/**
 * Terminal screen of the wizard. Auto-advances to the settings view so the
 * merchant is not left on a dead end.
 */

import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SuccessIllustration } from '../icons';

/**
 * @param {Object}   props
 * @param {Function} props.onDone Called once the confirmation has been shown.
 * @return {Element} The success screen.
 */
const Success = ( { onDone } ) => {
	useEffect( () => {
		const timer = setTimeout( onDone, 2600 );
		return () => clearTimeout( timer );
	}, [ onDone ] );

	return (
		<div className="flw-success">
			<SuccessIllustration />
			<h1>{ __( 'Store setup successful!', 'rave-woocommerce-payment-gateway' ) }</h1>
			<p>
				{ __(
					'You have successfully connected your Flutterwave for Business account to WooCommerce.',
					'rave-woocommerce-payment-gateway'
				) }
			</p>
			<button type="button" className="flw-link-btn" onClick={ onDone }>
				{ __( 'Go to settings', 'rave-woocommerce-payment-gateway' ) } &rarr;
			</button>
		</div>
	);
};

export default Success;
