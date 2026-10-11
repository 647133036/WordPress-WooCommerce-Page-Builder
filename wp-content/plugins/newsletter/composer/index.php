<?php
/**
 * This file is included by NewsletterControls to create the composer.
 */

/** @var string $context_type */
/** @var NewsletterControls $this */

defined('ABSPATH') || exit;

$list = NewsletterComposer::instance()->get_blocks();

$blocks = [];
foreach ($list as $key => $data) {
    if (!isset($blocks[$data['section']])) {
        $blocks[$data['section']] = array();
    }
    $blocks[$data['section']][$key]['name'] = $data['name'];
    $blocks[$data['section']][$key]['filename'] = $key;
    $blocks[$data['section']][$key]['icon'] = $data['icon'];
}

$css_attrs = [
    'body_padding_left' => intval($this->data['options_composer_padding'] ?? '0') . 'px',
    'body_padding_right' => intval($this->data['options_composer_padding'] ?? '0') . 'px',
];

// order the sections
$blocks = array_merge(array_flip(['header', 'content', 'footer']), $blocks);

// prepare the options for the default blocks
$block_options = get_option('newsletter_main');

$fields = new NewsletterFields($this);

$dir = is_rtl() ? 'rtl' : 'ltr';
$rev_dir = is_rtl() ? 'ltr' : 'rlt';

//wp_enqueue_script('jquery-ui-dialog');
//wp_enqueue_style('wp-jquery-ui-dialog');
?>
<script type="text/javascript">
    if (window.innerWidth < 1550) {
        document.body.classList.add('folded');
    }
</script>

<style>
<?php echo NewsletterComposer::instance()->get_composer_backend_css($css_attrs); ?>
</style>

<!-- For composer options on the fly change -->
<style id="tnp-backend-css">
</style>


<div id="tnp-builder" dir="ltr">

    <?php $this->hidden_encoded('message'); ?>
    <?php $this->hidden('updated'); ?>

    <?php $this->hidden('sender_email'); ?>
    <?php $this->hidden('sender_name'); ?>
    <?php $this->hidden('track'); ?>
    <?php $this->hidden('email_id'); ?>

    <div id="tnpb-main">

        <div id="tnpc-subject-wrap" dir="<?php echo $dir ?>">
            <table role="presentation" style="width: 100%">
                <?php if (!empty($controls->data['sender_email'])) { ?>
                    <tr>
                        <th dir="<?php echo $dir ?>"><?php esc_html_e('From', 'newsletter') ?></th>
                        <td dir="<?php echo $dir ?>"><?php echo esc_html($controls->data['sender_email']) ?></td>
                    </tr>
                <?php } ?>
                <tr>
                    <th dir="<?php echo $dir ?>" style="white-space: nowrap">
                        <?php esc_html_e('Subject', 'newsletter') ?>
                        <?php if ($context_type === 'automated') { ?>
                            <?php $this->field_help('https://www.thenewsletterplugin.com/documentation/addons/extended-features/automated-extension/#subject') ?>
                        <?php } ?>
                    </th>
                    <td dir="<?php echo $dir ?>">
                        <div id="tnpc-subject">
                            <?php $this->subject_v3(); ?>
                        </div>
                    </td>
                    <td id="tnpc-subject-icons" style="white-space: nowrap">
                        <a href="#subject-ideas-modal" rel="modal:open"><i class="far fa-lightbulb tnp-suggest-subject"></i></a>
                        <a href="#" id="tnpc-subject-ai-button"><i class="far fa-smile"></i></a>
                        <?php do_action('newsletter_composer_subject'); ?>
                    </td>
                </tr>
                <tr>
                    <th dir="<?php echo $dir ?>" style="white-space: nowrap">
                        <span title="<?php esc_attr_e('Shown by some email clients as excerpt', 'newsletter') ?>"><?php esc_html_e('Snippet', 'newsletter') ?></span>
                        <?php $this->field_help('https://www.thenewsletterplugin.com/documentation/newsletters/composer/#subject') ?>
                    </th>
                    <td dir="<?php echo $dir ?>"><?php $this->text('options_preheader') ?></td>
                    <td>

                    </td>
                </tr>
            </table>

            <div class="tnpb-actions">

                <a href="#templates-modal" rel="modal:open" title="<?php esc_attr_e('Templates', 'newsletter') ?>"><i class="far fa-file"></i></a>

                <a href="#tnpc-placeholders-modal" rel="modal:open" title="<?php esc_attr_e('Placeholders', 'newsletter') ?>"><i class="fas fa-user"></i></a>

                <a href="#tnpc-attachment-modal" rel="modal:open" title="<?php esc_attr_e('Attachments', 'newsletter') ?>"><i class="fas fa-paperclip"></i></a>

                <a href="#test-newsletter-modal" rel="modal:open" title="<?php esc_attr_e('Test', 'newsletter') ?>"><i class="fas fa-paper-plane"></i></a>

                <span id="tnpc-view-mode" title="<?php esc_attr_e('Switch preview mode', 'newsletter') ?>">
                    <i id="tnpc-view-mode-icon" class="fas fa-desktop"></i>
                </span>

                <?php do_action('newsletter_composer_actions'); ?>

            </div>

        </div>


        <div id="tnpb-content" dir="<?php echo $dir ?>">

            <!-- Composer content -->

        </div>
    </div>


    <div id="tnpb-sidebar" dir="<?php echo $dir ?>">

        <div class="tnpb-tabs">
            <button class="tnpb-tab-button" onclick="tnpb_open_tab(event, 'tnpb-blocks')" data-tab-id='tnpb-blocks' id="defaultOpen"><?php _e('Blocks', 'newsletter') ?></button>
            <button class="tnpb-tab-button" onclick="tnpb_open_tab(event, 'tnpb-settings')" data-tab-id='tnpb-settings'><?php _e('Settings', 'newsletter') ?></button>
        </div>

        <div id="tnpb-blocks" class="tnpb-tab">
            <?php foreach ($blocks as $k => $section) { ?>
                <div class="tnpb-block-icons" id="sidebar-add-<?php echo esc_attr($k) ?>">
                    <?php foreach ($section as $key => $block) { ?>
                        <div class="tnpb-block-icon" data-id="<?php echo esc_attr($key) ?>" data-name="<?php echo esc_attr($block['name']) ?>">
                            <img src="<?php echo esc_attr($block['icon']) ?>" title="<?php echo esc_attr($block['name']) ?>">
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <div id="tnpb-settings" class="tnpb-tab">

            <div class="tnp-field-row">
                <div class="tnp-field-col-2">
                    <?php $fields->color('options_composer_background', __('Main background', 'newsletter')) ?>
                </div>
                <div class="tnp-field-col-2">
                    <?php $fields->color('options_composer_block_background', __('Blocks background', 'newsletter')) ?>
                </div>
            </div>

            <?php $fields->font('options_composer_title_font', __('Titles font', 'newsletter')) ?>
            <?php $fields->font('options_composer_text_font', __('Text font', 'newsletter')) ?>

            <?php $fields->button('options_composer_button', __('Button style', 'newsletter'), ['url'=>false, 'label'=>false]); ?>

            <div class="tnp-field-row">
                <div class="tnp-field-col-2">
                    <?php
                    $fields->select('options_composer_width', __('Width', 'newsletter'),
                            ['600' => '600', '650' => '650', '700' => '700', '750' => '750']);
                    ?>

                </div>
                <div class="tnp-field-col-2">
                    <?php $fields->text('options_composer_padding', __('Mobile padding', 'newsletter'), ['size' => '40', 'description' => 'For boxed layouts']); ?>
                </div>
            </div>
            <p>Custom CSS can be added from the <a href="?page=newsletter_emails_settings" target="_blank">Settings page</a>.</p>

            <input id="tnpb-settings-apply" type="button" class="button-secondary" value="<?php esc_attr_e("Apply", 'newsletter') ?>">

        </div>

        <!-- Block options container (dynamically loaded -->
        <div id="tnpc-block-options">
            <div id="tnpc-block-options-header">
                <div id="tnpc-block-options-title"></div>
                <div id="tnpc-block-options-buttons">
                    <span id="tnpc-block-options-cancel" class="tnpc-button tnpc-button-secondary"><?php esc_html_e("Cancel", "newsletter") ?></span>
                    <span id="tnpc-block-options-save" class="tnpc-button"><?php esc_html_e("Apply", "newsletter") ?></span>
                </div>
            </div>
            <div id="tnpc-block-options-form">
                <!-- Block options -->
            </div>
        </div>

    </div>

    <div style="clear: both"></div>

</div>

<div style="display: none">
    <div id="newsletter-preloaded-export"></div>
    <!-- Block placeholder used by jQuery UI -->
    <div id="tnpb-draggable-helper"></div>
    <div id="tnpb-sortable-helper"></div>
</div>

<script type="text/javascript">
    var TNP_PLUGIN_URL = '<?php echo esc_js(Newsletter::plugin_url()) ?>';
    var TNP_HOME_URL = '<?php echo esc_js(home_url('/', is_ssl() ? 'https' : 'http')) ?>';
    var tnp_context_type = "<?php echo esc_js($context_type) ?>";
    var tnp_nonce = '<?php echo esc_js(wp_create_nonce('save')) ?>';
    var tnp_ai_nonce = '<?= esc_js(wp_create_nonce('tnp-ai')) ?>';
</script>

<?php

wp_enqueue_script('tnp-composer', plugins_url('newsletter') . '/composer/composer.js', ['jquery'], NEWSLETTER_VERSION);
wp_enqueue_script('tnp-composer-ai', plugins_url('newsletter') . '/composer/composer-ai.js', ['jquery'], NEWSLETTER_VERSION);
include __DIR__ . '/modals/test.php';
include __DIR__ . '/modals/attachment.php';
include __DIR__ . '/modals/subjects.php';
include __DIR__ . '/modals/subjects-ai.php';
include __DIR__ . '/modals/placeholders.php';
include __DIR__ . '/modals/templates.php';

wp_enqueue_editor();

do_action('newsletter_composer_footer');

