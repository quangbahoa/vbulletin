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
$nozip = 1;

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 12669 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('thread', 'threadmanage');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
log_admin_action(iif(isset($_REQUEST['forumid']), "forum id = $_REQUEST[forumid]", iif(isset($_REQUEST['pollid']), "poll id = $_REQUEST[pollid]")));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['thread_manager']);

if (!can_moderate(0, 'canmassmove') AND !can_moderate(0, 'canmassprune'))
{
	print_stop_message('no_permission');
}

// ###################### Function to generate move/prune input boxes #######################
function print_move_prune_rows($permcheck = '')
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
		construct_moderator_options('thread[forumid]', -1, $vbphrase['all_forums'], $vbphrase['forum'], true, false, true, $permcheck);
		print_yes_no_row($vbphrase['include_child_forums'], 'thread[subforums]');
}

// ###################### Function to generate move/prune query #######################
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
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . addslashes(htmlspecialchars_uni($thread['posteduser'])) . "'
		");
		if (!$user)
		{
			print_stop_message('invalid_user_specified');
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
	if ($thread['subforums'])
	{
		$query .= " AND (thread.forumid = $thread[forumid] OR forum.parentlist LIKE '%,$thread[forumid],%')";
	}
	else
	{
		$query .= " AND thread.forumid = $thread[forumid]";
	}

	return $query;
}

// ###################### Start Prune #######################
if ($_REQUEST['do'] == 'prune')
{

	if (!can_moderate(0, 'canmassprune'))
	{
		print_stop_message('no_permission');
	}

	print_form_header('', '');
	print_table_header($vbphrase['prune_threads_manager']);
	print_description_row($vbphrase['pruning_many_threads_is_a_server_intensive_process']);
	print_table_footer();

	print_form_header('thread', 'dothreads');
	construct_hidden_code('type', 'prune');
	print_move_prune_rows('canmassprune');
	print_submit_row($vbphrase['prune_threads']);
}

// ###################### Start Move #######################
if ($_REQUEST['do'] == 'move')
{

	if (!can_moderate(0, 'canmassmove'))
	{
		print_stop_message('no_permission');
	}

	print_form_header('thread', 'dothreads');
	construct_hidden_code('type', 'move');
	print_table_header($vbphrase['move_threads']);
	construct_moderator_options('destforumid', -1, '', $vbphrase['destination_forum'], false, false, true, 'canmassmove');
	print_move_prune_rows('canmassmove');
	print_submit_row($vbphrase['move_threads']);
}

// ###################### Start thread move/prune by options #######################
if ($_POST['do'] == 'dothreads')
{
	globalize($_POST, array('type' => STR, 'thread', 'destforumid' => INT));

	if ($thread['forumid'] == 0)
	{
		print_stop_message('please_complete_required_fields');
	}

	$whereclause = fetch_thread_move_prune_sql($thread);

	if ($type == 'move')
	{
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
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
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

	print_table_header(construct_phrase($vbphrase['x_thread_matches_found'], $count[count]));
	if ($type == 'prune')
	{
		print_submit_row($vbphrase['prune_all_threads'], '');
	}
	else
	{
		construct_hidden_code('destforumid', $destforumid);
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
	globalize($_POST, array('type' => STR, 'criteria', 'destforumid' => INT));

	$thread = unserialize($criteria);
	$whereclause = fetch_thread_move_prune_sql($thread);

	$fullquery = "
		SELECT thread.threadid
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE $whereclause
	";
	$threads = $DB_site->query($fullquery);

	if ($type == 'prune')
	{
		require_once('./includes/functions_databuild.php');

		echo '<p>' . $vbphrase['deleting_threads'];
		while ($thread = $DB_site->fetch_array($threads))
		{
			delete_thread($thread['threadid'], 0);
			echo ". \n";
			flush();
		}
		echo $vbphrase['done'] . '</p>';

		define('CP_REDIRECT', 'thread.php');
		print_stop_message('pruned_threads_successfully_modcp');
	}
	else if ($type == 'move')
	{
		$threadslist = '0';
		while ($thread = $DB_site->fetch_array($threads))
		{
			$threadslist .= ",$thread[threadid]";
		}
		$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET forumid = $destforumid WHERE threadid IN($threadslist)");

		define('CP_REDIRECT', 'thread.php');
		print_stop_message('moved_threads_successfully_modcp');
	}
}

// ###################### Start move/prune select #######################
if ($_POST['do'] == 'dothreadssel')
{
	globalize($_POST, array('type' => STR, 'criteria', 'destforumid' => INT));

	$thread = unserialize($criteria);
	$whereclause = fetch_thread_move_prune_sql($thread);

	$fullquery = "
		SELECT thread.*, forum.title AS forum_title
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE $whereclause
	";
	$threads = $DB_site->query($fullquery);

	print_form_header('thread', 'dothreadsselfinish');
	construct_hidden_code('type', $type);
	construct_hidden_code('destforumid', $destforumid);
	if ($type == 'prune')
	{
		print_table_header($vbphrase['prune_threads_selectively'], 5);
	}
	else if ($type == 'move')
	{
		print_table_header($vbphrase['move_threads_selectively'], 5);
	}
	print_cells_row(array(
		'<input type="checkbox" name="allbox" title="' . $vbphrase['check_all'] . '" onClick="js_check_all(this.form);" checked="checked" />',
		$vbphrase['title'],
		$vbphrase['posted_by'],
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
	if ($type == 'prune')
	{
		print_submit_row($vbphrase['prune_threads'], NULL, 5);
	}
	else if ($type == 'move')
	{
		print_submit_row($vbphrase['move_threads'], NULL, 5);
	}
}

// ###################### Start move/prune select - finish! #######################
if ($_POST['do'] == 'dothreadsselfinish')
{
	globalize($_POST, array('type' => STR, 'thread', 'destforumid' => INT));

	if (is_array($thread))
	{
		require_once('./includes/functions_databuild.php');
		if ($type == 'prune')
		{
			echo '<p>' . $vbphrase['deleting_threads'];
			foreach ($thread AS $threadid => $confirm)
			{
				delete_thread(intval($threadid), 0);
				echo ". \n";
				flush();
			}
			echo $vbphrase['done'] . '</p>';
			print_stop_message('pruned_threads_successfully_modcp');
		}
		else if ($type == 'move')

		{
			$threadslist = '0';
			foreach ($thread AS $threadid => $confirm)
			{
				$threadslist .= ', '. intval($threadid);
			}
			$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET forumid = $destforumid WHERE threadid IN($threadslist)");
			print_stop_message('moved_threads_successfully_modcp');
		}
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12669 $
|| ####################################################################
\*======================================================================*/
?>