jQuery(document).ready(function ($) {
    var popover_title =
        '<div class="tippy-header"><h4><i class="fa fa-info-circle"></i> ' +
        options.popover_header_title +
        "</h4></div> ";

    /**
     * Initialize Tippy tooltips.
     *
     * @since 2.1.6.1
     * @since 2.3.0 Hide the popover instead of rendering it when the AJAX response is empty.
     */
    tippy('.wwp_show_wholesale_prices_link', {
        content: '<span class="spinner"></span>',
        interactive: true,
        placement: 'right',
        maxWidth: 512,
        arrow: true,
        animation: 'fade',
        trigger: 'click',
        theme: 'light-border wwp-tippy-show-wholesale-price',
        onShow(instance) {
            
            // We can monkey-patch the instance's state object with our own state
            if (instance.state.ajax === undefined) {
                instance.state.ajax = {
                    isFetching: false,
                    canFetch: true,
                }
            }

            // Now we will avoid initiating a new request unless the old one
            // finished (`isFetching`).
            // We also only want to initiate a request if the tooltip has been
            // reset back to Loading... (`canFetch`).
            if (instance.state.ajax.isFetching || !instance.state.ajax.canFetch) {
                return
            }

            $.ajax({
                url: options.ajaxurl,
                type: 'POST',
                data : {
                    nonce: options.nonce,
                    action: 'get_product_wholesale_prices_ajax',
                    data: {
                        product_id: instance.reference.dataset.product_id,
                    }
                },
                success: function( response ){
                    // Backstop: an empty response means the server had no wholesale price to show
                    // (e.g. a composite whose components resolve to no wholesale saving), so hide
                    // the popover instead of showing an empty box.
                    var content = $.trim( response );

                    if ( ! content ) {
                        instance.hide();
                        return;
                    }

                    instance.setContent(popover_title + '<div class="tippy-inner-content">' + content + '</div>');
                }
            });
        },
        onHidden(instance) {
            instance.setContent('<span class="spinner"></span>');
            instance.state.ajax.canFetch = true;
        },
      })
});
