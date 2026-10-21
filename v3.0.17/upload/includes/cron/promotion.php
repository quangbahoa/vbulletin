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

// $nextrun is the time difference between runs. Should be sent over from cron.php!!
// We only check the users that have been active since the lastrun to save a bit of cpu time.

$promotions = $DB_site->query("
	SELECT user.joindate, user.userid, user.membergroupids, user.posts, user.reputation,
		user.usergroupid, user.displaygroupid, user.customtitle, user.username,
		userpromotion.joinusergroupid, userpromotion.reputation AS jumpreputation, userpromotion.posts AS jumpposts,
		userpromotion.date AS jumpdate, userpromotion.type, userpromotion.strategy,
		usergroup.title, usergroup.usertitle AS ug_usertitle
	FROM " . TABLE_PREFIX . "user AS user
	LEFT JOIN " . TABLE_PREFIX . "userpromotion AS userpromotion ON (user.usergroupid = userpromotion.usergroupid)
	LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (userpromotion.joinusergroupid = usergroup.usergroupid)
	" . iif(VB_AREA != 'AdminCP', "WHERE user.lastactivity >= " . (TIMENOW - $nextrun))
);

$usertitlecache = array();
$usertitles = $DB_site->query("SELECT minposts, title FROM " . TABLE_PREFIX . "usertitle ORDER BY minposts ASC");
while ($usertitle = $DB_site->fetch_array($usertitles))
{
	$usertitlecache["$usertitle[minposts]"] = $usertitle['title'];
}

$primaryupdates = array();
$secondaryupdates = array();
$titleupdates = array();
$primarynames = array();
$secondarynames = array();
$titles = array();

while ($promotion = $DB_site->fetch_array($promotions))
{
	// First make sure user isn't already a member of the group we are joining
	if ((strpos(",$promotion[membergroupids],", ",$promotion[joinusergroupid],") === false AND $promotion['type'] == 2) OR ($promotion['usergroupid'] != $promotion['joinusergroupid'] AND $promotion['type'] == 1))
	{
		$daysregged = intval((TIMENOW - $promotion['joindate']) / 86400);
		$joinusergroupid = $promotion['joinusergroupid'];
		$titles["$joinusergroupid"] = $promotion['title'];
		$dojoin = false;
		$reputation = false;
		$posts = false;
		$joindate = false;
		// These strategies are negative reputation checking
		if (($promotion['strategy'] > 7 AND $promotion['strategy'] < 16) OR $promotion['strategy'] == 24)
		{
			if ($promotion['reputation'] < $promotion['jumpreputation'])
			{
				$reputation = true;
			}
		}
		else if ($promotion['reputation'] >= $promotion['jumpreputation'])
		{
			$reputation = true;
		}

		if ($promotion['posts'] >= $promotion['jumpposts'])
		{
			$posts = true;
		}
		if ($daysregged >= $promotion['jumpdate'])
		{
			$joindate = true;
		}

		if ($promotion['strategy'] == 17)
		{
			$dojoin = iif($posts, true, false);
		}
		else if ($promotion['strategy'] == 18)
		{
			$dojoin = iif($joindate, true, false);
		}
		else if ($promotion['strategy'] == 16 OR $promotion['strategy'] == 24)
		{
			$dojoin = iif($reputation, true, false);
		}
		else
		{
			switch($promotion['strategy'])
			{
				case 0:
				case 8:
					if ($posts AND $reputation AND $joindate)
					{
						$dojoin = true;
					}
					break;
				case 1:
				case 9:
					if ($posts OR $reputation OR $joindate)
					{
						$dojoin = true;
					}
					break;
				case 2:
				case 10:
					if (($posts AND $reputation) OR $joindate)
					{
						$dojoin = true;
					}
					break;
				case 3:
				case 11:
					if ($posts AND ($reputation OR $joindate))
					{
						$dojoin = true;
					}
					break;
				case 4:
				case 12:
					if (($posts OR $reputation) AND $joindate)
					{
						$dojoin = true;
					}
					break;
				case 5:
				case 13:
					if ($posts OR ($reputation AND $joindate))
					{
						$dojoin = true;
					}
					break;
				case 6:
				case 14:
					if ($reputation AND ($posts OR $joindate))
					{
						$dojoin = true;
					}
					break;
				case 7:
				case 15:
					if ($reputation OR ($posts AND $joindate))
					{
						$dojoin = true;
					}
			}
		}

		if ($dojoin)
		{
			if ($promotion['type'] == 1) // Primary
			{
				$primaryupdates["$joinusergroupid"] .= ",$promotion[userid]";
				$primarynames["$joinusergroupid"] .= iif($primarynames["$joinusergroupid"], ", $promotion[username]", $promotion['username']);

				if (
					(!$promotion['displaygroupid'] OR $promotion['displaygroupid'] == $promotion['usergroupid']) AND
					!$promotion['customtitle']
					)
				{
					if ($promotion['ug_usertitle'])
					{
						// update title if the user (doesn't have a special display group or if their display group is their primary group)
						// and he doesn't have a custom title already, and the new usergroup has a custom title
						$titleupdates["$promotion[userid]"] = $promotion['ug_usertitle'];
					}
					else
					{ // need to use default thats specified for X posts.
						foreach ($usertitlecache AS $minposts => $title)
						{
							if ($minposts <= $promotion['posts'])
							{
								$titleupdates["$promotion[userid]"] = $title;
							}
							else
							{
								break;
							}
						}
					}
				}
			}
			else
			{
				$secondaryupdates["$joinusergroupid"] .= ",$promotion[userid]";
				$secondarynames["$joinusergroupid"] .= iif($secondarynames["$joinusergroupid"], ", $promotion[username]", $promotion['username']);
			}
		}
	}
}

$log = '';

foreach($primaryupdates AS $joinusergroupid => $ids)
{
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user
		SET displaygroupid = IF(displaygroupid = usergroupid, $joinusergroupid, displaygroupid),
		usergroupid = $joinusergroupid
		WHERE userid IN (0$ids)
	");
	$log .= 'The following users were promoted to usergroup <b>'  . $titles["$joinusergroupid"] . '</b> :' . $primarynames[$joinusergroupid] . '<br />';
}

foreach ($titleupdates AS $userid => $newtitle)
{
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user
		SET usertitle = '" . addslashes($newtitle) . "'
		WHERE userid = $userid
	");
}

foreach($secondaryupdates AS $joinusergroupid => $ids)
{
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user
		SET membergroupids = IF(membergroupids= '', '$joinusergroupid', CONCAT(membergroupids, ',$joinusergroupid'))
		WHERE userid IN (0$ids)
	");
	// Cut down on inserts by combining the info into one log entry.
	$log .= 'The following users had usergroup <b>'  . $titles[$joinusergroupid] . '</b> added to their additional groups: ' . $secondarynames[$joinusergroupid] . '<br />';
}

if (!empty($log))
{
	log_cron_action($log, $nextitem);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9713 $
|| ####################################################################
\*======================================================================*/
?>