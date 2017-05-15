<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="error">
	<p><?php printf(__('Gravity Forms heidelpay requires <a target="_blank" href="%1$s">Gravity Forms</a> version %2$s or higher; your website has Gravity Forms version %3$s', 'gf-heidelpay'),
		'https://webaware.com.au/get-gravity-forms', esc_html(GFHeidelpayPlugin::MIN_VERSION_GF), esc_html(GFCommon::$version)); ?></p>
</div>
