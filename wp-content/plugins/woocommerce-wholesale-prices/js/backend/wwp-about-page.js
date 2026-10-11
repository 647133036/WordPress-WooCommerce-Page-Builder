jQuery(function ($) {
    $('.wwp-plugin-install').on('click', function (e) {
        e.preventDefault();

        // Check if the button is disabled. If it is, return early.
        if ($(this).data('disabled')) {
            return;
        }

        const $btn = $(this);
        const pluginSlug = $btn.data('plugin-slug');

        // Disable the button and change its text
        $btn.text('Installing...').data('disabled', true);
        $btn.addClass('disabled');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wwp_install_activate_plugin',
                plugin_slug: pluginSlug,
                nonce: about_page_params.nonce,
            },
            success: function (response) {
                if (response.success) {
                    $('.' + pluginSlug + '-status-text').text(about_page_params.i18n_installed_text);
                    $btn.remove();

                    // Reload the page.
                    location.reload();
                } else {
                    $btn.text(about_page_params.i18n_install_text).data('disabled', false);
                    $btn.removeClass('disabled');
                }
            },
            error: function () {
                $btn.text(about_page_params.i18n_install_text).data('disabled', false);
                $btn.removeClass('disabled');
            },
        });
    });
});