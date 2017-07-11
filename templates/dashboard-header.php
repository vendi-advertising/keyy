<?php if (!defined('KEYY_DIR')) die('No direct access.'); ?>

<div id="keyy-admin-header">

	<h1>Keyy - <?php echo ('admin' == $which_page) ? __('WordPress logins made easy', 'keyy') : __('WordPress logins made easy', 'keyy').' - '.__('Site administration', 'keyy'); ?></h1>

	<?php echo $home_page; ?> | 
	<a href="<?php echo $keyy_premium_shop;?>"><?php _e('Premium', 'keyy'); ?></a> | 
	<a href="https://updraftplus.com/news/"><?php _e('News', 'keyy'); ?></a> | 
	<a href="https://twitter.com/updraftplus"><?php _e('Twitter', 'keyy'); ?></a> | 
	<a href="<?php echo $support; ?>"><?php _e('Support', 'keyy'); ?></a> | 
	<a href="https://updraftplus.com/newsletter-signup"><?php _e('Newsletter', 'keyy'); ?></a> | 
	<a href="https://david.dw-perspective.org.uk"><?php _e("Lead developer's homepage", 'keyy'); ?></a> | 
	<a href="<?php echo $faqs; ?>">FAQs</a> | <a href="<?php echo $simba_plugins_landing; ?>"><?php _e('More plugins', 'keyy'); ?></a> - <?php _e('Version', 'keyy'); ?>: <?php echo $keyy::VERSION; ?>
	<br>
	
</div>

<?php
	$keyy_notices->do_notice();
	
	do_action('keyy_dashboard_header_after_notice');
