/**
 * Per-step validation. Each function returns a map of field key to message;
 * an empty object means the step is complete.
 */

import { __ } from '@wordpress/i18n';

const blank = ( value ) => ! String( value || '' ).trim();

/**
 * Step 1 needs a checkout title and a checkout style.
 *
 * @param {Object} values Form values.
 * @return {Object} Field errors.
 */
export const validateGeneral = ( values ) => {
	const errors = {};

	if ( blank( values.title ) ) {
		errors.title = __( 'Enter a title shoppers will see at checkout.', 'rave-woocommerce-payment-gateway' );
	}

	if ( blank( values.paymentStyle ) ) {
		errors.paymentStyle = __( 'Choose how the payment interface opens.', 'rave-woocommerce-payment-gateway' );
	}

	return errors;
};

/**
 * Step 2 needs the key pair for whichever mode is active. Live mode is checked
 * only when the merchant has switched it on, so test-only setups still pass.
 *
 * @param {Object} values Form values.
 * @return {Object} Field errors.
 */
export const validateApi = ( values ) => {
	const errors = {};

	if ( blank( values.testPublicKey ) ) {
		errors.testPublicKey = __( 'Enter your test public key.', 'rave-woocommerce-payment-gateway' );
	}

	if ( blank( values.testSecretKey ) ) {
		errors.testSecretKey = __( 'Enter your test private key.', 'rave-woocommerce-payment-gateway' );
	}

	if ( values.goLive ) {
		if ( blank( values.livePublicKey ) ) {
			errors.livePublicKey = __(
				'Live transactions are enabled, so a live public key is required.',
				'rave-woocommerce-payment-gateway'
			);
		}

		if ( blank( values.liveSecretKey ) ) {
			errors.liveSecretKey = __(
				'Live transactions are enabled, so a live private key is required.',
				'rave-woocommerce-payment-gateway'
			);
		}
	}

	return errors;
};

/**
 * Step 3 needs at least one method, otherwise checkout would offer nothing.
 *
 * @param {Object} values Form values.
 * @return {Object} Field errors.
 */
export const validateMethods = ( values ) => {
	const errors = {};
	const selected = ( values.paymentMethods || [] ).length + ( values.advancedMethods || [] ).length;

	if ( 0 === selected ) {
		errors.paymentMethods = __( 'Select at least one payment method.', 'rave-woocommerce-payment-gateway' );
	}

	return errors;
};
