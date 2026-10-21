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

define('THIS_SCRIPT', 'upgrade_370b3.php');
define('VERSION', '3.7.0 Beta 3');
define('PREV_VERSION', '3.7.0 Beta 2');

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
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	if (!isset($vbulletin->bf_misc_moderatorpermissions2['caneditvisitormessages']))
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$upgradecore_phrases[wrong_bitfield_xml]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrasetype', 1, 1),
		"UPDATE " . TABLE_PREFIX . "phrasetype SET special = 1 WHERE fieldname = 'hvquestion'"
	);

	// support for limited social groups
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 2),
		'user',
		'socgroupinvitecount',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 2),
		'user',
		'socgroupreqcount',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroup', 1, 2),
		'socialgroup',
		'type',
		'enum',
		array('attributes' => "('public', 'moderated', 'inviteonly')", 'null' => false, 'default' => 'public')
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroup', 2, 2),
		'socialgroup',
		'moderatedmembers',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroupmember', 1, 4),
		'socialgroupmember',
		'type',
		'enum',
		array('attributes' => "('member', 'moderated', 'invited')", 'null' => false, 'default' => 'member')
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroupmember', 2, 4),
		'socialgroupmember',
		'groupid',
		array('groupid', 'type')
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroupmember', 3, 4),
		'socialgroupmember',
		'userid'
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroupmember', 4, 4),
		'socialgroupmember',
		'userid',
		array('userid', 'type')
	);

	$upgrade->execute();
}

if ($vbulletin->GPC['step'] == 2)
{
	// Add default values

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgrouppicture', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "socialgrouppicture
			CHANGE groupid groupid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE pictureid pictureid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'notice', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "notice
			CHANGE title title VARCHAR(250) NOT NULL DEFAULT '',
			CHANGE displayorder displayorder INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE active active SMALLINT UNSIGNED NOT NULL DEFAULT '0'
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'noticecriteria', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "noticecriteria
			CHANGE noticeid noticeid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE criteriaid criteriaid VARCHAR(250) NOT NULL DEFAULT '',
			CHANGE condition1 condition1 VARCHAR(250) NOT NULL DEFAULT '',
			CHANGE condition2 condition2 VARCHAR(250) NOT NULL DEFAULT '',
			CHANGE condition3 condition3 VARCHAR(250) NOT NULL DEFAULT ''
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'tag', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "tag
			CHANGE tagtext tagtext VARCHAR(100) NOT NULL DEFAULT '',
			CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'tagthread', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "tagthread
			CHANGE tagid tagid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE threadid threadid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'tagsearch', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "tagsearch
			CHANGE tagid tagid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postedithistory', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "postedithistory
			CHANGE postid postid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE username username VARCHAR(100) NOT NULL DEFAULT '',
			CHANGE title title VARCHAR(250) NOT NULL DEFAULT '',
			CHANGE iconid iconid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE reason reason VARCHAR(200) NOT NULL DEFAULT '',
			CHANGE original original SMALLINT NOT NULL DEFAULT '0'
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usercsscache', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "usercsscache
			CHANGE cachedcss cachedcss TEXT
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'visitormessage', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "visitormessage
			CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE postuserid postuserid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE postusername postusername VARCHAR(100) NOT NULL DEFAULT '',
			CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE ipaddress ipaddress INT UNSIGNED NOT NULL DEFAULT '0'
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'groupmessage', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "groupmessage
			CHANGE groupid groupid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE postuserid postuserid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE postusername postusername VARCHAR(100) NOT NULL DEFAULT '',
			CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE ipaddress ipaddress INT UNSIGNED NOT NULL DEFAULT '0'
	");

	if ($upgrade->field_exists('album', 'picturecount'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'album', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "album
				CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE createdate createdate INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE lastpicturedate lastpicturedate INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE picturecount picturecount INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE title title VARCHAR(100) NOT NULL DEFAULT '',
				CHANGE description description TEXT,
				CHANGE coverpictureid coverpictureid INT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'albumpicture', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "albumpicture
			CHANGE albumid albumid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE pictureid pictureid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
	");

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'picture', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "picture
			CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE caption caption TEXT,
			CHANGE extension extension VARCHAR(20) NOT NULL DEFAULT '',
			CHANGE filedata filedata MEDIUMBLOB,
			CHANGE filesize filesize INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE width width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE height height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE thumbnail thumbnail MEDIUMBLOB,
			CHANGE thumbnail_filesize thumbnail_filesize INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE thumbnail_width thumbnail_width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE thumbnail_height thumbnail_height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE thumbnail_dateline thumbnail_dateline INT UNSIGNED NOT NULL DEFAULT '0',
			CHANGE idhash idhash VARCHAR(32) NOT NULL DEFAULT '',
			CHANGE reportthreadid reportthreadid INT UNSIGNED NOT NULL DEFAULT '0'
	");

	// For MySQL 5 compat, TEXT fields do not have NOT NULL or DEFAULT
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'rssfeed', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "rssfeed CHANGE url url TEXT"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'socialgroup', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "socialgroup CHANGE description description TEXT"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'usercsscache', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "usercsscache CHANGE cachedcss cachedcss TEXT"
	);

	$upgrade->execute();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 3)
{
	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);

	// need to reflect new options
	build_bbcode_cache();
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
