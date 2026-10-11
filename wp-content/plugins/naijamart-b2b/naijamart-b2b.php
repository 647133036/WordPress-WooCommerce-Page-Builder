<?php
/**
 * Plugin Name: NaijaMart B2B Toolkit
 * Description: Wholesale registration with approval workflow, MOQ enforcement, and B2B account indicators for NaijaMart Commerce.
 * Version: 1.0.0
 * Author: MonkeyCode AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NaijaMart_B2B {

	const MIN_WHOLESALE_ORDER_TOTAL = 100000; // NGN

	public function __construct() {
		add_shortcode( 'b2b_registration_form', array( $this, 'registration_form' ) );
		add_action( 'init', array( $this, 'handle_registration' ) );

		// Admin approval UI on user profile
		add_action( 'show_user_profile', array( $this, 'approval_field' ) );
		add_action( 'edit_user_profile', array( $this, 'approval_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_approval' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_approval' ) );

		// Users list column
		add_filter( 'manage_users_columns', array( $this, 'users_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'users_column_value' ), 10, 3 );

		// MOQ enforcement for wholesale carts
		add_action( 'woocommerce_check_cart_items', array( $this, 'enforce_moq' ) );

		// Global storefront styles
		add_action( 'wp_head', array( $this, 'global_styles' ), 99 );

		// Wholesale status indicator in My Account
		add_action( 'woocommerce_account_dashboard', array( $this, 'account_status' ) );
	}

	public function global_styles() {
		?>
<style>
:root{--nm-navy:#0a3d62;--nm-amber:#f0a500}
body{font-family:'Inter','Helvetica Neue',Arial,sans-serif;color:#1f2933}
a{color:var(--nm-navy)}
.ast-primary-header-bar{background:#0a3d62 !important}
.ast-primary-header-bar .menu-item>.menu-link{color:#fff !important;font-weight:600}
.ast-primary-header-bar .site-title,.ast-primary-header-bar .site-title a{color:#fff !important}
.ast-primary-header-bar .menu-item>.menu-link:hover{color:#f0a500 !important}
.ast-primary-header-bar .ast-header-custom-item .button{background:#f0a500 !important;color:#111 !important}
button,.button,.wp-element-button,button.single_add_to_cart_button,.woocommerce a.button,.woocommerce button.button,.woocommerce #respond input#submit{background:var(--nm-navy) !important;color:#fff !important;border-radius:8px !important;font-weight:600 !important}
button:hover,.button:hover,.woocommerce a.button:hover,.woocommerce button.button:hover{background:#155a8a !important}
.woocommerce ul.products li.product{border:1px solid #eef0f2;border-radius:12px;padding:12px;transition:box-shadow .2s,transform .2s}
.woocommerce ul.products li.product:hover{box-shadow:0 10px 28px rgba(10,61,98,.12);transform:translateY(-3px)}
.woocommerce ul.products li.product img{border-radius:8px}
.woocommerce span.onsale{background:var(--nm-amber) !important;color:#111 !important;border-radius:20px !important;padding:4px 14px !important;min-height:0;line-height:1.6}
.woocommerce ul.products li.product .price,.woocommerce div.product p.price{color:var(--nm-navy);font-weight:700}
.woocommerce ul.products li.product .price del{opacity:.5;font-weight:400}
.ast-footer-copyright,.site-footer,.ast-footer-overlay{background:#0a3d62 !important;color:#dbe7f3}
.site-footer a{color:#f0a500}
@media(max-width:768px){.woocommerce ul.products li.product{width:46% !important;margin:2% !important}}
/* Force boxed centered layout with side margins */
@media(min-width:922px){
.site #content>.ast-container,.site-content .ast-container,.ast-container{max-width:1200px !important;margin-left:auto !important;margin-right:auto !important;padding-left:20px !important;padding-right:20px !important}
.elementor-page .site #content>.ast-container{padding-left:0 !important;padding-right:0 !important}
}
</style>
		<?php
	}

	public function registration_form() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( in_array( 'wholesale_customer', (array) $user->roles, true ) ) {
				return '<p><strong>Your account already has wholesale access.</strong> Visit the <a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">shop</a> to see wholesale pricing.</p>';
			}
			$status = get_user_meta( $user->ID, 'b2b_application_status', true );
			if ( 'pending' === $status ) {
				return '<p><strong>Your wholesale application is pending review.</strong> We typically approve applications within 24 hours.</p>';
			}
			return $this->render_form( $user->user_email );
		}
		return $this->render_form();
	}

	private function render_form( $prefill_email = '' ) {
		$notice = '';
		if ( isset( $_GET['b2b_submitted'] ) ) {
			$notice = '<p style="color:green"><strong>Application received!</strong> You can shop at retail prices immediately; wholesale pricing unlocks after approval.</p>';
		}
		if ( isset( $_GET['b2b_error'] ) ) {
			$notice = '<p style="color:red"><strong>' . esc_html( sanitize_text_field( wp_unslash( $_GET['b2b_error'] ) ) ) . '</strong></p>';
		}
		ob_start();
		echo $notice; // phpcs:ignore
		?>
		<form method="post" action="" class="b2b-registration-form">
			<?php wp_nonce_field( 'b2b_register', 'b2b_nonce' ); ?>
			<p><label>Business Name *<br><input type="text" name="b2b_business" required style="width:100%"></label></p>
			<p><label>Tax ID / CAC Number *<br><input type="text" name="b2b_cac" required style="width:100%"></label></p>
			<p><label>Contact Phone *<br><input type="tel" name="b2b_phone" required style="width:100%"></label></p>
			<p><label>Business Address<br><textarea name="b2b_address" style="width:100%"></textarea></label></p>
			<?php if ( ! is_user_logged_in() ) : ?>
			<p><label>Email *<br><input type="email" name="b2b_email" value="<?php echo esc_attr( $prefill_email ); ?>" required style="width:100%"></label></p>
			<p><label>Password *<br><input type="password" name="b2b_password" required minlength="8" style="width:100%"></label></p>
			<?php endif; ?>
			<p><button type="submit" name="b2b_submit" class="button wp-element-button">Apply for Wholesale Account</button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	public function handle_registration() {
		if ( ! isset( $_POST['b2b_submit'] ) || ! isset( $_POST['b2b_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['b2b_nonce'] ) ), 'b2b_register' ) ) {
			return;
		}

		$business = sanitize_text_field( wp_unslash( $_POST['b2b_business'] ?? '' ) );
		$cac      = sanitize_text_field( wp_unslash( $_POST['b2b_cac'] ?? '' ) );
		$phone    = sanitize_text_field( wp_unslash( $_POST['b2b_phone'] ?? '' ) );
		$address  = sanitize_textarea_field( wp_unslash( $_POST['b2b_address'] ?? '' ) );

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
		} else {
			$email    = sanitize_email( wp_unslash( $_POST['b2b_email'] ?? '' ) );
			$password = wp_unslash( $_POST['b2b_password'] ?? '' );
			if ( ! is_email( $email ) || strlen( $password ) < 8 ) {
				wp_safe_redirect( add_query_arg( 'b2b_error', rawurlencode( 'Valid email and 8+ character password required.' ) ) );
				exit;
			}
			if ( email_exists( $email ) ) {
				wp_safe_redirect( add_query_arg( 'b2b_error', rawurlencode( 'An account with this email already exists. Please log in first.' ) ) );
				exit;
			}
			$user_id = wp_create_user( $email, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				wp_safe_redirect( add_query_arg( 'b2b_error', rawurlencode( $user_id->get_error_message() ) ) );
				exit;
			}
			$user = new WP_User( $user_id );
			$user->set_role( 'customer' );
			wp_set_auth_cookie( $user_id );
		}

		update_user_meta( $user_id, 'b2b_business_name', $business );
		update_user_meta( $user_id, 'b2b_cac_number', $cac );
		update_user_meta( $user_id, 'b2b_phone', $phone );
		update_user_meta( $user_id, 'b2b_address', $address );
		update_user_meta( $user_id, 'b2b_application_status', 'pending' );

		// Notify admin
		wp_mail(
			get_option( 'admin_email' ),
			'New Wholesale Application: ' . $business,
			"Business: {$business}\nCAC/Tax ID: {$cac}\nPhone: {$phone}\n\nApprove at: " . admin_url( 'user-edit.php?user_id=' . $user_id )
		);

		wp_safe_redirect( add_query_arg( 'b2b_submitted', '1', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	public function approval_field( $user ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$status = get_user_meta( $user->ID, 'b2b_application_status', true );
		if ( empty( $status ) ) {
			return;
		}
		?>
		<h2>Wholesale Application</h2>
		<table class="form-table">
			<tr><th>Business</th><td><?php echo esc_html( get_user_meta( $user->ID, 'b2b_business_name', true ) ); ?></td></tr>
			<tr><th>CAC / Tax ID</th><td><?php echo esc_html( get_user_meta( $user->ID, 'b2b_cac_number', true ) ); ?></td></tr>
			<tr><th>Phone</th><td><?php echo esc_html( get_user_meta( $user->ID, 'b2b_phone', true ) ); ?></td></tr>
			<tr><th>Address</th><td><?php echo esc_html( get_user_meta( $user->ID, 'b2b_address', true ) ); ?></td></tr>
			<tr>
				<th><label for="b2b_approve">Approve Wholesale Access</label></th>
				<td><input type="checkbox" name="b2b_approve" id="b2b_approve" value="yes" <?php checked( 'approved', $status ); ?>> Grants the Wholesale Customer role and wholesale pricing.</td>
			</tr>
		</table>
		<?php
	}

	public function save_approval( $user_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['b2b_approve'] ) !== false ) {
			// continue
		}
		$user = new WP_User( $user_id );
		if ( ! empty( $_POST['b2b_approve'] ) && 'yes' === $_POST['b2b_approve'] ) {
			$user->set_role( 'wholesale_customer' );
			update_user_meta( $user_id, 'b2b_application_status', 'approved' );
		} elseif ( isset( $_POST['b2b_approve'] ) === false && 'approved' === get_user_meta( $user_id, 'b2b_application_status', true ) ) {
			$user->set_role( 'customer' );
			update_user_meta( $user_id, 'b2b_application_status', 'pending' );
		}
	}

	public function users_column( $columns ) {
		$columns['b2b_status'] = 'Wholesale';
		return $columns;
	}

	public function users_column_value( $value, $column, $user_id ) {
		if ( 'b2b_status' !== $column ) {
			return $value;
		}
		$status = get_user_meta( $user_id, 'b2b_application_status', true );
		$user   = new WP_User( $user_id );
		if ( in_array( 'wholesale_customer', (array) $user->roles, true ) ) {
			return '<span style="color:green;font-weight:bold">Approved</span>';
		}
		if ( 'pending' === $status ) {
			return '<span style="color:orange;font-weight:bold">Pending review</span>';
		}
		return '—';
	}

	public function enforce_moq() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( ! in_array( 'wholesale_customer', (array) $user->roles, true ) || ! WC()->cart ) {
			return;
		}
		$total = WC()->cart->get_subtotal();
		if ( $total < self::MIN_WHOLESALE_ORDER_TOTAL && ! doing_action( 'wp_ajax_woocommerce_update_order_review' ) ) {
			wc_add_notice(
				sprintf(
					'Wholesale orders require a minimum subtotal of ₦%s. Your current subtotal is ₦%s.',
					number_format( self::MIN_WHOLESALE_ORDER_TOTAL ),
					number_format( $total )
				),
				'error'
			);
		}
	}

	public function account_status() {
		$user = wp_get_current_user();
		if ( in_array( 'wholesale_customer', (array) $user->roles, true ) ) {
			echo '<p style="background:#e7f6e7;border-left:4px solid #2e8540;padding:12px;"><strong>Wholesale Account Active.</strong> You are seeing wholesale prices store-wide. Minimum order subtotal: ₦' . esc_html( number_format( self::MIN_WHOLESALE_ORDER_TOTAL ) ) . '.</p>';
		} elseif ( 'pending' === get_user_meta( $user->ID, 'b2b_application_status', true ) ) {
			echo '<p style="background:#fff7e6;border-left:4px solid #d97706;padding:12px;"><strong>Wholesale application pending.</strong> You will be notified once approved.</p>';
		}
	}
}

new NaijaMart_B2B();
