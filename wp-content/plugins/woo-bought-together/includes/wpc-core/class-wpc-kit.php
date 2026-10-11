<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverKit' ) ) {
    class WPCleverKit {
        protected static $plugins = [
                'wc-save-for-later'              => 'wpc-save-for-later.php',
                'woo-product-bundle'             => 'wpc-product-bundles.php',
                'woo-bought-together'            => 'wpc-frequently-bought-together.php',
                'woo-smart-compare'              => 'wpc-smart-compare.php',
                'woo-smart-quick-view'           => 'wpc-smart-quick-view.php',
                'woo-smart-wishlist'             => 'wpc-smart-wishlist.php',
                'woo-fly-cart'                   => 'wpc-fly-cart.php',
                'woo-product-timer'              => 'wpc-product-timer.php',
                'woo-added-to-cart-notification' => 'wpc-added-to-cart-notification.php',
                'woo-order-notes'                => 'wpc-order-notes.php'
        ];

        function __construct() {
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
            add_action( 'admin_menu', [ $this, 'admin_menu' ] );
            add_action( 'wp_ajax_wpc_get_essential_kit', [ $this, 'ajax_get_essential_kit' ] );
        }

        function admin_scripts( $hook ) {
            if ( strpos( $hook, 'wpclever-kit' ) ) {
                wp_enqueue_style( 'wpc-dashboard', WPC_CORE_URI . 'assets/css/dashboard.css', [], WPC_CORE_VERSION );
                wp_enqueue_style( 'wpc-kit', WPC_CORE_URI . 'assets/css/kit.css', [ 'wpc-dashboard' ], WPC_CORE_VERSION );
                wp_enqueue_script( 'wpc-kit', WPC_CORE_URI . 'assets/js/kit.js', [ 'jquery' ], WPC_CORE_VERSION, true );
                wp_localize_script( 'wpc-kit', 'wpc_kit_vars', [
                                'nonce' => wp_create_nonce( 'wpc_kit' ),
                        ]
                );
            }
        }

        function admin_menu() {
            add_submenu_page( 'wpclever', 'WPC Essential Kit', 'Essential Kit', 'manage_options', 'wpclever-kit', [
                    $this,
                    'admin_menu_content'
            ], 2 );
        }

        function admin_menu_content() {
            add_thickbox();

            if ( ! function_exists( 'plugins_api' ) ) {
                include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }

            if ( isset( $_GET['action'], $_GET['plugin'], $_GET['_wpnonce'] ) && ( $_GET['action'] === 'activate' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'activate-plugin_' . sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) ) ) {
                activate_plugin( sanitize_text_field( wp_unslash( $_GET['plugin'] ?? '' ) ), '', false, true );
            }

            if ( isset( $_GET['action'], $_GET['plugin'], $_GET['_wpnonce'] ) && ( $_GET['action'] === 'deactivate' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'deactivate-plugin_' . sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) ) ) {
                deactivate_plugins( sanitize_text_field( wp_unslash( $_GET['plugin'] ?? '' ) ) );
            }
            ?>
            <div class="wpclever_page wrap">
                <?php WPCleverDashboard::render_header( 'kit' ); ?>

                <div class="wpclever_kit_toolbar">
                    <span class="wpclever_kit_order">
                        <a href="#" class="wpclever_kit_order_a" data-o="p">popular</a> |
                        <a href="#" class="wpclever_kit_order_a" data-o="u">last updated</a> |
                        <a href="#" class="wpclever_kit_search_btn" title="Search"><span class="dashicons dashicons-search"></span></a>
                    </span>
                    <span class="wpclever_kit_search" style="display:none">
                        <input type="text" class="wpclever_kit_search_input" placeholder="Search plugins…" />
                        <a href="#" class="wpclever_kit_search_close" title="Close">&times;</a>
                    </span>
                </div>

                <div class="wpclever_essential_kit_wrapper"></div>
            </div>
            <?php
        }

        function ajax_get_essential_kit() {
            if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['security'] ) ), 'wpc_kit' ) ) {
                die( 'Permissions check failed!' );
            }

            if ( false === ( $plugins_arr = get_transient( 'wpclever_plugins' ) ) ) {
                // Use API v1.2 which returns JSON instead of serialized PHP (safer and more reliable)
                $url      = 'https://api.wordpress.org/plugins/info/1.2/';
                $request  = [
                        'action'  => 'query_plugins',
                        'timeout' => 30,
                        'request' => [
                                'author'   => 'wpclever',
                                'per_page' => 120,
                                'page'     => 1,
                                'fields'   => [
                                        'slug'              => true,
                                        'name'              => true,
                                        'version'           => true,
                                        'downloaded'        => true,
                                        'active_installs'   => true,
                                        'last_updated'      => true,
                                        'rating'            => true,
                                        'num_ratings'       => true,
                                        'short_description' => true,
                                ],
                        ],
                ];
                $response = wp_remote_get( add_query_arg( $request, $url ), [ 'timeout' => 30 ] );

                if ( ! is_wp_error( $response ) ) {
                    $plugins_arr = [];

                    // API v1.2 returns JSON - safe to decode without unserialize
                    $data = json_decode( wp_remote_retrieve_body( $response ), true );

                    // Validate the decoded structure before use
                    if ( is_array( $data ) && isset( $data['plugins'] ) && is_array( $data['plugins'] ) && ( count( $data['plugins'] ) > 0 ) ) {
                        foreach ( $data['plugins'] as $pl ) {
                            if ( ! is_array( $pl ) ) {
                                continue;
                            }

                            $plugins_arr[] = [
                                    'slug'              => isset( $pl['slug'] ) ? (string) $pl['slug'] : '',
                                    'name'              => isset( $pl['name'] ) ? (string) $pl['name'] : '',
                                    'version'           => isset( $pl['version'] ) ? (string) $pl['version'] : '',
                                    'downloaded'        => isset( $pl['downloaded'] ) ? (int) $pl['downloaded'] : 0,
                                    'active_installs'   => isset( $pl['active_installs'] ) ? (int) $pl['active_installs'] : 0,
                                    'last_updated'      => isset( $pl['last_updated'] ) ? strtotime( (string) $pl['last_updated'] ) : 0,
                                    'rating'            => isset( $pl['rating'] ) ? (float) $pl['rating'] : 0,
                                    'num_ratings'       => isset( $pl['num_ratings'] ) ? (int) $pl['num_ratings'] : 0,
                                    'short_description' => isset( $pl['short_description'] ) ? (string) $pl['short_description'] : '',
                            ];
                        }
                    }

                    set_transient( 'wpclever_plugins', $plugins_arr, 24 * HOUR_IN_SECONDS );
                } else {
                    echo 'Have an error while loading the essential kit. Please visit our website <a href="https://wpclever.net?utm_source=visit&utm_medium=menu&utm_campaign=wporg" target="_blank">https://wpclever.net</a>';
                }
            }

            if ( is_array( $plugins_arr ) && ( count( $plugins_arr ) > 0 ) ) {
                array_multisort( array_column( $plugins_arr, 'active_installs' ), SORT_DESC, $plugins_arr );

                foreach ( $plugins_arr as $plugin ) {
                    $plugin_slug = $plugin['slug'];

                    if ( isset( self::$plugins[ $plugin_slug ] ) ) {
                        $plugin_file = self::$plugins[ $plugin_slug ];
                    } else {
                        $plugin_file = $plugin_slug . '.php';
                    }

                    $details_link = network_admin_url( 'plugin-install.php?tab=plugin-information&amp;plugin=' . $plugin['slug'] . '&amp;TB_iframe=true&amp;width=600&amp;height=550' );
                    ?>
                    <div class="wpc-plugin-card <?php echo esc_attr( $plugin_slug ); ?>"
                         id="<?php echo esc_attr( $plugin_slug ); ?>"
                         data-p="<?php echo esc_attr( $plugin['active_installs'] ?? 0 ); ?>"
                         data-u="<?php echo esc_attr( $plugin['last_updated'] ?? 0 ); ?>">
                        <div class="wpc-plugin-card-top">
                            <a href="<?php echo esc_url( $details_link ); ?>" class="thickbox wpc-plugin-card-icon"
                               title="<?php echo esc_attr( $plugin['name'] ); ?>">
                                <img src="<?php echo esc_url( 'https://api.wpclever.net/images/' . $plugin_slug . '.png' ); ?>"
                                     class="plugin-icon" alt="<?php echo esc_attr( $plugin['name'] ); ?>"/>
                            </a>
                            <div class="wpc-plugin-card-info">
                                <div class="name column-name">
                                    <h3>
                                        <a class="thickbox" title="<?php echo esc_attr( $plugin['name'] ); ?>"
                                           href="<?php echo esc_url( $details_link ); ?>">
                                            <?php echo esc_html( $plugin['name'] ); ?>
                                        </a>
                                    </h3>
                                </div>
                                <div class="desc column-description">
                                    <p><?php echo esc_html( $plugin['short_description'] ?? '' ); ?></p>
                                </div>
                                <div class="action-links">
                                    <ul class="plugin-action-buttons">
                                        <li>
                                            <?php if ( $this->is_plugin_installed( $plugin_slug, $plugin_file ) ) {
                                                if ( $this->is_plugin_active( $plugin_slug, $plugin_file ) ) {
                                                    ?>
                                                    <a href="<?php echo esc_url( $this->deactivate_plugin_link( $plugin_slug, $plugin_file ) ); ?>"
                                                       class="button deactivate-now">
                                                        Deactivate
                                                    </a>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <a href="<?php echo esc_url( $this->activate_plugin_link( $plugin_slug, $plugin_file ) ); ?>"
                                                       class="button activate-now">
                                                        Activate
                                                    </a>
                                                    <?php
                                                }
                                            } else { ?>
                                                <a href="<?php echo esc_url( $this->install_plugin_link( $plugin_slug ) ); ?>"
                                                   class="button install-now">
                                                    Install Now
                                                </a>
                                            <?php } ?>
                                        </li>
                                        <li>
                                            <a href="<?php echo esc_url( $details_link ); ?>"
                                               class="thickbox open-plugin-details-modal"
                                               aria-label="<?php echo esc_attr( sprintf( 'More information about %s', $plugin['name'] ) ); ?>"
                                               title="<?php echo esc_attr( $plugin['name'] ); ?>">
                                                More Details
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php
                        echo '<div class="wpc-plugin-card-bottom">';
                        echo '<div class="wpc-plugin-card-bottom-left">';

                        if ( isset( $plugin['version'] ) ) { ?>
                            <div class="wpc-plugin-card-version">
                                <strong>Version:</strong>
                                <span><?php echo esc_html( $plugin['version'] ); ?></span>
                            </div>
                        <?php }

                        if ( isset( $plugin['last_updated'] ) ) { ?>
                            <div class="wpc-plugin-card-updated">
                                <strong>Last Updated:</strong>
                                <span><?php printf( '%s ago', esc_html( human_time_diff( $plugin['last_updated'] ) ) ); ?></span>
                            </div>
                        <?php }

                        echo '</div>';
                        echo '<div class="wpc-plugin-card-bottom-right">';

                        if ( isset( $plugin['rating'], $plugin['num_ratings'] ) ) { ?>
                            <div class="wpc-plugin-card-rating">
                                <?php
                                wp_star_rating(
                                        [
                                                'rating' => $plugin['rating'],
                                                'type'   => 'percent',
                                                'number' => $plugin['num_ratings'],
                                        ]
                                );
                                ?>
                                <span class="num-ratings">(<?php echo esc_html( number_format_i18n( $plugin['num_ratings'] ) ); ?>)</span>
                            </div>
                        <?php }

                        if ( isset( $plugin['active_installs'] ) ) { ?>
                            <div class="wpc-plugin-card-installed">
                                <?php echo esc_html( number_format_i18n( $plugin['active_installs'] ) ) . '+ Active Installations'; ?>
                            </div>
                        <?php }

                        echo '</div>';
                        echo '</div>';

                        if ( $this->is_plugin_installed( $plugin_slug, $plugin_file, true ) ) {
                            ?>
                            <div class="wpc-plugin-card-bottom premium">
                                <div class="text">
                                    ✓ Premium version was installed.
                                </div>
                                <div class="btn">
                                    <?php
                                    if ( $this->is_plugin_active( $plugin_slug, $plugin_file, true ) ) {
                                        ?>
                                        <a href="<?php echo esc_url( $this->deactivate_plugin_link( $plugin_slug, $plugin_file, true ) ); ?>"
                                           class="button deactivate-now">
                                            Deactivate
                                        </a>
                                        <?php
                                    } else {
                                        ?>
                                        <a href="<?php echo esc_url( $this->activate_plugin_link( $plugin_slug, $plugin_file, true ) ); ?>"
                                           class="button activate-now">
                                            Activate
                                        </a>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                }
            } else {
                echo 'Have an error while loading the essential kit. Please visit our website <a href="https://wpclever.net?utm_source=visit&utm_medium=menu&utm_campaign=wporg" target="_blank">https://wpclever.net</a>';
            }

            wp_die();
        }

        public function plugin_index_by_slug( $slug, $array ) {
            foreach ( $array as $key => $val ) {
                if ( $val['slug'] === $slug ) {
                    return $key;
                }
            }

            return null;
        }

        public function is_plugin_installed( $plugin_slug, $plugin_file, $premium = false ) {
            if ( $premium ) {
                return file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug . '-premium/' . $plugin_file );
            } else {
                return file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_file );
            }
        }

        public function is_plugin_active( $plugin_slug, $plugin_file, $premium = false ) {
            if ( $premium ) {
                return is_plugin_active( $plugin_slug . '-premium/' . $plugin_file );
            } else {
                return is_plugin_active( $plugin_slug . '/' . $plugin_file );
            }
        }

        public function install_plugin_link( $plugin_slug ) {
            return wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $plugin_slug ), 'install-plugin_' . $plugin_slug );
        }

        public function activate_plugin_link( $plugin_slug, $plugin_file, $premium = false ) {
            if ( $premium ) {
                return wp_nonce_url( admin_url( 'admin.php?page=wpclever-kit&action=activate&plugin=' . $plugin_slug . '-premium/' . $plugin_file . '#' . $plugin_slug ), 'activate-plugin_' . $plugin_slug . '-premium/' . $plugin_file );
            } else {
                return wp_nonce_url( admin_url( 'admin.php?page=wpclever-kit&action=activate&plugin=' . $plugin_slug . '/' . $plugin_file . '#' . $plugin_slug ), 'activate-plugin_' . $plugin_slug . '/' . $plugin_file );
            }
        }

        public function deactivate_plugin_link( $plugin_slug, $plugin_file, $premium = false ) {
            if ( $premium ) {
                return wp_nonce_url( admin_url( 'admin.php?page=wpclever-kit&action=deactivate&plugin=' . $plugin_slug . '-premium/' . $plugin_file . '#' . $plugin_slug ), 'deactivate-plugin_' . $plugin_slug . '-premium/' . $plugin_file );
            } else {
                return wp_nonce_url( admin_url( 'admin.php?page=wpclever-kit&action=deactivate&plugin=' . $plugin_slug . '/' . $plugin_file . '#' . $plugin_slug ), 'deactivate-plugin_' . $plugin_slug . '/' . $plugin_file );
            }
        }
    }

    new WPCleverKit();
}
