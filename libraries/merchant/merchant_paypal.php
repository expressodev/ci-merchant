<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011 Crescendo Multimedia Ltd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Merchant Paypal Class
 *
 * Payment processing using Paypal Payments Standard
 */

class Merchant_paypal extends CI_Driver {

	public $name = 'PayPal';

	public $required_fields = array('amount', 'reference', 'currency_code', 'return_url', 'cancel_url', 'notify_url');

	public $settings = array(
		'paypal_email' => '',
		'test_mode' => FALSE
	);

	const PROCESS_URL = 'https://www.paypal.com/cgi-bin/webscr';
	const PROCESS_URL_TEST = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

	public $CI;

	public function __construct($settings = array())
	{
		foreach ($settings as $key => $value)
		{
			if(array_key_exists($key, $this->settings))	$this->settings[$key] = $value;
		}
		$this->CI =& get_instance();
	}

	public function _process($params)
	{
		// ask paypal to generate request url
		$request = array(
			'rm' => '2',
			'cmd' => '_xclick',
			'business' => $this->settings['paypal_email'],
			'return'=> $params['return_url'],
      		'cancel_return' => $params['cancel_url'],
      		'notify_url' => $params['notify_url'],
      		'item_name' => $params['reference'],
      		'amount' => sprintf('%01.2f', $params['amount']),
			'currency_code' => $params['currency_code'],
			'no_shipping' => 1
		);

		$url = $this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL;
		?>
<html>
	<head><title>Processing Payment...</title></head>
	<body onLoad="document.forms['paypal_form'].submit();">
	<center><h2>Please wait, your order is being processed and you will be redirected to the PayPal website.</h2></center>
	<form method="post" name="paypal_form" action="<?php echo $url; ?>">
	<?php foreach ($request as $key => $value): ?>
		<input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>"/>
	<?php endforeach ?>
	<center><br/><br/>If you are not automatically redirected to PayPal within 5 seconds...<br/><br/>
	<input type="submit" value="Click Here"></center>
	</form></body>
</html>
	<?php
		exit();
	}

	public function _process_return()
	{
		$action = $this->CI->input->get('action', TRUE);

		if ($action === FALSE) return new Merchant_response('failed', 'invalid_response');

		if ($action === 'success') return new Merchant_response('return', '', $_POST['txn_id']);

		if($action === 'cancel') return new Merchant_response('failed', 'payment_cancelled');

		if ($action === 'ipn')
		{
			// generate the post string from _POST
			$memo = $this->CI->input->post('memo');
			$post_string = 'cmd=_notify-validate&'.http_build_query($_POST);

			$curl = curl_init();
        	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        	curl_setopt($curl, CURLOPT_POST, 1);
        	curl_setopt($curl, CURLOPT_HEADER , 0);
        	curl_setopt($curl, CURLOPT_VERBOSE, 1);
        	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        	curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        	curl_setopt($curl, CURLOPT_URL, $this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL);
        	curl_setopt($curl, CURLOPT_POSTFIELDS, $post_string);
        	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', 'Content-Length: '.strlen($post_string)));
        	$response = curl_exec($curl);

			if (strpos("VERIFIED", $response) !== FALSE)
			{   // Valid IPN transaction.
				return new Merchant_response('authorized', 'payment_authorized - memo='.$memo, $_POST['txn_id'], (string)$_POST['mc_gross']);
      		}
			else
			{   // Invalid IPN transaction
				return new Merchant_response('failed', 'invalid_response');
      		}
		}
	}
}
/* End of file ./libraries/merchant/drivers/merchant_paypal.php */