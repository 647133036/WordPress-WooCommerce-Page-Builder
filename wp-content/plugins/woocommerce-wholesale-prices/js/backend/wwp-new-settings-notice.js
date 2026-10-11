jQuery(document).ready(function ($) {

    var $wwp_new_settings_notice = $(".wwp-new-settings-notice");

    $wwp_new_settings_notice.find('.wwp-new-settings-dismiss').click(function (e) {

        $wwp_new_settings_notice.fadeOut("fast", function () {
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "wwp_new_settings_notice_hide",
                    nonce: wwp_new_settings_notice_js_params.nonce
                },
                dataType: "json"
            })
                .done(function (data, textStatus, jqXHR) {
                    // notice is now hidden
                })

        });

    });

});