<?php

namespace Newsletter;

defined('ABSPATH') || exit;

class License {

    static function get_key() {
        if (defined('NEWSLETTER_LICENSE_KEY')) {
            return NEWSLETTER_LICENSE_KEY;
        }
        $key = \Newsletter::instance()->get_main_option('contract_key');
        return $key ?: false;
    }

    static function get_data($refresh = false) {
        $license_key = self::get_key();
        if (empty($license_key)) {
            delete_transient('newsletter_license_data');
            return false;
        }

        if (!$refresh) {
            $license_data = get_transient('newsletter_license_data');
            if ($license_data !== false && is_object($license_data)) {
                return $license_data;
            }
        }

        $license_data_url = 'https://www.thenewsletterplugin.com/wp-content/plugins/file-commerce-pro/get-license-data.php';

        $response = wp_remote_post($license_data_url, [
            'body' => ['k' => $license_key],
            'timeout' => 30
        ]);

        // Fall back to http...
        if (is_wp_error($response)) {
            $license_data_url = str_replace('https', 'http', $license_data_url);
            $response = wp_remote_post($license_data_url, array(
                'body' => array('k' => $license_key),
                'timeout' => 30
            ));
            if (is_wp_error($response)) {
                set_transient('newsletter_license_data', $response, DAY_IN_SECONDS);
                return $response;
            }
        }

        $download_message = 'You can download all addons from www.thenewsletterplugin.com if your license is valid.';

        if (wp_remote_retrieve_response_code($response) != '200') {
            $data = new \WP_Error(wp_remote_retrieve_response_code($response),
                    '[' . esc_html(wp_remote_retrieve_response_code($response)) . '] '
                    . esc_html(wp_remote_retrieve_response_message($response))
                    . '<br>' . $download_message);

            set_transient('newsletter_license_data', $data, DAY_IN_SECONDS);
            return $data;
        }

        $json = wp_remote_retrieve_body($response);
        $data = json_decode($json);

        if (!is_object($data)) {
            $data = new \WP_Error(1, 'License validation service error. <br>' . $download_message);
            set_transient('newsletter_license_data', $data, DAY_IN_SECONDS);
            return $data;
        }

        if (isset($data->message)) {
            $data = new \WP_Error(1, 'License check: ' . $data->message);
            set_transient('newsletter_license_data', $data, DAY_IN_SECONDS);
            return $data;
        }

        $expiration = WEEK_IN_SECONDS;
        // If the license expires in few days, make the transient live only few days, so it will be refreshed
        if ($data->expire > time() && $data->expire - time() < WEEK_IN_SECONDS) {
            $expiration = $data->expire - time();
        }
        set_transient('newsletter_license_data', $data, $expiration);

        return $data;
    }

    static function update() {
        self::get_data(true);
    }

    static function get_badge() {
        $license_data = self::get_data(false);
        $badge = '';

        if (is_wp_error($license_data)) {
            $badge = '<span class="tnp-badge-red"><a href="?page=newsletter_main_main">License check failed</a></span>';
        } else {
            if ($license_data !== false) {
                $type = $license_data->type ?? 'personal';
                if ($type === 'personal')
                    $type = '';
                $class = $type === 'reseller' ? 'tnp-badge-blue' : 'tnp-badge-green';
                if ($license_data->expire == 0) {
                    $badge = '<span class="tnp-badge-green"><a href="?page=newsletter_main_main">Free license</a></span>';
                } elseif ($license_data->expire >= time()) {
                    $badge = '<span class="' . $class . '"><a href="?page=newsletter_main_main">' . esc_html($type) . ' license expires on ' . esc_html(date('Y-m-d', $license_data->expire))
                            . '</a></span>';
                } else {
                    $badge = '<span class="tnp-badge-red"><a href="?page=newsletter_main_main">' . esc_html($type) . ' license expired on ' . esc_html(date('Y-m-d', $license_data->expire))
                            . '</a></span>';
                }
            } else {
                $badge = '<span class="tnp-badge-gray"><a href="?page=newsletter_main_main">License not set</a></span>';
            }
        }

        return $badge;
    }

    /**
     * Changing this code does not bypass the server side validation checks and does not enable
     * premium services.
     *
     * @return bool
     */
    static function is_premium() {
        $license_data = self::get_data();
        if (empty($license_data)) {
            return false;
        }
        if (is_wp_error($license_data)) {
            return false;
        }

        return $license_data->expire >= time();
    }

    static function is_free() {
        return !self::is_premium();
    }

    static function is_personal() {
        $license_data = self::get_data(false);
        if (is_wp_error($license_data) || !$license_data) {
            return true;
        }
        return $license_data->type === 'personal';
    }

    static function is_reseller() {
        $license_data = self::get_data(false);
        if (is_wp_error($license_data) || !$license_data) {
            return false;
        }
        return $license_data->type === 'reseller';
    }
}
