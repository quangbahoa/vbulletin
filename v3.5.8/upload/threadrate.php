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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'threadrate');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_array_gpc('p', array(
	'vote'			=> TYPE_UINT,
	'pagenumber'	=> TYPE_UINT,
	'perpage'		=> TYPE_UINT,
));

if ($vbulletin->GPC['vote'] < 1 OR $vbulletin->GPC['vote'] > 5)
{
	eval(standard_error(fetch_error('invalidvote')));
}

if (!$threadinfo['threadid'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')) OR (!$threadinfo['open'] AND !can_moderate($threadinfo['forumid'], 'canopenclose')) OR ($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')))
{
	eval(standard_error(fetch_error('threadrateclosed')));
}

$forumperms = fetch_permissions($threadinfo['forumid']);
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canthreadrate']) OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'])))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$rated = intval(fetch_bbarray_cookie('thread_rate', $threadinfo['threadid']));

($hook = vBulletinHook::fetch_hook('threadrate_start')) ? eval($hook) : false;

if ($vbulletin->userinfo['userid'])
{
	 if ($rating = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "threadrate
		WHERE userid = " . $vbulletin->userinfo['userid'] . "
			AND threadid = $threadinfo[threadid]
	"))
	 {
		if ($vbulletin->options['votechange'])
		{
			if ($vbulletin->GPC['vote'] != $rating['vote'])
			{
				$threadrate =& datamanager_init('ThreadRate', $vbulletin, ERRTYPE_STANDARD);
				$threadrate->set_info('thread', $threadinfo);
				$threadrate->set_existing($rating);
				$threadrate->set('vote', $vbulletin->GPC['vote']);

				($hook = vBulletinHook::fetch_hook('threadrate_update')) ? eval($hook) : false;

				$threadrate->save();
			}

			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;pp=" . $vbulletin->GPC['perpage'];
			eval(print_standard_redirect('redirect_threadrate_update'));
		}
		else
		{
			eval(standard_error(fetch_error('threadratevoted')));
		}
	 }
	 else
	 {
		$threadrate =& datamanager_init('ThreadRate', $vbulletin, ERRTYPE_STANDARD);
		$threadrate->set_info('thread', $threadinfo);
		$threadrate->set('threadid', $threadid);
		$threadrate->set('userid', $vbulletin->userinfo['userid']);
		$threadrate->set('vote', $vbulletin->GPC['vote']);

		($hook = vBulletinHook::fetch_hook('threadrate_add')) ? eval($hook) : false;

		$threadrate->save();

		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;pp=" . $vbulletin->GPC['perpage'];
		eval(print_standard_redirect('redirect_threadrate_add'));
	 }
}
else
{
	// Check for cookie on user's computer for this threadid
	if ($rated AND !$vbulletin->options['votechange'])
	{
		eval(standard_error(fetch_error('threadratevoted')));
	}

	// Check for entry in Database for this Ip Addr/Threadid
	if ($rating = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "threadrate
		WHERE ipaddress = '" . $db->escape_string(IPADDRESS) . "'
			AND threadid = $threadinfo[threadid]
	"))
	{
		if ($vbulletin->options['votechange'])
		{
			if ($vbulletin->GPC['vote'] != $rating['vote'])
			{
				$threadrate =& datamanager_init('ThreadRate', $vbulletin, ERRTYPE_STANDARD);
				$threadrate->set_info('thread', $threadinfo);
				$threadrate->set_existing($rating);
				$threadrate->set('vote', $vbulletin->GPC['vote']);

				($hook = vBulletinHook::fetch_hook('threadrate_update')) ? eval($hook) : false;

				$threadrate->save();
			}

			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;pp=" . $vbulletin->GPC['perpage'];
			eval(print_standard_redirect('redirect_threadrate_update'));
		}
		else
		{
			eval(standard_error(fetch_error('threadratevoted')));
		}
	}
	else
	{
		$threadrate =& datamanager_init('ThreadRate', $vbulletin, ERRTYPE_STANDARD);
		$threadrate->set_info('thread', $threadinfo);
		$threadrate->set('threadid', $threadid);
		$threadrate->set('userid', 0);
		$threadrate->set('vote', $vbulletin->GPC['vote']);
		$threadrate->set('ipaddress', IPADDRESS);

		($hook = vBulletinHook::fetch_hook('threadrate_add')) ? eval($hook) : false;

		$threadrate->save();

		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;pp=" . $vbulletin->GPC['perpage'];
		eval(print_standard_redirect('redirect_threadrate_add'));
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12800 $
|| ####################################################################
\*======================================================================*/
?>