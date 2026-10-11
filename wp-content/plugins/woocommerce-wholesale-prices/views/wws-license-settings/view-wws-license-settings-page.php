<?php
/**
 * View template for the WWS License Settings page.
 *
 * Renders the page wrapper, Wholesale Suite logo, page title, tab navigation,
 * and the content for the currently active license tab.
 *
 * @since 2.1.3
 * @since 2.2.8 Added wrap container with Wholesale Suite logo and page title heading above the tab strip; styles provided via the License Settings admin stylesheet.
 *
 * @package WooCommerceWholeSalePrices
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wws_license_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'wwpp'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="wrap">

    <div class="wwp-license-logo">
        <a href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'logo' ) ); ?>" target="_blank" rel="noopener noreferrer">
            <img src="<?php echo esc_url( WWP_IMAGES_URL . 'logo.png' ); ?>" alt="<?php esc_attr_e( 'Wholesale Suite', 'woocommerce-wholesale-prices' ); ?>" />
        </a>
    </div>

    <div class="wp-header-end"></div>

    <h1 class="wwp-settings-heading"><?php esc_html_e( 'WWS License Settings', 'woocommerce-wholesale-prices' ); ?></h1>

    <h2 class="nav-tab-wrapper">
        <?php do_action( 'wws_action_license_settings_tab' ); ?>
    </h2>

    <?php do_action( 'wws_action_license_settings_' . $wws_license_tab ); ?>

</div>
