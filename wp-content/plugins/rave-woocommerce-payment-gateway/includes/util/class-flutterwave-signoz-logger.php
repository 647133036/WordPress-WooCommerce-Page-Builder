<?php
/**
 * SigNoz observability service integration.
 *
 * Sends integration events (app.created, request.sent, app.transaction, app.error)
 * to the Flutterwave SigNoz service for developer analytics and TTFS/TTGL tracking.
 *
 * Events are dispatched asynchronously via WooCommerce's Action Scheduler so
 * the shopper's request never waits on the SigNoz service. The background
 * worker applies a health gate + circuit breaker, so failures are contained
 * and never surface to users. The only synchronous call is app.created,
 * which needs the response body (backend-generated app_id) and only runs
 * from the admin settings screen.
 *
 * @class          Flutterwave_Signoz_Logger
 * @version        2.0.0
 * @package    Flutterwave/WooCommerce
 * @subpackage Flutterwave/WooCommerce/util
 */

declare(strict_types=1);

namespace Flutterwave\WooCommerce\Util;

defined( 'ABSPATH' ) || exit;

/**
 * SigNoz Logger — sends observability events to the Flutterwave analytics service.
 *
 * @since 3.1.0
 */
final class Flutterwave_Signoz_Logger {

	const BASE_URL      = 'https://signozservice-prod.f4b-flutterwave.com';
	const MERCHANT_INFO = 'https://api.ravepay.co/flwv3-pug/getpaidx/api/mercinfo?PBFPubKey=';
	const API_KEY       = '';
	const LIBRARY       = 'WooCommerce';

	// --- Async dispatch (Action Scheduler) ---
	const SCHEDULED_SEND_HOOK = 'flw_signoz_send_event';
	const AS_GROUP            = 'flutterwave-signoz';

	// --- Health check ---
	const HEALTH_PATH      = '/health/ready';
	const HEALTH_CACHE_TTL = 60;   // Seconds a successful health check is trusted.

	// --- Circuit breaker ---
	const CB_FAILURE_THRESHOLD = 3;    // Consecutive failures before opening.
	const CB_OPEN_TTL          = 120;  // Seconds the circuit stays open (cooldown).
	const CB_FAILURES_KEY      = 'flw_signoz_cb_failures';
	const CB_OPEN_KEY          = 'flw_signoz_cb_open';
	const HEALTH_OK_KEY        = 'flw_signoz_health_ok';

	// --- Retry / backoff (503 only) ---
	const MAX_ATTEMPTS  = 3;     // Total attempts (1 initial + 2 retries).
	const BASE_DELAY_MS = 200;   // Backoff base.
	const MAX_DELAY_MS  = 1500;  // Per-retry delay cap.

	// --- Payload limits ---
	const ERROR_MESSAGE_MAX_LENGTH    = 4096;
	const ERROR_STACKTRACE_MAX_LENGTH = 16384;

	// --- Trace context ---
	const TRACE_CTX_TTL        = HOUR_IN_SECONDS; // Lifetime of a stored trace context.
	const TRACE_CTX_KEY_PREFIX = 'flw_signoz_trace_';

	/**
	 * Singleton instance.
	 *
	 * @var Flutterwave_Signoz_Logger|null
	 */
	private static ?self $instance = null;

	/**
	 * Backend-generated app identifier (from the app.created response).
	 *
	 * @var string
	 */
	private string $app_id = '';

	/**
	 * Merchant environment: "production" or "sandbox".
	 *
	 * @var string
	 */
	private string $environment = 'sandbox';

	/**
	 * In-process trace context registry (warm cache in front of transients).
	 *
	 * @var array<string, array>
	 */
	private array $trace_contexts_by_reference = array();

	/**
	 * Default trace context applied when none is registered for a reference.
	 *
	 * @var array|null
	 */
	private ?array $default_trace_context = null;

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
	 * Register the Action Scheduler callback for background event dispatch.
	 * Call once from the plugin bootstrap (e.g. on `init` or `plugins_loaded`).
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( self::SCHEDULED_SEND_HOOK, array( self::instance(), 'handle_scheduled_send' ), 10, 3 );
	}

	/**
	 * Get the current Flutterwave WooCommerce settings.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		$settings = get_option( 'woocommerce_rave_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Configure the logger environment and reset the registration flag.
	 * Must be called before any track_* methods.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->environment             = $this->get_current_environment();
		$new_options                   = $this->get_settings();
		$new_options['app_registered'] = false;
		update_option( 'woocommerce_rave_settings', $new_options );
	}

	/**
	 * Get the merchant's Flutterwave account name using the public key.
	 *
	 * @param string $public_key Merchant Flutterwave public key.
	 * @return string|null Account name or null on failure.
	 */
	public function get_merchant_id( string $public_key ): ?string {
		$settings = $this->get_settings();

		if ( ! empty( $settings['merchant_id'] ) && is_string( $settings['merchant_id'] ) ) {
			return $settings['merchant_id'];
		}

		try {
			$response = wp_remote_get(
				self::MERCHANT_INFO . rawurlencode( $public_key ),
				array(
					'timeout' => 2,
				)
			);

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( is_array( $body ) && ! empty( $body['mn'] ) && is_string( $body['mn'] ) ) {
				return $body['mn'];
			}
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}

		return null;
	}

	// --- Trace context ----------------------------------------------------

	/**
	 * Set the default trace context used when no reference-specific one exists.
	 *
	 * @param array|null $trace_context W3C-style trace context or null to clear.
	 * @return void
	 */
	public function set_default_trace_context( ?array $trace_context ): void {
		$this->default_trace_context = $trace_context;
	}

	/**
	 * Get the default trace context.
	 *
	 * @return array|null
	 */
	public function get_default_trace_context(): ?array {
		return $this->default_trace_context;
	}

	/**
	 * Register (or clear) the trace context for a transaction reference.
	 * Persisted via transients so it survives across requests
	 * (checkout -> redirect/webhook -> background worker).
	 *
	 * @param string     $reference     Transaction reference (tx_ref).
	 * @param array|null $trace_context Trace context, or null to delete.
	 * @return void
	 */
	public function set_trace_context_for_reference( string $reference, ?array $trace_context ): void {
		try {
			$key = $this->normalize_reference( $reference );

			if ( '' === $key ) {
				return;
			}

			if ( null === $trace_context ) {
				unset( $this->trace_contexts_by_reference[ $key ] );
				delete_transient( $this->trace_context_transient_key( $key ) );
				return;
			}

			$this->trace_contexts_by_reference[ $key ] = $trace_context;
			set_transient( $this->trace_context_transient_key( $key ), $trace_context, self::TRACE_CTX_TTL );
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}
	}

	/**
	 * Retrieve the trace context registered for a reference.
	 * Checks the in-process registry first, then the transient store.
	 *
	 * @param string $reference Transaction reference (tx_ref).
	 * @return array|null
	 */
	public function get_trace_context_for_reference( string $reference ): ?array {
		try {
			$key = $this->normalize_reference( $reference );

			if ( '' === $key ) {
				return null;
			}

			if ( isset( $this->trace_contexts_by_reference[ $key ] ) ) {
				return $this->trace_contexts_by_reference[ $key ];
			}

			$stored = get_transient( $this->trace_context_transient_key( $key ) );

			if ( is_array( $stored ) ) {
				$this->trace_contexts_by_reference[ $key ] = $stored; // Warm local registry.
				return $stored;
			}
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}

		return null;
	}

	/**
	 * Resolve the trace context for an event: explicit > default > by-reference.
	 * When no context is available for the reference, create a new root span.
	 * When a context already exists for the reference, create a child span
	 * under the prior one so the trace remains linked by tx_ref.
	 *
	 * @param array|null $explicit  Trace context passed directly by the caller.
	 * @param string     $reference Transaction reference (tx_ref).
	 * @return array|null
	 */
	private function resolve_trace_context( ?array $explicit, string $reference ): ?array {
		$parent_context = $explicit;
		if ( null === $parent_context ) {
			$parent_context = $this->default_trace_context;
		}
		if ( null === $parent_context ) {
			$parent_context = $this->get_trace_context_for_reference( $reference );
		}

		$context = $this->build_trace_context( $reference, $parent_context );
		$this->set_trace_context_for_reference( $reference, $context );
		return $context;
	}

	/**
	 * Build a trace context for a reference.
	 *
	 * @param string     $reference      Transaction reference (tx_ref).
	 * @param array|null $parent_context Optional parent trace context.
	 * @return array
	 */
	private function build_trace_context( string $reference, ?array $parent_context = null ): array {
		$trace_id = $this->generate_trace_id();
		$span_id  = $this->generate_span_id();
		$context  = array(
			'trace_id' => $trace_id,
			'span_id'  => $span_id,
		);

		if ( null !== $parent_context ) {
			$context['trace_id'] = $this->extract_trace_id( $parent_context ) ?? $trace_id;
			if ( '' !== ( $this->extract_span_id( $parent_context ) ?? '' ) ) {
				$context['parent_span_id'] = $this->extract_span_id( $parent_context );
			}
		}

		return $context;
	}

	/**
	 * Generate a unique hex trace id.
	 *
	 * @return string
	 */
	private function generate_trace_id(): string {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Throwable $e ) {
			unset( $e );
			return substr( md5( uniqid( '', true ) ), 0, 32 );
		}
	}

	/**
	 * Generate a unique hex span id.
	 *
	 * @return string
	 */
	private function generate_span_id(): string {
		try {
			return bin2hex( random_bytes( 8 ) );
		} catch ( \Throwable $e ) {
			unset( $e );
			return substr( md5( uniqid( '', true ) . microtime( true ) ), 0, 16 );
		}
	}

	/**
	 * Extract the trace id from a trace context array.
	 *
	 * @param array $context Trace context.
	 * @return string|null
	 */
	private function extract_trace_id( array $context ): ?string {
		if ( isset( $context['trace_id'] ) && is_string( $context['trace_id'] ) && '' !== trim( $context['trace_id'] ) ) {
			return $context['trace_id'];
		}

		if ( isset( $context['traceparent'] ) && is_string( $context['traceparent'] ) ) {
			$parts = explode( '-', $context['traceparent'] );
			return $parts[1] ?? null;
		}

		return null;
	}

	/**
	 * Extract the span id from a trace context array.
	 *
	 * @param array $context Trace context.
	 * @return string|null
	 */
	private function extract_span_id( array $context ): ?string {
		if ( isset( $context['span_id'] ) && is_string( $context['span_id'] ) && '' !== trim( $context['span_id'] ) ) {
			return $context['span_id'];
		}

		if ( isset( $context['traceparent'] ) && is_string( $context['traceparent'] ) ) {
			$parts = explode( '-', $context['traceparent'] );
			return $parts[2] ?? null;
		}

		return null;
	}

	/**
	 * Build the transient key for a normalized reference.
	 *
	 * @param string $normalized_reference Normalized transaction reference.
	 * @return string
	 */
	private function trace_context_transient_key( string $normalized_reference ): string {
		// Hash keeps the key within the 172-char transient name limit.
		return self::TRACE_CTX_KEY_PREFIX . md5( $normalized_reference );
	}

	// --- Events -------------------------------------------------------------

	/**
	 * Fire the `app.created` event and persist the backend-generated app_id.
	 * Should be called once when the merchant first configures their keys.
	 * Idempotent: skips the network call when an app_id is already stored.
	 *
	 * Intentionally synchronous: the response body carries the app_id, and
	 * this only runs from the admin settings screen, never during checkout.
	 *
	 * @param string $public_key Merchant Flutterwave public key.
	 * @return string|null The backend-generated app_id, or null when unavailable.
	 */
	public function track_app_created( string $public_key ): ?string {
		try {
			if ( empty( $public_key ) ) {
				return null;
			}

			// Already registered — reuse the stored app_id, don't re-create.
			$settings = $this->get_settings();
			if ( ! empty( $settings['app_id'] ) && is_string( $settings['app_id'] ) ) {
				$this->app_id = $this->normalize_app_id( $settings['app_id'] );
				return $this->app_id;
			}

			$response = $this->send_now(
				'app.created',
				array(
					'client_id'       => null,
					'public_key'      => $public_key,
					'library'         => self::LIBRARY,
					'library_version' => $this->library_version(),
				),
				gmdate( 'Y-m-d\TH:i:s.000\Z' )
			);

			if ( ! is_array( $response ) || empty( $response['app_id'] ) ) {
				return null;
			}

			$this->app_id = $this->normalize_app_id( (string) $response['app_id'] );
			$this->persist_app_id( $this->app_id );

			return $this->app_id;
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}

		return null;
	}

	/**
	 * Store the backend-generated app_id in the plugin settings so it
	 * survives across requests and is picked up by get_app_id().
	 *
	 * @param string $app_id Backend-generated app identifier.
	 * @return void
	 */
	private function persist_app_id( string $app_id ): void {
		$new_settings           = $this->get_settings();
		$new_settings['app_id'] = $app_id;
		update_option( 'woocommerce_rave_settings', $new_settings );
	}

	/**
	 * Mark App as Registered.
	 *
	 * @return void
	 */
	public function mark_app_registered(): void {
		$new_settings                   = $this->get_settings();
		$new_settings['app_registered'] = true;
		update_option( 'woocommerce_rave_settings', $new_settings );
	}

	/**
	 * Get an Application Identifier.
	 * Prefers the backend-generated app_id (from app.created), then the
	 * configured public key, then an anonymous marker, so a missing value
	 * can never fatal (e.g. during wp_head).
	 *
	 * @return string
	 */
	public function get_app_id(): string {
		if ( '' !== $this->app_id ) {
			return $this->app_id;
		}

		$settings = $this->get_settings();

		if ( ! empty( $settings['app_id'] ) && is_string( $settings['app_id'] ) ) {
			$this->app_id = $this->normalize_app_id( $settings['app_id'] );
			return $this->app_id;
		}

		if ( ! empty( $settings['public_key'] ) && is_string( $settings['public_key'] ) ) {
			return $this->normalize_app_id( $settings['public_key'] );
		}

		return 'unknown';
	}

	/**
	 * Get the current environment.
	 *
	 * @return string
	 */
	public function get_current_environment(): string {
		$settings = $this->get_settings();
		$go_live  = $settings['go_live'] ?? 'no';
		return 'yes' === $go_live ? 'production' : 'sandbox';
	}

	/**
	 * Fire the `request.sent` event when a payment request is initiated.
	 * Queued for background dispatch — never blocks the request.
	 *
	 * @param string     $method        Payment method (e.g. "card").
	 * @param string     $reference     Transaction reference (tx_ref).
	 * @param string     $path          Request path (e.g. "/v3/charges").
	 * @param mixed      $logger        Optional WC logger instance.
	 * @param array|null $trace_context Optional trace context override.
	 * @return void
	 */
	public function track_request_sent( string $method, string $reference, string $path, $logger = null, ?array $trace_context = null ): void {
		try {
			$safe_reference = $this->normalize_reference( $reference );

			$payload = array(
				'app_id'          => $this->get_app_id(),
				'environment'     => $this->get_current_environment(),
				'api_version'     => 'v3',
				'library'         => self::LIBRARY,
				'library_version' => $this->library_version(),
				'method'          => $method,
				'path'            => $path,
				'reference'       => $safe_reference,
			);

			$resolved_context = $this->resolve_trace_context( $trace_context, $safe_reference );
			if ( null !== $resolved_context ) {
				$payload['trace_context'] = $resolved_context;
			}

			$cache_key = 'flw_signoz_req_' . md5( $safe_reference );

			if ( get_transient( $cache_key ) ) {
				return; // Already sent recently.
			}

			set_transient( $cache_key, true, 300 ); // 5 minute TTL.

			if ( null !== $logger && is_callable( array( $logger, 'info' ) ) ) {
				$logger->info( 'request.sent: ' . wp_json_encode( $payload ) );
			}

			$this->queue_event( 'request.sent', $payload );
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}
	}

	/**
	 * Fire the `app.transaction` event after a successful payment.
	 * Queued for background dispatch — never blocks the request.
	 *
	 * @param string     $reference     Transaction reference (tx_ref).
	 * @param string     $currency      ISO 4217 currency code.
	 * @param float      $amount        Transaction amount.
	 * @param string     $method        Payment method (e.g. "card").
	 * @param float      $fee           Transaction fee.
	 * @param array|null $trace_context Optional trace context override.
	 * @return void
	 */
	public function track_transaction(
		string $reference,
		string $currency,
		float $amount,
		string $method,
		float $fee,
		?array $trace_context = null
	): void {
		try {
			$safe_reference    = $this->normalize_reference( $reference );
			$this->app_id      = $this->get_app_id();
			$this->environment = $this->get_current_environment();

			$payload = array(
				'app_id'    => $this->app_id,
				'reference' => $safe_reference,
				'library'   => self::LIBRARY,
				'currency'  => $currency,
				'amount'    => $amount,
				'fee'       => $fee,
				'method'    => $method,
			);

			$resolved_context = $this->resolve_trace_context( $trace_context, $safe_reference );
			if ( null !== $resolved_context ) {
				$payload['trace_context'] = $resolved_context;
			}

			$this->queue_event( 'app.transaction', $payload );
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}
	}

	/**
	 * Fire the `app.error` event when a payment fails.
	 * Queued for background dispatch — never blocks the request.
	 *
	 * @param string      $error_code    Short machine-readable error code.
	 * @param string      $error_message Human-readable error description.
	 * @param string      $reference     Optional transaction reference for trace correlation.
	 * @param array|null  $trace_context Optional trace context override.
	 * @param string|null $stack_trace   Optional stack trace (truncated before send).
	 * @return void
	 */
	public function track_error(
		string $error_code,
		string $error_message,
		string $reference = '',
		?array $trace_context = null,
		?string $stack_trace = null
	): void {
		try {
			$this->app_id      = $this->get_app_id();
			$this->environment = $this->get_current_environment();

			$payload = array(
				'app_id'          => $this->app_id,
				'library'         => self::LIBRARY,
				'library_version' => $this->library_version(),
				'error_code'      => $error_code,
				'error_message'   => $this->truncate_value( $error_message, self::ERROR_MESSAGE_MAX_LENGTH ),
			);

			if ( null !== $stack_trace && '' !== $stack_trace ) {
				$payload['error_stacktrace'] = $this->truncate_value( $stack_trace, self::ERROR_STACKTRACE_MAX_LENGTH );
			}

			$safe_reference = $this->normalize_reference( $reference );
			if ( '' !== $safe_reference ) {
				$payload['reference'] = $safe_reference;
			}

			$resolved_context = $this->resolve_trace_context( $trace_context, $safe_reference );
			if ( null !== $resolved_context ) {
				$payload['trace_context'] = $resolved_context;
			}

			$this->queue_event( 'app.error', $payload );
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}
	}

	// --- Async dispatch -------------------------------------------------------

	/**
	 * Queue an event for background dispatch via Action Scheduler.
	 * The timestamp is captured here (enqueue time), not when the worker runs,
	 * so events reflect when they actually happened.
	 * Falls back to a synchronous send if Action Scheduler is unavailable.
	 *
	 * @param string $event_name SigNoz event name.
	 * @param array  $data       Event payload.
	 * @return void
	 */
	private function queue_event( string $event_name, array $data ): void {
		$timestamp = gmdate( 'Y-m-d\TH:i:s.000\Z' );

		try {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action(
					self::SCHEDULED_SEND_HOOK,
					array( $event_name, $data, $timestamp ),
					self::AS_GROUP
				);
				return;
			}
		} catch ( \Throwable $e ) {
			// Fall through to synchronous send.
			unset( $e );
		}

		// Action Scheduler unavailable — send inline as a last resort.
		$this->send_now( $event_name, $data, $timestamp );
	}

	/**
	 * Action Scheduler callback: dispatch a queued event.
	 * Must be public so the hook can invoke it; never throws, so a failed
	 * send is not retried by Action Scheduler (the circuit breaker and
	 * 503 retry policy own that decision instead).
	 *
	 * @param string $event_name SigNoz event name.
	 * @param array  $data       Event payload.
	 * @param string $timestamp  ISO-8601 timestamp captured at enqueue time.
	 * @return void
	 */
	public function handle_scheduled_send( string $event_name, array $data, string $timestamp ): void {
		try {
			$this->send_now( $event_name, $data, $timestamp );
		} catch ( \Throwable $e ) {
			// Observability must never break payments — or the queue runner.
			unset( $e );
		}
	}

	// --- Transport: health gate + circuit breaker + retry -------------------

	/**
	 * Dispatch an event to the SigNoz service synchronously.
	 * Guarded by the circuit breaker and health gate; never throws.
	 *
	 * @param string $event_name SigNoz event name (e.g. "app.created").
	 * @param array  $data       Event payload.
	 * @param string $timestamp  ISO-8601 timestamp for the event.
	 * @return array|null Decoded JSON response body, or null when the event
	 *                    was dropped, failed, or returned a non-JSON body.
	 */
	private function send_now( string $event_name, array $data, string $timestamp ): ?array {
		try {
			// 1. Circuit breaker gate: if open, drop the event immediately.
			if ( $this->is_circuit_open() ) {
				return null;
			}

			// 2. Health gate: verify /health/ready (cached for HEALTH_CACHE_TTL).
			// When the circuit has just moved out of cooldown, this acts as
			// the half-open probe before real traffic resumes.
			if ( ! $this->is_service_healthy() ) {
				$this->record_failure();
				return null;
			}

			return $this->send_with_retry( $event_name, $data, $timestamp );
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}

		return null;
	}

	/**
	 * POST the event with bounded retries (503 only) and jittered backoff.
	 * Runs in the background worker (or admin context for app.created),
	 * so blocking requests and backoff sleeps never affect shoppers.
	 *
	 * @param string $event_name SigNoz event name.
	 * @param array  $data       Event payload.
	 * @param string $timestamp  ISO-8601 timestamp for the event.
	 * @return array|null Decoded JSON response body on success, otherwise null.
	 */
	private function send_with_retry( string $event_name, array $data, string $timestamp ): ?array {
		$body = array(
			'name'      => $event_name,
			'data'      => $data,
			'timestamp' => $timestamp,
		);

		$args = array(
			'headers'  => array(
				'Content-Type' => 'application/json',
			),
			'body'     => wp_json_encode( $body ),
			'blocking' => true,
			'timeout'  => 2,
		);

		for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
			$response = wp_remote_post( self::BASE_URL . '/events', $args );

			// Network/transport error: count as a failure, do not retry.
			if ( is_wp_error( $response ) ) {
				$this->record_failure();
				return null;
			}

			$status_code = (int) wp_remote_retrieve_response_code( $response );

			if ( $status_code >= 200 && $status_code < 300 ) {
				$this->record_success();

				$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
				return is_array( $decoded ) ? $decoded : null;
			}

			// Only 503 (service temporarily unavailable) is retried.
			if ( 503 === $status_code && $attempt < self::MAX_ATTEMPTS ) {
				$this->backoff_sleep( $attempt );
				continue;
			}

			$this->record_failure();
			return null;
		}

		// Exhausted retries (all 503s).
		$this->record_failure();
		return null;
	}

	/**
	 * Exponential backoff with full jitter:
	 * sleep for random(0, min(MAX_DELAY, BASE * 2^(attempt-1))).
	 *
	 * @param int $attempt Attempt number (1-indexed).
	 * @return void
	 */
	private function backoff_sleep( int $attempt ): void {
		$ceiling_ms = min(
			self::MAX_DELAY_MS,
			self::BASE_DELAY_MS * ( 2 ** ( $attempt - 1 ) )
		);

		try {
			$delay_ms = random_int( 0, $ceiling_ms );
		} catch ( \Throwable $e ) {
			$delay_ms = $ceiling_ms;
		}

		usleep( $delay_ms * 1000 );
	}

	/**
	 * GET /health/ready and require {"status":"ok"}.
	 * A passing check is cached so we don't probe on every event.
	 *
	 * @return bool
	 */
	private function is_service_healthy(): bool {
		// Trust a recent successful health check.
		if ( get_transient( self::HEALTH_OK_KEY ) ) {
			return true;
		}

		try {
			$response = wp_remote_get(
				self::BASE_URL . self::HEALTH_PATH,
				array(
					'timeout' => 1,
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			$result = json_decode( wp_remote_retrieve_body( $response ), true );

			$healthy = is_array( $result )
				&& isset( $result['status'] )
				&& 'ok' === $result['status'];

			if ( $healthy ) {
				set_transient( self::HEALTH_OK_KEY, true, self::HEALTH_CACHE_TTL );
			}

			return $healthy;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	// --- Circuit breaker state ----------------------------------------------

	/**
	 * Whether the circuit is currently open (cooling down).
	 *
	 * @return bool
	 */
	private function is_circuit_open(): bool {
		return (bool) get_transient( self::CB_OPEN_KEY );
	}

	/**
	 * Reset breaker state after a successful send.
	 *
	 * @return void
	 */
	private function record_success(): void {
		delete_transient( self::CB_FAILURES_KEY );
		delete_transient( self::CB_OPEN_KEY );
	}

	/**
	 * Count a failure; open the circuit at the threshold.
	 *
	 * @return void
	 */
	private function record_failure(): void {
		$failures = (int) get_transient( self::CB_FAILURES_KEY ) + 1;
		set_transient( self::CB_FAILURES_KEY, $failures, self::CB_OPEN_TTL * 2 );

		if ( $failures >= self::CB_FAILURE_THRESHOLD ) {
			$this->open_circuit();
		}
	}

	/**
	 * Open the circuit for CB_OPEN_TTL seconds and force a fresh health
	 * probe (half-open behavior) once the cooldown expires.
	 *
	 * @return void
	 */
	private function open_circuit(): void {
		set_transient( self::CB_OPEN_KEY, true, self::CB_OPEN_TTL );
		delete_transient( self::CB_FAILURES_KEY );

		// Invalidate the cached health status so the next attempt after
		// cooldown re-probes /health/ready.
		delete_transient( self::HEALTH_OK_KEY );
	}

	// --- Helpers --------------------------------------------------------------

	/**
	 * Replace whitespace runs with dashes so app IDs are URL/query safe.
	 *
	 * @param string $app_id Raw app identifier.
	 * @return string
	 */
	private function normalize_app_id( string $app_id ): string {
		$normalized = preg_replace( '/\s+/', '-', trim( $app_id ) );
		return null !== $normalized ? $normalized : $app_id;
	}

	/**
	 * Restrict references to [A-Za-z0-9_-] so cache keys and payloads are stable.
	 *
	 * @param string $reference Raw transaction reference.
	 * @return string
	 */
	private function normalize_reference( string $reference ): string {
		$normalized = preg_replace( '/[^A-Za-z0-9_-]+/', '-', trim( $reference ) );

		if ( null === $normalized ) {
			return $reference;
		}

		return trim( $normalized, '-' );
	}

	/**
	 * Truncate a value to a maximum length (multibyte safe).
	 *
	 * @param string $value      Value to truncate.
	 * @param int    $max_length Maximum length in characters.
	 * @return string
	 */
	private function truncate_value( string $value, int $max_length ): string {
		if ( mb_strlen( $value ) <= $max_length ) {
			return $value;
		}

		return mb_substr( $value, 0, $max_length );
	}

	/**
	 * Return the plugin version, falling back gracefully if the constant is not yet defined.
	 *
	 * @return string
	 */
	private function library_version(): string {
		return defined( 'FLW_WC_VERSION' ) ? FLW_WC_VERSION : '3.1.0';
	}
}
