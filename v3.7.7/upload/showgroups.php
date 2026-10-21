<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'showgroups');
define('CSRF_PROTECTION', true);

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

if (!$vbulletin->options['forumleaders'])
{
	print_no_permission();
}

$show['locationfield'] = true;
$show['contactinfo'] = (bool)$vbulletin->userinfo['userid'];

function process_showgroups_userinfo($user)
{
	global $vbulletin, $permissions, $stylevar, $show;

	$post =& $user;
	$datecut = TIMENOW - $vbulletin->options['cookietimeout'];

	require_once(DIR . '/includes/functions_bigthree.php');
	fetch_online_status($user, true);

	if ((!$user['invisible'] OR $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehidden']))
	{
		$user['lastonline'] = vbdate($vbulletin->options['dateformat'], $user['lastactivity'], 1);
	}
	else
	{
		$user['lastonline'] = '&nbsp;';
	}

	fetch_musername($user);

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

if (!($permissions & $vbulletin->bf_ugp_forumpermissions['canview']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('showgroups_start')) ? eval($hook) : false;

//require_once(DIR . '/includes/functions_databuild.php');
//cache_forums();

construct_forum_jump();

// get usergroups who should be displayed on showgroups
// Scans too many rows. Usergroup Rows * User Rows
$users = $db->query_read_slave("
	SELECT user.*, usergroup.usergroupid, usergroup.title, user.options, usertextfield.*, userfield.*,
	IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
	FROM " . TABLE_PREFIX . "user AS user
	LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON(usergroup.usergroupid = user.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
	LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
	LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid=user.userid)
	WHERE (usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['showgroup'] . ")
");

$groupcache = array();
while ($user = $db->fetch_array($users))
{
	$user = array_merge($user , convert_bits_to_array($user['options'], $vbulletin->bf_misc_useroptions));
	$user = array_merge($user , convert_bits_to_array($user['adminoptions'] , $vbulletin->bf_misc_adminoptions));
	cache_permissions($user, false);

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

			if ($vbulletin->options['enablepms'] AND $vbulletin->userinfo['permissions']['pmquota'] AND ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
	 				OR ($user['receivepm'] AND $user['permissions']['pmquota']
	 				AND (!$user['receivepmbuddies'] OR can_moderate() OR strpos(" $user[buddylist] ", ' ' . $vbulletin->userinfo['userid'] . ' ') !== false))
	 		))
			{
				$show['pmlink'] = true;
			}
			else
			{
				$show['pmlink'] = false;
			}

			if ($user['showemail'] AND $vbulletin->options['displayemails'] AND (!$vbulletin->options['secureemail'] OR ($vbulletin->options['secureemail'] AND $vbulletin->options['enableemail'])) AND $vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canemailmember'] AND $vbulletin->userinfo['userid'])
			{
				$show['emaillink'] = true;
			}
			else
			{
				$show['emaillink'] = false;
			}

			($hook = vBulletinHook::fetch_hook('showgroups_user')) ? eval($hook) : false;
			eval('$usergroupbits .= "' . fetch_template('showgroups_usergroupbit') . '";');
		}

		($hook = vBulletinHook::fetch_hook('showgroups_usergroup')) ? eval($hook) : false;
		eval('$usergroups .= "' . fetch_template('showgroups_usergroup') . '";');
	}
}

if ($vbulletin->options['forumleaders'] == 1)
{
	// get moderators **********************************************************
	$moderators = $db->query_read_slave("
		SELECT user.*, moderator.forumid, usertextfield.*, userfield.*,
		IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
		FROM " . TABLE_PREFIX . "moderator AS moderator
		INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		INNER JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
		INNER JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid=user.userid)
		WHERE moderator.forumid <> -1
	");
	$modcache = array();
	while ($moderator = $db->fetch_array($moderators))
	{
		if (!isset($modcache["$moderator[username]"]))
		{
			$modcache["$moderator[username]"] = $moderator;
		}
		$modcache["$moderator[username]"]['forums'][] = $moderator['forumid'];
	}
	unset($moderator);
	$db->free_result($moderators);

	if (is_array($modcache))
	{
		$showforums = true;
		uksort($modcache, 'strnatcasecmp'); // alphabetically sort moderator usernames
		foreach ($modcache AS $moderator)
		{
			$premodforums = array();
			foreach ($moderator['forums'] AS $forumid)
			{
				if ($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['active'] AND (($vbulletin->forumcache["$forumid"]['showprivate'] > 1 OR (!$vbulletin->forumcache["$forumid"]['showprivate'] AND $vbulletin->options['showprivateforums'])) OR ($vbulletin->userinfo['forumpermissions']["$forumid"] & $vbulletin->bf_ugp_forumpermissions['canview'])))
				{
					$forumtitle = $vbulletin->forumcache["$forumid"]['title'];
					$premodforums["$forumid"] = $forumtitle;
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
				($hook = vBulletinHook::fetch_hook('showgroups_forum')) ? eval($hook) : false;
				eval('$modforums[] = "' . fetch_template('showgroups_forumbit') . '";');
			}
			$user = $moderator;
			$user = array_merge($user , convert_bits_to_array($user['options'], $vbulletin->bf_misc_useroptions));
			$user = array_merge($user , convert_bits_to_array($user['adminoptions'] , $vbulletin->bf_misc_adminoptions));
			cache_permissions($user, false);

			$user = process_showgroups_userinfo($user);
			$user['forumbits'] = implode(",\n", $modforums);

			if ($vbulletin->options['enablepms'] AND $vbulletin->userinfo['permissions']['pmquota'] AND ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
	 				OR ($user['receivepm'] AND $user['permissions']['pmquota']
	 				AND (!$user['receivepmbuddies'] OR can_moderate() OR strpos(" $user[buddylist] ", ' ' . $vbulletin->userinfo['userid'] . ' ') !== false))
	 		))
			{
				$show['pmlink'] = true;
			}
			else
			{
				$show['pmlink'] = false;
			}

			if ($user['showemail'] AND $vbulletin->options['displayemails'] AND (!$vbulletin->options['secureemail'] OR ($vbulletin->options['secureemail'] AND $vbulletin->options['enableemail'])) AND $vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canemailmember'] AND $vbulletin->userinfo['userid'])
			{
				$show['emaillink'] = true;
			}
			else
			{
				$show['emaillink'] = false;
			}

			exec_switch_bg();

			($hook = vBulletinHook::fetch_hook('showgroups_usergroup')) ? eval($hook) : false;
			eval('$moderatorbits .= "' . fetch_template('showgroups_usergroupbit') . '";');
		}
	}
}

// *******************************************************

$navbits = construct_navbits(array('' => $vbphrase['show_groups']));
eval('$navbar = "' . fetch_template('navbar') . '";');

($hook = vBulletinHook::fetch_hook('showgroups_complete')) ? eval($hook) : false;

eval('print_output("' . fetch_template('SHOWGROUPS') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 31700 $
|| ####################################################################
\*======================================================================*/
?>