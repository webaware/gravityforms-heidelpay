<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<select name="payment_status">
	<option value="<?php echo esc_attr($content); ?>" selected="selected"><?php echo esc_html($content); ?></option>
	<option value="Paid">Paid</option>
	<option value="Failed">Failed</option>
</select>

