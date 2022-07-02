<?php

// If this file is called firectly, abort!!!
defined('ABSPATH') or die('Direct access is not allowed.');

/**
 * Sends API requests to UddoktaPay Gateway.
 */

class UddoktaPay_Gateway_API_Handler
{

	/** @var string/array Log variable function. */
	public static $log;
	/**
	 * Call the $log variable function.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log($message, $level = 'info')
	{
		return call_user_func(self::$log, $message, $level);
	}

	/** @var string UddoktaPay Gateway API url. */
	public static $api_url;


	/** @var string UddoktaPay Gateway API key. */
	public static $api_key;

	/**
	 * Get the response from an API request.
	 * @param  string $endpoint
	 * @param  array  $params
	 * @param  string $method
	 * @return array
	 */
	public static function send_request($params = array(), $method = 'POST')
	{
		// phpcs:ignore
		self::log('UddoktaPay Gateway Request Args: ' . print_r($params, true));

		$args = array(
			'method'  => $method,
			'headers' => array(
				'RT-UDDOKTAPAY-API-KEY' => self::$api_key,
				'Content-Type' => 'application/json'
			)
		);

		$url = self::$api_url;

		if (in_array($method, array('POST', 'PUT'))) {
			$args['body'] = json_encode($params);
		} else {
			$url = add_query_arg($params, $url);
		}
		$response = wp_remote_request(esc_url_raw($url), $args);

		if (is_wp_error($response)) {
			self::log('WP response error: ' . $response->get_error_message());
			return array(false, $response->get_error_message());
		} else {
			return json_decode($response['body']);
		}
	}


	/**
	 * Create a new charge request.
	 * @param  int    $amount Total Amount of Product
	 * @param  string $currency Payment Currency
	 * @param  array  $full_name User Full Name
	 * @param  array  $email User Email
	 * @param  srring $metadata Metadata for extra validation
	 * @param  string $redirect Redirect URL
	 * @param  string $cancel Cancel URl
	 * @param  string $webhook_url Webhook URL
	 * @return array
	 */
	public static function create_payment($amount = null, $currency = null, $full_name = null, $email = null, $metadata = null, $redirect = null, $cancel = null, $webhook_url = null)
	{

		if (is_null($currency)) {
			self::log('Error: if amount is given, currency must be given (in create_charge()).', 'error');
			return array(false, 'Missing currency.');
		}

		$args['amount'] = !empty($amount) ? $amount : '0';

		if ($currency !== "BDT") {
			$args['amount'] = $amount * 90;
		}


		$args['full_name'] = !empty($full_name) ? $full_name : 'Unknown';

		$args['email'] = !empty($email) ? $email : 'unknown@gmail.com';

		if (!is_null($metadata)) {
			$args['metadata'] = $metadata;
		}
		if (!is_null($redirect)) {
			$args['redirect_url'] = $redirect;
		}
		if (!is_null($cancel)) {
			$args['cancel_url'] = $cancel;
		}

		if (!is_null($webhook_url)) {
			$args['webhook_url'] = $webhook_url;
		}

		$result = self::send_request($args, 'POST');

		return $result;
	}
}
