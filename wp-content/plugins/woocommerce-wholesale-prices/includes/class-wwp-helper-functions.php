<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Helper_Functions' ) ) {

    /**
     * Model that houses plugin helper functions.
     *
     * @since 1.3.0
     */
    final class WWP_Helper_Functions {

        /**
         * Utility function that determines if a plugin is active or not.
         *
         * @param string $plugin_basename Plugin base name. Ex. woocommerce/woocommerce.php.
         *
         * @since  1.3.0
         * @access public
         *
         * @return boolean True if active, false otherwise.
         */
        public static function is_plugin_active( $plugin_basename ) {

            // Makes sure the plugin is defined before trying to use it.
            if ( ! function_exists( 'is_plugin_active' ) ) {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            return is_plugin_active( $plugin_basename );
        }

        /**
         * Get Admin Note instance. WC Admin v1.7 they changed the class from WC_Admin_Note to Note.
         *
         * @param string $note_id Note ID.
         *
         * @since  1.13
         * @access public
         *
         * @return string
         */
        public static function wc_admin_note_instance( $note_id = null ) {

            if ( class_exists( '\Automattic\WooCommerce\Admin\Notes\Note' ) ) {
                return new \Automattic\WooCommerce\Admin\Notes\Note( $note_id );
            } else {
                return new \Automattic\WooCommerce\Admin\Notes\WC_Admin_Note( $note_id );
            }
        }

        /**
         * WWPP is active.
         *
         * @since  1.12
         * @access public
         *
         * @return boolean True if active, false otherwise.
         */
        public static function is_wwpp_active() {

            global $wc_wholesale_prices_premium;

            return self::is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) && $wc_wholesale_prices_premium;
        }

        /**
         * WWOF is active.
         *
         * @since  1.14
         * @since  2.1.6 Adds check for constant `WWOF_PLUGIN_FILE`.
         * @access public
         *
         * @return boolean True if active, false otherwise.
         */
        public static function is_wwof_active() {

            global $wc_wholesale_order_form;

            //phpcs:disable PEAR.Functions.FunctionCallSignature.Indent
            return self::is_plugin_active(
                    'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php'
                ) && ( $wc_wholesale_order_form || defined( 'WWOF_PLUGIN_FILE' ) );
            //phpcs:enable PEAR.Functions.FunctionCallSignature.Indent
        }

        /**
         * WWLC is active.
         *
         * @since  1.14
         * @access public
         * @return boolean True if active, false otherwise.
         */
        public static function is_wwlc_active() {

            global $wc_wholesale_lead_capture;

            return self::is_plugin_active( 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php' ) && $wc_wholesale_lead_capture;
        }

        /**
         * Check if WPAY is active.
         *
         * @since 2.2.0
         * @return bool
         */
        public static function is_wpay_active() {

            return self::is_plugin_active( 'woocommerce-wholesale-payments/woocommerce-wholesale-payments.php' ) || defined( 'WPAY_PLUGIN_FILE' );
        }

        /**
         * ACFWF is active.
         *
         * @since  2.1.1
         * @access public
         * @return boolean True if active, false otherwise.
         */
        public static function is_acfwf_active() {

            global $ACFWF;

            return self::is_plugin_active( 'advanced-coupons-for-woocommerce-free/advanced-coupons-for-woocommerce-free.php' ) && $ACFWF;
        }

        /**
         * Check if WC version is 4.3 and up or rc versions.
         *
         * @since  1.11.10
         * @access public
         * @return boolean True if active, false otherwise.
         */
        public static function is_wc_four_point_three_and_up() {

            $wc_data = self::get_woocommerce_data();

            if (
                version_compare( $wc_data['Version'], '4.3.0', '>=' ) ||
                '4.3.0-rc.1' === $wc_data['Version'] ||
                '4.3.0-rc.2' === $wc_data['Version']
            ) {
                return true;
            } else {
                return false;
            }
        }

        /**
         * Check if WC Admin is active
         *
         * @since  1.11.7
         * @access public
         * @return boolean True if active, false otherwise.
         */
        public static function is_wc_admin_active() {

            if ( class_exists( '\Automattic\WooCommerce\Admin\Composer\Package' ) && defined( 'WC_ADMIN_APP' ) && WC_ADMIN_APP ) {
                return \Automattic\WooCommerce\Admin\Composer\Package::is_package_active();
            } elseif ( self::is_plugin_active( 'woocommerce-admin/woocommerce-admin.php' ) ) {
                return true;
            } else {
                return false;
            }
        }

        /**
         * Check if ACFWF is installed
         *
         * @since 1.11.5
         *
         * @return bool
         */
        public static function is_acfwf_installed() {

            $plugin_file = 'advanced-coupons-for-woocommerce-free/advanced-coupons-for-woocommerce-free.php';
            $acfwf_file  = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $acfwf_file );
        }

        /**
         * Check if WWP is installed
         *
         * @since  1.11.5
         * @access public
         *
         * @return bool
         */
        public static function is_wwp_installed() {

            $plugin_file = 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php';
            $wwp_file    = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $wwp_file );
        }

        /**
         * Check if WWPP is installed
         *
         * @since  1.11.5
         * @access public
         *
         * @return bool
         */
        public static function is_wwpp_installed() {

            $plugin_file = 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php';
            $wwpp_file   = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $wwpp_file );
        }

        /**
         * Check if WWOF is installed
         *
         * @since  1.11.5
         * @access public
         *
         * @return bool
         */
        public static function is_wwof_installed() {

            $plugin_file = 'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php';
            $wwof_file   = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $wwof_file );
        }

        /**
         * Check if WWLC is installed
         *
         * @since  1.11.5
         * @access public
         *
         * @return bool
         */
        public static function is_wwlc_installed() {

            $plugin_file = 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php';
            $wwlc_file   = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $wwlc_file );
        }


        /**
         * Check if WWQ is active.
         *
         * @since 2.2.5
         *
         * @return boolean
         */
        public static function is_wwq_active() {
            return self::is_plugin_active( 'woocommerce-wholesale-quotes/woocommerce-wholesale-quotes.php' );
        }

        /**
         * Check if WWQ is installed.
         *
         * @since 2.2.5
         *
         * @return boolean
         */
        public static function is_wwq_installed() {
            $plugin_file = 'woocommerce-wholesale-quotes/woocommerce-wholesale-quotes.php';

            $wwq_file = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $wwq_file );
        }

        /**
         * Check if WPAY is installed
         *
         * @since   2.2.0 - Added
         *
         * @return bool
         * @version 2.2.0
         */
        public static function is_wpay_installed() {

            $plugin_file = 'woocommerce-wholesale-payments/woocommerce-wholesale-payments.php';
            $wpay_file   = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $wpay_file ) ? true : false;
        }

        /**
         * Check if WC Vendors is installed
         *
         * @since 2.2.1
         *
         * @return bool
         */
        public static function is_wcvendors_installed() {

            $plugin_file    = 'wc-vendors/class-wc-vendors.php';
            $wcvendors_file = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $wcvendors_file );
        }

        /**
         * Check if AdTribes is installed
         *
         * @since 2.2.1
         *
         * @return bool
         */
        public static function is_adtribes_installed() {

            $plugin_file   = 'woo-product-feed-pro/woocommerce-sea.php';
            $adtribes_file = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $adtribes_file );
        }

        /**
         * Check if Invoice Gateway is installed
         *
         * @since 2.2.1
         *
         * @return bool
         */
        public static function is_invoice_gateway_installed() {

            $plugin_file          = 'invoice-gateway-for-woocommerce/invoice-gateway-for-woocommerce.php';
            $invoice_gateway_file = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $invoice_gateway_file );
        }

        /**
         * Check if Store Toolkit is installed
         *
         * @since 2.2.1
         *
         * @return bool
         */
        public static function is_store_toolkit_installed() {

            $plugin_file        = 'woocommerce-store-toolkit/store-toolkit.php';
            $store_toolkit_file = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $store_toolkit_file );
        }

        /**
         * Check if 'SaveTo Wishlist Lite for WooCommerce' is installed.
         *
         * @return boolean
         */
        public static function is_save_to_wish_list_for_woocommerce_installed() {
            $plugin_file           = 'saveto-wishlist-lite-for-woocommerce/saveto-wishlist-lite-for-woocommerce.php';
            $save_to_wishlist_file = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $save_to_wishlist_file );
        }

        /**
         * Check if Store Exporter is installed
         *
         * @since 2.2.1
         *
         * @return bool
         */
        public static function is_store_exporter_installed() {

            $plugin_file         = 'woocommerce-exporter/exporter.php';
            $store_exporter_file = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $store_exporter_file );
        }

        /**
         * Check if StoreAgent AI is installed
         *
         * @since 2.2.1
         *
         * @return bool
         */
        public static function is_storeagent_installed() {

            $plugin_file     = 'storeagent-ai-for-woocommerce/storeagent-ai-for-woocommerce.php';
            $storeagent_file = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $storeagent_file );
        }

        /**
         * Check if FunnelKit Stripe is installed
         *
         * @since 2.2.1
         *
         * @return bool
         */
        public static function is_funnelkit_stripe_installed() {

            $plugin_file           = 'funnelkit-stripe-woo-payment-gateway/funnelkit-stripe-woo-payment-gateway.php';
            $funnelkit_stripe_file = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

            return file_exists( $funnelkit_stripe_file );
        }

        /**
         * Check if any plugin is installed
         *
         * @param string $plugin_name Plugin name.
         *
         * @since 2.2.1
         *
         * @return bool
         */
        public static function is_wp_plugin_installed( $plugin_name ) {
            return file_exists( trailingslashit( WP_PLUGIN_DIR ) . $plugin_name );
        }

        /**
         * Get data about the current woocommerce installation.
         *
         * @since  1.3.1
         * @access public
         *
         * @return array Array of data about the current woocommerce installation.
         */
        public static function get_woocommerce_data() {

            return self::get_plugin_data( 'woocommerce/woocommerce.php' );
        }

        /**
         * Get plugin data.
         *
         * @param string $plugin_basename Plugin basename.
         *
         * @since  1.4.0
         * @access public
         *
         * @return array Array of data about the current woocommerce installation.
         */
        public static function get_plugin_data( $plugin_basename ) {

            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . '/wp-admin/includes/plugin.php';
            }

            if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_basename ) ) {
                return get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_basename, false, false );
            } else {
                return false;
            }
        }

        /**
         * Output a select input box.
         *
         * This is similar to woocommerce_wp_select function of WooCommerce.
         * Modified the position of tooltip, this is essential for the variation products where the tooltip should be next to the label, not the text field.
         *
         * @param array $field Data about the field to render.
         *
         * @since  2.1.0
         * @access public
         */
        public static function woocommerce_wp_select( $field ) {

            global $thepostid, $post;

            $thepostid = empty( $thepostid ) ? $post->ID : $thepostid;

            // Get product object.
            $product = wc_get_product( $thepostid );

            $field = wp_parse_args(
                $field,
                array(
                    'class'             => 'select short',
                    'style'             => '',
                    'wrapper_class'     => '',
                    'value'             => $product->get_meta( $field['id'], true ),
                    'name'              => $field['id'],
                    'desc_tip'          => false,
                    'custom_attributes' => array(),
                )
            );

            $wrapper_attributes = array(
                'class' => $field['wrapper_class'] . " form-field {$field['id']}_field",
            );

            $label_attributes = array(
                'for' => $field['id'],
            );

            $field_attributes          = (array) $field['custom_attributes'];
            $field_attributes['style'] = $field['style'];
            $field_attributes['id']    = $field['id'];
            $field_attributes['name']  = $field['name'];
            $field_attributes['class'] = $field['class'];

            // Custom part.
            $tooltip     = '';
            $description = '';
            if ( ! empty( $field['description'] ) ) {

                if ( isset( $field['desc_tip'] ) && false !== $field['desc_tip'] ) {
                    $tooltip = '<span class="wwp_wc_help_tip_container" style="top: -3px; position: relative; display: inline-block;">' . wc_help_tip( $field['description'] ) . '</span>';
                } else {
                    $description = '<br><span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
                }
            }

            ?>
            <p <?php echo wc_implode_html_attributes( $wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <label <?php echo wc_implode_html_attributes( $label_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo wp_kses_post( $field['label'] . $tooltip ); ?></label>
                <select <?php echo wc_implode_html_attributes( $field_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <?php
                    foreach ( $field['options'] as $key => $value ) {
                        echo '<option value="' . esc_attr( $key ) . '"' . esc_attr( wc_selected( $key, $field['value'] ) ) . '>' . esc_html( $value ) . '</option>';
                    }
                    ?>
                </select>
                <?php echo wp_kses_post( $description ); ?>
            </p>
            <?php
        }

        /**
         * Output a text input box.
         * This gonna be 99.99% similar to woocommerce_wp_text_input function of WooCommerce.
         * Only difference is the position of the tooltip.
         * This is essential for wholesale prices field on variation products where the tooltip should be next to the label, not the text field.
         *
         * @param array $field Field data.
         *
         * @since  1.3.0
         * @access public
         */
        public static function wwp_woocommerce_wp_text_input( $field ) {

            global $thepostid, $post;

            $thepostid = empty( $thepostid ) ? $post->ID : $thepostid;

            // Get product object.
            $product = wc_get_product( $thepostid );

            $field['placeholder']   = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
            $field['class']         = isset( $field['class'] ) ? $field['class'] : 'short';
            $field['style']         = isset( $field['style'] ) ? $field['style'] : '';
            $field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
            $field['value']         = isset( $field['value'] ) ? $field['value'] : $product->get_meta( $field['id'], true );
            $field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
            $field['type']          = isset( $field['type'] ) ? $field['type'] : 'text';
            $data_type              = empty( $field['data_type'] ) ? '' : $field['data_type'];

            switch ( $data_type ) {
                case 'price':
                    $field['class'] .= ' wc_input_price';

                    $field['value'] = wc_format_localized_price( $field['value'] );
                    break;
                case 'decimal':
                    $field['class'] .= ' wc_input_decimal';

                    $field['value'] = wc_format_localized_decimal( $field['value'] );
                    break;
                case 'stock':
                    $field['class'] .= ' wc_input_stock';

                    $field['value'] = wc_stock_amount( $field['value'] );
                    break;
                case 'url':
                    $field['class'] .= ' wc_input_url';

                    $field['value'] = esc_url( $field['value'] );
                    break;

                default:
                    break;
            }

            // Custom attribute handling.
            $custom_attributes = array();

            if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {

                foreach ( $field['custom_attributes'] as $attribute => $value ) {
                    $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
                }
            }

            // Custom part.
            $desc_tootip = '';
            if ( ! empty( $field['description'] ) ) {

                if ( isset( $field['desc_tip'] ) && false !== $field['desc_tip'] ) {
                    $desc_tootip = '<span class="wwp_wc_help_tip_container" style="top: -3px; position: relative; display: inline-block;">' . wc_help_tip( $field['description'] ) . '</span>';
                } else {
                    $desc_tootip = '<br><span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
                }
            }

            echo '<p class="form-field ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '">
                    <label for="' . esc_attr( $field['id'] ) . '">' . wp_kses_post( $field['label'] . $desc_tootip ) . '</label>
                    <input type="' . esc_attr( $field['type'] ) . '" class="' . esc_attr( $field['class'] ) . '" style="' . esc_attr( $field['style'] ) . '" name="' . esc_attr( $field['name'] ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['value'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" ' . implode( ' ', $custom_attributes ) . ' /> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</p>';
        }

        /**
         * Build the wholesale-price field label for the Aelia base currency.
         *
         * Canonical source for the "{name} ({symbol}) <em><b>Base Currency</b></em>" label format used by
         * the simple- and variable-product admin renders. The live base-currency JS handler mirrors this
         * format from localized data (it rebuilds the label via DOM nodes for output safety), so any change
         * here must be reflected there too.
         *
         * @since 2.2.9
         *
         * @param string $currency_name   The base currency display name.
         * @param string $currency_symbol The base currency symbol.
         *
         * @return string The base-currency field label markup.
         */
        public static function wwp_get_aelia_base_currency_field_label( $currency_name, $currency_symbol ) {

            return $currency_name . ' (' . $currency_symbol . ') <em><b>' . __( 'Base Currency', 'woocommerce-wholesale-prices' ) . '</b></em>';
        }

        /**
         * Return formatted price.
         * WPML compatible.
         *
         * @param float $price Raw price.
         * @param array $args  Additional arguments.
         *
         * @since  1.11.3 Bug fix. Removed is_admin() condition. For some reason WPML is hacking it to return true always. This is causing pricing conversion issue with custom page like WWOF.
         * @access public
         *
         * @since  1.4.0
         * @since  1.6.0 Bug fix. Default currency wholesale price have no currency symbol. WWP-143
         * @return string Formatted price.
         */
        public static function wwp_formatted_price( $price, $args = array() ) {

            if ( self::is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {

                global $woocommerce_wpml;

                if ( ! defined( 'WCML_MULTI_CURRENCIES_INDEPENDENT' ) ) {
                    include_once WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'wpml-woocommerce' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'constants.php';
                }

                if ( WCML_MULTI_CURRENCIES_INDEPENDENT === $woocommerce_wpml->settings['enable_multi_currency'] ) {

                    /**
                     * Wrap raw price with 'wcml_formatted_price' filter so that conversion will be correct.
                     * Only do this only if the currency is not the same as the default currency.
                     */
                    if ( $woocommerce_wpml->multi_currency->get_client_currency() !== get_option( 'woocommerce_currency' ) ) {
                        return apply_filters( 'wcml_formatted_price', $price );
                    }
                }
            }

            return wc_price( $price, $args );
        }

        /**
         * Normalise the currency symbol of a pre-rendered price HTML string to the active WPML/WCML client currency.
         *
         * Under WPML WooCommerce Multicurrency in independent mode the retail *amount* is converted to the client
         * currency by WCML's price filters (which run regardless of request context), but the *symbol* baked into a
         * pre-rendered get_price_html() string can still be the store's base-currency symbol — notably in a REST
         * context, where the display-currency selection that swaps the symbol on the storefront is not applied. WWP
         * formats the wholesale price through wwp_formatted_price() (which routes through the WCML price filter), so the
         * wholesale half already shows the client symbol; this helper brings the accompanying original/retail symbol in
         * line so both halves match. Only the symbol is rewritten — the already-converted amount is left intact (and a
         * pre-rendered amount cannot be re-converted). The reported defect (#352) is a symbol-only mismatch, consistent
         * with this amount-converted / symbol-lagging split.
         *
         * @since 2.3.0
         * @access public
         *
         * @param string $price_html Pre-rendered price HTML (for example, the output of WC_Product::get_price_html()).
         *
         * @return string Price HTML with the currency symbol normalised to the client currency, or the input unchanged
         *                when WCML independent multicurrency is not active or the client currency matches the base currency.
         */
        public static function wwp_correct_price_html_currency_symbol( $price_html ) {

            if ( '' === trim( (string) $price_html ) ) {
                return $price_html;
            }

            $client_currency = self::wwp_get_wcml_independent_client_currency();

            if ( null === $client_currency ) {
                return $price_html;
            }

            // Source the symbol from the same path the wholesale price is formatted through (wwp_formatted_price() ->
            // wcml_formatted_price) so both halves match even when the merchant has customised the currency symbol in
            // WCML. Fall back to WooCommerce's default symbol for the currency if the probe yields no symbol span.
            $client_symbol = get_woocommerce_currency_symbol( $client_currency );

            if ( preg_match( '/<span class="woocommerce-Price-currencySymbol"[^>]*>(.*?)<\/span>/', (string) apply_filters( 'wcml_formatted_price', 0 ), $probe ) ) {
                $client_symbol = $probe[1];
            }

            return self::wwp_replace_price_html_currency_symbol( $price_html, $client_symbol );
        }

        /**
         * Resolve the active WPML/WCML client currency when independent multicurrency is in effect.
         *
         * Extracted from wwp_correct_price_html_currency_symbol() so the guard (plugin-active check, constants
         * include, mode comparison and base-vs-client comparison) can be unit-tested and reused. Note:
         * wwp_formatted_price() still carries an independent copy of the same guard and has NOT been migrated to
         * this helper — that consolidation is deferred to a later pass, so a WCML-internals change (constant
         * renamed, constants.php relocated) must be applied to both copies until then.
         *
         * @since 2.3.0
         * @access private
         *
         * @return string|null The client currency code when WCML independent multicurrency is active and the client
         *                     currency differs from the store base currency; null otherwise.
         */
        private static function wwp_get_wcml_independent_client_currency() {

            if ( ! self::is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {
                return null;
            }

            global $woocommerce_wpml;

            if ( ! defined( 'WCML_MULTI_CURRENCIES_INDEPENDENT' ) ) {
                include_once WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'wpml-woocommerce' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'constants.php';
            }

            if ( ! isset( $woocommerce_wpml->multi_currency, $woocommerce_wpml->settings['enable_multi_currency'] ) || WCML_MULTI_CURRENCIES_INDEPENDENT !== $woocommerce_wpml->settings['enable_multi_currency'] ) {
                return null;
            }

            $client_currency = $woocommerce_wpml->multi_currency->get_client_currency();

            return get_option( 'woocommerce_currency' ) !== $client_currency ? $client_currency : null;
        }

        /**
         * Replace the symbol inside every WooCommerce currency-symbol span of a price HTML string.
         *
         * Pure string transform (no WCML/WordPress state), extracted so the rewrite can be unit-tested in isolation.
         * Only the currency-symbol span content is replaced; surrounding markup and the amount are left intact. The
         * opening tag may carry extra attributes (for example, translate="no") depending on the WooCommerce version, so
         * any attributes are allowed, and a callback inserts the symbol verbatim so a replacement metacharacter (for
         * example, "$") is not interpreted.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $price_html    Pre-rendered price HTML, possibly containing more than one symbol span (a range).
         * @param string $client_symbol The currency symbol to stamp into each symbol span.
         *
         * @return string The price HTML with every currency-symbol span's content replaced by $client_symbol.
         */
        private static function wwp_replace_price_html_currency_symbol( $price_html, $client_symbol ) {

            return preg_replace_callback(
                '/(<span class="woocommerce-Price-currencySymbol"[^>]*>).*?(<\/span>)/',
                static function ( $matches ) use ( $client_symbol ) {
                    return $matches[1] . $client_symbol . $matches[2];
                },
                $price_html
            );
        }

        /**
         * Return price.
         * WPML compatible. Converts price accordingly.
         *
         * @param float $price Raw price.
         *
         * @since  1.4.0
         * @access public
         *
         * @return float Processed price.
         */
        public static function wwp_wpml_price( $price ) {

            if ( self::is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {

                global $woocommerce_wpml;

                if ( ! defined( 'WCML_MULTI_CURRENCIES_INDEPENDENT' ) ) {
                    include_once WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'wpml-woocommerce' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'constants.php';
                }

                if ( WCML_MULTI_CURRENCIES_INDEPENDENT === $woocommerce_wpml->settings['enable_multi_currency'] ) {
                    return apply_filters( 'woocommerce_adjust_price', $price );
                } else {
                    return $price;
                }
            } else {
                return $price;
            }
        }

        /**
         * Get product price including tax. WC 2.7.
         *
         * @param WC_Product $product Product object.
         * @param array      $args    Array of arguments data.
         *
         * @since  1.3.1
         * @since  2.1.5 Remove legacy code related to pre-WC 3.0.
         * @access public
         *
         * @return float Product price with tax.
         */
        public static function wwp_get_price_including_tax( $product, $args ) {

            return wc_get_price_including_tax( $product, $args );
        }

        /**
         * Get product price excluding tax. WC 2.7.
         *
         * @param WC_Product $product Product object.
         * @param array      $args    Array of arguments data.
         *
         * @since  1.3.1
         * @since  2.1.5 Remove legacy code related to pre-WC 3.0.
         * @access public
         *
         * @return float Product price with no tax.
         */
        public static function wwp_get_price_excluding_tax( $product, $args ) {

            return wc_get_price_excluding_tax( $product, $args );
        }

        /**
         * Get product id. WC 2.7.
         *
         * @param WC_Product $product Product object.
         *
         * @since  2.1.5 Remove legacy code related to pre-WC 3.0.
         * @access public
         *
         * @since  1.3.1
         * @return int Product id.
         */
        public static function wwp_get_product_id( $product ) {

            if ( is_a( $product, 'WC_Product' ) ) {
                return $product->get_id();
            } else {
                error_log( 'WWP Error: wwp_get_product_id helper functions expect parameter $product of type WC_Product.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

                return 0;
            }
        }

        /**
         * Get variation parent variable product id. WC 2.7.
         *
         * @param WC_Product_Variation $variation Variation object.
         *
         * @since  2.1.5 Remove legacy code related to pre-WC 3.0.
         * @access public
         *
         * @since  1.3.1
         * @return int Variable product id.
         */
        public static function wwp_get_parent_variable_id( $variation ) {

            if ( self::wwp_get_product_type( $variation ) === 'variation' ) {
                return $variation->get_parent_id();
            } else {
                error_log( 'WWP Error: wwp_get_parent_variable_id helper function expect parameter $variation as a product variation.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

                return 0;
            }
        }

        /**
         * Get product type. WC 2.7.
         *
         * @param WC_Product $product Product type.
         *
         * @since  2.1.5 Remove legacy code related to pre-WC 3.0.
         * @access public
         *
         * @since  1.3.1
         * @return string Product type.
         */
        public static function wwp_get_product_type( $product ) {

            if ( is_a( $product, 'WC_Product' ) ) {
                return $product->get_type();
            } else {
                error_log( 'WWP Error: wwp_get_product_type helper functions expect parameter $product of type WC_Product.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

                return 0;
            }
        }

        /**
         * Get product display price. WC 2.7.
         *
         * @param WC_Product $product Product object.
         * @param array      $args    Array of additional data.
         *
         * @since  1.3.1
         * @since  2.1.5 Remove legacy code related to pre-WC 3.0.
         * @access public
         *
         * @return float Product display price.
         */
        public static function wwp_get_product_display_price( $product, $args = array() ) {

            return wc_get_price_to_display( $product, $args );
        }

        /**
         * Match a variation to a given set of attributes using a WP_Query. WC 2.7.
         *
         * @param WC_Product_Variable $variable         Variable product.
         * @param array               $match_attributes Attributes to match with.
         *
         * @since  1.3.1
         * @since  2.1.5 Remove legacy code related to pre-WC 3.0.
         * @access public
         *
         * @return int Matched variation id.
         */
        public static function wwp_get_matching_variation( $variable, $match_attributes = array() ) {

            $data_store = WC_Data_Store::load( 'product' );

            return $data_store->find_matching_product_variation( $variable, $match_attributes );
        }

        /**
         * This is a requirement for 'wwp_get_variable_product_variations' helper function.
         * You see 'get_available_variations' function indirectly calls 'get_price_html', which will then call 'woocommerce_get_price_html'.
         * So what happens is every time we call 'get_available_variations', we fire the 'woocommerce_get_price_html' filter, then our callbacks gets triggered.
         * In short we are executing our callbacks unnecessarily.
         *
         * @param boolean $return Boolean flag to determine to either show variation price or not.
         *
         * @since  1.5.0
         * @access public
         *
         * @return boolean Hard boolean false.
         */
        public static function wwp_hide_woocommerce_show_variation_price( $return ) { // phpcs:ignore
            return false;
        }

        /**
         * Efficient way of getting all variations of a variable product.
         * Please see 'wwp_hide_woocommerce_show_variation_price' above.
         *
         * @param WC_Product_Variable $variable_product Variable product object.
         *
         * @since  1.5.0
         * @access public
         *
         * @return array Array of variable product variations.
         */
        public static function wwp_get_variable_product_variations( $variable_product ) {

            add_filter(
                'woocommerce_show_variation_price',
                array(
                    'WWP_Helper_Functions',
                    'wwp_hide_woocommerce_show_variation_price',
                )
            );
            $variations = $variable_product->get_available_variations();
            remove_filter(
                'woocommerce_show_variation_price',
                array(
                    'WWP_Helper_Functions',
                    'wwp_hide_woocommerce_show_variation_price',
                )
            );

            return $variations;
        }

        /**
         * Check validity of a save post action.
         *
         * @param int    $post_id   Id of the coupon post.
         * @param string $post_type Post type to check.
         *
         * @since  1.6.0
         * @access public
         *
         * @return bool True if valid save post action, False otherwise.
         */
        public static function check_if_valid_save_post_action( $post_id, $post_type ) {

            if ( get_post_type() !== $post_type ||
                empty( $_POST ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing
                wp_is_post_autosave( $post_id ) ||
                wp_is_post_revision( $post_id ) ||
                ! current_user_can( 'edit_post', $post_id )
            ) {
                return false;
            } else {
                return true;
            }
        }

        /**
         * Log deprecated function error to the debug.log file.
         *
         * @param array  $trace         debug_backtrace() output.
         * @param string $function_name Name of depecrated function.
         * @param string $version       Version when the function is set as depecrated since.
         * @param string $replacement   Name of function to be replaced.
         *
         * @since  1.10.0
         * @access public
         */
        public static function deprecated_function( $trace, $function_name, $version, $replacement = null ) {

            $caller = array_shift( $trace );

            $log_string = "The <em>{$function_name}</em> function is deprecated since version <em>{$version}</em>.";

            $log_string .= $replacement ? " Replace with <em>{$replacement}</em>." : '';
            $log_string .= ' Trace: <strong>' . $caller['file'] . '</strong> on line <strong>' . $caller['line'] . '</strong>';

            error_log( wp_strip_all_tags( $log_string ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        /**
         * Convenience function to fetch WWP version
         *
         * @since  1.14
         * @access public
         *
         * @return string WWP version
         */
        public static function get_wwp_version() {

            global $wc_wholesale_prices;

            return $wc_wholesale_prices::VERSION;
        }

        /**
         * Convenience function to fetch WWPP version
         *
         * @since  1.14
         * @access public
         *
         * @return string WWPP version
         */
        public static function get_wwpp_version() {

            if ( self::is_wwpp_active() ) {
                global $wc_wholesale_prices_premium;

                return $wc_wholesale_prices_premium::VERSION;
            } else {
                return '';
            }
        }

        /**
         * Convenience function to fetch WWOF version
         *
         * @since  1.14
         * @since  2.1.6 Checks global `$wc_wholesale_order_form` variable before trying to access its property.
         * @access public
         * @return string WWOF version
         */
        public static function get_wwof_version() {

            if ( self::is_wwof_active() ) {
                global $wc_wholesale_order_form;
                if ( ! empty( $wc_wholesale_order_form ) ) {
                    return $wc_wholesale_order_form::VERSION;
                }

                $data = self::get_plugin_data(
                    'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php'
                );

                return $data['Version'];
            } else {
                return '';
            }
        }

        /**
         * Convenience function to fetch WWLC version
         *
         * @since  1.14
         * @access public
         *
         * @return string WWLC version
         */
        public static function get_wwlc_version() {

            if ( self::is_wwlc_active() ) {
                global $wc_wholesale_lead_capture;

                return $wc_wholesale_lead_capture::VERSION;
            } else {
                return '';
            }
        }

        /**
         * Convenience function to fetch WPAY version.
         *
         * WPAY does not expose a version constant or a global object with a
         * VERSION property, so the version is read from the plugin header.
         *
         * @since  2.3.0 Added to report the correct WPAY version in usage tracking.
         * @access public
         *
         * @return string WPAY version, or an empty string when WPAY is inactive.
         */
        public static function get_wpay_version() {

            if ( self::is_wpay_active() ) {
                $data = self::get_plugin_data(
                    'woocommerce-wholesale-payments/woocommerce-wholesale-payments.php'
                );

                return ( is_array( $data ) && ! empty( $data['Version'] ) ) ? $data['Version'] : '';
            } else {
                return '';
            }
        }

        /**
         * Convenience function to fetch WWQ version.
         *
         * WWQ exposes its version through the `WWS_WQ_VERSION` constant, defined
         * on load by its main plugin file.
         *
         * @since  2.3.0 Added to report the WWQ version in the usage check-in.
         * @access public
         *
         * @return string WWQ version, or an empty string when WWQ is inactive.
         */
        public static function get_wwq_version() {

            if ( self::is_wwq_active() && defined( 'WWS_WQ_VERSION' ) ) {
                return WWS_WQ_VERSION;
            }

            return '';
        }

        /**
         * Check to see if any paid plugin by Wholesale Suite is active
         *
         * @since  1.14
         * @since  2.3.0 Treat Wholesale Quotes (WWQ) as a paid plugin so a WWP+WWQ-only site checks in.
         * @access public
         *
         * @return bool If a paid plugin (WWPP, WWOF, WWLC, WPAY, or WWQ) is active or not
         */
        public static function has_paid_plugin_active() {

            return ( self::is_wwpp_active() || self::is_wwof_active() || self::is_wwlc_active() || self::is_wpay_active() || self::is_wwq_active() );
        }

        /**
         * Check to see if the given URL looks like a dev site
         *
         * @param string $url URL to check.
         *
         * @since  1.14
         * @access public
         *
         * @return bool If it appears to be a dev site
         */
        public static function is_dev_url( $url = '' ) {

            $is_local_url = false;

            // Check if testing constant is set.
            if ( defined( 'WWS_TESTING_SITE' ) && WWS_TESTING_SITE ) {
                return false;
            }

            // Use site's URL if nothing provided.
            if ( empty( $url ) ) {
                $url = get_bloginfo( 'url' );
            }

            // Trim it up.
            $url = strtolower( trim( $url ) );

            // Need to get the host...so let's add the scheme so we can use parse_url.
            if ( ! str_contains( $url, 'http://' ) && ! str_contains( $url, 'https://' ) ) {
                $url = 'http://' . $url;
            }

            $url_parts = wp_parse_url( $url );
            $host      = ! empty( $url_parts['host'] ) ? $url_parts['host'] : false;

            if ( ! empty( $url ) && ! empty( $host ) ) {
                if ( false !== ip2long( $host ) ) {
                    if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        $is_local_url = true;
                    }
                } elseif ( 'localhost' === $host ) {
                    $is_local_url = true;
                }

                $tlds_to_check = array( '.local', ':8888', ':8080', ':8081', '.invalid', '.example', '.test' );
                foreach ( $tlds_to_check as $tld ) {
                    if ( str_contains( $host, $tld ) ) {
                        $is_local_url = true;
                        break;
                    }
                }
                if ( substr_count( $host, '.' ) > 1 ) {
                    $subdomains_to_check = array( 'dev.', '*.staging.', 'beta.', 'test.' );
                    foreach ( $subdomains_to_check as $subdomain ) {
                        $subdomain = str_replace( '.', '(.)', $subdomain );
                        $subdomain = str_replace( array( '*', '(.)' ), '(.*)', $subdomain );
                        if ( preg_match( '/^(' . $subdomain . ')/', $host ) ) {
                            $is_local_url = true;
                            break;
                        }
                    }
                }
            }

            return $is_local_url;
        }

        /**
         * Retrieve all premium plugin license data if present
         *
         * @since  1.14
         * @since  2.3.0 Added Wholesale Quotes (WWQ) license data.
         * @access public
         *
         * @return array Array containing license data (if present, otherwise this will be empty)
         */
        public static function get_license_data() {

            $license_data = array();

            if ( self::is_wwpp_active() ) {
                $wwpp_license_email = is_multisite() ? get_site_option( WWPP_OPTION_LICENSE_EMAIL ) : get_option( WWPP_OPTION_LICENSE_EMAIL );
                $wwpp_license_key   = is_multisite() ? get_site_option( WWPP_OPTION_LICENSE_KEY ) : get_option( WWPP_OPTION_LICENSE_KEY );

                $license_data['wwpp_license_email'] = $wwpp_license_email;
                $license_data['wwpp_license_key']   = $wwpp_license_key;
            }

            if ( self::is_wwof_active() ) {
                $wwof_license_email = is_multisite() ? get_site_option( WWOF_OPTION_LICENSE_EMAIL ) : get_option( WWOF_OPTION_LICENSE_EMAIL );
                $wwof_license_key   = is_multisite() ? get_site_option( WWOF_OPTION_LICENSE_KEY ) : get_option( WWOF_OPTION_LICENSE_KEY );

                $license_data['wwof_license_email'] = $wwof_license_email;
                $license_data['wwof_license_key']   = $wwof_license_key;
            }

            if ( self::is_wwlc_active() ) {
                $wwlc_license_email = is_multisite() ? get_site_option( WWLC_OPTION_LICENSE_EMAIL ) : get_option( WWLC_OPTION_LICENSE_EMAIL );
                $wwlc_license_key   = is_multisite() ? get_site_option( WWLC_OPTION_LICENSE_KEY ) : get_option( WWLC_OPTION_LICENSE_KEY );

                $license_data['wwlc_license_email'] = $wwlc_license_email;
                $license_data['wwlc_license_key']   = $wwlc_license_key;
            }

            if ( self::is_wpay_active() ) {
                $license_data['wpay_license_key']   = is_multisite() ? get_site_option( 'wpay_license_key', null ) : get_option( 'wpay_license_key', null );
                $license_data['wpay_license_email'] = is_multisite() ? get_site_option( 'wpay_license_email', null ) : get_option( 'wpay_license_email', null );
            }

            if ( self::is_wwq_active() ) {
                // WWQ stores its license through the network-wide option API, so it is read the
                // same way here to stay correct on both single-site and multisite installs.
                $license_data['wwq_license_email'] = get_site_option( 'wws_wq_license_account_email', '' );
                $license_data['wwq_license_key']   = get_site_option( 'wws_wq_license_key', '' );
            }

            return $license_data;
        }

        /**
         * Helper function to fetch the current WooCommerce version and return it
         *
         * @since  1.14
         * @access public
         *
         * @return string WooCommerce version as reported by WooCommerce
         */
        public static function get_current_woocommerce_version() {

            $woocommerce_data = self::get_woocommerce_data();

            return isset( $woocommerce_data['Version'] ) ? $woocommerce_data['Version'] : '';
        }

        /**
         * Load React Scripts.
         *
         * @param array $args Arguments.
         *
         * @since  2.0
         * @access public
         */
        public static function load_react_scripts( $args ) {

            global $wc_wholesale_prices;

            if ( isset( $args['dir_name'] ) ) {

                // JS Files.
                $js_path = $args['js_path'] . 'app/' . $args['dir_name'] . '/build/static/js';

                if ( file_exists( $js_path ) ) {

                    $js_files = scandir( $js_path );

                    if ( $js_files ) {
                        foreach ( $js_files as $key => $js_file ) {

                            // Get the extension using pathinfo.
                            $extension = pathinfo( $js_file, PATHINFO_EXTENSION );

                            if ( 'js' === $extension ) {
                                wp_enqueue_script( $args['handle'] . $key, $args['js_url'] . 'app/' . $args['dir_name'] . '/build/static/js/' . $js_file, array( 'jquery' ), $wc_wholesale_prices::VERSION, true );
                            }
                        }
                    }
                }

                // CSS Files.
                $css_path = $args['js_path'] . 'app/' . $args['dir_name'] . '/build/static/css';

                if ( file_exists( $css_path ) ) {

                    $css_files = scandir( $css_path );

                    if ( $css_files ) {

                        foreach ( $css_files as $key => $css_file ) {

                            // Get the extension using pathinfo.
                            $extension = pathinfo( $css_file, PATHINFO_EXTENSION );

                            if ( 'css' === $extension ) {
                                wp_enqueue_style( $args['handle'] . $key, $args['js_url'] . 'app/' . $args['dir_name'] . '/build/static/css/' . $css_file, array(), $wc_wholesale_prices::VERSION, 'all' );
                            }
                        }
                    }
                }
            }
        }

        /**
         * Check if current WWP is v2.0.
         * Thi is used in other WWS Plugis.
         *
         * @since  2.0
         * @access public
         */
        public static function is_wwp_v2() {

            if ( self::is_plugin_active( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' ) ) {

                if ( ! function_exists( 'get_plugin_data' ) ) {
                    require_once ABSPATH . '/wp-admin/includes/plugin.php';
                }

                $wwp_data = get_plugin_data( WWP_PLUGIN_PATH . 'woocommerce-wholesale-prices.bootstrap.php', false, false );

                if ( version_compare( $wwp_data['Version'], '2', '>=' ) ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Check the min version of WWS Premium Plugins.
         *
         * @param string $plugin  Plugin name.
         * @param string $version Plugin version.
         *
         * @since  2.0
         * @access public
         */
        public static function check_wws_plugin_min_version( $plugin, $version ) {

            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . '/wp-admin/includes/plugin.php';
            }

            switch ( $plugin ) {
                case 'wwof':
                    if ( self::is_plugin_active( 'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php' ) ) {

                        $data = get_plugin_data( WWOF_MAIN_PLUGIN_FILE_PATH, false, false );

                        if ( version_compare( $data['Version'], $version, '>=' ) ) {
                            return true;
                        }
                    }

                    break;

                case 'wwlc':
                    if ( self::is_plugin_active( 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php' ) ) {

                        $data = get_plugin_data( WWLC_MAIN_PLUGIN_FILE_PATH, false, false );

                        if ( version_compare( $data['Version'], $version, '>=' ) ) {
                            return true;
                        }
                    }

                    break;

                case 'wwpp':
                default:
                    if ( self::is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) ) {

                        $data = get_plugin_data( WWPP_MAIN_PLUGIN_FILE_PATH, false, false );

                        if ( version_compare( $data['Version'], $version, '>=' ) ) {
                            return true;
                        }
                    }
            }

            return false;
        }

        /**
         * Get WWS license data.
         *
         * @param string $software_key The software key to get license data.
         *
         * @since  2.1.10.2
         * @access public
         *
         * @return array
         */
        public static function get_wws_license_data( $software_key = '' ) {

            $license_data = is_multisite() ? (array) get_site_option( WWS_LICENSE_DATA, array() ) : (array) get_option( WWS_LICENSE_DATA, array() );

            if ( ! empty( $software_key && array_key_exists( $software_key, $license_data ) ) ) {
                $license_data = ! empty( $license_data[ $software_key ] ) ? $license_data[ $software_key ] : array();
            }

            return $license_data;
        }

        /**
         * Get license status i18n.
         *
         * @param string $status The license status to get i18n.
         *
         * @since  2.1.10.2
         * @access public
         *
         * @return string
         */
        public static function get_license_status_i18n( $status ) {

            switch ( $status ) {
                case 'active':
                    return __( 'Active', 'woocommerce-wholesale-prices' );
                case 'disabled':
                    return __( 'Disabled', 'woocommerce-wholesale-prices' );
                case 'invalid':
                    return __( 'Invalid', 'woocommerce-wholesale-prices' );
                case 'expired':
                    return __( 'Expired', 'woocommerce-wholesale-prices' );
            }
        }

        /**
         * Get formatted meta data.
         *
         * @param WC_Product $product  Product object.
         * @param string     $meta_key Meta key.
         *
         * @since  2.2.0
         * @access public
         *
         * @return array Formatted meta data.
         */
        public static function get_formatted_meta_data( $product, $meta_key ) {
            // Check if products is product object.
            if ( ! is_object( $product ) || ! $product instanceof WC_Product ) {
                return array();
            }

            $meta_datas          = $product->get_meta( $meta_key, false );
            $formatted_meta_data = array();
            if ( ! empty( $meta_datas ) ) {
                foreach ( $meta_datas as $meta ) {
                    $formatted_meta_data[] = $meta->value;
                }
            }

            return $formatted_meta_data;
        }

        /**
         * Checks if the page has a WooCommerce cart block.
         *
         * @since 2.2.0
         * @return bool
         */
        public static function has_wc_cart_block() {

            return has_block( 'woocommerce/cart' );
        }

        /**
         * Checks if the page has a WooCommerce checkout block.
         *
         * @since 2.2.0
         * @return bool
         */
        public static function has_wc_checkout_block() {

            return has_block( 'woocommerce/checkout' );
        }

        /**
         * Check if a submenu slug exists.
         *
         * @param string $parent_slug  Parent slug.
         * @param string $submenu_slug Submenu slug.
         *
         * @since 2.2.0
         * @return bool
         */
        public static function is_submenu_slug_exists( $parent_slug, $submenu_slug ) {

            global $submenu;
            if ( ! isset( $submenu[ $parent_slug ] ) ) {
                return false;
            }
            foreach ( $submenu[ $parent_slug ] as $submenu_item ) {
                if ( $submenu_slug === $submenu_item[2] ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Get the URL with UTM parameters.
         *
         * @param string $url_path     URL path from main.
         * @param string $utm_source   UTM source.
         * @param string $utm_medium   UTM medium.
         * @param string $utm_campaign UTM campaign.
         * @param string $site_url     URL - defaults to `https://wholesalesuiteplugin.com/`.
         *
         * @since 2.2.1
         * @return string
         */
        public static function get_utm_url( $url_path = '', $utm_source = 'wwp', $utm_medium = 'action', $utm_campaign = 'default', $site_url = 'https://wholesalesuiteplugin.com/' ) {

            $utm_content = get_option( 'wwp_installed_by', false );
            $url         = trailingslashit( $site_url ) . $url_path;

            return add_query_arg(
                array(
                    'utm_source'   => $utm_source,
                    'utm_medium'   => $utm_medium,
                    'utm_campaign' => $utm_campaign,
                    'utm_content'  => $utm_content,
                ),
                trailingslashit( $url )
            );
        }

        /**
         * Notification dismiss.
         *
         * @since 2.2.1
         * @access public
         *
         * @param int    $id  User ID.
         * @param string $key Notification key.
         *
         * @return bool
         */
        public static function is_user_wws_notification_dismissed( $id, $key ) {
			$userdata = get_user_meta( $id, '_wws_notifications_close', true );
			$userdata = empty( $userdata ) && ! is_array( $userdata ) ? array() : $userdata;

			return in_array( $key, $userdata, true );
		}

        /**
         * Check if a plugin exists on WordPress.org
         *
         * @param string $plugin_slug The plugin slug.
         *
         * @since 2.2.1
         * @access public
         *
         * @return bool
         */
        public static function plugin_exists_on_wporg( $plugin_slug ) {
            // These plugins are known to exist on WordPress.org.
            $known_plugins = array(
                'advanced-coupons-for-woocommerce-free' => true,
                'wc-vendors'                            => true,
                'storeagent-ai-for-woocommerce'         => true,
                'invoice-gateway-for-woocommerce'       => true,
                'woo-product-feed-pro'                  => true,
                'funnelkit-stripe-woo-payment-gateway'  => true,
                'woocommerce-store-toolkit'             => true,
                'woocommerce-exporter'                  => true,
                // Add more as needed.
            );

            // If it's a known plugin, return true.
            if ( isset( $known_plugins[ $plugin_slug ] ) ) {
                return true;
            }

            // For unknown plugins, we'll assume they exist.
            return true;
        }

        /**
         * Get the WordPress.org plugin icon URL
         *
         * @param string $plugin_slug The plugin slug.
         * @param int    $size        The icon size (default: 128).
         *
         * @since 2.2.1
         * @access public
         *
         * @return string
         */
        public static function get_wp_org_plugin_icon_url( $plugin_slug, $size = 128 ) {
            // Default fallback icon.
            $default_icon = WWP_IMAGES_URL . 'wws-marketing-logo.png';

            // If the plugin doesn't exist on WordPress.org, return the default icon.
            if ( ! self::plugin_exists_on_wporg( $plugin_slug ) ) {
                return $default_icon;
            }

            // Known plugins with specific icon formats.
            $icon_formats = array(
                'invoice-gateway-for-woocommerce' => 'jpg',
                // Add more as needed.
            );

            // Determine the file extension based on our known list or default to PNG.
            $extension = isset( $icon_formats[ $plugin_slug ] ) ? $icon_formats[ $plugin_slug ] : 'png';

            // Build the icon URL with dimensions.
            $icon_url = sprintf(
                'https://ps.w.org/%s/assets/icon-%dx%d.%s',
                $plugin_slug,
                $size,
                $size,
                $extension
            );

            return $icon_url;
        }
        /**
         * Verify an AJAX request by checking wp_doing_ajax(), nonce existence, and nonce validity.
         *
         * @param string $nonce_action The nonce action name to verify against.
         *
         * @since  2.2.7
         * @access public
         *
         * @return boolean True if the AJAX request passes all security checks, false otherwise.
         */
        public static function verify_ajax_nonce( $nonce_action ) {
            return wp_doing_ajax()
                && isset( $_POST['nonce'] )
                && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), $nonce_action );
        }

        /**
         * Collect wholesale prices per role from a variable product's child variations.
         *
         * Iterates through all child variation IDs, loads each child product, and
         * collects the `{role_key}_wholesale_price` meta value into an array keyed
         * by role. Returns an associative array where keys are role keys and values
         * are arrays of floats representing the wholesale prices found.
         *
         * @since 2.2.8
         *
         * @param array $children        Array of child variation product IDs.
         * @param array $wholesale_roles Array of registered wholesale roles keyed by role key.
         *
         * @return array Associative array of role_key => float[] wholesale prices.
         */
        public static function get_wholesale_prices_per_role_from_variations( $children, $wholesale_roles ) {

            $prices_per_role = array();

            foreach ( $children as $child_id ) {
                $child_product = wc_get_product( $child_id );

                if ( ! $child_product ) {
                    continue;
                }

                foreach ( $wholesale_roles as $role_key => $role ) {
                    $child_wholesale_price = $child_product->get_meta( $role_key . '_wholesale_price' );

                    if ( is_numeric( $child_wholesale_price ) && $child_wholesale_price > 0 ) {
                        $prices_per_role[ $role_key ][] = (float) $child_wholesale_price;
                    }
                }
            }

            return $prices_per_role;
        }

        /**
         * Sanitize a posted wholesale price or percentage value for storage.
         *
         * Delegates locale/number-format handling to WooCommerce's
         * {@see wc_format_decimal()}, which treats the last separator as the
         * decimal point. That avoids destroying values like "3.20" when the
         * shop thousand separator is also a period (EU-style number formats).
         *
         * @since 2.2.9
         *
         * @param mixed $value Raw posted value (string, number, or null).
         * @return string Empty string when empty/invalid, '0' when negative, otherwise a WC-formatted decimal string.
         */
        public static function sanitize_price_input( $value ) {
            if ( null === $value ) {
                return '';
            }

            $value = sanitize_text_field( wp_unslash( (string) $value ) );

            if ( '' === $value ) {
                return '';
            }

            $formatted = wc_format_decimal( $value );

            if ( '' === $formatted || ! is_numeric( $formatted ) ) {
                return '';
            }

            if ( (float) $formatted < 0 ) {
                return '0';
            }

            return $formatted;
        }
    }
}
