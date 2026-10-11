<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WWP_Wholesale_Payments_Page' ) ) {
	/**
	 * Defines the logic for the Wholesale Payments Page.
	 *
	 * @version 2.2.0
	 * @since   2.2.0
	 */
	class WWP_Wholesale_Payments_Page {
		/**
		 * Holds singleton instance of the class.
		 *
		 * @var WWP_Wholesale_Payments_Page
		 * @version 2.2.0
		 * @since   2.2.0
		 */
		private static $_instance;

		/**
		 * Get or create an instance of the class.
         *
		 * @return WWP_Wholesale_Payments_Page
		 * @version 2.2.0
		 * @since   2.2.0
		 */
		public static function instance() {
			if ( ! self::$_instance instanceof self ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Register the submenu item for the page.
		 *
		 * @return void
		 * @version 2.2.0
		 * @since   2.2.0
		 */
		public function register_submenu_page() {
			if ( WWP_Helper_Functions::is_wpay_installed() && WWP_Helper_Functions::is_wpay_active() ) {
				return;
			}

			add_submenu_page(
				'wholesale-suite',
				__( 'Payments', 'woocommerce-wholesale-prices' ),
				__( 'Payments', 'woocommerce-wholesale-prices' ),
				'manage_options',
				'wholesale-payments',
				array( $this, 'render_page_html' ),
				5
			);
		}

		/**
		 * Render the page's HTML content
		 *
		 * @return void
		 * @version 2.2.0
		 * @since   2.2.0
		 */
		public function render_page_html() {
			// Render the page HTML.

			require_once WWP_VIEWS_PATH . 'view-wwp-wholesale-payments-page.php';
		}

		/**
         * Run the actions and filters for the page.
         *
         * @since 2.2.0
         * @access public
         */
        public function run() {
            add_action( 'admin_menu', array( $this, 'register_submenu_page' ), 99 );
			add_action( 'activated_plugin', array( $this, 'maybe_redirect' ) );
			add_action( 'admin_init', array( $this, 'check_redirect' ) );
        }

		/**
		 * Check if WPay is activated from the WPay page.
		 *
		 * @return void
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function check_redirect() {
			if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'activate-plugin_woocommerce-wholesale-payments/woocommerce-wholesale-payments.php' ) ) {
				return;
			}

			if ( ! isset( $_GET['activate_wpay'] ) || 1 !== (int) $_GET['activate_wpay'] ) {
				return;
			}

			set_transient( 'wpay_activated_from_wpay_page', true, MINUTE_IN_SECONDS );
		}

		/**
		 * Check if the activated plugin is WPay then redirect to WPay page.
		 *
		 * @param string $plugin The plugin file that was activated.
		 * @return void
		 * @version 2.2.0
		 * @since   2.2.0
		 */
		public function maybe_redirect( $plugin ) {
			if ( ! get_transient( 'wpay_activated_from_wpay_page' ) ) {
				return;
			}

			delete_transient( 'wpay_activated_from_wpay_page' );

			if ( 'woocommerce-wholesale-payments/woocommerce-wholesale-payments.php' === $plugin ) {
				wp_safe_redirect( admin_url( 'admin.php?page=wpay' ) );
				exit;
			}
		}
	}
}
