<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// #############################################################################
/**
* Prints a row containing a <select> showing forums the user has permission to moderate
*
* @param	string	name for the <select>
* @param	mixed	selected <option>
* @param	string	text given to the -1 option
* @param	string	title for the row
* @param	boolean	Display the -1 option or not
* @param	boolean	Allow a multiple <select> or not
* @param	boolean	Display a 'select forum' option or not
* @param	string	If specified, check this permission for each forum
*/
function print_moderator_forum_chooser($name = 'forumid', $selectedid = -1, $topname = NULL, $title = NULL, $displaytop = true, $multiple = false, $displayselectforum = false, $permcheck = '')
{
	if ($title === NULL)
	{
		$title = $vbphrase['parent_forum'];
	}

	$select_options = fetch_moderator_forum_options($topname, $displaytop, $displayselectforum, $permcheck);

	print_select_row($title, $name, $select_options, $selectedid, 0, iif($multiple, 10, 0), $multiple);
}

// #############################################################################
/**
* Returns a nice <select> list of forums, complete with displayorder, parenting and depth information
*
* @param	string	Optional name of the first <option>
* @param	boolean	Show the top <option> or not
* @param	boolean	Display an <option> labelled 'Select a forum'
* @param	string	Name of can_moderate() option to check for each forum - if 'none', show all forums
* @param	string	Character(s) to use to indicate forum depth
* @param	boolean	Show '(no posting)' after title of category-type forums
*
* @return	array	Array for use in building a <select> to show options
*/
function fetch_moderator_forum_options($topname = NULL, $displaytop = true, $displayselectforum = false, $permcheck = '', $depthmark = '--', $show_no_posting = true)
{
	global $vbphrase, $vbulletin;

	$select_options = array();

	if ($displayselectforum)
	{
		$selectoptions[0] = $vbphrase['select_forum'];
		$selectedid = 0;
	}

	if ($displaytop)
	{
		$select_options['-1'] = ($topname === NULL ? $vbphrase['no_one'] : $topname);
		$startdepth = $depthmark;
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
				$select_options["$forum[forumid]"] = str_repeat($depthmark, $forum['depth']) . "$startdepth $forum[title]";
				if ($show_no_posting)
				{
					$select_options["$forum[forumid]"] .= ' ' . ($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting'] ? '' : " ($vbphrase[no_posting])") . " $forum[allowposting]";
				}
			}
		}
	}

	return $select_options;
}

// #############################################################################
/**
* Returns an SQL condition to select forums a user has permission to moderate
*
* @param	string	Moderator permission to check (canannounce, canmoderateposts etc.)
*
* @return	string	SQL condition
*/
function fetch_moderator_forum_list_sql($modaction = '')
{
	global $vbulletin;

	if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
	{
		$sql = ' OR 1=1';
	}
	else
	{
		$forums = $vbulletin->db->query_read_slave("
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
|| # CVS: $RCSfile$ - $Revision: 14942 $
|| ####################################################################
\*======================================================================*/
?>