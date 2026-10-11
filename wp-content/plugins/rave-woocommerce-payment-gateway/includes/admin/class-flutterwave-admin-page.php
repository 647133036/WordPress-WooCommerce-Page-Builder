<?php
/**
 * Registers the standalone Flutterwave admin surface.
 *
 * The onboarding wizard and the post-setup settings screen are a single React
 * app mounted on a WooCommerce submenu page. The legacy WooCommerce gateway
 * section is kept registered (WooCommerce needs it to persist settings) but
 * visiting it redirects here so there is only one editable UI.
 *
 * @package    Flutterwave/WooCommerce
 * @subpackage Flutterwave/WooCommerce/admin
 */

declare(strict_types=1);

namespace Flutterwave\WooCommerce\Admin;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-flutterwave-settings.php';

/**
 * Menu registration, asset loading and legacy-screen redirect.
 */
final class Flutterwave_Admin_Page {

	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'flutterwave-payment';

	/**
	 * Singleton instance.
	 *
	 * @var Flutterwave_Admin_Page|null
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
	 * Register hooks. Call once from the plugin bootstrap.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		$self = self::instance();

		add_action( 'admin_menu', array( $self, 'register_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $self, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $self, 'redirect_legacy_section' ) );
		add_action( 'admin_notices', array( $self, 'legacy_secret_hash_notice' ) );
	}

	/**
	 * Show on every admin screen while the store still has the secret hash
	 * older versions shipped as the default. Webhooks are refused until it is
	 * replaced, so this is not dismissible. The wording says what to do, not
	 * why, so it tells no one reading over a shoulder anything useful.
	 *
	 * @return void
	 */
	public function legacy_secret_hash_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( Flutterwave_Settings::LEGACY_SECRET_HASH !== Flutterwave_Settings::raw()['secret_hash'] ) {
			return;
		}

		printf(
			'<div id="flw-legacy-secret-hash-notice" class="notice notice-error"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Flutterwave: action required.', 'rave-woocommerce-payment-gateway' ),
			esc_html__( 'Webhooks are paused until you set a new secret hash. Generate one, save, then add it to the webhook settings on your Flutterwave dashboard.', 'rave-woocommerce-payment-gateway' ),
			esc_url( add_query_arg( 'tab', 'api', self::get_url() ) ),
			esc_html__( 'Generate a new secret hash', 'rave-woocommerce-payment-gateway' )
		);
	}

	/**
	 * The URL of the standalone admin page.
	 *
	 * @return string
	 */
	public static function get_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Add the submenu entry under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Flutterwave Payment', 'rave-woocommerce-payment-gateway' ),
			__( 'Flutterwave Payment', 'rave-woocommerce-payment-gateway' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Send anyone landing on the legacy WooCommerce gateway section to the new page.
	 *
	 * Only redirects the plain view; POSTs are left alone so WooCommerce's own
	 * save handler is never interrupted.
	 *
	 * @return void
	 */
	public function redirect_legacy_section(): void {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing on a GET screen.
		$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab     = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'wc-settings' !== $page || 'checkout' !== $tab || 'rave' !== $section ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_safe_redirect( self::get_url() );
		exit;
	}

	/**
	 * Load the React bundle and styles on our page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_path = FLW_WC_DIR_PATH . 'build/admin.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			'flutterwave-admin',
			FLW_WC_PLUGIN_URL . 'build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( FLW_WC_DIR_PATH . 'build/admin.css' ) ) {
			wp_enqueue_style(
				'flutterwave-admin',
				FLW_WC_PLUGIN_URL . 'build/admin.css',
				array(),
				$asset['version']
			);

			// wp-scripts emits admin-rtl.css alongside admin.css.
			wp_style_add_data( 'flutterwave-admin', 'rtl', 'replace' );
		}

		wp_set_script_translations( 'flutterwave-admin', 'rave-woocommerce-payment-gateway', FLW_WC_DIR_PATH . 'i18n/languages' );

		wp_localize_script(
			'flutterwave-admin',
			'flutterwaveAdminData',
			array(
				'restUrl'       => esc_url_raw( rest_url( Flutterwave_Settings::REST_NAMESPACE ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'assetsUrl'     => FLW_WC_PLUGIN_URL . 'assets/img/admin/',
				'documentation' => 'https://developer.flutterwave.com/docs/woocommerce',
				'support'       => 'https://support.flutterwave.com/',
				'dashboardUrl'  => 'https://app.flutterwave.com/dashboard/settings/webhooks',
				'settingsUrl'   => self::get_url(),
			)
		);
	}

	/**
	 * Print the mount point.
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<div class="flw-admin-root" id="flutterwave-admin-root"></div>';
	}
}
