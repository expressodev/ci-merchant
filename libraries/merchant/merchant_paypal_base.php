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
 * Merchant Paypal base class
 *
 * Base class for Paypal Pro and Paypal Express
 */

abstract class Merchant_paypal_base extends Merchant_driver
{
	const PROCESS_URL = 'https://api-3t.paypal.com/nvp';
	const PROCESS_URL_TEST = 'https://api-3t.sandbox.paypal.com/nvp';
	const CHECKOUT_URL = 'https://www.paypal.com/webscr';
	const CHECKOUT_URL_TEST = 'https://www.sandbox.paypal.com/webscr';

	public function capture()
	{
		$request = $this->_build_capture();
		$response = $this->_post_paypal_request($request);
		return new Merchant_paypal_api_response($response, Merchant_response::COMPLETE);
	}

	public function refund()
	{
		$request = $this->_build_refund();
		$response = $this->_post_paypal_request($request);
		return new Merchant_paypal_api_response($response, Merchant_response::REFUNDED);
	}

	protected function _build_capture()
	{
		$this->require_params('reference', 'amount');

		$request = $this->_new_request('DoCapture');
		$request['AMT'] = $this->amount_dollars();
		$request['CURRENCYCODE'] = $this->param('currency');
		$request['AUTHORIZATIONID'] = $this->param('reference');
		$request['COMPLETETYPE'] = 'Complete';

		return $request;
	}

	protected function _build_refund()
	{
		$this->require_params('reference');

		$request = $this->_new_request('RefundTransaction');
		$request['TRANSACTIONID'] = $this->param('reference');
		$request['REFUNDTYPE'] = 'Full';

		return $request;
	}

	protected function _new_request($method)
	{
		$request = array();
		$request['METHOD'] = $method;
		$request['VERSION'] = '85.0';
		$request['USER'] = $this->setting('username');
		$request['PWD'] = $this->setting('password');
		$request['SIGNATURE'] = $this->setting('signature');

		return $request;
	}


		/**
		PARAMS
		 - REQUIRED 
				'items' => array(
						array(	'name'=>'',
								'desc'=>'',
								'amt'=>,
								'qty'=>
							)
				),
				'itemamt' => '0.00', //is calculated in library
				'currency' => 'EUR',
				'return_url' => 'http://.....',
				'cancel_url' => 'http://.....',
		
		 - OPCIONAL
		
				'taxamt' => 0.00, 			//tax IVA
				'shippingamt' => 0.00 , 	//shipping costs
				'handlingamt' => 0.00, 		//handling costs
				'insuranceamt' => 0.00, 	//insurance costs
				'shipdiscamt' => 0.00, 		//discount amount (put it negative)
				'amount' => '0.00', //is calculated in library

		 */
	
	protected function _add_request_details(&$request, $action, $prefix = '')
	{
		$request[$prefix.'PAYMENTACTION'] = $action;
		$request[$prefix.'CURRENCYCODE'] = $this->param('currency');
		$request[$prefix.'AMT'] = $this->amount_dollars();
		$request[$prefix.'ITEMAMT'] = $this->param('itemamt');
		$request[$prefix.'DESC'] = $this->param('description');
		
		//items
		$items = $this->param('items');
		if (is_array($items) == 0) {
			return false;
		}
		
		//total amount
		$amt = 0;
		$list_prefix = 'L_PAYMENTREQUEST_0_';
		
		$list_index = 0;
		//add all items to request
		foreach ($items as $item){
			$request[$list_prefix.'NAME'.$list_index] = $item['name'];
			$request[$list_prefix.'DESC'.$list_index] = $item['desc'];
			$request[$list_prefix.'AMT'.$list_index] = $item['amt'];
			$request[$list_prefix.'QTY'.$list_index] = $item['qty'];
			$amt += $item['amt'] * $item['qty'];
			$list_index++;
		}

		//total amount , only items
		$request[$prefix.'ITEMAMT'] = $amt;

		//tax IVA
		$amt += $this->param('taxamt');
		$request['PAYMENTREQUEST_0_TAXAMT'] = $this->param('taxamt');
		
		//shipping costs
		$amt += $this->param('shippingamt');
		$request['PAYMENTREQUEST_0_SHIPPINGAMT'] = $this->param('shippingamt');
		
		//handling costs
		$amt += $this->param('handlingamt');
		$request['PAYMENTREQUEST_0_HANDLINGAMT'] = $this->param('handlingamt');
		
		//insurance costs
		$amt += $this->param('insuranceamt');
		$request['PAYMENTREQUEST_0_INSURANCEAMT'] = $this->param('insuranceamt');
		
		//discount amount (put it negative)
		$amt += $this->param('shipdiscamt');
		$request['PAYMENTREQUEST_0_SHIPDISCAMT'] = $this->param('shipdiscamt');
		
		
		//total amount with extra costs
		$request[$prefix.'AMT'] = $amt;

	}

	/**
	 * Post a request to the PayPal API and decode the response
	 */
	protected function _post_paypal_request($request)
	{
		// post and decode response
		$response = $this->post_request($this->_process_url(), $request);
		$response_vars = array();
		parse_str($response, $response_vars);

		// check whether response was successful
		if (isset($response_vars['ACK']) AND
			($response_vars['ACK'] == 'Success' OR $response_vars['ACK'] == 'SuccessWithWarning'))
		{
			return $response_vars;
		}
		elseif (isset($response_vars['L_LONGMESSAGE0']))
		{
			throw new Merchant_exception($response_vars['L_LONGMESSAGE0']);
		}

		throw new Merchant_exception(lang('merchant_invalid_response'));
	}

	protected function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}

	protected function _checkout_url()
	{
		return $this->setting('test_mode') ? self::CHECKOUT_URL_TEST : self::CHECKOUT_URL;
	}
}

class Merchant_paypal_api_response extends Merchant_response
{
	public function __construct($response, $success_status)
	{
		// because the paypal response doesn't specify the state of the transaction,
		// we need to specify the status in the constructor
		$this->_status = $success_status;

		// find the reference
		foreach (array('REFUNDTRANSACTIONID', 'TRANSACTIONID', 'PAYMENTINFO_0_TRANSACTIONID') as $key)
		{
			if (isset($response[$key]))
			{
				$this->_reference = $response[$key];
				return;
			}
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_paypal_pro.php */