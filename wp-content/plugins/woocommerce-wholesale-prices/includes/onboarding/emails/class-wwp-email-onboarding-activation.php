<?php
/**
 * Onboarding "activation" email.
 *
 * A behavior-triggered admin email that fires exactly once, the first time a wholesale price is
 * saved on a product (the onboarding activation event, as defined in the WWP onboarding brief —
 * distinct from the celebrated first-order milestone). It is opt-out-able through the standard
 * WooCommerce email settings (the shared `enabled` toggle) and is never calendar-scheduled: the
 * one-time firing and past-milestone suppression are owned by {@see WWP_Onboarding_Emails}.
 *
 * WWP ships no email layer of its own, so this follows WWLC's `includes/emails/` pattern: a thin
 * {@see WC_Email} subclass rendered through WooCommerce's own header/footer templates so no bespoke
 * template files are needed.
 *
 * Part of epic #1031.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/1035
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Email_Onboarding_Activation' ) && class_exists( 'WC_Email' ) ) {

    /**
     * The onboarding activation email.
     *
     * @since 2.3.0
     */
    class WWP_Email_Onboarding_Activation extends WC_Email {

        /**
         * Constructor.
         *
         * @since 2.3.0
         * @access public
         */
        public function __construct() {
            $this->id             = 'wwp_onboarding_activation';
            $this->customer_email = false;
            $this->title          = __( 'Wholesale onboarding: first price set', 'woocommerce-wholesale-prices' );
            $this->description    = __( 'Onboarding email sent to the store admin the first time a wholesale price is saved on a product.', 'woocommerce-wholesale-prices' );

            // Admin-facing onboarding email: default the recipient to the store admin, overridable
            // in the email settings, mirroring WooCommerce's own admin emails (e.g. New Order).
            $this->recipient = get_option( 'admin_email' );

            parent::__construct();
        }

        /**
         * Default email subject.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_default_subject() {
            return __( 'You just set your first wholesale price', 'woocommerce-wholesale-prices' );
        }

        /**
         * Default email heading.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_default_heading() {
            return __( 'Your first wholesale price is live', 'woocommerce-wholesale-prices' );
        }

        /**
         * Default message body (HTML). Editable in the email settings; only the copy is a product
         * sign-off item per the brief.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_default_message() {
            return '<p>' . esc_html__( 'Nice work — you just saved your first wholesale price. Your wholesale customers will now see their special rate on that product.', 'woocommerce-wholesale-prices' ) . '</p>'
                . '<p>' . esc_html__( 'A couple of quick next steps to finish setting up:', 'woocommerce-wholesale-prices' ) . '</p>'
                . '<ul>'
                . '<li>' . esc_html__( 'Approve a wholesale customer so someone can buy at wholesale prices.', 'woocommerce-wholesale-prices' ) . '</li>'
                . '<li>' . esc_html__( 'Preview your store as a wholesale buyer to confirm the price shows correctly.', 'woocommerce-wholesale-prices' ) . '</li>'
                . '</ul>'
                . '<p>' . esc_html__( 'Finish the remaining steps on your Wholesale Prices dashboard whenever you are ready.', 'woocommerce-wholesale-prices' ) . '</p>';
        }

        /**
         * Send this email.
         *
         * The one-time / suppression bookkeeping lives in {@see WWP_Onboarding_Emails}; this just
         * renders and sends when enabled and addressable.
         *
         * @since 2.3.0
         * @access public
         */
        public function trigger() {
            $this->setup_locale();

            if ( $this->is_enabled() && $this->get_recipient() ) {
                $this->send(
                    $this->get_recipient(),
                    $this->get_subject(),
                    $this->get_content(),
                    $this->get_headers(),
                    $this->get_attachments()
                );
            }

            $this->restore_locale();
        }

        /**
         * The email's message, run through the WooCommerce placeholder formatter.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_message() {
            return $this->format_string( $this->get_option( 'message', $this->get_default_message() ) );
        }

        /**
         * HTML content, wrapped in WooCommerce's own email header/footer.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_content_html() {
            return wc_get_template_html(
                'emails/email-header.php',
                array(
                    'email_heading' => $this->get_heading(),
                    'email'         => $this,
                )
            ) . wpautop( wptexturize( $this->get_message() ) ) . wc_get_template_html(
                'emails/email-footer.php',
                array( 'email' => $this )
            );
        }

        /**
         * Plain-text content.
         *
         * @since 2.3.0
         * @access public
         *
         * @return string
         */
        public function get_content_plain() {
            return $this->get_heading() . "\n\n" . wp_strip_all_tags( $this->get_message() );
        }

        /**
         * Settings fields, including the shared enable/disable opt-out and the recipient override.
         *
         * @since 2.3.0
         * @access public
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'    => array(
                    'title'   => __( 'Enable/Disable', 'woocommerce-wholesale-prices' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable this email', 'woocommerce-wholesale-prices' ),
                    'default' => 'yes',
                ),
                'recipient'  => array(
                    'title'       => __( 'Recipient(s)', 'woocommerce-wholesale-prices' ),
                    'type'        => 'text',
                    /* translators: %s: admin email address */
                    'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'woocommerce-wholesale-prices' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ),
                    'placeholder' => '',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'subject'    => array(
                    'title'       => __( 'Subject', 'woocommerce-wholesale-prices' ),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => __( 'Available placeholders: {site_title}, {site_address}', 'woocommerce-wholesale-prices' ),
                    'placeholder' => $this->get_default_subject(),
                    'default'     => '',
                ),
                'heading'    => array(
                    'title'       => __( 'Email heading', 'woocommerce-wholesale-prices' ),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => __( 'Available placeholders: {site_title}, {site_address}', 'woocommerce-wholesale-prices' ),
                    'placeholder' => $this->get_default_heading(),
                    'default'     => '',
                ),
                'message'    => array(
                    'title'       => __( 'Message', 'woocommerce-wholesale-prices' ),
                    'type'        => 'textarea',
                    'desc_tip'    => true,
                    'description' => __( 'The body of the email. HTML allowed.', 'woocommerce-wholesale-prices' ),
                    'placeholder' => $this->get_default_message(),
                    'default'     => '',
                    'css'         => 'width:400px; height: 125px;',
                ),
                'email_type' => array(
                    'title'       => __( 'Email type', 'woocommerce-wholesale-prices' ),
                    'type'        => 'select',
                    'description' => __( 'Choose which format of email to send.', 'woocommerce-wholesale-prices' ),
                    'default'     => 'html',
                    'class'       => 'email_type wc-enhanced-select',
                    'options'     => $this->get_email_type_options(),
                    'desc_tip'    => true,
                ),
            );
        }
    }
}
