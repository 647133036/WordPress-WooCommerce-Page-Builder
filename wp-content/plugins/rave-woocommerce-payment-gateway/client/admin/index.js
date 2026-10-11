/**
 * Mounts the Flutterwave admin app.
 */

import { createRoot } from '@wordpress/element';
import App from './app';
import './style.scss';

const mount = () => {
	const root = document.getElementById( 'flutterwave-admin-root' );

	if ( ! root ) {
		return;
	}

	createRoot( root ).render( <App /> );
};

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
