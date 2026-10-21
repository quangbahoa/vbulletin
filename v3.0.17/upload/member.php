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
define('THIS_SCRIPT', 'member');
define('BYPASS_STYLE_OVERRIDE', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('wol', 'user');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'rankphp'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'MEMBERINFO',
	'memberinfo_customfields',
	'memberinfo_membergroupbit',
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'bbcode_code',
	'bbcode_html',
	'bbcode_php',
	'bbcode_quote',
	'postbit_reputation',
	'userfield_checkbox_option',
	'userfield_select_option'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

if ($_REQUEST['do'] == 'vcard')
{
	$noheader = 1;
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_showthread.php');
require_once('./includes/functions_user.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!($permissions['forumpermissions'] & CANVIEW) OR !($permissions['genericpermissions'] & CANVIEWMEMBERS))
{
	print_no_permission();
}

/*
if ($_REQUEST['do'] == 'quickinfo')
{
	$quick = true;
}
else
{
	$quick = false;
}
*/

globalize($_REQUEST, array(
	'find' => STR,
	'threadid' => INT,
	'forumid' => INT,
	'moderatorid' => INT,
	'userid' => INT,
	'username' => STR
));

if ($find == 'firstposter' AND $threadid)
{
	$threadinfo = verify_id('thread', $threadid, 1, 1);
	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		$idname = $vbphrase['thread'];
		eval(print_standard_error('error_invalidid'));
	}
	$userid = $threadinfo['postuserid'];
}
else if ($find == 'lastposter' AND $threadid)
{
	$threadinfo = verify_id('thread', $threadid, 1, 1);
	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		$idname = $vbphrase['thread'];
		eval(print_standard_error('error_invalidid'));
	}
	$getuserid = $DB_site->query_first("
		SELECT post.userid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND deletionlog.type = 'post')
		WHERE threadid = $threadinfo[threadid]
			AND visible = 1
			AND deletionlog.primaryid IS NULL
		ORDER BY dateline DESC
		LIMIT 1
	");
	$userid = $getuserid['userid'];
}
else if ($find == 'lastposter' AND $forumid)
{
	$foruminfo = verify_id('forum', $forumid, 1, 1);
	$forumid = $foruminfo['forumid'];

	// prevent a small backdoor where anyone could see who the last poster in ANY forum was
	$_permsgetter_ = 'lastposter fperms';
	$forumperms = fetch_permissions($forumid);
	if (!($forumperms & CANVIEW))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	require_once('./includes/functions_misc.php');
	$forumslist = $forumid . ',' . fetch_child_forums($forumid);

	$thread = $DB_site->query_first("
		SELECT threadid
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND type = 'thread')
		WHERE forumid IN ($forumslist)
			AND visible = 1
			AND (sticky = 1 OR sticky = 0)
			AND lastpost >= " . ($foruminfo['lastpost'] - 30) . "
			AND open <> 10
			AND deletionlog.primaryid IS NULL
		ORDER BY lastpost DESC
		LIMIT 1
	");

	if (!$thread)
	{
		$idname = $vbphrase['user'];
		eval(print_standard_error('error_invalidid'));
	}
	$getuserid = $DB_site->query_first("
		SELECT post.userid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND deletionlog.type = 'post')
		WHERE threadid = $thread[threadid]
			AND visible = 1
			AND deletionlog.primaryid IS NULL
		ORDER BY dateline DESC
		LIMIT 1
	");
	$userid = $getuserid['userid'];
}
else if ($find == 'moderator' AND $moderatorid)
{
	$moderatorinfo = verify_id('moderator', $moderatorid, 1, 1);
	$userid = $moderatorinfo['userid'];
}
else if ($username != '' AND $userid == 0)
{
	$user = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes(htmlspecialchars_uni($username)) . "'");
	$userid = $user['userid'];
}

if (!$userid)
{
	eval(print_standard_error('error_unregistereduser'));
}

$userinfo = verify_id('user', $userid, 1, 1, 15);


if ($userinfo['usergroupid'] == 4 && !($permissions['adminpermissions'] & CANCONTROLPANEL))
{
	print_no_permission();
}

if ($_REQUEST['do'] == 'vcard' AND $bbuserinfo['userid'] AND $userinfo['showvcard'])
{
	// source: http://www.ietf.org/rfc/rfc2426.txt
	$text = "BEGIN:VCARD\r\n";
	$text .= "VERSION:2.1\r\n";
	$text .= "N:;$userinfo[username]\r\n";
	$text .= "FN:$userinfo[username]\r\n";
	$text .= "EMAIL;PREF;INTERNET:$userinfo[email]\r\n";
	if (!empty($userinfo['birthday'][7]))
	{
		$birthday = explode('-', $userinfo['birthday']);
		$text .= "BDAY:$birthday[2]-$birthday[0]-$birthday[1]\r\n";
	}
	if (!empty($userinfo['homepage']))
	{
		$text .= "URL:$userinfo[homepage]\r\n";
	}
	$text .= 'REV:' . date('Y-m-d') . 'T' . date('H:i:s') . "Z\r\n";
	$text .= "END:VCARD\r\n";

	$filename = $userinfo['userid'] . '.vcf';

	header("Content-Disposition: attachment; filename=$filename");
	header('Content-Length: ' . strlen($text));
	header('Connection: close');
	header("Content-Type: text/x-vCard; name=$filename");
	echo $text;
	exit;
}

// display user info

$userperms = cache_permissions($userinfo, false);

if ((($userid == $bbuserinfo['userid'] AND $permissions['genericpermissions'] & CANVIEWOWNUSERNOTES) OR ($userid != $bbuserinfo['userid'] AND $permissions['genericpermissions'] & CANVIEWOTHERSUSERNOTES)) AND $userperms['genericpermissions'] & CANBEUSERNOTED)
{
	$show['usernotes'] = true;
	$usernote = $DB_site->query_first("
		SELECT MAX(dateline) AS lastpost, COUNT(*) AS total
		FROM " . TABLE_PREFIX . "usernote AS usernote
		WHERE userid = $userinfo[userid]
	");
	$show['usernotetotal'] = iif($usernote['total'], true, false);
	$usernote['lastpostdate'] = vbdate($vboptions['dateformat'], $usernote['lastpost'], true);
	$usernote['lastposttime'] = vbdate($vboptions['timeformat'], $usernote['lastpost'], true);
}
else
{
	$show['usernotes'] = false;
}

// PROFILE PIC
$show['profilepic'] = iif($userinfo['profilepic'] AND ($permissions['genericpermissions'] & CANSEEPROFILEPIC OR $bbuserinfo['userid'] == $userinfo['userid']), true, false);

// CUSTOM TITLE
if ($userinfo['customtitle'] == 2)
{
	$userinfo['usertitle'] = htmlspecialchars_uni($userinfo['usertitle']);
}

// LAST ACTIVITY AND LAST VISIT
if (!$userinfo['invisible'] OR ($permissions['genericpermissions'] & CANSEEHIDDEN) OR $userinfo['userid'] == $bbuserinfo['userid'])
{
	$show['lastactivity'] = true;
	$userinfo['lastactivitydate'] = vbdate($vboptions['dateformat'], $userinfo['lastactivity'], true);
	$userinfo['lastactivitytime'] = vbdate($vboptions['timeformat'], $userinfo['lastactivity'], true);
}
else
{
	$show['lastactivity'] = false;
	$userinfo['lastactivitydate'] = '';
	$userinfo['lastactivitytime'] = '';
}

// Get Rank
$post = &$userinfo;
eval($datastore['rankphp']);

// JOIN DATE & POSTS PER DAY
$userinfo['datejoined'] = vbdate($vboptions['dateformat'], $userinfo['joindate']);
$jointime = (TIMENOW - $userinfo['joindate']) / 86400; // Days Joined
if ($jointime < 1)
{ // User has been a member for less than one day.
	$userinfo['posts'] = vb_number_format($userinfo['posts']);
	$postsperday = $userinfo['posts'];
}
else
{
	$postsperday = vb_number_format($userinfo['posts'] / $jointime, 2);
	$userinfo['posts'] = vb_number_format($userinfo['posts']);
}

// HOMEPAGE
if ($userinfo['homepage'] != 'http://' AND $userinfo['homepage'] != '')
{
	$show['homepage'] = true;
}
else
{
	$show['homepage'] = false;
}

// PRIVATE MESSAGE
if ($userinfo['receivepm'] AND $userperms['pmquota'] > 0)
{
	$userinfo['pm'] = 1;
}
else
{
	$userinfo['pm'] = 0;
}

// IM icons
construct_im_icons($userinfo, true);

// AVATAR
$userinfo['avatarurl'] = fetch_avatar_url($userinfo['userid']);
if ($userinfo['avatarurl'] == '')
{
	$show['avatar'] = false;
}
else
{
	$show['avatar'] = true;
}

$show['lastpost'] = false;
// GET LAST POST
if ($userinfo['posts'] AND $userinfo['lastpost'])
{
	if ($vboptions['profilelastpost'])
	{
		$show['lastpost'] = true;
		$userinfo['lastpostdate'] = vbdate($vboptions['dateformat'], $userinfo['lastpost']);
		$userinfo['lastposttime'] = vbdate($vboptions['timeformat'], $userinfo['lastpost']);

		$getlastposts = $DB_site->query("
			SELECT thread.title, thread.threadid, thread.forumid, postid, post.dateline
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS delpost ON(delpost.primaryid = post.postid AND delpost.type = 'post')
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS delthread ON(delthread.primaryid = thread.threadid AND delthread.type = 'thread')
			WHERE thread.visible = 1
				AND post.userid = $userid
				AND delpost.primaryid IS NULL
				AND delthread.primaryid IS NULL
			ORDER BY post.dateline DESC
			LIMIT 20
		");
		while ($getlastpost = $DB_site->fetch_array($getlastposts))
		{
			$getperms = fetch_permissions($getlastpost['forumid']);
			if ($getperms & CANVIEW)
			{
				$userinfo['lastposttitle'] = $getlastpost['title'];
				$userinfo['lastposturl'] = "showthread.php?$session[sessionurl]p=$getlastpost[postid]#post$getlastpost[postid]";
				$userinfo['lastpostdate'] = vbdate($vboptions['dateformat'], $getlastpost['dateline']);
				$userinfo['lastposttime'] = vbdate($vboptions['timeformat'], $getlastpost['dateline']);
				break;
			}
		}
	}
}
else
{
	$show['lastpost'] = true;
	$userinfo['lastposttitle'] = '';
	$userinfo['lastposturl'] = '#';
	$userinfo['lastpostdate'] = $vbphrase['never'];
	$userinfo['lastposttime'] = '';
}

// reputation
fetch_reputation_image($userinfo, $userperms);

// signature
if ($userinfo['signature'])
{
	require_once('./includes/functions_bbcodeparse.php');
	$userinfo['signature'] = parse_bbcode($userinfo['signature'], 0, 1);
	$show['signature'] = true;
}
else
{
	$show['signature'] = false;
}

// REFERRALS
if ($vboptions['usereferrer'])
{
	$refcount = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "user WHERE referrerid = $userinfo[userid]");
	$referrals = vb_number_format($refcount['count']);
}

// extra info panel
$show['extrainfo'] = false;

// BIRTHDAY
// Set birthday fields right here!
if ($userinfo['birthday'])
{
	$bday = explode('-', $userinfo['birthday']);
	if (date('Y') > $bday[2] AND $bday[2] > 1901 AND $bday[2] != '0000')
	{
		require_once('./includes/functions_misc.php');
		$vboptions['calformat1'] = mktimefix($vboptions['calformat1'], $bday[2]);
		if ($bday[2] >= 1970)
		{
			$yearpass = $bday[2];
		}
		else
		{
			// day of the week patterns repeat every 28 years, so
			// find the first year >= 1970 that has this pattern
			$yearpass = $bday[2] + 28 * ceil((1970 - $bday[2]) / 28);
		}
		$userinfo['birthday'] = vbdate($vboptions['calformat1'], mktime(0, 0, 0, $bday[0], $bday[1], $yearpass), false, true, false);
	}
	else
	{
		// lets send a valid year as some PHP3 don't like year to be 0
		$userinfo['birthday'] = vbdate($vboptions['calformat2'], mktime(0, 0, 0, $bday[0], $bday[1], 1992), false, true, false);
	}
	if ($userinfo['birthday'] == '')
	{
		if ($bday[2] == '0000')
		{
			$userinfo['birthday'] = "$bday[0]-$bday[1]";
		}
		else
		{
			$userinfo['birthday'] = "$bday[0]-$bday[1]-$bday[2]";
		}
	}
	$show['extrainfo'] = true;
}

// *********************
// CUSTOM PROFILE FIELDS
$profilefields = $DB_site->query("
	SELECT profilefieldid, required, title, type, data, def, height
	FROM " . TABLE_PREFIX . "profilefield
	WHERE form = 0 " . iif(!($permissions['genericpermissions'] & CANSEEHIDDENCUSTOMFIELDS), "
		AND hidden = 0") . "
	ORDER BY displayorder
");

$search = array(
	'#(\r\n|\n|\r)#',
	'#(<br />){3,}#', // Replace 3 or more <br /> with two <br />
);
$replace = array(
	'<br />',
	'<br /><br />',
);

while ($profilefield = $DB_site->fetch_array($profilefields))
{
	exec_switch_bg();
	$profilefieldname = "field$profilefield[profilefieldid]";
	if ($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple')
	{
		$data = unserialize($profilefield['data']);
		foreach ($data AS $key => $val)
		{
			if ($userinfo["$profilefieldname"] & pow(2, $key))
			{
				$profilefield['value'] .= iif($profilefield['value'], ', ') . $val;
			}
		}
	}
	else if ($profilefield['type'] == 'textarea')
	{
		$profilefield['value'] = preg_replace($search, $replace, trim($userinfo["$profilefieldname"]));
	}
	else
	{
		$profilefield['value'] = $userinfo["$profilefieldname"];
	}
	if ($profilefield['value'] != '')
	{
		$show['extrainfo'] = true;
	}
	eval('$customfields .= "' . fetch_template('memberinfo_customfields') . '";');

}
// END CUSTOM PROFILE FIELDS
// *************************

$buddylist = explode(' ', trim($bbuserinfo['buddylist']));
$ignorelist = explode(' ', trim($bbuserinfo['ignorelist']));
if (!in_array($userinfo['userid'], $ignorelist))
{
	$show['addignorelist'] = true;
}
else
{
	$show['addignorelist'] = false;
}
if (!in_array($userinfo['userid'], $buddylist))
{
	$show['addbuddylist'] = true;
}
else
{
	$show['addbuddylist'] = false;
}

// Used in template conditional
$show['currentlocation'] = iif($permissions['wolpermissions'] & CANWHOSONLINE, true, false);

// get IDs of all member groups
$membergroups = fetch_membergroupids_array($userinfo);

$membergroupbits = '';
foreach ($membergroups AS $usergroupid)
{
	$usergroup = &$usergroupcache["$usergroupid"];
	if ($usergroup['ispublicgroup'])
	{
		exec_switch_bg();
		eval('$membergroupbits .= "' . fetch_template('memberinfo_membergroupbit') . '";');
	}
}

$show['membergroups'] = iif($membergroupbits != '', true, false);

$show['profilelinks'] = iif($show['member'] OR $userinfo['showvcard'], true, false);

$navbits = construct_navbits(array(
	"member.php?$session[sessionurl]u=$userinfo[userid]" => $vbphrase['view_profile'],
	'' => $userinfo['username']
));
eval('$navbar = "' . fetch_template('navbar') . '";');

$bgclass = 'alt2';
$bgclass1 = 'alt1';

$templatename = iif($quick, 'memberinfo_quick', 'MEMBERINFO');

eval('print_output("' . fetch_template($templatename) . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11291 $
|| ####################################################################
\*======================================================================*/
?>