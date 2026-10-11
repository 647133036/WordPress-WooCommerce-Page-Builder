<?php if ( ! defined( 'ABSPATH' ) ) {
exit;} // Exit if accessed directly ?>

<div id="wws_settings_wwp" class="wws_license_settings_page_container">

    <div id="wwof_wws_upgrade_to_premium_upsell" class="wws-license-manager-upsell-upgrade-to-premium-container">

        <!-- Content Header -->
        <div class="content-header">
            <h1><?php esc_html_e( 'Get Wholesale Order Form', 'woocommerce-wholesale-prices' ); ?></h1>
        </div>

        <!-- Content Body -->
        <div class="content-body">
            <div class="row-content">
                <div class="col-content">
                    <img src="<?php echo esc_url( WWP_IMAGES_URL ); ?>upgrade-page-wwof-box.png" alt="<?php esc_attr_e( 'WooCommerce Wholesale Prices Premium', 'woocommerce-wholesale-prices' ); ?>"/>
                </div>
                <div class="col-content">
                    <p><?php esc_html_e( 'Wholesale Order Form lets you add efficient one-page order forms to showcase your wholesale catalog all in one place. Easily create multiple, high speed, customizable order forms with smart searching & filtering that your customers will love.', 'woocommerce-wholesale-prices' ); ?></p>

                    <ul>
                        <li>+ <?php esc_html_e( 'Most efficient one-page WooCommerce ordering form', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'No page loading/reloading, fully AJAX enabled', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Advanced searching and category filtering', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Your whole catalog at your customer’s fingertips', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Easily create multiple unique order forms for a range of use cases', 'woocommerce-wholesale-prices' ); ?></li>
                    </ul>

                    <p><a class="action-button" href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-order-form', 'wwp', 'licensepage', 'WWOFlicenseupsell' ) ); ?>" target="_blank"><?php esc_html_e( 'Get Wholesale Suite', 'woocommerce-wholesale-prices' ); ?></a></p>
                </div>
            </div>
        </div>

    </div>

</div><!--#wws_settings_wwpp-->
