/**
 * WooCommerce dependencies
 */
import { getSetting, WC_ } from '@woocommerce/settings';

export const getBlocksConfiguration = () => {
	const flutterwaveServerData = getSetting( 'rave_data', null );

	if ( ! flutterwaveServerData ) {
		throw new Error( 'Flutterwave initialization data is not available' );
	}

	return flutterwaveServerData;
};

/**
 * Creates a payment request using cart data from WooCommerce.
 *
 * @param {Object} flutterwave - The Flutterwave JS object.
 * @param {Object} cart - The cart data response from the store's AJAX API.
 *
 * @return {Object} A Flutterwave payment request.
 */
export const createPaymentRequestUsingCart = ( flutterwave, cart ) => {
	const options = {
		total: cart.order_data.total,
		currency: cart.order_data.currency,
		country: cart.order_data.country_code,
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: getBlocksConfiguration()?.checkout
			?.needs_payer_phone,
		requestShipping: !!cart.shipping_required,
		displayItems: cart.order_data.displayItems,
	};

	if ( options.country === 'PR' ) {
		options.country = 'US';
	}

	return flutterwave.paymentRequest( options );
};

/**
 * Updates the given PaymentRequest using the data in the cart object.
 *
 * @param {Object} paymentRequest  The payment request object.
 * @param {Object} cart  The cart data response from the store's AJAX API.
 */
export const updatePaymentRequestUsingCart = ( paymentRequest, cart ) => {
	const options = {
		total: cart.order_data.total,
		currency: cart.order_data.currency,
		displayItems: cart.order_data.displayItems,
	};

	paymentRequest.update( options );
};

/**
 * Returns the Flutterwave public key
 *
 * @throws Error
 * @return {string} The public api key for the Flutterwave payment method.
 */
export const getPublicKey = () => {
	const public_key = getBlocksConfiguration()?.public_key;
	if ( ! public_key ) {
		throw new Error(
			'There is no public key available for Flutterwave. Make sure it is available on the wc.flutterwave_data.public_key property.'
		);
	}
	return public_key;
};

