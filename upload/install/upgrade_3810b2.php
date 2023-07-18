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

define('THIS_SCRIPT', 'upgrade_3810b2.php');
define('VERSION', '3.8.10 Beta 2');
define('PREV_VERSION', '3.8.10 Beta 1');

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
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderator', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "moderator CHANGE moderatorid moderatorid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT"
	);
	
	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 2)
{
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'passwordhistory', 1, 2),
		"ALTER TABLE " . TABLE_PREFIX . "passwordhistory CHANGE COLUMN passworddate passworddate DATE NOT NULL DEFAULT '1000-01-01'"
	);

	// There shouldn't be any to change, but lets play safe.
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'passwordhistory', 2, 2),
		"UPDATE " . TABLE_PREFIX . "passwordhistory SET passworddate = '1000-01-01' WHERE passworddate = '0000-00-00'"
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 3)
{
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 4),
		"ALTER TABLE " . TABLE_PREFIX . "user CHANGE COLUMN passworddate passworddate DATE NOT NULL DEFAULT '1000-01-01'"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 4),
		"UPDATE " . TABLE_PREFIX . "user SET passworddate = '1000-01-01' WHERE passworddate = '0000-00-00'"
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 4)
{
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 3, 4),
		"ALTER TABLE " . TABLE_PREFIX . "user CHANGE COLUMN birthday_search birthday_search DATE NOT NULL DEFAULT '1000-01-01'"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 4, 4),
		"UPDATE " . TABLE_PREFIX . "user SET birthday_search = '1000-01-01' WHERE birthday_search = '0000-00-00'"
	);

	$upgrade->execute();
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
