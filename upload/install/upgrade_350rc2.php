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

define('THIS_SCRIPT', 'upgrade_350rc2.php');
define('VERSION', '3.5.0 Release Candidate 2');
define('PREV_VERSION', '3.5.0 Release Candidate 1');

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
	// alter tables to allow 25 character product ids

	$upgrade->run_query(
		'',
		"REPLACE INTO " . TABLE_PREFIX . "adminutil (title, text) VALUES ('datastorelock', '0')"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrasetype', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "phrasetype CHANGE product product VARCHAR(25) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "phrase CHANGE product product VARCHAR(25) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'template', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "template CHANGE product product VARCHAR(25) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "setting CHANGE product product VARCHAR(25) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'settinggroup', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "settinggroup CHANGE product product VARCHAR(25) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'adminhelp', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "adminhelp CHANGE product product VARCHAR(25) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'plugin', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "plugin CHANGE product product VARCHAR(25) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'product', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "product CHANGE productid productid VARCHAR(25) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'productcode', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "productcode CHANGE productid productid VARCHAR(25) NOT NULL DEFAULT ''"
	);

	$upgrade->execute();

	build_product_datastore();
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
