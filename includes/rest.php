<?php

if (!defined('KEYY_DIR')) die('No direct access allowed');

/**
 * Handles interact with WP's REST system. This file has the Keyy_REST class. This is mostly a glue layer onto our command class.
 */
class Keyy_REST {

	private $keyy;

	private $keyy_login;

	/**
	 * As well as the 'methods' key, you can also use a 'needs_authorisation' key (defaults to true) to control whether the command needs authorisation.
	 *
	 * @var array
	 */
	private $available_commands = array(
		// The ping method needs authorisation, because testing the authorisation is its main reason for existing.
		'ping' => array('methods' => 'POST'),
		'get_version' => array('methods' => 'GET,POST', 'needs_authorisation' => false),
		'connect' => array('methods' => 'POST'),
		'disconnect' => array('methods' => 'POST'),
		'login' => array('methods' => 'POST'),
		'get_fresh_login_token' => array('methods', 'GET,POST'),
		'logout' => array('methods' => 'POST'),
		'sso/session_status' => array('methods', 'POST'),
	);

	/**
	 * Constructor
	 *
	 * @param Keyy_Login_Plugin $keyy - the parent Keyy object.
	 */
	public function __construct($keyy) {
		add_action('rest_api_init', array($this, 'rest_api_init'));
		$this->keyy = $keyy;
		$this->keyy_login = $this->keyy->login_instance();
	}
	
	/**
	 * Runs on the WP rest_api_init action.
	 */
	public function rest_api_init() {
	
		foreach ($this->available_commands as $command => $params) {
		
			$call_command = (false !== strpos($command, '/')) ? str_replace('/', '_', $command) : $command;
	
			register_rest_route('keyy/v1', '/'.$command.'/?', array(
				'methods' => isset($params['methods']) ? $params['methods'] : 'POST',
				'callback' => array($this, $call_command),
			));
		}
	}
	
	/**
	 * Despatch the indicated commands.
	 *
	 * @param  String 		   $command - the command.
	 * @param  WP_REST_Request $request - the WP_REST_Request object.
	 * @return Mixed a result, which will be processed by the REST API layer.
	 */
	 public function despatch_command($command, $request) {
		
		define('KEYY_DOING_REST', true);
		
		// Other commands, available for any remote method.
		if (!class_exists('Keyy_Commands')) include_once(KEYY_DIR.'/includes/class-commands.php');

		$commands = new Keyy_Commands();
		
		$call_command = (false !== strpos($command, '/')) ? str_replace('/', '_', $command) : $command;

		if (!isset($this->available_commands[$command]) || !is_callable(array($commands, $call_command))) {
			return new WP_Error('keyy_no_such_command', 'No such command', array('status' => 404, 'command' => $command));
		}
		
		$params = $request->get_json_params();
		
		if (!isset($this->available_commands[$command]['needs_authorisation']) || $this->available_commands[$command]['needs_authorisation']) {
			if (!class_exists('Keyy_Message_Verify')) include_once(KEYY_DIR.'/includes/class-message-verify.php');
			
			try {
				$message_verify = new Keyy_Message_Verify($params);
				
				if ('connect' == $command) {
					// With the 'connect' command, we still verify the signature, but, the authentication is coming from the token. On non-https connections this allows a theoretical MITM on the initial key exchange; but this is only theoretical because on such connections a MITM attacker can already snoop the login cookie.
					if (isset($params['public_key']) && is_string($params['public_key'])) {
						$message_verify->set_public_key($params['public_key']);
					} else {
						return new WP_Error('no_public_key', 'No public key was provided.');
					}
				} else {
					if (!isset($params['user_login']) || !is_string($params['user_login'])) {
						if (isset($params['user_email']) && is_string($params['user_email'])) {
							$user = get_user_by('email', $params['user_email']);
							
							if (!$user) return new WP_Error('invalid_user', 'User specified in user_email parameter not found');
							
							// N.B. Since the parameters have already been loaded by the Keyy_Message_Verify class, changing them at this stage will not break signature verification.
							$params['user_login'] = $user->user_login;
							unset($params['user_email']);
						} else {
							return new WP_Error('no_user', 'No user was validly specified in the user_login parameter');
						}
					}
				
					if (!isset($user)) $user = get_user_by('login', $params['user_login']);
			
					if (!$user) return new WP_Error('invalid_user', 'User specified in user_login parameter not found');
					
					$user_id = $user->ID;
				
					$public_key = $this->keyy_login->get_public_key($user_id);
				
					$message_verify->set_public_key($public_key);
				}
				
				if (!$message_verify->verify_message()) {
					return new WP_Error('message_verification_failed', 'The signature was invalid');
				}
			} catch (Exception $e) {
				return new WP_Error('message_verification_failed', $e->getMessage());
			}
		}
		
		$result = call_user_func(array($commands, $call_command), $params);

		return $result;
		
	 }
	
	/**
	 * Get the version
	 *
	 * @param WP_REST_Request $request - the REST request object.
	 */
	public function get_version(WP_REST_Request $request) {
		return $this->despatch_command('get_version', $request);
	}

	/**
	 * Verify whether a user has chosen to start an SSO session or not for a given login
	 *
	 * @param WP_REST_Request $request - the REST request object.
	 */
	public function sso_session_status(WP_REST_Request $request) {
		return $this->despatch_command('sso/session_status', $request);
	}

	/**
	 * An app connects
	 *
	 * @param WP_REST_Request $request - the REST request object.
	 */
	public function connect(WP_REST_Request $request) {
		return $this->despatch_command('connect', $request);
	}
	
	/**
	 * An app sends a signed login token
	 *
	 * @param WP_REST_Request $request - the REST request object.
	 */
	public function login(WP_REST_Request $request) {
		return $this->despatch_command('login', $request);
	}
	
	/**
	 * An app sends a logout command
	 *
	 * @param WP_REST_Request $request - the REST request object.
	 */
	public function logout(WP_REST_Request $request) {
		return $this->despatch_command('logout', $request);
	}
	 
	/**
	 * An app disconnects
	 *
	 * @param WP_REST_Request $request - the REST request object.
	 */
	public function disconnect(WP_REST_Request $request) {
		return $this->despatch_command('disconnect', $request);
	}
	 
	/**
	 * Ping (test authorisation)
	 *
	 * @param WP_REST_Request $request - the REST request object.
	 */
	public function ping(WP_REST_Request $request) {
		return $this->despatch_command('ping', $request);
	}

	/**
	 * Get a login token
	 *
	 * @param WP_REST_Request $request - the REST request object.
	 */
	public function get_fresh_login_token(WP_REST_Request $request) {
		return $this->despatch_command('get_fresh_login_token', $request);
	}
}
