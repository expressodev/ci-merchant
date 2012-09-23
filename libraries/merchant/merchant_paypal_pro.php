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

require_once(MERCHANT_DRIVER_PATH.'/merchant_paypal_base.php');

/**
 * Merchant Paypal Pro Class
 *
 * Payment processing using Paypal Payments Pro
 * Documentation: https://cms.paypal.com/cms_content/US/en_US/files/developer/PP_NVPAPI_DeveloperGuide.pdf
 */

class Merchant_paypal_pro extends Merchant_paypal_base
{
	public function default_settings()
	{
		return array(
			'username' => '',
			'password' => '',
			'signature' => '',
			'test_mode' => FALSE,
		);
	}
	
	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('Authorization');
		$response = $this->_post_paypal_request($request);
		return new Merchant_paypal_api_response($response, Merchant_response::AUTHORIZED);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('Sale');
		$response = $this->_post_paypal_request($request);
		return new Merchant_paypal_api_response($response, Merchant_response::COMPLETE);
	}

	protected function _build_authorize_or_purchase($action)
	{
		$this->require_params('card_no', 'first_name', 'last_name', 'exp_month', 'exp_year', 'csc');

		$request = $this->_new_request('DoDirectPayment');
		$this->_add_request_details($request, $action);

		// add credit card details
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
}

/* End of file ./libraries/merchant/drivers/merchant_paypal_pro.php */