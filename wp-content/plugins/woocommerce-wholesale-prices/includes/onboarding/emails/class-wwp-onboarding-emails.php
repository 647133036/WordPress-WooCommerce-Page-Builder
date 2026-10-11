<?php
/**
 * Onboarding emails coordinator.
 *
 * Registers WWP's two behavior-triggered onboarding emails with WooCommerce and owns the logic that
 * fires each exactly once:
 *
 * - The **activation** email, when the first wholesale price is saved on a product (the onboarding
 *   activation event — distinct from the celebrated first-order milestone).
 * - The **completion** email, when WWP's onboarding section reaches 100% (every counted step
 *   complete, the first-order milestone included).
 *
 * Both are opt-out-able through the standard WooCommerce email settings (each email's `enabled`
 * toggle) and are **never calendar-scheduled**. Firing is driven by the real store events — a
 * wholesale price saved (activation), and an order taken / customer approved / the manual "see your
 * store" step attested (completion) — plus an `admin_init` catch-all for that attestation, which is
 * recorded over REST with no hook of its own. Each event re-reads the same assembled onboarding
 * payload the card renders from, so the "truth" is the already-shipped data contract, reused rather
 * than re-detected.
 *
 * Both conditions are also **suppressed retroactively**: a ship-time baseline is seeded once, at
 * plugin-load in an admin/CLI request — before any save handler in that request can write a price — and
 * any condition already satisfied then is recorded as `suppressed` (never sent). So a store that
 * upgraded into this feature already past a threshold is not re-prompted; only a genuine transition
 * afterwards sends. (The one narrow gap: a brand-new store that sets its very first price over pure
 * REST before any admin or WP-CLI request has loaded would be seeded from that post-price state and
 * miss the activation email.)
 *
 * Part of epic #1031. Consumes the assembled payload from {@see WWP_Onboarding_Data_Contract} and the
 * per-site send-once option {@see WWP_ONBOARDING_EMAILS}; owns no shared scaffold (#1032) piece.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1035
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Onboarding_Emails' ) ) {

    /**
     * Registers and fires WWP's onboarding emails.
     *
     * @since 2.3.0
     */
    class WWP_Onboarding_Emails {

        /**
         * WWP's own onboarding section identifier.
         *
         * @since 2.3.0
         * @var string
         */
        const SECTION = 'wwp';

        /**
         * Step id whose completion is the activation event (first wholesale price saved).
         *
         * @since 2.3.0
         * @var string
         */
        const ACTIVATION_STEP = 'wholesale_price_set';

        /**
         * WC_Email id of the activation email.
         *
         * @since 2.3.0
         * @var string
         */
        const ACTIVATION_EMAIL = 'WWP_Email_Onboarding_Activation';

        /**
         * WC_Email id of the completion email.
         *
         * @since 2.3.0
         * @var string
         */
        const COMPLETION_EMAIL = 'WWP_Email_Onboarding_Completion';

        /**
         * Single main instance.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Onboarding_Emails|null
         */
        private static $_instance = null;

        /**
         * Ensure only one instance is loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @return WWP_Onboarding_Emails
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
         * @since 2.3.0
         * @access public
         */
        public function run() {
            add_filter( 'woocommerce_email_classes', array( $this, 'register_emails' ) );

            // Seed the no-retroactive baseline once, early on `init` in an admin/CLI request — after
            // WooCommerce has loaded (so assembling the section is safe) but before any save handler in
            // the request can write a price — so an install already past a threshold is captured as
            // "suppressed" rather than emailed. Frontend requests skip it (see the callback); a store is
            // never set up over the frontend before an admin/CLI request has loaded.
            add_action( 'init', array( $this, 'maybe_seed_baseline_on_load' ) );

            // Fire on the real transitions: a wholesale price saved (activation), and the events that
            // can complete the section — an order taken, a customer approved. The admin_init catch-all
            // also covers the manual "see your store" step, which is attested over REST with no hook.
            add_action( 'save_post_product', array( $this, 'maybe_dispatch' ) );
            add_action( 'woocommerce_save_product_variation', array( $this, 'maybe_dispatch' ) );
            add_action( 'woocommerce_new_order', array( $this, 'maybe_dispatch' ) );
            add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_dispatch' ) );
            add_action( 'set_user_role', array( $this, 'maybe_dispatch' ) );
            add_action( 'add_user_role', array( $this, 'maybe_dispatch' ) );
            add_action( 'admin_init', array( $this, 'maybe_dispatch_admin' ) );
        }

        /**
         * Register WWP's onboarding email classes with WooCommerce.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $emails Registered WC_Email instances, keyed by class name.
         * @return array
         */
        public function register_emails( $emails ) {
            require_once WWP_INCLUDES_PATH . 'onboarding/emails/class-wwp-email-onboarding-activation.php';
            require_once WWP_INCLUDES_PATH . 'onboarding/emails/class-wwp-email-onboarding-completion.php';

            if ( class_exists( 'WWP_Email_Onboarding_Activation' ) ) {
                $emails[ self::ACTIVATION_EMAIL ] = new WWP_Email_Onboarding_Activation();
            }

            if ( class_exists( 'WWP_Email_Onboarding_Completion' ) ) {
                $emails[ self::COMPLETION_EMAIL ] = new WWP_Email_Onboarding_Completion();
            }

            return $emails;
        }

        /**
         * `admin_init` entry point: a catch-all so the manual "see your store" step (attested over
         * REST with no hook) and any transition missed by the event hooks still fire.
         *
         * Skips admin-ajax — notably the WP Heartbeat poll — so the evaluation runs on real admin page
         * loads, not on every background tick while onboarding is still in progress.
         *
         * @since 2.3.0
         * @access public
         */
        public function maybe_dispatch_admin() {
            if ( wp_doing_ajax() ) {
                return;
            }

            $this->maybe_dispatch();
        }

        /**
         * `init` entry point for the eager baseline seed: only admin and WP-CLI requests seed, so a
         * frontend page load pays nothing. A store is never set up over the frontend before an
         * admin/CLI request, so this still captures the baseline before the first price can be saved.
         *
         * @since 2.3.0
         * @access public
         */
        public function maybe_seed_baseline_on_load() {
            if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
                $this->maybe_seed_baseline();
            }
        }

        /**
         * Record the ship-time baseline once: any condition already satisfied the first time this runs
         * is marked `suppressed`, so a store that upgraded into the feature already past a threshold is
         * never retroactively emailed. Sends nothing — it only captures the starting point.
         *
         * @since 2.3.0
         * @access public
         */
        public function maybe_seed_baseline() {
            if ( is_array( get_option( WWP_ONBOARDING_EMAILS, null ) ) ) {
                return;
            }

            $conditions = $this->evaluate_conditions();

            update_option(
                WWP_ONBOARDING_EMAILS,
                array(
                    'activation' => $conditions['activation'] ? 'suppressed' : 'pending',
                    'completion' => $conditions['completion'] ? 'suppressed' : 'pending',
                ),
                false
            );
        }

        /**
         * Send either email on a genuine pending → satisfied transition.
         *
         * Returns early once both emails are resolved (sent or suppressed) so the steady-state cost of
         * a repeated event is a single option read. The send is claimed (written `sent`) before it is
         * dispatched, so two overlapping requests cannot both send it.
         *
         * @since 2.3.0
         * @access public
         */
        public function maybe_dispatch() {
            $stored = get_option( WWP_ONBOARDING_EMAILS, null );

            // Not seeded yet — e.g. a frontend order arriving before any admin/CLI request. Capture the
            // baseline and send nothing: a just-satisfied condition here cannot be told apart from
            // pre-existing state.
            if ( ! is_array( $stored ) ) {
                $this->maybe_seed_baseline();

                return;
            }

            // Steady state: both emails already resolved, nothing left to evaluate.
            if ( 'pending' !== $this->status( $stored, 'activation' ) && 'pending' !== $this->status( $stored, 'completion' ) ) {
                return;
            }

            // A send needs the mailer. If it is not up yet, bail and retry on a later event rather than
            // resolving an email that never went out.
            if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
                return;
            }

            $conditions = $this->evaluate_conditions();
            $state      = $stored;

            foreach ( array( 'activation', 'completion' ) as $key ) {
                if ( 'pending' !== $this->status( $state, $key ) || ! $conditions[ $key ] ) {
                    continue;
                }

                // Claim the send before dispatching so an overlapping request cannot send it twice.
                $state[ $key ] = 'sent';
                update_option( WWP_ONBOARDING_EMAILS, $state, false );

                $this->send( $key );
            }
        }

        /**
         * Read one email's status out of the stored state, defaulting to `pending`.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array  $state Stored state.
         * @param string $key   `activation` | `completion`.
         * @return string
         */
        private function status( $state, $key ) {
            return isset( $state[ $key ] ) ? (string) $state[ $key ] : 'pending';
        }

        /**
         * Evaluate the current activation/completion conditions from the assembled onboarding payload.
         *
         * Reuses the shipped data contract as the single source of truth: activation = WWP's
         * wholesale-price step is complete; completion = every counted WWP step is complete.
         *
         * @since 2.3.0
         * @access private
         *
         * @return array{activation:bool,completion:bool}
         */
        private function evaluate_conditions() {
            $section = $this->wwp_section();

            if ( null === $section ) {
                return array(
                    'activation' => false,
                    'completion' => false,
                );
            }

            return array(
                'activation' => $this->step_is_complete( $section, self::ACTIVATION_STEP ),
                'completion' => WWP_Onboarding_Data_Contract::instance()->section_is_complete( $section ),
            );
        }

        /**
         * The assembled WWP section, or null if it is not present.
         *
         * @since 2.3.0
         * @access private
         *
         * @return array|null
         */
        private function wwp_section() {
            $payload  = WWP_Onboarding_Data_Contract::instance()->assemble();
            $sections = isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array();

            foreach ( $sections as $section ) {
                if ( isset( $section['plugin'] ) && self::SECTION === $section['plugin'] ) {
                    return $section;
                }
            }

            return null;
        }

        /**
         * Whether a specific step in a section is complete.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array  $section Assembled section.
         * @param string $step_id Step id.
         * @return bool
         */
        private function step_is_complete( $section, $step_id ) {
            $steps = isset( $section['steps'] ) && is_array( $section['steps'] ) ? $section['steps'] : array();

            foreach ( $steps as $step ) {
                if ( isset( $step['id'] ) && $step_id === $step['id'] ) {
                    return 'complete' === ( $step['status'] ?? '' );
                }
            }

            return false;
        }

        /**
         * Trigger one of the two emails through WooCommerce's mailer.
         *
         * @since 2.3.0
         * @access private
         *
         * The caller has already confirmed the mailer is available; WooCommerce still honours each
         * email's own enable toggle and recipient inside trigger().
         *
         * @param string $key `activation` | `completion`.
         */
        private function send( $key ) {
            $class  = 'activation' === $key ? self::ACTIVATION_EMAIL : self::COMPLETION_EMAIL;
            $emails = WC()->mailer()->get_emails();

            if ( isset( $emails[ $class ] ) ) {
                $emails[ $class ]->trigger();
            }
        }
    }
}
