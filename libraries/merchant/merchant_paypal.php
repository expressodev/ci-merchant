<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011-2012 Crescendo Multimedia Ltd
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

class Merchant_paypal extends Merchant_driver
{
	public $required_fields = array('amount', 'reference', 'currency_code', 'return_url');

	const PROCESS_URL = 'https://www.paypal.com/cgi-bin/webscr';
	const PROCESS_URL_TEST = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

	public function default_settings()
	{
		return array(
			'paypal_email' => '',
			'test_mode' => FALSE,
		);
	}

	public function process($params)
	{
		// ask paypal to generate request url
		$data = array(
			'cmd' => '_xclick',
			'paymentaction' => 'sale',
			'business' => $this->settings['paypal_email'],
			'amount' => sprintf('%01.2f', $params['amount']),
			'currency_code' => $params['currency_code'],
			'item_name' => $params['reference'],
			'return'=> $params['return_url'],
			'cancel_return' => $params['cancel_url'],
			'notify_url' => $params['return_url'],
			'rm' => '2',
			'no_shipping' => 1,
		);

		$post_url = $this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL;
		Merchant::redirect_post($post_url, $data);
	}

	public function process_return($params)
	{
		$txn_id = $this->CI->input->post('txn_id');
		if (empty($txn_id))
		{
			return new Merchant_response(Merchant_response::FAILED, 'payment_cancelled');
		}

		// verify payee
		if ($this->CI->input->post('receiver_email') != $this->settings['paypal_email'])
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}

		// verify response
		$post_string = 'cmd=_notify-validate&'.http_build_query($_POST);
		$response = Merchant::curl_helper($this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL, $post_string);
		if ( ! empty($response['error'])) return new Merchant_response(Merchant_response::FAILED, $response['error']);

		if ($response['data'] != 'VERIFIED')
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}

		$payment_status = $this->CI->input->post('payment_status');
		if ($payment_status == 'Completed')
		{
			$amount = (float)$this->CI->input->post('mc_gross');
			return new Merchant_response(Merchant_response::COMPLETED, NULL, $txn_id, $amount);
		}

		return new Merchant_response(Merchant_response::FAILED, $payment_status);
	}
}

/* End of file ./libraries/merchant/drivers/merchant_paypal.php */