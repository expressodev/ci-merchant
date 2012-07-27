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
 * Merchant DPS PxPost Class
 *
 * Payment processing using DPS PaymentExpress PxPost
 * Documentation: http://www.paymentexpress.com/technical_resources/ecommerce_nonhosted/pxpost.html
 */

class Merchant_dps_pxpost extends Merchant_driver
{
	const PROCESS_URL = 'https://sec.paymentexpress.com/pxpost.aspx';

	public function default_settings()
	{
		return array(
			'username' => '',
			'password' => '',
			'enable_token_billing' => FALSE,
		);
	}

	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('Auth');
		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		return new Merchant_dps_pxpost_response($response);
	}

	public function capture()
	{
		$request = $this->_build_capture_or_refund('Complete');
		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		return new Merchant_dps_pxpost_response($response);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('Purchase');
		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		return new Merchant_dps_pxpost_response($response);
	}

	public function refund()
	{
		$request = $this->_build_capture_or_refund('Refund');
		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		return new Merchant_dps_pxpost_response($response);
	}

	private function _build_authorize_or_purchase($method)
	{
		$this->require_params('card_no', 'name', 'exp_month', 'exp_year', 'csc');

		$request = new SimpleXMLElement('<Txn></Txn>');
		$request->PostUsername = $this->setting('username');
		$request->PostPassword = $this->setting('password');
		$request->TxnType = $method;
		$request->CardNumber = $this->param('card_no');
		$request->CardHolderName = $this->param('name');
		$request->Amount = $this->amount_dollars();
		$request->DateExpiry = $this->param('exp_month').($this->param('exp_year') % 100);
		$request->Cvc2 = $this->param('csc');
		$request->InputCurrency = $this->param('currency');
		$request->MerchantReference = $this->param('description');
		$request->EnableAddBillCard = (int)$this->setting('enable_token_billing');

		return $request;
	}

	private function _build_capture_or_refund($method)
	{
		$this->require_params('reference', 'amount');

		$request = new SimpleXMLElement('<Txn></Txn>');
		$request->PostUsername = $this->setting('username');
		$request->PostPassword = $this->setting('password');
		$request->TxnType = $method;
		$request->DpsTxnRef = $this->param('reference');
		$request->Amount = $this->amount_dollars();

		return $request;
	}
}

class Merchant_dps_pxpost_response extends Merchant_response
{
	protected $_response;

	public function __construct($response)
	{
		$this->_response = simplexml_load_string($response);

		$this->_status = self::FAILED;
		$this->_message = (string)$this->_response->HelpText;
		$this->_reference = (string)$this->_response->DpsTxnRef;

		if ((string)$this->_response->Success == '1')
		{
			switch ((string)$this->_response->Transaction->TxnType)
			{
				case 'Auth':
					$this->_status = self::AUTHORIZED;
					break;
				case 'Complete':
					$this->_status = self::COMPLETE;
					break;
				case 'Purchase':
					$this->_status = self::COMPLETE;
					break;
				case 'Refund':
					$this->_status = self::REFUNDED;
					break;
			}
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dps_pxpost.php */