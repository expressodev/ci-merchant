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
 * Merchant Authorize.net SIM Class
 *
 * Payment processing using Authorize.net SIM (hosted)
 */

require_once(MERCHANT_DRIVER_PATH.'/merchant_authorize_net.php');

class Merchant_authorize_net_sim extends Merchant_authorize_net
{
	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase_form('AUTH_ONLY');
		$this->post_redirect($this->_process_url(), $request);
	}

	public function authorize_return()
	{
		return $this->_decode_response('AUTH_ONLY');
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase_form('AUTH_CAPTURE');
		$this->post_redirect($this->_process_url(), $request);
	}

	public function purchase_return()
	{
		return $this->_decode_response('AUTH_CAPTURE');
	}

	protected function _build_authorize_or_purchase_form($method)
	{
		$request = $this->_new_sim_request($method);
		$this->_add_billing_details($request);

		$request['x_fp_hash'] = $this->_generate_hash($request);

		return $request;
	}

	protected function _new_sim_request($method)
	{
		$this->require_params('return_url');

		$request = array();
		$request['x_login'] = $this->setting('api_login_id');
		$request['x_type'] = $method;
		$request['x_fp_sequence'] = mt_rand();
		$request['x_fp_timestamp'] = gmmktime();
		$request['x_delim_data'] = 'FALSE';
		$request['x_show_form'] = 'PAYMENT_FORM';
		$request['x_relay_response'] = 'TRUE';
		$request['x_relay_url'] = $this->param('return_url');
		$request['x_cancel_url'] = $this->param('cancel_url');

		if ($this->setting('test_mode'))
		{
			$request['x_test_request'] = 'TRUE';
		}

		return $request;
	}

	protected function _generate_hash($request)
	{
		$fingerprint = implode('^', array(
			$this->setting('api_login_id'),
			$request['x_fp_sequence'],
			$request['x_fp_timestamp'],
			$request['x_amount'])).'^';

		return hash_hmac('md5', $fingerprint, $this->setting('transaction_key'));
	}

	protected function _decode_response($method)
	{
		if ( ! $this->_validate_return_hash())
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		$response_code = $this->CI->input->post('x_response_code');
		$message = $this->CI->input->post('x_response_reason_text');
		$reference = $this->CI->input->post('x_trans_id');

		if ($response_code == '1')
		{
			// we pass through $method rather than trusting the POST data because
			// it may have been tampered with
			if ($method == 'AUTH_CAPTURE')
			{
				return new Merchant_response(Merchant_response::COMPLETE, $message, $reference);
			}
			else
			{
				return new Merchant_response(Merchant_response::AUTHORIZED, $message, $reference);
			}
		}

		return new Merchant_response(Merchant_response::FAILED, $message);
	}

	protected function _validate_return_hash()
	{
		$expected = strtoupper(md5($this->setting('api_login_id').$transaction_id.$this->amount_dollars()));
		$actual = (string)$this->CI->input->post('x_MD5_Hash');
		return $expected == $actual;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_authorize_net_sim.php */