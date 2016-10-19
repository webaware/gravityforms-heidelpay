<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
* Class for dealing with a gateway response
*/
abstract class GFHeidelpayResponse {

	/**
	* load gateway response data
	* @param string|array $response_raw gateway response
	* @throws GFHeidelpayException
	*/
	public function loadResponse($response_raw) {
		$response = wp_unslash(wp_parse_args($response_raw));

		if (empty($response)) {
			throw new GFHeidelpayException($this->getMessageInvalid());
		}

		foreach ($response as $name => $value) {
			if (property_exists($this, $name)) {
				$this->$name = $value;
			}
		}
	}

	/**
	* get 'invalid response' message for specific response class
	* @return string
	*/
	abstract protected function getMessageInvalid();

}
