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

globalize($_POST, array('vote' => INT, 'pagenumber' => INT, 'perpage' => INT));

if ($vote < 1 OR $vote > 5)
{
	eval(print_standard_error('error_invalidvote'));
}

$threadid = verify_id('thread', $_POST['threadid']);

$forumperms = fetch_permissions($threadinfo['forumid']);
if (!($forumperms & CANVIEW) OR !($forumperms & CANTHREADRATE) OR (!($forumperms & CANVIEWOTHERS) AND ($threadinfo['postuserid'] != $bbuserinfo['userid'])))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

if (!$threadinfo['visible'] OR !$threadinfo['open'] OR $threadinfo['isdeleted'])
{
	eval(print_standard_error('error_threadrateclosed'));
}

$rated = intval(fetch_bbarray_cookie('thread_rate', $threadid));

if ($bbuserinfo['userid'])
{
	 if ($rating = $DB_site->query_first("
	 	SELECT vote, threadrateid FROM " . TABLE_PREFIX . "threadrate
		WHERE userid = $bbuserinfo[userid] AND threadid = $threadid
	"))
	 {
		if ($vboptions['votechange'])
		{
			if ($vote != $rating['vote'])
			{
				 $voteupdate = $vote - $rating['vote'];
				 $DB_site->query("
					UPDATE " . TABLE_PREFIX . "threadrate
					SET vote = $vote
					WHERE threadrateid = $rating[threadrateid]
				 ");
				 $DB_site->query("
					UPDATE " . TABLE_PREFIX . "thread
					SET votetotal = votetotal + $voteupdate
					WHERE threadid = $threadid
				 ");

				 set_bbarray_cookie('thread_rate', $threadid, $vote, 1);
			}
			$url = "showthread.php?$session[sessionurl]t=$threadid&amp;page=$pagenumber&amp;pp=$perpage";
			eval(print_standard_redirect('redirect_threadrate_update'));
		}
		else
		{
			eval(print_standard_error('error_threadratevoted'));
		}
	 }
	 else
	 {
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "threadrate (threadid,userid,vote)
			VALUES ($threadid, $bbuserinfo[userid], $vote)
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "thread
			SET votetotal = votetotal + $vote, votenum = votenum + 1
			WHERE threadid = $threadid
		");
		set_bbarray_cookie('thread_rate', $threadid, $vote, 1);

		$url = "showthread.php?$session[sessionurl]t=$threadid&amp;page=$pagenumber&amp;pp=$perpage";
		eval(print_standard_redirect('redirect_threadrate_add'));
	 }
}
else
{
	// Check for cookie on user's computer for this threadid
	if ($rated AND !$vboptions['votechange'])
	{
		eval(print_standard_error('error_threadratevoted'));
	}

	// Check for entry in Database for this Ip Addr/Threadid
	if ($rating = $DB_site->query_first("
		SELECT vote, threadrateid FROM " . TABLE_PREFIX . "threadrate
		WHERE ipaddress = '" . addslashes(IPADDRESS) . "' AND threadid = $threadid
	"))
	{
		if ($vboptions['votechange'])
		{
			if ($vote != $rating['vote'])
			{
				$voteupdate = $vote - $rating['vote'];
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "threadrate
					SET vote = $vote
					WHERE threadrateid = $rating[threadrateid]
				");
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "thread
					SET votetotal = votetotal + $voteupdate
					WHERE threadid = $threadid
				");
			}
			$url = "showthread.php?$session[sessionurl]t=$threadid&amp;page=$pagenumber&amp;pp=$perpage";
			eval(print_standard_redirect('redirect_threadrate_update'));
		}
		else
		{
			eval(print_standard_error('error_threadratevoted'));
		}
	}
	else
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "threadrate (threadid,vote,ipaddress)
			VALUES ($threadid, $vote,'" . addslashes(IPADDRESS) . "')
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "thread
			SET votetotal = votetotal + $vote, votenum = votenum + 1
			WHERE threadid = $threadid
		");
		set_bbarray_cookie('thread_rate', $threadid, $vote, 1);

		$url = "showthread.php?$session[sessionurl]t=$threadid&amp;page=$pagenumber&amp;pp=$perpage";
		eval(print_standard_redirect('redirect_threadrate_add'));
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12825 $
|| ####################################################################
\*======================================================================*/
?>