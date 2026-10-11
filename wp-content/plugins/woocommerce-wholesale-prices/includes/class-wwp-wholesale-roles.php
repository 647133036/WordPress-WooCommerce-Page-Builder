<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic of wholesale roles.
 *
 * @since 1.0.0
 * @since 2.2.9 Harden registered wholesale roles option reads against over-serialized values.
 */
class WWP_Wholesale_Roles {

    /**
     * Property that holds the single main instance of WWP_Wholesale_Roles.
     *
     * @since 1.0.0
     * @access private
     * @var WWP_Wholesale_Roles
     */
    private static $_instance;

    /**
     * WWP_Wholesale_Roles constructor.
     *
     * @since 2.1.7
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWP_Wholesale_Roles model.
     */
    public function __construct( $dependencies = array() ) {
        // Nothing to see here yet.
    }

    /**
     * Ensure that only one instance of WWP_Wholesale_Roles is loaded or can be loaded (Singleton Pattern).
     * *NOTE: Will be remove on future versions and update to instance() method.
     *
     * @since 1.0.0
     * @access public
     *
     * @return WWP_Wholesale_Roles
     */
    public static function getInstance() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Add custom user role.
     *
     * @since 1.0.0
     * @since 2.2.9 Fall back to a minimal capability set when the WooCommerce 'customer' role is
     *              unavailable (e.g. multisite sub-site network activation) so the role is always created.
     *
     * @param string $roleKey  Role key.
     * @param string $roleName Role name.
     */
    public function addCustomRole( $roleKey, $roleName ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        global $wp_roles;
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        }

        $customerRole = $wp_roles->get_role( 'customer' ); // Copy customer role capabilities.

        /*
         * Fall back to a minimal capability set when the WooCommerce 'customer' role is not
         * registered yet. This happens during multisite network activation on a fresh sub-site
         * where WooCommerce has not set up its roles for that site; the previous
         * `if ( $customerRole )` guard skipped role creation entirely, so the subsequent
         * addCustomCapability() call then fataled with add_cap() on a null role.
         */
        $capabilities = $customerRole ? $customerRole->capabilities : array( 'read' => true );

        do_action( 'wwp_action_before_add_custom_role', $roleKey, $roleName, $capabilities );

        add_role( $roleKey, $roleName, $capabilities );

        do_action( 'wwp_action_after_add_custom_role', $roleKey, $roleName, $capabilities );
    }

    /**
     * Add custom capability to a given user role.
     *
     * @since 1.0.0
     * @since 2.2.9 Guard against a missing role to avoid a fatal `add_cap()` on null during
     *              multisite network activation.
     *
     * @param string $roleKey Role key.
     * @param string $cap     Capability.
     */
    public function addCustomCapability( $roleKey, $cap ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        do_action( 'wwp_action_before_add_custom_cap', $roleKey, $cap );

        $role = get_role( $roleKey );

        // Guard against a missing role so activation does not fatal with add_cap() on null
        // (e.g. the role could not be created because the WooCommerce 'customer' role was
        // unavailable when addCustomRole() ran on a fresh multisite sub-site).
        if ( $role instanceof WP_Role ) {
            $role->add_cap( $cap );
        }

        do_action( 'wwp_action_after_add_custom_cap', $roleKey, $cap );
    }

    /**
     * Remove custom user role.
     *
     * @since 1.0.0
     *
     * @param string $roleKey Role key.
     */
    public function removeCustomRole( $roleKey ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        do_action( 'wwp_action_before_remove_custom_role', $roleKey );

        remove_role( $roleKey );

        do_action( 'wwp_action_after_remove_custom_role', $roleKey );
    }

    /**
     * Remove custom user capability to a given role.
     *
     * @since 1.0.0
     *
     * @param string $roleKey Role key.
     * @param string $cap     Capability.
     */
    public function removeCustomCapability( $roleKey, $cap ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        do_action( 'wwp_action_before_remove_custom_cap', $roleKey, $cap );

        $role = get_role( $roleKey );

        if ( $role instanceof WP_Role ) {
            $role->remove_cap( $cap );
        }

        do_action( 'wwp_action_after_remove_custom_cap', $roleKey, $cap );
    }

    /**
     * Register a custom role to the plugin custom role options, the plugin custom role options is used to track
     * all custom roles added via this plugin.
     *
     * @since 1.0.0
     * @since 2.2.9 Read the option through the over-serialization tolerant reader.
     *
     * @param string $roleKey  Role key.
     * @param string $roleName Role name.
     * @param array  $attr     Role attributes.
     */
    public function registerCustomRole( $roleKey, $roleName, $attr ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        do_action( 'wwp_action_before_register_custom_role', $roleKey, $roleName );

        $registeredCustomRoles = $this->get_registered_custom_roles_option();
        $registeredCustomRoles = array_filter( $registeredCustomRoles );

        $newRole = array( 'roleName' => $roleName );
        foreach ( $attr as $attKey => $attVal ) {
            $newRole[ $attKey ] = $attVal;
        }

        $newRole = apply_filters( 'wwp_filter_new_role', $newRole, $roleKey );

        if ( isset( $registeredCustomRoles[ $roleKey ] ) ) {
            $registeredCustomRoles[ $roleKey ] = $newRole;
        } else {
            $newRegisteredCustomRoles = array( $roleKey => $newRole );
            $registeredCustomRoles    = array_merge( $registeredCustomRoles, $newRegisteredCustomRoles );
        }

        update_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, maybe_serialize( $registeredCustomRoles ), 'no' );

        do_action( 'wwp_action_after_register_custom_role', $roleKey, $roleName );
    }

    /**
     * Unregister a custom role from the plugin custom role options, the plugin custom role options is used to track
     * all custom roles added via this plugin.
     *
     * @since 1.0.0
     * @since 2.2.9 Read the option through the over-serialization tolerant reader.
     *
     * @param string $roleKey  Role key.
     */
    public function unregisterCustomRole( $roleKey ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        do_action( 'wwp_action_before_unregister_custom_role', $roleKey );

        $registeredCustomRoles = $this->get_registered_custom_roles_option();

        if ( is_array( $registeredCustomRoles ) && array_key_exists( $roleKey, $registeredCustomRoles ) ) {
            unset( $registeredCustomRoles[ $roleKey ] );
        }

        update_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, maybe_serialize( $registeredCustomRoles ), 'no' );

        do_action( 'wwp_action_after_unregister_custom_role', $roleKey );
    }

    /**
     * Return all registered custom role from the plugin custom role options, the plugin custom role options is used to
     * track all custom roles added via this plugin.
     *
     * @since 1.0.0
     * @since 1.3.0 Refactor codebase. Make sure we return an array.
     * @since 2.2.9 Read the option through the over-serialization tolerant reader.
     * @access public
     *
     * @return array
     */
    public function getAllRegisteredWholesaleRoles() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $all_registered_wholesale_roles = $this->get_registered_custom_roles_option();

        return apply_filters( 'wwp_registered_wholesale_roles', $all_registered_wholesale_roles );
    }

    /**
     * Read the registered wholesale roles option, tolerating over-serialized values.
     *
     * The option is intentionally stored double-serialized: writers pre-serialize the
     * roles array and update_option() serializes it once more, so a healthy read needs
     * two unserialize passes (get_option() performs one, this method one more). A faulty
     * re-save elsewhere can leave the value triple-serialized, which the normal read
     * cannot resolve — it yields a string, the roles resolve to none, and wholesale
     * pricing disappears across the store. This method unwraps every serialization layer
     * until it resolves the array, and when it detects an over-serialized value it
     * re-saves the option in its canonical form so other readers of the option recover.
     * That repair is a one-time write performed during a read: it is skipped for healthy
     * values and stops recurring once the stored value has been normalized.
     *
     * @since 2.2.9
     * @access private
     *
     * @return array Registered wholesale roles, or an empty array when none are stored.
     */
    private function get_registered_custom_roles_option() {
        $value  = get_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES );
        $layers = 0;

        // Unwrap every serialization layer; stop when a pass no longer changes the
        // value so malformed data cannot loop indefinitely.
        while ( is_string( $value ) && is_serialized( $value ) ) {
            $unserialized = maybe_unserialize( $value );
            if ( $unserialized === $value ) {
                break;
            }
            $value = $unserialized;
            ++$layers;
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        // get_option() already performs one unserialize pass, so a correctly stored
        // (double-serialized) value resolves in exactly one unwrap here. More unwraps
        // mean the stored value was over-serialized, so repair it to the canonical form
        // so the option's other direct readers recover as well. The autoload argument is
        // omitted so this read-path repair only corrects the serialized value and leaves
        // the option's existing autoload setting untouched.
        $expected_unwrap_layers = 1;
        if ( $layers > $expected_unwrap_layers ) {
            update_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, maybe_serialize( $value ) );
        }

        return $value;
    }

    /**
     * Return all WordPress registered user roles.
     *
     * @since 1.0.0
     *
     * @return mixed
     */
    public function getAllRoles() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        global $wp_roles;

        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        }

        return $wp_roles->get_names();
    }

    /**
     * Return all roles for the current user
     *
     * @since 1.0.0
     * @since 1.2.10 Make sure it returns an array.
     * @since 1.6.0 Add support for passing $user object.
     *
     * @param WP_User|null $user WP_User object, null by default.
     * @return mixed
     */
    public function getUserRoles( $user = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        if ( ! function_exists( 'wp_get_current_user' ) ) {
            include ABSPATH . 'wp-includes/pluggable.php';
        }

        $current_user = is_null( $user ) ? wp_get_current_user() : $user;

        return $current_user->ID && is_array( $current_user->roles ) ? $current_user->roles : array();
    }

    /**
     * Return current user's wholesale role, array is empty if none.
     * We return an array coz we do not close the possibility of allowing a user having multiple wholesale roles in the future.
     * But for the mean time, tho it returns an array, it will only return an array with single element, and that element is the user's current wholesale role.
     *
     * @since 1.0.0
     * @since 1.2.10 Re-index the returned array of wholesale roles.
     * @since 1.6.0 Add support for passing $user object.
     * @since 1.6.4 Add 'wwp_user_wholesale_role' filter to allow 3rd party codes to alter the returned current user's wholesale role.
     * Note that since version 1.0.0 of this plugin, this function returns an array with only 1 element, and that one element is considered
     * as the user's current wholesale role. That is why in various places in this and wwpp's code base we are using [0] to get the first element of the array
     * the array value that was returned by this function, as use it as the current user's wholesale role.
     * As of this version, we do not support multiple wholesale role for one user. The doors are not closed however that why we set this to return an array.
     *
     * @param WP_User|null $user WP_User object, null by default.
     * @return array Array of user wholesale roles.
     */
    public function getUserWholesaleRole( $user = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $wholesaleRoleKeys = array();

        foreach ( $this->getAllRegisteredWholesaleRoles() as $roleKey => $roleName ) {
            $wholesaleRoleKeys[] = $roleKey;
        }

        if ( ! function_exists( 'wp_get_current_user' ) ) {
            include ABSPATH . 'wp-includes/pluggable.php';
        }

        $user = is_null( $user ) ? wp_get_current_user() : $user;

        return apply_filters( 'wwp_user_wholesale_role', array_values( array_intersect( $this->getUserRoles( $user ), $wholesaleRoleKeys ) ), $user );
    }

    /**
     * Add current user wholesale role, if any, on body tag classes.
     *
     * @since 1.1.2
     * @since 1.4.0 Refactor codebase.
     * @access public
     *
     * @param array $classes Array of classes for the body tag.
     * @return array Filtered array of classes for the body tag.
     */
    public function add_wholesale_role_to_body_class( $classes ) {
        $wholesale_role = $this->getUserWholesaleRole();

        if ( is_array( $wholesale_role ) && ! empty( $wholesale_role ) ) {
            foreach ( $wholesale_role as $role ) {
                $classes[] = $role;
            }
        }

        return $classes;
    }

    /**
     * Only return wholesale customer role only if WWP is active and WWPP is deactivated.
     *
     * @since 1.11
     * @access public
     *
     * @param array $wholesale_roles Array of wholesale roles.
     * @return array
     */
    public function filter_registered_wholesale_roles( $wholesale_roles ) {
        if ( ! WWP_Helper_Functions::is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) ) {

            if ( isset( $wholesale_roles['wholesale_customer'] ) ) {
                return array( 'wholesale_customer' => $wholesale_roles['wholesale_customer'] );
            }
        }

        return $wholesale_roles;
    }

    /**
     * Integration of WC Navigation Bar.
     *
     * @since 1.11.3
     * @access public
     */
    public function wc_navigation_bar() {
        if ( function_exists( 'wc_admin_connect_page' ) ) {
            wc_admin_connect_page(
                array(
                    'id'        => 'wholesale-roles-page',
                    'screen_id' => 'wholesale_page_wwpp-wholesale-roles-page',
                    'title'     => __( 'Wholesale Roles', 'woocommerce-wholesale-prices' ),
                )
            );
        }
    }

    /**
     * Get wholesale user ids.
     *
     * @since 2.0
     * @access public
     */
    public function get_all_wholesale_user_ids() {
        global $wpdb;

        $wholesale_roles                = array();
        $all_registered_wholesale_roles = $this->getAllRegisteredWholesaleRoles();

        $placeholders = array();
        $values       = array();

        foreach ( $all_registered_wholesale_roles as $role_key => $role_data ) {
            $placeholders[] = 'meta_value LIKE %s';
            $values[]       = '%' . $wpdb->esc_like( $role_key ) . '%';
        }

        $query = "SELECT ID FROM {$wpdb->users}
            WHERE ID IN (
                SELECT user_id FROM {$wpdb->usermeta}
                WHERE meta_key = %s
                AND (" . implode( ' OR ', $placeholders ) . ')
            )';

        array_unshift( $values, $wpdb->prefix . 'capabilities' );
        $query_result = $wpdb->get_results( $wpdb->prepare( $query, $values ), ARRAY_A ); //phpcs:ignore.

        $wholesale_user_ids = array();

        foreach ( $query_result as $result ) {
            $wholesale_user_ids[] = $result['ID'];
        }

        return $wholesale_user_ids;
    }

    /**
     * Execute model.
     *
     * @since 1.4.0
     * @access public
     */
    public function run() {
        add_filter( 'body_class', array( $this, 'add_wholesale_role_to_body_class' ), 10, 1 );
        add_filter( 'wwp_registered_wholesale_roles', array( $this, 'filter_registered_wholesale_roles' ), 10, 1 );
        add_action( 'init', array( $this, 'wc_navigation_bar' ) );
    }
}
