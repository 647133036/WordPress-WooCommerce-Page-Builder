/**
 * First-run screen. Shown until the merchant either finishes the wizard or is
 * detected as already configured.
 */

import { __ } from '@wordpress/i18n';
import { Page, Button } from '../components';
import { FlutterwaveWordmark, DocsIllustration, SupportIllustration } from '../icons';
import { adminData } from '../lib/api';

/**
 * @param {Object}   props
 * @param {Function} props.onActivate Starts the wizard.
 * @return {Element} The welcome screen.
 */
const Welcome = ( { onActivate } ) => {
	const { documentation, support } = adminData();

	return (
		<Page wide backLabel={ __( 'Flutterwave Payment', 'rave-woocommerce-payment-gateway' ) }>
			<div className="flw-welcome">
				<FlutterwaveWordmark />

				<h1 className="flw-welcome__title">
					{ __( 'Welcome to Flutterwave', 'rave-woocommerce-payment-gateway' ) }
					<br />
					{ __( 'for Business', 'rave-woocommerce-payment-gateway' ) }
				</h1>

				<p className="flw-welcome__lede">
					{ __(
						'Flutterwave enables you to collect payments in over 30 currencies worldwide. With support for Cards, Mobile money, Bank transfer, Apple Pay, Google Pay and more.',
						'rave-woocommerce-payment-gateway'
					) }
				</p>

				<Button onClick={ onActivate }>
					{ __( 'Activate Flutterwave', 'rave-woocommerce-payment-gateway' ) }
				</Button>

				<div className="flw-cards">
					<div className="flw-card">
						<div className="flw-card__art">
							<DocsIllustration />
						</div>
						<h2>{ __( 'Documentation', 'rave-woocommerce-payment-gateway' ) }</h2>
						<p>
							{ __(
								'Explore guides and resources to help you set up and manage your Flutterwave integration.',
								'rave-woocommerce-payment-gateway'
							) }
						</p>
						<a href={ documentation } target="_blank" rel="noreferrer noopener">
							{ __( 'Read Documentation', 'rave-woocommerce-payment-gateway' ) } &rarr;
						</a>
					</div>

					<div className="flw-card">
						<div className="flw-card__art">
							<SupportIllustration />
						</div>
						<h2>{ __( 'Support', 'rave-woocommerce-payment-gateway' ) }</h2>
						<p>
							{ __(
								'Contact our team for dedicated support with your integration.',
								'rave-woocommerce-payment-gateway'
							) }
						</p>
						<a href={ support } target="_blank" rel="noreferrer noopener">
							{ __( 'Contact Support', 'rave-woocommerce-payment-gateway' ) } &rarr;
						</a>
					</div>
				</div>
			</div>
		</Page>
	);
};

export default Welcome;
