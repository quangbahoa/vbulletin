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
define('THIS_SCRIPT', 'online');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('wol');

// get special data templates from the datastore
$specialtemplates = array(
	'maxloggedin',
	'wol_spiders'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'forumdisplay_sortarrow',
	'forumhome_loggedinusers',
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'WHOSONLINE',
	'whosonlinebit'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_online.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!$vboptions['WOLenable'])
{
	eval(print_standard_error('error_whosonlinedisabled'));
}

if (!($permissions['wolpermissions'] & CANWHOSONLINE))
{
	print_no_permission();
}

$datecut = TIMENOW - $vboptions['cookietimeout'];
$wol_event = array();
$wol_pm = array();
$wol_calendar = array();
$wol_user = array();
$wol_forum = array();
$wol_link = array();
$wol_thread = array();
$wol_post = array();

globalize($_REQUEST, array('perpage' => INT, 'pagenumber' => INT, 'sortorder' => STR, 'sortfield' => STR, 'who' => STR, 'ua' => INT));

// We can support multi page but we still have to grab every record and just throw away what we don't use.
// set defaults
$perpage = sanitize_perpage($perpage, 200, $vboptions['maxthreads']);

//sanitize_pageresults($totalonline, $pagenumber, $perpage, 200, $vboptions['maxthreads']);

if (!$pagenumber)
{
	$pagenumber = 1;
}

$limitlower = ($pagenumber - 1) * $perpage + 1;
$limitupper = ($pagenumber) * $perpage;

if ($sortorder != 'desc')
{
	$sortorder = 'asc';
	$oppositesort = 'desc';
}
else
{ // $sortorder = 'desc'
	$sortorder = 'desc';
	$oppositesort = 'asc';
}

switch ($sortfield)
{
	case 'location':
		$sqlsort = 'session.location';
		break;
	case 'time':
		$sqlsort = 'session.lastactivity';
		break;
	case 'host':
		$sqlsort = 'session.host';
		break;
	default:
		$sqlsort = 'user.username';
		$sortfield = 'username';
}

$allonly = $vbphrase['all'];
$membersonly = $vbphrase['members'];
$spidersonly = $vbphrase['spiders'];
$guestsonly = $vbphrase['guests'];
$whoselected = array();
$uaselected = array();

switch ($who)
{
	case 'members':
		$showmembers = true;
		$whoselected[1] = HTML_SELECTED;
		break;
	case 'guests':
		$showguests = true;
		$whoselected[2] = HTML_SELECTED;
		break;
	case 'spiders':
		$showspiders = true;
		$whoselected[3] = HTML_SELECTED;
		break;
	default:
		$showmembers = true;
		$showguests = true;
		$showspiders = true;
		$who = '';
		$whoselected[0] = HTML_SELECTED;
}

if ($ua)
{
	$uaselected[1] = HTML_SELECTED;
	$showua = true;
}
else
{
	$uaselected[0] = HTML_SELECTED;
	$showua = false;
}

$sorturl = "online.php?$session[sessionurl]";
eval("\$sortarrow[$sortfield] = \"" . fetch_template('forumdisplay_sortarrow') . '";');

$allusers = $DB_site->query("
	SELECT user.username, session.useragent, session.location, session.lastactivity, user.userid, user.options, session.host, session.badlocation, session.incalendar, user.aim, user.icq, user.msn, user.yahoo,
	IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
	FROM " . TABLE_PREFIX . "session AS session
	". iif($vboptions['WOLguests'], " LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid) ", ", " . TABLE_PREFIX . "user AS user") ."
	WHERE session.lastactivity > $datecut
		". iif(!$vboptions['WOLguests'], " AND session.userid = user.userid", "") ."
	ORDER BY $sqlsort $sortorder
");

$moderators = $DB_site->query("SELECT DISTINCT userid FROM " . TABLE_PREFIX . "moderator");
while ($mods = $DB_site->fetch_array($moderators))
{
	$mod["$mods[userid]"] = 1;
}

$count = 0;
$userinfo = array();
$guests = array();

// get buddylist
$buddy = array();
if (trim($bbuserinfo['buddylist']))
{
	$buddylist = preg_split('/( )+/', trim($bbuserinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
	foreach ($buddylist AS $buddyuserid)
	{
		$buddy["$buddyuserid"] = 1;
	}
}

require_once('./includes/functions_showthread.php');
while ($users = $DB_site->fetch_array($allusers))
{
	if ($users['userid'])
	{ // Reg'd Member
		if (!$showmembers)
		{
			continue;
		}

		$users = array_merge($users, convert_bits_to_array($users['options'] , $_USEROPTIONS));

		$key = $users['userid'];
		if ($key == $bbuserinfo['userid'])
		{ // in case this is the first view for the user, fake it that show up to themself
			$foundviewer = true;
		}
		if (empty($userinfo["$key"]['lastactivity']) OR ($userinfo["$key"]['lastactivity'] < $users['lastactivity']))
		{
			unset($userinfo["$key"]); // need this to sort by lastactivity
			$userinfo["$key"] = $users;
			$userinfo["$key"]['musername'] = fetch_musername($users);
			$userinfo["$key"]['useragent'] = htmlspecialchars_uni($users['useragent']);
			construct_im_icons($userinfo["$key"]);
			if ($users['invisible'])
			{
				if (($permissions['genericpermissions'] & CANSEEHIDDEN) OR $key == $bbuserinfo['userid'])
				{
					$userinfo["$key"]['hidden'] = '*';
					$userinfo["$key"]['invisible'] = 0;
				}
			}
			if ($vboptions['WOLresolve'] AND ($permissions['wolpermissions'] & CANWHOSONLINEIP))
			{
				$userinfo["$key"]['host'] = @gethostbyaddr($users['host']);
			}
			$userinfo["$key"]['buddy'] = $buddy["$key"];
		}
	}
	else
	{ // Guest or Spider..
		$spider = '';

		if ($vboptions['enablespiders'] AND $datastore['wol_spiders']['spiderstring'])
		{
			$spiderstring = $datastore['wol_spiders']['spiderstring'];
			if (preg_match("#($spiderstring)#si", strtolower($users['useragent']), $agent))
			{
				$spider = $datastore['wol_spiders']['spiderarray']["$agent[1]"];
			}
		}

		if ($spider)
		{
			if (!$showspiders)
			{
				continue;
			}
			$guests["$count"] = $users;
			$guests["$count"]['spider'] = $spider;
		}
		else
		{
			if (!$showguests)
			{
				continue;
			}
			$guests["$count"] = $users;
		}

		$guests["$count"]['username'] = $vbphrase['guest'];
		$guests["$count"]['invisible'] = 0;
		$guests["$count"]['displaygroupid'] = 1;
		$guests["$count"]['musername'] = fetch_musername($guests["$count"]);
		if ($vboptions['WOLresolve'] AND ($permissions['wolpermissions'] & CANWHOSONLINEIP))
		{
			$guests["$count"]['host'] = @gethostbyaddr($users['host']);
		}
		$guests["$count"]['count'] = $count + 1;
		$guests["$count"]['useragent'] = htmlspecialchars_uni($users['useragent']);
		$count++;
	}
}

if (!$foundviewer AND $bbuserinfo['userid'] AND ($who == '' OR $who == 'members'))
{ // Viewing user did not show up so fake him
	$userinfo["$bbuserinfo[userid]"] = $bbuserinfo;
	$userinfo["$bbuserinfo[userid]"]['location'] = '/online.php';
	$userinfo["$bbuserinfo[userid]"]['host'] = IPADDRESS;
	$userinfo["$bbuserinfo[userid]"]['lastactivity'] = TIMENOW;
	$userinfo["$bbuserinfo[userid]"]['joingroupid'] = iif($bbuserinfo['displaygroupid'] == 0, $bbuserinfo['usergroupid'], $bbuserinfo['displaygroupid']);
	$userinfo["$bbuserinfo[userid]"]['musername'] = fetch_musername($userinfo["$bbuserinfo[userid]"], 'joingroupid');
	$userinfo["$bbuserinfo[userid]"]['hidden'] = iif($bbuserinfo['invisible'], '*');
	$userinfo["$bbuserinfo[userid]"]['invisible'] = 0;
	construct_im_icons($userinfo["$bbuserinfo[userid]"]);

	if ($vboptions['WOLresolve'] AND ($permissions['wolpermissions'] & CANWHOSONLINEIP))
	{
		$userinfo[$bbuserinfo['userid']]['host'] = @gethostbyaddr($userinfo["$bbuserinfo[userid]"]['host']);
	}
}

$show['ip'] = iif($permissions['wolpermissions'] & CANWHOSONLINEIP, true, false);
$show['useragent'] = iif($showua, true, false);
$show['hidden'] = iif($permissions['genericpermissions'] & CANSEEHIDDEN, true, false);
$show['badlocation'] = iif($permissions['wolpermissions'] & CANWHOSONLINEBAD, true, false);

if (is_array($userinfo))
{
	foreach ($userinfo AS $key => $val)
	{
		if (!$val['invisible'])
		{
			$userinfo["$key"] = process_online_location($val, 1);
		}
	}
}

if (is_array($guests))
{
	foreach ($guests AS $key => $val)
	{
		$guests["$key"] = process_online_location($val, 1);
	}
}

convert_ids_to_titles();

$onlinecolspan = 4;

$bgclass = 'alt1';

if ($vboptions['enablepms'])
{
	$onlinecolspan++;
}

if ($vboptions['displayemails'] OR $vboptions['enablepms'])
{
	$onlinecolspan++;
	exec_switch_bg();
	$contactclass = $bgclass;
}

if ($permissions['wolpermissions'] & CANWHOSONLINEIP)
{
	$onlinecolspan++;
	exec_switch_bg();
	$ipclass = $bgclass;
}

if ($vboptions['showimicons'])
{
	$onlinecolspan += 4;
	exec_switch_bg();
	exec_switch_bg();
	exec_switch_bg();
	exec_switch_bg();
}

$numbervisible = 0;
$numberinvisible = 0;
if (is_array($userinfo))
{
	foreach ($userinfo AS $key => $val)
	{
		if (!$val['invisible'])
		{
			$onlinebits .= construct_online_bit($val, 1);
			$numbervisible++;
		}
		else
		{
			$numberinvisible++;
		}
	}
}

$numberguests = 0;
if (is_array($guests))
{
	foreach ($guests AS $key => $val)
	{
		$numberguests++;
		$onlinebits .= construct_online_bit($val, 1);
	}
}

$totalonline = $numbervisible + $numberguests;

// ### MAX LOGGEDIN USERS ################################
$maxusers = unserialize($datastore['maxloggedin']);
if (intval($maxusers['maxonline']) <= $totalonline)
{
	$maxusers['maxonline'] = $totalonline;
	$maxusers['maxonlinedate'] = TIMENOW;
	build_datastore('maxloggedin', serialize($maxusers));
}
$recordusers = $maxusers['maxonline'];
$recorddate = vbdate($vboptions['dateformat'], $maxusers['maxonlinedate'], true);
$recordtime = vbdate($vboptions['timeformat'], $maxusers['maxonlinedate']);

$currenttime = vbdate($vboptions['timeformat']);
$metarefresh = '';

if ($vboptions['WOLrefresh'])
{
	if (is_browser('mozilla'))
	{
		$metarefresh = "\n<script type=\"text/javascript\">\n";
		$metarefresh .= "myvar = \"\";\ntimeout = " . ($vboptions['WOLrefresh'] * 10) . ";
function exec_refresh()
{
	timerID = setTimeout(\"exec_refresh();\", 100);
	if (timeout > 0)
	{ timeout -= 1; }
	else { clearTimeout(timerID); window.location=\"online.php?$session[sessionurl_js]order=$sortorder&sort=$sortfield&pp=$perpage&page=$pagenumber" . iif($who, "&who=$who") . iif($showua, '&ua=1') . "\"; }
}
exec_refresh();";

		$metarefresh .= "\n</script>\n";
	}
	else
	{
		$metarefresh = "<meta http-equiv=\"refresh\" content=\"$vboptions[WOLrefresh]; url=online.php?$session[sessionurl]order=$sortorder&amp;sort=$sortfield&amp;pp=$perpage&amp;page=$pagenumber" . iif($who, "&amp;who=$who") . iif($showua, '&amp;ua=1') . "\" /> ";
	}
}

$frmjmpsel['wol'] = ' selected="selected" class="fjsel"';
construct_forum_jump();

$pagenav = construct_page_nav($totalonline, "online.php?$session[sessionurl]sort=$sortfield&amp;order=$sortorder&amp;pp=$perpage" . iif($who, "&amp;who=$who") . iif($showua, '&amp;ua=1'));
$numbervisible += $numberinvisible;

$colspan = 2;
$colspan = iif($show['ip'], $colspan + 1, $colspan);
$colspan = iif($vboptions['showimicons'], $colspan + 1, $colspan);

$navbits = construct_navbits(array('' => $vbphrase['whos_online']));
eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('WHOSONLINE') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13124 $
|| ####################################################################
\*======================================================================*/
?>