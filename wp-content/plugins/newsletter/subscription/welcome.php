<?php

defined('ABSPATH') || exit;

/** @var NewsletterSubscriptionAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
/** @var string $language */

$email = null;

if (!$controls->is_action()) {

    $controls->data = $this->get_options('', $language);

    $email = $this->get_email($controls->data['welcome_email_id'] ?? 0);

    if (!$email) {
        $email = NewsletterComposer::instance()->build_email_from_template('welcome-1');
        $email->status = TNP_Email::STATUS_SENT;

        $email->subject = $this->get_default_text('confirmed_subject');

        $email = $this->save_email($email);
        $controls->data['welcome_email_id'] = $email->id;
        $controls->data['welcome_email'] = '';
        $this->save_options($controls->data, '', $language);
    }

    $r = NewsletterComposer::instance()->regenerate($email);
    NewsletterComposer::update_controls($controls, $email);
} else {

    if (NEWSLETTER_DEBUG && $controls->is_action('delete')) {
        $options = $this->get_options('', $language);
        $this->delete_email($controls->data['welcome_email_id']);
        $options['welcome_email_id'] = 0;
        $this->save_options($controls->data, '', $language);
        $controls->js_redirect('?page=newsletter_subscription_welcome');
    }

    if ($controls->is_action('save')) {
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
        $email = Newsletter::instance()->get_email($options['welcome_email_id']);
        $email->track = Newsletter::instance()->get_option('track');
        NewsletterComposer::update_email($email, $controls);
        $email = NewsletterEmails::instance()->save_email($email);

        // Save the unfiltered values
        $tracking = $controls->data['confirmed_tracking'];

        $controls->data = wp_kses_post_deep($controls->data);

        if (current_user_can('unfiltered_html')) {
            $controls->data['confirmed_tracking'] = $tracking;
        }

        $options['confirmed_message'] = NewsletterModule::clean_url_tags($controls->data['confirmed_message']);
        $options['confirmed_subject'] = $controls->data['confirmed_subject'];
        $options['confirmed_text'] = $controls->data['confirmed_text'];
        $options['confirmed_tracking'] = $controls->data['confirmed_tracking'];
        $options['confirmed_id'] = $controls->data['confirmed_id'];
        $options['confirmed_url'] = $controls->data['confirmed_url'];
        $options['welcome_email'] = $controls->data['welcome_email'];

        $this->save_options($options, '', $language);

        $controls->add_toast_saved();
        $controls->data = $options;
        NewsletterComposer::update_controls($controls, $email);

        NewsletterMainAdmin::instance()->set_completed_step('welcome-email');
    }
}

foreach (['confirmed_message', 'confirmed_text'] as $key) {
    if (!empty($controls->data[$key])) {
        $controls->data[$key . '_custom'] = '1';
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

            <div id="tabs">
                <ul>
                    <li><a href="#tabs-settings"><?php esc_html_e('Page', 'newsletter') ?></a></li>
                    <li><a href="#tabs-email"><?php esc_html_e('Email', 'newsletter') ?></a></li>
                </ul>

                <div id="tabs-settings">

                    <?php //$this->language_notice(); ?>



                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Welcome page', 'newsletter') ?></th>
                            <td>
                                <?php $controls->page_or_url('confirmed', '', false); ?>

                            </td>
                        </tr>
                        <tr data-tnpshow="confirmed_id=0">
                            <th><?php esc_html_e('Page content', 'newsletter') ?></th>
                            <td>
                                <?php $controls->checkbox2('confirmed_text_custom', 'Customize'); ?>
                                <div data-tnpshow="confirmed_text_custom=1">
                                    <?php $controls->wp_editor('confirmed_text', ['editor_height' => 150], ['default' => $this->get_default_text('confirmed_text')]); ?>
                                </div>
                                <div data-tnpshow="confirmed_text_custom=0" class="tnpc-default-text">
                                    <?php echo wp_kses_post($this->get_default_text('confirmed_text')) ?>
                                </div>
                            </td>
                        </tr>



                        <tr data-tnpshow="confirmed_id=0">
                            <th><?php esc_html_e('Conversion tracking code', 'newsletter') ?>
                                <?php $controls->help('/subscription#conversion') ?></th>
                            <td>
                                <?php $controls->textarea('confirmed_tracking'); ?>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <?php $controls->button_save() ?>
                        <?php if (current_user_can('administrator')) { ?>
                            <?php $controls->btn_link($this->build_dummy_action_url('c'), __('Preview', 'newsletter'), ['tertiary' => true, 'target' => '_blank']); ?>
                        <?php } ?>
                    </p>


                </div>

                <div id="tabs-email">

                    <?php //$this->language_notice(); ?>


                    <?php
                    $controls->select('welcome_email',
                            ['0' => __('Default', 'newsletter'), '1' => __('Composer', 'newsletter'), '2' => __('Do not send', 'newsletter')]);
                    ?>

                    <?php $controls->button_save() ?>

                    <?php
                    $controls->button_icon_statistics(NewsletterStatisticsAdmin::instance()->get_statistics_url($controls->data['welcome_email_id']),
                            ['secondary' => true, 'id' => 'tnp-stats-button', 'target' => '_blank', 'data-tnpshow' => 'welcome_email=1'])
                    ?>

                    <?php if (NEWSLETTER_DEBUG) { ?>
                        <?php $controls->btn_link(home_url('/') . '?na=json&id=' . $email->id, '{}') ?>
                        <?php $controls->button_icon_delete(); ?>
                        [#<?php echo (int) $controls->data['welcome_email_id']; ?>]
                    <?php } ?>


                    <div id="tnp-composer-welcome" style="display: none" data-tnpshow="welcome_email=1">
                        <?php $controls->composer_v3(true, false); ?>
                    </div>

                    <div id="tnp-standard-welcome" style="display: none" data-tnpshow="welcome_email=0">
                        <table class="form-table">
                            <tr>
                                <td>

                                    <?php $controls->text('confirmed_subject', 70, $this->get_default_text('confirmed_subject')); ?>
                                    <br><br>
                                    <?php $controls->checkbox2('confirmed_message_custom', 'Customize', ['onchange' => 'tnp_refresh_binds()']); ?>

                                    <div data-tnpshow="confirmed_message_custom=1">
                                        <?php $controls->wp_editor('confirmed_message', ['editor_height' => 150], ['default' => $this->get_default_text('confirmed_message')]); ?>
                                    </div>

                                    <div data-tnpshow="confirmed_message_custom=0" class="tnpc-default-text">
                                        <?php echo wp_kses_post($this->get_default_text('confirmed_message')) ?>
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

