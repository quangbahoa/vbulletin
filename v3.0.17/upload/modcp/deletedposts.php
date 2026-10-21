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
define('CVS_REVISION', '$RCSfile$ - $Revision: 11197 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('thread');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_bbcodeparse.php');

// ############################# LOG ACTION ###############################
log_admin_action(iif(isset($_REQUEST['forumid']), "forum id = $_REQUEST[forumid]", iif(isset($_REQUEST['pollid']), "poll id = $_REQUEST[pollid]")));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['view_deleted_posts']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'view';
}

// ###################### Start View #######################
if ($_REQUEST['do'] == 'view')
{
	print_form_header('deletedposts', 'doview');
	print_table_header($vbphrase['view_deleted_posts']);
	construct_moderator_options('forumid', -1, $vbphrase['all_forums'], $vbphrase['forum']);
	print_select_row($vbphrase['view'], 'view', array($vbphrase['threads'], $vbphrase['posts']));
	print_select_row($vbphrase['order_by'], 'orderby', array($vbphrase['date'], $vbphrase['user'], $vbphrase['forum']));
	print_select_row($vbphrase['order'], 'order', array($vbphrase['ascending'], $vbphrase['descending']));
	print_submit_row($vbphrase['submit']);
}

// ###################### Do View ##########################
if ($_POST['do'] == 'doview')
{
	globalize($_POST, array('view' => INT, 'orderby' => INT, 'forumid' => INT, 'order' => INT));

	if (!$forumid)
	{
		print_stop_message('please_complete_required_fields');
	}
	else if ($forumid != -1 AND !can_moderate($forumid))
	{
		print_stop_message('no_permission');
	}

	// gather forums that this person is a moderator of
	if ($forumid == -1)
	{
		$forumids = fetch_moderator_forum_list_sql();
	}
	else
	{
		$forumids = " OR thread.forumid = $forumid ";
	}

	switch($orderby)
	{
		case 0:
			$orderby = 'postdateline';
			break;
		case 1:
			$orderby = 'postusername, postdateline';
			break;
		case 2:
			$orderby = 'forumid, postdateline';
			break;
		default:
			$orderby = 'postdateline';
	}

 	$orderby .= iif($order, ' ASC', ' DESC');

	print_form_header('', '');

	if (!$view) // threads
	{
		$threads = $DB_site->query("
			SELECT thread.threadid, title, postuserid AS userid, postusername, dateline AS postdateline,
				deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason, thread.forumid
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND type = 'thread')
			WHERE (1 = 0 $forumids) AND deletionlog.primaryid IS NOT NULL
			ORDER BY $orderby
		");

		print_table_header($vbphrase['deleted_threads']);
		print_table_break();

		while($thread = $DB_site->fetch_array($threads))
		{
				print_label_row('<b>' . $vbphrase['thread'] . '</b>', construct_link_code($thread['title'], "../showthread.php?$session[sessionurl]threadid=$thread[threadid]", 1));
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', "$thread[postusername] (" . vbdate("$vboptions[dateformat] $vboptions[timeformat]", $thread['postdateline']) . ')');
				print_label_row('<b>' . $vbphrase['deleted_by'] . '</b>', $thread['del_username']);
				print_label_row('<b>' . $vbphrase['reason'] . '</b>', $thread['del_reason']);
				print_table_break();
		}
	}
	else
	{
		$posts = $DB_site->query("
			SELECT post.postid, post.title, post.userid, post.username AS postusername, post.dateline AS postdateline,
				deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason, forumid,
				thread.title AS threadtitle, post.threadid, pagetext, allowsmilie
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND type = 'post')
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE (1 = 0 $forumids) AND deletionlog.primaryid IS NOT NULL
			ORDER BY $orderby
		");

		print_table_header($vbphrase['deleted_posts']);

		while($post = $DB_site->fetch_array($posts))
		{
			print_label_row('<b>' . $vbphrase['post'] . '</b>', construct_link_code(iif($post['title'], $post['title'], $vbphrase['n_a']), "../showthread.php?$session[sessionurl]postid=$post[postid]", 1));
			print_label_row('<b>' . $vbphrase['thread'] . '</b>', construct_link_code($post['threadtitle'], "../showthread.php?$session[sessionurl]threadid=$post[threadid]", 1));
			print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', "$post[postusername] (" . vbdate("$vboptions[dateformat] $vboptions[timeformat]", $post['postdateline']) . ')');
			print_label_row('<b>' . $vbphrase['deleted_by'] . '</b>', $post['del_username']);
			print_label_row('<b>' . $vbphrase['reason'] . '</b>', $post['del_reason']);
			print_label_row('<b>' . $vbphrase['post'] . '</b>', htmlspecialchars_uni($post['pagetext']));
			print_table_break();
		}
	}

	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11197 $
|| ####################################################################
\*======================================================================*/
?>