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
 * Merchant Netaxept Class
 *
 * Payment processing using Nets (BBS) Netaxept
 */

class Merchant_netaxept extends Merchant_driver
{
	public $required_fields = array('amount', 'reference', 'currency_code', 'return_url');

	public $settings = array(
		'merchant_id' => '',
		'token' => '',
		'test_mode' => FALSE
	);

	const PROCESS_URL = 'https://epayment.bbs.no';
	const PROCESS_URL_TEST = 'https://epayment-test.bbs.no';

	public $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function process($params)
	{
		$data = array(
			'merchantId' => $this->settings['merchant_id'],
			'token' => $this->settings['token'],
			'serviceType' => 'B',
			'orderNumber' => $params['reference'],
			'currencyCode' => $params['currency_code'],
			'amount' => round($params['amount'] * 100),
			'redirectUrl' => $params['return_url'],
//			'redirectOnError' => 'false',
		);

		// optional customer details
		if (isset($params['card_name']))
		{
			$names = explode(' ', $params['card_name'], 2);
			$data['customerFirstName'] = $names[0];
			$data['customerLastName'] = isset($names[1]) ? $names[1] : '';
		}

		if (isset($params['email'])) $data['customerEmail'] = $params['email'];
		if (isset($params['phone'])) $data['customerPhoneNumber'] = $params['phone'];
		if (isset($params['address'])) $data['customerAddress1'] = $params['address'];
		if (isset($params['address2'])) $data['customerAddress2'] = $params['address2'];
		if (isset($params['postcode'])) $data['customerPostcode'] = $params['postcode'];
		if (isset($params['city'])) $data['customerTown'] = $params['city'];
		if (isset($params['country'])) $data['customerCountry'] = $params['country'];

		$response = Merchant::curl_helper($this->_process_url().'/Netaxept/Register.aspx?'.http_build_query($data));
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$response = $this->_decode_response($response['data']);
		if (get_class($response) == 'Merchant_response') return $response;

		// redirect to payment page
		$redirect_data = array(
			'merchantId' => $this->settings['merchant_id'],
			'transactionId' => (string)$response->TransactionId,
		);

		$this->CI->load->helper('url');
		redirect($this->_process_url().'/Terminal/Default.aspx?'.http_build_query($redirect_data));
	}

	public function process_return($params)
	{
		$response_code = $this->CI->input->get('responseCode');
		if (empty($response_code))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		elseif ($response_code != 'OK')
		{
			return new Merchant_response('declined', $response_code);
		}

		$data = array(
			'merchantId' => $this->settings['merchant_id'],
			'token' => $this->settings['token'],
			'transactionId' => $this->CI->input->get('transactionId'),
			'operation' => 'AUTH',
		);

		$response = Merchant::curl_helper($this->_process_url().'/Netaxept/Process.aspx?'.http_build_query($data));
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$response = $this->_decode_response($response['data']);
		if (get_class($response) == 'Merchant_response') return $response;

		if ((string)$response->ResponseCode != 'OK')
		{
			return new Merchant_response('declined', (string)$response->ResponseCode);
		}

		return new Merchant_response('authorized', (string)$response->ResponseCode, (string)$response->TransactionId, $params['amount']);
	}

	protected function _process_url()
	{
		return $this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}

	/**
	 * Returns XML response if response is valid, otherwise a Merchant_response object
	 */
	protected function _decode_response($response)
	{
		$xml = simplexml_load_string($response);
		if (empty($xml))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		if (isset($xml->Error) AND isset($xml->Error->Message))
		{
			return new Merchant_response('failed', (string)$xml->Error->Message);
		}
		if (empty($xml->TransactionId))
		{
			return new Merchant_response('failed', 'invalid_response');
		}

		return $xml;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_netaxept.php */