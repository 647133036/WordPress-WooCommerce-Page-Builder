<?php
/* @var $this NewsletterSubscription */
/* @var $wpdb wpdb */
defined('ABSPATH') || exit;

global $wpdb;

include_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
$controls = new NewsletterControls();

$items = $wpdb->get_results("select * from {$wpdb->options} where option_name like 'newsletter_subscription%' order by option_name");
array_walk($items, function ($item) {
    $item->option_name = strtoupper(substr($item->option_name, 24));
    if (empty($item->option_name)) $item->option_name = 'Main';
});
?>

<div class="wrap" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER ?>

    <div id="tnp-heading">
        <?php include __DIR__ . '/nav.php' ?>
    </div>

    <div id="tnp-body">

        <?php $controls->show(); ?>
        <?php $controls->init(); ?>

        <div id="tabs">

            <ul>
                <?php foreach ($items as $item) { ?>
                    <li><a href="#tabs-<?php echo esc_attr($item->option_name) ?>"><?php echo esc_html($item->option_name); ?></a></li>
                <?php } ?>
            </ul>

            <?php foreach ($items as $item) { ?>
                <div id="tabs-<?php echo esc_attr($item->option_name) ?>">
                    <pre><?php echo esc_html(json_encode(maybe_unserialize($item->option_value), JSON_PRETTY_PRINT)) ?></pre>
                </div>

            <?php } ?>

        </div>

    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER; ?>

</div>
