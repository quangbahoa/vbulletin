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

// ###################### Start dodigest #######################
function exec_digest($type = 2)
{
	global $vbulletin;

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
	$threads = $vbulletin->db->query_read("SELECT
		user.userid, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
			IF(user.options & " . $vbulletin->bf_misc_useroptions['hasaccessmask'] . ", 1, 0) AS hasaccessmask,
		thread.threadid,thread.title,thread.dateline, thread.forumid,
		thread.lastpost,pollid, open, replycount, postusername, postuserid, lastposter, thread.dateline, views
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = subscribethread.threadid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = subscribethread.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		WHERE subscribethread.emailupdate = " . intval($type) . " AND
			thread.lastpost > " . intval($lastdate) . " AND
			thread.visible = 1 AND
			user.usergroupid <> 3 AND
			(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isbannedgroup'] . ") = 0
	");

	while ($thread = $vbulletin->db->fetch_array($threads))
	{
		$postbits = '';

		$userperms = fetch_permissions($thread['forumid'], $thread['userid'], $thread);
		if (!($userperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($userperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR ($thread['postuserid'] != $thread['userid'] AND !($userperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
		{
			continue;
		}

		$thread['lastreplydate'] = vbdate($vbulletin->options['dateformat'], $thread['lastpost'], 1);
		$thread['lastreplytime'] = vbdate($vbulletin->options['timeformat'], $thread['lastpost']);
		$thread['title'] = unhtmlspecialchars($thread['title']);
		$thread['username'] = unhtmlspecialchars($thread['username']);
		$thread['postusername'] = unhtmlspecialchars($thread['postusername']);
		$thread['lastposter'] = unhtmlspecialchars($thread['lastposter']);
		$thread['newposts'] = 0;

		// get posts
		$posts = $vbulletin->db->query_read("SELECT
			post.*,IFNULL(user.username,post.username) AS postusername,
			user.*,attachment.filename
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = post.userid)
			LEFT JOIN " . TABLE_PREFIX . "attachment AS attachment ON (attachment.postid = post.postid)
			WHERE threadid = " . intval($thread['threadid']) . " AND
				post.visible = 1 AND
				user.usergroupid <> 3 AND
				post.dateline > " . intval($lastdate) . "
			ORDER BY post.dateline
		");

		// compile
		$haveothers = false;
		while ($post = $vbulletin->db->fetch_array($posts))
		{
			if ($post['userid'] != $thread['userid'])
			{
				$haveothers = true;
			}
			$thread['newposts']++;
			$post['postdate'] = vbdate($vbulletin->options['dateformat'], $post['dateline'], 1);
			$post['posttime'] = vbdate($vbulletin->options['timeformat'], $post['dateline']);
			$post['pagetext'] = unhtmlspecialchars(strip_bbcode($post['pagetext']));
			$post['postusername'] = unhtmlspecialchars($post['postusername']);

			($hook = vBulletinHook::fetch_hook('digest_thread_post')) ? eval($hook) : false;

			eval(fetch_email_phrases('digestpostbit', $thread['languageid']));
			$postbits .= $message;

		}

		($hook = vBulletinHook::fetch_hook('digest_thread_process')) ? eval($hook) : false;

		// Don't send an update if the subscriber is the only one who posted in the thread.
		if ($haveothers)
		{
			// make email
			eval(fetch_email_phrases('digestthread', $thread['languageid']));

			vbmail($thread['email'], $subject, $message);
		}
	}


	// get new forums
	$forums = $vbulletin->db->query_read("
		SELECT user.userid, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
			IF(user.options & " . $vbulletin->bf_misc_useroptions['hasaccessmask'] . ", 1, 0) AS hasaccessmask,
		forum.forumid, forum.title_clean
		FROM " . TABLE_PREFIX . "subscribeforum AS subscribeforum
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = subscribeforum.forumid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = subscribeforum.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		WHERE subscribeforum.emailupdate = " . intval($type) . " AND
			forum.lastpost > " . intval($lastdate) . " AND
			(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isbannedgroup'] . ") = 0
	");
	while ($forum = $vbulletin->db->fetch_array($forums))
	{
		$newthreadbits = '';
		$newthreads = 0;
		$updatedthreadbits = '';
		$updatedthreads = 0;

		$forum['username'] = unhtmlspecialchars($forum['username']);
		$forum['title_clean'] = unhtmlspecialchars($forum['title_clean']);

		$threads = $vbulletin->db->query_read("
			SELECT forum.title_clean AS forumtitle, thread.threadid, thread.title, thread.dateline, thread.forumid,
			thread.lastpost, pollid, open, thread.replycount, postusername, postuserid,
			thread.lastposter, thread.dateline, views
			FROM " . TABLE_PREFIX . "forum AS forum
			INNER JOIN " . TABLE_PREFIX . "thread AS thread USING(forumid)
			WHERE FIND_IN_SET('" . intval($forum['forumid']) . "', forum.parentlist) AND
				thread.lastpost > " . intval ($lastdate) . " AND
				thread.visible = 1
			");

		while ($thread = $vbulletin->db->fetch_array($threads))
		{
			$thread['forumtitle'] = unhtmlspecialchars($thread['forumtitle']);
			$userperms = fetch_permissions($thread['forumid'], $forum['userid'], $forum);
			// allow those without canviewthreads to subscribe/receive forum updates as they contain not post content
			if (!($userperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR ($thread['postuserid'] != $forum['userid'] AND !($userperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
			{
				continue;
			}

			$thread['lastreplydate'] = vbdate($vbulletin->options['dateformat'], $thread['lastpost'], 1);
			$thread['lastreplytime'] = vbdate($vbulletin->options['timeformat'], $thread['lastpost']);
			$thread['title'] = unhtmlspecialchars($thread['title']);
			$thread['postusername'] = unhtmlspecialchars($thread['postusername']);
			$thread['lastposter'] = unhtmlspecialchars($thread['lastposter']);

			($hook = vBulletinHook::fetch_hook('digest_forum_thread')) ? eval($hook) : false;

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

		($hook = vBulletinHook::fetch_hook('digest_forum_process')) ? eval($hook) : false;

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
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14217 $
|| ####################################################################
\*======================================================================*/
?>