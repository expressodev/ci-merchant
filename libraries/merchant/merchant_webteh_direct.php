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
 * Merchant Webteh Direct
 *
 * Payment processing using Webteh Direct Integration
 * @link https://ipgtest.webteh.hr/en/documentation/direct
 */

class Merchant_webteh_direct extends Merchant_driver
{
	const LIVE_ENDPOINT = 'https://ipgtest.webteh.hr';
	const TEST_ENDPOINT = 'https://ipgtest.webteh.hr';

	public function default_settings()
	{
		return array(
			'authenticity_token' => '',
			'key' => '',
			'test_mode' => '',
		);
	}

	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('authorize');
		$response = $this->_post_webteh('/api', $request);
		return new Merchant_webteh_response($response, $this->param('return_url'));
	}

	public function capture()
	{
		$request = $this->_build_capture_or_refund();
		$response = $this->_post_webteh('/transactions/'.$this->param('reference').'/capture.xml', $request);
		return new Merchant_webteh_response($response);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('purchase');
		$response = $this->_post_webteh('/api', $request);
		return new Merchant_webteh_response($response, $this->param('return_url'));
	}

	public function refund()
	{
		$request = $this->_build_capture_or_refund();
		$response = $this->_post_webteh('/transactions/'.$this->param('reference').'/refund.xml', $request);
		return new Merchant_webteh_response($response);
	}

	protected function _build_authorize_or_purchase($method)
	{
		$this->require_params('card_no', 'name', 'exp_month', 'exp_year', 'csc');

		$request = new SimpleXMLElement('<transaction />');
		$request->language = 'en';
		$request->transaction_type = $method;
		$request->authenticity_token = $this->setting('authenticity_token');
		$request->digest = $this->_generate_digest();
		$request->amount = $this->amount_cents();
		$request->currency = $this->param('currency');
		$request->ip = $this->CI->input->ip_address();
		$request->{'order-number'} = $this->param('transaction_id');
		$request->{'order-info'} = $this->param('description');
		$request->pan = $this->param('card_no');
		$request->{'expiration-date'} = ($this->param('exp_year') % 100).$this->param('exp_month');
		$request->cvv = $this->param('csc');
		$request->{'ch-full-name'} = $this->param('name');
		$request->{'ch-address'} = $this->param('address1');
		$request->{'ch-city'} = $this->param('city');
		$request->{'ch-zip'} = $this->param('postcode');
		$request->{'ch-country'} = $this->param('country');
		$request->{'ch-email'} = $this->param('email');
		$request->{'ch-phone'} = $this->param('phone');

		return $request;
	}

	protected function _build_capture_or_refund()
	{
		$this->require_params('reference', 'amount');

		$request = new SimpleXMLElement('<transaction />');
		$request->authenticity_token = $this->setting('authenticity_token');
		$request->digest = $this->_generate_digest();
		$request->amount = $this->amount_cents();
		$request->currency = $this->param('currency');
		$request->{'order-number'} = $this->param('transaction_id');

		return $request;
	}

	protected function _generate_digest()
	{
		return sha1(
			$this->setting('key').
			$this->param('transaction_id').
			$this->amount_cents().
			$this->param('currency')
		);
	}

	protected function _post_webteh($path, $request)
	{
		// really fussy gateway
		$extra_headers = array('Content-Type: application/xml', 'Accept: application/xml');
		$url = $this->_endpoint().$path;

		return $this->post_request($url, $request->asXML(), null, null, $extra_headers);
	}

	protected function _endpoint()
	{
		return $this->setting('test_mode') ? self::TEST_ENDPOINT : self::LIVE_ENDPOINT;
	}
}

class Merchant_webteh_response extends Merchant_response
{
	public function __construct($response, $return_url = null)
	{
		$this->_data = simplexml_load_string($response);

		if (isset($this->_data->error))
		{
			$this->_status = self::FAILED;
			$this->_message = (string)$this->_data->error;
			return;
		}

		if (isset($this->_data->pareq))
		{
			// 3D Secure redirect
			$this->_status = self::REDIRECT;
			$this->_redirect_url = (string)$this->_data->{'acs-url'};
			$this->_redirect_method = 'POST';
			$this->_redirect_message = lang('merchant_3dauth_redirect');
			$this->_redirect_data = array(
				'PaReq' => (string)$this->_data->pareq,
				'TermUrl' => $return_url,
				'MD' => (string)$this->_data->{'authenticity-token'},
			);
			return;
		}

		$this->_status = self::FAILED;
		$this->_message = (string)$this->_data->{'response-message'};
		$this->_reference = (string)$this->_data->{'reference-number'};

		if ('approved' == (string)$this->_data->status)
		{
			switch ((string)$this->_data->{'transaction-type'})
			{
				case 'authorize':
					$this->_status = self::AUTHORIZED;
					break;
				case 'purchase':
					$this->_status = self::COMPLETE;
					break;
				case 'capture':
					$this->_status = self::COMPLETE;
					break;
				case 'refund':
					$this->_status = self::REFUNDED;
					break;
			}
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_webteh_direct.php */