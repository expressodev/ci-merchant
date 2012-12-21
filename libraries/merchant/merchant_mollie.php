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
 * Merchant Mollie Class
 *
 * Payment processing using Mollie (iDEAL)
 * @link https://www.mollie.nl/support/documentatie/betaaldiensten/ideal/en/
 */
class Merchant_mollie extends Merchant_driver
{
	const API_ENDPOINT = 'https://secure.mollie.nl/xml/ideal';

	public function default_settings()
	{
		return array(
			'partner_id' => '',
			'test_mode' => false,
		);
	}

	public function issuers()
	{
		return $this->_mollie_request('banklist');
	}

	public function purchase()
	{
		$request = $this->_build_purchase();
		$response = $this->_mollie_request('fetch', $request);
		$this->redirect((string)$response->order->URL);
	}

	public function purchase_return()
	{
		$request = $this->_build_purchase_return();
		$response = $this->_mollie_request('check', $request);

		return new Merchant_mollie_response($response);
	}

	protected function _build_purchase()
	{
		$this->require_params('issuer', 'return_url');

		$request['partnerid'] = $this->setting('partner_id');
		$request['reporturl'] = $this->param('return_url');
		$request['returnurl'] = $this->param('return_url');
		$request['bank_id'] = $this->param('issuer');
		$request['amount'] = $this->amount_cents();
		$request['description'] = $this->param('description');

		return $request;
	}

	protected function _build_purchase_return()
	{
		$request['transaction_id'] = $this->CI->input->get('transaction_id');
		$request['partnerid'] = $this->setting('partner_id');

		return $request;
	}

	protected function _mollie_request($method, $data = array())
	{
		if ($this->setting('test_mode'))
		{
			$data['testmode'] = 'true';
		}

		$url = self::API_ENDPOINT.'?a='.$method;
		if ( ! empty($data))
		{
			$url .= '&'.http_build_query($data);
		}

		$response = $this->get_request($url);
		$xml = simplexml_load_string($response);

		if (isset($xml->item) and (string)$xml->item['type'] == 'error')
		{
			$message = (string)$xml->item->message;
			$code = (string)$xml->item->errorcode;
			throw new Merchant_exception("$message ($code)");
		}

		return $xml;
	}
}

class Merchant_mollie_response extends Merchant_response
{
	public function __construct($response)
	{
		$this->_reference = (string)$response->order->transaction_id;

		if ((string)$response->order->payed == 'true')
		{
			$this->_status = self::COMPLETE;
		}
		else
		{
			$this->_status = self::FAILED;
			$this->_message = (string)$response->order->message;
		}
	}
}
