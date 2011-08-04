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
 * Merchant eWAY External Class
 *
 * Payment processing using eWAY's Secure Hosted Page
 */

class Merchant_eway_external extends CI_Driver {

	public $name = 'eWAY External (Hosted)';

	public $required_fields = array('amount', 'currency_code', 'transaction_id', 'reference', 'return_url', 'cancel_url');

	public $settings = array(
		'eWAYCustomerID' => '',
		'UserName' => '',
		'CompanyName' => '',
		'PageTitle' => '',
		'PageDescription' => '',
		'PageFooter' => '',
		'CompanyLogo' => '',
		'PageBanner' => '',
		'test_mode' => FALSE
	);

	const PROCESS_URL = 'https://au.ewaygateway.com/Request/';
	const PROCESS_RETURN_URL = 'https://au.ewaygateway.com/Result/';

	public function _process($params)
	{
		if ($params['currency_code'] !== 'AUD')	// check currency code is AUD
		{
			return new Merchant_response('failed', 'Required field "currency_code" was not in AUD.');
		}
		$params['cancel_url'] = str_replace('http://', 'https://', rtrim($params['cancel_url'], '/')).'.php';
		$params['return_url'] = str_replace('http://', 'https://', rtrim($params['return_url'], '/')).'.php';

		$post_url  = self::PROCESS_URL;
		$post_url .= '?CustomerID='.urlencode($this->settings['eWAYCustomerID']);
		$post_url .= '&UserName='.urlencode($this->settings['UserName']);
		$post_url .= '&Amount='.urlencode($params['amount']);
		$post_url .= '&Currency='.urlencode($params['currency_code']);
		$post_url .= '&PageTitle='.urlencode($this->settings['PageTitle']);
	    $post_url .= '&PageDescription='.urlencode($this->settings['PageDescription']);
		$post_url .= '&PageFooter='.urlencode($this->settings['PageFooter']);
		$post_url .= '&Language=EN';
		$post_url .= '&CompanyName='.urlencode($this->settings['CompanyName']);
		$post_url .= '&InvoiceDescription='.urlencode($params['reference']);
		$post_url .= '&CancelUrl='.urlencode($params['cancel_url']);
		$post_url .= '&ReturnUrl='.urlencode($params['return_url']);
		$post_url .= '&CompanyLogo='.urlencode($this->settings['CompanyLogo']);
		$post_url .= '&PageBanner='.urlencode($this->settings['PageBanner']);
		$post_url .= '&MerchantReference='.urlencode($params['transaction_id']);
		$post_url .= '&MerchantInvoice='.urlencode($params['reference']);
		$post_url .= '&MerchantOption1=';
		$post_url .= '&MerchantOption2=';
		$post_url .= '&MerchantOption3=';
		$post_url .= '&ModifiableCustomerDetails=';

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $post_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($curl);
		curl_close($curl);

		$xml = simplexml_load_string($response);

		if ($xml->Result == 'True')
		{
			header("location: ".$xml->URI);
		  	exit;
		}
		else
		{
			return new Merchant_response('failed', 'Error - '.(string)$xml->Error, (string)$params['transaction_id']);
		}
	}

	public function _process_return()
	{
		$post_url = self::PROCESS_RETURN_URL;
		$post_url .= '?CustomerID='.urlencode($this->settings['ewayCustomerID']).'&UserName='.urlencode($this->settings['UserName']).'&AccessPaymentCode='.urlencode($_REQUEST['AccessPaymentCode']);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $post_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($curl);
		curl_close($curl);
		$xml = simplexml_load_string($response);

		if ( ! isset($xml->TrxnStatus))
		{
			return new Merchant_response('failed', 'invalid_response - '.$xml);
		}
		elseif ($xml->TrxnStatus == 'True')
		{
			return new Merchant_response('authorized', 'AuthCode - '.(string)$xml->AuthCode.', ResponseCode - '.(string)$xml->ResponseCode.', ResponseMessage - '.$xml->TrxnResponseMessage, (string)$xml->MerchantReference, (string)$xml->ReturnAmount);
		}
		else
		{
			return new Merchant_response('declined', 'ErrorCode - '.(string)$xml->ResponseCode.', ErrorMessage - '.$xml->ErrorMessage, (string)$xml->MerchantReference);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_eway_external.php */