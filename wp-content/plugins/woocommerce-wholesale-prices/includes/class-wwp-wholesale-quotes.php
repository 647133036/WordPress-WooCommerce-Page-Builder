<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Wholesale_Quotes' ) ) {

    /**
     * Model that houses logic related to Wholesale > Wholesale Quotes
     *
     * @since 2.2.5
     */
    class WWP_Wholesale_Quotes {


        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWP_Wholesale_Quotes.
         *
         * @since 2.2.5
         * @access private
         * @var WWP_Wholesale_Quotes
         */
        private static $_instance;

        /**
         * Class Methods
         */


        /**
         * Ensure that only one instance of WWP_Wholesale_Quotes is loaded or can be loaded (Singleton Pattern).
         *
         * @since 2.2.5
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Wholesale_Quotes model.
         * @return WWP_Wholesale_Quotes
         */
        public static function instance( $dependencies = array() ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Integration of WC Navigation Bar.
         *
         * @since 2.2.5
         * @access public
         */
        public function wc_navigation_bar() {
            if ( function_exists( 'wc_admin_connect_page' ) ) {
                wc_admin_connect_page(
                    array(
                        'id'        => 'wwp-wholesale-quotes-page',
                        'screen_id' => 'wholesale_page_wwp-wholesale-quotes-page',
                        'title'     => __( 'Wholesale Quotes', 'woocommerce-wholesale-prices' ),
                    )
                );
            }
        }

        /**
         * View for Wholesale Quotes page.
         *
         * @since 2.2.5
         * @access public
         */
        public function view_wholesale_quotes_page() {
            require_once WWP_VIEWS_PATH . 'view-wwp-wholesale-quotes.php';
        }

        /**
         * Register new wholesale quotes menu item if WWQ is not active
         *
         * @since 2.2.5
         * @access public
         */
        public function register_wholesale_quotes_page_menu() {
            // Show upgrade page when WWQ is not active (covers both not installed and installed but inactive).
            if ( ! WWP_Helper_Functions::is_wwq_active() ) {
                add_submenu_page(
                    'wholesale-suite',
                    __( 'Wholesale Quotes', 'woocommerce-wholesale-prices' ),
                    __( 'Wholesale Quotes', 'woocommerce-wholesale-prices' ),
                    apply_filters( 'wwp_can_access_admin_menu_cap', 'manage_woocommerce' ),
                    'wwp-wholesale-quotes-page',
                    array( $this, 'view_wholesale_quotes_page' ),
                    3
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Execute Model
        |--------------------------------------------------------------------------
        */

        /**
         * Execute model.
         *
         * @since 2.2.5
         * @access public
         */
        public function run() {
            // Add a new submenu under the WooCommerce menu for Wholesale Quotes.
            add_action( 'admin_menu', array( $this, 'register_wholesale_quotes_page_menu' ), 99 );

            // Add WC navigation bar to page.
            add_action( 'init', array( $this, 'wc_navigation_bar' ) );
        }
    }
}
