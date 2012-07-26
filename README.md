# CI Merchant Library

## Requirements

 * CodeIgniter 2.0+

## Supported Payment Gateways

 * 2Checkout
 * Authorize.net AIM
 * Authorize.net SIM
 * DPS PaymentExpress PxPay
 * DPS PaymentExpress PxPost
 * Dummy (for testing purposes)
 * eWay Hosted
 * eWay Shared
 * GoCardless
 * iDEAL
 * Manual (for supporting check / bank transfers)
 * Payflow Pro
 * Paypal Express Checkout
 * Paypal Pro
 * Sage Pay Direct
 * Sage Pay Server
 * Stripe
 * WorldPay

## Quick Start

	//use this for sparks
	$this->load->spark('ci-merchant/1.1.0');

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
		'description' => 'something descriptive', 
		'return_url' => 'http://www.google.com',
	);
	/* merchant_paypal settable params */
	$others = array(
		'amount' => 99.00,
		'currency' => 'USD',
		'description' => 'something descriptive',
		'order_id' => 'unique id",
		'return_url' => 'http://yoursite.com/paid', // (also the notify URL for IPN)
		'cancel_url' => 'http://yoursite.com/notpaid',
	);

	/* end other params */
	$this->merchant->purchase($params);


	// process return from payment gateway (hosted payment gateways only)
	$this->merchant->purchase_return($params);



## License

CI Merchant is released under the MIT License. For more information, see [License](https://github.com/expressodev/ci-merchant/blob/develop/LICENSE.md).
