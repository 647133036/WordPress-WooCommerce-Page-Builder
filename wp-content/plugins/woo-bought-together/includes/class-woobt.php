<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWoobt' ) && class_exists( 'WC_Product' ) ) {
    class WPCleverWoobt {
        public static $instance = null;
        public static $image_size = 'woocommerce_thumbnail';
        public static $localization = [];
        public static $positions = [];
        public static $priorities = [];
        public static $settings = [];
        public static $rules = [];
        public static $types = [
                'simple',
                'woosb',
                'bundle',
                'subscription',
        ];

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        function __construct() {
            // Get rules
            self::$rules = (array) get_option( 'woobt_rules', [] );

            // Init
            add_action( 'init', [ $this, 'init' ] );

            // Add image to variation
            add_filter( 'woocommerce_available_variation', [ $this, 'available_variation' ], 10, 3 );
            add_filter( 'woovr_data_attributes', [ $this, 'woovr_data_attributes' ], 10, 2 );

            // Frontend scripts
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

            // Shortcode
            add_shortcode( 'woobt', [ $this, 'shortcode' ] );
            add_shortcode( 'woobt_items', [ $this, 'shortcode' ] );

            // Product price
            add_filter( 'woocommerce_product_price_class', [ $this, 'product_price_class' ] );

            // Add to cart button & form
            add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'add_to_cart_button' ] );

            // Add to cart
            add_filter( 'woocommerce_add_to_cart_sold_individually_found_in_cart', [
                    $this,
                    'found_in_cart'
            ], 11, 2 );
            add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'add_to_cart_validation' ], 11, 4 );
            add_action( 'woocommerce_add_to_cart', [ $this, 'add_to_cart' ], 11, 6 );
            add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 11, 2 );
            add_filter( 'woocommerce_get_cart_item_from_session', [
                    $this,
                    'get_cart_item_from_session'
            ], 11, 2 );

            // Frontend AJAX
            add_action( 'wc_ajax_woobt_get_variation_items', [ $this, 'ajax_get_variation_items' ] );
            add_action( 'wc_ajax_woobt_add_all_to_cart', [ $this, 'ajax_add_all_to_cart' ] );

            // Cart contents
            add_action( 'woocommerce_before_mini_cart_contents', [ $this, 'before_mini_cart_contents' ], 9999 );
            add_action( 'woocommerce_before_calculate_totals', [ $this, 'before_calculate_totals' ], 9999 );

            // Cart item
            add_filter( 'woocommerce_cart_item_name', [ $this, 'cart_item_name' ], 10, 2 );
            add_filter( 'woocommerce_cart_item_quantity', [ $this, 'cart_item_quantity' ], 10, 3 );
            add_action( 'woocommerce_cart_item_removed', [ $this, 'cart_item_removed' ], 10, 2 );

            // Order item
            add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'order_line_item' ], 10, 3 );
            add_filter( 'woocommerce_order_item_name', [ $this, 'cart_item_name' ], 10, 2 );

            // Order again
            add_filter( 'woocommerce_order_again_cart_item_data', [ $this, 'order_again_item_data' ], 10, 2 );
            add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'cart_loaded_from_session' ] );

            // Undo remove
            add_action( 'woocommerce_cart_item_restored', [ $this, 'cart_item_restored' ], 10, 2 );

            // Search filters
            if ( WPCleverWoobt_Helper()->get_setting( 'search_sku', 'no' ) === 'yes' ) {
                add_filter( 'pre_get_posts', [ $this, 'search_sku' ], 99 );
            }

            if ( WPCleverWoobt_Helper()->get_setting( 'search_exact', 'no' ) === 'yes' ) {
                add_action( 'pre_get_posts', [ $this, 'search_exact' ], 99 );
            }

            if ( WPCleverWoobt_Helper()->get_setting( 'search_sentence', 'no' ) === 'yes' ) {
                add_action( 'pre_get_posts', [ $this, 'search_sentence' ], 99 );
            }

            // Nonce check
            add_filter( 'woobt_disable_nonce_check', function ( $check, $context ) {
                return apply_filters( 'woobt_disable_security_check', $check, $context );
            }, 10, 2 );
        }

        function init() {

            self::$types      = (array) apply_filters( 'woobt_product_types', self::$types );
            self::$image_size = apply_filters( 'woobt_image_size', self::$image_size );
            self::$positions  = apply_filters( 'woobt_positions', [
                    'before'        => esc_html__( 'Above add to cart form', 'woo-bought-together' ),
                    'after'         => esc_html__( 'Under add to cart form', 'woo-bought-together' ),
                    'before_button' => esc_html__( 'Above add to cart button', 'woo-bought-together' ),
                    'after_button'  => esc_html__( 'Under add to cart button', 'woo-bought-together' ),
                    'below_title'   => esc_html__( 'Under the title', 'woo-bought-together' ),
                    'below_price'   => esc_html__( 'Under the price', 'woo-bought-together' ),
                    'below_excerpt' => esc_html__( 'Under the excerpt', 'woo-bought-together' ),
                    'below_meta'    => esc_html__( 'Under the meta', 'woo-bought-together' ),
                    'above_summary' => esc_html__( 'Above summary', 'woo-bought-together' ),
                    'below_summary' => esc_html__( 'Under summary', 'woo-bought-together' ),
                    'none'          => esc_html__( 'None (hide it)', 'woo-bought-together' )
            ] );
            self::$priorities = apply_filters( 'woobt_priorities', [
                    'before'        => 10,
                    'after'         => 10,
                    'before_button' => 10,
                    'after_button'  => 10,
                    'below_title'   => 6,
                    'below_price'   => 11,
                    'below_excerpt' => 21,
                    'below_meta'    => 41,
                    'above_summary' => 9,
                    'below_summary' => 21
            ] );

            // Show items in standard position
            add_action( 'woocommerce_before_add_to_cart_form', [
                    $this,
                    'show_items_before_atc'
            ], absint( self::$priorities['before'] ?? 10 ) );
            add_action( 'woocommerce_after_add_to_cart_form', [
                    $this,
                    'show_items_after_atc'
            ], absint( self::$priorities['after'] ?? 10 ) );
            add_action( 'woocommerce_before_add_to_cart_button', [
                    $this,
                    'show_items_before_atc_button'
            ], absint( self::$priorities['before_button'] ?? 10 ) );
            add_action( 'woocommerce_before_add_to_cart_button', [
                    $this,
                    'show_items_after_atc_button'
            ], absint( self::$priorities['after_button'] ?? 10 ) );
            add_action( 'woocommerce_single_product_summary', [
                    $this,
                    'show_items_below_title'
            ], absint( self::$priorities['below_title'] ?? 6 ) );
            add_action( 'woocommerce_single_product_summary', [
                    $this,
                    'show_items_below_price'
            ], absint( self::$priorities['below_price'] ?? 11 ) );
            add_action( 'woocommerce_single_product_summary', [
                    $this,
                    'show_items_below_excerpt'
            ], absint( self::$priorities['below_excerpt'] ?? 21 ) );
            add_action( 'woocommerce_single_product_summary', [
                    $this,
                    'show_items_below_meta'
            ], absint( self::$priorities['below_meta'] ?? 41 ) );
            add_action( 'woocommerce_after_single_product_summary', [
                    $this,
                    'show_items_above_summary'
            ], absint( self::$priorities['above_summary'] ?? 9 ) );
            add_action( 'woocommerce_after_single_product_summary', [
                    $this,
                    'show_items_below_summary'
            ], absint( self::$priorities['below_summary'] ?? 21 ) );

            // Show items in custom position
            add_action( 'woobt_custom_position', [ $this, 'show_items_position' ] );
        }

        function available_variation( $data, $variable, $variation ) {
            if ( $image_id = $variation->get_image_id() ) {
                $data['woobt_image'] = wp_get_attachment_image( $image_id, self::$image_size );
            }

            $items = self::get_rule_items( $variation->get_id(), 'available_variation' );

            if ( ! empty( $items ) ) {
                $data['woobt_items'] = 'yes';
            } else {
                $data['woobt_items'] = 'no';
            }

            return $data;
        }

        function woovr_data_attributes( $attributes, $variation ) {
            if ( $image_id = $variation->get_image_id() ) {
                $attributes['woobt_image'] = wp_get_attachment_image( $image_id, self::$image_size );
            }

            return $attributes;
        }

        function enqueue_scripts() {
            // carousel
            if ( apply_filters( 'woobt_carousel', true ) ) {
                wp_enqueue_style( 'slick', WOOBT_URI . 'assets/slick/slick.css', [], WOOBT_VERSION );
                wp_enqueue_script( 'slick', WOOBT_URI . 'assets/slick/slick.min.js', [ 'jquery' ], WOOBT_VERSION, true );
            }

            wp_enqueue_style( 'woobt-frontend', WOOBT_URI . 'assets/css/frontend.css', [], WOOBT_VERSION );
            wp_enqueue_script( 'woobt-frontend', WOOBT_URI . 'assets/js/frontend.js', [ 'jquery' ], WOOBT_VERSION, true );
            wp_localize_script( 'woobt-frontend', 'woobt_vars', apply_filters( 'woobt_vars', [
                            'wc_ajax_url'              => WC_AJAX::get_endpoint( '%%endpoint%%' ),
                            'nonce'                    => wp_create_nonce( 'woobt-security' ),
                            'change_image'             => WPCleverWoobt_Helper()->get_setting( 'change_image', 'yes' ),
                            'change_price'             => WPCleverWoobt_Helper()->get_setting( 'change_price', 'yes' ),
                            'price_selector'           => WPCleverWoobt_Helper()->get_setting( 'change_price_custom', '' ),
                            'counter'                  => WPCleverWoobt_Helper()->get_setting( 'counter', 'individual' ),
                            'variation_selector'       => ( class_exists( 'WPClever_Woovr' ) && ( WPCleverWoobt_Helper()->get_setting( 'variations_selector', 'default' ) === 'woovr' ) ) ? 'woovr' : 'default',
                            'price_format'             => get_woocommerce_price_format(),
                            'price_suffix'             => ( $suffix = get_option( 'woocommerce_price_display_suffix' ) ) && wc_tax_enabled() ? $suffix : '',
                            'price_decimals'           => wc_get_price_decimals(),
                            'price_thousand_separator' => wc_get_price_thousand_separator(),
                            'price_decimal_separator'  => wc_get_price_decimal_separator(),
                            'currency_symbol'          => get_woocommerce_currency_symbol(),
                            'trim_zeros'               => apply_filters( 'woobt_price_trim_zeros', apply_filters( 'woocommerce_price_trim_zeros', false ) ),
                            'additional_price_text'    => WPCleverWoobt_Helper()->localization( 'additional', esc_html__( 'Additional price:', 'woo-bought-together' ) ),
                            'total_price_text'         => WPCleverWoobt_Helper()->localization( 'total', esc_html__( 'Total:', 'woo-bought-together' ) ),
                            'add_to_cart'              => WPCleverWoobt_Helper()->get_setting( 'atc_button', 'main' ) === 'main' ? WPCleverWoobt_Helper()->localization( 'add_to_cart', esc_html__( 'Add to cart', 'woo-bought-together' ) ) : WPCleverWoobt_Helper()->localization( 'add_all_to_cart', esc_html__( 'Add all to cart', 'woo-bought-together' ) ),
                            'alert_selection'          => WPCleverWoobt_Helper()->localization( 'alert_selection', esc_html__( 'Please select a purchasable variation for [name] before adding this product to the cart.', 'woo-bought-together' ) ),
                            'carousel_params'          => apply_filters( 'woobt_carousel_params', json_encode( apply_filters( 'woobt_carousel_params_arr', [
                                    'dots'           => true,
                                    'arrows'         => true,
                                    'infinite'       => false,
                                    'adaptiveHeight' => true,
                                    'rtl'            => is_rtl(),
                                    'responsive'     => [
                                            [
                                                    'breakpoint' => 768,
                                                    'settings'   => [
                                                            'slidesToShow'   => 2,
                                                            'slidesToScroll' => 2
                                                    ]
                                            ],
                                            [
                                                    'breakpoint' => 480,
                                                    'settings'   => [
                                                            'slidesToShow'   => 1,
                                                            'slidesToScroll' => 1
                                                    ]
                                            ]
                                    ]
                            ] ) ) ),
                    ] )
            );
        }

        function cart_item_removed( $cart_item_key, $cart ) {
            if ( isset( $cart->removed_cart_contents[ $cart_item_key ]['woobt_keys'] ) ) {
                $keys = $cart->removed_cart_contents[ $cart_item_key ]['woobt_keys'];

                foreach ( $keys as $key ) {
                    unset( $cart->cart_contents[ $key ] );
                }
            }
        }

        function cart_item_name( $item_name, $item ) {
            if ( ! empty( $item['woobt_parent_id'] ) ) {
                $parent_id       = apply_filters( 'woobt_parent_id', $item['woobt_parent_id'], $item );
                $associated_text = WPCleverWoobt_Helper()->localization( 'associated', /* translators: product name */ esc_html__( '(bought together %s)', 'woo-bought-together' ) );

                if ( str_contains( $item_name, '</a>' ) ) {
                    $name = sprintf( $associated_text, '<a href="' . esc_url( get_permalink( $parent_id ) ) . '">' . esc_html( get_the_title( $parent_id ) ) . '</a>' );
                } else {
                    $name = sprintf( $associated_text, esc_html( get_the_title( $parent_id ) ) );
                }

                $item_name .= ' <span class="woobt-item-name">' . apply_filters( 'woobt_item_name', $name, $item ) . '</span>';
            }

            return $item_name;
        }

        function cart_item_quantity( $quantity, $cart_item_key, $cart_item ) {
            // add qty as text - not input
            if ( isset( $cart_item['woobt_parent_id'] ) ) {
                if ( ( WPCleverWoobt_Helper()->get_setting( 'cart_quantity', 'yes' ) === 'no' ) || ( isset( $cart_item['woobt_sync_qty'] ) && $cart_item['woobt_sync_qty'] ) ) {
                    return $cart_item['quantity'];
                }
            }

            return $quantity;
        }

        function check_in_cart( $product_id ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                if ( $cart_item['product_id'] === $product_id ) {
                    return true;
                }
            }

            return false;
        }

        function found_in_cart( $found_in_cart, $product_id ) {
            if ( apply_filters( 'woobt_sold_individually_found_in_cart', true ) && self::check_in_cart( $product_id ) ) {
                return true;
            }

            return $found_in_cart;
        }

        function add_to_cart_validation( $passed, $product_id, $quantity, $variation_id = 0 ) {
            if ( ! apply_filters( 'woobt_add_to_cart_validation', true ) ) {
                return $passed;
            }

            $validate_items = [];

            // get woobt_ids in custom data array
            $custom_request = apply_filters( 'woobt_custom_request_data', 'data' );

            if ( isset( $_REQUEST['woobt_ids'] ) ) {
                $ids = wp_unslash( $_REQUEST['woobt_ids'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            } elseif ( isset( $_REQUEST[ $custom_request ]['woobt_ids'] ) ) {
                $ids = wp_unslash( $_REQUEST[ $custom_request ]['woobt_ids'] ?? '' );
            } else {
                $ids = '';
            }

            $ids = apply_filters( 'woobt_add_cart_item_data_ids', $ids, [], $product_id );

            if ( ! empty( $ids ) ) {
                $validate_items = self::get_items_from_ids( $ids, $product_id );
            }

            // validate if it has items
            $items         = self::get_items( $product_id, 'validate' );
            $rule_items    = self::get_rule_items( $product_id, 'validate' );
            $product_items = $variation_id ? array_merge( self::get_product_items( $product_id, 'validate' ), self::get_rule_items( $variation_id, 'validate' ) ) : self::get_product_items( $product_id, 'validate' );

            if ( ! empty( $validate_items ) && ! empty( $items ) ) {
                foreach ( $validate_items as $validate_key => $validate_item ) {
                    $item_product = wc_get_product( $validate_item['id'] );

                    if ( ! $item_product ) {
                        wc_add_notice( esc_html__( 'One of the associated products is unavailable.', 'woo-bought-together' ), 'error' );
                        wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                        return false;
                    }

                    if ( ! empty( $product_items ) ) {
                        // if it has specified items
                        if ( ! isset( $product_items[ $validate_key ] ) ) {
                            wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                            return false;
                        }

                        if ( is_a( $item_product, 'WC_Product_Variation' ) ) {
                            $parent_id = apply_filters( 'woobt_parent_id', $item_product->get_parent_id() );

                            if ( ( $product_items[ $validate_key ]['id'] != $parent_id ) && ( $product_items[ $validate_key ]['id'] != $validate_item['id'] ) ) {
                                wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                                return false;
                            }
                        } else {
                            if ( $product_items[ $validate_key ]['id'] != $validate_item['id'] ) {
                                wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                                return false;
                            }
                        }
                    } elseif ( ! empty( $rule_items ) ) {
                        // if it has rule items
                        if ( ! isset( $rule_items[ $validate_key ] ) ) {
                            wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                            return false;
                        }

                        if ( is_a( $item_product, 'WC_Product_Variation' ) ) {
                            $parent_id = apply_filters( 'woobt_parent_id', $item_product->get_parent_id() );

                            if ( ( $rule_items[ $validate_key ]['id'] != $parent_id ) && ( $rule_items[ $validate_key ]['id'] != $validate_item['id'] ) ) {
                                wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                                return false;
                            }
                        } else {
                            if ( $rule_items[ $validate_key ]['id'] != $validate_item['id'] ) {
                                wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                                return false;
                            }
                        }
                    }

                    if ( $item_product->is_type( 'variable' ) ) {
                        wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" is un-purchasable.', 'woo-bought-together' ), esc_html( apply_filters( 'woobt_product_get_name', $item_product->get_name(), $item_product ) ) ), 'error' );
                        wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                        return false;
                    }

                    if ( $item_product->is_sold_individually() && apply_filters( 'woobt_sold_individually_found_in_cart', true ) && self::check_in_cart( $validate_item['id'] ) ) {
                        wc_add_notice( sprintf( /* translators: product name */ esc_html__( 'You cannot add another "%s" to the cart.', 'woo-bought-together' ), esc_html( apply_filters( 'woobt_product_get_name', $item_product->get_name(), $item_product ) ) ), 'error' );
                        wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                        return false;
                    }

                    if ( apply_filters( 'woobt_custom_qty', self::get_product_quantity( $product_id, 'validate' ) === 'custom', $product_id ) ) {
                        // custom qty
                        if ( ( $limit_min = apply_filters( 'woobt_limit_each_min', get_post_meta( $product_id, 'woobt_limit_each_min', true ), $validate_item, $product_id ) ) && ( $validate_item['qty'] < (float) $limit_min ) ) {
                            wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" does not reach the minimum quantity.', 'woo-bought-together' ), esc_html( apply_filters( 'woobt_product_get_name', $item_product->get_name(), $item_product ) ) ), 'error' );
                            wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                            return false;
                        }

                        if ( ( $limit_max = apply_filters( 'woobt_limit_each_max', get_post_meta( $product_id, 'woobt_limit_each_max', true ), $validate_item, $product_id ) ) && ( $validate_item['qty'] > (float) $limit_max ) ) {
                            wc_add_notice( sprintf( /* translators: product name */ esc_html__( '"%s" passes the maximum quantity.', 'woo-bought-together' ), esc_html( apply_filters( 'woobt_product_get_name', $item_product->get_name(), $item_product ) ) ), 'error' );
                            wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                            return false;
                        }
                    } else {
                        // fixed qty
                        if ( isset( $product_items[ $validate_key ]['qty'] ) && ( $product_items[ $validate_key ]['qty'] != $validate_item['qty'] ) ) {
                            wc_add_notice( esc_html__( 'You cannot add this product to the cart.', 'woo-bought-together' ), 'error' );

                            return false;
                        }
                    }
                }
            }

            return $passed;
        }

        function add_cart_item_data( $cart_item_data, $product_id ) {
            // get woobt_ids in custom data array
            $custom_request = apply_filters( 'woobt_custom_request_data', 'data' );

            if ( isset( $_REQUEST['woobt_ids'] ) ) {
                $ids = wp_unslash( $_REQUEST['woobt_ids'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                unset( $_REQUEST['woobt_ids'] );
            } elseif ( isset( $_REQUEST[ $custom_request ]['woobt_ids'] ) ) {
                $ids = wp_unslash( $_REQUEST[ $custom_request ]['woobt_ids'] ?? '' );
                unset( $_REQUEST[ $custom_request ]['woobt_ids'] );
            } else {
                $ids = '';
            }

            $ids = apply_filters( 'woobt_add_cart_item_data_ids', $ids, $cart_item_data, $product_id );

            if ( ! empty( $ids ) ) {
                $cart_item_data['woobt_ids'] = $ids;
            }

            return $cart_item_data;
        }

        function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
            $product_id = apply_filters( 'woobt_add_to_cart_product_id', $product_id, $cart_item_data );
            $items      = $variation_id ? array_merge( self::get_items( $product_id, 'add-to-cart' ), self::get_items( $variation_id, 'add-to-cart' ) ) : self::get_items( $product_id, 'add-to-cart' ); // make sure it has items

            if ( ! empty( $cart_item_data['woobt_ids'] ) && ! empty( $items ) ) {
                $ids = $cart_item_data['woobt_ids'];

                if ( $add_items = self::get_items_from_ids( $ids, $product_id ) ) {
                    $custom_qty  = apply_filters( 'woobt_custom_qty', self::get_product_quantity( $product_id, 'add-to-cart' ) === 'custom', $product_id );
                    $separately  = apply_filters( 'woobt_separately', get_post_meta( $product_id, 'woobt_separately', true ) === 'on', $product_id );
                    $reset_price = apply_filters( 'woobt_separately_reset_price', true, $product_id, 'add-to-cart' );
                    $ignore_this = apply_filters( 'woobt_separately_ignore_this_item', false, $product_id );
                    $sync_qty    = ! $custom_qty && apply_filters( 'woobt_sync_qty', self::get_product_quantity( $product_id, 'add-to-cart' ) === 'sync' );

                    if ( ! $separately ) {
                        // add sync_qty for the main product
                        WC()->cart->cart_contents[ $cart_item_key ]['woobt_ids']      = $ids;
                        WC()->cart->cart_contents[ $cart_item_key ]['woobt_key']      = $cart_item_key;
                        WC()->cart->cart_contents[ $cart_item_key ]['woobt_sync_qty'] = $sync_qty;
                    } else {
                        WC()->cart->remove_cart_item( $cart_item_key );

                        if ( ! $ignore_this ) {
                            // unset woobt_ids then add main product again
                            unset( $cart_item_data['woobt_ids'] );

                            if ( ! $reset_price ) {
                                $cart_item_data['woobt_new_price'] = ( 100 - self::get_discount( $product_id ) ) . '%';
                            }

                            WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
                        }
                    }

                    // add child products
                    self::add_to_cart_items( $add_items, $cart_item_key, $product_id, $quantity );
                }
            }
        }

        function add_to_cart_items( $items, $cart_item_key, $product_id, $quantity ) {
            $pricing       = WPCleverWoobt_Helper()->get_setting( 'pricing', 'sale_price' );
            $ignore_onsale = WPCleverWoobt_Helper()->get_setting( 'ignore_onsale', 'no' );
            $custom_qty    = apply_filters( 'woobt_custom_qty', self::get_product_quantity( $product_id, 'add-to-cart' ) === 'custom', $product_id );
            $separately    = apply_filters( 'woobt_separately', get_post_meta( $product_id, 'woobt_separately', true ) === 'on', $product_id );
            $reset_price   = apply_filters( 'woobt_separately_reset_price', true, $product_id, 'add-to-cart' );
            $sync_qty      = ! $custom_qty && apply_filters( 'woobt_sync_qty', self::get_product_quantity( $product_id, 'add-to-cart' ) === 'sync' );

            // add child products
            foreach ( $items as $item ) {
                $item_id      = apply_filters( 'woobt_item_id', $item['id'], $item, $product_id );
                $item_product = wc_get_product( $item_id );

                if ( in_array( $ignore_onsale, [
                                'fbt',
                                'both'
                        ] ) && ( $pricing === 'sale_price' ) && $item_product->is_on_sale() ) {
                    $item['price'] = '100%';
                }

                $item_qty          = apply_filters( 'woobt_item_qty', $item['qty'], $item, $product_id );
                $item_price        = apply_filters( 'woobt_item_price', $item['price'], $item, $product_id );
                $item_variation    = apply_filters( 'woobt_item_attrs', $item['attrs'], $item, $product_id );
                $item_variation_id = 0;

                if ( $item_product instanceof WC_Product_Variation ) {
                    // ensure we don't add a variation to the cart directly by variation ID
                    $item_variation_id = $item_id;
                    $item_id           = $item_product->get_parent_id();

                    if ( empty( $item_variation ) ) {
                        $item_variation = $item_product->get_variation_attributes();
                    }
                }

                if ( $item_product && $item_product->is_in_stock() && $item_product->is_purchasable() && ( 'trash' !== $item_product->get_status() ) ) {
                    if ( ! $separately ) {
                        // add to cart
                        $item_key = WC()->cart->add_to_cart( $item_id, $item_qty, $item_variation_id, $item_variation, [
                                'woobt_parent_id'  => $product_id,
                                'woobt_parent_key' => $cart_item_key,
                                'woobt_qty'        => $item_qty,
                                'woobt_sync_qty'   => $sync_qty,
                                'woobt_price_item' => $item_price
                        ] );

                        if ( $item_key ) {
                            WC()->cart->cart_contents[ $item_key ]['woobt_key']         = $item_key;
                            WC()->cart->cart_contents[ $cart_item_key ]['woobt_keys'][] = $item_key;
                        }
                    } else {
                        $item_data = apply_filters( 'woobt_add_to_cart_item_data', $reset_price ? [] : [ 'woobt_new_price' => $item_price ] );

                        if ( $sync_qty ) {
                            WC()->cart->add_to_cart( $item_id, $item_qty * $quantity, $item_variation_id, $item_variation, $item_data );
                        } else {
                            WC()->cart->add_to_cart( $item_id, $item_qty, $item_variation_id, $item_variation, $item_data );
                        }
                    }
                }
            }
        }

        function ajax_get_variation_items() {
            if ( ! apply_filters( 'woobt_disable_nonce_check', false, 'get_variation' ) ) {
                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) ) {
                    die( 'Permissions check failed!' );
                }
            }

            if ( ! isset( $_POST['variation_id'] ) ) {
                return;
            }

            $variation_id = absint( sanitize_text_field( wp_unslash( $_POST['variation_id'] ?? '' ) ) );

            self::show_items( $variation_id, false, true );

            wp_die();
        }

        function ajax_add_all_to_cart() {
            if ( ! apply_filters( 'woobt_disable_nonce_check', false, 'add_all_to_cart' ) ) {
                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) ) {
                    die( 'Permissions check failed!' );
                }
            }

            if ( ! isset( $_POST['product_id'] ) ) {
                return;
            }

            $product_id     = apply_filters( 'woocommerce_add_to_cart_product_id', absint( wp_unslash( $_POST['product_id'] ?? '' ) ) );
            $product        = wc_get_product( $product_id );
            $product_status = get_post_status( $product_id );
            $quantity       = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $_POST['quantity'] ?? '' ) );
            $variation_id   = absint( wp_unslash( $_POST['variation_id'] ?? 0 ) );
            $variation      = (array) ( wp_unslash( $_POST['variation'] ?? [] ) );

            if ( $product && 'variation' === $product->get_type() ) {
                $variation_id = $product_id;
                $product_id   = $product->get_parent_id();

                if ( empty( $variation ) ) {
                    $variation = $product->get_variation_attributes();
                }
            }

            $cart_item_data    = apply_filters( 'woobt_add_to_cart_data', [] );
            $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation );

            if ( $passed_validation && false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data ) && 'publish' === $product_status ) {
                do_action( 'woocommerce_ajax_added_to_cart', $product_id );

                if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
                    wc_add_to_cart_message( [ $product_id => $quantity ], true );
                }

                WC_AJAX::get_refreshed_fragments();
            } else {
                $data = [
                        'error'       => true,
                        'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
                ];

                wp_send_json( $data );
            }

            wp_die();
        }

        function before_mini_cart_contents() {
            WC()->cart->calculate_totals();
        }

        function before_calculate_totals( $cart_object ) {
            if ( ! defined( 'DOING_AJAX' ) && is_admin() ) {
                // This is necessary for WC 3.0+
                return;
            }

            $cart_contents = $cart_object->cart_contents;
            $new_keys      = [];

            foreach ( $cart_contents as $cart_item_key => $cart_item ) {
                if ( ! empty( $cart_item['woobt_key'] ) ) {
                    $new_keys[ $cart_item_key ] = $cart_item['woobt_key'];
                }
            }

            foreach ( $cart_contents as $cart_item_key => $cart_item ) {
                // add separately but don't reset price
                if ( isset( $cart_item['woobt_new_price'] ) && ( $cart_item['woobt_new_price'] !== '100%' ) && ( $cart_item['woobt_new_price'] !== '' ) ) {
                    $item_product   = wc_get_product( $cart_item['variation_id'] ?: $cart_item['product_id'] );
                    $item_price     = apply_filters( 'woobt_cart_item_product_price', $item_product->get_price(), $item_product );
                    $item_new_price = WPCleverWoobt_Helper()->new_price( $item_price, $cart_item['woobt_new_price'] );

                    $cart_item['data']->set_price( $item_new_price );
                    $cart_item['data']->get_price(); // resynchronize the price cache
                }

                // associated products
                if ( isset( $cart_item['woobt_parent_id'], $cart_item['woobt_price_item'] ) && ( $cart_item['woobt_price_item'] !== '100%' ) && ( $cart_item['woobt_price_item'] !== '' ) ) {
                    $item_product   = wc_get_product( $cart_item['variation_id'] ?: $cart_item['product_id'] );
                    $item_price     = apply_filters( 'woobt_cart_item_product_price', ( WPCleverWoobt_Helper()->get_setting( 'pricing', 'sale_price' ) === 'sale_price' ? $item_product->get_price() : $item_product->get_regular_price() ), $item_product );
                    $item_new_price = WPCleverWoobt_Helper()->new_price( $item_price, $cart_item['woobt_price_item'] );

                    $cart_item['data']->set_price( $item_new_price );
                    $cart_item['data']->get_price(); // resynchronize the price cache
                }

                // sync quantity
                if ( ! empty( $cart_item['woobt_parent_key'] ) && ! empty( $cart_item['woobt_qty'] ) && ! empty( $cart_item['woobt_sync_qty'] ) ) {
                    $parent_key     = $cart_item['woobt_parent_key'];
                    $parent_new_key = array_search( $parent_key, $new_keys );

                    if ( isset( $cart_contents[ $parent_key ] ) ) {
                        WC()->cart->cart_contents[ $cart_item_key ]['quantity'] = $cart_item['woobt_qty'] * $cart_contents[ $parent_key ]['quantity'];
                    } elseif ( isset( $cart_contents[ $parent_new_key ] ) ) {
                        WC()->cart->cart_contents[ $cart_item_key ]['quantity'] = $cart_item['woobt_qty'] * $cart_contents[ $parent_new_key ]['quantity'];
                    }
                }

                // main product
                if ( ! empty( $cart_item['woobt_ids'] ) ) {
                    $ignore_onsale = WPCleverWoobt_Helper()->get_setting( 'ignore_onsale', 'no' );
                    $separately    = apply_filters( 'woobt_separately', get_post_meta( $cart_item['product_id'], 'woobt_separately', true ) === 'on', $cart_item['product_id'] );

                    if ( in_array( $ignore_onsale, [ 'main', 'both' ] ) && $cart_item['data']->is_on_sale() ) {
                        $separately = true;
                    }

                    if ( ! $separately ) {
                        $discount = self::get_discount( $cart_item['product_id'] );

                        if ( ! empty( $discount ) ) {
                            $item_product = wc_get_product( $cart_item['variation_id'] ?: $cart_item['product_id'] );
                            $item_price   = apply_filters( 'woobt_cart_item_product_price', $item_product->get_price(), $item_product );

                            // has associated products
                            $has_associated = false;

                            if ( isset( $cart_item['woobt_keys'] ) ) {
                                foreach ( $cart_item['woobt_keys'] as $key ) {
                                    if ( isset( $cart_contents[ $key ] ) ) {
                                        $has_associated = true;
                                        break;
                                    }
                                }
                            }

                            if ( $has_associated ) {
                                $item_new_price = $item_price * ( 100 - (float) $discount ) / 100;
                                $cart_item['data']->set_price( $item_new_price );
                                $cart_item['data']->get_price(); // resynchronize the price cache
                            }
                        }
                    }
                }
            }
        }

        function get_cart_item_from_session( $cart_item, $item_session_values ) {
            if ( ! empty( $item_session_values['woobt_ids'] ) ) {
                $cart_item['woobt_ids'] = $item_session_values['woobt_ids'];
            }

            if ( ! empty( $item_session_values['woobt_parent_id'] ) ) {
                $cart_item['woobt_parent_id']  = $item_session_values['woobt_parent_id'];
                $cart_item['woobt_parent_key'] = $item_session_values['woobt_parent_key'];
                $cart_item['woobt_price_item'] = $item_session_values['woobt_price_item'];
                $cart_item['woobt_qty']        = $item_session_values['woobt_qty'];
            }

            if ( ! empty( $item_session_values['woobt_sync_qty'] ) ) {
                $cart_item['woobt_sync_qty'] = $item_session_values['woobt_sync_qty'];
            }

            return $cart_item;
        }

        function order_line_item( $item, $cart_item_key, $values ) {
            // add _ to hide
            if ( isset( $values['woobt_parent_id'] ) ) {
                $item->update_meta_data( '_woobt_parent_id', $values['woobt_parent_id'] );
            }

            if ( isset( $values['woobt_ids'] ) ) {
                $item->update_meta_data( '_woobt_ids', $values['woobt_ids'] );
            }
        }

        function order_again_item_data( $data, $item ) {
            if ( $ids = $item->get_meta( '_woobt_ids' ) ) {
                $data['woobt_order_again'] = 'yes';
                $data['woobt_ids']         = $ids;
            }

            if ( $parent_id = $item->get_meta( '_woobt_parent_id' ) ) {
                $data['woobt_order_again'] = 'yes';
                $data['woobt_parent_id']   = $parent_id;
            }

            return $data;
        }

        function cart_loaded_from_session( $cart ) {
            foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
                // remove orphaned products
                if ( isset( $cart_item['woobt_parent_key'] ) && ( $parent_key = $cart_item['woobt_parent_key'] ) && ! isset( $cart->cart_contents[ $parent_key ] ) ) {
                    WC()->cart->remove_cart_item( $cart_item_key );
                }

                // remove associated products first
                if ( isset( $cart_item['woobt_order_again'], $cart_item['woobt_parent_id'] ) ) {
                    WC()->cart->remove_cart_item( $cart_item_key );
                }
            }

            foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
                // add associated products again
                if ( isset( $cart_item['woobt_order_again'], $cart_item['woobt_ids'] ) ) {
                    unset( $cart->cart_contents[ $cart_item_key ]['woobt_order_again'] );

                    $product_id = $cart_item['product_id'];
                    $custom_qty = apply_filters( 'woobt_custom_qty', self::get_product_quantity( $product_id, 'add-to-cart' ) === 'custom', $product_id );
                    $sync_qty   = ! $custom_qty && apply_filters( 'woobt_sync_qty', self::get_product_quantity( $product_id, 'add-to-cart' ) === 'sync' );

                    $cart->cart_contents[ $cart_item_key ]['woobt_key']      = $cart_item_key;
                    $cart->cart_contents[ $cart_item_key ]['woobt_sync_qty'] = $sync_qty;

                    if ( $add_items = self::get_items_from_ids( $cart_item['woobt_ids'], $cart_item['product_id'] ) ) {
                        self::add_to_cart_items( $add_items, $cart_item_key, $cart_item['product_id'], $cart_item['quantity'] );
                    }
                }
            }
        }

        function cart_item_restored( $cart_item_key, $cart ) {
            if ( isset( $cart->cart_contents[ $cart_item_key ]['woobt_ids'] ) ) {
                // remove old keys
                unset( $cart->cart_contents[ $cart_item_key ]['woobt_keys'] );

                $ids        = $cart->cart_contents[ $cart_item_key ]['woobt_ids'];
                $product_id = $cart->cart_contents[ $cart_item_key ]['product_id'];
                $quantity   = $cart->cart_contents[ $cart_item_key ]['quantity'];
                $separately = apply_filters( 'woobt_separately', get_post_meta( $product_id, 'woobt_separately', true ) === 'on', $product_id );

                if ( ! $separately ) {
                    if ( $add_items = self::get_items_from_ids( $ids, $product_id ) ) {
                        self::add_to_cart_items( $add_items, $cart_item_key, $product_id, $quantity );
                    }
                }
            }
        }

        function product_price_class( $class ) {
            global $product;

            return $class . ' woobt-price-' . $product->get_id();
        }

        function show_items_position( $pos = 'before' ) {
            global $product;

            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                return;
            }

            $_position = get_post_meta( $product->get_id(), 'woobt_position', true ) ?: 'unset';
            $position  = $_position !== 'unset' ? $_position : apply_filters( 'woobt_position', WPCleverWoobt_Helper()->get_setting( 'position', apply_filters( 'woobt_default_position', 'before' ) ) );

            if ( $position === $pos ) {
                self::show_items();
            }
        }

        function show_items_before_atc() {
            self::show_items_position( 'before' );
        }

        function show_items_after_atc() {
            self::show_items_position( 'after' );
        }

        function show_items_before_atc_button() {
            self::show_items_position( 'before_button' );
        }

        function show_items_after_atc_button() {
            self::show_items_position( 'after_button' );
        }

        function show_items_below_title() {
            self::show_items_position( 'below_title' );
        }

        function show_items_below_price() {
            self::show_items_position( 'below_price' );
        }

        function show_items_below_excerpt() {
            self::show_items_position( 'below_excerpt' );
        }

        function show_items_below_meta() {
            self::show_items_position( 'below_meta' );
        }

        function show_items_above_summary() {
            self::show_items_position( 'above_summary' );
        }

        function show_items_below_summary() {
            self::show_items_position( 'below_summary' );
        }

        function add_to_cart_button() {
            global $product;

            if ( ! $product || ! is_a( $product, 'WC_Product' ) || $product->is_type( 'grouped' ) || $product->is_type( 'external' ) ) {
                return;
            }

            $product_id  = $product->get_id();
            $_position   = get_post_meta( $product_id, 'woobt_position', true ) ?: 'unset';
            $_atc_button = get_post_meta( $product_id, 'woobt_atc_button', true ) ?: 'unset';
            $position    = $_position !== 'unset' ? $_position : apply_filters( 'woobt_position', WPCleverWoobt_Helper()->get_setting( 'position', apply_filters( 'woobt_default_position', 'before' ) ) );
            $atc_button  = apply_filters( 'woobt_atc_button', $_atc_button !== 'unset' ? $_atc_button : WPCleverWoobt_Helper()->get_setting( 'atc_button', 'main' ), $product_id );

            if ( ( $atc_button === 'main' || $atc_button === 'both' ) && ( $position !== 'none' ) ) {
                echo '<input name="woobt_ids" class="woobt-ids woobt-ids-' . esc_attr( $product_id ) . '" data-id="' . esc_attr( $product_id ) . '" type="hidden"/>';
            }
        }

        function has_variables( $items ) {
            foreach ( $items as $item ) {
                if ( is_array( $item ) && isset( $item['id'] ) ) {
                    $item_id = $item['id'];
                } else {
                    $item_id = absint( $item );
                }

                $item_product = wc_get_product( $item_id );

                if ( ! $item_product ) {
                    continue;
                }

                if ( $item_product->is_type( 'variable' ) ) {
                    return true;
                }
            }

            return false;
        }

        function shortcode( $attrs ) {
            $attrs = shortcode_atts( [ 'id' => null, 'custom_position' => true ], $attrs );

            ob_start();

            self::show_items( $attrs['id'], wc_string_to_bool( $attrs['custom_position'] ), false, 'shortcode' );

            return ob_get_clean();
        }

        function show_items( $product = null, $custom_position = false, $is_variation = false, $context = '' ) {
            $product_id = 0;

            if ( ! $product ) {
                global $product;

                if ( $product ) {
                    $product_id = $product->get_id();
                }
            } else {
                if ( is_a( $product, 'WC_Product' ) ) {
                    $product_id = $product->get_id();
                }

                if ( is_numeric( $product ) ) {
                    $product_id = absint( $product );
                    $product    = wc_get_product( $product_id );
                }
            }

            if ( ! $product_id || ! $product || $product->is_type( 'grouped' ) || $product->is_type( 'external' ) ) {
                return;
            }

            if ( ! $is_variation ) {
                wp_enqueue_script( 'wc-add-to-cart-variation' );
            }

            $custom_qty  = apply_filters( 'woobt_custom_qty', self::get_product_quantity( $product_id, 'view' ) === 'custom', $product_id );
            $sync_qty    = ! $custom_qty && apply_filters( 'woobt_sync_qty', self::get_product_quantity( $product_id, 'view' ) === 'sync', $product_id );
            $checked_all = apply_filters( 'woobt_checked_all', get_post_meta( $product_id, 'woobt_checked_all', true ) === 'on', $product_id );
            $separately  = apply_filters( 'woobt_separately', get_post_meta( $product_id, 'woobt_separately', true ) === 'on', $product_id );
            $separately  &= apply_filters( 'woobt_separately_reset_price', true, $product_id, 'view' ); // change it to false if you want to keep the discounted price
            $selection   = apply_filters( 'woobt_selection', get_post_meta( $product_id, 'woobt_selection', true ) ?: 'multiple', $product_id );

            $_position       = get_post_meta( $product_id, 'woobt_position', true ) ?: 'unset';
            $_layout         = get_post_meta( $product_id, 'woobt_layout', true ) ?: 'unset';
            $_atc_button     = get_post_meta( $product_id, 'woobt_atc_button', true ) ?: 'unset';
            $_show_this_item = get_post_meta( $product_id, 'woobt_show_this_item', true ) ?: 'unset';

            // settings
            $pricing          = WPCleverWoobt_Helper()->get_setting( 'pricing', 'sale_price' );
            $ignore_onsale    = WPCleverWoobt_Helper()->get_setting( 'ignore_onsale', 'no' );
            $plus_minus       = WPCleverWoobt_Helper()->get_setting( 'plus_minus', 'no' ) === 'yes';
            $position         = $_position !== 'unset' ? $_position : apply_filters( 'woobt_position', WPCleverWoobt_Helper()->get_setting( 'position', apply_filters( 'woobt_default_position', 'before' ) ) );
            $layout           = apply_filters( 'woobt_layout', $_layout !== 'unset' ? $_layout : WPCleverWoobt_Helper()->get_setting( 'layout', 'default' ), $product_id );
            $show_this_item   = apply_filters( 'woobt_show_this_item', $_show_this_item !== 'unset' ? $_show_this_item : WPCleverWoobt_Helper()->get_setting( 'show_this_item', 'yes' ), $product_id );
            $atc_button       = apply_filters( 'woobt_atc_button', $_atc_button !== 'unset' ? $_atc_button : WPCleverWoobt_Helper()->get_setting( 'atc_button', 'main' ), $product_id );
            $separate_atc     = $atc_button === 'separate' || $atc_button === 'both';
            $separate_images  = $layout === 'separate';
            $hide_this_item   = apply_filters( 'woobt_hide_this_item', ! $custom_position && ! $separate_atc && ! wc_string_to_bool( $show_this_item ), $product_id );
            $ignore_this_item = apply_filters( 'woobt_separately_ignore_this_item', false, $product_id );
            $discount         = $separately ? '0' : self::get_discount( $product_id );

            if ( ! $is_variation ) {
                $wrap_class = 'woobt-wrap woobt-layout-' . esc_attr( $layout ) . ' woobt-wrap-' . esc_attr( $product_id ) . ' ' . ( WPCleverWoobt_Helper()->get_setting( 'responsive', 'yes' ) === 'yes' ? 'woobt-wrap-responsive' : '' );

                if ( ! empty( $context ) ) {
                    $wrap_class .= ' ' . sanitize_title( 'woobt-wrap-' . $context );
                }

                if ( $custom_position ) {
                    $wrap_class .= ' woobt-wrap-custom-position';
                }

                if ( $separate_atc ) {
                    $wrap_class .= ' woobt-wrap-separate-atc';
                }

                $sku        = htmlentities( $product->get_sku() );
                $weight     = htmlentities( wc_format_weight( $product->get_weight() ) );
                $dimensions = htmlentities( wc_format_dimensions( $product->get_dimensions( false ) ) );
                $price_html = htmlentities( $product->get_price_html() );

                $wrap_attrs = apply_filters( 'woobt_wrap_data_attributes', [
                        'id'                   => $product_id,
                        'selection'            => $selection,
                        'position'             => $position,
                        'atc-button'           => $atc_button,
                        'this-item'            => $hide_this_item ? 'no' : 'yes',
                        'ignore-this'          => $ignore_this_item ? 'yes' : 'no',
                        'separately'           => $separately ? 'on' : 'off',
                        'layout'               => $layout,
                        'product-id'           => $product->is_type( 'variable' ) ? '0' : $product_id,
                        'product-sku'          => $sku,
                        'product-o_sku'        => $sku,
                        'product-weight'       => $weight,
                        'product-o_weight'     => $weight,
                        'product-dimensions'   => $dimensions,
                        'product-o_dimensions' => $dimensions,
                        'product-price-html'   => $price_html,
                        'product-o_price-html' => $price_html,
                ], $product );

                echo '<div class="' . esc_attr( $wrap_class ) . '" ' . WPCleverWoobt_Helper()->data_attributes( $wrap_attrs ) . '>';
            }

            // get items
            $items = apply_filters( 'woobt_show_items', self::get_items( $product_id, 'view' ), $product_id );

            if ( ! empty( $items ) ) {
                // format items
                foreach ( $items as $key => $item ) {
                    if ( is_array( $item ) ) {
                        if ( ! empty( $item['id'] ) ) {
                            $_item['id']    = apply_filters( 'woobt_item_id', $item['id'], $item, $product_id );
                            $_item['price'] = $item['price'];
                            $_item['qty']   = $item['qty'];
                        } else {
                            // heading/paragraph
                            $_item = $item;
                        }
                    } else {
                        // make it works with upsells/cross-sells/related
                        $_item['id']    = apply_filters( 'woobt_item_id', absint( $item ), $item, $product_id );
                        $_item['price'] = WPCleverWoobt_Helper()->get_setting( 'default_price', '100%' );
                        $_item['qty']   = 1;
                    }

                    if ( ! empty( $_item['id'] ) ) {
                        if ( $_item_product = wc_get_product( $_item['id'] ) ) {
                            $_item['product'] = $_item_product;
                        } else {
                            unset( $items[ $key ] );
                            continue;
                        }
                    }

                    if ( ! empty( $_item['product'] ) && ( ! in_array( $_item['product']->get_type(), self::$types, true ) || ( ( WPCleverWoobt_Helper()->get_setting( 'exclude_unpurchasable', 'no' ) === 'yes' ) && ( ! $_item['product']->is_purchasable() || ! $_item['product']->is_in_stock() ) ) ) ) {
                        unset( $items[ $key ] );
                        continue;
                    }

                    if ( ! empty( $_item['product'] ) && ! apply_filters( 'woobt_item_visible', $_item['product']->get_status() === 'publish', $_item ) ) {
                        unset( $items[ $key ] );
                        continue;
                    }

                    $items[ $key ] = $_item;
                }
            }

            if ( ! empty( $items ) ) {
                $before_text = apply_filters( 'woobt_before_text', self::get_text( $product, 'before' ), $product_id );
                $after_text  = apply_filters( 'woobt_after_text', self::get_text( $product, 'after' ), $product_id );

                // show items
                do_action( 'woobt_wrap_before', $product, $items );

                if ( ! empty( $before_text ) ) {
                    do_action( 'woobt_before_text_above', $product );
                    echo '<div class="woobt-before-text woobt-text">' . wp_kses_post( do_shortcode( $before_text ) ) . '</div>';
                    do_action( 'woobt_before_text_below', $product );
                }

                if ( $layout === 'compact' ) {
                    echo '<div class="woobt-inner">';
                }

                if ( $separate_images ) {
                    do_action( 'woobt_images_above', $product );
                    ?>
                    <div class="woobt-images">
                        <?php
                        do_action( 'woobt_images_before', $product );

                        if ( ! $ignore_this_item ) {
                            echo '<div class="woobt-image woobt-image-this woobt-image-order-0 woobt-image-' . esc_attr( $product_id ) . '">';
                            do_action( 'woobt_product_thumb_before', $product, 0, 'separate' );
                            echo '<span class="woobt-img woobt-img-order-0" data-img="' . esc_attr( htmlentities( $product->get_image( self::$image_size ) ) ) . '">' . $product->get_image( self::$image_size ) . '</span>';
                            do_action( 'woobt_product_thumb_after', $product, 0, 'separate' );
                            echo '</div>';
                        }

                        $order = 1;

                        foreach ( $items as $item ) {
                            if ( ! empty( $item['id'] ) ) {
                                $item_product       = $item['product'];
                                $checked_individual = apply_filters( 'woobt_checked_individual', false, $item, $product_id, $order );
                                $item_checked       = apply_filters( 'woobt_item_checked', $item_product->is_in_stock() && ( $checked_individual || ( $checked_all && ( $selection === 'multiple' ) ) || ( $checked_all && ( $selection === 'single' ) && ( $order === 1 ) ) ), $item, $product_id, $order );
                                $item_image_class   = 'woobt-image woobt-image-order-' . $order . ' woobt-image-' . $item['id'] . ' ' . ( ! $item_checked ? 'woobt-image-hide' : '' );

                                echo '<div class="' . esc_attr( $item_image_class ) . '" data-order="' . esc_attr( $order ) . '">';

                                do_action( 'woobt_product_thumb_before', $item_product, $order, 'separate', $item );

                                if ( WPCleverWoobt_Helper()->get_setting( 'link', 'yes' ) !== 'no' ) {
                                    echo '<a class="' . esc_attr( WPCleverWoobt_Helper()->get_setting( 'link', 'yes' ) === 'yes_popup' ? 'woosq-link woobt-img woobt-img-order-' . $order : 'woobt-img woobt-img-order-' . $order ) . '" data-id="' . esc_attr( $item['id'] ) . '" data-context="woobt" href="' . $item_product->get_permalink() . '" data-img="' . esc_attr( htmlentities( $item_product->get_image( self::$image_size ) ) ) . '" ' . ( WPCleverWoobt_Helper()->get_setting( 'link', 'yes' ) === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . $item_product->get_image( self::$image_size ) . '</a>';
                                } else {
                                    echo '<span class="' . esc_attr( 'woobt-img woobt-img-order-' . $order ) . '" data-img="' . esc_attr( htmlentities( $item_product->get_image( self::$image_size ) ) ) . '">' . $item_product->get_image( self::$image_size ) . '</span>';
                                }

                                do_action( 'woobt_product_thumb_after', $item_product, $order, 'separate', $item );

                                echo '</div>';
                                $order ++;
                            }
                        }

                        do_action( 'woobt_images_after', $product );
                        ?>
                    </div>
                    <?php
                    do_action( 'woobt_images_below', $product );
                }

                $products_class = apply_filters( 'woobt_products_class', 'woobt-products woobt-products-layout-' . $layout . ' woobt-products-' . $product_id, $product );
                $products_attrs = apply_filters( 'woobt_products_data_attributes', [
                        'show-price'           => WPCleverWoobt_Helper()->get_setting( 'show_price', 'yes' ),
                        'optional'             => $custom_qty ? 'on' : 'off',
                        'separately'           => $separately ? 'on' : 'off',
                        'sync-qty'             => $sync_qty ? 'on' : 'off',
                        'variables'            => self::has_variables( $items ) ? 'yes' : 'no',
                        'product-id'           => $product->is_type( 'variable' ) ? '0' : $product_id,
                        'product-type'         => $product->get_type(),
                        'product-price-suffix' => htmlentities( $product->get_price_suffix() ),
                        'pricing'              => $pricing,
                        'discount'             => $discount,
                ], $product );

                do_action( 'woobt_products_above', $product, $items );
                ?>
                <div class="<?php echo esc_attr( $products_class ); ?>" <?php echo WPCleverWoobt_Helper()->data_attributes( $products_attrs ); ?>>
                    <?php
                    do_action( 'woobt_products_before', $product );

                    if ( ! $ignore_this_item ) {
                        // this item
                        $this_item_product    = apply_filters( 'woobt_this_item_product', $product );
                        $this_item_product_id = apply_filters( 'woobt_this_item_product_id', $this_item_product->get_id() );
                        $this_item_price      = ! $separately && ( $discount = get_post_meta( $this_item_product_id, 'woobt_discount', true ) ) ? ( 100 - (float) $discount ) . '%' : '100%';

                        if ( in_array( $ignore_onsale, [ 'main', 'both' ] ) && $this_item_product->is_on_sale() ) {
                            $this_item_price = '100%';
                        }

                        $this_item_price    = apply_filters( 'woobt_this_item_price', $this_item_price, [
                                'product' => $this_item_product,
                                'id'      => $this_item_product_id
                        ], $product_id );
                        $this_item_quantity = apply_filters( 'woobt_this_item_quantity', false, $this_item_product );
                        $this_item_name     = apply_filters( 'woobt_product_get_name', $this_item_product->get_name(), $this_item_product );
                        $this_item_attrs    = apply_filters( 'woobt_this_item_data_attributes', [
                                'order'         => 0,
                                'qty'           => 1,
                                'o_qty'         => 1,
                                'id'            => $this_item_product->is_type( 'variable' ) || ! $this_item_product->is_in_stock() ? 0 : $this_item_product_id,
                                'pid'           => $this_item_product_id,
                                'name'          => $this_item_name,
                                'price'         => apply_filters( 'woobt_item_data_price', wc_get_price_to_display( $this_item_product ), $this_item_product, $product ),
                                'regular-price' => apply_filters( 'woobt_item_data_regular_price', wc_get_price_to_display( $this_item_product, [ 'price' => $this_item_product->get_regular_price() ] ), $this_item_product, $product ),
                                'new-price'     => apply_filters( 'woobt_item_data_new_price', $this_item_price, $this_item_product, $product ),
                                'price-suffix'  => htmlentities( $this_item_product->get_price_suffix() )
                        ], $this_item_product, $product );

                        ob_start();

                        if ( $hide_this_item ) {
                            ?>
                            <div class="woobt-product woobt-product-this woobt-hide-this" <?php echo WPCleverWoobt_Helper()->data_attributes( $this_item_attrs ); ?>>
                                <div class="woobt-choose">
                                    <label for="woobt_checkbox_0"><?php echo esc_html( $this_item_name ); ?></label>
                                    <input id="woobt_checkbox_0" class="woobt-checkbox woobt-checkbox-this"
                                           type="checkbox" checked disabled/>
                                    <span class="checkmark"></span>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="woobt-product woobt-product-this" <?php echo WPCleverWoobt_Helper()->data_attributes( $this_item_attrs ); ?>>

                                <?php do_action( 'woobt_product_before', $this_item_product, 0 ); ?>

                                <div class="woobt-choose">
                                    <label for="woobt_checkbox_0"><?php echo esc_html( $this_item_name ); ?></label>
                                    <input id="woobt_checkbox_0" class="woobt-checkbox woobt-checkbox-this"
                                           type="checkbox" checked disabled/>
                                    <span class="checkmark"></span>
                                </div>

                                <?php if ( ! $separate_images && ( WPCleverWoobt_Helper()->get_setting( 'show_thumb', 'yes' ) !== 'no' ) ) {
                                    echo '<div class="woobt-thumb">';
                                    do_action( 'woobt_product_thumb_before', $this_item_product, 0, 'default' );
                                    echo '<span class="woobt-img woobt-img-order-0" data-img="' . esc_attr( htmlentities( $this_item_product->get_image( self::$image_size ) ) ) . '">' . $this_item_product->get_image( self::$image_size ) . '</span>';
                                    do_action( 'woobt_product_thumb_after', $this_item_product, 0, 'default' );
                                    echo '</div>';
                                } ?>

                                <div class="woobt-title">
                                <span class="woobt-title-inner">
                                    <?php echo apply_filters( 'woobt_product_this_name', '<span>' . WPCleverWoobt_Helper()->localization( 'this_item', esc_html__( 'This item:', 'woo-bought-together' ) ) . '</span> <span>' . apply_filters( 'woobt_product_get_name', $this_item_product->get_name(), $this_item_product ) . '</span>', $this_item_product ); ?>
                                </span>

                                    <?php if ( $separate_images && ( WPCleverWoobt_Helper()->get_setting( 'show_price', 'yes' ) !== 'no' ) ) { ?>
                                        <span class="woobt-price">
                                        <span class="woobt-price-new">
                                            <?php
                                            if ( ! $separately && ( $discount = get_post_meta( $this_item_product_id, 'woobt_discount', true ) ) ) {
                                                $sale_price = $this_item_product->get_price() * ( 100 - (float) $discount ) / 100;
                                                echo wc_format_sale_price( $this_item_product->get_price(), $sale_price ) . $this_item_product->get_price_suffix( $sale_price );
                                            } else {
                                                echo $this_item_product->get_price_html();
                                            }
                                            ?>
                                        </span>
                                        <span class="woobt-price-ori">
                                            <?php echo $this_item_product->get_price_html(); ?>
                                        </span>
                                    </span>
                                    <?php }

                                    if ( ( $separate_atc || $custom_position ) && $this_item_product->is_type( 'variable' ) ) {
                                        // this item's variations
                                        if ( ( WPCleverWoobt_Helper()->get_setting( 'variations_selector', 'default' ) === 'woovr' ) && class_exists( 'WPClever_Woovr' ) ) {
                                            echo '<div class="wpc_variations_form">';
                                            // use class name wpc_variations_form to prevent found_variation in woovr
                                            WPClever_Woovr::woovr_variations_form( $this_item_product, false, 'woobt' );
                                            echo '</div>';
                                        } else {
                                            $attributes           = $this_item_product->get_variation_attributes();
                                            $available_variations = $this_item_product->get_available_variations();

                                            if ( is_array( $attributes ) && ( count( $attributes ) > 0 ) ) {
                                                echo '<div class="variations_form woobt_variations_form" action="' . esc_url( $this_item_product->get_permalink() ) . '" data-product_id="' . absint( $this_item_product_id ) . '" data-product_variations="' . htmlspecialchars( wp_json_encode( $available_variations ) ) . '">';

                                                if ( apply_filters( 'woobt_variations_table_layout', false ) ) {
                                                    echo '<table class="variations" cellspacing="0" role="presentation"><tbody>';

                                                    foreach ( $attributes as $attribute_name => $options ) {
                                                        $attribute_name_sz = sanitize_title( $attribute_name );
                                                        ?>
                                                        <tr class="<?php echo esc_attr( 'variation variation-' . $attribute_name_sz ); ?>">
                                                            <th class="label">
                                                                <label for="<?php echo esc_attr( $attribute_name_sz ); ?>"><?php echo esc_html( wc_attribute_label( $attribute_name ) ); ?></label>
                                                            </th>
                                                            <td class="value">
                                                                <?php
                                                                $selected = isset( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ? wc_clean( wp_unslash( urldecode( wp_unslash( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ) ) ) : $this_item_product->get_variation_default_attribute( $attribute_name );
                                                                wc_dropdown_variation_attribute_options( [
                                                                        'options'          => $options,
                                                                        'attribute'        => $attribute_name,
                                                                        'product'          => $this_item_product,
                                                                        'selected'         => $selected,
                                                                        'show_option_none' => sprintf( WPCleverWoobt_Helper()->localization( 'choose', /* translators: attribute name */ esc_html__( 'Choose %s', 'woo-bought-together' ) ), wc_attribute_label( $attribute_name ) )
                                                                ] );
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php }

                                                    echo '</tbody></table><!-- /.variations -->';
                                                } else {
                                                    echo '<div class="variations">';

                                                    foreach ( $attributes as $attribute_name => $options ) {
                                                        $attribute_name_sz = sanitize_title( $attribute_name );
                                                        ?>
                                                        <div class="<?php echo esc_attr( 'variation variation-' . $attribute_name_sz ); ?>">
                                                            <div class="label">
                                                                <label for="<?php echo esc_attr( $attribute_name_sz ); ?>"><?php echo esc_html( wc_attribute_label( $attribute_name ) ); ?></label>
                                                            </div>
                                                            <div class="value">
                                                                <?php
                                                                $selected = isset( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ? wc_clean( wp_unslash( urldecode( wp_unslash( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ) ) ) : $this_item_product->get_variation_default_attribute( $attribute_name );
                                                                wc_dropdown_variation_attribute_options( [
                                                                        'options'          => $options,
                                                                        'attribute'        => $attribute_name,
                                                                        'product'          => $this_item_product,
                                                                        'selected'         => $selected,
                                                                        'show_option_none' => sprintf( WPCleverWoobt_Helper()->localization( 'choose', /* translators: attribute name */ esc_html__( 'Choose %s', 'woo-bought-together' ) ), wc_attribute_label( $attribute_name ) )
                                                                ] );
                                                                ?>
                                                            </div>
                                                        </div>
                                                    <?php }

                                                    echo '</div><!-- /.variations -->';
                                                }

                                                echo '<div class="reset">' . apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations" href="#">' . WPCleverWoobt_Helper()->localization( 'clear', esc_html__( 'Clear', 'woo-bought-together' ) ) . '</a>' ) . '</div>';
                                                echo '</div><!-- /.variations_form -->';

                                                if ( WPCleverWoobt_Helper()->get_setting( 'show_description', 'no' ) === 'yes' ) {
                                                    echo '<div class="woobt-variation-description"></div>';
                                                }
                                            }
                                        }
                                    }

                                    echo '<div class="woobt-availability">' . apply_filters( 'woobt_product_availability', ! $this_item_product->is_type( 'variable' ) ? wc_get_stock_html( $this_item_product ) : '', $this_item_product ) . '</div>';
                                    ?>
                                </div>

                                <?php if ( ( $separate_atc || $custom_position || $this_item_quantity ) && $custom_qty ) {
                                    echo '<div class="' . esc_attr( ( $plus_minus ? 'woobt-quantity woobt-quantity-plus-minus' : 'woobt-quantity' ) ) . '">';

                                    if ( $plus_minus ) {
                                        echo '<div class="woobt-quantity-input">';
                                        echo '<div class="woobt-quantity-input-minus">-</div>';
                                    }

                                    $qty_args = [
                                            'classes'    => [
                                                    'input-text',
                                                    'woobt-qty',
                                                    'woobt_qty',
                                                    'woobt-this-qty',
                                                    'qty',
                                                    'text'
                                            ],
                                            'input_name' => 'woobt_qty_0',
                                            'min_value'  => $this_item_product->get_min_purchase_quantity(),
                                            'max_value'  => $this_item_product->get_max_purchase_quantity(),
                                    ];

                                    if ( apply_filters( 'woobt_use_woocommerce_quantity_input', true ) ) {
                                        woocommerce_quantity_input( $qty_args, $this_item_product );
                                    } else {
                                        echo apply_filters( 'woobt_quantity_input', '<input type="number" name="woobt_qty_0" class="woobt-qty woobt-this-qty woobt_qty input-text qty text" value="1" min="' . esc_attr( $this_item_product->get_min_purchase_quantity() ) . '" max="' . esc_attr( $this_item_product->get_max_purchase_quantity() ) . '" />', $qty_args, $this_item_product );
                                    }

                                    if ( $plus_minus ) {
                                        echo '<div class="woobt-quantity-input-plus">+</div>';
                                        echo '</div>';
                                    }

                                    echo '</div>';
                                }

                                if ( ! $separate_images && ( WPCleverWoobt_Helper()->get_setting( 'show_price', 'yes' ) !== 'no' ) ) { ?>
                                    <div class="woobt-price">
                                        <div class="woobt-price-new">
                                            <?php
                                            if ( ! $separately && ( $discount = get_post_meta( $this_item_product_id, 'woobt_discount', true ) ) ) {
                                                $sale_price = $this_item_product->get_price() * ( 100 - (float) $discount ) / 100;
                                                echo wc_format_sale_price( $this_item_product->get_price(), $sale_price ) . $this_item_product->get_price_suffix( $sale_price );
                                            } else {
                                                echo $this_item_product->get_price_html();
                                            }
                                            ?>
                                        </div>
                                        <div class="woobt-price-ori">
                                            <?php echo $this_item_product->get_price_html(); ?>
                                        </div>
                                    </div>
                                <?php }

                                do_action( 'woobt_product_after', $this_item_product, 0 );
                                ?>
                            </div><!-- /.woobt-product-this -->
                            <?php
                        }

                        echo apply_filters( 'woobt_product_this_output', ob_get_clean(), $this_item_product, $custom_position );
                    }

                    // other items
                    $order = 1;

                    // store global $product
                    $global_product = $product;

                    foreach ( $items as $item_key => $item ) {
                        if ( ! empty( $item['id'] ) ) {
                            $item['key'] = $item_key;
                            $product     = $item['product'];
                            $item_id     = $item['id'];
                            $item_price  = $item['price'];
                            $item_qty    = $item['qty'];
                            $item_min    = 1;
                            $item_max    = 1000;

                            if ( $custom_qty ) {
                                if ( get_post_meta( $product_id, 'woobt_limit_each_min_default', true ) === 'on' ) {
                                    $item_min = $item_qty;
                                } else {
                                    $item_min = absint( get_post_meta( $product_id, 'woobt_limit_each_min', true ) ?: 0 );
                                }

                                $item_min = absint( apply_filters( 'woobt_limit_each_min', $item_min, $item, $product_id ) );
                                $item_max = absint( apply_filters( 'woobt_limit_each_max', get_post_meta( $product_id, 'woobt_limit_each_max', true ) ?: 1000, $item, $product_id ) );

                                if ( ( $max_purchase = $product->get_max_purchase_quantity() ) && ( $max_purchase > 0 ) && ( $max_purchase < $item_max ) ) {
                                    // get_max_purchase_quantity can return -1
                                    $item_max = $max_purchase;
                                }

                                if ( $item_qty < $item_min ) {
                                    $item_qty = $item_min;
                                }

                                if ( ( $item_max > $item_min ) && ( $item_qty > $item_max ) ) {
                                    $item_qty = $item_max;
                                }
                            }

                            if ( in_array( $ignore_onsale, [
                                            'fbt',
                                            'both'
                                    ] ) && ( $pricing === 'sale_price' ) && $product->is_on_sale() ) {
                                $item_price = '100%';
                            }

                            $item_price         = apply_filters( 'woobt_item_price', ! $separately ? $item_price : '100%', $item, $product_id );
                            $item_name          = apply_filters( 'woobt_product_get_name', $product->get_name(), $product );
                            $checked_individual = apply_filters( 'woobt_checked_individual', false, $item, $product_id, $order );
                            $item_checked       = apply_filters( 'woobt_item_checked', $product->is_in_stock() && ( $checked_individual || ( $checked_all && ( $selection === 'multiple' ) ) || ( $checked_all && ( $selection === 'single' ) && ( $order === 1 ) ) ), $item, $product_id, $order );
                            $item_disabled      = apply_filters( 'woobt_item_disabled', ! $product->is_in_stock(), $item, $product_id, $order );
                            $item_attrs         = apply_filters( 'woobt_item_data_attributes', [
                                    'key'           => $item_key,
                                    'order'         => $order,
                                    'id'            => $product->is_type( 'variable' ) || ! $product->is_in_stock() ? 0 : $item_id,
                                    'pid'           => $item_id,
                                    'name'          => $item_name,
                                    'new-price'     => apply_filters( 'woobt_item_data_new_price', $item_price, $product, $global_product ),
                                    'price-suffix'  => htmlentities( $product->get_price_suffix() ),
                                    'price'         => apply_filters( 'woobt_item_data_price', ( $pricing === 'sale_price' ) ? wc_get_price_to_display( $product ) : wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ), $product ),
                                    'regular-price' => apply_filters( 'woobt_item_data_regular_price', wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ), $product ),
                                    'qty'           => $item_qty,
                                    'o_qty'         => $item_qty,
                            ], $item, $product_id, $order );

                            ob_start();
                            ?>
                            <div class="woobt-product woobt-product-together" <?php echo WPCleverWoobt_Helper()->data_attributes( $item_attrs ); ?>>

                                <?php
                                do_action( 'woobt_item_before', $item, $global_product, $order );
                                do_action( 'woobt_product_before', $product, $order );
                                ?>

                                <div class="woobt-choose">
                                    <label for="<?php echo esc_attr( 'woobt_checkbox_' . $order ); ?>"><?php echo esc_html( $item_name ); ?></label>
                                    <input id="<?php echo esc_attr( 'woobt_checkbox_' . $order ); ?>"
                                           class="woobt-checkbox" type="checkbox"
                                           value="<?php echo esc_attr( $item_id ); ?>" <?php echo esc_attr( $item_disabled ? 'disabled' : '' ); ?> <?php echo esc_attr( $item_checked ? 'checked' : '' ); ?>/>
                                    <span class="checkmark"></span>
                                </div>

                                <?php if ( ! $separate_images && ( WPCleverWoobt_Helper()->get_setting( 'show_thumb', 'yes' ) !== 'no' ) ) {
                                    echo '<div class="woobt-thumb">';

                                    do_action( 'woobt_product_thumb_before', $product, $order, 'default', $item );

                                    if ( WPCleverWoobt_Helper()->get_setting( 'link', 'yes' ) !== 'no' ) {
                                        echo '<a class="' . esc_attr( WPCleverWoobt_Helper()->get_setting( 'link', 'yes' ) === 'yes_popup' ? 'woosq-link woobt-img woobt-img-order-' . $order : 'woobt-img woobt-img-order-' . $order ) . '" data-id="' . esc_attr( $item_id ) . '" data-context="woobt" href="' . $product->get_permalink() . '" data-img="' . esc_attr( htmlentities( $product->get_image( self::$image_size ) ) ) . '" ' . ( WPCleverWoobt_Helper()->get_setting( 'link', 'yes' ) === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . $product->get_image( self::$image_size ) . '</a>';
                                    } else {
                                        echo '<span class="' . esc_attr( 'woobt-img woobt-img-order-' . $order ) . '" data-img="' . esc_attr( htmlentities( $product->get_image( self::$image_size ) ) ) . '">' . $product->get_image( self::$image_size ) . '</span>';
                                    }

                                    do_action( 'woobt_product_thumb_after', $product, $order, 'default', $item );

                                    echo '</div>';
                                } ?>

                                <div class="woobt-title">
                                    <?php
                                    do_action( 'woobt_item_title_before', $item, $global_product, $order );

                                    echo '<span class="woobt-title-inner">';

                                    do_action( 'woobt_product_name_before', $product, $order );

                                    if ( ! $custom_qty ) {
                                        $product_qty = '<span class="woobt-qty-num"><span class="woobt-qty">' . $item_qty . '</span> × </span>';
                                    } else {
                                        $product_qty = '';
                                    }

                                    echo apply_filters( 'woobt_product_qty', $product_qty, $item_qty, $product );

                                    if ( $product->is_in_stock() ) {
                                        $product_name = apply_filters( 'woobt_product_get_name', $product->get_name(), $product );
                                    } else {
                                        $product_name = '<s>' . apply_filters( 'woobt_product_get_name', $product->get_name(), $product ) . '</s>';
                                    }

                                    if ( WPCleverWoobt_Helper()->get_setting( 'link', 'yes' ) !== 'no' ) {
                                        $product_name = '<a ' . ( WPCleverWoobt_Helper()->get_setting( 'link', 'yes' ) === 'yes_popup' ? 'class="woosq-link" data-id="' . $item_id . '" data-context="woobt"' : '' ) . ' href="' . $product->get_permalink() . '" ' . ( WPCleverWoobt_Helper()->get_setting( 'link', 'yes' ) === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . $product_name . '</a>';
                                    } else {
                                        $product_name = '<span>' . $product_name . '</span>';
                                    }

                                    echo apply_filters( 'woobt_product_name', $product_name, $product );

                                    do_action( 'woobt_product_name_after', $product, $order );

                                    echo '</span>';

                                    if ( $separate_images && ( WPCleverWoobt_Helper()->get_setting( 'show_price', 'yes' ) !== 'no' ) ) {
                                        echo '<span class="woobt-price">';

                                        do_action( 'woobt_product_price_before', $product, $order );

                                        echo '<span class="woobt-price-new"></span>';
                                        echo '<span class="woobt-price-ori">';

                                        if ( ! $separately && ( $item_price !== '100%' ) ) {
                                            if ( $product->is_type( 'variable' ) ) {
                                                $item_ori_price_min = apply_filters( 'woobt_product_price_ori', ( $pricing === 'sale_price' ) ? $product->get_variation_price( 'min', true ) : $product->get_variation_regular_price( 'min', true ), $item, 'min' );
                                                $item_ori_price_max = apply_filters( 'woobt_product_price_ori', ( $pricing === 'sale_price' ) ? $product->get_variation_price( 'max', true ) : $product->get_variation_regular_price( 'max', true ), $item, 'max' );
                                                $item_new_price_min = WPCleverWoobt_Helper()->new_price( $item_ori_price_min, $item_price );
                                                $item_new_price_max = WPCleverWoobt_Helper()->new_price( $item_ori_price_max, $item_price );

                                                if ( $item_new_price_min < $item_new_price_max ) {
                                                    $product_price = wc_format_price_range( $item_new_price_min, $item_new_price_max );
                                                } else {
                                                    $product_price = wc_format_sale_price( $item_ori_price_min, $item_new_price_min );
                                                }
                                            } else {
                                                $item_ori_price = apply_filters( 'woobt_product_price_ori', ( $pricing === 'sale_price' ) ? wc_get_price_to_display( $product, [ 'price' => $product->get_price() ] ) : wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ), $item );
                                                $item_new_price = WPCleverWoobt_Helper()->new_price( $item_ori_price, $item_price );

                                                if ( $item_new_price < $item_ori_price ) {
                                                    $product_price = wc_format_sale_price( $item_ori_price, $item_new_price );
                                                } else {
                                                    $product_price = wc_price( $item_new_price );
                                                }
                                            }

                                            $product_price .= $product->get_price_suffix();
                                        } else {
                                            $product_price = $product->get_price_html();
                                        }

                                        echo apply_filters( 'woobt_product_price', $product_price, $product, $item );

                                        echo '</span>';

                                        do_action( 'woobt_product_price_after', $product, $order );

                                        echo '</span>';
                                    }

                                    if ( WPCleverWoobt_Helper()->get_setting( 'show_description', 'no' ) === 'yes' ) {
                                        echo '<div class="woobt-description">' . apply_filters( 'woobt_product_short_description', $product->is_type( 'variation' ) ? $product->get_description() : $product->get_short_description(), $product ) . '</div>';
                                    }

                                    echo '<div class="woobt-availability">' . apply_filters( 'woobt_product_availability', ! $product->is_type( 'variable' ) ? wc_get_stock_html( $product ) : '', $product ) . '</div>';

                                    do_action( 'woobt_item_title_after', $item, $global_product, $order );
                                    ?>
                                </div>

                                <?php
                                if ( $custom_qty ) {
                                    echo '<div class="' . esc_attr( ( $plus_minus ? 'woobt-quantity woobt-quantity-plus-minus' : 'woobt-quantity' ) ) . '">';

                                    do_action( 'woobt_product_qty_before', $product, $order );

                                    if ( $plus_minus ) {
                                        echo '<div class="woobt-quantity-input">';
                                        echo '<div class="woobt-quantity-input-minus">-</div>';
                                    }

                                    $qty_args = [
                                            'classes'     => [
                                                    'input-text',
                                                    'woobt-qty',
                                                    'woobt_qty',
                                                    'qty',
                                                    'text'
                                            ],
                                            'input_name'  => 'woobt_qty_' . $order,
                                            'input_value' => $item_qty,
                                            'min_value'   => $item_min,
                                            'max_value'   => $item_max,
                                            'woobt_qty'   => [
                                                    'input_value' => $item_qty,
                                                    'min_value'   => $item_min,
                                                    'max_value'   => $item_max
                                            ]
                                        // compatible with WPC Product Quantity
                                    ];

                                    if ( apply_filters( 'woobt_use_woocommerce_quantity_input', true ) ) {
                                        woocommerce_quantity_input( $qty_args, $product );
                                    } else {
                                        echo apply_filters( 'woobt_quantity_input', '<input type="number" class="input-text woobt-qty woobt_qty qty text" name="' . esc_attr( 'woobt_qty_' . $order ) . '" value="' . esc_attr( $item_qty ) . '" min="' . esc_attr( $item_min ) . '" max="' . esc_attr( $item_max ) . '" />', $qty_args, $product );
                                    }

                                    if ( $plus_minus ) {
                                        echo '<div class="woobt-quantity-input-plus">+</div>';
                                        echo '</div>';
                                    }

                                    do_action( 'woobt_product_qty_after', $product, $order );

                                    echo '</div>';
                                }

                                if ( ! $separate_images && ( WPCleverWoobt_Helper()->get_setting( 'show_price', 'yes' ) !== 'no' ) ) {
                                    echo '<div class="woobt-price">';

                                    do_action( 'woobt_product_price_before', $product, $order );

                                    echo '<div class="woobt-price-new"></div>';
                                    echo '<div class="woobt-price-ori">';

                                    if ( ! $separately && ( $item_price !== '100%' ) ) {
                                        if ( $product->is_type( 'variable' ) ) {
                                            $item_ori_price_min = apply_filters( 'woobt_product_price_ori', ( $pricing === 'sale_price' ) ? $product->get_variation_price( 'min', true ) : $product->get_variation_regular_price( 'min', true ), $item, 'min' );
                                            $item_ori_price_max = apply_filters( 'woobt_product_price_ori', ( $pricing === 'sale_price' ) ? $product->get_variation_price( 'max', true ) : $product->get_variation_regular_price( 'max', true ), $item, 'max' );
                                            $item_new_price_min = WPCleverWoobt_Helper()->new_price( $item_ori_price_min, $item_price );
                                            $item_new_price_max = WPCleverWoobt_Helper()->new_price( $item_ori_price_max, $item_price );

                                            if ( $item_new_price_min < $item_new_price_max ) {
                                                $product_price = wc_format_price_range( $item_new_price_min, $item_new_price_max );
                                            } else {
                                                $product_price = wc_format_sale_price( $item_ori_price_min, $item_new_price_min );
                                            }
                                        } else {
                                            $item_ori_price = apply_filters( 'woobt_product_price_ori', ( $pricing === 'sale_price' ) ? wc_get_price_to_display( $product, [ 'price' => $product->get_price() ] ) : wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] ), $item );
                                            $item_new_price = WPCleverWoobt_Helper()->new_price( $item_ori_price, $item_price );

                                            if ( $item_new_price < $item_ori_price ) {
                                                $product_price = wc_format_sale_price( $item_ori_price, $item_new_price );
                                            } else {
                                                $product_price = wc_price( $item_new_price );
                                            }
                                        }

                                        $product_price .= $product->get_price_suffix();
                                    } else {
                                        $product_price = $product->get_price_html();
                                    }

                                    echo apply_filters( 'woobt_product_price', $product_price, $product, $item );

                                    echo '</div>';

                                    do_action( 'woobt_product_price_after', $product, $order );

                                    echo '</div><!-- /.woobt-price -->';
                                }
                                ?>

                                <?php
                                do_action( 'woobt_product_after', $product, $order );
                                do_action( 'woobt_item_after', $item, $global_product, $order );
                                ?>

                            </div><!-- /.woobt-product-together -->

                            <?php echo apply_filters( 'woobt_product_output', ob_get_clean(), $item, $product_id, $order );

                            $order ++;
                        } else {
                            // heading/paragraph
                            echo self::text_output( $item, $item_key, $product_id );
                        }
                    }

                    // restore global $product
                    $product = $global_product;

                    do_action( 'woobt_products_after', $product );
                    ?>
                </div><!-- /woobt-products -->
                <?php
                do_action( 'woobt_products_below', $product, $items );

                do_action( 'woobt_summary_above', $product, $items );

                echo '<div class="woobt-summary">';

                echo '<div class="woobt-additional woobt-text"></div>';

                do_action( 'woobt_total_above', $product, $items );

                echo '<div class="woobt-total woobt-text"></div>';

                do_action( 'woobt_alert_above', $product, $items );

                echo '<div class="woobt-alert woobt-text"></div>';

                if ( $custom_position || $separate_atc ) {
                    do_action( 'woobt_actions_above', $product );
                    echo '<div class="woobt-actions">';
                    do_action( 'woobt_actions_before', $product );
                    echo '<div class="woobt-form">';
                    echo '<input type="hidden" name="woobt_ids" class="woobt-ids woobt-ids-' . esc_attr( $product_id ) . '" data-id="' . esc_attr( $product_id ) . '"/>';
                    echo '<input type="hidden" name="quantity" value="1"/>';
                    echo '<input type="hidden" name="product_id" value="' . esc_attr( $product_id ) . '">';
                    echo '<input type="hidden" name="variation_id" class="variation_id" value="' . esc_attr( apply_filters( 'woobt_variation_id', 0, $product ) ) . '">';
                    echo '<button type="submit" class="single_add_to_cart_button button alt">' . WPCleverWoobt_Helper()->localization( 'add_all_to_cart', esc_html__( 'Add all to cart', 'woo-bought-together' ) ) . '</button>';
                    echo '</div>';
                    do_action( 'woobt_actions_after', $product );
                    echo '</div><!-- /woobt-actions -->';
                    do_action( 'woobt_actions_below', $product );
                }

                echo '</div><!-- /woobt-summary -->';

                do_action( 'woobt_summary_below', $product, $items );

                if ( $layout === 'compact' ) {
                    echo '</div><!-- /woobt-inner -->';
                }

                if ( ! empty( $after_text ) ) {
                    do_action( 'woobt_after_text_above', $product );
                    echo '<div class="woobt-after-text woobt-text">' . wp_kses_post( do_shortcode( $after_text ) ) . '</div>';
                    do_action( 'woobt_after_text_below', $product );
                }

                do_action( 'woobt_wrap_after', $product, $items );
            }

            if ( ! $is_variation ) {
                echo '</div><!-- /woobt-wrap -->';
            }
        }

        function text_output( $item, $item_key = '', $product_id = 0 ) {
            ob_start();

            if ( ! empty( $item['text'] ) ) {
                $item_class = 'woobt-item-text';

                if ( ! empty( $item['type'] ) ) {
                    $item_class .= ' woobt-item-text-type-' . $item['type'];
                }

                echo '<div class="' . esc_attr( apply_filters( 'woobt_item_text_class', $item_class, $item, $product_id ) ) . '" data-key="' . esc_attr( $item_key ) . '">';

                $item_text = apply_filters( 'woobt_item_text', do_shortcode( str_replace( '[woobt', '[_woobt', $item['text'] ) ), $item, $product_id );

                if ( empty( $item['type'] ) || ( $item['type'] === 'none' ) ) {
                    echo wp_kses_post( $item_text );
                } else {
                    echo '<' . $item['type'] . '>' . wp_kses_post( $item_text ) . '</' . $item['type'] . '>';
                }

                echo '</div>';
            }

            return apply_filters( 'woobt_text_output', ob_get_clean(), $item, $product_id );
        }

        function get_ids( $product, $context = 'display' ) {
            if ( is_a( $product, 'WC_Product' ) ) {
                $product_id = $product->get_id();
            } elseif ( is_int( $product ) ) {
                $product_id = $product;
            } else {
                $product_id = 0;
            }

            $ids = [];

            if ( $product_id && ! self::is_disable( $product_id ) ) {
                $ids = get_post_meta( $product_id, 'woobt_ids', true );
            }

            return apply_filters( 'woobt_get_ids', $ids, $product, $context );
        }

        function get_product_items( $product, $context = 'view' ) {
            if ( is_a( $product, 'WC_Product' ) ) {
                $product_id = $product->get_id();
            } elseif ( is_int( $product ) ) {
                $product_id = $product;
            } else {
                $product_id = 0;
            }

            if ( ! $product_id ) {
                return apply_filters( 'woobt_get_product_items', [], $product, $context );
            }

            static $cache = [];
            $cache_key     = $product_id . '_' . $context;
            $transient_key = 'woobt_items_' . $cache_key;

            // Skip cache for edit context to always get fresh data
            if ( $context !== 'edit' ) {
                if ( isset( $cache[ $cache_key ] ) ) {
                    return apply_filters( 'woobt_get_product_items', $cache[ $cache_key ], $product, $context );
                }

                $items = wp_cache_get( $transient_key, 'woobt' );

                if ( false !== $items ) {
                    $cache[ $cache_key ] = $items;

                    return apply_filters( 'woobt_get_product_items', $items, $product, $context );
                }
            }

            $items = [];

            if ( ! self::is_disable( $product_id ) ) {
                $ids = self::get_ids( $product_id, $context );

                if ( ! empty( $ids ) && is_array( $ids ) ) {
                    foreach ( $ids as $item_key => $item ) {
                        $item = array_merge( [
                                'id'    => 0,
                                'sku'   => '',
                                'price' => '100%',
                                'qty'   => 1,
                                'attrs' => []
                        ], $item );

                        // check for variation
                        if ( ( $parent_id = wp_get_post_parent_id( $item['id'] ) ) && ( $parent = wc_get_product( $parent_id ) ) ) {
                            $parent_sku = $parent->get_sku();
                        } else {
                            $parent_sku = '';
                        }

                        if ( apply_filters( 'woobt_use_sku', false ) && ! empty( $item['sku'] ) && ( $item['sku'] !== $parent_sku ) && ( $new_id = wc_get_product_id_by_sku( $item['sku'] ) ) ) {
                            // get product id by SKU for export/import
                            $item['id'] = $new_id;
                        }

                        $items[ $item_key ] = $item;
                    }
                }
            }

            // Only cache for non-edit contexts
            if ( $context !== 'edit' ) {
                wp_cache_set( $transient_key, $items, 'woobt', 900 );
                $cache[ $cache_key ] = $items;
            }

            return apply_filters( 'woobt_get_product_items', $items, $product, $context );
        }

        function get_rule( $product ) {
            return [];
        }

        function get_rules( $product ) {
            return [];
        }

        function get_rule_items( $product = null, $context = 'view' ) {
            return [];
        }

        function get_default_items( $product = null, $context = 'view' ) {
            if ( is_a( $product, 'WC_Product' ) ) {
                $product_id = $product->get_id();
            } elseif ( is_int( $product ) ) {
                $product_id = $product;
                $product    = wc_get_product( $product_id );
            } else {
                $product_id = 0;
            }

            $ids   = [];
            $items = [];

            if ( $product_id && ! self::is_disable( $product_id ) ) {
                $default       = apply_filters( 'woobt_default', WPCleverWoobt_Helper()->get_setting( 'default', [ 'default' ] ) );
                $default_limit = (int) apply_filters( 'woobt_default_limit', WPCleverWoobt_Helper()->get_setting( 'default_limit', 0 ) );
                $default_price = apply_filters( 'woobt_default_price', WPCleverWoobt_Helper()->get_setting( 'default_price', '100%' ) );

                // backward compatibility before 5.1.1
                if ( ! is_array( $default ) ) {
                    switch ( (string) $default ) {
                        case 'upsells':
                            $default = [ 'upsells' ];
                            break;
                        case 'related':
                            $default = [ 'related' ];
                            break;
                        case 'related_upsells':
                            $default = [ 'upsells', 'related' ];
                            break;
                        case 'none':
                            $default = [];
                            break;
                        default:
                            $default = [];
                    }
                }

                if ( is_array( $default ) && ! empty( $default ) ) {
                    if ( in_array( 'related', $default ) ) {
                        $ids = array_merge( $ids, wc_get_related_products( $product_id ) );
                    }

                    if ( in_array( 'upsells', $default ) ) {
                        $ids = array_merge( $ids, $product->get_upsell_ids() );
                    }

                    if ( in_array( 'crosssells', $default ) ) {
                        $ids = array_merge( $ids, $product->get_cross_sell_ids() );
                    }

                    if ( $default_limit ) {
                        $ids = array_slice( $ids, 0, $default_limit );
                    }
                }

                if ( ! empty( $ids ) ) {
                    foreach ( $ids as $id ) {
                        $item_key           = 'df' . WPCleverWoobt_Helper()->generate_key();
                        $items[ $item_key ] = [
                                'id'    => $id,
                                'price' => $default_price,
                                'qty'   => 1,
                        ];
                    }
                }
            }

            return apply_filters( 'woobt_get_default_items', $items, $product, $context );
        }

        function get_items( $product, $context = 'view' ) {
            if ( is_a( $product, 'WC_Product' ) ) {
                $product_id = $product->get_id();
            } elseif ( is_int( $product ) ) {
                $product_id = $product;
            } else {
                $product_id = 0;
            }

            $items = [];

            if ( $product_id && ! self::is_disable( $product_id ) ) {
                $priority = apply_filters( 'woobt_get_items_priority', [
                        'product',
                        'rule',
                        'default'
                ], $product_id );

                foreach ( $priority as $pr ) {
                    switch ( $pr ) {
                        case 'product':
                            $items = self::get_product_items( $product_id, $context );
                            break;
                        case 'rule':
                            $items = self::get_rule_items( $product_id, $context );
                            break;
                        case 'default':
                            $items = self::get_default_items( $product, $context );
                            break;
                        case 'combine':
                            $product_items = self::get_product_items( $product_id, $context );
                            $rule_items    = self::get_rule_items( $product_id, $context );
                            $default_items = self::get_default_items( $product, $context );
                            $items         = array_merge( $product_items, $rule_items, $default_items );
                            break;
                    }

                    if ( ! empty( $items ) ) {
                        break;
                    }
                }
            }

            return apply_filters( 'woobt_get_items', $items, $product_id, $context );
        }

        function get_text( $product, $context = 'before' ) {
            // Optimize product ID extraction
            $product_id = is_a( $product, 'WC_Product' ) ? $product->get_id() :
                    ( is_int( $product ) ? $product : 0 );

            // Early return for invalid products
            if ( ! $product_id || self::is_disable( $product_id ) ) {
                return apply_filters( 'woobt_get_text', '', $product, $context );
            }

            // Cache context check result
            $is_before = $context === 'before' || $context === 'above';

            // Get priority array with caching
            static $priority_cache = [];

            if ( ! isset( $priority_cache[ $product_id ] ) ) {
                $priority_cache[ $product_id ] = apply_filters( 'woobt_get_items_priority',
                        [ 'product', 'rule', 'default' ],
                        $product_id
                );
            }

            $text = '';

            foreach ( $priority_cache[ $product_id ] as $pr ) {
                switch ( $pr ) {
                    case 'product':
                        $meta_key = $is_before ? 'woobt_before_text' : 'woobt_after_text';
                        $text     = get_post_meta( $product_id, $meta_key, true );

                        break;
                    case 'rule':
                        static $rule_cache = [];

                        if ( ! isset( $rule_cache[ $product_id ] ) ) {
                            $rule_cache[ $product_id ] = self::get_rule( $product_id );
                        }

                        if ( ! empty( $rule_cache[ $product_id ] ) ) {
                            $text = $is_before ?
                                    ( $rule_cache[ $product_id ]['before_text'] ?? '' ) :
                                    ( $rule_cache[ $product_id ]['after_text'] ?? '' );
                        }

                        break;
                    case 'default':
                        static $helper;

                        if ( ! isset( $helper ) ) {
                            $helper = WPCleverWoobt_Helper();
                        }

                        $text = $is_before ?
                                $helper->localization( 'above_text' ) :
                                $helper->localization( 'under_text' );

                        break;
                }

                if ( ! empty( $text ) ) {
                    break;
                }
            }

            return apply_filters( 'woobt_get_text', $text, $product, $context );
        }

        function is_disable( $product, $context = 'view' ) {
            $disable = false;

            if ( is_a( $product, 'WC_Product' ) ) {
                $product_id = $product->get_id();
            } elseif ( is_int( $product ) ) {
                $product_id = $product;
            } else {
                $product_id = 0;
            }

            if ( $product_id ) {
                $disable = get_post_meta( $product_id, 'woobt_disable', true ) === 'yes';
            }

            return apply_filters( 'woobt_is_disable', $disable, $product, $context );
        }

        function get_product_quantity( $product, $context = 'view' ) {
            $quantity = 'default';

            if ( is_a( $product, 'WC_Product' ) ) {
                $product_id = $product->get_id();
            } elseif ( is_int( $product ) ) {
                $product_id = $product;
            } else {
                $product_id = 0;
            }

            if ( $product_id ) {
                $quantity = get_post_meta( $product_id, 'woobt_quantity', true );

                if ( empty( $quantity ) ) {
                    if ( get_post_meta( $product_id, 'woobt_custom_qty', true ) === 'on' ) {
                        $quantity = 'custom';
                    } elseif ( get_post_meta( $product_id, 'woobt_sync_qty', true ) === 'on' ) {
                        $quantity = 'sync';
                    } else {
                        $quantity = 'default';
                    }
                }
            }

            return apply_filters( 'woobt_product_quantity', $quantity, $product, $context );
        }

        function get_discount( $product_id ) {
            static $cache = [];

            if ( isset( $cache[ $product_id ] ) ) {
                return apply_filters( 'woobt_get_discount', $cache[ $product_id ], $product_id );
            }

            $discount = 0;

            if ( $product_id && ! self::is_disable( $product_id ) ) {
                $ids = self::get_ids( $product_id );

                if ( ! empty( $ids ) ) {
                    $discount = get_post_meta( $product_id, 'woobt_discount', true ) ?: 0;
                } else {
                    $rule = self::get_rule( $product_id );

                    if ( ! empty( $rule ) ) {
                        $discount = $rule['discount'] ?? 0;
                    } else {
                        $discount = WPCleverWoobt_Helper()->get_setting( 'default_discount', 0 );
                    }
                }
            }

            $cache[ $product_id ] = $discount;

            return apply_filters( 'woobt_get_discount', $discount, $product_id );
        }

        function get_items_from_ids( $ids, $product_id = 0, $context = 'view' ) {
            $product_items = self::get_product_items( $product_id, $context );
            $items         = [];

            if ( ! empty( $ids ) ) {
                $_items = explode( ',', $ids );

                if ( is_array( $_items ) && count( $_items ) > 0 ) {
                    foreach ( $_items as $_item ) {
                        $_item_data    = explode( '/', $_item );
                        $_item_id      = apply_filters( 'woobt_item_id', absint( $_item_data[0] ?? 0 ), $_item, $product_id );
                        $_item_product = wc_get_product( $_item_id );

                        if ( ! $_item_product || ( $_item_product->get_status() === 'trash' ) ) {
                            continue;
                        }

                        if ( ( $context === 'view' ) && ( ( WPCleverWoobt_Helper()->get_setting( 'exclude_unpurchasable', 'no' ) === 'yes' ) && ( ! $_item_product->is_purchasable() || ! $_item_product->is_in_stock() ) ) ) {
                            continue;
                        }

                        $_item_key   = $_item_data[1] ?? WPCleverWoobt_Helper()->generate_key();
                        $_item_price = WPCleverWoobt_Helper()->get_setting( 'default_price', '100%' );

                        if ( str_contains( $_item_key, '-' ) ) {
                            // smart rules
                            $_item_key_arr = explode( '-', $_item_key );
                            $rule_key      = $_item_key_arr[1] ?? '';

                            if ( ! empty( $rule_key ) ) {
                                $rules = self::$rules;

                                if ( is_array( $rules ) && isset( $rules[ $rule_key ]['price'] ) ) {
                                    $_item_price = $rules[ $rule_key ]['price'];
                                }
                            }
                        } else {
                            // product or default
                            if ( is_array( $product_items ) && isset( $product_items[ $_item_key ]['price'] ) ) {
                                $_item_price = $product_items[ $_item_key ]['price'];
                            }
                        }

                        $items[ $_item_key ] = [
                                'id'    => $_item_id,
                                'price' => WPCleverWoobt_Helper()->format_price( $_item_price ),
                                'qty'   => (float) ( $_item_data[2] ?? 1 ),
                                'attrs' => isset( $_item_data[3] ) ? (array) json_decode( rawurldecode( $_item_data[3] ) ) : []
                        ];
                    }
                }
            }

            return apply_filters( 'woobt_get_items_from_ids', $items, $ids, $product_id, $context );
        }

        function search_sku( $query ) {
            if ( $query->is_search && isset( $query->query['is_woobt'] ) ) {
                global $wpdb;
                $sku = sanitize_text_field( $query->query['s'] );
                $ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value = %s;", $sku ) );

                if ( ! $ids ) {
                    return;
                }

                unset( $query->query['s'], $query->query_vars['s'] );
                $query->query['post__in'] = [];

                foreach ( $ids as $id ) {
                    $post = get_post( $id );

                    if ( $post->post_type === 'product_variation' ) {
                        $query->query['post__in'][]      = $post->post_parent;
                        $query->query_vars['post__in'][] = $post->post_parent;
                    } else {
                        $query->query_vars['post__in'][] = $post->ID;
                    }
                }
            }
        }

        function search_exact( $query ) {
            if ( $query->is_search && isset( $query->query['is_woobt'] ) ) {
                $query->set( 'exact', true );
            }
        }

        function search_sentence( $query ) {
            if ( $query->is_search && isset( $query->query['is_woobt'] ) ) {
                $query->set( 'sentence', true );
            }
        }

        // Deprecated functions - moved to WPCleverWoobt_Helper

        public static function get_settings() {
            return WPCleverWoobt_Helper()->get_settings();
        }

        public static function get_setting( $name, $default = false ) {
            return WPCleverWoobt_Helper()->get_setting( $name, $default );
        }

        public static function localization( $key = '', $default = '' ) {
            return WPCleverWoobt_Helper()->localization( $key, $default );
        }

        public static function data_attributes( $attrs ) {
            return WPCleverWoobt_Helper()->data_attributes( $attrs );
        }

        public static function generate_key() {
            return WPCleverWoobt_Helper()->generate_key();
        }

        public static function sanitize_array( $arr ) {
            return WPCleverWoobt_Helper()->sanitize_array( $arr );
        }

        public static function clean_ids( $ids ) {
            return WPCleverWoobt_Helper()->clean_ids( $ids );
        }

        public static function format_price( $price ) {
            return WPCleverWoobt_Helper()->format_price( $price );
        }

        public static function new_price( $old_price, $new_price ) {
            return WPCleverWoobt_Helper()->new_price( $old_price, $new_price );
        }
    }

    function WPCleverWoobt() {
        return WPCleverWoobt::instance();
    }

    WPCleverWoobt();
}