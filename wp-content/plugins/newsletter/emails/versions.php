<?php
/** @var NewsletterEmailsAdmin $this */
/** @var NewsletterControls $controls */
defined('ABSPATH') || exit;

global $wpdb;

$email = $this->get_email((int) $_GET['id']);

if (!$email) {
    die('Newsletter not found');
}

$can_restore = $email->status == TNP_Email::STATUS_DRAFT || $email->status == TNP_Email::STATUS_PAUSED;

if ($controls->is_action('restore')) {
    if (!$can_restore) {
        die('Cannot restore');
    }
    $log = $wpdb->get_row($wpdb->prepare("select * from {$wpdb->prefix}newsletter_logs where id=%d limit 1", (int) $controls->button_data));
    if (!$log) {
        die('Invalid log');
    }
    if ($log->source !== 'newsletter-version-' . $email->id) {
        die('Invalid log');
    }

    Newsletter\Logs::add('newsletter-version-' . $email->id, date('Y-m-d H:i:s'), 0, $email->message);

    $data = [
        'id' => $email->id,
        'message' => $log->data
    ];
    $this->save_email($data);
    $controls->add_toast('Restored.');
}

use Newsletter\Logs;

require_once NEWSLETTER_INCLUDES_DIR . '/paginator.php';

$paginator = new TNP_Pagination_Controller($wpdb->prefix . 'newsletter_logs', 'id', ['source' => 'newsletter-version-' . $email->id]);
$logs = $paginator->get_items();
?>

<div class="wrap" id="tnp-wrap">
    <?php include NEWSLETTER_ADMIN_HEADER; ?>
    <div id="tnp-heading">
        <?php include __DIR__ . '/edit-nav.php'; ?>
    </div>

    <div id="tnp-body">
        <h2><?php echo esc_html($email->subject); ?></h2>
        <form method="post" action="">
            <?php $controls->init(); ?>



            <?php if (empty($logs)) { ?>
                <p>No versions.</p>
            <?php } else { ?>

                <?php $paginator->display_paginator(); ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th style="width: 1%">#</th>
                            <th>Date</th>

                            <th>Description</th>

                            <th></th>
                            <th></th>

                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($logs as $log) { ?>
                            <tr>
                                <td style="width: 1%"><?php echo esc_html($log->id); ?></td>
                                <td style="width: 5%; white-space: nowrap"><?php echo esc_html($controls->print_date($log->created)); ?></td>

                                <td><?php echo esc_html($log->description) ?></td>

                                <td>
                                    <?php
                                    $ajax_url = wp_nonce_url($this->build_action_url_ajax('emails-version-preview'), 'preview');
                                    ?>
                                    <?php $controls->button_icon_view($ajax_url . '&id=' . $log->id) ?>
                                </td>
                                <td>
                                    <?php $can_restore && $controls->button_icon('restore', 'fa-redo', 'Restore', $log->id, true); ?>
                                </td>

                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>


        </form>

    </div>
</div>
