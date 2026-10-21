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
* @version	$Revision: 13579 $
* @date		$Date: 2005-11-01 14:41:51 -0600 (Tue, 01 Nov 2005) $
*/
class vB_PaidSubscriptionMethod_moneybookers extends vB_PaidSubscriptionMethod
{
	/**
	* The currencies that are supported by this payment provider
	*
	* @var	array
	*/
	var $supported_currency = array('usd' => true, 'gbp' => true, 'eur' => true, 'aud' => true, 'cad' => true);

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
		$this->registry->input->clean_array_gpc('p', array(
			'pay_to_email'           => TYPE_STR,
			'merchant_id'            => TYPE_STR,
			'transaction_id'         => TYPE_STR,
			'mb_transaction_id'      => TYPE_UINT,
			'status'                 => TYPE_STR,
			'md5sig'                 => TYPE_STR,
			'amount'                 => TYPE_STR,
			'currency'               => TYPE_STR,
			'mb_amount'              => TYPE_STR,
			'mb_currency'            => TYPE_STR,
		));

		$this->transaction_id = $this->registry->GPC['mb_transaction_id'];

		$check_hash = strtoupper(md5($this->registry->GPC['merchant_id'] . $this->registry->GPC['transaction_id'] . strtoupper(md5(strtolower($this->settings['mbsecret']))) . $this->registry->GPC['mb_amount'] . $this->registry->GPC['mb_currency'] . $this->registry->GPC['status']));

		if ($check_hash == $this->registry->GPC['md5sig'] AND strtolower($this->registry->GPC['pay_to_email']) == strtolower($this->settings['mbemail']))
		{
			if (intval($this->registry->GPC['status']) == 2)
			{
				$this->paymentinfo = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentinfo WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['transaction_id']) . "'");
				// lets check the values
				if (!empty($this->paymentinfo))
				{
					$sub = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $this->paymentinfo['subscriptionid']);
					$cost = unserialize($sub['cost']);
					if (doubleval($this->registry->GPC['amount']) == doubleval($cost["{$this->paymentinfo[subscriptionsubid]}"]['cost'][strtolower($this->registry->GPC['currency'])]))
					{
						$this->type = 1;
						return true;
					}
				}
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
		$currency = strtoupper($currency);

		$form['action'] = 'https://www.moneybookers.com/app/payment.pl';
		$form['method'] = 'post';

		// load settings into array so the template system can access them
		$settings =& $this->settings;

		eval('$form[\'hiddenfields\'] .= "' . fetch_template('subscription_payment_moneybookers') . '";');
		return $form;
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13579 $
|| ####################################################################
\*======================================================================*/
?>