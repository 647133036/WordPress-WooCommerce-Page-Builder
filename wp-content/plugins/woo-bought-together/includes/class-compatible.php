<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWoobt_Compatible' ) ) {
	class WPCleverWoobt_Compatible {
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			// WPML
			if ( function_exists( 'wpml_loaded' ) && apply_filters( 'woobt_wpml_filters', true ) ) {
				add_filter( 'woobt_item_id', [ $this, 'wpml_product_id' ], 99 );
				add_filter( 'woobt_parent_id', [ $this, 'wpml_product_id' ], 99 );
			}

			// WPC Variations Radio Buttons
			add_filter( 'woovr_default_selector', [ $this, 'woovr_default_selector' ], 99, 4 );

			// WPC Smart Messages
			add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );
		}

		function wpml_product_id( $id ) {
			return apply_filters( 'wpml_object_id', $id, 'product', true );
		}

		function woovr_default_selector( $selector, $product, $variation, $context ) {
			if ( isset( $context ) && ( $context === 'woobt' ) ) {
				if ( ( $selector_interface = WPCleverWoobt_Helper()->get_setting( 'selector_interface', 'unset' ) ) && ( $selector_interface !== 'unset' ) ) {
					$selector = $selector_interface;
				}
			}

			return $selector;
		}

		function wpcsm_locations( $locations ) {
			$locations['WPC Frequently Bought Together'] = [
				'woobt_wrap_before'          => esc_html__( 'Before wrapper', 'woo-bought-together' ),
				'woobt_wrap_after'           => esc_html__( 'After wrapper', 'woo-bought-together' ),
				'woobt_products_before'      => esc_html__( 'Before products', 'woo-bought-together' ),
				'woobt_products_after'       => esc_html__( 'After products', 'woo-bought-together' ),
				'woobt_product_before'       => esc_html__( 'Before sub-product', 'woo-bought-together' ),
				'woobt_product_after'        => esc_html__( 'After sub-product', 'woo-bought-together' ),
				'woobt_product_thumb_before' => esc_html__( 'Before sub-product thumbnail', 'woo-bought-together' ),
				'woobt_product_thumb_after'  => esc_html__( 'After sub-product thumbnail', 'woo-bought-together' ),
				'woobt_product_name_before'  => esc_html__( 'Before sub-product name', 'woo-bought-together' ),
				'woobt_product_name_after'   => esc_html__( 'After sub-product name', 'woo-bought-together' ),
				'woobt_product_price_before' => esc_html__( 'Before sub-product price', 'woo-bought-together' ),
				'woobt_product_price_after'  => esc_html__( 'After sub-product price', 'woo-bought-together' ),
				'woobt_product_qty_before'   => esc_html__( 'Before sub-product quantity', 'woo-bought-together' ),
				'woobt_product_qty_after'    => esc_html__( 'After sub-product quantity', 'woo-bought-together' ),
			];

			return $locations;
		}
	}
}

WPCleverWoobt_Compatible::instance();
