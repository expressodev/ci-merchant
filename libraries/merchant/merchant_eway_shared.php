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

class Merchant_eway_shared extends CI_Driver {

	const PROCESS_URL = 'https://au.ewaygateway.com/Request/';
	const PROCESS_RETURN_URL = 'https://au.ewaygateway.com/Result/';

	public $name = 'eWAY Shared';

	public $required_fields = array('amount', 'currency_code', 'transaction_id', 'reference', 'return_url', 'cancel_url');

	public $settings = array(
		'customer_id' => '',
		'username' => '',
		'company_name' => '',
		'company_logo' => '',
		'page_title' => '',
		'page_banner' => '',
		'page_description' => '',
		'page_footer' => '',
	);

	public $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function _process($params)
	{
		$this->CI->load->helper('url');

		$data = array(
			'CustomerID' => $this->settings['customer_id'],
			'UserName' => $this->settings['username'],
			'Amount' => sprintf('%01.2f', $params['amount']),
			'Currency' => $params['currency_code'],
			'PageTitle' => $this->settings['page_title'],
			'PageDescription' => $this->settings['page_description'],
			'PageFooter' => $this->settings['page_footer'],
			'PageBanner' => $this->settings['page_banner'],
			'Language' => 'EN',
			'CompanyName' => $this->settings['company_name'],
			'CompanyLogo' => $this->settings['company_logo'],
			'CancelUrl' => $params['cancel_url'],
			'ReturnUrl' => $params['return_url'],
			'MerchantReference' => $params['reference'],
		);

		$response = Merchant::curl_helper(self::PROCESS_URL.'?'.http_build_query($data));
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$xml = simplexml_load_string($response['data']);

		// redirect to payment page
		if (empty($xml) OR ! isset($xml->Result))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		elseif ($xml->Result == 'True')
		{
			redirect((string)$xml->URI);
		}
		else
		{
			return new Merchant_response('failed', (string)$xml->Error);
		}
	}

	public function _process_return()
	{
		if (($payment_code = $this->CI->input->get_post('AccessPaymentCode')) === FALSE)
		{
			return new Merchant_response('failed', 'invalid_response');
		}

		$data = array(
			'CustomerID' => $this->settings['customer_id'],
			'UserName' => $this->settings['username'],
			'AccessPaymentCode' => $_REQUEST['AccessPaymentCode'],
		);

		$response = Merchant::curl_helper(self::PROCESS_RETURN_URL.'?'.http_build_query($data));
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$xml = simplexml_load_string($response['data']);

		if ( ! isset($xml->TrxnStatus))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		elseif ($xml->TrxnStatus == 'True')
		{
			return new Merchant_response('authorized', '', (string)$xml->TrxnNumber, (double)$xml->ReturnAmount);
		}
		else
		{
			return new Merchant_response('declined', (string)$xml->TrxnResponseMessage, (string)$xml->TrxnNumber);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_eway_shared.php */