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

define('THIS_SCRIPT', 'upgrade_300b4.php');
define('VERSION', '3.0.0 Beta 4');
define('PREV_VERSION', '3.0.0 Beta 3');

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
// step 1
if ($vbulletin->GPC['step'] == 1)
{
	// rename log_upgrade_step to upgradelog if it was named incorrectly
	$db->hide_errors();
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "log_upgrade_step RENAME " . TABLE_PREFIX . "upgradelog");
	$db->show_errors();

	// alter calendarcustomfield
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "calendarcustomfield
		ADD description MEDIUMTEXT";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "calendarcustomfield");

	// alter user
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user
		CHANGE timezoneoffset timezoneoffset CHAR(4) NOT NULL DEFAULT ''";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "user");

	// alter session
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "session
		ADD bypass SMALLINT NOT NULL DEFAULT '0',
		CHANGE useragent useragent VARCHAR(100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "session");

	// alter event
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "event
		CHANGE recuroption recuroption CHAR(6) NOT NULL DEFAULT ''";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "event");

	// alter administrator
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "administrator
		ADD navprefs VARCHAR(250) NOT NULL DEFAULT '',
		ADD cssprefs VARCHAR(250) NOT NULL DEFAULT ''";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "administrator");

	// alter usergroup
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "usergroup
		CHANGE avatarmaxsize avatarmaxsize INT UNSIGNED NOT NULL DEFAULT '0',
		CHANGE profilepicmaxsize profilepicmaxsize INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "usergroup");


	// drop replacement
	$query[] = "DROP TABLE IF EXISTS " . TABLE_PREFIX . "replacement";
	$explain[] = sprintf($vbphrase['remove_table'], TABLE_PREFIX . "replacement");

	// drop replacementset
	$query[] = "DROP TABLE IF EXISTS " . TABLE_PREFIX . "replacementset";
	$explain[] = sprintf($vbphrase['remove_table'], TABLE_PREFIX . "replacementset");

	// drop faqset
	$query[] = "DROP TABLE IF EXISTS " . TABLE_PREFIX . "faqset";
	$explain[] = sprintf($vbphrase['remove_table'], TABLE_PREFIX . "faqset");

	// drop help
	$query[] = "DROP TABLE IF EXISTS " . TABLE_PREFIX . "help";
	$explain[] = sprintf($vbphrase['remove_table'], TABLE_PREFIX . "help");

	exec_queries();
}

// #############################################################################
// step 2
if ($vbulletin->GPC['step'] == 2)
{
	$avatargroupexists = $db->query_first("SELECT imagecategoryid FROM " . TABLE_PREFIX . "imagecategory WHERE imagetype = 1");
	if (!$avatargroupexists)
	{
		$query[] = "INSERT INTO " . TABLE_PREFIX . "imagecategory (title, imagetype, displayorder) VALUES ('{$upgrade_phrases['upgrade_300b4.php']['generic_avatars']}', 1, 1)";
		$explain[] = $upgrade_phrases['upgrade_300b4.php']['default_avatar_category'];
	}

	$spiderarray = array(
		'spiderdesc' => "Google\nLycos\nAsk Jeeves\nAltavista\nAlltheWeb\nInktomi\nTurnitin.com",
		'spiderstrings' => "googlebot\nlycos\nask jeeves\nscooter\nfast-webcrawler\nslurp@inktomi\nturnitinbot",
		'spiderstring' => 'googlebot|lycos|ask jeeves|scooter|fast-webcrawler|slurp@inktomi|turnitinbot',
		'spiderarray' => array(
			'googlebot' => 'Google',
			'lycos' => 'Lycos',
			'ask jeeves' => 'Ask Jeeves',
			'scooter' => 'Altavista',
			'fast-webcrawler' => 'AllTheWeb',
			'slurp@inktomi' => 'Inktomi',
			'turnitinbot' => 'Turnitin.com'
		)
	);

	$query[] = "REPLACE INTO " . TABLE_PREFIX . "datastore (title, data) VALUES ('wol_spiders', '" . $db->escape_string(serialize($spiderarray)) . "')";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b4.php']['insert_into_whosonline'], TABLE_PREFIX);

	$query[] = "DELETE FROM " . TABLE_PREFIX . "cron WHERE filename = './includes/cron/threadmarkers.php'";
	$explain[] = $upgrade_phrases['upgrade_300b4.php']['delete_redundant_cron'];

	exec_queries();
}

// #############################################################################
// step 3
if ($vbulletin->GPC['step'] == 3)
{
	$data = array();

	$types = $db->query_read("
		SELECT extension, size, height, width, enabled, display
		FROM " . TABLE_PREFIX . "attachmenttype
		ORDER BY extension
	");
	while ($type = $db->fetch_array($types))
	{
		if (!empty($type['enabled']))
		{
			$data['extensions'] .= iif($data['extensions'], " $type[extension]", $type['extension']);
			$data["$type[extension]"] = $type;
			unset($type['extension']);
		}
	}
	$db->free_result($types);

	$db->query_write("UPDATE " . TABLE_PREFIX . "datastore SET data = '" . $db->escape_string(serialize($data)) . "' WHERE title = 'attachmentcache'");

	echo "<p>{$upgrade_phrases['upgrade_300b4.php']['attachment_cache_rebuilt']}</p>";
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 4)
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
