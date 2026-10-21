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
define('CVS_REVISION', '$RCSfile$ - $Revision: 13299 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cpuser', 'forum', 'timezone', 'user');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_profilefield.php');
require_once('./includes/adminfunctions_user.php');
if ($_REQUEST['do'] == 'edit')
{
	$_REQUEST['do'] = 'view';
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['userid']!=0, 'user id = ' . $_REQUEST['userid'], ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_manager']);

// ############################# start do ips #########################
if ($_REQUEST['do'] == 'doips')
{
	if (!can_moderate(0, 'canviewips'))
	{
		print_stop_message('no_permission_ips');
	}

	// the following is now a direct copy of the contents of doips from admincp/user.php
	if (function_exists('set_time_limit') AND get_cfg_var('safe_mode')==0)
	{
		@set_time_limit(0);
	}

	if (empty($_REQUEST['depth']))
	{
		$depth = 1;
	}
	else
	{
		$depth = intval($_REQUEST['depth']);
	}

	if ($_REQUEST['username'])
	{
		$getuserid = $DB_site->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . addslashes(htmlspecialchars_uni($_REQUEST['username'])) . "'
		");
		$userid = intval($getuserid['userid']);
		if (!$userid)
		{
			print_stop_message('invalid_user_specified');
		}
	}
	else if ($_REQUEST['userid'])
	{
		$userid = intval($_REQUEST['userid']);
		$userinfo = fetch_userinfo($userid);
		if (!$userinfo)
		{
			print_stop_message('invalid_user_specified');
		}
		$_REQUEST['username'] = unhtmlspecialchars($userinfo['username']);
	}
	else
	{
		$userid = 0;
	}

	if ($_REQUEST['ipaddress'] OR $userid)
	{
		print_form_header('', '');

		if ($_REQUEST['ipaddress'])
		{
			print_table_header(construct_phrase($vbphrase['ip_address_search_for_ip_address_x'], htmlspecialchars_uni($_REQUEST['ipaddress'])));
			if ($hostname = @gethostbyaddr($_REQUEST['ipaddress']) AND $hostname != $_REQUEST['ipaddress'])
			{
				print_description_row("<div style=\"margin-left:20px\"><a href=\"user.php?$session[sessionurl]do=gethost&amp;ip=$_REQUEST[ipaddress]\">$_REQUEST[ipaddress]</a> : <b>$hostname</b></div>");
			}
			$results = construct_ip_usage_table($_REQUEST['ipaddress'], 0, $depth);
			if (!$results)
			{
				print_description_row($vbphrase['no_matches_found']);
			}
			else
			{
				print_description_row($results);
			}
		}

		if ($userid)
		{
			print_table_header(construct_phrase($vbphrase['ip_address_search_for_user_x'], htmlspecialchars_uni($_REQUEST['username'])));
			$results = construct_user_ip_table($userid, 0, $depth);
			if (!$results)
			{
				print_description_row($vbphrase['no_matches_found']);
			}
			else
			{
				print_description_row($results);
			}
		}
		print_table_footer();
	}

	print_form_header('user', 'doips');
	print_table_header($vbphrase['search_ip_addresses']);
	print_input_row($vbphrase['find_users_by_ip_address'], 'ipaddress', $_REQUEST['ipaddress']);
	print_input_row($vbphrase['find_ip_addresses_for_user'], 'username', $_REQUEST['username']);
	print_select_row($vbphrase['depth_to_search'], 'depth', array(1 => 1, 2 => 2), $depth);
	print_submit_row($vbphrase['find']);
}

// ############################# start gethost #########################
if ($_REQUEST['do'] == 'gethost')
{
	globalize($_REQUEST, array('ip' => STR_NOHTML));

	print_form_header('', '');
	print_table_header($vbphrase['ip_address']);
	print_label_row($vbphrase['ip_address'], $ip);
	$resolvedip = @gethostbyaddr($ip);
	if ($resolvedip == $$ip)
	{
		print_label_row($vbphrase['host_name'], '<i>' . $vbphrase['n_a'] . '</i>');
	}
	else
	{
		print_label_row($vbphrase['host_name'], "<b>$resolvedip</b>");
	}
	print_table_footer();
}

// ###################### Start find #######################
if ($_REQUEST['do'] == 'find')
{
	if (!can_moderate(0, 'canunbanusers') AND !can_moderate(0, 'canbanusers') AND !can_moderate(0, 'canviewprofile') AND !can_moderate(0, 'caneditsigs') AND !can_moderate(0, 'caneditavatar'))
	{
		print_stop_message('no_permission_search_users');
	}

	print_form_header('user', 'findnames');
	print_table_header($vbphrase['search_users']);
	print_input_row($vbphrase['username'], 'findname');
	print_yes_no_row($vbphrase['exact_match'], 'exact', 0);
	print_submit_row($vbphrase['search']);
}

// ###################### Start findname #######################
if ($_REQUEST['do'] == 'findnames')
{
	globalize($_REQUEST, array('findname' => STR_NOHTML, 'exact'));

	$canbanusers = can_moderate(0, 'canbanusers');
	$canviewprofile = can_moderate(0, 'canviewprofile');
	$caneditsigs = can_moderate(0, 'caneditsigs');
	$caneditavatar = can_moderate(0, 'caneditavatar');
	$caneditprofilepic = can_moderate(0, 'caneditprofilepic');
	$caneditreputation = iif(can_moderate(0, 'caneditreputation') AND $vboptions['reputationenable'], true);
	if (!$canbanusers AND !$canviewprofile AND !$caneditsigs AND !$caneditavatar AND !$caneditprofilepic AND !$caneditreputation)
	{
		print_stop_message('no_permission_search_users');
	}

	if (empty($findname))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($exact)
	{
		$condition = "username = '" . addslashes($findname) . "'";
	}
	else
	{
		$condition = "username LIKE '%" . addslashes_like($findname) . "%'";
	}

	$users = $DB_site->query("
		SELECT userid, username
		FROM " . TABLE_PREFIX . "user
		WHERE $condition
		ORDER BY username
	");
	if ($DB_site->num_rows($users) > 0)
	{
		print_form_header('', '', 0, 1, 'cpform', '70%');
		print_table_header(construct_phrase($vbphrase['showing_users_x_to_y_of_z'], '1', $DB_site->num_rows($users), $DB_site->num_rows($users)), 7);
		while ($user = $DB_site->fetch_array($users))
		{
			$cell = array("<b>$user[username]</b>");

			$cell[] = iif($canbanusers, '<span class="smallfont">' . construct_link_code($vbphrase['ban_user'], "banning.php?$session[sessionurl]do=banuser&amp;userid=$user[userid]") . '</span>');
			$cell[] = iif($canviewprofile, '<span class="smallfont">' . construct_link_code($vbphrase['view_profile'], "user.php?$session[sessionurl]do=viewuser&amp;userid=$user[userid]") . '</span>');
			$cell[] = iif($caneditsigs, '<span class="smallfont">' . construct_link_code($vbphrase['change_signature'], "user.php?$session[sessionurl]do=editsig&amp;userid=$user[userid]") . '</span>');
			$cell[] = iif($caneditavatar, '<span class="smallfont">' . construct_link_code($vbphrase['change_avatar'], "user.php?$session[sessionurl]do=avatar&amp;userid=$user[userid]") . '</span>');
			$cell[] = iif($caneditprofilepic, '<span class="smallfont">' . construct_link_code($vbphrase['change_profile_picture'], "user.php?$session[sessionurl]do=profilepic&amp;userid=$user[userid]") . '</span>');
			$cell[] = iif($caneditreputation, '<span class="smallfont">' . construct_link_code($vbphrase['edit_reputation'], "user.php?$session[sessionurl]do=reputation&amp;userid=$user[userid]") . '</span>');

			print_cells_row($cell);
		}
		print_table_footer();
	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

// ###################### Start viewuser #######################
if ($_REQUEST['do'] == 'viewuser')
{

	globalize($_REQUEST, array('userid' => INT));

	if (!can_moderate(0, 'canviewprofile'))
	{
		print_stop_message('no_permission');
	}

	$OUTERTABLEWIDTH = '95%';
	$INNERTABLEWIDTH = '100%';

	if (empty($userid))
	{
		print_stop_message('invalid_user_specified');
	}

	print_form_header('user', 'viewuser', 0, 0);
	construct_hidden_code('userid', $userid);
	?>
	<table cellpadding="0" cellspacing="0" border="0" width="<?php echo $OUTERTABLEWIDTH; ?>" align="center"><tr valign="top"><td>
	<table cellpadding="4" cellspacing="0" border="0" align="center" width="100%" class="tborder">
	<?php

	$user = $DB_site->query_first("
		SELECT user.*,usertextfield.signature,avatar.avatarpath, NOT ISNULL(customavatar.avatardata) AS hascustomavatar,
			customavatar.dateline AS avatardateline, customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON avatar.avatarid = user.avatarid
		LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON customavatar.userid = user.userid
		LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON customprofilepic.userid = user.userid
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		WHERE user.userid = $userid
	");

	$getoptions = convert_bits_to_array($user['options'], $_USEROPTIONS);
	$user = array_merge($user, $getoptions);

	// get threaded mode options
	if ($user['threadedmode'] == 1 OR $user['threadedmode'] == 2)
	{
		$threaddisplaymode = $user['threadedmode'];
	}
	else
	{
		if ($user['postorder'] == 0)
		{
			$threaddisplaymode = 0;
		}
		else
		{
			$threaddisplaymode = 3;
		}
	}

	$userfield = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "userfield WHERE userid=$userid");

	// make array for daysprune menu
	$pruneoptions = array(
		'-1' => '- ' . $vbphrase['use_forum_default'] . ' -',
		'1' => $vbphrase['show_threads_from_last_day'],
		'2' => construct_phrase($vbphrase['show_threads_from_last_x_days'], 2),
		'7' => $vbphrase['show_threads_from_last_week'],
		'10' => construct_phrase($vbphrase['show_threads_from_last_x_days'], 10),
		'14' => construct_phrase($vbphrase['show_threads_from_last_x_weeks'], 2),
		'30' => $vbphrase['show_threads_from_last_month'],
		'45' => construct_phrase($vbphrase['show_threads_from_last_x_days'], 45),
		'60' => construct_phrase($vbphrase['show_threads_from_last_x_months'], 2),
		'75' => construct_phrase($vbphrase['show_threads_from_last_x_days'], 75),
		'100' => construct_phrase($vbphrase['show_threads_from_last_x_days'], 100),
		'365' => $vbphrase['show_threads_from_last_year'],
		'1000' => construct_phrase($vbphrase['show_threads_from_last_x_days'], 1000)
	);
	if ($pruneoptions["$user[daysprune]"] == '')
	{
		$pruneoptions["$user[daysprune]"] = construct_phrase($vbphrase['show_threads_from_last_x_days'], $user['daysprune']);
	}

	// start main table
	require_once('./includes/functions_misc.php');

	// PROFILE SECTION
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user'], $user['username'], $user['userid']));
	print_input_row($vbphrase['username'], 'user[username]', $user['username'], 0);
	print_input_row($vbphrase['email'], 'user[email]', $user['email'], 0);
	print_select_row($vbphrase['language'], 'user[languageid]', fetch_language_titles_array('', 0), $user['languageid'] );
	print_input_row($vbphrase['user_title'], 'user[usertitle]', $user['usertitle']);
	print_yes_no_row($vbphrase['custom_user_title'], 'options[customtitle]', $user['customtitle']);
	print_input_row($vbphrase['home_page'], 'user[homepage]', $user['homepage'], 0);
	print_time_row($vbphrase['birthday'], 'birthday', $user['birthday'], 0, 1);
	print_textarea_row($vbphrase['signature'] . iif(can_moderate(0, 'caneditsigs'), '<br /><br />' . construct_link_code($vbphrase['edit_signature'], "user.php?$session[sessionurl]do=editsig&amp;userid=$user[userid]")), 'signature', $user['signature'], 8, 45, 1, 0);
	print_input_row($vbphrase['icq_uin'], 'user[icq]', $user['icq'], 0);
	print_input_row($vbphrase['aim_screen_name'], 'user[aim]', $user['aim'], 0);
	print_input_row($vbphrase['yahoo_id'], 'user[yahoo]', $user['yahoo'], 0);
	print_input_row($vbphrase['msn_id'], 'user[msn]', $user['msn'], 0);
	print_yes_no_row($vbphrase['coppa_user'], 'options[coppauser]', $user['coppauser']);
	print_input_row($vbphrase['parent_email_address'], 'user[parentemail]', $user['parentemail'], 0);
	print_input_row($vbphrase['post_count'], 'user[posts]', $user['posts']);
	if ($user['referrerid'])
	{
		$referrername = $DB_site->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = $user[referrerid]");
		$user['referrer'] = $referrername['username'];
	}
	print_input_row($vbphrase['referrer'], 'referrer', $user['referrer']);
	print_input_row($vbphrase['ip_address'], 'user[ipaddress]', $user['ipaddress']);
	print_table_break('', $INNERTABLEWIDTH);

	// USER IMAGE SECTION
	print_table_header($vbphrase['image_options']);
	if ($user['avatarid'])
	{
		$avatarurl = iif(substr($user['avatarpath'], 0, 7) != 'http://' AND substr($user['avatarpath'], 0, 1) != '/', '../') . $user['avatarpath'];
	}
	else
	{
		if ($user['hascustomavatar'])
		{
			if ($vboptions['usefileavatar'])
			{
				$avatarurl = iif(substr($vboptions['avatarurl'] , 0, 7) == 'http://', '', '../') . "$vboptions[avatarurl]/avatar$user[userid]_$user[avatarrevision].gif";
			}
			else
			{
				$avatarurl = "../image.php?$session[sessionurl]userid=$user[userid]&amp;dateline=$user[avatardateline]";
			}
		}
		else
		{
			$avatarurl = "../$vboptions[cleargifurl]";
		}
	}
	if ($user['profilepic'])
	{
		$profilepicurl = "../image.php?$session[sessionurl]userid=$user[userid]&amp;type=profile&amp;dateline=$user[profilepicdateline]";
	}
	else
	{
		$profilepicurl = "../$vboptions[cleargifurl]";
	}
	print_label_row($vbphrase['avatar'] . iif(can_moderate(0, 'caneditavatar'), '<br /><br />' . construct_link_code($vbphrase['edit_avatar'], "user.php?$session[sessionurl]do=avatar&amp;userid=$user[userid]")) . '<input type="image" src="../' . $vboptions['cleargifurl'] . '" alt="" />','<img src="' . $avatarurl . '" alt="" align="top" />');
	print_label_row($vbphrase['profile_picture'] . iif(can_moderate(0, 'caneditprofilepic'), '<br /><br />' . construct_link_code($vbphrase['edit_profile_picture'], "user.php?$session[sessionurl]do=profilepic&amp;userid=$user[userid]")) . '<input type="image" src="../' . $vboptions['cleargifurl'] . '" alt="" />','<img src="' . $profilepicurl . '" alt="" align="top" />');
	print_table_break('', $INNERTABLEWIDTH);

	// PROFILE FIELDS SECTION
	print_table_header($vbphrase['user_profile_fields']);
	$profilefields = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "profilefield ORDER by displayorder");
	while ($profilefield = $DB_site->fetch_array($profilefields))
	{
		print_profilefield_row($profilefield, $userfield);
	}

	if ($vboptions['cp_usereditcolumns'] == 2)
	{
		?>
		</table>
		</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>
		<table cellpadding="4" cellspacing="0" border="0" align="center" width="100%" class="tborder">
		<?php
	}
	else
	{
		print_table_break('', $INNERTABLEWIDTH);
	}

	// USERGROUP SECTION
	print_table_header($vbphrase['usergroup_options']);
	print_chooser_row($vbphrase['primary_usergroup'], 'user[usergroupid]', 'usergroup', $user['usergroupid']);
	print_membergroup_row($vbphrase['additional_usergroups'], 'membergroup', 0, $user);
	print_table_break('', $INNERTABLEWIDTH);

	// reputation SECTION
	print_table_header($vbphrase['reputation']);
	print_yes_no_row($vbphrase['display_reputation'], 'options[showreputation]', $user['showreputation']);
	print_input_row($vbphrase['reputation_level'], 'user[reputation]', $user['reputation']);
	print_table_break('',$INNERTABLEWIDTH);

	// BROWSING OPTIONS SECTION
	print_table_header($vbphrase['browsing_options']);
	print_yes_no_row($vbphrase['receive_admin_emails'], 'options[adminemail]', $user['adminemail']);
	print_yes_no_row($vbphrase['display_email'], 'options[showemail]', $user[showemail]);
	print_yes_no_row($vbphrase['invisible_mode'], 'options[invisible]', $user['invisible']);
	print_yes_no_row($vbphrase['receive_private_messages'], 'options[receivepm]', $user['receivepm']);
	print_yes_no_row($vbphrase['send_notification_email_when_a_private_message_is_received'], 'options[emailonpm]', $user['emailonpm']);
	print_yes_no_row($vbphrase['pop_up_notification_box_when_a_private_message_is_received'], 'user[pmpopup]', $user['pmpopup']);
	print_yes_no_row($vbphrase['display_signature'], 'options[showsignatures]', $user['showsignatures']);
	print_yes_no_row($vbphrase['display_avatars'], 'options[showavatars]', $user['showavatars']);
	print_yes_no_row($vbphrase['display_images'], 'options[showimages]', $user['showimages']);
	//print_yes_no_row($vbphrase['use_email_notification_by_default'], 'options[emailnotification]', $user['emailnotification']);
	print_radio_row($vbphrase['auto_subscription_mode'], 'user[autosubscribe]', array(
		-1 => $vbphrase['subscribe_choice_none'],
		0  => $vbphrase['subscribe_choice_0'],
		1  => $vbphrase['subscribe_choice_1'],
		//4  => $vbphrase['subscribe_choice_4'], // no longer in use (was ICQ)
		2  => $vbphrase['subscribe_choice_2'],
		3  => $vbphrase['subscribe_choice_3'],
	), $user['autosubscribe'], 'smallfont');
	print_radio_row($vbphrase['thread_display_mode'], 'threaddisplaymode', array(
		0 => "$vbphrase[linear] - $vbphrase[oldest_first]",
		3 => "$vbphrase[linear] - $vbphrase[newest_first]",
		2 => $vbphrase['hybrid'],
		1 => $vbphrase['threaded']
	), $threaddisplaymode, 'smallfont');

	print_radio_row($vbphrase['message_editor_interface'], 'user[showvbcode]', array(
		0 => $vbphrase['do_not_show_editor_toolbar'],
		1 => $vbphrase['show_standard_editor_toolbar'],
		2 => $vbphrase['show_enhanced_editor_toolbar']
	), $user['showvbcode'], 'smallfont');

	construct_style_chooser($vbphrase['style'], 'user[styleid]', $user['styleid']);
	print_table_break('', $INNERTABLEWIDTH);

	// TIME FIELDS SECTION
	print_table_header($vbphrase['time_options']);
	print_description_row($vbphrase['timezone'].' <select name="user[timezoneoffset]" class="bginput" tabindex="1">' . construct_select_options(fetch_timezones_array(), $user['timezoneoffset']) . '</select>');
	print_label_row($vbphrase['default_view_age'], '<select name="user[daysprune]" class="bginput" tabindex="1">' . construct_select_options($pruneoptions, $user['daysprune']) . '</select>');
	print_time_row($vbphrase['join_date'], 'joindate', $user['joindate'], 0);
	print_time_row($vbphrase['last_visit'], 'lastvisit', $user['lastvisit']);
	print_time_row($vbphrase['last_activity'], 'lastactivity', $user['lastactivity']);
	print_time_row($vbphrase['last_post'], 'lastpost', $user['lastpost']);

	?>
	</table>
	</tr>
	<?php

	print_table_break('', $OUTERTABLEWIDTH);
	$tableadded = 1;
	print_table_footer();
}

// ###################### Start editsig #######################
if ($_REQUEST['do'] == 'editsig')
{
	globalize($_REQUEST, array('userid' => INT));

	if (!can_moderate(0, 'caneditsigs'))
	{
		print_stop_message('no_permission_signatures');
	}

	if (empty($userid))
	{
		print_stop_message('invalid_user_specified');
	}

	$noalter = explode(',', $undeletableusers);
	if (!empty($noalter[0]) AND in_array($userid, $noalter))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$user = $DB_site->query_first("
		SELECT * FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING (userid)
		WHERE user.userid = $userid
	");

	print_form_header('user','doeditsig', 0, 1);
	construct_hidden_code('userid', $userid);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['signature'], $user['username'], $user['userid']));
	print_textarea_row($vbphrase['signature'], 'signature', $user['signature'], 8, 45, 1, 0);
	print_submit_row();

}

// ###################### Start doeditsig #######################
if ($_POST['do'] == 'doeditsig')
{
	globalize($_POST, array('userid' => INT, 'signature' => STR));

	if (!can_moderate(0, 'caneditsigs'))
	{
		print_stop_message('no_permission_signatures');
	}

	$noalter = explode(',', $undeletableusers);
	if (!empty($noalter[0]) AND in_array($userid, $noalter))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	if (empty($userid))
	{
		print_stop_message('invalid_user_specified');
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "usertextfield SET
		signature = '" . addslashes($signature) . "'
		WHERE userid = $userid
	");

	if (can_moderate(0, 'canviewprofile'))
	{
		define('CP_REDIRECT', "user.php?do=viewuser&amp;userid=$userid");
	}
	else
	{
		define('CP_REDIRECT', "index.php?do=home");
	}
	print_stop_message('saved_signature_successfully');

}

// ###################### Start modify Profile Pic ################
if ($_REQUEST['do'] == 'profilepic')
{
	globalize($_REQUEST, array('userid' => INT));

	if (!can_moderate(0, 'caneditprofilepic'))
	{
		print_stop_message('no_permission');
	}

	$noalter = explode(',', $undeletableusers);
	if (!empty($noalter[0]) AND in_array($userid, $noalter))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$userinfo = fetch_userinfo($userid, 8);

	if (empty($userinfo))
	{
		print_stop_message('invalid_user_specified');
	}

	print_form_header('user', 'updateprofilepic', 1);
	construct_hidden_code('userid', $userid);
	if (!$userinfo['profilepic'])
	{
		construct_hidden_code('useprofilepic', 1);
	}
	print_table_header($vbphrase['edit_profile_picture']);
	if ($userinfo['profilepic'])
	{
		print_description_row("<div align=\"center\"><img src=\"../image.php?$session[sessionurl]userid=$userinfo[userid]&amp;type=profile&amp;dateline=$userinfo[profilepicdateline]\" alt=\"../image.php?$session[sessionurl]userid=$user[userid]&amp;type=profile\" title=\"$userinfo[username]'s Profile Picture\" /></div>");
	}
	if ($userinfo['profilepic'])
	{
			print_yes_no_row($vbphrase['use_profile_picture'], 'useprofilepic', iif($userinfo['profilepic'], 1, 0));
	}
	print_input_row($vbphrase['enter_profile_picture_url'], 'profilepicurl', 'http://www.');
	print_upload_row($vbphrase['upload_profile_picture_from_computer'], 'upload');

	print_submit_row($vbphrase['update'], '');

}

// ###################### Start Update Profile Pic ################
if ($_POST['do'] == 'updateprofilepic')
{
	globalize($_POST, array('userid' => INT, 'useprofilepic' => INT, 'profilepicurl' => STR, 'upload' => FILE));

	if (!can_moderate(0, 'caneditprofilepic'))
	{
		print_stop_message('no_permission');
	}

	$noalter = explode(',', $undeletableusers);
	if (!empty($noalter[0]) AND in_array($userid, $noalter))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$userinfo = fetch_userinfo($userid);

	if (empty($userinfo))
	{
		print_stop_message('invalid_user_specified');
	}

	if ($useprofilepic)
	{
		require_once('./includes/functions_upload.php');
		process_image_upload('profilepic', $profilepicurl, $userinfo);
	}
	else
	{
		// not using a profilepic
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "customprofilepic WHERE userid = $userinfo[userid]");
	}

	if (can_moderate(0, 'canviewprofile'))
	{
		define('CP_REDIRECT', "user.php?do=viewuser&amp;userid=$userid");
	}
	else
	{
		define('CP_REDIRECT', "index.php?do=home");
	}
	print_stop_message('saved_profile_picture_successfully');

}

// ###################### Start modify Avatar ################
if ($_REQUEST['do'] == 'avatar')
{
	globalize($_REQUEST, array('userid' => INT, 'startpage' => INT, 'perpage' => INT));

	if (!can_moderate(0, 'caneditavatar'))
	{
		print_stop_message('no_permission_avatars');
	}

	$noalter = explode(',', $undeletableusers);
	if (!empty($noalter[0]) AND in_array($userid, $noalter))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$bbuserinfo = fetch_userinfo($userid);

	if (empty($bbuserinfo))
	{
		print_stop_message('invalid_user_specified');
	}

	$avatarchecked["$bbuserinfo[avatarid]"] = HTML_CHECKED;
	$nouseavatarchecked = '';
	if (!$avatarinfo = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "customavatar WHERE userid = $userid"))
	{
		// no custom avatar exists
		if (!$bbuserinfo['avatarid'])
		{
			// must have no avatar selected
			$nouseavatarchecked = HTML_CHECKED;
			$avatarchecked[0] = '';
		}
	}
	if ($startpage < 1)
	{
		$startpage = 1;
	}
	if ($perpage < 1)
	{
		$perpage = 25;
	}
	$avatarcount = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "avatar");
	$totalavatars = $avatarcount['count'];
	if (($startpage - 1) * $perpage > $totalavatars)
	{
		if ((($totalavatars / $perpage) - (intval($totalavatars / $perpage))) == 0)
		{
			$startpage = $totalavatars / $perpage;
		}
		else
		{
			$startpage = intval(($totalavatars / $perpage)) + 1;
		}
	}
	$limitlower = ($startpage - 1) * $perpage+1;
	$limitupper = ($startpage) * $perpage;
	if ($limitupper > $totalavatars)
	{
		$limitupper = $totalavatars;
		if ($limitlower > $totalavatars)
		{
			$limitlower = $totalavatars - $perpage;
		}
	}
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}
	$avatars = $DB_site->query("
		SELECT * FROM " . TABLE_PREFIX . "avatar
		ORDER BY title LIMIT " . ($limitlower-1) . ", $perpage
	");
	$avatarcount = 0;
	if ($totalavatars > 0)
	{
		print_form_header('user', 'avatar');
		construct_hidden_code('userid', $userid);
		print_table_header(
			$vbphrase['avatars_to_show_per_page'] .
			': <input type="text" name="perpage" value="' . intval($perpage) . '" size="5" tabindex="1" />
			<input type="submit" class="button" value="' . $vbphrase['go'] . '" tabindex="1" />
		');
		print_table_footer();
	}

	print_form_header('user', 'updateavatar', 1);
	print_table_header($vbphrase['avatars']);

	$output = '<table border="0" cellpadding="6" cellspacing="1" class="tborder" align="center" width="100%">';
	while ($avatar = $DB_site->fetch_array($avatars))
	{
		$avatarid = $avatar['avatarid'];
		$avatar['avatarpath'] = iif(substr($avatar['avatarpath'], 0, 7) != 'http://' AND $avatar['avatarpath']{0} != '/', '../', '') . $avatar['avatarpath'];
		if ($avatarcount == 0)
		{
			$output .= '<tr class="' . fetch_row_bgclass() . '">';
		}
		$output .= "<td valign=\"bottom\" align=\"center\"><input type=\"radio\" name=\"avatarid\" value=\"$avatar[avatarid]\" tabindex=\"1\" $avatarchecked[$avatarid] />";
		$output .= "<img src=\"$avatar[avatarpath]\" alt=\"\" /><br />$avatar[title]</td>";
		$avatarcount++;
		if ($avatarcount == 5)
		{
			echo '</tr>';
			$avatarcount = 0;
		}
	}
	if ($avatarcount != 0)
	{
		while ($avatarcount != 5)
		{
			$output .= '<td>&nbsp;</td>';
			$avatarcount++;
		}
		echo '</tr>';
	}
	if ((($totalavatars / $perpage) - (intval($totalavatars / $perpage))) == 0)
	{
		$numpages = $totalavatars / $perpage;
	}
	else
	{
		$numpages = intval($totalavatars / $perpage) + 1;
	}
	if ($startpage == 1)
	{
		$starticon = 0;
		$endicon = $perpage - 1;
	}
	else
	{
		$starticon = ($startpage - 1) * $perpage;
		$endicon = ($perpage * $startpage) - 1 ;
	}
	if ($numpages > 1)
	{
		for ($x = 1; $x <= $numpages; $x++)
		{
			if ($x == $startpage)
			{
				$pagelinks .= " [<b>$x</b>] ";
			}
			else
			{
				$pagelinks .= " <a href=\"user.php?startpage=$x&perpage=$perpage&do=avatar&userid=$userid\">$x</a> ";
			}
		}
	}
	if ($startpage != $numpages)
	{
		$nextstart = $startpage + 1;
		$nextpage = " <a href=\"user.php?startpage=$nextstart&perpage=$perpage&do=avatar&userid=$userid\">" . $vbphrase['next_page'] . "</a>";
		$eicon = $endicon + 1;
	}
	else
	{
		$eicon = $totalavatars;
	}
	if ($startpage != 1)
	{
		$prevstart = $startpage - 1;
		$prevpage = "<a href=\"user.php?startpage=$prevstart&perpage=$perpage&do=avatar&userid=$userid\">" . $vbphrase['prev_page'] . "</a> ";
	}
	$sicon = $starticon +  1;
	if ($totalavatars > 0)
	{
		if ($pagelinks)
		{
			$colspan = 3;
		}
		else
		{
			$colspan = 5;
		}
		$output .= '<tr><td class="thead" align="center" colspan="' . $colspan . '">';
		$output .= construct_phrase($vbphrase['showing_avatars_x_to_y_of_z'], $sicon, $eicon, $totalavatars) . '</td>';
		if ($pagelinks)
		{
			$output .= "<td class=\"thead\" colspan=\"2\" align=\"center\">$vbphrase[page]: <span class=\"normal\">$prevpage $pagelinks $nextpage</span></td>";
		}
		$output .= '</tr>';
	}
	$output .= '</table>';

	if ($totalavatars > 0)
	{
		print_description_row($output);
	}

	if ($nouseavatarchecked)
	{
		print_description_row($vbphrase['user_has_no_avatar']);
	}
	else
	{
		print_yes_row($vbphrase['delete_avatar'], 'avatarid', $vbphrase['yes'], '', -1);
	}
	print_table_break();
	print_table_header($vbphrase['custom_avatar']);

	require_once('./includes/functions_user.php');
	$bbuserinfo['avatarurl'] = fetch_avatar_url($bbuserinfo['userid']);

	if ($bbuserinfo['avatarurl'] == '' OR $bbuserinfo['avatarid'] != 0)
	{
		$bbuserinfo['avatarurl'] = '<img src="' . $vboptions['cleargifurl'] . '" alt="" border="0" />';
	}
	else
	{
		$bbuserinfo['avatarurl'] = "<img src=\"../$bbuserinfo[avatarurl]\" alt=\"\" border=\"0\" />";
	}
	print_yes_row(
		iif($avatarchecked[0] != '',
			$vbphrase['use_current_avatar'] . ' ' . $bbuserinfo['avatarurl'],
			$vbphrase['add_new_custom_avatar']
		)
	, 'avatarid', $vbphrase['yes'], $avatarchecked[0], 0);
	print_input_row($vbphrase['enter_avatar_url'], 'avatarurl', 'http://www.');
	print_upload_row($vbphrase['upload_avatar_from_computer'], 'upload');
	construct_hidden_code('userid', $userid);
	print_submit_row($vbphrase['save']);
}

// ###################### Start Update Avatar ################
if ($_POST['do'] == 'updateavatar')
{
	globalize($_POST, array('userid' => INT, 'avatarid' => INT, 'useavatar', 'avatarurl' => STR, 'upload' => FILE));

	if (!can_moderate(0, 'caneditavatar'))
	{
		print_stop_message('no_permission_avatars');
	}

	$noalter = explode(',', $undeletableusers);
	if (!empty($noalter[0]) AND in_array($userid, $noalter))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$useavatar = iif($avatarid == -1, 0, 1);

	$userinfo = fetch_userinfo($userid);

	if (empty($userinfo))
	{
		print_stop_message('invalid_user_specified');
	}

	if ($useavatar)
	{
		if (!$avatarid)
		{
			// custom avatar
			require_once('./includes/functions_upload.php');
			process_image_upload('avatar', $avatarurl, $userinfo);
		}
		else
		{
			// predefined avatar
			// let the admin set the user to have any avatar, so don't include any of the checks
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "customavatar WHERE userid = $userinfo[userid]");
			@unlink("$vboptions[avatarpath]/avatar$userinfo[userid]_$userinfo[avatarrevision].gif");
		}
	}
	else
	{
		// not using an avatar
		$avatarid = 0;
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "customavatar WHERE userid = $userinfo[userid]");
		@unlink("$vboptions[avatarpath]/avatar$userinfo[userid]_$userinfo[avatarrevision].gif");
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user SET
		avatarid = " . intval($avatarid) . "
		WHERE userid = $userinfo[userid]
	");

	if (can_moderate(0, 'canviewprofile'))
	{
		define('CP_REDIRECT', "user.php?do=viewuser&amp;userid=$userid");
	}
	else
	{
		define('CP_REDIRECT', "index.php?do=home");
	}
	print_stop_message('saved_avatar_successfully');
}

// ###################### Start Moderate Group Join Requests #######################
if ($_REQUEST['do'] == 'viewjoinrequests')
{
	if ($permissions['adminpermissions'] & CANCONTROLPANEL)
	{
		$userlink = "<a href=\"../$admincpdir/user.php?$session[sessionurl]do=edit&amp;userid=%d\" target=\"_blank\">%s</a>";
		$grouplink = "../$admincpdir/usergroup.php?$session[sessionurl]do=viewjoinrequests&amp;usergroupid=%d";
	}
	else
	{
		$userlink = "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=%d\" target=\"_blank\">%s</a>";
		$grouplink = "../joinrequests.php?$session[sessionurl]usergroupid=%d";
	}

	// get array of all usergroup leaders
	$bbuserleader = array();
	$leaders = array();
	$groupleaders = $DB_site->query("
		SELECT ugl.*, user.username
		FROM " . TABLE_PREFIX . "usergroupleader AS ugl
		INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
	");
	while ($groupleader = $DB_site->fetch_array($groupleaders))
	{
		if ($groupleader['userid'] == $bbuserinfo['userid'])
		{
			$bbuserleader[] = $groupleader['usergroupid'];
		}
		$leaders["$groupleader[usergroupid]"]["$groupleader[userid]"] = sprintf($userlink, $groupleader['userid'], $groupleader['username']);
	}
	unset($groupleader);
	$DB_site->free_result($groupleaders);

	if (empty($bbuserleader) AND !($permissions['adminpermissions'] & CANCONTROLPANEL))
	{
		print_stop_message('no_permission');
	}

	$requests = $DB_site->query("
		SELECT usergrouprequest.usergroupid, COUNT(usergrouprequestid) AS requests
		FROM " . TABLE_PREFIX . "usergrouprequest AS usergrouprequest
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE user.userid IS NOT NULL
		GROUP BY usergroupid
	");
	while ($request = $DB_site->fetch_array($requests))
	{
		$usergroupcache["$request[usergroupid]"]['requests'] = $request['requests'];
	}
	unset($request);
	$DB_site->free_result($requests);

	print_form_header('', '');
	print_table_header($vbphrase['join_requests_manager'], 4);
	print_cells_row(array(
		$vbphrase['usergroup'],
		$vbphrase['usergroup_leader'],
		$vbphrase['join_requests'],
		$vbphrase['controls']
	), 1);
	foreach ($usergroupcache AS $usergroupid => $usergroup)
	{
		if ($usergroup['ispublicgroup'] AND in_array($usergroupid, $bbuserleader))
		{
			print_cells_row(array(
				$usergroup['title'],
				iif(empty($leaders["$usergroupid"]), "<i>$vbphrase[n_a]</i>", implode(', ', $leaders["$usergroupid"])),
				vb_number_format($usergroup['requests']),
				construct_link_code($vbphrase['view_join_requests'], sprintf($grouplink, $usergroupid))
			));
		}
	}
	print_table_footer();

}

// ###################### Start Reputation List #######################
if ($_REQUEST['do'] == 'reputation')
{

	globalize($_REQUEST, array('userid' => INT, 'perpage' => INT, 'page' => INT));

	if (!can_moderate(0, 'caneditreputation') OR !$vboptions['reputationenable'])
	{
		print_stop_message('no_permission');
	}

	$userinfo = fetch_userinfo($userid);

	$repcount = $DB_site->query_first("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "reputation
		WHERE userid = $userid
	");
	$totalrep = $repcount['count'];

	sanitize_pageresults($totalrep, $page, $perpage);
	$startat = ($page - 1) * $perpage;
	$totalpages = ceil($totalrep / $perpage);

	$comments = $DB_site->query("
		SELECT reputation.*, user.username
		FROM " . TABLE_PREFIX . "reputation AS reputation
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (reputation.whoadded = user.userid)
		WHERE reputation.userid = $userid
		ORDER BY reputation.dateline DESC
		LIMIT $startat, $perpage
	");
	if ($DB_site->num_rows($comments))
	{

		if ($page != 1)
		{
			$prv = $page - 1;
			$firstpage = "<input type=\"button\" class=\"button\" value=\"&laquo; " . $vbphrase['first_page'] . "\" tabindex=\"1\" onclick=\"window.location='user.php?$session[sessionurl]do=reputation&userid=$userid&perpage=$perpage&page=1'\">";
			$prevpage = "<input type=\"button\" class=\"button\" value=\"&lt; " . $vbphrase['prev_page'] . "\" tabindex=\"1\" onclick=\"window.location='user.php?$session[sessionurl]do=reputation&userid=$userid&perpage=$perpage&page=$prv'\">";
		}

		if ($page != $totalpages)
		{
			$nxt = $page + 1;
			$nextpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['next_page'] . " &gt;\" tabindex=\"1\" onclick=\"window.location='user.php?$session[sessionurl]do=reputation&userid=$userid&perpage=$perpage&page=$nxt'\">";
			$lastpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['last_page'] . " &raquo;\" tabindex=\"1\" onclick=\"window.location='user.php?$session[sessionurl]do=reputation&userid=$userid&perpage=$perpage&page=$totalpages'\">";
		}

		print_form_header('user', 'reputation');
		print_table_header(construct_phrase($vbphrase['reputation_for_a_page_b_c_there_are_d_comments'], $userinfo['username'], $page, vb_number_format($totalpages), vb_number_format($totalrep)), 4);

		$headings = array();
		$headings[] = "<a href='user.php?$session[sessionurl]do=reputation&userid=$userid&perpage=$perpage&orderby=user&page=$page' title='" . $vbphrase['order_by_username'] . "'>" . $vbphrase['username'] . "</a>";
		$headings[] = "<a href='user.php?$session[sessionurl]do=reputation&userid=$userid&perpage=$perpage&orderby=date&page=$page' title='" . $vbphrase['order_by_date'] . "'>" . $vbphrase['date'] . "</a>";
		$headings[] = $vbphrase['reason'];
		$headings[] = $vbphrase['edit'];
		print_cells_row($headings, 1);

		while ($comment = $DB_site->fetch_array($comments))
		{
			$cell = array();
			$cell[] = "<a href=\"user.php?$session[sessionurl]do=viewuser&amp;userid=$comment[whoadded]\"><b>$comment[username]</b></a>";
			$cell[] = '<span class="smallfont">' . vbdate($vboptions['logdateformat'], $comment['dateline']) . '</span>';
			$cell[] = htmlspecialchars_uni($comment['reason']);
			$cell[] = construct_link_code($vbphrase['edit'], "user.php?$session[sessionurl]do=editreputation&reputationid=$comment[reputationid]");
			print_cells_row($cell);
		}

		print_table_footer(4, "$firstpage $prevpage &nbsp; $nextpage $lastpage");

	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

// ###################### Start Reputation Edit Form #######################
if ($_REQUEST['do'] == 'editreputation')
{
	globalize($_REQUEST, array('reputationid' => INT));

	if (!can_moderate(0, 'caneditreputation') OR !$vboptions['reputationenable'])
	{
		print_stop_message('no_permission');
	}

	$reputation = $DB_site->query_first("
		SELECT reason, dateline, userid
		FROM " . TABLE_PREFIX . "reputation
		WHERE reputationid = $reputationid
	");

	print_form_header('user', 'doeditreputation');
	construct_hidden_code('reputationid', $reputationid);
	construct_hidden_code('userid', $reputation['userid']);
	print_table_header($vbphrase['edit_reputation_comment']);
	print_label_row($vbphrase['date'], vbdate($vboptions['logdateformat'], $reputation['dateline']));
	print_textarea_row($vbphrase['reason'], 'reason', $reputation['reason'], 4, 40, 1, 0);
	print_submit_row($vbphrase['update'], 0);


}

// ###################### Start Actual Reputation Editing #######################
if ($_POST['do'] == 'doeditreputation')
{
	globalize($_REQUEST, array('reputationid' => INT, 'reason' => STR, 'userid' => INT));

	if (!can_moderate(0, 'caneditreputation') OR !$vboptions['reputationenable'])
	{
		print_stop_message('no_permission');
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "reputation
		SET reason = '" . addslashes($reason) . "'
		WHERE reputationid = $reputationid
	");

	define('CP_REDIRECT', "user.php?do=reputation&amp;userid=$userid");
	print_stop_message('updated_reason_successfully');

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13299 $
|| ####################################################################
\*======================================================================*/
?>