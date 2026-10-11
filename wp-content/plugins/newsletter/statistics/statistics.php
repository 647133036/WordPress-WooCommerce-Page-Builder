<?php

defined('ABSPATH') || exit;

/**
 * Manages the clicks and opens tracking.
 */
class NewsletterStatistics extends NewsletterModule {

    static $instance;

    const SENT_NONE = 0;
    const SENT_READ = 1;
    const SENT_CLICK = 2;
    const TRACKING_STANDARD = 1;
    const TRACKING_ANONYMOUS = 2;

    // Internal variables for the relink callback
    var $relink_email_id = 0;
    var $relink_user_id = 0;
    var $relink_message_id = 0;
    var $relink_key = '';
    var $relink_url = '';
    var $relink_url_type = '';
    var $relink_type = 0;
    var $relink_tracking_type = self::TRACKING_STANDARD;

    /**
     * @return NewsletterStatistics
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        parent::__construct('statistics');
        add_action('wp_loaded', [$this, 'hook_wp_loaded']);
        add_action('rest_api_init', [$this, 'hook_rest_api_init']);
    }

    function hook_wp_loaded() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            add_action('wp_ajax_tnptr', [$this, 'tracking']);
            add_action('wp_ajax_nopriv_tnptr', [$this, 'tracking']);
            return; // Important to avoid double tracking by the "tracking" method when ajax is activated
        }

        $this->tracking();
    }

    function hook_rest_api_init() {
        // Open tracking route
        register_rest_route('tnp', '/o/(?P<email_id>[\d]+)/(?P<user_id>-?[\d]+)/(?P<signature>.+).gif', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'open_callback'],
            'permission_callback' => '__return_true',
        ));

        // Click tracking route
        register_rest_route('tnp', '/l/(?P<email_id>[\d]+)/(?P<user_id>-?[\d]+)/(?P<url>.+)/(?P<signature>.+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'link_callback']
        ]);
    }

    function open_callback($request) {
        $this->logger->debug('REST open tracking');

        $text = $request['email_id'] . '/' . $request['user_id'];
        // Standard signature, can be 1 or 2 if expired, but we do not check the expiration for open links
        $verified = $this->verify_signature('o/' . $text, $request['signature']);
        if (!$verified) {
            // Old style signature (to be removed)
            $verified = $this->check_signature($text, $request['signature']);
        }

        // Old key signature
        if (!$verified && !$this->is_old_key_expired()) {
            $verified = $this->check_old_signature('o/' . $text, $request['signature']);
            if (!$verified) {
                $verified = $this->check_old_signature($text, $request['signature']);
            }
        }

        if (!$verified) {
            $this->logger->debug('Invalid open signature');
            //return new WP_Error('signature', '', ['status' => 400]);
            //wp_send_json_error([], 400);
            http_response_code(400);
            die('Invalid signature');
        }

        $email = $this->get_email($request['email_id']);
        if ($email) {
            $user_id = intval($request['user_id']);
            if ($user_id < 0) {
                $this->register_anonymous_open($email->id, abs($user_id));
            } else {
                $user = $this->get_user($user_id);
                if ($user) {
                    $this->register_open($email->id, $user->id);
                }
            }
        }

        // Send an image anyway
        $this->send_tracking_image();
    }

    function link_callback($request) {
        $this->logger->debug('REST link tracking');

        $url = $this->base64url_decode($request['url']);
        $email = $this->get_email($request['email_id']);
        $user_id = intval($request['user_id']);
        $user = null;
        if ($user_id > 0) {
            $user = $this->get_user($user_id);
        }

        // Two types of signature for compatibility; can be removed in a year
        $text = $request['email_id'] . '/' . $request['user_id'] . '/' . $request['url'];

        // Standard signature
        $verified = $this->verify_signature('l/' . $text, $request['signature']);
        if (!$verified) {
            // Old style signature (to be removed)
            $verified = $this->verify_signature($text, $request['signature']);
        }

        // Try to verify with the old key
        if (!$verified) {

            $verified = $this->check_old_signature('l/' . $text, $request['signature']);
            if (!$verified) {
                $verified = $this->check_old_signature($text, $request['signature']);
            }

            // Manage stats and redirect when the old key signature is valid
            if ($verified) {

                // Here when verified with the old key

                if ($email) {

                    if ($user_id < 0) {
                        $this->register_anonymous_click($email->id, abs($user_id), $url);
                    } else {
                        // Don't set the cookie
                        $this->register_click($email, $user, $url);
                    }
                }

                // If the old key grace period is ended, accept only redirects to the site domain
                if ($this->is_old_key_expired()) {
                    $url = wp_validate_redirect($url, home_url());
                }

                $this->send_redirect($url, $email, $user);
            }
        }

        if (!$verified) {
            $this->logger->debug('Invalid link signature');
            http_response_code(400);
            die('Invalid signature');
        }

        // Here when verified with the current key

        if ($email) {

            if ($user_id < 0) {
                $this->register_anonymous_click($email->id, abs($user_id), $url);
            } else {
                if ($verified === 1) { // Key not expired
                    $this->set_user_cookie($user);
                }
            }
            $this->register_click($email, $user, $url);
        }

        // We keep the tracking link active even if the subscriber or the email are now missing...
        // Could be changed in the future
        $this->send_redirect($url, $email, $user);
    }

    /**
     *
     * @param int $email_id
     * @param int $user_id
     */
    function register_open($email_id, $user_id) {
        $this->add_open($email_id, $user_id);
        $this->update_open_value(self::SENT_READ, $user_id, $email_id);
        $this->reset_stats_time($email_id);
        $this->update_user_last_activity($user_id);
    }

    function register_anonymous_open($email_id, $message_id) {
        $this->add_anonymous_open($email_id, $message_id);
        $this->reset_stats_time($email_id);
    }

    /**
     *
     * @param object $email
     * @param object $user
     * @param string $url
     */
    function register_click($email, $user, $url) {
        $this->logger->debug(__METHOD__);
        if (!$email) {
            $this->logger->debug('Invalid email');
            return;
        }
        if (!$user) {
            $this->logger->debug('Invalid user');
            return;
        }

        $ip = $this->process_ip($this->get_remote_ip());
        $this->update_user_ip($user, $ip);
        $this->update_user_last_activity($user);

        $is_action = $this->is_action_url($url);

        if ($is_action) {
            // TODO: register placeholder URLs representing an action
            // Track a Newsletter action as an email open and not a click
            $this->update_open_value(self::SENT_READ, $user->id, $email->id, $ip);
            $this->logger->debug('Click on action link');
        } else {
            //$url = apply_filters('newsletter_pre_save_url', $url, $email, $user);
            if ($email) {
                $this->add_click($url, $email->id, $user->id, $ip);
                $this->update_open_value(self::SENT_CLICK, $user->id, $email->id, $ip);
                $this->logger->debug('Click registered');
            }
        }

        $this->reset_stats_time($email->id);
    }

    function register_anonymous_click($email_id, $message_id, $url) {

        $is_action = $this->is_action_url($url);
        if ($is_action) {
            // TODO: register placeholder URLs representing an action
            $this->add_anonymous_open($email_id, $message_id);
        } else {
            $this->add_anonymous_click($url, $email_id, $message_id);
        }

        $this->reset_stats_time($email_id);
    }

    function send_tracking_image() {
        $this->logger->debug(__METHOD__);
        header('Content-Type: image/gif', true);
        echo base64_decode('_R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        die();
    }

    /**
     * Filter the URL, redirect and die.
     *
     * @param string $url
     * @param object $email
     * @param object $user
     */
    function send_redirect($url, $email = null, $user = null) {
        //$this->logger->debug(__METHOD__);
        header("Referrer-Policy: no-referrer");
        header('Location: ' . sanitize_url(apply_filters('newsletter_redirect_url', $url, $email, $user)));
        die();
    }

    /**
     * Tracking for standard and ajax tracking URLs
     */
    function tracking() {

        // Click tracking
        if (isset($_GET['nltr'])) {

            //$this->logger->debug('Click tracking');
            //$this->logger->debug('Code: ' . $_GET['nltr']);
            //$this->logger->debug('Decoded: ' . base64_decode($_GET['nltr']));
            // Patch for links with ;
            $parts = explode(';', base64_decode($_GET['nltr']));
            // Shifts and pops since the URL can contains ";"...
            $email_id = (int) array_shift($parts);
            $user_id = (int) array_shift($parts);
            $signature = array_pop($parts);
            $anchor = array_pop($parts); // No more used
            // The remaining elements are the url splitted when it contains ";"
            $url = implode(';', $parts);

            //$this->logger->debug('Email: ' . $email_id . ', User: ' . $user_id . ', Signature: ' . $signature . ', Url: ' . $url .
            //        ', Anchor: ' . $anchor);

            $verified = $this->verify_signature($email_id . ';' . $user_id . ';' . $url . ';' . $anchor, $signature);

            // Try the old signature with custom management; all that can be removed in a year (?)
            if (!$verified) {
                $this->logger->debug('Not verified by current key');
                $verified = $this->check_old_signature($email_id . ';' . $user_id . ';' . $url . ';' . $anchor, $signature);

                if ($verified) {

                    // Here when verified with the old key

                    $user = $this->get_user($user_id);
                    $email = $this->get_email($email_id);
                    $this->register_click($email, $user, $url);

                    // Don't set the cookie
                    // If the old key grace period is ended, accept only redirects to the site domain
                    if ($this->is_old_key_expired()) {
                        $url = wp_validate_redirect($url, home_url());
                    }

                    $this->send_redirect($url, $email, $user);
                } else {
                    $this->logger->debug('Not verified by the old key');
                    $url = wp_validate_redirect($url, home_url());
                    $this->send_redirect($url);
                }
            }

            if (!$verified) {
                $this->logger->debug('Invalid link signature');
                http_response_code(400);
                die('Invalid signature');
            }

            // Here when verified with the current key

            $user = $this->get_user($user_id);
            if ($verified === 1) {
                $this->set_user_cookie($user);
            }

            $email = $this->get_email($email_id);
            //$this->set_email_cookie($email); // Obsolete

            $this->register_click($email, $user, $url);
            $this->send_redirect($url, $email, $user);
        }


        if (isset($_GET['noti'])) {

            //$this->logger->debug('Open tracking');
            //$this->logger->debug('Code: ' . $_GET['noti']);
            //$this->logger->debug('Decoded: ' . base64_decode($_GET['noti']));

            list($email_id, $user_id, $signature) = explode(';', base64_decode($_GET['noti']), 3);

            //$this->logger->debug('Email: ' . $email_id . ', User: ' . $user_id . ', Signature: ' . $signature);

            $email = $this->get_email($email_id);
            if (!$email) {
                $this->logger->error('Email not found, stop');
                $this->send_tracking_image();
            }

            $user = $this->get_user($user_id);
            if (!$user) {
                $this->logger->debug('User not found, stop');
                $this->send_tracking_image();
            }

            $verified = false;

            // Old signature, to be removed
            if ($email->token) {
                $verified = md5($email->id . $user->id . $email->token) === $signature;
            }

            // The first signature check if for compatibility, it'll be removed
            $text = $email->id . '/' . $user->id;
            $verified = $verified || $this->check_signature($text, $signature) !== 0 ||
                    $this->check_signature('o/' . $text, $signature) !== 0;

            // User the old key, if not expired
            if (!$verified && !$this->is_old_key_expired()) {
                $verified = $this->check_old_signature($text, $signature) ||
                        $this->check_old_signature('o/' . $text, $signature);
            }

            if (!$verified) {
                $this->logger->error('Wrong signature, stop');
                $this->send_tracking_image();
            }

            // Should be care about expired signature for open tracking? No...

            $this->register_open($email->id, $user->id);
            $this->send_tracking_image();
        }
    }

    function get_key() {
        if (defined('NEWSLETTER_RELINK_KEY')) {
            return NEWSLETTER_RELINK_KEY;
        }
        return $this->get_main_option('key');
    }

    function get_old_key() {
        if (defined('NEWSLETTER_RELINK_KEY')) {
            return NEWSLETTER_RELINK_KEY;
        }
        return $this->get_main_option('old_key');
    }

    function get_key_time() {
        return (int) $this->get_main_option('key_time');
    }

    function get_signature($text, $duration = 7 * DAY_IN_SECONDS) {
        // TODO: Add key caching
        $time = (string) (time() + $duration);
        return $time . '.' . md5($text . $time . $this->get_key());
    }

    function get_old_signature($text) {
        // TODO: Add key caching
        return md5($text . $this->get_old_key());
    }

    function check_signature($text, $signature) {
        return $this->verify_signature($text, $signature) !== 0;
    }

    /**
     *
     * @param type $text
     * @param type $signature
     * @return int 0 - invalid; 1 - valid; 2 - valid but expired or without the timestamp
     */
    function verify_signature($text, $signature) {
        $signature = trim($signature);
        if (!$signature) {
            return 0;
        }

        $parts = explode('.', $signature, 2);
        if (count($parts) === 2) {
            $time = (int) $parts[0];

            $verified = hash_equals(md5($text . $time . $this->get_key()), $parts[1]);

            if (!$verified) {
                return 0;
            }
            return $time >= time() ? 1 : 2;
        } else {
            // Signature w/o timestamp
            return hash_equals(md5($text . $this->get_key()), $signature) ? 2 : 0;
        }
    }

    function check_old_signature($text, $signature) {
        $signature = trim($signature);
        if (!$signature) {
            return false;
        }

        return $this->get_old_signature($text) === $signature;
    }

    function is_old_key_expired() {
        return $this->get_key_time() < time() - MONTH_IN_SECONDS;
    }

    function is_action_url($url) {
        return strpos($url, '?na=') !== false || strpos($url, '&na=') !== false;
    }

    function get_ip() {
        return $this->process_ip($this->get_remote_ip());
    }

    /**
     * Reset the timestamp which indicates the specific email stats must be recalculated.
     *
     * @global wpdb $wpdb
     * @param stdClass|int $email
     */
    function reset_stats_time($email) {
        global $wpdb;
        $email_id = $this->to_int_id($email);
        $wpdb->update(NEWSLETTER_EMAILS_TABLE, ['stats_time' => 0], ['id' => $email_id]);
    }

    /**
     *
     * @param string $text
     * @param mixed $email
     * @param mixed $user
     * @param TNP_Mailer_Message $message
     * @return string
     */
    function relink($text, $email, $user, $message) {

        $this->logger->debug(__METHOD__);

        // Should be checked externally
        if (empty($email->track)) {
            return $text;
        }

        $this->relink_tracking_type = $email->track; // 1 or 2
        // If we have no consent for full subscriber tracking we change to anonymous tracking.
        // The subscriber cannot control to total tracking removal.
        if (!$user->track) {
            $this->relink_tracking_type = self::TRACKING_ANONYMOUS;
        }

        $this->relink_email_id = $this->to_int_id($email);
        $this->relink_user_id = $this->to_int_id($user);
        $this->relink_message_id = $message->id;

        // URL format. This will be changed when even the action links can use the REST format
        // We support only "rest" link rewriting with anonymous tracking
        if ($this->relink_tracking_type == self::TRACKING_ANONYMOUS) {
            $this->relink_url_type = 'rest';
        } else {
            $this->relink_url_type = Newsletter::instance()->get_main_option('tracking_links') ?? '';
            if (!$this->relink_url_type) {
                $this->relink_url_type = Newsletter::instance()->get_main_option('links') ?? '';
            }
        }

        $this->logger->debug('URL type: ' . $this->relink_url_type);

        if (empty($this->relink_key)) {
            $this->relink_key = $this->get_key();
        }

        if ($this->relink_url_type === 'ajax') {
            $this->relink_url = admin_url('admin-ajax.php?action=tnptr&nltr=');
        } else {
            $this->relink_url = home_url('/') . '?nltr=';
        }

        $text = preg_replace_callback('/(<[aA][^>]+href[\s]*=[\s]*["\'])([^>"\']+)(["\'][^>]*>)(.*?)(<\/[Aa]>)/is', [$this, 'relink_callback'], $text);

        // Open tracking image
        if ($this->relink_tracking_type == self::TRACKING_ANONYMOUS) {
            $uri = 'o/' . $this->relink_email_id . '/-' . $this->relink_message_id;
            $signature = $this->get_signature($uri, 6 * MONTH_IN_SECONDS);
            $src = rest_url('tnp/' . $uri . '/' . $signature . '.gif');
            $img = '<img width="1" height="1" style="display: none !important; width:1px; height:1px; border:0; outline:none;" alt="" src="' . esc_attr($src) . '">';

            $text = str_replace('</body>', "\n" . $img . "\n</body>", $text);
        } else {

            $uri = 'o/' . $this->relink_email_id . '/' . $this->relink_user_id;
            $signature = $this->get_signature($uri, 6 * MONTH_IN_SECONDS);

            $img1 = '';
            if ($this->relink_url_type === 'ajax') {
                $url = admin_url('admin-ajax.php?action=tnptr&noti=') . rawurlencode(base64_encode($this->relink_email_id . ';' . $this->relink_user_id . ';' . $signature));
                $img1 = '<img width="1" height="1" style="display: none !important; width:1px; height:1px; border:0; outline:none;" alt="" src="' . esc_attr($url) . '"/>';
            } else {
                $url = home_url('/') . '?noti=' . rawurlencode(base64_encode($this->relink_email_id . ';' . $this->relink_user_id . ';' . $signature));
                $img1 = '<img width="1" height="1" style="display: none !important; width:1px; height:1px; border:0; outline:none;" alt="" src="' . esc_attr($url) . '"/>';
            }

            // New REST tracking (always added)
            $src = rest_url('tnp/' . $uri . '/' . $signature . '.gif');
            $img3 = '<img width="1" height="1" style="display: none !important; width:1px; height:1px; border:0; outline:none;" alt="" src="' . esc_attr($src) . '">';

            $text = str_replace('</body>', "\n" . $img1 . "\n" . $img3 . "\n</body>", $text);
        }

        return $text;
    }

    function can_relink($url) {
        if (strpos($url, '{') === 0) {
            return false;
        }

        // Do not relink anchors
        if (substr($url, 0, 1) === '#') {
            return false;
        }
        // Do not relink mailto:
        if (substr($url, 0, 7) === 'mailto:') {
            return false;
        }

        return true;
    }

    /**
     * $matches[0] contains the full A tag.
     *
     * @param array $matches
     * @return string
     */
    function relink_callback($matches) {
        $this->logger->debug(__METHOD__);
        $this->logger->debug('URL: ' . $matches[2]);

        $href = trim(str_replace('&amp;', '&', $matches[2]));

        if (!$this->can_relink($href)) {
            return $matches[0];
        }

        if ($this->relink_url_type === 'rest') {
            $encoded_href = $this->base64url_encode($href);
            if ($this->relink_tracking_type == self::TRACKING_STANDARD) {
                $uri = 'l/' . $this->relink_email_id . '/' . $this->relink_user_id . '/' . $encoded_href;
            } else {
                $uri = 'l/' . $this->relink_email_id . '/-' . $this->relink_message_id . '/' . $encoded_href;
            }

            $signature = $this->get_signature($uri);
            $url = rest_url('/tnp/' . $uri . '/' . $signature);

            $this->logger->debug('Relinked URL: ' . $url);

            return $matches[1] . $url . $matches[3] . $matches[4] . $matches[5];
        }

        // Anonymous tracking cannot use non-rest URLs

        $anchor = ''; // No more used
        $r = $this->relink_email_id . ';' . $this->relink_user_id . ';' . $href . ';' . $anchor;
        $r = $r . ';' . $this->get_signature($r);
        $r = base64_encode($r);
        $r = rawurlencode($r);

        $url = $this->relink_url . $r;

        $this->logger->debug('Relinked URL: ' . $url);

        return $matches[1] . $url . $matches[3] . $matches[4] . $matches[5];
    }

    function update_stats($email) {
        global $wpdb;

        $wpdb->query($wpdb->prepare("update " . NEWSLETTER_SENT_TABLE . " s1 join " . $wpdb->prefix . "newsletter_stats s2 on s1.user_id=s2.user_id and s1.email_id=s2.email_id and s1.email_id=%d set s1.open=1, s1.ip=s2.ip", $email->id));
        $wpdb->query($wpdb->prepare("update " . NEWSLETTER_SENT_TABLE . " s1 join " . $wpdb->prefix . "newsletter_stats s2 on s1.user_id=s2.user_id and s1.email_id=s2.email_id and s2.url<>'' and s1.email_id=%d set s1.open=2, s1.ip=s2.ip", $email->id));
    }

    /**
     * Deleted all collected stats for an email.
     *
     * @global wpdb $wpdb
     * @param stdClass|int $email
     */
    function reset_stats($email) {
        global $wpdb;
        $email_id = $this->to_int_id($email);
        $this->query("delete from " . NEWSLETTER_SENT_TABLE . " where email_id=" . $email_id);
        $this->query("delete from " . NEWSLETTER_STATS_TABLE . " where email_id=" . $email_id);

        $wpdb->update(NEWSLETTER_EMAILS_TABLE, [
            'sent' => 0,
            'error_count' => 0,
            'unsub_count' => 0,
            'open_count' => 0,
            'click_count' => 0,
            'stats_time' => 0
                ], ['id' => $email_id]);
    }

    /**
     *
     * @param int $user_id
     * @param int $email_id
     * @param string $ip
     */
    function add_open($email_id, $user_id, $ip = null) {
        $this->add_click('', $email_id, $user_id, $ip);
    }

    function add_click($url, $email_id, $user_id, $ip = null) {
        global $wpdb;
        $this->logger->debug(__METHOD__);
        if (is_null($ip)) {
            $this->logger->debug('IP not provided');
            $ip = $this->get_ip();
        }

        if (strlen($url) > 254) {
            $this->logger->debug('IP longer than 254 characters, trimming');
            $url = substr($url, 0, 254);
        }

        $url = sanitize_url($url);

        $this->logger->debug([
            'email_id' => (int) $email_id,
            'user_id' => (int) $user_id,
            'url' => $url,
            'ip' => $ip
        ]);

        $this->insert(NEWSLETTER_STATS_TABLE,
                [
                    'email_id' => (int) $email_id,
                    'user_id' => (int) $user_id,
                    'url' => $url,
                    'ip' => $ip
                ]
        );
    }

    /**
     * Update the "open" columns of the sent table.
     *
     * @global wpdb $wpdb
     * @param int $user_id
     * @param int $email_id
     */
    function add_anonymous_open($email_id, $message_id) {
        $this->add_anonymous_click('', $email_id, $message_id);
    }

    /**
     *
     * @global wpdb $wpdb
     * @param string $url
     * @param int $email_id
     * @param int $message_id
     */
    function add_anonymous_click($url, $email_id, $message_id) {
        global $wpdb;

        if (strlen($url) > 254) {
            $url = substr($url, 0, 254);
        }

        $url = sanitize_url($url);

        $this->insert(NEWSLETTER_STATS_TABLE, [
            'email_id' => (int) $email_id,
            'message_id' => (int) $message_id,
            'url' => $url
        ]);

        $this->reset_stats_time($email_id);

//        $count = $wpdb->get_var($wpdb->prepare('select count(*) from ' . $wpdb->prefix . 'newsletter_tracking where email_id=%d and message_id=%d and url=%s',
//                        $email_id, $message_id, $url));
//
//        if (!$count) {
//            $this->insert($wpdb->prefix . 'newsletter_tracking', [
//                'email_id' => $email_id,
//                'message_id' => $message_id,
//                'url' => $url,
//                    ]
//            );
//        }
    }

    function update_open_value($value, $user_id, $email_id, $ip = null) {
        global $wpdb;
        if (is_null($ip)) {
            $ip = $this->get_ip();
        }
        $this->query($wpdb->prepare("update " . NEWSLETTER_SENT_TABLE . " set open=%d, ip=%s where email_id=%d and user_id=%d and open<%d limit 1", $value, $ip, $email_id, $user_id, $value));
    }

    /** For compatibility */
    function get_statistics_url($email_id) {
        $page = apply_filters('newsletter_statistics_view', 'newsletter_statistics_view');
        return 'admin.php?page=' . $page . '&amp;id=' . $email_id;
    }

    /** For compatibility */
    function get_index_url() {
        $page = apply_filters('newsletter_statistics_index', 'newsletter_statistics_index');
        return 'admin.php?page=' . $page;
    }

    /**
     * Used by Automated.
     *
     * @deprecated
     *
     * @param type $email_id
     * @return type
     */
    function get_total_count($email_id) {
        $report = $this->get_statistics($email_id);
        return $report->total;
    }

    function get_statistics($email) {
        return NewsletterStatisticsAdmin::instance()->get_statistics($email);
    }

    /**
     * Used by Automated.
     *
     * @deprecated
     *
     * @param type $email_id
     * @return type
     */
    function get_open_count($email_id) {
        $report = $this->get_statistics($email_id);
        return $report->open_count;
    }

    /**
     * Used by Automated.
     *
     * @deprecated
     *
     * @param type $email_id
     * @return type
     */
    function get_click_count($email_id) {
        $report = $this->get_statistics($email_id);
        return $report->click_count;
    }

    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

NewsletterStatistics::instance();

