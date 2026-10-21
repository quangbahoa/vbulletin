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
define('CVS_REVISION', '$RCSfile$ - $Revision: 10869 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cppermission', 'cpuser', 'promotion', 'pm', 'cpusergroup');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_ranks.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminpermissions'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif(!empty($_REQUEST['usergroupid']), "usergroup id = $_REQUEST[usergroupid]", iif(!empty($_REQUEST['usergroupleaderid']), "leader id = $_REQUEST[usergroupleaderid]")));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['usergroup_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start getuserid #######################
function fetch_userid_from_username($username)
{
	global $DB_site;
	if ($user = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes(trim($username)) . "'"))
	{
		return $user['userid'];
	}
	else
	{
		return false;
	}
}

// ###################### Start add / update #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{

	if ($_REQUEST['do'] == 'add')
	{
		// get a list of other usergroups to base this one off of
		print_form_header('usergroup', 'add');
		$groups = $DB_site->query("SELECT usergroupid, title FROM " . TABLE_PREFIX . "usergroup ORDER BY title");
		$selectgroups = '';
		while ($group = $DB_site->fetch_array($groups))
		{
			$selectgroups .= "<option value=\"$group[usergroupid]\" " . iif($group['usergroupid'] == $_REQUEST['defaultgroupid'], HTML_SELECTED) . ">$group[title]</option>\n";
		}
		print_description_row(construct_table_help_button('defaultgroupid') . '<b>' . $vbphrase['create_usergroup_based_off_of_usergroup'] . '</b> <select name="defaultgroupid" tabindex="1" class="bginput">' . $selectgroups . '</select> <input type="submit" class="button" value="' . $vbphrase['go'] . '" tabindex="1" />', 0, 2, 'tfoot', 'center');
		print_table_footer();
	}

	print_form_header('usergroup', 'update');
	print_column_style_code(array('width: 70%', 'width: 30%'));

	if ($_REQUEST['do'] == 'add')
	{
		if (!empty($_REQUEST['defaultgroupid']))
		{
			// set defaults to this group's info
			$defaultgroupid = intval($_REQUEST['defaultgroupid']);
			$usergroup = $DB_site->query_first("
				SELECT * FROM " . TABLE_PREFIX . "usergroup
				WHERE usergroupid = $defaultgroupid
			");

			$ug_bitfield = array();
			foreach($_BITFIELD['usergroup'] AS $permissiongroup => $fields)
			{
				$ug_bitfield = array_merge(convert_bits_to_array($usergroup["$permissiongroup"], $fields) , $ug_bitfield);
			}
		}
		else
		{
			// set default yes permissions (bitfields)
			$ug_bitfield = array(
				'showgroup' => 1,
				'canview' => 1,
				'canviewmembers' => 1,
				'canviewothers' => 1,
				'cagetattachment' => 1,
				'cansearch' => 1,
				'canmodifyprofile' => 1,
				'canthreadrate' => 1,
				'canpostattachment' => 1,
				'canpostpoll' => 1,
				'canvote' => 1,
				'canwhosonline' => 1,
				'allowhidden' => 1,
				'showeditedby' => 1,
				'canseeprofilepic' => 1,
				'canusesignature' => 1,
				'cannegativerep' => 1,
				'canuserep' => 1,
			);
			// set default numeric permissions
			$usergroup = array(
				'pmquota' => 0, 'pmsendmax' => 5, 'attachlimit' => 1000000,
				'avatarmaxwidth' => 50, 'avatarmaxheight' => 50, 'avatarmaxsize' => 20000,
				'profilepicmaxwidth' => 100, 'profilepicmaxheight' => 100, 'profilepicmaxsize' => 25000
			);
		}
		print_table_header($vbphrase['add_new_usergroup']);
	}
	else
	{
		$usergroupid = intval($_REQUEST['usergroupid']);
		$usergroup = $DB_site->query_first("
			SELECT * FROM " . TABLE_PREFIX . "usergroup
			WHERE usergroupid = $usergroupid
		");

		$ug_bitfield = array();
		foreach($_BITFIELD['usergroup'] AS $permissiongroup => $fields)
		{
			$ug_bitfield = array_merge(convert_bits_to_array($usergroup["$permissiongroup"], $fields) , $ug_bitfield);
		}
		construct_hidden_code('usergroupid', $usergroupid);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['usergroup'],$usergroup[title], $usergroup[usergroupid]), 2, 0);
	}

	print_input_row($vbphrase['title'], 'usergroup[title]', $usergroup['title']);
	print_input_row($vbphrase['description'], 'usergroup[description]', $usergroup['description']);
	print_input_row($vbphrase['usergroup_user_title'], 'usergroup[usertitle]', $usergroup['usertitle']);
	print_label_row($vbphrase['username_markup'],
		'<span style="white-space:nowrap">
		<input size="15" type="text" class="bginput" name="usergroup[opentag]" value="' . htmlspecialchars_uni($usergroup['opentag']) . '" tabindex="1" />
		<input size="15" type="text" class="bginput" name="usergroup[closetag]" value="' . htmlspecialchars_uni($usergroup['closetag']) . '" tabindex="1" />
		</span>', '', 'top', 'htmltags');
	print_input_row($vbphrase['password_expiry'], 'usergroup[passwordexpires]', $usergroup['passwordexpires']);
	print_input_row($vbphrase['password_history'], 'usergroup[passwordhistory]', $usergroup['passwordhistory']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	print_table_header($vbphrase['usergroup_options']);
	print_yes_no_row(construct_phrase($vbphrase['viewable_on_showgroups'], $session['sessionurl']), 'usergroup[showgroup]', $ug_bitfield['showgroup']);

	print_yes_no_row($vbphrase['birthdays_viewable'], 'usergroup[showbirthday]', $ug_bitfield['showbirthday']);
	print_yes_no_row($vbphrase['viewable_on_memberlist'], 'usergroup[showmemberlist]', $ug_bitfield['showmemberlist']);
	print_yes_no_row($vbphrase['allow_member_groups'], 'usergroup[allowmembergroups]', $ug_bitfield['allowmembergroups']);
	print_yes_no_row($vbphrase['is_banned_group'], 'usergroup[isbannedgroup]', $ug_bitfield['isbannedgroup']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	if ($usergroupid > 7 OR $_REQUEST['do'] == 'add')
	{
		print_table_header($vbphrase['public_group_settings']);
		print_yes_no_row($vbphrase['public_joinable_custom_usergroup'], 'usergroup[ispublicgroup]', $usergroup['ispublicgroup']);
		print_yes_no_row($vbphrase['can_override_primary_group_title'], 'usergroup[canoverride]', $usergroup['canoverride']);
		print_table_break();
		print_column_style_code(array('width: 70%', 'width: 30%'));
	}

	print_table_header($vbphrase['general_permissions']);
	print_yes_no_row($vbphrase['can_see_invisible_users'], 'usergroup[canseehidden]', $ug_bitfield['canseehidden']);
	print_yes_no_row($vbphrase['can_view_member_info'], 'usergroup[canviewmembers]', $ug_bitfield['canviewmembers']);
	print_yes_no_row($vbphrase['can_edit_own_profile'], 'usergroup[canmodifyprofile]', $ug_bitfield['canmodifyprofile']);
	print_yes_no_row($vbphrase['can_set_self_invisible'], 'usergroup[caninvisible]', $ug_bitfield['caninvisible']);
	print_yes_no_row($vbphrase['show_edited_by_note_on_edited_messages'], 'usergroup[showeditedby]', $ug_bitfield['showeditedby']);
	print_yes_no_row($vbphrase['can_use_custom_title'], 'usergroup[canusecustomtitle]', $ug_bitfield['canusecustomtitle']);
	print_yes_no_row($vbphrase['can_use_signatures'], 'usergroup[canusesignature]', $ug_bitfield['canusesignature']);
	print_yes_no_row($vbphrase['can_view_others_profile_pictures'], 'usergroup[canseeprofilepic]', $ug_bitfield['canseeprofilepic']);
	print_yes_no_row($vbphrase['can_view_hidden_custom_fields'], 'usergroup[canseehiddencustomfields]', $ug_bitfield['canseehiddencustomfields']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	print_table_header($vbphrase['forum_viewing_permissions']);
	print_yes_no_row($vbphrase['can_view_board'], 'usergroup[canview]', $ug_bitfield['canview']);
	print_yes_no_row($vbphrase['can_view_others_threads'], 'usergroup[canviewothers]', $ug_bitfield['canviewothers']);
	print_yes_no_row($vbphrase['can_see_deletion_notices'], 'usergroup[canseedelnotice]', $ug_bitfield['canseedelnotice']);
	print_yes_no_row($vbphrase['can_search_forums'], 'usergroup[cansearch]', $ug_bitfield['cansearch']);
	print_yes_no_row($vbphrase['can_use_email_to_friend'], 'usergroup[canemail]', $ug_bitfield['canemail']);
	print_yes_no_row($vbphrase['can_download_attachments'], 'usergroup[cangetattachment]', $ug_bitfield['cangetattachment']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	print_table_header($vbphrase['post_thread_permissions']);
	print_yes_no_row($vbphrase['can_post_threads'], 'usergroup[canpostnew]', $ug_bitfield['canpostnew']);
	print_yes_no_row($vbphrase['can_reply_to_own_threads'], 'usergroup[canreplyown]', $ug_bitfield['canreplyown']);
	print_yes_no_row($vbphrase['can_reply_to_others_threads'], 'usergroup[canreplyothers]', $ug_bitfield['canreplyothers']);
	print_yes_no_row($vbphrase['can_edit_own_posts'], 'usergroup[caneditpost]', $ug_bitfield['caneditpost']);
	print_yes_no_row($vbphrase['can_delete_own_posts'], 'usergroup[candeletepost]', $ug_bitfield['candeletepost']);
	print_yes_no_row($vbphrase['can_move_own_threads'], 'usergroup[canmove]', $ug_bitfield['canmove']);
	print_yes_no_row($vbphrase['can_open_close_own_threads'], 'usergroup[canopenclose]', $ug_bitfield['canopenclose']);
	print_yes_no_row($vbphrase['can_delete_own_threads'], 'usergroup[candeletethread]', $ug_bitfield['candeletethread']);
	print_yes_no_row($vbphrase['always_moderate_posts'], 'usergroup[isalwaysmoderated]', $ug_bitfield['isalwaysmoderated']);
	print_yes_no_row($vbphrase['can_rate_threads'], 'usergroup[canthreadrate]', $ug_bitfield['canthreadrate']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	if ($usergroupid != 1) // Guests can not post attachments
	{
		print_table_header($vbphrase['attachment_permissions']);
		print_yes_no_row($vbphrase['can_upload_attachments'], 'usergroup[canpostattachment]', $ug_bitfield['canpostattachment']);
		print_input_row($vbphrase['space_in_bytes_attachlimit'], 'usergroup[attachlimit]', $usergroup['attachlimit'], 1, 20);
		print_table_break();
		print_column_style_code(array('width: 70%', 'width: 30%'));
	}

	print_table_header($vbphrase['poll_permissions']);
	print_yes_no_row($vbphrase['can_post_polls'], 'usergroup[canpostpoll]', $ug_bitfield['canpostpoll']);
	print_yes_no_row($vbphrase['can_vote_on_polls'], 'usergroup[canvote]', $ug_bitfield['canvote']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	print_table_header($vbphrase['picture_uploading_permissions']);
	print_yes_no_row($vbphrase['can_upload_custom_avatars'], 'usergroup[canuseavatar]', $ug_bitfield['canuseavatar']);
	print_input_row($vbphrase['custom_avatar_max_width'], 'usergroup[avatarmaxwidth]', $usergroup['avatarmaxwidth'], 1, 20);
	print_input_row($vbphrase['custom_avatar_max_height'], 'usergroup[avatarmaxheight]', $usergroup['avatarmaxheight'], 1, 20);
	print_input_row($vbphrase['custom_avatar_max_filesize'], 'usergroup[avatarmaxsize]', $usergroup['avatarmaxsize'], 1, 20);
	print_yes_no_row($vbphrase['can_upload_profile_pictures'], 'usergroup[canprofilepic]', $ug_bitfield['canprofilepic']);
	print_input_row($vbphrase['profile_picture_max_width'], 'usergroup[profilepicmaxwidth]', $usergroup['profilepicmaxwidth'], 1, 20);
	print_input_row($vbphrase['profile_picture_max_height'], 'usergroup[profilepicmaxheight]', $usergroup['profilepicmaxheight'], 1, 20);
	print_input_row($vbphrase['profile_picture_max_filesize'], 'usergroup[profilepicmaxsize]', $usergroup['profilepicmaxsize'], 1, 20);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	print_table_header($vbphrase['private_message_permissions']);
	print_input_row($vbphrase['maximum_stored_messages'], 'usergroup[pmquota]', $usergroup['pmquota'], 1, 20);
	print_yes_no_row($vbphrase['can_use_message_tracking'], 'usergroup[cantrackpm]', $ug_bitfield['cantrackpm']);
	print_yes_no_row($vbphrase['can_deny_pm_receipt'], 'usergroup[candenypmreceipts]', $ug_bitfield['candenypmreceipts']);
	print_input_row($vbphrase['maximum_recipients_to_send_pms'], 'usergroup[pmsendmax]', $usergroup['pmsendmax'], 1, 20);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	print_table_header($vbphrase['calendar_permissions']);
	print_yes_no_row($vbphrase['can_view_calendar'], 'usergroup[canviewcalendar]', $ug_bitfield['canviewcalendar']);
	print_yes_no_row($vbphrase['can_post_events'], 'usergroup[canpostevent]', $ug_bitfield['canpostevent']);
	print_yes_no_row($vbphrase['can_edit_own_events'], 'usergroup[caneditevent]', $ug_bitfield['caneditevent']);
	print_yes_no_row($vbphrase['can_delete_own_events'], 'usergroup[candeleteevent]', $ug_bitfield['candeleteevent']);
	print_yes_no_row($vbphrase['can_view_others_events'], 'usergroup[canviewothersevent]', $ug_bitfield['canviewothersevent']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	print_table_header($vbphrase['whos_online_permissions']);
	print_yes_no_row($vbphrase['can_view_whos_online'], 'usergroup[canwhosonline]', $ug_bitfield['canwhosonline']);
	print_yes_no_row($vbphrase['can_view_wol_detail_location'], 'usergroup[canwhosonlinefull]', $ug_bitfield['canwhosonlinefull']);
	print_yes_no_row($vbphrase['can_view_ip_addresses'], 'usergroup[canwhosonlineip]', $ug_bitfield['canwhosonlineip']);
	print_yes_no_row($vbphrase['can_view_wol_bad_location'], 'usergroup[canwhosonlinebad]', $ug_bitfield['canwhosonlinebad']);
	print_yes_no_row($vbphrase['can_view_wol_actual_location'], 'usergroup[canwhosonlinelocation]', $ug_bitfield['canwhosonlinelocation']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	// Guests can not leave reputation
	if ($usergroupid != 1)
	{
		print_table_header($vbphrase['user_reputation_permissions']);
		print_yes_no_row($vbphrase['can_use_reputation'], 'usergroup[canuserep]', $ug_bitfield['canuserep']);
		print_yes_no_row($vbphrase['can_leave_negative_reputation'], 'usergroup[cannegativerep]', $ug_bitfield['cannegativerep']);
		print_yes_no_row($vbphrase['can_see_who_left_user_ratings'], 'usergroup[canseeownrep]', $ug_bitfield['canseeownrep']);
		print_yes_no_row($vbphrase['can_hide_reputation'], 'usergroup[canhiderep]', $ug_bitfield['canhiderep']);
		print_table_break();
		print_column_style_code(array('width: 70%', 'width: 30%'));
	}
	//print_yes_no_row($vbphrase['can_see_rep_left_for_others'], 'usergroup[canseeothersrep]', $ug_bitfield['canseeothersrep']);

	print_table_header($vbphrase['user_note_permissions']);
	print_yes_no_row($vbphrase['can_view_own_user_notes'], 'usergroup[canviewownusernotes]', $ug_bitfield['canviewownusernotes']);
	print_yes_no_row($vbphrase['can_manage_own_user_notes'], 'usergroup[canmanageownusernotes]', $ug_bitfield['canmanageownusernotes']);
	print_yes_no_row($vbphrase['can_post_user_notes_about_self'], 'usergroup[canpostownusernotes]', $ug_bitfield['canpostownusernotes']);
	print_yes_no_row($vbphrase['can_view_others_user_notes'], 'usergroup[canviewothersusernotes]', $ug_bitfield['canviewothersusernotes']);
	print_yes_no_row($vbphrase['can_manage_others_user_notes'], 'usergroup[canmanageothersusernotes]', $ug_bitfield['canmanageothersusernotes']);
	print_yes_no_row($vbphrase['can_post_user_notes_about_others'], 'usergroup[canpostothersusernotes]', $ug_bitfield['canpostothersusernotes']);
	print_yes_no_row($vbphrase['can_edit_own_user_notes'], 'usergroup[caneditownusernotes]', $ug_bitfield['caneditownusernotes']);
	print_yes_no_row($vbphrase['other_users_can_be_posted'], 'usergroup[canbeusernoted]', $ug_bitfield['canbeusernoted']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));
	print_table_header($vbphrase['administrator_permissions']);
	print_yes_no_row($vbphrase['is_super_moderator'], 'usergroup[ismoderator]', $ug_bitfield['ismoderator']);

	if ($usergroupid == 6)
	{
		print_yes_row($vbphrase['can_access_control_panel'], 'usergroup[cancontrolpanel]', $vbphrase['yes'], true);
	}
	else
	{
		print_yes_no_row($vbphrase['can_access_control_panel'], 'usergroup[cancontrolpanel]', $ug_bitfield['cancontrolpanel']);
	}
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	if ($_REQUEST['do'] == 'add')
	{
		$permgroups = $DB_site->query("
			SELECT usergroup.usergroupid, title,
				(COUNT(forumpermission.forumpermissionid) + COUNT(calendarpermission.calendarpermissionid)) AS permcount
			FROM " . TABLE_PREFIX . "usergroup AS usergroup
			LEFT JOIN " . TABLE_PREFIX . "forumpermission AS forumpermission ON (usergroup.usergroupid = forumpermission.usergroupid)
			LEFT JOIN " . TABLE_PREFIX . "calendarpermission AS calendarpermission ON (usergroup.usergroupid = calendarpermission.usergroupid)
			GROUP BY usergroup.usergroupid
			HAVING permcount > 0
			ORDER BY title
		");
		$ugarr = array('-1' => '--- ' . $vbphrase['none'] . ' ---');
		while ($group = $DB_site->fetch_array($permgroups))
		{
			$ugarr["$group[usergroupid]"] = $group['title'];
		}
		print_table_header($vbphrase['default_forum_permissions']);
		print_select_row($vbphrase['create_permissions_based_off_of_forum'], 'ugid_base', $ugarr);
		print_table_break();
	}

	print_submit_row(iif($_REQUEST['do'] == 'add', $vbphrase['save'], $vbphrase['update']));
}

// ###################### Start insert / update #######################
if ($_POST['do'] == 'update')
{
	$usergroupid = intval($_POST['usergroupid']);
	$usergroup = $_POST['usergroup'];

	// create bitfield values
	require_once('./includes/functions_misc.php');
	foreach($_BITFIELD['usergroup'] AS $permissiongroup => $fields)
	{
		$usergroup["$permissiongroup"] = convert_array_to_bits($usergroup, $fields, 1);
	}

	if (!empty($usergroupid))
	{
	// update
		if (!($usergroup['adminpermissions'] & CANCONTROLPANEL))
		{ // check that not removing last admin group
			$checkadmin = $DB_site->query_first("
				SELECT COUNT(*) AS usergroups
				FROM " . TABLE_PREFIX . "usergroup
				WHERE (adminpermissions & " . CANCONTROLPANEL . ") AND
					usergroupid <> $usergroupid
			");
			if ($usergroupid == 6)
			{ // stop them turning no control panel for usergroup 6, seems the most sensible thing
				print_stop_message('invalid_usergroup_specified');
			}
			if (!$checkadmin['usergroups'])
			{
				print_stop_message('cant_delete_last_admin_group');
			}
		}
		$DB_site->query(fetch_query_sql($usergroup, 'usergroup', "WHERE usergroupid=$usergroupid"));
		if (!($usergroup['genericpermissions'] & CANINVISIBLE))
		{ // make the users in this group invisible
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "user
				SET options = (options & ~$_USEROPTIONS[invisible])
				WHERE usergroupid = $usergroupid
			");
		}
		if ($usergroup['adminpermissions'] & CANCONTROLPANEL)
		{
			$ausers = $DB_site->query("
				SELECT user.userid
				FROM " . TABLE_PREFIX . "user AS user
				LEFT JOIN " . TABLE_PREFIX . "administrator as administrator ON (user.userid = administrator.userid)
				WHERE administrator.userid IS NULL AND
					user.usergroupid = $usergroupid
			");
			while ($auser = $DB_site->fetch_array($ausers))
			{
				$userids[] = "($auser[userid])";
			}

			if (!empty($userids))
			{
				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "administrator
					(userid)
					VALUES
					" . implode(',', $userids)
				);
			}
		}
		else if ($usergroupcache[$usergroupid]['adminpermissions'] & CANCONTROLPANEL)
		{
			// lets find admin usergroupids
			$ausergroupids = array();
			$usergroupcache[$usergroupid]['adminpermissions'] = $usergroup['adminpermissions'];
			foreach ($usergroupcache AS $ausergroupid => $ausergroup)
			{
				if ($ausergroup['adminpermissions'] & CANCONTROLPANEL)
				{
					$ausergroupids[] = $ausergroupid;
				}
			}
			$ausergroupids = implode(',', $ausergroupids);
			$ausers = $DB_site->query("
				SELECT userid FROM " . TABLE_PREFIX . "user
				WHERE usergroupid NOT IN ($ausergroupids)
					AND NOT FIND_IN_SET('$ausergroupids', membergroupids)
					AND (usergroupid = $usergroupid
					OR FIND_IN_SET('$usergroupid', membergroupids))
			");

			while ($auser = $DB_site->fetch_array($ausers))
			{
				$userids[] = $auser['userid'];
			}

			if (!empty($userids))
			{
				$DB_site->query("
					DELETE FROM " . TABLE_PREFIX . "administrator
					WHERE userid IN (" . implode(',', $userids) . ")"
				);
			}
		}
	}
	else
	{
	// insert
		$DB_site->query(fetch_query_sql($usergroup, 'usergroup'));
		$newugid = $DB_site->insert_id();

		$ugid_base = intval($_POST['ugid_base']);
		if ($_POST['ugid_base'] > 0)
		{
			$fperms = $DB_site->query("
				SELECT * FROM " . TABLE_PREFIX . "forumpermission
				WHERE usergroupid = $ugid_base
			");
			while ($fperm = $DB_site->fetch_array($fperms))
			{
				unset($fperm['forumpermissionid']);
				$fperm['usergroupid'] = $newugid;
				$DB_site->query(fetch_query_sql($fperm, 'forumpermission'));
			}

			$cperms = $DB_site->query("
				SELECT * FROM " . TABLE_PREFIX . "calendarpermission
				WHERE usergroupid = $ugid_base
			");
			while ($cperm = $DB_site->fetch_array($cperms))
			{
				unset($cperm['calendarpermissionid']);
				$cperm['usergroupid'] = $newugid;
				$DB_site->query(fetch_query_sql($cperm, 'calendarpermission'));
			}
		}
	}

	$markups = $DB_site->query("
		SELECT usergroupid, opentag, closetag
		FROM " . TABLE_PREFIX . "usergroup
		WHERE opentag <> '' OR
		closetag <> ''
	");
	$usergroupmarkup = array();
	while ($markup = $DB_site->fetch_array($markups))
	{
		$usergroupmarkup["$markup[usergroupid]"]['opentag'] = $markup['opentag'];
		$usergroupmarkup["$markup[usergroupid]"]['closetag'] = $markup['closetag'];
	}

	require_once('./includes/functions_databuild.php');
	build_birthdays();
	build_forum_permissions();

	define('CP_REDIRECT', 'usergroup.php?do=modify');
	print_stop_message('saved_usergroup_x_successfully', $usergroup['title']);

}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{

	if ($_REQUEST['usergroupid'] < 8)
	{
		print_stop_message('cant_delete_usergroup');
	}
	else
	{
		print_delete_confirmation('usergroup', $_REQUEST['usergroupid'], 'usergroup', 'kill', 'usergroup', 0, $vbphrase['all_members_of_this_usergroup_will_revert']);
	}

}

// ###################### Start Kill #######################
if ($_POST['do'] == 'kill')
{
	globalize($_POST, array('usergroupid' => INT));

	// update users who are in this usergroup to be in the registered usergroup
	$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET usergroupid = 2 WHERE usergroupid = $usergroupid");
	$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET displaygroupid = 0 WHERE displaygroupid = $usergroupid");
	$DB_site->query("UPDATE " . TABLE_PREFIX . "useractivation SET usergroupid = 2 WHERE usergroupid = $usergroupid");
	$DB_site->query("UPDATE " . TABLE_PREFIX . "subscription SET nusergroupid = -1 WHERE nusergroupid = $usergroupid");
	$DB_site->query("UPDATE " . TABLE_PREFIX . "subscriptionlog SET pusergroupid = 2 WHERE pusergroupid = $usergroupid");

	// now get on with deleting stuff...
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "usergroup WHERE usergroupid = $usergroupid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "forumpermission WHERE usergroupid = $usergroupid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "ranks WHERE usergroupid = $usergroupid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "usergrouprequest WHERE usergroupid = $usergroupid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "userpromotion WHERE usergroupid = $usergroupid OR joinusergroupid = $usergroupid");

	build_ranks();
	build_forum_permissions();

	// remove this group from users who have this group as a membergroup
	$updateusers = array();
	$casesql = '';
	$users = $DB_site->query("
		SELECT userid, username, membergroupids
		FROM " . TABLE_PREFIX . "user
		WHERE FIND_IN_SET('$usergroupid', membergroupids)
	");
	if ($DB_site->num_rows($users))
	{
		while($user = $DB_site->fetch_array($users))
		{
			$membergroups = fetch_membergroupids_array($user, false);
			foreach($membergroups AS $key => $val)
			{
				if ($val == $usergroupid)
				{
					unset($membergroups["$key"]);
				}
			}
			$user['membergroupids'] = implode(',', $membergroups);
			$casesql .= "WHEN $user[userid] THEN '$user[membergroupids]' ";
			$updateusers[] = $user['userid'];
		}

		// do a big update to get rid of this usergroup from matched members' membergroupids
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user SET
			membergroupids = CASE userid
			$casesql
			ELSE '' END
			WHERE userid IN(" . implode(',', $updateusers) . ")
		");
	}

	define('CP_REDIRECT', 'usergroup.php?do=modify');
	print_stop_message('deleted_usergroup_successfully');
}

// ###################### Start kill group leader #######################
if ($_POST['do'] == 'killleader')
{

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "usergroupleader WHERE usergroupleaderid = " . intval($_POST['usergroupleaderid']));

	define('CP_REDIRECT', 'usergroup.php?do=modify');
	print_stop_message('deleted_usergroup_leader_successfully');
}

// ###################### Start delete group leader #######################
if ($_REQUEST['do'] == 'removeleader')
{

	print_delete_confirmation('usergroupleader', $_REQUEST['usergroupleaderid'], 'usergroup', 'killleader', 'usergroup_leader');

}

// ###################### Start insert group leader #######################
if ($_POST['do'] == 'insertleader')
{
	globalize($_POST, array('usergroupid' => INT, 'username' => STR_NOHTML));

	if ($usergroup = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "usergroup WHERE usergroupid = $usergroupid AND ispublicgroup = 1 AND usergroupid > 7"))
	{
		if ($user = $DB_site->query_first("SELECT userid, username, usergroupid, membergroupids FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes($username) . "'"))
		{
			if ($preexists = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "usergroupleader WHERE usergroupid = $usergroupid AND userid = $user[userid]"))
			{
				print_stop_message('invalid_usergroup_leader_specified');
			}

			// update leader's member groups if necessary
			if (strpos(",$user[membergroupids],", ",$usergroupid,") === false AND $user['usergroupid'] != $usergroupid)
			{
				if (empty($user['membergroupids']))
				{
					$membergroups = $usergroupid;
				}
				else
				{
					$membergroups = "$user[membergroupids],$usergroupid";
				}
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "user
					SET membergroupids = '$membergroups'
					WHERE userid = $user[userid]
				");
			}

			// insert into usergroupleader table
			$result = $DB_site->query("
				INSERT INTO " . TABLE_PREFIX . "usergroupleader
				(userid, usergroupid)
				VALUES
				($user[userid], $usergroupid)
			");

			define('CP_REDIRECT', 'usergroup.php?do=modify');
			print_stop_message('saved_usergroup_leader_x_successfully', $username);
		}
		else
		{
			print_stop_message('invalid_user_specified');
		}
	}
	else
	{
		print_stop_message('cant_add_usergroup_leader');
	}

}

// ###################### Start add group leader #######################
if ($_REQUEST['do'] == 'addleader')
{

	$usergroupid = intval($_REQUEST['usergroupid']);

	$groups = array();
	$usergroups = $DB_site->query("
		SELECT usergroupid, title
		FROM " . TABLE_PREFIX . "usergroup
		WHERE usergroupid > 7 AND
			ispublicgroup = 1
		ORDER BY title
	");
	while($usergroup = $DB_site->fetch_array($usergroups))
	{
		$groups["$usergroup[usergroupid]"] = $usergroup['title'];
	}

	if (!isset($groups["$usergroupid"]))
	{
		print_stop_message('usergroup_not_public_or_invalid');
	}

	print_form_header('usergroup', 'insertleader');
	construct_hidden_code('usergroupid', $usergroupid);
	print_table_header($vbphrase['add_new_usergroup_leader']);
	print_select_row($vbphrase['usergroup'], 'usergroupid', $groups, $usergroupid);
	print_input_row($vbphrase['username'], 'username');
	print_submit_row($vbphrase['add'], 0);

}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	// get usergroups (don't use the cache at this point...
	// this is the only place where you could rebuild the forumcache and usergroupcache
	// without them being present already...

	unset($usergroupcache);

	$usergroups = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "usergroup ORDER BY title");
	while ($usergroup = $DB_site->fetch_array($usergroups))
	{
		$usergroupcache["$usergroup[usergroupid]"] = $usergroup;
	}
	unset($usergroup);
	$DB_site->free_result($usergroups);

	// count primary users
	$groupcounts = $DB_site->query("
		SELECT user.usergroupid, COUNT(user.userid) AS total
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup USING (usergroupid)
		WHERE usergroup.usergroupid IS NOT NULL
		GROUP BY usergroupid
	");
	while($groupcount = $DB_site->fetch_array($groupcounts))
	{
		$usergroupcache["$groupcount[usergroupid]"]['count'] = $groupcount['total'];
	}
	unset($groupcount);
	$DB_site->free_result($groupcounts);

	// count secondary users
	$groupcounts = $DB_site->query("
		SELECT membergroupids, usergroupid
		FROM " . TABLE_PREFIX . "user
		WHERE membergroupids <> ''
	");
	while ($groupcount = $DB_site->fetch_array($groupcounts))
	{
		$ids = fetch_membergroupids_array($groupcount, false);
		foreach ($ids AS $index => $value)
		{
			if ($groupcount['usergroupid'] != $value AND !empty($usergroupcache["$value"]))
			{
				$usergroupcache["$value"]['secondarycount']++;
			}
		}
	}
	unset($groupcount);
	$DB_site->free_result($groupcounts);

	// count requests
	$groupcounts = $DB_site->query("
		SELECT usergroupid, COUNT(userid) AS total
		FROM " . TABLE_PREFIX . "usergrouprequest AS usergrouprequest
		GROUP BY usergroupid
	");
	while($groupcount = $DB_site->fetch_array($groupcounts))
	{
		$usergroupcache["$groupcount[usergroupid]"]['requests'] = $groupcount['total'];
	}
	unset($groupcount);
	$DB_site->free_result($groupcounts);

	$usergroups = array();
	foreach($usergroupcache AS $group)
	{
		if ($group['usergroupid'] > 7)
		{
			if ($group['ispublicgroup'])
			{
				$usergroups['public']["$group[usergroupid]"] = $group;
			}
			else
			{
				$usergroups['custom']["$group[usergroupid]"] = $group;
			}
		}
		else
		{
			$usergroups['default']["$group[usergroupid]"] = $group;
		}
	}

	$usergroupleaders = array();
	$leaders = $DB_site->query("
		SELECT usergroupleader.*, username
		FROM " . TABLE_PREFIX . "usergroupleader AS usergroupleader
		INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
	");
	while ($leader = $DB_site->fetch_array($leaders))
	{
		$usergroupleaders["$leader[usergroupid]"][] = $leader;
	}
	unset($leader);
	$DB_site->free_result($leaders);

	$promotions = array();
	$proms = $DB_site->query("
		SELECT COUNT(*) AS count, usergroupid
		FROM " . TABLE_PREFIX . "userpromotion
		GROUP BY usergroupid
	");
	while ($prom = $DB_site->fetch_array($proms))
	{
		$promotions["$prom[usergroupid]"] = $prom['count'];
	}

	?>
	<script type="text/javascript">
	function js_usergroup_jump(usergroupid)
	{
		task = eval("document.cpform.u" + usergroupid + ".options[document.cpform.u" + usergroupid + ".selectedIndex].value");
		switch (task)
		{
			case 'edit': window.location = "usergroup.php?s=<?php echo $session['sessionhash']; ?>&do=edit&usergroupid=" + usergroupid; break;
			case 'kill': window.location = "usergroup.php?s=<?php echo $session['sessionhash']; ?>&do=remove&usergroupid=" + usergroupid; break;
			case 'list': window.location = "user.php?s=<?php echo $session['sessionhash']; ?>&do=find&user[usergroupid]=" + usergroupid; break;
			case 'list2': window.location = "user.php?s=<?php echo $session['sessionhash']; ?>&do=find&user[membergroup][]=" + usergroupid; break;
			case 'reputation': window.location = "user.php?s=<?php echo $session['sessionhash']; ?>&do=find&display[username]=1&display[options]=1&display[posts]=1&display[usergroup]=1&display[lastvisit]=1&display[reputation]=1&orderby=reputation&direction=desc&limitnumber=25&user[usergroupid]=" + usergroupid; break;
			case 'promote': window.location = "usergroup.php?s=<?php echo $session['sessionhash']; ?>&do=modifypromotion&usergroupid=" + usergroupid; break;
			case 'leader': window.location = "usergroup.php?s=<?php echo $session['sessionhash']; ?>&do=addleader&usergroupid=" + usergroupid; break;
			case 'requests': window.location = "usergroup.php?s=<?php echo $session['sessionhash']; ?>&do=viewjoinrequests&usergroupid=" + usergroupid; break;
			default: return false; break;
		}
	}
	</script>
	<?php

	// ###################### Start makeusergroupcode #######################
	function print_usergroup_row($usergroup, $options)
	{
		global $usergroupleaders, $session, $vbphrase, $promotions;

		if ($promotions["$usergroup[usergroupid]"])
		{
			$options['promote'] .= " (${promotions[$usergroup[usergroupid]]})";
		}

		$cell = array();
		$cell[] = "<b>$usergroup[title]" . iif($usergroup['canoverride'], '*') . "</b>" . iif($usergroup['ispublicgroup'], '<br /><span class="smallfont">' . $usergroup['description'] . '</span>');
		$cell[] = iif($usergroup['count'], vb_number_format($usergroup['count']), '-');
		$cell[] = iif($usergroup['secondarycount'], vb_number_format($usergroup['secondarycount']), '-');

		if ($usergroup['ispublicgroup'])
		{
			$cell[] = iif($usergroup['requests'], vb_number_format($usergroup['requests']), '0');
		}
		if ($usergroup['ispublicgroup'])
		{
			$cell_out = '<span class="smallfont">';
			if (is_array($usergroupleaders["$usergroup[usergroupid]"]))
			{
				foreach($usergroupleaders["$usergroup[usergroupid]"] AS $usergroupleader)
				{
					$cell_out .= "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$usergroupleader[userid]\"><b>$usergroupleader[username]</b></a>" . construct_link_code($vbphrase['delete'], "usergroup.php?$session[sessionurl]do=removeleader&amp;usergroupleaderid=$usergroupleader[usergroupleaderid]") . '<br />';
				}
			}
			$cell[] = $cell_out . '</span>';
		}
		$options['edit'] .= " (id: $usergroup[usergroupid])";
		$cell[] = "\n\t<select name=\"u$usergroup[usergroupid]\" onchange=\"js_usergroup_jump($usergroup[usergroupid]);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select><input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_usergroup_jump($usergroup[usergroupid]);\" />\n\t";
		print_cells_row($cell);
	}

	print_form_header('usergroup', 'add');

	$options_default = array(
		'edit' => $vbphrase['edit_usergroup'],
		'promote' => $vbphrase['edit_promotions'],
		'list' => $vbphrase['show_all_primary_users'],
		'list2' => $vbphrase['show_all_additional_users'],
		'reputation' => $vbphrase['view_reputation']
	);
	$options_custom = array(
		'edit' => $vbphrase['edit_usergroup'],
		'promote' => $vbphrase['edit_promotions'],
		'kill' => $vbphrase['delete_usergroup'],
		'list' => $vbphrase['show_all_primary_users'],
		'list2' => $vbphrase['show_all_additional_users'],
		'reputation' => $vbphrase['view_reputation']
	);
	$options_public = array(
		'edit' => $vbphrase['edit_usergroup'],
		'promote' => $vbphrase['edit_promotions'],
		'kill' => $vbphrase['delete_usergroup'],
		'list' => $vbphrase['show_all_primary_users'],
		'list2' => $vbphrase['show_all_additional_users'],
		'reputation' => $vbphrase['view_reputation'],
		'leader' => $vbphrase['add_usergroup_leader'],
		'requests' => $vbphrase['view_join_requests']
	);

	print_table_header($vbphrase['default_usergroups'], 5);
	print_cells_row(array($vbphrase['title'], $vbphrase['primary_users'], $vbphrase['additional_users'], $vbphrase['controls']), 1);
	foreach($usergroups['default'] AS $usergroup)
	{
		print_usergroup_row($usergroup, $options_default);
	}
	if (is_array($usergroups['custom']))
	{
		print_table_break();
		print_table_header($vbphrase['custom_usergroups'], 5);
		print_cells_row(array($vbphrase['title'], $vbphrase['primary_users'], $vbphrase['additional_users'], $vbphrase['controls']), 1);
		foreach($usergroups['custom'] AS $usergroup)
		{
			print_usergroup_row($usergroup, $options_custom);
		}
	}
	if (is_array($usergroups['public']))
	{
		print_table_break();
		print_table_header($vbphrase['public_joinable_custom_usergroup'], 9);
		print_cells_row(array($vbphrase['title'], $vbphrase['primary_users'], $vbphrase['additional_users'], $vbphrase['join_requests'], $vbphrase['usergroup_leader'], $vbphrase['controls']), 1);
		foreach($usergroups['public'] AS $usergroup)
		{
			print_usergroup_row($usergroup, $options_public);
		}
		print_description_row('<span class="smallfont">' . $vbphrase['note_groups_marked_with_a_asterisk'] . '</span>', 0, 6);
	}

	print_table_break();
	print_submit_row($vbphrase['add_new_usergroup'], 0);

}

// ###################### Start modify promotions #######################
if ($_REQUEST['do'] == 'modifypromotion')
{
	globalize($_REQUEST, array('usergroupid' => INT));

	$title = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "usergroup WHERE usergroupid = $usergroupid");

	$promotions = array();
	$getpromos = $DB_site->query("
		SELECT userpromotion.*, joinusergroup.title
		FROM " . TABLE_PREFIX . "userpromotion AS userpromotion
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS joinusergroup ON (userpromotion.joinusergroupid = joinusergroup.usergroupid)
		" . iif($usergroupid, "WHERE userpromotion.usergroupid = $usergroupid") . "
	");
	while ($promotion = $DB_site->fetch_array($getpromos))
	{
		$promotions["$promotion[usergroupid]"][] = $promotion;
	}
	unset($promotion);
	$DB_site->free_result($getpromos);

	print_form_header('usergroup', 'updatepromotion');
	if (isset($usergroupcache["$usergroupid"]))
	{
		construct_hidden_code('usergroupid', $usergroupid);
	}

	foreach($promotions AS $groupid => $promos)
	{
		print_table_header("$vbphrase[promotions]: <span style=\"font-weight:normal\">{$usergroupcache[$groupid][title]} " . construct_link_code($vbphrase['add_new_promotion'], "usergroup.php?$session[sessionurl]do=updatepromotion&amp;usergroupid=$groupid") . "</span>", 7);
		print_cells_row(array(
			$vbphrase['usergroup'],
			$vbphrase['promotion_type'],
			$vbphrase['promotion_strategy'],
			$vbphrase['reputation_level'],
			$vbphrase['days_registered'],
			$vbphrase['posts'],
			$vbphrase['controls']
		), 1);

		foreach($promos AS $promotion)
		{
			$promotion['strategy'] = iif(($promotion['strategy'] > 7 AND $promotion['strategy'] < 16) OR $promotion['strategy'] == 24, $promotion['strategy'] - 8, $promotion['strategy']);
			if ($promotion['strategy'] == 16)
			{
				$type = $vbphrase['reputation'];
			}
			else if ($promotion['strategy'] == 17)
			{
				$type = $vbphrase['posts'];
			}
			else if ($promotion['strategy'] == 18)
			{
				$type = $vbphrase['join_date'];
			}
			else
			{
				$type = $vbphrase['promotion_strategy' . ($promotion['strategy'] + 1)];
			}
			print_cells_row(array(
				"<b>$promotion[title]</b>",
				iif($promotion['type']==1, $vbphrase['primary_usergroup'], $vbphrase['additional_usergroups']),
				$type,
				$promotion['reputation'],
				$promotion['date'],
				$promotion['posts'],
				construct_link_code($vbphrase['edit'], "usergroup.php?$session[sessionurl]userpromotionid=$promotion[userpromotionid]&do=updatepromotion") . construct_link_code($vbphrase['delete'], "usergroup.php?$session[sessionurl]userpromotionid=$promotion[userpromotionid]&do=removepromotion"),
			));
		}
	}

	print_submit_row($vbphrase['add_new_promotion'], 0, 7);

}

// ###################### Start edit/insert promotions #######################
if ($_REQUEST['do'] == 'updatepromotion')
{
	globalize($_REQUEST, array(
		'usergroupid' => INT,
		'userpromotionid' => INT
	));

	$usergroups = array();
	foreach($usergroupcache AS $usergroup)
	{
		$usergroups["$usergroup[usergroupid]"] = $usergroup['title'];
	}

	print_form_header('usergroup', 'doupdatepromotion');

	if (!$userpromotionid)
	{
		$promotion = array(
			'reputation' => 1000,
			'date' => 30,
			'posts' => 100,
			'type' => 1,
			'reputationtype' => 0,
			'strategy' => 16
		);

		if ($usergroupid)
		{
			$promotion['usergroupid'] = $usergroupid;
		}

		print_table_header($vbphrase['add_new_promotion']);
		print_select_row($vbphrase['usergroup'], 'promotion[usergroupid]', $usergroups, $promotion['usergroupid']);

	}
	else
	{
		$promotion = $DB_site->query_first("
			SELECT userpromotion.*, usergroup.title
			FROM " . TABLE_PREFIX . "userpromotion AS userpromotion,
			" . TABLE_PREFIX . "usergroup AS usergroup
			WHERE userpromotionid = $userpromotionid AND
				userpromotion.usergroupid = usergroup.usergroupid
		");

		if (($promotion['strategy'] > 7 AND $promotion['strategy'] < 16) OR $promotion['strategy'] == 24)
		{
			$promotion['reputationtype'] = 1;
			$promotion['strategy'] -= 8;
		}
		else
		{
			$promotion['reputationtype'] = 0;
		}
		construct_hidden_code('userpromotionid', $userpromotionid);
		construct_hidden_code('usergroupid', $promotion['usergroupid']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['promotion'], $promotion['title'], $promotion['userpromotionid']));
	}

	$promotionarray = array(
		17=> $vbphrase['posts'],
		18=> $vbphrase['join_date'],
		16=> $vbphrase['reputation'],
		0 => $vbphrase['promotion_strategy1'],
		1 => $vbphrase['promotion_strategy2'],
		2 => $vbphrase['promotion_strategy3'],
		3 => $vbphrase['promotion_strategy4'],
		4 => $vbphrase['promotion_strategy5'],
		5 => $vbphrase['promotion_strategy6'],
		6 => $vbphrase['promotion_strategy7'],
		7 => $vbphrase['promotion_strategy8'],
	);

	print_select_row($vbphrase['reputation_comparison_type'], 'promotion[reputationtype]', array($vbphrase['greater_or_equal_to'], $vbphrase['less_than']), $promotion['reputationtype']);
	print_input_row($vbphrase['reputation_level'], 'promotion[reputation]', $promotion['reputation']);
	print_input_row($vbphrase['days_registered'], 'promotion[date]', $promotion['date']);
	print_input_row($vbphrase['posts'], 'promotion[posts]', $promotion['posts']);
	print_select_row($vbphrase['promotion_strategy'] . " <dfn> $vbphrase[promotion_strategy_description]</dfn>", 'promotion[strategy]', $promotionarray, $promotion['strategy']);
	print_select_row($vbphrase['promotion_type'] . ' <dfn>' . $vbphrase['promotion_type_description_primary_additional'] . '</dfn>', 'promotion[type]', array(1 => $vbphrase['primary_usergroup'], 2 => $vbphrase['additional_usergroups']), $promotion['type']);
	print_chooser_row($vbphrase['move_user_to_usergroup'] . " <dfn>$vbphrase[move_user_to_usergroup_description]</dfn>", 'promotion[joinusergroupid]', 'usergroup', $promotion['joinusergroupid'], '&nbsp;');

	print_submit_row(iif(empty($userpromotionid), $vbphrase['save'], '_default_'));
}

// ###################### Start do edit/insert promotions #######################
if ($_POST['do'] == 'doupdatepromotion')
{

	globalize($_POST, array('promotion', 'userpromotionid', 'usergroupid'));

	if ($promotion['joinusergroupid'] == -1)
	{
		print_stop_message('invalid_usergroup_specified');
	}

	if ($promotion['reputationtype'] AND $promotion['strategy'] <= 16)
	{
		$promotion['strategy'] += 8;
	}
	unset($promotion['reputationtype']);

	if (!empty($userpromotionid))
	{ // update
		if ($usergroupid == $promotion['joinusergroupid'])
		{
			print_stop_message('promotion_join_same_group');
		}
		$DB_site->query(fetch_query_sql($promotion, 'userpromotion', "WHERE userpromotionid=$userpromotionid"));
	}
	else
	{ // insert
		$usergroupid = $promotion['usergroupid'];
		if ($usergroupid == $promotion['joinusergroupid'])
		{
			print_stop_message('promotion_join_same_group');
		}
		$DB_site->query(fetch_query_sql($promotion, 'userpromotion'));
	}

	// $title = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "usergroup WHERE usergroupid = $usergroupid");
	// $message = str_replace('{title}', $title['title'], $message);

	define('CP_REDIRECT', "usergroup.php?do=modifypromotion&usergroupid=$usergroupid");
	print_stop_message('saved_promotion_successfully');
}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'removepromotion')
{

	print_delete_confirmation('userpromotion', $_REQUEST['userpromotionid'], 'usergroup', 'killpromotion', 'promotion_usergroup', 0, '');

}

// ###################### Start Kill #######################
if ($_POST['do'] == 'killpromotion')
{

	$userpromotionid = $_POST['userpromotionid'];

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "userpromotion WHERE userpromotionid = $userpromotionid");

	define('CP_REDIRECT', 'usergroup.php?do=modifypromotion');
	print_stop_message('deleted_promotion_successfully');
}

// #############################################################################
// process usergroup join requests
if ($_POST['do'] == 'processjoinrequests')
{
	globalize($_POST, array('usergroupid' => INT, 'request'));

	// check we have some results to process
	if (!is_array($request) OR empty($request))
	{
		print_stop_message('no_matches_found');
	}

	// check that we are working with a valid usergroup
	if (!is_array($usergroupcache["$usergroupid"]))
	{
		print_stop_message('invalid_usergroup_specified');
	}
	else
	{
		$usergroupname = htmlspecialchars_uni($usergroupcache["$usergroupid"]['title']);
	}

	$auth = array();

	// sort the requests according to the action specified
	foreach($request AS $requestid => $action)
	{
		$action = intval($action);
		switch($action)
		{
			case -1:	// this request will be ignored
				unset($request["$requestid"]);
				break;

			case  1:	// this request will be authorized
				$auth[] = $requestid;
				break;

			case  0:	// this request will be denied
				// do nothing - this request will be zapped at the end of this segment
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
			WHERE usergrouprequestid IN (" . implode(', ', $auth) . ")
			ORDER BY user.username
		");
		$auth = array();
		echo "<p><b>" . $vbphrase['processing_join_requests'] . "</b></p><ul>\n";
		while ($user = $DB_site->fetch_array($users))
		{
			if (in_array($usergroupid, fetch_membergroupids_array($user)))
			{
				echo "\t<li>" . construct_phrase($vbphrase['x_is_already_a_member_of_the_usergroup_y'], "<b>$user[username]</b>", "<i>$usergroupname</i>") . "</li>\n";
			}
			else
			{
				echo "\t<li>" . construct_phrase($vbphrase['making_x_a_member_of_the_usergroup_y'], "<b>$user[username]</b>", "<i>$usergroupname</i>") . "</li>\n";
				$auth[] = $user['userid'];
			}
		}
		echo "</ul><p><b>$vbphrase[done]</b></p>\n";

		// check that we STILL have some valid requests
		if (!empty($auth))
		{
			$updateQuery = "
				UPDATE " . TABLE_PREFIX . "user SET
				membergroupids = IF(membergroupids = '', $usergroupid, CONCAT(membergroupids, ',$usergroupid'))
				WHERE userid IN (" . implode(', ', $auth) . ")
			";
			$DB_site->query($updateQuery);
		}
	}

	// delete processed join requests
	if (!empty($request))
	{
		$deleteQuery = "DELETE FROM " . TABLE_PREFIX . "usergrouprequest WHERE usergrouprequestid IN (" . implode(', ', array_keys($request)) . ")";
		$DB_site->query($deleteQuery);
	}

	// and finally jump back to the join requests screen
	$_REQUEST['do'] = 'viewjoinrequests';
}

// #############################################################################
// show usergroup join requests
if ($_REQUEST['do'] == 'viewjoinrequests')
{
	globalize($_REQUEST, array('usergroupid' => INT));

	// first query groups that have join requests
	$getusergroups = $DB_site->query("
		SELECT req.usergroupid, COUNT(req.usergrouprequestid) AS requests,
		IF(usergroup.usergroupid IS NULL, 0, 1) AS validgroup
		FROM " . TABLE_PREFIX . "usergrouprequest AS req
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = req.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = req.userid)
		WHERE user.userid IS NOT NULL
		GROUP BY req.usergroupid
	");
	if ($DB_site->num_rows($getusergroups) == 0)
	{
		// there are no join requests
		print_stop_message('nothing_to_do');
	}

	// if we got this far we know that we have at least one group with some requests in it
	$usergroups = array();
	$badgroups = array();

	while($getusergroup = $DB_site->fetch_array($getusergroups))
	{
		$ugid = &$getusergroup['usergroupid'];

		if (isset($usergroupcache["$ugid"]))
		{
			$usergroupcache["$ugid"]['joinrequests'] = $getusergroup['requests'];
		}
		else
		{
			$badgroups[] = $getusergroup['usergroupid'];
		}
	}
	unset($getusergroup);
	$DB_site->free_result($getusergroups);

	// if there are any invalid requests, zap them now
	if (!empty($badgroups))
	{
		$badgroups = implode(', ', $badgroups);
		DEVDEBUG("Deleting requests from the following invalid usergroups: $badgroups");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "usergrouprequest WHERE usergroupid IN ($badgroups)");
	}

	// create array to hold options for the menu
	$groupsmenu = array();

	foreach ($usergroupcache AS $id => $usergroup)
	{
		if ($usergroup['ispublicgroup'])
		{
			$groupsmenu["$id"] = htmlspecialchars_uni($usergroup['title']) . " ($vbphrase[join_requests]: " . vb_number_format($usergroup['joinrequests']) . ")";
		}
	}

	print_form_header('usergroup', 'viewjoinrequests', 0, 1, 'chooser');
	print_label_row(
		$vbphrase['usergroup'],
		'<select name="usergroupid" onchange="this.form.submit();" class="bginput">' . construct_select_options($groupsmenu, $usergroupid)  . '</select><input type="submit" class="button" value="' . $vbphrase['go'] . '" />',
		'thead'
	);
	print_table_footer();
	unset($groupsmenu);

	// now if we are being asked to display a particular usergroup, do so.
	if ($usergroupid)
	{
		// check this is a valid usergroup
		if (!is_array($usergroupcache["$usergroupid"]))
		{
			print_stop_message('invalid_usergroup_specified');
		}

		// check that this usergroup has some join requests
		if ($usergroupcache["$usergroupid"]['joinrequests'])
		{

			// everything seems okay, so make a total record for this usergroup
			$usergroup = &$usergroupcache["$usergroupid"];

			// query the usergroup leaders of this usergroup
			$leaders = array();
			$getleaders = $DB_site->query("
				SELECT usergroupleader.userid, user.username
				FROM " . TABLE_PREFIX . "usergroupleader AS usergroupleader
				INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
				WHERE usergroupleader.usergroupid = $usergroupid
			");
			while ($getleader = $DB_site->fetch_array($getleaders))
			{
				$leaders[] = "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$getleader[userid]\">$getleader[username]</a>";
			}
			unset($getleader);
			$DB_site->free_result($getleaders);

			// query the requests for this usergroup
			$requests = $DB_site->query("
				SELECT req.*, user.username
				FROM " . TABLE_PREFIX . "usergrouprequest AS req
				INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
				WHERE req.usergroupid = $usergroupid
				ORDER BY user.username
			");

			print_form_header('usergroup', 'processjoinrequests');
			construct_hidden_code('usergroupid', $usergroupid);
			print_table_header("$usergroup[title] - ($vbphrase[join_requests]: $usergroup[joinrequests])", 6);
			if (!empty($leaders))
			{
				print_description_row("<span style=\"font-weight:normal\">(" . $vbphrase['usergroup_leader'] . ': ' . implode(', ', $leaders) . ')</span>', 0, 6, 'thead');
			}
			print_cells_row(array
			(
				$vbphrase['username'],
				$vbphrase['reason'],
				'<span style="white-space:nowrap">' . $vbphrase['date'] . '</span>',
				'<input type="button" value="' . $vbphrase['accept'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['check_all'] . '" />',
				'<input type="button" value=" ' . $vbphrase['deny'] . ' " onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['check_all'] . '" />',
				'<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['check_all'] . '" />'
			), 1);

			$i = 0;

			while ($request = $DB_site->fetch_array($requests))
			{
				if ($i > 0 AND $i % 10 == 0)
				{
					print_description_row('<div align="center"><input type="submit" class="button" value="' . $vbphrase['process'] . '" accesskey="s" tabindex="1" /></div>', 0, 6, 'thead');
				}
				$i++;
				$cell = array
				(
					"<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$request[userid]\"><b>$request[username]</b></a>",
					$request['reason'],
					'<span class="smallfont">' . vbdate($vboptions['dateformat'], $request['dateline']) . '<br />' . vbdate($vboptions['timeformat'], $request['dateline']) . '</span>',
					'<label for="a' . $request['usergrouprequestid'] . '" class="smallfont">' . $vbphrase['accept'] . '<input type="radio" name="request[' . $request['usergrouprequestid'] . ']" value="1" id="a' . $request['usergrouprequestid'] . '" tabindex="1" /></label>',
					'<label for="d' . $request['usergrouprequestid'] . '" class="smallfont">' . $vbphrase['deny'] . '<input type="radio" name="request[' . $request['usergrouprequestid'] . ']" value="0" id="d' . $request['usergrouprequestid'] . '" tabindex="1" /></label>',
					'<label for="i' . $request['usergrouprequestid'] . '" class="smallfont">' . $vbphrase['ignore'] . '<input type="radio" name="request[' . $request['usergrouprequestid'] . ']" value="-1" id="i' . $request['usergrouprequestid'] . '" tabindex="1" checked="checked" /></label>'
				);
				print_cells_row($cell, 0, '', -5);
			}
			unset($request);
			$DB_site->free_result($requests);

			print_submit_row($vbphrase['process'], $vbphrase['reset'], 6);

		}
		else
		{
			print_stop_message('no_join_requests_matched_your_query');
		}

	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 10869 $
|| ####################################################################
\*======================================================================*/
?>