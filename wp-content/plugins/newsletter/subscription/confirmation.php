<?php

defined('ABSPATH') || exit;

/** @var NewsletterSubscriptionAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
/** @var string $language */

$email = null;

if (!$controls->is_action()) {

    $controls->data = $this->get_options('', $language);

    $email = Newsletter::instance()->get_email($controls->data['confirmation_email_id'] ?? 0);

    if (!$email) {
        $email = NewsletterComposer::instance()->build_email_from_template('confirmation-1');
        if (is_wp_error($email)) {
            die($email->get_error_message());
        }
        $email->status = TNP_Email::STATUS_SENT;
        $email->subject = $this->get_default_text('confirmation_subject');
        $email = $this->save_email($email);
        $controls->data['confirmation_email_id'] = $email->id;
        $controls->data['confirmation_email'] = '0'; // Type of confirmation email
        $this->save_options($controls->data, '', $language);
    }

    //$r = NewsletterComposer::instance()->regenerate($email);
    NewsletterComposer::update_controls($controls, $email);
} else {



    if ($controls->is_action('save')) {

        // Cleans up the custom value if the custom checkbox is not set, not the best implementation possible...
        foreach ($controls->data as $k => $v) {
            if (strpos($k, '_custom') > 0) {
                if (!$v) {
                    $controls->data[str_replace('_custom', '', $k)] = '';
                }
                // Remove the _custom field
                unset($controls->data[$k]);
            }
        }

        $options = $this->get_options('', $language);

        // Process the email before filtering the options, otherwise the wp_kses_post() will
        // break the email content.
        $email = Newsletter::instance()->get_email($options['confirmation_email_id']);
        $email->track = Newsletter::instance()->get_option('track');
        NewsletterComposer::update_email($email, $controls);
        $email = $this->save_email($email);

        $controls->data = wp_kses_post_deep($controls->data);

        $options['confirmation_message'] = NewsletterModule::clean_url_tags($controls->data['confirmation_message']);
        $options['confirmation_subject'] = $controls->data['confirmation_subject'];
        $options['confirmation_email'] = $controls->data['confirmation_email'];
        $options['confirmation_text'] = $controls->data['confirmation_text'];
        $options['confirmation_id'] = $controls->data['confirmation_id'];
        $options['confirmation_url'] = $controls->data['confirmation_url'];

        $this->save_options($options, '', $language);

        $controls->add_toast_saved();
        $controls->data = $options;
        NewsletterComposer::update_controls($controls, $email);
    }

    if (NEWSLETTER_DEBUG && $controls->is_action('delete')) {
        $options = $this->get_options('', $language);
        $this->delete_email($controls->data['confirmation_email_id']);
        $options['confirmation_email_id'] = 0;
        $this->save_options($controls->data, '', $language);
        $controls->js_redirect('?page=newsletter_subscription_confirmation');
    }
}

// Adds the custom checkbox value if the customizable field is not empty... again, it should be implemented in a better way
foreach (['confirmation_message', 'confirmation_text'] as $key) {
    if (!empty($controls->data[$key])) {
        $controls->data[$key . '_custom'] = '1';
    }
}

if (!empty($controls->data['confirmation_email'])) {
    if (strpos($controls->data['message'], '{subscription_confirm_url}') === false &&
            strpos($controls->data['message'], '{confirmation_url}') === false) {
        $controls->warnings = 'A button or link with the <code>{confirmation_url}</code> placeholder is missing.';
    }
}

update_option('newsletter_dummy_user_language', $language, false);
?>

<div class="wrap" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
        <?php $controls->title_help('/subscription') ?>
        <?php include __DIR__ . '/nav.php' ?>
    </div>

    <div id="tnp-body">

        <?php $controls->show(); ?>


        <form method="post" action="">
            <?php $controls->init(); ?>

            <p>

                <?php //esc_html_e('Only for double opt-in mode.', 'newsletter')  ?></p>
            </p>

            <div id="tabs">
                <ul>
                    <li><a href="#tabs-settings"><?php esc_html_e('Page', 'newsletter') ?></a></li>
                    <li><a href="#tabs-email"><?php esc_html_e('Email', 'newsletter') ?></a></li>
                </ul>

                <div id="tabs-settings">

                    <?php //$this->language_notice();  ?>

                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Confirmation page', 'newsletter'); ?></th>
                            <td>
                                <?php $controls->page_or_url('confirmation'); ?>
                            </td>
                        </tr>
                        <tr data-tnpshow="confirmation_id=0">
                            <th><?php esc_html_e('Page content', 'newsletter') ?></th>
                            <td>
                                <?php $controls->checkbox2('confirmation_text_custom', __('Customize', 'newsletter')); ?>
                                <div data-tnpshow="confirmation_text_custom=1">
                                    <?php $controls->wp_editor('confirmation_text', ['editor_height' => 150], ['default' => $this->get_default_text('confirmation_text')]); ?>
                                </div>
                                <div data-tnpshow="confirmation_text_custom=0" class="tnpc-default-text">
                                    <?php echo wp_kses_post($this->get_default_text('confirmation_text')) ?>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <?php $controls->button_save() ?>
                        <?php if (current_user_can('administrator')) { ?>
                            <?php $controls->btn_link($this->build_dummy_action_url('s'), __('Preview', 'newsletter'), ['tertiary' => true, 'target' => '_blank']); ?>
                        <?php } ?>
                    </p>

                </div>


                <div id="tabs-email">

                    <?php //$this->language_notice();  ?>

                    <?php $controls->select('confirmation_email', ['0' => __('Default', 'newsletter'), '1' => __('Composer', 'newsletter')]); ?>

                    <?php $controls->button_save() ?>

                    <?php
                    $controls->button_icon_statistics(NewsletterStatisticsAdmin::instance()->get_statistics_url($controls->data['confirmation_email_id']),
                            ['secondary' => true, 'target' => '_blank', 'data-tnpshow' => 'confirmation_email=1'])
                    ?>

                    <?php if (NEWSLETTER_DEBUG) { ?>
                        <?php $controls->btn_link(home_url('/') . '?na=json&id=' . $email->id, '{}') ?>
                        <?php $controls->button_icon_delete(); ?>
                        [#<?php echo (int) $controls->data['confirmation_email_id']; ?>]
                    <?php } ?>

                    <div id="tnp-composer-confirmation" style="display: none" data-tnpshow="confirmation_email=1">
                        <?php $controls->composer_v3(true, false, 'confirmation'); ?>
                    </div>

                    <div id="tnp-standard-confirmation" style="display: none" data-tnpshow="confirmation_email=0">

                        <table class="form-table">
                            <tr>
                                <td>

                                    <?php $controls->text('confirmation_subject', 70, $this->get_default_text('confirmation_subject')); ?>
                                    <br><br>
                                    <?php $controls->checkbox2('confirmation_message_custom', 'Customize'); ?>

                                    <div data-tnpshow="confirmation_message_custom=1">
                                        <?php $controls->wp_editor('confirmation_message', ['editor_height' => 150], ['default' => $this->get_default_text('confirmation_message')]); ?>
                                    </div>

                                    <div data-tnpshow="confirmation_message_custom=0" class="tnpc-default-text">
                                        <?php echo wp_kses_post($this->get_default_text('confirmation_message')) ?>
                                    </div>

                                </td>
                            </tr>
                        </table>

                    </div>

                </div>

            </div>

        </form>



    </div>
</div>

