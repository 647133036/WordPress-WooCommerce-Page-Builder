<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// This is where you set various options affecting the plugin.

// Path Constants.
define( 'WWP_PLUGIN_BASE_PATH', basename( __DIR__ ) . '/' );
define( 'WWP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WWP_PLUGIN_URL', plugins_url() . '/woocommerce-wholesale-prices/' );
define( 'WWP_CSS_PATH', WWP_PLUGIN_PATH . 'css/' );
define( 'WWP_CSS_URL', WWP_PLUGIN_URL . 'css/' );
define( 'WWP_IMAGES_PATH', WWP_PLUGIN_PATH . 'images/' );
define( 'WWP_IMAGES_URL', WWP_PLUGIN_URL . 'images/' );
define( 'WWP_INCLUDES_PATH', WWP_PLUGIN_PATH . 'includes/' );
define( 'WWP_INCLUDES_URL', WWP_PLUGIN_URL . 'includes/' );
define( 'WWP_JS_PATH', WWP_PLUGIN_PATH . 'js/' );
define( 'WWP_JS_URL', WWP_PLUGIN_URL . 'js/' );
define( 'WWP_LANGUAGES_PATH', WWP_PLUGIN_PATH . 'languages/' );
define( 'WWP_LANGUAGES_URL', WWP_PLUGIN_URL . 'languages/' );
define( 'WWP_LOGS_PATH', WWP_PLUGIN_PATH . 'logs/' );
define( 'WWP_LOGS_URL', WWP_PLUGIN_URL . 'logs/' );
define( 'WWP_VIEWS_PATH', WWP_PLUGIN_PATH . 'views/' );
define( 'WWP_VIEWS_URL', WWP_PLUGIN_URL . 'views/' );

// Cron.
define( 'WWP_CRON_REQUEST_REVIEW', 'wwp_cron_request_review' );
define( 'WWP_SHOW_REQUEST_REVIEW', 'wwp_show_request_review' );
define( 'WWP_REVIEW_REQUEST_RESPONSE', 'wwp_review_request_response' );

define( 'WWP_CRON_INSTALL_ACFWF_NOTICE', 'wwp_cron_install_acfwf_notice' );
define( 'WWP_SHOW_INSTALL_ACFWF_NOTICE', 'wwp_show_install_acfwf_notice' );

define( 'WWP_SHOW_CART_CHECKOUT_BLOCKS_INCOMPATIBILITY_NOTICE', 'wwp_show_cart_checkout_blocks_incompatibility_notice' );

// Options.
define( 'WWP_OPTIONS_REGISTERED_CUSTOM_ROLES', 'wwp_options_registered_custom_roles' );

// Versioned invalidation for the per-role wholesale price range cache used by the price filter
// widget (issue #1021). Bumping this option orphans every previous-epoch transient in one write,
// regardless of role count — see WWP_Wholesale_Prices::clear_wholesale_price_range_cache().
define( 'WWP_OPTIONS_PRICE_RANGE_CACHE_EPOCH', 'wwp_price_range_cache_epoch' );

// Gamified onboarding checklist (epic #1031, scaffold #1032).
// Per-site card-level state: whole-card dismissal, per-step first-completion timestamps,
// per-section one-time flags and the install/first-seen timestamp. Consumed by the WWP-section
// detectors (#1034), the activation/completion emails (#1035) and the telemetry payload (#1036).
define( 'WWP_ONBOARDING_STATE', 'wwp_onboarding_state' );

// Per-user, per-site UI state (which sections are collapsed). Stored as blog-prefixed user meta
// so the same account keeps independent collapse state on each site of a multisite network,
// mirroring how WordPress scopes capabilities. Consumed by the card UI renderer (#1033).
define( 'WWP_ONBOARDING_UI_STATE_META', 'wwp_onboarding_ui_state' );

// Per-site send-once state for the behavior-triggered onboarding emails (#1035): the activation
// email (first wholesale price saved) and the completion email (WWP's section at 100%). Each key
// is 'pending' | 'sent' | 'suppressed'; 'suppressed' is seeded on first evaluation when the store
// has already crossed the threshold, so an existing/past-milestone store is never re-prompted.
define( 'WWP_ONBOARDING_EMAILS', 'wwp_onboarding_emails' );

if ( ! defined( 'WWS_LICENSE_DATA' ) ) {
    define( 'WWS_LICENSE_DATA', 'wws_license_data' );
}

if ( ! defined( 'WWP_ENABLE_SUBRESOURCE_INTEGRITY_CHECK' ) ) {
    /**
     * Disable subresource integrity check by default.
     */
    define( 'WWP_ENABLE_SUBRESOURCE_INTEGRITY_CHECK', false );
}

// Abilities API.
if ( ! defined( 'WWP_ABILITIES_VERSION' ) ) {
    /**
     * Version of the suite-wide Abilities contract that WWP provides.
     *
     * Bump only when the shared surface changes in a way siblings must detect — the
     * `wholesale_suite_abilities_init` action, the `wholesale-suite` category slug, or the
     * `wwp_abilities_available()` helper. It intentionally does not track the plugin version.
     *
     * No sibling needs to check this for v1; the constant exists now so that a later contract
     * change has something to gate on.
     *
     * @since 2.3.0
     */
    define( 'WWP_ABILITIES_VERSION', '1.0.0' );
}

/*
 * WWP_DISABLE_ABILITIES — suite-wide kill switch for Abilities API registration.
 *
 * Deliberately NOT defined here. Defining it to false unconditionally would make
 * `defined( 'WWP_DISABLE_ABILITIES' )` always true and therefore useless as a probe, and would
 * make any later define() by another plugin a "constant already defined" notice with no effect.
 * WWP_Abilities::is_disabled() already handles the undefined case.
 *
 * Define it as true in wp-config.php or an mu-plugin to suppress the whole suite's abilities —
 * every sibling registers on WWP's `wholesale_suite_abilities_init` action, so this stops all of
 * them, not just WWP's. The `wwp_disable_abilities` filter is the runtime equivalent.
 *
 * @since 2.3.0
 */
