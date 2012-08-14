---
layout: default
---

# CI-Merchant: A CodeIgniter PHP Payments Library

CI-Merchant is a driver-based payment processing library built specifically for use in
[CodeIgniter](http://codeigniter.com/) web applications.
It allows you to integrate any supported payment gateway using a consistent API. It was
originally developed as part of [Expresso Store](http://exp-resso.com/store), an [e-commerce
module for ExpressionEngine](http://exp-resso.com/store), and is now used on hundreds of e-commerce sites worldwide.

CI-Merchant is released under the open source MIT license, and is [under active development](https://github.com/expressodev/ci-merchant/commits/develop).

## Supported Methods

Most payment gateways support the following methods:

* Authorize
* Capture
* Purchase (Combined Authorize & Capture)
* Refund

At this stage there is no support for recurring payments or token billing.

## Installation

CI-Merchant has fairly basic requirements. It has no dependencies on external libraries, and does
not repackage any libraries. Before you start, make sure you have the following:

* CodeIgniter 2.0+
* PHP 5.2+
* PHP CURL extension *(included by default on most servers)*
* PHP JSON extension *(certain gateways only)*

Adding CI-Merchant to your project is easy!

* [Install it via Sparks](http://getsparks.org/packages/ci-merchant/versions/HEAD/show) (recommended)
* [Download a zip directly from GitHub](https://github.com/expressodev/ci-merchant/zipball/master)

Or simply clone the git repo:

    $ git clone git://github.com/expressodev/ci-merchant

## Sounds great, how do I get started?

### Loading the library

CI-Merchant is based on the familiar "driver" pattern used in CodeIgniter. The first thing you
need to do is load the merchant library, then load the specific driver you need.
For example, to load the PayPal Express driver, you would use the following code:

    $this->load->library('merchant');
    $this->merchant->load('paypal_express');

### Initializing the gateway

Secondly, each payment driver has different settings which must be initialized. Settings are driver
options which generally will not change between payments, such as gateway API keys. To view
driver-specific settings, use the links on the left. You can also get the available settings
programmatically by calling `default_settings()` after loading the driver. This allows you to
display the settings in your control panel and store them in your database (though most projects
will prefer to hard-code the settings in a configuration file).

    $settings = $this->merchant->default_settings();

In the case of PayPal Express, the following settings are available:

* username
* password
* signature
* test_mode

The settings are initialized by passing an array to the `initialize()` method:

    $settings = array(
        'username' => '****',
        'password' => '****',
        'signature' => '****',
        'test_mode' => true);

    $this->merchant->initialize($settings);

### Processing a payment

Great! Now that you have loaded and initialized a payment driver, you are ready to start processing
payments. Each driver will support some or all of the following methods:

* `authorize()`
* `authorize_return()`
* `capture()`
* `purchase()`
* `purchase_return()`
* `refund()`

The `_return()` methods are only required for off-site payment gateways. We will come back to
them shortly. For now, we need only choose between `authorize` and `purchase`. Authorized
transactions only place a hold on the customer's card, and must be captured within a few days
(the exact time depends on your bank and payment processor), otherwise the authorization will
expire. This process is often required for physical goods, where funds must not be captured until
the items have been shipped.

For this example, we will use the simpler `purchase` method, which will transfer funds immediately.
Each payment driver requires slightly different parameters to be passed to the purchase method,
so check the driver-specific documentation. All payments require `amount` and `currency`
parameteres.  For the PayPal Express driver, the only other required parameter is `return_url`.

Pass the payment-specific details through to the driver using an array. Because PayPal Express
is an off-site payment gateway, we must specify a return URL which the customer will be redirected
to after a successful purchase. This return URL should be order-specific, so that you know which
order to mark as paid when the customer returns. In this example, we will redirect the customer
to the `payment_return` action on the `checkout` controller. Note that you should record the order
in your database before transferring the customer to PayPal (so that you can verify the order
details once payment is complete), and specify the order number in your return URL. We will also
pass the `cancel_url` parameter, which the customer will be redirected to if they click cancel on
the PayPal website.

    $params = array(
        'amount' => 100.00,
        'currency' => 'USD',
        'return_url' => 'https://www.example.com/checkout/payment_return/123',
        'cancel_url' => 'https://www.example.com/checkout');

    $response = $this->merchant->purchase($params);

This will create a payment request with PayPal, and immediately redirect the customer away from
your site. When the customer has completed their payment, they will be sent to the return URL you
specified. Some payment gateways accept credit cards directly on your site (on-site gateways),
and you will receive a `$response` immediately without the customer being redirected. In this case
you can skip the next step, and proceed to handling the response. Some gateways may only redirect
in certain situations (e.g. if the customer's bank supports 3D Secure), so you may need to handle
both situations.

The first thing you must do when the customer is returned to your site is load the order
details from your database. You must then call the `purchase_return()` method to verify the payment.
This method requires the same parameters you passed to the `purchase()` method (although some
parameters such as `return_url` are not required for `purchase_return()`). Because this is a fresh
request from the customer's browser, you will also need to initialize the merchant library again.

    $this->load->library('merchant');
    $this->merchant->load('paypal_express');
    $this->merchant->initialize($settings);
    $response = $this->merchant->purchase_return($params);

### Handling the response

The `$response` object returned from either the `purchase()` or `purchase_return()` method will be
an instance of the `Merchant_response` class. The response will have one of 5 statuses,
representing the state the payment is in:

* Merchant_response::AUTHORIZED
* Merchant_response::COMPLETE
* Merchant_response::FAILED
* Merchant_response::REDIRECT
* Merchant_response::REFUNDED

You can check the status of the response by calling the `status()` method:

    if ($response->status() == Merchant_response::COMPLETE)
    {
        // mark order as complete
    }

You can also simply check whether the response was successful (this means that the payment is any
status other than `FAILED`):

    if ($response->success())
    {
        // mark order as complete
    }

It is very important that you handle the `FAILED` case. For example, if the customer tried to forge
a payment return request, the response status will be `FAILED`.

Every response also has a message, which you can access using the `message()` method. Usually this
will only be useful for failed payments, where you should display the message to the customer,
and allow them to try again.

    if ($response->success())
    {
        // mark order as complete
    }
    else
    {
        $message = $response->message();
        echo('Error processing payment: ' . $message);
        exit;
    }

Finally, all payments will return a gateway generated reference (although sometimes it will be
blank for failed payments). You should store this in your database, in case you need to query the
payment with your payment gateway. It is also required for subsequent operations such as
`complete()` and `refund()`.

    $gateway_reference = $response->reference();

Congratulations! You have processed a simple payment using PayPal Express. You may now wish to
explore gateway-specific documentation, or check out the complete [API reference](/reference.html).
