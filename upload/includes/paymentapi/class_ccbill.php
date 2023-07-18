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
class vB_PaidSubscriptionMethod_ccbill extends vB_PaidSubscriptionMethod
{
	/**
	* The variable indicating if this payment provider supports recurring transactions
	*
	* @var	bool
	*/
	var $supports_recurring = false;

	/**
	* Perform verification of the payment, this is called from the payment gateway
	*
	* @return	bool	Whether the payment is valid
	*/
	function verify_payment()
	{
		$this->registry->input->clean_array_gpc('r', array(
			'clientAccnum'         => TYPE_STR,
			'clientSubacc'         => TYPE_STR,
			'subscription_id'      => TYPE_STR,
			'hash'                 => TYPE_STR,
			'typeId'               => TYPE_INT,
			'secretword'           => TYPE_STR,
			'reasonForDeclineCode' => TYPE_STR,
			'initialPrice'         => TYPE_NUM,
		));

		$this->transaction_id = $this->registry->GPC['subscription_id'];

		// reasonForDeclineCode will be set upon denial but CCBill can offer the user other payment options after a decline and submit again
		if (empty($this->registry->GPC['reasonForDeclineCode']) AND $this->registry->GPC['secretword'] == $this->settings['secretword'] AND preg_match('#^64\.38\.#', $this->registry->ipaddress))	// check REMOTE_ADDR = 64.38.*
		{
			$this->paymentinfo = $this->registry->db->query_first("
				SELECT paymentinfo.*, user.username
				FROM " . TABLE_PREFIX . "paymentinfo AS paymentinfo
				INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
				WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['hash']) . "'
			");
			// lets check the values
			if (!empty($this->paymentinfo))
			{
				$sub = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $this->paymentinfo['subscriptionid']);
				$this->paymentinfo['currency'] = 'usd';
				$this->paymentinfo['amount'] = floatval($this->registry->GPC['initialPrice']);
				$this->type = 1;
				return true;
			}
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
		return (!empty($this->settings['clientAccnum']) AND !empty($this->settings['clientSubacc']) AND !empty($this->settings['formName']));
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

		$form['action'] = 'https://bill.ccbill.com/jpost/signup.cgi';
		$form['method'] = 'post';

		// load settings into array so the template system can access them
		$settings =& $this->settings;
		$settings['email'] = htmlspecialchars_uni($this->registry->userinfo['email']);
		$subinfo['ccbillsubid'] = $timeinfo['ccbillsubid'];

		eval('$form[\'hiddenfields\'] .= "' . fetch_template('subscription_payment_ccbill') . '";');
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
