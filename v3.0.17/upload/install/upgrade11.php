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

define('THIS_SCRIPT', 'upgrade11.php');
define('VERSION', '3.0.0');
define('PREV_VERSION', '3.0.0 Release Candidate 4');

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
if ($step == 1)
{

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user CHANGE reputation reputation INT DEFAULT '10' NOT NULL";
	$explain[] = $upgrade_phrases['upgrade11.php']['make_reputation_signed'];

	// Put a birthday search field in for searching birthdays via the admincp
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user ADD birthday_search DATE NOT NULL";
	$explain[] = $upgrade_phrases['upgrade11.php']['add_birthday_search'];

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user ADD INDEX (birthday_search)";
	$explain[] = $upgrade_phrases['upgrade11.php']['add_index_birthday_search'];

	// Now populate this field by converting existing varchar birthdays.
	// We use the varchar birthdays for the calendar as the DATE format can't use an index in the way we use them
	$query[] = "UPDATE " . TABLE_PREFIX . "user
		SET birthday_search = IF (
				birthday <> '0000-00-00' AND birthday IS NOT NULL AND birthday <> '',
				CONCAT(
					SUBSTRING(birthday, 7, 4),
					'-',
					SUBSTRING(birthday, 1, 2),
					'-',
					SUBSTRING(birthday, 4, 2)
					),
					''
				)
	";
	$explain[] = $upgrade_phrases['upgrade11.php']['populate_birhtday_search'];

	exec_queries();

	$mod_threads = $DB_site->query("
		SELECT moderation.threadid FROM " . TABLE_PREFIX . "moderation AS moderation
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = moderation.threadid)
		WHERE moderation.type = 'thread' AND thread.threadid IS NULL
	");

	while ($mod_thread = $DB_site->fetch_array($mod_threads))
	{
		$ids[] = $mod_thread['threadid'];
	}
	if (!empty($ids))
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderation WHERE threadid IN (" . implode(',', $ids) . ")");
		$ids = array();
	}

	$mod_posts = $DB_site->query("
		SELECT moderation.postid FROM " . TABLE_PREFIX . "moderation AS moderation
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = moderation.postid)
		WHERE moderation.type = 'post' AND post.postid IS NULL
	");

	while ($mod_post = $DB_site->fetch_array($mod_posts))
	{
		$ids[] = $mod_post['postid'];
	}
	if (!empty($ids))
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderation WHERE postid IN (" . implode(',', $ids) . ")");
		$ids = array();
	}
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
|| # CVS: $RCSfile$ - $Revision: 9320 $
|| ####################################################################
\*======================================================================*/
?>