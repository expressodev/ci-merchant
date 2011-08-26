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

if ( ! class_exists('CI_Driver_Library'))
{
	get_instance()->load->library('driver');
}

define('MERCHANT_VENDOR_PATH', realpath(dirname(__FILE__).'/../vendor'));
define('MERCHANT_DRIVER_PATH', realpath(dirname(__FILE__).'/merchant'));

/**
 * Merchant Class
 *
 * Payment processing for CodeIgniter
 */

class Merchant extends CI_Driver_Library {

	protected $_adapter;

	public $valid_drivers = array(
		'Merchant_2checkout',
		'Merchant_authorize_net',
		'Merchant_authorize_net_sim',
		'Merchant_dps_pxpay',
		'Merchant_dps_pxpost',
		'Merchant_dummy',
		'Merchant_paypal',
		'Merchant_paypal_pro',
		'Merchant_eway',
		'Merchant_eway_shared',
		'Merchant_sagepay_direct',
	);

	public function __construct($driver = NULL)
	{
		$this->load($driver);
	}

	/**
	 * Check for drivers in our subfolder
	 */
	public function __get($child)
	{
		$driver_file = MERCHANT_DRIVER_PATH.'/merchant_'.strtolower($child).'.php';
		if (file_exists($driver_file)) include_once $driver_file;
		return parent::__get($child);
	}

	/**
	 * Load the specified driver
	 */
	public function load($driver)
	{
		if ( ! in_array('Merchant_'.$driver, $this->valid_drivers)) return FALSE;

		$this->_adapter = $driver;
		return TRUE;
	}

	/**
	 * The name of the currently loaded driver
	 */
	public function name()
	{
		if (isset($this->{$this->_adapter}->name))
		{
			return $this->{$this->_adapter}->name;
		}
		else
		{
			return FALSE;
		}
	}

	public function initialize($settings)
	{
		foreach ($settings as $key => $value)
		{
			if (isset($this->{$this->_adapter}->settings[$key]))
			{
				if (is_bool($this->{$this->_adapter}->settings[$key])) $value = (bool)$value;

				$this->{$this->_adapter}->settings[$key] = $value;
			}
		}
	}

	public function process($params = array())
	{
		if (isset($params['card_no']) AND empty($_SERVER['HTTPS']))
		{
			show_error('Card details were not submitted over a secure connection.');
		}

		if (is_array($this->{$this->_adapter}->required_fields))
		{
			foreach ($this->{$this->_adapter}->required_fields as $field_name)
			{
				if (empty($params[$field_name]))
				{
					$response = new Merchant_response('failed', 'field_missing');
					$response->error_field = $field_name;
					return $response;
				}
			}
		}

		// normalize months to 2 digits and years to 4
		if (isset($params['exp_month'])) $params['exp_month'] = sprintf('%02d', (int)$params['exp_month']);
		if (isset($params['exp_year'])) $params['exp_year'] = sprintf('%04d', (int)$params['exp_year']);
		if (isset($params['start_month'])) $params['start_month'] = sprintf('%02d', (int)$params['start_month']);
		if (isset($params['start_year'])) $params['start_year'] = sprintf('%04d', (int)$params['start_year']);

		// normalize card_type to lowercase
		if (isset($params['card_type'])) $params['card_type'] = strtolower($params['card_type']);

		return $this->{$this->_adapter}->_process($params);
	}

	public function process_return()
	{
		$adapter = $this->{$this->_adapter};
		if (method_exists($adapter, '_process_return'))	return $adapter->_process_return();
		else return new Merchant_response('failed', 'return_not_supported');
	}

	/**
	 * Curl helper function
	 *
	 * Let's keep our cURLs consistent
	 */
	public static function curl_helper($url, $post_data = NULL)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // don't check client certificate

		if ($post_data !== NULL)
		{
			if (is_array($post_data))
			{
				$post_data = http_build_query($post_data);
			}

			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		}

		$response = array();
		$response['data'] = curl_exec($ch);
		$response['error'] = curl_error($ch);

		curl_close($ch);
		return $response;
	}

	/**
	 * Redirect Post function
	 *
	 * Automatically redirect the user to payment pages which require POST data
	 */
	public static function redirect_post($post_url, $data)
	{
		?>
<!DOCTYPE html>
<html>
<head><title>Redirecting...</title></head>
<body onload="document.payment.submit();">
	<p>Please wait while we redirect you to the payment page...</p>
	<form name="payment" action="<?php echo htmlspecialchars($post_url); ?>" method="post">
		<p>
			<?php if (is_array($data)): ?>
				<?php foreach ($data as $key => $value): ?>
					<input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>" />
				<?php endforeach ?>
			<?php else: ?>
				<?php echo $data; ?>
			<?php endif; ?>
			<input type="submit" value="Continue" />
		</p>
	</form>
</body>
</html>
	<?php
		exit();
	}
}

class Merchant_response
{
	public $status;
	public $message;
	public $txn_id;
	public $amount;
	public $error_field;

	public function __construct($status, $message, $txn_id = null, $amount = null)
	{
		$this->status = $status;
		$this->message = $message;
		$this->txn_id = $txn_id;
		$this->amount = $amount;
	}
}

/* End of file ./libraries/merchant/merchant.php */