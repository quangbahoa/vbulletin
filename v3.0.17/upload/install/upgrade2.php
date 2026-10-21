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

define('THIS_SCRIPT', 'upgrade2.php');
define('VERSION', '3.0.0 Beta 4');
define('PREV_VERSION', '3.0.0 Beta 3');

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
// step 1
if ($step == 1)
{
	// rename log_upgrade_step to upgradelog if it was named incorrectly
	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "log_upgrade_step RENAME " . TABLE_PREFIX . "upgradelog");
	$DB_site->reporterror = 1;

	// alter calendarcustomfield
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "calendarcustomfield
		ADD description MEDIUMTEXT NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "calendarcustomfield");

	// alter user
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user
		CHANGE timezoneoffset timezoneoffset CHAR(4) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "user");

	// alter session
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "session
		ADD bypass SMALLINT NOT NULL,
		CHANGE useragent useragent VARCHAR(100) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "session");

	// alter event
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "event
		CHANGE recuroption recuroption CHAR(6) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "event");

	// alter administrator
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "administrator
		ADD navprefs VARCHAR(250) NOT NULL,
		ADD cssprefs VARCHAR(250) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "administrator");

	// alter usergroup
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "usergroup
		CHANGE avatarmaxsize avatarmaxsize INT UNSIGNED NOT NULL,
		CHANGE profilepicmaxsize profilepicmaxsize INT UNSIGNED NOT NULL";
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
if ($step == 2)
{
	$avatargroupexists = $DB_site->query_first("SELECT imagecategoryid FROM " . TABLE_PREFIX . "imagecategory WHERE imagetype = 1");
	if (!$avatargroupexists)
	{
		$query[] = "INSERT INTO " . TABLE_PREFIX . "imagecategory (title, imagetype, displayorder) VALUES ('{$upgrade_phrases['upgrade2.php']['generic_avatars']}', 1, 1)";
		$explain[] = $upgrade_phrases['upgrade2.php']['default_avatar_category'];
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

	$query[] = "INSERT INTO " . TABLE_PREFIX . "datastore (title, data) VALUES ('wol_spiders', '" . addslashes(serialize($spiderarray)) . "')";
	$explain[] = sprintf($upgrade_phrases['upgrade2.php']['insert_into_whosonline'], TABLE_PREFIX);

	$query[] = "DELETE FROM " . TABLE_PREFIX . "cron WHERE filename = './includes/cron/threadmarkers.php'";
	$explain[] = $upgrade_phrases['upgrade2.php']['delete_redundant_cron'];

	exec_queries();
}

// #############################################################################
// step 3
if ($step == 3)
{
	$data = array();

	$types = $DB_site->query("
		SELECT extension, size, height, width, enabled, display
		FROM " . TABLE_PREFIX . "attachmenttype
		ORDER BY extension
	");
	while ($type = $DB_site->fetch_array($types))
	{
		if (!empty($type['enabled']))
		{
			$data['extensions'] .= iif($data['extensions'], " $type[extension]", $type['extension']);
			$data["$type[extension]"] = $type;
			unset($type['extension']);
		}
	}
	$DB_site->free_result($types);

	$DB_site->query("UPDATE " . TABLE_PREFIX . "datastore SET data = '" . addslashes(serialize($data)) . "' WHERE title = 'attachmentcache'");

	echo "<p>{$upgrade_phrases['upgrade2.php']['attachment_cache_rebuilt']}</p>";
}

// #############################################################################
// import vbulletin options
if ($step == 4)
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
if ($step == 5)
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
if ($step == 6)
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
if ($step == 7)
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
if ($step == 8)
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
|| # CVS: $RCSfile$ - $Revision: 9259 $
|| ####################################################################
\*======================================================================*/
?>