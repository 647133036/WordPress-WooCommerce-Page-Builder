<?php

/**
 * Base class for addons.
 */
class NewsletterAddon {

    var $logger;
    var $admin_logger;
    var $name;
    var $options;
    var $version;
    var $labels;
    var $menu_priority = 100;
    var $weekly_check = true;
    var $menu_title = null;
    var $menu_slug = null;
    var $dir = '';

    public function __construct($name, $version = '0.0.0', $dir = '') {
        $this->name = $name;
        $this->version = $version;
        if (is_admin()) {
            $old_version = get_option('newsletter_' . $name . '_version');
            if ($version !== $old_version) {
                $this->upgrade($old_version === false);
                update_option('newsletter_' . $name . '_version', $version, false);
            }
        }
        add_action('newsletter_init', [$this, 'init']);

        if ($this->weekly_check && is_admin() && !wp_next_scheduled('newsletter_addon_' . $this->name)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', 'newsletter_addon_' . $this->name);
        }

        add_action('newsletter_addon_' . $this->name, [$this, 'weekly_check']);

        if ($dir) {
            $this->dir = $dir;
            register_deactivation_hook($dir . '/' . $this->name . '.php', [$this, 'deactivate']);
        }
    }

    /**
     * Method to be overridden and invoked on version change or on first install.
     *
     * @param bool $first_install
     */
    function upgrade($first_install = false) {

    }

    /**
     * Method to be overridden to initialize the add-on. It is invoked when Newsletter
     * fires the <code>newsletter_init</code> event.
     */
    function init() {
        //Load translations from specific addon /languages/ directory
        load_plugin_textdomain('newsletter-' . $this->name, false, 'newsletter-' . $this->name . '/languages/');
        if (is_admin()) {
            if ($this->is_allowed()) {
                add_action('admin_menu', [$this, 'admin_menu'], $this->menu_priority);
                // Should be registered only on our admin page, need to fix the $is_admin_page evaluation moment on
                // NewsletterAdmin class.
                add_action('newsletter_menu', [$this, 'newsletter_menu']);

                // TODO: remove when all addon has been updated
                if (method_exists($this, 'settings_menu')) {
                    add_filter('newsletter_menu_settings', [$this, 'settings_menu']);
                }

                if (method_exists($this, 'subscribers_menu')) {
                    add_filter('newsletter_menu_subscribers', [$this, 'subscribers_menu']);
                }
            }
            add_filter('newsletter_support_data', [$this, 'support_data'], 10, 1);
        }
    }

    /**
     * To be overridden by the single addon.
     */
    function weekly_check() {
        // To be implemented by the single addon
    }

    /**
     * To be overridden by the single addon.
     *
     * @return array
     */
    function get_support_data() {
        return [];
    }

    function support_data($data = []) {
        $d = $this->get_support_data();
        $d = array_merge($d, ['version' => $this->version]);
        $data[$this->name] = $d;
        return $data;
    }

    function deactivate() {
        $logger = $this->get_logger();
        $logger->info($this->name . ' deactivated');

        // The periodic check
        wp_unschedule_hook('newsletter_addon_' . $this->name);
        //delete_option('newsletter_' . $this->name . '_version');
    }

    function admin_menu() {

    }

    function newsletter_menu() {

    }

    function add_settings_menu_page($title, $url) {
        NewsletterAdmin::$menu['settings'][] = ['label' => $title, 'url' => $url];
    }

    function add_subscription_menu_page($title, $url) {
        NewsletterAdmin::$menu['subscription'][] = ['label' => $title, 'url' => $url];
    }

    function add_newsletters_menu_page($title, $url) {
        NewsletterAdmin::$menu['newsletters'][] = ['label' => $title, 'url' => $url];
    }

    function add_subscribers_menu_page($title, $url) {
        NewsletterAdmin::$menu['subscribers'][] = ['label' => $title, 'url' => $url];
    }

    function add_forms_menu_page($title, $url) {
        NewsletterAdmin::$menu['forms'][] = ['label' => $title, 'url' => $url];
    }

    function get_current_language() {
        return Newsletter::instance()->get_current_language();
    }

    function is_all_languages() {
        return empty(NewsletterAdmin::instance()->language());
    }

    function is_allowed() {
        return Newsletter::instance()->is_allowed();
    }

    function is_admin_page() {
        return NewsletterAdmin::instance()->is_admin_page();
    }

    function get_languages() {
        return Newsletter::instance()->get_languages();
    }

    function is_multilanguage() {
        return Newsletter::instance()->is_multilanguage();
    }

    /**
     * Wrapper for cleaner code.
     *
     * @param int $id
     * @return TNP_Email Type specified only for the IDE, it's a stdClass
     */
    function get_email($id) {
        return Newsletter::instance()->get_email($id);
    }

    /**
     * General logger for this add-on.
     *
     * @return NewsletterLogger
     */
    function get_logger() {
        if (!$this->logger) {
            $this->logger = new NewsletterLogger($this->name);
        }
//        $this->setup_options();
//        if (!empty($this->options['log_level'])) {
//            if ($this->options['log_level'] > $logger->level) {
//                $logger->level = $this->options['log_level'];
//            }
//        }
        return $this->logger;
    }

    /**
     * Specific logger for administrator actions.
     *
     * @return NewsletterLogger
     */
    function get_admin_logger() {
        if (!$this->admin_logger) {
            $this->admin_logger = new NewsletterLogger($this->name . '-admin');
        }
        return $this->admin_logger;
    }

    /**
     * Loads and prepares the options. It can be used to late initialize the options to save some resources on
     * add-ons which do not need to do something on each page load.
     */
    function setup_options() {
        if ($this->options) {
            return;
        }
        $this->options = $this->get_option_array('newsletter_' . $this->name);
    }

    /**
     * Returns a WP options granting it is an array.
     *
     * @param string $name
     * @return array
     */
    function get_option_array($name) {
        $opt = get_option($name, []);
        if (!is_array($opt)) {
            return [];
        }
        return $opt;
    }

    /**
     * Retrieve the stored options, merged with the specified language set.
     *
     * @param string $language
     * @return array
     */
    function get_options($language = '') {
        if ($language) {
            return array_merge($this->get_option_array('newsletter_' . $this->name), $this->get_option_array('newsletter_' . $this->name . '_' . $language));
        } else {
            return $this->get_option_array('newsletter_' . $this->name);
        }
    }

    /**
     * Saved the options under the correct keys and update the internal $options
     * property.
     * @param array $options
     */
    function save_options($options, $language = '') {
        if ($language) {
            update_option('newsletter_' . $this->name . '_' . $language, $options);
        } else {
            update_option('newsletter_' . $this->name, $options);
            $this->options = $options;
        }
    }

    function merge_defaults($defaults) {
        $options = $this->get_option_array('newsletter_' . $this->name);
        $options = array_merge($defaults, $options);
        $this->save_options($options);
    }

    /**
     *
     */
    function setup_labels() {
        if (!$this->labels) {
            $labels = [];
        }
    }

    function get_label($key) {
        if (!$this->options)
            $this->setup_options();

        if (!empty($this->options[$key])) {
            return $this->options[$key];
        }

        if (!$this->labels)
            $this->setup_labels();

        // We assume the required key is defined. If not there is an error elsewhere.
        return $this->labels[$key];
    }

    /**
     * Equivalent to $wpdb->query() but logs the event in case of error.
     *
     * @global wpdb $wpdb
     * @param string $query
     */
    function query($query) {
        global $wpdb;

        $r = $wpdb->query($query);
        if ($r === false) {
            $logger = $this->get_logger();
            $logger->fatal($query);
            $logger->fatal($wpdb->last_error);
        }
        return $r;
    }

    function get_results($query) {
        global $wpdb;
        $r = $wpdb->get_results($query);
        if ($r === false) {
            $this->logger->fatal($query);
            $this->logger->fatal($wpdb->last_error);
        }
        return $r;
    }

    function get_row($query) {
        global $wpdb;
        $r = $wpdb->get_row($query);
        if ($r === false) {
            $this->logger->fatal($query);
            $this->logger->fatal($wpdb->last_error);
        }
        return $r;
    }

    /**
     * Wrapper for cleaner code.
     *
     * @param int|string $id_or_email
     * @return TNP_User Type specified only for the IDE, it's a stdClass
     */
    function get_user($id_or_email) {
        return Newsletter::instance()->get_user($id_or_email);
    }

    function show_email_status_label($email) {
        return NewsletterAdmin::instance()->show_email_status_label($email);
    }

    function send_test_email($email, $controls) {
        NewsletterEmailsAdmin::instance()->send_test_email($email, $controls);
    }
}
