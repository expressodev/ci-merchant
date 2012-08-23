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

require_once(MERCHANT_DRIVER_PATH.'/merchant_paypal_base.php');

/**
 * Merchant Paypal Express Class
 *
 * Payment processing using Paypal Express Checkout
 * Documentation: https://cms.paypal.com/cms_content/US/en_US/files/developer/PP_ExpressCheckout_IntegrationGuide.pdf
 */

class Merchant_paypal_express extends Merchant_paypal_base
{
	public function authorize()
	{
		$request = $this->_build_authorize_or_purchase();
		$response = $this->_post_paypal_request($request);

		$this->redirect($this->_checkout_url().'?'.http_build_query(array(
			'cmd' => '_express-checkout',
			'useraction' => 'commit',
			'token' => $response['TOKEN'],
		)));
	}

	public function authorize_return()
	{
		$response = $this->_express_checkout_return('Authorization');
		return new Merchant_paypal_api_response($response, Merchant_response::AUTHORIZED);
	}

	public function purchase()
	{
		// authorize first then process as 'Sale' in DoExpressCheckoutPayment
		$this->authorize();
	}

	public function purchase_return()
	{
		$response = $this->_express_checkout_return('Sale');
		return new Merchant_paypal_api_response($response, Merchant_response::COMPLETE);
	}

	protected function _build_authorize_or_purchase()
	{
		$this->require_params('return_url');

		$request = $this->_new_request('SetExpressCheckout');
		$this->_add_request_details($request, 'Authorization', 'PAYMENTREQUEST_0_');

		// pp express specific fields
		$request['SOLUTIONTYPE'] = 'Sole'; // This allows a user to checkout w/ a CC but no account Options: Sole/Mark
		$request['LANDINGPAGE'] = 'Login'; // Allows you to choose which page a user lands on PP site Options: Billing/Login
		$request['NOSHIPPING'] = 1;
		$request['ALLOWNOTE'] = 0;
		$request['ADDROVERRIDE'] = 1;
		$request['RETURNURL'] = $this->param('return_url');
		$request['CANCELURL'] = $this->param('cancel_url');
		$request['SHIPTONAME'] = $this->param('name');
		$request['SHIPTOSTREET'] = $this->param('address1');
		$request['SHIPTOSTREET2'] = $this->param('address2');
		$request['SHIPTOCITY'] = $this->param('city');
		$request['SHIPTOSTATE'] = $this->param('region');
		$request['SHIPTOCOUNTRYCODE'] = $this->param('country');
		$request['SHIPTOZIP'] = $this->param('postcode');
		$request['SHIPTOPHONENUM'] = $this->param('phone');
		$request['EMAIL'] = $this->param('email');

		return $request;
	}

	protected function _express_checkout_return($action)
	{
		$request = $this->_new_request('DoExpressCheckoutPayment');
		$this->_add_request_details($request, $action, 'PAYMENTREQUEST_0_');

		$request['TOKEN'] = $this->CI->input->get_post('token');
		$request['PAYERID'] = $this->CI->input->get_post('PayerID');

		return $this->_post_paypal_request($request);
	}
}

/* End of file ./libraries/merchant/drivers/merchant_paypal_express.php */