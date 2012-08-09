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
 * Merchant Ogone DirectLink Class
 *
 * Payment processing using Ogone DirectLink
 * @link http://www.productivecomputing.com/docs/docs_library/FM_CreditCard/Ogone_DirectLink_EN.pdf
 */

class Merchant_ogone_directlink extends Merchant_driver
{
	const PROCESS_URL = 'https://secure.ogone.com/ncol/prod/orderdirect.asp';
	const PROCESS_URL_TEST = 'https://secure.ogone.com/ncol/test/orderdirect.asp';

	public function default_settings()
	{
		return array(
			'psp_id' => '',
			'user_id' => '',
			'password' => '',
			'signature' => '',
			'test_mode' => FALSE,
		);
	}

	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase('RES');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_ogone_directlink_response($response);	
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('SAL');
		$response = $this->post_request($this->_process_url(), $request);
		return new Merchant_ogone_directlink_response($response);
	}

	protected function _build_authorize_or_purchase($method)
	{
		$this->require_params('card_no', 'name', 'exp_month', 'exp_year', 'csc');

		$request = array();
		$request['PSPID'] = $this->setting('psp_id');
		$request['USERID'] = $this->setting('user_id');
		$request['PSWD'] = $this->setting('password');
		$request['OPERATION'] = $method;
		$request['ORDERID'] = $this->param('order_id');
		$request['AMOUNT'] = $this->amount_cents();
		$request['CURRENCY'] = $this->param('currency');
		$request['CARDNO'] = $this->param('card_no');
		$request['ED'] = $this->param('exp_month').($this->param('exp_year') % 100);
		$request['CN'] = $this->param('name');
		$request['CVC'] = $this->param('csc');
		$request['REMOTE_ADDR'] = $this->CI->input->ip_address();
		$request['ECI'] = 7;
		$request['EMAIL'] = $this->param('email');
		$request['OWNERADDRESS'] = $this->param('address1');
		$request['OWNERTOWN'] = $this->param('address2');
		$request['OWNERZIP'] = $this->param('postcode');
		$request['OWNERCTY'] = $this->param('city');
		$request['OWNERTELNO'] = $this->param('phone');

		$this->_sign_request($request);
		return $request;
	}

	protected function _sign_request(&$request)
	{
		ksort($request);
		$signature = '';
		foreach ($request as $key => $value)
		{
			$signature .= $key.'='.$value.$this->setting('signature');
		}
		$request['SHASIGN'] = sha1($signature);
	}

	protected function _process_url()
	{
		return $this->setting('developer_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}
}

class Merchant_ogone_directlink_response extends Merchant_response
{
	protected $_response;

	public function __construct($response)
	{
		$this->_response = simplexml_load_string($response);
		$this->_status = self::FAILED;

		if ( ! isset($this->_response['STATUS']))
		{
			$this->_message = lang('merchant_invalid_response');
		}
		elseif ((string)$this->_response['STATUS'] == '5')
		{
			$this->_status = self::AUTHORIZED;
			$this->_reference = (string)$this->_response['PAYID'];
		}
		elseif ((string)$this->_response['STATUS'] == '9')
		{
			$this->_status = self::COMPLETE;
			$this->_reference = (string)$this->_response['PAYID'];
		}
		else
		{
			$this->_message = (string)$this->_response['NCERRORPLUS'];
			$this->_reference = (string)$this->_response['PAYID'];
		}
	}
}

/* End of file ./libraries/merchant/merchant_ogone_directlink.php */