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

define('THIS_SCRIPT', 'upgrade_370rc1.php');
define('VERSION', '3.7.0 Release Candidate 1');
define('PREV_VERSION', '3.7.0 Beta 6');

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
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	if (!isset($vbulletin->bf_misc_useroptions['vm_enable']))
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$upgradecore_phrases[wrong_bitfield_xml]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'bookmarksite', 1, 1),
		"UPDATE " . TABLE_PREFIX . "bookmarksite
		SET url = 'http://www.google.com/bookmarks/mark?op=edit&amp;output=popup&amp;bkmk={URL}&amp;title={TITLE}'
		WHERE url = 'http://www.google.com/bookmarks/mark?op=edit&amp;output=popup&amp;bkmk={URL}&amp;annotation={TITLE}' AND title = 'Google'
		"
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 1),
		'user',
		'referrerid',
		array('referrerid')
	);

	$upgrade->execute();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 2)
{
	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);

	require_once(DIR . '/includes/adminfunctions_bookmarksite.php');
	build_bookmarksite_datastore();
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
