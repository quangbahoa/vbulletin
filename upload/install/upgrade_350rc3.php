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
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 1, 2),
		"ALTER TABLE " . TABLE_PREFIX . "forum CHANGE description_clean description_clean TEXT"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 2, 2),
		"ALTER TABLE " . TABLE_PREFIX . "forum CHANGE description description TEXT"
	);

	// Since we now require a year with required birthdates, we need to allow users to opt out of displaying it to all
	// Three states - (0) Display Nothing (1) Display Age (2) Display Age and Date of Birth
	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 3),
		'user',
		'showbirthday',
		'smallint',
		array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 2)
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 3),
		'user',
		'birthday'
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 3, 3),
		'user',
		'birthday',
		array('birthday', 'showbirthday')
	);

	$paypalinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentapi WHERE classname = 'paypal'");
	$ppsettings = vb_unserialize($paypalinfo['settings']);

	if (empty($ppsettings['primaryemail']))
	{
		$ppsettings['primaryemail'] = array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		);

		$upgrade->run_query(
			sprintf($upgrade_phrases['upgrade_350rc3.php']['updating_payment_api_x_settings'], 'paypal'),
			"UPDATE " . TABLE_PREFIX . "paymentapi
				SET settings = '" . $db->escape_string(serialize($ppsettings)) . "'
			WHERE classname = 'paypal'"
		);
	}

	$upgrade->execute();
}

// #############################################################################
// Thread Alters
if ($vbulletin->GPC['step'] == 2)
{
	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 2),
		'thread',
		'lastpost',
		array('lastpost', 'forumid')
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 2, 2),
		'thread',
		'iconid'
	);

	$upgrade->execute();

	echo '<blockquote><p>&nbsp;</p>';
	echo $upgrade_phrases['upgrade_350rc3.php']['please_wait_message'];
	echo '<p>&nbsp;</p></blockquote>';

}

// #############################################################################
// Post Alter
if ($vbulletin->GPC['step'] == 3)
{
	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'post', 1, 1),
		'post',
		'iconid'
	);

	$upgrade->execute();
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
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
