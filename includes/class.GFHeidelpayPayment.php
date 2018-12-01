<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}

/**
* Class for dealing with a heidelpay payment
*/
class GFHeidelpayPayment {

	#region "constants"

	// API hosts
	const API_HOST_LIVE						= 'https://heidelpay.hpcgw.net/sgw/gtwu';
	const API_HOST_SANDBOX					= 'https://test-heidelpay.hpcgw.net/sgw/gtwu';

	// payment methods
	const METHOD_CREDIT_CARD				= 'CC';
	const METHOD_DEBIT_CARD					= 'DC';
	const METHOD_DIRECT_DEBIT				= 'DD';
	const METHOD_INVOICE					= 'IV';
	const METHOD_PREPAYMENT					= 'PP';
	const METHOD_ONLINE_TRANSFER			= 'OT';
	const METHOD_VIRTUAL_ACCOUNT			= 'VA';
	const METHOD_PAYMENT_CARD				= 'PC';
	const METHOD_MOBILE_PAYMENT				= 'MP';

	// valid transaction types
	const TRANS_CODE_DEBIT					= 'DB';		// capture a debit transaction (i.e. charge the customer)
	const TRANS_CODE_CREDIT					= 'CD';		// capture a credit transaction (i.e. credit the customer)
	const TRANS_CODE_RESERVE				= 'PA';		// pre-authorisation (i.e. not capture)
	const TRANS_CODE_CAPTURE				= 'CP';		// i.e. capture a pre-authorisation

	#endregion // constants

	#region "members"

	#region "connection specific members"

	/**
	* use gateway sandbox
	* @var boolean
	*/
	public $useSandbox;

	/**
	* default TRUE, whether to validate the remote SSL/TLS certificate
	* @var boolean
	*/
	public $sslVerifyPeer;

	/**
	* heidelpay sender ID
	* @var string max. 32 characters
	*/
	public $sender;

	/**
	* gateway user ID max. 32 characters
	* @var string
	*/
	public $login;

	/**
	* gateway password max. 16 characters
	* @var string
	*/
	public $password;

	/**
	* heidelpay channel ID max. 32 characters
	* @var string
	*/
	public $channel;

	/**
	* HTTP user agent string identifying plugin, perhaps for debugging
	* @var string
	*/
	public $httpUserAgent;

	#endregion // "connection specific members"

	#region "payment specific members"

	/**
	* type of transaction, one of the TRANS_CODE_* values
	* @var boolean
	*/
	public $transactionType;

	/**
	* map of payment methods and card codes to 0 or 1
	* @var string
	*/
	public $enabledMethods;

	/**
	* a unique transaction number from your site
	* @var string max. 256 characters
	*/
	public $transactionNumber;

	/**
	* an invoice reference to track by
	* @var string max. 256 characters
	*/
	public $invoiceReference;

	/**
	* description of what is being purchased / paid for
	* @var string max. 128 characters
	*/
	public $invoiceDescription;

	/**
	* total amount of payment, in "dollars and cents" as a floating-point number
	* @var float
	*/
	public $amount;

	/**
	* ISO 4217 currency code
	* @var string 3 characters in uppercase
	*/
	public $currencyCode;

	// customer and billing details

	/**
	* customer's title
	* @var string max. 20 characters
	*/
	public $title;

	/**
	* customer's first name
	* @var string max. 40 characters
	*/
	public $firstName;

	/**
	* customer's last name
	* @var string max. 40 characters
	*/
	public $lastName;

	/**
	* customer's company name
	* @var string max. 40 characters
	*/
	public $companyName;

	/**
	* customer's birth date
	* @var string max. 10 characters
	*/
	public $birthDate;

	/**
	* customer's address line 1
	* @var string max. 50 characters (for address1 + address2!)
	*/
	public $address1;

	/**
	* customer's address line 2
	* @var string
	*/
	public $address2;

	/**
	* customer's suburb/city/town
	* @var string max. 30 characters
	*/
	public $suburb;

	/**
	* customer's state/province
	* @var string max. 30 characters
	*/
	public $state;

	/**
	* customer's postcode
	* @var string max. 10 characters
	*/
	public $postcode;

	/**
	* customer's country code
	* @var string 2 characters lowercase
	*/
	public $country;

	/**
	* customer's email address
	* @var string max. 128 characters
	*/
	public $emailAddress;

	/**
	* customer's phone number
	* @var string max. 20 characters
	*/
	public $phone;

	/**
	* customer's mobile phone number
	* @var string max. 20 characters
	*/
	public $mobile;

	#endregion "payment specific members"

	#region "shared pages options"

	/**
	* URL where shopper is directed on success
	* @var string max. 512 characters
	*/
	public $redirectURL;

	/**
	* URL where shopper is directed on failure
	* @var string max. 512 characters
	*/
	public $cancelUrl;

	/**
	* merchant name for shared page
	* @var string
	*/
	public $merchantName;

	/**
	* CSS stylesheet URL if available
	* @var string max. 512 characters
	*/
	public $cssUrl;

	/**
	* banner URL if available
	* @var string max. 512 characters
	*/
	public $bannerUrl;

	/**
	* banner height if available
	* @var string max. 512 characters
	*/
	public $bannerHeight;

	/**
	* payment page language code
	* @var string max. 5 characters
	*/
	public $languageCode;

	#endregion // "shared pages options"

	#endregion // "members"

	/**
	* populate members with defaults, and set account and environment information
	* @param GFHeidelpayCredentials $creds
	* @param boolean $useSandbox
	*/
	public function __construct($creds, $useSandbox = true) {
		$this->sender			= $creds->sender;
		$this->login			= $creds->login;
		$this->password			= $creds->password;
		$this->channel			= $creds->channel;
		$this->useSandbox		= $useSandbox;
		$this->sslVerifyPeer	= true;
		$this->httpUserAgent	= 'Gravity Forms heidelpay ' . GFHEIDELPAY_PLUGIN_VERSION;
	}

	/**
	* request a Shared Page payment URL from gateway; throws exception on error with error described in exception message.
	* @return GFHeidelpayResponseSharedPage
	* @throws GFHeidelpayException
	*/
	public function requestSharedPage() {
		$errors = $this->validateAmount();

		if (!empty($errors)) {
			throw new GFHeidelpayException(implode("\n", $errors));
		}

		$request = $this->getPayment();

		$response_raw = $this->apiPostRequest($request);

		$response = new GFHeidelpayResponseSharedPage();
		$response->loadResponse($response_raw);

		return $response;
	}

	/**
	* validate the amount for processing
	* @return array list of errors in validation
	*/
	protected function validateAmount() {
		$errors = array();

		if (!is_numeric($this->amount) || $this->amount <= 0) {
			$errors[] = __('Amount must be given as a number in currency format', 'gravityforms-heidelpay');
		}
		else if (!is_float($this->amount)) {
			$this->amount = (float) $this->amount;
		}

		return $errors;
	}

	/**
	* create request document for payment
	* @return string
	*/
	public function getPayment() {
		$request = array();

		$request['SECURITY.SENDER']					= substr($this->sender, 0, 32);
		$request['USER.LOGIN']						= substr($this->login, 0, 32);
		$request['USER.PWD']						= substr($this->password, 0, 16);
		$request['TRANSACTION.CHANNEL']				= substr($this->channel, 0, 32);
		$request['TRANSACTION.MODE']				= $this->useSandbox ? 'INTEGRATOR_TEST' : 'LIVE';

		$request['FRONTEND.RESPONSE_URL']			= $this->redirectURL;
		$request['FRONTEND.MODE']					= 'DEFAULT';				// could also be e.g. WPF_LIGHT
		$request['FRONTEND.ENABLED']				= 'true';
		$request['FRONTEND.POPUP']					= 'false';
		$request['FRONTEND.SHOP_NAME']				= $this->merchantName;
		$request['FRONTEND.REDIRECT_TIME']			= '0';
		$request['FRONTEND.LANGUAGE_SELECTOR']		= 'true';
		$request['FRONTEND.LANGUAGE']				= $this->getLanguageCode();
		$request['REQUEST.VERSION']					= '1.0';

		$request['FRONTEND.CSS_PATH']				= $this->cssUrl;

		if ($this->bannerUrl) {
			$request['FRONTEND.BANNER.1.LINK']		= $this->bannerUrl;
			$request['FRONTEND.BANNER.1.HEIGHT']	= $this->bannerHeight ? $this->bannerHeight : '100';
			$request['FRONTEND.BANNER.1.AREA']		= 'TOP';
		}

		$request['PAYMENT.CODE']					= $this->getPaymentCode();
		$request['PRESENTATION.CURRENCY']			= $this->currencyCode;
		$request['PRESENTATION.AMOUNT']				= sprintf('%0.2f', (float) $this->amount);
		$request['PRESENTATION.USAGE']				= substr($this->invoiceDescription, 0, 128);
		$request['IDENTIFICATION.INVOICEID']		= substr($this->invoiceReference, 0, 256);
		$request['IDENTIFICATION.TRANSACTIONID']	= substr($this->transactionNumber, 0, 256);

		$request['NAME.TITLE']						= substr($this->title, 0, 20);
		$request['NAME.GIVEN']						= substr($this->firstName, 0, 40);
		$request['NAME.FAMILY']						= substr($this->lastName, 0, 40);
		$request['NAME.BIRTHDATE']					= substr($this->birthDate, 0, 10);
		$request['ADDRESS.STREET']					= substr(implode(' ', array($this->address1, $this->address2)), 0, 50);
		$request['ADDRESS.ZIP']						= substr($this->postcode, 0, 10);
		$request['ADDRESS.CITY']					= substr($this->suburb, 0, 30);
		$request['ADDRESS.STATE']					= substr($this->state, 0, 30);
		$request['ADDRESS.COUNTRY']					= $this->country;
		$request['CONTACT.EMAIL']					= substr($this->emailAddress, 0, 128);
		$request['CONTACT.PHONE']					= substr($this->phone, 0, 20);
		$request['CONTACT.MOBILE']					= substr($this->mobile, 0, 20);
		$request['CONTACT.IP']						= self::getCustomerIP();

		// remove empty arguments
		$request = array_filter($request, 'strlen');

		if ($this->enabledMethods) {
			$request = array_merge($request, $this->getPaymentMethods());
		}

		return $request;
	}

	/**
	* construct a payment code from method and transaction type
	* @return string
	*/
	protected function getPaymentCode() {
		// TODO: handle tricky combinations of method and transaction type
		$paymentCode = sprintf('%s.%s', self::METHOD_CREDIT_CARD, $this->transactionType);

		return $paymentCode;
	}

	/**
	* get payment methods and subtypes from settings
	* @return array
	*/
	protected function getPaymentMethods() {
		$methods = array();
		$i = 0;

		foreach (array('CC', 'DC', 'DD', 'OT', 'VA', 'PC', 'IV', 'PP') as $method) {
			if (!empty($this->enabledMethods[$method])) {
				$i++;
				$methods["FRONTEND.PM.$i.METHOD"]	= $method;
				$methods["FRONTEND.PM.$i.ENABLED"]	= 'true';

				if ($method === 'CC') {
					$cards = array();
					foreach (array('VISA', 'MASTER', 'AMEX', 'JCB', 'DINERS', 'DISCOVER') as $cardcode) {
						if (!empty($this->enabledMethods[$cardcode])) {
							$cards[] = $cardcode;
						}
					}

					if (count($cards) > 0) {
						$methods["FRONTEND.PM.$i.SUBTYPES"] = implode(',', $cards);
					}
				}
			}
		}

		if ($i > 0) {
			$methods['FRONTEND.PM.DEFAULT_DISABLE_ALL']	= 'true';
		}

		return $methods;
	}

	/**
	* get gateway language code from WP language code
	* @return string
	*/
	protected function getLanguageCode() {
		if (empty($this->languageCode)) {
			return 'en';
		}

		return strtolower(substr($this->languageCode, 0, 2));
	}

	/**
	* generalise an API post request
	* @param array $request
	* @return string
	* @throws GFHeidelpayException
	*/
	protected function apiPostRequest($request) {
		// select host
		$url = $this->useSandbox ? self::API_HOST_SANDBOX : self::API_HOST_LIVE;

		// execute the request, and retrieve the response
		$response = wp_remote_post($url, array(
			'user-agent'	=> $this->httpUserAgent,
			'sslverify'		=> $this->sslVerifyPeer,
			'timeout'		=> 30,
			'headers'		=> array(
									'Content-Type'		=> 'application/x-www-form-urlencoded;charset=UTF-8',
							   ),
			'body'			=> $request,
		));

		// check for http error
		$this->checkHttpResponse($response);

		return wp_remote_retrieve_body($response);
	}

	/**
	* check http get/post response, throw exception if an error occurred
	* @param array $response
	* @throws GFHeidelpayException
	*/
	protected function checkHttpResponse($response) {
		// failure to handle the http request
		if (is_wp_error($response)) {
			$msg = $response->get_error_message();
			throw new GFHeidelpayException(sprintf(__('Error posting heidelpay request: %s', 'gravityforms-heidelpay'), $msg));
		}

		// error code returned by request
		$code = wp_remote_retrieve_response_code($response);
		if ($code !== 200) {
			$msg = wp_remote_retrieve_response_message($response);

			if (empty($msg)) {
				$msg = sprintf(__('Error posting heidelpay request: %s', 'gravityforms-heidelpay'), $code);
			}
			else {
				/* translators: 1. the error code; 2. the error message */
				$msg = sprintf(__('Error posting heidelpay request: %1$s, %2$s', 'gravityforms-heidelpay'), $code, $msg);
			}
			throw new GFHeidelpayException($msg);
		}
	}

	/**
	* get the customer's IP address dynamically from server variables
	* @return string
	*/
	protected function getCustomerIP() {
		// if test mode and running on localhost, then kludge to an Aussie IP address
		if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1' && $this->useSandbox) {
			$ip = '210.1.199.10';
		}

		// check for remote address, ignore all other headers as they can be spoofed easily
		elseif (isset($_SERVER['REMOTE_ADDR']) && self::isIpAddress($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		// allow hookers to override for network-specific fixes
		$ip = apply_filters('gfheidelpay_customer_ip', $ip);

		return $ip;
	}

	/**
	* check whether a given string is an IP address
	* @param string $maybeIP
	* @return bool
	*/
	protected static function isIpAddress($maybeIP) {
		if (function_exists('inet_pton')) {
			// check for IPv4 and IPv6 addresses
			return !!inet_pton($maybeIP);
		}

		// just check for IPv4 addresses
		return !!ip2long($maybeIP);
	}

}
