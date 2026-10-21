<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

define('THIS_SCRIPT', 'upgrade_303.php');
define('VERSION', '3.0.3');
define('PREV_VERSION', '3.0.2');

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
		if ($vbulletin->options['attachfile'])
		{
			echo $upgrade_phrases['upgrade_303.php']['note'];
		}
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
	// Make sure thumbnails are working from 3.0.2 if thumbnails are in the database. Attachment as files users have to rebuild them.
	if (!$vbulletin->options['attachfile'])
	{
		$query[] = "UPDATE " . TABLE_PREFIX . "attachment SET thumbnail_filesize = IF (thumbnail IS NULL, 0, LENGTH(thumbnail))";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 1, 1);
		exec_queries();
	}

	echo '<ul><li>' . $upgrade_phrases['upgrade_303.php']['rebuild_usergroupcache'] . '</li></ul>';
	// rebuild permissions just in case 3.0.2 missed it.
	build_forum_permissions();
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
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13428 $
|| ####################################################################
\*======================================================================*/
?>