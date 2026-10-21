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
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "thread ADD hiddencount INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 1);

	// Swap the hideprivate forums value and make sure not to set it to 1 if it doesn't exist
	$showprivateforums = ($vbulletin->options['hideprivateforums'] == 1 OR !isset($vbulletin->options['hideprivateforums'])) ? 0 : 1;
	build_options(); // Get showprivateforums into the system

	$query[] = "UPDATE " . TABLE_PREFIX . "setting SET value = '$showprivateforums' WHERE varname = 'showprivateforums'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'setting', 1, 1);
	// build_options() will run again in the final step

	$query[] = "UPDATE " . TABLE_PREFIX . "usergroup SET forumpermissions = IF(forumpermissions & 131072, forumpermissions - 131072, forumpermissions + 131072)";
	$explain[] = $upgrade_phrases['upgrade_350b4.php']['invert_moderate_permission'];

	$query[] = "UPDATE " . TABLE_PREFIX . "forumpermission SET forumpermissions = IF(forumpermissions & 131072, forumpermissions - 131072, forumpermissions + 131072)";
	$explain[] = $upgrade_phrases['upgrade_350b4.php']['invert_moderate_permission'];

	exec_queries();

	require_once(DIR . '/includes/class_bitfield_builder.php');
	if (!vB_Bitfield_Builder::save($db))
	{ // couldn't build bitfields bail out
		echo "<strong>error</strong>\n";
		print_r(vB_Bitfield_Builder::fetch_errors());
	}

	build_forum_permissions();

}

// #############################################################################
if ($vbulletin->GPC['step'] == 2)
{
	$query[] = "CREATE TABLE " . TABLE_PREFIX . "paymenttransaction (
		paymenttransactionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
		paymentinfoid INT UNSIGNED NOT NULL DEFAULT '0',
		transactionid VARCHAR(250) NOT NULL DEFAULT '',
		state SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		amount DOUBLE UNSIGNED NOT NULL DEFAULT '0',
		currency VARCHAR(5) NOT NULL DEFAULT '',
		PRIMARY KEY (paymenttransactionid)
	)";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "paymenttransaction");

	$query[] = "INSERT INTO " . TABLE_PREFIX . "paymentapi (title, currency, recurring, classname, active, settings) VALUES
		('Worldpay', 'usd,gbp,eur', 1, 'worldpay', " . (($vbulletin->options['subscriptionmethods'] & 4) ? 1 : 0) . ", ''),
		('Authorize.Net', 'usd,gbp,eur', 0, 'authorizenet', " . (($vbulletin->options['subscriptionmethods'] & 8) ? 1 : 0) . ", ''),
		('2Checkout', 'usd', 0, '2checkout', 0, '')
	";
	$explain[] = $upgrade_phrases['upgrade_350b3.php']['paymentapi_data'];

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
		$query[] = "UPDATE " . TABLE_PREFIX . "paymentapi SET settings = '" . $db->escape_string(serialize($settings)) . "' WHERE classname = '" . $db->escape_string($classname) . "'";
		$explain[] = sprintf($upgrade_phrases['upgrade_350b4.php']['adding_payment_api_x_settings'], $classname);
	}

	exec_queries();
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
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13428 $
|| ####################################################################
\*======================================================================*/
?>
