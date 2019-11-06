<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}

class SchemaUpgrade {

	protected $addon;

	public function __construct($addon) {
		$this->addon = $addon;

		$schema_version = get_plugin_schema_version();

		if ($schema_version < 1) {
			$this->update_1();
		}
	}

	/**
	* version 1 schema updates
	*/
	protected function update_1() {
		// fetch feeds with manually-set delay[A-Z] options and update to modern option names
		$slug = $this->addon->get_slug();
		$active = \GFAPI::get_feeds(null, null, $slug, $is_active = true);
		$inactive = \GFAPI::get_feeds(null, null, $slug, $is_active = false);
		$feeds = array_merge((array) $active, (array) $inactive);

		foreach ($feeds as $feed) {
			if (isset($feed['meta']['delayPost'])) {
				// upgrade meta names
				$feed['meta']['delay_post']								= !empty($feed['meta']['delayPost']);
				$feed['meta']['delay_gravityformsmailchimp']			= !empty($feed['meta']['delayMailchimp']);
				$feed['meta']['delay_gravityformsuserregistration']		= !empty($feed['meta']['delayUserrego']);

				// remove old meta names
				unset($feed['meta']['delayPost']);
				unset($feed['meta']['delayMailchimp']);
				unset($feed['meta']['delayUserrego']);

				\GFAPI::update_feed($feed['id'], $feed['meta']);
			}
		}

		set_plugin_schema_version(1);
	}

}
