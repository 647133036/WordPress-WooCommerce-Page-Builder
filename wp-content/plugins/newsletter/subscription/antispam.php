<?php
/** @var NewsletterSubscriptionAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */

defined('ABSPATH') || exit;

if ($controls->is_action()) {

    if ($controls->is_action('save')) {
        $controls->data['address_blacklist'] = wp_parse_list($controls->data['address_blacklist']);

        $controls->data = wp_kses_post_deep($controls->data);
        $this->save_main_options($controls->data, 'antispam');
        $controls->add_toast_saved();
    }
} else {
    $controls->data = $this->get_main_options('antispam');
}

?>

<div class="wrap" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER ?>

    <div id="tnp-heading">
        <?php $controls->title_help('/subscription/antiflood') ?>
<!--        <h2><?php esc_html_e('Subscription', 'newsletter') ?></h2>-->
        <?php include __DIR__ . '/nav.php' ?>
    </div>

    <div id="tnp-body">

        <?php $controls->show(); ?>

        <form method="post" action="">
            <?php $controls->init(); ?>


            <div id="tabs" class="tnp-tabs">
                <ul>
                    <li><a href="#tabs-general"><?php esc_html_e('General', 'newsletter') ?></a></li>
                    <li><a href="#tabs-blacklists"><?php esc_html_e('Blacklists', 'newsletter') ?></a></li>
                    <li><a href="#tabs-logs"><?php esc_html_e('Logs', 'newsletter') ?></a></li>
                    <?php if (NEWSLETTER_DEBUG) { ?>
                        <li><a href="#tabs-debug">Debug</a></li>
                    <?php } ?>
                </ul>

                <div id="tabs-general">


                    <table class="form-table">
                        <tr>
                            <th>
                                <?php $controls->label(__('Disable', 'newsletter'), '/subscription/antiflood') ?>
                            </th>
                            <td>
                                <?php $controls->yesno('disabled'); ?>
                            </td>
                        </tr>

                        <tr>
                            <th><?php $controls->label('Akismet', '/subscription/antiflood#akismet') ?></th>
                            <td>
                                <?php
                                $controls->select('akismet', [
                                    0 => __('Disabled', 'newsletter'),
                                    1 => __('Enabled', 'newsletter')
                                ]);
                                ?>
                                <span class="description">
                                    Sends the subscription data to Akismet to know if it's spam
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <th><?php $controls->label(__('Antiflood', 'newsletter'), '/subscription/antiflood#antiflood') ?></th>
                            <td>
                                <?php
                                $controls->select('antiflood', array(
                                    0 => __('Disabled', 'newsletter'),
                                    5 => '5 ' . __('seconds', 'newsletter'),
                                    10 => '10 ' . __('seconds', 'newsletter'),
                                    15 => '15 ' . __('seconds', 'newsletter'),
                                    30 => '30 ' . __('seconds', 'newsletter'),
                                    60 => '1 ' . __('minute', 'newsletter'),
                                    120 => '2 ' . __('minutes', 'newsletter'),
                                    300 => '5 ' . __('minutes', 'newsletter'),
                                    600 => '10 ' . __('minutes', 'newsletter'),
                                    900 => '15 ' . __('minutes', 'newsletter'),
                                    1800 => '30 ' . __('minutes', 'newsletter'),
                                    360 => '60 ' . __('minutes', 'newsletter')
                                ));
                                ?>

                                <span class="description">
                                    Refuses repeated subscription from the same IP or for the same address by the specified interval.
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php $controls->label(__('Captcha', 'newsletter'), '/subscription/antiflood/#captcha') ?>
                            </th>
                            <td>
                                <?php $controls->enabled('captcha'); ?>
                                <span class="description">
                                    <?php esc_html_e('Shown after the form submission as a confirmation step', 'newsletter'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php /*
                          <tr>
                          <th><?php _e('Domain check', 'newsletter') ?></th>
                          <td>
                          <?php
                          $controls->yesno('domain_check');
                          ?>
                          </td>
                          </tr>
                         */ ?>

                    </table>


                </div>

                <div id="tabs-blacklists">
                    <table class="form-table">

                        <tr>
                            <th>
                                <?php $controls->label(__('Address blacklist', 'newsletter'), '/subscription/antiflood/#email-blacklist') ?>
                            </th>
                            <td>
                                <?php $controls->textarea('address_blacklist'); ?>
                                <p class="description"><?php esc_html_e('One per line', 'newsletter') ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tabs-logs">
                    <?php $controls->logs('antispam'); ?>
                </div>

                <?php if (NEWSLETTER_DEBUG) { ?>
                    <div id="tabs-debug">
                        <pre><?php echo esc_html(json_encode($this->get_db_options('antispam'), JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                <?php } ?>

            </div>

            <p>
                <?php $controls->button_save() ?>
            </p>

        </form>

    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER; ?>

</div>
