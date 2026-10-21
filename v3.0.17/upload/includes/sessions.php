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
if (!is_object($DB_site))
{
	exit;
}

// Don't insert a new session, just return a minimal session
if (defined('SKIP_SESSIONCREATE'))
{
	// guest setup
	$bbuserinfo = array(
		'userid'         => 0,
		'usergroupid'    => 1,
		'styleid'        => 0,
		'languageid'     => 0,
		'timezoneoffset' => 0,
	);

	// get default language
	$DB_site->reporterror = 0;
	$phraseinfo = $DB_site->query_first("
		SELECT languageid" . fetch_language_fields_sql(0) . "
		FROM " . TABLE_PREFIX . "language
		WHERE languageid = " . intval($vboptions['languageid']) . "
	");
	foreach($phraseinfo AS $_arrykey => $_arryval)
	{
		$bbuserinfo["$_arrykey"] = $_arryval;
	}
	$DB_site->reporterror = 1;
	unset($phraseinfo);

	return;
}

// ###################### Start makesessionhash #######################
// get session info
function fetch_sessionhash()
{
	return md5(TIMENOW . SCRIPTPATH . SESSION_IDHASH . SESSION_HOST . vbrand(1, 1000000));
}

// ###################### Start createsession #######################
function build_session($userid = 0, $styleid = 0)
{
	global $servertoobusy, $vboptions, $DB_site, $_REQUEST, $sessioncreated;

	$session = array(
		'sessionhash' => fetch_sessionhash(),
		'userid' => intval($userid),
		'host' => SESSION_HOST,
		'idhash' => SESSION_IDHASH,
		'lastactivity' => TIMENOW,
		'location' => WOLPATH,
		'styleid' => intval($styleid),
		'useragent' => USER_AGENT,
		'loggedin' => 0
	);

	if (defined('LOCATION_BYPASS'))
	{
		$session['location'] = '';
	}

	$session['bypass'] = intval(SESSION_BYPASS);

	if ($vboptions['sessionlimit'] > 0 AND !$servertoobusy)
	{
		$sessions = $DB_site->query_first("SELECT COUNT(*) AS sessioncount FROM " . TABLE_PREFIX . "session");
		if ($sessions['sessioncount'] > $vboptions['sessionlimit'])
		{
			$servertoobusy = true;
		}
	}

	if ($servertoobusy)
	{
		return $session;
	}

	if (THIS_SCRIPT == 'login' OR THIS_SCRIPT == 'cron')
	{
		// we're already going to be inserting a session of our own
		// Don't have cron create sessions as it may collide with login.php creating a session.
		return;
	}

	// Have to use two replacements for those boards that shutdown queries don't work on as this will get executed
	// before the replacements can be done!
	$DB_site->shutdown_query("
		INSERT INTO " . TABLE_PREFIX . "session
			(sessionhash, userid, host, useragent, idhash, lastactivity, location, " . iif(VB_AREA !== 'Upgrade', 'bypass, ') . "styleid
			###REPLACE1###
			)
		VALUES
			('" . addslashes($session['sessionhash']) . "', $session[userid],
			'" . addslashes($session['host']) . "', '" . addslashes($session['useragent']) . "', '" . addslashes($session['idhash']) . "',
			$session[lastactivity], '" . addslashes($session['location']) . "', " . iif(VB_AREA !== 'Upgrade', "$session[bypass], ") . "$session[styleid]
			###REPLACE2###
			)
	", 'sessioninsert');

	if ($_REQUEST['do'] != 'login' AND $_REQUEST['do'] != 'logout')
	{
		vbsetcookie('sessionhash', $session['sessionhash'], false, true);
	}

	$sessioncreated = true;

	return $session;
}

// set defaults
unset($session);
unset($bbuserinfo);
$sessioncreated = false;

// handle style defaults
if (isset($_REQUEST['styleid']))
{
	// note: 0 is a valid styleid! (it'll remove the cookie)
	$styleid = intval($_REQUEST['styleid']);
	vbsetcookie('styleid', $styleid);
}
else if (isset($_COOKIE[COOKIE_PREFIX . 'styleid']))
{
	$styleid = intval($_COOKIE[COOKIE_PREFIX . 'styleid']);
}
else
{
	$styleid = 0;
}

// handle threaded/linear mode defaults
if ($vboptions['allowthreadedmode'])
{
	if (isset($_GET['mode']))
	{
		switch ($_GET['mode'])
		{
			case 'threaded':
				$threadedmode = 1;
				$threadedCookieVal = 'threaded';
				break;
			case 'hybrid':
				$threadedmode = 2;
				$threadedCookieVal = 'hybrid';
				break;
			default:
				$threadedmode = 0;
				$threadedCookieVal = 'linear';
				break;
		}
		vbsetcookie('threadedmode', $threadedCookieVal);
		$_COOKIE[COOKIE_PREFIX . 'threadedmode'] = $threadedCookieVal;
		unset($threadedCookieVal);
	}
	else if (isset($_COOKIE[COOKIE_PREFIX . 'threadedmode']))
	{
		switch ($_COOKIE[COOKIE_PREFIX . 'threadedmode'])
		{
			case 'threaded':
				$threadedmode = 1;
				break;
			case 'hybrid':
				$threadedmode = 2;
				break;
			default:
				$threadedmode = 0;
				break;
		}
	}
}
else
{
	$threadedmode = 0;
}

// look for sessionhash through URL
if (!empty($_POST['s']))
{ // session sent through POST?
	$_REQUEST['sessionhash'] = $_POST['s']; // override cookie
}
else if (!empty($_GET['s']))
{ // session sent through GET?
	$_REQUEST['sessionhash'] = $_GET['s']; // override cookie
}
else
{
	$_REQUEST['sessionhash'] = isset($_COOKIE[COOKIE_PREFIX . 'sessionhash']) ? $_COOKIE[COOKIE_PREFIX . 'sessionhash'] : $_COOKIE['sessionhash'];
}

// check for session sent through URL/cookie
if (!empty($_REQUEST['sessionhash']))
{
	$session = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "session
		WHERE sessionhash = '" . addslashes(trim($_REQUEST['sessionhash'])) . "'
		AND lastactivity > " . (TIMENOW - $vboptions['cookietimeout']) . "
		AND host = '" . addslashes(SESSION_HOST) . "'
		AND idhash = '" . addslashes(SESSION_IDHASH) . "'
	");
}


// no valid session or guest session, so check cookies
if (empty($session) OR $session['userid'] == 0)
{
	if (!empty($_COOKIE[COOKIE_PREFIX . 'userid']) AND !empty($_COOKIE[COOKIE_PREFIX . 'password']))
	{
		$useroptions = 0 + iif(defined('IN_CONTROL_PANEL'), 16, 0) + iif(defined('AVATAR_ON_NAVBAR'), 2, 0);
		$bbuserinfo = fetch_userinfo($_COOKIE[COOKIE_PREFIX . 'userid'], $useroptions);
		if (md5($bbuserinfo['password'] . 'VBS4AAFB47') == $_COOKIE[COOKIE_PREFIX . 'password'])
		{
			// combination is valid
			if (!empty($session['sessionhash']))
			{
				// old session still exists; kill it
				$DB_site->shutdown_query("
					DELETE FROM " . TABLE_PREFIX . "session
					WHERE sessionhash = '" . addslashes($session['sessionhash']). "'
				");
			}
			$session = build_session($bbuserinfo['userid'], $styleid);
		}
		// cookie is bad!
		else if (THIS_SCRIPT != 'login')
		{
			// cookie's bad and since we're not doing anything login related, kill the bad cookie
			vbsetcookie('userid', '', true, true);
			vbsetcookie('password', '', true, true);
		}
	}
}

if (empty($session))
{
	// still no session. the user is a guest, so try to find this guest's session
	$session = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "session
		WHERE userid = 0
		AND host = '" . addslashes(SESSION_HOST) . "'
		AND idhash = '" . addslashes(SESSION_IDHASH) . "'
		LIMIT 1
	");

	if (empty($session))
	{
		// still no session -- create a new one
		$session = build_session(0, $styleid);
	}
}

// by now, a session should be created/used in all cases (except for the server being too busy)

if ($session['userid'] == 0)
{
	// guest setup
	$bbuserinfo = array(
		'userid' => 0,
		'usergroupid' => 1,
		'username' => iif(!empty($_REQUEST['username']), htmlspecialchars_uni($_REQUEST['username'])),
		'password' => '',
		'email' => '',
		'styleid' => $session['styleid'],
		'lastactivity' => $session['lastactivity'],
		'daysprune' => 0,
		'timezoneoffset' => $vboptions['timeoffset'],
		'dstonoff' => $vboptions['dstonoff'],
		'showsignatures' => 1,
		'showavatars' => 1,
		'showimages' => 1,
		'dstauto' => 0,
		'maxposts' => -1,
		'startofweek' => 0,
		'threadedmode' => $vboptions['threadedmode']
	);

	// get default language
	$DB_site->reporterror = 0;
	$phraseinfo = $DB_site->query_first("
		SELECT languageid" . fetch_language_fields_sql(0) . "
		FROM " . TABLE_PREFIX . "language
		WHERE languageid = " . intval($vboptions['languageid']) . "
	");
	foreach($phraseinfo AS $_arrykey => $_arryval)
	{
		$bbuserinfo["$_arrykey"] = $_arryval;
	}
	$DB_site->reporterror = 1;
	unset($phraseinfo);

	// emulate the registered user's setup for lastvisit/lastactivity with cookies for new post markers
	if (!empty($_COOKIE[COOKIE_PREFIX . 'lastvisit']))
	{
		$bbuserinfo['lastvisit'] = intval($_COOKIE[COOKIE_PREFIX . 'lastvisit']);
		if (empty($_COOKIE[COOKIE_PREFIX . 'lastactivity']))
		{
			$bbuserinfo['lastactivity'] = TIMENOW;
		}
		else
		{
			$bbuserinfo['lastactivity'] = intval($_COOKIE[COOKIE_PREFIX . 'lastactivity']);
		}

		// see if user has been here recently
		if (TIMENOW - $bbuserinfo['lastactivity'] > $vboptions['cookietimeout'])
		{
			// nope, they have not been here recently, so  reset new post markers
			vbsetcookie('lastvisit', $bbuserinfo['lastactivity']);
			$bbuserinfo['lastvisit'] = $bbuserinfo['lastactivity'];
		}
	}
	else
	{
		$bbuserinfo['lastvisit'] = TIMENOW;
		vbsetcookie('lastvisit', TIMENOW);
	}
	vbsetcookie('lastactivity', $session['lastactivity']);
}
else
{
	// handle some stuff for registered users
	if (empty($bbuserinfo))
	{
		$useroptions = 0 + iif(defined('IN_CONTROL_PANEL'), 16, 0) + iif(defined('AVATAR_ON_NAVBAR'), 2, 0);
		$bbuserinfo = fetch_userinfo($session['userid'], $useroptions);
	}

	if (!SESSION_BYPASS)
	{
		if (TIMENOW - $bbuserinfo['lastactivity'] > $vboptions['cookietimeout'])
		{ // see if session has 'expired' and if new post indicators need resetting
			$DB_site->shutdown_query("
				UPDATE " . TABLE_PREFIX . "user
				SET lastvisit = lastactivity,
				lastactivity = " . TIMENOW . "
				WHERE userid = $bbuserinfo[userid]
			", 'lastvisit');
			$bbuserinfo['lastvisit'] = $bbuserinfo['lastactivity'];
		}
		else
		{
			// if this line is removed (say to be replaced by a cron job, you will need to change all of the 'online'
			// status indicators as they use $userinfo['lastactivity'] to determine if a user is online which relies
			// on this to be updated in real time.
			$DB_site->shutdown_query("
				UPDATE " . TABLE_PREFIX . "user
				SET lastactivity = " . TIMENOW . "
				WHERE userid = $bbuserinfo[userid]
			", 'lastvisit');
		}
	}
}

// update session data if we didn't just create it
if (!$sessioncreated AND THIS_SCRIPT != 'cron')
{
	if (!defined('LOCATION_BYPASS'))
	{ // An attempt to not have cron.php, or other such scripts update the user's location!
		$location = ', location = "' . addslashes(WOLPATH) . '"';
	}
	else
	{
		$location = '';
	}
	$bypass = intval(SESSION_BYPASS);
	$DB_site->shutdown_query("
		UPDATE " . TABLE_PREFIX . "session
		SET useragent = '" . addslashes(USER_AGENT) . "', lastactivity = " . TIMENOW . $location . ", styleid = $styleid" . iif(VB_AREA !== 'Upgrade', ", bypass = $bypass") . "
		 ###REPLACE###
		 WHERE sessionhash = '" . addslashes($session['sessionhash']) . "'"
		, 'sessionupdate'
	);
	$session['lastactivity'] = TIMENOW;
	$session['location'] = WOLPATH;
	$session['styleid'] = intval($styleid);
	unset($location);
}

// if there's a session/cookie based styleid, override the user specified style
if ($session['styleid'] != 0)
{
	$bbuserinfo['styleid'] = $session['styleid'];
}

$session['dbsessionhash'] = $session['sessionhash'];
// automatically determine whether to put the sessionhash into the URL

if (sizeof($_COOKIE) > 0 OR preg_match("#(google|msnbot|yahoo! slurp)#si", $_SERVER['HTTP_USER_AGENT']))
{
	// they have at least 1 cookie, so they should be accepting them
	$nosessionhash = 1;
	$shash = $session['sessionhash'] = '';
	$surl = $session['sessionurl'] = '';
	$surlJS = $session['sessionurl_js'] = '';
}
else
{
	$nosessionhash = 0;
	$shash = $session['sessionhash'];
	$surl = $session['sessionurl'] = 's=' . $session['sessionhash'] . '&amp;';
	$surlJS = $session['sessionurl_js'] = 's=' . $session['sessionhash'] . '&';
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15482 $
|| ####################################################################
\*======================================================================*/
?>