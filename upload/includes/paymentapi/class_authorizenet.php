<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Class that provides payment verification and form generation functions
*
* @package	vBulletin
* @version	$Revision: 92875 $
* @date		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
*/
class vB_PaidSubscriptionMethod_authorizenet extends vB_PaidSubscriptionMethod
{
	/**
	* The variable indicating if this payment provider supports recurring transactions
	*
	* @var	bool
	*/
	var $supports_recurring = false;

	/**
	* Display feedback via payment_gateway.php when the callback is made
	*
	* @var	bool
	*/
	var $display_feedback = true;

	/**
	* Perform verification of the payment, this is called from the payment gatewa
	*
	* @return	bool	Whether the payment is valid
	*/
	function verify_payment()
	{
		$this->registry->input->clean_array_gpc('p', array(
			'x_amount'               => TYPE_STR,
			'x_trans_id'             => TYPE_STR,
			'x_description'          => TYPE_STR,
			'x_MD5_Hash'             => TYPE_STR,
			'x_response_code'        => TYPE_UINT,
			'x_invoice_num'          => TYPE_STR,
			'x_response_reason_text' => TYPE_NOHTML,
			'x_response_reason_code' => TYPE_NOHTML,
		));

		$this->transaction_id = $this->registry->GPC['x_trans_id'];
		if (!preg_match('#([a-f0-9]{32})#i', $this->registry->GPC['x_description'], $matches))
		{
			$this->error = "No Payment Hash Found";
			return false;
		}
		$paymenthash = $matches[1];

		$check_hash = strtoupper(md5($this->settings['authorize_md5secret'] . $this->settings['authorize_loginid'] . $this->registry->GPC['x_trans_id'] . $this->registry->GPC['x_amount']));

		if ($check_hash == $this->registry->GPC['x_MD5_Hash'])
		{
			if ($this->registry->GPC['x_response_code'] == 1)
			{
				$this->paymentinfo = $this->registry->db->query_first("
					SELECT paymentinfo.*, user.username
					FROM " . TABLE_PREFIX . "paymentinfo AS paymentinfo
					INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
					WHERE hash = '" . $this->registry->db->escape_string($paymenthash) . "'
				");

				// lets check the values
				if (!empty($this->paymentinfo))
				{
					$sub = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $this->paymentinfo['subscriptionid']);
					$this->paymentinfo['currency'] = '';
					$this->paymentinfo['amount'] = floatval($this->registry->GPC['x_amount']);
					// dont need to check the amount since authornize.net dont include the currency when its sent back
					// the hash helps us get around this though
					$this->type = 1;
					return true;
				}
			}
			else if ($this->registry->GPC['x_response_code'] == 2 OR $this->registry->GPC['x_response_code'] == 3)
			{
				$this->error = $this->registry->GPC['x_response_reason_text'] . ' (' . $this->registry->GPC['x_response_reason_code'] . ')';
			}
			else
			{	// deliberately not phrased, this should never happen anyway
				$this->error = "Unknown Error";
			}
		}
		else
		{
			$this->error = "Hash check failed";
		}
		return false;
	}

	/**
	* Test that required settings are available, and if we can communicate with the server (if required)
	*
	* @return	bool	If the vBulletin has all the information required to accept payments
	*/
	function test()
	{
		return (!empty($this->settings['authorize_loginid']) AND !empty($this->settings['txnkey']));
	}

	/**
	* Generates HTML for the subscription form page
	*
	* @param	string		Hash used to indicate the transaction within vBulletin
	* @param	string		The cost of this payment
	* @param	string		The currency of this payment
	* @param	array		Information regarding the subscription that is being purchased
	* @param	array		Information about the user who is purchasing this subscription
	* @param	array		Array containing specific data about the cost and time for the specific subscription period
	*
	* @return	array		Compiled form information
	*/
	function generate_form_html($hash, $cost, $currency, $subinfo, $userinfo, $timeinfo)
	{
		global $vbphrase, $vbulletin, $stylevar, $show;

		$item = $hash;
		$currency = strtoupper($currency);

		$sequence = vbrand(1, 1000);
		$fingerprint = $this->hmac($this->settings['txnkey'], $this->settings['authorize_loginid'] . '^' . $sequence . '^' . TIMENOW . '^' . $cost . '^' . $currency);
		$timenow = TIMENOW;

		$form['action'] = 'https://secure.authorize.net/gateway/transact.dll';
		$form['method'] = 'post';

		// load settings into array so the template system can access them
		$settings =& $this->settings;

		eval('$form[\'hiddenfields\'] .= "' . fetch_template('subscription_payment_authorizenet') . '";');
		return $form;
	}

	/**
	* RFC 2104 HMAC
	*
	* @param	string		Key to hash data with
	* @param	string		Data
	*
	* @return	string		MD5 HMAC
	*/
	function hmac($key, $data)
	{
		$b = 64;
		if (strlen($key) > $b)
		{
			$key = pack("H*", md5($key));
		}
		$key  = str_pad($key, $b, chr(0x00));
		$ipad = str_pad('', $b, chr(0x36));
		$opad = str_pad('', $b, chr(0x5c));
		$k_ipad = $key ^ $ipad;
		$k_opad = $key ^ $opad;

		return md5($k_opad . pack("H*", md5($k_ipad . $data)));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
