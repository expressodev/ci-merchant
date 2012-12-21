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
 * Merchant eWAY Shared Class
 *
 * Payment processing using eWAY's Secure Hosted Page
 * @see http://www.eway.com.au/_files/documentation/HostedPaymentPageDoc.pdf
 */

class Merchant_eway_shared extends Merchant_driver
{
	public function default_settings()
	{
		return array(
			'customer_id' => '',
			'username' => '',
			'company_name' => '',
			'company_logo' => '',
			'page_title' => '',
			'page_banner' => '',
			'page_description' => '',
			'page_footer' => '',
		);
	}

	public function purchase()
	{
		$request = $this->_build_purchase();
		$response = $this->get_request($this->_process_url().'?'.http_build_query($request));
		$xml = simplexml_load_string($response);

		if ((string)$xml->Result == 'True')
		{
			$this->redirect((string)$xml->URI);
		}

		return new Merchant_response(Merchant_response::FAILED, (string)$xml->Error);
	}

	public function purchase_return()
	{
		$payment_code = $this->CI->input->get_post('AccessPaymentCode');
		if (empty($payment_code))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		$data = array(
			'CustomerID' => $this->setting('customer_id'),
			'UserName' => $this->setting('username'),
			'AccessPaymentCode' => $payment_code,
		);

		$response = $this->get_request($this->_process_return_url().'?'.http_build_query($data));
		$xml = simplexml_load_string($response);

		if (strtolower((string)$xml->TrxnStatus) == 'true')
		{
			return new Merchant_response(Merchant_response::COMPLETE, NULL, (string)$xml->TrxnNumber);
		}

		return new Merchant_response(Merchant_response::FAILED,
			(string)$xml->TrxnResponseMessage,
			(string)$xml->TrxnNumber);
	}

	protected function _build_purchase()
	{
		$this->require_params('return_url', 'cancel_url');

		$request = array();
		$request['CustomerID'] = $this->setting('customer_id');
		$request['UserName'] = $this->setting('username');
		$request['Amount'] = $this->amount_dollars();
		$request['Currency'] = $this->param('currency');
		$request['PageTitle'] = $this->setting('page_title');
		$request['PageDescription'] = $this->setting('page_description');
		$request['PageFooter'] = $this->setting('page_footer');
		$request['PageBanner'] = $this->setting('page_banner');
		$request['Language'] = 'EN';
		$request['CompanyName'] = $this->setting('company_name');
		$request['CompanyLogo'] = $this->setting('company_logo');
		$request['CancelUrl'] = $this->param('cancel_url');
		$request['ReturnUrl'] = $this->param('return_url');
		$request['MerchantReference'] = $this->param('description');
		$request['CustomerFirstName'] = $this->param('first_name');
		$request['CustomerLastName'] = $this->param('last_name');
		$request['CustomerAddress'] = trim($this->param('address1')." \n".$this->param('address2'));
		$request['CustomerCity'] = $this->param('city');
		$request['CustomerState'] = $this->param('region');
		$request['CustomerPostCode'] = $this->param('postcode');
		$request['CustomerCountry'] = $this->param('country');
		$request['CustomerEmail'] = $this->param('email');
		$request['CustomerPhone'] = $this->param('phone');

		return $request;
	}

	protected function _process_url()
	{
		return 'https://au.ewaygateway.com/Request/';
	}

	protected function _process_return_url()
	{
		return 'https://au.ewaygateway.com/Result/';
	}
}

/* End of file ./libraries/merchant/drivers/merchant_eway_shared.php */