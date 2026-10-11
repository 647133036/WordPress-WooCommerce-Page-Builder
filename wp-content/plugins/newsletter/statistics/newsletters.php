<?php

defined('ABSPATH') || exit;

global $wpdb;

/** @var NewsletterStatisticsAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */

$emails = $wpdb->get_results("select send_on, id, subject, total, sent, type, status, stats_time, open_count, click_count, error_count, unsub_count from " . NEWSLETTER_EMAILS_TABLE . " where status='sent' and type='message' order by send_on desc limit 20");

// Calculates the aggregates
$email_ids = [];
foreach ($emails as $email) {
    // Get updated statistics for each newsletter (cound be very slow if many newsletters need to be updated)
    $data = $this->get_statistics($email);

    $email_ids[] = $email->id;

    if (empty($data->total)) {
        //continue;
    }

    // Used later for the tabled view
    $email->report = $data;
}
?>


<div class="wrap" id="tnp-wrap">
    <?php include NEWSLETTER_ADMIN_HEADER ?>
    <div id="tnp-heading">

        <?php $controls->title_help('/reports-extension') ?>
        <?php include __DIR__ . '/index-nav.php' ?>

    </div>

    <div id="tnp-body" class="tnp-statistics">

        <p>
            Overview of the last 20 newsletters.
        </p>

        <table data-sortable class="widefat">
            <thead>
                <tr class="text-left">
                    <th>#</th>
                    <th>Subject</th>
                    <th>Subscribers</th>
                    <th>Opens (%)</th>
                    <th>Clicks (%)</th>
                    <th>Reactivity (%)</th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emails as $email) { ?>
                    <tr>
                        <td><?php echo $email->id ?></td>
                        <td><?php echo esc_html($email->subject ?: 'Newsletter #' . $email->id) ?></td>
                        <td><?php echo $email->report->total ?></td>
                        <td><?php echo $email->report->open_rate ?></td>
                        <td><?php echo $email->report->click_rate ?></td>
                        <td><?php echo $email->report->reactivity ?></td>
                        <td><?php $controls->button_icon_statistics('?page=newsletter_statistics_view&id=' . $email->id); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    </div>
</div>
