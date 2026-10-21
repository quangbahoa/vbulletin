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
define('THIS_SCRIPT', 'report');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('messaging');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'newpost_usernamecode',
	'reportbadpost'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

//check usergroup of user to see if they can use this
if (!$bbuserinfo['userid'])
{
	print_no_permission();
}

if (!$vboptions['enableemail'])
{
	eval(print_standard_error('error_emaildisabled'));
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'report';
}

$forumperms = fetch_permissions($threadinfo['forumid']);
if (!($forumperms & CANVIEW))
{
	print_no_permission();
}

if ($threadinfo['isdeleted'] OR $postinfo['isdeleted'])
{
	$idname = $vbphrase['post'];
	eval(print_standard_error('error_invalidid'));
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

if ($_REQUEST['do'] == 'report')
{
	$postid = verify_id('post', $_REQUEST['postid'] );

	/*if ($postinfo['userid'] == $bbuserinfo['userid'])
	{
		eval(print_standard_error('error_cantreportself'));
	}*/

	// draw nav bar
	$navbits = array();
	$parentlist = array_reverse(explode(',', $foruminfo['parentlist']));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $forumcache["$forumID"]['title'];
		$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
	}
	$navbits["showthread.php?$session[sessionurl]p=$postid"] = $threadinfo['title'];
	$navbits[''] = $vbphrase['report_bad_post'];
	$navbits = construct_navbits($navbits);

	require_once('./includes/functions_editor.php');
	$textareacols = fetch_textarea_width();
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template('reportbadpost') . '");');

}

if ($_POST['do'] == 'sendemail')
{
	$postid = verify_id('post', $_REQUEST['postid'] );

	globalize($_POST , array('reason' => STR));

	if ($reason == '')
	{
		eval(print_standard_error('error_noreason'));
	}

	$moderators = $DB_site->query("
		SELECT DISTINCT user.email
		FROM " . TABLE_PREFIX . "moderator AS moderator," . TABLE_PREFIX . "user AS user
		WHERE user.userid = moderator.userid
			AND moderator.forumid IN ($foruminfo[parentlist])
	");

	$mods = array();

	while ($moderator = $DB_site->fetch_array($moderators))
	{
		$mods[] = $moderator;
	}

	$threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
	$postinfo['title'] = unhtmlspecialchars($postinfo['title']);

	if (empty($mods) OR $foruminfo['options'] & $_FORUMOPTIONS['warnall'])
	{
		// get admins if no mods or if this forum notifies all
		$moderators = $DB_site->query("
			SELECT user.email, user.languageid
			FROM " . TABLE_PREFIX . "user AS user
			INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup USING (usergroupid)
			WHERE usergroup.adminpermissions <> 0
		");

		while ($moderator = $DB_site->fetch_array($moderators))
		{
			$mods[] = $moderator;
		}
	}

	vbmail_start();

	foreach ($mods AS $index => $moderator)
	{
		if (!empty($moderator['email']))
		{
			eval(fetch_email_phrases('reportbadpost', $moderator['languageid']));
			vbmail($moderator['email'], $subject, $message);
		}
	}

	vbmail_end();

	eval(print_standard_redirect('redirect_reportthanks'));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 8909 $
|| ####################################################################
\*======================================================================*/
?>