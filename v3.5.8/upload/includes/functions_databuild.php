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

// ###################### Start updateusertextfields #######################
// takes the field type pmfolders/buddylist/ignorelist/signature in 'field'
// takes the value to insert in $value
function build_usertextfields($field, $value, $userid = 0)
{
	global $vbulletin;

	$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);

	if ($userid == 0)
	{
		$userdata->set_existing($vbulletin->userinfo);
	}
	else
	{
		$userinfo = array('userid' => $userid);
		$userdata->set_existing($userinfo);
	}

	$userdata->set($field, $value);
	$userdata->save();

	return 0;
}

// ###################### Start getforumcache #######################
function cache_forums($forumid = -1, $depth = 0)
{
	// returns an array of forums with correct parenting and depth information
	// see makeforumchooser for an example of usage

	die('
		<p>The function <strong>cache_forums()</strong> is now redundant.</p>
		<p>The standard <em>$vbulletin->forumcache</em> variable now has a \'depth\' key for each forum,
		which can be used for producing the depthmark etc.</p>
		<p>Additionally, the <em>$vbulletin->forumcache</em> is now stored in the correct order</p>
	');

	global $vbulletin, $count;
	static $fcache, $i;
	if (!is_array($fcache))
	{
	// check to see if we have already got the results from the database
		$fcache = array();
		$vbulletin->forumcache = array();
		$forums = $vbulletin->db->query_read("SELECT * FROM " . TABLE_PREFIX . "forum ORDER BY displayorder");
		while ($forum = $vbulletin->db->fetch_array($forums))
		{
			$fcache["$forum[parentid]"]["$forum[forumid]"] = $forum;
		}
	}

	// database has already been queried
	if (is_array($fcache["$forumid"]))
	{
		foreach ($fcache["$forumid"] AS $forum)
		{
			$vbulletin->forumcache["$forum[forumid]"] = $forum;
			$vbulletin->forumcache["$forum[forumid]"]['depth'] = $depth;
			unset($fcache["$forumid"]);
			cache_forums($forum['forumid'], $depth + 1);
		} // end foreach ($fcache["$forumid"] AS $key1 => $val1)
	} // end if (found $fcache["$forumid"])
}

// ###################### Start updateforumcount #######################
// updates forum counters and last post info
function build_forum_counters($forumid)
{
	global $vbulletin;

	$forumid = intval($forumid);
	$foruminfo = fetch_foruminfo($forumid);

	// get counters
	$threads = $vbulletin->db->query_first("
		SELECT COUNT(*) AS threads, SUM(replycount) AS replies
		FROM " . TABLE_PREFIX . "thread AS thread
		WHERE forumid = $forumid AND
			visible = 1 AND
			open <> 10
	");

	require_once(DIR . '/includes/functions_bigthree.php');
	$coventry = fetch_coventry('string');

	// get last thread
	$lastthread = $vbulletin->db->query_first("
		SELECT thread.*
		FROM " . TABLE_PREFIX . "thread AS thread
		WHERE forumid = $forumid AND
			visible = 1 AND
			open <> 10
			". ($coventry ? "AND thread.postuserid NOT IN ($coventry)" : '') . "
		ORDER BY lastpost DESC
		LIMIT 1
	");

	$tachy_posts = array();
	$tachy_db = $vbulletin->db->query_read("
		SELECT thread.*, tachythreadpost.*
		FROM " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost,
			" . TABLE_PREFIX . "thread AS thread
		WHERE tachythreadpost.threadid = thread.threadid
			AND thread.forumid = $forumid
			AND tachythreadpost.lastpost > " . intval($lastthread['lastpost']) . "
		ORDER BY tachythreadpost.lastpost DESC
	");
	while ($tachy = $vbulletin->db->fetch_array($tachy_db))
	{
		if (!isset($tachy_posts["$tachy[userid]"]))
		{
			$tachy_posts["$tachy[userid]"] = $tachy;
		}
	}

	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "tachyforumpost
		WHERE forumid = $forumid
	");

	$tachy_replace = array();
	foreach ($tachy_posts AS $tachy)
	{
		$tachy_replace[] = "
			($tachy[userid], $forumid, $tachy[lastpost],
			'" . $vbulletin->db->escape_string($tachy['lastposter']) ."',
			'" . $vbulletin->db->escape_string($tachy['title']) . "',
			$tachy[threadid],
			$tachy[iconid])
		";
	}

	if ($tachy_replace)
	{
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "tachyforumpost
				(userid, forumid, lastpost, lastposter, lastthread, lastthreadid, lasticonid)
			VALUES
				" . implode(', ', $tachy_replace)
		);
	}

	// update forum
	$forumdm =& datamanager_init('Forum', $vbulletin, ERRTYPE_SILENT);
	$forumdm->set_existing($foruminfo);
	$forumdm->set('threadcount', $threads['threads'], true, false);
	$forumdm->set('replycount', $threads['threads'] + $threads['replies'], true, false);
	$forumdm->set('lastpost', $lastthread['lastpost'], true, false);
	$forumdm->set('lastposter', $lastthread['lastposter'], true, false);
	$forumdm->set('lastthread', $lastthread['title'], true, false);
	$forumdm->set('lastthreadid', $lastthread['threadid'], true, false);
	$forumdm->set('lasticonid', ($lastthread['pollid'] ? -1 : $lastthread['iconid']), true, false);
	$forumdm->save();
	unset($forumdm);
}

// ###################### Start updatethreadcount #######################
function build_thread_counters($threadid)
{
	global $vbulletin, $threadcache;

	$threadid = intval($threadid);

	$replies = $vbulletin->db->query_first("
		SELECT
			SUM(IF(visible = 1, attach, 0)) AS attachsum,
			SUM(IF(visible = 1, 1, 0)) AS visible,
			SUM(IF(visible = 0, 1, 0)) AS hidden,
			SUM(IF(visible = 2, 1, 0)) AS deleted
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = $threadid
	");
	if ($replies['visible'] == 0)
	{	// All threads should have at least the first post visible, no matter the thread state
		return;
	}

/*
	$replies = $vbulletin->db->query_first("
		SELECT COUNT(*) - 1 AS replies, SUM(post.attach) AS attachsum
		FROM " . TABLE_PREFIX . "post AS post
		WHERE threadid = $threadid AND
			post.visible = 1
	");
	if ($replies['replies'] == -1)
	{
		return; // no posts, thread most likely isn't valid anyway
	}
*/

	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "tachythreadpost
		WHERE threadid = $threadid
	");

	// read the last posts out of the thread, looking for tachy'd users.
	// if we find one, give them that as the last post but continue looking
	// for the displayed last post.
	$offset = 0;
	$users_processed = array();
	do
	{
		$lastposts = $vbulletin->db->query_first("
			SELECT user.username, post.userid, post.username AS postuser, post.dateline
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = post.userid
			WHERE post.threadid = $threadid AND
				post.visible = 1
			ORDER BY dateline DESC
			LIMIT $offset, 1
		");

		if (in_coventry($lastposts['userid'], true))
		{
			$offset++;

			if (!isset($users_processed["$lastposts[userid]"]))
			{
				$vbulletin->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "tachythreadpost
						(userid, threadid, lastpost, lastposter)
					VALUES
						($lastposts[userid],
						$threadid,
						" . intval($lastposts['dateline']) . ",
						'" . $vbulletin->db->escape_string(empty($lastposts['username']) ? $lastposts['postuser'] : $lastposts['username']) . "')
				");
				$users_processed["$lastposts[userid]"] = true;
			}
		}
		else
		{
			break;
		}
	}
	while ($lastposts);

	$firstposts = $vbulletin->db->query_first("
		SELECT post.postid, post.userid, user.username, post.username AS postuser, post.dateline
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = post.userid
		WHERE post.threadid = $threadid AND
			post.visible = 1
		ORDER BY dateline ASC
		LIMIT 1
	");

	if ($lastposts)
	{
		$lastposter = (empty($lastposts['username']) ? $lastposts['postuser'] : $lastposts['username']);
		$lastposttime = intval($lastposts['dateline']);
	}
	else
	{
		// this will occur on a thread posted by a tachy user.
		// since only they will see the thread, the lastpost info can say their name
		$lastposter = (empty($firstposts['username']) ? $firstposts['postuser'] : $firstposts['username']);
		$lastposttime = intval($firstposts['dateline']);
	}

	$firstposter = (empty($firstposts['username']) ? $firstposts['postuser'] : $firstposts['username']);
	$firstposterid = intval($firstposts['userid']);
	$firstpostid = intval($firstposts['postid']);
	$threadcreation = $firstposts['dateline'];

	$ratings = $vbulletin->db->query_first("
		SELECT
			COUNT(*) AS votenum,
			SUM(vote) AS votetotal
		FROM " . TABLE_PREFIX . "threadrate
		WHERE threadid = $threadid
	");

	$threadinfo = array('threadid' => $threadid);

	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set('firstpostid', $firstpostid, true, false);
	$threadman->set('postuserid', $firstposterid, true, false);
	$threadman->set('postusername', $firstposter, true, false);
	$threadman->set('lastpost', $lastposttime, true, false);
	$threadman->set('replycount', $replies['visible'] - 1, true, false);
	$threadman->set('hiddencount', $replies['hidden'], true, false);
	//$threadman->set('deletedcount', $replies['deleted'], true, false); // Not implemented
	$threadman->set('attach', $replies['attachsum'], true, false);
	$threadman->set('dateline', $threadcreation, true, false);
	$threadman->set('lastposter', $lastposter, true, false);
	$threadman->set('votenum', $ratings['votenum'], true, false);
	$threadman->set('votetotal', intval($ratings['votetotal']), true, false);
	$threadman->save();

}

// ###################### Start unapprovepost #######################
function unapprove_post($postid, $countposts, $dolog = true, $postinfo = NULL, $threadinfo = NULL)
{
	global $vbulletin, $vbphrase;

	// Valid postinfo array will contain: postid, threadid, visible, userid
	// Invalid post or post is not deleted
	if (!$postinfo AND !$postinfo = fetch_postinfo($postid))
	{
		return;
	}

	// Valid threadinfo array will contain: threadid, forumid, visible, firstpostid
	if (!$threadinfo AND !$threadinfo = fetch_threadinfo($postinfo['threadid']))
	{
		return;
	}

	if ($threadinfo['firstpostid'] == $postid)
	{
		// unapprove_thread
		unapprove_thread($threadinfo['threadid'], $countposts, $dolog, $threadinfo);
		return;
	}

	// Post is already moderated
	if (!$postinfo['visible'])
	{
		return;
	}

	// Only decrement post for a visible post and visible thread in a counting forum
	if ($countposts AND $postinfo['userid'] AND $threadinfo['visible'] == 1 AND $postinfo['visible'] == 1)
	{
		$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
		$userdata->set_existing($postinfo);
		$userdata->set('posts', 'posts - 1', false);
		$userdata->save();
		unset($userdata);
	}

	if ($postinfo['visible'] == 2)
	{	// remove deletionlog record
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "deletionlog
			WHERE primaryid = $postinfo[postid]
				AND type = 'post'
		");
	}

	// Insert Moderation record
	$vbulletin->db->query_write("
		INSERT IGNORE INTO " . TABLE_PREFIX . "moderation
		(postid, threadid, type)
		VALUES
		($postinfo[postid], $threadinfo[threadid], 'reply')
	");

	$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
	$postman->set_existing($postinfo);
	$postman->set('visible', 0);
	$postman->save();

	build_thread_counters($postinfo['threadid']);
	build_forum_counters($threadinfo['forumid']);

	fetch_phrase_group('threadmanage');
	$postinfo['forumid'] = $threadinfo['forumid'];

	require_once(DIR . '/includes/functions_log_error.php');
	log_moderator_action($postinfo, 'unapproved_post');
}

// ###################### Start approvepost #######################
function approve_post($postid, $countposts, $dolog = true, $postinfo = NULL, $threadinfo = NULL)
{
	global $vbulletin, $vbphrase;

	// Valid postinfo array will contain: postid, threadid, visible, userid
	// Invalid post or post is not deleted
	if (!$postinfo AND !$postinfo = fetch_postinfo($postid))
	{
		return;
	}

	// Valid threadinfo array will contain: threadid, forumid, visible, firstpostid
	if (!$threadinfo AND !$threadinfo = fetch_threadinfo($postinfo['threadid']))
	{
		return;
	}

	if ($threadinfo['firstpostid'] == $postid)
	{
		// approve_thread
		approve_thread($threadinfo['threadid'], $countposts, $dolog, $threadinfo);
		return;
	}

	// Post is not moderated
	if ($postinfo['visible'])
	{
		return;
	}

	// Only increment post for a visible thread in a counting forum
	if ($countposts AND $postinfo['userid'] AND $threadinfo['visible'] == 1)
	{
		$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
		$userdata->set_existing($postinfo);
		$userdata->set('posts', 'posts + 1', false);
		$userdata->save();
		unset($userdata);
	}

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "moderation WHERE postid = $postid AND type = 'reply'");

	$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
	$postman->set_existing($postinfo);
	$postman->set('visible', 1);
	$postman->save();

	build_thread_counters($postinfo['threadid']);
	build_forum_counters($threadinfo['forumid']);

	fetch_phrase_group('threadmanage');
	$postinfo['forumid'] = $threadinfo['forumid'];

	require_once(DIR . '/includes/functions_log_error.php');
	log_moderator_action($postinfo, 'approved_post');
}

// ###################### Start approvethread #######################
function approve_thread($threadid, $countposts = true, $dolog = true, $threadinfo = NULL)
{
	global $vbulletin, $vbphrase;

	// Valid threadinfo array will contain: threadid, forumid, visible
	if (!$threadinfo AND !$threadinfo = fetch_threadinfo($threadid))
	{
		return;
	}

	if ($threadinfo['visible'])
	{	// thread is already approved or deleted
		return;
	}

	if ($dolog)
	{
		// is a moderator, so log it
		fetch_phrase_group('threadmanage');

		require_once(DIR . '/includes/functions_log_error.php');
		log_moderator_action($threadinfo, 'approved_thread');
	}

	// Increment posts if this is a counting forum
	if ($countposts)
	{
		$userbyuserid = array();
		$postids = '';
		$posts = $vbulletin->db->query_read("
			SELECT userid, visible
			FROM " . TABLE_PREFIX . "post AS post
			WHERE threadid = $threadid
				AND visible = 1
				AND userid > 0
		");
		while ($post = $vbulletin->db->fetch_array($posts))
		{
			if (!isset($userbyuserid["$post[userid]"]))
			{
				$userbyuserid["$post[userid]"] = 1;
			}
			else
			{
				$userbyuserid["$post[userid]"]++;
			}
		}

		if (!empty($userbyuserid))
		{
			$userbypostcount = array();
			foreach ($userbyuserid AS $postuserid => $postcount)
			{
				$alluserids .= ",$postuserid";
				$userbypostcount["$postcount"] .= ",$postuserid";
			}
			foreach($userbypostcount AS $postcount => $userids)
			{
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount\n";
			}

			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET posts = posts +
				CASE
					$casesql
					ELSE 0
				END
				WHERE userid IN (0$alluserids)
			");
		}
	}

	// Delete moderation record
	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "moderation
		WHERE type = 'thread'
			AND threadid = $threadid
	");

	// Set thread visible
	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set('visible', 1);
	$threadman->save();
	unset($threadman);

	return;
}

// ###################### Start unapprovethread #######################
function unapprove_thread($threadid, $countposts = true, $dolog = true, $threadinfo = NULL)
{
	global $vbulletin, $vbphrase;

	// Valid threadinfo array will contain: threadid, forumid, visible, firstpostid
	if (!$threadinfo AND !$threadinfo = fetch_threadinfo($threadid))
	{
		return;
	}

	if (!$threadinfo['visible'])
	{	// thread is already moderated
		return;
	}

	if ($dolog)
	{
		// is a moderator, so log it
		fetch_phrase_group('threadmanage');

		require_once(DIR . '/includes/functions_log_error.php');
		log_moderator_action($threadinfo, 'unapproved_thread');
	}

	// Decrement posts if this is a counting forum and the thread is currently visible

	if ($countposts AND $threadinfo['visible'] == 1)
	{
		$userbyuserid = array();
		$postids = '';
		$posts = $vbulletin->db->query_read("
			SELECT userid, visible
			FROM " . TABLE_PREFIX . "post AS post
			WHERE threadid = $threadid
				AND visible = 1
				AND userid > 0
		");
		while ($post = $vbulletin->db->fetch_array($posts))
		{
			if (!isset($userbyuserid["$post[userid]"]))
			{
				$userbyuserid["$post[userid]"] = -1;
			}
			else
			{
				$userbyuserid["$post[userid]"]--;
			}
		}

		if (!empty($userbyuserid))
		{ // if the thread is already deleted or moderated, the posts have already been reduced
			$userbypostcount = array();
			foreach ($userbyuserid AS $postuserid => $postcount)
			{
				$alluserids .= ",$postuserid";
				$userbypostcount["$postcount"] .= ",$postuserid";
			}
			foreach($userbypostcount AS $postcount => $userids)
			{
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount\n";
			}

			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET posts = posts +
				CASE
					$casesql
					ELSE 0
				END
				WHERE userid IN (0$alluserids)
			");
		}
	}

	if ($threadinfo['visible'] == 2)
	{	// This is a deleted thread - remove deletionlog entry
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "deletionlog
			WHERE primaryid = $threadid
				AND type = 'thread'
		");
	}

	// Insert moderation record
	$vbulletin->db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "moderation
		(threadid, postid, type)
		VALUES
		($threadid, $threadinfo[firstpostid], 'thread')
	");

	// Set thread invisible
	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set('visible', 0);
	$threadman->save();
	unset($threadman);

	return;
}

// ###################### Start deletethread #######################
function delete_thread($threadid, $countposts = true, $physicaldel = true, $delinfo = NULL, $dolog = true, $threadinfo = NULL)
{
	global $vbulletin, $vbphrase;

	// valid threadinfo array will contain: threadid, forumid, visible, open, pollid, title
	if (!$threadinfo AND !$threadinfo = fetch_threadinfo($threadid))
	{
		return;
	}

	if (!$physicaldel AND $threadinfo['visible'] == 2)
	{	// thread is already soft deleted
		return;
	}

	if ($dolog AND can_moderate())
	{
		// is a moderator, so log it
		fetch_phrase_group('threadmanage');

		if ($threadinfo['open'] == 10)
		{
			$type = 'thread_redirect_removed';
		}
		else if (!$physicaldel)
		{
			$type = 'thread_softdeleted';
		}
		else
		{
			$type = 'thread_removed';
		}

		require_once(DIR . '/includes/functions_log_error.php');
		log_moderator_action($threadinfo, $type);
	}

	// Grab the inline moderation cookie (if it exists)
	if ($physicaldel)
	{
		$vbulletin->input->clean_array_gpc('c', array(
			'vbulletin_inlinethread' => TYPE_STR,
			'vbulletin_inlinepost'   => TYPE_STR,
		));

		if (!empty($vbulletin->GPC['vbulletin_inlinethread']) AND !headers_sent())
		{
			$newcookie = array();
			$found = false;
			$temp = explode('-', $vbulletin->GPC['vbulletin_inlinethread']);
			foreach($temp AS $inlinethreadid)
			{
				if ($inlinethreadid == $threadid)
				{
					$found = true;
				}
				else
				{
					$newcookie[] = intval($inlinethreadid);
				}
			}

			// this thread is in the inline thread cookie so delete it by rewriting cookie without it
			if ($found)
			{
				setcookie('vbulletin_inlinethread', implode('-', $newcookie), TIMENOW + 3600, '/');
			}
		}

		$plist = array();
		if (!empty($vbulletin->GPC['vbulletin_inlinepost']))
		{
			$temp = explode('-', $vbulletin->GPC['vbulletin_inlinepost']);
			foreach($temp AS $inlinepostid)
			{
				$plist["$inlinepostid"] = true;
			}
		}
	}

	$postids = '';
	$posts = $vbulletin->db->query_read("
		SELECT userid, postid, attach, visible
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = $threadid
	");

	$removepostid = array();
	$userbyuserid = array();
	while ($post = $vbulletin->db->fetch_array($posts))
	{
		if ($countposts AND $post['visible'] == 1 AND $post['userid'])
		{ // deleted posts have already been subtracted, ignore guest posts, hidden posts never had posts added
			if (!isset($userbyuserid["$post[userid]"]))
			{
				$userbyuserid["$post[userid]"] = 1;
			}
			else
			{
				$userbyuserid["$post[userid]"]++;
			}
		}
		$postids .= $post['postid'] . ',';

		if ($physicaldel)
		{
			delete_post_index($post['postid']); //remove search engine entries

			// mark posts that are in the inline moderation cookie
			if (!empty($plist["$post[postid]"]))
			{
				$removepostid["$post[postid]"] = true;
			}
		}
	}

	if (!empty($userbyuserid) AND $threadinfo['visible'] == 1)
	{ // if the thread is moderated the posts have already been reduced
		$userbypostcount = array();
		foreach ($userbyuserid AS $postuserid => $postcount)
		{
			$alluserids .= ",$postuserid";
			$userbypostcount["$postcount"] .= ",$postuserid";
		}
		foreach($userbypostcount AS $postcount => $userids)
		{
			$casesql .= " WHEN userid IN (0$userids) AND posts > $postcount THEN posts - $postcount\n";
		}

		// postcounts are already negative, so we don't want to do -(-1)
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX ."user SET
				posts = CASE $casesql ELSE 0 END
			WHERE userid IN (0$alluserids)
		");
	}

	if (!empty($postids))
	{
		if ($physicaldel OR (!$delinfo['keepattachments'] AND can_moderate($threadinfo['forumid'], 'canremoveposts')))
		{
			$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_SILENT);
			$attachdata->condition = "attachment.postid IN ($postids" . "0)";
			$attachdata->delete();
		}
	}

	if (!$threadinfo['visible'])
	{
		// Delete Moderation
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "moderation WHERE threadid = $threadid AND type = 'thread'");
	}

	if (!$physicaldel)
	{
		if (!is_array($delinfo))
		{
			$delinfo = array('userid' => $vbulletin->userinfo['userid'], 'username' => $vbulletin->userinfo['username'], 'reason' => '');
		}
		/*insert query*/
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "deletionlog
			(primaryid, type, userid, username, reason)
			VALUES
			($threadinfo[threadid], 'thread', $delinfo[userid], '" . $vbulletin->db->escape_string($delinfo['username']) . "',
			 '" . $vbulletin->db->escape_string(htmlspecialchars_uni(fetch_censored_text($delinfo['reason']))) . "')
		");

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threadman->set_existing($threadinfo);
		$threadman->set('visible', 2);
		if (!$delinfo['keepattachments'])
		{
			$threadman->set('attach', 0);
		}
		$threadman->save();

		// Delete any redirects to this thread
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "thread WHERE open = 10 AND pollid = $threadid");

		return;
	}

	if (!empty($postids))
	{
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "post WHERE postid IN ($postids" . "0)");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "post_parsed WHERE postid IN ($postids" . "0)");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "reputation WHERE postid IN ($postids" . "0)");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "moderation WHERE postid IN ($postids" . "0)");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "editlog WHERE postid IN ($postids" . "0)");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE type='post' AND primaryid IN ($postids" . "0)");

		// remove deleted posts from inline moderation cookie
		if (!empty($removepostid) AND !headers_sent())
		{
			$newcookie = array();
			foreach($plist AS $inlinepostid => $value)
			{
				if (empty($removepostid["$inlinepostid"]))
				{
					$newcookie[] = intval($inlinepostid);
				}
			}

			setcookie('vbulletin_inlinepost', implode('-', $newcookie), TIMENOW + 3600, '/');
		}

	}
	if ($threadinfo['pollid'] != 0 AND $threadinfo['open'] != 10)
	{
		$pollman =& datamanager_init('Poll', $vbulletin, ERRTYPE_SILENT);
		$pollid = array ('pollid' => $threadinfo['pollid']);
		$pollman->set_existing($pollid);
		$pollman->delete();

	}
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "thread WHERE threadid = $threadid");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "thread WHERE open=10 AND pollid = $threadid"); // delete redirects
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $threadid AND type = 'thread'");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "threadrate WHERE threadid = $threadid");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE threadid = $threadid");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "tachythreadpost WHERE threadid = $threadid");
	$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "moderatorlog SET threadtitle = '". $vbulletin->db->escape_string($threadinfo['title']) ."' WHERE threadid = $threadid");

}

// ###################### Start deletepost #######################
function delete_post($postid, $countposts = true, $threadid = 0, $physicaldel = true, $delinfo = NULL, $dolog = true)
{
	global $vbulletin, $vbphrase, $threadinfo;

	$postid = intval($postid);
	$threadid = intval($threadid);

	if (!is_array($delinfo))
	{
		$delinfo = array(
			'userid' => $vbulletin->userinfo['userid'],
			'username' => $vbulletin->userinfo['username'],
			'reason' => '',
			'keepattachments' => false
		);
	}
	else
	{
		if (!$delinfo['userid'])
		{
			$delinfo['userid'] = $vbulletin->userinfo['userid'];
		}
		if (!$delinfo['username'])
		{
			$delinfo['username'] = $vbulletin->userinfo['username'];
		}
	}

	if ($postinfo = fetch_postinfo($postid))
	{
		$threadinfo = fetch_threadinfo($postinfo['threadid']);

		if (!$physicaldel AND $post['visible'] == 2)
		{	// post is already soft deleted
			return;
		}

		if ($threadinfo['firstpostid'] == $postid)
		{
			if (!$physicaldel AND $threadinfo['visible'] == 2)
			{	// thread is already soft deleted
				return;
			}

			// delete thread
			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$threadman->set_existing($threadinfo);
			$threadman->delete($countposts, $physicaldel, $delinfo);
			unset($threadman);
			return;
		}

		if (can_moderate() AND $dolog)
		{
			fetch_phrase_group('threadmanage');

			if (!$physicaldel)
			{
				$type = 'post_x_by_y_softdeleted';
			}
			else
			{
				$type = 'post_x_by_y_removed';
			}

			$postinfo['forumid'] = $threadinfo['forumid'];
			require_once(DIR . '/includes/functions_log_error.php');
			log_moderator_action($postinfo, $type, array($postinfo['title'], $postinfo['username']));
		}

		if ($countposts AND $postinfo['visible'] == 1 AND $threadinfo['visible'] == 1 AND $postinfo['userid'])
		{	// deleted posts have already been decremented and hidden posts were never incremented (as of 3.5 at least)
			// init user data manager
			$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdata->set_existing($postinfo);
			$userdata->set('posts', 'posts - 1', false);
			$userdata->save();
			unset($userdata);
		}

		if ($postinfo['attach'])
		{
			if ($physicaldel OR (!$delinfo['keepattachments'] AND can_moderate($threadinfo['forumid'], 'canremoveposts')))
			{
				$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_SILENT);
				$attachdata->condition = "attachment.postid = $postinfo[postid]";
				$attachdata->delete();
			}
		}

		if (!$physicaldel)
		{
			/*insert query*/
			$vbulletin->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "deletionlog
				(primaryid, type, userid, username, reason)
				VALUES
				($postinfo[postid], 'post', $delinfo[userid], '" . $vbulletin->db->escape_string($delinfo['username']) . "',
				'" . $vbulletin->db->escape_string(fetch_censored_text(htmlspecialchars_uni($delinfo['reason']))) . "')
			");

			$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$postman->set_existing($postinfo);
			$postman->set('visible', 2);
			$postman->save();

			if (!$postinfo['visible'])
			{
				$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "moderation WHERE postid = $postinfo[postid] AND type = 'reply'");
			}

			return;
		}

		// Delete any postid entries from the inline moderation cookie
		$vbulletin->input->clean_array_gpc('c', array(
			'vbulletin_inlinepost'   => TYPE_STR,
		));

		if (!empty($vbulletin->GPC['vbulletin_inlinepost']) AND !headers_sent())
		{
			$newcookie = array();
			$found = false;
			$temp = explode('-', $vbulletin->GPC['vbulletin_inlinepost']);
			foreach($temp AS $inlinepostid)
			{
				if ($inlinepostid == $postid)
				{
					$found = true;
				}
				else
				{
					$newcookie[] = $inlinepostid;
				}
			}

			// this post is in the inline post cookie so delete it by rewriting cookie without it
			if ($found)
			{
				setcookie('vbulletin_inlinepost', implode('-', $newcookie), TIMENOW + 3600, '/');
			}
		}

		// delete post hash when physically deleting a post - last argument is type
		$dupehash = md5($threadinfo['forumid'] . $postinfo['title'] . $postinfo['pagetext'] . $postinfo['userid'] . 'reply');

		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "posthash
			WHERE userid = $postinfo[userid] AND
			dupehash = '" . $vbulletin->db->escape_string($dupehash) . "' AND
			dateline > " . (TIMENOW - 300)
		);

		// Hook this post's children up to it's parent so they aren't orphaned. Foster parents I guess.
		if ($postinfo['parentid'] == 0)
		{
			if ($firstchild = $vbulletin->db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = $postinfo[threadid] AND
				parentid = $postid
				ORDER BY dateline
				LIMIT 1
			"))
			{
				$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$postman->set_existing($firstchild);
				$postman->set('parentid', 0);
				$postman->save();
				unset($postman);

				$postinfo['parentid'] = $firstchild['postid'];
			}
		}

		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "post SET
				parentid = " . intval($postinfo['parentid']) . "
			WHERE threadid = $postinfo[threadid]
				AND parentid = $postid
		");

		delete_post_index($postid);
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "post WHERE postid = $postid");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "post_parsed WHERE postid = $postid");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $postid AND type = 'post'");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "editlog WHERE postid = $postid");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "reputation WHERE postid = $postid");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "moderation WHERE postid = $postid AND type = 'reply'");
	}
}

// ###################### Start indexword #######################
function is_index_word($word)
{
	global $vbulletin, $badwords, $goodwords;
	static $compiledlist;

	require_once(DIR . '/includes/searchwords.php'); // get the stop word list

	if (!$compiledlist)
	{
		$badwords = array_merge($badwords, explode(' ', $vbulletin->options['badwords']));
		$compiledlist = true;
	}

	// is the word in the goodwords array?
	if (in_array(strtolower($word), $goodwords))
	{
		return 1;
	}
	else
	{
		// is the word outside the min/max char lengths for indexing?
		$wordlength = vbstrlen($word);
		if ($wordlength < $vbulletin->options['minsearchlength'] OR $wordlength > $vbulletin->options['maxsearchlength'])
		{
			return 0;
		}
		// is the word a common/bad word?
		else if (in_array(strtolower($word), $badwords))
		{
			return false;
		}
		// word is good
		else
		{
			return 1;
		}
	}

}

// ###################### Start indexpost #######################
function build_post_index($postid, $foruminfo, $firstpost = -1, $post = false)
{
	global $vbulletin;

	if ($vbulletin->options['fulltextsearch'])
	{
		return;
	}

	if (!empty($post['postid']))
	{
		$postid = $post['postid'];
	}

	$postid = intval($postid);
	if (!$postid)
	{
		// no postid, don't know what to do anyway
		return;
	}

	if ($foruminfo['indexposts'])
	{
		global $vbulletin;
		static $firstpst;

		if (is_array($post))
		{
			if (isset($post['threadtitle']))
			{
				$threadinfo = array('title' => $post['threadtitle']);
			}
		}
		else
		{
			$post = $vbulletin->db->query_first("
				SELECT postid, post.title, pagetext, post.threadid, thread.title AS threadtitle
				FROM " . TABLE_PREFIX . "post AS post
				INNER JOIN " . TABLE_PREFIX . "thread AS thread USING(threadid)
				WHERE postid = $postid
			");
			$threadinfo = array('title' => $post['threadtitle']);
		}

		if (isset($firstpst["$post[threadid]"]))
		{
			if ($firstpst["$post[threadid]"] == $postid)
			{
				$firstpost = 1;
			}
			else
			{
				$firstpost = 0;
			}
		}

		if ($firstpost == -1)
		{
			$getfirstpost = $vbulletin->db->query_first("
				SELECT MIN(postid) AS postid
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = " . intval($post['threadid'])
			);
			if ($getfirstpost['postid'] == $postid)
			{
				$firstpost = 1;
			}
			else
			{
				$firstpost = 0;
			}
		}


		$allwords = '';

		if ($firstpost)
		{
			if (!is_array($threadinfo))
			{
				$threadinfo = $vbulletin->db->query_first("
					SELECT title
					FROM " . TABLE_PREFIX . "thread
					WHERE threadid = $post[threadid]
				");
			}

			$firstpst["$post[threadid]"] = $postid;

			$words = fetch_postindex_text($threadinfo['title']);
			$allwords .= $words;
			$wordarray = explode(' ', $words);
			foreach ($wordarray AS $word)
			{
				#$scores["$word"] += $vbulletin->options['threadtitlescore'];
				$intitle["$word"] = 2;
				$scores["$word"]++;
			}
		}

		$words = fetch_postindex_text($post['title']);
		$allwords .= ' ' . $words;
		$wordarray = explode(' ', $words);

		foreach ($wordarray AS $word)
		{
			#$scores["$word"] += $vbulletin->options['posttitlescore'];
			if (empty($intitle["$word"]))
			{
				$intitle["$word"] = 1;
			}
			$scores["$word"]++;
		}

		$words = fetch_postindex_text($post['pagetext']);
		$allwords .= ' ' . $words;
		$wordarray = explode(' ', $words);
		foreach ($wordarray AS $word)
		{
			$scores["$word"]++;
		}

		$getwordidsql = "title IN ('" . str_replace(" ", "','", $allwords) . "')";
		$words = $vbulletin->db->query_read("SELECT wordid, title FROM " . TABLE_PREFIX . "word WHERE $getwordidsql");
		while ($word = $vbulletin->db->fetch_array($words))
		{
			$word['title'] = vbstrtolower($word['title']);
			$wordcache["$word[title]"] = $word['wordid'];
		}
		$vbulletin->db->free_result($words);

		$insertsql = '';
		$newwords = '';
		$newtitlewords = '';

		foreach ($scores AS $word => $score)
		{
			if (!is_index_word($word))
			{
				unset($scores["$word"]);
				continue;
			}

			// prevent score going over 255 for overflow control
			if ($score > 255)
			{
				$scores["$word"] = 255;
			}
			// make sure intitle score is set
			$intitle["$word"] = intval($intitle["$word"]);

			if ($word)
			{
				if (isset($wordcache["$word"]))
				{ // Does this word already exist in the word table?
					$insertsql .= ", (" . $vbulletin->db->escape_string($wordcache["$word"]) . ", $postid, $score, $intitle[$word])"; // yes so just add a postindex entry for this post/word
					unset($scores["$word"], $intitle["$word"]);
				}
				else
				{
					$newwords .= $word . ' '; // No so add it to the word table
				}
			}
		}

		if (!empty($insertsql))
		{
			$insertsql = substr($insertsql, 1);
			/*insert query*/
			$vbulletin->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "postindex
				(wordid, postid, score, intitle)
				VALUES
				$insertsql
			");
		}

		$newwords = trim($newwords);
		if ($newwords)
		{
			$insertwords = "('" . str_replace(" ", "'),('", $newwords) . "')";
			/*insert query*/
			$vbulletin->db->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "word (title) VALUES $insertwords");
			$selectwords = "title IN ('" . str_replace(" ", "','", $newwords) . "')";

			$scoressql = 'CASE title';
			foreach ($scores AS $word => $score)
			{
				$scoressql .= " WHEN '" . $vbulletin->db->escape_string($word) . "' THEN $score";
			}
			$scoressql .= ' ELSE 1 END';

			$titlesql = 'CASE title';
			foreach($intitle AS $word => $intitlescore)
			{
				$titlesql .= " WHEN '" . $vbulletin->db->escape_string($word) . "' THEN $intitlescore";
			}
			$titlesql .= ' ELSE 0 END';

			/*insert query*/
			$vbulletin->db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "postindex
				(wordid, postid, score, intitle)
				SELECT DISTINCT wordid, $postid, $scoressql, $titlesql
				FROM " . TABLE_PREFIX . "word
				WHERE $selectwords
			");
		}
	}
}

// ###################### Start searchtextstrip #######################
function fetch_postindex_text($text)
{
	static $find, $replace;
	global $vbulletin;

	// remove all bbcode tags
	$text = strip_bbcode($text);

	// make lower case and pad with spaces
	//$text = strtolower(" $text ");
	$text = " $text ";

	if (!is_array($find))
	{
		$find = array(
			'#[()"\'!\#{};<>]|\\\\|:(?!//)#s',			// allow through +- for boolean operators and strip colons that are not part of URLs
			"#([.,?&/_]+)( |\.|\r|\n|\t)#s",			// \?\&\,
			'#\s+(-+|\++)+([^\s]+)#si',					// remove leading +/- characters
			'#(\s?\w*\*\w*)#s',							// remove words containing asterisks
			'#\s+#s',									// whitespace to space
		);
		$replace = array(
			'',		// allow through +- for boolean operators and strip colons that are not part of URLs
			' ',	// \?\&\,
			' \2',	// remove leading +/- characters
			'',		// remove words containing asterisks
			' ',	// whitespace to space
		);
	}

	$text = strip_tags($text); // clean out HTML as it's probably not going to be indexed well anyway

	// use regular expressions above
	$text = preg_replace($find, $replace, $text);

	return trim(vbstrtolower($text));
}

// ###################### Start unindexpost #######################
function delete_post_index($postid, $title = '', $pagetext = '')
{
	global $vbulletin;

	if ($vbulletin->options['fulltextsearch'])
	{
		return;
	}

	$postid = intval($postid);
	// get the data
	if (empty($pagetext))
	{
		$post = $vbulletin->db->query_first("
			SELECT postid, threadid, title, pagetext
			FROM " . TABLE_PREFIX . "post
			WHERE postid = $postid
		");
	}
	else
	{
		$post['postid'] = $postid;
		$post['title'] = $title;
		$post['pagetext'] = $pagetext;
	}

	// get word ids from table
	$allwords = $post['title'] . ' ' . $post['pagetext'];

	$allwords = fetch_postindex_text($allwords);

	$wordarray = explode(' ', $allwords);

	$getwordidsql = "title IN ('" . str_replace(" ", "','", $allwords) . "')";
	$words = $vbulletin->db->query_read("SELECT wordid, title FROM " . TABLE_PREFIX . "word WHERE $getwordidsql");

	if ($vbulletin->db->num_rows($words))
	{
		$wordids = '';
		while ($word = $vbulletin->db->fetch_array($words))
		{
			$wordids .= ',' . $word['wordid'];
		}

		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "postindex WHERE wordid IN (0$wordids) AND postid = " . $post['postid']);
	}
}

// ###################### Start saveuserstats #######################
// Save user count & newest user into template
function build_user_statistics()
{
	global $vbulletin;

	if ($vbulletin->options['activememberdays'] > 0)
	{
		$sumsql = "SUM(IF(lastvisit >= " . (TIMENOW - $vbulletin->options['activememberdays'] * 86400) . ", 1, 0)) AS active,";
	}
	else
	{
		$sumsql = '';
	}

	// get total members
	$members = $vbulletin->db->query_first("
		SELECT
		$sumsql
		COUNT(*) AS users,
		MAX(userid) AS maxid
		FROM " . TABLE_PREFIX . "user
	");

	// get newest member
	$newuser = $vbulletin->db->query_first("SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE userid = $members[maxid]");

	// make a little array with the data
	$values = array(
		'numbermembers' => $members['users'],
		'activemembers' => $members['active'],
		'newusername' => $newuser['username'],
		'newuserid' => $newuser['userid']
	);

	// update the special template
	build_datastore('userstats', serialize($values));

	return $values;
}

// ###################### Start getbirthdays #######################
function build_birthdays()
{
	global $stylevar, $vbulletin;

	$storebirthdays = array();

	$serveroffset = date('Z', TIMENOW) / 3600;

	$fromdate = getdate(TIMENOW + (-12 - $serveroffset) * 3600);
	$storebirthdays['day1'] = date('Y-m-d', TIMENOW + (-12 - $serveroffset) * 3600 );

	$todate = getdate(TIMENOW + (12 - $serveroffset) * 3600);
	$storebirthdays['day2'] = date('Y-m-d', TIMENOW + (12 - $serveroffset) * 3600);

	$todayneggmt = date('m-d', TIMENOW + (-12 - $serveroffset) * 3600);
	$todayposgmt = date('m-d', TIMENOW + (12 - $serveroffset) * 3600);

	// Seems quicker to grab the ids rather than doing a JOIN
	$usergroupids = 0;
	foreach($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		if ($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['showbirthday'])
		{
			$usergroupids .= ", $usergroupid";
		}
	}

	// if admin wants to only show birthdays for users who have
	// been active within the last $vbulletin->options[birthdaysdatecut] days...
	if ($vbulletin->options['activememberdays'] > 0 AND ($vbulletin->options['activememberoptions'] & 1))
	{
		$datecut = TIMENOW - (intval($vbulletin->options['activememberdays']) * 86400);
		$activitycut = "AND lastactivity >= $datecut";
	}
	else
	{
		$activitycut = '';
	}

	$bdays = $vbulletin->db->query_read("
		SELECT username, userid, birthday
		FROM " . TABLE_PREFIX . "user
		WHERE (birthday LIKE '$todayneggmt-%' OR birthday LIKE '$todayposgmt-%')
		AND usergroupid IN ($usergroupids)
		AND showbirthday = 2
		$activitycut
	");

	$year = date('Y');

	while ($birthday = $vbulletin->db->fetch_array($bdays))
	{
		$username = $birthday['username'];
		$userid = $birthday['userid'];
		$day = explode('-', $birthday['birthday']);
		if ($year > $day[2] AND $day[2] != '0000')
		{
			$age = $year - $day[2];
		}
		else
		{
			unset($age);
		}
		if ($todayneggmt == $day[0] . '-' . $day[1])
		{
			$day1 .= iif($day1, ', ') . "<!--rlm--><a href=\"member.php?" . $vbulletin->session->vars['sessionurl'] . "u=$userid\">$username</a>" . iif($age, " <!--rlm-->($age)");
		}
		else
		{
			$day2 .= iif($day2, ', ') . "<!--rlm--><a href=\"member.php?" . $vbulletin->session->vars['sessionurl'] . "u=$userid\">$username</a>" . iif($age, " <!--rlm-->($age)");
		}
	}
	$storebirthdays['users1'] = $day1;
	$storebirthdays['users2'] = $day2;

	build_datastore('birthdaycache', serialize($storebirthdays));

	return $storebirthdays;
}

// ###################### Start getevents #######################
function build_events()
{
	global $vbulletin, $vbphrase, $_CALENDAROPTIONS;

	if (!$vbulletin->options['showevents'])
	{
		return false;
	}

	$storeevents = array();

	// Store timestamp 24 hours before the current time and 24 hours after the showevent period
	$beginday = TIMENOW - 86400 * 2;
	$endday = TIMENOW + 86400 + 86400 * $vbulletin->options['showevents'];
	$storeevents['date'] = gmdate('n-j-Y' , $endday);

	// check if we have at least one calendar with holidays enabled
	if ($vbulletin->options['showholidays'])
	{
		$holidays = $vbulletin->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "holiday
		");
		while ($holiday = $vbulletin->db->fetch_array($holidays))
		{
			$holiday['dateline_from'] = $beginday;
			$holiday['dateline_to'] = $endday;
			$holiday['visible'] = 1;
			// $holiday['title'] = $vbphrase['holiday_title_' . $holiday['varname']];
			$holiday['eventid'] = 'h' . $holiday['holidayid'];
			$storeevents["$holiday[eventid]"] = $holiday;
		}
	}

	require_once(DIR . '/includes/functions_calendar.php');

	$events = $vbulletin->db->query_read("
		SELECT eventid, userid, event.title, recurring, recuroption, dateline_from, dateline_to, event.calendarid, IF (dateline_to = 0, 1, 0) AS singleday,
			dateline_from AS dateline_from_user, dateline_to AS dateline_to_user, utc
		FROM " . TABLE_PREFIX . "event AS event
		INNER JOIN " . TABLE_PREFIX . "calendar AS calendar USING (calendarid)
		WHERE ((dateline_to >= $beginday AND dateline_from < $endday) OR (dateline_to = 0 AND dateline_from >= $beginday AND dateline_from <= $endday ))
			AND visible = 1
			AND calendar.options & " . intval($_CALENDAROPTIONS['showupcoming']) . "
	");

	while ($event = $vbulletin->db->fetch_array($events))
	{
		$event['title'] = htmlspecialchars_uni($event['title']);
		$storeevents["$event[eventid]"] = $event;
	}

	build_datastore('eventcache', serialize($storeevents));

	return $storeevents;

}

// ###################### Start undeletethread #######################
function undelete_thread($threadid, $countposts = true, $threadinfo = NULL)
{
	global $vbulletin, $vbphrase;

	// Valid threadinfo array will contain: threadid, forumid, visible
	if (!$threadinfo AND !$threadinfo = fetch_threadinfo($threadid))
	{
		return;
	}

	// thread is not deleted
	if ($threadinfo['visible'] != 2)
	{
		return;
	}

	if ($countposts)
	{
		$posts = $vbulletin->db->query_read("
			SELECT post.userid, postid
			FROM " . TABLE_PREFIX . "post AS post
			WHERE threadid = $threadid
				AND visible = 1
				AND userid > 0
		");
		$userbyuserid = array();
		while ($post = $vbulletin->db->fetch_array($posts))
		{
			if (!isset($userbyuserid["$post[userid]"]))
			{
				$userbyuserid["$post[userid]"] = 1;
			}
			else
			{
				$userbyuserid["$post[userid]"]++;
			}
		}

		if (!empty($userbyuserid))
		{
			$userbypostcount = array();
			foreach ($userbyuserid AS $postuserid => $postcount)
			{
				$alluserids .= ",$postuserid";
				$userbypostcount["$postcount"] .= ",$postuserid";
			}
			foreach($userbypostcount AS $postcount => $userids)
			{
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount\n";
			}

			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX ."user SET
					posts = posts + CASE $casesql ELSE 0 END
				WHERE userid IN (0$alluserids)
			");
		}
	}

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $threadid AND type = 'thread'");


	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set('visible', 1);
	$threadman->save();

	fetch_phrase_group('threadmanage');

	require_once(DIR . '/includes/functions_log_error.php');
	log_moderator_action($threadinfo, 'thread_undeleted');
}

// ###################### Start undeletepost #######################
function undelete_post($postid, $countposts, $postinfo = NULL, $threadinfo = NULL)
{
	global $vbulletin, $vbphrase;

	// Valid postinfo array will contain: postid, threadid, visible, userid, username, title
	// Invalid post or post is not deleted
	if (!$postinfo AND !$postinfo = fetch_postinfo($postid))
	{
		return;
	}

	// Valid threadinfo array will contain: threadid, forumid, visible, firstpostid
	if (!$threadinfo AND !$threadinfo = fetch_threadinfo($postinfo['threadid']))
	{
		return;
	}

	if ($threadinfo['firstpostid'] == $postid)
	{
		// undelete thread
		undelete_thread($threadinfo['threadid'], $countposts, $threadinfo);
		return;
	}

	// Post is not deleted
	if ($postinfo['visible'] != 2)
	{
		return;
	}

	// Only increment post for a visible thread in a counting forum
	if ($countposts AND $postinfo['userid'] AND $threadinfo['visible'] == 1)
	{
		$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
		$userdata->set_existing($postinfo);
		$userdata->set('posts', 'posts + 1', false);
		$userdata->save();
		unset($userdata);
	}

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $postid AND type = 'post'");

	$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
	$postman->set_existing($postinfo);
	$postman->set('visible', 1);
	$postman->save();

	build_thread_counters($postinfo['threadid']);
	build_forum_counters($threadinfo['forumid']);

	fetch_phrase_group('threadmanage');
	$postinfo['forumid'] = $threadinfo['forumid'];

	require_once(DIR . '/includes/functions_log_error.php');
	log_moderator_action($postinfo, 'post_y_by_x_undeleted', array($postinfo['title'], $postinfo['username']));
}

// ############################### start do update thread subscriptions ###############################
function update_subscriptions($criteria)
{
	global $vbulletin;

	$sql = array();
	if (!empty($criteria['threadids']))
	{
		$sql[] = "subscribethread.threadid IN(" . implode(', ', $criteria['threadids']) . ")";
	}
	if (!empty($criteria['userids']))
	{
		$sql[] = "subscribethread.userid IN (" . implode(', ', $criteria['userids']) . ")";
	}

	if (empty($sql))
	{
		return;
	}

	// unsubscribe users who can't view the forum the threads are now in
	$users = $vbulletin->db->query_read("
		SELECT user.userid, usergroupid, membergroupids, IF(options & " . $vbulletin->bf_misc_useroptions['hasaccessmask'] . ", 1, 0) AS hasaccessmask,
			thread.postuserid, subscribethread.canview, subscribethreadid, thread.forumid
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (subscribethread.userid = user.userid)
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (subscribethread.threadid = thread.threadid)
		WHERE
			" . implode(" AND ", $sql) . "
	");
	$deleteuser = array();
	$adduser = array();
	while ($thisuser = $vbulletin->db->fetch_array($users))
	{
		cache_permissions($thisuser, true, true);
		if (($thisuser['forumpermissions']["$thisuser[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($thisuser['forumpermissions']["$thisuser[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) AND ($thisuser['postuserid'] == $thisuser['userid'] OR ($thisuser['forumpermissions']["$thisuser[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
		{
			// this user can now view this subscription
			if ($thisuser['canview'] == 0)
			{
				$adduser[] = $thisuser['subscribethreadid'];
			}
		}
		else
		{
			// this user can no longer view this subscription
			if ($thisuser['canview'] == 1)
			{
				$deleteuser[] = $thisuser['subscribethreadid'];
			}
		}
	}

	if (!empty($deleteuser) OR !empty($adduser))
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "subscribethread
			SET canview =
			CASE
				" . (!empty($deleteuser) ? " WHEN subscribethreadid IN (" . implode(', ', $deleteuser) . ") THEN 0" : "") . "
				" . (!empty($adduser) ? " WHEN subscribethreadid IN (" . implode(', ', $adduser) . ") THEN 1" : "") . "
			ELSE canview
			END
			WHERE subscribethreadid IN (" . implode(', ', array_merge($deleteuser, $adduser)) . ")
		");
	}
}

/**
* Adds an entire phrase group to the $vbphrase array.
* Utility function that is actually only used in this file.
*
* @param	string	Group Name
*/
function fetch_phrase_group($groupname)
{
	global $vbulletin, $vbphrase, $phrasegroups;

	if (in_array($groupname, $phrasegroups))
	{
		// this group is already in $vbphrase
		return;
	}
	$phrasegroups[] = $groupname;

	$group = $vbulletin->db->query_first("
		SELECT phrasegroup_$groupname AS $groupname
		FROM " . TABLE_PREFIX . "language
		WHERE languageid = " . intval(iif($vbulletin->userinfo['languageid'], $vbulletin->userinfo['languageid'], $vbulletin->options['languageid']))
	);

	$vbphrase = array_merge($vbphrase, unserialize($group["$groupname"]));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14760 $
|| ####################################################################
\*======================================================================*/
?>
