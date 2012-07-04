<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011-2012 Adrian Macneil
 */

/**
 * Merchant Buckaroo Class
 *
 * Payment processing using Buckaroo
 */

class Merchant_buckaroo extends Merchant_driver
{
	const PROCESS_URL = 'https://payment.buckaroo.nl/sslplus/request_for_authorization.asp';

	public function default_settings()
	{
		return array(
			'merchant_id' => '',
			'secret' => '',
			'test_mode' => false,
		);
	}

	public function purchase()
	{
		$request = $this->_build_purchase();
		$this->post_redirect($this->_process_url(), $request);
	}

	public function purchase_return()
	{
		if ($this->CI->input->post('bpe_signature2') != $this->_calculate_response_signature())
		{
			return new Merchant_response(Merchant_response::FAILED, lang('merchant_invalid_response'));
		}

		$reference = $this->CI->input->post('bpe_trx');
		switch ($this->CI->input->post('bpe_result'))
		{
			case '100':
				// Credit card success
				return new Merchant_response(Merchant_response::COMPLETE, NULL, $reference);
				break;
			case '121':
				// PayPal success
				return new Merchant_response(Merchant_response::COMPLETE, NULL, $reference);
				break;
			case '801':
				// iDEAL success
				return new Merchant_response(Merchant_response::COMPLETE, NULL, $reference);
				break;
		}

		// All other errors are a failure
		return new Merchant_response(Merchant_response::FAILED, lang('merchant_failed'));
	}

	private function _build_purchase()
	{
		$this->require_params('return_url');

		$form = array();
		$form['BPE_Merchant'] = $this->setting('merchant_id');
		$form['BPE_Amount'] = $this->amount_cents();
		$form['BPE_Currency'] = $this->param('currency');
		$form['BPE_Language'] = 'EN';
		$form['BPE_Mode'] = (int)$this->setting('test_mode');
		$form['BPE_Signature2'] = '';
		$form['BPE_Invoice'] = $this->param('order_id');
		$form['BPE_Return_Success'] = $this->param('return_url');
		$form['BPE_Return_Reject'] = $this->param('return_url');
		$form['BPE_Return_Error'] = $this->param('return_url');
		$form['BPE_Return_Method'] = 'POST';
		$form['BPE_Signature2'] = $this->_calculate_signature();

		return $form;
	}

	private function _calculate_signature()
	{
		return md5(
			$this->setting('merchant_id') .
			$this->param('order_id') .
			$this->amount_cents() .
			$this->param('currency') .
			(int)$this->setting('test_mode') .
			$this->setting('secret'));
	}

	private function _calculate_response_signature()
	{
		return md5(
			$this->CI->input->post('bpe_trx') .
			$this->CI->input->post('bpe_timestamp') .
			$this->setting('merchant_id') .
			$this->param('order_id') .
			$this->param('currency') .
			$this->amount_cents() .
			$this->CI->input->post('bpe_result') .
			(int)$this->setting('test_mode') .
			$this->setting('secret'));
	}

	protected function _process_url()
	{
		return self::PROCESS_URL;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_dps_pxpay.php */