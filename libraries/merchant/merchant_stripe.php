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
 * Merchant Stripe Class
 *
 * Payment processing using Stripe
 * Documentation: https://stripe.com/docs/api
 */

class Merchant_stripe extends Merchant_driver
{
	const PROCESS_URL = 'https://api.stripe.com';

	public function default_settings()
	{
		return array(
			'api_key' => '',
		);
	}

	public function purchase()
	{
		$this->require_params('token');

		$request = array();
		$request['amount'] = $this->amount_cents();
		$request['card'] = $this->param('token');
		$request['currency'] = strtolower($this->param('currency'));
		$request['description'] = $this->param('description');

		$process_url = self::PROCESS_URL.'/v1/charges';
		$response = $this->post_request($process_url, $request, $this->setting('api_key'));
		return new Merchant_stripe_response($response);
	}

	public function refund()
	{
		$this->require_params('reference', 'amount');

		$request = array('amount' => $this->amount_cents());

		$process_url = self::PROCESS_URL.'/v1/charges/'.$this->param('reference').'/refund';
		$response = $this->post_request($process_url, $request, $this->setting('api_key'));
		return new Merchant_stripe_response($response);
	}
}

class Merchant_stripe_response extends Merchant_response
{
	protected $_response;

	public function __construct($response)
	{
		$this->_response = json_decode($response);

		if (empty($this->_response))
		{
			$this->_status = self::FAILED;
			$this->_message = lang('merchant_invalid_response');
		}
		elseif (isset($this->_response->error))
		{
			$this->_status = self::FAILED;
			$this->_message = $this->_response->error->message;
		}
		elseif ($this->_response->refunded)
		{
			$this->_status = self::REFUNDED;
			$this->_reference = $this->_response->id;
		}
		else
		{
			$this->_status = self::COMPLETE;
			$this->_reference = $this->_response->id;
		}
	}
}


/* End of file ./libraries/merchant/drivers/merchant_stripe.php */