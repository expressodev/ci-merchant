<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// Welcome to First Data Global Gateway Connect - TEST ACCOUNT

// Welcome to Internet payment processing with the First Data Global Gateway! We are pleased you have 
// chosen the Global Gateway Connect service for your e-commerce Web site, and we offer the following 
// information to ensure effective setup. Please read this e-mail carefully!

// Forget Your Password?
// If you should forget your private password at anytime, call the Internet Support at (888)477-3611, 
// to obtain a new temporary password. After you receive a new password, it will take approximately 
// 30 minutes for the system to update. You should change your new temporary password the next time 
// you log on to the Global Gateway Virtual Terminal.

// Technical Questions? 
// Please contact technical support at (888) 477-3611.

// Getting Started:
// Below are the URLs you will need to access the Global Gateway Connect administrative functions 
// and to use Online Help or Reports. You must access the Global Gateway Connect administrative 
// functions and follow onscreen instructions to complete merchant setup.

// Global Gateway Connect Administration Program URL =>
// Access Global Gateway Connect Administration in the Global Gateway Virtual Terminal Administration section:
// -  Visit https://www.staging.yourpay.com
// - Log in to the Virtual Terminal.
// - Click on "Administration" in the Main Menu Bar.
// - Click on the word "Connect Settings" in the Side Menu Box to enter necessary URLs to enable the product.
// - Click on the words "Connect Setup" in the Side Menu Box to customize your payment form and 
// enter custom fields.

// Global Gateway Connect Posting URL => https://www.staging.yourpay.com/lpcentral/servlet/lppay

// To access Global Gateway reports and administrative tools, please use the Global Gateway Virtual 
// Terminal. Log on using the URL below.

// Visit http:// https://www.staging.yourpay.com and click on the ?Virtual Terminal? login link

// After you log on to the Global Gateway Virtual Terminal, you should change your temporary password 
// to a new secret or private password. To change your password, click on Administration in the Main Menu 
// Bar, then on Change Password. Choose a password that is familiar to you, and commit it to memory. Once 
// you establish your new password, First Data will have no record of your private password. 
// Note: For your own security, please do not share this password with anyone.

// Thank you for processing with the First Data Global Gateway. We appreciate your business!


/**
 * Merchant First Data Class
 *
 * Payment processing using First Data (external)
 * Documentataion: https://www.firstdata.com/downloads/marketing-merchant/fdgg-web-service-api.pdf
 */

class Merchant_firstdata extends Merchant_driver
{
	const PROCESS_URL = 'https://ws.firstdataglobalgateway.com/fdggwsapi';
	const PROCESS_URL_TEST = 'https://ws.merchanttest.firstdataglobalgateway.com/fdggwsapi/services';

	public function default_settings()
	{
		return array(
			'host_url' => '',
			'host_port' => '',
			'keyfile_location' => '',
			'store_no' => '',
			'password' => array('type' => 'password'),
			'test_mode' => FALSE
		);

	}

	
	public function purchase()
	{

		include"../linkpoint/linkpoint_module.php";
		$mylphp=new lphp;

		$request = $this->_build_purchase_or_return('SALE');
		$response = $mylphp->curl_process($request);
		return new Merchant_firstdata_response($response);

	}

	public function refund()
	{

		include"../linkpoint/linkpoint_module.php";
		$mylphp=new lphp;

		$request = $this->_build_purchase_or_return('RETURN');
		$response = $mylphp->curl_process($request);
		return new Merchant_firstdata_response($response);

	}


	private function _build_purchase_or_return($method)
	{

			$this->require_params('card_no', 'name', 'exp_month', 'exp_year', 'csc');

			# constants
			$myorder["host"]       = $this->setting('host_url');
			$myorder["port"]       = $this->setting('host_port');
			$myorder["keyfile"]    = $this->setting('keyfile_location'); 
			$myorder["configfile"] = $this->setting('store_no');
			$myorder["username"]   = 'WD'.$this->setting('store_no').'._.1';
			$myorder["password"]   = $this->setting('password');

			# transaction details
			$myorder["ordertype"]         = $method;
			$myorder["result"]            = 'LIVE';
			$myorder["transactionorigin"] = 'ECI';
			$myorder["oid"]               = $this->param('order_id');
			$myorder["taxexempt"]         = 'NO';
			$myorder["terminaltype"]      = 'UNSPECIFIED';
			$myorder["ip"]                = $this->CI->input->ip_address();

			# totals
			$myorder["chargetotal"] = $this->amount_dollars(); //0.00

			# card info
			$myorder["cardnumber"]   = $this->param('card_no'); //xxxxxxxxxxxxxxxx
			$myorder["cardexpmonth"] = $this->param('exp_month'); //xx
			$myorder["cardexpyear"]  = $this->param('exp_year') % 100; //xx
			$myorder["cvmindicator"] = 'PROVIDED';
			$myorder["cvmvalue"]     = $this->param('csc'); //xxx or xxxx

			# BILLING INFO
			$myorder["name"]     = $this->param('name');
			$myorder["address1"] = $this->param('address1');
			$myorder["address2"] = $this->param('address2');
			$myorder["city"]     = $this->param('city');
			$myorder["state"]    = $this->param('region');
			$myorder["country"]  = $this->param('country');
			$myorder["phone"]    = $this->param('phone');
			$myorder["email"]    = $this->param('email');
			$myorder["zip"]      = $this->param('postcode');


			# MISC
			$myorder["comments"] = $this->param('custom1');

			//$myorder["debugging"]="true";

		return $myorder;

	}

}

class Merchant_firstdata_response extends Merchant_response
{
	protected $_response;

	public function __construct($response)
	{
		
		$this->_response = $response;

		$this->_status = self::FAILED;
		$this->_message = (string)$this->_response['r_approved'];
		$this->_reference = (string)$this->_response['r_code'];

		if ((string)$this->_response['r_approved'] == 'APPROVED')
		{
			$this->_status = self::COMPLETE;
		}

	}
}


/* End of file ./libraries/merchant/drivers/merchant_worldpay.php */