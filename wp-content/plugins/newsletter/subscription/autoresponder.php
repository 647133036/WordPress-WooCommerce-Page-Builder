<?php
/** @var NewsletterSubscriptionAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */

defined('ABSPATH') || exit;
?>

<div class="wrap" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
        <?php $controls->title_help('/subscription') ?>
        <?php include __DIR__ . '/nav.php' ?>

    </div>

    <div id="tnp-body">


        <?php $controls->show(); ?>

        <?php if (!class_exists('NewsletterAutoresponder')) { ?>

            <p>
                To create a welcome series the Autoresponder Addon is required.
            </p>


        <?php } else { ?>

            <p>
                Configure your welcome/follow series on the <a href="?page=newsletter_autoresponder_index">Autoresponder settings page</a>.
            </p>

        <?php } ?>

    </div>
</div>

