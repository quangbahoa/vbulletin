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

define('THIS_SCRIPT', 'upgrade10.php');
define('VERSION', '3.0.0 Release Candidate 4');
define('PREV_VERSION', '3.0.0 Release Candidate 3');

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
// fix some broken fields
if ($step == 1)
{
	// Begin increase the size of the date/time override fields
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE dateoverride dateoverride varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade10.php']['increase_storage_dateoverride'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE timeoverride timeoverride varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade10.php']['increase_storage_timeoverride'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE registereddateoverride registereddateoverride varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade10.php']['increase_storage_registereddateoverride'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE calformat1override calformat1override varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade10.php']['increase_storage_calformat1override'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE calformat2override calformat2override varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade10.php']['increase_storage_calformat2override'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE logdateoverride logdateoverride varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade10.php']['increase_storage_logdateoverride'];
	// -- End increase the size of override fields

	// Alter Calendar to support more pre-defined holidays
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "calendar ADD holidays INT UNSIGNED NOT NULL";
		$explain[] = $upgrade_phrases['upgrade10.php']['adding_calendar_carnival'];

		$query[] = "UPDATE " . TABLE_PREFIX . "calendar SET holidays = 31 WHERE options & 1024";
		$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "calendar");

	exec_queries();
}

// #############################################################################
// import vbulletin options
if ($step == 2)
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
if ($step == 3)
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
if ($step == 4)
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
if ($step == 5)
{
	require_once('./includes/adminfunctions_template.php');

	if (!($xml = file_read('./install/vbulletin-style.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-style.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-style.xml');

	xml_import_style($xml);
	build_all_styles();
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($step == 6)
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
|| # CVS: $RCSfile$ - $Revision: 9222 $
|| ####################################################################
\*======================================================================*/
?>