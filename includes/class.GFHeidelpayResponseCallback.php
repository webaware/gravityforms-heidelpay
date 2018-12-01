<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}

/**
* extend gateway response for Shared Page request
*/
class GFHeidelpayResponseCallback extends GFHeidelpayResponse {

	#region members

	/**
	* request version
	* @var string
	*/
	public $REQUEST_VERSION;

	/**
	* URL for the shared payment page, including query parameters
	* @var string
	*/
	public $FRONTEND_RESPONSE_URL;

	/**
	* whether request was cancelled by customer
	* @var boolean
	*/
	public $FRONTEND_REQUEST_CANCELLED;

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
	* processing code
	* @var string
	*/
	public $PROCESSING_CODE;

	/**
	* reason code
	* @var string
	*/
	public $PROCESSING_REASON_CODE;

	/**
	* reason as text
	* @var string
	*/
	public $PROCESSING_REASON;

	/**
	* processing result
	* @var string
	*/
	public $PROCESSING_RESULT;

	/**
	* processing result code
	* @var string
	*/
	public $PROCESSING_RETURN_CODE;

	/**
	* processing result as text
	* @var string
	*/
	public $PROCESSING_RETURN;

	/**
	* processing status code
	* @var string
	*/
	public $PROCESSING_STATUS_CODE;

	/**
	* processing status
	* @var string
	*/
	public $PROCESSING_STATUS;

	/**
	* processing time YYYY-MM-DD hh:mm:ss
	* @var DateTime
	*/
	public $PROCESSING_TIMESTAMP;

	/**
	* link to original transaction
	* @var string
	*/
	public $IDENTIFICATION_TRANSACTIONID;

	/**
	* invoice reference
	* @var string
	*/
	public $IDENTIFICATION_INVOICEID;

	/**
	* gateway short ID for transaction
	* @var string
	*/
	public $IDENTIFICATION_SHORTID;

	/**
	* gateway unique ID for transaction
	* @var string
	*/
	public $IDENTIFICATION_UNIQUEID;

	/**
	* amount as sent to gateway
	* @var float
	*/
	public $PRESENTATION_AMOUNT;

	/**
	* currency as sent to gateway
	* @var string
	*/
	public $PRESENTATION_CURRENCY;

	/**
	* descripton as sent to gateway
	* @var string
	*/
	public $PRESENTATION_USAGE;

	/**
	* settlement amount at gateway
	* @var float
	*/
	public $CLEARING_AMOUNT;

	/**
	* currency of settlement amount at gateway
	* @var string
	*/
	public $CLEARING_CURRENCY;

	/**
	* date of settlement at gateway
	* @var DateTime
	*/
	public $CLEARING_DATE;

	/**
	* processing descriptive text, appears on end customer's statement
	* @var string
	*/
	public $CLEARING_DESCRIPTOR;

	/**
	* processor support contact details
	* @var string
	*/
	public $CLEARING_SUPPORT;

	#endregion

	/**
	* load gateway response data
	* @param string|array $response_raw gateway response
	* @throws GFHeidelpayException
	*/
	public function loadResponse($response_raw) {
		parent::loadResponse($response_raw);

		// handle type conversions
		$this->FRONTEND_REQUEST_CANCELLED	= strcasecmp($this->FRONTEND_REQUEST_CANCELLED, 'true') === 0;
		$this->PRESENTATION_AMOUNT			= (float) $this->PRESENTATION_AMOUNT;
	}

	/**
	* get processing message
	* @return string
	*/
	public function getProcessingMessages() {
		return array($this->PROCESSING_RETURN, $this->CLEARING_DESCRIPTOR);
	}

	/**
	* get 'invalid response' message for this response class
	* @return string
	*/
	protected function getMessageInvalid() {
		return __('Invalid response from heidelpay for Shared Page callback', 'gf-heidelpay');
	}

}
