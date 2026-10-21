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
$phrasegroups = array('cppermission', 'forum');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_forums.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminpermissions'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['fp'] != 0, "forumpermission id = " . $_REQUEST['fp'], iif($_REQUEST['f'] != 0, "forum id = " . $_REQUEST['f'] . iif($_REQUEST['u'] != 0, " / usergroup id = " . $_REQUEST['u']))));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['forum_permissions_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'f' => INT,
		'u' => INT,
		'fp' => INT,
	));

	$forumid = &$f;
	$usergroupid = &$u;
	$forumpermissionid = &$fp;

	?>
	<script type="text/javascript">
	<!--
	function js_set_custom()
	{
		if (document.cpform.useusergroup[1].checked == false)
		{
			if (confirm("<?php echo $vbphrase['must_enable_custom_permissions']; ?>"))
			{
				document.cpform.useusergroup[1].checked = true;
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return true;
		}
	}
	// -->
	</script>
	<?php

	print_form_header('forumpermission', 'doupdate');

	if (empty($forumpermissionid))
	{
		$forum = $forumcache["$forumid"];
		$usergroup = $usergroupcache["$usergroupid"];
		if (!$forum)
		{
			print_table_footer();
			print_stop_message('invalid_forum_specified');
		}
		else if (!$usergroup)
		{
			print_table_footer();
			print_stop_message('invalid_usergroup_specified');
		}
		$getperms = fetch_forum_permissions($usergroupid, $forumid);
		construct_hidden_code('forumpermission[usergroupid]', $usergroupid);
		construct_hidden_code('forumid', $forumid);
	}
	else
	{
		$getperms = $DB_site->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "forumpermission
			WHERE forumpermissionid = $forumpermissionid
		");
		if (!$getperms)
		{
			print_table_footer();
			print_stop_message('invalid_forum_permissions_specified');
		}
		$usergroup['title'] = $usergroupcache["$getperms[usergroupid]"]['title'];
		$forum['title'] = $forumcache["$getperms[forumid]"]['title'];
		construct_hidden_code('forumpermissionid', $forumpermissionid);
	}
	$forumpermission = convert_bits_to_array($getperms['forumpermissions'], $_BITFIELD['usergroup']['forumpermissions']);

	print_table_header(construct_phrase($vbphrase['edit_forum_permissions_for_usergroup_x_in_forum_y'], $usergroup['title'], $forum['title']));
	print_description_row('
		<label for="uug_1"><input type="radio" name="useusergroup" value="1" id="uug_1" onclick="this.form.reset(); this.checked=true;"' . iif(empty($forumpermissionid), ' checked="checked"') . ' />' . $vbphrase['use_default_permissions'] . '</label>
		<br />
		<label for="uug_0"><input type="radio" name="useusergroup" value="0" id="uug_0"' . iif(!empty($forumpermissionid), ' checked="checked"') . ' />' . $vbphrase['use_custom_permissions'] . '</label>
	', 0, 2, 'tfoot', '' , 'mode');
	print_table_break();
	print_forum_permission_rows($vbphrase['edit_forum_permissions'], $forumpermission, 'js_set_custom();');

	print_submit_row($vbphrase['save']);

}

// ###################### Start do update #######################
if ($_POST['do'] == 'doupdate')
{
	globalize($_POST, array(
		'forumpermissionid' => INT,
		'forumpermission',
		'useusergroup' => INT,
		'forumid' => INT,
	));

	// note: $getforum is called to get a forumid to jump to on the target page...
	$infoquery = "
		SELECT forum.forumid, forum.title AS forumtitle,usergroup.title AS grouptitle
		FROM " . TABLE_PREFIX . "forumpermission AS forumpermission
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = forumpermission.forumid)
		INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = forumpermission.usergroupid)
		WHERE forumpermissionid = $forumpermissionid
	";

	if ($useusergroup)
	{
		// use usergroup defaults. delete forumpermission if it exists
		if (!empty($forumpermissionid))
		{
			$info = $DB_site->query_first($infoquery);
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "forumpermission WHERE forumpermissionid = $forumpermissionid");
			build_forum_permissions();
			define('CP_REDIRECT', "forumpermission.php?do=modify&forumid=$info[forumid]#forum$info[forumid]");
			print_stop_message('deleted_forum_permissions_successfully');
		}
		else
		{
			build_forum_permissions();
			define('CP_REDIRECT', "forumpermission.php?do=modify&forumid=$forumid");
			print_stop_message('please_complete_required_fields');
		}
	}
	else
	{

		require_once('./includes/functions_misc.php');
		$querydata = array(
			'usergroupid' => $forumpermission['usergroupid'],
			'forumpermissions' => convert_array_to_bits($forumpermission, $_BITFIELD['usergroup']['forumpermissions'], 1)
		);

		if ($forumid)
		{
			$querydata['forumid'] = $forumid;
			$query = fetch_query_sql($querydata, 'forumpermission');
			$DB_site->query($query);

			$info['forumid'] = $forumid;
			$foruminfo = $DB_site->query_first("
				SELECT title
				FROM " . TABLE_PREFIX . "forum
				WHERE forumid = $forumid
			");
			$groupinfo = $DB_site->query_first("
				SELECT title
				FROM " . TABLE_PREFIX . "usergroup
				WHERE usergroupid = $forumpermission[usergroupid]
			");

			build_forum_permissions();
			define('CP_REDIRECT', "forumpermission.php?do=modify&forumid=$forumid");
			print_stop_message('saved_forum_permissions_successfully');
		}
		else
		{
			unset($querydata['usergroupid']);
			$query = fetch_query_sql($querydata, 'forumpermission', "WHERE forumpermissionid = $forumpermissionid");
			$DB_site->query($query);

			build_forum_permissions();

			$info = $DB_site->query_first($infoquery);
			define('CP_REDIRECT', "forumpermission.php?do=modify&forumid=$info[forumid]#forum$info[forumid]");
			print_stop_message('saved_forum_permissions_successfully');
		}
	}

}

// ###################### Start duplicator #######################
if ($_REQUEST['do'] == 'duplicate')
{
	$permgroups = $DB_site->query("
		SELECT usergroup.usergroupid, title, COUNT(forumpermission.forumpermissionid) AS permcount
		FROM " . TABLE_PREFIX . "usergroup AS usergroup
		LEFT JOIN " . TABLE_PREFIX . "forumpermission AS forumpermission ON (usergroup.usergroupid = forumpermission.usergroupid)
		GROUP BY usergroup.usergroupid
		HAVING permcount > 0
		ORDER BY title
	");
	$ugarr = array();
	while ($group = $DB_site->fetch_array($permgroups))
	{
		$ugarr["$group[usergroupid]"] = $group['title'];
	}

	$usergrouplist = array();
	foreach($usergroupcache AS $usergroup)
	{
		$usergrouplist[] = "<input type=\"checkbox\" name=\"usergrouplist[$usergroup[usergroupid]]\" value=\"1\" /> $usergroup[title]";
	}
	$usergrouplist = implode("<br />\n", $usergrouplist);

	print_form_header('forumpermission', 'doduplicate_group');
	print_table_header($vbphrase['usergroup_based_permission_duplicator']);
	print_select_row($vbphrase['copy_permissions_from_group'], 'ugid_from', $ugarr);
	print_label_row($vbphrase['copy_permissions_to_groups'], "<span class=\"smallfont\">$usergrouplist</span>", '', 'top', 'usergrouplist');
	print_forum_chooser('limitforumid', -1, $vbphrase['all_forums'], $vbphrase['only_copy_permissions_from_forum']);
	print_yes_no_row($vbphrase['overwrite_duplicate_entries'], 'overwritedupes_group', 0);
	print_yes_no_row($vbphrase['overwrite_inherited_entries'], 'overwriteinherited_group', 0);
	print_submit_row($vbphrase['go']);

	// generate forum check boxes
	$forumlist = array();
	foreach($forumcache AS $forum)
	{
		$depth = construct_depth_mark($forum['depth'], '--');
		$forumlist[] = "<input type=\"checkbox\" name=\"forumlist[$forum[forumid]]\" value=\"1\" tabindex=\"1\" />$depth $forum[title] ";
	}
	$forumlist = implode("<br />\n", $forumlist);

	print_form_header('forumpermission', 'doduplicate_forum');
	print_table_header($vbphrase['forum_based_permission_duplicator']);
	print_forum_chooser('forumid_from', -1, '', $vbphrase['copy_permissions_from_forum'], 0);
	print_label_row($vbphrase['copy_permissions_to_forums'], "<span class=\"smallfont\">$forumlist</span>", '', 'top', 'forumlist');
	//print_chooser_row($vbphrase['only_copy_permissions_from_group'], 'limitugid', 'usergroup', -1, $vbphrase['all_usergroups']);
	print_yes_no_row($vbphrase['overwrite_duplicate_entries'], 'overwritedupes_forum', 0);
	print_yes_no_row($vbphrase['overwrite_inherited_entries'], 'overwriteinherited_forum', 0);
	print_submit_row($vbphrase['go']);

}

// ###################### Start do duplicate (group-based) #######################
if ($_POST['do'] == 'doduplicate_group')
{
	globalize($_POST, array(
		'ugid_from' => INT,
		'limitforumid' => INT,
		'overwritedupes_group' => INT,
		'overwriteinherited_group' => INT,
		'usergrouplist',
	));

	$overwritedupes = &$overwritedupes_group;
	$overwriteinherited = &$overwriteinherited_group;

	if (!is_array($usergrouplist))
	{
		print_stop_message('invalid_usergroup_specified');
	}

	foreach ($usergrouplist AS $ugid_to => $confirm)
	{
		$ugid_to = intval($ugid_to);
		if ($ugid_from == $ugid_to OR $confirm != 1)
		{
			continue;
		}

		if ($limitforumid != -1)
		{
			$foruminfo = fetch_foruminfo($limitforumid);
			$forumsql = "AND forumpermission.forumid IN ($foruminfo[parentlist])";
		}
		else

		{
			$forumsql = '';
		}

		$existing = $DB_site->query("
			SELECT forumpermission.forumid, forum.parentlist
			FROM " . TABLE_PREFIX . "forumpermission AS forumpermission, " . TABLE_PREFIX . "forum AS forum
			WHERE forumpermission.forumid = forum.forumid AND usergroupid = $ugid_to $forumsql
		");
		$perm_set = array();
		while ($thisperm = $DB_site->fetch_array($existing))
		{
			$perm_set[] = $thisperm['forumid'];
		}

		$perm_inherited = array();
		if (sizeof($perm_set) > 0)
		{
			$inherits = $DB_site->query("
				SELECT forumid
				FROM " . TABLE_PREFIX . "forum
				WHERE CONCAT(',', parentlist, ',') LIKE '%," . implode(",%' OR CONCAT(',', parentlist, ',') LIKE '%,", $perm_set) . ",%'
			");
			while ($thisperm = $DB_site->fetch_array($inherits))
			{
				$perm_inherited[] = $thisperm['forumid'];
			}
		}

		if (!$overwritedupes OR !$overwriteinherited)
		{
			$exclude = array('0');
			if (!$overwritedupes)
			{
				$exclude = array_merge($exclude, $perm_set);
			}
			if (!$overwriteinherited)
			{
				$exclude = array_merge($exclude, $perm_inherited);
			}
			$exclude = array_unique($exclude);
			$forumsql .= ' AND forumpermission.forumid NOT IN (' . implode(',', $exclude) . ')';
		}

		$perms = $DB_site->query("
			SELECT forumid, forumpermissions
			FROM " . TABLE_PREFIX . "forumpermission AS forumpermission
			WHERE usergroupid = $ugid_from $forumsql
		");
		while ($thisperm = $DB_site->fetch_array($perms))
		{
			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "forumpermission
				(forumid, usergroupid, forumpermissions)
				VALUES
				($thisperm[forumid], $ugid_to, $thisperm[forumpermissions])
			");
		}
	}

	build_forum_permissions();

	define('CP_REDIRECT', 'forumpermission.php?do=modify');
	print_stop_message('duplicated_permissions_successfully');
}

// ###################### Start do duplicate (forum-based) #######################
if ($_POST['do'] == 'doduplicate_forum')
{
	globalize($_POST, array(
		'forumid_from' => INT,
		'overwritedupes_forum' => INT,
		'overwriteinherited_forum' => INT,
		'forumlist',
	));

	$overwritedupes = &$overwritedupes_forum;
	$overwriteinherited = &$overwriteinherited_forum;

	if (!is_array($forumlist))
	{
		print_stop_message('invalid_forum_specified');
	}

	$forumperms = $DB_site->query("
		SELECT usergroupid, forumpermissions
		FROM " . TABLE_PREFIX . "forumpermission
		WHERE forumid = $forumid_from
	");
	if ($DB_site->num_rows($forumperms) == 0)
	{
		print_stop_message('no_permissions_set');
	}
	$copyperms = array();
	while ($perm = $DB_site->fetch_array($forumperms))
	{
		$copyperms["$perm[usergroupid]"] = $perm['forumpermissions'];
	}

	$permscache = array();
	if (!$overwritedupes OR !$overwriteinherited)
	{
		// query forum permissions
		$forumpermissions = $DB_site->query("
			SELECT usergroupid, forum.forumid, IF(forumpermission.forumid = forum.forumid, 0, 1) AS inherited
			FROM " . TABLE_PREFIX . "forum AS forum, " . TABLE_PREFIX . "forumpermission AS forumpermission
			WHERE FIND_IN_SET(forumpermission.forumid, forum.parentlist)
		");
		// make permission cache
		while ($fperm = $DB_site->fetch_array($forumpermissions))
		{
			$permscache["$fperm[forumid]"]["$fperm[usergroupid]"] = $fperm['inherited'];
		}
	}

	foreach ($forumlist AS $forumid_to => $confirm)
	{
		$forumid_to = intval($forumid_to);
		if ($forumid_to == $forumid_from OR !$confirm)
		{
			continue;
		}
		foreach ($copyperms AS $usergroupid => $permissions)
		{
			if (!$overwritedupes AND isset($permscache["$forumid_to"]["$usergroupid"]) AND $permscache["$forumid_to"]["$usergroupid"] == 0)
			{
				continue;
			}
			if (!$overwriteinherited AND $permscache["$forumid_to"]["$usergroupid"] == 1)
			{
				continue;
			}
			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "forumpermission
				(forumid, usergroupid, forumpermissions)
				VALUES ($forumid_to, $usergroupid, $permissions)
			");
		}
	}

	build_forum_permissions();

	define('CP_REDIRECT', 'forumpermission.php?do=modify');
	print_stop_message('duplicated_permissions_successfully');
}

// ###################### Start quick edit #######################
if ($_REQUEST['do'] == 'quickedit')
{
	globalize($_REQUEST, array(
		'orderby' => STR
	));

	print_form_header('forumpermission', 'doquickedit');
	print_table_header($vbphrase['permissions_quick_editor'], 4);
	print_cells_row(array(
		'<input type="checkbox" name="allbox" title="' . $vbphrase['check_all'] . '" onclick="js_check_all(this.form);" />',
		"<a href=\"forumpermission.php?$session[sessionurl]do=quickedit&amp;orderby=forum\" title=\"" . $vbphrase['order_by_forum'] . "\">" . $vbphrase['forum'] . "</a>",
		"<a href=\"forumpermission.php?$session[sessionurl]do=quickedit&amp;orderby=usergroup\" title=\"" . $vbphrase['order_by_usergroup'] . "\">" . $vbphrase['usergroup'] . "</a>",
		$vbphrase['controls']
	), 1);
	$forumperms = $DB_site->query("
		SELECT forumpermissionid, usergroup.title AS ug_title, forum.title AS forum_title
		FROM " . TABLE_PREFIX . "forumpermission AS forumpermission,
		" . TABLE_PREFIX . "usergroup AS usergroup,
		" . TABLE_PREFIX . "forum AS forum
		WHERE forumpermission.usergroupid = usergroup.usergroupid AND
			forumpermission.forumid = forum.forumid
		" . iif($orderby == 'usergroup', 'ORDER BY ug_title, forum_title', 'ORDER BY forum_title, ug_title')
	);
	if ($DB_site->num_rows($forumperms) > 0)
	{
		while ($perm = $DB_site->fetch_array($forumperms))
		{
			print_cells_row(array("<input type=\"checkbox\" name=\"permission[$perm[forumpermissionid]]\" value=\"1\" tabindex=\"1\" />", $perm['forum_title'], $perm['ug_title'], construct_link_code($vbphrase['edit'], "forumpermission.php?$session[sessionurl]do=edit&amp;fp=$perm[forumpermissionid]")));
		}
		print_submit_row($vbphrase['delete_selected_permissions'], $vbphrase['reset'], 4);
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
	globalize($_POST, array(
		'permission'
	));

	if (!is_array($permission))
	{
		print_stop_message('nothing_to_do');
	}

	$removeids = '0';
	foreach ($permission AS $permissionid => $confirm)
	{
		if ($confirm == 1)
		{
			$removeids .= ", $permissionid";
		}
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "forumpermission WHERE forumpermissionid IN ($removeids)");

	build_forum_permissions();

	define('CP_REDIRECT', 'forumpermission.php?do=modify');
	print_stop_message('deleted_forum_permissions_successfully');
}

// ###################### Start quick forum setup #######################
if ($_REQUEST['do'] == 'quickforum')
{
	$usergrouplist = array();
	$usergroups = $DB_site->query("SELECT usergroupid, title FROM " . TABLE_PREFIX . "usergroup ORDER BY title");
	while ($usergroup = $DB_site->fetch_array($usergroups))
	{
		$usergrouplist[] = "<input type=\"checkbox\" name=\"usergrouplist[$usergroup[usergroupid]]\" value=\"1\" tabindex=\"1\" /> $usergroup[title]";
	}
	$usergrouplist = implode('<br />', $usergrouplist);

	print_form_header('forumpermission', 'doquickforum');
	print_table_header($vbphrase['quick_forum_permission_setup']);
	print_forum_chooser('forumid', -1, '', $vbphrase['apply_permissions_to_forum'], 0);
	print_label_row($vbphrase['apply_permissions_to_usergroup'], "<span class=\"smallfont\">$usergrouplist</span>", '', 'top', 'usergrouplist');
	print_description_row($vbphrase['permission_overwrite_notice']);

	print_table_break();
	print_forum_permission_rows($vbphrase['permissions']);
	print_submit_row();
}

// ###################### Start do quick forum #######################
if ($_POST['do'] == 'doquickforum')
{
	globalize($_POST, array(
		'usergrouplist',
		'forumid' => INT,
		'forumpermission'
	));

	if (!is_array($usergrouplist))
	{
		print_stop_message('invalid_usergroup_specified');
	}

	require_once('./includes/functions_misc.php');
	$permbits = convert_array_to_bits($forumpermission, $_BITFIELD['usergroup']['forumpermissions'], 1);
	foreach ($usergrouplist AS $usergroupid => $confirm)
	{
		if ($confirm == 1)
		{
			$usergroupid = intval($usergroupid);
			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "forumpermission
				(forumid, usergroupid, forumpermissions)
				VALUES ($forumid, $usergroupid, $permbits)
			");
		}
	}

	build_forum_permissions();

	define('CP_REDIRECT', 'forumpermission.php?do=modify&forumid=' . $forumid);
	print_stop_message('saved_forum_permissions_successfully');
}

// ###################### Start quick set #######################
if ($_REQUEST['do'] == 'quickset')
{
	globalize($_REQUEST, array(
		'forumid' => INT,
		'type' => STR,
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

	switch ($type)
	{
	case 'reset':
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "forumpermission WHERE forumid = $forumid");
		break;
	case 'deny':
		$groups = $DB_site->query("SELECT usergroupid FROM " . TABLE_PREFIX . "usergroup");
		while ($group = $DB_site->fetch_array($groups))
		{
			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "forumpermission
				(forumid, usergroupid, forumpermissions)
				VALUES ($forumid, $group[usergroupid], 0)
			");
		}
		break;
	default:
		print_stop_message('invalid_quick_set_action');
	}

	build_forum_permissions();

	define('CP_REDIRECT', 'forumpermission.php?do=modify&forumid=' . $forumid);
	print_stop_message('saved_forum_permissions_successfully');
}

// ###################### Start fpgetstyle #######################
function fetch_forumpermission_style($permissions)
{
	if (!($permissions & CANVIEW))
	{
		return " style=\"{$color}list-style-type:circle;\"";
	}
	else
	{
		return '';
	}
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	print_form_header('', '');
	print_table_header($vbphrase['additional_functions']);
	print_description_row("<b><a href=\"forumpermission.php?$session[sessionurl]do=duplicate\">" . $vbphrase['permission_duplication_tools'] . "</a> | <a href=\"forumpermission.php?$session[sessionurl]do=quickedit\">" . $vbphrase['permissions_quick_editor'] . "</a> | <a href=\"forumpermission.php?$session[sessionurl]do=quickforum\">" . $vbphrase['quick_forum_permission_setup'] . "</a></b>", 0, 2, '', 'center');
	print_table_footer();

	print_form_header('', '');
	print_table_header($vbphrase['forum_permissions']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="col-g">' . $vbphrase['standard_using_default_usergroup_permissions'] . '</li>
		<li class="col-c">' . $vbphrase['customized_using_custom_permissions_for_this_usergroup'] . '</li>
		<li class="col-i">' . $vbphrase['inherited_using_custom_permissions_inherited_from_a_parent_forum'] . '</li>
		</ul></div>
	');
	print_table_footer();

	require_once('./includes/functions_forumlist.php');

	// get forum orders
	cache_ordered_forums(0, 1);

	// get moderators
	cache_moderators();

	// query forum permissions
	$fpermscache = array();
	$forumpermissions = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "forumpermission");
	while ($fp = $DB_site->fetch_array($forumpermissions))
	{
		$fpermscache["$fp[forumid]"]["$fp[usergroupid]"] = $fp;
	}

	// get usergroup default permissions
	$permissions = array();
	foreach($usergroupcache AS $usergroupid => $usergroup)
	{
		$permissions["$usergroupid"] = $usergroup['forumpermissions'];
	}

?>
<center>
<div class="tborder" style="width: 89%">
<div class="alt1" style="padding: 8px">
<div class="darkbg" style="padding: 4px; border: 2px inset; text-align: <?php echo $stylevar['left']; ?>">
<?php

	// run the display function
	if ($vboptions['cp_collapse_forums'])
	{
?>
	<script type="text/javascript">
	<!--
	function js_forum_jump(forumid)
	{
		if (forumid > 0)
		{
			window.location = 'forumpermission.php?do=modify&forumid=' + forumid;
		}
	}
	-->
	</script>
		<?php
		define('ONLYID', intval($_GET['forumid']));

		$select = '<div align="center"><select name="forumid" id="sel_forumid" tabindex="1" class="bginput" onchange="js_forum_jump(this.options[selectedIndex].value);">';
		$select .= construct_forum_chooser(ONLYID, true);
		$select .= "</select></div>\n";
		echo $select;

		print_forums($permissions, array(), -1);
	}
	else
	{
		print_forums($permissions, array(), -1);
	}

?>
</div>
</div>
</div>
</center>
<?php

}

// ###################### Start displayforums #######################
function print_forums($permissions, $inheritance = array(), $parentid = -1, $indent = '	')
{
	global $DB_site, $iforumcache, $forumcache, $imodcache, $permscache, $usergroupcache, $session, $vbphrase, $cpsession;

	global $iforumcache, $forumcache, $imodcache, $fpermscache, $usergroupcache, $session, $vbphrase;

	// check to see if this forum actually exists / has children
	if (!isset($iforumcache["$parentid"]))
	{
		return;
	}

	foreach ($iforumcache["$parentid"] AS $displayorders)
	{
		if (!defined('ONLYID'))
		{
			echo "$indent<ul class=\"lsq\">\n";
		}

		foreach ($displayorders AS $forumid)
		{
			// get current forum info
			$forum = &$forumcache["$forumid"];

			// make a copy of the current permissions set up
			$perms = $permissions;

			// make a copy of the inheritance set up
            $inherit = $inheritance;

			if ($forumid == ONLYID)
			{
				echo "$indent<ul class=\"lsq\">\n";
			}

			// echo forum title and links
			if ($forumid == ONLYID OR !defined('ONLYID'))
			{
				echo "$indent<li><b><a name=\"forum$forumid\" href=\"forum.php?$session[sessionurl]do=edit&amp;forumid=$forumid\">$forum[title]</a> <span class=\"smallfont\">(" . construct_link_code($vbphrase['reset'], "forumpermission.php?$session[sessionurl]do=quickset&amp;type=reset&amp;forumid=$forumid&amp;hash=$cpsession[hash]") . construct_link_code($vbphrase['deny_all'], "forumpermission.php?$session[sessionurl]do=quickset&amp;type=deny&amp;forumid=$forumid&amp;hash=$cpsession[hash]") . ")</span></b>";

				// get moderators
				if (is_array($imodcache["$forumid"]))
				{
					echo "<span class=\"smallfont\"><br /> - <i>" . $vbphrase['moderators'] . ":";
					foreach($imodcache["$forumid"] AS $moderator)
					{
						// moderator username and links
						echo " <a href=\"moderator.php?$session[sessionurl]do=edit&amp;moderatorid=$moderator[moderatorid]\">$moderator[username]</a>";
					}
					echo "</i></span>";
				}

				echo "$indent\t<ul class=\"usergroups\">\n";
			}
			foreach($usergroupcache AS $usergroupid => $usergroup)
			{
				if ($inherit["$usergroupid"] == 'col-c')
				{
					$inherit["$usergroupid"] = 'col-i';
				}

				// if there is a custom permission for the current usergroup, use it
				if (isset($fpermscache["$forumid"]["$usergroupid"]))
				{
					$inherit["$usergroupid"] = 'col-c';
					$perms["$usergroupid"] = $fpermscache["$forumid"]["$usergroupid"]['forumpermissions'];
					$fplink = 'fp=' . $fpermscache["$forumid"]["$usergroupid"]['forumpermissionid'];
				}
				else
				{
					$fplink = "f=$forumid&amp;u=$usergroupid";
				}

				// work out display style
				$liStyle = '';
				if (isset($inherit["$usergroupid"]))
				{
					$liStyle = " class=\"$inherit[$usergroupid]\"";
				}
				if (!($perms["$usergroupid"] & CANVIEW))
				{
					$liStyle .= " style=\"list-style:circle\"";
				}
				if ($forumid == ONLYID OR !defined('ONLYID'))
				{
					echo "$indent\t<li$liStyle>" . construct_link_code($vbphrase['edit'], "forumpermission.php?$session[sessionurl]do=edit&amp;$fplink") . $usergroup['title'] . "</li>\n";
				}
			}
			if ($forumid == ONLYID OR !defined('ONLYID'))
			{
				echo "$indent\t</ul><br />\n";
			}

			if ($forumid == ONLYID AND defined('ONLYID'))
			{
				echo "$indent</li>\n";
				echo "$indent</ul>\n";
				return;
			}
			print_forums($perms, $inherit, $forumid, "$indent	");
			if ($forumid == ONLYID OR !defined('ONLYID'))
			{
				echo "$indent</li>\n";
			}
		}
		unset($inherit);
		if ($forumid == ONLYID OR !defined('ONLYID'))
		{
			echo "$indent</ul>\n";
		}

		if ($forum['parentid'] == -1 AND !defined('ONLYID'))
		{
			echo "<hr size=\"1\" />\n";
		}
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12738 $
|| ####################################################################
\*======================================================================*/
?>