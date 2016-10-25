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
			$links[] = sprintf('<a href="https://translate.webaware.com.au/projects/gravityforms-heidelpay" target="_blank">%s</a>', esc_html_x('Translate', 'plugin details links', 'gravityforms-heidelpay'));
		}

		return $links;
	}

}
