<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWoobt_Backend' ) ) {
    class WPCleverWoobt_Backend {
        protected static $instance = null;

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        function __construct() {
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_filter( 'pre_update_option', [ $this, 'last_saved' ], 10, 2 );
            add_action( 'admin_menu', [ $this, 'admin_menu' ] );
            add_action( 'wp_ajax_woobt_update_search_settings', [ $this, 'ajax_update_search_settings' ] );
            add_action( 'wp_ajax_woobt_get_search_results', [ $this, 'ajax_get_search_results' ] );
            add_action( 'wp_ajax_woobt_add_text', [ $this, 'ajax_add_text' ] );
            add_action( 'wp_ajax_woobt_add_rule', [ $this, 'ajax_add_rule' ] );
            add_action( 'wp_ajax_woobt_add_combination', [ $this, 'ajax_add_combination' ] );
            add_action( 'wp_ajax_woobt_search_term', [ $this, 'ajax_search_term' ] );
            add_action( 'wp_ajax_woobt_import_export', [ $this, 'ajax_import_export' ] );
            add_action( 'wp_ajax_woobt_import_export_save', [ $this, 'ajax_import_export_save' ] );
            add_action( 'wp_ajax_woobt_update_multiple_rules', [ $this, 'ajax_update_multiple_rules' ] );
            add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
            add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
            add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ] );
            add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_item_meta' ] );
            add_action( 'woocommerce_before_order_itemmeta', [ $this, 'before_order_item_meta' ], 10, 2 );
            add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
            add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );
            add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );
            add_filter( 'woocommerce_products_admin_list_table_filters', [ $this, 'product_filter' ] );
            add_action( 'pre_get_posts', [ $this, 'apply_product_filter' ] );
            add_filter( 'woocommerce_product_export_meta_value', [ $this, 'export_process' ], 10, 3 );
            add_filter( 'woocommerce_product_import_pre_insert_product_object', [
                    $this,
                    'import_process'
            ], 10, 2 );

        }

        function register_settings() {
            // settings
            register_setting( 'woobt_settings', 'woobt_settings', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWoobt_Helper', 'sanitize_array' ],
            ] );

            // rules
            register_setting( 'woobt_rules', 'woobt_rules_settings', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWoobt_Helper', 'sanitize_array' ],
            ] );
            register_setting( 'woobt_rules', 'woobt_rules', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWoobt_Helper', 'sanitize_array' ],
            ] );

            // localization
            register_setting( 'woobt_localization', 'woobt_localization', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWoobt_Helper', 'sanitize_array' ],
            ] );
        }

        function last_saved( $value, $option ) {
            if ( $option == 'woobt_settings' || $option == 'woobt_rules_settings' || $option == 'woobt_localization' ) {
                $value['_last_saved']    = current_time( 'timestamp' );
                $value['_last_saved_by'] = get_current_user_id();
            }

            return $value;
        }

        function admin_menu() {
            add_submenu_page( 'wpclever', esc_html__( 'WPC Frequently Bought Together', 'woo-bought-together' ), esc_html__( 'Bought Together', 'woo-bought-together' ), 'manage_options', 'wpclever-woobt', [
                    $this,
                    'admin_menu_content'
            ] );
        }

        function admin_menu_content() {
            add_thickbox();
            $active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'settings' ) );
            ?>
            <div class="wpclever_settings_page wrap">
                <div class="wpclever_settings_page_header">
                    <a class="wpclever_settings_page_header_logo" href="https://wpclever.net/"
                       target="_blank" title="Visit wpclever.net"></a>
                    <div class="wpclever_settings_page_header_text">
                        <div class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Frequently Bought Together', 'woo-bought-together' ) . ' ' . esc_html( WOOBT_VERSION ) . ' ' . ( defined( 'WOOBT_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'woo-bought-together' ) . '</span>' : '' ); ?></div>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
                                <?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woo-bought-together' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOBT_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'woo-bought-together' ); ?></a> |
                                <a href="<?php echo esc_url( WOOBT_CHANGELOG ); ?>"
                                   target="_blank"><?php esc_html_e( 'Changelog', 'woo-bought-together' ); ?></a> |
                                <a href="<?php echo esc_url( WOOBT_DISCUSSION ); ?>"
                                   target="_blank"><?php esc_html_e( 'Discussion', 'woo-bought-together' ); ?></a>
                            </p>
                        </div>
                    </div>
                </div>
                <h2></h2>
                <?php if ( isset( $_GET['settings-updated'] ) && sanitize_text_field( wp_unslash( $_GET['settings-updated'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'woo-bought-together' ); ?></p>
                    </div>
                <?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=settings' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Settings', 'woo-bought-together' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=rules' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'rules' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Smart Rules', 'woo-bought-together' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=localization' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Localization', 'woo-bought-together' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=premium' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
                           style="color: #c9356e">
                            <?php esc_html_e( 'Premium Version', 'woo-bought-together' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
                           class="nav-tab">
                            <?php esc_html_e( 'Essential Kit', 'woo-bought-together' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
                    <?php if ( $active_tab === 'settings' ) {
                        $pricing               = WPCleverWoobt_Helper()->get_setting( 'pricing', 'sale_price' );
                        $ignore_onsale         = WPCleverWoobt_Helper()->get_setting( 'ignore_onsale', 'no' );
                        $multiple_rules        = WPCleverWoobt_Helper()->get_setting( 'multiple_rules', 'no' );
                        $default               = WPCleverWoobt_Helper()->get_setting( 'default', [ 'default' ] );
                        $default_limit         = WPCleverWoobt_Helper()->get_setting( 'default_limit', '5' );
                        $default_price         = WPCleverWoobt_Helper()->get_setting( 'default_price', '100%' );
                        $default_discount      = WPCleverWoobt_Helper()->get_setting( 'default_discount', '0' );
                        $layout                = WPCleverWoobt_Helper()->get_setting( 'layout', 'default' );
                        $atc_button            = WPCleverWoobt_Helper()->get_setting( 'atc_button', 'main' );
                        $show_this_item        = WPCleverWoobt_Helper()->get_setting( 'show_this_item', 'yes' );
                        $exclude_unpurchasable = WPCleverWoobt_Helper()->get_setting( 'exclude_unpurchasable', 'no' );
                        $show_thumb            = WPCleverWoobt_Helper()->get_setting( 'show_thumb', 'yes' );
                        $show_price            = WPCleverWoobt_Helper()->get_setting( 'show_price', 'yes' );
                        $show_description      = WPCleverWoobt_Helper()->get_setting( 'show_description', 'no' );
                        $plus_minus            = WPCleverWoobt_Helper()->get_setting( 'plus_minus', 'no' );
                        $variations_selector   = WPCleverWoobt_Helper()->get_setting( 'variations_selector', 'default' );
                        $selector_interface    = WPCleverWoobt_Helper()->get_setting( 'selector_interface', 'unset' );
                        $link                  = WPCleverWoobt_Helper()->get_setting( 'link', 'yes' );
                        $change_image          = WPCleverWoobt_Helper()->get_setting( 'change_image', 'yes' );
                        $change_price          = WPCleverWoobt_Helper()->get_setting( 'change_price', 'yes' );
                        $counter               = WPCleverWoobt_Helper()->get_setting( 'counter', 'individual' );
                        $responsive            = WPCleverWoobt_Helper()->get_setting( 'responsive', 'yes' );
                        $cart_quantity         = WPCleverWoobt_Helper()->get_setting( 'cart_quantity', 'yes' );
                        ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'General', 'woo-bought-together' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Pricing method', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[pricing]">
                                                <option value="sale_price" <?php selected( $pricing, 'sale_price' ); ?>><?php esc_html_e( 'from Sale price', 'woo-bought-together' ); ?></option>
                                                <option value="regular_price" <?php selected( $pricing, 'regular_price' ); ?>><?php esc_html_e( 'from Regular price', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Calculate prices from the sale price (default) or regular price of products.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Ignore on-sale products', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[ignore_onsale]">
                                                <option value="no" <?php selected( $ignore_onsale, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                                <option value="main" <?php selected( $ignore_onsale, 'main' ); ?>><?php esc_html_e( 'The main product only', 'woo-bought-together' ); ?></option>
                                                <option value="fbt" <?php selected( $ignore_onsale, 'fbt' ); ?>><?php esc_html_e( 'The FBT products only', 'woo-bought-together' ); ?></option>
                                                <option value="both" <?php selected( $ignore_onsale, 'both' ); ?>><?php esc_html_e( 'Both the main product and the FBT products', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <p class="description"><?php esc_html_e( 'Ignore on-sale products when calculating FBT sale prices and discounts.', 'woo-bought-together' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Smart rules', 'woo-bought-together' ); ?></th>
                                    <td>
                                        You can configure advanced rules for multiple FBT products at once with the
                                        Smart Rules <a
                                                href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=rules' ) ); ?>">here</a>.
                                        <input type="hidden" name="woobt_settings[multiple_rules]"
                                               value="<?php echo esc_attr( $multiple_rules ); ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Default products', 'woo-bought-together' ); ?></th>
                                    <td>
                                            <span class="description"><?php esc_html_e( 'Choose which to be used as default FBT for products with no item list specified individually or no Smart Rules applicable.', 'woo-bought-together' ); ?> You can use
                                                <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-bought-together&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                   class="thickbox" title="WPC Custom Related Products">WPC Custom Related Products</a> or
                                                <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-smart-linked-products&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                   class="thickbox" title="WPC Smart Linked Products">WPC Smart Linked Products</a> plugin to configure related/upsells/cross-sells in bulk with smart conditions.</span>
                                        <?php
                                        // backward compatibility before 5.1.1
                                        if ( ! is_array( $default ) ) {
                                            switch ( (string) $default ) {
                                                case 'upsells':
                                                    $default = [ 'upsells' ];
                                                    break;
                                                case 'related':
                                                    $default = [ 'related' ];
                                                    break;
                                                case 'related_upsells':
                                                    $default = [ 'upsells', 'related' ];
                                                    break;
                                                case 'none':
                                                    $default = [];
                                                    break;
                                                default:
                                                    $default = [];
                                            }
                                        }
                                        ?>
                                        <div class="woobt_inner_lines" style="margin-top: 10px">
                                            <div class="woobt_inner_line">
                                                <div class="woobt_inner_value">
                                                    <input type="hidden" name="woobt_settings[default][]"
                                                           value="default" checked/>
                                                    <label><input type="checkbox"
                                                                  name="woobt_settings[default][]"
                                                                  value="related" <?php echo esc_attr( in_array( 'related', $default ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Related products', 'woo-bought-together' ); ?>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="woobt_inner_line">
                                                <div class="woobt_inner_value">
                                                    <label><input type="checkbox"
                                                                  name="woobt_settings[default][]"
                                                                  value="upsells" <?php echo esc_attr( in_array( 'upsells', $default ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Upsells products', 'woo-bought-together' ); ?>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="woobt_inner_line">
                                                <div class="woobt_inner_value">
                                                    <label><input type="checkbox"
                                                                  name="woobt_settings[default][]"
                                                                  value="crosssells" <?php echo esc_attr( in_array( 'crosssells', $default ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Cross-sells products', 'woo-bought-together' ); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="woobt_show_if_default_products woobt_inner_lines"
                                             style="margin-top: 10px">
                                            <div class="woobt_inner_line">
                                                <div class="woobt_inner_label"><?php esc_html_e( 'Limit', 'woo-bought-together' ); ?></div>
                                                <div class="woobt_inner_value">
                                                    <label>
                                                        <input type="number" class="small-text"
                                                               name="woobt_settings[default_limit]"
                                                               value="<?php echo esc_attr( $default_limit ); ?>"/>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="woobt_inner_line">
                                                <div class="woobt_inner_label"><?php esc_html_e( 'New price', 'woo-bought-together' ); ?></div>
                                                <div class="woobt_inner_value">
                                                    <label>
                                                        <input type="text" class="small-text"
                                                               name="woobt_settings[default_price]"
                                                               value="<?php echo esc_attr( $default_price ); ?>"/>
                                                    </label>
                                                    <span class="description"><?php esc_html_e( 'Set a new price for each product using a number (eg. "49") or percentage (eg. "90%" of original price).', 'woo-bought-together' ); ?></span>
                                                </div>
                                            </div>
                                            <div class="woobt_inner_line">
                                                <div class="woobt_inner_label"><?php esc_html_e( 'Discount', 'woo-bought-together' ); ?></div>
                                                <div class="woobt_inner_value">
                                                    <label>
                                                        <input type="number" class="small-text"
                                                               name="woobt_settings[default_discount]"
                                                               value="<?php echo esc_attr( $default_discount ); ?>"/>
                                                    </label>%.
                                                    <span class="description"><?php esc_html_e( 'Discount for the main product when buying at least one product in this list.', 'woo-bought-together' ); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Layout', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[layout]">
                                                <option value="default" <?php selected( $layout, 'default' ); ?>><?php esc_html_e( 'List', 'woo-bought-together' ); ?></option>
                                                <option value="compact" <?php selected( $layout, 'compact' ); ?>><?php esc_html_e( 'Compact', 'woo-bought-together' ); ?></option>
                                                <option value="separate" <?php selected( $layout, 'separate' ); ?>><?php esc_html_e( 'Separate images', 'woo-bought-together' ); ?></option>
                                                <option value="grid-2" <?php selected( $layout, 'grid-2' ); ?>><?php esc_html_e( 'Grid - 2 columns', 'woo-bought-together' ); ?></option>
                                                <option value="grid-3" <?php selected( $layout, 'grid-3' ); ?>><?php esc_html_e( 'Grid - 3 columns', 'woo-bought-together' ); ?></option>
                                                <option value="grid-4" <?php selected( $layout, 'grid-4' ); ?>><?php esc_html_e( 'Grid - 4 columns', 'woo-bought-together' ); ?></option>
                                                <option value="carousel-2" <?php selected( $layout, 'carousel-2' ); ?>><?php esc_html_e( 'Carousel - 2 columns', 'woo-bought-together' ); ?></option>
                                                <option value="carousel-3" <?php selected( $layout, 'carousel-3' ); ?>><?php esc_html_e( 'Carousel - 3 columns', 'woo-bought-together' ); ?></option>
                                                <option value="carousel-4" <?php selected( $layout, 'carousel-4' ); ?>><?php esc_html_e( 'Carousel - 4 columns', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Position', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <?php
                                        $position = apply_filters( 'woobt_position', WPCleverWoobt_Helper()->get_setting( 'position', apply_filters( 'woobt_default_position', 'before' ) ) );

                                        if ( is_array( WPCleverWoobt::$positions ) && ( count( WPCleverWoobt::$positions ) > 0 ) ) {
                                            echo '<select name="woobt_settings[position]">';

                                            foreach ( WPCleverWoobt::$positions as $k => $p ) {
                                                echo '<option value="' . esc_attr( $k ) . '" ' . ( $k === $position ? 'selected' : '' ) . '>' . esc_html( $p ) . '</option>';
                                            }

                                            echo '</select>';
                                        }
                                        ?>
                                        <p class="description"><?php esc_html_e( 'Choose the position to show the products list. You also can use the shortcode [woobt] to show the list where you want.', 'woo-bought-together' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Add to cart button', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <select name="woobt_settings[atc_button]" class="woobt_atc_button">
                                                <option value="main" <?php selected( $atc_button, 'main' ); ?>><?php esc_html_e( 'Main product\'s button', 'woo-bought-together' ); ?></option>
                                                <option value="separate" <?php selected( $atc_button, 'separate' ); ?>><?php esc_html_e( 'Separate buttons', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show "this item"', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <select name="woobt_settings[show_this_item]"
                                                    class="woobt_show_this_item">
                                                <option value="yes" <?php selected( $show_this_item, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $show_this_item, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( '"This item" cannot be hidden if "Separate buttons" is in use for the Add to Cart button.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Exclude unpurchasable', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[exclude_unpurchasable]">
                                                <option value="yes" <?php selected( $exclude_unpurchasable, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $exclude_unpurchasable, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Exclude unpurchasable products from the list.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show thumbnail', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[show_thumb]">
                                                <option value="yes" <?php selected( $show_thumb, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $show_thumb, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show price', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[show_price]">
                                                <option value="yes" <?php selected( $show_price, 'yes' ); ?>><?php esc_html_e( 'Price', 'woo-bought-together' ); ?></option>
                                                <option value="total" <?php selected( $show_price, 'total' ); ?>><?php esc_html_e( 'Total', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $show_price, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show short description', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[show_description]">
                                                <option value="yes" <?php selected( $show_description, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $show_description, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Show plus/minus button', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[plus_minus]">
                                                <option value="yes" <?php selected( $plus_minus, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $plus_minus, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show the plus/minus button for the quantity input.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Variations selector', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <select name="woobt_settings[variations_selector]"
                                                    class="woobt_variations_selector">
                                                <option value="default" <?php selected( $variations_selector, 'default' ); ?>><?php esc_html_e( 'Default', 'woo-bought-together' ); ?></option>
                                                <option value="woovr" <?php selected( $variations_selector, 'woovr' ); ?>><?php esc_html_e( 'Use WPC Variations Radio Buttons', 'woo-bought-together' ); ?></option>
                                            </select>
                                        </label>
                                        <p class="woobt-notice">
                                            WPC Variations Radio Buttons is recommended if you encounter errors with the
                                            variation swatches you are using, or especially when products have many
                                            variations. Install
                                            <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-variations-radio-buttons&TB_iframe=true&width=800&height=550' ) ); ?>"
                                               class="thickbox" title="WPC Variations Radio Buttons">WPC Variations
                                                Radio Buttons</a> to make it work.
                                        </p>
                                        <div class="woobt_show_if_woovr" style="margin-top: 10px">
                                            <?php esc_html_e( 'Selector interface', 'woo-bought-together' ); ?>
                                            <label> <select name="woobt_settings[selector_interface]">
                                                    <option value="unset" <?php selected( $selector_interface, 'unset' ); ?>><?php esc_html_e( 'Unset', 'woo-bought-together' ); ?></option>
                                                    <option value="ddslick" <?php selected( $selector_interface, 'ddslick' ); ?>><?php esc_html_e( 'ddSlick', 'woo-bought-together' ); ?></option>
                                                    <option value="select2" <?php selected( $selector_interface, 'select2' ); ?>><?php esc_html_e( 'Select2', 'woo-bought-together' ); ?></option>
                                                    <option value="default" <?php selected( $selector_interface, 'default' ); ?>><?php esc_html_e( 'Radio buttons', 'woo-bought-together' ); ?></option>
                                                    <option value="select" <?php selected( $selector_interface, 'select' ); ?>><?php esc_html_e( 'HTML select tag', 'woo-bought-together' ); ?></option>
                                                    <option value="grid-2" <?php selected( $selector_interface, 'grid-2' ); ?>><?php esc_html_e( 'Grid - 2 columns', 'woo-bought-together' ); ?></option>
                                                    <option value="grid-3" <?php selected( $selector_interface, 'grid-3' ); ?>><?php esc_html_e( 'Grid - 3 columns', 'woo-bought-together' ); ?></option>
                                                    <option value="grid-4" <?php selected( $selector_interface, 'grid-4' ); ?>><?php esc_html_e( 'Grid - 4 columns', 'woo-bought-together' ); ?></option>
                                                </select> </label>
                                            <p class="description"><?php esc_html_e( 'Choose a selector interface that apply for variations of FBT products only.', 'woo-bought-together' ); ?></p>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Link to individual product', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[link]">
                                                <option value="yes" <?php selected( $link, 'yes' ); ?>><?php esc_html_e( 'Yes, open in the same tab', 'woo-bought-together' ); ?></option>
                                                <option value="yes_blank" <?php selected( $link, 'yes_blank' ); ?>><?php esc_html_e( 'Yes, open in the new tab', 'woo-bought-together' ); ?></option>
                                                <option value="yes_popup" <?php selected( $link, 'yes_popup' ); ?>><?php esc_html_e( 'Yes, open quick view popup', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $link, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <p class="description">If you choose "Open quick view popup", please
                                            install
                                            <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>"
                                               class="thickbox" title="WPC Smart Quick View">WPC Smart Quick
                                                View</a> to make it work.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Change image', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[change_image]">
                                                <option value="yes" <?php selected( $change_image, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $change_image, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Change the main product image when choosing the variation of variable products.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Change price', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <select name="woobt_settings[change_price]"
                                                    class="woobt_change_price">
                                                <option value="yes" <?php selected( $change_price, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                                <option value="yes_custom" <?php selected( $change_price, 'yes_custom' ); ?>><?php esc_html_e( 'Yes, custom selector', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $change_price, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label> <label>
                                            <input type="text" name="woobt_settings[change_price_custom]"
                                                   value="<?php echo WPCleverWoobt_Helper()->get_setting( 'change_price_custom', '.summary > .price' ); ?>"
                                                   placeholder=".summary > .price"
                                                   class="woobt_change_price_custom"/>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Change the main product price when choosing the variation or quantity of products. It uses JavaScript to change product price so it is very dependent on theme’s HTML. If it cannot find and update the product price, please contact us and we can help you find the right selector or adjust the JS file.', 'woo-bought-together' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Counter', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[counter]">
                                                <option value="individual" <?php selected( $counter, 'individual' ); ?>><?php esc_html_e( 'Count the individual products', 'woo-bought-together' ); ?></option>
                                                <option value="qty" <?php selected( $counter, 'qty' ); ?>><?php esc_html_e( 'Count the product quantities', 'woo-bought-together' ); ?></option>
                                                <option value="hide" <?php selected( $counter, 'hide' ); ?>><?php esc_html_e( 'Hide', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Counter on the add to cart button.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Responsive', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[responsive]">
                                                <option value="yes" <?php selected( $responsive, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $responsive, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Change the layout for small screen devices.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Cart & Checkout', 'woo-bought-together' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Change quantity', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label> <select name="woobt_settings[cart_quantity]">
                                                <option value="yes" <?php selected( $cart_quantity, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                                <option value="no" <?php selected( $cart_quantity, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Buyer can change the quantity of associated products or not?', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Search', 'woo-bought-together' ); ?>
                                    </th>
                                </tr>
                                <?php self::search_settings(); ?>
                                <tr class="submit">
                                    <th colspan="2">
                                        <div class="wpclever_submit">
                                            <?php
                                            settings_fields( 'woobt_settings' );
                                            submit_button( '', 'primary', 'submit', false );

                                            if ( function_exists( 'wpc_last_saved' ) ) {
                                                wpc_last_saved( WPCleverWoobt_Helper()->get_settings() );
                                            }
                                            ?>
                                        </div>
                                        <a style="display: none;" class="wpclever_export" data-key="woobt_settings"
                                           data-name="settings"
                                           href="#"><?php esc_html_e( 'import / export', 'woo-bought-together' ); ?></a>
                                    </th>
                                </tr>
                            </table>
                        </form>
                    <?php } elseif ( $active_tab === 'rules' ) {
                        $multiple_rules = WPCleverWoobt_Helper()->get_setting( 'multiple_rules', 'no' );
                        ?>
                        <table class="form-table">
                            <tr>
                                <td colspan="2">
                                    <p class="description" style="color: #c9356e">
                                        * This feature only available on Premium Version. Click
                                        <a href="https://wpclever.net/downloads/frequently-bought-together?utm_source=pro&utm_medium=woobt&utm_campaign=wporg"
                                           target="_blank">here</a> to buy, just $29!
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Multiple rules', 'woo-bought-together' ); ?></th>
                                <td>
                                    <label> <select id="woobt_multiple_rules">
                                            <option value="no" <?php selected( $multiple_rules, 'no' ); ?>><?php esc_html_e( 'No — only the first matching rule is applied', 'woo-bought-together' ); ?></option>
                                            <option value="yes" <?php selected( $multiple_rules, 'yes' ); ?>><?php esc_html_e( 'Yes — all matching rules are applied and their products merged', 'woo-bought-together' ); ?></option>
                                        </select> </label>
                                    <button type="button" id="woobt_multiple_rules_save"
                                            class="button button-primary"><?php esc_html_e( 'Save', 'woo-bought-together' ); ?></button>
                                    <span id="woobt_multiple_rules_saved"
                                          style="display:none; color:#46b450; margin-left:6px;"><?php esc_html_e( 'Saved!', 'woo-bought-together' ); ?></span>
                                    <p class="description">
                                        <?php esc_html_e( 'In "No" mode (default), the plugin checks rules from top to bottom and stops at the first rule a product matches — keeping the result clean and predictable. In "Yes" mode, every matching rule is applied and their product lists are merged together, which is useful when you want a product to inherit items from multiple rules at the same time.', 'woo-bought-together' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php
                        self::rules( 'woobt_rules', WPCleverWoobt::$rules );
                    } elseif ( $active_tab === 'localization' ) { ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th scope="row"><?php esc_html_e( 'General', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'woo-bought-together' ); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'This item', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[this_item]"
                                                   class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'this_item' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'This item:', 'woo-bought-together' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Choose an attribute', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[choose]"
                                                   class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'choose' ) ); ?>"
                                                   placeholder="<?php /* translators: attribute name */
                                                   esc_attr_e( 'Choose %s', 'woo-bought-together' ); ?>"/>
                                        </label>
                                        <span class="description"><?php /* translators: attribute name */
                                            esc_html_e( 'Use %s to show the attribute name.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Clear', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[clear]"
                                                   class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'clear' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Clear', 'woo-bought-together' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Additional price', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[additional]"
                                                   class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'additional' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Additional price:', 'woo-bought-together' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total price', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[total]"
                                                   class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'total' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Total:', 'woo-bought-together' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Associated', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[associated]"
                                                   class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'associated' ) ); ?>"
                                                   placeholder="<?php /* translators: product name */
                                                   esc_attr_e( '(bought together %s)', 'woo-bought-together' ); ?>"/>
                                        </label> <span class="description"><?php /* translators: product name */
                                            esc_html_e( 'The text behind associated products. Use "%s" for the main product name.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Add to cart', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[add_to_cart]"
                                                   class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'add_to_cart' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Add to cart', 'woo-bought-together' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Add all to cart', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[add_all_to_cart]"
                                                   class="regular-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'add_all_to_cart' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Add all to cart', 'woo-bought-together' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Default above text', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[above_text]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'above_text' ) ); ?>"/>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'The default text above products list. You can overwrite it for each product in product settings.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Default under text', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[under_text]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'under_text' ) ); ?>"/>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'The default text under products list. You can overwrite it for each product in product settings.', 'woo-bought-together' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Alert', 'woo-bought-together' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Require selection', 'woo-bought-together' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woobt_localization[alert_selection]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( WPCleverWoobt_Helper()->localization( 'alert_selection' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Please select a purchasable variation for [name] before adding this product to the cart.', 'woo-bought-together' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
                                        <?php settings_fields( 'woobt_localization' ); ?><?php submit_button(); ?>
                                        <a style="display: none;" class="wpclever_export" data-key="woobt_localization"
                                           data-name="settings"
                                           href="#"><?php esc_html_e( 'import / export', 'woo-bought-together' ); ?></a>
                                    </th>
                                </tr>
                            </table>
                        </form>
                    <?php } elseif ( $active_tab == 'tools' ) { ?>
                        <table class="form-table">
                            <tr class="heading">
                                <th scope="row"><?php esc_html_e( 'Data Migration', 'woo-bought-together' ); ?></th>
                                <td>
                                    <?php esc_html_e( 'If selected products don\'t appear on the current version. Please try running Migrate tool.', 'woo-bought-together' ); ?>

                                    <?php
                                    echo '<p>';
                                    $num   = absint( wp_unslash( $_GET['num'] ?? 50 ) );
                                    $paged = absint( wp_unslash( $_GET['paged'] ?? 1 ) );

                                    if ( isset( $_GET['act'] ) && ( $_GET['act'] === 'migrate' ) ) {
                                        $args = [
                                                'post_type'      => 'product',
                                                'posts_per_page' => $num,
                                                'paged'          => $paged,
                                                'meta_query'     => [
                                                        [
                                                                'key'     => 'woobt_ids',
                                                                'compare' => 'EXISTS'
                                                        ]
                                                ]
                                        ];

                                        $posts = get_posts( $args );

                                        if ( ! empty( $posts ) ) {
                                            foreach ( $posts as $post ) {
                                                $ids = get_post_meta( $post->ID, 'woobt_ids', true );

                                                if ( ! empty( $ids ) && is_string( $ids ) ) {
                                                    $items     = explode( ',', $ids );
                                                    $new_items = [];

                                                    foreach ( $items as $item ) {
                                                        $item_data = explode( '/', $item );
                                                        $item_key  = WPCleverWoobt_Helper()->generate_key();
                                                        $item_id   = absint( $item_data[0] ?? 0 );

                                                        if ( $item_product = wc_get_product( $item_id ) ) {
                                                            $item_sku   = $item_product->get_sku();
                                                            $item_price = $item_data[1] ?? '100%';
                                                            $item_qty   = (float) ( $item_data[2] ?? 1 );

                                                            $new_items[ $item_key ] = [
                                                                    'id'    => $item_id,
                                                                    'sku'   => $item_sku,
                                                                    'price' => $item_price,
                                                                    'qty'   => $item_qty,
                                                            ];
                                                        }
                                                    }

                                                    update_post_meta( $post->ID, 'woobt_ids', $new_items );
                                                }
                                            }

                                            echo '<span style="color: #2271b1; font-weight: 700">' . esc_html__( 'Migrating...', 'woo-bought-together' ) . '</span>';
                                            echo '<p class="description">' . esc_html__( 'Please wait until it has finished!', 'woo-bought-together' ) . '</p>';
                                            ?>
                                            <script type="text/javascript">
                                                (function ($) {
                                                    $(function () {
                                                        setTimeout(function () {
                                                            window.location.href = '<?php echo admin_url( 'admin.php?page=wpclever-woobt&tab=tools&act=migrate&num=' . $num . '&paged=' . ( $paged + 1 ) ); ?>';
                                                        }, 1000);
                                                    });
                                                })(jQuery);
                                            </script>
                                        <?php } else {
                                            echo '<span style="color: #2271b1; font-weight: 700">' . esc_html__( 'Finished!', 'woo-bought-together' ) . '</span>';
                                        }
                                    } else {
                                        echo '<a class="button btn" href="' . esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=tools&act=migrate' ) ) . '">' . esc_html__( 'Migrate', 'woo-bought-together' ) . '</a>';
                                    }
                                    echo '</p>';
                                    ?>
                                </td>
                            </tr>
                        </table>
                    <?php } elseif ( $active_tab == 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/frequently-bought-together?utm_source=pro&utm_medium=woobt&utm_campaign=wporg"
                                   target="_blank">https://wpclever.net/downloads/frequently-bought-together</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Add a variable product or a specific variation of a product.</li>
                                <li>- Use Smart Rules to configure multiple bought-together products at once.</li>
                                <li>- Get the lifetime update & premium support.</li>
                            </ul>
                        </div>
                    <?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please
                            install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC
                                Smart Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC
                                Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC
                                Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        function rules( $name = 'woobt_rules', $rules = [] ) {
            ?>
            <form method="post" action="options.php">
                <table class="form-table">
                    <tr>
                        <td>
                            <div class="woobt_rules">
                                <?php
                                $rules = array_filter( $rules );

                                if ( ! empty( $rules ) ) {
                                    foreach ( $rules as $key => $rule ) {
                                        self::rule( $key, $name, $rule, false );
                                    }
                                } else { ?>
                                    <div class="woobt_rules_empty">
                                        <span><?php esc_html_e( 'No rules yet. Click "+ Add rule" below to create your first rule.', 'woo-bought-together' ); ?></span>
                                    </div>
                                <?php }
                                ?>
                            </div>
                            <div class="woobt_add_rule">
                                <div>
                                    <a href="#" class="woobt_new_rule button"
                                       data-name="<?php echo esc_attr( $name ); ?>">
                                        <?php esc_html_e( '+ Add rule', 'woo-bought-together' ); ?>
                                    </a> <a href="#" class="woobt_expand_all">
                                        <?php esc_html_e( 'Expand All', 'woo-bought-together' ); ?>
                                    </a> <a href="#" class="woobt_collapse_all">
                                        <?php esc_html_e( 'Collapse All', 'woo-bought-together' ); ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="submit">
                        <th colspan="2">
                            <div class="wpclever_submit">
                                <?php
                                $log = $name . '_settings';
                                echo '<input type="hidden" name="' . $log . '[version]" value="' . esc_attr( WOOBT_VERSION ) . '"/>';
                                settings_fields( $name );
                                submit_button( '', 'primary', 'submit', false );

                                if ( function_exists( 'wpc_last_saved' ) ) {
                                    wpc_last_saved( get_option( $log, [] ) );
                                }
                                ?>
                            </div>
                            <a style="display: none;" class="wpclever_export" data-key="woobt_rules" data-name="rules"
                               href="#"><?php esc_html_e( 'import / export', 'woo-bought-together' ); ?></a>
                        </th>
                    </tr>
                </table>
            </form>
            <?php
        }

        function rule( $key = '', $name = 'woobt_rules', $rule = null, $open = false ) {
            if ( empty( $key ) || is_numeric( $key ) ) {
                $key = WPCleverWoobt_Helper()->generate_key();
            }

            $rule_name         = $rule['name'] ?? '';
            $active            = $rule['active'] ?? 'yes';
            $apply             = $rule['apply'] ?? 'all';
            $apply_products    = (array) ( $rule['apply_products'] ?? [] );
            $apply_terms       = (array) ( $rule['apply_terms'] ?? [] );
            $apply_combination = (array) ( $rule['apply_combination'] ?? [] );
            $get               = $rule['get'] ?? 'all';
            $get_products      = (array) ( $rule['get_products'] ?? [] );
            $get_terms         = (array) ( $rule['get_terms'] ?? [] );
            $get_combination   = (array) ( $rule['get_combination'] ?? [] );
            $get_limit         = absint( $rule['get_limit'] ?? 3 );
            $get_orderby       = $rule['get_orderby'] ?? 'default';
            $get_order         = $rule['get_order'] ?? 'default';
            $price                  = $rule['price'] ?? '100%';
            $discount               = $rule['discount'] ?? '0';
            $before_text            = $rule['before_text'] ?? '';
            $after_text             = $rule['after_text'] ?? '';
            $default_qty            = ! empty( $rule['default_qty'] ) ? (float) $rule['default_qty'] : ( ! empty( $rule['default_quantity'] ) ? (float) $rule['default_quantity'] : 1 );

            if ( $default_qty <= 0 ) {
                $default_qty = 1;
            }

            $quantity               = $rule['quantity'] ?? ( $rule['qty_mode'] ?? 'default' );
            $limit_each_min_default = $rule['limit_each_min_default'] ?? 'no';
            $limit_each_min         = $rule['limit_each_min'] ?? '';
            $limit_each_max         = $rule['limit_each_max'] ?? '';
            $input_name             = $name . '[' . $key . ']';
            $rule_class             = 'woobt_rule' . ( $open ? ' open' : '' ) . ( $active === 'yes' ? ' active' : '' );
            ?>
            <div class="<?php echo esc_attr( $rule_class ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
                <input type="hidden" name="<?php echo esc_attr( $input_name . '[key]' ); ?>"
                       value="<?php echo esc_attr( $key ); ?>"/>
                <div class="woobt_rule_heading">
                    <span class="woobt_rule_move"></span>
                    <span class="woobt_rule_label"><span
                                class="woobt_rule_name"><?php echo esc_html( $rule_name ?: '#' . $key ); ?></span><span
                                class="woobt_rule_apply"></span><span class="woobt_rule_get"></span></span>
                    <a href="#" class="woobt_rule_duplicate"
                       data-name="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'duplicate', 'woo-bought-together' ); ?></a>
                    <a href="#"
                       class="woobt_rule_remove"><?php esc_html_e( 'remove', 'woo-bought-together' ); ?></a>
                </div>
                <div class="woobt_rule_content">
                    <div class="woobt_tr woobt_tr_stripes">
                        <div class="woobt_th"><?php esc_html_e( 'Active', 'woo-bought-together' ); ?></div>
                        <div class="woobt_td woobt_rule_td">
                            <label><select name="<?php echo esc_attr( $input_name . '[active]' ); ?>"
                                           class="woobt_rule_active">
                                    <option value="yes" <?php selected( $active, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                    <option value="no" <?php selected( $active, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                </select></label>
                        </div>
                    </div>
                    <div class="woobt_tr">
                        <div class="woobt_th"><?php esc_html_e( 'Name', 'woo-bought-together' ); ?></div>
                        <div class="woobt_td woobt_rule_td">
                            <label>
                                <input type="text" class="regular-text woobt_rule_name_val"
                                       name="<?php echo esc_attr( $input_name . '[name]' ); ?>"
                                       value="<?php echo esc_attr( $rule_name ); ?>"/>
                            </label>
                            <span class="description"><?php esc_html_e( 'For management only.', 'woo-bought-together' ); ?></span>
                        </div>
                    </div>
                    <div class="woobt_tr">
                        <div class="woobt_th woobt_th_full">
                            <?php esc_html_e( 'Add FBT products to which?', 'woo-bought-together' ); ?>
                        </div>
                    </div>
                    <?php self::source( $name, $key, $apply, $apply_products, $apply_terms, $apply_combination, 'apply' ); ?>
                    <div class="woobt_tr">
                        <div class="woobt_th woobt_th_full">
                            <?php esc_html_e( 'Define applicable FBT products:', 'woo-bought-together' ); ?>
                        </div>
                    </div>
                    <?php self::source( $name, $key, $get, $get_products, $get_terms, $get_combination, 'get', $get_limit, $get_orderby, $get_order ); ?>
                    <div class="woobt_tr">
                        <div class="woobt_th"><?php esc_html_e( 'New price', 'woo-bought-together' ); ?></div>
                        <div class="woobt_td woobt_rule_td">
                            <label>
                                <input type="text" class="small-text"
                                       name="<?php echo esc_attr( $input_name . '[price]' ); ?>"
                                       value="<?php echo esc_attr( $price ); ?>"/>
                            </label>
                            <span class="description"><?php esc_html_e( 'Set a new price for each product using a number (eg. "49") or percentage (eg. "90%" of original price).', 'woo-bought-together' ); ?></span>
                        </div>
                    </div>
                    <div class="woobt_tr">
                        <div class="woobt_th"><?php esc_html_e( 'Discount', 'woo-bought-together' ); ?></div>
                        <div class="woobt_td woobt_rule_td">
                            <label>
                                <input type="text" class="small-text"
                                       name="<?php echo esc_attr( $input_name . '[discount]' ); ?>"
                                       value="<?php echo esc_attr( $discount ); ?>"/>
                            </label>%.
                            <span class="description"><?php esc_html_e( 'Discount for the main product when buying at least one product in this list.', 'woo-bought-together' ); ?></span>
                        </div>
                    </div>
                    <div class="woobt_tr">
                        <div class="woobt_th"><?php esc_html_e( 'Default quantity', 'woo-bought-together' ); ?></div>
                        <div class="woobt_td woobt_rule_td">
                            <label>
                                <input type="number" class="small-text" min="1" step="1"
                                       name="<?php echo esc_attr( $input_name . '[default_qty]' ); ?>"
                                       value="<?php echo esc_attr( $default_qty ); ?>"/>
                            </label>
                            <span class="description"><?php esc_html_e( 'Default quantity for each product.', 'woo-bought-together' ); ?></span>
                        </div>
                    </div>
                    <div class="woobt_tr">
                        <div class="woobt_th"><?php esc_html_e( 'Item quantity', 'woo-bought-together' ); ?></div>
                        <div class="woobt_td woobt_rule_td">
                            <label>
                                <select name="<?php echo esc_attr( $input_name . '[quantity]' ); ?>"
                                        class="woobt_rule_quantity">
                                    <option value="default" <?php selected( $quantity, 'default' ); ?>><?php esc_html_e( 'Default', 'woo-bought-together' ); ?></option>
                                    <option value="custom" <?php selected( $quantity, 'custom' ); ?>><?php esc_html_e( 'Custom', 'woo-bought-together' ); ?></option>
                                    <option value="sync" <?php selected( $quantity, 'sync' ); ?>><?php esc_html_e( 'Sync', 'woo-bought-together' ); ?></option>
                                </select>
                            </label>
                            <span class="description"><?php esc_html_e( 'Default: use default quantity; Custom: allow buyer to change quantity; Sync: sync with main product quantity multiplied by default quantity.', 'woo-bought-together' ); ?></span>
                        </div>
                    </div>
                    <div class="woobt_tr woobt_tr_limit <?php echo esc_attr( $quantity !== 'custom' ? 'woobt_hide' : '' ); ?>">
                        <div class="woobt_th"><?php esc_html_e( 'Limit each item', 'woo-bought-together' ); ?></div>
                        <div class="woobt_td woobt_rule_td">
                            <label>
                                <input name="<?php echo esc_attr( $input_name . '[limit_each_min_default]' ); ?>"
                                       type="checkbox" value="yes" <?php echo esc_attr( $limit_each_min_default === 'yes' ? 'checked' : '' ); ?>/>
                                <?php esc_html_e( 'Use default quantity as min', 'woo-bought-together' ); ?>
                            </label>
                            <u><?php esc_html_e( 'or', 'woo-bought-together' ); ?></u>
                            <?php esc_html_e( 'Min', 'woo-bought-together' ); ?>
                            <label>
                                <input name="<?php echo esc_attr( $input_name . '[limit_each_min]' ); ?>"
                                       type="number" min="0" class="small-text woobt_limit_each_input"
                                       value="<?php echo esc_attr( $limit_each_min ); ?>"/>
                            </label>
                            <?php esc_html_e( 'Max', 'woo-bought-together' ); ?>
                            <label>
                                <input name="<?php echo esc_attr( $input_name . '[limit_each_max]' ); ?>"
                                       type="number" min="1" class="small-text woobt_limit_each_input"
                                       value="<?php echo esc_attr( $limit_each_max ); ?>"/>
                            </label>
                        </div>
                    </div>
                    <div class="woobt_tr">
                        <div class="woobt_th"><?php esc_html_e( 'Above text', 'woo-bought-together' ); ?></div>
                        <div class="woobt_td woobt_rule_td">
                            <label>
                            <textarea name="<?php echo esc_attr( $input_name . '[before_text]' ); ?>" rows="1"
                                      style="width: 100%"><?php echo esc_textarea( $before_text ); ?></textarea>
                            </label>
                        </div>
                    </div>
                    <div class="woobt_tr">
                        <div class="woobt_th"><?php esc_html_e( 'Under text', 'woo-bought-together' ); ?></div>
                        <div class="woobt_td woobt_rule_td">
                            <label>
                            <textarea name="<?php echo esc_attr( $input_name . '[after_text]' ); ?>" rows="1"
                                      style="width: 100%"><?php echo esc_textarea( $after_text ); ?></textarea>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        function source( $name, $key, $apply, $products, $terms, $combination, $type = 'apply', $get_limit = null, $get_orderby = null, $get_order = null ) {
            $input_name = $name . '[' . $key . ']';
            ?>
            <div class="woobt_tr">
                <div class="woobt_th"><?php esc_html_e( 'Source', 'woo-bought-together' ); ?></div>
                <div class="woobt_td woobt_rule_td">
                    <label>
                        <select class="woobt_source_selector woobt_source_selector_<?php echo esc_attr( $type ); ?>"
                                data-type="<?php echo esc_attr( $type ); ?>"
                                name="<?php echo esc_attr( $input_name . '[' . $type . ']' ); ?>">
                            <option value="all"><?php esc_html_e( 'All products', 'woo-bought-together' ); ?></option>
                            <option value="products" <?php selected( $apply, 'products' ); ?>><?php esc_html_e( 'Selected products', 'woo-bought-together' ); ?></option>
                            <option value="combination" <?php selected( $apply, 'combination' ); ?>><?php esc_html_e( 'Combined', 'woo-bought-together' ); ?></option>
                            <?php
                            $taxonomies = get_object_taxonomies( 'product', 'objects' );

                            foreach ( $taxonomies as $taxonomy ) {
                                echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . ( $apply === $taxonomy->name ? 'selected' : '' ) . '>' . esc_html( $taxonomy->label ) . '</option>';
                            }
                            ?>
                        </select> </label>
                    <?php if ( $type === 'get' ) { ?>
                        <span class="show_get hide_if_get_products">
										<span><?php esc_html_e( 'Limit', 'woo-bought-together' ); ?> <label>
<input type="number" min="1" max="50" name="<?php echo esc_attr( $input_name . '[get_limit]' ); ?>"
       value="<?php echo esc_attr( $get_limit ); ?>"/>
</label></span>
										<span>
										<?php esc_html_e( 'Order by', 'woo-bought-together' ); ?> <label>
<select name="<?php echo esc_attr( $input_name . '[get_orderby]' ); ?>">
<option value="default" <?php selected( $get_orderby, 'default' ); ?>><?php esc_html_e( 'Default', 'woo-bought-together' ); ?></option>
<option value="none" <?php selected( $get_orderby, 'none' ); ?>><?php esc_html_e( 'None', 'woo-bought-together' ); ?></option>
<option value="ID" <?php selected( $get_orderby, 'ID' ); ?>><?php esc_html_e( 'ID', 'woo-bought-together' ); ?></option>
<option value="name" <?php selected( $get_orderby, 'name' ); ?>><?php esc_html_e( 'Name', 'woo-bought-together' ); ?></option>
<option value="type" <?php selected( $get_orderby, 'type' ); ?>><?php esc_html_e( 'Type', 'woo-bought-together' ); ?></option>
<option value="date" <?php selected( $get_orderby, 'date' ); ?>><?php esc_html_e( 'Date', 'woo-bought-together' ); ?></option>
<option value="price" <?php selected( $get_orderby, 'price' ); ?>><?php esc_html_e( 'Price', 'woo-bought-together' ); ?></option>
<option value="modified" <?php selected( $get_orderby, 'modified' ); ?>><?php esc_html_e( 'Modified', 'woo-bought-together' ); ?></option>
<option value="rand" <?php selected( $get_orderby, 'rand' ); ?>><?php esc_html_e( 'Random', 'woo-bought-together' ); ?></option>
</select>
</label>
									</span>
										<span><?php esc_html_e( 'Order', 'woo-bought-together' ); ?> <label>
<select name="<?php echo esc_attr( $input_name . '[get_order]' ); ?>">
<option value="default" <?php selected( $get_order, 'default' ); ?>><?php esc_html_e( 'Default', 'woo-bought-together' ); ?></option>
<option value="DESC" <?php selected( $get_order, 'DESC' ); ?>><?php esc_html_e( 'DESC', 'woo-bought-together' ); ?></option>
<option value="ASC" <?php selected( $get_order, 'ASC' ); ?>><?php esc_html_e( 'ASC', 'woo-bought-together' ); ?></option>
</select>
</label></span>
									</span>
                    <?php } ?>
                </div>
            </div>
            <div class="woobt_tr hide_<?php echo esc_attr( $type ); ?> show_if_<?php echo esc_attr( $type ); ?>_products">
                <div class="woobt_th"><?php esc_html_e( 'Products', 'woo-bought-together' ); ?></div>
                <div class="woobt_td woobt_rule_td">
                    <label>
                        <select class="wc-product-search woobt-product-search"
                                name="<?php echo esc_attr( $input_name . '[' . $type . '_products][]' ); ?>"
                                multiple="multiple" data-sortable="1"
                                data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woo-bought-together' ); ?>"
                                data-action="woocommerce_json_search_products_and_variations">
                            <?php
                            if ( ! empty( $products ) ) {
                                foreach ( $products as $_product_id ) {
                                    if ( $_product = wc_get_product( $_product_id ) ) {
                                        echo '<option value="' . esc_attr( $_product_id ) . '" selected>' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
                                    }
                                }
                            }
                            ?>
                        </select> </label>
                </div>
            </div>
            <div class="woobt_tr hide_<?php echo esc_attr( $type ); ?> show_if_<?php echo esc_attr( $type ); ?>_combination">
                <div class="woobt_th"><?php esc_html_e( 'Combined', 'woo-bought-together' ); ?></div>
                <div class="woobt_td woobt_rule_td">
                    <div class="woobt_combinations">
                        <p class="description"><?php esc_html_e( '* Configure to find products that match all listed conditions.', 'woo-bought-together' ); ?></p>
                        <?php
                        if ( ! empty( $combination ) ) {
                            foreach ( $combination as $ck => $cmb ) {
                                self::combination( $ck, $name, $cmb, $key, $type );
                            }
                        } else {
                            self::combination( '', $name, null, $key, $type );
                        }
                        ?>
                    </div>
                    <div class="woobt_add_combination">
                        <a class="woobt_new_combination" href="#" data-name="<?php echo esc_attr( $name ); ?>"
                           data-type="<?php echo esc_attr( $type ); ?>"><?php esc_attr_e( '+ Add condition', 'woo-bought-together' ); ?></a>
                    </div>
                </div>
            </div>
            <div class="woobt_tr show_<?php echo esc_attr( $type ); ?> hide_if_<?php echo esc_attr( $type ); ?>_all hide_if_<?php echo esc_attr( $type ); ?>_products hide_if_<?php echo esc_attr( $type ); ?>_combination">
                <div class="woobt_th woobt_<?php echo esc_attr( $type ); ?>_text"><?php esc_html_e( 'Terms', 'woo-bought-together' ); ?></div>
                <div class="woobt_td woobt_rule_td">
                    <label>
                        <select class="woobt_terms" data-type="<?php echo esc_attr( $type ); ?>"
                                name="<?php echo esc_attr( $input_name . '[' . $type . '_terms][]' ); ?>"
                                multiple="multiple"
                                data-<?php echo esc_attr( $apply ); ?>="<?php echo esc_attr( implode( ',', $terms ) ); ?>">
                            <?php
                            if ( ! empty( $terms ) ) {
                                foreach ( $terms as $at ) {
                                    if ( $term = get_term_by( 'slug', $at, $apply ) ) {
                                        echo '<option value="' . esc_attr( $at ) . '" selected>' . esc_html( self::get_term_name( $term, $apply ) ) . '</option>';
                                    }
                                }
                            }
                            ?>
                        </select> </label>
                </div>
            </div>
            <?php
        }

        function combination( $c_key = '', $name = 'woobt_rules', $combination = null, $key = '', $type = 'apply' ) {
            if ( empty( $c_key ) || is_numeric( $c_key ) ) {
                $c_key = WPCleverWoobt_Helper()->generate_key();
            }

            $apply   = $combination['apply'] ?? '';
            $compare = $combination['compare'] ?? 'is';
            $same    = $combination['same'] ?? '';
            $terms   = (array) ( $combination['terms'] ?? [] );
            $name    .= '[' . $key . '][' . $type . '_combination][' . $c_key . ']';
            ?>
            <div class="woobt_combination">
                <span class="woobt_combination_remove">&times;</span>
                <span class="woobt_combination_selector_wrap">
                                <label>
                                <select class="woobt_combination_selector"
                                        name="<?php echo esc_attr( $name . '[apply]' ); ?>">
                                    <?php
                                    if ( $type === 'apply' ) {
                                        echo '<option value="variation" ' . selected( $apply, 'variation', false ) . '>' . esc_html__( 'Variations only', 'woo-bought-together' ) . '</option>';
                                        echo '<option value="not_variation" ' . selected( $apply, 'not_variation', false ) . '>' . esc_html__( 'Non-variation products', 'woo-bought-together' ) . '</option>';
                                    } elseif ( $type === 'get' ) {
                                        echo '<option value="same" ' . selected( $apply, 'same', false ) . '>' . esc_html__( 'Has same', 'woo-bought-together' ) . '</option>';
                                    }

                                    $taxonomies = get_object_taxonomies( 'product', 'objects' );

                                    foreach ( $taxonomies as $taxonomy ) {
                                        echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . ( $apply === $taxonomy->name ? 'selected' : '' ) . '>' . esc_html( $taxonomy->label ) . '</option>';
                                    }
                                    ?>
                                </select>
                                </label>
                            </span> <span class="woobt_combination_compare_wrap">
                        <label> <select class="woobt_combination_compare"
                                        name="<?php echo esc_attr( $name . '[compare]' ); ?>">
                            <option value="is" <?php selected( $compare, 'is' ); ?>><?php esc_html_e( 'including', 'woo-bought-together' ); ?></option>
                            <option value="is_not" <?php selected( $compare, 'is_not' ); ?>><?php esc_html_e( 'excluding', 'woo-bought-together' ); ?></option>
                        </select> </label></span> <span class="woobt_combination_val_wrap">
                                <label>
                                <select class="woobt_combination_val woobt_apply_terms" multiple="multiple"
                                        name="<?php echo esc_attr( $name . '[terms][]' ); ?>">
                                    <?php
                                    if ( ! empty( $terms ) ) {
                                        foreach ( $terms as $ct ) {
                                            if ( $term = get_term_by( 'slug', $ct, $apply ) ) {
                                                echo '<option value="' . esc_attr( $ct ) . '" selected>' . esc_html( $term->name ) . '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                </label>
                            </span> <span class="woobt_combination_same_wrap"><label>
							<select name="<?php echo esc_attr( $name . '[same]' ); ?>">
								<?php foreach ( $taxonomies as $taxonomy ) {
                                    echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . selected( $same, $taxonomy->name, false ) . '>' . esc_html( $taxonomy->label ) . '</option>';
                                } ?>
							</select></label></span>
            </div>
            <?php
        }

        function ajax_add_rule() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $rule      = [];
            $name      = sanitize_key( wp_unslash( $_POST['name'] ?? 'woobt_rules' ) );
            $rule_data = wp_unslash( $_POST['rule_data'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

            if ( ! empty( $rule_data ) ) {
                $form_rule = [];
                parse_str( $rule_data, $form_rule );

                if ( isset( $form_rule[ $name ] ) && is_array( $form_rule[ $name ] ) ) {
                    $rule = reset( $form_rule[ $name ] );
                }
            }

            self::rule( '', $name, $rule, true );
            wp_die();
        }

        function ajax_add_combination() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $key  = sanitize_key( wp_unslash( $_POST['key'] ?? '' ) ?? WPCleverWoobt_Helper()->generate_key() );
            $name = sanitize_key( wp_unslash( $_POST['name'] ?? 'woobt_rules' ) );
            $type = sanitize_key( wp_unslash( $_POST['type'] ?? 'apply' ) );

            self::combination( '', $name, null, $key, $type );
            wp_die();
        }

        function ajax_search_term() {
            if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['nonce'] ) ), 'woobt-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $return = [];

            $args = [
                    'taxonomy'   => sanitize_text_field( wp_unslash( $_REQUEST['taxonomy'] ?? '' ) ),
                    'orderby'    => 'id',
                    'order'      => 'ASC',
                    'hide_empty' => false,
                    'fields'     => 'all',
                    'name__like' => sanitize_text_field( wp_unslash( $_REQUEST['q'] ?? '' ) ),
            ];

            $terms = get_terms( $args );

            if ( count( $terms ) ) {
                foreach ( $terms as $term ) {
                    $return[] = [
                            $term->slug,
                            self::get_term_name( $term, sanitize_text_field( wp_unslash( $_REQUEST['taxonomy'] ?? '' ) ) )
                    ];
                }
            }

            wp_send_json( $return );
        }

        function get_term_name( $term, $taxonomy ) {
            if ( $term->parent ) {
                $separator = ' > ';
                $name      = get_term_parents_list( $term->term_id, $taxonomy, [
                        'link'      => false,
                        'separator' => $separator
                ] );

                $name = rtrim( $name, $separator );
            } else {
                $name = $term->name;
            }

            return apply_filters( 'woobt_get_term_name', $name, $term, $taxonomy );
        }

        function search_settings() {
            $search_sku      = WPCleverWoobt_Helper()->get_setting( 'search_sku', 'no' );
            $search_id       = WPCleverWoobt_Helper()->get_setting( 'search_id', 'no' );
            $search_exact    = WPCleverWoobt_Helper()->get_setting( 'search_exact', 'no' );
            $search_sentence = WPCleverWoobt_Helper()->get_setting( 'search_sentence', 'no' );
            $search_same     = WPCleverWoobt_Helper()->get_setting( 'search_same', 'no' );
            ?>
            <tr>
                <th><?php esc_html_e( 'Search limit', 'woo-bought-together' ); ?></th>
                <td>
                    <label>
                        <input class="woobt_search_limit" type="number" name="woobt_settings[search_limit]"
                               value="<?php echo WPCleverWoobt_Helper()->get_setting( 'search_limit', 10 ); ?>"/>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Search by SKU', 'woo-bought-together' ); ?></th>
                <td>
                    <label> <select name="woobt_settings[search_sku]" class="woobt_search_sku">
                            <option value="yes" <?php selected( $search_sku, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                            <option value="no" <?php selected( $search_sku, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                        </select> </label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Search by ID', 'woo-bought-together' ); ?></th>
                <td>
                    <label> <select name="woobt_settings[search_id]" class="woobt_search_id">
                            <option value="yes" <?php selected( $search_id, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                            <option value="no" <?php selected( $search_id, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                        </select> </label>
                    <span class="description"><?php esc_html_e( 'Search by ID when entering the numeric only.', 'woo-bought-together' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Search exact', 'woo-bought-together' ); ?></th>
                <td>
                    <label> <select name="woobt_settings[search_exact]" class="woobt_search_exact">
                            <option value="yes" <?php selected( $search_exact, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                            <option value="no" <?php selected( $search_exact, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                        </select> </label>
                    <span class="description"><?php esc_html_e( 'Match whole product title or content?', 'woo-bought-together' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Search sentence', 'woo-bought-together' ); ?></th>
                <td>
                    <label> <select name="woobt_settings[search_sentence]" class="woobt_search_sentence">
                            <option value="yes" <?php selected( $search_sentence, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                            <option value="no" <?php selected( $search_sentence, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                        </select> </label>
                    <span class="description"><?php esc_html_e( 'Do a phrase search?', 'woo-bought-together' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Accept same products', 'woo-bought-together' ); ?></th>
                <td>
                    <label> <select name="woobt_settings[search_same]" class="woobt_search_same">
                            <option value="yes" <?php selected( $search_same, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                            <option value="no" <?php selected( $search_same, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                        </select> </label>
                    <span class="description"><?php esc_html_e( 'If yes, a product can be added many times.', 'woo-bought-together' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Product types', 'woo-bought-together' ); ?></th>
                <td>
                    <?php
                    $search_types  = WPCleverWoobt_Helper()->get_setting( 'search_types', [ 'all' ] );
                    $product_types = wc_get_product_types();
                    $product_types = array_merge( [ 'all' => esc_html__( 'All', 'woo-bought-together' ) ], $product_types );
                    $key_pos       = array_search( 'variable', array_keys( $product_types ) );

                    if ( $key_pos !== false ) {
                        $key_pos ++;
                        $second_array  = array_splice( $product_types, $key_pos );
                        $product_types = array_merge( $product_types, [ 'variation' => esc_html__( ' → Variation', 'woo-bought-together' ) ], $second_array );
                    }

                    echo '<select name="woobt_settings[search_types][]" multiple style="width: 200px; height: 150px;" class="woobt_search_types">';

                    foreach ( $product_types as $key => $name ) {
                        echo '<option value="' . esc_attr( $key ) . '" ' . ( in_array( $key, $search_types, true ) ? 'selected' : '' ) . '>' . esc_html( $name ) . '</option>';
                    }

                    echo '</select>';
                    ?>
                </td>
            </tr>
            <?php
        }

        function admin_enqueue_scripts( $hook ) {
            if ( apply_filters( 'woobt_ignore_backend_scripts', false, $hook ) ) {
                return null;
            }

            wp_enqueue_style( 'hint', WOOBT_URI . 'assets/css/hint.css', [], WOOBT_VERSION );
            wp_enqueue_style( 'woobt-backend', WOOBT_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WOOBT_VERSION );
            wp_enqueue_script( 'woobt-backend', WOOBT_URI . 'assets/js/backend.js', [
                    'jquery',
                    'jquery-ui-dialog',
                    'jquery-ui-sortable',
                    'wc-enhanced-select',
                    'selectWoo',
            ], WOOBT_VERSION, true );
            wp_localize_script( 'woobt-backend', 'woobt_vars', [ 'nonce' => wp_create_nonce( 'woobt-security' ), ] );
        }

        function action_links( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOBT_FILE );
            }

            if ( $plugin === $file ) {
                $settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'woo-bought-together' ) . '</a>';
                $links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'woo-bought-together' ) . '</a>';
                array_unshift( $links, $settings );
            }

            return (array) $links;
        }

        function row_meta( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOBT_FILE );
            }

            if ( $plugin === $file ) {
                $row_meta = [
                        'support' => '<a href="' . esc_url( WOOBT_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'woo-bought-together' ) . '</a>',
                ];

                return array_merge( $links, $row_meta );
            }

            return (array) $links;
        }

        function display_post_states( $states, $post ) {
            if ( 'product' == get_post_type( $post->ID ) ) {
                if ( WPCleverWoobt::instance()->is_disable( $post->ID, 'edit' ) ) {
                    $states[] = apply_filters( 'woobt_post_states', '<span class="woobt-state">' . esc_html__( 'Bought together (Disabled)', 'woo-bought-together' ) . '</span>', $post->ID );
                } else {
                    $items = WPCleverWoobt::instance()->get_product_items( $post->ID, 'edit' );
                    $count = 0;

                    if ( ! empty( $items ) ) {
                        foreach ( $items as $item ) {
                            if ( ! empty( $item['id'] ) ) {
                                $count += 1;
                            }
                        }

                        $states[] = apply_filters( 'woobt_post_states', '<span class="woobt-state">' . sprintf( /* translators: count */ esc_html__( 'Bought together (%d)', 'woo-bought-together' ), $count ) . '</span>', $count, $post->ID );
                    }
                }
            }

            return $states;
        }

        function hidden_order_item_meta( $hidden ) {
            return array_merge( $hidden, [
                    '_woobt_parent_id',
                    '_woobt_ids',
                    'woobt_parent_id',
                    'woobt_ids'
            ] );
        }

        function before_order_item_meta( $item_id, $item ) {
            if ( $parent_id = $item->get_meta( '_woobt_parent_id' ) ) {
                echo sprintf( WPCleverWoobt_Helper()->localization( 'associated', /* translators: product name */ esc_html__( '(bought together %s)', 'woo-bought-together' ) ), esc_html( get_the_title( $parent_id ) ) );
            }
        }

        function ajax_update_search_settings() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $settings                    = (array) get_option( 'woobt_settings', [] );
            $settings['search_limit']    = (int) sanitize_text_field( wp_unslash( $_POST['limit'] ?? '' ) );
            $settings['search_sku']      = sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) );
            $settings['search_id']       = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
            $settings['search_exact']    = sanitize_text_field( wp_unslash( $_POST['exact'] ?? '' ) );
            $settings['search_sentence'] = sanitize_text_field( wp_unslash( $_POST['sentence'] ?? '' ) );
            $settings['search_same']     = sanitize_text_field( wp_unslash( $_POST['same'] ?? '' ) );
            $settings['search_types']    = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['types'] ?? [] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

            update_option( 'woobt_settings', $settings );
            wp_die();
        }

        function ajax_update_multiple_rules() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            // Only update the single key to avoid overwriting other settings
            $settings                   = (array) get_option( 'woobt_settings', [] );
            $settings['multiple_rules'] = sanitize_text_field( wp_unslash( $_POST['multiple_rules'] ?? 'no' ) );

            update_option( 'woobt_settings', $settings );
            wp_die();
        }

        function ajax_get_search_results() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) ) {
                die( 'Permissions check failed!' );
            }

            $types         = WPCleverWoobt_Helper()->get_setting( 'search_types', [ 'all' ] );
            $keyword       = esc_html( wp_unslash( $_POST['woobt_keyword'] ?? '' ) );
            $id            = absint( wp_unslash( $_POST['woobt_id'] ?? '' ) );
            $exclude_ids   = explode( ',', wp_unslash( $_POST['woobt_ids'] ?? '' ) );
            $exclude_ids[] = $id;

            if ( ( WPCleverWoobt_Helper()->get_setting( 'search_id', 'no' ) === 'yes' ) && is_numeric( $keyword ) ) {
                // search by id
                $query_args = [
                        'p'         => absint( $keyword ),
                        'post_type' => 'product'
                ];
            } else {
                $limit = WPCleverWoobt_Helper()->get_setting( 'search_limit', 10 );

                if ( $limit < 1 ) {
                    $limit = 10;
                } elseif ( $limit > 500 ) {
                    $limit = 500;
                }

                $query_args = [
                        'is_woobt'       => true,
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        's'              => $keyword,
                        'posts_per_page' => $limit
                ];

                if ( ! empty( $types ) && ! in_array( 'all', $types, true ) ) {
                    $product_types = $types;

                    if ( in_array( 'variation', $types, true ) ) {
                        $product_types[] = 'variable';
                    }

                    $query_args['tax_query'] = [
                            [
                                    'taxonomy' => 'product_type',
                                    'field'    => 'slug',
                                    'terms'    => $product_types,
                            ],
                    ];
                }

                if ( WPCleverWoobt_Helper()->get_setting( 'search_same', 'no' ) !== 'yes' ) {
                    $query_args['post__not_in'] = $exclude_ids;
                }
            }

            $query = new WP_Query( $query_args );

            if ( $query->have_posts() ) {
                echo '<ul>';

                while ( $query->have_posts() ) {
                    $query->the_post();
                    $product = wc_get_product( get_the_ID() );

                    if ( ! $product || ( 'trash' === $product->get_status() ) ) {
                        continue;
                    }

                    if ( ! $product->is_type( 'variable' ) || in_array( 'variable', $types, true ) || in_array( 'all', $types, true ) ) {
                        self::product_data_li( $product, '100%', 1, true );
                    }

                    if ( $product->is_type( 'variable' ) && ( empty( $types ) || in_array( 'all', $types, true ) || in_array( 'variation', $types, true ) ) ) {
                        // show all children
                        $children = $product->get_children();

                        if ( is_array( $children ) && count( $children ) > 0 ) {
                            foreach ( $children as $child ) {
                                $product_child = wc_get_product( $child );

                                if ( $product_child ) {
                                    self::product_data_li( $product_child, '100%', 1, true );
                                }
                            }
                        }
                    }
                }

                echo '</ul>';
                wp_reset_postdata();
            } else {
                echo '<ul><span>' . sprintf( /* translators: keyword */ esc_html__( 'No results found for "%s"', 'woo-bought-together' ), esc_html( $keyword ) ) . '</span></ul>';
            }

            wp_die();
        }

        function ajax_add_text() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) ) {
                die( 'Permissions check failed!' );
            }

            self::text_data_li();

            wp_die();
        }

        function product_data_li( $product, $price = '100%', $qty = 1, $search = false, $key = null ) {
            if ( empty( $key ) || is_numeric( $key ) ) {
                $key = WPCleverWoobt_Helper()->generate_key();
            }

            $product_id    = $product->get_id();
            $product_sku   = $product->get_sku();
            $product_class = 'woobt-li-product woobt-item';
            $product_class .= ! $product->is_in_stock() ? ' out-of-stock' : '';
            $product_class .= ! in_array( $product->get_type(), WPCleverWoobt::$types, true ) ? ' disabled' : '';

            if ( class_exists( 'WPCleverWoopq' ) && ( WPCleverWoopq::get_setting( 'decimal', 'no' ) === 'yes' ) ) {
                $step = '0.000001';
            } else {
                $step = '1';
                $qty  = (int) $qty;
            }

            if ( $search ) {
                $remove_btn = '<span class="woobt-remove hint--left" aria-label="' . esc_html__( 'Add', 'woo-bought-together' ) . '">+</span>';
            } else {
                $remove_btn = '<span class="woobt-remove hint--left" aria-label="' . esc_html__( 'Remove', 'woo-bought-together' ) . '">×</span>';
            }

            $hidden_input = '<input type="hidden" name="woobt_ids[' . $key . '][id]" value="' . $product_id . '"/><input type="hidden" name="woobt_ids[' . $key . '][sku]" value="' . $product_sku . '"/>';

            echo '<li class="' . esc_attr( trim( $product_class ) ) . '" data-id="' . $product->get_id() . '">' . $hidden_input . '<span class="woobt-move"></span><span class="price hint--right" aria-label="' . esc_html__( 'Set a new price using a number (eg. "49") or percentage (eg. "90%" of original price)', 'woo-bought-together' ) . '"><input type="text" name="woobt_ids[' . $key . '][price]" value="' . $price . '"/></span><span class="qty hint--right" aria-label="' . esc_html__( 'Default quantity', 'woo-bought-together' ) . '"><input type="number" name="woobt_ids[' . $key . '][qty]" value="' . esc_attr( $qty ) . '" step="' . esc_attr( $step ) . '"/></span><span class="img">' . $product->get_image( [
                            30,
                            30
                    ] ) . '</span><span class="data">' . ( $product->get_status() === 'private' ? '<span class="info">private</span> ' : '' ) . '<span class="name">' . wp_strip_all_tags( $product->get_name() ) . '</span> <span class="info">' . $product->get_price_html() . '</span></span> <span class="type"><a href="' . esc_url( get_edit_post_link( $product_id ) ) . '" target="_blank">' . $product->get_type() . '<br/>#' . $product->get_id() . '</a></span> ' . $remove_btn . '</li>';
        }

        function product_data_li_deleted( $product_id, $key ) {
            $hidden_input = '<input type="hidden" name="woobt_ids[' . $key . '][id]" value="' . $product_id . '"/><input type="hidden" name="woobt_ids[' . $key . '][sku]" value=""/>';
            echo '<li class="woobt-li-product woobt-item" data-id="' . esc_attr( $product_id ) . '">' . $hidden_input . '<span class="woobt-move"></span><span class="data"><span class="name">' . sprintf( /* translators: product ID */ esc_html__( 'Product ID %d does not exist.', 'woo-bought-together' ), $product_id ) . '</span></span><span class="woobt-remove hint--left" aria-label="' . esc_html__( 'Remove', 'woo-bought-together' ) . '">×</span></li>';
        }

        function text_data_li( $data = [], $key = null ) {
            if ( empty( $key ) || is_numeric( $key ) ) {
                $key = WPCleverWoobt_Helper()->generate_key();
            }

            $data = array_merge( [ 'type' => 'h1', 'text' => '' ], $data );
            $type = '<select name="woobt_ids[' . $key . '][type]"><option value="h1" ' . selected( $data['type'], 'h1', false ) . '>H1</option><option value="h2" ' . selected( $data['type'], 'h2', false ) . '>H2</option><option value="h3" ' . selected( $data['type'], 'h3', false ) . '>H3</option><option value="h4" ' . selected( $data['type'], 'h4', false ) . '>H4</option><option value="h5" ' . selected( $data['type'], 'h5', false ) . '>H5</option><option value="h6" ' . selected( $data['type'], 'h6', false ) . '>H6</option><option value="p" ' . selected( $data['type'], 'p', false ) . '>p</option><option value="span" ' . selected( $data['type'], 'span', false ) . '>span</option><option value="none" ' . selected( $data['type'], 'none', false ) . '>none</option></select>';

            echo '<li class="woobt-li-text"><span class="woobt-move"></span><span class="tag">' . $type . '</span><span class="data"><input type="text" name="woobt_ids[' . $key . '][text]" value="' . esc_attr( $data['text'] ) . '"/></span><span class="woobt-remove hint--left" aria-label="' . esc_html__( 'Remove', 'woo-bought-together' ) . '">×</span></li>';
        }

        function product_data_tabs( $tabs ) {
            $tabs['woobt'] = [
                    'label'  => esc_html__( 'Bought Together', 'woo-bought-together' ),
                    'target' => 'woobt_settings',
            ];

            return $tabs;
        }

        function product_data_panels() {
            global $post, $thepostid, $product_object;

            if ( $product_object instanceof WC_Product ) {
                $product_id = $product_object->get_id();
            } elseif ( is_numeric( $thepostid ) ) {
                $product_id = $thepostid;
            } elseif ( $post instanceof WP_Post ) {
                $product_id = $post->ID;
            } else {
                $product_id = 0;
            }

            if ( ! $product_id ) {
                ?>
                <div id='woobt_settings' class='panel woocommerce_options_panel woobt_table'>
                    <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'woo-bought-together' ); ?></p>
                </div>
                <?php
                return;
            }

            $disable        = get_post_meta( $product_id, 'woobt_disable', true ) ?: 'no';
            $selection      = get_post_meta( $product_id, 'woobt_selection', true ) ?: 'multiple';
            $layout         = get_post_meta( $product_id, 'woobt_layout', true ) ?: 'unset';
            $position       = get_post_meta( $product_id, 'woobt_position', true ) ?: 'unset';
            $atc_button     = get_post_meta( $product_id, 'woobt_atc_button', true ) ?: 'unset';
            $show_this_item = get_post_meta( $product_id, 'woobt_show_this_item', true ) ?: 'unset';
            $quantity       = get_post_meta( $product_id, 'woobt_quantity', true );

            if ( empty( $quantity ) ) {
                if ( get_post_meta( $product_id, 'woobt_custom_qty', true ) === 'on' ) {
                    $quantity = 'custom';
                } elseif ( get_post_meta( $product_id, 'woobt_sync_qty', true ) === 'on' ) {
                    $quantity = 'sync';
                } else {
                    $quantity = 'default';
                }
            }
            ?>
            <div id='woobt_settings' class='panel woocommerce_options_panel woobt_table'>
                <div id="woobt_search_settings" style="display: none"
                     data-title="<?php esc_html_e( 'Search settings', 'woo-bought-together' ); ?>">
                    <table>
                        <?php self::search_settings(); ?>
                        <tr>
                            <th></th>
                            <td>
                                <button id="woobt_search_settings_update" class="button button-primary">
                                    <?php esc_html_e( 'Update Options', 'woo-bought-together' ); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
                <table>
                    <tr>
                        <th><?php esc_html_e( 'Disable', 'woo-bought-together' ); ?></th>
                        <td>
                            <label for="woobt_disable"></label><input id="woobt_disable" name="woobt_disable"
                                                                      type="checkbox" <?php echo esc_attr( $disable === 'yes' ? 'checked' : '' ); ?>/>
                        </td>
                    </tr>
                </table>
                <table class="woobt_table_enable">
                    <tr class="woobt_tr_space">
                        <th><?php esc_html_e( 'Search', 'woo-bought-together' ); ?> (<a
                                    href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=settings#search' ) ); ?>"
                                    id="woobt_search_settings_btn"><?php esc_html_e( 'settings', 'woo-bought-together' ); ?></a>)
                        </th>
                        <td>
                            <div class="w100">
                                    <span class="loading" id="woobt_loading"
                                          style="display: none"><?php esc_html_e( 'searching...', 'woo-bought-together' ); ?></span>
                                <label for="woobt_keyword"></label><input type="search" id="woobt_keyword"
                                                                          placeholder="<?php esc_attr_e( 'Type any keyword to search', 'woo-bought-together' ); ?>"/>
                                <div id="woobt_results" class="woobt_results" style="display: none"></div>
                            </div>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space">
                        <th>
                            <?php esc_html_e( 'Selected', 'woo-bought-together' ); ?>
                            <div class="woobt_tools">
                                <a href="#"
                                   class="woobt-import-export"><?php esc_html_e( 'import/export', 'woo-bought-together' ); ?></a>
                            </div>
                        </th>
                        <td>
                            <div class="w100">
                                <?php echo '<div class="woobt_notice_default">' . sprintf( /* translators: links */ esc_html__( '* If don\'t choose any products, it can shows products from Smart Rules %1$s or Default %2$s.', 'woo-bought-together' ), '<a
                                                href="' . esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=rules' ) ) . '" target="_blank">' . esc_html__( 'here', 'woo-bought-together' ) . '</a>', '<a
                                                href="' . esc_url( admin_url( 'admin.php?page=wpclever-woobt&tab=settings' ) ) . '" target="_blank">' . esc_html__( 'here', 'woo-bought-together' ) . '</a>' ) . '</div>'; ?>
                                <div id="woobt_selected" class="woobt_selected">
                                    <ul>
                                        <?php
                                        if ( $items = WPCleverWoobt::instance()->get_product_items( $product_id, 'edit' ) ) {
                                            foreach ( $items as $item_key => $item ) {
                                                if ( ! empty( $item['id'] ) ) {
                                                    $item_id      = $item['id'];
                                                    $item_price   = $item['price'];
                                                    $item_qty     = $item['qty'];
                                                    $item_product = wc_get_product( $item_id );

                                                    if ( ! $item_product ) {
                                                        self::product_data_li_deleted( $item_id, $item_key );
                                                    } else {
                                                        self::product_data_li( $item_product, $item_price, $item_qty, false, $item_key );
                                                    }
                                                } else {
                                                    self::text_data_li( $item, $item_key );
                                                }
                                            }
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space">
                        <th></th>
                        <td>
                            <a href="#" class="woobt_add_text button">
                                <?php esc_html_e( '+ Add heading/paragraph', 'woo-bought-together' ); ?>
                            </a>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space">
                        <th><?php esc_html_e( 'Add separately', 'woo-bought-together' ); ?></th>
                        <td>
                            <label for="woobt_separately"></label><input id="woobt_separately"
                                                                         name="woobt_separately"
                                                                         type="checkbox" <?php echo esc_attr( get_post_meta( $product_id, 'woobt_separately', true ) === 'on' ? 'checked' : '' ); ?>/>
                            <span class="woocommerce-help-tip"
                                  data-tip="<?php esc_attr_e( 'If enabled, the associated products will be added as separate items and stay unaffected from the main product, their prices will change back to the original.', 'woo-bought-together' ); ?>"></span>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space">
                        <th><?php esc_html_e( 'Discount', 'woo-bought-together' ); ?></th>
                        <td>
                            <label for="woobt_discount"></label><input id="woobt_discount" name="woobt_discount"
                                                                       type="number" min="0" max="100"
                                                                       step="0.0001" style="width: 50px"
                                                                       value="<?php echo get_post_meta( $product_id, 'woobt_discount', true ); ?>"/>%
                            <span class="woocommerce-help-tip"
                                  data-tip="<?php esc_attr_e( 'Discount for the main product when buying at least one product in this list.', 'woo-bought-together' ); ?>"></span>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space">
                        <th><?php esc_html_e( 'Selecting method', 'woo-bought-together' ); ?></th>
                        <td>
                            <label> <select name="woobt_selection">
                                    <option value="multiple" <?php selected( $selection, 'multiple' ); ?>><?php esc_html_e( 'Multiple selection (default)', 'woo-bought-together' ); ?></option>
                                    <option value="single" <?php selected( $selection, 'single' ); ?>><?php esc_html_e( 'Single selection (choose 1 only)', 'woo-bought-together' ); ?></option>
                                </select> </label>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space">
                        <th><?php esc_html_e( 'Checked all', 'woo-bought-together' ); ?></th>
                        <td>
                            <input id="woobt_checked_all" name="woobt_checked_all"
                                   type="checkbox" <?php echo esc_attr( apply_filters( 'woobt_checked_all', get_post_meta( $product_id, 'woobt_checked_all', true ) === 'on', $product_id ) ? 'checked' : '' ); ?>/>
                            <label for="woobt_checked_all"><?php esc_html_e( 'Checked all by default.', 'woo-bought-together' ); ?></label>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space">
                        <th><?php esc_html_e( 'Item quantity', 'woo-bought-together' ); ?></th>
                        <td>
                            <label>
                                <select id="woobt_quantity" name="woobt_quantity">
                                    <option value="default" <?php selected( $quantity, 'default' ); ?>><?php esc_html_e( 'Default', 'woo-bought-together' ); ?></option>
                                    <option value="custom" <?php selected( $quantity, 'custom' ); ?>><?php esc_html_e( 'Custom', 'woo-bought-together' ); ?></option>
                                    <option value="sync" <?php selected( $quantity, 'sync' ); ?>><?php esc_html_e( 'Sync', 'woo-bought-together' ); ?></option>
                                </select>
                            </label>
                            <span class="description"><?php esc_html_e( 'Default: use default quantity; Custom: allow buyer to change quantity; Sync: sync with main product quantity.', 'woo-bought-together' ); ?></span>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space woobt_tr_show_if_custom_qty">
                        <th><?php esc_html_e( 'Limit each item', 'woo-bought-together' ); ?></th>
                        <td>
                            <input id="woobt_limit_each_min_default" name="woobt_limit_each_min_default"
                                   type="checkbox" <?php echo esc_attr( get_post_meta( $product_id, 'woobt_limit_each_min_default', true ) === 'on' ? 'checked' : '' ); ?>/>
                            <label for="woobt_limit_each_min_default"><?php esc_html_e( 'Use default quantity as min', 'woo-bought-together' ); ?></label>
                            <u>or</u> Min <label>
                                <input name="woobt_limit_each_min" type="number" min="0" class="small-text woobt_limit_each_input"
                                       value="<?php echo esc_attr( get_post_meta( $product_id, 'woobt_limit_each_min', true ) ?: '' ); ?>"/>
                            </label> Max <label>
                                <input name="woobt_limit_each_max" type="number" min="1" class="small-text woobt_limit_each_input"
                                       value="<?php echo esc_attr( get_post_meta( $product_id, 'woobt_limit_each_max', true ) ?: '' ); ?>"/>
                            </label>
                        </td>
                    </tr>
                    <?php do_action( 'woobt_product_settings', $product_id ); ?>
                    <tr class="woobt_tr_space">
                        <th><?php esc_html_e( 'Displaying', 'woo-bought-together' ); ?></th>
                        <td>
                            <a href="#"
                               class="woobt_displaying"><?php esc_html_e( 'Overwrite the default displaying settings', 'woo-bought-together' ); ?></a>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space woobt_show_if_displaying">
                        <th><?php esc_html_e( 'Layout', 'woo-bought-together' ); ?></th>
                        <td>
                            <label> <select name="woobt_layout">
                                    <option value="unset" <?php selected( $layout, 'unset' ); ?>><?php esc_html_e( 'Unset (default setting)', 'woo-bought-together' ); ?></option>
                                    <option value="default" <?php selected( $layout, 'default' ); ?>><?php esc_html_e( 'List', 'woo-bought-together' ); ?></option>
                                    <option value="compact" <?php selected( $layout, 'compact' ); ?>><?php esc_html_e( 'Compact', 'woo-bought-together' ); ?></option>
                                    <option value="separate" <?php selected( $layout, 'separate' ); ?>><?php esc_html_e( 'Separate images', 'woo-bought-together' ); ?></option>
                                    <option value="grid-2" <?php selected( $layout, 'grid-2' ); ?>><?php esc_html_e( 'Grid - 2 columns', 'woo-bought-together' ); ?></option>
                                    <option value="grid-3" <?php selected( $layout, 'grid-3' ); ?>><?php esc_html_e( 'Grid - 3 columns', 'woo-bought-together' ); ?></option>
                                    <option value="grid-4" <?php selected( $layout, 'grid-4' ); ?>><?php esc_html_e( 'Grid - 4 columns', 'woo-bought-together' ); ?></option>
                                    <option value="carousel-2" <?php selected( $layout, 'carousel-2' ); ?>><?php esc_html_e( 'Carousel - 2 columns', 'woo-bought-together' ); ?></option>
                                    <option value="carousel-3" <?php selected( $layout, 'carousel-3' ); ?>><?php esc_html_e( 'Carousel - 3 columns', 'woo-bought-together' ); ?></option>
                                    <option value="carousel-4" <?php selected( $layout, 'carousel-4' ); ?>><?php esc_html_e( 'Carousel - 4 columns', 'woo-bought-together' ); ?></option>
                                </select> </label>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space woobt_show_if_displaying">
                        <th><?php esc_html_e( 'Position', 'woo-bought-together' ); ?></th>
                        <td>
                            <?php
                            if ( is_array( WPCleverWoobt::$positions ) && ( count( WPCleverWoobt::$positions ) > 0 ) ) {
                                echo '<select name="woobt_position">';

                                echo '<option value="unset" ' . ( 'unset' === $position ? 'selected' : '' ) . '>' . esc_html__( 'Unset (default setting)', 'woo-bought-together' ) . '</option>';

                                foreach ( WPCleverWoobt::$positions as $k => $p ) {
                                    echo '<option value="' . esc_attr( $k ) . '" ' . ( $k === $position ? 'selected' : '' ) . '>' . esc_html( $p ) . '</option>';
                                }

                                echo '</select>';
                            }
                            ?>
                            <span class="description"><?php esc_html_e( 'Choose the position to show the products list. You also can use the shortcode [woobt] to show the list where you want.', 'woo-bought-together' ); ?></span>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space woobt_show_if_displaying">
                        <th><?php esc_html_e( 'Add to cart button', 'woo-bought-together' ); ?></th>
                        <td>
                            <label> <select name="woobt_atc_button" class="woobt_atc_button">
                                    <option value="unset" <?php selected( $atc_button, 'unset' ); ?>><?php esc_html_e( 'Unset (default setting)', 'woo-bought-together' ); ?></option>
                                    <option value="main" <?php selected( $atc_button, 'main' ); ?>><?php esc_html_e( 'Main product\'s button', 'woo-bought-together' ); ?></option>
                                    <option value="separate" <?php selected( $atc_button, 'separate' ); ?>><?php esc_html_e( 'Separate buttons', 'woo-bought-together' ); ?></option>
                                </select> </label>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space woobt_show_if_displaying">
                        <th><?php esc_html_e( 'Show "this item"', 'woo-bought-together' ); ?></th>
                        <td>
                            <label> <select name="woobt_show_this_item" class="woobt_show_this_item">
                                    <option value="unset" <?php selected( $show_this_item, 'unset' ); ?>><?php esc_html_e( 'Unset (default setting)', 'woo-bought-together' ); ?></option>
                                    <option value="yes" <?php selected( $show_this_item, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-bought-together' ); ?></option>
                                    <option value="no" <?php selected( $show_this_item, 'no' ); ?>><?php esc_html_e( 'No', 'woo-bought-together' ); ?></option>
                                </select> </label>
                            <span class="description"><?php esc_html_e( '"This item" cannot be hidden if "Separate buttons" is in use for the Add to Cart button.', 'woo-bought-together' ); ?></span>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space woobt_show_if_displaying">
                        <th><?php esc_html_e( 'Above text', 'woo-bought-together' ); ?></th>
                        <td>
                            <div class="w100">
                                <label>
                                        <textarea name="woobt_before_text" rows="1"
                                                  style="width: 100%"><?php echo esc_textarea( get_post_meta( $product_id, 'woobt_before_text', true ) ); ?></textarea>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr class="woobt_tr_space woobt_show_if_displaying">
                        <th><?php esc_html_e( 'Under text', 'woo-bought-together' ); ?></th>
                        <td>
                            <div class="w100">
                                <label>
                                        <textarea name="woobt_after_text" rows="1"
                                                  style="width: 100%"><?php echo esc_textarea( get_post_meta( $product_id, 'woobt_after_text', true ) ); ?></textarea>
                                </label>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        }

        function process_product_meta( $post_id ) {
            if ( isset( $_POST['woobt_disable'] ) ) {
                update_post_meta( $post_id, 'woobt_disable', 'yes' );
            } else {
                update_post_meta( $post_id, 'woobt_disable', 'no' );
            }

            if ( isset( $_POST['woobt_ids'] ) ) {
                update_post_meta( $post_id, 'woobt_ids', WPCleverWoobt_Helper()->sanitize_array( wp_unslash( $_POST['woobt_ids'] ?? '' ) ) );
            } else {
                delete_post_meta( $post_id, 'woobt_ids' );
            }

            // Invalidate product items cache
            wp_cache_delete( 'woobt_items_' . $post_id . '_view', 'woobt' );
            wp_cache_delete( 'woobt_items_' . $post_id . '_validate', 'woobt' );

            if ( isset( $_POST['woobt_discount'] ) ) {
                update_post_meta( $post_id, 'woobt_discount', sanitize_text_field( wp_unslash( $_POST['woobt_discount'] ?? '' ) ) );
            }

            if ( isset( $_POST['woobt_checked_all'] ) ) {
                update_post_meta( $post_id, 'woobt_checked_all', 'on' );
            } else {
                update_post_meta( $post_id, 'woobt_checked_all', 'off' );
            }

            if ( isset( $_POST['woobt_separately'] ) ) {
                update_post_meta( $post_id, 'woobt_separately', 'on' );
            } else {
                update_post_meta( $post_id, 'woobt_separately', 'off' );
            }

            if ( isset( $_POST['woobt_selection'] ) ) {
                update_post_meta( $post_id, 'woobt_selection', sanitize_text_field( wp_unslash( $_POST['woobt_selection'] ?? '' ) ) );
            }

            if ( isset( $_POST['woobt_quantity'] ) ) {
                $quantity = sanitize_text_field( wp_unslash( $_POST['woobt_quantity'] ) );
                update_post_meta( $post_id, 'woobt_quantity', $quantity );
                // Backwards compatibility with previous woobt_custom_qty and woobt_sync_qty meta
                update_post_meta( $post_id, 'woobt_custom_qty', $quantity === 'custom' ? 'on' : 'off' );
                update_post_meta( $post_id, 'woobt_sync_qty', $quantity === 'sync' ? 'on' : 'off' );
            } elseif ( isset( $_POST['woobt_custom_qty'] ) ) {
                update_post_meta( $post_id, 'woobt_custom_qty', 'on' );
                update_post_meta( $post_id, 'woobt_quantity', 'custom' );
            } elseif ( isset( $_POST['woobt_sync_qty'] ) ) {
                update_post_meta( $post_id, 'woobt_sync_qty', 'on' );
                update_post_meta( $post_id, 'woobt_quantity', 'sync' );
            }

            if ( isset( $_POST['woobt_limit_each_min_default'] ) ) {
                update_post_meta( $post_id, 'woobt_limit_each_min_default', 'on' );
            } else {
                update_post_meta( $post_id, 'woobt_limit_each_min_default', 'off' );
            }

            if ( isset( $_POST['woobt_limit_each_min'] ) ) {
                update_post_meta( $post_id, 'woobt_limit_each_min', sanitize_text_field( wp_unslash( $_POST['woobt_limit_each_min'] ?? '' ) ) );
            }

            if ( isset( $_POST['woobt_limit_each_max'] ) ) {
                update_post_meta( $post_id, 'woobt_limit_each_max', sanitize_text_field( wp_unslash( $_POST['woobt_limit_each_max'] ?? '' ) ) );
            }

            // overwrite displaying

            if ( isset( $_POST['woobt_layout'] ) ) {
                update_post_meta( $post_id, 'woobt_layout', sanitize_text_field( wp_unslash( $_POST['woobt_layout'] ?? '' ) ) );
            }

            if ( isset( $_POST['woobt_position'] ) ) {
                update_post_meta( $post_id, 'woobt_position', sanitize_text_field( wp_unslash( $_POST['woobt_position'] ?? '' ) ) );
            }

            if ( isset( $_POST['woobt_atc_button'] ) ) {
                update_post_meta( $post_id, 'woobt_atc_button', sanitize_text_field( wp_unslash( $_POST['woobt_atc_button'] ?? '' ) ) );
            }

            if ( isset( $_POST['woobt_show_this_item'] ) ) {
                update_post_meta( $post_id, 'woobt_show_this_item', sanitize_text_field( wp_unslash( $_POST['woobt_show_this_item'] ?? '' ) ) );
            }

            if ( isset( $_POST['woobt_before_text'] ) ) {
                update_post_meta( $post_id, 'woobt_before_text', sanitize_post_field( 'post_content', wp_unslash( $_POST['woobt_before_text'] ?? '' ), $post_id, 'display' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }

            if ( isset( $_POST['woobt_after_text'] ) ) {
                update_post_meta( $post_id, 'woobt_after_text', sanitize_post_field( 'post_content', wp_unslash( $_POST['woobt_after_text'] ?? '' ), $post_id, 'display' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }
        }

        function ajax_import_export() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $ids      = [];
            $ids_arr  = [];
            $ids_data = sanitize_post( wp_unslash( $_POST['ids'] ?? '' ) );
            parse_str( $ids_data, $ids_arr );

            if ( isset( $ids_arr['woobt_ids'] ) && is_array( $ids_arr['woobt_ids'] ) ) {
                $ids = $ids_arr['woobt_ids'];
            }

            echo '<textarea class="woobt_import_export_data" style="width: 100%; height: 200px">' . esc_textarea( ( ! empty( $ids ) ? wp_json_encode( $ids, JSON_PRETTY_PRINT ) : '' ) ) . '</textarea>';
            echo '<div style="display: flex; align-items: center"><button class="button button-primary woobt-import-export-save">' . esc_html__( 'Update', 'woo-bought-together' ) . '</button>';
            echo '<span style="color: #ff4f3b; font-size: 10px; margin-left: 10px">' . esc_html__( '* All selected products will be replaced after pressing Update!', 'woo-bought-together' ) . '</span>';
            echo '</div>';

            wp_die();
        }

        function ajax_import_export_save() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woobt-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $ids = sanitize_textarea_field( wp_unslash( $_POST['ids'] ?? '' ) );

            if ( ! empty( $ids ) ) {
                $items = json_decode( stripcslashes( $ids ), true );

                if ( ! empty( $items ) ) {
                    foreach ( $items as $item_key => $item ) {
                        if ( ! empty( $item['id'] ) ) {
                            $item_id      = $item['id'];
                            $item_price   = $item['price'];
                            $item_qty     = $item['qty'];
                            $item_product = wc_get_product( $item_id );

                            if ( ! $item_product ) {
                                continue;
                            }

                            self::product_data_li( $item_product, $item_price, $item_qty, false, $item_key );
                        } else {
                            self::text_data_li( $item, $item_key );
                        }
                    }
                }
            }

            wp_die();
        }

        function product_filter( $filters ) {
            $filters['woobt'] = [ $this, 'product_filter_callback' ];

            return $filters;
        }

        function product_filter_callback() {
            $woobt  = wc_clean( wp_unslash( $_REQUEST['woobt'] ?? '' ) );
            $output = '<select name="woobt"><option value="">' . esc_html__( 'Bought together', 'woo-bought-together' ) . '</option>';
            $output .= '<option value="yes" ' . selected( $woobt, 'yes', false ) . '>' . esc_html__( 'With associated products', 'woo-bought-together' ) . '</option>';
            $output .= '<option value="no" ' . selected( $woobt, 'no', false ) . '>' . esc_html__( 'Without associated products', 'woo-bought-together' ) . '</option>';
            $output .= '</select>';
            echo $output;
        }

        function apply_product_filter( $query ) {
            global $pagenow;

            if ( $query->is_admin && $pagenow == 'edit.php' && isset( $_GET['woobt'] ) && wp_unslash( $_GET['woobt'] ?? '' ) != '' && wp_unslash( $_GET['post_type'] ?? '' ) == 'product' ) {
                $meta_query = (array) $query->get( 'meta_query' );

                if ( wp_unslash( $_GET['woobt'] ?? '' ) === 'yes' ) {
                    $meta_query[] = [
                            'relation' => 'AND',
                            [
                                    'key'     => 'woobt_ids',
                                    'compare' => 'EXISTS'
                            ],
                            [
                                    'key'     => 'woobt_ids',
                                    'value'   => '',
                                    'compare' => '!='
                            ],
                    ];
                } else {
                    $meta_query[] = [
                            'relation' => 'OR',
                            [
                                    'key'     => 'woobt_ids',
                                    'compare' => 'NOT EXISTS'
                            ],
                            [
                                    'key'     => 'woobt_ids',
                                    'value'   => '',
                                    'compare' => '=='
                            ],
                    ];
                }

                $query->set( 'meta_query', $meta_query );
            }
        }

        function export_process( $value, $meta, $product ) {
            if ( $meta->key === 'woobt_ids' ) {
                $ids = get_post_meta( $product->get_id(), 'woobt_ids', true );

                if ( ! empty( $ids ) && is_array( $ids ) ) {
                    return json_encode( $ids );
                }
            }

            return $value;
        }

        function import_process( $object, $data ) {
            if ( isset( $data['meta_data'] ) ) {
                foreach ( $data['meta_data'] as $meta ) {
                    if ( $meta['key'] === 'woobt_ids' ) {
                        $object->update_meta_data( 'woobt_ids', json_decode( $meta['value'], true ) );
                        break;
                    }
                }
            }

            return $object;
        }


    }
}

WPCleverWoobt_Backend::instance();
