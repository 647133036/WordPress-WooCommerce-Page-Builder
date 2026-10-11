/* global JQuery */
jQuery( document).ready( function( $ ) {

    // For Variable products. Only triggered on variable products.
    $( document.body ).on( 'woocommerce_variations_loaded' , '#woocommerce-product-data' , function() {

        $( ".wholesale-price-per-role-and-country-accordion" ).accordion( {
            collapsible: true,
            heightStyle: "content"
        } );

    });

    // For simple products. Triggered too on variable products but has no effect.
    $( ".wholesale-price-per-role-and-country-accordion" ).accordion( {
        collapsible: true,
        heightStyle: "content"
    } );

    // When the Aelia Currency Switcher "Product base currency" selector changes, update the
    // wholesale-price field labels live so they reflect the newly selected base currency,
    // instead of only updating after the product is saved.
    var baseCurrencyLabelParams = window.wwp_base_currency_label_params || null;

    if ( baseCurrencyLabelParams && baseCurrencyLabelParams.currencies ) {

        var updateBaseCurrencyLabels = function( $scope, currencyCode ) {
            var currency = baseCurrencyLabelParams.currencies[ currencyCode ];

            if ( ! currency ) {
                return;
            }

            $scope.find( "label" ).each( function() {
                var $label  = $( this );
                var forAttr = $label.attr( "for" ) || "";

                // Only the base-currency wholesale price labels carry the "Base Currency" marker
                // (rendered inside an <em> element); leave the per-currency labels untouched.
                if ( forAttr.indexOf( "_wholesale_price" ) === -1 || ! $label.find( "em" ).length ) {
                    return;
                }

                var roleKey = forAttr.replace( /_wholesale_price.*$/, "" );
                var role    = baseCurrencyLabelParams.roles[ roleKey ] || {};
                var symbol  = role.currency_symbol ? role.currency_symbol : currency.symbol;

                // For variable products the help-tip is rendered inside the label, so preserve it
                // across the rebuild instead of overwriting it.
                var $tip = $label.find( ".wwp_wc_help_tip_container" );

                // Build the label from text/DOM nodes rather than an HTML string so a role's
                // admin-set currency symbol (or name) can never inject markup into the DOM.
                // Mirrors the canonical PHP format in
                // WWP_Helper_Functions::wwp_get_aelia_base_currency_field_label(); keep the two in sync.
                $label
                    .empty()
                    .append( $( "<span>" ).text( currency.name + " (" + symbol + ") " ) )
                    .append( $( "<em>" ).append( $( "<b>" ).text( baseCurrencyLabelParams.base_currency_text ) ) );

                if ( $tip.length ) {
                    $label.append( " " ).append( $tip );

                    // Keep the help-tip text in sync with the relabelled currency so the tooltip
                    // doesn't keep describing the previous base currency until the next save.
                    if ( role.name && baseCurrencyLabelParams.tip_format ) {
                        var tipText = baseCurrencyLabelParams.tip_format
                            .replace( "%1$s", role.name )
                            .replace( "%2$s", currency.name + " (" + symbol + ")" );

                        // WooCommerce's tipTip renders data-tip as HTML, so escape the assembled text
                        // (a role name can be admin-set) via a text-node round-trip before assigning —
                        // mirroring the .text() used for the visible label and the server's
                        // wc_sanitize_tooltip, so no markup can reach tipTip's HTML sink.
                        var safeTipText = $( "<div>" ).text( tipText ).html();

                        $tip.find( ".woocommerce-help-tip" ).attr( {
                            "data-tip": safeTipText,
                            "aria-label": tipText
                        } );
                    }
                }
            } );
        };

        // When a currency becomes the base, its standalone per-currency row duplicates the base
        // field (the server omits it on reload). Hide that row so the editor isn't ambiguous, while
        // leaving its input in the DOM so the entered value is still preserved on save; restore any
        // row hidden by a previous switch.
        var syncBaseCurrencyDuplicateRow = function( $scope, currencyCode ) {
            // Restore by the marker class alone: variable products wrap fields in .form-row, while
            // simple products use the core .form-field <p> with no .form-row ancestor.
            $scope.find( ".wwp-base-currency-duplicate" )
                .removeClass( "wwp-base-currency-duplicate" )
                .show();

            $scope.find( "label[for*='_" + currencyCode + "_wholesale_price']" ).each( function() {
                // Prefer the variable-product .form-row wrapper so its behaviour is unchanged, then
                // fall back to the simple-product .form-field wrapper. Not .closest( ".form-row, .form-field" ):
                // .closest() returns the nearest match, which on variable products is the inner
                // .form-field, leaving an empty bordered row and breaking the restore selector.
                var $row = $( this ).closest( ".form-row" );
                if ( ! $row.length ) {
                    $row = $( this ).closest( ".form-field" );
                }
                $row.addClass( "wwp-base-currency-duplicate" ).hide();
            } );
        };

        $( document.body ).on( "change", "select[name^='_product_base_currency']", function() {
            var $select = $( this );
            var $scope  = $select.closest( ".woocommerce_variation" );

            if ( ! $scope.length ) {
                // The product-level base-currency select on variable products lives outside any
                // variation. Scope to non-variation groups so changing it doesn't relabel every
                // variation's own base-currency fields.
                $scope = $( ".wholesale-prices-options-group" ).not( ".woocommerce_variation .wholesale-prices-options-group" );
            }

            updateBaseCurrencyLabels( $scope, $select.val() );
            syncBaseCurrencyDuplicateRow( $scope, $select.val() );
        } );

    }

} );
