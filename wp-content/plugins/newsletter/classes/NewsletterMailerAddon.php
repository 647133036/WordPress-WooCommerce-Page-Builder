<?php

defined('ABSPATH') || exit;

/**
 * Used by mailer add-ons as base-class. Some specific options collected by the mailer
 * are interpreted automatically.
 *
 * They are:
 *
 * `enabled` if not empty it means the mailer is active and should be registered
 *
 * The options are set up in the constructor, there is no need to setup them later.
 */
class NewsletterMailerAddon extends NewsletterAddon {

    var $enabled = false;
    var $menu_title = null;
    var $menu_description = null;
    var $menu_slug = null;
    var $dir = '';
    var $index_page = null;
    var $logs_page = null;
    var $webhook_logger = null;

    function __construct($name, $version = '0.0.0', $dir = '') {
        parent::__construct($name, $version, $dir);
        $this->dir = $dir;
        $this->setup_options();
        $this->enabled = !empty($this->options['enabled']);
        $this->menu_slug = $this->name;
    }

    /**
     * This method must be called as `parent::init()` is overridden.
     */
    function init() {
        parent::init();
        if ($this->enabled) {
            add_action('newsletter_register_mailer', function () {
                Newsletter::instance()->register_mailer($this->get_mailer());
            });
        }

        if (is_admin()) {
            $this->index_page = 'newsletter_' . $this->menu_slug . '_index';
            $this->logs_page = 'newsletter_' . $this->menu_slug . '_logs';
        }
    }

    function upgrade($first_install = false) {
        parent::upgrade($first_install);
        $this->merge_defaults(['turbo' => 0, 'enabled' => 0]);
    }

    function deactivate() {
        parent::deactivate();

        // For delivery services without webkooks
        wp_clear_scheduled_hook('newsletter_' . $this->name . '_bounce');
    }

    function admin_menu() {

        if (!current_user_can('administrator')) {
            return;
        }

        add_submenu_page('newsletter_main_index', $this->menu_title, '<span class="tnp-side-menu">' . esc_html($this->menu_title) . '</span>', 'manage_options', $this->index_page,
                function () {
                    /** @since 8.4.0 */
                    require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                    $controls = new NewsletterControls();
                    if (file_exists($this->dir . '/admin/index.php')) {
                        require $this->dir . '/admin/index.php';
                    } else {
                        require $this->dir . '/index.php';
                    }
                }
        );

        if (file_exists($this->dir . '/admin/logs.php')) {
            add_submenu_page('admin.php', __('Logs', 'newsletter'), __('Logs', 'newsletter'), 'manage_options', $this->logs_page,
                    function () {
                        /** @since 8.4.0 */
                        require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                        $controls = new NewsletterControls();
                        require $this->dir . '/admin/logs.php';
                    }
            );
        }
    }

    function newsletter_menu() {
        if (!current_user_can('administrator')) {
            return;
        }
        $this->add_settings_menu_page($this->menu_title, '?page=' . $this->index_page);
    }

    function set_warnings($controls) {
//        if (!$this->enabled) {
//            $controls->warnings[] = 'Enable to send with this service.';
//        }

        $current_mailer = Newsletter::instance()->get_mailer();
        if ($current_mailer && $current_mailer->name !== 'default' && $this->enabled && get_class($current_mailer) != get_class($this->get_mailer())) {
            $controls->warnings[] = 'Another delivery addon is active: ' . esc_html($current_mailer->get_description());
        }

        if ($this->enabled && class_exists('NewsletterBounce')) {
            $controls->warnings[] = 'The Bounce addon is active and should be disabled on the <a href="plugins.php">plugins page</a> (bounces are managed by this addon)';
        }
    }

    function get_status_badge() {
        if ($this->enabled) {
            return '<span class="tnp-badge-green">' . esc_html__('Enabled', 'newsletter') . '</span>';
        } else {
            return '<span class="tnp-badge-orange">' . esc_html__('Disabled', 'newsletter') . '</span>';
        }
    }

    /** @since 8.4.0 */
    function echo_status_badge() {
        if ($this->enabled) {
            echo '<span class="tnp-badge-green">', esc_html__('Enabled', 'newsletter'), '</span>';
        } else {
            echo '<span class="tnp-badge-orange">', esc_html__('Disabled', 'newsletter'), '</span>';
        }
    }

    function get_title() {
        return esc_html($this->menu_title) . $this->get_status_badge();
    }

    /**
     * @since 8.4.0
     */
    function echo_title() {
        echo esc_html($this->menu_title);
        $this->echo_status_badge();
    }

    /**
     * @since 8.5.9
     */
    function set_bounced($email, $type = 'permanent', $data = '') {
        global $wpdb;
        $logger = $this->get_logger();
        $logger->info($email . ' bounced');
        $user = Newsletter::instance()->get_user($email);
        if (!$user) {
            Newsletter\Logs::add($this->name, $email . ' - ' . $type . ' bounce - no subscriber found', 0, $data);
            $logger->info($email . ' not found');
            return;
        }

        Newsletter::instance()->set_user_status($user, TNP_User::STATUS_BOUNCED);
        Newsletter::instance()->add_user_log($user, $this->name);
        Newsletter\Logs::add($this->name, $email . ' - ' . $type . ' bounce', 0, $data);
        do_action('newsletter_user_bounced', $user);
    }

    function set_bounced_hard($email, $data = '') {
        $this->set_bounced($email, 'permanent', $data);
    }

    function set_bounced_soft($email, $data = '') {
        $this->set_bounced($email, 'transient', $data);
    }

    /**
     * @since 8.5.9
     */
    function set_complained($email, $data = '') {
        global $wpdb;
        $logger = $this->get_logger();
        $logger->info($email . ' complained');
        $user = Newsletter::instance()->get_user($email);
        if (!$user) {
            Newsletter\Logs::add($this->name, $email . ' - complaint - no subscriber found', 0, $data);
            $logger->info($email . ' not found');
            return;
        }

        Newsletter::instance()->set_user_status($user, TNP_User::STATUS_COMPLAINED);
        Newsletter::instance()->add_user_log($user, $this->name);
        Newsletter\Logs::add($this->name, $email . ' complaint', 0, $data);
        do_action('newsletter_user_complained', $user);
    }

    /**
     * @since 8.5.9
     */
    function set_unsubscribed($email, $data = '') {
        global $wpdb;
        $logger = $this->get_logger();
        $logger->info($email . ' unsubscribed');
        $user = Newsletter::instance()->get_user($email);
        if (!$user) {
            Newsletter\Logs::add($this->name, $email . ' - unsubscribe - no subscriber found', 0, $data);
            $logger->info($email . ' not found');
            return;
        }

        Newsletter::instance()->set_user_status($user, TNP_User::STATUS_UNSUBSCRIBED);
        Newsletter::instance()->add_user_log($user, $this->name);
        Newsletter\Logs::add($this->name, $email . ' unsubscribe', 0, $data);
        do_action('newsletter_user_unsubscribed', $user);
    }

    /**
     * Must return an implementation of NewsletterMailer.
     * @return NewsletterMailer
     */
    function get_mailer() {
        return null;
    }

    function get_last_run() {
        return get_option('newsletter_' . $this->name . '_last_run', 0);
    }

    function save_last_run($time) {
        update_option('newsletter_' . $this->name . '_last_run', $time);
    }

    function save_options($options, $language = '') {
        parent::save_options($options, $language);
        $this->enabled = !empty($options['enabled']);
    }

    /**
     * @since 8.5.2
     */
    function get_webhook_url() {
        return admin_url('admin-ajax.php') . '?action=newsletter-' . $this->name;
    }

    /**
     * The logger (on file) for tracking the webhook activity.
     * @return NewsletterLogger
     * @since 8.5.2
     */
    function get_webhook_logger() {
        if (!$this->webhook_logger) {
            $this->webhook_logger = new NewsletterLogger($this->name . '-webhook');
        }
        return $this->webhook_logger;
    }

    /**
     * Add a log for a received webhook event then shown on the addon's log page.
     *
     * @since 8.5.2
     */
    function webhook_log($description, $data = null) {
        Newsletter\Logs::add($this->name, $description, 0, $data);
    }

    /**
     * The function to be implemented to managed the webhook event.
     *
     * @since 8.5.2
     */
    function webhook_callback() {
        //$logger = $this->get_webhook_logger();
        // ...
    }

    /**
     * Return the webhooks in the delivery service custom format.
     *
     * @return array|WP_Error
     */
    function get_webhooks() {
        return [];
    }

    /**
     * Returns a TNP_Mailer_Message built to send a test message to the <code>$to</code>
     * email address.
     *
     * @param string $to
     * @param string $subject
     * @return TNP_Mailer_Message
     */
    static function get_test_message($to, $subject = '', $type = '') {
        $message = new TNP_Mailer_Message();
        $message->to = $to;
        $message->to_name = '';
        if (empty($type) || $type == 'html') {
            $message->body = file_get_contents(NEWSLETTER_DIR . '/includes/test-message.html');
            $message->body = str_replace('{plugin_url}', Newsletter::plugin_url(), $message->body);
        }

        if (empty($type) || $type == 'text') {
            $message->body_text = 'This is the TEXT version of a test message. You should see this message only if you email client does not support the rich text (HTML) version.';
        }

        //$message->headers['X-Newsletter-Email-Id'] = '0';

        if (empty($subject)) {
            $message->subject = '[' . get_option('blogname') . '] Test message from Newsletter (' . date(DATE_ISO8601) . ')';
        } else {
            $message->subject = $subject;
        }

        if ($type) {
            $message->subject .= ' - ' . $type . ' only';
        }

        $message->from = Newsletter::instance()->get_sender_email();
        $message->from_name = Newsletter::instance()->get_sender_name();
        $message->headers['X-Newsletter'] = 'test';
        return $message;
    }

    /**
     * Returns a set of test messages to be sent to the specified email address. Used for
     * turbo mode tests. Each message has a different generated subject.
     *
     * @param string $to The destination mailbox
     * @param int $count Number of message objects to create
     * @return TNP_Mailer_Message[]
     */
    function get_test_messages($to, $count, $type = '') {
        $messages = array();
        for ($i = 0; $i < $count; $i++) {
            $messages[] = self::get_test_message($to, '[' . get_option('blogname') . '] Test message ' . ($i + 1) . ' from Newsletter (' . date(DATE_ISO8601) . ')', $type);
        }
        return $messages;
    }
}
