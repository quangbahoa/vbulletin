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

define('THIS_SCRIPT', 'upgrade_300rc1.php');
define('VERSION', '3.0.0 Release Candidate 1');
define('PREV_VERSION', '3.0.0 Gamma');

$phrasegroups = array();
$specialtemplates = array();

// #############################################################################
// require the code that makes it all work...
require_once('./upgradecore.php');

// since the system is being changed slightly, make sure phrases still look ok
// before languages are rebuilt
$vbphrase = preg_replace('/\{([0-9]+)\}/siU', '%\\1$s', $vbphrase);

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

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "userpromotion CHANGE reputation reputation INT DEFAULT '0' NOT NULL";
	$explain[] = $upgrade_phrases['upgrade_300rc1.php']['alter_reputation_negative'];

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "phrase CHANGE varname varname VARCHAR(250) BINARY NOT NULL DEFAULT ''";
	$explain[] = $upgrade_phrases['upgrade_300rc1.php']['phrase_varname_case_sens'];

	$query[] = "INSERT INTO " . TABLE_PREFIX . "faq VALUES ('vb_threadedmode', 'vb_board_usage', 9, 1)";
	$explain[] = $upgrade_phrases['upgrade_300rc1.php']['add_faq_entry'];

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
