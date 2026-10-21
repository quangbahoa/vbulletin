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
define('THIS_SCRIPT', 'joinrequests');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'usercpmenu',
	'usercpnav'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'viewjoinrequests' => array(
		'JOINREQUESTS',
		'joinrequestsbit',
		'usercp_nav_folderbit',
	),
);

$actiontemplates['none'] = &$actiontemplates['viewjoinrequests'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// must be a logged in user to use this page
if (!$bbuserinfo['userid'])
{
	print_no_permission();
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'viewjoinrequests';
}

// #############################################################################
// process join requests
if ($_POST['do'] == 'processjoinrequests')
{
	globalize($_POST, array(
		'usergroupid' => INT,
		'pagenumber' => INT,
		'perpage' => INT,
		'request'
	));

	// check we have a valid usergroup
	if (!$usergroupid OR !isset($usergroupcache["$usergroupid"]))
	{
		$idname = $vbphrase['usergroup'];
		eval(print_standard_error('invalidid'));
	}

	// check we have some requests to work with
	if (!is_array($request) OR empty($request))
	{
		$idname = $vbphrase['join_request'];
		eval(print_standard_error('invalidid'));
	}

	// check permission to do authorizations in this group
	if (!($check = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "usergroupleader WHERE userid = $bbuserinfo[userid] AND usergroupid = $usergroupid")))
	{
		print_no_permission();
	}

	// initialize an array to store requests that will be authorized
	$auth = array();
	$delete = array();

	// sort the requests according to the action specified
	foreach ($request AS $requestid => $action)
	{
		$action = intval($action);
		$requestid = intval($requestid);
		switch($action)
		{
			case -1:	// this request will be ignored
				unset($request["$requestid"]);
				break;

			case  1:	// this request will be authorized
				$auth[] = $requestid;
				$delete[] = $requestid;
				break;

			case  0:	// this request will be denied
				$delete[] = $requestid;
				break;
		}
	}

	// if we have any accepted requests, make sure they are valid
	if (!empty($auth))
	{
		$users = $DB_site->query("
			SELECT req.userid, user.username, user.usergroupid, user.membergroupids, req.usergrouprequestid
			FROM " . TABLE_PREFIX . "usergrouprequest AS req
			INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
			WHERE usergrouprequestid IN(" . implode(', ', $auth) . ")
			ORDER BY user.username
		");
		$auth = array();
		while ($user = $DB_site->fetch_array($users))
		{
			if (!in_array($usergroupid, fetch_membergroupids_array($user)))
			{
				$auth[] = $user['userid'];
			}
		}

		// check that we STILL have some valid requests
		if (!empty($auth))
		{
			$updateQuery = "
				UPDATE " . TABLE_PREFIX . "user SET
				membergroupids = IF(membergroupids = '', $usergroupid, CONCAT(membergroupids, ',$usergroupid'))
				WHERE userid IN(" . implode(', ', $auth) . ")
			";
			$DB_site->query($updateQuery);
		}
	}

	// delete processed join requests
	if (!empty($delete))
	{
		$deleteQuery = "
			DELETE FROM " . TABLE_PREFIX . "usergrouprequest
			WHERE usergrouprequestid IN(" . implode(', ', $delete) . ")
		";
		$DB_site->query($deleteQuery);
	}

	$_REQUEST['forceredirect'] = 1;
	eval(print_standard_redirect('join_requests_processed'));
}

// #############################################################################
// view join requests
if ($_REQUEST['do'] == 'viewjoinrequests')
{
	globalize($_REQUEST, array(
		'usergroupid' => INT,
		'pagenumber' => INT,
		'perpage' => INT
	));

	if (!$usergroupid OR !isset($usergroupcache["$usergroupid"]))
	{
		$idname = $vbphrase['usergroup'];
		eval(print_standard_error('invalidid'));
	}

	$usergroups = array();

	// query usergroups of which bbuser is a leader
	$joinrequests = $DB_site->query("
		SELECT usergroupleader.usergroupid, COUNT(usergrouprequestid) AS requests
		FROM " . TABLE_PREFIX . "usergroupleader AS usergroupleader
		LEFT JOIN " . TABLE_PREFIX . "usergrouprequest AS usergrouprequest USING(usergroupid)
		WHERE usergroupleader.userid = $bbuserinfo[userid]
		GROUP BY usergroupleader.usergroupid
	");
	while ($joinrequest = $DB_site->fetch_array($joinrequests))
	{
		$usergroups["$joinrequest[usergroupid]"] = intval($joinrequest['requests']);
	}
	unset($joinrequest);
	$DB_site->free_result($joinrequests);

	// if we got no results, or if the specified usergroupid was not returned, show no permission
	if (empty($usergroups))
	{
		print_no_permission();
	}

	$usergroupbits = '';
	foreach ($usergroupcache AS $optionvalue => $usergroup)
	{
		if (isset($usergroups["$optionvalue"]))
		{
			$optiontitle = construct_phrase($vbphrase['x_y_requests'], $usergroupcache["$optionvalue"]['title'], vb_number_format($usergroups["$optionvalue"]));
			$optionselected = iif($optionvalue == $usergroupid, HTML_SELECTED, '');
			$optionclass = '';
			eval('$usergroupbits .= "' . fetch_template('option') . '";');
		}
	}

	// set a shortcut to the usergroupcache entry for this group
	$usergroup = &$usergroupcache["$usergroupid"];

	// initialize $joinrequestbits
	$joinrequestbits = '';

	$numrequests = &$usergroups["$usergroupid"];

	// if there are some requests for this usergroup, display them
	if ($numrequests > 0)
	{
		// set defaults
		sanitize_pageresults($numrequests, $pagenumber, $perpage, 100, 20);
		$startat = ($pagenumber - 1) * $perpage;

		$pagenav = construct_page_nav($numrequests, "joinrequests.php?$session[sessionurl]usergroupid=$usergroupid&amp;pp=$perpage");

		$requests = $DB_site->query("
			SELECT req.*, user.username, IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid
			FROM " . TABLE_PREFIX . "usergrouprequest AS req
			INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
			WHERE req.usergroupid = $usergroupid
			LIMIT $startat, $perpage
		");
		while ($request = $DB_site->fetch_array($requests))
		{
			$request['musername'] = fetch_musername($request);
			$request['date'] = vbdate($vboptions['dateformat'], $request['dateline'], 1);
			$request['time'] = vbdate($vboptions['timeformat'], $request['dateline']);

			exec_switch_bg();
			eval('$joinrequestbits .= "' . fetch_template('joinrequestsbit') . '";');
		}
	} // end if ($numrequests > 0)

	$show['joinrequests'] = iif($joinrequestbits != '', true, false);

	// draw cp nav bar
	require_once('./includes/functions_user.php');
	construct_usercp_nav('usergroups');

	// make the navbar elements
	$navbits = construct_navbits(array(
		"usercp.php?$session[sessionurl]" => $vbphrase['user_control_panel'],
		"profile.php?$session[sessionurl]do=editusergroups" => $vbphrase['group_memberships'],
		'' => "$vbphrase[join_requests]: '$usergroup[title]'" // <phrase> ?
	));
}

// #############################################################################
// spit out final HTML if we have got this far

// make navbar
eval('$navbar = "' . fetch_template('navbar') . '";');

// shell template
eval('print_output("' . fetch_template('JOINREQUESTS') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13240 $
|| ####################################################################
\*======================================================================*/
?>
