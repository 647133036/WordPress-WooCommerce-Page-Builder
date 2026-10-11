<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWoobt_Helper' ) ) {
	class WPCleverWoobt_Helper {
		protected static $instance = null;
		protected static $settings = [];
		protected static $localization = [];

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			// settings
			self::$settings = (array) get_option( 'woobt_settings', [] );
			// localization
			self::$localization = (array) get_option( 'woobt_localization', [] );
		}

		public static function get_settings() {
			return apply_filters( 'woobt_get_settings', self::$settings );
		}

		public static function get_setting( $name, $default = false ) {
			if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
				$setting = self::$settings[ $name ];
			} else {
				$setting = get_option( 'woobt_' . $name, $default );
			}

			return apply_filters( 'woobt_get_setting', $setting, $name, $default );
		}

		public static function localization( $key = '', $default = '' ) {
			$str = '';

			if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
				$str = self::$localization[ $key ];
			} elseif ( ! empty( $default ) ) {
				$str = $default;
			}

			return apply_filters( 'woobt_localization_' . $key, $str );
		}

		public static function clean_ids( $ids ) {
			return apply_filters( 'woobt_clean_ids', $ids );
		}

		public static function sanitize_array( $arr ) {
			foreach ( (array) $arr as $k => $v ) {
				if ( is_array( $v ) ) {
					$arr[ $k ] = self::sanitize_array( $v );
				} else {
					$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
				}
			}

			return $arr;
		}

		public static function generate_key() {
			$key         = '';
			$key_str     = apply_filters( 'woobt_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
			$key_str_len = strlen( $key_str );

			for ( $i = 0; $i < apply_filters( 'woobt_key_length', 4 ); $i ++ ) {
				$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
			}

			if ( is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			return apply_filters( 'woobt_generate_key', $key );
		}

		public static function data_attributes( $attrs ) {
			$attrs_arr = [];

			foreach ( $attrs as $key => $attr ) {
				$attrs_arr[] = esc_attr( 'data-' . sanitize_title( $key ) ) . '="' . esc_attr( $attr ) . '"';
			}

			return implode( ' ', $attrs_arr );
		}

		public static function format_price( $price ) {
			// format price to percent or number
			$format_price = preg_replace( '/[^.\-%0-9]/', '', $price );

			return apply_filters( 'woobt_format_price', $format_price, $price );
		}

		public static function new_price( $old_price, $new_price ) {
			if ( str_contains( $new_price, '%' ) ) {
				$calc_price = ( (float) $new_price * $old_price ) / 100;
			} else {
				$calc_price = $new_price;
			}

			return apply_filters( 'woobt_new_price', $calc_price, $old_price );
		}
	}

	function WPCleverWoobt_Helper() {
		return WPCleverWoobt_Helper::instance();
	}
}