<?php
/**
 * The client-specific functionality of the plugin.
 *
 * @link       https://flutterwave.com
 * @since      2.3.2
 * @interface  FLW_WC_Payment_Gateway_Event_Handler_Interface
 * @package    Flutterwave\WooCommerce\Contracts
 * @subpackage FLW_WC_Payment_Gateway/includes
 */

namespace Flutterwave\WooCommerce\Contracts;

interface FLW_WC_Payment_Gateway_Event_Handler_Interface {

	/**
	 * This is called when a transaction is initialized
	 *
	 * @param object $initialization_data This is the initial transaction data as passed.
	 * */
	public function on_init( object $initialization_data );

	/**
	 * This is called only when a transaction is successful
	 *
	 * @param object $transaction_data This is the transaction data as returned from the Rave payment. gateway.
	 * */
	public function on_successful( object $transaction_data );

	/**
	 * This is called only when a transaction failed
	 *
	 * @param object $transaction_data This is the transaction data as returned from the Rave payment gateway.
	 * */
	public function on_failure( object $transaction_data );

	/**
	 * This is called when a transaction is requeryed from the payment gateway
	 *
	 * @param string $transaction_reference This is the transaction reference as returned from the Rave payment gateway.
	 * */
	public function on_requery( string $transaction_reference );

	/**
	 * This is called a transaction requery returns with an error
	 *
	 * @param string $requery_response This is the error response gotten from the Rave payment gateway requery call.
	 * */
	public function on_requery_error( $requery_response );

	/**
	 * This is called when a transaction is canceled by the user
	 *
	 * @param string $transaction_reference This is the transaction reference as returned from the Rave payment gateway.
	 * */
	public function on_cancel( string $transaction_reference );

	/**
	 * This is called when a transaction doesn't return with a success or a failure response.
	 *
	 * @param string $transaction_reference This is the transaction reference as returned from the Rave payment gateway.
	 * @param object $data This is the data returned from the requery call.
	 * */
	public function on_timeout( string $transaction_reference, object $data );

	/**
	 * This is called when a webhook is received
	 *
	 * @param string $event_type This is the event type as returned from the Rave payment gateway.
	 * @param object $event_data This is the event data as returned from the Rave payment gateway.
	 * */
	public function on_webhook( string $event_type, object $event_data );
}
