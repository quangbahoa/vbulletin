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

error_reporting(E_ALL & ~E_NOTICE);

define('THIS_SCRIPT', 'upgrade13.php');
define('VERSION', '3.0.2');
define('PREV_VERSION', '3.0.1');

$phrasegroups = array();
$specialtemplates = array();

// #############################################################################
// require the code that makes it all work...
require_once('./upgradecore.php');

// #############################################################################
// welcome step
if ($step == 'welcome')
{
	if ($vboptions['templateversion'] == PREV_VERSION)
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
if ($step == 1)
{
	// remove useless field
	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "usergroup DROP pmpermissions_bak");
	$DB_site->reporterror = 1;

	// Add an index to the moderatorlog table
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "moderatorlog ADD INDEX (threadid)";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'moderatorlog', 1, 1);

	// Add an index to the reputation table
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "reputation ADD INDEX (dateline)";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'reputation', 1, 1);

	// Add an index to the usergroupleader table
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "usergroupleader ADD index ugl (userid, usergroupid)";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'usergroupleader', 1, 1);

	// Add lastvote date to poll table to support better read status of bumping of polls on vote
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "poll ADD lastvote INT UNSIGNED NOT NULL";
	$explain[] = $upgrade_phrases['upgrade13.php']['alter_poll_table'];

	exec_queries();
}

// #############################################################################
// alters #2
if ($step == 2)
{
	// Add thumbnail filesize so we know what it is when thumbnails are in the FS
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment ADD thumbnail_filesize INT UNSIGNED NOT NULL";
	$explain[] = $upgrade_phrases['upgrade13.php']['add_thumbnail_filesize'];

	// Once again make sure the attachmentid is INT as it might still be SMALLINT from vB2
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE attachmentid attachmentid INT UNSIGNED NOT NULL AUTO_INCREMENT";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'attachment', 1, 1);

	// Make email column longer
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user CHANGE email email CHAR(100) NOT NULL DEFAULT ''";
	$explain[] = $upgrade_phrases['upgrade13.php']['alter_user_table'];

	exec_queries();
}

// #############################################################################
// alters #3
if ($step == 3)
{
	if (defined('CANSEEHIDDENCUSTOMFIELDS'))
	{
		// Update genericpermissions with new 'Can See Hidden Profile Fields' and Reputation Permissions
		$query[] = "
			UPDATE " . TABLE_PREFIX . "usergroup SET
				genericpermissions = genericpermissions + " . CANSEEHIDDENCUSTOMFIELDS . "
			WHERE NOT (genericpermissions & " . CANSEEHIDDENCUSTOMFIELDS . ") AND (adminpermissions & " . CANCONTROLPANEL . " OR adminpermissions & " . ISMODERATOR . ")
		";
		$explain[] = $upgrade_phrases['upgrade13.php']['update_genericpermissions'];

		$query[] = "
			UPDATE " . TABLE_PREFIX . "usergroup SET
				genericpermissions = genericpermissions + " . (CANUSEREP + CANNEGATIVEREP) . "
			WHERE usergroupid NOT IN (1, 3, 4)
			AND NOT (genericpermissions & " . CANUSEREP . ")
			AND NOT (genericpermissions & " . CANNEGATIVEREP . ")
			AND NOT (genericoptions & " . ISBANNEDGROUP . ")
			";
		$explain[] = $upgrade_phrases['upgrade13.php']['update_genericpermissions'];
	}

	// Change profilefields from char/varchar to mediumtext
	$customfields = $DB_site->query("
		SHOW columns FROM " . TABLE_PREFIX . "userfield
	");

	while ($customfield = $DB_site->fetch_array($customfields))
	{
		if(preg_match('#^(temp)|(field[0-9]+)$#', $customfield['Field']))
		{
			$query[] = "ALTER TABLE " . TABLE_PREFIX . "userfield CHANGE $customfield[Field] $customfield[Field] MEDIUMTEXT NOT NULL";
			$explain[] = $upgrade_phrases['upgrade13.php']['change_profilefield'];
		}
	}

	// add faq entry if group still exists
	$groupexists = $DB_site->query_first("SELECT faqname FROM " . TABLE_PREFIX . "faq WHERE faqname = 'vb_board_usage'");
	if (!empty($groupexists['faqname']))
	{
		$query[] = "INSERT IGNORE INTO " . TABLE_PREFIX . "faq (faqname, faqparent, displayorder, volatile) VALUES ('vb_rss_syndication', 'vb_board_usage', 10, 1)";
		$explain[] = $upgrade_phrases['upgrade13.php']['add_rss_faq'];
	}

	exec_queries();

	// rebuild permissions for the permissions updated above
	build_forum_permissions();
}

// #############################################################################
// alters #4
if ($step == 4)
{
	if (!$DB_site->query_first("SHOW COLUMNS FROM " . TABLE_PREFIX . "administrator LIKE 'notes'"))
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "administrator ADD notes MEDIUMTEXT NOT NULL";
		$explain[] = $upgrade_phrases['upgrade13.php']['add_notes'];
	}

	$query[] = "CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "cpsession (
		userid INT UNSIGNED NOT NULL DEFAULT '0',
		hash VARCHAR(32) NOT NULL DEFAULT '',
		dateline INT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (userid, hash)
	) TYPE = HEAP";
	$explain[] = $upgrade_phrases['upgrade13.php']['add_cpsession_table'];

	$query[] = "UPDATE " . TABLE_PREFIX . "language SET charset = '" . addslashes($upgrade_phrases['upgrade1.php']['master_language_charset']) . "' WHERE charset = ''";
	$explain[] = $upgrade_phrases['upgrade13.php']['fix_blank_charset'];

	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "thread ADD INDEX pollid (pollid)");
	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "thread DROP INDEX userid");
	$DB_site->reporterror = 1;

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "pollvote ADD INDEX pollid (pollid, userid)";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'pollvote', 1, 1);

	exec_queries();
}

// #############################################################################
// import vbulletin options
if ($step == 5)
{
	require_once('./includes/adminfunctions_options.php');

	if (!($xml = file_read('./install/vbulletin-settings.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-settings.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-settings.xml');

	xml_import_settings($xml);

	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// import admin help
if ($step == 6)
{
	require_once('./includes/adminfunctions_help.php');

	if (!($xml = file_read('./install/vbulletin-adminhelp.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-adminhelp.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-adminhelp.xml');

	xml_import_help_topics($xml);

	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// import language
if ($step == 7)
{
	require_once('./includes/adminfunctions_language.php');

	if (!($xml = file_read('./install/vbulletin-language.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-language.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-language.xml');

	xml_import_language($xml);
	build_language();

	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// import style
if ($step == 8)
{
	require_once('./includes/adminfunctions_template.php');

	if (!($xml = file_read('./install/vbulletin-style.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-style.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-style.xml');

	xml_import_style($xml);
	define('NO_POST_EDITOR_BUILD', true);
	build_all_styles();

	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}
// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($step == 9)
{
	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15453 $
|| ####################################################################
\*======================================================================*/
?>