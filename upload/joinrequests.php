<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'joinrequests');
define('CSRF_PROTECTION', true);

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
		'USERCP_SHELL',
	),
);

$actiontemplates['none'] = &$actiontemplates['viewjoinrequests'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// must be a logged in user to use this page
if (!$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'viewjoinrequests';
}

($hook = vBulletinHook::fetch_hook('joinrequest_start')) ? eval($hook) : false;

// #############################################################################
// process join requests
if ($_POST['do'] == 'processjoinrequests')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'usergroupid' => TYPE_UINT,
		'request' => TYPE_ARRAY_INT,
	));

	($hook = vBulletinHook::fetch_hook('joinrequest_process_start')) ? eval($hook) : false;

	// check we have a valid usergroup
	if (!$vbulletin->GPC['usergroupid'] OR !isset($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['usergroup'], $vbulletin->options['contactuslink'])));
	}

	// check we have some requests to work with
	if (empty($vbulletin->GPC['request']))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['join_request'], $vbulletin->options['contactuslink'])));
	}

	// check permission to do authorizations in this group
	if (!($check = $db->query_first_slave("SELECT * FROM " . TABLE_PREFIX . "usergroupleader WHERE userid = " . $vbulletin->userinfo['userid'] . " AND usergroupid = " . $vbulletin->GPC['usergroupid'])))
	{
		print_no_permission();
	}

	// initialize an array to store requests that will be authorized
	$auth = array();
	$delete = array();

	// sort the requests according to the action specified
	foreach ($vbulletin->GPC['request'] AS $requestid => $action)
	{
		$requestid = intval($requestid);
		switch($action)
		{
			case -1:	// this request will be ignored
				unset($vbulletin->GPC['request']["$requestid"]);
				break;

			case  1:	// this request will be authorized and then removed
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
		$users = $db->query_read_slave("
			SELECT req.userid, user.username, user.usergroupid, user.membergroupids, req.usergrouprequestid
			FROM " . TABLE_PREFIX . "usergrouprequest AS req
			INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
			WHERE usergrouprequestid IN(" . implode(', ', $auth) . ")
			ORDER BY user.username
		");
		$auth = array();
		while ($user = $db->fetch_array($users))
		{
			if (!in_array($vbulletin->GPC['usergroupid'], fetch_membergroupids_array($user)))
			{
				$auth[] = $user['userid'];
			}
		}

		// check that we STILL have some valid requests
		if (!empty($auth))
		{
			$updateQuery = "
				UPDATE " . TABLE_PREFIX . "user SET
				membergroupids = IF(membergroupids = '', " . $vbulletin->GPC['usergroupid'] . ", CONCAT(membergroupids, '," . $vbulletin->GPC['usergroupid'] . "'))
				WHERE userid IN(" . implode(', ', $auth) . ")
			";
			$db->query_write($updateQuery);
		}
	}

	// delete processed join requests
	if (!empty($delete))
	{
		$deleteQuery = "
			DELETE FROM " . TABLE_PREFIX . "usergrouprequest
			WHERE usergrouprequestid IN(" . implode(', ', $delete) . ")
		";
		$db->query_write($deleteQuery);
	}

	($hook = vBulletinHook::fetch_hook('joinrequest_process_complete')) ? eval($hook) : false;

	eval(print_standard_redirect('join_requests_processed', true, true));
}

// #############################################################################
// view join requests
if ($_REQUEST['do'] == 'viewjoinrequests')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'usergroupid' => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'perpage' => TYPE_UINT
	));

	$usergroupid = $vbulletin->GPC['usergroupid'];

	($hook = vBulletinHook::fetch_hook('joinrequest_view_start')) ? eval($hook) : false;

	if (!$vbulletin->GPC['usergroupid'] OR !isset($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['usergroup'], $vbulletin->options['contactuslink'])));
	}

	$usergroups = array();

	// query usergroups of which bbuser is a leader
	$joinrequests = $db->query_read_slave("
		SELECT usergroupleader.usergroupid, COUNT(usergrouprequestid) AS requests
		FROM " . TABLE_PREFIX . "usergroupleader AS usergroupleader
		LEFT JOIN " . TABLE_PREFIX . "usergrouprequest AS usergrouprequest USING(usergroupid)
		WHERE usergroupleader.userid = " . $vbulletin->userinfo['userid'] . "
		GROUP BY usergroupleader.usergroupid
	");
	while ($joinrequest = $db->fetch_array($joinrequests))
	{
		$usergroups["{$joinrequest['usergroupid']}"] = intval($joinrequest['requests']);
	}
	unset($joinrequest);
	$db->free_result($joinrequests);

	// if we got no results, or if the specified usergroupid was not returned, show no permission
	if (empty($usergroups))
	{
		print_no_permission();
	}

	$usergroupbits = '';
	foreach ($vbulletin->usergroupcache AS $optionvalue => $usergroup)
	{
		if (isset($usergroups["$optionvalue"]))
		{
			$optiontitle = construct_phrase($vbphrase['x_y_requests'], $vbulletin->usergroupcache["$optionvalue"]['title'], vb_number_format($usergroups["$optionvalue"]));
			$optionselected = iif($optionvalue == $vbulletin->GPC['usergroupid'], 'selected="selected"', '');
			$optionclass = '';
			eval('$usergroupbits .= "' . fetch_template('option') . '";');
		}
	}

	// set a shortcut to the vbulletin->usergroupcache entry for this group
	$usergroup =& $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"];

	// initialize $joinrequestbits
	$joinrequestbits = '';

	$numrequests =& $usergroups["{$vbulletin->GPC['usergroupid']}"];

	// if there are some requests for this usergroup, display them
	if ($numrequests > 0)
	{
		// set defaults
		sanitize_pageresults($numrequests, $vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage'], 100, 20);
		$startat = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage'];

		$pagenav = construct_page_nav($vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage'], $numrequests, 'joinrequests.php?' . $vbulletin->session->vars['sessionurl'] . "usergroupid={$vbulletin->GPC['usergroupid']}&amp;pp=" . $vbulletin->GPC['perpage']);

		$requests = $db->query_read_slave("
			SELECT req.*, user.username, IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
			FROM " . TABLE_PREFIX . "usergrouprequest AS req
			INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
			WHERE req.usergroupid = " . $vbulletin->GPC['usergroupid'] . "
			LIMIT $startat, " . $vbulletin->GPC['perpage'] . "
		");
		while ($request = $db->fetch_array($requests))
		{
			fetch_musername($request);
			$request['date'] = vbdate($vbulletin->options['dateformat'], $request['dateline'], 1);
			$request['time'] = vbdate($vbulletin->options['timeformat'], $request['dateline']);

			exec_switch_bg();

			($hook = vBulletinHook::fetch_hook('joinrequest_view_bit')) ? eval($hook) : false;

			eval('$joinrequestbits .= "' . fetch_template('joinrequestsbit') . '";');
		}
	} // end if ($numrequests > 0)

	$show['joinrequests'] = iif($joinrequestbits != '', true, false);

	// make the navbar elements
	$navbits = construct_navbits(array(
		'usercp.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['user_control_panel'],
		'profile.php?' . $vbulletin->session->vars['sessionurl'] . "do=editusergroups" => $vbphrase['group_memberships'],
		'' => "$vbphrase[join_requests]: '$usergroup[title]'" // <phrase> ?
	));
}

// #############################################################################
// spit out final HTML if we have got this far

// build the cp nav
require_once(DIR . '/includes/functions_user.php');
construct_usercp_nav('usergroups');

($hook = vBulletinHook::fetch_hook('joinrequest_complete')) ? eval($hook) : false;

eval('$navbar = "' . fetch_template('navbar') . '";');
eval('$HTML = "' . fetch_template('JOINREQUESTS') . '";');

// shell template
eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
