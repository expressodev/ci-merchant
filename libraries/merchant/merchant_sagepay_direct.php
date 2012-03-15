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
 * Merchant SagePay Direct Class
 *
 * Payment processing using SagePay Direct
 */

class Merchant_sagepay_direct extends Merchant_driver
{
	const PROCESS_URL = 'https://live.sagepay.com/gateway/service';
	const PROCESS_URL_TEST = 'https://test.sagepay.com/gateway/service';
	const PROCESS_URL_SIM = 'https://test.sagepay.com/Simulator';

	public function default_settings()
	{
		return array(
			'vendor' => '',
			'test_mode' => FALSE,
			'simulator_mode' => FALSE,
		);
	}

	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('DEFERRED');
		return $this->_submit_request($request);
	}

	public function authorize_return()
	{
		return $this->_direct3d_return(Merchant_response::AUTHORIZED);
	}

	public function capture()
	{
		$request = $this->_build_capture();
		return $this->_submit_request($request);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('PAYMENT');
		return $this->_submit_request($request);
	}

	/**
	 * Only used for returning from Direct 3D Authentication
	 */
	public function purchase_return()
	{
		return $this->_direct3d_return(Merchant_response::COMPLETE);
	}

	public function refund()
	{
		$request = $this->_build_refund();
		return $this->_submit_request($request);
	}

	protected function _build_authorize_or_purchase($method)
	{
		$this->require_params('transaction_id', 'card_no', 'name', 'card_type',
			'exp_month', 'exp_year', 'csc');

		$request = array();
		$request['TxType'] = $method;
		$request['VPSProtocol'] = '2.23';
		$request['Vendor'] = $this->setting('vendor');
		$request['Description'] = $this->param('description');
		$request['Amount'] = $this->amount_dollars();
		$request['Currency'] = $this->param('currency');
		$request['VendorTxCode'] = $this->param('transaction_id');
		$request['CardHolder'] = $this->param('name');
		$request['CardNumber'] = $this->param('card_no');
		$request['CV2'] = $this->param('csc');
		$request['IssueNumber'] = $this->param('card_issue');
		$request['ExpiryDate'] = $this->param('exp_month').($this->param('exp_year') % 100);
		$request['ClientIPAddress'] = $this->CI->input->ip_address();
		$request['CustomerEMail'] = $this->param('email');
		$request['ApplyAVSCV2'] = 0; // use account setting
		$request['Apply3DSecure'] = 0; // use account setting

		$request['CardType'] = strtoupper($this->param('card_type'));
		if ($request['CardType'] == 'MASTERCARD')
		{
			$request['CardType'] = 'MC';
		}

		if ($this->param('start_month') AND $this->param('start_year'))
		{
			$request['StartDate'] = $this->param('start_month').($this->param('start_year') % 100);
		}

		// billing details
		$request['BillingFirstnames'] = $this->param('first_name');
		$request['BillingSurname'] = $this->param('last_name');
		$request['BillingAddress1'] = $this->param('address1');
		$request['BillingAddress2'] = $this->param('address2');
		$request['BillingCity'] = $this->param('city');
		$request['BillingPostCode'] = $this->param('postcode');
		$request['BillingState'] = $this->param('region');
		$request['BillingCountry'] = $this->param('country') == 'uk' ? 'gb' : $this->param('country');
		$request['BillingPhone'] = $this->param('phone');

		// shipping details
		foreach (array('Firstnames', 'Surname', 'Address1', 'Address2', 'City', 'PostCode',
			'State', 'Country', 'Phone') as $field)
		{
			$request["Delivery$field"] = $request["Billing$field"];
		}

		return $request;
	}

	protected function _build_capture()
	{
		$this->require_params('reference', 'amount');

		$reference = $this->_decode_reference($this->param('reference'));

		$request = array();
		$request['TxType'] = 'RELEASE';
		$request['VPSProtocol'] = '2.23';
		$request['Vendor'] = $this->setting('vendor');
		$request['ReleaseAmount'] = $this->amount_dollars();
		$request['VendorTxCode'] = $reference->VendorTxCode;
		$request['VPSTxId'] = $reference->VPSTxId;
		$request['SecurityKey'] = $reference->SecurityKey;
		$request['TxAuthNo'] = $reference->TxAuthNo;

		return $request;
	}

	protected function _build_refund()
	{
		$this->require_params('reference', 'amount');

		$reference = $this->_decode_reference($this->param('reference'));

		$request = array();
		$request['TxType'] = 'REFUND';
		$request['VPSProtocol'] = '2.23';
		$request['Vendor'] = $this->setting('vendor');
		$request['Amount'] = $this->amount_dollars();
		$request['Currency'] = $this->param('currency');
		$request['Description'] = $this->param('description');
		$request['RelatedVendorTxCode'] = $reference->VendorTxCode;
		$request['RelatedVPSTxId'] = $reference->VPSTxId;
		$request['RelatedSecurityKey'] = $reference->SecurityKey;
		$request['RelatedTxAuthNo'] = $reference->TxAuthNo;

		// VendorTxCode must be unique for the refund
		$request['VendorTxCode'] = $this->param('transaction_id').'-'.mt_rand(100, 999);

		return $request;
	}

	protected function _submit_request($request)
	{
		$process_url = $this->_process_url($request['TxType']);
		$response = $this->post_request($process_url, $request);
		$response = $this->_decode_response($response);

		// do we need to redirect for 3D authentication?
		if (isset($response['Status']) AND $response['Status'] == '3DAUTH')
		{
			$data = array(
				'PaReq' => $response['PAReq'],
				'TermUrl' => $this->param('return_url'),
				'MD' => $response['MD'],
			);

			$this->post_redirect($response['ACSURL'], $data,
				'Please wait while we redirect you to your card issuer for authentication...');
		}

		// record the VendorTxCode so we can use it for capture/refunds
		$response['VendorTxCode'] = $request['VendorTxCode'];

		switch ($request['TxType'])
		{
			case 'DEFERRED':
				$success_status = Merchant_response::AUTHORIZED;
				break;
			case 'RELEASE':
				$success_status = Merchant_response::COMPLETE;
				break;
			case 'PAYMENT':
				$success_status = Merchant_response::COMPLETE;
				break;
			case 'REFUND':
				$success_status = Merchant_response::REFUNDED;
				break;
		}

		return new Merchant_sagepay_direct_response($response, $success_status);
	}

	protected function _process_url($service)
	{
		$service = strtolower($service);
		if ($service == 'payment' OR $service == 'deferred')
		{
			$service = 'vspdirect-register';
		}

		if ($this->setting('simulator_mode'))
		{
			// hooray for consistency
			if ($service == 'vspdirect-register')
			{
				return self::PROCESS_URL_SIM.'/VSPDirectGateway.asp';
			}
			elseif ($service == 'direct3dcallback')
			{
				return self::PROCESS_URL_SIM.'/VSPDirectCallback.asp';
			}

			return self::PROCESS_URL_SIM.'/VSPServerGateway.asp?Service=Vendor'.ucfirst($service).'Tx';
		}

		if ($this->setting('test_mode'))
		{
			return self::PROCESS_URL_TEST."/$service.vsp";
		}

		return self::PROCESS_URL."/$service.vsp";
	}

	protected function _direct3d_return($success_status)
	{
		$data = array(
			'MD' => $this->CI->input->post('MD'),
			'PARes' => $this->CI->input->post('PaRes'), // inconsistent caps are intentional
		);

		if (empty($data['MD']) OR empty($data['PARes']))
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}

		$response = $this->post_request($this->_process_url('direct3dcallback'), $data);
		$response = $this->_decode_response($response);

		// record the VendorTxCode so we can use it for capture/refunds
		$response['VendorTxCode'] = $this->param('transaction_id');

		return new Merchant_sagepay_direct_response($response, $success_status);
	}

	/**
	 * Convert ini-style response into a useful array
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

	/**
	 * Decode transaction references stored in our custom format
	 * VendorTxCode;VPSTxId;SecurityKey;TxAuthNo
	 */
	protected function _decode_reference($reference)
	{
		$reference = explode(';', $reference);

		return (object)array(
			'VendorTxCode' => isset($reference[0]) ? $reference[0] : NULL,
			'VPSTxId' => isset($reference[1]) ? $reference[1] : NULL,
			'SecurityKey' => isset($reference[2]) ? $reference[2] : NULL,
			'TxAuthNo' => isset($reference[3]) ? $reference[3] : NULL,
		);
	}
}

class Merchant_sagepay_direct_response extends Merchant_response
{
	protected $_response;

	public function __construct($response, $success_status)
	{
		// init expected fields to avoid php errors
		$this->_response = array_merge(array(
			'Status' => NULL,
			'StatusDetail' => NULL,
			'VendorTxCode' => NULL,
			'VPSTxId' => NULL,
			'SecurityKey' => NULL,
			'TxAuthNo' => NULL,
			), $response);

		$this->_message = $this->_response['StatusDetail'];

		if ($this->_response['Status'] == 'OK')
		{
			$this->_status = $success_status;

			if ($this->_response['VPSTxId'])
			{
				$this->_reference = implode(';', array($this->_response['VendorTxCode'],
					$this->_response['VPSTxId'],
					$this->_response['SecurityKey'],
					$this->_response['TxAuthNo']));
			}
		}
		else
		{
			$this->_status = self::FAILED;
			if (empty($this->_message)) $this->_message = 'invalid_response';
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_sagepay_direct.php */