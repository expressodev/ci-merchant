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
 * Merchant Netaxept Class
 *
 * Payment processing using Nets (BBS) Netaxept
 * @link http://www.betalingsterminal.no/Netthandel-forside/Teknisk-veiledning/Overview/
 */

class Merchant_netaxept extends Merchant_driver
{
	const PROCESS_URL = 'https://epayment.bbs.no';
	const PROCESS_URL_TEST = 'https://epayment-test.bbs.no';

	public function default_settings()
	{
		return array(
			'merchant_id' => '',
			'token' => '',
			'test_mode' => FALSE
		);
	}

	public function purchase()
	{
		$this->require_params('return_url');

		$request = array();
		$request['merchantId'] = $this->setting('merchant_id');
		$request['token'] = $this->setting('token');
		$request['serviceType'] = 'B';
		$request['orderNumber'] = $this->param('order_id');
		$request['currencyCode'] = $this->param('currency');
		$request['amount'] = $this->amount_cents();
		$request['redirectUrl'] = $this->param('return_url');
		$request['customerFirstName'] = $this->param('first_name');
		$request['customerLastName'] = $this->param('last_name');
		$request['customerEmail'] = $this->param('email');
		$request['customerPhoneNumber'] = $this->param('phone');
		$request['customerAddress1'] = $this->param('address1');
		$request['customerAddress2'] = $this->param('address2');
		$request['customerPostcode'] = $this->param('postcode');
		$request['customerTown'] = $this->param('city');
		$request['customerCountry'] = $this->param('country');

		$response = $this->get_request($this->_process_url().'/Netaxept/Register.aspx?'.http_build_query($request));
		$response_xml = $this->_decode_response($response);

		// redirect to payment page
		$redirect_data = array(
			'merchantId' => $this->setting('merchant_id'),
			'transactionId' => (string)$response_xml->TransactionId,
		);

		$this->redirect($this->_process_url().'/Terminal/Default.aspx?'.http_build_query($redirect_data));
	}

	public function purchase_return()
	{
		$response_code = $this->CI->input->get('responseCode');
		if (empty($response_code))
		{
			throw new Merchant_exception(lang('merchant_invalid_response'));
		}
		elseif ($response_code != 'OK')
		{
			throw new Merchant_exception($response_code);
		}

		$request = array(
			'merchantId' => $this->setting('merchant_id'),
			'token' => $this->setting('token'),
			'transactionId' => $this->CI->input->get('transactionId'),
			'operation' => 'AUTH',
		);

		$response = $this->get_request($this->_process_url().'/Netaxept/Process.aspx?'.http_build_query($request));
		$xml = $this->_decode_response($response);

		if ((string)$xml->ResponseCode != 'OK')
		{
			return new Merchant_response(Merchant_response::FAILED, (string)$xml->ResponseCode);
		}

		return new Merchant_response(Merchant_response::COMPLETE, (string)$xml->ResponseCode, (string)$xml->TransactionId);
	}

	protected function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}

	/**
	 * Returns XML response if response is valid, otherwise a Merchant_response object
	 */
	protected function _decode_response($response)
	{
		$xml = simplexml_load_string($response);
		if (empty($xml))
		{
			throw new Merchant_exception(lang('merchant_invalid_response'));
		}
		if (isset($xml->Error) AND isset($xml->Error->Message))
		{
			throw new Merchant_exception((string)$xml->Error->Message);
		}
		if (empty($xml->TransactionId))
		{
			throw new Merchant_exception(lang('merchant_invalid_response'));
		}

		return $xml;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_netaxept.php */