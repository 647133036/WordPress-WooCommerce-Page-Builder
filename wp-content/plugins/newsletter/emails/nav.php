<?php
$p = $_GET['page'];
?>
<ul class="tnp-nav">
    <li class="tnp-nav-title"><?php esc_html_e('Newsletters', 'newsletter'); ?></li>
    <li class="<?php echo $p === 'newsletter_emails_index' ? 'active' : '' ?>"><a href="?page=newsletter_emails_index"><?php esc_html_e('Newsletters', 'newsletter') ?></a></li>
    <li class="<?php echo $p === 'newsletter_emails_presets' ? 'active' : '' ?>"><a href="?page=newsletter_emails_presets"><?php esc_html_e('Templates', 'newsletter') ?></a></li>
    <li class="<?php echo $p === 'newsletter_emails_settings' ? 'active' : '' ?>"><a href="?page=newsletter_emails_settings"><?php esc_html_e('Settings', 'newsletter') ?></a></li>
    <li class="<?php echo $p === 'newsletter_emails_automated' ? 'active' : '' ?>"><a href="?page=newsletter_emails_automated"><?php esc_html_e('Recurring', 'newsletter') ?></a></li>
    <li class="<?php echo $p === 'newsletter_emails_autoresponder' ? 'active' : '' ?>"><a href="?page=newsletter_emails_autoresponder"><?php esc_html_e('Series', 'newsletter') ?></a></li>
    <li class="<?php echo $p === 'newsletter_statistics_index' ? 'active' : '' ?>">
        <?php if (class_exists('NewsletterReports')) { ?>
            <a href="?page=newsletter_reports_index"><?php esc_html_e('Statistics', 'newsletter') ?></a>
        <?php } else { ?>
            <a href="?page=newsletter_statistics_index"><?php esc_html_e('Statistics', 'newsletter') ?></a>
        <?php } ?>
    </li>
</ul>
<?php
unset($p);
?>