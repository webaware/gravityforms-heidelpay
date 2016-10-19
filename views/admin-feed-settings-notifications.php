<?php
if (!defined('ABSPATH')) {
	exit;
}

// NB: these checkbox inputs are not meant to have input names, because they are not sent with form submission!
// they are used to record changes to selected delayed notifications via JSON in a hidden field
?>

<ul id="heidelpay-notification-container">
<?php foreach ($notifications as $notification) { ?>

	<li class="heidelpay-notification">
		<input type="checkbox" class="heidelpay-notification-checkbox" id="heidelpay-notification-<?php echo esc_attr($notification['id']); ?>"
			value="<?php echo esc_attr($notification['id']); ?>" <?php checked(!empty($selections[$notification['id']])); ?> />
		<label class="inline" for="heidelpay-notification-<?php echo esc_attr($notification['id']); ?>"><?php echo esc_html($notification['name']); ?></label>
	</li>

<?php } ?>
</ul>
