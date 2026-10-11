<div id="wwp-help-page" class="wwp-page wrap nosubsub">
    <div class="row-container">
    <img id="wws-logo" src="<?php echo esc_url( WWP_IMAGES_URL ); ?>logo.png" alt="<?php esc_attr_e( 'Wholesale Suite', 'woocommerce-wholesale-prices' ); ?>" />
    </div>

    <div class="row-container">
    <div class="one-column">
        <div class="page-title"><?php esc_html_e( 'Getting Help', 'woocommerce-wholesale-prices' ); ?></div>
        <p class="page-description"><?php esc_html_e( 'We\'re here to help you get the most out of Wholesale Suite for WooCommerce.', 'woocommerce-wholesale-prices' ); ?></p>
    </div>
    </div>

    <div class="row-container two-columns">
    <div class="two-column">
        <h3><?php esc_html_e( 'Knowledge Base', 'woocommerce-wholesale-prices' ); ?></h3>
        <p><?php esc_html_e( 'Access our self-service help documentation via the Knowledge Base. You\'ll find answers and solutions for a wide range of well know situations. You\'ll also find a Getting Started guide here for the plugin.', 'woocommerce-wholesale-prices' ); ?></p>
        <a href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'knowledge-base', 'wwp', 'helppage', 'helppageopenkbbutton' ) ); ?>" target="_blank" class="button-green"><?php esc_html_e( 'Open Knowledge Base', 'woocommerce-wholesale-prices' ); ?></a>
    </div>

    <div class="two-column">
        <h3><?php esc_html_e( 'Free Version WordPress.org Help Forums', 'woocommerce-wholesale-prices' ); ?></h3>
        <p><?php esc_html_e( 'Our support staff regularly check and help our free users at the official plugin WordPress.org help forums. Submit a post there with your question and we\'ll get back to you as soon as possible.', 'woocommerce-wholesale-prices' ); ?></p>
        <a href="https://wordpress.org/support/plugin/woocommerce-wholesale-prices/" target="_blank" class="button-green"><?php esc_html_e( 'Visit WordPress.org Forums', 'woocommerce-wholesale-prices' ); ?></a>
    </div>
    </div>

    <div class="row-container free-guide">
    <div class="page-title"><?php esc_html_e( 'FREE GUIDE: How To Setup Wholesale On Your WooCommerce Store', 'woocommerce-wholesale-prices' ); ?></div>
    <div class="two-columns">
        <div class="left-box">
        <div class="page-title"><?php esc_html_e( 'A Step-By-Step Guide For Adding Wholesale Ordering To Your Store', 'woocommerce-wholesale-prices' ); ?></div>
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    // translators: %1$s <strong> tag, %2$s </strong> tag.
                    __( 'If you\'ve ever wanted to grow a store to 6, 7 or 8-figures and beyond %1$sdownload this guide%2$s now. You\'ll learn how smart store owners are using coupons to grow their WooCommerce stores.', 'woocommerce-wholesale-prices' ),
                    '<strong>',
                    '</strong>'
                ),
            );
            ?>
        </p>
        <ul>
            <li><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'Exactly how to price your products ready for wholesale', 'woocommerce-wholesale-prices' ); ?></li>
            <li><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'The free way to setup wholesale pricing for customers in WooCommerce', 'woocommerce-wholesale-prices' ); ?></li>
            <li><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'Why you need an efficient ordering process', 'woocommerce-wholesale-prices' ); ?></li>
            <li><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'How to find your ideal wholesale customers & recruit them', 'woocommerce-wholesale-prices' ); ?></li>
        </ul>
        <a href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'free-guide', 'wwp', 'helppage', 'helppagefreeguidebutton' ) ); ?>" target="_blank" class="button-green"><?php esc_html_e( 'Get FREE Training Guide', 'woocommerce-wholesale-prices' ); ?></a>
        </div>
        <div class="right-box">
        <img id="wws-book-cover" src="<?php echo esc_url( WWP_IMAGES_URL ); ?>book-cover.png" alt="<?php esc_html_e( 'Free Guide', 'woocommerce-wholesale-prices' ); ?>" />
        </div>
    </div>
    </div>

</div>
