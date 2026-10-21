<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

define('THIS_SCRIPT', 'upgrade_350b1.php');
define('VERSION', '3.5.0 Beta 1');
define('PREV_VERSION', '3.0.16');

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

	// these next 2 queries seem to be causing people some problems, so add some error checking
	$db->hide_errors();
	$db->query_first("SELECT languageid FROM " . TABLE_PREFIX . "session LIMIT 1");
	if ($db->errno())
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "session ADD languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'session', 1, 1);
	}
	$db->errno = 0;

	$db->query_first("SELECT languageid FROM " . TABLE_PREFIX . "administrator LIMIT 1");
	if ($db->errno())
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "administrator ADD languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'administrator', 1, 1);
	}
	$db->errno = 0;
	$db->show_errors();

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "moderatorlog ADD type SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderatorlog', 1, 3);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "moderatorlog ADD threadtitle VARCHAR(250) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderatorlog', 2, 3);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "moderatorlog ADD attachmentid INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderatorlog', 3, 3);

	$db->hide_errors();
	$db->query_first("SELECT reason FROM " . TABLE_PREFIX . "userban LIMIT 1");
	if ($db->errno())
	{
		// there's a hack that creates this
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "userban ADD reason VARCHAR(250) DEFAULT '' NOT NULL";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'userban', 1, 1);
	}
	$db->errno = 0;
	$db->show_errors();

	// the 3.0 beta 3 upgrade scripts needs this column to function, so we might already have them
	$db->hide_errors();
	$db->query_first("SELECT datatype FROM " . TABLE_PREFIX . "setting LIMIT 1");
	if ($db->errno())
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "setting ADD datatype ENUM('free','number','boolean') NOT NULL DEFAULT 'free'";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 1, 1);
	}
	$db->errno = 0;
	$db->show_errors();

	// Update usergroup and forumpermissions with new Can View Thread Content permission

	$query[] = "

		UPDATE " . TABLE_PREFIX . "usergroup SET
			forumpermissions = forumpermissions | " .
				$vbulletin->bf_ugp_forumpermissions['canviewthreads']
			. "
		WHERE forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canview'] . "
	";
	$explain[] = $upgrade_phrases['upgrade_350b1.php']['update_forumpermissions'];

	$query[] = "

		UPDATE " . TABLE_PREFIX . "forumpermission SET
			forumpermissions = forumpermissions | " .
				$vbulletin->bf_ugp_forumpermissions['canviewthreads']
			. "
		WHERE forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canview'] . "
	";
	$explain[] = $upgrade_phrases['upgrade_350b1.php']['update_forumpermissions'];

	// Update genericpermissions with new fulltext search options
	$query[] = "

		UPDATE " . TABLE_PREFIX . "usergroup SET
			genericpermissions = genericpermissions | " . $vbulletin->bf_ugp_genericpermissions['cansearchft_bool'] . "
	";
	$explain[] = $upgrade_phrases['upgrade_302.php']['update_genericpermissions'];

	// Update calendarpermissions
	$query[] = "

		UPDATE " . TABLE_PREFIX . "usergroup SET
			calendarpermissions = calendarpermissions | {$vbulletin->bf_ugp_calendarpermissions['isnotmoderated']}
	";
	$explain[] = $upgrade_phrases['upgrade_307.php']['update_calendarpermissions'];

	// Update generic permission with new animated userpic permissions
	$query[] = "

		UPDATE " . TABLE_PREFIX . "usergroup SET
			genericpermissions = genericpermissions | " . (
				$vbulletin->bf_ugp_genericpermissions['cananimateprofilepic'] +
				$vbulletin->bf_ugp_genericpermissions['cananimateavatar']
			) . "
		WHERE usergroupid IN (5,6,7)
	";
	$explain[] = $upgrade_phrases['upgrade_302.php']['update_genericpermissions'];

	// update admin permissions for plugins if you can edit styles
	// (since style management would allow you to edit phpinclude_*)
	$query[] = "
		UPDATE " . TABLE_PREFIX . "administrator SET
			adminpermissions = adminpermissions | " . $vbulletin->bf_ugp_adminpermissions['canadminplugins'] . "
		WHERE adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['canadminstyles'] . "
	";
	$explain[] = $upgrade_phrases['upgrade_350b1.php']['update_adminpermissions'];

	require_once(DIR . '/includes/functions_calendar.php');
	// Update calendar, set all calendars to default to displaying upcoming events.
	$query[] = "UPDATE " . TABLE_PREFIX . "calendar SET options = options | $_CALENDAROPTIONS[showupcoming]";
	$explain[] = $upgrade_phrases['upgrade_307.php']['update_calendarpermissions'];

	$db->hide_errors();
		$db->query_first("SELECT product FROM " . TABLE_PREFIX . "adminhelp LIMIT 1");
		$product_missing = ($db->errno() != 0);
		$db->errno = 0;
	$db->show_errors();

	if ($product_missing)
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "phrasetype ADD product VARCHAR(25) NOT NULL DEFAULT ''";
		$explain[] = $upgrade_phrases['upgrade_350b1.php']['support_multiple_products'];
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "phrase ADD product VARCHAR(25) NOT NULL DEFAULT ''";
		$explain[] = $upgrade_phrases['upgrade_350b1.php']['support_multiple_products'];
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "template ADD product VARCHAR(25) NOT NULL DEFAULT ''";
		$explain[] = $upgrade_phrases['upgrade_350b1.php']['support_multiple_products'];
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "setting ADD product VARCHAR(25) NOT NULL DEFAULT ''";
		$explain[] = $upgrade_phrases['upgrade_350b1.php']['support_multiple_products'];
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "settinggroup ADD product VARCHAR(25) NOT NULL DEFAULT ''";
		$explain[] = $upgrade_phrases['upgrade_350b1.php']['support_multiple_products'];
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "adminhelp ADD product VARCHAR(25) NOT NULL DEFAULT ''";
		$explain[] = $upgrade_phrases['upgrade_350b1.php']['support_multiple_products'];
	}

	$query[] = "REPLACE INTO " . TABLE_PREFIX . "datastore (title, data) VALUES ('products', '" . $db->escape_string(serialize(array('vbulletin' => '1'))) . "')";
	$explain[] = $upgrade_phrases['upgrade_350b1.php']['support_multiple_products'];

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "cron CHANGE minute minute VARCHAR(100) DEFAULT '' NOT NULL";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachmenttype ADD thumbnail SMALLINT NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachmenttype', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachmenttype ADD newwindow SMALLINT NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachmenttype', 2, 2);

	$query[] = "
		UPDATE " . TABLE_PREFIX . "attachmenttype
		SET thumbnail = 1,
			newwindow = 1
		WHERE extension IN ('gif', 'jpeg', 'jpg', 'jpe','png', 'bmp', 'tiff', 'tif')
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachmenttype', 3, 3);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment ADD extension VARCHAR(20) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 1, 2);

	# Populate the new extension field
	$query[] = "
		UPDATE " . TABLE_PREFIX . "attachment
		SET extension = LOWER(SUBSTRING_INDEX(filename, '.', -1))
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 2, 2);


	$query[] = "
		UPDATE " . TABLE_PREFIX . "reputationlevel
		SET level = 'is an unknown quantity at this point'
		WHERE level = 'is an unknown quantity at this poINT'
	";
	$explain[] =  sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'reputationlevel', 1, 1);

	exec_queries();
}

// #############################################################################
// misc updates
if ($vbulletin->GPC['step'] == 2)
{
	// update the attachment cache from the previous step
	build_attachment_types();

	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "useractivation DROP INDEX userid";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'useractivation', 1, 2);

	$query[] = "ALTER IGNORE TABLE " . TABLE_PREFIX . "usergrouprequest ADD UNIQUE INDEX (userid, usergroupid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergrouprequest', 1, 1);

	$query[] = "ALTER IGNORE TABLE " . TABLE_PREFIX . "useractivation ADD UNIQUE INDEX (userid, type)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'useractivation', 2, 2);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "post_parsed ADD styleid_code INT NOT NULL DEFAULT '-1'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post_parsed', 1, 6);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "post_parsed ADD styleid_html INT NOT NULL DEFAULT '-1'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post_parsed', 2, 6);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "post_parsed ADD styleid_php INT NOT NULL DEFAULT '-1'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post_parsed', 3, 6);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "post_parsed ADD styleid_quote INT NOT NULL DEFAULT '-1'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post_parsed', 4, 6);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "post_parsed DROP PRIMARY KEY";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post_parsed', 5, 6);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "post_parsed ADD PRIMARY KEY (postid, styleid_code, styleid_html, styleid_php, styleid_quote)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post_parsed', 6, 6);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customavatar ADD width SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customavatar', 1, 2);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customavatar ADD height SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customavatar', 2, 2);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customprofilepic ADD width SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customprofilepic', 1, 2);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customprofilepic ADD height SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customprofilepic', 2, 2);

	// add profilepicrevision field
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user ADD profilepicrevision INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 1);

	// update paid subscriptions *groan*
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscription CHANGE cost cost MEDIUMTEXT";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscription', 1, 3);

	// need to add length / units to whats in cost before dropping them just below here
	$subs = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "subscription");
	while ($sub = $db->fetch_array($subs))
	{
		$costs = unserialize($sub['cost']);
		$newsub = array();
		$newsub[0] = array('cost' => $costs, 'length' => $sub['length'], 'units' => $sub['units']);
		$query[] = "UPDATE ". TABLE_PREFIX . "subscription SET cost = '" . $db->escape_string(serialize($newsub)) . "' WHERE subscriptionid = $sub[subscriptionid]";
		$explain[] = sprintf($vbphrase['update_table'], 'subscription');
	}

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscription DROP length, DROP units";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscription', 2, 3);

	$db->hide_errors();
	$db->query_first("SELECT displayorder FROM " . TABLE_PREFIX . "subscription LIMIT 1");
	if ($db->errno())
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscription ADD displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1'";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscription', 3, 3);
	}
	$db->errno = 0;
	$db->show_errors();

	$cronitems = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "cron");
	while ($cronitem = $db->fetch_array($cronitems))
	{
		if (is_numeric($cronitem['minute']))
		{ // its not been run before
			$newminute = array(0 => $cronitem['minute']);
			$db->query_write("UPDATE " . TABLE_PREFIX . "cron SET minute = '" . $db->escape_string(serialize($newminute)) . "' WHERE cronid = $cronitem[cronid]");
		}
	}

	exec_queries();
}

// #############################################################################
// plugin fields and misc
if ($vbulletin->GPC['step'] == 3)
{

	// Clean out orphan records in usergrouprequest (left by broken user merge)
	$orphans = $db->query_read("
		SELECT DISTINCT usergrouprequest.userid
		FROM " . TABLE_PREFIX . "usergrouprequest AS usergrouprequest
		LEFT JOIN " . TABLE_PREFIX . "user AS user
		USING (userid)
		WHERE user.userid IS NULL
	");
	while ($orphan = $db->fetch_array($orphans))
	{
		$deleteids .= ",$orphan[userid]";
	}
	if ($deleteids != '')
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "usergrouprequest
			WHERE userid IN (-1$deleteids)
		");
	}

	$deleteids = '';
	// Clean out orphan records from deletetionlog
	$records = $db->query_read("
		SELECT deletionlog.primaryid
		FROM " . TABLE_PREFIX . "deletionlog AS deletionlog
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = deletionlog.primaryid)
		WHERE deletionlog.type = 'post' AND post.postid IS NULL
	");
	while ($record = $db->fetch_array($records))
	{
		$deleteids .= ",$record[primaryid]";
	}
	if ($deleteids != '')
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "deletionlog
			WHERE primaryid IN (-1$deleteids) AND type = 'post'
		");
	}

	$query[] = "CREATE TABLE " . TABLE_PREFIX . "plugin (
		pluginid INT unsigned NOT NULL AUTO_INCREMENT,
		title VARCHAR(250) NOT NULL DEFAULT '',
		hookname VARCHAR(250) NOT NULL DEFAULT '',
		phpcode TEXT,
		product VARCHAR(25) NOT NULL DEFAULT '',
		devkey VARCHAR(25) NOT NULL DEFAULT '',
		active SMALLINT(6) NOT NULL DEFAULT '0',
		PRIMARY KEY (pluginid),
		KEY active (active)
	)";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "plugin");

	// the 3.0 beta 3 upgrade scripts needs these tables to function, so we might already have them
	$db->hide_errors();
	$db->query_first("SELECT userid FROM " . TABLE_PREFIX . "tachyforumpost LIMIT 1");
	if ($db->errno())
	{
		$query[] = "CREATE TABLE " . TABLE_PREFIX . "tachyforumpost (
			userid int unsigned NOT NULL default '0',
			forumid int unsigned NOT NULL default '0',
			lastpost int unsigned NOT NULL default '0',
			lastposter varchar(100) NOT NULL default '',
			lastthread varchar(250) NOT NULL default '',
			lastthreadid int unsigned NOT NULL default '0',
			lasticonid smallint unsigned NOT NULL default '0',
			PRIMARY KEY (userid, forumid),
			INDEX (forumid)
		)";
		$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "tachyforumpost");

		$query[] = "CREATE TABLE " . TABLE_PREFIX . "tachythreadpost (
			userid int unsigned NOT NULL default '0',
			threadid int unsigned NOT NULL default '0',
			lastpost int unsigned NOT NULL default '0',
			lastposter varchar(100) NOT NULL default '',
			PRIMARY KEY (userid, threadid),
			INDEX (threadid)
		)";
		$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "tachythreadpost");
	}
	$db->errno = 0;
	$db->show_errors();

	$query[] = "CREATE TABLE " . TABLE_PREFIX . "templatehistory (
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
	)";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "templatehistory");

	$query[] = "CREATE TABLE " . TABLE_PREFIX . "forumread (
		userid int(10) unsigned NOT NULL default '0',
		forumid smallint(5) unsigned NOT NULL default '0',
		readtime int(10) unsigned NOT NULL default '0',
		PRIMARY KEY (forumid, userid),
		INDEX (readtime)
	)";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "forumread");

	$query[] = "CREATE TABLE " . TABLE_PREFIX . "threadread (
		userid int(10) unsigned NOT NULL default '0',
		threadid int(10) unsigned NOT NULL default '0',
		readtime int(10) unsigned NOT NULL default '0',
		PRIMARY KEY  (userid, threadid),
		INDEX (readtime)
	)";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "threadread");

	// add ipaddress to moderator log
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "moderatorlog ADD ipaddress CHAR(15) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderatorlog', 1, 1);

	// Increase size of navprefs
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "administrator CHANGE navprefs navprefs MEDIUMTEXT";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'administrator', 1, 1);

	// Increase attachment counter
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE counter counter INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 1, 1);

	// import old birthdaydatecut vboption
	$activememberdays = intval($vbulletin->options['birthdaydatecut']);
	$activememberoptions = $activememberdays ? 3 : 0;
	$query[] = "
		REPLACE INTO " . TABLE_PREFIX . "setting
		(varname, grouptitle, value, defaultvalue, displayorder, volatile, product, optioncode)
		VALUES
		('activememberdays', 'forumhome', $activememberdays, 30, 70, 1, 'vbulletin', ''),
		('activememberoptions', 'forumhome', $activememberoptions, 3, 80, 1, 'vbulletin', '')
	";
	$explain[] = $upgrade_phrases['upgrade_307.php']['import_birthdaydatecut_option'];

	// Change faq.faqname to a binary field so that the compare with phrase.varname doesn't fail on mysql 4.1 due to Illegal Collation Mix
	// !Made need to check the collation of phrase.varname first and set it to that!
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "faq CHANGE faqname faqname VARCHAR(250) BINARY NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'faq', 1, 1);

	// Make sure phrase.varname is a VARCHAR BINARY and not VARBINARY. Upgrading to Mysql< 4.1.8 can incorrectly convert it
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "phrase CHANGE varname varname VARCHAR(250) BINARY NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 1, 1);

	// Make sure adminutil has a primary key. Bug 2974
	$db->hide_errors();
		$db->query_write("ALTER TABLE " . TABLE_PREFIX . "adminutil DROP INDEX title");
		$db->errno = 0;

		$db->query_write("ALTER IGNORE TABLE " . TABLE_PREFIX . "adminutil ADD PRIMARY KEY (title)");
		$db->errno = 0;
	$db->show_errors();

	exec_queries();
	build_options();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 4)
{

	// subscribeevent table is lacking a proper primary key
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscribeevent DROP PRIMARY KEY";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribeevent', 1, 5);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscribeevent ADD subscribeeventid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribeevent', 2, 5);

	$query[] = "ALTER IGNORE TABLE " . TABLE_PREFIX . "subscribeevent ADD UNIQUE INDEX subindex (userid, eventid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribeevent', 3, 5);

	// Timestamp field to track when we last sent a reminder email for this event.
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscribeevent ADD lastreminder INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribeevent', 4, 5);

	// Time before event occurs that user wishes to be reminded
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscribeevent ADD reminder INT UNSIGNED NOT NULL DEFAULT '3600'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribeevent', 5, 5);

	// Remove event indexes
	$db->hide_errors();
		$db->query_write("ALTER TABLE " . TABLE_PREFIX . "event DROP INDEX daterange");
		$db->errno = 0;

		$db->query_write("ALTER TABLE " . TABLE_PREFIX . "event DROP INDEX calendarid");
		$db->errno = 0;
	$db->show_errors();

	// add indexes to the visble fields of the event and attachment tables (for the admincp stats mainly)
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "attachment ADD INDEX (visible)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 1, 1);

	// new daterange index
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "event ADD INDEX daterange (dateline_to, dateline_from, visible, calendarid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 1, 3);

	// add calendarid index since we moved calendarid to the end of the daterange index
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "event ADD INDEX calendarid (calendarid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 2, 3);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "event ADD INDEX (visible)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 3, 3);

	// Change our mediumtext image fields to mediumblob [bug 3736]
	$query[] = "
		ALTER TABLE " . TABLE_PREFIX . "attachment
			CHANGE filedata filedata MEDIUMBLOB,
			CHANGE thumbnail thumbnail MEDIUMBLOB
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 1, 1);
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customavatar CHANGE avatardata filedata MEDIUMBLOB";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customavatar', 1, 1);
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "customprofilepic CHANGE profilepicdata filedata MEDIUMBLOB";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'customprofilepic', 1, 1);

	// Add cron job for sending event reminders. This has to be ran twice an hour, 30 minutes apart.
	$query[] = "INSERT INTO " . TABLE_PREFIX . "cron (nextrun, weekday, day, hour, minute, filename, loglevel, title)
		VALUES (1053271600, -1, -1, -1, 'a:2:{i:0;i:25;i:1;i:55;}', './includes/cron/reminder.php', 0, '{$upgrade_phrases['upgrade_350b1.php']['cron_event_reminder']}')";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 1, 1);

	exec_queries();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 5)
{
	## Update all usernames fields to 100 characters to allow for longer usernames and to account for htmlentities in shorter names, i.e.
	## <<<<<<<<<username>>>>>>>>>  becomes &lt;&lt;&lt;&lt;&lt; (and so on)

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "deletionlog CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'deletionlog', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "editlog CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'editlog', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "forum CHANGE lastposter lastposter VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "pmreceipt CHANGE tousername tousername VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pmreceipt', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "pmtext CHANGE fromusername fromusername VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pmtext', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "strikes CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'strikes', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "template CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'template', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "usernote CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usernote', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscription ADD options INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscription', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "mailqueue ADD fromemail MEDIUMTEXT";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'mailqueue', 1, 1);

	$db->hide_errors();
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "thread
			SET visible = 2
			WHERE threadid IN (SELECT primaryid FROM " . TABLE_PREFIX . "deletionlog WHERE type = 'thread')
		");
		// No sub-query support
		if ($db->errno())
		{
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

	// alter language table // New groups
	$db->hide_errors();
	$db->query_first("SELECT phrasegroup_inlinemod FROM " . TABLE_PREFIX . "language LIMIT 1");
	$db->show_errors();
	if ($db->errno() != 0)
	{
		$query[] = "ALTER TABLE " . TABLE_PREFIX . "language ADD phrasegroup_inlinemod MEDIUMTEXT,
			ADD phrasegroup_plugins MEDIUMTEXT";
		$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "language");
	}
	$db->errno = 0;

	// update phrase group list
	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['plugins']}', editrows=3, fieldname='plugins' WHERE phrasetypeid=45";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype");
	$query[] = "UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['inlinemod']}', editrows=3, fieldname='inlinemod' WHERE phrasetypeid=47";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype");

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "search ADD titleonly SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'search', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "usertextfield ADD rank MEDIUMTEXT";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usertextfield', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "ranks ADD stack SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'ranks', 1, 3);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "ranks ADD display SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'ranks', 2, 3);

	// make existing ranks work as they did before
	$query[] = "UPDATE " . TABLE_PREFIX . "ranks SET display = 1";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'ranks', 3, 3);

	$db->query_write("DELETE FROM " . TABLE_PREFIX . "datastore WHERE title = 'rankphp'");

	exec_queries();
}

// #############################################################################
// move the thread/post queries to their own step since they might take a while
if ($vbulletin->GPC['step'] == 6)
{
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "thread CHANGE postusername postusername VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 1);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "post CHANGE username username VARCHAR (100) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post', 1, 1);

	exec_queries();
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
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15859 $
|| ####################################################################
\*======================================================================*/
?>
