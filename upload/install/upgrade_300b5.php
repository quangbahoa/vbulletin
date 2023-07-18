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

define('THIS_SCRIPT', 'upgrade_300b5.php');
define('VERSION', '3.0.0 Beta 5');
define('PREV_VERSION', '3.0.0 Beta 4');

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
	// rename log_upgrade_step to upgradelog if it was named incorrectly
	$db->hide_errors();
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "log_upgrade_step RENAME " . TABLE_PREFIX . "upgradelog");
	$db->show_errors();

	// Seeing too many session tables as MyISAM?
	$db->hide_errors();
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "session ENGINE = MEMORY");
	$db->show_errors();

	// alter pmtext table // add column that sets smilies on/off
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "pmtext
		ADD allowsmilie SMALLINT NOT NULL DEFAULT '1'";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "pmtext");

	// alter ranks table // Increase size to allow HTML to be used
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "ranks
		CHANGE rankimg rankimg MEDIUMTEXT,
		ADD type SMALLINT NOT NULL DEFAULT '0'";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "ranks");

	// alter attachmenttype table // Increase allowed size of extension file names
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachmenttype
		CHANGE extension extension CHAR(20) NOT NULL DEFAULT ''";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "attachmenttype");

	// alter bbcode table // Increase size of field that holds bbcode data
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "bbcode
		CHANGE bbcodereplacement bbcodereplacement mediumtext";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "bbcode");

	// alter stats table // Remove the column that records forum views as support for views is being dropped
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "stats
		DROP nviews";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "stats");

	// alter user table // Increase size of allowed Yahoo screen name to 32
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user
		CHANGE yahoo yahoo CHAR(32) NOT NULL DEFAULT ''";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "user");

	// change insouth to showvcard
	$query[] = "UPDATE " . TABLE_PREFIX . "user SET options = options - 32 WHERE (options & 32)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "user");

	// add calendar option to disable Easter holidays independent of inputted holidays
	// Set this value to true if showholidays is true
	$query[] = "UPDATE " . TABLE_PREFIX . "calendar SET options = options + 1024 WHERE (options & 2)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "calendar");

	// alter language table // New groups
	$db->hide_errors();
	$db->query_first("SELECT phrasegroup_accessmask FROM " . TABLE_PREFIX . "language LIMIT 1");
	$db->show_errors();
	if ($db->errno() != 0)
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language
			ADD phrasegroup_accessmask mediumtext,
			ADD phrasegroup_cron mediumtext,
			ADD phrasegroup_moderator mediumtext,
			ADD phrasegroup_cpoption mediumtext,
			ADD phrasegroup_cprank mediumtext,
			ADD phrasegroup_cpusergroup mediumtext";
		$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "language");
	}
	$db->errno = 0;

	// update phrase group list
	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['accessmask']}', editrows=3, fieldname='accessmask' WHERE phrasetypeid=29";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype");
	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['cron']}', editrows=3, fieldname='cron' WHERE phrasetypeid=30";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype");
	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['moderator']}', editrows=3, fieldname='moderator' WHERE phrasetypeid=31";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype");
	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['cpoption']}', editrows=3, fieldname='cpoption' WHERE phrasetypeid=32";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype");
	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['cprank']}', editrows=3, fieldname='cprank' WHERE phrasetypeid=33";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype");
	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['cpusergroup']}', editrows=3, fieldname='cpusergroup' WHERE phrasetypeid=34";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype");

	$query[] = "UPDATE " . TABLE_PREFIX . "setting SET value = 'images/icons/icon1.gif' WHERE varname = 'showdeficon' AND value = 1";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "setting");

	exec_queries();
}

// #############################################################################
// step 2
if ($vbulletin->GPC['step'] == 2)
{
	require_once(DIR . '/includes/functions_misc.php');

	// change post title field
	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade_300b5.php']['alter_post_title'], TABLE_PREFIX) . "</i>");
	$t = microtime();
	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "post
		CHANGE title title VARCHAR(250) NOT NULL DEFAULT ''
	");
	echo_flush('<br />' . sprintf($vbphrase['query_took'], number_format(fetch_microtime_difference($t), 2)) . '</p>');

	// change thread title field
	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade_300b5.php']['alter_thread_title'], TABLE_PREFIX) . "</i>");
	$t = microtime();
	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "thread
		CHANGE title title VARCHAR(250) NOT NULL DEFAULT ''
	");
	echo_flush('<br />' . sprintf($vbphrase['query_took'], number_format(fetch_microtime_difference($t), 2)) . '</p>');
}

// #############################################################################
// ask user if they want to change a setting that is causing some of the CP login issues
if ($vbulletin->GPC['step'] == 3)
{
	$vbulletin->input->clean_array_gpc('p', array(
		'settingconfirm' => TYPE_UINT,
		'disablesetting' => TYPE_UINT,
	));

	if ($vbulletin->GPC['settingconfirm'] == 1)
	{
		echo '<p>';
		if ($vbulletin->GPC['disablesetting'] == 1)
		{
			$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value=0 WHERE varname='timeoutcontrolpanel'");
			echo $upgrade_phrases['upgrade_300b5.php']['disabled_timeout_admin'];
		}
		else
		{
			echo $upgrade_phrases['upgrade_300b5.php']['timeout_admin_not_changed'];
		}
		echo '</p>';
	}
	else
	{
		if ($vbulletin->options['timeoutcontrolpanel'] == 1)
		{
			print_form_header('upgrade_300b5', '');
			construct_hidden_code('step', $vbulletin->GPC['step']);
			construct_hidden_code('settingconfirm', 1);
			print_table_header($upgrade_phrases['upgrade_300b5.php']['change_setting_value']);
			print_yes_no_row($upgrade_phrases['upgrade_300b5.php']['setting_info'], 'disablesetting', 0);
			print_submit_row($upgrade_phrases['upgrade_300b5.php']['proceed'], '');
			print_cp_footer();
		}
		else
		{
			echo "<p>{$upgrade_phrases['upgrade_300b5.php']['no_change_needed']}</p>";
		}
	}

}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 4)
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
