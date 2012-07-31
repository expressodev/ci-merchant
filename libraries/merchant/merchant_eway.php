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
 * Merchant eWAY Class
 *
 * Payment processing using eWAY
 * Documentation: http://www.eway.com.au/Developer/eway-api/hosted-payment-solution.aspx
 */

class Merchant_eway extends Merchant_driver
{
	const PROCESS_URL = 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp';
	const PROCESS_URL_TEST =  'https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp';

	public function default_settings()
	{
		return array(
			'customer_id' => '',
			'test_mode' => FALSE
		);
	}

	public function purchase()
	{
		$request = $this->_build_purchase();
		$response = $this->post_request($this->_process_url(), $request->asXML());
		return new Merchant_eway_response($response);
	}

	protected function _build_purchase()
	{
		// eway thows HTML formatted error if customerid is missing
		if ( ! $this->setting('customer_id'))
		{
			return new Merchant_response(Merchant_response::FAILED, 'Missing Customer ID!');
		}

		$this->require_params('card_no', 'name', 'exp_month', 'exp_year', 'csc');

		$request = new SimpleXMLElement('<ewaygateway></ewaygateway>');
		$request->ewayCustomerID = $this->setting('customer_id');
		$request->ewayCustomerInvoiceDescription = $this->param('description');
		$request->ewayCustomerInvoiceRef = $this->param('order_id');
		$request->ewayTotalAmount = $this->amount_cents();
		$request->ewayCardHoldersName = $this->param('name');
		$request->ewayCardNumber = $this->param('card_no');
		$request->ewayCardExpiryMonth = $this->param('exp_month');
		$request->ewayCardExpiryYear = $this->param('exp_year') % 100;
		$request->ewayCVN = $this->param('csc');
		$request->ewayCustomerFirstName = $this->param('first_name');
		$request->ewayCustomerLastName = $this->param('last_name');
		$request->ewayCustomerEmail = $this->param('email');
		$request->ewayCustomerAddress = trim($this->param('address1')." \n".$this->param('address2'));
		$request->ewayCustomerPostcode = $this->param('postcode');

		// these need to be submitted, otherwise we get errors
		$request->ewayTrxnNumber = '';
		$request->ewayOption1 = '';
		$request->ewayOption2 = '';
		$request->ewayOption3 = '';

		return $request;
	}

	protected function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}
}

class Merchant_eway_response extends Merchant_response
{
	protected $_response;

	public function __construct($response)
	{
		$this->_response = simplexml_load_string($response);

		$this->_status = self::FAILED;
		$this->_message = (string)$this->_response->ewayTrxnError;
		$this->_reference = (string)$this->_response->ewayTrxnNumber;

		if ((string)$this->_response->ewayTrxnStatus == 'True')
		{
			$this->_status = self::COMPLETE;
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_eway.php */