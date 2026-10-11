<?php
/** @var NewsletterStatisticsAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
defined('ABSPATH') || exit;

$debug = NEWSLETTER_DEBUG || isset($_GET['debug']);

if ($controls->is_action()) {
    if ($controls->is_action('save')) {
        $controls->data = $this->get_main_options();

        $controls->add_toast_saved();
    }
    
    if ($controls->is_action('regenerate')) {
        $options = $this->get_main_options();
        $options['key'] = wp_generate_password(32, false, false);
        $this->save_main_options($options);
        $controls->data = $this->get_main_options();

        $logger->info('Key changed');

        $controls->add_toast_saved();
    }
} else {
    $controls->data = $this->get_main_options();
}
?>

<div class="wrap tnp-statistics tnp-statistics-settings" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">

        <?php $controls->title_help('/reports-extension') ?>
        <?php include __DIR__ . '/index-nav.php' ?>

    </div>

    <div id="tnp-body">

        <?php $controls->show() ?>


        <form method="post" action="">
            <?php $controls->init(); ?>

            <div id="tabs">
                <ul>
                    <li><a href="#tabs-general"><?php esc_html_e('General', 'newsletter') ?></a></li>
                    <?php if ($debug) { ?>
                        <li><a href="#tabs-debug">Debug</a></li>
                    <?php } ?>
                </ul>

                <div id="tabs-general">
                    <table class="form-table">

                        <tr>
                            <th>Key</th>
                            <td>
                                <?php $controls->value('key'); ?>
                                <?php $controls->btn('regenerate', 'Regenerate', ['confirm' => true]) ?>
                                <p class="description">
                                    WARNING! If the key is regenerated the links in already sent newsletters won't work anymore.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>Old Key</th>
                            <td>
                                <?php $controls->value('old_key'); ?>
                                <p class="description">
                                    For support.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>Key Time</th>
                            <td>
                                <?php $controls->echo_date($controls->data['key_time'] ?? 0); ?>
                                <p class="description">
                                    For support.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if ($debug) { ?>
                    <div id="tabs-debug">
                        <pre><?php echo esc_html(wp_json_encode($this->get_db_options(''), JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                <?php } ?>
            </div>

        </form>

    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER; ?>

</div>
