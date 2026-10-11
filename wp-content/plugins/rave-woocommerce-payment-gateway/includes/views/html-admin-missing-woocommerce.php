<?php
/**
 * Missing WooCommerce notice.
 *
 * @package Flutterwave WooCommerce
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="notice notice-error" style='text-align: center'>
	<p>
		<?php
		// Translators: %s Plugin name.
		printf( esc_html__( '%s requires WooCommerce to be installed and activated in order to serve updates.', 'rave-woocommerce-payment-gateway' ), '<strong>' . esc_html__( 'Flutterwave WooCommerce', 'rave-woocommerce-payment-gateway' ) . '</strong>' );
		?>
	</p>

	<?php if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) && current_user_can( 'activate_plugin', 'woocommerce/woocommerce.php' ) ) : ?>
		<p>
			<?php
			$installed_plugins = get_plugins();
			if ( isset( $installed_plugins['woocommerce/woocommerce.php'] ) ) :
				?>
				<a href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php&plugin_status=active' ), 'activate-plugin_woocommerce/woocommerce.php' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Activate WooCommerce', 'rave-woocommerce-payment-gateway' ); ?></a>
			<?php endif; ?>
			<?php if ( current_user_can( 'deactivate_plugin', 'rave-woocommerce-payment-gateway/rave-woocommerce-payment-gateway.php' ) ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( 'plugins.php?action=deactivate&plugin=rave-woocommerce-payment-gateway/rave-woocommerce-payment-gateway.php&plugin_status=inactive', 'deactivate-plugin_rave-woocommerce-payment-gateway/rave-woocommerce-payment-gateway.php' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Turn off Flutterwave WooCommerce', 'rave-woocommerce-payment-gateway' ); ?></a>
			<?php endif; ?>
		</p>
	<?php else : ?>
		<?php
		if ( current_user_can( 'install_plugins' ) ) {
			$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' );
		} else {
			$url = 'http://wordpress.org/plugins/woocommerce/';
		}
		?>
		<p>
			<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Install WooCommerce', 'rave-woocommerce-payment-gateway' ); ?></a>
			<?php if ( current_user_can( 'deactivate_plugin', 'rave-woocommerce-payment-gateway/rave-woocommerce-payment-gateway.php' ) ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( 'plugins.php?action=deactivate&plugin=rave-woocommerce-payment-gateway/rave-woocommerce-payment-gateway.php&plugin_status=inactive', 'deactivate-plugin_rave-woocommerce-payment-gateway/rave-woocommerce-payment-gateway.php' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Turn off Flutterwave WooCommerce', 'rave-woocommerce-payment-gateway' ); ?></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</div>
