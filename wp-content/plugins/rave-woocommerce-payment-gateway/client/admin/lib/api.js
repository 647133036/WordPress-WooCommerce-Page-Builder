/**
 * Thin wrapper over the plugin's REST endpoints.
 *
 * `wp-api-fetch` is enqueued as a dependency of the admin bundle, so the root
 * URL and the REST nonce middleware are already registered by WordPress.
 */

import apiFetch from '@wordpress/api-fetch';

const NAMESPACE = '/flutterwave/v1';

export const fetchSettings = () =>
	apiFetch( { path: `${ NAMESPACE }/settings` } );

export const saveSettings = ( data ) =>
	apiFetch( {
		path: `${ NAMESPACE }/settings`,
		method: 'POST',
		data,
	} );

export const completeOnboarding = ( data = {} ) =>
	apiFetch( {
		path: `${ NAMESPACE }/onboarding/complete`,
		method: 'POST',
		data,
	} );

/**
 * Values injected by the PHP page (doc/support links, asset base URL).
 *
 * @return {Object} Admin bootstrap data, or an empty object outside WP admin.
 */
export const adminData = () => window.flutterwaveAdminData || {};
