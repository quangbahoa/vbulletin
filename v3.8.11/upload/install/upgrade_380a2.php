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

define('THIS_SCRIPT', 'upgrade_380a2.php');
define('VERSION', '3.8.0 Alpha 2');
define('PREV_VERSION', '3.7.1+');
define('VERSION_COMPAT_STARTS', '3.7.1');
define('VERSION_COMPAT_ENDS', '3.7.99');

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

	if (!isset($vbulletin->bf_ugp_socialgrouppermissions['canuploadgroupicon']))
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$upgradecore_phrases[wrong_bitfield_xml]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'prefixpermission'),
		"CREATE TABLE " . TABLE_PREFIX . "prefixpermission (
			prefixid VARCHAR(25) NOT NULL,
			usergroupid SMALLINT UNSIGNED NOT NULL,
			KEY prefixusergroup (prefixid, usergroupid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// album update
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'albumupdate'),
		"CREATE TABLE " . TABLE_PREFIX . "albumupdate (
			albumid INT UNSIGNED NOT NULL DEFAULT '0',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (albumid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// pm quota
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'pmthrottle'),
		"CREATE TABLE " . TABLE_PREFIX . "pmthrottle (
			userid INT unsigned NOT NULL,
			dateline INT unsigned NOT NULL,
			KEY userid (userid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// discussions
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'discussion'),
		"CREATE TABLE " . TABLE_PREFIX . "discussion (
			discussionid INT unsigned NOT NULL auto_increment,
			groupid INT unsigned NOT NULL,
			firstpostid INT unsigned NOT NULL,
			lastpostid INT unsigned NOT NULL,
			lastpost INT unsigned NOT NULL,
			lastposter VARCHAR(255) NOT NULL,
			lastposterid INT unsigned NOT NULL,
			visible INT unsigned NOT NULL default '0',
			deleted INT unsigned NOT NULL default '0',
			moderation INT unsigned NOT NULL default '0',
			subscribers ENUM('0', '1') default '0',
			PRIMARY KEY  (discussionid),
			KEY groupid (groupid, lastpost)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'groupread'),
		"CREATE TABLE " . TABLE_PREFIX . "groupread (
			userid INT unsigned NOT NULL,
			groupid INT unsigned NOT NULL,
			readtime INT unsigned NOT NULL,
			PRIMARY KEY  (userid, groupid),
			KEY readtime (readtime)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'discussionread'),
		"CREATE TABLE " . TABLE_PREFIX . "discussionread (
			userid INT unsigned NOT NULL,
			discussionid INT unsigned NOT NULL,
			readtime INT unsigned NOT NULL,
			PRIMARY KEY (userid, discussionid),
			KEY readtime (readtime)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);


 	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'socialgroupcategory'),
		"CREATE TABLE " . TABLE_PREFIX . "socialgroupcategory (
			 socialgroupcategoryid INT unsigned NOT NULL auto_increment,
			 creatoruserid INT unsigned NOT NULL,
			 title VARCHAR(250) NOT NULL,
			 description TEXT NOT NULL,
			 displayorder INT unsigned NOT NULL,
			 lastupdate INT unsigned NOT NULL,
			 groups INT unsigned default '0',
			 PRIMARY KEY  (socialgroupcategoryid),
			 KEY displayorder (displayorder)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'subscribegroup'),
		"CREATE TABLE " . TABLE_PREFIX . "subscribegroup (
			subscribegroupid INT unsigned NOT NULL auto_increment,
			userid INT unsigned NOT NULL,
			groupid INT unsigned NOT NULL,
			PRIMARY KEY  (subscribegroupid),
			UNIQUE KEY usergroup (userid, groupid),
			KEY groupid (groupid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'subscribediscussion'),
		"CREATE TABLE " . TABLE_PREFIX . "subscribediscussion (
			subscribediscussionid INT unsigned NOT NULL auto_increment,
			userid INT unsigned NOT NULL,
			discussionid INT unsigned NOT NULL,
			emailupdate SMALLINT unsigned NOT NULL default '0',
			PRIMARY KEY (subscribediscussionid),
			UNIQUE KEY userdiscussion (userid, discussionid),
			KEY discussionid (discussionid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'socialgroupicon'),
		"CREATE TABLE " . TABLE_PREFIX . "socialgroupicon (
			groupid INT unsigned NOT NULL default '0',
			userid INT unsigned default '0',
			filedata mediumblob,
			extension VARCHAR(20) NOT NULL default '',
			dateline INT unsigned NOT NULL default '0',
			width INT unsigned NOT NULL default '0',
			height INT unsigned NOT NULL default '0',
			thumbnail_filedata mediumblob,
			thumbnail_width INT unsigned NOT NULL default '0',
			thumbnail_height INT unsigned NOT NULL default '0',
			PRIMARY KEY  (groupid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'profileblockprivacy'),
		"CREATE TABLE " . TABLE_PREFIX . "profileblockprivacy (
			userid INT UNSIGNED NOT NULL,
			blockid varchar(255) NOT NULL,
			requirement SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (userid, blockid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . 'noticedismissed'),
		"CREATE TABLE " . TABLE_PREFIX . "noticedismissed (
			noticeid INT UNSIGNED NOT NULL DEFAULT '0',
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (noticeid,userid),
			KEY userid (userid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 2)
{
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "event CHANGE utc utc DECIMAL(4,2) NOT NULL DEFAULT '0.0'"
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'prefix', 1, 1),
		'prefix',
		'options',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pm', 1, 1),
		'pm',
		'parentpmid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		$upgrade_phrases['upgrade_380a2.php']['updating_profile_categories'],
		'profilefieldcategory',
		'allowprivacy',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroup', 1, 5),
		'socialgroup',
		'lastdiscussionid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroup', 2, 5),
		'socialgroup',
		'discussions',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroup', 3, 5),
		'socialgroup',
		'lastdiscussion',
		'varchar',
		array('length' => 255, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroup', 4, 5),
		'socialgroup',
		'lastupdate',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroup', 5, 5),
		'socialgroup',
		'transferowner',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 1, 3),
		'usergroup',
		'pmthrottlequantity',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 2, 3),
		'usergroup',
		'groupiconmaxsize',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 3, 3),
		'usergroup',
		'maximumsocialgroups',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_380a2.php']['create_index_on_x'], TABLE_PREFIX . 'usernote'),
		'usernote',
		'posterid',
		array('posterid')
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_380a2.php']['alter_index_on_x'], TABLE_PREFIX . 'moderator'),
		'moderator',
		'userid'
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_380a2.php']['create_index_on_x'], TABLE_PREFIX . 'moderator'),
		"ALTER TABLE " . TABLE_PREFIX . "moderator ADD UNIQUE INDEX userid_forumid (userid, forumid)",
		array(MYSQL_ERROR_KEY_EXISTS, MYSQL_ERROR_UNIQUE_CONSTRAINT)
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 3)
{
	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . 'groupmessage'),
		"UPDATE " . TABLE_PREFIX . 'socialgroup
		SET lastupdate = ' . TIMENOW
	);

	if ($upgrade->field_exists('groupmessage', 'groupid'))
	{
		$upgrade->drop_index(
			sprintf($upgrade_phrases['upgrade_380a2.php']['alter_index_on_x'], TABLE_PREFIX . 'groupmessage'),
			'groupmessage',
			'groupid'
		);

		$upgrade->add_field(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'groupmessage', 1, 1),
			'groupmessage',
			'discussionid',
			'int',
			FIELD_DEFAULTS
		);

		$upgrade->add_index(
			sprintf($upgrade_phrases['upgrade_380a2.php']['create_index_on_x'], TABLE_PREFIX . 'groupmessage'),
			'groupmessage',
			'discussionid',
			array('discussionid', 'dateline', 'state')
		);

		$upgrade->add_index(
			sprintf($upgrade_phrases['upgrade_380a2.php']['fulltext_index_on_x'], TABLE_PREFIX . 'groupmessage'),
			'groupmessage',
			'gm_ft',
			array('title', 'pagetext'),
			'fulltext'
		);

		$upgrade->run_query(
			$upgrade_phrases['upgrade_380a2.php']['convert_messages_to_discussion'],
			"REPLACE INTO " . TABLE_PREFIX . "discussion (groupid, firstpostid, lastpostid)
			SELECT gm.groupid, MIN(gm.gmid) AS firstpostid, MAX(gm.gmid) AS lastpostid
			FROM " . TABLE_PREFIX . "groupmessage AS gm
			LEFT JOIN " . TABLE_PREFIX . "socialgroup AS sg
			 ON sg.groupid = gm.groupid
			GROUP BY gm.groupid
		");

		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . 'groupmessage'),
			"UPDATE " . TABLE_PREFIX . "groupmessage AS gm, " . TABLE_PREFIX . "discussion as gd
			SET gm.discussionid = gd.discussionid
			WHERE gm.groupid = gd.groupid
		");

		$upgrade->run_query(
			$upgrade_phrases['upgrade_380a2.php']['set_discussion_titles'],
			"UPDATE " . TABLE_PREFIX . "groupmessage gm
			INNER JOIN " . TABLE_PREFIX . "discussion d
			 ON gm.gmid = d.firstpostid
			INNER JOIN " . TABLE_PREFIX . "socialgroup sg
			 ON sg.groupid = d.groupid
			SET gm.title = IF(gm.title='',sg.name,gm.title)
		");

		$upgrade->show_message($upgrade_phrases['upgrade_380a2.php']['update_discussion_counters']);

		$upgrade->run_query(
			$upgrade_phrases['upgrade_380a2.php']['update_last_post'],
			"UPDATE " . TABLE_PREFIX . "discussion d
			INNER JOIN " . TABLE_PREFIX . "groupmessage gm
			 ON gm.gmid = d.lastpostid
			SET d.lastpost = gm.dateline,
			    d.lastposter = gm.postusername,
			    d.lastposterid = gm.postuserid
		");

		// Get discussion counters
		$temptable = TABLE_PREFIX . 'discussion_temp_' . TIMENOW;

		$upgrade->run_query('', "
			CREATE TABLE $temptable (
				discussionid INT unsigned NOT NULL,
				visible INT unsigned DEFAULT '0',
				moderation INT unsigned DEFAULT '0',
				deleted INT unsigned DEFAULT '0',
				PRIMARY KEY(discussionid)
			)"
		);

		$upgrade->run_query('', "
			REPLACE INTO $temptable (discussionid, visible, moderation, deleted)
			SELECT discussionid,
				SUM(IF(state = 'visible', 1, 0)) AS visible,
				SUM(IF(state = 'deleted', 1, 0)) AS deleted,
				SUM(IF(state = 'moderation', 1, 0)) AS moderation
			FROM " . TABLE_PREFIX . "groupmessage
			GROUP BY discussionid
		");

		$upgrade->run_query('', "
			UPDATE " . TABLE_PREFIX . "discussion AS d
			INNER JOIN $temptable AS temp
			 ON temp.discussionid = d.discussionid
			SET d.visible = temp.visible,
				d.moderation = temp.moderation,
				d.deleted = temp.deleted
		");

		$upgrade->run_query('', "
			DROP TABLE $temptable
		");

		$upgrade->show_message($upgrade_phrases['upgrade_380a2.php']['update_group_message_counters']);

		$temptable = TABLE_PREFIX . "socialgroup" . TIMENOW;

		$upgrade->run_query('', "
			CREATE TABLE $temptable (
				groupid INT unsigned NOT NULL,
				visible INT unsigned DEFAULT '0',
				moderation INT unsigned DEFAULT '0',
				deleted INT unsigned DEFAULT '0',
				discussions INT unsigned DEFAULT '0',
				PRIMARY KEY (groupid)
			)
		");

		$upgrade->run_query('', "
			REPLACE INTO $temptable (groupid, visible, moderation, deleted, discussions)
			SELECT discussion.groupid,
					SUM(IF(state != 'visible',0,visible)) AS visible,
					SUM(deleted) AS deleted,
					SUM(moderation) AS moderation,
					SUM(IF(state = 'visible', 1, 0)) AS discussions
			FROM " . TABLE_PREFIX . "discussion AS discussion
			LEFT JOIN " . TABLE_PREFIX . "groupmessage AS gm
				ON gm.gmid = discussion.firstpostid
			GROUP BY discussion.groupid
		");

		$upgrade->run_query('', "
			UPDATE " . TABLE_PREFIX . "socialgroup AS sg
			INNER JOIN $temptable AS temp
			 ON temp.groupid = sg.groupid
			SET sg.visible = temp.visible,
				sg.moderation = temp.moderation,
				sg.deleted = temp.deleted,
				sg.discussions = temp.discussions
		");

		$upgrade->run_query('', "
			DROP TABLE $temptable
		");

		$upgrade->drop_field(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'groupmessage', 1, 1),
			'groupmessage',
			'groupid'
		);

		$upgrade->execute();
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroup', 1, 1),
		'socialgroup',
		'socialgroupcategoryid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_380a2.php']['create_index_on_x'], TABLE_PREFIX . 'socialgroup'),
		'socialgroup',
		'socialgroupcategoryid',
		array('socialgroupcategoryid')
	);

	$upgrade->run_query(
		$upgrade_phrases['upgrade_380a2.php']['creating_default_group_category'],
		"REPLACE INTO " . TABLE_PREFIX . "socialgroupcategory
			(socialgroupcategoryid, creatoruserid, title, description, displayorder, lastupdate)
		VALUES
			(1, 1, '" . $db->escape_string($upgrade_phrases['upgrade_380a2.php']['uncategorized']) . "',
			'" . $db->escape_string($install_phrases['upgrade_380a2.php']['uncategorized_description']) . "', 1, " . TIMENOW . ")
	");

	$upgrade->run_query(
		$upgrade_phrases['upgrade_380a2.php']['move_groups_to_default_category'],
		"UPDATE " . TABLE_PREFIX . "socialgroup
		SET socialgroupcategoryid = 1
	");

	$upgrade->execute();

}

// #############################################################################
if ($vbulletin->GPC['step'] == 4)
{
	// report PM
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pmtext', 1, 1),
		'pmtext',
		'reportthreadid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'notice', 1, 1),
		'notice',
		'dismissible',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'useractivation', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "useractivation CHANGE activationid activationid VARCHAR(40) NOT NULL DEFAULT ''"
	);

	// Human Verification options and permissions
	if ($vbulletin->options['hvcheck_registration']
		OR $vbulletin->options['hvcheck_post']
		OR $vbulletin->options['hvcheck_search']
		OR $vbulletin->options['hvcheck_contactus']
		OR $vbulletin->options['hvcheck_lostpw'])
	{
		$hvcheck = 0;
		$hvcheck += ($vbulletin->options['hvcheck_registration'] ? $vbulletin->bf_misc_hvcheck['register'] : 0);
		$hvcheck += ($vbulletin->options['hvcheck_post'] ? $vbulletin->bf_misc_hvcheck['post'] : 0);
		$hvcheck += ($vbulletin->options['hvcheck_search'] ? $vbulletin->bf_misc_hvcheck['search'] : 0);
		$hvcheck += ($vbulletin->options['hvcheck_contactus'] ? $vbulletin->bf_misc_hvcheck['contactus'] : 0);
		$hvcheck += ($vbulletin->options['hvcheck_lostpw'] ? $vbulletin->bf_misc_hvcheck['lostpw'] : 0);

		$upgrade->run_query(
			$upgrade_phrases['upgrade_300b3.php']['updating_usergroup_permissions'],
			"UPDATE " . TABLE_PREFIX . "usergroup SET
				genericpermissions = genericpermissions | " . $vbulletin->bf_ugp_genericoptions['requirehvcheck'] . "
			 WHERE usergroupid = 1"
		);
	}
	else
	{
		$hvcheck = array_sum($vbulletin->bf_misc_hvcheck);
	}

	$upgrade->run_query(
		$upgrade_phrases['upgrade_380a2.php']['update_hv_options'],
		"REPLACE INTO " . TABLE_PREFIX . "setting
			(varname, grouptitle, value, volatile, product)
		VALUES ('hvcheck', 'humanverification', $hvcheck, 1, 'vbulletin')"
	);

	// Give permissions
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_380a2.php']['granting_permissions'], 'usergroup', 1, 1),
		"UPDATE " . TABLE_PREFIX . "usergroup SET
			usercsspermissions = usercsspermissions |
				IF(forumpermissions & " . $vbulletin->bf_ugp_genericpermissions['canmodifyprofile'] . ", " . $vbulletin->bf_ugp_usercsspermissions['caneditprivacy'] . ", 0),
			forumpermissions = forumpermissions |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['cangetattachment'] . ", " . $vbulletin->bf_ugp_forumpermissions['canseethumbnails'] . ", 0),
			genericoptions = genericoptions |
				IF(usergroupid = 1," . $vbulletin->bf_ugp_genericoptions['requirehvcheck'] . ", 0),
			socialgrouppermissions = socialgrouppermissions |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canreplyown'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canpostmessage'] . ", 0) |
				IF(adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canalwayspostmessage'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canpostnew'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['cancreatediscussion'] . ", 0) |
				IF(adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canalwayscreatediscussion'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canopenclose'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canlimitdiscussion'] . ", 0) |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['candeletethread'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canmanagediscussions'] . ", 0) |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canprofilepic'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canuploadgroupicon'] . ", 0) |
				IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['cananimateprofilepic'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['cananimategroupicon'] . ", 0),
			groupiconmaxsize = profilepicmaxsize,
			pmthrottlequantity = 0,
			maximumsocialgroups = 5
		"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_380a2.php']['granting_permissions'], 'forumpermission', 1, 1),
		"UPDATE " . TABLE_PREFIX . "forumpermission SET
			forumpermissions = forumpermissions |
				IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['cangetattachment'] . ", " . $vbulletin->bf_ugp_forumpermissions['canseethumbnails'] . ", 0)
		"
	);

	// Give moderator permissions
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderator', 1, 1),
		"UPDATE " . TABLE_PREFIX . "moderator SET
			permissions2 = permissions2 |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions2['caneditgroupmessages'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['caneditsocialgroups'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions2['candeletegroupmessages'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['candeletediscussions'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions2['candeletesocialgroups'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['cantransfersocialgroups'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions2['canremovegroupmessages'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['canremovediscussions'] . ", 0) |
				IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions2['canmoderategroupmessages'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['canmoderatediscussions'] . ", 0)
		"
	);

	// Update latest albums
	$upgrade->show_message($upgrade_phrases['upgrade_380a2.php']['update_album_update_counters']);

	$upgrade->execute();

	require_once(DIR . '/includes/functions_album.php');
	$vbulletin->options['album_recentalbumdays'] = 7;
	exec_rebuild_album_updates();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 5)
{
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname LIKE 'notice\_%\_title'
			AND fieldname = 'global'
	");

	require_once(DIR . '/includes/adminfunctions_prefix.php');
	build_prefix_datastore();

	build_forum_permissions();

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
