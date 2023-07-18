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

define('THIS_SCRIPT', 'upgrade_300b7.php');
define('VERSION', '3.0.0 Beta 7');
define('PREV_VERSION', '3.0.0 Beta 6');

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
// step 1
if ($vbulletin->GPC['step'] == 1)
{
	// add template version column
	$db->hide_errors();
	$db->query_first("SELECT version FROM " . TABLE_PREFIX . "template LIMIT 1");
	$db->show_errors();
	if ($db->errno() != 0)
	{
		// error from query, so we don't have the columns
		$db->errno = 0;
		$query[] = "
			ALTER TABLE " . TABLE_PREFIX . "template
			ADD version varchar(30) NOT NULL DEFAULT ''
		";
		$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "template");
	}

	// delete redundant stylevars
	$query[] = "
		DELETE FROM " . TABLE_PREFIX . "template
		WHERE templatetype = 'template'
		AND title IN('imagesfolder', 'newthreadimage', 'newreplyimage', 'closedthreadimage', 'contenttype')
	";
	$explain[] = $upgrade_phrases['upgrade_300b7.php']['redundant_stylevars'];

	// rename some templates
	$query[] = "
		UPDATE " . TABLE_PREFIX . "template
		SET title = CASE title
			WHEN 'postbit_bbcode_code' THEN 'bbcode_code'
			WHEN 'postbit_bbcode_html' THEN 'bbcode_html'
			WHEN 'postbit_bbcode_php' THEN 'bbcode_php'
			WHEN 'postbit_bbcode_quote' THEN 'bbcode_quote'
			WHEN 'bbcode' THEN 'help_bbcodes'
			WHEN 'bbcodebit' THEN 'help_bbcodes_bbcode'
			WHEN 'SMILIELIST' THEN 'help_smilies'
			WHEN 'smilielistbit' THEN 'help_smilies_smilie'
			WHEN 'smilielist_category' THEN 'help_smilies_category'
			WHEN 'avatarlist' THEN 'help_avatars'
			WHEN 'avatarlist_avatar' THEN 'help_avatars_avatar'
			WHEN 'avatarlist_category' THEN 'help_avatars_category'
			WHEN 'avatarlist_row' THEN 'help_avatars_row'
			ELSE title END
	";
	$explain[] = $upgrade_phrases['upgrade_300b7.php']['renaming_some_templates'];

	// fix my oops in upgrade_300b6.php - update the remove bans scheduled task to run hourly
	$query[] = "
		UPDATE " . TABLE_PREFIX . "cron SET
			nextrun = " . (TIMENOW + 1500) . ",
			weekday = -1,
			day = -1,
			hour = -1,
			minute = 15
		WHERE filename = './includes/cron/removebans.php'
	";
	$explain[] = $upgrade_phrases['upgrade_300b7.php']['ban_removal_fix'];

	// fix scott's oops when he broke user promotions
	$query[] = "
		UPDATE " . TABLE_PREFIX . "cron
		SET nextrun = 1062979200
		WHERE filename = './includes/cron/promotion.php'
	";
	$explain[] = $upgrade_phrases['upgrade_300b7.php']['promotion_lastrun_fix'];

	// alter language table
	$db->hide_errors();
	$db->query_first("SELECT charset FROM " . TABLE_PREFIX . "language LIMIT 1");
	$db->show_errors();
	if ($db->errno() != 0)
	{
		// error from query, so we don't have the column
		$query[] = "
			ALTER TABLE " . TABLE_PREFIX . "language
			ADD charset VARCHAR(15) NOT NULL DEFAULT ''
		";
		$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "language");
	}
	$db->errno = 0;

	// add data to language table
	$query[] = "
		UPDATE " . TABLE_PREFIX . "language
		SET charset = 'ISO-8859-1'
	";
	$explain[] = $upgrade_phrases['upgrade_300b7.php']['default_charset'];

	exec_queries();
}

// #############################################################################
// step 2
if ($vbulletin->GPC['step'] == 2)
{
	// add the autosubscribe column to user
	$query[] = "
		ALTER TABLE " . TABLE_PREFIX . "user
		ADD autosubscribe SMALLINT NOT NULL DEFAULT '-1'
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "user");

	// update the autosubscribe field to reflect the 'emailnotification' field
	// number is hardcoded, since it isn't in init.php anymore
	$query[] = "
		UPDATE " . TABLE_PREFIX . "user SET
			autosubscribe = 1,
			options = options - 16384
		WHERE (options & 16384)
	";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "user");

	// fix adminhelp phrases
	$query[] = "UPDATE " . TABLE_PREFIX . "phrase SET varname = REPLACE(varname, ',', '_')";
	$explain[] = $upgrade_phrases['upgrade_300b7.php']['comma_var_names'];

	exec_queries();
}

// #############################################################################
// *******************************************************
// DO NOT PUT ANY SCHEMA CHANGES BELOW THIS POINT!! - Kier
// *******************************************************
// step 3
if ($vbulletin->GPC['step'] == 3)
{
	echo "<blockquote><blockquote>";
	echo $upgrade_phrases['upgrade_300b7.php']['bbcode_update'];
	echo "</blockquote></blockquote>";

	unset($vbulletin->debug); // just to prevent auto-proceed
}

// #############################################################################
// step 4
if ($vbulletin->GPC['step'] == 4)
{
	$query[] = "DELETE FROM " . TABLE_PREFIX . "bbcode WHERE bbcodetag IN('email', 'quote')";
	$explain[] = $upgrade_phrases['upgrade_300b7.php']['delete_quote_email_bbcode'];

	exec_queries();

	// since we have altered the bbcode table, we need to rebuild the bbcode cache
	build_bbcode_cache();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 5)
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
