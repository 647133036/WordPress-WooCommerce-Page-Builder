<?php
?>
<ul class="tnp-nav">
    <li class="tnp-nav-title"><?php esc_html_e('Statistics', 'newsletter'); ?></li>
    <li class="<?php echo $_GET['page'] === 'newsletter_statistics_index'?'active':''?>"><a href="?page=newsletter_statistics_index"><?php esc_html_e('Overview', 'newsletter')?></a></li>
    <li class="<?php echo $_GET['page'] === 'newsletter_statistics_newsletters'?'active':''?>"><a href="?page=newsletter_statistics_newsletters"><?php esc_html_e('Newsletters', 'newsletter')?></a></li>
    <li class="<?php echo $_GET['page'] === 'newsletter_statistics_settings'?'active':''?>"><a href="?page=newsletter_statistics_settings"><?php esc_html_e('Settings', 'newsletter')?></a></li>
    <?php /*
    <li class="<?php echo $_GET['page'] === 'newsletter_reports_indexurls'?'active':''?>"><a href="?page=newsletter_reports_indexurls"><?php _e('Links', 'newsletter')?></a></li>
    */ ?>
</ul>
