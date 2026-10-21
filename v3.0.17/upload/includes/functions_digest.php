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

// ###################### Start dodigest #######################
function exec_digest($type = 2)
{
	global $DB_site, $vboptions, $_USEROPTIONS;

	// type = 2 : daily
	// type = 3 : weekly

	$lastdate = mktime(0, 0); // midnight today
	if ($type == 2)
	{ // daily
		// yesterday midnight
		$lastdate -= 24 * 60 * 60;
	}
	else
	{ // weekly
		// last week midnight
		$lastdate -= 7 * 24 * 60 * 60;
	}

	vbmail_start();

	// get new threads
	$threads = $DB_site->query("SELECT
		user.userid, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
			(user.options & $_USEROPTIONS[hasaccessmask]) AS hasaccessmask,
		thread.threadid,thread.title,thread.dateline, thread.forumid,
		thread.lastpost,pollid, open, replycount, postusername, postuserid, lastposter, thread.dateline, views
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = subscribethread.threadid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = subscribethread.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (thread.threadid = deletionlog.primaryid AND type = 'thread')
		WHERE subscribethread.emailupdate = " . intval($type) . " AND
			thread.lastpost > " . intval($lastdate) . " AND
			thread.visible = 1 AND
			user.usergroupid <> 3 AND
			deletionlog.primaryid IS NULL AND
			(usergroup.genericoptions & " . ISBANNEDGROUP . ") = 0
	");

	while ($thread = $DB_site->fetch_array($threads))
	{
		$postbits = '';

		$userperms = fetch_permissions($thread['forumid'], $thread['userid'], $thread);
		if (!($userperms & CANVIEW) OR ($thread['postuserid'] != $thread['userid'] AND !($userperms & CANVIEWOTHERS)))
		{
			continue;
		}

		$thread['lastreplydate'] = vbdate($vboptions['dateformat'], $thread['lastpost'], 1);
		$thread['lastreplytime'] = vbdate($vboptions['timeformat'], $thread['lastpost']);
		$thread['title'] = unhtmlspecialchars($thread['title']);
		$thread['username'] = unhtmlspecialchars($thread['username']);
		$thread['postusername'] = unhtmlspecialchars($thread['postusername']);
		$thread['lastposter'] = unhtmlspecialchars($thread['lastposter']);
		$thread['newposts'] = 0;

		// get posts
		$posts = $DB_site->query("SELECT
			post.*,IFNULL(user.username,post.username) AS postusername,
			user.*,attachment.filename
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = post.userid)
			LEFT JOIN " . TABLE_PREFIX . "attachment AS attachment ON (attachment.postid = post.postid)
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND deletionlog.type = 'post')
			WHERE threadid = " . intval($thread['threadid']) . " AND
				post.visible = 1 AND
				deletionlog.primaryid IS NULL AND
				user.usergroupid <> 3 AND
				post.dateline > " . intval($lastdate) . "
			ORDER BY post.dateline
		");

		// compile
		$haveothers = false;
		while ($post = $DB_site->fetch_array($posts))
		{
			if ($post['userid'] != $thread['userid'])
			{
				$haveothers = true;
			}
			$thread['newposts']++;
			$post['postdate'] = vbdate($vboptions['dateformat'], $post['dateline'], 1);
			$post['posttime'] = vbdate($vboptions['timeformat'], $post['dateline']);
			$post['pagetext'] = unhtmlspecialchars(strip_bbcode($post['pagetext']));
			$post['postusername'] = unhtmlspecialchars($post['postusername']);

			eval(fetch_email_phrases('digestpostbit', $thread['languageid']));
			$postbits .= $message;

		}

		// Don't send an update if the subscriber is the only one who posted in the thread.
		if ($haveothers)
		{
			// make email
			eval(fetch_email_phrases('digestthread', $thread['languageid']));

			vbmail($thread['email'], $subject, $message);
		}
	}


	// get new forums
	$forums = $DB_site->query("
		SELECT user.userid, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
			(user.options & $_USEROPTIONS[hasaccessmask]) AS hasaccessmask,
			forum.forumid, forum.title
		FROM " . TABLE_PREFIX . "subscribeforum AS subscribeforum
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = subscribeforum.forumid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = subscribeforum.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		WHERE subscribeforum.emailupdate = " . intval($type) . " AND
			forum.lastpost > " . intval($lastdate) . " AND
			(usergroup.genericoptions & " . ISBANNEDGROUP . ") = 0
	");
	while ($forum = $DB_site->fetch_array($forums))
	{
		$newthreadbits = '';
		$newthreads = 0;
		$updatedthreadbits = '';
		$updatedthreads = 0;

		$forum['username'] = unhtmlspecialchars($forum['username']);
		$forum['title'] = unhtmlspecialchars($forum['title']);

		$threads = $DB_site->query("
			SELECT forum.title AS forumtitle, thread.threadid, thread.title, thread.dateline, thread.forumid,
			thread.lastpost, pollid, open, thread.replycount, postusername, postuserid,
			thread.lastposter, thread.dateline, views
			FROM " . TABLE_PREFIX . "forum AS forum
			INNER JOIN " . TABLE_PREFIX . "thread AS thread USING(forumid)
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (thread.threadid = deletionlog.primaryid AND type = 'thread')
			WHERE FIND_IN_SET('" . intval($forum['forumid']) . "', forum.parentlist) AND
				thread.lastpost > " . intval ($lastdate) . " AND
				thread.visible = 1 AND
				deletionlog.primaryid IS NULL
			");

		while ($thread = $DB_site->fetch_array($threads))
		{

			$userperms = fetch_permissions($thread['forumid'], $forum['userid'], $forum);
			if (!($userperms & CANVIEW) OR ($thread['postuserid'] != $forum['userid'] AND !($userperms & CANVIEWOTHERS)))
			{
				continue;
			}

			$thread['lastreplydate'] = vbdate($vboptions['dateformat'], $thread['lastpost'], 1);
			$thread['lastreplytime'] = vbdate($vboptions['timeformat'], $thread['lastpost']);
			$thread['title'] = unhtmlspecialchars($thread['title']);
			$thread['postusername'] = unhtmlspecialchars($thread['postusername']);
			$thread['lastposter'] = unhtmlspecialchars($thread['lastposter']);

			eval(fetch_email_phrases('digestthreadbit', $forum['languageid']));
			if ($thread['dateline'] > $lastdate)
			{ // new thread
				$newthreads++;
				$newthreadbits .= $message;
			}
			else
			{
				$updatedthreads++;
				$updatedthreadbits .= $message;
			}
		}

		if (!empty($newthreads) OR !empty($updatedthreadbits))
		{
			// make email
			eval(fetch_email_phrases('digestforum', $forum['languageid']));

			vbmail($forum['email'], $subject, $message);
		}
	}

	vbmail_end();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12924 $
|| ####################################################################
\*======================================================================*/
?>