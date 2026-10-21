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

// ###################### Start microtime_diff #######################
// get microtime difference between $starttime and NOW
function fetch_microtime_difference($starttime, $addtime = 0)
{
	$starttime = explode(' ', $starttime);
	$finishtime = explode(' ', microtime());
	return $finishtime[0] - $starttime[0] + $finishtime[1] - $starttime[1] + $addtime;
}

// ###################### Start mktimefix #######################
// mktime() workaround for < 1970
function mktimefix($format, $year)
{
	$search = array('%Y', '%y', 'Y', 'y');
	$replace = array($year, substr($year, 2), $year, substr($year, 2));

	return str_replace($search, $replace, $format);
}

// ###################### Start getlanguagesarray #######################
function fetch_language_titles_array($titleprefix = '', $getall = true)
{
	global $DB_site;

	$out = array();

	$languages = $DB_site->query("
		SELECT languageid, title
		FROM " . TABLE_PREFIX . "language
		" . iif($getall != true, ' WHERE userselect = 1')
	);
	while ($language = $DB_site->fetch_array($languages))
	{
		$out["$language[languageid]"] = $titleprefix . $language['title'];
	}

	asort($out);

	return $out;
}

// ###################### Start makefolderjump #######################
function construct_folder_jump($foldertype = 0, $selectedid = false, $exclusions = false, $sentfolders = '')
{
	global $vbphrase, $vboptions, $bbuserinfo, $folderid, $folderselect, $foldernames, $messagecounters, $subscribecounters, $folder;
	global $DB_site;
	// 0 indicates PMs
	// 1 indicates subscriptions
	// get all folder names (for dropdown)
	// reference with $foldernames[#] .

	$folderjump = '';
	if (!is_array($foldernames))
	{
		$foldernames = array();
	}

	switch($foldertype)
	{
		case 0:
		    // get PM folders total
		    $pmcounts = $DB_site->query("
		        SELECT COUNT(*) AS total, folderid
		        FROM " . TABLE_PREFIX . "pm AS pm
		        LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext USING(pmtextid)
		        WHERE userid = $bbuserinfo[userid]
		        GROUP BY folderid
		    ");
		    $messagecounters = array();
		    while ($pmcount = $DB_site->fetch_array($pmcounts))
		    {
		        $messagecounters["$pmcount[folderid]"] = $pmcount['total'];
    		}

			$folderfield = 'pmfolders';
			$folders = array('0' => $vbphrase['inbox'], '-1' => $vbphrase['sent_items']);
			if (!empty($bbuserinfo["$folderfield"]))
			{
				$folders = $folders + unserialize($bbuserinfo["$folderfield"]);
			}
			$counters = &$messagecounters;
			break;
		case 1:

		    // get Subscription folder totals
		    $foldertotals = $DB_site->query("
		        SELECT COUNT(*) AS total, folderid
		        FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		        LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = subscribethread.threadid)
		        LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND type = 'thread')
		        WHERE subscribethread.userid = $bbuserinfo[userid]
		            AND subscribethread.threadid = thread.threadid
		            AND thread.visible = 1 AND deletionlog.primaryid IS NULL
		        GROUP BY folderid
		    ");
		    $subscribecounters = array();
		    while ($foldertotal = $DB_site->fetch_array($foldertotals))
		    {
		        $subscribecounters["$foldertotal[folderid]"] = intval($foldertotal['total']);
    		}

			$folderfield = 'subfolders';
			$folders = iif($sentfolders, $sentfolders, unserialize($bbuserinfo["$folderfield"]));
			if (!$folders[0])
			{
				$folders[0] = $vbphrase['subscriptions'];
				asort($folders, SORT_STRING);
			}
			$counters = &$subscribecounters;
			break;
		default:
			return;
	}

	if (is_array($folders))
	{
		foreach($folders AS $_folderid => $_foldername)
		{
			if (is_array($exclusions) AND in_array($_folderid, $exclusions))
			{
				continue;
			}
			else
			{
				$foldernames["$_folderid"] = $_foldername;
				$folderjump .= "<option value=\"$_folderid\" " . iif($_folderid == $selectedid, HTML_SELECTED) . ">$_foldername" . iif(is_array($counters), ' (' . intval($counters["$_folderid"]) . iif($foldertype == 1, " $vbphrase[threads])", " $vbphrase[messages])")) . "</option>\n";
				if ($_folderid == $selectedid AND $selectedid !== false)
				{
					$folder = $_foldername;
				}
			}
		}
	}

	return $folderjump;

}

// ###################### Start vbmktime #######################
function vbmktime($hours = 0, $minutes = 0, $seconds = 0, $month = 0, $day = 0, $year = 0)
{
	global $bbuserinfo, $vboptions;

	return mktime($hours, $minutes, $seconds, $month, $day, $year) + $vboptions['hourdiff'];
}

// ###################### Start gmvbdate #####################
function vbgmdate($format, $timestamp, $doyestoday = false, $locale = true)
{
	return vbdate($format, $timestamp, $doyestoday, $locale, false, true);
}

// ###################### Start fetch period group #####################
function fetch_period_group($itemtime)
{
	global $vbphrase, $vboptions, $bbuserinfo;
	static $periods;

	// create the periods array if it does not exist
	if (empty($periods))
	{
		$daynum = -1;
		$i = 0;

		// make $bbuserinfo's startofweek setting agree with the date() function
		$weekstart = $bbuserinfo['startofweek'] - 1;

		// get the timestamp for the beginning of today, according to bbuserinfo's timezone
		$timestamp = vbmktime(0, 0, 0, vbdate('m', TIMENOW, false, false), vbdate('d', TIMENOW, false, false), vbdate('Y', TIMENOW, false, false));

		// initialize $periods array with stamp for today
		$periods = array('today' => $timestamp);

		// create periods for today, yesterday and all days until we hit the start of the week
		while ($daynum != $weekstart AND $i++ < 7)
		{
			// take away 24 hours
			$timestamp -= 86400;

			// get the number of the current day
			$daynum = vbdate('w', $timestamp, false, false);

			if ($i == 1)
			{
				$periods['yesterday'] = $timestamp;
			}
			else
			{
				$periods[strtolower(vbdate('l', $timestamp, false, false))] = $timestamp;
			}
		}

		// create periods for Last Week, 2 Weeks Ago, 3 Weeks Ago and Last Month
		$periods['last_week'] = $timestamp -= (7 * 86400);
		$periods['2_weeks_ago'] = $timestamp -= (7 * 86400);
		$periods['3_weeks_ago'] = $timestamp -= (7 * 86400);
		$periods['last_month'] = $timestamp -= (28 * 86400);
	}

	foreach ($periods AS $periodname => $periodtime)
	{
		if ($itemtime >= $periodtime)
		{
			return $periodname;
		}
	}

	return 'older';
}

// ###################### Start array2bits #######################
// takes an array and returns the bitwise value
function convert_array_to_bits(&$arry, $_FIELDNAMES, $unset = 0)
{
	$bits = 0;
	foreach($_FIELDNAMES AS $fieldname => $bitvalue)
	{
		if ($arry["$fieldname"] == 1)
		{
			$bits += $bitvalue;
		}
		if ($unset)
		{
			unset($arry["$fieldname"]);
		}
	}
	return $bits;
}

// ###################### Start bitwise #######################
// Returns 1 if the bitwise is successful, 0 other wise
// usage bitwise($perms, UG_CANMOVE);
function bitwise($value, $bitfield)
{
	// Do not change this to return true/false!

	return iif(intval($value) & $bitfield, 1, 0);
}

// ###################### Start echoarray #######################
// recursively prints out an array
function print_array($array, $title = NULL, $htmlisekey = false, $indent = '')
{
	global $vbphrase;
	if ($title === NULL)
	{
		$title = 'My Array';
	}
	if (is_array($array))
	{
		echo iif(empty($indent), "<div class=\"echoarray\">\n") . "$indent<li><b" . iif(empty($indent), ' style="font-size: larger"', '').">" . iif($htmlisekey, htmlspecialchars_uni($title), $title) . "</b><ul>\n";
		foreach ($array AS $key => $val)
		{
			if (is_array($val))
			{
				print_array($val, $key, $htmlisekey, $indent."\t");
			}
			else
			{
				echo "$indent\t<li>" . iif($htmlisekey, htmlspecialchars_uni($key), $key) . " = '<i>" . htmlspecialchars_uni($val) . "</i>'</li>\n";
			}
		}
		echo iif(empty($indent), "</div>\n") . "$indent</ul></li>\n";
	}
}

// ###################### Start getChildForums #######################
function fetch_child_forums($parentid, $return = 'STRING', $glue = ',')
{
	global $forumcache, $allforumcache;
	static $childlist;

	if (!is_array($allforumcache))
	{
		if ($return == 'ARRAY')
		{
			$childlist = array();
		}
		else
		{
			$childlist = 0;
		}
		foreach(array_keys($forumcache) AS $forumid)
		{
			$f = &$forumcache["$forumid"];
			$allforumcache["$f[parentid]"]["$forumid"] = $forumid;
		}
	}

	if (!is_array($allforumcache["$parentid"]))
	{
		return $childlist;
	}
	else
	{
		foreach($allforumcache["$parentid"] AS $forumid)
		{
			if ($return == 'ARRAY')
			{
				$childlist[] = $forumid;
			}
			else
			{
				$childlist .= "$glue$forumid";
			}
			fetch_child_forums($forumid, $return, $glue);
		}
	}

	return $childlist;
}

// ###################### Start verify autosubscribe #######################
// function to verify that the subscription type is valid
function verify_subscription_choice($choice, &$userinfo, $default = 9999, $doupdate = true)
{
	// check that the subscription choice is valid
	switch ($choice)
	{
		// the choice is good
		case 0:
		case 1:
		case 2:
		case 3:
			break;

		// check that ICQ number is valid
		case 4:
			if (!preg_match('#^[0-9\-]+$', $userinfo['icq']))
			{
				// icq number is bad
				if ($doupdate)
				{
					global $DB_site;
					$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET icq = '', autosubscribe = 1 WHERE userid = $userinfo[userid]");
				}
				$userinfo['icq'] = '';
				$userinfo['autosubscribe'] = 1;
				$choice = 1;
			}
			break;

		// all other options
		default:
			$choice = $default;
			break;
	}

	return $choice;
}

// ###################### Start countchar #######################
function fetch_character_count($string, $char)
{
	//counts number of times $char occus in $string

	return substr_count(strtolower($string), strtolower($char));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9417 $
|| ####################################################################
\*======================================================================*/
?>