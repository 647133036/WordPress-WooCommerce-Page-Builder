<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Settings' ) ) {

    /**
     * Model that houses the logic of WooCommerce Wholesale Prices Settings page.
     *
     * @since 1.0.0
     * @since 1.11 We are adding settings by transferring setting options from WWPP to WWP.
     *             These options include "Wholesale Price Text", "Disable coupons for wholesale users" and "Hide Original Price".
     *             Note that these options we are still using the wwpp_ prefix to maintain values across WWP and WWPP.
     * @since 1.24 The setting options will be removed in WWPP and its logic codes.
     *             WWP will handle the transferred options in this version.
     */
    class WWP_Settings extends WC_Settings_Page {

        /**
         * WWP_Settings constructor.
         *
         * @access public
         * @since 1.0.0
         */
        public function __construct() {
            $this->id    = 'wwp_settings';
            $this->label = __( 'Wholesale Prices', 'woocommerce-wholesale-prices' );

            add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 30 ); // 30 so it is after the emails tab
            add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
            add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
            add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );

            add_action( 'woocommerce_admin_field_upgrade_content', array( $this, 'render_upgrade_content' ) );

            // Remove upgrade tab when WWPP is active.
            add_filter( 'wwp_filter_settings_sections', array( $this, 'remove_upgrade_tab' ) );
            add_filter( 'wwp_filter_settings_sections', array( $this, 'remove_help_tab' ) );

            // Remove dummy settings to avoid duplication when WWPP is active.
            add_filter( 'wwp_general_section_settings', array( $this, 'remove_dummy_settings_when_wwpp_active' ) );
            add_filter( 'wwp_price_section_settings', array( $this, 'remove_dummy_settings_when_wwpp_active' ) );
            add_filter( 'wwp_tax_section_settings', array( $this, 'remove_dummy_settings_when_wwpp_active' ) );
            add_filter( 'wwp_help_section_settings', array( $this, 'remove_dummy_settings_when_wwpp_active' ) );

            add_action( 'woocommerce_admin_field_wwp_upsells_buttons', array( $this, 'wwp_upsells_buttons' ) );

            add_action( 'woocommerce_admin_field_wwp_editor', array( $this, 'render_plugin_settings_custom_field_wwp_editor' ), 10, 1 );

            // Help Tab.
            add_action( 'woocommerce_admin_field_help_resources_controls', array( $this, 'render_plugin_settings_custom_field_help_resources_controls' ), 10 );

            // Email Capture Box.
            add_action( 'woocommerce_admin_field_wwp_free_training_guide', array( $this, 'render_plugin_settings_free_training_guide' ), 10 );

            // Dummy License Tab.
            add_action( 'woocommerce_admin_field_license_upgrade_content', array( $this, 'render_license_upgrade_content' ), 10 );

            // Move license section after upgrade section if WWPP is active.
            add_filter( 'woocommerce_get_sections_' . $this->id, array( $this, 'move_wwp_license_section' ), 10, 1 );

            do_action( 'wwp_settings_construct' );
        }

        /**
         * Get sections.
         *
         * @return array
         * @since 2.1.3 - Added Dummy License section
         * @since 1.0.0
         */
        public function get_sections() {
            $sections = array(
                ''                           => apply_filters( 'wwp_filter_settings_general_section_title', __( 'General', 'woocommerce-wholesale-prices' ) ),
                'wwpp_setting_price_section' => __( 'Price', 'woocommerce-wholesale-prices' ),
                'wwpp_setting_tax_section'   => __( 'Tax', 'woocommerce-wholesale-prices' ),
                'wwp_help_section'           => __( 'Help', 'woocommerce-wholesale-prices' ),
                'wwp_upgrade_section'        => __( 'Upgrade', 'woocommerce-wholesale-prices' ),
                'wwp_license_section'        => __( 'License', 'woocommerce-wholesale-prices' ),
            );

            $sections = apply_filters( 'wwp_filter_settings_sections', $sections );

            return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
        }

        /**
         * Output the settings.
         *
         * @since 1.0.0
         */
        public function output() {
            global $current_section;

            $settings = $this->get_settings( $current_section );
            WC_Admin_Settings::output_fields( $settings );
        }

        /**
         * Save settings.
         *
         * @since 1.0.0
         * @since 1.6.0 Passed the current section on the wwp_before_save_settings and wwp_after_save_settings action filters.
         */
        public function save() {
            global $current_section;

            $settings = array_merge( $this->get_settings( $current_section ), $this->sub_fields( $current_section ) );

            if ( isset( $_POST['wwp_editor'] ) && is_array( $_POST['wwp_editor'] ) && ! empty( $_POST['wwp_editor'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $wwp_editor = wp_unslash( $_POST['wwp_editor'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below per-field.
                foreach ( $wwp_editor as $index => $content ) {
                    $index           = sanitize_key( $index );
                    $_POST[ $index ] = htmlentities( wpautop( wp_kses_post( $content ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                }
            }

            do_action( 'wwp_before_save_settings', $settings, $current_section );

            WC_Admin_Settings::save_fields( $settings );

            do_action( 'wwp_after_save_settings', $settings, $current_section );
        }

        /**
         * Get subfields settings array, this can be useful if you have sub fields in your settings fields,
         * just add based on your $current_section so we can save values on subfields,
         * merge this with settings on save() function.
         *
         * @since 1.15.0
         *
         * @param string $current_section Current section.
         * @return $settings array, merge with settings of subfields
         */
        public function sub_fields( $current_section = '' ) {
            $settings = array();

            if ( 'wwpp_setting_price_section' === $current_section ) {

                // For showing wholesale price to non wholesale users sub fields.
                $wwp_non_wholesale_settings = array(
                    array(
                        'type' => 'text',
                        'id'   => 'wwp_see_wholesale_prices_replacement_text',
                    ),

                    array(
                        'type' => 'multiselect',
                        'id'   => 'wwp_non_wholesale_wholesale_role_select2',
                    ),

                    array(
                        'type' => 'text',
                        'id'   => 'wwp_price_settings_register_text',
                    ),

                    array(
                        'type' => 'checkbox',
                        'id'   => 'wwp_non_wholesale_show_in_shop',
                    ),

                    array(
                        'type' => 'checkbox',
                        'id'   => 'wwp_non_wholesale_show_in_products',
                    ),

                    array(
                        'type' => 'checkbox',
                        'id'   => 'wwp_non_wholesale_show_in_wwof',
                    ),

                );

                $settings = array_merge( $settings, $wwp_non_wholesale_settings );
            }

            return $settings;
        }

        /**
         * Get settings array.
         *
         * @param string $current_section Current section.
         *
         * @return mixed
         * @since 1.0.0
         */
        public function get_settings( $current_section = '' ) {
            $settings = array();

            if ( '' === $current_section ) { // General Settings.

                $wwpGeneralSettings = apply_filters( 'wwp_general_section_settings', $this->_get_general_section_settings() );
                $settings           = array_merge( $settings, $wwpGeneralSettings );

            } elseif ( 'wwpp_setting_price_section' === $current_section ) { // Price Section.

                $wwp_price_settings = apply_filters( 'wwp_price_section_settings', $this->_get_price_section_settings() );
                $settings           = array_merge( $settings, $wwp_price_settings );

            } elseif ( 'wwpp_setting_tax_section' === $current_section && ! WWP_Helper_Functions::is_wwpp_active() ) { // Tax Section.

                $wwp_tax_settings = apply_filters( 'wwp_tax_section_settings', $this->_get_tax_section_settings() );
                $settings         = array_merge( $settings, $wwp_tax_settings );

            } elseif ( 'wwp_upgrade_section' === $current_section ) { // Upgrade Section.

                $wwp_upgrade_settings = apply_filters( 'wwp_upgrade_section_settings', $this->_get_upgrade_section_settings() );
                $settings             = array_merge( $settings, $wwp_upgrade_settings );

            } elseif ( 'wwp_help_section' === $current_section ) { // Help Section.

                $wwp_help_settings = apply_filters( 'wwp_help_section_settings', $this->_get_help_section_settings() );
                $settings          = array_merge( $settings, $wwp_help_settings );

            } elseif ( 'wwp_license_section' === $current_section && ! WWP_Helper_Functions::is_wwpp_active() ) { // License Section.

                $wwp_license_section = apply_filters( 'wwp_license_section', $this->_get_license_section_settings() );
                $settings            = array_merge( $settings, $wwp_license_section );

            }

            $settings = apply_filters( 'wwp_settings_section_content', $settings, $current_section );

            return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
        }

        /**
         * General Setting.
         * This setting comes from WWPP. We maintain the prefix wwpp_ to avoid any with duplicate setting value.
         *
         * @since 1.11
         * @access public
         */
        private function _get_general_section_settings() {
            $general_settings = array(

                array(
                    'name' => __( 'Wholesale Prices Settings', 'woocommerce-wholesale-prices' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwp_general_settings_section_title',
                ),
                array(
                    'name'     => __( 'Allow Usage Tracking', 'woocommerce-wholesale-prices' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'By allowing us to track usage data we can better help you because we know with which WordPress configurations, themes and plugins we should test.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => sprintf(
                        // translators: %1$s - <a> tag, %2$s - </a> tag.
                        __( 'Complete documentation on usage tracking is available %1$shere%2$s.', 'woocommerce-wholesale-prices' ),
                        sprintf(
                            '<a href="%s" target="_blank">',
                            esc_url( WWP_Helper_Functions::get_utm_url( 'kb/usage-tracking', 'wwp', 'kb', 'helppageusagetracking' ) )
                        ),
                        '</a>'
                    ),
                    'id'       => 'wwp_anonymous_data',
                    'class'    => 'wwp_anonymous_data',
                ),
                array(
                    'name'     => __( 'Disable Coupons For Wholesale Users', 'woocommerce-wholesale-prices' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Globally turn off coupons functionality for customers with a wholesale user role.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => __( 'This applies to all customers with a wholesale role.', 'woocommerce-wholesale-prices' ),
                    'id'       => 'wwpp_settings_disable_coupons_for_wholesale_users',
                    'class'    => 'wwpp_settings_disable_coupons_for_wholesale_users',
                ),
                array(
                    'type'  => 'sectionend',
                    'class' => 'wwp_general_settings_sectionend',
                ),
                array(
                    'name'  => '',
                    'type'  => 'title',
                    'desc'  => $this->_get_wwp_upgrade_box_desc(),
                    'id'    => 'wwp_general_settings_bottom_upgrade_message',
                    'class' => 'wwp_upgrade_box',
                ),
                array(
                    'type'  => 'sectionend',
                    'class' => 'wwp_general_settings_sectionend',
                ),
                array(
                    'name'  => '',
                    'type'  => 'wwp_free_training_guide',
                    'desc'  => '',
                    'class' => 'wwp_email_capture_box',
                    'id'    => 'wwp_general_settings_section_title_free_training',

                ),
                array(
                    'type'  => 'sectionend',
                    'class' => 'wwp_general_settings_sectionend',
                ),
            );

            if ( get_option( 'wwp_anonymous_data' ) === 'yes' ) {
                foreach ( $general_settings as $key => $setting ) {
                    if ( 'wwp_anonymous_data' === $setting['id'] ) {
                        unset( $general_settings[ $key ] );
                        break;
                    }
                }
            }

            if ( WWP_Notice_Bar::has_wws_premiums() ) {
                foreach ( $general_settings as $key => $setting ) {
                    if ( isset( $setting['id'] ) && 'wwp_general_settings_section_title_free_training' === $setting['id'] ) {
                        unset( $general_settings[ $key ] );
                        break;
                    }
                }
            }

            return $general_settings;
        }

        /**
         * Price settings section options. This setting comes from WWPP. We maintain the prefix wwpp_ to avoid any with duplicate setting value.
         *
         * @since 1.11
         * @access public
         *
         * @return array
         */
        private function _get_price_section_settings() {
            return array(

                array(
                    'name' => __( 'Price Options', 'woocommerce-wholesale-prices' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwp_settings_price_section_title',
                ),

                array(
                    'name'  => __( 'Wholesale Price Text', 'woocommerce-wholesale-prices' ),
                    'type'  => 'text',
                    'desc'  => __( 'The text shown immediately before the wholesale price. Default is "Wholesale Price: "', 'woocommerce-wholesale-prices' ),
                    'id'    => 'wwpp_settings_wholesale_price_title_text',
                    'class' => 'wwpp_settings_wholesale_price_title_text',
                ),

                array(
                    'name'     => __( 'Hide Retail Price', 'woocommerce-wholesale-prices' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Hide retail price instead of showing a crossed out price if a wholesale price is present.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => '',
                    'id'       => 'wwpp_settings_hide_original_price',
                    'class'    => 'wwpp_settings_hide_original_price',
                ),

                array(
                    'name'  => __( 'Always Use Regular Price', 'woocommerce-wholesale-prices' ),
                    'type'  => 'checkbox',
                    'desc'  => __( 'When calculating the wholesale price by using a percentage (global discount % or category based %) always ensure the Regular Price is used and ignore the Sale Price if present.', 'woocommerce-wholesale-prices' ),
                    'id'    => 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc_dummy',
                    'class' => 'wwp_settings_explicitly_use_product_regular_price_on_discount_calc_dummy',
                ),

                array(
                    'name'     => __( 'Variable Product Price Display', 'woocommerce-wholesale-prices' ),
                    'type'     => 'select',
                    'desc'     => __( 'Specify the format in which variable product prices are displayed. Only for wholesale customers.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => true,
                    'id'       => 'wwpp_settings_variable_product_price_display_dummy',
                    'class'    => 'wwp_settings_variable_product_price_display_dummy',
                    'options'  => array(
                        'price-range' => __( 'Price Range', 'woocommerce-wholesale-prices' ),
                        'minimum'     => __( 'Minimum Price (Premium)', 'woocommerce-wholesale-prices' ),
                        'maximum'     => __( 'Maximum Price (Premium)', 'woocommerce-wholesale-prices' ),
                    ),
                ),

                array(
                    'name'  => __( 'Hide Wholesale Price on Admin Product Listing', 'woocommerce-wholesale-prices' ),
                    'type'  => 'checkbox',
                    'desc'  => __( 'If checked, hides wholesale price per wholesale role on the product listing on the admin page.', 'woocommerce-wholesale-prices' ),
                    'id'    => 'wwpp_hide_wholesale_price_on_product_listing',
                    'class' => 'wwp_hide_wholesale_price_on_product_listing',
                ),

                array(
                    'name'  => __( 'Hide Price and Add to Cart button', 'woocommerce-wholesale-prices' ),
                    'type'  => 'checkbox',
                    'desc'  => __( 'If checked, hides price and add to cart button for visitors.', 'woocommerce-wholesale-prices' ),
                    'id'    => 'wwp_hide_price_add_to_cart',
                    'class' => 'wwp_hide_price_add_to_cart',
                ),

                array(
                    'name' => __( 'Price and Add to Cart Replacement Message', 'woocommerce-wholesale-prices' ),
                    'type' => 'wwp_editor',
                    'desc' => __( 'This message is only shown if <b>Hide Price and Add to Cart button</b> is enabled. "Login to see prices" is the default message.', 'woocommerce-wholesale-prices' ),
                    'id'   => 'wwp_price_and_add_to_cart_replacement_message',
                    'css'  => 'min-width: 400px; min-height: 100px;',
                ),

                array(
                    'name'     => __( 'Show Wholesale Price to non-wholesale users', 'woocommerce-wholesale-prices' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'If checked, displays the wholesale price on the front-end to entice non-wholesale customers to register as wholesale customers. This is only shown for guest, customers, administrator, and shop managers.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => '',
                    'id'       => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                    'class'    => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwp_settings_price_sectionend',
                ),

            );
        }

        /**
         * Price settings section options. This setting comes from WWPP. We maintain the prefix wwpp_ to avoid any with duplicate setting value.
         *
         * @since 1.11
         * @access public
         *
         * @return array
         */
        private function _get_tax_section_settings() {
            return array(

                array(
                    'name' => __( 'Tax Options', 'woocommerce-wholesale-prices' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwpp_settings_tax_section_title',
                ),
                array(
                    'name'     => __( 'Tax Exemption', 'woocommerce-wholesale-prices' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Do not apply tax to all wholesale roles', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => __( 'Removes tax for all wholesale roles. All wholesale prices will display excluding tax throughout the store, cart and checkout. The display settings below will be ignored.', 'woocommerce-wholesale-prices' ),
                    'id'       => 'wwp_settings_tax_exempt_wholesale_users',
                ),
                array(
                    'name'     => __( 'Display Prices in the Shop', 'woocommerce-wholesale-prices' ),
                    'type'     => 'select',
                    'class'    => 'wc-enhanced-select',
                    'desc'     => __( 'Choose how wholesale roles see all prices throughout your shop pages.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => __( 'Note: If the option above of "Tax Exempting" wholesale users is enabled, then wholesale prices on shop pages will not include tax regardless the value of this option.', 'woocommerce-wholesale-prices' ),
                    'options'  => array(
                        ''     => '--' . __( 'Use woocommerce default', 'woocommerce-wholesale-prices' ) . '--',
                        'incl' => __( 'Including tax (Premium)', 'woocommerce-wholesale-prices' ),
                        'excl' => __( 'Excluding tax (Premium)', 'woocommerce-wholesale-prices' ),
                    ),
                    'default'  => '',
                    'id'       => 'wwp_settings_incl_excl_tax_on_wholesale_price',
                ),
                array(
                    'name'     => __( 'Display Prices During Cart and Checkout', 'woocommerce-wholesale-prices' ),
                    'type'     => 'select',
                    'class'    => 'wc-enhanced-select',
                    'desc'     => __( 'Choose how wholesale roles see all prices on the cart and checkout pages.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => __( 'Note: If the option above of "Tax Exempting" wholesale users is enabled, then wholesale prices on cart and checkout page will not include tax regardless the value of this option.', 'woocommerce-wholesale-prices' ),
                    'options'  => array(
                        ''     => '--' . __( 'Use woocommerce default', 'woocommerce-wholesale-prices' ) . '--',
                        'incl' => __( 'Including tax (Premium)', 'woocommerce-wholesale-prices' ),
                        'excl' => __( 'Excluding tax (Premium)', 'woocommerce-wholesale-prices' ),
                    ),
                    'default'  => '',
                    'id'       => 'wwp_settings_wholesale_tax_display_cart',
                ),
                array(
                    'name'     => __( 'Override Regular Price Suffix', 'woocommerce-wholesale-prices' ),
                    'type'     => 'text',
                    'desc'     => __( 'Override the price suffix on regular prices for wholesale users.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => __( 'Make this blank to use the default price suffix. You can also use prices substituted here using one of the following {price_including_tax} and {price_excluding_tax}.', 'woocommerce-wholesale-prices' ),
                    'id'       => 'wwp_settings_override_price_suffix_regular_price',
                ),
                array(
                    'name'     => __( 'Wholesale Price Suffix', 'woocommerce-wholesale-prices' ),
                    'type'     => 'text',
                    'desc'     => __( 'Set a specific price suffix specifically for wholesale prices.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => __( 'Make this blank to use the default price suffix. You can also use prices substituted here using one of the following {price_including_tax} and {price_excluding_tax}.', 'woocommerce-wholesale-prices' ),
                    'id'       => 'wwp_settings_override_price_suffix',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_tax_divider1_sectionend',
                ),
                array(
                    'name' => __( 'Wholesale Role / Tax Exemption Mapping', 'woocommerce-wholesale-prices' ),
                    'type' => 'title',
                    'desc' => sprintf(
                        // translators: %1$s <b> tag, %2$s </b> tag, %3$s link to premium add-on, %4$s </a> tag, %5$s link to bundle.
                        __(
                            'Specify tax exemption per wholesale role. Overrides general %1$sTax Exemption%2$s option above.

                                    In the Premium add-on you can map specific wholesale roles to be tax exempt which gives you more control. This is useful for classifying customers
                                    based on their tax exemption status so you can separate those who need to pay tax and those who don\'t.

                                    This feature and more is available in the %3$sPremium add-on%4$s and we also have other wholesale tools available as part of the %5$sWholesale Suite Bundle%4$s.',
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
                    ),
                ),
                array(
                    'name' => '',
                    'type' => 'wholesale_role_tax_options_mapping_controls',
                    'desc' => '',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wwp_settings_tax_divider2_sectionend',
                ),
                array(
                    'name' => __( 'Wholesale Role / Tax Class Mapping', 'woocommerce-wholesale-prices' ),
                    'type' => 'title',
                    'desc' => sprintf(
                        // translators: %1$s link to premium add-on, %2$s </a> tag, %3$s link to wholesale suite bundle.
                        __(
                            'Specify tax classes per wholesale role.

                                    In the Premium add-on you can map specific wholesale role to specific tax classes. You can also hide those mapped tax classes from your regular
                                    customers making it possible to completely separate tax functionality for wholesale customers.

                                    This feature and more is available in the %1$sPremium add-on%2$s and we also have other wholesale tools available as part of the %3$sWholesale Suite Bundle%2$s.',
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
                    ),
                    'id'   => 'wwp_settings_wholesale_role_tax_class_mapping_section_title',
                ),
                array(
                    'name' => '',
                    'type' => 'wwp_upsells_buttons',
                    'desc' => '',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wwp_settings_tax_sectionend',
                ),

            );
        }

        /**
         * Upgrade section options.
         *
         * @since 1.11
         * @access public
         *
         * @return array
         */
        private function _get_upgrade_section_settings() {
            // Only show Upgrade tab when WWPP is deactivated.
            if ( WWP_Helper_Functions::is_wwpp_active() ) {
                return array();
            }

            return array(

                array(
                    'name' => '',
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwp_settings_upgrade_section_title',
                ),

                array(
                    'name' => '',
                    'type' => 'upgrade_content',
                    'desc' => '',
                    'id'   => 'wwp_settings_upgrade_content',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwp_settings_upgrade_sectionend',
                ),

            );
        }

        /**
         * Help section options.
         *
         * @since 1.11
         * @access public
         *
         * @return array
         */
        private function _get_help_section_settings() {
            // Only show Help tab when WWPP is deactivated, WWPP adds its own Help tab which should take precedence over this one.
            if ( WWP_Helper_Functions::is_wwpp_active() ) {
                return array();
            }

            return array(

                array(
                    'name' => __( 'Help Options', 'woocommerce-wholesale-prices' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwp_settings_help_section_title',
                ),

                array(
                    'name' => '',
                    'type' => 'help_resources_controls',
                    'desc' => '',
                    'id'   => 'wwp_settings_help_resources',
                ),

                array(
                    'name'     => __( 'Allow Usage Tracking', 'woocommerce-wholesale-prices' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'By allowing us to track usage data we can better help you because we know with which WordPress configurations, themes and plugins we should test.', 'woocommerce-wholesale-prices' ),
                    'desc_tip' => sprintf(
                        // translators: %1$s link to usage tracking documentation, %2$s </a> tag.
                        __( 'Complete documentation on usage tracking is available %1$shere%2$s.', 'woocommerce-wholesale-prices' ),
                        sprintf(
                            '<a href="%s" target="_blank">',
                            esc_url( WWP_Helper_Functions::get_utm_url( 'kb/usage-tracking', 'wwp', 'kb', 'helppageusagetracking' ) )
                        ),
                        '</a>'
                    ),
                    'id'       => 'wwp_anonymous_data',
                    'class'    => 'wwp_anonymous_data',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwp_settings_help_devider1',
                ),
            );
        }

        /**
         * WWS Dummy License options
         *
         * @since 2.1.3
         * @access private
         *
         * @return array
         */
        private function _get_license_section_settings() {

            if ( WWP_Helper_Functions::is_wwpp_active() ) {
                return array();
            }

            return array(

                array(
                    'name'  => '',
                    'title' => '',
                    'desc'  => '',
                    'id'    => 'wwp_settings_license_section_title',
                ),
                array(
                    'name' => '',
                    'type' => 'license_upgrade_content',
                    'desc' => '',
                    'id'   => 'wwp_settings_license_upgrade_content',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wwp_settings_license_sectionend',
                ),

            );
        }

        /**
         * Render WWS License upgrade content
         *
         * @since 2.1.3
         * @access public
         */
        public function render_license_upgrade_content() {

            if ( isset( $_GET['section'] ) && 'wwp_license_section' === sanitize_key( wp_unslash( $_GET['section'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_safe_redirect( admin_url( 'admin.php?page=wws-license-settings' ) );
                exit;
            }
        }

        /**
         * Render upgrade content
         *
         * @param array $value Array of field data.
         * @since 1.11
         */
        public function render_upgrade_content( $value ) {
            ob_start();
            require_once WWP_VIEWS_PATH . 'view-wwp-upgrade-upsell.php';
            echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        /**
         * Plugin knowledge base custom control.
         * WooCommerce > Settings > Wholesale Prices > Help > Knowledge Base
         *
         * @since 1.14
         * @access public
         */
        public function render_plugin_settings_custom_field_help_resources_controls() {
            if ( ! WWP_Helper_Functions::is_wwpp_active() ) {
                require_once WWP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwp-help-resources-controls-custom-field.php';
            }
        }

        /**
         * Only render upgrade tab if WWPP is NOT active.
         *
         * @since 1.11
         *
         * @param array $sections Array of tabs.
         * @return array
         */
        public function remove_upgrade_tab( $sections ) {
            if ( WWP_Helper_Functions::is_wwpp_active() && isset( $sections['wwp_upgrade_section'] ) ) {
                unset( $sections['wwp_upgrade_section'] );
            }

            return $sections;
        }

        /**
         * Only render help tab if WWPP is NOT active.
         *
         * @since 1.11
         *
         * @param array $sections Array of tabs.
         * @return array
         */
        public function remove_help_tab( $sections ) {
            if ( WWP_Helper_Functions::is_wwpp_active() && isset( $sections['wwp_help_section'] ) ) {
                unset( $sections['wwp_help_section'] );
            }

            return $sections;
        }

        /**
         * Remove dummy settings when WWPP is active.
         *
         * @since 1.12
         * @since 1.13.4 - Make function more generic to handle all sections
         *
         * @param array $settings Array of settings.
         * @return array
         */
        public function remove_dummy_settings_when_wwpp_active( $settings ) {
            // Set up array to hold settings IDs that we want to remove.
            $dummy_settings_to_remove = array();

            // Check that WWPP is active and that we are on the correct section in the settings.
            if ( WWP_Helper_Functions::is_wwpp_active() ) {

                if ( isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    switch ( sanitize_key( wp_unslash( $_GET['section'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        case 'wwpp_setting_price_section':
                            $dummy_settings_to_remove = array(
                                'wwp_settings_explicitly_use_product_regular_price_on_discount_calc_dummy',
                                'wwp_settings_variable_product_price_display_dummy',
                                'wwp_hide_wholesale_price_on_product_listing',
                            );
                            break;

                        default:
                            break;
                    }
                } else {
                    // General page.
                    $dummy_settings_to_remove = array(
                        'wwp_general_settings_bottom_upgrade_message',
                    );
                }
            }

            if ( WWP_Helper_Functions::has_paid_plugin_active() ) {
                $dummy_settings_to_remove[] = 'wwp_anonymous_data';
            }

            // Remove any dummy settings that we have identified.
            foreach ( $settings as $key => $setting ) {
                if ( isset( $setting['class'] ) && in_array( $setting['class'], $dummy_settings_to_remove, true ) ) {
                    unset( $settings[ $key ] );
                }
            }

            return $settings;
        }

        /**
         * WWPP upsell buttons.
         *
         * @since 1.11
         */
        public function wwp_upsells_buttons() {
            ?>
            <tr>
                <td style="padding: 0px; display: flex; padding-top: 20px;">

                    <a class="wws-bundle-btn" target="_blank"
                        href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'wwptaxbundlebutton' ) ); ?>">
                        <div>
                            <span><b><?php esc_html_e( 'Wholesale Suite Bundle &rarr;', 'woocommerce-wholesale-prices' ); ?></b></span>
                            <span><?php esc_html_e( '3x wholesale plugins', 'woocommerce-wholesale-prices' ); ?></span>
                        </div>
                    </a>
                    <a class="wwpp-addon" target="_blank"
                        href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwptaxwwppbutton' ) ); ?>"><?php esc_html_e( 'Wholeasale Prices Premium Add-on &rarr;', 'woocommerce-wholesale-prices' ); ?></a>
                </td>
            </tr>
            <?php
        }

        /**
         * Render wwp editor custom field.
         *
         * @since 1.13
         * @access public
         *
         * @param array $data Array of data.
         */
        public function render_plugin_settings_custom_field_wwp_editor( $data ) {
            require_once WWP_VIEWS_PATH . 'view-wwp-editor.php';
        }

        /**
         * Render WWP Free Training Guide, this is shown in the WC > Settings > Wholesale Price > General settings page.
         *
         * @since 2.1.2
         * @access public
         */
        public function render_plugin_settings_free_training_guide() {
            require_once WWP_VIEWS_PATH . 'view-wwp-free-training-guide.php';
        }

        /**
         * Move WWS License tab, after upgrade section under WWP Settings tab, move only if WWPP is active
         *
         * @since 2.1.3
         *
         * @param array $section Array of tabs.
         * @access public
         */
        public function move_wwp_license_section( $section ) {

            if ( WWP_Helper_Functions::is_wwpp_active() ) {
                foreach ( $section as $key => $setting ) {
                    if ( 'wwp_license_section' === $key ) {
                        unset( $section[ $key ] );
                        break;
                    }
                }

                $section = array_merge( $section, array( 'wwpp_license_section' => __( 'License', 'woocommerce-wholesale-prices' ) ) );
            }

            return $section;
        }

        /**
         * Display upgrade box description
         *
         * @since 2.1.7
         * @access private
         */
        private function _get_wwp_upgrade_box_desc() {
            ob_start();
            ?>
            <div class="wwp-upgrade-box">
                <img class="wws-logo" src="<?php echo esc_url( WWP_IMAGES_URL . '/logo-upgrade-box.png' ); ?>" />
                <h2><?php esc_html_e( 'Get Wholesale Suite and unlock all the wholesale features', 'woocommerce-wholesale-prices' ); ?></h2>
                <?php
                    esc_html_e( 'Thanks for being a loyal Wholesale Prices by Wholesale Suite user. Upgrade to unlock all of the extra wholesale features that makes Wholesale Suite consistently rated the best WooCommerce wholesale plugin.', 'woocommerce-wholesale-prices' );
                    echo wp_kses_post(
                        sprintf(
                            /* translators: %s: 5 star image. */
                            __( 'We know that you will truly love Wholesale Suite. It has 325+ five star ratings (%s) and is active on over 20,000 stores.' ),
                            '<img class="fivestar" src="' . esc_url( WWP_IMAGES_URL . '/5star.png' ) . '" />'
                        )
                    );
                ?>
                <div class="wwp-upgrade-box-row">
                    <div class="wwp-upgrade-box-col">
                        <h3><?php esc_html_e( 'Wholesale Prices Premium', 'woocommerce-wholesale-prices' ); ?></h3>
                        <ul>
                            <li>+ <?php esc_html_e( 'Global & category level wholesale pricing', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( '"Wholesale Only" products', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Hide wholesale products from retail customers', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Multiple levels of wholesale user roles', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Manage wholesale pricing over multiple user tiers', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Shipping mapping', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Payment gateway mapping', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Tax exemptions & fine grained tax display control', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Order minimum quantities & subtotals', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( '100\'s of other premium pricing related features', 'woocommerce-wholesale-prices' ); ?></li>
                        </ul>
                    </div>
                    <div class="wwp-upgrade-box-col">
                        <h3><?php esc_html_e( 'Wholesale Order Form', 'woocommerce-wholesale-prices' ); ?></h3>
                        <ul>
                            <li>+ <?php esc_html_e( 'Most efficient one-page WooCommerce ordering form', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'No page loading/reloading, fully AJAX enabled', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Advanced searching and category filtering', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Your whole catalog at your customer\'s fingertips', 'woocommerce-wholesale-prices' ); ?></li>
                        </ul>
                        <h3><?php esc_html_e( 'Wholesale Lead Capture', 'woocommerce-wholesale-prices' ); ?></h3>
                        <ul>
                            <li>+ <?php esc_html_e( 'Automatically recruit & register wholesale customers', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Save huge amounts of admin time & recruit on autopilot', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Full registration form builder', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Custom fields capability to capture all required information', 'woocommerce-wholesale-prices' ); ?></li>
                            <li>+ <?php esc_html_e( 'Full automated mode OR manual approvals mode', 'woocommerce-wholesale-prices' ); ?></li>
                        </ul>
                    </div>
                </div>

                <div class="actions">
                    <a href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'generalsettingsboxlink' ) ); ?>" target="_blank"><?php esc_html_e( 'Get Wholesale Suite today & unlock these powerful features + more', 'woocommerce-wholesale-prices' ); ?> &rarr;</a>
                    <p>
                        <?php
                            echo wp_kses_post(
                                sprintf(
                                    // translators: %1$s: <span style="font-weight: bold;">, %2$s: <span class="green-text">50&#37;, %3$s: </span>, %4$s: </span>.
                                    __( '%1$sBonus:%3$s Wholesale Prices lite users get %2$soff regular price%3$s, automatically applied at checkout.', 'woocommerce-wholesale-prices' ),
                                    '<span style="font-weight: bold;">',
                                    '<span class="green-text">50&#37;',
                                    '</span>'
                                )
                            );
                        ?>
                    </p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
    }
}

return new WWP_Settings();
