<?php
/**
 * Onboarding "completion" email.
 *
 * A behavior-triggered admin email that fires exactly once, when WWP's onboarding section reaches
 * 100% (every counted step complete, the first-order milestone included). It is opt-out-able through
 * the standard WooCommerce email settings (the shared `enabled` toggle) and is never
 * calendar-scheduled: the one-time firing and past-milestone suppression are owned by
 * {@see WWP_Onboarding_Emails}.
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

if ( ! class_exists( 'WWP_Email_Onboarding_Completion' ) && class_exists( 'WC_Email' ) ) {

    /**
     * The onboarding completion email.
     *
     * @since 2.3.0
     */
    class WWP_Email_Onboarding_Completion extends WC_Email {

        /**
         * Constructor.
         *
         * @since 2.3.0
         * @access public
         */
        public function __construct() {
            $this->id             = 'wwp_onboarding_completion';
            $this->customer_email = false;
            $this->title          = __( 'Wholesale onboarding: setup complete', 'woocommerce-wholesale-prices' );
            $this->description    = __( 'Onboarding email sent to the store admin when the Wholesale Prices setup checklist reaches 100%.', 'woocommerce-wholesale-prices' );

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
            return __( 'Your wholesale store is fully set up', 'woocommerce-wholesale-prices' );
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
            return __( 'Wholesale setup complete', 'woocommerce-wholesale-prices' );
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
            return '<p>' . esc_html__( "Congratulations — you've completed every step of your Wholesale Prices setup, including your first wholesale order. Your wholesale store is ready to go.", 'woocommerce-wholesale-prices' ) . '</p>'
                . '<p>' . esc_html__( 'Want to do more with wholesale? You can grow from here with the rest of the Wholesale Suite — bulk order forms, advanced pricing rules and a lead-capture flow for new wholesale applicants.', 'woocommerce-wholesale-prices' ) . '</p>'
                . '<p>' . esc_html__( 'Thanks for setting up with Wholesale Prices.', 'woocommerce-wholesale-prices' ) . '</p>';
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
