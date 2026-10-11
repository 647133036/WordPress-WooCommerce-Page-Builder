<?php
/**
 * View template for the Wholesale Payments (WPAY) upsell tab on the WWS License Settings page.
 *
 * Rendered by WWP_WWS_License_Manager::wpay_license_content() when the Wholesale Payments
 * plugin is not active.
 *
 * @since 2.2.8
 *
 * @package WooCommerceWholeSalePrices
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wws_settings_wwp" class="wws_license_settings_page_container">

    <div id="wpay_wws_upgrade_to_premium_upsell" class="wws-license-manager-upsell-upgrade-to-premium-container">

        <!-- Content Header -->
        <div class="content-header">
            <h1><?php esc_html_e( 'Get Wholesale Payments', 'woocommerce-wholesale-prices' ); ?></h1>
        </div>

        <!-- Content Body -->
        <div class="content-body">
            <div class="row-content">
                <div class="col-content">
                    <img
                        src="<?php echo esc_url( WWP_IMAGES_URL . 'upgrade-page-wpay-box.png' ); ?>"
                        alt="<?php esc_attr_e( 'WooCommerce Wholesale Payments', 'woocommerce-wholesale-prices' ); ?>"
                    />
                </div>
                <div class="col-content">
                    <p><?php esc_html_e( 'Wholesale Payments lets you offer NET 30, 60, or 90 day invoice terms to your wholesale customers so they can place orders now and pay later. Run a proper B2B credit workflow without the manual paperwork.', 'woocommerce-wholesale-prices' ); ?></p>

                    <ul>
                        <li>+ <?php esc_html_e( 'Offer NET 30/60/90 invoicing to wholesale customers', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Automated invoice generation and delivery', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Track outstanding balances per customer', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Send reminders and collect payments with ease', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Integrates cleanly with your existing wholesale pricing and roles', 'woocommerce-wholesale-prices' ); ?></li>
                    </ul>

                    <p><a
                            class="action-button"
                            href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-payments', 'wwp', 'licensepage', 'WPAYlicenseupsell' ) ); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        ><?php esc_html_e( 'Get Wholesale Suite', 'woocommerce-wholesale-prices' ); ?></a></p>
                </div>
            </div>
        </div>

    </div>

</div><!--#wws_settings_wwp-->
