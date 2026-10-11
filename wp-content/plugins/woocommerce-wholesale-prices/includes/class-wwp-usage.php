<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Wholesale Suite usage tracking functions for reporting usage to the Rymera Web Co servers for users who have opted in
 *
 * @since 1.14
 */
class WWP_Usage {

    /**
     * Class Properties.
     */

    /**
     * Property that holds the single main instance of WWP_Usage.
     *
     * @since  1.14
     * @access private
     * @var WWP_Usage
     */
    private static $_instance;

    /**
     * Most reporting weeks one check-in run sends when catching up after missed cron runs.
     * Older unreported weeks are dropped so a long-dormant site does not fire a burst of requests.
     *
     * @since 2.3.0
     * @var int
     */
    const MAX_CATCHUP_WEEKS = 4;

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
    */

    /**
     * WWP_Usage constructor.
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWP_Usage model.
     *
     * @since  1.14
     * @access public
     */
    public function __construct( $dependencies = array() ) {
        // Nothing to see here yet.
    }

    /**
     * Ensure that only one instance of WWP_Usage is loaded or can be loaded (Singleton Pattern).
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWP_Usage model.
     *
     * @since  1.14
     * @access public
     *
     * @return WWP_Usage
     */
    public static function instance( $dependencies = array() ) {

        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Gather the tracking data together
     *
     * @param int|null $week_start Unix timestamp of the Sunday 00:00 that starts the week the effectiveness
     *                             data covers. Defaults to the most recently ended week.
     *
     * @since  1.14
     * @since  2.3.0 Added the `$week_start` parameter so a catch-up run can report an earlier week.
     * @access private
     * @return array The check-in payload.
     */
    private function get_data( $week_start = null ) {

        $data = array();

        // Merge plugin specific data.
        $data = array_merge( $data, $this->_fetch_plugin_version_data() );

        // Merge license data.
        $data = array_merge( $data, $this->_fetch_license_data() );

        // Wholesale Roles.
        $wwp_wholesale_roles     = WWP_Wholesale_Roles::getInstance();
        $data['wholesale_roles'] = $wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

        // Settings.
        $data['settings'] = $this->_fetch_all_wws_settings();

        // First-year activation signals (flags + counts, no dates) ride the same
        // generic settings channel, so the usage worker stores them as-is with no
        // worker change or allowlist update needed.
        $data['settings'] = array_merge( $data['settings'], $this->_fetch_activation_signals() );

        // Merge environment settings data.
        $data = array_merge( $data, $this->_fetch_environment_settings_data() );

        // Retrieve current plugin information.
        $data['active_plugins'] = $this->_fetch_plugin_data();

        // Effectiveness data.
        if ( $this->effectiveness_allowed() ) {
            $data['effectiveness'] = $this->_fetch_effectiveness_data( $week_start );
        } else {
            $data['effectiveness'] = array();
        }

        // Onboarding checklist telemetry. This whole payload is only built inside send_checkin(),
        // which gates on the wwp_anonymous_data opt-in, so the snapshot rides that same consent.
        if ( class_exists( 'WWP_Onboarding_Telemetry' ) ) {
            $data['onboarding'] = WWP_Onboarding_Telemetry::instance()->get_snapshot();
        }

        return $data;
    }

    /**
     * Whether the check-in may carry per-week effectiveness data. Local and development hosts send
     * an empty effectiveness payload.
     *
     * @since  2.3.0 Extracted from get_data() so send_checkin() can limit catch-up runs to sites that send per-week data.
     * @access private
     * @return bool Whether effectiveness data is collected on this site.
     */
    private function effectiveness_allowed() {

        // Don't track effectiveness data on local sites.
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) {
            return false;
        }

        $parsed_home = wp_parse_url( get_option( 'home' ) );
        $host        = $parsed_home['host'];

        $disallowed_tlds = array(
            '.test',
            '.loc',
            '.local',
        );
        foreach ( $disallowed_tlds as $tld ) {
            if ( mb_substr( $host, -strlen( $tld ) ) === $tld ) {
                return false;
            }
        }

        $disallowed_hosts = array(
            'localhost',
            '127.0.0.1',
        );

        return ! in_array( $host, $disallowed_hosts, true );
    }

    /**
     * Fetch plugin specific data and compile them together into an array
     *
     * @since  2.1.7
     * @since  2.3.0 Report the installed WPAY version instead of duplicating the WWLC version.
     * @since  2.3.0 Added Wholesale Quotes (WWQ) version, active flag, and DB version.
     * @access private
     * @return array All plugin specific data.
     */
    private function _fetch_plugin_version_data() {

        $data = array();

        $data['wwp_version']  = WWP_Helper_Functions::get_wwp_version();
        $data['wwpp_version'] = WWP_Helper_Functions::get_wwpp_version();
        $data['wwof_version'] = WWP_Helper_Functions::get_wwof_version();
        $data['wwlc_version'] = WWP_Helper_Functions::get_wwlc_version();
        $data['wpay_version'] = WWP_Helper_Functions::get_wpay_version();
        $data['wwq_version']  = WWP_Helper_Functions::get_wwq_version();
        $data['wwpp']         = (int) WWP_Helper_Functions::is_wwpp_active();
        $data['wwof']         = (int) WWP_Helper_Functions::is_wwof_active();
        $data['wwlc']         = (int) WWP_Helper_Functions::is_wwlc_active();
        $data['wpay']         = (int) WWP_Helper_Functions::is_wpay_active();
        $data['wwq']          = (int) WWP_Helper_Functions::is_wwq_active();

        // Forward-only boundary marker; empty when WWQ has never recorded a schema version.
        $data['wwq_db_version'] = WWP_Helper_Functions::is_wwq_active() ? get_option( 'wws_wq_db_version', '' ) : '';

        return $data;
    }

    /**
     * Fetch license data and compile them together into an array
     *
     * @since  2.1.7
     * @since  2.2.0 Added WPAY license data.
     * @since  2.3.0 Added WWQ license data and copied through the WWLC license key that was previously dropped.
     * @access private
     * @return array All license data.
     */
    private function _fetch_license_data() {

        $data     = array();
        $licenses = WWP_Helper_Functions::get_license_data();

        $data['wwpp_license_email'] = isset( $licenses ) && isset( $licenses['wwpp_license_email'] ) ? $licenses['wwpp_license_email'] : '';
        $data['wwpp_license_key']   = isset( $licenses ) && isset( $licenses['wwpp_license_key'] ) ? $licenses['wwpp_license_key'] : '';
        $data['wwof_license_email'] = isset( $licenses ) && isset( $licenses['wwof_license_email'] ) ? $licenses['wwof_license_email'] : '';
        $data['wwof_license_key']   = isset( $licenses ) && isset( $licenses['wwof_license_key'] ) ? $licenses['wwof_license_key'] : '';
        $data['wwlc_license_email'] = isset( $licenses ) && isset( $licenses['wwlc_license_email'] ) ? $licenses['wwlc_license_email'] : '';
        $data['wwlc_license_key']   = isset( $licenses ) && isset( $licenses['wwlc_license_key'] ) ? $licenses['wwlc_license_key'] : '';
        $data['wpay_license_key']   = isset( $licenses ) && isset( $licenses['wpay_license_key'] ) ? $licenses['wpay_license_key'] : '';
        $data['wpay_license_email'] = isset( $licenses ) && isset( $licenses['wpay_license_email'] ) ? $licenses['wpay_license_email'] : '';
        $data['wwq_license_email']  = isset( $licenses ) && isset( $licenses['wwq_license_email'] ) ? $licenses['wwq_license_email'] : '';
        $data['wwq_license_key']    = isset( $licenses ) && isset( $licenses['wwq_license_key'] ) ? $licenses['wwq_license_key'] : '';

        return $data;
    }

    /**
     * Fetch all WWS settings (across Premium plugins too if installed) and compile them together into an array
     *
     * @since  1.14
     * @since  2.1.7 Make this function private.
     * @since  2.2.0 Added WPAY settings.
     * @since  2.3.0 Added Wholesale Quotes (WWQ) settings via an exact-name allowlist.
     * @access private
     * @return array All settings from WWP, WWPP, WWOF, WWLC, WPAY, WWQ plugins
     */
    private function _fetch_all_wws_settings() {

        global $wpdb;
        $settings = array();

        // WWP.
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value
                FROM {$wpdb->prefix}options
                WHERE option_name like %s
                    OR option_name like %s
                    OR option_name like %s
                    OR option_name like %s
                    OR option_name like %s",
                'wwp_%',
                'wwpp_%',
                'wwof_%',
                'wwlc_%',
                'wpay_%'
            ),
            ARRAY_A
        );

        // WPAY.
        $wpay_settings_whitelist = array(
            'wpay_api_mode',
            'wpay_installed_version',
            'wpay_license_activated',
            'wpay_license_notice_dismissed',
            'wpay_payment_method_name',
        );
        foreach ( $result as $option ) {
            if ( isset( $option ) && isset( $option['option_name'] ) && isset( $option['option_value'] ) ) {
                // if option_name starts with wpay and not included in whitelist, continue.
                if ( str_starts_with( $option['option_name'], 'wpay_' ) && ! in_array( $option['option_name'], $wpay_settings_whitelist, true ) ) {
                    continue;
                }

                $settings[ $option['option_name'] ] = $option['option_value'];
            }
        }

        // Unset unwanted settings.
        unset( $settings['wwp_settings_hash'] );
        unset( $settings['wwpp_settings_hash'] );
        unset( $settings['wwp_product_hash'] );
        unset( $settings['wwpp_product_hash'] );
        unset( $settings['wwp_product_cat_hash'] );
        unset( $settings['wwpp_product_cat_hash'] );
        unset( $settings['wwpp_update_data'] );
        unset( $settings['wwof_update_data'] );
        unset( $settings['wwlc_update_data'] );
        unset( $settings['wwof_order_form_v2_consumer_key'] );
        unset( $settings['wwof_order_form_v2_consumer_secret'] );
        unset( $settings['wwlc_security_recaptcha_secret_key'] );
        unset( $settings['wwlc_security_recaptcha_site_key'] );

        // WWQ — gathered by an exact-name allowlist (never a `wws_wq_%` LIKE) so
        // license credentials, license-server data, the admin-recipient email and
        // the mailcatcher transient can never leak into the analytics store. Guarded on
        // is_wwq_active() so a deactivated-WWQ site does not report quote configuration
        // alongside `wwq = 0`, matching every other WWQ signal in the payload.
        if ( WWP_Helper_Functions::is_wwq_active() ) {
            $settings = array_merge( $settings, $this->_fetch_wwq_settings() );
        }

        if ( WWP_Helper_Functions::is_wpay_active() ) {
            $plans_query = \RymeraWebCo\WPay\Helpers\Payment_Plans::get_payment_plans(
                array(
                    'posts_per_page' => -1,
                )
            );

            $percentage_count            = 0;
            $fixed_count                 = 0;
            $mixed_count                 = 0;
            $enabled_plan_count          = 0;
            $disabled_plan_count         = 0;
            $enabled_restrictions_count  = 0;
            $disabled_restrictions_count = 0;
            $restricted_to_roles_count   = 0;
            $restricted_to_users_count   = 0;
            foreach ( $plans_query->posts as $plan ) {

                /***************************************************************************
                 * Check for plan types
                 ***************************************************************************
                 *
                 * We gather data for plans that have mixed 'fixed' + 'percentage' types.
                 * We also count the number of plans that have only 'fixed' and 'percentage'
                 * types.
                 */
                $plan_types = array_column( array_column( $plan->breakdown, 'due' ), 'type' );
                $plan_types = array_flip( $plan_types );
                if ( count( $plan_types ) > 1 ) {
                    ++$mixed_count;
                } elseif ( isset( $plan_types['percentage'] ) ) {
                    ++$percentage_count;
                } elseif ( isset( $plan_types['fixed'] ) ) {
                    ++$fixed_count;
                }

                /***************************************************************************
                 * Check for enabled/disabled plans
                 ***************************************************************************
                 *
                 * We count the number of enabled and disabled plans.
                 */
                if ( 'yes' === $plan->enabled ) {
                    ++$enabled_plan_count;
                } else {
                    ++$disabled_plan_count;
                }

                if ( 'yes' === $plan->apply_restrictions ) {
                    ++$enabled_restrictions_count;
                    if ( ! empty( $plan->wholesale_roles ) ) {
                        ++$restricted_to_roles_count;
                    }
                    if ( ! empty( $plan->allowed_users ) ) {
                        ++$restricted_to_users_count;
                    }
                } else {
                    ++$disabled_restrictions_count;
                }
            }

            $settings['wpay_plan_percentage_count']       = $percentage_count;
            $settings['wpay_plan_fixed_count']            = $fixed_count;
            $settings['wpay_plan_mixed_count']            = $mixed_count;
            $settings['wpay_enabled_plan_count']          = $enabled_plan_count;
            $settings['wpay_disabled_plan_count']         = $disabled_plan_count;
            $settings['wpay_enabled_restrictions_count']  = $enabled_restrictions_count;
            $settings['wpay_disabled_restrictions_count'] = $disabled_restrictions_count;
            $settings['wpay_restricted_to_roles_count']   = $restricted_to_roles_count;
            $settings['wpay_restricted_to_users_count']   = $restricted_to_users_count;
        }

        // Return the settings as an array.
        return $settings;
    }

    /**
     * Fetch the allowlisted Wholesale Quotes (WWQ) settings for the check-in.
     *
     * WWQ options are collected by an EXACT-NAME allowlist rather than a
     * `wws_wq_%` LIKE, because a blanket prefix match would leak WWQ's license
     * key, stored license-server data, an admin-recipient email and a
     * mailcatcher transient into the analytics store. The allowlist is closed by
     * default (see get_wwq_settings_allowlist()), and matching exact names also
     * never picks up `_transient_wws_wq_*` rows.
     *
     * @since  2.3.0
     * @access private
     *
     * @return array Allowlisted WWQ option name => value pairs (only options that exist).
     */
    private function _fetch_wwq_settings() {

        global $wpdb;

        $allowlist = $this->get_wwq_settings_allowlist();

        // Guard the empty case: an empty allowlist would build `IN ( )` and log a MySQL error.
        if ( empty( $allowlist ) ) {
            return array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $allowlist ), '%s' ) );

        // $placeholders is a comma-separated list of %s tokens sized to the hard-coded
        // allowlist; every option name is passed as a prepared argument in $allowlist.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->prefix}options WHERE option_name IN ( $placeholders )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                $allowlist
            ),
            ARRAY_A
        );

        $settings = array();
        foreach ( (array) $results as $option ) {
            if ( isset( $option['option_name'], $option['option_value'] ) ) {
                $settings[ $option['option_name'] ] = $option['option_value'];
            }
        }

        return $settings;
    }

    /**
     * The exact-name allowlist of Wholesale Quotes (WWQ) options sent in the check-in.
     *
     * Kept as an explicit closed list — NOT "everything not excluded" — so a new
     * `wws_wq_` option is never sent until it is added here on purpose. The
     * security-sensitive names (license credentials, license-server data, the
     * `wws_wq_email_admin_recipient` PII, internal bookkeeping and the dead
     * `wws_wq_settings`) are simply absent from this list and never leave the site.
     *
     * @since  2.3.0
     * @access private
     *
     * @return array List of allowlisted `wws_wq_` option names.
     */
    private function get_wwq_settings_allowlist() {

        return array(
            // SECURITY: every name in this array is POSTed to the analytics endpoint by
            // WWP_Usage::send_checkin(). Do NOT add wws_wq_license_key,
            // wws_wq_license_account_email, wws_wq_license_status, wws_wq_license_data,
            // wws_wq_last_license_check, wws_wq_option_license_activated,
            // wws_wq_option_license_expired, wws_wq_option_update_data,
            // wws_wq_option_retrieving_update_data, wws_wq_email_admin_recipient,
            // wws_wq_enable_mail_catcher or wws_wq_settings — adding one exfiltrates
            // credentials or PII. Also excluded as internal bookkeeping (not telemetry):
            // wws_wq_db_version, wws_wq_rewrite_version, wws_wq_view_quote_rewrite_flushed,
            // wws_wq_expire_quotes_scheduled_version — 16 excluded in total. Guarded by
            // tests/pest/Integration/Usage/Settings_Allowlist_Test.php.
            'wws_wq_allow_messages_converted',
            'wws_wq_allow_messages_declined',
            'wws_wq_csv_upload_rate_limit',
            'wws_wq_delete_on_uninstall',
            'wws_wq_email_message_admin_enabled',
            'wws_wq_email_message_customer_enabled',
            'wws_wq_email_quote_admin_notification_enabled',
            'wws_wq_email_quote_approved_enabled',
            'wws_wq_email_quote_converted_enabled',
            'wws_wq_email_quote_declined_enabled',
            'wws_wq_email_quote_expired_enabled',
            'wws_wq_email_quote_submitted_enabled',
            'wws_wq_enable_messages',
            'wws_wq_messages_per_hour',
            'wws_wq_quote_page',
            'wws_wq_settings_allow_multiple_quotes',
            'wws_wq_settings_button_text',
            'wws_wq_settings_clear_cart_after_quote',
            'wws_wq_settings_counter_minicart_placement',
            'wws_wq_settings_counter_position',
            'wws_wq_settings_disable_my_account_page',
            'wws_wq_settings_enable_address_collection',
            'wws_wq_settings_enable_cart_to_quote',
            'wws_wq_settings_enable_dropdown_widget',
            'wws_wq_settings_enable_guest_quotes',
            'wws_wq_settings_enable_toast_notifications',
            'wws_wq_settings_guest_quote_limit',
            'wws_wq_settings_guest_quote_tracking_period',
            'wws_wq_settings_guest_wholesale_role',
            'wws_wq_settings_maximum_quote_items',
            'wws_wq_settings_minimum_quote_amount',
            'wws_wq_settings_out_of_stock_handling',
            'wws_wq_settings_quote_add_action_button',
            'wws_wq_settings_quote_button_cart_label',
            'wws_wq_settings_quote_button_cart_placement',
            'wws_wq_settings_quote_button_cart_style',
            'wws_wq_settings_quote_button_label',
            'wws_wq_settings_quote_button_product_placement',
            'wws_wq_settings_quote_button_product_style',
            'wws_wq_settings_quote_button_remove_from_quote_label',
            'wws_wq_settings_quote_button_shop_placement',
            'wws_wq_settings_quote_button_shop_style',
            'wws_wq_settings_quote_description',
            'wws_wq_settings_quote_expiration_days',
            'wws_wq_settings_quotes_history_title',
            'wws_wq_settings_quote_submit_redirect',
            'wws_wq_settings_quote_title',
            'wws_wq_settings_quote_update_redirect',
            'wws_wq_settings_require_admin_approval',
            'wws_wq_settings_require_billing_address',
            'wws_wq_settings_require_shipping_address',
            'wws_wq_settings_restrict_to_wholesale',
            'wws_wq_settings_show_counter_widget',
            'wws_wq_settings_show_login_create_link',
            'wws_wq_settings_show_login_link',
            'wws_wq_settings_show_quote_button_out_of_stock',
            'wws_wq_settings_show_retail_prices',
            'wws_wq_settings_show_wholesale_prices',
            'wws_wq_settings_toast_duration',
            'wws_wq_view_quote_page',
        );
    }

    /**
     * Fetch first-year activation signals for the weekly check-in.
     *
     * Flags and counts only — no dates/timestamps and no PII. The signals ride
     * the generic settings channel (see WWP_Usage::get_data()), which the usage
     * worker stores as-is without an allowlist, so no worker-side change is
     * required; they must not be promoted to bespoke top-level params, which
     * would need worker allowlisting.
     *
     * WWP contributes its own self-contained `wholesale_price_set` flag. Sibling
     * plugins (WWPP, Wholesale Quotes, Invoice Gateway) expose their signals by
     * hooking the `wwp_activation_signals` filter, so WWP reads whatever is
     * present with no hard runtime dependency and the data degrades gracefully
     * when a sibling is inactive.
     *
     * @since  2.2.9
     * @access private
     * @return array Activation-signal key => scalar value pairs.
     */
    private function _fetch_activation_signals() {

        global $wpdb;

        // `wholesale_price_set`: true once any product or variation carries a
        // wholesale price for any role. `{role}_have_wholesale_price = 'yes'` is
        // WWP's canonical "has a wholesale price" marker.
        $wholesale_price_set = (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value = %s LIMIT 1",
                '%_have_wholesale_price',
                'yes'
            )
        );

        $signals = array(
            'wholesale_price_set' => $wholesale_price_set ? 'yes' : 'no',
        );

        /**
         * Filters the first-year activation signals sent in WWP's weekly check-in.
         *
         * Sibling plugins add their own flags/counts here (e.g. `pricing_rule_set`,
         * `quote_count`, `quote_converted_count`, `invoice_gateway_order_count`).
         * Values must be flags/counts only — no dates/timestamps or PII — and are
         * stored as-is by the usage worker's generic settings channel.
         *
         * @since 2.2.9
         *
         * @param array $signals Activation-signal key => scalar value pairs.
         */
        return (array) apply_filters( 'wwp_activation_signals', $signals );
    }

    /**
     * Fetch environment settings data and compile them together into an array
     *
     * @since  2.1.7
     * @access private
     * @return array All environment settings data.
     */
    private function _fetch_environment_settings_data() {

        $data = array();

        // Get current theme info.
        $theme_data = wp_get_theme();

        // Get multisite data.
        $count_blogs = 1;
        if ( is_multisite() ) {
            if ( function_exists( 'get_blog_count' ) ) {
                $count_blogs = get_blog_count();
            } else {
                $count_blogs = 'Not Set';
            }
        }

        $data['url']                            = home_url();
        $data['php_version']                    = phpversion();
        $data['wp_version']                     = get_bloginfo( 'version' );
        $data['wc_version']                     = WWP_Helper_Functions::get_current_woocommerce_version();
        $data['server']                         = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
        $data['multisite']                      = is_multisite();
        $data['sites']                          = $count_blogs;
        $data['usercount']                      = function_exists( 'count_users' ) ? count_users() : 'Not Set';
        $data['themename']                      = $theme_data->Name;
        $data['themeversion']                   = $theme_data->Version;
        $data['admin_email']                    = get_bloginfo( 'admin_email' );
        $data['usagetracking']                  = get_option( 'wwp_usage_tracking_config', false );
        $data['timezoneoffset']                 = wp_timezone_string();
        $data['locale']                         = get_locale();
        $data['is_hpos_enabled']                = 'yes' === get_option( 'woocommerce_feature_custom_order_tables_enabled' );
        $data['is_custom_orders_table_enabled'] = OrderUtil::custom_orders_table_usage_is_enabled();
        $data['is_cart_block']                  = has_block( 'woocommerce/cart', wc_get_page_id( 'cart' ) );
        $data['is_checkout_block']              = has_block( 'woocommerce/checkout', wc_get_page_id( 'checkout' ) );

        return $data;
    }

    /**
     * Fetch all plugin data and compile them together into an array
     *
     * @since  2.1.7
     * @access private
     * @return array All plugin data.
     */
    private function _fetch_plugin_data() {

        // This site's active plugins list.
        $active_plugins = get_option( 'active_plugins', array() );

        // Multi-site network activated plugins (if we're on multi-site).
        $network_active_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );

        // Merge to get the final active plugins list.
        $all_active_plugins = array_unique( array_merge( $active_plugins, $network_active_plugins ) );

        return $all_active_plugins;
    }

    /**
     * Fetch effectiveness data and compile them together into an array
     *
     * @since  2.1.7
     * @since  2.2.2 Change date from Sunday last week to Saturday last week.
     * @since  2.3.0 Merge Wholesale Quotes (WWQ) weekly effectiveness metrics over the same window.
     * @since  2.3.0 Report the week passed in instead of always last week, so a catch-up run can send a missed week.
     * @access private
     *
     * @param int|null $week_start Unix timestamp of the Sunday 00:00 that starts the reported week.
     *                             Defaults to the most recently ended week.
     *
     * @return array All effectiveness data.
     */
    private function _fetch_effectiveness_data( $week_start = null ) {

        $data = array();

        // Set the start date to the Sunday that starts the reported week.
        $start_date = null === $week_start ? $this->get_reporting_week_start() : (int) $week_start;

        // Set the end date to the Saturday of the same week.
        $end_date = $start_date + ( 6 * DAY_IN_SECONDS );

        $order_args = array(
            'limit'        => -1,
            'status'       => array( 'wc-completed', 'wc-processing' ),
            'date_created' => gmdate( 'Y-m-d', $start_date ) . '...' . gmdate( 'Y-m-d', $end_date ),
            'return'       => 'objects',
        );

        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $order_args['meta_query'] = array(
                array(
                    'key'     => '_wwpp_order_type',
                    'value'   => 'wholesale',
                    'compare' => '=',
                ),
            );
        } else {
            $order_args['wholesale_order'] = true;
        }

        // Fetch the orders with wc_get_orders.
        $orders = wc_get_orders( $order_args );

        // Get the total number of orders.
        $total_orders = count( $orders );

        // Get the total number of orders using WPAY payment method with generated Stripe invoice.
        $wpay_order_count = 0;

        // Get the revenue total that used WPAY payment method.
        $revenue_total_wpay = 0;

        // Get the revenue total.
        $revenue_total = 0;
        foreach ( $orders as $order ) {
            $order_total = $order->get_total();

            $revenue_total += $order_total;

            // Get WPAY additional data.
            if ( $order->get_payment_method() === 'wc_wholesale_payments' && $order->get_meta( '_wpay_stripe_invoice_id' ) ) {
                ++$wpay_order_count;
                $revenue_total_wpay += $order_total;
            }
        }

        // Get the total number of user registrations for wholesale.

        // Get all registered wholesale roles.
        $wwp_wholesale_roles        = WWP_Wholesale_Roles::getInstance();
        $registered_wholesale_roles = $wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

        // Get all users registered after the $start_date but before $end_date with a wholesale user role.
        $args = array(
            'role__in'    => array_keys( $registered_wholesale_roles ),
            'date_query'  => array(
                array(
                    'after'     => gmdate( 'Y-m-d H:i:s', $start_date ),
                    'before'    => gmdate( 'Y-m-d H:i:s', $end_date ),
                    'inclusive' => true,
                ),
            ),
            'count_total' => true,
            'fields'      => 'ID',
        );

        $total_registrations = count( get_users( $args ) );

        // Set the data and pass back.

        $data['currency']                = get_option( 'woocommerce_currency' );
        $data['date']                    = gmdate( 'Y-m-d H:i:s', $end_date );
        $data['wholesale_order_count']   = $total_orders;
        $data['wholesale_order_revenue'] = $revenue_total;
        $data['wholesale_new_leads']     = $total_registrations;

        // WPAY additional data.
        $data['wpay_order_count']   = $wpay_order_count;
        $data['wpay_order_revenue'] = $revenue_total_wpay;

        // Merge Wholesale Quotes weekly metrics over the same reporting window.
        $data = array_merge( $data, $this->_fetch_wwq_effectiveness_data( $start_date, $end_date ) );

        return $data;
    }

    /**
     * Fetch Wholesale Quotes (WWQ) weekly effectiveness metrics for the check-in.
     *
     * The metrics are produced by WWQ's own reporting class, so WWP carries no
     * knowledge of the quotes schema. The call is feature-detected on both the
     * plugin being active and the reporting class (and its methods) existing,
     * so an older WWQ that predates the reporter — or no WWQ at all — degrades
     * to an empty array instead of fatally erroring the weekly cron.
     *
     * The reporting window is WWP's existing Sunday-to-Saturday window, passed
     * in so both halves of the effectiveness payload cover the same dates.
     *
     * The reporting class's output is whitelisted to the known weekly metric
     * keys (field-list contract section B) so a WWQ-side rename or bug can never
     * inject arbitrary keys into the outgoing check-in payload.
     *
     * @since  2.3.0
     * @access private
     *
     * @param int $start_date Unix timestamp for the start of the reporting window.
     * @param int $end_date   Unix timestamp for the end of the reporting window.
     *
     * @return array WWQ weekly metric key => value pairs limited to the known keys, or an empty array when unavailable.
     */
    private function _fetch_wwq_effectiveness_data( $start_date, $end_date ) {

        $reporter_class = 'WholesaleQuotes\Helpers\Usage_Reporter';

        if ( ! WWP_Helper_Functions::is_wwq_active() || ! class_exists( $reporter_class ) || ! method_exists( $reporter_class, 'instance' ) ) {
            return array();
        }

        $reporter = $reporter_class::instance();

        if ( ! is_object( $reporter ) || ! method_exists( $reporter, 'weekly' ) ) {
            return array();
        }

        $weekly = $reporter->weekly( (int) $start_date, (int) $end_date );

        if ( ! is_array( $weekly ) ) {
            return array();
        }

        // Whitelist the reporting class's output to the known weekly metric keys
        // (field-list contract section B). Unknown keys are dropped so a WWQ-side
        // rename or bug cannot inject arbitrary keys into the check-in; absent keys
        // are simply omitted, matching how the payload treats missing metrics.
        $known_keys = array(
            'wwq_quotes_submitted',
            'wwq_guest_quotes_submitted',
            'wwq_total_quoted_value',
            'wwq_quotes_approved',
            'wwq_quotes_declined',
            'wwq_quotes_converted',
            'wwq_converted_revenue',
        );

        return array_intersect_key( $weekly, array_flip( $known_keys ) );
    }

    /**
     * Handle a custom 'wholesale_order' query var to get orders with the 'wwp_wholesale_role' meta.
     *
     * @param array $query      - Args for WP_Query.
     * @param array $query_vars - Query vars from WC_Order_Query.
     *
     * @return array modified $query
     */
    public function handle_custom_order_query_var( $query, $query_vars ) {

        if ( ! empty( $query_vars['wholesale_order'] ) && true === $query_vars['wholesale_order'] ) {
            // Adjust meta query to get orders where 'wwp_wholesale_role' exists (indicating its a wholesale order).
            $query['meta_query'][] = array(
                'key'     => 'wwp_wholesale_role',
                'compare' => 'EXISTS',
            );
        }

        return $query;
    }

    /**
     * Send the checkin
     *
     * @param BOOL $override            Flag to override if tracking is allowed or not.
     * @param BOOL $ignore_last_checkin Flag to ignore that last checkin time check. Sends only the latest week and
     *                                  moves the marker to it, so any pending backlog of missed weeks is dropped.
     *
     * @since  1.14
     * @since  2.3.0 Send every reporting week once, stepping from the last reported week, instead of guarding on
     *         wall-clock time since the last send, so WP-Cron delay variance can neither skip a week nor send
     *         the same week twice (#1066). Weeks that end without consent are skipped, and only sites that send
     *         per-week effectiveness data catch up on missed weeks.
     * @access public
     *
     * @return BOOL  Whether the checkin was sent successfully
     */
    public function send_checkin( $override = false, $ignore_last_checkin = false ) {

        // Don't track anything from our domains.
        $home_url = trailingslashit( home_url() );
        if ( str_contains( $home_url, 'wholesalesuiteplugin.com' ) ) {
            return false;
        }

        // Check if tracking is allowed on this site.
        if ( ! $this->tracking_allowed() && ! $override ) {
            // Weeks that end without consent are never owed: move the marker past them so a later
            // opt-in does not catch up on weeks from the opt-out period.
            update_option( 'wwp_usage_tracking_last_reported_week', $this->get_reporting_week_start(), 'no' );

            return false;
        }

        // Send every reporting week exactly once. The payload covers one Sunday-to-Saturday week, so
        // the guard tracks the last week reported and sends each week that has ended since, instead
        // of comparing wall-clock time since the last send against the weekly cron interval. Those
        // two clocks are unrelated: WP-Cron's variable delay let a run fire slightly under a week
        // after a delayed previous run, and the old guard then skipped a week that was never sent
        // (#1066). Anchoring on the previously reported week does not depend on when the cron
        // fires, and a duplicate fire in the same week finds nothing left to send.
        $latest_week   = $this->get_reporting_week_start();
        $last_reported = (int) get_option( 'wwp_usage_tracking_last_reported_week', 0 );

        if ( ! $last_reported ) {
            // Sites updated from a build that recorded only the send timestamp: resolve the week that
            // send reported with the window formula that build used (the Sunday before last Monday).
            $last_send = get_option( 'wwp_usage_tracking_last_checkin' );
            if ( is_numeric( $last_send ) ) {
                $last_reported = strtotime( 'monday last week', (int) $last_send ) - DAY_IN_SECONDS;
                // strtotime() follows the PHP default timezone, which a plugin may have changed from
                // UTC; snap the result back onto the UTC Sunday grid.
                $last_reported = $latest_week + (int) round( ( $last_reported - $latest_week ) / WEEK_IN_SECONDS ) * WEEK_IN_SECONDS;
            }
        }

        // A marker ahead of the latest week (the clock moved back) would send nothing until real time
        // caught up; treat the latest week as owed instead.
        if ( $last_reported > $latest_week ) {
            $last_reported = $latest_week - WEEK_IN_SECONDS;
        }

        if ( $ignore_last_checkin || defined( 'WWS_TESTING_SITE' ) ) {
            $weeks = array( $latest_week );
        } else {
            $first_week = $last_reported ? $last_reported + WEEK_IN_SECONDS : $latest_week;
            $first_week = max( $first_week, $latest_week - ( ( self::MAX_CATCHUP_WEEKS - 1 ) * WEEK_IN_SECONDS ) );

            $weeks = array();
            for ( $week = $first_week; $week <= $latest_week; $week += WEEK_IN_SECONDS ) {
                $weeks[] = $week;
            }

            // Local and development hosts send no per-week data, so every catch-up payload would be the
            // same; one check-in is enough.
            if ( $weeks && ! $this->effectiveness_allowed() ) {
                $weeks = array( $latest_week );
            }
        }

        if ( empty( $weeks ) ) {
            return false;
        }

        $checkin_url = 'https://usg.rymeraplugins.com/v1/wwp-checkin/';
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE &&
            defined( 'RYMERA_LOCAL_USAGE_TRACKING_URL' ) &&
            wc_is_valid_url( RYMERA_LOCAL_USAGE_TRACKING_URL ) ) {
            $checkin_url = RYMERA_LOCAL_USAGE_TRACKING_URL;
        }

        // Only the effectiveness data depends on the week, so build the rest of the payload once.
        // More than one week is only sent when effectiveness data is allowed (see above).
        $payload = $this->get_data( $weeks[0] );

        // Record the send time and the latest week reported after the payload is built, so a failed
        // build leaves the weeks owed, and before sending, so a cron run that fires while this one is
        // still sending finds nothing left to send.
        update_option( 'wwp_usage_tracking_last_checkin', time(), 'no' );
        update_option( 'wwp_usage_tracking_last_reported_week', $latest_week, 'no' );

        foreach ( $weeks as $index => $week ) {
            if ( $index > 0 ) {
                $payload['effectiveness'] = $this->_fetch_effectiveness_data( $week );
            }

            wp_remote_post(
                $checkin_url,
                array(
                    'method'      => 'POST',
                    'timeout'     => 5,
                    'redirection' => 5,
                    'httpversion' => '1.1',
                    'blocking'    => false,
                    'body'        => $payload,
                    'user-agent'  => 'WWP/' . WWP_Helper_Functions::get_wwp_version() . '; ' . get_bloginfo( 'url' ),
                )
            );
        }

        return true;
    }

    /**
     * Start of the most recently ended reporting week: 00:00 UTC of the Sunday that began the last
     * complete Sunday-to-Saturday week before `$now`. Reporting weeks form a fixed grid of Sundays,
     * so send_checkin() steps from the last reported week to this one in whole weeks.
     *
     * @param int|null $now Unix timestamp to evaluate at. Defaults to the current time.
     *
     * @internal Public only for tests and `wp eval`; not part of the plugin API.
     *
     * @since  2.3.0
     * @access public
     *
     * @return int Unix timestamp of the reported week's start.
     */
    public function get_reporting_week_start( $now = null ) {

        $now      = null === $now ? time() : (int) $now;
        $midnight = $now - ( $now % DAY_IN_SECONDS );
        $sunday   = $midnight - ( (int) gmdate( 'w', $now ) * DAY_IN_SECONDS );

        return $sunday - WEEK_IN_SECONDS;
    }

    /**
     * Check if tracking is allowed on this site
     *
     * @since  1.14
     * @access public
     * @return BOOL whether this site can be tracked or not
     */
    private function tracking_allowed() {

        $allow_usage = get_option( 'wwp_anonymous_data', false );

        return ( false !== $allow_usage && 'no' !== $allow_usage ) || WWP_Helper_Functions::has_paid_plugin_active();
    }

    /**
     * Schedule when we should send tracking data
     *
     * @since  1.14
     * @access public
     */
    public function schedule_send() {

        if ( ! wp_next_scheduled( 'wwp_usage_tracking_cron' ) ) {
            $tracking             = array();
            $tracking['day']      = wp_rand( 0, 6 );
            $tracking['hour']     = wp_rand( 0, 23 );
            $tracking['minute']   = wp_rand( 0, 59 );
            $tracking['second']   = wp_rand( 0, 59 );
            $tracking['offset']   = ( $tracking['day'] * DAY_IN_SECONDS ) +
                ( $tracking['hour'] * HOUR_IN_SECONDS ) +
                ( $tracking['minute'] * MINUTE_IN_SECONDS ) +
                $tracking['second'];
            $tracking['initsend'] = strtotime( 'next sunday' ) + $tracking['offset'];

            wp_schedule_event( $tracking['initsend'], 'weekly', 'wwp_usage_tracking_cron' );
            update_option( 'wwp_usage_tracking_config', $tracking, 'no' );
        }
    }

    /**
     * Check if the admin notice was opted in to tracking and if so, send the data and schedule the cron for future
     * sends
     *
     * @since  1.14
     * @access public
     */
    public function check_for_optin() {

        if ( ! ( ! empty( $_REQUEST['wwp_action'] ) && 'opt_into_tracking' === sanitize_key( wp_unslash( $_REQUEST['wwp_action'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        if ( get_option( 'wwp_anonymous_data' ) === 'yes' ) {
            return;
        }

        if ( WWP_Helper_Functions::has_paid_plugin_active() ) {
            update_option( 'wwp_anonymous_data', 'yes', 'no' );

            return;
        }

        update_option( 'wwp_anonymous_data', 'yes', 'no' );
        $this->send_checkin( true, true );
        update_option( 'wwp_tracking_notice', 1, 'no' );
    }

    /**
     * Check for optout via the admin notice and handle appropriately
     *
     * @since  1.14
     * @access public
     */
    public function check_for_optout() {

        if ( ! ( ! empty( $_REQUEST['wwp_action'] ) && 'opt_out_of_tracking' === sanitize_key( wp_unslash( $_REQUEST['wwp_action'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        if ( get_option( 'wwp_anonymous_data' ) === 'yes' ) {
            return;
        }

        if ( WWP_Helper_Functions::has_paid_plugin_active() ) {
            return;
        }

        update_option( 'wwp_anonymous_data', 'no', 'no' );
        update_option( 'wwp_tracking_notice', 1, 'no' );
    }

    /**
     * Add the cron schedule
     *
     * @param array $schedules The schedules array from the filter.
     *
     * @since  1.14
     * @access public
     */
    public function add_schedules( $schedules = array() ) {

        // Adds once weekly to the existing schedules.
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display'  => __( 'Once Weekly', 'woocommerce-wholesale-prices' ),
        );

        return $schedules;
    }

    /**
     * Set up the usage tracking notice for admin users. Only shows once so as not to annoy them.
     *
     * @since  1.14
     * @access public
     */
    public function wwp_admin_setup_usage_tracking_notice() {

        if ( current_user_can( 'administrator' ) ) { // phpcs:ignore
            if ( ! is_network_admin() ) {

                if ( ! get_option( 'wwp_tracking_notice' ) || defined( 'WWS_TESTING_SITE' ) ) {

                    if ( ! get_option( 'wwp_anonymous_data', false ) ) {

                        if ( ! WWP_Helper_Functions::is_dev_url( network_site_url( '/' ) ) || defined( 'WWS_TESTING_SITE' ) ) {

                            if ( WWP_Helper_Functions::has_paid_plugin_active() ) {
                                update_option( 'wwp_anonymous_data', 1, 'no' );

                                return;
                            }

                            $optin_url  = add_query_arg( 'wwp_action', 'opt_into_tracking' );
                            $optout_url = add_query_arg( 'wwp_action', 'opt_out_of_tracking' );

                            $output = '<div class="updated">';

                            $output .= '<p style="font-weight:700;">' . __( 'Wholesale Suite Usage Tracking Permission', 'woocommerce-wholesale-prices' ) . '</p>';
                            $output .= '<p>';
                            $output .= __( 'Allow Wholesale Suite to track plugin usage? Opt-in to let us track usage data so we know with which WordPress configurations, themes and plugins we should test with.', 'woocommerce-wholesale-prices' );
                            $output .= '</p>';
                            $output .= '<a href="' . esc_url( $optin_url ) . '" class="button-primary">' . __( 'Allow', 'woocommerce-wholesale-prices' ) . '</a>';
                            $output .= '&nbsp;<a href="' . esc_url( $optout_url ) . '" class="button-secondary">' . __( 'Do not allow', 'woocommerce-wholesale-prices' ) . '</a>';
                            $output .= '</p></div>';

                            echo wp_kses_post( $output );
                        } else {
                            // is testing site.
                            update_option( 'wwp_tracking_notice', '1', 'no' );
                        }
                    }
                }
            }
        }
    }

    /**
     * Execute model.
     *
     * @since  1.14
     * @access public
     */
    public function run() {

        // Schedule sending.
        add_action( 'init', array( $this, 'schedule_send' ) );
        add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
        add_action( 'wwp_usage_tracking_cron', array( $this, 'send_checkin' ) );

        // Handle admin notice for optin.
        add_action( 'admin_notices', array( $this, 'wwp_admin_setup_usage_tracking_notice' ) );
        add_action( 'network_admin_notices', array( $this, 'wwp_admin_setup_usage_tracking_notice' ) );
        add_action( 'admin_head', array( $this, 'check_for_optin' ) );
        add_action( 'admin_head', array( $this, 'check_for_optout' ) );

        // Custom query filter for orders.
        add_filter(
            'woocommerce_order_data_store_cpt_get_orders_query',
            array(
                $this,
                'handle_custom_order_query_var',
            ),
            10,
            2
        );
    }
}
