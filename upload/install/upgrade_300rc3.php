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

define('THIS_SCRIPT', 'upgrade_300rc3.php');
define('VERSION', '3.0.0 Release Candidate 3');
define('PREV_VERSION', '3.0.0 Release Candidate 2');

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
		$db->hide_errors();
		$db->query_first("SELECT filesize FROM " . TABLE_PREFIX . "customprofilepic LIMIT 1");
		if ($db->errno() == 0)
		{
			// they're hitting this because they did a fresh install of RC3, but
			// we had a bug that labeled the version as RC2. So let's update
			// the version number and redirect
			$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '3.0.0 Release Candidate 3' WHERE varname = 'templateversion'");
			build_options();
			echo "\n<script type=\"text/javascript\">\n";
			echo 'window.location="upgrade_300rc4.php";';
			echo "\n</script>\n";
			echo '<a href="upgrade_300rc4.php">' . $upgrade_phrases['upgrade_300rc3.php']['click_here_auto_redirect'] . '</a>';
		}
		else
		{
			echo "<blockquote><p>&nbsp;</p>";
			echo "$vbphrase[upgrade_start_message]";
			echo "<p>&nbsp;</p></blockquote>";
		}
		$db->errno = 0;
		$db->show_errors();
	}
	else
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$vbphrase[upgrade_wrong_version]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}

	// Update hidden profile field cache.. (hides hidden custom profile fields from non mods on posts)..
	require_once(DIR . '/includes/adminfunctions_profilefield.php');
	if (function_exists('build_profilefield_cache'))
	{
		build_profilefield_cache();
	}
	else
	{
		echo "<blockquote><p>{$upgrade_phrases['upgrade_300rc3.php']['not_latest_files']}</p></blockquote>";
		exit;
	}
}

// #############################################################################
// fix some broken fields
if ($vbulletin->GPC['step'] == 1)
{
	// search.sortorder should not be VARCHAR(2)
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "search CHANGE sortorder sortorder varchar(4) NOT NULL DEFAULT '' ";
	$explain[] = $upgrade_phrases['upgrade_300rc3.php']['fix_sortorder'];

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE logdateoverride logdateoverride varchar(20) NOT NULL default '' ";
	$explain[] = $upgrade_phrases['upgrade_300rc3.php']['fix_logdateoverride'];

	// Add filesize to customavatar table so that the Quick Stats work if avatars are in the FS (don't want to fool with listing a dir and adding it up..)
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customavatar ADD filesize INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = $upgrade_phrases['upgrade_300rc3.php']['fix_filesize_customavatar'];

	// this one is for future use and is added now to maintain sync with the customavatar table since they share functions
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customprofilepic ADD filesize INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = $upgrade_phrases['upgrade_300rc3.php']['fix_filesize_customprofile'];

	$query[] = "UPDATE " . TABLE_PREFIX . "customavatar SET filesize = LENGTH(avatardata)";
	$explain[] = $upgrade_phrases['upgrade_300rc3.php']['populate_avatar_filesize'];

	$query[] = "UPDATE " . TABLE_PREFIX . "customprofilepic SET filesize = LENGTH(profilepicdata)";
	$explain[] = $upgrade_phrases['upgrade_300rc3.php']['populate_profile_filesize'];

	// make sure lasticonid is not UNSIGNED as we store -1 in it for polls. upgrade_300b3.php has been creating it as UNSIGNED
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "forum CHANGE lasticonid lasticonid SMALLINT DEFAULT '0' NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "forum");

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
