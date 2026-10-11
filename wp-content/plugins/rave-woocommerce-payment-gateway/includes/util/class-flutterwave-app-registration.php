<?php
/**
 * Auto-triggers SigNoz app registration for merchants who update the plugin
 * without visiting the settings screen.
 *
 * Two triggers, both funnelling into the same guarded enqueue:
 *  - `upgrader_process_complete` fires immediately after an in-dashboard or
 *    WP-CLI update of this plugin.
 *  - `admin_init` compares the stored plugin version against the running
 *    version on every admin request, as a catch-all for update paths that
 *    bypass the WP upgrader (manual file replace, host-level deploys, and
 *    merchants who were already on a pre-registration version).
 *
 * Both paths only enqueue a background job (Action Scheduler); neither does
 * network I/O inline, since admin_init and the upgrader hook are not
 * shopper-facing and shouldn't accumulate work on every request either.
 *
 * @class          Flutterwave_App_Registration
 * @package    Flutterwave/WooCommerce
 * @subpackage Flutterwave/WooCommerce/util
 */

declare(strict_types=1);

namespace Flutterwave\WooCommerce\Util;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-flutterwave-signoz-logger.php';

/**
 * Auto-registration trigger for existing/updating merchants.
 */
final class Flutterwave_App_Registration {

	const REGISTER_HOOK = 'flw_signoz_register_app';
	const AS_GROUP      = 'flutterwave-signoz';

	/**
	 * Singleton instance.
	 *
	 * @var Flutterwave_App_Registration|null
	 */
	private static ?self $instance = null;

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Get or create the singleton.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks. Call once from the plugin bootstrap on `plugins_loaded`.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		$self = self::instance();

		add_action( self::REGISTER_HOOK, array( $self, 'handle_scheduled_registration' ) );
		add_action( 'admin_init', array( $self, 'maybe_trigger_on_admin_init' ) );
		add_action( 'upgrader_process_complete', array( $self, 'maybe_trigger_on_upgrade' ), 10, 2 );
	}

	/**
	 * Catch-all: on every admin request, check whether registration is
	 * missing or stale for the currently running plugin version.
	 *
	 * @return void
	 */
	public function maybe_trigger_on_admin_init(): void {
		if ( ! is_admin() ) {
			return;
		}

		$this->maybe_enqueue_registration();
	}

	/**
	 * Fast path: fires right after this plugin is updated via the WP
	 * upgrader (dashboard "Update Now" or WP-CLI `plugin update`).
	 *
	 * @param \WP_Upgrader $upgrader_object Unused; required by the hook signature.
	 * @param array        $options         Upgrade context: type, action, plugins/plugin.
	 * @return void
	 */
	public function maybe_trigger_on_upgrade( $upgrader_object, array $options ): void {
		unset( $upgrader_object );

		if ( ( $options['type'] ?? '' ) !== 'plugin' || ( $options['action'] ?? '' ) !== 'update' ) {
			return;
		}

		if ( ! defined( 'FLW_WC_PLUGIN_FILE' ) ) {
			return;
		}

		$plugin_basename = plugin_basename( FLW_WC_PLUGIN_FILE );

		$updated_plugins = array();
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$updated_plugins = $options['plugins']; // Bulk update.
		} elseif ( ! empty( $options['plugin'] ) ) {
			$updated_plugins = array( $options['plugin'] ); // Single update.
		}

		if ( ! in_array( $plugin_basename, $updated_plugins, true ) ) {
			return; // A different plugin was updated.
		}

		$this->maybe_enqueue_registration();
	}

	/**
	 * Decide whether registration is needed and, if so, queue it.
	 * Cheap: option reads/comparisons only, no network calls.
	 *
	 * @return void
	 */
	private function maybe_enqueue_registration(): void {
		$settings = get_option( 'woocommerce_rave_settings', array() );

		$current_version = defined( 'FLW_WC_VERSION' ) ? FLW_WC_VERSION : '';
		$is_registered   = ! empty( $settings['app_registered'] );
		$stored_version  = $settings['plugin_version'] ?? '';

		if ( $is_registered && '' !== $current_version && $stored_version === $current_version ) {
			return; // Already registered on the version currently running.
		}

		$go_live    = $settings['go_live'] ?? 'no';
		$public_key = 'yes' === $go_live
			? ( $settings['live_public_key'] ?? '' )
			: ( $settings['test_public_key'] ?? '' );

		if ( empty( $public_key ) ) {
			return; // Merchant hasn't configured keys yet — nothing to register.
		}

		if ( function_exists( 'as_has_scheduled_action' )
			&& as_has_scheduled_action( self::REGISTER_HOOK, array( $public_key ), self::AS_GROUP ) ) {
			return; // Already queued; avoid piling up duplicate jobs.
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::REGISTER_HOOK, array( $public_key ), self::AS_GROUP );
			return;
		}

		// Action Scheduler unavailable — fall back to a direct call. Only
		// acceptable here because admin_init/upgrader hooks are not
		// shopper-facing, so a brief synchronous call is tolerable.
		$this->handle_scheduled_registration( $public_key );
	}

	/**
	 * Action Scheduler callback: perform the actual registration.
	 * Must be public so the hook can invoke it. On success, stamps the
	 * settings with `app_registered` and `plugin_version` so subsequent
	 * admin_init checks short-circuit. On failure, leaves both untouched so
	 * the next admin page load retries automatically.
	 *
	 * @param string $public_key Merchant Flutterwave public key.
	 * @return void
	 */
	public function handle_scheduled_registration( string $public_key ): void {
		try {
			$logger = Flutterwave_Signoz_Logger::instance();
			$logger->init();

			$app_id = $logger->track_app_created( $public_key );

			if ( null === $app_id ) {
				return; // Service unavailable / circuit open — retry on next admin_init.
			}

			$settings                   = get_option( 'woocommerce_rave_settings', array() );
			$settings['app_registered'] = true;

			if ( defined( 'FLW_WC_VERSION' ) ) {
				$settings['plugin_version'] = FLW_WC_VERSION;
			}

			update_option( 'woocommerce_rave_settings', $settings );
		} catch ( \Throwable $e ) {
			// Observability must never break the site.
			unset( $e );
		}
	}
}
