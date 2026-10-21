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

define('THIS_SCRIPT', 'upgrade_350rc1.php');
define('VERSION', '3.5.0 Release Candidate 1');
define('PREV_VERSION', '3.5.0 Beta 4');

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
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "product"),
		"CREATE TABLE " . TABLE_PREFIX . "product (
			productid VARCHAR(25) NOT NULL DEFAULT '',
			title VARCHAR(50) NOT NULL DEFAULT '',
			description VARCHAR(250) NOT NULL DEFAULT '',
			version VARCHAR(25) NOT NULL DEFAULT '',
			active SMALLINT UNSIGNED DEFAULT '1' NOT NULL,
			PRIMARY KEY (productid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "productcode"),
		"CREATE TABLE " . TABLE_PREFIX . "productcode (
			productcodeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			productid VARCHAR(25) NOT NULL DEFAULT '',
			version VARCHAR(25) NOT NULL DEFAULT '',
			installcode MEDIUMTEXT,
			uninstallcode MEDIUMTEXT,
			PRIMARY KEY (productcodeid),
			INDEX (productid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		$upgrade_phrases['upgrade_350rc1.php']['control_panel_hook_support'],
		"INSERT IGNORE INTO " . TABLE_PREFIX . "datastore (title, data) VALUES ('pluginlistadmin', 'a:0:{}')"
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
