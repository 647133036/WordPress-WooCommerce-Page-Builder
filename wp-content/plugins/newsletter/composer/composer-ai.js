jQuery(function ($) {

    jQuery('#tnpc-subject-ai-button').on('click', function (ev) {
        ev.preventDefault();
        // Composer v2 and v3
        let el = document.getElementById('options-subject');
        if (el === null) {
            return;
        }
        $subject = $(el);
        if ($subject.val().length < 20) {
            alert('Some more words are required to generate ideas for your subject');
        } else {
            jQuery('#tnpc-subject-ai-content').html('Creating...<div class="tnp-ellipsis"><div></div><div></div><div></div><div></div></div>');
            jQuery('#tnpc-subject-ai-modal').modal({
                clickClose: false,
                fadeDuration: 500
            });
            $.post(ajaxurl, {
                subject: $subject.val(),
                action: 'newsletter_ai_subjects',
                _ajax_nonce: tnp_ai_nonce
            }).done(function (response) {
                if (response.success) {
                    data = response.data;
                    let html = '';
                    for (subject of data) {
                        html += '<a href="#">' + subject + '</a><br>';
                    }
                    $('#tnpc-subject-ai-content').html(html);
                } else {
                    alert(response.data.message);
                    $.modal.close();
                }
                //$('#tnpc-subject-ai-content').html(marked.parse(data));
            }).fail(function () {
                $.modal.close();
                alert('Failed to contact the Newsletter Assistant, check to have your license set on the Newsletter main settings');
            });
        }

    });

    $('#tnpc-subject-ai-content').on('click', 'a', function (ev) {
        ev.preventDefault();
        let el = document.getElementById('options-subject');
        el.value = this.innerText;
        $.modal.close();
    });
});

var tnp_ai_generating = false;
function tnp_ai_generate(button) {
    if (tnp_ai_generating)
        return;
    tnp_ai_generating = true;
    button.innerHTML = '...';
    button.disabled = true;
    aprompt = document.getElementById('options-prompt').value;
    jQuery.post(ajaxurl, {
        prompt: aprompt,
        action: 'newsletter_composer_ai_generate',
        _ajax_nonce: tnp_ai_nonce
    }).done(function (response) {
        if (!response.success) {
            alert(response.data.message);
            $.modal.close();
        } else {
            document.getElementById('options-html').value = marked.parse(response.data.message);
            tinymce.activeEditor.load();
        }
        button.innerHTML = '<i class="far fa-smile"></i>';
        tnp_ai_generating = false;
        button.disabled = false;
    }).fail(function (response) {
        $.modal.close();
        alert('Failed');

    });
}