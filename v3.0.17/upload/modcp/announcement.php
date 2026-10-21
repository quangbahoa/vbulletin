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
define('CVS_REVISION', '$RCSfile$ - $Revision: 13297 $');
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

// ###################### Start add / edit #######################

if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	print_form_header('announcement', 'update');

	if ($_REQUEST['do'] == 'add')
	{
		// set default values
		$forumid = intval($_REQUEST['forumid']);
		if (is_array($newforumid))
		{
			foreach ($newforumid AS $key => $val)
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
		$announcementid = intval($_REQUEST['announcementid']);
		$announcement = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "announcement WHERE announcementid = $announcementid");

		if ($retval = can_announce($announcement['forumid']))
		{
			print_table_header(fetch_announcement_permissions_error($retval));
			print_table_break();
		}

		construct_hidden_code('announcementid', $announcementid);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['announcement'], $announcement['title'], $announcement['announcementid']));

	}

	$issupermod = $permissions['adminpermissions'] & ISMODERATOR;
	construct_moderator_options('announcement[forumid]', $announcement['forumid'], $vbphrase['all_forums'], $vbphrase['forum_and_children'], iif($issupermod, true, false), false, false,'canannounce');
	print_input_row($vbphrase['title'], 'announcement[title]', $announcement['title']);

	print_time_row($vbphrase['start_date'], 'start', $announcement['startdate'], 0);
	print_time_row($vbphrase['end_date'], 'end', $announcement['enddate'], 0);

	print_textarea_row($vbphrase['text'], 'pagetext', $announcement['pagetext'], 10, 50, 1, 0);

	print_yes_no_row($vbphrase['allow_bbcode'], 'announcement[allowbbcode]', $announcement['allowbbcode']);
	print_yes_no_row($vbphrase['allow_smilies'], 'announcement[allowsmilies]', $announcement['allowsmilies']);
	print_yes_no_row($vbphrase['allow_html'], 'announcement[allowhtml]', $announcement['allowhtml']);

	print_submit_row(iif($_REQUEST['do'] == 'add', $vbphrase['add'], $vbphrase['save']));
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

	if ($retval = can_announce($announcement['forumid']))
	{
		print_stop_message(fetch_announcement_permissions_error($retval));
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

	if (!trim($announcement['title']))
	{
		$announcement['title'] = $vbphrase['announcement'];
	}

	$validfields = array(
		'forumid'       => $announcement['forumid'],
		'title'         => $announcement['title'],
		'pagetext'      => $pagetext,

		// convert date arrays into unixtime
		'startdate'     => mktime(1, 0, 0, $start['month'], $start['day'], $start['year']),
		'enddate'       => mktime(1, 0, 0, $end['month'], $end['day'], $end['year']),

		'allowbbcode'   => $announcement['allowbbcode'],
		'allowsmilies'  => $announcement['allowsmilies'],
		'allowhtml'     => $announcement['allowhtml']
	);

	if (!empty($announcementid))
	{ // update
		$DB_site->query(fetch_query_sql($validfields, 'announcement', 'WHERE announcementid=' . intval($announcementid)));

		define('CP_REDIRECT', 'forum.php');
		print_stop_message('saved_announcement_x_successfully', $announcement['title']);
	}
	else
	{ // insert
		$validfields['userid'] = $bbuserinfo['userid'];
		$DB_site->query(fetch_query_sql($validfields, 'announcement'));

		define('CP_REDIRECT', 'forum.php');
		print_stop_message('saved_announcement_x_successfully', $announcement['title']);
	}
}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	$announcementid = intval($_REQUEST['announcementid']);
	$announcement = $DB_site->query_first("
		SELECT forumid
		FROM " . TABLE_PREFIX . "announcement
		WHERE announcementid = $announcementid
	");
	if ($retval = can_announce($announcement['forumid']))
	{
		print_stop_message(fetch_announcement_permissions_error($retval));
	}

	print_delete_confirmation('announcement', $announcementid, 'announcement', 'kill', 'announcement');
}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	$announcementid = intval($_POST['announcementid']);
	$announcement = $DB_site->query_first("
		SELECT forumid
		FROM " . TABLE_PREFIX . "announcement
		WHERE announcementid = $announcementid
	");
	if ($retval = can_announce($announcement['forumid']))
	{
		print_stop_message(fetch_announcement_permissions_error($retval));
	}

	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "announcement
		WHERE announcementid = $announcementid
	");

	define('CP_REDIRECT', 'forum.php');
	print_stop_message('deleted_announcement_successfully');
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13297 $
|| ####################################################################
\*======================================================================*/

?>