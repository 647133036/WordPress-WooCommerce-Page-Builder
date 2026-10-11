<?php
/** @var NewsletterEmailsAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
defined('ABSPATH') || exit;

if ($controls->is_action()) {
    if ($controls->is_action('save')) {
        $controls->data = wp_kses_post_deep($controls->data);
        $this->save_main_options($controls->data, '');
        $controls->add_toast_saved();
    }
} else {
    $controls->data = $this->get_options('');
}
?>

<?php include NEWSLETTER_INCLUDES_DIR . '/codemirror.php'; ?>
<style>
    .CodeMirror {
        border: 1px solid #ddd;
    }
</style>

<script>
    jQuery(function () {
        var editor = CodeMirror.fromTextArea(document.getElementById("options-css"), {
            lineNumbers: true,
            mode: 'css',
            extraKeys: {"Ctrl-Space": "autocomplete"}
        });
    });
</script>

<div class="wrap tnp-emails tnp-emails-options" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
        <?php //$controls->title_help('/profile-page')   ?>
<!--        <h2><?php esc_html_e('Newsletters', 'newsletter') ?></h2>-->
        <?php include __DIR__ . '/nav.php' ?>

    </div>

    <div id="tnp-body">

        <?php $controls->show() ?>
        <p>

        </p>

        <form id="channel" method="post" action="">
            <?php $controls->init(); ?>


            <table class="form-table">

                <tr>
                    <th><?php esc_html_e('Custom CSS', 'newsletter') ?>
                    </th>
                    <td>

                        <?php $controls->textarea('css'); ?>
                        <p class="description">
                            CSS added to the main newsletter CSS.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Post featured image', 'newsletter') ?>
                    </th>
                    <td>

                        <?php $controls->checkbox2('post_image_use_gallery', 'Use the first gallery image'); ?>
                        <br>
                        <?php $controls->checkbox2('post_image_use_content', 'Use the first content image'); ?>
                        <p class="description">
                            When the featured image is not set.
                        </p>
                    </td>
                </tr>

            </table>


            <p>
                <?php $controls->button_save() ?>
            </p>

        </form>

    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER ?>

</div>
