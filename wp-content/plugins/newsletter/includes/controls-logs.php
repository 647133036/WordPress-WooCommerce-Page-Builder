<?php
/** @var NewsletterControls $this */
/** @var string $source */

use Newsletter\Logs;

global $wpdb;

require_once NEWSLETTER_INCLUDES_DIR . '/paginator.php';

$paginator = new TNP_Pagination_Controller($wpdb->prefix . 'newsletter_logs', 'id', ['source' => $source]);
$logs = $paginator->get_items();

$ajax_url = wp_nonce_url(admin_url('admin-ajax.php') . '?action=newsletter-log', 'newsletter-log');

$show_status = $attrs['show_status'] ?? $attrs['status'] ?? true;
$show_data = $attrs['show_data'] ?? true;
?>


<?php if (empty($logs)) { ?>
    <p>No logs.</p>
<?php } else { ?>

    <?php $paginator->display_paginator(); ?>
    <table class="widefat">
        <thead>
            <tr>
                <th style="width: 1%">#</th>
                <th>Date</th>
                <?php if ($show_status) { ?>
                    <th>Status</th>
                <?php } ?>
                <th>Description</th>
                <?php if ($show_data) { ?>
                    <th>Data</th>
                <?php } ?>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($logs as $log) { ?>
                <tr>
                    <td style="width: 1%"><?php echo esc_html($log->id); ?></td>
                    <td style="width: 5%; white-space: nowrap"><?php echo esc_html($this->print_date($log->created)); ?></td>
                    <?php if ($show_status) { ?>
                        <td><?php echo esc_html($log->status) ?></td>
                    <?php } ?>
                    <td><?php echo esc_html($log->description) ?></td>
                    <?php if ($show_data) { ?>
                        <td>
                            <?php if (!empty($log->data)) $this->button_icon_view($ajax_url . '&id=' . $log->id) ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php }

