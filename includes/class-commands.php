<?php

if (!defined('KEYY_DIR')) die('No direct access allowed');

/**
 * All commands that are intended to be available for calling from any sort of control interface (e.g. wp-admin, UpdraftCentral) go in here.
 * All public methods should either return the data to be returned, or a WP_Error with associated error code, message and error data.
 */
class Keyy_Commands {

	private $keyy;

	private $keyy_login;
	
	/**
	 * A list of actions which non-admins can perform. All other actions default to requiring admin privileges.
	 *
	 * @var array
	 */
	private $user_actions = array('ping', 'get_version', 'get_connection_token', 'save_settings', 'get_fresh_login_token', 'get_dashboard_page', 'get_disconnection_result', 'get_connection_result', 'get_token_state', 'disconnect', 'login', 'connect', 'logout');

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->keyy = Keyy_Login_Plugin();
		$this->keyy_login = $this->keyy->login_instance();
	}
	
	/**
	 * Determine whether a particular command is available to all users, or admins only
	 *
	 * @param  String $command - the method name in this class that the consumer wants to call.
	 * @return String - either 'user' or 'admin'
	 */
	public function get_privilege_level_required($command) {
		return in_array($command, $this->user_actions) ? 'user' : 'admin';
	}
	
	/**
	 * Send emails with connection codes to users who are not yet connected
	 *
	 * @param Array $params - command parameters. Relevant keys:
	 * - 'role' (string), indicating which roles unconnected users are to be enumerated in. Can be 'all' or (on a multisite) 'super-admin'
	 * - 'page' (integer) - which page of users to do this for (from 1)
	 *
	 * @return Array. Keys - message (string), more (boolean)
	 */
	public function user_send_unconnected_user_codes($params) {
		return apply_filters('keyy_command_user_send_unconnected_user_codes', array(), $params);
	}
	
	/**
	 * Get the HTML fragment for the site admin dashboard page with information on a particular user
	 *
	 * @param Array $params - relevant keys are user_id, referring to a WordPress user ID, and include_actions (defaults to false).
	 * @return Array - the value for the key 'html' contains the resulting HTML
	 */
	public function get_user_settings_html($params) {
	
		return apply_filters('keyy_command_get_user_settings_html', array('html' => ''), $params);
	
	}
	
	/**
	 * A helper function.
	 *
	 * @param  Array $params - the data sent from Select2 indicating the terms of the user search.
	 * @return Array - a list of results, in the format desired by Select2
	 */
	public function select2_choose_user($params) {

		if (empty($params['q']) || !is_string($params['q'])) return array();

		$args = array(
			'search' => '*'.$params['q'].'*',
			'fields' => array('ID', 'user_login', 'user_email', 'user_nicename'),
			'search_columns' => array('user_login', 'user_email')
		);

		$res = array();

		$user_query = new WP_User_Query($args);

		if (! empty($user_query->results)) {
			foreach ($user_query->results as $user) {
				$res[] = array(
					'id' => $user->ID,
					'text' => sprintf("%s - %s (%s)", $user->user_nicename, $user->user_login, $user->user_email),
				);
			}
		}

		$results = array(
			'results' => $res
		);

		return $results;
	}
	
	/**
	 * Get the installed version of Keyy. Its main use over get_version is that the REST route will require authorisation for ping, so it can be used to distinguish different communication problems and test authorisation.
	 *
	 * @return Array - An array containing the version
	 */
	public function ping() {
		return $this->get_version();
	}

	/**
	 * Get the installed version of Keyy
	 *
	 * @return Array - An array containing the version
	 */
	public function get_version() {
	
		$keyy = $this->keyy;
		
		return array(
			'plugin_version' => $keyy::VERSION,
			'app_compat_version' => $keyy::APP_COMPAT_VERSION
		);
	}
	
	/**
	 * Get a current connection token
	 *
	 * @param  Array $params - set the force_refresh value to force a refresh.
	 * @return Array - The token
	 */
	public function get_connection_token($params = array()) {
	
		$force_refresh = empty($params['force_refresh']) ? false : true;
		
		$connection_token = $this->keyy_login->get_connection_token(false, $force_refresh);
		
		$seconds_until_expiry = ($connection_token['expiry_time'] - time());
		
		$connection_token['expires_after'] = $seconds_until_expiry;
		
		return $connection_token;
	}
		
	/**
	 * Get a random string
	 *
	 * @param Integer $length - how many characters long
	 *
	 * @return String - the random string
	 */
	private function get_random_string($length) {
		$dictionary = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$dictionary_length = strlen($dictionary);
		$output = '';
		for ($i = 0; $i < $length; $i++) {
			$output .= $dictionary[rand(0, $dictionary_length-1)];
		}
		return $output;
	}
	
	/**
	 * Save settings
	 *
	 * @param  Array $data - keys are data (the settings to be saved, serialized) and type, the type of settings being saved.
	 * @return Array The response
	 */
	public function save_settings($data) {
		
		$settings_data = $data['data'];
		$type = $data['type'];
		
		parse_str(stripslashes($settings_data), $posted_settings);

		// We now have $posted_settings as an array.
		if ('user' == $type) {
			$current_settings = $this->keyy_login->get_user_settings();
		} else {
			// Users of this filter should be sure to apply any necessary permissions checks.
			$current_settings = apply_filters('keyy_save_settings_get_current_settings', array(), $type, $data);
		}
		
		// Strip any keyy_ prefix off the settings keys.
		foreach ($posted_settings as $key => $value) {
			if (0 === strpos($key, 'keyy_')) {
				$posted_settings[substr($key, 5)] = $value;
				unset($posted_settings[$key]);
			}
		}

		$new_settings = array_merge($current_settings, $posted_settings);

		if ('user' == $type) {
			$save_results = $this->keyy_login->set_user_settings(false, $new_settings);
		} else {
			$save_results = apply_filters('keyy_save_settings_results', false, $type, $new_settings, $data);
		}
		
		$results = array(
			'save_results' => $save_results,
		);
		
		if (!$save_results) {
			$results['errors'] = array(__('An error occurred when saving. The save was not successful.', 'keyy'));
		}
		
		return $results;
	}
	
	/**
	 * Get a current login token
	 *
	 * @param  Array $params - parameters. These are passed on to Keyy_Login::get_fresh_login_token.
	 * @uses   Keyy_Login::get_fresh_login_token.
	 * @return Array - The token.
	 */
	public function get_fresh_login_token($params = array()) {
	
		$login_token = $this->keyy_login->get_fresh_login_token($params);
		
		$seconds_until_expiry = ($login_token['expiry_time'] - time());
		
		$login_token['expires_after'] = $seconds_until_expiry;

		return $login_token;
	}
	
	/**
	 * Gets the dashboard page contents
	 *
	 * @return Array and array with the content as the 'html' attribute
	 */
	public function get_dashboard_page() {
	
		$extract_these = $this->keyy->get_common_urls();
	
		return array(
			'html' => $this->keyy->include_template('dashboard-page.php', true, $extract_these)
		);
	
	}
	
	/**
	 * Polls to see if the user has been disconnected.
	 *
	 * @param  Array $params - Any parameters. Valid parameters: wait.
	 * @uses   sleep().
	 * @return Array - key 'connected': whether the user is connected or not.
	 */
	public function get_disconnection_result($params = array()) {

		if (isset($params['wait']) && (!is_numeric($params['wait']) || $params['wait'] < 1)) return array();
		
		if (isset($params['wait'])) $params['wait'] = min($params['wait'], 30);
		$clear_cache = isset($params['wait']) ? true : false;
	
		$keep_going = true;
		$waited = 0;
		
		while ($keep_going) {
			$is_configured_for_user = $this->keyy_login->is_configured_for_user(false, $clear_cache);

			if (!isset($params['wait'])) $keep_going = false;
			
			if (!$is_configured_for_user) $keep_going = false;
		
			if ($keep_going) {
				$waited++;
				// Don't keep the session (if there is one) open whilst sleeping, so that multiple PHP processes aren't contending for it.
				if ($waited < 2) $this->close_php_session();
				sleep(1);
				if ($waited > $params['wait']) $keep_going = false;
			}
		}
		
		$result = array('connected' => $is_configured_for_user);
	
		if (!$is_configured_for_user) {
			$result['code'] = 'disconnected';
			$result['data'] = array('message' => __('This site has been disconnected from your Keyy app.', 'keyy'));
			$result['connection_token'] = $this->keyy_login->get_connection_token(false);
		}
	
		return $result;
	
	}
	
	/**
	 * Close the PHP session, if there is one open. The various lines ignored for coding standards purposes are because sensible WP code does not use PHP sessions. But the point is that we're trying to deal with the existence of third party code that may be using it.
	 */
	private function close_php_session() {
		if ('cli' === PHP_SAPI) return;
		
		$session_open = false;
		
		if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
			//@codingStandardsIgnoreLine
			if (session_status() === PHP_SESSION_ACTIVE) $session_open = true;
			//@codingStandardsIgnoreLine
		} elseif ('' !== session_id()) {
			$session_open = true;
		}

		//@codingStandardsIgnoreLine
		if ($session_open) @session_write_close();
	}
	
	/**
	 * Gets the latest connection result for the logged-in user
	 *
	 * @param  Array $params - Any parameters. Valid parameters: wait, since_time.
	 * @uses   sleep().
	 * @return Array - see Keyy_Login::get_connection_result(); returns an empty array if the since_time condition was not fulfilled.
	 */
	public function get_connection_result($params = array()) {
	
		if (isset($params['wait']) && (!is_numeric($params['wait']) || $params['wait'] < 1)) return array();
		
		if (isset($params['wait'])) $params['wait'] = min($params['wait'], 30);
		$clear_cache = isset($params['wait']) ? true : false;
	
		$keep_going = true;
		$waited = 0;
		$satisfied_since_time = false;
		
		while ($keep_going) {
			$connection_result = $this->keyy_login->get_connection_result(false, $clear_cache);
			
			if (isset($params['since_time'])) {
				if (is_array($connection_result) && isset($connection_result['time'])) {
					if ($connection_result['time'] > $params['since_time']) {
						$keep_going = false;
						$satisfied_since_time = true;
					}
				}
			} else {
				$keep_going = false;
			}
			
			if ($keep_going) {
				$waited ++;
				
				if (!isset($params['wait']) || $waited >= $params['wait']) {
					$keep_going = false;
				} else {
					// Don't keep the session (if there is one) open whilst sleeping, so that multiple PHP processes aren't contending for it.
					if ($waited < 2) $this->close_php_session();
					sleep(1);
				}
			}
		}
		
		if (isset($params['since_time']) && !$satisfied_since_time) {
			$connection_result = array();
		}
		
		return $connection_result;
		
	}
	
	/**
	 * This is a convenience method to prevent repetitive boilerplate upon failed connections
	 *
	 * @param  Integer 	    $user_id 					The WordPress user ID.
	 * @param  String 	    $connection_code 			The code indicating state.
	 * @param  String|Array $connection_message_or_data Any associated data.
	 * @return WP_Error
	 */
	private function _set_failed_connection_and_return_error($user_id, $connection_code, $connection_message_or_data = array()) {
	
		if (is_string($connection_message_or_data)) {
			$connection_data = array('message' => $connection_message_or_data);
		} else {
			$connection_data = $connection_message_or_data;
		}
	
		$this->keyy_login->set_connection_result($user_id, $connection_code, $connection_data);
		
		if (!isset($connection_data['message'])) {
			$connection_data['message'] = 'Unspecified error';
		}
		
		return new WP_Error($connection_code, $connection_data['message'], $connection_data);
	
	}
	
	/**
	 * Gets the status for a specified token.
	 *
	 * @param  Array $params - parameters; valid keys: wait, token_id.
	 * @return Array|Boolean
	 */
	public function get_token_state($params) {
	
		if (isset($params['wait']) && (!is_numeric($params['wait']) || $params['wait'] < 1)) return array();
		
		if (isset($params['wait'])) $params['wait'] = min($params['wait'], 30);
		$clear_cache = isset($params['wait']) ? true : false;
	
		$keep_going = true;
		$waited = 0;
		
		while ($keep_going) {
			$token = $this->keyy_login->verify_login_token($params['token_id'], true);
			
			if (is_array($token) && isset($token['state']) && 'claimed' == $token['state'] && isset($token['user_login']) && is_string($token['user_login'])) {
				$keep_going = false;
				// Return a sanitized version.
				$return_token = array(
					'state' => $token['state'],
					'user_login' => $token['user_login'],
					'form_id' => isset($token['form_id']) ? (int) $token['form_id'] : false
				);
				
				$user = get_user_by('login', $token['user_login']);
				
				// Help the front end to allow password to be entered after the scan, not only before
				if (is_a($user, 'WP_User')) {
				
					$user_login_policy = $this->keyy_login->get_user_login_policy($user->ID);
				
					// If a password is required, then don't immedaitely submit the form. This flag enables the JavaScript to understand that.
					if (!empty($user_login_policy['password']) && 'required' == $user_login_policy['password']) {
						$return_token['password_policy'] = 'required';
					}
					
				}
				
			}
		
			if (isset($params['wait']) && $keep_going) {
				$waited++;
				if ($waited < 2) $this->close_php_session();
				sleep(1);
				if ($waited > $params['wait']) $keep_going = false;
			} else {
				$keep_going = false;
			}
		}
		
		if (!isset($return_token) && is_array($token) && isset($token['state']) && 'unused' == $token['state']) {
			$return_token = array('state' => $token['state']);
		}
		
		return isset($return_token) ? $return_token : false;
		
	}
	
	/**
	 * Receive the result of attempting to disconnect
	 *
	 * @param Array $params - user_login. This is only paid attention to over REST or if the user has site-admin capabilities; otherwise dropped.
	 *
	 * @return Array
	 */
	public function disconnect($params = array()) {

		$user_id = false;
	
		if ((!defined('KEYY_DOING_REST') || !KEYY_DOING_REST) && !current_user_can($this->keyy->capability_required('admin')) && isset($params['user_login'])) {
			return new WP_Error('invalid_parameter', 'user_login parameter not allowed', $params);
		} elseif (isset($params['user_login']) && is_string($params['user_login'])) {
			$user = get_user_by('login', $params['user_login']);
			
			if (!$user) return new WP_Error('invalid_user', 'User specified in user_login parameter not found', $params);
			
			$user_id = $user->ID;
		}
	
		$result = array();
		
		$disconnect = $this->keyy_login->disconnect($user_id);
	
		$result['result'] = $disconnect;
		
		$this->keyy_login->delete_connection_token($user_id);
		
		$result['connection_token'] = $this->keyy_login->get_connection_token($user_id, true);
		
		$dashboard_html = $this->get_dashboard_page();
		
		$result['dashboard_html'] = $dashboard_html['html'];
		
		$user_settings_html = $this->get_user_settings_html(array('user_id' => $user_id, 'include_actions' => true));
		
		$result['user_settings_html'] = $user_settings_html['html'];
		
		return $result;
	}
	
	/**
	 * Log a user out of all his logged-in sessions
	 *
	 * @param  array $params As described in the specification (possibly relevant keys: user_login).
	 * @return array         With keys code, message, data.
	 */
	 public function logout($params) {
	 
		if (!isset($params['user_login']) || !is_string($params['user_login']) || '' === $params['user_login']) {
			return new WP_Error('missing_user_login', 'No user login was received.');
		}
		
		$user = get_user_by('login', $params['user_login']);
		
		if (!is_a($user, 'WP_User')) {
			return new WP_Error('invalid_user_login', 'The user ('.$params['user_login'].') was not recognised.');
		}
		
		$sessions = WP_Session_Tokens::get_instance($user->ID);

		$sessions->destroy_all();
			
		return array(
			'code' => 'logged_out',
			'message' => sprintf(__('The user %s has been logged out.', 'keyy'), $user->user_login),
			'data' => array(),
		);
	 
	 }
	 
	/**
	 * Log a specified user out of all his logged-in sessions
	 *
	 * @param  array $params Relevant key: user_id.
	 * @return array         With keys code, message, data.]
	 */
	 public function logout_any_user($params) {
	 
		if (empty($params['user_id']) || !is_numeric($params['user_id'])) {
			return new WP_Error('missing_user_id', 'No user ID was received.');
		}
		
		$user = get_user_by('id', $params['user_id']);
		
		if (!is_a($user, 'WP_User')) {
			return new WP_Error('invalid_user_login', 'The user ('.$params['user_login'].') was not recognised.');
		}
		
		$sessions = WP_Session_Tokens::get_instance($user->ID);

		$sessions->destroy_all();
			
		return array(
			'code' => 'logged_out',
			'message' => sprintf(__('The user %s has been logged out.', 'keyy'), $user->user_login),
			'data' => array(),
		);
	 
	 }
	
	/**
	 * Receive the result of attempting to login
	 *
	 * @param Array $params - as described in the specification (possibly relevant keys: user_login, token).
	 * @return Array
	 */
	public function sso_session_status($params) {
	
		// TODO
		// Need to verify that the session is logged in, and that the user chose to start an SSO session.
		return array(
			'session_begun' => false
		);
	
	}
	
	/**
	 * Receive the result of attempting to login
	 *
	 * @param Array $params - as described in the specification (possibly relevant keys: user_login, token, include_autologin, sso_server).
	 * @return Boolean. N.B. This does not indicate the result of attempting to login, but whether the message got through and could be understood.
	 */
	 public function login($params) {
	 
		// Identify the user first, so that the results can be passed back to them.
		if (!isset($params['user_login']) || !is_string($params['user_login']) || '' === $params['user_login']) {
			return new WP_Error('missing_user_login', 'No user login was received.');
		}
		
		$user = get_user_by('login', $params['user_login']);
		
		if (!is_a($user, 'WP_User')) {
			return new WP_Error('invalid_user_login', 'The user ('.$params['user_login'].') was not recognised.');
		}
		
		// Beware of type-juggling.
		$token_id = isset($params['token']) ? $params['token'] : '';
		
		if (!is_string($token_id) || '' == $token_id || strlen($token_id) > 45) {
			return new WP_Error('invalid_token', 'The supplied token was invalid. Please try again');
		}
		
		$token = $this->keyy_login->verify_login_token($token_id);
		
		if (false == $token) {
			return new WP_Error('not_valid_token', 'The supplied token was not valid. Please try again');
		}
		
		// Was the token locked to a particular user?
		if (!empty($token['user_login']) && $token['user_login'] != $user->user_login) {
			return new WP_Error('token_is_for_another_user', __('The login token is not allowed to be used by this user. Please try again.', 'keyy'));
		}
		
		if (!isset($token['state']) || 'unused' !== $token['state']) return new WP_Error('invalid_token', __('The login token was not valid (already used). Please try again.', 'keyy'));

		$extra = array();
		
		if (isset($params['form_id']) && is_numeric($params['form_id'])) {
			$extra['form_id'] = $params['form_id'];
		}
		
		if (isset($params['sso_server']) && is_string($params['sso_server']) && preg_match('#^https?://#i', $params['sso_server'])) {
			$extra['sso_server'] = $params['sso_server'];
		}
		
		// Claim the token for this user ID.
		$set_result = $this->keyy_login->set_token_result($user, $token_id, 'accepted', $extra);
		
		if (is_wp_error($set_result)) return $set_result;
		
		$data = array();
		
		if (!empty($params['include_autologin'])) {
		
			$autologin_url = wp_login_url().'?keyy_token_id='.$token_id;
		
			$user_login_policy = $this->keyy_login->get_user_login_policy($user->ID);
			
			// If a password is required, then don't immedaitely submit the form. This flag enables the JavaScript to understand that.
			if (!empty($user_login_policy['password']) && 'required' == $user_login_policy['password']) {
				$autologin_url .= '&keyy_user_login='.urlencode($user->user_login).'&keyy_password=required';
			}

			// Basic idea: add it as a parameter to the wp-login.php page. We want to indicate the token, and that the form should be immediately submitted.
			$data['autologin_url'] = $autologin_url;
		}
		
		if ($this->keyy_login->is_sso_enabled()) $data['sso_enabled'] = true;
		
		return apply_filters('keyy_command_login', array(
			'code' => 'accepted',
			'message' => sprintf(__('The request to login as %s was accepted.', 'keyy'), $user->user_login),
			'data' => $data,
		));
		
	 }
	
	/**
	 * Receive the result of attempting to connect
	 *
	 * @param Array $params - as described in the specification (possibly relevant keys: status, message_code, message, public_key, token, user_login).
	 * @return Array|WP_Error, as per the specification. N.B. This does not indicate the result of attempting to connect, but whether the message got through and could be understood.
	 */
	 public function connect($params) {
	 
		// Identify the user first, so that the results can be passed back to them.
		if (!isset($params['user_login']) || !is_string($params['user_login']) || '' === $params['user_login']) {
			return new WP_Error('missing_user_login', 'No user login was received.');
		}
		
		$user = get_user_by('login', $params['user_login']);
		
		if (!is_a($user, 'WP_User')) {
			return new WP_Error('invalid_user_login', 'The user ('.$params['user_login'].') was not recognised.');
		}

		if (!isset($params['status'])) {
			return $this->_set_failed_connection_and_return_error($user->ID, 'missing_status_code', 'No status code was received.');
		}
	 
		// Theoretically impossible at this stage, as the signature was alraedy verified.
		if (!isset($params['public_key']) || !is_string($params['public_key'])) {
			return $this->_set_failed_connection_and_return_error($user->ID, 'missing_public_key', 'No public key was received.');
		}
	 
		$connection_data = array(
			'message' => (isset($params['message']) && is_string($params['message'])) ? $params['message'] : __('The attempt to connect failed. Please try again.', 'keyy')
		);
		
		if (isset($params['message_code']) && is_string($params['message_code'])) $connection_data['message_code'] = $params['message_code'];
		
		if (isset($params['message_data']) && (is_string($params['message_data']) || is_array($params['message_data']))) $connection_data['message_data'] = $params['message_data'];
	 
		if (!$params['status']) {
			return $this->_set_failed_connection_and_return_error($user->ID, 'not_connected', $connection_data);
		}
		
		// Beware of type-juggling.
		$token = isset($params['token']) ? $params['token'] : '';
		$expected_token = $this->keyy_login->get_connection_token($user->ID);
		
		if ($expected_token['expiry_time'] < time()) {
			return $this->_set_failed_connection_and_return_error($user->ID, 'stored_token_expired', __('No setup process was active - perhaps it timed out. You should try again.', 'keyy'));
		}
		
		if (empty($expected_token['value'])) {
			return $this->_set_failed_connection_and_return_error($user->ID, 'missing_expected_token', __('No setup process was active - perhaps it timed out and you should try again.', 'keyy'));
		}

		if (!$token || $token !== $expected_token['value']) {
			return $this->_set_failed_connection_and_return_error($user->ID, 'incorrect_expected_token', __('The security token received was incorrect; perhaps it had expired. Please try again.', 'keyy'));
		}
	 
		if (!isset($connection_data['message_data']['account_email'])) {
			return $this->_set_failed_connection_and_return_error($user->ID, 'missing_account_email', __('The security token was valid, but no account email address was indicated.', 'keyy'), $connection_data);
		}
	 
		$connection_data['message'] = (isset($params['message']) && is_string($params['message'])) ? $params['message'] : __('The attempt to connect succeeded.', 'keyy');
	 
		$this->keyy_login->set_connection_result($user->ID, 'connected', $connection_data);
		
		$this->keyy_login->set_user_setting($user->ID, 'email', $connection_data['message_data']['account_email']);
		
		$this->keyy_login->set_public_key($user->ID, $params['public_key']);
	 
		// Prevent double-use.
		$this->keyy_login->delete_connection_token($user->ID);
	 
		return array(
			'code' => 'connected',
			'message' => $connection_data['message'],
			'data' => array(
				'name' => get_bloginfo(),
			),
		);
		
	 }
}
