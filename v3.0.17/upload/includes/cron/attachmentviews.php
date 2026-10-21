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

$attachments = $DB_site->query("SELECT attachmentid, COUNT(*) AS views FROM " . TABLE_PREFIX . "attachmentviews GROUP BY attachmentid");

while ($attachment = $DB_site->fetch_array($attachments))
{
	$DB_site->query("UPDATE " . TABLE_PREFIX . "attachment SET counter = counter + $attachment[views] WHERE attachmentid = $attachment[attachmentid]");
}

log_cron_action('Attachment Views Updated', $nextitem);

$DB_site->query("DELETE FROM " . TABLE_PREFIX . "attachmentviews");

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9305 $
|| ####################################################################
\*======================================================================*/
?>