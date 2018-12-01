<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="notice notice-error">
	<p>
		<?= gf_heidelpay_external_link(
			sprintf(__('Gravity Forms heidelpay requires {{a}}Gravity Forms{{/a}} version %1$s or higher; your website has Gravity Forms version %2$s', 'gf-heidelpay'),
				esc_html(MIN_VERSION_GF), esc_html(\GFCommon::$version)),
			'https://webaware.com.au/get-gravity-forms'
		); ?>
	</p>
</div>
