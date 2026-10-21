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
	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pm', 1, 3),
		'pm',
		'userid'
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pm', 2, 3),
		'pm',
		'folderid'
	);

	$upgrade->add_index(
		 sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pm', 3, 3),
		 'pm',
		 'userid',
		 array('userid', 'folderid')
	);

	// Index update that helps with the pm floodcheck -- trivial
	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pmtext', 1, 2),
		'pmtext',
		'fromuserid'
	);

	$upgrade->add_index(
		 sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pmtext', 2, 2),
		 'pmtext',
		 'fromuserid',
		 array('fromuserid', 'dateline')
	);

	// Index update that helps with subscribed threads, kills more filesorts
	// this index is named indexname in mysql-schema and subscribeindex in 3.0 beta 3 upgrade script ?!?
	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 1, 5),
		'subscribethread',
		'indexname'
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 2, 5),
		'subscribethread',
		'subscribeindex'
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 3, 5),
		'subscribethread',
		'threadid'
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 4, 5),
		"ALTER TABLE " . TABLE_PREFIX . "subscribethread ADD UNIQUE threadid (threadid, userid)",
		MYSQL_ERROR_KEY_EXISTS
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'subscribethread', 5, 5),
		'subscribethread',
		'userid',
		array('userid', 'folderid')
	);

	$upgrade->execute();
}

// #############################################################################
// Thread Alter
if ($vbulletin->GPC['step'] == 2)
{
	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'thread', 1, 1),
		'thread',
		'dateline',
		'dateline'
	);

	$upgrade->execute();
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
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
