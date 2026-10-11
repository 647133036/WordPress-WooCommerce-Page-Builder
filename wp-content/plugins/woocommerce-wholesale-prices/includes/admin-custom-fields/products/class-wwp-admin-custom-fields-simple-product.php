<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Admin_Custom_Fields_Simple_Product' ) ) {

    /**
     * Model that houses logic  admin custom fields for simple products.
     *
     * @since 1.3.0
     */
    class WWP_Admin_Custom_Fields_Simple_Product {


        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWP_Admin_Custom_Fields_Simple_Product.
         *
         * @since 1.3.0
         * @access private
         * @var WWP_Admin_Custom_Fields_Simple_Product
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.3.0
         * @access private
         * @var WWP_Wholesale_Roles
         */
        private $_wwp_wholesale_roles;

        /**
         * Class Methods
         */

        /**
         * WWP_Admin_Custom_Fields_Simple_Product constructor.
         *
         * @since 1.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Admin_Custom_Fields_Simple_Product model.
         */
        public function __construct( $dependencies ) {
            $this->_wwp_wholesale_roles = $dependencies['WWP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWP_Admin_Custom_Fields_Simple_Product is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Admin_Custom_Fields_Simple_Product model.
         * @return WWP_Admin_Custom_Fields_Simple_Product
         */
        public static function instance( $dependencies ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /*
        |--------------------------------------------------------------------------
        | Quick Edit Fields
        |--------------------------------------------------------------------------
         */

        /**
         * Add wholesale custom form fields on the quick edit option.
         *
         * @since 1.0.0
         * @since 1.2.0 Add Aelia Currency Switcher Plugin Integration.
         * @since 1.3.0 Refactor codebase and move to its own model.
         * @access public
         */
        public function add_wholesale_price_fields_on_quick_edit_screen() {
            /**
             * Filter to show or hide wholesale prices on the WP Admin product editor.
             *
             * This allows integrations to hide/show the pricing fields conditionally.
             *
             * @param boolean $show Whether to show or hide pricing fields.
             * @return boolean $show
             */
            if ( ! apply_filters( 'wwp_product_editor_show_wholesale_prices_fields', true ) ) {
                return;
            }
            $all_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
            do_action( 'wwp_before_quick_edit_wholesale_price_fields', $all_wholesale_roles );?>

            <div class="quick_edit_wholesale_prices" style="float: none; clear: both; display: block;">
                <div style="height: 1px;"></div><!--To Prevent Heading From Bumping Up-->
                <h4><?php esc_html_e( 'Wholesale Price', 'woocommerce-wholesale-prices' ); ?></h4>

                <?php
                if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                    $woocommerce_currencies  = get_woocommerce_currencies(); // Get all woocommerce currencies.
                    $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies(); // Get all active currencies.

                    /*
                    * Here since we don't have access to post id, well just spit out all wholesale price key with currency code.
                    * We will just determine later dynamically via js which one is the base currency.
                    */

                    echo '<div class="wholesale-price-per-role-and-country-accordion">';

                    foreach ( $all_wholesale_roles as $roleKey => $role ) {

                        echo '<h4>' . esc_html( $role['roleName'] ) . '</h4>';
                        echo "<div class='section-container'>";

                        foreach ( $wacs_enabled_currencies as $currency_code ) {

                            $currency_symbol = get_woocommerce_currency_symbol( $currency_code );
                            $field_title     = $woocommerce_currencies[ $currency_code ] . ' (' . $currency_symbol . ')';
                            $field_name      = $roleKey . '_' . $currency_code . '_wholesale_price';

                            $this->_add_wholesale_price_fields_on_quick_edit_screen( $field_title, $field_name, 'Auto' );

                        }

                        echo '</div><!--.section-container-->';

                    }

                    echo '</div><!--.wholesale-price-per-role-and-country-accordion-->';

                } else {

                    foreach ( $all_wholesale_roles as $roleKey => $role ) {

                        $currency_symbol = get_woocommerce_currency_symbol();
                        if ( array_key_exists( 'currency_symbol', $role ) && ! empty( $role['currency_symbol'] ) ) {
                            $currency_symbol = $role['currency_symbol'];
                        }

                        /* translators: %1$s: wholesale role name, %2$s: currency symbol */
                        $field_title = sprintf( __( '%1$s Price (%2$s)', 'woocommerce-wholesale-prices' ), $role['roleName'], $currency_symbol );
                        $field_name  = $roleKey . '_wholesale_price';

                        $this->_add_wholesale_price_fields_on_quick_edit_screen( $field_title, $field_name );

                    }
                }
                ?>

                <div style="clear: both; float: none; display: block;"></div>
            </div>

            <?php if ( is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) ) : ?>
                <div class="quick_edit_wholesale_prices" style="float: none; clear: both; display: block;">
                    <div style="height: 1px;"></div><!--To Prevent Heading From Bumping Up-->
                    <h4><?php esc_html_e( 'Wholesale Sale Price', 'woocommerce-wholesale-prices' ); ?></h4>
                    <?php

                        foreach ( $all_wholesale_roles as $roleKey => $role ) {

                            $currency_symbol = get_woocommerce_currency_symbol();
                            if ( array_key_exists( 'currency_symbol', $role ) && ! empty( $role['currency_symbol'] ) ) {
                                $currency_symbol = $role['currency_symbol'];
                            }

                            /* translators: %1$s: wholesale role name, %2$s: currency symbol */
                            $field_title = sprintf( __( '%1$s Price (%2$s)', 'woocommerce-wholesale-prices' ), $role['roleName'], $currency_symbol );
                            $field_name  = $roleKey . '_wholesale_sale_price';

                            $this->_add_wholesale_price_fields_on_quick_edit_screen( $field_title, $field_name );

                        }
                    ?>
                    <div style="clear: both; float: none; display: block;"></div>
                </div>
            <?php endif; ?>

            <?php
            do_action( 'wwp_after_quick_edit_wholesale_price_fields', $all_wholesale_roles );
        }

        /**
         * Print custom wholesale price field on quick edit screen.
         *
         * @since 1.2.0
         * @since 1.3.0 Refactor codebase and move to its own model.
         * @access public
         *
         * @param string $field_title  Field title.
         * @param strin  $field_name   Field name.
         * @param string $place_holder Field placeholder.
         */
        private function _add_wholesale_price_fields_on_quick_edit_screen( $field_title, $field_name, $place_holder = '' ) {
            ?>

            <label class="alignleft" style="width: 100%;">
                <div class="title"><?php echo esc_html( $field_title ); ?></div>
                <input type="text" name="<?php echo esc_html( $field_name ); ?>" class="text wholesale_price wc_input_price" placeholder="<?php echo esc_attr( $place_holder ); ?>" value="">
            </label>

            <?php
        }

        /**
         * Save wholesale custom fields on the quick edit option.
         *
         * @since 1.0.0
         * @since 1.2.0 Add Aelia Currency Switcher Plugin Integration
         * @since 1.3.0 Refactor codebase and move to its own model.
         * @since 2.1.0 Add wholesale sale percentage discount support.
         * @since 2.2.9 Normalize the wholesale price and wholesale sale price via
         *              WWP_Helper_Functions::sanitize_price_input() so a comma
         *              decimal separator is converted correctly instead of being
         *              saved verbatim and truncated by later floatval() calls.
         * @access public
         *
         * @param WC_Product $product Product object.
         */
        public function save_wholesale_price_fields_on_quick_edit_screen( $product ) {
            $all_wholesale_roles   = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $post_id               = WWP_Helper_Functions::wwp_get_product_id( $product );
            $product_type          = WWP_Helper_Functions::wwp_get_product_type( $product );
            $allowed_product_types = apply_filters( 'wwp_quick_edit_allowed_product_types', array( 'simple', 'external' ), 'wholesale_price_fields' );

            if ( in_array( $product_type, $allowed_product_types, true ) && wp_verify_nonce( $_REQUEST['woocommerce_quick_edit_nonce'], 'woocommerce_quick_edit_nonce' ) ) { //phpcs:ignore

                $aelia_currency_switcher_active = WWP_ACS_Integration_Helper::aelia_currency_switcher_active();
                $thousand_sep                   = get_option( 'woocommerce_price_thousand_sep' );
                $decimal_sep                    = get_option( 'woocommerce_price_decimal_sep' );

                if ( $aelia_currency_switcher_active ) {

                    $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies(); // Get all active currencies.
                    $base_currency           = WWP_ACS_Integration_Helper::get_product_base_currency( $post_id ); // Get base currency. Product base currency ( if present ) or shop base currency.

                    foreach ( $all_wholesale_roles as $roleKey => $role ) {

                        foreach ( $wacs_enabled_currencies as $currency_code ) {

                            if ( $currency_code === $base_currency ) {

                                // Base Currency.
                                $wholesale_price_key = $roleKey . '_wholesale_price';
                                $is_base_currency    = true;

                            } else {

                                $wholesale_price_key = $roleKey . '_' . $currency_code . '_wholesale_price';
                                $is_base_currency    = false;

                            }

                            if ( isset( $_REQUEST[ $wholesale_price_key ] ) ) {

                                $has_wholesale_price_key = $roleKey . '_have_wholesale_price';
                                $this->_save_wholesale_price_fields( 'simple', $post_id, $roleKey, $wholesale_price_key, $has_wholesale_price_key, $thousand_sep, $decimal_sep, $aelia_currency_switcher_active, $is_base_currency, $currency_code );

                            }
                        }
                    }
                } else {

                    foreach ( $all_wholesale_roles as $roleKey => $role ) {

                        $wholesale_price_key = $roleKey . '_wholesale_price';

                        if ( isset( $_REQUEST[ $wholesale_price_key ] ) ) {

                            $has_wholesale_price_key = $roleKey . '_have_wholesale_price';
                            $this->_save_wholesale_price_fields( 'simple', $post_id, $roleKey, $wholesale_price_key, $has_wholesale_price_key, $thousand_sep, $decimal_sep );

                        }
                    }

                    if ( is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) ) {
                        // save wholesale_sale_price in post meta of product.
                        foreach ( $all_wholesale_roles as $roleKey => $role ) {

                            $wholesale_price_key             = $roleKey . '_wholesale_price';
                            $wholesale_sale_price_key        = $roleKey . '_wholesale_sale_price';
                            $wholesale_price                 = isset( $_REQUEST[ $wholesale_price_key ] ) ? WWP_Helper_Functions::sanitize_price_input( wp_unslash( $_REQUEST[ $wholesale_price_key ] ) ) : ''; //phpcs:ignore
                            $wholesale_sale_price            = isset( $_REQUEST[ $wholesale_sale_price_key ] ) ? WWP_Helper_Functions::sanitize_price_input( wp_unslash( $_REQUEST[ $wholesale_sale_price_key ] ) ) : ''; //phpcs:ignore
                            $check_wholesale_price_existence = isset( $_REQUEST[ $wholesale_price_key ] ) && ! empty( $_REQUEST[ $wholesale_price_key ] ) ? true : false; //phpcs:ignore

                            if ( ! $check_wholesale_price_existence ) { // if corresponding wholesale price is empty, set wholesale sale price to empty as well.
                                $product->update_meta_data( $wholesale_sale_price_key, '' );
                                $product->save();
                            } elseif ( isset( $_REQUEST[ $wholesale_sale_price_key ] ) && $check_wholesale_price_existence && $wholesale_price > $wholesale_sale_price ) { // if wholesale price exist and wholesale sale price also exist and wholesale price should be greater than wholesale sale price, set the wholesale sale price.
                                $product = wc_get_product( $post_id );
                                $product->update_meta_data( $wholesale_sale_price_key, $wholesale_sale_price );
                                $product->save();
                            }
                        }
                    }
                }
            }

            do_action( 'wwp_save_wholesale_price_fields_on_quick_edit_screen', $product, $post_id );
        }

        /**
         * This will be used by wwp-quick-edit.js file, Basically we are spitting out the value of wholesale fields on product listing.
         * The script then goes to those markup and extract the data and prepopulate the values of quick edit fields.
         * This is a hackish way to do it. We need to refactor this.
         *
         * @since 1.0.0
         * @since 1.2.0 Add Aelia Currency Switcher Plugin Integration.
         * @since 1.3.0 Refactor codebase and move to its own model.
         * @access public
         *
         * @param string $column  Column name.
         * @param int    $post_id Product Id.
         */
        public function add_wholesale_price_fields_data_to_product_listing_column( $column, $post_id ) {

            // Get product object.
            $product = wc_get_product( $post_id );

            $all_wholesale_roles   = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $allowed_product_types = apply_filters( 'wwp_quick_edit_allowed_product_types', array( 'simple', 'external' ), 'wholesale_price_fields' );

            switch ( $column ) {
                case 'name':
                ?>

                    <div class="hidden wholesale_prices_inline" id="wholesale_prices_inline_<?php echo esc_attr( $post_id ); ?>">

                        <div class="<?php echo 'wholesale_price_fields_allowed_product_types_' . esc_attr( $post_id ); ?>" data-product_types='<?php echo wp_json_encode( $allowed_product_types ); ?>'></div>

                        <?php
                        if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                        $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies(); // Get all active currencies.
                        $base_currency           = WWP_ACS_Integration_Helper::get_product_base_currency( $post_id ); // Get base currency. Product base currency ( if present ) or shop base currency.
                        ?>

                            <span class="hidden product_base_currency" id="product_base_currency_<?php echo esc_attr( $post_id ); ?>"><?php echo esc_html( $base_currency ); ?></span>

                            <?php
                            foreach ( $all_wholesale_roles as $roleKey => $role ) {

                                foreach ( $wacs_enabled_currencies as $currency_code ) {

                                    if ( $currency_code === $base_currency ) {

                                        // Base Currency.
                                        $wholesale_price_key                    = $roleKey . '_wholesale_price';
                                        $wholesale_price_key_with_currency_code = $roleKey . '_' . $currency_code . '_wholesale_price';

                                    } else {

                                        $wholesale_price_key                    = $roleKey . '_' . $currency_code . '_wholesale_price';
                                        $wholesale_price_key_with_currency_code = '';

                                    }
                                    ?>

                                    <div id="<?php echo esc_attr( $wholesale_price_key ); ?>" data-currency_code="<?php echo esc_attr( $currency_code ); ?>" data-wholesalePriceKeyWithCurrency="<?php echo esc_attr( $wholesale_price_key_with_currency_code ); ?>" class="whole_price"><?php echo esc_html( wc_format_localized_price( $product->get_meta( $wholesale_price_key, true ) ) ); ?></div>

                                <?php
                                }
                        }
                    } else {
                    ?>

                        <?php foreach ( $all_wholesale_roles as $roleKey => $role ) { ?>

                            <div id="<?php echo esc_attr( $roleKey ); ?>_wholesale_price" class="whole_price"><?php echo esc_attr( wc_format_localized_price( $product->get_meta( $roleKey . '_wholesale_price', true ) ) ); ?></div>

                        <?php
                        }
                        // for wholesale sale prices data.
                        foreach ( $all_wholesale_roles as $roleKey => $role ) {
                            ?>
                                <div id="<?php echo esc_attr( $roleKey ); ?>_wholesale_sale_price" class="whole_sale_price"><?php echo esc_attr( wc_format_localized_price( $product->get_meta( $roleKey . '_wholesale_sale_price', true ) ) ); ?></div>
                            <?php
                        }
                    }
                    ?>

                        <?php do_action( 'wwp_add_wholesale_price_fields_data_to_product_listing_column', $all_wholesale_roles, $post_id ); ?>

                    </div><!--.wholesale_prices_inline-->

                    <?php
                    break;

                default:
                    break;

            } // switch
        }

        /*
        |--------------------------------------------------------------------------
        | Wholesale Price Field
        |--------------------------------------------------------------------------
         */

        /**
         * Maybe we should add wholesale price field on this product.
         * We need to do this, else all other product types that inherits from simple products will automatically have wholesale pricing fields added to them.
         *
         * @since 1.13.0
         * @since 2.1.5  Fix Wholesale Prices fields won't show when changing product type from variable to simple
         * @since 2.2.1  Add WC Subscription support.
         * @access public
         */
        public function maybe_add_wholesale_price_fields() {
            global $post;

            $product = wc_get_product( $post->ID );

            $product_types = array( 'simple', 'variable' );

            // Check if WC Subscription is active.
            $wc_subscription_active = is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ? true : false;
            if ( $wc_subscription_active ) {
                $product_types[] = 'subscription';
                $product_types[] = 'variable-subscription';
            }

            // Added variable product type, so the wholesale price field will be executed when the inital product type is variable then changed to simple product.
            if ( in_array( WWP_Helper_Functions::wwp_get_product_type( $product ), $product_types, true ) ) {
                $this->add_wholesale_price_fields();
            }
        }

        /**
         * Add wholesale custom price field to simple product edit page.
         * Note this also adds these custom fields to external products that closely similar simple products since we used the more generic 'woocommerce_product_options_pricing' hook.
         *
         * @since 1.0.0
         * @since 1.2.0 Add Aelia Currency Switcher Plugin Integratio
         * @since 1.3.0 Refactor codebase, move it on its own model.
         * @since 2.1.0 Add additional fields to support wholesale percentage discount.
         * @since 2.2.1 Add WC Subscription support.
         * @access public
         *
         * @param string $product_type Product type.
         */
        public function add_wholesale_price_fields( $product_type = 'simple' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            global $post, $WOOCS, $woocommerce_wpml;

            /**
             * Filter to show or hide wholesale prices on the WP Admin product editor.
             *
             * This allows integrations to hide/show the pricing fields conditionally.
             *
             * @param boolean $show Whether to show or hide pricing fields.
             * @return boolean $show
             */
            if ( ! apply_filters( 'wwp_product_editor_show_wholesale_prices_fields', true ) ) {
                return;
            }

            // Get product object.
            $product = wc_get_product( $post->ID );

            $all_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                $wc_currencies           = get_woocommerce_currencies(); // Get all woocommerce currencies.
                $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies(); // Get all active currencies.
                $base_currency           = WWP_ACS_Integration_Helper::get_product_base_currency( $post->ID ); // Get base currency. Product base currency ( if present ) or shop base currency.
                ?>

                <div class="wholesale-prices-options-group options-group options_group hide_if_advanced_gift_card hide_if_external" style="border-top: 1px solid #EEEEEE;">

                    <header>
                        <h3 style="padding-bottom: 10px;"><?php esc_html_e( 'Wholesale Prices', 'woocommerce-wholesale-prices' ); ?></h3>
                        <p style="margin:0; padding:0 12px; line-height: 16px; font-style: italic; font-size: 13px;">
                            <?php
                                /* translators: %1$s: HTML tag (<br/>), %2$s: HTML tag (<b>), %3$s: HTML tag (</b>) */
                                printf( esc_html__( 'Wholesale prices are set per role and currency.%1$s%1$s%2$sNote:%3$s Wholesale price must be set for the base currency to enable wholesale pricing for that role. The base currency will be used for conversion to other currencies that have no wholesale price explicitly set (Auto).', 'woocommerce-wholesale-prices' ), '<br/>', '<b>', '</b>' );
                            ?>
                        </p>
                    </header>

                    <div class="wholesale-price-per-role-and-country-accordion">

                        <?php
                        foreach ( $all_wholesale_roles as $role_key => $role ) {

                            // Get base currency currency symbol.
                            $currency_symbol = get_woocommerce_currency_symbol( $base_currency );
                            if ( array_key_exists( 'currency_symbol', $role ) && ! empty( $role['currency_symbol'] ) ) {
                                $currency_symbol = $role['currency_symbol'];
                            }

                            $wholesale_price = $product->get_meta( $role_key . '_wholesale_price', true ); // Get base currency wholesale price.
                            $field_id        = $role_key . '_wholesale_price';
                            $field_label     = WWP_Helper_Functions::wwp_get_aelia_base_currency_field_label( $wc_currencies[ $base_currency ], $currency_symbol );
                            /* translators: %1$s: Wholesale role name,%2$s: currency name and symbol */
                            $field_desc = sprintf( __( 'Only applies to users with the role of %1$s for %2$s currency', 'woocommerce-wholesale-prices' ), $role['roleName'], $wc_currencies[ $base_currency ] . ' (' . $currency_symbol . ')' );
                            ?>

                            <h4><?php echo esc_html( $role['roleName'] ); ?></h4>
                            <div class="section-container">
                                <?php
                                // Always put the base currency on top of the list.
                                woocommerce_wp_text_input(
                                    array(
										'id'          => $field_id,
										'class'       => $role_key . '_wholesale_price wholesale_price short',
										'label'       => $field_label,
										'placeholder' => '',
										'desc_tip'    => 'true',
										'description' => $field_desc,
										'data_type'   => 'price',
										'value'       => $wholesale_price,
                                    )
                                );

                                do_action( 'wwp_after_wacs_simple_wholesale_price_field', $post->ID, $role, $role_key, $currency_symbol, $base_currency, $wholesale_price );

                                foreach ( $wacs_enabled_currencies as $currency_code ) {

                                    if ( $currency_code === $base_currency ) {
                                        continue;
                                    }
                                    // Base currency already processed above.

                                    $currency_symbol = get_woocommerce_currency_symbol( $currency_code );

                                    $wholesale_price_for_specific_currency = $product->get_meta( $role_key . '_' . $currency_code . '_wholesale_price', true );
                                    $field_id                              = $role_key . '_' . $currency_code . '_wholesale_price';
                                    $field_label                           = $wc_currencies[ $currency_code ] . ' (' . $currency_symbol . ')';
                                    /* translators: %1$s: Wholesale role name,%2$s: currency name and symbol */
                                    $field_desc = sprintf( __( 'Only applies to users with the role of %1$s for %2$s currency', 'woocommerce-wholesale-prices' ), $role['roleName'], $wc_currencies[ $currency_code ] . ' (' . $currency_symbol . ')' );

                                    woocommerce_wp_text_input(
                                        array(
											'id'          => $field_id,
											'class'       => $role_key . '_wholesale_price wholesale_price short',
											'label'       => $field_label,
											'placeholder' => __( 'Auto', 'woocommerce-wholesale-prices' ),
											'desc_tip'    => 'true',
											'description' => $field_desc,
											'data_type'   => 'price',
											'value'       => $wholesale_price_for_specific_currency,
                                        )
                                    );

                                    do_action( 'wwp_after_wacs_simple_wholesale_price_field', $post->ID, $role, $role_key, $currency_symbol, $currency_code, $wholesale_price );
                                }
                                ?>

                            </div><!-- .section-contianer -->

                        <?php } ?>

                    </div><!--.wholesale-price-per-role-and-country-accordion-->

                </div><!--.options_group-->

            <?php
            } else {
                // Check if WC Subscription is active.
                $wc_subscription_active = is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ? true : false;
                $wwpp_active            = is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) ? true : false;
                ?>

                <div class="wholesale-prices-options-group options-group options_group hide_if_advanced_gift_card hide_if_external" style="border-top: 1px solid #EEEEEE;">

                    <header>
                        <h3 style="padding-bottom: 10px;"><?php esc_html_e( 'Wholesale Prices', 'woocommerce-wholesale-prices' ); ?></h3>
                        <p style="margin:0; padding:0 12px; line-height: 16px; font-style: italic; font-size: 13px;">
                            <?php esc_html_e( 'Set a wholesale price for this product.', 'woocommerce-wholesale-prices' ); ?>
                            <?php echo $wwpp_active ? '' : '<a href="#" class="price-levels">' . esc_html__( 'Add additional wholesale price levels.', 'woocommerce-wholesale-prices' ) . '</a>'; ?>
                        </p>
                    </header>

                    <?php
                    foreach ( $all_wholesale_roles as $role_key => $role ) {

                        $currency_symbol = get_woocommerce_currency_symbol();
                        if ( array_key_exists( 'currency_symbol', $role ) && ! empty( $role['currency_symbol'] ) ) {
                            $currency_symbol = $role['currency_symbol'];
                        }

                        $wholesale_price = $product->get_meta( $role_key . '_wholesale_price', true );
                        $field_id        = $role_key . '_wholesale_price';

                        /* translators: %1$s: currency symbol */
                        $field_label = sprintf( __( 'Wholesale Price (%1$s)', 'woocommerce-wholesale-prices' ), $currency_symbol );

                        /* translators: %1$s: Wholesale role name */
                        $field_desc       = sprintf( __( 'Wholesale price for %1$s customers', 'woocommerce-wholesale-prices' ), str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] ) );
                        $field_desc_fixed = $field_desc;

                        /* translators: %1$s: Wholesale role name, %2$s: HTML tag (<br/>) */
                        $field_desc_percentage = sprintf( __( 'Wholesale price for %1$s customers %2$s Note: Prices are shown up to 6 decimal places but may be calculated and stored at higher precision.', 'woocommerce-wholesale-prices' ), str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] ), '<br/>' );

                        // Percentage Discount.
                        $wholesale_percentage_discount = $product->get_meta( $role_key . '_wholesale_percentage_discount', true );

                        $discount_type = metadata_exists( 'post', $post->ID, $role_key . '_wholesale_percentage_discount' ) ? 'percentage' : 'fixed';

                        if ( 'percentage' === $discount_type ) {
                            $field_desc = $field_desc_percentage;
                        }

                        ?>
                        <div class="wholesale-prices-field wholesale-prices-field--simple">
                            <div class="wholesale-prices-field-role-name">
                                <?php echo esc_html( $role['roleName'] ); ?>
                            </div>
                            <div class="wholesale-prices-field-form-field-container">
                                <?php
                                    woocommerce_wp_select(
                                        array(
											'id'          => $role_key . '_wholesale_discount_type',
											'class'       => 'wholesale_discount_type select',
											'label'       => __( 'Discount Type', 'woocommerce-wholesale-prices' ),
											'value'       => $discount_type,
											'options'     => array(
												'fixed' => __( 'Fixed', 'woocommerce-wholesale-prices' ),
												'percentage' => __( 'Percentage', 'woocommerce-wholesale-prices' ),
											),
											'desc_tip'    => 'true',
                                            /* translators: %1$s: HTML tag (<br/>) */
											'description' => sprintf( __( 'Choose Price Type%1$sFixed (default)%1$sPercentage', 'woocommerce-wholesale-prices' ), '<br/>' ),
											'custom_attributes' => array(
												'data-wholesale_role' => $role_key,
											),
                                        )
                                    );

                                    woocommerce_wp_text_input(
                                        array(
											'id'          => $role_key . '_wholesale_percentage_discount',
											'class'       => 'wholesale_discount',
											'label'       => __( 'Discount (%)', 'woocommerce-wholesale-prices' ),
											'placeholder' => '',
											'desc_tip'    => 'true',
											'description' => __( 'The percentage amount discounted from the regular price', 'woocommerce-wholesale-prices' ),
											'data_type'   => 'price',
											'value'       => $wholesale_percentage_discount,
											'custom_attributes' => array(
												'data-wholesale_role' => $role_key,
											),
                                        )
                                    );

                                woocommerce_wp_text_input(
                                    array(
										'id'          => $field_id,
										'class'       => $role_key . '_wholesale_price wholesale_price',
										'label'       => $field_label,
										'placeholder' => '',
										'desc_tip'    => 'true',
										'description' => $field_desc,
										'data_type'   => 'price',
										'value'       => $wholesale_price,
										'custom_attributes' => array(
											'data-field_desc_fixed' => html_entity_decode( $field_desc_fixed ),
											'data-field_desc_percentage' => html_entity_decode( $field_desc_percentage ),
										),
                                    )
                                );

                                // Add signup fee field if WC Subscription is active.
                                if ( $wc_subscription_active && $wwpp_active ) {
                                    $signup_fee          = $product->get_meta( $role_key . '_wholesale_signup_fee', true );
                                    $field_signup_fee_id = $role_key . '_wholesale_signup_fee';
                                    /* translators: %1$s: currency symbol */
                                    $field_signup_fee_label = sprintf( __( 'Wholesale Signup Fee (%1$s)', 'woocommerce-wholesale-prices' ), $currency_symbol );
                                    /* translators: %1$s: Wholesale role name */
                                    $field_signup_fee_desc = sprintf( __( 'Wholesale Signup Fee for %1$s customers', 'woocommerce-wholesale-prices' ), str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] ) );

                                    $signup_fee_discount          = $product->get_meta( $role_key . '_wholesale_signup_fee_discount', true );
                                    $field_signup_fee_discount_id = $role_key . '_wholesale_signup_fee_discount';
                                    /* translators: %1$s: currency symbol */
                                    $field_signup_fee_discount_label = __( 'Signup Fee Discount (%)', 'woocommerce-wholesale-prices' );
                                    /* translators: %1$s: Wholesale role name */
                                    $field_signup_fee_discount_desc = sprintf( __( 'Wholesale Signup Fee for %1$s customers', 'woocommerce-wholesale-prices' ), str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] ) );

                                    woocommerce_wp_text_input(
                                        array(
											'id'          => $field_signup_fee_discount_id,
											'class'       => $role_key . '_wholesale_signup_fee_discount wholesale_signup_fee_discount',
											'label'       => $field_signup_fee_discount_label,
											'placeholder' => '',
											'desc_tip'    => 'true',
											'description' => __( 'The percentage amount discounted from the Sign-up fee', 'woocommerce-wholesale-prices' ),
											'data_type'   => 'price',
											'value'       => $signup_fee_discount,
                                            'custom_attributes' => array(
												'data-wholesale_role' => $role_key,
											),
                                        )
                                    );

                                    woocommerce_wp_text_input(
                                        array(
											'id'          => $field_signup_fee_id,
											'class'       => $role_key . '_wholesale_signup_fee wholesale_signup_fee wholesale_price',
											'label'       => $field_signup_fee_label,
											'placeholder' => '',
											'desc_tip'    => 'true',
											'description' => $field_signup_fee_desc,
											'data_type'   => 'price',
											'value'       => $signup_fee,
                                        )
                                    );
                                }

                                do_action( 'wwp_after_simple_wholesale_price_field', $post->ID, $role, $role_key, $currency_symbol, $wholesale_price, $discount_type, $wholesale_percentage_discount );
                                ?>
                            </div>
                        </div>

                    <?php } ?>

                </div><!--.options_group-->
            <?php
            }
        }

        /**
         * Save wholesale custom price field on simple products.
         *
         * @since 1.0.0
         * @since 1.2.0 Add Aelia Currency Switcher Plugin Integration.
         * @since 1.3.0 Refactor codebase, and move to its own model.
         *
         * @param int    $post_id      Product id.
         * @param string $product_type Product type.
         */
        public function save_wholesale_price_fields( $post_id, $product_type = 'simple' ) {
            $all_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $thousand_sep        = get_option( 'woocommerce_price_thousand_sep' );
            $decimal_Sep         = get_option( 'woocommerce_price_decimal_sep' );

            $aelia_currency_switcher_active = WWP_ACS_Integration_Helper::aelia_currency_switcher_active();

            // Check the nonce.
            if ( empty( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) { //phpcs:ignore
                return;
            }

            if ( $aelia_currency_switcher_active ) {

                $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies(); // Get all active currencies.
                $base_currency           = WWP_ACS_Integration_Helper::get_product_base_currency( $post_id ); // Get base currency. Product base currency ( if present ) or shop base currency.

                foreach ( $all_wholesale_roles as $role_key => $role ) {

                    foreach ( $wacs_enabled_currencies as $currency_code ) {

                        if ( $currency_code === $base_currency ) {

                            // Base currency.
                            $wholesale_price_key = $role_key . '_wholesale_price';
                            $is_base_currency    = true;

                        } else {

                            $wholesale_price_key = $role_key . '_' . $currency_code . '_wholesale_price';
                            $is_base_currency    = false;

                        }

                        $has_wholesale_price_key = $role_key . '_have_wholesale_price';

                        $this->_save_wholesale_price_fields( $product_type, $post_id, $role_key, $wholesale_price_key, $has_wholesale_price_key, $thousand_sep, $decimal_Sep, $aelia_currency_switcher_active, $is_base_currency, $currency_code );

                    }
                }
            } else {

                foreach ( $all_wholesale_roles as $role_key => $role ) {

                    $wholesale_price_key     = $role_key . '_wholesale_price';
                    $has_wholesale_price_key = $role_key . '_have_wholesale_price';

                    $this->_save_wholesale_price_fields( $product_type, $post_id, $role_key, $wholesale_price_key, $has_wholesale_price_key, $thousand_sep, $decimal_Sep );

                }
            }
        }

        /**
         * Save simple product wholesale price.
         *
         * @since 1.2.0
         * @since 1.3.0 Refactor codebase, move to its own model.
         * @since 2.1.0 Added wholesale discount type per product level
         *
         * @param string  $product_type                   Product type.
         * @param int     $post_id                        Product id.
         * @param string  $role_key                       Wholesale role key.
         * @param string  $wholesale_price_key            Wholesale price key. Wholesale role key + '_wholesale_price'.
         * @param string  $has_wholesale_price_key        Has wholesle price key. Wholesale role key + '_have_wholesale_price'.
         * @param string  $thousand_sep                   Thousand separator.
         * @param string  $decimal_sep                    Decimal separator.
         * @param boolean $aelia_currency_switcher_active Flag that detemines if aelia currency switcher is active or not.
         * @param boolean $is_base_currency               Flag that determines if this is a base currency.
         * @param mixed   $currency_code                  String of current currency code or null.
         */
        private function _save_wholesale_price_fields( $product_type, $post_id, $role_key, $wholesale_price_key, $has_wholesale_price_key, $thousand_sep, $decimal_sep, $aelia_currency_switcher_active = false, $is_base_currency = false, $currency_code = null ) {
            // Get the product object.
            $product = wc_get_product( $post_id );

            // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce already checked before the function called
            $has_wholesale_discount_key = $role_key . '_wholesale_discount_type';

            /**
             * Sanitize and properly format wholesale price / percentage.
             *
             * Uses WC number formatting (last separator = decimal) so values like
             * "3.20" are not corrupted when the shop thousand separator is ".".
             *
             * Note: $thousand_sep and $decimal_sep remain in the method signature so
             * existing call sites stay unchanged; separator handling is owned by
             * WWP_Helper_Functions::sanitize_price_input() / wc_format_decimal().
             *
             * @see https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/955
             */
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- kept for call-site BC.
            $wholesale_discount_type = isset( $_POST[ $has_wholesale_discount_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $has_wholesale_discount_key ] ) ) : ( ! empty( $product->get_meta( $role_key . '_wholesale_percentage_discount', true ) ) ? 'percentage' : '' ); //phpcs:ignore

            // Helper also unslashes; wp_unslash here for PHPCS WordPress.Security.ValidatedSanitizedInput.
            $wholesale_price = isset( $_POST[ $wholesale_price_key ] ) ? WWP_Helper_Functions::sanitize_price_input( wp_unslash( $_POST[ $wholesale_price_key ] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            $wholesale_price = wc_clean( apply_filters( 'wwp_before_save_' . $product_type . '_product_wholesale_price', $wholesale_price, $role_key, $post_id, $aelia_currency_switcher_active, $is_base_currency, $currency_code ) );

            $product->update_meta_data( $wholesale_price_key, $wholesale_price );

            if ( 'percentage' === $wholesale_discount_type ) {
                $raw_discount = isset( $_POST[ $role_key . '_wholesale_percentage_discount' ] ) //phpcs:ignore WordPress.Security.NonceVerification.Missing
                    ? wp_unslash( $_POST[ $role_key . '_wholesale_percentage_discount' ] ) //phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    : ( ! empty( $product->get_meta( $role_key . '_wholesale_percentage_discount', true ) ) ? $product->get_meta( $role_key . '_wholesale_percentage_discount', true ) : '' );

                $wholesale_discount = WWP_Helper_Functions::sanitize_price_input( $raw_discount );

                $product->update_meta_data( $role_key . '_wholesale_percentage_discount', trim( esc_attr( $wholesale_discount ) ) );
            } else {
                $product->delete_meta_data( $role_key . '_wholesale_percentage_discount' );
            }

            // WWPP-147 : Delete the meta that is set when setting discount on per product category level.
            $product->delete_meta_data( $role_key . '_have_wholesale_price_set_by_product_cat' );

            // Mark current simple product if having wholesale price or not.
            if ( is_numeric( $wholesale_price ) && $wholesale_price > 0 ) {

                if ( $aelia_currency_switcher_active ) {

                    // Only base currency custom wholesale price field has the power to determine if a product has wholesale price or not.
                    // Coz if wholesale price is not set for base currency, then even if user set wholesale pricing for other currencies
                    // then it will not matter. The product is still considered to not having wholesale price.
                    if ( $is_base_currency ) {
                        $product->update_meta_data( $has_wholesale_price_key, 'yes' );
                    }
                } else {
                    $product->update_meta_data( $has_wholesale_price_key, 'yes' );
                }
            } else {
                $product->update_meta_data( $has_wholesale_price_key, 'no' );
                do_action( 'wwp_set_have_wholesale_price_meta_prod_cat_wholesale_discount', $post_id, $role_key );
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing

            // Save the product.
            $product->save();
        }

        /**
         * Show/hide wholesale order quantity based wholesale pricing custom fields.
         *
         * @since 2.0.2
         *
         * @param array $classes Array of classes.
         */
        public function filter_admin_custom_field_wholesale_quantity_based_visibility_clasess( $classes ) {
            $classes[] = 'hide_if_advanced_gift_card ';
            $classes[] = 'hide_if_external';
            return $classes;
        }

        /**
         * Show/hide wholesale minimum order quantity custom fields.
         *
         * @since 2.0.2
         *
         * @param array $classes Array of classes.
         */
        public function filter_admin_custom_field_wholesale_min_order_quantity_visibility_clasess( $classes ) {
            $classes[] = 'hide_if_advanced_gift_card ';
            $classes[] = 'hide_if_external';
            return $classes;
        }

        /**
         * Show/hide wholesale order quantity step custom field
         *
         * @since 2.0.2
         *
         * @param array $classes Array of classes.
         */
        public function filter_admin_custom_field_wholesale_order_quantity_step_visibility_clasess( $classes ) {
            $classes[] = 'hide_if_advanced_gift_card ';
            $classes[] = 'hide_if_external';
            return $classes;
        }

        /**
         * Add wholesale sale price dummy field on the simple product edit page.
         *
         * @since 2.1.6
         *
         * @param array  $post_id                       Product post ID.
         * @param array  $role                          Wholesale role array.
         * @param string $role_key                      Wholesale role key.
         * @param string $currency_symbol               Currency symbol.
         * @param int    $wholesale_price               The wholesale price.
         * @param string $discount_type                 The discount type (fixed | percentage).
         * @param int    $wholesale_percentage_discount The Wholesale percentage discount value.
         */
        public function add_wholesale_sale_price_dummy_fields( $post_id, $role, $role_key, $currency_symbol, $wholesale_price, $discount_type, $wholesale_percentage_discount ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

            global $WOOCS, $woocommerce_wpml;

            if ( empty( $WOOCS ) && empty( $woocommerce_wpml ) ) {
                woocommerce_wp_text_input(
                    array(
						'id'                => $role_key . '_wholesale_sale_discount_dummy',
						'class'             => $role_key . '_wholesale_sale_discount wholesale_sale_discount',
						'label'             => __( 'Sale Discount (%)', 'woocommerce-wholesale-prices' ),
						'placeholder'       => '',
						'desc_tip'          => 'true',
						'description'       => __( 'The percentage amount discounted from the wholesale price', 'woocommerce-wholesale-prices' ),
						'data_type'         => 'price',
						'custom_attributes' => array(
							'data-wholesale_role' => $role_key,
						),
                    )
                );
            }

            woocommerce_wp_text_input(
                array(
					'id'          => $role_key . '_wholesale_sale_price_dummy',
					'class'       => $role_key . '_wholesale_sale_price wholesale_sale_price',
					/* translators: %s: currency symbol */
					'label'       => sprintf( __( 'Wholesale Sale Price (%1$s)', 'woocommerce-wholesale-prices' ), $currency_symbol ),
					'placeholder' => '',
					'description' => '<a href="#" class="wholesale_sale_schedule">' . __( 'Schedule', 'woocommerce-wholesale-prices' ) . '</a>',
					'data_type'   => 'price',
                )
            );

            echo '<p class="form-field ' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_fields wholesale_sale_price_dates_fields hidden">
				<label for="wholesale__sale_price_dates_from">' . esc_html__( 'Sale price dates', 'woocommerce-wholesale-prices' ) . '</label>
				<input type="text" name="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_from" id="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_from" value="" placeholder="' . esc_html( _x( 'From&hellip;', 'placeholder', 'woocommerce-wholesale-prices' ) ) . ' YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
				<input type="text" name="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_to" id="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_to" value="" placeholder="' . esc_html( _x( 'To&hellip;', 'placeholder', 'woocommerce-wholesale-prices' ) ) . '  YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
				<a href="#" class="description cancel_wholesale_sale_schedule">' . esc_html__( 'Cancel', 'woocommerce-wholesale-prices' ) . '</a>' . wp_kses_post( wc_help_tip( __( 'The sale will start at 00:00:00 of "From" date and end at 23:59:59 of "To" date.', 'woocommerce-wholesale-prices' ) ) ) . '
			</p>';
        }

        /**
         * Execute the model.
         *
         * @since 1.3.0
         */
        public function run() {
            // Quick edit fields.
            add_action( 'woocommerce_product_quick_edit_end', array( $this, 'add_wholesale_price_fields_on_quick_edit_screen' ), 10 );
            add_action( 'woocommerce_product_quick_edit_save', array( $this, 'save_wholesale_price_fields_on_quick_edit_screen' ), 10, 1 );
            add_action( 'manage_product_posts_custom_column', array( $this, 'add_wholesale_price_fields_data_to_product_listing_column' ), 99, 2 );

            // Wholesale price fields.
            add_action( 'woocommerce_product_options_pricing', array( $this, 'maybe_add_wholesale_price_fields' ), 11 );
            add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_wholesale_price_fields' ), 10, 1 );

            // Show/hide wholesale custom fields.
            add_filter( 'wwpp_filter_admin_custom_field_wholesale_quantity_based_visibility_clasess', array( $this, 'filter_admin_custom_field_wholesale_quantity_based_visibility_clasess' ), 11 );
            add_filter( 'wwpp_filter_admin_custom_field_wholesale_min_order_quantity_visibility_clasess', array( $this, 'filter_admin_custom_field_wholesale_min_order_quantity_visibility_clasess' ), 11 );
            add_filter( 'wwpp_filter_admin_custom_field_wholesale_order_quantity_step_visibility_clasess', array( $this, 'filter_admin_custom_field_wholesale_order_quantity_step_visibility_clasess' ), 11 );

            if ( ! WWP_Helper_Functions::is_wwpp_active() ) {
                // Wholesale sale price dummy fields.
                add_action( 'wwp_after_simple_wholesale_price_field', array( $this, 'add_wholesale_sale_price_dummy_fields' ), 10, 7 );
            }
        }
    }

}
