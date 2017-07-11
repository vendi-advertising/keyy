<?php if (!defined('KEYY_DIR')) die('No direct access allowed'); ?>

<div class="updraft-ad-container updated">
	<div class="updraft_notice_container">
		<div class="updraft_advert_content_left">
			<img src="<?php echo KEYY_URL.'/images/'.$image; ?>" width="60" height="60" alt="<?php esc_attr_e('notice image', 'keyy'); ?>" />
		</div>
		<div class="updraft_advert_content_right">
			<h3 class="updraft_advert_heading">
				<?php
				if (!empty($prefix)) echo $prefix.' ';
					echo $title;
				?>
				<div class="updraft-advert-dismiss">
				<?php if (!empty($dismiss_time)) { ?>
					<a href="#" onclick="jQuery('.updraft-ad-container').slideUp(); jQuery.post(ajaxurl, {action: 'keyy_ajax', subaction: '<?php echo $dismiss_time; ?>', nonce: '<?php echo wp_create_nonce('keyy-ajax-nonce'); ?>' });"><?php _e('Dismiss', 'keyy'); ?></a>
				<?php } else { ?>
					<a href="#" onclick="jQuery('.updraft-ad-container').slideUp();"><?php _e('Dismiss', 'keyy'); ?></a>
				<?php } ?>
				</div>
			</h3>
			<p>
				<?php
				echo $text;

					if (isset($discount_code)) echo ' <b>' . $discount_code . '</b>';

					if (!empty($button_link) && !empty($button_meta)) {
					// Check which Message is going to be used.
					if ('updraftcentral' == $button_meta) {
						$button_text = __('Get UpdraftCentral', 'keyy');
					} elseif ('review' == $button_meta) {
						$button_text = __('Review Keyy', 'keyy');
					} elseif ('updraftplus' == $button_meta) {
						$button_text = __('Get UpdraftPlus', 'keyy');
					} elseif ('signup' == $button_meta) {
						$button_text = __('Sign up', 'keyy');
					} elseif ('go_there' == $button_meta) {
						$button_text = __('Go there', 'keyy');
					} elseif ('keyy_premium' == $button_meta) {
						$button_text = __('Buy or find out more.', 'keyy');
					}
					$keyy->keyy_url($button_link, $button_text, null, 'class="updraft_notice_link"');
					}
				?>
			</p>
		</div>
	</div>
	<div class="clear"></div>
</div>
