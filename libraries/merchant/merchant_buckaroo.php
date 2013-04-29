<?php if (! defined('BASEPATH')) { exit('No direct script access allowed'); }

/*
 * CI-Merchant Library for Buckaroo
 *
 * Copyright (c) 2012 Denver Sessink, a&m impact internetdiensten bv <d.sessink@am-impact.nl>
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

/*
 * Buckaroo (Dutch payment gateway)
 *
 * Payment processing using Buckaroo
 * Documentation used: "BPE 3.0 gateway HTML.1.00.pdf"
 */
class Merchant_buckaroo extends Merchant_driver
{
	const PROCESS_URL      = 'https://checkout.buckaroo.nl/html/';
	const PROCESS_URL_TEST = 'https://testcheckout.buckaroo.nl/html/';

	const BUCKAROO_STATUSCODE_PAYMENT_SUCCESS            = 190;
	const BUCKAROO_STATUSCODE_PAYMENT_FAILURE            = 490;
	const BUCKAROO_STATUSCODE_VALIDATION_ERROR           = 491;
	const BUCKAROO_STATUSCODE_TECHNICAL_ERROR            = 492;
	const BUCKAROO_STATUSCODE_PAYMENT_REJECTED           = 690;
	const BUCKAROO_STATUSCODE_WAITING_FOR_USER_INPUT     = 790;
	const BUCKAROO_STATUSCODE_WAITING_FOR_PROCESSOR      = 791;
	const BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER_ACTION = 792;
	const BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD            = 793;
	const BUCKAROO_STATUSCODE_CANCELLED_BY_CONSUMER      = 890;
	const BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT      = 891;

	public function default_settings()
	{
		return array(
			'website_key' => '',     // Required // The unique key of the website for which the payment is placed.
			'secret_key' => '',     // Required // Pre-shared secret key which is used at calculating the digital signature
			'test_mode'  => TRUE,
		);
	}

	/**
	 * Sends the user to the Buckaroo payment gateway
	 */
	public function purchase()
	{
		$request_array = $this->_build_purchase();
		$this->post_redirect($this->_process_url(), $request_array);
	}

	/**
	 * After getting back from Buckaroo, this method is called.
	 *
	 * @return Merchant_response
	 */
	public function purchase_return()
	{
		if (!$this->CI->input->post('brq_signature'))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		// Match incoming key
		if ($this->_calculate_digital_signature($_POST) != $this->CI->input->post('brq_signature'))
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		switch ( (int) $this->CI->input->post('brq_statuscode') )
		{
			// Success
			case self::BUCKAROO_STATUSCODE_PAYMENT_SUCCESS:
				return new Merchant_response(Merchant_response::COMPLETE);
				break;

			// Waiting for action, payment on hold
			case self::BUCKAROO_STATUSCODE_WAITING_FOR_USER_INPUT:
			case self::BUCKAROO_STATUSCODE_WAITING_FOR_PROCESSOR:
			case self::BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER_ACTION:
			case self::BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD:
				return new Merchant_response(Merchant_response::FAILED, lang('merchant_payment_failed'));
				break;

			// Cancelled
			case self::BUCKAROO_STATUSCODE_CANCELLED_BY_CONSUMER:
			case self::BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT:
				return new Merchant_response(Merchant_response::FAILED, lang('merchant_payment_failed'));
				break;

			// Failures, errors, rejection
			case self::BUCKAROO_STATUSCODE_PAYMENT_FAILURE:
			case self::BUCKAROO_STATUSCODE_VALIDATION_ERROR:
			case self::BUCKAROO_STATUSCODE_TECHNICAL_ERROR:
			case self::BUCKAROO_STATUSCODE_PAYMENT_REJECTED:
				return new Merchant_response(Merchant_response::FAILED, lang('merchant_payment_failed'));
				break;
		}

		return new Merchant_response(Merchant_response::FAILED, lang('merchant_payment_failed'));
	}

	/**
	 * Builds array for use in POST to Buckaroo process URL
	 *
	 * @return array
	 */
	private function _build_purchase()
	{
		$request                      = array();

		/**
		 * @desc The unique key of the website for which the payment is placed.
		 * @required true
		 */
		$request['Brq_websitekey']    = $this->setting('website_key');

		/**
		 * @desc The amount to pay in the format 12.34 (always use a dot as a decimal separator)
		 * @required true
		 */
		$request['Brq_amount']        = $this->amount_dollars();

		/**
		 * @desc The currency code (e.g. EUR, USD, GBP). Make sure the specified payment method supports the specified currency.
		 * @required true
		 */
		$request['Brq_currency']      = $this->currency();

		/**
		 * @desc The unique invoice number that identifies the payment. This is a free text field of max. 255 characters.
		 * @required true
		 */
		$request['Brq_invoicenumber'] = $this->param('transaction_id');

		/**
		 * @desc A description of the payment to aid the consumer.
		 * @required false
		 */
		$request['Brq_description'] = '';

		/**
		 * @desc ISO culture code that specifies the language and/or country of residence of the consumer. Examples: en-US, en GB, de-DE, EN or DE.
		 * The language part of the culture code is used to apply language localization to the gateway.
		 * Currently the following languages are supported: NL, EN, DE. When the culture parameter is not supplied, the default culture nl-NL is used.
		 * @required false
		 */
		$request['Brq_culture'] = '';

		/**
		 * @desc The return URL where the consumer is redirected after payment.
		 * If not supplied, the value specified in the Payment Plaza is used.
		 * @required false
		 */
		$request['Brq_return'] = $this->param('return_url');

		/**
		 * @desc The return URL used when the consumer cancels the payment. Fallback is the value in brq_return
		 * @required false
		 */
		$request['Brq_returncancel'] = '';

		/**
		 * @desc The return URL used when the request results in an error. Fallback is the value in brq_return
		 * @required false
		 */
		$request['Brq_returnerror']  = '';

		/**
		 * @desc The return URL used when the payment is rejected by the processor. Fallback is the value in brq_return.
		 * @required false
		 */
		$request['Brq_returnreject'] = '';

		/**
		 * @desc A comma separated list of service codes.
		 * If no specific service is passed in the field Brq_payment_method, all available services are displayed to a
		 * customer. Use this to specify which services should be shown. (Only services with an active subscription are shown)
		 * @required false
		 */
		$request['Brq_requestedservices'] = '';

		$request['Brq_signature'] = $this->_calculate_digital_signature($request);

		return $request;
	}

	/**
	 * Calculate the Digital Signature.
	 * Documentation used: Implementation Manual Buckaroo Payment Engine 3.0 (page 10, heading 6)
	 *
	 * @param   array   $origArray
	 * @return  string  $signature
	 */
	private function _calculate_digital_signature($origArray)
	{
		unset($origArray['brq_signature'], $origArray['Brq_signature']);

		$sortableArray = $this->_buckaroo_sort($origArray);

		// turn into string and add the secret key to the end
		$signatureString = '';
		foreach ($sortableArray as $key => $value)
		{
			$signatureString .= $key . '=' . urldecode($value);
		}
		$signatureString .= $this->setting('secret_key');

		// return the SHA1 encoded string for comparison
		$signature = sha1($signatureString);

		return $signature;
	}

	/**
	 * Obtained from the Buckaroo documentation.
	 *
	 * @param   array $array
	 * @return  array
	 */
	private function _buckaroo_sort($array)
	{
		$arrayToSort = array();
		$origArray   = array();

		foreach ($array as $key => $value)
		{
			$arrayToSort[strtolower($key)] = $value;

			// stores the original value in an array
			$origArray[strtolower($key)] = $key;
		}

		ksort($arrayToSort);

		$sortedArray = array();
		foreach ($arrayToSort as $key => $value)
		{
			// switch the lowercase keys back to their originals
			$key               = $origArray[$key];
			$sortedArray[$key] = $value;
		}

		return $sortedArray;
	}

	/**
	 * Finds out the right URL based on the current test mode.
	 *
	 * @return string
	 */
	protected function _process_url()
	{
		return $this->setting('test_mode') ? self::PROCESS_URL_TEST : self::PROCESS_URL;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_buckaroo.php */