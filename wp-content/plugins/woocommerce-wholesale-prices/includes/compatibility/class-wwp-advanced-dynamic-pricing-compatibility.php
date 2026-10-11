<?php
/**
 * Advanced Dynamic Pricing for WooCommerce compatibility.
 *
 * Ensures the wholesale price takes precedence over Advanced Dynamic Pricing's rules
 * for wholesale customers.
 *
 * @package    WooCommerceWholeSalePrices
 * @subpackage Compatibility
 * @since      2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Advanced_Dynamic_Pricing_Compatibility' ) ) {

    /**
     * Model that houses the logic of Advanced Dynamic Pricing for WooCommerce compatibility.
     *
     * Advanced Dynamic Pricing recalculates product prices from the raw catalog price and
     * overrides the wholesale price for wholesale customers (both the displayed price and the
     * cart price). For wholesale customers the wholesale price must take precedence, so this
     * model uses Advanced Dynamic Pricing's own public filters to stand its rule engine down
     * while a wholesale customer is browsing or buying. Non-wholesale customers are unaffected.
     *
     * @since 2.3.0
     */
    class WWP_Advanced_Dynamic_Pricing_Compatibility {

        /**
         * Property that holds the single main instance of WWP_Advanced_Dynamic_Pricing_Compatibility.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Advanced_Dynamic_Pricing_Compatibility
         */
        private static $_instance;

        /**
         * Wholesale roles model.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Wholesale_Roles
         */
        private $wwp_wholesale_roles;

        /**
         * Cache of whether the resolved customer is a wholesale customer.
         *
         * Null means "not yet resolved" so the lookup runs at most once per user context.
         *
         * @since 2.3.0
         * @access private
         * @var bool|null
         */
        private $is_wholesale_customer;

        /**
         * User id the wholesale-customer cache was resolved for.
         *
         * Tracked so the cache is re-resolved when the current user changes within a single
         * long-running process (e.g. WP-CLI or a background worker that calls
         * wp_set_current_user() per user), instead of reusing the first user's status.
         *
         * @since 2.3.0
         * @access private
         * @var int|null
         */
        private $cached_user_id;

        /**
         * WWP_Advanced_Dynamic_Pricing_Compatibility constructor.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of this model.
         */
        public function __construct( $dependencies = array() ) {
            if ( isset( $dependencies['WWP_Wholesale_Roles'] ) ) {
                $this->wwp_wholesale_roles = $dependencies['WWP_Wholesale_Roles'];
            }
        }

        /**
         * Ensure that only one instance of WWP_Advanced_Dynamic_Pricing_Compatibility is loaded or can be loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of this model.
         * @return WWP_Advanced_Dynamic_Pricing_Compatibility
         */
        public static function instance( $dependencies = array() ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Determine whether the current customer has a wholesale role.
         *
         * The result is cached because the dynamic pricing filters can fire many times while a
         * single page is rendered. The cache is keyed on the current user id and re-resolved when
         * it changes, so a long-running process that switches user context with
         * wp_set_current_user() (WP-CLI, background worker) does not reuse an earlier user's status.
         *
         * @since 2.3.0
         * @access private
         *
         * @return bool True when the current customer has a wholesale role, false otherwise.
         */
        private function is_wholesale_customer() {
            $user_id = get_current_user_id();

            if ( $user_id !== $this->cached_user_id || null === $this->is_wholesale_customer ) {
                $this->cached_user_id        = $user_id;
                $wholesale_roles             = $this->wwp_wholesale_roles ? $this->wwp_wholesale_roles->getUserWholesaleRole() : array();
                $this->is_wholesale_customer = is_array( $wholesale_roles ) && ! empty( $wholesale_roles );
            }

            return $this->is_wholesale_customer;
        }

        /**
         * Suppress Advanced Dynamic Pricing's rule engine for wholesale customers.
         *
         * Hooked onto Advanced Dynamic Pricing's 'adp_rules_suppression' filter. Returning true
         * stops the plugin from recalculating cart prices, so the wholesale price applied by WWP
         * stands as the effective price for wholesale customers.
         *
         * @since 2.3.0
         * @access public
         *
         * @param bool $suppressed Whether Advanced Dynamic Pricing's rules are already suppressed.
         * @return bool True to suppress the rules for wholesale customers, otherwise the passed value.
         */
        public function suppress_dynamic_pricing_rules( $suppressed ) {
            return $this->is_wholesale_customer() ? true : $suppressed;
        }

        /**
         * Prevent Advanced Dynamic Pricing from rewriting the displayed price HTML for wholesale customers.
         *
         * Hooked onto Advanced Dynamic Pricing's 'adp_get_price_html_is_mod_needed' filter. Returning
         * false leaves the wholesale price HTML rendered by WWP untouched instead of replacing it with
         * the dynamic-rule price.
         *
         * @since 2.3.0
         * @access public
         *
         * @param bool       $mod_needed Whether Advanced Dynamic Pricing intends to modify the price HTML.
         * @param WC_Product $product    The product whose price HTML is being rendered.
         * @param mixed      $context    Advanced Dynamic Pricing context object.
         * @return bool False for wholesale customers to keep the wholesale price HTML, otherwise the passed value.
         */
        public function suppress_price_html_modification( $mod_needed, $product, $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
            return $this->is_wholesale_customer() ? false : $mod_needed;
        }

        /**
         * Execute the model.
         *
         * The filters below belong to Advanced Dynamic Pricing for WooCommerce; when that plugin is
         * not active they never fire, so wholesale pricing behaves exactly as before.
         *
         * @since 2.3.0
         * @access public
         *
         * @return void
         */
        public function run() {
            add_filter( 'adp_rules_suppression', array( $this, 'suppress_dynamic_pricing_rules' ), 10, 1 );
            add_filter( 'adp_get_price_html_is_mod_needed', array( $this, 'suppress_price_html_modification' ), 10, 3 );
        }
    }
}
