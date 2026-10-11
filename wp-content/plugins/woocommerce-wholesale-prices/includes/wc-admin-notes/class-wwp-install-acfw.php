<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Install_ACFW' ) ) {

    /**
     * Class WWP_Install_ACFW.
     *
     * @since 1.11.5
     */
    class WWP_Install_ACFW {

        /**
         *  WC Admin Note unique name
         *
         * @since 1.11.5
         */
        const NOTE_NAME = 'wc-admin-wwp-install-acfwf';

        /**
         *  WC Admin Note Install URL
         *
         * @since 1.11.9
         */
        const AJAX_INSTALL_URL = 'admin-ajax.php?action=admin_note_install_acfwf&type=install';

        /**
         * WWP_Install_ACFW constructor.
         *
         * @since 1.11.5
         * @access public
         */
        public function __construct() {

            // Register AJAX handler.
            add_action( 'init', array( $this, 'register_ajax_handler' ) );

            // Replace "Install Now" button url.
            add_action( 'init', array( $this, 'replace_install_now_url' ) );

            // Show Note.
            add_action( 'plugins_loaded', array( $this, 'install_acfwf_note' ), 11 );

            // Hide Note.
            add_action( 'plugins_loaded', array( $this, 'dismiss_install_acfwf_note' ), 11 );

            // Set flag to dismiss note.
            add_action( 'woocommerce_note_action_install-acfw', array( $this, 'dismiss_note_on_click' ) );
        }

        /**
         * Check if WWP_SHOW_INSTALL_ACFWF_NOTICE is set to yes then show note.
         * For some reason hooking into the cron action won't fire the install url so workaround is to use a flag and fire the add note on init action.
         * Create Note Condition: - Current user is admin
         *                           - WWP_SHOW_INSTALL_ACFWF_NOTICE flag is 'yes'
         *                           - ACFWF is not installed
         *
         * @since 1.11.5
         * @access public
         */
        public function install_acfwf_note() {

            // If WC Admin is not active then don't proceed.
            if ( ! WWP_Helper_Functions::is_wc_admin_active() ) {
                return;
            }

            if (
                get_option( WWP_SHOW_INSTALL_ACFWF_NOTICE ) === 'yes' &&
                current_user_can( 'administrator' ) && // phpcs:ignore
                ! WWP_Helper_Functions::is_acfwf_installed()
            ) {

                try {

                    $data_store = \WC_Data_Store::load( 'admin-note' );

                    // We already have this note? Then exit, we're done.
                    $note_ids = $data_store->get_notes_with_name( self::NOTE_NAME );
                    if ( ! empty( $note_ids ) ) {
                        return;
                    }

                    $learn_more       = esc_url( WWP_Helper_Functions::get_utm_url( '', 'wwp', 'wcinbox', 'wcinboxacfwflearnmorebutton', 'https://advancedcouponsplugin.com' ) );
                    $install_acfw_url = admin_url() . self::AJAX_INSTALL_URL;

                    $note_content = __(
                        'This free plugin extends your coupon features. Market your store better with WooCommerce coupons. Install the free plugin now.',
                        'woocommerce-wholesale-prices'
                    );

                    $note = WWP_Helper_Functions::wc_admin_note_instance();
                    $note->set_title( __( 'Install Advanced Coupons (FREE PLUGIN)', 'woocommerce-wholesale-prices' ) );
                    $note->set_content( $note_content );
                    $note->set_content_data( (object) array() );
                    $note->set_type( $note::E_WC_ADMIN_NOTE_INFORMATIONAL );
                    $note->set_name( self::NOTE_NAME );
                    $note->set_source( 'woocommerce-admin' );
                    $note->add_action( 'install-acfw', __( 'Install Now', 'woocommerce-wholesale-prices' ), $install_acfw_url, $note::E_WC_ADMIN_NOTE_ACTIONED, true );
                    $note->add_action( 'learn-about-acfw', __( 'Learn more', 'woocommerce-wholesale-prices' ), $learn_more, $note::E_WC_ADMIN_NOTE_UNACTIONED, false );
                    $note->save();

                } catch ( Exception $e ) {
                    return;
                }
            }
        }

        /**
         * Dismisses the note.
         * Conditions:     - If notice is dismissed.
         *                 - If user is not admin.
         *                 - If ACFWF is installed.
         *
         * Note: Added a condition to show the note again if WWP_SHOW_INSTALL_ACFWF_NOTICE equal to yes
         *
         * @since 1.11.5
         * @access public
         */
        public function dismiss_install_acfwf_note() {

            // If WC Admin is not active then don't proceed.
            if ( ! WWP_Helper_Functions::is_wc_admin_active() ) {
                return;
            }

            // If not login return.
            if ( ! is_user_logged_in() ) {
                return;
            }

            $wc_data = WWP_Helper_Functions::get_woocommerce_data();

            if (
                version_compare( $wc_data['Version'], '4.3.0', '>=' ) ||
                '4.3.0-beta.1' === $wc_data['Version'] ||
                '4.3.0-rc.1' === $wc_data['Version']
            ) {

                global $wpdb;

                $note_name = self::NOTE_NAME;
                $row       = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}wc_admin_notes
                            WHERE name = %s",
                        $note_name
                    ),
                    ARRAY_A
                );

                // Check if column layout doesn't exist in wc_admin_notes then don't proceed.
                if ( ! isset( $row['layout'] ) && empty( $row['layout'] ) ) {
                    return;
                }
            }

            try {

                $data_store = \WC_Data_Store::load( 'admin-note' );
                $note_ids   = $data_store->get_notes_with_name( self::NOTE_NAME );

                if ( ! empty( $note_ids ) ) {

                    $note_id   = current( $note_ids );
                    $note      = WWP_Helper_Functions::wc_admin_note_instance( $note_id );
                    $user_data = get_userdata( get_current_user_id() );

                    if (
                        ! in_array( 'administrator', $user_data->roles, true ) ||
                        get_option( WWP_SHOW_INSTALL_ACFWF_NOTICE ) === 'no' ||
                        WWP_Helper_Functions::is_acfwf_installed()
                    ) {
                        $note->set_status( $note::E_WC_ADMIN_NOTE_ACTIONED );
                        $note->save();
                    }
                }
            } catch ( Exception $e ) {
                return;
            }
        }

        /**
         * When "Install Now" button is clicked, then set flag that dismiss the note and notice.
         *
         * @since 1.11.5
         * @access public
         */
        public function dismiss_note_on_click() {
            update_option( WWP_SHOW_INSTALL_ACFWF_NOTICE, 'no', 'no' );
        }

        /**
         * Register AJAX
         *
         * @since 1.11.9
         * @access public
         */
        public function register_ajax_handler() {
            add_action( 'wp_ajax_admin_note_install_acfwf', array( $this, 'ajax_redirect_install_acfwf_plugin' ) );
        }

        /**
         * Handles installing ACFWF
         *
         * @since 1.11.9
         * @access public
         */
        public function ajax_redirect_install_acfwf_plugin() {

            $type = isset( $_REQUEST['type'] ) ? sanitize_key( wp_unslash( $_REQUEST['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if (
                current_user_can( 'install_plugins' ) &&
                ! WWP_Helper_Functions::is_acfwf_installed() &&
                'install' === $type
            ) {
                $url = htmlspecialchars_decode( wp_nonce_url( admin_url() . 'update.php?action=install-plugin&plugin=advanced-coupons-for-woocommerce-free', 'install-plugin_advanced-coupons-for-woocommerce-free' ) );
            } else {
                $url = admin_url();
            }

            wp_safe_redirect( $url );
            exit;
        }

        /**
         * Replace "Install Now" url with the ajax url install
         *
         * @since 1.11.9
         * @access public
         */
        public function replace_install_now_url() {

            global $wc_wholesale_prices;

            try {

                $wc_data = WWP_Helper_Functions::get_woocommerce_data();

                if ( version_compare( $wc_data['Version'], '4.3.0', '<=' ) ) {

                    global $wpdb;

                    $note_name        = self::NOTE_NAME;
                    $install_acfw_url = admin_url() . self::AJAX_INSTALL_URL;

                    $row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT note_id FROM {$wpdb->prefix}wc_admin_notes
                                WHERE name = %s",
                            $note_name
                        )
                    );

                    if ( $row ) {

                        $button = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT query FROM {$wpdb->prefix}wc_admin_note_actions
                                    WHERE note_id = %d
                                        AND name = 'install-acfw'",
                                $row->note_id
                            )
                        );

                        if ( $button && $button->query !== $install_acfw_url ) {

                            $wpdb->query(
                                $wpdb->prepare(
                                    "UPDATE {$wpdb->prefix}wc_admin_note_actions
                                        SET query = %s
                                        WHERE note_id = %d",
                                    $install_acfw_url,
                                    $row->note_id
                                )
                            );

                        }
                    }
                }
            } catch ( Exception $e ) {
                return;
            }
        }
    }

    return new WWP_Install_ACFW();
}
