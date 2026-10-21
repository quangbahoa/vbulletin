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

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'calendar');
define('CSRF_PROTECTION', true);
define('GET_EDIT_TEMPLATES', 'edit,add,manage');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'calendar',
	'holiday',
	'timezone',
	'posting',
	'user'
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'noavatarperms',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'calendarjump',
	'calendarjumpbit',
	'bbcode_code',
	'bbcode_html',
	'bbcode_php',
	'bbcode_quote',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'displayweek' => array(
		'calendar_yearly',
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
		'calendar_edit_customfield',
		'calendar_edit_recurrence',
		'calendar_manage',
		'userfield_select_option'
	),
	'viewreminder' => array(
		'CALENDAR_REMINDER',
		'calendar_reminder_eventbit',
		'USERCP_SHELL',
		'forumdisplay_sortarrow',
		'usercp_nav_folderbit',
	),
	'addreminder' => array(
		'USERCP_SHELL',
		'calendar_reminder_choosetype',
		'usercp_nav_folderbit',
	),
);

$actiontemplates['getday'] =& $actiontemplates['getinfo'];
$actiontemplates['add'] =& $actiontemplates['edit'];
$actiontemplates['displaymonth'] =& $actiontemplates['displayweek'];
$actiontemplates['none'] =& $actiontemplates['displayweek'];

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_calendar.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$serveroffset = intval(date('Z', TIMENOW)) / 3600;

$idname = $vbphrase['event'];

$vbulletin->input->clean_array_gpc('r', array(
	'calendarid' => TYPE_UINT,
	'eventid'    => TYPE_UINT,
	'holidayid'  => TYPE_UINT,
	'week'       => TYPE_UINT,
	'month'      => TYPE_UINT,
	'year'       => TYPE_UINT,
	'sb'         => TYPE_UINT,
));

($hook = vBulletinHook::fetch_hook('calendar_start')) ? eval($hook) : false;

if ($vbulletin->GPC['week'])
{
	$_REQUEST['do'] = 'displayweek';
}

if (!$vbulletin->GPC['calendarid'])
{ // Determine the first calendar we have canview access to for the default calendar
	if ($vbulletin->GPC['eventid'])
	{ // get calendarid for this event
		if ($eventinfo = $db->query_first_slave("
			SELECT event.*, user.username, IF(dateline_to = 0, 1, 0) AS singleday,
			IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
			" . ($vbulletin->userinfo['userid'] ? ", subscribeevent.eventid AS subscribed" : "") . "
			FROM " . TABLE_PREFIX . "event AS event
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = event.userid)
			" . ($vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "subscribeevent AS subscribeevent ON(subscribeevent.eventid = " . $vbulletin->GPC['eventid'] . " AND subscribeevent.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
			WHERE event.eventid = " . $vbulletin->GPC['eventid']))
		{
			$vbulletin->GPC['calendarid'] =& $eventinfo['calendarid'];
			if (!$vbulletin->GPC['calendarid'])
			{
				foreach ($calendarcache AS $index => $value)
				{
					if ($vbulletin->userinfo['calendarpermissions']["$index"] & $vbulletin->bf_ugp_calendarpermissions['canviewcalendar'])
					{
						$vbulletin->GPC['calendarid'] = $index;
						$addcalendarid = $index;
						break;
					}
				}
			}
			if (!($vbulletin->userinfo['calendarpermissions']["{$vbulletin->GPC['calendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['canviewcalendar']))
			{
				print_no_permission();
			}
			if (!$eventinfo['visible'])
			{
				eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
			}

			$offset = intval($eventinfo['dst'] ? $vbulletin->userinfo['timezoneoffset'] : $vbulletin->userinfo['tzoffset']);

			$eventinfo['dateline_from_user'] = $eventinfo['dateline_from'] + $offset * 3600;
			$eventinfo['dateline_to_user'] = $eventinfo['dateline_to'] + $offset * 3600;
			fetch_musername($eventinfo);
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}
	else
	{
		foreach ($calendarcache AS $index => $value)
		{
			if ($vbulletin->userinfo['calendarpermissions']["$index"] & $vbulletin->bf_ugp_calendarpermissions['canviewcalendar'])
			{
				$vbulletin->GPC['calendarid'] = $index;
				$addcalendarid = $index;
				break;
			}
		}
		if (!$vbulletin->GPC['calendarid'])
		{
			if (sizeof($calendarcache) == 0)
			{
				eval(standard_error(fetch_error('nocalendars')));
			}
			else
			{
				print_no_permission();
			}
		}
	}
}
else if (!($vbulletin->userinfo['calendarpermissions']["{$vbulletin->GPC['calendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['canviewcalendar']))
{
	print_no_permission();
}
else if ($vbulletin->GPC['eventid'])
{
	if ($eventinfo = $db->query_first_slave("
		SELECT event.*, user.username, IF(dateline_to = 0, 1, 0) AS singleday,
		IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
		" . ($vbulletin->userinfo['userid'] ? ", subscribeevent.eventid AS subscribed" : "") . "
		FROM " . TABLE_PREFIX . "event AS event
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = event.userid)
		" . ($vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "subscribeevent AS subscribeevent ON(subscribeevent.eventid = " . $vbulletin->GPC['eventid'] . " AND subscribeevent.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
		WHERE event.eventid = " . $vbulletin->GPC['eventid']))
	{
		if (!$eventinfo['visible'])
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}

		$offset = intval($eventinfo['dst'] ? $vbulletin->userinfo['timezoneoffset'] : $vbulletin->userinfo['tzoffset']);
		$eventinfo['dateline_from_user'] = $eventinfo['dateline_from'] + $offset * 3600;
		$eventinfo['dateline_to_user'] = $eventinfo['dateline_to'] + $offset * 3600;
		fetch_musername($eventinfo);
	}
	else
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}
}

if ($vbulletin->GPC['holidayid']) // $holidayid > 0 ?
{
	if ($eventinfo = $db->query_first_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "holiday AS holiday
		WHERE holidayid = " . $vbulletin->GPC['holidayid'])
	)
	{
		$eventinfo['visible'] = 1;
		$eventinfo['holiday'] = 1;
		$eventinfo['title'] = $vbphrase['holiday' . $eventinfo['holidayid'] . '_title'];
		$eventinfo['event'] = $vbphrase['holiday' . $eventinfo['holidayid'] . '_desc'];
	}
	else
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}
}

if ($eventinfo['eventid'] AND $eventinfo['userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['calendarpermissions']["$eventinfo[calendarid]"] & $vbulletin->bf_ugp_calendarpermissions['canviewothersevent']))
{
	print_no_permission();
}

$calendarinfo = verify_id('calendar', $vbulletin->GPC['calendarid'], 1, 1);
$getoptions = convert_bits_to_array($calendarinfo['options'], $_CALENDAROPTIONS);
$calendarinfo = array_merge($calendarinfo, $getoptions);
$geteaster = convert_bits_to_array($calendarinfo['holidays'], $_CALENDARHOLIDAYS);
$calendarinfo = array_merge($calendarinfo, $geteaster);
$calendarid =& $calendarinfo['calendarid'];

$calview = htmlspecialchars_uni(fetch_bbarray_cookie('calendar', 'calview' . $calendarinfo['calendarid']));
$calmonth = intval(fetch_bbarray_cookie('calendar', 'calmonth'));
$calyear = intval(fetch_bbarray_cookie('calendar', 'calyear'));

if (empty($_REQUEST['do']))
{
	$defaultview = ((!empty($calendarinfo['weekly'])) ? 'displayweek' : ((!empty($calendarinfo['yearly'])) ? 'displayyear' : 'displaymonth'));
	$_REQUEST['do'] = !empty($calview) ? $calview : $defaultview;
}

if ($vbulletin->GPC['sb'])
{
	// Allow showbirthdays to be turned on if they are off -- mainly for the birthday link from the front page
	$calendarinfo['showbirthdays'] = true;
}

// chande the start of week for invalid values or guests (which are currently forced to 1, Sunday)
if ($vbulletin->userinfo['startofweek'] > 7 OR $vbulletin->userinfo['startofweek'] < 1 OR $vbulletin->userinfo['userid'] == 0)
{
	$vbulletin->userinfo['startofweek'] = $calendarinfo['startofweek'];
}

// get decent textarea size for user's browser
require_once(DIR . '/includes/functions_editor.php');
$textareacols = fetch_textarea_width();

// Make first part of Calendar Nav Bar
$navbits = array('calendar.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['calendar']);

// Make second part of calendar nav... link if needed
if (in_array($_REQUEST['do'], array('displayweek', 'displaymonth', 'displayyear')))
{
	$navbits[''] = $calendarinfo['title'];
}
else
{
	$navbits['calendar.php?' . $vbulletin->session->vars['sessionurl'] . "c={$calendarinfo[calendarid]}"] = $calendarinfo['title'];
}

$today = getdate(TIMENOW - $vbulletin->options['hourdiff']);
$today['month'] = $vbphrase[strtolower($today['month'])];

if (!$vbulletin->GPC['year'])
{
	if (!empty($calyear))
	{
		$vbulletin->GPC['year'] = $calyear;
	}
	else
	{
		$vbulletin->GPC['year'] = $today['year'];
		set_bbarray_cookie('calendar', 'calyear', $today['year']);

	}
}
else
{
	if ($vbulletin->GPC['year'] < 1970 OR $vbulletin->GPC['year'] > 2037)
	{
		$vbulletin->GPC['year'] = $today['year'];
	}
	set_bbarray_cookie('calendar', 'calyear', $vbulletin->GPC['year']);
}

if (!$vbulletin->GPC['month'])
{
	if (!empty($calmonth))
	{
		$vbulletin->GPC['month'] = $calmonth;
	}
	else
	{
		$vbulletin->GPC['month'] = $today['mon'];
		set_bbarray_cookie('calendar', 'calmonth', $today['mon']);
	}
}
else
{
	if ($vbulletin->GPC['month'] < 1 OR $vbulletin->GPC['month'] > 12)
	{
		$vbulletin->GPC['month'] = $today['mon'];
	}
	set_bbarray_cookie('calendar', 'calmonth', $vbulletin->GPC['month']);
}

if ($calendarinfo['startyear'])
{
	if ($vbulletin->GPC['year'] < $calendarinfo['startyear'] OR $vbulletin->GPC['year'] > $calendarinfo['endyear'])
	{
		if ($calendarinfo['startyear'] > $today['year'])
		{
			$vbulletin->GPC['year'] = $calendarinfo['startyear'];
			$vbulletin->GPC['month'] = 1;
		}
		else
		{
			$vbulletin->GPC['year'] = $calendarinfo['endyear'];
			$vbulletin->GPC['month'] = 12;
		}
		set_bbarray_cookie('calendar', 'calyear', $vbulletin->GPC['year']);
		set_bbarray_cookie('calendar', 'calmonth', $vbulletin->GPC['month']);
	}
}

if ($vbulletin->GPC['month'] >= 1 AND $vbulletin->GPC['month'] <= 9)
{
	$doublemonth = "0{$vbulletin->GPC['month']}";
}
else
{
	$doublemonth = $vbulletin->GPC['month'];
}

// For calendarjump
$monthselected["{$vbulletin->GPC['month']}"] = 'selected="selected"';

($hook = vBulletinHook::fetch_hook('calendar_start2')) ? eval($hook) : false;

// ############################################################################
// ############################### MONTHLY VIEW ###############################

if ($_REQUEST['do'] == 'displaymonth')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'day' => TYPE_UINT
	));

	($hook = vBulletinHook::fetch_hook('calendar_displaymonth_start')) ? eval($hook) : false;

	$show['weeklyview'] = false;
	$show['monthlyview'] = true;
	$show['yearlyview'] = false;

	$usertoday = array(
		'firstday' => gmdate('w', gmmktime(0, 0, 0, $month, 1, $year)),
		'day' => $vbulletin->GPC['day'],
		'month' => $vbulletin->GPC['month'],
		'year' => $vbulletin->GPC['year'],
	);

	// Make Nav Bar #####################################################################
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	$usertodayprev = $usertoday;
	$usertodaynext = $usertoday;
	$eventrange = array();

	if ($vbulletin->GPC['month'] == 1)
	{
		$usertodayprev['month'] = 12;
		$usertodayprev['year'] = $vbulletin->GPC['year'] - 1;
		$usertodayprev['firstday'] = gmdate('w', gmmktime(0, 0, 0, 12, 1, $vbulletin->GPC['year'] - 1));
		$eventrange['frommonth'] = 12;
		$eventrange['fromyear'] = $vbulletin->GPC['year'] - 1;
	}
	else
	{
		$usertodayprev['month'] = $vbulletin->GPC['month'] - 1;
		$usertodayprev['year'] = $vbulletin->GPC['year'];
		$usertodayprev['firstday'] = gmdate('w', gmmktime(0, 0, 0, $vbulletin->GPC['month'] - 1, 1, $vbulletin->GPC['year']));
		$eventrange['frommonth'] = $vbulletin->GPC['month'] - 1;
		$eventrange['fromyear']= $vbulletin->GPC['year'];
	}

	if ($vbulletin->GPC['month'] == 12)
	{
		$usertodaynext['month'] = 1;
		$usertodaynext['year'] = $vbulletin->GPC['year'] + 1;
		$usertodaynext['firstday'] = gmdate('w', gmmktime(0, 0, 0, 1, 1, $vbulletin->GPC['year'] + 1));
		$eventrange['nextmonth'] = 1;
		$eventrange['nextyear'] = $vbulletin->GPC['year'] + 1;
	}
	else
	{
		$usertodaynext['month'] = $vbulletin->GPC['month'] + 1;
		$usertodaynext['year'] = $vbulletin->GPC['year'];
		$usertodaynext['firstday'] = gmdate('w', gmmktime(0, 0, 0, $vbulletin->GPC['month'] + 1, 1, $vbulletin->GPC['year']));
		$eventrange['nextmonth'] = $vbulletin->GPC['month'] + 1;
		$eventrange['nextyear'] = $vbulletin->GPC['year'];
	}

	$birthdaycache = cache_birthdays();
	$eventcache = cache_events($eventrange);

	if ($vbulletin->GPC['month'] == 1 AND $vbulletin->GPC['year'] == 1970)
	{
		$prevmonth = '';
	}
	else
	{
		$prevmonth = construct_calendar_output($today, $usertodayprev, $calendarinfo);
	}
	$calendarbits = construct_calendar_output($today, $usertoday, $calendarinfo, 1);
	if ($vbulletin->GPC['month'] == 12 AND $vbulletin->GPC['year'] == 2037)
	{
		$nextmonth = '';
	}
	else
	{
		$nextmonth = construct_calendar_output($today, $usertodaynext, $calendarinfo);
	}

	$monthname = $vbphrase[strtolower(gmdate('F', gmmktime(0, 0, 0, $vbulletin->GPC['month'], 1, $vbulletin->GPC['year'])))];

	$calendarjump = construct_calendar_jump($calendarinfo['calendarid'], $vbulletin->GPC['month'], $vbulletin->GPC['year']);

	if ($calview != 'displaymonth')
	{
		set_bbarray_cookie('calendar', 'calview' . $calendarinfo['calendarid'], 'displaymonth');
	}

	($hook = vBulletinHook::fetch_hook('calendar_displaymonth_complete')) ? eval($hook) : false;

	eval('$HTML = "' . fetch_template('calendar_monthly') . '";');
	eval('print_output("' . fetch_template('CALENDAR') . '");');

}

// ############################################################################
// ############################### WEEKLY VIEW ################################
// ############################################################################

if ($_REQUEST['do'] == 'displayweek')
{
	($hook = vBulletinHook::fetch_hook('calendar_displayweek_start')) ? eval($hook) : false;

	$show['weeklyview'] = true;
	$show['monthlyview'] = false;
	$show['yearlyview'] = false;

	if ($vbulletin->GPC['week'])
	{
		if ($vbulletin->GPC['week'] < 259200)
		{
			$vbulletin->GPC['week'] = 259200;
		}
		else if ($vbulletin->GPC['week'] > 2145484800)
		{
			$vbulletin->GPC['week'] = 2145484800;
		}
		$prevweek = $vbulletin->GPC['week'] - 604800;
		$nextweek = $vbulletin->GPC['week'] + 604800;
	}
	else
	{
		$firstday = gmdate('w', gmmktime(0, 0, 0, 1, 1, $vbulletin->GPC['year'])) + 1;
		if ($vbulletin->userinfo['startofweek'] <= $firstday)
		{
			$offset = -1 * ($firstday - $vbulletin->userinfo['startofweek'] - 1);
		}
		else
		{ // $firstday < Start Of Week
			$offset = ($firstday + 6) * -1 + $vbulletin->userinfo['startofweek'];
		}
		if ($vbulletin->GPC['month'] == $today['mon'] AND $vbulletin->GPC['year'] == $today['year'])
		{
			$todaystamp = gmmktime(0, 0, 0, $vbulletin->GPC['month'], $today['mday'], $vbulletin->GPC['year']);
		}
		else
		{
			$todaystamp = gmmktime(0, 0, 0, $vbulletin->GPC['month'], 1, $vbulletin->GPC['year']);
		}

		while (true)
		{
			$prevweek = gmmktime(0, 0, 0, 1, $offset - 7, $vbulletin->GPC['year']);
			$vbulletin->GPC['week'] = gmmktime(0, 0, 0, 1, $offset, $vbulletin->GPC['year']);
			$nextweek = gmmktime(0, 0, 0, 1, $offset + 7, $vbulletin->GPC['year']);
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

	$day1 = gmdate('n-j-Y', $vbulletin->GPC['week']);
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

	$doublemonth1 = $day1[0] < 10 ? '0' . $day1[0] : $day1[0];
	$doublemonth2 = $day7[0] < 10 ? '0' . $day7[0] : $day7[0];
	$birthdaycache = cache_birthdays(1);
	$eventcache = cache_events($eventrange);

	$weekrange = array();
	$weekrange['start'] = gmmktime(0, 0, 0, $day1[0], $day1[1], $day1[2]);
	$weekrange['end'] = gmmktime(0, 0, 0, $day7[0], $day7[1], $day7[2]);
	$month1 = construct_calendar_output($today, $usertoday1, $calendarinfo, 0, $weekrange);
	if (is_array($usertoday2) AND $vbulletin->GPC['week'] != 2145484800)
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
					if ($weekyear > $userday[2] AND $userday[2] != '0000' AND $value['showbirthday'] == 2)
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

			require_once(DIR . '/includes/functions_misc.php');

			unset($userevents);
			$show['events'] = false;
			if (is_array($eventcache))
			{
				$eventarray = cache_events_day($month, $weekday, $weekyear);

				foreach ($eventarray AS $index => $value)
				{
					$show['holiday'] = !empty($value['holidayid']) ? true : false;
					$eventid = $value['eventid'];
					$holidayid = $value['holidayid'];

					$allday = false;
					$eventtitle =  htmlspecialchars_uni($value['title']);
					$year = gmdate('Y', $daystamp);
					$month = gmdate('n', $daystamp);
					$day = gmdate('j', $daystamp);
					if (!$value['singleday'])
					{
						$fromtime = vbgmdate($vbulletin->options['timeformat'], $value['dateline_from_user']);
						$totime = vbgmdate($vbulletin->options['timeformat'], $value['dateline_to_user']);
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
										$totime = vbgmdate($vbulletin->options['timeformat'], 946771200);
									}
								}
							}
							else if ($eventlastday == $daystamp)
							{
								$fromtime = vbgmdate($vbulletin->options['timeformat'], 946771200);
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
					$issubscribed = !empty($value['subscribed']) ? true : false;
					$show['events'] = true;

					($hook = vBulletinHook::fetch_hook('calendar_displayweek_event')) ? eval($hook) : false;

					eval ('$userevents .= "' . fetch_template('calendar_weekly_event') . '";');
				}
			}

			$month = gmdate('n', $daystamp);

			if (!empty($eastercache["$month-$weekday-$weekyear"]))
			{
				$show['events'] = true;
				$show['holiday'] = true;
				$eventtotal++;
				$eventtitle =& $eastercache["$month-$weekday-$weekyear"]['title'];
				eval ('$userevents .= "' . fetch_template('calendar_weekly_event') . '";');
				unset($holidayid);
				$show['holiday'] = false;
			}

			$show['highlighttoday'] = ("$today[year]-$today[mon]-$today[mday]" == "$weekyear-$month-$weekday");

			eval('$weekbits .= "' . fetch_template('calendar_weekly_day') . '";');
			$lastmonth = $weekmonth;
		}
		$daystamp = gmmktime(0, 0, 0, $day1['0'], ++$day1['1'], $day1['2']);
	}

	// Make Nav Bar #####################################################################
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	if ($calview != 'displayweek')
	{
		set_bbarray_cookie('calendar', 'calview' . $calendarinfo['calendarid'], 'displayweek');
	}

	$calendarjump = construct_calendar_jump($calendarinfo['calendarid'], $vbulletin->GPC['month'], $vbulletin->GPC['year']);

	($hook = vBulletinHook::fetch_hook('calendar_displayweek_complete')) ? eval($hook) : false;

	eval('$HTML = "' . fetch_template('calendar_weekly') . '";');
	eval('print_output("' . fetch_template('CALENDAR') . '");');

}

// ############################################################################
// ############################### YEARLY VIEW ################################
// ############################################################################

if ($_REQUEST['do'] == 'displayyear')
{
	($hook = vBulletinHook::fetch_hook('calendar_displayyear_start')) ? eval($hook) : false;

	$show['weeklyview'] = false;
	$show['monthlyview'] = false;
	$show['yearlyview'] = true;

	$eventrange = array('frommonth' => 1, 'fromyear' => $vbulletin->GPC['year'], 'nextmonth' => 12, 'nextyear' => $vbulletin->GPC['year']);
	$eventcache = cache_events($eventrange);

	$usertoday = array();
	$usertoday['year'] = $vbulletin->GPC['year'];

	for ($x = 1; $x <= 12; $x++)
	{
		$usertoday['month'] = $x;
		$usertoday['firstday'] = date('w', mktime(12, 0, 0, $x, 1, $vbulletin->GPC['year']));
		// build small calendar.
		$calname = 'month' . $x;
		$$calname = construct_calendar_output($today, $usertoday, $calendarinfo);
	}

	// Make Nav Bar #####################################################################
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	$calendarjump = construct_calendar_jump($calendarinfo['calendarid'], $vbulletin->GPC['month'], $vbulletin->GPC['year']);

	($hook = vBulletinHook::fetch_hook('calendar_displayyear_complete')) ? eval($hook) : false;

	eval('$HTML = "' . fetch_template('calendar_yearly') . '";');
	eval('print_output("' . fetch_template('CALENDAR') . '");');
}

// ############################################################################
// ############################### MANAGE EVENT ###############################
// ############################################################################

if ($_POST['do'] == 'manage')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'what'          => TYPE_STR,
		'newcalendarid' => TYPE_UINT,
		'dodelete'      => TYPE_BOOL,
		'day'           => TYPE_STR,
	));

	($hook = vBulletinHook::fetch_hook('calendar_manage_start')) ? eval($hook) : false;

	$getdate = explode('-', $vbulletin->GPC['day']);
	$year = intval($getdate[0]);
	$month = intval($getdate[1]);
	$day = intval($getdate[2]);

	$validdate = checkdate($month, $day, $year);

	if (!$eventinfo['eventid'])
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}

	$eventinfo['title'] = htmlspecialchars_uni($eventinfo['title']);

	if ($vbulletin->GPC['what'] == 'dodelete' AND !$vbulletin->GPC['dodelete'])
	{
		// tried to delete but didn't click the checkbox... try again.
		$vbulletin->GPC['what'] = 'delete';
	}

	$print_output = false;

	switch ($vbulletin->GPC['what'])
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
				// init event datamanager class
				$eventdata = datamanager_init('Event', $vbulletin, ERRTYPE_STANDARD);
				$eventdata->set_existing($eventinfo);
				$eventdata->delete();

				$vbulletin->url = 'calendar.php?' . $vbulletin->session->vars['sessionurl'] . "c=$calendarinfo[calendarid]";
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
					$navbits['calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=getinfo&amp;c=$calendarinfo[calendarid]&amp;day=$year-$month-$day"] = vbgmdate($vbulletin->options['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
				}
				$navbits['calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=getinfo&amp;e=$eventinfo[eventid]"] = $eventinfo['title'];
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
				if (!($vbulletin->userinfo['calendarpermissions']["{$vbulletin->GPC['newcalendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['canviewcalendar']))
				{
					print_no_permission();
				}

				// unsubscribe users who can't view the calendar that the event is now in
				$users = $db->query_read("
					SELECT user.userid, usergroupid, membergroupids, infractiongroupids, IF(options & " . $vbulletin->bf_misc_useroptions['hasaccessmask'] . ", 1, 0) AS hasaccessmask
					FROM " . TABLE_PREFIX . "subscribeevent AS subscribeevent
					INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
					WHERE eventid = $eventinfo[eventid]
				");
				$deleteuser = '0';
				while ($thisuser = $db->fetch_array($users))
				{
					cache_permissions($thisuser);
					$userperms =& $thisuser['calendarpermissions']["{$vbulletin->GPC['newcalendarid']}"];
					if (($userperms & $vbulletin->bf_ugp_calendarpermissions['canviewcalendar']) AND ($eventinfo['userid'] == $thisuser['userid'] OR ($userperms & $vbulletin->bf_ugp_calendarpermissions['canviewothersevent'])))
					{
						// don't delete
						continue;
					}
					else

					{
						$deleteuser .=  ',' . $thisuser['userid'];
					}
				}

				if ($deleteuser)
				{
					$query = "DELETE FROM " . TABLE_PREFIX . "subscribeevent WHERE eventid = $eventinfo[eventid] AND userid IN ($deleteuser)";
					$db->query_write($query);
				}

				// init event datamanager class
				$eventdata = datamanager_init('Event', $vbulletin, ERRTYPE_STANDARD);
				$eventdata->verify_datetime = false;
				$eventdata->set_existing($eventinfo);
				$eventdata->set('calendarid', $vbulletin->GPC['newcalendarid']);
				$eventdata->save();

				$vbulletin->url = 'calendar.php?' . $vbulletin->session->vars['sessionurl'] . 'c=' . $vbulletin->GPC['newcalendarid'];
				eval(print_standard_redirect('redirect_calendarmoveevent'));
			}
		}
		break;

		// move
		case 'move':
		{
			if (!can_moderate_calendar($calendarinfo['calendarid'], 'canmoveevents'))
			{
				print_no_permission();
			}
			else
			{
				$calendarbits = '';
				foreach ($calendarcache AS $lcalendarid => $title)
				{
					if (!($vbulletin->userinfo['calendarpermissions']["$lcalendarid"] & $vbulletin->bf_ugp_calendarpermissions['canviewcalendar']) OR ($lcalendarid == $eventinfo['calendarid']))
					{
						continue;
					}
					else
					{
						$optionvalue = $lcalendarid;
						$optiontitle = $title;
						eval('$calendarbits .= "' . fetch_template('option') . '";');
					}
				}
				if ($calendarbits == '')
				{
					eval(standard_error(fetch_error('calendarmove')));
				}
				else
				{
					$print_output = true;
					$show['delete'] = false;
					if ($validdate)
					{
						$navbits['calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=getinfo&amp;c=$calendarinfo[calendarid]&amp;day=$year-$month-$day"] = vbgmdate($vbulletin->options['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
					}
					$navbits['calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=getinfo&amp;e=$eventinfo[eventid]"] = $eventinfo['title'];
					$navbits[''] = $vbphrase['move_event'];
				}
			}
		}
		break;

		// edit - skip through to do=edit
		case 'edit':
		default:
		{
			$_POST['do'] = 'edit';
		}
		break;

	}

	($hook = vBulletinHook::fetch_hook('calendar_manage_complete')) ? eval($hook) : false;

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

	$vbulletin->input->clean_array_gpc('r', array(
		'day' => TYPE_STR
	));

	($hook = vBulletinHook::fetch_hook('calendar_getday_start')) ? eval($hook) : false;

	$getdate = explode('-', $vbulletin->GPC['day']);
	$year = intval($getdate[0]);
	$month = intval($getdate[1]);
	$day = intval($getdate[2]);
	$eventarray = array();

	$validdate = checkdate($month, $day, $year);

	if ($eventinfo['eventid'])
	{
		$eventarray = array($eventinfo);
	}
	else if ($validdate)
	{
		$doublemonth = $month < 10 ? '0' . $month : $month;
		$doubleday = $day < 10 ? '0' . $day : $day;

		$todaystamp = gmmktime(0, 0, 0, $month, $day, $year);

		// set date range for events to cache.
		$eventrange = array('frommonth' => $month, 'fromyear' => $year, 'nextmonth' => $month, 'nextyear' => $year);

		// cache events for this month only.
		$eventcache = cache_events($eventrange);

		if ($calendarinfo['showbirthdays'])
		{  // Load the birthdays for today

			foreach($vbulletin->usergroupcache AS $usergroupid => $usergroup)
			{
				if ($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['showbirthday'])
				{
					$ids .= ",$usergroupid";
				}
			}

			$comma = '';
			$birthday = $db->query_read_slave("
				SELECT birthday, username, userid, showbirthday
				FROM " . TABLE_PREFIX . "user
				WHERE birthday LIKE '$doublemonth-$doubleday-%' AND
					usergroupid IN (0$ids) AND
					showbirthday IN (2,3)
			");

			while ($birthdays = $db->fetch_array($birthday))
			{
				$userday = explode('-', $birthdays['birthday']);
				$username = $birthdays['username'];
				$userid = $birthdays['userid'];
				if ($year > $userday[2] AND $userday[2] != '0000' AND $birthdays['showbirthday'] == 2)
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
		$customcalfields = $db->query_read_slave("
			SELECT calendarcustomfieldid, title, options, allowentry, description
			FROM " . TABLE_PREFIX . "calendarcustomfield AS calendarcustomfield
			WHERE calendarid = $calendarinfo[calendarid]
			ORDER BY calendarcustomfieldid
		");
		$customfieldssql = array();
		while ($custom = $db->fetch_array($customcalfields))
		{
			$customfieldssql[] = $custom;
		}
	}

	$show['canmoveevent'] = can_moderate_calendar($calendarinfo['calendarid'], 'canmoveevents');
	$show['candeleteevent'] = can_moderate_calendar($calendarinfo['calendarid'], 'candeleteevents');

	require_once(DIR . '/includes/functions_misc.php'); // mainly for fetch_timezone

	foreach ($eventarray AS $index => $eventinfo)
	{
		$eventinfo = fetch_event_date_time($eventinfo);
		$holidayid = $eventinfo['holidayid'];
		$customfields = '';

		fetch_musername($eventinfo);

		if (!$holidayid)
		{
			unset($holidayid);
			$eventfields = vb_unserialize($eventinfo['customfields']);

			$bgclass = 'alt2';
			$show['customfields'] = false;

			foreach ($customfieldssql AS $index => $value)
			{
				$description = $value['description'];
				$value['options'] = vb_unserialize($value['options']);
				exec_switch_bg();
				$selectbits = '';
				$customoption = '';
				$customtitle = $value['title'];
				if (is_array($value['options']))
				{
					foreach ($value['options'] AS $key => $val)
					{
						if ($val == $eventfields["{$value['calendarcustomfieldid']}"])
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

				require_once(DIR . '/includes/functions_newpost.php');
				$customoption = parse_calendar_bbcode(convert_url_to_bbcode(unhtmlspecialchars($eventfields["{$value['calendarcustomfieldid']}"])));

				$show['customoption'] = ($customoption == '') ? false : true;
				if ($show['customoption'])
				{
					$show['customfields'] = true;
				}
				eval('$customfields .= "' . fetch_template('calendar_showeventsbit_customfield') . '";');
			}

			$show['holiday'] = false;
			// check for calendar moderator here.
			$show['caneditevent'] = true;
			if (!can_moderate_calendar($calendarinfo['calendarid'], 'caneditevents'))
			{
				if ($eventinfo['userid'] != $vbulletin->userinfo['userid'])
				{
					$show['caneditevent'] = false;
				}
				else if (!($vbulletin->userinfo['calendarpermissions']["{$calendarinfo['calendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['caneditevent']))
				{
					$show['caneditevent'] = false;
				}
			}
			$show['subscribed'] = !empty($eventinfo['subscribed']) ? true : false;
			if ($eventinfo['subscribed'])
			{
				$show['subscribelink'] = true;
			}
			else if ($vbulletin->userinfo['userid'] AND $eventinfo['dateline_to'] AND TIMENOW <= $eventinfo['dateline_to'])
			{
				$show['subscribelink'] = true;
			}
			else if ($vbulletin->userinfo['userid'] AND $eventinfo['singleday'] AND TIMENOW <= $eventinfo['dateline_from'])
			{
				$show['subscribelink'] = true;
			}
			else
			{
				$show['subscribelink'] = false;
			}
		}
		else
		{
			$show['holiday'] = true;
			$show['caneditevent'] = false;
			$show['subscribelink'] = false;
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

		$show['ignoredst'] = ($eventinfo['dst'] AND !$eventinfo['singleday']) ? true : false;
		$show['postedby'] = !empty($eventinfo['userid']) ? true : false;
		$show['singleday'] = !empty($eventinfo['singleday']) ? true : false;
		if (($show['candeleteevent'] OR $show['canmoveevent'] OR $show['caneditevent']) AND !$show['holiday'])
		{
			$show['eventoptions'] = true;
		}

		($hook = vBulletinHook::fetch_hook('calendar_getday_event')) ? eval($hook) : false;

		eval ('$caldaybits .= "' . fetch_template('calendar_showeventsbit') . '";');
	}
	unset($date2, $recurcriteria, $customfields);
	$show['subscribelink'] = false;
	$show['adjustedday'] = false;
	$show['ignoredst'] = true;
	$show['singleday'] = false;
	$show['holiday'] = false;
	$show['eventoptions'] = false;
	$show['postedby'] = false;
	$show['recuroption'] = false;

	if (!$vbulletin->GPC['eventid'])
	{
		$eventinfo = array();
		$eastercache = fetch_easter_array($year);

		if (!empty($eastercache["$month-$day-$year"]))
		{
			$eventinfo['title'] =& $eastercache["$month-$day-$year"]['title'];
			$eventinfo['event'] =& $eastercache["$month-$day-$year"]['event'];
			$show['holiday'] = true;
		}

		if ($eventinfo['title'] != '')
		{
			require_once(DIR . '/includes/functions_misc.php');
			$eventdate = vbgmdate($vbulletin->options['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
			$titlecolor = 'alt2';
			$bgclass = 'alt1';

			($hook = vBulletinHook::fetch_hook('calendar_getday_event')) ? eval($hook) : false;

			eval ('$caldaybits .= "' . fetch_template('calendar_showeventsbit') . '";');
		}
	}

	if (empty($eventarray) AND !$show['birthdays'] AND !$show['holiday'])
	{
		eval(standard_error(fetch_error('noevents')));
	}

	$monthselected = array($month => 'selected="selected"');
	$calendarjump = construct_calendar_jump($calendarinfo['calendarid'], $month, $year);

	// Make Rest of Nav Bar
	require_once(DIR . '/includes/functions_misc.php');
	if ($vbulletin->GPC['eventid'])
	{
		if ($validdate)
		{
			$navbits['calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=getinfo&amp;c=$calendarinfo[calendarid]&amp;day=$year-$month-$day"] = vbgmdate($vbulletin->options['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
		}
		$navbits[''] = $eventinfo['title'];
	}
	else
	{
		$navbits[''] = vbgmdate($vbulletin->options['dateformat'], gmmktime(0, 0, 0, $month, $day, $year));
	}

	$navbits = construct_navbits($navbits);
	eval('$navbar = "'. fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('calendar_getday_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('calendar_showevents') . '");');
}

// ############################################################################
// ################################# EDIT EVENT ###############################
// ############################################################################

if ($_POST['do'] == 'edit')
{
	($hook = vBulletinHook::fetch_hook('calendar_edit_start')) ? eval($hook) : false;

	if (!$eventinfo['eventid'])
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}

	// check for calendar moderator here.
	if (!can_moderate_calendar($calendarinfo['calendarid'], 'caneditevents'))
	{
		if ($eventinfo['userid'] != $vbulletin->userinfo['userid'])
		{
			print_no_permission();
		}
		else if (!($vbulletin->userinfo['calendarpermissions']["{$calendarinfo['calendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['caneditevent']))
		{
			print_no_permission();
		}

	}

	$checked = array('disablesmilies' => ($eventinfo['allowsmilies'] == 1 ? '' : 'checked="checked"'));

	if ($calendarinfo['allowsmilies'])
	{
		eval('$disablesmiliesoption = "' . fetch_template('newpost_disablesmiliesoption') . '";');
	}

	$calrules['allowbbcode'] = $calendarinfo['allowbbcode'];
	$calrules['allowimages'] = $calendarinfo['allowimgcode'];
	$calrules['allowhtml'] = $calendarinfo['allowhtml'];
	$calrules['allowsmilies'] = $calendarinfo['allowsmilies'];

	$bbcodeon = !empty($calrules['allowbbcode']) ? $vbphrase['on'] : $vbphrase['off'];
	$imgcodeon = !empty($calrules['allowimages']) ? $vbphrase['on'] : $vbphrase['off'];
	$htmlcodeon = !empty($calrules['allowhtml']) ? $vbphrase['on'] : $vbphrase['off'];
	$smilieson = !empty($calrules['allowsmilies']) ? $vbphrase['on'] : $vbphrase['off'];

	// only show posting code allowances in forum rules template
	$show['codeonly'] = true;

	require_once(DIR . '/includes/functions_bigthree.php');
	construct_forum_rules($calrules, $permissions);

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	$title = $eventinfo['title'];
	$message = htmlspecialchars_uni($eventinfo['event']);

	$fromdate = explode('-', gmdate('n-j-Y', $eventinfo['dateline_from'] + $eventinfo['utc'] * 3600));
	$fromtime = gmdate('g_i_A_H', $eventinfo['dateline_from'] + $eventinfo['utc'] * 3600);

	$todate = explode('-', gmdate('n-j-Y', $eventinfo['dateline_to'] + $eventinfo['utc'] * 3600));
	$totime = gmdate('g_i_A_H', $eventinfo['dateline_to'] + $eventinfo['utc'] * 3600);

	$fromtime = explode('_', $fromtime);
	$totime = explode('_', $totime);

	if (strpos($vbulletin->options['timeformat'], 'H') !== false)
	{
		$show['24hour'] = true;
	}
	else
	{
		$show['24hour'] = false;
	}

	$fromtimeoptions = fetch_time_options($fromtime, $show['24hour'], $user_from_time);
	$totimeoptions = fetch_time_options($totime, $show['24hour'], $user_to_time);

	if ($eventinfo['utc'] < 0)
	{
		$timezonesel['n' . (-$eventinfo['utc'] * 10)] = 'selected="selected"';
	}
	else
	{
		$index = $eventinfo['utc'] * 10;
		$timezonesel["$index"] = 'selected="selected"';
	}

	// select correct timezone and build timezone options
	require_once(DIR . '/includes/functions_misc.php');
	$timezoneoptions = '';
	foreach (fetch_timezone() AS $optionvalue => $timezonephrase)
	{
		$optiontitle = $vbphrase["$timezonephrase"];
		$optionselected = ($optionvalue == $eventinfo['utc'] ? 'selected="selected"' : '');
		eval('$timezoneoptions .= "' . fetch_template('option') . '";');
	}

	if (($pos = strpos($vbulletin->options['timeformat'], 'H')) !== false)
	{
		$show['24hour'] = true;
		$fromtime[3] = intval($fromtime[3]);
		$totime[3] = intval($totime[3]);
		$from_hourselected["$fromtime[3]"] = 'selected="selected"';
		$from_minuteselected["$fromtime[1]"] = 'selected="selected"';
		$to_hourselected["$totime[3]"] = 'selected="selected"';
		$to_minuteselected["$totime[1]"] = 'selected="selected"';
	}
	else
	{
		$show['24hour'] = false;
		$from_hourselected["$fromtime[0]"] = 'selected="selected"';
		$from_minuteselected["$fromtime[1]"] = 'selected="selected"';
		$from_ampmselected["$fromtime[2]"] = 'selected="selected"';

		$to_hourselected["$totime[0]"] = 'selected="selected"';
		$to_minuteselected["$totime[1]"] = 'selected="selected"';
		$to_ampmselected["$totime[2]"] = 'selected="selected"';
	}

	$from_day = $fromdate[1];
	$from_monthselected["$fromdate[0]"] = 'selected="selected"';
	$from_yearselected["$fromdate[2]"] = 'selected="selected"';

	$to_day = $todate[1];
	$to_monthselected["$todate[0]"] = 'selected="selected"';
	$to_yearselected["$todate[2]"] = 'selected="selected"';

	$from_yearbits = '';
	$to_yearbits = '';
	for ($gyear = $calendarinfo['startyear']; $gyear <= $calendarinfo['endyear']; $gyear++)
	{
		$from_yearbits .= "\t\t<option value=\"$gyear\" $from_yearselected[$gyear]>$gyear</option>";
		$to_yearbits .= "\t\t<option value=\"$gyear\" $to_yearselected[$gyear]>$gyear</option>";
	}

	// Do custom fields

	$eventcustomfields = vb_unserialize($eventinfo['customfields']);

	$customfields_required = '';
	$show['custom_required'] = false;
	$customfields_optional = '';
	$show['custom_optional'] = false;

	$customcalfields = $db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "calendarcustomfield
		WHERE calendarid = $calendarinfo[calendarid]
		ORDER BY calendarcustomfieldid
	");
	$bgclass = 'alt1';
	while ($custom = $db->fetch_array($customcalfields))
	{
		$custom['options'] = vb_unserialize($custom['options']);
		$customfieldname = "userfield[f{$custom['calendarcustomfieldid']}]";
		$customfieldname_opt = "userfield[o{$custom['calendarcustomfieldid']}]";
		exec_switch_bg();
		$selectbits = '';
		$found = false;
		if (is_array($custom['options']))
		{
			$optioncount = sizeof($custom['options']);
			foreach ($custom['options'] AS $key => $val)
			{
				if ($eventcustomfields["{$custom['calendarcustomfieldid']}"] == $val)
				{
					$selected = 'selected="selected"';
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
			$custom['optional'] = $eventcustomfields["{$custom['calendarcustomfieldid']}"];
			$custom['length'] = $custom['length'] ? $custom['length'] : 255;
		}
		$show['customdescription'] = !empty($custom['description']) ? true : false;
		$show['customoptionalinput'] = !empty($custom['allowentry']) ? true : false;

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
		$patterncheck = array($eventinfo['recurring'] => 'checked="checked"');
		$eventtypecheck = array();

		if ($eventinfo['recurring'] == 1)
		{
			$dailybox = $eventinfo['recuroption'];
			$thistype = 'daily';
			$eventtypecheck[1] = 'checked="checked"';
		}
		else if ($eventinfo['recurring'] == 2)
		{
			// Nothing to do for this one..
			$thistype = 'daily';
			$eventtypecheck[1] = 'checked="checked"';
		}
		else if ($eventinfo['recurring'] == 3)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$weeklybox = $monthbit[0];
			if ($monthbit[1] & 1)
			{
				$sunboxchecked = 'checked="checked"';
			}
			if ($monthbit[1] & 2)
			{
				$monboxchecked = 'checked="checked"';
			}
			if ($monthbit[1] & 4)
			{
				$tueboxchecked = 'checked="checked"';
			}
			if ($monthbit[1] & 8)
			{
				$wedboxchecked = 'checked="checked"';
			}
			if ($monthbit[1] & 16)
			{
				$thuboxchecked = 'checked="checked"';
			}
			if ($monthbit[1] & 32)
			{
				$friboxchecked = 'checked="checked"';
			}
			if ($monthbit[1] & 64)
			{
				$satboxchecked = 'checked="checked"';
			}
			$thistype = 'weekly';
			$eventtypecheck[2] = 'checked="checked"';
		}
		else if ($eventinfo['recurring'] == 4)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$monthlycombo1 = $monthbit[0];

			$monthlybox1 = $monthbit[1];
			$thistype = 'monthly';
			$eventtypecheck[3] = 'checked="checked"';
		}
		else if ($eventinfo['recurring'] == 5)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$monthlycombo2["$monthbit[0]"] = 'selected="selected"';
			$monthlycombo3["$monthbit[1]"] = 'selected="selected"';
			$monthlybox2 = $monthbit[2];
			$thistype = 'monthly';
			$eventtypecheck[3] = 'checked="checked"';
		}
		else if ($eventinfo['recurring'] == 6)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$yearlycombo1["$monthbit[0]"] = 'selected="selected"';
			$yearlycombo2 = $monthbit[1];
			$thistype = 'yearly';
			$eventtypecheck[4] = 'checked="checked"';
		}
		else if ($eventinfo['recurring'] == 7)
		{
			$monthbit = explode('|', $eventinfo['recuroption']);
			$yearlycombo3["$monthbit[0]"] = 'selected="selected"';
			$yearlycombo4["$monthbit[1]"] = 'selected="selected"';
			$yearlycombo5["$monthbit[2]"] = 'selected="selected"';
			$thistype = 'yearly';
			$eventtypecheck[4] = 'checked="checked"';
		}
		eval ('$recurrence = "' . fetch_template('calendar_edit_recurrence') . '";');
		$type = 'recur';
	}
	else if ($eventinfo['dateline_to'] == 0)
	{
		$type = 'single';
	}

	$show['todate'] = ($type == 'single' ? false : true);
	$show['deleteoption'] = true;
	$dstchecked = $eventinfo['dst'] ? 'checked="checked"' : '';

	$class = array();
	exec_switch_bg();
	$class['event'] = $bgclass;
	exec_switch_bg();
	$class['options'] = $bgclass;

	$navbits[''] = $eventinfo['title'];
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	$editorid = construct_edit_toolbar($eventinfo['event'], 0, 'calendar', $calendarinfo['allowsmilies']);

	$show['parseurl'] = $calendarinfo['allowbbcode'];
	$show['misc_options'] = ($show['parseurl'] OR !empty($disablesmiliesoption));
	$show['additional_options'] = ($show['misc_options'] OR $show['custom_optional']);

	($hook = vBulletinHook::fetch_hook('calendar_edit_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('calendar_edit') . '");');
}

// ############################################################################
// ################################# ADD EVENT ################################
// ############################################################################

if ($_REQUEST['do'] == 'add')
{
	if (!($vbulletin->userinfo['calendarpermissions']["{$calendarinfo['calendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['canpostevent']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'day'	=> TYPE_STR,
		'type'	=> TYPE_STR,
	));

	($hook = vBulletinHook::fetch_hook('calendar_add_start')) ? eval($hook) : false;

	// Used in edit template
	$type =& $vbulletin->GPC['type'];

	// Make sure $type is only 'recur' or 'single', else set it blank
	$type = ($type == 'recur' OR $type == 'single') ? $type : '';
	$vbulletin->GPC['eventid'] = 0;

	if ($calendarinfo['allowsmilies'] == 1)
	{
		eval('$disablesmiliesoption = "' . fetch_template('newpost_disablesmiliesoption') . '";');
	}

	$customfields_required = '';
	$show['custom_required'] = false;
	$customfields_optional = '';
	$show['custom_optional'] = false;

	$customcalfields = $db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "calendarcustomfield
		WHERE calendarid = $calendarinfo[calendarid]
		ORDER BY calendarcustomfieldid
	");
	$bgclass = 'alt1';
	while ($custom = $db->fetch_array($customcalfields))
	{
		$custom['options'] = vb_unserialize($custom['options']);
		$customfieldname = "userfield[f{$custom['calendarcustomfieldid']}]";
		$customfieldname_opt = "userfield[o{$custom['calendarcustomfieldid']}]";
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
		$show['customdescription'] = !empty($custom['description']) ? true : false;
		$show['customoptions'] = is_array($custom['options']) ? true : false;
		if ($custom['allowentry'])
		{
			$show['customoptionalinput'] = true;
			$custom['length'] = $custom['length'] ? $custom['length'] : 255;
		}
		else
		{
			$show['customoptionalinput'] = false;
		}

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

	$bbcodeon = !empty($calrules['allowbbcode']) ? $vbphrase['on'] : $vbphrase['off'];
	$imgcodeon = !empty($calrules['allowimages']) ? $vbphrase['on'] : $vbphrase['off'];
	$htmlcodeon = !empty($calrules['allowhtml']) ? $vbphrase['on'] : $vbphrase['off'];
	$smilieson = !empty($calrules['allowsmilies']) ? $vbphrase['on'] : $vbphrase['off'];

	// only show posting code allowances in forum rules template
	$show['codeonly'] = true;

	require_once(DIR . '/includes/functions_bigthree.php');
	construct_forum_rules($calrules, $permissions);

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	if (($pos = strpos($vbulletin->options['timeformat'], 'H')) !== false)
	{
		$show['24hour'] = true;
	}

	$fromtimeoptions = fetch_time_options('', $show['24hour'], $user_from_time);
	$totimeoptions = fetch_time_options('', $show['24hour'], $user_to_time);

	$passedday = false;
	// did a day value get passed in?
	if ($vbulletin->GPC['day'] != '')
	{
		$daybits = explode('-', $vbulletin->GPC['day']);
		foreach ($daybits AS $key => $val)
		{
			$daybits["$key"] = intval($val);
		}
		if (checkdate($daybits[1], $daybits[2], $daybits[0]))
		{
			$to_day = $from_day = $daybits[2];
			$to_monthselected["$daybits[1]"] = $from_monthselected["$daybits[1]"] = 'selected="selected"';
			$to_yearselected["$daybits[0]"] = $from_yearselected["$daybits[0]"] = 'selected="selected"';
			$passedday = true;
		}
	}

	if (!$passedday)
	{
		$from_day = $today['mday'];
		$from_monthselected["$today[mon]"] = 'selected="selected"';
		$from_yearselected["$today[year]"] = 'selected="selected"';

		$to_day = $today['mday'];
		$to_monthselected["$today[mon]"] = 'selected="selected"';
		$to_yearselected["$today[year]"] = 'selected="selected"';
	}

	$from_yearbits = '';
	$to_yearbits = '';
	for ($gyear = $calendarinfo['startyear']; $gyear <= $calendarinfo['endyear']; $gyear++)
	{
		$from_yearbits .= "\t\t<option value=\"$gyear\" $from_yearselected[$gyear]>$gyear</option>";
		$to_yearbits .= "\t\t<option value=\"$gyear\" $to_yearselected[$gyear]>$gyear</option>";
	}

	// select correct timezone and build timezone options
	require_once(DIR . '/includes/functions_misc.php'); // mainly for fetch_timezone
	$timezoneoptions = '';
	foreach (fetch_timezone() AS $optionvalue => $timezonephrase)
	{
		$optiontitle = $vbphrase["$timezonephrase"];
		$optionselected = ($optionvalue == $vbulletin->userinfo['timezoneoffset'] ? 'selected="selected"' : '');
		eval('$timezoneoptions .= "' . fetch_template('option') . '";');
	}

	if ($type == 'recur')
	// Recurring Event
	{
		exec_switch_bg();
		$patterncheck = array(1 => 'checked="checked"');
		$eventtypecheck = array(1 => 'checked="checked"');
		$dailybox = '1';
		$weeklybox = '1';
		$monthlybox1 = '2';
		$monthlybox2 = '1';
		$monthlycombo1 = 1;
		$monthlycombo2 = array(1 => 'selected="selected"');
		$monthlycombo3 = array(1 => 'selected="selected"');
		$yearlycombo1 = array(1 => 'selected="selected"');
		$yearlycombo2 = 1;
		$yearlycombo3 = array(1 => 'selected="selected"');
		$yearlycombo4 = array(1 => 'selected="selected"');
		$yearlycombo5 = array(1 => 'selected="selected"');
		$thistype = 'daily';
		eval ('$recurrence .= "' . fetch_template('calendar_edit_recurrence') . '";');
	}

	$class = array();
	exec_switch_bg();
	$class['event'] = $bgclass;
	exec_switch_bg();
	$class['options'] = $bgclass;

	$show['todate'] = ($type == 'single') ? false : true;
	$show['deleteoption'] = false;

	// Make Rest of Nav Bar
	$navbits[''] = $vbphrase['add_new_event'];

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	$editorid = construct_edit_toolbar('', 0, 'calendar', $calendarinfo['allowsmilies']);

	$dstchecked = 'checked="checked"';
	$show['parseurl'] = $calendarinfo['allowbbcode'];
	$show['misc_options'] = ($show['parseurl'] OR !empty($disablesmiliesoption));
	$show['additional_options'] = ($show['misc_options'] OR $show['custom_optional']);
	($hook = vBulletinHook::fetch_hook('calendar_add_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('calendar_edit') . '");');
}

// ############################################################################
// ############################### UPDATE EVENT ###############################
// ############################################################################

if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title'          => TYPE_STR,
		'message'        => TYPE_STR,
		'parseurl'       => TYPE_BOOL,
		'disablesmilies' => TYPE_BOOL,
		'deletepost'     => TYPE_BOOL,
		'deletebutton'   => TYPE_STR,
		'wysiwyg'	       => TYPE_BOOL,
		'timezoneoffset' => TYPE_NUM,
		'userfield'      => TYPE_ARRAY_STR,
		'dst'            => TYPE_UINT,
		'fromdate'       => TYPE_ARRAY_UINT,
		'fromtime'       => TYPE_ARRAY_STR,
		'todate'         => TYPE_ARRAY_INT,
		'totime'         => TYPE_ARRAY_STR,
		'recur'          => TYPE_ARRAY_UINT,
		'type'           => TYPE_STR,
		'loggedinuser'   => TYPE_INT
	));

	if ($vbulletin->GPC['loggedinuser'] != 0 AND $vbulletin->userinfo['userid'] == 0)
	{
		// User was logged in when writing post but isn't now. If we got this
		// far, guest posts are allowed, but they didn't enter a username so
		// they'll get an error. Force them to log back in.
		standard_error(fetch_error('session_timed_out_login'), '', false, 'STANDARD_ERROR_LOGIN');
	}

	($hook = vBulletinHook::fetch_hook('calendar_update_start')) ? eval($hook) : false;

	if ($eventinfo['eventid'])
	{
		if ($vbulletin->GPC['deletebutton'])
		{
			if (!$vbulletin->GPC['deletepost'])
			{
				eval(standard_error(fetch_error('please_confirm_delete')));
			}

			if (!can_moderate_calendar($calendarinfo['calendarid'], 'candeleteevents'))
			{
				if ($eventinfo['userid'] != $vbulletin->userinfo['userid'])
				{
					print_no_permission();
				}
				else if (!($vbulletin->userinfo['calendarpermissions']["{$calendarinfo['calendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['candeleteevent']))
				{
					print_no_permission();
				}
			}

			// init event datamanager class
			$eventdata = datamanager_init('Event', $vbulletin, ERRTYPE_STANDARD);
			$eventdata->set_existing($eventinfo);
			$eventdata->delete();

			$vbulletin->url = 'calendar.php?' . $vbulletin->session->vars['sessionurl_q'] . "c=$calendarinfo[calendarid]";
			eval(print_standard_redirect('redirect_calendardeleteevent'));
		}
		else
		{
			if (!can_moderate_calendar($calendarinfo['calendarid'], 'caneditevents'))
			{
				if ($eventinfo['userid'] != $vbulletin->userinfo['userid'])
				{
					print_no_permission();
				}
				else if (!($vbulletin->userinfo['calendarpermissions']["{$calendarinfo['calendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['caneditevent']))
				{
					print_no_permission();
				}
			}
		}
	}
	else
	{
		if (!($vbulletin->userinfo['calendarpermissions']["{$calendarinfo['calendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['canpostevent']))
		{
			print_no_permission();
		}
	}

	// unwysiwygify the incoming data
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$message = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $calendarinfo['allowhtml']);
	}
	else
	{
		$message = $vbulletin->GPC['message'];
	}

	// init event datamanager class
	$eventdata = datamanager_init('Event', $vbulletin, ERRTYPE_STANDARD);

	($hook = vBulletinHook::fetch_hook('calendar_update_process')) ? eval($hook) : false;

	$eventdata->set_info('parseurl', ($vbulletin->GPC['parseurl'] AND $calendarinfo['allowbbcode']));
	$eventdata->setr_info('fromtime', $vbulletin->GPC['fromtime']);
	$eventdata->setr_info('totime', $vbulletin->GPC['totime']);
	$eventdata->setr_info('fromdate', $vbulletin->GPC['fromdate']);
	$eventdata->setr_info('todate', $vbulletin->GPC['todate']);
	$eventdata->setr_info('type', $vbulletin->GPC['type']);
	$eventdata->setr_info('recur', $vbulletin->GPC['recur']);

	$eventdata->set('title', $vbulletin->GPC['title']);
	$eventdata->set('event', $message);
	$eventdata->set('allowsmilies', empty($vbulletin->GPC['disablesmilies']) ? true : false);
	$eventdata->set('utc', $vbulletin->GPC['timezoneoffset']);
	$eventdata->set('recurring', $vbulletin->GPC['recur']['pattern']);
	$eventdata->set('calendarid', $calendarinfo['calendarid']);
	$eventdata->set('dst', $vbulletin->GPC['dst']);
	$eventdata->set_userfields($vbulletin->GPC['userfield']);


	if (!$eventinfo['eventid'])
	{ // No Eventid == Insert Event

		if (can_moderate_calendar($calendarinfo['calendarid'], 'canmoderateevents'))
		{
			$eventdata->set('visible', 1);
			$visible = 1;
		}
		else if (!($vbulletin->userinfo['calendarpermissions']["{$calendarinfo['calendarid']}"] & $vbulletin->bf_ugp_calendarpermissions['isnotmoderated']) OR $calendarinfo['moderatenew'])
		{
			$eventdata->set('visible', 0);
			$visible = 0;
		}
		else
		{
			$eventdata->set('visible', 1);
			$visible = 1;
		}

		$eventdata->set('userid', $vbulletin->userinfo['userid']);
		$eventdata->set('calendarid', $calendarinfo['calendarid']);

		$eventid = $eventdata->save();

		if ($calendarinfo['neweventemail'])
		{
			$calemails = vb_unserialize($calendarinfo['neweventemail']);
			$calendarinfo['title'] = unhtmlspecialchars($calendarinfo['title']);
			$title =& $vbulletin->GPC['title'];
			$vbulletin->userinfo['username'] = unhtmlspecialchars($vbulletin->userinfo['username']); //for emails

			require_once(DIR . '/includes/class_bbcode_alt.php');
			$plaintext_parser = new vB_BbCodeParser_PlainText($vbulletin, fetch_tag_list());
			$plaintext_parser->set_parsing_language(0); // email addresses don't have a language ID
			$eventmessage = $plaintext_parser->parse($message, 'calendar');

			foreach ($calemails AS $index => $toemail)
			{
				if (trim($toemail))
				{
					eval(fetch_email_phrases('newevent', 0));
					vbmail($toemail, $subject, $message, true);
				}
			}
		}

		($hook = vBulletinHook::fetch_hook('calendar_update_complete')) ? eval($hook) : false;

		if ($visible)
		{
			$vbulletin->url = 'calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=getinfo&amp;e=$eventid&amp;day=" . $eventdata->info['occurdate'];
			eval(print_standard_redirect('redirect_calendaraddevent'));
		}
		else
		{
			$vbulletin->url = 'calendar.php?' . $vbulletin->session->vars['sessionurl'] . "c=$calendarinfo[calendarid]";
			eval(print_standard_redirect('redirect_calendarmoderated', true, true));
		}
	}
	else
	{ // Update event

		$eventdata->set_existing($eventinfo);
		$eventdata->save();

		($hook = vBulletinHook::fetch_hook('calendar_update_complete')) ? eval($hook) : false;

		$vbulletin->url = 'calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=getinfo&amp;e=$eventinfo[eventid]&amp;day=" . $eventdata->info['occurdate'];
		eval(print_standard_redirect('redirect_calendarupdateevent'));
	}

}

// ############################################################################
// ######################## DELETE EVENT REMINDER #############################
// ############################################################################

if ($_REQUEST['do'] == 'deletereminder')
{

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!$eventinfo['eventid'])
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('calendar_deletereminder')) ? eval($hook) : false;

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "subscribeevent
		WHERE userid = " . $vbulletin->userinfo['userid'] . "
			AND eventid = $eventinfo[eventid]
	");

	$vbulletin->url = 'calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=getinfo&amp;e=$eventinfo[eventid]";
	eval(print_standard_redirect('redirect_subsremove_event', true, true));

}

// ############################################################################
// ######################## DELETE EVENT REMINDERS ############################
// ############################################################################

if ($_POST['do'] == 'dostuff')
{

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'deletebox'	 => TYPE_ARRAY_BOOL,
		'what'       => TYPE_STR,
		'calendarid' => TYPE_UINT,
	));

	if (empty($vbulletin->GPC['deletebox']))
	{
		eval(standard_error(fetch_error('eventsnoselected')));
	}

	$vbulletin->url = 'calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewreminder"
		. (!empty($vbulletin->GPC['calendarid']) ? '&amp;c=' . $vbulletin->GPC['calendarid'] : '');

	$ids = '';
	foreach ($vbulletin->GPC['deletebox'] AS $id => $value)
	{
		if ($id = intval($id))
		{
			$ids .= ",$id";
		}
	}

	($hook = vBulletinHook::fetch_hook('calendar_dostuff')) ? eval($hook) : false;

	if (!empty($ids))
	{
		if ($vbulletin->GPC['what'] == 'delete')
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "subscribeevent
				WHERE subscribeeventid IN (-1$ids)
					AND userid = " . $vbulletin->userinfo['userid']
			);
			eval(print_standard_redirect('redirect_reminderdeleted'));
		}
		else
		{
			if (!empty($reminders["{$vbulletin->GPC['what']}"]))
			{ # make sure the supplied integer is a valid one
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "subscribeevent
					SET reminder = " . intval($vbulletin->GPC['what']) . "
					WHERE subscribeeventid IN (-1$ids)
						AND userid = " . $vbulletin->userinfo['userid']
				);
			}
			eval(print_standard_redirect('redirect_reminderupdated'));
		}
	}
}

// ############################################################################
// ######################## MANAGE EVENT REMINDERS ############################
// ############################################################################

if ($_REQUEST['do'] == 'viewreminder')
{
	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML,
	));

	($hook = vBulletinHook::fetch_hook('calendar_viewreminder_start')) ? eval($hook) : false;

	// These $_REQUEST values will get used in the sort template so they are assigned to normal variables
	$perpage =&  $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$sortfield =& $vbulletin->GPC['sortfield'];
	$sortorder =& $vbulletin->GPC['sortorder'];
	$calendarid =& $vbulletin->GPC['calendarid'];

	// look at sorting options:
	if ($sortorder != 'asc')
	{
		$sortorder = 'desc';
	}

	switch ($sortfield)
	{
		case 'username':
			$sqlsortfield = 'user.username';
			break;
		case 'reminder':
			$sqlsortfield = 'subscribeevent.reminder';
			break;
		case 'title':
			$sqlsortfield = 'event.' . $sortfield;
			break;
		default:
			$sqlsortfield = 'event.dateline_from';
			$sortfield = 'fromdate';
	}

	$eventcount = $db->query_first_slave("
		SELECT COUNT(*) AS events
		FROM " . TABLE_PREFIX . "subscribeevent AS subscribeevent
		LEFT JOIN " . TABLE_PREFIX . "event AS event ON (subscribeevent.eventid = event.eventid)
		WHERE subscribeevent.userid = " . $vbulletin->userinfo['userid'] . "
			AND event.visible = 1
	");

	$totalevents = intval($eventcount['events']); // really stupid mysql bug

	sanitize_pageresults($totalevents, $pagenumber, $perpage, 200, $vbulletin->options['maxthreads']);

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

	$getevents = $db->query_read_slave("
		SELECT event.*, IF(dateline_to = 0, 1, 0) AS singleday, user.username,
			subscribeevent.reminder, subscribeevent.subscribeeventid
		FROM " . TABLE_PREFIX . "subscribeevent AS subscribeevent
		LEFT JOIN " . TABLE_PREFIX . "event AS event ON (subscribeevent.eventid = event.eventid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (event.userid = user.userid)
		WHERE subscribeevent.userid = " . $vbulletin->userinfo['userid'] . "
			AND event.visible = 1
		ORDER BY $sqlsortfield $sortorder
	");

	if ($totalevents = $db->num_rows($getevents))
	{
		$show['haveevents'] = true;

		while ($event = $db->fetch_array($getevents))
		{
			if (empty($reminders["{$event['reminder']}"]))
			{
				$event['reminder'] = 3600;
			}
			$event['reminder'] = $vbphrase[$reminders[$event['reminder']]];
			$offset = intval($event['dst'] ? $vbulletin->userinfo['timezoneoffset'] : $vbulletin->userinfo['tzoffset']);

			$event['dateline_from_user'] = $event['dateline_from'] + $offset * 3600;
			$event['dateline_to_user'] = $event['dateline_to'] + $offset * 3600;
			$event['preview'] = htmlspecialchars_uni(strip_bbcode(fetch_trimmed_title(strip_quotes($event['event']), 300), false, true));
			$event = fetch_event_date_time($event);
			$event['calendar'] = $calendarcache["$event[calendarid]"];
			$show['singleday'] = !empty($event['singleday']) ? true : false;

			($hook = vBulletinHook::fetch_hook('calendar_viewreminder_event')) ? eval($hook) : false;

			eval('$eventbits .= "' . fetch_template('calendar_reminder_eventbit') . '";');
		}

		$db->free_result($getevents);
		$sorturl = 'calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewreminder&amp;pp=$perpage";
		$pagenav = construct_page_nav($pagenumber, $perpage, $totalevents, $sorturl . "&amp;sort=$sortfield" . (!empty($sortorder) ? "&amp;order=$sortorder" : ""));
		$oppositesort = ($sortorder == 'asc' ? 'desc' : 'asc');
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
	require_once(DIR . '/includes/functions_user.php');
	construct_usercp_nav('event_reminders');

	($hook = vBulletinHook::fetch_hook('calendar_viewreminder_complete')) ? eval($hook) : false;

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('CALENDAR_REMINDER') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

}

// ############################################################################
// ######################### ADD EVENT REMINDER ###############################
// ############################################################################

if ($_POST['do'] == 'doaddreminder')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'reminder' => TYPE_UINT
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!$eventinfo['eventid'])
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('calendar_doaddreminder')) ? eval($hook) : false;

	/*insert query*/
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "subscribeevent (userid, eventid, reminder)
		VALUES (" . $vbulletin->userinfo['userid'] . ", $eventinfo[eventid], " . (!empty($reminders["{$vbulletin->GPC['reminder']}"]) ? $vbulletin->GPC['reminder'] : 3600) . ")
	");

	$vbulletin->url = 'calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=getinfo&amp;e=$eventinfo[eventid]";
	eval(print_standard_redirect('redirect_subsadd_event'));
}


// ############################### start add subscription ###############################
if ($_REQUEST['do'] == 'addreminder')
{
	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!$eventinfo['eventid'])
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}

	// make title safe for display
	$eventinfo['title'] = htmlspecialchars_uni($eventinfo['title']);

	$navbits['calendar.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewreminder"] = $vbphrase['event_reminders'];

	$navbits[''] = $vbphrase['add_reminder'];
	$navbits = construct_navbits($navbits);

	require_once(DIR . '/includes/functions_user.php');
	construct_usercp_nav('event_reminders');
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('calendar_addreminder')) ? eval($hook) : false;

	$url =& $vbulletin->url;
	eval('$HTML = "' . fetch_template('calendar_reminder_choosetype') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
