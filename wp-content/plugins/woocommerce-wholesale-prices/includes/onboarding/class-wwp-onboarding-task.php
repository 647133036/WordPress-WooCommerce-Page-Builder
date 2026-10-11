<?php
/**
 * WooCommerce "Things to do next" onboarding task.
 *
 * Registers a single entry in WooCommerce Admin's extended task list ("Things to do next" on the
 * WooCommerce Home screen) that deep-links a store manager into the Wholesale Suite onboarding card.
 * This is a discovery entry only: it owns no state of its own and reads completion from the shared
 * onboarding data contract, so the task and the card can never disagree about progress.
 *
 * Consumes the scaffold (#1032): the {@see WWP_Onboarding_Data_Contract} assembled payload and the
 * {@see WWP_Onboarding_WWP_Section} plugin id. Creates no shared infrastructure of its own.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1037
 */

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// The abstract Task base only exists on WooCommerce versions that ship the OnboardingTasks feature
// (WWP supports WooCommerce down to 4.0, which predates it). The caller guards registration on the
// same class, but guard the declaration too so this file is safe to require unconditionally.
if ( class_exists( Task::class ) && ! class_exists( 'WWP_Onboarding_Task' ) ) {

    /**
     * The Wholesale Prices onboarding task shown in WooCommerce's extended task list.
     *
     * @since 2.3.0
     */
    class WWP_Onboarding_Task extends Task {

        /**
         * Memoized completion result for the current request.
         *
         * WooCommerce evaluates a task's completion several times per task-list render (via
         * `possibly_track_completion()`, `Task::get_json()` and `TaskList::get_json()`), and each
         * call would otherwise re-run the full data-contract assembly (the `wws_onboarding_register_checklist`
         * filter across every sibling section). Caching the result on the instance keeps assembly to a
         * single pass. It is scoped to this task object, which lives only for the request that built it,
         * so it never risks serving a stale answer across a state change in a later request.
         *
         * @since 2.3.0
         * @access private
         * @var bool|null
         */
        private $is_complete_cache = null;

        /**
         * Unique task id.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_id() {
            return 'wwp-onboarding';
        }

        /**
         * Task title shown in the list.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_title() {
            return __( 'Set up wholesale pricing', 'woocommerce-wholesale-prices' );
        }

        /**
         * Task description shown under the title.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_content() {
            return __( 'Create a wholesale role, set wholesale prices, and start selling to your wholesale customers.', 'woocommerce-wholesale-prices' );
        }

        /**
         * Estimated time to complete, shown next to the task.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_time() {
            return __( '5 minutes', 'woocommerce-wholesale-prices' );
        }

        /**
         * Where clicking the task sends the user: the Wholesale Suite dashboard, where the onboarding
         * card is the first element on the page.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_action_url() {
            return admin_url( 'admin.php?page=wholesale-suite' );
        }

        /**
         * Only store managers (the card's audience) should see the task.
         *
         * @since 2.3.0
         * @access public
         *
         * @return bool
         */
        public function can_view() {
            return current_user_can( 'manage_woocommerce' );
        }

        /**
         * Whether the task is complete.
         *
         * Reads the same assembled payload the card renders from, so the task mirrors exactly what the
         * user sees: complete once WWP's own section is at 100% (every counting step done), or once the
         * whole card has been dismissed (the user has told us they are finished with onboarding).
         *
         * @since 2.3.0
         * @access public
         *
         * @return bool
         */
        public function is_complete() {
            if ( null === $this->is_complete_cache ) {
                $this->is_complete_cache = $this->resolve_completion();
            }

            return $this->is_complete_cache;
        }

        /**
         * Resolve completion from the shared onboarding payload.
         *
         * Complete once WWP's own section is at 100% (every counting step done), or once the whole card
         * has been dismissed. Split out from {@see self::is_complete()} so the result can be memoized
         * per request without re-running the data-contract assembly on every WooCommerce lookup.
         *
         * @since 2.3.0
         * @access private
         *
         * @return bool
         */
        private function resolve_completion() {
            if ( ! class_exists( 'WWP_Onboarding_Data_Contract' ) || ! class_exists( 'WWP_Onboarding_WWP_Section' ) ) {
                return false;
            }

            $contract = WWP_Onboarding_Data_Contract::instance();
            $payload  = $contract->assemble( get_current_user_id() );

            if ( ! empty( $payload['dismissed'] ) ) {
                return true;
            }

            $sections = isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array();

            foreach ( $sections as $section ) {
                if ( WWP_Onboarding_WWP_Section::PLUGIN === ( $section['plugin'] ?? '' ) ) {
                    return $contract->section_is_complete( $section );
                }
            }

            return false;
        }
    }
}
