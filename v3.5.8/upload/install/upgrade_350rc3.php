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

define('THIS_SCRIPT', 'upgrade_350rc3.php');
define('VERSION', '3.5.0 Release Candidate 3');
define('PREV_VERSION', '3.5.0 Release Candidate 2');

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
// Misc Alter
if ($vbulletin->GPC['step'] == 1)
{
	// Beta2 - RC2 New installs have these fields as varchar
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "forum CHANGE description_clean description_clean TEXT";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 1, 2);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "forum CHANGE description description TEXT";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 2, 2);

	// Since we now require a year with required birthdates, we need to allow users to opt out of displaying it to all
	// Three states - (0) Display Nothing (1) Display Age (2) Display Age and Date of Birth
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user ADD showbirthday SMALLINT UNSIGNED DEFAULT '2' NOT NULL";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 3);

	$query[]['hideerror'] =  "ALTER TABLE " . TABLE_PREFIX . "user DROP INDEX birthday";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 3);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "user ADD INDEX birthday (birthday, showbirthday)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 3, 3);

	$paypalinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentapi WHERE classname = 'paypal'");
	$ppsettings = unserialize($paypalinfo['settings']);

	if (empty($ppsettings['primaryemail']))
	{
		$ppsettings['primaryemail'] = array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		);
		$query[] = "UPDATE " . TABLE_PREFIX . "paymentapi SET settings = '" . $db->escape_string(serialize($ppsettings)) . "' WHERE classname = 'paypal'";
		$explain[] = sprintf($upgrade_phrases['upgrade_350rc3.php']['updating_payment_api_x_settings'], 'paypal');
	}

	exec_queries();
}

// #############################################################################
// Thread Alters
if ($vbulletin->GPC['step'] == 2)
{
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "thread ADD index (lastpost, forumid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 2);

	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "thread DROP INDEX iconid";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 2, 2);

	exec_queries();

	echo '<blockquote><p>&nbsp;</p>';
	echo $upgrade_phrases['upgrade_350rc3.php']['please_wait_message'];
	echo '<p>&nbsp;</p></blockquote>';

}

// #############################################################################
// Post Alter
if ($vbulletin->GPC['step'] == 3)
{
	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "post DROP INDEX iconid";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post', 1, 1);

	exec_queries();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 4)
{
	// need to rerun this because of a bug in RC2 that wouldn't update it when necessary
	build_product_datastore();

	// build bitfields to catch the new language option 'dirmark'
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
|| # CVS: $RCSfile$ - $Revision: 13431 $
|| ####################################################################
\*======================================================================*/
?>
