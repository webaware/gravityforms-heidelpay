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
			$links[] = sprintf('<a href="https://wordpress.org/support/plugin/gf-heidelpay" target="_blank">%s</a>', _x('Get help', 'plugin details links', 'gf-heidelpay'));
			$links[] = sprintf('<a href="https://wordpress.org/plugins/gf-heidelpay/" target="_blank">%s</a>', _x('Rating', 'plugin details links', 'gf-heidelpay'));
			$links[] = sprintf('<a href="https://translate.wordpress.org/projects/wp-plugins/gf-heidelpay" target="_blank">%s</a>', _x('Translate', 'plugin details links', 'gf-heidelpay'));
			$links[] = sprintf('<a href="https://shop.webaware.com.au/donations/?donation_for=Gravity+Forms+heidelpay" target="_blank">%s</a>', _x('Donate', 'plugin details links', 'gf-heidelpay'));
		}

		return $links;
	}

}
