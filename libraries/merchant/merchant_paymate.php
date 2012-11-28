<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Merchant Paymate Class
 *
 * Payment processing using Paymate
 * @link http://www.paymate.com/cms/index.php/sellers/sell-on-your-website/sell-on-your-website/199
 */

class Merchant_paymate extends Merchant_driver
{
	const API_ENDPOINT = 'https://www.paymate.com/PayMate/api';
	const CHECKOUT_ENDPOINT = 'https://www.paymate.com/PayMate/ExpressPayment';

	public function default_settings()
	{
		return array(
			'username' => '',
		);
	}

	public function purchase()
	{
		$request = array();
		$request['mid'] = $this->setting('username');
		$request['amt'] = $this->param('amount');
		$request['amt_editable'] = 'N';
		$request['currency'] = $this->param('currency');
		$request['ref'] = $this->param('transaction_id');
		$request['pmt_sender_email'] = $this->param('email');
		$request['pmt_contact_firstname'] = $this->param('first_name');
		$request['pmt_contact_surname'] = $this->param('last_name');
		$request['pmt_contact_phone'] = $this->param('phone');
		$request['pmt_country'] = strtoupper($this->param('country'));
		$request['regindi_state'] = $this->param('region');
		$request['regindi_address1'] = $this->param('address1');
		$request['regindi_address2'] = $this->param('address2');
		$request['regindi_sub'] = $this->param('city');
		$request['regindi_pcode'] = $this->param('postcode');
		$request['return'] = $this->param('return_url');
		$request['popup'] = 'false';

		$this->post_redirect(self::CHECKOUT_ENDPOINT, $request);
	}

	public function purchase_return()
	{
		$code = $this->CI->input->post('responseCode');
		$reference = $this->CI->input->post('transactionID');

		switch ($code)
		{
			case 'PD':
				return new Merchant_response(Merchant_response::FAILED, 'Payment declined', $reference);
				break;
			case 'PP':
				return new Merchant_response(Merchant_response::FAILED, 'Payment processing', $reference);
				break;
			case 'PA':
				return new Merchant_response(Merchant_response::COMPLETE, null, $reference);
				break;
		}

		return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
	}
}
