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

define('THIS_SCRIPT', 'upgrade_3811b2.php');
define('VERSION', '3.8.11 Beta 2');
define('PREV_VERSION', '3.8.11 Beta 1');

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
// IPv6 Updates
if ($vbulletin->GPC['step'] == 1)
{
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'session', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "session CHANGE host host VARCHAR(45) NOT NULL DEFAULT ''"
	);
	
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'search', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "search CHANGE ipaddress ipaddress VARCHAR(45) NOT NULL DEFAULT ''"
	);
	
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'threadrate', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "threadrate CHANGE ipaddress ipaddress VARCHAR(45) NOT NULL DEFAULT ''"
	);
	
	if ($upgrade->field_exists('apilog', 'ipaddress'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'apilog', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "apilog CHANGE ipaddress ipaddress VARCHAR(45) NOT NULL DEFAULT ''"
		);
	}
	
	if ($upgrade->field_exists('apiclient', 'initialipaddress'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'apiclient', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "apiclient CHANGE initialipaddress initialipaddress VARCHAR(45) NOT NULL DEFAULT ''"
		);
	}

	if (!$upgrade->field_exists('postlog', 'ipaddress'))
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postlog', 1, 2),
			"TRUNCATE TABLE " . TABLE_PREFIX . "postlog"
		);

		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'postlog', 2, 2),
			"ALTER TABLE " . TABLE_PREFIX . "postlog ADD COLUMN ipaddress VARCHAR(45) NOT NULL DEFAULT ''"
		);
	}

	if (!$upgrade->field_exists('ipaddress', 'ipid'))
	{
		$upgrade->run_query(
			sprintf($vbphrase['create_table'], TABLE_PREFIX . 'ipaddress'),
			"CREATE TABLE " . TABLE_PREFIX . "ipaddress (
				ipid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				contentid INT(10) UNSIGNED NOT NULL DEFAULT '0',
				contenttype ENUM('groupmessage','visitormessage','picturecomment','other') NOT NULL DEFAULT 'other',
				dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ip VARCHAR(45) NOT NULL DEFAULT '0.0.0.0',
				altip VARCHAR(45) NOT NULL DEFAULT '0.0.0.0',
				PRIMARY KEY (contenttype, contentid),
				UNIQUE INDEX ipid (ipid)
			)",
			MYSQL_ERROR_TABLE_EXISTS
		);
	}

	$upgrade->execute();
}

// #############################################################################
// Group Messages
if ($vbulletin->GPC['step'] == 2)
{
	$fieldid = 'gmid';
	$type = 'groupmessage';

	$records = $db->query_read_slave("
		SELECT tp.*
		FROM " . TABLE_PREFIX . "$type tp
		LEFT JOIN " . TABLE_PREFIX . "ipaddress ip ON (tp.ipaddress = ip.ipid)
		WHERE ipid IS NULL
	");

	while ($record = $db->fetch_array($records))
	{
		$ipman = datamanager_init('IPAddress', $vbulletin, ERRTYPE_STANDARD);

		$contentid = $record[$fieldid];
		$ipaddress = long2ip($record['ipaddress']);

		$ipman->set('ip', $ipaddress);
		$ipman->set('altip', $ipaddress);
		$ipman->set('contenttype', $type);
		$ipman->set('contentid', $contentid);
		$ipman->set('dateline', $record['dateline']);

		$ipid = $ipman->save();
		$ipman->update_content($type, $fieldid, $ipid, $contentid);

		unset($ipman);
	}

	$upgrade->show_message(sprintf($vbphrase['update_table'], TABLE_PREFIX . $type));

	$upgrade->execute();
}

// #############################################################################
// Picture Comments
if ($vbulletin->GPC['step'] == 3)
{
	$fieldid = 'commentid';
	$type = 'picturecomment';

	$records = $db->query_read_slave("
		SELECT tp.*
		FROM " . TABLE_PREFIX . "$type tp
		LEFT JOIN " . TABLE_PREFIX . "ipaddress ip ON (tp.ipaddress = ip.ipid)
		WHERE ipid IS NULL
	");

	while ($record = $db->fetch_array($records))
	{
		$ipman = datamanager_init('IPAddress', $vbulletin, ERRTYPE_STANDARD);

		$contentid = $record[$fieldid];
		$ipaddress = long2ip($record['ipaddress']);

		$ipman->set('ip', $ipaddress);
		$ipman->set('altip', $ipaddress);
		$ipman->set('contenttype', $type);
		$ipman->set('contentid', $contentid);
		$ipman->set('dateline', $record['dateline']);

		$ipid = $ipman->save();
		$ipman->update_content($type, $fieldid, $ipid, $contentid);

		unset($ipman);
	}

	$upgrade->show_message(sprintf($vbphrase['update_table'], TABLE_PREFIX . $type));

	$upgrade->execute();
}

// #############################################################################
// Visitor Message
if ($vbulletin->GPC['step'] == 4)
{
	$fieldid = 'vmid';
	$type = 'visitormessage';

	$records = $db->query_read_slave("
		SELECT tp.*
		FROM " . TABLE_PREFIX . "$type tp
		LEFT JOIN " . TABLE_PREFIX . "ipaddress ip ON (tp.ipaddress = ip.ipid)
		WHERE ipid IS NULL
	");

	while ($record = $db->fetch_array($records))
	{
		$ipman = datamanager_init('IPAddress', $vbulletin, ERRTYPE_STANDARD);

		$contentid = $record[$fieldid];
		$ipaddress = long2ip($record['ipaddress']);

		$ipman->set('ip', $ipaddress);
		$ipman->set('altip', $ipaddress);
		$ipman->set('contenttype', $type);
		$ipman->set('contentid', $contentid);
		$ipman->set('dateline', $record['dateline']);

		$ipid = $ipman->save();
		$ipman->update_content($type, $fieldid, $ipid, $contentid);

		unset($ipman);
	}

	$upgrade->show_message(sprintf($vbphrase['update_table'], TABLE_PREFIX . $type));

	$upgrade->execute();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 5)
{
	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023
|| # CVS: $RCSfile$ - $Revision: 13568 $
|| ####################################################################
\*======================================================================*/
?>
