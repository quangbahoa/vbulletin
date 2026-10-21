<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
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
define('CVS_REVISION', '$RCSfile$ - $Revision: 16924 $');

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

$vbulletin->input->clean_array_gpc('r', array(
	'forumid'     => TYPE_INT,
	'moderatorid' => TYPE_UINT,
	'userid'      => TYPE_UINT,
	'modusername' => TYPE_STR,
	'redir'       => TYPE_NOHTML,
));

// ############################# LOG ACTION ###############################
log_admin_action(iif($vbulletin->GPC['moderatorid'] != 0, " moderator id = " . $vbulletin->GPC['moderatorid'], iif($vbulletin->GPC['forumid'] != 0, "forum id = " . $vbulletin->GPC['forumid'], iif($vbulletin->GPC['userid'] != 0, "user id = " . $vbulletin->GPC['userid'], iif(!empty($vbulletin->GPC['modusername']), "mod username = " . $vbulletin->GPC['modusername'])))));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['moderator_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add / edit moderator #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'editglobal')
{
	if ($_REQUEST['do'] == 'editglobal')
	{
		$moderator = $db->query_first("
			SELECT user.username, user.userid,
			moderator.forumid, moderator.permissions, moderator.moderatorid
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON (moderator.userid = user.userid AND moderator.forumid = -1)
			WHERE user.userid = " . $vbulletin->GPC['userid']
		);

		print_form_header('moderator', 'update');
		construct_hidden_code('forumid', '-1');
		construct_hidden_code('modusername', $moderator['username'], false);
		$username = $moderator['username'];

		if (empty($moderator['moderatorid']))
		{
			// This $moderator array gets overwritten below
			$moderator = array(
				'caneditposts'           => 1,
				'candeleteposts'         => 1,
				'canopenclose'           => 1,
				'caneditthreads'         => 1,
				'canmanagethreads'       => 1,
				'canannounce'            => 1,
				'canmoderateposts'       => 1,
				'canmoderateattachments' => 1,
				'canviewips'             => 1,
			);

			// this user doesn't have a record for super mod permissions, which is equivalent to having them all
			$globalperms = array_sum($vbulletin->bf_misc_moderatorpermissions) - ($vbulletin->bf_misc_moderatorpermissions['newthreademail'] + $vbulletin->bf_misc_moderatorpermissions['newpostemail']);
			$moderator = convert_bits_to_array($globalperms, $vbulletin->bf_misc_moderatorpermissions, 1);
			$moderator['username'] = $username;
		}
		else
		{
			construct_hidden_code('moderatorid', $moderator['moderatorid']);
			$perms = convert_bits_to_array($moderator['permissions'], $vbulletin->bf_misc_moderatorpermissions, 1);
			$moderator = array_merge($perms, $moderator);
		}

		print_table_header($vbphrase['super_moderator_permissions'] . ' - <span class="normal">' . $moderator['username'] . '</span>');
	}
	else if (empty($vbulletin->GPC['moderatorid']))
	{
		// add moderator - set default values
		$foruminfo = $db->query_first("
			SELECT forumid,title AS forumtitle
			FROM " . TABLE_PREFIX . "forum
			WHERE forumid = " . $vbulletin->GPC['forumid'] . "
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
		$moderator = $db->query_first("
			SELECT moderator.moderatorid,moderator.userid,
			moderator.forumid,moderator.permissions,user.username,forum.title AS forumtitle
			FROM " . TABLE_PREFIX . "moderator AS moderator
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = moderator.forumid)
			WHERE moderatorid = " . $vbulletin->GPC['moderatorid'] . "
		");
		$perms = convert_bits_to_array($moderator['permissions'], $vbulletin->bf_misc_moderatorpermissions, 1);
		$moderator = array_merge($perms, $moderator);

		// delete link
		print_form_header('moderator', 'remove');
		construct_hidden_code('moderatorid', $vbulletin->GPC['moderatorid']);
		print_table_header($vbphrase['if_you_would_like_to_remove_this_moderator'] . ' &nbsp; &nbsp; <input type="submit" class="button" value="' . $vbphrase['remove'] . '" tabindex="1" />');
		print_table_footer();

		print_form_header('moderator', 'update');
		construct_hidden_code('moderatorid', $vbulletin->GPC['moderatorid']);
		print_table_header(construct_phrase($vbphrase['edit_moderator_x_for_forum_y'], $moderator['username'], $moderator['forumtitle']));
	}

	if ($_REQUEST['do'] != 'editglobal')
	{
		print_forum_chooser($vbphrase['forum_and_children'], 'forumid', $moderator['forumid']);
		print_input_row($vbphrase['moderator_username'], 'modusername', $moderator['username'], 0);
		construct_hidden_code('redir', $vbulletin->GPC['redir']);
	}

	// usergroup membership options
	if ($_REQUEST['do'] == 'add' AND can_administer('canadminusers'))
	{
		$usergroups = array(0 => $vbphrase['do_not_change_usergroup']);
		foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
		{
			$usergroups["$usergroupid"] = $usergroup['title'];
		}
		print_table_header($vbphrase['usergroup_options']);
		print_select_row($vbphrase['change_moderator_primary_usergroup_to'], 'usergroupid', $usergroups, 0);
		print_membergroup_row($vbphrase['make_moderator_a_member_of'], 'membergroupids', 2);
	}

	// post permissions
	print_description_row($vbphrase['post_thread_permissions'], false, 2, 'thead');
	print_yes_no_row($vbphrase['can_edit_posts'], 'modperms[caneditposts]', $moderator['caneditposts']);
	print_yes_no_row($vbphrase['can_delete_posts'], 'modperms[candeleteposts]', $moderator['candeleteposts']);
	print_yes_no_row($vbphrase['can_physically_delete_posts'], 'modperms[canremoveposts]', $moderator['canremoveposts']);
	// thread permissions
	print_yes_no_row($vbphrase['can_open_close_threads'], 'modperms[canopenclose]', $moderator['canopenclose']);
	print_yes_no_row($vbphrase['can_edit_threads'], 'modperms[caneditthreads]', $moderator['caneditthreads']);
	print_yes_no_row($vbphrase['can_manage_threads'], 'modperms[canmanagethreads]', $moderator['canmanagethreads']);
	print_yes_no_row($vbphrase['can_edit_polls'], 'modperms[caneditpoll]', $moderator['caneditpoll']);
	// moderation permissions
	print_description_row($vbphrase['forum_permissions'], false, 2, 'thead');
	print_yes_no_row($vbphrase['can_post_announcements'], 'modperms[canannounce]', $moderator['canannounce']);
	print_yes_no_row($vbphrase['can_moderate_posts'], 'modperms[canmoderateposts]', $moderator['canmoderateposts']);
	print_yes_no_row($vbphrase['can_moderate_attachments'], 'modperms[canmoderateattachments]', $moderator['canmoderateattachments']);
	print_yes_no_row($vbphrase['can_mass_move_threads'], 'modperms[canmassmove]', $moderator['canmassmove']);
	print_yes_no_row($vbphrase['can_mass_prune_threads'], 'modperms[canmassprune]', $moderator['canmassprune']);
	print_yes_no_row($vbphrase['can_set_forum_password'], 'modperms[cansetpassword]', $moderator['cansetpassword']);
	// user permissions
	print_description_row($vbphrase['user_permissions'], false, 2, 'thead');
	print_yes_no_row($vbphrase['can_view_ip_addresses'], 'modperms[canviewips]', $moderator['canviewips']);
	print_yes_no_row($vbphrase['can_view_whole_profile'], 'modperms[canviewprofile]', $moderator['canviewprofile']);
	print_yes_no_row($vbphrase['can_ban_users'], 'modperms[canbanusers]', $moderator['canbanusers']);
	print_yes_no_row($vbphrase['can_restore_banned_users'], 'modperms[canunbanusers]', $moderator['canunbanusers']);
	print_yes_no_row($vbphrase['can_edit_user_signatures'], 'modperms[caneditsigs]', $moderator['caneditsigs']);
	print_yes_no_row($vbphrase['can_edit_user_avatars'], 'modperms[caneditavatar]', $moderator['caneditavatar']);
	print_yes_no_row($vbphrase['can_edit_user_profile_pictures'], 'modperms[caneditprofilepic]', $moderator['caneditprofilepic']);
	print_yes_no_row($vbphrase['can_edit_user_reputation_comments'], 'modperms[caneditreputation]', $moderator['caneditreputation']);
	// new thread/new post email preferences
	print_description_row($vbphrase['email_preferences'], false, 2, 'thead');
	print_yes_no_row($vbphrase['receive_email_on_new_thread'], 'modperms[newthreademail]', $moderator['newthreademail']);
	print_yes_no_row($vbphrase['receive_email_on_new_post'], 'modperms[newpostemail]', $moderator['newpostemail']);

	($hook = vBulletinHook::fetch_hook('admin_moderator_form')) ? eval($hook) : false;

	print_submit_row($vbphrase['save']);

}

// ###################### Start insert / update moderator #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'modperms'       => TYPE_ARRAY_BOOL,
		'usergroupid'    => TYPE_UINT,
		'membergroupids' => TYPE_ARRAY_UINT
	));

	$moddata =& datamanager_init('Moderator', $vbulletin, ERRTYPE_CP);
	if ($vbulletin->GPC['moderatorid'])
	{
		$moddata->set_existing($db->query_first("
			SELECT moderator.*,
			user.username, user.usergroupid, user.membergroupids
			FROM " . TABLE_PREFIX . "moderator AS moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
			WHERE moderator.moderatorid = " . $vbulletin->GPC['moderatorid']
		));
	}
	else
	{
		$moddata->set_info('usergroupid', $vbulletin->GPC['usergroupid']);
		$moddata->set_info('membergroupids', $vbulletin->GPC['membergroupids']);
	}
	$moddata->set('username', htmlspecialchars_uni($vbulletin->GPC['modusername']));
	$moddata->set('forumid', $vbulletin->GPC['forumid']);
	foreach ($vbulletin->GPC['modperms'] AS $key => $val)
	{
		$moddata->set_bitfield('permissions', $key, $val);
	}

	($hook = vBulletinHook::fetch_hook('admin_moderator_save')) ? eval($hook) : false;

	$moddata->save();

	if ($vbulletin->GPC['forumid'] == -1 OR !empty($vbulletin->GPC['redir']))
	{
		// use showlist for both but probably need to handle showmods
		define('CP_REDIRECT', "moderator.php?do=" . ($vbulletin->GPC['redir'] == 'showlist' ? 'showlist' : 'showlist'));
	}
	else
	{
		define('CP_REDIRECT', "forum.php?do=modify&amp;f=" . $vbulletin->GPC['forumid'] . "#forum" . $vbulletin->GPC['forumid']);
	}
	print_stop_message('saved_moderator_x_successfully', $moddata->info['user']['username']);

}

// ###################### Start Remove moderator #######################

if ($_REQUEST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'redir' => TYPE_STR
	));

	$hidden = array('redir' => $vbulletin->GPC['redir']);

	print_delete_confirmation('moderator', $vbulletin->GPC['moderatorid'], 'moderator', 'kill', 'moderator', $hidden);
}

// ###################### Start Kill moderator #######################

if ($_POST['do'] == 'kill')
{
	$mod = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "moderator WHERE moderatorid = " . $vbulletin->GPC['moderatorid']);
	if (!$mod)
	{
		print_stop_message('invalid_moderator_specified');
	}

	$moddata =& datamanager_init('Moderator', $vbulletin, ERRTYPE_CP);
	$moddata->set_existing($mod);
	$moddata->delete(true);

	$vbulletin->input->clean_array_gpc('p', array(
		'redir' => TYPE_STR
	));

	if ($vbulletin->GPC['redir'] == 'modlist')
	{
		define('CP_REDIRECT', 'moderator.php?do=showlist');
	}
	else
	{
		define('CP_REDIRECT', 'forum.php');
	}
	print_stop_message('deleted_moderator_successfully');
}

// ###################### Start Show moderator list per moderator #######################

if ($_REQUEST['do'] == 'showlist')
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

	// get the timestamp for the beginning of today, according to bbuserinfo's timezone
	require_once(DIR . '/includes/functions_misc.php');
	$unixtoday = vbmktime(0, 0, 0, vbdate('m', TIMENOW, false, false), vbdate('d', TIMENOW, false, false), vbdate('Y', TIMENOW, false, false));

	print_form_header('', '');
	print_table_header($vbphrase['super_moderators']);
	echo "<tr valign=\"top\">\n\t<td class=\"" . fetch_row_bgclass() . "\" colspan=\"2\">";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: $stylevar[left]\"><ul>";

	$countmods = 0;
	$supergroups = $db->query_read("
		SELECT user.*, usergroup.usergroupid
		FROM " . TABLE_PREFIX . "usergroup AS usergroup
		INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
		WHERE (usergroup.adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ")
		GROUP BY user.userid
		ORDER BY user.username
	");
	if ($db->num_rows($supergroups))
	{
		while ($supergroup = $db->fetch_array($supergroups))
		{
			$countmods++;
			if ($supergroup['lastactivity'] >= $unixtoday)
			{
				$onlinecolor = 'modtoday';
			}
			else if ($supergroup['lastactivity'] >= ($unixtoday - 86400))
			{
				$onlinecolor = 'modyesterday';
			}
			else if ($supergroup['lastactivity'] >= ($unixtoday - 864000))
			{
				$onlinecolor = 'modlasttendays';
			}
			else if ($supergroup['lastactivity'] >= ($unixtoday - 2592000))
			{
				$onlinecolor = 'modsincetendays';
			}
			else
			{
				$onlinecolor = 'modsincethirtydays';
			}

			$lastonline = vbdate($vbulletin->options['dateformat'] . ' ' .$vbulletin->options['timeformat'], $supergroup['lastactivity']);
			echo "\n\t<li><b><a href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$supergroup[userid]\">$supergroup[username]</a></b><span class=\"smallfont\"> (" . construct_link_code($vbphrase['edit_permissions'], "moderator.php?" . $vbulletin->session->vars['sessionurl'] . "do=editglobal&amp;u=$supergroup[userid]") . ") - " . $vbphrase['last_online'] . " <span class=\"$onlinecolor\">" . $lastonline . "</span></span></li>\n";
		}
	}
	else
	{
		echo $vbphrase['there_are_no_moderators'];
	}
	echo "</ul></div>\n";
	echo "</td>\n</tr>\n";

	if ($countmods)
	{
		print_table_footer(1, $vbphrase['total'] . ": <b>$countmods</b>");
	}
	else
	{
		print_table_footer();
	}

	print_form_header('', '');
	print_table_header($vbphrase['moderators']);
	echo "<tr valign=\"top\">\n\t<td class=\"" . fetch_row_bgclass() . "\" colspan=\"2\">";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: $stylevar[left]\">";

	$countmods = 0;
	$moderators = $db->query_read("
		SELECT moderator.moderatorid, user.userid, user.username, user.lastactivity, forum.forumid, forum.title
		FROM " . TABLE_PREFIX . "forum AS forum
		INNER JOIN " . TABLE_PREFIX . "moderator AS moderator ON (moderator.forumid = forum.forumid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
		ORDER BY user.username, forum.title
	");
	if ($db->num_rows($moderators))
	{
		$curmod = -1;
		while ($moderator = $db->fetch_array($moderators))
		{
			if ($curmod != $moderator['userid'])
			{
				$curmod = $moderator['userid'];
				if ($countmods++ != 0)
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
				$lastonline = vbdate($vbulletin->options['dateformat'] . ' ' .$vbulletin->options['timeformat'], $moderator['lastactivity']);
				echo "\n\t<ul>\n\t<li><b><a href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=$moderator[userid]&amp;redir=showlist\">$moderator[username]</a></b><span class=\"smallfont\"> - " . $vbphrase['last_online'] . " <span class=\"$onlinecolor\">" . $lastonline . "</span></span>\n";
				echo "\n\t\t<ul>$vbphrase[forums] <span class=\"smallfont\">(" . construct_link_code($vbphrase['remove_moderator_from_all_forums'], "moderator.php?" . $vbulletin->session->vars['sessionurl'] . "do=removeall&amp;u=$moderator[userid]") . ")</span>\n\t<ul>\n";
			}
			echo "\t\t\t<li><a href=\"../forumdisplay.php?" . $vbulletin->session->vars['sessionurl'] . "f=$moderator[forumid]\" target=\"_blank\">$moderator[title]</a>\n".
				"\t\t\t\t<span class=\"smallfont\">(" . construct_link_code($vbphrase['edit'], "moderator.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&moderatorid=$moderator[moderatorid]&amp;redir=showlist").
				construct_link_code($vbphrase['remove'], "moderator.php?" . $vbulletin->session->vars['sessionurl'] . "do=remove&moderatorid=$moderator[moderatorid]") . ")</span>\n".
				"\t\t\t</li><br />\n";
		}
		echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
	}
	else
	{
		echo $vbphrase['there_are_no_moderators'];
	}
	echo "</div>\n";
	echo "</td>\n</tr>\n";

	if ($countmods)
	{
		print_table_footer(1, $vbphrase['total'] . ": <b>$countmods</b>");
	}
	else
	{
		print_table_footer();
	}
}

// ###################### Start Show moderator list per forum #######################

if ($_REQUEST['do'] == 'showmods')
{

	$forums = $db->query_read("
		SELECT moderator.moderatorid, user.userid, user.username, user.lastactivity, forum.forumid, forum.title
		FROM " . TABLE_PREFIX . "moderator AS moderator
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (moderator.forumid = forum.forumid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
		" . iif($vbulletin->GPC['forumid'], "WHERE moderator.forumid = " . $vbulletin->GPC['forumid']) . "
		ORDER BY forum.title, user.username
	");

	if (!$db->num_rows($forums))
	{
		print_stop_message('this_forum_does_not_have_any_moderators');
	}

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
	require_once(DIR . '/includes/functions_misc.php');
	$unixtoday = vbmktime(0, 0, 0, vbdate('m', TIMENOW, false, false), vbdate('d', TIMENOW, false, false), vbdate('Y', TIMENOW, false, false));

	$list = array();
	$curforum = -1;
	if ($db->num_rows($forums))
	{
		while ($forum = $db->fetch_array($forums))
		{
			$modlist["$forum[userid]"]++;

			if ($curforum != $forum['forumid'])
			{
				$curforum = $forum['forumid'];
				if ($countforums++ != 0)
				{
					echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
				}



				echo "\n\t<ul>\n\t<li><b><a href=\"../forumdisplay.php?" . $vbulletin->session->vars['sessionurl'] . "f=$forum[forumid]\">$forum[title]</a></b>\n";
				echo "\n\t\t<ul>$vbphrase[moderators]\n\t<ul>\n";
			}

			if ($forum['lastactivity'] >= $unixtoday)
			{
				$onlinecolor = 'modtoday';
			}
			else if ($forum['lastactivity'] >= ($unixtoday - 86400))
			{
				$onlinecolor = 'modyesterday';
			}
			else if ($forum['lastactivity'] >= ($unixtoday - 864000))
			{
				$onlinecolor = 'modlasttendays';
			}
			else if ($forum['lastactivity'] >= ($unixtoday - 2592000))
			{
				$onlinecolor = 'modsincetendays';
			}
			else
			{
				$onlinecolor = 'modsincethirtydays';
			}

			$lastonline = vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $forum['lastactivity']);

			echo "\t\t\t<li><a href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=$forum[userid]\" target=\"_blank\">$forum[username]</a>" .
				"\t\t\t\t<span class=\"smallfont\">(" . construct_link_code($vbphrase['edit'], "moderator.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&moderatorid=$forum[moderatorid]&amp;redir=showmods") .
				construct_link_code($vbphrase['remove'], "moderator.php?" . $vbulletin->session->vars['sessionurl'] . "do=remove&moderatorid=$forum[moderatorid]&redir=modlist") . ")" .
				" - " . $vbphrase['last_online'] . " <span class=\"$onlinecolor\">" . $lastonline . "</span></span>\n" .
				"\t\t\t</li><br />\n";
		}
		echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
	}
	else
	{
		echo $vbphrase['there_are_no_moderators'];
	}
	echo "</div>\n";
	echo "</td>\n</tr>\n";

	if (!empty($modlist))
	{
		print_table_footer(1, $vbphrase['total'] . ": <b>" . count($modlist) . "</b>");
	}
	else
	{
		print_table_footer();
	}
}

// ###################### Start Remove moderator from all forums #######################

if ($_REQUEST['do'] == 'removeall')
{

	$modinfo = $db->query_first("
		SELECT username FROM " . TABLE_PREFIX . "moderator AS moderator
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		WHERE moderator.userid = " . $vbulletin->GPC['userid'] . "
	");
	if (!$modinfo)
	{
		print_stop_message('user_no_longer_moderator');
	}

	print_form_header('moderator', 'killall', 0, 1, '', '75%');
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row('<blockquote><br />' . $vbphrase['are_you_sure_you_want_to_delete_this_moderator'] . "<br /></blockquote>\n\t");
	print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
}

// ###################### Start Kill moderator from all forums #######################

if ($_POST['do'] == 'killall')
{

	if (empty($vbulletin->GPC['userid']))
	{
		print_stop_message('invalid_users_specified');
	}

	$getuserid = $db->query_first("
		SELECT user.*,
		IF (user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid
		FROM " . TABLE_PREFIX . "moderator AS moderator
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		WHERE moderator.userid = " . $vbulletin->GPC['userid'] . "
			AND forumid <> -1
	");
	if (!$getuserid)
	{
		print_stop_message('user_no_longer_moderator');
	}
	else
	{
		($hook = vBulletinHook::fetch_hook('admin_moderator_killall')) ? eval($hook) : false;

		$db->query_write("DELETE FROM " . TABLE_PREFIX . "moderator WHERE userid = " . $vbulletin->GPC['userid'] . " AND forumid <> -1");
		// if the user is in the moderators usergroup, then move them to registered users usergroup
		if ($getuserid['usergroupid'] == 7)
		{
			if (!$getuserid['customtitle'])
			{
				if (!$vbulletin->usergroupcache["2"]['usertitle'])
				{
					$gettitle = $db->query_first("
						SELECT title
						FROM " . TABLE_PREFIX . "usertitle
						WHERE minposts <= $getuserid[posts]
						ORDER BY minposts DESC
					");
					$usertitle = $gettitle['title'];
				}
				else
				{
					$usertitle = $vbulletin->usergroupcache["2"]['usertitle'];
				}
			}
			else
			{
				$usertitle = $getuserid['usertitle'];
			}

			$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdm->set_existing($getuserid);
			$userdm->set('usergroupid', 2);

			$getuserid['usergroupid'] = 2;
			if ($getuserid['displaygroupid'] == 7)
			{
				$userdm->set('displaygroupid', 2);
				$getuserid['displaygroupid'] = 2;
			}
			$userdm->set('usertitle', $usertitle);

			$userdm->save();
			unset($userdm);
		}

		define('CP_REDIRECT', "moderator.php?do=showlist");
		print_stop_message('deleted_moderators_successfully');
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 16924 $
|| ####################################################################
\*======================================================================*/
?>