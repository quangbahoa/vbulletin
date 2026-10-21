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

// ###################### Start replacesession #######################
function fetch_replaced_session_url($url)
{
	// replace the sessionhash in $url with the current one
	global $session;

	$url = addslashes($url);
	$url = fetch_removed_sessionhash($url);

	if ($session['sessionurl'] != '')
	{
		if (strpos($url, '?') !== false)
		{
			$url .= "&amp;$session[sessionurl]";
		}
		else
		{
			$url .= "?$session[sessionurl]";
		}
	}

	return $url;
}

// ###################### Start removesessionhash #######################
function fetch_removed_sessionhash($string)
{
	return preg_replace('/(?<=[^a-z0-9])(s|sessionhash)=[a-z0-9]{32}(&|&amp;)?/', '', $string);
}

// ###################### Start verify_strike_status #######################
function verify_strike_status($username = '', $supress_error = false)
{
	global $DB_site, $vboptions;

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "strikes WHERE striketime < " . (TIMENOW - 3600));

	if (!$vboptions['usestrikesystem'])
	{
		return 0;
	}

	$strikes = $DB_site->query_first("
		SELECT COUNT(*) AS strikes, MAX(striketime) AS lasttime
		FROM " . TABLE_PREFIX . "strikes
		WHERE strikeip = '" . addslashes(IPADDRESS) . "'
	");
	if (!empty($username))
	{
		$strikes_user = $DB_site->query_first("
			SELECT COUNT(*) AS strikes
			FROM " . TABLE_PREFIX . "strikes
			WHERE strikeip = '" . addslashes(IPADDRESS) . "'
				AND username = '" . addslashes(htmlspecialchars_uni($username)) . "'
		");
	}
	if ($strikes['strikes'] == 0)
	{
		$strikes_user['strikes'] = 1;
	}
	if ($strikes['strikes'] >= 5 AND $strikes['lasttime'] > TIMENOW - 900)
	{ //they've got it wrong 5 times or greater for any username at the moment

		if (($strikes_user['strikes'] % 5 == 0) AND $user = $DB_site->query_first("SELECT userid, username, email, languageid FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes($username) . "' AND usergroupid <> 3"))
		{ // they've got it wrong 5 times for this user lets email them
			$ip = IPADDRESS;
			eval(fetch_email_phrases('accountlocked', $user['languageid']));
			vbmail($user['email'], $subject, $message, true);
		}

		// the user is still not giving up so lets keep increasing this marker
		exec_strike_user($username);

		if ($strikes['strikes'] > 5)
		{ // a bit sneaky but at least it makes the error message look right
			$strikes['strikes'] = 5;
		}
		if (!$supress_error)
		{
			eval(print_standard_error('error_strikes'));
		}
		else
		{
			return false;
		}
	}
	return $strikes['strikes'];
}

// ###################### Start exec_strike_user #######################
function exec_strike_user($username = '', $strikes = 0)
{
	global $DB_site, $strikes, $vboptions;

	if (!$vboptions['usestrikesystem'])
	{
		return 0;
	}

	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "strikes
		(striketime, strikeip, username)
		VALUES
		(" . TIMENOW . ", '" . addslashes(IPADDRESS) . "', '" . addslashes(htmlspecialchars_uni($username)) . "')
	");
	$strikes++;
}

// ###################### Start exec_unstrike_user #######################
function exec_unstrike_user($username)
{
	global $DB_site;

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "strikes WHERE strikeip = '" . addslashes(IPADDRESS) . "' AND username='" . addslashes(htmlspecialchars_uni($username)) . "'");
}

// ###################### Start verify_authentication #######################
function verify_authentication($username, $password, $md5password, $md5password_utf, $send_cookies)
{
	global $DB_site, $bbuserinfo, $_REQUEST, $_COOKIE;

	$username = strip_blank_ascii($username, ' ');
	if ($bbuserinfo = $DB_site->query_first("SELECT userid, usergroupid, membergroupids, username, password, salt FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes(htmlspecialchars_uni($username)) . "'"))
	{
		if (
			$bbuserinfo['password'] != iif($password AND !$md5password, md5(md5($password) . $bbuserinfo['salt']), '') AND
			$bbuserinfo['password'] != md5($md5password . $bbuserinfo['salt']) AND
			$bbuserinfo['password'] != iif($md5password_utf, md5($md5password_utf . $bbuserinfo['salt']), '')
		)
		{
			return false;
		}
		if ($send_cookies)
		{
			if ($_REQUEST['cookieuser'])
			{
				vbsetcookie('userid', $bbuserinfo['userid'], true, true);
				vbsetcookie('password', md5($bbuserinfo['password'] . 'VBS4AAFB47'), true, true);
			}
			else if ($_COOKIE[COOKIE_PREFIX . 'userid'] AND $_COOKIE[COOKIE_PREFIX . 'userid'] != $bbuserinfo['userid'])
			{
				// we have a cookie from a user and we're logging in as
				// a different user and we're not going to store a new cookie,
				// so let's unset the old one
				vbsetcookie('userid', '', true, true);
				vbsetcookie('password', '', true, true);
			}
		}
		return true;
	}
	return false;
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15482 $
|| ####################################################################
\*======================================================================*/
?>