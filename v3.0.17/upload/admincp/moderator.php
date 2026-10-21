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
define('CVS_REVISION', '$RCSfile$ - $Revision: 11370 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cppermission', 'forum', 'moderator');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');


// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminforums'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['moderatorid'] != 0, " moderator id = " . $_REQUEST['moderatorid'], iif($_REQUEST['forumid'] != 0, "forum id = " . $_REQUEST['forumid'], iif($_REQUEST['userid'] != 0, "user id = " . $_REQUEST['userid'], iif(!empty($_REQUEST['modusername']), "mod username = " . $_REQUEST['modusername'])))));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['moderator_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add / edit moderator #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'moderatorid' => INT,
		'forumid' => INT
	));

	if (empty($moderatorid))
	{
		// add moderator - set default values
		$foruminfo = $DB_site->query_first("
			SELECT forumid,title AS forumtitle
			FROM " . TABLE_PREFIX . "forum
			WHERE forumid = $forumid
		");
		$moderator = array(
			'caneditposts' => 1,
			'candeleteposts' => 1,
			'canopenclose' => 1,
			'caneditthreads' => 1,
			'canmanagethreads' => 1,
			'canannounce' => 1,
			'canmoderateposts' => 1,
			'canmoderateattachments' => 1,
			'canviewips' => 1,
			'forumid' => $foruminfo['forumid'],
			'forumtitle' => $foruminfo['forumtitle']
		);
		print_form_header('moderator', 'update');
		print_table_header(construct_phrase($vbphrase['add_new_moderator_to_forum_x'], $foruminfo['forumtitle']));
	}
	else
	{
		// edit moderator - query moderator
		$moderator = $DB_site->query_first("
			SELECT moderator.moderatorid,moderator.userid,
			moderator.forumid,moderator.permissions,user.username,forum.title AS forumtitle
			FROM " . TABLE_PREFIX . "moderator AS moderator
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = moderator.forumid)
			WHERE moderatorid = $moderatorid
		");
		$perms = convert_bits_to_array($moderator['permissions'], $_BITFIELD['moderatorpermissions'], 1);
		$moderator = array_merge($perms, $moderator);

		// delete link
		print_form_header('moderator', 'remove');
		construct_hidden_code('moderatorid', $moderatorid);
		print_table_header($vbphrase['if_you_would_like_to_remove_this_moderator'] . ' &nbsp; &nbsp; <input type="submit" class="button" value="' . $vbphrase['delete'] . '" tabindex="1" />');
		print_table_footer();

		print_form_header('moderator', 'update');
		construct_hidden_code('moderatorid', $moderatorid);
		print_table_header(construct_phrase($vbphrase['edit_moderator_x_for_forum_y'], $moderator['username'], $moderator['forumtitle']));
	}

	print_forum_chooser('moderator[forumid]', $moderator['forumid'], '', $vbphrase['forum_and_children'], 0);
	print_input_row($vbphrase['moderator_username'], 'modusername', $moderator['username'], 0);

	// usergroup membership options
	if ($_REQUEST['do'] == 'add' AND can_administer('canadminusers'))
	{
		$usergroups = array(0 => $vbphrase['do_not_change_usergroup']);
		foreach ($usergroupcache AS $usergroupid => $usergroup)
		{
			$usergroups["$usergroupid"] = $usergroup['title'];
		}
		print_table_header($vbphrase['usergroup_options']);
		print_select_row($vbphrase['change_moderator_primary_usergroup_to'], 'usergroupid', $usergroups, 0);
		print_membergroup_row($vbphrase['make_moderator_a_member_of'], 'membergroupids', 2);
	}

	// post permissions
	print_table_header($vbphrase['post_thread_permissions']);
	print_yes_no_row($vbphrase['can_edit_posts'], 'moderator[caneditposts]', $moderator['caneditposts']);
	print_yes_no_row($vbphrase['can_delete_posts'], 'moderator[candeleteposts]', $moderator['candeleteposts']);
	print_yes_no_row($vbphrase['can_physically_delete_posts'], 'moderator[canremoveposts]', $moderator['canremoveposts']);
	// thread permissions
	print_yes_no_row($vbphrase['can_open_close_threads'], 'moderator[canopenclose]', $moderator['canopenclose']);
	print_yes_no_row($vbphrase['can_edit_threads'], 'moderator[caneditthreads]', $moderator['caneditthreads']);
	print_yes_no_row($vbphrase['can_manage_threads'], 'moderator[canmanagethreads]', $moderator['canmanagethreads']);
	print_yes_no_row($vbphrase['can_edit_polls'], 'moderator[caneditpoll]', $moderator['caneditpoll']);
	// moderation permissions
	print_table_header($vbphrase['forum_permissions']);
	print_yes_no_row($vbphrase['can_post_announcements'], 'moderator[canannounce]', $moderator['canannounce']);
	print_yes_no_row($vbphrase['can_moderate_posts'], 'moderator[canmoderateposts]', $moderator['canmoderateposts']);
	print_yes_no_row($vbphrase['can_moderate_attachments'], 'moderator[canmoderateattachments]', $moderator['canmoderateattachments']);
	print_yes_no_row($vbphrase['can_mass_move_threads'], 'moderator[canmassmove]', $moderator['canmassmove']);
	print_yes_no_row($vbphrase['can_mass_prune_threads'], 'moderator[canmassprune]', $moderator['canmassprune']);
	print_yes_no_row($vbphrase['can_set_forum_password'], 'moderator[cansetpassword]', $moderator['cansetpassword']);
	// user permissions
	print_table_header($vbphrase['user_permissions']);
	print_yes_no_row($vbphrase['can_view_ip_addresses'], 'moderator[canviewips]', $moderator['canviewips']);
	print_yes_no_row($vbphrase['can_view_whole_profile'], 'moderator[canviewprofile]', $moderator['canviewprofile']);
	print_yes_no_row($vbphrase['can_ban_users'], 'moderator[canbanusers]', $moderator['canbanusers']);
	print_yes_no_row($vbphrase['can_restore_banned_users'], 'moderator[canunbanusers]', $moderator['canunbanusers']);
	print_yes_no_row($vbphrase['can_edit_user_signatures'], 'moderator[caneditsigs]', $moderator['caneditsigs']);
	print_yes_no_row($vbphrase['can_edit_user_avatars'], 'moderator[caneditavatar]', $moderator['caneditavatar']);
	print_yes_no_row($vbphrase['can_edit_user_profile_pictures'], 'moderator[caneditprofilepic]', $moderator['caneditprofilepic']);
	print_yes_no_row($vbphrase['can_edit_user_reputation_comments'], 'moderator[caneditreputation]', $moderator['caneditreputation']);
	// new thread/new post email preferences
	print_table_header($vbphrase['email_preferences']);
	print_yes_no_row($vbphrase['receive_email_on_new_thread'], 'moderator[newthreademail]', $moderator['newthreademail']);
	print_yes_no_row($vbphrase['receive_email_on_new_post'], 'moderator[newpostemail]', $moderator['newpostemail']);

	print_submit_row($vbphrase['save']);

}

// ###################### Start insert / update moderator #######################
if ($_POST['do'] == 'update')
{
	globalize($_POST, array(
		'moderator',
		'moderatorid' => INT,
		'modusername' => STR_NOHTML,
		'usergroupid' => INT,
		'membergroupids'
	));

	if ($modusername == '')
	{
		print_stop_message('invalid_user_specified');
	}

	$userinfo = $DB_site->query_first("
		SELECT userid, username, usergroupid, membergroupids
		FROM " . TABLE_PREFIX . "user
		WHERE username = '" . addslashes($modusername) . "'
	");
	$foruminfo = $DB_site->query_first("
		SELECT forumid,title
		FROM " . TABLE_PREFIX . "forum
		WHERE forumid = $moderator[forumid]
	");

	if ($userinfo['userid'] AND $foruminfo['forumid'])
	{ // no errors

		require_once('./includes/functions_misc.php');

		$moderator['userid'] = $userinfo['userid'];
		$moderator['permissions'] = convert_array_to_bits($moderator, $_BITFIELD['moderatorpermissions'],1);
		if (!empty($moderatorid))
		{
			// update
			$DB_site->query(fetch_query_sql($moderator, 'moderator', "WHERE moderatorid = $moderatorid"));
		}
		else
		{
			// insert
			$newusergroupid = $userinfo['usergroupid'];
			$newmembergroupids = $userinfo['membergroupids'];

			$noalter = explode(',', $undeletableusers);
			if (!in_array($userinfo['userid'], $noalter) AND can_administer('canadminusers'))
			{
				if ($usergroupid > 0)
				{
					$newusergroupid	= $usergroupid;
				}
				if (is_array($membergroupids) AND !empty($membergroupids))
				{
					if ($userinfo['membergroupids'] !== '')
					{
						$membergroupids = array_unique(array_merge($membergroupids, fetch_membergroupids_array($userinfo, false)));
					}
					if ($primarykey = array_search($newusergroupid, $membergroupids))
					{
						unset($membergroupids["$primarykey"]);
					}
					$newmembergroupids = implode(',', $membergroupids);
				}

				if ($newusergroupid != $userinfo['usergroupid'] OR $newmembergroupids != $userinfo['membergroupids'])
				{
					$DB_site->query("
						UPDATE " . TABLE_PREFIX . "user SET
							usergroupid = $newusergroupid,                             # used to be $userinfo[usergroupid]
							displaygroupid = $newusergroupid,
							membergroupids = '" . addslashes($newmembergroupids) . "'  # used to be $userinfo[membergroupids]
						WHERE userid = $userinfo[userid]
					");
				}
			}

			$DB_site->query(fetch_query_sql($moderator, 'moderator'));
		}

		define('CP_REDIRECT', "forum.php?do=modify#forum$moderator[forumid]");
		print_stop_message('saved_moderator_x_successfully', $modusername);

	}
	else
	{
		// error
		if (!$userinfo['userid'])
		{
			print_stop_message('no_users_matched_your_query');
		}
		if (!$foruminfo['forumid'])
		{
			print_stop_message('invalid_forum_specified');
		}
	}

}

// ###################### Start Remove moderator #######################

if ($_REQUEST['do'] == 'remove')
{
	globalize($_REQUEST, array(
		'moderatorid' => INT,
		'redir' => STR
	));

	$hidden = array('redir' => $redir);

	print_delete_confirmation('moderator', $moderatorid, 'moderator', 'kill', 'moderator', $hidden);
}

// ###################### Start Kill moderator #######################

if ($_POST['do'] == 'kill')
{
	globalize($_POST, array(
		'moderatorid' => INT,
		'redir' => STR
	));

	$getuserid = $DB_site->query_first("
		SELECT user.userid, usergroupid, username, displaygroupid
		FROM " . TABLE_PREFIX . "moderator AS moderator
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE moderatorid = $moderatorid
	");
	if (!$getuserid)
	{
		print_stop_message('user_no_longer_moderator');
	}
	else
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderator WHERE moderatorid = $moderatorid");
		// if the user is in the moderators usergroup and they are not modding more forums, then move them to registered users usergroup
		if ($getuserid['usergroupid'] == 7 AND !$moreforums=$DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "moderator WHERE userid=$getuserid[userid]"))
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET usergroupid = 2" . iif($getuserid['displaygroupid'] == 7, ', displaygroupid = 2') . " WHERE userid = $getuserid[userid]");
		}

		if ($redir == 'modlist')
		{
			define('CP_REDIRECT', 'moderator.php?do=showlist');
		}
		else
		{
			define('CP_REDIRECT', 'forum.php');
		}
		print_stop_message('deleted_moderator_successfully');
	}
}

// ###################### Start Show moderator list #######################

if ($_REQUEST['do'] == "showlist")
{
	print_form_header('', '');
	print_table_header($vbphrase['last_online'] . ' - ' . $vbphrase['color_key']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li class="modtoday">' . $vbphrase['today'] . '</li>
		<li class="modyesterday">' . $vbphrase['yesterday'] . '</li>
		<li class="modlasttendays">' . construct_phrase($vbphrase['within_the_last_x_days'], '10') . '</li>
		<li class="modsincetendays">' . construct_phrase($vbphrase['more_than_x_days_ago'], '10') . '</li>
		<li class="modsincethirtydays"> ' . construct_phrase($vbphrase['more_than_x_days_ago'], '30') . '</li>
		</ul></div>
	');
	print_table_footer();

	print_form_header('', '');
	print_table_header($vbphrase['moderators']);
	echo "<tr valign=\"top\">\n\t<td class=\"" . fetch_row_bgclass() . "\" colspan=\"2\">";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: $stylevar[left]\">";

	// get the timestamp for the beginning of today, according to bbuserinfo's timezone
	require_once('./includes/functions_misc.php');
	$unixtoday = vbmktime(0, 0, 0, vbdate('m', TIMENOW, false, false), vbdate('d', TIMENOW, false, false), vbdate('Y', TIMENOW, false, false));

	$moderators = $DB_site->query("
		SELECT moderator.moderatorid, user.userid, user.username, user.lastactivity, forum.forumid, forum.title
		FROM " . TABLE_PREFIX . "forum AS forum
		INNER JOIN " . TABLE_PREFIX . "moderator AS moderator ON (moderator.forumid = forum.forumid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
		ORDER BY user.username, forum.title
	");
	$curmod = -1;
	while ($moderator = $DB_site->fetch_array($moderators))
	{
		if ($curmod != $moderator['userid'])
		{
			$curmod = $moderator['userid'];
			if ($countmods++!=0)
			{
				echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
			}

			if ($moderator['lastactivity'] >= $unixtoday)
			{
				$onlinecolor = 'modtoday';
			}
			else if ($moderator['lastactivity'] >= ($unixtoday - 86400))
			{
				$onlinecolor = 'modyesterday';
			}
			else if ($moderator['lastactivity'] >= ($unixtoday - 864000))
			{
				$onlinecolor = 'modlasttendays';
			}
			else if ($moderator['lastactivity'] >= ($unixtoday - 2592000))
			{
				$onlinecolor = 'modsincetendays';
			}
			else
			{
				$onlinecolor = 'modsincethirtydays';
			}
			$lastonline = vbdate($vboptions['dateformat'] . ' ' .$vboptions['timeformat'], $moderator['lastactivity']);
			echo "\n\t<ul>\n\t<li><b><a href=\"user.php?s=$session[sessionhash]&do=edit&userid=$moderator[userid]\">$moderator[username]</a></b><span class=\"smallfont\"> - " . $vbphrase['last_online'] . " <span class=\"$onlinecolor\">" . $lastonline . "</span></span>\n";
			echo "\n\t\t<ul>$vbphrase[forums] <span class=\"smallfont\">(" . construct_link_code($vbphrase['remove_moderator_from_all_forums'], "moderator.php?$session[sessionurl]do=removeall&amp;userid=$moderator[userid]") . ")</span>\n\t<ul>\n";
		}
		echo "\t\t\t<li><a href=\"../forumdisplay.php?s=$session[sessionhash]&forumid=$moderator[forumid]\" target=\"_blank\">$moderator[title]</a>\n".
			"\t\t\t\t<span class=\"smallfont\">(" . construct_link_code($vbphrase['edit'], "moderator.php?s=$session[sessionhash]&do=edit&moderatorid=$moderator[moderatorid]").
			construct_link_code($vbphrase['delete'], "moderator.php?s=$session[sessionhash]&do=remove&moderatorid=$moderator[moderatorid]&redir=modlist") . ")</span>\n".
			"\t\t\t</li><br />\n";
	}
	echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
	echo "</div>\n";
	echo "</td>\n</tr>\n";

	print_table_footer(1, $vbphrase['moderators'] . ": <b>$countmods</b>");


}

// ###################### Start Remove moderator from all forums #######################

if ($_REQUEST['do'] == 'removeall')
{
	globalize($_REQUEST, array(
		'userid' => INT
	));

	$modinfo = $DB_site->query_first("
		SELECT username FROM " . TABLE_PREFIX . "moderator AS moderator
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		WHERE moderator.userid = $userid
	");
	if (!$modinfo)
	{
		print_stop_message('user_no_longer_moderator');
	}

	print_form_header('moderator', 'killall', 0, 1, '', '75%');
	construct_hidden_code('userid', $userid);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row('<blockquote><br />' . $vbphrase['are_you_sure_you_want_to_delete_this_moderator'] . "<br /></blockquote>\n\t");
	print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
}

// ###################### Start Kill moderator from all forums #######################

if ($_POST['do'] == 'killall')
{
	globalize($_POST, array(
		'userid' => INT
	));

	if (empty($userid))
	{
		print_stop_message('invalid_users_specified');
	}

	$getuserid = $DB_site->query_first("
		SELECT user.userid, usergroupid, username, displaygroupid
		FROM " . TABLE_PREFIX . "moderator AS moderator
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		WHERE moderator.userid = $userid
	");
	if (!$getuserid)
	{
		print_stop_message('user_no_longer_moderator');
	}
	else
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderator WHERE userid = $userid");
		// if the user is in the moderators usergroup, then move them to registered users usergroup
		if ($getuserid['usergroupid'] == 7)
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET usergroupid = 2" . iif($getuserid['displaygroupid'] == 7, ', displaygroupid = 2') . " WHERE userid = $userid");
		}

		define('CP_REDIRECT', "moderator.php?do=showlist");
		print_stop_message('deleted_moderators_successfully');
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11370 $
|| ####################################################################
\*======================================================================*/
?>