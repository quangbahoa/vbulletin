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
define('CVS_REVISION', '$RCSfile$ - $Revision: 9026 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('forum');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['moderatorid'] != 0, " moderator id = $_REQUEST[moderatorid]", iif($_REQUEST['forumid'] != 0, "forum id = $_REQUEST[forumid]")));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['forum_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ################# Start edit password ###################
if ($_REQUEST['do'] == 'editpassword')
{
	globalize($_REQUEST, array('forumid' => INT));

	if (empty($forumid))
	{
		print_stop_message('invalid_forum_specified');
	}

	if (!can_moderate($forumid, 'cansetpassword'))
	{
		print_stop_message('no_permission_forum_password');
	}
	$foruminfo = fetch_foruminfo($forumid);
	if (!$foruminfo['canhavepassword'])
	{
		print_stop_message('forum_cant_have_password');
	}

	print_form_header('forum', 'doeditpassword');
	print_table_header(construct_phrase($vbphrase['edit_password'], $foruminfo['title']), 2);
	print_input_row($vbphrase['forum_password'], 'forumpwd', $foruminfo['password']);
	print_yes_no_row($vbphrase['apply_password_to_children'], 'applypwdtochild', iif($foruminfo['password'], 0, 1));
	construct_hidden_code('forumid', $forumid);
	print_submit_row($vbphrase['save']);
}

// ################# Start do edit password ###################
if ($_POST['do'] == 'doeditpassword')
{

	globalize($_POST, array('forumid' => INT, 'forumpwd' => STR, 'applypwdtochild' => INT));

	if (!can_moderate($forumid, 'cansetpassword'))
	{
		print_stop_message('no_permission_forum_password');
	}
	$foruminfo = fetch_foruminfo($forumid);

	if (!$foruminfo['canhavepassword'])
	{
		print_stop_message('forum_cant_have_password');
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "forum SET
		password = '" . addslashes($forumpwd) . "'
		WHERE forumid = $forumid
	");
	if ($applypwdtochild)
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "forum SET
			password = '" . addslashes($forumpwd) . "'
			WHERE FIND_IN_SET('$forumid', parentlist) AND (options & $_FORUMOPTIONS[canhavepassword])
		");
	}

	build_forum_permissions();

	define('CP_REDIRECT', 'forum.php');
	print_stop_message('saved_x_y_successfully', $forum['forum'], $foruminfo['title']);
}

// ################# Start modify ###################
if ($_REQUEST['do'] == 'modify')
{
	/******** Global Announcements ****/
	if ($permissions['adminpermissions'] & ISMODERATOR)
	{
		$forumannouncements = $DB_site->query("
			SELECT title, FROM_UNIXTIME(startdate) AS startdate, FROM_UNIXTIME(enddate) AS enddate, announcementid
			FROM " . TABLE_PREFIX . "announcement AS announcement
			WHERE announcement.forumid = -1
		");

		print_form_header('', '');
		print_table_header($vbphrase['global_announcements'], 4);
		print_cells_row(array($vbphrase['title'], $vbphrase['start_date'], $vbphrase['end_date'], $vbphrase['modify']), 1);

		if ($DB_site->num_rows($forumannouncements))
		{
			while ($announcement = $DB_site->fetch_array($forumannouncements))
			{
				$cell = array($announcement['title'], $announcement['startdate'], $announcement['enddate']);
				$cell[] = construct_link_code($vbphrase['edit'], "announcement.php?$session[sessionurl]do=edit&amp;announcementid=$announcement[announcementid]") .
					construct_link_code($vbphrase['delete'],"announcement.php?$session[sessionurl]do=remove&amp;announcementid=$announcement[announcementid]");
				print_cells_row($cell);
			}
		}
		else
		{
			print_description_row($vbphrase['no_global_announcements_defined'], '', 4, '', 'center');
		}
		print_description_row(construct_link_code($vbphrase['add_announcement'],"announcement.php?$session[sessionurl]do=add"), '', 4, 'thead', $stylevar['right']);
		print_table_footer();
	}

	/******** Forums List ****/
	require_once('./includes/functions_databuild.php');
	cache_forums();

	$forums = array();
	foreach ($forumcache AS $forumid => $forum)
	{
		$forums["$forum[forumid]"] = construct_depth_mark($forum['depth'], '--') . ' ' . $forum['title'];
	}

	print_form_header('', '');
	print_table_header($vbphrase['forums'], 2);


	foreach ($forumcache AS $key => $forum)
	{
		$perms = fetch_permissions($forum['forumid']);
		if (!($perms & CANVIEW))
		{
			continue;
		}

		if ($forum['parentid'] == -1)
		{
			print_cells_row(array('&nbsp; ' . $vbphrase['title'], $vbphrase['modify']), 1, 'tcat');
		}

		$cell = array();
		$cell[] = '&nbsp; <b>' . construct_depth_mark($forum['depth'], '- - ') . "<a href=\"../forumdisplay.php?$session[sessionurl]forumid=$forum[forumid]\">$forum[title]</a></b>";
		$cell[] =
			'&nbsp;' .
			iif(can_moderate($forum['forumid'], 'canannounce'), construct_link_code($vbphrase['add_announcement'], "announcement.php?$session[sessionurl]do=add&amp;forumid=$forum[forumid]"), '') .
			' ' .
			iif(can_moderate($forum['forumid'], 'cansetpassword') AND ($forum['options'] & $_FORUMOPTIONS['canhavepassword']), construct_link_code($vbphrase['edit_password'], "forum.php?$session[sessionurl]do=editpassword&amp;forumid=$forum[forumid]"), '');

		print_cells_row($cell);

		if (can_moderate($forum['forumid'], 'canannounce'))
		{
			$forumannouncements = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "announcement WHERE forumid = $forum[forumid]");
			if ($DB_site->num_rows($forumannouncements))
			{
				$annc = "<ul><b>" . $vbphrase['announcements'] . ":</b><ul>\n";
				while ($announcement=$DB_site->fetch_array($forumannouncements))
				{
					$annc .=
						"<li>$announcement[title] ".
						construct_link_code($vbphrase['edit'], "announcement.php?$session[sessionurl]do=edit&amp;announcementid=$announcement[announcementid]") .
						' '.
						construct_link_code($vbphrase['delete'], "announcement.php?$session[sessionurl]do=remove&amp;announcementid=$announcement[announcementid]") .
						'</li>';
				}
				$annc .= "</ul></ul>\n";
				print_description_row($annc);
			}
		}
	}

	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9026 $
|| ####################################################################
\*======================================================================*/
?>