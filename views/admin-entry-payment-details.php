<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<?php if ($short_id): ?>
<div class="gf_payment_detail">
	<?= esc_html_x('Short ID:', 'entry details', 'gf-heidelpay') ?>
	<span id="gfheidelpay_short_id"><?= esc_html($short_id); ?></span>
</div>
<?php endif; ?>

<?php if ($return_code): ?>
<div class="gf_payment_detail">
	<?= esc_html_x('Return Code:', 'entry details', 'gf-heidelpay') ?>
	<span id="gfheidelpay_return_code"><?= esc_html($return_code); ?></span>
</div>
<?php endif; ?>

