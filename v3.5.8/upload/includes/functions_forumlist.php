<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ###################### Start cache ordered forums #######################
// pass $vbulletin->userinfo[userid] into the function in order to return a value
// for which forums are subscribed ($arry[subscribeforumid])
function cache_ordered_forums($getcounters = 0, $getinvisibles = 0, $userid = 0)
{
	global $vbulletin;

	// query forum table to get latest lastpost/lastthread info and counters
	if ($getcounters)
	{
		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
		{
			$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachyforumpost AS tachyforumpost ON " .
				"(tachyforumpost.forumid = forum.forumid AND tachyforumpost.userid = " . $vbulletin->userinfo['userid'] . ')';

			$counter_select = '
				forum.forumid,
				IF(tachyforumpost.userid IS NULL, forum.lastpost, tachyforumpost.lastpost) AS lastpost,
				IF(tachyforumpost.userid IS NULL, forum.lastposter, tachyforumpost.lastposter) AS lastposter,
				IF(tachyforumpost.userid IS NULL, forum.lastthread, tachyforumpost.lastthread) AS lastthread,
				IF(tachyforumpost.userid IS NULL, forum.lastthreadid, tachyforumpost.lastthreadid) AS lastthreadid,
				IF(tachyforumpost.userid IS NULL, forum.lasticonid, tachyforumpost.lasticonid) AS lasticonid,
				forum.threadcount,
				forum.replycount';
		}
		else
		{
			$tachyjoin = '';
			$counter_select = 'forum.forumid, lastpost, lastposter, lastthread, lastthreadid, lasticonid, threadcount, replycount';
		}

		($hook = vBulletinHook::fetch_hook('cache_ordered_forums')) ? eval($hook) : false;

		// get subscribed forums too
		if ($userid)
		{
			$query = "
			SELECT subscribeforumid, $counter_select
				". iif($vbulletin->options['threadmarking'], ', forumread.readtime AS forumread') . "
			FROM " . TABLE_PREFIX . "forum AS forum
			LEFT JOIN " . TABLE_PREFIX . "subscribeforum AS subscribeforum ON (subscribeforum.forumid = forum.forumid AND subscribeforum.userid = $userid)
			" . iif($vbulletin->options['threadmarking'], " LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = forum.forumid AND forumread.userid = $userid)") . "
			$tachyjoin
			";
		}
		// just get counters
		else
		{
			$query = "
			SELECT $counter_select
				". iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], ', forumread.readtime AS forumread') . "
			FROM " . TABLE_PREFIX . "forum AS forum
			" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = forum.forumid AND forumread.userid = " .  $vbulletin->userinfo['userid'] . ")") . "
			$tachyjoin
			";
		}
	}
		// get subscribed forums
	else if ($userid)
	{
		$query = "
		SELECT subscribeforumid, forumid
		FROM " . TABLE_PREFIX . "subscribeforum
		WHERE userid = $userid
		";
	}
	// don't bother to query forum table, just use the cache
	else
	{
		$query = null;
	}

	if ($query !== null)
	{
		$db =& $vbulletin->db;

		$getthings = $db->query_read($query);
		if ($db->num_rows($getthings))
		{
			while ($getthing = $db->fetch_array($getthings))
			{
				if (empty($vbulletin->forumcache["$getthing[forumid]"]))
				{
					$vbulletin->forumcache["$getthing[forumid]"] = $getthing;
				}
				else
				{
					// this adds the existing cache to $getthing without overwriting
					// any of $getthing's keys
					$vbulletin->forumcache["$getthing[forumid]"] = $getthing + $vbulletin->forumcache["$getthing[forumid]"];
					/*$cached_forum =& $vbulletin->forumcache["$getthing[forumid]"];

					$cached_forum['lastpost'] = $getthing['lastpost'];
					$cached_forum['lastposter'] = $getthing['lastposter'];
					$cached_forum['lastthread'] = $getthing['lastthread'];
					$cached_forum['lastthreadid'] = $getthing['lastthreadid'];
					$cached_forum['lasticonid'] = $getthing['lasticonid'];
					$cached_forum['threadcount'] = $getthing['threadcount'];
					$cached_forum['replycount'] = $getthing['replycount'];

					$cached_forum['forumread'] = $getthing['forumread'];

					$cached_forum['subscribeforumid'] = $getthing['subscribeforumid'];*/
				}
			}
		}
	}

	$vbulletin->iforumcache = array();

	foreach ($vbulletin->forumcache AS $forumid => $forum)
	{
		if ((!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active'])) AND !$getinvisibles)
		{
			continue;
		}
		$forum['parentid'] = intval($forum['parentid']);
		$vbulletin->iforumcache["$forum[parentid]"]["$forumid"] = $forumid;
	}
}

// ###################### Start getimodcache #######################
function cache_moderators($userid = null)
{
	global $vbulletin, $imodcache, $mod;

	$imodcache = array();
	$mod = array();

	$forummoderators = $vbulletin->db->query_read("
		SELECT moderator.*, user.username,
		IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid
		FROM " . TABLE_PREFIX . "moderator AS moderator
		INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		" . ($userid != null ? "WHERE moderator.userid = " . intval($userid) : "") . "
	");
	while ($moderator = $vbulletin->db->fetch_array($forummoderators))
	{
		$moderator['musername'] = fetch_musername($moderator);
		$imodcache["$moderator[forumid]"]["$moderator[userid]"] = $moderator;
		$mod["$moderator[userid]"] = 1;
	}
	$vbulletin->db->free_result($forummoderators);
}

// ###################### Start getlastpostinfo #######################
// this function creates a lastpostinfo array that tells makeforumbit which forum
// each forum should grab its last post info from.
// it also tots up the thread/post totals for each forum. - PERMISSIONS are taken into account.
function fetch_last_post_array()
{
	global $vbulletin, $lastpostarray, $counters;

	$cannotView = array();
	$children = array();

	// loop through the vbulletin->iforumcache
	foreach ($vbulletin->iforumcache AS $moo)
	{
		foreach ($moo AS $forumid)
		{
			$forum = $vbulletin->forumcache["$forumid"];

			// if we have no permission to view the forum's parent
			// set cannotView permissions cache for this forum and continue
			if (!empty($cannotView["$forum[parentid]"]))
			{
				$cannotView["$forumid"] = 1;
			}
			else
			{

				$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];

				// if we have no permissions for this forum, set the cannotView permissions cache
				// so that we don't have to check its child forums
				//if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])
				if ((!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND $vbulletin->options['showprivateforums'] != 2) OR ($forum['password'] AND !verify_forum_password($forum['forumid'], $forum['password'], false)))
				{
					$cannotView["$forumid"] = 1;
				}
				else
				{
					if (!isset($lastpostarray["$forumid"]))
					{
						$lastpostarray["$forumid"] = $forumid;
					}
					$parents = explode(',', $forum['parentlist']);
					foreach ($parents AS $parentid)
					{
						// for each parent, set an array entry containing this forum's number of posts & threads
						$children["$parentid"]["$forumid"] = array('threads' => $forum['threadcount'], 'posts' => $forum['replycount']);

						if ($parentid == -1 OR !isset($vbulletin->forumcache["$parentid"]))
						{
							continue;
						}

						// compare the date for the last post info with the last post date
						// for the parent forum, and if it's greater, set the last post info
						// array for this forum to point to that forum... (erm..)
						if ($forum['lastpost'] > $vbulletin->forumcache["$parentid"]['lastpost'])
						{
							$lastpostarray["$parentid"] = $forumid;
							$vbulletin->forumcache["$parentid"]['lastpost'] = $forum['lastpost'];
						}
					} // end foreach($parents)
				} // end can view
			} // end can view parent
		}
	}

	$counters = array();
	if (is_array($vbulletin->forumcache))
	{
		foreach($vbulletin->forumcache AS $forum)
		{
			$this_forum =& $counters["$forum[forumid]"];
			$this_forum['threadcount'] = 0;
			$this_forum['replycount'] = 0;
			if (is_array($children["$forum[forumid]"]))
			{
				foreach($children["$forum[forumid]"] AS $id => $info)
				{
					$this_forum['threadcount'] += $info['threads'];
					$this_forum['replycount'] += $info['posts'];
				}
			}
		}
	}

}

// ###################### Start makeforumbit #######################
// this function returns the properly-ordered and formatted forum lists for forumhome,
// forumdisplay and usercp. Of course, you could use it elsewhere too..
function construct_forum_bit($parentid, $depth = 0, $subsonly = 0)
{
	global $vbulletin, $stylevar, $vbphrase, $show;
	global $imodcache, $lastpostarray, $counters, $inforum;

	// this function takes the constant MAXFORUMDEPTH as its guide for how
	// deep to recurse down forum lists. if MAXFORUMDEPTH is not defined,
	// it will assume a depth of 2.

	// call fetch_last_post_array() first to get last post info for forums
	if (!is_array($lastpostarray))
	{
		fetch_last_post_array();
	}

	if (empty($vbulletin->iforumcache["$parentid"]))
	{
		return;
	}

	if (!defined('MAXFORUMDEPTH'))
	{
		define('MAXFORUMDEPTH', 1);
	}

	$forumbits = '';
	$depth++;

	foreach ($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		// grab the appropriate forum from the $vbulletin->forumcache
		$forum = $vbulletin->forumcache["$forumid"];
		$lastpostforum = $vbulletin->forumcache["$lastpostarray[$forumid]"];
		if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
		{
			continue;
		}

		$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
		$lastpostforumperms = $vbulletin->userinfo['forumpermissions']["$lastpostarray[$forumid]"];
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND !$vbulletin->options['showprivateforums'])
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

			if (($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']))
			{ // get appropriate suffix for template name
				$tempext = '_post';
			}
			else

			{
				$tempext = '_nopost';
			}

			if (!$vbulletin->options['showforumdescription'])
			{ // blank forum description if set to not show
				$forum['description'] = '';
			}

			// dates & thread title
			$lastpostinfo = $vbulletin->forumcache["$lastpostarray[$forumid]"];

			// compare last post time for this forum with the last post time specified by
			// the $lastpostarray, and if it's less, use the last post info from the forum
			// specified by $lastpostarray
			if ($vbulletin->forumcache["$lastpostarray[$forumid]"]['lastpost'] > 0)
			{
				if (!($lastpostforumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR (!($lastpostforumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $lastpostinfo['lastposter'] != $vbulletin->userinfo['username']))
				{
					$forum['lastpostinfo'] = $vbphrase['private'];
				}
				else
				{
					$lastpostinfo['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $lastpostinfo['lastpost'], 1);
					$lastpostinfo['lastposttime'] = vbdate($vbulletin->options['timeformat'], $lastpostinfo['lastpost']);
					$lastpostinfo['trimthread'] = fetch_trimmed_title($lastpostinfo['lastthread']);

					if ($vbulletin->forumcache["$lastpostforum[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'] AND $icon = fetch_iconinfo($lastpostinfo['lasticonid']))
					{
						$show['icon'] = true;
					}
					else
					{
						$show['icon'] = false;
					}

					$show['lastpostinfo'] = (!$lastpostforum['password'] OR verify_forum_password($lastpostforum['forumid'], $lastpostforum['password'], false));

					eval('$forum[\'lastpostinfo\'] = "' . fetch_template('forumhome_lastpostby') . '";');
				}
			}
			else if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
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
			if ((!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting'])) AND $vbulletin->options['showlocks'] AND !$forum['link'])
			{
				$forum['statusicon'] .= '_lock';
			}

			// get counters from the counters cache ( prepared by fetch_last_post_array() )
			$forum['threadcount'] = $counters["$forum[forumid]"]['threadcount'];
			$forum['replycount'] = $counters["$forum[forumid]"]['replycount'];

			// get moderators ( this is why we needed cache_moderators() )
			if ($vbulletin->options['showmoderatorcolumn'])
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
						if (isset($showmods["$moderator[userid]"]))
						{
							continue;
						}

						($hook = vBulletinHook::fetch_hook('forumbit_moderator')) ? eval($hook) : false;

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

			if (($subsonly OR $depth == MAXFORUMDEPTH) AND $vbulletin->options['subforumdepth'] > 0)
			{
				$forum['subforums'] = construct_subforum_bit($forumid, ($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads'] ) );
			}
			else
			{
				$forum['subforums'] = '';
			}

			$forum['browsers'] = 0;
			$children = explode(',', $forum['childlist']);
			foreach($children AS $childid)
			{
				$forum['browsers'] += (isset($inforum["$childid"]) ? $inforum["$childid"] : 0);
			}

			if ($depth == 1 AND $tempext == '_nopost')
			{
				global $vbcollapse;
				$collapseobj_forumid =& $vbcollapse["collapseobj_forumbit_$forumid"];
				$collapseimg_forumid =& $vbcollapse["collapseimg_forumbit_$forumid"];
				$show['collapsebutton'] = true;
			}
			else
			{
				$show['collapsebutton'] = false;
			}

			$show['forumsubscription'] = ($subsonly ? true : false);
			$show['forumdescription'] = ($forum['description'] != '' ? true : false);
			$show['subforums'] = ($forum['subforums'] != '' ? true : false);
			$show['browsers'] = ($vbulletin->options['displayloggedin'] AND !$forum['link'] AND $forum['browsers'] ? true : false);

			// build the template for the current forum

			($hook = vBulletinHook::fetch_hook('forumbit_display')) ? eval($hook) : false;

			eval('$forumbits .= "' . fetch_template("forumhome_forumbit_level$depth$tempext") . '";');

		} // end if (!$subsonly OR ($subsonly AND !empty($forum['subscribeforumid'])))
		else
		{
			$forumbits .= $childforumbits;
		}
	}

	return $forumbits;
}

// ###################### Start getforumlightbulb #######################
// returns 'on' or 'off' depending on last post info for a forum
function fetch_forum_lightbulb(&$forumid, &$lastpostinfo, &$foruminfo)
{

	global $bb_view_cache, $vbulletin;

	if (is_array($foruminfo) AND !empty($foruminfo['link']))
	{ // see if it is a redirect
		return 'link';
	}
	else
	{
		if ($vbulletin->userinfo['lastvisitdate'] == -1)
		{
			return 'new';
		}
		else
		{
			if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
			{
				$userlastvisit = (!empty($foruminfo['forumread']) ? $foruminfo['forumread'] : (TIMENOW - ($vbulletin->options['markinglimit'] * 86400)));
			}
			else
			{
				$forumview = intval(fetch_bbarray_cookie('forum_view', $foruminfo['forumid']));

				//use which one produces the highest value, most likely cookie
				$userlastvisit = ($forumview > $vbulletin->userinfo['lastvisit'] ? $forumview : $vbulletin->userinfo['lastvisit']);
			}

			if ($lastpostinfo['lastpost'] AND $userlastvisit < $lastpostinfo['lastpost'])
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
	global $vbulletin, $stylevar, $vbphrase;
	global $lastpostinfo, $lastpostarray;
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

	if (!isset($vbulletin->iforumcache["$parentid"]))
	{
		return $output;
	}

	foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		$forum = $vbulletin->forumcache["$forumid"];
		$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND !$vbulletin->options['showprivateforums'])
		{ // no permission to view current forum
			continue;
		}

		if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
		{
			// forum not active
			continue;
		}
		else
		{ // get on/off status
			$lastpostinfo = $vbulletin->forumcache["$lastpostarray[$forumid]"];
			$forum['statusicon'] = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);
			$show['newposticon'] = ($forum['statusicon'] ? true : false);

			($hook = vBulletinHook::fetch_hook('forumbit_subforumbit')) ? eval($hook) : false;

			eval('$subforum = "' . fetch_template("forumhome_subforumbit_$canpost") . '";');
			if (!empty($output))
			{
				$subforum = $splitter["$canpost"] . $subforum;
			}
			if ($depth < $vbulletin->options['subforumdepth'])
			{
				$output .= construct_subforum_bit($forumid, $cancontainthreads, $subforum, $depthmark . '--', $depth + 1);
			}
		}
	}

	return $output;

}

// ###################### Start geticoninfo #######################
function fetch_iconinfo($iconid = 0)
{
	global $vbulletin, $stylevar, $vbphrase, $vbulletin;

	$iconid = intval($iconid);

	switch($iconid)
	{
		case -1:
			DEVDEBUG('returning poll icon');
			return array('iconpath' => "$stylevar[imgdir_misc]/poll_posticon.gif", 'title' => $vbphrase['poll']);
		case 0:
			if (!empty($vbulletin->options['showdeficon']))
			{
				DEVDEBUG("returning default icon");
				return array('iconpath' => $vbulletin->options['showdeficon'], 'title' => '');
			}
			else
			{
				return false;
			}
		default:
			if ($vbulletin->iconcache !== null)
			{ // we can get the icon info from the template cache

				DEVDEBUG("returning iconid:$iconid from the template cache");
				return $vbulletin->iconcache["$iconid"];
			}
			else
			{ // we have to get the icon from a query
				DEVDEBUG("QUERYING iconid:$iconid)");
				return $vbulletin->db->query_first("
					SELECT title, iconpath
					FROM " . TABLE_PREFIX . "icon
					WHERE iconid = $iconid
				");
			}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14574 $
|| ####################################################################
\*======================================================================*/
?>