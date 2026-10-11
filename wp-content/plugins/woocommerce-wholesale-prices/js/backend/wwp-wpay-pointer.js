/**
 * WPay toolbar pointer dismiss handler.
 *
 * Initializes the WP Pointer for the Wholesale Payments toolbar button
 * and handles dismissal via AJAX for both the "Dismiss Forever" button
 * and the X close button.
 *
 * @since 2.2.8
 */
jQuery( document ).ready( function ( $ ) {
    var $toolbar = $( '#wp-admin-bar-wpay_toolbar' );

    if ( ! $toolbar.length ) {
        return;
    }

    if ( typeof wwp_wpay_pointer_params === 'undefined' ) {
        return;
    }

    var params = wwp_wpay_pointer_params;

    /**
     * Build the pointer tooltip HTML from localized string params.
     *
     * @return {string} The pointer tooltip HTML.
     */
    function buildPointerContent() {
        var html = '<h3>' + params.title + '</h3>';
        html += '<p>' + params.description + '</p>';
        html += '<ul>';

        for ( var i = 0; i < params.features.length; i++ ) {
            html += '<li>' + params.features[ i ] + '</li>';
        }

        html += '</ul>';
        html += '<a href="' + params.learn_more_url + '" target="_blank" rel="noopener noreferrer" class="button button-primary">';
        html += '<span>' + params.learn_more_label + '</span></a>';

        return html;
    }

    /**
     * Dismiss the WPay pointer via AJAX and remove the toolbar button.
     * Re-opens the pointer if the AJAX request fails.
     */
    function dismissWpayPointer() {
        $.ajax( {
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpay_toolbar_dismiss_notice',
                nkey: 'wpay-menu-bar-button',
                nonce: params.nonce
            },
            dataType: 'json'
        } ).done( function () {
            $toolbar.remove();
        } ).fail( function () {
            $toolbar.pointer( 'open' );
        } );
    }

    $toolbar.pointer( {
        content: buildPointerContent(),
        buttons: function ( event, t ) {
            var button = $( '<a class="close" href="#"></a>' )
                .text( wp.i18n.__( 'Dismiss Forever', 'woocommerce-wholesale-prices' ) );

            return button.on( 'click.pointer', function ( e ) {
                e.preventDefault();
                t.element.pointer( 'close' );
                dismissWpayPointer();
            } );
        },
        position: { edge: 'top', align: 'center' },
        pointerClass: 'wpay-bar-tooltip',
        pointerWidth: 350
    } ).pointer( 'open' );

    // Add an X close button to the pointer that also persists the dismiss.
    // WP Pointer does not add its default X button when a custom buttons
    // callback is provided, so we add one manually.
    var $pointerWidget = $toolbar.pointer( 'widget' );
    var $closeBtn = $( '<a class="close wp-pointer-close" href="#"></a>' )
        .attr( 'aria-label', wp.i18n.__( 'Dismiss', 'woocommerce-wholesale-prices' ) );

    $pointerWidget.find( '.wp-pointer-content' ).prepend( $closeBtn );
    $closeBtn.on( 'click.wwp', function ( e ) {
        e.preventDefault();
        $toolbar.pointer( 'close' );
        dismissWpayPointer();
    } );
} );
