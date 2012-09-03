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
 * Merchant Rabo OmniKassa
 *
 * Payment processing using Rabobank OmniKassa
 */

class Merchant_rabo_omnikassa extends Merchant_driver
{
	const PROCESS_URL = 'https://payment-webinit.omnikassa.rabobank.nl/paymentServlet';
	const PROCESS_URL_TEST = 'https://payment-webinit.simu.omnikassa.rabobank.nl/paymentServlet';

	public function default_settings()
	{
		return array(
			'merchant_id' => '',
			'key_version' => '',
			'secret_key' => '',
			'test_mode' => FALSE,
		);
	}

	public function purchase()
	{
		$request = $this->_build_purchase();
		$this->post_redirect($this->_process_url(), $request);
	}

	public function purchase_return()
	{
		if ( ! $this->CI->input->post('InterfaceVersion'))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		$data = $this->CI->input->post('Data');
		$seal = hash('sha256', $data.$this->setting('secret_key'));

		if ($seal !== $this->CI->input->post('Seal'))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		$data = $this->_decode_response($data);

		if ('00' === $data['responseCode'])
		{
			return new Merchant_response(Merchant_response::COMPLETE);
		}

		return new Merchant_response(Merchant_response::FAILED, lang('merchant_payment_failed'));
	}

	private function _build_purchase()
	{
		$request = array();
		$request['Data'] = 'amount='.$this->amount_cents().
			'|currencyCode='.$this->currency_numeric().
			'|merchantId='.$this->setting('merchant_id').
			'|normalReturnUrl='.$this->param('return_url').
			'|automaticResponseUrl='.$this->param('return_url').
			'|transactionReference='.$this->param('transaction_id').
			'|keyVersion='.$this->setting('key_version');
		$request['InterfaceVersion'] = 'HP_1.0';
		$request['Seal'] = hash('sha256', $request['Data'].$this->setting('secret_key'));
		return $request;
	}

	protected function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}

	protected function _decode_response($response)
	{
		$lines = explode('|', $response);
		$data = array();

		foreach ($lines as $line)
		{
			$line = explode('=', $line, 2);
			if ( ! empty($line[0]))
			{
				$data[trim($line[0])] = isset($line[1]) ? trim($line[1]) : '';
			}
		}

		return $data;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_rabo_omnikassa.php */