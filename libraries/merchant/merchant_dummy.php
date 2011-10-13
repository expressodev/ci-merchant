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
 * Merchant Dummy Class
 *
 * Handles sample payment processing (authorizes when using the test credit card number only)
 */

class Merchant_dummy extends CI_Driver {

	const DUMMY_CARD = '4111111111111111';

	public $name = 'Dummy';

	public $required_fields = array('amount', 'card_no', 'card_name', 'exp_month', 'exp_year', 'csc', 'currency_code', 'transaction_id', 'reference');

	public $settings = array();

	public function _process($params)
	{
		$date = getdate();
		if ($params['card_no'] == self::DUMMY_CARD AND (
				$params['exp_year'] > $date['year'] OR
				($params['exp_year'] == $date['year'] AND $params['exp_month'] >= $date['mon'])
			))
		{
			return new Merchant_response('authorized', 'The transaction was authorized', null, $params['amount']);
		}
		else
		{
			return new Merchant_response('declined', 'The transaction was declined');
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dummy.php */