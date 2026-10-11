<?php
/** @var NewsletterEmailsAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */

defined('ABSPATH') || exit;

$email_id = (int) ($_GET['id'] ?? 0);
$e = $this->get_email($email_id);
if (!$e) {
    die('Invalid email');
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

<style>
    .mce-tinymce {
        margin-top: 15px;
        margin-bottom: 15px;
    }
</style>
<script src="<?php echo plugins_url('newsletter') ?>/vendor/tinymce/tinymce.min.js"></script>
<script type="text/javascript">

    // https://www.tinymce.com/docs/advanced/editor-control-identifiers/#toolbarcontrols
    tinymce.init({
        height: 600,
        mode: "specific_textareas",
        editor_selector: "visual",
        statusbar: true,
        allow_conditional_comments: true,
        table_toolbar: "tableprops tablecellprops tabledelete | tableinsertrowbefore tableinsertrowafter tabledeleterow | " +
                "tableinsertcolbefore tableinsertcolafter tabledeletecol",
        toolbar: "formatselect fontselect fontsizeselect | bold italic underline strikethrough forecolor backcolor | alignleft alignright aligncenter alignjustify | bullist numlist | link unlink | image",
        //theme: "advanced",
        entity_encoding: "raw",
        image_advtab: true,
        image_title: true,
        plugins: "table fullscreen legacyoutput textcolor colorpicker link image code lists advlist fullpage",
        relative_urls: false,
        convert_urls: false,
        remove_script_host: false,
        document_base_url: "<?php echo esc_js(get_option('home')) ?>/",
        content_css: ["<?php echo plugins_url('newsletter') ?>/emails/editor.css", "<?php echo home_url('/') . '?na=emails-css&id=' . $email_id . '&' . time(); ?>"]
    });

</script>
<script>
    function tnp_media() {
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

                tinyMCE.execCommand('mceInsertLink', false, media.attributes.url);

            } else {
                var display = tnp_uploader.state().display(media);
                var url = media.attributes.sizes[display.attributes.size].url;
                var width = media.attributes.sizes[display.attributes.size].width;
                var height = media.attributes.sizes[display.attributes.size].height;
                var img = '<img src="' + url + '" style="width: ' + width + 'px; height: ' + height + 'px">';
                tinyMCE.execCommand('mceInsertContent', false, img);

            }
        }).open();
    }

</script>
<div id="tnp-notification">
    <?php $controls->show(); ?>
</div>

<div class="wrap tnp-emails-editor-html" id="tnp-wrap">

    <div id="tnp-body">
        <form action="" method="post" style="margin-top: 2rem">
            <?php $controls->init() ?>

            <?php $controls->text('subject', 60, 'Newsletter subject') ?>
            <!-- <a href="#" class="button-primary" onclick="tnp_suggest_subject(); return false;"><?php _e('Get ideas', 'newsletter') ?></a> -->
            <input type="button" class="button-primary" value="Add media" onclick="tnp_media()">

            <?php $controls->editor('message', 30); ?>



            <div style="text-align: right ">
                <?php $controls->button_confirm('reset', __('Back to last save', 'newsletter'), 'Are you sure?'); ?>
                <?php $controls->button('test', __('Test', 'newsletter')); ?>
                <?php $controls->button('save', __('Save', 'newsletter')); ?>
                <?php $controls->button('next', __('Next', 'newsletter') . ' &raquo;'); ?>
            </div>
        </form>
        <?php include NEWSLETTER_DIR . '/emails/subjects.php'; ?>
    </div>

</div>