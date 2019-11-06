<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}

// minimum versions required
const MIN_VERSION_GF		= '2.0';

// current data version
const SCHEMA_VERSION					= 1;
const SCHEMA_VERSION_OPTION				= 'gfheidelpay_schema';

// entry meta keys
const META_TRANSACTION_ID				= 'heidelpay_txn_id';
const META_SHORT_ID						= 'heidelpay_short_id';
const META_RETURN_CODE					= 'heidelpay_return_code';
const META_FEED_ID						= 'heidelpay_feed_id';
const META_UNIQUE_ID					= 'heidelpay_unique_id';

// end points for return to website
const ENDPOINT_CONFIRMATION				= '__gfheidelpay';

/**
* custom exception types
*/
class GFHeidelpayException extends \Exception {}
class GFHeidelpayCurlException extends \Exception {}

/**
* kick start the plugin
*/
add_action('plugins_loaded', function() {
	require GFHEIDELPAY_PLUGIN_ROOT . 'includes/functions.php';
	require GFHEIDELPAY_PLUGIN_ROOT . 'includes/class.Plugin.php';
	Plugin::getInstance()->pluginStart();
}, 5);

/**
* autoload classes as/when needed
* @param string $class_name name of class to attempt to load
*/
spl_autoload_register(function($class_name) {
	static $classMap = [
		'Credentials'				=> 'includes/class.Credentials.php',
		'HeidelpayAPI'				=> 'includes/class.Payment.php',
		'Response'					=> 'includes/class.Response.php',
		'ResponseCallback'			=> 'includes/class.ResponseCallback.php',
		'ResponseSharedPage'		=> 'includes/class.ResponseSharedPage.php',
	];

	if (strpos($class_name, __NAMESPACE__) === 0) {
		$class_name = substr($class_name, strlen(__NAMESPACE__) + 1);

		if (isset($classMap[$class_name])) {
			require GFHEIDELPAY_PLUGIN_ROOT . $classMap[$class_name];
		}
	}
});
