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

require_once(MERCHANT_DRIVER_PATH.'/merchant_sagepay_base.php');

/**
 * Merchant SagePay Direct Class
 *
 * Payment processing using SagePay Direct
 */

class Merchant_sagepay_direct extends Merchant_sagepay_base
{
	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('DEFERRED');
		return $this->_submit_request($request);
	}

	public function authorize_return()
	{
		return $this->_direct3d_return('DEFERRED');
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('PAYMENT');
		return $this->_submit_request($request);
	}

	/**
	 * Only used for returning from Direct 3D Authentication
	 */
	public function purchase_return()
	{
		return $this->_direct3d_return('PAYMENT');
	}

	protected function _build_authorize_or_purchase($method)
	{
		$this->require_params('card_no', 'name', 'card_type', 'exp_month', 'exp_year', 'csc');

		$request = parent::_build_authorize_or_purchase($method);

		$request['CardHolder'] = $this->param('name');
		$request['CardNumber'] = $this->param('card_no');
		$request['CV2'] = $this->param('csc');
		$request['ExpiryDate'] = $this->param('exp_month').($this->param('exp_year') % 100);

		$request['CardType'] = strtoupper($this->param('card_type'));
		if ($request['CardType'] == 'MASTERCARD')
		{
			$request['CardType'] = 'MC';
		}

		if ($this->param('start_month') AND $this->param('start_year'))
		{
			$request['StartDate'] = $this->param('start_month').($this->param('start_year') % 100);
		}

		if ($this->param('card_issue'))
		{
			$request['IssueNumber'] = $this->param('card_issue');
		}

		return $request;
	}

	protected function _direct3d_return($TxType)
	{
		$data = array(
			'MD' => $this->CI->input->post('MD'),
			'PARes' => $this->CI->input->post('PaRes'), // inconsistent caps are intentional
		);

		if (empty($data['MD']) OR empty($data['PARes']))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		$response = $this->post_request($this->_process_url('direct3dcallback'), $data);
		$response = $this->_decode_response($response);

		// add the TxType and VendorTxCode so we can use them in the response class
		$response['TxType'] = $TxType;
		$response['VendorTxCode'] = $this->param('transaction_id');

		return new Merchant_sagepay_response($response);
	}

	protected function _process_url($service)
	{
		$service = strtolower($service);
		if ($service == 'payment' OR $service == 'deferred')
		{
			$service = 'vspdirect-register';
		}

		return parent::_process_url($service);
	}
}

/* End of file ./libraries/merchant/drivers/merchant_sagepay_direct.php */