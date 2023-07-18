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

define('THIS_SCRIPT', 'upgrade_350b1.php');
define('VERSION', '3.5.0 Beta 1');
define('PREV_VERSION', '3.0.14+');
define('VERSION_COMPAT_STARTS', '3.0.14');
define('VERSION_COMPAT_ENDS', '3.0.99');

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
		if ($vbulletin->options['usefileavatar'])
		{
			echo $upgrade_phrases['upgrade_350b1.php']['note'];
		}
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
// fix some broken fields
if ($vbulletin->GPC['step'] == 1)
{

	// Write bitfields to datastore // this may move somewhere else in the process at a later date since it will need to be generally ran
	// with every upgrade to catch new permissions.

	require_once(DIR . '/includes/class_bitfield_builder.php');
	if (!vB_Bitfield_Builder::save($db))
	{ // couldn't build bitfields bail out
		echo "<strong>error</strong>\n";
		print_r(vB_Bitfield_Builder::fetch_errors());
	}

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'session', 1, 1),
		'session',
		'languageid',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'administrator', 1, 1),
		'administrator',
		'languageid',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'moderatorlog', 1, 3),
		'moderatorlog',
		'type',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'moderatorlog', 2, 3),
		'moderatorlog',
		'threadtitle',
		'varchar',
		array('length' => 250, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'moderatorlog', 3, 3),
		'moderatorlog',
		'attachmentid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'userban', 1, 1),
		'userban',
		'reason',
		'varchar',
		array('length' => 250, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'setting', 1, 1),
		'setting',
		'datatype',
		'enum',
		array('attributes' => "('free','number','boolean')", 'null' => false, 'default' => 'free')
	);

	// Update usergroup and forumpermissions with new Can View Thread Content permission
	$upgrade->run_query(
		$upgrade_phrases['upgrade_350b1.php']['update_forumpermissions'],
		"UPDATE " . TABLE_PREFIX . "usergroup SET
			forumpermissions = forumpermissions | " .
				$vbulletin->bf_ugp_forumpermissions['canviewthreads']
			. "
		WHERE forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canview']
	);

	$upgrade->run_query(
		$upgrade_phrases['upgrade_350b1.php']['update_forumpermissions'],
		"UPDATE " . TABLE_PREFIX . "forumpermission SET
			forumpermissions = forumpermissions | " .
				$vbulletin->bf_ugp_forumpermissions['canviewthreads']
			. "
		WHERE forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canview']
	);

	// Update genericpermissions with new fulltext search options
	$upgrade->run_query(
		$upgrade_phrases['upgrade_302.php']['update_genericpermissions'],
		"UPDATE " . TABLE_PREFIX . "usergroup SET
			genericpermissions = genericpermissions | " . $vbulletin->bf_ugp_genericpermissions['cansearchft_bool']
	);

	// Update calendarpermissions
	$upgrade->run_query(
		$upgrade_phrases['upgrade_307.php']['update_calendarpermissions'],
		"UPDATE " . TABLE_PREFIX . "usergroup SET
			calendarpermissions = calendarpermissions | " . $vbulletin->bf_ugp_calendarpermissions['isnotmoderated']
	);

	// Update generic permission with new animated userpic permissions
	$upgrade->run_query(
		$upgrade_phrases['upgrade_302.php']['update_genericpermissions'],
		"UPDATE " . TABLE_PREFIX . "usergroup SET
			genericpermissions = genericpermissions | " . (
				$vbulletin->bf_ugp_genericpermissions['cananimateprofilepic'] +
				$vbulletin->bf_ugp_genericpermissions['cananimateavatar']
			) . "
		WHERE usergroupid IN (5,6,7)"
	);

	// update admin permissions for plugins if you can edit styles
	// (since style management would allow you to edit phpinclude_*)
	$upgrade->run_query(
		$upgrade_phrases['upgrade_350b1.php']['update_adminpermissions'],
		"UPDATE " . TABLE_PREFIX . "administrator SET
			adminpermissions = adminpermissions | " . $vbulletin->bf_ugp_adminpermissions['canadminplugins'] . "
		WHERE adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['canadminstyles']
	);

	// Update calendar, set all calendars to default to displaying upcoming events.
	require_once(DIR . '/includes/functions_calendar.php');
	$upgrade->run_query(
		$upgrade_phrases['upgrade_307.php']['update_calendarpermissions'],
		"UPDATE " . TABLE_PREFIX . "calendar SET options = options | $_CALENDAROPTIONS[showupcoming]"
	);

	$upgrade->add_field(
		$upgrade_phrases['upgrade_350b1.php']['support_multiple_products'],
		'phrasetype',
		'product',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		$upgrade_phrases['upgrade_350b1.php']['support_multiple_products'],
		'phrase',
		'product',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		$upgrade_phrases['upgrade_350b1.php']['support_multiple_products'],
		'template',
		'product',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		$upgrade_phrases['upgrade_350b1.php']['support_multiple_products'],
		'setting',
		'product',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		$upgrade_phrases['upgrade_350b1.php']['support_multiple_products'],
		'settinggroup',
		'product',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		$upgrade_phrases['upgrade_350b1.php']['support_multiple_products'],
		'adminhelp',
		'product',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->run_query(
		$upgrade_phrases['upgrade_350b1.php']['support_multiple_products'],
		"REPLACE INTO " . TABLE_PREFIX . "datastore
			(title, data)
		VALUES
			('products', '" . $db->escape_string(serialize(array('vbulletin' => '1'))) . "')"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'cron', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "cron CHANGE minute minute VARCHAR(100) DEFAULT '' NOT NULL"
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachmenttype', 1, 3),
		'attachmenttype',
		'thumbnail',
		'smallint',
		array('null' => false, 'default' => 0)
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachmenttype', 2, 3),
		'attachmenttype',
		'newwindow',
		'smallint',
		array('null' => false, 'default' => 0)
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachmenttype', 3, 3),
		"UPDATE " . TABLE_PREFIX . "attachmenttype
		SET thumbnail = 1,
			newwindow = 1
		WHERE extension IN ('gif', 'jpeg', 'jpg', 'jpe','png', 'bmp', 'tiff', 'tif')"
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachment', 1, 2),
		'attachment',
		'extension',
		'varchar',
		array('length' => 20, 'attributes' => FIELD_DEFAULTS)
	);

	// Populate the new extension field
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachment', 2, 2),
		"UPDATE " . TABLE_PREFIX . "attachment SET extension = LOWER(SUBSTRING_INDEX(filename, '.', -1))"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'reputationlevel', 1, 1),
		"UPDATE " . TABLE_PREFIX . "reputationlevel
			SET level = 'is an unknown quantity at this point'
		WHERE level = 'is an unknown quantity at this poINT'"
	);

	$upgrade->execute();
}

// #############################################################################
// misc updates
if ($vbulletin->GPC['step'] == 2)
{
	// update the attachment cache from the previous step
	build_attachment_types();

	$upgrade->drop_index(
		sprintf($upgradecore_phrases['altering_x_table'], 'useractivation', 1, 2),
		'useractivation',
		'userid'
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'useractivation', 2, 2),
		"ALTER TABLE " . TABLE_PREFIX . "useractivation ADD UNIQUE INDEX userid (userid, type)",
		MYSQL_ERROR_KEY_EXISTS
	);
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'usergrouprequest', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "usergrouprequest ADD UNIQUE INDEX userid (userid, usergroupid)",
		MYSQL_ERROR_KEY_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'postparsed', 1, 6),
		'postparsed',
		'styleid_code',
		'int',
		array('null' => false, 'default' => '-1')
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'postparsed', 2, 6),
		'postparsed',
		'styleid_html',
		'int',
		array('null' => false, 'default' => '-1')
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'postparsed', 3, 6),
		'postparsed',
		'styleid_php',
		'int',
		array('null' => false, 'default' => '-1')
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'postparsed', 4, 6),
		'postparsed',
		'styleid_quote',
		'int',
		array('null' => false, 'default' => '-1')
	);

	// primary key modifications are not handled by the alter class at this time
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'postparsed', 5, 6),
		"ALTER TABLE " . TABLE_PREFIX . "postparsed DROP PRIMARY KEY",
		MYSQL_ERROR_DROP_KEY_COLUMN_MISSING
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'postparsed', 6, 6),
		"ALTER TABLE " . TABLE_PREFIX . "postparsed ADD PRIMARY KEY (postid, styleid_code, styleid_html, styleid_php, styleid_quote)",
		MYSQL_ERROR_KEY_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'customavatar', 1, 2),
		'customavatar',
		'width',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'customavatar', 2, 2),
		'customavatar',
		'height',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'customprofilepic', 1, 2),
		'customprofilepic',
		'width',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'customprofilepic', 2, 2),
		'customprofilepic',
		'height',
		'smallint',
		FIELD_DEFAULTS
	);

	// add profilepicrevision field
	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'user', 1, 1),
		'user',
		'profilepicrevision',
		'int',
		FIELD_DEFAULTS
	);

	// update paid subscriptions *groan*
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'subscription', 1, 4),
		"ALTER TABLE " . TABLE_PREFIX . "subscription CHANGE cost cost MEDIUMTEXT"
	);

	// need to add length / units to whats in cost before dropping them just below here
	$subs = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "subscription");
	while ($sub = $db->fetch_array($subs))
	{
		$costs = vb_unserialize($sub['cost']);
		if (isset($costs['usd']))
		{
			// still in the 3.0 form
			$newsub = array();
			$newsub[0] = array('cost' => $costs, 'length' => $sub['length'], 'units' => $sub['units']);

			$upgrade->run_query(
				sprintf($vbphrase['update_table'], 'subscription'),
				"UPDATE ". TABLE_PREFIX . "subscription SET cost = '" . $db->escape_string(serialize($newsub)) . "' WHERE subscriptionid = $sub[subscriptionid]"
			);
		}
	}

	$upgrade->drop_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'subscription', 2, 4),
		'subscription',
		'length'
	);

	$upgrade->drop_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'subscription', 3, 4),
		'subscription',
		'units'
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'subscription', 4, 4),
		'subscription',
		'displayorder',
		'smallint',
		array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 1)
	);

	$cronitems = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "cron");
	while ($cronitem = $db->fetch_array($cronitems))
	{
		if (is_numeric($cronitem['minute']))
		{
			// not been run before
			$newminute = array(0 => $cronitem['minute']);

			$upgrade->run_query(
				sprintf($vbphrase['update_table'], 'cron'),
				"UPDATE " . TABLE_PREFIX . "cron SET minute = '" . $db->escape_string(serialize($newminute)) . "' WHERE cronid = $cronitem[cronid]"
			);
		}
	}

	$upgrade->execute();
}

// #############################################################################
// plugin fields and misc
if ($vbulletin->GPC['step'] == 3)
{

	// Clean out orphan records in usergrouprequest (left by broken user merge)
	$orphans = $db->query_read("
		SELECT DISTINCT usergrouprequest.userid
		FROM " . TABLE_PREFIX . "usergrouprequest AS usergrouprequest
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE user.userid IS NULL
	");
	$deleteids = '';
	while ($orphan = $db->fetch_array($orphans))
	{
		$deleteids .= ",$orphan[userid]";
	}
	if ($deleteids != '')
	{
		$upgrade->run_query(
			'', // empty message = no output
			"DELETE FROM " . TABLE_PREFIX . "usergrouprequest WHERE userid IN (-1$deleteids)"
		);
	}

	// Clean out orphan records from delettionlog
	$records = $db->query_read("
		SELECT deletionlog.primaryid
		FROM " . TABLE_PREFIX . "deletionlog AS deletionlog
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = deletionlog.primaryid)
		WHERE deletionlog.type = 'post' AND post.postid IS NULL
	");
	$deleteids = '';
	while ($record = $db->fetch_array($records))
	{
		$deleteids .= ",$record[primaryid]";
	}
	if ($deleteids != '')
	{
		$upgrade->run_query(
			'', // empty message = no output
			"DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid IN (-1$deleteids) AND type = 'post'"
		);
	}

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "plugin"),
		"CREATE TABLE " . TABLE_PREFIX . "plugin (
			pluginid INT unsigned NOT NULL AUTO_INCREMENT,
			title VARCHAR(250) NOT NULL DEFAULT '',
			hookname VARCHAR(250) NOT NULL DEFAULT '',
			phpcode TEXT,
			product VARCHAR(25) NOT NULL DEFAULT '',
			devkey VARCHAR(25) NOT NULL DEFAULT '',
			active SMALLINT(6) NOT NULL DEFAULT '0',
			PRIMARY KEY (pluginid),
			KEY active (active)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "tachyforumpost"),
		"CREATE TABLE " . TABLE_PREFIX . "tachyforumpost (
			userid int unsigned NOT NULL default '0',
			forumid int unsigned NOT NULL default '0',
			lastpost int unsigned NOT NULL default '0',
			lastposter varchar(100) NOT NULL default '',
			lastthread varchar(250) NOT NULL default '',
			lastthreadid int unsigned NOT NULL default '0',
			lasticonid smallint unsigned NOT NULL default '0',
			PRIMARY KEY (userid, forumid),
			INDEX (forumid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "tachythreadpost"),
		"CREATE TABLE " . TABLE_PREFIX . "tachythreadpost (
			userid int unsigned NOT NULL default '0',
			threadid int unsigned NOT NULL default '0',
			lastpost int unsigned NOT NULL default '0',
			lastposter varchar(100) NOT NULL default '',
			PRIMARY KEY (userid, threadid),
			INDEX (threadid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "templatehistory"),
		"CREATE TABLE " . TABLE_PREFIX . "templatehistory (
			templatehistoryid int(10) unsigned NOT NULL auto_increment,
			styleid smallint(5) unsigned NOT NULL default '0',
			title varchar(100) NOT NULL default '',
			template mediumtext,
			dateline int(10) unsigned NOT NULL default '0',
			username varchar(100) NOT NULL default '',
			version varchar(30) NOT NULL default '',
			comment varchar(255) NOT NULL default '',
			PRIMARY KEY (templatehistoryid),
			KEY title (title, styleid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "forumread"),
		"CREATE TABLE " . TABLE_PREFIX . "forumread (
			userid int(10) unsigned NOT NULL default '0',
			forumid smallint(5) unsigned NOT NULL default '0',
			readtime int(10) unsigned NOT NULL default '0',
			PRIMARY KEY (forumid, userid),
			INDEX (readtime)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "threadread"),
		"CREATE TABLE " . TABLE_PREFIX . "threadread (
			userid int(10) unsigned NOT NULL default '0',
			threadid int(10) unsigned NOT NULL default '0',
			readtime int(10) unsigned NOT NULL default '0',
			PRIMARY KEY  (userid, threadid),
			INDEX (readtime)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// add ipaddress to moderator log
	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'moderatorlog', 1, 1),
		'moderatorlog',
		'ipaddress',
		'char',
		array('length' => 15, 'attributes' => FIELD_DEFAULTS)
	);

	// Increase size of navprefs
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'administrator', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "administrator CHANGE navprefs navprefs MEDIUMTEXT"
	);

	// Increase attachment counter
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachment', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE counter counter INT UNSIGNED NOT NULL DEFAULT '0'"
	);


	// import old birthdaydatecut vboption
	$activememberdays = intval($vbulletin->options['birthdaydatecut']);
	$activememberoptions = ($activememberdays ? 3 : 0);

	$upgrade->run_query(
		$upgrade_phrases['upgrade_307.php']['import_birthdaydatecut_option'],
		"REPLACE INTO " . TABLE_PREFIX . "setting
			(varname, grouptitle, value, defaultvalue, displayorder, volatile, product, optioncode)
		VALUES
			('activememberdays', 'forumhome', $activememberdays, 30, 70, 1, 'vbulletin', ''),
			('activememberoptions', 'forumhome', $activememberoptions, 3, 80, 1, 'vbulletin', '')"
	);

	// Change faq.faqname to a binary field so that the compare with phrase.varname doesn't fail on mysql 4.1 due to Illegal Collation Mix
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'faq', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "faq CHANGE faqname faqname VARCHAR(250) BINARY NOT NULL DEFAULT ''"
	);
	// Make sure phrase.varname is a VARCHAR BINARY and not VARBINARY. Upgrading to Mysql< 4.1.8 can incorrectly convert it
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'phrase', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "phrase CHANGE varname varname VARCHAR(250) BINARY NOT NULL DEFAULT ''"
	);

	// Make sure adminutil has a primary key. Bug 2974
	$upgrade->drop_index(
		'', // don't display a message
		'adminutil',
		'title'
	);

	$upgrade->run_query(
		'',
		"ALTER TABLE " . TABLE_PREFIX . "adminutil ADD PRIMARY KEY (title)",
		MYSQL_ERROR_PRIMARY_KEY_EXISTS
	);

	$upgrade->execute();

	build_options();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 4)
{
	// subscribeevent table is lacking a proper primary key
	if (!$upgrade->field_exists('subscribeevent', 'subscribeeventid'))
	{
		$upgrade->run_query(
			sprintf($upgradecore_phrases['altering_x_table'], 'subscribeevent', 1, 5),
			"ALTER TABLE " . TABLE_PREFIX . "subscribeevent DROP PRIMARY KEY"
		);
	}

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'subscribeevent', 2, 5),
		'subscribeevent',
		'subscribeeventid',
		'int',
		array('attributes' => 'UNSIGNED', 'null' => false, 'extra' => 'AUTO_INCREMENT PRIMARY KEY FIRST')
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'subscribeevent', 3, 5),
		"ALTER TABLE " . TABLE_PREFIX . "subscribeevent ADD UNIQUE INDEX subindex (userid, eventid)",
		MYSQL_ERROR_KEY_EXISTS
	);

	// Timestamp field to track when we last sent a reminder email for this event.
	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'subscribeevent', 4, 5),
		'subscribeevent',
		'lastreminder',
		'int',
		FIELD_DEFAULTS
	);

	// Time before event occurs that user wishes to be reminded
	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'subscribeevent', 5, 5),
		'subscribeevent',
		'reminder',
		'int',
		array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 3600)
	);

	// Remove event indexes
	$upgrade->drop_index(
		'',
		'event',
		'daterange'
	);

	$upgrade->drop_index(
		'',
		'event',
		'calendarid'
	);

	// add indexes to the visble fields of the event and attachment tables (for the admincp stats mainly)
	$upgrade->add_index(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachment', 1, 1),
		'attachment',
		'visible',
		'visible'
	);

	// new daterange index
	$upgrade->add_index(
		sprintf($upgradecore_phrases['altering_x_table'], 'event', 1, 3),
		'event',
		'daterange',
		array('dateline_to', 'dateline_from', 'visible', 'calendarid')
	);

	// add calendarid index since we moved calendarid to the end of the daterange index
	$upgrade->add_index(
		sprintf($upgradecore_phrases['altering_x_table'], 'event', 2, 3),
		'event',
		'calendarid',
		'calendarid'
	);

	$upgrade->add_index(
		sprintf($upgradecore_phrases['altering_x_table'], 'event', 3, 3),
		'event',
		'visible',
		'visible'
	);

	// Change our mediumtext image fields to mediumblob [bug 3736]
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachment', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "attachment
			CHANGE filedata filedata MEDIUMBLOB,
			CHANGE thumbnail thumbnail MEDIUMBLOB"
	);

	// the change will fail if the table has already been altered
	if ($upgrade->field_exists('customavatar', 'avatardata'))
	{
		$upgrade->run_query(
			sprintf($upgradecore_phrases['altering_x_table'], 'customavatar', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "customavatar CHANGE avatardata filedata MEDIUMBLOB"
		);
	}

	if ($upgrade->field_exists('customprofilepic', 'profilepicdata'))
	{
		$upgrade->run_query(
			sprintf($upgradecore_phrases['altering_x_table'], 'customprofilepic', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "customprofilepic CHANGE profilepicdata filedata MEDIUMBLOB"
		);
	}

	// Add cron job for sending event reminders. This has to be ran twice an hour, 30 minutes apart.
	if (!$db->query_first("SELECT filename FROM " . TABLE_PREFIX . "cron WHERE filename = './includes/cron/reminder.php'"))
	{
		$upgrade->run_query(
			sprintf($upgradecore_phrases['altering_x_table'], 'cron', 1, 1),
			"INSERT INTO " . TABLE_PREFIX . "cron
				(nextrun, weekday, day, hour, minute, filename, loglevel, title)
			VALUES
				(1053271600, -1, -1, -1, 'a:2:{i:0;i:25;i:1;i:55;}', './includes/cron/reminder.php', 0, '{$upgrade_phrases['upgrade_350b1.php']['cron_event_reminder']}')"
		);
	}

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 5)
{
	// the first query after this block adds subscription.options.
	// So if it's there, then this has been run.
	if (!$upgrade->field_exists('subscription', 'options'))
	{
		$db->hide_errors();

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "thread
			SET visible = 2
			WHERE threadid IN (SELECT primaryid FROM " . TABLE_PREFIX . "deletionlog WHERE type = 'thread')
		");

		if ($db->errno())
		{
			// No sub-query support
			$threadids = '';
			$postids = '';

			$deletions = $db->query_read("
				SELECT primaryid, type
				FROM " . TABLE_PREFIX . "deletionlog
			");
			while ($deleted = $db->fetch_array($deletions))
			{
				if ($deleted['type'] == 'thread')
				{
					$threadids .= ",$deleted[primaryid]";
				}
				else
				{
					$postids .= ",$deleted[primaryid]";
				}
			}
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "thread
				SET visible = 2
				WHERE threadid IN (-1$threadids)
			");
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "post
				SET visible = 2
				WHERE postid IN (-1$postids)
			");
		}
		else
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "post
				SET visible = 2
				WHERE postid IN (SELECT primaryid FROM " . TABLE_PREFIX . "deletionlog WHERE type = 'post')
			");
		}

		$db->show_errors();
	}

	// run this query first -- if this is completed, then we know the deleted thread/post queries are done
	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'subscription', 1, 1),
		'subscription',
		'options',
		'int',
		FIELD_DEFAULTS
	);

	## Update all usernames fields to 100 characters to allow for longer usernames and to account for htmlentities in shorter names, i.e.
	## <<<<<<<<<username>>>>>>>>>  becomes &lt;&lt;&lt;&lt;&lt; (and so on)
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'deletionlog', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "deletionlog CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'editlog', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "editlog CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'forum', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "forum CHANGE lastposter lastposter VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'pmreceipt', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "pmreceipt CHANGE tousername tousername VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'pmtext', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "pmtext CHANGE fromusername fromusername VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'strikes', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "strikes CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'template', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "template CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'user', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "user CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'usernote', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "usernote CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'mailqueue', 1, 1),
		'mailqueue',
		'fromemail',
		'mediumtext',
		FIELD_DEFAULTS
	);

	// alter language table -- New groups
	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'language', 1, 2),
		'language',
		'phrasegroup_inlinemod',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'language', 2, 2),
		'language',
		'phrasegroup_plugins',
		'mediumtext',
		FIELD_DEFAULTS
	);

	// update phrase group list
	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype"),
		"UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['plugins']}', editrows=3, fieldname='plugins' WHERE phrasetypeid=45"
	);

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype"),
		"UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['inlinemod']}', editrows=3, fieldname='inlinemod' WHERE phrasetypeid=47"
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'search', 1, 1),
		'search',
		'titleonly',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'usertextfield', 1, 1),
		'usertextfield',
		'rank',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'ranks', 1, 3),
		'ranks',
		'stack',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgradecore_phrases['altering_x_table'], 'ranks', 2, 3),
		'ranks',
		'display',
		'smallint',
		FIELD_DEFAULTS
	);

	// make existing ranks work as they did before
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'ranks', 3, 3),
		"UPDATE " . TABLE_PREFIX . "ranks SET display = 1"
	);

	$upgrade->run_query(
		'',
		"DELETE FROM " . TABLE_PREFIX . "datastore WHERE title = 'rankphp'"
	);

	$upgrade->execute();
}

// #############################################################################
// move the thread/post queries to their own step since they might take a while
if ($vbulletin->GPC['step'] == 6)
{
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'thread', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "thread CHANGE postusername postusername VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'post', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "post CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''"
	);

	$upgrade->execute();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 7)
{
	// rebuild user stats for new active members thingy
	require_once(DIR . '/includes/functions_databuild.php');
	build_user_statistics();

	// build new rank datastore
	require_once(DIR . '/includes/functions_ranks.php');
	build_ranks();

	// rebuild the smilie cache -- something has changed in 3.5 that this fixes
	build_image_cache('smilie');

	// rebuild the options for the new aggressive email ban option/
	// since it defaults to off but 3.0.x users are used to it being on,
	// put it on for upgrades
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "setting
			(varname, grouptitle, value, defaultvalue, optioncode, displayorder, advanced, volatile)
		VALUES
			('aggressiveemailban', 'banning', '0', '0', 'yesno', 31, 0, 1)
	");
	build_options();

	// rebuild the forum perms for the new can view thread content perm
	build_forum_permissions();

	// rebuild any custom templates for new variables
	require_once(DIR . '/includes/adminfunctions_template.php');
	$customs = $db->query_read("
		SELECT templateid, template_un
		FROM " . TABLE_PREFIX . "template
		WHERE styleid <> -1 AND templatetype = 'template'
	");

	while ($custom = $db->fetch_array($customs))
	{
		$newtemplate = compile_template($custom['template_un']);

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "template SET
				template = '" . $db->escape_string($newtemplate) . "'
			WHERE templateid = $custom[templateid]
		");
	}

	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

// ###################### Start updateattachmenttypes #######################
function build_attachment_types()
{
	global $vbulletin;

	$data = array();

	$types = $vbulletin->db->query_read("
		SELECT extension, size, height, width, enabled, thumbnail, newwindow
		FROM " . TABLE_PREFIX . "attachmenttype
		ORDER BY extension
	");
	while ($type = $vbulletin->db->fetch_array($types))
	{
		if (!empty($type['enabled']))
		{
			$data['extensions'] .= iif($data['extensions'], " $type[extension]", $type['extension']);
			$data["$type[extension]"] = $type;
			unset($type['extension']); // save some space and don't store the extension as both a value and the index
		}
	}
	$vbulletin->db->free_result($types);

	build_datastore('attachmentcache', serialize($data));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
