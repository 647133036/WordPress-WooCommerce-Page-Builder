<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

wp_clear_scheduled_hook( 'wwp_cron_request_review' );
wp_clear_scheduled_hook( 'wwp_cron_install_acfwf_notice' );
wp_clear_scheduled_hook( 'wwp_wc_admin_note_join_store_owner_tips' );
wp_clear_scheduled_hook( 'wwp_wc_admin_note_wws_bundle' );
wp_clear_scheduled_hook( 'wwp_wc_admin_note_wws_youtube' );

// Delete options.
delete_option( 'wwp_activation_date' );
delete_option( 'wwp_admin_notice_getting_started_show' );
