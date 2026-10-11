<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class WPCleverWoobt_Blocks_IntegrationInterface implements IntegrationInterface {
	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'woobt-blocks';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		wp_enqueue_style(
			'woobt-blocks',
			$this->get_url( 'blocks', 'css' ),
			[],
			WOOBT_VERSION
		);

		wp_register_script(
			'woobt-blocks',
			$this->get_url( 'blocks', 'js' ),
			[ 'wc-blocks-checkout' ],
			WOOBT_VERSION,
			true
		);

		wp_set_script_translations(
			'woobt-blocks',
			'woo-bought-together',
			WOOBT_DIR . 'languages'
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return [ 'woobt-blocks' ];
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return [];
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return [];
	}

	public function get_url( $file, $ext ) {
		return plugins_url( $this->get_path( $ext ) . $file . '.' . $ext, WOOBT_FILE );
	}

	protected function get_path( $ext ) {
		return 'css' === $ext ? 'assets/css/' : 'assets/js/';
	}
}

if ( ! class_exists( 'WPCleverWoobt_Blocks' ) ) {
	class WPCleverWoobt_Blocks {
		function __construct() {
			add_filter( 'rest_request_after_callbacks', [ $this, 'cart_item_data' ], 10, 3 );
			add_filter( 'woocommerce_hydration_request_after_callbacks', [ $this, 'cart_item_data' ], 10, 3 );
			add_action(
				'woocommerce_blocks_mini-cart_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new WPCleverWoobt_Blocks_IntegrationInterface() );
				}
			);
			add_action(
				'woocommerce_blocks_cart_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new WPCleverWoobt_Blocks_IntegrationInterface() );
				}
			);
			add_action(
				'woocommerce_blocks_checkout_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new WPCleverWoobt_Blocks_IntegrationInterface() );
				}
			);
		}

		function cart_item_data( $response, $server, $request ) {
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( ! str_contains( $request->get_route(), 'wc/store' ) ) {
				return $response;
			}

			$data = $response->get_data();

			if ( empty( $data['items'] ) ) {
				return $response;
			}

			if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
				return $response;
			}

			$cart_contents = WC()->cart->get_cart();
			$cart_quantity = WPCleverWoobt_Helper()->get_setting( 'cart_quantity', 'yes' ) !== 'no';

			foreach ( $data['items'] as &$item_data ) {
				$cart_item_key = $item_data['key'];
				$cart_item     = $cart_contents[ $cart_item_key ] ?? null;

				if ( ! empty( $cart_item['woobt_ids'] ) ) {
					$item_data['woobt_main'] = true;
				}

				if ( ! empty( $cart_item['woobt_parent_id'] ) ) {
					$parent_id                 = apply_filters( 'woobt_parent_id', $cart_item['woobt_parent_id'], $cart_item );
					$item_data['woobt_linked'] = true;
					$item_data['name']         .= ' ' . sprintf( WPCleverWoobt_Helper()->localization( 'associated', /* translators: product name */ esc_html__( '(bought together %s)', 'woo-bought-together' ) ), esc_html( get_the_title( $parent_id ) ) );

					if ( ! $cart_quantity || ! empty( $cart_item['woobt_sync_qty'] ) ) {
						$item_data['quantity_limits']->editable = false;
					}
				}
			}

			$response->set_data( $data );

			return $response;
		}
	}

	new WPCleverWoobt_Blocks();
}