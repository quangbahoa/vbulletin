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

error_reporting(E_ALL & ~E_NOTICE);

define('NO_REGISTER_GLOBALS', 1);
define('SESSION_BYPASS', 1);

$phrasegroups = array();
$specialtemplates = array();

chdir('./../');
require_once('./includes/init.php');
require_once('./includes/functions.php');
require_once('./includes/adminfunctions.php');
require_once('./includes/functions_subscriptions.php');

if (!($vboptions['subscriptionmethods'] & $_SUBSCRIPTIONS['methods']['paypal']))
{
	die("This subscription method is disabled");
}

if (empty($_POST))
{
	exit;
}

$query[] = 'cmd=_notify-validate';
foreach ($_POST as $key => $val)
{
	$query[] = $key . '=' . urlencode ($val);
}
$query = implode('&', $query);

$used_curl = false;

if (function_exists('curl_init') AND $ch = curl_init())
{
	curl_setopt($ch, CURLOPT_URL, 'http://www.paypal.com/cgi-bin/webscr');
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDSIZE, 0);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$result = curl_exec($ch);
	curl_close($ch);
	if ($result !== false)
	{
		$used_curl = true;
	}
}
if (!$used_curl)
{

	$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
	$header .= "Host: www.paypal.com\r\n";
	$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$header .= "Content-Length: " . strlen($query) . "\r\n\r\n";
	$fp = fsockopen('www.paypal.com', 80, $errno, $errstr, 30);
	socket_set_timeout($fp, 30);
	fwrite($fp, $header . $query);
	while (!feof($fp))
	{
		$result = fgets($fp, 1024);
		if (strcmp($result, 'VERIFIED') == 0)
		{
			break;
		}
	}
	fclose($fp);
}

if (($result == 'VERIFIED' OR strcmp($result, 'VERIFIED') == 0) AND !empty($_POST['item_number']) AND strtolower($_POST['business']) == strtolower($vboptions['ppemail']))
{

	$item_number = explode('_', $_POST['item_number']);
	$subscriptionid = intval($item_number[0]);

	if (empty($item_number[1]))
	{ // non vBulletin subscription
		exit;
	}

	$userid = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid = " . intval($item_number[1]));

	// lets check the values
	if ($subscriptionid AND $userid['userid'])
	{
		//its a paypal payment and we have some valid ids
		$sub = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = $subscriptionid");
		$cost = unserialize($sub['cost']);
		if ($_POST['tax'] > 0)
		{
			$_POST['mc_gross'] -= $_POST['tax'];
		}

		// Check if its a payment or if its a reversal
		if ($_POST['txn_type'] == 'web_accept' AND $_POST['payment_status'] == 'Completed')
		{
			if ($_POST['mc_gross'] == $cost[strtolower($_POST['mc_currency'])])
			{
				build_user_subscription($subscriptionid, $userid['userid']);
			}
		}
		else if ($_POST['payment_status'] == 'Reversed' OR $_POST['payment_status'] == 'Refunded')
		{
			delete_user_subscription($subscriptionid, $userid['userid']);
		}
	}

	// Paypal likes to get told its message has been received
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		header('Status: 200 OK');
	}
	else
	{
		header('HTTP/1.1 200 OK');
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12861 $
|| ####################################################################
\*======================================================================*/
?>