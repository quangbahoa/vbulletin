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
define('CVS_REVISION', '$RCSfile$ - $Revision: 14966 $');
define('NOZIP', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('banning', 'cpuser');
$specialtemplates = array('banemail');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array('username' => TYPE_STR));
log_admin_action(!empty($vbulletin->GPC['username']) ? 'username = ' . $vbulletin->GPC['username'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_banning']);
$canbanuser = ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canbanusers')) ? true : false;
$canunbanuser = ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canunbanusers')) ? true : false;

// check banning permissions
if (!$canbanuser AND !$canunbanuser)
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
	$vbulletin->input->clean_array_gpc('r', array(
		'userid' => TYPE_INT
	));

	if (!$canunbanuser)
	{
		print_stop_message('no_permission_un_ban_users');
	}

	$user = $db->query_first("
		SELECT user.*,
		userban.usergroupid, userban.displaygroupid, userban.customtitle, userban.usertitle,
		IF(userban.userid, 1, 0) AS banrecord,
		IF(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isbannedgroup'] . ", 1, 0) AS isbannedgroup
		FROM " . TABLE_PREFIX . "user AS user
		INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON(usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
		WHERE user.userid = " . $vbulletin->GPC['userid'] . "
	");

	// check we got a record back and that the returned user is in a banned group
	if (!$user OR !$user['isbannedgroup'])
	{
		print_stop_message('invalid_user_specified');
	}

	// show confirmation message
	print_form_header('banning', 'doliftban');
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	print_table_header($vbphrase['lift_ban']);
	print_description_row(construct_phrase($vbphrase['confirm_ban_lift_on_x'], $user['username']));
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

if ($_POST['do'] == 'doliftban')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'userid' => TYPE_INT
	));

	if (!$canunbanuser)
	{
		print_stop_message('no_permission_un_ban_users');
	}

	$user = $db->query_first("
		SELECT user.*,
		userban.usergroupid, userban.displaygroupid, userban.customtitle, userban.usertitle,
		IF(userban.userid, 1, 0) AS banrecord,
		IF(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isbannedgroup'] . ", 1, 0) AS isbannedgroup
		FROM " . TABLE_PREFIX . "user AS user
		INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON(usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
		WHERE user.userid = " . $vbulletin->GPC['userid'] . "
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

	$usergroup = $vbulletin->usergroupcache["$getusergroupid"];
	if ($user['customtitle'])
	{
		$usertitle = $user['usertitle'];
	}
	else if (!$usergroup['usertitle'])
	{
		$gettitle = $db->query_first("
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

	$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
	$userdm->set_existing($user);
	$userdm->set('usertitle', $usertitle);
	$userdm->set('posts', $user['posts']); // This will activate the rank update

	// check to see if there is a ban record for this user
	if ($user['banrecord'])
	{
		$userdm->set('usergroupid', $user['usergroupid']);
		$userdm->set('displaygroupid', $user['displaygroupid']);
		$userdm->set('customtitle', $user['customtitle']);

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "userban
			WHERE userid = $user[userid]
		");
	}
	else
	{
		$userdm->set('usergroupid', 2);
		$user['usergroupid'] = 2;
		$userdm->set('displaygroupid', 0);
		$user['displaygroupid'] = 0;
	}

	$userdm->save();
	unset($userdm);

	define('CP_REDIRECT', 'banning.php' . $vbulletin->session->vars['sessionurl_q']);
	print_stop_message('lifted_ban_on_user_x_successfully', "<b>$user[userame]</b>");
}

// #############################################################################
// ban a user

if ($_POST['do'] == 'dobanuser')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'usergroupid' => TYPE_INT,
		'period'      => TYPE_STR,
		'reason'      => TYPE_STR
	));

	$vbulletin->GPC['username'] = htmlspecialchars_uni($vbulletin->GPC['username']);

	if (!$canbanuser)
	{
		print_stop_message('no_permission_ban_users');
	}

	/*$liftdate = convert_date_to_timestamp($vbulletin->GPC['period']);
	echo "
	<p>Period: {$vbulletin->GPC['period']}</p>
	<p>Banning <b>{$vbulletin->GPC['username']}</b> into usergroup <i>" . $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['title'] . "</i></p>
	<table>
	<tr><td>Time now:</td><td>" . vbdate('g:ia l jS F Y', TIMENOW, false, false) . "</td></tr>
	<tr><td>Lift date:</td><td>" . vbdate('g:ia l jS F Y', $liftdate, false, false) . "</td></tr>
	</table>";
	exit;*/

	// check that the target usergroup is valid
	if (!isset($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]) OR !($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isbannedgroup']))
	{
		print_stop_message('invalid_usergroup_specified');
	}

	// check that the user exists
	$user = $db->query_first("
		SELECT user.*,
			IF(moderator.moderatorid IS NULL, 0, 1) AS ismoderator
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator USING(userid)
		WHERE user.username = '" . $db->escape_string($vbulletin->GPC['username']) . "'
	");
	if (!$user OR $user['userid'] == $vbulletin->userinfo['userid'] OR is_member_of($user, 6))
	{
		print_stop_message('invalid_user_specified');
	}

	// check that user has permission to ban the person they want to ban
	if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		if ($user['usergroupid'] == 5 OR $user['ismoderator'])
		{
			print_stop_message('no_permission_ban_non_registered_users');
		}
	}

	// check that the number of days is valid
	if ($vbulletin->GPC['period'] != 'PERMANENT' AND !preg_match('#^(D|M|Y)_[1-9][0-9]?$#', $vbulletin->GPC['period']))
	{
		print_stop_message('invalid_ban_period_specified');
	}

	// if we've got this far all the incoming data is good
	if ($vbulletin->GPC['period'] == 'PERMANENT')
	{
		// make this ban permanent
		$liftdate = 0;
	}
	else
	{
		// get the unixtime for when this ban will be lifted
		$liftdate = convert_date_to_timestamp($vbulletin->GPC['period']);
	}


	// check to see if there is already a ban record for this user in the userban table
	if ($check = $db->query_first("SELECT userid, liftdate FROM " . TABLE_PREFIX . "userban WHERE userid = $user[userid]"))
	{
		if ($liftdate < $check['liftdate'])
		{
			if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND !can_moderate(0, 'canunbanusers'))
			{
				print_stop_message('no_permission_un_ban_users');
			}
		}

		// there is already a record - just update this record
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "userban SET
			bandate = " . TIMENOW . ",
			liftdate = $liftdate,
			adminid = " . $vbulletin->userinfo['userid'] . ",
			reason = '" . $db->escape_string($vbulletin->GPC['reason']) . "'
			WHERE userid = $user[userid]
		");
	}
	else
	{
		// insert a record into the userban table
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "userban
			(userid, usergroupid, displaygroupid, customtitle, usertitle, adminid, bandate, liftdate, reason)
			VALUES
			($user[userid], $user[usergroupid], $user[displaygroupid], $user[customtitle], '" . $db->escape_string($user['usertitle']) . "', " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ", $liftdate, '" . $db->escape_string($vbulletin->GPC['reason']) . "')
		");
	}

	// update the user record
	$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
	$userdm->set_existing($user);
	$userdm->set('usergroupid', $vbulletin->GPC['usergroupid']);
	$userdm->set('displaygroupid', 0);
	$userdm->set('posts', $user['posts']); // This will activate the rank update

	// update the user's title if they've specified a special user title for the banned group
	if ($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle'] != '')
	{
		$userdm->set('usertitle', $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle']);
		$userdm->set('customtitle', 0);
	}

	$userdm->save();
	unset($userdm);

	define('CP_REDIRECT', 'banning.php');
	if ($vbulletin->GPC['period'] == 'PERMANENT')
	{
		print_stop_message('user_x_has_been_banned_permanently', $user['username']);
	}
	else
	{
		print_stop_message('user_x_has_been_banned_until_y', $user['username'], vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $liftdate));
	}
}

// #############################################################################
// user banning form

if ($_REQUEST['do'] == 'banuser')
{

	$vbulletin->input->clean_array_gpc('r', array(
		'userid'   => TYPE_INT,
		'period'   => TYPE_STR
	));

	if (!$canbanuser)
	{
		print_stop_message('no_permission_ban_users');
	}

	// fill in the username field if it's specified
	if (!$vbulletin->GPC['username'] AND $vbulletin->GPC['userid'])
	{
		$user = $db->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = " . $vbulletin->GPC['userid']);
		$vbulletin->GPC['username'] = $user['username'];
	}
	else
	{
		$vbulletin->GPC['username'] = htmlspecialchars_uni($vbulletin->GPC['username']);
	}

	// set a default banning period if there isn't one specified
	if (empty($vbulletin->GPC['period']))
	{
		$vbulletin->GPC['period'] = 'D_7'; // 7 days
	}

	// make a list of usergroups into which to move this user
	$selectedid = 0;
	$usergroups = array();
	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		if ($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isbannedgroup'])
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
			$periodoptions["$temporary_phrase"]["$thisperiod"] .= ' (' . vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $liftdate) . ')';
		}
	}

	print_form_header('banning', 'dobanuser');
	print_table_header($vbphrase['ban_user']);
	print_input_row($vbphrase['username'], 'username', $vbulletin->GPC['username'], 0);
	print_select_row($vbphrase['move_user_to_usergroup'], 'usergroupid', $usergroups, $selectedid);
	print_select_row($vbphrase['lift_ban_after'], 'period', $periodoptions);
	print_input_row($vbphrase['user_ban_reason'], 'reason', '', true, 50, 250);
	print_submit_row($vbphrase['ban_user']);
}

// #############################################################################
// display users from 'banned' usergroups

if ($_REQUEST['do'] == 'modify')
{
	function construct_banned_user_row($user)
	{
		global $vbulletin, $vbphrase;

		if ($user['liftdate'] == 0)
		{
			$user['banperiod'] = $vbphrase['permanent'];
			$user['banlift'] = $vbphrase['never'];
			$user['banremaining'] = $vbphrase['forever'];
		}
		else
		{
			$user['banlift'] = vbdate($vbulletin->options['dateformat'] . ', ~' . $vbulletin->options['timeformat'], $user['liftdate']);
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
		$cell = array("<a href=\"" . iif(($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']), '../' . $vbulletin->config['Misc']['admincpdir'] . '/') . 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=$user[userid]\"><b>$user[username]</b></a>");
		if ($user['bandate'])
		{
			$cell[] = iif($user['adminid'], "<a href=\"" . iif(($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']), '../' . $vbulletin->config['Misc']['admincpdir'] . '/') . 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=$user[adminid]\">$user[adminname]</a>", $vbphrase['n_a']);
			$cell[] = vbdate($vbulletin->options['dateformat'], $user['bandate']);
		}
		else
		{
			$cell[] = $vbphrase['n_a'];
			$cell[] = $vbphrase['n_a'];
		}
		$cell[] = $user['banperiod'];
		$cell[] = $user['banlift'];
		$cell[] = $user['banremaining'];
		if (($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR can_moderate(0, 'canunbanusers'))
		{
			$cell[] = construct_link_code($vbphrase['lift_ban'], 'banning.php?' . $vbulletin->session->vars['sessionurl'] . "do=liftban&amp;u=$user[userid]");
		}


		$cell[] = $user['reason'];

		return $cell;
	}

	$querygroups = array();
	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		if ($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isbannedgroup'])
		{
			$querygroups["$usergroupid"] = $usergroup['title'];
		}
	}
	if (empty($querygroups))
	{
		print_stop_message('no_groups_defined_as_banned');
	}

	// now query users from the specified groups
	$getusers = $db->query_read("
		SELECT user.userid, user.username, user.usergroupid AS busergroupid,
		userban.usergroupid AS ousergroupid,
		IF(userban.displaygroupid = 0, userban.usergroupid, userban.displaygroupid) AS odisplaygroupid,
		bandate, liftdate, reason,
		adminuser.userid AS adminid, adminuser.username AS adminname
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "user AS adminuser ON(adminuser.userid = userban.adminid)
		WHERE user.usergroupid IN(" . implode(',', array_keys($querygroups)) . ")
		ORDER BY userban.liftdate ASC, user.username
	");
	if ($db->num_rows($getusers))
	{
		$users = array();
		while ($user = $db->fetch_array($getusers))
		{
			$temporary = iif($user['liftdate'], 1, 0);
			$users["$temporary"][] = $user;
		}
	}
	$db->free_result($getusers);

	// define the column headings
	$headercell = array(
		$vbphrase['username'],
		$vbphrase['banned_by'],
		$vbphrase['banned_on'],
		$vbphrase['ban_period'],
		$vbphrase['ban_will_be_lifted_on'],
		$vbphrase['ban_time_remaining']
	);
	if (($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR can_moderate(0, 'canunbanusers'))
	{
		$headercell[] = $vbphrase['lift_ban'];
	}
	$headercell[] = $vbphrase['ban_reason'];

	// show temporarily banned users
	if (!empty($users[1]))
	{
		print_form_header('banning', 'banuser');
		print_table_header("$vbphrase[banned_users]: $vbphrase[temporary_ban] <span class=\"normal\">$vbphrase[usergroups]: " . implode(', ', $querygroups) . "</span>", 8);
		print_cells_row($headercell, 1);
		foreach ($users[1] AS $user)
		{
			print_cells_row(construct_banned_user_row($user));
		}
		print_description_row("<div class=\"smallfont\" align=\"center\">$vbphrase[all_times_are_gmt_x_time_now_is_y]</div>", 0, 8, 'thead');
		if ($canbanuser)
		{
			print_submit_row($vbphrase['ban_user'], 0, 8);
		}
		else
		{
			print_table_footer();
		}
	}

	// show permanently banned users
	if (!empty($users[0]))
	{
		print_form_header('banning', 'banuser');
		construct_hidden_code('period', 'PERMANENT');
		print_table_header("$vbphrase[banned_users]: $vbphrase[permanent_ban] <span class=\"normal\">$vbphrase[usergroups]: " . implode(', ', $querygroups) . '</span>', 8);
		print_cells_row($headercell, 1);
		foreach ($users[0] AS $user)
		{
			print_cells_row(construct_banned_user_row($user));
		}
		print_submit_row($vbphrase['ban_user'], 0, 8);
	}

	if (empty($users))
	{
		if ($canbanuser)
		{
			print_stop_message('no_users_banned_from_x_board_click_here', '<b>' . $vbulletin->options['bbtitle'] . '</b>', 'banning.php?' . $vbulletin->session->vars['sessionurl'] . 'do=banuser');
		}
		else
		{
			print_stop_message('no_users_banned_from_x_board', '<b>' . $vbulletin->options['bbtitle'] . '</b>');
		}
	}

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14966 $
|| ####################################################################
\*======================================================================*/
?>
