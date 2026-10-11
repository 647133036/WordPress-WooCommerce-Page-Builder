<div id="wwp-about-page" class="wwp-page wrap nosubsub">

    <div class="row-container">
    <img id="wws-logo" src="<?php echo esc_url( WWP_IMAGES_URL . 'logo.png' ); ?>" alt="<?php esc_attr_e( 'Wholesale Suite', 'woocommerce-wholesale-prices' ); ?>" />
    </div>

    <div class="row-container">
    <div class="one-column">
        <div class="page-title"><?php esc_html_e( 'About Wholesale Suite', 'woocommerce-wholesale-prices' ); ?></div>
        <p class="page-description"><?php esc_html_e( 'Hello and welcome to Wholesale Suite, the most popular wholesale solution for WooCommerce.', 'woocommerce-wholesale-prices' ); ?></p>
    </div>
    </div>

    <div class="row-container main">
    <div class="two-column">
        <h3><?php esc_html_e( 'About The Makers - Rymera Web Co', 'woocommerce-wholesale-prices' ); ?></h3>
        <p><?php esc_html_e( 'Over the years we\'ve worked with thousands of smart store owners that were frustrated by having separate workflows for wholesale between their online WooCommerce store and old-school offline methods.', 'woocommerce-wholesale-prices' ); ?></p>
        <p><?php esc_html_e( 'That\'s why we decided to make Wholesale Suite - a state of the art solution focused suite of plugins that make it easy to sell to wholesale alongside your existing WooCommerce store.', 'woocommerce-wholesale-prices' ); ?></p>
        <p><?php esc_html_e( 'Wholesale Suite is brought to you by the same team that\'s behind the best coupon feature plugin for WooCommerce, Advanced Coupons. We\'ve also been in the WordPress space for over a decade.', 'woocommerce-wholesale-prices' ); ?></p>
        <p><?php esc_html_e( 'We\'re thrilled you\'re using our tool and invite you to try our other tools as well.', 'woocommerce-wholesale-prices' ); ?></p>
    </div>

    <div class="two-column">
        <img id="wws-logo" src="<?php echo esc_url( WWP_IMAGES_URL . 'rymera-team-photo.jpg' ); ?>" alt="<?php esc_attr_e( 'Wholesale Suite', 'woocommerce-wholesale-prices' ); ?>" />
    </div>
    </div>

    <!-- ACFW and WWPP -->
    <div class="row-container two-columns">
        <div class="left-box">
            <div class="desc">
                <div class="page-title"><img id="acfw-marketing-logo" src="<?php echo esc_url( WWP_Helper_Functions::get_wp_org_plugin_icon_url( 'advanced-coupons-for-woocommerce-free' ) ); ?>" alt="<?php esc_attr_e( 'Advanced Coupons', 'woocommerce-wholesale-prices' ); ?>" width="36" />&nbsp;<?php esc_html_e( 'Advanced Coupons for WooCommerce (Free Plugin)', 'woocommerce-wholesale-prices' ); ?></div>
                <p><?php esc_html_e( 'Extends your coupon features so you can market your store better. Adds cart conditions (coupon rules), buy one get one (BOGO) deals, url coupons, coupon categories and loads more. Install this free plugin.', 'woocommerce-wholesale-prices' ); ?></p>
            </div>
            <div class="acfw-installed check-installed">
                <span><strong><?php esc_html_e( 'Status:', 'woocommerce-wholesale-prices' ); ?></strong>&nbsp;<span class="advanced-coupons-for-woocommerce-free-status-text"><?php echo WWP_Helper_Functions::is_acfwf_installed() ? esc_html_e( 'Installed', 'woocommerce-wholesale-prices' ) : esc_html_e( 'Not installed', 'woocommerce-wholesale-prices' ); ?></span></span>
                <?php if ( ! WWP_Helper_Functions::is_acfwf_installed() ) { ?>
                <a href="#" data-plugin-slug="advanced-coupons-for-woocommerce-free" class="button-green wwp-plugin-install"><?php esc_html_e( 'Install Plugin', 'woocommerce-wholesale-prices' ); ?></a>
                <?php } ?>
            </div>
        </div>
        <div class="right-box">
            <div class="desc">
                <div class="page-title"><img id="wws-marketing-logo" src="<?php echo esc_url( WWP_IMAGES_URL . 'wws-marketing-logo.png' ); ?>" alt="<?php esc_attr_e( 'Wholesale Suite', 'woocommerce-wholesale-prices' ); ?>" />&nbsp;<?php esc_html_e( 'Wholesale Suite Bundle', 'woocommerce-wholesale-prices' ); ?></div>
                <p><?php esc_html_e( 'Selling to wholesale in WooCommerce requires a full strategy, that\'s why we made the Wholesale Suite bundle. Advanced wholesale pricing, tax, shipping mapping, payment gateway mapping, an optimized form and a wholesale registration.', 'woocommerce-wholesale-prices' ); ?></p>
            </div>
            <div class="wwp-installed check-installed">
                <span><strong><?php esc_html_e( 'Status:', 'woocommerce-wholesale-prices' ); ?></strong>&nbsp;<span class="woocommerce-wholesale-prices-premium-status-text"><?php echo $bundle_installed ? esc_html_e( 'Installed', 'woocommerce-wholesale-prices' ) : esc_html_e( 'Not installed', 'woocommerce-wholesale-prices' ); ?></span></span>
                <?php if ( ! $bundle_installed ) { ?>
                <a href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'aboutpage', 'aboutpagebundlebutton' ) ); ?>" target="_blank" class="button-green"><?php esc_html_e( 'Learn More', 'woocommerce-wholesale-prices' ); ?></a>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- StoreAgent AI and AdTribes -->
    <div class="row-container two-columns">
        <div class="left-box">
            <div class="desc">
                <div class="page-title"><img id="storeagent-marketing-logo" src="<?php echo esc_url( WWP_Helper_Functions::get_wp_org_plugin_icon_url( 'storeagent-ai-for-woocommerce' ) ); ?>" alt="<?php esc_attr_e( 'StoreAgent AI', 'woocommerce-wholesale-prices' ); ?>" width="36" />&nbsp;<?php esc_html_e( 'StoreAgent AI for WooCommerce (Free Plugin)', 'woocommerce-wholesale-prices' ); ?></div>
                <p><?php esc_html_e( 'Get AI Agents for WooCommerce with StoreAgent.ai, the free AI-powered plugin designed to automate tasks, personalize customer interactions, and optimize your eCommerce operations.', 'woocommerce-wholesale-prices' ); ?></p>
            </div>
            <div class="storeagent-installed check-installed">
                <span><strong><?php esc_html_e( 'Status:', 'woocommerce-wholesale-prices' ); ?></strong>&nbsp;<span class="storeagent-ai-for-woocommerce-status-text"><?php echo WWP_Helper_Functions::is_storeagent_installed() ? esc_html_e( 'Installed', 'woocommerce-wholesale-prices' ) : esc_html_e( 'Not installed', 'woocommerce-wholesale-prices' ); ?></span></span>
                <?php if ( ! WWP_Helper_Functions::is_storeagent_installed() ) { ?>
                <a href="#" data-plugin-slug="storeagent-ai-for-woocommerce" class="button-green wwp-plugin-install"><?php esc_html_e( 'Install Plugin', 'woocommerce-wholesale-prices' ); ?></a>
                <?php } ?>
            </div>
        </div>
        <div class="right-box">
            <div class="desc">
                <div class="page-title"><img id="ad-tribes-logo" src="<?php echo esc_url( WWP_Helper_Functions::get_wp_org_plugin_icon_url( 'woo-product-feed-pro' ) ); ?>" alt="<?php esc_attr_e( 'Product Feed Pro', 'woocommerce-wholesale-prices' ); ?>" width="36" />&nbsp;<?php esc_html_e( 'Product Feed Pro (Free Plugin)', 'woocommerce-wholesale-prices' ); ?></div>
                <p><?php esc_html_e( 'Helps you generate and manage product feeds for various marketing channels, such as Google Shopping, Facebook, and more, to optimize your eCommerce store\'s visibility and sales.', 'woocommerce-wholesale-prices' ); ?></p>
            </div>
            <div class="ad-tribes-installed check-installed">
                <span><strong><?php esc_html_e( 'Status:', 'woocommerce-wholesale-prices' ); ?></strong>&nbsp;<span class="woo-product-feed-pro-status-text"><?php echo WWP_Helper_Functions::is_adtribes_installed() ? esc_html_e( 'Installed', 'woocommerce-wholesale-prices' ) : esc_html_e( 'Not installed', 'woocommerce-wholesale-prices' ); ?></span></span>
                <?php if ( ! WWP_Helper_Functions::is_adtribes_installed() ) { ?>
                <a href="#" data-plugin-slug="woo-product-feed-pro" class="button-green wwp-plugin-install"><?php esc_html_e( 'Install Plugin', 'woocommerce-wholesale-prices' ); ?></a>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- WC Vendors and Invoice Gateway -->
    <div class="row-container two-columns">
        <div class="left-box">
            <div class="desc">
                <div class="page-title"><img id="wc-vendors-marketing-logo" src="<?php echo esc_url( WWP_Helper_Functions::get_wp_org_plugin_icon_url( 'wc-vendors' ) ); ?>" alt="<?php esc_attr_e( 'WC Vendors Marketplace', 'woocommerce-wholesale-prices' ); ?>" width="36" />&nbsp;<?php esc_html_e( 'WC Vendors (Free Plugin)', 'woocommerce-wholesale-prices' ); ?></div>
                <p><?php esc_html_e( 'Easiest way to create your multivendor marketplace and earn commission from every sale. Create a WooCommerce marketplace with multi-seller, product vendor & multi vendor commissions.', 'woocommerce-wholesale-prices' ); ?></p>
            </div>
            <div class="wc-vendors-installed check-installed">
                <span><strong><?php esc_html_e( 'Status:', 'woocommerce-wholesale-prices' ); ?></strong>&nbsp;<span class="wc-vendors-status-text"><?php echo WWP_Helper_Functions::is_wcvendors_installed() ? esc_html_e( 'Installed', 'woocommerce-wholesale-prices' ) : esc_html_e( 'Not installed', 'woocommerce-wholesale-prices' ); ?></span></span>
                <?php if ( ! WWP_Helper_Functions::is_wcvendors_installed() ) { ?>
                <a href="#" data-plugin-slug="wc-vendors" class="button-green wwp-plugin-install"><?php esc_html_e( 'Install Plugin', 'woocommerce-wholesale-prices' ); ?></a>
                <?php } ?>
            </div>
        </div>
        <div class="right-box">
            <div class="desc">
                <div class="page-title"><img id="invoice-gateway-marketing-logo" src="<?php echo esc_url( WWP_Helper_Functions::get_wp_org_plugin_icon_url( 'invoice-gateway-for-woocommerce' ) ); ?>" alt="<?php esc_attr_e( 'Invoice Gateway for WooCommerce', 'woocommerce-wholesale-prices' ); ?>" width="36" />&nbsp;<?php esc_html_e( 'Invoice Gateway for WooCommerce (Free Plugin)', 'woocommerce-wholesale-prices' ); ?></div>
                <p><?php esc_html_e( 'Accept orders via a special invoice payment gateway method which lets your customer enter their order without upfront payment. Then just issue an invoice from your accounting system and paste in the number.', 'woocommerce-wholesale-prices' ); ?></p>
            </div>
            <div class="invoice-gateway-installed check-installed">
                <span><strong><?php esc_html_e( 'Status:', 'woocommerce-wholesale-prices' ); ?></strong>&nbsp;<span class="invoice-gateway-for-woocommerce-status-text"><?php echo WWP_Helper_Functions::is_invoice_gateway_installed() ? esc_html_e( 'Installed', 'woocommerce-wholesale-prices' ) : esc_html_e( 'Not installed', 'woocommerce-wholesale-prices' ); ?></span></span>
                <?php if ( ! WWP_Helper_Functions::is_invoice_gateway_installed() ) { ?>
                <a href="#" data-plugin-slug="invoice-gateway-for-woocommerce" class="button-green wwp-plugin-install"><?php esc_html_e( 'Install Plugin', 'woocommerce-wholesale-prices' ); ?></a>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Store Toolkit and Store Exporter -->
    <div class="row-container two-columns">
        <div class="left-box">
            <div class="desc">
                <div class="page-title"><img id="store-toolkit-logo" src="<?php echo esc_url( WWP_Helper_Functions::get_wp_org_plugin_icon_url( 'woocommerce-store-toolkit' ) ); ?>" alt="<?php esc_attr_e( 'Store Toolkit', 'woocommerce-wholesale-prices' ); ?>" width="36" />&nbsp;<?php esc_html_e( 'Store Toolkit for WooCommerce (Free Plugin)', 'woocommerce-wholesale-prices' ); ?></div>
                <p><?php esc_html_e( 'A growing set of commonly-used WooCommerce admin tools such as deleting WooCommerce data in bulk, such as products, orders, coupons, and customers. It also adds extra small features, order filtering, and more.', 'woocommerce-wholesale-prices' ); ?></p>
            </div>
            <div class="store-toolkit-installed check-installed">
                <span><strong><?php esc_html_e( 'Status:', 'woocommerce-wholesale-prices' ); ?></strong>&nbsp;<span class="woocommerce-store-toolkit-status-text"><?php echo WWP_Helper_Functions::is_store_toolkit_installed() ? esc_html_e( 'Installed', 'woocommerce-wholesale-prices' ) : esc_html_e( 'Not installed', 'woocommerce-wholesale-prices' ); ?></span></span>
                <?php if ( ! WWP_Helper_Functions::is_store_toolkit_installed() ) { ?>
                <a href="#" data-plugin-slug="woocommerce-store-toolkit" class="button-green wwp-plugin-install"><?php esc_html_e( 'Install Plugin', 'woocommerce-wholesale-prices' ); ?></a>
                <?php } ?>
            </div>
        </div>
        <div class="right-box">
            <div class="desc">
                <div class="page-title"><img id="store-exporter-marketing-logo" src="<?php echo esc_url( WWP_Helper_Functions::get_wp_org_plugin_icon_url( 'woocommerce-exporter' ) ); ?>" alt="<?php esc_attr_e( 'Store Exporter for WooCommerce', 'woocommerce-wholesale-prices' ); ?>" width="36" />&nbsp;<?php esc_html_e( 'Store Exporter for WooCommerce (Free Plugin)', 'woocommerce-wholesale-prices' ); ?></div>
                <p><?php esc_html_e( 'Easily export Orders, Subscriptions, Coupons, Products, Categories, Tags to a variety of formats. The deluxe version also adds scheduled exporting for easy reporting and syncing with other systems.', 'woocommerce-wholesale-prices' ); ?></p>
            </div>
            <div class="store-exporter-installed check-installed">
                <span><strong><?php esc_html_e( 'Status:', 'woocommerce-wholesale-prices' ); ?></strong>&nbsp;<span class="woocommerce-store-exporter-status-text"><?php echo WWP_Helper_Functions::is_store_exporter_installed() ? esc_html_e( 'Installed', 'woocommerce-wholesale-prices' ) : esc_html_e( 'Not installed', 'woocommerce-wholesale-prices' ); ?></span></span>
                <?php if ( ! WWP_Helper_Functions::is_store_exporter_installed() ) { ?>
                <a href="#" data-plugin-slug="woocommerce-store-exporter" class="button-green wwp-plugin-install"><?php esc_html_e( 'Install Plugin', 'woocommerce-wholesale-prices' ); ?></a>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- SaveTo Wishlist Lite for WooCommerce -->
    <div class="row-container two-columns">
        <div class="left-box">
            <div class="desc">
                <div class="page-title"><img id="save-to-wishlist-lite-for-woocommerce-logo" src="<?php echo esc_url( WWP_Helper_Functions::get_wp_org_plugin_icon_url( 'saveto-wishlist-lite-for-woocommerce' ) ); ?>" alt="<?php esc_attr_e( 'SaveTo Wishlist Lite for WooCommerce', 'woocommerce-wholesale-prices' ); ?>" width="36" />&nbsp;<?php esc_html_e( 'SaveTo Wishlist Lite for WooCommerce (Free Plugin)', 'woocommerce-wholesale-prices' ); ?></div>
                <p><?php esc_html_e( 'A simple, powerful WooCommerce wishlist plugin to help customers save products they love and buy later.', 'woocommerce-wholesale-prices' ); ?></p>
            </div>
            <div class="save-to-wishlist-lite-for-woocommerce-installed check-installed">
                <span><strong><?php esc_html_e( 'Status:', 'woocommerce-wholesale-prices' ); ?></strong>&nbsp;<span class="save-to-wishlist-lite-for-woocommerce-status-text"><?php echo WWP_Helper_Functions::is_save_to_wish_list_for_woocommerce_installed() ? esc_html_e( 'Installed', 'woocommerce-wholesale-prices' ) : esc_html_e( 'Not installed', 'woocommerce-wholesale-prices' ); ?></span></span>
                <?php if ( ! WWP_Helper_Functions::is_save_to_wish_list_for_woocommerce_installed() ) { ?>
                <a href="#" data-plugin-slug="saveto-wishlist-lite-for-woocommerce" class="button-green wwp-plugin-install"><?php esc_html_e( 'Install Plugin', 'woocommerce-wholesale-prices' ); ?></a>
                <?php } ?>
            </div>
        </div>
    </div>

</div>
