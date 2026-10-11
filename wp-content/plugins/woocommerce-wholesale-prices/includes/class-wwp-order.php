<?php
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Order' ) ) {

    /**
     * Model that houses the logic of integrating with WooCommerce orders.
     * Be it be adding additional data/meta to orders or order items, etc..
     *
     * @since 1.3.0
     */
    class WWP_Order {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWP_Order.
         *
         * @since 1.3.0
         * @access private
         * @var WWP_Order
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
         *  Class Methods
         */

        /**
         * WWP_Order constructor.
         *
         * @since 1.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Order model.
         */
        public function __construct( $dependencies ) {
            $this->_wwp_wholesale_roles = $dependencies['WWP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWP_Order is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Order model.
         * @return WWP_Order
         */
        public static function instance( $dependencies ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Add order meta.
         *
         * @since 1.3.0
         * @since 2.1.10.1 Compatibility with checkout blocks.
         *                 '_wwp_add_order_meta' deprecated, replaced with 'wwp_add_order_meta'
         * @since 2.2.8   Hooked into pre-save checkout actions ( woocommerce_checkout_create_order and
         *                woocommerce_store_api_checkout_update_order_from_request ) and removed the explicit
         *                $order->save() call so WooCommerce persists the meta during its own save cycle.
         *                Prevents a premature wc_order_stats re-sync that amplified the WWPP tax_total
         *                race condition ( issue #869 ). The wwp_add_order_meta action now fires before
         *                WooCommerce's initial order save; consumers must not call $order->save() inside it,
         *                or the premature re-sync returns. The deprecated _wwp_add_order_meta action keeps
         *                its original post-save timing and argument shape via fire_deprecated_add_order_meta_action().
         * @access public
         *
         * @param int|WC_Order $order_id_or_order_object Order id or order object.
         */
        public function wwp_add_order_meta( $order_id_or_order_object ) {
            $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();

            if ( ! empty( $user_wholesale_role ) ) {
                $order = ( $order_id_or_order_object instanceof WC_Order ) ? $order_id_or_order_object : wc_get_order( $order_id_or_order_object );

                $order->update_meta_data( 'wwp_wholesale_role', $user_wholesale_role[0] );

                do_action( 'wwp_add_order_meta', $order, $user_wholesale_role );

            }
        }

        /**
         * Fire the deprecated _wwp_add_order_meta action on the legacy post-save checkout hooks.
         *
         * The main wwp_add_order_meta() handler moved to pre-save hooks in 2.2.8 to avoid a premature
         * wc_order_stats re-sync. The deprecated _wwp_add_order_meta action must keep its original
         * post-save timing and first-argument shape ( int order id on classic checkout, WC_Order on
         * checkout blocks ) for backwards compatibility with third-party consumers. This handler fires
         * the action only; it does not modify or save the order, so it does not re-introduce the
         * woocommerce_update_order / wc_order_stats amplification fixed in issue #869.
         *
         * @since 2.2.8
         * @access public
         *
         * @param int|WC_Order $order_id_or_order_object Order id on classic checkout, WC_Order on checkout blocks.
         * @param array        $posted_data              Posted data from checkout ( classic only; empty on blocks ).
         */
        public function fire_deprecated_add_order_meta_action( $order_id_or_order_object, $posted_data = array() ) {
            $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();

            if ( empty( $user_wholesale_role ) ) {
                return;
            }

            wc_do_deprecated_action(
                '_wwp_add_order_meta',
                array(
                    $order_id_or_order_object,
                    $posted_data,
                    $user_wholesale_role,
                ),
                '2.1.10.1',
                'wwp_add_order_meta',
                'This action was deprecated in version 2.1.10.1, please use wwp_add_order_meta instead.'
            );
        }

        /**
         * Attach order item meta for new orders since WWP 1.3.0 for more accurate reporting in the future versions of WWPP.
         * Replaces the wwp_add_order_item_meta function of WWP 1.3.0.
         *
         * @since 1.3.1
         * @since 2.0.2 Add support to add order item meta using item object when the item ID is not available.
         * @since 2.1.5 Remove legacy code related to pre-WC 3.0.
         * @since 2.2.0 Refactor $order->save since it is saved by WooCommerce
         * @access public
         *
         * @param WC_Order_Item $item          Order item object.
         * @param string        $cart_item_key Cart item unique hash key.
         * @param array         $values        Cart item data.
         * @param WC_Order      $order         Order object.
         */
        public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
            $user_wholesale_role = $this->_wwp_wholesale_roles->getUserWholesaleRole();

            if ( isset( $values['wwp_data'] ) && ! empty( $values['wwp_data'] ) && isset( $values['wwp_data']['wholesale_role'] ) &&
                ! empty( $user_wholesale_role ) && $user_wholesale_role[0] === $values['wwp_data']['wholesale_role']
            ) {
                if ( isset( $values['wwp_data']['wholesale_priced'] ) ) {
                    $item->update_meta_data( '_wwp_wholesale_priced', $values['wwp_data']['wholesale_priced'] );
                }

                if ( isset( $values['wwp_data']['wholesale_role'] ) ) {
                    $item->update_meta_data( '_wwp_wholesale_role', $values['wwp_data']['wholesale_role'] );
                }
            }

            do_action( 'wwp_add_order_item_meta', $item, $cart_item_key, $values, $order );
        }

        /**
         * ############################################################################################################
         * Move the Order type filtering feature from WWPP to WWP
         *
         * @since 1.15.0
         * ##########################################################################################################*/

        /**
         * Add custom column to order listing page.
         *
         * @since 1.0.0
         * @since 1.14.0 Refactor codebase and move to its own model.
         * @access public
         *
         * @param array $columns Orders cpt listing columns.
         * @return array Filtered orders cpt listing columns.
         */
        public function add_orders_listing_custom_column( $columns ) {
            $arrayKeys = array_keys( $columns );
            $lastIndex = $arrayKeys[ count( $arrayKeys ) - 1 ];
            $lastValue = $columns[ $lastIndex ];
            array_pop( $columns );

            $columns['wwpp_order_type'] = __( 'Order Type', 'woocommerce-wholesale-prices' );

            $columns[ $lastIndex ] = $lastValue;

            return $columns;
        }

        /**
         * Add content to the custom column on order listing page.
         *
         * @since 1.0.0
         * @since 1.14.0 Refactor codebase and move to its own model.
         * @access public
         *
         * @param string $column  Current column key.
         * @param int    $post_id Current post id.
         */
        public function add_orders_listing_custom_column_content( $column, $post_id ) {
            if ( 'wwpp_order_type' === $column ) {

                $order      = wc_get_order( $post_id );
                $order_type = $order->get_meta( '_wwpp_order_type', true );

                if ( '' === $order_type || null === $order_type || false === $order_type || 'retail' === $order_type ) {

                    esc_html_e( 'Retail', 'woocommerce-wholesale-prices' );

                } elseif ( 'wholesale' === $order_type ) {

                    $all_registered_wholesale_roles = maybe_unserialize( get_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES ) );
                    if ( ! is_array( $all_registered_wholesale_roles ) ) {
                        $all_registered_wholesale_roles = array();
                    }

                    $wholesale_order_type = $order->get_meta( '_wwpp_wholesale_order_type', true );

                    if ( isset( $all_registered_wholesale_roles[ $wholesale_order_type ] ) && ! empty( $all_registered_wholesale_roles[ $wholesale_order_type ]['roleName'] ) ) {
                        // translators: %1$s: wholesale role name.
                        echo esc_html( sprintf( __( 'Wholesale (%1$s)', 'woocommerce-wholesale-prices' ), $all_registered_wholesale_roles[ $wholesale_order_type ]['roleName'] ) );

                        if ( ! WWP_Helper_Functions::is_wwpp_active() && 'wholesale_customer' !== $wholesale_order_type ) {
                            echo wp_kses_post( wc_help_tip( __( 'This wholesale role is exclusive only for Wholesale Prices Premium. Make sure the plugin is active.', 'woocommerce-wholesale-prices' ) ) );
                        }
                    }
                }
            }
        }

        /**
         * Attach custom meta to orders ( the order type metadata ) to be used later for filtering orders by order type
         * on the order listing page.
         *
         * @since 1.0.0
         * @since 1.14.0   Refactor codebase and move to its own model.
         * @since 2.1.10.1 Compatibility with checkout blocks.
         * @since 2.2.8    Hooked into pre-save checkout actions ( woocommerce_checkout_create_order and
         *                 woocommerce_store_api_checkout_update_order_from_request ) and removed the explicit
         *                 $current_order->save() call so WooCommerce persists the meta during its own save
         *                 cycle. Prevents a premature wc_order_stats re-sync that amplified the WWPP
         *                 tax_total race condition ( issue #869 ).
         * @access public
         *
         * @param int|WC_Order $order_id_or_order_object Order id or order object.
         */
        public function add_order_type_meta_to_wc_orders( $order_id_or_order_object ) {
            $all_registered_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $current_order                  = ( $order_id_or_order_object instanceof WC_Order ) ? $order_id_or_order_object : wc_get_order( $order_id_or_order_object );
            $current_order_wp_user          = get_userdata( $current_order->get_user_id() );
            $current_order_user_roles       = array();

            if ( $current_order_wp_user ) {
                $current_order_user_roles = $current_order_wp_user->roles;
            }

            if ( ! is_array( $current_order_user_roles ) ) {
                $current_order_user_roles = array();
            }

            $all_registered_wholesale_roles_keys = array();
            foreach ( $all_registered_wholesale_roles as $roleKey => $role ) {
                $all_registered_wholesale_roles_keys[] = $roleKey;
            }

            $orderUserWholesaleRole = array_values( array_intersect( $current_order_user_roles, $all_registered_wholesale_roles_keys ) );

            if ( isset( $orderUserWholesaleRole[0] ) ) {

                $current_order->update_meta_data( '_wwpp_order_type', 'wholesale' );
                $current_order->update_meta_data( '_wwpp_wholesale_order_type', $orderUserWholesaleRole[0] );

            } else {

                $current_order->update_meta_data( '_wwpp_order_type', 'retail' );
                $current_order->update_meta_data( '_wwpp_wholesale_order_type', '' );

            }
        }

        /**
         * Add custom filter on order listing page ( order type filter ).
         *
         * @since 1.0.0
         * @since 1.14.0 Refactor codebase and move to its own model.
         * @since 2.1.8  Add compatibility with HPOS.
         * @access public
         *
         * @param string|null $order_type  The order type.
         * @param string|null $which       The location of the extra table nav: 'top' or 'bottom'.
         */
        public function add_wholesale_role_order_listing_filter( $order_type = null, $which = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
            global $typenow;

            $screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
                ? $order_type
                : $typenow;

            $all_registered_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( 'shop_order' === $screen ) {

                ob_start();

                // Filter By Wholesale Role.
                $wwpp_fbwr = null;

                if ( isset( $_GET['wwpp_fbwr'] ) ) {  //phpcs:ignore
                    $wwpp_fbwr = sanitize_text_field( wp_unslash( trim($_GET['wwpp_fbwr']) ) ); //phpcs:ignore
                }

                $all_registered_wholesale_roles = array( 'all_wholesale_orders' => array( 'roleName' => __( 'All Wholesale Orders', 'woocommerce-wholesale-prices' ) ) ) + $all_registered_wholesale_roles;
                $all_registered_wholesale_roles = array( 'all_retail_orders' => array( 'roleName' => __( 'All Retail Orders', 'woocommerce-wholesale-prices' ) ) ) + $all_registered_wholesale_roles;
                $all_registered_wholesale_roles = array( 'all_order_types' => array( 'roleName' => __( 'Show all order types', 'woocommerce-wholesale-prices' ) ) ) + $all_registered_wholesale_roles;
                ?>

                <select name="wwpp_fbwr" id="filter-by-wholesale-role" class="chosen_select">
                    <?php foreach ( $all_registered_wholesale_roles as $roleKey => $role ) : ?>
                        <option value="<?php echo esc_attr( $roleKey ); ?>" <?php echo ( $roleKey === $wwpp_fbwr ) ? 'selected' : ''; ?>>
                            <?php echo esc_html( $role['roleName'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php
                echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }

        /**
         * Add functionality to the custom filter added on order listing page ( order type filter ).
         *
         * @since 1.0.0
         * @since 1.14.0 Refactor codebase and move to its own model.
         * @since 2.1.8  If custom orders table is enabled, dont execute the query modification.
         * @access public
         *
         * @param WP_Query $query WP_Query object.
         */
        public function wholesale_role_order_listing_filter( $query ) {
            global $pagenow;
            $wholesale_filter = null;

            $allowed_filters = array( 'all_order_types', 'all_retail_orders', 'all_wholesale_orders' );
            if ( isset( $_GET['wwpp_fbwr'] ) ) { //phpcs:ignore
                $wholesale_filter = sanitize_text_field( wp_unslash( trim( $_GET['wwpp_fbwr'] ) ) ); //phpcs:ignore
                if ( ! in_array( $wholesale_filter, $allowed_filters, true ) && ! empty( $wholesale_filter ) ) {
                    // Validate it's a valid wholesale role if not in allowed filters.
                    $all_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
                    if ( ! isset( $all_wholesale_roles[ $wholesale_filter ] ) ) {
                        $wholesale_filter = null;
                    }
                }
            }

            if ( ! OrderUtil::custom_orders_table_usage_is_enabled() &&
                'edit.php' === $pagenow &&
                isset( $query->query_vars['post_type'] ) &&
                'shop_order' === $query->query_vars['post_type']
            ) {

                switch ( $wholesale_filter ) {

                    case null:
                        // Do nothing.
                        break;

                    case 'all_order_types':
                        // Do nothing.
                        break;

                    case 'all_retail_orders':
                        $query->set(
                            'meta_query',
                            array(
                                'relation' => 'OR',
                                array(
                                    'key'     => '_wwpp_order_type',
                                    'value'   => array( 'retail' ),
                                    'compare' => 'IN',
                                ),
                                array(
                                    'key'     => '_wwpp_order_type',
                                    'value'   => 'gebbirish', // Pre WP 3.9 bug, must set string for NOT EXISTS to work.
                                    'compare' => 'NOT EXISTS',
                                ),
                            )
                        );

                        break;

                    case 'all_wholesale_orders':
                        $query->query_vars['meta_key']   = '_wwpp_order_type';
                        $query->query_vars['meta_value'] = 'wholesale';

                        break;

                    default:
                        $query->query_vars['meta_key']   = '_wwpp_wholesale_order_type';
                        $query->query_vars['meta_value'] = $wholesale_filter;

                        break;

                }
            }

            return $query;
        }

        /**
         * Wholesale order type custom filter for the query arguments used in the (Custom Order Table-powered)
         * order list table.
         *
         * @since 2.1.8
         * @access public
         *
         * @param array $order_query_args Arguments to be passed to `wc_get_orders()`.
         * @return array
         */
        public function custom_order_tables_query_args( $order_query_args ) {
            $wholesale_filter = null;

            $allowed_filters = array( 'all_order_types', 'all_retail_orders', 'all_wholesale_orders' );
            if ( isset( $_GET['wwpp_fbwr'] ) ) { //phpcs:ignore
                $wholesale_filter = sanitize_text_field( wp_unslash( trim( $_GET['wwpp_fbwr'] ) ) ); //phpcs:ignore
                if ( ! in_array( $wholesale_filter, $allowed_filters, true ) && ! empty( $wholesale_filter ) ) {
                    // Validate it's a valid wholesale role if not in allowed filters.
                    $all_wholesale_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
                    if ( ! isset( $all_wholesale_roles[ $wholesale_filter ] ) ) {
                        $wholesale_filter = null;
                    }
                }
            }

            if ( ! is_null( $wholesale_filter ) ) {
                switch ( $wholesale_filter ) {

                    case null:
                        // Do nothing.
                        break;

                    case 'all_order_types':
                        // Do nothing.
                        break;

                    case 'all_retail_orders':
                        $order_query_args['meta_query'] = array(
                            'relation' => 'OR',
                            array(
                                'key'     => '_wwpp_order_type',
                                'value'   => array( 'retail' ),
                                'compare' => 'IN',
                            ),
                            array(
                                'key'     => '_wwpp_order_type',
                                'compare' => 'NOT EXISTS',
                            ),
                        );

                        break;

                    case 'all_wholesale_orders':
                        $order_query_args['meta_key']   = '_wwpp_order_type';
                        $order_query_args['meta_value'] = 'wholesale';

                        break;

                    default:
                        $order_query_args['meta_key']   = '_wwpp_wholesale_order_type';
                        $order_query_args['meta_value'] = $wholesale_filter;

                        break;

                }
            }
            return $order_query_args;
        }

        /**
         * Execute model.
         *
         * @since 1.3.0
         * @since 2.2.8 Moved the wwp_add_order_meta and add_order_type_meta_to_wc_orders handlers off the
         *              post-save woocommerce_checkout_order_processed / woocommerce_store_api_checkout_order_processed
         *              hooks onto the pre-save woocommerce_checkout_create_order and
         *              woocommerce_store_api_checkout_update_order_from_request hooks. WooCommerce now persists
         *              the wholesale meta during its own save cycle instead of via an explicit $order->save()
         *              call, which avoided a premature wc_order_stats re-sync ( issue #869 ).
         * @access public
         */
        public function run() {
            // Add order meta on classic checkout.
            add_action( 'woocommerce_checkout_create_order', array( $this, 'wwp_add_order_meta' ), 10, 1 );

            // Add order meta on checkout blocks.
            add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'wwp_add_order_meta' ), 10, 1 );

            // Fire the deprecated _wwp_add_order_meta action on the legacy post-save hooks so third-party
            // consumers keep receiving int $order_id on classic checkout and WC_Order on checkout blocks.
            add_action( 'woocommerce_checkout_order_processed', array( $this, 'fire_deprecated_add_order_meta_action' ), 10, 2 );
            add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'fire_deprecated_add_order_meta_action' ), 10, 1 );

            // Attach order item meta for new orders.
            add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

            // Execute add_filter for Order Filtering if WWPP is not active and WWPP Class does not exists, else execute WWPP's Order Filtering.
            if ( is_plugin_inactive( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) && ! class_exists( 'WooCommerceWholeSalePricesPremium' ) ) {

                add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_orders_listing_custom_column' ), 15, 1 ); // WordPress posts table.
                add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_orders_listing_custom_column' ), 15, 1 ); // WooCommerce orders tables.
                add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_orders_listing_custom_column_content' ), 10, 2 ); // WordPress posts table.
                add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'add_orders_listing_custom_column_content' ), 10, 2 ); // WooCommerce orders tables.

                // Add order type meta on classic checkout.
                add_action( 'woocommerce_checkout_create_order', array( $this, 'add_order_type_meta_to_wc_orders' ), 10, 1 );

                // Add order type meta on checkout blocks.
                add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'add_order_type_meta_to_wc_orders' ), 10, 1 );

                add_action( 'restrict_manage_posts', array( $this, 'add_wholesale_role_order_listing_filter' ), 10, 1 ); // WordPress posts table.
                add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'add_wholesale_role_order_listing_filter' ), 10, 2 ); // WooCommerce orders tables.
                add_filter( 'parse_query', array( $this, 'wholesale_role_order_listing_filter' ), 10, 1 );  // WordPress posts table.
                add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( $this, 'custom_order_tables_query_args' ), 10, 1 );  // WooCommerce orders tables.

            }
        }
    }

}
