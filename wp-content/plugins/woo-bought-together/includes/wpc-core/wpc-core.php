<?php
/**
 * WPC Core — Single entry point for the shared WPC core library.
 *
 * Usage in any WPC plugin:
 *   require_once __DIR__ . '/includes/wpc-core/wpc-core.php';
 *   wpc_core_register( [ 'file' => __FILE__, 'version' => '1.0.0', 'prefix' => 'my_plugin' ] );
 *
 * @package WPC_Core
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Version guard: only load the highest version when multiple plugins ship wpc-core.
$wpc_core_this_version = '1.0.0';

if ( defined( 'WPC_CORE_VERSION' ) ) {
	if ( version_compare( WPC_CORE_VERSION, $wpc_core_this_version, '>=' ) ) {
		return; // A newer or equal version is already loaded.
	}
}

define( 'WPC_CORE_VERSION', $wpc_core_this_version );
define( 'WPC_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPC_CORE_URI', plugin_dir_url( __FILE__ ) );

// Load registry first — other modules depend on it.
require_once WPC_CORE_DIR . 'class-wpc-core-registry.php';

// Load modules
require_once WPC_CORE_DIR . 'class-wpc-dashboard.php';
require_once WPC_CORE_DIR . 'class-wpc-kit.php';
require_once WPC_CORE_DIR . 'class-wpc-log.php';
require_once WPC_CORE_DIR . 'class-wpc-hpos.php';

/**
 * Shortcut function for plugins to register with the core.
 *
 * @param array $args See WPC_Core_Registry::register().
 */
if ( ! function_exists( 'wpc_core_register' ) ) {
	function wpc_core_register( $args ) {
		WPC_Core_Registry::instance()->register( $args );
	}
}
