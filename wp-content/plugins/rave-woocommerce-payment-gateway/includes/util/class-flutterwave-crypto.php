<?php
/**
 * At-rest encryption for charge-capable secrets.
 *
 * The Flutterwave card token stored against a subscription can initiate charges
 * on its own. Held as plaintext in order meta it is readable by any other plugin
 * with meta access, and it falls out of any SQL injection elsewhere on the site.
 * Encrypting it means a meta dump alone is not enough to charge a customer.
 *
 * The key is derived from the site's WordPress salts, so it lives in wp-config.php
 * rather than the database. Rotating those salts invalidates stored tokens: the
 * decrypt path fails closed and the affected subscription falls back to asking the
 * customer to renew manually, which is the safe outcome.
 *
 * @package    Flutterwave/WooCommerce/util
 * @since      3.3.1
 */

declare(strict_types=1);

namespace Flutterwave\WooCommerce\Util;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-flutterwave-logger.php';

/**
 * Symmetric encryption helper for stored payment secrets.
 */
final class Flutterwave_Crypto {

	/**
	 * Marker identifying values written by this class.
	 *
	 * Values without it are legacy plaintext and are returned as-is on read.
	 *
	 * @var string
	 */
	private const PREFIX = 'flwenc:v1:';

	/**
	 * Cipher used for stored secrets.
	 *
	 * @var string
	 */
	private const CIPHER = 'aes-256-gcm';

	/**
	 * Authentication tag length in bytes.
	 *
	 * @var int
	 */
	private const TAG_LENGTH = 16;

	/**
	 * Encrypt a secret for storage.
	 *
	 * Falls back to returning the plaintext when OpenSSL is unavailable, so a
	 * misconfigured host degrades to today's behaviour instead of losing the
	 * token and silently breaking renewals.
	 *
	 * @param string $plaintext The value to protect.
	 *
	 * @return string The stored representation.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext || ! self::is_available() ) {
			return $plaintext;
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $iv_length || $iv_length <= 0 ) {
			return $plaintext;
		}

		try {
			$iv = random_bytes( $iv_length );
		} catch ( \Exception $e ) {
			Flutterwave_Logger::instance()->error( 'Unable to generate an IV for token encryption; storing the token unencrypted.' );
			return $plaintext;
		}

		$tag        = '';
		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH );

		if ( false === $ciphertext ) {
			Flutterwave_Logger::instance()->error( 'Token encryption failed; storing the token unencrypted.' );
			return $plaintext;
		}

		return self::PREFIX . base64_encode( $iv . $tag . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext needs a text-safe encoding for meta storage.
	}

	/**
	 * Decrypt a stored secret.
	 *
	 * @param string $stored The stored representation.
	 *
	 * @return string The plaintext, or an empty string when it cannot be recovered.
	 */
	public static function decrypt( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}

		if ( 0 !== strpos( $stored, self::PREFIX ) ) {
			// Written before this plugin encrypted tokens.
			return $stored;
		}

		if ( ! self::is_available() ) {
			Flutterwave_Logger::instance()->error( 'OpenSSL is unavailable, so the stored payment token cannot be decrypted.' );
			return '';
		}

		$payload   = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reversing our own storage encoding.
		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $payload || false === $iv_length || strlen( $payload ) <= $iv_length + self::TAG_LENGTH ) {
			Flutterwave_Logger::instance()->error( 'Stored payment token is malformed and could not be decrypted.' );
			return '';
		}

		$iv         = substr( $payload, 0, $iv_length );
		$tag        = substr( $payload, $iv_length, self::TAG_LENGTH );
		$ciphertext = substr( $payload, $iv_length + self::TAG_LENGTH );

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $plaintext ) {
			// Most likely cause is a WordPress salt rotation since the token was stored.
			Flutterwave_Logger::instance()->error( 'Stored payment token failed authentication and was discarded. Site salts may have been rotated.' );
			return '';
		}

		return $plaintext;
	}

	/**
	 * Whether the platform can encrypt at all.
	 *
	 * @return bool
	 */
	private static function is_available(): bool {
		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'openssl_cipher_iv_length' )
			&& in_array( self::CIPHER, openssl_get_cipher_methods(), true );
	}

	/**
	 * Derive the encryption key from the site's salts.
	 *
	 * @return string A 32 byte key.
	 */
	private static function key(): string {
		return hash_hkdf( 'sha256', wp_salt( 'secure_auth' ), 32, 'flutterwave-woocommerce-payment-token' );
	}
}
