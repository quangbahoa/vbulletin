<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

define('THIS_SCRIPT', 'upgrade_370rc3.php');
define('VERSION', '3.7.0 Release Candidate 3');
define('PREV_VERSION', '3.7.0 Release Candidate 2');

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
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 1)
{
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	if (!isset($vbulletin->bf_ugp_adminpermissions['canadminnotices']))
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$upgradecore_phrases[wrong_bitfield_xml]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}

	// give all admins notices permissions by default
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "administrator SET
			adminpermissions = adminpermissions | " .
				($vbulletin->bf_ugp_adminpermissions['canadminnotices'] + $vbulletin->bf_ugp_adminpermissions['canadminmodlog'])
	);

	require_once(DIR . '/includes/functions_databuild.php');
	build_birthdays();

	$tables = $db->query_write("SHOW TABLES");
	while ($table = $db->fetch_array($tables, DBARRAY_NUM))
	{
		if (strpos($table[0], TABLE_PREFIX . 'aaggregate_temp_') !== false OR strpos($table[0], TABLE_PREFIX . 'taggregate_temp_') !== false)
		{
			if (!preg_match('/_(\d+)$/siU', $table[0], $matches))
			{
				continue;
			}

			if ($matches[1] > TIMENOW - 3600)
			{
				continue;
			}

			$upgrade->run_query(
				sprintf($upgrade_phrases['upgrade_370rc3.php']['dropping_old_table_x'], $table[0]),
				"DROP TABLE IF EXISTS " . $table[0]
			);
		}
	}

	$upgrade->execute();

	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13568 $
|| ####################################################################
\*======================================================================*/
?>