<?php
/**
 * The file that defines the Flutterwave_WC_Gateway_Blocks_Support class
 *
 * A class that defines a block type
 *
 * @link       https://flutterwave.com
 * @since      2.3.2
 *
 * @package    Flutterwave\WooCommerce
 * @subpackage FLW_WC_Payment_Gateway/includes
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class Flutterwave_WC_Gateway_Blocks_Support
 *
 * @since 2.3.2
 * @extends AbstractPaymentMethodType
 * @package Flutterwave
 */
final class Flutterwave_WC_Gateway_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Name of the payment method.
	 *
	 * @var string
	 */
	protected $name = 'rave';

	/**
	 * Settings from the WP options table
	 *
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Initialize the Block.
	 *
	 * @inheritDoc
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_rave_settings', array() );

		if ( version_compare( WC_VERSION, '6.9.1', '<' ) ) {
			// For backwards compatibility.
			if ( ! class_exists( 'FLW_WC_Payment_Gateway' ) ) {
				require_once dirname( FLW_WC_PLUGIN_FILE ) . '/includes/class-flw-wc-payment-gateway.php';
			}
		}

		$this->gateway = new FLW_WC_Payment_Gateway();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active(): bool {
		if ( version_compare( WC_VERSION, '6.9.0', '>' ) ) {
			$gateways = WC()->payment_gateways->payment_gateways();

			if ( ! isset( $gateways[ $this->name ] ) ) {
				return false;
			}

			$this->gateway = $gateways[ $this->name ];
		}

		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features(): array {
		return $this->gateway->supports;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'flutterwave',
			'https://checkout.flutterwave.com/v3.js',
			array(),
			FLW_WC_VERSION,
			true
		);

		$asset_path   = dirname( FLW_WC_PLUGIN_FILE ) . '/build/index.asset.php';
		$version      = FLW_WC_VERSION;
		$dependencies = array();
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}
		wp_register_script(
			'wc-flutterwave-blocks',
			FLW_WC_URL . '/build/index.js',
			array_merge( array( 'flutterwave' ), $dependencies ),
			$version,
			true
		);
		wp_set_script_translations(
			'wc-flutterwave-blocks',
			'rave-woocommerce-payment-gateway'
		);

		return array(
			'wc-flutterwave-blocks',
		);
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		return array(
			'icons'       => $this->get_icons(),
			'supports'    => array_filter( $this->get_supported_features(), array( $this->gateway, 'supports' ) ),
			'isAdmin'     => is_admin(),
			'public_key'  => ( 'yes' === $this->settings['go_live'] ) ? $this->settings['live_public_key'] : $this->settings['test_public_key'],
			'asset_url'   => plugins_url( 'assets', FLW_WC_PLUGIN_FILE ),
			'title'       => $this->settings['title'],
			'description' => $this->settings['description'],
		);
	}

	/**
	 * Returns an array of icons for the payment method.
	 *
	 * @return array
	 */
	private function get_icons(): array {
		$icons_src = array(
			'visa'       => array(
				'src' => dirname( FLW_WC_PLUGIN_FILE ) . '/assets/img/visa.svg',
				'alt' => __( 'Visa', 'rave-woocommerce-payment-gateway' ),
			),
			'amex'       => array(
				'src' => dirname( FLW_WC_PLUGIN_FILE ) . '/assets/img/amex.svg',
				'alt' => __( 'American Express', 'rave-woocommerce-payment-gateway' ),
			),
			'mastercard' => array(
				'src' => dirname( FLW_WC_PLUGIN_FILE ) . '/assets/img/mastercard.svg',
				'alt' => __( 'Mastercard', 'rave-woocommerce-payment-gateway' ),
			),
		);

		if ( 'USD' === get_woocommerce_currency() ) {
			$icons_src['discover'] = array(
				'src' => dirname( FLW_WC_PLUGIN_FILE ) . '/assets/img/discover.svg',
				'alt' => _x( 'Discover', 'Name of credit card', 'rave-woocommerce-payment-gateway' ),
			);
			$icons_src['jcb']      = array(
				'src' => dirname( FLW_WC_PLUGIN_FILE ) . '/assets/img/jcb.svg',
				'alt' => __( 'JCB', 'rave-woocommerce-payment-gateway' ),
			);
			$icons_src['diners']   = array(
				'src' => dirname( FLW_WC_PLUGIN_FILE ) . '/assets/img/diners.svg',
				'alt' => __( 'Diners', 'rave-woocommerce-payment-gateway' ),
			);
		}
		return $icons_src;
	}
}
