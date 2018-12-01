<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="notice notice-error">
	<p>
		<?php echo gf_heidelpay_external_link(
				esc_html__('Gravity Forms heidelpay requires {{a}}Gravity Forms{{/a}} to be installed and activated.', 'gf-heidelpay'),
				'https://webaware.com.au/get-gravity-forms'
			); ?>
	</p>
</div>
