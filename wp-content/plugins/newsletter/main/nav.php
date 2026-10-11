<?php ?>
<ul class="tnp-nav">
    <li class="tnp-nav-title"><?php esc_html_e('Settings', 'newsletter') ?></li>
    <li class="<?php echo $_GET['page'] === 'newsletter_main_main' ? 'active' : '' ?>"><a href="?page=newsletter_main_main"><?php esc_html_e('General', 'newsletter') ?></a></li>
    <li class="<?php echo $_GET['page'] === 'newsletter_main_info' ? 'active' : '' ?>"><a href="?page=newsletter_main_info"><?php esc_html_e('Company', 'newsletter') ?></a></li>
    <li class="<?php echo $_GET['page'] === 'newsletter_main_welcome' ? 'active' : '' ?>"><a href="?page=newsletter_main_welcome"><?php esc_html_e('Setup Wizard', 'newsletter') ?></a></li>
    <li class=""><a href="?page=newsletter_statistics_settings"><?php esc_html_e('Statistics', 'newsletter') ?></a></li>
</ul>
