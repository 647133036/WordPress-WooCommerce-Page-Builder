<?php

/*
  Plugin Name: Newsletter
  Plugin URI: https://www.thenewsletterplugin.com
  Description: Newsletter is a cool plugin to create your own subscriber list, to send newsletters, to build your business.
  Version: 9.4.7
  Author: The Newsletter Team
  Author URI: https://www.thenewsletterplugin.com
  Disclaimer: Use at your own risk. No warranty expressed or implied is provided.
  Text Domain: newsletter
  License: GPLv2 or later
  Requires at least: 6.1
  Requires PHP: 7.0

  Copyright 2009-2026 The Newsletter Team (email: info@thenewsletterplugin.com, web: https://www.thenewsletterplugin.com)

  Newsletter is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 2 of the License, or
  any later version.

  Newsletter is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Newsletter. If not, see https://www.gnu.org/licenses/gpl-2.0.html.

 */

define('NEWSLETTER_VERSION', '9.4.7');

global $wpdb, $newsletter;

// For acceptance tests, DO NOT CHANGE
if (!defined('NEWSLETTER_DEBUG'))
    define('NEWSLETTER_DEBUG', false);

if (!defined('NEWSLETTER_EXTENSION_UPDATE'))
    define('NEWSLETTER_EXTENSION_UPDATE', true);

if (!defined('NEWSLETTER_EMAILS_TABLE'))
    define('NEWSLETTER_EMAILS_TABLE', $wpdb->prefix . 'newsletter_emails');

if (!defined('NEWSLETTER_USERS_TABLE'))
    define('NEWSLETTER_USERS_TABLE', $wpdb->prefix . 'newsletter');

if (!defined('NEWSLETTER_USERS_META_TABLE'))
    define('NEWSLETTER_USERS_META_TABLE', $wpdb->prefix . 'newsletter_user_meta');

if (!defined('NEWSLETTER_STATS_TABLE'))
    define('NEWSLETTER_STATS_TABLE', $wpdb->prefix . 'newsletter_stats');

if (!defined('NEWSLETTER_SENT_TABLE'))
    define('NEWSLETTER_SENT_TABLE', $wpdb->prefix . 'newsletter_sent');

if (!defined('NEWSLETTER_LOGS_TABLE'))
    define('NEWSLETTER_LOGS_TABLE', $wpdb->prefix . 'newsletter_logs');

//if (!defined('NEWSLETTER_SEND_DELAY'))
//    define('NEWSLETTER_SEND_DELAY', 0);

if (!defined('NEWSLETTER_USE_POST_GALLERY'))
    define('NEWSLETTER_USE_POST_GALLERY', false);

// Empty or "ajax"
if (!defined('NEWSLETTER_TRACKING_TYPE'))
    define('NEWSLETTER_TRACKING_TYPE', '');

if (!defined('NEWSLETTER_PAGE_WARNING'))
    define('NEWSLETTER_PAGE_WARNING', true);

// Empty or "ajax"
if (!defined('NEWSLETTER_ACTION_TYPE'))
    define('NEWSLETTER_ACTION_TYPE', '');

define('NEWSLETTER_SLUG', 'newsletter');

define('NEWSLETTER_DIR', __DIR__);
define('NEWSLETTER_INCLUDES_DIR', __DIR__ . '/includes');

if (!defined('NEWSLETTER_LIST_MAX'))
    define('NEWSLETTER_LIST_MAX', 40);

if (!defined('NEWSLETTER_PROFILE_MAX'))
    define('NEWSLETTER_PROFILE_MAX', 20);

if (!defined('NEWSLETTER_FORMS_MAX'))
    define('NEWSLETTER_FORMS_MAX', 10);

if (!defined('NEWSLETTER_TEST'))
    define('NEWSLETTER_TEST', false);

spl_autoload_register(function ($class) {
    static $dir = __DIR__ . '/classes/';

    if (strncmp('Newsletter', $class, 10) === 0) {
        $file = $dir . str_replace('\\', '/', $class) . '.php';

        if (file_exists($file)) {
//            if (NEWSLETTER_DEBUG) {
//                $memory = size_format(memory_get_usage(), 1);
//            }
            require $file;
        }
    }
});

require_once NEWSLETTER_INCLUDES_DIR . '/defaults.php';
require_once NEWSLETTER_INCLUDES_DIR . '/classes.php';
require_once NEWSLETTER_INCLUDES_DIR . '/module-base.php';
require_once NEWSLETTER_INCLUDES_DIR . '/module.php';
require_once NEWSLETTER_INCLUDES_DIR . '/TNP.php';
require_once NEWSLETTER_INCLUDES_DIR . '/cron.php';

class Newsletter extends NewsletterModule {

    // Limits to respect to avoid memory, time or provider limits
    var $time_start;
    var $time_limit = 0;
    var $max_emails = null;
    var $mailer = null;
    var $action = '';
    static $instance;

    /**
     * @return Newsletter
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {

        // Do not weait plugins_loaded, since there are plugins removing parameters...
        if (!is_admin()) {
            $this->action = sanitize_key($_POST['na'] ?? $_GET['na'] ?? '');
        }

        $this->time_start = time();

        parent::__construct('main');

        // The main actions of WP during the inizialization phase, in order
        add_action('plugins_loaded', [$this, 'hook_plugins_loaded']);
        add_action('init', [$this, 'hook_init'], 1);
        add_action('wp_loaded', [$this, 'hook_wp_loaded'], 1);

        add_action('newsletter', [$this, 'hook_newsletter'], 1);

        add_action('newsletter_send_error_recover', [$this, 'hook_newsletter_send_error_recover']);

        add_action('wp_ajax_tnp', [$this, 'ajax_action']);
        add_action('wp_ajax_nopriv_tnp', [$this, 'ajax_action']);

        if (is_admin()) {
            add_action('wp_ajax_newsletter-log', function () {
                check_ajax_referer('newsletter-log');
                if (!current_user_can('administrator')) {
                    die('no admin');
                }
                $log = Newsletter\Logs::get((int) $_GET['id']);
                header('Content-Type: text/plain;charset=utf-8');
                if (empty($log->data))
                    echo '[no data]';
                else
                    echo $log->data;
                die();
            });
        }

        register_activation_hook(__FILE__, [$this, 'hook_activate']);
        register_deactivation_hook(__FILE__, [$this, 'hook_deactivate']);
    }

    function hook_newsletter_send_error_recover($id = null) {
        $email = $this->get_email($id);
        if (!$email) {
            return;
        }

        if ($email->status !== TNP_Email::STATUS_ERROR) {
            return;
        }

        $this->set_email_status($email, TNP_Email::STATUS_SENDING);
        Newsletter\Logs::add('newsletter-' . $email->id, 'Auto recovery from error');
    }

    /**
     * Action request via AJAX.
     */
    function ajax_action() {

        $this->action = sanitize_key($_REQUEST['na'] ?? '');
        $this->do_action();

        die();
    }

    /**
     * When all plugins have been loaded (but not initialized).
     */
    function hook_plugins_loaded() {

        // Used to load dependant modules
        do_action('newsletter_loaded', NEWSLETTER_VERSION);

        // WPML is not ready here
        //$this->setup_language();

        if (function_exists('load_plugin_textdomain')) {
            load_plugin_textdomain('newsletter', false, plugin_basename(__DIR__) . '/languages');
        }
    }

    /**
     * Plugins initialization.
     *
     * @global wpdb $wpdb
     */
    function hook_init() {

        // Here since there are still newsletter actions used by the admin modules
        if (current_user_can('administrator')) {
            self::$is_allowed = true;
        } else {
            $roles = $this->get_main_option('roles');
            if (!empty($roles)) {
                foreach ($roles as $role) {
                    if (current_user_can($role)) {
                        self::$is_allowed = true;
                        break;
                    }
                }
            }
        }

        if ($this->get_option('debug')) {
            ini_set('log_errors', 1);
            ini_set('error_log', WP_CONTENT_DIR . '/logs/newsletter/php-' . date('Y-m') . '-' . get_option('newsletter_logger_secret') . '.txt');
        }

        if (!is_admin() || defined('DOING_AJAX') && DOING_AJAX) {
            // Shortcode for the Newsletter page
            add_shortcode('newsletter', array($this, 'shortcode_newsletter'));
            add_shortcode('newsletter_replace', [$this, 'shortcode_newsletter_replace']);
        }

        add_filter('site_transient_update_plugins', [$this, 'hook_site_transient_update_plugins']);

        add_action('wp_enqueue_scripts', [$this, 'hook_wp_enqueue_scripts']);

        do_action('newsletter_init');

        if (is_admin()) {
            if (!wp_next_scheduled('newsletter_clean')) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', 'newsletter_clean');
            }
            if (!wp_next_scheduled('newsletter_update')) {
                wp_schedule_event(time() + HOUR_IN_SECONDS * 2, 'daily', 'newsletter_update');
            }
        }
        add_action('newsletter_clean', [$this, 'newsletter_clean']);
    }

    function newsletter_clean() {
        Newsletter\Logs::clean();
    }

    function hook_wp_loaded() {

        // After everything has been loaded, since the plugin url could be changed (usually for multidomain installations)
        self::$plugin_url = plugins_url('newsletter');

        $this->setup_language();

        // Avoid upgrade during AJAX
        if (!defined('DOING_AJAX')) {
            $old_version = get_option('newsletter_version', '0.0.0');
            if ($old_version !== NEWSLETTER_VERSION) {
                include_once NEWSLETTER_INCLUDES_DIR . '/upgrade.php';
                update_option('newsletter_version', NEWSLETTER_VERSION);
            }
        }

        $this->do_action();
    }

    function do_action() {

        if (empty($this->action)) {
            return false;
        }

        if ($this->action === 'test') {
            // This response is tested, do not change it!
            echo 'ok';
            die();
        }

        if ($this->action === 'nul') {
            $this->dienow('This link is not active on newsletter preview', 'You can send a test message to test subscriber to have the real working link.');
        }

        // Get the subscriber from the signed and timed key in the request, if valid.
        // This way, each action link has a life timespan.
        $user = $this->get_user_by_key($_REQUEST['nk'] ?? '', $this->action);

        if ($user && $user->id === 0 && current_user_can('administrator')) {
            $this->switch_language($user);
            do_action('newsletter_action_dummy', $this->action, $user, null);
            return;
        }

        // Old token temporary management
        if (!$user) {
            $user = $this->get_user_by_old_key($_REQUEST['nk'] ?? '');
        }

        $email = $this->get_email_from_request();

        $this->switch_language($user);

        // An action may not require a subscriber (for example the subscription)
        do_action('newsletter_action', $this->action, $user, $email);
    }

    function hook_activate() {
        //include_once NEWSLETTER_INCLUDES_DIR . '/upgrade.php';
        //update_option('newsletter_version', NEWSLETTER_VERSION);
    }

    function first_install() {
        update_option('newsletter_show_welcome', '1', false);
    }

    function is_allowed() {
        return self::$is_allowed;
    }

    /**
     * Sets the internal language used by admin panels to extract the language-related
     * values.
     */
    function setup_language() {

        if (defined('NEWSLETTER_MULTILANGUAGE') && !NEWSLETTER_MULTILANGUAGE) {
            return;
        }

        self::$is_multilanguage = apply_filters('newsletter_is_multilanguage', class_exists('SitePress') || function_exists('pll_default_language') || class_exists('TRP_Translate_Press'));

        if (self::$is_multilanguage) {
            self::$language = self::_get_current_language();
            self::$locale = self::get_locale(self::$language);
        }
    }

    static function _get_current_language() {

        if (defined('NEWSLETTER_MULTILANGUAGE') && !NEWSLETTER_MULTILANGUAGE) {
            return '';
        }

        // WPML
        if (class_exists('SitePress')) {
            $current_language = apply_filters('wpml_current_language', '');
            if ($current_language == 'all') {
                $current_language = '';
            }
            return $current_language;
        }

        // Polylang
        if (function_exists('pll_current_language')) {
            return pll_current_language();
        }

        // Trnslatepress and/or others
        $current_language = apply_filters('newsletter_current_language', '');

        return $current_language;
    }

    /**
     * Public CSS for subscription forms and profile form and widgets.
     */
    function hook_wp_enqueue_scripts() {
        $css = $this->get_option('css');

        if (empty($this->get_option('css_disabled')) && apply_filters('newsletter_enqueue_style', true)) {
            wp_enqueue_style('newsletter', $this->plugin_url() . '/style.css', [], NEWSLETTER_VERSION);

            if (!empty($css)) {
                wp_add_inline_style('newsletter', $css);
            }
        } else {
            if (!empty($css)) {
                add_action('wp_head', function () {
                    echo '<style>', $this->get_option('css'), '</style>';
                });
            }
        }
        $data = ['action_url' => admin_url('admin-ajax.php')];
        wp_enqueue_script('newsletter', $this->plugin_url() . '/main.js', [], NEWSLETTER_VERSION, true);
        wp_localize_script('newsletter', 'newsletter_data', $data);
    }

    function get_message_key_from_request() {
        if (empty($_GET['nm'])) {
            return 'subscription';
        }
        $key = $_GET['nm'];
        switch ($key) {
            case 's': return 'confirmation';
            case 'c': return 'confirmed';
            case 'u': return 'unsubscription';
            case 'uc': return 'unsubscribed';
            case 'p':
            case 'pe':
                return 'profile';
            default: return $key;
        }
    }

    function get_current_dummy_user() {
        if (isset($_REQUEST['nk'])) {
            list($id, $token) = explode('-', $_REQUEST['nk']);
            if ($id === '0') {
                return $this->get_dummy_user($token);
            }
        }
        return null;
    }

    /**
     * The main shortcode to be used in the reserved page.
     * @todo Separate below the code for the shortcode and the one for the "subscription" content
     *
     * @global wpdb $wpdb
     * @param array $attrs
     * @param string $content
     * @return string
     */
    function shortcode_newsletter($attrs, $content) {
        static $executing = false;

        // To avoid loops
        if ($executing) {
            return '';
        }

        $executing = true;

        $message_key = sanitize_key($_GET['nm'] ?? 'subscription');

        $user = $this->get_current_user();
        $this->switch_language($user);
        if ($user && $user->id === 0) {
            ob_start();
            do_action('newsletter_shortcode_dummy', $message_key, $user);
            $message = ob_get_clean();
            // Do not replace here so the tags are visible, useful when previewing.
            $message = do_shortcode($message);
            $executing = false;
            return $message;
        }

        ob_start();
        do_action('newsletter_shortcode', $message_key, $user);
        $message = ob_get_clean();
        $message = $this->replace($message, $user, null, 'page');
        $message = do_shortcode($message);

        $executing = false;
        return $message;
    }

    function shortcode_newsletter_replace($attrs, $content) {
        $content = do_shortcode($content);
        $content = $this->replace($content, $this->get_current_user(), null, 'page');
        return $content;
    }

    /**
     * Runs every 5 minutes and look for emails that need to be processed.
     */
    function hook_newsletter() {
        NewsletterEngine::instance()->run();
        do_action('newsletter_engine_run');
    }

    function get_send_speed($email = null) {
        $this->logger->debug(__METHOD__ . '> Computing delivery speed');
        $mailer = $this->get_mailer();
        $speed = (int) $mailer->get_speed();
        if (!$speed) {
            $this->logger->debug(__METHOD__ . '> Speed not set by mailer, use the default');
            $speed = (int) $this->get_main_option('scheduler_max');
        } else {
            $this->logger->debug(__METHOD__ . '> Speed set by mailer');
        }

        $speed = max($speed, (int) (3600 / NEWSLETTER_REAL_CRON_INTERVAL));

        $this->logger->debug(__METHOD__ . '> Speed: ' . $speed);
        return $speed;
    }

    function get_runs_per_hour() {
        return (int) (3600 / NEWSLETTER_REAL_CRON_INTERVAL);
    }

    /**
     * Used by Autoresponder.
     *
     * @return int
     */
    function get_emails_per_run() {
        $speed = $this->get_send_speed();
        $max = (int) ($speed / $this->get_runs_per_hour());

        return $max;
    }

    /**
     * Sends an email to targeted users or to given users. If a list of users is given (usually a list of test users)
     * the query inside the email to retrieve users is not used.
     *
     * @global wpdb $wpdb
     * @global type $newsletter_feed
     * @param TNP_Email $email
     * @param array $users
     * @return boolean|WP_Error True if the process completed, false if limits was reached. On false the caller should no continue to call it with other emails.
     */
    function send($email, $users = null, $test = false) {
        return NewsletterEngine::instance()->send($email, $users, $test);
    }

    /**
     * @deprecated since version 7.3.0
     */
    function limits_exceeded() {
        return false;
    }

    /**
     * @deprecated since version 6.0.0
     */
    function register_mail_method($callable) {

    }

    function register_mailer($mailer) {
        if ($mailer instanceof NewsletterMailer) {
            $this->mailer = $mailer;
        }
    }

    /**
     * Returns the current registered mailer which must be used to send emails.
     *
     * @return NewsletterMailer
     */
    function get_mailer() {
        if ($this->mailer) {
            return $this->mailer;
        }

        do_action('newsletter_register_mailer');

        if (!$this->mailer) {
            $this->mailer = new NewsletterDefaultMailer();
        }
        return $this->mailer;
    }

    /**
     *
     * @param TNP_Mailer_Message $message
     * @return type
     */
    function deliver($message) {
        $mailer = $this->get_mailer();
        if (empty($message->from)) {
            $message->from = $this->get_sender_email();
        }
        if (empty($message->from_name)) {
            $mailer->from_name = $this->get_sender_name();
        }
        return $mailer->send($message);
    }

    /**
     *
     * @param type $to
     * @param type $subject
     * @param string|array $message If string is considered HTML, is array should contains the keys "html" and "text"
     * @param type $headers
     * @param type $enqueue
     * @param type $from
     * @return boolean
     */
    function mail($to, $subject, $message, $headers = array(), $enqueue = false, $from = false) {

        if (!$subject) {
            $this->logger->error('mail> Subject empty, skipped');
            return true;
        }

        $mailer_message = new TNP_Mailer_Message();
        $mailer_message->to = $to;
        $mailer_message->subject = $subject;
        $mailer_message->from = $this->get_option('sender_email');
        $mailer_message->from_name = $this->get_option('sender_name');

        if (!$headers) {
            $mailer_message->headers = $headers;
        }
        $mailer_message->headers['X-Auto-Response-Suppress'] = 'OOF, AutoReply';

        // Message carrige returns and line feeds clean up
        if (!is_array($message)) {
            $mailer_message->body = $this->clean_eol($message);
        } else {
            if (!empty($message['text'])) {
                $mailer_message->body_text = $this->clean_eol($message['text']);
            }

            if (!empty($message['html'])) {
                $mailer_message->body = $this->clean_eol($message['html']);
            }
        }

        $mailer = $this->get_mailer();

        $r = $mailer->send($mailer_message);

        return !is_wp_error($r);
    }

    function hook_deactivate() {
        wp_clear_scheduled_hook('newsletter');
    }

    function find_file($file1, $file2) {
        if (is_file($file1))
            return $file1;
        return $file2;
    }

    function hook_site_transient_update_plugins($value) {
        return Newsletter\Addons::update_plugins_transient($value, $this->get_license_key());
    }

    /**
     * @deprecated since version 6.1.9
     */
    function get_extension_version($extension_id) {
        return null;
    }

    /**
     * @deprecated since version 6.1.9
     */
    function set_extension_update_data($value, $extension) {
        return $value;
    }

    /**
     * Retrieve the extensions form the tnp site
     * @return array
     */
    function getTnpExtensions() {
        return Newsletter\Addons::get_addons();
    }

    function clear_extensions_cache() {
        delete_transient('tnp_extensions_json');
    }

    /**
     * @deprecated
     */
    function add_panel($key, $panel) {

    }

    function has_license() {
        return !empty($this->get_main_option('contract_key'));
    }

    function get_sender_name() {
        return $this->get_main_option('sender_name');
    }

    function get_sender_email() {
        $sender_email = $this->get_mailer()->get_sender_email();
        return $sender_email ?: $this->get_main_option('sender_email');
    }

    function get_reply_to() {
        return $this->get_main_option('reply_to');
    }

    /**
     *
     * @return int
     */
    function get_newsletter_page_id() {
        return (int) $this->get_option('page');
    }

    /**
     *
     * @return WP_Post
     */
    function get_newsletter_page() {
        $page_id = $this->get_newsletter_page_id();
        if (!$page_id) {
            return false;
        }
        return get_post($page_id);
    }

    /**
     * Returns the Newsletter public page URL or an alternative URL if that page if not
     * configured or not available.
     *
     * @staticvar string $url
     * @return string
     */
    function get_newsletter_page_url($language = '') {
        $page = $this->get_newsletter_page();

        if (!$page || $page->post_status !== 'publish') {
//            if (current_user_can('administrator')) {
//                $this->dienow('Public page not available. This message is shown only to administrators, user will see the home page.'
//                        . 'Please review the "public page" setting on the Newsletter\'s main configuration.');
//            }
            return home_url();
        }

        $url = get_permalink($page->ID);

        return $url;
    }

    function get_license_key() {
        return Newsletter\License::get_key();
    }

    /**
     * Get the data connected to the specified license code on man settings.
     *
     * - false if no license is present
     * - WP_Error if something went wrong if getting the license data
     * - object with expiration and addons list
     *
     * @param boolean $refresh
     * @return \WP_Error|boolean|object
     */
    function get_license_data($refresh = false) {
        return Newsletter\License::get_data($refresh);
    }

    /**
     * @deprecated
     * @param type $license_key
     * @return \WP_Error
     */
    public static function check_license($license_key) {
        return new WP_Error(-1, 'check_license() is deprecated');
    }
}

$newsletter = Newsletter::instance();

// Frontend modules
require_once NEWSLETTER_DIR . '/composer/composer.php';
require_once NEWSLETTER_DIR . '/users/users.php';
require_once NEWSLETTER_DIR . '/subscription/subscription.php';
require_once NEWSLETTER_DIR . '/emails/emails.php';
require_once NEWSLETTER_DIR . '/statistics/statistics.php';
require_once NEWSLETTER_DIR . '/unsubscription/unsubscription.php';
require_once NEWSLETTER_DIR . '/profile/profile.php';
require_once NEWSLETTER_DIR . '/widget/standard.php';
require_once NEWSLETTER_DIR . '/widget/minimal.php';

if (is_admin()) {
    require_once NEWSLETTER_DIR . '/admin.php';
}



