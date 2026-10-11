<?php
// Exit if accessed directly.
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic of wholesale prices.
 *
 * @since 1.3.0
 * @since 2.2.9 Recalculate cart totals on woocommerce_add_to_cart for wholesale customers (issue #549), without suppressing WC core's priority-20 recalc (issues #923, #994).
 */
class WWP_Wholesale_Prices {

    /**
     * Class Properties
     */

    /**
     * Property that holds the single main instance of WWP_Wholesale_Prices.
     *
     * @since  1.3.0
     * @access private
     * @var WWP_Wholesale_Prices
     */
    private static $_instance;

    /**
     * Set of notices that have been printed.
     *
     * @var array
     */
    private static $printed_notices = array();

    /**
     * Product IDs whose variable attribute selectors have already been rendered this request,
     * keyed by product ID (issue #318). Marked from the woocommerce_before_variations_form seam
     * so a dispatcher that renders the variation form BEFORE woocommerce_single_product_summary
     * priority 30 (e.g. the woocommerce/add-to-cart-form block) suppresses this class's own
     * render for the same product. A dispatcher that runs AFTER priority 30 is outside that call
     * path and is not deduplicated — see display_variable_product_attribute_selectors() for the
     * full boundary.
     *
     * @since 2.3.0
     * @access private
     * @var array
     */
    private static $rendered_variation_form_product_ids = array();

    /**
     * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
     *
     * @since  1.5.0
     * @access private
     * @var WWP_Wholesale_Roles
     */
    private $_wwp_wholesale_roles;

    /**
     * Wholesale role key stored for use in posts_clauses filter callback.
     *
     * @since  2.2.8
     * @access private
     * @var string
     */
    private $wholesale_price_filter_role_key = '';

    /**
     * Minimum wholesale price stored for use in posts_clauses filter callback.
     *
     * @since  2.2.8
     * @access private
     * @var float
     */
    private $wholesale_price_filter_min = 0;

    /**
     * Maximum wholesale price stored for use in posts_clauses filter callback.
     *
     * @since  2.2.8
     * @access private
     * @var float
     */
    private $wholesale_price_filter_max = 0;

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
     */

    /**
     * WWP_Wholesale_Prices constructor.
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWP_Wholesale_Prices model.
     *
     * @since  1.3.0
     * @access public
     */
    public function __construct( $dependencies = array() ) {

        if ( isset( $dependencies['WWP_Wholesale_Roles'] ) ) {
            $this->_wwp_wholesale_roles = $dependencies['WWP_Wholesale_Roles'];
        }
    }

    /**
     * Ensure that only one instance of WWP_Wholesale_Prices is loaded or can be loaded (Singleton Pattern).
     *
     * @param array ...$dependencies Array of instance objects of all dependencies of WWP_Wholesale_Prices model.
     *
     * @since  1.3.0
     * @access public
     *
     * @return WWP_Wholesale_Prices
     */
    public static function instance( ...$dependencies ) {

        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( ...$dependencies );
        }

        return self::$_instance;
    }

    /**
     * Ensure that only one instance of WWP_Wholesale_Prices is loaded or can be loaded (Singleton Pattern).
     *
     * @since     1.3.0
     * @access    public
     * @return WWP_Wholesale_Prices
     * @deprecated: Will be remove on future versions
     */
    public static function getInstance() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Return product wholesale price for a given wholesale user role.
     * Still being used on WWOF 1.7.8
     *
     * @param int   $product_id          Product id.
     * @param array $user_wholesale_role Array of user wholesale roles.
     *
     * @since     1.0.0
     * @return string
     * @deprecated: Will be removed in future versions
     */
    public static function getUserProductWholesalePrice( $product_id, $user_wholesale_role ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        return self::getProductWholesalePrice( $product_id, $user_wholesale_role );
    }

    /**
     * Return product wholesale price for a given wholesale user role.
     *
     * @param int   $product_id          Product id.
     * @param array $user_wholesale_role Array of user wholesale roles.
     * @param int   $quantity            Quantity of product.
     *
     * @since      1.0.0
     * @return string
     * @deprecated To be removed for future versions.
     */
    public static function getProductWholesalePrice( $product_id, $user_wholesale_role, $quantity = 1 ) {

        if ( empty( $user_wholesale_role ) ) {

            return '';

        } else {

            // Get product object.
            $product = wc_get_product( $product_id );

            if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                $wholesale_price            = $product->get_meta( $user_wholesale_role[0] . '_wholesale_price', true );
                $baseCurrencyWholesalePrice = $wholesale_price;

                if ( $baseCurrencyWholesalePrice ) {

                    $activeCurrency = get_woocommerce_currency();
                    $baseCurrency   = WWP_ACS_Integration_Helper::get_product_base_currency( $product_id );

                    if ( $activeCurrency === $baseCurrency ) {
                        $wholesale_price = $baseCurrencyWholesalePrice;
                    } else { // Base Currency.

                        $wholesale_price = $product->get_meta( $user_wholesale_role[0] . '_' . $activeCurrency . '_wholesale_price', true );

                        if ( ! $wholesale_price ) {

                            /**
                             * This specific currency has no explicit wholesale price (Auto). Therefore will need to convert the wholesale price
                             * set on the base currency to this specific currency.
                             *
                             * This is why it is very important users set the wholesale price for the base currency if they want wholesale pricing
                             * to work properly with aelia currency switcher plugin integration.
                             */
                            $wholesale_price = WWP_ACS_Integration_Helper::convert( $baseCurrencyWholesalePrice, $activeCurrency, $baseCurrency );

                        }
                    }

                    $wholesale_price = apply_filters( 'wwp_filter_' . $activeCurrency . '_wholesale_price', $wholesale_price, $product_id, $user_wholesale_role, $quantity );

                } else {
                    $wholesale_price = '';
                }
                // Base currency not set. Ignore the rest of the wholesale price set on other currencies.

            } else {
                $wholesale_price = $product->get_meta( $user_wholesale_role[0] . '_wholesale_price', true );
            }

            return apply_filters( 'wwp_filter_wholesale_price', $wholesale_price, $product_id, $user_wholesale_role, $quantity );

        }
    }

    /**
     * Get product raw wholesale price. Without being passed through any filter.
     *
     * @param int|WC_Product $product             Product id or an already-hydrated product object.
     * @param array          $user_wholesale_role Array of user wholesale roles.
     *
     * @since  1.5.0
     * @since  1.6.3 Removed $quantity variable from the list of variables being passed to 'wwp_filter_' .
     *         $activeCurrency . '_wholesale_price' filter.
     * @since  2.2.9 Accept an already-hydrated WC_Product to avoid a redundant second product
     *         hydration when the caller already loaded the same product object.
     * @access public
     *
     * @return string Filtered wholesale price.
     */
    public static function get_product_raw_wholesale_price( $product, $user_wholesale_role ) {

        // Accept either a product id or an already-hydrated product object. Reusing the caller's
        // object avoids a redundant second hydration on the hot wholesale price path.
        $product = is_a( $product, 'WC_Product' ) ? $product : wc_get_product( $product );

        // check if valid product.
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return '';
        }

        $product_id = $product->get_id();

        if ( empty( $user_wholesale_role ) ) {
            $wholesale_price = '';
        } elseif ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

            $wholesale_price            = $product->get_meta( $user_wholesale_role[0] . '_wholesale_price', true );
            $baseCurrencyWholesalePrice = $wholesale_price;

            if ( $baseCurrencyWholesalePrice ) {

                $activeCurrency = get_woocommerce_currency();
                $baseCurrency   = WWP_ACS_Integration_Helper::get_product_base_currency( $product_id );

                if ( $activeCurrency === $baseCurrency ) {
                    $wholesale_price = $baseCurrencyWholesalePrice;
                } else { // Base Currency.

                    $wholesale_price = $product->get_meta( $user_wholesale_role[0] . '_' . $activeCurrency . '_wholesale_price', true );

                    if ( ! $wholesale_price ) {

                        /**
                         * This specific currency has no explicit wholesale price (Auto). Therefore will need to convert the wholesale price
                         * set on the base currency to this specific currency.
                         *
                         * This is why it is very important users set the wholesale price for the base currency if they want wholesale pricing
                         * to work properly with aelia currency switcher plugin integration.
                         */
                        $wholesale_price = WWP_ACS_Integration_Helper::convert( $baseCurrencyWholesalePrice, $activeCurrency, $baseCurrency );

                    }
                }

                $wholesale_price = apply_filters( 'wwp_filter_' . $activeCurrency . '_wholesale_price', $wholesale_price, $product_id, $user_wholesale_role );

            } else {
                $wholesale_price = '';
            }
            // Base currency not set. Ignore the rest of the wholesale price set on other currencies.

        } else {
            $wholesale_price = $product->get_meta( $user_wholesale_role[0] . '_wholesale_price', true );
        }

        /**
         * Allows to filter the raw wholesale price for a product.
         *
         * @param string     $wholesale_price     The raw product wholesale price.
         * @param WC_Product $product             The product object.
         * @param array      $user_wholesale_role The user wholesale roles.
         */
        return apply_filters(
            'wwp_get_product_raw_wholesale_price',
            $wholesale_price,
            $product,
            $user_wholesale_role
        );
    }

    /**
     * Return product wholesale price for a given wholesale user role.
     * With 'wwp_filter_wholesale_price_shop' filter already applied.
     * Replaces getProductWholesalePrice.
     *
     * @param int   $product_id          Product id.
     * @param array $user_wholesale_role Array of user wholesale roles.
     *
     * @since  1.5.0
     * @since  1.6.0 Deprecated.
     * @access public
     *
     * @return string Filtered wholesale price.
     */
    public static function get_product_wholesale_price_on_shop( $product_id, $user_wholesale_role ) {

        $price_arr = self::get_product_wholesale_price_on_shop_v3( $product_id, $user_wholesale_role );

        return $price_arr['wholesale_price'];
    }

    /**
     * Replacement for 'get_product_wholesale_price_on_shop'.
     * Returns an array containing wholesale price both passed through and not passed through taxing.
     *
     * @param int   $product_id          Product id.
     * @param array $user_wholesale_role Array of user wholesale roles.
     *
     * @since  1.10  Deprecated.
     * @access public
     *
     * @since  1.6.0
     * @since  1.6.3 Add 'wwp_filter_wholesale_price_shop_v2' filter.
     * @return array Array of wholesale price data.
     */
    public static function get_product_wholesale_price_on_shop_v2( $product_id, $user_wholesale_role ) {

        WWP_Helper_Functions::deprecated_function( debug_backtrace(), 'WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v2()', '1.10', 'WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3()' ); // phpcs:ignore

        $price_arr = array();

        $per_product_level_wholesale_price = self::get_product_raw_wholesale_price( $product_id, $user_wholesale_role );

        if ( empty( $per_product_level_wholesale_price ) ) {

            $result = apply_filters(
                'wwp_filter_wholesale_price_shop',
                array(
                    'source'          => 'per_product_level',
                    'wholesale_price' => $per_product_level_wholesale_price,
                ),
                $product_id,
                $user_wholesale_role,
                null,
                null
            );

            $price_arr['wholesale_price_with_no_tax'] = trim( $result['wholesale_price'] );
            $price_arr['source']                      = $result['source'];

        } else {

            $price_arr['wholesale_price_with_no_tax'] = $per_product_level_wholesale_price;
            $price_arr['source']                      = 'per_product_level';

        }

        $price_arr['wholesale_price'] = trim( apply_filters( 'wwp_pass_wholesale_price_through_taxing', $price_arr['wholesale_price_with_no_tax'], $product_id, $user_wholesale_role ) );

        return apply_filters( 'wwp_filter_wholesale_price_shop_v2', $price_arr, $product_id, $user_wholesale_role );
    }

    /**
     * Replacement for get_product_wholesale_price_on_shop_v2.
     * Returns an array containing of the raw wholesale price, wholesale price for display, and wholesale price without
     * tax.
     * - wholesale_price             = the price used in display after all calculation. dependent on all settings.
     * - raw_wholesale_price         = the raw amount value inputted on the wholesale price field.
     * - wholesale_price_with_no_tax = the wholesale price deducted of the calculated tax.
     *
     * @param int   $product_id          Product id.
     * @param array $user_wholesale_role Array of user wholesale roles.
     *
     * @since  2.0.2 Add filter to be used for caching wholesale price data. Feature is available in premium.
     * @access public
     *
     * @since  1.9
     * @since  1.12 "WooCommerce Currency Switcher" plugin support. Wrap wholesale_price_raw with
     *         "woocommerce_product_get_price" filter so that the wholesale prices is properly converted to selected
     *         currency.
     * @since  2.2.9 Bail early with an empty-price array when the id does not resolve to a valid WC_Product,
     *         preventing a fatal error on stale/orphaned Grouped product child ids. See issue #991.
     * @return array Array of wholesale price data.
     */
    public static function get_product_wholesale_price_on_shop_v3( $product_id, $user_wholesale_role ) {

        $price_arr = array();
        $user_id   = apply_filters( 'wwp_wholesale_price_current_user_id', get_current_user_id() );
        $product   = wc_get_product( $product_id );

        // Bail early when the id does not resolve to a valid product (e.g. a Grouped product's
        // "_children" postmeta still referencing a deleted/orphaned child id). Passing the resulting
        // "false" downstream into WooCommerce core's wc_get_price_including_tax()/wc_get_price_excluding_tax()
        // would fatal with "Call to a member function get_price() on bool". Return the standard
        // empty-price shape so consumers reading these keys keep working. See issue #991.
        if ( ! $product instanceof WC_Product ) {
            return apply_filters(
                'wwp_filter_wholesale_price_shop_v2',
                array(
                    'wholesale_price_raw'         => '',
                    'source'                      => '',
                    'wholesale_price'             => '',
                    'wholesale_price_with_no_tax' => '',
                    'wholesale_price_with_tax'    => '',
                ),
                $product_id,
                $user_wholesale_role
            );
        }

        $cache_data = apply_filters( 'wwp_get_product_wholesale_price_on_shop_v3_cache', false, $user_id, $product, $product_id, $user_wholesale_role );

        if ( ! empty( $cache_data ) ) {

            $price_arr = $cache_data;

        } else {

            // Reuse the product object already hydrated above instead of re-fetching it by id.
            // Fall back to the id when the object is invalid so the empty-result path is preserved.
            $per_product_level_wholesale_price = self::get_product_raw_wholesale_price( is_a( $product, 'WC_Product' ) ? $product : $product_id, $user_wholesale_role );

            if ( empty( $per_product_level_wholesale_price ) ) {

                $result = apply_filters(
                    'wwp_filter_wholesale_price_shop',
                    array(
                        'source'          => 'per_product_level',
                        'wholesale_price' => $per_product_level_wholesale_price,
                    ),
                    $product_id,
                    $user_wholesale_role,
                    null,
                    null
                );

                $price_arr['wholesale_price_raw'] = trim( $result['wholesale_price'] );
                $price_arr['source']              = $result['source'];

            } else {

                $price_arr['wholesale_price_raw'] = $per_product_level_wholesale_price;
                $price_arr['source']              = 'per_product_level';

            }

            // Single Product Page Wholesale Price "WooCommerce Currency Switcher" plugin support
            // "WooCommerce Currency Switcher" must be enabled and "Aelia Currency Switcher for WooCommerce" must be disabled.
            if (
                WWP_Helper_Functions::is_plugin_active( 'woocommerce-currency-switcher/index.php' ) &&
                ! WWP_Helper_Functions::is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' )
            ) {
                if ( ! empty( $price_arr['wholesale_price_raw'] ) && 'per_product_level' === $price_arr['source'] ) {
                    $price_arr['wholesale_price_raw'] = apply_filters( 'woocommerce_product_get_price', $price_arr['wholesale_price_raw'], $product );
                }
            }

            $price_arr['wholesale_price'] = trim( apply_filters( 'wwp_pass_wholesale_price_through_taxing', $price_arr['wholesale_price_raw'], $product_id, $user_wholesale_role ) );

            // when product price is inclusive of tax, we use the calculated wholesale_price here cause it has been deducted by tax.
            if ( wc_prices_include_tax() && $price_arr['wholesale_price_raw'] ) {
                $price_arr['wholesale_price_with_no_tax'] = WWP_Helper_Functions::wwp_get_price_excluding_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $price_arr['wholesale_price_raw'],
                    )
                );
            } else {
                $price_arr['wholesale_price_with_no_tax'] = $price_arr['wholesale_price_raw'];
            }

            $price_arr['wholesale_price_with_tax'] = WWP_Helper_Functions::wwp_get_price_including_tax(
                $product,
                array(
                    'qty'   => 1,
                    'price' => $price_arr['wholesale_price_raw'],
                )
            );

            if ( isset( $price_arr['wholesale_price'] ) && $price_arr['wholesale_price'] > 0 ) {

                do_action( 'wwp_after_get_product_wholesale_price_on_shop_v3', $user_id, $product, $product_id, $user_wholesale_role, $price_arr );

            }
        }

        return apply_filters( 'wwp_filter_wholesale_price_shop_v2', $price_arr, $product_id, $user_wholesale_role );
    }

    /**
     * Return product wholesale price for a given wholesale user role.
     * With 'wwp_filter_wholesale_price_cart' filter already applied.
     * The wholesale price returned is not passed through taxing filters.
     * No need to do it tho, coz we hooking on 'before_calculate_totals' hook so after our wholesale price computation,
     * WC will take care of passing it through taxing options.
     *
     * @param int     $product_id          Product id.
     * @param array   $user_wholesale_role Array of user wholesale roles.
     * @param array   $cart_item           Cart item data.
     * @param WC_Cart $cart_object         WC_Cart object.
     *
     * @since  1.6.0 Refactor codebase.
     * @since  1.12 Compatibility with "WooCommerce Currency Switcher by PluginUs.NET. Woo Multi Currency and Woo Multi
     *         Pay" plugin
     *
     * @access public
     *
     * @since  1.5.0
     * @return string Filtered wholesale price.
     */
    public static function get_product_wholesale_price_on_cart(
        $product_id,
        $user_wholesale_role,
        $cart_item,
        $cart_object
    ) {

        $wholesale_price = self::get_product_raw_wholesale_price( $product_id, $user_wholesale_role );

        global $WOOCS;

        if ( $WOOCS && empty( $wholesale_price ) ) {
            $_REQUEST['woocs_block_price_hook'] = true;
        }

        $result = apply_filters(
            'wwp_filter_wholesale_price_cart',
            array(
                'source'          => 'per_product_level',
                'wholesale_price' => $wholesale_price,
            ),
            $product_id,
            $user_wholesale_role,
            $cart_item,
            $cart_object
        );

        if ( $WOOCS && empty( $wholesale_price ) ) {
            unset( $_REQUEST['woocs_block_price_hook'] );
        }

        return isset( $result['wholesale_price'] ) ? trim( $result['wholesale_price'] ) : '';
    }

    /**
     * Get wholesale price suffix.
     *
     * @param WC_Product $product                     WC_Product object.
     * @param array      $user_wholesale_role         User wholesale role.
     * @param string     $wholesale_price             Wholesale price.
     * @param boolean    $return_wholesale_price_only Whether to return wholesale price markup only, used on product
     *                                                cpt listing.
     * @param array      $extra_args                  Extra arguments.
     *
     * @since  1.6.0
     * @since  1.7.0  When '{price_including_tax}', '{price_excluding_tax}' tags are used in the 'Price display suffix'
     *         dont return any computation since it will just use the regular price instead of wholesale price.
     * @since  1.11.5 We now support '{price_including_tax}', '{price_excluding_tax}' tags in our wholesale prices.
     * @since  1.16.1 Add filter to return value of $price_base
     * @since  2.1.5  Fix wholesale price suffix always display default wholesale role (wholesale_customer) price
     * @access public
     *
     * @return string Wholesale price suffix.
     */
    public static function get_wholesale_price_suffix(
        $product,
        $user_wholesale_role,
        $wholesale_price,
        $return_wholesale_price_only = false,
        $extra_args = array()
    ) {

        $wc_price_suffix = apply_filters( 'wwp_wholesale_price_suffix', get_option( 'woocommerce_price_display_suffix' ) );

        if ( ! empty( $user_wholesale_role ) ) {

            $price_arr  = self::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
            $base_price = apply_filters( 'wwp_wholesale_price_suffix_base_price', ! empty( $price_arr['wholesale_price_raw'] ) ? $price_arr['wholesale_price_raw'] : $product->get_regular_price(), $product );

            // To be used in function get_wholesale_price_display_suffix_filter of WWPP
            // For wholesale price display suffix.
            $extra_args['base_price'] = $base_price;

            if ( str_contains( $wc_price_suffix, '{price_including_tax}' ) ) {

                $wholesale_price_incl_tax = WWP_Helper_Functions::wwp_formatted_price(
                    WWP_Helper_Functions::wwp_get_price_including_tax(
                        $product,
                        array(
                            'qty'   => 1,
                            'price' => $base_price,
                        )
                    )
                );
                $wc_price_suffix          = str_replace( '{price_including_tax}', $wholesale_price_incl_tax, $wc_price_suffix );

            }

            if ( str_contains( $wc_price_suffix, '{price_excluding_tax}' ) ) {

                $wholesale_price_excl_tax = WWP_Helper_Functions::wwp_formatted_price(
                    WWP_Helper_Functions::wwp_get_price_excluding_tax(
                        $product,
                        array(
                            'qty'   => 1,
                            'price' => $base_price,
                        )
                    )
                );
                $wc_price_suffix          = str_replace( '{price_excluding_tax}', $wholesale_price_excl_tax, $wc_price_suffix );

            }

            $wc_price_suffix = ' <small class="woocommerce-price-suffix wholesale-price-suffix">' . $wc_price_suffix . '</small>';

        } else {
            $wc_price_suffix = $product->get_price_suffix();
        }

        return apply_filters( 'wwp_filter_wholesale_price_display_suffix', $wc_price_suffix, $product, $user_wholesale_role, $wholesale_price, $return_wholesale_price_only, $extra_args );
    }

    /**
     * Filter callback that alters the product price, it embeds the wholesale price of a product for a wholesale user.
     *
     * @param string     $price                       Product price in html.
     * @param WC_Product $product                     WC_Product instance.
     * @param array      $user_wholesale_role         User's wholesale role.
     * @param boolean    $return_wholesale_price_only Whether to only return the wholesale price markup. Used for
     *                                                products cpt listing.
     *
     * @since  1.0.0
     * @since  1.2.8 Now if empty $price then don't bother creating wholesale html price.
     * @since  1.5.0 Refactor codebase.
     * @since  1.6.0 Refactor codebase.
     * @since  2.2.9 Prime the post, term, and meta caches for all variations in bulk before the
     *               price-range loop to avoid a per-variation query (N+1) when the caches are cold.
     * @since  2.3.0 Normalise the original/retail price currency symbol to the active WCML client currency
     *               so it matches the wholesale price under WPML Multicurrency.
     * @access public
     *
     * @return string Product price with wholesale applied if necessary.
     */
    public function wholesale_price_html_filter( $price, $product, $user_wholesale_role = null, $return_wholesale_price_only = false ) {

        if ( is_null( $user_wholesale_role ) ) {
            // If get price html is called from rest api request, then get wholesale role from request.
            // The wholesale role verification is done in the rest api request.
            if ( WC()->is_rest_api_request() ) {
                $user_wholesale_role = isset( $_REQUEST['wholesale_role'] ) ? array( sanitize_text_field( wp_unslash( $_REQUEST['wholesale_role'] ) ) ) : $this->_wwp_wholesale_roles->getUserWholesaleRole(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            } else {
                $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();
            }
        }

        if ( ! empty( $user_wholesale_role ) && ! empty( $price ) ) {

            $wholesale_price_title_text = trim( apply_filters( 'wwp_filter_wholesale_price_title_text', __( 'Wholesale Price:', 'woocommerce-wholesale-prices' ) ) );
            $raw_wholesale_price        = '';
            $wholesale_price            = '';
            $source                     = '';
            $extra_args                 = array();

            if ( in_array(
                WWP_Helper_Functions::wwp_get_product_type( $product ),
                array(
                    'simple',
                    'variation',
                ),
                true
            ) ) {

                $price_arr           = self::get_product_wholesale_price_on_shop_v3( WWP_Helper_Functions::wwp_get_product_id( $product ), $user_wholesale_role );
                $raw_wholesale_price = $price_arr['wholesale_price'];
                $source              = $price_arr['source'];

                if ( strcasecmp( $raw_wholesale_price, '' ) !== 0 ) {

                    $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $raw_wholesale_price );

                    if ( ! $return_wholesale_price_only ) {
                        $wholesale_price .= self::get_wholesale_price_suffix( $product, $user_wholesale_role, $price_arr['wholesale_price_with_no_tax'], $return_wholesale_price_only );
                    }
                }
            } elseif ( WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variable' ) {

                $user_id    = apply_filters( 'wwp_wholesale_price_current_user_id', get_current_user_id() );
                $cache_data = apply_filters( 'wwp_get_variable_product_price_range_cache', false, $user_id, $product, $user_wholesale_role );

                // Do not use caching if $return_wholesale_price_only is true, coz this is used on cpt listing
                // and cpt listing callback is triggered unpredictably, and multiple times.
                // It is even triggered even before WC have initialized.
                if ( is_array( $cache_data ) && $cache_data['min_price'] && $cache_data['max_price'] && ! $return_wholesale_price_only ) {

                    $min_price                            = $cache_data['min_price'];
                    $min_wholesale_price_without_taxing   = $cache_data['min_wholesale_price_without_taxing'];
                    $max_price                            = $cache_data['max_price'];
                    $max_wholesale_price_without_taxing   = $cache_data['max_wholesale_price_without_taxing'];
                    $some_variations_have_wholesale_price = $cache_data['some_variations_have_wholesale_price'];

                } else {

                    // Fast path: resolve the min/max range with an aggregate query when parity with
                    // the per-variation loop is provable; null signals a fall back to the loop
                    // (e.g. WWPP percentage discounts, currency switchers, non-uniform taxing, or a
                    // third-party snippet altering individual variation prices).
                    $range = $this->get_variable_product_price_range_via_aggregate( $product, $user_wholesale_role );

                    if ( null === $range ) {
                        $range = $this->get_variable_product_price_range_via_loop( $product, $user_wholesale_role );
                    }

                    $min_price                            = $range['min_price'];
                    $min_wholesale_price_without_taxing   = $range['min_wholesale_price_without_taxing'];
                    $max_price                            = $range['max_price'];
                    $max_wholesale_price_without_taxing   = $range['max_wholesale_price_without_taxing'];
                    $some_variations_have_wholesale_price = $range['some_variations_have_wholesale_price'];

                    if ( ! $return_wholesale_price_only ) {

                        do_action(
                            'wwp_after_variable_product_compute_price_range',
                            $user_id,
                            $product,
                            $user_wholesale_role,
                            $range
                        );

                    }
                }

                // To be used in function get_wholesale_price_display_suffix_filter of WWPP
                // For wholesale price display suffix.
                $extra_args = array(
                    'min_price' => $min_price,
                    'max_price' => $max_price,
                );

                // Only alter price html if, some/all variations of this variable product have sale price and
                // min and max price have valid values.
                if ( $some_variations_have_wholesale_price && strcasecmp( $min_price, '' ) !== 0 && strcasecmp( $max_price, '' ) !== 0 ) {

                    if ( $min_price !== $max_price && $min_price < $max_price ) {

                        $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $min_price ) . ' - ' . WWP_Helper_Functions::wwp_formatted_price( $max_price );
                        $wc_price_suffix = get_option( 'woocommerce_price_display_suffix' );

                        if ( ! str_contains( $wc_price_suffix, '{price_including_tax}' ) && ! str_contains( $wc_price_suffix, '{price_excluding_tax}' ) && ! $return_wholesale_price_only ) {

                            $wsprice          = ! empty( $max_wholesale_price_without_taxing ) ? $max_wholesale_price_without_taxing : null;
                            $wholesale_price .= self::get_wholesale_price_suffix( $product, $user_wholesale_role, $wsprice, $return_wholesale_price_only, $extra_args );

                        }
                    } else {

                        $wholesale_price = WWP_Helper_Functions::wwp_formatted_price( $max_price );

                        if ( ! $return_wholesale_price_only ) {

                            $wsprice          = ! empty( $max_wholesale_price_without_taxing ) ? $max_wholesale_price_without_taxing : null;
                            $wholesale_price .= self::get_wholesale_price_suffix( $product, $user_wholesale_role, $wsprice, $return_wholesale_price_only, $extra_args );

                        }
                    }
                }

                $return_value = apply_filters(
                    'wwp_filter_variable_product_wholesale_price_range',
                    array(
                        'wholesale_price'             => $wholesale_price,
                        'price'                       => $price,
                        'product'                     => $product,
                        'user_wholesale_role'         => $user_wholesale_role,
                        'min_price'                   => $min_price,
                        'min_wholesale_price_without_taxing' => $min_wholesale_price_without_taxing,
                        'max_price'                   => $max_price,
                        'max_wholesale_price_without_taxing' => $max_wholesale_price_without_taxing,
                        'wholesale_price_title_text'  => $wholesale_price_title_text,
                        'return_wholesale_price_only' => $return_wholesale_price_only,
                    )
                );

                $wholesale_price = $return_value['wholesale_price'];

                if ( isset( $return_value['wholesale_price_title_text'] ) ) {
                    $wholesale_price_title_text = $return_value['wholesale_price_title_text'];
                }
            }

            // Third party plugins can use this filter to alter the wholesale price html before returning wholesale price only.
            $wholesale_price = apply_filters( 'wwp_before_wholesale_price_html_filter', $wholesale_price, $price, $product, $user_wholesale_role, $wholesale_price_title_text, $raw_wholesale_price, $source, $return_wholesale_price_only );

            if ( strcasecmp( $wholesale_price, '' ) !== 0 ) {

                $wholesale_price_html = '<span style="display: block;" class="wholesale_price_container">
                                            <span class="wholesale_price_title">' . $wholesale_price_title_text . '</span>
                                            <ins>' . $wholesale_price . '</ins>
                                        </span>';

                /**
                 * Filter wholesale price html before returning wholesale price only.
                 *
                 * @param string     $wholesale_price_html        The Wholesale price markup.
                 * @param string     $price                       Original product price.
                 * @param WC_Product $product                     Product object.
                 * @param array      $user_wholesale_role         Array of user wholesale roles.
                 * @param string     $wholesale_price_title_text  Wholesale price title text.
                 * @param int|string $raw_wholesale_price         Unformatted wholesale price. Only available for simple & variation products.
                 * @param string     $source                      Source of the wholesale price being applied.
                 * @param boolean    $return_wholesale_price_only Whether to only return the wholesale price markup.
                 * @param string     $wholesale_price             Formatted wholesale price.
                 */
                $wholesale_price_html = apply_filters( 'wwp_filter_wholesale_price_html_before_return_wholesale_price_only', $wholesale_price_html, $price, $product, $user_wholesale_role, $wholesale_price_title_text, $raw_wholesale_price, $source, $return_wholesale_price_only, $wholesale_price );

                if ( $return_wholesale_price_only ) {
                    return $wholesale_price_html;
                }

                // Normalise the original/retail price symbol to the active WCML client currency so it matches the
                // wholesale price (which is already formatted via wwp_formatted_price()); a no-op outside WCML multicurrency.
                $original_price = WWP_Helper_Functions::wwp_correct_price_html_currency_symbol( $price );

                // Pass the corrected markup as the 3rd argument too. Listeners such as
                // filter_product_original_price_visibility() rebuild the visible markup from that argument
                // (e.g. when a regular-price title text is set), so passing the raw $price there would discard
                // the symbol correction and reintroduce the mismatch under WCML multicurrency.
                $wholesale_price_html = apply_filters( 'wwp_product_original_price', '<del class="original-computed-price">' . $original_price . '</del>', $wholesale_price, $original_price, $product, $user_wholesale_role ) . $wholesale_price_html;

                /**
                 * Filter wholesale price html.
                 *
                 * @param string     $wholesale_price_html       The Wholesale price markup.
                 * @param string     $price                      Original product price.
                 * @param WC_Product $product                    Product object.
                 * @param array      $user_wholesale_role        Array of user wholesale roles.
                 * @param string     $wholesale_price_title_text Wholesale price title text.
                 * @param int|string $raw_wholesale_price        Unformatted wholesale price. Only available for simple & variation products.
                 * @param string     $source                     Source of the wholesale price being applied.
                 * @param string     $wholesale_price            Formatted wholesale price.
                 */
                return apply_filters( 'wwp_filter_wholesale_price_html', $wholesale_price_html, $price, $product, $user_wholesale_role, $wholesale_price_title_text, $raw_wholesale_price, $source, $wholesale_price );

            }
        }

        return apply_filters( 'wwp_filter_variable_product_price_range_for_none_wholesale_users', $price, $product );
    }

    /**
     * Compute a variable product's wholesale price range (min/max) by looping every purchasable
     * variation and running the full wholesale price chain per child.
     *
     * Extracted verbatim from the historical inline loop in
     * {@see WWP_Wholesale_Prices::wholesale_price_html_filter()} so it can serve as the fall-back when
     * {@see WWP_Wholesale_Prices::get_variable_product_price_range_via_aggregate()} cannot guarantee
     * byte-identical parity. Behaviour is unchanged from the inline loop.
     *
     * @param WC_Product $product             Variable product.
     * @param array      $user_wholesale_role User's wholesale role(s).
     *
     * @since  2.2.9
     * @access private
     *
     * @return array {
     *     Range payload.
     *
     *     @type int|string $min_price                            Lowest effective display price across variations.
     *     @type int|string $min_wholesale_price_without_taxing   No-tax wholesale price of the min owner, or '' if it had none.
     *     @type int|string $max_price                            Highest effective display price across variations.
     *     @type int|string $max_wholesale_price_without_taxing   No-tax wholesale price of the max owner, or '' if it had none.
     *     @type bool       $some_variations_have_wholesale_price Whether any variation resolved a wholesale price.
     * }
     */
    private function get_variable_product_price_range_via_loop( $product, $user_wholesale_role ) {

        $variations = $product->get_children();

        // Prime the post, term, and meta caches for every variation in a few bulk
        // queries so the per-variation product loads inside the loop below are served
        // from cache instead of triggering separate queries per variation. This is a
        // no-op when the caches are already warm (e.g. the frontend price render path).
        if ( ! empty( $variations ) ) {
            _prime_post_caches( $variations, true, true );
        }

        $min_price                            = '';
        $min_wholesale_price_without_taxing   = '';
        $max_price                            = '';
        $max_wholesale_price_without_taxing   = '';
        $some_variations_have_wholesale_price = false;

        foreach ( $variations as $variation_id ) {

            $variation = wc_get_product( $variation_id );
            if ( ! $variation || ! $variation->is_purchasable() ) {
                continue;
            }

            $curr_var_price = wc_get_price_to_display( $variation );
            $price_arr      = self::get_product_wholesale_price_on_shop_v3( $variation_id, $user_wholesale_role );

            if ( strcasecmp( $price_arr['wholesale_price'], '' ) !== 0 ) {

                $curr_var_price = $price_arr['wholesale_price'];

                if ( ! $some_variations_have_wholesale_price ) {
                    $some_variations_have_wholesale_price = true;
                }
            }

            if ( strcasecmp( $min_price, '' ) === 0 || $curr_var_price < $min_price ) {

                $min_price                          = $curr_var_price;
                $min_wholesale_price_without_taxing = strcasecmp( $price_arr['wholesale_price_with_no_tax'], '' ) !== 0 ? $price_arr['wholesale_price_with_no_tax'] : '';

            }

            if ( strcasecmp( $max_price, '' ) === 0 || $curr_var_price > $max_price ) {

                $max_price                          = $curr_var_price;
                $max_wholesale_price_without_taxing = strcasecmp( $price_arr['wholesale_price_with_no_tax'], '' ) !== 0 ? $price_arr['wholesale_price_with_no_tax'] : '';

            }
        }

        return array(
            'min_price'                            => $min_price,
            'min_wholesale_price_without_taxing'   => $min_wholesale_price_without_taxing,
            'max_price'                            => $max_price,
            'max_wholesale_price_without_taxing'   => $max_wholesale_price_without_taxing,
            'some_variations_have_wholesale_price' => $some_variations_have_wholesale_price,
        );
    }

    /**
     * Compute a variable product's wholesale price range (min/max) with an aggregate query instead of
     * hydrating and price-computing every variation.
     *
     * Returns the same range payload as
     * {@see WWP_Wholesale_Prices::get_variable_product_price_range_via_loop()} in O(1) queries, OR null
     * when the fast path cannot guarantee byte-identical parity with the loop — in which case the
     * caller falls back to the loop. The path is taken only for the plain WWP scenario where a
     * variation's wholesale price is its explicit "{role}_wholesale_price" meta (no premium percentage
     * discounts) and the tax transform is uniform across variations, so min/max over the raw effective
     * prices maps to min/max over the displayed prices.
     *
     * Parity guards (any failure returns null, signalling a loop fall-back):
     * - WWPP active: per-variation prices can come from general/category/per-product percentage
     *   discounts resolved inside WWPP via the 'wwp_filter_wholesale_price_shop' filter, and WWPP owns
     *   the wholesale tax transform — neither is visible to this free aggregate. (WWPP's own O(1) range
     *   computation is a separate premium-side enhancement.)
     * - Aelia / WooCommerce Currency Switcher active: per-product currency conversion and
     *   currency-specific meta keys ("{role}_{currency}_wholesale_price").
     * - A callback on any per-variation price extension filter ('wwp_filter_wholesale_price_shop',
     *   'wwp_filter_wholesale_price_shop_v2', 'wwp_get_product_raw_wholesale_price',
     *   'wwp_filter_wholesale_price') — these are unused in WWP, so a callback means a third-party
     *   snippet alters individual variation prices.
     * - A callback on the visibility / purchasability filters ('woocommerce_variation_is_visible',
     *   'woocommerce_variation_is_purchasable', 'woocommerce_is_purchasable') — the aggregate
     *   approximates is_purchasable() as "published + priced", which a plugin (e.g. Subscriptions,
     *   Bundles, hide-out-of-stock-variations) can override via any of these.
     * - Non-uniform taxing: when taxes are calculated, any variation overriding the parent's tax class
     *   or tax status breaks the uniform-monotonic-transform assumption.
     *
     * @param WC_Product $product             Variable product.
     * @param array      $user_wholesale_role User's wholesale role(s).
     *
     * @since  2.2.9
     * @access private
     *
     * @return array|null Range payload (same shape as the loop) or null to fall back to the loop.
     */
    private function get_variable_product_price_range_via_aggregate( $product, $user_wholesale_role ) {

        global $wpdb;

        if ( empty( $user_wholesale_role ) || ! is_a( $product, 'WC_Product' ) ) {
            return null;
        }

        // Premium / currency / third-party hooks the free aggregate cannot reproduce -> fall back.
        if (
            WWP_Helper_Functions::is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) ||
            WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ||
            WWP_Helper_Functions::is_plugin_active( 'woocommerce-currency-switcher/index.php' ) ||
            has_filter( 'wwp_filter_wholesale_price_shop' ) ||
            has_filter( 'wwp_filter_wholesale_price_shop_v2' ) ||
            has_filter( 'wwp_get_product_raw_wholesale_price' ) ||
            has_filter( 'wwp_filter_wholesale_price' ) ||
            has_filter( 'woocommerce_variation_is_visible' ) ||
            has_filter( 'woocommerce_variation_is_purchasable' ) ||
            has_filter( 'woocommerce_is_purchasable' )
        ) {
            return null;
        }

        $parent_id         = WWP_Helper_Functions::wwp_get_product_id( $product );
        $meta_key          = $user_wholesale_role[0] . '_wholesale_price';
        $calc_taxes        = 'yes' === get_option( 'woocommerce_calc_taxes', false );
        $tax_display_incl  = 'incl' === get_option( 'woocommerce_tax_display_shop', false );
        $parent_tax_status = $product->get_tax_status();
        $parent_tax_class  = $product->get_tax_class();

        // One aggregate over the purchasable (published + priced) children. Per child, "eff" is the
        // explicit "{role}_wholesale_price" meta when set, else the active "_price" value, and "has_ws"
        // flags whether the child has an explicit wholesale price.
        // GROUP_CONCAT(...) ordered to mirror WC_Product::get_children() (menu_order, ID) yields the
        // has_ws flag of the variation that "owns" the min / max, matching the loop's first-wins ties.
        // class_overrides / status_mismatches detect non-uniform taxing across the children. A child whose
        // _tax_class meta is absent (NULL) or 'parent' inherits the parent's class; an explicit '' is the
        // Standard rate (NOT inherit), so it is uniform only when the parent is also Standard. Hence the
        // comparison is against the parent's own class (%s) rather than a blanket "non-empty" override test.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    MIN( t.eff ) AS min_raw,
                    MAX( t.eff ) AS max_raw,
                    SUM( t.has_ws ) AS ws_count,
                    COUNT(*) AS n,
                    SUBSTRING_INDEX( GROUP_CONCAT( t.has_ws ORDER BY t.eff ASC, t.menu_order ASC, t.post_id ASC ), ',', 1 ) AS min_owner_has_ws,
                    SUBSTRING_INDEX( GROUP_CONCAT( t.has_ws ORDER BY t.eff DESC, t.menu_order ASC, t.post_id ASC ), ',', 1 ) AS max_owner_has_ws,
                    SUM( CASE WHEN t.tax_class IS NULL OR t.tax_class IN ( 'parent', %s ) THEN 0 ELSE 1 END ) AS class_overrides,
                    SUM( CASE WHEN COALESCE( NULLIF( t.tax_status, '' ), 'taxable' ) <> %s THEN 1 ELSE 0 END ) AS status_mismatches
                FROM (
                    SELECT
                        p.ID AS post_id,
                        p.menu_order AS menu_order,
                        CAST( COALESCE( NULLIF( ws.meta_value, '' ), price.meta_value ) AS DECIMAL(10,2) ) AS eff,
                        CASE WHEN ws.meta_value IS NOT NULL AND ws.meta_value <> '' THEN 1 ELSE 0 END AS has_ws,
                        tc.meta_value AS tax_class,
                        ts.meta_value AS tax_status
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} price ON price.post_id = p.ID AND price.meta_key = '_price'
                    LEFT JOIN {$wpdb->postmeta} ws ON ws.post_id = p.ID AND ws.meta_key = %s
                    LEFT JOIN {$wpdb->postmeta} tc ON tc.post_id = p.ID AND tc.meta_key = '_tax_class'
                    LEFT JOIN {$wpdb->postmeta} ts ON ts.post_id = p.ID AND ts.meta_key = '_tax_status'
                    WHERE p.post_parent = %d
                        AND p.post_type = 'product_variation'
                        AND p.post_status = 'publish'
                        AND price.meta_value <> ''
                ) t",
                $parent_tax_class,
                $parent_tax_status,
                $meta_key,
                $parent_id
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // A query error (e.g. an unsupported SQL function on a non-MySQL backend, where this aggregate's
        // GROUP_CONCAT( ... ORDER BY ... ) / SUBSTRING_INDEX are unavailable) makes get_row() return null.
        // Fall back to the loop in that case rather than the empty-range branch below, which would hide
        // the product's range. Unreachable on the supported MySQL/MariaDB stack.
        if ( '' !== $wpdb->last_error ) {
            return null;
        }

        if ( ! $row || (int) $row->n < 1 || null === $row->min_raw ) {
            // No purchasable children: mirror the loop producing an empty (unshown) range.
            return array(
                'min_price'                            => '',
                'min_wholesale_price_without_taxing'   => '',
                'max_price'                            => '',
                'max_wholesale_price_without_taxing'   => '',
                'some_variations_have_wholesale_price' => false,
            );
        }

        // Non-uniform taxing would break the monotonic-transform assumption -> fall back to the loop.
        if ( $calc_taxes && ( (int) $row->class_overrides > 0 || (int) $row->status_mismatches > 0 ) ) {
            return null;
        }

        $min_raw         = $row->min_raw;
        $max_raw         = $row->max_raw;
        $prices_incl_tax = wc_prices_include_tax();

        return array(
            'min_price'                            => $this->apply_price_range_tax_display( $product, $min_raw, $calc_taxes, $tax_display_incl ),
            'min_wholesale_price_without_taxing'   => '1' === $row->min_owner_has_ws ? $this->price_range_wholesale_no_tax( $product, $min_raw, $prices_incl_tax ) : '',
            'max_price'                            => $this->apply_price_range_tax_display( $product, $max_raw, $calc_taxes, $tax_display_incl ),
            'max_wholesale_price_without_taxing'   => '1' === $row->max_owner_has_ws ? $this->price_range_wholesale_no_tax( $product, $max_raw, $prices_incl_tax ) : '',
            'some_variations_have_wholesale_price' => (int) $row->ws_count > 0,
        );
    }

    /**
     * Apply the shop's tax-display transform to an aggregate boundary price, mirroring the per-variation
     * paths the loop uses (wc_get_price_to_display() for regular prices and the
     * 'wwp_pass_wholesale_price_through_taxing' callback for wholesale prices). Both resolve to the same
     * wc_get_price_including_tax() / wc_get_price_excluding_tax() under the uniform-tax guard, so a single
     * transform applied via the parent product is byte-identical to the per-variation computation.
     *
     * @param WC_Product   $product          Parent variable product (children inherit its tax treatment).
     * @param float|string $price            Raw boundary price.
     * @param bool         $calc_taxes       Whether taxes are calculated store-wide.
     * @param bool         $tax_display_incl Whether the shop displays prices inclusive of tax.
     *
     * @since  2.2.9
     * @access private
     *
     * @return float|string Display price.
     */
    private function apply_price_range_tax_display( $product, $price, $calc_taxes, $tax_display_incl ) {

        if ( ! $calc_taxes ) {
            return $price;
        }

        $args = array(
            'qty'   => 1,
            'price' => $price,
        );

        return $tax_display_incl
            ? WWP_Helper_Functions::wwp_get_price_including_tax( $product, $args )
            : WWP_Helper_Functions::wwp_get_price_excluding_tax( $product, $args );
    }

    /**
     * Derive the "wholesale price without taxing" for an aggregate boundary owner, mirroring
     * {@see WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3()}: when shop prices are stored
     * inclusive of tax the raw value is reduced by tax, otherwise it is returned as-is.
     *
     * @param WC_Product   $product         Parent variable product.
     * @param float|string $raw             Raw wholesale price of the boundary owner.
     * @param bool         $prices_incl_tax Whether stored prices include tax.
     *
     * @since  2.2.9
     * @access private
     *
     * @return float|string Wholesale price excluding tax.
     */
    private function price_range_wholesale_no_tax( $product, $raw, $prices_incl_tax ) {

        if ( $prices_incl_tax ) {
            return WWP_Helper_Functions::wwp_get_price_excluding_tax(
                $product,
                array(
                    'qty'   => 1,
                    'price' => $raw,
                )
            );
        }

        return $raw;
    }

    /**
     * Apply product wholesale price upon adding to cart.
     *
     * @param WC_Cart $cart_object The woocommerce cart object.
     *
     * @since  1.2.3 Add filter hook 'wwp_filter_get_custom_product_type_wholesale_price' for which extensions can
     *         attach and add support for custom product types.
     * @since  1.4.0 Add filter hook 'wwp_wholesale_requirements_not_passed' for which extensions can attach and do
     *         something whenever wholesale requirement is not meet.
     * @since  1.5.0 Rewrote the code for speed and efficiency.
     * @access public
     *
     * @since  1.0.0
     */
    public function apply_product_wholesale_price_to_cart( $cart_object ) {

        $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();

        if ( empty( $user_wholesale_role ) ) {
            return false;
        }

        $cart_contents                   = $cart_object->cart_contents;
        $per_product_requirement_notices = array();
        $has_cart_items                  = false;
        $cart_total                      = 0;
        $cart_items                      = 0;
        $cart_items_price_cache          = array(); // Holds the original prices of products in cart.
        $wwp_data                        = array();

        do_action( 'wwp_before_apply_product_wholesale_price_cart_loop', $cart_object, $user_wholesale_role );

        foreach ( $cart_contents as $cart_item_key => $cart_item ) {

            if ( ! $has_cart_items ) {
                $has_cart_items = true;
            }

            $wwp_data[ $cart_item_key ] = array();
            $wholesale_price            = '';

            if ( in_array(
                WWP_Helper_Functions::wwp_get_product_type( $cart_item['data'] ),
                array(
                    'simple',
                    'variation',
                    'subscription',
                ),
                true
            ) ) {
                $wholesale_price = self::get_product_wholesale_price_on_cart( WWP_Helper_Functions::wwp_get_product_id( $cart_item['data'] ), $user_wholesale_role, $cart_item, $cart_object );
            } else {
                $wholesale_price = apply_filters( 'wwp_filter_get_custom_product_type_wholesale_price', $wholesale_price, $cart_item, $user_wholesale_role, $cart_object );
            }

            if ( '' !== $wholesale_price ) {

                if ( get_option( 'woocommerce_prices_include_tax' ) === 'yes' ) {
                    $wp = wc_get_price_excluding_tax(
                        $cart_item['data'],
                        array(
                            'qty'   => 1,
                            'price' => $wholesale_price,
                        )
                    );
                } else {
                    $wp = $wholesale_price;
                }

                $apply_product_level_wholesale_price = apply_filters( 'wwp_apply_wholesale_price_per_product_level', true, $cart_item, $cart_object, $user_wholesale_role, $wp );

                if ( true === $apply_product_level_wholesale_price ) {

                    $cart_items_price_cache[ $cart_item_key ] = $cart_item['data']->get_price();
                    $cart_item['data']->set_price( WWP_Helper_Functions::wwp_wpml_price( $wholesale_price ) );
                    $wwp_data[ $cart_item_key ] = array(
                        'wholesale_priced' => 'yes',
                        'wholesale_role'   => $user_wholesale_role[0],
                    );

                } else {

                    if ( is_array( $apply_product_level_wholesale_price ) ) {
                        $per_product_requirement_notices[] = $apply_product_level_wholesale_price;
                    }

                    $wwp_data[ $cart_item_key ] = array(
                        'wholesale_priced' => 'no',
                        'wholesale_role'   => $user_wholesale_role[0],
                    );

                }
            } else {
                $wwp_data[ $cart_item_key ] = array(
                    'wholesale_priced' => 'no',
                    'wholesale_role'   => $user_wholesale_role[0],
                );
            }

            if ( apply_filters( 'wwp_include_cart_item_on_cart_totals_computation', true, $cart_item, $user_wholesale_role ) ) {

                if ( $wholesale_price ) {

                    if ( get_option( 'woocommerce_prices_include_tax' ) === 'yes' ) {
                        $wp = wc_get_price_excluding_tax(
                            $cart_item['data'],
                            array(
                                'qty'   => 1,
                                'price' => $wholesale_price,
                            )
                        );
                    } else {
                        $wp = $wholesale_price;
                    }
                } else {
                    $wp = $cart_item['data']->get_price();
                }

                $cart_total += (float) $wp * $cart_item['quantity'];
                $cart_items += $cart_item['quantity'];

            }
        } // Cart loop

        do_action( 'wwp_after_apply_product_wholesale_price_cart_loop', $cart_object, $user_wholesale_role );

        $apply_wholesale_price_cart_level = apply_filters( 'wwp_apply_wholesale_price_cart_level', true, $cart_total, $cart_items, $cart_object, $user_wholesale_role );

        if ( ( $has_cart_items && true !== $apply_wholesale_price_cart_level ) || ! empty( $per_product_requirement_notices ) ) {
            do_action( 'wwp_wholesale_requirements_not_passed', $cart_object, $user_wholesale_role );
        }

        if ( $has_cart_items && true !== $apply_wholesale_price_cart_level ) {

            // Revert back to original pricing.
            foreach ( $cart_contents as $cart_item_key => $cart_item ) {

                if ( array_key_exists( $cart_item_key, $cart_items_price_cache ) ) {

                    $cart_item['data']->set_price( $cart_items_price_cache[ $cart_item_key ] );

                    $wwp_data[ $cart_item_key ] = array(
                        'wholesale_priced' => 'no',
                        'wholesale_role'   => $user_wholesale_role[0],
                    );
                }
            }

            if ( ( is_cart() || is_checkout() || WWP_Helper_Functions::has_wc_cart_block() || WWP_Helper_Functions::has_wc_checkout_block() ) &&
                ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
                $this->printWCNotice( $apply_wholesale_price_cart_level );
            }
        }

        if ( ! empty( $per_product_requirement_notices ) ) {
            foreach ( $per_product_requirement_notices as $notice ) {
                if ( ( is_cart() || is_checkout() || WWP_Helper_Functions::has_wc_cart_block() || WWP_Helper_Functions::has_wc_checkout_block() ) &&
                    ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
                    $this->printWCNotice( $per_product_requirement_notices );
                }
            }
        }

        // Add additional wwp data to cart item. This is used for WWS Reporting.
        foreach ( $cart_contents as $cart_item_key => $cart_item ) {
            if ( array_key_exists( $cart_item_key, $wwp_data ) ) {
                $cart_item['wwp_data']           = apply_filters( 'wwp_add_cart_item_meta', $wwp_data[ $cart_item_key ], $cart_item, $cart_object, $user_wholesale_role );
                $cart_contents[ $cart_item_key ] = $cart_item;
            }
        }
        $cart_object->set_cart_contents( $cart_contents );

        return true;
    }

    /**
     * Reapply wholesale pricing when the cart is loaded from the session, for the classic cart only.
     *
     * WooCommerce rebuilds the cart contents from the session on every page load with fresh,
     * plain-priced product instances and only re-runs calculate_totals() when the cart changed
     * or no totals are cached yet. On a plain reload of an unchanged cart, neither condition
     * holds, so the classic Cart page's row Price and Subtotal columns (which read the product
     * price live at render time) fall back to the undiscounted price while the cached Cart Totals
     * box stays correct. Re-applying the wholesale price here keeps them consistent.
     *
     * The block-based cart is skipped because it updates through the Store API and reverts
     * minimum-order-quantity pricing on its own; re-applying here would leave stale prices in it.
     *
     * @since 2.2.9
     *
     * @param WC_Cart $cart_object The cart object.
     *
     * @return void
     */
    public function reapply_wholesale_price_on_classic_cart( $cart_object ) {

        // Detect the block cart by passing the cart page id explicitly: this hook fires on
        // wp_loaded, before the main query sets the global post, so the page-less has_block()
        // helper cannot be used reliably here.
        if ( has_block( 'woocommerce/cart', wc_get_page_id( 'cart' ) ) ) {
            return;
        }

        $this->apply_product_wholesale_price_to_cart( $cart_object );
    }

    /**
     * Recalculate cart totals.
     * We need to do this on loading widget cart to properly sync the cart item prices.
     * If we don't do this, the cart item line price will not be sync with what's on the cart.
     *
     * @since  1.5.0
     * @access public
     */
    public function recalculate_cart_totals() {

        WC()->cart->calculate_totals();
    }

    /**
     * Force cart totals recalculation when a wholesale customer adds a product to the cart.
     *
     * WooCommerce's WC_Cart::add_to_cart() does not invoke calculate_totals() before firing the
     * woocommerce_add_to_cart action, so cart item line totals (line_subtotal, line_total,
     * line_tax) are NULL at that point and the cart item's product data still reflects the
     * regular price (the wholesale price is normally applied via woocommerce_before_calculate_totals).
     * Third-party plugins that capture cart state on woocommerce_add_to_cart - such as FunnelKit
     * Automations abandoned cart tracking - therefore see empty/zero values for wholesale customers.
     *
     * Hooking at priority 1 and triggering calculate_totals here runs the
     * woocommerce_before_calculate_totals chain so wholesale prices are applied, and populates
     * each cart item's line totals before any later listener on woocommerce_add_to_cart runs.
     * The work is limited to wholesale customers to keep regular customers unaffected.
     *
     * Two defensive guards short-circuit the recalc:
     * 1. WC()->cart null-guard - the woocommerce_add_to_cart action normally fires from inside
     *    WC_Cart::add_to_cart() (so the cart exists), but a third-party plugin can invoke the
     *    action manually without a session-loaded cart. Calling calculate_totals() on a missing
     *    cart would fatal.
     * 2. Re-entrancy guard - if a plugin's listener on the calculate_totals chain itself calls
     *    WC_Cart::add_to_cart() (e.g. an auto-add upsell on woocommerce_before_calculate_totals),
     *    triggering another calculate_totals() from within the chain risks corrupted state or
     *    infinite recursion. Skip in that case.
     *
     * IMPORTANT: unlike the original 2.2.8 implementation (reverted in #928), this deliberately
     * does NOT remove WC core's priority-20 calculate_totals callback on woocommerce_add_to_cart.
     * Core hooks WC_Cart_Session::set_session() onto woocommerce_after_calculate_totals, so this
     * priority-1 recalc persists a cart-session snapshot taken BEFORE plugins like WooCommerce
     * Product Bundles (priority 9) or Composite Products insert their child items directly into
     * cart_contents. Core's later priority-20 pass recalculates with those children present and
     * re-persists the complete session - suppressing it is what dropped bundled/composite child
     * items from the persisted cart for wholesale customers (issues #923, #994). The cost of
     * leaving it in place is one extra calculate_totals() per wholesale add-to-cart - the same
     * work every non-wholesale add-to-cart already performs. Under batched add-to-cart flows
     * (e.g. WWOF's Store API batch, which dispatches woocommerce_add_to_cart once per item in
     * a single request) that extra pass compounds per item - an accepted tradeoff (see #994).
     *
     * @since  2.2.9 Re-introduced without the core priority-20 hook suppression (issues #549, #923, #994).
     * @access public
     *
     * @return void
     */
    public function recalculate_cart_totals_for_wholesale_customer_on_add_to_cart() {

        if ( empty( $this->_wwp_wholesale_roles->getUserWholesaleRole() ) ) {
            return;
        }

        if ( ! ( WC()->cart instanceof WC_Cart ) ) {
            return;
        }

        if (
            doing_action( 'woocommerce_before_calculate_totals' ) ||
            doing_action( 'woocommerce_after_calculate_totals' ) ||
            doing_action( 'woocommerce_calculate_totals' )
        ) {
            return;
        }

        $this->recalculate_cart_totals();
    }

    /**
     * Apply taxing accordingly to wholesale prices on shop page.
     * We will handle tax application to wholesale prices only on WWP if WWPP is not present.
     * If WWPP is present lets allow WWPP to handle this instead.
     * This is only applied on shop page, we dont need to do this on cart/checkout prices.
     * WC will take care of that coz we are hooking to 'before_calculate_totals' so after we apply wholesale pricing on
     * cart/checkout page, WC will then apply taxing above it.
     *
     * @param float $wholesale_price     Wholesale price.
     * @param int   $product_id          Product Id.
     * @param array $user_wholesale_role User wholesale roles.
     *
     * @since  1.5.0
     * @access public
     *
     * @return float Modified wholesale price.
     */
    public function apply_taxing_to_wholesale_prices_on_shop_page(
        $wholesale_price,
        $product_id,
        $user_wholesale_role
    ) {

        if ( ! WWP_Helper_Functions::is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) && ! empty( $wholesale_price ) && ! empty( $user_wholesale_role ) && get_option( 'woocommerce_calc_taxes', false ) === 'yes' ) {

            $product                      = wc_get_product( $product_id );
            $woocommerce_tax_display_shop = get_option( 'woocommerce_tax_display_shop', false );

            if ( 'incl' === $woocommerce_tax_display_shop ) {
                $wholesale_price = WWP_Helper_Functions::wwp_get_price_including_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $wholesale_price,
                    )
                );
            } else {
                $wholesale_price = WWP_Helper_Functions::wwp_get_price_excluding_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $wholesale_price,
                    )
                );
            }
        }

        return $wholesale_price;
    }

    /**
     * Print WP Notices.
     *
     * Notices are echoed immediately, so they only reach the response when this runs while the page
     * is being rendered. Cart-modifying requests recalculate the totals on `wp_loaded` — before the
     * template stage — and requests such as the classic cart "Update cart" POST go on to render the
     * page in the same request. Emitting there would send the markup ahead of the document and
     * record the message in the printed notices list, suppressing the emission that would otherwise
     * have landed on the rendered page. So skip any emission that happens before the main query has
     * run and leave it to the render pass.
     *
     * @param string|array $notices WWP/P related notices.
     *
     * @since  1.0.7
     * @since  2.2.9 Skip emission before the main query has run so the notice is not lost on
     *               requests that recalculate the cart totals ahead of rendering the page.
     * @access public
     *
     * @return void
     */
    public function printWCNotice( $notices ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        if ( ! did_action( 'wp' ) ) {
            return;
        }

        if ( is_array( $notices ) && array_key_exists( 'message', $notices ) && array_key_exists( 'type', $notices )
            && ! in_array( $notices['message'], self::$printed_notices, true ) ) {
            // Pre Version 1.2.0 of wwpp where it sends back single dimension array of notice.

            wc_print_notice( $notices['message'], $notices['type'] );
            self::$printed_notices[] = $notices['message'];

        } elseif ( is_array( $notices ) && ! array_key_exists( 'message', $notices ) && ! array_key_exists( 'type', $notices ) ) {
            // Version 1.2.0 of wwpp where it sends back multiple notice via multi dimensional arrays.

            foreach ( $notices as $notice ) {

                if ( array_key_exists( 'message', $notice ) && array_key_exists( 'type', $notice )
                    && ! in_array( $notice['message'], self::$printed_notices, true ) ) {
                    wc_print_notice( $notice['message'], $notice['type'] );
                    self::$printed_notices[] = $notice['message'];
                }
            }
        }
    }

    /**
     * Fix issue regarding meta role key being lowercased after product import.
     * Issue 1: Addressed the issue with aelia currency wholesale price not detecting after import. WWP-160
     * Issue 2: Addressed the issue with uppercase wholesale role key not detected the wholesale price after import.
     * WWPP-657 Reason for that is WC tends to lowercase the meta keys while the currency is in uppercase or role has
     * uppercase letter so wp won't detect the meta properly. ex 1: instead of 'wholesale_customer_USD_wholesale_price'
     * wc imports the key as wholesale_customer_usd_wholesale_price.
     *
     * @param array  $data     WC Product Data.
     * @param object $importer WC_Product_CSV_Importer Object.
     *
     * @since  1.8
     * @access public
     *
     * @return array
     */
    public function update_meta_data_with_proper_meta_keys( $data, $importer ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        $aelia_currency_switcher_active = WWP_ACS_Integration_Helper::aelia_currency_switcher_active();

        if ( isset( $data['meta_data'] ) ) {

            $wholesale_roles         = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $wacs_enabled_currencies = WWP_ACS_Integration_Helper::enabled_currencies();

            if ( ! empty( $wholesale_roles ) ) {

                foreach ( $wholesale_roles as $role_key => $role_data ) {

                    $pattern = '/' . strtolower( $role_key ) . '_([a-z]+)_wholesale_price/';

                    foreach ( $data['meta_data'] as $key => $meta ) {

                        // Aelia Currency Fix.
                        if ( $aelia_currency_switcher_active ) {

                            preg_match( $pattern, $meta['key'], $matches );

                            if ( isset( $matches[1] ) && in_array( strtoupper( $matches[1] ), $wacs_enabled_currencies, true ) ) {

                                $updated_key                      = $role_key . '_' . strtoupper( $matches[1] ) . '_wholesale_price';
                                $data['meta_data'][ $key ]['key'] = $updated_key;
                                $meta['key']                      = $updated_key;

                            }
                        }

                        // Wholesale role key with uppercase letter fix.
                        if ( str_contains( $meta['key'], strtolower( $role_key ) ) ) {
                            $data['meta_data'][ $key ]['key'] = str_replace( strtolower( $role_key ), $role_key, $meta['key'] );
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Add the wholesale price to the variation data on the single product page form.
     *
     * @param array                $data      Variation data.
     * @param WC_Product_Variable  $variable  Parent variable product object.
     * @param WC_Product_Variation $variation Variation product object.
     *
     * @since  1.9
     * @access public
     */
    public function add_wholesale_price_to_variation_data( $data, $variable, $variation ) {

        $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();

        if ( ! empty( $user_wholesale_role ) ) {

            $price_arr = self::get_product_wholesale_price_on_shop_v3( $variation->get_id(), $user_wholesale_role );

            if ( isset( $price_arr['wholesale_price'] ) && $price_arr['wholesale_price'] ) {
                $data['wholesale_price'] = (float) $price_arr['wholesale_price'];
            }

            if ( isset( $price_arr['wholesale_price_raw'] ) && $price_arr['wholesale_price_raw'] ) {
                $data['wholesale_price_raw'] = (float) $price_arr['wholesale_price_raw'];
            }

            if ( isset( $price_arr['wholesale_price_with_no_tax'] ) && $price_arr['wholesale_price_with_no_tax'] ) {
                $data['wholesale_price_with_no_tax'] = (float) $price_arr['wholesale_price_with_no_tax'];
            }

            if ( isset( $price_arr['wholesale_price_with_tax'] ) && $price_arr['wholesale_price_with_tax'] ) {
                $data['wholesale_price_with_tax'] = (float) $price_arr['wholesale_price_with_tax'];
            }
        }

        return $data;
    }

    /**
     * Set coupons availability to wholesale users.
     * Used to show/hide original product price.
     *
     * @param boolean $enabled Coupons available flag.
     *
     * @since  1.11
     * @access public
     *
     * @return bool Filtered coupons available flag.
     */
    public function toggle_availability_of_coupons_to_wholesale_users( $enabled ) {

        $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();
        $user_wholesale_role = ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : '';

        if ( get_option( 'wwpp_settings_disable_coupons_for_wholesale_users' ) === 'yes' && ! empty( $user_wholesale_role ) ) {
            $enabled = false;
        }

        return $enabled;
    }

    /**
     * There's a bug on wwpp where wholesale users can still avail coupons even if 'Disable Coupons For Wholesale
     * Users' option is enabled. They can do this by applying coupon to cart first before logging in as wholesale user.
     * Therefore when wholesale user visits cart/checkout pages, we check if 'Disable Coupons For Wholesale Users' is
     * enabled. If so then we remove coupons to the cart.
     *
     * Hooked into:
     * - woocommerce_before_cart / woocommerce_before_checkout_form (legacy shortcode pages)
     * - woocommerce_load_cart_from_session (block-based cart/checkout — fires on every cart-from-session load)
     *
     * @since  1.11
     * @since  2.2.8 Added empty-coupon guard, explicit calculate_totals()/set_session() to ensure the
     *               cleared coupon list is persisted on block-based pages that don't fire the legacy hooks.
     * @access public
     */
    public function remove_coupons_for_wholesale_users_when_necessary() {

        $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();
        $user_wholesale_role = ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : '';

        if ( get_option( 'wwpp_settings_disable_coupons_for_wholesale_users' ) === 'yes' && ! empty( $user_wholesale_role ) ) {
            $applied_coupons = WC()->cart->get_applied_coupons();
            if ( ! empty( $applied_coupons ) ) {
                WC()->cart->remove_coupons();
                WC()->cart->calculate_totals();
                WC()->cart->set_session();
            }
        }
    }

    /**
     * Filter the crossed out original price visibility.
     *
     * @param string     $original_price      Crossed out original price html.
     * @param float      $wholesale_price     wholesale price.
     * @param float      $price               Original price.
     * @param WC_Product $product             Product object.
     * @param array      $user_wholesale_role User wholesale role.
     *
     * @return string Filtered crossed out original price html.
     */
    public function filter_product_original_price_visibility( $original_price, $wholesale_price, $price, $product, $user_wholesale_role ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if ( get_option( 'wwpp_settings_hide_original_price' ) === 'yes' ) {
            $original_price = '';
        } else {
            $wholesale_regular_text = apply_filters( 'wwp_filter_wholesale_regular_price_title_text', '', $original_price, $wholesale_price, $price, $product, $user_wholesale_role );

            if ( ! empty( trim( $wholesale_regular_text ) ) ) {
                $original_price = '<del style="display: block; text-decoration: none;" class="wholesale_regular_price_container">
                                            <span class="wholesale_regular_price_title">' . $wholesale_regular_text . '</span>
                                            <ins>' . $price . '</ins>
                                        </del>';
            }
        }

        return $original_price;
    }

    /**
     * Filter the text for the wholesale price title.
     *
     * @param string $title_text Wholesale price title text.
     *
     * @since 1.11
     * @return mixed
     */
    public function filter_wholesale_price_title_text( $title_text ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        $setting_title_text = esc_attr( trim( get_option( 'wwpp_settings_wholesale_price_title_text' ) ) );

        return $setting_title_text;
    }

    /**
     * Filter the text for the regular price title.
     *
     * @param string     $title_text Regular price title text.
     * @param float      $original_price Original price.
     * @param float      $wholesale_price Wholesale price.
     * @param float      $price Product price.
     * @param WC_Product $product Product object.
     * @param array      $user_wholesale_role User wholesale role.
     *
     * @since 2.2.2
     * @return mixed
     */
    public function filter_wholesale_regular_price_title_text( $title_text, $original_price, $wholesale_price, $price, $product, $user_wholesale_role ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        $setting_title_text = esc_attr( trim( get_option( 'wwpp_settings_regular_price_title_text' ) ) );

        return $setting_title_text;
    }

    /**
     * Determine whether the price and add-to-cart button should be hidden for the current visitor.
     *
     * Wraps the shared condition (logged-out visitor with the "Hide Price and Add to Cart button" option
     * enabled) behind the wwp_hide_price_and_add_to_cart_button filter so every caller evaluates it
     * identically and the rule has a single source of truth.
     *
     * @since  2.2.9 Extracted from the duplicated hide-price condition.
     * @access public
     *
     * @return bool True when the price and add-to-cart button should be hidden.
     */
    public function should_hide_price_and_add_to_cart_button() {

        return (bool) apply_filters( 'wwp_hide_price_and_add_to_cart_button', ! is_user_logged_in() && get_option( 'wwp_hide_price_add_to_cart' ) === 'yes' ? true : false );
    }

    /**
     * Handles hiding Price and Add to Cart button when "Hide Price and Add to Cart button" option is enabled.
     *
     * @since  1.13
     * @since  2.2.9 Use the shared should_hide_price_and_add_to_cart_button() helper.
     * @since  2.3.0 Keep variable products' attribute/variation dropdowns visible (issue #318): stop removing
     *         woocommerce_variable_add_to_cart, re-dispatch it type-scoped via
     *         display_variable_product_attribute_selectors(), and blank per-variation price/stock output
     *         instead of removing the variation form outright.
     * @access public
     */
    public function hide_price_and_add_to_cart_button() {

        $hide_price_and_add_to_cart_button = $this->should_hide_price_and_add_to_cart_button();

        if ( $hide_price_and_add_to_cart_button ) {
            remove_action( 'woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30 );
            remove_action( 'woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', 30 );
            remove_action( 'woocommerce_external_add_to_cart', 'woocommerce_external_add_to_cart', 30 );
            remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
            remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );

            /**
             * The woocommerce_template_single_add_to_cart hook is a type-agnostic dispatcher that would
             * re-add the add-to-cart form for every product type (subscription, bundle, composite, etc.)
             * if left in place, so it stays removed above. Variable products need their attribute
             * dropdowns back (issue #318), so re-dispatch woocommerce_variable_add_to_cart() ourselves,
             * scoped to the variable product type only, via display_variable_product_attribute_selectors().
             */
            add_action( 'woocommerce_single_product_summary', array( $this, 'display_variable_product_attribute_selectors' ), 30 );

            /**
             * WooCommerce's own variable-product template fires woocommerce_before_variations_form on
             * every path that renders the variation form, regardless of which dispatcher triggered it
             * (our own call above, or the woocommerce/add-to-cart-form block). Mark the product as
             * rendered there so display_variable_product_attribute_selectors()'s once-per-product guard
             * also covers a dispatcher that runs BEFORE woocommerce_single_product_summary priority 30
             * in the same request. A dispatcher that runs AFTER priority 30 (e.g. a page-builder widget
             * calling woocommerce_template_single_add_to_cart() directly later in the request) is outside
             * this method's own call path and is not deduplicated by this guard.
             */
            add_action( 'woocommerce_before_variations_form', array( $this, 'mark_variation_form_rendered' ), 10 );

            /**
             * Remove the qty input, Add to cart button, and variation_id/add-to-cart hidden inputs for the
             * selected variation, while keeping the priority 10 empty container that
             * wc-add-to-cart-variation.js needs to render into.
             */
            remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );

            /**
             * Skip WooCommerce's per-variation get_price_html() build entirely; together with the
             * blanking filter below this guarantees no variation .price element is ever rendered.
             */
            add_filter( 'woocommerce_show_variation_price', '__return_false' );

            /**
             * Blank the per-variation price and stock-availability data returned to the variation form's
             * JS, at a late priority so no other integration re-populates it afterwards.
             */
            add_filter( 'woocommerce_available_variation', array( $this, 'blank_hidden_variation_data' ), 999, 3 );

            /**
             * This small line of code will render the product unpurchasable and do it in a pretty simple way,
             * and no hooks or templates will be removed, thus no incompatibility issues would creep up.
             *
             * This will remove the add to cart button
             */
            add_filter( 'woocommerce_is_purchasable', '__return_false', 999 );

            /**
             * Hide also Click to See Wholesale Prices for non wholesale customers
             */
            add_filter(
                'wwp_show_wholesale_prices_to_non_wholesale_customers',
                function ( $show_wholesale_prices ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
                    return 'no';
                },
                11,
                1
            );

            /**
             * Empty prices for other theme compatibility, some themes have custom hooks
             */
            add_filter( 'woocommerce_get_price_html', array( $this, 'remove_product_prices' ), 10, 2 );

            /**
             * The template-layer removals above leave two machine-readable copies of the price in place
             * for the same guest: the Product JSON-LD WooCommerce prints in the footer, and the raw
             * `prices` fields of the Store API product routes (which the All Products block, product
             * filters, and any anonymous request read). Strip both at a late priority so nothing
             * re-populates them afterwards.
             */
            add_filter( 'woocommerce_structured_data_product', array( $this, 'remove_hidden_structured_data_offers' ), 999, 2 );
            add_filter( 'rest_request_after_callbacks', array( $this, 'blank_hidden_store_api_prices' ), 999, 3 );
        }
    }

    /**
     * Re-dispatch WooCommerce's variable product attribute/variation dropdowns when the "Hide Price and Add
     * to Cart button" option hides pricing for the current visitor (issue #318).
     *
     * The woocommerce_template_single_add_to_cart hook is removed above because it is a type-agnostic
     * dispatcher (removing it also blocks non-variable, non-price-bearing add-to-cart forms such as
     * subscription or bundle products from leaking through). This method allowlists ONLY the variable
     * product type and calls WooCommerce's own woocommerce_variable_add_to_cart() directly, so the
     * attribute selectors still render while the variation price/stock/add-to-cart button stay hidden
     * via the sibling hooks below.
     *
     * Covers the classic single-product template (woocommerce_single_product_summary) and the block-based
     * "Add to Cart Form" block (woocommerce/add-to-cart-form), which also fires
     * woocommerce_variable_add_to_cart() internally. The block-based "Add to Cart with Options" block
     * (woocommerce/add-to-cart-with-options) does not use this action and is a declared degradation
     * boundary: it is not covered by this method and falls back to WooCommerce's own default rendering.
     *
     * A dispatcher that runs BEFORE this method in the same request (e.g. the woocommerce/add-to-cart-form
     * block rendering ahead of woocommerce_single_product_summary) also renders WooCommerce's
     * variable-product template, which fires woocommerce_before_variations_form once per rendered form.
     * mark_variation_form_rendered() marks the product as rendered there, so this method's guard also
     * covers that earlier dispatch and skips its own render. A dispatcher that runs AFTER this method in
     * the same request (e.g. a page-builder widget calling woocommerce_template_single_add_to_cart()
     * directly later in the page) is outside this method's own call path and is not deduplicated — it
     * would render a second variation form.
     *
     * Known, deliberate degradation boundary: WooCommerce's variable-product template also fires
     * woocommerce_before_add_to_cart_form, woocommerce_after_add_to_cart_form,
     * woocommerce_before_single_variation, and woocommerce_after_single_variation unconditionally around
     * the form, and this method does not suppress them. A theme or plugin that attaches a purchase-intent
     * widget there (e.g. an express-checkout "Buy now" button) can render it to a guest whose pricing is
     * hidden, even though the underlying purchase is already blocked server-side
     * (woocommerce_is_purchasable is forced false). Blanket-removing third-party callbacks on these
     * widely-used extension points was considered and rejected: it would also strip unrelated,
     * purchase-unrelated widgets (share buttons, trust badges, size guides, etc.) that legitimately rely
     * on the same hooks, an unbounded blast radius this method cannot safely take on generically. If a
     * specific integration is found to leak a purchase control this way, target it by name in a follow-up.
     *
     * @since 2.3.0
     * @access public
     *
     * @return void
     */
    public function display_variable_product_attribute_selectors() {

        global $product;

        if ( ! ( $product instanceof WC_Product ) || ! $product->is_type( 'variable' ) ) {
            return;
        }

        if ( isset( self::$rendered_variation_form_product_ids[ $product->get_id() ] ) ) {
            return;
        }

        woocommerce_variable_add_to_cart();
    }

    /**
     * Mark the current global product as having had its variation form rendered this request
     * (issue #318). Hooked to woocommerce_before_variations_form, which WooCommerce's own
     * variable-product template fires on every path that renders the form — this is the seam
     * display_variable_product_attribute_selectors()'s once-per-product guard relies on to cover
     * a separate add-to-cart surface dispatched outside the product summary hook.
     *
     * @since 2.3.0
     * @access public
     *
     * @return void
     */
    public function mark_variation_form_rendered() {

        global $product;

        if ( $product instanceof WC_Product ) {
            self::$rendered_variation_form_product_ids[ $product->get_id() ] = true;
        }
    }

    /**
     * Blank the per-variation price and stock data sent to the variation form's JS when the
     * "Hide Price and Add to Cart button" option hides pricing for the current visitor (issue #318).
     *
     * Blanks the fields the variation template renders (price_html, display_price, display_regular_price,
     * availability_html) plus max_qty, which carries the raw stock quantity of a managed-stock variation.
     * Also unsets the raw wholesale-price fields add_wholesale_price_to_variation_data() writes into the
     * same payload at priority 10 on this filter: that method only bails for a visitor with no wholesale
     * role, so a logged-in wholesale-role visitor for whom the hide gate is filtered true (e.g. via
     * wwp_hide_price_and_add_to_cart_button) would otherwise still receive their raw wholesale prices in
     * data-product_variations even though price_html/display_price are blank. The remaining payload
     * (is_in_stock, sku, weight, dimensions, images) is ordinary public catalog data and is left as
     * WooCommerce sends it. woocommerce_get_stock_html and woocommerce_ajax_variation_threshold are
     * intentionally left untouched so stock display elsewhere and the >30-variation AJAX threshold are
     * unaffected site-wide.
     *
     * @param array                $variation_data Data for a single variation, sent to the variation form's JS.
     * @param WC_Product_Variable  $product        The parent variable product.
     * @param WC_Product_Variation $variation      The variation product.
     *
     * @since 2.3.0
     * @access public
     *
     * @return array
     */
    public function blank_hidden_variation_data( $variation_data, $product, $variation ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

        $variation_data['price_html']            = '';
        $variation_data['display_price']         = '';
        $variation_data['display_regular_price'] = '';
        $variation_data['availability_html']     = '';
        $variation_data['max_qty']               = '';

        unset(
            $variation_data['wholesale_price'],
            $variation_data['wholesale_price_raw'],
            $variation_data['wholesale_price_with_no_tax'],
            $variation_data['wholesale_price_with_tax']
        );

        return $variation_data;
    }

    /**
     * Remove Prices if Hide price and add to cart button is enabled
     *
     * @param string $prices  The price html.
     * @param object $product The product object.
     *
     * @since  1.16.1
     * @access public
     *
     * @return boolean
     */
    public function remove_product_prices( $prices, $product ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        return '';
    }

    /**
     * Drop the `offers` node from WooCommerce's Product JSON-LD while "Hide Price and Add to Cart button"
     * hides pricing for the current visitor, so the structured data in the page footer does not disclose
     * the price the template just hid.
     *
     * Registered on woocommerce_structured_data_product only from within the hide branch of
     * hide_price_and_add_to_cart_button(), so it never runs for visitors who can see prices.
     *
     * @since 2.3.0
     * @access public
     *
     * @param array      $markup  Product structured data markup.
     * @param WC_Product $product Product the markup describes.
     *
     * @return array Markup without the `offers` node.
     */
    public function remove_hidden_structured_data_offers( $markup, $product ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

        unset( $markup['offers'] );

        return $markup;
    }

    /**
     * Blank the price fields of Store API product responses while "Hide Price and Add to Cart button"
     * hides pricing for the current visitor.
     *
     * The Store API product schema reads raw product prices and exposes no filter of its own, so the
     * response is edited after the route callback. The route guard matches any `wc/store[/vN]/products`
     * route, but the method only rewrites responses that actually carry price data: a single product
     * (`products/{id}`), the product collection (`wc/store/v1/products`), and the price-range aggregate
     * (`products/collection-data`, used by price filters). Sibling `products/*` sub-routes such as
     * `reviews`, `attributes`, `categories`, `tags`, and `brands` carry no `prices`/`price_range` key,
     * so they fall through untouched — a no-op, not an edit. `price_html` is already blank via the
     * woocommerce_get_price_html filter. Registered on rest_request_after_callbacks only from within the
     * hide branch of hide_price_and_add_to_cart_button().
     *
     * @since 2.3.0
     * @access public
     *
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
     * @param array                                            $handler  Route handler used for the request.
     * @param WP_REST_Request                                  $request  Request used to generate the response.
     *
     * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed The response, with price fields blanked on Store API product routes.
     */
    public function blank_hidden_store_api_prices( $response, $handler, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

        if ( ! ( $response instanceof WP_REST_Response ) || ! ( $request instanceof WP_REST_Request ) ) {
            return $response;
        }

        if ( ! preg_match( '#^/wc/store(?:/v\d+)?/products(?:/|$)#', $request->get_route() ) ) {
            return $response;
        }

        $data = $response->get_data();

        if ( ! is_array( $data ) ) {
            return $response;
        }

        if ( isset( $data['prices'] ) ) {
            // Single product.
            $data = $this->blank_store_api_product_prices( $data );
        } elseif ( isset( $data['price_range'] ) ) {
            // products/collection-data aggregate.
            $data['price_range'] = null;
        } else {
            // Product collection.
            foreach ( $data as $key => $item ) {
                if ( is_array( $item ) && isset( $item['prices'] ) ) {
                    $data[ $key ] = $this->blank_store_api_product_prices( $item );
                }
            }
        }

        $response->set_data( $data );

        return $response;
    }

    /**
     * Blank the `prices` fields of one Store API product item, keeping the currency metadata the
     * schema requires.
     *
     * @since 2.3.0
     * @access private
     *
     * @param array $item Store API product item.
     *
     * @return array The item with `price`, `regular_price`, and `sale_price` emptied and `price_range` nulled.
     */
    private function blank_store_api_product_prices( $item ) {

        $prices = (array) $item['prices'];

        foreach ( array( 'price', 'regular_price', 'sale_price' ) as $field ) {
            if ( array_key_exists( $field, $prices ) ) {
                $prices[ $field ] = '';
            }
        }

        if ( array_key_exists( 'price_range', $prices ) ) {
            $prices['price_range'] = null;
        }

        $item['prices'] = (object) $prices;

        return $item;
    }

    /**
     * Handles displaying replacement message for Price and Add to Cart button when "Hide Price and Add to Cart button"
     * option is enabled.
     *
     * @since  1.13
     * @since  2.1.5 Separate logic on how to get the price and add to cart replacement message so the function is
     *         reusable
     * @since  2.2.9 Use the shared should_hide_price_and_add_to_cart_button() helper.
     * @access public
     */
    public function display_replacement_message() {

        $hide_price_and_add_to_cart_button = $this->should_hide_price_and_add_to_cart_button();

        if ( $hide_price_and_add_to_cart_button ) {
            echo wp_kses( $this->get_price_and_add_to_cart_replacement_message(), $this->get_replacement_message_allowed_html() );
        }
    }

    /**
     * Get the HTML tags allowed when escaping the price and add to cart replacement message.
     *
     * Shared by every output path that prints the replacement message so they escape consistently.
     *
     * @since 2.2.9 Extracted so the inline and Elementor output paths escape the message identically.
     *
     * @return array The allowed HTML tags and attributes for wp_kses().
     */
    private function get_replacement_message_allowed_html() {

        return array(
            'a' => array(
                'href'  => array(),
                'class' => array(),
            ),
        );
    }

    /**
     * Show the price and add-to-cart replacement message inside Elementor's "Product Price" widget.
     *
     * Elementor's WooCommerce "Product Price" widget renders the price through its own widget,
     * bypassing the woocommerce_single_product_summary / woocommerce_after_shop_loop_item actions
     * that normally output the replacement message. When the "Hide Price and Add to Cart button"
     * option hides the price for the current visitor, the widget would otherwise be left blank, so
     * replace its content with the replacement message.
     *
     * @since 2.2.9 Output the replacement message in Elementor's Product Price widget when the price is hidden.
     *
     * @param string $widget_content The widget's rendered HTML.
     * @param object $widget         The Elementor widget instance.
     *
     * @return string The widget content, or the replacement message when the price is hidden.
     */
    public function show_replacement_message_in_elementor_price_widget( $widget_content, $widget ) {

        if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'woocommerce-product-price' !== $widget->get_name() ) {
            return $widget_content;
        }

        $hide_price_and_add_to_cart_button = $this->should_hide_price_and_add_to_cart_button();

        if ( ! $hide_price_and_add_to_cart_button ) {
            return $widget_content;
        }

        return wp_kses( $this->get_price_and_add_to_cart_replacement_message(), $this->get_replacement_message_allowed_html() );
    }

    /**
     * Get the replacement message for price and add to cart when "Hide Price and Add to Cart button" option is enabled.
     *
     * @since  2.1.5
     * @access public
     */
    public function get_price_and_add_to_cart_replacement_message() {

        $message = get_option( 'wwp_price_and_add_to_cart_replacement_message' );

        if ( empty( $message ) ) {
            $message = '<a class="wwp-login-to-see-wholesale-prices" href="' . get_permalink( wc_get_page_id( 'myaccount' ) ) . '">' . __( 'Login to see prices', 'woocommerce-wholesale-prices' ) . '</a>';
        } else {
            $message = html_entity_decode( $message );

            $message = $this->wwp_get_translated_text( $message, 'wwp_price_and_add_to_cart_replacement_message' );
        }

        return apply_filters( 'wwp_display_replacement_message', $message );
    }

    /**
     * Clear Product Transient on Tax Settings Save
     *
     * This will clear product transient when there is a change in WC Tax settings to properly apply the price tax, and
     * will only run if wwpp is not activated.
     *
     * - The problem is that when the Tax Settings in WC > Tax options, specially on "Prices entered with tax" options
     * has been change. The products price tax are not properly applied.
     *
     * @since 2.1.2
     */
    public function clear_product_transient_on_tax_settings_save() {

        // We will only execute "wc_delete_product_transients" if WWPP is not activated.
        if ( ! WWP_Helper_Functions::is_wwpp_active() && function_exists( 'wc_delete_product_transients' ) ) {

            wc_delete_product_transients();

        }
    }

    /**
     * Handles hide Add to Cart button in WooCommerce Product Blocks when "Hide Price and Add to Cart button" option is
     * enabled.
     *
     * @param string     $html    Product grid item HTML.
     * @param object     $data    Product data passed to the template.
     * @param WC_Product $product Product object.
     *
     * @since  2.1.5
     * @since  2.2.9 Use the shared should_hide_price_and_add_to_cart_button() helper.
     * @access public
     */
    public function hide_add_to_cart_button_wc_blocks( $html, $data, $product ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

        $hide_price_and_add_to_cart_button = $this->should_hide_price_and_add_to_cart_button();

        if ( $hide_price_and_add_to_cart_button ) {

            $replacement_message = $this->get_price_and_add_to_cart_replacement_message();

            return "<li class=\"wc-block-grid__product\">
                <a href=\"{$data->permalink}\" class=\"wc-block-grid__product-link\">
                    {$data->image}
                    {$data->title}
                </a>
                {$data->badge}
                {$data->price}
                {$data->rating}
                $replacement_message
            </li>";
        }

        return $html;
    }

    /**
     * Customize the product price on the cart.
     *
     * @param string $price     The price of the product.
     * @param array  $cart_item The cart item details.
     *
     * @return string
     */
    public function filter_product_price( $price, $cart_item ) {

        if ( ! empty( $cart_item['wwp_data']['wholesale_priced'] ) && 'yes' === $cart_item['wwp_data']['wholesale_priced'] &&
            method_exists( $cart_item['data'], 'get_regular_price' ) ) {
            /**
             * Get the product object from the cart item.
             *
             * @var WC_Product $product
             */
            [
                'data' => $product,
            ] = $cart_item;

            $original_price = '';
            if ( get_option( 'wwpp_settings_hide_original_price', 'no' ) === 'no' ) {
                $original_price = wc_price( $product->get_regular_price() );
                $original_price = sprintf( '<del class="original-computed-price">%s</del><br>', $original_price );
            }
            $price = sprintf(
                '%1$s<span class="wholesale_price_container"><span class="wholesale_price_title">%2$s</span><ins style="margin-left: 0.6180469716em;">%3$s</ins></span>',
                $original_price,
                esc_html( trim( apply_filters( 'wwp_filter_wholesale_price_title_text', __( 'Wholesale Price:', 'woocommerce-wholesale-prices' ) ) ) ),
                $price
            );
        }

        return $price;
    }

    /**
     * Register the wholesale prices data to the cart item block.
     *
     * @return void
     */
    public function wc_cart_item_block_wwp_data() {
        if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
            woocommerce_store_api_register_endpoint_data(
                array(
                    'endpoint'        => CartItemSchema::IDENTIFIER,
                    'namespace'       => 'rymera_wwp',
                    'data_callback'   => array( $this, 'get_cart_block_item_wwp_data' ),
                    'schema_callback' => array( $this, 'get_cart_block_item_wwp_schema' ),
                    'schema_type'     => ARRAY_A,
                )
            );
        }
    }

    /**
     * Adds wholesale prices data to the cart item block.
     *
     * @param array $cart_item Cart item.
     *
     * @since 2.2.0
     * @return array
     */
    public function get_cart_block_item_wwp_data( $cart_item ) {

        return array(
            'wwp_data' => $cart_item['wwp_data'] ?? null,
        );
    }

    /**
     * Returns the schema for the wholesale prices data in the cart item block.
     *
     * @since 2.2.0
     * @return array[]
     */
    public function get_cart_block_item_wwp_schema() {

        return array(
            'wwp_data' => array(
                'description' => __( 'Wholesale Prices data.', 'woocommerce-wholesale-prices' ),
                'type'        => 'array',
                'readonly'    => true,
            ),
        );
    }

    /**
     * Customize cart block price HTML markup
     *
     * @since 2.2.0
     * @return void
     */
	public function wc_cart_block_price_html() {

        global $wc_wholesale_prices;

		if ( ( ( is_cart() && has_block( 'woocommerce/cart' ) ) || has_block( 'woocommerce/cart' ) ) || ( ( is_checkout() && has_block( 'woocommerce/checkout' ) ) || has_block( 'woocommerce/checkout' ) ) ) {

            wp_enqueue_script( 'wwp-cart-checkout-block-js', WWP_JS_URL . 'frontend/cart-checkout-block.js', array( 'jquery', 'wp-element', 'wc-blocks-checkout' ), $wc_wholesale_prices::VERSION, true );

			if ( get_option( 'wwpp_settings_hide_original_price', null ) === 'yes' ) {
				$css = <<<'CSS'
.wwp-wholesale-priced .price del.wc-block-components-product-price__regular {
  display: none;
}
.wwp-wholesale-priced .price ins.wc-block-components-product-price__value {
  margin-left: 0;
}
CSS;
				wp_add_inline_style( 'woocommerce-inline', $css );
			}
		}
	}


    /**
     * Register the options for translation.
     *
     * @param string $value The value of the option.
     * @param string $option The option key.
     *
     * @since 2.2.3
     * @return string
     */
    public function wwp_wpml_translatable_options( $value, $option ) {
        // Check if WPML is active.
        if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
            return $value;
        }

        $translatable_options = array(
			'wwp_price_and_add_to_cart_replacement_message',
		);

        $translatable_options = apply_filters( 'wwp_translatable_options', $translatable_options );

        if ( in_array( $option, $translatable_options, true ) ) {
            $title   = 'WWP Option';
            $package = array(
                'kind'      => $title,
                'kind_slug' => 'woocommerce-wholesale-prices',
                'name'      => 'option',
                'title'     => $title,
            );

            // Register the option for translation.
            do_action( 'wpml_register_string', $value, $option, $package, $title, $package['kind'] );
        }

        return $value;
    }

    /**
     * Get the translated text.
     *
     * @param string $message The message to translate.
     * @param string $option_key The option key.
     *
     * @since 2.2.3
     * @return string
     */
    public function wwp_get_translated_text( $message, $option_key ) {
        if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
            $title   = 'WWP Option';
            $package = array(
                'kind'      => $title,
                'kind_slug' => 'woocommerce-wholesale-prices',
                'title'     => $title,
                'name'      => 'option',
            );

            return apply_filters( 'wpml_translate_string', $message, $option_key, $package );
        }

        return $message;
    }

    /**
     * Get the general discount percentage for a wholesale role.
     *
     * General discounts are configured in WWPP and stored as an option mapping
     * wholesale roles to percentage discounts. This helper retrieves the discount
     * for the given role, returning 0 if none is set or if WWPP is not active.
     *
     * @since 2.2.7
     *
     * @param string $wholesale_role The wholesale role key.
     *
     * @return float The discount percentage, or 0 if none set.
     */
    public static function get_general_discount_for_role( $wholesale_role ) {

        $discount_mapping = get_option( 'wwpp_option_wholesale_role_general_discount_mapping', array() );

        if ( ! empty( $discount_mapping ) && is_array( $discount_mapping ) && isset( $discount_mapping[ $wholesale_role ] ) ) {
            return (float) $discount_mapping[ $wholesale_role ];
        }

        return 0;
    }

    /**
     * Build the wholesale price filter meta query for a given role and price range.
     *
     * Constructs an OR meta query matching products with per-product wholesale prices,
     * variable product wholesale prices, and (if a general discount is set) products
     * whose retail price falls within the reverse-calculated range. Also provides a
     * filter hook for premium plugins (e.g. WWPP) to add category-level conditions.
     *
     * @since 2.2.7
     * @since 2.2.9 Cast the wholesale price BETWEEN clause as DECIMAL(10,2) instead of NUMERIC so prices below $1.00 are not truncated to 0.
     *
     * @param string $role_key  The sanitized wholesale role key.
     * @param float  $min_price The minimum wholesale price to filter.
     * @param float  $max_price The maximum wholesale price to filter.
     *
     * @return array The meta query array.
     */
    private function build_wholesale_price_filter_meta_query( $role_key, $min_price, $max_price ) {

        $meta_key     = $role_key . '_wholesale_price';
        $meta_key_min = $role_key . '_min_wholesale_price';

        $meta_query = array(
            'relation' => 'OR',
            array(
                'key'     => $meta_key,
                'value'   => array( $min_price, $max_price ),
                'compare' => 'BETWEEN',
                'type'    => 'DECIMAL(10,2)',
            ),
            array(
                'relation' => 'AND',
                array(
                    'key'     => $role_key . '_min_wholesale_price',
                    'value'   => $max_price,
                    'compare' => '<=',
                    'type'    => 'DECIMAL(10,2)',
                ),
                array(
                    'key'     => $role_key . '_max_wholesale_price',
                    'value'   => $min_price,
                    'compare' => '>=',
                    'type'    => 'DECIMAL(10,2)',
                ),
            ),
        );

        // Include products that receive wholesale pricing via general discount rules.
        $general_discount = self::get_general_discount_for_role( $role_key );

        if ( $general_discount > 0 ) {
            // Reverse-calculate: wholesale = retail * (1 - discount/100)
            // Therefore:         retail    = wholesale / (1 - discount/100).
            $multiplier = 1 - ( $general_discount / 100 );

            // Guard against 100% discount (division by zero).
            if ( $multiplier > 0 ) {
                $adjusted_min = $min_price / $multiplier;
                $adjusted_max = $max_price / $multiplier;

                $meta_query[] = array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_price',
                        'value'   => array( $adjusted_min, $adjusted_max ),
                        'compare' => 'BETWEEN',
                        'type'    => 'DECIMAL(10,2)',
                    ),
                    array(
                        'key'     => $meta_key,
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => $meta_key_min,
                        'compare' => 'NOT EXISTS',
                    ),
                );
            }
        }

        /**
         * Filter the wholesale price filter meta query.
         *
         * Allows premium plugins (e.g. WWPP) to add conditions for category-level
         * wholesale pricing or other custom pricing rules.
         *
         * @since 2.2.7
         *
         * @param array  $meta_query The meta query array with OR relation.
         * @param string $role_key   The wholesale role key.
         * @param float  $min_price  The minimum wholesale price filter value.
         * @param float  $max_price  The maximum wholesale price filter value.
         */
        return apply_filters( 'wwp_wholesale_price_filter_meta_query', $meta_query, $role_key, $min_price, $max_price );
    }

    /**
     * Configure Maximum/Minimum Values for WooCommerce Products Shortcode for Compatibility with the HUSKY – Products Filter Professional for WooCommerce Plugin.
     *
     * @since 2.2.7 Added general discount and category-level wholesale price filtering support.
     *
     * @param array  $query WordPress query object.
     * @param array  $atts  attributes set for woocommerce.
     * @param string $type  post_type for woocoomerce which is 'post_type'.
     *
     * @return array
     */
    public function woocommerce_shortcode_products_query( $query, $atts, $type ) { //phpcs:ignore
        $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();

        // if the user has a wholesale role and the min and max price is set, then add the meta query to the query.
        if ( ! empty( $user_wholesale_role ) && isset( $_GET['min_price'] ) && isset( $_GET['max_price'] ) ) { //phpcs:ignore
            $min_price = isset( $_GET['min_price'] ) ? $this->absfloat( sanitize_text_field( wp_unslash( $_GET['min_price'] ) ) ) : 1; //phpcs:ignore
            $max_price = isset( $_GET['max_price'] ) ? $this->absfloat( sanitize_text_field( wp_unslash( $_GET['max_price'] ) ) ) : 0; //phpcs:ignore

            // Validate wholesale role.
            $role_key                       = sanitize_key( $user_wholesale_role[0] );
            $all_registered_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( ! isset( $all_registered_wholesale_roles[ $role_key ] ) ) {
                return $query;
            }

            $query['meta_query'] = $this->build_wholesale_price_filter_meta_query( $role_key, $min_price, $max_price ); //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        }
        return $query;
    }


    /**
     * Convert into float.
     *
     * @since 2.2.5
     *
     * @param int|string $value The subject value.
     *
     * @return float
     */
    private function absfloat( $value ) {
        $float = floatval( $value );
        return $float >= 0 ? $float : 0;
    }

    /**
     * Hook into WooCommerce product queries for wholesale-aware price filtering.
     *
     * When a wholesale customer uses the price filter widget, WooCommerce's default
     * `product_query_post_clauses` filter compares against retail prices from the
     * `wc_product_meta_lookup` table, excluding products that only have wholesale prices.
     * This method disables WooCommerce's retail-price clause via the
     * `woocommerce_enable_post_clause_filtering` filter — preserving attribute filtering,
     * price sorting, and rating filtering that also live in
     * `product_query_post_clauses()` — and registers our own SQL-based
     * `posts_clauses` callback that understands wholesale pricing.
     *
     * @since 2.2.8 Replaced meta_query approach with direct SQL posts_clauses for correct
     *              wholesale price filtering against the lookup table.
     * @since 2.2.7 Added general discount and category-level wholesale price filtering support.
     *
     * @param object $product_query WooCommerce product query object.
     * @param object $wc_object     WooCommerce query object.
     *
     * @return void
     */
    public function woocommerce_product_query_meta_query( $product_query, $wc_object ) { //phpcs:ignore
        $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();

        if ( ! empty( $user_wholesale_role ) && isset( $_GET['min_price'] ) && isset( $_GET['max_price'] ) ) { //phpcs:ignore
            $min_price = isset( $_GET['min_price'] ) ? $this->absfloat( sanitize_text_field( wp_unslash( $_GET['min_price'] ) ) ) : 1; //phpcs:ignore
            $max_price = isset( $_GET['max_price'] ) ? $this->absfloat( sanitize_text_field( wp_unslash( $_GET['max_price'] ) ) ) : 0; //phpcs:ignore

            // Validate wholesale role.
            $role_key                       = sanitize_key( $user_wholesale_role[0] );
            $all_registered_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( ! isset( $all_registered_wholesale_roles[ $role_key ] ) ) {
                return;
            }

            // Disable WooCommerce's retail-price-based clause inside price_filter_post_clauses()
            // while preserving attribute filtering, price sorting, and rating filtering that
            // also live in product_query_post_clauses().
            add_filter( 'woocommerce_enable_post_clause_filtering', '__return_false' );

            // Store filter state on the instance so our posts_clauses callback can access it.
            $this->wholesale_price_filter_role_key = $role_key;
            $this->wholesale_price_filter_min      = $min_price;
            $this->wholesale_price_filter_max      = $max_price;

            // Register our wholesale-aware posts_clauses filter.
            add_filter( 'posts_clauses', array( $this, 'wholesale_price_filter_post_clauses' ), 10, 2 );
        }
    }

    /**
     * Build the posts_clauses WHERE fragment for wholesale price filtering.
     *
     * Appends an AND (...OR...) clause that matches products satisfying ANY of:
     *   1. Simple products whose per-product wholesale price falls within the range.
     *   2. Variable products whose wholesale price range overlaps the filter range.
     *   3. Products with no per-product wholesale price that receive a general role
     *      discount whose reverse-calculated retail price falls within the range
     *      (only when a general discount is configured for the role).
     *
     * Self-removes from the posts_clauses filter after executing to prevent stale
     * state if subsequent queries fire posts_clauses.
     *
     * @since 2.2.8
     *
     * @param array    $args     SQL clause fragments (where, join, orderby, etc.).
     * @param WP_Query $wp_query The current WP_Query instance.
     *
     * @return array Modified clause fragments.
     */
    public function wholesale_price_filter_post_clauses( $args, $wp_query ) {
        global $wpdb;

        // Self-remove to prevent stale state if subsequent queries fire posts_clauses.
        remove_filter( 'posts_clauses', array( $this, 'wholesale_price_filter_post_clauses' ), 10 );

        // Mirror WooCommerce's own guard: only run on queries that opted in.
        if ( ! $wp_query->is_main_query() ) {
            return $args;
        }

        $role_key  = $this->wholesale_price_filter_role_key;
        $min_price = $this->wholesale_price_filter_min;
        $max_price = $this->wholesale_price_filter_max;

        // Reset instance state to prevent stale data on any unexpected re-entry.
        $this->wholesale_price_filter_role_key = '';
        $this->wholesale_price_filter_min      = 0;
        $this->wholesale_price_filter_max      = 0;

        // Meta key components — role_key is already sanitized via sanitize_key().
        // No esc_sql() needed here; $wpdb->prepare() with %s handles escaping.
        $meta_key_simple  = $role_key . '_wholesale_price';
        $meta_key_var_min = $role_key . '_min_wholesale_price';
        $meta_key_var_max = $role_key . '_max_wholesale_price';

        // Condition 1: Simple products with a per-product wholesale price in range.
        $condition1 = $wpdb->prepare(
            "EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} wwp_pm
                WHERE wwp_pm.post_id = {$wpdb->posts}.ID
                AND wwp_pm.meta_key = %s
                AND wwp_pm.meta_value + 0 BETWEEN %f AND %f
            )",
            $meta_key_simple,
            $min_price,
            $max_price
        );

        // Condition 2: Variable products whose wholesale price range overlaps the filter range.
        // The range [stored_min, stored_max] overlaps [min_price, max_price] when
        // stored_min <= max_price AND stored_max >= min_price.
        $condition2 = $wpdb->prepare(
            "EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} wwp_vmin
                INNER JOIN {$wpdb->postmeta} wwp_vmax
                    ON wwp_vmin.post_id = wwp_vmax.post_id
                WHERE wwp_vmin.post_id = {$wpdb->posts}.ID
                AND wwp_vmin.meta_key = %s
                AND wwp_vmax.meta_key = %s
                AND wwp_vmin.meta_value + 0 <= %f
                AND wwp_vmax.meta_value + 0 >= %f
            )",
            $meta_key_var_min,
            $meta_key_var_max,
            $max_price,
            $min_price
        );

        $conditions = array( $condition1, $condition2 );

        // Condition 3: Products with no per-product wholesale price that benefit from a
        // general role discount. Reverse-calculate the retail range that maps onto the
        // requested wholesale range and match against the lookup table.
        $general_discount = self::get_general_discount_for_role( $role_key );

        if ( $general_discount > 0 ) {
            $multiplier = 1 - ( $general_discount / 100 );

            // Guard against 100% discount (division by zero).
            if ( $multiplier > 0 ) {
                $adjusted_min = $min_price / $multiplier;
                $adjusted_max = $max_price / $multiplier;

                $condition3 = $wpdb->prepare(
                    "EXISTS (
                        SELECT 1 FROM {$wpdb->prefix}wc_product_meta_lookup wc_lookup
                        WHERE wc_lookup.product_id = {$wpdb->posts}.ID
                        AND NOT ( %f < wc_lookup.min_price OR %f > wc_lookup.max_price )
                        AND NOT EXISTS (
                            SELECT 1 FROM {$wpdb->postmeta} wwp_excl
                            WHERE wwp_excl.post_id = {$wpdb->posts}.ID
                            AND wwp_excl.meta_key IN ( %s, %s )
                        )
                    )",
                    $adjusted_max,
                    $adjusted_min,
                    $meta_key_simple,
                    $meta_key_var_min
                );

                $conditions[] = $condition3;
            }
        }

        $sql = '( ' . implode( ' OR ', $conditions ) . ' )';

        /**
         * Filter the wholesale price filter SQL fragment appended to posts_clauses.
         *
         * Allows premium plugins (e.g. WWPP) to extend the WHERE condition with
         * additional OR clauses for category-level wholesale pricing or other custom rules.
         *
         * @warning The returned value is appended verbatim to the SQL WHERE clause.
         *          Callers MUST use $wpdb->prepare() for all dynamic values to prevent
         *          SQL injection.
         *
         * @since 2.2.8
         *
         * @param string $sql       The SQL OR-group string (without leading AND).
         * @param string $role_key  The wholesale role key.
         * @param float  $min_price The minimum wholesale price filter value.
         * @param float  $max_price The maximum wholesale price filter value.
         */
        $sql = apply_filters( 'wwp_wholesale_price_filter_post_clauses', $sql, $role_key, $min_price, $max_price );

        $args['where'] .= " AND {$sql}";

        return $args;
    }

    /**
     * Filter the minimum price for the WooCommerce price filter widget to use wholesale prices.
     *
     * When a wholesale customer views the shop, the price filter widget slider should
     * reflect wholesale price ranges instead of retail prices.
     *
     * @since 2.2.7
     *
     * @param float $min_price The minimum price from WooCommerce.
     *
     * @return float The wholesale minimum price, or the original if no wholesale role.
     */
    public function wholesale_price_filter_widget_min_amount( $min_price ) {
        return $this->get_wholesale_price_for_filter( $min_price, 'MIN' );
    }

    /**
     * Filter the maximum price for the WooCommerce price filter widget to use wholesale prices.
     *
     * When a wholesale customer views the shop, the price filter widget slider should
     * reflect wholesale price ranges instead of retail prices.
     *
     * @since 2.2.7
     *
     * @param float $max_price The maximum price from WooCommerce.
     *
     * @return float The wholesale maximum price, or the original if no wholesale role.
     */
    public function wholesale_price_filter_widget_max_amount( $max_price ) {
        return $this->get_wholesale_price_for_filter( $max_price, 'MAX' );
    }

    /**
     * Get the wholesale price for the price filter widget.
     *
     * Validates the current user's wholesale role and returns the appropriate
     * wholesale price range value, falling back to the original price.
     *
     * @since 2.2.7
     *
     * @param float  $fallback_price The default price from WooCommerce.
     * @param string $aggregate      'MIN' or 'MAX'.
     *
     * @return float The wholesale price, or the fallback if no wholesale role.
     */
    private function get_wholesale_price_for_filter( $fallback_price, $aggregate ) {

        $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();

        if ( empty( $user_wholesale_role ) ) {
            return $fallback_price;
        }

        $role_key                       = sanitize_key( $user_wholesale_role[0] );
        $all_registered_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

        if ( ! isset( $all_registered_wholesale_roles[ $role_key ] ) ) {
            return $fallback_price;
        }

        $wholesale_price = $this->get_wholesale_price_range_value( $role_key, $aggregate );

        return null !== $wholesale_price ? $wholesale_price : $fallback_price;
    }

    /**
     * Get the minimum or maximum wholesale price across all published products for a role.
     *
     * Considers per-product wholesale prices, variable product wholesale prices,
     * and general discount applied to retail prices.
     *
     * @since 2.2.7
     *
     * @param string $role_key  The wholesale role key.
     * @param string $aggregate 'MIN' or 'MAX'.
     *
     * @return float|null The aggregated price, or null if no wholesale prices found.
     */
    private function get_wholesale_price_range_value( $role_key, $aggregate ) {

        // The key itself stays stable across invalidations (see clear_wholesale_price_range_cache()) —
        // only the epoch STAMPED INSIDE the cached entry changes, so a cache bump orphans the entry's
        // value by making the stamp stop matching, rather than growing a new transient row per bump.
        $cache_key = 'wwp_price_range_' . $role_key . '_' . strtolower( $aggregate );
        $epoch     = self::get_price_range_cache_epoch();
        $cached    = get_transient( $cache_key );

        if ( is_array( $cached ) && isset( $cached['epoch'], $cached['value'] ) && $epoch === (int) $cached['epoch'] ) {
            return $cached['value'];
        }

        global $wpdb;

        $is_max   = 'MAX' === strtoupper( $aggregate );
        $sql_func = $is_max ? 'MAX' : 'MIN';
        $meta_key = $role_key . '_wholesale_price';

        // Get min/max per-product wholesale price.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $per_product_price = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT {$sql_func}(CAST(pm.meta_value AS DECIMAL(10,2)))
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                AND pm.meta_value > 0
                AND p.post_status = 'publish'",
                $meta_key
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $prices = array();

        if ( null !== $per_product_price ) {
            $prices[] = (float) $per_product_price;
        }

        // Also consider retail prices with general discount applied.
        $general_discount = self::get_general_discount_for_role( $role_key );

        if ( $general_discount > 0 ) {
            $multiplier = 1 - ( $general_discount / 100 );

            if ( $multiplier > 0 ) {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $retail_price = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT {$sql_func}(CAST(pm.meta_value AS DECIMAL(10,2)))
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                        WHERE pm.meta_key = %s
                        AND pm.meta_value > 0
                        AND p.post_status = 'publish'",
                        '_price'
                    )
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                if ( null !== $retail_price ) {
                    $prices[] = (float) $retail_price * $multiplier;
                }
            }
        }

        /**
         * Filter the wholesale price range values for the price filter widget.
         *
         * Allows premium plugins to include category-level or other custom pricing.
         *
         * @since 2.2.7
         *
         * @param array  $prices    Array of candidate prices.
         * @param string $role_key  The wholesale role key.
         * @param string $aggregate 'MIN' or 'MAX'.
         */
        $prices = apply_filters( 'wwp_wholesale_price_filter_widget_prices', $prices, $role_key, $aggregate );

        if ( empty( $prices ) ) {
            return null;
        }

        $result = 'MIN' === $aggregate ? min( $prices ) : max( $prices );

        // Mark the cache as populated (see get_price_range_cache_epoch()) BEFORE writing the
        // transient, so clear_wholesale_price_range_cache() knows there is now something to
        // invalidate. Stamp the entry with the epoch already read above rather than re-reading
        // the option a second time.
        self::ensure_price_range_cache_epoch( $epoch );
        set_transient(
            $cache_key,
            array(
                'epoch' => $epoch,
                'value' => $result,
            ),
            HOUR_IN_SECONDS
        );

        return $result;
    }

    /**
     * Get the current price-range cache epoch, defaulting to 1 when none has ever been set.
     *
     * Read-only: unlike ensure_price_range_cache_epoch(), this never creates the option, so a
     * request that only reads (and misses) the cache does not itself mark the cache "ever set".
     *
     * @since 2.3.0
     *
     * @return int
     */
    private static function get_price_range_cache_epoch() {

        $epoch = get_option( WWP_OPTIONS_PRICE_RANGE_CACHE_EPOCH, false );

        return false === $epoch ? 1 : (int) $epoch;
    }

    /**
     * Ensure the price-range cache epoch option exists, creating it on first write.
     *
     * @since 2.3.0
     *
     * @param int $current_epoch The epoch to seed the option with if it does not exist yet.
     * @return void
     */
    private static function ensure_price_range_cache_epoch( $current_epoch ) {

        // add_option() is itself a no-op when the option already exists, so this never
        // overwrites a live epoch that may have advanced since the caller read $current_epoch.
        add_option( WWP_OPTIONS_PRICE_RANGE_CACHE_EPOCH, $current_epoch, '', false );
    }

    /**
     * Invalidate the per-role wholesale price range cache used by the price filter widget.
     *
     * Hooked on every `woocommerce_update_product` (and on the general-discount mapping option
     * update), so it must stay O(1) regardless of how many wholesale roles are registered.
     *
     * Bumps a single versioned "epoch" option instead of enumerating registered roles and
     * deleting each role's min/max transient:
     * - Bails immediately, with no write, when the epoch option has never been created — i.e. no
     *   price-range transient has ever been set (get_wholesale_price_range_value() is the only
     *   writer, via ensure_price_range_cache_epoch()).
     * - Otherwise a single `update_option()` bumps the epoch. Every transient cached under the
     *   previous epoch (for every role — including a role that has since been renamed or
     *   unregistered, which the old per-role loop could never reach) fails the epoch-stamp check
     *   in get_wholesale_price_range_value() on its next read, because that stamp no longer
     *   matches the CURRENT epoch, so it recomputes instead of serving the stale value.
     * - Once the epoch option exists, this also makes invalidation immune to a slow concurrent
     *   read: a request that started computing under epoch N writes its result stamped with
     *   epoch N, which is already stale by the time it lands if a bump happened meanwhile,
     *   instead of overwriting the live value with pre-change data as a plain delete-then-
     *   recompute race could. (Before the option exists, a bump is deliberately suppressed above,
     *   so a compute that races the very first population can still cache pre-save data until the
     *   transient expires — the same window the previous delete-based implementation had.)
     *
     * @since 2.2.7
     * @since 2.3.0 Replace the per-role delete_transient() loop with a single versioned-epoch
     *              bump so the cost no longer scales with registered role count and leftover
     *              keys for deregistered roles are correctly orphaned (issue #1021).
     *
     * @return void
     */
    public static function clear_wholesale_price_range_cache() {

        $epoch = get_option( WWP_OPTIONS_PRICE_RANGE_CACHE_EPOCH, false );

        // Nothing has ever been cached — nothing to invalidate.
        if ( false === $epoch ) {
            return;
        }

        update_option( WWP_OPTIONS_PRICE_RANGE_CACHE_EPOCH, ( (int) $epoch ) + 1 );
    }

    /**
     * Migrate variable products to store per-role min/max wholesale price meta.
     *
     * Runs once on `init` (guarded by the `wwp_variable_price_range_version` option).
     * Iterates all variable products that previously stored variation IDs under the
     * `_variations_with_wholesale_price` meta key and replaces that data with
     * computed `_min_wholesale_price` / `_max_wholesale_price` values per wholesale
     * role, enabling the range-overlap price filter query introduced in 2.2.8.
     *
     * @since  2.2.8
     * @since  2.2.9 Process a single page of products per invocation and re-enqueue for the next
     *               page, tracking progress with the `wwp_variable_price_range_current_page`
     *               checkpoint option. This keeps each run within the host PHP time limit, makes
     *               the migration resumable after an interruption, and prevents the Action
     *               Scheduler queue from being permanently blocked by a timed-out monolithic run.
     * @access public
     *
     * @return void
     */
    public function migrate_variable_product_wholesale_price_range() {

        if ( get_option( 'wwp_variable_price_range_version' ) ) {
            return;
        }

        $wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

        if ( empty( $wholesale_roles ) ) {
            delete_option( 'wwp_variable_price_range_current_page' );
            update_option( 'wwp_variable_price_range_version', '1.0' );
            return;
        }

        $limit = 50;
        $page  = max( 1, (int) get_option( 'wwp_variable_price_range_current_page', 1 ) );

        $variable_products = wc_get_products(
            array(
                'type'   => 'variable',
                'limit'  => $limit,
                'page'   => $page,
                'return' => 'objects',
            )
        );

        foreach ( $variable_products as $variable_product ) {
            $children = $variable_product->get_children();

            if ( empty( $children ) ) {
                continue;
            }

            $wholesale_prices_per_role = WWP_Helper_Functions::get_wholesale_prices_per_role_from_variations( $children, $wholesale_roles );

            foreach ( $wholesale_roles as $role_key => $role ) {
                if ( ! empty( $wholesale_prices_per_role[ $role_key ] ) ) {
                    $prices = $wholesale_prices_per_role[ $role_key ];
                    $variable_product->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );
                    $variable_product->update_meta_data( $role_key . '_min_wholesale_price', min( $prices ) );
                    $variable_product->update_meta_data( $role_key . '_max_wholesale_price', max( $prices ) );
                } else {
                    $variable_product->update_meta_data( $role_key . '_have_wholesale_price', 'no' );
                    $variable_product->delete_meta_data( $role_key . '_min_wholesale_price' );
                    $variable_product->delete_meta_data( $role_key . '_max_wholesale_price' );
                }
            }

            $variable_product->save_meta_data();
        }

        if ( count( $variable_products ) < $limit ) {
            // Final page processed - clear the checkpoint and mark the migration complete.
            delete_option( 'wwp_variable_price_range_current_page' );
            update_option( 'wwp_variable_price_range_version', '1.0' );
        } else {
            // More pages remain - persist the checkpoint for the next run.
            update_option( 'wwp_variable_price_range_current_page', $page + 1 );

            if ( function_exists( 'as_enqueue_async_action' ) ) {
                // Re-enqueue the next page as a fresh Action Scheduler run so each invocation
                // stays well within the PHP execution limit.
                as_enqueue_async_action( 'wwp_migrate_variable_price_range' );
            }
            // When Action Scheduler is unavailable the synchronous driver in
            // schedule_wholesale_price_range_migration() advances through the remaining pages.
        }
    }

    /**
     * Schedule the wholesale price range migration via Action Scheduler.
     *
     * Schedules a single async action `wwp_migrate_variable_price_range` using
     * Action Scheduler so the migration runs in the background rather than
     * blocking the `init` hook. If Action Scheduler is unavailable (e.g. WooCommerce
     * is not yet loaded), the migration is run synchronously as a fallback.
     *
     * @since  2.2.8
     * @since  2.2.9 Enqueue via as_enqueue_async_action(); when Action Scheduler is unavailable,
     *               drive the now-paginated migration synchronously page by page until complete.
     * @access public
     *
     * @return void
     */
    public function schedule_wholesale_price_range_migration() {

        if ( get_option( 'wwp_variable_price_range_version' ) ) {
            return;
        }

        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            // Fallback: Action Scheduler is unavailable, so run the migration synchronously,
            // one page per call, until the completion flag is set.
            do {
                $this->migrate_variable_product_wholesale_price_range();
            } while ( ! get_option( 'wwp_variable_price_range_version' ) );
            return;
        }

        if ( ! as_has_scheduled_action( 'wwp_migrate_variable_price_range' ) ) {
            as_enqueue_async_action( 'wwp_migrate_variable_price_range' );
        }
    }

    /**
     * Whether to register wholesale_price_html_filter on woocommerce_get_price_html.
     *
     * Pure admin page loads do not need frontend wholesale price HTML. Admin AJAX
     * (e.g. variation price display) and REST API requests still do.
     *
     * @since 2.2.9
     * @access private
     *
     * @return bool True when the filter should be registered for this request.
     */
    private function should_register_wholesale_price_html_filter() {

        // Storefront, cron, CLI, and other non-admin contexts always need the filter.
        // Evaluated at plugin bootstrap (registration time), not inside price callbacks
        // where WPML historically made is_admin() unreliable.
        if ( ! is_admin() ) {
            return true;
        }

        // Admin-ajax.php still needs the filter for variation / dynamic price HTML.
        if ( wp_doing_ajax() ) {
            return true;
        }

        // REST requests (including WC Store API / product endpoints) need the filter.
        // REST_REQUEST may not be defined yet at bootstrap; WC URI check covers that case.
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return true;
        }

        if ( function_exists( 'WC' ) ) {
            $woocommerce = WC();
            if ( $woocommerce && is_callable( array( $woocommerce, 'is_rest_api_request' ) ) && $woocommerce->is_rest_api_request() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute model.
     *
     * @since  1.5.0
     * @since  2.2.9 Register the Elementor Product Price widget replacement-message filter;
     *               skip wholesale price HTML filter on pure admin screens (#951).
     * @since  2.2.9 Reapply wholesale pricing on woocommerce_cart_loaded_from_session for every
     *               classic (non-block) cart, replacing the previous Divi-only registration, so the
     *               classic Cart page row matches the totals box and Checkout on a plain reload.
     * @access public
     */
    public function run() {

        add_action( 'init', array( $this, 'schedule_wholesale_price_range_migration' ) );
        add_action( 'wwp_migrate_variable_price_range', array( $this, 'migrate_variable_product_wholesale_price_range' ) );

        // Apply wholesale price to archive and single product pages.
        // On WC 3.x series, includes variation products.
        // Do not attach on pure admin screens. WooCommerce's Price column on
        // /wp-admin/edit.php?post_type=product calls get_price_html() per row and would
        // otherwise drag the full WWPP pricing pipeline into the product list for no
        // frontend benefit. Keep it for storefront, admin-ajax, and REST. Issue #951.
        if ( $this->should_register_wholesale_price_html_filter() ) {
            add_filter( 'woocommerce_get_price_html', array( $this, 'wholesale_price_html_filter' ), 10, 2 );
        }

        // Apply wholesale price upon adding product to cart.
        add_action(
            'woocommerce_before_calculate_totals',
            array(
                $this,
                'apply_product_wholesale_price_to_cart',
            ),
            10,
            1
        );

        // Populate wholesale line totals for woocommerce_add_to_cart listeners (issue #549).
        // Priority 1 so later listeners see calculated totals; WC core's own priority-20
        // calculate_totals stays registered so the complete cart (incl. bundled/composite
        // children added at priority 9) is re-persisted to the session (issues #923, #994).
        add_action(
            'woocommerce_add_to_cart',
            array(
                $this,
                'recalculate_cart_totals_for_wholesale_customer_on_add_to_cart',
            ),
            1,
            0
        );

        // Reapply wholesale pricing right after the cart is rebuilt from the session so the
        // classic Cart page's row Price and Subtotal columns match the Cart Totals box and
        // Checkout, even on a plain reload where the cart is unchanged and calculate_totals()
        // (the trigger that normally re-applies the price) is skipped. The block-based cart
        // manages its own pricing through the Store API, so it is excluded inside the handler.
        add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'reapply_wholesale_price_on_classic_cart' ), 10, 1 );

        // We need to recalculate cart on loading widget cart to properly sync the cart item prices.
        add_action( 'woocommerce_before_mini_cart', array( $this, 'recalculate_cart_totals' ) );

        // Apply taxing to wholesale price on shop pages.
        add_filter(
            'wwp_pass_wholesale_price_through_taxing',
            array(
                $this,
                'apply_taxing_to_wholesale_prices_on_shop_page',
            ),
            10,
            3
        );

        // Product Import. Wholesale Prices + Aelia Currency plugin compatibility. Also fix issue with wholesale role with uppercase letter.
        add_filter(
            'woocommerce_product_importer_parsed_data',
            array(
                $this,
                'update_meta_data_with_proper_meta_keys',
            ),
            10,
            2
        );

        // Add the wholesale price to the variation data on the single product page form.
        add_filter( 'woocommerce_available_variation', array( $this, 'add_wholesale_price_to_variation_data' ), 10, 3 );

        // Disable Coupons For Wholesale Users Option.
        add_filter(
            'woocommerce_coupons_enabled',
            array(
                $this,
                'toggle_availability_of_coupons_to_wholesale_users',
            ),
            10,
            1
        );
        add_action( 'woocommerce_before_cart', array( $this, 'remove_coupons_for_wholesale_users_when_necessary' ) );
        add_action(
            'woocommerce_before_checkout_form',
            array(
                $this,
                'remove_coupons_for_wholesale_users_when_necessary',
            )
        );

        // Block-based cart/checkout don't fire woocommerce_before_cart / woocommerce_before_checkout_form,
        // so hook the lower-level cart-loaded action to cover that case (see issue #880).
        add_action( 'woocommerce_load_cart_from_session', array( $this, 'remove_coupons_for_wholesale_users_when_necessary' ) );

        // Filter the product price to hide the original price for wholesale users.
        add_filter( 'wwp_product_original_price', array( $this, 'filter_product_original_price_visibility' ), 10, 5 );

        // Custom Wholesale Price Text.
        add_filter(
            'wwp_filter_wholesale_price_title_text',
            array(
                $this,
                'filter_wholesale_price_title_text',
            ),
            10,
            1
        );

        // Custom Wholesale Price Text.
        add_filter(
            'wwp_filter_wholesale_regular_price_title_text',
            array(
                $this,
                'filter_wholesale_regular_price_title_text',
            ),
            10,
            6
        );

        // Hide Price and Add to Cart button feature.
        add_filter( 'init', array( $this, 'hide_price_and_add_to_cart_button' ) );
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_replacement_message' ), 10 );
        add_action( 'woocommerce_after_shop_loop_item', array( $this, 'display_replacement_message' ), 10 );
        // Elementor renders the price through its own "Product Price" widget, bypassing the
        // WooCommerce actions above, so output the replacement message there too. The filter only
        // fires when Elementor is active, so no extra guard is needed.
        add_filter( 'elementor/widget/render_content', array( $this, 'show_replacement_message_in_elementor_price_widget' ), 10, 2 );
        add_filter(
            'woocommerce_blocks_product_grid_item_html',
            array(
                $this,
                'hide_add_to_cart_button_wc_blocks',
            ),
            10,
            3
        );

        // Clear Product Transients on tax settings save.
        add_action(
            'woocommerce_settings_save_tax',
            array(
                $this,
                'clear_product_transient_on_tax_settings_save',
            ),
            10
        );

        add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_product_price' ), 100, 2 );

        add_action( 'wp_enqueue_scripts', array( $this, 'wc_cart_block_price_html' ), 20 );
        add_action( 'woocommerce_blocks_loaded', array( $this, 'wc_cart_item_block_wwp_data' ) );

        // For products queried using the WooCommerce Query class, ensure compatibility with the HUSKY – WooCommerce Products Filter plugin, Filter Products by Price.
        add_action( 'woocommerce_product_query', array( $this, 'woocommerce_product_query_meta_query' ), 99999, 2 );

        // Modify WooCommerce Shortcode Filtering for Compatibility with the HUSKY – WooCommerce Products Filter Plugin, Filter Products by Price.
        add_filter( 'woocommerce_shortcode_products_query', array( $this, 'woocommerce_shortcode_products_query' ), 99999, 3 );

        // Adjust price filter widget min/max range to use wholesale prices for wholesale customers.
        add_filter( 'woocommerce_price_filter_widget_min_amount', array( $this, 'wholesale_price_filter_widget_min_amount' ), 10, 1 );
        add_filter( 'woocommerce_price_filter_widget_max_amount', array( $this, 'wholesale_price_filter_widget_max_amount' ), 10, 1 );

        // Clear wholesale price range cache when products are updated.
        add_action( 'woocommerce_update_product', array( __CLASS__, 'clear_wholesale_price_range_cache' ) );
        add_action( 'update_option_wwpp_option_wholesale_role_general_discount_mapping', array( __CLASS__, 'clear_wholesale_price_range_cache' ) );

        // Register the option for translation.
        add_filter( 'pre_update_option', array( $this, 'wwp_wpml_translatable_options' ), 100, 2 );
    }
}
