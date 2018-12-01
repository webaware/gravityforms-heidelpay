<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}

/**
* class for managing the plugin
*/
class Plugin {

	/**
	* static method for getting the instance of this singleton object
	* @return self
	*/
	public static function getInstance() {
		static $instance = null;

		if ($instance === null) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	* hide constructor
	*/
	private function __construct() {}

	/**
	* initialise plugin
	*/
	public function pluginStart() {
		add_action('gform_loaded', [$this, 'addonInit']);
		add_action('init', 'gf_heidelpay_load_text_domain', 8);	// use priority 8 to get in before our add-on uses translated text
		add_action('admin_notices', [$this, 'checkPrerequisites']);
		add_filter('plugin_row_meta', [$this, 'pluginDetailsLinks'], 10, 2);
	}

	/**
	* initialise the Gravity Forms add-on
	*/
	public function addonInit() {
		if (!method_exists('GFForms', 'include_feed_addon_framework')) {
			return;
		}

		if (has_required_gravityforms()) {
			// load add-on framework and hook our add-on
			\GFForms::include_payment_addon_framework();

			require GFHEIDELPAY_PLUGIN_ROOT . 'includes/class.AddOn.php';
			\GFAddOn::register(__NAMESPACE__ . '\\AddOn');
		}
	}

	/**
	* check for required prerequisites, tell admin if any are missing
	*/
	public function checkPrerequisites() {
		if (!gf_heidelpay_can_show_admin_notices()) {
			return;
		}

		// of course, we need Gravity Forms
		if (!class_exists('GFCommon', false)) {
			include GFHEIDELPAY_PLUGIN_ROOT . 'views/requires-gravity-forms.php';
		}
		elseif (!has_required_gravityforms()) {
			include GFHEIDELPAY_PLUGIN_ROOT . 'views/requires-gravity-forms-upgrade.php';
		}
	}

	/**
	* action hook for adding plugin details links
	*/
	public function pluginDetailsLinks($links, $file) {
		if ($file === GFHEIDELPAY_PLUGIN_NAME) {
			$links[] = sprintf('<a href="https://wordpress.org/support/plugin/gf-heidelpay" rel="noopener" target="_blank">%s</a>', esc_html_x('Get help', 'plugin details links', 'gf-heidelpay'));
			$links[] = sprintf('<a href="https://wordpress.org/plugins/gf-heidelpay/" rel="noopener" target="_blank">%s</a>', esc_html_x('Rating', 'plugin details links', 'gf-heidelpay'));
			$links[] = sprintf('<a href="https://translate.wordpress.org/projects/wp-plugins/gf-heidelpay" rel="noopener" target="_blank">%s</a>', esc_html_x('Translate', 'plugin details links', 'gf-heidelpay'));
			$links[] = sprintf('<a href="https://shop.webaware.com.au/donations/?donation_for=Gravity+Forms+heidelpay" rel="noopener" target="_blank">%s</a>', esc_html_x('Donate', 'plugin details links', 'gf-heidelpay'));
		}

		return $links;
	}

}
