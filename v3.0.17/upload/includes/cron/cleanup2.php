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

// posthashes are only valid for 5 minutes
$DB_site->query("
	DELETE FROM " . TABLE_PREFIX . "posthash
	WHERE dateline < " . (TIMENOW - 300)
);

// expired registration images after 1 hour
$DB_site->query("
	DELETE FROM " . TABLE_PREFIX . "regimage
	WHERE dateline < " . (TIMENOW - 3600)
);

// expired cached posts
$DB_site->query("
	DELETE FROM " . TABLE_PREFIX . "post_parsed
	WHERE dateline < " . (TIMENOW - ($vboptions['cachemaxage'] * 60 * 60 * 24))
);

// Orphaned Attachments are removed after one hour
if ($vboptions['attachfile'])
{
	require_once('./includes/functions_file.php');
	$attachmentids = '';
	$attachments = $DB_site->query("
		SELECT attachmentid, userid
		FROM " . TABLE_PREFIX . "attachment
		WHERE postid = 0 AND
		dateline < " . (TIMENOW - 3600)
	);
	while ($attachment = $DB_site->fetch_array($attachments))
	{
		$attachpath = fetch_attachment_path($attachment['userid'], $attachment['attachmentid']);
		@unlink($attachpath);
		$attachpath = fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], true);
		@unlink($attachpath);
		$attachmentids .= ',' . $attachment['attachmentid'];
	}
	if ($attachmentids)
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "attachment WHERE attachmentid IN (0$attachmentids)");
	}
}
else
{
	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "attachment
		WHERE postid = 0 AND
		dateline < " . (TIMENOW - 3600)
	);
}

// Orphaned pmtext records are removed after one hour.
// When we delete PMs we only delete the pm record, leaving
// the pmtext record alone for this script to clean up
$pmtexts = $DB_site->query("
	SELECT pmtext.pmtextid
	FROM " . TABLE_PREFIX . "pmtext AS pmtext
	LEFT JOIN " . TABLE_PREFIX . "pm AS pm USING(pmtextid)
	WHERE pm.pmid IS NULL
");
if ($DB_site->num_rows($pmtexts))
{
	$pmtextids = '0';
	while ($pmtext = $DB_site->fetch_array($pmtexts))
	{
		$pmtextids .= ",$pmtext[pmtextid]";
	}
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "pmtext WHERE pmtextid IN($pmtextids)");
}
$DB_site->free_result($pmtexts);

log_cron_action('Hourly Cleanup #2 Completed', $nextitem);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9499 $
|| ####################################################################
\*======================================================================*/
?>