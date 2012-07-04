<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011-2012 Adrian Macneil
 */

require_once(MERCHANT_DRIVER_PATH.'/merchant_buckaroo.php');

/**
 * Merchant Buckaroo PayPal Class
 *
 * Payment processing using Buckaroo
 */

class Merchant_buckaroo_paypal extends Merchant_buckaroo
{
	protected function _process_url()
	{
		return 'https://payment.buckaroo.nl/gateway/paypal_payment.asp';
	}
}

/* End of file ./libraries/merchant/drivers/merchant_buckaroo_paypal.php */