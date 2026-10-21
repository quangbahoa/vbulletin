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

// Defined constants used for calendars
$_CALENDAROPTIONS = array(
	'showbirthdays'     => 1,
	'showholidays'      => 2,
	'showweekends'      => 4,
	'allowhtml'         => 8,
	'allowbbcode'       => 16,
	'allowimgcode'      => 32,
	'allowsmilies'      => 64,
	'default'           => 128,		// 128 = monthly, 0 = weekly
);

// Defined constants for pre-defined holidays
$_CALENDARHOLIDAYS = array(
	'easter'			=> 1,
	'good_friday'		=> 2,
	'palm_sunday'		=> 4,
	'ash_wednesday'		=> 8,
	'pentecost'			=> 16,
	'carnival'			=> 32,
	'corpus_christi'	=> 64,
);

$months = array(
	1 => 'january',
	2 => 'february',
	3 => 'march',
	4 => 'april',
	5 => 'may',
	6 => 'june',
	7 => 'july',
	8 => 'august',
	9 => 'september',
	10 => 'october',
	11 => 'november',
	12 => 'december'
);

$days = array(
	1 => 'sunday',
	2 => 'monday',
	3 => 'tuesday',
	4 => 'wednesday',
	5 => 'thursday',
	6 => 'friday',
	7 => 'saturday'
);

$period = array(
	1 => 'date_first',
	'second',
	'third',
	'fourth',
	'date_last'
);

// ###################### Start setdatetime #######################
function fetch_event_date_time($info)
{
	global $timerange, $vboptions, $vbphrase, $months, $days, $day, $month, $year, $holiday, $eventdate;
	global $titlecolor, $date1, $date2, $time1, $time2, $recurcriteria, $allday, $show;

	require_once('./includes/functions_misc.php');

	$daterange = '';
	$recurcriteria = '';
	$show['recuroption'] = false;
	$titlecolor = 'alt1';

	$info['title'] = htmlspecialchars_uni($info['title']);
	if ($wordwrap != 0)
	{
		$info['title'] = fetch_word_wrapped_string($info['title']);
	}

	$info['event'] = iif(empty($info['event']), '&nbsp', parse_calendar_bbcode($info['event'], $info['allowsmilies']));

	if (!$info['recurring'] AND !$info['singleday'])
	{
		$daystamp = gmmktime(0, 0, 0, $month, $day, $year);
		$eventfirstday = gmmktime(0, 0, 0, gmdate('n', $info['dateline_from_user']), gmdate('j', $info['dateline_from_user']), gmdate('Y', $info['dateline_from_user']));
		$eventlastday = gmmktime(0, 0, 0, gmdate('n', $info['dateline_to_user']), gmdate('j', $info['dateline_to_user']), gmdate('Y', $info['dateline_to_user']));
		if ($info['dateline_from'] == $daystamp)
		{
			if ($eventfirstday == $daystamp)
			{
				if ($eventfirstday != $eventlastday)
				{
					if (vbdate('g:ia', $info['dateline_from_user'], false, false) == '12:00am')
					{
						$allday = true;
					}
					else
					{
						$time2 = vbgmdate($vboptions['timeformat'], gmmktime(0, 0, 0, $month, $day, $year));
					}
				}
			}
		}
		else if ($eventlastday == $daystamp)
		{
			$time1 = gmdate($vboptions['timeformat'], gmmktime(0, 0, 0, $month, $day, $year));
			$time1 = vbgmdate($vboptions['timeformat'], gmmktime(0, 0, 0, $month, $day, $year));
		}
		else // A day in the middle of a multi-day event so event covers 24 hours
		{
			$allday = true; // Used in conditional
		}
	}

	if ($info['holidayid'])
	{
		$eventdate = vbgmdate($vboptions['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
	}
	else if ($info['singleday'])
	{
		$eventdate = vbgmdate($vboptions['dateformat'], $info['dateline_from']);
	}
	else
	{
		$date1 = vbgmdate($vboptions['dateformat'], $info['dateline_from_user']);
		$date2 = vbgmdate($vboptions['dateformat'], $info['dateline_to_user']);
		$time1 = vbgmdate($vboptions['timeformat'], $info['dateline_from_user']);
		$time2 = vbgmdate($vboptions['timeformat'], $info['dateline_to_user']);
		if ($info['recurring'])
		{
			$recurcriteria = fetch_event_criteria($info);
			$show['recuroption'] = true;
		}
		else
		{
			$show['daterange'] = iif($date1 != $date2, true, false);
			$eventdate = vbgmdate($vboptions['dateformat'], $info['dateline_from_user']);
		}
	}

	return $info;
}

// ##################### Start cache_events_day #######################
function cache_events_day($month, $day, $year)
{
	global $eventcache, $bbuserinfo;

	$eventarray = array();

	if (!empty($eventcache['holiday1']))
	{
		if (!empty($eventcache['holiday1']["$month|$day"]))
		{
			foreach($eventcache['holiday1']["$month|$day"] AS $index => $event)
			{
				$eventarray[] = &$eventcache['holiday1']["$month|$day"]["$index"];
			}
		}
	}

	if (!empty($eventcache['holiday2']))
	{
		$dayofweek = gmdate('w', gmmktime(0, 0, 0, $month, $day, $year)) + 1;
		if (!empty($eventcache['holiday2']["$dayofweek|$month"]))
		{
			foreach($eventcache['holiday2']["$dayofweek|$month"] AS $index => $event)
			{
				if (cache_event_info($event, $month, $day, $year))
				{
					$eventarray[] = &$eventcache['holiday2']["$dayofweek|$month"]["$index"];
				}
			}
		}
	}

	if (!empty($eventcache['singleday']))
	{
		// Check for single day events occuring on this date
		$dateline_from = gmmktime(0, 0, 0, $month, $day, $year);

		if (!empty($eventcache['singleday']["$dateline_from"]))
		{
			foreach($eventcache['singleday']["$dateline_from"] AS $index => $event)
			{
				$eventarray[] = &$eventcache['singleday']["$dateline_from"]["$index"];
			}
		}
	}

	if (!empty($eventcache['ranged']))
	{
		// Check for ranged events ocurring on this date
		$todaystart = gmmktime(0, 0, 0, $month, $day, $year);

		if (!empty($eventcache['ranged']["$todaystart"]))
		{
			foreach($eventcache['ranged']["$todaystart"] AS $index => $event)
			{
				$eventarray[] = &$eventcache['ranged']["$todaystart"]["$index"];
			}
		}
	}

	if (!empty($eventcache['recurring']))
	{
		foreach ($eventcache['recurring'] AS $index => $event)
		{
			if (cache_event_info($event, $month, $day, $year))
			{
				$eventarray[] = &$eventcache['recurring']["$index"];
			}
		}
	}

	return $eventarray;
}

// ###################### Start getcalendarbits #######################
function construct_calendar_output($today, $usertoday, $calendarinfo, $fullcalendar = 0, $weekrange = '')
{
	global $birthdaycache, $eventcache, $bbuserinfo, $vbphrase, $stylevar, $show, $offset, $colspan, $days, $months;

	$calendarid = $calendarinfo['calendarid'];
	$month = $usertoday['month'];
	$year = $usertoday['year'];

	$calendardaybits = '';
	$calendarweekbits = '';

	if ($calendarinfo['showweekends'])
	{
		$weeklength = 7;
		$colspan = 8;
		$daywidth = '14%';
		$linkwidth = '2%';
	}
	else
	{
		$weeklength = 5;
		$colspan = 6;
		$daywidth = '19%';
		$linkwidth = '5%';
	}

	// set up the controls for this month
	$displaymonth['starttime'] = gmmktime(12, 0, 0, $month, 1, $year);
	$displaymonth['numdays'] = gmdate('t', $displaymonth['starttime']);
	$displaymonth['month'] = gmdate('n', $displaymonth['starttime']);
	$displaymonth['year'] = gmdate('Y', $displaymonth['starttime']);
	$todaylink = "$today[year]-$today[mon]-$today[mday]";

	// get an array of days starting with the user's chosen week start
	static $userweek;
	if (!is_array($userweek))
	{
		$userweek = array();
		$curday = $bbuserinfo['startofweek'];
		while (sizeof($userweek) < $weeklength)
		{
			if (!$calendarinfo['showweekends'] AND ($curday == 1 OR $curday == 7))
			{
				$curday++;
			}
			else
			{
				$userweek[] = $curday++;
			}
			if ($curday > 7)
			{
				$curday = 1;
			}
		}
	}

	// wind back the start day so we have the days preceeding the current month that will be displayed
	$startday = 1;
	while ($null++ < 31)
	{
		if ((gmdate('w', gmmktime(0, 0, 0, $displaymonth['month'], $startday, $displaymonth['year'])) + 1) == $userweek[0])
		{
			break;
		}
		else
		{
			$startday--;
		}
	}

	if ($fullcalendar)
	{
		$template = array(
			'day' => 'calendar_monthly_day',
			'day_other' => 'calendar_monthly_day_other',
			'week' => 'calendar_monthly_week',
			'header' => 'calendar_monthly_header'
		);
	}
	else
	{
		if ($startday == 1 AND $show['yearlyview'])
		{
			// wind back the start day by a week so we always end up with 6 rows for neatness
			$startday -= 7;
		}
		$template = array(
			'day' => 'calendar_smallmonth_day',
			'day_other' => 'calendar_smallmonth_day_other',
			'week' => 'calendar_smallmonth_week',
			'header' => 'calendar_smallmonth_header'
		);
	}

	// fetch special holiday events
	$eastercache = fetch_easter_array($usertoday['year']);

	if (!$fullcalendar)
	{
		// set up which days will be shown
		for ($i = 0; $i < 7; $i++)
		{
			$dayvarname = 'day' . ($i + 1);
			if ($userweek["$i"])
			{
				$$dayvarname = $vbphrase[ $days[ $userweek[$i] ] . '_short'];
				$show["$dayvarname"] = true;
			}
			else
			{
				$show["$dayvarname"] = false;
			}
		}
	}

	// now start creating the week rows
	$rows = 0;
	while (++$rows <= 6)
	{
		if (!$show['yearlyview'] AND $startday > $displaymonth['numdays'])
		{
			break;
		}

		// run through the user's week days
		$calendardaybits = '';
		foreach ($userweek AS $daycount => $curday)
		{
			$datestamp = gmmktime(0, 0, 0, $displaymonth['month'], $startday++, $displaymonth['year']);
			$dayname = $vbphrase["$days[$curday]_abbr"];
			$year = gmdate('Y', $datestamp);
			$month = gmdate('n', $datestamp);
			$day = gmdate('j', $datestamp);
			$daylink = "$year-$month-$day";

			if (!$daycount)
			{
				$firstweek = gmmktime(0, 0, 0, $month, $day, $year);
			}

			// is this day today?
			$show['highlighttoday'] = iif($daylink == $todaylink, true, false);

			// is this day in our desired month?
			if ($month != $displaymonth['month'])
			{
				$daytemplatename = &$template['day_other'];
			}
			else
			{
				$daytemplatename = &$template['day'];

				// is this day in our desired week?
				if (!$fullcalendar)
				{
					if (is_array($weekrange) AND $datestamp >= $weekrange['start'] AND $datestamp <= $weekrange['end'])
					{
						$show['highlightweek'] = true;
					}
					else
					{
						$show['highlightweek'] = false;
					}
				}

				$show['birthdaylink'] = false;
				$show['daylink'] = false;
				$show['holiday'] = false;
				$show['eventlink'] = false;
				$userbdays = '';
				$userevents = '';
				$eventdesc = '';
				$eventtotal = 0;

				// get this day's birthdays
				if ($calendarinfo['showbirthdays'] AND $fullcalendar AND is_array($birthdaycache["$month"]["$day"]))
				{
					unset($userday);
					unset($age);
					unset($birthdaydesc);
					$bdaycount = 0;
					foreach ($birthdaycache["$month"]["$day"] AS $index => $value)
					{
						$userday = explode('-', $value['birthday']);
						$bdaycount++;
						$username = $value['username'];
						$userid = $value['userid'];
						if ($year > $userday[2] AND $userday[2] != '0000')
						{
							$age = '(' . ($year - $userday[2]) . ')';
						}
						else
						{
							unset($age);
						}
						$birthdaydesc .= iif($birthdaydesc, ', ') . $value['username'];
						eval ('$userbdays .= "' . fetch_template('calendar_monthly_birthday') . '";');
					}
					if ($bdaycount > $calendarinfo['birthdaycount'])
					{
						$show['birthdaylink'] = true;
					}
				}

				// get this day's holidays
				if (!empty($eastercache["$month-$day-$year"]))
				{
					$eventtotal++;
					$event['title'] = &$eastercache["$month-$day-$year"]['title'];
					$event['preview'] = &$eastercache["$month-$day-$year"]['event'];
					$show['daylink'] = true;

					$eventdesc .= iif($eventdesc, "\r\n") .	$event['title'];
					if ($fullcalendar)
					{
						$show['holiday'] = true;
						eval ('$userevents .= "' . fetch_template('calendar_monthly_event') . '";');
					}
				}

				// get this day's events
				if (is_array($eventcache))
				{
					$eventarray = cache_events_day($month, $day, $year);

					foreach($eventarray AS $index => $event)
					{
						$eventtotal++;
						$show['daylink'] = true;
						$eventdesc .= iif($eventdesc, "\r\n") .	htmlspecialchars_uni($event['title']);
						if ($fullcalendar)
						{
							$eventid = $event['eventid'];
							$show['holiday'] = iif($event['holidayid'], true, false);
							$show['subscribed'] = iif($event['subscribed'], true, false);
							if ($calendarinfo['cutoff'] AND strlen($event['title']) > $calendarinfo['cutoff'] AND !$event['holidayid'])
							{
								$event['title'] = substr($event['title'], 0, $calendarinfo['cutoff']) . ' (...)';
							}
							$event['title'] =  htmlspecialchars_uni($event['title']);
							eval ('$userevents .= "' . fetch_template('calendar_monthly_event') . '";');
						}
					}
					if ($eventtotal > $calendarinfo['eventcount'])
					{
						$show['eventlink'] = true;
					}
				}
			}

			eval('$calendardaybits .= "' . fetch_template($daytemplatename) . '";');

			// if no weekends, bump on the counter to compensate
			if (!$calendarinfo['showweekends'] AND $daycount >= 4)
			{
				$startday += 2;
			}
		}

		eval('$calendarrowbits .= "' . fetch_template($template['week']) . '";');
	}

	$year = $displaymonth['year'];
	$month = $displaymonth['month'];
	$monthname = $vbphrase["$months[$month]"];

	eval('$calendarrowbits = "' . fetch_template($template['header']) . '";');

	return $calendarrowbits;
}

// ###################### Start iscalmoderator #######################
function can_moderate_calendar($calendarid = 0, $do = '', $userid = -1)
{
	global $bbuserinfo, $DB_site, $_BITFIELD, $cmodcache;

	cache_calendar_moderators();

	if ($userid == -1)
	{
		$userid = $bbuserinfo['userid'];
	}

	if ($bbuserinfo['permissions']['adminpermissions'] & ISMODERATOR)
	{
		DEVDEBUG('  USER IS A SUPER MODERATOR');
		return true;
	}

	// if we got this far, user is not a super moderator

	if ($calendarid == 0)
	{ // just check to see if the user is a moderator of any calendar

		DEVDEBUG('looping through cmodcache to find userid $userid');
		$ismod = 0;
		foreach($cmodcache AS $calendarmods)
		{
			if (!empty($calendarmods["$userid"]))
			{
				$ismod = 1;
			}
		}
		return $ismod;

	}
	else
	{ // check to see if user is a moderator of specific calendar

		$getmodperms = intval($cmodcache["$calendarid"]["$userid"]['permissions']);

		if (empty($do) AND $getmodperms)
		{
			return true;
		}
		else
		{ // check if user is a mod and has permissions to '$do'
			if ($getmodperms & $_BITFIELD['calmoderatorpermissions']["$do"])
			{
				return true;
			}
			else
			{
				return false;
			}  // if has perms for this action
		}// if is mod for calendar and no action set
	} // if calendarid=0
}

// ###################### Start getcmodcache #######################
function cache_calendar_moderators()
{
	global $DB_site, $cmodcache, $calmod;

	if (!is_array($cmodcache))
	{
		$cmodcache = array();
		$mod = array();
		$calmoderators = $DB_site->query("
			SELECT calendarmoderator.*, user.username
			FROM " . TABLE_PREFIX . "calendarmoderator AS calendarmoderator
			INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		");
		while ($moderator = $DB_site->fetch_array($calmoderators))
		{
			$cmodcache["$moderator[calendarid]"]["$moderator[userid]"] = $moderator;
			$calmod["$moderator[userid]"] = 1;
		}
		$DB_site->free_result($calmoderators);
	} // else we have already called cache_calendar_moderators()
}

// ###################### Start cacheevents #######################
function cache_events($range)
{

	global $DB_site, $bbuserinfo, $calendarinfo, $vbphrase, $serveroffset, $vboptions, $usergroupcache;

	$numdays = date('j', mktime(0, 0, 0, $range['nextmonth'] + 1, 1, $range['nextyear']) - 1);

	$beginday = gmmktime(0, 0, 0, $range['frommonth'], 1, $range['fromyear']) + (-12 * 3600);
	$endday = gmmktime(24, 0, 0, $range['nextmonth'], $numdays, $range['nextyear']) + (12 * 3600);

	$event = array();
	$eventids = array();

	if ($calendarinfo['showholidays'])
	{
		// Holidays show across all calendars that a user has access to.
		$holidays = $DB_site->query("
			SELECT *
			FROM " . TABLE_PREFIX . "holiday
		");

		if ($DB_site->num_rows($holidays))
		{
			while ($ev = $DB_site->fetch_array($holidays))
			{
				$ev['visible'] = 1;
				$ev['title'] = &$vbphrase['holiday_title_' . $ev['varname']];
				$ev['event'] = &$vbphrase['holiday_event_' . $ev['varname']];
				$ev['preview'] = strip_quotes($ev['event']);
				$ev['preview'] = htmlspecialchars_uni(strip_bbcode(fetch_trimmed_title($ev['preview'], 300), false, true));

				if ($ev['recurring'] == 6)
				{
					$event['holiday1']["$ev[recuroption]"][] = $ev;
				}
				else
				{
					$ev['dateline_from'] = $beginday;
					$ev['dateline_to'] = $endday;
					$ev['dateline_from_user'] = $ev['dateline_from'] + $bbuserinfo['timezoneoffset'] * 3600;
					$ev['dateline_to_user'] = $ev['dateline_to'] + $bbuserinfo['timezoneoffset'] * 3600;
					$recuroption = substr($ev['recuroption'], 2);
					$event['holiday2']["$recuroption"][] = $ev;
				}
			}
		}
	}

	$events = $DB_site->query("
		SELECT event.*,
		user.username, IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid,
		IF(dateline_to = 0, 1, 0) AS singleday
		" . iif($bbuserinfo['userid'], ", subscribeevent.eventid AS subscribed") . "
		FROM " . TABLE_PREFIX . "event AS event
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = event.userid)
		" . iif($bbuserinfo['userid'], "LEFT JOIN " . TABLE_PREFIX . "subscribeevent AS subscribeevent ON (subscribeevent.eventid = event.eventid AND subscribeevent.userid = $bbuserinfo[userid])") . "
		WHERE calendarid = $calendarinfo[calendarid] AND
			((dateline_to >= $beginday AND dateline_from < $endday) OR (dateline_to = 0 AND dateline_from >= $beginday AND dateline_from <= $endday )) AND
			visible = 1
	");
	// Cache Events

	if ($DB_site->num_rows($events))
	{
		while ($ev = $DB_site->fetch_array($events))
		{
			if ($ev['userid'] != $bbuserinfo['userid'] AND !($bbuserinfo['calendarpermissions']["$calendarinfo[calendarid]"] & CANVIEWOTHERSEVENT))
			{
				continue;
			}

			$ev['preview'] = strip_quotes($ev['event']);
			$ev['preview'] = htmlspecialchars_uni(strip_bbcode(fetch_trimmed_title($ev['preview'], 300), false, true));

			$ev['dateline_from_user'] = $ev['dateline_from'] + $bbuserinfo['timezoneoffset'] * 3600;
			$ev['dateline_to_user'] = $ev['dateline_to'] + $bbuserinfo['timezoneoffset'] * 3600;
			$ev['musername'] = fetch_musername($ev);

			if (!$ev['recurring'])
			{
				if ($ev['singleday'])
				{
					$event['singleday']["$ev[dateline_from]"][] = $ev;
				}
				else
				{
					$found = false;
					$date = explode('-', gmdate('n-j-Y', $ev['dateline_from_user']));
					$beginday = gmmktime(0, 0, 0, $date[0], $date[1], $date[2]);

					while ($beginday <= $ev['dateline_to_user'])
					{
						if (!$found)
						{
							$event['ranged']["$beginday"][] = $ev;
							$count = count($event['ranged']["$beginday"]) - 1;
							$tempevent = &$event['ranged']["$beginday"]["$count"];
							$found = true;
						}
						else
						{
							// if event ends at the start of the day, don't display it for that day
							if ($ev['dateline_to_user'] != $beginday)
							{
								$event['ranged']["$beginday"][] = &$tempevent;
							}
						}
						$beginday += 86400;
					}
				}
			}
			else
			{
				$event['recurring'][] = $ev;
			}
		}
	}

	return $event;
}

// ###################### Start cachebirthdays #######################
function cache_birthdays($weekly = 0)
{

	global $doublemonth, $DB_site, $calendarinfo, $doublemonth1, $doublemonth2, $usergroupcache;

	$birthday = array();

	if ($calendarinfo['showbirthdays'])
	{
		// Load the birthdays for this month:
		$ids = '0';
		foreach($usergroupcache AS $usergroupid => $usergroup)
		{
			if ($usergroup['genericoptions'] & SHOWBIRTHDAY)
			{
				$ids .= ",$usergroupid";
			}
		}

		if (!$weekly)
		{ // cache birthdays for a single month
			$birthdays = $DB_site->query("
				SELECT birthday,username,userid
				FROM " . TABLE_PREFIX . "user
				WHERE birthday LIKE '$doublemonth-%' AND
					usergroupid IN ($ids)
			");
		}
		else
		{ // cache for two months!
			$birthdays = $DB_site->query("
				SELECT birthday,username,userid
				FROM " . TABLE_PREFIX . "user
				WHERE (birthday LIKE '$doublemonth1-%' OR
					birthday LIKE '$doublemonth2-%') AND
					usergroupid IN ($ids)
			");
		}

		// Cache Birthdays
		if ($DB_site->num_rows($birthdays))
		{
			$birthday = array();
			while ($bday = $DB_site->fetch_array($birthdays))
			{
				$userday = explode('-', $bday['birthday']);
				$month = intval($userday[0]);
				$day = intval($userday[1]);
				$birthday["$month"]["$day"][] = $bday;
			}
		}
	}

	return $birthday;

}

// ###################### Start makecalendarjump #######################
function construct_calendar_jump($currentcalendarid)
{

	global $calendarcache, $DB_site, $bbuserinfo, $gobutton, $month, $year, $stylevar, $today, $vbphrase, $calendarinfo, $monthselected;

	foreach ($calendarcache AS $calendarid => $title)
	{

		$calendarperms = $bbuserinfo['calendarpermissions']["$calendarid"];
		if (!($calendarperms & CANVIEWCALENDAR))
		{
			continue;
		}
		else
		{
			$optionvalue = $calendarid;
			$optiontitle = $title;

			if ($currentcalendarid == $optionvalue)
			{
				$optionselected = HTML_SELECTED;
				$optionclass = 'fjsel';
				$selectedone = 1;
			}
			else
			{
				$optionselected = '';
				$optionclass = '';
			}
			eval('$jumpcalendarbits .= "' . fetch_template('option') . '";');
		}
	}

	if ($selectedone != 1)
	{
		$defaultselected = HTML_SELECTED;
	}

	for ($gyear = $calendarinfo['startyear']; $gyear <= $calendarinfo['endyear']; $gyear++)
	{
		$selected = iif($year == $gyear, HTML_SELECTED);
		$yearbits .= "\t\t<option value=\"$gyear\" $selected>$gyear</option>\n";
	}

	eval('$calendarjump = "' . fetch_template('calendarjump') . '";');

	return $calendarjump;

}

// ###################### Start getfirstday #######################
function fetch_first_day($daynum = 1, $fullcalendar, $showweekends)
{
	global $vbphrase, $bbuserinfo;

	if ($daynum > 7)
	{
		$daynum = $daynum - 7;
	}

	if (!$fullcalendar)
	{
		$header = '_short';
	}

	switch ($daynum)
	{
		case 1:
			if (!$showweekends)
			{
				return -1;
			}
			else
			{
				return $vbphrase['sunday' . $header];
			}
		case 2:
			return $vbphrase['monday' . $header];
		case 3:
			return $vbphrase['tuesday' . $header];
		case 4:
			return $vbphrase['wednesday' . $header];
		case 5:
			return $vbphrase['thursday' . $header];
		case 6:
			return $vbphrase['friday' . $header];
		case 7:
			if (!$showweekends)
			{
				return -1;
			}
			else
			{
				return $vbphrase['saturday' . $header];
			}
	}
}

// ###################### Start subtractdays #######################
function fetch_subtract_days($offset = 0, $dateVal)
{
	// Takes number of days to subtract and the date to subtract from
	// Returns an array containing month/day/year

	return explode('-', gmdate('n-j-Y', $dateVal - 86400 * $offset));
}

// ###################### Start adddays #######################
function fetch_add_days($offset = 0, $dateVal)
{
	// Takes number of days to add and the date to add from
	// Returns an array containing month/day/year

	return explode('-', gmdate('n-j-Y', $dateVal + 86400 * $offset));
}

// ###################### Start easter #######################
function fetch_easter_array($year)
{
	global $vbphrase, $calendarinfo, $_CALENDARHOLIDAYS;

	global $eastercache;

	if ($eastercache)
	{
		return $eastercache;
	}

	// Calculates day that easter and it's accompanying holidays fall on.
	$goldennum = ($year % 19) + 1;
	$century = intval($year / 100) + 1;
	$leapcent = intval(3 * $century / 4) - 12;
	$lunarcorr = intval((8 * $century + 5) / 25) - 5;
	$sundaycorr = intval(5 * $year / 4) - $leapcent - 10;
	$epact = abs(11 * $goldennum + 20 + $lunarcorr - $leapcent) % 30;

	if (($epact == 25 AND $goldennum > 11) OR ($epact == 24))
	{
		 $epact++;
	}

	$fullmoon = 44 - $epact;

	if ($fullmoon < 21)
	{
			$fullmoon = $fullmoon + 30;
	}

	$day = $fullmoon + 7 - (($sundaycorr + $fullmoon) % 7);

	if ($day > 31)
	{
		$day = $day - 31;
		$month = 4;
	}
	else
	{
		$month = 3;
	}

	$easterStamp = gmmktime(12, 0, 0, $month, $day, $year);

	$eastercache = array();
	foreach($_CALENDARHOLIDAYS AS $holiday => $value)
	{
		if ($calendarinfo["$holiday"])
		{
			unset($count);
			switch($holiday)
			{
				case 'easter':
					$count = 0;
					break;
				case 'good_friday':
					$count = -2;
					break;
				case 'palm_sunday':
					$count = -7;
					break;
				case 'ash_wednesday':
					$count = -46;
					break;
				case 'pentecost':
					$count = 49;
					break;
				case 'carnival':
					$count = -47;
					break;
				case 'corpus_christi':
					$count = 60;
					break;
			}

			if (isset($count))
			{
				$date = implode('-', fetch_add_days($count, $easterStamp));
				$desc = $holiday . '_desc';
				$eastercache["$date"] = array(
					'title' => &$vbphrase["$holiday"],
					'event' => &$vbphrase["$desc"]
				);
			}
		}
	}

	return $eastercache;
}

// ###################### Start calcodeparse #######################
function parse_calendar_bbcode($bbcode, $smilies = 1)
{
	global $calendarinfo;

	require_once('./includes/functions_bbcodeparse.php');
	$bbcode = parse_bbcode2($bbcode, $calendarinfo['allowhtml'], $calendarinfo['allowimgcode'], iif($calendarinfo['allowsmilies'] AND $smilies, 1, 0), $calendarinfo['allowbbcode']);

	return $bbcode;
}

// ###################### Start geteventcriteria #######################
function fetch_event_criteria($option)
{
	global $vbphrase, $months, $days, $period;

	$options = explode('|', $option['recuroption']);

	if ($option['recurring'] == 1)
	{ // Recurring Daily Event
		$desc = construct_phrase($vbphrase['this_event_occurs_every_x_days'], $options[0]);
	}
	else if ($option['recurring'] == 2)
	{ // Every Weekday
		$desc = $vbphrase['this_event_occurs_every_weekday'];
	}
	else if ($option['recurring'] == 3)
	{ // Weekly Recurring Event
		if ($options[1] & 1)
		{ // Sunday
			$seldays = $vbphrase['sunday'];
		}
		if ($options[1] & 2)
		{ // Monday
			if ($seldays)
			{
				$seldays .= ", $vbphrase[monday]";
			}
			else
			{
				$seldays = $vbphrase['monday'];
			}
		}
		if ($options[1] & 4)
		{ // Tuesday
			if ($seldays)
			{
				$seldays .= ", $vbphrase[tuesday]";
			}
			else
			{
				$seldays = $vbphrase['tuesday'];
			}
		}
		if ($options[1] & 8)
		{ // Wednesday
			if ($seldays)
			{
				$seldays .= ", $vbphrase[wednesday]";
			}
			else
			{
				$seldays = $vbphrase['wednesday'];
			}
		}
		if ($options[1] & 16)
		{ // Thursday
			if ($seldays)
			{
				$seldays .= ", $vbphrase[thursday]";
			}
			else
			{
				$seldays = $vbphrase['thursday'];
			}
		}
		if ($options[1] & 32)
		{ // Friday
			if ($seldays)
			{
				$seldays .= ", $vbphrase[friday]";
			}
			else
			{
				$seldays = $vbphrase['friday'];
			}
		}
		if ($options[1] & 64)
		{ // Saturday
			if ($seldays)
			{
				$seldays .= ", $vbphrase[saturday]";
			}
			else
			{
				$seldays = $vbphrase[saturday];
			}
		}
		$desc = construct_phrase($vbphrase['this_event_occurs_every_x_weeks_on_y'], $options[0], $seldays);
	}
	else if ($option['recurring'] == 4)
	{ // Monthly Event
		$desc = construct_phrase($vbphrase['this_event_occurs_on_day_x_of_every_y_months'], $options[0], $options[1]);
	}
	else if ($option['recurring'] == 5)
	{ // Monthly Event
		$desc = construct_phrase($vbphrase['this_event_occurs_on_the_x_y_of_every_z_months'], $vbphrase["{$period[$options[0]]}"], $vbphrase["{$days[$options[1]]}"], $options[2]);
	}
	else if ($option['recurring'] == 6)
	{ // Yearly Event
		$desc = construct_phrase($vbphrase['this_event_occurs_every_x_y'], $vbphrase["{$months[$options[0]]}"], $options[1]);
	}
	else if ($option['recurring'] == 7)
	{ // Yearly Event
		$desc = construct_phrase($vbphrase['this_event_occurs_on_every_x_y_of_z'], $vbphrase["{$period[$options[0]]}"], $vbphrase["{$days[$options[1]]}"], $vbphrase["{$months[$options[2]]}"]);
	}
	return $desc;
}

// ###################### Start getevent #######################
function cache_event_info(&$event, $month, $day, $year, $adjust = 1)
{

	static $foundday, $foundevent, $foundholiday, $e;

	global $bbuserinfo, $vboptions;

	$eventid = $event['eventid'];

	if (($event['holidayid'] AND $foundholiday["$month-$day-$year"]["$event[holidayid]"]) OR ($eventid AND $foundevent["$month-$day-$year"]["$eventid"]))
	{
		return true;
	}

	if (empty($e["$eventid"]) AND !$event['singleday'])
	{
		$e["$eventid"]['startday'] = gmmktime(0, 0, 0, gmdate('n', $event['dateline_from_user']), gmdate('j', $event['dateline_from_user']), gmdate('Y', $event['dateline_from_user']));
		$e["$eventid"]['startmonth'] = gmmktime(0, 0, 0, gmdate('n', $event['dateline_from_user']), 1, gmdate('Y', $event['dateline_from_user']));
		$e["$eventid"]['endday'] = gmmktime(0, 0, 0, gmdate('n', $event['dateline_to_user']), gmdate('j', $event['dateline_to_user']), gmdate('Y', $event['dateline_to_user']));
	}

	$todaystart = gmmktime(0, 0, 0, $month, $day, $year);
	$todaymid = gmmktime(12, 0, 0, $month, $day, $year);
	$todayend = gmmktime(23, 59, 59, $month, $day, $year);
	$thismonth = gmmktime(0, 0, 0, $month, 1, $year);

	if ($event['singleday'])
	{
		if ("$month-$day-$year" == gmdate('n-j-Y', $event['dateline_from']))
		{
			return true;
		}
	}

#	if (($event['dateline_to'] + $event['utc'] * 3600) < $todaystart OR ($event['dateline_from'] + $event['utc'] * 3600) > $todayend)
#	{
#		return false;
#	}

	if ($event['dateline_to_user'] < $todaystart OR $event['dateline_from_user'] > $todayend)
	{
		return false;
	}

	if ($event['recurring'])
	{ // this is a recurring event
		if ($event['recurring'] == 1)
		{
			if (($todaystart - $e["$eventid"]['startday']) % (86400 * $event['recuroption']))
			{
				return false;
			}
			else
			{
				return true;
			}
		}
		else if ($event['recurring'] == 2)
		{
			if (gmdate('w', $event['dateline_from_user']) != gmdate('w', $event['dateline_from'] + ($event['utc'] * 3600)) AND $adjust)
			{
				if ($event['dateline_from_user'] > $event['dateline_from'] + ($event['utc'] * 3600))
				{
					$todaymid -= 86400;
				}
				else
				{
					$todaymid += 86400;
				}
			}

			if (gmdate('w', $todaymid) < 1 OR gmdate('w', $todaymid) > 5)
			{
				return false;
			}
		}
		else if ($event['recurring'] == 3)
		{
			$monthbit = explode('|', $event['recuroption']);
			$weekrep = $monthbit[0];

			$daysfromstart = ($todaystart - $e["$eventid"]['startday']) / 86400;
			$week = intval($daysfromstart / 7);
			if ($week % $weekrep)
			{
				return false;
			}

			if ($monthbit[1] & 1)
			{
				$haveday[0] = 1;
			}
			else
			{
				unset($haveday[1]);
			}
			if ($monthbit[1] & 2)
			{
				$haveday[1] = 1;
			}
			else
			{
				unset($haveday[2]);
			}
			if ($monthbit[1] & 4)
			{
				$haveday[2] = 1;
			}
			else
			{
				unset($haveday[3]);
			}
			if ($monthbit[1] & 8)
			{
				$haveday[3] = 1;
			}
			else
			{
				unset($haveday[4]);
			}
			if ($monthbit[1] & 16)
			{
				$haveday[4] = 1;
			}
			else
			{
				unset($haveday[5]);
			}
			if ($monthbit[1] & 32)
			{
				$haveday[5] = 1;
			}
			else
			{
				unset($haveday[6]);
			}
			if ($monthbit[1] & 64)
			{
				$haveday[6] = 1;
			}
			else
			{
				unset($haveday[7]);
			}

			if (gmdate('w', $event['dateline_from_user']) != gmdate('w', $event['dateline_from'] + ($event['utc'] * 3600)) AND $adjust)
			{
				if ($event['dateline_from_user'] > $event['dateline_from'] + ($event['utc'] * 3600))
				{
					$todaymid -= 86400;
				}
				else
				{
					$todaymid += 86400;
				}
			}

			$dayofweek = gmdate('w', $todaymid);

			if (!$haveday["$dayofweek"])
			{
				return false;
			}
		}
		else if ($event['recurring'] == 4)
		{
			$monthbit = explode('|', $event['recuroption']);
			$monthrep = $monthbit[1];

			if (gmdate('w', $event['dateline_from_user']) != gmdate('w', $event['dateline_from'] + ($event['utc'] * 3600)) AND $adjust)
			{
				if ($event['dateline_from_user'] > $event['dateline_from'] + ($event['utc'] * 3600))
				{
					$monthbit[0]++;
					if ($day == 1)
					{
						$todaymid -= 86400;
					}
					if ($monthbit[0] > gmdate('t', $todaymid))
					{
						$monthbit[0] = 1;
					}
				}
				else
				{
					$monthbit[0]--;
					if ($day == gmdate('t', $todaymid))
					{
						$todaymid += 86400;
					}
					if ($monthbit[0] == 0)
					{
						$monthbit[0] = gmdate('t', $todaymid);
					}
				}
			}

			if ($day != $monthbit[0])
			{
				return false;
			}

			if (empty($e["$eventid"]['currentmonth']))
			{
				if ($e["$eventid"]['startday'] > gmmktime(0, 0, 0, gmdate('n', $event['dateline_from_user']), $monthbit[0], gmdate('Y', $event['dateline_from_user'])))
				{
					$e["$eventid"]['currentmonth'] =  gmmktime(0, 0, 0, gmdate('n', $event['dateline_from_user']) + 1, 1, gmdate('Y', $event['dateline_from_user']));
				}
				else
				{
					$e["$eventid"]['currentmonth'] = $e["$eventid"]['startmonth'];
				}
			}

			while ($e["$eventid"]['currentmonth'] != $thismonth)
			{
				$e["$eventid"]['currentmonth'] = gmmktime(0, 0, 0, gmdate('n', $e["$eventid"]['currentmonth']) + 1, 1, gmdate('Y', $e["$eventid"]['currentmonth']));
				$e["$eventid"]['monthcount']++;
			}

			if ($e["$eventid"]['monthcount'] % $monthrep)
			{
				return false;
			}
		}
		else if ($event['recurring'] == 5)
		{
			if (gmdate('w', $event['dateline_from_user']) != gmdate('w', $event['dateline_from'] + ($event['utc'] * 3600)) AND $adjust)
			{
				if ($event['dateline_from_user'] > $event['dateline_from'] + ($event['utc'] * 3600))
				{
					$todaystart -= 86400;
				}
				else
				{
					$todaystart += 86400;
				}
			}
			$monthbit = explode('|', $event['recuroption']);
			$monthrep = $monthbit[2];
			$monthday = $monthbit[1];
			$monthweek = $monthbit[0];

			$fromdate = $e["$eventid"]['startday'];
			$todate = $e["$eventid"]['endday'];
			if (!is_array($foundday["$eventid"]))
			{
				while ($fromdate <= $todate)
				{
					$stopday = false;
					$tempday = gmdate('j', $fromdate);
					$whatday = gmdate('w', $fromdate) + 1;
					if ($monthday != $whatday)
					{
						$stopday = true;
					}
					else if ($tempday > ($monthweek * 7) OR $tempday < ($monthweek * 7 - 6))
					{
						$DaysInMonth = gmdate('t', $fromdate);
						if ($monthweek != 5 OR $tempday + 7 <= $DaysInMonth)
						{
							$stopday = true;
						}
					}
					if ($stopday)
					{	// Add one day
						$fromdate += 86400;
					}
					else
					{
						// We found a day this occurs on.
						$foundday["$eventid"]["$fromdate"] = 1;
						// Move to the next month depending on repetition
						$temp = gmdate('n-j-Y', $fromdate);
						$temp = explode('-', $temp);
						$fromdate = gmmktime(0, 0, 0, $temp[0] + $monthrep, 1, $temp[2]);
					}
				}
			}
			if (!$foundday["$eventid"]["$todaystart"])
			{
				return false;
			}
		}
		else if ($event['recurring'] == 6)
		{
			if (gmdate('w', $event['dateline_from_user']) != gmdate('w', $event['dateline_from'] + ($event['utc'] * 3600)) AND $adjust AND !$event['holidayid'])
			{
				if ($event['dateline_from_user'] > $event['dateline_from'] + ($event['utc'] * 3600))
				{
					$todaystart -= 86400;
				}
				else
				{
					$todaystart += 86400;
				}
			}
			$monthbit = explode('|', $event['recuroption']);
			if ($todaystart != gmmktime(0, 0, 0, $monthbit[0], $monthbit[1], $year))
			{
				return false;
			}
		}
		else if ($event['recurring'] == 7)
		{
			$monthbit = explode('|', $event['recuroption']);

			$tempmonth = $month;
			$tempday = $day;

			if (gmdate('w', $event['dateline_from_user']) != gmdate('w', $event['dateline_from'] + ($event['utc'] * 3600)) AND $adjust AND !$event['holidayid'])
			{
				if ($event['dateline_from_user'] < $event['dateline_from'] + ($event['utc'] * 3600))
				{
					$day++;
					if ($day > gmdate('t', $todaymid))
					{
						$day = 1;
						$month++;
						if ($month == 13)
						{
							$month = 1;
						}
					}
					$todaymid += 86400;
				}
				else
				{
					$todaymid -= 86400;
					$day--;
					if ($day == 0)
					{
						$day = gmdate('t', $todaymid);
						$month--;
						if ($month == 0)
						{
							$month = 12;
						}
					}
				}
			}

			$dayofweek = gmdate('w', $todaymid) + 1;

			if ($month != $monthbit[2] OR $dayofweek != $monthbit[1])
			{
				return false;
			}
			else if ($day > ($monthbit[0] * 7) OR $day < ($monthbit[0] * 7 - 6)			)
			{
				// Now check to see if we aren't looking for the 'last' day of a month
				// sometimes that is the 5th day and sometimes the 4th
				$DaysInMonth = gmdate('t', $todaymid);
				if ($monthbit[0] != 5 OR $day + 7 <= $DaysInMonth)
				{
					return false;
				}
			}
			$day = $tempday;
			$month = $tempmonth;
		}

		if ($event['holidayid'])
		{
			$foundholiday["$month-$day-$year"]["$event[holidayid]"] = 1;
		}
		else
		{
			$foundevent["$month-$day-$year"]["$eventid"] = 1;
		}
		return true;

	}
	else  //($todaystamp >= $event['from_date'] AND $todaystamp <= $event['to_date'])
	{
		$foundevent["$month-$day-$year"]["$eventid"] = 1;

		return true;
	}

	return false;
}

// ###################### Start fetch time options #######################
function fetch_time_options($giAH, $use24hour = false)
{
	global $vboptions, $stylevar, $vbphrase;
	static $timearray;

	if (!is_array($timearray))
	{
		$timearray = array();

		if ($use24hour)
		{
			for ($hour = 0; $hour < 24; $hour++)
			{
				for ($mins = 0; $mins <= 30; $mins += 30)
				{
					$hh = str_pad($hour, 2, 0, STR_PAD_LEFT);
					$mm = str_pad($mins, 2, 0, STR_PAD_LEFT);
					$timearray["{$hour}_{$mm}"] = "$hh:$mm";
				}
			}
			$timearray['0_00'] = $vbphrase['midnight'];
			$timearray['12_00'] = $vbphrase['midday'];
		}
		else
		{
			$ampm_array = array(
				'AM' => 'am',
				'PM' => 'pm'
			);
			$hour_array = array(
				12, 1, 2, 3,
				4, 5, 6, 7,
				8, 9, 10, 11
			);
			foreach ($ampm_array AS $AMPM => $ampm)
			{
				foreach ($hour_array AS $hour)
				{
					for ($mins = 0; $mins <= 30; $mins += 30)
					{
						$hh = str_pad($hour, 2, 0, STR_PAD_LEFT);
						$mm = str_pad($mins, 2, 0, STR_PAD_LEFT);
						$timearray["{$hour}_{$mm}_{$AMPM}"] = "$hh:$mm $ampm";
					}
				}
			}
			$timearray['12_00_AM'] = $vbphrase['midnight'];
			$timearray['12_00_PM'] = $vbphrase['midday'];
		}
	}

	if (true OR is_array($giAH))
	{
		switch($giAH[1])
		{
			case '15': $giAH[1] = '00'; break;
			case '45': $giAH[1] = '30'; break;
		}
		if ($use24hour)
		{
			$selectedindex = intval($giAH[3]) . '_' . $giAH[1];
		}
		else
		{
			$selectedindex = intval($giAH[0]) . '_' . $giAH[1] . '_' . $giAH[2];
		}
	}
	else
	{
		$selectedindex = false;
	}

	$output = '';
	foreach ($timearray AS $optionvalue => $optiontitle)
	{
		$optionselected = iif($optionvalue == $selectedindex, HTML_SELECTED, '');
		eval('$output .= "' . fetch_template('option') . '";');
	}

	return $output;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11575 $
|| ####################################################################
\*======================================================================*/
?>