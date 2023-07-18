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

define('THIS_SCRIPT', 'upgrade_350b3.php');
define('VERSION', '3.5.0 Beta 3');
define('PREV_VERSION', '3.5.0 Beta 2');

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
	// translate vbulletin->options [allowvbcodebuttons] and [quickreply] into [editormodes]
	switch (intval($vbulletin->options['quickreply']))
	{
		case 3: $qr = 0; break;
		case 2: $qr = 1; break;
		case 1: $qr = 2; break;
		case 0: $qr = intval($vbulletin->options['allowvbcodebuttons']); break;
	}

	$editormodes = array(
		'fe' => intval($vbulletin->options['allowvbcodebuttons']),
		'qr' => $qr,
		'qe' => $qr
	);

	$upgrade->run_query(
		$upgrade_phrases['upgrade_350b3.php']['translating_allowvbcodebuttons'],
		"INSERT IGNORE INTO " . TABLE_PREFIX . "setting
			(varname, grouptitle, value, volatile, product)
		VALUES
			('editormodes', 'posting', '" . $db->escape_string(serialize($editormodes)) . "', 1, 'vbulletin')"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_350b3.php']['converting_phrases_x_of_y'], 1, 2),
		"UPDATE " . TABLE_PREFIX . "phrase
			SET varname = 'setting_editormodes_title'
		WHERE varname = 'setting_allowvbcodebuttons_title'"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_350b3.php']['converting_phrases_x_of_y'], 2, 2),
		"UPDATE " . TABLE_PREFIX . "phrase
			SET varname = 'setting_editormodes_desc'
		WHERE varname = 'setting_allowvbcodebuttons_desc'"
	);

	// translate vbulletin->options [quickreply] and [quickreplyclick] into [quickreply]
	if ($vbulletin->options['quickreply'])
	{
		if ($vbulletin->options['quickreplyclick'])
		{
			$quickreply = 2;
		}
		else
		{
			$quickreply = 1;
		}
	}
	else
	{
		$quickreply = 0;
	}

	$upgrade->run_query(
		$upgrade_phrases['upgrade_350b3.php']['translating_quickreply'],
		"UPDATE " . TABLE_PREFIX . "setting SET value = '$quickreply'
		WHERE varname = 'quickreply' AND product = 'vbulletin'"
	);

	$upgrade->execute();

	$custom_codes = $db->query_read("
		SELECT bbcodeid, bbcodereplacement
		FROM " . TABLE_PREFIX . "bbcode
	");
	while ($bbcode = $db->fetch_array($custom_codes))
	{
		$bbcode['bbcodereplacement'] = stripslashes(str_replace(
			array('%', '\\7', '\\4', '\\5'),
			array('%%', '%1$s', '%1$s', '%2$s'),
			$bbcode['bbcodereplacement']
		));

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "bbcode SET
				bbcodereplacement = '" . $db->escape_string($bbcode['bbcodereplacement']) . "'
			WHERE bbcodeid = $bbcode[bbcodeid]
		");
	}

	build_bbcode_cache();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 2)
{
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "paymentapi"),
		"CREATE TABLE " . TABLE_PREFIX . "paymentapi (
			paymentapiid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(250) NOT NULL DEFAULT '',
			currency VARCHAR(250) NOT NULL DEFAULT '',
			recurring SMALLINT NOT NULL DEFAULT '0',
			classname VARCHAR(250) NOT NULL DEFAULT '',
			active SMALLINT NOT NULL DEFAULT '0',
			settings MEDIUMTEXT,
			PRIMARY KEY (paymentapiid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	// need to run this query immediately for the if below to work. Keep the list open though.
	$upgrade->execute(false);

	if (!$db->query_first("SELECT title FROM " . TABLE_PREFIX . "paymentapi WHERE title = 'NOCHEX'"))
	{
		$upgrade->run_query(
			$upgrade_phrases['upgrade_350b3.php']['paymentapi_data'],
			"INSERT INTO " . TABLE_PREFIX . "paymentapi
				(title, currency, recurring, classname, active, settings)
			VALUES
				('Paypal', 'usd,gbp,eur,aud,cad', 1, 'paypal', " . (($vbulletin->options['subscriptionmethods'] & 1) ? 1 : 0) . ", ''),
				('NOCHEX', 'gbp', 0, 'nochex', " . (($vbulletin->options['subscriptionmethods'] & 2) ? 1 : 0) . ", '')"
		);
	}

	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "paymentinfo"),
		"CREATE TABLE " . TABLE_PREFIX . "paymentinfo (
			paymentinfoid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			hash VARCHAR(32) NOT NULL DEFAULT '',
			subscriptionid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			subscriptionsubid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			completed SMALLINT NOT NULL DEFAULT '0',
			PRIMARY KEY (paymentinfoid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	$upgrade->execute();
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
