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
define('CVS_REVISION', '$RCSfile$ - $Revision: 13360 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array();
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_announcement.php');

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(	'announcementid' => TYPE_INT));
log_admin_action(!empty($vbulletin->GPC['announcementid']) ? "announcement id = " . $vbulletin->GPC['announcementid'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['announcement_manager']);

// ###################### Start add / edit #######################

if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'forumid' => TYPE_INT,
	));

	print_form_header('announcement', 'update');

	if ($_REQUEST['do'] == 'add')
	{
		$announcement = array('startdate' => TIMENOW, 'enddate' => (TIMENOW + 86400 * 31), 'forumid' => $vbulletin->GPC['forumid'], 'allowbbcode' => 1, 'allowsmilies' => 1);
		print_table_header($vbphrase['add_new_announcement']);
	}
	else
	{
		// query announcement
		$announcement = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "announcement WHERE announcementid = " . $vbulletin->GPC['announcementid']);

		if ($retval = can_announce($announcement['forumid']))
		{
			print_table_header(fetch_announcement_permissions_error($retval));
			print_table_break();
		}

		construct_hidden_code('announcementid', $vbulletin->GPC['announcementid']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['announcement'], htmlspecialchars_uni($announcement['title']), $announcement['announcementid']));

	}

	$issupermod = $permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'];
	construct_moderator_options('announcement[forumid]', $announcement['forumid'], $vbphrase['all_forums'], $vbphrase['forum_and_children'], iif($issupermod, true, false), false, false,'canannounce');
	print_input_row($vbphrase['title'], 'announcement[title]', $announcement['title']);

	print_time_row($vbphrase['start_date'], 'start', $announcement['startdate'], 0);
	print_time_row($vbphrase['end_date'], 'end', $announcement['enddate'], 0);

	print_textarea_row($vbphrase['text'], 'announcement[pagetext]', $announcement['pagetext'], 10, 50, 1, 0);

	print_yes_no_row($vbphrase['allow_bbcode'], 'announcement[allowbbcode]', $announcement['allowbbcode']);
	print_yes_no_row($vbphrase['allow_smilies'], 'announcement[allowsmilies]', $announcement['allowsmilies']);
	print_yes_no_row($vbphrase['allow_html'], 'announcement[allowhtml]', $announcement['allowhtml']);

	print_submit_row(iif($_REQUEST['do'] == 'add', $vbphrase['add'], $vbphrase['save']));
}

// ###################### Start insert #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'start'        => TYPE_ARRAY_INT,
		'end'          => TYPE_ARRAY_INT,
		'announcement' => TYPE_ARRAY_STR,
	));

	if ($retval = can_announce($vbulletin->GPC['announcement']['forumid']))
	{
		print_stop_message(fetch_announcement_permissions_error($retval));
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

	if (!$vbulletin->GPC['announcement']['title'])
	{
		$vbulletin->GPC['announcement']['title'] = $vbphrase['announcement'];
	}

	if (!empty($vbulletin->GPC['announcementid']))
	{ // update
		$db->query_write(fetch_query_sql($vbulletin->GPC['announcement'], 'announcement', "WHERE announcementid=" . $vbulletin->GPC['announcementid']));

		define('CP_REDIRECT', 'forum.php');
		print_stop_message('saved_announcement_x_successfully', htmlspecialchars_uni($vbulletin->GPC['announcement']['title']));
	}
	else
	{ // insert
		$vbulletin->GPC['announcement']['userid'] = $vbulletin->userinfo['userid'];
		/*insert query*/
		$db->query_write(fetch_query_sql($vbulletin->GPC['announcement'], 'announcement'));

		define('CP_REDIRECT', 'forum.php');
		print_stop_message('saved_announcement_x_successfully', htmlspecialchars_uni($vbulletin->GPC['announcement']['title']));
	}
}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	$announcement = $db->query_first("
		SELECT forumid
		FROM " . TABLE_PREFIX . "announcement
		WHERE announcementid = " . $vbulletin->GPC['announcementid'] . "
	");
	if ($retval = can_announce($announcement['forumid']))
	{
		print_stop_message(fetch_announcement_permissions_error($retval));
	}

	print_delete_confirmation('announcement', $vbulletin->GPC['announcementid'], 'announcement', 'kill', 'announcement');
}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{

	$announcement = $db->query_first("
		SELECT forumid
		FROM " . TABLE_PREFIX . "announcement
		WHERE announcementid = " . $vbulletin->GPC['announcementid'] . "
	");
	if ($retval = can_announce($announcement['forumid']))
	{
		print_stop_message(fetch_announcement_permissions_error($retval));
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "announcement
		WHERE announcementid = " . $vbulletin->GPC['announcementid'] . "
	");

	define('CP_REDIRECT', 'forum.php');
	print_stop_message('deleted_announcement_successfully');
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13360 $
|| ####################################################################
\*======================================================================*/

?>