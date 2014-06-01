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
 * Merchant RealEx Remote Class
 *
 * Payment processing using RealEx Realauth Remote
 * @link https://resourcecentre.realexpayments.com/documents/pdf.html?id=142
 */

class Merchant_realex_remote extends Merchant_driver
{
	const PROCESS_URL = 'https://epage.payandshop.com/epage-remote.cgi';

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
		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		return new Merchant_realex_remote_response($response);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase(true);
		$response = $this->post_request(self::PROCESS_URL, $request->asXML());
		return new Merchant_realex_remote_response($response);
	}

	private function _build_authorize_or_purchase($auto_settle)
	{
		$this->require_params('card_no', 'card_type', 'name', 'exp_month', 'exp_year', 'csc');

		$request = new SimpleXMLElement('<request />');
		$request['timestamp'] = gmdate('YmdHis');
		$request['type'] = 'auth';
		$request->merchantid = $this->setting('merchant_id');
		$request->account = $this->setting('account')
		$request->orderid = $this->param('transaction_id');
		$request->amount = $this->amount_cents();
		$request->amount['currency'] = $this->currency();
		$request->card->number = $this->param('card_no');
		$request->card->expdate = $this->param('exp_month').($this->param('exp_year') % 100);
		$request->card->chname = $this->param('name');
		$request->card->type = strtoupper($this->param('card_type'));
		$request->card->issueno = $this->param('card_issue');
		$request->card->cvn->number = $this->param('csc');
		$request->card->cvn->presind = '1';
		$request->autosettle['flag'] = (int)$auto_settle;
		$request->custipaddress = $this->CI->input->ip_address();
		$request->address['type'] = 'billing';
		$request->address->code = $this->param('postcode');
		$request->address->country = $this->param('country');
		$request->sha1hash = $this->_generate_signature($request, 'sha1');
		$request->md5hash = $this->_generate_signature($request, 'md5');

		return $request;
	}

	private function _generate_signature($request, $method)
	{
		$step1 = $method(implode('.', array(
			(string)$request['timestamp'],
			(string)$request->merchantid,
			(string)$request->orderid,
			(string)$request->amount,
			(string)$request->amount['currency'],
			(string)$request->card->number,
		)));
		return $method($step1.'.'.$this->setting('secret'));
	}
}

class Merchant_realex_remote_response extends Merchant_response
{
	protected $_response;

	public function __construct($response)
	{
		$this->_response = simplexml_load_string($response);

		$this->_status = self::FAILED;
		$this->_message = (string)$this->_response->message;

		if ((string)$this->_response->result === '00') {
			$this->_reference = (string)$this->_response->pasref;
			if ((string)$this->_response->batchid === '-1') {
				$this->_status = self::AUTHORIZED;
			} else {
				$this->_status = self::COMPLETE;
			}
		}
	}
}
