<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

define('THIS_SCRIPT', 'upgrade9.php');
define('VERSION', '3.0.0 Release Candidate 3');
define('PREV_VERSION', '3.0.0 Release Candidate 2');

$phrasegroups = array();
$specialtemplates = array();

// #############################################################################
// require the code that makes it all work...
require_once('./upgradecore.php');

// #############################################################################
// welcome step
if ($step == 'welcome')
{
	if ($vboptions['templateversion'] == PREV_VERSION)
	{
		$DB_site->reporterror = 0;
		$DB_site->query_first("SELECT filesize FROM " . TABLE_PREFIX . "customprofilepic LIMIT 1");
		if ($DB_site->geterrno() == 0)
		{
			// they're hitting this because they did a fresh install of RC3, but
			// we had a bug that labeled the version as RC2. So let's update
			// the version number and redirect
			$DB_site->query("UPDATE " . TABLE_PREFIX . "setting SET value = '3.0.0 Release Candidate 3' WHERE varname = 'templateversion'");
			build_options();
			echo "\n<script type=\"text/javascript\">\n";
			echo 'window.location="upgrade10.php";';
			echo "\n</script>\n";
			echo '<a href="upgrade10.php">' . $upgrade_phrases['upgrade9.php']['click_here_auto_redirect'] . '</a>';
		}
		else
		{
			echo "<blockquote><p>&nbsp;</p>";
			echo "$vbphrase[upgrade_start_message]";
			echo "<p>&nbsp;</p></blockquote>";
		}
		$DB_site->errno = 0;
		$DB_site->reporterror = 1;
	}
	else
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$vbphrase[upgrade_wrong_version]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}

	// Update hidden profile field cache.. (hides hidden custom profile fields from non mods on posts)..
	require_once('./includes/adminfunctions_profilefield.php');
	if (function_exists('build_hiddenprofilefield_cache'))
	{
		build_hiddenprofilefield_cache();
	}
	else
	{
		echo "<blockquote><p>{$upgrade_phrases['upgrade9.php']['not_latest_files']}</p></blockquote>";
		exit;
	}
}

// #############################################################################
// fix some broken fields
if ($step == 1)
{
	// search.sortorder should not be VARCHAR(2)
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "search CHANGE sortorder sortorder varchar(4) NOT NULL DEFAULT '' ";
	$explain[] = $upgrade_phrases['upgrade9.php']['fix_sortorder'];

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "language CHANGE logdateoverride logdateoverride varchar(20) NOT NULL default '' ";
	$explain[] = $upgrade_phrases['upgrade9.php']['fix_logdateoverride'];

	// Add filesize to customavatar table so that the Quick Stats work if avatars are in the FS (don't want to fool with listing a dir and adding it up..)
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customavatar ADD filesize INT UNSIGNED NOT NULL";
	$explain[] = $upgrade_phrases['upgrade9.php']['fix_filesize_customavatar'];

	// this one is for future use and is added now to maintain sync with the customavatar table since they share functions
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customprofilepic ADD filesize INT UNSIGNED NOT NULL";
	$explain[] = $upgrade_phrases['upgrade9.php']['fix_filesize_customprofile'];

	$query[] = "UPDATE " . TABLE_PREFIX . "customavatar SET filesize = LENGTH(avatardata)";
	$explain[] = $upgrade_phrases['upgrade9.php']['populate_avatar_filesize'];

	$query[] = "UPDATE " . TABLE_PREFIX . "customprofilepic SET filesize = LENGTH(profilepicdata)";
	$explain[] = $upgrade_phrases['upgrade9.php']['populate_profile_filesize'];

	// make sure lasticonid is not UNSIGNED as we store -1 in it for polls. upgrade1.php has been creating it as UNSIGNED
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "forum CHANGE lasticonid lasticonid SMALLINT DEFAULT 0 NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "forum");

	exec_queries();
}

// #############################################################################
// import vbulletin options
if ($step == 2)
{
	require_once('./includes/adminfunctions_options.php');

	if (!($xml = file_read('./install/vbulletin-settings.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-settings.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-settings.xml');

	xml_import_settings($xml);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// import admin help
if ($step == 3)
{
	require_once('./includes/adminfunctions_help.php');

	if (!($xml = file_read('./install/vbulletin-adminhelp.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-adminhelp.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-adminhelp.xml');

	xml_import_help_topics($xml);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// import language
if ($step == 4)
{
	require_once('./includes/adminfunctions_language.php');

	if (!($xml = file_read('./install/vbulletin-language.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-language.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-language.xml');

	xml_import_language($xml);
	build_language();
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// import style
if ($step == 5)
{
	require_once('./includes/adminfunctions_template.php');

	if (!($xml = file_read('./install/vbulletin-style.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-style.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-style.xml');

	xml_import_style($xml);
	build_all_styles();
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($step == 6)
{
	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9222 $
|| ####################################################################
\*======================================================================*/
?>