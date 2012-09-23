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
 * Merchant Dummy Class
 *
 * Handles sample payment processing (authorizes when using the test credit card number only)
 */

class Merchant_dummy extends Merchant_driver
{
	const DUMMY_CARD = '4111111111111111';

	public function default_settings()
	{
		return array();
	}

	public function purchase()
	{
		$this->require_params('card_no', 'name', 'exp_month', 'exp_year', 'csc');

		if ($this->param('card_no') == self::DUMMY_CARD)
		{
			return new Merchant_response(Merchant_response::COMPLETE);
		}
		else
		{
			return new Merchant_response(Merchant_response::FAILED, 'The transaction was declined');
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dummy.php */