<?php

defined('ABSPATH') || exit;

/**
 * Standard Mailer which uses the wp_mail() function of WP.
 */
class NewsletterDefaultMailer extends NewsletterMailer {

    var $filter_active = false;

    /** @var WP_Error */
    var $last_error = null;

    /**
     * Static to be accessed in the hook: on some installation the object $this is not working, we're still trying to understand why
     * @var TNP_Mailer_Message
     */
    var $current_message = null;

    function __construct() {
        parent::__construct('default');
        add_action('wp_mail_failed', [$this, 'hook_wp_mail_failed']);
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    function hook_wp_mail_failed($error) {
        $this->last_error = $error;
    }

    function get_description() {
        // TODO: check if overloaded
        return ' WordPress wp_mail() function';
    }

    function get_speed() {
        return (int) Newsletter::instance()->options['scheduler_max'];
    }

    /**
     *
     * @param PHPMailer $mailer
     */
    function fix_mailer($mailer) {

        // If there is not a current message, wp_mail() was not called by us
        if (is_null($this->current_message)) {
            return;
        }

        $newsletter = Newsletter::instance();
        if (isset($this->current_message->encoding)) {
            $mailer->Encoding = $this->current_message->encoding;
        } else {
            $encoding = $newsletter->get_main_option('content_transfer_encoding');
            if (!empty($encoding)) {
                $mailer->Encoding = $encoding;
            } else {
                //$mailer->Encoding = 'base64';
            }
        }

        /* @var $mailer PHPMailer */
        $mailer->Sender = $newsletter->get_main_option('return_path');

        // If there is an HTML body AND a text body, add the text part.
        if (!empty($this->current_message->body) && !empty($this->current_message->body_text)) {
            $mailer->AltBody = $this->current_message->body_text;
        }

        $mailer->XMailer = false;

        return $mailer; // It's not a filter...
    }

    /**
     *
     * @param TNP_Mailer_Message $message
     * @return \WP_Error|boolean
     */
    function send($message) {

        $logger = $this->get_logger();

        if (!$this->filter_active) {
            add_action('phpmailer_init', array($this, 'fix_mailer'), 100);
            $this->filter_active = true;
        }

        $newsletter = Newsletter::instance();
        $wp_mail_headers = [];
        if (empty($message->from)) {
            $message->from = $newsletter->get_sender_email();
        }

        if (empty($message->from_name)) {
            $message->from_name = $newsletter->get_sender_name();
        }

        $wp_mail_headers[] = 'From: "' . $message->from_name . '" <' . $message->from . '>';

        $reply_to = $newsletter->get_reply_to();
        if (!empty($reply_to)) {
            $wp_mail_headers[] = 'Reply-To: ' . $reply_to;
        }

        // Manage from and from name

        if (!empty($message->headers)) {
            foreach ($message->headers as $key => $value) {
                $wp_mail_headers[] = $key . ': ' . $value;
            }
        }

        if (!empty($message->body)) {
            $wp_mail_headers[] = 'Content-Type: text/html;charset=UTF-8';
            $body = $message->body;
        } elseif (!empty($message->body_text)) {
            $wp_mail_headers[] = 'Content-Type: text/plain;charset=UTF-8';
            $body = $message->body_text;
        } else {
            $message->error = 'Empty body';
            return new WP_Error(self::ERROR_GENERIC, 'Message format');
        }

        $this->last_error = null;

        $this->current_message = $message;

        // To avoid to show errors/warnings by code executed before
        error_clear_last();

        $r = wp_mail($message->to, $message->subject, $body, $wp_mail_headers);

        $this->current_message = null;

        if (!$r) {
            if ($this->last_error && is_wp_error($this->last_error)) {
                $error_message = $this->last_error->get_error_message();

                // Still not used
                $error_data = $this->last_error->get_error_data();
                $error_code = $error_data['phpmailer_exception_code'] ?? '';

                if (stripos($error_message, 'Could not instantiate mail function') || stripos($error_message, 'Failed to connect to mailserver')) {
                    return new WP_Error(self::ERROR_FATAL, $error_message);
                } else {
                    return new WP_Error(self::ERROR_GENERIC, $error_message);
                }
            }

            // This code should be removed when sure...
            $last_error = error_get_last();
            if (is_array($last_error)) {
                $message->error = $last_error['message'];

                if (stripos($message->error, 'Could not instantiate mail function') || stripos($message->error, 'Failed to connect to mailserver')) {
                    return new WP_Error(self::ERROR_FATAL, $last_error['message']);
                } else {
                    return new WP_Error(self::ERROR_GENERIC, $last_error['message']);
                }
            } else {
                $message->error = 'No error explanation reported';
                return new WP_Error(self::ERROR_GENERIC, 'No error message reported');
            }
        }
        return true;
    }
}
