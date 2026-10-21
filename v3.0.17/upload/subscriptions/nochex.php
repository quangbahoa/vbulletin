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
define('DEMO_SITE', false);

chdir('./../');
require_once('./includes/init.php');
require_once('./includes/adminfunctions.php');
require_once('./includes/functions_subscriptions.php');

if (!($vboptions['subscriptionmethods'] & $_SUBSCRIPTIONS['methods']['nochex']))
{
	die("This subscription method is disabled");
}

foreach ($_POST as $key => $val)
{
	$query[] = $key . '=' . urlencode ($val);
}
if (!is_array($query))
{
	exit;
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

if (($result == 'AUTHORISED' OR strcmp($result, 'AUTHORISED') == 0) AND !empty($_POST['order_id']))
{

	$item_number = explode('_', $_POST['order_id']);
	$subscriptionid = intval($item_number[0]);
	if (empty($item_number[1]))
	{ // non vBulletin subscription
		exit;
	}
	$userid = $DB_site->query_first('SELECT userid FROM ' . TABLE_PREFIX . 'user WHERE userid = ' . intval($item_number['1']));

	if ($subscriptionid AND $userid['userid'])
	{
		//its a payment and we have some valid ids
		$sub = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid=$subscriptionid");
		$cost = unserialize($sub['cost']);

		if ($_POST['amount'] == $cost['gbp'])
		{
			build_user_subscription($subscriptionid, $userid['userid']);
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