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

define('THIS_SCRIPT', 'upgrade_360.php');
define('VERSION', '3.6.0');
define('PREV_VERSION', '3.6.0 Release Candidate 3');

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
	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'reputation', 1, 2),
		'reputation',
		'whoadded'
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'reputation', 2, 2),
		"ALTER IGNORE TABLE " . TABLE_PREFIX . "reputation ADD UNIQUE INDEX
			whoadded_postid (whoadded, postid)",
		MYSQL_ERROR_KEY_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'useractivation', 1, 1),
		'useractivation',
		'emailchange',
		'smallint',
		FIELD_DEFAULTS
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
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 31700 $
|| ####################################################################
\*======================================================================*/
?>
