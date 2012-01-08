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
 * Merchant Stripe Class
 *
 * Payment processing using Stripe
 *
 * Please note: There is a client-side script that is also required for Stripe's operation
 */

class Merchant_stripe extends CI_Driver {

	public $name = 'Stripe';

	public $required_fields = array('amount', 'token', 'reference');

	public $settings = array(
		'live_api_key' => '',
		'test_api_key' => '',
		'test_mode' => FALSE
	);

	public $CI;

	public function __construct($settings = array())
	{
		require_once MERCHANT_VENDOR_PATH.'/Stripe/lib/Stripe.php';
	}

	public function _process($params)
	{
        $apiKey = $this->settings['test_mode'] ? $this->settings['test_api_key'] : $this->settings['live_api_key'];
        Stripe::setApiKey($apiKey);
        
        try
        {
    		// send the data to Stripe
    		$response = Stripe_Charge::create(array(
                "amount" => $params['amount'], // Stripe needs the amount in cents rather than dollars
                "currency" => 'usd', // Stripe only supports USD for now - this will eventually need to change
                "card" => $params['token'], // Obtained with stripe.js
                "description" => $params['reference'])
            );

            // Handle the response
            if ($response->paid)
            {
                return new Merchant_response('authorized', '', $response->id, (double)($response->amount / 100));
            }
            else
            {
                return new Merchant_response('failed', 'invalid_response');
            }
        }
        catch (Exception $exception)
        {
            // The payment failed
            return new Merchant_response('failed', $exception->getMessage());
        }
	}
}
/* End of file ./libraries/merchant/drivers/merchant_stripe.php */