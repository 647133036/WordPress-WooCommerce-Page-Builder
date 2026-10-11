<?php
/**
 * REST controller for the onboarding checklist: `wwp/v1/onboarding`.
 *
 * GET returns the assembled payload the card renders. POST is the write surface for whole-card
 * dismissal, manual step completion, per-section UI (collapse) state, a section's one-time
 * celebration, and the anonymous-usage (insights) opt-in choice; each write persists (through
 * {@see WWP_Onboarding_State}, or the `wwp_anonymous_data` option for insights) and returns the
 * freshly assembled envelope so the card re-renders from the response.
 *
 * Scaffold for epic #1031; POST persistence implemented for the card UI in #1033. Consumed by: the
 * card UI renderer (#1033) and the WWP-section detectors (#1034).
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1032
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1033
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_REST_Onboarding_V1_Controller' ) ) {

    /**
     * Serves and (eventually) mutates the onboarding checklist payload.
     *
     * @since 2.3.0
     */
    class WWP_REST_Onboarding_V1_Controller extends WC_REST_Controller {

        /**
         * Endpoint namespace.
         *
         * @since 2.3.0
         * @var string
         */
        protected $namespace = 'wwp/v1';

        /**
         * Route base.
         *
         * @since 2.3.0
         * @var string
         */
        protected $rest_base = 'onboarding';

        /**
         * Constructor.
         *
         * @since 2.3.0
         * @access public
         */
        public function __construct() {
            add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        }

        /**
         * Register the onboarding routes.
         *
         * @since 2.3.0
         * @access public
         */
        public function register_routes() {
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'get_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                    ),
                    array(
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( $this, 'update_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                        'args'                => array(
                            'action'    => array(
                                'type'        => 'string',
                                'required'    => true,
                                'enum'        => array( 'dismiss', 'complete', 'ui_state', 'celebrate', 'insights' ),
                                'description' => __( 'Which write to perform.', 'woocommerce-wholesale-prices' ),
                            ),
                            'allow'     => array(
                                'type'        => 'boolean',
                                'required'    => false,
                                'default'     => true,
                                'description' => __( 'For the insights write: true opts in to anonymous usage sharing, false opts out.', 'woocommerce-wholesale-prices' ),
                            ),
                            'plugin'    => array(
                                'type'              => 'string',
                                'required'          => false,
                                'sanitize_callback' => 'sanitize_key',
                                'description'       => __( 'Section identifier (registering plugin slug) for complete/ui_state/celebrate.', 'woocommerce-wholesale-prices' ),
                            ),
                            'step_id'   => array(
                                'type'              => 'string',
                                'required'          => false,
                                'sanitize_callback' => 'sanitize_key',
                                'description'       => __( 'Step identifier within the section, for the complete write.', 'woocommerce-wholesale-prices' ),
                            ),
                            'collapsed' => array(
                                'type'        => 'boolean',
                                'required'    => false,
                                'description' => __( 'Whether the section is collapsed, for the ui_state write. Omit to leave the flag unchanged.', 'woocommerce-wholesale-prices' ),
                            ),
                            'dismissed' => array(
                                'type'        => 'boolean',
                                'required'    => false,
                                'default'     => true,
                                'description' => __( 'Whole-card dismissal flag for the dismiss write; false re-opens it.', 'woocommerce-wholesale-prices' ),
                            ),
                        ),
                    ),
                )
            );
        }

        /**
         * Capability guard shared by every onboarding route.
         *
         * @since 2.3.0
         * @access public
         *
         * @return bool|WP_Error True if allowed, WP_Error otherwise.
         */
        public function permissions_check() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return new WP_Error(
                    'wwp_onboarding_forbidden',
                    __( 'You are not allowed to access the onboarding checklist.', 'woocommerce-wholesale-prices' ),
                    array( 'status' => rest_authorization_required_code() )
                );
            }

            return true;
        }

        /**
         * GET the assembled onboarding payload for the current user.
         *
         * @since 2.3.0
         * @access public
         *
         * @param WP_REST_Request $request Request object.
         * @return WP_REST_Response
         */
        public function get_item( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
            $payload = WWP_Onboarding_Data_Contract::instance()->assemble( get_current_user_id() );

            return rest_ensure_response( $payload );
        }

        /**
         * POST a write (whole-card dismissal, manual step completion, per-section UI state, or a
         * section's one-time celebration), then return the freshly assembled payload.
         *
         * Verifies the nonce, dispatches on `action`, and persists through the state store. The
         * response is the same envelope shape as {@see self::get_item()} so the card can re-render
         * from the write's result without a second request.
         *
         * @since 2.3.0
         * @access public
         *
         * @param WP_REST_Request $request Request object.
         * @return WP_REST_Response|WP_Error
         */
        public function update_item( $request ) {
            $nonce = $request->get_header( 'X-WP-Nonce' );

            // The WP REST API uses 'wp_rest' as the shared nonce action for cookie-auth flows; WP
            // sets the X-WP-Nonce header automatically (via wp_create_nonce( 'wp_rest' )), so no
            // plugin-defined nonce action is needed or wanted here.
            if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return new WP_Error(
                    'wwp_onboarding_invalid_nonce',
                    __( 'Invalid or missing nonce.', 'woocommerce-wholesale-prices' ),
                    array( 'status' => 403 )
                );
            }

            $state  = WWP_Onboarding_State::instance();
            $action = $request->get_param( 'action' );

            switch ( $action ) {
                case 'dismiss':
                    $state->set_dismissed( (bool) $request->get_param( 'dismissed' ) );
                    break;

                case 'ui_state':
                    $plugin = (string) $request->get_param( 'plugin' );

                    if ( '' === $plugin ) {
                        return $this->missing_param_error( 'plugin' );
                    }

                    // Only write when the flag is present, so a later partial ui_state write that
                    // omits `collapsed` leaves the stored value untouched instead of resetting it.
                    if ( null !== $request->get_param( 'collapsed' ) ) {
                        $state->set_section_collapsed( get_current_user_id(), $plugin, (bool) $request->get_param( 'collapsed' ) );
                    }
                    break;

                case 'complete':
                    $result = $this->handle_complete( $request, $state );

                    if ( is_wp_error( $result ) ) {
                        return $result;
                    }
                    break;

                case 'celebrate':
                    $result = $this->handle_celebrate( $request, $state );

                    if ( is_wp_error( $result ) ) {
                        return $result;
                    }
                    break;

                case 'insights':
                    $this->handle_insights( $request );
                    break;
            }

            return rest_ensure_response( WWP_Onboarding_Data_Contract::instance()->assemble( get_current_user_id() ) );
        }

        /**
         * Record a manual step completion, guarding that the step is genuinely user-attested.
         *
         * A step is only completable through this route when it is currently registered and its
         * completion is user-attested (`manual`, or a `copy` action) — the store, not the caller,
         * decides what a user may attest, so an arbitrary or auto-detected step cannot be forced
         * complete.
         *
         * @since 2.3.0
         * @access private
         *
         * @param WP_REST_Request      $request Request object.
         * @param WWP_Onboarding_State $state   State store.
         * @return true|WP_Error
         */
        private function handle_complete( $request, $state ) {
            $plugin  = (string) $request->get_param( 'plugin' );
            $step_id = (string) $request->get_param( 'step_id' );

            if ( '' === $plugin ) {
                return $this->missing_param_error( 'plugin' );
            }

            if ( '' === $step_id ) {
                return $this->missing_param_error( 'step_id' );
            }

            $contract = WWP_Onboarding_Data_Contract::instance();
            $step     = $this->find_step( $contract->get_registered_sections(), $plugin, $step_id );

            if ( null === $step || ! $contract->is_user_attested_step( $step ) ) {
                return new WP_Error(
                    'wwp_onboarding_step_not_completable',
                    __( 'That step cannot be marked complete.', 'woocommerce-wholesale-prices' ),
                    array( 'status' => 400 )
                );
            }

            $state->mark_completed( $contract->step_completion_key( $plugin, $step_id ) );

            return true;
        }

        /**
         * Record a section's one-time celebration, guarding that it has actually reached 100%.
         *
         * @since 2.3.0
         * @access private
         *
         * @param WP_REST_Request      $request Request object.
         * @param WWP_Onboarding_State $state   State store.
         * @return true|WP_Error
         */
        private function handle_celebrate( $request, $state ) {
            $plugin = (string) $request->get_param( 'plugin' );

            if ( '' === $plugin ) {
                return $this->missing_param_error( 'plugin' );
            }

            $contract = WWP_Onboarding_Data_Contract::instance();
            $section  = $this->find_section( $contract->assemble( get_current_user_id() )['sections'], $plugin );

            if ( null === $section || ! $contract->section_is_complete( $section ) ) {
                return new WP_Error(
                    'wwp_onboarding_section_not_complete',
                    __( 'That section has not reached 100% yet.', 'woocommerce-wholesale-prices' ),
                    array( 'status' => 400 )
                );
            }

            $state->mark_celebrated( $plugin );

            return true;
        }

        /**
         * Record the user's anonymous-usage (insights) choice from the card's opt-in row.
         *
         * The row is a second surface for the consent the legacy admin notice and settings page write
         * to `wwp_anonymous_data`: it persists the choice and marks the legacy tracking notice as seen
         * so the user is not asked twice. It does NOT send a check-in itself — opting in simply lifts
         * the {@see WWP_Usage::tracking_allowed()} gate, so the existing weekly `wwp_usage_tracking_cron`
         * sends the payload (the onboarding object included) on its normal schedule. Weeks that ended
         * while the site was opted out are not sent: {@see WWP_Usage::send_checkin()} moves its
         * reported-week marker past them. The route is already guarded by `manage_woocommerce` plus
         * the REST nonce.
         *
         * Note: a site with a paid Wholesale plugin active is treated as opted-in by
         * {@see WWP_Usage::tracking_allowed()} regardless of this value; opting out still records the
         * preference and only suppresses tracking on a free-only site.
         *
         * @since 2.3.0
         * @access private
         *
         * @param WP_REST_Request $request Request object.
         * @return void
         */
        private function handle_insights( $request ) {
            $allow = (bool) $request->get_param( 'allow' );

            update_option( 'wwp_anonymous_data', $allow ? 'yes' : 'no', 'no' );

            // The user has now answered on the card; suppress the legacy admin opt-in notice.
            update_option( 'wwp_tracking_notice', 1, 'no' );
        }

        /**
         * Find a registered step by section and id.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array  $sections Assembled/registered sections.
         * @param string $plugin   Section identifier.
         * @param string $step_id  Step identifier.
         * @return array|null
         */
        private function find_step( $sections, $plugin, $step_id ) {
            $section = $this->find_section( $sections, $plugin );

            if ( null === $section ) {
                return null;
            }

            foreach ( $section['steps'] as $step ) {
                if ( $step['id'] === $step_id ) {
                    return $step;
                }
            }

            return null;
        }

        /**
         * Find a section by its plugin identifier.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array  $sections Assembled/registered sections.
         * @param string $plugin   Section identifier.
         * @return array|null
         */
        private function find_section( $sections, $plugin ) {
            foreach ( $sections as $section ) {
                if ( $section['plugin'] === $plugin ) {
                    return $section;
                }
            }

            return null;
        }

        /**
         * A uniform 400 for a missing required write parameter.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $param Parameter name.
         * @return WP_Error
         */
        private function missing_param_error( $param ) {
            return new WP_Error(
                'wwp_onboarding_missing_param',
                sprintf(
                    /* translators: %s: the missing parameter name. */
                    __( 'Missing required parameter: %s.', 'woocommerce-wholesale-prices' ),
                    $param
                ),
                array( 'status' => 400 )
            );
        }
    }
}
