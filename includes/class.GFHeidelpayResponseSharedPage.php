<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
* extend gateway response for Shared Page request
*/
class GFHeidelpayResponseSharedPage extends GFHeidelpayResponse {

	#region members

	/**
	* request version
	* @var string
	*/
	public $REQUEST_VERSION;

	/**
	* redirect URL for the shared payment page, including query parameters
	* @var string
	*/
	public $FRONTEND_REDIRECT_URL;

	/**
	* post validation code
	* @var string
	*/
	public $POST_VALIDATION;

	/**
	* P3 validation code
	* @var string
	*/
	public $P3_VALIDATION;

	/**
	* link to original transaction
	* @var string
	*/
	public $IDENTIFICATION_TRANSACTIONID;

	#endregion

	/**
	* get 'invalid response' message for this response class
	* @return string
	*/
	protected function getMessageInvalid() {
		return __('Invalid response from heidelpay for Shared Page request', 'gf-heidelpay');
	}

}
