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
 * Merchant DPS PxPost Class
 *
 * Payment processing using DPS PaymentExpress PxPost
 */

class Merchant_dps_pxpost extends Merchant_driver
{
	const PROCESS_URL = 'https://sec.paymentexpress.com/pxpost.aspx';

	public function default_settings()
	{
		return array(
			'username' => '',
			'password' => '',
			'enable_token_billing' => FALSE,
		);
	}

	public function purchase()
	{
		$this->require_params('card_no', 'card_name', 'exp_month', 'exp_year', 'csc', 'reference');

		$date_expiry = $this->param('exp_month');
		$date_expiry .= $this->param('exp_year') % 100;

		$request = '<Txn>'.
				'<PostUsername>'.$this->setting('username').'</PostUsername>'.
				'<PostPassword>'.$this->setting('password').'</PostPassword>'.
				'<CardHolderName>'.htmlspecialchars($this->param('card_name')).'</CardHolderName>'.
				'<CardNumber>'.$this->param('card_no').'</CardNumber>'.
				'<Amount>'.sprintf('%01.2f', $this->param('amount')).'</Amount>'.
				'<DateExpiry>'.$date_expiry.'</DateExpiry>'.
				'<Cvc2>'.$this->param('csc').'</Cvc2>'.
				'<InputCurrency>'.$this->param('currency').'</InputCurrency>'.
				'<TxnType>Purchase</TxnType>'.
				'<MerchantReference>'.$this->param('reference').'</MerchantReference>'.
				'<EnableAddBillCard>'.(int)$this->setting('enable_token_billing').'</EnableAddBillCard>'.
			'</Txn>';

		$response = Merchant::curl_helper(self::PROCESS_URL, $request);
		if ( ! empty($response['error'])) return new Merchant_response(Merchant_response::FAILED, $response['error']);

		$xml = simplexml_load_string($response['data']);

		if ( ! isset($xml->Success))
		{
			return new Merchant_response(Merchant_response::FAILED, 'invalid_response');
		}
		elseif ($xml->Success == '1')
		{
			return new Merchant_response(Merchant_response::COMPLETED, (string)$xml->HelpText, (string)$xml->DpsTxnRef, (string)$xml->Transaction->Amount);
		}
		else
		{
			return new Merchant_response(Merchant_response::FAILED, (string)$xml->HelpText, (string)$xml->DpsTxnRef);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dps_pxpost.php */