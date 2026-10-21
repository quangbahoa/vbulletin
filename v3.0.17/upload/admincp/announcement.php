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
define('CVS_REVISION', '$RCSfile$ - $Revision: 8966 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array();
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_announcement.php');

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['announcementid'] != 0, "announcement id = " . $_REQUEST['announcementid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['announcement_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add / edit #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array('forumid' => INT, 'newforumid', 'announcementid' => INT));

	print_form_header('announcement', 'update');

	if ($_REQUEST['do'] == 'add')
	{
		// set default values
		if (is_array($newforumid))
		{
			foreach($newforumid AS $key => $val)
			{
				$forumid = $key;
			}
		}
		$announcement = array('startdate' => TIMENOW, 'enddate' => (TIMENOW + 86400 * 31), 'forumid' => $forumid, 'allowbbcode' => 1, 'allowsmilies' => 1);
		print_table_header($vbphrase['add_new_announcement']);
	}
	else
	{
		// query announcement
		$announcement = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "announcement WHERE announcementid = $announcementid");

		if (!($permissions['adminpermissions'] & CANCONTROLPANEL))
		{
			if ($announcement['forumid'] == -1 AND !($permissions['adminpermissions'] & ISMODERATOR))
			{
				print_table_header($vbphrase['no_permission_global_announcement']);
				print_table_break();
			}
			else if ($announcement['forumid'] != -1 AND !can_moderate($announcement['forumid'], 'canannounce'))
			{
				print_table_header($vbphrase['no_permission_announcement']);
				print_table_break();
			}
		}

		construct_hidden_code('announcementid', $announcementid);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['announcement'], $announcement['title'], $announcement['announcementid']));

	}

	print_forum_chooser('announcement[forumid]', $announcement['forumid'], $vbphrase['all_forums'], $vbphrase['forum_and_children']);
	print_input_row($vbphrase['title'], 'announcement[title]', $announcement['title']);

	print_time_row($vbphrase['start_date'], 'start', $announcement['startdate'] + max(0, $vboptions['hourdiff']), 0);
	print_time_row($vbphrase['end_date'], 'end', $announcement['enddate'] + max(0, $vboptions['hourdiff']), 0);

	print_textarea_row($vbphrase['text'], 'pagetext', $announcement['pagetext'], 10, 50);

	print_yes_no_row($vbphrase['allow_bbcode'], 'announcement[allowbbcode]', $announcement['allowbbcode']);
	print_yes_no_row($vbphrase['allow_smilies'], 'announcement[allowsmilies]', $announcement['allowsmilies']);
	print_yes_no_row($vbphrase['allow_html'], 'announcement[allowhtml]', $announcement['allowhtml']);

	print_submit_row($vbphrase['save']);
}

// ###################### Start insert #######################
if ($_POST['do'] == 'update')
{
	globalize($_POST, array(
		'start',
		'end',
		'announcement',
		'announcementid' => INT,
		'pagetext' => STR
	));

	if (!($permissions['adminpermissions'] & CANCONTROLPANEL))
	{
		if ($announcement['forumid']== -1 AND !($permissions['adminpermissions'] & ISMODERATOR))
		{
			print_stop_message('no_permission_global_announcement');
		}
		else if ($announcement['forumid'] != -1 AND !can_moderate($announcement['forumid'], 'canannounce'))
		{
			print_stop_message('no_permission_announcement');
		}
	}

	// check for valid dates
	if (!@checkdate($start['month'], $start['day'], $start['year']))
	{
		print_stop_message('invalid_start_date_specified');
	}
	if (!@checkdate($end['month'], $end['day'], $start['year']))
	{
		print_stop_message('invalid_end_date_specified');
	}

	if (mktime(0, 0, 0 ,$start['month'], $start['day'], $start['year']) > mktime(0, 0, 0 ,$end['month'], $end['day'], $end['year']))
	{
		print_stop_message('begin_date_after_end_date');
	}

	// convert date arrays into unixtime
	$announcement['startdate'] = mktime(1, 0, 0, $start['month'], $start['day'], $start['year']);
	$announcement['enddate'] = mktime(1, 0, 0, $end['month'], $end['day'], $end['year']);

	$announcement['pagetext'] = $pagetext;

	if (!trim($announcement['title']))
	{
		$announcement['title'] = $vbphrase['announcement'];
	}

	if ($announcementid)
	{
		// update
		$DB_site->query(fetch_query_sql($announcement, 'announcement', "WHERE announcementid = $announcementid"));
	}
	else
	{
		// insert
		$announcement['userid'] = $bbuserinfo['userid'];
		$DB_site->query(fetch_query_sql($announcement, 'announcement'));
	}

	define('CP_REDIRECT', 'announcement.php');
	print_stop_message('saved_announcement_x_successfully', $announcement['title']);

}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	globalize($_REQUEST, array('announcementid' => INT));

	print_delete_confirmation('announcement', $announcementid, 'announcement', 'kill', 'announcement');
}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	globalize($_POST, array('announcementid' => INT));

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "announcement WHERE announcementid = $announcementid");

	define('CP_REDIRECT', 'announcement.php?do=modify');
	print_stop_message('deleted_announcement_successfully');

}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	$ans = $DB_site->query("
		SELECT announcementid,title,startdate,enddate,forumid,username
		FROM " . TABLE_PREFIX . "announcement AS announcement
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		ORDER BY startdate
	");
	while ($an = $DB_site->fetch_array($ans))
	{
		if (!$an['username'])
		{
			$an['username'] = $vbphrase['guest'];
		}
		if ($an['forumid'] == -1)
		{
			$globalannounce[] = $an;
		}
		else
		{
			$ancache[$an['forumid']][$an['announcementid']] = $an;
		}
	}

	require_once('./includes/functions_databuild.php');
	cache_forums();
	print_form_header('announcement', 'add');
	print_table_header($vbphrase['announcement_manager'], 3);

	// display global announcments
	if (is_array($globalannounce))
	{
		$cell = array();
		$cell[] = '<b>' . $vbphrase['global_announcements'] . '</b>';
		$announcements = '';
		foreach($globalannounce AS $announcementid => $announcement)
		{
			$announcements .=
			"\t\t<li><b>$announcement[title]</b> ($announcement[username]) ".
			construct_link_code($vbphrase['edit'], "announcement.php?$session[sessionurl]do=edit&announcementid=$announcement[announcementid]").
			construct_link_code($vbphrase['delete'], "announcement.php?$session[sessionurl]do=remove&announcementid=$announcement[announcementid]").
			'<span class="smallfont">(' . ' ' .
				construct_phrase($vbphrase['x_to_y'], vbdate($vboptions['dateformat'], $announcement['startdate'] + max(0, $vboptions['hourdiff'])), vbdate($vboptions['dateformat'], $announcement['enddate'] + max(0, $vboptions['hourdiff']))) .
			")</span></li>\n";
		}
		$cell[] = $announcements;
		$cell[] = '<input type="submit" class="button" value="' . $vbphrase['new'] . '" title="' . $vbphrase['add_new_announcement'] . '" />';
		print_cells_row($cell, 0, '', -1);
		print_table_break();
	}

	// display forum-specific announcements
	foreach($forumcache AS $key => $forum)
	{
		if ($forum['parentid'] == -1)
		{
			print_cells_row(array($vbphrase['forum'], $vbphrase['announcements'], ''), 1, 'tcat', 1);
		}
		$cell = array();
		$cell[] = "<b>" . construct_depth_mark($forum['depth'], '- - ', '- - ') . "<a href=\"../announcement.php?$session[sessionurl]forumid=$forum[forumid]\" target=\"_blank\">$forum[title]</a></b>";
		$announcements = '';
		if (is_array($ancache[$forum['forumid']]))
		{
			foreach($ancache[$forum['forumid']] AS $announcementid => $announcement)
			{
				$announcements .=
				"\t\t<li><b>$announcement[title]</b> ($announcement[username]) ".
				construct_link_code($vbphrase['edit'], "announcement.php?$session[sessionurl]do=edit&announcementid=$announcement[announcementid]").
				construct_link_code($vbphrase['delete'], "announcement.php?$session[sessionurl]do=remove&announcementid=$announcement[announcementid]").
				'<span class="smallfont">('.
					construct_phrase($vbphrase['x_to_y'], vbdate($vboptions['dateformat'], $announcement['startdate'] + max(0, $vboptions['hourdiff'])), vbdate($vboptions['dateformat'], $announcement['enddate'] + max(0, $vboptions['hourdiff']))) .
				")</span></li>\n";
			}
		}
		$cell[] = $announcements;
		$cell[] = '<input type="submit" class="button" value="' . $vbphrase['new'] . '" name="newforumid[' . $forum['forumid'] . ']" title="' . $vbphrase['add_new_announcement'] . '" />';
		print_cells_row($cell, 0, '', -1);
	}

	print_table_footer();

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 8966 $
|| ####################################################################
\*======================================================================*/
?>