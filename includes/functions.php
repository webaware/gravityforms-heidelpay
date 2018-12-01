<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}

// minimum versions required
const MIN_VERSION_GF		= '2.0';

// entry meta keys
const META_TRANSACTION_ID				= 'heidelpay_txn_id';
const META_SHORT_ID						= 'heidelpay_short_id';
const META_RETURN_CODE					= 'heidelpay_return_code';
const META_FEED_ID						= 'heidelpay_feed_id';
const META_UNIQUE_ID					= 'heidelpay_unique_id';

// end points for return to website
const ENDPOINT_CONFIRMATION				= '__gfheidelpay';

/**
* custom exception types
*/
class GFHeidelpayException extends \Exception {}
class GFHeidelpayCurlException extends \Exception {}

/**
* compare Gravity Forms version against target
* @param string $target
* @param string $operator
* @return bool
*/
function gform_version_compare($target, $operator) {
	if (class_exists('GFCommon', false)) {
		return version_compare(\GFCommon::$version, $target, $operator);
	}

	return false;
}

/**
* test whether the minimum required Gravity Forms is installed / activated
* @return bool
*/
function has_required_gravityforms() {
	return gform_version_compare(MIN_VERSION_GF, '>=');
}

/**
* check whether this form entry's unique ID has already been used; if so, we've already done/doing a payment attempt.
* @param int $form_id
* @return boolean
*/
function has_form_been_processed($form_id) {
	$unique_id = \GFFormsModel::get_form_unique_id($form_id);

	$search = [
		'field_filters' => [
			[
				'key'		=> META_UNIQUE_ID,
				'value'		=> $unique_id,
			],
		],
	];

	$entries = \GFAPI::get_entries($form_id, $search);

	return !empty($entries);
}
