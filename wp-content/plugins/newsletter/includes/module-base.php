<?php

class NewsletterModuleBase {

    static $language = '';
    static $switched_languages = [];
    static $switched_locales = [];
    static $locale = '';
    static $previous_language = '';
    static $previous_locale = '';
    static $is_multilanguage = false;
    static $is_allowed = false;
    static $plugin_url;

    /**
     * @var string The module name
     */
    var $module;

    /**
     * @var NewsletterLogger
     */
    var $logger;
    var $store;

    function __construct($module, $logger = null) {

        $this->module = $module;
        if ($logger) {
            $this->logger = $logger;
        } else {
            $this->logger = new NewsletterLogger($module);
        }

        $this->store = NewsletterStore::instance();
    }

    function is_allowed() {
        return self::$is_allowed;
    }

    function is_html_allowed() {
        if (defined('NEWSLETTER_HTML_ALLOWED') && NEWSLETTER_HTML_ALLOWED) {
            return true;
        }
        return current_user_can('unfiltered_html');
    }

    function is_multilanguage() {
        return self::$is_multilanguage;
    }

    function is_ai_enabled() {
        return true;
    }

    static function plugin_url() {
        return self::$plugin_url;
    }

    /**
     * Returns an array of languages with key the language code and value the language name.
     * An empty array is returned if no language is available.
     */
    static function get_languages() {

        if (defined('NEWSLETTER_MULTILANGUAGE') && !NEWSLETTER_MULTILANGUAGE) {
            return [];
        }

        $language_options = [];

        // WPML
        if (class_exists('SitePress')) {
            $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
            foreach ($languages as $language) {
                $language_options[$language['language_code']] = $language['translated_name'];
            }

            return $language_options;
        }

        // Polylang
        if (function_exists('pll_languages_list')) {
            $languages = pll_languages_list(['fields' => '']);
            foreach ($languages as $data) {
                $language_options[$data->slug] = $data->name;
            }

            return $language_options;
        }

        // Addons
        return apply_filters('newsletter_languages', $language_options);
    }

    /**
     * Returns the current language code set by multiplanguage plugins or empty in single language installations.
     * Note: the language code is not the WordPress "locale" code.
     *
     * @return string
     */
    function language() {
        return self::$language;
    }

    /* For compatibility */

    function get_current_language() {
        return self::$language;
    }

    /**
     * Gets the WordPress locale code for the specified language code of a multilanguage plugin (Polylang, WPML, ...).
     * Not all multilanguage plugins can be queried for the locale.
     *
     * @param string $language
     * @return string A WP compatibile locale
     */
    static function get_locale($language) {
        if (function_exists('pll_languages_list')) { // Polylang
            $languages = pll_languages_list(['fields' => '']);
            if ($languages && is_array($languages)) {
                foreach ($languages as $data) {
                    if ($data->slug === self::$language) {
                        return $data->locale;
                    }
                }
            }
        } else if (class_exists('SitePress')) { // WPML
            $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);

            // A user reported this filter returning a null value (!?)
            if ($languages && is_array($languages)) {
                foreach ($languages as $code => $data) {
                    if ($code === self::$language) {
                        return $data['default_locale'];
                    }
                }
            }
        }
        return '';
    }

    /**
     * Switch the internal language and locale variables, then used to get language
     * specific URLs, translations, ...
     *
     * @param mixed $language It can be an object ocntaining the "language" attribute
     */
    function switch_language($language) {
        $language = $language->language ?? $language ?? ''; // When receiving a subscriber...
        if (!self::$is_multilanguage || !$language) {
            return;
        }

        array_push(self::$switched_languages, self::$language);
        array_push(self::$switched_locales, self::$locale);
        self::$language = $language;
        self::$locale = $this->get_locale($language);

        // Works with Polylang as well
        // It should swith the WP locale
        do_action('wpml_switch_language', $language);
    }

    /**
     * Restore ther internal previous language and locale after a switch.
     */
    function restore_language() {
        if (!self::$switched_languages) {
            return;
        }
        self::$language = array_pop(self::$switched_languages);
        self::$locale = array_pop(self::$switched_locales);

        $has_stack = defined('ICL_SITEPRESS_VERSION') && version_compare(ICL_SITEPRESS_VERSION, '5.0', '>=');
        do_action('wpml_switch_language', $has_stack ? null : self::$language );
    }

    /** Returns a prefix to be used for option names and other things which need to be uniquely named. The parameter
     * "sub" should be used when a sub name is needed for another set of options or like.
     *
     * @param string $sub
     * @return string The prefix for names
     */
    function get_prefix($sub = '', $language = '') {
        if (empty($sub)) {
            $sub = $this->module;
        }
        return 'newsletter_' . $sub . (!empty($language) ? '_' : '') . $language;
    }

    /**
     * Return the default label for the given key and submodule. The WP language is switched to
     * match the current session language to force WP to use the correct translation file.
     *
     * @param string $name
     * @param string $sub
     * @return string
     *
     * @todo Implement a "setup" to cache the values at class level and return only the requested entry
     */
    function get_default_text($key, $sub = '') {
        if (!$sub) {
            $sub = $this->module;
        }
        return NewsletterDefaults::get_text($key, $sub);
    }

    /**
     * Returns a text identified by the $key using the current language. If it cannot be
     * found, the default values is retutned.
     *
     * @param string $key
     * @param string $sub
     * @return string
     */
    function get_text($key, $sub = '') {
        $text = $this->get_option($key, $sub);
        return !empty($text) ? $text : $this->get_default_text($key, $sub);
    }

    /**
     * Frontend and admin modules provide different implementations.
     *
     * @param string $key
     * @param string $sub
     * @param string $language
     * @return mixed
     */
    function get_option($key, $sub = '', $language = null) {
        return null;
    }

    /**
     * To be implemented by derived classes.
     */
    function get_main_options($sub = '') {
        return [];
    }

    function get_default_options($sub = '') {
        if (!$sub) {
            $sub = $this->module;
        }
        return NewsletterDefaults::get_options($sub);
    }

    /**
     * Runs a query and returns "false" on error (logged).
     *
     * @global wpdb $wpdb
     * @param string $query
     */
    function query($query) {
        global $wpdb;

        $this->logger->debug($query);
        $wpdb->last_error = '';
        $r = $wpdb->query($query);
        if ($r === false || $wpdb->last_error) {
            $this->logger->fatal($query);
            $this->logger->fatal($wpdb->last_error);
            return false;
        }
        return $r;
    }

    function get_results($query, $format = OBJECT) {
        global $wpdb;
        $wpdb->last_error = '';
        $r = $wpdb->get_results($query, $format);
        // Error check for the fancy way the ressukts are returned
        if ($r === false || $r === null || $wpdb->last_error) {
            $this->logger->fatal($query);
            $this->logger->fatal($wpdb->last_error);
            return false;
        }
        return $r;
    }

    /**
     * Return a single db row or null if not found or false for errors.
     * @global wpdb $wpdb
     * @param type $query
     * @return bool
     */
    function get_row($query, $format = OBJECT) {
        global $wpdb;
        $wpdb->last_error = '';
        $r = $wpdb->get_row($query, $format);
        if ($r === false || $wpdb->last_error) {
            $this->logger->fatal($query);
            $this->logger->fatal($wpdb->last_error);
            return false;
        }
        return $r;
    }

    /**
     *
     * @global wpdb $wpdb
     * @param string $table
     * @param array $data
     */
    function insert($table, $data) {
        global $wpdb;
        $this->logger->debug("inserting into table $table");
        $r = $wpdb->insert($table, $data);
        if ($r === false) {
            $this->logger->fatal($wpdb->last_error);
        }
    }

    /**
     * Returns a list of users marked as "test user".
     * @return TNP_User[]
     */
    function get_test_users() {
        return $this->store->get_all(NEWSLETTER_USERS_TABLE, "where test=1 and status in ('C', 'S')");
    }

    /** Returns the user identify by an id or an email. If $id_or_email is an object or an array, it is assumed it contains
     * the "id" attribute or key and that is used to load the user.
     *
     * @global type $wpdb
     * @param string|int|object|array $id_or_email
     * @param string $format
     * @return TNP_User|null
     */
    function get_user($id_or_email, $format = OBJECT) {
        global $wpdb;

        if (empty($id_or_email)) {
            return null;
        }

// To simplify the reaload of a user passing the user it self.
        if (is_object($id_or_email)) {
            $id_or_email = $id_or_email->id;
        } else if (is_array($id_or_email)) {
            $id_or_email = $id_or_email['id'];
        }

        $id_or_email = strtolower(trim($id_or_email));

        if (is_numeric($id_or_email)) {
            $r = $this->get_row($wpdb->prepare("select * from " . NEWSLETTER_USERS_TABLE . " where id=%d limit 1", $id_or_email), $format);
        } else {
            $r = $this->get_row($wpdb->prepare("select * from " . NEWSLETTER_USERS_TABLE . " where status <> 'T' and email=%s limit 1", $id_or_email), $format);
        }

        return $r;
    }

    function get_dummy_user($token = '0') {
        $token = sanitize_key($token);
        $dummy_user = new TNP_User();
        $dummy_user->id = 0;
        $dummy_user->token = $token;
        $dummy_user->email = 'john.doe@example.org';
        $dummy_user->name = 'John';
        $dummy_user->surname = 'Doe';
        $dummy_user->sex = 'n';
        $dummy_user->track = 1;
        $dummy_user->language = get_option('newsletter_dummy_user_language', '');
        $dummy_user->status = TNP_User::STATUS_CONFIRMED;

        for ($i = 1; $i <= NEWSLETTER_PROFILE_MAX; $i++) {
            $profile_key = "profile_$i";
            $dummy_user->$profile_key = 'Dummy profile ' . $i;
        }

        return $dummy_user;
    }

    /**
     * Returns ther meta value as int, null if not present.
     *
     * @param int $user_id
     * @param string $key
     * @return null|int
     */
    function get_user_meta_int($user_id, $key) {
        $value = $this->get_user_meta($user_id, $key);
        if (is_null($value)) {
            return null;
        }
        return (int) $value;
    }

    function get_user_meta($user_id, $key) {
        global $wpdb;
        $r = $this->get_row($wpdb->prepare("select * from " . NEWSLETTER_USERS_META_TABLE . " where user_id=%d and meta_key=%s limit 1", $user_id, $key));
        return $r ? $r->value : null;
    }

    /**
     *
     * @global wpdb $wpdb
     * @param string $email
     * @return mixed
     */
    function get_user_by_email($email) {
        global $wpdb;
        return $this->get_row($wpdb->prepare("select * from " . NEWSLETTER_USERS_TABLE . " where status <> 'T' and email=%s limit 1", $email));
    }

    /**
     * Loads a user identify by a key, checking if the key is expired given the max age and the matching action.
     */
    function get_user_by_key($key, $action = '') {
        if (empty($key)) {
            return null;
        }

        if (current_user_can('administrator')) {
            if (str_starts_with($key, '0-')) {
                return $this->get_dummy_user();
            }
        }

        // Old key
        if (strpos($key, '.') === false) {
            return null;
        }

        list ($id, $token) = explode('-', $key, 2);
        $user = $this->get_user($id);
        if (!$user) {
            return null;
        }


        list($timestamp, $signature) = explode('.', $token, 2);
        if ($timestamp < time()) {
            return null;
        }

        $computed = md5($user->id . $user->token . $timestamp . $action);
        if (!hash_equals($computed, $signature)) {
            return null;
        }

        return $user;
    }

    /**
     * @todo Possibly add a constant to control the grace period
     * @param string $key
     * @return null|TNP_User
     */
    function get_user_by_old_key($key) {
        if (empty($key)) {
            return null;
        }
        $time = (int) get_option('newsletter_new_token_time');
        if ($time < time() - MONTH_IN_SECONDS) {
            //$this->error_log("Old keys grace period expired");
            return null;
        }

        list ($id, $token) = explode('-', $key, 2);
        $user = $this->get_user($id);
        if (!$user) {
            return null;
        }

        if (hash_equals($user->token, $token)) {
            return $user;
        }

        $user_token = $this->get_user_meta($user->id, 'old_token');
        if ($user_token) {
            if (hash_equals($user_token, $token)) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Returns a generic subscriber key that expires in 7 days.
     *
     * @param TNP_User $user
     * @return string
     */
    function get_user_key($user, $action = '', $lifespan = 7 * DAY_IN_SECONDS) {
        // Dummy user
        if ((int) $user->id === 0) {
            return $user->id . '-' . $user->token; // The token is 0 or the language to use while testing
        }

        if (strlen($user->token) < 16) {
            $this->refresh_user_token($user);
        }

        $timestamp = time() + (int) $lifespan;

        return $user->id . '-' . $timestamp . '.' . md5($user->id . $user->token . $timestamp . $action);
    }

    function get_user_key_field($user, $action) {
        return '<input type="hidden" name="nk" value="' . esc_attr($this->get_user_key($user, $action, HOUR_IN_SECONDS)) . '">';
    }

    /**
     * NEVER CHANGE THIS METHOD SIGNATURE, USER BY THIRD PARTY PLUGINS.
     *
     * Saves a new user on the database. Return false if the email (that must be unique) is already
     * there. For a new users set the token and creation time if not passed.
     *
     * @param array $user
     * @return TNP_User|array|boolean Returns the subscriber reloaded from DB in the specified format. Flase on failure (duplicate email).
     */
    function save_user($user, $return_format = OBJECT) {
        if (is_object($user)) {
            $user = (array) $user;
        }
        if (empty($user['id'])) {
            $existing = $this->get_user($user['email']);
            if ($existing != null) {
                $user['status'] = 'T';
                //return false;
            }
            if (empty($user['token'])) {
                $user['token'] = $this->get_token();
            }
        }

        // We still don't know when it happens but under some conditions, matbe external, lists are passed as NULL
        foreach ($user as $key => $value) {
            if (strpos($key, 'list_') !== 0) {
                continue;
            }
            if (is_null($value)) {
                unset($user[$key]);
            } else {
                $user[$key] = (int) $value;
            }
        }

        // Due to the unique index on email field, this can fail.
        return $this->store->save(NEWSLETTER_USERS_TABLE, $user, $return_format);
    }

    function save_user_meta($user_id, $key, $value) {
        global $wpdb;

        $meta = $this->get_user_meta($user_id, $key);
        if ($meta) {
            $wpdb->query($wpdb->prepare("update " . NEWSLETTER_USERS_META_TABLE . " set value=%s where user_id=%d and meta_key=%s limit 1",
                            $value, $user_id, $key));
        } else {

            $this->query($wpdb->prepare("insert into " . NEWSLETTER_USERS_META_TABLE . " (user_id, meta_key, value) values (%d, %s, %s)",
                            $user_id, $key, $value));
        }
    }

    function save_user_subscription($user_id, $subscription) {
        $this->save_user_meta($user_id, 'subscription', serialize($subscription));
    }

    /**
     * @return TNP_Subscription
     */
    function get_user_subscription($user_id) {
        $value = $this->get_user_meta($user_id, 'subscription');
        if ($value) {
            return unserialize($value);
        }

        return null;
    }

    function delete_user_subscription($user_id) {
        $this->delete_user_meta($user_id, 'subscription');
    }

    function delete_user_meta($user_id, $key) {
        global $wpdb;

        $wpdb->query($wpdb->prepare("delete from " . NEWSLETTER_USERS_META_TABLE . " where user_id=%d and meta_key=%s", $user_id, $key));
    }

    /**
     * Changes a user status. Accept a user object, user id or user email.
     *
     * @param TNP_User $user
     * @param string $status
     * @return TNP_User
     */
    function set_user_status($user, $status) {
        global $wpdb;

        $this->logger->debug('Status change to ' . $status . ' of subscriber ' . $user->id . ' from ' . $_SERVER['REQUEST_URI']);

        $this->query($wpdb->prepare("update " . NEWSLETTER_USERS_TABLE . " set status=%s, updated=%d where id=%d limit 1", $status, time(), $user->id));
        $user->status = $status;
        return $this->get_user($user);
    }

    /**
     *
     * @global wpdb $wpdb
     * @param TNP_User $user
     */
    function refresh_user_token($user) {
        global $wpdb;

        // Dummy user
        if ($user->id == 0) {
            return;
        }

        $token = $this->get_token(16);

        $this->save_user_meta($user->id, 'old_token', $user->token);

        $this->query($wpdb->prepare("update " . NEWSLETTER_USERS_TABLE . " set token=%s where id=%d limit 1", $token, $user->id));
        $user->token = $token;
    }

    function set_email_status($email, $status) {
        global $wpdb;
        $this->query($wpdb->prepare("update " . NEWSLETTER_EMAILS_TABLE . " set status=%s where id=%d limit 1", $status, $email->id));
    }

    /**
     * @param string $language The language for the list labels (it does not affect the lists returned)
     * @return TNP_List[]
     */
    function get_lists() {
        static $lists = null;

        if (is_null($lists)) {
            $options = $this->get_main_options('lists');
            $lists = TNP_List::build($options);
        }

        return $lists;
    }

    /**
     * Returns an array of TNP_List objects of lists that are public.
     * @return TNP_List[]
     */
    function get_lists_public() {
        static $lists = null;

        if (is_null($lists)) {
            $lists = [];
            foreach ($this->get_lists() as $list) {
                if ($list->is_public()) {
                    $lists['' . $list->id] = $list;
                }
            }
        }
        return $lists;
    }

    /**
     * Create a log entry with the meaningful user data.
     *
     * @global wpdb $wpdb
     * @param TNP_User $user
     * @param string $source
     */
    function add_user_log($user, $source = '') {
        global $wpdb;

        $lists = $this->get_lists_public();
        foreach ($lists as $list) {
            $field_name = 'list_' . $list->id;
            $data[$field_name] = $user->$field_name;
        }
        $data['status'] = $user->status;
        $data['track'] = $user->track ?? '-';
        $ip = $this->get_remote_ip();
        $ip = $this->process_ip($ip);
        $this->store->save($wpdb->prefix . 'newsletter_user_logs', array('ip' => $ip, 'user_id' => $user->id, 'source' => $source, 'created' => time(), 'data' => json_encode($data)));
    }

    /**
     *
     * @global wpdb $wpdb
     * @param TNP_User $user
     * @param int $list
     * @param type $value
     */
    function set_user_list($user, $list, $value) {
        global $wpdb;

        $list = (int) $list;
        $value = $value ? 1 : 0;
        $r = $wpdb->update(NEWSLETTER_USERS_TABLE, array('list_' . $list => $value), array('id' => $user->id));
    }

    function set_user_field($id, $field, $value) {
        $this->store->set_field(NEWSLETTER_USERS_TABLE, $id, $field, $value);
    }

    function set_user_wp_user_id($user_id, $wp_user_id) {
        $this->store->set_field(NEWSLETTER_USERS_TABLE, $user_id, 'wp_user_id', $wp_user_id);
    }

    /**
     *
     * @param int $wp_user_id
     * @param string $format
     * @return TNP_User
     */
    function get_user_by_wp_user_id($wp_user_id, $format = OBJECT) {
        return $this->store->get_single_by_field(NEWSLETTER_USERS_TABLE, 'wp_user_id', $wp_user_id, $format);
    }

    /**
     * Deletes a subscriber and cleans up all the stats table with his correlated data.
     *
     * @global wpdb $wpdb
     * @param int|id[] $id
     */
    function delete_user($id) {
        global $wpdb;
        $id = (array) $id;
        foreach ($id as $user_id) {
            $user = $this->get_user($user_id);
            if ($user) {
                $r = $this->store->delete(NEWSLETTER_USERS_TABLE, $user_id);
                $wpdb->delete(NEWSLETTER_STATS_TABLE, array('user_id' => $user_id));
                $wpdb->delete(NEWSLETTER_SENT_TABLE, array('user_id' => $user_id));
                $wpdb->delete($wpdb->prefix . 'newsletter_user_meta', array('user_id' => $user_id));
                do_action('newsletter_user_deleted', $user);
            }
        }

        return count($id);
    }

    function anonymize_user($id) {
        global $wpdb;
        $user = $this->get_user($id);
        if (!$user) {
            return null;
        }

        $user->name = '';
        $user->surname = '';
        $user->ip = $this->anonymize_ip($user->ip);

        for ($i = 1; $i <= NEWSLETTER_PROFILE_MAX; $i++) {
            $field = 'profile_' . $i;
            $user->$field = '';
        }

// [TODO] Status?
        $user->status = TNP_User::STATUS_UNSUBSCRIBED;
        $user->email = $user->id . '@anonymi.zed';

        $user = $this->save_user($user);

        return $user;
    }

    /**
     * Retrieves an email from DB and unserialize the options.
     *
     * @param mixed $id
     * @param string $format
     * @return TNP_Email An object with the same fields of TNP_Email, but not actually of that type
     */
    function get_email($id, $format = OBJECT) {
        $email = $this->store->get_single(NEWSLETTER_EMAILS_TABLE, $id, $format);
        if (!$email) {
            return null;
        }
        if ($format == OBJECT) {
            $email->options = maybe_unserialize($email->options);
            if (!is_array($email->options)) {
                $email->options = [];
            }
            if (empty($email->query)) {
                $email->query = "select * from " . NEWSLETTER_USERS_TABLE . " where status='C'";
            }
        } else if ($format == ARRAY_A) {
            $email['options'] = maybe_unserialize($email['options']);
            if (!is_array($email['options'])) {
                $email['options'] = [];
            }
            if (empty($email['query'])) {
                $email['query'] = "select * from " . NEWSLETTER_USERS_TABLE . " where status='C'";
            }
        }
        return $email;
    }

    /** Returns all the emails of the give type (message, feed, followup, ...) and in the given format
     * (default as objects). Return false on error or at least an empty array. Errors should never
     * occur.
     *
     * @global wpdb $wpdb
     * @param string $type
     * @return boolean|array
     */
    function get_emails($type = null, $format = OBJECT) {
        global $wpdb;
        if ($type == null) {
            $list = $wpdb->get_results("select * from " . NEWSLETTER_EMAILS_TABLE . " order by id desc", $format);
        } else {
            $type = (string) $type;
            $list = $wpdb->get_results($wpdb->prepare("select * from " . NEWSLETTER_EMAILS_TABLE . " where type=%s order by id desc", $type), $format);
        }
        if ($wpdb->last_error) {
            $this->logger->error($wpdb->last_error);
            return false;
        }
        if (empty($list)) {
            return [];
        }
        return $list;
    }

    function get_emails_by_status($status) {
        global $wpdb;
        $list = $wpdb->get_results($wpdb->prepare("select * from " . NEWSLETTER_EMAILS_TABLE . " where status=%s order by id desc", $status));

        array_walk($list, function ($email) {
            $email->options = maybe_unserialize($email->options);
            if (!is_array($email->options)) {
                $email->options = [];
            }
        });

        return $list;
    }

    function get_emails_blocked_count() {
        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare("select count(*) from " . NEWSLETTER_EMAILS_TABLE . " where status=%s order by id desc", TNP_Email::STATUS_ERROR));
        return $count;
    }

    /**
     * Save an email and provide serialization, if needed, of $email['options'].
     * @return TNP_Email|bool|WP_Error
     */
    function save_email($email, $return_format = OBJECT) {
        if (is_object($email)) {
            $email = (array) $email;
        }

        if (isset($email['subject'])) {
            if (mb_strlen($email['subject'], 'UTF-8') > 250) {
                $email['subject'] = mb_substr($email['subject'], 0, 250, 'UTF-8');
            }
        }
        if (isset($email['options']) && is_array($email['options'])) {
            $email['options'] = serialize($email['options']);
        }

        $email = $this->store->save(NEWSLETTER_EMAILS_TABLE, $email, $return_format, true);

        if (is_wp_error($email)) {
            return $email;
        }

        if ($return_format == OBJECT) {
            $email->options = maybe_unserialize($email->options);
            if (!is_array($email->options)) {
                $email->options = [];
            }
        } else if ($return_format == ARRAY_A) {
            $email['options'] = maybe_unserialize($email['options']);
            if (!is_array($email['options'])) {
                $email['options'] = [];
            }
        }
        return $email;
    }

    /**
     * Delete one or more emails identified by ID (single value or array of ID)
     *
     * @global wpdb $wpdb
     * @param int|array $id Single numeric ID or an array of IDs to be deleted
     * @return boolean
     */
    function delete_email($id) {
        global $wpdb;
        $r = $this->store->delete(NEWSLETTER_EMAILS_TABLE, $id);
        if ($r !== false) {
            // $id could be an array if IDs
            $id = (array) $id;
            foreach ($id as $email_id) {
                $wpdb->delete(NEWSLETTER_STATS_TABLE, ['email_id' => $email_id]);
                $wpdb->delete(NEWSLETTER_SENT_TABLE, ['email_id' => $email_id]);
            }
        }
        return $r;
    }

    function get_email_field($id, $field_name) {
        return $this->store->get_field(NEWSLETTER_EMAILS_TABLE, $id, $field_name);
    }

    /**
     * Returns the email unique key
     * @param TNP_User $user
     * @return string
     */
    function get_email_key($email) {
        if (!$email)
            return '0-0';

        if (!isset($email->token)) {
            return $email->id . '-';
        }
        return $email->id . '-' . $email->token;
    }

    /**
     *
     * @param type $filters
     * @param string|null $language
     * @return type
     */
    function get_posts($filters = [], $language = '') {

        if ($language) {
            $this->switch_language($language);
            //do_action('wpml_switch_language', $language);
            $filters['suppress_filters'] = false;
            $filters = apply_filters('newsletter_get_posts_filters', $filters, $language);
        }

        // Fix by B. K. (see ticket 301048)
        if (empty($filters['orderby'])) {
            $filters['orderby'] = 'date';
            $filters['order'] = 'DESC';
        }

        $posts = get_posts($filters);

        if ($language) {
            $this->restore_language();
            //do_action('wpml_switch_language', Newsletter::$language);
        }
        return $posts;
    }

    function get_wp_query($filters, $language = '') {
        if ($language) {
            $this->switch_language($language);
            if (class_exists('SitePress')) {
                //do_action('wpml_switch_language', $language);
                $filters['suppress_filters'] = false;
            } else if (class_exists('Polylang')) {
                $filters['lang'] = $language;
            }

            $filters = apply_filters('newsletter_get_posts_filters', $filters, $language);
        }

        $posts = new WP_Query($filters);

        if ($language) {
            $this->restore_language($language);
            //do_action('wpml_switch_language', Newsletter::$language);
        }

        return $posts;
    }

    /**
     * Process a collected IP to be stored following the privacy settings.
     *
     * @param string $ip
     * @return string
     */
    static function process_ip($ip) {
        $option = Newsletter::instance()->get_option('ip');
        if (!$option) {
            return $ip;
        }
        if ($option === 'anonymize') {
            return self::anonymize_ip($ip);
        }
        return '';
    }

    /**
     * Remove the last byte of an IPv4 address.
     *
     * @param string $ip
     * @return string
     */
    static function anonymize_ip($ip) {
        if (empty($ip)) {
            return $ip;
        }
        $parts = explode('.', $ip);
        array_pop($parts);
        return implode('.', $parts) . '.0';
    }

    /**
     * Converts a GMT date from mysql (see the posts table columns) into a timestamp.
     *
     * @param string $s GMT date with format yyyy-mm-dd hh:mm:ss
     * @return int A timestamp
     */
    static function m2t($s) {
        // TODO: use the wordpress function I don't remember the name
        $s = explode(' ', $s);
        $d = explode('-', $s[0]);
        $t = explode(':', $s[1]);
        return gmmktime((int) $t[0], (int) $t[1], (int) $t[2], (int) $d[1], (int) $d[2], (int) $d[0]);
    }

    /**
     * Adds query string parameters to an URL checing id there are already other parameters.
     *
     * @param string $url
     * @param string $qs The part of query-string to add (param1=value1&param2=value2...)
     * @param boolean $amp If the method must use the &amp; instead of the plain & (default true)
     * @return string
     */
    static function add_qs($url, $qs) {
        if (strpos($url, '?') !== false) {
            return $url . '&' . $qs;
        } else {
            return $url . '?' . $qs;
        }
    }

    static function get_home_url() {
        static $url = false;
        if (!$url) {
            $url = home_url('/');
        }
        return $url;
    }

    function get_action_base_url() {

        // TODO: manage the "rest" option when implemented
        $type = Newsletter::instance()->get_main_option('links');
        if (empty($type)) {
            return $this->get_home_url();
        } else {
            return admin_url('admin-ajax.php') . '?action=tnp';
        }
    }

    /**
     * Builds a standard Newsletter action URL for the specified action.
     *
     * @param string $action
     * @param TNP_User $user
     * @param TNP_Email $email
     * @return string
     */
    function build_action_url($action, $user = null, $email = null, $lifespan = DAY_IN_SECONDS) {
        $url = $this->get_action_base_url();

        $url = $this->add_qs($url, 'na=' . rawurlencode($action));

        if ($user) {
            $url .= '&nk=' . rawurlencode($this->get_user_key($user, $action, $lifespan));
        }
        if ($email) {
            $url .= '&nek=' . rawurlencode($this->get_email_key($email));
        }
        return $url;
    }

    function build_dummy_action_url($action) {
        $url = $this->get_action_base_url();

        $url = $this->add_qs($url, 'na=' . rawurlencode($action));
        $language = $this->language();
        if (empty($language)) {
            $language = '0';
        }
        $url .= '&nk=0-' . $language;
        return $url;
    }

    function build_action_url_ajax($action, $user = null, $email = null) {
        $url = admin_url('admin-ajax.php') . '?action=tnp&na=' . rawurlencode($action);

        if ($user) {
            $url .= '&nk=' . rawurlencode($this->get_user_key($user, $action));
        }
        if ($email) {
            $url .= '&nek=' . rawurlencode($this->get_email_key($email));
        }
        return $url;
    }

    static function deep_replace($search, $subject) {
        $count = 1;
        while ($count) {
            $subject = str_replace($search, '', $subject, $count);
        }

        return $subject;
    }

    static function sanitize_user_field($value, $max = 250) {
        if (!$value) {
            return '';
        }
        $value = html_entity_decode($value, ENT_QUOTES);
        $value = wp_strip_all_tags($value, true);
        $value = str_replace(['{', '}', '[', ']', '>', '<'], '', $value); // Tags cannot be used on user's fields
        $value = self::deep_replace(['%7B', '%7D'], $value);
        $value = str_replace(';', ' ', $value);
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }

        return $value;
    }

    /**
     * Sanitize the subscriber first and last name
     *
     * @param string $value
     * @return string
     */
    static function sanitize_name($value) {
        $value = self::sanitize_user_field($value);
        if (mb_strlen($value) > 100) {
            $value = mb_substr($value, 0, 100);
        }
        return $value;
    }

    /**
     * @see sanitize_name
     * @deprecated
     */
    static function normalize_name($value) {
        return self::sanitize_name($value);
    }

    static function sanitize_gender($gender = 'n') {
        if (empty($gender)) {
            return 'n';
        }
        $gender = trim(strtolower($gender));
        if (empty($gender)) {
            return 'n';
        }
        $gender = substr($gender, 0, 1);
        if ($gender !== 'f' && $gender !== 'm') {
            $gender = 'n';
        }
        return $gender;
    }

    static function sanitize_language($value = '') {
        $languages = self::get_languages();
        return isset($languages[$value]) ? $value : '';
    }

    static function sanitize_country($value) {
        return self::sanitize_user_field($value, 2);
    }

    /**
     * @see sanitize_gender
     * @deprecated
     */
    static function normalize_sex($sex) {
        return self::sanitize_gender($sex);
    }

    /**
     *
     * @param TNP_Subscription_Data $data
     */
    static function sanitize_subscription_data($data) {
        $data->email = self::sanitize_email($data->email);
        $data->name = self::sanitize_name($data->name);
        $data->surname = self::sanitize_name($data->surname);
        $data->sex = self::sanitize_gender($data->sex);
        $data->language = self::sanitize_language($data->language);

        if (isset($data->city)) {
            $data->city = self::sanitize_user_field($data->city, 50);
        }

        if (isset($data->region)) {
            $data->region = self::sanitize_user_field($data->region, 50);
        }

        if (isset($data->source)) {
            $data->source = self::sanitize_user_field($data->source, 50);
        }

        if (isset($data->http_referer)) {
            $data->http_referer = self::sanitize_user_field($data->http_referer, 200);
        }

        if (isset($data->referrer)) {
            $data->referrer = self::sanitize_user_field($data->referrer, 50);
        }

        for ($i = 1; $i < NEWSLETTER_PROFILE_MAX; $i++) {
            $field = 'profile_' . $i;
            if (isset($data->$field)) {
                $data->$field = self::sanitize_user_field($data->$field);
            }
        }
    }

    /**
     * @todo Optimize.
     *
     * @param string $email
     * @param bool $empty_ok
     * @return bool
     */
    static function is_email($email, $empty_ok = false) {

        if (!is_string($email)) {
            return false;
        }

        $email = trim($email);

        if ($email === '') {
            return $empty_ok;
        }

        // WP function
        if (!is_email($email)) {
            return false;
        }

        $set = "{}<>%";

        if (strpbrk($email, $set) !== false) {
            return false;
        }

        if (self::sanitize_user_field($email) !== $email) {
            return false;
        }
        // Extra checks

        if (mb_strlen($email) > 100) {
            return false;
        }

        if (strpos($email, '..') !== false) {
            return false;
        }

        if (strpos($email, '.@') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Returns the email address normalized, lowercase with no spaces. If it's not a valid email
     * returns false.
     */
    static function normalize_email($email) {

        $email = strtolower(trim($email));
        if (!self::is_email($email)) {
            return false;
        }

        return $email;
    }

    /**
     * Try to sanitize and email and return false if it is not valid.
     * @param string $email
     * @return bool
     */
    static function sanitize_email($email) {
        return self::normalize_email($email);
    }

    /**
     * Returns a WP option granting to be an array.
     *
     * @param string $name
     * @param array $default
     * @return array
     */
    static function get_option_array($name, $default = []) {
        $opt = get_option($name, []);
        if (!is_array($opt)) {
            return $default;
        }
        return $opt;
    }

    /** Returns a random token of the specified size (or 10 characters if size is not specified).
     *
     * @param int $size
     * @return string
     */
    static function get_token($size = 16) {
        return wp_generate_password($size, false, false);
    }

    /**
     * Takes in a variable and checks if object, array or scalar and return the integer representing
     * a database record id.
     *
     * @param mixed $var
     * @return in
     */
    static function to_int_id($var) {
        if (is_object($var)) {
            return (int) $var->id;
        }
        if (is_array($var)) {
            return (int) $var['id'];
        }
        return (int) $var;
    }

    /**
     * Transform a multilines text onto an array, trimming and lowercasing every value.
     *
     * @param string $text
     * @return array
     */
    static function to_array($text) {
        $text = trim($text);
        if (empty($text)) {
            return [];
        }
        $text = preg_split("/\\r\\n/", $text);
        $text = array_map('trim', $text);
        $text = array_map('strtolower', $text);
        $text = array_filter($text);

        return $text;
    }

    static function sanitize_ip($ip) {
        if (empty($ip)) {
            return '';
        }
        $ip = preg_replace('/[^0-9a-fA-F:., ]/', '', trim($ip));
        if (strlen($ip) > 50) {
            $ip = substr($ip, 0, 50);
        }

        // When more than one IP is present due to firewalls, proxies, and so on. The first one should be the origin.
        if (strpos($ip, ',') !== false) {
            list($ip, $tail) = explode(',', $ip, 2);
        }
        return $ip;
    }

    /**
     * Extracts the remote IP searching even forwarded IP by proxies.
     *
     * @return string
     */
    static function get_remote_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return self::sanitize_ip($ip);
    }

    static function sanitize_file_name($name) {
        return preg_replace('/[^0-9a-z_\\-]/i', '', $name);
    }

    /**
     * Cleans up a text containing url tags with appended the absolute URL (due to
     * the editor behavior) moving back them to the simple form.
     */
    static function clean_url_tags($text) {
        $text = str_replace('%7B', '{', $text);
        $text = str_replace('%7D', '}', $text);

        // Only tags which are {*_url}
        $text = preg_replace("/[\"']http[^\"']+(\\{[^\\}]+_url\\})[\"']/i", "\"\\1\"", $text);
        return $text;
    }

    static function clean_eol($text) {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = str_replace("\n", "\r\n", $text);
        return $text;
    }

    static function dienow($message, $admin_message = null, $http_code = 200) {
        $message = esc_html($message);
        if ($admin_message && current_user_can('administrator')) {
            $message .= '<br><br><strong>Text below only visibile to administrators</strong><br>';
            $message .= esc_html($admin_message);
        }
        http_response_code($http_code);
        ?>
        <!DOCTYPE html>
        <html>
            <head></head>
            <body style="font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0;">
                <div class="box" style="border: 1px solid #ccc; padding: 20px;">
                    <?= $message ?>
                </div>
            </body>
        </html>
        <?php
        die();
    }

    static function dump($var) {
        if (NEWSLETTER_DEBUG) {
            var_dump($var);
        }
    }

    static function dump_die($var) {
        if (NEWSLETTER_DEBUG) {
            var_dump($var);
            die();
        }
    }

    /* Wrong place but used by some addons */

    function get_email_status_slug($email) {
        $email = (object) $email;
        if ($email->status == 'sending' && $email->send_on > time()) {
            return 'scheduled';
        }
        return $email->status;
    }

    function get_email_status_label($email) {
        $email = (object) $email;
        $status = $this->get_email_status_slug($email);
        switch ($status) {
            case 'sending':
                return __('Sending', 'newsletter');
            case 'scheduled':
                return __('Scheduled', 'newsletter');
            case 'sent':
                return __('Sent', 'newsletter');
            case 'paused':
                return __('Paused', 'newsletter');
            case 'new':
                return __('Draft', 'newsletter');
            default:
                return ucfirst($email->status);
        }
    }

    function show_email_status_label($email) {
        echo '<span class="tnp-status tnp-email-status tnp-email-status--', $this->get_email_status_slug($email), '">', esc_html($this->get_email_status_label($email)), '</span>';
    }

    function get_email_progress($email, $format = 'percent') {
        return $email->total > 0 ? intval($email->sent / $email->total * 100) : 0;
    }

    static function get_email_default_text_part() {
        return __("This email requires a modern e-mail reader.\nView online: {email_url}\nChange your subscription: {profile_url}\nUnsubscribe: {unsubscription_url}", 'newsletter');
    }

    /**
     * Echos the progress bar of an email. Attributes:
     *
     * - scheduled: if true the future date is shown
     *
     * @param type $email
     * @param type $attrs
     */
    function show_email_progress_bar($email, $attrs = []) {

        $email = (object) $email;

        $attrs = array_merge(array('format' => 'percent', 'numbers' => false, 'scheduled' => false), $attrs);

        if ($email->status == 'sending' && $email->send_on > time()) {
            if ($attrs['scheduled']) {
                echo '<span class="tnp-progress-date">', esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $email->send_on)), '</span>';
            }
            return;
        } else if ($email->status == 'new') {
            echo '';
            return;
        } else if ($email->status == 'sent') {
            $percent = 100;
        } else {
            $percent = $this->get_email_progress($email);
        }

        echo '<div class="tnp-progress tnp-progress--' . esc_attr($email->status) . '">';
        echo '<div class="tnp-progress-bar" role="progressbar" style="width: ', esc_attr($percent), '%;">&nbsp;', esc_attr($percent), '%&nbsp;</div>';
        echo '</div>';
        if ($attrs['numbers']) {
            if ($email->status == 'sent') {
                echo '<div class="tnp-progress-numbers">', ((int) $email->total), ' ', esc_html__('of', 'newsletter'), ' ', ((int) $email->total), '</div>';
            } else {
                echo '<div class="tnp-progress-numbers">', ((int) $email->sent), ' ', esc_html__('of', 'newsletter'), ' ', ((int) $email->total), '</div>';
            }
        }
    }

    function show_email_progress_numbers($email, $attrs = []) {

        $email = (object) $email;

        $attrs = array_merge(array('format' => 'percent', 'numbers' => false, 'scheduled' => false), $attrs);

        if ($email->status == 'sending' && $email->send_on > time()) {
            //return;
        } else if ($email->status == 'new') {
            return;
        }


        if ($email->status == 'sent') {
            echo '<div class="tnp-progress-numbers">', $email->total, '/', $email->total, '</div>';
        } else {
            echo '<div class="tnp-progress-numbers">', $email->sent, '/', $email->total, '</div>';
        }
    }

    function get_email_type_label($type) {

// Is an email?
        if (is_object($type)) {
            $type = $type->type;
        }

        $label = apply_filters('newsletter_email_type', '', $type);

        if (!empty($label)) {
            return $label;
        }

        switch ($type) {
            case 'followup':
                return 'Followup';
            case 'message':
                return 'Standard Newsletter';
            case 'feed':
                return 'Feed by Mail';
        }

        if (strpos($type, 'automated') === 0) {
            list($a, $id) = explode('_', $type);
            return 'Automated Channel ' . $id;
        }

        return ucfirst($type);
    }

    function get_email_progress_label($email) {
        if ($email->status == 'sent' || $email->status == 'sending') {
            return $email->sent . ' ' . __('of', 'newsletter') . ' ' . $email->total;
        }
        return '-';
    }

    static function redirect($url) {
        wp_redirect($url);
        die();
    }

    static function redirect_local($url) {
        $url = wp_sanitize_redirect($url);
        $local_host = parse_url(home_url(), 'host');
        $url_host = parse_url($url, 'host');

        if ($url_host !== $local_host) {
            die('Redirect URL invalid');
        }

        wp_redirect($url);
        die();
    }

    function set_lock($name, $duration) {
        global $wpdb;

        $duration = (int) $duration;

        $wpdb->flush();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'newsletter_lock_' . $name));
        if ($row) {
            $value = (int) $row->option_value;
            if ($value < time()) {
                $wpdb->query($wpdb->prepare("update $wpdb->options set option_value=%s where option_id=%d limit 1", '' . (time() + $duration), $row->option_id));
                $wpdb->flush();
                return true;
            }
            return false;
        }
        $wpdb->insert($wpdb->options, ['option_name' => 'newsletter_lock_' . $name, 'option_value' => '' . (time() + $duration)]);
        $wpdb->flush();
        return true;
    }

    function reset_lock($name) {
        global $wpdb;
        $wpdb->query($wpdb->prepare("update $wpdb->options set option_value=%s where option_name=%s limit 1", '0', 'newsletter_lock_' . $name));
        $wpdb->flush();
    }

    /**
     * To be used only when debugging, then all calls should be removed.
     *
     * @param mixed $text
     */
    function error_log($text) {
        if (!NEWSLETTER_DEBUG) {
            return;
        }
        if (!is_scalar($text)) {
            error_log(print_r($text, true));
        } else {
            error_log($text);
        }
    }
}
