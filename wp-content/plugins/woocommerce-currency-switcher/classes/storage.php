<?php

if (!defined('ABSPATH')) {
	die('No direct access allowed');
}

// keeps current user data
final class WOOCS_STORAGE {

	public $type = 'session'; // session, transient, woo_session, cookie
	private $user_ip = null;
	private $transient_key = null;
	private $woocs_session = null;

	public function __construct($type = '') {
		if (!empty($type)) {
			$this->type = $type;
		}
		if ($this->type == 'woo_session') {
			$this->type = 'session';
		}
		if ($this->type == 'session') {
			if (!session_id() && !defined('REST_REQUEST')) {
				@session_start();
			}
		}

		if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
			$ip = $_SERVER['HTTP_X_REAL_IP'];
			if ($splitPos = strpos($ip, ',')) {
				$ip = substr($ip, 0, $splitPos);
			}
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			if ($splitPos = strpos($ip, ',')) {
				$ip = substr($ip, 0, $splitPos);
			}
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		} else {
			$ip = '';
		}
		$this->user_ip = filter_var($ip, FILTER_VALIDATE_IP);

		//+++
		// Cloudflare sets CF-Connecting-IP itself and overwrites any client supplied value,
		// so behind Cloudflare it is more reliable than X-Real-IP / X-Forwarded-For.
		if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			$cf_ip = filter_var(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']), FILTER_VALIDATE_IP);
			if ($cf_ip) {
				$this->user_ip = $cf_ip;
			}
		}

		$this->transient_key = substr(md5($this->user_ip), 7, 23);

		// Browser key mode: the browser supplies its own unique key from localStorage,
		// so visitors behind a shared IP (office NAT, mobile carriers) do not share
		// the same storage bucket. Enabled ONLY for Transient storage + cached shop.
		if ($this->type === 'transient' && get_option('woocs_shop_is_cached', 0)) {

			$sk = '';

			// jQuery requests carry it as a parameter, fetch() based ones as a header.
			if (!empty($_REQUEST['woocs_sk'])) {
				$sk = wp_unslash($_REQUEST['woocs_sk']);
			} elseif (!empty($_SERVER['HTTP_X_WOOCS_SK'])) {
				$sk = wp_unslash($_SERVER['HTTP_X_WOOCS_SK']);
			}

			// One sanitizer for both sources, otherwise the same key yields two buckets.
			$sk = substr(sanitize_key($sk), 0, 32);

			if (strlen($sk) >= 8) {
				$this->transient_key = substr(md5('woocs_sk_' . $sk), 7, 23);
			}
		}


		//+++

		if ($this->type == 'woocs_session') {
			$this->woocs_session = new WOOCS_SESSION();
			$this->woocs_session->init();
		}
	}

	public function set_val($key, $value) {

		$value = sanitize_text_field(esc_html($value));
		// ***

		switch ($this->type) {

			case 'woo_session':
				if (WC()->session) {
					$cookie = WC()->session->get_session_cookie();
					if (!$cookie) {
						WC()->session->set_customer_session_cookie(true);
					}

					WC()->session->set($key, $value);
				}

				break;
			case 'woocs_session':
				if ($this->woocs_session) {
					$this->woocs_session->set($key, $value);
				}

				break;
			case 'session':
				$_SESSION[$key] = $value;
				break;
			case 'memcached':
				if (class_exists('Memcached')) {
					$mem = new Memcached();
					$mem->addServer(WOOCS_MEMCACHED_SERVER, WOOCS_MEMCACHED_PORT);
					$mem->set($this->transient_key . '_' . $key, $value);
				}
				break;
			case 'redis':
				if (class_exists('Redis')) {
					$red = new Redis();
					try {
						@$red->connect(WOOCS_REDIS_SERVER, WOOCS_REDIS_PORT);
						$red->set($this->transient_key . '_' . $key, $value);
					} catch (RedisException $e) {
						// ***
					}
				}
				break;
			case 'transient':
				$data = get_transient($this->transient_key);
				if (!is_array($data)) {
					$data = array();
				}
				$data[$key] = $value;

				// 1 day by default. Longer only in browser key mode, where the key is stable per browser.
				$woocs_ttl = ( $this->type === 'transient' && get_option('woocs_shop_is_cached', 0) ) ? 30 * 24 * 3600 : 1 * 24 * 3600;
				set_transient($this->transient_key, $data, $woocs_ttl);

				break;
			case 'cookie':
				setcookie($key, $value, time() + 1 * 24 * 3600); // 1 day
				// wc_setcookie( $key, $value, time() + 1 * 24 * 3600 );
				break;

			default:
				break;
		}
	}

	public function get_val($key) {
		$value = null;

		switch ($this->type) {
			case 'woo_session':
				if (WC()->session) {
					$value = WC()->session->get($key);
				}

				break;
			case 'woocs_session':
				if ($this->woocs_session && $this->is_isset($key)) {
					$value = $this->woocs_session->get($key);
				}

				break;
			case 'session':
				if ($this->is_isset($key)) {
					$value = $_SESSION[$key];
				}
				break;
			case 'memcached':
				if ($this->is_isset($key) and class_exists('Memcached')) {
					$mem = new Memcached();
					$mem->addServer(WOOCS_MEMCACHED_SERVER, WOOCS_MEMCACHED_PORT);
					$value = $mem->get($this->transient_key . '_' . $key);
				}
				break;
			case 'redis':
				if ($this->is_isset($key) and class_exists('Redis')) {
					$red = new Redis();
					try {
						@$red->connect(WOOCS_REDIS_SERVER, WOOCS_REDIS_PORT);
						$value = $red->get($this->transient_key . '_' . $key);
					} catch (RedisException $e) {
						// ***
					}
				}
				break;
			case 'transient':
				$data = get_transient($this->transient_key);
				if (!is_array($data)) {
					$data = array();
				}
				if (isset($data[$key])) {
					$value = $data[$key];
				}
				break;
			case 'cookie':
				if ($this->is_isset($key)) {
					$value = $_COOKIE[$key];
				}
				break;

			default:
				break;
		}

		return sanitize_text_field(esc_html($value));
	}

	public function is_isset($key) {
		$isset = false;
		switch ($this->type) {
			case 'woo_session':
				if (WC()->session) {
					$isset = (bool) WC()->session->get($key);
				}
				break;
			case 'woocs_session':
				if ($this->woocs_session) {
					$isset = (bool) $this->woocs_session->get($key);
				}

				break;
			case 'session':
				$isset = isset($_SESSION[$key]);
				break;
			case 'memcached':
				$isset = false;
				if (class_exists('Memcached')) {
					$mem = new Memcached();
					$mem->addServer(WOOCS_MEMCACHED_SERVER, WOOCS_MEMCACHED_PORT);
					$isset = boolval($mem->get($this->transient_key . '_' . $key) !== false && $mem->getResultCode() !== Memcached::RES_NOTFOUND);
				}
				break;
			case 'redis':
				$isset = false;
				if (class_exists('Redis')) {
					$red = new Redis();
					try {
						@$red->connect(WOOCS_REDIS_SERVER, WOOCS_REDIS_PORT);
						$isset = boolval($red->exists($this->transient_key . '_' . $key));
					} catch (RedisException $e) {
						// ***
					}
				}
				break;
			case 'transient':
				$isset = (bool) $this->get_val($key);
				break;
			case 'cookie':
				$isset = isset($_COOKIE[$key]);
				break;
		}

		return $isset;
	}
}
