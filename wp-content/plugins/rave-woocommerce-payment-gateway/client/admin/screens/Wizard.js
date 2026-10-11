/**
 * The three-step onboarding wizard.
 *
 * Each step saves on "Save & continue" so a merchant who drops out halfway
 * keeps what they entered, and the final step flips the onboarding flag and
 * enables the gateway.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Page, StepBadge, Stepper, Button, LoadingScreen } from '../components';
import { GeneralForm, ApiForm, MethodsForm } from './forms';
import { STEPS } from '../lib/constants';
import { saveSettings, completeOnboarding } from '../lib/api';
import { validateGeneral, validateApi, validateMethods } from '../lib/validate';

const STEP_META = [
	{
		title: __( 'General details', 'rave-woocommerce-payment-gateway' ),
		subtitle: __( 'Please fill in your correct details to setup your store', 'rave-woocommerce-payment-gateway' ),
		crumb: __( 'Flutterwave Payment', 'rave-woocommerce-payment-gateway' ),
		fields: [ 'title', 'description', 'paymentStyle', 'autocompleteOrder' ],
		validate: validateGeneral,
		Form: GeneralForm,
	},
	{
		title: __( 'API & webhook', 'rave-woocommerce-payment-gateway' ),
		subtitle: __( 'Please fill in your correct details to setup your store', 'rave-woocommerce-payment-gateway' ),
		crumb: __( 'General', 'rave-woocommerce-payment-gateway' ),
		fields: [ 'testPublicKey', 'testSecretKey', 'livePublicKey', 'liveSecretKey', 'secretHash', 'goLive' ],
		validate: validateApi,
		Form: ApiForm,
	},
	{
		title: __( 'Payment methods', 'rave-woocommerce-payment-gateway' ),
		subtitle: __( 'Add your preferred methods to your checkout.', 'rave-woocommerce-payment-gateway' ),
		crumb: __( 'API & Webhook', 'rave-woocommerce-payment-gateway' ),
		fields: [ 'paymentMethods', 'advancedMethods', 'disableLogging', 'disableBarter' ],
		validate: validateMethods,
		Form: MethodsForm,
	},
];

/**
 * @param {Object}   props
 * @param {Object}   props.values     Current form values.
 * @param {Function} props.setValue   `( key, value ) => void`.
 * @param {Function} props.onSaved    Receives the payload returned by the API.
 * @param {Function} props.onFinished Called after the final step saves.
 * @param {Function} props.onError    Receives an error message.
 * @return {Element} The wizard.
 */
const Wizard = ( { values, setValue, onSaved, onFinished, onError } ) => {
	const [ index, setIndex ] = useState( 0 );
	const [ busy, setBusy ] = useState( false );
	const [ submitting, setSubmitting ] = useState( false );
	const [ errors, setErrors ] = useState( {} );

	const meta = STEP_META[ index ];
	const { Form } = meta;
	const isLast = index === STEP_META.length - 1;

	// Mirrors the designs: the primary button stays faded until the step is
	// actually completable.
	const complete = Object.keys( meta.validate( values ) ).length === 0;

	const patchFor = ( step ) =>
		step.fields.reduce( ( patch, key ) => ( { ...patch, [ key ]: values[ key ] } ), {} );

	const advance = async () => {
		const found = meta.validate( values );

		if ( Object.keys( found ).length ) {
			setErrors( found );
			return;
		}

		setErrors( {} );
		setBusy( true );

		try {
			if ( isLast ) {
				setSubmitting( true );
				const payload = await completeOnboarding( patchFor( meta ) );
				onSaved( payload );
				onFinished();
			} else {
				const payload = await saveSettings( patchFor( meta ) );
				onSaved( payload );
				setIndex( index + 1 );
			}
		} catch ( error ) {
			setSubmitting( false );
			onError( error?.message || __( 'Could not save your settings. Please try again.', 'rave-woocommerce-payment-gateway' ) );
		} finally {
			setBusy( false );
		}
	};

	if ( submitting ) {
		return <LoadingScreen />;
	}

	return (
		<Page
			backLabel={ meta.crumb }
			onBack={ index > 0 ? () => setIndex( index - 1 ) : undefined }
			aside={ <Stepper steps={ STEPS } activeIndex={ index } /> }
		>
			<StepBadge current={ index + 1 } total={ STEP_META.length } />

			<h1 className="flw-heading">{ meta.title }</h1>
			<p className="flw-subheading">{ meta.subtitle }</p>
			<hr className="flw-rule" />

			<Form values={ values } setValue={ setValue } errors={ errors } />

			<div className={ `flw-actions ${ index > 0 ? 'has-back' : '' }` }>
				{ index > 0 && (
					<Button variant="secondary" onClick={ () => setIndex( index - 1 ) } disabled={ busy }>
						{ __( 'Go back', 'rave-woocommerce-payment-gateway' ) }
					</Button>
				) }
				<Button onClick={ advance } busy={ busy } disabled={ ! complete }>
					{ isLast
						? __( 'Save', 'rave-woocommerce-payment-gateway' )
						: __( 'Save & continue', 'rave-woocommerce-payment-gateway' ) }
				</Button>
			</div>
		</Page>
	);
};

export default Wizard;
