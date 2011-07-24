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
		'Merchant_authorize_net',
		'Merchant_authorize_net_sim',
		'Merchant_dps_pxpay',
		'Merchant_dps_pxpost',
		'Merchant_dummy',
		'Merchant_paypal',
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

		$field_check = FALSE;
		if (is_array($this->{$this->_adapter}->required_fields))
		{
			foreach ($this->{$this->_adapter}->required_fields as $field_name)
			{
				if (empty($params[$field_name])) $field_check = $field_name;
			}
		}

		if ($field_check !== FALSE)
		{
			$response = new Merchant_response('failed', 'field_missing');
			$response->error_field = $field_check;
			return $response;
		}

		return $this->{$this->_adapter}->_process($params);
	}

	public function process_return()
	{
		$adapter = $this->{$this->_adapter};
		if (method_exists($adapter, '_process_return'))	return $adapter->_process_return();
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