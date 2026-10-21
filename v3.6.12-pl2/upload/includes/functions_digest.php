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

	require_once(DIR . '/includes/class_bbcode_alt.php');
	$plaintext_parser =& new vB_BbCodeParser_PlainText($vbulletin, fetch_tag_list());

	vbmail_start();

	// get new threads
	$threads = $vbulletin->db->query_read_slave("
		SELECT
			user.userid, user.salt, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
			user.timezoneoffset, IF(user.options & " . $vbulletin->bf_misc_useroptions['dstonoff'] . ", 1, 0) AS dstonoff,
			IF(user.options & " . $vbulletin->bf_misc_useroptions['hasaccessmask'] . ", 1, 0) AS hasaccessmask,
			thread.threadid, thread.title, thread.dateline, thread.forumid, thread.lastpost, pollid,
			open, replycount, postusername, postuserid, lastposter, thread.dateline, views, subscribethreadid,
			language.dateoverride AS lang_dateoverride, language.timeoverride AS lang_timeoverride, language.locale AS lang_locale
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = subscribethread.threadid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = subscribethread.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "language AS language ON (language.languageid = IF(user.languageid = 0, " . intval($vbulletin->options['languageid']) . ", user.languageid))
		WHERE subscribethread.emailupdate = " . intval($type) . " AND
			thread.lastpost > " . intval($lastdate) . " AND
			thread.visible = 1 AND
			user.usergroupid <> 3 AND
			(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
	");

	while ($thread = $vbulletin->db->fetch_array($threads))
	{
		$postbits = '';

		$userperms = fetch_permissions($thread['forumid'], $thread['userid'], $thread);
		if (!($userperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($userperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR ($thread['postuserid'] != $thread['userid'] AND !($userperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
		{
			continue;
		}
		
		$userinfo = array(
			'lang_locale'    => $thread['lang_locale'],
			'dstonoff'       => $thread['dstonoff'],
			'timezoneoffset' => $thread['timezoneoffset'],
		);
	
		$thread['lastreplydate'] = vbdate($thread['lang_dateoverride'] ? $thread['lang_dateoverride'] : $vbulletin->options['default_dateformat'], $thread['lastpost'], false, true, true, false, $userinfo);
		$thread['lastreplytime'] = vbdate($thread['lang_timeoverride'] ? $thread['lang_timeoverride'] : $vbulletin->options['default_timeformat'], $thread['lastpost'], false, true, true, false, $userinfo);
		$thread['title'] = unhtmlspecialchars($thread['title']);
		$thread['username'] = unhtmlspecialchars($thread['username']);
		$thread['postusername'] = unhtmlspecialchars($thread['postusername']);
		$thread['lastposter'] = unhtmlspecialchars($thread['lastposter']);
		$thread['newposts'] = 0;
		$thread['auth'] = md5($thread['userid'] . $thread['subscribethreadid'] . $thread['salt'] . COOKIE_SALT);

		// get posts
		$posts = $vbulletin->db->query_read_slave("SELECT
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

			$post['postdate'] = vbdate($thread['lang_dateoverride'] ? $thread['lang_dateoverride'] : $vbulletin->options['default_dateformat'], $post['dateline'], false, true, true, false, $userinfo);
			$post['posttime'] = vbdate($thread['lang_timeoverride'] ? $thread['lang_timeoverride'] : $vbulletin->options['default_timeformat'], $post['dateline'], false, true, true, false, $userinfo);

			$post['postusername'] = unhtmlspecialchars($post['postusername']);

			$plaintext_parser->set_parsing_language($thread['languageid']);
			$post['pagetext'] = $plaintext_parser->parse($post['pagetext'], $thread['forumid']);

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

	unset($plaintext_parser);

	// get new forums
	$forums = $vbulletin->db->query_read_slave("
		SELECT user.userid, user.salt, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
			user.timezoneoffset, IF(user.options & " . $vbulletin->bf_misc_useroptions['dstonoff'] . ", 1, 0) AS dstonoff,
			IF(user.options & " . $vbulletin->bf_misc_useroptions['hasaccessmask'] . ", 1, 0) AS hasaccessmask,
			forum.forumid, forum.title_clean, subscribeforum.subscribeforumid,
			language.dateoverride AS lang_dateoverride, language.timeoverride AS lang_timeoverride, language.locale AS lang_locale
		FROM " . TABLE_PREFIX . "subscribeforum AS subscribeforum
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = subscribeforum.forumid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = subscribeforum.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "language AS language ON (language.languageid = IF(user.languageid = 0, " . intval($vbulletin->options['languageid']) . ", user.languageid))
		WHERE subscribeforum.emailupdate = " . intval($type) . " AND
			forum.lastpost > " . intval($lastdate) . " AND
			user.usergroupid <> 3 AND
			(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
	");

	while ($forum = $vbulletin->db->fetch_array($forums))
	{
		$userinfo = array(
			'lang_locale'       => $forum['lang_locale'],
			'dstonoff'          => $forum['dstonoff'],
			'timezoneoffset'    => $forum['timezoneoffset'],
		);

		$newthreadbits = '';
		$newthreads = 0;
		$updatedthreadbits = '';
		$updatedthreads = 0;

		$forum['username'] = unhtmlspecialchars($forum['username']);
		$forum['title_clean'] = unhtmlspecialchars($forum['title_clean']);
		$forum['auth'] = md5($forum['userid'] . $forum['subscribeforumid'] . $forum['salt'] . COOKIE_SALT);

		$threads = $vbulletin->db->query_read_slave("
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
			$userperms = fetch_permissions($thread['forumid'], $forum['userid'], $forum);
			// allow those without canviewthreads to subscribe/receive forum updates as they contain not post content
			if (!($userperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR ($thread['postuserid'] != $forum['userid'] AND !($userperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
			{
				continue;
			}

			$thread['forumtitle'] = unhtmlspecialchars($thread['forumtitle']);
			$thread['lastreplydate'] = vbdate($forum['lang_dateoverride'] ? $forum['lang_dateoverride'] : $vbulletin->options['default_dateformat'], $thread['lastpost'], false, true, true, false, $userinfo);
			$thread['lastreplytime'] = vbdate($forum['lang_timeoverride'] ? $forum['lang_timeoverride'] : $vbulletin->options['default_timeformat'], $thread['lastpost'], false, true, true, false, $userinfo);

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
|| # CVS: $RCSfile$ - $Revision: 24468 $
|| ####################################################################
\*======================================================================*/
?>