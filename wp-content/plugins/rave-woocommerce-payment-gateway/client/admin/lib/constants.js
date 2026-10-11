/**
 * Shared copy and option lists for the admin app.
 */

import { __ } from '@wordpress/i18n';

export const STEPS = [
	{ key: 'general', label: __( 'General details', 'rave-woocommerce-payment-gateway' ) },
	{ key: 'api', label: __( 'API & webhook', 'rave-woocommerce-payment-gateway' ) },
	{ key: 'methods', label: __( 'Payment methods', 'rave-woocommerce-payment-gateway' ) },
];

export const CHECKOUT_OPTIONS = [
	{
		value: 'inline',
		label: __( 'Open payment interface in a pop-up.', 'rave-woocommerce-payment-gateway' ),
	},
	{
		value: 'redirect',
		label: __( 'Redirect users to the payment page in a new tab.', 'rave-woocommerce-payment-gateway' ),
	},
];

/**
 * The checkbox list on the "Payment methods" step. `key` maps to the groups
 * declared in Flutterwave_Settings::METHOD_MAP on the PHP side.
 */
export const PAYMENT_METHODS = [
	{
		key: 'card',
		label: __( 'Cards (credit/debit cards)', 'rave-woocommerce-payment-gateway' ),
		description: __(
			'Pay securely using your Visa, Mastercard, or American Express.',
			'rave-woocommerce-payment-gateway'
		),
	},
	{
		key: 'stablecoin',
		label: __( 'Stablecoin', 'rave-woocommerce-payment-gateway' ),
		description: __(
			'Pay with USDT or USDC. Fast, global, and low-fee.',
			'rave-woocommerce-payment-gateway'
		),
	},
	{
		key: 'banktransfer',
		label: __( 'Bank Transfer', 'rave-woocommerce-payment-gateway' ),
		description: __(
			"Transfer funds directly from your bank account using your bank's transfer service.",
			'rave-woocommerce-payment-gateway'
		),
	},
	{
		key: 'mobilemoney',
		label: __( 'Mobile Money', 'rave-woocommerce-payment-gateway' ),
		description: __(
			'Quick and secure payment directly from your mobile wallet.',
			'rave-woocommerce-payment-gateway'
		),
	},
	{
		key: 'applepay',
		label: __( 'Apple Pay', 'rave-woocommerce-payment-gateway' ),
		description: __(
			'Check out in seconds using your Apple Pay wallet.',
			'rave-woocommerce-payment-gateway'
		),
	},
	{
		key: 'googlepay',
		label: __( 'Google Pay', 'rave-woocommerce-payment-gateway' ),
		description: __(
			'Simple and fast checkout using the cards saved to your Google Account.',
			'rave-woocommerce-payment-gateway'
		),
	},
	{
		key: 'opay',
		label: __( 'Opay', 'rave-woocommerce-payment-gateway' ),
		description: __(
			'Pay directly from your OPay wallet or card.',
			'rave-woocommerce-payment-gateway'
		),
	},
];

/**
 * Flutterwave payment options the designs do not surface as checkboxes. Kept
 * editable under "Advanced" so upgrading merchants never silently lose them.
 */
export const ADVANCED_METHODS = [
	{ key: 'ussd', label: __( 'USSD', 'rave-woocommerce-payment-gateway' ) },
	{ key: 'qr', label: __( 'QR', 'rave-woocommerce-payment-gateway' ) },
	{ key: 'nqr', label: __( 'NQR', 'rave-woocommerce-payment-gateway' ) },
	{ key: 'credit', label: __( 'Credit', 'rave-woocommerce-payment-gateway' ) },
	{ key: 'barter', label: __( 'Barter', 'rave-woocommerce-payment-gateway' ) },
];

export const TOOLTIPS = {
	title: __( 'Your unique payment modal title.', 'rave-woocommerce-payment-gateway' ),
	testPublicKey: __(
		'This is your test API key. Retrieve this from your test mode dashboard.',
		'rave-woocommerce-payment-gateway'
	),
	testSecretKey: __(
		'This is your test private key. Retrieve this from your test mode dashboard.',
		'rave-woocommerce-payment-gateway'
	),
	livePublicKey: __(
		'This is your production public key for live transactions.',
		'rave-woocommerce-payment-gateway'
	),
	liveSecretKey: __(
		'This is your production private key for live transactions.',
		'rave-woocommerce-payment-gateway'
	),
};
