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
 * Merchant GoCardless Class
 *
 * Payment processing using GoCardless
 * Documentation: https://sandbox.gocardless.com/docs
 */

class Merchant_gocardless extends Merchant_driver
{
	const PROCESS_URL = 'https://gocardless.com';
	const PROCESS_URL_TEST = 'https://sandbox.gocardless.com';

	public function default_settings()
	{
		return array(
			'app_id' => '',
			'app_secret' => '',
			'merchant_id' => '',
			'access_token' => '',
			'test_mode' => FALSE,
		);
	}

	public function purchase()
	{
		$data = array(
			'client_id' => $this->setting('app_id'),
			'nonce' => $this->_generate_nonce(),
			'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
			'redirect_uri' => $this->param('return_url'),
			'cancel_uri' => $this->param('cancel_url'),
			'bill' => array(
				'merchant_id' => $this->setting('merchant_id'),
				'amount' => $this->amount_dollars(),
				'name' => $this->param('description'),
				'user' => array(
					'first_name' => $this->param('first_name'),
					'last_name' => $this->param('last_name'),
					'email' => $this->param('email'),
					'billing_address1' => $this->param('address1'),
					'billing_address2' => $this->param('address2'),
					'billing_town' => $this->param('city'),
					'billing_county' => $this->param('country'),
					'billing_postcode' => $this->param('postcode'),
				),
			),
		);

		$data['signature'] = $this->_generate_signature($data);

		$url = $this->_process_url().'/connect/bills/new?'.$this->_generate_query_string($data);
		$this->redirect($url);
	}

	public function purchase_return()
	{
		$data = array(
			'resource_uri' => $this->CI->input->get('resource_uri'),
			'resource_id' => $this->CI->input->get('resource_id'),
			'resource_type' => $this->CI->input->get('resource_type'),
		);

		if ($this->_generate_signature($data) !== $this->CI->input->get('signature'))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		unset($data['resource_uri']);
		$url = $this->_process_url().'/api/v1/confirm';
		$response = $this->post_request($url, $data, $this->setting('app_id'), $this->setting('app_secret'));
		$response = json_decode($response);

		if ( ! empty($response->success))
		{
			return new Merchant_response(Merchant_response::COMPLETE, NULL, $data['resource_id']);
		}

		$error_message = isset($response->error) ? $response->error : lang('merchant_invalid_response');
		return new Merchant_response(Merchant_response::FAILED, $error_message);
	}

	private function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}

	/**
	 * Generates a random nonce
	 */
	private function _generate_nonce()
	{
		$nonce = '';
		for ($i = 0; $i < 64; $i++)
		{
			// append random ASCII character
			$nonce .= chr(mt_rand(33, 126));
		}
		return base64_encode($nonce);
	}

	/**
	 * Generate a signature for the data array
	 */
	private function _generate_signature($data)
	{
		return hash_hmac('sha256', $this->_generate_query_string($data), $this->setting('app_secret'));
	}

	/**
	 * Generates, encodes, re-orders variables for the query string.
	 * Copyright (c) 2011 GoCardless
	 * Source: https://github.com/gocardless/gocardless-php/blob/2f2e3ddb23a58fccc2d77f5860c8179905296ce2/lib/gocardless/utils.php#L38
	 *
	 * @param array $params The specific parameters for this payment
	 * @param array $pairs Pairs
	 * @param string $namespace The namespace
	 *
	 * @return string An encoded string of parameters
	 */
	private function _generate_query_string($params, &$pairs = array(), $namespace = null)
	{
		if (is_array($params))
		{
			foreach ($params as $k => $v)
			{
				if (is_int($k))
				{
					$this->_generate_query_string($v, $pairs, $namespace.'[]');
				}
				else
				{
					$this->_generate_query_string($v, $pairs, $namespace !== null ? $namespace."[$k]" : $k);
				}
			}

			if ($namespace !== null)
			{
				return $pairs;
			}

			if (empty($pairs))
			{
				return '';
			}

			sort($pairs);
			$strs = array_map('implode', array_fill(0, count($pairs), '='), $pairs);

			return implode('&', $strs);
		}
		else
		{
			$pairs[] = array(rawurlencode($namespace), rawurlencode($params));
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_gocardless.php */