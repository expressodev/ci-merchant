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
 * Merchant Paypal Class
 *
 * Payment processing using Paypal Payments Standard
 */

class Merchant_paypal extends Merchant_driver
{
	const PROCESS_URL = 'https://www.paypal.com/cgi-bin/webscr';
	const PROCESS_URL_TEST = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

	public function default_settings()
	{
		return array(
			'paypal_email' => '',
			'test_mode' => FALSE,
		);
	}

	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('authorization');
		$this->redirect($this->_process_url().'?'.http_build_query($request));
	}

	public function authorize_return()
	{
		return $this->purchase_return();
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('sale');
		$this->redirect($this->_process_url().'?'.http_build_query($request));
	}

	public function purchase_return()
	{
		if (empty($_POST['txn_id']))
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}

		if ( ! $this->_validate_return($_POST))
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}

		return new Merchant_paypal_response($_POST);
	}

	protected function _build_authorize_or_purchase($method)
	{
		$this->require_params('description', 'return_url');

		$request = array();
		$request['cmd'] = '_xclick';
		$request['paymentaction'] = $method;
		$request['business'] = $this->setting('paypal_email');
		$request['amount'] = $this->amount_dollars();
		$request['currency_code'] = $this->param('currency');
		$request['item_name'] = $this->param('description');
		$request['invoice'] = $this->param('order_id');
		$request['return'] = $this->param('return_url');
		$request['cancel_return'] = $this->param('cancel_url');
		$request['notify_url'] = $this->param('return_url');
		$request['rm'] = '2';
		$request['no_shipping'] = '1';

		return $request;
	}

	protected function _validate_return($data)
	{
		// to make sure amount and email address have not been tampered with,
		// we replace the parameters with their expected values
		$data['business'] = $this->setting('paypal_email');
		$data['receiver_email'] = $this->setting('paypal_email');
		$data['mc_gross'] = $this->amount_dollars();
		$data['mc_currency'] = $this->param('currency');
		$data['cmd'] = '_notify-validate';

		$verify_request = $this->post_request($this->_process_url(), $data);
		return $verify_request == 'VERIFIED';
	}

	protected function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}
}

class Merchant_paypal_response extends Merchant_response
{
	protected $_response;

	public function __construct($response)
	{
		$this->_response = $response;

		$this->_status = self::FAILED;
		$this->_reference = $this->_response['txn_id'];

		$payment_status = $this->_response['payment_status'];
		if ($payment_status == 'Completed')
		{
			$this->_status = self::COMPLETE;
		}
		elseif ($payment_status == 'Refunded' OR $payment_status == 'Voided')
		{
			$this->_status = self::REFUNDED;
		}
		elseif ($payment_status == 'Pending' AND $this->_response['pending_reason'] == 'authorization')
		{
			$this->_status = self::AUTHORIZED;
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_paypal.php */