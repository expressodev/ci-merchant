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
 * Merchant Paypal Pro Class
 *
 * Payment processing using Paypal Payments Pro
 */

class Merchant_paypal_pro extends CI_Driver {

	public $name = 'PayPal Pro';

	public $required_fields = array('reference', 'currency_code', 'amount',
		'card_no', 'card_name', 'exp_month', 'exp_year', 'csc');

	public $settings = array(
		'username' => '',
		'password' => '',
		'signature' => '',
		'test_mode' => FALSE
	);

	const PROCESS_URL = 'https://api-3t.paypal.com/nvp';
	const PROCESS_URL_TEST = 'https://api-3t.sandbox.paypal.com/nvp';

	public $CI;

	public function __construct($settings = array())
	{
		$this->CI =& get_instance();

		foreach ($settings as $key => $value)
		{
			if (array_key_exists($key, $this->settings)) $this->settings[$key] = $value;
		}
	}

	public function _process($params)
	{
		$card_name = explode(' ', $params['card_name'], 2);

		$data = array(
			'USER' => $this->settings['username'],
			'PWD' => $this->settings['password'],
			'SIGNATURE' => $this->settings['signature'],
			'VERSION' => '65.1',
			'METHOD' => 'doDirectPayment',
			'PAYMENTACTION' => 'Sale',
			'AMT' => sprintf('%01.2f', $params['amount']),
			'CURRENCYCODE' => $params['currency_code'],
			'ACCT' => $params['card_no'],
			'EXPDATE' => $params['exp_month'].$params['exp_year'],
			'CVV2' => $params['csc'],
			'IPADDRESS' => $this->CI->input->ip_address(),
			'FIRSTNAME' => $card_name[0],
			'LASTNAME' => isset($card_name[1]) ? $card_name[1] : '',
		);

		if (isset($params['card_type']))
		{
			$data['CREDITCARDTYPE'] = ucfirst($params['card_type']);
			if ($data['CREDITCARDTYPE'] == 'Mastercard') $data['CREDITCARDTYPE'] = 'MasterCard';
		}

		if (isset($params['card_issue'])) $data['ISSUENUMBER'] = $params['card_issue'];
		if (isset($params['start_month']) AND isset($params['start_year']))
		{
			$data['STARTDATE'] = $params['start_month'].$params['start_year'];
		}

		if (isset($params['address'])) $data['STREET'] = $params['address'];
		if (isset($params['city'])) $data['CITY'] = $params['city'];
		if (isset($params['region'])) $data['STATE'] = $params['region'];
		if (isset($params['postcode'])) $data['ZIP'] = $params['postcode'];
		if (isset($params['country'])) $data['COUNTRYCODE'] = strtoupper($params['country']);

		// send request to paypal
		$response = Merchant::curl_helper($this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL, $data);
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$response_array = array();
		parse_str($response['data'], $response_array);

		if (empty($response_array['ACK']))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		elseif ($response_array['ACK'] == 'Success' OR $response_array['ACK'] == 'SuccessWithWarning')
		{
			return new Merchant_response('authorized', '', $response_array['TRANSACTIONID'], (double)$response_array['AMT']);
		}
		elseif ($response_array['ACK'] == 'Failure' OR $response_array['ACK'] == 'FailureWithWarning')
		{
			return new Merchant_response('declined', $response_array['L_ERRORCODE0'].': '.$response_array['L_LONGMESSAGE0']);
		}
		else
		{
			return new Merchant_response('failed', 'invalid_response');
		}
	}
}
/* End of file ./libraries/merchant/drivers/merchant_paypal_pro.php */