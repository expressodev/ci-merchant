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
 * Merchant CardSave Class
 *
 * Payment processing using CardSave
 * Documentation: http://www.cardsave.net/dev-downloads
 */

class Merchant_cardsave extends Merchant_driver
{
	const PROCESS_URL = 'https://gw1.cardsaveonlinepayments.com:4430/';

	public function default_settings()
	{
		return array(
			'merchant_id' => '',
			'password' => '',
		);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('SALE');
		$response = $this->_post_cardsave_request($request);
		return new Merchant_cardsave_response($response);
	}

	public function purchase_return()
	{
		$request = $this->_build_3dauth();
		$response = $this->_post_cardsave_request($request);
		return new Merchant_cardsave_response($response);
	}

	private function _build_authorize_or_purchase($method)
	{
		$this->require_params('card_no', 'name', 'exp_month', 'exp_year', 'csc');

		$request = $this->_new_request('CardDetailsTransaction');
		$request->PaymentMessage->MerchantAuthentication['MerchantID'] = $this->setting('merchant_id');
		$request->PaymentMessage->MerchantAuthentication['Password'] = $this->setting('password');
		$request->PaymentMessage->TransactionDetails['Amount'] = $this->amount_cents();
		$request->PaymentMessage->TransactionDetails['CurrencyCode'] = $this->currency_numeric();
		$request->PaymentMessage->TransactionDetails->OrderID = $this->param('transaction_id');
		$request->PaymentMessage->TransactionDetails->OrderDescription = $this->param('description');
		$request->PaymentMessage->TransactionDetails->MessageDetails['TransactionType'] = $method;
		$request->PaymentMessage->CardDetails->CardName = $this->param('name');
		$request->PaymentMessage->CardDetails->CardNumber = $this->param('card_no');
		$request->PaymentMessage->CardDetails->ExpiryDate['Month'] = $this->param('exp_month');
		$request->PaymentMessage->CardDetails->ExpiryDate['Year'] = $this->param('exp_year') % 100;
		$request->PaymentMessage->CardDetails->CV2 = $this->param('csc');

		if ($this->param('card_issue'))
		{
			$request->PaymentMessage->CardDetails->IssueNumber = $this->param('card_issue');
		}

		if ($this->param('start_month') AND $this->param('start_year'))
		{
			$request->PaymentMessage->CardDetails->StartDate['Month'] = $this->param('start_month');
			$request->PaymentMessage->CardDetails->StartDate['Year'] = $this->param('start_year') % 100;
		}

		$request->PaymentMessage->CustomerDetails->BillingAddress->Address1 = $this->param('address1');
		$request->PaymentMessage->CustomerDetails->BillingAddress->Address2 = $this->param('address2');
		$request->PaymentMessage->CustomerDetails->BillingAddress->City = $this->param('city');
		$request->PaymentMessage->CustomerDetails->BillingAddress->PostCode = $this->param('postcode');
		$request->PaymentMessage->CustomerDetails->BillingAddress->State = $this->param('region');
		// requires numeric country code
		// $request->PaymentMessage->CustomerDetails->BillingAddress->CountryCode = $this->param('country');
		$request->PaymentMessage->CustomerDetails->CustomerIPAddress = $this->CI->input->ip_address();

		return $request;
	}

	private function _build_3dauth()
	{
		if (empty($_POST['MD']) OR empty($_POST['PaRes']))
		{
			throw new Merchant_exception(lang('merchant_invalid_response'));
		}

		$request = $this->_new_request('ThreeDSecureAuthentication');
		$request->ThreeDSecureMessage->MerchantAuthentication['MerchantID'] = $this->setting('merchant_id');
		$request->ThreeDSecureMessage->MerchantAuthentication['Password'] = $this->setting('password');
		$request->ThreeDSecureMessage->ThreeDSecureInputData['CrossReference'] = $this->CI->input->post('MD');
		$request->ThreeDSecureMessage->ThreeDSecureInputData->PaRES = $this->CI->input->post('PaRes');

		return $request;
	}

	private function _new_request($action)
	{
		$request = new SimpleXMLElement("<$action></$action>");
		$request->addAttribute('xmlns', 'https://www.thepaymentgateway.net/');
		return $request;
	}

	private function _post_cardsave_request($request)
	{
		// the PHP SOAP library sucks, and SimpleXML can't append element trees
		$document = new DOMDocument('1.0', 'utf-8');
		$envelope = $document->appendChild($document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soap:Envelope'));
		$envelope->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$envelope->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
		$body = $envelope->appendChild($document->createElement('soap:Body'));
		$body->appendChild($document->importNode(dom_import_simplexml($request), TRUE));

		// post to Cardsave
		$http_headers = array(
			'Content-Type: text/xml; charset=utf-8',
			'SOAPAction: https://www.thepaymentgateway.net/'.$request->getName());
		$response_str = $this->post_request(self::PROCESS_URL, $document->saveXML(), NULL, NULL, $http_headers);

		// we only care about the content of the soap:Body element
		$response_dom = DOMDocument::loadXML($response_str);
		$response = simplexml_import_dom($response_dom->documentElement->firstChild->firstChild);

		$result_elem = $request->getName().'Result';
		$status = (int)$response->$result_elem->StatusCode;
		switch ($status)
		{
			case 0:
				// success
				return $response;
			case 3:
				// redirect for 3d authentication
				$data = array(
					'PaReq' => (string)$response->TransactionOutputData->ThreeDSecureOutputData->PaREQ,
					'TermUrl' => $this->param('return_url'),
					'MD' => (string)$response->TransactionOutputData['CrossReference'],
				);

				$acs_url = (string)$response->TransactionOutputData->ThreeDSecureOutputData->ACSURL;
				$this->post_redirect($acs_url, $data, lang('merchant_3dauth_redirect'));
				break;
			default:
				// error
				throw new Merchant_exception((string)$response->$result_elem->Message);
		}
	}
}

class Merchant_cardsave_response extends Merchant_response
{
	protected $_response;

	public function __construct($response)
	{
		$this->_response = $response;
		$this->_status = self::COMPLETE;
		$this->_reference = (string)$response->TransactionOutputData['CrossReference'];
	}
}

/* End of file ./libraries/merchant/drivers/merchant_cardsave.php */