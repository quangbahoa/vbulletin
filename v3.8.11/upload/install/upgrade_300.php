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

define('THIS_SCRIPT', 'upgrade_300.php');
define('VERSION', '3.0.0');
define('PREV_VERSION', '3.0.0 Release Candidate 4');

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

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user CHANGE reputation reputation INT NOT NULL DEFAULT '10'";
	$explain[] = $upgrade_phrases['upgrade_300.php']['make_reputation_signed'];

	// Put a birthday search field in for searching birthdays via the admincp
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user ADD birthday_search DATE NOT NULL DEFAULT '0000-00-00'";
	$explain[] = $upgrade_phrases['upgrade_300.php']['add_birthday_search'];

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user ADD INDEX (birthday_search)";
	$explain[] = $upgrade_phrases['upgrade_300.php']['add_index_birthday_search'];

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
	$explain[] = $upgrade_phrases['upgrade_300.php']['populate_birhtday_search'];

	exec_queries();

	$mod_threads = $db->query_read("
		SELECT moderation.threadid FROM " . TABLE_PREFIX . "moderation AS moderation
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = moderation.threadid)
		WHERE moderation.type = 'thread' AND thread.threadid IS NULL
	");

	while ($mod_thread = $db->fetch_array($mod_threads))
	{
		$ids[] = $mod_thread['threadid'];
	}
	if (!empty($ids))
	{
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "moderation WHERE threadid IN (" . implode(',', $ids) . ")");
		$ids = array();
	}

	$mod_posts = $db->query_read("
		SELECT moderation.postid FROM " . TABLE_PREFIX . "moderation AS moderation
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = moderation.postid)
		WHERE moderation.type = 'post' AND post.postid IS NULL
	");

	while ($mod_post = $db->fetch_array($mod_posts))
	{
		$ids[] = $mod_post['postid'];
	}
	if (!empty($ids))
	{
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "moderation WHERE postid IN (" . implode(',', $ids) . ")");
		$ids = array();
	}
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
