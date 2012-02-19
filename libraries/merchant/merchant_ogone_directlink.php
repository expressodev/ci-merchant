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
 * Merchant Ogone DirectLink Class
 *
 * Payment processing using Ogone DirectLink
 */

class Merchant_ogone_directlink extends Merchant_driver
{
	const PROCESS_URL = 'https://secure.ogone.com/ncol/prod/orderdirect.asp';
	const PROCESS_URL_TEST = 'https://secure.ogone.com/ncol/test/orderdirect.asp';

	public $required_fields = array('amount', 'card_no', 'card_name', 'exp_month', 'exp_year', 'csc', 'currency_code', 'reference');

	public $settings = array(
		'psp_id' => '',
		'user_id' => '',
		'password' => '',
		'signature' => '',
		'test_mode' => FALSE,
	);

	public $CI;

	public function __construct()
	{
		$this->CI = get_instance();
	}

	public function _process($params)
	{
		$date_expiry = $params['exp_month'];
		$date_expiry .= $params['exp_year'] % 100;

		$request = array(
			'PSPID' => $this->settings['psp_id'],
			'USERID' => $this->settings['user_id'],
			'PSWD' => $this->settings['password'],
			'ORDERID' => $params['reference'],
			'OPERATION' => 'SAL',
			'AMOUNT' => round($params['amount'] * 100),
			'CURRENCY' => $params['currency_code'],
			'CARDNO' => $params['card_no'],
			'ED' => $date_expiry,
			'CN' => $params['card_name'],
			'CVC' => $params['csc'],
			'REMOTE_ADDR' => $this->CI->input->ip_address(),
			'ECI' => 7,
		);

		if ( ! empty($params['email'])) $request['EMAIL'] = $params['email'];
		if ( ! empty($params['address'])) $request['OWNERADDRESS'] = $params['address'];
		if ( ! empty($params['address2'])) $request['OWNERTOWN'] = $params['address2'];
		if ( ! empty($params['postcode'])) $request['OWNERZIP'] = $params['postcode'];
		if ( ! empty($params['city'])) $request['OWNERCTY'] = $params['city'];
		if ( ! empty($params['phone'])) $request['OWNERTELNO'] = $params['phone'];

		// generate secure SHA signature
		ksort($request);
		$signature = '';
		foreach ($request as $key => $value)
		{
			$signature .= $key.'='.$value.$this->settings['signature'];
		}
		$request['SHASIGN'] = sha1($signature);

		$process_url = $this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL;
		$response = Merchant::curl_helper($process_url, $request);
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$xml = simplexml_load_string($response['data']);

		if (empty($xml['PAYID']))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		elseif ($xml['STATUS'] == '9')
		{
			return new Merchant_response('authorized', NULL, (string)$xml['PAYID'], (float)$xml['amount']);
		}
		else
		{
			return new Merchant_response('declined', (string)$xml['NCERRORPLUS'], (string)$xml['PAYID']);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_ogone_directlink.php */