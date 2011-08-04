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
 * Merchant Paypal Pro Class
 *
 * Payment processing using Paypal Payments Pro
 */

class Merchant_paypal_pro extends CI_Driver {

	public $name = 'PayPal Pro';

	public $required_fields = array('reference', 'currency_code', 'ip_address', 'amount', 'card_no',
									'card_name', 'card_type', 'exp_month', 'exp_year', 'csc', 'billing_address1',
									'billing_address3', 'billing_region',
									'billing_country', 'billing_postcode');

	public $settings = array(
		'API_USERNAME' => '',
		'API_PASSWORD' => '',
		'API_SIGNATURE' => '',
		'test_mode' => FALSE
	);

	const PROCESS_URL = 'https://www.paypal.com/webscr&cmd=_express-checkout&token=';
	const PROCESS_URL_TEST = 'https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&token=';
	const API_ENDPOINT = 'https://api-3t.paypal.com/nvp';
	const API_ENDPOINT_TEST = 'https://api-3t.sandbox.paypal.com/nvp';
	const VERSION = '65.1';
	const ACK_SUCCESS = 'Success';
	const ACK_SUCCESS_WITH_WARNING = 'SuccessWithWarning';
	const ACK_FAILURE = 'Failure';
	const ACK_FAILURE_WITH_WARNING = 'FailureWithWarning';

	public $CI;

	public function __construct($settings = array())
	{
		foreach ($settings as $key => $value)
		{
			if(array_key_exists($key, $this->settings))	$this->settings[$key] = $value;
		}
		$this->CI =& get_instance();
	}

	public function _process($params)
	{
		if (! $this->_check_valid_card_info($params))
		{
			return new Merchant_response('failed', 'UK Credit Card information incorrect (or currency is not GBP).');
		}

		$name = explode(' ', $params['card_name'], 2);
		$firstName = urlencode($name[0]);
		$lastName = urlencode($name[1]);

		// Month must be padded with leading zero
		$params['exp_month'] = str_pad($params['exp_month'], 2, '0', STR_PAD_LEFT);

		foreach ($params as $key => $param) $params[$key] = urlencode($param);

		/* Construct the request string that will be sent to PayPal.
		   The variable $nvpstr contains all the variables and is a
		   name value pair string with & as a delimiter */
		$nvpstr = 	'&VERSION='.self::VERSION.'&PWD='.urlencode($this->settings['API_PASSWORD']).'&USER='.urlencode($this->settings['API_USERNAME']).
					'&SIGNATURE='.urlencode($this->settings['API_SIGNATURE']).'&METHOD=doDirectPayment&PAYMENTACTION=Sale'.
					'&AMT='.$params['amount'].'&CREDITCARDTYPE='.$params['card_type'].
					'&ACCT='.$params['card_no'].'&EXPDATE='.$params['exp_month'].$params['exp_year'].
					'&CVV2='.$params['csc'].'&FIRSTNAME='.$firstName.'&LASTNAME='.$lastName.
					'&STREET='.$params['billing_address1'].$params['billing_address2'].'&CITY='.$params['billing_address3'].
					'&STATE='.$params['billing_region'].'&ZIP='.$params['billing_postcode'].
					'&COUNTRYCODE='.$params['billing_country'].'&CURRENCYCODE='.$params['currency_code'].
					'&IPADDRESS='.$params['ip_address'];

		if ($params['card_type'] == 'Maestro' || $params['card_type'] == 'Solo')
		{
			$nvpstr .= '&ISSUENUMBER='.$params['card_issue_number'].'&STARTDATE'.$params['card_start_date'];
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->settings['test_mode'] ? self::API_ENDPOINT_TEST : self::API_ENDPOINT);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpstr);

		//getting response from server
		$response = curl_exec($ch);
		parse_str($response, $reply);

		if (empty($reply['ACK'])) return new Merchant_response('failed', 'invalid_response');

		switch ($reply['ACK'])
		{
			case self::ACK_SUCCESS:
				return new Merchant_response('authorized', 'payment_authorized', $reply['TRANSACTIONID'], $params['amount']);
				break;

			case self::ACK_SUCCESS_WITH_WARNING:
				return new Merchant_response('authorized', 'payment_authorized, avs code - '.$reply['AVSCODE'].
											', csc - '.$reply['CVV2MATCH'],
											$reply['TRANSACTIONID'], $reply['AMT']);
				break;

			case self::ACK_FAILURE:
				return new Merchant_response('declined', 'payment_declined, error code - '.$reply['L_ERRORCODE0'].
											', short - '.$reply['L_SHORTMESSAGE0'].', long - '.$reply['L_LONGMESSAGE0']);
				break;

			case self::ACK_FAILURE_WITH_WARNING:
				return new Merchant_response('declined', 'payment_declined, error code - '.$reply['L_ERRORCODE0'].
											', short - '.$reply['L_SHORTMESSAGE0'].', long - '.$reply['L_LONGMESSAGE0']);
				break;
		}
	}

	// PayPal accepts Maestro and Solo credit card type in the UK so check that GBP are being
	// used as well as one of the two extra fields that must be submitted for those card types
	// (either 'card_issue_number' or 'card_start_date')
	private function _check_valid_card_info($params)
	{
		if ($params['card_type'] == 'Maestro' OR $params['card_type'] == 'Solo')
		{
			if ($params['currency_code'] !== 'GBP')
			{
				return FALSE;
			}
			if (empty($params['card_start_date']))
			{
				if (empty($params['card_issue_number']) OR strlen($params['card_issue_number']) > 2)
				{
					return FALSE;
				}
			}
			elseif (empty($params['card_issue_number']))
			{
				if (empty($params['card_start_date']) OR strlen($params['card_start_date']) != 6)
				{
					return FALSE;
				}
			}
		}
		if ($params['card_type'] == 'Discover' OR $params['card_type'] == 'Amex' AND $params['currency_code'] !== 'USD')
		{
			return FALSE;
		}
		return TRUE;
	}
}
/* End of file ./libraries/merchant/drivers/merchant_paypal_pro.php */