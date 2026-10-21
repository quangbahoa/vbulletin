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

define('THIS_SCRIPT', 'upgrade_351.php');
define('VERSION', '3.5.1');
define('PREV_VERSION', '3.5.0');

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
// Post Alter
if ($vbulletin->GPC['step'] == 1)
{
	// vBulletin says no, <cough>
	$query[] = "DELETE FROM " . TABLE_PREFIX . "product WHERE title = 'vbulletin'";
	$explain[] = $upgrade_phrases['upgrade_351.php']['delete_vb_product'];

	$query[] = "INSERT INTO " . TABLE_PREFIX . "paymentapi (title, currency, recurring, classname, active, settings) VALUES
		('Moneybookers', 'usd,gbp', 0, 'moneybookers', 0, '')
	";
	$explain[] = $upgrade_phrases['upgrade_350b3.php']['paymentapi_data'];

	$mb_settings =  array(
		'mbemail' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'mbsecret' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		)
	);

	$query[] = "UPDATE " . TABLE_PREFIX . "paymentapi SET settings = '" . $db->escape_string(serialize($mb_settings)) . "' WHERE classname = 'moneybookers'";
	$explain[] = sprintf($upgrade_phrases['upgrade_350b4.php']['adding_payment_api_x_settings'], $classname);

	exec_queries();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 2)
{
	build_product_datastore();

	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13579 $
|| ####################################################################
\*======================================================================*/
?>
