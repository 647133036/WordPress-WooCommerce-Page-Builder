<?php if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wwq_is_installed = WWP_Helper_Functions::is_wwq_installed() ? ' wwq-installed' : '';
$wwq_is_active    = WWP_Helper_Functions::is_wwq_active() ? ' wwq-active' : '';

$plugin_file = 'woocommerce-wholesale-quotes/woocommerce-wholesale-quotes.php';

?>

<div id="wwp-wholesale-quotes-page" class="wwp-page wrap nosubsub">

    <div class="row-container">
    <img id="wws-logo" src="<?php echo esc_url( WWP_IMAGES_URL . '/logo.png' ); ?>" alt="<?php esc_html_e( 'Wholesale Suite', 'woocommerce-wholesale-prices' ); ?>" />
    </div>

    <div class="row-container">
    <div class="one-column">

        <div class="page-title"><?php esc_html_e( 'Streamline Your Wholesale Quote Process', 'woocommerce-wholesale-prices' ); ?></div>

        <p class="page-description">
        <?php
        echo wp_kses_post(
            sprintf(
                // translators: %s <br /> tag.
                __( 'Wholesale Quotes enables your customers to request custom quotes for bulk orders,%smanage quote requests, approve or modify quotes, convert quotes to orders, and streamline your wholesale sales workflow.', 'woocommerce-wholesale-prices' ),
                '<br />'
            )
        );
        ?>
        </p>
    </div>
    </div>

    <div id="box-row" class="row-container">
    <div class="two-column">
        <img class="box-image" src="<?php echo esc_url( WWP_IMAGES_URL . '/upgrade-page-wwq-box.png' ); ?>" alt="<?php esc_attr_e( 'WooCommerce Wholesale Quotes', 'woocommerce-wholesale-prices' ); ?>" />
    </div>

    <div class="two-column">
        <ul class="reasons-box">
        <li><?php esc_html_e( 'Trusted by over 20,000+ stores', 'woocommerce-wholesale-prices' ); ?></li>
        <li><?php esc_html_e( '5-star customer satisfaction rating', 'woocommerce-wholesale-prices' ); ?></li>
        <li><?php esc_html_e( 'Custom quote request forms', 'woocommerce-wholesale-prices' ); ?></li>
        <li><?php esc_html_e( 'Quote approval and management system', 'woocommerce-wholesale-prices' ); ?></li>
        <li><?php esc_html_e( 'Convert quotes to orders seamlessly', 'woocommerce-wholesale-prices' ); ?></li>
        </ul>
    </div>
    </div>

    <div id="step-1" class="row-container step-container<?php echo $wwq_is_installed ? ' grayout' : ''; ?>">
    <div class="two-column">
        <span class="step-number"><?php esc_html_e( '1', 'woocommerce-wholesale-prices' ); ?></span>
    </div>
    <div class="two-column">
        <h3><?php esc_html_e( 'Purchase & Install Wholesale Quotes', 'woocommerce-wholesale-prices' ); ?></h3>
        <p>
        <?php
        esc_html_e(
            'Streamline your wholesale quote process with a powerful system that lets customers request custom quotes,
        allows you to manage and approve quotes efficiently, and converts approved quotes directly into orders.',
            'woocommerce-wholesale-prices'
        );
?>
</p>

        <p><a class="<?php echo esc_attr( $wwq_is_installed ? 'button-grey' : ' button-green' ); ?>" href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-quotes', 'wwp', 'upsell' ) ); ?>" target="_blank"><?php esc_html_e( 'Get Wholesale Quotes', 'woocommerce-wholesale-prices' ); ?></a></p>
    </div>
    </div>

    <div id="step-2" class="row-container step-container<?php echo ! $wwq_is_installed || $wwq_is_active ? ' grayout' : ''; ?>">
    <div class="two-column">
        <span class="step-number"><?php esc_html_e( '2', 'woocommerce-wholesale-prices' ); ?></span>
    </div>
    <div class="two-column">
        <h3><?php esc_html_e( 'Configure Wholesale Quotes', 'woocommerce-wholesale-prices' ); ?></h3>
        <p>
        <?php
        esc_html_e(
            'Wholesale Quotes comes mostly configured out of the box, but with some small tweaks you will have the perfect quote request system and approval workflow for your wholesale business.',
            'woocommerce-wholesale-prices'
        );
?>
</p>

        <p><a class="<?php echo ! $wwq_is_installed || $wwq_is_active ? 'button-grey' : ' button-green'; ?>" href="<?php echo esc_url( wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=all&amp;s', 'activate-plugin_' . $plugin_file ) ); ?>"><?php esc_html_e( 'Activate Plugin', 'woocommerce-wholesale-prices' ); ?></a></p>
    </div>
    </div>
</div>
