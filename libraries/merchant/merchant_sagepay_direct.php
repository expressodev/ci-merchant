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
 * Merchant SagePay Direct Class
 *
 * Payment processing using SagePay Direct
 */

class Merchant_sagepay_direct extends CI_Driver {

	public $name = 'SagePay Direct';

	const PROCESS_URL = 'https://live.sagepay.com/gateway/service/vspdirect-register.vsp';
	const PROCESS_URL_TEST = 'https://test.sagepay.com/gateway/service/vspdirect-register.vsp';
	const PROCESS_URL_SANDBOX = 'https://test.sagepay.com/simulator/VSPDirectGateway.asp';
	const VPS_PROTOCOL = '2.23';

	public $required_fields = array('amount', 'card_no', 'card_name', 'card_type', 'exp_month', 'exp_year', 'csc', 'currency_code', 'transaction_id', 'reference');

	public $settings = array(
		'Vendor' => '',
		'PartnerID' => '',
		'test_mode' => FALSE
	);

	public function _process($params)
	{
		if ($params['card_type'] == 'Visa') $params['card_type'] = 'VISA';
		if ($params['card_type'] == 'Mastercard') $params['card_type'] = 'MC';
		if ($params['card_type'] == 'Amex') $params['card_type'] = 'AMEX';
		if ($params['card_type'] == 'Maestro') $params['card_type'] = 'MAESTRO';
		if ($params['card_type'] == 'Solo') $params['card_type'] = 'VISA';
		if ($params['card_type'] == 'Discover') $params['card_type'] = 'VISA';

		$params['exp_year'] = $params['exp_year'] % 100;
		$expiry_date = $params['exp_month'].$params['exp_year'];

		$request = 'VPSProtocol='.self::VPS_PROTOCOL;
		$request .= '&TxType=PAYMENT';
		$request .= '&Vendor='.$this->settings['Vendor'];
		$request .= '&VendorTxCode='.$params['transaction_id'];

		// Optional: If you are a Sage Pay Partner and wish to flag the transactions with your unique partner id, it should be passed here
		if ( ! empty($this->settings['PartnerID'])) $request .= '&ReferrerID='.urlencode($this->settings['PartnerID']);

		$request .= '&Amount='.$params['amount'];
		$request .= '&Currency='.$params['currency_code'];
		$request .= '&CardHolder='.$params['card_name'];
		$request .= '&CardNumber='.$params['card_no'];
		$request .= '&CV2='.$params['csc'];
		$request .= '&CardType='.$params['card_type'];
		$request .= '&ExpiryDate='.$expiry_date;
		if (! empty($params['card_issue_number'])) $request .= '&IssueNumber='.$params['card_issue_number'];
		if (! empty($params['card_start_date'])) $request .= '&StartDate='.$params['card_start_date'];
		$request .= '&AccountType=E';

		$curl = curl_init($this->settings['test_mode'] ? self::PROCESS_URL_SANDBOX : self::PROCESS_URL);

		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_TIMEOUT,30);
    	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1);

		$response = curl_exec($curl);
		if (curl_error($curl)) return new Merchant_response('failed', 'invalid_response - '.curl_error($curl));
		curl_close($curl);

		$response = explode("\n", $response);

		$output = array();
		foreach ($response as $nvp)
		{
			$splitAt = strpos($nvp, "=");
			$output[trim(substr($nvp, 0, $splitAt))] = trim(substr($nvp, ($splitAt+1)));
		}

		if ($output['Status'] == 'OK')
		{
			return new Merchant_response('authorized', (string)$output['StatusDetail'], (string)$output['VPSTxId'], (string)$params['amount']);
		}
		elseif ($output['Status'] == 'MALFORMED')
		{
			return new Merchant_response('invalid', 'MALFORMED - '.$output['StatusDetail']);
		}
		elseif ($output['Status'] == 'INVALID')
		{
			return new Merchant_response('invalid', 'INVALID - '.$output['StatusDetail']);
		}
		elseif ($output['Status'] == 'NOTAUTHED')
		{
			return new Merchant_response('declined', 'DECLINED - The transaction was not authorised by the bank.');
		}
		elseif ($output['Status'] == 'REJECTED')
		{
			return new Merchant_response('declined', 'REJECTED - The transaction was failed by your 3D-Secure or AVS/CV2 rule-bases.');
		}
		elseif ($output['Status'] == 'ERROR')
		{
			return new Merchant_response('invalid', 'ERROR - '.$output['StatusDetail']);
		}
		else
		{
			return new Merchant_response('invalid', 'UNKNOWN - '.$output['StatusDetail']);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_sagepay_direct.php */