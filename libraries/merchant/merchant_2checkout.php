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
 * Merchant 2Checkout Class
 *
 * Payment processing using 2Checkout
 */

class Merchant_2checkout extends CI_Driver {

	public $name = '2Checkout';

	public $required_fields = array('amount', 'reference', 'currency_code', 'return_url');

	public $settings = array(
		'account_no' => '',
		'secret_word' => '',
		'test_mode' => FALSE
	);

	const PROCESS_URL = 'https://www.2checkout.com/checkout/purchase';

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
		// post data to 2checkout
		$data = array(
			'sid' => $this->settings['account_no'],
			'cart_order_id' => $params['reference'],
			'total' => $params['amount'],
			'tco_currency' => $params['currency_code'],
			'skip_landing' => 1,
      		'x_Receipt_Link_URL' => $params['return_url'],
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

		if ($this->settings['test_mode'])
		{
			$data['demo'] = 'Y';
		}

		Merchant::redirect_post(self::PROCESS_URL, $data);
	}

	public function _process_return()
	{
		$order_number = $this->CI->input->post('order_number');
		$order_total = $this->CI->input->post('total');

		if ($this->settings['test_mode'])
		{
			$order_number = '1';
		}

		$check = strtoupper(md5($this->settings['secret_word'].$this->settings['account_no'].$order_number.$order_total));

		if ($check == $this->CI->input->post('key'))
		{
			return new Merchant_response('authorized', '', $this->CI->input->post('order_number'), (float)$order_total);
		}
		else
		{
			return new Merchant_response('failed', 'invalid_response');
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_paypal.php */