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
define('CVS_REVISION', '$RCSfile$ - $Revision: 10943 $');
define('NO_REGISTER_GLOBALS', 1);
@set_time_limit(0);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('forum', 'cpuser');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_template.php');
require_once('./includes/adminfunctions_forums.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminforums'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['moderatorid'] != 0, " moderator id = $_REQUEST[moderatorid]", iif($_REQUEST['forumid'] != 0, "forum id = $_REQUEST[forumid]")));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['forum_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'forumid' => INT,
		'parentid' => INT
	));

	print_form_header('forum', 'update');

	if ($_REQUEST['do'] == 'add')
	{
		// Set Defaults;
		$forum = array(
			'displayorder' => 1,
			'daysprune' => 30,
			'parentid' => $parentid,
			'styleid' => '',
			'cancontainthreads' => 1,
			'active' => 1,
			'allowposting' => 1,
			'allowbbcode' => 1,
			'allowsmilies' => 1,
			'allowicons' => 1,
			'allowimages' => 1,
			'allowratings' => 1,
			'countposts' => 1,
			'indexposts' => 1,
			'showonforumjump' => 1,
			'warnall' => 0
		);

		print_table_header($vbphrase['add_new_forum']);
	}
	else
	{
		$forum = fetch_foruminfo($forumid);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['forum'], $forum['title'], $forum['forumid']));
		construct_hidden_code('forumid', $forumid);
	}

	print_input_row($vbphrase['title'], 'forum[title]', $forum['title']);
	print_textarea_row($vbphrase['description'], 'forum[description]', $forum['description']);
	print_input_row($vbphrase['forum_link'], 'forum[link]', $forum['link']);
	print_input_row("$vbphrase[display_order]<dfn>$vbphrase[zero_equals_no_display]</dfn>", 'forum[displayorder]', $forum['displayorder']);
	//print_input_row($vbphrase['default_view_age'], 'forum[daysprune]', $forum['daysprune']);

	// make array for daysprune menu
	$pruneoptions = array(
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
		'-1' => $vbphrase['show_all_threads']
	);

	print_select_row($vbphrase['default_view_age'], 'forum[daysprune]', $pruneoptions, $forum['daysprune']);

	if ($forumid != -1)
	{
		print_forum_chooser('forum[parentid]', $forum['parentid']);
	}
	else
	{
		construct_hidden_code('parentid', 0);
	}

	print_table_header($vbphrase['moderation_options']);

	print_input_row($vbphrase['emails_to_notify_when_post'], 'forum[newpostemail]', $forum['newpostemail']);
	print_input_row($vbphrase['emails_to_notify_when_thread'], 'forum[newthreademail]', $forum['newthreademail']);

	print_yes_no_row($vbphrase['moderate_posts'] . ' <dfn>(' . $vbphrase['require_moderator_validation_before_new_posts_are_displayed'] . ')</dfn>', 'options[moderatenewpost]', $forum['moderatenewpost']);
	print_yes_no_row($vbphrase['moderate_threads'] . ' <dfn>(' . $vbphrase['require_moderator_validation_before_new_threads_are_displayed'] . ')</dfn>', 'options[moderatenewthread]', $forum['moderatenewthread']);
	print_yes_no_row($vbphrase['moderate_attachments'] . ' <dfn>(' . $vbphrase['require_moderator_validation_before_new_attachments_are_displayed'] . ')</dfn>', 'options[moderateattach]', $forum['moderateattach']);
	print_yes_no_row($vbphrase['warn_administrators'], 'options[warnall]', $forum['warnall']);

	print_table_header($vbphrase['style_options']);

	if ($forum['styleid'] == 0)
	{
		$forum['styleid'] = -1; // to get the "use default style" option selected
	}
	print_style_chooser_row('forum[styleid]', $forum['styleid'], $vbphrase['use_default_style'], $vbphrase['custom_forum_style'], 1);
	print_yes_no_row($vbphrase['override_style_choice'], 'options[styleoverride]', $forum['styleoverride']);

	print_table_header($vbphrase['access_options']);

	print_input_row($vbphrase['forum_password'], 'forum[password]', $forum['password']);
	if ($_REQUEST['do'] == 'edit')
	{
		print_yes_no_row($vbphrase['apply_password_to_children'], 'applypwdtochild', iif($forum['password'], 0, 1));
	}
	print_yes_no_row($vbphrase['can_have_password'], 'options[canhavepassword]', $forum['canhavepassword']);

	print_table_header($vbphrase['posting_options']);

	print_yes_no_row($vbphrase['act_as_forum'], 'options[cancontainthreads]', $forum['cancontainthreads']);
	print_yes_no_row($vbphrase['forum_is_active'], 'options[active]', $forum['active']);
	print_yes_no_row($vbphrase['forum_open'], 'options[allowposting]', $forum['allowposting']);
	print_yes_no_row($vbphrase['index_new_posts'], 'options[indexposts]' , $forum['indexposts'] );

	print_table_header($vbphrase['enable_disable_features']);

	print_yes_no_row($vbphrase['allow_html'], 'options[allowhtml]', $forum['allowhtml']);
	print_yes_no_row($vbphrase['allow_bbcode'], 'options[allowbbcode]', $forum['allowbbcode']);
	print_yes_no_row($vbphrase['allow_img_code'], 'options[allowimages]', $forum['allowimages']);
	print_yes_no_row($vbphrase['allow_smilies'], 'options[allowsmilies]', $forum['allowsmilies']);
	print_yes_no_row($vbphrase['allow_icons'], 'options[allowicons]', $forum['allowicons']);
	print_yes_no_row($vbphrase['allow_thread_ratings_in_this_forum'], 'options[allowratings]', $forum['allowratings']);
	print_yes_no_row($vbphrase['count_posts_in_forum'], 'options[countposts]', $forum['countposts']);
	print_yes_no_row($vbphrase['show_forum_on_forum_jump'], 'options[showonforumjump]', $forum['showonforumjump']);

	print_submit_row($vbphrase['save']);
}

// ###################### Start update #######################
if ($_POST['do'] == 'update')
{
	globalize($_POST, array(
		'forumid' => INT,
		'private' => INT,
		'applypwdtochild' => INT,
		'forum',
		'options'
	));

	//$forum['title'] = htmlspecialchars_uni($forum['title']);

	if (empty($forum['title']))
	{
		print_stop_message('invalid_title_specified');
	}

	if ($forum['styleid'] == -1)
	{
		$forum['styleid'] = 0; // use board's default
	}

	require_once('./includes/functions_misc.php');
	$forum['options'] = convert_array_to_bits($options, $_FORUMOPTIONS);
	$forum['title'] = convert_to_valid_html($forum['title']);
	$forum['description'] = convert_to_valid_html($forum['description']);

	if (empty($forumid))
	{
		$parentlist = fetch_forum_parentlist($forum['parentid']);
		$DB_site->query(fetch_query_sql($forum, 'forum'));

		$forumid = $DB_site->insert_id();
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "forum
			SET parentlist='" . addslashes("$forumid,$parentlist") . "',
			childlist = '$forumid,-1'
			WHERE forumid = $forumid
		");

		build_forum_child_lists($forum['parentid']);
		build_forum_permissions();

		define('CP_REDIRECT', "forum.php?do=modify#forum$forumid");
		print_stop_message('saved_forum_x_successfully', $forum['title']);
	}
	else
	{
		$parentid = intval($forum['parentid']);

		// SANITY CHECK (prevent invalid nesting)
		if ($parentid == $forumid)
		{
			print_stop_message('cant_parent_forum_to_self');
		}
		$foruminfo = $DB_site->query_first("
			SELECT forumid,title,parentlist
			FROM " . TABLE_PREFIX . "forum
			WHERE forumid=$parentid
		");
		$parents = explode(',', $foruminfo['parentlist']);
		foreach($parents AS $val)
		{
			if ($val == $forumid)
			{
				print_stop_message('cant_parent_forum_to_child');
			}
		}
		// end Sanity check

		$oldforuminfo = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "forum WHERE forumid = $forumid");

		$forum['parentlist'] = $forumid . ',' . fetch_forum_parentlist($parentid);

		$DB_site->query(fetch_query_sql($forum, 'forum', "WHERE forumid=$forumid"));

		if ($forum['password'] != $oldforuminfo['password'] AND $applypwdtochild)
		{
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "forum
				SET password = '" . addslashes($forum['password']) . "'
				WHERE FIND_IN_SET('$forumid', parentlist)
			");
		}

		unset($forumarraycache, $forumcache);
		build_forum_parentlists($forumid);
		build_forum_child_lists($parentid);
		build_forum_child_lists($oldforuminfo['parentid']);
		build_forum_permissions();

		define('CP_REDIRECT', "forum.php?do=modify#forum$forumid");
		print_stop_message('saved_forum_x_successfully', $forum['title']);
	}

}
// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	globalize($_REQUEST, array('forumid' => INT));

	print_delete_confirmation('forum', $forumid, 'forum', 'kill', 'forum', 0, $vbphrase['are_you_sure_you_want_to_delete_this_forum']);

}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	globalize($_REQUEST, array('forumid' => INT));

	$forums = $DB_site->query("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum
		WHERE FIND_IN_SET('$forumid', parentlist)
	");

	$forumlist = array();

	while($thisforum = $DB_site->fetch_array($forums))
	{
		$forumlist[] = $thisforum['forumid'];
	}
	$forumlist = implode(',', $forumlist);

	if (!empty($forumlist))
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "forum WHERE forumid IN ($forumlist)");

		$threads = $DB_site->query("SELECT threadid, forumid FROM " . TABLE_PREFIX . "thread WHERE forumid IN ($forumlist)");

		require_once('./includes/functions_databuild.php');
		while ($thread = $DB_site->fetch_array($threads))
		{
			delete_thread($thread['threadid'], $forumcache["$thread[forumid]"]['options'] & $_FORUMOPTIONS['countposts']);
		}

		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "forumpermission WHERE forumid IN ($forumlist)");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "access WHERE forumid IN ($forumlist)");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderator WHERE forumid IN ($forumlist)");

		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "announcement WHERE forumid IN ($forumlist)");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscribeforum WHERE forumid IN ($forumlist)");

	}

	build_forum_parentlists();
	build_forum_child_lists();
	build_forum_permissions();

	define('CP_REDIRECT', 'forum.php');
	print_stop_message('deleted_forum_successfully');
}

// ###################### Start do order #######################
if ($_POST['do'] == 'doorder')
{
	globalize($_POST, array('order'));

	if (is_array($order))
	{
		$forums = $DB_site->query("SELECT forumid,displayorder FROM " . TABLE_PREFIX . "forum");
		while ($forum = $DB_site->fetch_array($forums))
		{
			if (!isset($order["$forum[forumid]"]))
			{
				continue;
			}

			$displayorder = intval($order["$forum[forumid]"]);
			if ($forum['displayorder'] != $displayorder)
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "forum
					SET displayorder = $displayorder
					WHERE forumid = $forum[forumid]
				");
			}
		}
	}

	build_forum_permissions();

	define('CP_REDIRECT', 'forum.php?do=modify');
	print_stop_message('saved_display_order_successfully');
}

// ###################### Start forum_is_related_to_forum #######################
function forum_is_related_to_forum($partial_list, $forumid, $full_list)
{
	// This function is only used below, only for expand/collapse of forums.
	// If the first forum's parent list is contained within the second,
	// then it is considered related (think of it as an aunt or uncle forum).

	$partial = explode(',', $partial_list);
	if ($partial[0] == $forumid)
	{
		array_shift($partial);
	}
	$full = explode(',', $full_list);

	foreach ($partial AS $fid)
	{
		if (!in_array($fid, $full))
		{
			return false;
		}
	}

	return true;
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	globalize($_REQUEST, array('expandid' => INT));
	if (!$expandid)
	{
		$expandid = -1;
	}
	else if ($expandid == -2)
	{
		// expand all -- easiest to just turn off collapsing
		$vboptions['cp_collapse_forums'] = false;
	}

	// a little javascript for the options menus
	?>
	<script type="text/javascript">
	function js_forum_jump(foruminfo)
	{
		var cp_collapse_forums = <?php echo intval($vboptions['cp_collapse_forums']); ?>;
		if (foruminfo == 0)
		{
			alert('<?php echo addslashes($vbphrase['please_select_forum']); ?>');
			return;
		}
		else if (typeof(document.cpform.forumid) != 'undefined')
		{
			action = document.cpform.controls.options[document.cpform.controls.selectedIndex].value;
		}
		else
		{
			action = eval("document.cpform.f" + foruminfo + ".options[document.cpform.f" + foruminfo + ".selectedIndex].value");
		}
		if (action != '')
		{
			switch (action)
			{
				case 'edit': page = "forum.php?do=edit&forumid="; break;
				case 'remove': page = "forum.php?do=remove&forumid="; break;
				case 'add': page = "forum.php?do=add&parentid="; break;
				case 'addmod': page = "moderator.php?do=add&forumid="; break;
				case 'listmod': page = "moderator.php?do=showmods&forumid=";break;
				case 'annc': page = "announcement.php?do=add&forumid="; break;
				case 'view': page = "../forumdisplay.php?forumid="; break;
				case 'perms':
					if (cp_collapse_forums > 0)
					{
						page = "forumpermission.php?do=modify&forumid=";
					}
					else
					{
						page = "forumpermission.php?do=modify&devnull=";
					}
					break;
				case 'move': page = "forum.php?do=moveposts&forumid="; break;
				case 'empty': page = "forum.php?do=empty&forumid="; break;
			}
			document.cpform.reset();
			jumptopage = page + foruminfo + "&s=<?php echo $session['sessionhash']; ?>";
			if (action == 'perms')
			{
				window.location = jumptopage + '#forum' + foruminfo;
			}
			else
			{
				window.location = jumptopage;
			}
		}
		else
		{
			alert($vbphrase['invalid_action_specified']);
		}
	}
	function js_moderator_jump(foruminfo)
	{
		if (foruminfo == 0)
		{
			alert('<?php echo addslashes($vbphrase['please_select_forum']); ?>');
			return;
		}
		else if (typeof(document.cpform.forumid) != 'undefined')
		{
			modinfo = document.cpform.moderator[document.cpform.moderator.selectedIndex].value;
		}
		else
		{
			modinfo = eval("document.cpform.m" + foruminfo + ".options[document.cpform.m" + foruminfo + ".selectedIndex].value");
			document.cpform.reset();
		}

		switch (modinfo)
		{
			case 'add': window.location = "moderator.php?s=<?php echo $session['sessionhash']; ?>&do=add&forumid=" + foruminfo; break;
			case 'show': window.location = "moderator.php?s=<?php echo $session['sessionhash']; ?>&do=showmods&forumid=" + foruminfo; break;
			case '': return false; break;
			default: window.location = "moderator.php?s=<?php echo $session['sessionhash']; ?>&do=edit&moderatorid=" + modinfo; break;
		}
	}
	function js_returnid()
	{
		return document.cpform.forumid.value;
	}
	</script>
	<?php

	$forumoptions1 = array(
		'edit' => $vbphrase['edit_forum'],
		'view' => $vbphrase['view_forum'],
		'remove' => $vbphrase['delete_forum'],
		'add' => $vbphrase['add_child_forum'],
		'addmod' => $vbphrase['add_moderator'],
		'listmod' => $vbphrase['list_moderators'],
		'annc' => $vbphrase['add_announcement'],
		'perms' => $vbphrase['view_permissions'],
	);

	$forumoptions2 = array(
		'edit' => $vbphrase['edit_forum'],
		'view' => $vbphrase['view_forum'],
		'remove' => $vbphrase['delete_forum'],
		'add' => $vbphrase['add_child_forum'],
		'addmod' => $vbphrase['add_moderator'],
		'annc' => $vbphrase['add_announcement'],
		'perms' => $vbphrase['view_permissions'],
	);

	require_once('./includes/functions_databuild.php');
	cache_forums();

	if ($vboptions['cp_collapse_forums'] != 2)
	{

		print_form_header('forum', 'doorder');
		print_table_header($vbphrase['forum_manager'], 4);
		print_description_row($vbphrase['if_you_change_display_order'], 0, 4);

		require_once('./includes/functions_forumlist.php');
		cache_moderators();

		$forums = array();
		$expanddata = array('forumid' => -1, 'parentlist' => '');
		if (is_array($forumcache))
		{
			foreach($forumcache AS $forumid => $forum)
			{
				$forums["$forum[forumid]"] = construct_depth_mark($forum['depth'], '--') . ' ' . $forum['title'];
				if ($forum['forumid'] == $expandid)
				{
					$expanddata = $forum;
				}
			}
		}
		$expanddata['parentids'] = explode(',', $expanddata['parentlist']);

		if ($vboptions['cp_collapse_forums'])
		{
			$expandtext = '[-] ';
		}
		else
		{
			$expandtext = '';
		}

		if (is_array($forumcache))
		{
			foreach($forumcache AS $key => $forum)
			{
				$modcount = sizeof($imodcache["$forum[forumid]"]);
				if ($modcount)
				{
					$mainoptions = &$forumoptions1;
					$mainoptions['listmod'] = $vbphrase['list_moderators'] . " ($modcount)";
				}
				else
				{
					$mainoptions = &$forumoptions2;
				}

				$cell = array();
				if (!$vboptions['cp_collapse_forums'] OR $forum['forumid'] == $expanddata['forumid'] OR in_array($forum['forumid'], $expanddata['parentids']))
				{
					$cell[] = "<a name=\"forum$forum[forumid]\">&nbsp;</a> $expandtext<b>" . construct_depth_mark($forum['depth'],'- - ') . "<a href=\"forum.php?$session[sessionurl]do=edit&forumid=$forum[forumid]\">$forum[title]</a>" . iif(!empty($forum['password']),'*') . " " . iif($forum['link'], "(<a href=\"$forum[link]\">" . $vbphrase['link'] . "</a>)") . "</b>";
					$cell[] = "\n\t<select name=\"f$forum[forumid]\" onchange=\"js_forum_jump($forum[forumid]);\" class=\"bginput\">\n" . construct_select_options($mainoptions) . "\t</select><input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_forum_jump($forum[forumid]);\" />\n\t";
					$cell[] = "<input type=\"text\" class=\"bginput\" name=\"order[$forum[forumid]]\" value=\"$forum[displayorder]\" tabindex=\"1\" size=\"3\" title=\"" . $vbphrase['edit_display_order'] . "\" />";

					$mods = array('no_value' => $vbphrase['moderators'].' (' . sizeof($imodcache["$forum[forumid]"]) . ')');
					if (is_array($imodcache["$forum[forumid]"]))
					{
						foreach ($imodcache["$forum[forumid]"] AS $moderator)
						{
							$mods['']["$moderator[moderatorid]"] = $moderator['username'];
						}
					}
					$mods['add'] = $vbphrase['add_moderator'];
					$cell[] = "\n\t<select name=\"m$forum[forumid]\" onchange=\"js_moderator_jump($forum[forumid]);\" class=\"bginput\">\n" . construct_select_options($mods) . "\t</select><input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_moderator_jump($forum[forumid]);\" />\n\t";
				}
				else if (
					$vboptions['cp_collapse_forums'] AND
						(
						$forum['parentid'] == $expanddata['forumid'] OR
						$forum['parentid'] == -1 OR
						forum_is_related_to_forum($forum['parentlist'], $forum['forumid'], $expanddata['parentlist'])
						)
					)
				{
					$cell[] = "<a name=\"forum$forum[forumid]\">&nbsp;</a> <a href=\"forum.php?$session[sessionurl]do=modify&amp;expandid=$forum[forumid]\">[+]</a>  <b>" . construct_depth_mark($forum['depth'],'- - ') . "<a href=\"forum.php?$session[sessionurl]do=edit&forumid=$forum[forumid]\">$forum[title]</a>" . iif(!empty($forum['password']),'*') . " " . iif($forum['link'], "(<a href=\"$forum[link]\">" . $vbphrase['link'] . "</a>)") . "</b>";
					$cell[] = construct_link_code($vbphrase['expand'], "forum.php?$session[sessionurl]do=modify&amp;expandid=$forum[forumid]");
					$cell[] = "&nbsp;";
					$cell[] = "&nbsp;";
				}
				else
				{
					continue;
				}

				if ($forum['parentid'] == -1)
				{
					print_cells_row(array($vbphrase['forum'], $vbphrase['controls'], $vbphrase['display_order'], $vbphrase['moderators']), 1, 'tcat');
				}
				print_cells_row($cell);
			}
		}

		print_table_footer(4, "<input type=\"submit\" class=\"button\" tabindex=\"1\" value=\"" . $vbphrase['save_display_order'] . "\" accesskey=\"s\" />" . construct_button_code($vbphrase['add_new_forum'], "forum.php?$session[sessionurl]do=add"));

		if ($vboptions['cp_collapse_forums'])
		{
			echo '<p class="smallfont" align="center">' . construct_link_code($vbphrase['expand_all'], "forum.php?$session[sessionurl]do=modify&amp;expandid=-2") . '</p>';
		}

		echo '<p class="smallfont" align="center">' . $vbphrase['forums_marked_asterisk_are_password_protected'] . '</p>';
	}
	else
	{

		print_form_header('forum', 'doorder');
		print_table_header($vbphrase['forum_manager'], 2);

		print_cells_row(array($vbphrase['forum'], $vbphrase['controls']), 1, 'tcat');
		$cell = array();

		$select = '<select name="forumid" id="sel_forumid" tabindex="1" class="bginput">';
		$select .= construct_forum_chooser(-1, true);
		$select .= "</select>\n";

		$cell[] = $select;
		$cell[] = "\n\t<select name=\"controls\" class=\"bginput\">\n" . construct_select_options($forumoptions1) . "\t</select><input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_forum_jump(js_returnid());\" />\n\t";
		print_cells_row($cell);
		print_table_footer(2, construct_button_code($vbphrase['add_new_forum'], "forum.php?$session[sessionurl]do=add"));
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 10943 $
|| ####################################################################
\*======================================================================*/
?>