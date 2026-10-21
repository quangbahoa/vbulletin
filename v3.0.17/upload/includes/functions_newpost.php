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

// ###################### Start chooseicons #######################
function construct_icons($seliconid = 0, $allowicons = true)
{
	// returns the icons chooser for posting new messages
	global $DB_site, $session, $bbuserinfo, $stylevar, $vboptions;
	global $vbphrase, $selectedicon, $show;

	$selectedicon = array('src' => $vboptions['cleargifurl'], 'alt' => '');

	if (!$allowicons)
	{
		return false;
	}

	$membergroups = fetch_membergroupids_array($bbuserinfo);

	$avperms = $DB_site->query("
		SELECT imagecategorypermission.imagecategoryid, usergroupid
		FROM " . TABLE_PREFIX . "imagecategorypermission AS imagecategorypermission, " . TABLE_PREFIX . "imagecategory AS imagecategory
		WHERE imagetype = 2
			AND imagecategorypermission.imagecategoryid = imagecategory.imagecategoryid
		ORDER BY imagecategory.displayorder
	");
	$noperms = array();
	while ($avperm = $DB_site->fetch_array($avperms))
	{
		$noperms["$avperm[imagecategoryid]"][] = $avperm['usergroupid'];
	}
	foreach($noperms AS $imagecategoryid => $usergroups)
	{
		if (!count(array_diff($membergroups, $usergroups)))
		{
			$badcategories .= ",$imagecategoryid";
		}
	}

	$icons = $DB_site->query("
		SELECT iconid, iconpath, title
		FROM " . TABLE_PREFIX . "icon AS icon
		WHERE imagecategoryid NOT IN (0$badcategories)
		ORDER BY imagecategoryid, displayorder
	");

	if (!$DB_site->num_rows($icons))
	{
		return false;
	}

	$numicons = 0;
	$show['posticons'] = false;

	while ($icon = $DB_site->fetch_array($icons))
	{
		$show['posticons'] = true;
		if ($numicons % 7 == 0 AND $numicons != 0)
		{
			$posticonbits .= "</tr><tr><td>&nbsp;</td>";
		}

		$numicons++;

		$iconid = $icon['iconid'];
		$iconpath = $icon['iconpath'];
		$alttext = $icon['title'];
		if ($seliconid == $iconid)
		{
			$iconchecked = HTML_CHECKED;
			$selectedicon = array('src' => $iconpath, 'alt' => $alttext);
		}
		else
		{
			$iconchecked = '';
		}

		eval('$posticonbits .= "' . fetch_template('posticonbit') . '";');

	}

	$remainder = $numicons % 7;

	if ($remainder)
	{
		$remainingspan = 2 * (7 - $remainder);
		$show['addedspan'] = true;
	}
	else
	{
		$remainingspan = 0;
		$show['addedspan'] = false;
	}

	if ($seliconid == 0)
	{
		$iconchecked = HTML_CHECKED;
	}
	else
	{
		$iconchecked = '';
	}

	eval('$posticons = "' . fetch_template('posticons') . '";');

	return $posticons;

}

// ###################### Start parseurl #######################
function convert_url_to_bbcode($messagetext)
{
	// attempts to discover what areas we should auto-parse in
	$skiptaglist = 'url|email|code|php|html';
	return preg_replace('#(^|\[/(' . $skiptaglist . ')\])(.+(?=\[(' . $skiptaglist . ')|$))#siUe', "convert_url_to_bbcode_callback('\\3', '\\1')", $messagetext);
}

// ###################### Start convert_url_to_bbcode_callback #######################
function convert_url_to_bbcode_callback($messagetext, $prepend)
{
	// the auto parser - adds [url] tags around neccessary things
	$messagetext = str_replace('\"', '"', $messagetext);
	$prepend = str_replace('\"', '"', $prepend);

	static $urlSearchArray, $urlReplaceArray, $emailSearchArray, $emailReplaceArray;
	if (empty($urlSearchArray))
	{
		$taglist = '\[b|\[i|\[u|\[left|\[center|\[right|\[indent|\[quote|\[highlight|\[\*' .
			'|\[/b|\[/i|\[/u|\[/left|\[/center|\[/right|\[/indent|\[/quote|\[/highlight';
		$urlSearchArray = array(
			"#(^|(?<=[^_a-z0-9-=\]\"'/@]|(?<=" . $taglist . ")\]))((https?|ftp|gopher|news|telnet)://|www\.)((\[(?!/)|[^\s[^$!`\"'|{}<>])+)(?!\[/url|\[/img)(?=[,.]*(\)\s|\)$|[\s[]|$))#siU"
		);

		$urlReplaceArray = array(
			"[url]\\2\\4[/url]"
		);

		$emailSearchArray = array(
			"/([ \n\r\t])([_a-z0-9-]+(\.[_a-z0-9-]+)*@[^\s]+(\.[a-z0-9-]+)*(\.[a-z]{2,4}))/si",
			"/^([_a-z0-9-]+(\.[_a-z0-9-]+)*@[^\s]+(\.[a-z0-9-]+)*(\.[a-z]{2,4}))/si"
		);

		$emailReplaceArray = array(
			"\\1[email]\\2[/email]",
			"[email]\\0[/email]"
		);
	}

	$text = preg_replace($urlSearchArray, $urlReplaceArray, $messagetext);
	if (strpos($text, "@"))
	{
		$text = preg_replace($emailSearchArray, $emailReplaceArray, $text);
	}

	return $prepend . $text;
}

// ###################### Start newpost #######################
function build_new_post($type = 'thread', $foruminfo, $threadinfo, $parentid, &$post, &$errors)
{
	//NOTE: permissions are not checked in this function

	// $post is passed by reference, so that any changes (wordwrap, censor, etc) here are reflected on the copy outside the function
	// $post[] includes:
	// title, iconid, message, parseurl, email, signature, preview, disablesmilies, rating
	// $errors will become any error messages that come from the checks before preview kicks in
	global $DB_site, $vboptions, $vbphrase, $bbuserinfo, $forumperms, $usergroupcache, $_REQUEST;

	// ### PREPARE OPTIONS AND CHECK VALID INPUT ###
	$post['parseurl'] = intval($post['parseurl']);
	$post['email'] = intval($post['email']);
	$post['signature'] = intval($post['signature']);
	$post['preview'] = iif($post['preview'], 1, 0);
	$post['disablesmilies'] = intval($post['disablesmilies']);
	$post['enablesmilies'] = iif($post['disablesmilies'], 0, 1);
	$post['rating'] = intval($post['rating']);
	$post['iconid'] = intval($post['iconid']);
	$post['message'] = trim($post['message']);
	$post['title'] = trim(preg_replace('/&#0*32;/', ' ', $post['title']));
	$post['emailupdate'] = intval($post['emailupdate']);
	$post['folderid'] = intval($post['folderid']);
	$post['username'] = trim($post['username']);
	$post['posthash'] = trim($post['posthash']);
	$post['poststarttime'] = trim($post['poststarttime']);

	// Make sure the posthash is valid
	if (md5($post['poststarttime'] . $bbuserinfo['userid'] . $bbuserinfo['salt']) != $post['posthash'])
	{
		$post['posthash'] = 'invalid posthash'; // don't phrase me
	}

	// OTHER SANITY CHECKS
	$threadinfo['threadid'] = intval($threadinfo['threadid']);
	$parentid = intval($parentid);
	if ($bbuserinfo['userid'] == 0)
	{
		$post['username'] = preg_replace('#\s+#', ' ', $post['username']);
		$post['postusername'] = htmlspecialchars_uni($post['username']);
	}
	else
	{
		$post['username'] = '';
		$post['postusername'] = $bbuserinfo['username'];
	}

	// censor and htmlspecialchars post title
	$post['title'] = htmlspecialchars_uni(fetch_censored_text($post['title']));

	// do word wrapping
	if ($vboptions['wordwrap'] != 0)
	{
		$post['title'] = fetch_word_wrapped_string($post['title']);
	}

	// remove all caps subjects
	$post['title'] = fetch_no_shouting_text($post['title']);

	// remove empty bbcodes
	$post['message'] = strip_empty_bbcode($post['message']);

	// add # to color tags using hex if it's not there
	$post['message'] = preg_replace('#\[color=(&quot;|"|\'|)([a-f0-9]{6})\\1]#i', '[color=\1#\2\1]', $post['message']);

	// strip alignment codes that are closed and then immediately reopened
	$post['message'] = preg_replace('#\[/(left|center|right)]((\r\n|\r|\n)*)\[\\1]#si', '\\2', $post['message']);

	// remove /list=x remnants
	if (stristr($post['message'], '/list=') != false)
	{
		$post['message'] = preg_replace('#/list=[a-z0-9]+\]#siU', '/list]', $post['message']);
	}

	// remove extra whitespace between [list] and first element
	$post['message'] = preg_replace('#(\[list(=(&quot;|"|\'|)([^\]]*)\\3)?\])\s+#i', "\\1\n", $post['message']);

	// censor main message text
	$post['message'] = fetch_censored_text($post['message']);

	// parse URLs in message text
	if ($post['parseurl'])
	{
		$post['message'] = convert_url_to_bbcode($post['message']);
	}

	// remove sessionhash from urls:
	require_once('./includes/functions_login.php');
	$post['message'] = fetch_removed_sessionhash($post['message']);

	$post['message'] = fetch_no_shouting_text($post['message']);

	if ($_REQUEST['fromquickreply'] AND $post['preview'])
	{
		$errors = array();
	}
	else
	{
		verify_post_errors($type, $post, $errors);
	}

	if ($post['preview'] OR sizeof($errors) > 0)
	{
		// preview or errors, so don't submit
		return;
	}

	if ($vboptions['logip'])
	{
		$post['ipaddress'] = IPADDRESS;
	}
	else
	{
		$post['ipaddress'] = '';
	}

	// see if post has to be moderated or if poster in a mod
	if (
		(
			(
				($foruminfo['moderatenewthread'] AND $type == 'thread') OR ($foruminfo['moderatenewpost'] AND $type == 'reply')
			)
			OR ($forumperms & ISALWAYSMODERATED)
		)
		AND !can_moderate($foruminfo['forumid'])
	)
	{
		$post['visible'] = 0;
	}
	else
	{
		$post['visible'] = 1;
	}

	// ### DUPE CHECK ###
	$dupehash = md5($foruminfo['forumid'] . $post['title'] . $post['message'] . $bbuserinfo['userid'] . $type);
	$prevpostfound = false;
	$prevpostthreadid = 0;

	if ($prevpost = $DB_site->query_first("
		SELECT posthash.threadid
		FROM " . TABLE_PREFIX . "posthash AS posthash
		WHERE posthash.userid = $bbuserinfo[userid] AND
			posthash.dupehash = '" . addslashes($dupehash) . "' AND
			posthash.dateline > " . (TIMENOW - 300) . "
	"))
	{
		if (($type == 'thread' AND $prevpost['threadid'] == 0) OR ($type == 'reply' AND $prevpost['threadid'] == $threadinfo['threadid']))
		{
			$prevpostfound = true;
			$prevpostthreadid = $prevpost['threadid'];
		}
	}

	// Redirect user to forumdisplay since this is a duplicate post
	if ($prevpostfound)
	{
		$_REQUEST['forceredirect'] = 1;

		if ($type == 'thread')
		{
			$url = "forumdisplay.php?$session[sessionurl]f=$foruminfo[forumid]";
			eval(print_standard_redirect('redirect_duplicatethread'));
		}
		else
		{
			$url = "showthread.php?$session[sessionurl]t=$prevpostthreadid";
			eval(print_standard_redirect('redirect_duplicatepost'));
		}

	}
	else
	{
		if ($parentid == 0 AND $type != 'thread')
		{ // get parentid of the new post
			// we're not posting a new thread, so make this post a child of the first post in the thread
			$getfirstpost = $DB_site->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE threadid=$threadinfo[threadid] ORDER BY dateline LIMIT 1");
			$parentid = $getfirstpost['postid'];
		}

		// Get attachment info for previous post and any new attachments that may have been stuck in...
		$attachcount = $DB_site->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "attachment
			WHERE posthash = '" . addslashes($post['posthash']) . "'
				AND userid = $bbuserinfo[userid]
		");

		$totalattachments = $attachcount['count'];

		// ### Insert Dupe Info ###
		// If threadid == 0 than this indicates that this post is the first post of a thread
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "posthash
			(userid, threadid, dupehash, dateline)
			VALUES
			($bbuserinfo[userid], " . intval($threadinfo['threadid']) . ", '" . $dupehash . "', " . TIMENOW . ")
		");

		// check that they can use this icon
		if ($post['iconid'] > 0)
		{
			$membergroups = fetch_membergroupids_array($bbuserinfo);
			$imagecheck = $DB_site->query("SELECT usergroupid FROM " . TABLE_PREFIX . "icon AS icon
				INNER JOIN " . TABLE_PREFIX . "imagecategorypermission USING (imagecategoryid)
				WHERE icon.iconid = $post[iconid]
					AND usergroupid IN (" . addslashes(implode(',', $membergroups)) . ")
			");

			if ($DB_site->num_rows($imagecheck) == sizeof($membergroups))
			{
				$post['iconid'] = 0;
			}
		}

		// ### POST NEW THREAD ###
		if ($type == 'thread')
		{
			if ($vboptions['similarthreadsearch'])
			{
				require_once('./includes/functions_search.php');
				$similarthreads = fetch_similar_threads($post['title'], 0);
			}
			else
			{
				$similarthreads = '';
			}
			$DB_site->query("
				INSERT INTO " . TABLE_PREFIX . "thread(title, lastpost, forumid, open, replycount, postusername, postuserid, lastposter, dateline,
					 iconid, visible, attach, similar)
				VALUES
					('" . addslashes($post['title']) . "', " . TIMENOW . ", " . intval($foruminfo['forumid']) . ",
					 1, 0, '" . addslashes($post['postusername']) . "', $bbuserinfo[userid],
					 '" . addslashes($post['postusername']) . "', " . TIMENOW . ", $post[iconid], $post[visible],
					 $totalattachments, '" . addslashes($similarthreads) . "')
			");
			$threadinfo['threadid'] = $DB_site->insert_id();
			$post['threadid'] = $threadinfo['threadid'];
			$threadinfo['visible'] = $post['visible'];
			if (!$post['visible'])
			{ // if the thread is being moderated then we dont need to hide the post
				$post['visible'] = 1;
			}
			$threadinfo['forumid'] = $foruminfo['forumid'];
			$threadinfo['title'] = $post['title'];
			$threadinfo['pollid'] = $post['postpoll'];
			$threadinfo['iconid'] = $post['iconid'];
			$threadinfo['open'] = 1;
			$threadinfo['sticky'] = 0;
			$threadinfo['postuserid'] = $bbuserinfo['userid'];
			$parentid = 0;
		}

		// ### POST NEW POST ###
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "post
				(threadid, parentid, title, username, userid, dateline, pagetext, allowsmilie,
				 showsignature, ipaddress, iconid, visible, attach)
			VALUES
				($threadinfo[threadid], $parentid, '" . addslashes($post['title']) . "',
				 '" . addslashes($post['postusername']) . "', $bbuserinfo[userid], " . TIMENOW . ",
				 '" . addslashes($post['message']) . "', $post[enablesmilies], $post[signature],
				 '" . addslashes($post['ipaddress']) . "', $post[iconid], $post[visible], $totalattachments)
		");
		$post['postid'] = $DB_site->insert_id();

		if ($type == 'thread')
		{
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "thread
				SET firstpostid = $post[postid]
				WHERE threadid = $threadinfo[threadid]
			");
			$post['visible'] = $threadinfo['visible'];
		}

		// now update the attachments .. if we have any visible OR not, otherwise the hourly cleanup will wipe them out
		if ($totalattachments)
		{
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "attachment
				SET postid = $post[postid], posthash = ''
				WHERE posthash = '" . addslashes($post['posthash']) . "'
					AND userid = $bbuserinfo[userid]
			");
		}

		// ### UPDATE SEARCH INDEX ###
		require_once('./includes/functions_databuild.php');
		build_post_index($post['postid'], $foruminfo, iif($type == 'thread', 1, 0));

		// initialise thread update conditions
		$threadupdate = array();

		// ### UPDATE FORUM COUNTERS IF THREAD IS VISIBLE ###
		if ($post['visible'])
		{
			if ($type != 'thread')
			{ // update thread stuff if not posting a new thread
				if ($threadinfo['replycount'] % 10 == 0)
				{ // only do a full recount every 10 posts
					$replies = $DB_site->query_first("
						SELECT COUNT(*)-1 AS replies
						FROM " . TABLE_PREFIX . "post AS post
						LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
						WHERE threadid = $threadinfo[threadid] AND
							post.visible <> 0 AND
							deletionlog.primaryid IS NULL
					");
					$threadupdate[] = "replycount = $replies[replies], lastpost = " . TIMENOW . ", lastposter = '" . addslashes($post['postusername']) . "'";
				}
				else
				{
					$threadupdate[] = "replycount = replycount + 1, lastpost = " . TIMENOW . ", lastposter = '" . addslashes($post['postusername']) . "'";
				}
			}

			// update forum stuff
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "forum
				SET replycount = replycount +  1,
				" . iif($type == 'thread', 'threadcount = threadcount + 1,') . "
				lastpost = " . TIMENOW . ",
				lastposter = '" . addslashes($post['postusername']) . "',
				lastthread = '" . addslashes($threadinfo['title']) . "',
				lastthreadid = $threadinfo[threadid],
				lasticonid = $threadinfo[iconid]
				WHERE forumid = $foruminfo[forumid]
			");
		}

		// add attachment update to thread update conditions
		if ($totalattachments AND $type != 'thread')
		{
			$threadupdate[] = "attach = attach + $totalattachments";
		}

		$date = vbdate($vboptions['dateformat'], TIMENOW);
		$time = vbdate($vboptions['timeformat'], TIMENOW);
		$modlogsql = array();

		// can this user open/close this thread if they want to?
		if ($_POST['openclose'] AND (($threadinfo['postuserid'] != 0 AND $threadinfo['postuserid'] == $bbuserinfo['userid'] AND $forumperms & CANOPENCLOSE) OR can_moderate($threadinfo['forumid'], 'canopenclose')))
		{
			if ($threadinfo['open'])
			{
				$open = 0;
				$notes = addslashes(construct_phrase($vbphrase['thread_closed_by_x_on_y_at_z'], $bbuserinfo['username'], $date, $time));
				$string = $vbphrase['closed'];
			}
			else
			{
				$open = 1;
				$notes = addslashes(construct_phrase($vbphrase['thread_opened_by_x_on_y_at_z'], $bbuserinfo['username'], $date, $time));
				$string = $vbphrase['opened'];
			}
			$threadupdate[] = "open = $open, notes = CONCAT(notes, ' $notes')";
			$modlogsql[] = "($bbuserinfo[userid], " . TIMENOW . ", $threadinfo[forumid], $threadinfo[threadid], '" . addslashes($string) . "')";

		}
		// can this user stick/unstick this thread if they want to?
		if ($_POST['stickunstick'] AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
		{
			if ($threadinfo['sticky'])
			{
				$stick = 0;
				$notes = addslashes(construct_phrase($vbphrase['thread_unstuck_by_x_on_y_at_z'], $bbuserinfo['username'], $date, $time));
				$string = $vbphrase['unstuck'];
			}
			else
			{
				$stick = 1;
				$notes = addslashes(construct_phrase($vbphrase['thread_stuck_by_x_on_y_at_z'], $bbuserinfo['username'], $date, $time));
				$string = $vbphrase['stuck'];
			}
			$threadupdate[] = "sticky = $stick, notes = CONCAT(notes, ' $notes')";
			$modlogsql[] = "($bbuserinfo[userid], " . TIMENOW . ", $threadinfo[forumid], $threadinfo[threadid], '" . addslashes($string) . "')";
		}

		// update the thread if there are any conditions to update
		if (!empty($threadupdate))
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET " . implode(', ', $threadupdate) . " WHERE threadid = $threadinfo[threadid]");
			if (!empty($modlogsql))
			{
				$DB_site->query("INSERT INTO " . TABLE_PREFIX . "moderatorlog(userid, dateline, forumid, threadid, action) VALUES " . implode(', ', $modlogsql));
			}
		}

		// ### DO MODERATION ###
		if ($post['visible'] == 0)
		{
			$DB_site->query("INSERT INTO " . TABLE_PREFIX . "moderation (threadid, postid, type) VALUES ($threadinfo[threadid], $post[postid], '$type')");
		}

		// ### DO THREAD RATING ###
		build_thread_rating($post['rating'], $foruminfo, $threadinfo);

		// ### UPDATE USER INFO ###
		$dotitle = '';
		$dogroup = '';
		$doposts = '';

		// get usergroup info
		$getusergroupid = iif($bbuserinfo['displaygroupid'], $bbuserinfo['displaygroupid'], $bbuserinfo['usergroupid']);
		$usergroup = $usergroupcache["$getusergroupid"];

		if ($foruminfo['countposts'])
		{
			$bbuserinfo['posts'] += 1;
			if (!$bbuserinfo['customtitle'])
			{
				if (!$usergroup['usertitle'])
				{
					$gettitle = $DB_site->query_first("
						SELECT title
						FROM " . TABLE_PREFIX . "usertitle
						WHERE minposts <= $bbuserinfo[posts]
						ORDER BY minposts DESC
					");
					$usertitle = $gettitle['title'];
				}
				else
				{
					$usertitle = $usergroup['usertitle'];
				}
				$dotitle = 'usertitle = \'' . addslashes($usertitle) . '\',';
			}
			$doposts = 'posts = posts + 1,';
		}

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user SET
				$doposts
				$dotitle
				lastpost = " . TIMENOW . "
			WHERE userid = $bbuserinfo[userid]
		");

		// ### SEND EMAIL TO MODERATORS ###
		if ($type == 'thread')
		{ // see if we are posting new thread or new reply
			$field = "CONCAT(newthreademail ,' ', newpostemail) AS newpostemail";
			$condition = '((moderator.permissions & 16384) OR (moderator.permissions & 32768))';
		}
		else
		{
			$field = 'newpostemail';
			$condition = '(moderator.permissions & 32768)';
		}

		$moderators = $DB_site->query("
			SELECT $field
			FROM " . TABLE_PREFIX . "forum
			WHERE forumid IN (" . addslashes($foruminfo['parentlist']) . ")
		");
		while ($moderator = $DB_site->fetch_array($moderators))
		{
			$newpostemail .= ' ' . $moderator['newpostemail'];
		}

		$mods = $DB_site->query("
			SELECT DISTINCT user.email, user.languageid
			FROM " . TABLE_PREFIX . "moderator AS moderator
			LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
			WHERE moderator.forumid IN (" . addslashes($foruminfo['parentlist']) . ") AND
				$condition
		");
		$newpost_lang = array();
		while ($mod = $DB_site->fetch_array($mods))
		{
			$newpost_lang["$mod[email]"] = $user['languageid'];
			$newpostemail .= ' ' . $mod['email'];
		}

		$newpostemail = trim($newpostemail);

		if (!empty($newpostemail) AND !in_coventry($bbuserinfo['userid'], true))
		{
			$foruminfo['title'] = unhtmlspecialchars($foruminfo['title']);
			$threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
			$post['message'] = strip_bbcode($post['message']);
			$bbuserinfo['username'] = unhtmlspecialchars($bbuserinfo['username']); //for emails
			$mods = explode(' ', $newpostemail);
			$mods = array_unique($mods);
			foreach($mods AS $toemail)
			{
				if (trim($toemail))
				{
					eval(fetch_email_phrases('moderator', iif(isset($newpost_lang["$toemail"]), $newpost_lang["$toemail"], 0)));
					vbmail($toemail, $subject, $message);
				}
			}
			$bbuserinfo['username'] = htmlspecialchars_uni($bbuserinfo['username']); //back to norm
		}
	}
	// ### DO EMAIL NOTIFICATION ###
	if ($post['visible'] AND !$prevpostfound AND $type != 'thread' AND !in_coventry($bbuserinfo['userid'], true))
	{
		// Send out subscription emails
		exec_send_notification($threadinfo['threadid'], $bbuserinfo['userid'], $post['postid']);
	}

	// ### DO THREAD SUBSCRIPTION ###
	if ($bbuserinfo['userid'] != 0)
	{
		require_once('./includes/functions_misc.php');
		$post['emailupdate'] = verify_subscription_choice($post['emailupdate'], $bbuserinfo, 9999);

		if (!$threadinfo['issubscribed'] AND $post['emailupdate'] != 9999)
		{ // user is not subscribed to this thread so insert it
			$DB_site->query("INSERT IGNORE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid)
					VALUES ($bbuserinfo[userid], $threadinfo[threadid], $post[emailupdate], $post[folderid])");
		}
		else
		{ // User is subscribed, see if they changed the settings for this thread
			if ($post['emailupdate'] == 9999)
			{	// Remove this subscription, user chose 'No Subscription'
				$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE threadid = $threadinfo[threadid] AND userid = $bbuserinfo[userid]");
			}
			else if ($threadinfo['emailupdate'] != $post['emailupdate'] OR $threadinfo['folderid'] != $post['folderid'])
			{
				// User changed the settings so update the current record
				$DB_site->query("REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid)
					VALUES ($bbuserinfo[userid], $threadinfo[threadid], $post[emailupdate], $post[folderid])");
			}
		}
	}

}

// ###################### Start ratethread #######################
function build_thread_rating($rating, $foruminfo, $threadinfo)
{
	// add thread rating into DB

	global $bbuserinfo, $vboptions, $DB_site;

	if ($rating >= 1 AND $rating <= 5 AND $foruminfo['allowratings'])
	{
		if ($bbuserinfo['forumpermissions'][$foruminfo['forumid']] & CANTHREADRATE)
		{ // see if voting allowed
			$vote = intval($rating);
			if ($ratingsel = $DB_site->query_first("
				SELECT vote, threadrateid
				FROM " . TABLE_PREFIX . "threadrate
				WHERE userid = $bbuserinfo[userid] AND
				threadid = $threadinfo[threadid]
			"))
			{ // user has already voted
				if ($vboptions['votechange'])
				{ // if allowed to change votes
					if ($vote != $ratingsel['vote'])
					{ // if vote is different to original
						$voteupdate = $vote - $ratingsel['vote'];
						$DB_site->query("
							UPDATE " . TABLE_PREFIX . "threadrate
							SET vote = $vote
							WHERE threadrateid = $ratingsel[threadrateid]
						");
						$DB_site->query("
							UPDATE " . TABLE_PREFIX . "thread
							SET votetotal = votetotal + $voteupdate
							WHERE threadid = $threadinfo[threadid]
						");
					}
				}
			}
			else
			{	// insert new vote
				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "threadrate
					(threadid, userid, vote)
					VALUES
					($threadinfo[threadid], $bbuserinfo[userid], $vote)
				");
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "thread
					SET votetotal = votetotal + $vote,
					votenum = votenum + 1
					WHERE threadid = $threadinfo[threadid]
				");
			}
		}
	}
}

// ###################### Start checkforerrors #######################
function verify_post_errors($type = 'thread', $post, &$errors)
{
	global $DB_site, $vboptions, $vbphrase, $foruminfo, $threadinfo, $bbuserinfo;

	// check supplied username if guest
	if ($bbuserinfo['userid'] == 0)
	{
		$post['username'] = preg_replace('#( ){2,}#', ' ', $post['username']);

		if (empty($post['username']))
		{

			eval('$errors[] = "' . fetch_phrase('nousername', PHRASETYPEID_ERROR) . '";');

		}
		else if (vbstrlen($post['username']) < $vboptions['minuserlength'])
		{

			eval('$errors[] = "' . fetch_phrase('usernametooshort', PHRASETYPEID_ERROR) . '";');

		}
		else if (vbstrlen($post['username']) > $vboptions['maxuserlength'])
		{

			eval('$errors[] = "' . fetch_phrase('usernametoolong', PHRASETYPEID_ERROR) . '";');

		}
		else if ($post['username'] != fetch_censored_text($post['username']) OR $checkuser = $DB_site->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes(htmlspecialchars_uni($post['username'])) . "'"))
		{
			$username = &$post['username'];
			eval('$errors[] = "' . fetch_phrase('usernametaken', PHRASETYPEID_ERROR) . '";');
		}
		else if (!empty($vboptions['illegalusernames']))
		{

			$usernames = preg_split('/( )+/', trim(strtolower($vboptions['illegalusernames'])), -1, PREG_SPLIT_NO_EMPTY);
			$tempusername = strtolower($post['username']);
			foreach ($usernames AS $val)
			{
				if (strpos($tempusername, $val) !== false)
				{
					$username = &$val;
					eval('$errors[] = "' . fetch_phrase('usernametaken', PHRASETYPEID_ERROR) . '";');
					break;
				}
			}

		}
	}

	// check for subject/message
	if (($post['message'] == '' AND $vboptions['postminchars'] > 0) OR ($type == 'thread' AND empty($post['title'])))
	{
		eval('$errors[] = "' . fetch_phrase('nosubject', PHRASETYPEID_ERROR) . '";');
	}

	// check max length
	if (($postlength = vbstrlen($post['message'])) > $vboptions['postmaxchars'] AND $vboptions['postmaxchars'] != 0)
	{
		eval('$errors[] = "' . fetch_phrase('toolong', PHRASETYPEID_ERROR) . '";');
	}

	// check min length (this strips out quotes and vbcode from the message)
	if ($vboptions['postminchars'] <= 0)
	{
		++$vboptions['postminchars'];
	}
	if (vbstrlen(strip_bbcode($post['message'], $vboptions['ignorequotechars'], false, false)) < $vboptions['postminchars'])
	{
		eval('$errors[] = "' . fetch_phrase('tooshort', PHRASETYPEID_ERROR) . '";');
	}

	// check flooding
	if (THIS_SCRIPT != 'editpost' AND $vboptions['floodchecktime'] > 0 AND $bbuserinfo['userid'] != 0 AND (TIMENOW - $bbuserinfo['lastpost']) <= $vboptions['floodchecktime'] AND !can_moderate($foruminfo['forumid']) AND !$post['preview'])
	{
		eval('$errors[] = "' . fetch_phrase('floodcheck', PHRASETYPEID_ERROR) . '";');
	}

	// check max images
	require_once('./includes/functions_misc.php');
	require_once('./includes/functions_bbcodeparse.php');
	if ($vboptions['maximages'] != 0 AND fetch_character_count(parse_bbcode($post['message'], $foruminfo['forumid'], $post['enablesmilies'], 1), '<img') > $vboptions['maximages'])
	{
		eval('$errors[] = "' . fetch_phrase('toomanyimages', PHRASETYPEID_ERROR) . '";');
	}

	return sizeof($errors);
}

// ###################### Start processerrors #######################
function construct_errors($errors)
{
	global $vboptions, $bbuserinfo, $vbphrase, $stylevar, $show;

	$errorlist = '';
	foreach ($errors AS $key => $errormessage)
	{
		eval('$errorlist .= "' . fetch_template('newpost_errormessage') . '";');
	}
	$show['errors'] = true;
	eval('$errortable = "' . fetch_template('newpost_preview') . '";');

	return $errortable;
}

// ###################### Start processpreview #######################
function process_post_preview(&$newpost, $postuserid = 0)
{
	global $vboptions, $vbphrase, $bbuserinfo, $checked, $rate, $previewpost, $stylevar, $foruminfo, $DB_site;

	require_once('./includes/functions_bbcodeparse.php');

	$previewpost = 1;
	$previewmessage = parse_bbcode($newpost['message'], $foruminfo['forumid'], iif($newpost['disablesmilies'], 0, 1));

	// set default to the signature of the editing user, if it differs it will be changed below
	$signature = $bbuserinfo['signature'];
	if ($postuserid)
	{
		$fetchsignature = $DB_site->query_first("
			SELECT signature
			FROM " . TABLE_PREFIX . "usertextfield
			WHERE userid = $postuserid
		");
		$signature = $fetchsignature['signature'];
		unset ($fetchsignature);
	}

	if ($newpost['signature'] AND $vboptions['allowsignatures'] AND trim($signature))
	{
		$post['signature'] = parse_bbcode($signature, 0, $vboptions['allowsmilies']);
		$show['signature'] = true;
	}
	else
	{
		$show['signature'] = false;
	}

	if ($foruminfo['allowicons'] AND $newpost['iconid'])
	{
		if ($icon = $DB_site->query_first("
			SELECT title as title, iconpath
			FROM " . TABLE_PREFIX . "icon
			WHERE iconid = " . intval($newpost['iconid']) . "
		"))
		{
			$newpost['iconpath'] = $icon['iconpath'];
			$newpost['icontitle'] = $icon['title'];
		}
	}
	else if ($vboptions['showdeficon'] != '')
	{
		$newpost['iconpath'] = $vboptions['showdeficon'];
		$newpost['icontitle'] = $vbphrase['default'];
	}

	$show['messageicon'] = iif($newpost['iconpath'], true, false);
	$show['errors'] = false;
	if ($previewmessage != '')
	{
		eval('$postpreview = "' . fetch_template('newpost_preview')."\";");
	}
	else
	{
		$postpreview = '';
	}

	construct_checkboxes($newpost);

	if ($newpost['rating'])
	{
		$rate["$newpost[rating]"] = ' '.HTML_SELECTED;
	}

	return $postpreview;
}

// ###################### Start processcheckboxes #######################
function construct_checkboxes($post)
{
	global $checked;

	$checked = array(
		'parseurl' => iif($post['parseurl'], HTML_CHECKED),
		'disablesmilies' => iif($post['disablesmilies'], HTML_CHECKED),
		'signature' => iif($post['signature'], HTML_CHECKED),
		'postpoll' => iif($post['postpoll'], HTML_CHECKED),
		'savecopy' => iif($post['savecopy'], HTML_CHECKED),
		'stickunstick' => iif($post['stickunstick'], HTML_CHECKED),
		'openclose' => iif($post['openclose'], HTML_CHECKED)
	);
}

// ###################### Start stopshouting #######################
function fetch_no_shouting_text($text)
{
	// stops $text being all UPPER CASE
	global $vboptions;

	return iif($vboptions['stopshouting'] AND $text == strtoupper($text), ucwords(vbstrtolower($text)), $text);
}

// ###################### Start sendnotification #######################
function exec_send_notification($threadid, $userid, $postid)
{
	// $threadid = threadid to send from;
	// $userid = userid of who made the post
	// $postid = only sent if post is moderated -- used to get username correctly

	global $DB_site, $vboptions, $message, $postusername, $bbuserinfo, $usergroupcache;

	if (!$vboptions['enableemail'])
	{
		return;
	}

	$threadinfo = fetch_threadinfo($threadid);
	$foruminfo = fetch_foruminfo($threadinfo['forumid']);

	// get last reply time
	if ($postid)
	{
		$dateline = $DB_site->query_first("
			SELECT dateline, pagetext
			FROM " . TABLE_PREFIX . "post
			WHERE postid = $postid
		");

		$pagetext = $dateline['pagetext'];

		$lastposttime = $DB_site->query_first("
			SELECT MAX(dateline) AS dateline
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
			WHERE threadid = $threadid
			AND dateline < $dateline[dateline]
			AND visible = 1
			AND deletionlog.primaryid IS NULL
		");
	}
	else
	{
		$lastposttime = $DB_site->query_first("
			SELECT MAX(postid) AS postid, MAX(dateline) AS dateline
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
			WHERE threadid = $threadid
			AND visible = 1
			AND deletionlog.primaryid IS NULL
		");

		$pagetext = $DB_site->query_first("
			SELECT pagetext
			FROM " . TABLE_PREFIX . "post
			WHERE postid = $lastposttime[postid]
		");
		$pagetext = $pagetext['pagetext'];
	}

	// strip bbcode and quotes from notification text
	$pagetext = strip_bbcode($pagetext, 1);

	$useremails = $DB_site->query("
		SELECT user.*, subscribethread.emailupdate
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread, " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		WHERE subscribethread.threadid = $threadid AND
			subscribethread.emailupdate IN (1, 4) AND
			subscribethread.userid = user.userid AND
			" . ($userid ? "CONCAT(' ', usertextfield.ignorelist, ' ') NOT LIKE ' " . intval($userid) . " ' AND" : '') . "
			user.usergroupid <> 3 AND
			user.userid <> " . intval($userid) . " AND
			user.lastactivity > " . intval($lastposttime['dateline']) . " AND
			(usergroup.genericoptions & " . ISBANNEDGROUP . ") = 0
	");

	$threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
	$foruminfo['title'] = unhtmlspecialchars($foruminfo['title']);

	$temp = $bbuserinfo['username'];
	if ($postid)
	{
		$postinfo = fetch_postinfo($postid);
		$bbuserinfo['username'] = unhtmlspecialchars($postinfo['username']);
	}
	else
	{
		if (!$bbuserinfo['userid'])
		{
			$bbuserinfo['username'] = unhtmlspecialchars($postusername);
		}
		else
		{
			$bbuserinfo['username'] = unhtmlspecialchars($bbuserinfo['username']);
		}
	}

	vbmail_start();

	$evalemail = array();
	while ($touser = $DB_site->fetch_array($useremails))
	{
		if ($usergroupcache["$touser[usergroupid]"]['genericoptions'] & ISBANNEDGROUP)
		{
			continue;
		}
		$touser['username'] = unhtmlspecialchars($touser['username']);
		$touser['languageid'] = iif($touser['languageid'] == 0, $vboptions['languageid'], $touser['languageid']);

		if (empty($evalemail))
		{
			$email_texts = $DB_site->query("
				SELECT text, languageid, phrasetypeid
				FROM " . TABLE_PREFIX . "phrase
				WHERE phrasetypeid IN (" . PHRASETYPEID_MAILSUB . ", " . PHRASETYPEID_MAILMSG . ") AND varname = 'notify'
			");

			while ($email_text = $DB_site->fetch_array($email_texts))
			{
				$emails["$email_text[languageid]"]["$email_text[phrasetypeid]"] = $email_text['text'];
			}

			foreach ($emails AS $languageid => $email_text)
			{ // lets cycle through our array of notify phrases
				$evalemail["$languageid"] = '$message = "' . str_replace("\\'", "'", addslashes(iif(empty($email_text[PHRASETYPEID_MAILMSG]), $emails['-1'][PHRASETYPEID_MAILMSG],$email_text[PHRASETYPEID_MAILMSG]))) . '";'.
					'$subject = "' . str_replace("\\'", "'", addslashes(iif(empty($email_text[PHRASETYPEID_MAILSUB]), $emails['-1'][PHRASETYPEID_MAILSUB],$email_text[PHRASETYPEID_MAILSUB]))) . '";';
			}

		}

		eval(iif(empty($evalemail["$touser[languageid]"]), $evalemail["-1"], $evalemail["$touser[languageid]"]));

		if ($touser['emailupdate'] == 4 AND !empty($touser['icq']))
		{ // instant notification by ICQ
			$touser['email'] = $touser['icq'] . '@pager.icq.com';
		}

		vbmail($touser['email'], $subject, $message);
	}

	$bbuserinfo['username'] = $temp;

	vbmail_end();

}

// ###################### Start stripemptyvbcode #######################
function strip_empty_bbcode($text)
{
	return preg_replace('#(^.*|.*)(\[(php|html)\].*\[/\\3])(.*|.*$)#siUe', "strip_empty_bbcode_callback('\\1', '\\2', '\\4')", $text);
	/*while(preg_match_all('#(\[([^=\]]+)(=[^\]]+)?]\s*\[/\\2])#siU', $text, $regs))
	{
		$text = str_replace($regs[0], '', $text);
	}
	return $text;*/
}

// ###################### Start stripemptyvbcode #######################
function strip_empty_bbcode_callback($before, $inner, $after)
{
	$before = str_replace('\"', '"', $before);
	$inner = str_replace('\"', '"', $inner); // this is text in php/html tags, so empty tags aren't stripped
	$after = str_replace('\"', '"', $after);

	$before = preg_replace('#(\[([^=\]]+)(=[^\]]+)?]\s*\[/\\2])#siU', '', $before);
	$after = preg_replace('#(\[([^=\]]+)(=[^\]]+)?]\s*\[/\\2])#siU', '', $after);

	return $before . $inner . $after;
}

// ###################### Start fetch_quote_username #######################
// this deals with the problem of quoting usernames that contain square brackets.
// note the following:
//	alphanum + square brackets => WORKS
//	alphanum + square brackets + single quotes => WORKS
//	alphanum + square brackets + double quotes => WORKS
//	alphanum + square brackets + single quotes + double quotes => BREAKS (can't quote a string containing both types of quote)
function fetch_quote_username($username)
{
	$username = unhtmlspecialchars($username);

	if (strpos($username, '[') !== false OR strpos($username, ']') !== false)
	{
		if (strpos($username, "'") !== false)
		{
			return '"' . $username . '"';
		}
		else
		{
			return "'$username'";
		}
	}
	else
	{
		return $username;
	}
}

// ###################### Start fetch_quote_title #######################
// checks the parent post and thread for a title to fill the default title field
function fetch_quote_title($parentposttitle, $threadtitle)
{
	global $vboptions, $vbphrase;

	if ($vboptions['quotetitle'])
	{
		if ($parentposttitle != '')
		{
			$posttitle = $parentposttitle;
		}
		else
		{
			$posttitle = $threadtitle;
		}
		$posttitle = unhtmlspecialchars($posttitle);
		$posttitle = preg_replace('#^(' . preg_quote($vbphrase['reply_prefix'], '#') . '\s*)+#i', '', $posttitle);
		return "$vbphrase[reply_prefix] $posttitle";
	}
	else
	{
		return '';
	}
}

// ###################### Start fetch emailchecked #######################
// function to fetch the array containing the checked="checked" value for thread subscription
function fetch_emailchecked($threadinfo, $userinfo = false, $newpost = false)
{

	if (is_array($newpost) AND $newpost['emailupdate'])
	{
		$choice = $newpost['emailupdate'];
	}
	else
	{
		if ($threadinfo['issubscribed'])
		{
			$choice = $threadinfo['emailupdate'];
		}
		else if (is_array($userinfo) AND $userinfo['autosubscribe'] != -1)
		{
			$choice = $userinfo['autosubscribe'];
		}
		else
		{
			$choice = 9999;
		}
	}

	require_once('./includes/functions_misc.php');
	$choice = verify_subscription_choice($choice, $userinfo, 9999, false);

	return array($choice => HTML_SELECTED);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13954 $
|| ####################################################################
\*======================================================================*/
?>