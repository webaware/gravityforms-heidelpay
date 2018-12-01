<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<select name="payment_status">
	<option value="<?= esc_attr($content); ?>" selected="selected"><?= esc_html($content); ?></option>
	<option value="Paid">Paid</option>
	<option value="Failed">Failed</option>
</select>

