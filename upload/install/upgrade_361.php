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

define('THIS_SCRIPT', 'upgrade_361.php');
define('VERSION', '3.6.1');
define('PREV_VERSION', '3.6.0');

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
	if (!$upgrade->field_exists('infractionlevel', 'extend'))
	{
		$avatarids = array();
		$avatars = $db->query_read("
			SELECT userid
			FROM " . TABLE_PREFIX . "customavatar
		");
		while ($avatar = $db->fetch_array($avatars))
		{
			$avatarids[] = $avatar['userid'];
		}

		$profilepicids = array();
		$profilepics = $db->query_read("
			SELECT userid
			FROM " . TABLE_PREFIX . "customprofilepic
		");
		while ($profilepic = $db->fetch_array($profilepics))
		{
			$profilepicids[] = $profilepic['userid'];
		}

		if ($avatarids)
		{
			$upgrade->run_query(
				sprintf($vbphrase['update_table'], TABLE_PREFIX . "user"),
				"UPDATE " . TABLE_PREFIX . "user
				SET adminoptions = adminoptions | " . $vbulletin->bf_misc_adminoptions['adminavatar'] . "
				WHERE userid IN (" . implode(',', $avatarids) . ")"
			);
		}

		if ($profilepicids)
		{
			$upgrade->run_query(
				sprintf($vbphrase['update_table'], TABLE_PREFIX . "user"),
				"UPDATE " . TABLE_PREFIX . "user
				 SET adminoptions = adminoptions | " . $vbulletin->bf_misc_adminoptions['adminprofilepic'] . "
				 WHERE userid IN (" . implode(',', $profilepicids) . ")"
			);
		}
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'infractionlevel', 1, 1),
		'infractionlevel',
		'extend',
		'smallint',
		FIELD_DEFAULTS
	);

	if (!$upgrade->field_exists('podcastitem', 'explicit'))
	{
		$upgrade->add_field(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'podcasturl', 1, 4),
			'podcasturl',
			'explicit',
			'smallint',
			FIELD_DEFAULTS
		);

		$upgrade->add_field(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'podcasturl', 2, 4),
			'podcasturl',
			'keywords',
			'varchar',
			array('length' => 255, 'attributes' => FIELD_DEFAULTS)
		);

		$upgrade->add_field(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'podcasturl', 3, 4),
			'podcasturl',
			'subtitle',
			'varchar',
			array('length' => 255, 'attributes' => FIELD_DEFAULTS)
		);

		$upgrade->add_field(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'podcasturl', 4, 4),
			'podcasturl',
			'author',
			'varchar',
			array('length' => 255, 'attributes' => FIELD_DEFAULTS)
		);

		$upgrade->run_query(
			 $upgrade_phrases['upgrade_360b1.php']['rename_podcasturl'],
			 "ALTER TABLE " . TABLE_PREFIX . "podcasturl RENAME " . TABLE_PREFIX . "podcastitem",
			 MYSQL_ERROR_TABLE_MISSING
		);
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'administrator', 1, 1),
		'administrator',
		'dismissednews',
		'text',
		FIELD_DEFAULTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "infractionban"),
		"CREATE TABLE " . TABLE_PREFIX . "infractionban (
			infractionbanid int unsigned NOT NULL auto_increment,
			usergroupid int NOT NULL DEFAULT '0',
			banusergroupid int unsigned NOT NULL DEFAULT '0',
			amount int unsigned NOT NULL DEFAULT '0',
			period char(5) NOT NULL DEFAULT '',
			method enum('points','infractions') NOT NULL default 'infractions',
			PRIMARY KEY (infractionbanid),
			KEY usergroupid (usergroupid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->execute();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 2)
{
	$changed_strip = false;

	if (strpos($vbulletin->options['blankasciistrip'], 'u8204') === false)
	{
		$vbulletin->options['blankasciistrip'] .= ' u8204 u8205';
		$changed_strip = true;
	}
	if (strpos($vbulletin->options['blankasciistrip'], 'u8237') === false)
	{
		$vbulletin->options['blankasciistrip'] .= ' u8237 u8238';
		$changed_strip = true;
	}

	if ($changed_strip)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "setting SET
				value = '" . $db->escape_string($vbulletin->options['blankasciistrip']) . "'
			WHERE varname = 'blankasciistrip'
		");
	}

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
