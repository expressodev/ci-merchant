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
 * Documentation: https://cms.paypal.com/cms_content/US/en_US/files/developer/PP_NVPAPI_DeveloperGuide.pdf
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

	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('Authorization');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_paypal_pro_response($response, Merchant_response::AUTHORIZED);
	}

	public function capture()
	{
		$request = $this->_build_capture();
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_paypal_pro_response($response, Merchant_response::COMPLETE);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('Sale');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_paypal_pro_response($response, Merchant_response::COMPLETE);
	}

	public function refund()
	{
		$request = $this->_build_refund();
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_paypal_pro_response($response, Merchant_response::REFUNDED);
	}

	protected function _build_authorize_or_purchase($action)
	{
		$this->require_params('card_no', 'first_name', 'last_name', 'exp_month', 'exp_year', 'csc');

		$request = $this->_new_request('DoDirectPayment');
		$request['PAYMENTACTION'] = $action;
		$request['DESC'] = $this->param('description');
		$request['AMT'] = $this->amount_dollars();
		$request['CURRENCYCODE'] = $this->param('currency');
		$request['CREDITCARDTYPE'] = $this->param('card_type');
		$request['ACCT'] = $this->param('card_no');
		$request['EXPDATE'] = $this->param('exp_month').$this->param('exp_year');
		$request['STARTDATE'] = $this->param('start_month').$this->param('start_year');
		$request['CVV2'] = $this->param('csc');
		$request['ISSUENUMBER'] = $this->param('card_issue');
		$request['IPADDRESS'] = $this->CI->input->ip_address();
		$request['FIRSTNAME'] = $this->param('first_name');
		$request['LASTNAME'] = $this->param('last_name');
		$request['EMAIL'] = $this->param('email');
		$request['STREET'] = $this->param('address1');
		$request['STREET2'] = $this->param('address2');
		$request['CITY'] = $this->param('city');
		$request['STATE'] = $this->param('region');
		$request['ZIP'] = $this->param('postcode');
		$request['COUNTRYCODE'] = strtoupper($this->param('country'));

		return $request;
	}

	protected function _build_capture()
	{
		$this->require_params('reference', 'amount');

		$request = $this->_new_request('DoCapture');
		$request['AMT'] = $this->amount_dollars();
		$request['AUTHORIZATIONID'] = $this->param('reference');
		$request['COMPLETETYPE'] = 'Complete';

		return $request;
	}

	protected function _build_refund()
	{
		$this->require_params('reference');

		$request = $this->_new_request('RefundTransaction');
		$request['TRANSACTIONID'] = $this->param('reference');
		$request['REFUNDTYPE'] = 'Full';

		return $request;
	}

	protected function _new_request($method)
	{
		$request = array();
		$request['METHOD'] = $method;
		$request['VERSION'] = '85.0';
		$request['USER'] = $this->setting('username');
		$request['PWD'] = $this->setting('password');
		$request['SIGNATURE'] = $this->setting('signature');

		return $request;
	}

	protected function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}
}

class Merchant_paypal_pro_response extends Merchant_response
{
	protected $_response;

	public function __construct($response, $success_status)
	{
		$this->_response = array();
		parse_str($response, $this->_response);

		if (isset($this->_response['ACK']) AND
			($this->_response['ACK'] == 'Success' OR $this->_response['ACK'] == 'SuccessWithWarning'))
		{
			// because the paypal response doesn't specify the state of the transaction,
			// we need to specify the status in the constructor
			$this->_status = $success_status;
			$this->_reference = isset($this->_response['REFUNDTRANSACTIONID']) ?
				$this->_response['REFUNDTRANSACTIONID'] :
				$this->_response['TRANSACTIONID'];
		}
		else
		{
			$this->_status = self::FAILED;
			$this->_message = isset($this->_response['L_LONGMESSAGE0']) ?
				$this->_response['L_LONGMESSAGE0'] :
				'invalid_response';
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_paypal_pro.php */