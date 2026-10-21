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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'calendar');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'calendar',
	'holiday',
	'timezone',
	'posting'
);
// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'calendarjump',
	'calendarjumpbit',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'displayweek' => array(
		'calendar_monthly',
		'calendar_monthly_week',
		'calendar_monthly_day',
		'calendar_monthly_day_other',
		'calendar_monthly_birthday',
		'calendar_monthly_event',
		'calendar_monthly_header',
		'calendar_smallmonth_header',
		'calendar_smallmonth_week',
		'calendar_smallmonth_day',
		'calendar_smallmonth_day_other',
		'calendar_weekly_day',
		'calendar_weekly_event',
		'calendar_weekly',
		'calendar_showbirthdays',
		'CALENDAR'
	),
	'displayyear' => array(
		'calendar_smallmonth_day_other',
		'calendar_smallmonth_header',
		'calendar_smallmonth_week',
		'calendar_monthly_event',
		'calendar_smallmonth_day',
		'calendar_monthly_week',
		'calendar_showbirthdays',
		'calendar_weekly_day',
		'calendar_yearly',
		'CALENDAR'
	),
	'getinfo' => array(
		'calendar_showevents',
		'calendar_showbirthdays',
		'calendar_showeventsbit',
		'calendar_showeventsbit_customfield'
	),
	'edit' => array(
		'calendar_edit',
		'calendar_edit_customfield',
		'calendar_edit_recurrence',
		'userfield_select_option'
	),
	'manage' => array(
		'calendar_edit',
		'calendar_manage'
	),
	'viewreminder' => array(
		'CALENDAR_REMINDER',
		'calendar_reminder_eventbit',
		'USERCP_SHELL',
		'forumdisplay_sortarrow',
		'usercp_nav_folderbit',
	)
);

$actiontemplates['getday'] = &$actiontemplates['getinfo'];
$actiontemplates['add'] = &$actiontemplates['edit'];

if (PHP_VERSION < '4.1.0')
{
	$_REQUEST = array_merge($HTTP_GET_VARS, $HTTP_POST_VARS, $HTTP_COOKIE_VARS);
	$_COOKIE = &$HTTP_COOKIE_VARS;
}

// get the editor templates if required
if (in_array($_REQUEST['do'], array('edit', 'add', 'manage')))
{
	define('GET_EDIT_TEMPLATES', true);
}

$actiontemplates['displaymonth'] = &$actiontemplates['displayweek'];
$actiontemplates['none'] = &$actiontemplates['displayweek'];

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_calendar.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$serveroffset = date('Z', TIMENOW) / 3600;

$idname = $vbphrase['event'];

if ($_REQUEST['week'])
{
	$_REQUEST['do'] = 'displayweek';
}

globalize($_REQUEST, array('calendarid' => INT, 'c' => INT, 'eventid' => INT, 'e' => INT, 'holidayid' => INT, 'week' => INT, 'month' => INT, 'year' => INT, 'sb' => INT));

$calendarid = iif ($c, $c, $calendarid);
$eventid = iif($e, $e, $eventid);

if (!$calendarid)
{ // Determine the first calendar we have canview access to for the default calendar
	if ($eventid AND $eventid > 0)
	{ // get calendarid for this event
		if ($eventinfo = $DB_site->query_first("
			SELECT event.*, user.username, IF(dateline_to = 0, 1, 0) AS singleday,
			IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid
			" . iif($bbuserinfo['userid'], ', subscribeevent.eventid AS subscribed') . "
			FROM " . TABLE_PREFIX . "event AS event
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = event.userid)
			" . iif($bbuserinfo['userid'], "LEFT JOIN " . TABLE_PREFIX . "subscribeevent AS subscribeevent ON(subscribeevent.eventid = $eventid AND subscribeevent.userid = $bbuserinfo[userid])") . "
			WHERE event.eventid = $eventid"))
		{
			$calendarid = $eventinfo['calendarid'];
			if (!$calendarid)
			{
				foreach ($calendarcache AS $index => $value)
				{
					if ($bbuserinfo['calendarpermissions']["$index"] & CANVIEWCALENDAR)
					{
						$calendarid = $index;
						$addcalendarid = $index;
						break;
					}
				}
			}
			if (!($bbuserinfo['calendarpermissions']["$calendarid"]) & CANVIEWCALENDAR)
			{
				print_no_permission();
			}
			if (!$eventinfo['visible'])
			{
				eval(print_standard_error('error_invalidid'));
			}

			$eventinfo['dateline_from_user'] = $eventinfo['dateline_from'] + $bbuserinfo['timezoneoffset'] * 3600;
			$eventinfo['dateline_to_user'] = $eventinfo['dateline_to'] + $bbuserinfo['timezoneoffset'] * 3600;
			$eventinfo['musername'] = fetch_musername($eventinfo);
		}
		else
		{
			eval(print_standard_error('error_invalidid'));
		}
	}
	else
	{
		foreach ($calendarcache AS $index => $value)
		{
			if ($bbuserinfo['calendarpermissions']["$index"] & CANVIEWCALENDAR)
			{
				$calendarid = $index;
				$addcalendarid = $index;
				break;
			}
		}
		if (!$calendarid)
		{
			if (sizeof($calendarcache) == 0)
			{
				eval(print_standard_error('error_nocalendars'));
			}
			else
			{
				print_no_permission();
			}
		}
	}
}
else if (!($bbuserinfo['calendarpermissions']["$calendarid"]) & CANVIEWCALENDAR)
{
	print_no_permission();
}
else if ($eventid AND $eventid > 0)
{
	if ($eventinfo = $DB_site->query_first("
		SELECT event.*, user.username, IF(dateline_to = 0, 1, 0) AS singleday,
		IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid
		" . iif($bbuserinfo['userid'], ', subscribeevent.eventid AS subscribed') . "
		FROM " . TABLE_PREFIX . "event AS event
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = event.userid)
		" . iif($bbuserinfo['userid'], "LEFT JOIN " . TABLE_PREFIX . "subscribeevent AS subscribeevent ON(subscribeevent.eventid = $eventid AND subscribeevent.userid = $bbuserinfo[userid])") . "
		WHERE event.eventid = $eventid"))
	{
		if (!$eventinfo['visible'])
		{
			eval(print_standard_error('error_invalidid'));
		}

		$eventinfo['dateline_from_user'] = $eventinfo['dateline_from'] + $bbuserinfo['timezoneoffset'] * 3600;
		$eventinfo['dateline_to_user'] = $eventinfo['dateline_to'] + $bbuserinfo['timezoneoffset'] * 3600;
		$eventinfo['musername'] = fetch_musername($eventinfo);
	}
	else
	{
		eval(print_standard_error('error_invalidid'));
	}
}
if ($holidayid AND $holidayid > 0)
{
	if ($eventinfo = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "holiday AS holiday
		WHERE holidayid = $holidayid")
	)
	{
		$eventinfo['visible'] = 1;
		$eventinfo['holiday'] = 1;
		$eventinfo['title'] = $vbphrase['holiday_title_' . $eventinfo['varname']];
		$eventinfo['event'] = $vbphrase['holiday_event_' . $eventinfo['varname']];
	}
	else
	{
		eval(print_standard_error('error_invalidid'));
	}
}

if ($eventinfo['eventid'] AND $eventinfo['userid'] != $bbuserinfo['userid'] AND !($bbuserinfo['calendarpermissions']["$eventinfo[calendarid]"] & CANVIEWOTHERSEVENT))
{
	print_no_permission();
}

$calendarinfo = verify_id('calendar', $calendarid, 1, 1);
$getoptions = convert_bits_to_array($calendarinfo['options'], $_CALENDAROPTIONS);
$calendarinfo = array_merge($calendarinfo, $getoptions);
$geteaster = convert_bits_to_array($calendarinfo['holidays'], $_CALENDARHOLIDAYS);
$calendarinfo = array_merge($calendarinfo, $geteaster);

if (empty($_REQUEST['do']))
{
	$defaultview = iif ($calendarinfo['default'], 'displayweek', 'displaymonth');
	$_REQUEST['do'] = iif(!empty($_COOKIE[COOKIE_PREFIX . 'calview' . $calendarinfo['calendarid']]), $_COOKIE[COOKIE_PREFIX . 'calview' . $calendarinfo['calendarid']], $defaultview);
}

if ($sb)
{
	// Allow showbirthdays to be turned on if they are off -- mainly for the birthday link from the front page
	$calendarinfo['showbirthdays'] = true;
}

if ($bbuserinfo['startofweek'] > 7 OR $bbuserinfo['startofweek'] < 1)
{
	$bbuserinfo['startofweek'] = $calendarinfo['startofweek'];
}

// get decent textarea size for user's browser
require_once('./includes/functions_editor.php');
$textareacols = fetch_textarea_width();

// Make first part of Calendar Nav Bar
$navbits = array("calendar.php?$session[sessionurl]" => $vbphrase['calendar']);

// Make second part of calendar nav... link if needed
if (in_array($_REQUEST['do'], array('displayweek', 'displaymonth', 'displayyear')))
{
	$navbits[''] = $calendarinfo['title'];
}
else
{
	$navbits["calendar.php?$session[sessionurl]c=$calendarid"] = $calendarinfo['title'];
}

$today = getdate(TIMENOW - $vboptions['hourdiff']);
$today['month'] = $vbphrase[strtolower($today['month'])];

if (!$year)
{
	if (!empty($_COOKIE[COOKIE_PREFIX . 'calyear']))
	{
		$year = intval($_COOKIE[COOKIE_PREFIX . 'calyear']);
	}
	else
	{
		$year = $today['year'];
		vbsetcookie('calyear', $year, 0);
	}
}
else
{
	if ($year < 1970 OR $year > 2037)
	{
		$year = $today['year'];
	}
	vbsetcookie('calyear', $year, 0);
}

if (!$month)
{
	if (!empty($_COOKIE[COOKIE_PREFIX . 'calmonth']))
	{
		$month = intval($_COOKIE[COOKIE_PREFIX . 'calmonth']);
	}
	else
	{
		$month = $today['mon'];
		vbsetcookie('calmonth', $month, 0);
	}
}
else
{
	if ($month < 1 OR $month > 12)
	{
		$month = $today['mon'];
	}
	vbsetcookie('calmonth', $month, 0);
}

if ($calendarinfo['startyear'])
{
	if ($year < $calendarinfo['startyear'] OR $year > $calendarinfo['endyear'])
	{
		if ($calendarinfo['startyear'] > $today['year'])
		{
			$year = $calendarinfo['startyear'];
			$month = 1;
		}
		else
		{
			$year = $calendarinfo['endyear'];
			$month = 12;
		}
		vbsetcookie('calyear', $year, 0);
		vbsetcookie('calmonth', $month, 0);
	}
}

if ($month >= 1 AND $month <= 9)
{
	$doublemonth = "0$month";
}
else
{
	$doublemonth = $month;
}

// For calendarjump
$monthselected["$month"] = HTML_SELECTED;

// ############################################################################
// ############################### MONTHLY VIEW ###############################

if ($_REQUEST['do'] == 'displaymonth')
{
	$show['weeklyview'] = false;
	$show['monthlyview'] = true;
	$show['yearlyview'] = false;

	$usertoday = array(
		'firstday' => gmdate('w', gmmktime(0, 0, 0, $month, 1, $year)),
		'day' => intval($_REQUEST['day']),
		'month' => $month,
		'year' => $year,
	);

	// Make Nav Bar #####################################################################
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	$usertodayprev = $usertoday;
	$usertodaynext = $usertoday;
	$eventrange = array();

	if ($month == 1)
	{
		$usertodayprev['month'] = 12;
		$usertodayprev['year'] = $year - 1;
		$usertodayprev['firstday'] = gmdate('w', gmmktime(0, 0, 0, 12, 1, $year - 1));
		$eventrange['frommonth'] = 12;
		$eventrange['fromyear'] = $year - 1;
	}
	else
	{
		$usertodayprev['month'] = $month - 1;
		$usertodayprev['year'] = $year;
		$usertodayprev['firstday'] = gmdate('w', gmmktime(0, 0, 0, $month - 1, 1, $year));
		$eventrange['frommonth'] = $month - 1;
		$eventrange['fromyear']= $year;
	}

	if ($month == 12)
	{
		$usertodaynext['month'] = 1;
		$usertodaynext['year'] = $year + 1;
		$usertodaynext['firstday'] = gmdate('w', gmmktime(0, 0, 0, 1, 1, $year + 1));
		$eventrange['nextmonth'] = 1;
		$eventrange['nextyear'] = $year + 1;
	}
	else
	{
		$usertodaynext['month'] = $month + 1;
		$usertodaynext['year'] = $year;
		$usertodaynext['firstday'] = gmdate('w', gmmktime(0, 0, 0, $month + 1, 1, $year));
		$eventrange['nextmonth'] = $month + 1;
		$eventrange['nextyear'] = $year;
	}

	$birthdaycache = cache_birthdays();
	$eventcache = cache_events($eventrange);

	if ($month == 1 AND $year == 1970)
	{
		$prevmonth = '';
	}
	else
	{
		$prevmonth = construct_calendar_output($today, $usertodayprev, $calendarinfo);
	}
	$calendarbits = construct_calendar_output($today, $usertoday, $calendarinfo, 1);
	if ($month == 12 AND $year == 2037)
	{
		$nextmonth = '';
	}
	else
	{
		$nextmonth = construct_calendar_output($today, $usertodaynext, $calendarinfo);
	}

	$monthname = $vbphrase[strtolower(gmdate('F', gmmktime(0, 0, 0, $month, 1, $year)))];

	$calendarjump = construct_calendar_jump($calendarid);

	if ($_COOKIE[COOKIE_PREFIX . 'calview' . $calendarinfo['calendarid']] != 'displaymonth')
	{
		vbsetcookie('calview' . $calendarinfo['calendarid'], 'displaymonth', 0);
	}

	eval('$HTML = "' . fetch_template('calendar_monthly') . '";');
	eval('print_output("' . fetch_template('CALENDAR') . '");');

}

// ############################################################################
// ############################### WEEKLY VIEW ################################
// ############################################################################

if ($_REQUEST['do'] == 'displayweek')
{
	$show['weeklyview'] = true;
	$show['monthlyview'] = false;
	$show['yearlyview'] = false;

	if ($week)
	{
		if ($week < 259200)
		{
			$week = 259200;
		}
		else if ($week > 2145484800)
		{
			$week = 2145484800;
		}
		$prevweek = $week - 604800;
		$nextweek = $week + 604800;
	}
	else
	{
		$firstday = gmdate('w', gmmktime(0, 0, 0, 1, 1, $year)) + 1;
		if ($bbuserinfo['startofweek'] <= $firstday)
		{
			$offset = -1 * ($firstday - $bbuserinfo['startofweek'] - 1);
		}
		else
		{ // $firstday < Start Of Week
			$offset = ($firstday + 6) * -1 + $bbuserinfo['startofweek'];
		}
		if ($month == $today['mon'] AND $year == $today['year'])
		{
			$todaystamp = gmmktime(0, 0, 0, $month, $today['mday'], $year);
		}
		else
		{
			$todaystamp = gmmktime(0, 0, 0, $month, 1, $year);
		}
		while (true)
		{
			$prevweek = gmmktime(0, 0, 0, 1, $offset - 7, $year);
			$week = gmmktime(0, 0, 0, 1, $offset, $year);
			$nextweek = gmmktime(0, 0, 0, 1, $offset + 7, $year);
			if ($nextweek > $todaystamp)
			{ // current week was last week so show that week!!
				break;
			}
			else
			{
				$offset += 7;
			}
		}

	}

	$day1 = gmdate('n-j-Y', $week);
	$day1 = explode('-', $day1);
	$day7 = gmdate('n-j-Y', gmmktime(0, 0, 0, $day1[0], $day1[1] + 6, $day1[2]));
	$day7 = explode('-', $day7);

	$usertoday1 = array(
		'firstday' => gmdate('w', gmmktime(0, 0, 0, $day1[0], 1, $day1[2])),
		'month' => $day1[0],
		'year' => $day1[2]
	);
	$eventrange = array();
	$usertoday1 = array();
	$eventrange['frommonth'] = $day1[0];
	$eventrange['fromyear'] = $day1[2];
	$usertoday1['month'] = $day1[0];
	$usertoday1['year'] = $day1[2];
	$usertoday1['firstday'] = gmdate('w', gmmktime(0, 0, 0, $day1[0], 1, $day1[2]));
	if ($day1[0] != $day7[0])
	{
		$eventrange['nextmonth'] = $day7[0];
		$eventrange['nextyear'] = $day7[2];
		$usertoday2 = array();
		$usertoday2['month'] = $day7[0];
		$usertoday2['year'] = $day7[2];
		$usertoday2['firstday'] = gmdate('w', gmmktime(0, 0, 0, $day7[0], 1, $day7[2]));
	}
	else
	{
		$eventrange['nextmonth'] = $eventrange['frommonth'];
		$eventrange['nextyear'] = $eventrange['fromyear'];
	}

	$doublemonth1 = iif($day1[0] < 10, '0' . $day1[0], $day1[0]);
	$doublemonth2 = iif($day7[0] < 10, '0' . $day7[0], $day7[0]);
	$birthdaycache = cache_birthdays(1);
	$eventcache = cache_events($eventrange);

	$weekrange = array();
	$weekrange['start'] = gmmktime(0, 0, 0, $day1[0], $day1[1], $day1[2]);
	$weekrange['end'] = gmmktime(0, 0, 0, $day7[0], $day7[1], $day7[2]);
	$month1 = construct_calendar_output($today, $usertoday1, $calendarinfo, 0, $weekrange);
	if (is_array($usertoday2) AND $week != 2145484800)
	{
		$month2 = construct_calendar_output($today, $usertoday2, $calendarinfo, 0, $weekrange);
		$show['secondmonth'] = true;
	}

	$daystamp = $weekrange['start'];
	$eastercache = fetch_easter_array($day1['2']);

	$lastmonth = '';

	while ($daystamp <= $weekrange['end'])
	{
		$weekmonth = $vbphrase[strtolower(gmdate('F', $daystamp))];
		$weekdayname = $vbphrase[ strtolower(gmdate('l', $daystamp)) ];
		$weekday = gmdate('j', $daystamp);
		$weekyear = gmdate('Y', $daystamp);
		$month = gmdate('n', $daystamp);
		$monthnum = gmdate('m', $daystamp);
		if ($lastmonth != $weekmonth)
		{
			$show['monthname'] = true;
		}
		else
		{
			$show['monthname'] = false;
		}
		if (!$calendarinfo['showweekends'] AND (gmdate('w', $daystamp) == 6 OR gmdate('w', $daystamp) == 0))
		{
			// do nothing..
		}
		else
		{
			// Process birthdays / Events / templates
			unset($userbdays);
			$show['birthdays'] = false;
			if ($calendarinfo['showbirthdays'] AND is_array($birthdaycache["$month"]["$weekday"]))
			{
				unset($userday);
				unset($age);
				unset($comma);
				$bdaycount = 0;
				foreach ($birthdaycache["$month"]["$weekday"] AS $index => $value)
				{
					$userday = explode('-', $value['birthday']);
					$bdaycount++;
					$username = $value['username'];
					$userid = $value['userid'];
					if ($weekyear > $userday[2] AND $userday[2] != '0000')
					{
						$age = '(' . ($weekyear - $userday[2]) . ')';
						$show['age'] = true;
					}
					else
					{
						unset($age);
						$show['age'] = false;
					}
					eval ("\$userbdays .= \"$comma " . fetch_template('calendar_showbirthdays') . '";');
					$comma = ',';
					$show['birthdays'] = true;
				}
			}

			require_once('./includes/functions_misc.php');

			unset($userevents);
			$show['events'] = false;
			if (is_array($eventcache))
			{
				$eventarray = cache_events_day($month, $weekday, $weekyear);

				foreach ($eventarray AS $index => $value)
				{
					$show['holiday'] = iif ($value['holidayid'], true, false);
					$eventid = $value['eventid'];
					$holidayid = $value['holidayid'];

					$allday = false;
					$eventtitle =  htmlspecialchars_uni($value['title']);
					$year = gmdate('Y', $daystamp);
					$month = gmdate('n', $daystamp);
					$day = gmdate('j', $daystamp);
					if (!$value['singleday'])
					{
						$fromtime = vbgmdate($vboptions['timeformat'], $value['dateline_from_user']);
						$totime = vbgmdate($vboptions['timeformat'], $value['dateline_to_user']);

						$eventfirstday = gmmktime(0, 0, 0, gmdate('n', $value['dateline_from_user']), gmdate('j', $value['dateline_from_user']), gmdate('Y', $value['dateline_from_user']));
						$eventlastday = gmmktime(0, 0, 0, gmdate('n', $value['dateline_to_user']), gmdate('j', $value['dateline_to_user']), gmdate('Y', $value['dateline_to_user']));

						if (!$value['recurring'])
						{
							if ($eventfirstday == $daystamp)
							{
								if ($eventfirstday != $eventlastday)
								{
									if (gmdate('g:ia', $value['dateline_from_user']) == '12:00am')
									{
										$allday = true;
									}
									else
									{
										$totime = vbgmdate($vboptions['timeformat'], 946771200);
									}
								}
							}
							else if ($eventlastday == $daystamp)
							{
								$fromtime = vbgmdate($vboptions['timeformat'], 946771200);
							}
							else // A day in the middle of a multi-day event so event covers 24 hours
							{
								$allday = true; // Used in conditional
							}
						}
						$show['time'] = true;
					}
					else
					{
						$show['time'] = false;
					}
					$issubscribed = iif($value['subscribed'], true, false);
					$show['events'] = true;
					eval ('$userevents .= "' . fetch_template('calendar_weekly_event') . '";');
				}
			}

			$month = gmdate('n', $daystamp);

			if (!empty($eastercache["$month-$weekday-$weekyear"]))
			{
				$show['events'] = true;
				$show['holiday'] = true;
				$eventtotal++;
				$eventtitle = &$eastercache["$month-$weekday-$weekyear"]['title'];
				eval ('$userevents .= "' . fetch_template('calendar_weekly_event') . '";');
				unset($holidayid);
				$show['holiday'] = false;
			}

			eval('$weekbits .= "' . fetch_template('calendar_weekly_day') . '";');
			$lastmonth = $weekmonth;
		}
		$daystamp = gmmktime(0, 0, 0, $day1['0'], ++$day1['1'], $day1['2']);
	}

	// Make Nav Bar #####################################################################
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	if ($_COOKIE[COOKIE_PREFIX . 'calview' . $calendarinfo['calendarid']] != 'displayweek')
	{
		vbsetcookie('calview' . $calendarinfo['calendarid'], 'displayweek', 0);
	}
	$calendarjump = construct_calendar_jump($calendarid);

	eval('$HTML = "' . fetch_template('calendar_weekly') . '";');
	eval('print_output("' . fetch_template('CALENDAR') . '");');

}

// ############################################################################
// ############################### YEARLY VIEW ################################
// ############################################################################

if ($_REQUEST['do'] == 'displayyear')
{
	$show['weeklyview'] = false;
	$show['monthlyview'] = false;
	$show['yearlyview'] = true;

	$eventrange = array('frommonth' => 1, 'fromyear' => $year, 'nextmonth' => 12, 'nextyear' => $year);
	$eventcache = cache_events($eventrange);

	$usertoday = array();
	$usertoday['year'] = $year;

	for ($x = 1; $x <= 12; $x++)
	{
		$usertoday['month'] = $x;
		$usertoday['firstday'] = date('w', mktime(12, 0, 0, $x, 1, $year));
		// build small calendar.
		$calname = 'month' . $x;
		$$calname = construct_calendar_output($today, $usertoday, $calendarinfo);
	}

	// Make Nav Bar #####################################################################
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	$calendarjump = construct_calendar_jump($calendarid);

	eval('$HTML = "' . fetch_template('calendar_yearly') . '";');
	eval('print_output("' . fetch_template('CALENDAR') . '");');
}

// ############################################################################
// ############################### MANAGE EVENT ###############################
// ############################################################################

if ($_POST['do'] == 'manage')
{
	globalize($_POST, array(
		'what' => STR,
		'newcalendarid' => INT,
		'day'
	));

	$getdate = explode('-', $day);
	$year = intval($getdate[0]);
	$month = intval($getdate[1]);
	$day = intval($getdate[2]);

	$validdate = checkdate($month, $day, $year);

	if (!$eventinfo['eventid'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	$eventinfo['title'] = htmlspecialchars_uni($eventinfo['title']);

	if ($what == 'dodelete' AND empty($_POST['dodelete']))
	{
		// tried to delete but didn't click the checkbox... try again.
		$what = 'delete';
	}

	$print_output = false;

	switch ($what)
	{
		// do delete
		case 'dodelete':
		{
			if (!can_moderate_calendar($calendarinfo['calendarid'], 'candeleteevents'))
			{
				print_no_permission();
			}
			else
			{
				$DB_site->query("DELETE FROM " . TABLE_PREFIX . "event WHERE eventid = $eventinfo[eventid]");
				$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscribeevent WHERE eventid = $eventinfo[eventid]");
				require_once('./includes/functions_databuild.php');
				build_events();
				$url = "calendar.php?$session[sessionurl]c=$eventinfo[calendarid]";
				eval(print_standard_redirect('redirect_calendardeleteevent'));
			}
		}
		break;

		// delete
		case 'delete':
		{
			if (!can_moderate_calendar($calendarinfo['calendarid'], 'candeleteevents'))
			{
				print_no_permission();
			}
			else
			{
				$print_output = true;
				$show['delete'] = true;
				if ($validdate)
				{
					$navbits["calendar.php?$session[sessionurl]do=getinfo&amp;c=$calendarid&amp;day=$year-$month-$day"] = vbgmdate($vboptions['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
				}
				$navbits["calendar.php?$session[sessionurl]do=getinfo&amp;e=$eventinfo[eventid]"] = $eventinfo['title'];
				$navbits[''] = $vbphrase['delete_event'];
			}
		}
		break;

		// do move
		case 'domove':
		{
			if (!can_moderate_calendar($calendarinfo['calendarid'], 'canmoveevents'))
			{
				print_no_permission();
			}
			else
			{
				$calendarperms = $bbuserinfo['calendarpermissions']["$newcalendarid"];
				if (!($calendarperms & CANVIEWCALENDAR))
				{
					print_no_permission();
				}
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "event
					SET calendarid = $newcalendarid
					WHERE eventid = $eventinfo[eventid]
				");
				require_once('./includes/functions_databuild.php');
				build_events();
				$url = "calendar.php?$session[sessionurl]c=$newcalendarid";
				eval(print_standard_redirect('redirect_calendarmoveevent'));
			}
		}
		break;

		// edit - skip through to do=edit
		case 'edit':
		{
			$_POST['do'] = 'edit';
		}
		break;

		// move + failsafe
		default:
		{
			if (!can_moderate_calendar($calendarinfo['calendarid'], 'canmoveevents'))
			{
				print_no_permission();
			}
			else
			{
				$calendarbits = '';
				foreach ($calendarcache AS $calendarid => $title)
				{
					$calendarperms = $bbuserinfo['calendarpermissions']["$calendarid"];
					if (!($calendarperms & CANVIEWCALENDAR) OR ($calendarid == $eventinfo['calendarid']))
					{
						continue;
					}
					else
					{
						$optionvalue = $calendarid;
						$optiontitle = $title;
						eval('$calendarbits .= "' . fetch_template('option') . '";');
					}
				}
				if ($calendarbits == '')
				{
					eval(print_standard_error('error_calendarmove'));
				}
				else
				{
					$print_output = true;
					$show['delete'] = false;
					if ($validdate)
					{
						$navbits["calendar.php?$session[sessionurl]do=getinfo&amp;c=$calendarid&amp;day=$year-$month-$day"] = vbgmdate($vboptions['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
					}
					$navbits["calendar.php?$session[sessionurl]do=getinfo&amp;e=$eventinfo[eventid]"] = $eventinfo['title'];
					$navbits[''] = $vbphrase['move_event'];
				}
			}
		}
	}

	if ($print_output)
	{
		$navbits = construct_navbits($navbits);
		eval('$navbar = "' . fetch_template('navbar') . '";');
		eval('print_output("' . fetch_template('calendar_manage') . '");');
	}
}

// ############################################################################
// ############################### GET EVENTS #################################
// ############################################################################

if ($_REQUEST['do'] == 'getday' OR $_REQUEST['do'] == 'getinfo')
{

	globalize($_REQUEST, array('day'));

	$getdate = explode('-', $day);
	$year = intval($getdate[0]);
	$month = intval($getdate[1]);
	$day = intval($getdate[2]);

	$validdate = checkdate($month, $day, $year);
	$eventarray = array();

	if ($eventid)
	{
		$eventarray = array($eventinfo);
	}
	else if ($validdate)
	{
		$doublemonth = iif($month < 10, '0' . $month, $month);
		$doubleday = iif($day < 10, '0' . $day, $day);

		$todaystamp = gmmktime(0, 0, 0, $month, $day, $year);

		// set date range for events to cache.
		$eventrange = array('frommonth' => $month, 'fromyear' => $year, 'nextmonth' => $month, 'nextyear' => $year);

		// cache events for this month only.
		$eventcache = cache_events($eventrange);

		if ($calendarinfo['showbirthdays'])
		{  // Load the birthdays for today
			$ids = '';
			foreach($usergroupcache AS $usergroupid => $usergroup)
			{
				if ($usergroup['genericoptions'] & SHOWBIRTHDAY)
				{
					$ids .= ",$usergroupid";
				}
			}

			$comma = '';
			$birthday = $DB_site->query("
				SELECT birthday,username,userid
				FROM " . TABLE_PREFIX . "user
				WHERE birthday LIKE '$doublemonth-$doubleday-%' AND
					usergroupid IN (0$ids)
			");

			while ($birthdays = $DB_site->fetch_array($birthday))
			{
				$userday = explode('-', $birthdays['birthday']);
				$username = $birthdays['username'];
				$userid = $birthdays['userid'];
				if ($year > $userday[2] AND $userday[2] != '0000')
				{
					$age = '(' . ($year - $userday[2]) . ')';
					$show['age'] = true;
				}
				else
				{
					unset($age);
					$show['age'] = false;
				}
				eval ("\$userbdays .= \"$comma " . fetch_template('calendar_showbirthdays') . '";');

				$show['birthdays'] = true;

				$comma = ',';
			}
		}

		$eventarray = cache_events_day($month, $day, $year);
	}

	if (!empty($eventarray))
	{
		$customcalfields = $DB_site->query("
			SELECT calendarcustomfieldid, title, options, allowentry, description
			FROM " . TABLE_PREFIX . "calendarcustomfield AS calendarcustomfield
			WHERE calendarid = $calendarid
			ORDER BY calendarcustomfieldid
		");
		$customfieldssql = array();
		while ($custom = $DB_site->fetch_array($customcalfields))
		{
			$customfieldssql[] = $custom;
		}
	}

	$show['canmoveevent'] = can_moderate_calendar($calendarinfo['calendarid'], 'canmoveevents');
	$show['candeleteevent'] = can_moderate_calendar($calendarinfo['calendarid'], 'candeleteevents');

	foreach ($eventarray AS $index => $eventinfo)
	{
		$eventinfo = fetch_event_date_time($eventinfo);
		$holidayid = $eventinfo['holidayid'];
		$customfields = '';

		$eventinfo['musername'] = fetch_musername($eventinfo);

		if (!$holidayid)
		{
			unset($holidayid);
			$eventfields = unserialize($eventinfo['customfields']);

			$bgclass = 'alt2';
			$show['customfields'] = false;
			foreach ($customfieldssql AS $index => $value)
			{
				$description = $value['description'];
				$value['options'] = unserialize($value['options']);
				exec_switch_bg();
				$selectbits = '';
				$customoption = '';
				$customtitle = $value['title'];
				if (is_array($value['options']))
				{
					foreach ($value['options'] AS $key => $val)
					{
						if ($val == $eventfields["$value[calendarcustomfieldid]"])
						{
							$customoption = $val;
							break;
						}
					}
				}

				// Skip this value if a user entered entry exists but no longer allowed
				if (!$value['allowentry'] AND $customoption == '')
				{
					continue;
				}

				require_once('./includes/functions_newpost.php');
				$customoption = parse_calendar_bbcode( convert_url_to_bbcode(unhtmlspecialchars( $eventfields["{$value['calendarcustomfieldid']}"])));

				$show['customoption'] = iif($customoption == '', false, true);
				if ($show['customoption'])
				{
					$show['customfields'] = true;
				}
				eval('$customfields .= "' . fetch_template('calendar_showeventsbit_customfield') . '";');
			}

			$show['holiday'] = false;
			// check for calendar moderator here.
			$show['caneditevent'] = true;
			if (!can_moderate_calendar($calendarid, 'caneditevents'))
			{
				if ($eventinfo['userid'] != $bbuserinfo['userid'])
				{
					$show['caneditevent'] = false;
				}
				else if (!($bbuserinfo['calendarpermissions']["$calendarid"] & CANEDITEVENT))
				{
					$show['caneditevent'] = false;
				}
			}
		}
		else
		{
			$show['holiday'] = true;
			$show['caneditevent'] = false;
		}

		exec_switch_bg();
		if (!$eventinfo['singleday'] AND gmdate('w', $eventinfo['dateline_from_user']) != gmdate('w', $eventinfo['dateline_from'] + ($eventinfo['utc'] * 3600)))
		{
			$show['adjustedday'] = true;
			$eventinfo['timezone'] = str_replace('&nbsp;', ' ', $vbphrase[fetch_timezone($eventinfo['utc'])]);
		}
		else
		{
			$show['adjustedday'] = false;
		}

		$show['subscribed'] = iif ($eventinfo['subscribed'], true, false);
		if ($eventinfo['subscribed'])
		{
			$show['subscribelink'] = true;
		}
		else if ($bbuserinfo['userid'] AND $eventinfo['dateline_to'] AND TIMENOW <= $eventinfo['dateline_to'])
		{
			$show['subscribelink'] = true;
		}
		else if ($bbuserinfo['userid'] AND $eventinfo['singleday'] AND TIMENOW <= $eventinfo['dateline_from'])
		{
			$show['subscribelink'] = true;
		}
		else
		{
			$show['subscribelink'] = false;
		}
		$show['postedby'] = iif($eventinfo['userid'], true, false);
		$show['singleday'] = iif($eventinfo['singleday'], true, false);
		if (($show['candeleteevent'] OR $show['canmoveevent'] OR $show['caneditevent']) AND !$show['holiday'])
		{
			$show['eventoptions'] = true;
		}

		eval ('$caldaybits .= "' . fetch_template('calendar_showeventsbit') . '";');
	}
	unset($date2, $recurcriteria, $customfields);
	$show['subscribelink'] = false;
	$show['adjustedday'] = false;
	$show['singleday'] = false;
	$show['holiday'] = false;
	$show['eventoptions'] = false;
	$show['postedby'] = false;
	$show['recuroption'] = false;

	if (!$eventid)
	{
		$eventinfo = array();
		$holidayid = -1;
		$eastercache = fetch_easter_array($year);

		if (!empty($eastercache["$month-$day-$year"]))
		{
			$eventinfo['title'] = &$eastercache["$month-$day-$year"]['title'];
			$eventinfo['event'] = &$eastercache["$month-$day-$year"]['event'];
			$show['holiday'] = true;
		}

		if ($eventinfo['title'] != '')
		{
			require_once('./includes/functions_misc.php');
			$eventdate = vbgmdate($vboptions['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
			$titlecolor = 'alt2';
			$bgclass = 'alt1';
			eval ('$caldaybits .= "' . fetch_template('calendar_showeventsbit') . '";');
		}
	}

	if (empty($eventarray) AND !$show['birthdays'] AND !$show['holiday'])
	{
		eval(print_standard_error('error_noevents'));
	}

	$monthselected = array($month => HTML_SELECTED);
	$calendarjump = construct_calendar_jump($calendarid);

	// Make Rest of Nav Bar
	require_once('./includes/functions_misc.php');
	if ($eventid)
	{
		if ($validdate)
		{
			$navbits["calendar.php?$session[sessionurl]do=getinfo&amp;c=$calendarid&amp;day=$year-$month-$day"] = vbgmdate($vboptions['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
		}
		$navbits[''] = $eventinfo['title'];
	}
	else
	{
		$navbits[''] = vbgmdate($vboptions['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
	}

	$navbits = construct_navbits($navbits);
	eval('$navbar = "'. fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('calendar_showevents') . '");');
}

// ############################################################################
// ################################# EDIT EVENT ###############################
// ############################################################################

if ($_POST['do'] == 'edit')
{
	if (!$eventinfo['eventid'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	// check for calendar moderator here.
	if (!can_moderate_calendar($calendarid, 'caneditevents'))
	{
		if ($eventinfo['userid'] != $bbuserinfo['userid'])
		{
			print_no_permission();
		}
		else if (!($bbuserinfo['calendarpermissions']["$calendarid"] & CANEDITEVENT))
		{
			print_no_permission();
		}

	}

	$checked = array('disablesmilies' => iif($eventinfo['allowsmilies'] == 1, '', HTML_CHECKED));

	if ($calendarinfo['allowsmilies'])
	{
		eval('$disablesmiliesoption = "' . fetch_template('newpost_disablesmiliesoption') . '";');
	}

	$calrules['allowbbcode'] = $calendarinfo['allowbbcode'];
	$calrules['allowimages'] = $calendarinfo['allowimagecode'];
	$calrules['allowhtml'] = $calendarinfo['allowhtml'];
	$calrules['allowsmilies'] = $calendarinfo['allowsmilies'];

	$bbcodeon = iif($calrules['allowbbcode'], $vbphrase['on'], $vbphrase['off']);
	$imgcodeon = iif($calrules['allowimages'], $vbphrase['on'], $vbphrase['off']);
	$htmlcodeon = iif($calrules['allowhtml'], $vbphrase['on'], $vbphrase['off']);
	$smilieson = iif($calrules['allowsmilies'], $vbphrase['on'], $vbphrase['off']);

	// only show posting code allowances in forum rules template
	$show['codeonly'] = true;

	require_once('./includes/functions_bigthree.php');
	construct_forum_rules($calrules, $permissions);

	$currentpage = urlencode("calendar.php?do=edit&e=$eventinfo[eventid]");
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	$title = $eventinfo['title'];
	$message = htmlspecialchars_uni($eventinfo['event']);

	$fromdate = explode('-', gmdate('n-j-Y', $eventinfo['dateline_from'] + $eventinfo['utc'] * 3600));
	$fromtime = gmdate('g_i_A_H', $eventinfo['dateline_from'] + $eventinfo['utc'] * 3600);

	$todate = explode('-', gmdate('n-j-Y', $eventinfo['dateline_to'] + $eventinfo['utc'] * 3600));
	$totime = gmdate('g_i_A_H', $eventinfo['dateline_to'] + $eventinfo['utc'] * 3600);

	$fromtime = explode('_', $fromtime);
	$totime = explode('_', $totime);

	if (strpos($vboptions['timeformat'], 'H') !== false)
	{
		$show['24hour'] = true;
	}
	else
	{
		$show['24hour'] = false;
	}

	$fromtimeoptions = fetch_time_options($fromtime, $show['24hour']);
	$totimeoptions = fetch_time_options($totime, $show['24hour']);

	if ($eventinfo['utc'] < 0)
	{
		$timezonesel['n' . (-$eventinfo['utc'] * 10)] = HTML_SELECTED;
	}
	else
	{
		$index = $eventinfo['utc'] * 10;
		$timezonesel["$index"] = HTML_SELECTED;
	}

	// select correct timezone and build timezone options
	$timezoneoptions = '';
	foreach (fetch_timezone() AS $optionvalue => $timezonephrase)
	{
		$optiontitle = $vbphrase["$timezonephrase"];
		$optionselected = iif($optionvalue == $eventinfo['utc'], HTML_SELECTED, '');
		eval('$timezoneoptions .= "' . fetch_template('option') . '";');
	}

	if (($pos = strpos($vboptions['timeformat'], 'H')) !== false)
	{
		$show['24hour'] = true;
		$fromtime[3] = intval($fromtime[3]);
		$totime[3] = intval($totime[3]);
		$from_hourselected["$fromtime[3]"] = HTML_SELECTED;
		$from_minuteselected["$fromtime[1]"] = HTML_SELECTED;
		$to_hourselected["$totime[3]"] = HTML_SELECTED;
		$to_minuteselected["$totime[1]"] = HTML_SELECTED;
	}
	else
	{
		$show['24hour'] = false;
		$from_hourselected["$fromtime[0]"] = HTML_SELECTED;
		$from_minuteselected["$fromtime[1]"] = HTML_SELECTED;
		$from_ampmselected["$fromtime[2]"] = HTML_SELECTED;

		$to_hourselected["$totime[0]"] = HTML_SELECTED;
		$to_minuteselected["$totime[1]"] = HTML_SELECTED;
		$to_ampmselected["$totime[2]"] = HTML_SELECTED;
	}


	$from_day = $fromdate[1];
	$from_monthselected["$fromdate[0]"] = HTML_SELECTED;
	$from_yearselected["$fromdate[2]"] = HTML_SELECTED;

	$to_day = $todate[1];
	$to_monthselected["$todate[0]"] = HTML_SELECTED;
	$to_yearselected["$todate[2]"] = HTML_SELECTED;

	$from_yearbits = '';
	$to_yearbits = '';
	for ($gyear = $calendarinfo['startyear']; $gyear <= $calendarinfo['endyear']; $gyear++)
	{
		$from_yearbits .= "\t\t<option value=\"$gyear\" $from_yearselected[$gyear]>$gyear</option>";
		$to_yearbits .= "\t\t<option value=\"$gyear\" $to_yearselected[$gyear]>$gyear</option>";
	}

	// Do custom fields

	$eventcustomfields = unserialize($eventinfo['customfields']);

	$customfields_required = '';
	$show['custom_required'] = false;
	$customfields_optional = '';
	$show['custom_optional'] = false;

	$customcalfields = $DB_site->query("
		SELECT *
		FROM " . TABLE_PREFIX . "calendarcustomfield
		WHERE calendarid = $calendarid
		ORDER BY calendarcustomfieldid
	");
	$bgclass = 'alt1';
	while ($custom = $DB_site->fetch_array($customcalfields))
	{
		$custom['options'] = unserialize($custom['options']);
		$customfieldname = "customfield$custom[calendarcustomfieldid]";
		$customfieldname_opt = "userfield_opt$custom[calendarcustomfieldid]";
		exec_switch_bg();
		$selectbits = '';
		$found = false;
		if (is_array($custom['options']))
		{
			$optioncount = sizeof($custom['options']);
			foreach ($custom['options'] AS $key => $val)
			{
				if ($eventcustomfields["$custom[calendarcustomfieldid]"] == $val)
				{
					$selected = HTML_SELECTED;
					$found = true;
				}
				else

				{
					$selected = '';
				}
				eval('$selectbits .= "' . fetch_template('userfield_select_option') . "\";");
			}
			$show['customoptions'] = true;
		}
		else
		{
			$optioncount = 0;
			$show['customoptions'] = false;
		}
		if ($custom['allowentry'] AND !$found)
		{
			$custom['optional'] = $eventcustomfields["$custom[calendarcustomfieldid]"];
		}
		$show['customdescription'] = iif($custom['description'], true, false);
		$show['customoptionalinput'] = iif($custom['allowentry'], true, false);

		if ($custom['required'])
		{
			$show['custom_required'] = true;
			eval('$customfields_required .= "' . fetch_template('calendar_edit_customfield') . '";');
		}
		else
		{
			$show['custom_optional'] = true;
			eval('$customfields_optional .= "' . fetch_template('calendar_edit_customfield') . '";');
		}
	}

	$recur = $eventinfo['recurring'];
	if ($recur)
	{
		exec_switch_bg();
		$dailybox = 1;
		$weeklybox = 2;
		$monthlybox1 = 2;
		$monthlybox2 = 2;
		$monthlycombo1 = 1;
		$yearlycombo2 = 1;
		$patterncheck = array($eventinfo['recurring'] => HTML_CHECKED);
		$eventtypecheck = array();

		if ($eventinfo['recurring'] == 1)
		{
			$dailybox = $eventinfo['recuroption'];
			$thistype = 'daily';
			$eventtypecheck[1] = HTML_CHECKED;
		}
		else if ($eventinfo['recurring'] == 2)
		{
			// Nothing to do for this one..
			$thistype = 'daily';
			$eventtypecheck[1] = HTML_CHECKED;
		}
		else if ($eventinfo['recurring'] == 3)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$weeklybox = $monthbit[0];
			if ($monthbit[1] & 1)
			{
				$sunboxchecked = HTML_CHECKED;
			}
			if ($monthbit[1] & 2)
			{
				$monboxchecked = HTML_CHECKED;
			}
			if ($monthbit[1] & 4)
			{
				$tueboxchecked = HTML_CHECKED;
			}
			if ($monthbit[1] & 8)
			{
				$wedboxchecked = HTML_CHECKED;
			}
			if ($monthbit[1] & 16)
			{
				$thuboxchecked = HTML_CHECKED;
			}
			if ($monthbit[1] & 32)
			{
				$friboxchecked = HTML_CHECKED;
			}
			if ($monthbit[1] & 64)
			{
				$satboxchecked = HTML_CHECKED;
			}
			$thistype = 'weekly';
			$eventtypecheck[2] = HTML_CHECKED;
		}
		else if ($eventinfo['recurring'] == 4)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$monthlycombo1 = $monthbit[0];

			$monthlybox1 = $monthbit[1];
			$thistype = 'monthly';
			$eventtypecheck[3] = HTML_CHECKED;
		}
		else if ($eventinfo['recurring'] == 5)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$monthlycombo2["$monthbit[0]"] = HTML_SELECTED;
			$monthlycombo3["$monthbit[1]"] = HTML_SELECTED;
			$monthlybox2 = $monthbit[2];
			$thistype = 'monthly';
			$eventtypecheck[3] = HTML_CHECKED;
		}
		else if ($eventinfo['recurring'] == 6)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$yearlycombo1["$monthbit[0]"] = HTML_SELECTED;
			$yearlycombo2 = $monthbit[1];
			$thistype = 'yearly';
			$eventtypecheck[4] = HTML_CHECKED;
		}
		else if ($eventinfo['recurring'] == 7)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$yearlycombo3["$monthbit[0]"] = HTML_SELECTED;
			$yearlycombo4["$monthbit[1]"] = HTML_SELECTED;
			$yearlycombo5["$monthbit[2]"] = HTML_SELECTED;
			$thistype = 'yearly';
			$eventtypecheck[4] = HTML_CHECKED;
		}
		eval ('$recurrence = "' . fetch_template('calendar_edit_recurrence') . '";');
		$type = 'recur';
	}
	else if ($eventinfo['dateline_to'] == 0)
	{
		$type = 'single';
	}

	$show['todate'] = iif($type == 'single', false, true);
	$show['deleteoption'] = true;

	$class = array();
	exec_switch_bg();
	$class['event'] = $bgclass;
	exec_switch_bg();
	$class['options'] = $bgclass;

	// Make Rest of Nav Bar
	if ($validdate)
	{
		$navbits["calendar.php?$session[sessionurl]do=getinfo&amp;c=$calendarid&amp;day=$year-$month-$day"] = vbgmdate($vboptions['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
	}
	$navbits[''] = $eventinfo['title'];

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	construct_edit_toolbar($eventinfo['event'], 0, 'calendar', $calendarinfo['allowsmilies']);

	eval('print_output("' . fetch_template('calendar_edit') . '");');
}

// ############################################################################
// ################################# ADD EVENT ################################
// ############################################################################

if ($_REQUEST['do'] == 'add')
{
	if (!($bbuserinfo['calendarpermissions']["$calendarid"] & CANPOSTEVENT))
	{
		print_no_permission();
	}

	globalize($_REQUEST, array('type' => STR, 'day' => STR));

	// Make sure $type is only 'recur' or 'single', else set it blank
	$type = iif($type == 'recur' OR $type == 'single', $type);
	$eventid = 0;

	if ($calendarinfo['allowsmilies'] == 1)
	{
		eval('$disablesmiliesoption = "' . fetch_template('newpost_disablesmiliesoption') . '";');
	}

	$customfields_required = '';
	$show['custom_required'] = false;
	$customfields_optional = '';
	$show['custom_optional'] = false;

	$customcalfields = $DB_site->query("
		SELECT *
		FROM " . TABLE_PREFIX . "calendarcustomfield
		WHERE calendarid = $calendarid
		ORDER BY calendarcustomfieldid
	");
	$bgclass = 'alt1';
	while ($custom = $DB_site->fetch_array($customcalfields))
	{
		$custom['options'] = unserialize($custom['options']);
		$customfieldname = "customfield$custom[calendarcustomfieldid]";
		$customfieldname_opt = "userfield_opt$custom[calendarcustomfieldid]";
		exec_switch_bg();
		$selectbits = '';
		if (is_array($custom['options']))
		{
			$optioncount = sizeof($custom['options']);
			foreach ($custom['options'] AS $key => $val)
			{
				eval('$selectbits .= "' . fetch_template('userfield_select_option') . "\";");
			}
		}
		else
		{
			$optioncount = 0;
		}
		$show['customdescription'] = iif($custom['description'], true, false);
		$show['customoptionalinput'] = iif($custom['allowentry'], true, false);
		$show['customoptions'] = iif(is_array($custom['options']), true, false);

		if ($custom['required'])
		{
			$show['custom_required'] = true;
			eval('$customfields_required .= "' . fetch_template('calendar_edit_customfield') . '";');
		}
		else
		{
			$show['custom_optional'] = true;
			eval('$customfields_optional .= "' . fetch_template('calendar_edit_customfield') . '";');
		}
	}

	$calrules['allowbbcode'] = $calendarinfo['allowbbcode'];
	$calrules['allowimages'] = $calendarinfo['allowimgcode'];
	$calrules['allowhtml'] = $calendarinfo['allowhtml'];
	$calrules['allowsmilies'] = $calendarinfo['allowsmilies'];

	$bbcodeon = iif($calrules['allowbbcode'], $vbphrase['on'], $vbphrase['off']);
	$imgcodeon = iif($calrules['allowimages'], $vbphrase['on'], $vbphrase['off']);
	$htmlcodeon = iif($calrules['allowhtml'], $vbphrase['on'], $vbphrase['off']);
	$smilieson = iif($calrules['allowsmilies'], $vbphrase['on'], $vbphrase['off']);

	// only show posting code allowances in forum rules template
	$show['codeonly'] = true;

	require_once('./includes/functions_bigthree.php');
	construct_forum_rules($calrules, $permissions);

	$currentpage = urlencode("calendar.php?do=add&c=$c&type=$type");
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	if (($pos = strpos($vboptions['timeformat'], 'H')) !== false)
	{
		$show['24hour'] = true;
	}

	$fromtimeoptions = fetch_time_options(array('8', '00', 'AM', '8'), $show['24hour']);
	$totimeoptions = fetch_time_options(array('9', '00', 'AM', '9'), $show['24hour']);

	$passedday = false;
	// did a day value get passed in?
	if ($day != '')
	{
		$daybits = explode('-', $day);
		foreach ($daybits AS $key => $val)
		{
			$daybits["$key"] = intval($val);
		}
		if (checkdate($daybits[1], $daybits[2], $daybits[0]))
		{
			$to_day = $from_day = $daybits[2];
			$to_monthselected["$daybits[1]"] = $from_monthselected["$daybits[1]"] = HTML_SELECTED;
			$to_yearselected["$daybits[0]"] = $from_yearselected["$daybits[0]"] = HTML_SELECTED;
			$passedday = true;
		}
	}

	if (!$passedday)
	{
		$from_day = $today['mday'];
		$from_monthselected["$today[mon]"] = HTML_SELECTED;
		$from_yearselected["$today[year]"] = HTML_SELECTED;

		$to_day = $today['mday'];
			$to_monthselected["$today[mon]"] = HTML_SELECTED;
		$to_yearselected["$today[year]"] = HTML_SELECTED;
	}

	$from_yearbits = '';
	$to_yearbits = '';
	for ($gyear = $calendarinfo['startyear']; $gyear <= $calendarinfo['endyear']; $gyear++)
	{
		$from_yearbits .= "\t\t<option value=\"$gyear\" $from_yearselected[$gyear]>$gyear</option>";
		$to_yearbits .= "\t\t<option value=\"$gyear\" $to_yearselected[$gyear]>$gyear</option>";
	}

	// select correct timezone and build timezone options
	$timezoneoptions = '';
	foreach (fetch_timezone() AS $optionvalue => $timezonephrase)
	{
		$optiontitle = $vbphrase["$timezonephrase"];
		$optionselected = iif($optionvalue == $bbuserinfo['timezoneoffset'], HTML_SELECTED, '');
		eval('$timezoneoptions .= "' . fetch_template('option') . '";');
	}

	if ($type == 'recur')
	// Recurring Event
	{
		exec_switch_bg();
		$patterncheck = array(1 => HTML_CHECKED);
		$eventtypecheck = array(1 => HTML_CHECKED);
		$dailybox = '1';
		$weeklybox = '1';
		$monthlybox1 = '2';
		$monthlybox2 = '1';
		$monthlycombo1 = 1;
		$monthlycombo2 = array(1 => HTML_SELECTED);
		$monthlycombo3 = array(1 => HTML_SELECTED);
		$yearlycombo1 = array(1 => HTML_SELECTED);
		$yearlycombo2 = 1;
		$yearlycombo3 = array(1 => HTML_SELECTED);
		$yearlycombo4 = array(1 => HTML_SELECTED);
		$yearlycombo5 = array(1 => HTML_SELECTED);
		$thistype = 'daily';
		eval ('$recurrence .= "' . fetch_template('calendar_edit_recurrence') . '";');
	}

	$class = array();
	exec_switch_bg();
	$class['event'] = $bgclass;
	exec_switch_bg();
	$class['options'] = $bgclass;

	$show['todate'] = iif($type == 'single', false, true);
	$show['deleteoption'] = false;

	// Make Rest of Nav Bar
	$navbits[''] = $vbphrase['add_new_event'];

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	construct_edit_toolbar('', 0, 'calendar', $calendarinfo['allowsmilies']);

	eval('print_output("' . fetch_template('calendar_edit') . '");');
}

// ############################################################################
// ############################### UPDATE EVENT ###############################
// ############################################################################

if ($_POST['do'] == 'update')
{
	globalize($_POST, array(
		'title' => STR, 'message' => STR, 'parseurl' => STR, 'disablesmilies' => INT,
		'from_month' => INT, 'from_day' => INT, 'from_year' => INT,
		'from_ampm' => INT, 'from_hour' => INT, 'from_minute' => INT,
		'to_month' => INT, 'to_day' => INT, 'to_year' => INT,
		'to_ampm' => INT, 'to_hour' => INT, 'to_minute' => INT,
		'from_time' => STR, 'to_time' => STR,
		'pattern' => INT, 'dailybox' => INT, 'weeklybox' => INT,
		'sunbox', 'monbox', 'tuebox', 'wedbox', 'thubox', 'fribox', 'satbox',
		'monthlybox1' => INT, 'monthlycombo1' => INT, 'monthlybox2' => INT, 'monthlycombo2' => INT,
		'monthlycombo3' => INT, 'yearlycombo1' => INT, 'yearlycombo2' => INT, 'yearlycombo3' => INT, 'yearlycombo4' => INT,
		'yearlycombo5' => INT, 'type' => STR, 'deletepost' => STR, 'WYSIWYG_HTML' => STR, 'to_ampm' => STR, 'from_ampm' => STR, 'timezoneoffset'
	));

	if ($eventid)
	{
		if ($deletepost == 'yes')
		{
			if (!can_moderate_calendar($calendarid, 'candeleteevents'))
			{
				if ($eventinfo['userid'] != $bbuserinfo['userid'])
				{
					print_no_permission();
				}
				else if (!($bbuserinfo['calendarpermissions']["$calendarid"] & CANDELETEEVENT))
				{
					print_no_permission();
				}
			}

			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "event WHERE eventid = $eventid");
			require_once('./includes/functions_databuild.php');
			build_events();
			$url = "calendar.php?$session[sessionurl]";
			eval(print_standard_redirect('redirect_calendardeleteevent'));
		}
		else
		{
			if (!can_moderate_calendar($calendarid, 'caneditevents'))
			{
				if ($eventinfo['userid'] != $bbuserinfo['userid'])
				{
					show_nopermision();
				}
				else if (!($bbuserinfo['calendarpermissions']["$calendarid"] & CANEDITEVENT))
				{
					print_no_permission();
				}
			}
		}
	}
	else
	{
		if (!($bbuserinfo['calendarpermissions']["$calendarid"] & CANPOSTEVENT))
		{
			print_no_permission();
		}
	}

	if ($title == '')
	{
		eval(print_standard_error('error_calendarfieldmissing'));
	}

	require_once('./includes/functions_newpost.php');
	require_once('./includes/functions_misc.php');

	// unwysiwygify the incoming data
	if ($WYSIWYG_HTML != '')
	{
		require_once('./includes/functions_wysiwyg.php');
		$message = convert_wysiwyg_html_to_bbcode($WYSIWYG_HTML, $calendarinfo['allowhtml']);
	}

	if ($vboptions['postmaxchars'] AND strlen($message) > $vboptions['postmaxchars'])
	{
		eval(print_standard_error('error_toolong'));
	}

	$allowsmilies = iif($disablesmilies, 0, 1);

	if (!checkdate($from_month, $from_day, $from_year) OR ($type != 'single' AND !checkdate($to_month, $to_day, $to_year)))
	{
		eval(print_standard_error('error_calendarbaddate'));
	}

	if ($parseurl)
	{
		$message = convert_url_to_bbcode($message);
	}

	// #### Verify any custom fields
	$customcalfields = $DB_site->query("
		SELECT *
		FROM " . TABLE_PREFIX . "calendarcustomfield
		WHERE calendarid = $calendarid
		ORDER BY calendarcustomfieldid
	");
	$customfield = array();
	while ($custom = $DB_site->fetch_array($customcalfields))
	{
		$customfieldname = 'customfield' . $custom['calendarcustomfieldid'];
		$customfieldname_opt = 'userfield_opt' . $custom[calendarcustomfieldid];

		if ($custom['allowentry'] AND $_POST["$customfieldname_opt"] != '')
		{
			$option = $_POST["$customfieldname_opt"];
		}
		else
		{
			$option = $_POST["$customfieldname"];
		}

		if ($custom['required'] AND !$option)
		{
			$profilefield = array();
			$profilefield['title'] = $custom['title'];
			eval(print_standard_error('error_requiredfieldmissing'));
		}

		$custom['options'] = unserialize($custom['options']);
		unset($chosenoption);
		if (is_array($custom['options']))
		{
			foreach ($custom['options'] AS $index => $value)
			{
				if ($index == $option)
				{
					$chosenoption = $value;
					break;
				}
			}
		}
		if ($chosenoption == '' AND $custom['allowentry'])
		{
			$chosenoption = htmlspecialchars_uni($_POST["$customfieldname_opt"]);
		}
		$customfield["$custom[calendarcustomfieldid]"] = $chosenoption;
	}

	$customfields = serialize($customfield);
	// #### End Custom Fields Verification

	// make sure $timezoneoffset is a numeric value
	$timezoneoffset += 0;

	// extract the relevant info from from_time and to_time
	if ($from_time != '')
	{
		$ft = explode('_', $from_time);
		$from_hour = intval($ft[0]);
		$from_minute = intval($ft[1]);
		$from_ampm = $ft[2];
	}
	if ($to_time != '')
	{
		$tt = explode('_', $to_time);
		$to_hour = intval($tt[0]);
		$to_minute = intval($tt[1]);
		$to_ampm = $tt[2];
	}

	if ($type != 'single')
	{
		if (($pos = strpos($vboptions['timeformat'], 'H')) === false)
		{
			if ($to_ampm == 'PM')
			{
				if ($to_hour >= 1 AND $to_hour <= 11)
				{
					$to_hour += 12;
				}
			}
			else
			{
				if ($to_hour == 12)
				{
					$to_hour = 0;
				}
			}

			if ($from_ampm == 'PM')
			{
				if ($from_hour >= 1 AND $from_hour <= 11)
				{
					$from_hour += 12;
				}
			}
			else
			{
				if ($from_hour == 12)
				{
					$from_hour = 0;
				}
			}
		}

		$from_hour = $from_hour - $timezoneoffset;
		$to_hour = $to_hour - $timezoneoffset;
		$dateline_to = gmmktime($to_hour, $to_minute, 0, $to_month, $to_day, $to_year);
		$dateline_from = gmmktime($from_hour, $from_minute, 0, $from_month, $from_day, $from_year);

		if ($dateline_to < $dateline_from)
		{
			eval(print_standard_error('error_calendartodate'));
		}
		$offset = iif (!$timezoneoffset, $bbuserinfo['tzoffset'], $bbuserinfo['timezoneoffset']);
		$occurdate = vbgmdate('Y-n-j H:i:s', $dateline_from + $offset * 3600, false, false);
	}
	else // single day event
	{
		$dateline_to = 0;
		$dateline_from = gmmktime(0, 0, 0, $from_month, $from_day, $from_year);
		$occurdate = vbgmdate('Y-n-j H:i:s', $dateline_from, false, false);
	}

	if ($type == 'recur')
	{
		$checkevent = array(
			'eventid' => 1,
			'dateline_from' => $dateline_from,
			'dateline_to' => $dateline_to,
			'dateline_from_user' => $dateline_from + $offset * 3600,
			'dateline_to_user' => $dateline_to + $offset * 3600,
			'recurring' => $pattern,
			'utc' => $timezoneoffset,
		);

		$startday = gmmktime(0, 0, 0, gmdate('n', $checkevent['dateline_from_user']), gmdate('j', $checkevent['dateline_from_user']), gmdate('Y', $checkevent['dateline_from_user']));
		$endday = gmmktime(0, 0, 0, gmdate('n', $checkevent['dateline_to_user']), gmdate('j', $checkevent['dateline_to_user']), gmdate('Y', $checkevent['dateline_to_user']));

		if ($pattern == 1)
		{
			$patoptions = intval($dailybox);
		}
		else if ($pattern == 3)
		{
			if ($sunbox)
			{
				$daybit = 1;
			}
			if ($monbox)
			{
				$daybit += 2;
			}
			if ($tuebox)
			{
				$daybit += 4;
			}
			if ($wedbox)
			{
				$daybit += 8;
			}
			if ($thubox)
			{
				$daybit += 16;
			}
			if ($fribox)
			{
				$daybit += 32;
			}
			if ($satbox)
			{
				$daybit += 64;
			}
			$patoptions = intval($weeklybox) . '|' . $daybit;
		}
		else if ($pattern == 4)
		{
			$patoptions = $monthlycombo1 . '|' . intval($monthlybox1);
		}
		else if ($pattern == 5)
		{
			$patoptions = $monthlycombo2 . '|' . $monthlycombo3 . '|' . intval($monthlybox2);
		}
		else if ($pattern == 6)
		{
			$patoptions = $yearlycombo1 . '|' . $yearlycombo2;
		}
		else if ($pattern == 7)
		{
			$patoptions = $yearlycombo3 . '|' . $yearlycombo4 . '|' . $yearlycombo5;
		}
		$checkevent['recuroption'] = $patoptions;
		$foundevent = false;
		while ($startday <= $endday)
		{
			$temp = explode('-', gmdate('n-j-Y', $startday));
			if (cache_event_info($checkevent, $temp[0], $temp[1], $temp[2], 0))
			{
				$foundevent = true;
				break;
			}
			$startday += 86400;
		}
		if (!$foundevent)
		{
			eval(print_standard_error('error_calendarnorecur'));
		}
	}

	if ($vboptions['maximages'])
	{
		$parsedmessage = parse_calendar_bbcode($message, 'nonforum', 1, 1);
		if (fetch_character_count($parsedmessage, '<img') > $vboptions['maximages'])
		{
			eval(print_standard_error('error_toomanyimages'));
		}
	}

	$title = fetch_censored_text($title);
	$message = fetch_censored_text($message);
	if (!$eventid)
	{ // No Eventid == Insert Event
		// Insert new field and redirect user to calendar
		if ($query = $DB_site->query_first("
			SELECT eventid
			FROM " . TABLE_PREFIX . "event
			WHERE userid = $bbuserinfo[userid]
				AND dateline_from = $dateline_from
				AND dateline_to = $dateline_to
				AND event = '" . addslashes($message) . "'
				AND title = '" . addslashes($title) . "'
				AND calendarid = $calendarid
		"))
		{
			eval(print_standard_error('error_calendareventexists'));
		}
		else
		{
			if (!$calendarinfo['moderatenew'] OR can_moderate_calendar($calendarinfo['calendarid'], 'canmoderateevents'))
			{
				$visible = 1;
			}
			else
			{
				$visible = iif($calendarinfo['moderatenew'], 0, 1);
			}

			$DB_site->query("
				INSERT INTO " . TABLE_PREFIX . "event (calendarid, userid, event, recurring, recuroption,dateline_to,dateline_from,	title, allowsmilies, customfields, dateline, visible, utc)
				VALUES ($calendarid, $bbuserinfo[userid], '" . addslashes($message) . "', '" . addslashes($pattern) . "', '" . addslashes($patoptions) . "', '$dateline_to', '$dateline_from', '" . addslashes($title) . "', '$allowsmilies', '" . addslashes($customfields) . "', " . TIMENOW . ", $visible, '" . addslashes($timezoneoffset) . "')
			");
			$eventid = $DB_site->insert_id();

			if ($calendarinfo['neweventemail'])
			{
				$calemails = unserialize($calendarinfo['neweventemail']);
				$calendarinfo['title'] = unhtmlspecialchars($calendarinfo['title']);
				$title = unhtmlspecialchars($title);
				$eventmessage = strip_bbcode($message);
				$bbuserinfo['username'] = unhtmlspecialchars($bbuserinfo['username']); //for emails
				foreach ($calemails AS $index => $toemail)
				{
					if (trim($toemail))
					{
						eval(fetch_email_phrases('newevent', 0));
						vbmail($toemail, $subject, $message, true);
					}
				}
			}
			if ($visible)
			{
				$url = "calendar.php?$session[sessionurl]do=getinfo&amp;e=$eventid&amp;day=$occurdate";
				require_once('./includes/functions_databuild.php');
				build_events();
				eval(print_standard_redirect('redirect_calendaraddevent'));
			}
			else
			{
				$url = "calendar.php?$session[sessionurl]c=$calendarid";
				eval(print_standard_redirect('redirect_calendarmoderated'));
			}
		}
	}
	else
	{ // Update event
		// Update field and redirect user to Calendar
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "event
			SET event = '".addslashes($message) . "',
				recurring = '" . addslashes($pattern) . "',
				recuroption = '" . addslashes($patoptions) . "',
				dateline_from = $dateline_from,
				dateline_to = $dateline_to,
				title = '" . addslashes($title) . "',
				allowsmilies = $allowsmilies,
				customfields = '" . addslashes($customfields) . "',
				utc = '" . addslashes($timezoneoffset) . "'
			WHERE eventid = $eventid
		");
		require_once('./includes/functions_databuild.php');
		build_events();
		$url = "calendar.php?$session[sessionurl]do=getinfo&amp;e=$eventid&amp;day=$occurdate";
		eval(print_standard_redirect('redirect_calendarupdateevent'));
	}

}

// ############################################################################
// ######################### ADD EVENT REMINDER ###############################
// ############################################################################

if ($_REQUEST['do'] == 'addreminder')
{

	if (!$bbuserinfo['userid'])
	{
		print_no_permission();
	}

	if (!$eventinfo['eventid'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	$_REQUEST['forceredirect'] = 1;

	$DB_site->query("
		REPLACE INTO " . TABLE_PREFIX . "subscribeevent (userid, eventid)
		VALUES ($bbuserinfo[userid], $eventinfo[eventid])
	");

	$url = "calendar.php?$session[sessionurl]do=getinfo&amp;e=$eventinfo[eventid]";
	eval(print_standard_redirect('redirect_subsadd_event'));

}

// ############################################################################
// ######################## DELETE EVENT REMINDER #############################
// ############################################################################

if ($_REQUEST['do'] == 'deletereminder')
{

	if (!$bbuserinfo['userid'])
	{
		print_no_permission();
	}

	if (!$eventinfo['eventid'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	$_REQUEST['forceredirect'] = 1;

	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "subscribeevent
		WHERE userid = $bbuserinfo[userid]
			AND eventid = $eventinfo[eventid]
	");

	$type = $vbphrase['event'];
	$url = "calendar.php?$session[sessionurl]do=getinfo&amp;e=$eventinfo[eventid]";
	eval(print_standard_redirect('redirect_subsremove_event'));

}

// ############################################################################
// ######################## DELETE EVENT REMINDERS ############################
// ############################################################################

if ($_POST['do'] == 'dodeletereminder')
{

	if (!$bbuserinfo['userid'])
	{
		print_no_permission();
	}

	globalize($_POST, array('deletebox'));

	if (!is_array($deletebox))
	{
		eval(print_standard_error('error_eventsnoselected'));
	}

	$ids = '';
	foreach ($deletebox AS $eventid => $value)
	{
		$ids .= ',' . intval($eventid);
	}
	if ($ids)
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscribeevent WHERE eventid IN (0$ids) AND userid = $bbuserinfo[userid]");
	}
	$url = "calendar.php?$session[sessionurl]do=viewreminder";
	eval(print_standard_redirect('redirect_reminderdeleted'));
	exit;
}

// ############################################################################
// ######################## MANAGE EVENT REMINDERS ############################
// ############################################################################

if ($_REQUEST['do'] == 'viewreminder')
{
	if (!$bbuserinfo['userid'])
	{
		print_no_permission();
	}

	globalize($_REQUEST , array('perpage' => INT, 'pagenumber' => INT, 'sortfield', 'sortorder'));

	// look at sorting options:
	if ($sortorder != 'asc')
	{
		$sortorder = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => HTML_SELECTED);
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => HTML_SELECTED);
	}

	switch ($sortfield)
	{
		case 'username':
			$sqlsortfield = 'user.username';
			break;
		case 'title':
		case 'calendarid':
			$sqlsortfield = 'event.' . $sortfield;
			break;
		default:
			$sqlsortfield = 'event.dateline_from';
			$sortfield = 'fromdate';
	}
	$sort = array($sortfield => HTML_SELECTED);

	$eventcount = $DB_site->query_first("
		SELECT COUNT(*) AS events
		FROM " . TABLE_PREFIX . "subscribeevent AS subscribeevent
		LEFT JOIN " . TABLE_PREFIX . "event AS event ON (subscribeevent.eventid = event.eventid)
		WHERE subscribeevent.userid = $bbuserinfo[userid]
			AND event.visible = 1
	");

	$totalevents = intval($eventcount['events']); // really stupid mysql bug

	sanitize_pageresults($totalevents, $pagenumber, $perpage, 200, $vboptions['maxthreads']);

	$limitlower = ($pagenumber - 1) * $perpage + 1;
	$limitupper = ($pagenumber) * $perpage;

	if ($limitupper > $totalevents)
	{
		$limitupper = $totalevents;
		if ($limitlower > $totalevents)
		{
			$limitlower = $totalevents - $perpage;
		}
	}
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}

	$getevents = $DB_site->query("
		SELECT event.*, IF(dateline_to = 0, 1, 0) AS singleday, user.username
		FROM " . TABLE_PREFIX . "subscribeevent AS subscribeevent
		LEFT JOIN " . TABLE_PREFIX . "event AS event ON (subscribeevent.eventid = event.eventid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (event.userid = user.userid)
		WHERE subscribeevent.userid = $bbuserinfo[userid]
			AND event.visible = 1
		ORDER BY $sqlsortfield $sqlsortorder
	");

	if ($totalevents = $DB_site->num_rows($getevents))
	{
		$show['haveevents'] = true;

		while ($event = $DB_site->fetch_array($getevents))
		{
			$event['dateline_from_user'] = $event['dateline_from'] + $bbuserinfo['timezoneoffset'] * 3600;
			$event['dateline_to_user'] = $event['dateline_to'] + $bbuserinfo['timezoneoffset'] * 3600;
			$event = fetch_event_date_time($event);
			$event['preview'] = htmlspecialchars_uni(strip_bbcode(fetch_trimmed_title(strip_quotes($event['event']), 300), false, true));
			$event['calendar'] = $calendarcache["$event[calendarid]"];
			$show['singleday'] = iif($event['singleday'], true, false);
			eval('$eventbits .= "' . fetch_template('calendar_reminder_eventbit') . '";');
		}

		$DB_site->free_result($getevents);
		$sorturl = "calendar.php?$session[sessionurl]do=viewreminder&amp;pp=$perpage&amp;type=$type";
		$pagenav = construct_page_nav($totalevents, $sorturl . "&amp;sort=$sortfield" . iif(!empty($sortorder), "&amp;order=$sortorder"));
		$oppositesort = iif($sortorder == 'asc', 'desc', 'asc');
		eval('$sortarrow[' . $sortfield . '] = "' . fetch_template('forumdisplay_sortarrow') . '";');

	}
	else
	{
		$show['haveevents'] = false;
	}

	array_pop($navbits);
	$navbits[''] = $vbphrase['event_reminders'];
	$navbits = construct_navbits($navbits);

	// build the cp nav
	require_once('./includes/functions_user.php');
	construct_usercp_nav('event_reminders');

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('CALENDAR_REMINDER') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

}

eval(print_standard_error('error_invalidid'));

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11575 $
|| ####################################################################
\*======================================================================*/
?>