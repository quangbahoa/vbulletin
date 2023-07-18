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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
define('MYSQL_VERSION', $mysqlversion['version']);

$aggtable = "aaggregate_temp_$nextitem[nextrun]";

$vbulletin->db->query_write("
	CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "$aggtable (
		attachmentid INT UNSIGNED NOT NULL DEFAULT '0',
		views INT UNSIGNED NOT NULL DEFAULT '0',
		KEY attachmentid (attachmentid)
	) ENGINE = MEMORY");

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->lock_tables(array(
		$aggtable         => 'WRITE',
		'attachmentviews' => 'WRITE'
	));
}

$vbulletin->db->query_write("
	INSERT INTO ". TABLE_PREFIX ."$aggtable
		SELECT attachmentid, COUNT(*) AS views
		FROM " . TABLE_PREFIX . "attachmentviews
		GROUP BY attachmentid
");

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->unlock_tables();
}
/* Small race condition but better than lots of IO wait for a DELETE query */
$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "attachmentviews");

$vbulletin->db->query_write("
	UPDATE " . TABLE_PREFIX . "attachment AS attachment
	INNER JOIN (SELECT * FROM ". TABLE_PREFIX . "$aggtable
	ORDER BY attachmentid) AS aggregate USING (attachmentid)
	SET attachment.counter = attachment.counter + aggregate.views
");

$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . $aggtable);

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
