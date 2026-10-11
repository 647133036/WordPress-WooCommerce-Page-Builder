<?php
/**
 * Global helpers for the Wholesale Suite Abilities contract.
 *
 * Deliberately global (rather than methods on a WWP class) so sibling Wholesale
 * Suite plugins can feature-detect them with function_exists() without taking a
 * code-level dependency on a WWP class or method signature.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'wwp_abilities_available' ) ) {

    /**
     * Whether a usable WordPress Abilities API is present.
     *
     * The Abilities API landed in WordPress core in 6.9, and WooCommerce 10.9+ bundles
     * its own copy of the library. So on any site where a current Wholesale Suite plugin
     * can run at all, the API is present — either from core or from WooCommerce. This is
     * a feature detect rather than a version check precisely because either source will
     * do, and neither requires the suite to ship a copy of core.
     *
     * When it returns false (WP < 6.9 *and* WC < 10.9), the correct behaviour for the
     * whole suite is a silent no-op.
     *
     * @since 2.3.0
     *
     * @return bool True when abilities and ability categories can be registered.
     */
    function wwp_abilities_available() {
        return function_exists( 'wp_register_ability' )
            && function_exists( 'wp_register_ability_category' );
    }
}
