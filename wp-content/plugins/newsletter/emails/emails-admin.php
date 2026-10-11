<?php

defined('ABSPATH') || exit;

class NewsletterEmailsAdmin extends NewsletterModuleAdmin {

    static $instance;
    var $themes;

    /**
     * @return NewsletterEmailsAdmin
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        parent::__construct('emails');
        $this->themes = new NewsletterThemes('emails');
        // Thank you to plugins that add the WP editor on other admin plugin pages...
        if (isset($_GET['page']) && $_GET['page'] == 'newsletter_emails_edit') {
            global $wp_actions;
            $wp_actions['wp_enqueue_editor'] = 1;
        }

        add_action('wp_ajax_tnpc_test_raw_html', array($this, 'ajax_tnpc_test_raw_html'));
    }

    function admin_menu() {
        //$this->add_menu_page('index', 'Newsletters');
        $this->add_admin_page('list', 'Newsletter List');
        $this->add_admin_page('new', 'Newsletter New');
        $this->add_admin_page('edit', 'Newsletter Edit');
        $this->add_admin_page('logs', 'Newsletter Logs');
        $this->add_admin_page('versions', 'Newsletter Versions');
        $this->add_admin_page('theme', 'Newsletter Themes');
        $this->add_admin_page('composer', 'The Composer');
        $this->add_admin_page('editorhtml', 'HTML Editor');
        $this->add_admin_page('editortinymce', 'TinyMCE Editor');
        $this->add_admin_page('presets', 'Presets');
        $this->add_admin_page('presets-edit', 'Presets');

        $this->add_admin_page('settings', 'Settings');

        $this->add_admin_page('automated', 'Automated');
        $this->add_admin_page('autoresponder', 'Autoresponder');
    }

    /** Returns the correct admin page to edit the newsletter with the correct editor. */
    function get_editor_url($email_id, $editor_type) {
        switch ($editor_type) {
            case NewsletterEmails::EDITOR_COMPOSER:
                return '?page=newsletter_emails_composer&id=' . urlencode($email_id);
            case NewsletterEmails::EDITOR_HTML:
                return '?page=newsletter_emails_editorhtml&id=' . urlencode($email_id);
            case NewsletterEmails::EDITOR_TINYMCE:
                return '?page=newsletter_emails_editortinymce&id=' . urlencode($email_id);
        }
    }

    function get_edit_button($email, $only_icon = true) {

        $editor_type = $this->get_editor_type($email);
        if ($email->status === TNP_Email::STATUS_DRAFT) {
            $edit_url = $this->get_editor_url($email->id, $editor_type);
        } else {
            $edit_url = '?page=newsletter_emails_edit&id=' . urlencode($email->id);
        }

        $icon_class = 'edit';
        if ($only_icon) {
            return '<a class="button-primary tnpc-button" href="' . $edit_url . '" title="' . esc_attr__('Edit', 'newsletter') . '">' .
                    '<i class="fas fa-' . $icon_class . '"></i></a>';
        } else {
            return '<a class="button-primary tnpc-button" href="' . $edit_url . '" title="' . esc_attr__('Edit', 'newsletter') . '">' .
                    '<i class="fas fa-' . $icon_class . '"></i> ' . __('Edit', 'newsletter') . '</a>';
        }
    }

    /** Returns the correct editor type for the provided newsletter. Contains backward compatibility code. */
    function get_editor_type($email) {
        $email = (object) $email;
        $editor_type = $email->editor;

        // Backward compatibility
        $email_options = maybe_unserialize($email->options);
        if (isset($email_options['composer'])) {
            $editor_type = NewsletterEmails::EDITOR_COMPOSER;
        }
        // End backward compatibility

        return $editor_type;
    }

    private function set_test_subject_to($email) {
        if ($email->subject == '') {
            $email->subject = 'Dummy subject, it was empty (remember to set it)';
        }
        if (!defined('NEWSLETTER_TEST_SUBJECT_POSTFIX')) {
            define('NEWSLETTER_TEST_SUBJECT_POSTFIX', ' [PREVIEW]');
        }
        $email->subject = $email->subject . NEWSLETTER_TEST_SUBJECT_POSTFIX;
    }

    function ajax_tnpc_test_raw_html() {
        check_admin_referer('save');
        if (!$this->is_allowed()) {
            wp_send_json_error('Not allowed', 403);
        }

            require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';

        $controls = new NewsletterControls();

        $email = $this->get_email($controls->data['id']);
        $email->id = 0; // To unlink from the database object

        if ($this->is_html_allowed()) {
            $email->message = $controls->data['message'];
        } else {
            $email->message = wp_kses_post($controls->data['message']);
        }

        $email->subject = wp_strip_all_tags($controls->data['subject']);

        $email->track = $controls->data['track'] ?? (int) Newsletter::instance()->get_option('track');
        if (!empty($controls->data['sender_email'])) {
            $email->options['sender_email'] = $controls->data['sender_email'];
        }
        if (!empty($controls->data['sender_name'])) {
            $email->options['sender_name'] = $controls->data['sender_name'];
        }

        if (isset($_POST['to_email'])) {
            $message = NewsletterEmailsAdmin::instance()->send_test_newsletter_to_email_address($email, $controls->data['test_email']);
            echo $message;
        } else {
            NewsletterEmailsAdmin::instance()->send_test_email($email, $controls);
            if ($controls->messages) {
                echo $controls->messages;
            } else {
                echo $controls->errors;
            }
        }

        die();
    }

    /**
     * Send an email to the test subscribers.
     *
     * @param TNP_Email $email Could be any object with the TNP_Email attributes
     * @param NewsletterControls $controls
     */
    function send_test_email($email, $controls) {
        if (!$email) {
            $controls->errors = __('Newsletter should be saved before sending a test', 'newsletter');
            return;
        }

        $original_subject = $email->subject;
        $this->set_test_subject_to($email);

        $users = NewsletterUsersAdmin::instance()->get_test_users();
        if (count($users) == 0) {
            $controls->errors = '' . __('There are no test subscribers to send to', 'newsletter') .
                    '. <a href="https://www.thenewsletterplugin.com/plugins/newsletter/subscribers-module#test" target="_blank"><strong>' .
                    __('Read more', 'newsletter') . '</strong></a>.';
        } else {
            $r = Newsletter::instance()->send($email, $users, true);
            $emails = [];
            foreach ($users as $user) {
                $emails[] = '<a href="admin.php?page=newsletter_users_edit&id=' . $user->id . '" target="_blank">' . $user->email . '</a>';
            }
            if (is_wp_error($r)) {
                $controls->errors = 'Something went wrong. Check the error logs on status page.<br>';
                $controls->errors .= __('Test subscribers:', 'newsletter');
                $controls->errors .= ' ' . implode(', ', $emails);
                $controls->errors .= '<br>';
                $controls->errors .= '<strong>' . esc_html($r->get_error_message()) . '</strong><br>';
                $controls->errors .= '<a href="https://www.thenewsletterplugin.com/documentation/email-sending-issues" target="_blank"><strong>' . __('Read more about delivery issues', 'newsletter') . '</strong></a>.';
            } else {
                $controls->messages = __('Test sent to:', 'newsletter');
                //$controls->messages .= __('Test subscribers:', 'newsletter');

                $controls->messages .= ' ' . implode(', ', $emails);
                $controls->messages .= '.<br>';
                $controls->messages .= 'If the message does not shows up on the mailbox, check the spam folder and run a test from the '
                        . '<a href="?page=newsletter_system_delivery" target="_blank"><strong>System/Delivery panel</strong></a>.<br>';

                $controls->messages .= '<a href="https://www.thenewsletterplugin.com/documentation/subscribers#test" target="_blank"><strong>' .
                        __('Read more about test subscribers', 'newsletter') . '</strong></a>.<br>';
                $controls->messages .= '<a href="https://www.thenewsletterplugin.com/documentation/email-sending-issues" target="_blank"><strong>' . __('Read more about delivery issues', 'newsletter') . '</strong></a>.';
            }
        }
        $email->subject = $original_subject;
    }

    /**
     * Send an email to the test subscribers.
     *
     * @param TNP_Email $email Could be any object with the TNP_Email attributes
     * @param string $email_address
     *
     * @throws Exception
     */
    function send_test_newsletter_to_email_address($email, $email_address) {

        if (!$email) {
            throw new Exception(__('Newsletter should be saved before send a test', 'newsletter'));
        }

        $this->set_test_subject_to($email);

        $dummy_subscriber = $this->get_user($email_address);

        if (!$dummy_subscriber) {
            $dummy_subscriber = $this->get_dummy_user();
            $dummy_subscriber->email = $email_address;
        }

        $result = Newsletter::instance()->send($email, [$dummy_subscriber], true);

        if (is_wp_error($result)) {
            $error_message = 'Something went wrong. Check the error logs on the System/Logs page.<br>';
            $error_message .= '<br>';
            $error_message .= '<strong>' . esc_html($result->get_error_message()) . '</strong><br>';
            $error_message .= '<a href="https://www.thenewsletterplugin.com/documentation/email-sending-issues" target="_blank"><' . __('Read more about delivery issues', 'newsletter') . '</a>.';
            throw new Exception($error_message);
        }

        $messages = __('Test sent to:', 'newsletter');

        $messages .= ' ' . esc_html($email_address);
        $messages .= '.<br>';
        $messages .= 'If the message does not shows up on the mailbox, check the spam folder and run a test from the '
                . '<a href="?page=newsletter_system_delivery" target="_blank"><strong>System/Delivery panel</strong></a>.<br>';
        $messages .= '<a href="https://www.thenewsletterplugin.com/documentation/email-sending-issues" target="_blank">' . __('Read more about delivery issues', 'newsletter') . '</a>.';

        return $messages;
    }

    function log($email_id, $description) {
        global $current_user;

        if (defined('DOING_CRON') && DOING_CRON) {
            $user = '[cron]';
        } elseif ($current_user) {
            $user = $current_user->user_login;
        } else {
            $user = '[no user]';
        }
        Newsletter\Logs::add('newsletter-' . $email_id, '[' . $user . '] ' . $description);
    }
}
