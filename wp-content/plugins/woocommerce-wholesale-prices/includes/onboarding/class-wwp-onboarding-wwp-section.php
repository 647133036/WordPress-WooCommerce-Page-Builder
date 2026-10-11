<?php
/**
 * WWP's own onboarding section.
 *
 * Registers Wholesale Prices' own checklist section against the shared data contract
 * ({@see WWP_Onboarding_Data_Contract::FILTER}), exactly the way a sibling plugin would — WWP owns
 * no privileged path into the card. The steps are the ones defined in the WWP onboarding brief;
 * their statuses are resolved here, server-side, by thin detectors that reuse signals WWP already
 * computes elsewhere (the `{role}_have_wholesale_price` price marker, the wholesale-user query, and
 * the wholesale-order lookup). Manual attestation (the "see your store" step) is left to the card's
 * completion store, which the assembler overlays.
 *
 * Part of epic #1031; WWP-section steps + detectors (#1034) built on the #1033 renderer.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1034
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Onboarding_WWP_Section' ) ) {

    /**
     * Builds and registers WWP's own onboarding section.
     *
     * @since 2.3.0
     */
    class WWP_Onboarding_WWP_Section {

        /**
         * Section identifier (this plugin's slug).
         *
         * @since 2.3.0
         * @var string
         */
        const PLUGIN = 'wwp';

        /**
         * Render priority — WWP's own section comes first.
         *
         * @since 2.3.0
         * @var int
         */
        const PRIORITY = 10;

        /**
         * Transient key memoizing the three detector signals.
         *
         * @since 2.3.0
         * @var string
         */
        const DETECTOR_CACHE = 'wwp_onboarding_detector_signals';

        /**
         * Single main instance.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Onboarding_WWP_Section|null
         */
        private static $_instance = null;

        /**
         * Ensure only one instance is loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @return WWP_Onboarding_WWP_Section
         */
        public static function instance() {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Register hooks.
         *
         * @since 2.3.0
         * @access public
         */
        public function run() {
            add_filter( WWP_Onboarding_Data_Contract::FILTER, array( $this, 'register_section' ), self::PRIORITY );

            // Bust the detector cache the moment a tracked signal changes, so a just-completed step
            // ticks on the next render instead of waiting out the TTL.
            //
            // Each flush is hooked to the point where its signal's data is actually committed — never
            // earlier. Flushing before the write lets an in-request consumer (the onboarding emails
            // and telemetry both re-assemble on save) re-populate the cache with the pre-write value,
            // stranding a completed step as incomplete until the TTL expires (see #1059).
            //
            // Price: WWP writes the `%_have_wholesale_price` marker inside $product->save() (see
            // WWP_Admin_Custom_Fields_Simple_Product::_save_wholesale_price_fields()), so the flush is
            // hooked to the CRUD save actions WooCommerce fires at the END of save() — after the marker
            // is committed — for EVERY product type (simple, variable, and any future bundle/composite/
            // subscription type) and every save path (editor, REST, import, programmatic). This is
            // deliberately type-agnostic: a new product type gains correct invalidation for free,
            // with no new hook here. save_post_product is intentionally NOT used — WP fires it before
            // the marker exists. woocommerce_ajax_save_product_variations covers the inline variation
            // save, whose parent-marker write does not always route through a full product save().
            add_action( 'woocommerce_update_product', array( $this, 'flush_detector_cache' ) );
            add_action( 'woocommerce_new_product', array( $this, 'flush_detector_cache' ) );
            add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'flush_detector_cache' ) );
            add_action( 'set_user_role', array( $this, 'flush_detector_cache' ) );
            add_action( 'add_user_role', array( $this, 'flush_detector_cache' ) );
            add_action( 'woocommerce_new_order', array( $this, 'flush_detector_cache' ) );
            add_action( 'woocommerce_order_status_changed', array( $this, 'flush_detector_cache' ) );
        }

        /**
         * Append WWP's own section to the checklist.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $sections Sections registered so far.
         * @return array
         */
        public function register_section( $sections ) {
            $sections[] = array(
                'plugin'   => self::PLUGIN,
                'label'    => __( 'Wholesale Prices', 'woocommerce-wholesale-prices' ),
                'priority' => self::PRIORITY,
                'steps'    => $this->build_steps(),
            );

            return $sections;
        }

        /**
         * Build WWP's ordered step list with server-resolved statuses.
         *
         * The first not-yet-complete counted step (excluding the milestone) is marked `current` so
         * the card highlights a single "do this now" action.
         *
         * @since 2.3.0
         * @access private
         *
         * @return array
         */
        private function build_steps() {
            $signals      = $this->detector_signals();
            $has_price    = $signals['price'];
            $has_customer = $signals['customer'];
            $has_order    = $signals['order'];

            $steps = array(
                array(
                    'id'          => 'role_created',
                    'title'       => __( 'Wholesale role created', 'woocommerce-wholesale-prices' ),
                    'subtitle'    => __( 'Added when you activated the plugin.', 'woocommerce-wholesale-prices' ),
                    'description' => __( 'A “Wholesale Customer” role was created so you can give buyers wholesale pricing.', 'woocommerce-wholesale-prices' ),
                    'status'      => 'complete',
                    'completion'  => 'auto',
                    'counts'      => true,
                ),
                array(
                    'id'          => 'wholesale_price_set',
                    'title'       => __( 'Set a wholesale price on a product', 'woocommerce-wholesale-prices' ),
                    'subtitle'    => $has_price ? __( 'Detected in your store', 'woocommerce-wholesale-prices' ) : '',
                    'description' => __( 'Add a wholesale price to any product so your wholesale customers see their rate.', 'woocommerce-wholesale-prices' ),
                    'status'      => $has_price ? 'complete' : 'todo',
                    'completion'  => 'auto',
                    'counts'      => true,
                    'action'      => array(
                        'type'  => 'link',
                        'label' => __( 'Edit a product', 'woocommerce-wholesale-prices' ),
                        'url'   => admin_url( 'edit.php?post_type=product' ),
                    ),
                ),
                array(
                    'id'          => 'wholesale_customer',
                    'title'       => __( 'Approve a wholesale customer', 'woocommerce-wholesale-prices' ),
                    'subtitle'    => $has_customer ? __( 'Detected in your store', 'woocommerce-wholesale-prices' ) : '',
                    'description' => __( 'Give a user a wholesale role so they can buy at wholesale prices.', 'woocommerce-wholesale-prices' ),
                    'status'      => $has_customer ? 'complete' : 'todo',
                    'completion'  => 'auto',
                    'counts'      => true,
                    'action'      => array(
                        'type'  => 'link',
                        'label' => __( 'Manage users', 'woocommerce-wholesale-prices' ),
                        'url'   => admin_url( 'users.php' ),
                    ),
                ),
                array(
                    'id'          => 'store_view',
                    'title'       => __( 'See your store as a wholesale buyer', 'woocommerce-wholesale-prices' ),
                    'subtitle'    => '',
                    'description' => __( "Confirm everything looks right from the buyer's side — wholesale prices, tax display and product visibility — by previewing your store as a wholesale customer.", 'woocommerce-wholesale-prices' ),
                    // Manual step: the assembler overrides this from WWP's completion store once attested.
                    'status'      => 'todo',
                    'completion'  => 'manual',
                    'counts'      => true,
                    'estimate'    => __( '~1 min', 'woocommerce-wholesale-prices' ),
                    'action'      => array(
                        'type'  => 'link',
                        'label' => __( 'Open your store', 'woocommerce-wholesale-prices' ),
                        'url'   => home_url( '/' ),
                    ),
                ),
                array(
                    'id'          => 'first_order',
                    'title'       => __( 'Your first wholesale order', 'woocommerce-wholesale-prices' ),
                    'subtitle'    => $has_order
                        ? __( 'Order received', 'woocommerce-wholesale-prices' )
                        : __( 'Waiting for your first order', 'woocommerce-wholesale-prices' ),
                    'description' => __( 'Nothing to do here yet. Once a wholesale customer checks out, this ticks itself and your setup is complete.', 'woocommerce-wholesale-prices' ),
                    'status'      => $has_order ? 'complete' : 'waiting',
                    'completion'  => 'milestone',
                    'counts'      => true,
                    'action'      => array(
                        'type'  => 'link',
                        'label' => __( 'View orders', 'woocommerce-wholesale-prices' ),
                        'url'   => $this->orders_screen_url(),
                    ),
                ),
            );

            $steps = $this->mark_current_step( $steps );

            return array_merge( $steps, $this->discovery_steps() );
        }

        /**
         * Mark the first incomplete, counted, non-milestone step as `current`.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $steps Ordered steps.
         * @return array
         */
        private function mark_current_step( $steps ) {
            foreach ( $steps as &$step ) {
                if ( $step['counts'] && 'milestone' !== $step['completion'] && 'complete' !== $step['status'] ) {
                    $step['status']   = 'current';
                    $step['subtitle'] = __( 'Recommended next step', 'woocommerce-wholesale-prices' );
                    break;
                }
            }
            unset( $step );

            return $steps;
        }

        /**
         * Optional, uncounted discovery rows for sibling plugins that are not active yet.
         *
         * @since 2.3.0
         * @access private
         *
         * @return array
         */
        private function discovery_steps() {
            $candidates = array(
                array(
                    'active' => WWP_Helper_Functions::is_wwof_active(),
                    'id'     => 'explore_wwof',
                    'title'  => __( 'Explore the Wholesale Order Form', 'woocommerce-wholesale-prices' ),
                    'desc'   => __( 'Let buyers order in bulk from a single page.', 'woocommerce-wholesale-prices' ),
                    'url'    => WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-order-form', 'wwp', 'upsell', 'onboardingexplorewwof' ),
                ),
                array(
                    'active' => WWP_Helper_Functions::is_wwpp_active(),
                    'id'     => 'explore_wwpp',
                    'title'  => __( 'Explore Wholesale Prices Premium', 'woocommerce-wholesale-prices' ),
                    'desc'   => __( 'Unlock role- and category-based pricing rules and more.', 'woocommerce-wholesale-prices' ),
                    'url'    => WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'onboardingexplorewwpp' ),
                ),
                array(
                    'active' => WWP_Helper_Functions::is_wwlc_active(),
                    'id'     => 'explore_wwlc',
                    'title'  => __( 'Explore Wholesale Lead Capture', 'woocommerce-wholesale-prices' ),
                    'desc'   => __( 'Add a registration and approval flow for wholesale applicants.', 'woocommerce-wholesale-prices' ),
                    'url'    => WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-lead-capture', 'wwp', 'upsell', 'onboardingexplorewwlc' ),
                ),
                array(
                    'active' => WWP_Helper_Functions::is_wpay_active(),
                    'id'     => 'explore_wpay',
                    'title'  => __( 'Explore Wholesale Payments', 'woocommerce-wholesale-prices' ),
                    'desc'   => __( 'Let wholesale customers pay by card, ACH or bank transfer at checkout.', 'woocommerce-wholesale-prices' ),
                    'url'    => WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-payments', 'wwp', 'upsell', 'onboardingexplorewpay' ),
                ),
            );

            $steps = array();

            foreach ( $candidates as $candidate ) {
                if ( $candidate['active'] ) {
                    continue;
                }

                $steps[] = array(
                    'id'          => $candidate['id'],
                    'title'       => $candidate['title'],
                    'description' => $candidate['desc'],
                    'status'      => 'todo',
                    'completion'  => 'auto',
                    'counts'      => false,
                    'action'      => array(
                        'type'  => 'link',
                        'label' => __( 'Learn more', 'woocommerce-wholesale-prices' ),
                        'url'   => esc_url_raw( $candidate['url'] ),
                    ),
                );
            }

            return $steps;
        }

        /**
         * The three auto-detected signals, memoized in a short-lived transient.
         *
         * Each detector is an existence check that touches large tables (`wp_postmeta`, the wholesale
         * users query, an orders lookup), and {@see WWP_Onboarding_Data_Contract::assemble()} runs on
         * every dashboard render and every REST write. Caching mirrors how the sibling
         * {@see WWP_Dashboard} wraps its quick-stats queries, and honours the same
         * `wwp_enable_dashboard_cache` switch, so a large store does not pay three scans per view.
         * The cache is busted on the events that flip a signal ({@see self::flush_detector_cache()}),
         * so a completed step ticks on the next render; the short TTL is only a backstop.
         *
         * @since 2.3.0
         * @access private
         *
         * @return array{price:bool,customer:bool,order:bool}
         */
        private function detector_signals() {
            $cache_enabled = (bool) apply_filters( 'wwp_enable_dashboard_cache', true );

            if ( $cache_enabled ) {
                $cached = get_transient( self::DETECTOR_CACHE );

                if ( is_array( $cached ) ) {
                    return $cached;
                }
            }

            $signals = array(
                'price'    => $this->has_wholesale_price(),
                'customer' => $this->has_wholesale_customer(),
                'order'    => $this->has_wholesale_order(),
            );

            if ( $cache_enabled ) {
                set_transient( self::DETECTOR_CACHE, $signals, MINUTE_IN_SECONDS );
            }

            return $signals;
        }

        /**
         * Delete the memoized detector signals so the next render re-reads live state.
         *
         * Hooked to the post-commit event for each signal — the product CRUD save (fired after WWP's
         * own price-marker write, for any product type), the order create/status change, and the role
         * add/change — so the flush always follows the data write it reacts to. See {@see self::run()}
         * for why the timing matters.
         *
         * @since 2.3.0
         * @access public
         */
        public function flush_detector_cache() {
            delete_transient( self::DETECTOR_CACHE );
        }

        /**
         * Whether any product/variation carries a wholesale price for any role.
         *
         * Reuses WWP's canonical `{role}_have_wholesale_price = 'yes'` marker (the same signal
         * {@see WWP_Usage::_fetch_activation_signals()} reports).
         *
         * @since 2.3.0
         * @access private
         *
         * @return bool
         */
        private function has_wholesale_price() {
            global $wpdb;

            return (bool) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value = %s LIMIT 1",
                    '%_have_wholesale_price',
                    'yes'
                )
            );
        }

        /**
         * Whether at least one user holds a wholesale role.
         *
         * @since 2.3.0
         * @access private
         *
         * @return bool
         */
        private function has_wholesale_customer() {
            return ! empty( WWP_Wholesale_Roles::getInstance()->get_all_wholesale_user_ids() );
        }

        /**
         * Whether the store has taken at least one wholesale order.
         *
         * Uses the same wholesale-order marker the dashboard reports (`_wwpp_order_type`), through
         * `wc_get_orders()` so it is HPOS-safe.
         *
         * @since 2.3.0
         * @since 2.3.0 Switched from a `meta_query` array to the `meta_key`/`meta_value` shortcut.
         *              The legacy (non-HPOS) order datastore silently DISCARDS `meta_query`
         *              (`WC_Data_Store_WP::get_wp_query_args()` skips it outright, and the caller's
         *              array is never restored), so on legacy storage this previously matched ANY
         *              order at all — a store with retail orders only reported this milestone
         *              complete. The shortcut is honoured by both datastores, so the
         *              `_wwpp_order_type = 'wholesale'` marker is now actually required; a legacy
         *              store whose orders are all retail now correctly reports `first_order` as
         *              waiting, which it did not before.
         * @access private
         *
         * @return bool
         */
        private function has_wholesale_order() {
            if ( ! function_exists( 'wc_get_orders' ) ) {
                return false;
            }

            $orders = wc_get_orders(
                array(
                    'limit'      => 1,
                    'return'     => 'ids',
                    'meta_key'   => '_wwpp_order_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value' => 'wholesale', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                )
            );

            return ! empty( $orders );
        }

        /**
         * The admin URL of the orders screen (HPOS-aware).
         *
         * @since 2.3.0
         * @access private
         *
         * @return string
         */
        private function orders_screen_url() {
            if (
                class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
                && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
            ) {
                return admin_url( 'admin.php?page=wc-orders' );
            }

            return admin_url( 'edit.php?post_type=shop_order' );
        }
    }
}
