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
define('THIS_SCRIPT', 'showgroups');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'SHOWGROUPS',
	'showgroups_forumbit',
	'showgroups_usergroup',
	'showgroups_usergroupbit',
	'postbit_onlinestatus'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// get the fieldid of the location field
if ($field = $DB_site->query_first("SELECT profilefieldid FROM " . TABLE_PREFIX . "profilefield WHERE profilefieldid=2 OR title='Location'"))
{
	$locationfieldselect = 'userfield.field' . $field['profilefieldid'] . ',';
	$locationfieldid = $field['profilefieldid'];
	$show['locationfield'] = true;
}
else
{
	$locationfieldselect = '';
	$locationfieldid = 0;
	$show['locationfield'] = false;
}

function process_showgroups_userinfo($user)
{
	global $vboptions, $locationfieldid, $permissions, $stylevar, $show;

	$post = &$user;
	$datecut = TIMENOW - $vboptions['cookietimeout'];

	if (empty($user['field' . $locationfieldid]) OR !$show['locationfield'])
	{
		$show['location'] = false;
		$user['location'] = '';
	}
	else
	{
		$show['location'] = true;
		$user['location'] = $user['field' . $locationfieldid];
	}

	require_once('./includes/functions_bigthree.php');
	fetch_online_status($user, true);

	if ((!$user['invisible'] OR $permissions['genericpermissions'] & CANSEEHIDDEN))
	{
		$user['lastonline'] = vbdate($vboptions['dateformat'], $user['lastactivity'], 1);
	}
	else
	{
		$user['lastonline'] = '&nbsp;';
	}

	$user['musername'] = fetch_musername($user, iif($user['displaygroupid'], 'displaygroupid', 'usergroupid'));

	return $user;
}

function print_users($usergroupid, $userarray)
{
	global $bgclass, $vbphrase;
	$out = '';
	uksort($userarray, 'strnatcasecmp'); // alphabetically sort usernames
	foreach ($userarray AS $user)
	{
		exec_switch_bg();
		$user = process_showgroups_userinfo($user);
		eval('$out .= "' . fetch_template('showgroups_adminbit') . '";');
	}
	return $out;
}

if (!($permissions & CANVIEW))
{
	print_no_permission();
}

require_once('./includes/functions_databuild.php');
cache_forums();

construct_forum_jump();

// get usergroups who should be displayed on showgroups
// Scans too many rows. Usergroup Rows * User Rows
$users = $DB_site->query("
	SELECT $locationfieldselect user.*, usergroup.usergroupid, usergroup.title, user.options
	FROM " . TABLE_PREFIX . "usergroup AS usergroup
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
	LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
	WHERE (usergroup.genericoptions & " . SHOWGROUP . ")
");

$groupcache = array();
while ($user = $DB_site->fetch_array($users))
{
	$user = array_merge($user , convert_bits_to_array($user['options'], $_USEROPTIONS));

	if ($user['userid'])
	{
		$t = strtoupper($user['title']);
		$u = strtoupper($user['username']);
		$groupcache["$t"]["$u"] = $user;
	}
}

$usergroups = '';
if (sizeof($groupcache) >= 1)
{
	ksort($groupcache); // alphabetically sort usergroups
	foreach ($groupcache AS $users)
	{
		ksort($users); // alphabetically sort users
		$usergroupbits = '';
		foreach ($users AS $user)
		{
			exec_switch_bg();
			$user = process_showgroups_userinfo($user);

			if ($user['receivepm'] AND $bbuserinfo['receivepm'] AND $permissions['pmquota'] AND $vboptions['enablepms'])
			{
				$show['pmlink'] = true;
			}
			else
			{
				$show['pmlink'] = false;
			}

			if ($user['showemail'] AND $vboptions['displayemails'] AND (!$vboptions['secureemail'] OR ($vboptions['secureemail'] AND $vboptions['enableemail'])))
			{
				$show['emaillink'] = true;
			}
			else
			{
				$show['emaillink'] = false;
			}
			eval('$usergroupbits .= "' . fetch_template('showgroups_usergroupbit') . '";');
		}
		eval('$usergroups .= "' . fetch_template('showgroups_usergroup') . '";');
	}
}

// get moderators **********************************************************
$moderators = $DB_site->query("
	SELECT $locationfieldselect user.*,moderator.*
	FROM " . TABLE_PREFIX . "moderator AS moderator
	INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
	INNER JOIN " . TABLE_PREFIX . "userfield AS userfield USING(userid)
	#ORDER BY user.username
");
$modcache = array();
while ($moderator = $DB_site->fetch_array($moderators))
{
	if (!isset($modcache["$moderator[username]"]))
	{
		$modcache["$moderator[username]"] = $moderator;
	}
	$modcache["$moderator[username]"]['forums'][] = $moderator['forumid'];
}
unset($moderator);
$DB_site->free_result($moderators);

if (is_array($modcache))
{
	$showforums = true;
	uksort($modcache, 'strnatcasecmp'); // alphabetically sort moderator usernames
	foreach ($modcache AS $moderator)
	{
		$premodforums = array();
		foreach ($moderator['forums'] AS $forumid)
		{
			if ($forumcache["$forumid"]['options'] & $_FORUMOPTIONS['active'] AND (!$vboptions['hideprivateforums'] OR ($bbuserinfo['forumpermissions']["$forumid"] & CANVIEW)))
			{
				$forumtitle = $forumcache["$forumid"]['title'];
				$premodforums[$forumid] = $forumtitle;
			}
		}
		if (empty($premodforums))
		{
			continue;
		}
		$modforums = array();
		uasort($premodforums, 'strnatcasecmp'); // alphabetically sort moderator usernames
		foreach($premodforums AS $forumid => $forumtitle)
		{
			eval('$modforums[] = "' . fetch_template('showgroups_forumbit') . '";');
		}
		$user = $moderator;
		$user = array_merge($user , convert_bits_to_array($user['options'], $_USEROPTIONS));
		$user = process_showgroups_userinfo($user);
		$user['forumbits'] = implode(",\n", $modforums);

		if ($user['receivepm'] AND $bbuserinfo['receivepm'] AND $permissions['pmquota'] AND $vboptions['enablepms'])
		{
			$show['pmlink'] = true;
		}
		else
		{
			$show['pmlink'] = false;
		}

		if ($user['showemail'] AND $vboptions['displayemails'] AND (!$vboptions['secureemail'] OR ($vboptions['secureemail'] AND $vboptions['enableemail'])))
		{
			$show['emaillink'] = true;
		}
		else
		{
			$show['emaillink'] = false;
		}

		exec_switch_bg();
		eval('$moderatorbits .= "' . fetch_template('showgroups_usergroupbit') . '";');
	}
}

// *******************************************************

$navbits = construct_navbits(array('' => $vbphrase['show_groups']));
eval('$navbar = "' . fetch_template('navbar') . '";');

eval('print_output("' . fetch_template('SHOWGROUPS') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 10857 $
|| ####################################################################
\*======================================================================*/
?>