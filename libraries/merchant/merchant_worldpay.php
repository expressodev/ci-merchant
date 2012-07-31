<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011-2012 Adrian Macneil
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
 * Documentataion: http://www.worldpay.com/support/kb/bg/htmlredirect/rhtml.html
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
		$request = array();
		$request['instId'] = $this->setting('installation_id');
		$request['cartId'] = $this->param('order_id');
		$request['desc'] = $this->param('description');
		$request['amount'] = $this->amount_dollars();
		$request['currency'] = $this->param('currency');
		$request['testMode'] = $this->setting('test_mode') ? 100 : 0;
		$request['MC_callback'] = $this->param('return_url');
		$request['name'] = $this->param('name');
		$request['address1'] = $this->param('address1');
		$request['address2'] = $this->param('address2');
		$request['town'] = $this->param('city');
		$request['region'] = $this->param('region');
		$request['postcode'] = $this->param('postcode');
		$request['country'] = $this->param('country');
		$request['tel'] = $this->param('phone');
		$request['email'] = $this->param('email');

		if ($this->setting('secret'))
		{
			$request['signatureFields'] = 'instId:amount:currency:cartId';
			$signature_data = array($this->setting('secret'),
				$request['instId'], $request['amount'], $request['currency'], $request['cartId']);
			$request['signature'] = md5(implode(':', $signature_data));
		}

		$this->redirect($this->_process_url().'?'.http_build_query($request));
	}

	public function purchase_return()
	{
		$callback_pw = (string)$this->CI->input->post('callbackPW');
		if ($callback_pw != $this->setting('payment_response_password'))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		$status = $this->CI->input->post('transStatus');
		if (empty($status))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
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
			return new Merchant_response(Merchant_response::COMPLETE, NULL, $transaction_id, $amount);
		}
	}

	private function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_worldpay.php */