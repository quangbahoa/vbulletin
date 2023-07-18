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

define('THIS_SCRIPT', 'upgrade_308.php');
define('VERSION', '3.0.8');
define('PREV_VERSION', '3.0.7');

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
	// Change mediumtext binary fields to mediumblob
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE filedata filedata MEDIUMBLOB";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 1, 2);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE thumbnail thumbnail MEDIUMBLOB";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 2, 2);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customavatar CHANGE avatardata avatardata MEDIUMBLOB";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customavatar', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customprofilepic CHANGE profilepicdata profilepicdata MEDIUMBLOB";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customprofilepic', 1, 1);

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
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
