/**
 * WPay suggestion banner on WooCommerce Settings page.
 *
 * Injects a recommendation banner after the WooCommerce settings form
 * with a dismiss button that persists via AJAX.
 *
 * @since 2.1.3
 * @since 2.2.8 Added dismiss button and AJAX persistence.
 */
jQuery( document ).ready( function ( $ ) {
    var $target = $( '.wc-settings-prevent-change-event' );

    if ( ! $target.length ) {
        return;
    }

    if ( typeof wwp_wpay_wc_settings_params === 'undefined' ) {
        return;
    }

    var content = '<div class="wwp-recommendation-main-warapper">' +
        '<button type="button" class="wwp-wpay-wc-settings-dismiss" aria-label="' + wwp_wpay_wc_settings_params.dismiss_label + '">&times;</button>' +
        '<span class="wwpp-recommendation-notice">' +
        '<span class="dashicons dashicons-info-outline"></span>' +
        wwp_wpay_wc_settings_params.banner_message +
        '</span></div>';

    $target.after( content );

    $( document ).on( 'click', '.wwp-wpay-wc-settings-dismiss', function ( e ) {
        e.preventDefault();

        var $wrapper = $( this ).closest( '.wwp-recommendation-main-warapper' );
        $wrapper.fadeOut( 'fast' );

        $.ajax( {
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpay_toolbar_dismiss_notice',
                nkey: 'wpay-wc-settings-banner',
                nonce: wwp_wpay_wc_settings_params.nonce
            },
            dataType: 'json'
        } ).done( function () {
            $wrapper.promise().done( function () {
                $wrapper.remove();
            } );
        } ).fail( function () {
            $wrapper.fadeIn( 'fast' );
        } );
    } );
} );
