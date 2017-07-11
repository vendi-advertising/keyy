<?php

if (!defined('KEYY_DIR')) die('Security check');

$common_urls = Keyy_Login_Plugin()->get_common_urls();

return array(
	'role' => __('Role', 'keyy'),
	'delete' => __('Delete', 'keyy'),
	'use_this_policy' => __('use this policy', 'keyy'),
	'error_unexpected_response' => __('An unexpected response was received.', 'keyy'),
	'replacing' => __('The token has expired; a replacement is being retrieved...', 'keyy'),
	'refresh' => __('An error occurred. Please refresh the page and try again.', 'keyy'),
	'what_is_this' => __('What is this?', 'keyy'),
	'login_form_not_found' => __('The login form was not found. Please try again.', 'keyy'),
	'disabled' => __("Keyy has been disabled. Only WordPress's default login mechanisms (and any others from other plugins) are active.", 'keyy'),
	'authorised_needs_password' => __('An authorised login request from your Keyy app has been received. Please now enter your password to complete login.', 'keyy'),
	'choose_valid_user' => __('You must first choose a valid user.', 'keyy'),
	'user_must_provide_password' => __('The user must use a password', 'keyy'),
	'user_must_provide_keyy' => __('The user must scan with Keyy', 'keyy'),
	'user_must_provide_both' => __('The user must both use a password and scan with Keyy', 'keyy'),
	'emails_status' => __('Emails sent: %s Emails failed to send: %s', 'keyy'),
	'emails_continue' => __('The next batch will now be sent.', 'keyy'),
	'emails_complete' => __('All emails have now been sent.', 'keyy'),
);
