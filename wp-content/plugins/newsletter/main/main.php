<?php
/** @var NewsletterMainAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
/** @var wpdb $wpdb */

/** @var string $language */
use Newsletter\License;

defined('ABSPATH') || exit;

if (!$controls->is_action()) {
    $controls->data = $this->get_options('', $language);
} else {

    if ($controls->is_action('save')) {

        if (!$language) {

            $css = $controls->data['css'] ?? '';

            if (!$this->is_html_allowed()) {
                $controls->data = wp_kses_post_deep($controls->data);
                // CSS must not be filtered!
                $controls->data['css'] = $css;
            }

            if (preg_match('#</?\w+#', $css)) {
                $controls->errors .= __('Invalid CSS', 'newsletter') . '<br>';
            }

            if (!$this->is_email($controls->data['sender_email'])) {
                $controls->errors .= __('The sender email address is not correct.', 'newsletter') . '<br>';
            } else {
                $controls->data['sender_email'] = $this->normalize_email($controls->data['sender_email']);
            }

            if (!$this->is_email($controls->data['return_path'], true)) {
                $controls->errors .= __('Return path email is not correct.', 'newsletter') . '<br>';
            } else {
                $controls->data['return_path'] = $this->normalize_email($controls->data['return_path']);
            }

            $controls->data['scheduler_max'] = (int) $controls->data['scheduler_max'];
            if ($controls->data['scheduler_max'] < 12) {
                $controls->data['scheduler_max'] = 12;
            }

            $send_delay = intval($controls->data['send_delay'] ?? 0);
            $send_delay = min(max(0, $send_delay), 5000);
            $controls->data['send_delay'] = $send_delay;

            $autorecovery_delay = intval($controls->data['autorecovery_delay'] ?? 0);
            $autorecovery_delay = min(max(0, $autorecovery_delay), 60);
            $controls->data['autorecovery_delay'] = $autorecovery_delay;

            if (!$this->is_email($controls->data['reply_to'], true)) {
                $controls->errors .= __('Reply to email is not correct.', 'newsletter') . '<br>';
            } else {
                $controls->data['reply_to'] = $this->normalize_email($controls->data['reply_to']);
            }

            if (!empty($controls->data['contract_key'])) {
                $controls->data['contract_key'] = trim($controls->data['contract_key']);
            }

            if (empty($controls->data['schedule_hours'])) {
                $controls->data['schedule_hours'] = [];
            } else {
                $controls->data['schedule_hours'] = array_map('intval', $controls->data['schedule_hours']);
            }

            if (empty($controls->data['schedule_hours'])) {
                $controls->data['schedule'] = 0;
            }

//            $schedule_hours = [];
//            if ($end < $start) $end += 24;
//            for ($i=$start; $i<$end; $i++) {
//                $schedule_hours[] = $i % 24;
//            }
//            $controls->data['schedule_hours'] = $schedule_hours;


            update_option('newsletter_log_level', $controls->data['log_level']);
        }

        if (empty($controls->errors)) {
            $this->save_options($controls->data, '', $language);
            $controls->add_toast_saved();
            if (!$language) {
                $this->logger->debug('Main options saved');
                NewsletterMainAdmin::instance()->set_completed_step('sender');
                if ($controls->data['scheduler_max'] != 100) {
                    NewsletterMainAdmin::instance()->set_completed_step('delivery-speed');
                }
            }
        }

        Newsletter\Addons::clear();
        Newsletter\News::clear();
        update_option('newsletter_public_page_check', 0, false);

        License::update();
    }

    if ($controls->is_action('create')) {
        $page = [];
        $page['post_title'] = 'Newsletter';
        $page['post_content'] = '[newsletter]';
        $page['post_status'] = 'publish';
        $page['post_type'] = 'page';
        $page['comment_status'] = 'closed';
        $page['ping_status'] = 'closed';
        $page['post_category'] = [1];

        $current_language = $this->get_current_language();
        $this->switch_language('');
        // Insert the post into the database
        $page_id = wp_insert_post($page);
        $this->switch_language($language);

        $controls->data['page'] = $page_id;
        $this->save_options($controls->data);

        $controls->messages = 'A new page has been created';
    }
}

if (!$language) {
    $return_path = $controls->data['return_path'] ?? '';

    if (!empty($return_path)) {
        list($return_path_local, $return_path_domain) = explode('@', $return_path);

        $sender = $this->get_option('sender_email');
        list($sender_local, $sender_domain) = explode('@', $sender);

        if ($sender_domain != $return_path_domain) {
            $controls->warnings[] = __('Your Return Path domain is different from your Sender domain. Providers may require them to match.', 'newsletter');
        }
    }
}

$license_data = License::get_data();

if (is_wp_error($license_data)) {
    $controls->errors .= esc_html($license_data->get_error_message());
}

$mailer = Newsletter::instance()->get_mailer();
$mailer_speed = $mailer->get_speed();

// Public page type check
$front_page_id = (int) get_option('page_on_front');
$blog_page_id = (int) get_option('page_for_posts');

if (!empty($controls->data['page'])) {
    if ($controls->data['page'] == $front_page_id) {
        $controls->warnings[] = 'The blog front page has been set as the Newsletter public page, that is not recommended. Please create a dedicated page.';
    } elseif ($controls->data['page'] == $blog_page_id) {
        $controls->warnings[] = 'The blog post list page has been set as the Newsletter public page, that is not recommended. Please create a dedicated page.';
    }
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

<div class="wrap" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
        <?php $controls->title_help('https://www.thenewsletterplugin.com/plugins/newsletter/newsletter-configuration') ?>

<!--        <h2><?php esc_html_e('Settings', 'newsletter'); ?></h2>-->
        <?php include __DIR__ . '/nav.php' ?>

    </div>
    <div id="tnp-body" class="tnp-main-main">

        <?php $controls->show() ?>

        <?php if (!empty($controls->data['contract_key']) && !class_exists('NewsletterExtensions')) { ?>
            <div class="tnp-notice">
                Please, install the <a href="?page=newsletter_main_extensions">Addons Manager</a> to access all professional addons and features.
            </div>
        <?php } ?>


        <form method="post" action="">
            <?php $controls->init(); ?>

            <div id="tabs">

                <ul>
                    <li><a href="#tabs-basic"><?php esc_html_e('Settings', 'newsletter') ?></a></li>
                    <li><a href="#tabs-speed"><?php esc_html_e('Sending', 'newsletter') ?></a></li>
                    <li class="tnp-tabs-advanced"><a href="#tabs-advanced"><?php esc_html_e('Advanced', 'newsletter') ?></a></li>
                    <?php if (NEWSLETTER_DEBUG) { ?>
                        <li><a href="#tabs-debug">Debug</a></li>
                    <?php } ?>
                </ul>

                <div id="tabs-basic">
                    <?php $controls->language_notice(); ?>
                    <table class="form-table">
                        <?php if (!$language) { ?>
                            <tr>
                                <th>
                                    <?php esc_html_e('Sender email', 'newsletter') ?>
                                    <?php $controls->field_help('https://www.thenewsletterplugin.com/documentation/installation/newsletter-configuration/#sender') ?>
                                </th>
                                <td>
                                    <?php $controls->text_email('sender_email', 40); ?>
                                    <span class="description">
                                        Emails delivered with a <a href="https://www.thenewsletterplugin.com/documentation/installation/newsletter-configuration/#sender" target="_blank">different address</a>?
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php esc_html_e('Sender name', 'newsletter') ?>
                                </th>
                                <td>
                                    <?php $controls->text('sender_name', 40); ?>
                                </td>
                            </tr>

                            <tr>
                                <th><?php esc_html_e('License key', 'newsletter') ?></th>
                                <td>

                                    <?php if (defined('NEWSLETTER_LICENSE_KEY')) { ?>
                                        <?php esc_html_e('A license key is set', 'newsletter') ?>
                                    <?php } else { ?>
                                        <?php $controls->text('contract_key', 40); ?>
                                        <span class="description">
                                            <?php printf(__('Find it in <a href="%s" target="_blank">your account</a> page', 'newsletter'), "https://www.thenewsletterplugin.com/account") ?>
                                        </span>
                                    <?php } ?>
                                </td>
                            </tr>


                            <tr>
                                <th>
                                    <?php esc_html_e('Return path', 'newsletter') ?>
                                    <?php $controls->field_help('/installation/newsletter-configuration/#return-path') ?>
                                </th>
                                <td>
                                    <?php $controls->text_email('return_path', 40); ?>
                                    <span class="description">Some providers ignore it or <strong>block all emails</strong> if set</span>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php esc_html_e('Reply to', 'newsletter') ?>
                                    <?php $controls->field_help('/installation/newsletter-configuration/#reply-to') ?>
                                </th>
                                <td>
                                    <?php $controls->text_email('reply_to', 40); ?>

                                </td>
                            </tr>
                        <?php } ?>
                        <tr>
                            <th>
                                <?php esc_html_e('Public page', 'newsletter') ?>
                                <?php $controls->field_help('/installation/newsletter-configuration/#dedicated-page') ?>
                            </th>
                            <td>

                                <?php $controls->page('page', NEWSLETTER_DEBUG ? 'None (for debug)' : null, '', true); ?>

                                <?php
                                if (!$this->is_multilanguage() && empty($controls->data['page'])) {
                                    $controls->button('create', __('Create the page', 'newsletter'));
                                }
                                ?>

                                <p class="description">
                                    <?php esc_html_e('The page content must be only the shortcode [newsletter]', 'newsletter') ?>.
                                    <br>
                                    <?php esc_html_e('Do not choose the "front page" or "post list page"', 'newsletter') ?>.
                                </p>

                                <?php if ($this->is_multilanguage()) { ?>
                                    <p class="description">
                                        <?php esc_html_e('To be set for every language', 'newsletter'); ?>
                                    </p>
                                <?php } ?>

                            </td>
                        </tr>

                    </table>
                </div>

                <div id="tabs-speed">
                    <?php $controls->language_notice(); ?>
                    <?php if (!$language) { ?>
                        <table class="form-table">
                            <tr>
                                <th>
                                    <?php esc_html_e('Max emails per hour', 'newsletter') ?>
                                    <?php $controls->field_help('/installation/newsletter-configuration/#speed') ?>
                                </th>
                                <td>
                                    <?php $controls->text('scheduler_max', 5); ?> (min. 12)
                                    <p class="description">
                                        <a href="?page=newsletter_system_delivery#tnp-speed">See the collected statistics</a>

                                        <?php $batch_max = floor($controls->data['scheduler_max'] / 12); ?>
                                        <?php if ($batch_max < 5) { ?>
                                            <br>
                                            The delivery engines runs every 5 minutes (12 runs per hour). With that setting you'll have:
                                            <br>
                                            <?php echo $batch_max; ?> max emails every 5 minutes,
                                            <?php echo $batch_max * 12; ?> max emails per hour,
                                            <?php echo $batch_max * 12 * 24; ?> max emails per day
                                        <?php } ?>

                                        <?php if ($mailer_speed && $mailer_speed != $controls->data['scheduler_max']) { ?>
                                            <br><br>
                                            <strong>The maximum speed is overridden by the delivery addon "<?php echo esc_html($mailer->get_description()); ?>"</strong>
                                        <?php } ?>
                                    </p>


                                </td>
                            </tr>

                            <tr>
                                <th>
                                    <?php esc_html_e('Delay between emails', 'newsletter') ?>
                                    <?php $controls->field_help('/installation/newsletter-configuration/#speed') ?>
                                </th>
                                <td>
                                    <?php if (defined('NEWSLETTER_SEND_DELAY')) { ?>

                                        This value is set by the constant <code>NEWSLETTER_SEND_DELAY</code> in the site <code>wp-config.php</code>
                                        with value <?php echo esc_html(NEWSLETTER_SEND_DELAY); ?>.

                                    <?php } else { ?>
                                        <?php $controls->text('send_delay', 5); ?> milliseconds
                                    <?php } ?>
                                    <p class="description">
                                        From 0 (default) to 5000 (5 seconds). It's a time delay added after each email
                                        to respect specific provider contraints.
                                        <br>
                                        For example when your provider requires you to:<br>
                                        - send no more than 1 email every 2 seconds: set it to 2000.
                                        <br>
                                        - send no more than 10 emails per second: set it to 100 (1 second / 10 emails = 100 milliseconds).
                                    </p>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th>Limit sending hours</th>
                                <td>
                                    <?php $controls->enabled('schedule'); ?>

                                    <div data-tnpshow="schedule=1">

                                        <?php
                                        for ($i = 0; $i < 24; $i++) {
                                            echo '<div style="float:left; width: 30px; margin-right: 3px;">';
                                            echo '<label>';
                                            echo sprintf('%02d', $i);
                                            echo '<br>';
                                            $controls->checkbox_group('schedule_hours', $i);
                                            echo '</label>';
                                            echo '</div>';
                                        }
                                        ?>
                                        <p class="description" style="clear: both">
                                            Limit the sending to specified hours. Does not apply to email series and other transactional emails.
                                        </p>
                                    </div>


                                </td>
                            </tr>
                            <tr valign="top">
                                <th>Autorecovery delay</th>
                                <td>
                                    <?php
                                    $controls->select('autorecovery_delay', ['' => 'Never',
                                        '5' => '5', '10' => '10', '15' => '15', '20' => '20', '30' => '30',
                                        '40' => '40', '50' => '50', '60' => '60']);
                                    ?> (minutes)

                                    <p class="description">
                                        Minuto to wait before restarting the sending process when blocked by a fatal error
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <?php do_action('newsletter_panel_main_speed', $controls) ?>
                    <?php } ?>
                </div>


                <div id="tabs-advanced">
                    <?php $controls->language_notice(); ?>
                    <?php if (!$language) { ?>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Allowed roles', 'newsletter') ?></th>
                                <td>
                                    <?php
                                    $wp_roles = get_editable_roles();
                                    $roles = array();
                                    foreach ($wp_roles as $key => $wp_role) {
                                        if ($key == 'administrator')
                                            continue;
                                        if ($key == 'subscriber')
                                            continue;
                                        $roles[$key] = $wp_role['name'];
                                    }
                                    $controls->checkboxes('roles', $roles);
                                    ?>

                                </td>
                            </tr>

                            <tr>
                                <th>
                                    <?php $controls->label(__('Tracking default', 'newsletter'), '/installation/newsletter-configuration/#tracking') ?>
                                </th>
                                <td>
                                    <?php $controls->enabled('track'); ?>
                                    <span class="description">
                                        Applied only to new newsletters.
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    <?php $controls->label(__('Log level', 'newsletter'), '/newsletter-configuration#log') ?>
                                </th>
                                <td>
                                    <?php $controls->log_level('log_level'); ?>
                                </td>
                            </tr>

                            <tr>
                                <th><?php esc_html_e('Standard styles', 'newsletter') ?></th>
                                <td>
                                    <?php $controls->disabled('css_disabled'); ?>
                                </td>
                            </tr>

                            <tr>
                                <th><?php esc_html_e('Custom styles', 'newsletter') ?></th>
                                <td>
                                    <p class="description">
                                        Styles added to the site for the subscription and profile editing forms.
                                    </p>
                                    <?php if (apply_filters('newsletter_enqueue_style', true) === false) { ?>
                                        <p><strong>Warning: Newsletter styles and custom styles are disable by your theme or a plugin.</strong></p>
                                    <?php } ?>
                                    <?php $controls->textarea('css'); ?>

                                </td>
                            </tr>

                            <tr>
                                <th><?php esc_html_e('IP addresses', 'newsletter') ?></th>
                                <td>
                                    <?php $controls->select('ip', array('' => __('Store', 'newsletter'), 'anonymize' => __('Anonymize', 'newsletter'), 'skip' => __('Do not store', 'newsletter'))); ?>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    <?php $controls->label(__('Debug mode', 'newsletter'), '/newsletter-configuration#debug') ?>
                                </th>
                                <td>
                                    <?php $controls->yesno('debug', 40); ?>
                                    <span class="description">
                                        If PHP errors are intercepted they are logged <a href="?page=newsletter_system_logs">here</a>.
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    <?php $controls->label(__('Action links', 'newsletter'), '') ?>
                                </th>
                                <td>
                                    <?php $controls->select('links', ['' => 'Standard', 'ajax' => 'AJAX']); ?>
                                    <span class="description">
                                        Select "AJAX" if confirmation/unsubscription/tracking links seems to not work. Resend a test neswletter
                                        to check the effect, old newsletters are not affacted.
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <?php $controls->label(__('Tracking links', 'newsletter'), '') ?>
                                </th>
                                <td>
                                    <?php $controls->select('tracking_links', ['' => 'Standard', 'rest' => 'WP REST']); ?>
                                    <span class="description">

                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    <?php $controls->label(__('Email encoding', 'newsletter'), '/newsletter-configuration#encoding') ?>
                                </th>
                                <td>
                                    <?php $controls->select('content_transfer_encoding', array('' => 'Default', '8bit' => '8 bit', 'base64' => 'Base 64', 'binary' => 'Binary', 'quoted-printable' => 'Quoted printable', '7bit' => '7 bit')); ?>
                                </td>
                            </tr>


                        </table>
                    <?php } ?>
                </div>

                <?php if (NEWSLETTER_DEBUG) { ?>
                    <div id="tabs-debug">
                        <pre><?php echo esc_html(wp_json_encode($this->get_db_options('', $language), JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                <?php } ?>


            </div> <!-- tabs -->

            <div class="tnp-buttons">
                <?php $controls->button_save(); ?>
            </div>

        </form>

    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER; ?>

</div>

