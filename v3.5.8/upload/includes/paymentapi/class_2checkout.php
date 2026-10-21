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
* @version	$Revision: 15342 $
* @date		$Date: 2006-07-25 06:48:22 -0500 (Tue, 25 Jul 2006) $
*/
class vB_PaidSubscriptionMethod_2checkout extends vB_PaidSubscriptionMethod
{
	/**
	* The currencies that are supported by this payment provider
	*
	* @var	array
	*/
	var $supported_currency = array('usd' => true);

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
		$this->registry->input->clean_array_gpc('r', array(
			'order_number'  => TYPE_STR,
			'key'           => TYPE_STR,
			'cart_order_id' => TYPE_STR,
			'total'         => TYPE_NUM,
		));

		$this->transaction_id = $this->registry->GPC['order_number'];

		$check_hash = strtoupper(md5($this->settings['secret_word'] . $this->settings['twocheckout_id'] . $this->registry->GPC['order_number'] . $this->registry->GPC['total']));

		if ($check_hash == $this->registry->GPC['key'])
		{
			$this->paymentinfo = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentinfo WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['cart_order_id']) . "'");
			// lets check the values
			if (!empty($this->paymentinfo))
			{
				// dont need to check the amount since authornize.net dont include the currency when its sent back
				// the hash helps us get around this though
				$this->type = 1;
				return true;
			}
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

		$form['action'] = 'https://www.2checkout.com/cgi-bin/sbuyers/cartpurchase.2c';
		$form['method'] = 'get';

		// load settings into array so the template system can access them
		$settings =& $this->settings;

		eval('$form[\'hiddenfields\'] .= "' . fetch_template('subscription_payment_2checkout') . '";');
		return $form;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15342 $
|| ####################################################################
\*======================================================================*/
?>