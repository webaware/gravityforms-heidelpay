<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}

/**
* gateway credentials logic
*/
class GFHeidelpayCredentials {

	public $sender;
	public $login;
	public $password;
	public $channel;

	// 3D-secure test channel credentials
	const TEST_SENDER_3D		= '31HA07BC8124AD82A9E96D9A35FAFD2A';
	const TEST_LOGIN_3D			= '31ha07bc8124ad82a9e96d486d19edaa';
	const TEST_PASSWORD_3D		= 'password';
	const TEST_CHANNEL_OT_3D	= '31HA07BC81A71E2A47DA94B6ADC524D8';

	// non-3D-secure test channel credentials
	const TEST_SENDER			= '31HA07BC810C91F08643A5D477BDD7C0';
	const TEST_LOGIN			= '31ha07bc810c91f086431f7471d042d6';
	const TEST_PASSWORD			= 'password';
	const TEST_CHANNEL_OT		= '31HA07BC810C91F086433734258F6628';

	/**
	* set gateway credentials for selected feed
	* @param GFPaymentAddOn $addon
	* @param array $feed
	*/
	public function __construct($addon, $feed) {
		if (empty($feed['meta']['useTest'])) {
			// get defaults from add-on settings
			$this->sender			= $addon->get_plugin_setting('sender');
			$this->login			= $addon->get_plugin_setting('login');
			$this->password			= $addon->get_plugin_setting('password');
			$this->channel			= $addon->get_plugin_setting('channel_OT');
		}
		else {
			// single sandbox for all! only differentiated by 3D secure and channel
			if (rgar($feed['meta'], 'test_3D_secure')) {
				// 3D-secure channel credentials
				$this->sender		= self::TEST_SENDER_3D;
				$this->login		= self::TEST_LOGIN_3D;
				$this->password		= self::TEST_PASSWORD_3D;
				$this->channel		= self::TEST_CHANNEL_OT_3D;
			}
			else {
				// non-3D-secure channel credentials
				$this->sender		= self::TEST_SENDER;
				$this->login		= self::TEST_LOGIN;
				$this->password		= self::TEST_PASSWORD;
				$this->channel		= self::TEST_CHANNEL_OT;
			}
		}

		// maybe override from feed settings
		if (!empty($feed['meta']['custom_connection'])) {
			if (!empty($feed['meta']['sender'])) {
				$this->sender = $feed['meta']['sender'];
			}

			if (!empty($feed['meta']['login'])) {
				$this->login = $feed['meta']['login'];
			}

			if (!empty($feed['meta']['password'])) {
				$this->password = $feed['meta']['password'];
			}

			if (!empty($feed['meta']['channel_id'])) {
				$this->channel = $feed['meta']['channel_id'];
			}
		}
	}

	/**
	* check for missing credentials
	* @return bool
	*/
	public function isIncomplete() {
		return empty($this->sender) || empty($this->login) || empty($this->password) || empty($this->channel);
	}

}
