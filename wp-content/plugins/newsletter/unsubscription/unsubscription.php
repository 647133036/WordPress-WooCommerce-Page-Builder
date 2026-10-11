<?php

defined('ABSPATH') || exit;

class NewsletterUnsubscription extends NewsletterModule {

    static $instance;

    /**
     * @return NewsletterUnsubscription
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        parent::__construct('unsubscription');

        add_filter('newsletter_replace', [$this, 'hook_newsletter_replace'], 10, 5);

        add_action('newsletter_shortcode', [$this, 'hook_newsletter_shortcode'], 10, 2);
        add_action('newsletter_shortcode_dummy', [$this, 'hook_newsletter_shortcode_dummy'], 10, 2);

        // Add one click unsubscription header
        add_filter('newsletter_message', [$this, 'hook_newsletter_message'], 9, 3);

        add_action('newsletter_action', [$this, 'hook_newsletter_action'], 11, 3);
        add_action('newsletter_action_dummy', [$this, 'hook_newsletter_action_dummy'], 11, 3);

        if (!is_admin() || defined('DOING_AJAX') && DOING_AJAX) {
            add_shortcode('newsletter_unsubscribe_button', [$this, 'shortcode_newsletter_unsubscribe_button']);
            add_shortcode('newsletter_resubscribe_button', [$this, 'shortcode_newsletter_resubscribe_button']);
        }
    }

    /**
     * Action URL to the page where to start the unsubscription.
     *
     * @param object $user
     * @return string
     */
    function get_unsubscribe_url($user) {
        return $this->build_action_url('u', $user, null, 7 * DAY_IN_SECONDS);
    }

    /**
     * Only the form action URL, without the user key whoch is added as hidden
     * form field.
     *
     * @return string
     */
    function get_unsubscribe_confirm_url() {
        return $this->build_action_url('uc', null, null, DAY_IN_SECONDS);
    }

    /**
     * Action URL to the page where to start the re-subscription.
     *
     * @param object $user
     * @return string
     */
    function get_resubscribe_url($user) {
        return $this->build_action_url('r', $user, null, 7 * DAY_IN_SECONDS);
    }

    /**
     * Only the form action URL, without the user key whoch is added as hidden
     * form field.
     *
     * @return string
     */
    function get_resubscribe_confirm_url() {
        return $this->build_action_url('rc', null, null, DAY_IN_SECONDS);
    }

    /**
     * Button to confirm to unsubscribe.
     *
     * Attributes:
     * - label: The button label
     *
     * @param array $attrs
     * @param string $content Ignored
     * @return string
     */
    function shortcode_newsletter_unsubscribe_button($attrs, $content = '') {
        $user = $this->get_current_user();

        // For dummy user it's ok to show the button
        if (!$user || $user->id !== 0 && $user->status !== TNP_User::STATUS_CONFIRMED) {
            return '';
        }

        $label = empty($attrs['label']) ? __('Unsubscribe', 'newsletter') : $attrs['label'];

        $b = '<form action="' . esc_attr($this->get_unsubscribe_confirm_url()) . '" method="post" class="tnp-button-form tnp-button-unsubscribe">';
        $b .= wp_nonce_field('newsletter-unsubscribe', '_wpnonce', true, false);
        $b .= $this->get_user_key_field($user, 'uc');
        $b .= '<button class="tnp-submit">' . esc_html($label) . '</button>';
        $b .= '</form>';
        return $b;
    }

    /**
     * Button to confirm the resubscribe.
     *
     * Attributes:
     * - label: The button label
     *
     * @param array $attrs
     * @param string $content Ignored
     * @return string
     */
    function shortcode_newsletter_resubscribe_button($attrs, $content = '') {
        $user = $this->get_current_user();

        // For dummy user it's ok to show the button
        if (!$user || $user->id !== 0 && $user->status !== TNP_User::STATUS_UNSUBSCRIBED) {
            return '';
        }

        $label = empty($attrs['label']) ? __('Resubscribe', 'newsletter') : $attrs['label'];

        $b = '<form action="' . esc_attr($this->get_resubscribe_confirm_url()) . '" method="post" class="tnp-button-form tnp-button-resubscribe">';
        $b .= wp_nonce_field('newsletter-resubscribe', '_wpnonce', true, false);
        $b .= $this->get_user_key_field($user, 'rc');
        $b .= '<button class="tnp-submit">' . esc_html($label) . '</button>';
        $b .= '</form>';
        return $b;
    }

    function hook_newsletter_action_dummy($action, $user, $email) {
        if (!in_array($action, ['u', 'uc', 'ocu', 'r', 'rc'])) {
            return;
        }

        $this->set_user_cookie($user);

        switch ($action) {
            case 'u':
                $url = $this->build_message_url(null, 'unsubscribe', $user, $email);
                $this->redirect($url);
                break;

            case 'uc':
                $this->send_unsubscribed_email($user);
                $url = $this->build_message_url(null, 'unsubscribed', $user, $email);
                $this->redirect($url);
                break;

            case 'r':
                $url = $this->build_message_url(null, 'reactivate', $user, $email);
                $this->redirect($url);
                break;

            case 'rc':
                $url = $this->build_message_url(null, 'reactivated', $user);
                $this->redirect($url);
                break;
        }
    }

    /**
     * @param string $action
     * @param TNP_User $user
     * @param TNP_Email $email
     */
    function hook_newsletter_action($action, $user, $email) {

        if (!in_array($action, ['u', 'uc', 'ocu', 'r', 'rc'])) {
            return;
        }

        if (!$user) {
            $this->dienow(__('Subscriber not found or link expired', 'newsletter'), 'From a test newsletter or already deleted or using the wrong subscriber key in the URL', 404);
        }

        if ($user->status !== TNP_User::STATUS_CONFIRMED && $user->status !== TNP_User::STATUS_UNSUBSCRIBED) {
            $this->dienow(__('Subscriber blocked', 'newsletter'), '', 404);
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $agent = strtolower(wp_unslash($_SERVER['HTTP_USER_AGENT']));
            $bots = ['googlebot', 'yandexbot', 'bingbot', 'bingpreview', 'microsoftpreview', 'bytespider', 'headlesschrome'];
            foreach ($bots as $bot) {
                if (strpos($agent, $bot) !== false) {
                    die();
                }
            }
        }

        // Show the antibot and stop, not for the direct email client call with action "ocu".
        if (in_array($action, ['u', 'uc', 'r', 'rc'])) {
            if (!NEWSLETTER_TEST) {
                if (!$this->antibot_form_check(false)) {
                    $this->antibot_unsubscription('');
                }
            }
        }

        // It should be moved to the action dispatcher
        $this->set_user_cookie($user);

        switch ($action) {
            case 'u':
                //$url = $this->build_message_url(null, 'unsubscribe', $user, $email);
                // Assume there is a cookie
                $url = $this->build_message_url(null, 'unsubscribe', null, null);
                $this->redirect($url);
                break;

            case 'uc':
                $verified = wp_verify_nonce($_REQUEST['_wpnonce'], 'newsletter-unsubscribe');
                if (!$verified) {
                    $this->redirect($this->build_action_url('u', $user, $email));
                }
                $this->unsubscribe($user, $email);
                //$url = $this->build_message_url(null, 'unsubscribed', $user, $email);
                // Assume there is a cookie
                $url = $this->build_message_url('', 'unsubscribed', null, null);
                setcookie('newsletter', '', 0, '/');
                $this->redirect($url);
                break;

            // One Click Unsubscribe rfc8058. It could be started many days after the email has been sent.
            case 'ocu':
                if ('One-Click' === wp_unslash($_POST['List-Unsubscribe'] ?? '')) {
                    $this->unsubscribe($user, $email, 'unsubscribe-rfc8058');
                    die('ok');
                }
                die('ko');
                break;

            // Resubscribe starting page
            case 'r':
                $url = $this->build_message_url(null, 'reactivate', $user, $email);
                $this->redirect($url);
                break;

            // Resubscribe confirm
            case 'rc':
                $verified = wp_verify_nonce($_REQUEST['_wpnonce'], 'newsletter-resubscribe');
                if (!$verified) {
                    die('Unverified request');
                }
                $this->resubscribe($user);
                $this->set_user_cookie($user);
                $url = $this->build_message_url(null, 'reactivated', $user);
                $this->redirect($url);
                break;
        }
    }

    /**
     * Unsubscribes the subscriber from the request. Die on subscriber extraction failure.
     */
    function unsubscribe($user, $email = null, $type = 'unsubscribe') {
        global $wpdb;

        if ($user->status !== TNP_User::STATUS_CONFIRMED) {
            return;
        }

        $this->set_user_status($user, TNP_User::STATUS_UNSUBSCRIBED);

        $this->add_user_log($user, $type);

        if ($email) {
            $wpdb->update(NEWSLETTER_USERS_TABLE, ['unsub_email_id' => (int) $email->id, 'unsub_time' => time()], ['id' => (int) $user->id]);
        }

        $this->send_unsubscribed_email($user);

        do_action('newsletter_user_unsubscribed', $user);

        $this->notify_admin($user);
    }

    function send_unsubscribed_email($user) {
        if (!empty($this->get_main_option('unsubscribed_disabled'))) {
            return;
        }

        $this->switch_language($user->language);

        $message = do_shortcode($this->get_text('unsubscribed_message'));
        $subject = $this->get_text('unsubscribed_subject');

        NewsletterSubscription::instance()->mail($user, $subject, $message);
        $this->restore_language();
    }

    function notify_admin($user) {

        if (empty($this->get_main_option('notify'))) {
            return;
        }

        $message = $this->generate_admin_notification_message($user);
        $email = trim($this->get_main_option('notify_email'));
        $subject = $this->generate_admin_notification_subject('New cancellation');

        Newsletter::instance()->mail($email, $subject, ['html' => $message]);
    }

    /**
     * Reactivate the subscriber extracted from the request setting his status
     * to confirmed and logging. No email are sent. Dies on subscriber extraction failure.
     */
    function resubscribe($user) {
        if ($user->status !== TNP_User::STATUS_UNSUBSCRIBED) {
            return;
        }

        $this->set_user_status($user, TNP_User::STATUS_CONFIRMED);
        $this->add_user_log($user, 'resubscribe');
        do_action('newsletter_user_resubscribed', $user);
    }

    function hook_newsletter_replace($text, $user, $email, $html = true, $context = null) {

        if ($user) {
            $url = $this->build_action_url('u', $user, $email);
            if ('page' === $context) {
                $url = wp_nonce_url($url, 'newsletter-unsubscribe');
            }
            $text = $this->replace_url($text, 'unsubscription_confirm_url', $url);
            $text = $this->replace_url($text, 'unsubscription_url', $this->get_unsubscribe_url($user, $email));
            $text = $this->replace_url($text, 'unsubscribe_url', $this->get_unsubscribe_url($user, $email));

            $url = $this->get_resubscribe_url($user);
            // It should no more used as URL and needs to be removed!
            if ('page' === $context) {
                $url = wp_nonce_url($url, 'newsletter-resubscribe');
            }

            $text = $this->replace_url($text, 'resubscribe_url', $url);
            $text = $this->replace_url($text, 'reactivate_url', $url);
            $text = $this->replace_url($text, 'reactivation_url', $url);
        } else {
            $text = $this->replace_url($text, 'unsubscription_confirm_url', $this->build_action_url('nul'));
            $text = $this->replace_url($text, 'unsubscription_url', $this->build_action_url('nul'));
            $text = $this->replace_url($text, 'unsubscribe_url', $this->build_action_url('nul'));
        }

        return $text;
    }

    function hook_newsletter_shortcode($message, $user) {
        if (!in_array($message, ['unsubscribe', 'reactivate', 'unsubscribed', 'reactivated'], true)) {
            return;
        }

        if (!$user) {
            $this->show_admin_notice('The link is expired or the subscriber does not exist.');
            echo $this->get_text('error_text');
            return;
        }

        // User status check?

        $this->show_admin_notice('<a href="' . admin_url('admin.php?page=newsletter_unsubscription_index') . '" target="_blank">Edit this content</a>.');

        echo $this->get_text($message . '_text');
    }

    function hook_newsletter_shortcode_dummy($message, $user) {
        if (!in_array($message, ['unsubscribe', 'reactivate', 'unsubscribed', 'reactivated'], true)) {
            return;
        }

        $this->show_admin_notice('Preview of the content with a dummy subscriber.');

        echo $this->get_text($message . '_text');
    }

    /**
     *
     * @param TNP_Mailer_Message $message
     * @param TNP_Email $email
     * @param TNP_User $user
     * @return TNP_Mailer_Message
     */
    function hook_newsletter_message($message, $email, $user) {

        if (!empty($this->get_main_option('disable_unsubscribe_headers'))) {
            return $message;
        }

        $list_unsubscribe_values = [];
        if (!empty($this->get_main_option('list_unsubscribe_mailto_header'))) {
            $unsubscribe_address = $this->get_main_option('list_unsubscribe_mailto_header');
            $list_unsubscribe_values[] = "<mailto:$unsubscribe_address?subject=Unsubscribe>";
        }

        // Lifespan of 7 days, it should be increased... since people can unsubscribe when reviewing old
        // newsletters.
        $unsubscribe_action_url = $this->build_action_url('ocu', $user, $email, 7 * DAY_IN_SECONDS);
        $list_unsubscribe_values[] = "<$unsubscribe_action_url>";

        $message->headers['List-Unsubscribe'] = implode(', ', $list_unsubscribe_values);
        $message->headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';

        return $message;
    }
}

NewsletterUnsubscription::instance();
