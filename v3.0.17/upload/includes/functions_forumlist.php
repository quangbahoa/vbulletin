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

// ###################### Start getiforumcache #######################
// pass $bbuserinfo[userid] into the function in order to return a value
// for which forums are subscribed ($arry[subscribeforumid])
function cache_ordered_forums($getcounters = 0, $getinvisibles = 0, $userid = 0)
{
	global $DB_site, $iforumcache, $forumcache, $_FORUMOPTIONS, $bbuserinfo;

	$forumfields = 'forum.forumid, lastpost, lastposter, lastthread, lastthreadid, lasticonid, threadcount, replycount';

	// query forum table to get latest lastpost/lastthread info and counters
	if ($getcounters)
	{
		// get subscribed forums too
		if ($userid)
		{
			$query = "
			SELECT subscribeforumid, $forumfields
			FROM " . TABLE_PREFIX . "forum AS forum
			LEFT JOIN " . TABLE_PREFIX . "subscribeforum AS subscribeforum ON (subscribeforum.forumid = forum.forumid AND subscribeforum.userid = $userid)
			";
		}
		// just get counters
		else
		{
			$query = "
			SELECT $forumfields
			FROM " . TABLE_PREFIX . "forum AS forum
			";
		}
	}
	// don't bother to query forum table, just use the cache
	else
	{
		// get subscribed forums
		if ($userid)
		{
			$query = "
			SELECT subscribeforumid, forumid
			FROM " . TABLE_PREFIX . "subscribeforum
			WHERE userid = $userid
			";
		}
	}

	if ($query)
	{
		$getthings = $DB_site->query($query);
		if ($DB_site->num_rows($getthings))
		{
			while ($getthing = $DB_site->fetch_array($getthings))
			{
				if (empty($forumcache["$getthing[forumid]"]))
				{
					$forumcache["$getthing[forumid]"] = array();
				}
				$forumcache["$getthing[forumid]"] = array_merge($forumcache["$getthing[forumid]"], $getthing);
			}
		}
	}

	$iforumcache = array();

	if ($getinvisibles) // get all forums including invisibles
	{
		foreach($forumcache AS $forumid => $forum)
		{
			$iforumcache["$forum[parentid]"]["$forum[displayorder]"]["$forumid"] = $forumid;
		}
	}
	else // get all forums except invisibles
	{
		foreach($forumcache AS $forumid => $forum)
		{
			if ($forum['displayorder'] AND ($forum['options'] & $_FORUMOPTIONS['active']))
			{
				$iforumcache["$forum[parentid]"]["$forum[displayorder]"]["$forumid"] = $forumid;
			}
			else
			{
				unset($forumcache["$forumid"]);
			}
		}
	}

	// do some sorting (instead of sorting with MySQL and causing a filesort)
	foreach($iforumcache AS $parentid => $devnull)
	{
		ksort($iforumcache["$parentid"]); // sort by display order
	}
	ksort($iforumcache); // sort by parentid (not actually sure if this is necessary)
}

// ###################### Start getimodcache #######################
function cache_moderators()
{
	global $DB_site, $imodcache, $mod;

	$imodcache = array();
	$mod = array();

	$forummoderators = $DB_site->query("
		SELECT moderator.*, user.username,
		IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid
		FROM " . TABLE_PREFIX . "moderator AS moderator
		INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
	");
	while ($moderator = $DB_site->fetch_array($forummoderators))
	{
		$moderator['musername'] = fetch_musername($moderator);
		$imodcache["$moderator[forumid]"]["$moderator[userid]"] = $moderator;
		$mod["$moderator[userid]"] = 1;
	}
	$DB_site->free_result($forummoderators);
}

// ###################### Start getlastpostinfo #######################
// this function creates a lastpostinfo array that tells makeforumbit which forum
// each forum should grab its last post info from.
// it also tots up the thread/post totals for each forum. - PERMISSIONS are taken into account.
function fetch_last_post_array()
{
	global $iforumcache, $forumcache, $lastpostarray, $bbuserinfo, $counters;

	// loop through the iforumcache
	foreach($iforumcache AS $moo)
	{
		foreach($moo AS $baa)
		{
			foreach ($baa AS $forumid)
			{

				$forum = $forumcache["$forumid"];

				// if we have no permission to view the forum's parent
				// set cannotView permissions cache for this forum and continue
				if ($cannotView["$forum[parentid]"] == 1)
				{
					$cannotView["$forumid"] = 1;
				}
				else
				{

					$forumperms = $bbuserinfo['forumpermissions']["$forumid"];

					// if we have no permissions for this forum, set the cannotView permissions cache
					// so that we don't have to check its child forums
					//if (!($forumperms & CANVIEW) OR !($forumperms & CANVIEWOTHERS))
					if (!($forumperms & CANVIEW))
					{
						$cannotView["$forumid"] = 1;
					}
					else
					{
						if (!($forumperms & CANVIEWOTHERS))
						{
							continue;
						}

						//$lastpostarray["$forumid"] = $forumid;
						if (!isset($lastpostarray["$forumid"]))
						{
							$lastpostarray["$forumid"] = $forumid;
						}
						$parents = explode(',', $forum['parentlist']);
						foreach($parents AS $parentid)
						{
							// for each parent, set an array entry containing this forum's number of posts & threads
							$children["$parentid"]["$forumid"] = array('threads' => $forum['threadcount'], 'posts' => $forum['replycount']);
							if ($parentid == -1 OR !is_array($forumcache["$parentid"]))
							{
								continue;
							}
							// compare the date for the last post info with the last post date
							// for the parent forum, and if it's greater, set the last post info
							// array for this forum to point to that forum... (erm..)
							if ((!$forum['password'] OR verify_forum_password($forum['forumid'], $forum['password'], false)) AND $forum['lastpost'] > $forumcache["$parentid"]['lastpost'])
							{
								$lastpostarray["$parentid"] = $forumid;
								$forumcache["$parentid"]['lastpost'] = $forum['lastpost'];
							}
						} // end foreach($parents)
					} // end can view
				} // end can view parent
			}
		}
	}

	$counters = array();
	if (is_array($forumcache))
	{
		foreach($forumcache AS $forum)
		{
			$counters["$forum[forumid]"]['threadcount'] = 0;
			$counters["$forum[forumid]"]['replycount'] = 0;
			if (is_array($children["$forum[forumid]"]))
			{
				foreach($children["$forum[forumid]"] AS $id => $info)
				{
					$counters["$forum[forumid]"]['threadcount'] += $info['threads'];
					$counters["$forum[forumid]"]['replycount'] += $info['posts'];
				}
			}
		}
	}
	return $i;
}

// ###################### Start makeforumbit #######################
// this function returns the properly-ordered and formatted forum lists for forumhome,
// forumdisplay and usercp. Of course, you could use it elsewhere too..
function construct_forum_bit($parentid, $depth = 0, $subsonly = 0)
{
	global $vboptions, $DB_site, $session, $bbuserinfo, $stylevar, $_FORUMOPTIONS, $vbphrase, $show;
	global $iforumcache, $forumcache, $imodcache, $lastpostarray, $counters, $inforum;

	// this function takes the constant MAXFORUMDEPTH as its guide for how
	// deep to recurse down forum lists. if MAXFORUMDEPTH is not defined,
	// it will assume a depth of 2.

	// call fetch_last_post_array() first to get last post info for forums
	if (!is_array($lastpostarray))
	{
		fetch_last_post_array();
	}

	if (!isset($iforumcache["$parentid"]))
	{
		return;
	}

	if (!defined(MAXFORUMDEPTH))
	{
		define('MAXFORUMDEPTH', 1);
	}

	$forumbits = '';
	$depth++;

	foreach ($iforumcache["$parentid"] AS $baa)
	{
		foreach ($baa AS $forumid)
		{
			// grab the appropriate forum from the $forumcache
			$forum = $forumcache["$forumid"];
			if (!$forum['displayorder'] OR !($forum['options'] & $_FORUMOPTIONS['active']))
			{
				continue;
			}

			$forumperms = $bbuserinfo['forumpermissions']["$forumid"];
			if (!($forumperms & CANVIEW) AND $vboptions['hideprivateforums'])
			{ // no permission to view current forum
				continue;
			}

			if ($subsonly)
			{
				$childforumbits = construct_forum_bit($forum['forumid'], 1, $subsonly);
			}
			else if ($depth < MAXFORUMDEPTH)
			{
				$childforumbits = construct_forum_bit($forum['forumid'], $depth, $subsonly);
			}
			else
			{
				$childforumbits = '';
			}

			// do stuff if we are not doing subscriptions only, or if we ARE doing subscriptions,
			// and the forum has a subscribedforumid
			if (!$subsonly OR ($subsonly AND !empty($forum['subscribeforumid'])))
			{

				$GLOBALS['forumshown'] = true; // say that we have shown at least one forum

				if (($forum['options'] & $_FORUMOPTIONS['cancontainthreads']))
				{ // get appropriate suffix for template name
					$tempext = '_post';
				}
				else

				{
					$tempext = '_nopost';
				}

				if (!$vboptions['showforumdescription'])
				{ // blank forum description if set to not show
					$forum['description'] = '';
				}

				// dates & thread title
				$lastpostinfo = $forumcache["$lastpostarray[$forumid]"];

				// compare last post time for this forum with the last post time specified by
				// the $lastpostarray, and if it's less, use the last post info from the forum
				// specified by $lastpostarray
				if ($forumcache["$lastpostarray[$forumid]"]['lastpost'] > 0)
				{

					$lastpostinfo['lastpostdate'] = vbdate($vboptions['dateformat'], $lastpostinfo['lastpost'], 1);
					$lastpostinfo['lastposttime'] = vbdate($vboptions['timeformat'], $lastpostinfo['lastpost']);
					$lastpostinfo['trimthread'] = fetch_trimmed_title($lastpostinfo['lastthread']);

					if ($icon = fetch_iconinfo($lastpostinfo['lasticonid']))
					{
						$show['icon'] = true;
					}
					else
					{
						$show['icon'] = false;
					}

					$show['lastpostinfo'] = (!$forum['password'] OR verify_forum_password($forum['forumid'], $forum['password'], false));

					eval('$forum[\'lastpostinfo\'] = "' . fetch_template('forumhome_lastpostby') . '";');

				}
				else if (!($forumperms & CANVIEW) AND $vboptions['hideprivateforums'] == 0)
				{
					$forum['lastpostinfo'] = $vbphrase['private'];
				}
				else
				{
					$forum['lastpostinfo'] = $vbphrase['never'];
				}


				// do light bulb
				$forum['statusicon'] = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);

				// add lock to lightbulb if necessary
				if ((!($forumperms & CANPOSTNEW) OR !($forum['options'] & $_FORUMOPTIONS['allowposting'])) AND $vboptions['showlocks'] AND !$forum['link'])
				{
					$forum['statusicon'] .= '_lock';
				}

				// get counters from the counters cache ( prepared by fetch_last_post_array() )
				$forum['threadcount'] = $counters["$forum[forumid]"]['threadcount'];
				$forum['replycount'] = $counters["$forum[forumid]"]['replycount'];

				// get moderators ( this is why we needed cache_moderators() )
				if ($vboptions['showmoderatorcolumn'])
				{
					$showmods = array();
					$listexploded = explode(',', $forum['parentlist']);
					foreach ($listexploded AS $parentforumid)
					{
						if (!isset($imodcache["$parentforumid"]))
						{
							continue;
						}
						foreach($imodcache["$parentforumid"] AS $moderator)
						{
							if ($showmods["$moderator[userid]"] === true)
							{
								continue;
							}

							$showmods["$moderator[userid]"] = true;
							if (!isset($forum['moderators']))
							{
								eval('$forum[\'moderators\'] = "' . fetch_template('forumhome_moderator') . '";');
							}
							else
							{
								eval('$forum[\'moderators\'] .= ", ' . fetch_template('forumhome_moderator') . '";');
							}
						}
					}
					if (!isset($forum['moderators']))
					{
						$forum['moderators'] = '';
					}
				}

				if ($forum['link'])
				{
					$forum['replycount'] = '-';
					$forum['threadcount'] = '-';
					$forum['lastpostinfo'] = '-';
				}
				else
				{
					$forum['replycount'] = vb_number_format($forum['replycount']);
					$forum['threadcount'] = vb_number_format($forum['threadcount']);
				}

				if (($subsonly OR $depth == MAXFORUMDEPTH) AND $vboptions['subforumdepth'] > 0)
				{
					$forum['subforums'] = construct_subforum_bit($forumid, ($forum['options'] & $_FORUMOPTIONS['cancontainthreads'] ) );
				}
				else
				{
					$forum['subforums'] = '';
				}

				$children = explode(',', $forum['childlist']);
				foreach($children AS $childid)
				{
					$forum['browsers'] += iif($inforum["$childid"], $inforum["$childid"], 0);
				}

				if ($depth == 1 AND $tempext == '_nopost')
				{
					global $vbcollapse;
					$collapseobj_forumid = &$vbcollapse["collapseobj_forumbit_$forumid"];
					$collapseimg_forumid = &$vbcollapse["collapseimg_forumbit_$forumid"];
					$show['collapsebutton'] = true;
				}
				else
				{
					$show['collapsebutton'] = false;
				}

				$show['forumsubscription'] = iif ($subsonly, true, false);
				$show['forumdescription'] = iif ($forum['description'] != '', true, false);
				$show['subforums'] = iif ($forum['subforums'] != '', true, false);
				$show['browsers'] = iif ($vboptions['displayloggedin'] AND !$forum['link'] AND $forum['browsers'], true, false);

				// build the template for the current forum
				eval('$forumbits .= "' . fetch_template("forumhome_forumbit_level$depth$tempext") . '";');

			} // end if (!$subsonly OR ($subsonly AND !empty($forum['subscribeforumid'])))
			else
			{
				$forumbits .= $childforumbits;
			}
		}
	}

	return $forumbits;
}

// ###################### Start getforumlightbulb #######################
// returns 'on' or 'off' depending on last post info for a forum
function fetch_forum_lightbulb(&$forumid, &$lastpostinfo, &$foruminfo)
{

	global $bbuserinfo, $bb_view_cache, $DB_site;

	if (is_array($foruminfo) AND !empty($foruminfo['link']))
	{ // see if it is a redirect
		return 'link';
	}
	else
	{
		if ($bbuserinfo['lastvisitdate'] == -1)
		{
			return 'new';
		}
		else
		{
			$forumview = intval(fetch_bbarray_cookie('forum_view', $foruminfo['forumid']));
			//use which one produces the highest value, most likely cookie
			if ($forumview > $bbuserinfo['lastvisit'])
			{
				$userlastvisit = $forumview;
			}
			else
			{
				$userlastvisit = $bbuserinfo['lastvisit'];
			}

			if ($userlastvisit < $lastpostinfo['lastpost'])
			{
				return 'new';
			}
			else
			{
				return 'old';
			}
		}
	}
}

// ###################### Start makesubforumbit #######################
// gets a list of a forum's children and returns it
// based on the forumhome_subforumbit template
function construct_subforum_bit($parentid, $cancontainthreads, $output = '', $depthmark = '--', $depth = 0)
{

	global $vboptions, $stylevar, $vbphrase, $session, $_FORUMOPTIONS;
	global $iforumcache, $forumcache, $bbuserinfo, $lastpostinfo, $lastpostarray;
	static $splitter;

	if ($cancontainthreads)
	{
		$canpost = 'post';
	}
	else
	{
		$canpost = 'nopost';
	}

	// get the splitter template
	if (!isset($splitter["$canpost"]))
	{
		eval('$splitter[$canpost] = "' . fetch_template("forumhome_subforumseparator_$canpost") . '";');
	}

	if (!isset($iforumcache["$parentid"]))
	{
		return $output;
	}

	foreach($iforumcache["$parentid"] AS $forums)
	{
		foreach($forums AS $forumid)
		{
			$forum = $forumcache["$forumid"];
			$forumperms = $bbuserinfo['forumpermissions']["$forumid"];
			if (!($forumperms & CANVIEW) AND $vboptions['hideprivateforums'])
			{ // no permission to view current forum
				continue;
			}

			if (!$forum['displayorder'] OR !($forum['options'] & $_FORUMOPTIONS['active']))
			{
				// forum not active
				continue;
			}
			else
			{ // get on/off status
				$lastpostinfo = $forumcache["$lastpostarray[$forumid]"];
				$forum['statusicon'] = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);
				$show['newposticon'] = iif($forum['statusicon'], true, false);

				eval('$subforum = "' . fetch_template("forumhome_subforumbit_$canpost") . '";');
				if (!empty($output))
				{
					$subforum = $splitter["$canpost"] . $subforum;
				}
				if ($depth < $vboptions['subforumdepth'])
				{
					$output .= construct_subforum_bit($forumid, $cancontainthreads, $subforum, $depthmark . '--', $depth + 1);
				}
			}
		}
	}

	return $output;

}

// ###################### Start geticoninfo #######################
function fetch_iconinfo($iconid = 0)
{
	global $DB_site, $vboptions, $stylevar, $vbphrase;

	$iconid = intval($iconid);

	switch($iconid)
	{
		case -1:
			DEVDEBUG('returning poll icon');
			return array('iconpath' => "$stylevar[imgdir_misc]/poll_posticon.gif", 'title' => $vbphrase['poll']);
		case 0:
			if (!empty($vboptions['showdeficon']))
			{
				DEVDEBUG("returning default icon");
				return array('iconpath' => "$vboptions[showdeficon]", 'title' => '');
			}
			else
			{
				return false;
			}
		default:
			global $datastore;
			static $iconcache;
			if (isset($datastore['iconcache']))
			{ // we can get the icon info from the template cache
				if (!isset($iconcache))
				{
					$iconcache = unserialize($datastore['iconcache']);
				}
				DEVDEBUG("returning iconid:$iconid from the template cache");
				return $iconcache["$iconid"];
			}
			else
			{ // we have to get the icon from a query
				DEVDEBUG("QUERYING iconid:$iconid)");
				return $DB_site->query_first("
					SELECT title, iconpath
					FROM " . TABLE_PREFIX . "icon
					WHERE iconid = $iconid
				");
			}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13174 $
|| ####################################################################
\*======================================================================*/
?>