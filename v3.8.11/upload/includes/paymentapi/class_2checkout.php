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
class vB_PaidSubscriptionMethod_2checkout extends vB_PaidSubscriptionMethod
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
	* Perform verification of the payment, this is called from the payment gateway
	*
	* @return	bool	Whether the payment is valid
	*/
	function verify_payment()
	{
		// use _GET rather than GPC since we dont want the value changed
		$unclean_total = $_REQUEST['total'];

		$this->registry->input->clean_array_gpc('r', array(
			'order_number'  => TYPE_STR,
			'key'           => TYPE_STR,
			'cart_order_id' => TYPE_STR,
			'total'         => TYPE_NUM,
		));

		$this->transaction_id = $this->registry->GPC['order_number'];

		$check_hash = strtoupper(md5($this->settings['secret_word'] . $this->settings['twocheckout_id'] . $this->registry->GPC['order_number'] . $unclean_total));

		if ($check_hash == $this->registry->GPC['key'])
		{
			$this->paymentinfo = $this->registry->db->query_first("
				SELECT paymentinfo.*, user.username
				FROM " . TABLE_PREFIX . "paymentinfo AS paymentinfo
				INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
				WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['cart_order_id']) . "'
			");
			// lets check the values
			if (!empty($this->paymentinfo))
			{
				$this->paymentinfo['currency'] = 'usd';
				$this->paymentinfo['amount'] = $this->registry->GPC['total'];
				// dont need to check the amount since 2checkout dont include the currency when its sent back
				// the hash helps us get around this though
				$this->type = 1;
				return true;
			}
			else
			{
				$this->error = 'Duplicate transaction.';
			}
		}
		else
		{
			$this->error = 'Hash mismatch, please check your secret word.';
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
		return (!empty($this->settings['secret_word']) AND !empty($this->settings['twocheckout_id']));
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

		$form['action'] = 'https://www.2checkout.com/cgi-bin/sbuyers/cartpurchase.2c';
		$form['method'] = 'get';

		// load settings into array so the template system can access them
		$settings =& $this->settings;
		$subinfo['twocheckout_prodid'] = htmlspecialchars_uni($timeinfo['twocheckout_prodid']);

		eval('$form[\'hiddenfields\'] .= "' . fetch_template('subscription_payment_2checkout') . '";');
		return $form;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
