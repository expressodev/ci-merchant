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
 * Merchant Authorize.Net Class
 *
 * Payment processing using Authorize.net AIM
 */

class Merchant_authorize_net extends Merchant_driver
{
	public $settings = array(
		'api_login_id' => '',
		'transaction_key' => '',
		'test_mode' => FALSE,
	);

	public $required_fields = array('amount','card_no','exp_month','exp_year','csc','reference');

	public $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		require_once MERCHANT_VENDOR_PATH.'/AuthorizeNet/AuthorizeNet.php';
	}

	public function _process($params)
	{
		$transaction = new AuthorizeNetAIM($this->settings['api_login_id'],$this->settings['transaction_key']);
		$transaction->amount = $params['amount'];
		$transaction->card_num = $params['card_no'];
		$transaction->exp_date = $params['exp_month'].$params['exp_year'];
		$transaction->card_code = $params['csc'];
		$transaction->invoice_num = $params['reference'];
		$transaction->customer_ip = $this->CI->input->ip_address();
		$transaction->setSandbox((bool)$this->settings['test_mode']);

		// set extra billing details if we have them
		if (isset($params['card_name']))
		{
			$names = explode(' ', $params['card_name'], 2);
			$transaction->first_name = $names[0];
			$transaction->last_name = isset($names[1]) ? $names[1] : '';
		}

		if (isset($params['address']) AND isset($params['address2']))
		{
			$params['address'] = trim($params['address']." \n".$params['address2']);
		}

		foreach (array(
			'company' => 'company',
			'address' => 'address',
			'city' => 'city',
			'state' => 'region',
			'zip' => 'postcode',
			'country' => 'country',
			'phone' => 'phone',
			'email' => 'email') as $key => $field)
		{
			if (isset($params[$field]))
			{
				$transaction->$key = $params[$field];
			}
		}

		$response = $transaction->authorizeAndCapture();

		if ($response->approved)
		{
			return new Merchant_response('authorized', $response->response_reason_text, $response->transaction_id, (double)$response->amount);
		}
		elseif ($response->declined)
		{
			return new Merchant_response('declined', $response->response_reason_text);
		}
		else
		{
			return new Merchant_response('failed', $response->response_reason_text);
		}
	}
}
/* End of file ./libraries/merchant/drivers/merchant_authorize_net.php */