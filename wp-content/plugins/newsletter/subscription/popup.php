<?php
/** @var NewsletterSubscriptionAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
/** @var string $language */

defined('ABSPATH') || exit;

if (!$controls->is_action()) {
    $controls->data = $this->get_options('popup', $language);
} else {
    if ($controls->is_action('save')) {
        $controls->data = wp_kses_post_deep($controls->data);
        $this->save_options($controls->data, 'popup', $language);
        $controls->add_toast_saved();
        NewsletterMainAdmin::instance()->set_completed_step('forms');
    }
}

if (class_exists('NewsletterLeads')) {
    $controls->warnings[] = 'The Newsletter Leads Addon is active: disable this popup and configure the <a href="?page=newsletter_leads_index">full-featured popup</a>';
}
?>

<div class="wrap" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
        <?php $controls->title_help('/subscription') ?>
        <?php include __DIR__ . '/nav-forms.php' ?>
    </div>

    <div id="tnp-body">

        <?php $controls->show(); ?>
        <?php $controls->language_notice(); ?>

        <p>
            Starting from the 2nd visit and after 5 seconds. It doesn't show up if the visitor is already subscribed.
            When closed it shows up again after 30 days.
        </p>
        <p>
            More options are available with the <a href="<?php echo esc_attr(Newsletter\Integrations::get_leads_url()) ?>" target="_blank">Leads Addon</a>.
        </p>


        <form action="" method="post">
            <?php $controls->init(); ?>

            <div id="tabs">
                <ul>
                    <li><a href="#tabs-settings"><?php esc_html_e('Settings', 'newsletter') ?></a></li>
                    <?php if (NEWSLETTER_DEBUG) { ?>
                        <li><a href="#tabs-debug">Debug</a></li>
                    <?php } ?>
                </ul>

                <div id="tabs-settings">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Enabled', 'newsletter') ?></th>
                            <td>
                                <?php $controls->yesno('enabled'); ?>
                                <a href="<?php echo esc_attr($this->add_qs(home_url('/'), 'tnp-popup-test=1')) ?>" target="_blank"><?php esc_html_e('Preview', 'newsletter') ?></a>

                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Shown before the form', 'newsletter'); ?></th>
                            <td>
                                <?php $controls->wp_editor('text', ['editor_height' => 150], ['body_background' => '#ccc']); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if (NEWSLETTER_DEBUG) { ?>
                    <div id="tabs-debug">
                        <?php //$controls->button_reset(); ?>
                        <pre><?php echo esc_html(json_encode($this->get_db_options('popup', $language), JSON_PRETTY_PRINT)) ?></pre>
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
