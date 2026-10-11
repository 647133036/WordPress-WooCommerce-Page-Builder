<?php
/** @var NewsletterSubscriptionAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
/** @var string $language */

defined('ABSPATH') || exit;

if ($controls->is_action()) {
    if (NEWSLETTER_DEBUG && $controls->is_action('reset')) {
        $this->reset_options();
        $controls->js_redirect('?page=' . sanitize_key($_GET['page']));
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

        $controls->data = wp_kses_post_deep($controls->data);
        $this->merge_options($controls->data, '', $language);
        NewsletterMainAdmin::instance()->set_completed_step('notification');
        $controls->add_toast_saved();
    }

    if ($controls->is_action('test-confirmation')) {

        $users = $this->get_test_users();
        if (count($users) == 0) {
            $controls->errors = 'There are no test subscribers. Read more about test subscribers <a href="https://www.thenewsletterplugin.com/plugins/newsletter/subscribers-module#test" target="_blank">here</a>.';
        } else {
            $addresses = array();
            foreach ($users as &$user) {
                $addresses[] = $user->email;
                $user->language = $language;
                $res = NewsletterSubscription::instance()->send_confirmation_email($user);
                if (!$res) {
                    $controls->errors = 'The email address ' . $user->email . ' failed.';
                    break;
                }
            }
            $controls->messages .= 'Test emails sent to ' . count($users) . ' test subscribers: ' .
                    implode(', ', $addresses) . '. Read more about test subscribers <a href="https://www.thenewsletterplugin.com/plugins/newsletter/subscribers-module#test" target="_blank">here</a>.';
            $controls->messages .= '<br>If the message is not received, try to change the message text it could trigger some antispam filters.';
        }
    }

    if ($controls->is_action('test-confirmed')) {

        $users = $this->get_test_users();
        if (count($users) == 0) {
            $controls->errors = 'There are no test subscribers. Read more about test subscribers <a href="https://www.thenewsletterplugin.com/plugins/newsletter/subscribers-module#test" target="_blank">here</a>.';
        } else {
            $addresses = array();
            foreach ($users as $user) {
                $addresses[] = $user->email;
                // Force the language to send the message coherently with the current panel view
                $user->language = $language;
                $res = NewsletterSubscription::instance()->send_welcome_email($user);
                if (!$res) {
                    $controls->errors = 'The email address ' . $user->email . ' failed.';
                    break;
                }
            }
            $controls->messages .= 'Test emails sent to ' . count($users) . ' test subscribers: ' .
                    implode(', ', $addresses) . '. Read more about test subscribers <a href="https://www.thenewsletterplugin.com/plugins/newsletter/subscribers-module#test" target="_blank">here</a>.';
            $controls->messages .= '<br>If the message is not received, try to change the message text it could trigger some antispam filters.';
        }
    }
} else {
    $controls->data = $this->get_options('', $language);
}

foreach (['subscription_text', 'error_text'] as $key) {
    if (!empty($controls->data[$key])) {
        $controls->data[$key . '_custom'] = '1';
    }
}

update_option('newsletter_dummy_user_language', $language, false);
?>

<div class="wrap" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER ?>

    <div id="tnp-heading">
        <?php $controls->title_help('/subscription') ?>
<!--        <h2><?php esc_html_e('Subscription', 'newsletter') ?></h2>-->
        <?php include __DIR__ . '/nav.php' ?>
    </div>

    <div id="tnp-body">

        <?php $controls->show(); ?>

        <form method="post" action="">
            <?php $controls->init(); ?>

            <div id="tabs">
                <ul>
                    <li><a href="#tabs-subscription"><?php esc_html_e('Subscription', 'newsletter') ?></a></li>
                    <li class="tnp-tabs-advanced"><a href="#tabs-advanced"><?php esc_html_e('Advanced', 'newsletter') ?></a></li>
                    <?php if (NEWSLETTER_DEBUG) { ?>
                        <li><a href="#tabs-debug">Debug</a></li>
                    <?php } ?>
                </ul>

                <div id="tabs-subscription">
                    <?php $this->language_notice(); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php $controls->field_label(__('Opt In', 'newsletter'), '/subscription/subscription/') ?></th>
                            <td>
                                <?php $controls->select('noconfirmation', array(0 => __('Double Opt In', 'newsletter'), 1 => __('Single Opt In', 'newsletter'))); ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Subscription page', 'newsletter') ?></th>
                            <td>

                                <?php $controls->checkbox2('subscription_text_custom', 'Customize'); ?>
                                <div data-tnpshow="subscription_text_custom=1">
                                    <?php $controls->wp_editor('subscription_text', ['editor_height' => 150], ['default' => $this->get_default_text('subscription_text')]); ?>
                                    <p class="description">
                                        Remember to add at least the <code>[newsletter_form]</code> shortcode
                                        (<a href="https://www.thenewsletterplugin.com/documentation/subscription/subscription-form-shortcodes/#attributes" target="_blank">see all the options</a>).
                                        Remove the shortcode if you don't want to show the subscription form.
                                    </p>
                                </div>
                                <div data-tnpshow="subscription_text_custom=0" class="tnpc-default-text">
                                    <?php echo wp_kses_post($this->get_default_text('subscription_text')) ?>
                                </div>


                            </td>
                        </tr>

                    </table>

                    <?php if (!$language) { ?>
                        <h3>Subscription of existing subscribers</h3>

                        <table class="form-table">

                            <tr>
                                <th>
                                    When confirmed
                                </th>
                                <td>
                                    <?php
                                    $controls->select('multiple', [
                                        '0' => __('Not allowed', 'newsletter'),
                                        '1' => __('Allowed', 'newsletter'),
                                        '2' => __('Allowed (single opt-in)', 'newsletter'),
                                        '3' => __('Allowed (double opt-in)', 'newsletter')
                                    ]);
                                    ?>
                                    <div data-tnpshow="multiple=0" style="margin-top: 1rem;">

                                        <?php $controls->checkbox2('error_text_custom', __('Customize', 'newsletter')); ?>
                                        <div data-tnpshow="error_text_custom=1">
                                            <?php $controls->wp_editor('error_text', ['editor_height' => 150], ['default' => $this->get_default_text('error_text')]); ?>
                                        </div>
                                        <div data-tnpshow="error_text_custom=0" class="tnpc-default-text">
                                            <?php echo wp_kses_post($this->get_default_text('error_text')) ?>
                                        </div>

                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    When unsubscribed
                                </th>
                                <td>
                                    <?php
                                    $controls->select('allow_unsubscribed', [
                                        '0' => __('Not allowed', 'newsletter'),
                                        '1' => __('Allowed', 'newsletter'),
                                    ]);
                                    ?>

                                </td>
                            </tr>
                            <tr>
                                <th>
                                    When bounced or complained
                                </th>
                                <td>
                                    Not allowed.
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    When not confirmed
                                </th>
                                <td>
                                    Allowed.
                                </td>
                            </tr>

                        </table>
                    <?php } ?>

                </div>

                <div id="tabs-advanced">

                    <?php $this->language_notice(); ?>

                    <?php if (!$language) { ?>
                        <table class="form-table">
                            <tr>
                                <th><?php $controls->field_label(__('Override Opt In', 'newsletter'), '/subscription/subscription/#advanced') ?></th>
                                <td>
                                    <?php $controls->yesno('optin_override'); ?>
                                </td>
                            </tr>

                            <tr>
                                <th><?php esc_html_e('Notifications', 'newsletter') ?></th>
                                <td>
                                    <?php $controls->yesno('notify'); ?>
                                    <?php $controls->text_email('notify_email'); ?>
                                </td>
                            </tr>
                        </table>
                    <?php } ?>
                </div>

                <?php if (NEWSLETTER_DEBUG) { ?>
                    <div id="tabs-debug">
                        <?php $controls->button_reset(); ?>
                        <pre><?php echo esc_html(json_encode($this->get_db_options('', $language), JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                <?php } ?>

            </div>

            <p>
                <?php $controls->button_save(); ?>
            </p>

        </form>


    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER; ?>

</div>
