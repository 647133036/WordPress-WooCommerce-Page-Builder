<?php
/**
 * AI / MCP settings tab.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_AI_MCP_Settings' ) ) {

    /**
     * Adds the AI / MCP child tab to the Wholesale Prices settings page.
     *
     * The tab promotes StoreAgent, which serves the Wholesale Suite abilities over MCP, and lets the
     * owner allow or block every Wholesale Suite write ability in StoreAgent's MCP allowlist at once.
     *
     * Every StoreAgent symbol used here is internal to StoreAgent (there is no public API for MCP
     * settings), so each call is guarded and the tab degrades to its promo state when one is missing.
     *
     * @since 2.3.0
     */
    class WWP_AI_MCP_Settings {

        /**
         * Settings child tab key.
         *
         * @since 2.3.0
         * @var string
         */
        const TAB_KEY = 'ai_mcp';

        /**
         * Trigger action slug for the write access toggle (`wwp_trigger_{slug}`).
         *
         * @since 2.3.0
         * @var string
         */
        const WRITE_ACCESS_ACTION = 'ai_mcp_write_access';

        /**
         * StoreAgent plugin slug on WordPress.org.
         *
         * @since 2.3.0
         * @var string
         */
        const STOREAGENT_SLUG = 'storeagent-ai-for-woocommerce';

        /**
         * StoreAgent plugin basename.
         *
         * @since 2.3.0
         * @var string
         */
        const STOREAGENT_FILE = 'storeagent-ai-for-woocommerce/storeagent-ai-for-woocommerce.php';

        /**
         * Tab sort position when WWPP is active: after Advanced (9), before Cache (10).
         *
         * @since 2.3.0
         * @var float
         */
        const SORT_WITH_WWPP = 9.5;

        /**
         * Tab sort position when WWPP is not active: after Admin Roles (4), before Help (5).
         *
         * @since 2.3.0
         * @var float
         */
        const SORT_WITHOUT_WWPP = 4.5;

        /**
         * Fallback for StoreAgent's MCP allowlist size limit (StoreAgent 1.1.9 `MCP::MAX_ALLOWLIST_ENTRIES`).
         *
         * @since 2.3.0
         * @var int
         */
        const DEFAULT_ALLOWLIST_LIMIT = 200;

        /**
         * StoreAgent state: plugin files are not present.
         *
         * @since 2.3.0
         * @var string
         */
        const STATE_NOT_INSTALLED = 'not_installed';

        /**
         * StoreAgent state: installed but not active.
         *
         * @since 2.3.0
         * @var string
         */
        const STATE_INACTIVE = 'inactive';

        /**
         * StoreAgent state: active, but older than the first release with MCP (1.1.9).
         *
         * @since 2.3.0
         * @var string
         */
        const STATE_OUTDATED = 'outdated';

        /**
         * StoreAgent state: active with MCP support, but not connected or MCP is off.
         *
         * @since 2.3.0
         * @var string
         */
        const STATE_SETUP = 'setup';

        /**
         * StoreAgent state: MCP is serving this store.
         *
         * @since 2.3.0
         * @var string
         */
        const STATE_READY = 'ready';

        /**
         * Property that holds the single main instance of WWP_AI_MCP_Settings.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_AI_MCP_Settings
         */
        private static $_instance;

        /**
         * Memoised result of {@see self::has_mcp_api()}. Null until first evaluated.
         *
         * @since 2.3.0
         * @access private
         * @var bool|null
         */
        private $has_mcp_api = null;

        /**
         * Ensure that only one instance of WWP_AI_MCP_Settings is loaded or can be loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @return WWP_AI_MCP_Settings
         */
        public static function instance() {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Add the AI / MCP child tab.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $tabs Settings tabs.
         * @return array
         */
        public function register_tab( $tabs ) {
            $tabs['wholesale_prices']['child'][ self::TAB_KEY ] = array(
                'sort'     => WWP_Helper_Functions::is_wwpp_active() ? self::SORT_WITH_WWPP : self::SORT_WITHOUT_WWPP,
                'key'      => self::TAB_KEY,
                'label'    => __( 'AI / MCP', 'woocommerce-wholesale-prices' ),
                'badge'    => __( 'New', 'woocommerce-wholesale-prices' ),
                'sections' => array(
                    'ai_mcp_options' => array(
                        'label' => '',
                        'desc'  => '',
                    ),
                ),
                'no_save'  => true,
            );

            return $tabs;
        }

        /**
         * Add the AI / MCP tab control.
         *
         * The control has no `id`, so it never becomes an allowed option key for the settings save route.
         * The panel data (which loads the abilities registry) is built only for the settings page render,
         * not for the save and action REST requests that also read the controls.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $controls Settings controls.
         * @return array
         */
        public function register_controls( $controls ) {
            $controls['wholesale_prices'][ self::TAB_KEY ]['ai_mcp_options'] = array(
                array_merge(
                    array( 'type' => 'ai_mcp' ),
                    doing_action( 'admin_enqueue_scripts' ) ? $this->get_panel_data() : array()
                ),
            );

            return $controls;
        }

        /**
         * Build the data the AI / MCP panel renders.
         *
         * @since 2.3.0
         * @access private
         *
         * @return array
         */
        private function get_panel_data() {
            $state          = $this->get_storeagent_state();
            $can_install    = current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' ) && wp_is_file_mod_allowed( 'wwp_install_plugin' );
            $write_names    = $this->get_wholesale_write_abilities();
            $allowlist      = $this->get_allowlist();
            $allowed_writes = array_intersect( $write_names, $allowlist );
            $is_connected   = in_array( $state, array( self::STATE_SETUP, self::STATE_READY ), true ) && \SAAI\Helpers\Connect::is_connected();
            $version        = defined( 'SAAI_VERSION' ) ? SAAI_VERSION : '';
            $mcp_url        = admin_url( 'admin.php?page=storeagent-settings&tab=mcp&subtab=general' );

            switch ( $state ) {
                case self::STATE_NOT_INSTALLED:
                    $cta = $can_install
                        ? array(
                            'action' => 'install',
                            'label'  => __( 'Install StoreAgent (Free)', 'woocommerce-wholesale-prices' ),
                            'note'   => __( 'Free on WordPress.org. Installs and activates in one click.', 'woocommerce-wholesale-prices' ),
                        )
                        : array(
                            'action' => 'link',
                            'label'  => __( 'Install from WordPress.org', 'woocommerce-wholesale-prices' ),
                            'url'    => $this->get_wporg_url(),
                            'note'   => __( 'Your site is configured to disallow plugin installation from the dashboard.', 'woocommerce-wholesale-prices' ),
                        );
                    break;

                case self::STATE_INACTIVE:
                    $cta = current_user_can( 'activate_plugins' )
                        ? array(
                            'action' => 'activate',
                            'label'  => __( 'Activate StoreAgent', 'woocommerce-wholesale-prices' ),
                            'note'   => __( 'StoreAgent is installed but not active.', 'woocommerce-wholesale-prices' ),
                        )
                        : array(
                            'action' => 'none',
                            'label'  => '',
                            'note'   => __( 'StoreAgent is installed but not active. Ask a site administrator to activate it.', 'woocommerce-wholesale-prices' ),
                        );
                    break;

                case self::STATE_OUTDATED:
                    $cta = array(
                        'action' => 'link',
                        'label'  => __( 'Update StoreAgent', 'woocommerce-wholesale-prices' ),
                        'url'    => admin_url( 'plugins.php' ),
                        /* translators: %s: StoreAgent version number. */
                        'note'   => sprintf( __( 'StoreAgent %s is active. MCP needs StoreAgent 1.1.9 or later.', 'woocommerce-wholesale-prices' ), $version ),
                    );
                    break;

                case self::STATE_SETUP:
                    $cta = array(
                        'action' => 'link',
                        'label'  => __( 'Set Up StoreAgent MCP', 'woocommerce-wholesale-prices' ),
                        'url'    => $is_connected ? $mcp_url : admin_url( 'admin.php?page=storeagent-dashboard' ),
                        /* translators: %s: StoreAgent version number. */
                        'note'   => sprintf( __( 'StoreAgent %s is active. Connect your StoreAgent account and turn on MCP to finish setup.', 'woocommerce-wholesale-prices' ), $version ),
                    );
                    break;

                default:
                    $cta = array(
                        'action' => 'link',
                        'label'  => __( 'Manage StoreAgent Connection', 'woocommerce-wholesale-prices' ),
                        'url'    => admin_url( 'admin.php?page=storeagent-settings&tab=mcp&subtab=connections' ),
                        /* translators: %s: StoreAgent version number. */
                        'note'   => sprintf( __( 'StoreAgent %s is active. Write abilities are only available to MCP connections you approve with read and write access.', 'woocommerce-wholesale-prices' ), $version ),
                    );
                    break;
            }

            $has_endpoint    = in_array( $state, array( self::STATE_SETUP, self::STATE_READY ), true );
            $endpoint_ready  = self::STATE_READY === $state;
            $cta['is_ready'] = $endpoint_ready;

            return array(
                'state'         => $state,
                'cta'           => $cta,
                'wporg_url'     => $this->get_wporg_url(),
                'plugin_slug'   => self::STOREAGENT_SLUG,
                'plugin_file'   => self::STOREAGENT_FILE,
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonces'        => array(
                    'install'  => $can_install ? wp_create_nonce( 'wwp_install_plugin' ) : '',
                    'activate' => current_user_can( 'activate_plugins' ) ? wp_create_nonce( 'wwp_activate_plugin' ) : '',
                ),
                'write_access'  => array(
                    'action'    => self::WRITE_ACCESS_ACTION,
                    // Defensive: WWP always registers a write ability, so a connected store never sees this gate.
                    'available' => $is_connected && ! empty( $write_names ),
                    'enabled'   => ! empty( $write_names ) && count( $allowed_writes ) === count( $write_names ),
                    'allowed'   => count( $allowed_writes ),
                    'total'     => count( $write_names ),
                ),
                'endpoint'      => array(
                    'available' => $has_endpoint,
                    'url'       => $has_endpoint
                        ? \SAAI\Helpers\MCP_Gateway_Client::get_endpoint_url()
                        : 'https://api.storeagent.ai/mcp/' . wp_parse_url( home_url(), PHP_URL_HOST ),
                    'help'      => $endpoint_ready
                        ? __( 'Paste this into any MCP client to connect it to your store.', 'woocommerce-wholesale-prices' )
                        : ( $has_endpoint
                            ? __( 'This is your store\'s endpoint. It works in MCP clients once you connect StoreAgent and turn on MCP.', 'woocommerce-wholesale-prices' )
                            : __( 'Available once StoreAgent 1.1.9 or later is installed and active.', 'woocommerce-wholesale-prices' ) ),
                ),
                'images_url'    => WWP_IMAGES_URL . 'ai-clients/',
                'abilities_url' => WWP_Helper_Functions::get_utm_url( 'kb/ai-abilities', 'wwp', 'settings', 'aimcpabilitiesdocs' ),
            );
        }

        /**
         * Get the StoreAgent state.
         *
         * @since 2.3.0
         * @access private
         *
         * @return string One of the STATE_* constants.
         */
        private function get_storeagent_state() {
            if ( ! WWP_Helper_Functions::is_plugin_active( self::STOREAGENT_FILE ) ) {
                return WWP_Helper_Functions::is_storeagent_installed() ? self::STATE_INACTIVE : self::STATE_NOT_INSTALLED;
            }

            if ( ! $this->has_mcp_api() ) {
                return self::STATE_OUTDATED;
            }

            return \SAAI\Helpers\MCP::is_available() ? self::STATE_READY : self::STATE_SETUP;
        }

        /**
         * Whether the active StoreAgent has every MCP helper this tab calls.
         *
         * @since 2.3.0
         * @access private
         *
         * @return bool
         */
        private function has_mcp_api() {
            if ( null !== $this->has_mcp_api ) {
                return $this->has_mcp_api;
            }

            $this->has_mcp_api = class_exists( '\SAAI\Helpers\MCP' )
                && class_exists( '\SAAI\Helpers\Connect' )
                && class_exists( '\SAAI\Helpers\MCP_Gateway_Client' )
                && class_exists( '\SAAI\Classes\MCP\Mcp_Gateway_Sync' )
                && method_exists( '\SAAI\Helpers\MCP', 'get_settings' )
                && method_exists( '\SAAI\Helpers\MCP', 'update_settings' )
                && method_exists( '\SAAI\Helpers\MCP', 'classify_ability' )
                && method_exists( '\SAAI\Helpers\MCP', 'is_available' )
                && method_exists( '\SAAI\Helpers\Connect', 'is_connected' )
                && method_exists( '\SAAI\Helpers\MCP_Gateway_Client', 'get_endpoint_url' )
                && method_exists( '\SAAI\Classes\MCP\Mcp_Gateway_Sync', 'instance' )
                && method_exists( '\SAAI\Classes\MCP\Mcp_Gateway_Sync', 'sync' )
                && defined( '\SAAI\Helpers\MCP::CLASS_WRITE' );

            return $this->has_mcp_api;
        }

        /**
         * Get the names of every registered Wholesale Suite ability StoreAgent treats as a write.
         *
         * @since 2.3.0
         * @access private
         *
         * @return string[]
         */
        private function get_wholesale_write_abilities() {
            if ( ! $this->has_mcp_api() || ! function_exists( 'wp_get_abilities' ) ) {
                return array();
            }

            $prefix = WWP_Abilities::CATEGORY . '/';
            $names  = array();

            foreach ( wp_get_abilities() as $ability ) {
                $name = $ability->get_name();

                if ( str_starts_with( $name, $prefix ) && \SAAI\Helpers\MCP::CLASS_WRITE === \SAAI\Helpers\MCP::classify_ability( $ability ) ) {
                    $names[] = $name;
                }
            }

            return $names;
        }

        /**
         * Get StoreAgent's MCP write allowlist.
         *
         * @since 2.3.0
         * @access private
         *
         * @return string[]
         */
        private function get_allowlist() {
            if ( ! $this->has_mcp_api() ) {
                return array();
            }

            $settings = \SAAI\Helpers\MCP::get_settings();

            return isset( $settings['allowlist'] ) && is_array( $settings['allowlist'] ) ? $settings['allowlist'] : array();
        }

        /**
         * Handle the write access toggle from the settings app.
         *
         * Adds or removes the Wholesale Suite write abilities in StoreAgent's MCP allowlist, then syncs
         * the gateway. This is the same sequence as StoreAgent's own settings save route. Other
         * allowlist entries, `enabled` and `read_denylist` are never changed.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $params Sanitized request params. `enabled` is 'yes' or 'no'.
         * @return array Status and message for the settings app.
         */
        public function trigger_write_access( $params ) {
            $error = array(
                'status'  => 'error',
                'message' => __( 'The MCP write access could not be changed. Please try again.', 'woocommerce-wholesale-prices' ),
            );

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return $error;
            }

            if ( ! WWP_Helper_Functions::is_plugin_active( self::STOREAGENT_FILE ) || ! $this->has_mcp_api() || ! \SAAI\Helpers\Connect::is_connected() ) {
                $error['message'] = __( 'Connect StoreAgent 1.1.9 or later first.', 'woocommerce-wholesale-prices' );
                return $error;
            }

            $enable      = isset( $params['enabled'] ) && 'yes' === $params['enabled'];
            $write_names = $this->get_wholesale_write_abilities();
            $allowlist   = $this->get_allowlist();
            $prefix      = WWP_Abilities::CATEGORY . '/';

            if ( $enable && empty( $write_names ) ) {
                return $error;
            }

            // Disabling removes every suite entry, including ones for plugins that are inactive right
            // now, so reactivating that plugin does not silently re-allow its writes. Read abilities
            // never use the allowlist, so removing by prefix cannot hide one.
            $allowlist = $enable
                ? array_values( array_unique( array_merge( $allowlist, $write_names ) ) )
                : array_values(
                    array_filter(
                        $allowlist,
                        function ( $name ) use ( $prefix ) {
                            return ! str_starts_with( $name, $prefix );
                        }
                    )
                );

            // StoreAgent silently truncates an oversized allowlist on save, so refuse before writing anything.
            if ( $enable && count( $allowlist ) > $this->get_allowlist_limit() ) {
                $error['message'] = __( 'The StoreAgent MCP allowlist is full. Remove some entries in StoreAgent > Settings > MCP, then try again.', 'woocommerce-wholesale-prices' );
                return $error;
            }

            if ( ! \SAAI\Helpers\MCP::update_settings( array( 'allowlist' => $allowlist ) ) ) {
                return $error;
            }

            $settings = \SAAI\Helpers\MCP::get_settings();
            $sync     = \SAAI\Classes\MCP\Mcp_Gateway_Sync::instance()->sync( $settings );

            // The allowlist is already saved, so this is a warning, not an error: the switch keeps the saved state.
            if ( is_array( $sync ) && isset( $sync['status'] ) && 'failed' === $sync['status'] ) {
                return array(
                    'status'  => 'warning',
                    'message' => __( 'The setting was saved, but StoreAgent could not sync it to the MCP gateway. Retry from StoreAgent > Settings > MCP.', 'woocommerce-wholesale-prices' ),
                );
            }

            return array(
                'status'  => 'success',
                'message' => $enable
                    ? __( 'Wholesale Suite write abilities are now allowed for MCP.', 'woocommerce-wholesale-prices' )
                    : __( 'Wholesale Suite write abilities are now blocked for MCP.', 'woocommerce-wholesale-prices' ),
            );
        }

        /**
         * Get StoreAgent's maximum number of MCP allowlist entries.
         *
         * @since 2.3.0
         * @access private
         *
         * @return int
         */
        private function get_allowlist_limit() {
            return defined( '\SAAI\Helpers\MCP::MAX_ALLOWLIST_ENTRIES' ) ? (int) \SAAI\Helpers\MCP::MAX_ALLOWLIST_ENTRIES : self::DEFAULT_ALLOWLIST_LIMIT;
        }

        /**
         * Add the write access action to the allowed trigger actions.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $actions Allowed trigger action slugs.
         * @return array
         */
        public function allow_trigger_action( $actions ) {
            $actions[] = self::WRITE_ACCESS_ACTION;

            return $actions;
        }

        /**
         * Get the StoreAgent WordPress.org URL.
         *
         * @since 2.3.0
         * @access private
         *
         * @return string
         */
        private function get_wporg_url() {
            return 'https://wordpress.org/plugins/' . self::STOREAGENT_SLUG . '/';
        }

        /**
         * Execute model.
         *
         * @since 2.3.0
         * @access public
         */
        public function run() {
            add_filter( 'wwp_admin_setting_default_tabs', array( $this, 'register_tab' ) );
            add_filter( 'wwp_admin_setting_default_controls', array( $this, 'register_controls' ) );
            add_filter( 'wwp_trigger_' . self::WRITE_ACCESS_ACTION, array( $this, 'trigger_write_access' ) );
            add_filter( 'wwp_allowed_trigger_actions', array( $this, 'allow_trigger_action' ) );
        }
    }
}
