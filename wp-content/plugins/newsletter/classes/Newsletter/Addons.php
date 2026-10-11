<?php

namespace Newsletter;

defined('ABSPATH') || exit;

class Addons {

    /**
     * Get the latest addons information but keeping the old one if the update fails.
     *
     * @return \Newsletter\WP_Error|bool
     */
    static function update() {
        update_option('newsletter_addons_updated', time(), false);

        // HTTP is ok here
        $url = "http://www.thenewsletterplugin.com/wp-content/extensions.json?ver=" . NEWSLETTER_VERSION;
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new \WP_Error(wp_remote_retrieve_response_code($response), 'HTTP Error');
        }

        $addons = json_decode(wp_remote_retrieve_body($response));

        // Not clear cases
        if (!$addons || !is_array($addons)) {
            return new \WP_Error('invalid', 'Invalid JSON');
        }
        update_option('newsletter_addons', $addons, false);
        return true;
    }

    static function clear() {
        update_option('newsletter_addons_updated', 0, false);
    }

    static function get_option_array($key) {
        $value = get_option($key, []);
        if (!is_array($value)) {
            return [];
        }
        return $value;
    }

    static function get_addons() {

        $updated = (int) get_option('newsletter_addons_updated');

        if ($updated < time() - DAY_IN_SECONDS*3) {
            self::update(); // This may fail, we use the old values
        }

        return self::get_option_array('newsletter_addons');
    }

    static function update_plugins_transient($value, $license_key) {
        static $extra_response = [];

        if (!$value || !is_object($value)) {
            return $value;
        }

        if (!isset($value->response) || !is_array($value->response)) {
            $value->response = [];
        }

        // Already computed? Use it! (this filter is called many times in a single request)
        if ($extra_response) {
            $value->response = array_merge($value->response, $extra_response);
            return $value;
        }

        $extensions = self::get_addons();

        // Ops...
        if (!$extensions) {
            return $value;
        }

        foreach ($extensions as $extension) {
            unset($value->response[$extension->wp_slug]);
            unset($value->no_update[$extension->wp_slug]);
        }

        // Someone doesn't want our addons updated, let respect it (this constant should be defined in wp-config.php)
        if (!NEWSLETTER_EXTENSION_UPDATE) {
            return $value;
        }

        // @phpstan-ignore-next-line
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        // Ok, that is really bad (should we remove it? is there a minimum WP version?)
        if (!function_exists('get_plugin_data')) {
            return $value;
        }

        // Here we prepare the update information BUT do not add the link to the package which is privided
        // by our Addons Manager (due to WP policies)
        foreach ($extensions as $extension) {

            // Patch for names convention
            $extension->plugin = $extension->wp_slug;

            $plugin_data = false;
            if (file_exists(WP_PLUGIN_DIR . '/' . $extension->plugin)) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $extension->plugin, false, false);
            } else if (file_exists(WPMU_PLUGIN_DIR . '/' . $extension->plugin)) {
                $plugin_data = get_plugin_data(WPMU_PLUGIN_DIR . '/' . $extension->plugin, false, false);
            }

            if (!$plugin_data) {
                continue;
            }

            $plugin = new \stdClass();
            $plugin->id = $extension->id;
            $plugin->slug = $extension->slug;
            $plugin->plugin = $extension->plugin;
            $plugin->new_version = $extension->version;
            $plugin->url = $extension->url;
            if (class_exists('\NewsletterExtensions')) {
                // NO filters here!
                $plugin->package = \NewsletterExtensions::$instance->get_package($extension->id, $license_key);
            } else {
                $plugin->package = '';
            }
//            [banners] => Array
//                        (
//                            [2x] => https://ps.w.org/wp-rss-aggregator/assets/banner-1544x500.png?rev=2040548
//                            [1x] => https://ps.w.org/wp-rss-aggregator/assets/banner-772x250.png?rev=2040548
//                        )
//            [icons] => Array
//                        (
//                            [2x] => https://ps.w.org/advanced-custom-fields/assets/icon-256x256.png?rev=1082746
//                            [1x] => https://ps.w.org/advanced-custom-fields/assets/icon-128x128.png?rev=1082746
//                        )
            if (version_compare($extension->version, $plugin_data['Version']) > 0) {
                $extra_response[$extension->plugin] = $plugin;
            } else {
                $value->no_update[$extension->plugin] = $plugin;
            }
        }

        $value->response = array_merge($value->response, $extra_response);

        return $value;
    }
}
