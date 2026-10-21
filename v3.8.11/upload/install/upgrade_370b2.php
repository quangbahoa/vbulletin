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

define('THIS_SCRIPT', 'upgrade_370b2.php');
define('VERSION', '3.7.0 Beta 2');
define('PREV_VERSION', '3.6.8+');
define('VERSION_COMPAT_STARTS', '3.6.8');
define('VERSION_COMPAT_ENDS', '3.6.99');

$phrasegroups = array();
$specialtemplates = array();

// #############################################################################
// require the code that makes it all work...
require_once('./upgradecore.php');

// #############################################################################
// welcome step
if ($vbulletin->GPC['step'] == 'welcome')
{
	if (version_compare($vbulletin->options['templateversion'], VERSION_COMPAT_STARTS, '>=') AND version_compare($vbulletin->options['templateversion'], VERSION_COMPAT_ENDS, '<'))
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

	/* Create Tables */

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'userchangelog'),
		"CREATE TABLE " . TABLE_PREFIX . "userchangelog (
			changeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			fieldname VARCHAR(250) NOT NULL DEFAULT '',
			newvalue VARCHAR(250) NOT NULL DEFAULT '',
			oldvalue VARCHAR(250) NOT NULL DEFAULT '',
			adminid INT UNSIGNED NOT NULL DEFAULT '0',
			change_time INT UNSIGNED NOT NULL DEFAULT '0',
			change_uniq VARCHAR(32) NOT NULL DEFAULT '',
			PRIMARY KEY  (changeid),
			KEY userid (userid,change_time),
			KEY change_time (change_time),
			KEY change_uniq (change_uniq),
			KEY fieldname (fieldname,change_time),
			KEY adminid (adminid,change_time)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "forumprefix"),
		"CREATE TABLE " . TABLE_PREFIX . "forumprefixset (
			forumid INT UNSIGNED NOT NULL DEFAULT '0',
			prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
			PRIMARY KEY (forumid, prefixsetid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "socialgroup"),
		"CREATE TABLE " . TABLE_PREFIX . "socialgroup (
			groupid INT unsigned NOT NULL auto_increment,
			name VARCHAR(255) NOT NULL DEFAULT '',
			description TEXT,
			creatoruserid INT unsigned NOT NULL DEFAULT '0',
			dateline INT unsigned NOT NULL DEFAULT '0',
			members INT unsigned NOT NULL DEFAULT '0',
			picturecount INT unsigned NOT NULL DEFAULT '0',
			lastpost INT unsigned NOT NULL DEFAULT '0',
			lastposter VARCHAR(255) NOT NULL DEFAULT '',
			lastposterid INT UNSIGNED NOT NULL DEFAULT '0',
			lastgmid INT UNSIGNED NOT NULL DEFAULT '0',
			visible INT UNSIGNED NOT NULL DEFAULT '0',
			deleted INT UNSIGNED NOT NULL DEFAULT '0',
			moderation INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY  (groupid),
			KEY creatoruserid (creatoruserid),
			KEY dateline (dateline),
			FULLTEXT KEY name (name, description)
		) ENGINE = MyISAM",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "socialgroupmember"),
		"CREATE TABLE " . TABLE_PREFIX . "socialgroupmember (
			userid INT unsigned NOT NULL DEFAULT '0',
			groupid INT unsigned NOT NULL DEFAULT '0',
			dateline INT unsigned NOT NULL DEFAULT '0',
			PRIMARY KEY (groupid, userid),
			KEY userid (userid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "socialgrouppicture"),
		"CREATE TABLE " . TABLE_PREFIX . "socialgrouppicture (
			groupid INT UNSIGNED NOT NULL DEFAULT '0',
			pictureid INT UNSIGNED NOT NULL DEFAULT '0',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (groupid, pictureid),
			KEY groupid (groupid, dateline),
			KEY pictureid (pictureid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "prefix"),
		"CREATE TABLE " . TABLE_PREFIX . "prefix (
			prefixid VARCHAR(25) NOT NULL DEFAULT '',
			prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
			displayorder INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (prefixid),
			KEY prefixsetid (prefixsetid, displayorder)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "prefixset"),
		"CREATE TABLE " . TABLE_PREFIX . "prefixset (
			prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
			displayorder INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (prefixsetid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'notice'),
		"CREATE TABLE " . TABLE_PREFIX . "notice (
			noticeid INT UNSIGNED NOT NULL auto_increment,
			title VARCHAR(250) NOT NULL DEFAULT '',
			displayorder INT UNSIGNED NOT NULL DEFAULT '0',
			persistent SMALLINT UNSIGNED NOT NULL default '0',
			active SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (noticeid),
			KEY active (active)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'noticecriteria'),
		"CREATE TABLE " . TABLE_PREFIX . "noticecriteria (
			noticeid INT UNSIGNED NOT NULL DEFAULT '0',
			criteriaid VARCHAR(250) NOT NULL DEFAULT '',
			condition1 VARCHAR(250) NOT NULL DEFAULT '',
			condition2 VARCHAR(250) NOT NULL DEFAULT '',
			condition3 VARCHAR(250) NOT NULL DEFAULT '',
			PRIMARY KEY (noticeid,criteriaid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'postlog'),
		"CREATE TABLE " . TABLE_PREFIX . "postlog (
			postid INT UNSIGNED NOT NULL DEFAULT '0',
			useragent CHAR(100) NOT NULL DEFAULT '',
			ip INT UNSIGNED NOT NULL DEFAULT '0',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (postid),
			KEY dateline (dateline),
			KEY ip (ip)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'spamlog'),
		"CREATE TABLE " . TABLE_PREFIX . "spamlog (
			postid INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (postid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// create bookmarksites table
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'bookmarksite'),
		"CREATE TABLE " . TABLE_PREFIX . "bookmarksite (
			bookmarksiteid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(250) NOT NULL DEFAULT '',
			iconpath VARCHAR(250) NOT NULL DEFAULT '',
			active  SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			displayorder INT UNSIGNED NOT NULL DEFAULT '0',
			url VARCHAR(250) NOT NULL DEFAULT '',
			PRIMARY KEY (bookmarksiteid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'tag'),
		"CREATE TABLE " . TABLE_PREFIX . "tag (
			tagid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			tagtext VARCHAR(100) NOT NULL DEFAULT '',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (tagid),
			UNIQUE KEY tagtext (tagtext)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'tagthread'),
		"CREATE TABLE " . TABLE_PREFIX . "tagthread (
			tagid INT UNSIGNED NOT NULL DEFAULT '0',
			threadid INT UNSIGNED NOT NULL DEFAULT '0',
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (tagid, threadid),
			KEY threadid (threadid, userid),
			KEY dateline (dateline)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'tagsearch'),
		"CREATE TABLE " . TABLE_PREFIX . "tagsearch (
			tagid INT UNSIGNED NOT NULL DEFAULT '0',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			KEY (tagid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'postedithistory'),
		"CREATE TABLE " . TABLE_PREFIX . "postedithistory (
			postedithistoryid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			postid INT UNSIGNED NOT NULL DEFAULT '0',
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			username VARCHAR(100) NOT NULL DEFAULT '',
			title VARCHAR(250) NOT NULL DEFAULT '',
			iconid INT UNSIGNED NOT NULL DEFAULT '0',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			reason VARCHAR(200) NOT NULL DEFAULT '',
			original SMALLINT NOT NULL DEFAULT '0',
			pagetext MEDIUMTEXT,
			PRIMARY KEY  (postedithistoryid),
			KEY postid (postid,userid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'usercss'),
		"CREATE TABLE " . TABLE_PREFIX . "usercss (
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			selector VARCHAR(30) NOT NULL DEFAULT '',
			property VARCHAR(30) NOT NULL DEFAULT '',
			value VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (userid, selector, property),
			KEY property (property, userid, value(20))
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'usercsscache'),
		"CREATE TABLE " . TABLE_PREFIX . "usercsscache (
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			cachedcss TEXT,
			buildpermissions INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (userid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'tachyforumcounter'),
		"CREATE TABLE " . TABLE_PREFIX . "tachyforumcounter (
			userid int(10) unsigned NOT NULL default '0',
			forumid smallint(5) unsigned NOT NULL default '0',
			threadcount mediumint(8) unsigned NOT NULL default '0',
			replycount int(10) unsigned NOT NULL default '0',
			PRIMARY KEY  (userid,forumid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'tachythreadcounter'),
		"CREATE TABLE " . TABLE_PREFIX . "tachythreadcounter (
			userid int(10) unsigned NOT NULL default '0',
			threadid int(10) unsigned NOT NULL default '0',
			replycount int(10) unsigned NOT NULL default '0',
			PRIMARY KEY  (userid,threadid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'profilevisitor'),
		"CREATE TABLE " . TABLE_PREFIX . "profilevisitor (
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			visitorid INT UNSIGNED NOT NULL DEFAULT '0',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			visible SMALLINT UNSIGNED NOT NULL DEFAULT '1',
			PRIMARY KEY (visitorid, userid),
			KEY userid (userid, visible, dateline)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'visitormessage'),
		"CREATE TABLE " . TABLE_PREFIX . "visitormessage (
		  vmid INT UNSIGNED NOT NULL auto_increment,
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			postuserid INT UNSIGNED NOT NULL DEFAULT '0',
			postusername VARCHAR(100) NOT NULL  DEFAULT '',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			state ENUM('visible','moderation','deleted') NOT NULL default 'visible',
			title VARCHAR(255) NOT NULL DEFAULT '',
			pagetext MEDIUMTEXT,
			ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
			allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
			messageread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		  PRIMARY KEY  (vmid),
			KEY postuserid (postuserid, userid, state),
			KEY userid (userid, dateline, state)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'visitormessage_hash'),
		"CREATE TABLE " . TABLE_PREFIX . "visitormessage_hash (
			postuserid INT UNSIGNED NOT NULL DEFAULT '0',
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			dupehash VARCHAR(32) NOT NULL DEFAULT '',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			KEY postuserid (postuserid, dupehash),
			KEY dateline (dateline)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'groupmessage'),
		"CREATE TABLE " . TABLE_PREFIX . "groupmessage (
			gmid INT UNSIGNED NOT NULL auto_increment,
			groupid INT UNSIGNED NOT NULL DEFAULT '0',
			postuserid INT UNSIGNED NOT NULL DEFAULT '0',
			postusername VARCHAR(100) NOT NULL DEFAULT '',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			state ENUM('visible','moderation','deleted') NOT NULL default 'visible',
			title VARCHAR(255) NOT NULL DEFAULT '',
			pagetext MEDIUMTEXT,
			ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
			allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY  (gmid),
			KEY postuserid (postuserid, groupid, state),
			KEY groupid (groupid, dateline, state)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'groupmessage_hash'),
		"CREATE TABLE " . TABLE_PREFIX . "groupmessage_hash (
			postuserid INT UNSIGNED NOT NULL DEFAULT '0',
			groupid INT UNSIGNED NOT NULL DEFAULT '0',
			dupehash VARCHAR(32) NOT NULL DEFAULT '',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			KEY postuserid (postuserid, dupehash),
			KEY dateline (dateline)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'album'),
		"CREATE TABLE " . TABLE_PREFIX . "album (
			albumid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			createdate INT UNSIGNED NOT NULL DEFAULT '0',
			lastpicturedate INT UNSIGNED NOT NULL DEFAULT '0',
			picturecount INT UNSIGNED NOT NULL DEFAULT '0',
			title VARCHAR(100) NOT NULL DEFAULT '',
			description TEXT,
			public SMALLINT UNSIGNED NOT NULL DEFAULT '1',
			coverpictureid INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (albumid),
			KEY userid (userid, lastpicturedate)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'albumpicture'),
		"CREATE TABLE " . TABLE_PREFIX . "albumpicture (
			albumid INT UNSIGNED NOT NULL DEFAULT '0',
			pictureid INT UNSIGNED NOT NULL DEFAULT '0',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (albumid, pictureid),
			KEY albumid (albumid, dateline),
			KEY pictureid (pictureid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'picture'),
		"CREATE TABLE " . TABLE_PREFIX . "picture (
			pictureid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			caption TEXT,
			extension VARCHAR(20) NOT NULL DEFAULT '',
			filedata MEDIUMBLOB,
			filesize INT UNSIGNED NOT NULL DEFAULT '0',
			width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			thumbnail MEDIUMBLOB,
			thumbnail_filesize INT UNSIGNED NOT NULL DEFAULT '0',
			thumbnail_width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			thumbnail_height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			thumbnail_dateline INT UNSIGNED NOT NULL DEFAULT '0',
			idhash VARCHAR(32) NOT NULL DEFAULT '',
			reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (pictureid),
			KEY userid (userid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'humanverify'),
		"CREATE TABLE " . TABLE_PREFIX . "humanverify (
			hash CHAR(32) NOT NULL DEFAULT '',
			answer MEDIUMTEXT,
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			viewed SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			KEY hash (hash),
			KEY dateline (dateline)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'hvanswer'),
		"CREATE TABLE " . TABLE_PREFIX . "hvanswer (
 			answerid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 			questionid INT NOT NULL DEFAULT '0',
 			answer VARCHAR(255) NOT NULL DEFAULT '',
 			dateline INT UNSIGNED NOT NULL DEFAULT '0',
 			INDEX (questionid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'hvquestion'),
		"CREATE TABLE " . TABLE_PREFIX . "hvquestion (
 			questionid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 			regex VARCHAR(255) NOT NULL DEFAULT '',
 			dateline INT UNSIGNED NOT NULl DEFAULT '0'
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 2)
{
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 2),
		'thread',
		'prefixid',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 2, 3),
		'thread',
		'prefixid',
		array('prefixid', 'forumid')
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 3, 3),
		'thread',
		'taglist',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'deletionlog', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "deletionlog CHANGE type type ENUM('post', 'thread', 'visitormessage', 'groupmessage') NOT NULL DEFAULT 'post'"
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customavatar', 1, 3),
		'customavatar',
		'filedata_thumb',
		'mediumblob',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customavatar', 2, 3),
		'customavatar',
		'width_thumb',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customavatar', 3, 3),
		'customavatar',
		'height_thumb',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 3)
{

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 1, 2),
		'forum',
		'lastprefixid',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 2, 2),
		'forum',
		'imageprefix',
		'varchar',
		array('length' => 100, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'tachyforumpost', 1, 1),
		'tachyforumpost',
		'lastprefixid',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'rssfeed', 1, 1),
		'rssfeed',
		'prefixid',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'search', 1, 1),
		'search',
		'prefixchoice',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 1, 5),
		'language',
		'phrasegroup_prefix',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 2, 5),
		'language',
		'phrasegroup_socialgroups',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 3, 5),
		'language',
		'phrasegroup_prefixadmin',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 4, 5),
		'language',
		'phrasegroup_notice',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 5, 5),
		'language',
		'phrasegroup_album',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'editlog', 1, 1),
		'editlog',
		'hashistory',
		'SMALLINT',
		FIELD_DEFAULTS
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'userlist', 1, 4),
		"ALTER  TABLE  " . TABLE_PREFIX . "userlist ADD friend ENUM('yes', 'no', 'pending', 'denied') NOT NULL DEFAULT 'no'",
		MYSQL_ERROR_COLUMN_EXISTS
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'userlist', 2, 4),
		'userlist',
		'relationid'
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'userlist', 3, 4),
		'userlist',
		'relationid',
		array('relationid', 'type', 'friend')
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'userlist', 4, 4),
		'userlist',
		'userid',
		array('userid', 'type', 'friend')
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 5),
		'user',
		'profilevisits',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 5),
		'user',
		'friendcount',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 3, 5),
		'user',
		'friendreqcount',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 4, 5),
		'user',
		'vmunreadcount',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 5, 5),
		'user',
		'vmmoderatedcount',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'bbcode', 1, 1),
		'bbcode',
		'options',
		'int',
		array('default' => 1, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderator', 1, 1),
		'moderator',
		'permissions2',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 1, 10),
		'usergroup',
		'visitormessagepermissions',
		'INT',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 2, 10),
		'usergroup',
		'socialgrouppermissions',
		'INT',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 3, 10),
		'usergroup',
		'usercsspermissions',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 4, 10),
		'usergroup',
		'albumpermissions',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 5, 10),
		'usergroup',
		'albumpicmaxwidth',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 6, 10),
		'usergroup',
		'albumpicmaxheight',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 7, 10),
		'usergroup',
		'albumpicmaxsize',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 8, 10),
		'usergroup',
		'albummaxpics',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 9, 10),
		'usergroup',
		'albummaxsize',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 10, 10),
		'usergroup',
		'genericpermissions2',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderation', 1, 6),
		"ALTER TABLE " . TABLE_PREFIX . "moderation CHANGE type type ENUM('thread', 'reply', 'visitormessage', 'groupmessage') NOT NULL DEFAULT 'thread'"
	);

	if ($upgrade->field_exists('moderation', 'threadid'))
	{
		$upgrade->run_query(
			sprintf($upgradecore_phrases['altering_x_table'], 'moderation', 2, 6),
			"ALTER TABLE " . TABLE_PREFIX . "moderation CHANGE threadid primaryid INT UNSIGNED NULL DEFAULT '0'"
		);
	}

	if ($upgrade->field_exists('moderation', 'postid'))
	{
		$upgrade->run_query(
			sprintf($upgradecore_phrases['altering_x_table'], 'moderation', 3, 6),
			"UPDATE " . TABLE_PREFIX . "moderation SET primaryid = postid WHERE type = 'reply'"
		);
	}

	// primary key modifications are not handled by the alter class at this time
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'moderation', 4, 6),
		"ALTER TABLE " . TABLE_PREFIX . "moderation DROP PRIMARY KEY",
		MYSQL_ERROR_DROP_KEY_COLUMN_MISSING
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'moderation', 5, 6),
		"ALTER TABLE " . TABLE_PREFIX . "moderation ADD PRIMARY KEY (primaryid, type)",
		MYSQL_ERROR_KEY_EXISTS
	);

	$upgrade->drop_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'moderation', 6, 6),
		'moderation',
		'postid'
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'profilefieldcategory', 1, 1),
		'profilefieldcategory',
		'location',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'rssfeed', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "rssfeed CHANGE url url TEXT"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'regimage', 1, 1),
		"DROP TABLE IF EXISTS " . TABLE_PREFIX . "regimage"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "setting CHANGE datatype datatype ENUM('free', 'number', 'boolean', 'bitfield', 'username', 'integer') NOT NULL DEFAULT 'free'"
	);

	// Was missing in 3.6.5 install script for a few hours but this is a common support issue.
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'search', 1, 1),
		'search',
		'completed',
		'smallint',
		array('null' => false, 'default' => 1)
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 4)
{
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
				('Google',      1, 40, 'bookmarksite_google.gif',      'http://www.google.com/bookmarks/mark?op=edit&amp;output=popup&amp;bkmk={URL}&amp;title={TITLE}')
			"
		);
	}

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype"),
		"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
			(title, editrows, fieldname, special)
		VALUES
			('{$phrasetype['prefix']}', 3, 'prefix', 0),
			('{$phrasetype['prefixadmin']}', 3, 'prefixadmin', 0),
			('{$phrasetype['socialgroups']}', 3, 'socialgroups', 0),
			('{$phrasetype['notice']}', 3, 'notice', 0),
			('{$phrasetype['hvquestion']}', 3, 'hvquestion', 1),
			('{$phrasetype['album']}', 3, 'album', 0)"
	);

	if (trim($vbulletin->options['globalignore']) != '')
	{
		$rows = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "adminmessage WHERE varname = 'after_upgrade_37_update_counters'");
		if ($rows['count'] == 0)
		{
			$upgrade->run_query(
				sprintf($vbphrase['update_table'], TABLE_PREFIX . "adminmessage"),
				"INSERT INTO " . TABLE_PREFIX . "adminmessage
					(varname, dismissable, script, action, execurl, method, dateline, status)
				VALUES
					('after_upgrade_37_update_counters', 1, 'misc.php', 'updatethread', 'misc.php?do=updatethread', 'get', " . TIMENOW . ", 'undone')
				"
			);
		}
	}

	// Check for modified Additional CSS template
	if ($db->query_first("
		SELECT template.styleid
		FROM " . TABLE_PREFIX . "template AS template
		INNER JOIN " . TABLE_PREFIX . "style AS style USING (styleid)
		WHERE
			template.title = 'EXTRA' AND
			template.templatetype = 'css' AND
			template.product IN ('', 'vbulletin') AND
			template.styleid <> -1
		LIMIT 1
	"))
	{
		$rows = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "adminmessage WHERE varname = 'after_upgrade_37_modified_css'");
		if ($rows['count'] == 0)
		{
			$upgrade->run_query(
				sprintf($vbphrase['update_table'], TABLE_PREFIX . "adminmessage"),
				"INSERT INTO " . TABLE_PREFIX . "adminmessage
					(varname, dismissable, script, action, execurl, method, dateline, status)
				VALUES
					('after_upgrade_37_modified_css', 1, 'template.php', 'modify', 'template.php?do=modify', 'get', " . TIMENOW . ", 'undone')
				"
			);
		}
	}

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 1, 1),
		"UPDATE " . TABLE_PREFIX . "usergroup SET
			forumpermissions = forumpermissions |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canpostnew'] . ", " . $vbulletin->bf_ugp_forumpermissions['cantagown'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canreplyothers'] . ", " . $vbulletin->bf_ugp_forumpermissions['cantagothers'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['candeletethread'] . ", " . $vbulletin->bf_ugp_forumpermissions['candeletetagown'] . ", 0),
			genericpermissions = genericpermissions |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canreplyothers'] . "
					OR forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canpostnew'] . ", " . $vbulletin->bf_ugp_genericpermissions['cancreatetag'] . ", 0),

			genericpermissions2 = genericpermissions2 |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canseeprofilepic'] . "
					OR genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canemailmember'] . ", " . $vbulletin->bf_ugp_genericpermissions2['canusefriends'] . ", 0),

			albumpermissions = albumpermissions |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canmodifyprofile'] . "
					AND genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canprofilepic'] . ", " . $vbulletin->bf_ugp_albumpermissions['canalbum'] . ", 0) |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canviewmembers'] . ", " . $vbulletin->bf_ugp_albumpermissions['canviewalbum'] . ", 0),
			albumpicmaxwidth = 600,
			albumpicmaxheight = 600,
			albumpicmaxsize = 100000,
			albummaxpics = 100,
			albummaxsize = 0,

			usercsspermissions = usercsspermissions |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canmodifyprofile'] . "
					AND signaturepermissions & " . $vbulletin->bf_ugp_signaturepermissions['canbbcodefont'] . ", " . $vbulletin->bf_ugp_usercsspermissions['caneditfontfamily'] . ", 0) |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canmodifyprofile'] . "
					AND signaturepermissions & " . $vbulletin->bf_ugp_signaturepermissions['canbbcodefont'] . ", " . $vbulletin->bf_ugp_usercsspermissions['caneditfontsize'] . ", 0) |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canmodifyprofile'] . "
					AND signaturepermissions & " . $vbulletin->bf_ugp_signaturepermissions['canbbcodecolor'] . ", " . $vbulletin->bf_ugp_usercsspermissions['caneditcolors'] . ", 0) |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canmodifyprofile'] . "
					AND genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canprofilepic'] . "
					AND signaturepermissions & " . $vbulletin->bf_ugp_signaturepermissions['canbbcodecolor'] . ", " . $vbulletin->bf_ugp_usercsspermissions['caneditbgimage'] . ", 0) |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canmodifyprofile'] . "
					AND signaturepermissions & " . $vbulletin->bf_ugp_signaturepermissions['canbbcodecolor'] . ", " . $vbulletin->bf_ugp_usercsspermissions['caneditborders'] . ", 0),

			visitormessagepermissions = visitormessagepermissions |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canreplyothers'] . "
					OR forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canreplyown'] . ", " . $vbulletin->bf_ugp_visitormessagepermissions['canmessageownprofile'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canreplyothers'] . ", " . $vbulletin->bf_ugp_visitormessagepermissions['canmessageothersprofile'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['caneditpost'] . ", " . $vbulletin->bf_ugp_visitormessagepermissions['caneditownmessages'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['candeletepost'] . ", " . $vbulletin->bf_ugp_visitormessagepermissions['candeleteownmessages'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['followforummoderation'] . ", " . $vbulletin->bf_ugp_visitormessagepermissions['followforummoderation'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['caneditpost'] . "
					OR forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['candeletepost'] . ", " . $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile'] . ", 0),

			socialgrouppermissions = socialgrouppermissions |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canviewmembers'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canpostnew'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['cancreategroups'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['caneditpost'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['caneditowngroups'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['candeletethread'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['candeleteowngroups'] . ", 0) |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canviewmembers'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['caneditpost']  . "
					OR forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['candeletepost'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canmanagemessages'] . ", 0) |
				IF(adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canalwayspostmessage'] . ", 0) |
				" . $vbulletin->bf_ugp_socialgrouppermissions['followforummoderation'] . "
		"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forumpermission', 1, 1),
		"UPDATE " . TABLE_PREFIX . "forumpermission SET
			forumpermissions = forumpermissions |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canpostnew'] . ", " . $vbulletin->bf_ugp_forumpermissions['cantagown'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canreplyothers'] . ", " . $vbulletin->bf_ugp_forumpermissions['cantagothers'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['candeletethread'] . ", " . $vbulletin->bf_ugp_forumpermissions['candeletetagown'] . ", 0)
		"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderator', 1, 1),
		"UPDATE " . TABLE_PREFIX . "moderator SET
			permissions2 = permissions2 |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['caneditposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['caneditvisitormessages'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['candeleteposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['candeletevisitormessages'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['canremoveposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['canremovevisitormessages'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['canmoderateposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['canmoderatevisitormessages'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['caneditavatar'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['caneditalbumpicture'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['caneditavatar'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['candeletealbumpicture'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['caneditposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['caneditsocialgroups'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['candeleteposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['candeletesocialgroups'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['candeleteposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['candeletegroupmessages'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['canremoveposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['canremovegroupmessages'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['canmoderateposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['canmoderategroupmessages'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions['caneditposts'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['caneditgroupmessages'] . ", 0)
		"
	);

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . "adminmessage"),
		"INSERT INTO " . TABLE_PREFIX . "adminmessage
			(varname, dismissable, script, action, execurl, method, dateline, status)
		VALUES
			('after_upgrade_37_moderator_permissions', 1, 'moderator.php', 'showlist', 'moderator.php?do=showlist', 'get', " . TIMENOW . ", 'undone')
		"
	);

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . 'user'),
		"UPDATE " . TABLE_PREFIX . "user SET options = options | " . $vbulletin->bf_misc_useroptions['showusercss'] . " | " . $vbulletin->bf_misc_useroptions['receivefriendemailrequest']
	);

	if ($vbulletin->options['thumbquality'] <= 70)
	{
		$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '65' WHERE varname = 'thumbquality'");
	}
	else if ($vbulletin->options['thumbquality'] >= 90)
	{
		$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '95' WHERE varname = 'thumbquality'");
	}
	else if ($vbulletin->options['thumbquality'] >= 80)
	{
		$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '85' WHERE varname = 'thumbquality'");
	}
	else
	{
		$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '75' WHERE varname = 'thumbquality'");
	}

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . 'paymentapi'),
		"UPDATE " . TABLE_PREFIX . "paymentapi SET currency = 'usd,gbp,eur,aud,cad' WHERE classname = 'moneybookers'"
	);

	// add YUI to headinclude so things don't suddenly just stop working
	require_once(DIR . '/includes/adminfunctions_template.php');

	$templates = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "template
		WHERE styleid > 0
			AND title IN ('headinclude')
	");
	while ($template = $db->fetch_array($templates))
	{
		if (strpos($template['template_un'], '$stylevar[yuipath]') !== false)
		{
			continue;
		}

		$template['template_un'] = str_replace(
			'<script type="text/javascript">' . "\r\n" . '<!--' . "\r\n" . 'var SESSIONURL = "$session[sessionurl_js]";',
			'<script type="text/javascript" src="$stylevar[yuipath]/yahoo-dom-event/yahoo-dom-event.js?v=' . $vbulletin->options['simpleversion'] . '"></script>' . "\n" . '<script type="text/javascript" src="$stylevar[yuipath]/connection/connection-min.js?v=' . $vbulletin->options['simpleversion'] . '"></script>'  . "\n" . '<script type="text/javascript">' . "\n" . '<!--' . "\n" . 'var SESSIONURL = "$session[sessionurl_js]";',
			$template['template_un']
		);

		// check in case it was newlines only
		if (strpos($template['template_un'], '$stylevar[yuipath]') === false)
		{
			$template['template_un'] = str_replace(
				'<script type="text/javascript">' . "\n" . '<!--' . "\n" . 'var SESSIONURL = "$session[sessionurl_js]";',
				'<script type="text/javascript" src="$stylevar[yuipath]/yahoo-dom-event/yahoo-dom-event.js?v=' . $vbulletin->options['simpleversion'] . '"></script>' . "\n" . '<script type="text/javascript" src="$stylevar[yuipath]/connection/connection-min.js?v=' . $vbulletin->options['simpleversion'] . '"></script>'  . "\n" . '<script type="text/javascript">' . "\n" . '<!--' . "\n" . 'var SESSIONURL = "$session[sessionurl_js]";',
				$template['template_un']
			);
		}

		$compiled_template = compile_template($template['template_un']);

		$upgrade->run_query(
			sprintf($vbphrase['apply_critical_template_change_to_x'], $template['title'], $template['styleid']),
			"UPDATE " . TABLE_PREFIX . "template SET
				template = '" . $db->escape_string($compiled_template) . "',
				template_un = '" . $db->escape_string($template['template_un']) . "'
			WHERE templateid = $template[templateid]
		");
	}

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 1, 1),
		"UPDATE " . TABLE_PREFIX . "setting SET value = 'GDttf' WHERE varname = 'regimagetype' AND value = 'GD'"
	);

	if ($db->query_first("SELECT varname FROM " . TABLE_PREFIX . "setting WHERE varname = 'regimagetype' AND value IN ('GDttf', 'GD')"))
	{
		require_once(DIR . '/includes/adminfunctions_options.php');
		$gdinfo = fetch_gdinfo();
		if ($gdinfo['freetype'] != 'freetype')
		{
			// they won't be able to use the simple text version and they don't have FreeType support, so no image verification
			$upgrade->run_query(
				sprintf($vbphrase['update_table'], TABLE_PREFIX . 'setting'),
				"INSERT IGNORE INTO " . TABLE_PREFIX . "setting
					(varname, grouptitle, value, volatile, product)
				VALUES
					('hv_type', 'version', '0', 1, 'vbulletin')"
			);

			$upgrade->run_query(
				sprintf($vbphrase['update_table'], TABLE_PREFIX . "adminmessage"),
				"INSERT INTO " . TABLE_PREFIX . "adminmessage
					(varname, dismissable, script, action, execurl, method, dateline, status)
				VALUES
					('after_upgrade_37_image_verification_disabled', 1, 'verify.php', '', 'verify.php', 'get', " . TIMENOW . ", 'undone')
				"
			);
		}
	}

	$upgrade->execute();

	build_forum_permissions();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 5)
{ // rebuild userlists for friends
	$vbulletin->GPC['perpage'] = 100;

	$users = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN ". TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		WHERE user.userid > {$vbulletin->GPC['startat']} AND (usertextfield.ignorelist <> '' OR usertextfield.buddylist <> '')
		ORDER BY user.userid ASC
		LIMIT 0, {$vbulletin->GPC['perpage']}
	");

	// check to see if we have some results...
	if ($db->num_rows($users))
	{
		$lastid = 0;
		while ($user = $db->fetch_array($users))
		{
			echo sprintf($upgrade_phrases['upgrade_370b2.php']['build_userlist'], $user['userid']);

			$buddylist = preg_split('/( )+/', trim($user['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
			$ignorelist = preg_split('/( )+/', trim($user['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);

			if (!empty($buddylist))
			{
				$buddylist = array_map('intval', $buddylist);
				foreach ($buddylist AS $buddyid)
				{
					$db->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "userlist (userid, relationid, type, friend) VALUES (" . $user['userid'] . ", " . $buddyid . ", 'buddy', 'no')");
				}
			}

			if (!empty($ignorelist))
			{
				$ignorelist = array_map('intval', $ignorelist);
				foreach ($ignorelist AS $ignoreid)
				{
					$db->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "userlist (userid, relationid, type, friend) VALUES (" . $user['userid'] . ", " . $ignoreid . ", 'ignore', 'no')");
				}
			}
			$lastid = $user['userid'];
		}
		print_next_page(1, $lastid);
	}
	else
	{
		/* Could potentially write a query here to delete invalid users since it was never guaranteed that the userids would be deleted from the usertextfield */
		echo $upgrade_phrases['upgrade_370b2.php']['build_userlist_complete'];
	}
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 6)
{
	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);

	// need to reflect new options
	build_bbcode_cache();

	require_once(DIR . '/includes/adminfunctions_bookmarksite.php');
	build_bookmarksite_datastore();
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
