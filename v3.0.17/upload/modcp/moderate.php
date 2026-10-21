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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 11394 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('thread',	'calendar', 'timezone', 'threadmanage');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_databuild.php');

// ############################# LOG ACTION ###############################
log_admin_action(iif(isset($_REQUEST['calendarid']), "calendar id = $_REQUEST[calendarid]", iif(isset($_REQUEST['forumid']), "forum id = $_REQUEST[forumid]")));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['moderation']);

// ###################### Start event moderation #######################
if ($_REQUEST['do'] == 'events')
{
	if (can_moderate())
	{
		$sql = 'OR 1 = 1';
	}
	else
	{
		$calendars = $DB_site->query("SELECT calendarid FROM " . TABLE_PREFIX . "calendar");
		$sql = ' OR calendar.calendarid IN(0';
		while ($calendar = $DB_site->fetch_array($calendars))
		{
			if (can_moderate_calendar($calendar['calendarid'], 'canmoderateevents'))
			{
				$sql .= ", $calendar[calendarid]";
			}
		}
		$sql .= ')';
	}

	print_form_header('moderate', 'doevents');
	print_table_header($vbphrase['events_awaiting_moderation']);
	$events = $DB_site->query("
		SELECT event.*, event.title AS subject, user.username, calendar.title, IF(dateline_to = 0, 1, 0) AS singleday
		FROM " . TABLE_PREFIX . "event AS event
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(event.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "calendar AS calendar ON(calendar.calendarid = event.calendarid)
		WHERE (1 = 0 $sql) AND visible = 0
	");
	$done = false;
	while ($eventinfo = $DB_site->fetch_array($events))
	{
		if ($done)
		{
			print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
		}
		else
		{
			print_description_row('
				<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
				&nbsp;
				<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />
				&nbsp;
				<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
			', 0, 2, 'thead', 'center');
		}
		print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', "<a href=\"user.php?$session[sessionurl]do=viewuser&userid=$eventinfo[userid]\">$eventinfo[username]</a>");
		print_label_row('<b>' . $vbphrase['calendar'] . '</b>', "<a href=\"../calendar.php?$session[sessionurl]calendarid=$eventinfo[calendarid]\">$eventinfo[title]</a>");
		print_input_row('<b>' . $vbphrase['subject'] . '</b>', "eventsubject[$eventinfo[eventid]]", $eventinfo['subject']);

		$time1 =  vbdate($vboptions['timeformat'], $eventinfo['dateline_from']);
		$time2 =  vbdate($vboptions['timeformat'], $eventinfo['dateline_to']);

		if ($eventinfo['singleday'])
		{
			print_label_row('<b>' . $vbphrase['date'] . '</b>', vbdate($vboptions['dateformat'], $eventinfo['dateline_from']));
		}
		else if ($eventinfo['dateline_from'] != $eventinfo['dateline_to'])
		{
			$recurcriteria = fetch_event_criteria($eventinfo);
			$date1 = vbdate($vboptions['dateformat'], $eventinfo['dateline_from']);
			$date2 = vbdate($vboptions['dateformat'], $eventinfo['dateline_to']);

			print_label_row('<b>' . $vbphrase['time'] . '</b>', construct_phrase($vbphrase['x_to_y'], $time1, $time2));
			print_label_row('<b>' . $vbphrase['timezone'] . '</b>', "<select name=\"eventtimezone[$eventinfo[eventid]]\" tabindex=\"1\" class=\"bginput\">" . construct_select_options(fetch_timezones_array(), $eventinfo['utc']) . '</select>');
			print_label_row('<b>' . $vbphrase['date_range'] . '</b>', $recurcriteria . ' | ' . construct_phrase($vbphrase['x_to_y'], $date1, $date2));
		}
		else
		{
			$date = vbdate($vboptions['dateformat'], $eventinfo['from_date']);
			print_label_row('<b>' . $vbphrase['time'] . '</b>', construct_phrase($vbphrase['x_to_y'], $time1, $time2));
			print_label_row('<b>' . $vbphrase['timezone'] . '</b>', "<select name=\"eventtimezone[$eventinfo[eventid]]\" tabindex=\"1\" class=\"bginput\">" . construct_select_options(fetch_timezones_array(), $eventinfo['utc']) . '</select>');
			print_label_row('<b>' . $vbphrase['date_range'] . '</b>', $date);
		}

		print_textarea_row('<b>' . $vbphrase['event'] . '</b>', "eventtext[$eventinfo[eventid]]", $eventinfo['event'], 8, 50);
		print_label_row($vbphrase['action'], "
			<label for=\"val_$eventinfo[eventid]\"><input type=\"radio\" name=\"eventaction[$eventinfo[eventid]]\" value=\"1\" id=\"val_$eventinfo[eventid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
			<label for=\"del_$eventinfo[eventid]\"><input type=\"radio\" name=\"eventaction[$eventinfo[eventid]]\" value=\"-1\" id=\"del_$eventinfo[eventid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>
			<label for=\"ign_$eventinfo[eventid]\"><input type=\"radio\" name=\"eventaction[$eventinfo[eventid]]\" value=\"0\" id=\"ign_$eventinfo[eventid]\" tabindex=\"1\" checked=\"checked\" /> " . $vbphrase['ignore'] . "</label>
		", '', 'top', 'eventaction');
		$done = true;
	}

	if (!$done)
	{
		print_description_row($vbphrase['no_events_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}
}

// ###################### Start do event moderation #######################
if ($_POST['do'] == 'doevents')
{
	globalize($_POST, array('eventaction', 'eventsubject', 'eventtext', 'eventtimezone'));

	foreach ($eventaction AS $eventid => $action)
	{
		$eventid = intval($eventid);
		$getcalendarid = $DB_site->query_first("
			SELECT calendarid
			FROM " . TABLE_PREFIX . "event
			WHERE eventid = $eventid
		");
		if (!can_moderate_calendar($getcalendarid['calendarid'], 'canmoderateevents'))
		{
			continue;
		}

		if ($action == 1)
		{ // validate
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "event
				SET visible = 1,
					utc = '" . addslashes($eventtimezone["$eventid"]) . "',
					title = '" . addslashes($eventsubject["$eventid"]) . "',
					event = '". addslashes($eventtext["$eventid"]) . "'
				WHERE eventid = $eventid
			");
		}
		else if ($action == -1)
		{ // delete
			$DB_site->query("
				DELETE FROM " . TABLE_PREFIX . "event
				WHERE eventid = $eventid
			");
		}
	}
	build_events();

	define('CP_REDIRECT', 'moderate.php?do=events');
	print_stop_message('moderated_events_successfully');
}

// ###################### Start thread/post moderation #######################
if ($_REQUEST['do'] == 'posts')
{
	// fetch threads and posts to be moderated from the moderation table
	// this saves a index on visible and a query with about 3 inner joins
	$threadids = array();
	$postids = array();

	$moderated = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "moderation");
	while ($moderate = $DB_site->fetch_array($moderated))
	{
		if ($moderate['type'] == 'thread')
		{
			$threadids[] = $moderate['threadid'];
		}
		else
		{
			$postids[] = $moderate['postid'];
		}
	}
	$DB_site->free_result($moderated);

	$sql = fetch_moderator_forum_list_sql('canmoderateposts');

	print_form_header('moderate', 'doposts', 0, 1, 'threads');
	print_table_header($vbphrase['threads_awaiting_moderation']);

	if (!empty($threadids))
	{
		$threadids = implode(',', $threadids);
		$threads = $DB_site->query("
			SELECT thread.threadid, thread.title AS title, thread.notes AS notes,
			thread.forumid AS forumid, thread.postuserid AS userid,
			thread.postusername AS username, thread.dateline, thread.firstpostid, pagetext
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON(thread.firstpostid = post.postid)
			WHERE (1 = 0 $sql) AND thread.threadid IN($threadids)
			ORDER BY thread.lastpost
		");

		$havethreads = false;
		while ($thread = $DB_site->fetch_array($threads))
		{
			if ($thread['firstpostid'] == 0)
			{ // eek potential for disaster
				$post_text = $DB_site->query_first("SELECT pagetext FROM " . TABLE_PREFIX . "post WHERE threadid = $thread[threadid] ORDER BY dateline ASC");
				$thread['pagetext'] = $post_text['pagetext'];
			}

			if ($havethreads)
			{
				print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
			}
			else
			{
				print_description_row('
					<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
					&nbsp;
					<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />
					&nbsp;
					<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
				', 0, 2, 'thead', 'center');
			}
			print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($thread['userid'], "<a href=\"user.php?$session[sessionurl]do=viewuser&userid=$thread[userid]\" target=\"_blank\">$thread[username]</a>", $vbphrase['guest']));
			print_label_row('<b>' . $vbphrase['forum'] . '</b>', "<a href=\"../forumdisplay.php?$session[sessionurl]forumid=$thread[forumid]\" target=\"_blank\">" . $forumcache["$thread[forumid]"]['title'] . "</a>");
			print_input_row($vbphrase['title'], "threadtitle[$thread[threadid]]", $thread['title'], 0);
			print_textarea_row($vbphrase['message'], "threadpagetext[$thread[threadid]]", $thread['pagetext'], 7, 55);
			print_input_row($vbphrase['notes'], "threadnotes[$thread[threadid]]", $thread['notes'], 1, 55);
			print_label_row($vbphrase['action'], "
				<label for=\"val_$thread[threadid]\"><input type=\"radio\" name=\"threadaction[$thread[threadid]]\" value=\"1\" id=\"val_$thread[threadid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
				<label for=\"del_$thread[threadid]\"><input type=\"radio\" name=\"threadaction[$thread[threadid]]\" value=\"-1\" id=\"del_$thread[threadid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>
				<label for=\"ign_$thread[threadid]\"><input type=\"radio\" name=\"threadaction[$thread[threadid]]\" value=\"0\" id=\"ign_$thread[threadid]\" tabindex=\"1\" checked=\"checked\" />" . $vbphrase['ignore'] . "</label>
			", '', 'top', 'threadaction');
			$havethreads = true;
		}
	}
	if (!$havethreads)
	{
		print_description_row($vbphrase['no_threads_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}

	print_form_header('moderate', 'doposts', 0, 1, 'posts');
	print_table_header($vbphrase['posts_awaiting_moderation'], 2, 0, 'postlist');


	if (!empty($postids))
	{
		$postids = implode(',', $postids);
		$posts = $DB_site->query("
			SELECT postid, pagetext, post.dateline, post.userid, post.title AS post_title,
			thread.title AS thread_title, thread.forumid AS forumid, username, thread.threadid
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE (1 = 0 $sql) AND postid IN($postids)
			ORDER BY dateline
		");
		$haveposts = false;
		while ($post = $DB_site->fetch_array($posts))
		{
			if ($haveposts)
			{
				print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
			}
			else
			{
				print_description_row('
					<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
					&nbsp;
					<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />
					&nbsp;
					<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
				', 0, 2, 'thead', 'center');
			}
			print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($post['userid'], "<a href=\"user.php?$session[sessionurl]do=viewuser&userid=$post[userid]\" target=\"_blank\">$post[username]</a>", $vbphrase['guest']));
			print_label_row('<b>' . $vbphrase['thread'] . '</b>', "<a href=\"../showthread.php?$session[sessionurl]threadid=$post[threadid]\" target=\"_blank\">$post[thread_title]</a>");
			print_label_row('<b>' . $vbphrase['forum'] . '</b> ', "<a href=\"../forumdisplay.php?$session[sessionurl]forumid=$post[forumid]\" target=\"_blank\">" . $forumcache["$post[forumid]"]['title'] . "</a>");
			print_input_row($vbphrase['title'], "posttitle[$post[postid]]", $post['post_title'], 0);
			print_textarea_row($vbphrase['message'], "postpagetext[$post[postid]]", $post['pagetext'], 4, 40);
			print_label_row($vbphrase['action'], "
				<label for=\"val_$post[postid]\"><input type=\"radio\" name=\"postaction[$post[postid]]\" value=\"1\" id=\"val_$post[postid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
				<label for=\"del_$post[postid]\"><input type=\"radio\" name=\"postaction[$post[postid]]\" value=\"-1\" id=\"del_$post[postid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>
				<label for=\"ign_$post[postid]\"><input type=\"radio\" name=\"postaction[$post[postid]]\" value=\"0\" id=\"ign_$post[postid]\" tabindex=\"1\"  checked=\"checked\" />" . $vbphrase['ignore'] . "</label>
			", '', 'top', 'postaction');
			$haveposts = true;
		}
	}
	if (!$haveposts)
	{
		print_description_row($vbphrase['no_posts_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}

}

// ###################### Start do thread/post moderation #######################
if ($_POST['do'] == 'doposts')
{
	$updateforum = array();
	$updatethread = array();
	$notified = array();
	$threadids = array();
	$postids = array();

	globalize($_POST, array('threadaction', 'threadtitle', 'threadnotes', 'threadpagetext', 'postpagetext', 'postaction', 'posttitle'));

	vbmail_start();

	if (is_array($threadaction))
	{
		foreach ($threadaction AS $threadid => $action)
		{
			$threadid = intval($threadid);
			// check whether moderator of this forum
			$getforumid = $DB_site->query_first("SELECT forumid FROM " . TABLE_PREFIX . "thread WHERE threadid = $threadid");
			if (!can_moderate($getforumid['forumid'], 'canmoderateposts'))
			{
				continue;
			}

			if ($action == 1)
			{ // validate
				// do queries
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "thread SET
					visible = 1,
					title = '" . addslashes(htmlspecialchars_uni($threadtitle["$threadid"])) . "',
					notes = '" . addslashes(htmlspecialchars_uni($threadnotes["$threadid"])) . "'
					WHERE threadid = $threadid
				");
				$post = $DB_site->query_first("
					SELECT postid
					FROM " . TABLE_PREFIX . "post
					WHERE threadid = $threadid
					ORDER BY dateline
				");
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "post SET
					title = '" . addslashes(htmlspecialchars_uni($threadtitle["$threadid"])) . "',
					pagetext = '" . addslashes($threadpagetext["$threadid"]) . "'
					WHERE postid = $post[postid]
				");
				$DB_site->query("
					DELETE FROM " . TABLE_PREFIX . "post_parsed
					WHERE postid = $post[postid]
				");
				$threadids[] = $threadid;
				$npostids[] = $post['postid'];
				$updateforum["$getforumid[forumid]"] = 1;
			}
			else if ($action == -1)
			{ // delete
				delete_thread($threadid, 1, can_moderate($getforumid['forumid'], 'canremoveposts'));
				$updateforum["$getforumid[forumid]"] = 1;
			}
		}
		if (!empty($npostids))
		{
			$npostids = implode(',', $npostids);
			$DB_site->query("UPDATE " . TABLE_PREFIX . "post SET visible = 1 WHERE postid IN($npostids)");
		}
		if (!empty($threadids))
		{
			$threadids = implode(',', $threadids);
			$DB_site->query("
				DELETE FROM " . TABLE_PREFIX . "moderation
				WHERE threadid IN($threadids) AND type = 'thread'
			");
		}
	}

	if (is_array($postaction))
	{
		require_once('./includes/functions_newpost.php');
		foreach ($postaction AS $postid => $action)
		{
			$postid = intval($postid);

			$thread = $DB_site->query_first("
				SELECT threadid,userid
				FROM " . TABLE_PREFIX . "post
				WHERE postid = $postid
			");
			if (empty($thread))
			{
				continue;
			}
			$getforumid = $DB_site->query_first("
				SELECT forumid
				FROM " . TABLE_PREFIX . "thread
				WHERE threadid = $thread[threadid]
			");
			if (!can_moderate($getforumid['forumid'], 'canmoderateposts'))
			{
				continue;
			}

			if ($action == 1)
			{ // validate
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "post SET
					pagetext = '" . addslashes($postpagetext["$postid"]) . "',
					visible = 1
					WHERE postid = $postid
				");
				$DB_site->query("
					DELETE FROM " . TABLE_PREFIX . "post_parsed
					WHERE postid = $postid
				");

				// send notification
				if (!$notified["$thread[threadid]"])
				{
					$message = $postpagetext["$postid"];
					exec_send_notification($thread['threadid'], $thread['userid'], $postid);
					$notified["$thread[threadid]"] = true;
				}

				$postids[] = $postid;
				$updatethread[$thread['threadid']] = 1;
				$updateforum[$getforumid['forumid']] = 1;
			}
			else if ($action == -1)
			{ // delete
				$postids[] = $postid;
				delete_post($postid, 1, $thread['threadid'], can_moderate($getforumid['forumid'], 'canremoveposts'));
				$updatethread[$thread['threadid']] = 1;
				$updateforum[$getforumid['forumid']] = 1;
			}
		}
		if (!empty($postids))
		{
			$postids = implode(',', $postids);
			$DB_site->query("
				DELETE FROM " . TABLE_PREFIX . "moderation
				WHERE postid IN($postids) AND type = 'reply'
			");
		}
	}

	vbmail_end();

	// update counters
	if (!empty($updatethread))
	{
		foreach ($updatethread AS $threadid => $null)
		{
			build_thread_counters($threadid);
		}
	}
	if (!empty($updateforum))
	{
		foreach ($updateforum AS $forumid => $null)
		{
			build_forum_counters($forumid);
		}
	}

	define('CP_REDIRECT', 'moderate.php?do=posts');
	print_stop_message('moderated_posts_successfully');

}


// ###################### Start attachment moderation #######################
if ($_REQUEST['do'] == 'attachments')
{
	$sql = fetch_moderator_forum_list_sql('canmoderateattachments');

	print_form_header('moderate', 'doattachments');
	print_table_header($vbphrase['attachments_awaiting_moderation']);

	$attachments = $DB_site->query("
		SELECT user.username, post.username AS postusername, attachment.filename, attachment.postid, thread.forumid, thread.threadid,
			attachment.attachmentid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize, attachment.filesize
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (attachment.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (attachment.userid = user.userid)
		WHERE attachment.visible = 0 AND attachment.postid <> 0
			AND (1 = 0 $sql)
	");
	$done = false;
	while ($attachment = $DB_site->fetch_array($attachments))
	{
		if ($done)
		{
			print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
		}
		else
		{
			print_description_row('
				<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '"
				/>&nbsp;<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '"
				/>&nbsp;<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
			', 0, 2, 'thead', 'center');
		}
		print_label_row($vbphrase['attachment'], '<b> ' . "<a href=\"../attachment.php?$session[sessionurl]attachmentid=$attachment[attachmentid]\" target=\"_blank\">" . htmlspecialchars_uni($attachment['filename']) . '</a></b>' . ' (' . vb_number_format($attachment['filesize'], 1, true) . ')');

		$extension = strtolower(file_extension($attachment['filename']));
		if ($extension == 'gif' OR $extension == 'jpg' OR $extension == 'jpe' OR $extension == 'jpeg' OR $extension == 'png' OR $extension == 'bmp')
		{
			if ($attachment['hasthumbnail'])
			{
				print_label_row($vbphrase['thumbnail'], "<a href=\"../attachment.php?$session[sessionurl]attachmentid=$attachment[attachmentid]&amp;stc=1\" target=\"_blank\"><img src=\"../attachment.php?$session[sessionurl]attachmentid=$attachment[attachmentid]&amp;thumb=1\" border=\"0\" style=\"border: outset 1px #AAAAAA\" alt=\"\" /></a>");
			}
			else
			{
				print_label_row($vbphrase['image'], "<img src=\"../attachment.php?$session[sessionurl]attachmentid=$attachment[attachmentid]\" border=\"0\" />");
			}
		}
		print_label_row($vbphrase['posted_by'], iif($attachment['username'], $attachment['username'], $attachment['postusername']). ' ' . construct_link_code($vbphrase['view_post'], "../showthread.php?$session[sessionurl]postid=$attachment[postid]", 1));
		print_label_row($vbphrase['action'], "
			<label for=\"val_$attachment[attachmentid]\"><input type=\"radio\" name=\"attachaction[$attachment[attachmentid]]\" value=\"1\" id=\"val_$attachment[attachmentid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
			<label for=\"del_$attachment[attachmentid]\"><input type=\"radio\" name=\"attachaction[$attachment[attachmentid]]\" value=\"-1\" id=\"del_$attachment[attachmentid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>
			<label for=\"ign_$attachment[attachmentid]\"><input type=\"radio\" name=\"attachaction[$attachment[attachmentid]]\" value=\"0\" id=\"ign_$attachment[attachmentid]\" tabindex=\"1\" checked=\"checked\" />" . $vbphrase['ignore'] . "</label>
		", '', 'top', 'attachaction');
		$done = true;
	}

	if (!$done)
	{
		print_description_row($vbphrase['no_attachments_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}
}


// ###################### Start do attachment moderation #######################
if ($_POST['do'] == 'doattachments')
{
	globalize($_POST, array('attachaction'));

	$ids = '';
	$deleteids = array();
	$postids = array();
	$threadids = array();
	foreach ($attachaction AS $attachmentid => $action)
	{
		if ($action == 0)
		{ // no point in checking the permission if they dont want to do anything to the attachment
			continue;
		}

		$attachmentid = intval($attachmentid);

		$getforumid = $DB_site->query_first("
			SELECT attachment.attachmentid, post.threadid, thread.forumid, attachment.userid, post.postid
			FROM  " . TABLE_PREFIX . "attachment AS attachment,
			" . TABLE_PREFIX . "post AS post,
			" . TABLE_PREFIX . "thread AS thread
			WHERE attachment.attachmentid = $attachmentid AND
			post.postid = attachment.postid AND
			post.threadid = thread.threadid
		");
		if (!can_moderate($getforumid['forumid'], 'canmoderateattachments'))
		{
			continue;
		}

		if ($action == 1)
		{ // validate
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "attachment SET
				visible = 1
				WHERE attachmentid = $attachmentid
			");
		}
		else if ($action == -1)
		{ // delete
			$ids .= ',' . $attachmentid;
			$deleteids["$attachmentid"] = $getforumid['userid'];
			$postids["$getforumid[postid]"]++;
			$threadids[] = $getforumid[threadid];
		}
	}

	foreach($postids AS $postid => $decrement)
	{
		$attachcasesql .= " WHEN postid = $postid THEN attach - $decrement";
		$allpostids .= ",$postid";
	}

	if ($attachcasesql)
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "post
			SET attach =
			CASE
				$attachcasesql
				ELSE attach
			END
			WHERE postid IN (0$allpostids)
		");
	}

	foreach($threadids AS $threadid)
	{
		build_thread_counters($threadid);
	}

	require_once('./includes/functions_file.php');
	delete_attachment_files($deleteids);
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "attachment WHERE attachmentid IN(0$ids)");

	define('CP_REDIRECT', 'moderate.php?do=attachments');
	print_stop_message('moderated_attachments_successfully');
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11394 $
|| ####################################################################
\*======================================================================*/
?>