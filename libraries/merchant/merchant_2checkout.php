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
 * Merchant 2Checkout Class
 *
 * Payment processing using 2Checkout
 */

class Merchant_2checkout extends Merchant_driver
{
	const PROCESS_URL = 'https://www.2checkout.com/checkout/purchase';

	public function default_settings()
	{
		return array(
			'account_no' => '',
			'secret_word' => '',
			'test_mode' => FALSE
		);
	}

	public function purchase()
	{
		$this->require_params('reference', 'return_url');

		// post data to 2checkout
		$data = array(
			'sid' => $this->setting('account_no'),
			'cart_order_id' => $this->param('reference'),
			'total' => $this->param('amount'),
			'tco_currency' => $this->param('currency'),
			'fixed' => 'Y',
			'skip_landing' => 1,
			'x_receipt_link_url' => $this->param('return_url'),
		);

		foreach (array(
			'card_holder_name' => 'card_name',
			'street_address' => 'address',
			'street_address2' => 'address2',
			'city' => 'city',
			'state' => 'region',
			'zip' => 'postcode',
			'country' => 'country',
			'phone' => 'phone',
			'email' => 'email') as $key => $field)
		{
			if (isset($params[$field]))
			{
				$data[$key] = $params[$field];
			}
		}

		if ($this->setting('test_mode'))
		{
			$data['demo'] = 'Y';
		}

		Merchant::redirect_post(self::PROCESS_URL, $data);
	}

	public function purchase_return()
	{
		$order_number = $this->CI->input->post('order_number');
		$order_total = $this->CI->input->post('total');

		if ($this->setting('test_mode'))
		{
			$order_number = '1';
		}

		$check = strtoupper(md5($this->setting('secret_word').$this->setting('account_no').$order_number.$order_total));

		if ($check == $this->CI->input->post('key'))
		{
			return new Merchant_response(Merchant_response::COMPLETED, NULL, $this->CI->input->post('order_number'));
		}

		return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
	}
}

/* End of file ./libraries/merchant/drivers/merchant_paypal.php */