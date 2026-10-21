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

define('THIS_SCRIPT', 'upgrade_360b1.php');
define('VERSION', '3.6.0 Beta 1');
define('PREV_VERSION', '3.5.4+');
define('VERSION_COMPAT_STARTS', '3.5.4');
define('VERSION_COMPAT_ENDS', '3.5.99');

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
		echo "<p>" . $upgrade_phrases['upgrade_360b1.php']['please_wait_message'] . "</p>";
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
// Big Table Alters #1
if ($vbulletin->GPC['step'] == 1)
{
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	// Alter Post table OH NO HERE WE GO! -- new system will allow a refresh if it dies at least
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post', 1, 1),
		'post',
		'infraction',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 1),
		'thread',
		'deletedcount',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->execute();

	echo "<p>" . $upgrade_phrases['upgrade_360b1.php']['please_wait_message'] . "</p>";
}

// #############################################################################
// Big Table Alters #2
if ($vbulletin->GPC['step'] == 2)
{
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post', 1, 1),
		'post',
		'reportthreadid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 1),
		'thread',
		'lastpostid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->execute();
}

// #############################################################################
// Misc Alters
if ($vbulletin->GPC['step'] == 3)
{
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 1, 2),
		"ALTER TABLE " . TABLE_PREFIX . "setting CHANGE datatype datatype ENUM('free', 'number', 'boolean', 'bitfield', 'username') NOT NULL DEFAULT 'free'"
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 2, 2),
		'setting',
		'blacklist',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "forum CHANGE childlist childlist TEXT"
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "externalcache"),
		"CREATE TABLE " . TABLE_PREFIX . "externalcache (
			cachehash CHAR(32) NOT NULL default '',
			text MEDIUMTEXT,
			headers MEDIUMTEXT,
			dateline INT UNSIGNED NOT NULL default '0',
			PRIMARY KEY (cachehash),
			KEY dateline (dateline, cachehash)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// Go medieval on phrases
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 1, 7),
		'phrase',
		'fieldname',
		'varchar',
		array('length' => 20, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 2, 7),
		'phrase',
		'languageid'
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 3, 7),
		'phrase',
		'name_lang_type'
	);

	if ($upgrade->field_exists('phrase', 'phrasetypeid'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 4, 7),
			"UPDATE " . TABLE_PREFIX . "phrase AS phrase, " . TABLE_PREFIX . "phrasetype AS phrasetype
				SET phrase.fieldname = phrasetype.fieldname
			WHERE phrase.phrasetypeid = phrasetype.phrasetypeid"
		);
	}

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 5, 7),
		'phrase',
		'languageid',
		array('languageid', 'fieldname')
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 6, 7),
		"ALTER TABLE " . TABLE_PREFIX . "phrase ADD UNIQUE INDEX
			name_lang_type (varname, languageid, fieldname)",
		MYSQL_ERROR_KEY_EXISTS
	);

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 7, 7),
		'phrase',
		'phrasetypeid'
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrasetype', 1, 5),
		'phrasetype',
		'special',
		'smallint',
		FIELD_DEFAULTS
	);

	if ($upgrade->field_exists('phrasetype', 'phrasetypeid'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrasetype', 2, 5),
			"UPDATE " . TABLE_PREFIX . "phrasetype SET special = 1 WHERE phrasetypeid >= 1000"
		);
	}

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrasetype', 3, 5),
		'phrasetype',
		'phrasetypeid'
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrasetype', 4, 5),
		"ALTER TABLE " . TABLE_PREFIX . "phrasetype ADD PRIMARY KEY (fieldname)",
		MYSQL_ERROR_PRIMARY_KEY_EXISTS
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrasetype', 5, 5),
		"DELETE FROM " . TABLE_PREFIX . "phrasetype WHERE fieldname = ''"
	);

	// Add cron job for Daily Cleanup tasks, the title will be moved to the phrase system in Step #2
	if (!$db->query_first("SELECT filename FROM " . TABLE_PREFIX . "cron WHERE filename = './includes/cron/dailycleanup.php'"))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 1, 3),
			"INSERT INTO " . TABLE_PREFIX . "cron
				(title, nextrun, weekday, day, hour, minute, filename, loglevel)
			VALUES
				('" . $db->escape_string($upgrade_phrases['upgrade_360b1.php']['dailycleanup_title']) . "', 1053533100, '-1', '-1', '0', 'a:1:{i:0;i:10;}', './includes/cron/dailycleanup.php', '0')"
		);
	}

	// Add cron job for RSS posting robot
	if (!$db->query_first("SELECT filename FROM " . TABLE_PREFIX . "cron WHERE filename = './includes/cron/rssposter.php'"))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 2, 3),
			"INSERT INTO " . TABLE_PREFIX . "cron
				(title, weekday, day, hour, minute, filename, loglevel)
			VALUES
				('" . $db->escape_string($upgrade_phrases['upgrade_360b1.php']['rssposter_title']) . "', '-1', '-1', '-1', 'a:6:{i:0;i:0;i:1;i:10;i:2;i:20;i:3;i:30;i:4;i:40;i:5;i:50;}', './includes/cron/rssposter.php', '1')"
		);
	}

	// Add cron job for Infractions, the title will be moved to the phrase system in Step #2
	if (!$db->query_first("SELECT filename FROM " . TABLE_PREFIX . "cron WHERE filename = './includes/cron/infractions.php'"))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 2, 3),
			"INSERT INTO " . TABLE_PREFIX . "cron
				(title, nextrun, weekday, day, hour, minute, filename, loglevel)
			VALUES
				('" . $db->escape_string($upgrade_phrases['upgrade_360b1.php']['infractions_title']) . "', 1053533100, '-1', '-1', '-1', 'a:2:{i:0;i:20;i:1;i:50;}', './includes/cron/infractions.php', '1')"
		);
	}

	// Add cron job for CCBill, the title will be moved to the phrase system in Step #2
	if (!$db->query_first("SELECT filename FROM " . TABLE_PREFIX . "cron WHERE filename = './includes/cron/ccbill.php'"))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 3, 3),
			"INSERT INTO " . TABLE_PREFIX . "cron
				(title, nextrun, weekday, day, hour, minute, filename, loglevel)
			VALUES
				('" . $db->escape_string($upgrade_phrases['upgrade_360b1.php']['ccbill_title']) . "', 1053533100, '-1', '-1', '-1', 'a:1:{i:0;i:10;}', './includes/cron/ccbill.php', '1')"
		);
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'product', 1, 2),
		'product',
		'url',
		'varchar',
		array('length' => 250, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'product', 2, 2),
		'product',
		'versioncheckurl',
		'varchar',
		array('length' => 250, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "productdependency"),
		"CREATE TABLE " . TABLE_PREFIX . "productdependency (
			productdependencyid INT NOT NULL AUTO_INCREMENT,
			productid varchar(25) NOT NULL DEFAULT '',
			dependencytype varchar(25) NOT NULL DEFAULT '',
			parentproductid varchar(25) NOT NULL DEFAULT '',
			minversion varchar(50) NOT NULL DEFAULT '',
			maxversion varchar(50) NOT NULL DEFAULT '',
			PRIMARY KEY (productdependencyid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'plugin', 1, 1),
		'plugin',
		'executionorder',
		'smallint',
		array('null' => false, 'default' => 5)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 1, 2),
		'event',
		'dst',
		'smallint',
		array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 1)
	);

	// now we need to update the actual entry
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 2, 2),
		"UPDATE " . TABLE_PREFIX . "event SET
			dst = 0
		WHERE utc = 0"
	);


	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 4)
{
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscription', 1, 1),
		'subscription',
		'adminoptions',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 2),
		'user',
		'adminoptions',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 2),
		'user',
		'lastpostid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 1, 6),
		'cron',
		'active',
		'smallint',
		array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 1)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 2, 6),
		'cron',
		'varname',
		'varchar',
		array('length' => 100, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 3, 6),
		'cron',
		'volatile',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 4, 6),
		'cron',
		'product',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->execute(false);

	// Phrasing for Scheduled Tasks
	if ($upgrade->field_exists('cron', 'title'))
	{
		$updates = array();
		$cronfiles = array(
			'dailycleanup', 'birthday', 'threadviews', 'promotion', 'digestdaily', 'digestweekly', 'subscriptions',
			'cleanup', 'attachmentviews', 'activate', 'removebans', 'cleanup2', 'stats', 'reminder', 'infractions', 'ccbill', 'rssposter'
		);
		$cron = $db->query_read("
			SELECT cronid, filename, title
			FROM " . TABLE_PREFIX . "cron
			WHERE varname = ''
		");
		while ($croninfo = $db->fetch_array($cron))
		{
			$create_cron_phrases = true;

			$has_file_match = preg_match('#([a-z0-9_]+)\.php$#si', $croninfo['filename'], $match);
			if ($has_file_match AND in_array(strtolower($match[1]), $cronfiles))
			{
				$croninfo['varname'] = strtolower($match[1]);
				$croninfo['volatile'] = 1;

				// phrases are the XML already, don't need to create them
				$create_cron_phrases = false;
			}
			else if ($has_file_match)
			{
				// have a filename, that's a good way to prepend
				$croninfo['varname'] = strtolower($match[1]) . $croninfo['cronid'];
				$croninfo['volatile'] = 0;
			}
			else
			{
				$croninfo['varname'] = 'task' . $croninfo['cronid'];
				$croninfo['volatile'] = 0;
			}

			if ($create_cron_phrases)
			{
				$title = 'task_' . $db->escape_string($croninfo['varname']) . '_title';
				$desc = 'task_' . $db->escape_string($croninfo['varname']) . '_desc';
				$log = 'task_' . $db->escape_string($croninfo['varname']) . '_log';

				$upgrade->run_query(
					'', // don't display a message for this
					"REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid,  fieldname, varname, text, product)
					VALUES
						(0, 'cron', '$title', '" . $db->escape_string($croninfo['title']) . "', 'vbulletin'),
						(0, 'cron', '$desc', '', 'vbulletin'),
						(0, 'cron', '$log', '', 'vbulletin')"
				);
			}

			// now we need to update the actual entry
			$upgrade->run_query(
				$upgrade_phrases['upgrade_360b1.php']['updating_cron'],
				"UPDATE " . TABLE_PREFIX . "cron SET
					varname = '" . $db->escape_string($croninfo['varname']) . "',
					volatile = $croninfo[volatile]
				WHERE cronid = $croninfo[cronid]"
			);
		}
	}

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 5, 6),
		'cron',
		'title'
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cron', 6, 6),
		"ALTER TABLE " . TABLE_PREFIX . "cron ADD UNIQUE INDEX varname (varname)",
		MYSQL_ERROR_KEY_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cronlog', 1, 5),
		'cronlog',
		'type',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cronlog', 2, 5),
		'cronlog',
		'varname',
		'varchar',
		array('length' => 100, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cronlog', 3, 5),
		'cronlog',
		'varname',
		'varname'
	);

	if ($upgrade->field_exists('cronlog', 'cronid'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cronlog', 4, 5),
			"UPDATE " . TABLE_PREFIX . "cronlog AS cronlog, " . TABLE_PREFIX . "cron AS cron SET
				cronlog.varname = cron.varname
			WHERE cronlog.cronid = cron.cronid"
		);
	}

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'cronlog', 5, 5),
		'cronlog',
		'cronid'
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'announcement', 1, 1),
		'announcement',
		'enddate',
		array('enddate', 'forumid', 'startdate')
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "announcementread"),
		"CREATE TABLE " . TABLE_PREFIX . "announcementread (
			announcementid INT UNSIGNED NOT NULL DEFAULT '0',
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (announcementid, userid),
			KEY userid (userid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	if (!$upgrade->field_exists('search', 'announceids'))
	{
		// this must only be run once, so make sure the query that follows hasn't been run
		$upgrade->run_query(
			$upgrade_phrases['upgrade_360b1.php']['invert_banned_flag'],
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET genericoptions = IF(genericoptions & 32, genericoptions - 32, genericoptions + 32)"
		);
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'search', 1, 1),
		'search',
		'announceids',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 5)
{
	if ($upgrade->field_exists('subscription', 'description'))
	{
		// Phrasing for Subscriptions
		$subs = $db->query_read("
			SELECT subscriptionid, title, description
			FROM " . TABLE_PREFIX . "subscription
		");
		while ($sub = $db->fetch_array($subs))
		{
			$title = 'sub' . $sub['subscriptionid'] . '_title';
			$desc = 'sub' . $sub['subscriptionid'] . '_desc';

			$upgrade->run_query(
				$upgrade_phrases['upgrade_360b1.php']['updating_subscriptions'],
				"REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product)
				VALUES
					(0, 'subscription', '$title', '" . $db->escape_string($sub['title']) . "', 'vbulletin'),
					(0, 'subscription', '$desc', '" . $db->escape_string($sub['description']) . "', 'vbulletin')"
			);
		}
	}

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscription', 1, 2),
		'subscription',
		'title'
	);

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscription', 2, 2),
		'subscription',
		'description'
	);

	if ($upgrade->field_exists('holiday', 'varname'))
	{
		// Phrase changes for Holidays (remove varname, simplify)
		$holidays = $db->query_read("
			SELECT holidayid, varname
			FROM " . TABLE_PREFIX . "holiday
		");
		while ($holiday = $db->fetch_array($holidays))
		{
			$upgrade->run_query(
				'', // only output one message per holiday
				"UPDATE IGNORE " . TABLE_PREFIX . "phrase
					SET varname = 'holiday" . $holiday['holidayid'] . "_title'
				WHERE varname = 'holiday_title_" . $db->escape_string($holiday['varname']) . "'"
			);

			$upgrade->run_query(
				$upgrade_phrases['upgrade_360b1.php']['updating_holidays'],
				"UPDATE IGNORE " . TABLE_PREFIX . "phrase
					SET varname = 'holiday" . $holiday['holidayid'] . "_desc'
				WHERE varname = 'holiday_event_" . $db->escape_string($holiday['varname']) . "'"
			);
		}
	}

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'holiday', 1, 1),
		'holiday',
		'varname'
	);

	if ($upgrade->field_exists('profilefield', 'description'))
	{
		// Phrasing for custom profilefields
		$fields = $db->query_read("
			SELECT title, description, profilefieldid
			FROM " . TABLE_PREFIX . "profilefield
		");
		while ($field = $db->fetch_array($fields))
		{
			$title = 'field' . $field['profilefieldid'] . '_title';
			$desc = 'field' . $field['profilefieldid'] . '_desc';

			$upgrade->run_query(
				$upgrade_phrases['upgrade_360b1.php']['updating_profilefields'],
				"REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product)
				VALUES
					(0, 'cprofilefield', '$title', '" . $db->escape_string($field['title']) . "', 'vbulletin'),
					(0, 'cprofilefield', '$desc', '" . $db->escape_string($field['description']) . "', 'vbulletin')"
			);
		}
	}

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'profilefield', 1, 2),
		'profilefield',
		'title'
	);

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'profilefield', 2, 2),
		'profilefield',
		'description'
	);

	if ($upgrade->field_exists('reputationlevel', 'level'))
	{
		// Phrasing for Reputation Levels
		$levels = $db->query_read("
			SELECT level, reputationlevelid
			FROM " . TABLE_PREFIX . "reputationlevel
		");
		while ($level = $db->fetch_array($levels))
		{
			$desc = 'reputation' . $level['reputationlevelid'];

			$upgrade->run_query(
				$upgrade_phrases['upgrade_360b1.php']['updating_reputationlevels'],
				"REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product)
				VALUES
					(0, 'reputationlevel', '$desc', '" . $db->escape_string($level['level']) . "', 'vbulletin')"
			);
		}
	}

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'reputationlevel', 1, 1),
		'reputationlevel',
		'level'
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 1, 2),
		'language',
		'phrasegroup_cprofilefield',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 2, 2),
		'language',
		'phrasegroup_reputationlevel',
		'mediumtext',
		FIELD_DEFAULTS
	);

	// update phrase group list
	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype"),
		"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
			(title, editrows, fieldname)
		VALUES
			('{$phrasetype['cprofilefield']}', 3, 'cprofilefield'),
			('{$phrasetype['reputationlevel']}', 3, 'reputationlevel')"
	);

	$upgrade->execute();
}

// #############################################################################

// Misc Stuff
if ($vbulletin->GPC['step'] == 6)
{
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "podcast"),
		"CREATE TABLE " . TABLE_PREFIX . "podcast (
			forumid INT UNSIGNED NOT NULL DEFAULT '0',
			author VARCHAR(255) NOT NULL DEFAULT '',
			category VARCHAR(255) NOT NULL DEFAULT '',
			image VARCHAR(255) NOT NULL DEFAULT '',
			explicit SMALLINT NOT NULL DEFAULT '0',
			enabled SMALLINT NOT NULL DEFAULT '1',
			keywords VARCHAR(255) NOT NULL DEFAULT '',
			owneremail VARCHAR(255) NOT NULL DEFAULT '',
			ownername VARCHAR(255) NOT NULL DEFAULT '',
			subtitle VARCHAR(255) NOT NULL DEFAULT '',
			summary MEDIUMTEXT,
			categoryid SMALLINT NOT NULL DEFAULT '0',
			PRIMARY KEY  (forumid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'announcement', 1, 5),
		'announcement',
		'announcementoptions',
		'int',
		FIELD_DEFAULTS
	);

	if ($upgrade->field_exists('announcement', 'allowsmilies'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'announcement', 2, 5),
			"UPDATE " . TABLE_PREFIX . "announcement
				SET announcementoptions = 0
					+ IF(allowbbcode, 1, 0)
					+ IF(allowhtml, 2, 0)
					+ IF(allowsmilies, 4, 0)
					+ 8  # parseurl = yes
					+ 16 # signature = yes"
		);
	}

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'announcement', 3, 5),
		'announcement',
		'allowbbcode'
	);

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'announcement', 4, 5),
		'announcement',
		'allowhtml'
	);

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'announcement', 5, 5),
		'announcement',
		'allowsmilies'
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'faq', 1, 1),
		'faq',
		'product',
		'varchar',
		array('length' => 25, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 1, 1),
		'forum',
		'lastpostid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'tachythreadpost', 1, 1),
		'tachythreadpost',
		'lastpostid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'tachyforumpost', 1, 1),
		'tachyforumpost',
		'lastpostid',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 7)
{
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "sigpic"),
		"CREATE TABLE " . TABLE_PREFIX . "sigpic (
			  userid int unsigned NOT NULL default '0',
			  filedata mediumblob,
			  dateline int unsigned NOT NULL default '0',
			  filename varchar(100) NOT NULL default '',
			  visible smallint NOT NULL default '1',
			  filesize int unsigned NOT NULL default '0',
			  width smallint unsigned NOT NULL default '0',
			  height smallint unsigned NOT NULL default '0',
			  PRIMARY KEY  (userid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 1, 9),
		'usergroup',
		'signaturepermissions',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 2, 9),
		'usergroup',
		'sigpicmaxwidth',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 3, 9),
		'usergroup',
		'sigpicmaxheight',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 4, 9),
		'usergroup',
		'sigpicmaxsize',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 5, 9),
		'usergroup',
		'sigmaximages',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 6, 9),
		'usergroup',
		'sigmaxsizebbcode',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 7, 9),
		'usergroup',
		'sigmaxchars',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 8, 9),
		'usergroup',
		'sigmaxrawchars',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 9, 9),
		'usergroup',
		'sigmaxlines',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 1),
		'user',
		'sigpicrevision',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "sigparsed"),
		"CREATE TABLE " . TABLE_PREFIX . "sigparsed (
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			signatureparsed MEDIUMTEXT,
			hasimages SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (userid, styleid, languageid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	if (!$upgrade->field_exists('setting', 'validationcode'))
	{
		// give any group that has sig perms permission to use the appropriate bbcodes, etc
		// the "can have signature" perm has existed for a while, so that will take precedence over these settings
		$sig_perm_bits =(
			(($vbulletin->options['allowedbbcodes'] & 1) ? 1 : 0) + // basic bb codes
			(($vbulletin->options['allowedbbcodes'] & 2) ? 2 : 0) + // color bb code
			(($vbulletin->options['allowedbbcodes'] & 4) ? 4 : 0) + // size bb code
			(($vbulletin->options['allowedbbcodes'] & 8) ? 8 : 0) + // font bb code
			(($vbulletin->options['allowedbbcodes'] & 16) ? 16 : 0) + // align bb codes
			(($vbulletin->options['allowedbbcodes'] & 32) ? 32 : 0) + // list bb code
			(($vbulletin->options['allowedbbcodes'] & 64) ? 64 : 0) + // link bb codes
			(($vbulletin->options['allowedbbcodes'] & 128) ? 128 : 0) + // code bb code
			(($vbulletin->options['allowedbbcodes'] & 256) ? 256 : 0) + // php bb code
			(($vbulletin->options['allowedbbcodes'] & 512) ? 512 : 0) + // html bb code
			1024 + // quote is always allowed
			($vbulletin->options['allowbbimagecode'] ? 2048 : 0) + // images
			($vbulletin->options['allowsmilies'] ? 4096 : 0) + // smilies
			($vbulletin->options['allowhtml'] ? 8192 : 0) + // html
			// 16384 isn't used
			// 32768 = sig pics, handled in query itself
			// 65536 = can upload animated sig pics, handled in query
			($vbulletin->options['allowbbcode'] ? 131072 : 0) // global bbcode switch
		);

		$can_cp_sql = "adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'];

		// this has been removed from vbulletin-settings.xml so may possibly be missing if they used the new xml file before the upgrade
		$vbulletin->options['sigmax'] = (isset($vbulletin->options['sigmax']) ? $vbulletin->options['sigmax'] : 500);

		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 1, 1),
			"UPDATE " . TABLE_PREFIX . "usergroup SET
				signaturepermissions = $sig_perm_bits
					+ IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['canuseavatar'] . ", 32768, 0) # sig pic
					+ IF(genericpermissions & " . $vbulletin->bf_ugp_genericpermissions['cananimateavatar'] . ", 65536, 0), # animated sig pic
				sigmaxrawchars = IF($can_cp_sql, 0, " . intval(2 * $vbulletin->options['sigmax']) . "),
				sigmaxchars = IF($can_cp_sql, 0, " . intval($vbulletin->options['sigmax']) . "),
				sigmaxlines = 0,
				sigmaxsizebbcode = 7,
				sigmaximages = IF($can_cp_sql, 0, " . intval($vbulletin->options['maximages']) . "),
				sigpicmaxwidth = 500,
				sigpicmaxheight = 100,
				sigpicmaxsize = 20000
			"
		);
	}

	// add validation code to settings table
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 1, 1),
		'setting',
		'validationcode',
		'text',
		FIELD_DEFAULTS
	);

	// rename the post_parsed table so none of our tables have underscores
	$upgrade->run_query(
		 $upgrade_phrases['upgrade_360b1.php']['rename_post_parsed'],
		 "ALTER TABLE " . TABLE_PREFIX . "post_parsed RENAME " . TABLE_PREFIX . "postparsed",
		 MYSQL_ERROR_TABLE_MISSING
	);

	// update thread redirects to have TIMENOW for dateline
	$upgrade->run_query(
		$upgrade_phrases['upgrade_360b1.php']['updating_thread_redirects'],
		"UPDATE " . TABLE_PREFIX . "thread
			SET dateline = " . TIMENOW . "
		WHERE open = 10
			AND pollid > 0"
	);

	// set canignorequota for usergroups 5, 6 and 7
	if (!$upgrade->field_exists('forum', 'showprivate'))
	{
		$upgrade->run_query(
			$upgrade_phrases['upgrade_360b1.php']['install_canignorequota_permission'],
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET pmpermissions = pmpermissions + 4
			 WHERE usergroupid IN (5, 6, 7) AND NOT (pmpermissions & 4)"
		);
	}

	// add per-forum setting to show/hide private forums
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 1, 3),
		'forum',
		'showprivate',
		'tinyint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 2, 3),
		'forum',
		'defaultsortfield',
		'varchar',
		array('length' => 50, 'null' => false, 'default' => 'lastpost')
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 3, 3),
		'forum',
		'defaultsortorder',
		'enum',
		array('attributes' => "('asc', 'desc')", 'null' => false, 'default' => 'desc')
	);

	$upgrade->execute();

	// rebuild usergroupcache to reflect the changes
	build_forum_permissions();
}

// #############################################################################

// #############################################################################
// Infractions
if ($vbulletin->GPC['step'] == 8)
{
	// Infraction Table
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "infraction"),
		"CREATE TABLE " . TABLE_PREFIX . "infraction (
			infractionid INT UNSIGNED NOT NULL AUTO_INCREMENT ,
			infractionlevelid INT UNSIGNED NOT NULL DEFAULT '0',
			postid INT UNSIGNED NOT NULL DEFAULT '0',
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			whoadded INT UNSIGNED NOT NULL DEFAULT '0',
			points INT UNSIGNED NOT NULL DEFAULT '0',
			dateline INT UNSIGNED NOT NULL DEFAULT '0',
			note varchar(255) NOT NULL DEFAULT '',
			action SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			actiondateline INT UNSIGNED NOT NULL DEFAULT '0',
			actionuserid INT UNSIGNED NOT NULL DEFAULT '0',
			actionreason VARCHAR(255) NOT NULL DEFAULT '',
			expires INT UNSIGNED NOT NULL DEFAULT '0',
			threadid INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (infractionid),
			KEY expires (expires, action),
			KEY userid (userid, action),
			KEY infractonlevelid (infractionlevelid),
			KEY postid (postid),
			KEY threadid (threadid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// Infraction Groups Table
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "infractiongroup"),
		"CREATE TABLE " . TABLE_PREFIX . "infractiongroup (
			infractiongroupid INT UNSIGNED NOT NULL AUTO_INCREMENT ,
			usergroupid INT NOT NULL DEFAULT '0',
			orusergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			pointlevel INT UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (infractiongroupid),
			KEY usergroupid (usergroupid, pointlevel)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// Infraction Level Table
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "infractionlevel"),
		"CREATE TABLE " . TABLE_PREFIX . "infractionlevel (
			infractionlevelid INT UNSIGNED NOT NULL AUTO_INCREMENT ,
			points INT UNSIGNED NOT NULL DEFAULT '0',
			expires INT UNSIGNED NOT NULL DEFAULT '0',
			period ENUM('H','D','M','N') DEFAULT 'H' NOT NULL,
			warning SMALLINT UNSIGNED DEFAULT '0',
			PRIMARY KEY (infractionlevelid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// Add new language Groups
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 1, 2),
		'language',
		'phrasegroup_infraction',
		'mediumtext',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 2, 2),
		'language',
		'phrasegroup_infractionlevel',
		'mediumtext',
		FIELD_DEFAULTS
	);

	// Add new phrase groups
	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrasetype"),
		"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
			(fieldname , title , editrows, special)
		VALUES
			('infraction', '{$phrasetype['infraction']}', 3, 0),
			('infractionlevel', '{$phrasetype['infractionlevel']}', 3, 0)"
	);

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . "infractionlevel"),
		"INSERT IGNORE INTO " . TABLE_PREFIX . "infractionlevel
			(infractionlevelid, points, expires, period, warning)
		VALUES
			(1, 1, 10, 'D', 1),
			(2, 1, 10, 'D', 1),
			(3, 1, 10, 'D', 1),
			(4, 1, 10, 'D', 1)"
	);

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . "phrase"),
		"REPLACE INTO " . TABLE_PREFIX . "phrase
			(languageid, fieldname, varname, text, product)
		VALUES
			(0, 'infractionlevel', 'infractionlevel1_title', '" . $db->escape_string($upgrade_phrases['upgrade_360b1.php']['infractionlevel1_title']) . "', 'vbulletin'),
			(0, 'infractionlevel', 'infractionlevel2_title', '" . $db->escape_string($upgrade_phrases['upgrade_360b1.php']['infractionlevel2_title']) . "', 'vbulletin'),
			(0, 'infractionlevel', 'infractionlevel3_title', '" . $db->escape_string($upgrade_phrases['upgrade_360b1.php']['infractionlevel3_title']) . "', 'vbulletin'),
			(0, 'infractionlevel', 'infractionlevel4_title', '" . $db->escape_string($upgrade_phrases['upgrade_360b1.php']['infractionlevel4_title']) . "', 'vbulletin')"
	);

	// only do these perm updates once
	if (!$upgrade->field_exists('user', 'ipoints'))
	{
		// Make sure to zero out permissions from possible past usage
		$newperms = array(
			'genericpermissions' => array(
				$vbulletin->bf_ugp_genericpermissions['canreverseinfraction'],
				$vbulletin->bf_ugp_genericpermissions['canseeinfraction'],
				$vbulletin->bf_ugp_genericpermissions['cangiveinfraction'],
				$vbulletin->bf_ugp_genericpermissions['canemailmember'],
		));

		foreach ($newperms AS $permission => $permissions)
		{
			$upgrade->run_query(
				sprintf($vbphrase['update_table'], TABLE_PREFIX . "usergroup"),
				"UPDATE " . TABLE_PREFIX . "usergroup SET $permission = $permission & ~" . (array_sum($permissions))
			);
		}

		$infractionperms = $vbulletin->bf_ugp_genericpermissions['cangiveinfraction'] + $vbulletin->bf_ugp_genericpermissions['canseeinfraction'];
		// Set infraction permissions for admins, mods and super mods
		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET genericpermissions = genericpermissions | $infractionperms
			WHERE adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] . "
				OR adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . "
				OR usergroupid = 7"
		);

		// give infraction reversal perms to admins
		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET genericpermissions = genericpermissions | " . $vbulletin->bf_ugp_genericpermissions['canreverseinfraction'] ."
			WHERE adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
		);

		// Set can email member's permissions
		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET genericpermissions = genericpermissions | " . $vbulletin->bf_ugp_genericpermissions['canemailmember'] . "
			WHERE usergroupid NOT IN (1,3,4) AND genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']
		);
	}



	// Alter User Table
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 4),
		'user',
		'ipoints',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 4),
		'user',
		'infractions',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 3, 4),
		'user',
		'warnings',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'deletionlog', 1, 2),
		'deletionlog',
		'dateline',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'deletionlog', 2, 2),
		'deletionlog',
		'type',
		array('type', 'dateline')
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderation', 1, 3),
		'moderation',
		'dateline',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderation', 2, 3),
		'moderation',
		'type'
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderation', 3, 3),
		'moderation',
		'type',
		array('type', 'dateline')
	);

	if (!$upgrade->field_exists('user', 'infractiongroupids'))
	{
		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . "deletionlog"),
			"UPDATE " . TABLE_PREFIX . "deletionlog AS deletionlog, " . TABLE_PREFIX . "thread AS thread
				SET deletionlog.dateline = thread.lastpost
			WHERE deletionlog.primaryid = thread.threadid
				AND deletionlog.type = 'thread'"
		);

		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . "post"),
			"UPDATE " . TABLE_PREFIX . "deletionlog AS deletionlog, " . TABLE_PREFIX . "post AS post
				SET deletionlog.dateline = post.dateline
			WHERE deletionlog.primaryid = post.postid
				AND deletionlog.type = 'post'"
		);

		$upgrade->run_query(
			sprintf($vbphrase['update_table'], TABLE_PREFIX . "moderation"),
			"UPDATE " . TABLE_PREFIX . "moderation AS moderation, " . TABLE_PREFIX . "post AS post
				SET moderation.dateline = post.dateline
			WHERE moderation.postid = post.postid"
		);
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 4, 4),
		'user',
		'infractiongroupids',
		'varchar',
		array('length' => 255, 'attributes' => FIELD_DEFAULTS)
	);

	// drop usergroup.pmforwardmax
	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 1, 1),
		'usergroup',
		'pmforwardmax'
	);

	$upgrade->execute();
}

// #############################################################################
// misc modifications
if ($vbulletin->GPC['step'] == 9)
{
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'profilefield', 1, 1),
		'profilefield',
		'perline',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . "profilefield"),
		"UPDATE " . TABLE_PREFIX . "profilefield
			SET perline = def
		WHERE type = 'checkbox'"
	);

	if ($upgrade->field_exists('adminhelp', 'optionname'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'adminhelp', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "adminhelp CHANGE optionname optionname VARCHAR(100) NOT NULL DEFAULT ''"
		);
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'session', 1, 1),
		'session',
		'profileupdate',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 1, 3),
		'phrase',
		'username',
		'varchar',
		array('length' => 100, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 2, 3),
		'phrase',
		'dateline',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'phrase', 3, 3),
		'phrase',
		'version',
		'varchar',
		array('length' => 30, 'attributes' => FIELD_DEFAULTS)
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscriptionpermission"),
		"CREATE TABLE " . TABLE_PREFIX . "subscriptionpermission (
			subscriptionpermissionid int(10) unsigned NOT NULL auto_increment,
			subscriptionid int(10) unsigned NOT NULL default '0',
			usergroupid int(10) unsigned NOT NULL default '0',
			PRIMARY KEY  (subscriptionpermissionid),
			UNIQUE KEY subscriptionid (subscriptionid,usergroupid),
			KEY usergroupid (usergroupid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	if (!$db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentapi WHERE classname = 'ccbill'"))
	{
		$ccbill_settings =  array(
			'clientAccnum' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'string'
			),
			'clientSubacc' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'string'
			),
			'formName' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'string'
			),
			'secretword' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'string'
			),
			'username' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'string'
			),
			'password' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'string'
			)
		);

		$upgrade->run_query(
			$upgrade_phrases['upgrade_350b3.php']['paymentapi_data'],
			"INSERT INTO " . TABLE_PREFIX . "paymentapi
				(title, currency, recurring, classname, active, settings)
			VALUES
				('CCBill', 'usd', 0, 'ccbill', 0, '" . $db->escape_string(serialize($ccbill_settings)) . "')"
		);
	}

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'paymentinfo', 1, 1),
		'paymentinfo',
		'hash',
		'hash'
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'paymenttransaction', 1, 7),
		'paymenttransaction',
		'dateline',
		'int',
		 FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'paymenttransaction', 2, 7),
		'paymenttransaction',
		'paymentapiid',
		'int',
		 FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'paymenttransaction', 3, 7),
		'paymenttransaction',
		'request',
		'mediumtext',
		 FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'paymenttransaction', 4, 7),
		'paymenttransaction',
		'reversed',
		'int',
		 FIELD_DEFAULTS
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'paymenttransaction', 5, 7),
		'paymenttransaction',
		'dateline',
		'dateline'
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'paymenttransaction', 6, 7),
		'paymenttransaction',
		'transactionid',
		'transactionid'
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'paymenttransaction', 7, 7),
		'paymenttransaction',
		'paymentapiid',
		'paymentapiid'
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscriptionlog', 1, 2),
		'subscriptionlog',
		'userid',
		array('userid', 'subscriptionid')
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscriptionlog', 2, 2),
		'subscriptionlog',
		'subscriptionid',
		'subscriptionid'
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachmenttype', 1, 1),
		'attachmenttype',
		'enabled',
		'enabled'
	);

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmentpermission"),
		"CREATE TABLE " . TABLE_PREFIX . "attachmentpermission (
			attachmentpermissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			extension VARCHAR(20) BINARY NOT NULL DEFAULT '',
			usergroupid INT UNSIGNED NOT NULL,
			size INT UNSIGNED NOT NULL,
			width SMALLINT UNSIGNED NOT NULL,
			height SMALLINT UNSIGNED NOT NULL,
			attachmentpermissions INT UNSIGNED NOT NULL,
			PRIMARY KEY  (attachmentpermissionid),
			UNIQUE KEY extension (extension,usergroupid),
			KEY usergroupid (usergroupid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'datastore', 1, 1),
		'datastore',
		'unserialize',
		'smallint',
		array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 2)
	);

	// create rssfeed table
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "rssfeed"),
		"CREATE TABLE " . TABLE_PREFIX . "rssfeed (
			rssfeedid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(250) NOT NULL,
			url VARCHAR(250) NOT NULL,
			port SMALLINT UNSIGNED NOT NULL DEFAULT '80',
			ttl SMALLINT UNSIGNED NOT NULL DEFAULT '1500',
			maxresults SMALLINT NOT NULL DEFAULT '0',
			userid INT UNSIGNED NOT NULL,
			forumid SMALLINT UNSIGNED NOT NULL,
			iconid SMALLINT UNSIGNED NOT NULL,
			titletemplate MEDIUMTEXT NOT NULL,
			bodytemplate MEDIUMTEXT NOT NULL,
			searchwords MEDIUMTEXT NOT NULL,
			itemtype ENUM('thread','announcement') NOT NULL DEFAULT 'thread',
			threadactiondelay SMALLINT UNSIGNED NOT NULL,
			endannouncement INT UNSIGNED NOT NULL,
			options INT UNSIGNED NOT NULL,
			lastrun INT UNSIGNED NOT NULL,
			PRIMARY KEY  (rssfeedid),
			KEY lastrun (lastrun)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// create rsslog table
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "rsslog"),
		"CREATE TABLE " . TABLE_PREFIX . "rsslog (
			rssfeedid INT UNSIGNED NOT NULL,
			itemid INT UNSIGNED NOT NULL,
			itemtype ENUM('thread','announcement') NOT NULL DEFAULT 'thread',
			uniquehash CHAR(32) NOT NULL,
			contenthash CHAR(32) NOT NULL,
			dateline INT UNSIGNED NOT NULL,
			threadactiontime INT UNSIGNED NOT NULL,
			threadactioncomplete TINYINT UNSIGNED NOT NULL,
			PRIMARY KEY (rssfeedid,itemid,itemtype),
			UNIQUE KEY uniquehash (uniquehash)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// Update hidden profile cache to handle hidden AND required fields
	require_once(DIR . '/includes/adminfunctions_profilefield.php');
	build_profilefield_cache();
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "datastore WHERE title = 'hidprofilecache'");

	$upgrade->execute();

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "datastore
		SET unserialize = 1
		WHERE title IN (
			'options', 'forumcache', 'languagecache', 'stylecache', 'bbcodecache',
			'smiliecache', 'wol_spiders', 'usergroupcache', 'attachmentcache',
			'maxloggedin', 'userstats', 'birthdaycache', 'eventcache', 'iconcache',
			'products', 'pluginlist', 'pluginlistadmin', 'bitfields', 'ranks',
			'noavatarperms', 'acpstats', 'profilefield'
		)
	");
}

// #############################################################################
// super moderator permissions updates
if ($vbulletin->GPC['step'] == 10)
{
	$moderator_permissions = array_sum($vbulletin->bf_misc_moderatorpermissions) - ($vbulletin->bf_misc_moderatorpermissions['newthreademail'] + $vbulletin->bf_misc_moderatorpermissions['newpostemail']);

	$supergroups = $db->query_read("
		SELECT user.*, usergroup.usergroupid
		FROM " . TABLE_PREFIX . "usergroup AS usergroup
		INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
		LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON(moderator.userid = user.userid AND moderator.forumid = -1)
		WHERE (usergroup.adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ") AND moderator.forumid IS NULL
		GROUP BY user.userid
	");
	while ($supergroup = $db->fetch_array($supergroups))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_360b1.php']['super_moderator_x_updated'], $supergroup['username']),
			"INSERT INTO " . TABLE_PREFIX . "moderator
				(userid, forumid, permissions)
			VALUES
				($supergroup[userid], -1, $moderator_permissions)"
		);
	}

	$db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postparsed', 1, 8),
		'postparsed',
		'styleid',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postparsed', 2, 8),
		'postparsed',
		'languageid',
		'smallint',
		FIELD_DEFAULTS
	);

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postparsed', 3, 8),
		'postparsed',
		'styleid_code'
	);

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postparsed', 4, 8),
		'postparsed',
		'styleid_html'
	);

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postparsed', 5, 8),
		'postparsed',
		'styleid_php'
	);

	$upgrade->drop_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postparsed', 6, 8),
		'postparsed',
		'styleid_quote'
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postparsed', 7, 8),
		"ALTER TABLE " . TABLE_PREFIX . "postparsed DROP PRIMARY KEY",
		MYSQL_ERROR_DROP_KEY_COLUMN_MISSING
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postparsed', 8, 8),
		"ALTER TABLE " . TABLE_PREFIX . "postparsed ADD PRIMARY KEY (postid, styleid, languageid)",
		MYSQL_ERROR_PRIMARY_KEY_EXISTS
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachment', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'attachmenttype', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "attachmenttype CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
	);

	$upgrade->execute();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 11)
{
	require_once(DIR . '/includes/adminfunctions_attachment.php');
	build_attachment_permissions();

	vBulletinHook::build_datastore($db);
	build_product_datastore();

	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	?>
	<blockquote>
	<p class="navbody" style="padding: 3px; border: outset 2px;"><?php echo "{$upgrade_phrases['upgrade_360b1.php']['lastpostid_notice']}"; ?></p>
	</blockquote>
	<?php

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
