<?php
// error message displayed on failure of payment request
// replaces confirmation text

if (!defined('ABSPATH')) {
	exit;
}
?>
<?php echo $anchor; ?>
<div id="gform_confirmation_wrapper_<?php echo esc_attr($form['id']); ?>" class="gform_confirmation_wrapper <?php echo esc_attr($cssClass); ?>">
	<div id="gform_confirmation_message_<?php echo esc_attr($form['id']); ?>" class="gform_confirmation_message_<?php echo esc_attr($form['id']); ?> gform_confirmation_message">
	<p><strong><?php _e('heidelpay payment request error', 'gf-heidelpay'); ?></strong></p>
	<?php echo $error_msg; ?>
	</div>
</div>
