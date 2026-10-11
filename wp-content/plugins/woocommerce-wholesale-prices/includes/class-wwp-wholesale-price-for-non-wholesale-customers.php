<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic of Wholesale Prices For Non Wholesale Customers feature.
 *
 * @since 1.15.0
 * @since 2.3.0 Expose the 'wwp_non_wholesale_price_markup' filter so integrations can supply
 *              popover markup for product types WWP cannot price itself (e.g. WWPP composites).
 */
class WWP_Wholesale_Prices_For_Non_Wholesale_Customers {


    /** ===============================================================================================================
     *  Class Properties
     * ===============================================================================================================*/

    /**
     * Property that holds single main instance of WWP_Wholesale_Prices_For_Non_Wholesale_Customers
     *
     * @since 1.15.0
     * @access private
     * @var WWP_Wholesale_Prices_For_Non_Wholesale_Customers
     */
    private static $_instance;

    /**
     * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
     *
     * @since 1.15.0
     * @access private
     * @var WWP_Wholesale_Roles
     */
    private $_wwp_wholesale_roles;

    /**
     * Model that houses the logic of retrieving information relating to woocommerce wholesale prices.
     *
     * @since 2.1.6
     * @access private
     * @var WWP_Wholesale_Prices
     */
    private $_wwp_wholesale_prices;

    /** ===============================================================================================================
     *  Class Methods
     * ===============================================================================================================*/

    /**
     * WWP_Wholesale_Prices_For_Non_Wholesale_Customers constructor.
     *
     * @since 1.3.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWP_Wholesale_Prices_For_Non_Wholesale_Customers model.
     */
    public function __construct( $dependencies = array() ) {
        if ( isset( $dependencies['WWP_Wholesale_Roles'] ) ) {
            $this->_wwp_wholesale_roles = $dependencies['WWP_Wholesale_Roles'];
        }

        if ( isset( $dependencies['WWP_Wholesale_Prices'] ) ) {
            $this->_wwp_wholesale_prices = $dependencies['WWP_Wholesale_Prices'];
        }
    }

    /**
     * Ensure that only one instance of WWP_Wholesale_Prices_For_Non_Wholesale_Customers is loaded (singleton pattern)
     *
     * @since 1.15.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWP_Wholesale_Prices_For_Non_Wholesale_Customers model.
     * @return WWP_Wholesale_Prices_For_Non_Wholesale_Customers
     */
    public static function instance( $dependencies ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * This function will process product price html mariku to be used in show wholesale prices to non wholesale customers
     *
     * @since  1.16.1
     * @since  2.1.6   Change function parameter from API data to string.
     *                 Combine '_process_simple_product_wholesale_price' & '_process_variable_product_wholesale_price' function to '_process_product_wholesale_price'.
     *                 Use the 'wwp_filter_wholesale_price_title_text' hook instead of hardcoding the Wholesale Price title text.
     * @access private
     *
     * @param string $price_html Data from api results.
     * @param string $role_name Wholesale role name.
     * @return string $html
     */
    private function _process_product_wholesale_price( $price_html, $role_name ) {
        $wholesale_price_title_text = trim( apply_filters( 'wwp_filter_wholesale_price_title_text', __( 'Wholesale Price:', 'woocommerce-wholesale-prices' ) ) );

        $result = str_replace( $wholesale_price_title_text, $role_name, $price_html );

        return $result;
    }

    /**
     * Resolve the wholesale role options enabled for the "show wholesale prices to non-wholesale
     * customers" feature.
     *
     * Applies the 'wwp_non_wholesale_roles_options' filter so an integration can narrow the enabled
     * roles for a product (e.g. WWPP's WC Vendors integration narrows the role set per-vendor).
     *
     * @since 2.3.0
     * @access public
     *
     * @param array $wholesale_roles All registered wholesale roles, keyed by role slug.
     * @param int   $product_id      Product ID.
     * @return array Enabled wholesale role keys.
     */
    public function _get_enabled_non_wholesale_role_options( $wholesale_roles, $product_id ) {
        if ( WWP_Helper_Functions::is_wwpp_active() ) {
            $wholesale_role_options = get_option( 'wwp_non_wholesale_wholesale_role_select2' );
        } else {
            $wholesale_role_options = array_keys( $wholesale_roles );
        }

        /**
         * Allow to filter non wholesale role options
         *
         * @param array $wholesale_role_options The current wholesale role options.
         * @param int $product_id The product ID.
         *
         * @return array
         */
        return apply_filters( 'wwp_non_wholesale_roles_options', $wholesale_role_options, $product_id );
    }

    /**
     * This function is responsible for the prices of wholesale roles if each products, this is triggered by "Click to See Wholesale Prices"
     *
     * @since 1.15.0
     * @since 1.15.1 Removing function of getting ajax request, we dont need it anymore, since data is now encoded using base64 utf8 and added to html data attribute for fetching later on in js script for faster and better user experience.
     *               Rename function from get_product_wholesale_prices_ajax to get_product_wholesale_prices.
     * @since 2.1.6  Remove API usage.
     * @since 2.3.0  Expose the 'wwp_non_wholesale_price_markup' filter so an integration can supply
     *               popover markup for product types WWP cannot price itself (e.g. WWPP composites).
     *
     * @access public
     * @return string|void html
     */
    public function get_product_wholesale_prices_ajax() {
        global $wc_wholesale_prices;

        /**
         * Verify nonce if its the same as we created, if not then we return
         */
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wwp_nonce' ) ) {
            return;
        }

        $product_id                 = isset( $_POST['data']['product_id'] ) ? absint( $_POST['data']['product_id'] ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Missing
        $product_object             = wc_get_product( $product_id );
        $wholesale_roles            = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
        $wholesale_price_title_text = trim( apply_filters( 'wwp_filter_wholesale_price_title_text', __( 'Wholesale Price:', 'woocommerce-wholesale-prices' ) ) );
        $html_result                = '';

        $wholesale_role_options = $this->_get_enabled_non_wholesale_role_options( $wholesale_roles, $product_id );

        foreach ( $wholesale_roles as $wholesale_role => $data ) {
            if ( in_array( $wholesale_role, $wholesale_role_options, true ) ) {
                $product_type   = WWP_Helper_Functions::wwp_get_product_type( $product_object );
                $wwp_price_html = $this->_wwp_wholesale_prices->wholesale_price_html_filter( 1, $product_object, array( $wholesale_role ), true );
                $role_markup    = '';

                if (
                    is_string( $wwp_price_html )
                    && str_contains( $wwp_price_html, $wholesale_price_title_text )
                    && in_array( $product_type, array( 'simple', 'variation', 'variable' ), true )
                ) {
                    $role_markup = $this->_process_product_wholesale_price( $wwp_price_html, $data['roleName'] );
                }

                /**
                 * Filter the "See wholesale prices" popover markup for a single wholesale role.
                 *
                 * Lets an integration supply the markup for product types WWP cannot price itself
                 * (e.g. WWPP composite products, whose price is the sum of their priced-individually
                 * components). For WWP's own product types $role_markup already holds the built markup;
                 * for other types it is an empty string an integration can fill.
                 *
                 * @since 2.3.0
                 *
                 * @param string     $role_markup    The price markup for this role, or '' when WWP cannot build it.
                 * @param WC_Product  $product_object The product being displayed.
                 * @param string      $wholesale_role The wholesale role key.
                 * @param array       $data           The wholesale role data (includes 'roleName').
                 */
                $html_result .= apply_filters( 'wwp_non_wholesale_price_markup', $role_markup, $product_object, $wholesale_role, $data );
            }
        }

        // A link was clicked but no wholesale price could be resolved for any role - the link gate and
        // this popover gate have diverged (the failure mode of the "See wholesale prices" feature). The
        // JS backstop hides the empty popover, so log it here to keep the divergence detectable.
        if ( '' === trim( $html_result ) && function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->debug(
                sprintf( 'See wholesale prices: no wholesale price resolved for product #%d despite a rendered link.', $product_id ),
                array( 'source' => 'wwp-non-wholesale-prices' )
            );
        }

        if ( ! empty( $wholesale_roles ) ) {

            $wwlc_registration_link = $this->registration_link_filter();

            if ( WWP_Helper_Functions::is_wwlc_active() && ! empty( $wwlc_registration_link ) ) {
                $html_result .= "<div class='register-link'><a href='" . $wwlc_registration_link . "'><strong>" . $this->registration_text_filter() . '</strong></a></div>';
            }

            echo wp_kses_post( $html_result );
            die();

        }
    }

    /**
     * Register custom fields
     *
     * @since 1.15.0
     * @access private
     */
    public function register_settings_field_options() {
        // Show wholesale price to non wholesale users settings options.
        if ( get_option( 'wwp_see_wholesale_prices_replacement_text' ) === false ) {
            update_option( 'wwp_see_wholesale_prices_replacement_text', 'See wholesale prices', 'no' );
        }

        // NOTE: Default role value is added in add_default_wholesale_role_value.

        if ( get_option( 'wwp_price_settings_register_text' ) === false ) {
            update_option( 'wwp_price_settings_register_text', 'Click here to register as a wholesale customer', 'no' );
        }

        if ( get_option( 'wwp_non_wholesale_show_in_products' ) === false ) {
            update_option( 'wwp_non_wholesale_show_in_products', 'yes', 'no' );
        }

        if ( get_option( 'wwp_non_wholesale_show_in_shop' ) === false ) {
            update_option( 'wwp_non_wholesale_show_in_shop', 'yes', 'no' );
        }

        if ( get_option( 'wwp_non_wholesale_show_in_wwof' ) === false ) {
            update_option( 'wwp_non_wholesale_show_in_wwof', 'yes', 'no' );
        }
    }

    /**
     * This will get the registration wholesale page if WWLC is active/installed from the selected options in WWLC Registration Settings.
     *
     * @since 1.15.0
     * @since 1.15.1
     * @access public
     * @return permalink for registration page
     */
    public function registration_link_filter() {
        $wwlc_registration_page = get_option( 'wwlc_general_registration_page', '' );

        return apply_filters( 'wwp_non_wholesale_registration_link_filter', WWP_Helper_Functions::is_wwlc_active() && ! empty( $wwlc_registration_page ) ? get_permalink( $wwlc_registration_page ) : '' );
    }

    /**
     * This will display registration text message for non wholesale users to register as a wholesale customer
     *
     * @since 1.15.0
     * @since 1.15.1
     * @access public
     * @return string registration text message which is filterable
     */
    public function registration_text_filter() {
        $registration_text = get_option( 'wwp_price_settings_register_text', '' );

        return apply_filters( 'wwp_non_wholesale_registration_text_filter', empty( $registration_text ) ? __( 'Click here to register as a wholesale customer', 'woocommerce-wholesale-prices' ) : $registration_text );
    }

    /**
     * This function display's "Click to See Wholesale Prices" on Shops, Single Products, Upsells, Cross sells
     * Wholesale Order Form, this function will also trigger popover Wholesale Price Box if click.
     *
     * @since  1.15.0
     * @since  1.15.1 added function get_product_wholesale_prices
     * @since  2.3.0  Add $force_eligible so an integration can offer the link for a product whose
     *                wholesale price is resolved outside WWP's parent-level meta (e.g. WWPP composites).
     * @access public
     *
     * @param  WC_Product $product        Product object.
     * @param  bool       $force_eligible Optional. When true, skip the parent-level meta eligibility
     *                                    check below and treat the product as eligible. Used by
     *                                    integrations whose wholesale price is resolved separately
     *                                    (e.g. WWPP composite products). Default false.
     * @return string|void     $message containing html string
     */
    public function display_replacement_message_to_non_wholesale( $product = null, $force_eligible = false ) {
        if ( is_null( $product ) ) {
            return;
        }

        // A caller can force eligibility for products whose wholesale price is resolved outside
        // the parent-level meta loop below (composites resolve theirs via WWPP).
        $show_wholesale_prices_text      = $force_eligible;
        $product_id                      = $product->get_id();
        $is_wwpp_active                  = WWP_Helper_Functions::is_wwpp_active();
        $replacement_text                = get_option( 'wwp_see_wholesale_prices_replacement_text' );
        $wholesale_role_general_discount = get_option( 'wwpp_option_wholesale_role_general_discount_mapping', array() );
        $wholesale_price_options         = $is_wwpp_active ? get_option( 'wwp_non_wholesale_wholesale_role_select2', array() ) : array_keys( $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles() );
        $variable_parent_object          = $product->get_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : null;

        $show_in_product       = apply_filters(
            'wwp_non_wholesale_show_in_products',
            get_option( 'wwp_non_wholesale_show_in_products' ),
            $product
        );
        $show_in_shop          = apply_filters(
            'wwp_non_wholesale_show_in_shop',
            get_option( 'wwp_non_wholesale_show_in_shop' ),
            $product
        );
        $show_wholesale_prices = apply_filters(
            'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
            get_option( 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale' ),
            $product
        );

        $message = apply_filters( 'wwp_display_non_wholesale_replacement_message', empty( $replacement_text ) ? __( 'See wholesale prices', 'woocommerce-wholesale-prices' ) : $replacement_text );

        if ( ! empty( $wholesale_price_options ) ) {

            // For PHP 8.0.8 Compatibility.
            if ( is_array( $wholesale_price_options ) || is_object( $wholesale_price_options ) ) {

                foreach ( $wholesale_price_options as $wholesale_role ) {

                    $wholesale_price                 = $product->get_meta( $wholesale_role . '_wholesale_price', true );
                    $have_wholesale_price            = $product->get_meta( $wholesale_role . '_have_wholesale_price', true );
                    $variations_with_wholesale_price = WWP_Helper_Functions::get_formatted_meta_data( $product, $wholesale_role . '_variations_with_wholesale_price' );

                    // Discount is set in product level.
                    if ( $wholesale_price > 0 || ! empty( $variations_with_wholesale_price ) ) {
                        $show_wholesale_prices_text = true;
                        break;
                    }

                    if ( $is_wwpp_active ) {

                        $ignore_cat_level               = $product->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true );
                        $ignore_role_level              = $product->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true );
                        $have_wholesale_price_cat_level = $product->get_meta( $wholesale_role . '_have_wholesale_price_set_by_product_cat', true );

                        $variable_have_wholesale_price_cat_level = false;
                        if ( ! empty( $variable_parent_object ) && is_a( $variable_parent_object, 'WC_Product' ) ) {
                            $variable_have_wholesale_price_cat_level = $variable_parent_object->get_meta( $wholesale_role . '_have_wholesale_price_set_by_product_cat', true );
                        }

                        // Category wholesale price and ignore category level should not be set.
                        if ( ( 'yes' === $have_wholesale_price || 'yes' === $have_wholesale_price_cat_level || $variable_have_wholesale_price_cat_level ) && 'yes' !== $ignore_cat_level ) {

                            $show_wholesale_prices_text = true;
                            break;

                        }

                        // General Discount is set.
                        if ( ! empty( $wholesale_role_general_discount ) &&
                            is_array( $wholesale_role_general_discount ) &&
                            array_key_exists( $wholesale_role, $wholesale_role_general_discount ) &&
                            'yes' !== $ignore_role_level
                        ) {

                            $show_wholesale_prices_text = true;
                            break;

                        }
                    }
                }
            }
        }

        $show_wholesale_prices_text = apply_filters(
            'wwp_show_wholesale_prices_to_non_wholesale_customers',
            $show_wholesale_prices_text,
            $product
        );

        if (
            $show_wholesale_prices_text && 'yes' === $show_wholesale_prices && (
                ( is_product() && 'yes' === $show_in_product ) || (
                    ( ( is_shop() || is_product_category() ) && 'yes' === $show_in_shop ) ||
                    apply_filters( 'wwp_load_show_wholesale_to_non_wholesale', false, 'text' )
                )
            )
        ) {

            return sprintf(
                '<div class="wwp_show_wholesale_prices_text">
                    <a href="#" onclick="return false;" role="button" type="button" class="wwp_show_wholesale_prices_link" data-product_id="%1$s">
                        <span>%2$s</span>
                    </a>
                </div>',
                $product_id,
                $message
            );

        }
    }

    /**
     * Show wholesale price to non wholesale customer under product price
     * WooCommerce Admin, Shop Managers, Guest, and Regular Customers should be able to access the Wholesale Prices Box
     *
     * @since 1.16.1
     * @access public
     *
     * @param  string     $price   Price html.
     * @param  WC_Product $product Product object.
     * @return string Price html, with the "See wholesale prices" link appended when applicable.
     */
    public function add_click_wholesale_price_for_non_wholesale_customers( $price, $product ) {
        // Do not show "see wholesale price" text in admin dashboard.
        if ( ! is_admin() ) {

            global $wc_wholesale_prices;

            $product_id            = $product->get_id();
            $user_wholesale_role   = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();
            $show_wholesale_prices = get_option( 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale' );

            if ( $product_id && 'yes' === $show_wholesale_prices && ( ! is_user_logged_in() || current_user_can( 'manage_woocommerce' ) || empty( $user_wholesale_role ) ) && ( is_shop() || is_product() ) ) {

                $product_type = WWP_Helper_Functions::wwp_get_product_type( $product );

                if ( in_array( $product_type, array( 'simple', 'variable' ), true ) ) {

                    $price .= $this->display_replacement_message_to_non_wholesale( $product );

                }
            }
        }

        return $price;
    }

    /**
     * Show Wholesale prices to non wholesale customer in WWOF
     * This function is being called by filter woocommerce_get_price_html
     *
     * @since 1.16.1
     *
     * @param string     $price   Price html.
     * @param WC_Product $product Product object.
     * @access public
     */
    public function show_wholesale_price_in_wwof( $price, $product ) {
        global $wc_wholesale_prices;

        $price_html          = '';
        $user_wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();

        // Check if WWOF beta is performing API request to WWP/WWPP.
        // If value wholesale role is present then the current user is wholesale customer.
        if ( isset( $_REQUEST['wholesale_role'] ) && ! empty( $_REQUEST['wholesale_role'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return $price;
        }

        if ( ( ! is_product() && ! is_shop() && ! is_cart() ) && ( ! is_user_logged_in() || current_user_can( 'manage_woocommerce' ) || empty( $user_wholesale_role ) ) ) {

            $price_html = $this->display_replacement_message_to_non_wholesale( $product );
        }

        return $price .= $price_html;
    }

    /**
     * Add default role value.
     * We need to have default value in order to use the feature "Show Wholesale Price to non-wholesale users"
     * Re-add the value when user activates WWPP then removes the role.
     *
     * @since 1.16.1
     * @access public
     */
    public function add_default_wholesale_role_value() {
        $role_value = get_option( 'wwp_non_wholesale_wholesale_role_select2', array() );

        // If only WWP is active and WWPP is deactivated and if role is empty then add a default value.
        // Note WWP will always have a default role value else the feature will be useless.
        if ( ( empty( $role_value ) || false === $role_value ) && ! WWP_Helper_Functions::is_wwpp_active() ) {

            update_option( 'wwp_non_wholesale_wholesale_role_select2', array( 'wholesale_customer' ), 'no' );

        }
    }

    /**
     * This function is responsible in executing all actions needed to run our application
     *
     * @since 1.15.0
     * @access public
     */
    public function run() {
        // Add default role value.
        add_action( 'init', array( $this, 'add_default_wholesale_role_value' ) );

        // Get available wholesale prices.
        add_action( 'wp_ajax_get_product_wholesale_prices_ajax', array( $this, 'get_product_wholesale_prices_ajax' ) );
        add_action( 'wp_ajax_nopriv_get_product_wholesale_prices_ajax', array( $this, 'get_product_wholesale_prices_ajax' ) );

        // Display "See wholesale prices" text.
        add_filter( 'woocommerce_get_price_html', array( $this, 'add_click_wholesale_price_for_non_wholesale_customers' ), 10, 2 );

        // Display "See wholesale prices" text in v2 and old form.
        add_filter( 'woocommerce_get_price_html', array( $this, 'show_wholesale_price_in_wwof' ), 9999, 2 );
    }
}
