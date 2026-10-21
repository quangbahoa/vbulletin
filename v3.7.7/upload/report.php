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
define('THIS_SCRIPT', 'report');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('messaging');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'newpost_usernamecode',
	'reportitem'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_misc.php'); // for fetch_phrase
require_once(DIR . '/includes/class_reportitem.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

//check usergroup of user to see if they can use this
if (!$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

$reportthread = ($rpforumid = $vbulletin->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
$reportemail = ($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']);

if (!$reportthread AND !$reportemail)
{
	eval(standard_error(fetch_error('emaildisabled')));
}

$reportobj =& new vB_ReportItem_Post($vbulletin);
$reportobj->set_extrainfo('forum', $foruminfo);
$reportobj->set_extrainfo('thread', $threadinfo);
$perform_floodcheck = $reportobj->need_floodcheck();

if ($perform_floodcheck)
{
	$reportobj->perform_floodcheck_precommit();
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'report';
}

$forumperms = fetch_permissions($threadinfo['forumid']);
if (
	!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
		OR
	!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
		OR
	(($threadinfo['postuserid'] != $vbulletin->userinfo['userid']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
)
{
	print_no_permission();
}

if (!$postinfo['postid'])
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
}

if ((!$postinfo['visible'] OR $postinfo ['isdeleted']) AND !can_moderate($threadinfo['forumid']))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
}

if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

($hook = vBulletinHook::fetch_hook('report_start')) ? eval($hook) : false;

if ($_REQUEST['do'] == 'report')
{
	// draw nav bar
	$navbits = array();
	$parentlist = array_reverse(explode(',', $foruminfo['parentlist']));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
	}
	$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$postid"] = $threadinfo['prefix_plain_html'] . ' ' . $threadinfo['title'];
	$navbits[''] = $vbphrase['report_bad_post'];
	$navbits = construct_navbits($navbits);

	require_once(DIR . '/includes/functions_editor.php');
	$textareacols = fetch_textarea_width();
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('report_form_start')) ? eval($hook) : false;

	$url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$postid#post$postid";
	$forminfo = $reportobj->set_forminfo($postinfo);
	eval('print_output("' . fetch_template('reportitem') . '");');
}

if ($_POST['do'] == 'sendemail')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'reason' => TYPE_STR,
	));

	if ($vbulletin->GPC['reason'] == '')
	{
		eval(standard_error(fetch_error('noreason')));
	}

	if ($perform_floodcheck)
	{
		$reportobj->perform_floodcheck_commit();
	}

	$reportobj->do_report($vbulletin->GPC['reason'], $postinfo);

	eval(print_standard_redirect('redirect_reportthanks'));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 31700 $
|| ####################################################################
\*======================================================================*/
?>