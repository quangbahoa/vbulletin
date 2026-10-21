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
define('CVS_REVISION', '$RCSfile$ - $Revision: 9439 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cppermission');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['administrator_permissions_manager']);

if (!in_array($bbuserinfo['userid'], preg_split('#\s*,\s*#s', $superadministrators, -1, PREG_SPLIT_NO_EMPTY)))
{
	print_stop_message('sorry_you_are_not_allowed_to_edit_admin_permissions');
}

// ############################# LOG ACTION ###############################
globalize($_REQUEST, array('userid' => INT));

if ($userid)
{
	$user = $DB_site->query_first("
		SELECT user.userid, user.username, administrator.adminpermissions, administrator.cssprefs,
		IF(administrator.userid IS NULL, 0, 1) AS isadministrator
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "administrator AS administrator ON(administrator.userid = user.userid)
		WHERE user.userid = $userid
	");

	if (!$user)
	{
		print_stop_message('no_matches_found');
	}
	else if (!$user['isadministrator'])
	{
		print_stop_message('invalid_user_specified');
	}
}
else
{
	$user = array();
}

$ADMINPERMISSIONS = $_BITFIELD['usergroup']['adminpermissions'];
unset($ADMINPERMISSIONS['ismoderator'], $ADMINPERMISSIONS['cancontrolpanel']);

require_once('./includes/functions_misc.php');
log_admin_action(iif($user, "user id = $userid ($user[username])" . iif($_POST['do'] == 'update', " ($_POST[oldpermissions] &raquo; " . convert_array_to_bits($_POST['adminpermissions'], $ADMINPERMISSIONS) . ")")));

// #############################################################################

$permsphrase = array(
	'canadminsettings'		=> $vbphrase['can_administer_settings'],
	'canadminstyles'		=> $vbphrase['can_administer_styles'],
	'canadminlanguages'		=> $vbphrase['can_administer_languages'],
	'canadminforums'		=> $vbphrase['can_administer_forums'],
	'canadminthreads'		=> $vbphrase['can_administer_threads'],
	'canadmincalendars'		=> $vbphrase['can_administer_calendars'],
	'canadminusers'			=> $vbphrase['can_administer_users'],
	'canadminpermissions'	=> $vbphrase['can_administer_user_permissions'],
	'canadminfaq'			=> $vbphrase['can_administer_faq'],
	'canadminimages'		=> $vbphrase['can_administer_images'],
	'canadminbbcodes'		=> $vbphrase['can_administer_bbcodes'],
	'canadmincron'			=> $vbphrase['can_administer_cron'],
	'canadminmaintain'		=> $vbphrase['can_run_maintenance'],
	'canadminupgrade'		=> $vbphrase['can_run_upgrades']
);

// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// #############################################################################

if ($_POST['do'] == 'update')
{
	globalize($_POST, array('adminpermissions', 'cssprefs' => STR));

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "administrator SET
			adminpermissions = " . convert_array_to_bits($adminpermissions, $ADMINPERMISSIONS) . ",
			cssprefs = '" . addslashes($cssprefs) . "'
		WHERE userid = $userid
	");

	define('CP_REDIRECT', "adminpermissions.php?$session[sessionurl]#user$user[userid]");
	print_stop_message('saved_administrator_permissions_successfully');
}

// #############################################################################

if ($_REQUEST['do'] == 'edit')
{
	print_form_header('adminpermissions', 'update');
	construct_hidden_code('userid', $userid);
	construct_hidden_code('oldpermissions', $user['adminpermissions']);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['administrator_permissions'], $user['username'], $user['userid']));
	print_label_row("$vbphrase[administrator]: <a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$userid\">$user[username]</a>", '<div align="' . $stylevar['right'] .'"><input type="button" class="button" value=" ' . $vbphrase['all_yes'] . ' " onclick="js_check_all_option(this.form, 1);" /> <input type="button" class="button" value=" ' . $vbphrase['all_no'] . ' " onclick="js_check_all_option(this.form, 0);" /></div>', 'thead');

	foreach(convert_bits_to_array($user['adminpermissions'], $ADMINPERMISSIONS) AS $field => $value)
	{
		if ($field == 'canadminupgrade')
		{
			construct_hidden_code("adminpermissions[$field]", $value);
		}
		else
		{
			print_yes_no_row($permsphrase["$field"], "adminpermissions[$field]", $value);
		}
	}

	print_select_row($vbphrase['control_panel_style_choice'], 'cssprefs', array_merge(array('' => "($vbphrase[default])"), fetch_cpcss_options()), $user['cssprefs']);

	print_submit_row();
}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{
	print_form_header('adminpermissions', 'edit');
	print_table_header($vbphrase['administrator_permissions'], 3);

	$users = $DB_site->query("
		SELECT user.username, usergroupid, membergroupids, administrator.*
		FROM " . TABLE_PREFIX . "administrator AS administrator
		INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		ORDER BY user.username
	");
	while ($user = $DB_site->fetch_array($users))
	{
		$perms = fetch_permissions(0, $user['userid'], $user);
		if ($perms['adminpermissions'] & CANCONTROLPANEL)
		{
			print_cells_row(array(
				"<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$user[userid]\" name=\"user$user[userid]\"><b>$user[username]</b></a>",
				'-',
				construct_link_code($vbphrase['view_control_panel_log'], "adminlog.php?$session[sessionurl]do=view&script=&userid=$user[userid]") .
				construct_link_code($vbphrase['edit_permissions'], "adminpermissions.php?$session[sessionurl]do=edit&amp;userid=$user[userid]")
			), 0, '', 0);
		}
	}

	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9439 $
|| ####################################################################
\*======================================================================*/
?>