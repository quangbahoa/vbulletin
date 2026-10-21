<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'member');
define('BYPASS_STYLE_OVERRIDE', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'wol',
	'user',
	'messaging'
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
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
	'im_skype',
	'bbcode_code',
	'bbcode_html',
	'bbcode_php',
	'bbcode_quote',
	'postbit_reputation',
	'postbit_onlinestatus',
	'userfield_checkbox_option',
	'userfield_select_option'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

if ($_REQUEST['do'] == 'vcard') // don't alter this $_REQUEST
{
	define('NOHEADER', 1);
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_postbit.php');
require_once(DIR . '/includes/functions_user.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']))
{
	print_no_permission();
}


$vbulletin->input->clean_array_gpc('r', array(
	'find' => TYPE_STR,
	'moderatorid' => TYPE_UINT,
	'userid' => TYPE_UINT,
	'username' => TYPE_NOHTML
));

($hook = vBulletinHook::fetch_hook('member_start')) ? eval($hook) : false;

if ($vbulletin->GPC['find'] == 'firstposter' AND $threadinfo['threadid'])
{
	if ((!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')) OR ($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'])))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
	}
	if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
	}

	$forumperms = fetch_permissions($threadinfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		print_no_permission();
	}
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
	{
		print_no_permission();
	}

	$vbulletin->GPC['userid'] = $threadinfo['postuserid'];
}
else if ($vbulletin->GPC['find'] == 'lastposter' AND $threadinfo['threadid'])
{
	if ((!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')) OR ($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'])))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
	}
	if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
	}

	$forumperms = fetch_permissions($threadinfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		print_no_permission();
	}
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
	{
		print_no_permission();
	}

	require_once(DIR . '/includes/functions_bigthree.php');
	$coventry = fetch_coventry('string');

	$getuserid = $db->query_first("
		SELECT post.userid
		FROM " . TABLE_PREFIX . "post AS post
		WHERE post.threadid = $threadinfo[threadid]
			AND post.visible = 1
			". ($coventry ? "AND post.userid NOT IN ($coventry)" : '') . "
		ORDER BY dateline DESC
		LIMIT 1
	");
	$vbulletin->GPC['userid'] = $getuserid['userid'];
}
else if ($vbulletin->GPC['find'] == 'lastposter' AND $foruminfo['forumid'])
{
	$_permsgetter_ = 'lastposter fperms';
	$forumperms = fetch_permissions($foruminfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		print_no_permission();
	}

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
	{
		$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
			"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
	}
	else
	{
		$tachyjoin = '';
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	require_once(DIR . '/includes/functions_misc.php');
	$forumslist = $forumid . ',' . fetch_child_forums($foruminfo['forumid']);

	require_once(DIR . '/includes/functions_bigthree.php');
	// this isn't including moderator checks, because the last post checks don't either
	if ($coventry = fetch_coventry('string')) // takes self into account
	{
		$globalignore_post = "AND post.userid NOT IN ($coventry)";
		$globalignore_thread = "AND thread.postuserid NOT IN ($coventry)";
	}
	else
	{
		$globalignore_post = '';
		$globalignore_thread = '';
	}

	require_once(DIR . '/includes/functions_forumlist.php');
	cache_ordered_forums(1);

	$datecutoff = $vbulletin->forumcache["$foruminfo[forumid]"]['lastpost'] - 30;

	$thread = $db->query_first("
		SELECT thread.threadid
			" . ($tachyjoin ? ', IF(tachythreadpost.lastpost > thread.lastpost, tachythreadpost.lastpost, thread.lastpost) AS lastpost' : '') . "
		FROM " . TABLE_PREFIX . "thread AS thread
		$tachyjoin
		WHERE thread.forumid IN ($forumslist)
			AND thread.visible = 1
			AND thread.sticky IN (0,1)
			AND thread.open <> 10
			" . (!$tachyjoin ? "AND lastpost > $datecutoff" : '') . "
			$globalignore_thread
		ORDER BY lastpost DESC
		LIMIT 1
	");

	if (!$thread)
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['user'], $vbulletin->options['contactuslink'])));
	}

	$getuserid = $db->query_first("
		SELECT post.userid
		FROM " . TABLE_PREFIX . "post AS post
		WHERE threadid = $thread[threadid]
			AND visible = 1
			$globalignore_post
		ORDER BY dateline DESC
		LIMIT 1
	");

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($getuserid['userid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
	{
		print_no_permission();
	}

	$vbulletin->GPC['userid'] = $getuserid['userid'];
}
else if ($vbulletin->GPC['find'] == 'moderator' AND $vbulletin->GPC['moderatorid'])
{
	$moderatorinfo = verify_id('moderator', $vbulletin->GPC['moderatorid'], 1, 1);
	$vbulletin->GPC['userid'] = $moderatorinfo['userid'];
}
else if ($vbulletin->GPC['username'] != '' AND !$vbulletin->GPC['userid'])
{
	$user = $db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . $db->escape_string($vbulletin->GPC['username']) . "'");
	$vbulletin->GPC['userid'] = $user['userid'];
}

if (!$vbulletin->GPC['userid'])
{
	eval(standard_error(fetch_error('unregistereduser')));
}

$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1, 15);


if ($userinfo['usergroupid'] == 4 AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
{
	print_no_permission();
}

if ($_REQUEST['do'] == 'vcard' AND $vbulletin->userinfo['userid'] AND $userinfo['showvcard'])
{
	// source: http://www.ietf.org/rfc/rfc2426.txt
	$text = "BEGIN:VCARD\r\n";
	$text .= "VERSION:2.1\r\n";
	$text .= "N:;$userinfo[username]\r\n";
	$text .= "FN:$userinfo[username]\r\n";
	$text .= "EMAIL;PREF;INTERNET:$userinfo[email]\r\n";
	if (!empty($userinfo['birthday'][7]) AND $userinfo['showbirthday'] == 2)
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

if ($userperms['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canbeusernoted'])
{
	# User has permission to view self or others
	if
		(
				($userinfo['userid'] == $vbulletin->userinfo['userid'] AND $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewownusernotes'])
			OR 	($userinfo['userid'] != $vbulletin->userinfo['userid'] AND $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewothersusernotes'])
		)
	{
		$show['usernotes'] = true;
		$usernote = $db->query_first("
			SELECT MAX(dateline) AS lastpost, COUNT(*) AS total
			FROM " . TABLE_PREFIX . "usernote AS usernote
			WHERE userid = $userinfo[userid]
		");
		$show['usernoteview'] = intval($usernote['total']) ? true : false;

		$usernote['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $usernote['lastpost'], true);
		$usernote['lastposttime'] = vbdate($vbulletin->options['timeformat'], $usernote['lastpost'], true);
	}
	# User has permission to post about self or others

	if
		(
				($userinfo['userid'] == $vbulletin->userinfo['userid'] AND $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canpostownusernotes'])
			OR 	($userinfo['userid'] != $vbulletin->userinfo['userid'] AND $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canpostothersusernotes'])
		)
	{
		$show['usernotes'] = true;
		$show['usernotepost'] = true;
	}
}

// PROFILE PIC
$show['profilepic'] = iif($vbulletin->options['profilepicenabled'] AND $userinfo['profilepic'] AND ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseeprofilepic'] OR $vbulletin->userinfo['userid'] == $userinfo['userid']), true, false);

if ($vbulletin->options['usefileavatar'])
{
	$userinfo['profilepicurl'] = $vbulletin->options['profilepicurl'] . '/profilepic' . $userinfo['userid'] . '_' . $userinfo['profilepicrevision'] . '.gif';
}
else
{
	$userinfo['profilepicurl'] = 'image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $userinfo['userid'] . "&amp;dateline=$userinfo[profilepicdateline]&amp;type=profile";
}

if ($userinfo['ppwidth'] AND $userinfo['ppheight'])
{
	$userinfo['profilepicsize'] = " width=\"$userinfo[ppwidth]\" height=\"$userinfo[ppheight]\" ";
}

// CUSTOM TITLE
if ($userinfo['customtitle'] == 2)
{
	$userinfo['usertitle'] = htmlspecialchars_uni($userinfo['usertitle']);
}

// LAST ACTIVITY AND LAST VISIT
if (!$userinfo['invisible'] OR ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehidden']) OR $userinfo['userid'] == $vbulletin->userinfo['userid'])
{
	$show['lastactivity'] = true;
	$userinfo['lastactivitydate'] = vbdate($vbulletin->options['dateformat'], $userinfo['lastactivity'], true);
	$userinfo['lastactivitytime'] = vbdate($vbulletin->options['timeformat'], $userinfo['lastactivity'], true);
}
else
{
	$show['lastactivity'] = false;
	$userinfo['lastactivitydate'] = '';
	$userinfo['lastactivitytime'] = '';
}

// Get Rank
$post =& $userinfo;

// JOIN DATE & POSTS PER DAY
$userinfo['datejoined'] = vbdate($vbulletin->options['dateformat'], $userinfo['joindate']);
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

// EMAIL
$show['email'] = iif($vbulletin->options['enableemail'] AND $vbulletin->options['displayemails'], true, false);

// HOMEPAGE
$show['homepage'] = iif($userinfo['homepage'] != 'http://' AND $userinfo['homepage'] != '', true, false);

// PRIVATE MESSAGE
$show['pm'] = iif($userinfo['receivepm'] AND $userperms['pmquota'] > 0, true, false);

// IM icons
construct_im_icons($userinfo, true);
if (!$vbulletin->options['showimicons'])
{
	$show['textimicons'] = true;
}

// AVATAR
$avatarurl = fetch_avatar_url($userinfo['userid']);

if ($avatarurl == '' OR !$vbulletin->options['avatarenabled'])
{
	$show['avatar'] = false;
}
else
{
	$show['avatar'] = true;
	$userinfo['avatarsize'] = $avatarurl[1];
	$userinfo['avatarurl'] = $avatarurl[0];
}

$show['lastpost'] = false;
// GET LAST POST
if ($vbulletin->options['profilelastpost'] AND $userinfo['lastpost'])
{
	if (!in_coventry($userinfo['userid']))
	{
		$getlastposts = $db->query_read("
			SELECT thread.title, thread.threadid, thread.forumid, post.postid, post.dateline
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE thread.visible = 1
				AND post.userid =  $userinfo[userid]
				AND post.visible = 1
			ORDER BY post.dateline DESC
			LIMIT 20
		");
		while ($getlastpost = $db->fetch_array($getlastposts))
		{
			$getperms = fetch_permissions($getlastpost['forumid']);
			if ($getperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			{
				$show['lastpost'] = true;
				$userinfo['lastposttitle'] = $getlastpost['title'];
				$userinfo['lastposturl'] = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$getlastpost[postid]#post$getlastpost[postid]";
				$userinfo['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $getlastpost['dateline'], true);
				$userinfo['lastposttime'] = vbdate($vbulletin->options['timeformat'], $getlastpost['dateline']);
				break;
			}
		}
	}

	if (!$show['lastpost'])
	{
		$show['lastpost'] = true;
		$userinfo['lastposttitle'] = '';
		$userinfo['lastposturl'] = '#';
		$userinfo['lastpostdate'] = $vbphrase['never'];
		$userinfo['lastposttime'] = '';
	}
}

// reputation
fetch_reputation_image($userinfo, $userperms);

// signature
if ($userinfo['signature'])
{
	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
	$userinfo['signature'] = $bbcode_parser->parse($userinfo['signature'], 0, true);

	$show['signature'] = true;
}
else
{
	$show['signature'] = false;
}

// REFERRALS
if ($vbulletin->options['usereferrer'])
{
	$refcount = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "user WHERE referrerid = $userinfo[userid]");
	$referrals = vb_number_format($refcount['count']);
}

// extra info panel
$show['extrainfo'] = false;

// BIRTHDAY
// Set birthday fields right here!
if ($userinfo['birthday'] AND $userinfo['showbirthday'] > 0)
{
	$bday = explode('-', $userinfo['birthday']);

	$year = vbdate('Y', TIMENOW, false, false);
	$month = vbdate('n', TIMENOW, false, false);
	$day = vbdate('j', TIMENOW, false, false);
	if ($year > $bday[2] AND $bday[2] != '0000')
	{
		$userinfo['age'] = $year - $bday[2];
		if ($month < $bday[0] OR ($month == $bday[0] AND $day < $bday[1]))
		{
			$userinfo['age']--;
		}

		if ($userinfo['age'] > 101)
		{	// why can't we have 102 year old forum users?
			$show['age'] = false;
		}
		else
		{
			$show['age'] = true;
			$show['extrainfo'] = true;
		}
	}

	if ($userinfo['showbirthday'] == 2)
	{
		if ($year > $bday[2] AND $bday[2] > 1901 AND $bday[2] != '0000')
		{
			require_once(DIR . '/includes/functions_misc.php');
			$vbulletin->options['calformat1'] = mktimefix($vbulletin->options['calformat1'], $bday[2]);
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
			$userinfo['birthday'] = vbdate($vbulletin->options['calformat1'], mktime(0, 0, 0, $bday[0], $bday[1], $yearpass), false, true, false);
		}
		else
		{
			// lets send a valid year as some PHP3 don't like year to be 0
			$userinfo['birthday'] = vbdate($vbulletin->options['calformat2'], mktime(0, 0, 0, $bday[0], $bday[1], 1992), false, true, false);
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
		$show['birthday'] = true;
	}
	else
	{
		$show['birthday'] = false;
	}
}

// *********************
// CUSTOM PROFILE FIELDS
$profilefields = $db->query_read("
	SELECT profilefieldid, required, title, type, data, def, height
	FROM " . TABLE_PREFIX . "profilefield
	WHERE form = 0 " . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), "
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

$customfields = '';
while ($profilefield = $db->fetch_array($profilefields))
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

	($hook = vBulletinHook::fetch_hook('member_customfields')) ? eval($hook) : false;

	if ($profilefield['value'] != '')
	{
		$show['extrainfo'] = true;
		eval('$customfields .= "' . fetch_template('memberinfo_customfields') . '";');
	}

}
// END CUSTOM PROFILE FIELDS
// *************************

require_once(DIR . '/includes/functions_bigthree.php');
fetch_online_status($userinfo, true);

$buddylist = explode(' ', trim($vbulletin->userinfo['buddylist']));
$ignorelist = explode(' ', trim($vbulletin->userinfo['ignorelist']));
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
if ($vbulletin->options['WOLenable'] AND $userinfo['action'] AND $permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonline'])
{
	$show['currentlocation'] = true;
}

// get IDs of all member groups
$membergroups = fetch_membergroupids_array($userinfo);

$membergroupbits = '';
foreach ($membergroups AS $usergroupid)
{
	$usergroup =& $vbulletin->usergroupcache["$usergroupid"];
	if ($usergroup['ispublicgroup'])
	{
		exec_switch_bg();
		eval('$membergroupbits .= "' . fetch_template('memberinfo_membergroupbit') . '";');
	}
}

$show['membergroups'] = iif($membergroupbits != '', true, false);
$show['profilelinks'] = iif($show['member'] OR $userinfo['showvcard'], true, false);
$show['contactlinks'] = iif($show['email'] OR $show['pm'] OR $show['homepage'] OR $show['hasimicons'], true, false);

$navbits = construct_navbits(array(
	'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => $vbphrase['view_profile'],
	'' => $userinfo['username']
));
eval('$navbar = "' . fetch_template('navbar') . '";');

$bgclass = 'alt2';
$bgclass1 = 'alt1';

$templatename = iif($quick, 'memberinfo_quick', 'MEMBERINFO');

($hook = vBulletinHook::fetch_hook('member_complete')) ? eval($hook) : false;

eval('print_output("' . fetch_template($templatename) . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13624 $
|| ####################################################################
\*======================================================================*/
?>