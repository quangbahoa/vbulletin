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
define('THIS_SCRIPT', 'moderator');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ####################### PRE-BACK-END ACTIONS ##########################
function exec_postvar_call_back()
{
	global $_REQUEST, $noheader;
	if ($_REQUEST['do'] == 'move' OR $_REQUEST['do'] == 'prune' OR $_REQUEST['do'] == 'useroptions')
	{
		$noheader = 1;
	}
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions.php'); // required for can_administer

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ############################### start add moderator ###############################
if ($_REQUEST['do'] == 'addmoderator')
{
	globalize($_REQUEST, array('forumid' => INT));

	if (can_administer('canadminforums'))
	{
		if ($forumid < 1)
		{
			$idname = $vbphrase['forum'];
			eval(print_standard_error('invalidid'));
		}
		else
		{
			exec_header_redirect("$admincpdir/index.php?$session[sessionurl_js]loc=" . urlencode("moderator.php?$session[sessionurl_js]do=add&f=$forumid"));
		}
	}
	else
	{
		print_no_permission();
	}
}

if ($_REQUEST['do'] == 'useroptions')
{
	$userid = verify_id('user', $_REQUEST['userid']);

	if (can_administer('canadminusers'))
	{
		exec_header_redirect("$admincpdir/index.php?$session[sessionurl_js]loc=" . urlencode("user.php?$session[sessionurl_js]do=edit&u=$userid"));
	}
	else if (can_moderate(0, 'canviewprofile'))
	{
		exec_header_redirect("$modcpdir/index.php?$session[sessionurl_js]loc=" . urlencode("user.php?$session[sessionurl_js]do=viewuser&u=$userid"));
	}
	else
	{
		print_no_permission();
	}

}

if ($_REQUEST['do'] == 'move')
{
	$forumid = verify_id('forum', $_REQUEST['forumid']);

	if (can_administer('canadminthreads'))
	{
		exec_header_redirect("$admincpdir/index.php?$session[sessionurl_js]loc=" . urlencode("thread.php?$session[sessionurl_js]do=move"));
	}
	else if (can_moderate($forumid, 'canmassmove'))
	{
		exec_header_redirect("$modcpdir/index.php?$session[sessionurl_js]loc=" . urlencode("thread.php?$session[sessionurl_js]do=move"));
	}
	else
	{
		print_no_permission();
	}
}

if ($_REQUEST['do'] == 'prune')
{
	$forumid = verify_id('forum', $_REQUEST['forumid']);

	if (can_administer('canadminthreads'))
	{
		exec_header_redirect("$admincpdir/index.php?$session[sessionurl_js]loc=" . urlencode("thread.php?$session[sessionurl_js]do=prune"));
	}
	else if (can_moderate($forumid, 'canmassprune'))
	{
		exec_header_redirect("$modcpdir/index.php?$session[sessionurl_js]loc=" . urlencode("thread.php?$session[sessionurl_js]do=prune"));
	}
	else
	{
		print_no_permission();
	}
}

if ($_REQUEST['do'] == 'modposts')
{
	if (can_moderate(0, 'canmoderateposts'))
	{
		exec_header_redirect("$modcpdir/index.php?$session[sessionurl_js]loc=" . urlencode("moderate.php?$session[sessionurl_js]do=posts"));
	}
	else
	{
		print_no_permission();
	}
}

if ($_REQUEST['do'] == 'modattach')
{
	if (can_moderate(0, 'canmoderateattachments'))
	{
		exec_header_redirect("$modcpdir/index.php?$session[sessionurl_js]loc=" . urlencode("moderate.php?$session[sessionurl_js]do=attachments"));
	}
	else
	{
		print_no_permission();
	}

}

//setup redirects for other options in moderators cp

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11293 $
|| ####################################################################
\*======================================================================*/
?>