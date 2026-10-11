/**
 * Post-onboarding screen: the same three groups of fields, now as tabs.
 *
 * Saving from any tab persists that tab's fields only, matching the wizard so
 * an unsaved edit on one tab can never overwrite another.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Page, Button } from '../components';
import { GeneralForm, ApiForm, MethodsForm } from './forms';
import { saveSettings } from '../lib/api';
import { validateGeneral, validateApi, validateMethods } from '../lib/validate';

const TABS = [
	{
		key: 'general',
		label: __( 'General', 'rave-woocommerce-payment-gateway' ),
		crumb: __( 'Flutterwave Payment', 'rave-woocommerce-payment-gateway' ),
		fields: [ 'title', 'description', 'paymentStyle', 'autocompleteOrder' ],
		validate: validateGeneral,
		Form: GeneralForm,
		cta: __( 'Save & continue', 'rave-woocommerce-payment-gateway' ),
	},
	{
		key: 'api',
		label: __( 'API & Webhook', 'rave-woocommerce-payment-gateway' ),
		crumb: __( 'General', 'rave-woocommerce-payment-gateway' ),
		fields: [ 'testPublicKey', 'testSecretKey', 'livePublicKey', 'liveSecretKey', 'secretHash', 'goLive' ],
		validate: validateApi,
		Form: ApiForm,
		cta: __( 'Save & continue', 'rave-woocommerce-payment-gateway' ),
	},
	{
		key: 'methods',
		label: __( 'Payment Methods', 'rave-woocommerce-payment-gateway' ),
		crumb: __( 'API & Webhook', 'rave-woocommerce-payment-gateway' ),
		fields: [ 'paymentMethods', 'advancedMethods', 'disableLogging', 'disableBarter' ],
		validate: validateMethods,
		Form: MethodsForm,
		cta: __( 'Save', 'rave-woocommerce-payment-gateway' ),
	},
];

/**
 * The tab named by `?tab=` in the URL (the secret hash admin notice links to
 * `tab=api`), falling back to the first.
 *
 * @return {number} Tab index.
 */
const initialTab = () => {
	const key = new URLSearchParams( window.location.search ).get( 'tab' );
	const index = TABS.findIndex( ( item ) => item.key === key );

	return index > -1 ? index : 0;
};

/**
 * @param {Object}   props
 * @param {Object}   props.values   Current form values.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @param {Function} props.onSaved  Receives the payload returned by the API.
 * @param {Function} props.onNotice `( status, message ) => void`.
 * @return {Element} The settings screen.
 */
const Settings = ( { values, setValue, onSaved, onNotice } ) => {
	const [ active, setActive ] = useState( initialTab );
	const [ busy, setBusy ] = useState( false );
	const [ errors, setErrors ] = useState( {} );

	const tab = TABS[ active ];
	const { Form } = tab;

	const save = async () => {
		const found = tab.validate( values );

		if ( Object.keys( found ).length ) {
			setErrors( found );
			return;
		}

		setErrors( {} );
		setBusy( true );

		try {
			const patch = tab.fields.reduce( ( acc, key ) => ( { ...acc, [ key ]: values[ key ] } ), {} );
			const payload = await saveSettings( patch );
			onSaved( payload );
			onNotice( 'success', __( 'Changes saved.', 'rave-woocommerce-payment-gateway' ) );

			if ( active < TABS.length - 1 ) {
				setActive( active + 1 );
			}
		} catch ( error ) {
			onNotice(
				'error',
				error?.message || __( 'Could not save your settings. Please try again.', 'rave-woocommerce-payment-gateway' )
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<Page
			wide
			backLabel={ tab.crumb }
			onBack={ active > 0 ? () => setActive( active - 1 ) : undefined }
		>
			<div className="flw-tabs" role="tablist">
				{ TABS.map( ( item, index ) => (
					<button
						key={ item.key }
						type="button"
						role="tab"
						aria-selected={ index === active }
						className={ `flw-tab ${ index === active ? 'is-active' : '' }` }
						onClick={ () => {
							setErrors( {} );
							setActive( index );
						} }
					>
						{ item.label }
					</button>
				) ) }
			</div>

			<div className="flw-tabpanel" role="tabpanel">
				<Form values={ values } setValue={ setValue } errors={ errors } />

				<div className="flw-actions">
					<Button onClick={ save } busy={ busy }>
						{ tab.cta }
					</Button>
				</div>
			</div>
		</Page>
	);
};

export default Settings;
