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
 * Merchant 2Checkout Class
 *
 * Payment processing using 2Checkout
 * Documentation: http://www.2checkout.com/documentation/Advanced_User_Guide.pdf
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
		$this->require_params('order_id', 'return_url');

		// post data to 2checkout
		$data = array(
			'sid' => $this->setting('account_no'),
			'cart_order_id' => $this->param('order_id'),
			'total' => $this->amount_dollars(),
			'tco_currency' => $this->param('currency'),
			'fixed' => 'Y',
			'skip_landing' => 1,
			'x_receipt_link_url' => $this->param('return_url'),
			'card_holder_name' => $this->param('name'),
			'street_address' => $this->param('address1'),
			'street_address2' => $this->param('address2'),
			'city' => $this->param('city'),
			'state' => $this->param('region'),
			'zip' => $this->param('postcode'),
			'country' => $this->param('country'),
			'phone' => $this->param('phone'),
			'email' => $this->param('email'),
		);

		if ($this->setting('test_mode'))
		{
			$data['demo'] = 'Y';
		}

		$this->redirect(self::PROCESS_URL.'?'.http_build_query($data));
	}

	public function purchase_return()
	{
		$order_number = $this->CI->input->get_post('order_number');

		// strange exception specified by 2Checkout
		if ($this->setting('test_mode'))
		{
			$order_number = '1';
		}

		$key = strtoupper(md5($this->setting('secret_word').$this->setting('account_no').$order_number.$this->amount_dollars()));
		if ($key == $this->CI->input->get_post('key'))
		{
			return new Merchant_response(Merchant_response::COMPLETE, NULL, $order_number);
		}

		return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
	}
}

/* End of file ./libraries/merchant/drivers/merchant_paypal.php */