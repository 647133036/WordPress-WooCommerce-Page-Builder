<?php
/** @var NewsletterEmailsAdmin $this */
/** @var NewsletterControls $controls */
$email = $this->get_email($_GET['id']);

if (!$email) {
    die('Newsletter not found');
}
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

            <?php $controls->logs('newsletter-' . $email->id, ['show_status' => false, 'show_data' => false]); ?>

        </form>

    </div>
</div>
