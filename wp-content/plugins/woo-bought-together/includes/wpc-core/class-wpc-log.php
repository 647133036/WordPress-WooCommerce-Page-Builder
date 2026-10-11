<?php
/**
 * WPC Core — Logging & Lifecycle Hooks.
 *
 * Provides a shared wpc_log() function and automatically registers
 * activation/deactivation/upgrade hooks for ALL registered WPC plugins.
 *
 * @package WPC_Core
 */

defined( 'ABSPATH' ) || exit;

// Shared log function
if ( ! function_exists( 'wpc_log' ) ) {
	/**
	 * Write a log entry for a WPC plugin.
	 *
	 * @param string $prefix Plugin log prefix (e.g. 'wpcpr_premium').
	 * @param string $action Action description (e.g. 'installed', 'upgraded').
	 */
	function wpc_log( $prefix, $action ) {
		$logs = get_option( 'wpc_logs', [] );
		$user = wp_get_current_user();

		if ( ! isset( $logs[ $prefix ] ) ) {
			$logs[ $prefix ] = [];
		}

		$logs[ $prefix ][] = [
			'time'   => current_time( 'mysql' ),
			'user'   => $user->display_name . ' (ID: ' . $user->ID . ')',
			'action' => $action,
		];

		update_option( 'wpc_logs', $logs, false );
	}
}

// Register lifecycle hooks for ALL plugins — runs early in plugins_loaded
add_action( 'plugins_loaded', function () {
	$plugins = WPC_Core_Registry::instance()->get_plugins();

	foreach ( $plugins as $prefix => $plugin ) {
		$file    = $plugin['file'];
		$version = $plugin['version'];

		// Activation hook
		register_activation_hook( $file, function () use ( $prefix, $version ) {
			wpc_log( $prefix, 'installed' );
			update_option( $prefix . '_version', $version, false );
		} );

		// Deactivation hook
		register_deactivation_hook( $file, function () use ( $prefix ) {
			wpc_log( $prefix, 'deactivated' );
		} );
	}
}, 0 );

// Version upgrade check
add_action( 'admin_init', function () {
	$plugins = WPC_Core_Registry::instance()->get_plugins();

	foreach ( $plugins as $prefix => $plugin ) {
		$stored  = get_option( $prefix . '_version', '' );
		$current = $plugin['version'];

		if ( ! empty( $stored ) && version_compare( $stored, $current, '<' ) ) {
			wpc_log( $prefix, 'upgraded' );
			update_option( $prefix . '_version', $current, false );
		}
	}
} );
