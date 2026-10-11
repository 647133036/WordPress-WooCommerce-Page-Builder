<?php

defined('ABSPATH') || exit;

class NewsletterFormManagerAddon extends NewsletterAddon {

    var $menu_title = null;
    var $menu_description = null;
    var $menu_slug = null;
    var $index_page = null;
    var $edit_page = null;
    var $welcome_page = null;
    var $confirmation_page = null;
    var $logs_page = null;
    var $dir = '';
    var $forms = null; // For caching

    function __construct($name, $version, $dir, $menu_slug = null) {
        parent::__construct($name, $version, $dir);
        $this->dir = $dir;
        $this->menu_slug = $menu_slug;
        if (empty($this->menu_slug)) {
            $this->menu_slug = $this->name;
        }
        $this->setup_options();
    }

    function init() {
        parent::init();

        if (is_admin() && $this->is_allowed()) {

            $this->index_page = 'newsletter_' . $this->menu_slug . '_index';
            $this->edit_page = 'newsletter_' . $this->menu_slug . '_edit';
            $this->welcome_page = 'newsletter_' . $this->menu_slug . '_welcome';
            $this->confirmation_page = 'newsletter_' . $this->menu_slug . '_confirmation';
            $this->logs_page = 'newsletter_' . $this->menu_slug . '_logs';

            add_filter('newsletter_lists_notes', [$this, 'hook_newsletter_lists_notes'], 10, 2);
        }
    }

    function hook_newsletter_lists_notes($notes, $list_id) {
        if (!$this->forms) {
            $this->forms = $this->get_forms();
        }
        foreach ($this->forms as $form) {
            $ok = false;
            $form_options = $this->get_form_options($form->id);
            // Too many years of development
            if (!empty($form_options['lists']) && is_array($form_options['lists']) && in_array($list_id, $form_options['lists'])) {
                $ok = true;
            } elseif (!empty($form_options['preferences_' . $list_id])) {
                $ok = true;
            } elseif (!empty($form_options['preferences']) && is_array($form_options['preferences']) && in_array($list_id, $form_options['preferences'])) {
                $ok = true;
            }
            if ($ok) {
                $notes[] = 'Linked to form "' . $form->title . '"';
            }
        }

        return $notes;
    }

    function hook_newsletter_autoresponder_sources($list, $id) {
        $forms = $this->get_forms();
        foreach ($forms as $form) {
            $settings = $this->get_form_options($form->id);

            if (empty($settings['autoresponders'])) {
                continue;
            }

            if (in_array('' . $id, $settings['autoresponders'])) {
                $s = new Newsletter\Source($form->title, $this->menu_title);
                $s->action = 'on';
                $list[] = $s;
            } elseif (in_array('-' . $id, $settings['autoresponders'])) {
                $s = new Newsletter\Source($form->title, $this->menu_title);
                $s->action = 'off';
                $list[] = $s;
            }
        }
        return $list;
    }

    /**
     * Basic subscription object to collect the data and option of a 3rd party form
     * integration.
     *
     * @param type $form_options
     * @return type
     */
    function get_default_subscription($form_options, $form_id = null) {
        $subscription = NewsletterSubscription::instance()->get_default_subscription();
        $subscription->floodcheck = false;

        if ($form_id) {
            $subscription->data->referrer = sanitize_key($this->name . '-' . $form_id);
        }

        // 'welcome_email' is a flag indicating what to use for that email (0- default, 1 - custom, 2 - don't send)
        if (!empty($form_options['welcome_email'])) {
            if ($form_options['welcome_email'] == '1') {
                $subscription->welcome_email_id = (int) $form_options['welcome_email_id'];
            } else {
                $subscription->welcome_email_id = -1;
            }
        }

        // Not for 3rd party form integration
        if (!empty($form_options['welcome_page_id'])) {
            $subscription->welcome_page_id = (int) $form_options['welcome_page_id'];
        }

        if (!empty($form_options['confirmation_email'])) {
            if ($form_options['confirmation_email'] == '1') {
                $subscription->confirmation_email_id = (int) $form_options['confirmation_email_id'];
            } else {
                $subscription->confirmation_email_id = -1;
            }
        }

        // Not for 3rd party form integration
        if (!empty($form_options['confirmation_page_id'])) {
            $subscription->confirmation_page_id = (int) $form_options['confirmation_page_id'];
        }

        if (!empty($form_options['status'])) {
            $subscription->optin = $form_options['status'];
        }

        $subscription->data->add_lists($form_options['lists'] ?? []);

        // The parser already removes the empty/non scalar values
        $subscription->autoresponders = wp_parse_list($form_options['autoresponders'] ?? []);

        return $subscription;
    }

    function newsletter_menu() {
        $this->add_subscription_menu_page($this->menu_title, '?page=' . $this->index_page);
    }

    function admin_menu() {
        add_submenu_page('newsletter_main_index', $this->menu_title, '<span class="tnp-side-menu">' . $this->menu_title . '</span>', 'exist', $this->index_page,
                function () {
                    require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                    $controls = new NewsletterControls();
                    require $this->dir . '/admin/index.php';
                }
        );
        add_submenu_page('admin.php', $this->menu_title, '<span class="tnp-side-menu">' . $this->menu_title . '</span>', 'exist', $this->edit_page,
                function () {
                    require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                    $controls = new NewsletterControls();

                    $form = $this->get_form(sanitize_key($_GET['id'] ?? ''));
                    if (!$form) {
                        echo 'Form not found';
                        return;
                    }
                    require $this->dir . '/admin/edit.php';
                }
        );

        if (file_exists($this->dir . '/admin/welcome.php')) {
            add_submenu_page('admin.php', $this->menu_title, '<span class="tnp-side-menu">' . $this->menu_title . '</span>', 'exist', $this->welcome_page,
                    function () {
                        /** @since 8.3.9 */
                        require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                        $controls = new NewsletterControls();

                        /** @since 8.3.9 */
                        $form = $this->get_form(sanitize_key($_GET['id'] ?? ''));
                        if (!$form) {
                            echo 'Form not found';
                            return;
                        }

                        require $this->dir . '/admin/welcome.php';
                    }
            );
        }

        /** @since 8.7.5 */
        if (file_exists($this->dir . '/admin/confirmation.php')) {
            add_submenu_page('admin.php', $this->menu_title, '<span class="tnp-side-menu">' . $this->menu_title . '</span>', 'exist', $this->confirmation_page,
                    function () {

                        require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                        $controls = new NewsletterControls();

                        $form = $this->get_form(sanitize_key($_GET['id'] ?? ''));
                        if (!$form) {
                            echo 'Form not found';
                            return;
                        }

                        require $this->dir . '/admin/confirmation.php';
                    }
            );
        }

        if (file_exists($this->dir . '/admin/logs.php')) {
            add_submenu_page('admin.php', $this->menu_title, $this->menu_title, 'exist', $this->logs_page,
                    function () {
                        require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
                        $controls = new NewsletterControls();

                        $form = $this->get_form(sanitize_key($_GET['id'] ?? ''));
                        if (!$form) {
                            echo 'Form not found';
                            return;
                        }
                        require $this->dir . '/admin/logs.php';
                    }
            );
        }
    }

    /**
     * Processes the subscription, logs errors and returns the subscriber.
     *
     * @param TNP_Subscription $subscription
     * @param mixed $form_id
     * @return TNP_User|WP_Error
     */
    function subscribe($subscription, $form_id) {
        $logger = $this->get_logger();
        if ($logger->is_debug) {
            $logger->debug($subscription);
        }

        $user = NewsletterSubscription::instance()->subscribe2($subscription);

        if (is_wp_error($user)) {
            Newsletter\Logs::add($this->name . '-' . $form_id, 'Subcription for ' . $subscription->data->email . ' failed: ' . $user->get_error_message());
        } else {
            Newsletter\Logs::add($this->name . '-' . $form_id, 'Subcription for ' . $subscription->data->email);
        }

        return $user;
    }

    /**
     * Adds a log visible on the "logs" page for the specific form.
     *
     * @param string $form_id
     * @param string $text
     */
    function log($form_id, $text) {
        Newsletter\Logs::add($this->name . '-' . $form_id, $text);
    }

    /**
     * Returns a lists of representations of forms available in the plugin subject of integration.
     * Usually the $fields is not set up on returned objects.
     * Must be implemented.
     *
     * @return TNP_FormManager_Form[] List of forms by 3rd party plugin
     */
    function get_forms() {
        return [];
    }

    /**
     * Build a form general representation of a real form from a form manager plugin extracting
     * only the data required to integrate. The form id is domain of the form manager plugin, so it can be
     * anything.
     * Must be implemented.
     *
     * @param mixed $form_id
     * @return TNP_FormManager_Form
     */
    function get_form($form_id) {
        return null;
    }

    /**
     * Saves the form mapping and integration settings.
     * @param mixed $form_id
     * @param array $data
     */
    public function save_form_options($form_id, $data) {
        $data['autoresponders'] = array_values(array_filter($data['autoresponders'] ?? []));
        update_option('newsletter_' . $this->name . '_' . $form_id, $data, false);
        return $data;
    }

    /**
     * Gets the form mapping and integration settings. Returns an empty array if the dataset is missing.
     * @param mixed $form_id
     * @return array
     */
    public function get_form_options($form_id) {
        return Newsletter::get_option_array('newsletter_' . $this->name . '_' . $form_id);
    }
}

class TNP_FormManager_Form {

    var $id = null;
    var $title = '';
    var $fields = [];
    var $connected = false;
}

