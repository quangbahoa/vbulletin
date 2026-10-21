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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 9062 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cppermission');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_misc.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminforums'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif(isset($_REQUEST['userid']), "user id = $_REQUEST[userid]", iif(isset($_REQUEST['usergroupid']), "usergroup id = $_REQUEST[usergroupid]", iif(isset($_REQUEST['forumid']), "forum id = $_REQUEST[forumid]"))));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['view_permissions']);

$perm_phrase = array(
	'canview'		=> $vbphrase['can_view_forum'],
	'canviewothers'		=> $vbphrase['can_view_others_threads'],
	'cansearch'		=> $vbphrase['can_search_forum'],
	'canemail'		=> $vbphrase['can_use_email_to_friend'],
	'canpostnew'		=> $vbphrase['can_post_threads'],
	'canreplyown'		=> $vbphrase['can_reply_to_own_threads'],
	'canreplyothers'	=> $vbphrase['can_reply_to_others_threads'],
	'caneditpost'		=> $vbphrase['can_edit_own_posts'],
	'candeletepost'		=> $vbphrase['can_delete_own_posts'],
	'candeletethread'	=> $vbphrase['can_delete_own_threads'],
	'canopenclose'		=> $vbphrase['can_open_close_own_threads'],
	'canmove'		=> $vbphrase['can_move_own_threads'],
	'cangetattachment'	=> $vbphrase['can_view_attachments'],
	'canpostattachment'	=> $vbphrase['can_post_attachments'],
	'canpostpoll'		=> $vbphrase['can_post_polls'],
	'canvote'		=> $vbphrase['can_vote_on_polls'],
	'canthreadrate'		=> $vbphrase['can_rate_threads'],
	'canseedelnotice' 	=> $vbphrase['can_see_deletion_notices'],
	'isalwaysmoderated'	=> $vbphrase['always_moderate_posts']
);

//build a nice array with permission names
foreach ($_BITFIELD['usergroup']['forumpermissions'] AS $key => $val)
{
	$bitfieldnames["$val"] = $perm_phrase["$key"];
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'index';
}

// ###################### Start index ########################
if ($_REQUEST['do'] == 'index')
{

	require_once('./includes/functions_databuild.php');
	cache_forums();
	print_form_header('resources', 'view');
	print_table_header($vbphrase['view_forum_permissions']);
	print_forum_chooser('forumid', '', "($vbphrase[forum])", $vbphrase['forum']);
	print_chooser_row($vbphrase['usergroup'], 'usergroupid', 'usergroup', '', "($vbphrase[usergroup])");
	print_label_row(
		$vbphrase['forum_permissions'],
		'<label for="cb_checkall"><input type="checkbox" id="cb_checkall" name="allbox" onclick="js_check_all(this.form)" />' . $vbphrase['check_all'] . '</label>',
		'thead'
	);
	foreach ($_BITFIELD['usergroup']['forumpermissions'] AS $field => $value)
	{
		print_checkbox_row($perm_phrase["$field"], "checkperm[$value]", false, $value);
	}
	print_submit_row($vbphrase['find']);

}

// ###################### Start viewing resources for forums or usergroups ########################
if ($_REQUEST['do'] == 'view')
{
	globalize($_POST, array('forumid', 'usergroupid', 'checkperm'));

	if ($forumid == -1 AND $usergroupid == -1)
	{
		print_stop_message('you_must_pick_a_usergroup_or_forum_to_check_permissions');
	}
	if (!is_array($checkperm))
	{
		$checkperm[] = 1;
	}
	$fpermscache = array();
	$_PERMQUERY = "
	### FORUM PERMISSIONS QUERY ###
	SELECT forumpermission.usergroupid, forumpermission.forumpermissions, forum.forumid, forum.title
	FROM " . TABLE_PREFIX . "forum AS forum
	LEFT JOIN " . TABLE_PREFIX . "forumpermission AS forumpermission ON
	(FIND_IN_SET(forumpermission.forumid, forum.parentlist))
	";
	$forumpermissions = $DB_site->query($_PERMQUERY);
	while ($forumpermission = $DB_site->fetch_array($forumpermissions))
	{
		$fpermscache["$forumpermission[forumid]"]["$forumpermission[usergroupid]"] = intval($forumpermission['forumpermissions']);
	}
	unset($forumpermission);
	$DB_site->free_result($forumpermissions);

	$usergroups = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "usergroup" . iif($usergroupid > 0, " WHERE usergroupid = $usergroupid"));
	while ($usergroup = $DB_site->fetch_array($usergroups))
	{
		$usergrouptitlecache["$usergroup[usergroupid]"] = $usergroup['title'];
		$usergroupcache["$usergroup[usergroupid]"] = $usergroup;
	}

	foreach($fpermscache AS $sforumid => $fpermissions)
	{
		if ($usergroupid == -1)
		{
			foreach ($usergroupcache AS $pusergroupid => $usergroup)
			{
				$perms["$sforumid"]["$pusergroupid"] = 0;
				if (isset($fpermissions["$pusergroupid"]))
				{
					$perms["$sforumid"]["$pusergroupid"] |= $fpermissions["$pusergroupid"];
				}
				else
				{
					$perms["$sforumid"]["$pusergroupid"] |= $usergroupcache["$pusergroupid"]['forumpermissions'];
				}
			}
		}
		else
		{
			$perms["$sforumid"]["$usergroupid"] = 0;
			if (isset($fpermissions["$usergroupid"]))
			{
				$perms["$sforumid"]["$usergroupid"] |= $fpermissions["$usergroupid"];
			}
			else
			{
				$perms["$sforumid"]["$usergroupid"] |= $usergroupcache["$usergroupid"]['forumpermissions'];
			}
		}
	}
	//we now have a nice $perms array with the forumid as the index, lets look at the users original request
	//did they want all forums for a usergroup or all perms for a forum or just a specific one

	print_form_header('', '');
	if ($forumid == -1)
	{
		print_table_header($usergrouptitlecache["$usergroupid"] . " <span class=\"normal\">(usergroupid: $usergroupid)</span>");
		foreach ($perms AS $sforumid => $usergroup)
		{
			print_table_header($forumcache["$sforumid"]['title'] . " <span class=\"normal\">(forumid: $sforumid)</span>");
			foreach ($checkperm AS $key => $val)
			{

				if (bitwise($usergroup["$usergroupid"], $val))
				{
					print_label_row($bitfieldnames["$val"], '<b>' . $vbphrase['yes'] . '</b>');
				}
				else
				{
					print_label_row($bitfieldnames["$val"], '<b>' . $vbphrase['no'] . '</b>');
				}
			}
		}
	}
	else if ($usergroupid == -1)
	{
		ksort($perms["$forumid"], SORT_NUMERIC);
		print_table_header($forumcache["$forumid"]['title'] . " <span class=\"normal\">(forumid: $forumid)</span>");
		//forumid was set so show permissions for all usergroups on that forum
		foreach ($perms["$forumid"] AS $usergroupid => $usergroup)
		{
			print_table_header($usergrouptitlecache["$usergroupid"] . " <span class=\"normal\">(usergroupid: $usergroupid)</span>");
			foreach ($checkperm AS $key => $val)
			{
				if (bitwise($usergroup, $val))
				{
					print_label_row($bitfieldnames["$val"], '<b>' . $vbphrase['yes'] . '</b>');
				}
				else
				{
					print_label_row($bitfieldnames["$val"], '<b>' . $vbphrase['no'] . '</b>');
				}
			}
		}
	}
	else
	{
		print_table_header($usergrouptitlecache["$usergroupid"] . ' / ' . $forumcache["$forumid"]['title']);
		foreach ($checkperm AS $key => $val)
		{
			if (bitwise($perms["$forumid"]["$usergroupid"], $val))
			{
				print_label_row($bitfieldnames["$val"], '<b>' . $vbphrase['yes'] . '</b>');
			}
			else
			{
				print_label_row($bitfieldnames["$val"], '<b>' . $vbphrase['no'] . '</b>');
			}
		}
	}
	print_table_footer();
}

// ###################### Start viewing resources for specific user ########################
if ($_REQUEST['do'] == 'viewuser')
{

	$userinfo = fetch_userinfo($_REQUEST['userid']);
	$perms = cache_permissions($userinfo);

	print_form_header('', '');
	print_table_header($userinfo['username'] . " <span class=\"normal\">(userid: $userinfo[userid])</span>");

	foreach ($userinfo['forumpermissions'] AS $forumid => $forumperms)
	{
		print_table_header($forumcache["$forumid"]['title'] . " <span class=\"normal\">(forumid: $forumid)</span>");
		foreach ($_BITFIELD['usergroup']['forumpermissions'] AS $key => $val)
		{

			if (bitwise($userinfo['forumpermissions']["$forumid"], $val))
			{
				print_label_row($bitfieldnames["$val"], '<b>' . $vbphrase['yes'] . '</b>');
			}
			else
			{
				print_label_row($bitfieldnames["$val"], '<b>' . $vbphrase['no'] . '</b>');
			}
		}
	}
	print_table_footer();
}
print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9062 $
|| ####################################################################
\*======================================================================*/
?>