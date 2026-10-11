<?php
/**
 * Class FLW_WC_Payment_Gateway_Notices
 *
 * @package    Flutterwave\WooCommerce
 * @subpackage FLW_WC_Payment_Gateway/includes/notices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flutterwave Payment Gateway Notices Class
 */
class FLW_WC_Payment_Gateway_Notices {

	/**
	 *  WooCommerce_not_installed
	 *
	 * @return void
	 */
	public function woocommerce_not_installed() {
		include_once dirname( FLW_WC_PLUGIN_FILE ) . '/includes/views/html-admin-missing-woocommerce.php';
	}

	/**
	 *  Woocommerce_wc_not_supported
	 *
	 * @return void
	 */
	public function woocommerce_wc_not_supported() {
		/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
		echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Flutterwave WooCommerce requires WooCommerce %1$s or greater to be installed and active. kindly upgrade to a higher version of WooCommerce or downgrade to a lower version of Flutterwave WooCommerce that supports WooCommerce version %2$s.', 'rave-woocommerce-payment-gateway' ), esc_attr( FLW_WC_MIN_WC_VER ), esc_attr( WC_VERSION ) ) . '</strong></p></div>';
	}
}
