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

// all these stats are for that day
$timestamp = TIMENOW - 3600 * 23;
// note: we only subtract 23 hours from the current time to account for Spring DST. Bug id 2673.

$month = date('n', $timestamp);
$day = date('j', $timestamp);
$year = date('Y', $timestamp);

$timestamp = mktime(0, 0, 0, $month, $day, $year);
// new users
$newusers = $DB_site->query_first("SELECT COUNT(userid) AS total FROM " . TABLE_PREFIX . "user WHERE joindate >= " . $timestamp);
$newusers['total'] = intval($newusers['total']);

// new threads
$newthreads = $DB_site->query_first("SELECT COUNT(threadid) AS total FROM " . TABLE_PREFIX . "thread WHERE dateline >= " . $timestamp);
$newthreads['total'] = intval($newthreads['total']);

// new posts
$newposts = $DB_site->query_first("SELECT COUNT(threadid) AS total FROM " . TABLE_PREFIX . "post WHERE dateline >= " . $timestamp);
$newposts['total'] = intval($newposts['total']);

// active users
$activeusers = $DB_site->query_first("SELECT COUNT(userid) AS total FROM " . TABLE_PREFIX . "user WHERE lastactivity >= " . $timestamp);
$activeusers['total'] = intval($activeusers['total']);

$DB_site->query("
	INSERT IGNORE INTO " . TABLE_PREFIX . "stats
		(dateline, nuser, nthread, npost, ausers)
	VALUES
		($timestamp, $newusers[total], $newthreads[total], $newposts[total], $activeusers[total])
");

log_cron_action('Statistics Saved', $nextitem);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9412 $
|| ####################################################################
\*======================================================================*/
?>