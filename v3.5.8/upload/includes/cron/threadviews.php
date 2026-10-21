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

$threadids = '';
$threadcasesql = '';

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->lock_tables(array('threadviews' => 'WRITE'));
}

$threads = $vbulletin->db->query_read("
	SELECT threadid, COUNT(*) AS views
	FROM " . TABLE_PREFIX . "threadviews
	GROUP BY threadid
");

while ($thread = $vbulletin->db->fetch_array($threads))
{
	$threadcasesql .= " WHEN threadid = $thread[threadid] THEN $thread[views]";
	$threadids .= ",$thread[threadid]";
}

if (!empty($threadids))
{
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "threadviews");
}

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->unlock_tables();
}

if (!empty($threadids))
{

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "thread
		SET views = views +
		CASE
			$threadcasesql
			ELSE 0
		END
		WHERE threadid IN (0$threadids)
	");
}

log_cron_action('Thread Views Updated', $nextitem);

$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "threadviews");

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13587 $
|| ####################################################################
\*======================================================================*/
?>