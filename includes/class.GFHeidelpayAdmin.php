<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
* class for admin screens
*/
class GFHeidelpayAdmin {

	public function __construct() {
		add_action('admin_notices', array($this, 'checkPrerequisites'));
		add_filter('plugin_row_meta', array($this, 'pluginDetailsLinks'), 10, 2);
	}

	/**
	* check for required prerequisites, tell admin if any are missing
	*/
	public function checkPrerequisites() {
		// only on specific pages
		global $pagenow;
		if ($pagenow !== 'plugins.php' && !self::isGravityFormsPage()) {
			return;
		}

		// only bother admins / plugin installers / option setters with this stuff
		if (!current_user_can('activate_plugins') && !current_user_can('manage_options')) {
			return;
		}

		// need at least PHP 5.2.11 for libxml_disable_entity_loader()
		$php_min = '5.2.11';
		if (version_compare(PHP_VERSION, $php_min, '<')) {
			include GFHEIDELPAY_PLUGIN_ROOT . 'views/requires-php.php';
		}

		// need these PHP extensions too
		// NB: libxml / SimpleXML used for version update functions
		$prereqs = array('libxml', 'pcre', 'SimpleXML', 'xmlwriter');
		$missing = array();
		foreach ($prereqs as $ext) {
			if (!extension_loaded($ext)) {
				$missing[] = $ext;
			}
		}
		if (!empty($missing)) {
			include GFHEIDELPAY_PLUGIN_ROOT . 'views/requires-extensions.php';
		}

		// and PCRE needs to be v8+ or we break! e.g. \K not present until v7.2 and some sites still use v6.6!
		//~ $pcre_min = '8';
		//~ if (defined('PCRE_VERSION') && version_compare(PCRE_VERSION, $pcre_min, '<')) {
			//~ include GFHEIDELPAY_PLUGIN_ROOT . 'views/requires-pcre.php';
		//~ }

		// and of course, we need Gravity Forms
		if (!class_exists('GFCommon', false)) {
			include GFHEIDELPAY_PLUGIN_ROOT . 'views/requires-gravity-forms.php';
		}
		elseif (!GFHeidelpayPlugin::hasMinimumGF()) {
			include GFHEIDELPAY_PLUGIN_ROOT . 'views/requires-gravity-forms-upgrade.php';
		}
	}

	/**
	* test if admin page is a Gravity Forms page
	* @return bool
	*/
	protected static function isGravityFormsPage() {
		$is_gf = false;
		if (class_exists('GFForms', false)) {
			$is_gf = !!(GFForms::get_page());
		}

		return $is_gf;
	}

	/**
	* action hook for adding plugin details links
	*/
	public function pluginDetailsLinks($links, $file) {
		if ($file === GFHEIDELPAY_PLUGIN_NAME) {
			//~ $links[] = sprintf('<a href="https://gfheidelpay.webaware.net.au/" target="_blank">%s</a>', esc_html_x('Instructions', 'plugin details links', 'gravityforms-heidelpay'));
			//~ $links[] = sprintf('<a href="https://shop.webaware.com.au/support/?for=Support+--+Gravity+Forms+heidelpay" target="_blank">%s</a>', esc_html_x('Get Help', 'plugin details links', 'gravityforms-heidelpay'));
			$links[] = sprintf('<a href="https://translate.webaware.com.au/projects/gravityforms-heidelpay" target="_blank">%s</a>', esc_html_x('Translate', 'plugin details links', 'gravityforms-heidelpay'));
		}

		return $links;
	}

}
