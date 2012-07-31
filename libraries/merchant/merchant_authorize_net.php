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
 * Merchant Authorize.Net Class
 *
 * Payment processing using Authorize.net AIM
 */

class Merchant_authorize_net extends Merchant_driver
{
	const PROCESS_URL = 'https://secure.authorize.net/gateway/transact.dll';
	const PROCESS_URL_TEST = 'https://test.authorize.net/gateway/transact.dll';

	public function default_settings()
	{
		return array(
			'api_login_id' => '',
			'transaction_key' => '',
			'test_mode' => FALSE,
			'developer_mode' => FALSE,
		);
	}

	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('AUTH_ONLY');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_authorize_net_response($response);
	}

	public function capture()
	{
		$request = $this->_build_capture_or_refund('PRIOR_AUTH_CAPTURE');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_authorize_net_response($response);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('AUTH_CAPTURE');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_authorize_net_response($response);
	}

	protected function _build_authorize_or_purchase($method)
	{
		$this->require_params('card_no', 'first_name', 'last_name', 'exp_month', 'exp_year', 'csc');

		$request = $this->_new_aim_request($method);
		$request['x_customer_ip'] = $this->CI->input->ip_address();
		$request['x_card_num'] = $this->param('card_no');
		$request['x_exp_date'] = $this->param('exp_month').$this->param('exp_year');
		$request['x_card_code'] = $this->param('csc');

		if ($this->setting('test_mode'))
		{
			$request['x_test_request'] = 'TRUE';
		}

		$this->_add_billing_details($request);

		return $request;
	}

	protected function _add_billing_details(&$request)
	{
		$request['x_amount'] = $this->amount_dollars();
		$request['x_invoice_num'] = $this->param('order_id');
		$request['x_description'] = $this->param('description');
		$request['x_first_name'] = $this->param('first_name');
		$request['x_last_name'] = $this->param('last_name');
		$request['x_company'] = $this->param('company');
		$request['x_address'] = trim($this->param('address1')." \n".$this->param('address2'));
		$request['x_city'] = $this->param('city');
		$request['x_state'] = $this->param('region');
		$request['x_zip'] = $this->param('postcode');
		$request['x_country'] = $this->param('country');
		$request['x_phone'] = $this->param('phone');
		$request['x_email'] = $this->param('email');
	}

	protected function _build_capture_or_refund($method)
	{
		$this->require_params('reference', 'amount');

		$request = $this->_new_aim_request($method);
		$request['x_amount'] = $this->amount_dollars();
		$request['x_trans_id'] = $this->param('reference');

		return $request;
	}

	protected function _new_aim_request($method)
	{
		$request = array();
		$request['x_login'] = $this->setting('api_login_id');
		$request['x_tran_key'] = $this->setting('transaction_key');
		$request['x_type'] = $method;
		$request['x_version'] = '3.1';
		$request['x_delim_data'] = 'TRUE';
		$request['x_delim_char'] = ',';
		$request['x_encap_char'] = '|';
		$request['x_relay_response'] = 'FALSE';

		return $request;
	}

	protected function _process_url()
	{
		return $this->setting('developer_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}
}

class Merchant_authorize_net_response extends Merchant_response
{
	protected $_response;
	protected $_response_array;

	public function __construct($response)
	{
		$this->_response = $response;
		$this->_response_array = explode('|,|', substr($response, 1, -1));

		if (count($this->_response_array) < 10)
		{
			$this->_status == self::FAILED;
			$this->_message = lang('merchant_invalid_response');
		}
		else
		{
			$this->_status = self::FAILED;

			if ($this->_response_array[0] == '1')
			{
				switch (strtoupper($this->_response_array[11]))
				{
					case 'AUTH_CAPTURE':
						$this->_status = self::COMPLETE;
						break;
					case 'AUTH_ONLY':
						$this->_status = self::AUTHORIZED;
						break;
					case 'PRIOR_AUTH_CAPTURE':
						$this->_status = self::COMPLETE;
						break;
				}
			}

			$this->_reference = $this->_response_array[6];
			$this->_message = $this->_response_array[3];
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_authorize_net.php */