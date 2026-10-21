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

// authorize.net settings
$authorize_txnkey = '123456';
$authorize_md5hash = '';

// worldpay settings
$worldpay_password = '123456';

// ######################## Define payment bits ###################
$_SUBSCRIPTIONS['methods'] = array(
	'paypal'	=>	1,
	'nochex'	=>	2,
	'worldpay'	=>	4,
	'authorize'	=>	8
);

// ######################## Define supported curencies ###################
$_SUBSCRIPTIONS['curencies'] = array(
	'paypal'	=>	array('usd' => true, 'gbp' => true, 'eur' => true, 'aud' => true, 'cad' => true),
	'nochex'	=>	array('gbp' => true),
	'worldpay'	=>	array('usd' => true, 'gbp' => true, 'eur' => true),
	'authorize'	=>	array('usd' => true, 'gbp' => true, 'eur' => true)
);

// ######################## Define symbols ###################
$_CURRENCYSYMBOLS = array(
	'usd'	=>	'US$',
	'gbp'	=>	'&pound;',
	'eur'	=>	'&euro;',
	'aud'	=>	'AU$',
	'cad'   =>  'CA$'
);

// ###################### Start fetch_proper_expirydate ######################
function fetch_proper_expirydate($regdate, $length, $units)
{
	// conver the string to an integer by adding 0
	$length = $length + 0;
	$regdate = $regdate + 0;
	if (!is_int($regdate) OR !is_int($length) OR !is_string($units))
	{ // its not a valid date
		return false;
	}

	$units_full = array(
		'D' => 'day',
		'W' => 'week',
		'M' => 'month',
		'Y' => 'year'
	);
	// lets get a formatted string that strtotime will understand
	$formatted = date('d F Y H:i', $regdate);

	// now lets add the appropriate terms and return it
	return strtotime("$formatted + $length " . $units_full["$units"]);
}

// ###################### Start joinsubscription #######################
function build_user_subscription($subscriptionid, $userid, $regdate = 0, $expirydate = 0)
{
	// first two variables are pretty self explanitory
	// the 3rd is used to decide if the user is subscribing to the subscription for the first time or rejoining
	global $DB_site, $subscriptioncache, $forumcache, $_USEROPTIONS;
	// may need this for checking we're not modifying the user details of an admin
	global $undeletableusers;

	$subscriptionid = intval($subscriptionid);
	$userid = intval($userid);

	cache_user_subscriptions();
	$sub = $subscriptioncache[$subscriptionid];
	$user = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "user WHERE userid = $userid");
	$currentsubscription = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "subscriptionlog WHERE userid = $userid AND subscriptionid = $subscriptionid");

	// no value passed in for regdate and we have a currently active subscription
	if ($regdate <= 0 AND $currentsubscription['regdate'] AND $currentsubscription['status'])
	{
		$regdate = $currentsubscription['regdate'];
	}
	// no value passed and no active subscription
	else if ($regdate <= 0)
	{
		$regdate = TIMENOW;
	}

	if ($expirydate <= 0 AND $currentsubscription['expirydate'] AND $currentsubscription['status'])
	{
		$expirydate_basis = $currentsubscription['expirydate'];
	}
	else if ($expirydate <= 0 OR $expirydate <= $regdate)
	{
		$expirydate_basis = $regdate;
	}

	if ($expirydate_basis)
	{ // active subscription base the value on our current expirydate
		$expirydate = fetch_proper_expirydate($expirydate_basis, $sub['length'], $sub['units']);
	}

	if ($user['userid'] AND $sub['subscriptionid'])
	{

		//access masks
		$subscription_forums = unserialize($sub['forums']);

		if (is_array($subscription_forums))
		{
			// double check since we might not have fetched this -- this might not be necessary
			require_once('./includes/functions.php');
			$origsize = sizeof($subscription_forums);

			require_once('./includes/functions_databuild.php');
			cache_forums();
			$forumlist = "0";

			foreach ($subscription_forums AS $key => $val)
			{
				if (!empty($forumcache[$key]))
				{
					$forumlist .= ",$key";
					$forumsql[] = "($userid, $key, 1)";
				}
				else
				{ //oops! it seems that some of the subscribed forums have been deleted, lets unset it
					unset($subscription_forums[$key]);
				}
			}
			$DB_site->query("
				DELETE FROM " . TABLE_PREFIX . "access
				WHERE forumid IN ($forumlist) AND
					userid = $userid
			");

			if ($origsize != sizeof($subscription_forums))
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "subscription
					SET forums = '" . addslashes(serialize($subscription_forums)) . "'
					WHERE subscriptionid = $subscriptionid
				");
			}

			if (!empty($forumsql))
			{

				$forumsql = implode($forumsql, ', ');
				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "access
					(userid, forumid, accessmask)
					VALUES " .
					$forumsql
				);
			}
			$masksql = ", options = (options | $_USEROPTIONS[hasaccessmask]) ";
		}

		$usertitlesql = '';
		$usergroupsql = '';
		$noalter = explode(',', $undeletableusers);
		if (empty($noalter[0]) OR !in_array($userid, $noalter))
		{
			//membergroupids and usergroupid
			if (!empty($sub['membergroupids']))
			{
				$membergroupids = array_diff(fetch_membergroupids_array($sub, false), fetch_membergroupids_array($user, false));
			}
			else
			{
				$membergroupids = array();
			}
			if (!empty($user['membergroupids']))
			{
				$membergroupids = array_merge($user['membergroupids'], $membergroupids);
			}
	
			if ($sub['nusergroupid'] > 0)
			{
				$usergroupsql = ", usergroupid = $sub[nusergroupid], displaygroupid = 0";
	
				if ($user['customtitle'] == 0)
				{
					$usergroup = $DB_site->query_first("
						SELECT usertitle
						FROM " . TABLE_PREFIX . "usergroup
						WHERE usergroupid = $sub[nusergroupid]
					");
					if (!empty($usergroup['usertitle']))
					{
						$usertitlesql = ", usertitle = '" . addslashes($usergroup['usertitle']) . "'";
					}
				}
			}
		}

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET membergroupids = '" . implode($membergroupids, ',') . "'
			$usergroupsql
			$masksql
			$usertitlesql
			WHERE userid = $userid
		");

		if (!$currentsubscription['subscriptionlogid'])
		{
			$DB_site->query("
				INSERT INTO " . TABLE_PREFIX . "subscriptionlog
				(subscriptionid, userid, pusergroupid, status, regdate, expirydate)
				VALUES
				($subscriptionid, $userid, $user[usergroupid], 1, $regdate, $expirydate)
			");
		}
		else
		{
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "subscriptionlog
				SET status = 1,
				" . iif(!$currentsubscription['status'], "pusergroupid = $user[usergroupid],") . "
				regdate = $regdate,
				expirydate = $expirydate
				WHERE userid = $userid AND
					subscriptionid = $subscriptionid
			");
		}
	}
}

// ###################### Start leavesubscription #######################
function delete_user_subscription($subscriptionid, $userid)
{
	global $DB_site, $subscriptioncache, $_USEROPTIONS;

	$subscriptionid = intval($subscriptionid);
	$userid = intval($userid);

	cache_user_subscriptions();
	$sub = $subscriptioncache[$subscriptionid];
	$user = $DB_site->query_first("
		SELECT user.*, subscriptionlog.pusergroupid
		FROM " . TABLE_PREFIX . "user AS user,
		" . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
		WHERE user.userid = $userid AND
			subscriptionlog.userid = $userid AND
			subscriptionlog.subscriptionid = $subscriptionid
	");

	if ($user['userid'] AND $sub['subscriptionid'])
	{

		//access masks
		$subscription_forums = unserialize($sub['forums']);
		if (is_array($subscription_forums))
		{
			$forumlist = "0";
			foreach ($subscription_forums AS $key => $val)
			{
				$forumlist .= ",$key";
			}
			$DB_site->query("
				DELETE FROM " . TABLE_PREFIX . "access
				WHERE forumid IN ($forumlist) AND
					userid = $userid
			");
		}
		$countaccess = $DB_site->query_first("
			SELECT COUNT(*) AS masks
			FROM " . TABLE_PREFIX . "access
			WHERE userid = $userid
		");

		$membergroupids = array_diff(fetch_membergroupids_array($user, false), fetch_membergroupids_array($sub, false));
		if($sub['nusergroupid'] == $user['usergroupid'] AND $user['usergroupid'] != $user['pusergroupid'])
		{
			$usergroupsql = ", usergroupid=$user[pusergroupid]";
		}
		$groups = iif(!empty($sub['membergroupids']), $sub['membergroupids'] . ',') . $sub['nusergroupid'];

		if (in_array ($user['displaygroupid'], explode(',', $groups)))
		{ // there displaying as one of the usergroups in the subscription
			$user['displaygroupid'] = 0;
		}

		// does their old groups still allow custom titles?
		$reset_title = false;
		if ($user['customtitle'] == 1)
		{
			$groups = iif(!empty($user['membergroupids']), $user['membergroupids'] . ',') . $user['pusergroupid'];
			$usergroup = $DB_site->query_first("
				SELECT usergroupid
				FROM " . TABLE_PREFIX . "usergroup
				WHERE (genericpermissions & " . CANUSECUSTOMTITLE . ")
					AND usergroupid IN ($groups)
			");

			if (empty($usergroup['usergroupid']))
			{
				// no custom group any more lets set it back to the default
				$reset_title = true;
			}
		}

		if (($sub['nusergroupid'] > 0 AND $user['customtitle'] == 0) OR $reset_title)
		{ // they need a default title
			$usergroup = $DB_site->query_first("
				SELECT usertitle
				FROM " . TABLE_PREFIX . "usergroup
				WHERE usergroupid = $user[pusergroupid]
			");
			if (empty($usergroup['usertitle']))
			{ // should be a title based on minposts it seems then
				$usergroup = $DB_site->query_first("
					SELECT title AS usertitle
					FROM " . TABLE_PREFIX . "usertitle
					WHERE minposts <= $user[posts]
					ORDER BY minposts DESC
				");
			}
			$usertitlesql = ", customtitle = 0, usertitle = '" . addslashes($usergroup['usertitle']) . "'";
		}

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET displaygroupid = $user[displaygroupid],
			membergroupids = '" . implode($membergroupids, ',') . "',
			options = (options" . iif($countaccess['masks'], ' | ' , ' & ~' ) . "$_USEROPTIONS[hasaccessmask])
			$usergroupsql
			$usertitlesql
			WHERE userid = $userid
		");

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "subscriptionlog
			SET status = 0
			WHERE subscriptionid = $subscriptionid AND
			userid = $userid
		");

		$mysubs = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "subscriptionlog WHERE status = 1 AND userid = $userid");
        while ($mysub = $DB_site->fetch_array($mysubs))
        {
            build_user_subscription($mysub['subscriptionid'], $userid, $mysub['regdate'], $mysub['expirydate']);
        }
	}
}

// ###################### Start getsubscriptionscache #######################
function cache_user_subscriptions()
{
	global $DB_site, $subscriptioncache;

	if (!is_array($subscriptioncache))
	{
		$subscriptioncache = array();
		$subscriptions = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "subscription");
		while ($subscription = $DB_site->fetch_array($subscriptions))
		{
			$subscriptioncache["$subscription[subscriptionid]"] = $subscription;
		}
		$DB_site->free_result($subscriptions);
	}
}

function construct_payment($method, $cost, $currency, $subscriptionid, $title, $userinfo)
{
	global $vboptions;

	$item = $subscriptionid . '_' . $userinfo['userid'];
	$currency = strtoupper($currency);

	switch ($method)
	{
		case 'paypal':
		$form['action'] = 'https://www.paypal.com/cgi-bin/webscr';
		$form['method'] = 'post';
		$form['hiddenfields'] = "
			<input type=\"hidden\" name=\"cmd\" value=\"_xclick\" />
			<input type=\"hidden\" name=\"business\" value=\"$vboptions[ppemail]\" />
			<input type=\"hidden\" name=\"item_name\" value=\"$title Subscription\" />
			<input type=\"hidden\" name=\"item_number\" value=\"$item\" />
			<input type=\"hidden\" name=\"amount\" value=\"$cost\" />
			<input type=\"hidden\" name=\"currency_code\" value=\"$currency\" />
			<input type=\"hidden\" name=\"no_shipping\" value=\"1\" />
			<input type=\"hidden\" name=\"shipping\" value=\"0.00\" />
			<input type=\"hidden\" name=\"return\" value=\"$vboptions[bburl]/$vboptions[forumhome].php\" />
			<input type=\"hidden\" name=\"notify_url\" value=\"$vboptions[bburl]/subscriptions/paypal.php\" />
			<input type=\"hidden\" name=\"custom\" value=\"$userinfo[username]\" />
			<input type=\"hidden\" name=\"no_note\" value=\"1\" />";
			break;
		case 'nochex':
		$form['action'] = 'https://www.nochex.com/nochex.dll/checkout';
		$form['method'] = 'post';
		$form['hiddenfields'] = "
			<input type=\"hidden\" name=\"email\" value=\"$vboptions[ncxemail]\" />
			<input type=\"hidden\" name=\"amount\" value=\"$cost\" />
			<input type=\"hidden\" name=\"ordernumber\" value=\"$item\" />
			<input type=\"hidden\" name=\"returnurl\" value=\"$vboptions[bburl]\" />";
			break;
		case 'authorize':
			$form = construct_authorize_form($cost, $item, $currency);
			break;
		case 'worldpay':
		$form['action'] = 'https://select.worldpay.com/wcc/purchase';
		$form['method'] = 'post';
		$form['hiddenfields'] = "
			<input type=\"hidden\" name=\"desc\" value=\"$item\" />
			<input type=\"hidden\" name=\"cost\" value=\"$cost\" />
			<input type=\"hidden\" name=\"currency\" value=\"$currency\" />
			<input type=\"hidden\" name=\"instId\" value=\"$vboptions[worldpay_instid]\">
			<input type=\"hidden\" name=\"cartId\" value=\"$title Subscription\">";
			break;
	}
	return $form;
}

function construct_subscription_currency($method, $currency, $cost, $sub)
{
	global $_SUBSCRIPTIONS, $_CURRENCYSYMBOLS, $bbuserinfo;
	$currencies = $_SUBSCRIPTIONS['curencies']["$method"];

	$output = '';

	if ($cost > 0 AND $currencies["$currency"])
	{ // this payment method supports a currency we've inputed
		$output = construct_payment($method, $cost, $currency, $sub['subscriptionid'], $sub['title'], $bbuserinfo);
	}
	return $output;
}

// ###################### Start hmac #######################
function hmac($key, $data)
{
	$b = 64;
	if (strlen($key) > $b)
	{
		$key = pack("H*", md5($key));
	}
	$key  = str_pad($key, $b, chr(0x00));
	$ipad = str_pad('', $b, chr(0x36));
	$opad = str_pad('', $b, chr(0x5c));
	$k_ipad = $key ^ $ipad ;
	$k_opad = $key ^ $opad;

	return md5($k_opad  . pack("H*", md5($k_ipad . $data)));
}

function construct_authorize_form($amount, $id, $currency = 'USD')
{
	global $vboptions, $authorize_txnkey;

	$sequence = vbrand(1, 1000);
	$fingerprint = hmac($authorize_txnkey, $vboptions['authorize_loginid'] . '^' . $sequence . '^' . TIMENOW . '^' . $amount . '^' . $currency);
	$timenow = TIMENOW;

	$id .= "_$currency";

	$form['action'] = 'https://secure.authorize.net/gateway/transact.dll';
	$form['method'] = 'post';
	$form['hiddenfields'] = <<< HTML
	<input type="hidden" name="x_fp_sequence" value="$sequence" />
	<input type="hidden" name="x_fp_timestamp" value="$timenow" />
	<input type="hidden" name="x_fp_hash" value="$fingerprint" />
	<input type="hidden" name="x_login" value="$vboptions[authorize_loginid]" />

	<input type="hidden" name="x_show_form" value="PAYMENT_FORM" />
	<input type="hidden" name="x_amount" value="$amount" />
	<input type="hidden" name="x_currency_code" value="$currency" />

	<input type="hidden" name="x_invoice_num" value="$id" />
	<input type="hidden" name="x_description" value="Subscription" />
	<input type="hidden" name="x_relay_response" value="TRUE" />
	<input type="hidden" name="x_relay_url" value="$vboptions[bburl]/subscriptions/authorize.php" />
HTML;
	return $form;
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13186 $
|| ####################################################################
\*======================================================================*/
?>