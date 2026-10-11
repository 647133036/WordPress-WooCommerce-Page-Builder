<?php
/**
 * REST endpoints backing the Flutterwave admin app.
 *
 * @package    Flutterwave/WooCommerce
 * @subpackage Flutterwave/WooCommerce/admin
 */

declare(strict_types=1);

namespace Flutterwave\WooCommerce\Admin;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-flutterwave-settings.php';

/**
 * Settings REST controller.
 */
final class Flutterwave_Settings_Controller {

	/**
	 * Register hooks. Call once from the plugin bootstrap.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			Flutterwave_Settings::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Flutterwave_Settings::REST_NAMESPACE,
			'/onboarding/complete',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'complete_onboarding' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Only shop managers and administrators may read or write gateway keys.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * GET /settings.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_settings(): \WP_REST_Response {
		return rest_ensure_response( Flutterwave_Settings::to_payload() );
	}

	/**
	 * POST /settings.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}

		return rest_ensure_response( Flutterwave_Settings::update( (array) $body ) );
	}

	/**
	 * POST /onboarding/complete — flips the wizard flag and enables the gateway.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function complete_onboarding( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$body['onboardingComplete'] = true;
		$body['enabled']            = true;

		$payload = Flutterwave_Settings::update( $body );

		/**
		 * Fires once a merchant finishes the onboarding wizard.
		 *
		 * Lets the SigNoz app-registration path run for merchants who never
		 * touch the legacy WooCommerce settings screen.
		 *
		 * @since 3.4.0
		 */
		do_action( 'woocommerce_update_options_payment_gateways_rave' );

		return rest_ensure_response( $payload );
	}
}
