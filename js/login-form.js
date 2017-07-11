jQuery(document).ready(function($) {
	
	var i = 0;
	
	// Inject into any relevant forms.
	$.each(keyy.hook_forms, function(index, data) {
		var selector = data.selector;
		
		if ($(selector).length < 1) { return; }

		keyy_inject_code_into_form(selector, i + 1);
				
		if (!keyy.is_disabled && data.hasOwnProperty('hide') && data.hide) {
			$(data.hide).hide();
		}
		
		i++;
	});
	
	$('form').on('click', '.keyy_qrcode_container a.keyy_qrcode_explanation', function(e) {
		e.preventDefault();
		$(this).parent().hide().html(keyy.what_is_this_answer).fadeIn('slow');
	});
	
	if (keyy.stealth_mode) {
		$('body').focus().blur();
		
		var keypress_handler = function(event) {
			if (event.hasOwnProperty('which') && 107 == event.which) {
				var tag = event.target.tagName.toLowerCase();
				if ('input' != tag && 'textarea' != tag) {
					$('.keyy_qrcode_container').fadeToggle('slow');
					$('body').off('keypress', keypress_handler);
				}
			}
		};
		
		$('body').keypress(keypress_handler);
	}
});

/**
 * Inject a QR code into a login form
 *
 * @param {String} form_selector - the form (a single selector only)
 * @param {String} form_identifier - a unique (across the page) identifier fo use for this QR code. This is for the purposes of distinguishing which form was scanned in the case of multiple being on the same page.
 * @param {String} [selector] - where to inject after. If unspecified, it will be added at the start of the form. Not yet supported (i.e. do not specify).
 */
function keyy_inject_code_into_form(form_selector, form_identifier, selector) {
	
	var $ = jQuery;
	
	// Useful keys: value, expiry_date, expires_after (autologin, password_policy).
	var login_token = Keyy.get_login_token();
	
	// Create the div for the QR code.
	if ('undefined' === typeof selector) {
		if (keyy.is_disabled) {
			$(form_selector).prepend('<div class="keyy_qrcode_container_disabled">'+keyy.disabled+'</div>');
			
			if (Keyy.parse_query_string.hasOwnProperty('keyy_disable')) {
			
				$(form_selector).prepend('<input type="hidden" name="keyy_disable" value="'+Keyy.parse_query_string.keyy_disable+'">');
				
			}
			
			return;
		}
		
		var prepend_this = '<div class="keyy_qrcode_container"';
		
		if (keyy.stealth_mode) {
			// We do this directly, because we can't rely on the style sheet having been loaded.
			prepend_this += ' style="display:none;"';
		}
		
		prepend_this += '><div class="keyy_qrcode" data-keyy_form_identifier="'+form_identifier+'"></div>';
		
		if (keyy.what_is_this_answer) { prepend_this += '<div class="keyy_qrcode_descriptor"><a href="#" class="keyy_qrcode_explanation">'+keyy.what_is_this+'</a></div>'; }
		
		prepend_this += '</div>';
		
		$(form_selector).prepend(prepend_this);
	}
	
	// Display the QR code in the div.
	var qr_selector = form_selector + ' .keyy_qrcode';
	
	Keyy.show_qr_code(qr_selector, 'login');

	if (login_token.hasOwnProperty('origin') && 'autologin' == login_token.origin && login_token.hasOwnProperty('password_policy') && 'required' == login_token.password_policy) {
		if (login_token.hasOwnProperty('user_login')) {
			var $login_form = $('form .keyy_qrcode').first().closest('form');
			
			$login_form.find('input[name="log"], input[name="username"], input[name="affwp_user_login"]').val(login_token.user_login).prop('readonly', true);
			
			$login_form.find('.keyy_qrcode').html(keyy.authorised_needs_password);
		}
		
		console.log("Keyy: user policy requires a password. Will not auto-submit form.");
		
		return;
	}
	
	// Set up a listener.
	Keyy.register_listener('login', function(response, login_token) {
		
		if (response.hasOwnProperty('state') && 'claimed' == response.state) {
			var $login_form;
			
			if (response.hasOwnProperty('form_id') && response.form_id) {
				$login_form = $('.keyy_qrcode[data-keyy_form_identifier="'+response.form_id+'"]').closest('form');
			} else {
				$login_form = $('form .keyy_qrcode').first().closest('form');
			}
			
			if ($login_form.length < 1) {
				alert(keyy.login_form_not_found);
				console.log(response);
				return;
			}
			
			$login_form.find('input[name="log"], input[name="username"], input[name="affwp_user_login"]').val(response.user_login).prop('readonly', true);
			$login_form.find('input[type="checkbox"][name="rememberme"]').prop('checked', true);
			$login_form.prepend('<input type="hidden" name="keyy_token_id" value="'+login_token.value+'">');
			
			if (response.hasOwnProperty('password_policy') && 'required' == response.password_policy) {
				
				var password = $login_form.find('input[name="pwd"], input[name="password"], input[name="pass"]');
				
				if (password.length && !password.val()) {
				
					console.log("Keyy: user policy requires a password, and none yet entered. Will not auto-submit form.");
					
					$login_form.find('.keyy_qrcode').html(keyy.authorised_needs_password);
					
					// Stop polling
					return true;
					
				}
			}
			
			console.log("Keyy: submitting: token_id="+login_token.value+" user_login="+response.user_login);
			$login_form.submit();
			
			// Stop polling.
			return true;
		} else {
			if (!response.hasOwnProperty('state') || 'unused' != response.state) {
				// The app should show them a problem if there is one, so no need to also show in the UI here.
				console.log("Keyy: login listener responded, but not for a login:");
				console.log(response);
			}
		}
	});
	
}
