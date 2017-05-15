<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
* custom exception types
*/
class GFHeidelpayException extends Exception {}
class GFHeidelpayCurlException extends Exception {}

/**
* class for managing the plugin
*/
class GFHeidelpayPlugin {

	// minimum versions required
	const MIN_VERSION_GF		= '2.0';

	/**
	* static method for getting the instance of this singleton object
	* @return self
	*/
	public static function getInstance() {
		static $instance = null;

		if (is_null($instance)) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	* initialise plugin
	*/
	private function __construct() {
		spl_autoload_register(array(__CLASS__, 'autoload'));

		add_action('gform_loaded', array($this, 'addonInit'));
		add_action('init', array($this, 'loadTextDomain'));

		if (is_admin()) {
			require GFHEIDELPAY_PLUGIN_ROOT . 'includes/class.GFHeidelpayAdmin.php';
			new GFHeidelpayAdmin();
		}
	}

	/**
	* initialise the Gravity Forms add-on
	*/
	public function addonInit() {
		if (!method_exists('GFForms', 'include_feed_addon_framework')) {
			return;
		}

		if (self::hasMinimumGF()) {
			// load add-on framework and hook our add-on
			GFForms::include_payment_addon_framework();

			require GFHEIDELPAY_PLUGIN_ROOT . 'includes/class.GFHeidelpayAddOn.php';
			GFAddOn::register('GFHeidelpayAddOn');

			// no need to load text domain now, Gravity Forms will do it for us
			remove_action('init', array($this, 'loadTextDomain'));
		}
	}

	/**
	* load text translations
	* Gravity Forms loads text domain for add-ons, so this won't be called if the add-on was registered
	*/
	public function loadTextDomain() {
		load_plugin_textdomain('gravityforms-heidelpay', false, plugin_basename(dirname(GFHEIDELPAY_PLUGIN_FILE)) . '/languages/');
	}

	/**
	* compare Gravity Forms version against target
	* @param string $target
	* @param string $operator
	* @return bool
	*/
	public static function versionCompareGF($target, $operator) {
		if (class_exists('GFCommon', false)) {
			return version_compare(GFCommon::$version, $target, $operator);
		}

		return false;
	}

	/**
	* compare Gravity Forms version against minimum required version
	* @return bool
	*/
	public static function hasMinimumGF() {
		return self::versionCompareGF(self::MIN_VERSION_GF, '>=');
	}

	/**
	* autoload classes as/when needed
	* @param string $class_name name of class to attempt to load
	*/
	public static function autoload($class_name) {
		static $classMap = array (
			'GFHeidelpayCredentials'				=> 'includes/class.GFHeidelpayCredentials.php',
			'GFHeidelpayPayment'					=> 'includes/class.GFHeidelpayPayment.php',
			'GFHeidelpayResponse'					=> 'includes/class.GFHeidelpayResponse.php',
			'GFHeidelpayResponseCallback'			=> 'includes/class.GFHeidelpayResponseCallback.php',
			'GFHeidelpayResponseSharedPage'			=> 'includes/class.GFHeidelpayResponseSharedPage.php',
		);

		if (isset($classMap[$class_name])) {
			require GFHEIDELPAY_PLUGIN_ROOT . $classMap[$class_name];
		}
	}

}
