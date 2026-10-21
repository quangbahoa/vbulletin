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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'index');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('holiday');

// get special data templates from the datastore
$specialtemplates = array(
	'userstats',
	'birthdaycache',
	'maxloggedin',
	'iconcache',
	'eventcache',
	'mailqueue'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'FORUMHOME',
	'forumhome_event',
	'forumhome_forumbit_level1_nopost',
	'forumhome_forumbit_level1_post',
	'forumhome_forumbit_level2_nopost',
	'forumhome_forumbit_level2_post',
	'forumhome_lastpostby',
	'forumhome_loggedinuser',
	'forumhome_moderator',
	'forumhome_pmloggedin',
	'forumhome_subforumbit_nopost',
	'forumhome_subforumbit_post',
	'forumhome_subforumseparator_nopost',
	'forumhome_subforumseparator_post'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_bigthree.php');
require_once('./includes/functions_forumlist.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// get permissions to view forumhome
if (!($permissions['forumpermissions'] & CANVIEW))
{
	print_no_permission();
}

// get forumid if set, otherwise set to -1
globalize($_REQUEST, array('forumid' => INT));

if (! is_array($foruminfo))
{
	$forumid = -1;
}
else
{
	// check forum permissions
	$_permsgetter_ = 'index';
	$forumperms = fetch_permissions($forumid);
	if (!($forumperms & CANVIEW))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// draw nav bar
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $forumcache["$forumID"]['title'];
		$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
	}

	// pop the last element off the end of the $nav array so that we can show it without a link
	array_pop($navbits);

	$navbits[''] = $foruminfo['title'];
	$navbits = construct_navbits($navbits);
}

$today = vbdate('Y-m-d', TIMENOW, false, false);

// ### TODAY'S BIRTHDAYS #################################################
if ($vboptions['showbirthdays'])
{
	$birthdaystore = unserialize($datastore['birthdaycache']);
	if (!is_array($birthdaystore) OR ($today != $birthdaystore['day1'] AND $today != $birthdaystore['day2']))
	{
		// Need to update!
		require_once('./includes/functions_databuild.php');
		$birthdaystore = build_birthdays();
		DEVDEBUG('Updated Birthdays');
	}
	switch($today)
	{
		case $birthdaystore['day1']:
			$birthdays = $birthdaystore['users1'];
			break;

		case $birthdaystore['day2'];
			$birthdays = $birthdaystore['users2'];
			break;
	}
	// memory saving
	unset($birthdaystore);

	$show['birthdays'] = iif ($birthdays, true, false);
}
else
{
	$show['birthdays'] = false;
}

// ### TODAY'S EVENTS #################################################
if ($vboptions['showevents'])
{
	require_once('./includes/functions_calendar.php');

	$future = gmdate('n-j-Y' , TIMENOW + 43200 + (86400 * ($vboptions['showevents'] - 1)));
	$eventstore = unserialize($datastore['eventcache']);

	if (!is_array($eventstore) OR $future != $eventstore['date'])
	{
		// Need to update!
		require_once('./includes/functions_databuild.php');
		$eventstore = build_events();
		DEVDEBUG('Updated Events');
	}

	unset($eventstore['date']);
	$events = array();
	$eventcount = 0;
	foreach ($eventstore AS $eventid => $eventinfo)
	{
		$eventstore["$index"]['dateline_from_user'] = $eventinfo['dateline_from_user'] = $eventinfo['dateline_from'] + $bbuserinfo['timezoneoffset'] * 3600;
		$eventstore["$index"]['dateline_to_user'] = $eventinfo['dateline_to_user'] = $eventinfo['dateline_to'] + $bbuserinfo['timezoneoffset'] * 3600;
		$gettime = TIMENOW - $vboptions['hourdiff'];
		$iterations = 0;
		$todaydate = getdate($gettime);

		if ($bbuserinfo['calendarpermissions']["$eventinfo[calendarid]"] & CANVIEWCALENDAR OR ($eventinfo['holidayid'] AND $vboptions['showholidays']))
		{
			if ($eventinfo['userid'] == $bbuserinfo['userid'] OR $bbuserinfo['calendarpermissions']["$eventinfo[calendarid]"] & CANVIEWOTHERSEVENT OR ($eventinfo['holidayid'] AND $vboptions['showholidays']))
			{
				if (!$eventinfo['recurring'] AND !$vboptions['showeventtype'] AND !$eventinfo['singleday'] AND cache_event_info($eventinfo, $todaydate['mon'], $todaydate['mday'], $todaydate['year']))
				{
					$events["$eventid"][] = true;
				}
				else
				{
					while ($iterations < $vboptions['showevents'])
					{
						$addcache = false;

						$todaydate = getdate($gettime);
						if ($eventinfo['holidayid'] AND $eventinfo['recurring'] == 6)
						{
							if ($eventinfo['recuroption'] == "$todaydate[mon]|$todaydate[mday]")
							{
								$addcache = true;
							}
						}
						else if (cache_event_info($eventinfo, $todaydate['mon'], $todaydate['mday'], $todaydate['year']))
						{
							$addcache = true;
						}

						if ($addcache)
						{
							if (!$vboptions['showeventtype'])
							{
								$events["$eventid"][] = $gettime;
							}
							else
							{
								$events["$gettime"][] = $eventid;
							}
							$eventcount++;
						}

						$iterations++;
						$gettime += 86400;
					}
				}
			}
		}
	}

	if (!empty($events))
	{
		ksort($events, SORT_NUMERIC);
		foreach($events AS $index => $value)
		{
			$pastevent = 0;
			$pastcount = 0;

			$eventinfo = $eventstore["$index"];

			$comma = $eventdates = $daysevents = '';
			if (!$vboptions['showeventtype'])
			{	// Group by Event // $index = $eventid
				if (empty($eventinfo['recurring']) AND empty($eventinfo['singleday']))
				{	// ranged event -- show it from its real start and real end date
					$eventdates = construct_phrase($vbphrase['event_x_to_y'], vbdate($vboptions['dateformat'], $eventinfo['dateline_from_user'], false, true), vbdate($vboptions['dateformat'], $eventinfo['dateline_to_user'], false, true));
					$day = vbdate('Y-n-j', $eventinfo['dateline_from_user'], false, false);
				}
				else
				{
					unset($day);
					foreach($value AS $key => $dateline)
					{
						if (($dateline - 86400) == $pastevent AND !$eventinfo['holidayid'])
						{
							$pastevent = $dateline;
							$pastcount++;
							continue;
						}
						else
						{
							if ($pastcount)
							{
								$eventdates = construct_phrase($vbphrase['event_x_to_y'], $eventdates, vbdate($vboptions['dateformat'], $pastevent, false, true, false));
							}
							$pastcount = 0;
							$pastevent = $dateline;
						}
						if (!$day)
						{
							$day = vbdate('Y-n-j', $dateline, false, false, false);
						}
						$eventdates .= $comma . vbdate($vboptions['dateformat'], $dateline, false, true, false);
						$comma = ', ';
					}
					if ($pastcount)
					{
						$eventdates = construct_phrase($vbphrase['event_x_to_y'], $eventdates, vbdate($vboptions['dateformat'], $pastevent, false, true, false));
					}
				}

				if ($eventinfo['holidayid'])
				{
					$callink = "<a href=\"calendar.php?$session[sessionurl]do=getinfo&amp;day=$day\">" . $vbphrase['holiday_title_' . $eventinfo['varname']] . "</a>";
				}
				else
				{
					$callink = "<a href=\"calendar.php?$session[sessionurl]do=getinfo&amp;day=$day&amp;e=$eventinfo[eventid]&amp;c=$eventinfo[calendarid]\">$eventinfo[title]</a>";
				}
			}
			else
			{	// Group by Date
				$eventdate = vbdate($vboptions['dateformat'], $index, false, true, false);
				$day = vbdate('Y-n-j', $index, false, false);
				foreach($value AS $key => $eventid)
				{
					$eventinfo = $eventstore["$eventid"];
					if ($eventinfo['holidayid'])
					{
						$daysevents .= $comma . "<a href=\"calendar.php?$session[sessionurl]do=getinfo&amp;day=$day\">" . $vbphrase['holiday_title_' . $eventinfo['varname']] . "</a>";
					}
					else
					{
						$daysevents .= $comma . "<a href=\"calendar.php?$session[sessionurl]do=getinfo&amp;day=$day&amp;e=$eventinfo[eventid]&amp;c=$eventinfo[calendarid]\">$eventinfo[title]</a>";
					}
					$comma = ', ';
				}
			}
			eval('$upcomingevents .= "' . fetch_template('forumhome_event') . '";');
		}
		// memory saving
		unset($events, $eventstore);
	}
	$show['upcomingevents'] = iif ($upcomingevents, true, false);
	$show['todaysevents'] = iif ($vboptions['showevents'] == 1, true, false);
}
else
{
	$show['upcomingevents'] = false;
}

// ### LOGGED IN USERS #################################################
$activeusers = '';
if ($vboptions['displayloggedin'])
{
	$datecut = TIMENOW - $vboptions['cookietimeout'];
	$numbervisible = 0;
	$numberregistered = 0;
	$numberguest = 0;

	$forumusers = $DB_site->query("
		SELECT
			user.username, (user.options & $_USEROPTIONS[invisible]) AS invisible, user.usergroupid,
			session.userid, session.inforum, session.lastactivity,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
		FROM " . TABLE_PREFIX . "session AS session
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = session.userid)
		WHERE session.lastactivity > $datecut
		" . iif($vboptions['displayloggedin'] == 1, "ORDER BY username ASC") . "
	");

	if ($bbuserinfo['userid'])
	{
		// fakes the user being online for an initial page view of index.php
		$bbuserinfo['joingroupid'] = iif($bbuserinfo['displaygroupid'], $bbuserinfo['displaygroupid'], $bbuserinfo['usergroupid']);
		$userinfos = array
		(
			$bbuserinfo['userid'] => array
			(
				'userid' => $bbuserinfo['userid'],
				'username' => $bbuserinfo['username'],
				'invisible' => $bbuserinfo['invisible'],
				'inforum' => 0,
				'lastactivity' => TIMENOW,
				'usergroupid' => $bbuserinfo['usergroupid'],
				'displaygroupid' => $bbuserinfo['displaygroupid'],
			)
		);
	}
	else
	{
		$userinfos = array();
	}
	$inforum = array();

	while ($loggedin = $DB_site->fetch_array($forumusers))
	{
		$userid = $loggedin['userid'];
		if (!$userid)
		{	// Guest
			$numberguest++;
			$inforum["$loggedin[inforum]"]++;
		}
		else if (empty($userinfos["$userid"]) OR ($userinfos["$userid"]['lastactivity'] < $loggedin['lastactivity']))
		{
			$userinfos["$userid"] = $loggedin;
		}
	}

	foreach($userinfos AS $userid => $loggedin)
	{
		$numberregistered++;
		if ($userid != $bbuserinfo['userid'])
		{
			$inforum["$loggedin[inforum]"]++;
		}
		$loggedin['musername'] = fetch_musername($loggedin);

		if (fetch_online_status($loggedin))
		{
			$numbervisible++;
			eval('$activeusers .= ", ' . fetch_template('forumhome_loggedinuser') . '";');
		}
	}

	// memory saving
	unset($userinfos, $loggedin);

	$activeusers = substr($activeusers , 2); // get rid of initial comma

	$DB_site->free_result($loggedins);

	$totalonline = $numberregistered + $numberguest;
	$numberinvisible = $numberregistered - $numbervisible;

	// ### MAX LOGGEDIN USERS ################################
	$maxusers = unserialize($datastore['maxloggedin']);
	if (intval($maxusers['maxonline']) <= $totalonline)
	{
		$maxusers['maxonline'] = $totalonline;
		$maxusers['maxonlinedate'] = TIMENOW;
		build_datastore('maxloggedin', serialize($maxusers));
	}

	$recordusers = $maxusers['maxonline'];
	$recorddate = vbdate($vboptions['dateformat'], $maxusers['maxonlinedate'], true);
	$recordtime = vbdate($vboptions['timeformat'], $maxusers['maxonlinedate']);

	$show['loggedinusers'] = true;
}
else
{
	$show['loggedinusers'] = false;
}

// ### GET FORUMS & MODERATOR iCACHES ########################
cache_ordered_forums(1);
if ($vboptions['showmoderatorcolumn'])
{
	cache_moderators();
}
else
{
	$imodcache = array();
	$mod = array();
}

// define max depth for forums display based on $vboptions[forumhomedepth]
define('MAXFORUMDEPTH', $vboptions['forumhomedepth']);

$forumbits = construct_forum_bit($forumid);

// ### BOARD STATISTICS #################################################

// get total threads & posts from the forumcache
$totalthreads = 0;
$totalposts = 0;
if (is_array($forumcache))
{
	foreach ($forumcache AS $forum)
	{
		$totalthreads += $forum['threadcount'];
		$totalposts += $forum['replycount'];
	}
}
$totalthreads = vb_number_format($totalthreads);
$totalposts = vb_number_format($totalposts);

// get total members and newest member from template
$userstats = unserialize($datastore['userstats']);
$numbermembers = vb_number_format($userstats['numbermembers']);
$newusername = $userstats['newusername'];
$newuserid = $userstats['newuserid'];

// ### ALL DONE! SPIT OUT THE HTML AND LET'S GET OUTA HERE... ###

eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('FORUMHOME') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13616 $
|| ####################################################################
\*======================================================================*/
?>