<?php
/**
 * Onboarding checklist module.
 *
 * The backbone the WWS gamified onboarding checklist is built on. This module is pure shared
 * infrastructure: it loads the state store and the data contract (both plain services other parts
 * of the suite consume directly) and registers the `wwp/v1/onboarding` REST controller. The card UI
 * (#1033) lives in its own sub-issue; the behavior-triggered activation and completion emails (#1035)
 * are wired here through {@see WWP_Onboarding_Emails}, and the weekly telemetry snapshot (#1036)
 * through {@see WWP_Onboarding_Telemetry}.
 *
 * Scaffold for epic #1031.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1032
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once WWP_INCLUDES_PATH . 'onboarding/class-wwp-onboarding-state.php';
require_once WWP_INCLUDES_PATH . 'onboarding/class-wwp-onboarding-data-contract.php';
require_once WWP_INCLUDES_PATH . 'onboarding/class-wwp-onboarding-wwp-section.php';
require_once WWP_INCLUDES_PATH . 'onboarding/class-wwp-onboarding-telemetry.php';
require_once WWP_INCLUDES_PATH . 'onboarding/emails/class-wwp-onboarding-emails.php';

if ( ! class_exists( 'WWP_Onboarding' ) ) {

    /**
     * Wires the onboarding backbone into WordPress.
     *
     * @since 2.3.0
     */
    class WWP_Onboarding {

        /**
         * Single main instance of WWP_Onboarding.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Onboarding|null
         */
        private static $_instance = null;

        /**
         * The onboarding state store.
         *
         * @since 2.3.0
         * @access public
         * @var WWP_Onboarding_State
         */
        public $state;

        /**
         * The onboarding data contract.
         *
         * @since 2.3.0
         * @access public
         * @var WWP_Onboarding_Data_Contract
         */
        public $data_contract;

        /**
         * WWP's own onboarding section provider.
         *
         * @since 2.3.0
         * @access public
         * @var WWP_Onboarding_WWP_Section
         */
        public $wwp_section;

        /**
         * The behavior-triggered onboarding emails coordinator.
         *
         * @since 2.3.0
         * @access public
         * @var WWP_Onboarding_Emails
         */
        public $emails;

        /**
         * The onboarding telemetry contributor.
         *
         * @since 2.3.0
         * @access public
         * @var WWP_Onboarding_Telemetry
         */
        public $telemetry;

        /**
         * The onboarding REST controller (available after rest_api_init).
         *
         * @since 2.3.0
         * @access public
         * @var WWP_REST_Onboarding_V1_Controller|null
         */
        public $rest_controller = null;

        /**
         * Constructor.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $dependencies Injected dependencies. Unused; kept for wiring consistency.
         */
        public function __construct( $dependencies = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
            $this->state         = WWP_Onboarding_State::instance();
            $this->data_contract = WWP_Onboarding_Data_Contract::instance();
            $this->wwp_section   = WWP_Onboarding_WWP_Section::instance();
            $this->telemetry     = WWP_Onboarding_Telemetry::instance();
            $this->emails        = WWP_Onboarding_Emails::instance();
        }

        /**
         * Ensure only one instance is loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $dependencies Injected dependencies.
         * @return WWP_Onboarding
         */
        public static function instance( $dependencies = array() ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Register hooks.
         *
         * @since 2.3.0
         * @access public
         */
        public function run() {
            // Register WWP's own section against the shared data contract.
            $this->wwp_section->run();

            // Record onboarding transition timestamps for the weekly telemetry snapshot.
            $this->telemetry->run();

            // Register and drive the behavior-triggered activation/completion emails.
            $this->emails->run();

            // Load the controller during rest_api_init (priority 5) so WC_REST_Controller is
            // available; the controller then hooks register_routes at the default priority.
            add_action( 'rest_api_init', array( $this, 'register_rest_controller' ), 5 );

            // Register the "Things to do next" task on init (priority 20) so WooCommerce has already
            // built its default extended task list by the time we add to it.
            add_action( 'init', array( $this, 'register_wc_task' ), 20 );
        }

        /**
         * Register the Wholesale Prices entry in WooCommerce's "Things to do next" task list.
         *
         * No-op on WooCommerce versions that predate the OnboardingTasks feature (WWP supports
         * WooCommerce down to 4.0), and a quiet bail if the extended list is not registered, so an
         * unexpected WooCommerce build can never fatal the store.
         *
         * @since 2.3.0
         * @access public
         */
        public function register_wc_task() {
            if ( ! class_exists( 'Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists' ) ) {
                return;
            }

            // Load the Task subclass here rather than at plugin bootstrap: it extends WooCommerce's
            // OnboardingTasks Task base, which is only registered once the feature loads on `init`
            // (priority 4) — earlier than this callback (priority 20) but later than plugin load, so
            // requiring it at load time would skip the guarded class declaration for good.
            require_once WWP_INCLUDES_PATH . 'onboarding/class-wwp-onboarding-task.php';

            if ( ! class_exists( 'WWP_Onboarding_Task' ) ) {
                return;
            }

            $list = \Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists::get_list( 'extended' );

            if ( null === $list ) {
                return;
            }

            \Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists::add_task( 'extended', new WWP_Onboarding_Task( $list ) );
        }

        /**
         * Load and instantiate the onboarding REST controller.
         *
         * @since 2.3.0
         * @access public
         */
        public function register_rest_controller() {
            require_once WWP_INCLUDES_PATH . 'api/v1/class-wwp-rest-api-onboarding-v1-controller.php';

            $this->rest_controller = new WWP_REST_Onboarding_V1_Controller();
        }
    }
}
