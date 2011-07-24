CI Merchant Library
===================

Requirements
------------
 * CodeIgniter 2.0+

Quick Start
-----------

	// load the merchant library
	$this->load->library('merchant');

	// load a payment driver
	$this->merchant->load('paypal');

	// initialize payment driver settings (if not already done in config)
	$this->merchant->initialize(array(
		'paypal_email' => 'text@example.com'
	));

	// process payment
	$this->merchant->process(array(
		'amount' => 99.00,
		'currency_code' => 'USD',
		'reference' => 'Order #50'
	));

	// process return from payment gateway (hosted payment gateways only)
	$this->merchant->process_return();

License
-------

You are free to use this code under the terms of the MIT License. See LICENSE.txt for further details.
