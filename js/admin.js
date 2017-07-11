jQuery(document).ready(function($) {
	
	var send_command = keyy_send_command_admin_ajax;
	
	$('#keyy-admin-body-container').on('change', 'input', function() {
		Keyy.save_settings('#keyy-admin-body', '#keyy_save_spinner', '#keyy_save_done');
	});
		
	/**
	 * Initial set up - display the QR code and listen for events
	 */
	function setup_page() {
		
		if (!Keyy.get_connection_status()) {
			Keyy.show_qr_code('#keyy_connect_qrcode', 'connect');
		}
		
		Keyy.register_listener('connect', function(resp) {
			
			console.log("Keyy: remote command received:");
			console.log(resp);
			
			if (!resp.hasOwnProperty('code') || !resp.hasOwnProperty('data') || !resp.data || !resp.data.hasOwnProperty('message')) {
				alert(keyy.error_unexpected_response);
				return;
			}
			
			if (resp && resp.hasOwnProperty('connection_token')) {
				Keyy.set_connection_token(resp.connection_token);
			}
			
			var code = resp.code;
			var message = resp.data.message;
			
			$('#keyy-admin-body').after('<div id="keyy_connected_message">'+message+'</div>');
				
			send_command('get_dashboard_page', null, function(response) {
				
				if (response && response.hasOwnProperty('html')) {
					$('#keyy-admin-body').fadeOut('slow', function() {
						$(this).replaceWith(response.html);
						if ($('#keyy_connect_qrcode').length > 0) {
							Keyy.show_qr_code('#keyy_connect_qrcode', 'connect');
						}
					});
					
					setTimeout(function() {
						$('#keyy_connected_message').slideUp('slow', function() {
											$(this).remove();
						});
					}, 5000);
				} else {
					console.log("Keyy: get_dashboard_page failed");
					console.log(response);
				}
			});
		});
	}
	
	setup_page();
	
	// Handle clicks on the 'disconnect' button.
	$('#keyy-admin-body-container').on('click', '#keyy_disconnect', function() {
		
		$('#keyy_disconnect').prop('disabled', true);
		
		send_command('disconnect', null, function(response) {
			
			$('#keyy_disconnect').prop('disabled', false);
			
			if (response && response.hasOwnProperty('connection_token')) {
				Keyy.set_connection_token(response.connection_token);
			}
			
			if (response && response.hasOwnProperty('dashboard_html')) {
				$('#keyy-admin-body').replaceWith(response.dashboard_html);
				Keyy.set_connection_status(false);
				Keyy.show_qr_code('#keyy_connect_qrcode', 'connect');
			} else {
				console.log("Keyy: disconnect: unexpected response:");
				console.log(response);
			}
		});
	});
});
