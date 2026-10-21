<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ###################### Start fetch_coventry #######################
// gets a list of userids in Coventry. Specify 'string' as your argument
// if you want a comma-separated string rather than an array
function fetch_coventry($returntype = 'array')
{
	global $vbulletin;
	static $Coventry;

	if (!isset($Coventry))
	{
		if (trim($vbulletin->options['globalignore']) != '')
		{
			$Coventry = preg_split('#\s+#s', $vbulletin->options['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
			$bbuserkey = array_search($vbulletin->userinfo['userid'], $Coventry);
			if ($bbuserkey !== FALSE AND $bbuserkey !== NULL)
			{
				unset($Coventry["$bbuserkey"]);
			}
		}
		else
		{
			$Coventry = array();
		}
	}

	if ($returntype === 'array')
	{
		// return array
		return $Coventry;
	}
	else
	{
		// return comma-separated string
		return implode(',', $Coventry);
	}
}

// ###################### Start getOnlineStatus #######################
// work out if bbuser can see online status of user
// also puts in + and * symbols as $user[buddymark] and $user[invisiblemark]
function fetch_online_status(&$user, $setstatusimage = false)
{
	global $vbulletin, $stylevar, $vbphrase;
	static $buddylist, $datecut;

	// get variables used by this function
	if (!is_array($buddylist))
	{
		$datecut = TIMENOW - $vbulletin->options['cookietimeout'];

		if ($vbulletin->userinfo['buddylist'] = trim($vbulletin->userinfo['buddylist']))
		{
			$buddylist = preg_split('/\s+/', $vbulletin->userinfo['buddylist'], -1, PREG_SPLIT_NO_EMPTY);
		}
		else
		{
			$buddylist = array();
		}
	}

	// is the user on bbuser's buddylist?
	if (in_array($user['userid'], $buddylist))
	{
		$user['buddymark'] = '+';
	}
	else
	{
		$user['buddymark'] = '';
	}

	// set the invisible mark to nothing by default
	$user['invisiblemark'] = '';

	$onlinestatus = 0;
	// now decide if we can see the user or not
	if ($user['lastactivity'] > $datecut AND $user['lastvisit'] != $user['lastactivity'])
	{
		if ($user['invisible'])
		{
			if (($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehidden']) OR $user['userid'] == $vbulletin->userinfo['userid'])
			{
				// user is online and invisible BUT bbuser can see them
				$user['invisiblemark'] = '*';
				$onlinestatus = 2;
			}
		}
		else
		{
			// user is online and visible
			$onlinestatus = 1;
		}
	}

	if ($setstatusimage)
	{
		eval('$user[\'onlinestatus\'] = "' . fetch_template('postbit_onlinestatus') . '";');
	}

	return $onlinestatus;
}

/**
* Marks a thread as read using the appropriate method.
*
* @param	array	Array of data for the thread being marked
* @param	array	Array of data for the forum the thread is in
* @param	integer	User ID this thread is being marked read for
* @param	integer	Unix timestamp that the thread is being marked read
*/
function mark_thread_read(&$threadinfo, &$foruminfo, $userid, $time)
{
	global $vbulletin, $db;

	$userid = intval($userid);
	$time = intval($time);

	if ($vbulletin->options['threadmarking'] AND $userid)
	{
		// can't be shutdown as we do a read query below on this table
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "threadread
				(threadid, userid, readtime)
			VALUES
				($threadinfo[threadid], $userid, $time)
		");
	}
	else
	{
		set_bbarray_cookie('thread_lastview', $threadinfo['threadid'], $time);
	}

	// now if applicable search to see if this was the last thread requiring marking in this forum
	if ($vbulletin->options['threadmarking'] == 2 AND $userid)
	{
		/*$forumread = intval(max($threadinfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400)));
		$unread = $db->query_first("
			SELECT COUNT(*) AS count
 			FROM " . TABLE_PREFIX . "thread AS thread
 			LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = $userid)
 			WHERE thread.forumid = $threadinfo[forumid]
	      		AND thread.visible = 1
	      		AND thread.sticky IN (0,1)
	      		AND thread.lastpost > $forumread
	      		AND thread.open <> 10
	      		AND (threadread.threadid IS NULL OR threadread.readtime < thread.lastpost)
		");
		if ($unread['count'] == 0)
		{
			mark_forum_read($foruminfo, $userid, TIMENOW);
		}*/

		// forum can only be marked as read if all the children are read as well,
		// so determine which children "count"
		if ($foruminfo['childlist'] AND $userid == $vbulletin->userinfo['userid'])
		{
			$children = '-1';
			foreach (explode(',', $foruminfo['childlist']) AS $child_forum)
			{
				$child_forum = intval($child_forum);
				$forumperms = $vbulletin->userinfo['forumpermissions']["$child_forum"];

				if (empty($forumperms) OR
					!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR
					!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR
					!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
				{
					// invalid forum, can't be viewed, can't view threads, can't view others threads
					// means we can't include this when trying to mark a thread as read
					continue;
				}

				$children .= ',' . $child_forum;
			}
		}
		else
		{
			$children = $threadinfo['forumid'];
		}

		$cutoff = TIMENOW - ($vbulletin->options['markinglimit'] * 86400);
		$unread = $db->query_first("
			SELECT COUNT(*) AS count
 			FROM " . TABLE_PREFIX . "thread AS thread
 			LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = $userid)
			LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = thread.forumid AND forumread.userid = $userid)
			WHERE thread.forumid IN ($children)
	      		AND thread.visible = 1
	      		AND thread.sticky IN (0,1)
				AND thread.lastpost > IF(threadread.readtime IS NULL, $cutoff, threadread.readtime)
				AND thread.lastpost > IF(forumread.readtime IS NULL, $cutoff, forumread.readtime)
				AND thread.lastpost > $cutoff
	      		AND thread.open <> 10
		");
		if ($unread['count'] == 0)
		{
			mark_forum_read($foruminfo, $userid, TIMENOW);
		}
	}
}

/**
* Marks a forum as read using the appropriate method.
*
* @param	array	Array of data for the forum being marked
* @param	integer	User ID this thread is being marked read for
* @param	integer	Unix timestamp that the thread is being marked read
* @param	boolean	Whether to automatically check if the parents' read times need to be updated
*
* @return	array	Returns an array of forums that were marked as read
*/
function mark_forum_read(&$foruminfo, $userid, $time, $check_parents = true)
{
	global $vbulletin, $db;

	if (empty($foruminfo['forumid']))
	{
		// sanity check -- wouldn't work anyway
		return array();
	}

	$userid = intval($userid);
	$time = intval($time);
	$forums_marked = array($foruminfo['forumid']);

	if ($vbulletin->options['threadmarking'] AND $userid)
	{

		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "forumread
				(forumid, userid, readtime)
			VALUES
				($foruminfo[forumid], $userid, $time)
		");

		if (!$check_parents)
		{
			return $forums_marked;
		}

		// check to see if any parent forums should be marked as read as well
		$parentarray = array_diff(explode(',', $foruminfo['parentlist']), array($foruminfo['forumid'], -1));
		if (!empty($parentarray))
		{
			// find the top most entry in the parent list -- we need its child list
			$top_parentid = end($parentarray);
			$top_foruminfo = $vbulletin->forumcache["$top_parentid"];
			if (!$top_foruminfo['childlist'])
			{
				return $forums_marked;
			}

			// fetch the effective (including children) and raw last post times
			static $lastpostset = false, $rawlastpostinfo;
			if (!$lastpostset)
			{
				$lastpostset = true;
				require_once(DIR . '/includes/functions_forumlist.php');
				cache_ordered_forums(1);
				$rawlastpostinfo = $vbulletin->forumcache;
				fetch_last_post_array();
			}

			// determine the read time for all forums that we need to consider
			$readtimes = array();
			$readtimes_query = $db->query_read_slave("
				SELECT forumid, readtime
				FROM " . TABLE_PREFIX . "forumread
				WHERE userid = $userid
					AND forumid IN ($top_foruminfo[childlist])
			");
			while ($readtime = $db->fetch_array($readtimes_query))
			{
				$readtimes["$readtime[forumid]"] = $readtime['readtime'];
			}

			$cutoff = (TIMENOW - ($vbulletin->options['markinglimit'] * 86400));

			// now work through the parent, grandparent, etc of the forum we just marked
			// and mark it read only if all direct children are marked read
			foreach ($parentarray AS $parentid)
			{
				if (empty($vbulletin->forumcache["$parentid"]))
				{
					continue;
				}

				$markread = true;

				// now look through all the children and confirm they are all read
				if (is_array($vbulletin->iforumcache["$parentid"]))
				{
					foreach ($vbulletin->iforumcache["$parentid"] AS $childid)
					{
						if (max($cutoff, $readtimes["$childid"]) < $vbulletin->forumcache["$childid"]['lastpost'])
						{
							$markread = false;
							break;
						}
					}
				}
				
				// if all children are read, make sure all the threads in this forum are read too
				if ($markread)
				{
					$forumread = intval(max($readtimes["$parentid"], $cutoff));
					$unread = $db->query_first("
						SELECT COUNT(*) AS count
			 			FROM " . TABLE_PREFIX . "thread AS thread
			 			LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = $userid)
			 			WHERE thread.forumid = $parentid
				      		AND thread.visible = 1
				      		AND thread.sticky IN (0,1)
				      		AND thread.lastpost > $forumread
				      		AND thread.open <> 10
				      		AND (threadread.threadid IS NULL OR threadread.readtime < thread.lastpost)
					");
					if ($unread['count'] > 0)
					{
						$markread = false;
					}
				}

				if ($markread)
				{
					// can mark as read
					$readtimes["$parentid"] = $time;
					$parents[] = "($parentid, $userid, $time)";
					$forums_marked[] = $parentid;
				}
				else
				{
					// can't mark this as read, so we have no need to continue with further generations
					break;
				}
			}

			if ($parents)
			{
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "forumread
						(forumid, userid, readtime)
					VALUES
						" . implode(', ', $parents)
				);
			}
		}
	}
	else
	{
		set_bbarray_cookie('forum_view', $foruminfo['forumid'], $time);
	}

	return $forums_marked;
}

// ###################### Start getforumrules #######################
function construct_forum_rules($foruminfo, $permissions)
{
	// array of foruminfo and permissions for this forum
	global $forumrules, $stylevar, $vbphrase, $vbcollapse, $show, $vbulletin;

	$bbcodeon = iif($foruminfo['allowbbcode'], $vbphrase['on'], $vbphrase['off']);
	$imgcodeon = iif($foruminfo['allowimages'], $vbphrase['on'], $vbphrase['off']);
	$htmlcodeon = iif($foruminfo['allowhtml'], $vbphrase['on'], $vbphrase['off']);
	$smilieson = iif($foruminfo['allowsmilies'], $vbphrase['on'], $vbphrase['off']);

	$can['postnew'] = (($permissions & $vbulletin->bf_ugp_forumpermissions['canpostnew']) AND $foruminfo['allowposting']);
	$can['replyown'] = (($permissions & $vbulletin->bf_ugp_forumpermissions['canreplyown']) AND $foruminfo['allowposting']);
	$can['replyothers'] = (($permissions & $vbulletin->bf_ugp_forumpermissions['canreplyothers']) AND $foruminfo['allowposting']);
	$can['editpost'] = $permissions & $vbulletin->bf_ugp_forumpermissions['caneditpost'];
	$can['postattachment'] = (($permissions & $vbulletin->bf_ugp_forumpermissions['canpostattachment']) AND $foruminfo['allowposting'] AND !empty($vbulletin->userinfo['attachmentextensions']));

	$notword = $vbphrase['not'];
	$rules['postnew'] = iif($can['postnew'], '', $notword);
	$rules['postreply'] = iif($can['replyown'] OR $can['replyothers'], '', $notword);
	$rules['edit'] = iif($can['editpost'], '', $notword);
	$rules['attachment'] = iif(($can['postattachment']) AND ($can['postnew'] OR $can['replyown'] OR $can['replyothers']), '', $notword);

	($hook = vBulletinHook::fetch_hook('forumrules')) ? eval($hook) : false;

	eval('$forumrules = "' . fetch_template('forumrules') . '";');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 17973 $
|| ####################################################################
\*======================================================================*/
?>