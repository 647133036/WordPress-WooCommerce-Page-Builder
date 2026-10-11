/**
 * Wholesale Reports teaser modal controller.
 *
 * Vanilla JS (no jQuery). Manages show/hide, focus trap, ESC and backdrop
 * dismissal for the upsell modal on the Reports landing page when WWPP is
 * inactive.
 *
 * - Modal opens on page load.
 * - Closing the modal (✕, ESC, backdrop, "View Sample Reports") reveals the
 *   static preview underneath.
 * - Clicking anywhere on the preview re-opens the modal so the "Unlock
 *   Reports" CTA stays one click away.
 * - "Unlock Reports" inside the modal does NOT close the modal — the link
 *   opens in a new tab and the original tab keeps the modal up.
 *
 * @since 2.2.8
 */
( function () {
    'use strict';

    var FOCUSABLE_SELECTOR = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join( ',' );

    var BODY_OPEN_CLASS = 'wwp-reports-teaser-modal-open';
    var HIDDEN_CLASS = 'is-hidden';

    function init() {
        var modal = document.getElementById( 'wwp-reports-teaser-modal' );
        if ( ! modal ) {
            return;
        }

        var card = modal.querySelector( '.wwp-reports-teaser__modal-card' );
        var preview = document.getElementById( 'wwp-reports-teaser-preview' );
        var previouslyFocused = null;

        function getFocusable() {
            return Array.prototype.slice.call(
                card.querySelectorAll( FOCUSABLE_SELECTOR )
            ).filter( function ( el ) {
                // getClientRects().length > 0 covers position:fixed elements too,
                // which offsetParent would incorrectly exclude.
                return el.getClientRects().length > 0;
            } );
        }

        function open() {
            if ( ! modal.classList.contains( HIDDEN_CLASS ) ) {
                return;
            }

            previouslyFocused = document.activeElement;
            modal.classList.remove( HIDDEN_CLASS );
            modal.removeAttribute( 'aria-hidden' );
            document.body.classList.add( BODY_OPEN_CLASS );

            if ( preview ) {
                preview.setAttribute( 'aria-hidden', 'true' );
            }

            var focusables = getFocusable();
            if ( focusables.length ) {
                focusables[ 0 ].focus();
            } else {
                card.setAttribute( 'tabindex', '-1' );
                card.focus();
            }
        }

        function close() {
            if ( modal.classList.contains( HIDDEN_CLASS ) ) {
                return;
            }
            modal.classList.add( HIDDEN_CLASS );
            modal.setAttribute( 'aria-hidden', 'true' );
            document.body.classList.remove( BODY_OPEN_CLASS );

            if ( preview ) {
                preview.removeAttribute( 'aria-hidden' );
            }

            if ( previouslyFocused && typeof previouslyFocused.focus === 'function' ) {
                previouslyFocused.focus();
            }
        }

        function handleKeydown( event ) {
            if ( modal.classList.contains( HIDDEN_CLASS ) ) {
                return;
            }

            if ( event.key === 'Escape' || event.key === 'Esc' ) {
                event.preventDefault();
                close();
                return;
            }

            if ( event.key !== 'Tab' ) {
                return;
            }

            var focusables = getFocusable();
            if ( ! focusables.length ) {
                event.preventDefault();
                return;
            }

            var first = focusables[ 0 ];
            var last = focusables[ focusables.length - 1 ];

            if ( event.shiftKey && document.activeElement === first ) {
                event.preventDefault();
                last.focus();
            } else if ( ! event.shiftKey && document.activeElement === last ) {
                event.preventDefault();
                first.focus();
            }
        }

        modal.addEventListener( 'click', function ( event ) {
            var target = event.target;
            while ( target && target !== modal ) {
                if ( target.getAttribute && target.getAttribute( 'data-wwp-reports-teaser-close' ) === 'true' ) {
                    event.preventDefault();
                    close();
                    return;
                }
                target = target.parentNode;
            }
        } );

        if ( preview ) {
            preview.addEventListener( 'click', function () {
                if ( modal.classList.contains( HIDDEN_CLASS ) ) {
                    open();
                }
            } );
        }

        document.addEventListener( 'keydown', handleKeydown );

        open();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
}() );
