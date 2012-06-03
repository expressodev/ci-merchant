# CI Merchant Library

## Requirements

 * CodeIgniter 2.0+

### Supported Payment Gateways

 * 2Checkout
 * Authorize.net AIM
 * Authorize.net SIM
 * DPS PaymentExpress PxPay
 * DPS PaymentExpress PxPost
 * Dummy (for testing purposes)
 * eWay Hosted
 * eWay Shared
 * GoCardless
 * Manual (for supporting check / bank transfers)
 * Payflow Pro
 * Paypal Express Checkout
 * Paypal Pro
 * Sage Pay Direct
 * Sage Pay Server
 * Stripe
 * WorldPay

### Quick Start

	// load the merchant library
	$this->load->library('merchant');

	// load a payment driver
	$this->merchant->load('paypal');

	// initialize payment driver settings (if not already done in config)
	$this->merchant->initialize(array(
		'paypal_email' => 'text@example.com'
	));

	// process payment
	$params = array(
		'amount' => 99.00,
		'currency' => 'USD',
		'reference' => 'Order #50',
	)
	$this->merchant->purchase($params);

	// process return from payment gateway (hosted payment gateways only)
	$this->merchant->purchase_return($params);

### License

You are free to use this code under the terms of the MIT License. See LICENSE.md for further details.
