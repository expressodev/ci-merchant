<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011 Crescendo Multimedia Ltd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Merchant Paypal Class
 *
 * Payment processing using Paypal Payments Standard
 */

class Merchant_paypal extends CI_Driver {

	public $name = 'PayPal';

	public $required_fields = array('amount', 'reference', 'currency_code', 'return_url', 'cancel_url', 'notify_url');

	public $settings = array(
		'paypal_email' => '',
		'test_mode' => FALSE
	);

	const PROCESS_URL = 'https://www.paypal.com/cgi-bin/webscr';
	const PROCESS_URL_TEST = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

	public $CI;

	public function __construct($settings = array())
	{
		foreach ($settings as $key => $value)
		{
			if(array_key_exists($key, $this->settings))	$this->settings[$key] = $value;
		}
		$this->CI =& get_instance();
	}

	public function _process($params)
	{
		// ask paypal to generate request url
		$data = array(
			'rm' => '2',
			'cmd' => '_xclick',
			'business' => $this->settings['paypal_email'],
			'return'=> $params['return_url'],
      		'cancel_return' => $params['cancel_url'],
      		'notify_url' => $params['notify_url'],
      		'item_name' => $params['reference'],
      		'amount' => sprintf('%01.2f', $params['amount']),
			'currency_code' => $params['currency_code'],
			'no_shipping' => 1
		);

		$post_url = $this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL;
		Merchant::redirect_post($post_url, $data);
	}

	public function _process_return()
	{
		$action = $this->CI->input->get('action', TRUE);

		if ($action === FALSE) return new Merchant_response('failed', 'invalid_response');

		if ($action === 'success') return new Merchant_response('return', '', $_POST['txn_id']);

		if ($action === 'cancel') return new Merchant_response('failed', 'payment_cancelled');

		if ($action === 'ipn')
		{
			// generate the post string from _POST
			$post_string = 'cmd=_notify-validate&'.http_build_query($_POST);

			$response = Merchant::curl_helper($this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL, $post_string);
			if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

			$memo = $this->CI->input->post('memo');
			if (strpos("VERIFIED", $response['data']) !== FALSE)
			{
				// Valid IPN transaction.
				return new Merchant_response('authorized', $memo, $_POST['txn_id'], (string)$_POST['mc_gross']);
      		}
			else
			{
				// Invalid IPN transaction
				return new Merchant_response('declined', $memo);
			}
		}
	}
}
/* End of file ./libraries/merchant/drivers/merchant_paypal.php */