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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 13359 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array();
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_announcement.php');

// ############################# LOG ACTION ###############################

$vbulletin->input->clean_array_gpc('r', array(
	'announcementid' => TYPE_INT
));
log_admin_action(iif($vbulletin->GPC['announcementid'] != 0, "announcement id = " . $vbulletin->GPC['announcementid']));

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
	$vbulletin->input->clean_array_gpc('r', array(
		'forumid' 			=> TYPE_INT,
		'newforumid'		=> TYPE_ARRAY,
		'announcementid'	=> TYPE_INT
	));

	print_form_header('announcement', 'update');

	if ($_REQUEST['do'] == 'add')
	{
		// set default values
		if (is_array($vbulletin->GPC['newforumid']))
		{
			foreach($vbulletin->GPC['newforumid'] AS $key => $val)
			{
				$vbulletin->GPC['forumid'] = intval($key);
			}
		}
		$announcement = array('startdate' => TIMENOW, 'enddate' => (TIMENOW + 86400 * 31), 'forumid' => $vbulletin->GPC['forumid'], 'allowbbcode' => 1, 'allowsmilies' => 1);
		print_table_header($vbphrase['add_new_announcement']);
	}
	else
	{
		// query announcement
		$announcement = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "announcement WHERE announcementid = " . $vbulletin->GPC['announcementid']);

		if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
		{
			if ($announcement['forumid'] == -1 AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']))
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

		construct_hidden_code('announcementid', $vbulletin->GPC['announcementid']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['announcement'], htmlspecialchars_uni($announcement['title']), $announcement['announcementid']));

	}

	print_forum_chooser($vbphrase['forum_and_children'], 'announcement[forumid]', $announcement['forumid'], $vbphrase['all_forums']);
	print_input_row($vbphrase['title'], 'announcement[title]', $announcement['title']);

	print_time_row($vbphrase['start_date'], 'start', $announcement['startdate'] + max(0, $vbulletin->options['hourdiff']), 0);
	print_time_row($vbphrase['end_date'], 'end', $announcement['enddate'] + max(0, $vbulletin->options['hourdiff']), 0);

	print_textarea_row($vbphrase['text'], 'pagetext', $announcement['pagetext'], 10, 50);

	print_yes_no_row($vbphrase['allow_bbcode'], 'announcement[allowbbcode]', $announcement['allowbbcode']);
	print_yes_no_row($vbphrase['allow_smilies'], 'announcement[allowsmilies]', $announcement['allowsmilies']);
	print_yes_no_row($vbphrase['allow_html'], 'announcement[allowhtml]', $announcement['allowhtml']);

	print_submit_row($vbphrase['save']);
}

// ###################### Start insert #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'start'				=> TYPE_ARRAY,
		'end'				=> TYPE_ARRAY,
		'announcement'		=> TYPE_ARRAY,
		'announcementid' 	=> TYPE_INT,
		'pagetext'  		=> TYPE_STR
	));

	if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		if ($vbulletin->GPC['announcement']['forumid']== -1 AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']))
		{
			print_stop_message('no_permission_global_announcement');
		}
		else if ($vbulletin->GPC['announcement']['forumid'] != -1 AND !can_moderate($vbulletin->GPC['announcement']['forumid'], 'canannounce'))
		{
			print_stop_message('no_permission_announcement');
		}
	}

	// check for valid dates
	if (!@checkdate($vbulletin->GPC['start']['month'], $vbulletin->GPC['start']['day'], $vbulletin->GPC['start']['year']))
	{
		print_stop_message('invalid_start_date_specified');
	}
	if (!@checkdate($vbulletin->GPC['end']['month'], $vbulletin->GPC['end']['day'], $vbulletin->GPC['start']['year']))
	{
		print_stop_message('invalid_end_date_specified');
	}

	// convert date arrays into unixtime
	$vbulletin->GPC['announcement']['startdate'] = @mktime(1, 0, 0, $vbulletin->GPC['start']['month'], $vbulletin->GPC['start']['day'], $vbulletin->GPC['start']['year']);
	$vbulletin->GPC['announcement']['enddate'] = @mktime(1, 0, 0, $vbulletin->GPC['end']['month'], $vbulletin->GPC['end']['day'], $vbulletin->GPC['end']['year']);

	if ($vbulletin->GPC['announcement']['startdate'] < 0 AND $vbulletin->GPC['start']['year'] > 1969)
	{
		// we've overflowed the integer for the date (probably year > 2037)
		print_stop_message('invalid_start_date_specified');
	}
	if ($vbulletin->GPC['announcement']['enddate'] < 0 AND $vbulletin->GPC['end']['year'] > 1969)
	{
		// we've overflowed the integer for the date (probably year > 2037)
		print_stop_message('invalid_end_date_specified');
	}

	if ($vbulletin->GPC['announcement']['startdate'] > $vbulletin->GPC['announcement']['enddate'])
	{
		print_stop_message('begin_date_after_end_date');
	}


	$vbulletin->GPC['announcement']['pagetext'] = $vbulletin->GPC['pagetext'];

	if (!trim($vbulletin->GPC['announcement']['title']))
	{
		$vbulletin->GPC['announcement']['title'] = $vbphrase['announcement'];
	}

	if ($vbulletin->GPC['announcementid'])
	{
		// update
		$db->query_write(fetch_query_sql($vbulletin->GPC['announcement'], 'announcement', "WHERE announcementid = " . $vbulletin->GPC['announcementid']));
	}
	else
	{
		// insert
		$vbulletin->GPC['announcement']['userid'] = $vbulletin->userinfo['userid'];
		/*insert query*/
		$db->query_write(fetch_query_sql($vbulletin->GPC['announcement'], 'announcement'));
	}

	define('CP_REDIRECT', 'announcement.php');
	print_stop_message('saved_announcement_x_successfully', htmlspecialchars_uni($vbulletin->GPC['announcement']['title']));

}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'announcementid' 	=> TYPE_INT
	));

	print_delete_confirmation('announcement', $vbulletin->GPC['announcementid'], 'announcement', 'kill', 'announcement');
}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'announcementid' 	=> TYPE_INT
	));

	$db->query_write("DELETE FROM " . TABLE_PREFIX . "announcement WHERE announcementid = " . $vbulletin->GPC['announcementid']);

	define('CP_REDIRECT', 'announcement.php?do=modify');
	print_stop_message('deleted_announcement_successfully');
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	$ans = $db->query_read("
		SELECT announcementid,title,startdate,enddate,forumid,username
		FROM " . TABLE_PREFIX . "announcement AS announcement
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		ORDER BY startdate
	");
	while ($an = $db->fetch_array($ans))
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

	//require_once(DIR . '/includes/functions_databuild.php');
	//cache_forums();
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
			"\t\t<li><b>" . htmlspecialchars_uni($announcement['title']) . "</b> ($announcement[username]) ".
			construct_link_code($vbphrase['edit'], "announcement.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&a=$announcement[announcementid]").
			construct_link_code($vbphrase['delete'], "announcement.php?" . $vbulletin->session->vars['sessionurl'] . "do=remove&a=$announcement[announcementid]").
			'<span class="smallfont">(' . ' ' .
				construct_phrase($vbphrase['x_to_y'], vbdate($vbulletin->options['dateformat'], $announcement['startdate'] + max(0, $vbulletin->options['hourdiff'])), vbdate($vbulletin->options['dateformat'], $announcement['enddate'] + max(0, $vbulletin->options['hourdiff']))) .
			")</span></li>\n";
		}
		$cell[] = $announcements;
		$cell[] = '<input type="submit" class="button" value="' . $vbphrase['new'] . '" title="' . $vbphrase['add_new_announcement'] . '" />';
		print_cells_row($cell, 0, '', -1);
		print_table_break();
	}

	// display forum-specific announcements
	foreach($vbulletin->forumcache AS $key => $forum)
	{
		if ($forum['parentid'] == -1)
		{
			print_cells_row(array($vbphrase['forum'], $vbphrase['announcements'], ''), 1, 'tcat', 1);
		}
		$cell = array();
		$cell[] = "<b>" . construct_depth_mark($forum['depth'], '- - ', '- - ') . "<a href=\"../announcement.php?" . $vbulletin->session->vars['sessionurl'] . "f=$forum[forumid]\" target=\"_blank\">$forum[title]</a></b>";
		$announcements = '';
		if (is_array($ancache[$forum['forumid']]))
		{
			foreach($ancache[$forum['forumid']] AS $announcementid => $announcement)
			{
				$announcements .=
				"\t\t<li><b>" . htmlspecialchars_uni($announcement['title']) . "</b> ($announcement[username]) ".
				construct_link_code($vbphrase['edit'], "announcement.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&a=$announcement[announcementid]").
				construct_link_code($vbphrase['delete'], "announcement.php?" . $vbulletin->session->vars['sessionurl'] . "do=remove&a=$announcement[announcementid]").
				'<span class="smallfont">('.
					construct_phrase($vbphrase['x_to_y'], vbdate($vbulletin->options['dateformat'], $announcement['startdate'] + max(0, $vbulletin->options['hourdiff'])), vbdate($vbulletin->options['dateformat'], $announcement['enddate'] + max(0, $vbulletin->options['hourdiff']))) .
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
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13359 $
|| ####################################################################
\*======================================================================*/
?>
