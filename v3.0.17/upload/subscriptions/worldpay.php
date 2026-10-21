<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # All PHP code in this file is 2000-2026 Jelsoft Enterprises Ltd. # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

define('NO_REGISTER_GLOBALS', 1);
define('SESSION_BYPASS', 1);

$phrasegroups = array();
$specialtemplates = array();

chdir('./../');
require_once('./includes/init.php');
require_once('./includes/adminfunctions.php');
require_once('./includes/functions_subscriptions.php');

if (!($vboptions['subscriptionmethods'] & $_SUBSCRIPTIONS['methods']['worldpay']))
{
	die("This subscription method is disabled");
}

if ($_REQUEST['callbackPW'] == $worldpay_password)
{

	$item_number = explode('_', $_REQUEST['desc']);
	$subscriptionid = intval($item_number[0]);

	if (empty($item_number[1]))
	{ // non vBulletin subscription
		exit;
	}

	$userid = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid = " . intval($item_number[1]));
	if ($subscriptionid AND $userid['userid'])
	{
		$sub = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = $subscriptionid");
		if ($_REQUEST['transStatus'] == 'Y' AND ($_REQUEST['authMode'] == 'A' OR $_REQUEST['authMode'] == 'O'))
		{
			$cost = unserialize($sub['cost']);
			if ($_REQUEST['cost'] == $cost[strtolower($_REQUEST['currency'])])
			{
				build_user_subscription($subscriptionid, $userid['userid']);
			}
		}
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12861 $
|| ####################################################################
\*======================================================================*/
?>