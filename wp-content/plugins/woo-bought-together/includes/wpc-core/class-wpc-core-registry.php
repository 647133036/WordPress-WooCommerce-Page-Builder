<?php
/**
 * WPC Core Registry — Singleton that stores all registered WPC plugin info.
 *
 * Each WPC plugin calls wpc_core_register() to add itself.
 * Core modules (HPOS, Log, etc.) loop through the registry to serve every plugin.
 *
 * @package WPC_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPC_Core_Registry' ) ) {
	class WPC_Core_Registry {
		/** @var self|null */
		private static $instance = null;

		/** @var array Registered plugins keyed by prefix */
		private $plugins = [];

		/**
		 * Get singleton instance.
		 *
		 * @return self
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Register a WPC plugin.
		 *
		 * @param array $args {
		 *     @type string $file    Full path to the main plugin file (__FILE__). Required.
		 *     @type string $version Plugin version string. Required.
		 *     @type string $prefix  Unique log prefix, e.g. 'wpcpr_premium'. Required.
		 *     @type string $slug    Plugin directory slug. Auto-detected if empty.
		 * }
		 */
		public function register( $args ) {
			$args = wp_parse_args( $args, [
				'file'    => '',
				'version' => '1.0.0',
				'prefix'  => '',
				'slug'    => '',
			] );

			if ( empty( $args['file'] ) || empty( $args['prefix'] ) ) {
				return;
			}

			if ( empty( $args['slug'] ) ) {
				$args['slug'] = basename( dirname( $args['file'] ) );
			}

			// Key by prefix to prevent duplicates
			$this->plugins[ $args['prefix'] ] = $args;
		}

		/**
		 * Get all registered plugins.
		 *
		 * @return array
		 */
		public function get_plugins() {
			return $this->plugins;
		}

		/**
		 * Get a single registered plugin by prefix.
		 *
		 * @param string $prefix
		 *
		 * @return array|null
		 */
		public function get_plugin( $prefix ) {
			return $this->plugins[ $prefix ] ?? null;
		}
	}
}
