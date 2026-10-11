<?php
/**
 * Authentication and binding for the public payment callback endpoint.
 *
 * The gateway return URL (`?wc-api=flw_wc_payment_gateway`) is unauthenticated by
 * design - the customer arrives there from Flutterwave, not from a logged-in
 * session. Everything that makes that endpoint safe therefore has to come from
 * the request itself, so this class does two things:
 *
 * 1. Authenticates the caller against a specific order using the order key, the
 *    same secret the checkout (pay) page already relies on.
 * 2. Binds the request to a transaction reference this store actually issued for
 *    that order, so a reference belonging to another order cannot be replayed.
 *
 * @package    Flutterwave/WooCommerce/util
 * @since      3.3.1
 */

declare(strict_types=1);

namespace Flutterwave\WooCommerce\Util;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-flutterwave-logger.php';

/**
 * Guards the gateway return URL.
 */
final class Flutterwave_Callback {

	/**
	 * Query var carrying the order key on the gateway return URL.
	 *
	 * @var string
	 */
	public const ORDER_KEY_VAR = 'flw_order_key';

	/**
	 * Meta key holding every transaction reference issued for an order.
	 *
	 * @var string
	 */
	public const REFS_META_KEY = '_flw_payment_txn_refs';

	/**
	 * Meta key holding the most recent transaction reference issued for an order.
	 *
	 * Kept for backwards compatibility with anything already reading it.
	 *
	 * @var string
	 */
	public const REF_META_KEY = '_flw_payment_txn_ref';

	/**
	 * How many transaction references to remember per order.
	 *
	 * A customer may re-open the checkout modal several times, and each attempt
	 * mints a new reference, so more than one has to stay valid.
	 *
	 * @var int
	 */
	private const MAX_REMEMBERED_REFS = 10;

	/**
	 * Order statuses the callback is allowed to transition away from.
	 *
	 * Anything else is already settled and must not be reopened by a request
	 * arriving on a public endpoint.
	 *
	 * @var string[]
	 */
	private const AWAITING_PAYMENT_STATUSES = array( 'pending', 'failed', 'on-hold', 'checkout-draft' );

	/**
	 * Build the gateway return URL for an order.
	 *
	 * `add_query_arg()` url-encodes values and merges into an existing query
	 * string correctly, which
	 * matters because `WC()->api_request_url()` returns `/?wc-api=...` on sites
	 * that do not use pretty permalinks. Hand-rolling the URL there is what
	 * previously dropped the callback's parameters on those sites.
	 *
	 * @param \WC_Order $order The order being paid for.
	 *
	 * @return string
	 */
	public static function build_url( \WC_Order $order ): string {
		return add_query_arg(
			array(
				'order_id'          => $order->get_id(),
				self::ORDER_KEY_VAR => (string) $order->get_order_key(),
			),
			\WC()->api_request_url( 'FLW_WC_Payment_Gateway' )
		);
	}

	/**
	 * Read a parameter from the callback request.
	 *
	 * Flutterwave returns the customer over GET, but the endpoint has always
	 * accepted POST as well, so both are checked.
	 *
	 * @param string $key Parameter name.
	 *
	 * @return string Empty string when absent or non-scalar.
	 */
	public static function param( string $key ): string {
		// phpcs:disable WordPress.Security.NonceVerification -- Public gateway return URL: the customer arrives from Flutterwave with no session, so there is no nonce to verify. resolve_order() authenticates the request against the order key instead.
		if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}

		if ( isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		return '';
	}

	/**
	 * Resolve and authenticate the order a callback request refers to.
	 *
	 * The caller must present the order key. Without it there is nothing tying
	 * the request to the customer who placed the order, and order IDs are
	 * sequential, so anyone could otherwise address any order on the store.
	 *
	 * @param string $txn_ref Transaction reference supplied by the caller.
	 *
	 * @return \WC_Order|null The order, or null when the request cannot be trusted.
	 */
	public static function resolve_order( string $txn_ref ): ?\WC_Order {
		$logger   = Flutterwave_Logger::instance();
		$order_id = self::requested_order_id( $txn_ref );

		if ( $order_id <= 0 ) {
			$logger->info( 'Payment callback rejected: no usable order reference in the request.' );
			return null;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			$logger->info( 'Payment callback rejected: no order found for the supplied reference.' );
			return null;
		}

		$supplied_key = self::param( self::ORDER_KEY_VAR );
		$expected_key = (string) $order->get_order_key();

		if ( '' === $supplied_key || '' === $expected_key || ! hash_equals( $expected_key, $supplied_key ) ) {
			$logger->info( 'Payment callback rejected: missing or invalid order key for order ' . $order_id . '.' );
			return null;
		}

		return $order;
	}

	/**
	 * Work out which order the caller is addressing.
	 *
	 * Both sources are attacker controlled; the value is only a lookup, and
	 * resolve_order() is what actually authorises access to the order found.
	 *
	 * @param string $txn_ref Transaction reference supplied by the caller.
	 *
	 * @return int Zero when no order ID can be determined.
	 */
	private static function requested_order_id( string $txn_ref ): int {
		$explicit = absint( self::param( 'order_id' ) );

		if ( $explicit > 0 ) {
			return $explicit;
		}

		// Legacy shape: WOOC_<order id>_<timestamp>.
		$parts = explode( '_', $txn_ref );

		return isset( $parts[1] ) ? absint( $parts[1] ) : 0;
	}

	/**
	 * Remember a transaction reference issued for an order.
	 *
	 * Uses the CRUD API rather than update_post_meta() so the plugin keeps
	 * working under High-Performance Order Storage, which it declares support for.
	 *
	 * @param \WC_Order $order   The order the reference was issued for.
	 * @param string    $txn_ref The transaction reference.
	 *
	 * @return void
	 */
	public static function record_txn_ref( \WC_Order $order, string $txn_ref ): void {
		if ( '' === $txn_ref ) {
			return;
		}

		$refs = $order->get_meta( self::REFS_META_KEY, true );
		$refs = is_array( $refs ) ? $refs : array();

		if ( ! in_array( $txn_ref, $refs, true ) ) {
			$refs[] = $txn_ref;
		}

		$order->update_meta_data( self::REFS_META_KEY, array_slice( $refs, -self::MAX_REMEMBERED_REFS ) );
		$order->update_meta_data( self::REF_META_KEY, $txn_ref );
		$order->save();
	}

	/**
	 * Check that a transaction reference was issued by this store for this order.
	 *
	 * This is a binding check, not an authentication one - `WOOC_<id>_<time()>`
	 * is guessable, so it is only meaningful once resolve_order() has already
	 * authenticated the caller. What it adds is that a reference belonging to a
	 * *different* order cannot be presented against this one.
	 *
	 * @param \WC_Order $order   The authenticated order.
	 * @param string    $txn_ref The transaction reference supplied by the caller.
	 *
	 * @return bool
	 */
	public static function txn_ref_belongs_to_order( \WC_Order $order, string $txn_ref ): bool {
		if ( '' === $txn_ref ) {
			return false;
		}

		$refs = $order->get_meta( self::REFS_META_KEY, true );
		$refs = is_array( $refs ) ? $refs : array();

		if ( empty( $refs ) ) {
			$legacy = (string) $order->get_meta( self::REF_META_KEY, true );

			if ( '' === $legacy ) {
				// Order was started before this plugin recorded references, so
				// there is nothing stored to match. Fall back to the shape the
				// store has always issued, WOOC_<order id>_<suffix>, which at
				// least stops a reference minted for another order from being
				// presented against this one.
				if ( self::txn_ref_names_order( $order, $txn_ref ) ) {
					Flutterwave_Logger::instance()->info(
						'Order ' . $order->get_id() . ' has no recorded transaction reference; accepted ' . $txn_ref . ' because it was issued in this order\'s name.'
					);
					return true;
				}

				Flutterwave_Logger::instance()->info(
					'Transaction reference rejected: order ' . $order->get_id() . ' has no recorded reference and ' . $txn_ref . ' was not issued in its name.'
				);
				return false;
			}

			$refs = array( $legacy );
		}

		foreach ( $refs as $known ) {
			if ( is_string( $known ) && hash_equals( $known, $txn_ref ) ) {
				return true;
			}
		}

		Flutterwave_Logger::instance()->info(
			'Payment callback rejected: transaction reference was not issued for order ' . $order->get_id() . '.'
		);

		return false;
	}

	/**
	 * Whether an order is still waiting to be paid.
	 *
	 * Anything outside this set has already been settled, so a callback must
	 * leave it alone. This also makes repeated callbacks idempotent.
	 *
	 * @param \WC_Order $order The order to check.
	 *
	 * @return bool
	 */
	public static function order_awaiting_payment( \WC_Order $order ): bool {
		return in_array( $order->get_status(), self::AWAITING_PAYMENT_STATUSES, true );
	}

	/**
	 * Whether a webhook may reopen a cancelled order to record a charge.
	 *
	 * A customer can cancel while a charge is still pending on Flutterwave, which
	 * later completes. Refusing that webhook leaves the customer charged with
	 * nothing fulfilled. This only decides whether the order is a candidate: the
	 * webhook still requeries Flutterwave before completing it, so a forged or
	 * stale "successful" status cannot mark the order paid on its own.
	 *
	 * @param \WC_Order $order      The order the webhook refers to.
	 * @param object    $event_data The `data` object of the webhook event.
	 * @param string    $gateway_id The Flutterwave gateway id.
	 *
	 * @return bool
	 */
	public static function may_recover_cancelled_order( \WC_Order $order, object $event_data, string $gateway_id ): bool {
		if ( 'cancelled' !== $order->get_status() ) {
			return false;
		}

		if ( 'successful' !== strtolower( (string) ( $event_data->status ?? '' ) ) ) {
			return false;
		}

		if ( $gateway_id !== $order->get_payment_method() ) {
			return false;
		}

		return self::txn_ref_belongs_to_order( $order, (string) ( $event_data->tx_ref ?? '' ) );
	}

	/**
	 * Whether a reference has the WOOC_<order id>_<suffix> shape for this order.
	 *
	 * @param \WC_Order $order   The order.
	 * @param string    $txn_ref The transaction reference.
	 *
	 * @return bool
	 */
	private static function txn_ref_names_order( \WC_Order $order, string $txn_ref ): bool {
		$parts = explode( '_', $txn_ref, 3 );

		return 3 === count( $parts )
			&& 'WOOC' === $parts[0]
			&& ctype_digit( $parts[1] )
			&& (int) $parts[1] === (int) $order->get_id()
			&& '' !== $parts[2];
	}
}
