<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2012 Crescendo Multimedia Ltd
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
 * Payment processing using Stripe (https://stripe.com/)
 */

class Merchant_stripe extends CI_Driver {

	const API_ENDPOINT = 'https://api.stripe.com';

	public $name = 'Stripe';

	public $required_fields = array('amount', 'currency_code', 'reference');

	public $settings = array(
		'api_key' => '',
	);

	public function _process($params)
	{
		// if card token is not supplied, card details are required
		if (empty($params['token']))
		{
			foreach (array('card_no', 'card_name', 'exp_month', 'exp_year', 'csc') as $field_name)
			{
				if (empty($params[$field_name]))
				{
					$response = new Merchant_response('failed', 'field_missing');
					$response->error_field = $field_name;
					return $response;
				}
			}
		}

		$request = array(
			'amount' => (int)($params['amount'] * 100),
			'currency' => strtolower($params['currency_code']),
		);

		if (empty($params['token']))
		{
			$request['card'] = array(
				'number' => $params['card_no'],
				'name' => $params['card_name'],
				'exp_month' => $params['exp_month'],
				'exp_year' => $params['exp_year'],
				'cvc' => $params['csc'],
			);

			if (isset($params['address']) AND isset($params['address2']))
			{
				$params['address'] .= ', '.$params['address2'];
			}

			if (isset($params['address'])) $request['card']['address_line1'] = $params['address'];
			if (isset($params['city'])) $request['card']['address_line2'] = $params['city'];
			if (isset($params['region'])) $request['card']['address_state'] = $params['region'];
			if (isset($params['country'])) $request['card']['address_country'] = $params['country'];
			if (isset($params['postcode'])) $request['card']['address_zip'] = $params['postcode'];
		}
		else
		{
			$request['card'] = $params['token'];
		}

		$response = Merchant::curl_helper(self::API_ENDPOINT.'/v1/charges', $request, $this->settings['api_key']);
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$data = json_decode($response['data']);
		if (isset($data->error))
		{
			return new Merchant_response('declined', $data->error->message);
		}
		else
		{
			return new Merchant_response('authorized', '', $data->id, $data->amount / 100);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dps_pxpost.php */