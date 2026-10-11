<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_REST_API' ) ) {

    /**
     * Model that houses the logic of WWPP API.
     *
     * @since 1.12
     * @since 2.2.9 Resolve the wholesale role from the authenticated user for wholesale/v1 reads.
     */
    class WWP_REST_API {

        /**
         * Class Properties.
         */

        /**
         * Property that holds the single main instance of WWP_REST_API.
         *
         * @since 1.12.0
         * @access private
         * @var WWP_REST_API
         */
        private static $_instance;

        /**
         * Property that holds WWP API Products Controllers.
         *
         * @since 1.12.0
         * @access public
         * @var WWP_REST_API_Wholesale_Products_Controller
         */
        public $wwp_rest_api_wholesale_products_controller;

        /**
         * Property that holds WWP API Variations Controllers.
         *
         * @since 1.12.0
         * @access public
         * @var WWP_REST_API_Wholesale_Variations_Controller
         */
        public $wwp_rest_api_wholesale_variations_controller;

        /**
         * Property that holds WWP API Wholesale Roles Controllers.
         *
         * @since 1.12.0
         * @access public
         * @var WWP_REST_API_Wholesale_Roles_Controller
         */
        public $wwp_rest_api_wholesale_roles_controller;

        /**
         * Class Methods.
         */

        /**
         * WWP_REST_API constructor.
         *
         * @since 1.12.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_REST_API model.
         */
        public function __construct( $dependencies = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
            add_action( 'woocommerce_loaded', array( $this, 'load_wwp_api' ), 10 );
        }

        /**
         * Load WWP API.
         *
         * @since 1.16.0
         * @since 2.2.9 Pin the request wholesale_role to the authenticated user's real role for wholesale/v1 reads.
         * @access public
         */
        public function load_wwp_api() {
            // API controllers using wholesale/v1 namespace.
            add_action( 'rest_api_init', array( $this, 'load_api_wwp_controllers' ), 5 );

            // Authenticate users if api keys are provided.
            add_action( 'woocommerce_rest_is_request_to_rest_api', array( $this, 'authenticate_user' ) );

            // Resolve the wholesale role from the authenticated user, ignoring a tampered request param.
            add_filter( 'rest_pre_dispatch', array( $this, 'resolve_wholesale_role_from_authenticated_user' ), 10, 3 );
        }

        /**
         * Ensure that only one instance of WWP_REST_API is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.12.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_REST_API model.
         * @return WWP_REST_API
         */
        public static function instance( $dependencies = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * WWPP API controllers under wwpp/v1 namespace.
         *
         * @since 1.12
         * @access public
         */
        public function load_api_wwp_controllers() {
            require_once WWP_INCLUDES_PATH . 'api/v1/class-wwp-rest-api-wholesale-products-v1-controller.php';
            require_once WWP_INCLUDES_PATH . 'api/v1/class-wwp-rest-api-wholesale-products-variations-v1-controller.php';
            require_once WWP_INCLUDES_PATH . 'api/v1/class-wwp-rest-api-wholesale-roles-v1-controller.php';

            $this->wwp_rest_api_wholesale_products_controller   = new WWP_REST_Wholesale_Products_V1_Controller();
            $this->wwp_rest_api_wholesale_variations_controller = new WWP_REST_Wholesale_Product_Variations_V1_Controller();
            $this->wwp_rest_api_wholesale_roles_controller      = new WWP_REST_Wholesale_Roles_V1_Controller();
        }

        /**
         * Authenticate if user if using WWPP rest base if api keys are provided.
         *
         * @since 1.12
         * @access public
         *
         * @param bool $rest_request Whether the request is a REST API request.
         * @return boolean
         */
        public function authenticate_user( $rest_request ) {
            $rest_prefix = trailingslashit( rest_get_url_prefix() );
            $request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

            // Authenticate if the request is using the wholesale/v1 endpoint.
            if ( str_contains( $request_uri, $rest_prefix . 'wholesale/' ) ) {
                return true;
            }

            return $rest_request;
        }

        /**
         * Pin a mismatched wholesale_role request param to the authenticated user's real role.
         *
         * On wholesale/v1 read requests the wholesale role is the source of truth for both
         * authorization and which per-role prices/variations are returned. When the request
         * carries a wholesale_role that does not match the authenticated wholesale user's
         * actual role, the server overrides it with the user's real role so a tampered or
         * mismatched param can neither trigger a 403 for a legitimate wholesale user nor
         * expose another role's data. Requests without a wholesale_role param, and users
         * who have no wholesale role (guests, admins, shop managers, plain customers), are
         * left untouched so their existing capability-based authorization is preserved.
         *
         * Hooked on rest_pre_dispatch so the correction lands on the shared WP_REST_Request
         * before the route's permission callback and query run.
         *
         * @since 2.2.9
         * @access public
         *
         * @param mixed           $result  Response to replace the requested version with, or null to continue.
         * @param WP_REST_Server  $server  Server instance.
         * @param WP_REST_Request $request Request used to generate the response.
         * @return mixed The unmodified $result.
         */
        public function resolve_wholesale_role_from_authenticated_user( $result, $server, $request ) {
            // Never interfere when another handler already short-circuited the dispatch.
            if ( is_wp_error( $result ) || ! ( $request instanceof WP_REST_Request ) ) {
                return $result;
            }

            // Only wholesale/v1 read requests resolve a role.
            if ( 'GET' !== $request->get_method() || ! str_contains( $request->get_route(), 'wholesale/v1' ) ) {
                return $result;
            }

            $supplied_role = $request->get_param( 'wholesale_role' );

            // Only act when the client supplied a wholesale_role. An absent param keeps the
            // request untouched so the no-role authorization path is unchanged.
            if ( ! is_string( $supplied_role ) || '' === $supplied_role ) {
                return $result;
            }

            global $wc_wholesale_prices;

            if ( ! is_object( $wc_wholesale_prices ) || ! isset( $wc_wholesale_prices->wwp_wholesale_roles ) ) {
                return $result;
            }

            $user_roles = array_values( array_filter( (array) $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole() ) );

            // Non-wholesale users (guests, admins, shop managers, plain customers) authorize via
            // their capabilities, so leave their request param as-is.
            if ( empty( $user_roles ) ) {
                return $result;
            }

            // Server is the source of truth: when the supplied role is not one the user actually
            // holds, replace it with the user's real role (the first, as only one is supported).
            // A param that already matches one of the user's roles is left untouched.
            if ( ! in_array( sanitize_text_field( $supplied_role ), $user_roles, true ) ) {
                $request->set_param( 'wholesale_role', $user_roles[0] );
            }

            return $result;
        }
    }

}
