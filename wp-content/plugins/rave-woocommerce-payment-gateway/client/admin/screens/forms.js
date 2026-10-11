/**
 * The three form bodies, shared by the onboarding wizard and the tabbed
 * settings screen so both surfaces stay in step with one another.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Field,
	TextInput,
	TextArea,
	Select,
	Checkbox,
	CopyField,
} from '../components';
import { METHOD_MARKS } from '../icons';
import {
	CHECKOUT_OPTIONS,
	PAYMENT_METHODS,
	ADVANCED_METHODS,
	TOOLTIPS,
} from '../lib/constants';

/**
 * A random 64-character hex string, the same shape the server generates.
 *
 * @return {string} The new secret hash.
 */
const generateSecretHash = () => {
	const bytes = new Uint8Array( 32 );
	window.crypto.getRandomValues( bytes );

	return Array.from( bytes, ( byte ) => byte.toString( 16 ).padStart( 2, '0' ) ).join( '' );
};

/**
 * Step 1 — payment method title, description, checkout style, autocomplete.
 *
 * @param {Object}   props
 * @param {Object}   props.values   Current form values.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @param {Object}   [props.errors] Per-field validation messages.
 * @return {Element} The form body.
 */
export const GeneralForm = ( { values, setValue, errors = {} } ) => (
	<>
		<Field
			id="flw-title"
			label={ __( 'Title', 'rave-woocommerce-payment-gateway' ) }
			tooltip={ TOOLTIPS.title }
			error={ errors.title }
		>
			<TextInput
				id="flw-title"
				value={ values.title }
				placeholder={ __( 'Enter your title', 'rave-woocommerce-payment-gateway' ) }
				onChange={ ( event ) => setValue( 'title', event.target.value ) }
			/>
		</Field>

		<Field id="flw-description" label={ __( 'Description', 'rave-woocommerce-payment-gateway' ) }>
			<TextArea
				id="flw-description"
				value={ values.description }
				placeholder={ __( 'Enter your description', 'rave-woocommerce-payment-gateway' ) }
				onChange={ ( event ) => setValue( 'description', event.target.value ) }
			/>
		</Field>

		<Field
			id="flw-checkout"
			label={ __( 'Checkout', 'rave-woocommerce-payment-gateway' ) }
			error={ errors.paymentStyle }
		>
			<Select
				id="flw-checkout"
				value={ values.paymentStyle }
				options={ CHECKOUT_OPTIONS }
				placeholder={ __( 'Select option', 'rave-woocommerce-payment-gateway' ) }
				onChange={ ( event ) => setValue( 'paymentStyle', event.target.value ) }
			/>
		</Field>

		<Checkbox
			id="flw-autocomplete"
			checked={ values.autocompleteOrder }
			onChange={ ( checked ) => setValue( 'autocompleteOrder', checked ) }
			label={ __( 'Auto complete order', 'rave-woocommerce-payment-gateway' ) }
		/>
	</>
);

/**
 * Step 2 — API keys, the webhook URL and secret hash to copy, and the
 * live-mode switch.
 *
 * @param {Object}   props
 * @param {Object}   props.values   Current form values.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @param {Object}   [props.errors] Per-field validation messages.
 * @return {Element} The form body.
 */
export const ApiForm = ( { values, setValue, errors = {} } ) => {
	const [ regenerated, setRegenerated ] = useState( false );

	const keyFields = [
		{
			key: 'testPublicKey',
			label: __( 'Test public key', 'rave-woocommerce-payment-gateway' ),
			placeholder: __( 'Enter your test public key', 'rave-woocommerce-payment-gateway' ),
		},
		{
			key: 'testSecretKey',
			label: __( 'Test private key', 'rave-woocommerce-payment-gateway' ),
			placeholder: __( 'Enter your test private key', 'rave-woocommerce-payment-gateway' ),
		},
		{
			key: 'livePublicKey',
			label: __( 'Live public key', 'rave-woocommerce-payment-gateway' ),
			placeholder: __( 'Enter your live public key', 'rave-woocommerce-payment-gateway' ),
		},
		{
			key: 'liveSecretKey',
			label: __( 'Live private key', 'rave-woocommerce-payment-gateway' ),
			placeholder: __( 'Enter your live private key', 'rave-woocommerce-payment-gateway' ),
		},
	];

	return (
		<>
			{ keyFields.map( ( field ) => (
				<Field
					key={ field.key }
					id={ `flw-${ field.key }` }
					label={ field.label }
					tooltip={ TOOLTIPS[ field.key ] }
					error={ errors[ field.key ] }
				>
					<TextInput
						id={ `flw-${ field.key }` }
						value={ values[ field.key ] }
						placeholder={ field.placeholder }
						spellCheck="false"
						autoComplete="off"
						onChange={ ( event ) => setValue( field.key, event.target.value ) }
					/>
				</Field>
			) ) }

			<Field
				id="flw-webhook"
				label={ __( 'Webhook', 'rave-woocommerce-payment-gateway' ) }
				hint={ __(
					'Add this webhook URL to your webhook settings in your F4B dashboard.',
					'rave-woocommerce-payment-gateway'
				) }
			>
				<CopyField id="flw-webhook" value={ values.webhookUrl } />
			</Field>

			<Field
				id="flw-secret-hash"
				label={ __( 'Secret hash', 'rave-woocommerce-payment-gateway' ) }
				hint={ __(
					'Copy this into the secret hash field of your webhook settings in your F4B dashboard. Webhooks are rejected unless the two match.',
					'rave-woocommerce-payment-gateway'
				) }
				error={
					values.secretHashIsLegacy && ! regenerated
						? __(
							'Webhooks are paused until you replace this secret hash. Generate a new one, save, and update your F4B dashboard to match.',
							'rave-woocommerce-payment-gateway'
						)
						: undefined
				}
			>
				<div className="flw-copyfield">
					<TextInput
						id="flw-secret-hash"
						value={ values.secretHash }
						spellCheck="false"
						autoComplete="off"
						onChange={ ( event ) => setValue( 'secretHash', event.target.value ) }
					/>
					<button
						type="button"
						className="flw-copyfield__btn"
						onClick={ () => {
							setValue( 'secretHash', generateSecretHash() );
							setRegenerated( true );
						} }
					>
						{ __( 'Generate', 'rave-woocommerce-payment-gateway' ) }
					</button>
				</div>
			</Field>

			<Checkbox
				id="flw-golive"
				checked={ values.goLive }
				onChange={ ( checked ) => setValue( 'goLive', checked ) }
				label={ __( 'Enable live transactions', 'rave-woocommerce-payment-gateway' ) }
			/>
		</>
	);
};

/**
 * Step 3 — the payment methods offered at checkout, plus the advanced options
 * the designs drop but that existing merchants may already rely on.
 *
 * @param {Object}   props
 * @param {Object}   props.values   Current form values.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @param {Object}   [props.errors] Per-field validation messages.
 * @return {Element} The form body.
 */
export const MethodsForm = ( { values, setValue, errors = {} } ) => {
	const [ advancedOpen, setAdvancedOpen ] = useState( false );

	const toggle = ( listKey, key, checked ) => {
		const current = values[ listKey ] || [];
		setValue(
			listKey,
			checked ? [ ...new Set( [ ...current, key ] ) ] : current.filter( ( item ) => item !== key )
		);
	};

	return (
		<>
			<div className="flw-methods">
				{ PAYMENT_METHODS.map( ( method ) => {
					const Mark = METHOD_MARKS[ method.key ];
					const checked = ( values.paymentMethods || [] ).includes( method.key );

					return (
						<label key={ method.key } className="flw-method" htmlFor={ `flw-method-${ method.key }` }>
							<input
								id={ `flw-method-${ method.key }` }
								type="checkbox"
								checked={ checked }
								onChange={ ( event ) => toggle( 'paymentMethods', method.key, event.target.checked ) }
							/>
							<span className="flw-checkbox__box" aria-hidden="true">
								<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true">
									<path
										d="m3.5 8.4 3 3 6-6.8"
										fill="none"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
								</svg>
							</span>
							<span className="flw-method__mark">{ Mark && <Mark /> }</span>
							<span className="flw-method__text">
								<span className="flw-method__title">{ method.label }</span>
								<span className="flw-method__desc">{ method.description }</span>
							</span>
						</label>
					);
				} ) }
			</div>

			{ errors.paymentMethods && <p className="flw-field__error">{ errors.paymentMethods }</p> }

			<div className="flw-advanced">
				<button
					type="button"
					className="flw-advanced__toggle"
					aria-expanded={ advancedOpen }
					onClick={ () => setAdvancedOpen( ! advancedOpen ) }
				>
					{ __( 'Advanced', 'rave-woocommerce-payment-gateway' ) }
					<span className={ `flw-advanced__caret ${ advancedOpen ? 'is-open' : '' }` } aria-hidden="true" />
				</button>

				{ advancedOpen && (
					<div className="flw-advanced__body">
						<p className="flw-advanced__note">
							{ __(
								'Additional Flutterwave payment options and diagnostics. These are not shown at checkout unless selected.',
								'rave-woocommerce-payment-gateway'
							) }
						</p>

						<div className="flw-advanced__grid">
							{ ADVANCED_METHODS.map( ( method ) => (
								<Checkbox
									key={ method.key }
									id={ `flw-advanced-${ method.key }` }
									checked={ ( values.advancedMethods || [] ).includes( method.key ) }
									onChange={ ( checked ) => toggle( 'advancedMethods', method.key, checked ) }
									label={ method.label }
								/>
							) ) }
						</div>

						<Checkbox
							id="flw-disable-logging"
							checked={ values.disableLogging }
							onChange={ ( checked ) => setValue( 'disableLogging', checked ) }
							label={ __( 'Disable logging', 'rave-woocommerce-payment-gateway' ) }
						/>
						<Checkbox
							id="flw-disable-barter"
							checked={ values.disableBarter }
							onChange={ ( checked ) => setValue( 'disableBarter', checked ) }
							label={ __( 'Disable Barter', 'rave-woocommerce-payment-gateway' ) }
						/>
					</div>
				) }
			</div>
		</>
	);
};
