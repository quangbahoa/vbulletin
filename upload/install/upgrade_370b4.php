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

define('THIS_SCRIPT', 'upgrade_370b4.php');
define('VERSION', '3.7.0 Beta 4');
define('PREV_VERSION', '3.7.0 Beta 3');

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

	if (!isset($vbulletin->bf_misc_moderatorpermissions2['caneditpicturecomments']))
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$upgradecore_phrases[wrong_bitfield_xml]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'album', 1, 3),
		'album',
		'state',
		'enum',
		array('attributes' => "('public', 'private', 'profile')", 'null' => false, 'default' => 'public')
	);

	if ($upgrade->field_exists('album', 'public'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'album', 2, 3),
			"UPDATE " . TABLE_PREFIX . "album SET state = 'private' WHERE public = 0"
		);

		$upgrade->drop_field(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'album', 3, 3),
			'album',
			'public'
		);
	}

	// Change the extension field to binary - all extension fields must be binary
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'picture', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "picture CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
	);

	// setup picture comments

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'picturecomment_hash'),
		"CREATE TABLE " . TABLE_PREFIX . "picturecomment_hash (
			postuserid INT UNSIGNED NOT NULL DEFAULT '0',
			pictureid INT UNSIGNED NOT NULL DEFAULT '0',
			dupehash VARCHAR(32) NOT NULL DEFAULT '',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			KEY postuserid (postuserid, dupehash),
			KEY dateline (dateline)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'picturecomment'),
		"CREATE TABLE " . TABLE_PREFIX . "picturecomment (
			commentid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			pictureid INT UNSIGNED NOT NULL DEFAULT '0',
			postuserid INT UNSIGNED NOT NULL DEFAULT '0',
			postusername varchar(100) NOT NULL DEFAULT '',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			state ENUM('visible','moderation','deleted') NOT NULL DEFAULT 'visible',
			title VARCHAR(255) NOT NULL DEFAULT '',
			pagetext MEDIUMTEXT,
			ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
			allowsmilie SMALLINT NOT NULL DEFAULT '1',
			reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
			messageread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (commentid),
			KEY pictureid (pictureid, dateline, state),
			KEY postuserid (postuserid, pictureid, state)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'deletionlog', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "deletionlog CHANGE type type ENUM('post', 'thread', 'visitormessage', 'groupmessage', 'picturecomment') NOT NULL DEFAULT 'post'"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderation', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "moderation CHANGE type type ENUM('thread', 'reply', 'visitormessage', 'groupmessage', 'picturecomment') NOT NULL DEFAULT 'thread'"
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 2),
		'user',
		'pcunreadcount',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 2),
		'user',
		'pcmoderatedcount',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 2)
{
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'poll', 1, 1),
		"DELETE pollvote, poll
		FROM " . TABLE_PREFIX . "poll AS poll
		LEFT JOIN " . TABLE_PREFIX . "pollvote AS pollvote ON (poll.pollid = pollvote.pollid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (poll.pollid = thread.pollid)
		WHERE thread.threadid IS NULL"
	);

	// Clean out orphan calendar custom fields
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'calendarcustomfield', 1, 1),
		"DELETE calendarcustomfield
		FROM " . TABLE_PREFIX . "calendarcustomfield AS calendarcustomfield
		LEFT JOIN " . TABLE_PREFIX . "calendar AS calendar ON (calendar.calendarid = calendarcustomfield.calendarid)
		WHERE calendar.calendarid IS NULL"
	);

	// Reinsert the boomark data since the install script had a typo, wasn't inserting
	/* DEFAULT DATA */
	$bookmarkcount = $db->query_first("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "bookmarksite
	");
	if ($bookmarkcount['total'] == 0)
	{
		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . "bookmarksite"),
			"INSERT INTO " . TABLE_PREFIX . "bookmarksite
				(title, active, displayorder, iconpath, url)
			VALUES
				('Digg',        1, 10, 'bookmarksite_digg.gif',        'http://digg.com/submit?phrase=2&amp;url={URL}&amp;title={TITLE}'),
				('del.icio.us', 1, 20, 'bookmarksite_delicious.gif',   'http://del.icio.us/post?url={URL}&amp;title={TITLE}'),
				('StumbleUpon', 1, 30, 'bookmarksite_stumbleupon.gif', 'http://www.stumbleupon.com/submit?url={URL}&amp;title={TITLE}'),
				('Google',      1, 40, 'bookmarksite_google.gif',      'http://www.google.com/bookmarks/mark?op=edit&amp;output=popup&amp;bkmk={URL}&amp;annotation={TITLE}')
			"
		);
	}

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 1, 1),
		"UPDATE " . TABLE_PREFIX . "usergroup SET
			albumpermissions = albumpermissions |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canreplyothers'] . ", " . $vbulletin->bf_ugp_albumpermissions['canpiccomment'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['caneditpost'] . ", " . $vbulletin->bf_ugp_albumpermissions['caneditownpiccomment'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['candeletepost'] . ", " . $vbulletin->bf_ugp_albumpermissions['candeleteownpiccomment'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['followforummoderation'] . ", " . $vbulletin->bf_ugp_albumpermissions['commentfollowforummoderation'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['caneditpost'] . "
					OR forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['candeletepost'] . ", " . $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment'] . ", 0)
		"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderator', 1, 1),
		"UPDATE " . TABLE_PREFIX . "moderator SET
			permissions2 = permissions2 |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['caneditposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['caneditpicturecomments'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['candeleteposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['candeletepicturecomments'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['canremoveposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['canremovepicturecomments'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['canmoderateposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['canmoderatepicturecomments'] . ", 0)
		"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 1, 1),
		"UPDATE " . TABLE_PREFIX . "setting SET
			value = '1'
		WHERE varname = 'contactustype' AND value = '2'"
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
