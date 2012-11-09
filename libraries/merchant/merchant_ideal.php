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
 * Merchant iDEAL Class
 *
 * Payment processing using iDEAL
 * Documentation: Extremely hard to find!
 */
class Merchant_ideal extends Merchant_driver
{
	const LANGUAGE = 'nl';
	const EXPIRATION_PERIOD = 'PT10M';

	public function default_settings()
	{
		return array(
			'acquirer_url' => '',
			'merchant_id' => '',
			'public_key_path' => '',
			'private_key_path' => '',
			'private_key_password' => '',
		);
	}

	public function issuers()
	{
		$request = $this->_new_request('DirectoryReq');

		$message = (string)$request->createDateTimeStamp;
		$message .= (string)$request->Merchant->merchantID;
		$message .= (string)$request->Merchant->subID;
		$request->Merchant->tokenCode = $this->_generate_signature($message);

		$response = $this->_post_ideal_request($request);
		return $response;
	}

	public function purchase()
	{
		$request = $this->_build_purchase();
		$response = $this->_post_ideal_request($request);
		$this->redirect((string)$response->Issuer->issuerAuthenticationURL);
	}

	public function purchase_return()
	{
		$request = $this->_build_purchase_return();
		$response = $this->_post_ideal_request($request);
		return new Merchant_ideal_response($response);
	}

	protected function _build_purchase()
	{
		$this->require_params('issuer', 'return_url');

		$request = $this->_new_request('AcquirerTrxReq');

		$request->Issuer->issuerID = $this->param('issuer');
		$request->Merchant->merchantReturnURL = $this->param('return_url');

		$request->Transaction->purchaseID = $this->param('transaction_id');
		$request->Transaction->amount = $this->amount_cents();
		$request->Transaction->currency = $this->param('currency');
		$request->Transaction->expirationPeriod = self::EXPIRATION_PERIOD;
		$request->Transaction->language = self::LANGUAGE;
		$request->Transaction->description = $this->param('description');
		$request->Transaction->entranceCode = $this->param('transaction_hash');

		// signature fields
		$message = (string)$request->createDateTimeStamp;
		$message .= (string)$request->Issuer->issuerID;
		$message .= (string)$request->Merchant->merchantID;
		$message .= (string)$request->Merchant->subID;
		$message .= (string)$request->Merchant->merchantReturnURL;
		$message .= (string)$request->Transaction->purchaseID;
		$message .= (string)$request->Transaction->amount;
		$message .= (string)$request->Transaction->currency;
		$message .= (string)$request->Transaction->language;
		$message .= (string)$request->Transaction->description;
		$message .= (string)$request->Transaction->entranceCode;
		$request->Merchant->tokenCode = $this->_generate_signature($message);

		return $request;
	}

	protected function _build_purchase_return()
	{
		$trxid = (string)$this->CI->input->get('trxid');
		if (empty($trxid))
		{
			throw new Merchant_exception(lang('merchant_invalid_response'));
		}

		$request = $this->_new_request('AcquirerStatusReq');
		$request->Transaction->transactionID = $trxid;

		// signature fields
		$message = (string)$request->createDateTimeStamp;
		$message .= (string)$request->Merchant->merchantID;
		$message .= (string)$request->Merchant->subID;
		$message .= (string)$request->Transaction->transactionID;
		$request->Merchant->tokenCode = $this->_generate_signature($message);

		return $request;
	}

	protected function _new_request($action)
	{
		$request = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'."<$action></$action>");
		$request->addAttribute('xmlns', 'http://www.idealdesk.com/Message');
		$request->addAttribute('version', '1.1.0');

		$request->createDateTimeStamp = gmdate('Y-m-d\TH:i:s.000\Z');

		$request->Merchant->merchantID = $this->setting('merchant_id');
		$request->Merchant->subID = 0;
		$request->Merchant->authentication = 'SHA1_RSA';
		$request->Merchant->token = $this->_public_key_digest();

		return $request;
	}

	protected function _public_key_digest()
	{
		$cert = '';
		if (openssl_x509_export('file://'.$this->setting('public_key_path'), $cert))
		{
			$cert = str_replace(array('-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'), '', $cert);
			return strtoupper(sha1(base64_decode($cert)));
		}

		throw new Merchant_exception("Missing or invalid public key!");
	}

	protected function _generate_signature($message)
	{
		$message = preg_replace('/[\s]+/', '', $message);

		if ($key = openssl_get_privatekey('file://'.$this->setting('private_key_path'), $this->setting('private_key_password')))
		{
			$signature = '';
			openssl_sign($message, $signature, $key);
			openssl_free_key($key);
			return base64_encode($signature);
		}

		throw new Merchant_exception("Missing or invalid private key!");
	}

	protected function _post_ideal_request($request)
	{
		$response = $this->post_request($this->setting('acquirer_url'), $request->asXML());
		$xml = simplexml_load_string($response);

		if (isset($xml->Error))
		{
			$errorCode = (string)$xml->Error->errorCode;
			$errorMessage = (string)$xml->Error->errorMessage;
			$errorDetail = (string)$xml->Error->errorDetail;
			throw new Merchant_exception("$errorMessage ($errorCode: $errorDetail)");
		}

		return $xml;
	}
}

class Merchant_ideal_response extends Merchant_response
{
	public function __construct($response)
	{
		$this->_reference = (string)$response->Transaction->transactionID;

		if ((string)$response->Transaction->status == 'Success')
		{
			$this->_status = self::COMPLETE;
		}
		else
		{
			$this->_status = self::FAILED;
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_ideal.php */