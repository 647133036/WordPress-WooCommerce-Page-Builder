<?php
/**
 * WooCommerce Wholesale Prices : Wholesale Role Preview.
 *
 * @package WooCommerceWholeSalePrices
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WWP_Wholesale_Role_Preview' ) ) {

    /**
     * Model that lets a store admin preview the storefront as a selected wholesale role.
     *
     * Adds a "Preview as wholesale role" control to the WordPress admin bar. While a
     * preview is active, the previewing admin's wholesale role is overridden on the
     * storefront via the {@see 'wwp_user_wholesale_role'} filter, so all wholesale
     * pricing (including WWPP rule-based pricing, which reads the same role) renders as
     * that role would see it. The override is scoped to the storefront and to the
     * previewing admin, so non-admins and real customers are never affected.
     *
     * @since 2.2.9
     */
    class WWP_Wholesale_Role_Preview {

        /**
         * User meta key storing the wholesale role an admin is currently previewing as.
         *
         * @since 2.2.9
         * @var string
         */
        const PREVIEW_META_KEY = '_wwp_preview_wholesale_role';

        /**
         * Request var carrying the role to preview, or "none" to exit, on a toggle request.
         *
         * @since 2.2.9
         * @var string
         */
        const TOGGLE_QUERY_VAR = 'wwp_preview_role';

        /**
         * Nonce action protecting the preview toggle requests.
         *
         * @since 2.2.9
         * @var string
         */
        const NONCE_ACTION = 'wwp_preview_role_toggle';

        /**
         * Capability required to use the preview control.
         *
         * @since 2.2.9
         * @var string
         */
        const CAPABILITY = 'manage_woocommerce';

        /**
         * Property that holds the single main instance of WWP_Wholesale_Role_Preview.
         *
         * @since 2.2.9
         * @access private
         * @var WWP_Wholesale_Role_Preview
         */
        private static $_instance;

        /**
         * Model that houses all the wholesale roles related functionality.
         *
         * @since 2.2.9
         * @access private
         * @var WWP_Wholesale_Roles
         */
        private $_wwp_wholesale_roles;

        /**
         * WWP_Wholesale_Role_Preview constructor.
         *
         * @since 2.2.9
         * @access public
         * @param array $dependencies Array of instance dependencies.
         */
        public function __construct( $dependencies ) {

            $this->_wwp_wholesale_roles = $dependencies['WWP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWP_Wholesale_Role_Preview is loaded or can be
         * loaded (Singleton Pattern).
         *
         * @since 2.2.9
         * @access public
         * @param array $dependencies Array of instance dependencies.
         * @return WWP_Wholesale_Role_Preview
         */
        public static function instance( $dependencies = array() ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Get the wholesale role the current admin is previewing as.
         *
         * The stored role is re-validated against the currently registered wholesale
         * roles so a preview set against a since-deleted role silently resolves to none.
         *
         * @since 2.2.9
         * @access private
         * @param array|null $registered_roles Optional. The registered wholesale roles, so a
         *                                     caller that already has them avoids a re-fetch.
         *                                     Fetched internally when null.
         * @return string The previewed wholesale role key, or an empty string when no
         *                valid preview is active.
         */
        private function get_active_preview_role( $registered_roles = null ) {

            $role = get_user_meta( get_current_user_id(), self::PREVIEW_META_KEY, true );

            if ( ! is_string( $role ) || '' === $role ) {
                return '';
            }

            if ( null === $registered_roles ) {
                $registered_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
            }

            return is_array( $registered_roles ) && array_key_exists( $role, $registered_roles ) ? $role : '';
        }

        /**
         * Resolve a wholesale role's display name from the registered roles structure.
         *
         * Registered roles are keyed by role key with a value array shaped as
         * `array( 'roleName' => ..., 'desc' => ..., 'main' => ... )`. Falls back to the
         * role key when no display name is present.
         *
         * @since 2.2.9
         * @access private
         * @param string $role_key         The wholesale role key.
         * @param array  $registered_roles The registered wholesale roles structure.
         * @return string The role display name.
         */
        private function get_role_display_name( $role_key, $registered_roles ) {

            if ( isset( $registered_roles[ $role_key ]['roleName'] ) && '' !== $registered_roles[ $role_key ]['roleName'] ) {
                return $registered_roles[ $role_key ]['roleName'];
            }

            return $role_key;
        }

        /**
         * Handle a preview toggle request.
         *
         * Validates capability and nonce, persists the chosen wholesale role (or clears
         * the preview when "none"), then redirects back to the originating page with the
         * toggle request vars stripped so a refresh does not re-trigger the toggle.
         *
         * @since 2.2.9
         * @access public
         */
        public function maybe_handle_preview_toggle() {

            if ( ! isset( $_GET[ self::TOGGLE_QUERY_VAR ] ) ) {
                return;
            }

            if ( ! current_user_can( self::CAPABILITY ) ) {
                return;
            }

            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

            if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
                return;
            }

            $role    = sanitize_text_field( wp_unslash( $_GET[ self::TOGGLE_QUERY_VAR ] ) );
            $user_id = get_current_user_id();

            if ( 'none' === $role || '' === $role ) {
                delete_user_meta( $user_id, self::PREVIEW_META_KEY );
            } else {
                $registered_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

                if ( is_array( $registered_roles ) && array_key_exists( $role, $registered_roles ) ) {
                    update_user_meta( $user_id, self::PREVIEW_META_KEY, $role );
                }
            }

            wp_safe_redirect( esc_url_raw( remove_query_arg( array( self::TOGGLE_QUERY_VAR, '_wpnonce' ) ) ) );
            exit;
        }

        /**
         * Override the previewing admin's wholesale role on the storefront.
         *
         * Leaves the computed value untouched for admin-screen requests, for users other
         * than the current viewer, and for viewers without the required capability, so
         * real customers and non-admins are never affected. Front-end AJAX requests
         * (e.g. WooCommerce's legacy admin-ajax variation-price and add-to-cart calls,
         * for which `is_admin()` is true) are deliberately NOT excluded, so the previewed
         * pricing stays consistent across those flows.
         *
         * @since 2.2.9
         * @access public
         * @param array   $wholesale_roles The wholesale roles computed for $user.
         * @param WP_User $user            The user the roles were computed for.
         * @return array The wholesale roles to use.
         */
        public function filter_user_wholesale_role( $wholesale_roles, $user ) {

            if ( is_admin() && ! wp_doing_ajax() ) {
                return $wholesale_roles;
            }

            if ( ! ( $user instanceof WP_User ) || 0 === (int) $user->ID || get_current_user_id() !== (int) $user->ID ) {
                return $wholesale_roles;
            }

            if ( ! current_user_can( self::CAPABILITY ) ) {
                return $wholesale_roles;
            }

            $preview_role = $this->get_active_preview_role();

            return '' === $preview_role ? $wholesale_roles : array( $preview_role );
        }

        /**
         * Add the "Preview as wholesale role" control to the admin bar.
         *
         * Renders a parent node (reflecting the active preview, if any), one child node
         * per registered wholesale role, and an "Exit preview" child while a preview is
         * active. Each child is a nonce-protected link handled by
         * {@see WWP_Wholesale_Role_Preview::maybe_handle_preview_toggle()}.
         *
         * @since 2.2.9
         * @access public
         * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
         */
        public function add_preview_toggle_to_admin_bar( $wp_admin_bar ) {

            if ( ! current_user_can( self::CAPABILITY ) ) {
                return;
            }

            $registered_roles = $this->_wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( ! is_array( $registered_roles ) || empty( $registered_roles ) ) {
                return;
            }

            $active_role = $this->get_active_preview_role( $registered_roles );
            $nonce       = wp_create_nonce( self::NONCE_ACTION );

            if ( '' !== $active_role ) {
                /* translators: %s: wholesale role name currently being previewed. */
                $parent_title = sprintf( __( 'Previewing as: %s', 'woocommerce-wholesale-prices' ), $this->get_role_display_name( $active_role, $registered_roles ) );
            } else {
                $parent_title = __( 'Preview as wholesale role', 'woocommerce-wholesale-prices' );
            }

            $wp_admin_bar->add_node(
                array(
                    'id'    => 'wwp-preview-role',
                    'title' => esc_html( $parent_title ),
                    'meta'  => array( 'class' => '' !== $active_role ? 'wwp-preview-role-active' : '' ),
                )
            );

            foreach ( array_keys( $registered_roles ) as $role_key ) {

                $role_name = $this->get_role_display_name( $role_key, $registered_roles );

                if ( $role_key === $active_role ) {
                    /* translators: %s: wholesale role name. */
                    $child_title = sprintf( __( '%s (previewing)', 'woocommerce-wholesale-prices' ), $role_name );
                } else {
                    $child_title = $role_name;
                }

                $wp_admin_bar->add_node(
                    array(
                        'parent' => 'wwp-preview-role',
                        'id'     => 'wwp-preview-role-' . $role_key,
                        'title'  => esc_html( $child_title ),
                        'href'   => esc_url_raw(
                            add_query_arg(
                                array(
                                    self::TOGGLE_QUERY_VAR => rawurlencode( $role_key ),
                                    '_wpnonce'             => $nonce,
                                )
                            )
                        ),
                    )
                );
            }

            if ( '' !== $active_role ) {
                $wp_admin_bar->add_node(
                    array(
                        'parent' => 'wwp-preview-role',
                        'id'     => 'wwp-preview-role-exit',
                        'title'  => esc_html__( 'Exit preview', 'woocommerce-wholesale-prices' ),
                        'href'   => esc_url_raw(
                            add_query_arg(
                                array(
                                    self::TOGGLE_QUERY_VAR => 'none',
                                    '_wpnonce'             => $nonce,
                                )
                            )
                        ),
                    )
                );
            }
        }

        /**
         * Execute model.
         *
         * @since 2.2.9
         * @access public
         */
        public function run() {

            add_action( 'init', array( $this, 'maybe_handle_preview_toggle' ) );
            add_action( 'admin_bar_menu', array( $this, 'add_preview_toggle_to_admin_bar' ), 100 );
            add_filter( 'wwp_user_wholesale_role', array( $this, 'filter_user_wholesale_role' ), 10, 2 );
        }
    }

}
