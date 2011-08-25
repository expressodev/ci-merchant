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
 * Merchant SagePay Direct Class
 *
 * Payment processing using SagePay Direct
 */

class Merchant_sagepay_direct extends CI_Driver {

	public $name = 'SagePay Direct';

	const PROCESS_URL = 'https://live.sagepay.com/gateway/service/vspdirect-register.vsp';
	const PROCESS_URL_TEST = 'https://test.sagepay.com/gateway/service/vspdirect-register.vsp';

	// the simulator URL is only used for plugin development
	const PROCESS_URL_SIM = 'https://test.sagepay.com/Simulator/VSPDirectGateway.asp';

	public $required_fields = array('amount', 'card_no', 'card_name', 'card_type',
		'exp_month', 'exp_year', 'csc', 'currency_code', 'transaction_id', 'reference');

	public $settings = array(
		'vendor' => '',
		'test_mode' => FALSE
	);

	public function _process($params)
	{
		$data = array(
			'VPSProtocol' => '2.23',
			'TxType' => 'PAYMENT',
			'Vendor' => $this->settings['vendor'],
			'VendorTxCode' => $params['transaction_id'],
			'Description' => $params['reference'],
			'Amount' => sprintf('%01.2f', $params['amount']),
			'Currency' => $params['currency_code'],
			'CardHolder' => $params['card_name'],
			'CardNumber' => $params['card_no'],
			'CV2' => $params['csc'],
			'CardType' => strtoupper($params['card_type']),
			'ExpiryDate' => $params['exp_month'].($params['exp_year'] % 100),
			'AccountType' => 'E',
			'ApplyAVSCV2' => 2,
		);

		if ($data['CardType'] == 'MASTERCARD') $data['CardType'] = 'MC';

		if ( ! empty($params['card_issue'])) $data['IssueNumber'] = $params['card_issue'];
		if ( ! empty($params['start_month']) AND ! empty($params['start_year']))
		{
			$data['StartDate'] = $params['start_month'].($params['start_year'] % 100);
		}

		$response = Merchant::curl_helper($this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL, $data);
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		// convert weird ini-type format to a useful array
		$response_array = explode("\n", $response['data']);
		foreach ($response_array as $key => $value)
		{
			unset($response_array[$key]);
			$line = explode('=', $value, 2);
			$response_array[trim($line[0])] = isset($line[1]) ? trim($line[1]) : '';
		}

		if (empty($response_array['Status']))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		elseif ($response_array['Status'] == 'OK')
		{
			return new Merchant_response('authorized', $response_array['StatusDetail'], $response_array['VPSTxId'], (double)$params['amount']);
		}
		else
		{
			return new Merchant_response('declined', $response_array['StatusDetail']);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_sagepay_direct.php */