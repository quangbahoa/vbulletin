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
@set_time_limit(0);
ignore_user_abort(1);
$nozip = 1;

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 11464 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('maintenance');
if ($_POST['do'] == 'rebuildstyles' OR $HTTP_POST_VARS['do'] == 'rebuildstyles')
{
	$phrasegroups[] = 'style';
}
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_databuild.php');
$nozip = 1;

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminmaintain'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['maintenance']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'chooser';
}

globalize($_REQUEST, array('perpage' => INT, 'startat' => INT));
$finishat = $startat + $perpage;

// ###################### Rebuild all style info #######################
if ($_POST['do'] == 'rebuildstyles')
{
	require_once('./includes/adminfunctions_template.php');

	globalize($_POST, array(
		'renumber' => INT,
		'install' => INT
	));

	build_all_styles($renumber, $install, "misc.php?$session[sessionurl]do=chooser#style");

	print_stop_message('updated_styles_successfully');
}

// ###################### Start emptying the index #######################
if ($_REQUEST['do'] == 'emptyindex')
{
	print_form_header('misc', 'doemptyindex');
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_empty_index']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Start emptying the index #######################
if ($_POST['do'] == 'doemptyindex')
{
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "postindex");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "word");

	define('CP_REDIRECT', 'misc.php');
	print_stop_message('emptied_search_index_successfully');
}

// ###################### Start build search index #######################
if ($_REQUEST['do'] == 'buildpostindex')
{
	globalize($_REQUEST, array(
		'totalthreads' => INT,
		'doprocess' => INT,
		'autoredirect' => INT,
		'totalposts' => INT,
	));

	$starttime = microtime();

	if (empty($perpage))
	{
		$perpage = 250;
	}

	echo '<p>' . $vbphrase['building_search_index'] . ' ';
	flush();

	$foruminfo = array('indexposts' => 1);
	$firstpost = array();

	$posts = $DB_site->query("
		SELECT postid, post.title, post.pagetext, post.threadid, thread.title AS threadtitle
		FROM " . TABLE_PREFIX . "post AS post
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		WHERE (forum.options & $_FORUMOPTIONS[indexposts])
			AND post.postid >= $startat
			AND post.postid < $finishat
		ORDER BY post.postid
	");

	echo $vbphrase['posts_queried'] . '</p><p>';
	flush();

	while ($post = $DB_site->fetch_array($posts) AND (!$doprocess OR $totalposts < $doprocess))
	{
		$totalposts++;

		echo construct_phrase($vbphrase['processing_x'], $post['postid']) . ' ... ';
		flush();

		if (empty($firstpost["$post[threadid]"]))
		{
			echo '<i>' . $vbphrase['querying_first_post_of_thread'] . '</i> ';
			flush();
			$getfirstpost = $DB_site->query_first("
				SELECT MIN(postid) AS postid
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = $post[threadid]
			");
			$firstpost["$post[threadid]"] = $getfirstpost['postid'];
		}

		build_post_index($post['postid'], $foruminfo, iif($post['postid'] == $firstpost["$post[threadid]"], 1, 0), $post);

		echo $vbphrase['done'] . "<br />\n";
		flush();
	}

	require_once('./includes/functions_misc.php');
	$pagetime = vb_number_format(fetch_microtime_difference($starttime), 2);
	echo '</p><p><b>' . construct_phrase($vbphrase['processing_time_x'], $pagetime) . "</b></p>";
	flush();

	if (($totalposts < $doprocess OR !$doprocess) AND $checkmore = $DB_site->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE postid >= $finishat LIMIT 1"))
	{
		if ($autoredirect == 1)
		{
			print_cp_redirect("misc.php?$session[sessionurl]do=buildpostindex&startat=$finishat&perpage=$perpage&autoredirect=$autoredirect&totalthreads=$totalthreads&doprocess=$doprocess&totalposts=$totalposts");
		}
		echo "<p><a href=\"misc.php?$session[sessionurl]do=buildpostindex&amp;startat=$finishat&amp;perpage=$perpage&amp;autoredirect=$autoredirect&amp;totalthreads=$totalthreads&amp;doprocess=$doprocess&amp;totalposts=$totalposts\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('rebuilt_search_index_successfully');
	}
}

// ###################### Start update post counts ################
if ($_REQUEST['do'] == 'updateposts')
{
	if (empty($perpage))
	{
		$perpage = 1000;
	}

	echo '<p>' . $vbphrase['updating_post_counts'] . '</p>';

	$forums = $DB_site->query("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum AS forum
		WHERE (forum.options & $_FORUMOPTIONS[countposts])
	");
	$gotforums = '';
	while ($forum = $DB_site->fetch_array($forums))
	{
		$gotforums .= ',' . $forum['forumid'];
	}

	$users = $DB_site->query("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $startat AND userid < $finishat ORDER BY userid DESC");
	while ($user = $DB_site->fetch_array($users))
	{
		$totalposts = $DB_site->query_first("
			SELECT COUNT(*) AS posts FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog_t ON (deletionlog_t.primaryid = thread.threadid AND deletionlog_t.type = 'thread')
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog_p ON (deletionlog_p.primaryid = post.postid AND deletionlog_p.type = 'post')
			WHERE post.userid = $user[userid] AND
				thread.forumid IN (0$gotforums) AND
				deletionlog_t.primaryid IS NULL AND
				deletionlog_p.primaryid IS NULL
		");
		$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET posts=$totalposts[posts] WHERE userid = $user[userid]");

		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		flush();
	}


	if ($checkmore = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=updateposts&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=updateposts&amp;startat=$finishat&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('updated_post_counts_successfully');
	}
}

// ###################### Start update user #######################
if ($_REQUEST['do'] == 'updateuser')
{
	if (empty($perpage))
	{
		$perpage = 1000;
	}

	echo '<p>' . $vbphrase['updating_user_info'] . '</p>';
	$usergroupcachealt = array();

	$users = $DB_site->query("SELECT userid, usertitle, usergroupid, displaygroupid, customtitle, posts FROM " . TABLE_PREFIX . "user WHERE userid >= $startat AND userid < $finishat ORDER BY userid DESC");
	while($user = $DB_site->fetch_array($users))
	{
		// update user stuff
		if ($user['customtitle'] == 0)
		{
			if ($user['displaygroupid'])
			{
				$getusergroupid = $user['displaygroupid'];
			}
			else
			{
				$getusergroupid = $user['usergroupid'];
			}
			if (is_array($usergroupcachealt[$getusergroupid]))
			{
				$usergroup = $usergroupcachealt[$getusergroupid];
			}
			else if (is_array($usergroupcache[$getusergroupid]))
			{
				$usergroup = $usergroupcache[$getusergroupid];
			}
			else
			{
				$usergroup = $DB_site->query_first("
					SELECT usertitle
					FROM " . TABLE_PREFIX . "usergroup
					WHERE usergroupid = $getusergroupid
				");
				$usergroupcachealt[$getusergroupid] = $usergroup;
			}
			if (empty($usergroup['usertitle']))
			{
				$gettitle = $DB_site->query_first("
					SELECT title
					FROM " . TABLE_PREFIX . "usertitle
					WHERE minposts <= $user[posts]
					ORDER BY minposts DESC
				");
				$usertitle = $gettitle['title'];
			}
			else
			{
				$usertitle = $usergroup['usertitle'];
			}

			$sql = 'usertitle = \'' . addslashes($usertitle) . '\',';
		}
		else
		{
			$sql = '';
		}

		if ($lastpost = $DB_site->query_first("SELECT MAX(dateline) AS dateline FROM " . TABLE_PREFIX . "post WHERE userid = $user[userid]"))
		{
			$lastpost['dateline'] = intval($lastpost['dateline']);
		}
		else
		{
			$lastpost['dateline'] = 0;
		}

		$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET $sql"."lastpost = $lastpost[dateline] WHERE userid = $user[userid]");

		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		flush();
	}

	if ($checkmore = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=updateuser&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=updateuser&amp;startat=$finishat&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('updated_user_titles_successfully');
	}
}

// ###################### Start update usernames #######################
if ($_REQUEST['do'] == 'updateusernames')
{
	if (empty($perpage))
	{
		$perpage = 1000;
	}

	echo '<p>' . $vbphrase['updating_usernames'] . '</p>';
	$users = $DB_site->query("
		SELECT userid, username
		FROM " . TABLE_PREFIX . "user
		WHERE userid >= $startat AND
			userid < $finishat
		ORDER BY userid DESC
	");
	while($user = $DB_site->fetch_array($users))
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "deletionlog
			SET username = '" . addslashes($user['username']) . "'
			WHERE userid = $user[userid]
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "editlog
			SET username = '" . addslashes($user['username']) . "'
			WHERE userid = $user[userid]
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "pmreceipt
			SET tousername = '" . addslashes($user['username']) . "'
			WHERE touserid = $user[userid]
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "pmtext
			SET fromusername = '" . addslashes($user['username']) . "'
			WHERE fromuserid = $user[userid]
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "post
			SET username = '" . addslashes($user['username']) . "'
			WHERE userid = $user[userid]
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "thread
			SET postusername = '" . addslashes($user['username']) . "'
			WHERE postuserid = $user[userid]
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "usernote
			SET username = '" . addslashes($user['username']) . "'
			WHERE posterid = $user[userid]
		");
		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		flush();
	}

	if ($checkmore = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=updateusernames&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=updateusernames&amp;startat=$finishat&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('updated_usernames_successfully');
	}
}


// ###################### Start update forum #######################
if ($_REQUEST['do'] == 'updateforum')
{
	if (empty($perpage))
	{
		$perpage = 100;
	}

	echo '<p>' . $vbphrase['updating_forums'] . '</p>';

	$forums = $DB_site->query("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum
		WHERE forumid >= $startat AND
			forumid < $finishat
		ORDER BY forumid DESC
	");
	while($forum = $DB_site->fetch_array($forums))
	{
		build_forum_counters($forum['forumid']);
		echo construct_phrase($vbphrase['processing_x'], $forum['forumid']) . "<br />\n";
		flush();
	}

	if ($checkmore = $DB_site->query_first("SELECT forumid FROM " . TABLE_PREFIX . "forum WHERE forumid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=updateforum&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=updateforum&amp;startat=$finishat&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		// get rid of "ghost" moderators who are not attached to a valid forum
		$deadmods = $DB_site->query("
			SELECT moderatorid
			FROM " . TABLE_PREFIX . "moderator AS moderator
			LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING (forumid)
			WHERE forum.forumid IS NULL
		");

		$mods = '';

		while ($mod = $DB_site->fetch_array($deadmods))
		{
			if (!empty($mods))
			{
				$mods .= ' , ';
			}
			$mods .= $mod['moderatorid'];
		}

		if (!empty($mods))
		{
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderator WHERE moderatorid IN (" . $mods . ")");
		}

		// and finally rebuild the forumcache
		unset($forumarraycache, $forumcache);
		build_forum_permissions();

		define('CP_REDIRECT', 'misc.php');
		print_stop_message('updated_forum_successfully');
	}
}

// ###################### Start update threads #######################
if ($_REQUEST['do'] == 'updatethread')
{
	if (empty($perpage))
	{
		$perpage = 2000;
	}

	echo '<p>' . $vbphrase['updating_threads'] . '</p>';

	$threads = $DB_site->query("
		SELECT threadid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid >= $startat AND
		threadid < $finishat
		ORDER BY threadid
	");
	while ($thread = $DB_site->fetch_array($threads))
	{
		build_thread_counters($thread['threadid']);
		echo construct_phrase($vbphrase['processing_x'], $thread['threadid'])."<br />\n";
		flush();
	}

	if ($checkmore = $DB_site->query_first("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE threadid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=updatethread&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=updatethread&amp;startat=$finishat&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('updated_threads_successfully');
	}
}

// ###################### Start update similar threads #######################
if ($_REQUEST['do'] == 'updatesimilar')
{
	require_once('./includes/functions_search.php');
	if (empty($perpage))
	{
		$perpage = 100;
	}

	echo '<p>' . $vbphrase['updating_similar_threads'] . '</p>';

	$threads = $DB_site->query("
		SELECT title, threadid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid >= $startat AND
			threadid < $finishat
		ORDER BY threadid
	");
	while ($thread = $DB_site->fetch_array($threads))
	{
		$similarthreads = fetch_similar_threads($thread['title'], $thread['threadid']);
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "thread
			SET similar = '" . addslashes($similarthreads) . "'
			WHERE threadid = $thread[threadid]
		");
		echo construct_phrase($vbphrase['processing_x'], $thread['threadid']) . "<br />\n";
		flush();
	}

	if ($checkmore = $DB_site->query_first("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE threadid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=updatesimilar&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=updatesimilar&amp;startat=$finishat&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('updated_similar_threads_successfully');
	}
}

// ################## Start rebuilding user reputation ######################
if ($_POST['do'] == 'rebuildreputation')
{

	$users = $DB_site->query("
		SELECT reputation.userid, SUM(reputation.reputation) AS totalrep
		FROM " . TABLE_PREFIX . "reputation AS reputation
		GROUP BY reputation.userid
	");

	$userrep = array();
	while ($user = $DB_site->fetch_array($users))
	{
		$user['totalrep'] += $_POST['reputation_base'];
		$userrep["$user[totalrep]"] .= ",$user[userid]";
	}

	if (!empty($userrep))
	{
		foreach ($userrep AS $reputation => $ids)
		{
			$usercasesql .= " WHEN userid IN (0$ids) THEN $reputation";
		}
	}

	if ($usercasesql)
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET reputation =
				CASE
					$usercasesql
					ELSE $_POST[reputation_base]
				END
		");
	}
	else // there is no reputation
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET reputation = $_POST[reputation_base]
		");
	}

	require_once('./includes/adminfunctions_reputation.php');
	build_reputationids();

	define('CP_REDIRECT', 'misc.php');
	print_stop_message('rebuilt_user_reputation_successfully');

}

// ################## Start rebuilding attachment thumbnails ################
if ($_REQUEST['do'] == 'rebuildthumbs')
{
	if (!function_exists('imagetypes') OR !$vboptions['gdversion'])
	{
		//define('CP_REDIRECT', 'misc.php');
		print_stop_message('your_version_no_image_support');
	}

	require_once('./includes/functions_image.php');

	if (empty($perpage))
	{
		$perpage = 20;
	}

	if (!$startat)
	{
		$firstattach = $DB_site->query_first("SELECT MIN(attachmentid) AS min FROM " . TABLE_PREFIX . "attachment");
		$startat = intval($firstattach['min']);
		$finishat = $startat + $perpage;
	}

	echo '<p>' . construct_phrase($vbphrase['building_attachment_thumbnails'], "misc.php?$session[sessionurl]do=rebuildthumbs&startat=$startat&perpage=$perpage") . '</p>';

	if ($vboptions['attachfile'])
	{
		require_once('./includes/functions_file.php');
	}

	$attachments = $DB_site->query("
		SELECT attachmentid, filedata, userid, postid, filename
		FROM " . TABLE_PREFIX . "attachment
		WHERE attachmentid >= $startat
			AND	attachmentid < $finishat
			AND	SUBSTRING_INDEX(filename, '.', -1) IN ('gif', 'jpg', 'jpeg', 'jpe', 'png')
		ORDER BY attachmentid
	");
	while ($attachment = $DB_site->fetch_array($attachments))
	{
		if (!$vboptions['attachfile']) // attachments are in the database
		{
			if ($vboptions['safeupload'])
			{
				$filename = $vboptions['tmppath'] . '/' . md5(uniqid(microtime()) . $bbuserinfo['userid']);
			}
			else
			{
				$filename = tempnam(ini_get('upload_tmp_dir'), 'vbthumb');
			}
			$filenum = fopen($filename, 'wb');
			fwrite($filenum, $attachment['filedata']);
			fclose($filenum);
		}
		else
		{
			$attachmentids .= ",$attachment[attachmentid]";
			$filename = fetch_attachment_path($attachment['userid'], $attachment['attachmentid']);
		}

		echo construct_phrase($vbphrase['processing_x'], "$vbphrase[attachment] : " .
			construct_link_code($attachment['attachmentid'], "../attachment.php?$session[sessionurl]attachmentid=$attachment[attachmentid]", 1) . " ($vbphrase[post] : " .
			construct_link_code($attachment['postid'], "../showthread.php?$session[sessionurl]postid=$attachment[postid]", 1) . " )") . ' ';

		$filesize = @filesize($filename);
		if (!$filesize)
		{
			echo '<b>' . $vbphrase['error_attachment_missing'] . '</b><br />';
			continue;
		}

		$fileinfo = array(
			'name' => $attachment['filename'],
			'tmp_name' => $filename
		);

		$thumbnail = fetch_thumbnail_from_image($fileinfo);

		if (!$vboptions['attachfile'])
		{
			// Remove temporary file we used to generate thumbnail
			@unlink($filename);
		}
		else
		{
			$filename = fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], true);
			@unlink($filename);
			if (!empty($thumbnail['filedata']))
			{
				$fp = fopen($filename, 'wb');
				fwrite($fp, $thumbnail['filedata']);
				fclose($fp);
			}
		}

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "attachment
			SET thumbnail = '" . iif(!$vboptions['attachfile'], $thumbnail['filedata']) . "',
				thumbnail_dateline = $thumbnail[dateline],
				thumbnail_filesize = $thumbnail[filesize]
			WHERE attachmentid = $attachment[attachmentid]
		");

		if (!empty($thumbnail['imageerror']))
		{
			echo '<b>' . $vbphrase["error_$thumbnail[imageerror]"] . '</b>';
		}
		else if (empty($thumbnail['filedata']))
		{
			echo '<b>' . $vbphrase['error'] . '</b>';
		}
		echo '<br />';
		flush();
		unset($thumbnail);
	}

	if ($checkmore = $DB_site->query_first("SELECT attachmentid FROM " . TABLE_PREFIX . "attachment WHERE attachmentid >= $finishat AND SUBSTRING_INDEX(filename, '.', -1) IN ('gif', 'jpg', 'jpeg', 'jpe', 'png') LIMIT 1"))
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=rebuildthumbs&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=rebuildthumbs&amp;startat=$finishat&amp;perpage=$perpage\">".'Continue'."</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('rebuilt_attachment_thumbnails_successfully');
	}
}

// ###################### Start rebuilding post cache #######################
if ($_REQUEST['do'] == 'buildpostcache')
{

	$style = $DB_site->query_first("
		SELECT templatelist FROM " . TABLE_PREFIX . "style
		WHERE styleid = $vboptions[styleid]
		LIMIT 1
	");

	# 3.0.x has no support for caching these templates in multiple styles in post_parsed
	cache_templates(array('bbcode_quote', 'bbcode_php', 'bbcode_code', 'bbcode_html'), $style['templatelist']);

	require_once('./includes/functions_bbcodeparse.php');
	if (empty($perpage))
	{
		$perpage = 1000;
	}

	echo '<p>' . $vbphrase['building_post_cache'] . '</p>';
	$saveparsed = '';

	$posts = $DB_site->query("
		SELECT postid, forumid, pagetext, allowsmilie, thread.lastpost
		FROM " . TABLE_PREFIX . "post AS post, " . TABLE_PREFIX . "thread AS thread
		WHERE post.threadid = thread.threadid AND
			postid >= $startat AND
			postid < $finishat AND
			thread.lastpost >= " . (TIMENOW - ($vboptions['cachemaxage'] * 60 * 60 * 24)) . "
		ORDER BY postid
	");
	while ($post = $DB_site->fetch_array($posts))
	{
		$parsedtext = parse_bbcode($post['pagetext'], $post['forumid'], $post['allowsmilie']);
		$saveparsed .= ", ($post[postid]," . intval($post['lastpost']) . "," . intval($parsed_postcache['images']) . ",'" . addslashes($parsed_postcache['text']) . "')";

		echo construct_phrase($vbphrase['processing_x'], $post['postid'])."<br />\n";
		flush();
	}
	if ($saveparsed)
	{
		$saveparsed = substr($saveparsed, 1);
		$DB_site->query("
			REPLACE INTO " . TABLE_PREFIX . "post_parsed
			(postid,dateline,hasimages,pagetext_html)
			VALUES
			$saveparsed
		");
	}

	if ($checkmore = $DB_site->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE postid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=buildpostcache&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=buildpostcache&amp;startat=$finishat&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('updated_post_cache_successfully');
	}
}

// ###################### Start remove dupe #######################
if ($_REQUEST['do'] == 'removedupe')
{
	if (empty($perpage))
	{
		$perpage = 500;
	}

	echo '<p>' . $vbphrase['removing_duplicate_threads'] . '</p>';

	$threads = $DB_site->query("
		SELECT threadid, title, forumid, postusername, dateline
		FROM " . TABLE_PREFIX . "thread WHERE threadid >= $startat AND
			threadid < $finishat
		ORDER BY threadid
	");
	while ($thread = $DB_site->fetch_array($threads))
	{
		$deletethreads = $DB_site->query("
			SELECT threadid
			FROM " . TABLE_PREFIX . "thread
			WHERE title = '" . addslashes($thread['title']) . "' AND
				forumid = $thread[forumid] AND
				postusername = '" . addslashes($thread['postusername']) . "' AND
				dateline = $thread[dateline] AND
				threadid > $thread[threadid]
		");
		while ($deletethread = $DB_site->fetch_array($deletethreads))
		{
			delete_thread($deletethread['threadid']);
			echo "&nbsp;&nbsp;&nbsp; ".construct_phrase($vbphrase['delete_x'], $deletethread['threadid'])."<br />";
		}
		echo construct_phrase($vbphrase['processing_x'], $thread['threadid'])."<br />\n";
		flush();
	}
	if ($checkmore = $DB_site->query_first("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE threadid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=removedupe&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=removedupe&amp;startat=$finishat&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('deleted_duplicate_threads_successfully');
	}

}

// ###################### Start update forum counters vB3-style #######################
if ($_REQUEST['do'] == 'updateforum2')
{

	if (empty($perpage))
	{
		$perpage = 50;
	}

	$forums = $DB_site->query("
		SELECT forum.title, forum.forumid, COUNT(thread.threadid) AS threads
		FROM " . TABLE_PREFIX . "thread AS thread
		INNER JOIN " . TABLE_PREFIX . "forum AS forum USING(forumid)
		WHERE thread.visible = 1 AND
			thread.open <> 10
		GROUP BY forumid
		LIMIT $startat, $perpage
	");
	$numforums = $DB_site->num_rows($forums);

	echo "<ul>\n";
	while ($forum = $DB_site->fetch_array($forums))
	{
		$forum['threads'] = intval($forum['threads']);
		$posts = $DB_site->query_first("
			SELECT COUNT(*) AS posts, MAX(postid) AS lastpost
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread USING(threadid)
			WHERE thread.forumid = $forum[forumid] AND
			post.visible = 1
		");
		$posts['lastpost'] = intval($posts['lastpost']);
		$posts['posts'] = intval($posts['posts']);
		if ($posts['lastpost'])
		{
			$lastthread = $DB_site->query_first("
				SELECT thread.threadid, thread.title, thread.iconid, lastpost, lastposter, pollid
				FROM " . TABLE_PREFIX . "thread AS thread
				INNER JOIN " . TABLE_PREFIX . "post AS post USING(threadid)
				WHERE postid = $posts[lastpost]
				LIMIT 0, 1
			");
		}
		else
		{
			// Should never get into here unless something is screwy, i.e. have a single thread without posts
			$lastthread = array(
				'lastpost' => 0,
				'lastposter' => '',
				'title' => '',
				'iconid' => 0,
				'threadid' => 0
			);
			$forum['threads'] = 0;
		}

		echo "<li><b>$forum[title]</b>";
		flush();

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "forum SET
			replycount = $posts[posts],
			threadcount = $forum[threads],
			lastpost = $lastthread[lastpost],
			lastposter = '" . addslashes($lastthread['lastposter']) . "',
			lastthread = '" . addslashes($lastthread['title']) . "',
			lastthreadid = $lastthread[threadid],
			lasticonid = " . intval(iif($lastthread['pollid'], -1, $lastthread['iconid'])) . "
			WHERE forumid = $forum[forumid]
		");

		echo "
		<ul class=\"smallfont\">
		<li>" . $vbphrase['threads'] . ' ' .  vb_number_format($forum['threads']) . "</li>
		<li>" . $vbphrase['posts'] . ' ' . vb_number_format($posts['posts']) . "</li>
		<li>" . $vbphrase['last_post'] . vbdate(" $vboptions[dateformat] $vboptions[timeformat]", $lastthread['lastpost'])." by <i>$lastthread[lastposter]</i></li>
		<li>" . $vbphrase['last_thread'] . " <i>$lastthread[title]</i></li>
		</ul>\n</li>\n";
		flush();
	}
	echo "</ul>\n";

	if ($numforums < $perpage)
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('updated_forums_successfully');
	}
	else
	{
		$startat += $perpage;
		print_cp_redirect("misc.php?$session[sessionurl]do=updateforum2&amp;startat=$startat&amp;perpage=$perpage");
	}

}

// ###################### Start find lost users #######################
if ($_POST['do'] == 'lostusers')
{

	$users = $DB_site->query("
		SELECT user.userid
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield USING(userid)
		WHERE userfield.userid IS NULL
	");

	$userids = array();
	while ($user = $DB_site->fetch_array($users))
	{
		$userids[] = $user['userid'];
	}

	if (!empty($userids))
	{
		$DB_site->query("INSERT INTO " . TABLE_PREFIX . "userfield (userid) VALUES (" . implode('),(', $userids) . ")");
	}

	$users = $DB_site->query("
		SELECT user.userid
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING(userid)
		WHERE usertextfield.userid IS NULL
	");

	$userids = array();
	while ($user = $DB_site->fetch_array($users))
	{
		$userids[] = $user['userid'];
	}

	if (!empty($userids))
	{
		$DB_site->query("INSERT INTO " . TABLE_PREFIX . "usertextfield (userid) VALUES (" . implode('),(', $userids) . ")");
	}

	define('CP_REDIRECT', 'misc.php');
	print_stop_message('user_records_repaired');
}

// ###################### Start build statistics #######################
if ($_REQUEST['do'] == 'buildstats')
{
	globalize($_REQUEST, array('startat' => INT));
	$timestamp = $startat;
	$perpage = 10 * 86400;

	if (empty($timestamp))
	{
		// this is the first page of a stat rebuild
		// so let's clear out the old stats
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "stats");

		// and select a suitable start time
		$timestamp = $DB_site->query_first("SELECT MIN(joindate) AS start FROM " . TABLE_PREFIX . "user WHERE joindate > 0");
		if ($timestamp['start'] == 0 OR $timestamp['start'] < 915166800)
		{ // no value found or its before 1999 lets just make it the year 2000
			$timestamp['start'] = 946684800;
		}
		$month = date('n', $timestamp['start']);
		$day = date('j', $timestamp['start']);
		$year = date('Y', $timestamp['start']);

		$timestamp = mktime(0, 0, 0, $month, $day, $year);
	}

	if ($timestamp + $perpage >= TIMENOW)
	{
		$endstamp = TIMENOW;
	}
	else
	{
		$endstamp = $timestamp + $perpage;
	}

	while ($timestamp <= $endstamp)
	{
		// new users
		$newusers = $DB_site->query_first('SELECT COUNT(userid) AS total FROM ' . TABLE_PREFIX . 'user WHERE joindate >= ' . $timestamp . ' AND joindate < ' . ($timestamp + 86400));

		// new threads
		$newthreads = $DB_site->query_first('SELECT COUNT(threadid) AS total FROM ' . TABLE_PREFIX . 'thread WHERE dateline >= ' . $timestamp . ' AND dateline < ' . ($timestamp + 86400));

		// new posts
		$newposts = $DB_site->query_first('SELECT COUNT(threadid) AS total FROM ' . TABLE_PREFIX . 'post WHERE dateline >= ' . $timestamp . ' AND dateline < ' . ($timestamp + 86400));

		// active users
		$activeusers = $DB_site->query_first('SELECT COUNT(userid) AS total FROM ' . TABLE_PREFIX . 'user WHERE lastactivity >= ' . $timestamp . ' AND lastactivity < ' . ($timestamp + 86400));

		$inserts[] = "($timestamp, $newusers[total], $newthreads[total], $newposts[total], $activeusers[total])";

		echo $vbphrase['done'] . " $timestamp <br />\n";
		flush();

		$timestamp += 3600 * 24;

	}

	if (!empty($inserts))
	{
		$DB_site->query("
			REPLACE INTO " . TABLE_PREFIX . "stats
				(dateline, nuser, nthread, npost, ausers)
			VALUES
				" . implode(',', $inserts) . "
		");

		print_cp_redirect("misc.php?$session[sessionurl]do=buildstats&startat=$timestamp");

	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('rebuilt_statistics_successfully');
	}
}

// ###################### Start remove dupe threads #######################
if ($_REQUEST['do'] == 'removeorphanthreads')
{
	if (empty($perpage))
	{
		$perpage = 50;
	}

	$result = fetch_adminutil_text('orphanthread');

	if ($result == 'done')
	{
		build_adminutil_text('orphanthread');
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('deleted_orphan_threads_successfully');
	}
	else if ($result != '')
	{
		$threadarray = unserialize($result);
	}
	else
	{
		$threadarray = array();
		// Fetch IDS
		$threads = $DB_site->query("
			SELECT thread.threadid
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING(forumid)
			WHERE forum.forumid IS NULL
		");
		while ($thread = $DB_site->fetch_array($threads))
		{
			$threadarray[] = $thread['threadid'];
			$count++;
		}
	}

	echo '<p>' . $vbphrase['removing_orphan_threads'] . '</p>';

	while ($threadid = array_pop($threadarray) AND $count < $perpage)
	{
		delete_thread($threadid);
		echo construct_phrase($vbphrase['processing_x'], $threadid)."<br />\n";
		flush();
		$count++;
	}

	if (empty($threadarray))
	{
		build_adminutil_text('orphanthread', 'done');
	}
	else
	{
		build_adminutil_text('orphanthread', serialize($threadarray));
	}

	print_cp_redirect("misc.php?$session[sessionurl]do=removeorphanthreads&perpage=$perpage");
	echo "<p><a href=\"misc.php?$session[sessionurl]do=removeorphanthreads&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";

}

// ###################### Start remove posts #######################
if ($_REQUEST['do'] == 'removeorphanposts')
{
	if (empty($perpage))
	{
		$perpage = 50;
	}

	$posts = $DB_site->query("
		SELECT post.postid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING(threadid)
		WHERE thread.threadid IS NULL
		LIMIT $startat, $perpage
	");
	while ($post = $DB_site->fetch_array($posts))
	{
		delete_post($post['postid']);
		echo construct_phrase($vbphrase['processing_x'], $post['postid'])."<br />\n";
		flush();
		$gotsome = true;
	}

	if($gotsome)
	{
		print_cp_redirect("misc.php?$session[sessionurl]do=removeorphanposts&perpage=$perpage&startat=$finishat");
		echo "<p><a href=\"misc.php?$session[sessionurl]do=removeorphanposts&amp;perpage=$perpage&amp;startat=$finishat\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('deleted_orphan_posts_successfully');
	}
}

// ###################### Start user choices #######################
if ($_REQUEST['do'] == 'chooser')
{
	print_form_header('misc', 'updateuser');
	print_table_header($vbphrase['update_user_titles'], 2, 0);
	print_input_row($vbphrase['number_of_users_to_process_per_cycle'], 'perpage', 1000);
	print_submit_row($vbphrase['update_user_titles']);

	print_form_header('misc', 'updatethread');
	print_table_header($vbphrase['rebuild_thread_information'], 2, 0);
	print_input_row($vbphrase['number_of_threads_to_process_per_cycle'], 'perpage', 2000);
	print_submit_row($vbphrase['rebuild_thread_information']);

	print_form_header('misc', 'updateforum');
	print_table_header($vbphrase['rebuild_forum_information'], 2, 0);
	print_input_row($vbphrase['number_of_forums_to_process_per_cycle'], 'perpage', 100);
	print_submit_row($vbphrase['rebuild_forum_information']);

	print_form_header('misc', 'lostusers');
	print_table_header($vbphrase['fix_broken_user_profiles']);
	print_description_row($vbphrase['finds_users_without_complete_entries']);
	print_submit_row($vbphrase['fix_broken_user_profiles']);

	print_form_header('misc', 'buildpostindex');
	print_table_header($vbphrase['rebuild_search_index'], 2, 0);
	print_description_row(construct_phrase($vbphrase['note_reindexing_empty_indexes_x'], $session['sessionurl']));
	print_input_row($vbphrase['number_of_posts_to_process_per_cycle'], 'perpage', 250);
	print_input_row($vbphrase['post_id_to_start_at'], 'startat', 0);
	print_input_row($vbphrase['total_number_posts_process'], 'doprocess', 0);
	print_yes_no_row($vbphrase['include_automatic_javascript_redirect'], 'autoredirect', 1);
	print_description_row($vbphrase['note_server_intensive']);
	print_submit_row($vbphrase['rebuild_search_index']);

	if ($vboptions['cachemaxage'] > 0)
	{
		print_form_header('misc', 'buildpostcache');
		print_table_header($vbphrase['rebuild_post_cache']);
		print_input_row($vbphrase['number_of_posts_to_process_per_cycle'], 'perpage', 1000);
		print_submit_row($vbphrase['rebuild_post_cache']);
	}

	print_form_header('misc', 'buildstats');
	print_table_header($vbphrase['rebuild_statistics'], 2, 0);
	print_description_row($vbphrase['rebuild_statistics_warning']);
	print_submit_row($vbphrase['rebuild_statistics']);

	print_form_header('misc', 'updatesimilar');
	print_table_header($vbphrase['rebuild_similar_threads']);
	print_description_row($vbphrase['note_rebuild_similar_thread_list']);
	print_input_row($vbphrase['number_of_threads_to_process_per_cycle'], 'perpage', 100);
	print_submit_row($vbphrase['rebuild_similar_threads']);

	print_form_header('misc', 'removedupe');
	print_table_header($vbphrase['delete_duplicate_threads'], 2, 0);
	print_description_row($vbphrase['note_duplicate_threads_have_same']);
	print_input_row($vbphrase['number_of_threads_to_process_per_cycle'], 'perpage', 500);
	print_submit_row($vbphrase['delete_duplicate_threads']);

	print_form_header('misc', 'rebuildthumbs');
	print_table_header($vbphrase['rebuild_attachment_thumbnails'], 2, 0);
	print_description_row($vbphrase['function_rebuilds_thumbnails']);
	print_input_row($vbphrase['number_of_attachments_to_process_per_cycle'], 'perpage', 25);
	print_submit_row($vbphrase['rebuild_attachment_thumbnails']);

	print_form_header('misc', 'rebuildreputation');
	print_table_header($vbphrase['rebuild_user_reputation'], 2, 0);
	print_description_row($vbphrase['function_rebuilds_reputation']);
	print_input_row($vbphrase['reputation_base'], 'reputation_base', $vboptions['reputationdefault']);
	print_submit_row($vbphrase['rebuild_user_reputation']);

	print_form_header('misc', 'updateusernames');
	print_table_header($vbphrase['update_usernames']);
	print_input_row($vbphrase['number_of_users_to_process_per_cycle'], 'perpage', 1000);
	print_submit_row($vbphrase['update_usernames']);

	print_form_header('misc', 'updateposts');
	print_table_header($vbphrase['update_post_counts'], 2, 0);
	print_description_row($vbphrase['recalculate_users_post_counts_warning']);
	print_input_row($vbphrase['number_of_users_to_process_per_cycle'], 'perpage', 1000);
	print_submit_row($vbphrase['update_post_counts']);

	print_form_header('misc', 'rebuildstyles');
	print_table_header($vbphrase['rebuild_styles'], 2, 0, 'style');
	print_description_row($vbphrase['function_allows_rebuild_all_style_info']);
	print_yes_no_row($vbphrase['check_styles_no_parent'], 'install', 1);
	print_yes_no_row($vbphrase['renumber_all_templates_from_one'], 'renumber', 0);
	print_submit_row($vbphrase['rebuild_styles'], 0);

	print_form_header('misc', 'removeorphanthreads');
	print_table_header($vbphrase['remove_orphan_threads']);
	print_description_row($vbphrase['function_removes_orphan_threads']);
	print_input_row($vbphrase['number_of_threads_to_process_per_cycle'], 'perpage', 50);
	print_submit_row($vbphrase['remove_orphan_threads']);

	print_form_header('misc', 'removeorphanposts');
	print_table_header($vbphrase['remove_orphan_posts']);
	print_description_row($vbphrase['function_removes_orphan_posts']);
	print_input_row($vbphrase['number_of_posts_to_process_per_cycle'], 'perpage', 50);
	print_submit_row($vbphrase['remove_orphan_posts']);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11464 $
|| ####################################################################
\*======================================================================*/
?>