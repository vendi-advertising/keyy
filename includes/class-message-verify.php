<?php

if (!defined('KEYY_DIR')) die('No direct access allowed');

/**
 * This class is for verifying an incoming message. It uses the phpseclib library.

	Usage:

	The example below is the shortest way. If you don't pass data to the constructor, you can also do things a longer way by calling more methods. Do not omit to catch exceptions.

	$message_verify = new Keyy_Message_Verify($data);

	try {
		$message_verify->set_public_key($public_key);
		$result = $message_verify->verify_message();
	} catch (Exception $e) {
		// Do something with the thrown exception
	}
 */
class Keyy_Message_Verify {

	private $hash_algorithm = 'sha256';

	private $allowed_hash_algorithms = array('sha256', 'sha384', 'sha512');
	
	private $public_key;
	
	private $signature;
	
	private $data;
	
	/**
	 * Constructor.
	 *
	 * @throws Exception Methods which are called may do this.
	 * @param Array|Null $data - a payload to work with. Note that any included public_key (on the connect message) is *not* automatically sent to set_public_key().
	 */
	public function __construct($data = null) {
	
		if (is_array($data)) {
			if (isset($data['signature_algorithm'])) {
				$this->set_hash_algorithm($data['signature_algorithm']);
			}

			if (isset($data['signature_hash'])) {
				$this->set_signature($data['signature_hash']);
			}
		
			$this->set_data($data);
		}
	
	}
	
	/**
	 * Ensure that the phpseclib library is loaded - specifically, the Crypt_RSA and Crypt_Hash classes
	 */
	private function load_rsa_functions() {
	
		if (class_exists('Crypt_RSA') && class_exists('Crypt_Hash')) return;
	
		if (false === strpos(get_include_path(), KEYY_DIR.'/vendor/phpseclib/phpseclib/phpseclib')) set_include_path(KEYY_DIR.'/vendor/phpseclib/phpseclib/phpseclib'.PATH_SEPARATOR.get_include_path());

		if (!class_exists('Crypt_RSA')) include_once 'Crypt/RSA.php';
		if (!class_exists('Crypt_Hash')) include_once 'Crypt/Hash.php';
		
	}
	
	/**
	 * Load the mentioned public key.
	 *
	 * @param String $key The public key, in PEM format.
	 */
	public function set_public_key($key) {
		$this->public_key = $key;
	}
	
	/**
	 * Sets the data to work on
	 *
	 * @param Array - $data - a set of parameters. This method will strip any signature_ parameters, but will not do anything with any of them.
	 */
	public function set_data($data) {
	
		foreach ($data as $key => $value) {
			if (0 === strpos($key, 'signature_')) unset($data[$key]);
		}
	
		$this->data = $data;
	}
	
	/**
	 * Sets the signature calculated for the message (which at this stage is not known to be correct).
	 *
	 * @param Array - $signature - The signature.
	 */
	public function set_signature($signature) {
		$this->signature = $signature;
	}
	
	/**
	 * Use the specified hash algorithm when comparing signatures.
	 *
	 * @param String $hash_algorithm An algorithm, as known by hash_algos().
	 * @throws Exception 			 If the algorithm was not available.
	 */
	public function set_hash_algorithm($hash_algorithm) {
	
		if ('' == $hash_algorithm || !is_string($hash_algorithm) || !in_array($hash_algorithm, hash_algos())) {
			throw new Exception('Hash algorithm not available in this PHP installation.');
		}
	
		if (!in_array($hash_algorithm, $this->allowed_hash_algorithms)) {
			throw new Exception('This hash algorithm is not supported.');
		}
		
		$this->hash_algorithm = $hash_algorithm;
		
	}
	
	/**
	 * This turns a set of supplied parameters into canonical form, as described in the spec.
	 *
	 * @param Array $data - a set of parameters. It is not necessary to pre-strip signature_ parameters; these will be handled. N.B. Currently, if any parameters are arrays, then those arrays cannot contain further arrays as values (without improving this method).
	 * @throws Exception If something goes wrong.
	 * @return String - the canonicalised message.
	 */
	public function get_canonical_message($data) {
	
		if (!ksort($data)) {
			throw new Exception('Sorting message data failed.');
		}
	
		$message = '';
		
		foreach ($data as $key => $value) {
			if (0 === strpos($key, 'signature_')) continue;
		
			if (is_array($value)) {
				foreach ($value as $key2 => $value2) {
					$message .= "$key-$key2:$value2\n";
				}
			} else {
				$message .= "$key:$value\n";
			}
		}
		
		return $message;
	
	}
	
	/**
	 * Verify whether the message has a valid signature from the private key associated with the known public key
	 *
	 * @param  string $message
	 * @throws Exception 	   If various pre-required parameters were not set up, or if the verification fails.
	 * @return Boolean
	 */
	public function verify_signature($message) {
	
		if ('' == $message) throw new Exception('An empty message was supplied for verification.');
	
		$this->load_rsa_functions();
		
		$rsa = new Crypt_RSA();
		
		if (!$this->hash_algorithm) {
			throw new Exception('No hash algorithm has been set for calculating the signature with.');
		}
		
		$rsa->setHash($this->hash_algorithm);
		
		// This is not the default, but is what we use.
		$rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);

		if (!$this->public_key) {
			throw new Exception('No public key has been set for verifying the signature with.');
		}
		
		$rsa->loadKey($this->public_key);

		// No need to hash it - Crypt_RSA::verify() already does that.
		if (!$this->signature) {
			throw new Exception('No signature has been set for verifying.');
		}

		$verified = $rsa->verify($message, base64_decode($this->signature), $this->public_key);

		return $verified;
	}
	
	/**
	 * Calculate whether the message is signed (by the private key corresponding to our public one) with the correct hash.
	 *
	 * @throws Exception If any required parameters had not been previously set up with set_ methods, or if the verification failed.
	 * @return Boolean (which, given that it throws an exception upon failure, will be true).
	 */
	public function verify_message() {
	
		if (!$this->data) {
			throw new Exception('No data has been loaded to compare the signature with.');
		}
		
		$message = $this->get_canonical_message($this->data);
	
		if (!$this->verify_signature($message)) {
			throw new Exception('The signature was invalid');
		}
		
		return true;
	
	}
}
