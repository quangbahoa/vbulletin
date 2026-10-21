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

$threads = $DB_site->query("SELECT threadid , COUNT(*) AS views FROM " . TABLE_PREFIX . "threadviews GROUP BY threadid");

while ($thread = $DB_site->fetch_array($threads))
{
	$DB_site->query(
		"UPDATE " . TABLE_PREFIX . "thread
		SET views = views + " . intval($thread['views']) . "
		WHERE threadid = " . intval($thread['threadid'])
	);
}

log_cron_action('Thread Views Updated', $nextitem);

$DB_site->query("DELETE FROM " . TABLE_PREFIX . "threadviews");

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9305 $
|| ####################################################################
\*======================================================================*/
?>