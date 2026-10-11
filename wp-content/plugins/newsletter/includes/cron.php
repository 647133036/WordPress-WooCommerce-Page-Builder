<?php

/**
 * Set up and log the cron system. To be loaded at the plugin start.
 */
defined('ABSPATH') || exit;

// Can be redefined in wp-config.php (not recommended)
if (!defined('NEWSLETTER_CRON_INTERVAL')) {
    define('NEWSLETTER_CRON_INTERVAL', 300);
}

// Try to use the defined cron interval. If it is not a number use 300. If less than 60 or over 900 apply those contraints
define('NEWSLETTER_REAL_CRON_INTERVAL', min(max(60, intval(NEWSLETTER_CRON_INTERVAL) ?: 300), 900));

// Logging of the cron calls to debug the so many situations where the cron is not triggered at all (grrr...).
if (defined('DOING_CRON') && DOING_CRON) {
    $calls = get_option('newsletter_diagnostic_cron_calls', []);
    // Protection against scrambled options or bad written database caching plugin (yes, it happened, grrr...).
    if (!is_array($calls)) {
        $calls = [];
    }
    $calls[] = time();
    if (count($calls) > 100) {
        array_shift($calls);
    }
    update_option('newsletter_diagnostic_cron_calls', $calls, false);
}

// As soon as possible but with low priority so it is ecxecutes as last filter to avoid bad witten
// filters which remove other's schedules (yes, it happened, grrr...).
add_filter('cron_schedules', function ($schedules) {
    $schedules['newsletter'] = [
        'interval' => NEWSLETTER_REAL_CRON_INTERVAL,
        'display' => 'Newsletter plugin'
    ];
    return $schedules;
}, 1000);

// Attempt to record problems with the scheduler...
add_action('cron_reschedule_event_error', function ($result, $hook, $v) {
    \Newsletter\Logs::add('cron', 'Reschedule error: ' . $hook, 0, $result);
}, 1, 3);

add_action('cron_unschedule_event_error', function ($result, $hook, $v) {
    \Newsletter\Logs::add('cron', 'Unschedule error: ' . $hook, 0, $result);
}, 1, 3);
