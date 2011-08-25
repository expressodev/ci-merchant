<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011 Crescendo Multimedia Ltd
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
 * Merchant DPS PxPay Class
 *
 * Payment processing using DPS PaymentExpress PxPay (hosted)
 */

class Merchant_dps_pxpay extends CI_Driver {

	const PROCESS_URL = 'https://sec.paymentexpress.com/pxpay/pxaccess.aspx';

	public $name = 'DPS PaymentExpress PxPay';

	public $required_fields = array('email', 'amount', 'reference', 'currency_code', 'return_url', 'cancel_url');

	public $settings = array(
		'user_id' => '',
		'key' => '',
		'enable_token_billing' => FALSE
	);

	public $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function _process($params)
	{
		$this->CI->load->helper('url');

		// ask DPS to generate request url
		$request = '<GenerateRequest>'.
			'<PxPayUserId>'.$this->settings['user_id'].'</PxPayUserId>'.
			'<PxPayKey>'.$this->settings['key'].'</PxPayKey>'.
			'<AmountInput>'.sprintf('%01.2f', $params['amount']).'</AmountInput>'.
			'<CurrencyInput>'.$params['currency_code'].'</CurrencyInput>'.
			'<EmailAddress>'.$params['email'].'</EmailAddress>'.
			'<MerchantReference>'.$params['reference'].'</MerchantReference>'.
			'<TxnType>Purchase</TxnType>'.
			'<UrlSuccess>'.$params['return_url'].'</UrlSuccess>'.
			'<UrlFail>'.$params['cancel_url'].'</UrlFail>'.
			'<EnableAddBillCard>'.(int)$this->settings['enable_token_billing'].'</EnableAddBillCard>'.
			'</GenerateRequest>';

		$response = Merchant::curl_helper(self::PROCESS_URL, $request);
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$xml = simplexml_load_string($response['data']);

		// redirect to hosted payment page
		if (empty($xml) OR ! isset($xml->attributes()->valid))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		elseif ($xml->attributes()->valid == 1)
		{
			redirect((string)$xml->URI);
		}
		else
		{
			return new Merchant_response('failed', (string)$xml->URI);
		}
	}

	public function _process_return()
	{
		if ($this->CI->input->get('result', TRUE) === FALSE) return new Merchant_response('failed', 'invalid_response');

		// validate dps response
		$request = '<ProcessResponse>'.
			'<PxPayUserId>'.$this->settings['user_id'].'</PxPayUserId>'.
			'<PxPayKey>'.$this->settings['key'].'</PxPayKey>'.
			'<Response>'.$this->CI->input->get('result', TRUE).'</Response>'.
			'</ProcessResponse>';

		$response = Merchant::curl_helper(self::PROCESS_URL, $request);
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$xml = simplexml_load_string($response['data']);
		if ( ! isset($xml->Success))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		elseif ($xml->Success == '1')
		{
			return new Merchant_response('authorized', (string)$xml->ResponseText, (string)$xml->DpsTxnRef, (double)$xml->AmountSettlement);
		}
		else
		{
			return new Merchant_response('declined', (string)$xml->ResponseText, (string)$xml->DpsTxnRef);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dps_pxpay.php */