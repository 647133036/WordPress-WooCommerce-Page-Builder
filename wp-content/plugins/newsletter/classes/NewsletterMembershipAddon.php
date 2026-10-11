<?php

defined('ABSPATH') || exit;

/**
 * Support class to integrate membership plugins.
 */
abstract class NewsletterMembershipAddon extends NewsletterAddon {

    var $index_page = null;
    var $logs_page = null;
    var $import_time_limit = 0;

    function __construct($name, $version, $dir, $menu_slug = null) {
        parent::__construct($name, $version, $dir);
        $this->dir = $dir;
        $this->menu_slug = $menu_slug ?? $this->name;
        $this->setup_options();
    }

    function init() {
        parent::init();

        add_action('profile_update', [$this, 'hook_profile_update'], 10, 3);
        add_action('set_user_role', [$this, 'hook_set_user_role']);
        add_filter('newsletter_current_user', [$this, 'hook_newsletter_current_user']);
        add_action('newsletter_' . $this->name . '_import', [$this, 'import_run']);
        if (is_admin() && $this->is_allowed()) {
            $this->index_page = 'newsletter_' . $this->menu_slug . '_index';
            $this->logs_page = 'newsletter_' . $this->menu_slug . '_logs';
            add_filter('newsletter_lists_notes', [$this, 'hook_newsletter_lists_notes'], 10, 2);
        }
    }

    function deactivate() {
        parent::deactivate();
        $this->stop_import();
    }

    abstract function hook_newsletter_lists_notes($notes, $list_id);

    function hook_set_user_role($wp_user_id) {
        $this->_process_wp_user($wp_user_id);
    }

    function hook_profile_update($wp_user_id, $old_user_data = null, $userdata = null) {
        $this->_process_wp_user($wp_user_id);
    }

    function _process_wp_user($wp_user_id) {
        $logger = $this->get_logger();
        $logger->debug('User changed: ' . $wp_user_id);
        $wp_user = get_user_by('id', $wp_user_id);
        if (!$wp_user) {
            $logger->debug('User ' . $wp_user_id . ' not found');
            return;
        }
        $subscriber = $this->get_subscriber($wp_user);
        if ($subscriber) {
            $this->update_subscriber($subscriber, $wp_user);
        }
    }

    /**
     * Returns the subscriber connected to the provided WP user.
     *
     * @param WP_User $wp_user
     * @return object
     */
    function get_subscriber($wp_user, $autocreate = null) {
        global $wpdb;

        $logger = $this->get_logger();

        $newsletter = Newsletter::instance();

        $subscriber = $newsletter->get_user_by_wp_user_id($wp_user->ID);
        if ($subscriber) {
            // TODO: Fix email?
            return $subscriber;
        }

        $logger->debug('Linked subscriber not found, tying by email');
        $subscriber = $newsletter->get_user_by_email($wp_user->user_email);
        if ($subscriber) {
            $newsletter->set_user_wp_user_id($subscriber->id, $wp_user->ID);
            return $subscriber;
        }

        if (is_null($autocreate)) {
            $autocreate = !empty($this->options['autocreate']);
        }

        if ($autocreate) {
            $subscriber = [];
            $subscriber['email'] = $wp_user->user_email;
            $subscriber['wp_user_id'] = $wp_user->ID;
            $subscriber['status'] = TNP_User::STATUS_CONFIRMED;
            $subscriber['token'] = Newsletter::instance()->get_token();
            $subscriber['name'] = get_user_meta($wp_user->ID, 'first_name', true);
            $subscriber['surname'] = get_user_meta($wp_user->ID, 'last_name', true);
            $subscriber['referrer'] = $this->name;

            $subscriber = $newsletter->save_user($subscriber);
            return $subscriber;
        }

        return null;
    }

    abstract function update_subscriber($subscriber, $wp_user);

    function newsletter_menu() {
        $this->add_subscription_menu_page($this->menu_title, '?page=' . $this->index_page);
    }

    function admin_menu() {
        add_submenu_page('newsletter_main_index', $this->menu_title, '<span class="tnp-side-menu">' . $this->menu_title . '</span>', 'exist', $this->index_page,
                function () {
                    require $this->dir . '/admin/index.php';
                }
        );

        add_submenu_page('admin.php', $this->menu_title, $this->menu_title, 'exist', $this->logs_page,
                function () {
                    require $this->dir . '/admin/logs.php';
                }
        );
    }

    function hook_newsletter_current_user($user) {
        if (!is_user_logged_in()) {
            return $user;
        }

        $wp_user = wp_get_current_user();

        $subscriber = $this->get_subscriber($wp_user);

        if (!$subscriber) {
            return $user;
        }

        //$this->update_subscriber($subscriber, $wp_user);
        $subscriber->_trusted = true;
        $subscriber->editable = true;
        return $subscriber;
    }

    /**
     *
     * @param WP_User|int $wp_user WP_User object or ID of a WP user
     */
    function get_default_subscription($wp_user) {
        if (is_scalar($wp_user)) {
            $tmp = get_user_by('id', $wp_user);

            if (!$tmp) {
                $error = new WP_Error(101, 'WP user not found with ID ' . $wp_user);
                $this->log($error);
                return $error;
            }
            $wp_user = $tmp;
        }

        /** @var TNP_Subscription $subscription */
        if (!NewsletterModule::is_email($wp_user->user_email)) {
            $error = new WP_Error(101, 'The WP users has not a valid email');
            $this->log($error);
            return $error;
        }

        $subscription = NewsletterSubscription::instance()->get_default_subscription();
        $subscription->send_emails = false;
        $subscription->spamcheck = false;
        $subscription->floodcheck = false;
        $subscription->data->email = $wp_user->user_email;
        $subscription->data->name = get_user_meta($wp_user->ID, 'first_name', true);
        $subscription->data->surname = get_user_meta($wp_user->ID, 'last_name', true);
        $subscription->data->referrer = $this->name;
        $subscription->data->wp_user_id = $wp_user->ID;
        //$subscription->optin = 'single';
        if (!empty($this->options['optin'])) {
            $subscription->optin = $this->options['optin'];
        }
        return $subscription;
    }

    /**
     *
     * @param TNP_Subscription $subscription
     */
    function subscribe($subscription) {
        $user = NewsletterSubscription::instance()->subscribe2($subscription);
        if (is_wp_error($user)) {
            $logger = $this->get_logger();
            $logger->fatal('Unable to create the subscription ');
            $logger->fatal($user);
            $this->log('Failed subscription for user ' . $subscription->data->wp_user_id . ': ' . $user->get_error_message());
            return $user;
        }
        $this->log('User ' . $subscription->data->wp_user_id . ' subscribed');
        return $user;
    }

    function log($text) {
        Newsletter\Logs::add($this->name, $text);
    }

    function start_import() {
        if ($this->is_import_running()) {
            return;
        }
        wp_schedule_event(time(), 'newsletter', 'newsletter_' . $this->name . '_import');
        $this->update_import_last_id(0);

        $this->log('Import/alignment started');
    }

    function set_import_time_limit() {
        $this->import_time_limit = (int) (@ini_get('max_execution_time') * 0.90);
        if (!$this->import_time_limit) {
            $this->import_time_limit = 30;
        }

        $this->import_time_limit += time();
    }

    function end_import() {
        wp_unschedule_hook('newsletter_' . $this->name . '_import');
        $this->update_import_last_id(0);
        $this->log('Import/alignment ended');
    }

    function stop_import() {
        wp_unschedule_hook('newsletter_' . $this->name . '_import');
        $this->update_import_last_id(0);
        $this->log('Import/alignment stopped');
    }

    function is_import_running() {
        return wp_next_scheduled('newsletter_' . $this->name . '_import');
    }

    function get_import_progress() {
        global $wpdb;
        $data = [];
        $data['total'] = (int) $wpdb->get_var("select count(*) from {$wpdb->users}");
        $data['last_id'] = $this->get_import_last_id();
        $data['processed'] = (int) $wpdb->get_var($wpdb->prepare("select count(*) from {$wpdb->users} where id <= %d", $data['last_id']));
        $data['next_run'] = wp_next_scheduled('newsletter_' . $this->name . '_import');
        return $data;
    }

    function update_import_last_id($last_id) {
        update_option('newsletter_' . $this->name . '_import_last', $last_id, false);
    }

    function get_import_last_id() {
        return (int) get_option('newsletter_' . $this->name . '_import_last', 0);
    }

    abstract function import_run();

}
