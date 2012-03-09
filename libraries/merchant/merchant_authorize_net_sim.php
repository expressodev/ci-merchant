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
 * Merchant Authorize.net SIM Class
 *
 * Payment processing using Authorize.net SIM (hosted)
 */

class Merchant_authorize_net_sim extends Merchant_driver
{
	const PROCESS_URL = 'https://secure.authorize.net/gateway/transact.dll';
	const PROCESS_URL_TEST = 'https://test.authorize.net/gateway/transact.dll';

	public function __construct()
	{
		parent::__construct();
		require_once MERCHANT_VENDOR_PATH.'/AuthorizeNet/AuthorizeNet.php';
	}

	public function default_settings()
	{
		return array(
			'api_login_id' => '',
			'transaction_key' => '',
			'test_mode' => FALSE,
		);
	}

	public function purchase()
	{
		$this->require_params('reference', 'return_url');

		$fp_sequence = $this->param('reference');
		$time = time();

		$fingerprint = AuthorizeNetSIM_Form::getFingerprint(
			$this->setting('api_login_id'),
			$this->setting('transaction_key'),
			$this->param('amount'),
			$fp_sequence,
			$time
		);

		$data = array(
			'x_amount' => $this->param('amount'),
			'x_delim_data' => 'FALSE',
			'x_fp_sequence' => $fp_sequence,
			'x_fp_hash' => $fingerprint,
			'x_fp_timestamp' => $time,
			'x_invoice_num' => $this->param('reference'),
			'x_relay_response' => 'TRUE',
			'x_relay_url' => $this->param('return_url'),
			'x_login' => $this->setting('api_login_id'),
			'x_show_form' => 'PAYMENT_FORM',
			'x_customer_ip' => $this->CI->input->ip_address(),
		);

		// set extra billing details if we have them
		if (isset($this->param('card_name')))
		{
			$names = explode(' ', $this->param('card_name'), 2);
			$data['x_first_name'] = $names[0];
			$data['x_last_name'] = isset($names[1]) ? $names[1] : '';
		}

		if (isset($this->param('address')) AND isset($this->param('address2')))
		{
			$this->param('address') = trim($this->param('address')." \n".$this->param('address2'));
		}

		foreach (array(
			'x_company' => 'company',
			'x_address' => 'address',
			'x_city' => 'city',
			'x_state' => 'region',
			'x_zip' => 'postcode',
			'x_country' => 'country',
			'x_phone' => 'phone',
			'x_email' => 'email') as $key => $field)
		{
			if (isset($params[$field]))
			{
				$data[$key] = $params[$field];
			}
		}

		$sim = new AuthorizeNetSIM_Form($data);

		$post_url = $this->setting('test_mode') ? self::PROCESS_URL_TEST: self::PROCESS_URL;
		Merchant::redirect_post($post_url, $sim->getHiddenFieldString());
	}

	public function purchase_return()
	{
		$response = new AuthorizeNetSIM($this->setting('api_login_id'));

  		if ($response->approved)
  		{
			return new Merchant_response(Merchant_response::COMPLETED, (string)$response->response_reason_text, (string)$response->trans_id, (string)$response->amount);
		}

		return new Merchant_response(Merchant_response::FAILED, (string)$response->response_reason_text);
	}
}
/* End of file ./libraries/merchant/drivers/merchant_authorize_net_sim.php */