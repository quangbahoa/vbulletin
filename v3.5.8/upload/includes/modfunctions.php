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

// ###################### Start makemodchooser #######################
function construct_moderator_options($name = 'forumid', $selectedid = -1, $topname = NULL, $title = NULL, $displaytop = 1, $multiple = 0, $displayselectforum = 0, $permcheck = '')
{
	// returns a nice <select> list of forums, complete with displayorder, parenting and depth information
	// $name: name of the <select>; $selectedid: selected <option>; $topname: name given to the -1 <option>
	// $title: text for the left cell of the table row; $displaytop: display the -1 <option> or not.
	// $permcheck: permission to check to determine whether to display forum or not; User must moderate forum unless 'none' is sent

	global $vbphrase, $vbulletin;

	if ($topname === NULL)
	{
		$topname = $vbphrase['no_one'];
	}
	if ($title === NULL)
	{
		$title = $vbphrase['parent_forum'];
	}

	//require_once(DIR . '/includes/functions_databuild.php');
	//cache_forums();

	$selectoptions = array();

	if ($displayselectforum)
	{
		$selectoptions[0] = $vbphrase['select_forum'];
		$selectedid = 0;
	}

	if ($displaytop)
	{
		$selectoptions['-1'] = $topname;
		$startdepth = '--';
	}
	else
	{
		$startdepth = '';
	}

	foreach($vbulletin->forumcache AS $forum)
	{
		$perms = fetch_permissions($forum['forumid']);
		if (!($perms & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			continue;
		}
		if (empty($forum['link']))
		{
			if ($permcheck == 'none' OR can_moderate($forum['forumid'], $permcheck))
			{
				$selectoptions["$forum[forumid]"] = construct_depth_mark($forum['depth'], '--', $startdepth) . ' ' . $forum['title'] . ' ' . iif(!($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting']), " ($vbphrase[no_posting])") . ' ' . $forum['allowposting'];
			}
		}
	}

	print_select_row($title, $name, $selectoptions, $selectedid, 0, iif($multiple, 10, 0), $multiple);
}

// ###################### Start getmodforumlistsql #######################
function fetch_moderator_forum_list_sql($modaction = '')
{
	global $vbulletin;

	if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
	{
		$sql = ' OR 1=1';
	}
	else
	{
		$forums = $vbulletin->db->query_read("
			SELECT DISTINCT forum.forumid
			FROM " . TABLE_PREFIX . "forum AS forum, " . TABLE_PREFIX . "moderator AS moderator
			WHERE FIND_IN_SET(moderator.forumid, forum.parentlist)
				AND moderator.userid = " . $vbulletin->userinfo['userid'] . "
				" . iif($modaction != '', "AND moderator.permissions & " . intval($vbulletin->bf_misc_moderatorpermissions["$modaction"]))
		);

		$sql = ' OR thread.forumid IN (0';
		while ($forum = $vbulletin->db->fetch_array($forums))
		{
			$sql .= ",$forum[forumid]";
		}
		$sql .= ')';
	}

	return $sql;
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13230 $
|| ####################################################################
\*======================================================================*/
?>