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
 * Merchant SagePay Direct Class
 *
 * Payment processing using SagePay Direct
 */

class Merchant_sagepay_direct extends Merchant_driver
{
	const PROCESS_URL = 'https://live.sagepay.com/gateway/service/vspdirect-register.vsp';
	const PROCESS_URL_TEST = 'https://test.sagepay.com/gateway/service/vspdirect-register.vsp';
	const PROCESS_URL_SIM = 'https://test.sagepay.com/Simulator/VSPDirectGateway.asp';

	const AUTH_URL = 'https://live.sagepay.com/gateway/service/direct3dcallback.vsp';
	const AUTH_URL_TEST = 'https://test.sagepay.com/gateway/service/direct3dcallback.vsp';
	const AUTH_URL_SIM = 'https://test.sagepay.com/Simulator/VSPDirectCallback.asp';

	public $required_fields = array('amount', 'card_no', 'card_name', 'card_type',
		'exp_month', 'exp_year', 'csc', 'currency_code', 'reference');

	public $settings = array(
		'vendor' => '',
		'test_mode' => FALSE
	);

	public $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function process($params)
	{
		$data = array(
			'VPSProtocol' => '2.23',
			'TxType' => 'PAYMENT',
			'Vendor' => $this->settings['vendor'],
			'VendorTxCode' => $params['reference'],
			'Description' => $params['reference'],
			'Amount' => sprintf('%01.2f', $params['amount']),
			'Currency' => $params['currency_code'],
			'CardHolder' => $params['card_name'],
			'CardNumber' => $params['card_no'],
			'CV2' => $params['csc'],
			'CardType' => strtoupper($params['card_type']),
			'ExpiryDate' => $params['exp_month'].($params['exp_year'] % 100),
			'ClientIPAddress' => $this->CI->input->ip_address(),
			'ApplyAVSCV2' => 0,
			'Apply3DSecure' => 0,
		);

		if ($data['CardType'] == 'MASTERCARD') $data['CardType'] = 'MC';

		if ( ! empty($params['card_issue']))
		{
			$data['IssueNumber'] = $params['card_issue'];
		}
		if ( ! empty($params['start_month']) AND ! empty($params['start_year']))
		{
			$data['StartDate'] = $params['start_month'].($params['start_year'] % 100);
		}

		$response = Merchant::curl_helper($this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL, $data);
		if ( ! empty($response['error'])) return new Merchant_response(Merchant_response::FAILED, $response['error']);

		return $this->_process_response($response['data'], $params);
	}

	/**
	 * Only used for returning from 3D Secure Authentication
	 */
	public function process_return($params)
	{
		$data = array(
			'MD' => $this->CI->input->post('MD'),
			'PARes' => $this->CI->input->post('PaRes'),
		);

		if (empty($data['MD']) OR empty($data['PARes']))
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}

		$response = Merchant::curl_helper($this->settings['test_mode'] ? self::AUTH_URL_TEST : self::AUTH_URL, $data);
		if ( ! empty($response['error'])) return new Merchant_response(Merchant_response::FAILED, $response['error']);

		return $this->_process_response($response['data'], $params);
	}

	protected function _process_response($response, $params)
	{
		$response = $this->_decode_response($response);

		if (empty($response['Status']))
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}

		$txn_id = empty($response['VPSTxId']) ? NULL : $response['VPSTxId'];
		$message = empty($response['StatusDetail']) ? NULL : $response['StatusDetail'];

		if ($response['Status'] == 'OK')
		{
			return new Merchant_response(Merchant_response::COMPLETED, $message, $txn_id, (double)$params['amount']);
		}

		if ($response['Status'] == '3DAUTH')
		{
			// redirect to card issuer for 3D Authentication
			$data = array(
				'PaReq' => $response['PAReq'],
				'TermUrl' => $params['return_url'],
				'MD' => $response['MD'],
			);
			Merchant::redirect_post($response['ACSURL'], $data, 'Please wait while we redirect you to your card issuer for authentication...');
		}

		return new Merchant_response(Merchant_response::FAILED, $message, $txn_id);
	}

	/**
	 * Convert weird ini-type format into a useful array
	 */
	protected function _decode_response($response)
	{
		$lines = explode("\n", $response);
		$data = array();

		foreach ($lines as $line)
		{
			$line = explode('=', $line, 2);
			if ( ! empty($line[0]))
			{
				$data[trim($line[0])] = isset($line[1]) ? trim($line[1]) : '';
			}
		}

		return $data;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_sagepay_direct.php */