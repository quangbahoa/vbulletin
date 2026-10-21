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

define('THIS_SCRIPT', 'upgrade_3810b1.php');
define('VERSION', '3.8.10 Beta 1');
define('PREV_VERSION', '3.8.9');

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
// IPv6 Updates
if ($vbulletin->GPC['step'] == 1)
{
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'strikes', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "strikes CHANGE strikeip strikeip VARCHAR(45) NOT NULL DEFAULT ''"
	);
	
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'adminlog', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "adminlog CHANGE ipaddress ipaddress VARCHAR(45) NOT NULL DEFAULT ''"
	);
	
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderatorlog', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "moderatorlog CHANGE ipaddress ipaddress VARCHAR(45) NOT NULL DEFAULT ''"
	);
	
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "user CHANGE ipaddress ipaddress VARCHAR(45) NOT NULL DEFAULT ''"
	);
	
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "post CHANGE ipaddress ipaddress VARCHAR(45) NOT NULL DEFAULT ''"
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
