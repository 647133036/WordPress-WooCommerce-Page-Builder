<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WWP_Plugin_Installer' ) ) {
	/**
	 * Defines the Wholesale Plugin install.
	 *
	 * @since   2.2.1
	 */
	class WWP_Plugin_Installer {
		/**
		 * Holds singleton instance of the class.
		 *
		 * @var WWP_Plugin_Installer
		 * @since   2.2.1
		 */
		private static $_instance;

        /**
         * Get the allowed plugins.
         *
         * @var $allowed_plugins
         * @since 2.2.1
         * @access private
         *
         * @return array
         */
        private $allowed_plugins = array(
			'advanced-coupons-for-woocommerce-free' => 'advanced-coupons-for-woocommerce-free/advanced-coupons-for-woocommerce-free.php',
			'wc-vendors'                            => 'wc-vendors/class-wc-vendors.php',
			'storeagent-ai-for-woocommerce'         => 'storeagent-ai-for-woocommerce/storeagent-ai-for-woocommerce.php',
			'invoice-gateway-for-woocommerce'       => 'invoice-gateway-for-woocommerce/invoice-gateway-for-woocommerce.php',
			'woo-product-feed-pro'                  => 'woo-product-feed-pro/woocommerce-sea.php',
			'funnelkit-stripe-woo-payment-gateway'  => 'funnelkit-stripe-woo-payment-gateway/funnelkit-stripe-woo-payment-gateway.php',
			'woocommerce-store-toolkit'             => 'woocommerce-store-toolkit/store-toolkit.php',
			'woocommerce-store-exporter'            => 'woocommerce-exporter/exporter.php',
            'saveto-wishlist-lite-for-woocommerce'  => 'saveto-wishlist-lite-for-woocommerce/saveto-wishlist-lite-for-woocommerce.php',
		);

		/**
		 * Get or create an instance of the class.
         *
		 * @return WWP_Plugin_Installer
		 * @since   2.2.1
		 */
		public static function instance() {
			if ( ! self::$_instance instanceof self ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

        /**
         * Install and activate a plugin.
         *
         * @since 2.2.1
         * @access public
         */
        public function install_activate_plugin() {
            // Check the nonce.
            if ( false !== check_ajax_referer( 'wwp_install_plugin', 'nonce', false ) ) {
                $plugin_slug = isset( $_REQUEST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin_slug'] ) ) : '';
                $result      = $this->download_and_activate_plugin( $plugin_slug );

                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( $result->get_error_message() );
                } else {
                    wp_send_json_success();
                }
            }
        }

        /**
         * Activate plugin via ajax
         *
         * @since 2.2.1
         * @since 2.3.0 Check the activate_plugins capability, allow only the allowed plugins, and report activation errors.
         *
         * @return void
         */
        public function activate_plugin() {
            if ( ! check_ajax_referer( 'wwp_activate_plugin', 'nonce', false ) || ! current_user_can( 'activate_plugins' ) ) {
                wp_send_json_error( __( 'You do not have permission to activate plugins.', 'woocommerce-wholesale-prices' ), 403 );
            }

            $plugin_file = isset( $_REQUEST['plugin_file'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin_file'] ) ) : '';

            if ( ! in_array( $plugin_file, $this->get_allowed_plugins(), true ) ) {
                wp_send_json_error( __( 'This plugin cannot be activated here.', 'woocommerce-wholesale-prices' ), 400 );
            }

            $result = $this->_activate_plugin( $plugin_file );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( $result->get_error_message() );
            }

            wp_send_json_success();
        }

        /**
         * Download and activate a plugin.
         *
         * @param string $plugin_slug The plugin slug.
         *
         * @since 2.2.1
         * @access public
         *
         * @return bool|\WP_Error
         */
        public function download_and_activate_plugin( $plugin_slug ) {

            // Check if the current user has the required permissions.
            if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
                return new \WP_Error( 'permission_denied', __( 'You do not have sufficient permissions to install and activate plugins.', 'woocommerce-wholesale-prices' ) );
            }

            // Check if the plugin is valid.
            if ( ! $this->_is_plugin_allowed_for_install( $plugin_slug ) ) {
                return new \WP_Error( 'wwp_plugin_not_allowed', __( 'The plugin is not valid.', 'woocommerce-wholesale-prices' ) );
            }

            // Get required files since we're calling this outside of context.
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            // Get the plugin info from WordPress.org's plugin repository.
            $api = plugins_api( 'plugin_information', array( 'slug' => $plugin_slug ) );
            if ( is_wp_error( $api ) ) {
                return $api;
            }

            $plugin_basename = $this->get_plugin_basename_by_slug( $plugin_slug );

            // Check if the plugin is already active.
            if ( is_plugin_active( $plugin_basename ) ) {
                return new \WP_Error( 'wwp_plugin_already_active', __( 'The plugin is already installed.', 'woocommerce-wholesale-prices' ) );
            }

            // Check if the plugin is already installed but inactive, just activate it and return true.
            if ( WWP_Helper_Functions::is_wp_plugin_installed( $plugin_basename ) ) {
                return $this->_activate_plugin( $plugin_basename );
            }

            // Download the plugin.
            $skin     = new \WP_Ajax_Upgrader_Skin();
            $upgrader = new \Plugin_Upgrader( $skin );

            $result = $upgrader->install( $api->download_link );

            // Check if the plugin was installed successfully.
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            // Activate the plugin.
            return $this->_activate_plugin( $plugin_basename );
        }

        /**
         * Activate a plugin.
         *
         * @param string $plugin_basename The plugin basename.
         *
         * @since 2.2.1
         * @access private
         *
         * @return bool|\WP_Error
         */
        private function _activate_plugin( $plugin_basename ) {
            $result = activate_plugin( $plugin_basename );

            if ( ! is_wp_error( $result ) ) {
                // Get plugin slug from basename.
                $plugin_slug = explode( '/', $plugin_basename )[0];

                // Update plugin install information.
                $this->_update_plugin_install_information( $plugin_slug );
            }

            return is_wp_error( $result ) ? $result : true;
        }

        /**
         * Update tracking information after plugin installation.
         *
         * @param string $plugin_slug The plugin slug.
         *
         * @since 2.2.1
         * @since 2.3.0 Write StoreAgent's source to `storeagentai_installed_by`, the key StoreAgent reads.
         * @access private
         *
         * @return void
         */
        private function _update_plugin_install_information( $plugin_slug ) {
            // Update StoreAgent AI source option when StoreAgent AI is installed.
            if ( 'storeagent-ai-for-woocommerce' === $plugin_slug ) {
                update_option( 'storeagentai_installed_by', 'wwp' );
            }

            // Update Advanced Coupons source option when Advanced Coupons is installed.
            if ( 'advanced-coupons-for-woocommerce-free' === $plugin_slug ) {
                update_option( 'acfw_installed_by', 'wwp' );
            }
        }

        /**
         * Check if the plugin is allowed for install.
         *
         * @param string $plugin_slug The plugin slug.
         *
         * @since 2.2.1
         * @access private
         *
         * @return bool
         */
        private function _is_plugin_allowed_for_install( $plugin_slug ) {
            return in_array( $plugin_slug, array_keys( $this->get_allowed_plugins() ), true );
        }

        /**
         * Get the allowed plugins.
         *
         * @since 2.2.1
         * @access public
         *
         * @return array
         */
        public function get_allowed_plugins() {
            // Allow other plugins to be installed but not let them overwrite the ones listed above.
            $extra_allowed_plugins = apply_filters( 'wwp_allowed_install_plugins', array() );

            return array_merge( $this->allowed_plugins, $extra_allowed_plugins );
        }

        /**
         * Get the plugin basename by the plugin slug.
         *
         * @param string $plugin_slug The plugin slug.
         *
         * @since 2.2.1
         * @access public
         *
         * @return string
         */
        public function get_plugin_basename_by_slug( $plugin_slug ) {
            $allowed_plugins = $this->get_allowed_plugins();

            return $allowed_plugins[ $plugin_slug ] ?? '';
        }

		/**
         * Run the actions and filters for the page.
         *
         * @since 2.2.1
         * @access public
         */
        public function run() {
            add_action( 'wp_ajax_wwp_install_activate_plugin', array( $this, 'install_activate_plugin' ) );
            add_action( 'wp_ajax_wwp_activate_plugin', array( $this, 'activate_plugin' ) );
        }
	}
}
