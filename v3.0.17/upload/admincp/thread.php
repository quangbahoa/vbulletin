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
$nozip = 1;

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 14579 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('thread', 'threadmanage');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_databuild.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminthreads'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif(isset($_REQUEST['forumid']), "forum id = $_REQUEST[forumid]", iif(isset($_REQUEST['pollid']), "poll id = $_REQUEST[pollid]")));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['thread_manager']);

// ###################### Do who voted ####################
if ($_POST['do'] == 'dovotes')
{

	$pollid = intval($_POST['pollid']);

	$poll = $DB_site->query_first("
		SELECT poll.*, thread.threadid, thread.title
		FROM " . TABLE_PREFIX . "poll AS poll
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING(pollid)
		WHERE poll.pollid = $pollid
	");

	$votes = $DB_site->query("
		SELECT pollvote.*, user.username
		FROM " . TABLE_PREFIX . "pollvote AS pollvote
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid=pollvote.userid)
		WHERE pollid = $pollid
		ORDER BY username ASC
	");

	$options = explode('|||', $poll['options']);

	$lastoption = 0;
	$users = '';

	print_form_header('', '');
	print_description_row(construct_phrase($vbphrase['poll_x_in_thread_y'], "<a href=\"../poll.php?$session[sessionurl]do=showresults&pollid=$poll[pollid]\" target=\"_blank\">$poll[question]</a>", "<a href=\"../showthread.php?$session[sessionurl]threadid=$poll[threadid]\" target=\"_blank\">$poll[title]</a>"), 0, 2, 'thead');
	print_table_header($poll['question'], 2, 0);

	while ($vote = $DB_site->fetch_array($votes))
	{
		if (empty($vote['username']))
		{
			$username = '<span class="smallfont">' . $vbphrase['guest'] . '</span>';
		}
		else
		{
			$username = "<a href=\"../member.php?$session[sessionurl]do=getinfo&userid=$vote[userid]\" target=\"_blank\">$vote[username]</a>";
		}

		$votelist[$vote['voteoption']] .= "$username &nbsp;";
	}

	if (is_array($votelist))
	{
		foreach ($votelist AS $optionid => $usernamelist)
		{
			$option = $options[($optionid - 1)];
			print_label_row("<b>$option</b>", $usernamelist);
		}
	}

	print_table_footer();
}

// ###################### Start who voted ####################
if ($_REQUEST['do'] == 'votes')
{

// JAVASCRIPT CODE
?>
<script type="text/javascript">
function js_fetch_thread_title(formid,threadid)
{
	if (threadid)
	{
		formid.threadtitle.value = t[threadid];
	}
}
t = new Array();
<?php
// END JAVASCRIPT CODE

	$polloptions = '';
	$polls = $DB_site->query("
		SELECT thread.title, poll.pollid, poll.question
        FROM " . TABLE_PREFIX . "thread AS thread
        LEFT JOIN " . TABLE_PREFIX . "poll AS poll ON (thread.pollid=poll.pollid)
        WHERE thread.open <> 10 AND thread.pollid <> 0
        ORDER BY thread.dateline DESC
	");
	while ($poll = $DB_site->fetch_array($polls))
	{
		if (empty($poll['pollid']))
		{
			continue;
		}
		if (empty($firsttitle))
		{
			$firsttitle = $poll['title'];
		}
		$polloptions .= "<option value=\"$poll[pollid]\">[$poll[pollid]] $poll[question]</option>\n";
		echo "t[" . intval($poll['pollid']) . "] = \"$poll[title]\";\n";
	}

	echo "</script>\n\n";

	print_form_header('thread', 'dovotes');
	print_table_header($vbphrase['who_voted']);
	print_label_row($vbphrase['poll'], "<select name=\"pollid\" class=\"bginput\" tabindex=\"1\" onchange=\"js_fetch_thread_title(this.form,this.options[this.selectedIndex].value)\">$polloptions</select>", '', 'top', 'pollid');
	print_label_row($vbphrase['thread'], "<input type=\"text\" tabindex=\"1\" class=\"bginput\" size=\"50\" name=\"threadtitle\" value=\"$firsttitle\" readonly=\"readonly\" disabled=\"disabled\" />", '', 'top', 'threadtitle');
	print_submit_row($vbphrase['who_voted'], 0);
}

// ###################### Start Prune by user #######################
if ($_REQUEST['do'] == 'pruneuser')
{
	globalize($_REQUEST, array('username' => STR, 'forumid' => INT, 'subforums' => INT, 'confirm' => INT, 'userid' => INT));

	if ($confirm != 1)
	{

		if (empty($username) or $forumid == 0)
		{
			print_stop_message('please_complete_required_fields');
		}

		if ($forumid == -1)
		{
			$forumtitle = $vbphrase['all_forums'];
		}
		else
		{
			$forum = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "forum WHERE forumid = $forumid");
			$forumtitle = 'the "' . $forum['title'] . '" forum';
			if ($subforums)
			{
				$forumtitle .= '(' . $vbphrase['include_child_forums'] . ')';
			}
		}
		echo '<p>' . construct_phrase($vbphrase['about_to_delete_posts_in_x_by_users'], $forumtitle) . '</p>';

		$users = $DB_site->query("
			SELECT userid,username
			FROM " . TABLE_PREFIX . "user
			WHERE username LIKE '%" . addslashes(htmlspecialchars_uni($username)) . "%'
			ORDER BY username
		");
		while ($user = $DB_site->fetch_array($users))
		{

			print_form_header('thread', 'pruneuser');
			print_table_header(construct_phrase($vbphrase['prune_all_x_posts_automatically'], $user['username']), 2, 0);
			construct_hidden_code('forumid', $forumid);
			construct_hidden_code('userid', $user['userid']);
			construct_hidden_code('subforums', $subforums);
			construct_hidden_code('confirm', 1);
			print_submit_row(construct_phrase($vbphrase['prune_all_x_posts_automatically'], $user['username']), '', 2);

			print_form_header('thread', 'pruneusersel');
			print_table_header(construct_phrase($vbphrase['prune_x_posts_selectively'], $user['username']), 2, 0);
			construct_hidden_code('forumid', $forumid);
			construct_hidden_code('userid', $user['userid']);
			construct_hidden_code('subforums', $subforums);
			construct_hidden_code('confirm', 1);
			print_submit_row(construct_phrase($vbphrase['prune_x_posts_selectively'], $user['username']), '', 2);

			echo "\n<hr />\n";
		}
		exit;
	}

	if ($forumid != -1)
	{
		if ($subforums)
		{
			$forumcheck = "(thread.forumid=$forumid OR parentlist LIKE '%,$forumid,%') AND ";
		}
		else
		{
			$forumcheck = "thread.forumid=$forumid AND ";
		}
	}
	else
	{
		$forumcheck = '';
	}

	$usernames = $DB_site->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = $userid");
	$username = $usernames['username'];

	echo '<p><b>' . $vbphrase['deleting_threads'] . '</b>';
	$threads = $DB_site->query("
		SELECT threadid
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING(forumid)
		WHERE $forumcheck postusername = '" . addslashes($username) . "'
	");
	while ($thread = $DB_site->fetch_array($threads))
	{
		delete_thread($thread['threadid'], 0);
		echo ". \n";
		flush();
	}
	echo ' ' .$vbphrase['done'] . '</p><p><b>' . $vbphrase['deleting_posts'] . '</b>';
	$posts = $DB_site->query("
		SELECT postid FROM " . TABLE_PREFIX . "post AS post,
		" . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING(forumid)
		WHERE $forumcheck
			post.threadid = thread.threadid AND
			post.userid = $userid
	");

	while ($post = $DB_site->fetch_array($posts))
	{
		delete_post($post['postid']);
		echo ". \n";
		flush();
	}
	echo ' ' .$vbphrase['done'] . '</p>';

	//define('CP_REDIRECT', 'thread.php?do=prune');
	print_stop_message('pruned_threads_successfully');
}

// ###################### Start prune by user selector #######################
if ($_REQUEST['do'] == 'pruneusersel')
{
	globalize($_REQUEST, array('forumid' => INT, 'subforums' => INT, 'confirm' => INT, 'userid' => INT));

	$usernames = $DB_site->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = $userid");
	$username = $usernames['username'];

	if ($forumid != -1)
	{
		if ($subforums)
		{
			$forumcheck = "(thread.forumid = $forumid OR parentlist LIKE '%,$forumid,%') AND ";
		}
		else
		{
			$forumcheck = "thread.forumid = $forumid AND ";
		}
	}
	else
	{
		$forumcheck = '';
	}

?>
	<script type="text/javascript">
	function js_check_all_posts()
	{
		for (var i=0; i < document.cpform.elements.length; i++)
		{
			var e = document.cpform.elements[i];
			if (e.name != 'allboxposts' && e.name != 'allboxthreads' && e.type=='checkbox' && e.name.substring(0, 10) == 'deletepost')
			{
				e.checked = document.cpform.allboxposts.checked;
			}
		}
	}

	function js_check_all_threads()
	{
		for (var i=0;i < document.cpform.elements.length;i++)
		{
			var e = document.cpform.elements[i];
			if (e.name != 'allboxposts' && e.name != 'allboxthreads' && e.type=='checkbox' && e.name.substring(0, 12) == 'deletethread')
			{
				e.checked = document.cpform.allboxthreads.checked;
			}
		}
	}
	</script>
<?php

	print_form_header('thread', 'dopruneuser');
	print_table_header($vbphrase['prune_threads']);
	print_label_row($vbphrase['title'], '<label for="cb_allthreads">' . $vbphrase['delete'] . ' <input type="checkbox" name="allboxthreads" title="' . $vbphrase['check_all'] . '" onClick="js_check_all_threads();" checked="checked" /></label>', 'thead');

	$threads = $DB_site->query("
		SELECT threadid,thread.title
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING(forumid)
		WHERE $forumcheck postusername = '" . addslashes($username) . "'
		ORDER BY thread.lastpost DESC
	");
	while ($thread = $DB_site->fetch_array($threads))
	{
		print_checkbox_row("<a href=\"../showthread.php?$session[sessionurl]threadid=$thread[threadid]\" target=\"_blank\">$thread[title]</a>", "deletethread[$thread[threadid]]", 1, 1);
	}

	print_table_break();
	print_table_header($vbphrase['prune_posts']);
	print_label_row($vbphrase['title'], '<label for="cb_allposts">' . $vbphrase['delete'] . ' <input type="checkbox" name="allboxposts" tabindex="1" title="' . $vbphrase['check_all'] . '" onClick="js_check_all_posts();" checked="checked" /></label>', 'thead');

	$threads = $DB_site->query("SELECT post.postid,thread.threadid,thread.title FROM " . TABLE_PREFIX . "post AS post, " . TABLE_PREFIX . "thread AS thread LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING (forumid) WHERE thread.threadid = post.threadid AND thread.firstpostid <> post.postid AND $forumcheck post.userid=$userid ORDER BY post.threadid DESC, post.dateline DESC");
	while ($thread = $DB_site->fetch_array($threads))
	{
		print_checkbox_row("<a href=\"../showthread.php?$session[sessionurl]threadid=$thread[threadid]\" target=\"_blank\">$thread[title]</a> (postid $thread[postid])", "deletepost[$thread[postid]]", 1, 1);
	}

	print_table_break();

	print_submit_row($vbphrase['submit']);
}

// ###################### Start Prune by user selected #######################
if ($_POST['do'] == 'dopruneuser')
{

	echo '<p><b>' . $vbphrase['deleting_threads'] . '</b>';
	if (is_array($_POST['deletethread']))
	{
		foreach ($_POST['deletethread'] AS $threadid => $confirm)
		{
			if ($confirm == 1)
			{
				delete_thread($threadid, 0);
				echo ". \n";
				flush();
			}
		}
	}
	echo ' ' . $vbphrase['done'] . '</p><p><b>' . $vbphrase['deleting_posts'] . '</b>';
	if (is_array($_POST['deletepost']))
	{
		foreach ($_POST['deletepost'] AS $postid => $confirm)
		{
			if ($confirm == 1)
			{
				delete_post($postid, 0);
				echo ". \n";
				flush();
			}
		}
	}
	echo ' ' . $vbphrase['done'] . '</p>';

	//define('CP_REDIRECT', 'thread.php?do=prune');
	print_stop_message('pruned_threads_successfully');
}

// ###################### Start Prune #######################
if ($_REQUEST['do'] == 'prune')
{
	print_form_header('', '');
	print_table_header($vbphrase['prune_threads_manager']);
	print_description_row($vbphrase['pruning_many_threads_is_a_server_intensive_process']);
	print_table_footer();

	print_form_header('thread', 'dothreads');
	construct_hidden_code('type', 'prune');
	print_move_prune_rows();
	print_submit_row($vbphrase['prune_threads']);

	print_form_header('thread', 'pruneuser');
	print_table_header($vbphrase['prune_by_username']);
	print_input_row($vbphrase['username'], 'username');
	print_forum_chooser('forumid', -1, $vbphrase['all_forums'], $vbphrase['forum'], 1, 0, 1);

	print_yes_no_row($vbphrase['include_child_forums'], 'subforums');
	print_submit_row($vbphrase['prune_threads']);
}

// ###################### Start Move #######################
if ($_REQUEST['do'] == 'move')
{
	print_form_header('thread', 'dothreads');
	construct_hidden_code('type', 'move');
	print_table_header($vbphrase['move_threads']);
	print_forum_chooser('destforumid', -1, '', $vbphrase['destination_forum'], 0);
	print_move_prune_rows();
	print_submit_row($vbphrase['move_threads']);
}

/************ GENERAL MOVE/PRUNE HANDLING CODE ******************/

// ###################### Start makeprunemoveboxes #######################
function print_move_prune_rows()
{
	global $vbphrase;
	print_description_row($vbphrase['date_options'], 0, 2, 'thead', 'center');
		print_input_row($vbphrase['original_post_date_is_at_least_xx_days_ago'], 'thread[originaldaysolder]', 0, 1, 5);
		print_input_row($vbphrase['original_post_date_is_at_most_xx_days_ago'] . '<dfn>' . construct_phrase($vbphrase['note_leave_x_specify_no_limit'], '0') . '</dfn>', 'thread[originaldaysnewer]', 0, 1, 5);
		print_input_row($vbphrase['last_post_date_is_at_least_xx_days_ago'], 'thread[lastdaysolder]', 0, 1, 5);
		print_input_row($vbphrase['last_post_date_is_at_most_xx_days_ago'] . '<dfn>' . construct_phrase($vbphrase['note_leave_x_specify_no_limit'], '0') . '</dfn>', 'thread[lastdaysnewer]', 0, 1, 5);

	print_description_row($vbphrase['view_options'], 0, 2, 'thead', 'center');
		print_input_row($vbphrase['thread_has_at_least_xx_replies'], 'thread[repliesleast]', 0, 1, 5);
		print_input_row($vbphrase['thread_has_at_most_xx_replies'] . '<dfn>' . construct_phrase($vbphrase['note_leave_x_specify_no_limit'], '-1') . '</dfn>', 'thread[repliesmost]', -1, 1, 5);
		print_input_row($vbphrase['thread_has_at_least_xx_views'], 'thread[viewsleast]', 0, 1, 5);
		print_input_row($vbphrase['thread_has_at_most_xx_views'] . '<dfn>' . construct_phrase($vbphrase['note_leave_x_specify_no_limit'], '-1') . '</dfn>', 'thread[viewsmost]', -1, 1, 5);

	print_description_row($vbphrase['status_options'], 0, 2, 'thead', 'center');
		print_yes_no_other_row($vbphrase['thread_is_sticky'], 'thread[issticky]', $vbphrase['either'], 0);
		print_yes_no_other_row($vbphrase['thread_is_deleted'], 'thread[isdeleted]', $vbphrase['either'], -1);
		print_yes_no_other_row($vbphrase['thread_is_open'], 'thread[isopen]', $vbphrase['either'], -1);
		print_yes_no_other_row($vbphrase['thread_is_visible'], 'thread[isvisible]', $vbphrase['either'], -1);
		print_yes_no_other_row($vbphrase['thread_is_a_redirect'], 'thread[isredirect]', $vbphrase['either'], 0);

	print_description_row($vbphrase['other_options'], 0, 2, 'thead', 'center');
		print_input_row($vbphrase['username'], 'thread[posteduser]');
		print_input_row($vbphrase['title'], 'thread[titlecontains]');
		print_forum_chooser('thread[forumid]', -1, $vbphrase['all_forums'], $vbphrase['forum'], 1, 0, 1);
		print_yes_no_row($vbphrase['include_child_forums'], 'thread[subforums]');
}

// ###################### Start genmoveprunequery #######################
function fetch_thread_move_prune_sql($thread)
{
	global $DB_site, $vbphrase;

	$query = '1=1';

	// original post
	if (intval($thread['originaldaysolder']))
	{
		$query .= ' AND thread.dateline <= ' . (TIMENOW - ($thread['originaldaysolder'] * 86400));
	}
	if (intval($thread['originaldaysnewer']))
	{
		$query .= ' AND thread.dateline >= ' . (TIMENOW - ($thread['originaldaysnewer'] * 86400));
	}

	// last post
	if (intval($thread['lastdaysolder']))
	{
		$query .= ' AND thread.lastpost <= ' . (TIMENOW - ($thread['lastdaysolder'] * 86400));
	}
	if (intval($thread['lastdaysnewer']))
	{
		$query .= ' AND thread.lastpost >= ' . (TIMENOW - ($thread['lastdaysnewer'] * 86400));
	}

	// replies
	if (intval($thread['repliesleast']) > 0)
	{
		$query .= ' AND thread.replycount >= ' . intval($thread['repliesleast']);
	}
	if (intval($thread['repliesmost']) > -1)
	{
		$query .= ' AND thread.replycount <= ' . intval($thread['repliesmost']);
	}

	// views
	if (intval($thread['viewsleast']) > 0)
	{
		$query .= ' AND thread.views >= ' . intval($thread['viewsleast']);
	}
	if (intval($thread['viewsmost']) > -1)
	{
		$query .= ' AND thread.views <= ' . intval($thread['viewsmost']);
	}

	// sticky
	if ($thread['issticky'] == 1)
	{
		$query .= ' AND thread.sticky = 1';
	}
	else if ($thread['issticky'] == 0)

	{
		$query .= ' AND thread.sticky = 0';
	}

	// deleted
	if ($thread['isdeleted'] == 1)
	{
		$query .= ' AND deletionlog.primaryid IS NOT NULL';
	}
	else if ($thread['isdeleted'] == 0)
	{
		$query .= ' AND deletionlog.primaryid IS NULL';
	}

	// open
	if ($thread['isopen'] == 1)
	{
		$query .= ' AND thread.open = 1';
	}
	else if ($thread['isopen'] == 0)
	{
		$query .= ' AND thread.open = 0';
	}

	// visible
	if ($thread['isvisible'] == 1)
	{
		$query .= ' AND thread.visible = 1';
	}
	else if ($thread['isvisible'] == 0)
	{
		$query .= ' AND thread.visible = 0';
	}

	// redirect
	if ($thread['isredirect'] == 1)
	{
		$query .= ' AND thread.open = 10';
	}
	else if ($thread['isredirect'] == 0)
	{
		$query .= ' AND thread.open <> 10';
	}

	// posted by
	if ($thread['posteduser'])
	{
		$user = $DB_site->query_first("
			SELECT userid FROM " . TABLE_PREFIX . "user
			WHERE username = '" . addslashes(htmlspecialchars_uni($thread['posteduser'])) . "'
		");
		if (!$user)
		{
			print_stop_message('invalid_username_specified');
		}
		$query .= " AND thread.postuserid = $user[userid]";
	}

	// title contains
	if ($thread['titlecontains'])
	{
		$query .= " AND thread.title LIKE '%" . addslashes_like(htmlspecialchars_uni($thread['titlecontains'])) . "%'";
	}

	// forum
	$thread['forumid'] = intval($thread['forumid']);
	if ($thread['forumid'] != -1)
	{
		if ($thread['subforums'])
		{
			$query .= " AND (thread.forumid = $thread[forumid] OR forum.parentlist LIKE '%,$thread[forumid],%')";
		}
		else
		{
			$query .= " AND thread.forumid = $thread[forumid]";
		}
	}

	return $query;
}

// ###################### Start thread move/prune by options #######################
if ($_POST['do'] == 'dothreads')
{
	$type = trim($_REQUEST['type']);
	$thread = $_REQUEST['thread'];
	$whereclause = fetch_thread_move_prune_sql($thread);

	if ($thread['forumid'] == 0)
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($type == 'move')
	{
		$destforumid = intval($_REQUEST['destforumid']);
		$foruminfo = fetch_foruminfo($destforumid);
		if (!$foruminfo)
		{
			print_stop_message('invalid_destination_forum_specified');
		}
		if (!$foruminfo['cancontainthreads'] OR $foruminfo['link'])
		{
			print_stop_message('destination_forum_cant_contain_threads');
		}
	}

	$fullquery = "
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE $whereclause
	";
	$count = $DB_site->query_first($fullquery);

	if (!$count['count'])
	{
		print_stop_message('no_threads_matched_your_query');
	}

	print_form_header('thread', 'dothreadsall');
	construct_hidden_code('type', $type);
	construct_hidden_code('criteria', serialize($thread));

	print_table_header(construct_phrase($vbphrase['x_thread_matches_found'], $count['count']));
	if ($type == 'prune')
	{
		print_submit_row($vbphrase['prune_all_threads'], '');
	}
	else
	{
		construct_hidden_code('destforumid', $_REQUEST['destforumid']);
		print_submit_row($vbphrase['move_all_threads'], '');
	}

	print_form_header('thread', 'dothreadssel');
	construct_hidden_code('type', $type);
	construct_hidden_code('criteria', serialize($thread));
	print_table_header(construct_phrase($vbphrase['x_thread_matches_found'], $count['count']));
	if ($type == 'prune')
	{
		print_submit_row($vbphrase['prune_threads_selectively'], '');
	}
	else
	{
		construct_hidden_code('destforumid', $destforumid);
		print_submit_row($vbphrase['move_threads_selectively'], '');
	}
}

// ###################### Start move/prune all matching #######################
if ($_POST['do'] == 'dothreadsall')
{
	$type = trim($_POST['type']);
	$thread = unserialize($_POST['criteria']);
	$whereclause = fetch_thread_move_prune_sql($thread);

	$fullquery = "
		SELECT thread.threadid
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE $whereclause
	";
	$threads = $DB_site->query($fullquery);

	if ($type == 'prune')
	{
		echo '<p><b>' . $vbphrase['deleting_threads'] . '</b>';
		while ($thread = $DB_site->fetch_array($threads))
		{
			delete_thread($thread['threadid'], 0);
			echo ". \n";
			flush();
		}
		echo ' ' . $vbphrase['done'] . '</p>';

		//define('CP_REDIRECT', 'thread.php?do=prune');
		print_stop_message('pruned_threads_successfully');
	}
	else if ($type == 'move')
	{
		$destforumid = intval($_POST['destforumid']);
		$threadslist = '0';
		while ($thread = $DB_site->fetch_array($threads))
		{
			$threadslist .= ",$thread[threadid]";
		}
		$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET forumid = $destforumid WHERE threadid IN ($threadslist)");

		//define('CP_REDIRECT', 'thread.php?do=move');
		print_stop_message('moved_threads_successfully');
	}
}

// ###################### Start move/prune select #######################
if ($_POST['do'] == 'dothreadssel')
{
	$type = trim($_POST['type']);
	$thread = unserialize($_POST['criteria']);
	$whereclause = fetch_thread_move_prune_sql($thread);

	$fullquery = "
		SELECT thread.*, forum.title AS forum_title
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE $whereclause
	";
	$threads = $DB_site->query($fullquery);

	print_form_header('thread', 'dothreadsselfinish');
	construct_hidden_code('type', $type);
	construct_hidden_code('destforumid', $_POST['destforumid']);
	if ($type == 'prune')
	{
		print_table_header($vbphrase['prune_threads_selectively'], 5);
	}
	else if ($type == 'move')
	{
		print_table_header($vbphrase['move_threads_selectively'], 5);
	}
	print_cells_row(array(
		'<input type="checkbox" name="allbox" title="' . $vbphrase['check_all'] . '" onclick="js_check_all(this.form);" checked="checked" />',
		$vbphrase['title'],
		$vbphrase['user'],
		$vbphrase['replies'],
		$vbphrase['last_post']
	), 1);

	while ($thread = $DB_site->fetch_array($threads))
	{
		$cells = array();
		$cells[] = "<input type=\"checkbox\" name=\"thread[$thread[threadid]]\" tabindex=\"1\" checked=\"checked\" />";
		$cells[] = "<a href=\"../showthread.php?$session[sessionurl]threadid=$thread[threadid]\" target=\"_blank\">$thread[title]</a>";
		if ($thread['postuserid'])
		{
			$cells[] = "<span class=\"smallfont\"><a href=\"../member.php?$session[sessionurl]userid=$thread[postuserid]\">$thread[postusername]</a></span>";
		}
		else
		{
			$cells[] = '<span class="smallfont">' . $thread['postusername'] . '</span>';
		}
		$cells[] = "<span class=\"smallfont\">$thread[replycount]</span>";
		$cells[] = '<span class="smallfont">' . vbdate("$vboptions[dateformat] $vboptions[timeformat]", $thread['lastpost']) . '</span>';
		print_cells_row($cells, 0, 0, -1);
	}
	print_submit_row($vbphrase['go'], NULL, 5);

}

// ###################### Start move/prune select - finish! #######################
if ($_POST['do'] == 'dothreadsselfinish')
{
	$type = trim($_POST['type']);

	if (is_array($_POST['thread']))
	{
		if ($type == 'prune')
		{
			echo '<p><b>' . $vbphrase['deleting_threads'] . '</b>';
			foreach ($_POST['thread'] AS $threadid => $confirm)
			{
				delete_thread(intval($threadid), 0);
				echo ". \n";
				flush();
			}

			//define('CP_REDIRECT', 'thread.php?do=prune');
			print_stop_message('pruned_threads_successfully');
		}
		else if ($type == 'move')

		{
			$destforumid = intval($_POST['destforumid']);
			$threadslist = '0';
			foreach ($_POST['thread'] AS $threadid => $confirm)
			{
				$threadslist .= ', ' . intval($threadid);
			}
			$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET forumid = $destforumid WHERE threadid IN ($threadslist)");

			//define('CP_REDIRECT', 'thread.php?do=move');
			print_stop_message('moved_threads_successfully');
		}
	}
}

// **********************************************************************
// *** POLL STRIPPING SYSTEM - removes a poll from a thread *************
// **********************************************************************

// ###################### Start confirm kill poll #######################
if ($_REQUEST['do'] == 'removepoll')
{

	$threadid = intval($_REQUEST['threadid']);
	if (!$threadid)
	{
		print_stop_message('invalid_x_specified', 'threadid');
	}
	else
	{
		$thread = $DB_site->query_first("
			SELECT thread.threadid, thread.title, thread.postusername, thread.pollid, poll.question
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "poll AS poll USING (pollid)
			WHERE threadid = $threadid
				AND open <> 10 ### this is a redirect, not a poll! ###
		");
		if (!$thread['threadid'])
		{
			print_stop_message('invalid_x_specified', 'threadid');
		}
		else if (!$thread['pollid'])
		{
			print_stop_message('invalid_x_specified', 'pollid');
		}
		else
		{
			print_form_header('thread', 'doremovepoll');
			construct_hidden_code('threadid', $thread['threadid']);
			construct_hidden_code('pollid', $thread['pollid']);
			print_table_header($vbphrase['delete_poll']);
			print_label_row($vbphrase['posted_by'], "<i>$thread[postusername]</i>");
			print_label_row($vbphrase['title'], "<i>$thread[title]</i>");
			print_label_row($vbphrase['question'], "<i>$thread[question]</i>");
			print_submit_row($vbphrase['delete'], 0);
		}
	}
}

// ###################### Start do kill poll #######################
if ($_POST['do'] == 'doremovepoll')
{
	globalize($_POST, array('threadid' => INT, 'pollid' => INT));

	// check valid thread + poll
	$thread = $DB_site->query("SELECT threadid,pollid FROM " . TABLE_PREFIX . "thread WHERE threadid = $threadid AND pollid=$pollid");
	if ($DB_site->num_rows($thread))
	{

		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "pollvote WHERE pollid = $pollid");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "poll WHERE pollid = $pollid");
		$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET pollid = 0 WHERE threadid = $threadid");

		define('CP_REDIRECT', 'thread.php?do=killpoll');
		print_stop_message('deleted_poll_successfully');
	}
	else
	{
		print_stop_message('invalid_poll_specified');
	}

}

// ###################### Start kill poll #######################
if ($_REQUEST['do'] == 'killpoll')
{

	print_form_header('thread', 'removepoll');
	print_table_header($vbphrase['delete_poll']);
	print_input_row($vbphrase['enter_the_threadid_of_the_thread'], 'threadid', '', 0, 10);
	print_submit_row($vbphrase['continue'], 0);

	echo "\n\n<!-- the pun is intended ;o) -->\n\n";
}

// **********************************************************************
// *** UNSUBSCRIPTION SYSTEM - unsubscribe users from thread(s) *********
// **********************************************************************

// ############### generate id list for specified threads ####################
if ($_REQUEST['do'] == 'dospecificunsubscribe')
{

	$ids = trim($_REQUEST['ids']);
	if (empty($ids))
	{
		print_stop_message('please_complete_required_fields');
	}
	else
	{
		$threadids = preg_split('/( )+/', $ids, -1, PREG_SPLIT_NO_EMPTY);
		$ids = '';
		foreach ($threadids AS $threadid)
		{
			$ids .= intval($threadid) . ' ';
		}
		$threadids = str_replace(' ', ',', trim($ids));
		$_REQUEST['do'] = 'confirmunsubscribe';
	}

}

// ############### generate id list for mass-selected threads ####################
if ($_POST['do'] == 'domassunsubscribe')
{

	$forumid = intval($_POST['forumid']);
	if ($forumid == -1)
	{
		unset($forumid);
	}
	$daysprune = intval($_POST['daysprune']);
	$datecut = TIMENOW - (86400 * $daysprune);

	if ($_POST['username'])
	{
		if (!($userexist = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes(htmlspecialchars_uni($_POST['username'])) . "'")))
		{
			print_stop_message('invalid_user_specified');
		}
	}

	if ($forumid)
	{
		$sqlconds .= "\n" . iif(empty($sqlconds), 'WHERE', 'AND') . " forumid = $forumid";
	}
	if ($daysprune)
	{
		$sqlconds .= "\n" . iif(empty($sqlconds), 'WHERE', 'AND') . " lastpost < $datecut";
	}

	$threads = $DB_site->query("SELECT threadid FROM " . TABLE_PREFIX . "thread $sqlconds");
	if ($DB_site->num_rows($threads))
	{
		$ids = '';
		while ($thread = $DB_site->fetch_array($threads))
		{
			$ids .= "$thread[threadid] ";
		}
		$threadids = str_replace(' ', ',', trim($ids));
		$_REQUEST['do'] = 'confirmunsubscribe';
	}
	else
	{
		print_stop_message('no_threads_matched_your_query');
	}

}

// ############### generate id list for mass-selected threads ####################
if ($_REQUEST['do'] == 'confirmunsubscribe')
{

	if (!isset($threadids))
	{
		print_stop_message('please_complete_required_fields');
	}

	$threadids = addslashes($threadids);

	$sub = $DB_site->query_first("SELECT COUNT(*) AS threads
				FROM " . TABLE_PREFIX . "subscribethread
				WHERE threadid IN ($threadids) AND
					emailupdate <> 0
				" . iif($userexist['userid'], " AND userid = " . intval($userexist['userid'])) . "
				");
	if ($sub['threads'] > 0)
	{
		$idarray = array('threadids' => $threadids);
		print_form_header('thread', 'killsubscription');
		print_table_header($vbphrase['confirm_deletion']);
		if ($userexist['userid'])
		{
			$idarray['userid'] = $userexist['userid'];
			$name = htmlspecialchars_uni($_POST['username']);
		}
		else
		{
			$name = $vbphrase['all_users'];
		}
		print_description_row(construct_phrase($vbphrase['x_subscriptions_matches_found'], vb_number_format($sub['threads'])) . '<br /><br />' . $vbphrase['are_you_sure_you_want_to_delete_these_subscriptions']);
		print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);

		build_adminutil_text('subscribe', serialize($idarray));
	}
	else
	{
		print_stop_message('no_threads_matched_your_query');
	}

}

// ############### do unsubscribe threads ####################
if ($_POST['do'] == 'killsubscription')
{
	$idarray = unserialize(fetch_adminutil_text('subscribe'));
	$threadids = trim($idarray['threadids']);
	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "subscribethread
		WHERE threadid IN ($threadids) AND
			emailupdate <> 0
		" . iif($idarray['userid'], " AND userid = $idarray[userid]") . "
	");

	define('CP_REDIRECT', 'thread.php?do=unsubscribe');
	print_stop_message('deleted_subscriptions_successfully');
}

// ############### unsubscribe threads ####################
if ($_REQUEST['do'] == 'unsubscribe')
{

	print_form_header('thread', 'dospecificunsubscribe');
	print_table_header($vbphrase['unsubsribe_all_users_from_specific_threads']);
	print_textarea_row($vbphrase['enter_the_threadids_of_the_threads'], 'ids');
	print_submit_row($vbphrase['go']);

	print_form_header('thread', 'domassunsubscribe');
	print_table_header($vbphrase['unsubsribe_all_threads_from_specific_users']);
	print_input_row($vbphrase['username_leave_blank_to_remove_all'], 'username');
	print_input_row($vbphrase['find_all_threads_older_than_days'], 'daysprune', 30);
	print_forum_chooser('forumid', -1, $vbphrase['all_forums'], $vbphrase['forum']);
	print_submit_row($vbphrase['go']);

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14579 $
|| ####################################################################
\*======================================================================*/
?>