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

define('THIS_SCRIPT', 'upgrade_350b4.php');
define('VERSION', '3.5.0 Beta 4');
define('PREV_VERSION', '3.5.0 Beta 3');

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

	// Swap the hideprivate forums value and make sure not to set it to 1 if it doesn't exist
	$showprivateforums = ($vbulletin->options['hideprivateforums'] == 1 OR !isset($vbulletin->options['hideprivateforums'])) ? 0 : 1;
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 1, 1),
		"REPLACE INTO " . TABLE_PREFIX . "setting
			(varname, grouptitle, value, defaultvalue, optioncode, displayorder, advanced, volatile)
		VALUES
			('showprivateforums', 'forumlist', '$showprivateforums', '0', 'select:piped
0|no
1|yes_hide_post_counts
2|yes_display_post_counts', 30, 0, 1)"
	);

	// if the thread.hiddencount field exists, we know this has been run
	if (!$upgrade->field_exists('thread', 'hiddencount'))
	{
		$upgrade->run_query(
			$upgrade_phrases['upgrade_350b4.php']['invert_moderate_permission'],
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET forumpermissions = IF(forumpermissions & 131072, forumpermissions - 131072, forumpermissions + 131072)"
		);

		$upgrade->run_query(
			$upgrade_phrases['upgrade_350b4.php']['invert_moderate_permission'],
			"UPDATE " . TABLE_PREFIX . "forumpermission
				SET forumpermissions = IF(forumpermissions & 131072, forumpermissions - 131072, forumpermissions + 131072)"
		);
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 1),
		'thread',
		'hiddencount',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->execute();

	require_once(DIR . '/includes/class_bitfield_builder.php');
	if (!vB_Bitfield_Builder::save($db))
	{ // couldn't build bitfields bail out
		echo "<strong>error</strong>\n";
		print_r(vB_Bitfield_Builder::fetch_errors());
	}

	build_options(); // Get showprivateforums into the system
	build_forum_permissions();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 2)
{
	$upgrade->run_query(
		sprintf($vbphrase['create_table'], TABLE_PREFIX . "paymenttransaction"),
		"CREATE TABLE " . TABLE_PREFIX . "paymenttransaction (
			paymenttransactionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			paymentinfoid INT UNSIGNED NOT NULL DEFAULT '0',
			transactionid VARCHAR(250) NOT NULL DEFAULT '',
			state SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			amount DOUBLE UNSIGNED NOT NULL DEFAULT '0',
			currency VARCHAR(5) NOT NULL DEFAULT '',
			PRIMARY KEY (paymenttransactionid)
		)",
		MYSQL_ERROR_TABLE_EXISTS
	);

	if (!$db->query_first("SELECT title FROM " . TABLE_PREFIX . "paymentapi WHERE title = '2Checkout'"))
	{
		$upgrade->run_query(
			$upgrade_phrases['upgrade_350b3.php']['paymentapi_data'],
			"INSERT INTO " . TABLE_PREFIX . "paymentapi
				(title, currency, recurring, classname, active, settings)
			VALUES
				('Worldpay', 'usd,gbp,eur', 1, 'worldpay', " . (($vbulletin->options['subscriptionmethods'] & 4) ? 1 : 0) . ", ''),
				('Authorize.Net', 'usd,gbp,eur', 0, 'authorizenet', " . (($vbulletin->options['subscriptionmethods'] & 8) ? 1 : 0) . ", ''),
				('2Checkout', 'usd', 0, '2checkout', 0, '')"
		);
	}

	$apiinfo = array(
		'paypal' => array(
			'ppemail' => array(
				'type' => 'text',
				'value' => "{$vbulletin->options['ppemail']}",
				'validate' => 'string'
			)
		),
		'nochex' => array(
			'ncxemail' => array(
				'type' => 'text',
				'value' => "{$vbulletin->options['ncxemail']}",
				'validate' => 'string'
			)
		),
		'worldpay' => array(
			'worldpay_instid' => array(
				'type' => 'text',
				'value' => "{$vbulletin->options['worldpay_instid']}",
				'validate' => 'string'
			),
			'worldpay_password' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'string'
			)
		),
		'authorizenet' => array(
			'authorize_loginid' => array(
				'type' => 'text',
				'value' => "{$vbulletin->options['authorize_loginid']}",
				'validate' => 'string'
			),
			'txnkey' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'string'
			)
		),
		'2checkout' => array(
			'twocheckout_id' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'number'
			),
			'secret_word' => array(
				'type' => 'text',
				'value' => '',
				'validate' => 'string'
			)
		)
	);

	foreach ($apiinfo AS $classname => $settings)
	{
		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_350b4.php']['adding_payment_api_x_settings'], $classname),
			"UPDATE " . TABLE_PREFIX . "paymentapi
				SET settings = '" . $db->escape_string(serialize($settings)) . "'
			WHERE classname = '" . $db->escape_string($classname) . "'"
		);
	}

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
