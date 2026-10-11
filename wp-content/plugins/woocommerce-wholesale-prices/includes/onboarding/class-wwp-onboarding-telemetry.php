<?php
/**
 * Onboarding telemetry.
 *
 * Contributes the `onboarding` object to WWP's weekly opt-in usage check-in
 * ({@see WWP_Usage}). It is a **snapshot**, not an event stream: {@see self::get_snapshot()} projects
 * the already-shipped onboarding state store and assembled data contract into the card-level and
 * per-section fields the field-list contract defines, and {@see WWP_Usage::get_data()} folds it in.
 * Because it rides the existing weekly check-in, it is sent only when the site has opted in through
 * `wwp_anonymous_data` — the gate {@see WWP_Usage::send_checkin()} already enforces upstream.
 *
 * The one thing a snapshot cannot reconstruct after the fact is *when* an auto-detected step first
 * completed — the store records first-completion timestamps only for user-attested steps. So this
 * class also records first-completion timestamps for WWP's own counted steps at event time (product,
 * order and role transitions), writing them into the same completion store the assembler reads via
 * the scaffold's public {@see WWP_Onboarding_State::mark_completed()} (first write wins). That is what
 * makes `activation_ts` (first wholesale price saved) and `time_to_activation_days` measure to the
 * price-saved event rather than to whenever the weekly cron happens to run.
 *
 * Part of epic #1031. Consumes the state store and data contract from scaffold #1032; owns no shared
 * scaffold piece.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1036
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Onboarding_Telemetry' ) ) {

    /**
     * Records onboarding transition timestamps and builds the check-in snapshot.
     *
     * @since 2.3.0
     */
    class WWP_Onboarding_Telemetry {

        /**
         * WWP's own onboarding section identifier.
         *
         * @since 2.3.0
         * @var string
         */
        const SECTION = 'wwp';

        /**
         * Step id whose first completion is the activation event (first wholesale price saved).
         *
         * @since 2.3.0
         * @var string
         */
        const ACTIVATION_STEP = 'wholesale_price_set';

        /**
         * Step id whose first completion is the first wholesale order.
         *
         * @since 2.3.0
         * @var string
         */
        const FIRST_ORDER_STEP = 'first_order';

        /**
         * WWP step ids whose first completion the recorder stamps at event time.
         *
         * These are the auto/milestone steps from {@see WWP_Onboarding_WWP_Section::build_steps()}:
         * only they need an event-time stamp, because the store cannot otherwise reconstruct when they
         * first completed. `store_view` is deliberately excluded — it is user-attested over REST, which
         * already records its timestamp, so stamping it here would be a first-write-wins no-op. Keep in
         * sync if that section's auto/milestone steps change. (The per-section `steps` map still reports
         * every counted step, `store_view` included — see {@see self::build_sections()}.)
         *
         * @since 2.3.0
         * @var string[]
         */
        const RECORDED_STEPS = array(
            'role_created',
            self::ACTIVATION_STEP,
            'wholesale_customer',
            self::FIRST_ORDER_STEP,
        );

        /**
         * Single main instance.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Onboarding_Telemetry|null
         */
        private static $_instance = null;

        /**
         * Whether a tracked transition has already scheduled this request's reconciliation.
         *
         * @since 2.3.0
         * @access private
         * @var bool
         */
        private $reconcile_scheduled = false;

        /**
         * Ensure only one instance is loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @return WWP_Onboarding_Telemetry
         */
        public static function instance() {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Register hooks.
         *
         * The real store transitions that can flip a WWP step — a wholesale price saved (activation),
         * an order taken, a customer approved — are routed through {@see self::schedule_reconcile()},
         * which reconciles once per web request on `shutdown` (so a bulk import that fires the save hook
         * N times pays one payload assembly, reading the request's final state), and inline in a
         * CLI/cron process where `shutdown` is not a reliable boundary. The manual "see your store" step
         * is attested over REST, which already stamps the completion store, so it needs no hook here.
         *
         * @since 2.3.0
         * @access public
         */
        public function run() {
            add_action( 'save_post_product', array( $this, 'schedule_reconcile' ) );
            add_action( 'woocommerce_save_product_variation', array( $this, 'schedule_reconcile' ) );
            add_action( 'woocommerce_new_order', array( $this, 'schedule_reconcile' ) );
            add_action( 'woocommerce_order_status_changed', array( $this, 'schedule_reconcile' ) );
            add_action( 'set_user_role', array( $this, 'schedule_reconcile' ) );
            add_action( 'add_user_role', array( $this, 'schedule_reconcile' ) );
        }

        /**
         * On the first tracked transition of a web request, schedule a single `shutdown` reconciliation;
         * in a CLI/cron process (where `shutdown` may never fire) reconcile inline instead.
         *
         * Cheap by design: on the web path it sets an instance flag and hooks `shutdown` once, no matter
         * how many tracked events fire, so the expensive payload assembly happens at most once per request.
         *
         * @since 2.3.0
         * @access public
         */
        public function schedule_reconcile() {
            // WP-CLI, cron and Action Scheduler run many transitions in one long-lived process where
            // `shutdown` fires only once, at process end — and never at all if the process is killed by
            // a timeout, OOM, or a fatal in a later queued action. Deferring there would drop or
            // time-shift the first-completion stamp (and with it activation_ts / time_to_activation_days,
            // the metric this class exists to keep precise), so stamp inline in those contexts. The
            // steady-state short-circuit in record_transitions() keeps the inline call cheap.
            if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
                $this->record_transitions();
                return;
            }

            if ( $this->reconcile_scheduled ) {
                return;
            }

            $this->reconcile_scheduled = true;
            add_action( 'shutdown', array( $this, 'record_transitions' ) );
        }

        /**
         * Stamp the first-completion timestamp for any recorded WWP step that is now complete.
         *
         * First write wins, so a step keeps its original timestamp and re-detection never moves it.
         * Scheduled by {@see self::schedule_reconcile()} — deferred to `shutdown` on a web request (once,
         * however many transitions fired) or run inline in a CLI/cron process where `shutdown` is not a
         * reliable boundary. Short-circuits with a single option read once every {@see self::RECORDED_STEPS}
         * step is already stamped, so a finished onboarding assembles nothing.
         *
         * @since 2.3.0
         * @access public
         */
        public function record_transitions() {
            $state    = WWP_Onboarding_State::instance();
            $snapshot = $state->get_state();
            $contract = WWP_Onboarding_Data_Contract::instance();

            // Guard: skip assembling the payload once every recorded step is stamped.
            if ( $this->all_recorded_steps_stamped( $snapshot['completed_ts'], $contract ) ) {
                return;
            }

            $section = $this->wwp_section( $contract->assemble() );

            if ( null === $section ) {
                return;
            }

            foreach ( $section['steps'] as $step ) {
                if ( ! in_array( $step['id'], self::RECORDED_STEPS, true ) || 'complete' !== $step['status'] ) {
                    continue;
                }

                $state->mark_completed( $contract->step_completion_key( self::SECTION, $step['id'] ) );
            }
        }

        /**
         * Whether every recorded WWP step already has a stored first-completion timestamp.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array                        $completed_ts The completion-store map (`plugin:step_id` => timestamp).
         * @param WWP_Onboarding_Data_Contract $contract     The data contract, for the step-key format.
         * @return bool
         */
        private function all_recorded_steps_stamped( $completed_ts, $contract ) {
            foreach ( self::RECORDED_STEPS as $step_id ) {
                if ( empty( $completed_ts[ $contract->step_completion_key( self::SECTION, $step_id ) ] ) ) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Build the `onboarding` snapshot object for the weekly check-in.
         *
         * Card-level fields come from the state store; per-section progress is computed from the
         * assembled payload the card renders from, so telemetry counts exactly what the user sees.
         *
         * @since 2.3.0
         * @access public
         *
         * @return array The onboarding telemetry object.
         */
        public function get_snapshot() {
            $state    = WWP_Onboarding_State::instance();
            $contract = WWP_Onboarding_Data_Contract::instance();
            $snapshot = $state->get_state();
            $payload  = $contract->assemble();

            $install_ts    = (int) $snapshot['install_ts'];
            $activation_ts = (int) $state->get_completed_ts( $contract->step_completion_key( self::SECTION, self::ACTIVATION_STEP ) );
            $first_order   = (int) $state->get_completed_ts( $contract->step_completion_key( self::SECTION, self::FIRST_ORDER_STEP ) );

            return array(
                'install_ts'              => $install_ts,
                // Timestamps are null until reached — that is what powers the funnel questions.
                // Time-to-activation is measured to the price-saved event, never the first order.
                'activation_ts'           => $this->nullable_ts( $activation_ts ),
                'time_to_activation_days' => $this->days_between( $install_ts, $activation_ts ),
                'first_order_ts'          => $this->nullable_ts( $first_order ),
                'card_dismissed'          => (bool) $snapshot['dismissed'],
                'reentry_count'           => (int) $snapshot['reentry_count'],
                'sections'                => $this->build_sections( $payload, $snapshot, $contract, $activation_ts ),
            );
        }

        /**
         * Build the per-section telemetry rows from the assembled payload.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array                        $payload       The assembled onboarding payload.
         * @param array                        $snapshot      The card-level state snapshot.
         * @param WWP_Onboarding_Data_Contract $contract      The data contract (for completion keys).
         * @param int                          $activation_ts WWP's recorded price-saved timestamp.
         * @return array
         */
        private function build_sections( $payload, $snapshot, $contract, $activation_ts ) {
            $sections   = isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array();
            $state      = WWP_Onboarding_State::instance();
            $celebrated = $snapshot['celebrated'];
            $rows       = array();

            foreach ( $sections as $section ) {
                $plugin    = $section['plugin'];
                $total     = 0;
                $completed = 0;
                $steps     = array();

                foreach ( $section['steps'] as $step ) {
                    // Only counted steps carry progress; uncounted discovery rows never complete.
                    if ( empty( $step['counts'] ) ) {
                        continue;
                    }

                    $is_complete = 'complete' === $step['status'];

                    ++$total;

                    if ( $is_complete ) {
                        ++$completed;
                    }

                    // Prefer the recorded first-completion timestamp; fall back to whatever the
                    // section forwarded (siblings send their own via the contract's completed_ts).
                    $ts = (int) $state->get_completed_ts( $contract->step_completion_key( $plugin, $step['id'] ) );
                    $ts = $ts > 0 ? $ts : (int) $step['completed_ts'];

                    // Per the contract: one entry per counted step, `complete` plus a `completed_ts`
                    // that is null until the step is done.
                    $steps[ $step['id'] ] = array(
                        'complete'     => $is_complete,
                        'completed_ts' => $this->nullable_ts( $ts ),
                    );
                }

                // Siblings forward their own activation_ts via the contract; for WWP's own section,
                // surface the recorded price-saved timestamp so the row is self-consistent.
                $section_activation_ts = (int) $section['activation_ts'];
                if ( self::SECTION === $plugin && 0 === $section_activation_ts ) {
                    $section_activation_ts = $activation_ts;
                }

                // section_100_ts = when the section first reached 100%. The celebration flag is the
                // store's one-time "this section hit 100%" marker (set once, first-write-wins), so it
                // doubles as that timestamp.
                $section_100_ts = isset( $celebrated[ $plugin ] ) ? (int) $celebrated[ $plugin ] : 0;

                $rows[] = array(
                    'plugin'          => $plugin,
                    'completed_count' => $completed,
                    'total_count'     => $total,
                    'activation_ts'   => $this->nullable_ts( $section_activation_ts ),
                    'section_100_ts'  => $this->nullable_ts( $section_100_ts ),
                    'steps'           => $steps,
                );
            }

            return $rows;
        }

        /**
         * Find WWP's own section in an assembled payload.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $payload The assembled onboarding payload.
         * @return array|null
         */
        private function wwp_section( $payload ) {
            $sections = isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array();

            foreach ( $sections as $section ) {
                if ( isset( $section['plugin'] ) && self::SECTION === $section['plugin'] ) {
                    return $section;
                }
            }

            return null;
        }

        /**
         * Whole days between two timestamps, or null when either endpoint is unknown.
         *
         * @since 2.3.0
         * @access private
         *
         * @param int $from Earlier timestamp.
         * @param int $to   Later timestamp.
         * @return int|null Whole days elapsed, or null if not computable.
         */
        private function days_between( $from, $to ) {
            if ( $from <= 0 || $to <= 0 || $to < $from ) {
                return null;
            }

            return (int) floor( ( $to - $from ) / DAY_IN_SECONDS );
        }

        /**
         * A timestamp as an int, or null when it has not been reached.
         *
         * The telemetry field-list contract types every timestamp as "int or null", where null means
         * "not yet reached" — the state store's 0 sentinel becomes null at the payload boundary.
         *
         * @since 2.3.0
         * @access private
         *
         * @param int $ts A first-completion timestamp, 0 when never recorded.
         * @return int|null
         */
        private function nullable_ts( $ts ) {
            $ts = (int) $ts;

            return $ts > 0 ? $ts : null;
        }
    }
}
