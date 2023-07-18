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

// ###################### Start replacesession #######################
function fetch_replaced_session_url($url)
{
	// replace the sessionhash in $url with the current one
	global $vbulletin;

	$url = addslashes($url);
	$url = fetch_removed_sessionhash($url);

	if ($vbulletin->session->vars['sessionurl'] != '')
	{
		if (strpos($url, '?') !== false)
		{
			$url .= '&amp;' . $vbulletin->session->vars['sessionurl'];
		}
		else
		{
			$url .= '?' . $vbulletin->session->vars['sessionurl'];
		}
	}

	return $url;
}

// ###################### Start removesessionhash #######################
function fetch_removed_sessionhash($string)
{
	return preg_replace('/([^a-z0-9])(s|sessionhash)=[a-z0-9]{32}(&amp;|&)?/', '\\1', $string);
}

// ###################### Start verify_strike_status #######################
function verify_strike_status($username = '', $supress_error = false)
{
	global $vbulletin;

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "strikes WHERE striketime < " . (TIMENOW - 3600));

	if (!$vbulletin->options['usestrikesystem'])
	{
		return 0;
	}

	$strikes = $vbulletin->db->query_first("
		SELECT COUNT(*) AS strikes, MAX(striketime) AS lasttime
		FROM " . TABLE_PREFIX . "strikes
		WHERE strikeip = '" . $vbulletin->db->escape_string(IPADDRESS) . "'
	");

	if ($strikes['strikes'] >= 5 AND $strikes['lasttime'] > TIMENOW - 900)
	{ //they've got it wrong 5 times or greater for any username at the moment

		($hook = vBulletinHook::fetch_hook('login_strikes_user')) ? eval($hook) : false;

		// the user is still not giving up so lets keep increasing this marker
		exec_strike_user($username);

		if (!$supress_error)
		{
			eval(standard_error(fetch_error('strikes', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'])));
		}
		else
		{
			return false;
		}
	}
	else if ($strikes['strikes'] > 5)
	{ // a bit sneaky but at least it makes the error message look right
		$strikes['strikes'] = 5;
	}

	return $strikes['strikes'];
}

// ###################### Start exec_strike_user #######################
function exec_strike_user($username = '')
{
	global $vbulletin, $strikes;

	if (!$vbulletin->options['usestrikesystem'])
	{
		return 0;
	}

	if (!empty($username))
	{
		$strikes_user = $vbulletin->db->query_first("
			SELECT COUNT(*) AS strikes
			FROM " . TABLE_PREFIX . "strikes
			WHERE strikeip = '" . $vbulletin->db->escape_string(IPADDRESS) . "'
				AND username = '" . $vbulletin->db->escape_string(htmlspecialchars_uni($username)) . "'
		");

		if ($strikes_user['strikes'] == 4)		// We're about to add the 5th Strike for a user
		{
			if ($user = $vbulletin->db->query_first("SELECT userid, username, email, languageid FROM " . TABLE_PREFIX . "user WHERE username = '" . $vbulletin->db->escape_string($username) . "' AND usergroupid <> 3"))
			{
				$ip = IPADDRESS;
				eval(fetch_email_phrases('accountlocked', $user['languageid']));
				vbmail($user['email'], $subject, $message, true);
			}
		}
	}

	/*insert query*/
	$vbulletin->db->query_write("
		INSERT INTO " . TABLE_PREFIX . "strikes
		(striketime, strikeip, username)
		VALUES
		(" . TIMENOW . ", '" . $vbulletin->db->escape_string(IPADDRESS) . "', '" . $vbulletin->db->escape_string(htmlspecialchars_uni($username)) . "')
	");
	$strikes++;

	($hook = vBulletinHook::fetch_hook('login_strikes')) ? eval($hook) : false;
}

// ###################### Start exec_unstrike_user #######################
function exec_unstrike_user($username)
{
	global $vbulletin;

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "strikes WHERE strikeip = '" . $vbulletin->db->escape_string(IPADDRESS) . "' AND username='" . $vbulletin->db->escape_string(htmlspecialchars_uni($username)) . "'");
}

// ###################### Start verify_authentication #######################
function verify_authentication($username, $password, $md5password, $md5password_utf, $cookieuser, $send_cookies)
{
	global $vbulletin;

	$username = strip_blank_ascii($username, ' ');
	// See VBM-635: &#xxx; should be converted to windows-1252 extended char. This may not happen if a browser submits the form. But from API or user manually input, it does.
	// See also vB_DataManager_User::verify_username()

	global $stylevar;
	$charset = $stylevar['charset'] ? $stylevar['charset'] : 'ISO-8859-1';
	$callback = new Convert_unicode_char_to_charset_callback($charset);

	$username = preg_replace_callback(
		'/&#([0-9]+);/i',
		array($callback, 'callback'),
		$username
	);

	if ($vbulletin->userinfo = $vbulletin->db->query_first("SELECT userid, usergroupid, membergroupids, infractiongroupids, username, password, salt FROM " . TABLE_PREFIX . "user WHERE username = '" . $vbulletin->db->escape_string(htmlspecialchars_uni($username)) . "'"))
	{
		if (
			$vbulletin->userinfo['password'] != iif($password AND !$md5password, md5(md5($password) . $vbulletin->userinfo['salt']), '') AND
			$vbulletin->userinfo['password'] != iif($md5password, md5($md5password . $vbulletin->userinfo['salt']), '') AND
			$vbulletin->userinfo['password'] != iif($md5password_utf, md5($md5password_utf . $vbulletin->userinfo['salt']), '')
		)
		{
			$return_value = false;
			($hook = vBulletinHook::fetch_hook('login_verify_failure_password')) ? eval($hook) : false;
			if (isset($return_value))
			{
				// unset $return_value if you want to run the $send_cookies stuff
				return $return_value;
			}
		}
		else if ($vbulletin->userinfo['password'] == '')
		{
			// sanity check, though there should never really be an empty string for a password
			$return_value = false;
			($hook = vBulletinHook::fetch_hook('login_verify_failure_password')) ? eval($hook) : false;
			if (isset($return_value))
			{
				// unset $return_value if you want to run the $send_cookies stuff
				return $return_value;
			}
		}

		if ($send_cookies)
		{
			if ($cookieuser)
			{
				vbsetcookie('userid', $vbulletin->userinfo['userid'], true, true, true);
				vbsetcookie('password', md5($vbulletin->userinfo['password'] . COOKIE_SALT), true, true, true);
			}
			else if ($vbulletin->GPC[COOKIE_PREFIX . 'userid'] AND $vbulletin->GPC[COOKIE_PREFIX . 'userid'] != $vbulletin->userinfo['userid'])
			{
				// we have a cookie from a user and we're logging in as
				// a different user and we're not going to store a new cookie,
				// so let's unset the old one
				vbsetcookie('userid', '', true, true, true);
				vbsetcookie('password', '', true, true, true);
			}
		}
		$return_value = true;
		($hook = vBulletinHook::fetch_hook('login_verify_success')) ? eval($hook) : false;
		return $return_value;
	}

	$return_value = false;
	($hook = vBulletinHook::fetch_hook('login_verify_failure_username')) ? eval($hook) : false;
	return $return_value;
}

// ###################### Start process new login #######################
// creates new session once $vbulletin->userinfo has been set to the newly logged in user
// processes logins into CP
function process_new_login($logintype, $cookieuser, $cssprefs)
{
	global $vbulletin;

	$lang_info = array(
		'lang_locale' =>  (isset($vbulletin->userinfo['lang_locale']) ? $vbulletin->userinfo['lang_locale'] : ''),
		'lang_charset' => (isset($vbulletin->userinfo['lang_charset']) ? $vbulletin->userinfo['lang_charset'] : '')
	);

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "session WHERE sessionhash = '" . $vbulletin->db->escape_string($vbulletin->session->vars['dbsessionhash']) . "'");

	if ($vbulletin->session->created == true AND $vbulletin->session->vars['userid'] == 0)
	{
		// if we just created a session on this page, there's no reason not to use it
		$newsession =& $vbulletin->session;
	}
	else
	{
		$newsession = new vB_Session($vbulletin, '', $vbulletin->userinfo['userid'], '', $vbulletin->session->vars['styleid'], $vbulletin->session->vars['languageid']);
	}
	$newsession->set('userid', $vbulletin->userinfo['userid']);
	$newsession->set('loggedin', 1);
	if ($logintype == 'cplogin')
	{
		$newsession->set('bypass', 1);
	}
	else
	{
		$newsession->set('bypass', 0);
	}
	$newsession->set_session_visibility(($vbulletin->superglobal_size['_COOKIE'] > 0));
	$newsession->fetch_userinfo();
	$vbulletin->session =& $newsession;
	$vbulletin->userinfo = $newsession->userinfo;
	$vbulletin->userinfo['lang_locale'] = $lang_info['lang_locale'];
	$vbulletin->userinfo['lang_charset'] = $lang_info['lang_charset'];

	// admin control panel or upgrade script login
	if ($logintype === 'cplogin')
	{
		$permissions = cache_permissions($vbulletin->userinfo, false);
		$vbulletin->userinfo['permissions'] =& $permissions;
		if ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
		{
			if ($cssprefs != '')
			{
				$admininfo = $vbulletin->db->query_first_slave("SELECT * FROM " . TABLE_PREFIX . "administrator WHERE userid = " . $vbulletin->userinfo['userid']);
				if ($admininfo)
				{
					$admindm = datamanager_init('Admin', $vbulletin, ERRTYPE_SILENT);
					$admindm->set_existing($admininfo);
					$admindm->set('cssprefs', $vbulletin->GPC['cssprefs']);
					$admindm->save();
				}
			}

			$cpsession = $vbulletin->session->fetch_sessionhash();
			/*insert query*/
			$vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "cpsession (userid, hash, dateline) VALUES (" . $vbulletin->userinfo['userid'] . ", '" . $vbulletin->db->escape_string($cpsession) . "', " . TIMENOW . ")");
			vbsetcookie('cpsession', $cpsession, false, true, true);

			if (!$cookieuser AND empty($vbulletin->GPC[COOKIE_PREFIX . 'userid']))
			{
				vbsetcookie('userid', $vbulletin->userinfo['userid'], false, true, true);
				vbsetcookie('password', md5($vbulletin->userinfo['password'] . COOKIE_SALT), false, true, true);
			}
		}
	}

	// moderator control panel login
	if ($logintype === 'modcplogin')
	{
		$permissions = cache_permissions($vbulletin->userinfo, false);
		$vbulletin->userinfo['permissions'] =& $permissions;

		require_once(DIR . '/includes/functions_calendar.php');
		if (can_moderate() OR can_moderate_calendar())
		{
			$cpsession = $vbulletin->session->fetch_sessionhash();
			/*insert query*/
			$vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "cpsession (userid, hash, dateline) VALUES (" . $vbulletin->userinfo['userid'] . ", '" . $vbulletin->db->escape_string($cpsession) . "', " . TIMENOW . ")");
			vbsetcookie('cpsession', $cpsession, false, true, true);

			if (!$cookieuser AND empty($vbulletin->GPC[COOKIE_PREFIX . 'userid']))
			{
				vbsetcookie('userid', $vbulletin->userinfo['userid'], false, true, true);
				vbsetcookie('password', md5($vbulletin->userinfo['password'] . COOKIE_SALT), false, true, true);
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('login_process')) ? eval($hook) : false;
}

// ###################### Start do login redirect #######################
function do_login_redirect()
{
	global $vbulletin, $vbphrase;

	if (
		$vbulletin->url == 'login.php'
		OR $vbulletin->url == $vbulletin->options['forumhome'] . '.php'
		OR strpos($vbulletin->url, 'do=logout') !== false
	)
	{
		$vbulletin->url = $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'];
	}
	else
	{
		$vbulletin->url = fetch_replaced_session_url($vbulletin->url);
		$vbulletin->url = preg_replace('#^/+#', '/', $vbulletin->url); // bug 3654 don't ask why
	}

	$temp = strpos($vbulletin->url, '?');
	if ($temp)
	{
		$formfile = substr($vbulletin->url, 0, $temp);
	}
	else
	{
		$formfile =& $vbulletin->url;
	}

	$postvars = $vbulletin->GPC['postvars'];

	($hook = vBulletinHook::fetch_hook('login_redirect')) ? eval($hook) : false;

	// recache the global group to get the stuff from the new language
	$globalgroup = $vbulletin->db->query_first_slave("
		SELECT phrasegroup_global, languagecode, charset
		FROM " . TABLE_PREFIX . "language
		WHERE languageid = " . intval($vbulletin->userinfo['languageid'] ? $vbulletin->userinfo['languageid'] : $vbulletin->options['languageid'])
	);
	if ($globalgroup)
	{
		$vbphrase = array_merge($vbphrase, vb_unserialize($globalgroup['phrasegroup_global']));

		global $stylevar;
		if ($stylevar['charset'] != $globalgroup['charset'])
		{
			// change the character set in a bunch of places - a total hack
			global $headinclude;

			$headinclude = str_replace(
				"content=\"text/html; charset=$stylevar[charset]\"",
				"content=\"text/html; charset=$globalgroup[charset]\"",
				$headinclude
			);

			$stylevar['charset'] = $globalgroup['charset'];
			$vbulletin->userinfo['lang_charset'] = $globalgroup['charset'];

			exec_headers();
		}

		$stylevar['languagecode'] = $globalgroup['languagecode'];
	}

	eval(print_standard_redirect('redirect_login', true, true, $vbulletin->userinfo['languageid']));
}

// ###################### Start process logout #######################
function process_logout()
{
	global $vbulletin;

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
			// vbsetcookie will add the cookie prefix
			vbsetcookie($key, '', 1);
		}
	}

	if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['userid'] != -1)
	{
		// init user data manager
		$userdata = datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
		$userdata->set_existing($vbulletin->userinfo);
		$userdata->set('lastactivity', TIMENOW - $vbulletin->options['cookietimeout']);
		$userdata->set('lastvisit', TIMENOW);
		$userdata->save();

		// make sure any other of this user's sessions are deleted (in case they ended up with more than one)
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "session WHERE userid = " . $vbulletin->userinfo['userid']);
	}

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "session WHERE sessionhash = '" . $vbulletin->db->escape_string($vbulletin->session->vars['dbsessionhash']) . "'");

	if ($vbulletin->session->created == true)
	{
		// if we just created a session on this page, there's no reason not to use it
		$newsession =& $vbulletin->session;
	}
	else
	{
		$newsession = new vB_Session($vbulletin, '', 0, '', $vbulletin->session->vars['styleid']);
	}
	$newsession->set('userid', 0);
	$newsession->set('loggedin', 0);
	$newsession->set_session_visibility(($vbulletin->superglobal_size['_COOKIE'] > 0));
	$vbulletin->session =& $newsession;

	($hook = vBulletinHook::fetch_hook('logout_process')) ? eval($hook) : false;
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
