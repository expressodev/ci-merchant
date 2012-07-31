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
 * Merchant DPS PxPay Class
 *
 * Payment processing using DPS PaymentExpress PxPay (hosted)
 * Documentation: http://www.paymentexpress.com/technical_resources/ecommerce_hosted/pxpay.html
 */

class Merchant_dps_pxpay extends Merchant_driver
{
	const PROCESS_URL = 'https://sec.paymentexpress.com/pxpay/pxaccess.aspx';

	public function default_settings()
	{
		return array(
			'user_id' => '',
			'key' => '',
			'enable_token_billing' => FALSE
		);
	}

	public function authorize()
	{
		return $this->_begin_authorize_or_purchase('Auth');
	}

	public function authorize_return()
	{
		return $this->purchase_return();
	}

	public function purchase()
	{
		return $this->_begin_authorize_or_purchase('Purchase');
	}

	public function purchase_return()
	{
		$result = $this->CI->input->get_post('result');
		if (empty($result))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		// validate dps response
		$request = new SimpleXMLElement('<ProcessResponse></ProcessResponse>');
		$request->PxPayUserId = $this->setting('user_id');
		$request->PxPayKey = $this->setting('key');
		$request->Response = $result;

		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		$xml = simplexml_load_string($response);

		if ((string)$xml->Success == '1')
		{
			if ((string)$xml->TxnType == 'Auth')
			{
				return new Merchant_response(Merchant_response::AUTHORIZED, (string)$xml->ResponseText, (string)$xml->DpsTxnRef);
			}
			elseif ((string)$xml->TxnType == 'Purchase')
			{
				return new Merchant_response(Merchant_response::COMPLETE, (string)$xml->ResponseText, (string)$xml->DpsTxnRef);
			}
		}

		return new Merchant_response(Merchant_response::FAILED, (string)$xml->ResponseText, (string)$xml->DpsTxnRef);
	}

	private function _begin_authorize_or_purchase($method)
	{
		$this->require_params('return_url');

		$request = new SimpleXMLElement('<GenerateRequest></GenerateRequest>');
		$request->PxPayUserId = $this->setting('user_id');
		$request->PxPayKey = $this->setting('key');
		$request->TxnType = $method;
		$request->AmountInput = $this->amount_dollars();
		$request->CurrencyInput = $this->param('currency');
		$request->MerchantReference = $this->param('description');
		$request->UrlSuccess = $this->param('return_url');
		$request->UrlFail = $this->param('return_url');
		$request->EnableAddBillCard = (int)$this->setting('enable_token_billing');

		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		$xml = simplexml_load_string($response);

		// redirect to hosted payment page
		if ((string)$xml['valid'] == '1')
		{
			$this->redirect((string)$xml->URI);
		}
		else
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dps_pxpay.php */