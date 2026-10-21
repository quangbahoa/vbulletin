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

define('THIS_SCRIPT', 'upgrade6.php');
define('VERSION', '3.0.0 Gamma');
define('PREV_VERSION', '3.0.0 Beta 7');

$phrasegroups = array();
$specialtemplates = array();

// #############################################################################
// require the code that makes it all work...
require_once('./upgradecore.php');

// since the system is being changed slightly, make sure phrases still look ok
// before languages are rebuilt
$vbphrase = preg_replace('/\{([0-9])+\}/siU', '%\\1$s', $vbphrase);

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
	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "template DROP INDEX title");
	$DB_site->errno = 0;
	$DB_site->reporterror = 1;

	$duplicates = $DB_site->query("
		SELECT title, styleid, MAX(dateline) AS lastedit, COUNT(*) AS size
		FROM " . TABLE_PREFIX . "template
		GROUP BY title, styleid
		HAVING size > 1
	");
	$outputted = false;
	while ($dupe = $DB_site->fetch_array($duplicates))
	{
		if ($outputted == false)
		{
			echo $upgrade_phrases['upgrade6.php']['remove_duplicate_templates'];
			$outputted = true;
		}
		$saveid = $DB_site->query_first("
			SELECT templateid FROM " . TABLE_PREFIX . "template
			WHERE title = '" . addslashes($dupe['title']) . "'
				AND styleid = $dupe[styleid] AND dateline = $dupe[lastedit]
			ORDER BY templateid DESC
			LIMIT 1
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "template
			WHERE title = '" . addslashes($dupe['title']) . "' AND
				styleid = $dupe[styleid] AND templateid <> $saveid[templateid]
		");
	}
	if ($outputted == true)
	{
		echo $upgrade_phrases['upgrade6.php']['done'] . "<br /><br />";
	}

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "template ADD UNIQUE title (title, styleid)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "template");

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "search CHANGE sortorder sortorder VARCHAR(4) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "search");

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user CHANGE autosubscribe autosubscribe SMALLINT NOT NULL DEFAULT -1";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "user");

	// $daysprune needs to be able to store -1 -- remove UNSIGNED
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "forum CHANGE daysprune daysprune SMALLINT NOT NULL DEFAULT 0";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "forum");

	// -1 indicates all threads, just forgot to change the setting in the forum table to reflect that
	$query[] = "UPDATE " . TABLE_PREFIX . "forum SET daysprune = -1 WHERE daysprune = 1000";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "forum");

	// Add dateline field for thumbnails so that they uncache properly
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment ADD thumbnail_dateline INT UNSIGNED NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "attachment");

	// Populate thumbnail dateline with attachment dateline as a reference point
	$query[] = "UPDATE " . TABLE_PREFIX . "attachment SET thumbnail_dateline = dateline WHERE thumbnail <> ''";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "attachment");

	// Rename searchindex table as 'postindex'
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "searchindex RENAME " . TABLE_PREFIX . "postindex";
	$explain[] = $upgrade_phrases['upgrade6.php']['rename_searchindex_postindex'];

	// Large Thread Management
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "adminutil CHANGE title title VARCHAR(50) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "adminutil");

	// Add columns for announcements
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "announcement ADD allowbbcode SMALLINT UNSIGNED NOT NULL, ADD allowsmilies SMALLINT UNSIGNED NOT NULL, DROP pagehtml";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "announcement");

	$query[] = "UPDATE " . TABLE_PREFIX . "announcement SET allowbbcode = 1, allowsmilies = 1";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "announcement");

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "userpromotion CHANGE reputation reputation INT NOT NULL DEFAULT 0";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "userpromotion");

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "phrase DROP INDEX varname";
	$explain[] = $upgrade_phrases['upgrade6.php']['removing_redundant_index_phrase'];

	exec_queries();
}

// #############################################################################
// step 2
if ($step == 2)
{

	// Add new phrase group for holidays
	$DB_site->reporterror = 0;
	$DB_site->query_first("SELECT phrasegroup_holiday FROM " . TABLE_PREFIX . "language LIMIT 1");
	$DB_site->reporterror = 1;
	if ($DB_site->geterrno() != 0)
	{
		$query[] = "
			ALTER IGNORE TABLE " . TABLE_PREFIX . "language
			ADD phrasegroup_holiday mediumtext NOT NULL,
			ADD phrasegroup_posting mediumtext NOT NULL,
			ADD phrasegroup_poll mediumtext NOT NULL,
			ADD phrasegroup_fronthelp mediumtext NOT NULL,
			ADD phrasegroup_register mediumtext NOT NULL,
			ADD phrasegroup_search mediumtext NOT NULL,
			ADD phrasegroup_showthread mediumtext NOT NULL,
			ADD phrasegroup_postbit mediumtext NOT NULL,
			ADD phrasegroup_forumdisplay mediumtext NOT NULL,
			ADD phrasegroup_messaging mediumtext NOT NULL
		";
		$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "language");
	}

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['holiday']}', editrows = 3, fieldname = 'holiday' WHERE phrasetypeid = 35";
	$explain[] = $upgrade_phrases['upgrade6.php']['holiday_to_phrasetype'];

	$query[] = "UPDATE " . TABLE_PREFIX . "phrase SET phrasetypeid = 35 WHERE phrasetypeid = 5	AND varname LIKE 'holiday_%'";
	$explain[] = $upgrade_phrases['upgrade6.php']['moving_holiday_type'];

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['posting']}', editrows = 3, fieldname = 'posting' WHERE phrasetypeid = 36";
	$explain[] = sprintf($upgrade_phrases['upgrade6.php']['adding_x_to_phrasetype'], 'posting');

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['poll']}', editrows = 3, fieldname = 'poll' WHERE phrasetypeid = 37";
	$explain[] = sprintf($upgrade_phrases['upgrade6.php']['adding_x_to_phrasetype'], 'poll');

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['fronthelp']}', editrows = 3, fieldname = 'fronthelp' WHERE phrasetypeid = 38";
	$explain[] = sprintf($upgrade_phrases['upgrade6.php']['adding_x_to_phrasetype'], 'fronthelp');

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['register']}', editrows = 3, fieldname = 'register' WHERE phrasetypeid = 39";
	$explain[] = sprintf($upgrade_phrases['upgrade6.php']['adding_x_to_phrasetype'], 'register');

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['search']}', editrows = 3, fieldname = 'search' WHERE phrasetypeid = 40";
	$explain[] = sprintf($upgrade_phrases['upgrade6.php']['adding_x_to_phrasetype'], 'search');

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['showthread']}', editrows = 3, fieldname = 'showthread' WHERE phrasetypeid = 41";
	$explain[] = sprintf($upgrade_phrases['upgrade6.php']['adding_x_to_phrasetype'], 'showthread');

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['postbit']}', editrows = 3, fieldname = 'postbit' WHERE phrasetypeid = 42";
	$explain[] = sprintf($upgrade_phrases['upgrade6.php']['adding_x_to_phrasetype'], 'postbit');

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['forumdisplay']}', editrows = 3, fieldname = 'forumdisplay' WHERE phrasetypeid = 43";
	$explain[] = sprintf($upgrade_phrases['upgrade6.php']['adding_x_to_phrasetype'], 'forumdisplay');

	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['messaging']}', editrows = 3, fieldname = 'messaging' WHERE phrasetypeid = 44";
	$explain[] = sprintf($upgrade_phrases['upgrade6.php']['adding_x_to_phrasetype'], 'messaging');

	// Erase all birthdays that are of an invalid format due to a temporary bug in a previous beta version
	$query[]  = "UPDATE " . TABLE_PREFIX . "user SET birthday = '' WHERE length(birthday) <> 10 AND birthday <> ''";
	$explain[] = $upgrade_phrases['upgrade6.php']['update_invalid_birthdays'];

	// Add locale to the language table
	$DB_site->reporterror = 0;
	$DB_site->query_first("SELECT locale FROM " . TABLE_PREFIX . "language LIMIT 1");
	$DB_site->reporterror = 1;
	if ($DB_site->geterrno() != 0)
	{
		// error from query, so we don't have the columns
		$DB_site->errno = 0;
		$query[] = "
			ALTER TABLE " . TABLE_PREFIX . "language
			ADD locale VARCHAR(20) NOT NULL default ''
		";
		$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "language");
	}

	exec_queries();
}

// #############################################################################
// step 3
if ($step == 3)
{
	// scott's subscription changes
	// number 1 lets get us an expiry date added

	if ($DB_site->query_first("SHOW COLUMNS FROM " . TABLE_PREFIX . "subscriptionlog LIKE 'expirydate'"))
	{
		echo "<p>" . $upgrade_phrases['upgrade6.php']['step_already_run'] . "</p>";
	}
	else
	{
		require_once('./includes/functions_subscriptions.php');
		$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "subscriptionlog ADD expirydate INT(10) UNSIGNED DEFAULT '0'");

		// ok now lets fetch the people who are currently active and need some expiry times :)
		cache_user_subscriptions();
		$time = array('D' => 86400, 'W' => 604800, 'M' => 2678400, 'Y' => 31536000);
		$peoples = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "subscriptionlog WHERE expirydate = 0");
		while ($people = $DB_site->fetch_array($peoples))
		{
			$expirydate = $people['regdate'] + ($subscriptioncache["$people[subscriptionid]"]['length'] * $time[$subscriptioncache["$people[subscriptionid]"]['units']]);
			$DB_site->query("UPDATE " . TABLE_PREFIX . "subscriptionlog SET expirydate = $expirydate WHERE userid = $people[userid]");
		}

		echo "<p>" . $upgrade_phrases['upgrade6.php']['updating_subscription_expiry_times'] . "</p>";
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

	if (isset($vboptions['showdeficon']))
	{
		if ($vboptions['showdeficon'] == 1)
		{ // lets show that bug who's boss! (Scott)
			$vboptions['showdeficon'] = "images/icons/icon1.gif";
		}
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