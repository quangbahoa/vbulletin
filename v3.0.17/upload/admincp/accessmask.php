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
define('CVS_REVISION', '$RCSfile$ - $Revision: 12738 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cppermission', 'accessmask');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require('./includes/adminfunctions_forums.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminpermissions'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['forumid'] != 0, "forum id = ".$_REQUEST['forumid'] . iif($_REQUEST['accessmask'] != 0, " / accessmask = ".$_REQUEST['accessmask'])));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['access_mask_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start Edit Access #######################

if ($_REQUEST['do'] == 'edit')
{

	$forumid = intval($_REQUEST['forumid']);
	$accessmask = intval($_REQUEST['accessmask']);

	$forum = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "forum WHERE forumid = $forumid");

	print_form_header('accessmask', 'update');
	construct_hidden_code('forumid', $forumid);

	print_table_header(construct_phrase($vbphrase['user_forum_access_for_x'], '<span class="normal">' . $forum['title'] . '</span>'), 2, 0);
	print_description_row($vbphrase['here_you_may_edit_forum_access_on_a_user_by_user_basis']);

	print_table_header($vbphrase['users']);

	if ($accessmask != 2)
	{
		$accessmasksql = "AND accessmask='$accessmask'";
	}

	$accesslist = $DB_site->query("
		SELECT access.*, user.userid, user.username
		FROM " . TABLE_PREFIX . "access AS access
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = access.userid
		WHERE forumid = $forumid $accessmasksql
		ORDER BY user.username
	");

	while ($access=$DB_site->fetch_array($accesslist))
	{
		if ($access['accessmask'] == 0)
		{
			$sel = 0;
		}
		else if ($access['accessmask'] == 1)
		{
			$sel = 1;
		}
		else
		{
			$sel = -1;
		}
		construct_hidden_code('oldcache[' . $access['userid'] . ']', $sel);
		$radioname = 'accessupdate[' . $access['userid'] . ']';
		print_label_row($access['username'], "
			<label for=\"rb_1_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"1\" id=\"rb_1_$radioname\" tabindex=\"1\"" . iif($sel==1," checked=\"checked\"","")." />" . $vbphrase['yes'] . "</label>
			<label for=\"rb_0_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"0\" id=\"rb_0_$radioname\" tabindex=\"1\"" . iif($sel==0," checked=\"checked\"","")." />" . $vbphrase['no'] . "</label>
			<label for=\"rb_x_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"-1\" id=\"rb_x_$radioname\" tabindex=\"1\"" . iif($sel==-1," checked=\"checked\"","")." />" . $vbphrase['default'] . "</label>
		");
	}
	print_submit_row();
}

// ###################### Start Update Access #######################

if ($_POST['do'] == 'update')
{
	if (!is_array($_POST['oldcache']) OR !is_array($_POST['accessupdate']))
	{
		print_stop_message('nothing_to_do');
	}
	globalize($_POST, array('oldcache', 'forumid' => INT));

	foreach ($_POST['accessupdate'] AS $userid => $val)
	{
		$userid = intval($userid);

		// build 3 arrays, one of users to have access masks added
		// one to have theres deleted
		// those to have it changed

		if ($oldcache[$userid] == $val)
		{
			continue;
		}

		if ($oldcache[$userid] != '-1' AND $val == '-1')
		{ // remove access mask
			$countaccess = $DB_site->query_first("
				SELECT COUNT(*) AS masks
				FROM " . TABLE_PREFIX . "access
				WHERE userid = $userid
			");

			$countaccess['masks']--;
			if ($countaccess['masks'] == 0)
			{
				$maskdelete[] = $userid;
			}
			// we're removing a forum so remove it from the total
			$removemask[] = $userid;
		}
		else
		{ // add access mask or updating it
			$updateuserids[] = $userid;
			$newmask[] = "($userid, $forumid, $val)";
		}

	}

	if (is_array($removemask))
	{
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "access
			WHERE forumid = $forumid AND userid IN (" . implode(',', $removemask) . ")
		");
	}

	if (is_array($maskdelete))
	{
		$maskdelete = implode(',', $maskdelete);
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET options = (options - $_USEROPTIONS[hasaccessmask])
			WHERE userid IN ($maskdelete) AND (options & $_USEROPTIONS[hasaccessmask])
		");
	}

	if (is_array($newmask))
	{
		$DB_site->query("
			REPLACE INTO " . TABLE_PREFIX . "access
			(userid,forumid,accessmask)
			VALUES " . implode(",\n\t", $newmask)
		);
		$updateuserids = implode(',', $updateuserids);
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET options = (options + $_USEROPTIONS[hasaccessmask])
			WHERE userid IN ($updateuserids) AND NOT (options & $_USEROPTIONS[hasaccessmask])
		");
	}

	define('CP_REDIRECT', 'accessmask.php?do=modify');
	print_stop_message('saved_user_forum_access_successfully');
}

// ###################### Start quick edit #######################
if ($_REQUEST['do'] == 'quickedit')
{
	print_form_header('accessmask', 'doquickedit');
	print_table_header($vbphrase['access_masks_quick_editor'], 4);
	print_cells_row(array(
		"<a href=\"accessmask.php?$session[sessionurl]do=quickedit&amp;orderby=user\" title=\"" . $vbphrase['sort'] . "\">" . $vbphrase['username'] . "</a>",
		"<a href=\"accessmask.php?$session[sessionurl]do=quickedit&amp;orderby=forum\" title=\"" . $vbphrase['sort'] . "\">" . $vbphrase['forum'] . "</a>",
		'<input type="button" value="' . $vbphrase['all_yes'] . '" onclick="js_check_all_option(this.form, 1);" class="button" />
		<input type="button" value=" ' . $vbphrase['all_no'] . ' " onclick="js_check_all_option(this.form, 0);" class="button" />
		<input type="button" value="' . $vbphrase['all_default'] .'" onclick="js_check_all_option(this.form, -1);" class="button" />'), 0, 'thead', 0, 'middle');
	$accessmasks = $DB_site->query("
		SELECT user.username, user.userid, forum.forumid, forum.title AS forum_title, accessmask
		FROM " . TABLE_PREFIX . "access AS access,
		" . TABLE_PREFIX . "user AS user,
		" . TABLE_PREFIX . "forum AS forum
		WHERE access.userid = user.userid AND
			access.forumid = forum.forumid
		" . iif($_REQUEST['orderby'] == 'forum', 'ORDER BY forum_title, username', 'ORDER BY username, forum_title')
	);
	if ($DB_site->num_rows($accessmasks) > 0)
	{
		while ($access = $DB_site->fetch_array($accessmasks))
		{
			if ($access['accessmask'] == 0)
			{
				$sel = 0;
			}
			else if ($access['accessmask'] == 1)
			{
				$sel = 1;
			}
			else
			{
				$sel = -1;
			}
			construct_hidden_code('oldcache[' . $access['userid'] . '][' . $access['forumid'] . ']', $sel);
			$radioname = 'accessupdate[' . $access['userid'] . '][' . $access['forumid'] . ']';

			print_cells_row(array(
				"<a href=\"user.php?$session[sessionurl]do=editaccess&userid=$access[userid]\">$access[username]</a>",
				"<a href=\"accessmask.php?$session[sessionurl]do=edit&amp;forumid=$access[forumid]&amp;accessmask=2\">$access[forum_title]</a>",
				"
					<label for=\"rb_1_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"1\" id=\"rb_1_$radioname\" tabindex=\"1\"" . iif($sel==1," checked=\"checked\"","")." />" . $vbphrase['yes'] . "</label>
					<label for=\"rb_0_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"0\" id=\"rb_0_$radioname\" tabindex=\"1\"" . iif($sel==0," checked=\"checked\"","")." />" . $vbphrase['no'] . "</label>
					<label for=\"rb_x_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"-1\"  id=\"rb_x_$radioname\"tabindex=\"1\"" . iif($sel==-1," checked=\"checked\"","")." />" . $vbphrase['default'] . "</label>
				"));
		}
		print_submit_row($vbphrase['update'], $vbphrase['reset'], 4);
	}
	else
	{
		print_description_row($vbphrase['nothing_to_do'], 0, 4, '', 'center');
		print_table_footer();
	}
}

// ###################### Start do quick edit #######################
if ($_POST['do'] == 'doquickedit')
{
	if (!is_array($_POST['oldcache']) OR !is_array($_POST['accessupdate']))
	{
		print_stop_message('nothing_to_do');
	}

	globalize($_POST, array('oldcache', 'accessupdate'));

	foreach($accessupdate AS $userid => $accessforums)
	{
		$userid = intval($userid);

		foreach($accessforums AS $forumid => $val)
		{
			$forumid = intval($forumid);

			if ($oldcache["$userid"]["$forumid"] == $val)
			{
				continue;
			}
			if ($oldcache["$userid"]["$forumid"] == '-1')
			{
				$DB_site->query("
					INSERT IGNORE INTO " . TABLE_PREFIX . "access
					(userid,forumid,accessmask)
					VALUES
					($userid, $forumid, '$val')
				");
			}
			else if ($oldcache["$userid"]["$forumid"] != '-1' AND $val == '-1')
			{
				$DB_site->query("
					DELETE FROM " . TABLE_PREFIX . "access
					WHERE userid = $userid AND
						forumid = $forumid
				");
			}
			else
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "access
					SET accessmask = '$val'
					WHERE userid = $userid AND
						forumid = $forumid
				");
			}
		}
		$countaccess = $DB_site->query_first("
			SELECT COUNT(*) AS masks
			FROM " . TABLE_PREFIX . "access
			WHERE userid = $userid
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET options = (options" . iif($countaccess['masks'], ' | ' , ' & ~' ) . "$_USEROPTIONS[hasaccessmask])
			WHERE userid = $userid
		");
	}

	define('CP_REDIRECT', 'accessmask.php?do=modify');
	print_stop_message('saved_user_forum_access_successfully');

}

// ###################### Start reset all access masks for forum #######################
if ($_REQUEST['do'] == 'resetforum')
{
	globalize($_REQUEST, array(
		'forumid' => INT,
		'hash' => STR
	));

	if ($hash != $cpsession['hash'])
	{
		exit;
	}

	if (!$forumid)
	{
		print_stop_message('invalid_forum_specified');
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "access WHERE forumid = $forumid");

	define('CP_REDIRECT', 'accessmask.php?do=modify');
	print_stop_message('deleted_access_masks_successfully');
}

// ###################### Start Delete All Access Masks #######################
if ($_REQUEST['do'] == 'resetall')
{
	print_form_header('accessmask', 'doresetall');
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['delete_all_access_masks']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Process Delete All Access Masks #######################

if ($_POST['do'] == 'doresetall')
{
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "access");

	define('CP_REDIRECT', 'accessmask.php?do=modify');
	print_stop_message('saved_user_forum_access_successfully');
}

// ###################### Start displayforums #######################
function print_forums($parentid = -1, $indent = "	")
{
// new version - DRASTICALLY reduces queries...
	global $DB_site, $iforumcache, $forumcache, $imodcache, $accesscache, $session, $vbphrase, $cpsession;
	// check to see if we need to do the queries
	if (!is_array($iforumcache))
	{
		require_once('./includes/functions_forumlist.php');
		cache_ordered_forums(0, 1);
	}
	if (!is_array($imodcache))
	{
		require_once('./includes/functions_forumlist.php');
		cache_moderators();
	}
	// check to see if this forum actually exists / has children
	if (!isset($iforumcache["$parentid"]))
	{
		return;
	}
	foreach ($iforumcache["$parentid"] AS $holder)
	{
		echo "$indent<ul class=\"lsq\">\n";
		foreach ($holder AS $forumid)
		{
			$forum = $forumcache["$forumid"];
			// forum title and links
			echo "$indent<li><b><a name=\"forum$forumid\" href=\"forum.php?$session[sessionurl]do=edit&amp;forumid=$forumid\">$forum[title]</a> <span class=\"smallfont\">(" . construct_link_code($vbphrase['reset'], "accessmask.php?$session[sessionurl]do=resetforum&amp;forumid=$forumid&amp;hash=$cpsession[hash]") . ")</span></b>";
			// get moderators
			if (is_array($imodcache["$forumid"]))
			{
				echo "<span class=\"smallfont\"><br /> - <i>".$vbphrase['moderators'].":";
				foreach($imodcache["$forumid"] AS $moderator)
				{
					// moderator username and links
					echo " <a href=\"moderator.php?$session[sessionurl]do=edit&amp;moderatorid=$moderator[moderatorid]\">$moderator[username]</a>";
				}
				echo "</i></span>";
			}
			$allaccessmasks = 0;
			$forbidden = "";
			$permitted = "";
			$deny = $accesscache["$forumid"]["0"];
			$permit = $accesscache["$forumid"]["1"];
			if (is_array($deny))
			{
				$forbidden="$indent\t<li class=\"am-deny\"><b>" . construct_phrase($vbphrase['access_denied_x_users'], $deny['count']) . '</b>' . construct_link_code($vbphrase['display_users'], "accessmask.php?s=$session[sessionhash]&do=edit&forumid=$forumid&accessmask=$deny[accessmask]") . "</li>\n";
				$allaccessmasks=$deny[count];
			}
			if (is_array($permit))
			{
				$permitted="$indent\t<li class=\"am-grant\"><b>" . construct_phrase($vbphrase['access_granted_x_users'], $permit['count']) . '</b>' . construct_link_code($vbphrase['display_users'], "accessmask.php?s=$session[sessionhash]&do=edit&forumid=$forumid&accessmask=$permit[accessmask]") . "</li>\n";
				$allaccessmasks = $allaccessmasks + $permit['count'];
			}
			if ($allaccessmasks>0)
			{
				echo "$indent\t<ul class=\"usergroups\">\n";
				echo "$indent\t<li>" . construct_phrase($vbphrase['x_access_masks_set'], $allaccessmasks) . ' ' . construct_link_code('<b>' . $vbphrase['display_all_users'] . '</b>', "accessmask.php?s=$session[sessionhash]&do=edit&forumid=$forum[forumid]&accessmask=2")."</li>";
				echo $permitted;
				echo $forbidden;
				echo "$indent\t</ul><br />\n";
			}
			else
			{
				echo "$indent\t\n";
				echo "$indent\t<br />\n";
			}
			print_forums($forumid, "$indent	");
			echo "$indent</li>\n";
		}
		echo "$indent</ul>\n";
		if ($forum['parentid'] == -1)
		{
			echo "<hr size=\"1\" />\n";
		}
	}
	unset($iforumcache["$parentid"]);
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{

	print_form_header('', '');
	print_table_header($vbphrase['additional_functions']);
	print_description_row("<b><a href=\"accessmask.php?$session[sessionurl]do=resetall\">" . $vbphrase['delete_all_access_masks'] . "</a> | <a href=\"accessmask.php?$session[sessionurl]do=quickedit\">" . $vbphrase['access_masks_quick_editor'] . "</a></b>", 0, 2, '', 'center');
	print_table_footer();

	print_form_header('', '');
	print_table_header($vbphrase['access_masks']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="am-grant">' . $vbphrase['access_granted'] . '</li>
		<li class="am-deny">' . $vbphrase['access_denied'] . '</li>
		</ul></div>
	');
	print_table_footer();

	// query access masks
	$accessmasks = $DB_site->query("SELECT COUNT(*) AS count,forumid,accessmask FROM " . TABLE_PREFIX . "access GROUP BY forumid,accessmask");
	// make access masks cache
	$accesscache = array();
	while ($amask = $DB_site->fetch_array($accessmasks))
	{
		$accesscache["$amask[forumid]"]["$amask[accessmask]"] = $amask;
	}

	echo "<center>\n";
	echo "<div class=\"tborder\" style=\"width: 89%\">\n";
	echo "<div class=\"alt1\" style=\"padding: 8px\">\n";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: $stylevar[left]\">\n";

	// run the display function
	print_forums();

	echo "</div></div></div>\n</center>\n";

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12738 $
|| ####################################################################
\*======================================================================*/
?>