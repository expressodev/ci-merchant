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
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
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

	public function default_settings()
	{
		return array(
			'installation_id' => '',
			'secret' => '',
			'payment_response_password' => '',
			'test_mode' => FALSE,
		);
	}

	public function purchase()
	{
		$this->require_params('reference', 'return_url');

		$data = array(
			'instId' => $this->setting('installation_id'),
			'cartId' => $this->param('reference'),
			'amount' => $this->param('amount'),
			'currency' => $this->param('currency'),
			'testMode' => $this->setting('test_mode') ? 100 : 0,
			'MC_callback' => $this->param('return_url'),
		);

		if ($this->param('card_name')) $data['name'] = $this->param('card_name');
		if ($this->param('address')) $data['address1'] = $this->param('address');
		if ($this->param('address2')) $data['address2'] = $this->param('address2');
		if ($this->param('city')) $data['town'] = $this->param('city');
		if ($this->param('region')) $data['region'] = $this->param('region');
		if ($this->param('postcode')) $data['postcode'] = $this->param('postcode');
		if ($this->param('country')) $data['country'] = $this->param('country');
		if ($this->param('phone')) $data['tel'] = $this->param('phone');
		if ($this->param('email')) $data['email'] = $this->param('email');

		if ($this->setting('secret'))
		{
			$data['signatureFields'] = 'instId:amount:currency:cartId';
			$signature_data = array($this->setting('secret'),
				$data['instId'], $data['amount'], $data['currency'], $data['cartId']);
			$data['signature'] = md5(implode(':', $signature_data));
		}

		$post_url = $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
		Merchant::redirect_post($post_url, $data);
	}

	public function purchase_return()
	{
		$callback_pw = (string)$this->CI->input->post('callbackPW');
		if ($callback_pw != $this->setting('payment_response_password'))
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