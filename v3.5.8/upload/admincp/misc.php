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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(0);
ignore_user_abort(1);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 13395 $');
define('NOZIP', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('maintenance');
if ($_POST['do'] == 'rebuildstyles')
{
	$phrasegroups[] = 'style';
}
$specialtemplates = array('attachmentcache', 'ranks');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_databuild.php');

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

$vbulletin->input->clean_array_gpc('r', array(
	'perpage' => TYPE_UINT,
	'startat' => TYPE_UINT
));

// ###################### Rebuild all style info #######################
if ($_POST['do'] == 'rebuildstyles')
{
	require_once(DIR . '/includes/adminfunctions_template.php');

	$vbulletin->input->clean_array_gpc('p', array(
		'renumber' => TYPE_BOOL,
		'install'  => TYPE_BOOL
	));

	build_all_styles($vbulletin->GPC['renumber'], $vbulletin->GPC['install'], 'misc.php?' . $vbulletin->session->vars['sessionurl'] . 'do=chooser#style');

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
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "postindex");
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "word");

	define('CP_REDIRECT', 'misc.php');
	print_stop_message('emptied_search_index_successfully');
}

// ###################### Start build search index #######################
if ($_REQUEST['do'] == 'buildpostindex')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'doprocess'    => TYPE_UINT,
		'autoredirect' => TYPE_BOOL,
		'totalposts'   => TYPE_UINT,
	));

	$starttime = microtime();

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 250;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['building_search_index'] . ' ';
	vbflush();

	$foruminfo = array('indexposts' => 1);
	$firstpost = array();

	$posts = $db->query_read("
		SELECT postid, post.title, post.pagetext, post.threadid, thread.title AS threadtitle
		FROM " . TABLE_PREFIX . "post AS post
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		WHERE (forum.options & " . $vbulletin->bf_misc_forumoptions['indexposts'] . ")
			AND post.postid >= " . $vbulletin->GPC['startat'] . "
			AND post.postid < $finishat
		ORDER BY post.postid
	");

	echo $vbphrase['posts_queried'] . '</p><p>';
	vbflush();

	while ($post = $db->fetch_array($posts) AND (!$vbulletin->GPC['doprocess'] OR $vbulletin->GPC['totalposts'] < $vbulletin->GPC['doprocess']))
	{
		$vbulletin->GPC['totalposts']++;

		echo construct_phrase($vbphrase['processing_x'], $post['postid']) . ' ... ';
		vbflush();

		if (empty($firstpost["$post[threadid]"]))
		{
			echo '<i>' . $vbphrase['querying_first_post_of_thread'] . '</i> ';
			vbflush();
			$getfirstpost = $db->query_first("
				SELECT MIN(postid) AS postid
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = $post[threadid]
			");
			$firstpost["$post[threadid]"] = $getfirstpost['postid'];
		}

		build_post_index($post['postid'], $foruminfo, iif($post['postid'] == $firstpost["$post[threadid]"], 1, 0), $post);

		echo $vbphrase['done'] . "<br />\n";
		vbflush();
	}

	require_once(DIR . '/includes/functions_misc.php');
	$pagetime = vb_number_format(fetch_microtime_difference($starttime), 2);
	echo '</p><p><b>' . construct_phrase($vbphrase['processing_time_x'], $pagetime) . '<br />' . construct_phrase($vbphrase['total_posts_processed_x'], $vbulletin->GPC['totalposts']) . '</b></p>';
	vbflush();

	if (($vbulletin->GPC['totalposts'] < $vbulletin->GPC['doprocess'] OR !$vbulletin->GPC['doprocess']) AND $checkmore = $db->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE postid >= $finishat LIMIT 1"))
	{
		if ($vbulletin->GPC['autoredirect'] == 1)
		{
			print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=buildpostindex&startat=$finishat&pp=" . $vbulletin->GPC['perpage'] . "&autoredirect=" . $vbulletin->GPC['autoredirect'] . "&doprocess=" . $vbulletin->GPC['doprocess'] . "&totalposts=" . $vbulletin->GPC['totalposts']);
		}
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=buildpostindex&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;autoredirect=" . $vbulletin->GPC['autoredirect'] . "&amp;doprocess=" . $vbulletin->GPC['doprocess'] . "&amp;totalposts=" . $vbulletin->GPC['totalposts'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
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
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 1000;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['updating_post_counts'] . '</p>';

	$forums = $db->query_read("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum AS forum
		WHERE (forum.options & " . $vbulletin->bf_misc_forumoptions['countposts'] . ")
	");
	$gotforums = '';
	while ($forum = $db->fetch_array($forums))
	{
		$gotforums .= ',' . $forum['forumid'];
	}

	$users = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "user
		WHERE userid >= " . $vbulletin->GPC['startat'] . " AND userid < $finishat
		ORDER BY userid
	");
	while ($user = $db->fetch_array($users))
	{
		$totalposts = $db->query_first("
			SELECT COUNT(*) AS posts FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog_t ON (deletionlog_t.primaryid = thread.threadid AND deletionlog_t.type = 'thread')
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog_p ON (deletionlog_p.primaryid = post.postid AND deletionlog_p.type = 'post')
			WHERE post.userid = $user[userid] AND
				thread.forumid IN (0$gotforums) AND
				deletionlog_t.primaryid IS NULL AND
				deletionlog_p.primaryid IS NULL
		");

		$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
		$userdm->set_existing($user);
		$userdm->set('posts', $totalposts['posts']);
		$userdm->save();
		unset($userdm);

		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		vbflush();
	}


	if ($checkmore = $db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateposts&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateposts&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
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
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 1000;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['updating_user_info'] . '</p>';
	$tmp_usergroup_cache = array();

	$users = $db->query_read("
		SELECT user.*, usertextfield.rank,
		IF(user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING (userid)
		WHERE user.userid >= " . $vbulletin->GPC['startat'] . " AND user.userid < $finishat
		ORDER BY user.userid
	");
	while ($user = $db->fetch_array($users))
	{
		$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
		$userdm->set_existing($user);

		// update user stuff
		if ($user['customtitle'] == 0)
		{
			if (is_array($tmp_usergroup_cache["$user[displaygroupid]"]))
			{
				$usergroup = $tmp_usergroup_cache["$user[displaygroupid]"];
			}
			else if (is_array($vbulletin->usergroupcache["$user[displaygroupid]"]))
			{
				$usergroup = $vbulletin->usergroupcache["$user[displaygroupid]"];
			}
			else
			{
				$usergroup = $db->query_first("
					SELECT usertitle
					FROM " . TABLE_PREFIX . "usergroup
					WHERE usergroupid = $user[displaygroupid]
				");
				$tmp_usergroup_cache["$user[displaygroupid]"] = $usergroup;
			}
			if (empty($usergroup['usertitle']))
			{
				$gettitle = $db->query_first("
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

			$userdm->set('usertitle', $usertitle);
		}

		if ($lastpost = $db->query_first("SELECT MAX(dateline) AS dateline FROM " . TABLE_PREFIX . "post WHERE userid = $user[userid]"))
		{
			$lastpost['dateline'] = intval($lastpost['dateline']);
		}
		else
		{
			$lastpost['dateline'] = 0;
		}

		$userdm->set('posts', $user['posts']); // This will activate the rank update
		$userdm->set('lastpost', $lastpost['dateline']);
		$userdm->save();
		unset($userdm);

		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		vbflush();
	}

	if ($checkmore = $db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateuser&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateuser&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
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
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 1000;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['updating_usernames'] . '</p>';
	$users = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "user
		WHERE userid >= " . $vbulletin->GPC['startat'] . " AND
			userid < $finishat
		ORDER BY userid
	");
	while ($user = $db->fetch_array($users))
	{
		$userman =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
		$userman->set_existing($user);
		$userman->update_username($user['userid'], $user['username']);
		unset($userman);

		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		vbflush();
	}

	if ($checkmore = $db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateusernames&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateusernames&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
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
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 100;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['updating_forums'] . '</p>';

	$forums = $db->query_read("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum
		WHERE forumid >= " . $vbulletin->GPC['startat'] . " AND
			forumid < $finishat
		ORDER BY forumid
	");
	while($forum = $db->fetch_array($forums))
	{
		build_forum_counters($forum['forumid']);
		echo construct_phrase($vbphrase['processing_x'], $forum['forumid']) . "<br />\n";
		vbflush();
	}

	if ($checkmore = $db->query_first("SELECT forumid FROM " . TABLE_PREFIX . "forum WHERE forumid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateforum&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateforum&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		// get rid of "ghost" moderators who are not attached to a valid forum
		$deadmods = $db->query_read("
			SELECT moderatorid
			FROM " . TABLE_PREFIX . "moderator AS moderator
			LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING (forumid)
			WHERE forum.forumid IS NULL
		");

		$mods = '';

		while ($mod = $db->fetch_array($deadmods))
		{
			if (!empty($mods))
			{
				$mods .= ' , ';
			}
			$mods .= $mod['moderatorid'];
		}

		if (!empty($mods))
		{
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "moderator WHERE moderatorid IN (" . $mods . ")");
		}

		// and finally rebuild the forumcache
		unset($forumarraycache, $vbulletin->forumcache);
		build_forum_permissions();

		define('CP_REDIRECT', 'misc.php');
		print_stop_message('updated_forum_successfully');
	}
}

// ###################### Start update threads #######################
if ($_REQUEST['do'] == 'updatethread')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 2000;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['updating_threads'] . '</p>';

	$threads = $db->query_read("
		SELECT threadid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid >= " . $vbulletin->GPC['startat'] . " AND
		threadid < $finishat
		ORDER BY threadid
	");
	while ($thread = $db->fetch_array($threads))
	{
		build_thread_counters($thread['threadid']);
		echo construct_phrase($vbphrase['processing_x'], $thread['threadid'])."<br />\n";
		vbflush();
	}

	if ($checkmore = $db->query_first("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE threadid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updatethread&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updatethread&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
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
	require_once(DIR . '/includes/functions_search.php');
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 100;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['updating_similar_threads'] . '</p>';

	$threads = $db->query_read("
		SELECT title, threadid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid >= " . $vbulletin->GPC['startat'] . " AND
			threadid < $finishat
		ORDER BY threadid
	");
	while ($thread = $db->fetch_array($threads))
	{
		$similarthreads = fetch_similar_threads($thread['title'], $thread['threadid']);

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_CP, 'threadpost');
		$threadman->set_existing($thread);
		$threadman->set('similar', $similarthreads);
		$threadman->save();

		echo construct_phrase($vbphrase['processing_x'], $thread['threadid']) . "<br />\n";
		vbflush();
	}

	if ($checkmore = $db->query_first("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE threadid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updatesimilar&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=updatesimilar&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
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
	$vbulletin->input->clean_array_gpc('p', array(
		'reputation_base' => TYPE_INT,
	));

	$users = $db->query_read("
		SELECT reputation.userid, SUM(reputation.reputation) AS totalrep
		FROM " . TABLE_PREFIX . "reputation AS reputation
		GROUP BY reputation.userid
	");

	$userrep = array();
	while ($user = $db->fetch_array($users))
	{
		$user['totalrep'] += $vbulletin->GPC['reputation_base'];
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
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET reputation =
				CASE
					$usercasesql
					ELSE " . $vbulletin->GPC['reputation_base'] . "
				END
		");
	}
	else // there is no reputation
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET reputation = " . $vbulletin->GPC['reputation_base'] . "
		");
	}

	require_once(DIR . '/includes/adminfunctions_reputation.php');
	build_reputationids();

	define('CP_REDIRECT', 'misc.php');
	print_stop_message('rebuilt_user_reputation_successfully');

}

// ################## Start rebuilding attachment thumbnails ################
if ($_REQUEST['do'] == 'rebuildthumbs')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'quality'      => TYPE_UINT,
		'autoredirect' => TYPE_BOOL,
	));

	require_once(DIR . '/includes/class_image.php');
	$image =& vB_Image::fetch_library($vbulletin);

	//$validtypes = array('gif', 'jpg', 'jpe', 'jpeg', 'png', 'tif', 'tiff', 'psd', 'bmp');
	$validtypes =& $image->resize_extensions;

	foreach ($vbulletin->attachmentcache AS $key => $value)
	{
		$key = strtolower($key);
		if ($key != 'extensions' AND !empty($validtypes["$key"]) AND $vbulletin->attachmentcache["$key"]['thumbnail'])
		{
			$extensions .= iif($extensions, ', ') . "'$key'";
		}
	}

	if (!$extensions)
	{
		print_stop_message('you_have_no_attachments_set_to_thumb');
	}

	if ($vbulletin->options['imagetype'] == 'GD' AND (!function_exists('imagetypes') OR !$vbulletin->options['gdversion']))
	{
		//define('CP_REDIRECT', 'misc.php');
		print_stop_message('your_version_no_image_support');
	}

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 20;
	}

	if (!$vbulletin->GPC['startat'])
	{
		$firstattach = $db->query_first("SELECT MIN(attachmentid) AS min FROM " . TABLE_PREFIX . "attachment");
		$vbulletin->GPC['startat'] = intval($firstattach['min']);
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . construct_phrase($vbphrase['building_attachment_thumbnails'], "misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuildthumbs&startat=" . $vbulletin->GPC['startat'] . "&pp=" . $vbulletin->GPC['perpage']) . '</p>';

	if ($vbulletin->options['attachfile'])
	{
		require_once(DIR . '/includes/functions_file.php');
	}

	$attachments = $db->query_read("
		SELECT attachmentid, filedata, userid, postid, filename, dateline
		FROM " . TABLE_PREFIX . "attachment
		WHERE attachmentid >= " . $vbulletin->GPC['startat'] . "
			AND	attachmentid < $finishat
			AND	SUBSTRING_INDEX(filename, '.', -1) IN ($extensions)
		ORDER BY attachmentid
	");
	while ($attachment = $db->fetch_array($attachments))
	{
		if (!$vbulletin->options['attachfile']) // attachments are in the database
		{
			if ($vbulletin->options['safeupload'])
			{
				$filename = $vbulletin->options['tmppath'] . '/' . md5(uniqid(microtime()) . $vbulletin->userinfo['userid']);
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

		echo construct_phrase($vbphrase['processing_x'], "$vbphrase[attachment] : (" . file_extension($attachment['filename']) . ') ' .
			construct_link_code($attachment['attachmentid'], "../attachment.php?" . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;d=$attachment[dateline]", 1) . " ($vbphrase[post] : " .
			construct_link_code($attachment['postid'], "../showthread.php?" . $vbulletin->session->vars['sessionurl'] . "p=$attachment[postid]", 1) . " )") . ' ';

		if (!is_readable($filename) OR !@filesize($filename))
		{
			echo '<b>' . $vbphrase['error_attachment_missing'] . '</b><br />';
			continue;
		}

		$thumbnail = $image->fetch_thumbnail($attachment['filename'], $filename, $vbulletin->options['attachthumbssize'], $vbulletin->options['attachthumbssize'], $vbulletin->GPC['quality']);

		// Remove temporary file we used to generate thumbnail
		if (!$vbulletin->options['attachfile'])
		{
			@unlink($filename);
		}

		if (!empty($thumbnail['filedata']))
		{
			$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_SILENT);
			$attachdata->set_existing($attachment);
			$attachdata->setr('thumbnail', $thumbnail['filedata']);
			$attachdata->set('thumbnail_dateline', TIMENOW);
			if (!($result = $attachdata->save()))
			{
				if (!empty($attachdata->errors[0]))
				{
					echo $attacherror =& $attachdata->errors[0];
				}
			}
			unset($attachdata);
		}

		if (!empty($thumbnail['imageerror']))
		{
			echo '<b>' . $vbphrase["error_$thumbnail[imageerror]"] . '</b>';
		}
		else if (empty($thumbnail['filedata']))
		{
			echo '<b>' . $vbphrase['error'] . '</b>';
		}
		echo '<br />';
		vbflush();
	}

	if ($checkmore = $db->query_first("SELECT attachmentid FROM " . TABLE_PREFIX . "attachment WHERE attachmentid >= $finishat AND SUBSTRING_INDEX(filename, '.', -1) IN ('gif', 'jpg', 'jpeg', 'jpe', 'png') LIMIT 1"))
	{
		if ($vbulletin->GPC['autoredirect'] == 1)
		{
			print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuildthumbs&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;quality=" . $vbulletin->GPC['quality'] . "&amp;autoredirect=1");
		}
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuildthumbs&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;quality=" . $vbulletin->GPC['quality'] . '">' . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
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
	$bbcodelist = array();
	$bbcodes = $db->query_read("
		SELECT templateid,  template
		FROM " . TABLE_PREFIX . "template
		WHERE title IN ('bbcode_quote', 'bbcode_php', 'bbcode_code', 'bbcode_html')
	");
	while ($bbcode = $db->fetch_array($bbcodes))
	{
		$bbcodelist["$bbcode[templateid]"] = $bbcode['template'];
	}

	$stylelist = array();
	$uniquelist = array();
	$styles = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "style
	");

	while ($style = $db->fetch_array($styles))
	{
		$tlist = unserialize($style['templatelist']);
		$stylelist["$style[styleid]"]['templatelist'] = array(
			'bbcode_code'  =>& $bbcodelist["$tlist[bbcode_code]"],
			'bbcode_quote' =>& $bbcodelist["$tlist[bbcode_quote]"],
			'bbcode_php'   =>& $bbcodelist["$tlist[bbcode_php]"],
			'bbcode_html'  =>& $bbcodelist["$tlist[bbcode_html]"]
		);

		$stylelist["$style[styleid]"]['idlist'] = array(
			'bbcode_code'  => intval($tlist['bbcode_code_styleid']),
			'bbcode_quote' => intval($tlist['bbcode_quote_styleid']),
			'bbcode_php'   => intval($tlist['bbcode_php_styleid']),
			'bbcode_html'  => intval($tlist['bbcode_html_styleid'])
		);

		// The fact that we use $userinfo here means that if you were to use any language specific stylevars in these templates (which we don't do by default), they would be of this user's language
		// The only remedy for this is to create even more scenarios in the post parsed table with left -> right, right -> left and the imageoverride folder :eek:
		$stylelist["$style[styleid]"]['stylevar'] = fetch_stylevars($style, $vbulletin->userinfo);

		$unique = "{$tlist['bbcode_code_styleid']}|{$tlist['bbcode_quote_styleid']}|{$tlist['bbcode_php_styleid']}|{$tlist['bbcode_html_styleid']}";
		if (empty($uniquelist["$unique"]))
		{
			$uniquelist["$unique"] = $style['styleid'];
		}
	}
	$stylelist["0"] =& $stylelist["{$vbulletin->options['styleid']}"];

	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 1000;
	}

	if ($vbulletin->GPC['startat'] == 0)
	{
		$db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "post_parsed");
		$firstpost = $db->query_first("SELECT MIN(postid) AS min FROM " . TABLE_PREFIX . "post");
		$vbulletin->GPC['startat'] = intval($firstpost['min']);
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['building_post_cache'] . '</p>';
	$saveparsed = '';

	$posts = $db->query_read("
		SELECT postid, forumid, pagetext, allowsmilie, thread.lastpost
		FROM " . TABLE_PREFIX . "post AS post, " . TABLE_PREFIX . "thread AS thread
		WHERE post.threadid = thread.threadid AND
			postid >= " . $vbulletin->GPC['startat'] . " AND
			postid < $finishat AND
			thread.lastpost >= " . (TIMENOW - ($vbulletin->options['cachemaxage'] * 60 * 60 * 24)) . "
		ORDER BY postid
	");
	while ($post = $db->fetch_array($posts))
	{
		# Only cache posts for the chosen style if this post belongs to a forum with a styleoverride
		if ($vbulletin->forumcache["$post[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['styleoverride'])
		{
			$styleid = $vbulletin->forumcache["$post[forumid]"]['styleid'];
			$vbulletin->templatecache =& $stylelist["$styleid"]['templatelist'];
			$stylevar =& $stylelist["$styleid"]['stylevar'];

			$parsedtext = $bbcode_parser->parse($post['pagetext'], $post['forumid'], $post['allowsmilie'], false, '', false, true);

			$saveparsed .= ", ($post[postid],
				" . intval($post['lastpost']) . ",
				" . intval($bbcode_parser->cached['has_images']) . ",
				'" . $db->escape_string($bbcode_parser->cached['text']) . "',
				" . $stylelist["$styleid"]['idlist']['bbcode_code'] . ",
				" . $stylelist["$styleid"]['idlist']['bbcode_html'] . ",
				" . $stylelist["$styleid"]['idlist']['bbcode_php'] . ",
				" . $stylelist["$styleid"]['idlist']['bbcode_quote'] . "
			)";
			echo construct_phrase($vbphrase['processing_x'], $post['postid']) . "<br />\n";
		}
		else
		{
			echo construct_phrase($vbphrase['processing_x'], $post['postid']) . "\n";
			$count = 0;
			foreach ($uniquelist AS $styleid)
			{
				$count++;
				if ($count > 1)
				{
					echo ',';
				}
				echo " $count";
				$vbulletin->templatecache =& $stylelist["$styleid"]['templatelist'];
				$stylevar =& $stylelist["$styleid"]['stylevar'];

				$parsedtext = $bbcode_parser->parse($post['pagetext'], $post['forumid'], $post['allowsmilie'], false, '', false, true);
				$saveparsed .= ", ($post[postid],
					" . intval($post['lastpost']) . ",
					" . intval($bbcode_parser->cached['has_images']) . ",
					'" . $db->escape_string($bbcode_parser->cached['text']) . "',
					" . $stylelist["$styleid"]['idlist']['bbcode_code'] . ",
					" . $stylelist["$styleid"]['idlist']['bbcode_html'] . ",
					" . $stylelist["$styleid"]['idlist']['bbcode_php'] . ",
					" . $stylelist["$styleid"]['idlist']['bbcode_quote'] . "
				)";
			}
			echo "<br />\n";
		}
	}
	if ($saveparsed)
	{
		$saveparsed = substr($saveparsed, 1);
		/*insert query*/
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "post_parsed
			(postid, dateline, hasimages, pagetext_html , styleid_code, styleid_html, styleid_php, styleid_quote)
			VALUES
			$saveparsed
		");
	}
	else
	{
		echo construct_phrase($vbphrase['processing_x'], $vbphrase['n_a']) . "\n";
	}

	vbflush();

	if ($checkmore = $db->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE postid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=buildpostcache&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=buildpostcache&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
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
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 500;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['removing_duplicate_threads'] . '</p>';

	$threads = $db->query_read("
		SELECT threadid, title, forumid, postusername, dateline
		FROM " . TABLE_PREFIX . "thread WHERE threadid >= " . $vbulletin->GPC['startat'] . " AND
			threadid < $finishat
		ORDER BY threadid
	");

	while ($thread = $db->fetch_array($threads))
	{
		$deletethreads = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "thread
			WHERE title = '" . $db->escape_string($thread['title']) . "' AND
				forumid = $thread[forumid] AND
				postusername = '" . $db->escape_string($thread['postusername']) . "' AND
				dateline = $thread[dateline] AND
				threadid > $thread[threadid]
		");
		while ($deletethread = $db->fetch_array($deletethreads))
		{
			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$threadman->set_existing($deletethread);
			$threadman->delete($vbulletin->forumcache["$deletethread[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['countposts']);
			unset($threadman);

			echo "&nbsp;&nbsp;&nbsp; ".construct_phrase($vbphrase['delete_x'], $deletethread['threadid'])."<br />";
		}
		echo construct_phrase($vbphrase['processing_x'], $thread['threadid'])."<br />\n";
		vbflush();
	}
	if ($checkmore = $db->query_first("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE threadid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=removedupe&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=removedupe&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'misc.php');
		print_stop_message('deleted_duplicate_threads_successfully');
	}

}

// ###################### Start find lost users #######################
if ($_POST['do'] == 'lostusers')
{
	$users = $db->query_read("
		SELECT user.userid
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield USING(userid)
		WHERE userfield.userid IS NULL
	");

	$userids = array();
	while ($user = $db->fetch_array($users))
	{
		$userids[] = $user['userid'];
	}

	if (!empty($userids))
	{
		/*insert query*/
		$db->query_write("INSERT INTO " . TABLE_PREFIX . "userfield (userid) VALUES (" . implode('),(', $userids) . ")");
	}

	$users = $db->query_read("
		SELECT user.userid
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING(userid)
		WHERE usertextfield.userid IS NULL
	");

	$userids = array();
	while ($user = $db->fetch_array($users))
	{
		$userids[] = $user['userid'];
	}

	if (!empty($userids))
	{
		/*insert query*/
		$db->query_write("INSERT INTO " . TABLE_PREFIX . "usertextfield (userid) VALUES (" . implode('),(', $userids) . ")");
	}

	define('CP_REDIRECT', 'misc.php');
	print_stop_message('user_records_repaired');
}

// ###################### Start build statistics #######################
if ($_REQUEST['do'] == 'buildstats')
{
	$timestamp =& $vbulletin->GPC['startat'];
	$vbulletin->GPC['perpage'] = 10 * 86400;

	if (empty($timestamp))
	{
		// this is the first page of a stat rebuild
		// so let's clear out the old stats
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "stats");

		// and select a suitable start time
		$timestamp = $db->query_first("SELECT MIN(joindate) AS start FROM " . TABLE_PREFIX . "user WHERE joindate > 0");
		if ($timestamp['start'] == 0 OR $timestamp['start'] < 915166800)
		{ // no value found or its before 1999 lets just make it the year 2000
			$timestamp['start'] = 946684800;
		}
		$month = date('n', $timestamp['start']);
		$day = date('j', $timestamp['start']);
		$year = date('Y', $timestamp['start']);

		$timestamp = mktime(0, 0, 0, $month, $day, $year);
	}

	if ($timestamp + $vbulletin->GPC['perpage'] >= TIMENOW)
	{
		$endstamp = TIMENOW;
	}
	else
	{
		$endstamp = $timestamp + $vbulletin->GPC['perpage'];
	}

	while ($timestamp <= $endstamp)
	{
		// new users
		$newusers = $db->query_first('SELECT COUNT(userid) AS total FROM ' . TABLE_PREFIX . 'user WHERE joindate >= ' . $timestamp . ' AND joindate < ' . ($timestamp + 86400));

		// new threads
		$newthreads = $db->query_first('SELECT COUNT(threadid) AS total FROM ' . TABLE_PREFIX . 'thread WHERE dateline >= ' . $timestamp . ' AND dateline < ' . ($timestamp + 86400));

		// new posts
		$newposts = $db->query_first('SELECT COUNT(threadid) AS total FROM ' . TABLE_PREFIX . 'post WHERE dateline >= ' . $timestamp . ' AND dateline < ' . ($timestamp + 86400));

		// active users
		$activeusers = $db->query_first('SELECT COUNT(userid) AS total FROM ' . TABLE_PREFIX . 'user WHERE lastactivity >= ' . $timestamp . ' AND lastactivity < ' . ($timestamp + 86400));

		$inserts[] = "($timestamp, $newusers[total], $newthreads[total], $newposts[total], $activeusers[total])";

		echo $vbphrase['done'] . " $timestamp <br />\n";
		vbflush();

		$timestamp += 3600 * 24;

	}

	if (!empty($inserts))
	{
		/*insert query*/
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "stats
				(dateline, nuser, nthread, npost, ausers)
			VALUES
				" . implode(',', $inserts) . "
		");

		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=buildstats&startat=$timestamp");

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
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 50;
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
		$threads = $db->query_read("
			SELECT thread.threadid
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING(forumid)
			WHERE forum.forumid IS NULL
		");
		while ($thread = $db->fetch_array($threads))
		{
			$threadarray[] = $thread['threadid'];
			$count++;
		}
	}

	echo '<p>' . $vbphrase['removing_orphan_threads'] . '</p>';

	while ($threadid = array_pop($threadarray) AND $count < $vbulletin->GPC['perpage'])
	{
		$threadinfo = fetch_threadinfo($threadid);

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threadman->set_existing($threadinfo);
		$threadman->delete();
		unset($threadman);

		echo construct_phrase($vbphrase['processing_x'], $threadid)."<br />\n";
		vbflush();
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

	print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=removeorphanthreads&pp=" . $vbulletin->GPC['perpage']);
	echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=removeorphanthreads&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";

}

// ###################### Start remove posts #######################
if ($_REQUEST['do'] == 'removeorphanposts')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 50;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	$posts = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING(threadid)
		WHERE thread.threadid IS NULL
		LIMIT " . $vbulletin->GPC['startat'] . ", " . $vbulletin->GPC['perpage'] . "
	");
	while ($post = $db->fetch_array($posts))
	{
		$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$postman->set_existing($post);
		$postman->delete();
		unset($postman);

		echo construct_phrase($vbphrase['processing_x'], $post['postid'])."<br />\n";
		vbflush();
		$gotsome = true;
	}

	if($gotsome)
	{
		print_cp_redirect("misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=removeorphanposts&pp=" . $vbulletin->GPC['perpage'] . "&startat=$finishat");
		echo "<p><a href=\"misc.php?" . $vbulletin->session->vars['sessionurl'] . "do=removeorphanposts&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;startat=$finishat\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
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
	print_description_row(construct_phrase($vbphrase['note_reindexing_empty_indexes_x'], $vbulletin->session->vars['sessionurl']));
	print_input_row($vbphrase['number_of_posts_to_process_per_cycle'], 'perpage', 250);
	print_input_row($vbphrase['post_id_to_start_at'], 'startat', 0);
	print_input_row($vbphrase['total_number_posts_process'], 'doprocess', 0);
	print_yes_no_row($vbphrase['include_automatic_javascript_redirect'], 'autoredirect', 1);
	print_description_row($vbphrase['note_server_intensive']);
	print_submit_row($vbphrase['rebuild_search_index']);

	if ($vbulletin->options['cachemaxage'] > 0)
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
	$quality = intval($vbulletin->options['thumbquality']);
	if ($quality <= 0 OR $quality > 100)
	{
		$quality = 75;
	}
	print_input_row($vbphrase['thumbnail_quality'], 'quality', $quality);
	print_yes_no_row($vbphrase['include_automatic_javascript_redirect'], 'autoredirect', 1);
	print_submit_row($vbphrase['rebuild_attachment_thumbnails']);

	print_form_header('misc', 'rebuildreputation');
	print_table_header($vbphrase['rebuild_user_reputation'], 2, 0);
	print_description_row($vbphrase['function_rebuilds_reputation']);
	print_input_row($vbphrase['reputation_base'], 'reputation_base', $vbulletin->options['reputationdefault']);
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

($hook = vBulletinHook::fetch_hook('admin_maintenance')) ? eval($hook) : false;

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13395 $
|| ####################################################################
\*======================================================================*/
?>