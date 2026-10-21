<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE & ~8192);

define('THIS_SCRIPT', 'upgrade_300rc4.php');
define('VERSION', '3.0.0 Release Candidate 4');
define('PREV_VERSION', '3.0.0 Release Candidate 3');

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
// fix some broken fields
if ($vbulletin->GPC['step'] == 1)
{
	// Begin increase the size of the date/time override fields
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE dateoverride dateoverride varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade_300rc4.php']['increase_storage_dateoverride'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE timeoverride timeoverride varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade_300rc4.php']['increase_storage_timeoverride'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE registereddateoverride registereddateoverride varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade_300rc4.php']['increase_storage_registereddateoverride'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE calformat1override calformat1override varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade_300rc4.php']['increase_storage_calformat1override'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE calformat2override calformat2override varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade_300rc4.php']['increase_storage_calformat2override'];

		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE logdateoverride logdateoverride varchar(50) NOT NULL default '' ";
		$explain[] = $upgrade_phrases['upgrade_300rc4.php']['increase_storage_logdateoverride'];
	// -- End increase the size of override fields

	// Alter Calendar to support more pre-defined holidays
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "calendar ADD holidays INT UNSIGNED NOT NULL DEFAULT '0'";
		$explain[] = $upgrade_phrases['upgrade_300rc4.php']['adding_calendar_mardi_gras'];

		$query[] = "UPDATE " . TABLE_PREFIX . "calendar SET holidays = 31 WHERE options & 1024";
		$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "calendar");

	exec_queries();
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
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 31700 $
|| ####################################################################
\*======================================================================*/
?>