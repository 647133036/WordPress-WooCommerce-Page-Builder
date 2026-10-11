<?php

namespace Newsletter;

defined('ABSPATH') || exit;

class News {

    static function update() {

        // Anyway we set the news as updated
        update_option('newsletter_news_updated', time(), false);

        // HTTP is ok for this data
        if (NEWSLETTER_DEBUG) {
            $url = "http://www.thenewsletterplugin.com/wp-content/news-test.json?ver=" . NEWSLETTER_VERSION;
        } else {
            $url = "http://www.thenewsletterplugin.com/wp-content/news.json?ver=" . NEWSLETTER_VERSION;
        }
        $response = wp_remote_get($url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            update_option('newsletter_news', [], false);
            return;
        }

        $news = json_decode(wp_remote_retrieve_body($response), true);

        // Firewall returns an invalid response
        if (!$news || !is_array($news)) {
            $news = [];
        }

        update_option('newsletter_news', $news, false);
    }

    static function clear() {
        update_option('newsletter_news_updated', 0, false);
    }

    static function get_option_array($key) {
        $value = get_option($key, []);
        if (!is_array($value)) {
            return [];
        }
        return $value;
    }

    /**
     * Updates made only when used, if no one is accessing the admin pages
     * there is no need to update the news.
     *
     * @return array
     */
    static function get_news() {
        $updated = (int) get_option('newsletter_news_updated');

        if ($updated < time() - DAY_IN_SECONDS) {
            self::update();
        }

        $news = self::get_option_array('newsletter_news');

        $news_dismissed = self::get_option_array('newsletter_news_dismissed');
        $today = date('Y-m-d');
        $list = [];
        foreach ($news as $n) {
            if (!NEWSLETTER_DEBUG) {
                if ($today < $n['start'] || $today > $n['end']) {
                    continue;
                }
            }
            if (in_array($n['id'], $news_dismissed)) {
                continue;
            }
            $list[] = $n;
        }
        return $list;
    }

    static function dismiss($id) {
        $dismissed = self::get_option_array('newsletter_news_dismissed');
        $dismissed[] = sanitize_key($id);
        update_option('newsletter_news_dismissed', $dismissed, false);
    }
}
