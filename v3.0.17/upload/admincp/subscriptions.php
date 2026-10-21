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
define('CVS_REVISION', '$RCSfile$ - $Revision: 11304 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('subscription');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_subscriptions.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif(isset($_REQUEST['userid']), "user id = $_REQUEST[userid]", iif(isset($_REQUEST['subscriptionid']), "subscriptionid id = $_REQUEST[subscriptionid]")));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['subscription_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start Add #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{

	$OUTERTABLEWIDTH = '95%';
	$INNERTABLEWIDTH = '100%';
	?>
	<table cellpadding="0" cellspacing="0" border="0" width="<?php echo $OUTERTABLEWIDTH; ?>" align="center"><tr valign="top"><td>
	<table cellpadding="4" cellspacing="0" border="0" align="center" width="100%" class="tborder">
	<?php
	print_form_header('subscriptions', 'update', 0, 0);

	if ($_REQUEST['do'] == 'add')
	{
		print_table_header($vbphrase['add_new_subscription']);

	}
	else
	{
		$sub = $DB_site->query_first("
			SELECT * FROM " . TABLE_PREFIX . "subscription
			WHERE subscriptionid = $_REQUEST[subscriptionid]
		");
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['subscription'], $sub['title'], $sub['subscriptionid']));
		construct_hidden_code('subscriptionid', $sub['subscriptionid']);
		$sub['cost'] = unserialize($sub['cost']);
	}

	print_input_row($vbphrase['title'], 'sub[title]', $sub['title']);
	print_textarea_row($vbphrase['description'], 'sub[description]', $sub['description']);
	print_checkbox_row($vbphrase['active'], "sub[active]", $sub['active']);

	print_input_select_row($vbphrase['subscription_length'], 'sub[length]', $sub['length'], 'sub[units]', array('D' => $vbphrase['days'], 'W' => $vbphrase['weeks'], 'M' => $vbphrase['months'], 'Y' => $vbphrase['years']), $sub['units'], 1, 4);

	print_input_row($vbphrase['cost_in_us_dollars'], 'sub[cost][usd]', number_format($sub['cost']['usd'], 2));
	print_input_row($vbphrase['cost_in_pounds_sterling'], 'sub[cost][gbp]', number_format($sub['cost']['gbp'], 2));
	print_input_row($vbphrase['cost_in_euros'], 'sub[cost][eur]', number_format($sub['cost']['eur'], 2));
	print_input_row($vbphrase['cost_in_aud_dollars'], 'sub[cost][aud]', number_format($sub['cost']['aud'], 2));
	print_input_row($vbphrase['cost_in_cad_dollars'], 'sub[cost][cad]', number_format($sub['cost']['cad'], 2));

	?>
	</table>
	</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>
	<table cellpadding="4" cellspacing="0" border="0" align="center" width="100%" class="tborder">
	<?php
	// USERGROUP SECTION
	print_table_header($vbphrase['usergroup_options']);
	print_chooser_row($vbphrase['primary_usergroup'], 'sub[nusergroupid]', 'usergroup', $sub['nusergroupid'], $vbphrase['no_change']);
	print_membergroup_row($vbphrase['additional_usergroups'], 'membergroup', 0, $sub);
	?>
	</table>
	</tr>
	<?php

	print_table_break('', $OUTERTABLEWIDTH);
	print_table_header($vbphrase['forums']);
	print_description_row($vbphrase['here_you_can_select_which_forums_the_user']);

	require_once('./includes/functions_databuild.php');
	cache_forums();
	$forums = unserialize($sub['forums']);
	if (is_array($forumcache))
	{
		foreach ($forumcache AS $forumid => $forum)
		{
			if ($forums["$forum[forumid]"] == 1)
			{
				$sel = 1;
			}
			else

			{
				$sel = -1;
			}
			$radioname = 'forums[' . $forum['forumid'] . ']';
			print_label_row(construct_depth_mark($forum['depth'], '- - ') . ' ' . $forum['title'],"
				<label for=\"rb_1_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"1\" id=\"rb_1_$radioname\" tabindex=\"1\"" . iif($sel==1, ' checked="checked"') . " />" . $vbphrase['yes'] . "</label>
				<label for=\"rb_0_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"-1\" for=\"rb_0_$radioname\" tabindex=\"1\"" . iif($sel==-1, ' checked="checked"') . " />" . $vbphrase['default'] . "</label>
			");
		}
	}
	$tableadded = 1;
	print_submit_row(iif($_REQUEST['do'] == 'add', $vbphrase['save'], $vbphrase['update']));

}

// ###################### Start Update #######################
if ($_REQUEST['do'] == 'update')
{
	$sub = $_POST['sub'];

	$sub['title'] = htmlspecialchars_uni($sub['title']);

	$subscriptionid = intval($_REQUEST['subscriptionid']);
	$sub['length'] = intval($sub['length']);
	$sub['active'] = intval($sub['active']);

	$lengths = array('D' => 'days', 'W' => 'weeks', 'M' => 'months', 'Y' => 'years');
	if (strtotime("now + $sub[length] " . $lengths["$sub[units]"]) == -1 OR $sub['length'] <= 0)
	{
		print_stop_message('invalid_subscription_length');
	}

	foreach ($_REQUEST['forums'] AS $key => $value)
	{
		if ($value != '-1')
		{
			$aforums[$key] = $value;
		}
	}

	$sub['membergroupids'] = '';
	if (is_array($_REQUEST['membergroup']))
	{
		$sub['membergroupids'] = implode(',', $_REQUEST['membergroup']);
	}
	$sub['forums'] = serialize($aforums);

	foreach ($sub['cost'] AS $currency => $value)
	{
		$sub['cost']["$currency"] = number_format($value, 2);
	}
	$sub['cost'] = serialize($sub['cost']);

	if (empty($sub['title']) OR !$sub['length'])
	{
		print_stop_message('please_complete_required_fields');
	}

	if (empty($subscriptionid))
	{
		$DB_site->query(fetch_query_sql($sub, 'subscription'));
	}
	else
	{
		$DB_site->query(fetch_query_sql($sub, 'subscription', "WHERE subscriptionid=$subscriptionid"));
	}

	define('CP_REDIRECT', 'subscriptions.php?do=modify');
	print_stop_message('saved_subscription_x_successfully', $sub['title']);

}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'remove')
{
	print_delete_confirmation('subscription', $_REQUEST['subscriptionid'], 'subscriptions', 'kill', 'subscription', 0, $vbphrase['doing_this_will_remove_all_of_this_subscriptions_members_and_their_access']);
}

// ###################### Start Kill #######################
if ($_POST['do'] == 'kill')
{

	$subscriptionid = intval($_POST['subscriptionid']);

	$users = $DB_site->query("
		SELECT * FROM " . TABLE_PREFIX . "subscriptionlog
		WHERE subscriptionid = $subscriptionid AND
		status = 1
	");
	while ($user = $DB_site->fetch_array($users))
	{
		delete_user_subscription($subscriptionid, $user['userid']);
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = $subscriptionid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscriptionlog WHERE subscriptionid = $subscriptionid");

	define('CP_REDIRECT', 'subscriptions.php?do=modify');
	print_stop_message('deleted_subscription_successfully');

}

// ###################### Start find #######################
if ($_REQUEST['do'] == 'find')
{

	$condition = '1=1';
	$condition .= iif($_REQUEST['subscriptionid'], " AND subscriptionid=$_REQUEST[subscriptionid]");
	$condition .= iif(isset($_REQUEST['status']), " AND status=$_REQUEST[status]");

	if (empty($_REQUEST['limitstart']))
	{
		$limitstart = 0;
	}
	else

	{
		$limitstart--;
	}
	if (empty($_REQUEST['limitnumber']))
	{
		$limitnumber = 99999999;
	}

	$searchquery = "
		SELECT *
		FROM " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE $condition
			AND user.userid = subscriptionlog.userid
		ORDER BY username $direction
		LIMIT $limitstart, $limitnumber
	";
	$users = $DB_site->query($searchquery);

	$countusers['users'] = $DB_site->num_rows($users);

	if (!$countusers['users'])
	{
		print_stop_message('no_matches_found');
	}
	else
	{
		$limitfinish = $limitstart + $limitnumber;

		$subs = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "subscription ORDER BY subscriptionid");
		while ($sub = $DB_site->fetch_array($subs))
		{
			$subcache["$sub[subscriptionid]"] = $sub['title'];
		}
		$DB_site->free_result($subs);

		print_form_header('user', 'find');
		print_table_header(construct_phrase($vbphrase['showing_subscriptions_x_to_y_of_z'], ($limitstart + 1), iif($limitfinish > $countusers['users'], $countusers['users'], $limitfinish), $countusers[users]), 5);
		print_cells_row(array($vbphrase['title'], $vbphrase['username'], $vbphrase['start_date'], $vbphrase['status'], $vbphrase['controls']), 1);
		// now display the results
		while ($user=$DB_site->fetch_array($users))
		{
			$cell = array();
			$cell[] = $subcache["$user[subscriptionid]"];
			$cell[] = "<a href=\"user.php?$session[sessionurl]do=edit&userid=$user[userid]\"><b>$user[username]</b></a>&nbsp;";
			$cell[] = vbdate($vboptions['dateformat'], $user['regdate']);
			$cell[] = iif($user['status'], $vbphrase['active'], $vbphrase['disabled']);
			$cell[] = construct_button_code($vbphrase['edit'], "subscriptions.php?$session[sessionurl]do=adjust&subscriptionlogid=$user[subscriptionlogid]");
			print_cells_row($cell);
		}

		if ($limitnumber != 99999999 AND $limitfinish < $countusers['users'])
		{
			// a nifty bit of code to make hidden form fields based on the incoming variables
			// makes for smaller HTML and means we don't have to manually input everything
			foreach ($_REQUEST AS $key => $val)
			{
				switch ($key)
				{
					case 's':
						break;
					case 'do':
						break;
					case 'limitstart':
						break;
					case 'bblastvisit':
						break;
					case 'bbuserid':
						break;
					case 'bbpassword':
						break;
					default:
						switch ($val)
						{
							case '':
								break;
							case 0:
								break;
							default:
								construct_hidden_code($key, $val);
						}
				}
			}
			construct_hidden_code('limitstart', $limitstart + $limitnumber + 1);
			construct_hidden_code('orderby', $orderby);
			construct_hidden_code('direction', $direction);
			print_submit_row($vbphrase['next_page'], 0, $colspan, $vbphrase['go_back']);
		}
		else

		{
			print_table_footer();
		}
	}
}

// ###################### Start status #######################
if ($_POST['do'] == 'status')
{
	globalize($_POST, array('subscriptionlogid' => INT, 'subscriptionid' => INT, 'userid' => INT, 'status' => INT, 'regdate', 'expirydate', 'username' => STR_NOHTML));

	require_once('./includes/functions_misc.php');
	//$regdate = vbmktime(intval($regdate['hour']), intval($regdate['minute']), 0, intval($regdate['month']), intval($regdate['day']), intval($regdate['year']));
	$expirydate = vbmktime(intval($expirydate['hour']), intval($expirydate['minute']), 0, intval($expirydate['month']), intval($expirydate['day']), intval($expirydate['year']));

	if ($expirydate < 0)
	{
		print_stop_message('invalid_subscription_length');
	}
	if ($userid)
	{ // already existing entry
		if (!$status)
		{
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "subscriptionlog
				SET expirydate = $expirydate
				WHERE userid = $userid
					AND subscriptionid = $subscriptionid
			");
			delete_user_subscription($subscriptionid, $userid);
		}
		else
		{
			build_user_subscription($subscriptionid, $userid, 0, $expirydate);
		}
	}
	else
	{
		$userinfo = $DB_site->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . addslashes($username) . "'
		");

		if (!$userinfo['userid'])
		{
			print_stop_message('no_users_matched_your_query');
		}

		build_user_subscription($subscriptionid, $userinfo['userid'], 0, $expirydate);

	}

	define('CP_REDIRECT', "subscriptions.php?do=find&subscriptionid=$subscriptionid");
	print_stop_message('saved_subscription_x_successfully', $sub['title']);
}

// ###################### Start status #######################
if ($_REQUEST['do'] == 'adjust')
{
	globalize($_REQUEST, array('subscriptionlogid' => INT, 'subscriptionid' => INT));

	print_form_header('subscriptions', 'status');

	if ($subscriptionlogid)
	{ // already exists
		$sub = $DB_site->query_first("
			SELECT subscriptionlog.*, username FROM " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
			LEFT JOIN " . TABLE_PREFIX . "user USING(userid)
			WHERE subscriptionlogid = $_REQUEST[subscriptionlogid]
		");
		print_table_header(construct_phrase($vbphrase['edit_subscription_for_x'], $sub['username']));
		construct_hidden_code('userid', $sub['userid']);
		$subscriptionid = $sub['subscriptionid'];
	}
	else
	{
		print_table_header($vbphrase['add_user']);
		$subinfo = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = $subscriptionid");

		$sub = array(
			'regdate'	=> TIMENOW,
			'status'	=> 1,
			'expirydate'	=> fetch_proper_expirydate(TIMENOW, $subinfo['length'], $subinfo['units'])
		);
		print_input_row($vbphrase['username'], 'username', '', 0);
	}

	construct_hidden_code('subscriptionid', $subscriptionid);

	print_label_row($vbphrase['start_date'], '<span>' . vbdate($vboptions['dateformat'] . ' ' . $vboptions['timeformat'], $sub['regdate']) . '</span>');
	print_time_row($vbphrase['expiry_date'], 'expirydate', $sub['expirydate']);
	print_radio_row($vbphrase['active'], 'status', array(
		0 => $vbphrase['no'],
		1 => $vbphrase['yes']
	), $sub['status'], 'smallfont');
	print_submit_row();
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{

	$options = array(
		'edit' => $vbphrase['edit'],
		'remove' => $vbphrase['delete'],
		'view' => $vbphrase['view_users'],
		'addu' => $vbphrase['add_user']
	);

	?>
	<script type="text/javascript">
	function js_forum_jump(sid)
	{
		action = eval("document.cpform.s" + sid + ".options[document.cpform.s" + sid + ".selectedIndex].value");
		if (action != '')
		{
			switch (action)
			{
				case 'edit': page = "subscriptions.php?do=edit&subscriptionid="; break;
				case 'remove': page = "subscriptions.php?do=remove&subscriptionid="; break;
				case 'view': page = "subscriptions.php?do=find&subscriptionid="; break;
				case 'addu': page = "subscriptions.php?do=adjust&subscriptionid="; break;
			}
			document.cpform.reset();
			jumptopage = page + sid + "&s=<?php echo $session['sessionhash']; ?>";
			window.location = jumptopage;
		}
		else
		{
			alert("<?php echo $vbphrase['invalid_action_specified']; ?>");
		}
	}
	</script>
	<?php

	print_form_header();
	print_table_header($vbphrase['subscription_manager'], 6);
	print_cells_row(array($vbphrase['title'], $vbphrase['cost'], $vbphrase['active'], $vbphrase['completed'], $vbphrase['total'], $vbphrase['controls']), 1, 'tcat', 1);
	$totals = $DB_site->query("SELECT COUNT(*) as total, subscriptionid FROM " . TABLE_PREFIX . "subscriptionlog GROUP BY subscriptionid");
	while ($total = $DB_site->fetch_array($totals))
	{
		$t_cache["$total[subscriptionid]"] = $total['total'];
	}
	unset($total);
	$DB_site->free_result($totals);

	$totals = $DB_site->query("SELECT COUNT(*) as total, subscriptionid FROM " . TABLE_PREFIX . "subscriptionlog WHERE status = 1 GROUP BY subscriptionid");
	while ($total = $DB_site->fetch_array($totals))
	{
		$ta_cache["$total[subscriptionid]"] = $total['total'];
	}

	cache_user_subscriptions();
	if (is_array($subscriptioncache))
	{
		foreach ($subscriptioncache AS $key => $subscription)
		{
			$subscription['cost'] = unserialize($subscription['cost']);
			$string = array();
			$cells = array();
			foreach ($subscription['cost'] AS $currency => $value)
			{
				if ($value > 0)
				{
					$string[] = $_CURRENCYSYMBOLS["$currency"] . $value;
				}
			}

			if (empty($string) OR !$subscription['active'])
			{
				$cells[] = "<i>$subscription[title]</i>";
			}
			else
			{
				$cells[] = "<b>$subscription[title]</b>";
			}

			// cost
			$cells[] = implode(' / ', $string);
			// active
			$cells[] = iif(!$ta_cache["$subscription[subscriptionid]"], 0, "<a href=\"subscriptions.php?do=find&amp;subscriptionid=$subscription[subscriptionid]&amp;status=1\"><span style=\"color: green;\">" . $ta_cache["$subscription[subscriptionid]"] . "</span></a>");
			// completed
			$completed = intval($t_cache["$subscription[subscriptionid]"] - $ta_cache["$subscription[subscriptionid]"]);
			$cells[] = iif(!$completed, 0, "<a href=\"subscriptions.php?do=find&amp;subscriptionid=$subscription[subscriptionid]&amp;status=0\"><span style=\"color: red;\">" . $completed . "</span></a>");
			// total
			$cells[] = iif(!$t_cache["$subscription[subscriptionid]"], 0, "<a href=\"subscriptions.php?do=find&amp;subscriptionid=$subscription[subscriptionid]\">" . $t_cache["$subscription[subscriptionid]"] . "</a>");
			// controls
			$cells[] = "\n\t<select name=\"s$subscription[subscriptionid]\" onchange=\"js_forum_jump($subscription[subscriptionid]);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>\n\t<input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_forum_jump($subscription[subscriptionid]);\" />\n\t";
			print_cells_row($cells, 0, '', -1);
		}
	}
	print_table_footer(6, construct_button_code($vbphrase['add_new_subscription'], "subscriptions.php?$session[sessionurl]do=add"));

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11304 $
|| ####################################################################
\*======================================================================*/
?>