<?php
/** @var NewsletterEmailsAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */

defined('ABSPATH') || exit;

$email_id = (int) ($_GET['id'] ?? 0);

if (!$email_id) {
    check_admin_referer('newsletter-new');
    $email = [];
    $email['status'] = 'new';
    $email['subject'] = __('Here the email subject', 'newsletter');
    $email['track'] = Newsletter::instance()->get_option('track');
    $email['token'] = $this->get_token();
    $email['type'] = 'message';
    $email['send_on'] = time();
    $email['editor'] = NewsletterEmails::EDITOR_HTML;
    $email['message'] = "<!DOCTYPE html>\n<html>\n<head>\n<title>Your email title</title>\n</head>\n<body>\n</body>\n</html>";
    $email = $this->save_email($email);

    $controls->js_redirect($this->get_editor_url($email->id, $email->editor));
} else {
    $e = $this->get_email($email_id);
    if (!$e) {
        die('Invalid email ID');
    }
}

if ($controls->is_action('save') || $controls->is_action('next') || $controls->is_action('test')) {
    $email = [];
    $email['id'] = $email_id;

    if ($this->is_html_allowed()) {
        $email['message'] = $controls->data['message'];
    } else {
        $email['message'] = wp_kses_post($controls->data['message']);
    }

    $email['subject'] = wp_strip_all_tags($controls->data['subject']);
    $email_object = $this->save_email($email);
    Newsletter\Logs::add('newsletter-version-' . $email_object->id, date('Y-m-d H:i:s'), 0, $email_object->message);
    if ($controls->is_action('next')) {
        $controls->js_redirect($this->get_admin_page_url('edit') . '&id=' . $email_id);
        return;
    }
}

if ($controls->is_action('test')) {
    $this->send_test_email($this->get_email($email_id), $controls);
}

$controls->data = $this->get_email($email_id, ARRAY_A);

if (!$this->is_html_allowed()) {
    $controls->warnings[] = 'Your user cannot manage full HTML content, when saving the content will be filtered and get broken.';
}
?>

<?php include NEWSLETTER_INCLUDES_DIR . '/codemirror.php'; ?>

<style>
    .CodeMirror {
        height: 600px;
        margin-top: 15px;
        margin-bottom: 15px;
    }

    /* jQuery modal */
    .blocker {
        z-index: 100000;
    }

    .modal {
        z-index: 100001;
        top: 50px;
        visibility: visible !important; /* Patch for buy me a coffee plugin... */
    }
</style>
<script>
    var templateEditor;
    jQuery(function () {
        templateEditor = CodeMirror.fromTextArea(document.getElementById("options-message"), {
            lineNumbers: true,
            mode: 'htmlmixed',
            lineWrapping: true,
            extraKeys: {"Ctrl-Space": "autocomplete"}
        });
    });
    function tnp_media(name) {
        var tnp_uploader = wp.media({
            title: "Select an image",
            button: {
                text: "Select"
            },
            frame: 'post',
            multiple: false,
            displaySetting: true,
            displayUserSettings: true
        }).on("insert", function () {
            wp.media;
            var media = tnp_uploader.state().get("selection").first();
            if (media.attributes.url.indexOf("http") !== 0)
                media.attributes.url = "http:" + media.attributes.url;

            if (!media.attributes.mime.startsWith("image")) {

                templateEditor.getDoc().replaceRange(url, templateEditor.getDoc().getCursor());

            } else {
                var display = tnp_uploader.state().display(media);
                var url = media.attributes.sizes[display.attributes.size].url;

                templateEditor.getDoc().replaceRange('<img src="' + url + '">', templateEditor.getDoc().getCursor());

            }
        }).open();
    }

</script>

<div class="wrap tnp-emails-editor-html" id="tnp-wrap">

    <div id="tnp-body">

        <?php $controls->show(); ?>

        <form action="" method="post" style="margin-top: 2rem" id="tnp-raw-html-editor">
            <?php $controls->init() ?>

            <?php $controls->hidden('id'); // Used during tests ?>

            <div style="margin-bottom: 1.5rem">
                <?php $controls->button_confirm('reset', __('Back to last save', 'newsletter'), 'Are you sure?'); ?>
                <a class="button-primary tnpc-button" href="#tnp-test-modal" rel="modal:open"><?php esc_html_e('Test', 'newsletter'); ?></a>
                <?php $controls->button('save', __('Save', 'newsletter')); ?>
                <?php $controls->button('next', __('Next', 'newsletter') . ' &raquo;'); ?>
            </div>


            <?php $controls->text('subject', 60, 'Newsletter subject') ?>
            <!--
            <a href="#subject-ideas-modal" rel="modal:open" class="button-primary" onclick="tnp_suggest_subject(); return false;"><?php esc_html_e('Get ideas', 'newsletter') ?></a>
            -->
            <a href="#" class="button-primary" onclick="newsletter_textarea_preview('options-message'); return false;"><i class="fa fa-eye"></i></a>

            <input type="button" class="button-primary" value="Add media" onclick="tnp_media()">
            <?php $controls->textarea_preview('message', '100%', 700, '', '', false); ?>


        </form>
        <?php include NEWSLETTER_DIR . '/emails/subjects.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/modals/test.php' ?>