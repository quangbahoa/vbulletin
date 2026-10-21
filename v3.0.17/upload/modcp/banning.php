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
$nozip = 1;

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 13127 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('banning', 'cpuser');
$specialtemplates = array('banemail');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
log_admin_action(iif(!empty($_REQUEST['username']), 'username = ' . $_REQUEST['username'], ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_banning']);

// check banning permissions
if (!($permissions['adminpermissions'] & CANCONTROLPANEL) AND (!can_moderate(0, 'canbanusers')))
{
	print_stop_message('no_permission_ban_users');
}

// set default action
if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// function to get a ban-end timestamp
function convert_date_to_timestamp($period)
{
	$p = explode('_', $period);

	if ($p[0] == 'P')
	{
		$time = 0;
		return 0;
	}
	else
	{
		$d = explode('-', date('H-i-n-j-Y'));
		$date = array(
			'h' => &$d[0],
			'm' => &$d[1],
			'M' => &$d[2],
			'D' => &$d[3],
			'Y' => &$d[4]
		);

		/*if ($date['m'] >= 30)
		{
			$date['h']++;
		}*/

		$date["$p[0]"] += $p[1];
		return mktime($date['h'], 0, 0, $date['M'], $date['D'], $date['Y']);
	}
}

// #############################################################################
// lift a ban

if ($_REQUEST['do'] == 'liftban')
{
	globalize($_REQUEST, array('userid' => INT, 'hash' => STR));

	if (!($permissions['adminpermissions'] & CANCONTROLPANEL) AND (!can_moderate(0, 'canunbanusers')))
	{
		print_stop_message('no_permission_un_ban_users');
	}

	if ($hash != $cpsession['hash'])
	{
		exit;
	}

	$user = $DB_site->query_first("
		SELECT user.userid, user.username, user.posts,
		userban.usergroupid, userban.displaygroupid, userban.customtitle, userban.usertitle,
		IF(userban.userid, 1, 0) AS banrecord,
		IF(usergroup.genericoptions & " . ISBANNEDGROUP . ", 1, 0) AS isbannedgroup
		FROM " . TABLE_PREFIX . "user AS user
		INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON(usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
		WHERE user.userid = $userid
	");

	// check we got a record back and that the returned user is in a banned group
	if (!$user OR !$user['isbannedgroup'])
	{
		print_stop_message('invalid_user_specified');
	}

	// get usergroup info
	$getusergroupid = iif($user['displaygroupid'], $user['displaygroupid'], $user['usergroupid']);
	if (!$getusergroupid)
	{
		$getusergroupid = 2; // ack! magic numbers!
	}
	$usergroup = $usergroupcache["$getusergroupid"];
	if ($user['customtitle'])
	{
		$usertitle = $user['usertitle'];
	}
	else if (!$usergroup['usertitle'])
	{
		$gettitle = $DB_site->query_first("
			SELECT title
			FROM " . TABLE_PREFIX . "usertitle
			WHERE minposts <= $user[posts]
			ORDER BY minposts DESC
		");
		$usertitle = $gettitle['title'];
	}
	else
	{
		$usertitle = $usergroup['usertitle'];
	}
	$dotitle = 'usertitle = \'' . addslashes($usertitle) . '\',';

	// check to see if there is a ban record for this user
	if ($user['banrecord'])
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET	$dotitle
				usergroupid = $user[usergroupid],
				displaygroupid = $user[displaygroupid],
				customtitle = $user[customtitle]
			WHERE userid = $user[userid]
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "userban
			WHERE userid = $user[userid]
		");
	}
	else
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user SET
			$dotitle
			usergroupid = 2,
			displaygroupid = 0
			WHERE userid = $user[userid]
		");
	}

	define('CP_REDIRECT', "banning.php?$session[sessionurl]");
	print_stop_message('lifted_ban_on_user_x_successfully', "<b>$user[userame]</b>");
}

// #############################################################################
// ban a user

if ($_POST['do'] == 'dobanuser')
{
	globalize($_POST, array(
		'username' => STR_NOHTML,
		'usergroupid' => INT,
		'period' => STR
	));

	/*$liftdate = convert_date_to_timestamp($period);
	echo "
	<p>Period: $period</p>
	<p>Banning <b>$username</b> into usergroup <i>{$usergroupcache[$usergroupid][title]}</i></p>
	<table>
	<tr><td>Time now:</td><td>" . vbdate('g:ia l jS F Y', TIMENOW, false, false) . "</td></tr>
	<tr><td>Lift date:</td><td>" . vbdate('g:ia l jS F Y', $liftdate, false, false) . "</td></tr>
	</table>";
	exit;*/

	// check that the target usergroup is valid
	if (!isset($usergroupcache["$usergroupid"]) OR !($usergroupcache["$usergroupid"]['genericoptions'] & ISBANNEDGROUP))
	{
		print_stop_message('invalid_usergroup_specified');
	}

	// check that the user exists
	$user = $DB_site->query_first("
		SELECT
		user.userid, user.username, user.usergroupid, user.displaygroupid, user.customtitle, user.usertitle,
		IF(moderator.moderatorid IS NULL, 0, 1) AS ismoderator
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator USING(userid)
		WHERE user.username = '" . addslashes($username) . "'
	");
	if (!$user OR $user['userid'] == $bbuserinfo['userid'] OR $user['usergroupid'] == 6)
	{
		print_stop_message('invalid_user_specified');
	}

	// check that user has permission to ban the person they want to ban
	if (!($permissions['adminpermissions'] & CANCONTROLPANEL))
	{
		if ($user['usergroupid'] == 5 OR $user['ismoderator'])
		{
			print_stop_message('no_permission_ban_non_registered_users');
		}
	}

	// check that the number of days is valid
	if ($period != 'PERMANENT' AND !preg_match('#^(D|M|Y)_[1-9][0-9]?$#', $period))
	{
		print_stop_message('invalid_ban_period_specified');
	}

	// if we've got this far all the incoming data is good
	if ($period == 'PERMANENT')
	{
		// make this ban permanent
		$liftdate = 0;
	}
	else
	{
		// get the unixtime for when this ban will be lifted
		$liftdate = convert_date_to_timestamp($period);
	}

	// update the user's title if they've specified a special user title for the banned group
	if ($usergroupcache["$usergroupid"]['usertitle'] != '')
	{
		$bantitlesql = "usertitle = '" . addslashes($usergroupcache["$usergroupid"]['usertitle']) . "', customtitle = 0,";
	}
	else
	{
		$bantitlesql = '';
	}

	// check to see if there is already a ban record for this user in the userban table
	if ($check = $DB_site->query_first("SELECT userid, liftdate FROM " . TABLE_PREFIX . "userban WHERE userid = $user[userid]"))
	{
		if ($liftdate < $check['liftdate'])
		{
			if (!($permissions['adminpermissions'] & CANCONTROLPANEL) AND (!can_moderate(0, 'canunbanusers')))
			{
				print_stop_message('no_permission_un_ban_users');
			}
		}

		// there is already a record - just update this record
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "userban SET
			adminid = $bbuserinfo[userid],
			bandate = " . TIMENOW . ",
			liftdate = $liftdate,
			adminid = $bbuserinfo[userid]
			WHERE userid = $user[userid]
		");
	}
	else
	{
		// insert a record into the userban table
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "userban
			(userid, usergroupid, displaygroupid, customtitle, usertitle, adminid, bandate, liftdate)
			VALUES
			($user[userid], $user[usergroupid], $user[displaygroupid], $user[customtitle], '" . addslashes($user['usertitle']) . "', $bbuserinfo[userid], " . TIMENOW . ", $liftdate)
		");
	}

	// update the user record
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user SET
		$bantitlesql
		usergroupid = $usergroupid,
		displaygroupid = 0
		WHERE userid = $user[userid]
	");

	define('CP_REDIRECT', 'banning.php');
	if ($period == 'PERMANENT')
	{
		print_stop_message('user_x_has_been_banned_permanently', $user['username']);
	}
	else
	{
		print_stop_message('user_x_has_been_banned_until_y', $user['username'], vbdate("$vboptions[dateformat] $vboptions[timeformat]", $liftdate));
	}
}

// #############################################################################
// user banning form

if ($_REQUEST['do'] == 'banuser')
{
	globalize($_REQUEST, array('userid' => INT, 'username' => STR_NOHTML, 'period' => STR));

	// fill in the username field if it's specified
	if (!$username AND $userid)
	{
		$user = $DB_site->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = $userid");
		$username = $user['username'];
	}

	// set a default banning period if there isn't one specified
	if (empty($period))
	{
		$period = 'D_7'; // 7 days
	}

	// make a list of usergroups into which to move this user
	$selectedid = 0;
	$usergroups = array();
	foreach ($usergroupcache AS $usergroupid => $usergroup)
	{
		if ($usergroup['genericoptions'] & ISBANNEDGROUP)
		{
			$usergroups["$usergroupid"] = $usergroup['title'];
			if ($selectedid == 0)
			{
				$selectedid = $usergroupid;
			}
		}
	}

	$temporary_phrase = $vbphrase['temporary_ban_options'];
	$permanent_phrase = $vbphrase['permanent_ban_options'];

	// make a list of banning period options
	$periodoptions = array(
		$temporary_phrase => array(
			'D_1'  => "1 $vbphrase[day]",
			'D_2'  => "2 $vbphrase[days]",
			'D_3'  => "3 $vbphrase[days]",
			'D_4'  => "4 $vbphrase[days]",
			'D_5'  => "5 $vbphrase[days]",
			'D_6'  => "6 $vbphrase[days]",
			'D_7'  => "7 $vbphrase[days]",
			'D_10' => "10 $vbphrase[days]",
			'D_14' => "2 $vbphrase[weeks]",
			'D_21' => "3 $vbphrase[weeks]",
			'M_1'  => "1 $vbphrase[month]",
			'M_2' => "2 $vbphrase[months]",
			'M_3' => "3 $vbphrase[months]",
			'M_4' => "4 $vbphrase[months]",
			'M_5' => "5 $vbphrase[months]",
			'M_6' => "6 $vbphrase[months]",
			'Y_1' => "1 $vbphrase[year]",
			'Y_2' => "2 $vbphrase[years]",
		),
		$permanent_phrase => array(
			'PERMANENT' => "$vbphrase[permanent] - $vbphrase[never_lift_ban]"
		)
	);

	foreach ($periodoptions["$temporary_phrase"] AS $thisperiod => $text)
	{
		if ($liftdate = convert_date_to_timestamp($thisperiod))
		{
			$periodoptions["$temporary_phrase"]["$thisperiod"] .= ' (' . vbdate($vboptions['dateformat'] . ' ' . $vboptions['timeformat'], $liftdate) . ')';
		}
	}

	print_form_header('banning', 'dobanuser');
	print_table_header($vbphrase['ban_user']);
	print_input_row($vbphrase['username'], 'username', $username, 0);
	print_select_row($vbphrase['move_user_to_usergroup'], 'usergroupid', $usergroups, $selectedid);
	print_select_row($vbphrase['lift_ban_after'], 'period', $periodoptions);
	print_submit_row($vbphrase['ban_user']);
}

// #############################################################################
// display users from 'banned' usergroups

if ($_REQUEST['do'] == 'modify')
{
	function construct_banned_user_row($user)
	{
		global $session, $usergroupcache, $vboptions, $permissions, $admincpdir, $vbphrase;
		global $cpsession;

		if ($user['liftdate'] == 0)
		{
			$user['banperiod'] = $vbphrase['permanent'];
			$user['banlift'] = $vbphrase['never'];
			$user['banremaining'] = $vbphrase['forever'];
		}
		else
		{
			$user['banlift'] = vbdate("$vboptions[dateformat], ~$vboptions[timeformat]", $user['liftdate']);
			$user['banperiod'] = ceil(($user['liftdate'] - $user['bandate']) / 86400);
			if ($user['banperiod'] == 1)
			{
				$user['banperiod'] .= " $vbphrase[day]";
			}
			else
			{
				$user['banperiod'] .= " $vbphrase[days]";
			}

			$remain = $user['liftdate'] - TIMENOW;
			$remain_days = floor($remain / 86400);
			$remain_hours = ceil(($remain - ($remain_days * 86400)) / 3600);
			if ($remain_hours == 24)
			{
				$remain_days += 1;
				$remain_hours = 0;
			}

			if ($remain_days < 0)
			{
				$user['banremaining'] = "<i>$vbphrase[will_be_lifted_soon]</i>";
			}
			else
			{
				if ($remain_days == 1)
				{
					$day_word = $vbphrase['day'];
				}
				else
				{
					$day_word = $vbphrase['days'];
				}
				if ($remain_hours == 1)
				{
					$hour_word = $vbphrase['hour'];
				}
				else
				{
					$hour_word = $vbphrase['hours'];
				}
				$user['banremaining'] = "$remain_days $day_word, $remain_hours $hour_word";
			}
		}
		$cell = array("<a href=\"" . iif(($permissions['adminpermissions'] & CANCONTROLPANEL), "../$admincpdir/") . "user.php?$session[sessionurl]do=edit&amp;userid=$user[userid]\"><b>$user[username]</b></a>");
		if ($user['bandate'])
		{
			$cell[] = iif($user['adminid'], "<a href=\"" . iif(($permissions['adminpermissions'] & CANCONTROLPANEL), "../$admincpdir/") . "user.php?$session[sessionurl]do=edit&amp;userid=$user[adminid]\">$user[adminname]</a>", $vbphrase['n_a']);
			$cell[] = vbdate($vboptions['dateformat'], $user['bandate']);
		}
		else
		{
			$cell[] = $vbphrase['n_a'];
			$cell[] = $vbphrase['n_a'];
		}
		$cell[] = $user['banperiod'];
		$cell[] = $user['banlift'];
		$cell[] = $user['banremaining'];
		$cell[] = construct_link_code($vbphrase['lift_ban'], "banning.php?$session[sessionurl]do=liftban&amp;userid=$user[userid]&amp;hash=$cpsession[hash]");

		return $cell;
	}

	$querygroups = array();
	foreach ($usergroupcache AS $usergroupid => $usergroup)
	{
		if ($usergroup['genericoptions'] & ISBANNEDGROUP)
		{
			$querygroups["$usergroupid"] = $usergroup['title'];
		}
	}
	if (empty($querygroups))
	{
		print_stop_message('no_groups_defined_as_banned');
	}

	// now query users from the specified groups
	$getusers = $DB_site->query("
		SELECT user.userid, user.username, user.usergroupid AS busergroupid,
		userban.usergroupid AS ousergroupid,
		IF(userban.displaygroupid = 0, userban.usergroupid, userban.displaygroupid) AS odisplaygroupid,
		bandate, liftdate,
		adminuser.userid AS adminid, adminuser.username AS adminname
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "user AS adminuser ON(adminuser.userid = userban.adminid)
		WHERE user.usergroupid IN(" . implode(',', array_keys($querygroups)) . ")
		ORDER BY userban.liftdate ASC, user.username
	");
	if ($DB_site->num_rows($getusers))
	{
		$users = array();
		while ($user = $DB_site->fetch_array($getusers))
		{
			$temporary = iif($user['liftdate'], 1, 0);
			$users["$temporary"][] = $user;
		}
	}
	$DB_site->free_result($getusers);

	// define the column headings
	$headercell = array(
		$vbphrase['username'],
		$vbphrase['banned_by'],
		$vbphrase['banned_on'],
		$vbphrase['ban_period'],
		$vbphrase['ban_will_be_lifted_on'],
		$vbphrase['ban_time_remaining'],
		$vbphrase['lift_ban']
	);

	// show temporarily banned users
	if (!empty($users[1]))
	{
		print_form_header('banning', 'banuser');
		print_table_header("$vbphrase[banned_users]: $vbphrase[temporary_ban] <span class=\"normal\">$vbphrase[usergroups]: " . implode(', ', $querygroups) . "</span>", 7);
		print_cells_row($headercell, 1);
		foreach ($users[1] AS $user)
		{
			print_cells_row(construct_banned_user_row($user));
		}
		print_description_row("<div class=\"smallfont\" align=\"center\">$vbphrase[all_times_are_gmt_x_time_now_is_y]</div>", 0, 7, 'thead');
		print_submit_row($vbphrase['ban_user'], 0, 7);
	}

	// show permanently banned users
	if (!empty($users[0]))
	{
		print_form_header('banning', 'banuser');
		construct_hidden_code('period', 'PERMANENT');
		print_table_header("$vbphrase[banned_users]: $vbphrase[permanent_ban] <span class=\"normal\">$vbphrase[usergroups]: " . implode(', ', $querygroups) . "</span>", 7);
		print_cells_row($headercell, 1);
		foreach ($users[0] AS $user)
		{
			print_cells_row(construct_banned_user_row($user));
		}
		print_submit_row($vbphrase['ban_user'], 0, 7);
	}

	if (empty($users))
	{
		print_stop_message('no_users_banned_from_x_board', "<b>$vboptions[bbtitle]</b>", "banning.php?$session[sessionurl]do=banuser");
	}

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13127 $
|| ####################################################################
\*======================================================================*/
?>