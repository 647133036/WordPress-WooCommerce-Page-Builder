<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Admin_Custom_Fields_Variable_Product' ) ) {

    /**
     * Model that houses logic  admin custom fields for variable products.
     *
     * @since 1.3.0
     */
    class WWP_Admin_Custom_Fields_Variable_Product {

        /**
         *  Class Properties
         */

        /**
         * Property that holds the single main instance of WWP_Admin_Custom_Fields_Variable_Product.
         *
         * @since 1.3.0
         * @access private
         * @var WWP_Admin_Custom_Fields_Variable_Product
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
         * WWP_Admin_Custom_Fields_Variable_Product constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Admin_Custom_Fields_Variable_Product model.
         *
         * @since 1.3.0
         * @access public
         */
        public function __construct( $dependencies ) {
            $this->_wwp_wholesale_roles = $dependencies['WWP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWP_Admin_Custom_Fields_Variable_Product is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Admin_Custom_Fields_Variable_Product model.
         *
         * @return WWP_Admin_Custom_Fields_Variable_Product
         * @since 1.3.0
         * @access public
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /*
        |------------------------------------------------------------------------------------------------------------------
        | Variable Product Custom Bulk Action ( Single Product Page )
        |------------------------------------------------------------------------------------------------------------------
         */

        /**
         * Add variation custom bulk action options.
         *
         * @since 1.2.3
         * @since 1.3.0 Refactor codebase and move to its dedicated model.
         * @access public
         */
        public function add_variation_custom_wholesale_bulk_action_options() {

            $all_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            ob_start(); ?>

            <optgroup label="<?php esc_attr_e( 'Wholesale', 'woocommerce-wholesale-prices' ); ?>">

                <?php
                    foreach ( $all_wholesale_roles as $role_key => $role ) {
                        /* translators: %1$s: wholesale role name */
                        echo '<option value="' . esc_attr( $role_key ) . '_wholesale_price">' . esc_attr( sprintf( __( 'Set wholesale prices (%1$s)', 'woocommerce-wholesale-prices' ), $role['roleName'] ) ) . '</option>';
                    }

                do_action( 'wwp_custom_variation_bulk_action_options', $all_wholesale_roles );
                ?>

            </optgroup>

            <?php do_action( 'wwp_custom_variation_group_bulk_action_options', $all_wholesale_roles ); ?>

            <?php
            echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput
        }

        /**
         * Execute variation custom bulk actions.
         *
         * @param string $bulk_action The current bulk action being executed.
         * @param array  $data Array of data passed.
         * @param int    $product_id Variable product id.
         * @param array  $variations Array of variation ids.
         *
         * @since 1.3.0 Refactor codebase and move to its own model.
         * @since 1.6.4 Only set base currency price when setting bulk price set for variations (WWP-155).
         * @access public
         *
         * @since 1.2.3
         */
        public function execute_variation_custom_wholesale_bulk_actions( $bulk_action, $data, $product_id, $variations ) {

            if ( str_contains( $bulk_action, '_wholesale_price' ) && is_array( $variations ) && isset( $data['value'] ) ) {

                $all_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
                $wholesale_role      = str_replace( '_wholesale_price', '', $bulk_action );
                $wholesale_role_arr  = array( $wholesale_role => $all_wholesale_roles[ $wholesale_role ] );

                $variation_ids    = array();
                $wholesale_prices = array();

                foreach ( $variations as $variationId ) {

                    $variation_ids[]    = $variationId;
                    $wholesale_prices[] = $data['value'];

                }

                // We only set the base currency.
                $this->save_wholesale_price_fields( $product_id, $wholesale_role_arr, $variation_ids, $wholesale_prices, true );

            }
        }

        /*
        |--------------------------------------------------------------------------
        | Wholesale Price Field
        |--------------------------------------------------------------------------
         */

        /**
         * Add wholesale custom price field to variable product edit page (on the variations section).
         *
         * @param int     $loop Variation loop count.
         * @param array   $variation_data Array of variation data.
         * @param WP_Post $variation Variation object.
         *
         * @since 1.0.0
         * @since 1.2.0 Add integration with Aelia Currency Switcher Plugin.
         * @since 1.3.0 Refactor codebase, and move to its own model.
         * @since 2.1.0 Added support for wholesale percentage discount.
         */
        public function add_wholesale_price_fields( $loop, $variation_data, $variation ) {

            global $woocommerce, $post, $WOOCS, $woocommerce_wpml;

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

            /**
             * Get the variable product data manually
             * Don't rely on the variation data woocommerce supplied
             * There is a logic change introduced on 2.3 series where they only send variation data (or variation meta)
             * That is built in to woocommerce, so all custom variation meta added to a variable product don't get passed along
             */
            $product_variation_object = wc_get_product( $variation->ID );

            if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
                ?>

                <div class="wholesale-prices-options-group options-group" style="border-top: 1px solid #DDDDDD;">

                    <header class="form-row form-row-full">
                        <h4 style="font-size: 14px; margin: 10px 0;"><?php esc_html_e( 'Wholesale Prices', 'woocommerce-wholesale-prices' ); ?></h4>
                        <p style="margin:0; padding:0; line-height: 16px; font-style: italic; font-size: 13px;">
                            <?php
                                /* translators: %1$s: HTML tag (<br/>), %2$s: HTML tag (<b>), %3$s: HTML tag (</b>) */
                                printf( esc_html__( 'Wholesale prices are set per role and currency.%1$s%1$s%2$sNote:%3$s Wholesale price must be set for the base currency to enable wholesale pricing for that role. The base currency will be used for conversion to other currencies that have no wholesale price explicitly set (Auto).', 'woocommerce-wholesale-prices' ), '<br/>', '<b>', '</b>' );
                            ?>
                        </p>
                    </header>

                    <div class="wholesale-price-per-role-and-country-accordion">
                        <?php
                        $woocommerce_currencies  = get_woocommerce_currencies(); // Get all woocommerce currencies.
                        $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies(); // Get all active currencies.
                        $base_currency           = WWP_ACS_Integration_Helper::get_product_base_currency( $variation->ID ); // Get base currency. Product base currency ( if present ) or shop base currency.

                        foreach ( $all_wholesale_roles as $role_key => $role ) {
                            ?>

                            <h4><?php echo esc_html( $role['roleName'] ); ?></h4>
                            <div class="section-container">

                                <?php
                                // Get base currency wholesale price.
                                $wholesale_price = $product_variation_object->get_meta( $role_key . '_wholesale_price', true );

                                // Get base currency currency symbol.
                                $currency_symbol = get_woocommerce_currency_symbol( $base_currency );
                                if ( array_key_exists( 'currency_symbol', $role ) && ! empty( $role['currency_symbol'] ) ) {
                                    $currency_symbol = $role['currency_symbol'];
                                }

                                $field_id    = $role_key . '_wholesale_prices[' . $loop . ']';
                                $field_label = WWP_Helper_Functions::wwp_get_aelia_base_currency_field_label( $woocommerce_currencies[ $base_currency ], $currency_symbol );

                                /* translators: %1$s: Wholesale role name,%2$s: currency name and symbol */
                                $field_desc = sprintf( __( 'Only applies to users with the role of %1$s for %2$s currency', 'woocommerce-wholesale-prices' ), $role['roleName'], $woocommerce_currencies[ $base_currency ] . ' (' . $currency_symbol . ')' );

                                echo '<div class="form-row form-row-full">';

                                // Always put the base currency on top of the list.
                                WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                                    array(
                                        'id'          => $field_id,
                                        'class'       => $role_key . '_wholesale_price wholesale_price',
                                        'label'       => $field_label,
                                        'placeholder' => '',
                                        'desc_tip'    => true,
                                        'description' => $field_desc,
                                        'data_type'   => 'price',
                                        'value'       => $wholesale_price,
                                    )
                                );

                                do_action( 'wwp_after_wacs_variable_wholesale_price_field', $loop, $variation_data, $variation, $role, $role_key, $currency_symbol, $base_currency, $wholesale_price );

                                echo '</div>';

                                foreach ( $wacs_enabled_currencies as $currency_code ) {

                                    if ( $currency_code === $base_currency ) {
                                        continue;
                                    }
                                    // Base currency already processed above.

                                    $currency_symbol                       = get_woocommerce_currency_symbol( $currency_code );
                                    $wholesale_price_for_specific_currency = $product_variation_object->get_meta( $role_key . '_' . $currency_code . '_wholesale_price', true );

                                    $field_id    = $role_key . '_' . $currency_code . '_wholesale_prices[' . $loop . ']';
                                    $field_label = $woocommerce_currencies[ $currency_code ] . ' (' . $currency_symbol . ')';

                                    /* translators: %1$s: Wholesale role name,%2$s: currency name and symbol */
                                    $field_desc = sprintf( __( 'Only applies to users with the role of %1$s for %2$s currency', 'woocommerce-wholesale-prices' ), $role['roleName'], $woocommerce_currencies[ $currency_code ] . ' (' . $currency_symbol . ')' );
                                    ?>
                                    <div class="form-row form-row-full">
                                        <?php
                                        WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                                            array(
                                                'id'       => $field_id,
                                                'class'    => $role_key . '_wholesale_price wholesale_price',
                                                'label'    => $field_label,
                                                'placeholder' => 'Auto',
                                                'desc_tip' => true,
                                                'description' => $field_desc,
                                                'data_type' => 'price',
                                                'value'    => $wholesale_price_for_specific_currency,
                                            )
                                        );

                                        do_action( 'wwp_after_wacs_variable_wholesale_price_field', $loop, $variation_data, $variation, $role, $role_key, $currency_symbol, $currency_code, $wholesale_price );
                                        ?>
                                    </div>

                                <?php } ?>

                            </div><!-- .section-container -->

                        <?php } ?>

                    </div><!--.wholesale-price-per-role-and-country-accordion-->

                </div><!--.wholesale-prices-options-group-->

            <?php
            } else {
                // Check if WC Subscription is active.
                $wc_subscription_active = is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ? true : false;
                $wwpp_active            = is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) ? true : false;
                ?>

                <div class="wholesale-prices-options-group options-group" style="border-top: 1px solid #DDDDDD;">

                    <header class="form-row form-row-full">
                        <h4 style="font-size: 14px; margin: 10px 0;"><?php esc_html_e( 'Wholesale Prices', 'woocommerce-wholesale-prices' ); ?></h4>
                        <p style="margin:0px; padding:0px; line-height: 16px; font-style: italic; font-size: 13px;">
                            <?php esc_html_e( 'Set a wholesale price for this product.', 'woocommerce-wholesale-prices' ); ?>
                            <?php echo $wwpp_active ? '' : '<a href="#" class="price-levels">' . esc_html__( 'Add additional wholesale price levels', 'woocommerce-wholesale-prices' ) . '</a>'; ?>
                        </p>
                    </header>
                    <?php
                    foreach ( $all_wholesale_roles as $role_key => $role ) {

                        $currency_symbol = get_woocommerce_currency_symbol();
                        if ( array_key_exists( 'currency_symbol', $role ) && ! empty( $role['currency_symbol'] ) ) {
                            $currency_symbol = $role['currency_symbol'];
                        }
                        ?>
                        <?php
                        $field_id = $role_key . '_wholesale_prices[' . $loop . ']';

                        /* translators: %1$s: currency symbol */
                        $field_label = sprintf( __( 'Wholesale Price (%1$s)', 'woocommerce-wholesale-prices' ), $currency_symbol );

                        /* translators: %1$s: Wholesale role name */
                        $field_desc       = sprintf( __( 'Wholesale price for %1$s customers', 'woocommerce-wholesale-prices' ), str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] ) );
                        $field_desc_fixed = $field_desc;

                        /* translators: %1$s: Wholesale role name, %2$s: HTML tag (<br/>) */
                        $field_desc_percentage = sprintf( __( 'Wholesale price for %1$s customers %2$s Note: Prices are shown up to 6 decimal places but may be calculated and stored at higher precision.', 'woocommerce-wholesale-prices' ), str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] ), '<br/>' );

                        $wholesale_price = $product_variation_object->get_meta( $role_key . '_wholesale_price', true );

                        // Percentage Discount.
                        $wholesale_percentage_discount = $product_variation_object->get_meta( $role_key . '_wholesale_percentage_discount', true );

                        if ( metadata_exists( 'post', $variation->ID, $role_key . '_wholesale_percentage_discount' ) ) {
                            $discount_type = 'percentage';
                            $field_desc    = $field_desc_percentage;
                        } else {
                            $discount_type                 = 'fixed';
                            $wholesale_percentage_discount = '';
                        }
                        ?>

                        <div class="form-row form-row-full">
                            <div class="wholesale-prices-field wholesale-prices-field--variable">
                                <div class="wholesale-prices-field-role-name">
                                        <?php echo esc_html( $role['roleName'] ); ?>
                                </div>
                                <div class="wholesale-prices-field-form-field-container">
                                    <?php
                                        WWP_Helper_Functions::woocommerce_wp_select(
                                            array(
                                                'id'       => "{$role_key}_wholesale_discount_type[{$loop}]",
                                                'class'    => 'wholesale_discount_type select',
                                                'wrapper_class' => 'form-row form-row-full',
                                                'label'    => __( 'Discount Type', 'woocommerce-wholesale-prices' ),
                                                'value'    => $discount_type,
                                                'options'  => array(
                                                    'fixed'      => __( 'Fixed', 'woocommerce-wholesale-prices' ),
                                                    'percentage' => __( 'Percentage', 'woocommerce-wholesale-prices' ),
                                                ),
                                                'desc_tip' => true,
                                                /* translators: %1$s: HTML tag (<br/>) */
                                                'description' => sprintf( __( 'Choose Price Type%1$sFixed (default)%1$sPercentage', 'woocommerce-wholesale-prices' ), '<br/>' ),
                                                'custom_attributes' => array(
                                                    'data-wholesale_role' => $role_key,
                                                    'data-loop_id'        => $loop,
                                                ),
                                            )
                                        );

                                        WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                                            array(
                                                'id'       => "{$role_key}_wholesale_percentage_discount[{$loop}]",
                                                'class'    => 'wholesale_discount',
                                                'wrapper_class' => 'form-row form-row-full',
                                                'label'    => __( 'Discount (%)', 'woocommerce-wholesale-prices' ),
                                                'placeholder' => '',
                                                'desc_tip' => true,
                                                'description' => __( 'The percentage amount discounted from the regular price', 'woocommerce-wholesale-prices' ),
                                                'data_type' => 'price',
                                                'value'    => $wholesale_percentage_discount,
                                                'custom_attributes' => array(
                                                    'data-wholesale_role' => $role_key,
                                                    'data-loop_id'        => $loop,
                                                ),
                                            )
                                        );

                                    WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                                        array(
                                            'id'          => $field_id,
                                            'class'       => $role_key . '_wholesale_price wholesale_price',
                                            'wrapper_class' => 'form-row form-row-full',
                                            'label'       => $field_label,
                                            'placeholder' => '',
                                            'desc_tip'    => true,
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
                                        $signup_fee          = $product_variation_object->get_meta( $role_key . '_wholesale_signup_fee', true );
                                        $field_signup_fee_id = $role_key . '_wholesale_signup_fee[' . $loop . ']';
                                        /* translators: %1$s: currency symbol */
                                        $field_signup_fee_label = sprintf( __( 'Wholesale Signup Fee (%1$s)', 'woocommerce-wholesale-prices' ), $currency_symbol );
                                        /* translators: %1$s: Wholesale role name */
                                        $field_signup_fee_desc = sprintf( __( 'Wholesale Signup Fee for %1$s customers', 'woocommerce-wholesale-prices' ), str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] ) );

                                        $signup_fee_discount          = $product_variation_object->get_meta( $role_key . '_wholesale_signup_fee_discount', true );
                                        $field_signup_fee_discount_id = $role_key . '_wholesale_signup_fee_discount[' . $loop . ']';
                                        /* translators: %1$s: currency symbol */
                                        $field_signup_fee_discount_label = __( 'Signup Fee Discount (%)', 'woocommerce-wholesale-prices' );
                                        /* translators: %1$s: Wholesale role name */
                                        $field_signup_fee_discount_desc = sprintf( __( 'Wholesale Signup Fee for %1$s customers', 'woocommerce-wholesale-prices' ), str_replace( array( 'Customer', 'Customers' ), '', $role['roleName'] ) );

                                        WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                                            array(
                                                'id'       => $field_signup_fee_discount_id,
                                                'wrapper_class' => 'form-row form-row-full',
                                                'class'    => $role_key . '_wholesale_signup_fee_discount wholesale_signup_fee_discount',
                                                'label'    => $field_signup_fee_discount_label,
                                                'placeholder' => '',
                                                'desc_tip' => 'true',
                                                'description' => __( 'The percentage amount discounted from the Sign-up fee', 'woocommerce-wholesale-prices' ),
                                                'data_type' => 'price',
                                                'value'    => $signup_fee_discount,
                                                'custom_attributes' => array(
                                                    'data-wholesale_role' => $role_key,
                                                    'data-loop_id'        => $loop,
                                                ),
                                            )
                                        );

                                        WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                                            array(
                                                'id'       => $field_signup_fee_id,
                                                'wrapper_class' => 'form-row form-row-full',
                                                'class'    => $role_key . '_wholesale_signup_fee wholesale_signup_fee wholesale_price',
                                                'label'    => $field_signup_fee_label,
                                                'placeholder' => '',
                                                'desc_tip' => 'true',
                                                'description' => $field_signup_fee_desc,
                                                'data_type' => 'price',
                                                'value'    => $signup_fee,
                                            )
                                        );
                                    }

                                    do_action( 'wwp_after_variable_wholesale_price_field', $loop, $variation_data, $variation, $role, $role_key, $currency_symbol, $wholesale_price, $discount_type, $wholesale_percentage_discount );
                                    ?>
                                </div>
                            </div>
                        </div>

                    <?php } ?>
                </div>

            <?php
            }
        }

        /**
         * Save wholesale custom price field on variable products.
         * Since WooCommerce 2.4.x series, they introduced a new button "Save Changes" on the variation tab of a variable product.
         * This allows you to save the variations itself even if the main variable product isn't saved yet.
         *
         * @param int        $post_id                    Product Id.
         * @param array|null $wholesale_roles            Array of wholesale roles to apply the wholesale price. If null, it will apply to all registered wholesale roles.
         * @param int|null   $variation_ids              Variation Id or null.
         * @param float|null $variation_wholesale_prices Variation wholesale price or null.
         * @param bool       $skip_non_base_currency     Skip non base currency.
         *
         * @since 1.6.4 Only set base currency price when setting bulk price set for variations (WWP-155).
         *
         * @since 1.0.0
         * @since 1.2.0 Add Aelia Currency Switcher Plugin Integration.
         * @since 1.2.3 Add support for custom variations bulk actions.
         * @since 1.3.0 Refactor codebase and move to dedicated model.
         * @since 2.1.0 Add support for wholesale percentage discount.
         */
        public function save_wholesale_price_fields( $post_id, $wholesale_roles = null, $variation_ids = null, $variation_wholesale_prices = null, $skip_non_base_currency = false ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            if ( is_null( $wholesale_roles ) ) {
                $wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
            }

            /*
             * The logic here is that we also check those variations that are not currently listed on the current page
             * WC 2.4.x series introduce variations pagination, now if we don't check those other variations that are not listed
             * currently coz they are on a different page, what will happen is we will only add on the $role_key . '_variations_with_wholesale_price'
             * meta the variations that are currently listed on the current page.
             */
            $main_variable_product = wc_get_product( $post_id );

            if ( ( ! is_null( $variation_ids ) && ! is_null( $variation_wholesale_prices ) ) || ( isset( $_POST['variable_post_id'] ) && ! empty( $_POST['variable_post_id'] ) ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Sanitized below on assignment.

                /**
                 * We delete this meta in the beginning coz we are using add_meta_data, not update_meta_data below
                 * If we don't delete this, the values will be stacked with the old values
                 * Note: per role
                 */
                foreach ( $wholesale_roles as $role_key => $role ) {
                    $main_variable_product->delete_meta_data( $role_key . '_variations_with_wholesale_price' );
                }

                $variable_post_id = ! is_null( $variation_ids ) ? $variation_ids : array_map( 'absint', wp_unslash( $_POST['variable_post_id'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
                $max_loop         = max( array_keys( $variable_post_id ) );
                $thousand_sep     = get_option( 'woocommerce_price_thousand_sep' );
                $decimal_sep      = get_option( 'woocommerce_price_decimal_sep' );
                $discount_type    = '';

                $aelia_currency_switcher_active = WWP_ACS_Integration_Helper::aelia_currency_switcher_active();

                if ( $aelia_currency_switcher_active && ! $skip_non_base_currency ) {

                    // Get all active currencies.
                    $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies();

                    foreach ( $wholesale_roles as $role_key => $role ) {

                        foreach ( $wacs_enabled_currencies as $currency_code ) {

                            for ( $i = 0; $i <= $max_loop; $i++ ) {

                                if ( ! isset( $variable_post_id[ $i ] ) ) {
                                    continue;
                                }

                                $variation_id = (int) $variable_post_id[ $i ];

                                $discount_type       = isset( $_POST[ $role_key . '_wholesale_discount_type' ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $role_key . '_wholesale_discount_type' ] ) ) : null; //phpcs:ignore WordPress.Security.NonceVerification.Missing
                                $percentage_discount = isset( $_POST[ $role_key . '_wholesale_percentage_discount' ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $role_key . '_wholesale_percentage_discount' ] ) ) : null; //phpcs:ignore WordPress.Security.NonceVerification.Missing

                                // Get base currency. Product base currency ( if present ) or shop base currency.
                                // Note for the variation, note for the parent variable product.
                                $base_currency = WWP_ACS_Integration_Helper::get_product_base_currency( $variation_id );

                                if ( $currency_code === $base_currency ) {

                                    // Base Currency.
                                    if ( ! is_null( $variation_wholesale_prices ) ) {
                                        $wholesale_prices = $variation_wholesale_prices;
                                    } elseif ( isset( $_POST[ $role_key . '_wholesale_prices' ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
                                        $wholesale_prices = wc_clean( wp_unslash( $_POST[ $role_key . '_wholesale_prices' ] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
                                    } elseif ( isset( $_POST[ $role_key . '_wholesale_price_hidden' ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
                                        $wholesale_prices = wc_clean( wp_unslash( $_POST[ $role_key . '_wholesale_price_hidden' ] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
                                    } else {
                                        $wholesale_prices = array();
                                    }

                                    $wholesale_price_key = $role_key . '_wholesale_price';
                                    $is_base_currency    = true;

                                } else {

                                    $currency_key        = $role_key . '_' . $currency_code . '_wholesale_prices';
                                    $wholesale_prices    = ! is_null( $variation_wholesale_prices ) ? $variation_wholesale_prices : ( isset( $_POST[ $currency_key ] ) ? wc_clean( wp_unslash( $_POST[ $currency_key ] ) ) : array() ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
                                    $wholesale_price_key = $role_key . '_' . $currency_code . '_wholesale_price';
                                    $is_base_currency    = false;

                                }

                                if ( isset( $wholesale_prices[ $i ] ) ) {
                                    $this->_save_variable_product_wholesale_price( $post_id, $variation_id, $role_key, $wholesale_prices[ $i ], $wholesale_price_key, $thousand_sep, $decimal_sep, $discount_type[ $i ], $percentage_discount[ $i ], $aelia_currency_switcher_active, $is_base_currency, $currency_code );
                                }
                            }
                        }
                    }
                } else {

                    foreach ( $wholesale_roles as $role_key => $role ) {

                        $wholesale_prices = ! is_null( $variation_wholesale_prices )
                            ? $variation_wholesale_prices
                            : ( isset( $_POST[ $role_key . '_wholesale_prices' ] ) ? wc_clean( wp_unslash( $_POST[ $role_key . '_wholesale_prices' ] ) ) : array() ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

                        $wholesale_price_key = $role_key . '_wholesale_price';

                        for ( $i = 0; $i <= $max_loop; $i++ ) {

                            if ( ! isset( $variable_post_id[ $i ] ) ) {
                                continue;
                            }

                            $variation_id        = (int) $variable_post_id[ $i ];
                            $discount_type       = isset( $_POST[ $role_key . '_wholesale_discount_type' ][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST[ $role_key . '_wholesale_discount_type' ][ $i ] ) ) : null; //phpcs:ignore WordPress.Security.NonceVerification.Missing
                            $percentage_discount = isset( $_POST[ $role_key . '_wholesale_percentage_discount' ][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST[ $role_key . '_wholesale_percentage_discount' ][ $i ] ) ) : null; //phpcs:ignore WordPress.Security.NonceVerification.Missing

                            if ( isset( $wholesale_prices[ $i ] ) ) {
                                $this->_save_variable_product_wholesale_price( $post_id, $variation_id, $role_key, $wholesale_prices[ $i ], $wholesale_price_key, $thousand_sep, $decimal_sep, $discount_type, $percentage_discount );

                                /**
                                 * Perform actions after saving a variation's wholesale prices
                                 *
                                 * @param int    $variation_id The variation's parent ID.
                                 * @param string $role_key The wholesale role key.
                                 * @param int    $i Variation's loop index.
                                 * @param int    $post_id The vparent product's post id
                                 */
                                do_action( 'wwp_after_save_variable_product_wholesale_price', $variation_id, $role_key, $i, $variation_id );
                            }
                        }
                    }
                }

                // Get other variations that are not currently displayed coz they are on another page.
                $other_page_variations = array_diff( $main_variable_product->get_children(), $variable_post_id );

                if ( ! empty( $other_page_variations ) ) {

                    foreach ( $wholesale_roles as $role_key => $role ) {

                        foreach ( $other_page_variations as $variation_id ) {

                            /**
                             * Code below on determining if other paged variations have wholesale pricing is already covers case
                             * if Aelia currency converter plugin is active. When Aelia plugin is active, we only need to check if wholesale price
                             * is set for the base currency to conclude that this variation have a wholesale price. Which the
                             * code below is already doing.
                             */
                            $variation_product = wc_get_product( $variation_id );
                            $wholesale_price   = $variation_product->get_meta( $role_key . '_wholesale_price', true );

                            if ( is_numeric( $wholesale_price ) && $wholesale_price > 0 ) {

                                $main_variable_product->add_meta_data( $role_key . '_variations_with_wholesale_price', $variation_id );
                                $main_variable_product->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );

                            }
                        }
                    }
                }
            }

            /**
             * Check if the parent variable product has wholesale price or not.
             * If it has, then set the meta '_have_wholesale_price' to 'yes'.
             * If not, then set the meta '_have_wholesale_price' to 'no'.
             */
            $main_variable_children = $main_variable_product->get_children();
            if ( ! empty( $main_variable_children ) ) {

                // WWPP-147 : Delete the meta that is set when setting discount on per product category level.
                foreach ( $wholesale_roles as $role_key => $role ) {
                    $main_variable_product->delete_meta_data( $role_key . '_have_wholesale_price_set_by_product_cat' );
                }

                $wholesale_prices_per_role = WWP_Helper_Functions::get_wholesale_prices_per_role_from_variations( $main_variable_children, $wholesale_roles );

                // Fire action for each role that has at least one variation with a wholesale price.
                foreach ( $wholesale_roles as $role_key => $role ) {
                    if ( ! empty( $wholesale_prices_per_role[ $role_key ] ) ) {
                        do_action( 'wwp_set_have_wholesale_price_meta_prod_cat_wholesale_discount', $post_id, $role_key );
                    }
                }

                foreach ( $wholesale_roles as $role_key => $role ) {
                    if ( ! empty( $wholesale_prices_per_role[ $role_key ] ) ) {
                        $prices = $wholesale_prices_per_role[ $role_key ];
                        $main_variable_product->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );
                        $main_variable_product->update_meta_data( $role_key . '_min_wholesale_price', min( $prices ) );
                        $main_variable_product->update_meta_data( $role_key . '_max_wholesale_price', max( $prices ) );
                    } else {
                        $main_variable_product->update_meta_data( $role_key . '_have_wholesale_price', 'no' );
                        $main_variable_product->delete_meta_data( $role_key . '_min_wholesale_price' );
                        $main_variable_product->delete_meta_data( $role_key . '_max_wholesale_price' );
                    }
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing

            // Save the parent variable product.
            $main_variable_product->save();
        }

        /**
         * Save variable product wholesale price.
         *
         * @param int     $variable_id                    Variable ID.
         * @param int     $variation_id                   Variation ID.
         * @param string  $role_key                       Wholesale role key.
         * @param float   $wholesale_price                Wholesale price.
         * @param string  $wholesale_price_key            Wholesale price key. Wholesale role key + '_wholesale_price'.
         * @param string  $thousand_sep                   Thousand separator.
         * @param string  $decimal_sep                    Decimal separator.
         * @param string  $discount_type                  Determines if price type is percentage or fixed price.
         * @param float   $percentage_discount            Percentage discount value.
         * @param boolean $aelia_currency_switcher_active Flag that determines if aelia currency switcher is active or not.
         * @param boolean $is_base_currency               Flag that determines if this is a base currency.
         * @param mixed   $currency_code                  String of current currency code or null.
         *
         * @since 1.2.0
         * @since 1.3.0 Refactor codebase and move to its dedicated model.
         * @since 2.1.0 Added support for wholesale percentage discount
         */
        private function _save_variable_product_wholesale_price( $variable_id, $variation_id, $role_key, $wholesale_price, $wholesale_price_key, $thousand_sep, $decimal_sep, $discount_type, $percentage_discount, $aelia_currency_switcher_active = false, $is_base_currency = false, $currency_code = null ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing

            // Get the parent variable product.
            $variable_product = wc_get_product( $variable_id );

            // Get the current variation product.
            $variation_product = wc_get_product( $variation_id );

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
            $wholesale_price = WWP_Helper_Functions::sanitize_price_input( $wholesale_price );

            if ( ! empty( $percentage_discount ) && null !== $percentage_discount ) {
                $percentage_discount = WWP_Helper_Functions::sanitize_price_input( $percentage_discount );
            }

            if ( $variable_product instanceof WC_Product ) {
                /**
                 * If it has valid wholesale price, attached current variation id to parent product (variable)
                 * $role_key . '_variations_with_wholesale_price' post meta. This meta of the parent variable product
                 * will be used later to determine if the parent variable product has wholesale price or not.
                 */
                if ( $aelia_currency_switcher_active ) {

                    /**
                     * Only add current variation id to parent variable product $role_key . '_variations_with_wholesale_price' meta
                     * if this is the base currency. You see due to how Aelia Currency Switcher works, base currency is very important.
                     * Therefore only base currency wholesale price is used to determine if variation has wholesale price or not.
                     */
                    if ( $is_base_currency ) {
                        if ( is_numeric( $wholesale_price ) && $wholesale_price > 0 ) {
                            $variable_product->add_meta_data( $role_key . '_variations_with_wholesale_price', $variation_id );
                        }
                    }
                } elseif ( is_numeric( $wholesale_price ) && $wholesale_price > 0 ) {
                    $variable_product->add_meta_data( $role_key . '_variations_with_wholesale_price', $variation_id );
                }

                // Save variable product.
                $variable_product->save();
            }

            $wholesale_price = wc_clean( apply_filters( 'wwp_before_save_variation_product_wholesale_price', $wholesale_price, $role_key, $variation_id, $variable_id, $aelia_currency_switcher_active, $is_base_currency, $currency_code ) );

            if ( $variation_product instanceof WC_Product ) {
                $variation_product->update_meta_data( $wholesale_price_key, $wholesale_price );

                if ( 'percentage' === $discount_type ) {
                    $variation_product->update_meta_data( $role_key . '_wholesale_percentage_discount', $percentage_discount );
                } else {
                    $variation_product->delete_meta_data( $role_key . '_wholesale_percentage_discount' );
                }

                // Save the current variation product.
                $variation_product->save();
            }

            // phpcs:enable WordPress.Security.NonceVerification.Missing
        }

        /**
         * Hook on product ( variation ) deletion. Remove post meta variation id reference and reset have wholesale price on the parent product.
         *
         * @param int $variation_id Product ID.
         *
         * @since 1.7
         * @since 2.1.0 Added support for wholesale percentage discount
         */
        public function variation_deletion( $variation_id ) {

            $product = wc_get_product( $variation_id );

            if ( $product instanceof WC_Product && $product->is_type( 'variation' ) ) {

                $variable_id                = $product->get_parent_id();
                $variable_product           = wc_get_product( $variable_id );
                $registered_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

                if ( $registered_wholesale_roles ) {

                    if ( ! $variable_product instanceof WC_Product ) {
                        return;
                    }

                    foreach ( $registered_wholesale_roles as $role_key => $role ) {

                        // Remove trace to variation with wholesale price since it will be deleted.
                        $variable_product->delete_meta_data( $role_key . '_variations_with_wholesale_price', $variation_id );

                        // Remove trace to variation whith percentage wholesale discount, since it will be deleted.
                        $variable_product->delete_meta_data( $role_key . '_variations_with_percentage_discount', $variation_id );

                        // Remove variation percentage discount.
                        $product->delete_meta_data( $role_key . '_wholesale_percentage_discount' );

                        // Update _have_wholesale_price meta.
                        $wholesale_variations = WWP_Helper_Functions::get_formatted_meta_data( $variable_product, $role_key . '_variations_with_wholesale_price' );
                        $variable_product->update_meta_data( $role_key . '_have_wholesale_price', empty( $wholesale_variations ) ? 'no' : 'yes' );

                    }
                }

                // Save the product.
                $product->save();
                $variable_product->save();
            }
        }

        /**
         * Add wholesale sale price dummy field on the variable product edit page.
         *
         * @since 2.1.6
         *
         * @param array   $loop                          The position of the loop.
         * @param array   $variation_data                Array of variation data.
         * @param WP_Post $variation                     Variation object.
         * @param array   $role                          Wholesale role array.
         * @param string  $role_key                      Wholesale role key.
         * @param string  $currency_symbol               Currency symbol.
         * @param int     $wholesale_price               The wholesale price.
         * @param string  $discount_type                 The discount type (fixed | percentage).
         * @param int     $wholesale_percentage_discount The Wholesale percentage discount value.
         */
        public function add_wholesale_sale_price_dummy_fields( $loop, $variation_data, $variation, $role, $role_key, $currency_symbol, $wholesale_price, $discount_type, $wholesale_percentage_discount ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            global $WOOCS, $woocommerce_wpml;

            if ( empty( $WOOCS ) && empty( $woocommerce_wpml ) ) {
                WWP_Helper_Functions::wwp_woocommerce_wp_text_input(
                    array(
                        'id'                => "{$role_key}_wholesale_sale_discount_dummy[{$loop}]",
                        'class'             => $role_key . '_wholesale_sale_discount wholesale_sale_discount',
                        'wrapper_class'     => 'form-row form-row-full',
                        'label'             => __( 'Sale Discount (%)', 'woocommerce-wholesale-prices' ),
                        'placeholder'       => '',
                        'desc_tip'          => true,
                        'description'       => __( 'The percentage amount discounted from the wholesale price', 'woocommerce-wholesale-prices' ),
                        'data_type'         => 'price',
                        'custom_attributes' => array(
                            'data-wholesale_role' => $role_key,
                            'data-loop_id'        => $loop,
                        ),
                    )
                );
            }

            woocommerce_wp_text_input(
                array(
                    'id'            => $role_key . '_wholesale_sale_price_dummy[' . $loop . ']',
                    'class'         => $role_key . '_wholesale_sale_price wholesale_sale_price',
                    'wrapper_class' => 'form-row form-row-full',
                    /* translators: %s: currency symbol */
                    'label'         => sprintf( __( 'Wholesale Sale Price (%1$s)', 'woocommerce-wholesale-prices' ), $currency_symbol ) . ' <a href="#" class="wholesale_sale_schedule">' . esc_html__( 'Schedule', 'woocommerce-wholesale-prices' ) . '</a><a href="#" class="cancel_wholesale_sale_schedule hidden">' . esc_html__( 'Cancel schedule', 'woocommerce-wholesale-prices' ) . '</a>',
                    'placeholder'   => '',
                    'data_type'     => 'price',
                )
            );

            echo '<div class="form-field ' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_fields wholesale_sale_price_dates_fields wholesale_sale_price_dates_fields__variations hidden">
                    <p class="form-row form-row-first">
                        <label>' . esc_html__( 'Sale start date', 'woocommerce-wholesale-prices' ) . '</label>
                        <input type="text" class="wholesale_sale_price_dates_from" name="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_from[' . esc_attr( $loop ) . ']" value="" placeholder="' . esc_attr_x( 'From&hellip;', 'placeholder', 'woocommerce-wholesale-prices' ) . ' YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
                    </p>
                    <p class="form-row form-row-last">
                        <label>' . esc_html__( 'Sale end date', 'woocommerce-wholesale-prices' ) . '</label>
                        <input type="text" class="wholesale_sale_price_dates_to" name="' . esc_attr( $role_key ) . '_wholesale_sale_price_dates_to[' . esc_attr( $loop ) . ']" value="" placeholder="' . esc_attr_x( 'To&hellip;', 'placeholder', 'woocommerce-wholesale-prices' ) . '  YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
                    </p>
                </div>';
        }

        /**
         * Execute model.
         *
         * @since 1.3.0
         * @access public
         */
        public function run() {

            // Variations custom wholesale bulk action.
            add_action( 'woocommerce_variable_product_bulk_edit_actions', array( $this, 'add_variation_custom_wholesale_bulk_action_options' ), 10 );

            add_action( 'woocommerce_bulk_edit_variations', array( $this, 'execute_variation_custom_wholesale_bulk_actions' ), 10, 4 );

            // Variations wholesale price.
            add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_wholesale_price_fields' ), 10, 3 );

            add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_wholesale_price_fields' ), 10, 1 );

            add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'save_wholesale_price_fields' ), 10, 1 ); // Via Ajax ( Introduced on WooCommerce 2.4 series ).

            // Delete any variation reference from the variable meta.
            add_action( 'before_delete_post', array( $this, 'variation_deletion' ), 10, 1 );

            if ( ! WWP_Helper_Functions::is_wwpp_active() ) {
                // Wholesale sale price dummy fields.
                add_action( 'wwp_after_variable_wholesale_price_field', array( $this, 'add_wholesale_sale_price_dummy_fields' ), 10, 9 );
            }
        }
    }
}
