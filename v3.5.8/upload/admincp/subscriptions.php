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
define('CVS_REVISION', '$RCSfile$ - $Revision: 14544 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('subscription');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_paid_subscription.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'userid'         => TYPE_INT,
	'subscriptionid' => TYPE_INT,
));

// ############################# LOG ACTION ###############################
log_admin_action(!empty($vbulletin->GPC['userid']) ? "user id = " . $vbulletin->GPC['userid'] : !empty($vbulletin->GPC['subscriptionid']) ? "subscriptionid id = " . $vbulletin->GPC['subscriptionid'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['subscription_manager']);
$subobj = new vB_PaidSubscription($vbulletin);

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
	<script type="text/javascript">
	function doRemove(str)
	{
		for (var i =0; i < document.forms.cpform.elements.length; i++)
		{
			var elm = document.forms.cpform.elements[i];
			if (elm.name.substring(0, str.length) == str)
			{
				switch (elm.type)
				{
					case 'text':
						elm.value = 0;
					break;
					case 'select-one':
						elm.selectedIndex = 0;
					break;
				}
			}
		}
		return false;
	}
	</script>
	<?php
	print_form_header('subscriptions', 'update', 0, 0);
	?>
	<table cellpadding="0" cellspacing="0" border="0" width="<?php echo $OUTERTABLEWIDTH; ?>" align="center"><tr valign="top"><td>
	<table cellpadding="4" cellspacing="0" border="0" align="center" width="100%" class="tborder">
	<?php

	if ($_REQUEST['do'] == 'add')
	{
		print_table_header($vbphrase['add_new_subscription']);
		$sub['active'] = true;
		$sub['displayorder'] = 1;
	}
	else
	{
		$sub = $db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "subscription
			WHERE subscriptionid = " . $vbulletin->GPC['subscriptionid'] . "
		");
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['subscription'], $sub['title'], $sub['subscriptionid']));
		construct_hidden_code('subscriptionid', $sub['subscriptionid']);
		$sub['cost'] = unserialize($sub['cost']);
		$sub = array_merge($sub, convert_bits_to_array($sub['options'], $subobj->_SUBSCRIPTIONOPTIONS));
	}

	print_input_row($vbphrase['title'], 'sub[title]', $sub['title']);
	print_textarea_row($vbphrase['description'], 'sub[description]', $sub['description']);
	print_yes_no_row($vbphrase['active'], 'sub[active]', $sub['active']);
	print_yes_no_row($vbphrase['tax'], 'options[tax]', $sub['tax']);
	print_select_row($vbphrase['shipping_address'], 'shipping', array(0 => $vbphrase['none'], 2 => $vbphrase['optional'], 4 => $vbphrase['required']), ($sub['options'] & $subobj->_SUBSCRIPTIONOPTIONS['shipping1']) + ($sub['options'] & $subobj->_SUBSCRIPTIONOPTIONS['shipping2']));
	print_input_row($vbphrase['display_order'], 'sub[displayorder]', $sub['displayorder'], true, 5);

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

	//require_once(DIR . '/includes/functions_databuild.php');
	//cache_forums();
	$forums = explode(',', $sub['forums']);
	if (is_array($vbulletin->forumcache))
	{
		foreach ($vbulletin->forumcache AS $forumid => $forum)
		{
			if (array_search($forum['forumid'], $forums) !== false)
			{
				$sel = 1;
			}
			else
			{
				$sel = -1;
			}
			$radioname = 'forums[' . $forum['forumid'] . ']';
			print_label_row(construct_depth_mark($forum['depth'], '- - ') . ' ' . $forum['title'], "<span class=\"smallfont\"><strong>
				<label for=\"rb_1_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"1\" id=\"rb_1_$radioname\" tabindex=\"1\"" . iif($sel==1, ' checked="checked"') . " />" . $vbphrase['yes'] . "</label>
				<label for=\"rb_0_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"-1\" for=\"rb_0_$radioname\" tabindex=\"1\"" . iif($sel==-1, ' checked="checked"') . " />" . $vbphrase['default'] . "</label>
			</strong></span>
			");
		}
	}
	print_table_break('', $OUTERTABLEWIDTH);
	print_table_header($vbphrase['cost'], 8);

	print_cells_row(array(
		$vbphrase['us_dollars'],
		$vbphrase['pounds_sterling'],
		$vbphrase['euros'],
		$vbphrase['aus_dollars'],
		$vbphrase['cad_dollars'],
		$vbphrase['subscription_length'],
		$vbphrase['recurring'],
		$vbphrase['options']
	), 1);
	$direction = verify_text_direction('');
	$sub['cost'][] = array();
	foreach ($sub['cost'] AS $i => $sub_occurence)
	{
		$usd = '<input type="text" class="bginput" name="sub[time][' . $i . '][cost][usd]" dir="' . $direction . '" tabindex="1" size="7" value="' . number_format($sub_occurence['cost']['usd'], 2, '.', '') . '" />';
		$gbp = '<input type="text" class="bginput" name="sub[time][' . $i . '][cost][gbp]" dir="' . $direction . '" tabindex="1" size="7" value="' . number_format($sub_occurence['cost']['gbp'], 2, '.', '') . '" />';
		$eur = '<input type="text" class="bginput" name="sub[time][' . $i . '][cost][eur]" dir="' . $direction . '" tabindex="1" size="7" value="' . number_format($sub_occurence['cost']['eur'], 2, '.', '') . '" />';
		$aud = '<input type="text" class="bginput" name="sub[time][' . $i . '][cost][aud]" dir="' . $direction . '" tabindex="1" size="7" value="' . number_format($sub_occurence['cost']['aud'], 2, '.', '') . '" />';
		$cad = '<input type="text" class="bginput" name="sub[time][' . $i . '][cost][cad]" dir="' . $direction . '" tabindex="1" size="7" value="' . number_format($sub_occurence['cost']['cad'], 2, '.', '') . '" />';
		$length = '<input type="text" class="bginput" name="sub[time][' . $i . '][length]" dir="' . $direction . '" tabindex="1" size="7" value="' . $sub_occurence['length'] . '" />';
		$length .= '<select name="sub[time][' . $i . '][units]" tabindex="1" class="bginput">' .
		construct_select_options(array('D' => $vbphrase['days'], 'W' => $vbphrase['weeks'], 'M' => $vbphrase['months'], 'Y' => $vbphrase['years']), $sub_occurence['units']) .
		"</select>\n";
		$recurring = '<input type="checkbox" name="sub[time][' . $i . '][recurring]" value="1" tabindex="1"' . ($sub_occurence['recurring'] ? ' checked="checked"' : '') . ' />';
		$options = '<a href="#" onclick="return doRemove(\'sub[time][' . $i . ']\');">' . $vbphrase['delete'] . '</a>';
		print_cells_row(array($usd, $gbp, $eur, $aud, $cad, $length, $recurring, $options));
	}
	$tableadded = 1;
	print_submit_row(iif($_REQUEST['do'] == 'add', $vbphrase['save'], $vbphrase['update']), '_default_', 8);

}

// ###################### Start Update #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'sub'          => TYPE_ARRAY,
		'forums'       => TYPE_ARRAY_BOOL,
		'membergroup'  => TYPE_ARRAY_UINT,
		'options'      => TYPE_ARRAY_UINT,
		'shipping'     => TYPE_UINT,
	));

	if ($vbulletin->GPC['shipping'] == 2)
	{
		$vbulletin->GPC['options']['shipping1'] = 1;
	}
	else if ($vbulletin->GPC['shipping'] == 4)
	{
		$vbulletin->GPC['options']['shipping2'] = 1;
	}

	require_once(DIR . '/includes/functions_misc.php');
	$vbulletin->GPC['sub']['options'] = convert_array_to_bits($vbulletin->GPC['options'], $subobj->_SUBSCRIPTIONOPTIONS);

	$sub =& $vbulletin->GPC['sub'];

	$sub['title'] = htmlspecialchars_uni($sub['title']);
	$sub['active'] = intval($sub['active']);
	$sub['displayorder'] = intval($sub['displayorder']);

	$clean_times = array();
	$lengths = array('D' => 'days', 'W' => 'weeks', 'M' => 'months', 'Y' => 'years');

	foreach ($vbulletin->GPC['sub']['time'] AS $key => $moo)
	{
		$moo['length'] = intval($moo['length']);
		if ($moo['length'] == 0)
		{
			continue;
		}
		if (strtotime("now + $moo[length] " . $lengths["$moo[units]"]) == -1 OR $moo['length'] <= 0)
		{
			print_stop_message('invalid_subscription_length');
		}
		foreach ($moo['cost'] AS $currency => $value)
		{
			$moo['cost']["$currency"] = number_format($value, 2, '.', '');
		}
		$moo['recurring'] = intval($moo['recurring']);
		$clean_times[$key] = $moo;
	}
	unset($vbulletin->GPC['sub']['time']);
	$sub['cost'] = serialize($clean_times);

	$aforums = array();
	foreach ($vbulletin->GPC['forums'] AS $key => $value)
	{
		if ($value == 1)
		{
			$aforums[] = $key;
		}
	}

	$sub['membergroupids'] = '';
	if (!empty($vbulletin->GPC['membergroup']))
	{
		$sub['membergroupids'] = implode(',', $vbulletin->GPC['membergroup']);
	}
	$sub['forums'] = implode(',', $aforums);

	if (empty($clean_times))
	{
		$sub['active'] = 0;
	}

	if (empty($sub['title']))
	{
		print_stop_message('please_complete_required_fields');
	}
	if (in_array($sub['nusergroupid'], $vbulletin->GPC['membergroup']))
	{
		print_stop_message('primary_equals_secondary');
	}

	if (empty($vbulletin->GPC['subscriptionid']))
	{
		/*insert query*/
		$db->query_write(fetch_query_sql($sub, 'subscription'));
	}
	else
	{
		$db->query_write(fetch_query_sql($sub, 'subscription', "WHERE subscriptionid=" . $vbulletin->GPC['subscriptionid']));
	}

	toggle_subs();

	define('CP_REDIRECT', 'subscriptions.php?do=modify');
	print_stop_message('saved_subscription_x_successfully', $sub['title']);

}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'remove')
{
	print_delete_confirmation('subscription', $vbulletin->GPC['subscriptionid'], 'subscriptions', 'kill', 'subscription', 0, $vbphrase['doing_this_will_remove_all_of_this_subscriptions_members_and_their_access']);
}

// ###################### Start Kill #######################
if ($_POST['do'] == 'kill')
{

	$users = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "subscriptionlog
		WHERE subscriptionid = " . $vbulletin->GPC['subscriptionid'] . " AND
		status = 1
	");
	while ($user = $db->fetch_array($users))
	{
		$subobj->delete_user_subscription($vbulletin->GPC['subscriptionid'], $user['userid']);
	}

	$db->query_write("DELETE FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $vbulletin->GPC['subscriptionid']);
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "subscriptionlog WHERE subscriptionid = " . $vbulletin->GPC['subscriptionid']);

	toggle_subs();

	define('CP_REDIRECT', 'subscriptions.php?do=modify');
	print_stop_message('deleted_subscription_successfully');

}

// ###################### Start find #######################
if ($_REQUEST['do'] == 'find')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'status'      => TYPE_INT,
		'limitstart'  => TYPE_INT,
		'limitnumber' => TYPE_INT,
	));

	$condition = '1=1';
	$condition .= iif($vbulletin->GPC['subscriptionid'], " AND subscriptionid=" . $vbulletin->GPC['subscriptionid']);
	$condition .= ($vbulletin->GPC['status'] > -1) ? ' AND status = ' . $vbulletin->GPC['status'] : '';

	if (!empty($vbulletin->GPC['limitstart']))
	{
		$vbulletin->GPC['limitstart']--;
	}
	if (empty($vbulletin->GPC['limitnumber']))
	{
		$vbulletin->GPC['limitnumber'] = 99999999;
	}

	$searchquery = "
		SELECT *
		FROM " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE $condition
			AND user.userid = subscriptionlog.userid
		ORDER BY username $direction
		LIMIT " . $vbulletin->GPC['limitstart'] . "," . $vbulletin->GPC['limitnumber'] . "
	";
	$users = $db->query_read($searchquery);

	$countusers['users'] = $db->num_rows($users);

	if (!$countusers['users'])
	{
		print_stop_message('no_matches_found');
	}
	else
	{
		$limitfinish = $vbulletin->GPC['limitstart'] + $vbulletin->GPC['limitnumber'];

		$subs = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "subscription ORDER BY subscriptionid");
		while ($sub = $db->fetch_array($subs))
		{
			$subcache["{$sub['subscriptionid']}"] = $sub['title'];
		}
		$db->free_result($subs);

		print_form_header('user', 'find');
		print_table_header(construct_phrase($vbphrase['showing_subscriptions_x_to_y_of_z'], ($vbulletin->GPC['limitstart'] + 1), iif($limitfinish > $countusers['users'], $countusers['users'], $limitfinish), $countusers[users]), 5);
		print_cells_row(array($vbphrase['title'], $vbphrase['username'], $vbphrase['start_date'], $vbphrase['status'], $vbphrase['controls']), 1);
		// now display the results
		while ($user=$db->fetch_array($users))
		{
			$cell = array();
			$cell[] = $subcache["{$user['subscriptionid']}"];
			$cell[] = "<a href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$user[userid]\"><b>$user[username]</b></a>&nbsp;";
			$cell[] = vbdate($vbulletin->options['dateformat'], $user['regdate']);
			$cell[] = iif($user['status'], $vbphrase['active'], $vbphrase['disabled']);
			$cell[] = construct_button_code($vbphrase['edit'], "subscriptions.php?" . $vbulletin->session->vars['sessionurl'] . "do=adjust&subscriptionlogid=$user[subscriptionlogid]");
			print_cells_row($cell);
		}

		if ($vbulletin->GPC['limitnumber'] != 99999999 AND $limitfinish < $countusers['users'])
		{
			construct_hidden_code('subscriptionid', $vbulletin->GPC['subscriptionid']);
			construct_hidden_code('status', $vbulletin->GPC['status']);
			construct_hidden_code('limitnumber', $vbulletin->GPC['limitnumber']);
			construct_hidden_code('limitstart', $vbulletin->GPC['limitstart'] + $vbulletin->GPC['limitnumber'] + 1);
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
	$vbulletin->input->clean_array_gpc('p', array(
		'subscriptionlogid' => TYPE_INT,
		'status'            => TYPE_INT,
		'regdate'           => TYPE_ARRAY_INT,
		'expirydate'        => TYPE_ARRAY_INT,
		'username'          => TYPE_NOHTML,
	));

	require_once(DIR . '/includes/functions_misc.php');
	$regdate = vbmktime($vbulletin->GPC['regdate']['hour'], $vbulletin->GPC['regdate']['minute'], 0, $vbulletin->GPC['regdate']['month'], $vbulletin->GPC['regdate']['day'], $vbulletin->GPC['regdate']['year']);
	$expirydate = vbmktime($vbulletin->GPC['expirydate']['hour'], $vbulletin->GPC['expirydate']['minute'], 0, $vbulletin->GPC['expirydate']['month'], $vbulletin->GPC['expirydate']['day'], $vbulletin->GPC['expirydate']['year']);

	if ($expirydate < 0 OR $expirydate <= $regdate)
	{
		print_stop_message('invalid_subscription_length');
	}
	if ($vbulletin->GPC['userid'])
	{ // already existing entry
		if (!$vbulletin->GPC['status'])
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "subscriptionlog
				SET regdate = $regdate, expirydate = $expirydate
				WHERE userid = " . $vbulletin->GPC['userid'] . "
					AND subscriptionid = " . $vbulletin->GPC['subscriptionid'] . "
			");
			$subobj->delete_user_subscription($vbulletin->GPC['subscriptionid'], $vbulletin->GPC['userid']);
		}
		else
		{
			$subobj->build_user_subscription($vbulletin->GPC['subscriptionid'], -1, $vbulletin->GPC['userid'], $regdate, $expirydate);
		}
	}
	else
	{
		$userinfo = $db->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . $db->escape_string($vbulletin->GPC['username']) . "'
		");

		if (!$userinfo['userid'])
		{
			print_stop_message('no_users_matched_your_query');
		}

		$subobj->build_user_subscription($vbulletin->GPC['subscriptionid'], -1, $userinfo['userid'], $regdate, $expirydate);

	}

	define('CP_REDIRECT', "subscriptions.php?do=find&status=1&subscriptionid=" . $vbulletin->GPC['subscriptionid']);
	print_stop_message('saved_subscription_x_successfully', $sub['title']);
}

// ###################### Start status #######################
if ($_REQUEST['do'] == 'adjust')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'subscriptionlogid' => TYPE_INT
	));

	print_form_header('subscriptions', 'status');

	if ($vbulletin->GPC['subscriptionlogid'])
	{ // already exists
		$sub = $db->query_first("
			SELECT subscriptionlog.*, username FROM " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
			LEFT JOIN " . TABLE_PREFIX . "user USING(userid)
			WHERE subscriptionlogid = " . $vbulletin->GPC['subscriptionlogid'] . "
		");
		print_table_header(construct_phrase($vbphrase['edit_subscription_for_x'], $sub['username']));
		construct_hidden_code('userid', $sub['userid']);
		$vbulletin->GPC['subscriptionid'] = $sub['subscriptionid'];
	}
	else
	{
		print_table_header($vbphrase['add_user']);
		$subinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $vbulletin->GPC['subscriptionid']);

		$cost_length = unserialize($subinfo['cost']);

		reset($cost_length);
		$first_sub = current($cost_length);
		if (!empty($first_sub['units']))
		{
			$subobj = new vB_PaidSubscription($vbulletin);
			$expiry = $subobj->fetch_proper_expirydate(TIMENOW, $first_sub['length'], $first_sub['units']);
		}
		else
		{
			$expiry = TIMENOW + 60;
		}

		$sub = array(
			'regdate'    => TIMENOW,
			'status'     => 1,
			'expirydate' => $expiry
		);
		print_input_row($vbphrase['username'], 'username', '', 0);
	}

	construct_hidden_code('subscriptionid', $vbulletin->GPC['subscriptionid']);

	print_time_row($vbphrase['start_date'], 'regdate', $sub['regdate']);
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
		var action = eval("document.cpform.s" + sid + ".options[document.cpform.s" + sid + ".selectedIndex].value");
		if (action != '')
		{
			switch (action)
			{
				case 'edit': page = "subscriptions.php?do=edit&subscriptionid="; break;
				case 'remove': page = "subscriptions.php?do=remove&subscriptionid="; break;
				case 'view': page = "subscriptions.php?do=find&status=1&subscriptionid="; break;
				case 'addu': page = "subscriptions.php?do=adjust&subscriptionid="; break;
			}
			document.cpform.reset();
			jumptopage = page + sid + "&s=<?php echo $vbulletin->session->vars['sessionhash']; ?>";
			window.location = jumptopage;
		}
		else
		{
			alert("<?php echo $vbphrase['invalid_action_specified']; ?>");
		}
	}
	</script>
	<?php

	print_form_header('subscriptions', 'doorder');
	print_table_header($vbphrase['subscription_manager'], 6);
	print_cells_row(array($vbphrase['title'], $vbphrase['active'], $vbphrase['completed'], $vbphrase['total'], $vbphrase['display_order'], $vbphrase['controls']), 1, 'tcat', 1);
	$totals = $db->query_read("SELECT COUNT(*) as total, subscriptionid FROM " . TABLE_PREFIX . "subscriptionlog GROUP BY subscriptionid");
	while ($total = $db->fetch_array($totals))
	{
		$t_cache["{$total['subscriptionid']}"] = $total['total'];
	}
	unset($total);
	$db->free_result($totals);

	$totals = $db->query_read("SELECT COUNT(*) as total, subscriptionid FROM " . TABLE_PREFIX . "subscriptionlog WHERE status = 1 GROUP BY subscriptionid");
	while ($total = $db->fetch_array($totals))
	{
		$ta_cache["{$total['subscriptionid']}"] = $total['total'];
	}

	$subobj->cache_user_subscriptions();
	if (is_array($subobj->subscriptioncache))
	{
		foreach ($subobj->subscriptioncache AS $key => $subscription)
		{
			$cells = array();

			if (!$subscription['active'])
			{
				$cells[] = "<em>$subscription[title]</em>";
			}
			else
			{
				$cells[] = "<strong>$subscription[title]</strong>";
			}

			// active
			$cells[] = iif(!$ta_cache["{$subscription['subscriptionid']}"], 0, "<a href=\"subscriptions.php?do=find&amp;subscriptionid=$subscription[subscriptionid]&amp;status=1\"><span style=\"color: green;\">" . $ta_cache["{$subscription['subscriptionid']}"] . "</span></a>");
			// completed
			$completed = intval($t_cache["{$subscription['subscriptionid']}"] - $ta_cache["{$subscription['subscriptionid']}"]);
			$cells[] = iif(!$completed, 0, "<a href=\"subscriptions.php?do=find&amp;subscriptionid=$subscription[subscriptionid]&amp;status=0\"><span style=\"color: red;\">" . $completed . "</span></a>");
			// total
			$cells[] = iif(!$t_cache["{$subscription['subscriptionid']}"], 0, "<a href=\"subscriptions.php?do=find&amp;subscriptionid=$subscription[subscriptionid]&amp;status=-1\">" . $t_cache["{$subscription['subscriptionid']}"] . "</a>");
			// display order
			$cells[] = "<input type=\"text\" class=\"bginput\" name=\"order[$subscription[subscriptionid]]\" value=\"$subscription[displayorder]\" tabindex=\"1\" size=\"3\" title=\"" . $vbphrase['edit_display_order'] . "\" />";
			// controls
			$cells[] = "\n\t<select name=\"s$subscription[subscriptionid]\" onchange=\"js_forum_jump($subscription[subscriptionid]);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>\n\t<input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_forum_jump($subscription[subscriptionid]);\" />\n\t";
			print_cells_row($cells, 0, '', 1);
		}
	}
	print_table_footer(6, "<input type=\"submit\" class=\"button\" tabindex=\"1\" value=\"" . $vbphrase['save_display_order'] . "\" accesskey=\"s\" />" . construct_button_code($vbphrase['add_new_subscription'], "subscriptions.php?" . $vbulletin->session->vars['sessionurl'] . "do=add"));

}

// ###################### Start do order #######################
if ($_POST['do'] == 'doorder')
{
	$vbulletin->input->clean_array_gpc('p', array('order' => TYPE_ARRAY));

	if (is_array($vbulletin->GPC['order']))
	{
		$subobj->cache_user_subscriptions();
		if (is_array($subobj->subscriptioncache))
		{
			$casesql = '';
			$subscriptionids = '';
			foreach($subobj->subscriptioncache AS $sub)
			{
				if (!isset($vbulletin->GPC['order']["$sub[subscriptionid]"]))
				{
					continue;
				}

				$displayorder = intval($vbulletin->GPC['order']["$sub[subscriptionid]"]);
				if ($sub['displayorder'] != $displayorder)
				{
					$casesql .= "WHEN subscriptionid = $sub[subscriptionid] THEN $displayorder\n";
					$subscriptionids .= ",$sub[subscriptionid]";
				}
			}

			if (!empty($casesql))
			{
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "subscription
					SET displayorder =
						CASE
							$casesql
							ELSE 1
						END
					WHERE subscriptionid IN (-1$subscriptionids)
				");
			}
		}
	}

	define('CP_REDIRECT', 'subscriptions.php?do=modify');
	print_stop_message('saved_display_order_successfully');
}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'apirem')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'paymentapiid' => TYPE_INT
	));
	print_delete_confirmation('paymentapi', $vbulletin->GPC['paymentapiid'], 'subscriptions', 'apikill', 'paymentapi');
}

// ###################### Start Kill #######################
if ($_POST['do'] == 'apikill')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'paymentapiid' => TYPE_INT
	));

	$db->query_write("DELETE FROM " . TABLE_PREFIX . "paymentapi WHERE paymentapiid = " . $vbulletin->GPC['paymentapiid']);

	toggle_subs();

	define('CP_REDIRECT', 'subscriptions.php?do=api');
	print_stop_message('deleted_paymentapi_successfully');

}

// ###################### Start Api Edit #######################
if ($_REQUEST['do'] == 'apiedit' OR $_REQUEST['do'] == 'apiadd')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'paymentapiid' => TYPE_INT
	));

	print_form_header('subscriptions', 'apiupdate');
	if ($_REQUEST['do'] == 'apiadd')
	{
		print_table_header($vbphrase['add_new_paymentapi']);
	}
	else
	{
		$api = $db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "paymentapi
			WHERE paymentapiid = " . $vbulletin->GPC['paymentapiid'] . "
		");
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['paymentapi'], $api['title'], $api['paymentapiid']));
		construct_hidden_code('paymentapiid', $api['paymentapiid']);
	}

	print_input_row($vbphrase['title'], 'api[title]', $api['title']);
	print_radio_row($vbphrase['active'], 'api[active]', array(
		0 => $vbphrase['no'],
		1 => $vbphrase['yes']
	), $api['active'], 'smallfont');
	if ($vbulletin->debug)
	{
		print_input_row($vbphrase['classname'], 'api[classname]', $api['classname']);
		print_input_row($vbphrase['supported_currency'], 'api[currency]', $api['currency']);
		print_radio_row($vbphrase['supports_recurring'], 'api[recurring]', array(
			0 => $vbphrase['no'],
			1 => $vbphrase['yes']
		), $api['recurring'], 'smallfont');
	}
	else
	{
		print_label_row($vbphrase['classname'], $api['classname']);
		print_label_row($vbphrase['supported_currency'], $api['currency']);
		print_label_row($vbphrase['supports_recurring'], ($api['recurring'] ? $vbphrase['yes'] : $vbphrase['no']));
	}

	if ($_REQUEST['do'] == 'apiedit')
	{
		$settings = unserialize($api['settings']);
		if (is_array($settings))
		{
			// $info is an array
			foreach ($settings AS $key => $info)
			{
				print_description_row(
					'<div>' . $vbphrase["setting_{$api[classname]}_{$key}_title"] . "</div>",
					0, 2, "optiontitle\""
				);
				$name = "settings[$key]";
				$description = "<div class=\"smallfont\">" . $vbphrase["setting_{$api[classname]}_{$key}_desc"] . '</div>';
				switch ($info['type'])
				{
					case 'yesno':
					print_yes_no_row($description, $name, $info['value']);
					break;

					default:
					print_input_row($description, $name, $info['value'], 1, 40);
					break;
				}
			}
		}
	}

	print_submit_row(iif($_REQUEST['do'] == 'apiadd', $vbphrase['save'], $vbphrase['update']));
}

// ###################### Start Update #######################
if ($_POST['do'] == 'apiupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'api'			=> TYPE_ARRAY,
		'settings'		=> TYPE_ARRAY,
		'paymentapiid'	=> TYPE_UINT,
	));

	$api =& $vbulletin->GPC['api'];

	if (!empty($vbulletin->GPC['paymentapiid']) AND !empty($vbulletin->GPC['settings']))
	{
		$currentinfo = $db->query_first("SELECT settings FROM " . TABLE_PREFIX . "paymentapi WHERE paymentapiid = " . $vbulletin->GPC['paymentapiid']);
		$settings = unserialize($currentinfo['settings']);
		$updatesettings = false;

		foreach ($vbulletin->GPC['settings'] AS $key => $value)
		{
			if (isset($settings["$key"]) AND $settings["$key"]['value'] != $value)
			{
				switch ($settings["$key"]['validate'])
				{
					case 'number':
						$value += 0;
						break;
					case 'boolean':
						$value = $value ? 1 : 0;
						break;
					case 'string':
						$value = trim($value);
						break;
				}
				$settings["$key"]['value'] = $value;
				$updatesettings = true;
			}
		}
		if ($updatesettings)
		{
			$api['settings'] = serialize($settings);
		}
	}

	$api['title'] = htmlspecialchars_uni($api['title']);
	$api['active'] = intval($api['active']);

	if (isset($api['classname']))
	{
		$api['classname'] = preg_replace('#[^a-z0-9_]#i', '', $api['classname']);
		if (empty($api['classname']))
		{
			print_stop_message('please_complete_required_fields');
		}
	}

	if (isset($api['currency']))
	{
		if (empty($api['currency']))
		{
			print_stop_message('please_complete_required_fields');
		}
	}

	if (isset($api['recurring']))
	{
		$api['recurring'] = intval($api['recurring']);
	}

	if (empty($api['title']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if (empty($vbulletin->GPC['paymentapiid']))
	{
		/*insert query*/
		$db->query_write(fetch_query_sql($api, 'paymentapi'));
	}
	else
	{
		$db->query_write(fetch_query_sql($api, 'paymentapi', "WHERE paymentapiid=" . $vbulletin->GPC['paymentapiid']));
	}

	toggle_subs();

	define('CP_REDIRECT', 'subscriptions.php?do=api');
	print_stop_message('saved_paymentapi_x_successfully', $api['title']);

}

// ###################### Start api #######################
if ($_REQUEST['do'] == 'api')
{

	$options = array(
		'edit' => $vbphrase['edit'],
		'remove' => $vbphrase['delete']
	);

	?>
	<script type="text/javascript">
	function js_forum_jump(pid)
	{
		var action = eval("document.cpform.p" + pid + ".options[document.cpform.p" + pid + ".selectedIndex].value");
		if (action != '')
		{
			switch (action)
			{
				case 'edit': page = "subscriptions.php?do=apiedit&paymentapiid="; break;
				case 'remove': page = "subscriptions.php?do=apirem&paymentapiid="; break;
			}
			document.cpform.reset();
			jumptopage = page + pid + "&s=<?php echo $vbulletin->session->vars['sessionhash']; ?>";
			window.location = jumptopage;
		}
		else
		{
			alert("<?php echo $vbphrase['invalid_action_specified']; ?>");
		}
	}
	</script>
	<?php
	print_form_header('subscriptions');
	// PHRASE ME
	print_table_header($vbphrase['payment_api_manager'], 3);
	print_cells_row(array($vbphrase['title'], $vbphrase['active'], $vbphrase['controls']), 1, 'tcat', 1);
	$apis = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "paymentapi
	");

	while ($api = $db->fetch_array($apis))
	{
		$cells = array();
		$cells[] = $api['title'];
		if ($api['active'])
		{
			$yesno = 'yes';
		}
		else
		{
			$yesno = 'no';
		}

		$cells[] = "<img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/cp_tick_$yesno.gif\" alt=\"\" />";
		$cells[] = "\n\t<select name=\"p$api[paymentapiid]\" onchange=\"js_forum_jump($api[paymentapiid]);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>\n\t<input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_forum_jump($api[paymentapiid]);\" />\n\t";
		print_cells_row($cells, 0, '', 1);
	}

	print_table_footer(3);
}

print_cp_footer();

// ###################### Start toggle_subs #######################
// Function disables subs if there isn't an active API or active SUB (and vice versa)
function toggle_subs()
{
	global $vbulletin;

	// bit of a hack, will most likely change this to a datastore item in the future

	$setting = 0;
	if ($check = $vbulletin->db->query_first("
		SELECT paymentapiid
		FROM " . TABLE_PREFIX . "paymentapi
		WHERE active = 1
	"))
	{
		if ($check = $vbulletin->db->query_first("
			SELECT subscriptionid
			FROM " . TABLE_PREFIX . "subscription
			WHERE active = 1
		"))
		{
			$setting = 1;
		}
	}

	if ($setting != $vbulletin->options['subscriptionmethods'])
	{
		// update $vboptions
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "setting
			SET value = '$setting'
			WHERE varname = 'subscriptionmethods'
		");
		build_options();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14544 $
|| ####################################################################
\*======================================================================*/
?>