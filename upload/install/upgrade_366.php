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

define('THIS_SCRIPT', 'upgrade_366.php');
define('VERSION', '3.6.6');
define('PREV_VERSION', '3.6.5');

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
if ($vbulletin->GPC['step'] == 1)
{
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'avatar', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "avatar CHANGE minimumposts minimumposts INT UNSIGNED NOT NULL DEFAULT '0'"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'ranks', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "ranks CHANGE minposts minposts INT UNSIGNED NOT NULL DEFAULT '0'"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usertitle', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "usertitle CHANGE minposts minposts INT UNSIGNED NOT NULL DEFAULT '0'"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'calendar', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "calendar CHANGE neweventemail neweventemail TEXT"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 1, 2),
		"ALTER TABLE " . TABLE_PREFIX . "forum CHANGE newpostemail newpostemail TEXT"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 2, 2),
		"ALTER TABLE " . TABLE_PREFIX . "forum CHANGE newthreademail newthreademail TEXT"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'datastore', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "datastore CHANGE title title VARCHAR(50) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "userlist"),
		"CREATE TABLE " . TABLE_PREFIX . "userlist (
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			relationid INT UNSIGNED NOT NULL DEFAULT '0',
			type ENUM('buddy', 'ignore') NOT NULL DEFAULT 'buddy',
			PRIMARY KEY (userid, relationid, type),
			KEY userid (relationid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "profilefieldcategory"),
		"CREATE TABLE " . TABLE_PREFIX . "profilefieldcategory (
			profilefieldcategoryid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
			displayorder SMALLINT UNSIGNED NOT NULL,
			PRIMARY KEY (profilefieldcategoryid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'profilefield', 1, 2),
		'profilefield',
		'profilefieldcategoryid',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'profilefield', 2, 2),
		'profilefield',
		'profilefieldcategoryid',
		'profilefieldcategoryid'
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'externalcache', 1, 2),
		'externalcache',
		'forumid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'externalcache', 2, 2),
		'externalcache',
		'forumid',
		'forumid'
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'template', 1, 2),
		'template',
		'title'
	);

	/* this deals with the older templates */
	$badtemplates = $db->query_read("
		SELECT styleid, title, templatetype, MAX(dateline) AS newest, COUNT(*) AS total
		FROM " . TABLE_PREFIX . "template
		GROUP BY styleid, title, templatetype
		HAVING total > 1
	");
	while ($template = $db->fetch_array($badtemplates))
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "template
			WHERE styleid = $template[styleid]
				AND title = '" . $db->escape_string($template['title']) . "'
				AND templatetype = '" . $db->escape_string($template['templatetype']) . "'
				AND dateline < " . intval($template['newest']) . "
		");
	}

	/* now to deal with those that have the same date */
	$badtemplates = $db->query_read("
		SELECT styleid, title, templatetype, MAX(templateid) AS newest, COUNT(*) AS total
		FROM " . TABLE_PREFIX . "template
		GROUP BY styleid, title, templatetype
		HAVING total > 1
	");
	while ($template = $db->fetch_array($badtemplates))
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "template
			WHERE styleid = $template[styleid]
				AND title = '" . $db->escape_string($template['title']) . "'
				AND templatetype = '" . $db->escape_string($template['templatetype']) . "'
				AND templateid <> " . intval($template['newest']) . "
		");
	}

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'template', 2, 2),
		'template',
		'title',
		array('title', 'styleid', 'templatetype'),
		'unique'
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 2)
{
	// here goes code that populates userlist from usertextfield.buddylist and usertextfield.ignorelist
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 3)
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
