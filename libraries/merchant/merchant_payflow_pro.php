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
 * Merchant Payflow Pro Class
 *
 * Payment processing using Payflo Pro
 * Documentation: https://cms.paypal.com/cms_content/US/en_US/files/developer/PayflowGateway_Guide.pdf
 */

class Merchant_payflow_pro extends Merchant_driver
{
	const PROCESS_URL = 'https://payflowpro.paypal.com';
	const PROCESS_URL_TEST = 'https://pilot-payflowpro.paypal.com';

	public function default_settings()
	{
		return array(
			'vendor' => '',
			'username' => '',
			'password' => '',
			'partner' => 'PayPal',
			'test_mode' => FALSE
		);
	}

	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('A');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_payflow_pro_response($response, Merchant_response::AUTHORIZED);
	}

	public function capture()
	{
		$request = $this->_build_capture_or_refund('D');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_payflow_pro_response($response, Merchant_response::COMPLETE);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('S');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_payflow_pro_response($response, Merchant_response::COMPLETE);
	}

	public function refund()
	{
		$request = $this->_build_capture_or_refund('C');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_payflow_pro_response($response, Merchant_response::REFUNDED);
	}

	protected function _build_authorize_or_purchase($method)
	{
		$this->require_params('card_no', 'first_name', 'last_name', 'exp_month', 'exp_year', 'csc');

		$request = $this->_new_request($method);
		$request['TENDER'] = 'C';
		$request['ACCT'] = $this->param('card_no');
		$request['AMT'] = $this->amount_dollars();
		$request['EXPDATE'] = $this->param('exp_month').($this->param('exp_year') % 100);
		$request['COMMENT1'] = $this->param('description');
		$request['CVV2'] = $this->param('csc');
		$request['BILLTOFIRSTNAME'] = $this->param('first_name');
		$request['BILLTOLASTNAME'] = $this->param('last_name');
		$request['BILLTOSTREET'] = trim($this->param('address1')." \n".$this->param('address2'));
		$request['BILLTOCITY'] = $this->param('city');
		$request['BILLTOSTATE'] = $this->param('region');
		$request['BILLTOZIP'] = $this->param('postcode');
		$request['BILLTOCOUNTRY'] = $this->param('country');

		return $request;
	}

	protected function _build_capture_or_refund($method)
	{
		$this->require_params('reference', 'amount');

		$request = $this->_new_request($method);
		$request['AMT'] = $this->amount_dollars();
		$request['ORIGID'] = $this->param('reference');

		return $request;
	}

	protected function _new_request($method)
	{
		$request = array();
		$request['TRXTYPE'] = $method;
		$request['VENDOR'] = $this->setting('vendor');
		$request['PWD'] = $this->setting('password');
		$request['PARTNER'] = $this->setting('partner');

		if ($this->setting('username'))
		{
			$request['USER'] = $this->setting('username');
		}
		else
		{
			$request['USER'] = $this->setting('vendor');
		}

		return $request;
	}

	protected function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}
}

class Merchant_payflow_pro_response extends Merchant_response
{
	protected $_response;

	public function __construct($response, $success_status)
	{
		$this->_response = array();
		parse_str($response, $this->_response);

		if (isset($this->_response['RESULT']) AND $this->_response['RESULT'] == '0')
		{
			// because the payflow response doesn't specify the state of the transaction,
			// we need to specify the expected status in the constructor
			$this->_status = $success_status;
			$this->_reference = $this->_response['PNREF'];
		}
		else
		{
			$this->_status = self::FAILED;
			$this->_message = isset($this->_response['RESPMSG']) ?
				$this->_response['RESPMSG'] :
				lang('merchant_invalid_response');
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_payflow_pro.php */