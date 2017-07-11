<?php

if (!defined('KEYY_DIR')) die('No direct access allowed');

/**
 * This class contains connection, token, sso and logging handling. Loading the file does not instantiate the class, but should be done on startup so that the class can register needed hooks, and be available to other classes. It is a singleton.
 */
class Keyy_Login {

	const META_KEY_PUBLIC_KEY = 'keyy_public_key';
	const META_KEY_SETTINGS = 'keyy_settings';
	const META_KEY_CONNECTION_TOKEN = 'keyy_connection_token';
	const META_KEY_CONNECTION_RESULT = 'keyy_connection_result';
	
	const OPTION_KEY_LOGIN_TOKEN_PREFIX = 'keyy_ltoken_';
	
	const CONNECTION_TOKEN_LIFE_TIME = 300;
	const LOGIN_TOKEN_LIFE_TIME = 300;
	
	private $keyy;
	
	private $login_bypassing_keyy = false;
	
	/* This is only set if we are in the same PHP instance as the login occured in. Usually not useful, as login usually results in a redirect. */
	private $login_used_keyy;
	
	/**
	 * Constructor
	 *
	 * @param Keyy_Login_Plugin $keyy - singleton instance of the parent.
	 */
	public function __construct($keyy) {
		$this->keyy = $keyy;
		add_action('login_enqueue_scripts', array($this, 'login_enqueue_scripts'));
		if (!defined('KEYY_DISABLE') || !KEYY_DISABLE) {
			add_filter('authenticate', array($this, 'authenticate'), 10, 3);
			add_action('wp_login', array($this, 'wp_login'), 10, 2);
			add_action('init', array($this, 'wp_init'));
		}
	}
	
	/**
	 * Runs upon the WordPress init action
	 */
	public function wp_init() {
		if ((empty($this->login_used_keyy) && empty($_COOKIE['keyy_just_logged_in']) || !is_string($_COOKIE['keyy_just_logged_in'])) || !$this->is_sso_enabled()) return;
		
		$token = $this->get_token($_COOKIE['keyy_just_logged_in']);
		
		if (!is_array($token) || empty($token['sso_server'])) return;
		
		$this->set_cookie('keyy_just_logged_in', '0', time()-86400);
		add_action('all_admin_notices', array($this, 'admin_notice_single_sign_on'));
		add_filter('the_content', array($this, 'the_content'));
	}
	
	/**
	 * Gets the HTML for the message inviting the user to activate SSO, and enqueues any other content needed to handle it.
	 *
	 * @return String
	 */
	private function get_sso_message() {
		$this->keyy->enqueue_scripts('sso');
		return $this->keyy->include_template('sso-message.php', true, $this->keyy->get_common_urls());
	}
	
	/**
	 * WordPress filter the_content
	 *
	 * @param String $content - the content of the post
	 */
	public function the_content($content) {
		// Should add something that makes this auto-hide, so that it doesn't permanently obscure the content
		return $this->get_sso_message().$content;
	}
	
	/**
	 * Directly render a notice about single sign-on
	 */
	public function admin_notice_single_sign_on() {
		$this->keyy->show_admin_warning($this->get_sso_message(), 'updated keyy-sso-offer-notice');
	}
	
	/**
	 * Find out whether SSO is enabled or not (as a client)
	 *
	 * @return Boolean
	 */
	public function is_sso_enabled() {
	
		$enabled = defined('KEYY_ALLOW_SSO') ? KEYY_ALLOW_SSO : true;
	
		return apply_filters('keyy_is_sso_enabled', $enabled);
	
	}
	
	/**
	 * Runs upon the WP action wp_login
	 *
	 * @param String  $user_login - the user login
	 * @param WP_User $user		  - the user object
	 */
	public function wp_login($user_login, $user) {
	
		if (!$this->login_bypassing_keyy) return;
	
		error_log("Keyy: login with Keyy disabled via the secret URL");
		
		// Reset the key
		$this->keyy->get_disable_key(true);
	
		$admin_email = get_bloginfo('admin_email');
		
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip_address = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip_address = empty($_SERVER['REMOTE_ADDR']) ? '???' : $_SERVER['REMOTE_ADDR'];
		}
		
		// Email the site admin to notify of the login.
		
		wp_mail($admin_email, home_url().': '.__('secret URL used to login', 'keyy'), sprintf(__('This is a notice for the site administrator of %s.', 'keyy'), home_url()).' '.__('The secret URL, intended for when a Keyy scan is not possible, was used to de-activate Keyy for the login process.', 'keyy').' '.__('The details of the login are below - you should check them in case they are unexpected.', 'keyy').' '.__('You will also need to visit the Keyy page in your WordPress dashboard to obtain the new secret URL, because the old one cannot be used again.', 'keyy')."\n\n".sprintf(__('User logging in: %s (%s)', 'keyy'), $user->user_email, $user_login)."\n".sprintf(__('Logging in from: %s', 'keyy'), strip_tags($ip_address))."\n".sprintf(__('User agent: %s', 'keyy'), strip_tags($_SERVER['HTTP_USER_AGENT'])));

	}
	
	/**
	 * Set a cookie so that, however we logged in, it can be found
	 *
	 * @param String  $name	   - the cookie name
	 * @param String  $value   - the cookie value
	 * @param Integer $expires - when the cookie expires. Defaults to 24 hours' time. Values in the past cause cookie deletion.
	 */
	public function set_cookie($name, $value, $expires = null) {
		if (null === $expires) $expires = time() + 86400;
		$secure = is_ssl();
		$secure_logged_in_cookie = ($secure && 'https' === parse_url(get_option('home'), PHP_URL_SCHEME));
		$secure = apply_filters('secure_auth_cookie', $secure, get_current_user_id());
		$secure_logged_in_cookie = apply_filters('secure_logged_in_cookie', $secure_logged_in_cookie, get_current_user_id(), $secure);
	
		setcookie($name, $value, $expires, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
		setcookie($name, $value, $expires, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
		if (COOKIEPATH != SITECOOKIEPATH) {
			setcookie($name, $value, $expires, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
		}
	}

	/**
	 * Runs upon the WP authenticate action. Can also be called by other internal code.
	 *
	 * @param WP_User|WP_Error|null $user     - filter parameter.
	 * @param String                $login	  - WP login username or email.
	 * @param String                $password - Password.
	 *
	 * @return WP_Error|WP_User
	 */
	public function authenticate($user, $login, $password) {

		if (!empty($_POST['keyy_disable']) && $_POST['keyy_disable'] === $this->keyy->get_disable_key()) {
		
			// At this point, we do not know whether the attempt will succeed or not (e.g. wrong password)
			$this->login_bypassing_keyy = true;
			
			return $user;
		
		}
	
		// Already going to fail?
		if (is_wp_error($user)) return $user;
		
		$orig_user = $user;
	
		// Sanity checks which should always run (partly from wp_authenticate_username_password - these return, so there's no danger of getting messages from both).
		if (empty($login)) {
			if (is_wp_error($user))
				return $user;

			$error = new WP_Error();

			if (empty($login))
				$error->add('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));
				
			if (empty($password))
				$error->add('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));

			return $error;
		}
		
		// This is what retrieve_password in WP does - there is no very fancy email address parsing
		if (strpos($login, '@')) {
			$user = get_user_by('email', $login);
			if (empty($user)) {
				$error = new WP_Error();
				$error->add('invalid_email', __('<strong>ERROR</strong>: There is no user registered with that email address.'));
				return $error;
			}
		} else {
			$user = get_user_by('login', $login);
		}

		if (!$user) {
			return new WP_Error( 'invalid_username',
				__('<strong>ERROR</strong>: Invalid username.') .
				' <a href="' . wp_lostpassword_url() . '">' .
				__('Lost your password?') .
				'</a>'
			);
		}
		
		if (!$this->is_configured_for_user($user->ID)) return $orig_user;
		
		// An array; keys are 'keyy' and 'password' (values: required|sufficient|ignored).
		$user_login_policy = $this->get_user_login_policy($user->ID);

		// Unexpected in all scenarios; just hand control back to WP.
		if (!is_array($user_login_policy) || empty($user_login_policy)) return $orig_user;

		// Hand back control to WP.
		if ('ignored' == $user_login_policy['keyy']) return $orig_user;
		
		// At this stage, we know that Keyy is either required or sufficient, so, we will run the check.
		$keyy_authenticated = false;
		// This can still be either required, sufficient or ignored at this stage.
		$password_authenticated = false;
		
		// Unconditionally remove WP's default filters - make all the decisions ourselves. We used to do this conditionally, but it missed some cases.
		// if ('ignored' == $user_login_policy['password'] || 'sufficient' == $user_login_policy['keyy']) {
		remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
		remove_filter('authenticate', 'wp_authenticate_email_password', 20, 3);

		// Any token in the form?
		if (!empty($_POST['keyy_token_id'])) {
			$keyy_authenticated = $this->verify_token_for_user_login($user->ID, $_POST['keyy_token_id']);
		} elseif ('required' == $user_login_policy['keyy']) {
			return new WP_Error('missing_keyy_code', apply_filters('keyy_missing_keyy_code_text', __('<strong>ERROR</strong>: This user has set up their account to require a successful scan with Keyy, which was not done.', 'keyy')));
		}

		if ($keyy_authenticated) {
			$this->login_used_keyy = true;
			$this->set_cookie('keyy_just_logged_in', $_POST['keyy_token_id'], time()+60);
		}
		
		if ($keyy_authenticated && 'sufficient' == $user_login_policy['keyy']) return $user;
		
		if (!$keyy_authenticated && 'required' == $user_login_policy['keyy']) {
			return new WP_Error('invalid_keyy_code', __('<strong>ERROR</strong>: The Keyy scan did not succeed. Please try again.', 'keyy'));
		}
		
		/*
		At this point, the Keyy situation can be:
		- Sufficient but failed to authenticated - hence, hand over completely to password
		- Required and authenticated - again, the result is entirely determined by the password result
		*/

		if ('ignored' == $user_login_policy['password'] && !$keyy_authenticated) return new WP_Error('no_credentials', __('No valid login credentials were provided. Please try again.', 'keyy'));
		
		if ('ignored' == $user_login_policy['password']) return $user;
		
		// At this point, we know that a password is either sufficient or required.
		/**
		* Filter whether the given user can be authenticated with the provided $password.
		*
		* @since 2.5.0
		*
		* @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous
		*                                   callback failed authentication.
		* @param string           $password Password to check against the user.
		*/
		$user = apply_filters('wp_authenticate_user', $user, $password);
		if (is_wp_error($user))
			return $user;

		if (!wp_check_password($password, $user->user_pass, $user->ID)) {
			$password_authenticated = false;
		
			if ('required' == $user_login_policy['password']) {
				return new WP_Error( 'incorrect_password',
					sprintf(
						/* translators: %s: user name */
						__('<strong>ERROR</strong>: The password you entered for the username %s is incorrect.'),
						'<strong>' . $login . '</strong>'
					) .
					' <a href="' . wp_lostpassword_url() . '">' .
					__('Lost your password?') .
					'</a>'
				);
			}
		} else {
			$password_authenticated = true;
		}
		
		// Password was either sufficient or required, and Keyy is happy if password is - so, proceed.
		return $user;
		
	}
	
	/**
	 * Runs upon the WP login_enqueue_scripts action
	 */
	public function login_enqueue_scripts() {

		if (isset($_GET['action']) && 'logout' != $_GET['action'] && 'login' != $_GET['action']) return;
		
		$this->keyy->enqueue_scripts('login');
		
	}
	
	/**
	 * Find out what is required for this user's login to be allowed to proceed, in terms of credentials.
	 *
	 * @param Integer $user_id - the WordPress user to search for.
	 *
	 * @return Array|WP_Error - An array with keys 'keyy' and 'password', with values as described below.
	 */
	public function get_user_login_policy($user_id) {
	
		if (!$user_id) return new WP_Error('invalid_user_id', 'An invalid user ID was passed to user_login_requires()', $user_id);
		
		// Possible values: required, sufficient, ignored
		// Not all combinations make sense, and hence not all are supported.
		$policy = array(
			'keyy' => 'required',
			// 'ignored' will cause processing to be aborted and default WP handling to continue
			'password' => 'ignored',
		);
		
		return apply_filters('keyy_user_login_policy', $policy, $user_id);
	
	}
	
	/**
	 * See if the supplied token is signed for login for the user
	 *
	 * @param Integer $user_id  - the WordPress user ID.
	 * @param String  $token_id - the token identifier.
	 *
	 * @return Boolean
	 */
	public function verify_token_for_user_login($user_id, $token_id) {

		static $known_successes = array();
		
		if (!empty($known_successes[$user_id.'-'.$token_id])) return true;
	
		if (!is_string($token_id) || '' == $token_id) return false;
		
		$token = $this->verify_login_token($token_id);

		if (!is_array($token)) return false;
		
		if ($user_id == $token['user_id'] && 'claimed' == $token['state']) {
			$token['state'] = 'used';
			update_option(self::OPTION_KEY_LOGIN_TOKEN_PREFIX.$token_id, $token, false);
			
			// Purge old tokens.
			$this->prune_expired_login_tokens();
			
			$known_successes[$user_id.'-'.$token_id] = true;
			
			return true;
		}

		return false;
	}
	
	/**
	 * Parse a URL, returning data in a format very similar to parse_url (which it uses)
	 *
	 * Static, to allow its re-use in testing scripts
	 *
	 * @param String $url - the URL.
	 *
	 * @return Array an associate array, with entries like from http://php.net/manual/en/function.parse-url.php; but also with keys 'user_login' (the WP user login), 'token', 'context' and 'rest_url'. The REST URL is the endpoint for the site (i.e. stops at /wp-json/).
	 */
	 public static function parse_url($url) {
	 
		$parsed = parse_url(rtrim($url));
		
		// We change the key name to try to prevent confusion.
		$parsed['user_login'] = isset($parsed['user']) ? $parsed['user'] : '';
		
		unset($parsed['user']);
		
		if (!isset($parsed['query'])) {
			$parsed['token'] = '';
			$parsed['context'] = '';
		} else {
			parse_str($parsed['query'], $query_results);

			$parsed['context'] = isset($query_results['context']) ? $query_results['context'] : '';
			$parsed['token'] = isset($query_results['token']) ? $query_results['token'] : '';
			$parsed['form_id'] = isset($query_results['form_id']) ? $query_results['form_id'] : '';
			
			unset($parsed['query']);
		}
		
		$parsed['rest_url'] = $parsed['scheme'].'://'.$parsed['host'];
		if (isset($parsed['path'])) $parsed['rest_url'] .= $parsed['path'];
		$parsed['rest_url'] = rtrim($parsed['rest_url'], '/').'/wp-json/';
		
		return $parsed;
	 
	 }
	
	/**
	 * Check whether Keyy is configured for the currently logged in, or indicated, user
	 *
	 * @param Integer $user_id     - the WordPress user to search for, or the currently logged in one if not specified.
	 * @param Boolean $clear_cache - whether to first clear the cache.
	 *
	 * @return Boolean
	 */
	public function is_configured_for_user($user_id = false, $clear_cache = false) {

		if (false == $user_id && function_exists('is_user_logged_in') && is_user_logged_in()) $user_id = get_current_user_id();
		
		$public_key = $user_id ? $this->get_public_key($user_id, $clear_cache) : false;
		
		return empty($public_key) ? false : true;
	}

	/**
	 * Get the configured Keyy settings (if any) for the indicated or currently logged-in user
	 *
	 *  @param Integer $user_id - the WordPress user to search for, or the currently logged in one if not specified.
	 *
	 * @return Array - an array, possibly empty if no settings exist
	 */
	public function get_user_settings($user_id = false) {
	
		$settings = array();
	
		if (false === $user_id && is_user_logged_in()) $user_id = get_current_user_id();
	
		$settings = $user_id ? get_user_meta($user_id, self::META_KEY_SETTINGS, true) : array();
		
		if (!is_array($settings)) $settings = array();
		
		return apply_filters('keyy_get_user_settings', $settings, $user_id);
		
	}
	
	/**
	 * Get the configured Keyy settings (if any) for the indicated or currently logged-in user
	 *
	 *  @param Integer $user_id  - the WordPress user to search for, or the currently logged in one if not specified.
	 *  @param Array   $settings - settings to be saved.
	 *
	 * @return Integer|Boolean - as for update_user_meta() ( https://codex.wordpress.org/Function_Reference/update_user_meta )
	 */
	public function set_user_settings($user_id = false, $settings) {
	
		if (false === $user_id && is_user_logged_in()) $user_id = get_current_user_id();
	
		if (!$user_id || !is_array($settings)) return false;
	
		return update_user_meta($user_id, self::META_KEY_SETTINGS, $settings);
		
	}
	
	/**
	 * Update one particular setting
	 *
	 *  @param Integer $user_id - the WordPress user to search for, or the currently logged in one if not specified.
	 * @param String  $key     - the setting to update.
	 * @param Mixed   $value   - the new value. If null, then the setting will be removed.
	 *
	 * @return Integer|Boolean - as for update_user_meta() ( https://codex.wordpress.org/Function_Reference/update_user_meta )
	 */
	public function set_user_setting($user_id, $key, $value) {
		$settings = $this->get_user_settings($user_id);
		if (null === $value) {
			unset($settings[$key]);
		} else {
			$settings[$key] = $value;
		}
		return $this->set_user_settings($user_id, $settings);
	}
	
	/**
	 * Deletes the specified user's connection token. This would usually be done after a successful connection.
	 *
	 * @param Integer|Boolean $user_id - the WordPress ID of the user, or false to use the currently logged-in user.
	 *
	 * @return WP_Error|Boolean - success state passed back from delete_user_meta (unless a WP_Error is returned before getting that far)
	 */
	public function delete_connection_token($user_id) {
		if (false == $user_id && is_user_logged_in()) $user_id = get_current_user_id();
		
		if (!$user_id) return new WP_Error('invalid_user_id', 'An invalid user ID was passed to delete_connection_token()', $user_id);
		
		return delete_user_meta($user_id, self::META_KEY_CONNECTION_TOKEN);
	}
	
	/**
	 * Get a connection token plus an expiry time after which a new one is needed
	 *
	 * @param Integer|Boolean $user_id       		 - the WordPress ID of the user, or false to use the currently logged-in user.
	 * @param Boolean         $force_refresh 		 - set this to get a new token even if the current one has not expired (can be useful for upcoming expiry). Note that it will be ignored if the current token was created in the last 5 seconds.
	 * @param Integer|Boolean $expire_after_at_least - token must not expire before this amount of time has passed. Otherwise, self::CONNECTION_TOKEN_LIFE_TIME is used. This parameter is intended for non-instant methods of receiving the connection token (e.g. via email).
	 *
	 * @return Array - with keys 'value', 'created_at' and 'expiry_time'
	 */
	public function get_connection_token($user_id = false, $force_refresh = false, $expire_after_at_least = false) {
	
		if (false == $user_id && is_user_logged_in()) $user_id = get_current_user_id();
		
		if (!$user_id) return array('error' => 'not_logged_in');
	
		$connection_token = get_user_meta($user_id, self::META_KEY_CONNECTION_TOKEN, true);

		$refresh_if_expires_before = (false === $expire_after_at_least) ? time() : time() + $expire_after_at_least;
		
		$token_created_at = (is_array($connection_token) && !empty($connection_token['created_at'])) ? $connection_token['created_at'] : 0;
		
		$time_now = time();
		
		if (($force_refresh && $time_now - $token_created_at >= 5) || !is_array($connection_token) || !isset($connection_token['value']) || !isset($connection_token['expiry_time']) || $connection_token['expiry_time'] <= $refresh_if_expires_before) {
		
			$expiry_interval = (false === $expire_after_at_least) ? self::CONNECTION_TOKEN_LIFE_TIME : $expire_after_at_least;
		
			// Deterministic after the first token, given the time and the user_id, to try to reduce race conditions on the force_refresh generation of subsequent tokens if the user has multiple 'connect' tokens being displayed. If an attacker knew all of the values to calculate it, then that would mean that the install's login security is already completely broken.
			$token_value = hash('sha256', wp_hash($time_now.$user_id, 'nonce').wp_hash($time_now, 'secure_auth').wp_hash($time_now.$user_id, 'logged_in').DB_PASSWORD.(isset($connection_token['value']) ? $connection_token['value'] : rand(0, 9999999).microtime(true)).$user_id);
		
			// Get a new one.
			$connection_token = array(
				'value' => $token_value,
				'expiry_time' => $time_now + $expiry_interval,
				'created_at' => $time_now
			);

			update_user_meta($user_id, self::META_KEY_CONNECTION_TOKEN, $connection_token);
		}
		
		return $connection_token;
	
	}
	
	/**
	 * Verify the validity of a sent login token (that it exists, and is not expired)
	 *
	 * @param String  $token_id    - the value.
	 * @param Boolean $clear_cache - Whether to first wipe the WP cache.
	 *
	 * @return Boolean|Array - if the token is not valid, then returns false; otherwise returns the token
	 */
	public function verify_login_token($token_id, $clear_cache = false) {

		$token = $this->get_token($token_id, $clear_cache);
	
		if (!is_array($token) || !isset($token['state'])) return false;

		// Expired ?
		$options_table_key = self::OPTION_KEY_LOGIN_TOKEN_PREFIX.$token_id;
		
		$expires_at = get_option($options_table_key.'_exp');
		
		if (!is_numeric($expires_at) || $expires_at < time()) return false;

		return $token;
	
	}
	
	/**
	 * Gets a specified token. Not intended for external use. This does not verify its expiry state - the caller should continue to do so if needed.
	 *
	 * @param String  $token_id    - the token identifier.
	 * @param Boolean $clear_cache - Whether to first wipe the WP cache.
	 *
	 * @return Array|Boolean - the token, or false if there is no token
	 */
	private function get_token($token_id, $clear_cache = false) {
	
		if (!is_string($token_id) || strlen($token_id) > 40 || strlen($token_id) < 20) return false;
	
		$options_table_key = self::OPTION_KEY_LOGIN_TOKEN_PREFIX.$token_id;
	
		if ($clear_cache) wp_cache_delete($options_table_key, 'options');
	
		$token = get_option($options_table_key);

		if (!is_array($token) || !isset($token['state'])) return false;
		
		return $token;
		
	}
	
	/**
	 * Indicate that the specified token has been signed by the indicated user
	 *
	 * @param Integer $user     - a WordPress user object for the user who has signed the token.
	 * @param String  $token_id - the token identifier.
	 * @param String  $status   - the new status (valid: 'unused', 'claimed', 'used').
	 * @param Array   $extra	- any additional pieces of data to store; e.g. (int)form_id, (string)sso_server
	 *
	 * @return Boolean|WP_Error - true for success, otherwise an error object
	 */
	public function set_token_result($user, $token_id, $status, $extra = array()) {
	
		$token = $this->verify_login_token($token_id);
		
		if (false == $token) return new WP_Error('invalid_token', __('The login token was not valid. It may have expired; please try again.', 'keyy'));
		
		if (!isset($token['state']) || 'unused' !== $token['state']) return new WP_Error('invalid_token', __('The login token was not valid (already used). Please try again.', 'keyy'));
		
		$token['state'] = 'claimed';
		$token['user_id'] = $user->ID;
		$token['user_login'] = $user->user_login;
		foreach ($extra as $key => $value) {
			// For now, we restrict these, for maximum security (and especially to forbid over-writing values with special meanings set above)
			if ('form_id' != $key && 'sso_server' != $key) continue;
			$token[$key] = $value;
		}
	
		// Do not autoload - prevents being cached.
		update_option(self::OPTION_KEY_LOGIN_TOKEN_PREFIX.$token_id, $token, false);
		
		return true;
		
	}
	
	/**
	 * Get a login token plus an expiry time after which a new one is needed.
	 * Unlike connection tokens, there can be many login tokens; one is made available for each form.
	 *
	 * @param Array $params - If the optional key user_login is present, then the (as-yet unauthenticated) token will be 'locked' to this username; i.e. only the indicated user will be allowed to use it.
	 *
	 * @return Array - with keys 'value', 'state' and 'expiry_time'
	 */
	public function get_fresh_login_token($params = array()) {
	
		$key_available = false;
		
		while (!$key_available) {
	
			// Maximum length of an option_name is 64.
			$random_id = substr(hash('sha256', SECURE_AUTH_SALT.microtime(true).rand(0, 9999).SECURE_AUTH_KEY), 0, 40);
		
			$options_table_key = self::OPTION_KEY_LOGIN_TOKEN_PREFIX.$random_id;
			
			if (false === get_option($options_table_key)) $key_available = true;
			
		}
	
		$token = array('state' => 'unused');
		
		// Did the caller pre-indicate the username that the token is for?
		if (!empty($params['user_login']) && is_string($params['user_login'])) $token['user_login'] = $params['user_login'];
	
		$expiry_time = (time() + self::LOGIN_TOKEN_LIFE_TIME);
		
		update_option($options_table_key, $token, false);
		// The expiry time is made a separate option for easy searching and deleting within a single MySQL query (see prune_expired_login_tokens). i.e. Not serialised within the same option.
		update_option($options_table_key.'_exp', $expiry_time);
		
		return array(
			'value' => $random_id,
			'state' => $token['state'],
			'expiry_time' => $expiry_time
		);
	
	}
	
	/**
	 * Purge the database of expired login tokens.
	 * Best time to run this is after a successful login.
	 */
	public function prune_expired_login_tokens() {
		
		$wpdb = $GLOBALS['wpdb'];
		
		$prefix = self::OPTION_KEY_LOGIN_TOKEN_PREFIX;
		
		$sql = "
				DELETE
					a, b
				FROM
					{$wpdb->options} a, {$wpdb->options} b
				WHERE
					a.option_name LIKE '$prefix%' AND
					a.option_name NOT LIKE '$prefix%_exp' AND
					b.option_name = CONCAT(
						a.option_name,
						'_exp'
					)
				AND b.option_value < UNIX_TIMESTAMP()
			";

		$wpdb->query($sql);
		
	}
	
	/**
	 * Disconnect a user
	 *
	 * @param Integer|Boolean $user_id - the WordPress ID of the user, or false to use the currently logged-in user.
	 */
	public function disconnect($user_id = false) {
	
		if (false == $user_id && function_exists('is_user_logged_in') && is_user_logged_in()) $user_id = get_current_user_id();
		
		if (!$user_id) return array('error' => 'not_logged_in');
		
		$this->set_public_key($user_id, null);
		$this->set_user_setting($user_id, 'email', null);
	
		return array('result' => true);
	
	}
	
	/**
	 * Stores (in the database) the public key for the indicated user
	 *
	 * @param  Integer $user_id    The WordPress user ID.
	 * @param  String  $public_key The public key.
	 * @return Integer|Boolean     As for update_user_meta() ( https://codex.wordpress.org/Function_Reference/update_user_meta )
	 */
	public function set_public_key($user_id, $public_key) {
	
		return update_user_meta($user_id, self::META_KEY_PUBLIC_KEY, $public_key);
	
	}
	
	/**
	 * Stores (in the database) the result of a connection attempt for the indicated user.
	 *
	 * @param  Integer $user_id                The WordPress user ID.
	 * @param  String  $connection_result_code The result code.
	 * @param  array   $connection_result_data Any associated data.
	 * @return Integer|Boolean - as for update_user_meta() ( https://codex.wordpress.org/Function_Reference/update_user_meta )
	 */
	public function set_connection_result($user_id, $connection_result_code, $connection_result_data = array()) {
	
		$connection_result = array(
			'code' => $connection_result_code,
			'data' => $connection_result_data,
			'time' => time()
		);
	
		return update_user_meta($user_id, self::META_KEY_CONNECTION_RESULT, $connection_result);
	
	}
	
	/**
	 * Returns the result of the most recent connection attempt for the indicated user
	 *
	 * N.B. Do not use this to detect whether the user is connected or not, as this can be affected by a third-party sending junk data. Use get_public_key for that - the stored public key can only be affected by properly authenticated users.
	 *
	 * @param Integer|Boolean $user_id     - The WordPress user ID; defaults to the currently logged in user.
	 * @param Boolean         $clear_cache - Whether to first wipe the WP cache.
	 *
	 * @return Array - with keys code, data, time, or an empty one if there has not been one (or the data was invalid/corrupt)
	 */
	public function get_connection_result($user_id = false, $clear_cache = false) {
	
		if (false == $user_id && is_user_logged_in()) $user_id = get_current_user_id();
		
		if (!$user_id) return array();
	
		if ($clear_cache) wp_cache_delete($user_id, 'user_meta');
	
		$connection_result = get_user_meta($user_id, self::META_KEY_CONNECTION_RESULT, true);
	
		if (!is_array($connection_result) || !isset($connection_result['code'])) $connection_result = array();
		
		$connection_result['connected'] = $this->is_configured_for_user();
		
		return $connection_result;
	
	}

	/**
	 * Returns the key used in the usermeta table for the public key
	 *
	 * @return String - the key
	 */
	public function get_meta_key_public_key() {
		return self::META_KEY_PUBLIC_KEY;
	}
	
	/**
	 * See if everybody is using Keyy
	 *
	 * The accurate query may be slow on a large site. So, it is best to check first whether it should be used.
	 *
	 * @param Boolean $accurate - whether we need an-always accurate answer, or usually correct one
	 *
	 * @return Boolean
	 */
	public function all_users_are_using_keyy($accurate = false) {
		
		if ($accurate) {
			
			$users = new WP_User_Query(array(
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key' => self::META_KEY_PUBLIC_KEY,
						'compare' => 'NOT EXISTS'
					),
					array(
						'key' => self::META_KEY_PUBLIC_KEY,
						'value' => '',
						'compare' => '='
					),
				),
				'number' => 1,
			));
			
			return ($users->get_total() > 0) ? false : true;
		
		}
		
		$how_many_connected_users = $this->get_how_many_connected_users();
		
		$how_many_users = $this->get_how_many_users();
		
		return ($how_many_users > $how_many_connected_users) ? false : true;
		
	}
	
	/**
	 * Get a user count
	 */
	public function get_how_many_users() {
		$wpdb = $GLOBALS['wpdb'];
		return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
	}
	
	/**
	 * Get a total of how many users are connected to Keyy
	 *
	 * @return Integer
	 */
	public function get_how_many_connected_users() {
		$wpdb = $GLOBALS['wpdb'];
		$how_many_connected_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '".self::META_KEY_PUBLIC_KEY."' AND meta_value != ''");
		return $how_many_connected_users;
	}
	
	/**
	 * Retrieves (from the database) the public key for the indicated user.
	 *
	 * @param Integer $user_id 	   The WordPress user ID.
	 * @param Boolean $clear_cache Whether to clear WP's cache first.
	 *
	 * @return String|Boolean - The public key, or false for an error
	 */
	public function get_public_key($user_id, $clear_cache = false) {
	
		if ($clear_cache) wp_cache_delete($user_id, 'user_meta');
	
		// N.B. This call to get_user_meta returns an empty string for unset keys.
		$public_key = get_user_meta($user_id, self::META_KEY_PUBLIC_KEY, true);
		
		if ('' === $public_key || !is_string($public_key)) return false;
		
		return $public_key;
	}
}
