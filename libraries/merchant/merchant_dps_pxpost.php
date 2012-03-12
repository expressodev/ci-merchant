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
 * Merchant DPS PxPost Class
 *
 * Payment processing using DPS PaymentExpress PxPost
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
		$xml = simplexml_load_string($response);

		if ($xml->Success == '1')
		{
			return new Merchant_response(Merchant_response::AUTHORIZED, (string)$xml->HelpText, (string)$xml->DpsTxnRef);
		}

		return new Merchant_response(Merchant_response::FAILED, (string)$xml->HelpText, (string)$xml->DpsTxnRef);
	}

	public function capture()
	{
		$request = $this->_build_capture_or_refund('Complete');
		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		$xml = simplexml_load_string($response);

		if ($xml->Success == '1')
		{
			return new Merchant_response(Merchant_response::COMPLETED, (string)$xml->HelpText, (string)$xml->DpsTxnRef);
		}

		return new Merchant_response(Merchant_response::FAILED, (string)$xml->HelpText, (string)$xml->DpsTxnRef);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('Purchase');
		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		$xml = simplexml_load_string($response);

		if ($xml->Success == '1')
		{
			return new Merchant_response(Merchant_response::COMPLETED, (string)$xml->HelpText, (string)$xml->DpsTxnRef);
		}

		return new Merchant_response(Merchant_response::FAILED, (string)$xml->HelpText, (string)$xml->DpsTxnRef);
	}

	public function refund()
	{
		$request = $this->_build_capture_or_refund('Refund');
		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		$xml = simplexml_load_string($response);

		if ($xml->Success == '1')
		{
			return new Merchant_response(Merchant_response::REFUNDED, (string)$xml->HelpText, (string)$xml->DpsTxnRef);
		}

		return new Merchant_response(Merchant_response::FAILED, (string)$xml->HelpText, (string)$xml->DpsTxnRef);
	}

	private function _build_authorize_or_purchase($method)
	{
		$this->require_params('card_no', 'card_name', 'exp_month', 'exp_year', 'csc', 'reference');

		$request = new SimpleXMLElement('<Txn></Txn>');
		$request->PostUsername = $this->setting('username');
		$request->PostPassword = $this->setting('password');
		$request->TxnType = $method;
		$request->CardHolderName = $this->param('card_name');
		$request->CardNumber = $this->param('card_no');
		$request->Amount = $this->amount_dollars();
		$request->DateExpiry = $this->param('exp_month').($this->param('exp_year') % 100);
		$request->Cvc2 = $this->param('csc');
		$request->InputCurrency = $this->param('currency');
		$request->MerchantReference = $this->param('reference');
		$request->EnableAddBillCard = (int)$this->setting('enable_token_billing');

		return $request;
	}

	private function _build_capture_or_refund($method)
	{
		$this->require_params('transaction_id', 'amount');

		$request = new SimpleXMLElement('<Txn></Txn>');
		$request->PostUsername = $this->setting('username');
		$request->PostPassword = $this->setting('password');
		$request->TxnType = $method;
		$request->DpsTxnRef = $this->param('transaction_id');
		$request->Amount = $this->amount_dollars();

		return $request;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dps_pxpost.php */