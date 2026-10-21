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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'payment_gateway');
define('SKIP_SESSIONCREATE', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
define('VB_AREA', 'Subscriptions');
define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
require_once(CWD . '/includes/init.php');

require_once(DIR . '/includes/adminfunctions.php');
require_once(DIR . '/includes/class_paid_subscription.php');

$vbulletin->input->clean_array_gpc('g', array(
	'method'    => TYPE_STR
));

$vbulletin->nozip = true;

$api = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentapi WHERE classname = '" . $db->escape_string($vbulletin->GPC['method']) . "'");
if (!empty($api))
{
	$subobj = new vB_PaidSubscription($vbulletin);
	if (file_exists(DIR . '/includes/paymentapi/class_' . $api['classname'] . '.php'))
	{
		require_once(DIR . '/includes/paymentapi/class_' . $api['classname'] . '.php');
		$api_class = 'vB_PaidSubscriptionMethod_' . $api['classname'];
		$apiobj = new $api_class($vbulletin);

		// at this point we know if the system is going to want to display feedback, so do the appropriate queries here
		if ($apiobj->display_feedback)
		{
			// initialize $vbphrase and set language constants
			$vbphrase = init_language();

			$vbulletin->userinfo['styleid'] = $vbulletin->options['styleid'];
			$style = $db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "style
				WHERE styleid = " . intval($vbulletin->options['styleid'])
			);
			define('STYLEID', $style['styleid']);

			cache_templates(array('STANDARD_REDIRECT', 'STANDARD_ERROR', 'STANDARD_ERROR_LITE', 'headinclude'), $style['templatelist']);
			$stylevar = fetch_stylevars($style, $vbulletin->userinfo);
			eval('$headinclude = "' . fetch_template('headinclude') . '";');
		}

		if (!empty($api['settings']))
		{ // need to convert this from a serialized array with types to a single value
			$apiobj->settings = $subobj->construct_payment_settings($api['settings']);
		}

		if ($apiobj->verify_payment())
		{
			// its a valid payment now lets check transactionid
			$transaction = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymenttransaction WHERE transactionid = '" . $db->escape_string($apiobj->transaction_id) . "'");
			if (empty($transaction))
			{
				/*insert query*/
				$trans['transactionid'] = $apiobj->transaction_id;
				$trans['paymentinfoid'] = $apiobj->paymentinfo['paymentinfoid'];
				$trans['state'] = $apiobj->type;
				$db->query_write(fetch_query_sql($trans, 'paymenttransaction'));
				if ($apiobj->type == 1)
				{
					$subobj->build_user_subscription($apiobj->paymentinfo['subscriptionid'], $apiobj->paymentinfo['subscriptionsubid'], $apiobj->paymentinfo['userid']);
					if ($apiobj->display_feedback)
					{
						$vbulletin->url = $vbulletin->options['bburl'] . '/payments.php';
				
						eval(print_standard_redirect('payment_complete', true, true));
					}
				}
				else if ($apiobj->type == 2)
				{
					$subobj->delete_user_subscription($apiobj->paymentinfo['subscriptionid'], $apiobj->paymentinfo['userid']);
				}

			}
			if ($apiobj->type == 2)
			{
				$subobj->delete_user_subscription($apiobj->paymentinfo['subscriptionid'], $apiobj->paymentinfo['userid']);
			}
		}
		else
		{ // something went horribly wrong, get $apiobj->error
			if ($apiobj->display_feedback AND !empty($apiobj->error))
			{
				//die("<p>{$apiobj->error}</p>");
				// we dont load header / footer, so just show the LITE template
				define('VB_ERROR_LITE', true);
				standard_error($apiobj->error);
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14218 $
|| ####################################################################
\*======================================================================*/
?>