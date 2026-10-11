<?php
/**
 * The client-specific functionality of the plugin.
 *
 * @link       https://flutterwave.com
 * @since      2.3.2
 *
 * @package    FLW_WC_Payment_Gateway
 * @subpackage FLW_WC_Payment_Gateway/includes
 */

declare(strict_types=1);

namespace Flutterwave\WooCommerce\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent direct access to this class.
defined( 'FLUTTERWAVEACCESS' ) || exit( 'No direct script access allowed' );

require_once FLW_WC_DIR_PATH . 'includes/client/class-flw-wc-payment-gateway-sdk.php';

define( 'FLW_WC_PAYMENT_GATEWAY_URL', plugin_dir_url( FLW_WC_PLUGIN_FILE ) );
define( 'FLW_WC_PAYMENT_GATEWAY_VERSION', 'v3' );



/**
 * Class FLW_WC_Payment_Gateway_Client
 *
 * @package Flutterwave\WooCommerce
 */
class FLW_WC_Payment_Gateway_Client {

	const API_VERSION  = 'v3';
	const API_BASE_URL = 'https://api.flutterwave.com';
	/**
	 * Secret key for the merchant.
	 *
	 * @var string - The secret key for the merchant.
	 */
	private string $secret_key;
	/**
	 * The logger for the plugin.
	 *
	 * @var \WC_Logger_Interface - The logger for the plugin.
	 */
	private \WC_Logger_Interface $logger;
	/**
	 * The headers for the request.
	 *
	 * @var array|string[] - The headers for the request.
	 */
	private array $headers;

	/**
	 * FLW_WC_Payment_Gateway_Client constructor.
	 *
	 * @param string $secret_key - The secret key for the merchant.
	 * @param bool   $is_logging_enabled - Whether to log the request or not.
	 */
	public function __construct(
		string $secret_key,
		bool $is_logging_enabled = false
	) {
		$this->logger     = \wc_get_logger();
		$this->secret_key = $secret_key;
		$this->setup();
		if ( $is_logging_enabled ) {
			$this->logger->log( 'info', 'Logging enabled', array( 'source' => 'flutterwave' ) );
		}
	}

	/**
	 * Set up the headers for the request.
	 *
	 * @return void - Sets up the headers for the request.
	 */
	private function setup() {
		$this->headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->secret_key,
		);
	}

	/**
	 * Request to the Flutterwave API.
	 *
	 * @param string     $url - The url to make the request to.
	 * @param string     $method - The method to use for the request.
	 * @param array|null $body - The body of the request.
	 *
	 * @return array|\WP_Error - The response from the request.
	 */
	public function request( $url, string $method = 'GET', $body = null ) {
		$body = wp_json_encode( $body, JSON_UNESCAPED_SLASHES );

		$args = array(
			'method'  => $method,
			'headers' => $this->headers,
			// The WordPress default of 5s is too tight for a payment verification
			// that a customer is waiting on at the end of checkout.
			'timeout' => 30,
		);

		if ( 'GET' !== $method ) {
			$args['body'] = $body;
		}

		return wp_safe_remote_request( $url, $args ); // return the body of the response json.
	}

	/**
	 * Handle the error from the request.
	 *
	 * @param \WP_Error $response - The response from the request.
	 *
	 * @return void - Logs the error and adds a notice to the cart.
	 */
	public function handle_error( \WP_Error $response ) {
		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'error', $response->get_error_message(), array( 'source' => 'flutterwave' ) );
			wc_add_notice( 'Unable to connect to Flutterwave. Please Try Again', 'error' );
		}
	}
}
