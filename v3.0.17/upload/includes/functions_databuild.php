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

// ###################### Start updateusertextfields #######################
// takes the field type pmfolders/buddylist/ignorelist/signature in 'field'
// takes the value to insert in $value
function build_usertextfields($field, $value, $userid = 0)
{
	global $DB_site, $bbuserinfo;

	if ($userid == 0)
	{
		$userid = $bbuserinfo['userid'];
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "usertextfield
		SET $field = '" . addslashes($value) . "'
		WHERE userid = $userid
	");

	return 0;
}

// ###################### Start getforumcache #######################
function cache_forums($forumid = -1, $depth = 0)
{
	// returns an array of forums with correct parenting and depth information
	// see makeforumchooser for an example of usage

	global $DB_site, $forumcache, $count;
	static $fcache, $i;
	if (!is_array($fcache))
	{
	// check to see if we have already got the results from the database
		$fcache = array();
		$forums = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "forum ORDER BY parentid, displayorder");
		while ($forum = $DB_site->fetch_array($forums))
		{
			$fcache["$forum[parentid]"]["$forum[displayorder]"]["$forum[forumid]"] = $forum;
		}
	}

	// database has already been queried
	if (is_array($fcache["$forumid"]))
	{
		foreach ($fcache["$forumid"] AS $holder)
		{
			foreach ($holder AS $forum)
			{
				$forumcache["$forum[forumid]"] = $forum;
				$forumcache["$forum[forumid]"]['depth'] = $depth;
				unset($fcache["$forumid"]);
				cache_forums($forum['forumid'], $depth + 1);
			} // end foreach ($val1 AS $key2 => $forum)
		} // end foreach ($fcache["$forumid"] AS $key1 => $val1)
	} // end if (found $fcache["$forumid"])
}

// ###################### Start updateforumcount #######################
// updates forum counters and last post info
function build_forum_counters($forumid)
{
	global $DB_site;

	$forumid = intval($forumid);
	$foruminfo = fetch_foruminfo($forumid);

	// get counters
	$threads = $DB_site->query_first("
		SELECT COUNT(*) AS threads, SUM(replycount) AS replies
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE forumid = $forumid AND
		visible = 1 AND
		open <> 10 AND
		deletionlog.primaryid IS NULL
	");
	// get last thread
	$lastthread = $DB_site->query_first("
		SELECT * FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE forumid = $forumid AND
		visible = 1 AND
		open <> 10 AND
		deletionlog.primaryid IS NULL
		ORDER BY lastpost DESC
		LIMIT 1
	");
	// update forum
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "forum SET
		threadcount = " . intval($threads['threads']) . ",
		replycount = " . intval($threads['threads'] + $threads['replies']) . ",
		lastpost = " . intval($lastthread['lastpost']) . ",
		lastposter = '" . addslashes($lastthread['lastposter']) . "',
		lastthread = '" . addslashes($lastthread['title']) . "',
		lastthreadid = " . intval($lastthread['threadid']) . ",
		lasticonid = " . intval(iif($lastthread['pollid'], -1, $lastthread['iconid'])) . "
		WHERE forumid = $forumid
	");
}

// ###################### Start updatethreadcount #######################
function build_thread_counters($threadid)
{
	global $DB_site, $threadcache;

	$threadid = intval($threadid);

	$replies = $DB_site->query_first("
		SELECT COUNT(*)-1 AS replies, SUM(post.attach) AS attachsum
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
		WHERE threadid = $threadid AND
		post.visible <> 0 AND
		deletionlog.primaryid IS NULL
	");
	if ($replies['replies'] == -1)
	{
		return; // no posts, thread most likely isn't valid anyway
	}

	$lastposts = $DB_site->query_first("
		SELECT user.username, post.username AS postuser, post.dateline
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = post.userid
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
		WHERE post.threadid = $threadid AND
		post.visible <> 0 AND
		deletionlog.primaryid IS NULL
		ORDER BY dateline DESC
	");

	$lastposter = iif(empty($lastposts['username']), $lastposts['postuser'], $lastposts['username']);
	$lastposttime = intval($lastposts['dateline']);

	$firstposts = $DB_site->query_first("
		SELECT post.postid, post.userid, user.username, post.username AS postuser, post.dateline
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = post.userid
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
		WHERE post.threadid = $threadid AND
		post.visible <> 0 AND
		deletionlog.primaryid IS NULL
		ORDER BY dateline ASC
	");

	$firstposter = iif(empty($firstposts['username']), $firstposts['postuser'], $firstposts['username']);
	$firstposterid = intval($firstposts['userid']);
	$firstpostid = intval($firstposts['postid']);
	$threadcreation = $firstposts['dateline'];

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "thread SET
		firstpostid = $firstpostid,
		postusername = '" . addslashes($firstposter) . "',
		postuserid = $firstposterid,
		lastpost = $lastposttime,
		replycount = $replies[replies],
		attach = $replies[attachsum],
		dateline = $threadcreation,
		lastposter = '" . addslashes($lastposter) . "'
		WHERE threadid = $threadid
	");

}

// ###################### Start deletethread #######################
function delete_thread($threadid, $countposts = 1, $physicaldel = 1, $delinfo = NULL, $dolog = true)
{
	global $DB_site, $vbphrase, $vboptions;

	// decrement users post counts
	if ($threadinfo = fetch_threadinfo($threadid))
	{
		if (can_moderate() AND $dolog)
		{
			// is a moderator, so log it
			fetch_phrase_group('threadmanage');
			if ($physicaldel == 0)
			{
				$type = 'thread_softdeleted';
			}
			else
			{
				$type = 'thread_removed';
			}

			require_once('./includes/functions_log_error.php');
			log_moderator_action($threadinfo, construct_phrase($vbphrase["$type"]));
		}

		$postids = '';
		$posts = $DB_site->query("
			SELECT post.userid, postid, NOT ISNULL(deletionlog.primaryid) AS isdeleted, attach
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
			WHERE threadid = $threadid
		");
		$attachmentids = array();
		$userbyuserid = array();
		while ($post = $DB_site->fetch_array($posts))
		{
			if ($countposts AND !$post['isdeleted'] AND $post['userid'])
			{ // deleted posts have already been subtracted and ignore guest posts
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
			$attachmentids['postid'] = $post['attach'];
			if ($physicaldel == 1)
			{
				delete_post_index($post['postid']); //remove search engine entries
			}
		}

		if (!empty($userbyuserid) AND !$threadinfo['isdeleted'])
		{ // if the thread is already deleted, the posts have already been reduced
			$userbypostcount = array();
			foreach ($userbyuserid AS $postuserid => $postcount)
			{
				$alluserids .= ",$postuserid";
				$userbypostcount["$postcount"] .= ",$postuserid";
			}
			foreach($userbypostcount AS $postcount => $userids)
			{
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount";
			}

			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "user
				SET posts = posts -
				CASE
					$casesql
					ELSE 0
				END
				WHERE userid IN (0$alluserids)
			");
		}

		if (!empty($postids))
		{
			if ($physicaldel == 1 OR (empty($delinfo['keepattachments']) AND can_moderate($threadinfo['forumid'], 'canremoveposts')))
			{
				if ($vboptions['attachfile'])
				{
					$attachments = $DB_site->query("
						SELECT attachmentid, userid
						FROM " . TABLE_PREFIX . "attachment
						WHERE postid IN ($postids" . "-1)
					");
					$ids = array();
					while ($attachment = $DB_site->fetch_array($attachments))
					{
						$ids["$attachment[attachmentid]"] = $attachment['userid'];
					}

					require_once('./includes/functions_file.php');
					delete_attachment_files($ids);

				}
				$DB_site->query("
					DELETE FROM " . TABLE_PREFIX . "attachment
					WHERE postid IN ($postids" . "-1)
				");
			}
		}

		if ($physicaldel == 0)
		{
			if (!is_array($delinfo))
			{
				global $bbuserinfo;
				$delinfo = array('userid' => $bbuserinfo['userid'], 'username' => $bbuserinfo['username'], 'reason' => '');
			}
			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "deletionlog
				(primaryid, type, userid, username, reason)
				VALUES
				($threadinfo[threadid], 'thread', $delinfo[userid], '" . addslashes($delinfo['username']) . "',
				 '" . addslashes(htmlspecialchars_uni(fetch_censored_text($delinfo['reason']))) . "')
			");
			$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET visible = 1 " . iif(!$delinfo['keepattachments'], ', attach = 0') . " WHERE threadid = $threadinfo[threadid]");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderation WHERE threadid = $threadinfo[threadid]");
			return;
		}

		if (!empty($postids))
		{
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "post WHERE postid IN ($postids" . "0)");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "post_parsed WHERE postid IN ($postids" . "0)");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "reputation WHERE postid IN ($postids" . "0)");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderation WHERE postid IN ($postids" . "0)");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "editlog WHERE postid IN ($postids" . "0)");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE type = 'post' AND primaryid IN ($postids" . "0)");
		}
		if ($threadinfo['pollid'] != 0)
		{
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "poll WHERE pollid = $threadinfo[pollid]");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "pollvote WHERE pollid = $threadinfo[pollid]");
		}
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "thread WHERE threadid = $threadid");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "thread WHERE open=10 AND pollid = $threadid"); // delete redirects
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $threadid AND type = 'thread'");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "threadrate WHERE threadid = $threadid");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE threadid = $threadid");
	}
}

// ###################### Start deletepost #######################
function delete_post($postid, $countposts = 1, $threadid = 0, $physicaldel = 1, $delinfo = NULL, $dolog = true)
{
	global $DB_site, $vbphrase, $bbuserinfo, $threadinfo, $vboptions;

	$postid = intval($postid);
	$threadid = intval($threadid);

	if (!is_array($delinfo))
	{
		$delinfo = array('userid' => $bbuserinfo['userid'], 'username' => $bbuserinfo['username'], 'reason' => '', 'keepattachments' => 0);
	}
	else
	{
		if (!$delinfo['userid'])
		{
			$delinfo['userid'] = $bbuserinfo['userid'];
		}
		if (!$delinfo['username'])
		{
			$delinfo['username'] = $bbuserinfo['username'];
		}
	}

	if ($postinfo = fetch_postinfo($postid))
	{
		$threadinfo = fetch_threadinfo($postinfo['threadid']);
		if (can_moderate() AND $dolog)
		{
			fetch_phrase_group('threadmanage');

			if ($physicaldel == 0)
			{
				$type = 'post_x_by_y_softdeleted';
			}
			else
			{
				$type = 'post_x_by_y_removed';
			}

			require_once('./includes/functions_log_error.php');
			log_moderator_action($postinfo, construct_phrase($vbphrase["$type"], $postinfo['title'], $postinfo['username']));
		}

		if ($countposts AND $postinfo['isdeleted'] == 0)
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET posts = posts - 1 WHERE userid = $postinfo[userid]");
		}

		if ($postinfo['attach'])
		{
			if ($physicaldel == 1 OR (empty($delinfo['keepattachments']) AND can_moderate($threadinfo['forumid'], 'canremoveposts')))
			{
				if ($vboptions['attachfile'])
				{
					$attachments = $DB_site->query("
						SELECT attachmentid, userid
						FROM " . TABLE_PREFIX . "attachment
						WHERE postid = $postinfo[postid]
					");
					while ($attachment = $DB_site->fetch_array($attachments))
					{
						$ids["$attachment[attachmentid]"] = $attachment['userid'];
					}

					require_once('./includes/functions_file.php');
					delete_attachment_files($ids);
				}
				$DB_site->query("DELETE FROM " . TABLE_PREFIX . "attachment WHERE postid = $postid");
			}
		}

		if ($physicaldel == 0)
		{
			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "deletionlog
				(primaryid, type, userid, username, reason)
				VALUES
				($postinfo[postid], 'post', $delinfo[userid], '" . addslashes($delinfo['username']) . "',
				'" . addslashes(fetch_censored_text(htmlspecialchars_uni($delinfo['reason']))) . "')
			");
			$DB_site->query("UPDATE " . TABLE_PREFIX . "post SET visible = 1 WHERE postid = $postinfo[postid]");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderation WHERE postid = $postinfo[postid] AND type = 'reply'");
			return;
		}

		// delete post hash when physically deleting a post - last argument is type
		$dupehash = md5($threadinfo['forumid'] . $postinfo['title'] . $postinfo['pagetext'] . $postinfo['userid'] . 'reply');

		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "posthash
			WHERE userid = $postinfo[userid] AND
			dupehash = '" . addslashes($dupehash) . "' AND
			dateline > " . (TIMENOW - 300)
		);

		// Hook this post's children up to it's parent so they aren't orphaned. Foster parents I guess.
		if ($postinfo['parentid'] == 0)
		{
			$firstchild = $DB_site->query_first("
				SELECT postid
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = $postinfo[threadid] AND
				parentid = $postid
				ORDER BY dateline
			");
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "post
				SET parentid = 0
				WHERE postid = " . intval($firstchild['postid'])
			);
			$postinfo['parentid'] = $firstchild['postid'];
		}
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "post
			SET parentid = " . intval($postinfo['parentid']) . "
			WHERE threadid = $postinfo[threadid] AND
			parentid = $postid
		");

		delete_post_index($postid);
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "post WHERE postid = $postid");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "post_parsed WHERE postid = $postid");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $postid AND type = 'post'");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "editlog WHERE postid = $postid");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "reputation WHERE postid = $postid");
	}
}

// ###################### Start indexword #######################
function is_index_word($word)
{
	global $vboptions, $badwords, $goodwords;

	require_once('./includes/searchwords.php'); // get the stop word list

	// is the word in the goodwords array?
	if (in_array(strtolower($word), $goodwords))
	{
		return true;
	}
	else
	{
		// is the word outside the min/max char lengths for indexing?
		$wordlength = strlen($word);
		if ($wordlength < $vboptions['minsearchlength'] OR $wordlength > $vboptions['maxsearchlength'])
		{
			return false;
		}
		// is the word a common/bad word?
		else if (in_array(strtolower($word), $badwords))
		{
			return false;
		}
		// word is good
		else
		{
			return true;
		}
	}

}

// ###################### Start indexpost #######################
function build_post_index($postid, $foruminfo, $firstpost = -1, $post = false)
{
	global $vboptions;

	if ($vboptions['fulltextsearch'])
	{
		return;
	}

	if ($foruminfo['indexposts'])
	{
		global $DB_site, $vboptions;
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
			$post = $DB_site->query_first("
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
			$getfirstpost = $DB_site->query_first("
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
				$threadinfo = $DB_site->query_first("
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
				#$scores["$word"] += $vboptions['threadtitlescore'];
				$intitle["$word"] = 2;
				$scores["$word"]++;
			}
		}

		$words = fetch_postindex_text($post['title']);
		$allwords .= ' ' . $words;
		$wordarray = explode(' ', $words);

		foreach ($wordarray AS $word)
		{
			#$scores["$word"] += $vboptions['posttitlescore'];
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
		$words = $DB_site->query("SELECT wordid, title FROM " . TABLE_PREFIX . "word WHERE $getwordidsql");
		while ($word = $DB_site->fetch_array($words))
		{
			$word['title'] = vbstrtolower($word['title']);
			$wordcache["$word[title]"] = $word['wordid'];
		}
		$DB_site->free_result($words);

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
					$insertsql .= ", (" . addslashes($wordcache["$word"]) . ", $postid, $score, $intitle[$word])"; // yes so just add a postindex entry for this post/word
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
			$DB_site->query("
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
			$DB_site->query("INSERT IGNORE INTO " . TABLE_PREFIX . "word (title) VALUES $insertwords");
			$selectwords = "title IN ('" . str_replace(" ", "','", $newwords) . "')";

			$scoressql = 'CASE title';
			foreach ($scores AS $word => $score)
			{
				$scoressql .= " WHEN '" . addslashes($word) . "' THEN $score";
			}
			$scoressql .= ' ELSE 1 END';

			$titlesql = 'CASE title';
			foreach($intitle AS $word => $intitlescore)
			{
				$titlesql .= " WHEN '" . addslashes($word) . "' THEN $intitlescore";
			}
			$titlesql .= ' ELSE 0 END';

			$DB_site->query("
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
	global $DB_site;

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
			'#\s+(-|\+)+([^\s]+)#si',					// remove leading +/- characters
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
	global $DB_site, $vboptions;

	if ($vboptions['fulltextsearch'])
	{
		return;
	}

	$postid = intval($postid);
	// get the data
	if (empty($pagetext))
	{
		$post = $DB_site->query_first("
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
	$words = $DB_site->query("SELECT wordid, title FROM " . TABLE_PREFIX . "word WHERE $getwordidsql");

	if ($DB_site->num_rows($words))
	{
		$wordids = '';
		while ($word = $DB_site->fetch_array($words))
		{
			$wordids .= ',' . $word['wordid'];
		}

		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "postindex WHERE wordid IN (0$wordids) AND postid = " . $post['postid']);
	}
}

// ###################### Start saveuserstats #######################
// Save user count & newest user into template
function build_user_statistics()
{
	global $vboptions, $DB_site;

	// get total members
	$members = $DB_site->query_first("SELECT COUNT(*) AS users, MAX(userid) AS max FROM " . TABLE_PREFIX . "user");

	// get newest member
	$newuser = $DB_site->query_first("SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE userid = $members[max]");

	// make a little array with the data
	$values = array(
		'numbermembers' => $members['users'],
		'newusername' => $newuser['username'],
		'newuserid' => $newuser['userid']
	);

	// update the special template
	build_datastore('userstats', serialize($values));

}

// ###################### Start getbirthdays #######################
function build_birthdays()
{
	global $vboptions, $stylevar, $DB_site, $usergroupcache, $session;

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
	foreach($usergroupcache AS $usergroupid => $usergroup)
	{
		if ($usergroup['genericoptions'] & SHOWBIRTHDAY)
		{
			$usergroupids .= ", $usergroupid";
		}
	}

	// if admin wants to only show birthdays for users who have
	// been active within the last $vboptions[birthdaysdatecut] days...
	if ($vboptions['birthdaydatecut'])
	{
		$datecut = TIMENOW - (intval($vboptions['birthdaydatecut']) * 86400);
		$activitycut = "AND lastactivity >= $datecut";
	}
	else
	{
		$activitycut = '';
	}

	$bdays = $DB_site->query("
		SELECT username, userid, birthday
		FROM " . TABLE_PREFIX . "user
		WHERE (birthday LIKE '$todayneggmt-%' OR birthday LIKE '$todayposgmt-%')
		AND usergroupid IN ($usergroupids)
		$activitycut
	");

	$year = date('Y');

	while ($birthday = $DB_site->fetch_array($bdays))
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
			$day1 .= iif($day1, ', ') . "<a href=\"member.php?$session[sessionurl]u=$userid\">$username</a>" . iif($age, " ($age)");
		}
		else
		{
			$day2 .= iif($day2, ', ') . "<a href=\"member.php?$session[sessionurl]u=$userid\">$username</a>" . iif($age, " ($age)");
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
	global $vboptions, $DB_site, $vbphrase;

	if (!$vboptions['showevents'])
	{
		return false;
	}

	$storeevents = array();

	// Store date 12 hours before and after the current time
	$day1 = vbdate('n-j-Y', gmmktime() - 43200, false, false);
	$fromdate = explode('-', $day1);
	$day2 = gmdate('n-j-Y' , TIMENOW + 43200 + (86400 * ($vboptions['showevents'] - 1)));
	$todate = explode('-', $day2);

	$beginday = gmmktime(0, 0, 0, $fromdate[0], $fromdate[1], $fromdate[2]);
	$endday = gmmktime(24, 0, 0, $todate[0], $todate[1], $todate[2]);

	$storeevents['date'] = $day2;

	// check if we have at least one calendar with holidays enabled
	if ($vboptions['showholidays'])
	{
		$holidays = $DB_site->query("
			SELECT *
			FROM " . TABLE_PREFIX . "holiday
		");
		while ($holiday = $DB_site->fetch_array($holidays))
		{
			$holiday['dateline_from'] = $beginday;
			$holiday['dateline_to'] = $endday;
			$holiday['visible'] = 1;
			// $holiday['title'] = $vbphrase['holiday_title_' . $holiday['varname']];
			$holiday['eventid'] = 'h' . $holiday['holidayid'];
			$storeevents["$holiday[eventid]"] = $holiday;
		}
	}

	$events = $DB_site->query("
		SELECT eventid, userid, title, recurring, recuroption, dateline_from, dateline_to, calendarid, IF (dateline_to = 0, 1, 0) AS singleday,
			dateline_from AS dateline_from_user, dateline_to AS dateline_to_user, utc
		FROM " . TABLE_PREFIX . "event AS event
		WHERE ((dateline_to >= $beginday AND dateline_from < $endday) OR (dateline_to = 0 AND dateline_from >= $beginday AND dateline_from <= $endday ))
			AND visible = 1
	");

	while ($event = $DB_site->fetch_array($events))
	{
		$event['title'] = htmlspecialchars_uni($event['title']);
		$storeevents["$event[eventid]"] = $event;
	}

	build_datastore('eventcache', serialize($storeevents));

	return $storeevents;

}

// ###################### Start undeletethread #######################
function undelete_thread($threadid, $countposts = 1)
{
	global $DB_site, $vbphrase;

	if (!$threadinfo = fetch_threadinfo($threadid))
	{
		return;
	}

	if ($countposts)
	{
		$posts = $DB_site->query("
			SELECT post.userid, postid
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
			WHERE threadid = $threadid AND deletionlog.primaryid IS NULL
		");
		$userbyuserid = array();
		while ($post = $DB_site->fetch_array($posts))
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

		unset($userbyuserid[0]); // skip any guest posts
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
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount";
			}

			$DB_site->query("
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

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $threadid AND type = 'thread'");
	build_forum_counters($threadinfo['forumid']);

	fetch_phrase_group('threadmanage');

	require_once('./includes/functions_log_error.php');
	log_moderator_action($threadinfo, construct_phrase($vbphrase['thread_undeleted']));
}

// ###################### Start undeletepost #######################
function undelete_post($postid, $countposts)
{
	global $DB_site, $vbphrase;

	if (!$postinfo = fetch_postinfo($postid))
	{
		return;
	}

	if ($countposts AND $postinfo['userid'])
	{
		$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET posts = posts + 1 WHERE userid = $postinfo[userid]");
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $postid AND type = 'post'");
	build_thread_counters($postinfo['threadid']);
	$threadinfo = fetch_threadinfo($postinfo['threadid']);
	build_forum_counters($threadinfo['forumid']);

	fetch_phrase_group('threadmanage');

	require_once('./includes/functions_log_error.php');
	log_moderator_action($postinfo, construct_phrase($vbphrase['post_y_by_x_undeleted'], $postinfo['title'], $postinfo['username']));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11622 $
|| ####################################################################
\*======================================================================*/
?>