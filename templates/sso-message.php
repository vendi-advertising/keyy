<?php if (!defined('KEYY_DIR')) die('No direct access.'); ?>

<div id="keyy-sso-offer">

	<strong><?php _e('Keyy: Single sign-on', 'keyy'); ?></strong><br>

	<?php echo sprintf(__('To begin a Keyy single sign-on session, %s.', 'keyy'), '<a href="#" id="keyy-sso-begin-session">'.__('press here', 'keyy').'</a>'); ?>
	
	<?php echo sprintf(__('Or, to learn more about single sign-on, %s.', 'keyy'), '<a href="'.$sso_information.'">'.__('go here', 'keyy').'</a>'); ?>
	
</div>
