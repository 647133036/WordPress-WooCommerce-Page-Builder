<?php
$p = sanitize_key($_GET['page'] ?? '');
?>
<ul class="tnp-nav">
    <li class="tnp-nav-title"><?php esc_html_e('Forms', 'newsletter') ?></li>
    <li class="<?php echo $p === 'newsletter_subscription_sources' ? 'active' : '' ?>"><a href="?page=newsletter_subscription_sources">All</a></li>
    <li class="<?php echo $p === 'newsletter_subscription_form' ? 'active' : '' ?>"><a href="?page=newsletter_subscription_form">Standard</a></li>
    <li class="<?php echo $p === 'newsletter_subscription_inject' ? 'active' : '' ?>"><a href="?page=newsletter_subscription_inject">Inside posts</a></li>
    <li class="<?php echo $p === 'newsletter_subscription_popup' ? 'active' : '' ?>"><a href="?page=newsletter_subscription_popup">Popup</a></li>
    <li class="<?php echo $p === 'newsletter_subscription_shortcodes' ? 'active' : '' ?>"><a href="?page=newsletter_subscription_shortcodes">Shortcodes and Widgets</a></li>
    <li class="<?php echo $p === 'newsletter_subscription_forms' ? 'active' : '' ?>"><a href="?page=newsletter_subscription_forms">HTML Forms</a></li>
    <?php if (class_exists('NewsletterLeads')) { ?>
    <li class="<?php echo $p === 'newsletter_leads_index' ? 'active' : '' ?>"><a href="?page=newsletter_leads_index">Leads Addon</a></li>
    <?php } ?>
</ul>
<?php
unset($p);
?>