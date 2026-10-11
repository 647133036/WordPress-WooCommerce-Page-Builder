<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wws_settings_wwp" class="wws_license_settings_page_container">

    <div id="wwp_wws_upgrade_to_premium_upsell" class="wws-license-manager-upsell-upgrade-to-premium-container">

        <!-- Content Header -->
        <div class="content-header">
            <h1><?php esc_html_e( 'Get Wholesale Prices Premium', 'woocommerce-wholesale-prices' ); ?></h1>
            <span>
                <?php
                // translators: %1$s <strong> tag, %2$s </strong> tag.
                echo wp_kses_post( sprintf( __( 'Currently using: %1$sFree Version%2$s', 'woocommerce-wholesale-prices' ), '<strong>', '</strong>' ) );
                ?>
            </span>
        </div>

        <!-- Content Body -->
        <div class="content-body">
            <div class="row-content">
                <div class="col-content">
                    <img
                        src="<?php echo esc_url( WWP_IMAGES_URL ); ?>upgrade-page-wwpp-box.png"
                        alt="<?php esc_attr_e( 'WooCommerce Wholesale Prices Premium', 'woocommerce-wholesale-prices' ); ?>"
                    />
                </div>
                <div class="col-content">
                    <p><?php esc_html_e( 'Wholesale Prices Premium gives you a massive range of extra wholesale features for pricing, tax, shipping, payment, user roles, product visibility & more. Premium functions as an add-on to Wholesale Prices Free so you need to have that installed & activate.', 'woocommerce-wholesale-prices' ); ?></p>

                    <ul>
                        <li>+ <?php esc_html_e( 'Global & category level wholesale pricing', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( '“Wholesale Only” products', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Hide wholesale products from retail customers', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Multiple levels of wholesale user roles', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Manage wholesale pricing over multiple user tiers', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Shipping mapping', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Payment gateway mapping', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Tax exemptions & fine grained tax display control', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( 'Order minimum quantities & subtotals', 'woocommerce-wholesale-prices' ); ?></li>
                        <li>+ <?php esc_html_e( '100’s of other premium pricing related features', 'woocommerce-wholesale-prices' ); ?></li>
                    </ul>

                    <p><a
                            class="action-button"
                            href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'licensepage', 'WWPPlicenseupsell' ) ); ?>"
                            target="_blank"
                        ><?php esc_html_e( 'Get Wholesale Suite', 'woocommerce-wholesale-prices' ); ?></a></p>
                </div>
            </div>
        </div>

    </div>

</div><!--#wws_settings_wwpp-->
