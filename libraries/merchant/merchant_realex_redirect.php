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
 * Merchant RealEx Redirect Class
 *
 * Payment processing using RealEx Realauth Redirect
 */
class Merchant_realex_redirect extends Merchant_driver
{
	const PROCESS_URL = 'https://epage.payandshop.com/epage.cgi';

	public function default_settings()
	{
		return array(
			'merchant_id' => '',
			'account' => 'internet',
			'secret' => '',
		);
	}

	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase(false);
		$this->post_redirect(self::PROCESS_URL, $request);
	}

	public function authorize_return()
	{
		return $this->purchase_return();
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase(true);
		$this->post_redirect(self::PROCESS_URL, $request);
	}

	public function purchase_return()
	{
		$signature = $this->_generate_signature($_POST,
			array('TIMESTAMP', 'MERCHANT_ID', 'ORDER_ID', 'RESULT', 'MESSAGE', 'PASREF', 'AUTHCODE'));

		if ($this->CI->input->post('SHA1HASH') !== $signature) {
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		return new Merchant_realex_redirect_response($_POST);
	}

	private function _build_authorize_or_purchase($auto_settle)
	{
		$request = array();
		$request['MERCHANT_ID'] = $this->setting('merchant_id');
		$request['ACCOUNT'] = $this->setting('account');
		$request['ORDER_ID'] = $this->param('transaction_id');
		$request['AMOUNT'] = $this->amount_cents();
		$request['CURRENCY'] = $this->currency();
		$request['TIMESTAMP'] = gmdate('YmdHis');
		$request['AUTO_SETTLE_FLAG'] = (int)$auto_settle;
		$request['SHA1HASH'] = $this->_generate_signature($request,
			array('TIMESTAMP', 'MERCHANT_ID', 'ORDER_ID', 'AMOUNT', 'CURRENCY'));

		// add any extra POST parameters which will be sent to the fixed return URL
		if (is_array($this->param('return_post_data'))) {
			$request = array_merge($request, $this->param('return_post_data'));
		}

		return $request;
	}

	private function _generate_signature($data, $fields)
	{
		$step0 = array();
		foreach ($fields as $key) {
			$step0[] = isset($data[$key]) ? (string) $data[$key] : '';
		}

		$step1 = sha1(implode('.', $step0));
		$step2 = sha1($step1.'.'.$this->setting('secret'));

		return $step2;
	}
}

class Merchant_realex_redirect_response extends Merchant_response
{
	public function __construct($data)
	{
		$this->_data = $data;
		$this->_status = self::FAILED;
		$this->_message = isset($data['MESSAGE']) ? (string) $data['MESSAGE'] : null;

		if (isset($data['RESULT']) && '00' === $data['RESULT']) {
			$this->_reference = isset($data['PASREF']) ? (string) $data['PASREF'] : null;
			if (isset($data['BATCHID']) && '-1' === $data['BATCHID']) {
				$this->_status = self::AUTHORIZED;
			} else {
				$this->_status = self::COMPLETE;
			}
		}
	}
}
