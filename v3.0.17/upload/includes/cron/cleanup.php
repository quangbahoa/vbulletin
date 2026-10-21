<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

if (!is_object($DB_site))
{
	exit;
}

$DB_site->query("
	### Delete stale sessions ###
	DELETE FROM " . TABLE_PREFIX . "session
	WHERE lastactivity < " . intval(TIMENOW - $vboptions['cookietimeout'])
);

$DB_site->query("
	### Delete stale cpsessions ###
	DELETE FROM " . TABLE_PREFIX . "cpsession
	WHERE dateline < " . intval(TIMENOW - 3600)
);

//searches expire after one hour
$DB_site->query("
	### Remove stale searches ###
	DELETE FROM " . TABLE_PREFIX . "search
	WHERE dateline < " . (TIMENOW - 3600)
);

// expired lost passwords and email confirmations after 4 days
$DB_site->query("
	### Delete stale password and email confirmation requests ###
	DELETE FROM " . TABLE_PREFIX . "useractivation
	WHERE dateline < " . (TIMENOW - 345600) . " AND
	(type = 1 OR (type = 0 and usergroupid = 2))
");

log_cron_action('Hourly Cleanup #1 Completed', $nextitem);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9722 $
|| ####################################################################
\*======================================================================*/
?>