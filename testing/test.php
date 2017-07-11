<?php
/**
 * This test script immitates an app. After copy/pasting in the QR code (you can get this from the developer console in the plugin), it will call back via the REST API to register your public key. You can then copy/paste in the QR code from the login page in order to trigger a login; or, you can get an auto-login URL.
 */

// @codingStandardsIgnoreLine
if ('cli' != php_sapi_name() || !empty($_SERVER['SERVER_ADDR']) || !empty($_SERVER['REQUEST_METHOD'])) die('Command-line use only.');

// Configuration.
$hash_algorithm = 'sha256';
// The RSA signature mode used is PKCS1 (hard-coded further down via a phpseclib constant).
// Configuration end.

define('KEYY_DIR', dirname(dirname(__FILE__)));

// Load crypto.
if (false === strpos(get_include_path(), KEYY_DIR.'/vendor/phpseclib/phpseclib/phpseclib')) set_include_path(KEYY_DIR.'/vendor/phpseclib/phpseclib/phpseclib'.PATH_SEPARATOR.get_include_path());

if (!class_exists('Crypt_RSA')) require_once 'Crypt/RSA.php';
if (!class_exists('Crypt_Hash')) require_once 'Crypt/Hash.php';

$keyy_version = '??';
// @codingStandardsIgnoreLine
if ($fp = fopen(KEYY_DIR.'/keyy.php', 'r')) {
	// @codingStandardsIgnoreLine
	$file_data = fread($fp, 1024);
	if (preg_match("/Version: ([\d\.]+)(\r|\n)/", $file_data, $matches)) {
		$keyy_version = $matches[1];
	}
	// @codingStandardsIgnoreLine
	fclose($fp);
}

require_once KEYY_DIR.'/includes/login.php';

require_once KEYY_DIR.'/includes/class-message-verify.php';

$rsa = new Crypt_RSA();

$rsa->setHash($hash_algorithm);
$rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);

echo "Generating key-pair...\n";

$key_pair = $rsa->createKey(2048);

$private_key = $key_pair['privatekey'];
$public_key = $key_pair['publickey'];

$rsa->loadKey($private_key);

echo "Enter the QR code URL for connecting: ";

do_connect(fgets(STDIN));

/**
 * Send a 'connect' command
 *
 * @param String  $qr_url			- the 'scanned' URL
 * @param Boolean $die_upon_failure - whether to die() if the attempt fails
 *
 * @return Array - the response. May die upon failure.
 */
function do_connect($qr_url, $die_upon_failure = true) {

	global $rest_url_base, $user_login, $public_key;

	$qr_url = rtrim($qr_url);

	$qr_data = Keyy_Login::parse_url($qr_url);

	if ('connect' !== $qr_data['context'] || '' == $qr_data['token'] || '' == $qr_data['user_login']) {
		// @codingStandardsIgnoreLine
		print_r($qr_data);
		die('This was not a valid connection URL');
	}

	$rest_url_base = $qr_data['rest_url'].'keyy/v1/';

	$user_login = $qr_data['user_login'];

	// N.B. This is JSON-encoded at the transport layer.
	$post_data = array(
		'status' => true,
	// Successful scan.
		'message_code' => 'connected',
		'message_data' => array('account_email' => 'user@example.com'),
		'message' => 'You have successfully connected - the account owner is user@example.com',
		'user_login' => $user_login,
		'token' => $qr_data['token'],
		'public_key' => $public_key,
	);

	$response = send_rest_command('connect', $post_data);

	if (empty($response['code']) || 'connected' != $response['code']) {
		if ($die_upon_failure) {
			die('Failed to connect - aborting.');
		}
		echo "Failed to connect\n";
	}
	
	return $response;

}

/**
 * Send a command to a site's REST interface, and print and return the results
 *
 * @param String $command     - the command to send.
 * @param Array  $post_data   - this data will be signed and encoded and sent with the request. When sending to the mothership, the user_email parameter will also be set (except for the get_version command which uses no authentication).
 * @param String $destination - 'client' or 'mothership' depending on which destination the command is for.
 * @return Array - the response, after JSON-decoding
 */
function send_rest_command($command, $post_data, $destination = 'client') {

	global $rsa, $rest_url_base, $hash_algorithm, $key_server_base, $user_email, $sso_server, $keyy_version;
	
	if ('mothership' == $destination) {
		if (empty($key_server_base)) {
			echo "Error: You need to first set mothership URL (M http(s)://...)\n";
			
			return false;
		}
	
		if ('get_version' !== $command && empty($user_email)) {
			echo "Error: You need to first set an email address (E myemail@example.com)\n";
			
			return false;
		}
		
		$post_data['user_email'] = $user_email;
	} elseif ('sso' == $destination) {
		if (empty($sso_server)) {
			echo "Error: You need to first set the SSO server URL (SSO http(s)://...)\n";
			return false;
		}
	}
	
	$url_base = ('client' == $destination) ? $rest_url_base : ('sso' == $destination ? $sso_server : $key_server_base);
	
	$rest_url = $url_base."$command/";
	
	// @codingStandardsIgnoreStart
	$ch = curl_init($rest_url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$curl_version = curl_version();
	curl_setopt($ch, CURLOPT_USERAGENT, 'Keyy-TestPHPApp/ '.$keyy_version.' PHP/'.PHP_VERSION.' Curl/'.$curl_version['version']);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
	));
	// @codingStandardsIgnoreEn
	
	$post_data['signature_algorithm'] = $hash_algorithm;
	
	// Signature calculation.
	$message_verify = new Keyy_Message_Verify();
	$message_verify->set_hash_algorithm($hash_algorithm);
	$canonical_message = $message_verify->get_canonical_message($post_data);

	$signature = $rsa->sign($canonical_message);
	$signature_b64 = base64_encode($signature);

	$post_data['signature_hash'] = $signature_b64;

	echo "POSTing to $rest_url: ".json_encode($post_data)."\n";

	// @codingStandardsIgnoreStart
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

	$rest_response = curl_exec($ch);
	
	curl_close($ch);
	// @codingStandardsIgnoreEnd

	echo "Response:\n";
	if (null !== ($decoded = json_decode($rest_response, true))) {
		// @codingStandardsIgnoreLine
		print_r($decoded);
	} else {
		// @codingStandardsIgnoreLine
		print_r($rest_response);
	}
	echo "\n";
	
	$response = json_decode($rest_response, true);
	
	return $response;
	
}

$key_server_base = null;

$user_email = null;

while (true) {
	echo "Commands: Q=quit, D=disconnect+quit, Paste in a login URL or A=get an auto-login URL, L=Log out, E <email address>=set the user email, 'M <url>'=set the mothership URL (i.e. testing Keyy Server), R=register account, S=get account status, 'SSO <url>' - set a single sign-on server, 'C <url> - connect to another site (useful for testing SSO)', '+ <url> <user_login> <description>'=add site, '- <url> <user_login>'=delete sites, V=re-send validation\n";
	
	$command = rtrim(fgets(STDIN));
	
	if ('q' == strtolower(rtrim($command))) exit;
	
	if ('d' == strtolower(rtrim($command))) {
		$post_data = array(
			'user_login' => $qr_data['user_login'],
		);
		
		$rest_response = send_rest_command('disconnect', $post_data);
	
		exit;
	}
	
	if ('a' == strtolower(rtrim($command))) {
		// This command is to emulate the same-device login flow, where login is initiated from an app via getting a token and signing it, and then receiving a URL to direct the device web browser to to complete the login.
		// $qr_data is set from the previous connect URL.
		$rest_url_base = $qr_data['rest_url'].'keyy/v1/';
		
		// N.B. This is JSON-encoded at the transport layer.
		$post_data = array(
			'user_login' => $user_login
		);
		
		$response = send_rest_command('get_fresh_login_token', $post_data);
		
		if (isset($response['value'])) {
			$rest_url_base = $qr_data['rest_url'].'keyy/v1/';
		
			// N.B. This is JSON-encoded at the transport layer.
			$post_data = array(
				'user_login' => $user_login,
				'token' => $response['value'],
				'include_autologin' => true
			);
			
			$response = send_rest_command('login', $post_data);
		}
	} elseif (preg_match('/^(https?:.*)$/i', $command, $matches)) {
		$qr_data = Keyy_Login::parse_url($matches[1]);
		
		$rest_url_base = $qr_data['rest_url'].'keyy/v1/';
		
		// N.B. This is JSON-encoded at the transport layer.
		$post_data = array(
			'user_login' => $user_login,
			'token' => $qr_data['token'],
			'form_id' => $qr_data['form_id']
		);
		
		if (!empty($sso_server)) {
			$post_data['sso_server'] = $sso_server;
		}
		
		$response = send_rest_command('login', $post_data);
		
		if (is_array($response) && 'accepted' == $response['code'] && !empty($response['data']['sso_enabled']) && !empty($sso_server)) {
		
			// A non-test app would apply some sort of back-off strategy for a limited time (which should be correlated with the amount of time that the 'offer' is shown to the user in WordPress).
			$poll_sso = true;
			
			while ($poll_sso) {
			
				echo "Poll for SSO session? n=no, anything else=yes\n";
			
				$poll_sso_response = rtrim(fgets(STDIN));
				
				if ('n' == strtolower($poll_sso_response)) {
					$poll_sso = false;
				} else {
				
					// Poll for whether the user started an SSO session.
				
					$post_data = array(
						'user_login' => $user_login,
						'login_token' => $qr_data['token'],
					);
					
					$response = send_rest_command('sso/session_status', $post_data);
				
					if (0) {
						// TODO: Look at the response, and if a session has begun, send the pre-signed tokens to the SSO server
					
					}
				
				}
			
			}
		
		}
		
	} elseif (preg_match('/^M (https?:.*)$/i', $command, $matches)) {
		$key_server_base = rtrim($matches[1], '/').'/wp-json/keyy_server/v1/';
		
		echo "The mothership (i.e. test of getkeyy.com) JSON endpoint has been set to: $key_server_base\n";
		
		$response = send_rest_command('get_version', array(), 'mothership');
	} elseif (preg_match('/^C (https?:.*)$/i', $command, $matches)) {
		
		$connect = do_connect($matches[1], false);
		// @codingStandardsIgnoreLine
		print_r($connect);
		
		$response = send_rest_command('get_version', array(), 'mothership');
	} elseif (preg_match('/^SSO (https?:.*)$/i', $command, $matches)) {
		$sso_server = rtrim($matches[1], '/').'/wp-json/keyy/v1/';
		
		echo "The single sign-on server (a site with Keyy that acts as a central point for handling SSO sessions) JSON endpoint has been set to: $sso_server\n";
		
		$response = send_rest_command('get_version', array(), 'sso');
	} elseif (preg_match('/^E (.*)$/i', $command, $matches)) {
		// Set email address. This is just internal to this testing client, for its later use - no commands are sent anywhere.
		$user_email = trim($matches[1]);
			
		echo "Your email address has been set to: $user_email\n";
	} elseif ('r' == strtolower(rtrim($command))) {
		// Register. Though user_email is a required parameter, send_rest_command() takes care of that.
		$response = send_rest_command('user/register', array('public_key' => $public_key), 'mothership');
	} elseif ('l' == strtolower(rtrim($command))) {
		// Logout
		$post_data = array(
			'user_login' => $user_login,
		);
		$response = send_rest_command('logout', $post_data);
	} elseif ('s' == strtolower(rtrim($command))) {
		// Status.
		$response = send_rest_command('user/status', array(), 'mothership');
	} elseif (preg_match('/^\+ (https?:\S+) (\S+) (.+)$/i', $command, $matches)) {
		$site_to_add = $matches[1];
		
		$user_login_to_add = $matches[2];
		
		$description_to_add = $matches[3];
				
		$response = send_rest_command(
			'sites/add',
			array(
				'site' => array(
					'home_url' => $site_to_add,
					'user_login' => $user_login_to_add,
					'description' => $description_to_add
				)
			),
			'mothership'
		);
	} elseif (preg_match('/^\- (https?:\S+) (\S+)$/i', $command, $matches)) {
		$site_to_remove = $matches[1];
		
		$user_login_to_remove = $matches[2];
		
		$response = send_rest_command(
			'sites/delete',
			array(
				'site' => array(
					'home_url' => $site_to_remove,
					'user_login' => $user_login_to_remove
				),
			),
			'mothership'
		);
		
	} elseif ('v' == strtolower(rtrim($command))) {
		$response = send_rest_command('user/re_send_validation', array(), 'mothership');
	} else {
		echo "Unrecognised command\n";
	}
}
