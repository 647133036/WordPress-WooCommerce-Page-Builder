<?php
/**
 * View template for the Wholesale Quotes (WWQ) upsell tab on the WWS License Settings page.
 *
 * Rendered by WWP_WWS_License_Manager::wwq_license_content() when the Wholesale Quotes
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

    <div id="wwq_wws_upgrade_to_premium_upsell" class="wws-license-manager-upsell-upgrade-to-premium-container">

        <!-- Content Header -->
        <div class="content-header">
            <h1><?php esc_html_e( 'Get Wholesale Quotes', 'woocommerce-wholesale-prices' ); ?></h1>
        </div>

        <!-- Content Body -->
        <div class="content-body">
            <div class="row-content">
                <div class="col-content">
                    <img
                        src="<?php echo esc_url( WWP_IMAGES_URL . 'upgrade-page-wwq-box.png' ); ?>"
                        alt="<?php esc_attr_e( 'WooCommerce Wholesale Quotes', 'woocommerce-wholesale-prices' ); ?>"
                    />
                </div>
                <div class="col-content">
                    <p><?php esc_html_e( 'Wholesale Quotes enables your customers to request custom quotes for bulk orders, lets you manage and approve quote requests, convert quotes to orders, and streamline your full wholesale sales workflow.', 'woocommerce-wholesale-prices' ); ?></p>

                    <ul>
                        <li>+ <?php esc_html_e( 'Enable customers to request quotes for bulk orders', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Quote approval and management system', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Convert approved quotes to orders seamlessly', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Adjust pricing and terms per quote', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Works alongside your existing wholesale pricing and roles', 'woocommerce-wholesale-prices' ); ?></li>
                    </ul>

                    <p><a
                            class="action-button"
                            href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-quotes', 'wwp', 'licensepage', 'WWQlicenseupsell' ) ); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        ><?php esc_html_e( 'Get Wholesale Suite', 'woocommerce-wholesale-prices' ); ?></a></p>
                </div>
            </div>
        </div>

    </div>

</div><!--#wws_settings_wwp-->
