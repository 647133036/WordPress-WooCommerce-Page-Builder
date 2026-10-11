import $ from 'jquery';
import {
	normalizeOrderData,
	normalizeAddress,
} from 'wcflutterwave/blocks/normalize';
import { getBlocksConfiguration } from 'wcflutterwave/blocks/utils';

/**
 * Construct WC AJAX endpoint URL.
 *
 * @param {string} endpoint Request endpoint URL.
 * @param {string} prefix Endpoint URI prefix (default: 'wc_rave_').
 * @return {string} URL with interpolated endpoint.
 */
const getAjaxUrl = ( endpoint, prefix = 'wc_rave_' ) => {
	return getBlocksConfiguration()
		?.ajax_url?.toString()
		?.replace( '%%endpoint%%', prefix + endpoint );
};

export const getCartDetails = () => {
	const data = {
		security: getBlocksConfiguration()?.nonce?.payment,
	};

	return $.ajax( {
		type: 'POST',
		data,
		url: getAjaxUrl( 'get_cart_details' ),
	} );
};

export const createOrder = ( sourceEvent, paymentRequestType ) => {
	const data = normalizeOrderData( sourceEvent, paymentRequestType );

	return $.ajax( {
		type: 'POST',
		data,
		dataType: 'json',
		url: getAjaxUrl( 'create_order' ),
	} );
};