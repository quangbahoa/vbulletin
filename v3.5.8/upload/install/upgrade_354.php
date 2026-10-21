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

define('THIS_SCRIPT', 'upgrade_354.php');
define('VERSION', '3.5.4');
define('PREV_VERSION', '3.5.3');

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
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscribethread ADD canview SMALLINT UNSIGNED NOT NULL DEFAULT 1";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 1, 1);

	$authorize_info = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentapi WHERE classname = 'authorizenet'");
	$unserialized = unserialize($authorize_info['settings']);
	if (!is_array($unserialized))
	{
		$unserialized = array();
	}
	$authorize_settings = array_merge(
		$unserialized,
		array('authorize_md5secret' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		))
	);

	$query[] = "UPDATE " . TABLE_PREFIX . "paymentapi SET settings = '" . $db->escape_string(serialize($authorize_settings)) . "' WHERE classname = 'authorizenet'";
	$explain[] = sprintf($upgrade_phrases['upgrade_350b4.php']['adding_payment_api_x_settings'], 'authorizenet');

	exec_queries();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 2)
{
	delete_product('vb_skypeweb_update');
	delete_product('vb353security');

	vBulletinHook::build_datastore($db);
	build_product_datastore();

	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14338 $
|| ####################################################################
\*======================================================================*/
?>