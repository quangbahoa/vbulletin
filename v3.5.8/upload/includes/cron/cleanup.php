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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "session
	WHERE lastactivity < " . intval(TIMENOW - $vbulletin->options['cookietimeout']) . "
	### Delete stale sessions ###
");

$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "cpsession
	WHERE dateline < " . intval(TIMENOW - 3600) . "
	### Delete stale cpsessions ###
");

//searches expire after one hour
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "search
	WHERE dateline < " . (TIMENOW - 3600) . "
	### Remove stale searches ###
");

// expired lost passwords and email confirmations after 4 days
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "useractivation
	WHERE dateline < " . (TIMENOW - 345600) . " AND
	(type = 1 OR (type = 0 and usergroupid = 2))
	### Delete stale password and email confirmation requests ###
");

// old forum/thread read marking data
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "threadread
	WHERE readtime < " . (TIMENOW - ($vbulletin->options['markinglimit'] * 86400))
);
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "forumread
	WHERE readtime < " . (TIMENOW - ($vbulletin->options['markinglimit'] * 86400))
);

($hook = vBulletinHook::fetch_hook('cron_script_cleanup')) ? eval($hook) : false;

log_cron_action('Hourly Cleanup #1 Completed', $nextitem);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12513 $
|| ####################################################################
\*======================================================================*/
?>