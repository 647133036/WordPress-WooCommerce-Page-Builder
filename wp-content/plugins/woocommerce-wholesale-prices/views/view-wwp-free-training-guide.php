<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * WWP Email Capture Box for Store owners
 *
 * This will capture users email information and signs the user to our Drip sequence and delivers the PDF guide.
 *
 * @since 2.1.2
 */
?>

<div id="wwp-free-training-guide" class="wwp-page wrap nosubsub">
    <div class="row-container free-guide">
        <div class="page-title">
            <?php esc_html_e( 'FREE GUIDE: How To Setup Wholesale On Your WooCommerce Store', 'woocommerce-wholesale-prices' ); ?>
        </div>
        <div class="two-columns">
            <div class="left-box">
                <div class="page-title">
                    <?php esc_html_e( 'A Step-By-Step Guide For Adding Wholesale Ordering To Your Store', 'woocommerce-wholesale-prices' ); ?>
                </div>
                <p>
                    <?php
                        echo wp_kses_post(
                            sprintf( // translators: %1$s <strong> tag, %2$s </strong> tag.
                                __( 'If you\'ve ever wanted to grow a store to 6, 7 or 8-figures and beyond %1$sdownload this guide%2$s now. You\'ll learn how smart store owners are using coupons to grow their WooCommerce stores.', 'woocommerce-wholesale-prices' ),
                                '<strong>',
                                '</strong>'
                            ),
                        );
                    ?>
                </p>
                <ul>
                    <li><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'Learn exactly how to price your products ready for wholesale', 'woocommerce-wholesale-prices' ); ?></li>
                    <li><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'The free way to setup wholesale pricing for customers in WooCommerce', 'woocommerce-wholesale-prices' ); ?></li>
                    <li><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'Why you need an efficient ordering process', 'woocommerce-wholesale-prices' ); ?></li>
                    <li><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'How to find your ideal wholesale customers & recruit them', 'woocommerce-wholesale-prices' ); ?></li>
                </ul>
                <a href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'free-guide', 'wwp', 'settings', 'generalsettingfreeguidebutton' ) ); ?>" target="_blank" class="button-green"><?php esc_html_e( 'Get FREE Training Guide', 'woocommerce-wholesale-prices' ); ?></a>
            </div>
            <div class="right-box">
                <img id="wws-book-cover" src="<?php echo esc_url( WWP_IMAGES_URL ); ?>book-cover.png" alt="<?php esc_html_e( 'Free Guide', 'woocommerce-wholesale-prices' ); ?>" />
            </div>
        </div>
    </div>    
</div>
