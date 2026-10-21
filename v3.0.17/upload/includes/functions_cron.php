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

// ###################### Start getnextrun #######################
// gets next run time today after $hour, $minute
// returns -1,-1 if not again today
function fetch_cron_next_run($crondata, $hour = -2, $minute = -2)
{
	if ($hour == -2)
	{
		$hour = intval(date('H', TIMENOW));
	}
	if ($minute == -2)
	{
		$minute = intval(date('i', TIMENOW));
	}

	if ($crondata['hour'] == -1 AND $crondata['minute'] == -1)
	{
		$newdata['hour'] = $hour;
		$newdata['minute'] = $minute + 1;
	}
	else if ($crondata['hour'] == -1 AND $crondata['minute'] != -1)
	{
		$newdata['hour'] = $hour;
		if ($crondata['minute'] <= $minute)  {
			$newdata['hour']++;
		}
		$newdata['minute'] = $crondata['minute'];
	}
	else if ($crondata['hour'] != -1 AND $crondata['minute'] == -1)
	{
		if ($crondata['hour'] < $hour)
		{ // too late for today!
			$newdata['hour'] = -1;
			$newdata['minute'] = -1;
		}
		else if ($crondata['hour'] == $hour)
		{ // this hour
			$newdata['hour'] = $crondata['hour'];
			$newdata['minute'] = $minute + 1;
		}
		else
		{ // some time in future, so launch at 0th minute
			$newdata['hour'] = $crondata['hour'];
			$newdata['minute'] = 0;
		}
	}
	else if ($crondata['hour'] != -1 AND $crondata['minute'] != -1)
	{
		if ($crondata['hour'] < $hour OR ($crondata['hour'] == $hour AND $crondata['minute'] <= $minute))
		{
			$newdata['hour'] = -1;
			$newdata['minute'] = -1;
		}
		else
		{
			// all good!
			$newdata['hour'] = $crondata['hour'];
			$newdata['minute'] = $crondata['minute'];
		}
	}

	return $newdata;
}

// ###################### Start updatecron #######################
// updates an entry in the cron table to determine the next run time
function build_cron_item($cronid, $crondata = '')
{
	global $DB_site;

	if (!is_array($crondata))
	{
		$crondata = $DB_site->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "cron
			WHERE cronid = " . intval($cronid)
		);
	}

	$minutenow = intval(date('i', TIMENOW));
	$hournow = intval(date('H', TIMENOW));
	$daynow = intval(date('d', TIMENOW));
	$monthnow = intval(date('m', TIMENOW));
	$yearnow = intval(date('Y', TIMENOW));
	$weekdaynow = intval(date('w', TIMENOW));

	// ok need to work out, date and time of 1st and 2nd next opportunities to run
	if ($crondata['weekday'] == -1)
	{ // any day of week:
		if ($crondata['day'] == -1)
		{ // any day of month:
			$firstday = $daynow;
			$secondday = $daynow + 1;
		}
		else
		{	// specific day of month:
			$firstday = $crondata['day'];
			$secondday = $crondata['day'] + date('t', TIMENOW); // number of days this month
		}
	}
	else
	{ // specific day of week:
		$firstday = $daynow + ($crondata['weekday'] - $weekdaynow);
		$secondday = $firstday + 7;
	}

	if ($firstday < $daynow)
	{
		$firstday = $secondday;
	}

	if ($firstday == $daynow)
	{ // next run is due today?
		$todaytime = fetch_cron_next_run($crondata); // see if possible to run again today
		if ($todaytime['hour'] == -1 AND $todaytime['minute'] == -1)
		{
			// can't run today
			$crondata['day'] = $secondday;

			$newtime = fetch_cron_next_run($crondata, 0, -1);
			$crondata['hour'] = $newtime['hour'];
			$crondata['minute'] = $newtime['minute'];
		}
		else
		{
			$crondata['day'] = $firstday;
			$crondata['hour'] = $todaytime['hour'];
			$crondata['minute'] = $todaytime['minute'];
		}
	}
	else
	{
		$crondata['day'] = $firstday;

		$newtime = fetch_cron_next_run($crondata, 0, -1); // work out first run time that day
		$crondata['hour'] = $newtime['hour'];
		$crondata['minute'] = $newtime['minute'];
	}

	$nextrun = mktime($crondata['hour'], $crondata['minute'], 0, $monthnow, $crondata['day'], $yearnow);

	// save it
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "cron
		SET nextrun = " . $nextrun . "
		WHERE cronid = " . intval($cronid) . " AND nextrun = " . $crondata['nextrun']
	);
	$not_run = ($DB_site->affected_rows() > 0);

	build_cron_next_run($nextrun);
	return iif($not_run, $nextrun, 0);
}

// ###################### Start updatenextrun #######################
function build_cron_next_run($nextrun = '')
{
	global $DB_site;

	// get next one to run
	if (!$nextcron = $DB_site->query_first("SELECT MIN(nextrun) AS nextrun FROM " . TABLE_PREFIX . "cron"))
	{
		$nextcron['nextrun'] = TIMENOW + 60 * 60;
	}

	// update DB details
	build_datastore('cron', $nextcron['nextrun']);

	return $nextrun;
}

// ###################### Start cronlog #######################
// description = action that was performed
// $nextitem is an array containing the information for this cronjob
function log_cron_action($description, $nextitem)
{
	global $DB_site;

	if (defined('ECHO_CRON_LOG'))
	{
		echo "<p>$description</p>";
	}

	if ($nextitem['loglevel'])
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "cronlog
				(cronid, dateline, description)
			VALUES
				($nextitem[cronid], " . TIMENOW . ", '" . addslashes($description) . "')
		");
	}
}

// ###################### Start docron #######################
function exec_cron()
{
	global $DB_site, $vboptions, $workingdir, $_USEROPTIONS, $usergroupcache, $subscriptioncache;

	if ($nextitem = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "cron WHERE nextrun <= " . TIMENOW . " ORDER BY nextrun"))
	{
		if ($nextrun = build_cron_item($nextitem['cronid'], $nextitem))
		{
			// Don't attempt chdir if getcwd() from cron.php fails .. see bug 24185
			if (!empty($workingdir))
			{
				chdir($workingdir); // workaround for php bug 14251
			}

			require_once($nextitem['filename']);
		}
	}
	else
	{
		build_cron_next_run();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12039 $
|| ####################################################################
\*======================================================================*/
?>