<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
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
* @version	$Revision: 12649 $
* @date		$Date: 2005-07-06 17:47:10 -0500 (Wed, 06 Jul 2005) $
*/
class vB_PaidSubscriptionMethod_nochex extends vB_PaidSubscriptionMethod
{
	/**
	* The currencies that are supported by this payment provider
	*
	* @var	array
	*/
	var $supported_currency = array('gbp' => true);

	/**
	* The variable indicating if this payment provider supports recurring transactions
	*
	* @var	bool
	*/
	var $supports_recurring = false;

	/**
	* Perform verification of the payment, this is called from the payment gatewa
	*
	* @return	bool	Whether the payment is valid
	*/
	function verify_payment()
	{
		// Leave these values at TYPE_STR since they need to be sent back to nochex just as they were received
		$this->registry->input->clean_array_gpc('p', array(
			'order_id'	=> TYPE_STR,
			'amount'	=> TYPE_STR,
			'transaction_id' => TYPE_STR
		));

		$this->transaction_id = $this->registry->GPC['transaction_id'];

		foreach($_POST AS $key => $val)
		{
			if (!empty($val))
			{
				$query[] = $key . '=' . urlencode($val);
			}
		}
		$query = implode('&', $query);
		
		$used_curl = false;
		
		if (function_exists('curl_init') AND $ch = curl_init())
		{
			curl_setopt($ch, CURLOPT_URL, 'https://www.nochex.com/nochex.dll/apc/apc');
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			curl_setopt($ch, CURLOPT_SSLVERSION, 2);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
			$result = curl_exec($ch);
			curl_close($ch);
			if ($result !== false)
			{
				$used_curl = true;
			}
		}
		if (PHP_VERSION >= '4.3.0' AND function_exists('openssl_open') AND !$used_curl)
		{
			$context = stream_context_create();
		
			$header = "POST /nochex.dll/apc/apc HTTP/1.0\r\n";
			$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$header .= "Content-Length: " . strlen($query) . "\r\n\r\n";
		
			if ($fp = fsockopen('ssl://www.nochex.com', 443))
			{
				fwrite($fp, $header . $query);
				do
				{
					$result = fread($fp, 1024);
					if (strlen($result) == 0 OR strcmp($result, 'AUTHORISED') == 0)
					{
						break;
					}
				} while (true);
				fclose($fp);
			}
		}

		if ($result == 'AUTHORISED')
		{
			$this->paymentinfo = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentinfo WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['order_id']) . "'");
			// lets check the values
			if (!empty($this->paymentinfo))
			{
				$sub = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $this->paymentinfo['subscriptionid']);
				$cost = unserialize($sub['cost']);
		
				// Check if its a payment or if its a reversal
				if ($this->registry->GPC['amount'] == $cost["{$this->paymentinfo[subscriptionsubid]}"]['cost']['gbp'])
				{
					$this->type = 1;
				}
			}

			return true;
		}
		else
		{
			$this->error = 'Invalid Request';
		}
		return false;
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
		$item = $hash;
		$currency = strtoupper($currency);

		$form['action'] = 'https://www.nochex.com/nochex.dll/checkout';
		$form['method'] = 'post';

		// load settings into array so the template system can access them
		$settings = $this->settings;

		eval('$form[\'hiddenfields\'] .= "' . fetch_template('subscription_payment_nochex') . '";');
		return $form;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12649 $
|| ####################################################################
\*======================================================================*/
?>