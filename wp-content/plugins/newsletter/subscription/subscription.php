<?php
defined('ABSPATH') || exit;

class NewsletterSubscription extends NewsletterModule {

    const OPTIN_DOUBLE = 0;
    const OPTIN_SINGLE = 1;

    static $instance;
    var $popup_test = false;
    var $popup_enabled = false;

    /**
     * @return NewsletterSubscription
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {

        parent::__construct('subscription');

        // Must be called after the Newsletter::hook_init, since some constants are defined
        // there.
        add_action('init', [$this, 'hook_init'], 90);
    }

    function hook_init() {

        add_filter('newsletter_replace', [$this, 'hook_newsletter_replace'], 10, 4);

        add_action('newsletter_action', [$this, 'hook_newsletter_action'], 10, 3);
        add_action('newsletter_action_dummy', [$this, 'hook_newsletter_action_dummy'], 11, 3);

        add_action('newsletter_shortcode', [$this, 'hook_newsletter_shortcode'], 10, 2);
        add_action('newsletter_shortcode_dummy', [$this, 'hook_newsletter_shortcode_dummy'], 10, 2);

        // The form is sometimes retrieved via AJAX
        if (!is_admin() || defined('DOING_AJAX') && DOING_AJAX) {
            add_shortcode('newsletter_form', [$this, 'shortcode_newsletter_form']);
            add_shortcode('newsletter_field', [$this, 'shortcode_newsletter_field']);
        }

        if (!empty($this->get_option('bottom_enabled', 'inject'))) {
            add_filter('the_content', [$this, 'hook_the_content'], 99);
        }

        if (class_exists('NewsletterLeads') && NewsletterLeads::$instance->popup_enabled) {
            $this->popup_enabled = false;
        } else {
            $this->popup_enabled = !empty($this->get_option('enabled', 'popup'));
        }
        $this->popup_test = isset($_GET['tnp-popup-test']) && Newsletter::instance()->is_allowed();

        if ($this->popup_enabled || $this->popup_test) {
            add_action('wp_footer', [$this, 'hook_wp_footer'], 99);
            add_action('wp_enqueue_scripts', [$this, 'hook_wp_enqueue_scripts']);
        }
        add_action('newsletter_action', [$this, 'hook_newsletter_action_popup']);
    }

    function hook_wp_footer() {
        if (!$this->popup_test) {
            $user = Newsletter::instance()->get_current_user();
            if ($user && $user->status === 'C') {
                return;
            }
        }
        ?>
        <div id="tnp-modal">
            <div id="tnp-modal-content">
                <div id="tnp-modal-close">&times;</div>
                <div id="tnp-modal-body"></div>
            </div>
        </div>
        <?php
    }

    function get_confirm_url($user) {
        return $this->build_action_url('c', $user, null);
    }

    function hook_newsletter_replace($text, $user, $email, $html = true) {
        if (!$user) {
            return $text;
        }

        $url = $this->get_confirm_url($user);

        // Overtime, many tags have been used.
        $text = $this->replace_url($text, 'subscription_confirm_url', $url);
        $text = $this->replace_url($text, 'activation_url', $url);
        $text = $this->replace_url($text, 'confirmation_url', $url);
        $text = $this->replace_url($text, 'confirm_url', $url);

        return $text;
    }

    function hook_wp_enqueue_scripts() {
        wp_enqueue_script('newsletter-popup', plugins_url('assets/popup.js', __FILE__), [], NEWSLETTER_VERSION, true);
        $data = ['test' => $this->popup_test ? '1' : '0',
            'action' => $this->build_action_url('sa'),
            'url' => Newsletter::add_qs(home_url('/'), 'na=popup&language=' . rawurlencode($this->language()))];
        wp_localize_script('newsletter-popup', 'newsletter_popup_data', $data);
        wp_enqueue_style('newsletter-popup', plugins_url('assets/popup.css', __FILE__), [], NEWSLETTER_VERSION);
    }

    function hook_newsletter_action_popup($action) {
        switch ($action) {
            case 'popup':
                header('Content-Type: text/html;charset=UTF-8');
                echo $this->get_option('text', 'popup');
                echo NewsletterSubscription::instance()->get_subscription_form('popup', null,
                        ['class' => 'tnp-subscription-popup', 'id' => 'tnp-subscription-popup']);
                die();
        }
    }

    function hook_the_content($content) {
        if (!is_single()) {
            return $content;
        }

        if ('post' !== get_post_type()) {
            return $content;
        }

        $style = '';

        return $content
                . '<div class="tnp-subscription-posts" id="tnp-subscription-posts"'
                . ' style="' . esc_attr($style) . '"'
                . '>'
                . $this->get_option('bottom_text', 'inject')
                . $this->get_subscription_form('posts_bottom')
                . '</div>';
    }

    /**
     * Compute the configured page url to show the confirmation request message.
     * Can return an empty
     * @return type
     */
    function get_confirmation_page_url() {
        // Per message custom URL from configuration (language variants could not be supported)
        $page_id = $this->get_option('confirmation_id');
        $url = '';
        if (!empty($page_id)) {
            if ($page_id === 'url') {
                $url = sanitize_url($this->get_option('confirmation_url'));
            } else {
                $url = get_permalink((int) $page_id);
            }
        }

        return $url;
    }

    function get_confirmed_page_url() {
        $page_id = $this->get_option('confirmed_id');
        $url = '';
        if (!empty($page_id)) {
            if ($page_id === 'url') {
                $url = sanitize_url($this->get_option('confirmed_url'));
            } else {
                $url = get_permalink((int) $page_id);
            }
        }
        return $url;
    }

    function hook_newsletter_action_dummy($action, $user, $email) {
        if ('s' === $action) {
            if (!$user) {
                die('Subscriber not found.');
            }
            $this->switch_language($user->language);
            $url = $this->build_message_url($this->get_confirmation_page_url(), 'confirmation', $user, $email);
            $this->redirect($url);
        }

        if ('c' === $action) {
            if (!$user) {
                die('Subscriber not found.');
            }
            $this->switch_language($user->language);
            $url = Newsletter::instance()->build_message_url($this->get_confirmed_page_url(), 'confirmed', $user, $email);
            $this->redirect($url);
        }
    }

    /**
     *
     * @global wpdb $wpdb
     * @return mixed
     */
    function hook_newsletter_action($action, $user, $email) {
        switch ($action) {

            // normal subscription
            case 's':
            case 'subscribe':

                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $this->dienow('Invalid request', 'The subscription request was not made with a HTTP POST', 400);
                }

                $options_antibot = $this->get_options('antispam');

                $captcha = !empty($options_antibot['captcha']);

                if (NEWSLETTER_TEST || !empty($_GET['_wp_amp_action_xhr_converted']) || !empty($options_antibot['disabled']) || $this->antibot_form_check($captcha)) {

                    $subscription = $this->build_subscription();

                    $user = $this->subscribe2($subscription);

                    if (is_wp_error($user)) {
                        if ($user->get_error_code() === 'exists') {
                            $this->redirect($this->build_message_url('', 'error'));
                        }
                        $this->dienow(__('Registration failed.', 'newsletter'), $user->get_error_message(), 400);
                    }

                    $this->set_user_cookie($user);

                    // The confirmation can be required by re-subscriptons or other conditions
                    if ($user->status === TNP_User::STATUS_CONFIRMED) {
                        $this->redirect_to_confirmed($user, $email);
                    } else {
                        $this->redirect_to_confirmation($user, $email);
                    }
                } else {
                    $language = $this->sanitize_language($_REQUEST['nlang'] ?? '');
                    Newsletter::instance()->switch_language($language);
                    $this->antibot_subscription($this->get_form_text('subscribe'), $captcha);
                }
                die();

            // AJAX subscription
            case 'sa':
            case 'ajaxsub':

                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $this->dienow('Invalid request');
                }

                $subscription = $this->build_subscription();

                $user = $this->subscribe2($subscription);

                if (is_wp_error($user)) {
                    if ($user->get_error_code() === 'exists') {
                        echo $this->get_text('error_text');
                        die();
                    } else {
                        $this->dienow(__('Registration failed.', 'newsletter'), $user->get_error_message(), 400);
                    }
                }

                $this->switch_language($user->language);
                $this->set_user_cookie($user);
                if ($user->status === TNP_User::STATUS_CONFIRMED) {

                    $message = $this->replace($this->get_text('confirmed_text'), $user);
                    $message .= $this->get_option('confirmed_tracking');
                } else {
                    $message = $this->replace($this->get_text('confirmation_text'), $user);
                }

                echo $message;
                die();

            case 'c':
                if (!$user) {
                    $this->dienow(__('Subscriber not found.', 'newsletter'), 'Or it is not present or the secret key does not match.', 404);
                }

                if (NEWSLETTER_TEST || $this->antibot_form_check()) {
                    $user = $this->confirm($user);
                    $this->set_user_cookie($user);
                    $this->redirect_to_confirmed($user, $email);
                } else {
                    $this->antibot_subscription('Confirm');
                }
                die();
        }
    }

    function get_options($set = '', $language = null) {
        // This is a patch for addon using the "profile" set which originally contained the
        // form options. This patch can create a problem if someone calls this method to get the actual
        // "profile" set which is the configuration of the profile page.
        // The correct call would be NewsletterProfile::instance()->get_options().
        if ($set === 'profile') {
            $set = 'form';
        }
        return parent::get_options($set, $language);
    }

    function get_form_options() {
        return $this->get_options('form');
    }

    function get_form_option($key) {
        return $this->get_option($key, 'form');
    }

    function get_form_text($key) {
        return $this->get_text($key, 'form');
    }

    function set_updated($user, $time = 0, $ip = '') {
        global $wpdb;
        if (!$time) {
            $time = time();
        }

        if (!$ip) {
            $ip = $this->get_remote_ip();
        }
        $ip = $this->process_ip($ip);

        if (is_object($user)) {
            $id = $user->id;
        } else if (is_array($user)) {
            $id = $user['id'];
        } else {
            $id = 0;
        }

        $id = (int) $id;

        $wpdb->update(NEWSLETTER_USERS_TABLE, array('updated' => $time, 'ip' => $ip, 'geo' => 0), array('id' => $id));
    }

    /**
     * Builds a default subscription object to be used to collect data and subscription options.
     * It is initilized following the main configurations of plugin modules (subscription, lists, ...).
     *
     * @return TNP_Subscription
     */
    function get_default_subscription($language = null) {
        $subscription = new TNP_Subscription();

        $language = is_null($language) ? $this->language() : $language;

        $subscription->data->language = $language;
        $subscription->optin = $this->is_double_optin() ? 'double' : 'single';

        $multiple = (int) $this->get_main_option('multiple');

        switch ($multiple) {
            case 0: $subscription->if_exists = TNP_Subscription::EXISTING_ERROR;
                break;
            case 1: $subscription->if_exists = $this->is_double_optin() ? TNP_Subscription::EXISTING_DOUBLE_OPTIN : TNP_Subscription::EXISTING_SINGLE_OPTIN;
                break;
            case 2: $subscription->if_exists = TNP_Subscription::EXISTING_SINGLE_OPTIN;
                break;
            case 3: $subscription->if_exists = TNP_Subscription::EXISTING_DOUBLE_OPTIN;
                break;
        }

        $lists = $this->get_lists();
        foreach ($lists as $list) {
            if ($list->forced) {
                $subscription->data->lists['' . $list->id] = 1;
                continue;
            }
            // Enforced by language
            if ($language && in_array($language, $list->languages)) {
                $subscription->data->lists['' . $list->id] = 1;
            }
        }

        $welcome_email = (int) $this->get_option('welcome_email');
        switch ($welcome_email) {
            case 1: $subscription->welcome_email_id = (int) $this->get_option('welcome_email_id');
                break;
            case 2: $subscription->welcome_email_id = -1;
        }

        // Activation email code
        $subscription->activation_email_id = 0;

        return $subscription;
    }

    function save_subscription_attributes($user, TNP_Subscription $subscription) {
        if (!$subscription) {
            return;
        }

        // Conformed custom ids

        if ($subscription->welcome_page_id) {
            $this->save_user_meta($user->id, 'confirmed_page_id', $subscription->welcome_page_id);
        } else {
            $this->delete_user_meta($user->id, 'confirmed_page_id');
        }

        if ($subscription->welcome_email_id) {
            $this->save_user_meta($user->id, 'confirmed_email_id', $subscription->welcome_email_id);
        } else {
            $this->delete_user_meta($user->id, 'confirmed_email_id');
        }

        // Confirmation customs ids

        if ($subscription->confirmation_page_id) {
            $this->save_user_meta($user->id, 'confirmation_page_id', $subscription->confirmation_page_id);
        } else {
            $this->delete_user_meta($user->id, 'confirmation_page_id');
        }

        if ($subscription->confirmation_email_id) {
            $this->save_user_meta($user->id, 'confirmation_email_id', $subscription->confirmation_email_id);
        } else {
            $this->delete_user_meta($user->id, 'confirmation_email_id');
        }

        // Other

        if (!empty($subscription->autoresponders)) {
            $this->save_user_meta($user->id, 'autoresponders', implode(',', $subscription->autoresponders));
        } else {
            $this->delete_user_meta($user->id, 'autoresponders');
        }
    }

    function spam_check($subscription) {
        if ($subscription->spamcheck) {
            // TODO: Use autoload
            require_once NEWSLETTER_INCLUDES_DIR . '/antispam.php';
            $antispam = NewsletterAntispam::instance();
            $res = $antispam->is_spam($subscription, true);
            if (is_wp_error($res)) {
                Newsletter\Logs::add('antispam', $res->get_error_code() . ' - ' . $res->get_error_message(), 0, $res->get_error_data());
                return new WP_Error('spam', $res->get_error_message());
            }
            return $res;
        }
        return true;
    }

    function subscribe(TNP_Subscription $subscription) {
        return $this->subscribe2($subscription);
    }

    function is_untrusted_single_optin_allowed() {
        if (NEWSLETTER_TEST) {
            return !empty($this->get_main_option('untrusted_single_optin'));
        }

        return defined('NEWSLETTER_UNTRUSTED_SINGLE_OPTIN') && NEWSLETTER_UNTRUSTED_SINGLE_OPTIN;

    }

    /**
     *
     * @param TNP_Subscription $subscription
     *
     * @return TNP_User|WP_Error
     */
    function subscribe2(TNP_Subscription $subscription) {

        $this->logger->debug('Subscription start');

        // Fill in optional data
        // The full IP can be used by the antispam, the, if required, it will be anonymized
        if (empty($subscription->data->ip)) {
            $subscription->data->ip = $this->get_remote_ip();
        }

        // Spam check before sanitization: we could remove relevant information to evaluate spam
        $spam_check = $this->spam_check($subscription);
        if (is_wp_error($spam_check)) {
            return $spam_check;
        }

        $this->sanitize_subscription_data($subscription->data);

        if (empty($subscription->data->email)) {
            return new WP_Error('email', 'Wrong email address');
        }

        // Exists?
        $existing_user = $this->get_user_by_email($subscription->data->email);

        $subscription = apply_filters('newsletter_subscription', $subscription, $existing_user);

        if (!$subscription) {
            return new WP_Error('filter', 'Subscription blocked by filter');
        }


        // GDPR
        $subscription->data->ip = $this->process_ip($subscription->data->ip);

        // ??? Or clean up anmd reuse?
        if ($existing_user) {
            $this->logger->debug('Existing user, first check');
            if ($existing_user->status === TNP_User::STATUS_NOT_CONFIRMED) {
                $this->delete_user($existing_user->id);
                $existing_user = null;
            }

//            elseif ($existing_user->status === TNP_User::STATUS_UNSUBSCRIBED) {
//                $multiple = $this->get_main_option('allow_unsubscribed');
//                if (empty($multiple)) {
//                    return new WP_Error('unsubscribed', 'Unsubscribed email address. Contact the site administrator.');
//                }
//                $subscription->optin = 'double';
//            }
        }

        $this->logger->debug($existing_user ? 'Existing subscriber with status ' . $existing_user->status : 'New subscriber');

        // New subscriber
        if (!$existing_user) {

            $user = new TNP_User();
            $user->token = $this->get_token();
            $user->status = TNP_User::STATUS_NOT_CONFIRMED;
            $subscription->data->merge_in($user);

            $user = apply_filters('newsletter_user_subscribe', $user);

            $user = $this->save_user($user);

            // Save only the useful info contained in the subscription (custom emails, custom pages, ...)
            $this->save_subscription_attributes($user, $subscription);

            $this->add_user_log($user, 'subscribe'); // Logs should be appended to the existing user but with submitted data

            if ($subscription->optin === 'single') {
                // With single optin in the confirmation is automatic
                $user = $this->confirm($user);
            } else {
                // It can be false, when the method is used for custom subscription and the welcome email is not required
                if ($subscription->send_emails) {
                    $this->send_confirmation_email($user);
                }
            }

            $this->logger->debug('Subscribe completed');
            return $user;
        }

        // A subscriber already exists with the provided email... deal with many special cases...
        // It should never happen.
        if ($existing_user->status === 'T') {
            return new WP_Error('temporary', 'Internal error.');
        }

        if ($existing_user->status === TNP_User::STATUS_BOUNCED) {
            return new WP_Error('bounced', 'Subscriber blocked since bounced. Contact the site administrator.');
        }

        if ($existing_user->status === TNP_User::STATUS_COMPLAINED) {
            return new WP_Error('complained', 'Subscriber blocked since complained. Contact the site administrator.');
        }


        if ($existing_user->status === TNP_User::STATUS_UNSUBSCRIBED) {
            $multiple = $this->get_main_option('allow_unsubscribed');
            if (empty($multiple)) {
                $this->logger->debug('Repeated subscription not allowed when already unsubscribed');
                return new WP_Error('unsubscribed', 'Unsubscribed email address. Contact the site administrator.');
            }
            $subscription->optin = 'double';
            $user = new TNP_User();
            $subscription->data->merge_in($user);
            $user->status = 'T'; // Important
            $user = $this->save_user($user);
            $this->save_user_subscription($user->id, $subscription);
            $this->save_subscription_attributes($user, $subscription);

            // Needed for caller to redirect
            $user->status = TNP_User::STATUS_NOT_CONFIRMED;
            $this->send_confirmation_email($user);
            return $user;
        }

        if ($existing_user->status === TNP_User::STATUS_CONFIRMED) {

            if ($subscription->if_exists === TNP_Subscription::EXISTING_ERROR) {
                $this->logger->debug('Repeated subscriptions not allowed');
                return new WP_Error('exists', 'Email address already registered and Newsletter sets to block repeated registrations. You can change this behavior or the user message above on subscription configuration panel.');
            }

            if ($this->is_untrusted_single_optin_allowed()) {
                $is_trusted = true;
                $this->logger->debug('Trusted user forced by configuration wp-config.php');
            } else {
                // If a current subscriber can be recognized and it is subscribing again (for example on a different form) allow it to
                // use the single optin, otherwise we cannot trust it and the confirmation is required to avoid it to access the original
                // subscriber data.
                $current_user = $this->get_current_user();
                $is_trusted = $current_user && $current_user->id == $existing_user->id;
                $this->logger->debug($is_trusted ? 'Trusted user' : 'Untrusted user');
            }

            // If a "current" subscriber is recognized and it matches the new subscription we can use it
            if ($is_trusted) {

                // Force the single opt-in specific on configuration
                if ($subscription->if_exists === TNP_Subscription::EXISTING_SINGLE_OPTIN) {
                    $subscription->optin = 'single';
                }

                // For a trusted user, we store the subscription details and they'll be merged on confirmation
                // (immediate or by email)
                $this->save_user_subscription($existing_user->id, $subscription);
                $this->save_subscription_attributes($existing_user, $subscription);
                $this->add_user_log($existing_user, 'subscribe');

                if ($subscription->optin === 'single') {
                    return $this->confirm($existing_user);
                } else {

                    // Not saved, used on calling method to proceed with redirects. The subscriber status cannot be changed
                    // since it is confirmed and this is a second subscription to be merged.
                    $existing_user->status = TNP_User::STATUS_NOT_CONFIRMED;
                    $this->send_confirmation_email($existing_user);
                    return $existing_user;
                }
            } else {

                // The current user cannot be trusted (or it could be missing), so we need to force a double opt-in and create
                // a temporary subscriber. It's like an unconfirmed but on confiration it is merged with the matching subscriber.
                $subscription->optin = 'double';
                $user = new TNP_User();
                $subscription->data->merge_in($user);
                $user->status = 'T'; // Important
                $user = $this->save_user($user);
                $this->save_user_subscription($user->id, $subscription);
                $this->save_subscription_attributes($user, $subscription);

                // Needed for caller to redirect
                $user->status = TNP_User::STATUS_NOT_CONFIRMED;
                $this->send_confirmation_email($user);
                return $user;
            }
        }

        $this->logger->debug('Subscritpion not processed');

        return null;
    }

    /**
     * Confirms a subscription changing the user status and, possibly, merging the
     * temporary data if present.
     *
     * @param TNP_User $user Optionally it can be null (user search from requests paramaters, but deprecated, or a user id)
     * @return TNP_User
     */
    function confirm($user = null, $emails = true) {

        $this->error_log('Confirmation start');

        // We get here only when a confirmation link is followed from an email, it is not expired, it identifies a
        // valid subscriber and, usually, there is a subscription dataset to confirm.

        if (!$user) {
            $this->dienow('Subscriber not found [c01]', 'The confirm procedure has been activated without a valid subscriber', 404);
        }

        // Email change? (to be moved to the profile module with a custom action and message)
        $new_email = get_transient('newsletter_user_' . $user->id . '_email');
        if ($new_email) {
            delete_transient('newsletter_user_' . $user->id . '_email');
            $data = ['id' => $user->id, 'email' => $new_email];
            $user = $this->save_user($data);
            $this->update_user_last_activity($user);
            return $user;
        }

        if ($user->status !== TNP_User::STATUS_NOT_CONFIRMED && $user->status !== TNP_User::STATUS_CONFIRMED && $user->status !== 'T' && $user->status !== TNP_User::STATUS_UNSUBSCRIBED) {
            $this->dienow('Subscriber not found [c02]', 'This subscriber is bounced, complained or unsubscribed, cannot be confirmed', 404);
        }

        // A temporary user?
        if ($user->status === 'T') {
            // Switch to the correct subscriber
            $matching_user = $this->get_user_by_email($user->email);
            if (!$matching_user) {
                $this->delete_user($user->id);
                $this->dienow('Matching subscriber not found', '', 404);
            }
            $subscription = $this->get_user_subscription($user->id);
            if (!$subscription) {
                $this->delete_user($user->id);
                $this->dienow('Subscription data not found', '', 404);
            }
            $subscription->data->merge_in($matching_user);
            // The original user could be unsubscribed, for example...
            $matching_user->status = TNP_User::STATUS_CONFIRMED;
            $matching_user = $this->save_user($matching_user);

            $this->save_subscription_attributes($matching_user, $subscription);

            $this->add_user_log($matching_user, 'activate');

            $this->delete_user($user->id);

            do_action('newsletter_user_confirmed', $matching_user);

            $this->send_welcome_email($matching_user);

            $this->notify_admin_on_subscription($matching_user);

            return $matching_user;
        }


        // Regular confirmation

        $subscription = $this->get_user_subscription($user->id);

        // When the subscription is available it means a trusted subscriber subscribed a second time and the data is awaiting
        // to be merged in.
        if ($subscription) {
            $subscription->data->merge_in($user);
            $this->delete_user_subscription($user->id);
        } else {

            // This could be a double click, we do not send the welcome email once again
            if ($user->status === TNP_User::STATUS_CONFIRMED) {
                // It a second confirmation
                $this->update_user_last_activity($user);
                // This is useful when checking for email scanners
                $this->add_user_log($user, 'activate');
                // No emails to be sent
                return $user;
            } //else {
            //  $this->dienow('Subscription data not found', 'The subscriber exists, but the subscription data is missing', 404);
            //}
        }

        // If the subscriber has subscription data, it's a double opt-in second subscription,
        // we need to merge the data and then process it as a regular confirm.
        // Merge the subscription data into the subscriber, it could be a confirmation for a second subscription of the
        // same email.


        $user->status = TNP_User::STATUS_CONFIRMED;
        $user->last_activity = time();
        $user = $this->save_user($user);

        $this->add_user_log($user, 'activate');

        do_action('newsletter_user_confirmed', $user);

        $this->send_welcome_email($user);
        $this->notify_admin_on_subscription($user);

        return $user;
    }

    function add_microdata($message) {
        return $message . '<span itemscope itemtype="http://schema.org/EmailMessage"><span itemprop="description" content="Email address confirmation"></span><span itemprop="action" itemscope itemtype="http://schema.org/ConfirmAction"><meta itemprop="name" content="Confirm Subscription"><span itemprop="handler" itemscope itemtype="http://schema.org/HttpActionHandler"><meta itemprop="url" content="{subscription_confirm_url}"><link itemprop="method" href="http://schema.org/HttpRequestMethod/POST"></span></span></span>';
    }

    function get_language_from_request() {
        return isset($_REQUEST['nlang']) ? trim(strip_tags($_REQUEST['nlang'])) : $this->language();
    }

    /**
     * Builds a subscription object starting from values in the $_REQUEST
     * global variable. It DOES NOT sanitize or formally check the values.
     * Usually data comes from a form submission.
     * https://www.thenewsletterplugin.com/documentation/subscription/newsletter-forms/
     *
     * @return TNP_Subscription
     */
    function build_subscription() {

        $posted = stripslashes_deep($_REQUEST); // Change to $_POST after compatibility tests

        $language = $this->sanitize_language($posted['nlang'] ?? $this->language());

        $subscription = $this->get_default_subscription($language);
        $data = $subscription->data;

        $data->email = $posted['ne'] ?? '';

        if (isset($posted['nn'])) {
            $data->name = $posted['nn'] ?? '';
        }

        if (isset($posted['ns'])) {
            $data->surname = $posted['ns'];
        }

        if (isset($posted['nx'])) {
            $data->sex = $posted['nx'];
        }

        if (isset($posted['nr'])) {
            $data->referrer = $posted['nr'];
        }

        if (isset($posted['nfid'])) {
            $subscription->form_id = sanitize_key($posted['nfid']);
        }

        if (isset($posted['ntr'])) {
            $data->track = isset($posted['ntr_cb']) ? '1' : '0';
        }

        // From the antibot form
        if (isset($posted['nhr'])) {
            $data->http_referer = $posted['nhr'];
        } else if (isset($_SERVER['HTTP_REFERER'])) {
            $data->http_referer = $_SERVER['HTTP_REFERER'];
        }

        // New profiles
        $customfields = $this->get_options('customfields');
        for ($i = 1; $i <= NEWSLETTER_PROFILE_MAX; $i++) {
            if (isset($posted['np' . $i])) {
                $profile = $this->get_customfield($i);
                if (!$profile || $profile->is_private()) {
                    if (current_user_can('administrator')) {
                        $this->dienow('Invalid custom field', 'Custom field ' . $i . ' has been submitted but it is set as private. Please fix the subscription form.');
                    }
                    continue;
                }
                $data->profiles['' . $i] = $posted['np' . $i];
            }
        }

        // Lists (field name is nl[] and values the list number so special forms with radio button can work)
        $nl = $posted['nl'] ?? [];

        foreach ($nl as $list_id) {
            $list = $this->get_list($list_id);
            if (!$list || $list->is_private()) {
                // To administrator show an error to make him aware of the wrong form configuration
                if (current_user_can('administrator')) {
                    $this->dienow('Invalid list', 'List ' . $list_id . ' has been submitted but it is set as private. Please fix the subscription form.');
                }
                // Ignore this list
                continue;
            }
            $data->lists['' . $list_id] = 1;
        }

        if (isset($_REQUEST['welcome_page_id'])) {
            $subscription->welcome_page_id = (int) $posted['welcome_page_id'];
        }

        if (class_exists('NewsletterAutoresponder') && method_exists('NewsletterAutoresponder', 'is_valid_key')) {

            $keys = wp_parse_list($posted['nar'] ?? []);
            if ($keys) {
                $subscription->autoresponders = []; // Remove the default one
                // Check the keys
                foreach ($keys as $key) {
                    if (NewsletterAutoresponder::$instance->is_valid_key($key)) {
                        list($id, $token) = explode('-', $key, 2);
                        $subscription->autoresponders[] = $id;
                    }
                }
            }
        }

        // Opt-in mode
        if (!empty($this->get_main_option('optin_override')) && isset($posted['optin'])) {
            switch ($posted['optin']) {
                case 'single': $subscription->optin = 'single';
                    break;
                case 'double': $subscription->optin = 'double';
                    break;
            }
        }

        return $subscription;
    }

    /**
     * Processes the request and fill in the *array* representing a subscriber with submitted values
     * (filtering when necessary).
     *
     * @deprecated since version 6.9.0
     * @param array $user An array partially filled with subscriber data
     * @return array The filled array representing a subscriber
     */
    function update_user_from_request($user) {
        return false;
    }

    /**
     * Sends a service message applying the template to the HTML part
     *
     * @param TNP_User $user
     * @param string $subject
     * @param string|array $message If string it is considered HTML, if array it should contains the key "html" and "text"
     * @return type
     */
    function mail($user, $subject, $message) {
        $this->switch_language($user->language);

        $options_template = $this->get_options('template');

        $template = trim($options_template['template']);
        if (empty($template) || strpos($template, '{message}') === false) {
            $template = '{message}';
        }

        if (is_array($message)) {
            $message['html'] = str_replace('{message}', $message['html'], $template);
            $message['html'] = $this->replace_for_email($message['html'], $user);
            $message['text'] = $this->replace_for_email($message['text'], $user);
        } else {
            $message = str_replace('{message}', $message, $template);
            $message = $this->replace_for_email($message, $user);
        }

        $headers = [];

        // Replaces tags from the template

        $subject = $this->replace($subject, $user);

        $this->restore_language();

        return Newsletter::instance()->mail($user->email, $subject, $message, $headers);
    }

    function is_double_optin() {
        return $this->get_main_option('noconfirmation') == 0;
    }

    function get_default_confirmation_email_id($language = null) {
        return $this->get_default_activation_email_id($language);
    }

    /**
     * Returns the email to be used to create the confirmation message. A positive
     * number indicates an email ID created with the composer, 0 indicates the standard
     * simple text email and -1 indicates to not send the message (option usually
     * not available for the confirmation email).
     *
     * @param string $language Code of the language for which return the information
     * @return int
     */
    function get_default_activation_email_id($language = null) {
        $this->switch_language($language);
        $type = (int) $this->get_option('confirmation_email', 0);
        switch ($type) {
            // ID of an email created with the composer
            case 1:
                $email_id = (int) $this->get_option('confirmation_email_id');
                break;

            // Do not send indicator
            case 2:
                $email_id = -1;
                break;

            // Default simple test email indicator
            default:
                $email_id = 0;
        }
        $this->restore_language();

        return $email_id;
    }

    function send_activation_email($user, $force = false) {
        return $this->send_confirmation_email($user, $force);
    }

    /**
     * Sends the activation email without conditions.
     *
     * @deprecated
     * @param stdClass $user
     * @return bool
     */
    function send_confirmation_email($user, $force = false) {

        $email_id = 0;

        $subscription = $this->get_user_subscription($user->id);
        if ($subscription) {
            $email_id = (int) $subscription->confirmation_email_id;
        }

        if (!$email_id) {
            $email_id = $this->get_default_confirmation_email_id($user->language);
        }

        // The confirmation email should never be disabled, at moment there is no way
        // to set that value by configuration
        if ($email_id === -1) {
            return true;
        }

        // Required to correctly load the default texts
        $this->switch_language($user->language);

        if ($email_id) {
            $email = $this->get_email($email_id);
            if ($email) {
                NewsletterComposer::instance()->regenerate($email);
                Newsletter::instance()->send($email, [$user]);
                $this->restore_language();
                return true;
            }
        }

        $message = [];
        $message['html'] = do_shortcode($this->get_text('confirmation_message'));
        $message['text'] = $this->get_text('confirmation_message_plain_text');
        $subject = $this->get_text('confirmation_subject');

        $this->mail($user, $subject, $message);

        $this->restore_language();
        return true;
    }

    function get_default_welcome_email_id($language = null) {

        $email_id = 0;

        $this->switch_language($language);
        $welcome_email = (int) $this->get_option('welcome_email');
        switch ($welcome_email) {
            case 1:
                $email_id = (int) $this->get_option('welcome_email_id');
                break;

            case 2:
                $email_id = -1;
                break;
        }
        $this->restore_language();

        return $email_id;
    }

    function send_welcome_email($user) {

        // Custom welcome email tipically by third party form integration, woocommerce and other
        // registration sources
        $email_id = (int) $this->get_user_meta($user->id, 'confirmed_email_id');

        // if -1, do not send, if 0, use defaults
        if ($email_id === -1) {
            return false;
        }

        if (!$email_id) {
            $email_id = $this->get_default_welcome_email_id($user->language);
        }

        if ($email_id === -1) {
            return false;
        }

        $this->switch_language($user->language);
        if ($email_id === 0) {
            $message = [];
            $message['html'] = do_shortcode($this->get_text('confirmed_message'));
            $message['text'] = $this->get_text('confirmed_message_plain_text');
            $subject = $this->get_text('confirmed_subject');

            $r = $this->mail($user, $subject, $message);
        } else {

            $email = $this->get_email($email_id);
            if ($email) {
                NewsletterComposer::instance()->regenerate($email);
                $r = Newsletter::instance()->send($email, [$user]);
            } else
                $r = false;
        }
        $this->restore_language();
        return $r;
    }

    function redirect_to_confirmed($user, $email = null) {
        if (!$user) {
            die('Subscriber not found.');
        }
        $this->switch_language($user->language);
        $url = '';
        $welcome_page_id = $this->get_user_meta($user->id, 'confirmed_page_id');
        if ($welcome_page_id) {
            $url = get_permalink($welcome_page_id);
        } else {
            if (isset($_REQUEST['ncu'])) {
                // Custom URL from the form
                $url = wp_validate_redirect(wp_unslash($_REQUEST['ncu']), home_url());
            } else {
                // Per message custom URL from configuration (language variants could not be supported)
                $page_id = $this->get_option('confirmed_id');
                if (!empty($page_id)) {
                    if ($page_id === 'url') {
                        $url = sanitize_url($this->get_option('confirmed_url'));
                    } else {
                        $url = get_permalink((int) $page_id);
                    }
                }
            }
        }
        $url = apply_filters('newsletter_welcome_url', $url, $user);
        //$url = Newsletter::instance()->build_message_url($url, 'confirmed', $user, $email);
        // Assume there is a cookie
        $url = $this->build_message_url($url, 'confirmed', null, null);
        $this->redirect($url);
    }

    function redirect_to_confirmation($user, $email = null) {
        if (!$user) {
            die('Subscriber not found.');
        }
        $this->switch_language($user->language);
        $url = '';
        // TODO: get it from the subscription
        $page_id = $this->get_user_meta($user->id, 'confirmation_page_id');
        if ($page_id) {
            $url = get_permalink($page_id);
        } else {
            if (isset($_REQUEST['ncu'])) {
                // Custom URL from the form
                $url = wp_validate_redirect($_REQUEST['ncu'], home_url());
            } else {
                $page_id = $this->get_option('confirmation_id');
                if (!empty($page_id)) {
                    if ($page_id === 'url') {
                        $url = sanitize_url($this->get_option('confirmation_url'));
                    } else {
                        $url = get_permalink((int) $page_id);
                    }
                }
            }
        }
        $url = apply_filters('newsletter_confirmation_url', $url, $user);

        //$url = $this->build_message_url($url, 'confirmation', $user, $email);
        // Assume there is a cookie
        $url = $this->build_message_url($url, 'confirmation', null, null);
        $this->redirect($url);
    }

    /**
     * Finds the right way to show the message identified by $key (welcome, unsubscription, ...) redirecting the user to the
     * WordPress page or loading the configured url or activating the standard page.
     */
    function show_message($key, $user = null, $alert = '', $email = null) {
        $url = '';

        if ($user) {
            $this->switch_language($user->language);
        }

        if ($key === 'confirmed') {
            $this->redirect_to_confirmed($user);
        }

        if ($key === 'confirmation') {
            $this->redirect_to_confirmation($user);
        }

        $url = sanitize_url($this->get_option($key . '_url'));

        $url = Newsletter::instance()->build_message_url($url, $key, $user, $email, $alert);
        $this->redirect($url);
    }

    var $privacy_url = false;

    /**
     * Generates the privacy URL and cache it.
     *
     * @return string
     */
    function get_privacy_url() {
        if ($this->privacy_url === false) {
            if (!empty($this->get_main_option('privacy_use_wp_url', 'form')) && function_exists('get_privacy_policy_url')) {
                $this->privacy_url = get_privacy_policy_url();
            } else {
                $this->privacy_url = $this->get_option('privacy_url', 'form');
            }
        }
        return $this->privacy_url;
    }

    function get_form_javascript() {

    }

    /**
     * Manages the custom forms made with [newsletter_form] and internal [newsletter_field] shortcodes.
     *
     * @param array $attrs
     * @param string $content
     * @return string
     */
    function get_subscription_form_custom($attrs = [], $content = '') {
        if (!is_array($attrs)) {
            $attrs = [];
        }

        $attrs = array_merge(['class' => 'tnp-subscription', 'style' => '', 'id' => ''], $attrs);

        $action = esc_attr($this->build_action_url('s'));
        $class = esc_attr($attrs['class']);
        $style = esc_attr($attrs['style']);
        if (!empty($attrs['ajax'])) {
            $class .= ' tnp-ajax';
        }

        $buffer = '<form method="post" action="' . $action . '" class="' . $class . '" style="' . $style . '"';
        if (!empty($attrs['id'])) {
            $buffer .= ' id="' . esc_attr($attrs['id']) . '"';
        }

        $buffer .= '>' . "\n";

        $buffer .= $this->get_form_hidden_fields($attrs);

        $buffer .= do_shortcode($content);

        // Check if a button has been added (bad code)
        if (strpos($buffer, 'type="submit"') || strpos($buffer, 'type="button"')) {

        } else {

            if (isset($attrs['button_label'])) {
                $label = $attrs['button_label'];
            } else {
                $label = $this->get_form_text('subscribe');
            }

            if (!empty($label)) {
                $buffer .= $this->get_button($attrs);
            }
        }

        $buffer .= '</form>';

        return $buffer;
    }

    /** Generates the hidden field for lists which should be implicitely set with a subscription form.
     *
     * @param string $lists Comma separated directly from the shortcode "lists" attribute
     * @param string $language ???
     * @return string
     */
    function get_form_implicit_lists($lists, $language = '') {
        $buffer = '';

        if (is_array($lists)) {
            $arr = $lists;
        } else {
            $arr = explode(',', $lists);
        }

        foreach ($arr as $a) {
            $a = trim($a);
            if (empty($a)) {
                continue;
            }

            $list = $this->get_list($a);
            if (!$list) {
                $buffer .= $this->build_field_admin_notice('List "' . $a . '" added to the form is not configured, skipped.');
                continue;
            }

            if ($list->is_private()) {
                $buffer .= $this->build_field_admin_notice('List ' . $a . ' is private cannot be used in a public form.');
                continue;
            }

            if ($list->forced) {
                $buffer .= $this->build_field_admin_notice('List ' . $a . ' is already enforced on every subscription there is no need to specify it.');
                continue;
            }

            $buffer .= "<input type='hidden' name='nl[]' value='" . esc_attr($a) . "'>\n";
        }
        return $buffer;
    }

    /**
     * Builds all the hidden fields of a subscription form. Implicit lists, confirmation url,
     * referrer, language, ...
     *
     * @param array $attrs Attributes of form shortcode
     * @return string HTML with the hidden fields
     */
    function get_form_hidden_fields($attrs) {
        $b = '';

        // Compatibility
        if (isset($attrs['list'])) {
            $attrs['lists'] = $attrs['list'];
        }
        if (isset($attrs['lists'])) {
            $b .= $this->get_form_implicit_lists($attrs['lists']);
        }

        if (isset($attrs['referrer'])) {
            $b .= '<input type="hidden" name="nr" value="' . esc_attr($attrs['referrer']) . '">' . "\n";
        }

        if (isset($attrs['confirmation_url'])) {
            if ($attrs['confirmation_url'] === '#') {
                $attrs['confirmation_url'] = esc_url_raw($_SERVER['REQUEST_URI']);
            }

            $b .= '<input type="hidden" name="ncu" value="' . esc_attr($attrs['confirmation_url']) . '">' . "\n";
        }

        if (isset($attrs['optin'])) {
            $optin = trim(strtolower($attrs['optin']));
            if ($optin !== 'double' && $optin !== 'single') {
                $b .= $this->build_field_admin_notice('The optin is set to an invalid value.');
            } else {
                if ($optin !== 'double' && $this->is_double_optin() && empty($this->get_main_option('optin_override'))) {
                    $b .= $this->build_field_admin_notice('The optin is specified but cannot be overridden (see the subscription configiraton page).');
                } else {
                    $b .= '<input type="hidden" name="optin" value="' . esc_attr($optin) . '">' . "\n";
                }
            }
        }

        $b .= '<input type="hidden" name="nlang" value="' . esc_attr($this->language()) . '">' . "\n";

        if (isset($attrs['nfid'])) {
            $b .= '<input type="hidden" name="nfid" value="' . esc_attr($attrs['nfid']) . '">' . "\n";
        }

        // Beta
//        if (isset($attrs['welcome_email_id'])) {
//            $e = $this->get_email($attrs['welcome_email_id']);
//            if ($e->type === 'welcome') {
//                $b .= '<input type="hidden" name="welcome_email_id" value="' . esc_attr($e->id . '-' . $e->token) . '">';
//            } else {
//                $b .= $this->build_field_admin_notice('The welcome email ID is not correct.');
//            }
//        }
        // Beta
        if (isset($attrs['welcome_page_id'])) {
            $page = get_post($attrs['welcome_page_id']);
            if ($page && $page->post_status === 'publish') {
                $b .= '<input type="hidden" name="welcome_page_id" value="' . esc_attr($page->ID) . '">' . "\n";
            } else {
                $b .= $this->build_field_admin_notice('The welcome page ID is not correct.');
            }
        }

        if (isset($attrs['autoresponders']) && method_exists('NewsletterAutoresponder', 'get_autoresponder_key')) {
            $ids = wp_parse_id_list($attrs['autoresponders']);
            foreach ($ids as $id) {
                // @phpstan-ignore-next-line
                $key = NewsletterAutoresponder::instance()->get_autoresponder_key($id);
                if ($key) {
                    $b .= '<input type="hidden" name="nar[]" value="' . esc_attr($key) . '">' . "\n";
                } else {
                    $b .= $this->build_field_admin_notice('Autoresponder not found: ' . $id);
                }
            }
        }

        return $b;
    }

    /**
     * Internal use only
     *
     * @param type $name
     * @param type $attrs
     * @return string
     */
    private function _shortcode_label($name, $attrs) {

        // When set but empty: no label
        if (isset($attrs['label']) && empty($attrs['label'])) {
            return;
        }

        $buffer = '<label for="' . esc_attr($attrs['id']) . '">';
        if (isset($attrs['label'])) {
            $buffer .= esc_html($attrs['label']);
        } else {
            if ($name !== 'lists') {
                $buffer .= esc_html($this->get_form_text($name));
            }
        }
        $buffer .= "</label>\n";
        return $buffer;
    }

    private function _shortcode_placeholder($name, $attrs) {

        if (isset($attrs['placeholder'])) {
            return $attrs['placeholder'];
        }

        if (!empty($attrs['label_as_placeholder'])) {
            return $this->get_form_text($name);
        }

        return $this->get_form_text($name . '_placeholder');
    }

    /**
     * Creates a notices to be displayed near a subscription form field to inform of worng configurations.
     * It is created only if the current user looking at the form is the administrator.
     *
     * @param string $message
     * @return string
     */
    function build_field_admin_notice($message) {
        if (!current_user_can('administrator')) {
            return '';
        }
        return '<p style="background-color: #eee; color: #000; padding: 10px; margin: 10px 0">' . $message . ' <strong>This notice is shown only to administrators to help with configuration.</strong></p>';
    }

    function shortcode_newsletter_field($attrs, $content = '') {
        // Counter to create unique ID for checkbox and labels
        static $idx = 0;

        $idx++;
        $attrs['id'] = 'tnp-' . $idx;

        $name = $attrs['name'];

        $buffer = '';

        if ($name == 'button' || $name == 'submit') {
            return $this->get_button($attrs);
        }

        if ($name == 'email') {
            $placeholder = $attrs['placeholder'] ?? $this->get_form_text('email_placeholder');
            $buffer .= '<div class="tnp-field tnp-field-email">';

            $buffer .= $this->_shortcode_label('email', $attrs);

            $buffer .= '<input class="tnp-email" type="email" name="ne" id="' . esc_attr($attrs['id']) . '" value=""';
            $buffer .= ' placeholder="' . esc_attr($placeholder) . '"';
            $buffer .= ' required>';
            if (isset($attrs['button_label'])) {
                $label = $attrs['button_label'];
                $buffer .= ' <input class="tnp-submit" type="submit" value="' . esc_attr($label) . '" style="width: 29%">';
            }
            $buffer .= "</div>\n";
            return $buffer;
        }

        if ($name == 'first_name' || $name == 'name') {
            $placeholder = $attrs['placeholder'] ?? $this->get_form_text('name_placeholder');
            $buffer .= '<div class="tnp-field tnp-field-firstname">';
            $buffer .= $this->_shortcode_label('name', $attrs);

            $buffer .= '<input class="tnp-name" type="text" name="nn" id="' . esc_attr($attrs['id']) . '" value=""';
            $buffer .= ' placeholder="' . esc_attr($placeholder) . '"';
            if ($this->get_form_option('name_rules') == 1) {
                $buffer .= ' required';
            }
            $buffer .= '>';
            $buffer .= "</div>\n";
            return $buffer;
        }

        if ($name == 'last_name' || $name == 'surname') {
            $placeholder = $attrs['placeholder'] ?? $this->get_form_text('surname_placeholder');
            $buffer .= '<div class="tnp-field tnp-field-surname">';
            $buffer .= $this->_shortcode_label('surname', $attrs);

            $buffer .= '<input class="tnp-surname" type="text" name="ns" id="' . esc_attr($attrs['id']) . '" value=""';
            $buffer .= ' placeholder="' . esc_attr($placeholder) . '"';
            if ($this->get_form_option('surname_rules') == 1) {
                $buffer .= ' required';
            }
            $buffer .= '>';
            $buffer .= '</div>';
            return $buffer;
        }

        // Single list
        if ($name == 'preference' || $name == 'list') {
            if (!isset($attrs['number'])) {
                return $this->build_field_admin_notice('List number not specified.');
            }
            $number = (int) $attrs['number'];
            $list = $this->get_list($number);
            if (!$list) {
                return $this->build_field_admin_notice('List ' . $number . ' is not configured, cannot be shown.');
            }

            if ($list->status == 0 || $list->forced) {
                return $this->build_field_admin_notice('List ' . $number . ' is private or enforced cannot be shown.');
            }

            if (isset($attrs['hidden'])) {
                return '<input type="hidden" name="nl[]" value="' . esc_attr($list->id) . '">';
            }

            $buffer .= '<div class="tnp-field tnp-field-checkbox tnp-field-list"><label for="tnp-' . $idx . '">';
            $buffer .= '<input type="checkbox" id="tnp-' . $idx . '" name="nl[]" value="' . esc_attr($list->id) . '"';
            if (isset($attrs['checked'])) {
                $buffer .= ' checked';
            }
            $buffer .= '> ';
            $buffer .= esc_html(isset($attrs['label']) ? $attrs['label'] : $list->name);
            $buffer .= "</label></div>\n";

            return $buffer;
        }

        // All lists
        if ($name === 'lists' || $name === 'preferences') {
            $list_ids = $this->get_form_option('lists');

            if (empty($list_ids)) {
                return;
                //return $this->build_field_admin_notice('No lists is set to be shown on Subscription/Form configuration for the "lists" field.');
            }

            $checked_ids = $this->get_form_option('lists_checked');

            if (isset($attrs['layout']) && $attrs['layout'] === 'dropdown') {

                $buffer .= '<div class="tnp-field tnp-lists">';
                // There is not a default "label" for the block of lists, so it can only be specified in the shortcode attrs as "label"
                $buffer .= $this->_shortcode_label('lists', $attrs);
                $buffer .= '<select class="tnp-lists" name="nl[]" required>';

                if (!empty($attrs['first_option_label'])) {
                    $buffer .= '<option value="" selected disabled>' . esc_html($attrs['first_option_label']) . '</option>';
                }

                foreach ($list_ids as $list_id) {
                    $list = $this->get_list($list_id);
                    if ($list && $list->is_private()) {
                        continue;
                    }
                    $buffer .= '<option value="' . esc_attr($list->id) . '">' . esc_html($list->name) . '</option>';
                }
                $buffer .= '</select>';
                $buffer .= '</div>';
            } else {

                $buffer .= '<div class="tnp-field tnp-lists">';
//                    if (!empty($attrs['label'])) {
//                        $buffer .= '<p>' . $attrs['label'] . '</p>';
//                    }
                foreach ($list_ids as $list_id) {
                    $list = $this->get_list($list_id);
                    if (!$list || $list->is_private()) {
                        continue;
                    }
                    $idx++;
                    $buffer .= '<div class="tnp-field tnp-field-checkbox tnp-field-list"><label for="nl' . $idx . '">';
                    $buffer .= '<input type="checkbox" id="nl' . $idx . '" name="nl[]" value="' . esc_attr($list->id) . '"';
                    if (in_array($list_id, $checked_ids)) {
                        $buffer .= ' checked';
                    }
                    $buffer .= '> ' . esc_html($list->name) . '</label>';
                    $buffer .= "</div>\n";
                }
                $buffer .= '</div>';
            }

            return $buffer;
        }

        if ($name === 'sex' || $name === 'gender') {
            $buffer .= '<div class="tnp-field tnp-field-gender">';
            $buffer .= $this->_shortcode_label('sex', $attrs);

            $buffer .= '<select name="nx" class="tnp-gender" id="tnp-gender"';
            if ($this->get_form_option('sex_rules')) {
                $buffer .= ' required ';
            }
            $buffer .= '>';
            if ($this->get_form_option('sex_rules')) {
                $buffer .= '<option value=""></option>';
            }
            $buffer .= '<option value="n">' . esc_html($this->get_form_text('sex_none')) . '</option>';
            $buffer .= '<option value="f">' . esc_html($this->get_form_text('sex_female')) . '</option>';
            $buffer .= '<option value="m">' . esc_html($this->get_form_text('sex_male')) . '</option>';
            $buffer .= '</select>';
            $buffer .= "</div>\n";
            return $buffer;
        }

        if ($name === 'profile' || $name === 'customfield') {
            $number = (int) $attrs['number'] ?? 0;

            $profile = $this->get_customfield($number);

            if (!$profile) {
                return $this->build_field_admin_notice('Custom field ' . $number . ' is not configured ot the number is wrong or not specified');
            }

            if ($profile->is_private()) {
                return $this->build_field_admin_notice('Custom field ' . $number . ' is private and cannot be shown.');
            }

            $size = $attrs['size'] ?? '';
            $attrs['label'] = $attrs['label'] ?? $profile->name;
            $placeholder = $attrs['placeholder'] ?? $profile->placeholder;

            $buffer .= '<div class="tnp-field tnp-field-profile">';
            $buffer .= $this->_shortcode_label('profile_' . $profile->id, $attrs);

            // Text field
            if ($profile->type == TNP_Profile::TYPE_TEXT) {
                $buffer .= '<input class="tnp-profile tnp-profile-' . $number . '" id="tnp-profile_' . $number . '" type="text" size="' . esc_attr($size) . '" name="np' . $number . '" placeholder="' . esc_attr($placeholder) . '"';
                if ($profile->is_required()) {
                    $buffer .= ' required';
                }
                $buffer .= '>';
            }

            // Select field
            if ($profile->type == TNP_Profile::TYPE_SELECT) {
                $buffer .= '<select class="tnp-profile tnp-profile-' . $number . '" id="tnp-profile_' . $number . '" name="np' . $number . '"';
                if ($profile->is_required()) {
                    $buffer .= ' required';
                }
                $buffer .= '>';
                if (!empty($placeholder)) {
                    $buffer .= '<option value="" selected disabled>' . esc_html($placeholder) . '</option>';
                }
                foreach ($profile->options as $option) {
                    $buffer .= '<option>' . esc_html(trim($option)) . '</option>';
                }
                $buffer .= "</select>\n";
            }

            $buffer .= "</div>\n";

            return $buffer;
        }

        if ($name === 'track') {
            $label = $attrs['track'] ?? $this->get_form_text('track');
            $buffer .= '<div class="tnp-field tnp-field-checkbox tnp-field-track">';

            $buffer .= '<input type="checkbox" name="ntr_cb" class="tnp-track" id="tnp-' . $idx . '"> ';
            $buffer .= '<label for="tnp-' . $idx . '">';
            $buffer .= esc_html($label);
            $buffer .= '</label>';
            $buffer .= '<input type="hidden" name="ntr" value="1">';
            $buffer .= '</div>';

            return $buffer;
        }

        if (strpos($name, 'privacy') === 0) {
            $url = $attrs['url'] ?? $this->get_privacy_url();
            $label = $attrs['label'] ?? $this->get_form_text('privacy');

            $buffer .= '<div class="tnp-field tnp-field-checkbox tnp-field-privacy">';

            $buffer .= '<input type="checkbox" name="ny" required class="tnp-privacy" id="tnp-' . $idx . '"> ';
            $buffer .= '<label for="tnp-' . $idx . '">';
            if (!empty($url)) {
                $buffer .= '<a target="_blank" href="' . esc_attr($url) . '">';
            }
            $buffer .= esc_html($label);
            if (!empty($url)) {
                $buffer .= '</a>';
            }
            $buffer .= '</label>';
            $buffer .= '</div>';

            return $buffer;
        }

        if ($name === 'button') {

        }
    }

    /**
     * Builds the privacy field only for completely generated forms.
     *
     * @return string Empty id the privacy filed is not configured
     */
    function get_privacy_field($pre_html = '', $post_html = '') {

        $privacy_status = (int) $this->get_option('privacy_status', 'form');
        if (empty($privacy_status)) {
            return '';
        }

        $buffer = '<label>';
        if ($privacy_status === 1) {
            $buffer .= '<input type="checkbox" name="ny" required class="tnp-privacy"> ';
        }
        $url = $this->get_privacy_url();
        if (!empty($url)) {
            $buffer .= '<a target="_blank" href="' . esc_attr($url) . '">';
            $buffer .= esc_html($this->get_form_text('privacy')) . '</a>';
        } else {
            $buffer .= esc_html($this->get_form_text('privacy'));
        }

        $buffer .= "</label>";

        return $pre_html . $buffer . $post_html;
    }

    /**
     * Creates the button element. The DIV wrapper control is used by the
     * minimal form layout.
     *
     * @param array $attrs
     * @param bool $wrapper
     * @return string
     */
    function get_button($attrs, $wrapper = true) {
        // Prepare the button attrbutes
        $button_style = '';
        if (!empty($attrs['button_color'])) {
            $button_style = 'background-color:' . $attrs['button_color'] . ';';
        }
        if (!empty($attrs['button_radius'])) {
            $button_style = 'border-radius:' . $attrs['button_radius'] . ';';
        }
        $button_label = $this->get_text('subscribe', 'form');
        if (!empty($attrs['button_label'])) {
            $button_label = $attrs['button_label'];
        } else if (!empty($attrs['button'])) { // Old attribute
            $button_label = $attrs['button'];
        }
        // For the button container
        $button_div_style = '';
        if (!empty($attrs['button_align'])) {
            $button_div_style = 'text-align: ' . $attrs['button_align'];
        } else {
            if (is_rtl()) {
                $button_div_style = 'text-align: right';
            } else {
                $button_div_style = 'text-align: left';
            }
        }

        $b = '';
        if ($wrapper) {
            $b .= '<div class="tnp-field tnp-field-button" style="' . esc_attr($button_div_style) . '">';
        }
        $b .= '<input class="tnp-submit" type="submit" value="' . esc_attr($button_label) . '" style="' . esc_attr($button_style) . '">' . "\n";
        if ($wrapper) {
            $b .= "</div>\n";
        }
        return $b;
    }

    /**
     * The new standard form.
     *
     * @param string $referrer Deprecated since 6.9.1, use the "referrer" key on $attrs
     * @param string $action
     * @param string $attrs
     * @return string The full HTML form
     */
    function get_subscription_form($referrer = '', $action = null, $attrs = []) {

        if (!is_array($attrs)) {
            $attrs = [];
        }
        $attrs = array_merge(['class' => '', 'show_labels' => 'true', 'show_placeholders' => 'true'], $attrs);

        $buffer = '';

        // The referrer parameter is deprecated
        if (!empty($referrer)) {
            $attrs['referrer'] = $referrer;
        }

        $action = $this->build_action_url($attrs['action'] ?? $action ?? 's');
        $class = $attrs['class'] ?? '';

        $buffer .= '<div class="tnp tnp-subscription ' . esc_attr($class) . '">' . "\n";

        $buffer .= '<form method="post" action="' . esc_attr($action) . '"';

        if (!empty($attrs['id'])) {
            $buffer .= ' id="' . esc_attr($attrs['id']) . '"';
        }

        if (!empty($attrs['ajax'])) {
            $buffer .= ' class="tnp-ajax"';
        }

        $buffer .= '>' . "\n";

        $buffer .= $this->get_form_hidden_fields($attrs);

        $field_attrs = [];
        if ($attrs['show_labels'] === 'false') {
            $field_attrs['label'] = '';
        }

        if ($attrs['show_placeholders'] === 'false') {
            $field_attrs['placeholder'] = '';
        }

        $fields = wp_parse_list($attrs['fields'] ?? '');

        if (empty($fields)) {
            if (!empty($this->get_form_option('name_status'))) {
                $fields[] = 'first_name';
            }
            if (!empty($this->get_form_option('surname_status'))) {
                $fields[] = 'last_name';
            }
            $fields[] = 'email';
            if ($this->get_form_option('sex_status')) {
                $fields[] = 'gender';
            }
            $fields[] = 'customfields';
            $fields[] = 'lists';
            if ($this->get_option('track_status', 'form')) {
                $fields[] = 'track';
            }
            if ($this->get_option('privacy_status', 'form')) {
                $fields[] = 'privacy';
            }
        }


        if (!in_array('email', $fields)) {
            $field_attrs['name'] = 'email';
            $buffer .= $this->shortcode_newsletter_field($field_attrs);
        }

        foreach ($fields as $field) {
            switch ($field) {
                case 'email':
                    $field_attrs['name'] = 'email';
                    $buffer .= $this->shortcode_newsletter_field($field_attrs);
                    break;

                case 'first_name':
                    $field_attrs['name'] = 'first_name';
                    $buffer .= $this->shortcode_newsletter_field($field_attrs);
                    break;

                case 'last_name':
                    $field_attrs['name'] = 'last_name';
                    $buffer .= $this->shortcode_newsletter_field($field_attrs);
                    break;

                case 'gender':
                    $field_attrs['name'] = 'gender';
                    $buffer .= $this->shortcode_newsletter_field($field_attrs);
                    break;

                case 'customfields':
                    $ids = $this->get_form_option('customfields');
                    if (is_array($ids)) {
                        $field_attrs['name'] = 'customfield';
                        foreach ($ids as $id) {
                            $profile = $this->get_customfield($id);

                            if (!$profile || $profile->is_private()) {
                                continue;
                            }
                            $field_attrs['number'] = $id;
                            $buffer .= $this->shortcode_newsletter_field($field_attrs);
                        }
                    }
                    break;

                case 'lists':
                    if (empty($attrs['lists_field_label'])) {
                        $attrs['lists_field_label'] = '';
                    }
                    if (!empty($attrs['lists_field_layout']) && $attrs['lists_field_layout'] === 'dropdown') {
                        if (empty($attrs['lists_field_empty_label'])) {
                            $attrs['lists_field_empty_label'] = '';
                        }
                        $buffer .= $this->shortcode_newsletter_field(['name' => 'lists', 'layout' => 'dropdown', 'first_option_label' => $attrs['lists_field_empty_label'], 'label' => $attrs['lists_field_label']]);
                    } else {
                        $buffer .= $this->shortcode_newsletter_field(['name' => 'lists', 'label' => $attrs['lists_field_label']]);
                    }

                    break;

                case 'track':
                    $buffer .= $this->shortcode_newsletter_field(['name' => 'track']);
                    break;

                case 'privacy':
                    $buffer .= $this->get_privacy_field('<div class="tnp-field tnp-privacy-field">', '</div>');
                    break;
            }
        }

        $buffer .= $this->get_button($attrs);

        $buffer .= "</form>\n";
        $buffer .= "</div>\n";

        return $buffer;
    }

    /**
     * Gets a form from the old HTML forms (actually they support shortcodes as well).
     *
     * @todo Remove the {lists} replacement and replace it with a shortcode before the do_shortcode().
     *
     * @param int $number
     * @param array $attrs
     * @return string
     */
    function get_form($number, $attrs = []) {
        $options = $this->get_options('htmlforms');

        $form = $options['form_' . $number] ?? '';

        $form = do_shortcode($form);

        $action = $this->build_action_url('s');
        $form .= $this->get_form_hidden_fields($attrs);

        // The <form> tag should never be added...
        if (stripos($form, '<form') === false) {
            $form = '<form method="post" action="' . esc_attr($action) . '">' . $form . '</form>';
        }

        // For compatibility
        $form = str_replace('{newsletter_url}', $action, $form);

        // [newslette_field name="lists"] should be used instead.
        $form = $this->replace_lists($form);

        return $form;
    }

    /** Replaces on passed text the special tag {lists} that can be used to show the preferences as a list of checkbox.
     * They are called lists but on configuration panel they are named preferences!
     *
     * @deprecated
     * @param string $buffer
     * @return string
     */
    function replace_lists($buffer) {
        $checkboxes = '';
        $lists = $this->get_lists_for_subscription();
        foreach ($lists as $list) {
            $checkboxes .= '<input type="checkbox" name="nl[]" value="' . esc_attr($list->id) . '"> ' . esc_attr($list->name) . '<br />';
        }
        $buffer = str_replace('{lists}', $checkboxes, $buffer);
        $buffer = str_replace('{preferences}', $checkboxes, $buffer); // For compatibility
        return $buffer;
    }

    function notify_admin_on_subscription($user) {

        if (empty($this->get_main_option('notify'))) {
            return;
        }

        $email = trim($this->get_main_option('notify_email'));

        if (!$email) {
            return;
        }

        $message = $this->generate_admin_notification_message($user);

        $subject = $this->generate_admin_notification_subject('New subscription');

        Newsletter::instance()->mail($email, $subject, ['html' => $message]);
    }

    /**
     * Builds the minimal subscription form, with only the email field and inline
     * submit button. If enabled the privacy checkbox is added.
     *
     * @param type $attrs
     * @return string
     */
    function get_subscription_form_minimal($attrs) {
        if (!is_array($attrs)) {
            $attrs = [];
        }

        // Compatibility
        if (isset($attrs['button_label'])) {
            $attrs['button'] = $attrs['button_label'];
        }

        $attrs = array_merge(['class' => '', 'referrer' => 'minimal',
            'button' => $this->get_form_text('subscribe'), 'button_color' => '',
            'button_radius' => '', 'placeholder' => $this->get_form_text('email'),
            'name_placeholder' => $this->get_form_text('name'),
            'show_name' => 0, 'align' => 'center'], $attrs);

        $show_name = !empty($attrs['show_name']);

        $form = '<div class="tnp tnp-subscription-minimal '
                . ($show_name ? 'with-name ' : ' ')
                . esc_attr($attrs['class']) . '">';

        $form .= '<form action="' . esc_attr($this->build_action_url('s')) . '" method="post"';
        if (!empty($attrs['id'])) {
            $form .= ' id="' . esc_attr($attrs['id']) . '"';
        }
        $style = 'text-align: ' . esc_attr($attrs['align']);

        $form .= ' style="' . $style . '"';

        $form .= '>';

        $form .= $this->get_form_hidden_fields($attrs);

        $form .= '<input class="tnp-email" type="email" required name="ne" value="" placeholder="' . esc_attr($attrs['placeholder']) . '">';

        if ($show_name) {
            $form .= '<input class="tnp-name" type="text" required name="nn" value="" placeholder="' . esc_attr($attrs['name_placeholder']) . '">';
        }

//        $form .= '<input class="tnp-submit" type="submit" value="' . esc_attr($attrs['button']) . '"'
//                . ' style="background-color:' . esc_attr($attrs['button_color']) . '">';

        $form .= $this->get_button($attrs, false);

        $form .= $this->get_privacy_field('<div class="tnp-field tnp-privacy-field">', '</div>');

        $form .= "</form></div>\n";

        return $form;
    }

    /**
     * Implementation of the [newsletter_form] shortcode. If there is some content,
     * is must ocntains the field shortcodes [newsletter_field] composing the final
     * form.
     *
     * @param array $attrs
     * @param string $content
     * @return string
     */
    function shortcode_newsletter_form($attrs, $content = '') {

        if (isset($attrs['type']) && $attrs['type'] === 'minimal') {
            return $this->get_subscription_form_minimal($attrs);
        }

        // Custom form using the [newsletter_field] shortcodes
        if (!empty($content)) {
            return $this->get_subscription_form_custom($attrs, $content);
        }

        // Custom form hand coded and saved in the custom forms option
        if (isset($attrs['form'])) {
            return $this->get_form((int) $attrs['form'], $attrs);
        }

        // Custom hand coded form (as above, new syntax)
        if (isset($attrs['number'])) {
            return $this->get_form((int) $attrs['number'], $attrs);
        }

        return $this->get_subscription_form(null, null, $attrs);
    }

    /**
     * Possible content to show when the [newsletter] shortcode is executed in the
     * public Newsletter page.
     *
     * @param string $message Type of the message to show
     * @param object $user The current subscriber (may be null)
     * @return string
     */
    function hook_newsletter_shortcode($message, $user) {
        if (!in_array($message, ['subscription', 'confirmed', 'confirmation', 'error'], true)) {
            return;
        }

        // Some help for administrators while configuring the plugin... (it saved tons of support requests!)
        if (current_user_can('administrator')) {
            $m = '<a href="' . esc_attr($this->get_admin_edit_url($message)) . '" target="_blank">Edit this content</a>.';
            if ($message === 'error') {
                $m .= '<br><br>';
                $m .= 'Error message shown because the email address already registered and repeated registration not allowed.<br>';
                $m .= 'That behavior can be changed in the Subscription configuration.';
            }
            $this->show_admin_notice($m);
        }

        echo $this->get_text($message . '_text');

        if ($message === 'confirmed') {
            echo $this->get_option($message . '_tracking');
        }
    }

    /**
     * Possible content to show when the [newsletter] shortcode is executed in the
     * public Newsletter page and accessed with a dummy subscriber (preview from the admin
     * pages).
     *
     * @param string $message
     * @param object $user It should be a dummy subscriber ($user->id === 0)
     * @return string
     */
    function hook_newsletter_shortcode_dummy($message, $user) {
        if (!in_array($message, ['subscription', 'confirmed', 'confirmation', 'error'], true)) {
            return;
        }

        $this->show_admin_notice('Preview of the content with a dummy subscriber.');

        echo $this->get_text($message . '_text');
    }

    function get_admin_edit_url($message) {
        switch ($message) {
            case 'confirmation':
                $url = admin_url('admin.php?page=newsletter_subscription_confirmation');
                break;
            case 'confirmed':
                $url = admin_url('admin.php?page=newsletter_subscription_welcome');
                break;
            default:
                $url = admin_url('admin.php?page=newsletter_subscription_options');
        }

        if ($this->is_multilanguage()) {
            $language = $this->language();
            if (empty($language)) {
                $language = 'all';
            }
            $url .= '&lang=' . urlencode($language);
        }
        return $url;
    }
}

NewsletterSubscription::instance();

// Compatibility code

/**
 * @deprecated
 * @param int $number
 */
function newsletter_form($number = null) {
    if ($number != null) {
        echo NewsletterSubscription::instance()->get_form($number);
    } else {
        echo NewsletterSubscription::instance()->get_subscription_form();
    }
}
