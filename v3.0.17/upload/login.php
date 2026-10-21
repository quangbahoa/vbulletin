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
define('THIS_SCRIPT', 'login');
//define('SESSION_BYPASS', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'lostpw' => array(
		'lostpw'
	)
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_login.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_REQUEST['do']) AND empty($_REQUEST['a']))
{
	exec_header_redirect("$vboptions[forumhome].php");
}

// ############################### start logout ###############################
if ($_REQUEST['do'] == 'logout')
{

	globalize($_REQUEST, array('url' => STR, 'userid' => INT));

	if ($bbuserinfo['userid'] != 0 AND $userid != $bbuserinfo['userid'])
	{
		eval(print_standard_error('logout_missing_userid'));
	}

	// clear all cookies beginning with COOKIE_PREFIX
	$prefix_length = strlen(COOKIE_PREFIX);
	foreach ($_COOKIE AS $key => $val)
	{
		$index = strpos($key, COOKIE_PREFIX);
		if ($index == 0 AND $index !== false)
		{
			$key = substr($key, $prefix_length);
			if (trim($key) == '')
			{
				continue;
			}
			vbsetcookie($key, '', 1);
		}
	}

	if ($bbuserinfo['userid'] != 0 AND $bbuserinfo['userid'] != -1)
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET lastactivity = " . (TIMENOW - $vboptions['cookietimeout']) . ",
				lastvisit = " . TIMENOW . "
			WHERE userid = $bbuserinfo[userid]
		");

		// make sure any other of this user's sessions are deleted (in case they ended up with more than one)
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "session WHERE userid = $bbuserinfo[userid]");
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "session WHERE sessionhash = '" . addslashes($session['dbsessionhash']) . "'");

	$session['sessionhash'] = fetch_sessionhash();
	$session['dbsessionhash'] = $session['sessionhash'];
	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "session
			(sessionhash, userid, host, idhash, lastactivity, styleid, useragent)
		VALUES
			('" . addslashes($session['sessionhash']) . "', 0, '" . addslashes($session['host']) . "', '" . addslashes($session['idhash']) . "', " . TIMENOW . ", 0, '" . addslashes(USER_AGENT) . "')
	");

	vbsetcookie('sessionhash', $session['sessionhash'], false, true);

	if ($nosessionhash == 1)
	{ // if user is working through cookies, blank out the sessionhash
		$shash = $session['sessionhash'] = '';
		$surl = $session['sessionurl'] = '';
		$surlJS = $session['sessionurl_js'] = '';
	}
	else
	{
		$shash = $session['sessionhash'];
		$surl = $session['sessionurl'] = 's=' . $session['sessionhash'] . '&amp;';
		$surlJS = $session['sessionurl_js'] = 's=' . $session['sessionhash'] . '&';
	}

	$url = fetch_replaced_session_url($url);
	if (strpos($url, 'do=logout') !== false)
	{
		$url = "$vboptions[forumhome].php?$surl";
	}
	eval(print_standard_error('error_cookieclear'));

}

// ############################### start do login ###############################
if ($_REQUEST['do'] == 'login')
{
	globalize($_REQUEST, array(
		'vb_login_username' => STR,
		'vb_login_password' => STR,
		'vb_login_md5password' => STR,
		'vb_login_md5password_utf' => STR,
		'url' => STR,
		'postvars'
	));
	globalize($_POST, array(
		'logintype' => STR,
	));

	// can the user login?

 	$username = &$vb_login_username;
	$password = &$vb_login_password;
	$md5password = &$vb_login_md5password;
	$md5password_utf = &$vb_login_md5password_utf;

	$strikes = verify_strike_status($username);

	if ($username == '')
	{
		eval(print_standard_error('error_badlogin'));
	}

	if (!verify_authentication($username, $password, $md5password, $md5password_utf, true))
	{
		// check password
		exec_strike_user($bbuserinfo['username']);

		if ($logintype === 'cplogin' OR $logintype === 'modcplogin')
		{
			// log this error if attempting to access the control panel
			require_once('./includes/functions_log_error.php');
			log_vbulletin_error($username, 'security');
		}
		$bbuserinfo = array(
			'userid' => 0,
			'usergroupid' => 1
		);
		eval(print_standard_error('error_badlogin'));
	}

	exec_unstrike_user($username);

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "session WHERE sessionhash = '" . addslashes($session['dbsessionhash']) . "'");

	$session['sessionhash'] = fetch_sessionhash();
	$session['dbsessionhash'] = $session['sessionhash'];
	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "session
			(sessionhash, userid, host, idhash, lastactivity, styleid, loggedin, bypass, useragent)
		VALUES
			('" . addslashes($session['sessionhash']) . "', " . intval($bbuserinfo['userid']) . ", '" . addslashes(SESSION_HOST) . "', '" . addslashes(SESSION_IDHASH) . "', " . TIMENOW . ", $session[styleid], 1, " . iif ($logintype === 'cplogin', 1, 0) . ", '" . addslashes(USER_AGENT) . "')
	");
	vbsetcookie('sessionhash', $session['sessionhash'], false, true);

	if ($nosessionhash == 1)
	{ // if user is working through cookies, blank out the sessionhash
		$shash = $session['sessionhash'] = '';
		$surl = $session['sessionurl'] = '';
		$surlJS = $session['sessionurl_js'] = '';
	}
	else
	{
		$shash = $session['sessionhash'];
		$surl = $session['sessionurl'] = 's=' . $session['sessionhash'] . '&amp;';
		$surlJS = $session['sessionurl_js'] = 's=' . $session['sessionhash'] . '&';
	}

	// admin control panel or upgrade script login
	if ($logintype === 'cplogin')
	{
		$permissions = cache_permissions($bbuserinfo, false);
		$bbuserinfo['permissions'] = &$permissions;
		if ($permissions['adminpermissions'] & CANCONTROLPANEL)
		{
			// update CSS preferences if administrator wants to
			globalize($_POST, array('cssprefs' => STR));
			if ($cssprefs != '')
			{
				$cssprefs = str_replace(array('..', '/', '\\'), '', $cssprefs); // get rid of harmful characters
				if ($cssprefs != '' AND @file_exists("./cpstyles/$cssprefs/controlpanel.css"))
				{
					$DB_site->query("UPDATE " . TABLE_PREFIX . "administrator SET cssprefs = '" . addslashes($cssprefs) . "' WHERE userid = $bbuserinfo[userid]");
				}
			}

			$cpsession = fetch_sessionhash();
			$DB_site->query("INSERT INTO " . TABLE_PREFIX . "cpsession (userid, hash, dateline) VALUES ($bbuserinfo[userid], '" . addslashes($cpsession) . "', " . TIMENOW . ")");
			vbsetcookie('cpsession', $cpsession, false, true);

			if (!$_REQUEST['cookieuser'] AND empty($_COOKIE[COOKIE_PREFIX . 'userid']))
			{
				vbsetcookie('userid', $bbuserinfo['userid'], false, true);
				vbsetcookie('password', md5($bbuserinfo['password'] . 'VBS4AAFB47'), false, true);
			}
		}
	}

	// moderator control panel login
	if ($logintype === 'modcplogin')
	{
		$permissions = cache_permissions($bbuserinfo, false);
		$bbuserinfo['permissions'] = &$permissions;

		include_once('./includes/functions_calendar.php');
		if (can_moderate() OR can_moderate_calendar())
		{
			$cpsession = fetch_sessionhash();
			$DB_site->query("INSERT INTO " . TABLE_PREFIX . "cpsession (userid, hash, dateline) VALUES ($bbuserinfo[userid], '" . addslashes($cpsession) . "', " . TIMENOW . ")");
			vbsetcookie('cpsession', $cpsession, false, true);

			if (!$_REQUEST['cookieuser'] AND empty($_COOKIE[COOKIE_PREFIX . 'userid']))
			{
				vbsetcookie('userid', $bbuserinfo['userid'], false, true);
				vbsetcookie('password', md5($bbuserinfo['password'] . 'VBS4AAFB47'), false, true);
			}
		}
	}

	if ($url == 'login.php' OR $url == "$vboptions[forumhome].php" OR strpos($url, 'do=logout') !== false)
	{
		$url = "$vboptions[forumhome].php?$surl";
	}
	else
	{
		$url = fetch_replaced_session_url($url);
		$url = preg_replace('#^/+#', '/', $url); // bug 3654 don't ask why
	}

	$postvars = construct_hidden_var_fields($postvars);

	$temp = strpos($url, '?');
	if ($temp)
	{
		$formfile = substr($url, 0, $temp);
	}
	else
	{
		$formfile = $url;
	}

	eval(print_standard_redirect('redirect_login'));
}

// ############################### start lost password ###############################
if ($_REQUEST['do'] == 'lostpw')
{
	$navbits = construct_navbits(array('' => $vbphrase['lost_password_recovery_form']));

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template('lostpw') . '");');
}

// ############################### start email password ###############################
if ($_POST['do'] == 'emailpassword')
{

	globalize($_POST, array('email' => STR, 'url' => STR));

	if ($email == '')
	{
		eval(print_standard_error('error_invalidemail'));
	}

	require_once('./includes/functions_user.php');

	$users = $DB_site->query("SELECT userid, username, email, languageid
		FROM " . TABLE_PREFIX . "user
		WHERE email = '" . addslashes(htmlspecialchars_uni($email)) . "'
	");
	if ($DB_site->num_rows($users))
	{
		while ($user = $DB_site->fetch_array($users))
		{
			$user['username'] = unhtmlspecialchars($user['username']);

			$user['activationid'] = build_user_activation_id($user['userid'], 2, 1);

			eval(fetch_email_phrases('lostpw', $user['languageid']));
			vbmail($user['email'], $subject, $message, true);
		}

		$_REQUEST['forceredirect'] = 1;
		$url = str_replace('"', '', $url);
		eval(print_standard_redirect('redirect_lostpw'));
	}
	else
	{
		eval(print_standard_error('error_invalidemail'));
	}
}

// ############################### start reset password ###############################
if ($_REQUEST['a'] == 'pwd' OR $_REQUEST['do'] == 'resetpassword')
{

	globalize($_REQUEST, array('userid' => INT, 'u' => INT, 'activationid' => INT, 'i' => INT));

	if (!$userid)
	{
		$userid = $u;
	}

	if (!$activationid)
	{
		$activationid = $i;
	}

	$userinfo = verify_id('user', $userid, 1, 1);

	$user = $DB_site->query_first("
		SELECT activationid, dateline
		FROM " . TABLE_PREFIX . "useractivation
		WHERE type = 1
			AND userid = $userinfo[userid]
	");

	if ($user['dateline'] < (TIMENOW - 24 * 60 * 60))
	{  // is it older than 24 hours?
		eval(print_standard_error('error_resetexpired'));
	}

	if ($user['activationid'] != $activationid)
	{ //wrong act id
		eval(print_standard_error('error_resetbadid'));
	}

	// delete old activation id
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "useractivation WHERE userid = $userinfo[userid] AND type = 1");

	// make random number
	$newpassword = vbrand(0, 100000000);

	$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET password = '" . addslashes(md5(md5($newpassword) . $userinfo['salt'])) . "', passworddate = NOW() WHERE userid = $userinfo[userid]");

	eval(fetch_email_phrases('resetpw', $userinfo['languageid']));
	vbmail($userinfo['email'], $subject, $message, true);

	eval(print_standard_error('error_resetpw'));

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15482 $
|| ####################################################################
\*======================================================================*/
?>