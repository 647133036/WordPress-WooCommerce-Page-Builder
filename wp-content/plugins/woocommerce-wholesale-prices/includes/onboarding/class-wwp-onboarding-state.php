<?php
/**
 * Onboarding checklist state store.
 *
 * Owns every persisted bit of onboarding state so the rest of the backbone (data-contract
 * assembler, REST controller) and the consuming sub-issues read/write state through one place
 * rather than touching options directly:
 *
 * - Card-level state (whole-card dismissal, per-step first-completion timestamps, per-section
 *   one-time flags, install/first-seen timestamp, re-entry count) lives in the per-site option
 *   {@see WWP_ONBOARDING_STATE} as a single array to keep the options table lean.
 * - Per-user UI state (which sections a user has collapsed) lives in BLOG-PREFIXED user meta so
 *   the same account keeps independent state on each site of a multisite network, mirroring how
 *   WordPress scopes the `capabilities` meta key.
 *
 * Scaffold for epic #1031. Consumed by: WWP-section detectors (#1034), activation/completion
 * emails (#1035), telemetry (#1036) and — via the REST controller — the card UI renderer (#1033).
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1032
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Onboarding_State' ) ) {

    /**
     * Reads and writes the onboarding checklist's persisted state.
     *
     * @since 2.3.0
     */
    class WWP_Onboarding_State {

        /**
         * Single main instance of WWP_Onboarding_State.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Onboarding_State|null
         */
        private static $_instance = null;

        /**
         * Ensure only one instance of WWP_Onboarding_State is loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @return WWP_Onboarding_State
         */
        public static function instance() {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Default shape of the card-level state option.
         *
         * @since 2.3.0
         * @access private
         *
         * @return array{dismissed:bool,install_ts:int,completed_ts:array<string,int>,celebrated:array<string,int>,reentry_count:int}
         */
        private function default_state() {
            return array(
                'dismissed'     => false,
                'install_ts'    => 0,
                'completed_ts'  => array(),
                'celebrated'    => array(),
                'reentry_count' => 0,
            );
        }

        /**
         * Get the full card-level state, defaults merged over whatever is stored.
         *
         * @since 2.3.0
         * @access public
         *
         * @return array The card-level state array.
         */
        public function get_state() {
            $stored = get_option( WWP_ONBOARDING_STATE, array() );

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            $state = wp_parse_args( $stored, $this->default_state() );

            // Guard the nested maps against a corrupted option value.
            $state['completed_ts']  = is_array( $state['completed_ts'] ) ? $state['completed_ts'] : array();
            $state['celebrated']    = is_array( $state['celebrated'] ) ? $state['celebrated'] : array();
            $state['dismissed']     = (bool) $state['dismissed'];
            $state['install_ts']    = (int) $state['install_ts'];
            $state['reentry_count'] = (int) $state['reentry_count'];

            return $state;
        }

        /**
         * Persist the card-level state.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $state The full state array to store.
         * @return bool True if the option value changed, false otherwise.
         */
        private function save_state( $state ) {
            return update_option( WWP_ONBOARDING_STATE, $state, false );
        }

        /**
         * Whether the whole card has been dismissed.
         *
         * @since 2.3.0
         * @access public
         *
         * @return bool
         */
        public function is_dismissed() {
            $state = $this->get_state();

            return (bool) $state['dismissed'];
        }

        /**
         * Set (or clear) the whole-card dismissal flag.
         *
         * Re-opening a dismissed card (a dismissed → shown transition) increments the re-entry
         * counter that onboarding telemetry reports, so the write and the count stay in one place and
         * no caller has to remember to bump it.
         *
         * @since 2.3.0
         * @access public
         *
         * @param bool $dismissed Whether the card is dismissed.
         * @return bool True if the stored value changed.
         */
        public function set_dismissed( $dismissed ) {
            $state     = $this->get_state();
            $dismissed = (bool) $dismissed;

            // A re-entry is the card being shown again after it was dismissed.
            if ( $state['dismissed'] && ! $dismissed ) {
                ++$state['reentry_count'];
            }

            $state['dismissed'] = $dismissed;

            return $this->save_state( $state );
        }

        /**
         * How many times the card has been re-opened after being dismissed.
         *
         * @since 2.3.0
         * @access public
         *
         * @return int
         */
        public function get_reentry_count() {
            $state = $this->get_state();

            return (int) $state['reentry_count'];
        }

        /**
         * Get the install/first-seen timestamp, lazily setting it on first read.
         *
         * The onboarding card measures "time since setup began" from this value, so it must be the
         * moment the store first became aware of the card rather than the plugin's own install time.
         *
         * @since 2.3.0
         * @access public
         *
         * @return int Unix timestamp (UTC).
         */
        public function get_install_ts() {
            $state = $this->get_state();

            if ( $state['install_ts'] > 0 ) {
                return $state['install_ts'];
            }

            $state['install_ts'] = time();
            $this->save_state( $state );

            return $state['install_ts'];
        }

        /**
         * First-completion timestamp for a step, or 0 if never completed.
         *
         * @since 2.3.0
         * @access public
         *
         * @param string $step_id Step identifier.
         * @return int Unix timestamp (UTC), or 0.
         */
        public function get_completed_ts( $step_id ) {
            $state = $this->get_state();

            return isset( $state['completed_ts'][ $step_id ] ) ? (int) $state['completed_ts'][ $step_id ] : 0;
        }

        /**
         * Record a step's first completion. First write wins: a step already completed keeps its
         * original timestamp so "completed on" never moves if the step is re-detected later.
         *
         * @since 2.3.0
         * @access public
         *
         * @param string $step_id Step identifier.
         * @return int The stored completion timestamp (the original if it already existed).
         */
        public function mark_completed( $step_id ) {
            $step_id = (string) $step_id;
            $state   = $this->get_state();

            if ( isset( $state['completed_ts'][ $step_id ] ) && (int) $state['completed_ts'][ $step_id ] > 0 ) {
                return (int) $state['completed_ts'][ $step_id ];
            }

            $now                               = time();
            $state['completed_ts'][ $step_id ] = $now;
            $this->save_state( $state );

            return $now;
        }

        /**
         * Whether a section's one-time celebration has already fired.
         *
         * @since 2.3.0
         * @access public
         *
         * @param string $section_plugin Section identifier (the registering plugin's slug).
         * @return bool
         */
        public function is_celebrated( $section_plugin ) {
            $state = $this->get_state();

            return ! empty( $state['celebrated'][ $section_plugin ] );
        }

        /**
         * Mark a section's one-time celebration as fired. First write wins.
         *
         * @since 2.3.0
         * @access public
         *
         * @param string $section_plugin Section identifier (the registering plugin's slug).
         * @return int The stored celebration timestamp (the original if it already existed).
         */
        public function mark_celebrated( $section_plugin ) {
            $section_plugin = (string) $section_plugin;
            $state          = $this->get_state();

            if ( ! empty( $state['celebrated'][ $section_plugin ] ) ) {
                return (int) $state['celebrated'][ $section_plugin ];
            }

            $now                                    = time();
            $state['celebrated'][ $section_plugin ] = $now;
            $this->save_state( $state );

            return $now;
        }

        /**
         * The blog-prefixed user-meta key for per-user UI state.
         *
         * Prefixing with {@see wpdb::get_blog_prefix()} scopes the value to the current site so the
         * same account collapses sections independently on each site of a network.
         *
         * @since 2.3.0
         * @access private
         *
         * @return string
         */
        private function ui_state_meta_key() {
            global $wpdb;

            return $wpdb->get_blog_prefix() . WWP_ONBOARDING_UI_STATE_META;
        }

        /**
         * Get a user's per-site UI state (which sections they have collapsed).
         *
         * @since 2.3.0
         * @access public
         *
         * @param int $user_id User ID.
         * @return array{collapsed:array<string,bool>}
         */
        public function get_ui_state( $user_id ) {
            $stored = get_user_meta( (int) $user_id, $this->ui_state_meta_key(), true );

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            $collapsed = isset( $stored['collapsed'] ) && is_array( $stored['collapsed'] ) ? $stored['collapsed'] : array();

            return array( 'collapsed' => array_map( 'boolval', $collapsed ) );
        }

        /**
         * Set the collapsed flag for one section in a user's per-site UI state.
         *
         * @since 2.3.0
         * @access public
         *
         * @param int    $user_id        User ID.
         * @param string $section_plugin Section identifier (the registering plugin's slug).
         * @param bool   $collapsed      Whether that section is collapsed.
         * @return bool True on success.
         */
        public function set_section_collapsed( $user_id, $section_plugin, $collapsed ) {
            $state = $this->get_ui_state( $user_id );
            $state['collapsed'][ (string) $section_plugin ] = (bool) $collapsed;

            return (bool) update_user_meta( (int) $user_id, $this->ui_state_meta_key(), $state );
        }
    }
}
