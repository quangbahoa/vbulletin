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

define('THIS_SCRIPT', 'upgrade_302.php');
define('VERSION', '3.0.2');
define('PREV_VERSION', '3.0.1');

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
// alters #1
if ($vbulletin->GPC['step'] == 1)
{
	// remove useless field
	$db->hide_errors();
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "usergroup DROP pmpermissions_bak");
	$db->show_errors();

	// Add an index to the moderatorlog table
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "moderatorlog ADD INDEX (threadid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderatorlog', 1, 1);

	// Add an index to the reputation table
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "reputation ADD INDEX (dateline)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'reputation', 1, 1);

	// Add an index to the usergroupleader table
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "usergroupleader ADD index ugl (userid, usergroupid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroupleader', 1, 1);

	// Add lastvote date to poll table to support better read status of bumping of polls on vote
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "poll ADD lastvote INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = $upgrade_phrases['upgrade_302.php']['alter_poll_table'];

	exec_queries();
}

// #############################################################################
// alters #2
if ($vbulletin->GPC['step'] == 2)
{
	// Add thumbnail filesize so we know what it is when thumbnails are in the FS
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment ADD thumbnail_filesize INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = $upgrade_phrases['upgrade_302.php']['add_thumbnail_filesize'];

	// Once again make sure the attachmentid is INT as it might still be SMALLINT from vB2
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE attachmentid attachmentid INT UNSIGNED NOT NULL AUTO_INCREMENT";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 1, 1);

	// Make email column longer
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user CHANGE email email CHAR(100) NOT NULL DEFAULT ''";
	$explain[] = $upgrade_phrases['upgrade_302.php']['alter_user_table'];

	exec_queries();
}

// #############################################################################
// alters #3
if ($vbulletin->GPC['step'] == 3)
{

	// Update genericpermissions with new 'Can See Hidden Profile Fields' and Reputation Permissions
	$query[] = "
		UPDATE " . TABLE_PREFIX . "usergroup SET
			genericpermissions = genericpermissions + " . $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields'] . "
		WHERE NOT (genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields'] . ") AND
			(adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] . " OR
				adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ")
	";
	$explain[] = $upgrade_phrases['upgrade_302.php']['update_genericpermissions'];

	$query[] = "
		UPDATE " . TABLE_PREFIX . "usergroup SET
			genericpermissions = genericpermissions + " . (
				$vbulletin->bf_ugp_genericpermissions['canuserep'] +
				$vbulletin->bf_ugp_genericpermissions['cannegativerep']
			) . "
		WHERE usergroupid NOT IN (1, 3, 4)
		AND NOT (genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canuserep'] . ")
		AND NOT (genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['cannegativerep'] . ")
		AND NOT (genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
		";
	$explain[] = $upgrade_phrases['upgrade_302.php']['update_genericpermissions'];

	// Change profilefields from char/varchar to mediumtext
	$customfields = $db->query_read("
		SHOW columns FROM " . TABLE_PREFIX . "userfield
	");

	while ($customfield = $db->fetch_array($customfields))
	{
		if(preg_match('#^(temp)|(field[0-9]+)$#', $customfield['Field']))
		{
			$query[] = "ALTER TABLE " . TABLE_PREFIX . "userfield CHANGE $customfield[Field] $customfield[Field] MEDIUMTEXT NOT NULL";
			$explain[] = $upgrade_phrases['upgrade_302.php']['change_profilefield'];
		}
	}

	// add faq entry if group still exists
	$groupexists = $db->query_first("SELECT faqname FROM " . TABLE_PREFIX . "faq WHERE faqname = 'vb_board_usage'");
	if (!empty($groupexists['faqname']))
	{
		$query[] = "INSERT IGNORE INTO " . TABLE_PREFIX . "faq (faqname, faqparent, displayorder, volatile) VALUES ('vb_rss_syndication', 'vb_board_usage', 10, 1)";
		$explain[] = $upgrade_phrases['upgrade_302.php']['add_rss_faq'];
	}

	exec_queries();

	// rebuild permissions for the permissions updated above
	build_forum_permissions();
}

// #############################################################################
// alters #4
if ($vbulletin->GPC['step'] == 4)
{
	if (!$db->query_first("SHOW COLUMNS FROM " . TABLE_PREFIX . "administrator LIKE 'notes'"))
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "administrator ADD notes MEDIUMTEXT";
		$explain[] = $upgrade_phrases['upgrade_302.php']['add_notes'];
	}

	$query[] = "CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "cpsession (
		userid INT UNSIGNED NOT NULL DEFAULT '0',
		hash VARCHAR(32) NOT NULL DEFAULT '',
		dateline INT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (userid, hash)
	) ENGINE = MEMORY";
	$explain[] = $upgrade_phrases['upgrade_302.php']['add_cpsession_table'];

	$query[] = "UPDATE " . TABLE_PREFIX . "language SET charset = '" . $db->escape_string($upgrade_phrases['upgrade_300b3.php']['master_language_charset']) . "' WHERE charset = ''";
	$explain[] = $upgrade_phrases['upgrade_302.php']['fix_blank_charset'];

	$db->hide_errors();
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "thread ADD INDEX pollid (pollid)");
	$db->show_errors();

	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "pollvote DROP INDEX userid";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pollvote', 1, 2);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "pollvote ADD INDEX pollid (pollid, userid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pollvote', 2, 2);

	exec_queries();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 5)
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
