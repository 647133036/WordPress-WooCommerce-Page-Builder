<?php
/**
 * The file that defines the Flutterwave Subscriptions class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://flutterwave.com
 * @since      1.0.0
 *
 * @package    Flutterwave_WooCommerce
 * @subpackage Flutterwave_WooCommerce/includes
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FLUTTERWAVEACCESS' ) ) {
	exit;
}

require_once FLW_WC_DIR_PATH . 'includes/util/class-flutterwave-crypto.php';

/**
 *  Flutterwave Subscription Class.
 */
class FLW_WC_Payment_Gateway_Subscriptions extends FLW_WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();
		// https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/ .
		// https://woocommerce.com/document/subscriptions/develop/functions/order-cart-functions/#section-9 .
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		}
	}

	/**
	 * Check if an order contains a subscription
	 *
	 * @param WC_Order $order The order.
	 */
	public function order_contains_subscription( WC_Order $order ): bool {
		return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order ) || wcs_order_contains_renewal( $order ) );
	}

	/**
	 * Process a trial subscription order with 0 total.
	 *
	 * @param int $order_id The order ID.
	 */
	public function process_payment( $order_id ): array {

		$order = wc_get_order( $order_id );
		// Check for trial subscription order with 0 total.
		if ( $this->order_contains_subscription( $order ) && $order->get_total() === 0 ) {

			$order->payment_complete();
			$order->add_order_note( 'This subscription has a free trial, reason for the 0 amount' );
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		} else {

			return parent::process_payment( $order_id );

		}
	}

	/**
	 * Process a subscription renewal.
	 *
	 * @param float    $amount_to_charge The amount to charge.
	 * @param WC_Order $renewal_order The order object.
	 */
	public function scheduled_subscription_payment( float $amount_to_charge, WC_Order $renewal_order ) {

		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {
			$renewal_order->update_status( 'failed', sprintf( 'Rave Transaction Failed: (%s)', $response->get_error_message() ) );
		}
	}

	/**
	 * Process a subscription renewal payment.
	 *
	 * @param WC_Order $order The order object.
	 * @param float    $amount The amount to charge.
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {

		$order_id = $order->get_id();
		// get token attached for this subscription id.
		$auth_code = \Flutterwave\WooCommerce\Util\Flutterwave_Crypto::decrypt( (string) $order->get_meta( '_rave_wc_token', true ) );
		if ( $auth_code ) {

			$headers = array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->get_secret_key(),
			);

			$txnref         = 'WC_' . $order_id . '_' . time();
			$order_currency = $order->get_currency();
			$first_name     = $order->get_billing_first_name();
			$last_name      = $order->get_billing_last_name();
			$email          = $order->get_billing_email();

			if ( strpos( $auth_code, '##' ) !== false ) {

				$payment_token = explode( '##', $auth_code );
				$token_code    = $payment_token[0];

			} else {

				$token_code = $auth_code;

			}

			$body = array(
				'token'     => $token_code,
				'currency'  => $order_currency,
				'amount'    => $amount,
				'email'     => $email,
				'firstname' => $first_name,
				'lastname'  => $last_name,
				'tx_ref'    => $txnref,
			);

			$args = array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			);

			// tokenize url.
			$tokenized_url = 'https://api.flutterwave.com/v3/tokenized-charges';
			$request       = wp_remote_post( $tokenized_url, $args );

			if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {

				$response         = json_decode( wp_remote_retrieve_body( $request ) );
				$status           = $response->status;
				$response_status  = $response->data->status;
				$payment_currency = $response->data->currency;

				if ( 'success' === $status && 'successful' === $response_status && $payment_currency === $order_currency ) {
					$txn_ref        = $response->data->tx_ref;
					$payment_ref    = $response->data->flw_ref;
					$amount_charged = $response->data->charged_amount;

					$order->payment_complete( $order_id );
					$order->add_order_note(
						sprintf(
							/* translators: 1: payment reference 2: transaction reference */
							__( 'Payment via Flutterwave successful (Payment Reference: %1$s, Transaction Reference: %2$s)', 'rave-woocommerce-payment-gateway' ),
							$payment_ref,
							$txn_ref
						)
					);

					$flw_settings = get_option( 'woocommerce_' . $order->get_payment_method() . '_settings' );

					if ( isset( $flw_settings['autocomplete_order'] ) && 'yes' === $flw_settings['autocomplete_order'] ) {
						$order->update_status( 'completed' );
					}
					return true;
				} else {

					return new WP_Error( 'flutterwave_error', 'Flutterwave payment failed. ' . $response->message );

				}
			}
		}

		return new WP_Error( 'flutterwave_error', 'This subscription can\'t be renewed automatically. The customer will have to login to his account to renew his subscription' );
	}
}
