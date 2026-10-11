<?php
/*
	Plugin Name: FOX - Currency Switcher Professional for WooCommerce
	Plugin URI: https://currency-switcher.com/
	Description: Currency Switcher for WooCommerce that allows to the visitors and customers on your woocommerce store site switch currencies and optionally apply selected currency on checkout
	Author: realmag777
	Version: 1.5.4
	Requires at least: 6.0
	Tested up to: 7.1
	Requires PHP: 7.4
	Text Domain: woocommerce-currency-switcher
	Domain Path: /languages
	Forum URI: https://pluginus.net/support/forum/woocs-woocommerce-currency-switcher-multi-currency-and-multi-pay-for-woocommerce/
	Author URI: https://pluginus.net/
	WC requires at least: 6.0
	WC tested up to: 11.1
	Requires Plugins: woocommerce
	License: GPL-2.0-or-later
	License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( isset( $_GET['woocommerce_gpf'] ) ) {
	return false;
}

// Optional Freemius integration: load only if the bootstrap file is present.
// For marketplace builds (woo.com, Envato) just delete /freemius and freemius.php — this is skipped.
$freemius_bootstrap = dirname( __FILE__ ) . '/freemius.php';
if ( file_exists( $freemius_bootstrap ) ) {
    require_once $freemius_bootstrap;
}

// disable FOX influence for REST api requests
if ( isset( $_SERVER['SCRIPT_URI'] ) ) {
	$uri = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['SCRIPT_URI'] ) ) );
	$uri = explode( '/', trim( $uri['path'], ' /' ) );
	if ( $uri[0] === 'wp-json' ) {
		$show_legacy = array( 'widget-types', 'sidebars', 'widgets', 
			'batch', 'collection-data', 'cart', 'store' );
		$match       = array_intersect( $show_legacy, $uri );

		if ( count( $match ) == 0 ) {
			$allow = array( 'woocs', 'divi-ajax-filter', 'bricks' );
			
			$extra = get_option( 'woocs_rest_allow_namespaces', '' );
			if ( $extra ) {
				$extra = array_filter( array_map( 'trim', explode( ',', $extra ) ) );
				$allow = array_merge( $allow, $extra );
			}
			
			if ( isset( $uri[1] ) and ! in_array( $uri[1], $allow ) ) {
				return; // !!it is important for different reports to exclude FOX influence
			}
		}
	}
}


if ( defined( 'DOING_AJAX' ) ) {

	add_action( 'wp_ajax_woocommerce_refund_line_items', function() {
		//https://pluginus.net/support/topic/unable-to-refund-order-invalid-refund-amount/
		if ( isset( $_POST['refund_amount'] ) ) {
			$_POST['refund_amount'] = str_replace( ',', '.', wp_unslash( $_POST['refund_amount'] ) );
		}
	}, 1 );

	if ( isset( $_REQUEST['action'] ) ) {
		// do not recalculate refund amounts when we are in order backend
		if ( $_REQUEST['action'] == 'woocommerce_refund_line_items' ) {
			if ( ! class_exists( 'WooCommerce_PDF_IPS_Pro' ) && ! class_exists( 'WC_Smart_Coupons' ) && ! class_exists( 'ACFWF' ) ) {
				return;
			}

			if ( apply_filters( 'woocs_disable_backend_refund_calculation', false ) ) {
				return;
			}
		}

		if ( isset( $_REQUEST['order_id'] ) and $_REQUEST['action'] == 'woocommerce_load_order_items' ) {
			return;
		}

		//fix for BEAR plugin
		if ( strpos($_REQUEST['action'], 'woobe') !== false ) {
			return;
		}
	}
}

// fix for WooCommerce PayPal Payments
if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
	$rest_route = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	if ( strpos( $rest_route, '/refunds' ) !== false || strpos( $rest_route, 'paypal' ) !== false ) {
		return;
	}
}

define( 'WOOCS_VERSION', '1.5.4' );
// define('WOOCS_VERSION', uniqid('woocs-'));//for dev test purposes to reset browser cache
define( 'WOOCS_MIN_WOOCOMMERCE', '6.0' );
define( 'WOOCS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOOCS_LINK', plugin_dir_url( __FILE__ ) );
define( 'WOOCS_PLUGIN_NAME', plugin_basename( __FILE__ ) );

// classes
require_once WOOCS_PATH . 'classes/woocs_session.php';
require_once WOOCS_PATH . 'classes/storage.php';
require_once WOOCS_PATH . 'classes/cron.php';
require_once WOOCS_PATH . 'classes/alert.php';
require_once WOOCS_PATH . 'classes/smart-designer.php';
require_once WOOCS_PATH . 'classes/fixed/fixed_amount.php';
require_once WOOCS_PATH . 'classes/fixed/fixed_price.php';
require_once WOOCS_PATH . 'classes/statistic.php';
require_once WOOCS_PATH . 'classes/reports.php';
require_once WOOCS_PATH . 'classes/dashboard_stat.php';
require_once WOOCS_PATH . 'classes/profiles.php';
require_once WOOCS_PATH . 'classes/compatibility/compatibility.php';
require_once WOOCS_PATH . 'classes/woocs_hpos.php';
require_once WOOCS_PATH . 'classes/world_currencies.php';

// 18-09-2026 - dd-mm-YYYY
class WOOCS_STARTER {

	private $default_woo_version   = 6.0;
	private $actualized            = 0.0;
	private $version_key           = 'woocs_woo_version';
	private $_woocs                = null;
	public $disable_plugin         = array(); // add a slug of the  page  to  disble  the plugin. Example: 'account','cart'
	public $reverse_disable_plugin = 0; // set: true - to activate the plugin  on exact  pages

	public function __construct() {
		$this->actualized = floatval( get_option( $this->version_key, $this->default_woo_version ) );
		$apl              = get_option( 'woocs_activate_page_list', '' );
		if ( $apl ) {
			$this->disable_plugin = array_map( 'trim', explode( ',', $apl ) );
		} else {
			$this->disable_plugin = array();
		}
		$this->reverse_disable_plugin = get_option( 'woocs_activate_page_list_reverse', 1 );
	}

	public function update_version() {
		if ( defined( 'WOOCOMMERCE_VERSION' ) and ( $this->actualized !== floatval( WOOCOMMERCE_VERSION ) ) ) {
			update_option( 'woocs_woo_version', WOOCOMMERCE_VERSION );
		}
	}

	public function get_actual_obj() {

		if ( count( $this->disable_plugin ) and ! is_admin() and ( isset( $_SERVER['SCRIPT_URI'] ) || isset( $_SERVER['REQUEST_URI'] ) ) ) {
			$exclude = false;
			$url     = false;
			if ( isset( $_SERVER['SCRIPT_URI'] ) ) {
				$url = $_SERVER['SCRIPT_URI'];
			}

			if ( ! $url ) {
				$url = explode( '?', $_SERVER['REQUEST_URI'] );
				$url = $url[0];
				$url = explode( '/page/', $_SERVER['REQUEST_URI'] );
				$url = $url[0];
			}
			if ( preg_match( '/\/([-a-zA-Z0-9_]+)[\/]$/', $url, $matches ) ) {

				$exclude = in_array( $matches[1], $this->disable_plugin );
			} elseif ( in_array( '', $this->disable_plugin ) ) {
				$exclude = true;
			}

			if ( $this->reverse_disable_plugin ) {
				$exclude = ! $exclude;
			}

			// do not exclude in widget page
			if ( isset( $_SERVER['SCRIPT_URI'] ) ) {
				$uri = wp_parse_url( trim( $_SERVER['SCRIPT_URI'] ) );

				$uri = explode( '/', trim( $uri['path'], ' /' ) );
				if ( $uri[0] === 'wp-json' ) {
					$show_legacy = array( 'widget-types', 'sidebars', 'widgets', 'batch' );
					$match       = array_intersect( $show_legacy, $uri );
					if ( count( $match ) != 0 ) {
						$exclude = false;
					}
				}
			}

			if ( $exclude ) {
				return false;
			}
		}

		if ( $this->_woocs != null ) {
			return $this->_woocs;
		}

		include_once WOOCS_PATH . 'classes/Rates/ExchangeRateLimiter.php';
		include_once WOOCS_PATH . 'classes/woocs.php'; // woocs_after_33.php
		include_once WOOCS_PATH . 'classes/fixed/fixed_coupon.php';
		include_once WOOCS_PATH . 'classes/fixed/fixed_shipping.php';
		include_once WOOCS_PATH . 'classes/fixed/fixed_shipping_free.php';
		include_once WOOCS_PATH . 'classes/fixed/fixed_user_role.php';
		include_once WOOCS_PATH . 'classes/auto_switcher.php';
		include_once WOOCS_PATH . 'classes/analytics.php';

		$this->_woocs = new WOOCS();
		return $this->_woocs;
	}
}

// +++
if ( isset( $_GET['P3_NOCACHE'] ) ) {
	// stupid trick for that who believes in P3
	return;
}

// +++
// fix: because of long id which prevent js script working
function woocs_short_id( $smth ) {
	return substr( md5( $smth ), 1, 7 );
}

// +++
$WOOCS_STARTER = new WOOCS_STARTER();

$WOOCS = $WOOCS_STARTER->get_actual_obj();
if ( $WOOCS ) {
	$GLOBALS['WOOCS'] = $WOOCS;
	add_action( 'init', array( $WOOCS, 'init' ), 11 );
}

// ****
// rate + interes
add_filter(
	'woocs_currency_data_manipulation',
	function ( $currencies ) {
		foreach ( $currencies as $key => $value ) {
			if ( isset( $value['rate_plus'] ) ) {
				$interes = 0;
				if ( ! strpos( $value['rate_plus'], '%' ) ) {
					$interes = floatval( $value['rate_plus'] );
				} else {
					// example: 20%
					$interes = floatval( floatval( $value['rate'] ) / 100 ) * intval( $value['rate_plus'] );
				}
				$currencies[ $key ]['rate'] = floatval( $value['rate'] ) + $interes;
			}
		}

		return $currencies;
	},
	1,
	1
);

// hide WOOCS meta in the order
add_filter(
	'woocommerce_order_item_get_formatted_meta_data',
	function ( $formatted_meta ) {
		foreach ( $formatted_meta as $key => $meta ) {
			if ( in_array( $meta->key, array( '_woocs_order_rate', '_woocs_order_base_currency', '_woocs_order_currency_changed_mannualy' ) ) ) {
				unset( $formatted_meta[ $key ] );
			}
		}
		return $formatted_meta;
	},
	10,
	1
);

// for bots
function woocs_is_bot( &$botname = '' ) {
	$bots = array(
		'rambler',
		'googlebot',
		'aport',
		'yahoo',
		'msnbot',
		'turtle',
		'mail.ru',
		'omsktele',
		'yetibot',
		'picsearch',
		'sape.bot',
		'sape_context',
		'gigabot',
		'snapbot',
		'alexa.com',
		'megadownload.net',
		'askpeter.info',
		'igde.ru',
		'ask.com',
		'qwartabot',
		'yanga.co.uk',
		'scoutjet',
		'similarpages',
		'oozbot',
		'shrinktheweb.com',
		'aboutusbot',
		'followsite.com',
		'dataparksearch',
		'google-sitemaps',
		'appEngine-google',
		'feedfetcher-google',
		'liveinternet.ru',
		'xml-sitemaps.com',
		'agama',
		'metadatalabs.com',
		'h1.hrn.ru',
		'googlealert.com',
		'seo-rus.com',
		'yaDirectBot',
		'yandeG',
		'yandex',
		'yandexSomething',
		'Copyscape.com',
		'AdsBot-Google',
		'domaintools.com',
		'Nigma.ru',
		'bing.com',
		'dotnetdotcom',
	);

	$HTTP_USER_AGENT = '';
	if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'];
	}
	foreach ( $bots as $bot ) {
		if ( stripos( $HTTP_USER_AGENT, $bot ) !== false ) {
			$botname = $bot;
			return true;
		}
	}

	return false;
}

add_action(
	'wp_head',
	function () {
		if ( woocs_is_bot() && ! get_option( 'woocs_disable_reset_currency_bots', 0 ) ) {
			if ( class_exists( 'WOOCS' ) ) {
				global $WOOCS;
				$WOOCS->reset_currency();
			}
		}
	},
	1
);

// for separators
add_filter(
	'option_woocommerce_price_thousand_sep',
	function ( $value ) {

		// Keep the native store separator inside the wp-admin order editor.
		if ( woocs_is_admin_order_edit_context() ) {
			return $value;
		}

		global $WOOCS;

		if ( is_object( $WOOCS ) ) {
			$current_currency = $WOOCS->get_woocommerce_currency();
			$value            = $WOOCS->get_thousand_sep( $current_currency, $value );
		}

		return $value;
	}
);

// for separators
add_filter(
	'option_woocommerce_price_decimal_sep',
	function ( $value ) {

		// Keep the native store separator inside the wp-admin order editor.
		if ( woocs_is_admin_order_edit_context() ) {
			return $value;
		}


		global $WOOCS;

		if ( is_object( $WOOCS ) ) {
			$current_currency = $WOOCS->get_woocommerce_currency();
			$value            = $WOOCS->get_decimal_sep( $current_currency, $value );
		}

		return $value;
	}
);

add_filter(
	'woocommerce_product_export_product_query_args',
	function ( $args ) {
		global $WOOCS;

		if ( is_object( $WOOCS ) && $WOOCS->current_currency != $WOOCS->default_currency ) {
			$WOOCS->reset_currency();
		}
		return $args;
	}
);

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

function woocs_validate_currency( $currency ) {
	global $WOOCS;
	$currency      = strtoupper( sanitize_text_field( $currency ) );
	$currencies    = $WOOCS->get_currencies();
	$currency_keys = array_map( 'strtoupper', array_keys( $currencies ) );
	if ( in_array( $currency, $currency_keys ) ) {
		return $currency;
	}
	return strtoupper( $WOOCS->default_currency );
}

// Detect the wp-admin ORDER editor (edit screen + its AJAX endpoints).
// Inside it WooCommerce's editable amount fields, their JS validation and
// wc_format_decimal() all assume the single store separator, so FOX must NOT
// override separators there (otherwise saved amounts get corrupted, e.g. x100).
if ( ! function_exists( 'woocs_is_admin_order_edit_context' ) ) {
	/**
	 * Detect the wp-admin ORDER context: the order edit and list screens
	 * plus the AJAX requests dispatched from them.
	 *
	 * Two consumers rely on this:
	 * 1) the separator filters - WooCommerce's editable amount fields, their JS
	 *    validation and wc_format_decimal() all assume the single store separator,
	 *    so FOX must not override separators there;
	 * 2) raw_woocommerce_price() in non-multiple mode - the stored order amounts
	 *    must be shown as they are, without the display-only conversion.
	 *
	 * @return bool
	 */
	function woocs_is_admin_order_edit_context() {

		if ( ! is_admin() ) {
			return false;
		}

		// The request-based part never changes within one request, so it is
		// resolved once. The screen check below is intentionally left out of
		// the cache: the screen is set up later than the first call here.
		static $request_context = null;

		if ( null === $request_context ) {

			$request_context = false;

			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {

				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

				// Actions that exist only inside the order editor.
				$order_ajax = array(
					'woocommerce_save_order_items',
					'woocommerce_calc_line_taxes',
					'woocommerce_add_order_item',
					'woocommerce_remove_order_item',
					'woocommerce_load_order_items',
					'woocommerce_add_order_fee',
					'woocommerce_add_order_shipping',
					'woocommerce_add_order_tax',
					'woocommerce_remove_order_tax',
					'woocommerce_add_coupon_discount',
					'woocommerce_remove_order_coupon',
					'woocommerce_refund_line_items',
					'woocommerce_delete_refund',
					'woocommerce_get_order_details',
					'woocommerce_grant_access_to_download',
					'woocommerce_revoke_access_to_download',
				);

				if ( in_array( $action, $order_ajax, true ) ) {
					$request_context = true;
				}

				// Anything else fired from an order screen - including the
				// product/customer search modals, which are also used on other
				// screens and therefore must not be trusted by action name alone.
				if ( ! $request_context ) {
					$referer = wp_get_referer();

					if ( ! empty( $referer ) ) {
						$query = wp_parse_url( $referer, PHP_URL_QUERY );

						if ( ! empty( $query ) ) {
							$args = array();
							wp_parse_str( $query, $args );

							if ( isset( $args['page'] ) && 0 === strpos( $args['page'], 'wc-orders' ) ) {
								$request_context = true;
							} elseif ( isset( $args['post_type'] ) && in_array( $args['post_type'], array( 'shop_order', 'shop_order_refund' ), true ) ) {
								$request_context = true;
							} elseif ( isset( $args['post'] ) && 'shop_order' === get_post_type( intval( $args['post'] ) ) ) {
								$request_context = true;
							}
						}
					}
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended

			} else {

				// phpcs:disable WordPress.Security.NonceVerification.Recommended

				// HPOS screens: admin.php?page=wc-orders and its order subtypes
				// (wc-orders--shop_order_refund and so on).
				$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
				if ( '' !== $page && 0 === strpos( $page, 'wc-orders' ) ) {
					$request_context = true;
				}

				// Classic list screen: edit.php?post_type=shop_order.
				// $_REQUEST is used on purpose: the classic order save is a POST
				// to post.php with action=editpost, so $_GET is empty there.
				if ( ! $request_context ) {
					$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['post_type'] ) ) : '';
					if ( in_array( $post_type, array( 'shop_order', 'shop_order_refund' ), true ) ) {
						$request_context = true;
					}
				}

				// Classic edit screen and its save: post.php?post=ID&action=edit
				// carries post, the save carries post_ID.
				if ( ! $request_context ) {
					$post_id = 0;

					if ( isset( $_REQUEST['post_ID'] ) ) {
						$post_id = intval( $_REQUEST['post_ID'] );
					} elseif ( isset( $_REQUEST['post'] ) ) {
						$post_id = intval( $_REQUEST['post'] );
					}

					if ( $post_id > 0 && in_array( get_post_type( $post_id ), array( 'shop_order', 'shop_order_refund' ), true ) ) {
						$request_context = true;
					}
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
			}
		}

		if ( $request_context ) {
			return true;
		}

		// Screen check. Evaluated on every call because get_current_screen()
		// returns nothing until the current_screen hook has run, while this
		// function is called much earlier than that.
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( is_object( $screen ) && ! empty( $screen->id ) ) {

				if ( in_array( $screen->id, array( 'shop_order', 'edit-shop_order' ), true ) ) {
					return true;
				}

				if ( false !== strpos( $screen->id, 'wc-orders' ) ) {
					return true;
				}

				global $WOOCS;

				if ( is_object( $WOOCS ) && is_object( $WOOCS->woocs_hpos ) ) {
					$order_screen = $WOOCS->woocs_hpos->getOrderScreenId();

					if ( ! empty( $order_screen ) && $screen->id === $order_screen ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
