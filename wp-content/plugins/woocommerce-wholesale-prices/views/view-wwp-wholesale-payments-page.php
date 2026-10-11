<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly.

$is_wpay_installed = WWP_Helper_Functions::is_wpay_installed();
$is_wpay_active    = WWP_Helper_Functions::is_wpay_active();
$wpay_plugin_file  = 'woocommerce-wholesale-payments/woocommerce-wholesale-payments.php';

$activate_plugin_url = wp_nonce_url(
	sprintf( 'plugins.php?action=activate&plugin=%s&plugin_status=all&s&activate_wpay=1', $wpay_plugin_file ),
	sprintf( 'activate-plugin_%s', $wpay_plugin_file )
);

$wpay_tracking_url = WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-payments', 'wwp', 'upsell', 'wpaypage' );

?>
<div id="wwp-payments-page">

	<!-- Hero Section -->
	<div class="hero" style="background-image: url(<?php echo esc_url_raw( WWP_IMAGES_URL ); ?>payments/background-image.png)">

		<div id="logo">
			<img
				id="logo-image"
				src="<?php echo esc_url_raw( WWP_IMAGES_URL ); ?>/logo.png"
				alt="<?php esc_attr_e( 'Wholesale Payments Icon', 'woocommerce-wholesale-prices' ); ?>"
			/>
		</div>

		<div id="title">
			<h2>
				<?php esc_html_e( 'Offer', 'woocommerce-wholesale-prices' ); ?> 
				<span id="installments"><?php esc_html_e( 'payment installments', 'woocommerce-wholesale-prices' ); ?></span> 
				<?php esc_html_e( 'effortlessly on your WooCommerce store!', 'woocommerce-wholesale-prices' ); ?>
			</h2>
		</div>

		<div id="gift-box">
			<img
				class="gift-box-image"
				src="<?php echo esc_url_raw( WWP_IMAGES_URL ); ?>payments/gift-box.png"
				alt="<?php esc_attr_e( 'Gift Box Image', 'woocommerce-wholesale-prices' ); ?>"
			/>
		</div>
	</div>

    <div id="features">
		<div class="feature">
			<img
				class="feature-icon"
				src="<?php echo esc_url_raw( WWP_IMAGES_URL ); ?>payments/fluent-emoji-money-with-wings.png"
				alt="<?php esc_attr_e( 'Money With Wings Icon', 'woocommerce-wholesale-prices' ); ?>"
			/>
			<span>
				<?php esc_html_e( 'Easily offer Payment Plans', 'woocommerce-wholesale-prices' ); ?>
			</span>
		</div>

		<div class="feature">
			<img
				class="feature-icon"
				src="<?php echo esc_url_raw( WWP_IMAGES_URL ); ?>payments/fluent-emoji-credit-card.png"
				alt="<?php esc_attr_e( 'Credit Card Icon', 'woocommerce-wholesale-prices' ); ?>"
			/>
			<span>
				<?php
                esc_html_e(
                    'Offer NET 30/60/90',
                    'woocommerce-wholesale-prices'
                );
?>
			</span>
		</div>

		<div class="feature">
			<img
				class="feature-icon"
				src="<?php echo esc_url_raw( WWP_IMAGES_URL ); ?>payments/fluent-emoji-spiral-notepad.png"
				alt="<?php esc_attr_e( 'Notepad Icon', 'woocommerce-wholesale-prices' ); ?>"
			/>
			<span>
				<?php
                esc_html_e(
                    'Easy Invoice Management',
                    'woocommerce-wholesale-prices'
                );
?>
			</span>
		</div>

		<div class="feature">
			<img
				class="feature-icon"
				src="<?php echo esc_url_raw( WWP_IMAGES_URL ); ?>payments/fluent-emoji-flexed-biceps.png"
				alt="<?php esc_attr_e( 'Flexed Biceps Icon', 'woocommerce-wholesale-prices' ); ?>"
			/>
			<span>
				<?php
                esc_html_e(
                    'Flexible Payment Options',
                    'woocommerce-wholesale-prices'
                );
?>
			</span>
		</div>
	</div>

	<div id="description">
		<h3>
			<?php
			esc_html_e(
				"Empower your wholesale customers with customizable payment plans-offering flexibility and convenience that sets your business apart. Save time & gain visibility into your store's finances with seamless invoice management.",
				'woocommerce-wholesale-prices'
			);
			?>
		</h3>
	</div>
    
	<div id="steps">
		<div class="step">
			<div class="step-number <?php echo $is_wpay_installed ? 'inactive' : 'current'; ?>">
				<?php esc_html_e( 'Step 1', 'woocommerce-wholesale-prices' ); ?>
			</div>

			<div class="step-title <?php echo $is_wpay_installed ? 'inactive' : 'active'; ?>">
				<h3><?php esc_html_e( 'Install Wholesale Payments', 'woocommerce-wholesale-prices' ); ?></h3>
			</div>

			<hr class="step-divider" />

			<div class="step-description">
				<span>
					<?php esc_html_e( 'Tailor payment structures seamlessly with Wholesale Payments. Create custom plans like NET 30 or initial 50%, offering wholesale flexibility.', 'woocommerce-wholesale-prices' ); ?>
				</span>
				<span>
					<?php
                    esc_html_e( "Craft terms that resonate fixed or percentage based with Wholesale Payments. Set your business apart by understanding and meeting your clients' needs", 'woocommerce-wholesale-prices' );
                    ?>
				</span>
			</div>

			<div id="actions">
				<a href="<?php echo ! $is_wpay_installed ? esc_url_raw( $wpay_tracking_url ) : '#'; ?>" class="step-button <?php echo $is_wpay_installed ? 'inactive disabled' : 'active'; ?>">
					<span>
						<?php esc_html_e( 'Get Wholesale Payments', 'woocommerce-wholesale-prices' ); ?>
					</span>
				</a>
			</div>
		</div>

		<div class="step">
			<div class="step-number <?php echo ! $is_wpay_active && $is_wpay_installed ? 'current' : 'inactive'; ?>">
				<?php esc_html_e( 'Step 2', 'woocommerce-wholesale-prices' ); ?>
			</div>

			<div class="step-title <?php echo ! $is_wpay_active && $is_wpay_installed ? 'active' : 'inactive'; ?>">
				<h3><?php esc_html_e( 'Configure Payment Plans', 'woocommerce-wholesale-prices' ); ?></h3>
			</div>

			<hr class="step-divider" />

			<div class="step-description">
				<span>
					<?php
                    esc_html_e(
                        ' Begin by selecting from pre-configured templates, simplifying the setup process while offering versatility to your clients.',
                        'woocommerce-wholesale-prices'
                    );
					?>
				</span>
				<span>
					<?php
                    esc_html_e(
						"Customize plan visibility to match customers' brand identity and expectations, empowering them to adjust seamlessly for a cohesive shopping experience that resonates with their audience.",
						'woocommerce-wholesale-prices'
					);
                    ?>
				</span>
			</div>

			<div id="actions">
				<a
					href="<?php echo ! $is_wpay_installed ? '#' : esc_url_raw( $activate_plugin_url ); ?>"
					class="step-button <?php echo ! $is_wpay_active && $is_wpay_installed ? 'active' : 'inactive disabled'; ?>">
					<span>
						<?php esc_html_e( 'Activate Plugin', 'woocommerce-wholesale-prices' ); ?>
					</span>
				</a>
			</div>
		</div>
	</div>
</div>
