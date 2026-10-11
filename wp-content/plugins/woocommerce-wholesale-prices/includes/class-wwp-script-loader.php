<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Script_Loader' ) ) {

    /**
     * Model that houses the logic of loading in scripts to various pages of the plugin.
     *
     * @since 1.4.0
     */
    class WWP_Script_Loader { // phpcs:ignore


        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWP_Script_Loader.
         *
         * @since 1.4.0
         * @access private
         * @var WWP_Script_Loader
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.4.0
         * @access private
         * @var WWP_Wholesale_Roles
         */
        private $_wwp_wholesale_roles;

        /**
         * Model that houses logic of wholesale prices.
         *
         * @since 1.4.0
         * @access private
         * @var WWP_Wholesale_Prices
         */
        private $_wwp_wholesale_prices;

        /**
         * Current WWP version.
         *
         * @since 1.3.1
         * @access private
         * @var int
         */
        private $_wwp_current_version;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWP_Script_Loader constructor.
         *
         * @since 1.4.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Script_Loader model.
         */
        public function __construct( $dependencies ) {
            $this->_wwp_wholesale_roles  = $dependencies['WWP_Wholesale_Roles'];
            $this->_wwp_wholesale_prices = $dependencies['WWP_Wholesale_Prices'];
            $this->_wwp_current_version  = $dependencies['WWP_CURRENT_VERSION'];
        }

        /**
         * Ensure that only one instance of WWP_Script_Loader is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.4.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Script_Loader model.
         * @return WWP_Script_Loader
         */
        public static function instance( $dependencies ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Load admin or backend related styles and scripts.
         * Only load em on the right time and on the right place.
         *
         * @since 1.0.0
         * @since 1.4.0 Refactor codebase and move to its own model.
         * @since 2.1.0 Added custom decimal place for calculation on percentage discount, added also filter so that this can be overridden based on your requirements.
         * @since 2.2.8 Enqueue Wholesale Reports teaser assets when WWPP is inactive (#885).
         * @since 2.2.9 Localize base-currency display data on the product edit screen so wholesale-price labels update live when the Aelia product base currency changes.
         * @access public
         *
         * @param string $handle Hook suffix for the current admin page.
         */
        public function load_back_end_styles_and_scripts( $handle ) {
            // Prepare everything needed.
            $screen = get_current_screen();

            $post_type = get_post_type();
            if ( ! $post_type && isset( $_GET['post_type'] ) ) { // phpcs:ignore
                $post_type = sanitize_text_field( $_GET['post_type'] ); // phpcs:ignore
            }

            $review_response = get_option( WWP_REVIEW_REQUEST_RESPONSE );

            /***********************************************************************************************************
             * General Backend Overrides (Careful, this is loaded on all backend pages!)
             */
            wp_enqueue_style( 'wwp_wcoverrides_css', WWP_CSS_URL . 'wwp-back-end-wcoverrides.css', array(), $this->_wwp_current_version, 'all' );
            wp_enqueue_script( 'wwp_wholesale_main_js', WWP_JS_URL . 'wholesale-main.js', array( 'jquery' ), $this->_wwp_current_version, true );

            $get_wpay_started_url = esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-payments', 'wwp', 'protip', 'wpaygatewaytablefooter' ) );
            wp_localize_script(
                'wwp_wholesale_main_js',
                'wwp_wholesale_main_object',
                array(
                    'is_wpay_active'              => WWP_Helper_Functions::is_wpay_active() ?? '',
                    'i18n_get_wholesale_payments' => sprintf(
                        // translators: %1$s <a> open tag, %2$s </a> close tag.
                        __(
                            'Want to do NET 30/60/90 Invoices for your wholesale customers? %1$sGet Wholesale Payments%2$s.',
                            'woocommerce-wholesale-prices'
                        ),
                        '<a href="' . $get_wpay_started_url . '" target="_blank">',
                        '</a>'
                    ),
                )
            );

            /***********************************************************************************************************
             * Show Review Request Popup
             */
            if ( is_admin() && current_user_can( 'manage_options' ) && get_option( WWP_SHOW_REQUEST_REVIEW ) === 'yes' && ( 'review-later' === $review_response || empty( $review_response ) ) ) {

                if ( WWP_Helper_Functions::is_wwpp_active() ) {
                    $msg = sprintf(
                        // translators: 1. <p> tag, 2. </p> tag, 3. <a> tag link to wholesale suite support, 4. </a> tag.
                        __(
                            '%1$sWe see you have been using Wholesale Suite for a couple of weeks now - thank you once again for your purchase and we hope you are enjoying it so far!%2$s
                            %1$sWe\'d really appreciate it if you could take a few minutes to write a 5-star review of our Wholesale Prices plugin on WordPress.org!%2$s
                            %1$sYour comment will go a long way to helping us grow and giving new users the confidence to give us a try.%2$s
                            %1$sThanks in advance, we are looking forward to reading it!%2$s
                            %1$sPS. If ysou ever need support, please just %3$sget in touch here%4$s.%2$s',
                            'woocommerce-wholesale-prices'
                        ),
                        '<p>',
                        '</p>',
                        '<a href="https://wholesalesuiteplugin.com/support/" target="_blank">',
                        '</a>'
                    );
                } else {
                    $msg = sprintf(
                        // translators: 1. <p> tag, 2. </p> tag.
                        __(
                            '%1$sThanks for using our free WooCommerce Wholesale Prices plugin - we hope you are enjoying it so far.%2$s
                            %1$sWe\'d really appreciate it if you could take a few minutes to write a 5-star review of our Wholesale Prices plugin on WordPress.org!%2$s
                            %1$sYour comment will go a long way to helping us grow and giving new users the confidence to give us a try.%2$s
                            %1$sThanks in advance, we are looking forward to reading it!%2$s',
                            'woocommerce-wholesale-prices'
                        ),
                        '<p>',
                        '</p>'
                    );

                    ?>
                    <p><?php esc_html_e( 'Thanks for using our free WooCommerce Wholesale Prices plugin - we hope you are enjoying it so far.', 'woocommerce-wholesale-prices' ); ?></p>
                    <p><?php esc_html_e( 'We\'d really appreciate it if you could take a few minutes to write a 5-star review of our Wholesale Prices plugin on WordPress.org!', 'woocommerce-wholesale-prices' ); ?></p>
                    <p><?php esc_html_e( 'Your comment will go a long way to helping us grow and giving new users the confidence to give us a try.', 'woocommerce-wholesale-prices' ); ?></p>
                    <p><?php esc_html_e( 'Thanks in advance, we are looking forward to reading it!', 'woocommerce-wholesale-prices' ); ?></p>
                    <?php
                }

                wp_enqueue_style( 'wwp-wwp-review', WWP_CSS_URL . '/wwp-review.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_style( 'wwp-vex-css', WWP_JS_URL . 'lib/vexjs/vex.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_style( 'wwp-vex-theme-plain-css', WWP_JS_URL . 'lib/vexjs/vex-theme-plain.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_script( 'wwp-vex-js', WWP_JS_URL . 'lib/vexjs/vex.combined.min.js', array(), $this->_wwp_current_version, true );
                wp_enqueue_script( 'wwp-review-request', WWP_JS_URL . 'backend/wwp-review-request.js', array(), $this->_wwp_current_version, true );
                wp_localize_script(
                    'wwp-review-request',
                    'review_request_args',
                    array(
						'js_url'      => WWP_IMAGES_URL,
						'msg'         => $msg,
						'review_link' => 'https://wordpress.org/support/plugin/woocommerce-wholesale-prices/reviews/?filter=5#new-post',
                        'nonce'       => wp_create_nonce( 'wwp_request_review_nonce' ),
                    )
                );
            }

            /**
             * Product Listing & Edit Product Pages
             */
            if ( in_array( $screen->id, array( 'edit-product' ), true ) ) {
                // Product listing.

                wp_enqueue_style( 'wwp_cpt_product_listing_admin_main_css', WWP_CSS_URL . 'backend/cpt/product/wwp-cpt-product-listing-admin-main.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_script( 'wwp_cpt_product_listing_admin_main_js', WWP_JS_URL . 'backend/cpt/product/wwp-cpt-product-listing-admin-main.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-accordion' ), $this->_wwp_current_version, false ); // Must not be loaded on footer, else it won't work.

            } elseif ( ( 'post-new.php' === $handle || 'post.php' === $handle ) && 'product' === $post_type ) {
                // Single product admin page ( new and edit product ).

                // VEX.
                wp_enqueue_style( 'wwp-vex-css', WWP_JS_URL . 'lib/vexjs/vex.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_style( 'wwp-vex-theme-plain-css', WWP_JS_URL . 'lib/vexjs/vex-theme-plain.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_style( 'wwp-vex-styling-css', WWP_CSS_URL . 'wwp-vex-styling.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_script( 'wwp-vex-js', WWP_JS_URL . 'lib/vexjs/vex.combined.min.js', array(), $this->_wwp_current_version, true );

                // Chosen.
                wp_enqueue_style( 'wwp_chosen_css', WWP_JS_URL . 'lib/chosen/chosen.min.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_script( 'wwp_chosen_js', WWP_JS_URL . 'lib/chosen/chosen.jquery.min.js', array( 'jquery' ), $this->_wwp_current_version, true );

                // Single Product specific CSS/JS.
                wp_enqueue_style( 'wwp_cpt_product_single_admin_main_css', WWP_CSS_URL . 'backend/cpt/product/wwp-cpt-product-single-admin-main.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_script( 'wwp_cpt_product_single_admin_main_js', WWP_JS_URL . 'backend/cpt/product/wwp-cpt-product-single-admin-main.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-accordion' ), $this->_wwp_current_version, true );

                // When Aelia Currency Switcher is active, provide the currency display data so the
                // wholesale-price field labels can update live when the product base currency changes.
                if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                    $wc_currencies  = get_woocommerce_currencies();
                    $currencies_map = array();
                    foreach ( WWP_ACS_Integration_Helper::enabled_currencies() as $currency_code ) {
                        $currencies_map[ $currency_code ] = array(
                            'name'   => isset( $wc_currencies[ $currency_code ] ) ? $wc_currencies[ $currency_code ] : $currency_code,
                            // Decode here so the JS label builder (which uses text nodes for output safety)
                            // renders the real glyph; currency symbols are entity-encoded (e.g. &euro;) and
                            // wp_localize_script only decodes top-level scalars, not these nested values.
                            'symbol' => html_entity_decode( get_woocommerce_currency_symbol( $currency_code ), ENT_QUOTES, 'UTF-8' ),
                        );
                    }

                    $roles_symbols = array();
                    foreach ( $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() as $role_key => $role ) {
                        $roles_symbols[ $role_key ] = array(
                            // Decode for the same reason as the currency symbols above: a role's override
                            // symbol is entity-encoded and the JS builder renders it via a text node.
                            'currency_symbol' => html_entity_decode( isset( $role['currency_symbol'] ) ? $role['currency_symbol'] : '', ENT_QUOTES, 'UTF-8' ),
                            'name'            => isset( $role['roleName'] ) ? $role['roleName'] : '',
                        );
                    }

                    wp_localize_script(
                        'wwp_cpt_product_single_admin_main_js',
                        'wwp_base_currency_label_params',
                        array(
                            'currencies'         => $currencies_map,
                            'roles'              => $roles_symbols,
                            'base_currency_text' => __( 'Base Currency', 'woocommerce-wholesale-prices' ),
                            /* translators: %1$s: Wholesale role name, %2$s: currency name and symbol */
                            'tip_format'         => __( 'Only applies to users with the role of %1$s for %2$s currency', 'woocommerce-wholesale-prices' ),
                        )
                    );
                }

                wp_enqueue_script( 'wwp_single_variable_product_admin_custom_bulk_actions_js', WWP_JS_URL . 'backend/cpt/product/wwp-single-variable-product-admin-custom-bulk-actions.js', array( 'jquery' ), $this->_wwp_current_version, true );

                wp_localize_script(
                    'wwp_single_variable_product_admin_custom_bulk_actions_js',
                    'wwp_custom_bulk_actions_params',
                    array(
						'wholesale_roles'     => $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles(),
						'i18n_prompt_message' => __( 'Enter a value (leave blank to remove pricing)', 'woocommerce-wholesale-prices' ),
                    )
                );

                // Percentage Wholesale Pricing On Product Level.
                wp_enqueue_script( 'wwp_currency_js', WWP_JS_URL . 'lib/currency/currency.min.js', array(), $this->_wwp_current_version, true );
                wp_enqueue_script( 'wwp_percentage_wholesale_pricing_on_product_level_js', WWP_JS_URL . 'backend/wwp-percentage-wholesale-pricing-on-product-level.js', array( 'jquery', 'jquery-tiptip' ), $this->_wwp_current_version, true );
                wp_localize_script(
                    'wwp_percentage_wholesale_pricing_on_product_level_js',
                    'wwp_percentage_wholesale_options',
                    array(
						'wholesale_roles'            => $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles(),
						'is_aelia_active'            => WWP_ACS_Integration_Helper::aelia_currency_switcher_active(),
                        'is_wc_subscriptions_active' => is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ? 'yes' : 'no',
						'decimal_sep'                => get_option( 'woocommerce_price_decimal_sep' ),
						'decimal_num'                => get_option( 'woocommerce_price_num_decimals' ),
						'thousand_sep'               => get_option( 'woocommerce_price_thousand_sep' ),
						'calculation_decimal_places' => apply_filters( 'wwp_percentage_discount_calculation_decimal_places', wc_get_price_decimals() ),
						'i18n_discount_greater_than_100_percent_error' => __( 'Please enter in a value not greater than 100 percent.', 'woocommerce-wholesale-prices' ),
						'i18n_discount_less_than_0_percent_error' => __( 'Please enter in a value not less than 0 percent', 'woocommerce-wholesale-prices' ),
                    )
                );

                // Upsell specific.
                if ( ! WWP_Helper_Functions::is_wwpp_active() ) {
                    wp_enqueue_style( 'wwp-backend-product-page-upsell', WWP_CSS_URL . 'wwp-backend-product-page-upsell.css', array(), $this->_wwp_current_version, 'all' );
                    wp_enqueue_script( 'wwp-backend-product-page-upsell', WWP_JS_URL . 'backend/wwp-backend-product-page-upsell.js', array(), $this->_wwp_current_version, true );

                    wp_localize_script(
                        'wwp-backend-product-page-upsell',
                        'backend_product_page_upsell_args',
                        array(
							'images_url'           => WWP_IMAGES_URL,
							'wholesale_prices'     => array(
								'title' => '<h4>' . __( 'Upgrade For Additional Wholesale Price Levels', 'woocommerce-wholesale-prices' ) . '</h4>',
								'msg'   => '<p>' . __(
									'WooCommerce Wholesale Prices Premium lets you add additional levels of pricing
                                    by adding more wholesale user roles. Click below for all the details.',
									'woocommerce-wholesale-prices'
                                ) . '</p>',
								'link'  => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwpproducteditadditionalprices' ) ),
							),
							'product_visibility'   => array(
								'title' => '<h4>' . __( 'Upgrade For Product Visibility Features', 'woocommerce-wholesale-prices' ) . '</h4>',
								'msg'   => '<p>' . __(
									'WooCommerce Wholesale Prices Premium lets you change the visibility of
                                    your products by selecting which wholesale roles should be able to see it. Click below for all
                                    the details.',
									'woocommerce-wholesale-prices'
                                ) . '</p>',
								'link'  => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwpproducteditproductvisibility' ) ),
							),
                            'wholesale_sale_price' => array(
								'title' => '<h4>' . __( 'Upgrade For Wholesale Sale Price Features', 'woocommerce-wholesale-prices' ) . '</h4>',
								'msg'   => '<p>' . __(
									'WooCommerce Wholesale Prices Premium lets you to add a sale price for the wholesale price.
                                    Click below for all the details.',
									'woocommerce-wholesale-prices'
                                ) . '</p>',
								'link'  => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwpproducteditproductvisibility' ) ),
							),
							'button_text'          => __( 'See Features & Pricing', 'woocommerce-wholesale-prices' ),
							'bonus_text'           => sprintf(
                                // translators: 1. Opening bold tag, 2. Opening green text tag, 3. Closing span tag, 4. Closing paragraph tag.
                                __( '%1$sBonus:%3$s Wholesale Prices lite users get %2$s50&#37; off regular price%3$s automatically applied at checkout.%4$s', 'woocommerce-wholesale-prices' ),
                                '<p><span style="font-weight: bold;">',
                                '<span class="green-text">',
                                '</span>',
                                '</p>'
                            ),
                        )
                    );

                    // Wholesale sale price dummy field js.
                    wp_enqueue_script( 'wwp-wholesale-sale-price-dummy', WWP_JS_URL . 'backend/wwp-wholesale-sale-price-dummy.js', array(), $this->_wwp_current_version, true );
                }
            }

            $file_version = wp_get_environment_type() !== 'production' ? time() : $this->_wwp_current_version;
            if ( get_option( 'wwp_admin_notice_getting_started_show' ) === 'yes' &&
                filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) === 'getting-started-with-wholesale-suite' ) {
                wp_enqueue_style( 'wwp-getting-started-css', WWP_CSS_URL . 'getting-started.css', array( 'dashicons' ), $file_version );
            }

            /**
             * Backend Common CSS
             */
            if ( get_option( 'wwp_admin_notice_getting_started_show' ) === 'yes' || get_option( WWP_SHOW_INSTALL_ACFWF_NOTICE ) === 'yes' || ( isset( $_GET['tab'] ) && 'wwp_settings' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_enqueue_style( 'wwp_backend_main_css', WWP_CSS_URL . 'wwp-back-end-main.css', array(), $file_version );
            }

            /**
             * Notices
             */
            // Getting Started notice. Notice shows up on every page in the backend unless the message is dismissed.
            if ( get_option( 'wwp_admin_notice_getting_started_show' ) === 'yes' ) {
                wp_enqueue_script( 'wwp_getting_started_js', WWP_JS_URL . 'backend/wwp-getting-started.js', array( 'jquery' ), $this->_wwp_current_version, true );
                wp_localize_script(
                    'wwp_getting_started_js',
                    'wwp_getting_started_js_params',
                    array(
                        'nonce' => wp_create_nonce( 'wwp_getting_started_nonce' ),
                    )
                );
            }

            // Install ACFWF notice. Notice shows up on every page in the backend unless the message is dismissed.
            if ( get_option( WWP_SHOW_INSTALL_ACFWF_NOTICE ) === 'yes' ) {
                wp_enqueue_script( 'wwp_acfwf_install_notice_js', WWP_JS_URL . 'backend/wwp-acfwf-install-notice.js', array( 'jquery' ), $this->_wwp_current_version, true );
                wp_localize_script(
                    'wwp_acfwf_install_notice_js',
                    'wwp_acfwf_install_notice_js_params',
                    array(
                        'nonce' => wp_create_nonce( 'wwp_hide_acfwf_install_notice_nonce' ),
                    )
                );
            }

            if ( ! WWP_Helper_Functions::is_wpay_active() && ! WWP_Helper_Functions::is_user_wws_notification_dismissed( get_current_user_id(), 'wpay-wc-settings-banner' ) ) {
                wp_enqueue_script( 'wwp_wpay_suggestion_install_notice', WWP_JS_URL . 'backend/wwp-show-wpay-suggestions-on-woo-settings.js', array( 'jquery' ), $this->_wwp_current_version, true );
                $get_wpay_url = esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-payments', 'wwp', 'upsell', 'wcsettingsbanner' ) );

                wp_localize_script(
                    'wwp_wpay_suggestion_install_notice',
                    'wwp_wpay_wc_settings_params',
                    array(
                        'nonce'          => wp_create_nonce( 'wp_wpay_toolbar_dismiss_notice' ),
                        'dismiss_label'  => esc_attr__( 'Dismiss', 'woocommerce-wholesale-prices' ),
                        'banner_message' => sprintf(
                            // translators: %1$s <a> open tag, %2$s </a> close tag.
                            __(
                                'Want to do NET 30/60/90 Invoices for your wholesale customers? %1$sGet Wholesale Payments%2$s.',
                                'woocommerce-wholesale-prices'
                            ),
                            '<a href="' . $get_wpay_url . '" target="_blank" rel="noopener noreferrer">',
                            '</a>'
                        ),
                    )
                );
                wp_enqueue_style( 'wwp_wpay_suggestion_install_notice', WWP_CSS_URL . 'backend/wwp-show-wpay-suggestions-on-woo-settings.css', array(), $this->_wwp_current_version, 'all' );
            }

            // New Settings notice. Notice shows up on every page in the backend unless the message is dismissed.
            if ( 'yes' !== get_option( 'wwp_admin_notice_new_settings_hide' ) ) {
                wp_enqueue_script( 'wwp_new_settings_notice_js', WWP_JS_URL . 'backend/wwp-new-settings-notice.js', array( 'jquery' ), $this->_wwp_current_version, true );
                wp_localize_script(
                    'wwp_new_settings_notice_js',
                    'wwp_new_settings_notice_js_params',
                    array(
                        'nonce' => wp_create_nonce( 'wwp_new_settings_notice_nonce' ),
                    )
                );
            }

            /**
             * Wholesale Roles Page
             */
            // Load script if premium add on isn't present.
            if (
                str_contains( $screen->id, 'wwpp-wholesale-roles-page' ) &&
                ! WWP_Helper_Functions::is_wwpp_active()
            ) {

                // Vex.
                wp_enqueue_style( 'wwp-vex-css', WWP_JS_URL . 'lib/vexjs/vex.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_style( 'wwp-vex-theme-plain-css', WWP_JS_URL . 'lib/vexjs/vex-theme-plain.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_style( 'wwp-vex-styling-css', WWP_CSS_URL . 'wwp-vex-styling.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_script( 'wwp-vex-js', WWP_JS_URL . 'lib/vexjs/vex.combined.min.js', array(), $this->_wwp_current_version, true );

                // Toastr.
                wp_enqueue_style( 'wwp_toastr_css', WWP_JS_URL . 'lib/toastr/toastr.min.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_script( 'wwp_toastr_js', WWP_JS_URL . 'lib/toastr/toastr.min.js', array( 'jquery' ), $this->_wwp_current_version, true );

                // Roles page specific css styling.
                wp_enqueue_style( 'wwp_roles_page_css', WWP_CSS_URL . 'wwp-backend-wholesale-roles.css', array(), $this->_wwp_current_version, 'all' );

                wp_enqueue_script( 'wwp_backEndAjaxServices_js', WWP_JS_URL . 'app/modules/BackEndAjaxServices.js', array( 'jquery' ), $this->_wwp_current_version, true );
                wp_enqueue_script( 'wwp_wholesaleRolesFormActions_js', WWP_JS_URL . 'app/modules/WholesaleRolesFormActions.js', array( 'jquery' ), $this->_wwp_current_version, true );

                wp_enqueue_script( 'wwp_wholesaleRolesListingActions_js', WWP_JS_URL . 'app/modules/WholesaleRolesListingActions.js', array( 'jquery' ), $this->_wwp_current_version, true );
                wp_localize_script(
                    'wwp_wholesaleRolesListingActions_js',
                    'wwp_wholesaleRolesListingActions_params',
                    array(
						'i18n_yes' => __( 'Yes', 'woocommerce-wholesale-prices' ),
						'i18n_no'  => __( 'No', 'woocommerce-wholesale-prices' ),
                    )
                );

                wp_enqueue_script( 'wwp_wholesale_roles_main_js', WWP_JS_URL . 'app/wholesale-roles-main.js', array( 'jquery', 'jquery-tiptip' ), $this->_wwp_current_version, true );
                wp_localize_script(
                    'wwp_wholesale_roles_main_js',
                    'wwp_wholesale_roles_main_params',
                    array(
						'i18n_enter_role_name'          => __( 'Please Enter Role Name', 'woocommerce-wholesale-prices' ),
						'i18n_error_wholesale_form'     => __( 'Error in Wholesale Form', 'woocommerce-wholesale-prices' ),
						'i18n_enter_role_key'           => __( 'Please Enter Role Key', 'woocommerce-wholesale-prices' ),
						'i18n_role_successfully_edited' => __( 'Wholesale Role Successfully Edited', 'woocommerce-wholesale-prices' ),
						'i18n_successfully_edited_role' => __( 'Successfully Edited Role', 'woocommerce-wholesale-prices' ),
						'i18n_failed_edit_role'         => __( 'Failed to Edit Wholesale Role', 'woocommerce-wholesale-prices' ),
						'i18n_upsell_message'           => $this->role_page_upsell_message(),
						'nonce'                         => wp_create_nonce( 'wwp_role_edit_nonce' ),
                    )
                );
            }

            /**
             * Wholesale Lead Capture Page
             */
            // Load script if premium add on isn't present.
            if (
                str_contains( $screen->id, 'wwp-lead-capture-page' ) &&
                ! WWP_Helper_Functions::is_wwlc_active()
            ) {
                wp_enqueue_style( 'wwp_lead_capture_page_css', WWP_CSS_URL . 'wwp-lead-capture.css', array(), $this->_wwp_current_version, 'all' );
            }

            /**
             * Wholesale Quotes Page
             */
            // Load script if premium add on isn't present.
            if (
                str_contains( $screen->id, 'wwp-wholesale-quotes-page' ) &&
                ! WWP_Helper_Functions::is_wwq_active()
            ) {
                wp_enqueue_style( 'wwp_wholesale_quotes_page_css', WWP_CSS_URL . 'wwp-wholesale-quotes.css', array(), $this->_wwp_current_version, 'all' );
            }

            /**
             * Wholesale Order Form Page
             */
            // Load script if order form add on isn't present.
            if (
                str_contains( $screen->id, 'order-forms' ) &&
                ! WWP_Helper_Functions::is_wwof_active()
            ) {
                wp_enqueue_style( 'wwp_order_form_page_css', WWP_CSS_URL . 'wwp-order-form.css', array(), $this->_wwp_current_version, 'all' );
            }

            /**
             * Help Page
             */
            if ( str_contains( $screen->id, 'wws-help-page' ) ) {
                wp_enqueue_style( 'wwp_help_page_css', WWP_CSS_URL . 'wwp-help-page.css', array(), $this->_wwp_current_version, 'all' );
            }

            /***********************************************************************************************************
             * About Page
             */
            if ( str_contains( $screen->id, 'wws-about-page' ) ) {
                wp_enqueue_style( 'wwp_about_page_css', WWP_CSS_URL . 'wwp-about-page.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_script( 'wwp_about_page_js', WWP_JS_URL . 'backend/wwp-about-page.js', array( 'jquery' ), $this->_wwp_current_version, true );
                wp_localize_script(
                    'wwp_about_page_js',
                    'about_page_params',
                    array(
						'nonce'               => wp_create_nonce( 'wwp_install_plugin' ),
                        'i18n_installed_text' => __( 'Installed', 'woocommerce-wholesale-prices' ),
                        'i18n_install_text'   => __( 'Install Plugin', 'woocommerce-wholesale-prices' ),
                    )
                );
            }

            /**
             * Wholesale Payments Education page.
             */
            if ( str_contains( $screen->id, 'wholesale-payments' ) ) {
                wp_enqueue_style(
                    'wwp_wholesale_payments_css',
                    WWP_CSS_URL . 'wwp-wholesale-payments-page.css',
                    array(),
                    $this->_wwp_current_version,
                    'all'
                );
            }

            /**
             * Advanced Coupons Page
             */
            // Load script if ACFW is not present. Test if Advanced Coupon plugin is not installed or if it is, check if it's not active.
            if ( str_contains( $screen->id, 'marketing_page_advanced-coupons-marketing' ) &&
                (
                    ! WWP_Helper_Functions::is_acfwf_installed() ||
                    ( WWP_Helper_Functions::is_acfwf_installed() && ! WWP_Helper_Functions::is_acfwf_active() )
                )
            ) {
                wp_enqueue_style( 'wwp_advanced_coupons_page_css', WWP_CSS_URL . 'wwp-advanced-coupons-page.css', array(), $this->_wwp_current_version, 'all' );
            }

            /**
             * Upgrade To Premium Page
             */
            if ( str_contains( $screen->id, 'upgrade-to-premium-page' ) &&
                (
                    ! WWP_Helper_Functions::is_wwp_installed() ||
                    ( WWP_Helper_Functions::is_wwp_installed() && ! WWP_Helper_Functions::is_wwpp_active() )
                )
            ) {

                // Style from wc.
                wp_enqueue_style( 'woocommerce_admin_styles' );

                // Style from wp.
                wp_enqueue_style( 'wp_forms_css', site_url() . '/wp-admin/css/forms.css', array(), $this->_wwp_current_version, 'all' );

                wp_enqueue_style( 'wwp_wwp_upgrade_css', WWP_CSS_URL . 'wwp-upgrade.css', array(), $this->_wwp_current_version, 'all' );

            }

            /**
             * Wholesale Reports teaser landing page.
             *
             * Loaded only when WWPP is inactive — when WWPP is active, the
             * 'wholesale-reports' callback redirects to WWPP's own page so the
             * teaser screen never renders.
             */
            if ( 'wholesale_page_wholesale-reports' === $screen->id &&
                ! WWP_Helper_Functions::is_wwpp_active()
            ) {
                wp_enqueue_style(
                    'wwp_reports_teaser_css',
                    WWP_CSS_URL . 'wwp-reports-teaser.css',
                    array(),
                    $this->_wwp_current_version,
                    'all'
                );
                wp_enqueue_script(
                    'wwp_reports_teaser_js',
                    WWP_JS_URL . 'backend/wwp-reports-teaser.js',
                    array(),
                    $this->_wwp_current_version,
                    true
                );
            }

            /**
             * WWP Settings
             */
            if (
                ! WWP_Helper_Functions::is_wwpp_active() &&
                isset( $_GET['tab'] ) && 'wwp_settings' === sanitize_key( wp_unslash( $_GET['tab'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ) {

                // Queue up stuff that is used on all tabs.
                wp_enqueue_style( 'wwp-vex-css', WWP_JS_URL . 'lib/vexjs/vex.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_style( 'wwp-vex-theme-plain-css', WWP_JS_URL . 'lib/vexjs/vex-theme-plain.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_style( 'wwp-vex-styling-css', WWP_CSS_URL . 'wwp-vex-styling.css', array(), $this->_wwp_current_version, 'all' );
                wp_enqueue_script( 'wwp-vex-js', WWP_JS_URL . 'lib/vexjs/vex.combined.min.js', array(), $this->_wwp_current_version, true );

                // WWP Free Training Guide.
                wp_enqueue_style( 'wwp-free-training-guide-css', WWP_CSS_URL . 'backend/wwp-free-training-guide.css', array(), $this->_wwp_current_version, 'all' );

                // Handle each section of the settings (General, Price, Tax, Upgrade).
                if ( isset( $_GET['section'] ) && '' !== $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    switch ( sanitize_key( wp_unslash( $_GET['section'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        case 'wwpp_setting_price_section':
                            wp_enqueue_script( 'wwp-price-settings', WWP_JS_URL . 'backend/wwp-price-setting.js', array( 'select2' ), $this->_wwp_current_version, true );
                            wp_localize_script(
                                'wwp-price-settings',
                                'price_settings_args',
                                array(
									'images_url'        => WWP_IMAGES_URL,
									'use_regular_price' => array(
										'title' => '<h4>' . __( 'Define Prices By Percentage Globally Or On Categories', 'woocommerce-wholesale-prices' ) . '</h4>',
										'msg'   => '<p>' . __(
											'In WooCommerce Wholesale Prices Premium you can set your wholesale prices by a percentage on a category or site-wide general level.
                                            This can save heaps of time instead of setting wholesale pricing on individual products. Read more about it below.',
											'woocommerce-wholesale-prices'
                                        ) . '</p>',
										'link'  => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwppricesettingsalwaysuseregularwwpplink' ) ),
									),
									'variable_product_price_display' => array(
										'title' => '<h4>' . __( 'Change How Variable Product Prices Are Displayed', 'woocommerce-wholesale-prices' ) . '</h4>',
										'msg'   => '<p>' . __(
											'Changing how your variable product prices are displayed can reduce the amount of computational work WooCommerce does on load, making your site faster.
                            Access this optimization option and more in the WooCommerce Wholesale Prices Premium plugin.',
											'woocommerce-wholesale-prices'
                                        ) . '</p>',
										'link'  => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwppricesettingsvariabledisplaywwpplink' ) ),
									),
									'button_text'       => __( 'See Features & Pricing', 'woocommerce-wholesale-prices' ),
                                )
                            );

                            // Load related styles and scripts for non wholesale users.
                            $this->load_wwp_prices_settings_for_non_wholesale_users_styles_and_scripts();
                            break;

                        case 'wwpp_setting_tax_section':
                            wp_enqueue_style( 'wwp-tax-css', WWP_CSS_URL . 'wwp-tax-settings.css', array(), $this->_wwp_current_version, 'all' );
                            wp_enqueue_script( 'wwp-tax-settings', WWP_JS_URL . 'backend/wwp-tax-setting.js', array(), $this->_wwp_current_version, true );
                            wp_localize_script(
                                'wwp-tax-settings',
                                'tax_settings_args',
                                array(
									'images_url'       => WWP_IMAGES_URL,
									'tax_exemption'    => array(
										'title' => '<h4>' . __( 'Upgrade To Wholesale Suite For Tax Exemption', 'woocommerce-wholesale-prices' ) . '</h4>',
										'msg'   => sprintf(
                                            // translators: %1$s - <p> tag, %2$s - </p> tag.
                                            __(
                                                '%1$sWholesale Suite is the #1 best rated wholesale solution for WooCommerce.%2$s
                                                    %1$sPrices Premium (one of the three plugins) features in-depth tax exemption controls including being able to turn on/off tax exemption just for specific wholesale roles.%2$s',
                                                'woocommerce-wholesale-prices'
                                            ),
                                            '<p>',
                                            '</p>'
                                        ),
										'link'  => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwptaxexemptionpopupbutton' ) ),
									),
									'tax_display'      => array(
										'title' => '<h4>' . __( 'Upgrade To Wholesale Suite For Advanced Tax Display', 'woocommerce-wholesale-prices' ) . '</h4>',
										'msg'   => '<p>' . __( 'Wholesale Suite is the #1 best rated wholesale solution for WooCommerce. Prices Premium (one of the three plugins) features in-depth tax display controls.', 'woocommerce-wholesale-prices' ) . '</p>',
										'link'  => esc_url( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'wwptaxdisplaypopupbutton' ) ),
									),
									'suffix_overrides' => array(
										'title' => '<h4>' . __( 'Upgrade To Wholesale Suite For Suffix Overrides', 'woocommerce-wholesale-prices' ) . '</h4>',
										'msg'   => sprintf(
                                            // translators: %1$s - <p> tag, %2$s - </p> tag.
                                            __(
                                                '%1$sWholesale Suite is the #1 best rated wholesale solution for WooCommerce. Prices Premium (one of three plugins) features advanced price suffix controls.%2$s
                                                    %1$sThis can help in complex tax situations where prices suffixes should be different for wholesale customers.%2$s',
                                                'woocommerce-wholesale-prices'
                                            ),
                                            '<p>',
                                            '</p>'
                                        ),
										'link'  => esc_url( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'wwppricesuffixpopupbutton' ) ),
									),
									'button_text'      => __( 'See Features & Pricing', 'woocommerce-wholesale-prices' ),
                                )
                            );
                            break;

                        case 'wwp_upgrade_section':
                            wp_enqueue_style( 'wwp_wwp_upgrade_css', WWP_CSS_URL . 'wwp-upgrade.css', array(), $this->_wwp_current_version, 'all' );
                            break;
                        case 'wwp_license_section':
                            wp_enqueue_style( 'wwp-license-upgrade-css', WWP_CSS_URL . 'wwp-license-upgrade.css', array(), $this->_wwp_current_version, 'all' );
                            break;
                    }
                } else {
                    // General page.
                    wp_enqueue_style( 'wwp-general-css', WWP_CSS_URL . 'wwp-general-settings.css', array(), $this->_wwp_current_version, 'all' );
                }
            } elseif ( isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                switch ( sanitize_key( wp_unslash( $_GET['section'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    case 'wwpp_setting_price_section':
                        $this->load_wwp_prices_settings_for_non_wholesale_users_styles_and_scripts();
                        break;
                    default:
                        break;
                }
            }

            // enqueue script to replace icons in wc-admin marketing.
            if ( 'woocommerce_page_wc-admin' === $screen->id || 'edit-shop_coupon' === $screen->id ) {
                wp_enqueue_script( 'wwp-wc-admin-icons', WWP_JS_URL . 'backend/wwp-wc-admin-icons.js', array( 'jquery' ), $this->_wwp_current_version, true );
                wp_localize_script(
                    'wwp-wc-admin-icons',
                    'wwpAdminIcons',
                    array(
						'imgUrl' => WWP_IMAGES_URL,
                    )
                );
            }
        }

        /**
         * Load wwp prices settings for non wholesale users ralated styles and scripts
         *
         * @since 1.15.0
         * @access private
         */
        private function load_wwp_prices_settings_for_non_wholesale_users_styles_and_scripts() {
            // Check if WWLC is installed.
            $wwlc_plugin_data     = WWP_Helper_Functions::get_plugin_data( 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php' );
            $admin_notice_message = '';

            // Only display if WWLC is not installed.
            if ( empty( $wwlc_plugin_data ) ) {

                $admin_notice_message = '<a href="' . esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-lead-capture', 'wwp', 'upsell', 'wwlcshowtononwholesale' ) ) . '" target="_blank">' . __( 'Get WooCommerce Wholesale Lead Capture to unlock this powerful feature + more', 'woocommerce-wholesale-prices' ) . '&rarr;</a>';

            }

            // Toastr.
            wp_enqueue_style( 'wwp_toastr_css', WWP_JS_URL . 'lib/toastr/toastr.min.css', array(), $this->_wwp_current_version, 'all' );
            wp_enqueue_script( 'wwp_toastr_js', WWP_JS_URL . 'lib/toastr/toastr.min.js', array( 'jquery' ), $this->_wwp_current_version, true );

            // Price tab CSS.
            wp_enqueue_style( 'wwp-price-non-wholesale-css', WWP_CSS_URL . 'wwp-price-settings.css', array(), $this->_wwp_current_version, 'all' );

            // Load Custom JS script.
            wp_enqueue_script( 'wwp-price-settings-non-wholesale_js', WWP_JS_URL . 'backend/wwp-price-settings-non-wholesale.js', array( 'jquery', 'jquery-tiptip', 'select2' ), $this->_wwp_current_version, true );

            wp_localize_script(
                'wwp-price-settings-non-wholesale_js',
                'Options',
                array(
					'wwp_price_settings_register_text'    => get_option( 'wwp_price_settings_register_text' ),
					'wwp_price_settings_register_text_tooltip' => wc_help_tip( __( 'This text is linked to the defined registration page in WooCommerce Wholesale Lead Capture Settings.', 'woocommerce-wholesale-prices' ) ),
					'wwp_see_wholesale_prices_replacement_text' => get_option( 'wwp_see_wholesale_prices_replacement_text' ),
					'wwp_see_wholesale_prices_replacement_text_tooltip' => wc_help_tip( __( 'The \'Click to See Wholesale Prices\' text seen in frontend.', 'woocommerce-wholesale-prices' ) ),
					'wwp_wholesale_role_select_select2_tooltip' => wc_help_tip( __( 'The selected wholesale roles and pricing that should show to non-wholesale customers on the front end.', 'woocommerce-wholesale-prices' ) ),
					'wholesale_role_data_placeholder_txt' => __( 'Choose wholesale role...', 'woocommerce-wholesale-prices' ),
					'wholesale_role_options'              => get_option( 'wwp_non_wholesale_wholesale_role_select2' ),
					'wholesale_roles'                     => $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles(),
					'wwlc_admin_notice'                   => $admin_notice_message,
					'is_wwpp_active'                      => WWP_Helper_Functions::is_wwpp_active(),
					'is_wwlc_active'                      => WWP_Helper_Functions::is_wwlc_active(),
					'is_wwof_active'                      => WWP_Helper_Functions::is_wwof_active(),
					'show_in_shop'                        => apply_filters( 'wwp_scripts_non_wholesale_show_in_shop', get_option( 'wwp_non_wholesale_show_in_shop' ) ),
					'show_in_products'                    => apply_filters( 'wwp_scripts_non_wholesale_show_in_products', get_option( 'wwp_non_wholesale_show_in_products' ) ),
					'show_in_wwof'                        => get_option( 'wwp_non_wholesale_show_in_wwof' ),
					'base_url'                            => get_site_url(),

					/**
                     * Show Wholesale Prices to Non Wholesale Customers
                     */
					'i18n_show_wholesale_price_settings_title' => __( 'Show Wholesale Prices Box For Non Wholesale Customers', 'woocommerce-wholesale-prices' ),
					'i18n_locations_title'                => __( 'Locations', 'woocommerce-wholesale-prices' ),
					'i18n_click_to_see_wholesale_price_title' => __( 'Click to See Wholesale Prices Text', 'woocommerce-wholesale-prices' ),
					'i18n_wholesale_role_title'           => __( 'Wholesale Roles(s)', 'woocommerce-wholesale-prices' ),
					'i18n_register_title'                 => __( 'Register Text', 'woocommerce-wholesale-prices' ),
					'i18n_locations_shop'                 => __( 'Shop Archives', 'woocommerce-wholesale-prices' ),
					'i18n_locations_single_product'       => __( 'Single Product', 'woocommerce-wholesale-prices' ),
					'i18n_locations_order_form'           => __( 'Wholesale Order Form', 'woocommerce-wholesale-prices' ),
					'i18n_bonus_text'                     => sprintf(
                        // translators: %1$s - <p><span style="font-weight: bold;">Bonus:</span>, %2$s <span style="color: #218900; font-weight: bold;">, %3$s </span></p><p>, %4$s </p><p>.
                        __(
                            '%1$sWholesale Prices lite users get %2$s50&#37; off regular price%3$s automatically applied at checkout.%4$s',
                            'woocommerce-wholesale-prices'
                        ),
                        '<p><span style="font-weight: bold;">Bonus:</span>',
                        '<span style="color: #218900; font-weight: bold;">',
                        '</span>',
                        '</p>'
                    ),
					'i18n_register_text_notice'           => sprintf(
                        // translators: %1$s - <div class="notice wwp-wwlc-inactive"><p><i class="fa fa-star checked"></i><strong>, %2$s <a href="https://wholesalesuiteplugin.com/woocommerce-wholesale-lead-capture/?utm_source=wwp&utm_medium=upsell&utm_campaign=wholesaletoguestwwlcrecommend" target="_blank">, %3$s </a></strong></p><p>, %4$s </p><p>.
                        __(
                            '%1$sRecommended Plugin: %2$sWooCommerce Wholesale Lead Capture%3$sLead Capture adds an additional \'register text\' link to the wholesale prices box on the front end to help you capture even more wholesale leads.%4$s',
                            'woocommerce-wholesale-prices'
                        ),
                        '<div class="notice wwp-wwlc-inactive"><p><i class="fa fa-star checked"></i><strong>',
                        sprintf(
                            '<a href="%s" target="_blank">',
                            esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-lead-capture', 'wwp', 'upsell', 'wholesaletoguestwwlcrecommend' ) )
                        ),
                        '</a></strong></p><p>',
                        '</p><p>'
                    ),
					'i18n_wwof_inactive_notice'           => '<small>' . sprintf(
                        // translators: %1$s - <strong>, %2$s </strong>.
                        __( 'To use this option, you must have %1$sWooCommerce Wholesale Order Form%2$s plugin installed and activated.', 'woocommerce-wholesale-prices' ),
                        '<strong>',
                        '</strong>'
                    ) . '</small>',
                )
            );
        }

        /**
         * Load frontend related styles and scripts.
         * Only load em on the right time and on the right place.
         *
         * @since 1.0.0
         * @since 1.4.0 Refactor codebase and move to its own model.
         * @since 1.15.3 Load scripts for showing wholesale prices to non wholesale customers
         * @access public
         */
        public function load_front_end_styles_and_scripts() {
            global $post;

            $show_wholesale_prices_to_non_wholesale = apply_filters( 'wwp_show_wholesale_prices_to_non_wholesale_customers', get_option( 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale' ), $post );

            if ( is_product() ) {

                /**
                 * This is about the issue where if variable product has variation with all having the same price.
                 * Wholesale price for a selected variation won't show on the single variable product page.
                 *
                 * This issue is already fixed in wwpp. Now if wwpp is installed and active, let wwpp fix this issue.
                 * Only fix this issue here in wwp if wwpp is not present.
                 *
                 * Note the fix on WWPP is different from the fix here coz WWPP has additional features to consider compared to WWP.
                 */
                if ( ! WWP_Helper_Functions::is_wwpp_active() ) {

                    wp_enqueue_style( 'wwp_single_product_page_css', WWP_CSS_URL . 'frontend/product/wwp-single-product-page.css', array(), $this->_wwp_current_version, 'all' );

                    if ( 'product' === $post->post_type ) {

                        $product = wc_get_product( $post->ID );

                        if ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variable' ) {

                            $userWholesaleRole = $this->_wwp_wholesale_roles->getUserWholesaleRole();
                            $variationsArr     = array();

                            if ( ! empty( $userWholesaleRole ) ) {

                                $variations = WWP_Helper_Functions::wwp_get_variable_product_variations( $product );

                                foreach ( $variations as $variation ) {

                                    $variationProduct = wc_get_product( $variation['variation_id'] );

                                    $currVarPrice    = $variation['display_price'];
                                    $price_arr       = $this->_wwp_wholesale_prices->get_product_wholesale_price_on_shop_v3( $variation['variation_id'], $userWholesaleRole );
                                    $wholesalePrice  = $price_arr['wholesale_price'];
                                    $variationsArr[] = array(
                                        'variation_id' => $variation['variation_id'],
                                        'raw_regular_price' => (float) $currVarPrice,
                                        'raw_wholesale_price' => (float) $wholesalePrice,
                                        'has_wholesale_price' => is_numeric( $wholesalePrice ),
                                    );
                                }

                                /**
                                 * #WWP-51
                                 * Check if variable product has same regular price and same wholesale price
                                 * If true then don't load the script below
                                 */
                                $same_reg_price       = true;
                                $temp_reg_price       = null;
                                $same_wholesale_price = true;
                                $temp_wholesale_price = null;

                                foreach ( $variationsArr as $varData ) {

                                    if ( is_null( $temp_reg_price ) ) {
                                        $temp_reg_price = $varData['raw_regular_price'];
                                    } elseif ( $same_reg_price ) {
                                        $same_reg_price = ( $temp_reg_price === $varData['raw_regular_price'] );
                                    }

                                    if ( is_null( $temp_wholesale_price ) ) {
                                        $temp_wholesale_price = $varData['raw_wholesale_price'];
                                    } elseif ( $same_wholesale_price ) {
                                        $same_wholesale_price = ( $temp_wholesale_price === $varData['raw_wholesale_price'] );
                                    }
                                }

                                $same_prices = $same_reg_price && $same_wholesale_price;

                                if ( ! $same_prices ) {
                                    add_filter(
                                        'woocommerce_show_variation_price',
                                        function () {
                                        return true;
                                        }
                                    );
                                }
                            }
                        }
                    }
                }
            }

            // Show wholesale prices to non-wholesale users feature.
            $show_in_products = apply_filters(
                'wwp_non_wholesale_show_in_products',
                get_option( 'wwp_non_wholesale_show_in_products' ),
                $post
            );
            $show_in_shop     = apply_filters(
                'wwp_non_wholesale_show_in_shop',
                get_option( 'wwp_non_wholesale_show_in_shop' ),
                $post
            );

            if (
                apply_filters( 'wwp_load_show_wholesale_to_non_wholesale', false, 'scripts' ) ||
                (
                    'yes' === $show_wholesale_prices_to_non_wholesale && (
                        ( is_product() && 'yes' === $show_in_products ) ||
                        ( ( is_shop() || is_product_category() ) && 'yes' === $show_in_shop )
                    )
                )
            ) {
                $userWholesaleRole = $this->_wwp_wholesale_roles->getUserWholesaleRole();

                if ( empty( $userWholesaleRole ) ) {
                    // Load CSS.
                    wp_enqueue_style( 'wwp-tippy-light-border-css', WWP_JS_URL . 'lib/tippy/tippy-light-border.css', array(), $this->_wwp_current_version, 'all' );
                    wp_enqueue_style( 'wwp-price-non-wholesale-css', WWP_CSS_URL . 'frontend/product/wwp-show-wholesale-prices-to-non-wholesale-users.css', array(), $this->_wwp_current_version, 'all' );

                    // Load JS.
                    wp_enqueue_script( 'wwp-popper-js', WWP_JS_URL . 'lib/tippy/popper.min.js', array( 'jquery' ), $this->_wwp_current_version, true );
                    wp_enqueue_script( 'wwp-tippy-js', WWP_JS_URL . 'lib/tippy/tippy.min.js', array( 'jquery', 'wwp-popper-js' ), $this->_wwp_current_version, true );
                    wp_enqueue_script( 'wwp-prices-non-wholesale_js', WWP_JS_URL . 'app/wwp-prices-non-wholesales.js', array( 'jquery' ), $this->_wwp_current_version, true );

                    // Load Localize script.
                    wp_localize_script(
                        'wwp-prices-non-wholesale_js',
                        'options',
                        array(
                            'popover_header_title' => apply_filters( 'wwp_popover_header_title', __( 'Wholesale Prices', 'woocommerce-wholesale-prices' ) ),
                            'nonce'                => wp_create_nonce( 'wwp_nonce' ),
                            'ajaxurl'              => admin_url( 'admin-ajax.php' ),
                        )
                    );
                }
            }
        }

        /**
         * Upsell message shown as popup in wholesale roles page.
         *
         * @since 1.11
         * @access public
         */
        private function role_page_upsell_message() {
            ob_start();
            ?>

            <div class="upsell-area">
                <h2><?php esc_html_e( 'Additional Wholesale Roles( Premium )', 'woocommerce - wholesale - prices' ); ?></h2>
                <p>
                <?php
                esc_html_e(
                    'You\'re currently using the free version of WooCommerce Wholesale Prices which lets you have one level of wholesale customers.',
                    'woocommerce-wholesale-prices'
                );
?>
</p>
                <p>
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            // translators: %1$s and %2$s are html tags.
                            __(
                                'In the %1$sPremium add-on%2$s you can add multiple wholesale roles. This will let you create separate "levels" of wholesale customers,
                                    each of which can have separate wholesale pricing, shipping and payment mapping, order minimums and more.',
                                'woocommerce-wholesale-prices'
                            ),
                            sprintf(
                                '<a href="%s" target="_blank">',
                                esc_url(
                                    WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwprolespagelinkpopup' )
                                ),
                            ),
                            '</a>'
                        )
                    );
                    ?>
                </p>
                <p>
                    <a class="button"
                        href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwprolespagebuttonpopup' ) ); ?>"
                        target="_blank">
                        <?php esc_html_e( 'See the full feature list', 'woocommerce-wholesale-prices' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 7px"></span>
                    </a>
                    <img class="fivestar" src="<?php echo esc_url( WWP_IMAGES_URL ); ?>/5star.png" />
                </p>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Here we load our scripts to be use by Store Management Quick links
         *
         * @since 2.1.2
         */
        public function wwp_store_management_link_script() {
            // We check if all plugins are activated, so we can hide Upgrade Wholesale Suite link in the WooCommerce > Home > Store Management Quick links.
            $has_all_premiums = 'false';
            if ( WWP_Helper_Functions::is_wwpp_active() && WWP_Helper_Functions::is_wwlc_active() && WWP_Helper_Functions::is_wwof_active() ) {
                $has_all_premiums = 'true';
            }

            wp_enqueue_script( 'wwp-store-management-quick-link-js', WWP_JS_URL . 'app/wc-store-management-quick-links/build/static/js/wwp-store-management-quick-links.js', array( 'wp-hooks' ), $this->_wwp_current_version, false );

            wp_localize_script(
                'wwp-store-management-quick-link-js',
                'wwp_store_management_quick_link',
                array(
					'has_all_premiums' => $has_all_premiums,
                )
            );
        }

        /**
         * This is use in the Store Management Quick links under WooCommerce > Home, in the Upgrade Wholesale Suite button under EXTENSIONS category, for the link, currently WooCommerce Store Management Quick links, does not accept "External Links", if you directly input the external links it will hide the "Upgrade Wholesale Suite" menu inside Store Management.
         *
         * @since 2.1.2
         */
        public function wchome_wws_upgrade_to_premium() {
            if ( isset( $_GET['page'] ) && 'wchome-wws-upgrade' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_safe_redirect( esc_url_raw( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'wchomeupgradelink' ) ) );
                exit;
            }
        }

        /**
         * Load scripts and style for showing wholesale price lite notice bar
         *
         * @since 2.1.2
         * @access public
         */
        public function load_wwp_admin_notice_bar_lite_styles_and_scripts() {
            if ( ! WWP_Notice_Bar::has_wws_premiums() ) {
                wp_enqueue_style( 'wwp-notice-bar-lite-css', WWP_CSS_URL . 'wwp-admin-notice-bar.css', array(), $this->_wwp_current_version, 'all' );
            }
        }

        /**
         * Load scripts and style for license upsell upgrade to premium
         *
         * @since 2.1.3
         * @access public
         */
        public function load_wws_license_upsell_upgrade_to_premium_styles_and_scripts() {
            if ( isset( $_GET['page'] ) && 'wws-license-settings' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_enqueue_style( 'wws-wwp-license-upsell-upgrade-css', WWP_CSS_URL . 'backend/wwp-license-upsell-upgrade.css', array(), $this->_wwp_current_version, 'all' );
            }
        }

        /**
         * Load scripts and style for wwp pointer
         *
         * @since 2.1.3
         * @access public
         */
        public function load_wwp_pointer() {
            $wwp_activation_date     = get_option( 'wwp_activation_date' );
            $wwp_activation_datetime = $wwp_activation_date ? strtotime( $wwp_activation_date ) : false;
            $now                     = time();

            // Get date difference in days.
            $date_diff = $wwp_activation_datetime ? ( $now - $wwp_activation_datetime ) / DAY_IN_SECONDS : false;

            if ( $date_diff < 21 || WWP_Helper_Functions::is_user_wws_notification_dismissed( get_current_user_id(), 'wpay-menu-bar-button' ) ) {
				return false;
			}

            return true;
        }

        /**
         * Check whether the current admin screen is a wholesale-related screen.
         *
         * Gates admin-bar menu injection and WPay pointer enqueueing so they
         * only fire on screens whose id contains "wholesale".
         *
         * @since 2.2.8
         *
         * @return bool True when on a wholesale admin screen, false otherwise.
         */
        private function is_wholesale_admin_screen() {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

            return $screen && str_contains( $screen->id, 'wholesale' );
        }

        /**
         * Add WWP menu in admin bar
         *
         * @since 2.2.1
         * @since 2.2.8 Extracted screen-id check into is_wholesale_admin_screen() helper.
         * @access public
         *
         * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
         */
        public function add_wwp_menu_in_admin_bar( WP_Admin_Bar $wp_admin_bar ) {
            if ( ! is_admin() || ! $this->load_wwp_pointer() ) {
				return;
			}

            if ( $this->is_wholesale_admin_screen() && ! WWP_Helper_Functions::is_wpay_active() ) {
                $default_roles = array( 'administrator' );
                $saved_roles   = (array) get_option( 'wwp_roles_allowed_dashboard_access', array() );

                // Shop manager has access if not disallowed by the admin.
                if ( empty( $saved_roles ) ) {
                    $default_roles[] = 'shop_manager';
                }

                /**
                 * Filter to allow other roles to access the wholesale dashboard.
                 * By default, only admin and shop manager can see the top level menu.
                 *
                 * @since 2.2.1
                 *
                 * @param array $allowed_roles Array of roles allowed to access the wholesale dashboard.
                 * @param array $default_roles Default roles allowed to access the wholesale dashboard.
                 * @return array
                 */
                $allowed_roles = apply_filters(
                    'wwp_roles_allowed_dashboard_access',
                    array_merge( $default_roles, $saved_roles ),
                );

                $user = wp_get_current_user();

                if ( ! empty( array_intersect( (array) $user->roles, $allowed_roles ) ) ) {
                    $wws_image_url      = WWP_IMAGES_URL . 'wws-icon.png';
                    $wpay_toolbar_label = sprintf(
                        '<span id="wpay_toolbar-container"><img src="%s" alt="%s" style="width: 20px; height: 20px; margin-right: 5px;"><span class="wpay_toolbar-label">%s</span></span>',
                        esc_url( $wws_image_url ),
                        esc_attr__( 'Wholesale Payments', 'woocommerce-wholesale-prices' ),
                        esc_attr__( 'Wholesale Payments', 'woocommerce-wholesale-prices' ),
                    );
                    $wp_admin_bar->add_node(
                        array(
                            'id'    => 'wpay_toolbar',
                            'title' => $wpay_toolbar_label,
                        )
                    );
                }
            }
        }

        /**
         * Enqueue wpay pointer scripts and styles.
         *
         * @since 2.1.3
         * @since 2.2.8 Enqueue external JS file with localized nonce and gate by wholesale admin screens.
         *
         * @access public
         */
        public function enqueue_wpay_pointer() {
            if ( ! $this->is_wholesale_admin_screen() ) {
                return;
            }

            if ( WWP_Helper_Functions::is_wpay_active() || ! $this->load_wwp_pointer() ) {
                return;
            }

            wp_enqueue_script( 'wp-pointer' );
            wp_enqueue_style( 'wp-pointer' );

            wp_enqueue_style( 'wwp_admin_pointer_css', WWP_CSS_URL . 'wwp-admin-pointer.css', array(), $this->_wwp_current_version, 'all' );

            wp_enqueue_script( 'wwp_wpay_pointer_js', WWP_JS_URL . 'backend/wwp-wpay-pointer.js', array( 'jquery', 'wp-pointer', 'wp-i18n' ), $this->_wwp_current_version, true );
            wp_localize_script(
                'wwp_wpay_pointer_js',
                'wwp_wpay_pointer_params',
                array(
                    'nonce'            => wp_create_nonce( 'wp_wpay_toolbar_dismiss_notice' ),
                    'title'            => esc_html__( 'Setup Wholesale Payments (Recommended)', 'woocommerce-wholesale-prices' ),
                    'description'      => esc_html__( 'Use Wholesale Suite Wholesale Payments plugin to create NET 30, 60, or any other invoice payment plan you can think of for your wholesale customers.', 'woocommerce-wholesale-prices' ),
                    'features'         => array(
                        esc_html__( 'Turn WooCommerce orders into Stripe Invoices with NET payment terms', 'woocommerce-wholesale-prices' ),
                        esc_html__( 'Add a new "Wholesale Invoice" gateway', 'woocommerce-wholesale-prices' ),
                        esc_html__( 'Restrict to certain wholesale roles or even certain customers only', 'woocommerce-wholesale-prices' ),
                        esc_html__( 'Create any kind of delayed payment plan (eg. NET 30, NET 60, % deposits, ...)', 'woocommerce-wholesale-prices' ),
                        esc_html__( 'Expert worldwide support', 'woocommerce-wholesale-prices' ),
                    ),
                    'learn_more_url'   => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-payments', 'wwp', 'upsell', 'upgradepagewpaylearnmore' ) ),
                    'learn_more_label' => esc_html__( 'Get Wholesale Payments', 'woocommerce-wholesale-prices' ),
                )
            );
        }

        /**
         * Save a dismissed notification key to user meta.
         *
         * Appends the given key to the user's `_wws_notifications_close` meta array
         * so the notification does not reappear.
         *
         * @since 2.2.8
         * @access private
         *
         * @param int    $user_id    The user ID.
         * @param string $notice_key The notification key to dismiss.
         */
        private function save_dismissed_notification( $user_id, $notice_key ) {
            $userdata   = get_user_meta( $user_id, '_wws_notifications_close', true );
            $userdata   = empty( $userdata ) || ! is_array( $userdata ) ? array() : $userdata;
            $userdata[] = $notice_key;

            update_user_meta( $user_id, '_wws_notifications_close', array_values( array_unique( $userdata ) ) );
        }

        /**
         * Dismiss admin notice via AJAX.
         *
         * Saves the notification key to user meta so the notice does not reappear.
         *
         * @since 2.1.3
         * @since 2.2.8 Converted to proper AJAX JSON response instead of redirect. Added capability check and allowlisted keys.
         *
         * @access public
         */
        public function ajax_dismiss_admin_notice() {
            if ( ! WWP_Helper_Functions::verify_ajax_nonce( 'wp_wpay_toolbar_dismiss_notice' ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'woocommerce-wholesale-prices' ) ) );
                return;
            }

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'woocommerce-wholesale-prices' ) ) );
                return;
            }

            $allowed_keys = array( 'wpay-menu-bar-button', 'wpay-wc-settings-banner' );
            $notice_key   = isset( $_POST['nkey'] ) ? sanitize_text_field( wp_unslash( $_POST['nkey'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax_nonce() above.
            if ( ! in_array( $notice_key, $allowed_keys, true ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid notice key.', 'woocommerce-wholesale-prices' ) ) );
                return;
            }

            $this->save_dismissed_notification( get_current_user_id(), $notice_key );

            wp_send_json_success();
        }

        /**
         * Activate plugin.
         *
         * @since 2.2.1
         * @access public
         */
        public function wwp_activate() {
            update_option( 'wwp_activation_date', current_time( 'mysql' ) );
        }

        /**
         * Execute model.
         *
         * @since 1.4.0
         * @access public
         */
        public function run() {
            add_action( 'wp_loaded', array( $this, 'wchome_wws_upgrade_to_premium' ), 10 );
            add_action( 'admin_enqueue_scripts', array( $this, 'load_back_end_styles_and_scripts' ), 10, 1 );
            add_action( 'wp_enqueue_scripts', array( $this, 'load_front_end_styles_and_scripts' ), 11 );
            add_action( 'admin_enqueue_scripts', array( $this, 'wwp_store_management_link_script' ), 10 );
            add_action( 'admin_enqueue_scripts', array( $this, 'load_wwp_admin_notice_bar_lite_styles_and_scripts' ), 10 );
            add_action( 'admin_enqueue_scripts', array( $this, 'load_wws_license_upsell_upgrade_to_premium_styles_and_scripts' ), 10 );
            add_action( 'admin_bar_menu', array( $this, 'add_wwp_menu_in_admin_bar' ), 99 );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wpay_pointer' ), 10 );
            add_action( 'wp_ajax_wpay_toolbar_dismiss_notice', array( $this, 'ajax_dismiss_admin_notice' ) );

            register_activation_hook( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices' . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices.bootstrap.php', array( $this, 'wwp_activate' ) );
        }
    }
}
