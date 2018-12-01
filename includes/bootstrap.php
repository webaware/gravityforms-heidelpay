<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}

/**
* kick start the plugin
*/
add_action('plugins_loaded', function() {
	require GFHEIDELPAY_PLUGIN_ROOT . 'includes/functions.php';
	require GFHEIDELPAY_PLUGIN_ROOT . 'includes/class.GFHeidelpayPlugin.php';
	Plugin::getInstance()->pluginStart();
}, 5);

/**
* autoload classes as/when needed
* @param string $class_name name of class to attempt to load
*/
spl_autoload_register(function($class_name) {
	static $classMap = [
		'Credentials'				=> 'includes/class.GFHeidelpayCredentials.php',
		'HeidelpayAPI'				=> 'includes/class.GFHeidelpayPayment.php',
		'Response'					=> 'includes/class.GFHeidelpayResponse.php',
		'ResponseCallback'			=> 'includes/class.GFHeidelpayResponseCallback.php',
		'ResponseSharedPage'		=> 'includes/class.GFHeidelpayResponseSharedPage.php',
	];

	if (strpos($class_name, __NAMESPACE__) === 0) {
		$class_name = substr($class_name, strlen(__NAMESPACE__) + 1);

		if (isset($classMap[$class_name])) {
			require GFHEIDELPAY_PLUGIN_ROOT . $classMap[$class_name];
		}
	}
});
