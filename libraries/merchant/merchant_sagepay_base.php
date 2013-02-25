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
 * Merchant Sage Pay Base Class
 *
 * Shared Sage Pay functions
 */

abstract class Merchant_sagepay_base extends Merchant_driver
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

	public function capture()
	{
		$request = $this->_build_capture();
		return $this->_submit_request($request);
	}

	public function refund()
	{
		$request = $this->_build_refund();
		return $this->_submit_request($request);
	}

	/**
	 * Basic purchase details shared between both Direct and Server methods
	 */
	protected function _build_authorize_or_purchase($method)
	{
		$this->require_params('transaction_id');

		$request = array();
		$request['TxType'] = $method;
		$request['VPSProtocol'] = '2.23';
		$request['Vendor'] = $this->setting('vendor');
		$request['Description'] = $this->param('description');
		$request['Amount'] = $this->amount_dollars();
		$request['Currency'] = $this->param('currency');
		$request['VendorTxCode'] = $this->param('transaction_id');
		$request['ClientIPAddress'] = $this->CI->input->ip_address();
		$request['CustomerEMail'] = $this->param('email');
		$request['ApplyAVSCV2'] = 0; // use account setting
		$request['Apply3DSecure'] = 0; // use account setting

		// billing details
		$request['BillingFirstnames'] = $this->param('first_name');
		$request['BillingSurname'] = $this->param('last_name');
		$request['BillingAddress1'] = $this->param('address1');
		$request['BillingAddress2'] = $this->param('address2');
		$request['BillingCity'] = $this->param('city');
		$request['BillingPostCode'] = $this->param('postcode');
		$request['BillingState'] = $this->param('country') == 'us' ? $this->param('region') : '';
		$request['BillingCountry'] = $this->param('country');
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

		// record the request TxType and VendorTxCode so we can use them in the response class
		$response['TxType'] = $request['TxType'];
		$response['VendorTxCode'] = $request['VendorTxCode'];

		// TermUrl is only needed for 3DAUTH redirects
		$response['TermUrl'] = $this->param('return_url');

		return new Merchant_sagepay_response($response);
	}

	protected function _process_url($service)
	{
		if ($this->setting('simulator_mode'))
		{
			// hooray for consistency
			if ($service == 'vspdirect-register')
			{
				return self::PROCESS_URL_SIM.'/VSPDirectGateway.asp';
			}
			elseif ($service == 'vspserver-register')
			{
				return self::PROCESS_URL_SIM.'/VSPServerGateway.asp?Service=VendorRegisterTx';
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
	 * Decode transaction references, either stored as JSON,
	 * or in our old custom format (VendorTxCode;VPSTxId;SecurityKey;TxAuthNo)
	 */
	protected function _decode_reference($reference)
	{
		// is first character a brace?
		if (strpos($reference, '{') === 0)
		{
			return (object)json_decode($reference, true);
		}
		else
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
}

class Merchant_sagepay_response extends Merchant_response
{
	public function __construct($response)
	{
		// init expected fields to avoid php errors
		$this->_data = array_merge(array(
			'Status' => NULL,
			'StatusDetail' => NULL,
			'VendorTxCode' => NULL,
			'VPSTxId' => NULL,
			'SecurityKey' => NULL,
			'TxType' => NULL,
			'TxAuthNo' => NULL,
		), $response);

		$this->_message = $this->_data['StatusDetail'];

		// do we need to redirect for 3D authentication?
		if ($this->_data['Status'] == '3DAUTH')
		{
			$this->_status = self::REDIRECT;
			$this->_redirect_url = $this->_data['ACSURL'];
			$this->_redirect_method = 'POST';
			$this->_redirect_message = lang('merchant_3dauth_redirect');
			$this->_redirect_data = array(
				'PaReq' => $this->_data['PAReq'],
				'TermUrl' => $this->_data['TermUrl'],
				'MD' => $this->_data['MD'],
			);
		}
		elseif ($this->_data['Status'] == 'OK')
		{
			// record gateway reference
			if ($this->_data['VPSTxId'])
			{
				$this->_reference = json_encode(array(
					'VendorTxCode' => $this->_data['VendorTxCode'],
					'VPSTxId' => $this->_data['VPSTxId'],
					'SecurityKey' => $this->_data['SecurityKey'],
					'TxAuthNo' => $this->_data['TxAuthNo']
				));
			}

			if ( ! empty($this->_data['NextURL']))
			{
				// using server method, please save reference then redirect
				$this->_status = self::REDIRECT;
				$this->_redirect_url = $this->_data['NextURL'];
			}
			else
			{
				// successful response, no redirect
				switch ($this->_data['TxType'])
				{
					case 'DEFERRED':
						$this->_status = self::AUTHORIZED;
						break;
					case 'RELEASE':
						$this->_status = self::COMPLETE;
						break;
					case 'PAYMENT':
						$this->_status = self::COMPLETE;
						break;
					case 'REFUND':
						$this->_status = self::REFUNDED;
						break;
					default:
						// how did this happen?
						$this->_status = self::FAILED;
						break;
				}
			}
		}
		else
		{
			$this->_status = self::FAILED;
			if (empty($this->_message)) $this->_message = lang('merchant_invalid_response');
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_sagepay_base.php */