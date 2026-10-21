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

define('THIS_SCRIPT', 'upgrade3.php');
define('VERSION', '3.0.0 Beta 5');
define('PREV_VERSION', '3.0.0 Beta 4');

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
// step 1
if ($step == 1)
{
	// rename log_upgrade_step to upgradelog if it was named incorrectly
	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "log_upgrade_step RENAME " . TABLE_PREFIX . "upgradelog");
	$DB_site->reporterror = 1;

	// Seeing too many session tables as MyISAM?
	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "session TYPE=HEAP");
	$DB_site->reporterror = 1;

	// alter pmtext table // add column that sets smilies on/off
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "pmtext
		ADD allowsmilie SMALLINT NOT NULL DEFAULT '1'";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "pmtext");

	// alter ranks table // Increase size to allow HTML to be used
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "ranks
		CHANGE rankimg rankimg MEDIUMTEXT NOT NULL,
		ADD type SMALLINT NOT NULL DEFAULT '0'";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "ranks");

	// alter attachmenttype table // Increase allowed size of extension file names
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachmenttype
		CHANGE extension extension CHAR(20) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "attachmenttype");

	// alter bbcode table // Increase size of field that holds bbcode data
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "bbcode
		CHANGE bbcodereplacement bbcodereplacement mediumtext NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "bbcode");

	// alter stats table // Remove the column that records forum views as support for views is being dropped
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "stats
		DROP nviews";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "stats");

	// alter user table // Increase size of allowed Yahoo screen name to 32
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user
		CHANGE yahoo yahoo CHAR( 32 ) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "user");

	// change insouth to showvcard
	$query[] = "UPDATE " . TABLE_PREFIX . "user SET options = options - 32 WHERE (options & 32)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "user");

	// add calendar option to disable Easter holidays independent of inputted holidays
	// Set this value to true if showholidays is true
	$query[] = "UPDATE " . TABLE_PREFIX . "calendar SET options = options + 1024 WHERE (options & 2)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "calendar");

	// alter language table // New groups
	$DB_site->reporterror = 0;
	$DB_site->query_first("SELECT phrasegroup_accessmask FROM " . TABLE_PREFIX . "language LIMIT 1");
	$DB_site->reporterror = 1;
	if ($DB_site->geterrno() != 0)
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language
			ADD phrasegroup_accessmask mediumtext NOT NULL,
			ADD phrasegroup_cron mediumtext NOT NULL,
			ADD phrasegroup_moderator mediumtext NOT NULL,
			ADD phrasegroup_cpoption mediumtext NOT NULL,
			ADD phrasegroup_cprank mediumtext NOT NULL,
			ADD phrasegroup_cpusergroup mediumtext NOT NULL";
		$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "language");
	}
	$DB_site->errno = 0;

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
if ($step == 2)
{
	require_once('./includes/functions_misc.php');

	// change post title field
	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade3.php']['alter_post_title'], TABLE_PREFIX) . "</i>");
	$t = microtime();
	$DB_site->query("
		ALTER TABLE " . TABLE_PREFIX . "post
		CHANGE title title VARCHAR(250) NOT NULL
	");
	echo_flush('<br />' . sprintf($vbphrase['query_took'], number_format(fetch_microtime_difference($t), 2)) . '</p>');

	// change thread title field
	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade3.php']['alter_thread_title'], TABLE_PREFIX) . "</i>");
	$t = microtime();
	$DB_site->query("
		ALTER TABLE " . TABLE_PREFIX . "thread
		CHANGE title title VARCHAR(250) NOT NULL
	");
	echo_flush('<br />' . sprintf($vbphrase['query_took'], number_format(fetch_microtime_difference($t), 2)) . '</p>');
}

// #############################################################################
// ask user if they want to change a setting that is causing some of the CP login issues
if ($step == 3)
{
	if ($_POST['settingconfirm'] == 1)
	{
		echo '<p>';
		if ($_POST['disablesetting'] == 1)
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "setting SET value=0 WHERE varname='timeoutcontrolpanel'");
			echo $upgrade_phrases['upgrade3.php']['disabled_timeout_admin'];
		}
		else
		{
			echo $upgrade_phrases['upgrade3.php']['timeout_admin_not_changed'];
		}
		echo '</p>';
	}
	else
	{
		if ($vboptions['timeoutcontrolpanel'] == 1)
		{
			print_form_header('upgrade3', '');
			construct_hidden_code('step', $step);
			construct_hidden_code('settingconfirm', 1);
			print_table_header($upgrade_phrases['upgrade3.php']['change_setting_value']);
			print_yes_no_row($upgrade_phrases['upgrade3.php']['setting_info'], 'disablesetting', 0);
			print_submit_row($upgrade_phrases['upgrade3.php']['proceed'], '');
			print_cp_footer();
		}
		else
		{
			echo "<p>{$upgrade_phrases['upgrade3.php']['no_change_needed']}</p>";
		}
	}

}

// #############################################################################
// import vbulletin options
if ($step == 4)
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
if ($step == 5)
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
if ($step == 6)
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
if ($step == 7)
{
	require_once('./includes/adminfunctions_template.php');

	if (!($xml = file_read('./install/vbulletin-style.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-style.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-style.xml');

	xml_import_style($xml);
	define('NO_POST_EDITOR_BUILD', true);
	build_all_styles();
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($step == 8)
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
|| # CVS: $RCSfile$ - $Revision: 9268 $
|| ####################################################################
\*======================================================================*/
?>