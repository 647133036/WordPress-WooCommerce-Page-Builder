<?php
/**
 * Onboarding checklist data contract.
 *
 * This class IS the cross-repo contract. Every Wholesale Suite plugin (WWPP #2038, WWQ #222,
 * WWOF #1092, WWLC #626, WPay #273) registers ONE section against the
 * {@see WWP_Onboarding_Data_Contract::FILTER} filter; WWP owns the single generic renderer and
 * every sibling is data-only. Because a newer sibling must never break an older WWP, intake is
 * defensively normalised here: malformed sections are dropped, empty sections skipped, and unknown
 * fields ignored, so the schema can grow additively without a hard version gate.
 *
 * The assembler layers WWP-owned envelope state (whole-card `dismissed`, per-section `celebrated`
 * and `collapsed`) over the normalised sections and overrides the `status` of user-attested steps
 * (`completion: manual`, or a `copy` action) from WWP's own completion store rather than trusting a
 * sibling detector for something only the user can confirm.
 *
 * Scaffold for epic #1031. Consumed by: the card UI renderer (#1033, via REST), the WWP-section
 * detectors (#1034) and telemetry (#1036).
 *
 * ## Section schema (each sibling appends ONE)
 * - `plugin`        string  Required, unique. Registering plugin's slug; identifies the section.
 * - `label`         string  Human-readable section title.
 * - `priority`      int     Sort order, ascending. Default 10.
 * - `activation_ts` int     Unix timestamp the sibling was activated. Default 0.
 * - `steps`         array   Ordered list of step objects (see below). Required, non-empty.
 *
 * ## Step schema
 * - `id`           string  Required, unique within the section.
 * - `title`        string  Human-readable, verb-first step title.
 * - `subtitle`     string  Short status line shown under the title, always visible (e.g.
 *                          "Detected in your store"). Default ''.
 * - `description`  string  Longer detail shown when the row is expanded. Default ''.
 * - `status`       string  `complete` | `current` | `todo` | `waiting`. Default `todo`. Resolved
 *                          server-side by the sibling's detector; WWP overrides user-attested steps.
 * - `completion`   string  `auto` | `manual` | `milestone`. Default `auto`. `manual` steps are
 *                          user-attested and resolved from WWP's completion store.
 * - `counts`       bool    Whether the step counts toward the section's N-of-M. Default true.
 * - `action`       array   Optional `{ type: link|copy, url?, value?, label? }`.
 * - `estimate`     string  Optional human effort hint (e.g. "2 min"). Default ''.
 * - `completed_ts` int     Unix timestamp of first completion. Default 0; overridden for
 *                          user-attested steps from the store.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1032
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Onboarding_Data_Contract' ) ) {

    /**
     * Registers, normalises and assembles the onboarding checklist payload.
     *
     * @since 2.3.0
     */
    class WWP_Onboarding_Data_Contract {

        /**
         * The filter siblings register their section against.
         *
         * @since 2.3.0
         * @var string
         */
        const FILTER = 'wws_onboarding_register_checklist';

        /**
         * Allowed values for a step's `completion` field.
         *
         * @since 2.3.0
         * @var string[]
         */
        const COMPLETION_MODES = array( 'auto', 'manual', 'milestone' );

        /**
         * Allowed values for a step's `status` field.
         *
         * Resolved server-side by each sibling's own detector (for `auto`/`milestone` steps) and
         * overridden by WWP for user-attested steps. `todo` is the default for a step that is not
         * yet done and is not the highlighted next action; `current` is the single "do this now"
         * step the card expands; `waiting` is a milestone that has not yet fired.
         *
         * @since 2.3.0
         * @var string[]
         */
        const STATUSES = array( 'complete', 'current', 'todo', 'waiting' );

        /**
         * Single main instance of WWP_Onboarding_Data_Contract.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Onboarding_Data_Contract|null
         */
        private static $_instance = null;

        /**
         * Ensure only one instance is loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @return WWP_Onboarding_Data_Contract
         */
        public static function instance() {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Collect every sibling-registered section and normalise it.
         *
         * @since 2.3.0
         * @access public
         *
         * @return array List of normalised, priority-sorted section arrays.
         */
        public function get_registered_sections() {
            /**
             * Register an onboarding checklist section.
             *
             * Each Wholesale Suite plugin appends exactly one section array. WWP normalises the
             * result defensively, so a section that does not match the schema is dropped rather than
             * breaking the card.
             *
             * @since 2.3.0
             *
             * @param array $sections Sections registered so far.
             */
            $sections = apply_filters( self::FILTER, array() );

            return $this->normalise( is_array( $sections ) ? $sections : array() );
        }

        /**
         * Normalise a raw list of sections: drop malformed, skip empty, ignore unknown fields.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $sections Raw sections as registered.
         * @return array Clean, priority-sorted sections.
         */
        public function normalise( $sections ) {
            $clean = array();
            $seen  = array();

            foreach ( $sections as $section ) {
                $normalised = $this->normalise_section( $section );

                if ( null === $normalised ) {
                    continue;
                }

                // Ignore a duplicate section identity; first registration wins.
                if ( isset( $seen[ $normalised['plugin'] ] ) ) {
                    continue;
                }

                $seen[ $normalised['plugin'] ] = true;
                $clean[]                       = $normalised;
            }

            usort(
                $clean,
                static function ( $a, $b ) {
                    return $a['priority'] <=> $b['priority'];
                }
            );

            return $clean;
        }

        /**
         * Normalise a single section, or return null if it is unusable.
         *
         * A section is dropped when it is not an array, has no non-empty string `plugin` identity,
         * or has no valid steps left after normalising (skip empty).
         *
         * @since 2.3.0
         * @access private
         *
         * @param mixed $section Raw section.
         * @return array|null Normalised section, or null to drop it.
         */
        private function normalise_section( $section ) {
            if ( ! is_array( $section ) ) {
                return null;
            }

            $plugin = isset( $section['plugin'] ) && is_string( $section['plugin'] ) ? trim( $section['plugin'] ) : '';

            if ( '' === $plugin ) {
                return null;
            }

            $raw_steps = isset( $section['steps'] ) && is_array( $section['steps'] ) ? $section['steps'] : array();
            $steps     = array();
            $step_ids  = array();

            foreach ( $raw_steps as $raw_step ) {
                $step = $this->normalise_step( $raw_step );

                if ( null === $step || isset( $step_ids[ $step['id'] ] ) ) {
                    continue;
                }

                $step_ids[ $step['id'] ] = true;
                $steps[]                 = $step;
            }

            // Skip a section with no usable steps.
            if ( empty( $steps ) ) {
                return null;
            }

            // Only known fields survive; unknown fields are ignored.
            return array(
                'plugin'        => $plugin,
                'label'         => isset( $section['label'] ) && is_string( $section['label'] ) ? $section['label'] : '',
                'priority'      => isset( $section['priority'] ) ? (int) $section['priority'] : 10,
                'activation_ts' => isset( $section['activation_ts'] ) ? (int) $section['activation_ts'] : 0,
                'steps'         => $steps,
            );
        }

        /**
         * Normalise a single step, or return null if it is unusable.
         *
         * A step is dropped when it is not an array or has no non-empty string `id`.
         *
         * @since 2.3.0
         * @access private
         *
         * @param mixed $step Raw step.
         * @return array|null Normalised step, or null to drop it.
         */
        private function normalise_step( $step ) {
            if ( ! is_array( $step ) ) {
                return null;
            }

            $id = isset( $step['id'] ) && is_string( $step['id'] ) ? trim( $step['id'] ) : '';

            if ( '' === $id ) {
                return null;
            }

            $completion = isset( $step['completion'] ) && in_array( $step['completion'], self::COMPLETION_MODES, true )
                ? $step['completion']
                : 'auto';

            $status = isset( $step['status'] ) && in_array( $step['status'], self::STATUSES, true )
                ? $step['status']
                : 'todo';

            return array(
                'id'           => $id,
                'title'        => isset( $step['title'] ) && is_string( $step['title'] ) ? $step['title'] : '',
                'subtitle'     => isset( $step['subtitle'] ) && is_string( $step['subtitle'] ) ? $step['subtitle'] : '',
                'description'  => isset( $step['description'] ) && is_string( $step['description'] ) ? $step['description'] : '',
                'status'       => $status,
                'completion'   => $completion,
                'counts'       => isset( $step['counts'] ) ? (bool) $step['counts'] : true,
                'action'       => $this->normalise_action( $step['action'] ?? null ),
                'estimate'     => isset( $step['estimate'] ) && is_string( $step['estimate'] ) ? $step['estimate'] : '',
                'completed_ts' => isset( $step['completed_ts'] ) ? (int) $step['completed_ts'] : 0,
            );
        }

        /**
         * Normalise a step's optional action, or return null when there is none.
         *
         * @since 2.3.0
         * @access private
         *
         * @param mixed $action Raw action.
         * @return array|null `{ type, url, value, label }`, or null.
         */
        private function normalise_action( $action ) {
            if ( ! is_array( $action ) ) {
                return null;
            }

            $type = isset( $action['type'] ) && in_array( $action['type'], array( 'link', 'copy' ), true ) ? $action['type'] : '';

            if ( '' === $type ) {
                return null;
            }

            return array(
                'type'  => $type,
                // Sanitize the URL at the contract boundary: sibling section data is untrusted, and
                // this value is bound to an <a href> in the card. esc_url_raw() strips dangerous
                // schemes (javascript:, data:) so a sibling cannot inject an admin-context XSS sink.
                'url'   => isset( $action['url'] ) && is_string( $action['url'] ) ? esc_url_raw( $action['url'] ) : '',
                'value' => isset( $action['value'] ) && is_string( $action['value'] ) ? $action['value'] : '',
                'label' => isset( $action['label'] ) && is_string( $action['label'] ) ? $action['label'] : '',
            );
        }

        /**
         * Whether a step's completion is user-attested (resolved from WWP's store, not a detector).
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $step A normalised step.
         * @return bool
         */
        public function is_user_attested_step( $step ) {
            if ( 'manual' === ( $step['completion'] ?? '' ) ) {
                return true;
            }

            return 'copy' === ( $step['action']['type'] ?? '' );
        }

        /**
         * The completion-store key for a step, namespaced by section so ids never collide.
         *
         * @since 2.3.0
         * @access public
         *
         * @param string $plugin  Section identifier.
         * @param string $step_id Step identifier.
         * @return string
         */
        public function step_completion_key( $plugin, $step_id ) {
            return $plugin . ':' . $step_id;
        }

        /**
         * Assemble the full payload the card renders: normalised sections plus WWP-owned envelope
         * state, with user-attested step statuses resolved from the completion store.
         *
         * @since 2.3.0
         * @access public
         *
         * @param int $user_id User whose per-section collapse state to apply. Defaults to current user.
         * @return array{dismissed:bool,insights_optin:?string,sections:array}
         */
        public function assemble( $user_id = 0 ) {
            $user_id  = $user_id ? (int) $user_id : get_current_user_id();
            $state    = WWP_Onboarding_State::instance();
            $ui_state = $state->get_ui_state( $user_id );
            $sections = $this->get_registered_sections();

            // Read the card-level state once and resolve every section/step lookup from this
            // snapshot, rather than re-normalising the option through an accessor per section.
            $snapshot     = $state->get_state();
            $completed_ts = $snapshot['completed_ts'];
            $celebrated   = $snapshot['celebrated'];

            foreach ( $sections as &$section ) {
                $plugin = $section['plugin'];

                foreach ( $section['steps'] as &$step ) {
                    if ( ! $this->is_user_attested_step( $step ) ) {
                        continue;
                    }

                    // A user-attested step's truth is WWP's store, not the sibling's detector.
                    $key = $this->step_completion_key( $plugin, $step['id'] );
                    $ts  = isset( $completed_ts[ $key ] ) ? (int) $completed_ts[ $key ] : 0;

                    if ( $ts > 0 ) {
                        $step['status']       = 'complete';
                        $step['completed_ts'] = $ts;
                    }
                }
                unset( $step );

                $section['celebrated'] = ! empty( $celebrated[ $plugin ] );
                $section['collapsed']  = ! empty( $ui_state['collapsed'][ $plugin ] );
            }
            unset( $section );

            return array(
                'dismissed'      => (bool) $snapshot['dismissed'],
                'insights_optin' => $this->insights_optin(),
                'sections'       => $sections,
            );
        }

        /**
         * Whether an assembled section is at 100%: every counting step is complete and at least one
         * counts.
         *
         * This is the card's N-of-M rule (denominator = `counts === true`, numerator = those that are
         * `complete`) expressed once, so every consumer — the card, the WooCommerce task entry (#1037)
         * and the completion email (#1035) — judges "done" identically and can never drift.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $section Assembled section (as produced by {@see self::assemble()}).
         * @return bool
         */
        public function section_is_complete( $section ) {
            $total     = 0;
            $completed = 0;
            $steps     = isset( $section['steps'] ) && is_array( $section['steps'] ) ? $section['steps'] : array();

            foreach ( $steps as $step ) {
                if ( empty( $step['counts'] ) ) {
                    continue;
                }

                ++$total;

                if ( 'complete' === ( $step['status'] ?? '' ) ) {
                    ++$completed;
                }
            }

            return $total > 0 && $total === $completed;
        }

        /**
         * The user's anonymous-usage (insights) choice, for the card's opt-in row.
         *
         * The row is a second surface for the same consent the legacy admin notice and the settings
         * page write to `wwp_anonymous_data`. Returns `'yes'`/`'no'` once a choice has been made, or
         * `null` when it has not — the card shows the opt-in row only in the `null` case, so a user who
         * has already answered anywhere is never re-prompted. Historic truthy values (the admin notice
         * once stored the int `1`) normalise to `'yes'`.
         *
         * @since 2.3.0
         * @access private
         *
         * @return string|null `'yes'`, `'no'`, or null when unanswered.
         */
        private function insights_optin() {
            $consent = get_option( 'wwp_anonymous_data', null );

            if ( null === $consent || false === $consent || '' === $consent ) {
                return null;
            }

            return 'no' === $consent ? 'no' : 'yes';
        }
    }
}
