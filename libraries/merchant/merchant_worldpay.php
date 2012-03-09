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
 * Merchant WorldPay Class
 *
 * Payment processing using WorldPay (external)
 */

class Merchant_worldpay extends Merchant_driver
{
	const PROCESS_URL = 'https://secure.worldpay.com/wcc/purchase';
	const PROCESS_URL_TEST = 'https://secure-test.worldpay.com/wcc/purchase';

	public $required_fields = array('amount', 'reference', 'currency_code', 'return_url');

	public $default_settings = array(
		'installation_id' => '',
		'secret' => '',
		'payment_response_password' => '',
		'test_mode' => FALSE,
	);

	public $CI;

	public function __construct()
	{
		$this->CI = get_instance();
	}

	public function process($params)
	{
		$data = array(
			'instId' => $this->settings['installation_id'],
			'cartId' => $params['reference'],
			'amount' => $params['amount'],
			'currency' => $params['currency_code'],
			'testMode' => $this->settings['test_mode'] ? 100 : 0,
			'MC_callback' => $params['return_url'],
		);

		if ( ! empty($params['card_name'])) $data['name'] = $params['card_name'];
		if ( ! empty($params['address'])) $data['address1'] = $params['address'];
		if ( ! empty($params['address2'])) $data['address2'] = $params['address2'];
		if ( ! empty($params['city'])) $data['town'] = $params['city'];
		if ( ! empty($params['region'])) $data['region'] = $params['region'];
		if ( ! empty($params['postcode'])) $data['postcode'] = $params['postcode'];
		if ( ! empty($params['country'])) $data['country'] = $params['country'];
		if ( ! empty($params['phone'])) $data['tel'] = $params['phone'];
		if ( ! empty($params['email'])) $data['email'] = $params['email'];

		if ( ! empty($this->settings['secret']))
		{
			$data['signatureFields'] = 'instId:amount:currency:cartId';
			$signature_data = array($this->settings['secret'],
				$data['instId'], $data['amount'], $data['currency'], $data['cartId']);
			$data['signature'] = md5(implode(':', $signature_data));
		}

		$post_url = $this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL;
		Merchant::redirect_post($post_url, $data);
	}

	public function process_return($params)
	{
		$callback_pw = (string)$this->CI->input->post('callbackPW');
		if ($callback_pw != $this->settings['payment_response_password'])
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}

		$status = $this->CI->input->post('transStatus');
		if (empty($status))
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}
		elseif ($status != 'Y')
		{
			$message = $this->CI->input->post('rawAuthMessage');
			return new Merchant_response(Merchant_response::FAILED, $message);
		}
		else
		{
			$transaction_id = $this->CI->input->post('transId');
			$amount = $this->CI->input->post('authAmount');
			return new Merchant_response(Merchant_response::COMPLETED, NULL, $transaction_id, $amount);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_worldpay.php */