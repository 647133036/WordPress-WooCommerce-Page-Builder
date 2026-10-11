<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Create a helper function for easy SDK access.
function dgoraAsfwFs() {
    global $dgoraAsfwFs;
    if ( !isset( $dgoraAsfwFs ) ) {
        // Include Freemius SDK.
        // Freemius SDK could be loaded via Composer autoload, but we disabled that with tools/fix-freemius.php.
        require_once dirname( dirname( __FILE__ ) ) . '/vendor/freemius/wordpress-sdk/start.php';
        // Escape request values that appear in Freemius sticky admin notices.
        // The SDK stores these notices during fs_dynamic_init(), so the filters must be registered before it.
        $dgoraAsfwEscapeFsNotice = function ( $message, array $keys ) {
            $pairs = array();
            foreach ( $keys as $key ) {
                $value = fs_request_get( $key, '' );
                if ( !is_string( $value ) || $value === '' ) {
                    continue;
                }
                // If a request value carries any markup, drop the request-derived content entirely
                // instead of trying to escape a crafted message in place.
                if ( strpbrk( $value, '<>' ) !== false ) {
                    return esc_html__( 'A purchase notification from Freemius was hidden because it contained unexpected content.', 'ajax-search-for-woocommerce' );
                }
                $pairs[$value] = esc_html( $value );
            }
            // strtr() replaces every pair in a single pass and never re-scans already-replaced text,
            // so overlapping values cannot leave one of them unescaped (unlike chained str_replace()).
            return ( empty( $pairs ) ? $message : strtr( $message, $pairs ) );
        };
        add_filter( 'fs_sticky_message_plan_purchased_ajax-search-for-woocommerce', function ( $message ) use($dgoraAsfwEscapeFsNotice) {
            return $dgoraAsfwEscapeFsNotice( $message, array('purchased_plan', 'purchase_email') );
        } );
        add_filter( 'fs_sticky_message_activation_pending_ajax-search-for-woocommerce', function ( $message ) use($dgoraAsfwEscapeFsNotice) {
            return $dgoraAsfwEscapeFsNotice( $message, array('user_email', 'support_email_address') );
        } );
        // Activate multisite network integration.
        if ( !defined( 'WP_FS__PRODUCT_700_MULTISITE' ) ) {
            define( 'WP_FS__PRODUCT_700_MULTISITE', true );
        }
        $dgoraAsfwFs = fs_dynamic_init( array(
            'id'               => '700',
            'slug'             => 'ajax-search-for-woocommerce',
            'type'             => 'plugin',
            'public_key'       => 'pk_f4f2a51dbe0aee43de0692db77a3e',
            'is_premium'       => false,
            'premium_suffix'   => 'Pro',
            'has_addons'       => false,
            'has_paid_plans'   => true,
            'menu'             => array(
                'slug'        => 'dgwt_wcas_settings',
                'parent'      => array(
                    'slug' => 'woocommerce',
                ),
                'account'     => false,
                'contact'     => false,
                'support'     => false,
                'pricing'     => false,
                'affiliation' => false,
            ),
            'is_live'          => true,
            'is_org_compliant' => true,
        ) );
    }
    return $dgoraAsfwFs;
}

// Init Freemius.
dgoraAsfwFs();
// Signal that SDK was initiated.
do_action( 'dgoraAsfwFs_loaded' );
dgoraAsfwFs()->add_filter( 'plugin_icon', function () {
    return dirname( dirname( __FILE__ ) ) . '/assets/img/logo-128.png';
} );
if ( !dgoraAsfwFs()->is_premium() ) {
    dgoraAsfwFs()->add_action( 'after_uninstall', function () {
        if ( !class_exists( '\\DgoraWcas\\Uninstall' ) ) {
            require_once __DIR__ . '/../includes/Uninstall.php';
        }
        \DgoraWcas\Uninstall::afterUninstall();
    } );
}