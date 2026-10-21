<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'subscriptions');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('subscription');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array('USERCP_SHELL','usercp_nav_folderbit');

// pre-cache templates used by specific actions
$actiontemplates = array(
	'none' => array(
		'subscription',
		'subscription_activebit',
		'subscription_availablebit'
	),
	'order' => array(
		'subscription_payment',
		'subscription_paymentbit'
	)
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_subscriptions.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if ($bbuserinfo['userid'] == 0)
{
	print_no_permission();
}

// start the navbar
$navbits = array("usercp.php?$session[sessionurl]" => $vbphrase['user_control_panel']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

// fetch all active subscriptions the user is subscribed too
$susers = $DB_site->query("
	SELECT *
	FROM " . TABLE_PREFIX . "subscriptionlog
	WHERE status = 1
	AND userid = $bbuserinfo[userid]
");
while ($suser = $DB_site->fetch_array($susers))
{
	$subscribed["$suser[subscriptionid]"] = $suser;
}

// cache all the subscriptions
cache_user_subscriptions();

if (empty($subscriptioncache) OR $vboptions['subscriptionmethods'] == 0)
{
	eval(print_standard_error('error_nosubscriptions'));
}

// #############################################################################

if ($_REQUEST['do'] == 'list')
{
	$lengths = array(
		'D' => $vbphrase['day'],
		'W' => $vbphrase['week'],
		'M' => $vbphrase['month'],
		'Y' => $vbphrase['year'],
		// plural stuff below
		'Ds' => $vbphrase['days'],
		'Ws' => $vbphrase['weeks'],
		'Ms' => $vbphrase['months'],
		'Ys' => $vbphrase['years']
	);

	$subscribedbits = '';
	$subscriptionbits = '';

	foreach ($subscriptioncache AS $subscription)
	{
		$show['will_extend'] = false;

		$subscriptionid = &$subscription['subscriptionid'];

		if (isset($subscribed["$subscription[subscriptionid]"]))
		{
			$joindate = vbdate($vboptions['dateformat'], $subscribed["$subscription[subscriptionid]"]['regdate'], false);
			$enddate = vbdate($vboptions['dateformat'], $subscribed["$subscription[subscriptionid]"]['expirydate'], false);

			$gotsubscriptions = true;
			eval('$subscribedbits .= "' . fetch_template('subscription_activebit') . '";');

			$show['will_extend'] = true;
		}

		if ($subscription['active'])
		{
			if (isset($subscribed["$subscription[subscriptionid]"]))
			{
				if (fetch_proper_expirydate($subscribed["$subscription[subscriptionid]"]['expirydate'], $subscription['length'], $subscription['units']) == -1)
				{
					continue;
				}
			}
			else
			{
				if (fetch_proper_expirydate(TIMENOW, $subscription['length'], $subscription['units']) == -1)
				{
					continue;
				}
			}

			$subscription['cost'] = unserialize($subscription['cost']);
			$string = '';
			foreach ($subscription['cost'] AS $currency => $value)
			{
				if ($value > 0)
				{
					$string .= "<option value=\"$currency\" >" . $_CURRENCYSYMBOLS["$currency"] . $value . "</option>\n";
				}
			}

			$subscription['cost'] = $string;
			if ($subscription['length'] == 1)
			{
				$subscription['units'] = $lengths[$subscription['units']];
			}
			else
			{
				$subscription['units'] = $lengths[$subscription['units'] . 's'];
			}

			eval('$subscriptionbits .= "' . fetch_template('subscription_availablebit') . '";');
		}
	}

	if ($subscribedbits == '')
	{
		$show['activesubscriptions'] = false;
	}
	else
	{
		$show['activesubscriptions'] = true;
	}

	if ($subscriptionbits == '')
	{
		$show['subscriptions'] = false;
	}
	else
	{
		$show['subscriptions'] = true;
	}

	$methods = convert_bits_to_array($vboptions['subscriptionmethods'], $_SUBSCRIPTIONS['methods']);
	$paymentlink = false;
	foreach ($methods AS $type => $active)
	{
		if ($active)
		{
			$paymentlink = true;
		}
	}

	$navbits[''] = $vbphrase['paid_subscriptions'];

	$templatename = 'subscription';
}

// #############################################################################

if ($_REQUEST['do'] == 'order')
{
	globalize($_REQUEST, array(
		'subscriptionids',
		'currency'
	));

	if (!is_array($subscriptionids))
	{
		$idname = $vbphrase['subscription'];
		eval(print_standard_error('error_invalidid'));
	}
	else
	{
		// get the subscription id...
		foreach (array_keys($subscriptionids) AS $subscriptionid)
		{
			break;
		}
	}
	$subscriptionid = intval($subscriptionid);

	// first check this is active if not die
	if (!$subscriptioncache["$subscriptionid"]['active'])
	{
		$idname = $vbphrase['subscription'];
		eval(print_standard_error('error_invalidid'));
	}

	$sub = $subscriptioncache["$subscriptionid"];
	$currency = $currency["$subscriptionid"];

	$costs = unserialize($sub['cost']);
	$subscription_title = $sub['title'];
	$subscription_cost = $_CURRENCYSYMBOLS["$currency"] . $costs["$currency"];
	$orderbits = '';

	$methods = convert_bits_to_array($vboptions['subscriptionmethods'], $_SUBSCRIPTIONS['methods']);

	// These phrases are constant since they are the name of a service
	$tmp = array(
		'paypal' => 'PayPal',
		'nochex' => 'NOCHEX',
		'worldpay' => 'WorldPay'
	);
	$vbphrase['authorize'] = 'Authorize.Net';

	$vbphrase += $tmp;

	foreach ($methods AS $type => $active)
	{
		if ($active)
		{
			$form = construct_subscription_currency($type, $currency, $costs["$currency"], $sub);
			if (!empty($form))
			{
				$typetext = $type . '_order_instructions';
				eval('$orderbits .= "' . fetch_template('subscription_paymentbit') . '";');
			}
		}
	}

	$navbits["subscriptions.php?$session[sessionurl]"] = $vbphrase['paid_subscriptions'];
	$navbits[''] = $vbphrase['select_payment_method'];

	$templatename = 'subscription_payment';
}

// #############################################################################

if ($templatename != '')
{

	// build the cp nav
	require_once('./includes/functions_user.php');
	construct_usercp_nav('paid_subscriptions');

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template($templatename) . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9300 $
|| ####################################################################
\*======================================================================*/
?>