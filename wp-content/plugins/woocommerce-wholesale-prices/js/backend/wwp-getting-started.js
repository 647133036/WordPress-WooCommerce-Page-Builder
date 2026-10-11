jQuery(document).ready(function ($) {

    var $wwp_getting_started = $(".wwp-getting-started");

    // Both the "X" (button.notice-dismiss) and the text "Dismiss" link (a.notice-dismiss-link)
    // dismiss the notice, so bind the same hide-AJAX to both.
    $wwp_getting_started.find('button.notice-dismiss, a.notice-dismiss-link').click(function (e) {

        // The text link is an href="#" anchor; stop it from jumping to the top of the page.
        e.preventDefault();

        $wwp_getting_started.fadeOut("fast", function () {
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "wwp_getting_started_notice_hide",
                    nonce: wwp_getting_started_js_params.nonce
                },
                dataType: "json"
            })
                .done(function (data, textStatus, jqXHR) {
                    // notice is now hidden
                })

        });

    });

});