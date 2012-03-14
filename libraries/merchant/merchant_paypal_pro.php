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
 * Merchant Paypal Pro Class
 *
 * Payment processing using Paypal Payments Pro
 * Documentation: https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/howto_api_reference
 */

class Merchant_paypal_pro extends Merchant_driver
{
	const PROCESS_URL = 'https://api-3t.paypal.com/nvp';
	const PROCESS_URL_TEST = 'https://api-3t.sandbox.paypal.com/nvp';

	public function default_settings()
	{
		return array(
			'username' => '',
			'password' => '',
			'signature' => '',
			'test_mode' => FALSE
		);
	}

	public function purchase()
	{
		$this->require_params('reference', 'card_no', 'card_name', 'exp_month', 'exp_year', 'csc');

		$card_name = explode(' ', $this->param('card_name'), 2);

		$data = array(
			'USER' => $this->setting('username'),
			'PWD' => $this->setting('password'),
			'SIGNATURE' => $this->setting('signature'),
			'VERSION' => '65.1',
			'METHOD' => 'doDirectPayment',
			'PAYMENTACTION' => 'Sale',
			'AMT' => sprintf('%01.2f', $this->param('amount')),
			'CURRENCYCODE' => $this->param('currency'),
			'ACCT' => $this->param('card_no'),
			'EXPDATE' => $this->param('exp_month').$this->param('exp_year'),
			'CVV2' => $this->param('csc'),
			'IPADDRESS' => $this->CI->input->ip_address(),
			'FIRSTNAME' => $card_name[0],
			'LASTNAME' => isset($card_name[1]) ? $card_name[1] : '',
		);

		if ($this->param('card_type'))
		{
			$data['CREDITCARDTYPE'] = ucfirst($this->param('card_type'));
			if ($data['CREDITCARDTYPE'] == 'Mastercard') $data['CREDITCARDTYPE'] = 'MasterCard';
		}

		if ($this->param('card_issue')) $data['ISSUENUMBER'] = $this->param('card_issue');
		if ($this->param('start_month') AND $this->param('start_year'))
		{
			$data['STARTDATE'] = $this->param('start_month').$this->param('start_year');
		}

		if ($this->param('address')) $data['STREET'] = $this->param('address');
		if ($this->param('city')) $data['CITY'] = $this->param('city');
		if ($this->param('region')) $data['STATE'] = $this->param('region');
		if ($this->param('postcode')) $data['ZIP'] = $this->param('postcode');
		if ($this->param('country')) $data['COUNTRYCODE'] = strtoupper($this->param('country'));

		// send request to paypal
		$response = Merchant::curl_helper($this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL, $data);
		if ( ! empty($response['error'])) return new Merchant_response(Merchant_response::FAILED, $response['error']);

		$response_array = array();
		parse_str($response['data'], $response_array);

		if (empty($response_array['ACK']))
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}
		elseif ($response_array['ACK'] == 'Success' OR $response_array['ACK'] == 'SuccessWithWarning')
		{
			return new Merchant_response(Merchant_response::COMPLETED, '', $response_array['TRANSACTIONID'], (double)$response_array['AMT']);
		}
		elseif ($response_array['ACK'] == 'Failure' OR $response_array['ACK'] == 'FailureWithWarning')
		{
			return new Merchant_response(Merchant_response::FAILED, $response_array['L_ERRORCODE0'].': '.$response_array['L_LONGMESSAGE0']);
		}
		else
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}
	}
}
/* End of file ./libraries/merchant/drivers/merchant_paypal_pro.php */