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
 * Merchant DPS PxPost Class
 *
 * Payment processing using DPS PaymentExpress PxPost
 */

class Merchant_dps_pxpost extends CI_Driver {

	const PROCESS_URL = 'https://sec.paymentexpress.com/pxpost.aspx';

	public $name = 'DPS PaymentExpress PxPost';

	public $required_fields = array('amount', 'card_no', 'card_name', 'exp_month', 'exp_year', 'csc', 'currency_code', 'transaction_id', 'reference');

	public $settings = array(
		'username' => '',
		'password' => '',
		'enable_token_billing' => FALSE,
	);

	public function _process($params)
	{
		$date_expiry = $params['exp_month'];
		$date_expiry .= $params['exp_year'] % 100;

		$request = '<Txn>'.
				'<PostUsername>'.$this->settings['username'].'</PostUsername>'.
				'<PostPassword>'.$this->settings['password'].'</PostPassword>'.
				'<CardHolderName>'.htmlspecialchars($params['card_name']).'</CardHolderName>'.
				'<CardNumber>'.$params['card_no'].'</CardNumber>'.
				'<Amount>'.sprintf('%01.2f', $params['amount']).'</Amount>'.
				'<DateExpiry>'.$date_expiry.'</DateExpiry>'.
				'<Cvc2>'.$params['csc'].'</Cvc2>'.
				'<InputCurrency>'.$params['currency_code'].'</InputCurrency>'.
				'<TxnType>Purchase</TxnType>'.
				'<TxnId>'.$params['transaction_id'].'</TxnId>'.
				'<MerchantReference>'.$params['reference'].'</MerchantReference>'.
				'<EnableAddBillCard>'.(int)$this->settings['enable_token_billing'].'</EnableAddBillCard>'.
			'</Txn>';

		$response = Merchant::curl_helper(self::PROCESS_URL, $request);
		if ( ! empty($response['error'])) return new Merchant_response('failed', $response['error']);

		$xml = simplexml_load_string($response['data']);

		if ( ! isset($xml->Success))
		{
			return new Merchant_response('failed', 'invalid_response');
		}
		elseif ($xml->Success == '1')
		{
			return new Merchant_response('authorized', (string)$xml->HelpText, (string)$xml->DpsTxnRef, (string)$xml->Transaction->Amount);
		}
		else
		{
			return new Merchant_response('declined', (string)$xml->HelpText, (string)$xml->DpsTxnRef);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dps_pxpost.php */