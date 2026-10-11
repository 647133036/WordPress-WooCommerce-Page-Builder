<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Admin_Settings' ) ) {
    /**
     * Model that houses the logic of WooCommerce Wholesale Prices Settings page.
     *
     * @since 1.0.0
     */
    class WWP_Admin_Settings {

        /**
         * Property that holds the single main instance of WWP_Dashboard.
         *
         * @since  2.0
         * @access private
         * @var WWP_Dashboard
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since  2.0
         * @access private
         * @var WWP_Wholesale_Roles
         */
        private $_wwp_wholesale_roles;

        /**
         * Property that holds all registered wholesale roles.
         *
         * @since 1.16.0
         * @access public
         * @var array
         */
        private $_all_wholesale_roles;

        /**
         * WWP_Admin_Settings constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Admin_Settings model.
         *
         * @since  2.0
         * @access public
         */
        public function __construct( $dependencies ) {

            $this->_wwp_wholesale_roles = $dependencies['WWP_Wholesale_Roles'];
            $this->_all_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
        }

        /**
         * Ensure that only one instance of WWP_Admin_Settings is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Admin_Settings model.
         *
         * @since  2.0
         * @access public
         *
         * @return WWP_Admin_Settings
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Load back end styles and scripts.
         *
         * @param string $hook_queue The hook queue.
         *
         * @since  2.0
         * @access public
         */
        public function load_back_end_styles_and_scripts( $hook_queue = '' ) {

            global $wc_wholesale_prices;

            if ( ! str_contains( $hook_queue, 'page_wholesale-settings' ) ) {
                return;
            }

            $i18n = require WWP_PLUGIN_PATH . 'includes/I18n/settings.php';

            $settings_object = array(
                'i18n'            => $i18n,
                'wpNonce'         => wp_create_nonce( 'wp_rest' ),
                'details'         => $this->get_details(),
                'rest_save_url'   => rest_url( 'wwp/v1/admin/save' ),
                'rest_action_url' => rest_url( 'wwp/v1/admin/action' ),
                'settings'        => $this->get_registered_tab_settings(),
                'pluginDirUrl'    => WWP_PLUGIN_URL,
                'allowedTags'     => $this->allowed_tags(),
                'allowedAttrs'    => $this->allowed_attrs(),
            );

            // Load vue scripts.
            $app = new Vite_App(
                'wwp-settings-app-scripts',
                'src/apps/settings/index.ts',
                array(
                    'wp-i18n',
                    'wp-url',
                    'wp-hooks',
                    'wp-html-entities',
                    'lodash',
                    'jquery',
                ),
                $settings_object
            );

            $app->enqueue();

            /***************************************************************************
             * Enqueue wp_editor scripts/styles
             ***************************************************************************
            *
            * We make sure the wp.editor scripts/styles are enqueued as we will be
            * using it in our app.
            */
            wp_enqueue_editor();
            wp_enqueue_media();
        }

        /**
         * REST API for settings page.
         *
         * @since  2.0
         * @access public
         */
        public function rest_api_settings() {

            // Save settings.
            register_rest_route(
                'wwp/v1',
                '/admin/save',
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'save_registered_settings' ),
                    'permission_callback' => array( $this, 'permission_admin_check' ),
                )
            );

            // Trigger custom action.
            register_rest_route(
                'wwp/v1',
                'admin/action',
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'trigger_action' ),
                    'permission_callback' => array( $this, 'permission_admin_check' ),
                )
            );
        }

        /**
         * Check whether the user has permission perform the request.
         *
         * @param WP_REST_Request $request Request object.
         *
         * @return WP_Error|boolean
         */
        public function permission_admin_check( $request ) { // phpcs:ignore.

            if ( ! current_user_can( 'manage_options' ) ) {
                return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to save data.', 'woocommerce-wholesale-prices' ), array( 'status' => 403 ) );
            }

            return true;
        }

        /**
         * Save registered settings.
         *
         * @param WP_REST_Request $request Request object.
         *
         * @since  2.0
         * @access public
         *
         * @return WP_REST_Response
         */
        public function save_registered_settings( $request ) {
            $params            = $request->get_params();
            $settings_messages = array(
                'status'  => 'success',
                'message' => esc_html__( 'Settings saved successfully.', 'woocommerce-wholesale-prices' ),
            );

            // Deprecate the old prefix-based filter.
            if ( has_filter( 'wwp_allowed_settings_prefix' ) ) {
                _deprecated_hook( 'wwp_allowed_settings_prefix', '2.2.7', 'wwp_allowed_settings_keys' );
            }

            if ( ! empty( $params ) ) {

                // Check if there is an action.
                $has_action = false;
                foreach ( $params as $param ) {
                    if ( isset( $param['key'] ) && 'action' === $param['key'] ) {
                        $has_action = true;
                        break;
                    }
                }

                $options = array();
                foreach ( $params as $param ) {
                    if ( is_array( $param ) && isset( $param['key'] ) ) {
                        $key         = sanitize_key( $param['key'] );
                        $value       = isset( $param['value'] ) ? $param['value'] : '';
                        $final_value = $value;

                        if ( ! is_array( $value ) ) {
                            // Validate if value is true or false.
                            if ( 'true' === trim( $value ) ) {
                                $final_value = 'yes';
                            } elseif ( 'false' === trim( $value ) ) {
                                $final_value = 'no';
                            }
                        }

                        $options[ $key ] = $final_value;
                    }
                }

                if ( $has_action && ! empty( $options['action'] ) ) {
                    $action_name     = sanitize_key( $options['action'] );
                    $allowed_actions = $this->get_allowed_group_actions();

                    if ( ! in_array( $action_name, $allowed_actions, true ) ) {
                        return rest_ensure_response(
                            array(
                                'status'  => 'error',
                                'message' => esc_html__( 'Invalid action.', 'woocommerce-wholesale-prices' ),
                            )
                        );
                    }

                    // Trigger the custom group save action.
                    $settings_messages = apply_filters( 'wwp_group_settings_' . $action_name, $options );
                } else {
                    // Regular save path: validate keys against explicit allowlist.
                    $allowed_keys = $this->get_allowed_option_keys();
                    $type_map     = $this->get_control_type_map();

                    foreach ( $options as $option_name => $option_value ) {
                        if ( ! in_array( $option_name, $allowed_keys, true ) ) {
                            continue;
                        }

                        $setting_value = $this->sanitize_option_value( $option_value, $option_name, $type_map );

                        $arr_value = is_string( $setting_value ) ? json_decode( $setting_value, true ) : null;
                        if ( is_array( $arr_value ) ) {
                            $setting_value = $arr_value;
                        }

                        // Update or create.
                        update_option( $option_name, $setting_value, 'no' );
                    }
                }
            }

            $response = $settings_messages;

            return rest_ensure_response( $response );
        }

        /**
         * Trigger custom action.
         *
         * @param WP_REST_Request $request Request object.
         *
         * @since  2.0
         * @access public
         *
         * @return WP_REST_Response
         */
        public function trigger_action( $request ) {
            $params            = $request->get_params();
            $settings_messages = array(
                'status'  => 'success',
                'message' => esc_html__( 'Cache cleared successfully.', 'woocommerce-wholesale-prices' ),
            );

            if ( ! empty( $params ) && ! empty( $params['action'] ) ) {
                $action_name     = sanitize_key( $params['action'] );
                $allowed_actions = $this->get_allowed_trigger_actions();

                if ( ! in_array( $action_name, $allowed_actions, true ) ) {
                    return rest_ensure_response(
                        array(
                            'status'  => 'error',
                            'message' => esc_html__( 'Invalid action.', 'woocommerce-wholesale-prices' ),
                        )
                    );
                }

                // Sanitize all params before passing to filter.
                $params = map_deep( $params, 'sanitize_text_field' );

                // Trigger the custom action.
                $settings_messages = apply_filters( 'wwp_trigger_' . $action_name, $params );
            }

            $response = array(
                'status'  => $settings_messages['status'],
                'message' => $settings_messages['message'],
            );

            // Check if there is a redirect.
            if ( ! empty( $settings_messages['redirect'] ) ) {
                $response['redirect'] = $settings_messages['redirect'];
            }

            return rest_ensure_response( $response );
        }

        /**
         * Get the list of allowed option keys derived from registered tab settings controls.
         *
         * Walks the controls tree returned by get_registered_tab_settings() and extracts
         * all 'id' values to build an explicit allowlist. Additional keys can be added
         * via the 'wwp_allowed_settings_keys' filter.
         *
         * @since  2.2.7
         * @access private
         *
         * @return array Flat array of allowed option key strings.
         */
        private function get_allowed_option_keys() {
            $settings = $this->get_registered_tab_settings();
            $keys     = array();

            if ( ! empty( $settings['controls'] ) && is_array( $settings['controls'] ) ) {
                array_walk_recursive(
                    $settings['controls'],
                    function ( $value, $key ) use ( &$keys ) {
                        if ( 'id' === $key && is_string( $value ) ) {
                            $keys[] = $value;
                        }
                    }
                );
            }

            /**
             * Filter the list of allowed settings keys for the REST API save endpoint.
             *
             * @since 2.2.7
             *
             * @param array $keys Allowed option key strings.
             */
            $keys = apply_filters( 'wwp_allowed_settings_keys', $keys );

            return array_unique( $keys );
        }

        /**
         * Build a type map from registered controls (id => type).
         *
         * @since  2.2.7
         * @access private
         *
         * @return array Associative array of option_key => control_type.
         */
        private function get_control_type_map() {
            $settings = $this->get_registered_tab_settings();
            $type_map = array();

            if ( ! empty( $settings['controls'] ) && is_array( $settings['controls'] ) ) {
                $this->extract_type_map( $settings['controls'], $type_map );
            }

            return $type_map;
        }

        /**
         * Recursively extract id => type pairs and option choices from controls.
         *
         * @since  2.2.7
         * @access private
         *
         * @param array $controls The controls array to walk.
         * @param array $type_map  Reference to the type map being built.
         */
        private function extract_type_map( $controls, &$type_map ) {
            foreach ( $controls as $value ) {
                if ( is_array( $value ) ) {
                    if ( isset( $value['id'], $value['type'] ) ) {
                        $type_map[ $value['id'] ] = array(
                            'type'     => $value['type'],
                            'options'  => isset( $value['options'] ) ? $value['options'] : array(),
                            'editor'   => ! empty( $value['editor'] ),
                            'multiple' => ! empty( $value['multiple'] ),
                        );
                    }
                    $this->extract_type_map( $value, $type_map );
                }
            }
        }

        /**
         * Sanitize an option value based on the control type.
         *
         * @since  2.2.7
         * @since  2.2.8 Cast select/radio option keys and the value to string so numeric-keyed options (e.g. page IDs) pass the strict comparison.
         * @access private
         *
         * @param mixed  $value      The value to sanitize.
         * @param string $option_key The option key.
         * @param array  $type_map   Type map from get_control_type_map().
         * @return mixed Sanitized value.
         */
        private function sanitize_option_value( $value, $option_key, $type_map ) {
            if ( is_array( $value ) ) {
                return map_deep( $value, 'sanitize_text_field' );
            }

            if ( ! isset( $type_map[ $option_key ] ) ) {
                return sanitize_text_field( $value );
            }

            $control = $type_map[ $option_key ];

            switch ( $control['type'] ) {
                case 'checkbox':
                case 'switch':
                    if ( ! empty( $control['options'] ) ) {
                        $allowed = array_keys( $control['options'] );
                        if ( in_array( $value, $allowed, true ) ) {
                            return $value;
                        }
                    }
                    // Default checkbox values.
                    return in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'no';

                case 'select':
                case 'radio':
                    if ( ! empty( $control['options'] ) ) {
                        // Cast keys to strings: PHP converts numeric array keys (e.g. page IDs)
                        // to integers, which would fail the strict comparison against the string
                        // values sent by the REST API.
                        $allowed = array_map( 'strval', array_keys( $control['options'] ) );

                        // Handle multi-select: value may be a JSON-encoded array.
                        if ( ! empty( $control['multiple'] ) ) {
                            $decoded = is_string( $value ) ? json_decode( $value, true ) : null;
                            if ( is_array( $decoded ) ) {
                                $sanitized = array_values( array_intersect( array_map( 'strval', $decoded ), $allowed ) );
                                return wp_json_encode( $sanitized );
                            }
                        }

                        if ( in_array( (string) $value, $allowed, true ) ) {
                            return (string) $value;
                        }
                        // Return first option as default if invalid.
                        return ! empty( $allowed ) ? reset( $allowed ) : '';
                    }
                    return sanitize_text_field( $value );

                case 'number':
                case 'money':
                case 'percent':
                    if ( is_numeric( $value ) ) {
                        return $value;
                    }
                    return '';

                case 'textarea':
                    if ( $control['editor'] ) {
                        return wp_kses_post( $value );
                    }
                    return sanitize_textarea_field( $value );

                default:
                    return sanitize_text_field( $value );
            }
        }

        /**
         * Get the list of allowed group action names.
         *
         * @since  2.2.7
         * @access private
         *
         * @return array Array of allowed group action slug strings.
         */
        private function get_allowed_group_actions() {
            // WWP core group actions.
            $actions = array(
                'group_save',
                'group_delete',
                'group_edit',
            );

            // Auto-detect group actions registered by companion plugins via WordPress hooks.
            // This ensures backwards compatibility with older plugin versions that register
            // 'wwp_group_settings_*' hooks but don't yet use the 'wwp_allowed_group_actions' filter.
            $actions = array_merge( $actions, $this->detect_registered_hooks( 'wwp_group_settings_' ) );

            /**
             * Filter the list of allowed group actions for the REST API save endpoint.
             *
             * Companion plugins should add their group action slugs via this filter.
             *
             * @since 2.2.7
             *
             * @param array $actions Allowed group action slug strings.
             */
            return array_unique( apply_filters( 'wwp_allowed_group_actions', $actions ) );
        }

        /**
         * Get the list of allowed trigger action names.
         *
         * @since  2.2.7
         * @access private
         *
         * @return array Array of allowed trigger action slug strings.
         */
        private function get_allowed_trigger_actions() {
            // Auto-detect trigger actions registered by companion plugins via WordPress hooks.
            // This ensures backwards compatibility with older plugin versions that register
            // 'wwp_trigger_*' hooks but don't yet use the 'wwp_allowed_trigger_actions' filter.
            $actions = $this->detect_registered_hooks( 'wwp_trigger_' );

            /**
             * Filter the list of allowed trigger actions for the REST API action endpoint.
             *
             * Companion plugins should add their trigger action slugs via this filter.
             *
             * @since 2.2.7
             *
             * @param array $actions Allowed trigger action slug strings.
             */
            return array_unique( apply_filters( 'wwp_allowed_trigger_actions', $actions ) );
        }

        /**
         * Detect registered WordPress hooks matching a given prefix and extract action slugs.
         *
         * Scans the global $wp_filter for hooks that start with the given prefix and returns
         * the action slug portion (everything after the prefix). This provides backwards
         * compatibility with companion plugins that register hooks but haven't yet been
         * updated to use the explicit allowlist filters.
         *
         * @since  2.2.7
         * @access private
         *
         * @param string $prefix The hook prefix to match (e.g. 'wwp_group_settings_' or 'wwp_trigger_').
         * @return array Array of action slug strings extracted from matching hooks.
         */
        private function detect_registered_hooks( $prefix ) {
            global $wp_filter;

            $actions       = array();
            $prefix_length = strlen( $prefix );

            if ( ! empty( $wp_filter ) && is_array( $wp_filter ) ) {
                foreach ( array_keys( $wp_filter ) as $hook_name ) {
                    if ( str_starts_with( $hook_name, $prefix ) ) {
                        $slug = substr( $hook_name, $prefix_length );
                        if ( ! empty( $slug ) ) {
                            $actions[] = $slug;
                        }
                    }
                }
            }

            return $actions;
        }

        /**
         * Get settings details.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function get_details() {
            $details = array(
                'logo'  => esc_url( WWP_IMAGES_URL ) . 'logo.png',
                'title' => __( 'Settings', 'woocommerce-wholesale-prices' ),
            );

            return $details;
        }

        /**
         * Get registered tabs, controls and data tables.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function get_registered_tab_settings() {
            // Get the tabs.
            $default_tabs = $this->_default_tabs();
            $tabs         = apply_filters( 'wwp_admin_setting_tabs', $default_tabs );

            // Get the controls.
            $default_controls = $this->_default_controls();
            $controls         = apply_filters( 'wwp_admin_setting_controls', $default_controls );

            // Get data tables.
            $data_tables = apply_filters( 'wwp_admin_data_tables', array() );

            return array(
                'tabs'       => $tabs,
                'controls'   => $controls,
                'dataTables' => $data_tables,
            );
        }

        /**
         * Get default tabs.
         *
         * @since  2.0
         * @since 2.2.0 - Added admin roles tab and control
         * @access private
         *
         * @return array
         */
        private function _default_tabs() {
            $settings = array();

            // Parent tab.
            $settings['wholesale_prices'] = array(
                'label' => __( 'Wholesale Prices', 'woocommerce-wholesale-prices' ),
                'child' => array(),
            );

            // General tab.
            $settings['wholesale_prices']['child']['general'] = array(
                'sort'                => 1,
                'key'                 => 'general',
                'label'               => __( 'General', 'woocommerce-wholesale-prices' ),
                'sections'            => array(
                    'order_requirements' => array(
                        'label' => __( 'Wholesale Prices Settings', 'woocommerce-wholesale-prices' ),
                        'desc'  => '',
                    ),
                ),
                'show_unlock_upgrade' => true,
                'show_free_guide'     => true,
            );

            // Price tab.
            $wwp_wwlc_is_active = WWP_Helper_Functions::is_wwlc_active();

            $settings['wholesale_prices']['child']['price'] = array(
                'sort'     => 2,
                'key'      => 'price',
                'label'    => __( 'Price', 'woocommerce-wholesale-prices' ),
                'sections' => array(
                    'price_options'         => array(
                        'label' => __( 'Price Options', 'woocommerce-wholesale-prices' ),
                        'desc'  => '',
                    ),
                    'box_for_non_wholesale' => array(
                        'label'             => __( 'Show Wholesale Prices Box For Non Wholesale Customers', 'woocommerce-wholesale-prices' ),
                        'desc'              => '',
                        'show_lead_upgrade' => ( ! $wwp_wwlc_is_active ) ? true : false,
                    ),
                ),
            );

            // Tax tab.
            $tax_exp_mapping_dec = sprintf(
            // translators: %1$s <b> tag, %2$s </b> tag, %3$s link to premium add-on, %4$s </a> tag, %5$s link to bundle.
                __(
                    'Specify tax exemption per wholesale role. Overrides general %1$sTax Exemption%2$s option above. <br><br>In the Premium add-on you can map specific wholesale roles to be tax exempt which gives you more control. This is useful for classifying customers based on their tax exemption status so you can separate those who need to pay tax and those who don\'t. <br><br>This feature and more is available in the %3$sPremium add-on%4$s and we also have other wholesale tools available as part of the %5$sWholesale Suite Bundle%4$s.',
                    'woocommerce-wholesale-prices'
                ),
                '<b>',
                '</b>',
                sprintf(
                    '<a target="_blank" href="%s">',
                    esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwptaxexemptionwwpplink' ) )
                ),
                '</a>',
                sprintf(
                    '<a target="_blank" href="%s">',
                    esc_url( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'wwptaxexemptionbundlelink' ) )
                )
            );
            $tax_cls_mapping_dec = sprintf(
            // translators: %1$s link to premium add-on, %2$s </a> tag, %3$s link to wholesale suite bundle.
                __(
                    'Specify tax classes per wholesale role. <br><br>In the Premium add-on you can map specific wholesale role to specific tax classes. You can also hide those mapped tax classes from your regular customers making it possible to completely separate tax functionality for wholesale customers. <br><br>This feature and more is available in the %1$sPremium add-on%2$s and we also have other wholesale tools available as part of the %3$sWholesale Suite Bundle%2$s.',
                    'woocommerce-wholesale-prices'
                ),
                sprintf(
                    '<a target="_blank" href="%s"> ',
                    esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwptaxexemptionwwpplink' ) )
                ),
                '</a>',
                sprintf(
                    '<a target="_blank" href="%s">',
                    esc_url( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'wwptaxexemptionbundlelink' ) )
                )
            );

            $settings['wholesale_prices']['child']['tax'] = array(
                'sort'               => 3,
                'key'                => 'tax',
                'label'              => __( 'Tax', 'woocommerce-wholesale-prices' ),
                'sections'           => array(
                    'tax_options'     => array(
                        'label' => __( 'Tax Options', 'woocommerce-wholesale-prices' ),
                        'desc'  => '',
                    ),
                    'tax_exp_mapping' => array(
                        'label' => __( 'Wholesale Role / Tax Exemption Mapping', 'woocommerce-wholesale-prices' ),
                        'desc'  => $tax_exp_mapping_dec,
                    ),
                    'tax_cls_mapping' => array(
                        'label' => __( 'Wholesale Role / Tax Class Mapping', 'woocommerce-wholesale-prices' ),
                        'desc'  => $tax_cls_mapping_dec,
                    ),
                ),
                'show_addon_upgrade' => true,
            );

            $settings['wholesale_prices']['child']['admin_roles'] = array(
                'sort'     => 4,
                'key'      => 'admin_roles',
                'label'    => __( 'Admin Roles', 'woocommerce-wholesale-prices' ),
                'sections' => array(
                    'admin_roles_options' => array(
                        'label' => __( 'Admin Roles', 'woocommerce-wholesale-prices' ),
                        'desc'  => __( 'Manage the roles that can access the Wholesale dashboard and settings.', 'woocommerce-wholesale-prices' ),
                    ),
                ),
            );

            // Help tab.
            $help_options_dec = sprintf(
            // translators: %1$s link to premium add-on, %2$s </a> tag.
                __(
                    'Looking for documentation? Please see our growing %1$sKnowledge Base%2$s.',
                    'woocommerce-wholesale-prices'
                ),
                sprintf(
                    '<a target="_blank" href="%s"> ',
                    esc_url( WWP_Helper_Functions::get_utm_url( 'knowledge-base', 'wwp', 'kb', 'helppagekblink' ) )
                ),
                '</a>',
            );

            $settings['wholesale_prices']['child']['help'] = array(
                'sort'     => 5,
                'key'      => 'help',
                'label'    => __( 'Help', 'woocommerce-wholesale-prices' ),
                'sections' => array(
                    'shipping_options' => array(
                        'label' => __( 'Help Options', 'woocommerce-wholesale-prices' ),
                        'desc'  => $help_options_dec,
                    ),
                ),
            );

            // License tab.
            $settings['wholesale_prices']['child']['license'] = array(
                'sort'     => 6,
                'key'      => 'license',
                'label'    => __( 'License', 'woocommerce-wholesale-prices' ),
                'link'     => admin_url( 'admin.php?page=wws-license-settings' ),
                'external' => false,
            );

            // Upgrade tab.
            $settings['wholesale_prices']['child']['upgrade'] = array(
                'sort'            => 7,
                'key'             => 'upgrade',
                'label'           => __( 'Upgrade To Premium', 'woocommerce-wholesale-prices' ),
                'sections'        => array(
                    'upgrade_options'  => array(
                        'label' => '',
                        'desc'  => '',
                    ),
                    'upgrade_options2' => array(
                        'label' => '',
                        'desc'  => '',
                    ),
                ),
                'with_background' => true,
                'no_save'         => true,
            );

            $default_tabs = apply_filters( 'wwp_admin_setting_default_tabs', $settings );

            return $default_tabs;
        }

        /**
         * Get default controls.
         *
         * @since  2.0
         * @access private
         *
         * @return array
         */
        private function _default_controls() {
            $controls = array();

            // General tab.
            $controls['wholesale_prices']['general'] = $this->general_tab_controls();

            // Price tab.
            $controls['wholesale_prices']['price'] = $this->prices_tab_controls();

            // Tax tab.
            $controls['wholesale_prices']['tax'] = $this->tax_tab_controls();

            // Roles tab.
            $controls['wholesale_prices']['admin_roles'] = $this->admin_roles_tab_controls();

            // Help tab.
            $controls['wholesale_prices']['help'] = $this->help_tab_controls();

            // Upgrade tab.
            $controls['wholesale_prices']['upgrade'] = $this->upgrade_tab_controls();

            $default_controls = apply_filters( 'wwp_admin_setting_default_controls', $controls );

            return $default_controls;
        }

        /**
         * General tab controls.
         *
         * @since  2.0
         * @access private
         * @return array
         */
        private function general_tab_controls() {
            $general_controls = array();

            // Get options.
            $disable_coupons_for_wholesale_users = get_option( 'wwpp_settings_disable_coupons_for_wholesale_users' );

            $wwp_anonymous_data     = get_option( 'wwp_anonymous_data' );
            $wwp_anonymous_data_val = ( ! empty( $wwp_anonymous_data ) ) ? $wwp_anonymous_data : 'yes';

            // General Options - Section.
            $general_controls['order_requirements'] = array();

            if ( 'yes' !== $wwp_anonymous_data_val ) {
                $allow_tracking_dec = sprintf(
                // translators: %1$s link to premium add-on, %2$s </a> tag.
                    __(
                        'Complete documentation on usage tracking is available %1$shere%2$s.',
                        'woocommerce-wholesale-prices'
                    ),
                    sprintf(
                        '<a target="_blank" href="%s"> ',
                        esc_url( WWP_Helper_Functions::get_utm_url( 'kb/usage-tracking', 'wwp', 'kb', 'helppageusagetracking' ) )
                    ),
                    '</a>',
                );

                $general_controls['order_requirements'][] = array(
					'type'        => 'checkbox',
					'label'       => __( 'Allow Usage Tracking', 'woocommerce-wholesale-prices' ),
					'id'          => 'wwp_anonymous_data',
					'input_label' => __( 'By allowing us to track usage data we can better help you because we know with which WordPress configurations, themes and plugins we should test.', 'woocommerce-wholesale-prices' ),
					'multiple'    => false,
					'description' => $allow_tracking_dec,
					'default'     => $wwp_anonymous_data_val,
				);
            }

            $general_controls['order_requirements'][] = array(
				'type'        => 'checkbox',
				'label'       => __( 'Disable Coupons For Wholesale Users', 'woocommerce-wholesale-prices' ),
				'id'          => 'wwpp_settings_disable_coupons_for_wholesale_users',
				'input_label' => __( 'Globally turn off coupons functionality for customers with a wholesale user role.', 'woocommerce-wholesale-prices' ),
				'multiple'    => false,
				'description' => __( 'This applies to all customers with a wholesale role.', 'woocommerce-wholesale-prices' ),
				'default'     => $disable_coupons_for_wholesale_users,
			);

            // Filter to modify general controls.
            $general_controls = apply_filters( 'wwp_admin_setting_default_general_controls', $general_controls );

            return $general_controls;
        }

        /**
         * Prices tab controls.
         *
         * @since  2.0
         * @access private
         * @return array
         */
        private function prices_tab_controls() {
            $prices_controls = array();

            // Get options.
            $settings_wholesale_price_title_text       = get_option( 'wwpp_settings_wholesale_price_title_text' );
            $settings_hide_original_price              = get_option( 'wwpp_settings_hide_original_price' );
            $settings_explicitly_dummy                 = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc_dummy' );
            $wholesale_price_on_product_listing        = get_option( 'wwpp_hide_wholesale_price_on_product_listing' );
            $hide_price_add_to_cart                    = get_option( 'wwp_hide_price_add_to_cart' );
            $price_and_add_to_cart_replacement_message = get_option( 'wwp_price_and_add_to_cart_replacement_message' );

            // Price options - Section.
            $prices_controls['price_options'] = array(
                array(
                    'type'        => 'text',
                    'label'       => __( 'Wholesale Price Text', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwpp_settings_wholesale_price_title_text',
                    'default'     => $settings_wholesale_price_title_text,
                    'description' => __( 'The text shown immediately before the wholesale price. Default is "Wholesale Price: "', 'woocommerce-wholesale-prices' ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Hide Retail Price', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwpp_settings_hide_original_price',
                    'input_label' => __( 'Hide retail price instead of showing a crossed out price if a wholesale price is present.', 'woocommerce-wholesale-prices' ),
                    'multiple'    => false,
                    'default'     => $settings_hide_original_price,
                ),
                array(
                    'type'          => 'checkbox',
                    'label'         => __( 'Always Use Regular Price', 'woocommerce-wholesale-prices' ),
                    'id'            => 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc_dummy',
                    'input_label'   => __( 'When calculating the wholesale price by using a percentage (global discount % or category based %) always ensure the Regular Price is used and ignore the Sale Price if present.', 'woocommerce-wholesale-prices' ),
                    'multiple'      => false,
                    'default'       => $settings_explicitly_dummy,
                    'pro'           => 'upgrade_explicitly_use_product',
                    'pro_on_active' => true,
                ),
                array(
                    'type'     => 'select',
                    'label'    => __( 'Variable Product Price Display', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => __( 'Specify the format in which variable product prices are displayed. Only for wholesale customers.', 'woocommerce-wholesale-prices' ),
                    'id'       => 'wwp_settings_variable_product_price_display_dummy',
                    'classes'  => 'wwp_settings_variable_product_price_display_dummy',
                    'options'  => array(
                        'price-range' => __( 'Price Range', 'woocommerce-wholesale-prices' ),
                        'minimum'     => __( 'Minimum Price (Premium)', 'woocommerce-wholesale-prices' ),
                        'maximum'     => __( 'Maximum Price (Premium)', 'woocommerce-wholesale-prices' ),
                    ),
                    'default'  => 'price-range',
                    'pro'      => 'upgrade_prices_display',
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Hide Wholesale Price on Admin Product Listing', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwpp_hide_wholesale_price_on_product_listing',
                    'input_label' => __( 'If checked, hides wholesale price per wholesale role on the product listing on the admin page.', 'woocommerce-wholesale-prices' ),
                    'multiple'    => false,
                    'default'     => $wholesale_price_on_product_listing,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Hide Price and Add to Cart button', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_hide_price_add_to_cart',
                    'input_label' => __( 'If checked, hides price and add to cart button for visitors.', 'woocommerce-wholesale-prices' ),
                    'multiple'    => false,
                    'default'     => $hide_price_add_to_cart,
                ),
                array(
                    'type'        => 'textarea',
                    'label'       => __( 'Price and Add to Cart Replacement Message', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_price_and_add_to_cart_replacement_message',
                    'description' => __( 'This message is only shown if <b>Hide Price and Add to Cart button</b> is enabled. "Login to see prices" is the default message.', 'woocommerce-wholesale-prices' ),
                    'default'     => $price_and_add_to_cart_replacement_message,
                    'editor'      => true,
                ),
            );

            // Get options.
            $wwp_wwof_is_active = WWP_Helper_Functions::is_wwof_active();
            $wwp_wwlc_is_active = WWP_Helper_Functions::is_wwlc_active();

            $prices_settings_show_wholesale_prices_to_non_wholesale = get_option( 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale' );
            $dependencies_display                                   = 'no' === $prices_settings_show_wholesale_prices_to_non_wholesale;
            $wwp_non_wholesale_show_in_shop                         = get_option( 'wwp_non_wholesale_show_in_shop' );
            $wwp_non_wholesale_show_in_products                     = get_option( 'wwp_non_wholesale_show_in_products' );
            $wwp_non_wholesale_show_in_wwof                         = get_option( 'wwp_non_wholesale_show_in_wwof' );
            $wwp_see_wholesale_prices_replacement_text              = get_option( 'wwp_see_wholesale_prices_replacement_text' );
            $wwp_price_settings_register_text                       = get_option( 'wwp_price_settings_register_text' );
            $wwp_price_settings_register_text                       = ( $wwp_wwlc_is_active ) ? $wwp_price_settings_register_text : __( 'Click here to register as a wholesale customer', 'woocommerce-wholesale-prices' );

            $wwof_description = '';
            if ( ! $wwp_wwof_is_active ) {
                $wwof_description = sprintf(
                    // translators: %1$s link to premium add-on, %2$s </a> tag.
                    __(
                        'To use this option, you must have %1$s<b>WooCommerce Wholesale Order Form</b>%2$s plugin installed and activated.',
                        'woocommerce-wholesale-prices'
                    ),
                    sprintf(
                        '<a target="_blank" href="%s"> ',
                        esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-order-form', 'wwp', 'upsell', 'upgradepagewwoflearnmore' ) )
                    ),
                    '</a>',
                );
            }

            // Price box for non wholesale customers - Section.
            $prices_controls['box_for_non_wholesale'] = array(
                array(
                    'type'        => 'switch',
                    'label'       => __( 'Show Wholesale Price to non-wholesale users', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                    'description' => __( 'If checked, displays the wholesale price on the front-end to entice non-wholesale customers to register as wholesale customers. This is only shown for guest, customers, administrator, and shop managers.', 'woocommerce-wholesale-prices' ),
                    'options'     => array(
                        'yes' => __( 'Enabled', 'woocommerce-wholesale-prices' ),
                        'no'  => __( 'Disabled', 'woocommerce-wholesale-prices' ),
                    ),
                    'default'     => $prices_settings_show_wholesale_prices_to_non_wholesale,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Locations', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_non_wholesale_show_in_shop',
                    'input_label' => __( 'Shop Archives', 'woocommerce-wholesale-prices' ),
                    'multiple'    => false,
                    'default'     => $wwp_non_wholesale_show_in_shop,
                    'hide'        => $dependencies_display,
                    'condition'   => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => '',
                    'id'          => 'wwp_non_wholesale_show_in_products',
                    'input_label' => __( 'Single Product', 'woocommerce-wholesale-prices' ),
                    'multiple'    => false,
                    'default'     => $wwp_non_wholesale_show_in_products,
                    'hide'        => $dependencies_display,
                    'condition'   => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => '',
                    'id'          => 'wwp_non_wholesale_show_in_wwof',
                    'input_label' => __( 'Wholesale Order Form', 'woocommerce-wholesale-prices' ),
                    'description' => $wwof_description,
                    'multiple'    => false,
                    'disabled'    => ! $wwp_wwof_is_active,
                    'default'     => $wwp_non_wholesale_show_in_wwof,
                    'hide'        => $dependencies_display,
                    'condition'   => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'      => 'text',
                    'label'     => __( 'Click to See Wholesale Prices Text', 'woocommerce-wholesale-prices' ),
                    'id'        => 'wwp_see_wholesale_prices_replacement_text',
                    'default'   => $wwp_see_wholesale_prices_replacement_text,
                    'desc_tip'  => __( 'The "Click to See Wholesale Prices Text" seen in the frontpage.', 'woocommerce-wholesale-prices' ),
                    'hide'      => $dependencies_display,
                    'condition' => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'      => 'select',
                    'label'     => __( 'Wholesale Roles(s)', 'woocommerce-wholesale-prices' ),
                    'id'        => 'wwp_non_wholesale_wholesale_role_select2',
                    'default'   => array( 'wholesale_customer' ),
                    'options'   => array(
                        'wholesale_customer' => __( 'Wholesale Customer', 'woocommerce-wholesale-prices' ),
                    ),
                    'desc_tip'  => __( 'The selected wholesale roles and pricing that should show to non-wholesale customers on the front end.', 'woocommerce-wholesale-prices' ),
                    'multiple'  => true,
                    'hide'      => $dependencies_display,
                    'condition' => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                    'disabled'  => true,
                ),
                array(
                    'type'              => 'text',
                    'label'             => __( 'Register Text', 'woocommerce-wholesale-prices' ),
                    'id'                => 'wwp_price_settings_register_text',
                    'default'           => $wwp_price_settings_register_text,
                    'desc_tip'          => __( 'This text is linked to the defined registration page in WooCommerce Wholesale Lead Capture settings.', 'woocommerce-wholesale-prices' ),
                    'hide'              => $dependencies_display,
                    'condition'         => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                    'disabled'          => ( $wwp_wwlc_is_active ) ? false : true,
                    'show_lead_upgrade' => ( $wwp_wwlc_is_active ) ? false : true,
                ),
            );

            // Filter to modify prices controls.
            $prices_controls = apply_filters( 'wwp_admin_setting_default_prices_controls', $prices_controls );

            return $prices_controls;
        }

        /**
         * Tax Tab Controls.
         *
         * @since  2.0
         * @access private
         * @return array
         */
        private function tax_tab_controls() {
            $tax_controls = array();

            // Tax Options - Section.
            $tax_controls['tax_options'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Tax Exemption', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_settings_tax_exempt_wholesale_users',
                    'input_label' => __( 'Do not apply tax to all wholesale roles', 'woocommerce-wholesale-prices' ),
                    'description' => __( 'Removes tax for all wholesale roles. All wholesale prices will display excluding tax throughout the store, cart and checkout. The display settings below will be ignored.', 'woocommerce-wholesale-prices' ),
                    'multiple'    => false,
                    'default'     => 'no',
                    'pro'         => 'upgrade_tax_exemption',
                ),
                array(
                    'type'        => 'select',
                    'label'       => __( 'Display Prices in the Shop', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_settings_incl_excl_tax_on_wholesale_price',
                    'options'     => array(
                        ''     => '--' . __( 'Use woocommerce default', 'woocommerce-wholesale-prices' ) . '--',
                        'incl' => __( 'Including tax (Premium)', 'woocommerce-wholesale-prices' ),
                        'excl' => __( 'Excluding tax (Premium)', 'woocommerce-wholesale-prices' ),
                    ),
                    'description' => __( 'Choose how wholesale roles see all prices throughout your shop pages.', 'woocommerce-wholesale-prices' ),
                    'desc_tip'    => __( 'Note: If the option above of "Tax Exempting" wholesale users is enabled, then wholesale prices on shop pages will not include tax regardless the value of this option.', 'woocommerce-wholesale-prices' ),
                    'multiple'    => false,
                    'default'     => '',
                    'pro'         => 'upgrade_advance_tax',
                ),
                array(
                    'type'        => 'select',
                    'label'       => __( 'Display Prices During Cart and Checkout', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_settings_wholesale_tax_display_cart',
                    'options'     => array(
                        ''     => '--' . __( 'Use woocommerce default', 'woocommerce-wholesale-prices' ) . '--',
                        'incl' => __( 'Including tax (Premium)', 'woocommerce-wholesale-prices' ),
                        'excl' => __( 'Excluding tax (Premium)', 'woocommerce-wholesale-prices' ),
                    ),
                    'description' => __( 'Choose how wholesale roles see all prices on the cart and checkout pages.', 'woocommerce-wholesale-prices' ),
                    'desc_tip'    => __( 'Note: If the option above of "Tax Exempting" wholesale users is enabled, then wholesale prices on cart and checkout page will not include tax regardless the value of this option.', 'woocommerce-wholesale-prices' ),
                    'multiple'    => false,
                    'default'     => '',
                    'pro'         => 'upgrade_advance_tax',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Override Regular Price Suffix', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_settings_override_price_suffix_regular_price',
                    'description' => __( 'Override the price suffix on regular prices for wholesale users.', 'woocommerce-wholesale-prices' ),
                    'desc_tip'    => __( 'Make this blank to use the default price suffix. You can also use prices substituted here using one of the following {price_including_tax} and {price_excluding_tax}.', 'woocommerce-wholesale-prices' ),
                    'default'     => '',
                    'pro'         => 'upgrade_suffix',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Wholesale Price Suffix', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_settings_override_price_suffix',
                    'description' => __( 'Set a specific price suffix specifically for wholesale prices.', 'woocommerce-wholesale-prices' ),
                    'desc_tip'    => __( 'Make this blank to use the default price suffix. You can also use prices substituted here using one of the following {price_including_tax} and {price_excluding_tax}.', 'woocommerce-wholesale-prices' ),
                    'default'     => '',
                    'pro'         => 'upgrade_suffix',
                ),
            );

            // Filter to modify tax controls.
            $tax_controls = apply_filters( 'wwp_admin_setting_default_tax_controls', $tax_controls );

            return $tax_controls;
        }

        /**
         * Roles tab controls.
         *
         * @return array
         * @since 2.2.0 - Added.
         * @since 2.2.1 Remove administrator as option.
         */
        public function admin_roles_tab_controls() {
            $registered_roles = $this->get_registered_roles();

            /**
             * Administrator and Shop manager allowed by default.
             */
            $allowed_roles = get_option( 'wwp_roles_allowed_dashboard_access', array( 'shop_manager' ) );

            // Roles Options - Section.
            $roles_controls['admin_roles_options'] = array(
                array(
                    'type'        => 'select',
                    'label'       => __( 'Allowed roles', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_roles_allowed_dashboard_access',
                    'multiple'    => true,
                    'options'     => $registered_roles,
                    'default'     => ! empty( $allowed_roles ) ? $allowed_roles : array(),
                    'description' => __( 'Select the user roles allowed to access the Wholesale admin dashboard. Administrator and Shop manager are allowed by default.', 'woocommerce-wholesale-prices' ),
                ),
            );

            /**
             * Filter to modify the roles controls.
             *
             * @since 2.2.0
             *
             * @param array $roles_controls Roles controls.
             * @return array
             */
            $roles_controls = apply_filters( 'wwp_admin_setting_default_admin_roles_controls', $roles_controls );

            return $roles_controls;
        }

        /**
         * Help tab controls.
         *
         * @since  2.0
         * @access private
         * @return array
         */
        private function help_tab_controls() {
            $help_controls = array();

            $wwp_anonymous_data     = get_option( 'wwp_anonymous_data' );
            $wwp_anonymous_data_val = ( ! empty( $wwp_anonymous_data ) ) ? $wwp_anonymous_data : 'yes';

            $allow_tracking_dec = sprintf(
            // translators: %1$s link to premium add-on, %2$s </a> tag.
                __(
                    'Complete documentation on usage tracking is available %1$shere%2$s.',
                    'woocommerce-wholesale-prices'
                ),
                sprintf(
                    '<a target="_blank" href="%s"> ',
                    esc_url( WWP_Helper_Functions::get_utm_url( 'kb/usage-tracking', 'wwp', 'kb', 'helppageusagetracking' ) )
                ),
                '</a>',
            );

            // Help Options - Section.
            $help_controls['shipping_options'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Allow Usage Tracking', 'woocommerce-wholesale-prices' ),
                    'id'          => 'wwp_anonymous_data',
                    'input_label' => __( 'By allowing us to track usage data we can better help you because we know with which WordPress configurations, themes and plugins we should test.', 'woocommerce-wholesale-prices' ),
                    'multiple'    => false,
                    'description' => $allow_tracking_dec,
                    'default'     => $wwp_anonymous_data_val,
                ),
            );

            // Filter to modify help controls.
            $help_controls = apply_filters( 'wwp_admin_setting_default_help_controls', $help_controls );

            return $help_controls;
        }

        /**
         * Upgrade Tab Controls.
         *
         * @since  2.0
         * @access private
         * @return array
         */
        private function upgrade_tab_controls() {
            $upgrade_controls = array();

            // Upgrade Options - Section.
            $upgrade_controls['upgrade_options'] = array(
                array(
                    'type'    => 'html',
                    'id'      => 'wwp_settings_upgrade_code_block',
                    'classes' => 'wwp-upgrade-code-block',
                    'fields'  => array(
                        array(
                            'type'    => 'image',
                            'id'      => 'wwp_image_upgrade',
                            'classes' => 'wwp-img-logo',
                            'url'     => esc_url( WWP_IMAGES_URL ) . 'wholesale-suite-activation-notice-logo.png',
                        ),
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_upgrade',
                            'classes' => 'wwp-heading-upgrade',
                            'tag'     => 'h2',
                            'content' => __( 'Free vs Premium', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_upgrade',
                            'classes' => 'wwp-paragraph-upgrade',
                            'content' => __( 'If you are serious about growing your wholesale sales within your WooCommerce store then the Premium add-on to the free WooCommerce Wholesale Prices plugin that you are currently using can help you.', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'       => 'table',
                            'classes'    => 'upgrade-feature-table',
                            'paginated'  => false,
                            'editable'   => false,
                            'can_delete' => false,
                            'fields'     => array(
                                array(
                                    'title'     => __( 'Features', 'woocommerce-wholesale-prices' ),
                                    'dataIndex' => 'features',
                                    'key'       => 'features',
                                ),
                                array(
                                    'title'     => __( 'Free Plugin', 'woocommerce-wholesale-prices' ),
                                    'dataIndex' => 'free_plugin',
                                    'key'       => 'free_plugin',
                                ),
                                array(
                                    'title'     => __( 'Premium Add-on', 'woocommerce-wholesale-prices' ),
                                    'dataIndex' => 'premium_addon',
                                    'key'       => 'premium_addon',
                                ),
                            ),
                            'data'       => array(
                                array(
                                    'key'           => '1',
                                    'features'      => __( 'Flexible wholesale pricing', 'woocommerce-wholesale-prices' ),
                                    'free_plugin'   => __( '<span class="dashicons dashicons-no"></span> Not available. Only basic wholesale pricing at the product level allowed.', 'woocommerce-wholesale-prices' ),
                                    'premium_addon' => __( '<span class="dashicons dashicons-yes-alt"></span> Set wholesale pricing at the global (%), category (%) or the product level. Also includes quantity based pricing.', 'woocommerce-wholesale-prices' ),
                                ),
                                array(
                                    'key'           => '2',
                                    'features'      => __( 'Product visibility control', 'woocommerce-wholesale-prices' ),
                                    'free_plugin'   => __( '<span class="dashicons dashicons-no"></span> Not available', 'woocommerce-wholesale-prices' ),
                                    'premium_addon' => __( '<span class="dashicons dashicons-yes-alt"></span> Make products "Wholesale Only", hide "Retail Only" products from wholesale customers, create variations that are "Wholesale Only".', 'woocommerce-wholesale-prices' ),
                                ),
                                array(
                                    'key'           => '3',
                                    'features'      => __( 'Multiple wholesale role levels', 'woocommerce-wholesale-prices' ),
                                    'free_plugin'   => __( '<span class="dashicons dashicons-no"></span> Not available. Only one wholesale role.', 'woocommerce-wholesale-prices' ),
                                    'premium_addon' => __( '<span class="dashicons dashicons-yes-alt"></span> Add multiple wholesale role levels and use them to manage wholesale pricing, shipping mapping, payment mapping, tax exemption, order minimums and more.', 'woocommerce-wholesale-prices' ),
                                ),
                                array(
                                    'key'           => '4',
                                    'features'      => __( 'Advanced tax control', 'woocommerce-wholesale-prices' ),
                                    'free_plugin'   => __( '<span class="dashicons dashicons-no"></span> Not available', 'woocommerce-wholesale-prices' ),
                                    'premium_addon' => __( '<span class="dashicons dashicons-yes-alt"></span> Fine grained control over price tax display for wholesale, tax exemptions per user role and more.', 'woocommerce-wholesale-prices' ),
                                ),
                                array(
                                    'key'           => '5',
                                    'features'      => __( 'Shipping method mapping', 'woocommerce-wholesale-prices' ),
                                    'free_plugin'   => __( '<span class="dashicons dashicons-no"></span> Not available', 'woocommerce-wholesale-prices' ),
                                    'premium_addon' => __( '<span class="dashicons dashicons-yes-alt"></span> Manage which shipping methods wholesale customers can see and use compared to retail customers.', 'woocommerce-wholesale-prices' ),
                                ),
                                array(
                                    'key'           => '6',
                                    'features'      => __( 'Payment gateway mapping', 'woocommerce-wholesale-prices' ),
                                    'free_plugin'   => __( '<span class="dashicons dashicons-no"></span> Not available', 'woocommerce-wholesale-prices' ),
                                    'premium_addon' => __( '<span class="dashicons dashicons-yes-alt"></span> Manage which payment gateways wholesale customers can see and use compared to retail customers.', 'woocommerce-wholesale-prices' ),
                                ),
                                array(
                                    'key'           => '7',
                                    'features'      => __( 'Set product and order minimums', 'woocommerce-wholesale-prices' ),
                                    'free_plugin'   => __( '<span class="dashicons dashicons-no"></span> Not available', 'woocommerce-wholesale-prices' ),
                                    'premium_addon' => __( '<span class="dashicons dashicons-yes-alt"></span> Use product minimums and order minimums to ensure wholesale customers are meeting requirements.', 'woocommerce-wholesale-prices' ),
                                ),
                            ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_feature',
                            'classes' => 'wwp-paragraph-feature',
                            'content' => __( '+100\'s of other premium wholesale features', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'         => 'button',
                            'id'           => 'wwp_feature_list_btn',
                            'classes'      => 'wwp-feature-custom-btn',
                            'button_label' => __( 'See the full feature list', 'woocommerce-wholesale-prices' ),
                            'link'         => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'upgradepagewwppbutton' ) ),
                            'external'     => true,
                        ),
                    ),
                ),
            );

            $wws_installed_label = array(
                'type'    => 'paragraph',
                'id'      => 'wwp_bundle_param_installed',
                'content' => __( '<em><span class="dashicons dashicons-yes-alt"></span> Installed<em>', 'woocommerce-wholesale-prices' ),
                'classes' => 'wwp-package-link wwp-installed-label wwp-package-active',
            );

            // WWPP is active or not.
            $wwp_wwpp_is_active       = WWP_Helper_Functions::is_wwpp_active();
            $wwp_wwpp_installed_label = array(
                'type'         => 'button',
                'id'           => 'wwp_bundle_link1',
                'button_label' => __( 'Learn more about Prices Premium', 'woocommerce-wholesale-prices' ),
                'link'         => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'upgradepagewwpplearnmore' ) ),
                'external'     => true,
                'classes'      => 'wwp-package-link',
            );
            if ( $wwp_wwpp_is_active ) {
                $wwp_wwpp_installed_label = $wws_installed_label;
            }

            // WWOF is active or not.
            $wwp_wwof_is_active       = WWP_Helper_Functions::is_wwof_active();
            $wwp_wwof_installed_label = array(
                'type'         => 'button',
                'id'           => 'wwp_bundle_link2',
                'button_label' => __( 'Learn more about Order Form', 'woocommerce-wholesale-prices' ),
                'link'         => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-order-form', 'wwp', 'upsell', 'upgradepagewwoflearnmore' ) ),
                'external'     => true,
                'classes'      => 'wwp-package-link',
            );
            if ( $wwp_wwof_is_active ) {
                $wwp_wwof_installed_label = $wws_installed_label;
            }

            // WWLC is active or not.
            $wwp_wwlc_is_active       = WWP_Helper_Functions::is_wwlc_active();
            $wwp_wwlc_installed_label = array(
                'type'         => 'button',
                'id'           => 'wwp_bundle_link3',
                'button_label' => __( 'Learn more about Lead Capture', 'woocommerce-wholesale-prices' ),
                'link'         => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-lead-capture', 'wwp', 'upsell', 'upgradepagewwlclearnmore' ) ),
                'external'     => true,
                'classes'      => 'wwp-package-link',
            );
            if ( $wwp_wwlc_is_active ) {
                $wwp_wwlc_installed_label = $wws_installed_label;
            }

            // WPAY is active or not.
            $wwp_wpay_is_active       = WWP_Helper_Functions::is_wpay_active();
            $wwp_wpay_installed_label = array(
                'type'         => 'button',
                'id'           => 'wwp_bundle_link4',
                'button_label' => __( 'Learn more about Wholesale Payments', 'woocommerce-wholesale-prices' ),
                'link'         => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-payments', 'wwp', 'upsell', 'upgradepagewpaylearnmore' ) ),
                'external'     => true,
                'classes'      => 'wwp-package-link',
            );
            if ( $wwp_wpay_is_active ) {
                $wwp_wpay_installed_label = $wws_installed_label;
            }

            $upgrade_controls['upgrade_options2'] = array(
                array(
                    'type'    => 'html',
                    'id'      => 'wwp_settings_upgrade_code_block2',
                    'classes' => 'wwp-upgrade-code2-block',
                    'fields'  => array(
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_upgrade2',
                            'classes' => 'wwp-heading-upgrade',
                            'tag'     => 'h2',
                            'content' => __( 'Wholesale Suite Bundle', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_upgrade2',
                            'classes' => 'wwp-paragraph-upgrade',
                            'content' => __( 'Everything you need to sell to wholesale customers in WooCommerce. The most complete wholesale solution for building wholesale sales into your existing WooCommerce driven store.', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_package1',
                            'classes' => 'wwp-heading-package1 wwp-heading-package-bundle ' . ( $wwp_wwpp_is_active ? 'wwp-package-active' : '' ),
                            'tag'     => 'h3',
                            'content' => __( 'WooCommerce Wholesale Prices Premium', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_package1',
                            'classes' => 'wwp-paragraph-upgrade wwp-paragraph-package-bundle ' . ( $wwp_wwpp_is_active ? 'wwp-package-active' : '' ),
                            'content' => __( 'Easily add wholesale pricing to your products. Control product visibility. Satisfy your country\'s strictest tax requirements & control pricing display. Force wholesalers to use certain shipping & payment gateways. Enforce order minimums and individual product minimums. and 100\'s of other product and pricing related wholesale features.', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'image',
                            'id'      => 'wwp_bundle_image1',
                            'classes' => 'wwp-bundle-img',
                            'url'     => esc_url( WWP_IMAGES_URL ) . 'upgrade-page-wwpp-box.png',
                        ),
                        $wwp_wwpp_installed_label,
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_package2',
                            'classes' => 'wwp-heading-package2 wwp-heading-package-bundle ' . ( $wwp_wwof_is_active ? 'wwp-package-active' : '' ),
                            'tag'     => 'h3',
                            'content' => __( 'WoWooCommerce Wholesale Order Form', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_package2',
                            'classes' => 'wwp-paragraph-upgrade wwp-paragraph-package-bundle ' . ( $wwp_wwof_is_active ? 'wwp-package-active' : '' ),
                            'content' => __( 'Decrease frustration and increase order size with the most efficient one-page WooCommerce order form. Your wholesale customers will love it. No page loading means less back & forth, full ajax enabled add to cart buttons, responsive layout for on-the-go ordering and your whole product catalog available at your customer\'s fingertips.', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'image',
                            'id'      => 'wwp_bundle_image2',
                            'classes' => 'wwp-bundle-img',
                            'url'     => esc_url( WWP_IMAGES_URL ) . 'upgrade-page-wwof-box.png',
                        ),
                        $wwp_wwof_installed_label,
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_package3',
                            'classes' => 'wwp-heading-package3 wwp-heading-package-bundle ' . ( $wwp_wwlc_is_active ? 'wwp-package-active' : '' ),
                            'tag'     => 'h3',
                            'content' => __( 'WooCommerce Wholesale Lead Capture', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_package3',
                            'classes' => 'wwp-paragraph-upgrade wwp-paragraph-package-bundle ' . ( $wwp_wwlc_is_active ? 'wwp-package-active' : '' ),
                            'content' => __( 'Take the pain out of manually recruiting & registering wholesale customers. Lead Capture will save you admin time and recruit wholesale customers for your WooCommerce store on autopilot. Full registration form builder, automated email onboarding email sequence, full automated or manual approvals system and much more.', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'image',
                            'id'      => 'wwp_bundle_image3',
                            'classes' => 'wwp-bundle-img',
                            'url'     => esc_url( WWP_IMAGES_URL ) . 'upgrade-page-wwlc-box.png',
                        ),
                        $wwp_wwlc_installed_label,
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_package4',
                            'classes' => 'wwp-heading-package4 wwp-heading-package-bundle ' . ( $wwp_wpay_is_active ? 'wwp-package-active' : '' ),
                            'tag'     => 'h3',
                            'content' => __( 'WooCommerce Wholesale Payments', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_package4',
                            'classes' => 'wwp-paragraph-upgrade wwp-paragraph-package-bundle ' . ( $wwp_wpay_is_active ? 'wwp-package-active' : '' ),
                            'content' => __( 'Streamline your wholesale payment processes with flexible payment options tailored for wholesale customers. Offer multiple payment gateways, enforce minimum order amounts for specific gateways, and provide payment terms that suit your wholesale business. Improve cash flow management with partial payments, deposits, and custom payment deadlines. Empower your customers with a seamless checkout experience designed for wholesale transactions.', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'    => 'image',
                            'id'      => 'wwp_bundle_image4',
                            'classes' => 'wwp-bundle-img',
                            'url'     => esc_url( WWP_IMAGES_URL ) . 'upgrade-page-wpay-box.png',
                        ),
                        $wwp_wpay_installed_label,
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_upgrade2_2',
                            'classes' => 'wwp-paragraph-feature',
                            'content' => __( 'The WooCommerce extensions to grow your wholesale business', 'woocommerce-wholesale-prices' ),
                        ),
                        array(
                            'type'         => 'button',
                            'id'           => 'wwp_full_bundle_btn',
                            'classes'      => 'wwp-feature-custom-btn',
                            'button_label' => __( 'See the full bundle now', 'woocommerce-wholesale-prices' ),
                            'link'         => esc_url( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'upgradepagebundlebutton' ) ),
                            'external'     => true,
                        ),
                    ),
                ),
            );

            // Filter to modify upgrade controls.
            $upgrade_controls = apply_filters( 'wwp_admin_setting_default_upgrade_controls', $upgrade_controls );

            return $upgrade_controls;
        }

        /**
         * Allowed tags.
         *
         * @since  2.1.9
         * @access public
         * @return array
         */
        public function allowed_tags() {
            global $allowedposttags;

            $allowed_html = wp_kses_allowed_html( 'post' );

            $allowed_tags = array();
            if ( ! empty( $allowed_html ) && is_array( $allowed_html ) ) {
                $allowed_tags = array_keys( $allowed_html );
            } else {
                $allowed_tags = array_keys( $allowedposttags );
            }
            sort( $allowed_tags );

            return $allowed_tags;
        }

        /**
         * Allowed Attributes.
         *
         * @since  2.1.9
         * @access public
         * @return array
         */
        public function allowed_attrs() {
            global $allowedposttags;

            $allowed_html = wp_kses_allowed_html( 'post' );

            $allowed_attrs = array();
            if ( ! empty( $allowed_html ) && is_array( $allowed_html ) ) {
                // Only get the keys of the array.
                $allowed_keys  = $this->get_array_values( $allowed_html );
                $allowed_attrs = array_merge( ...array_values( $allowed_keys ) );
                $allowed_attrs = array_unique( $allowed_attrs );
            } else {
                $allowed_attrs = array_keys( array_merge( ...array_values( $allowedposttags ) ) );
            }
            sort( $allowed_attrs );

            return $allowed_attrs;
        }

        /**
         * Get array values.
         *
         * @param array $allowed_html Allowed HTML.
         *
         * @since  2.1.9
         * @access public
         * @return array
         */
        public function get_array_values( $allowed_html ) {
            $values = array_values( $allowed_html );

            $arrays = array();
            foreach ( $values as $key => $value ) {
                if ( ! empty( $value ) && is_array( $value ) ) {
                    array_push( $arrays, array_keys( $value ) );
                }
            }

            return $arrays;
        }

        /**
         * Get roles options.
         *
         * @since  2.1.9
         * @access public
         * @return array
         */
        public function get_role_options() {
            $roles = array();
            if ( $this->_all_wholesale_roles ) {
                foreach ( $this->_all_wholesale_roles as $roleKey => $role ) {
                    $roles[ $roleKey ] = $role['roleName'];
                }
            }

            return $roles;
        }

        /**
         * Get allowed admin roles.
         *
         * @since 2.2.0
         * @since 2.2.1 Only allow roles with edit_posts capability.
         * @access public
         * @return array
         */
        public function get_registered_roles() {
            $registered_user_roles = wp_roles()->roles;

            $registered_roles = array();

            foreach ( $registered_user_roles as $roleKey => $role ) {
                if ( 'administrator' === $roleKey ) {
                    continue;
                }

                // Check capabilities edit_posts.
                if ( ! empty( $role['capabilities']['edit_posts'] ) ) {
                    $registered_roles[ $roleKey ] = $role['name'];
                }
            }

            /**
             * Filter to modify the registered roles.
             *
             * @param array $registered_roles Registered roles.
             * @since 2.2.0
             * @return array
             */
            return apply_filters( 'wwp_allowed_admin_roles', array_filter( $registered_roles ) );
        }

        /**
         * Group save custom action.
         *
         * @param array $options Options.
         *
         * @since  2.1.9
         * @access public
         */
        public function group_settings_group_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $discount_mapping = array_map( 'sanitize_text_field', $options );

            $saved_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING );
            if ( ! is_array( $saved_discount_mapping ) ) {
                $saved_discount_mapping = array();
            }

            if ( ! array_key_exists( $discount_mapping['wholesale_role'], $saved_discount_mapping ) ) {
                $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

                if ( 'yes' === $wwpp_product_cache_option ) {
                    global $wc_wholesale_prices_premium;
                    $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();
                }

                $saved_discount_mapping[ $discount_mapping['wholesale_role'] ] = $discount_mapping['general_discount'];
                update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $saved_discount_mapping, 'no' );
                $response = array( 'status' => 'success' );

                do_action( 'wwpp_add_wholesale_role_general_discount_mapping' );

            } else {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Duplicate Entry, Entry Already Exists', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group delete custom action.
         *
         * @param array $options Options.
         *
         * @since  2.1.9
         * @access public
         */
        public function group_settings_group_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $wholesale_role = sanitize_text_field( $options['wholesale_role'] );

            $saved_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING );
            if ( ! is_array( $saved_discount_mapping ) ) {
                $saved_discount_mapping = array();
            }

            if ( array_key_exists( $wholesale_role, $saved_discount_mapping ) ) {

                $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

                if ( 'yes' === $wwpp_product_cache_option ) {
                    global $wc_wholesale_prices_premium;
                    $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();
                }

                unset( $saved_discount_mapping[ $wholesale_role ] );
                update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $saved_discount_mapping, 'no' );
                $response = array( 'status' => 'success' );

                do_action( 'wwpp_delete_wholesale_role_general_discount_mapping' );

            } else {
                $response = array(
                    'status'        => 'error',
                    'error_message' => __( 'Entry to be deleted does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Group edit custom action.
         *
         * @param array $options Options.
         *
         * @since  2.1.9
         * @access public
         */
        public function group_settings_group_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $discount_mapping = array_map( 'sanitize_text_field', $options );

            $saved_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING );
            if ( ! is_array( $saved_discount_mapping ) ) {
                $saved_discount_mapping = array();
            }

            if ( array_key_exists( $discount_mapping['wholesale_role'], $saved_discount_mapping ) ) {

                $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

                if ( 'yes' === $wwpp_product_cache_option ) {
                    global $wc_wholesale_prices_premium;
                    $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();
                }

                $saved_discount_mapping[ $discount_mapping['wholesale_role'] ] = $discount_mapping['general_discount'];
                update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $saved_discount_mapping, 'no' );
                $response = array( 'status' => 'success' );

                do_action( 'wwpp_edit_wholesale_role_general_discount_mapping' );

            } else {
                $response = array(
                    'status'        => 'error',
                    'error_message' => __( 'Entry to be edited does not exist', 'woocommerce-wholesale-prices-premium' ),
                );
            }

            return $response;
        }

        /**
         * Add body class for wholesale page only.
         *
         * @param string $classes Body classes.
         *
         * @since  2.1.11.1
         * @access public
         *
         * @return string
         */
        public function add_admin_body_class( $classes ) {
            global $pagenow;

            if ( 'admin.php' === $pagenow && filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) === 'wholesale-settings' ) {
                $classes .= ' wws-settings-page ';
            }

            return $classes;
        }

        /**
         * New Settings admin notice.
         *
         * @since 2.1.12
         * @access public
         */
        public function new_settings_notice() {
            if ( current_user_can( 'manage_woocommerce' ) && get_option( 'wwp_admin_notice_new_settings_hide' ) !== 'yes' && filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) === 'wc-settings' ) {
                $settings_page = admin_url( 'admin.php?page=wholesale-settings' );
                ?>
                    <div class="updated notice wwp-new-settings-notice">
                        <p><img src="<?php echo esc_url( WWP_IMAGES_URL ); ?>wholesale-suite-activation-notice-logo.png" alt="" /></p>
                        <p><?php esc_html_e( 'Hey there! We recently moved the settings pages for all Wholesale Suite plugins to the Wholesale menu. You\'ll now find them under Wholesale->Settings.', 'woocommerce-wholesale-prices' ); ?>
                        <div class="wwp-new-settings-action-links">
                            <a href="<?php echo esc_url( $settings_page ); ?>" class="wwp-new-settings-take-btn">
                                <?php esc_html_e( 'I understand - take me there', 'woocommerce-wholesale-prices' ); ?>
                            </a>
                            <a href="javascript:void(0);" class="wwp-new-settings-dismiss">
                                <?php esc_html_e( 'Dismiss', 'woocommerce-wholesale-prices' ); ?>
                            </a>
                        </div>
                    </div>
                <?php
            }
        }

        /**
         * Hide getting started notice on close.
         * Attached to wwp_admin_notice_new_settings_hide
         *
         * @since 2.1.12
         * @access public
         */
        public function wwp_new_settings_notice_hide() {
            if ( ! WWP_Helper_Functions::verify_ajax_nonce( 'wwp_new_settings_notice_nonce' ) ) {
                // Security check failure.
                return;
            }

            update_option( 'wwp_admin_notice_new_settings_hide', 'yes', 'no' );
            wp_send_json( array( 'status' => 'success' ) );
        }

        /**
         * Execute model.
         *
         * @since  2.0
         * @access public
         */
        public function run() {

            // Load react scripts.
            add_action( 'admin_enqueue_scripts', array( $this, 'load_back_end_styles_and_scripts' ), 10, 1 );

            // Add admin body class.
            add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ), 10, 1 );

            // REST API for dashboard page.
            add_action( 'rest_api_init', array( $this, 'rest_api_settings' ) );

            // Add group settings action.
            add_action( 'wwp_group_settings_group_save', array( $this, 'group_settings_group_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_delete', array( $this, 'group_settings_group_delete' ), 10, 1 );
            add_action( 'wwp_group_settings_group_edit', array( $this, 'group_settings_group_edit' ), 10, 1 );

            // New settings notice.
            add_action( 'admin_notices', array( $this, 'new_settings_notice' ), 10 );
            add_action( 'wp_ajax_wwp_new_settings_notice_hide', array( $this, 'wwp_new_settings_notice_hide' ) );
        }
    }
}
