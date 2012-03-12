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

	public function default_settings()
	{
		return array(
			'vendor' => '',
			'test_mode' => FALSE,
			'simulator' => FALSE,
		);
	}

	public function purchase()
	{
		$this->require_params('card_no', 'card_name', 'card_type',
			'exp_month', 'exp_year', 'csc', 'reference');

		$data = array(
			'VPSProtocol' => '2.23',
			'TxType' => 'PAYMENT',
			'Vendor' => $this->setting('vendor'),
			'Description' => $this->param('reference'),
			'Amount' => sprintf('%01.2f', $this->param('amount')),
			'Currency' => $this->param('currency'),
			'CardHolder' => $this->param('card_name'),
			'CardNumber' => $this->param('card_no'),
			'CV2' => $this->param('csc'),
			'CardType' => strtoupper($this->param('card_type')),
			'ExpiryDate' => $this->param('exp_month').($this->param('exp_year') % 100),
			'ClientIPAddress' => $this->CI->input->ip_address(),
			'ApplyAVSCV2' => 0,
			'Apply3DSecure' => 0,
		);

		// SagePay requires a unique VendorTxCode for each transaction
		$data['VendorTxCode'] = $this->param('transaction_id').'-'.mt_rand(100000000, 999999999);

		if ($data['CardType'] == 'MASTERCARD') $data['CardType'] = 'MC';

		if ($this->param('card_name'))
		{
			$names = explode(' ', $this->param('card_name'), 2);
			$data['BillingFirstnames'] = $names[0];
			$data['BillingSurname'] = isset($names[1]) ? $names[1] : '';
			$data['DeliveryFirstnames'] = $data['BillingFirstnames'];
			$data['DeliverySurname'] = $data['BillingSurname'];
		}

		if ($this->param('email')) $data['CustomerEMail'] = $this->param('email');

		foreach (array(
				'Address1' => 'address',
				'Address2' => 'address2',
				'City' => 'city',
				'PostCode' => 'postcode',
				'State' => 'region',
				'Phone' => 'phone',
			) as $field => $param)
		{
			if ($this->param($param))
			{
				$data["Billing$field"] = $this->param($param);
				$data["Delivery$field"] = $this->param($param);
			}
		}

		if ($this->param('country'))
		{
			$data['BillingCountry'] = $this->param('country') == 'uk' ? 'gb' : $this->param('country');
			$data['DeliveryCountry'] = $data['BillingCountry'];
		}

		if ($this->param('card_issue'))
		{
			$data['IssueNumber'] = $this->param('card_issue');
		}

		if ($this->param('start_month') AND $this->param('start_year'))
		{
			$data['StartDate'] = $this->param('start_month').($this->param('start_year') % 100);
		}

		if ($this->setting('simulator'))
		{
			$process_url = self::PROCESS_URL_SIM;
		}
		elseif ($this->setting('test_mode'))
		{
			$process_url = self::PROCESS_URL_TEST;
		}
		else
		{
			$process_url = self::PROCESS_URL;
		}

		$response = Merchant::curl_helper($process_url, $data);
		if ( ! empty($response['error'])) return new Merchant_response(Merchant_response::FAILED, $response['error']);

		return $this->_process_response($response['data']);
	}

	/**
	 * Only used for returning from 3D Secure Authentication
	 */
	public function purchase_return()
	{
		$data = array(
			'MD' => $this->CI->input->post('MD'),
			'PARes' => $this->CI->input->post('PaRes'),
		);

		if (empty($data['MD']) OR empty($data['PARes']))
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}

		if ($this->setting('simulator'))
		{
			$auth_url = self::AUTH_URL_SIM;
		}
		elseif ($this->setting('test_mode'))
		{
			$auth_url = self::AUTH_URL_TEST;
		}
		else
		{
			$auth_url = self::AUTH_URL;
		}

		$response = Merchant::curl_helper($auth_url, $data);
		if ( ! empty($response['error'])) return new Merchant_response(Merchant_response::FAILED, $response['error']);

		return $this->_process_response($response['data']);
	}

	protected function _process_response($response)
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
			return new Merchant_response(Merchant_response::COMPLETED, $message, $txn_id, (double)$this->param('amount'));
		}

		if ($response['Status'] == '3DAUTH')
		{
			// redirect to card issuer for 3D Authentication
			$data = array(
				'PaReq' => $response['PAReq'],
				'TermUrl' => $this->param('return_url'),
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