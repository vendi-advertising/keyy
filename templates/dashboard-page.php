<?php

if (!defined('KEYY_DIR')) die('No direct access.');

$is_configured_for_user = $keyy_login->is_configured_for_user();

$settings = $keyy_login->get_user_settings();

?>

<div id="keyy-admin-body-container">

	<div id="keyy-admin-body">

		<div class="half-width-box" style="clear:left;">

		<?php if ($is_configured_for_user) { ?>
		
			<?php
				$email = isset($settings['email']) ? $settings['email'] : __('Unknown', 'keyy');
			?>
			
			<div style="float:right;">
				<span id="keyy_save_done" class="dashicons dashicons-yes display-none"></span>
				<img id="keyy_save_spinner" class="keyy_spinner" src="<?php echo esc_attr(admin_url('images/spinner-2x.gif')); ?>" alt="...">
			</div>
		
			<h2><?php _e('You are connected to Keyy', 'keyy'); ?></h2>
			
			<p>
			<?php
			echo sprintf(__('This user is connected to the Keyy account belonging to %s.', 'keyy'), htmlspecialchars($email)).' ';
			?>
			</p>
			
			<?php include('user-settings.php'); ?>
			
			<p>
				<?php echo apply_filters('keyy_dashboard_connection_implications', __('As a result, your password is inactive - all logging-in for this account is now through your Keyy app.', 'keyy')); ?>
			</p>
			
			<button id="keyy_disconnect" class="button button-primary"><?php _e('Disconnect from my Keyy app', 'keyy'); ?></button>
			
		<?php } else { ?>

			<h2><?php _e('Got the app? Scan this code to connect.', 'keyy'); ?></h2>
			
			<div id="keyy_connect_qrcode" class="keyy_qrcode"></div>
			
			<p>
				<?php _e('Scan this code with the Keyy app on your phone.', 'keyy'); ?>
			</p>
			
		<?php } ?>
		
		</div>
		
		<div id="keyy-second-box" class="half-width-box">
		
		<?php
		if ($is_configured_for_user) {
			include('dashboard-already-connected.php');
		} else {
		?>
		
			<h2><?php _e('First-time user? Get the app.', 'keyy'); ?></h2>

			
			<a href="<?php echo $android_app; ?>"><img src="<?php echo KEYY_URL.'/images/play-store-button.png'; ?>" width="165" height="55" title="<?php esc_attr_e('Keyy Android app', 'keyy'); ?>"></a>
			
			<a href="<?php echo $ios_app; ?>"><img src="<?php echo KEYY_URL.'/images/apple-store-button.png'; ?>" width="165" height="55" title="<?php esc_attr_e('Keyy iOS (iPhone, iPad) app', 'keyy'); ?>"></a>
			
			<p>
				<?php
				echo htmlspecialchars(__('Use the above buttons to install the Keyy app on your phone or tablet. Or, search for "Keyy" in the store on the device.', 'keyy')).' '.__('Run the app, and it will create your account.', 'keyy');
				?>
			</p>
			
			<?php
			if ($keyy->url_looks_internal(home_url())) {
				echo '<strong>'.__('N.B. This website looks like a localhost install (i.e. no incoming networking from the outside world). These are not supported by this early release of Keyy.', 'keyy').' <a href="'.$upcoming_features.'">'.__('We have plans, so please look out for updates!', 'keyy').'</a></strong>';
			} else {
				echo '<strong>'.'<a href="'.$upcoming_features.'">'.__('Learn about new features and improvements we are currently working on for our next releases, here.', 'keyy').'</a></strong>';
			}
			?>
			
		<?php } ?>
		
		</div>
		
	</div>
	
</div>

<?php if (current_user_can($keyy->capability_required('admin')) && !empty($disable_url)) { ?>

	<p class="clear-left">
		<?php echo sprintf(__('URL to login without Keyy (%s): %s', 'keyy'), '<a href="'.$faq_how_to_disable.'">'.__('read more', 'keyy').'</a>', '<br><a href="'.$disable_url.'">'.$disable_url.'</a>'); ?>
	</p>

<?php }
