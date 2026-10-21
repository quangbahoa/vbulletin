<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

define('THIS_SCRIPT', 'upgrade_360b2.php');
define('VERSION', '3.6.0 Beta 2');
define('PREV_VERSION', '3.6.0 Beta 1');

$phrasegroups = array();
$specialtemplates = array();

// #############################################################################
// require the code that makes it all work...
require_once('./upgradecore.php');

// #############################################################################
// welcome step
if ($vbulletin->GPC['step'] == 'welcome')
{
	if ($vbulletin->options['templateversion'] == PREV_VERSION)
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$vbphrase[upgrade_start_message]";
		echo "<p>&nbsp;</p></blockquote>";
	}
	else
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$vbphrase[upgrade_wrong_version]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}
}

// #############################################################################
if ($vbulletin->GPC['step'] == 1)
{
	if (!$upgrade->field_exists('adminmessage', 'adminmessageid'))
	{
		require_once(DIR . '/includes/class_bitfield_builder.php');
		vB_Bitfield_Builder::save($db);

		// Make sure to zero out permissions from possible past usage
		$newperms = array(
			'genericpermissions' => array(
				$vbulletin->bf_ugp_genericpermissions['cangivearbinfraction'],
		));

		foreach ($newperms AS $permission => $permissions)
		{
			$upgrade->run_query(
				sprintf($vbphrase['update_table'], TABLE_PREFIX . "usergroup"),
				"UPDATE " . TABLE_PREFIX . "usergroup SET $permission = $permission & ~" . (array_sum($permissions))
			);
		}
		// give arbitrary infraction perms to admins
		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET genericpermissions = genericpermissions | " . $vbulletin->bf_ugp_genericpermissions['cangivearbinfraction'] ."
			WHERE adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
		);
	}

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "adminmessage"),
		"CREATE TABLE " . TABLE_PREFIX . "adminmessage (
			adminmessageid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			varname varchar(250) NOT NULL DEFAULT '',
			dismissable SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			script varchar(50) NOT NULL DEFAULT '',
			action varchar(20) NOT NULL DEFAULT '',
			execurl mediumtext NOT NULL,
			method enum('get','post') NOT NULL DEFAULT 'post',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			status enum('undone','done','dismissed') NOT NULL default 'undone',
			statususerid INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (adminmessageid),
			KEY script_action (script, action)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->execute(false);

	$rows = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "adminmessage");
	if ($rows['count'] == 0)
	{
		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . "adminmessage"),
			"INSERT INTO " . TABLE_PREFIX . "adminmessage
				(varname, dismissable, script, action, execurl, method, dateline, status)
			VALUES
				('after_upgrade_36_update_counters', 1, 'misc.php', 'updatethread', 'misc.php?do=updatethread', 'get', " . TIMENOW . ", 'undone')
			"
		);
	}

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'adminlog', 1, 1),
		'adminlog',
		'script_action',
		array('script', 'action')
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachment', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachmenttype', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "attachmenttype CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachmentpermission', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "attachmentpermission CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
	);

	// Make sure we have sigpicrevision (missing from beta 1 mysql-schema)
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 1),
		'user',
		'sigpicrevision',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'infraction', 1, 1),
		'infraction',
		'customreason',
		'varchar',
		array('length' => 255, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->execute();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 2)
{
	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
