<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
* automatic updates
*/
class GFHeidelpayUpdates {

	const TRANSIENT_UPDATE_INFO		= 'gravityforms-heidelpay-update_info';
	const URL_UPDATE_INFO			= 'https://www.dropbox.com/s/0aho2hjvz9gz23t/gravityforms-heidelpay.json?dl=1';

	public function __construct() {
		$this->name = GFHEIDELPAY_PLUGIN_NAME;
		$this->slug = 'gravityforms-heidelpay';

		// check for plugin updates
		add_action('admin_init', array($this, 'maybeShowChangelog'));
		add_filter('pre_set_site_transient_update_plugins', array($this, 'checkPluginUpdates'));
		add_filter('plugins_api', array($this, 'getPluginInfo'), 10, 3);
		add_action('plugins_loaded', array($this, 'clearPluginInfo'));
		add_filter('plugin_row_meta', array($this, 'plugin_details_link'), 5, 2);

		// on multisite, must add new version notification ourselves...
		if (is_multisite() && !is_network_admin()) {
			add_action('after_plugin_row_' . $this->name, array($this, 'showUpdateNotification'), 10, 2);
		}
	}

	/**
	* check for plugin updates, every so often
	* @param object $plugins
	* @return object
	*/
	public function checkPluginUpdates($plugins) {
		if (empty($plugins->last_checked)) {
			return $plugins;
		}

		$current = $this->getPluginData();
		$latest = $this->getLatestVersionInfo();

		if ($latest && version_compare($current['Version'], $latest->version, '<')) {
			$update = new stdClass;
			$update->id				= '0';
			$update->url			= $latest->homepage;
			$update->slug			= $latest->slug;
			$update->version		= $latest->version;
			$update->new_version	= $latest->new_version;
			$update->package		= $latest->download_link;
			$update->author			= $latest->author;
			$update->contributors	= $latest->contributors;

			// duplicate version member for plugin info pop-up
			$update->version		= $update->new_version;

			// convert array of contributors' names into keyed array of contributors' profile links
			if (!empty($update->contributors) && is_array($update->contributors)) {
				reset($update->contributors);
				if (is_int(key($update->contributors))) {
					$contributors = array();
					foreach ($update->contributors as $contributor) {
						$contributor = sanitize_user($contributor);
						$contributors[$contributor] = "https://profiles.wordpress.org/$contributor";
					}
					$update->contributors = $contributors;
				}
			}

			$plugins->response[$this->name] = $update;
		}

		return $plugins;
	}

	/**
	* return plugin info for update pages, plugins list
	* @param boolean $false
	* @param array $action
	* @param object $args
	* @return bool|object
	*/
	public function getPluginInfo($false, $action, $args) {
		if (isset($args->slug) && $args->slug === basename($this->name, '.php') && $action === 'plugin_information') {
			return $this->getLatestVersionInfo();
		}

		return $false;
	}

	/**
	* if user asks to force an update check, clear our cached plugin info
	*/
	public function clearPluginInfo() {
		global $pagenow;

		if (!empty($_GET['force-check']) && !empty($pagenow) && $pagenow === 'update-core.php') {
			delete_site_transient(self::TRANSIENT_UPDATE_INFO);
		}
	}

	/**
	* show update nofication row -- needed for multisite subsites, because WP won't tell you otherwise!
	* @param string $file
	* @param array $plugin
	*/
	public function showUpdateNotification($file, $plugin) {
		if (!current_user_can('update_plugins')) {
			return;
		}

		$update_cache = get_site_transient('update_plugins');
		if (!is_object($update_cache)) {
			// refresh update info
			wp_update_plugins();
		}

		$current = $this->getPluginData();
		$info = $this->getLatestVersionInfo();

		if ($info && version_compare($current['Version'], $info->new_version, '<')) {
			// build a plugin list row, with update notification
			$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
			$plugin_name   = esc_html( $info->name );
			$plugin_slug   = esc_html( $info->slug );
			$new_version   = esc_html( $info->new_version );

			include GFHEIDELPAY_PLUGIN_ROOT . 'views/admin-plugin-update-upgrade.php';
		}
	}

	/**
	* maybe change the View Details link on the plugin page
	* @param array   $links
	* @param string  $file
	*/
	public function plugin_details_link( $links, $file ) {
		if ( $this->name === $file && is_multisite() && current_user_can( 'install_plugins' ) ) {

			// look for View Details link
			foreach ($links as $key => $link) {
				if (strpos($link, 'plugin-install.php?tab=plugin-information') !== false) {
					$links[$key] = preg_replace_callback('#href="\K[^"]+#', array($this, 'get_plugin_details_link'), $link);
					break;
				}
			}

		}

		return $links;
	}

	/**
	* get custom link to view plugin details
	* @return string
	*/
	public function get_plugin_details_link() {
		return self_admin_url("index.php?{$this->slug}_changelog=1&plugin={$this->slug}&slug={$this->slug}&TB_iframe=true");
	}

	/**
	* get current plugin data (cached so that we only ask once, because it hits the file system)
	* @return array
	*/
	protected function getPluginData() {
		if (empty($this->pluginData)) {
			$this->pluginData = get_plugin_data(GFHEIDELPAY_PLUGIN_FILE);
		}

		return $this->pluginData;
	}

	/**
	* get plugin version info from remote server
	* @param bool $cache set false to ignore the cache and fetch afresh
	* @return stdClass
	*/
	protected function getLatestVersionInfo($cache = true) {
		$info = false;
		if ($cache) {
			$info = get_site_transient(self::TRANSIENT_UPDATE_INFO);
		}

		if (empty($info)) {
			delete_site_transient(self::TRANSIENT_UPDATE_INFO);

			$url = add_query_arg(array('v' => time()), self::URL_UPDATE_INFO);
			$response = wp_remote_get($url, array('timeout' => 60));

			if (is_wp_error($response)) {
				return false;
			}

			if ($response && isset($response['body'])) {
				// load and decode JSON from response body
				$info = json_decode($response['body']);

				if ($info) {
					$sections = array();
					foreach ($info->sections as $name => $data) {
						$sections[$name] = $data;
					}
					$info->sections = $sections;

					set_site_transient(self::TRANSIENT_UPDATE_INFO, $info, HOUR_IN_SECONDS * 6);
				}
			}
		}

		return $info;
	}

	/**
	* maybe show the plugin changelog from update info
	*/
	public function maybeShowChangelog() {
		if (!empty($_REQUEST[$this->slug . '_changelog']) && !empty($_REQUEST['plugin']) && !empty($_REQUEST['slug'])) {
			if (!current_user_can('update_plugins')) {
				wp_die(translate('You do not have sufficient permissions to update plugins for this site.'), translate('Error'), array('response' => 403));
			}

			global $tab, $body_id;
			$body_id = $tab = 'plugin-information';
			$_REQUEST['section'] = 'changelog';

			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

			wp_enqueue_style('plugin-install');
			wp_enqueue_script('plugin-install');
			set_current_screen();
			install_plugin_information();

			exit;
		}
	}

}
