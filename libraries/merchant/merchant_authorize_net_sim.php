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
 * Merchant Authorize.net SIM Class
 *
 * Payment processing using Authorize.net SIM (hosted)
 */

class Merchant_authorize_net_sim extends CI_Driver {

	public $name = 'Authorize.Net SIM';

	public $settings = array(
		'api_login_id' => '',
		'transaction_key' => '',
		'test_mode' => FALSE,
	);

	public $required_fields = array('amount', 'reference', 'return_url');

	const PROCESS_URL = 'https://secure.authorize.net/gateway/transact.dll';
	const PROCESS_URL_TEST = 'https://test.authorize.net/gateway/transact.dll';

	public function __construct()
	{
		require_once MERCHANT_VENDOR_PATH.'/AuthorizeNet/AuthorizeNet.php';
	}

	public function _process($params)
	{
		$fp_sequence = $params['reference'];
		$time = time();

		$fingerprint = AuthorizeNetSIM_Form::getFingerprint(
			$this->settings['api_login_id'],
			$this->settings['transaction_key'],
			$params['amount'],
			$fp_sequence,
			$time
		);

		$data = array(
			'x_amount' => $params['amount'],
			'x_delim_data' => 'FALSE',
			'x_fp_sequence' => $fp_sequence,
			'x_fp_hash' => $fingerprint,
			'x_fp_timestamp' => $time,
			'x_relay_response' => 'TRUE',
			'x_relay_url' => $params['return_url'],
			'x_login' => $this->settings['api_login_id'],
			'x_show_form' => 'PAYMENT_FORM',
		);

		$sim = new AuthorizeNetSIM_Form($data);

		$post_url = $this->settings['test_mode'] ? self::PROCESS_URL_TEST: self::PROCESS_URL;
		Merchant::redirect_post($post_url, $sim->getHiddenFieldString());
	}

	public function _process_return()
	{
		$response = new AuthorizeNetSIM($this->settings['api_login_id']);

  		if ($response->approved)
  		{
			return new Merchant_response('authorized', (string)$response->response_reason_text, (string)$response->trans_id, (string)$response->amount);
		}
		elseif ($response->declined)
		{
			return new Merchant_response('declined', (string)$response->response_reason_text);
		}
		else
		{
			return new Merchant_response('failed', (string)$response->response_reason_text);
		}
	}
}
/* End of file ./libraries/merchant/drivers/merchant_authorize_net_sim.php */