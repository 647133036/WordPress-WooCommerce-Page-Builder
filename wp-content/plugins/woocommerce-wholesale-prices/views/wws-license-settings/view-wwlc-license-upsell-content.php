<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wws_settings_wwp" class="wws_license_settings_page_container">

    <div id="wwlc_wws_upgrade_to_premium_upsell" class="wws-license-manager-upsell-upgrade-to-premium-container">

        <!-- Content Header -->
        <div class="content-header">
            <h1><?php esc_html_e( 'Get Wholesale Lead Capture', 'woocommerce-wholesale-prices' ); ?></h1>
        </div>

        <!-- Content Body -->
        <div class="content-body">
            <div class="row-content">
                <div class="col-content">
                    <img
                        src="<?php echo esc_url( WWP_IMAGES_URL ); ?>upgrade-page-wwlc-box.png"
                        alt="<?php esc_attr_e( 'WooCommerce Wholesale Prices Premium', 'woocommerce-wholesale-prices' ); ?>"
                    />
                </div>
                <div class="col-content">
                    <p><?php esc_html_e( 'Wholesale Lead Capture provides a dedicated wholesale registration & login form for your wholesale customers, a full featured user approvals system, and email sequences so you can capture and onboard new wholesale customers with ease.', 'woocommerce-wholesale-prices' ); ?></p>

                    <ul>
                        <li>+ <?php esc_html_e( 'Automatically recruit & register wholesale customers', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Save huge amounts of admin time & recruit on autopilot', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Full registration form builder', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Custom fields capability to capture all required information', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Full automated mode OR manual approvals mode', 'woocommerce-wholesale-prices' ); ?></li>
                    </ul>

                    <p><a
                            class="action-button"
                            href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-lead-capture', 'wwp', 'licensepage', 'WWLClicenseupsell' ) ); ?>"
                            target="_blank"
                        ><?php esc_html_e( 'Get Wholesale Suite', 'woocommerce-wholesale-prices' ); ?></a></p>
                </div>
            </div>
        </div>

    </div>

</div><!--#wws_settings_wwpp-->
