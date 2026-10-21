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

define('THIS_SCRIPT', 'upgrade_350.php');
define('VERSION', '3.5.0');
define('PREV_VERSION', '3.5.0 Release Candidate 3');

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
	// Index update that speeds up userid = 2 and folderid = 2 (especially GROUP BY folderid / makeforumjump)
	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "pm DROP INDEX userid";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pm', 1, 3);

	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "pm DROP INDEX folderid";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pm', 2, 3);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "pm ADD index (userid, folderid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pm', 3, 3);

	// Index update that helps with the pm floodcheck -- trivial
	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "pmtext DROP INDEX fromuserid";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pmtext', 1, 2);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "pmtext ADD INDEX (fromuserid, dateline)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pmtext', 2, 2);

	// Index update that helps with subscribed threads, kills more filesorts
	// this index is named indexname in mysql-schema and subscribeindex in 3.0 beta 3 upgrade script ?!?
	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "subscribethread DROP INDEX indexname";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 1, 5);

	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "subscribethread DROP INDEX subscribeindex";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 2, 5);

	$query[]['hideerror'] = "ALTER TABLE " . TABLE_PREFIX . "subscribethread DROP INDEX threadid";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 3, 5);

	$query[] = "ALTER IGNORE TABLE " . TABLE_PREFIX . "subscribethread ADD UNIQUE (threadid, userid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 4, 5);

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "subscribethread ADD INDEX (userid, folderid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 5, 5);

	exec_queries();
}

// #############################################################################
// Thread Alter
if ($vbulletin->GPC['step'] == 2)
{
	$query[] = "ALTER TABLE " . TABLE_PREFIX . "thread ADD INDEX (dateline)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 1);

	exec_queries();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 3)
{
	// Build cache of usergroups with image permission set for all avatar categories
	build_image_permissions();

	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/**
* Stores a serialized list of usergroups who do not have permission to use any avatars into the datastore
*
* @return	None
*/
function build_image_permissions()
{
	global $vbulletin;
	$output = array();

	$categories = $vbulletin->db->query_read("
		SELECT imagecategory.imagecategoryid, COUNT(avatarid) AS avatars
		FROM " . TABLE_PREFIX . "imagecategory AS imagecategory
		LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.imagecategoryid=imagecategory.imagecategoryid)
		WHERE imagetype = 1
		GROUP BY imagecategory.imagecategoryid
		HAVING avatars > 0
	");

	$cats = array();
	while ($cat = $vbulletin->db->fetch_array($categories))
	{
			$cats[] = $cat['imagecategoryid'];
	}

	if (!empty($cats))
	{
		$noperms = $vbulletin->db->query_read("
			SELECT usergroupid, COUNT(*) AS count
			FROM " . TABLE_PREFIX . "imagecategorypermission
			WHERE imagecategoryid IN (" . implode(',', $cats) . ")
			GROUP BY usergroupid
			HAVING count = " . count($cats) . "
		");
		while ($noperm = $vbulletin->db->fetch_array($noperms))
		{
			$output[] = $noperm['usergroupid'];
		}
	}
	else	// No Avatars?
	{
		$output['all'] = true;
	}

	build_datastore('noavatarperms', serialize($output));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13367 $
|| ####################################################################
\*======================================================================*/
?>
