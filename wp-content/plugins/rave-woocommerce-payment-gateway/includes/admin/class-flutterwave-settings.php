<?php
/**
 * Read/write model for the Flutterwave gateway settings.
 *
 * Everything still lives in the `woocommerce_rave_settings` option so the
 * gateway, the blocks integration and the legacy screen keep working unchanged.
 * This class is the translation layer between that flat option array and the
 * shape the admin React app works with.
 *
 * @package    Flutterwave/WooCommerce
 * @subpackage Flutterwave/WooCommerce/admin
 */

declare(strict_types=1);

namespace Flutterwave\WooCommerce\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings model.
 */
final class Flutterwave_Settings {

	/**
	 * REST namespace used by the admin app.
	 */
	const REST_NAMESPACE = 'flutterwave/v1';

	/**
	 * WooCommerce option key for the gateway.
	 */
	const OPTION_KEY = 'woocommerce_rave_settings';

	/**
	 * The secret hash older versions shipped as the form default:
	 * sha256( 'Rave-Secret-Hash' ). Webhooks are refused while it is set, so
	 * merchants have to replace it.
	 */
	const LEGACY_SECRET_HASH = 'a4a6e4c86fc1347a48eeab1171f7fea1a10eecbac223b86db3b3e3e134fefa40';

	/**
	 * Payment options that make up each checkbox in the "Payment methods" step.
	 *
	 * The first token of each group is what gets written when only that method
	 * is picked; the whole group is written for methods Flutterwave splits by
	 * country (mobile money). Reading back, a group counts as enabled when any
	 * of its tokens is present.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const METHOD_MAP = array(
		'card'         => array( 'card' ),
		'stablecoin'   => array( 'stablecoin' ),
		'banktransfer' => array( 'banktransfer', 'account' ),
		'mobilemoney'  => array(
			'mobilemoneyghana',
			'mobilemoneyfranco',
			'mobilemoneyrwanda',
			'mobilemoneyzambia',
			'mobilemoneyuganda',
			'mobilemoneytanzania',
			'mpesa',
		),
		'applepay'     => array( 'applepay' ),
		'googlepay'    => array( 'googlepay' ),
		'opay'         => array( 'opay' ),
	);

	/**
	 * Payment option tokens the new UI does not surface as checkboxes but that
	 * merchants may still have selected. Kept editable under "Advanced".
	 *
	 * @var array<int, string>
	 */
	private const ADVANCED_METHODS = array( 'ussd', 'qr', 'nqr', 'credit', 'barter' );

	/**
	 * Defaults applied when a key has never been saved.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'enabled'             => 'no',
			'title'               => 'Flutterwave',
			'description'         => 'Powered by Flutterwave: Accepts Mastercard, Visa, Verve, Discover, AMEX, Diners Club and Union Pay.',
			'test_public_key'     => '',
			'test_secret_key'     => '',
			'live_public_key'     => '',
			'live_secret_key'     => '',
			'payment_style'       => 'inline',
			'autocomplete_order'  => 'no',
			'payment_options'     => 'card,ussd,account,mpesa,banktransfer,mobilemoneyghana,mobilemoneyfranco,mobilemoneyrwanda,mobilemoneyzambia,mobilemoneyuganda',
			'go_live'             => 'no',
			'logging_option'      => 'no',
			'barter'              => 'no',
			'secret_hash'         => '',
			'onboarding_complete' => 'no',
		);
	}

	/**
	 * The raw stored option merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function raw(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * The payload the admin app consumes.
	 *
	 * @return array<string, mixed>
	 */
	public static function to_payload(): array {
		self::ensure_secret_hash();

		$settings  = self::raw();
		$onboarded = self::is_onboarded();

		// A store that has never been set up starts the wizard with empty
		// fields, so step one reads as something to fill in rather than
		// something already answered. The storage defaults still stand behind
		// them, so nothing downstream sees a blank.
		$title       = $onboarded ? (string) $settings['title'] : '';
		$description = $onboarded ? (string) $settings['description'] : '';
		$style       = 'redirect' === $settings['payment_style'] ? 'redirect' : 'inline';

		return array(
			'title'              => $title,
			'description'        => $description,
			'paymentStyle'       => $onboarded ? $style : '',
			'autocompleteOrder'  => 'yes' === $settings['autocomplete_order'],
			'testPublicKey'      => (string) $settings['test_public_key'],
			'testSecretKey'      => (string) $settings['test_secret_key'],
			'livePublicKey'      => (string) $settings['live_public_key'],
			'liveSecretKey'      => (string) $settings['live_secret_key'],
			'goLive'             => 'yes' === $settings['go_live'],
			'secretHash'         => (string) $settings['secret_hash'],
			'secretHashIsLegacy' => self::LEGACY_SECRET_HASH === $settings['secret_hash'],
			'disableLogging'     => 'yes' === $settings['logging_option'],
			'disableBarter'      => 'yes' === $settings['barter'],
			'enabled'            => 'yes' === $settings['enabled'],
			'paymentMethods'     => self::methods_from_option( (string) $settings['payment_options'] ),
			'advancedMethods'    => self::advanced_from_option( (string) $settings['payment_options'] ),
			'onboardingComplete' => self::is_onboarded(),
			'webhookUrl'         => self::webhook_url(),
		);
	}

	/**
	 * The webhook URL merchants paste into their Flutterwave dashboard.
	 *
	 * @return string
	 */
	public static function webhook_url(): string {
		if ( function_exists( 'WC' ) && WC() instanceof \WooCommerce ) {
			return WC()->api_request_url( 'Flw_WC_Payment_Webhook' );
		}

		return home_url( '/wc-api/Flw_WC_Payment_Webhook' );
	}

	/**
	 * Which UI payment-method checkboxes are on, given a stored option string.
	 *
	 * @param string $option_string Comma separated Flutterwave payment options.
	 *
	 * @return array<int, string>
	 */
	public static function methods_from_option( string $option_string ): array {
		$tokens = self::tokenize( $option_string );

		if ( empty( $tokens ) ) {
			return array();
		}

		$enabled = array();

		foreach ( self::METHOD_MAP as $key => $group ) {
			if ( ! empty( array_intersect( $group, $tokens ) ) ) {
				$enabled[] = $key;
			}
		}

		return $enabled;
	}

	/**
	 * Tokens the merchant has enabled that the checkbox list does not cover.
	 *
	 * @param string $option_string Comma separated Flutterwave payment options.
	 *
	 * @return array<int, string>
	 */
	public static function advanced_from_option( string $option_string ): array {
		$tokens = self::tokenize( $option_string );

		return array_values( array_intersect( self::ADVANCED_METHODS, $tokens ) );
	}

	/**
	 * Build the option string Flutterwave expects from the UI selection.
	 *
	 * @param array<int, string> $methods  Checkbox keys.
	 * @param array<int, string> $advanced Extra tokens to preserve.
	 *
	 * @return string
	 */
	public static function option_from_methods( array $methods, array $advanced = array() ): string {
		$tokens = array();

		foreach ( $methods as $method ) {
			if ( isset( self::METHOD_MAP[ $method ] ) ) {
				$tokens = array_merge( $tokens, self::METHOD_MAP[ $method ] );
			}
		}

		$tokens = array_merge( $tokens, array_intersect( self::ADVANCED_METHODS, $advanced ) );

		return implode( ',', array_values( array_unique( $tokens ) ) );
	}

	/**
	 * Split and normalise a comma separated option string.
	 *
	 * @param string $option_string Raw option value.
	 *
	 * @return array<int, string>
	 */
	private static function tokenize( string $option_string ): array {
		$parts = array_map( 'trim', explode( ',', $option_string ) );

		return array_values( array_filter( $parts, static fn( $part ) => '' !== $part ) );
	}

	/**
	 * Persist a partial update coming from the admin app.
	 *
	 * Only keys present in the request are touched, so each wizard step can
	 * save independently without clobbering the others.
	 *
	 * @param array<string, mixed> $patch Request body.
	 *
	 * @return array<string, mixed> The refreshed payload.
	 */
	public static function update( array $patch ): array {
		$settings = self::raw();

		$strings = array(
			'title'         => 'title',
			'description'   => 'description',
			'testPublicKey' => 'test_public_key',
			'testSecretKey' => 'test_secret_key',
			'livePublicKey' => 'live_public_key',
			'liveSecretKey' => 'live_secret_key',
			'secretHash'    => 'secret_hash',
		);

		foreach ( $strings as $incoming => $stored_key ) {
			if ( array_key_exists( $incoming, $patch ) ) {
				$settings[ $stored_key ] = sanitize_text_field( (string) $patch[ $incoming ] );
			}
		}

		$booleans = array(
			'autocompleteOrder' => 'autocomplete_order',
			'goLive'            => 'go_live',
			'disableLogging'    => 'logging_option',
			'disableBarter'     => 'barter',
			'enabled'           => 'enabled',
		);

		foreach ( $booleans as $incoming => $stored_key ) {
			if ( array_key_exists( $incoming, $patch ) ) {
				$settings[ $stored_key ] = filter_var( $patch[ $incoming ], FILTER_VALIDATE_BOOLEAN ) ? 'yes' : 'no';
			}
		}

		if ( array_key_exists( 'paymentStyle', $patch ) ) {
			$settings['payment_style'] = 'redirect' === $patch['paymentStyle'] ? 'redirect' : 'inline';
		}

		if ( array_key_exists( 'paymentMethods', $patch ) ) {
			$methods  = is_array( $patch['paymentMethods'] ) ? array_map( 'sanitize_key', $patch['paymentMethods'] ) : array();
			$advanced = array_key_exists( 'advancedMethods', $patch ) && is_array( $patch['advancedMethods'] )
				? array_map( 'sanitize_key', $patch['advancedMethods'] )
				: self::advanced_from_option( (string) $settings['payment_options'] );

			$settings['payment_options'] = self::option_from_methods( $methods, $advanced );
		}

		if ( array_key_exists( 'onboardingComplete', $patch ) ) {
			$settings['onboarding_complete'] = filter_var( $patch['onboardingComplete'], FILTER_VALIDATE_BOOLEAN ) ? 'yes' : 'no';
		}

		// Never store a blank hash, and never let the old default be saved back
		// in. One that is merely already stored is left for the merchant to
		// replace (the admin notice and the webhook handler insist on it), so
		// the new value is one they have seen and can copy to their dashboard.
		$hash = trim( (string) $settings['secret_hash'] );

		if ( '' === $hash || ( array_key_exists( 'secretHash', $patch ) && self::LEGACY_SECRET_HASH === $hash ) ) {
			$settings['secret_hash'] = self::generate_secret_hash();
		}

		update_option( self::OPTION_KEY, $settings );

		return self::to_payload();
	}

	/**
	 * Whether a stored secret hash may be used to authenticate webhooks.
	 * Blank and the old shared default both fail.
	 *
	 * @param string $hash Stored secret hash.
	 *
	 * @return bool
	 */
	public static function is_usable_secret_hash( string $hash ): bool {
		$hash = trim( $hash );

		return '' !== $hash && ! hash_equals( self::LEGACY_SECRET_HASH, $hash );
	}

	/**
	 * A random 64-character hex secret hash.
	 *
	 * @return string
	 */
	public static function generate_secret_hash(): string {
		return hash( 'sha256', wp_generate_password( 64, true, true ) );
	}

	/**
	 * Store a generated secret hash when none is set, so a new install shows
	 * a real value on the API & Webhook step before anything has been saved.
	 * Nothing points at a blank hash yet, so filling it in breaks nothing.
	 *
	 * @return void
	 */
	public static function ensure_secret_hash(): void {
		$settings = self::raw();

		if ( '' !== trim( (string) $settings['secret_hash'] ) ) {
			return;
		}

		$settings['secret_hash'] = self::generate_secret_hash();

		update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Whether the merchant has finished (or previously configured) setup.
	 *
	 * Merchants upgrading from an older version never ran the wizard but do
	 * have keys saved, so they are treated as already onboarded.
	 *
	 * @return bool
	 */
	public static function is_onboarded(): bool {
		$settings = self::raw();

		if ( 'yes' === $settings['onboarding_complete'] ) {
			return true;
		}

		return '' !== trim( (string) $settings['test_public_key'] ) || '' !== trim( (string) $settings['live_public_key'] );
	}
}
