<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011-2012 Crescendo Multimedia Ltd
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

define('MERCHANT_VENDOR_PATH', realpath(dirname(__FILE__).'/../vendor'));
define('MERCHANT_DRIVER_PATH', realpath(dirname(__FILE__).'/merchant'));

/**
 * Merchant Class
 *
 * Payment processing for CodeIgniter
 */
class Merchant
{
	protected $_driver;

	public function __construct($driver = NULL)
	{
		if ( ! empty($driver))
		{
			$this->load($driver);
		}
	}

	public function __call($function, $arguments)
	{
		if ( ! empty($this->_driver))
		{
			return call_user_func_array(array($this->_driver, $function), $arguments);
		}
	}

	public function __get($property)
	{
		if ( ! empty($this->_driver))
		{
			return $this->_driver->$property;
		}
	}

	/**
	 * Load the specified driver
	 */
	public function load($driver)
	{
		$this->_driver = $this->_create_instance($driver);
		return $this->_driver !== FALSE;
	}

	/**
	 * Returns the name of the currently loaded driver
	 */
	public function active_driver()
	{
		$class_name = get_class($this->_driver);
		if ($class_name === FALSE) return FALSE;
		return str_replace('Merchant_', '', $class_name);
	}

	/**
	 * Load and create a new instance of a driver.
	 */
	protected function _create_instance($driver)
	{
		$driver_class = 'Merchant_'.strtolower($driver);
		if (class_exists($driver_class)) return new $driver_class;

		$driver_path = MERCHANT_DRIVER_PATH.'/'.strtolower($driver_class).'.php';
		if (file_exists($driver_path))
		{
			require_once($driver_path);
			if (class_exists($driver_class)) return new $driver_class;
		}

		return FALSE;
	}

	public function initialize($settings)
	{
		if ( ! is_array($settings)) return;

		foreach ($settings as $key => $value)
		{
			if (isset($this->_driver->settings[$key]))
			{
				if (is_bool($this->_driver->settings[$key])) $value = (bool)$value;

				$this->_driver->settings[$key] = $value;
			}
		}
	}

	public function get_valid_drivers()
	{
		$valid_drivers = array();

		foreach (scandir(MERCHANT_DRIVER_PATH) as $file_name)
		{
			$driver_path = MERCHANT_DRIVER_PATH.'/'.$file_name;
			if (stripos($file_name, 'merchant_') === 0 AND is_file($driver_path))
			{
				require_once($driver_path);

				$driver_class = ucfirst(str_replace('.php', '', $file_name));
				if (class_exists($driver_class))
				{
					$valid_drivers[] = str_replace('Merchant_', '', $driver_class);
				}
			}
		}

		return $valid_drivers;
	}

	public function process($params = array())
	{
		if (isset($params['card_no']) AND empty($_SERVER['HTTPS']))
		{
			show_error('Card details were not submitted over a secure connection.');
		}

		if (is_array($this->_driver->required_fields))
		{
			foreach ($this->_driver->required_fields as $field_name)
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

		return $this->_driver->_process($params);
	}

	public function process_return()
	{
		if (method_exists($this->_driver, '_process_return'))
		{
			return $this->_driver->_process_return();
		}

		return new Merchant_response('failed', 'return_not_supported');
	}

	/**
	 * Curl helper function
	 *
	 * Let's keep our cURLs consistent
	 */
	public static function curl_helper($url, $post_data = NULL, $username = NULL, $password = NULL)
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

		if ($username !== NULL)
		{
			curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
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

abstract class Merchant_driver
{
	public $settings;
	public $required_fields;

	public abstract function _process($params);
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