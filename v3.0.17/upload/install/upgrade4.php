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

define('THIS_SCRIPT', 'upgrade4.php');
define('VERSION', '3.0.0 Beta 6');
define('PREV_VERSION', '3.0.0 Beta 5');

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
// add index to thread table
if ($step == 1)
{
	require_once('./includes/functions_misc.php');

	// add postuserid index to thread table (eek!)
	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade4.php']['alter_thread_table'], TABLE_PREFIX) . "</i>");
	$t = microtime();

	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "thread ADD INDEX (postuserid)");

	require_once('./includes/functions_misc.php');
	echo_flush('<br />' . sprintf($vbphrase['query_took'], number_format(fetch_microtime_difference($t), 2)) . '</p>');
}

// #############################################################################
// various alters
if ($step == 2)
{
	// Make sure to modify the schema in mysql-schema.php also!

	// alter attachment table, add index on userid for misc.php?do=attachments
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment ADD INDEX userid (userid)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "attachment");

	// alter style table to add the new editorstyles field
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "style ADD editorstyles mediumtext NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "style");

	// remove the troublesome avatar cache
	$query[] = "DELETE FROM " . TABLE_PREFIX . "datastore WHERE title = 'avatarcache'";
	$explain[] = $upgrade_phrases['upgrade4.php']['remove_avatar_cache'];

	// Add three date overrides to the language table
	$DB_site->reporterror = 0;
	$DB_site->query_first("SELECT registereddateoverride FROM " . TABLE_PREFIX . "language LIMIT 1");
	$DB_site->reporterror = 1;
	if ($DB_site->geterrno() != 0)
	{
		// error from query, so we don't have the columns
		$DB_site->errno = 0;
		$query[] = "
			ALTER TABLE " . TABLE_PREFIX . "language
				ADD registereddateoverride VARCHAR(20) NOT NULL,
				ADD calformat1override VARCHAR(20) NOT NULL,
				ADD calformat2override VARCHAR(20) NOT NULL
		";
		$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "language");
	}

	// Change forum default view setting from -1 to 0 and view all from 1000 to -1
	$query[] = "UPDATE " . TABLE_PREFIX . "user SET daysprune = 0 WHERE daysprune = -1";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "user");
	$query[] = "UPDATE " . TABLE_PREFIX . "user SET daysprune = -1 WHERE daysprune = 1000";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "user");

	// Add a primary key to the repuation table so that we can edit comments in the modcp
	$query[] = "
		ALTER TABLE " . TABLE_PREFIX . "reputation
			ADD reputationid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "reputation");

	$query[] = "
		ALTER TABLE " . TABLE_PREFIX . "bbcode
			ADD buttonimage VARCHAR(250) NOT NULL DEFAULT ''
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "bbcode");

	$query[] = "
		UPDATE " . TABLE_PREFIX . "bbcode SET
			buttonimage = 'images/editor/quote.gif'
		WHERE bbcodetag = 'quote'
			AND twoparams = 0
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "bbcode");

	// Add 2 fields to the userban table to track the user's custom title
	$query[] = "
		ALTER TABLE " . TABLE_PREFIX . "userban
			ADD customtitle SMALLINT NOT NULL,
			ADD usertitle VARCHAR( 250 ) NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "userban");

	// update the remove bans scheduled task to run hourly
	$query[] = "
		UPDATE " . TABLE_PREFIX . "cron SET
			nextrun = " . (TIMENOW + 1500) . ",
			weekday = -1,
			day = -1,
			hour = -1,
			minute = 15
		WHERE filename = './includes/cron/removebans.php'
	";
	$explain[] = $upgrade_phrases['upgrade4.php']['update_userban'];

	// Add a field to the custom profile table to flag whether html is allowed
	$query[] = "
		ALTER TABLE " . TABLE_PREFIX . "profilefield
			ADD html SMALLINT NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "profilefield");

	exec_queries();

	// since we have altered the bbcode table, we need to rebuild the bbcode cache
	build_bbcode_cache();
}

// #############################################################################
// more alters
if ($step == 3)
{
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscription ADD active SMALLINT UNSIGNED NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "subscription");

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscription CHANGE cost cost VARCHAR(255) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "subscription");

	$query[] = "UPDATE " . TABLE_PREFIX . "subscription SET active = 1";
	$explain[] = $upgrade_phrases['upgrade4.php']['subscription_active'];

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscription DROP methods";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "subscription");

	exec_queries();

	$subscriptions = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "subscription");
	while ($subscription = $DB_site->fetch_array($subscriptions))
	{
		$cost = '';
		$cost['usd'] = number_format($subscription['cost'], 2);
		$cost['gbp'] = '0.00';
		$cost['eur'] = '0.00';
		$cost = serialize($cost);
		$DB_site->query("UPDATE " . TABLE_PREFIX . "subscription SET cost = '" . addslashes($cost) . "' WHERE subscriptionid = $subscription[subscriptionid]");
	}
}

// #############################################################################
// update some template names for the new WYSIWYG editor
if ($step == 4)
{
	$templates = array(
		// message editor menu contents
		'vbcode_font_options' => 'editor_jsoptions_font',
		'vbcode_size_options' => 'editor_jsoptions_size',
		// toolbar
		'newpost_messagearea_scripts' => 'editor_clientscript',
		'newpost_messagearea_toolbaroff' => 'editor_toolbar_off',
		'newpost_messagearea_standard' => 'editor_toolbar_standard',
		'newpost_messagearea_wysiwyg' => 'editor_toolbar_wysiwyg',
		// smilie box
		'newpost_smiliebox_category' => 'editor_smiliebox_category',
		'newpost_smiliebox_row' => 'editor_smiliebox_row',
		'newpost_smiliebox_straggler' => 'editor_smiliebox_straggler',
		'newpost_smiliebox' => 'editor_smiliebox',
		// smilie click button
		'newpost_smilie_wysiwyg' => 'editor_smilie_wysiwyg',
		'newpost_smilie_standard' => 'editor_smilie_standard',
	);

	foreach ($templates AS $oldtitle => $newtitle)
	{
		$query[] = "UPDATE " . TABLE_PREFIX . "template SET title = '$newtitle' WHERE title = '$oldtitle'";
		$explain[] = sprintf($upgrade_phrases['upgrade4.php']['rename_old_template'], $oldtitle, $newtitle);
	}

	// delete the vbcode_color_options template - colors are noe defined in the clientscript/vbulletin_editor.js file
	$query[] = "DELETE FROM " . TABLE_PREFIX . "template WHERE title = 'vbcode_color_options'";
	$explain[] = $upgrade_phrases['upgrade4.php']['delete_vbcode_color'];

	// capitalize default smilie titles properly
	$query[] = "UPDATE " . TABLE_PREFIX . "smilie SET title =
	CASE title
		WHEN 'Smile' THEN 'Smilie'
		WHEN 'Embarrasment' THEN 'Embarrassment'
		WHEN 'Big Grin' THEN 'Big Grin'
		WHEN 'Wink' THEN 'Wink'
		WHEN 'Stick Out Tongue' THEN 'Stick Out Tongue'
		WHEN 'Cool' THEN 'Cool'
		WHEN 'Roll Eyes (Sarcastic)' THEN 'Roll Eyes (Sarcastic)'
		WHEN 'Mad' THEN 'Mad'
		WHEN 'EEK!' THEN 'EEK!'
		WHEN 'Confused' THEN 'Confused'
		WHEN 'Frown' THEN 'Frown'
	ELSE title END";
	$explain[] = $upgrade_phrases['upgrade4.php']['smilie_fixes'];

	exec_queries();
}

// #############################################################################
// import vbulletin options
if ($step == 5)
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
if ($step == 6)
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
if ($step == 7)
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
if ($step == 8)
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
if ($step == 9)
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