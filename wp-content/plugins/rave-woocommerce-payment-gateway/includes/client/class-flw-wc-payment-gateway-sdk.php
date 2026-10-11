<?php
/**
 * The client-specific functionality of the plugin.
 *
 * @link       https://flutterwave.com
 * @since      2.3.2
 * @class      FLW_WC_Payment_Gateway_Sdk
 * @package    Flutterwave\WooCommerce\Client
 * @subpackage FLW_WC_Payment_Gateway/includes
 */

namespace Flutterwave\WooCommerce\Client;

use Flutterwave\WooCommerce\Contracts\FLW_WC_Payment_Gateway_Event_Handler_Interface;
use FLW_WC_Payment_Gateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent direct access to this class.
defined( 'FLUTTERWAVEACCESS' ) || exit( 'No direct script access allowed' );

require_once __DIR__ . '/class-flw-wc-payment-gateway-client.php';
require_once __DIR__ . '/../util/class-flutterwave-logger.php';

/**
 * Class FLW_WC_Payment_Gateway_Sdk file.
 *
 * @package Flutterwave\WooCommerce\Client
 */
final class FLW_WC_Payment_Gateway_Sdk {
	/**
	 * Count of the number of times the server has been queried.
	 *
	 * @var int The number of times to requery the server.
	 */
	protected int $requery_count = 0;
	/**
	 * Client
	 *
	 * @var FLW_WC_Payment_Gateway_Client The client.
	 */
	public FLW_WC_Payment_Gateway_Client $client;
	/**
	 * Event handler
	 *
	 * @var FLW_WC_Payment_Gateway_Event_Handler_Interface
	 */
	private FLW_WC_Payment_Gateway_Event_Handler_Interface $event_handler;
	/**
	 * Data
	 *
	 * @var array The data to be sent to the server.
	 */
	private array $data;
	/**
	 * Standard inline endpoint
	 *
	 * @var string The endpoint to send the data to.
	 */
	public static string $standard_inline_endpoint = 'https://api.flutterwave.com/v3/payments';
	/**
	 * Inline Checkout Script
	 *
	 * @var string The script to initiate the payment.
	 */
	public static string $checkout_url = 'https://checkout.flutterwave.com/v3.js';
	/**
	 * Logger
	 *
	 * @var mixed
	 */
	private \WC_Logger $logger;
	/**
	 * Gateway
	 *
	 * @var FLW_WC_Payment_Gateway The gateway.
	 */
	private FLW_WC_Payment_Gateway $gateway;

	/**
	 * FLW_WC_Payment_Gateway_Sdk constructor.
	 *
	 * @param string $secret_key  The secret key.
	 * @param bool   $log_enabled Whether to log or not.
	 */
	public function __construct( string $secret_key, bool $log_enabled = false ) {
		$this->client = new FLW_WC_Payment_Gateway_Client( $secret_key, $log_enabled );
		$this->logger = wc_get_logger();
	}

	/**
	 * Clone method.
	 * Prevents cloning of this class.
	 *
	 * @since 2.3.2
	 *
	 * @return null The clone method.
	 */
	public function __clone() {
		return null;
	}

	/**
	 * Get the client.
	 * Returns the client.
	 *
	 * @since 2.3.2
	 *
	 * @return FLW_WC_Payment_Gateway_Client The client.
	 */
	public function get_client(): FLW_WC_Payment_Gateway_Client {
		return $this->client;
	}

	/**
	 * Set the event handler.
	 *
	 * @param FLW_WC_Payment_Gateway_Event_Handler_Interface $event_handler The event handler.
	 *
	 * @return FLW_WC_Payment_Gateway_Sdk The sdk.
	 */
	public function set_event_handler( FLW_WC_Payment_Gateway_Event_Handler_Interface $event_handler ): FLW_WC_Payment_Gateway_Sdk {
		$this->event_handler = $event_handler;

		return $this;
	}

	/**
	 * Prepares the html to be rendered.
	 *
	 * @param array $clean_data  This is the data that will be sent to the server.
	 * @param int   $order_id  The order id.
	 *
	 * @return string The html to be rendered.
	 */
	private function prepare_html( array $clean_data, int $order_id ): string {
		// TODO: enqueue inline checkout script.

		$html_array = array(
			'<html lang="en">',
			'<body>',
			'   <img src="' . esc_url( plugins_url( 'sdk/ajax-loader.gif', FLW_WC_PLUGIN_FILE ) ) . '" />',
			'  <script>',
			'       var isFlutterwaveCompleted = false;',
			'       document.addEventListener("DOMContentLoaded", function(event) {',
			'           FlutterwaveCheckout("", function(event) {',
			'               public_key: "' . $clean_data['public_key'] . '",',
			'               tx_ref: "' . $clean_data['tx_ref'] . '",',
			'               amount: ' . $clean_data['amount'] . ',',
			'               currency: "' . $clean_data['currency'] . '",',
			'               payment_options: "' . $clean_data['payment_options'] . '",',
			'               redirect_url: "' . $clean_data['redirect_url'] . '",',
			'               customer: {',
			'                   email: "' . $clean_data['email'] . '",',
			'                   phone_number: "' . $clean_data['phone_number'] . '",',
			'                   name: "' . $clean_data['first_name'] . ' ' . $clean_data['last_name'] . '",',
			'               },',
			'               meta: {',
			'                   consumer_id: ' . $clean_data['consumer_id'] . ',',
			'                   ip: "' . $clean_data['ip_address'] . '",',
			'               },',
			'               callback: function (data) {',
			'                   console.log(data);',
			'               },',
			'               onclose: function() {',
			'                   window.location = "?cancelled=cancelled&order_id=' . $order_id . '";',
			'               },',
			'               customizations: {',
			'                   title: "' . $clean_data['title'] . '",',
			'                   description: "' . $clean_data['description'] . '",',
			'                   logo: "' . $clean_data['logo'] . '",',
			'               },',
			'           });',
			'       });',
			'  </script>',
			'</body>',
			'</html>',
		);

		return implode( '', $html_array );
	}

	/**
	 * Render the modal.
	 *
	 * @param array $data This is the data to be sent to the payment gateway.
	 * @param int   $order_id This is the order id.
	 *
	 * @return false|string The json encoded data.
	 */
	public function render_modal( array $data, int $order_id ) {
		$clean_data = $data;
		$html       = $this->prepare_html( $clean_data, $order_id );
		$this->logger->notice( 'Loading Payment Modal for order:' . $order_id );
		echo wp_kses_post( $html );
		return wp_json_encode( $clean_data );
	}

	/**
	 * Transaction Reference.
	 *
	 * @param string $tx_ref This is the unique reference for the transaction.
	 *
	 * @return void The requery transaction.
	 */
	public function requery_transaction( string $tx_ref ) {
		++$this->requery_count;
		$this->logger->notice( 'Requerying Transaction....' . $tx_ref );

		if ( isset( $this->event_handler ) ) {
			$this->event_handler->on_requery( $tx_ref );
		}

		$response = $this->verify_by_reference( $tx_ref );

		if ( is_wp_error( $response ) ) {
			// Could not reach Flutterwave. Leave the order where it is and let the
			// webhook settle it rather than guessing at an outcome.
			$this->logger->notice( 'Transaction Requeried Failed. Awaiting Webhook Verification...' );
			return;
		}

		if ( ! isset( $this->event_handler ) ) {
			return;
		}

		if ( 'success' === ( $response->status ?? '' ) && isset( $response->data ) ) {
			$this->logger->notice( 'Transaction Requeried Successfully' );
			$this->event_handler->on_successful( $response->data );
			return;
		}

		$this->logger->notice( 'Transaction Requeried Failed' );
		$this->event_handler->on_failure( $response );
	}

	/**
	 * Ask Flutterwave for the current state of a transaction.
	 *
	 * @param string $tx_ref The merchant transaction reference.
	 *
	 * @return object|\WP_Error The decoded API envelope, or a WP_Error when
	 *                          Flutterwave could not be reached or understood.
	 *                          A WP_Error means "unknown", never "not paid".
	 */
	private function verify_by_reference( string $tx_ref ) {
		$url = $this->client::API_BASE_URL . '/' . $this->client::API_VERSION .
			'/transactions/verify_by_reference?tx_ref=' . rawurlencode( $tx_ref );

		$response = $this->client->request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_object( $body ) || ! isset( $body->status ) ) {
			return new \WP_Error(
				'flutterwave_unreadable_response',
				'Flutterwave returned a response that could not be read for ' . $tx_ref . '.'
			);
		}

		return $body;
	}

	/**
	 * Webhook Verification.
	 *
	 * @param string $event_type The event type.
	 * @param object $event_data The event data.
	 */
	public function webhook_verify( string $event_type, object $event_data ) {
		$this->logger->notice( 'Webhook Verification Started' );
		$this->logger->notice( 'Event Type: ' . $event_type );

		$event_type = strtolower( $event_type );

		if ( isset( $this->event_handler ) ) {
			$this->event_handler->on_webhook( $event_type, $event_data );
		}

		if ( method_exists( $this, 'requery_transaction' ) ) {
			$this->requery_transaction( $event_data->tx_ref );
		} else {
			$this->logger->notice( 'Webhook Verification Failed' );
		}
	}

	/**
	 * Cancel Payment.
	 *
	 * @param string $tx_ref This is the transaction ref to be cancelled.
	 *
	 * @return void
	 */
	public function cancel_payment( string $tx_ref ) {
		if ( ! isset( $this->event_handler ) ) {
			return;
		}

		// A cancellation arrives as a query parameter on a public endpoint, so it
		// is a claim rather than a fact. Confirm the real state with Flutterwave
		// before letting it move the order.
		$response = $this->verify_by_reference( $tx_ref );

		if ( is_wp_error( $response ) ) {
			$this->logger->notice(
				'Could not confirm the cancellation of ' . $tx_ref . ' with Flutterwave. Leaving the order unchanged.'
			);
			return;
		}

		if ( 'success' === ( $response->status ?? '' ) && 'successful' === ( $response->data->status ?? '' ) ) {
			// The customer was charged before or while cancelling. Honour the payment.
			$this->logger->notice( 'Transaction ' . $tx_ref . ' was reported cancelled but is successful on Flutterwave. Completing the order.' );
			$this->event_handler->on_successful( $response->data );
			return;
		}

		// Flutterwave has no successful transaction under this reference, so the
		// cancellation is genuine.
		$this->event_handler->on_cancel( $tx_ref );
	}
}
