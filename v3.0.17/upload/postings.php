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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'postings');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('threadmanage');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'THREADADMIN',
	'threadadmin_postbit'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'editthread' => array(
		'threadadmin_editthread',
		'threadadmin_logbit',
		'posticonbit',
		'posticons'
	),
	'deleteposts' => array('threadadmin_deleteposts'),
	'deletethread' => array('threadadmin_deletethread'),
	'managepost' => array('threadadmin_managepost'),
	'mergethread' => array('threadadmin_mergethread'),
	'movethread' => array('threadadmin_movethread'),
	'splitthread' => array('threadadmin_splitthread'),
);

// ####################### PRE-BACK-END ACTIONS ##########################
require_once('./global.php');
require_once('./includes/functions_threadmanage.php');
require_once('./includes/functions_databuild.php');
require_once('./includes/functions_log_error.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ###################### Start makepostingsnav #######################
// shortcut function to make $navbits for navbar
function construct_postings_nav($foruminfo, $threadinfo)
{
	global $session, $forumcache, $vbphrase;
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $forumcache["$forumID"]['title'];
		$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
	}
	$navbits["showthread.php?$session[sessionurl]t=$threadinfo[threadid]"] = $threadinfo['title'];

	switch ($_REQUEST['do'])
	{
		case 'movethread':   $navbits[''] = $vbphrase['move_copy_thread']; break;
		case 'editthread':   $navbits[''] = $vbphrase['edit_thread']; break;
		case 'deletethread': $navbits[''] = $vbphrase['delete_thread']; break;
		case 'deleteposts':  $navbits[''] = $vbphrase['delete_posts']; break;
		case 'mergethread':  $navbits[''] = $vbphrase['merge_threads']; break;
		case 'splitthread':  $navbits[''] = $vbphrase['split_thread']; break;
	}

	return construct_navbits($navbits);
}

$idname = $vbphrase['thread'];

switch ($_REQUEST['do'])
{
	case 'openclosethread':
	case 'dodeletethread':
	case 'dodeleteposts':
	case 'domovethread':
	case 'updatethread':
	case 'domergethread':
	case 'dosplitthread':
	case 'stick':
	case 'removeredirect':

		$threadid = verify_id('thread', $_POST['threadid']);
		break;

	case 'deletethread':
	case 'deleteposts':
	case 'movethread':
	case 'editthread':
	case 'mergethread':
	case 'splitthread':

		$threadid = verify_id('thread', $_REQUEST['threadid']);
		break;

	case 'domanagepost':

		$postid = verify_id('post', $_POST['postid']);
		break;

	case 'getip':
	case 'managepost':

		$postid = verify_id('post', $_REQUEST['postid']);
		break;

	case 'editpoll':

		exec_header_redirect("poll.php?$session[sessionurl]do=polledit&pollid=" . intval($_REQUEST['pollid']));

	default: // throw and error about invalid $_REQUEST['do']
		eval(print_standard_error('error_invalid_action'));

}

// ensure that thread notes are run through htmlspecialchars
if (is_array($threadinfo))
{
	$threadinfo['notes'] = htmlspecialchars_uni($threadinfo['notes']);
}

$show['softdelete'] = iif(can_moderate($threadinfo['forumid'], 'candeleteposts'), true, false);
$show['harddelete'] = iif(can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);

// ############################### start do open / close thread ###############################
if ($_POST['do'] == 'openclosethread')
{
	// permission check
	if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & CANVIEW) OR !($forumperms & CANOPENCLOSE))
		{
			print_no_permission();
		}
		else
		{
			if (!is_first_poster($threadid))
			{
				print_no_permission();
			}
		}
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	if ($threadinfo['open'])
	{
		$threadinfo['open'] = 0;
		$logaction = $vbphrase['closed_thread'];
		$action = $vbphrase['closed'];
	}
	else
	{
		$threadinfo['open'] = 1;
		$logaction = $vbphrase['opened_thread'];
		$action = $vbphrase['opened'];
	}

	log_moderator_action($threadinfo, $logaction);

	$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET open = $threadinfo[open] WHERE threadid = $threadid");

	$_REQUEST['forceredirect'] = 1;
	$url = "showthread.php?$session[sessionurl]t=$threadid";
	eval(print_standard_redirect('redirect_openclose'));

}

// ############################### start delete thread ###############################
if ($_REQUEST['do'] == 'deletethread')
{
	$templatename = 'threadadmin_deletethread';

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	// permission check
	if (!can_moderate($threadinfo['forumid'], 'candeleteposts') AND !can_moderate($threadinfo['forumid'], 'canremoveposts'))
	{
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & CANVIEW) OR !($forumperms & CANDELETEPOST) OR !($forumperms & CANDELETETHREAD))
		{
			print_no_permission();
		}
		else
		{
			if (!$threadinfo['open'])
			{
				$url = "showthread.php?$session[sessionurl]t=$threadid";
				eval(print_standard_redirect('redirect_threadclosed'));
			}
			// make sure this thread is owned by the user trying to delete it
			if (!is_first_poster($threadid))
			{
				print_no_permission();
			}
		}
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);

}

// ############################### start do delete thread ###############################
if ($_POST['do'] == 'dodeletethread')
{

	globalize($_POST, array(
		'deletetype' => INT, 	// 1=leave message; 2=removal
		'deletereason' => STR,
		'keepattachments' => INT
		)
	);

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	$physicaldel = 0;
	if (!can_moderate($threadinfo['forumid'], 'candeleteposts') AND !can_moderate($threadinfo['forumid'], 'canremoveposts'))
	{
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & CANVIEW) OR !($forumperms & CANDELETEPOST) OR !($forumperms & CANDELETETHREAD))
		{
			print_no_permission();
		}
		else
		{
			if (!$threadinfo['open'])
			{
				$url = "showthread.php?$session[sessionurl]t=$threadid";
				eval(print_standard_redirect('redirect_threadclosed'));
			}
			if (!is_first_poster($threadid))
			{
				print_no_permission();
			}
		}
	}
	else
	{
		if (!can_moderate($threadinfo['forumid'], 'canremoveposts'))
		{
			$physicaldel = 0;
		}
		else if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
		{
			$physicaldel = 1;
		}
		else
		{
			$physicaldel = iif($deletetype == 1, 0, 1);
		}
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$delinfo = array('userid' => $bbuserinfo['userid'], 'username' => $bbuserinfo['username'], 'reason' => $deletereason, 'keepattachments' => $keepattachments);
	delete_thread($threadid, $foruminfo['countposts'], $physicaldel, $delinfo);
	build_forum_counters($threadinfo['forumid']);

	$url = "forumdisplay.php?$session[sessionurl]f=$threadinfo[forumid]";
	eval(print_standard_redirect('redirect_deletethread'));

}

// ############################### start delete posts ###############################
if ($_REQUEST['do'] == 'deleteposts')
{
	$templatename = 'threadadmin_deleteposts';

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		print_no_permission();
	}

	if ($bbuserinfo['threadedmode'])
	{
		$show['children'] = true;
	}

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);

	$show['deleteposts'] = true;

	$postbits = construct_post_tree('threadadmin_postbit', $threadid);
	$parentpostassoc = construct_js_post_parent_assoc($parentassoc);

}

// ############################### start do delete posts ###############################
if ($_POST['do'] == 'dodeleteposts')
{

	globalize($_POST, array('type' => INT, 'checkbox', 'keepattachments' => INT, 'checkpost', 'deletereason' => STR));

	if (!can_moderate($threadinfo['forumid'], 'canremoveposts'))
	{
		$type = 0;
	}
	else
	{
		$type = iif($type == 1, 1, 0);
	}

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		print_no_permission();
	}

	$deletepost = $checkpost;
	$deletethread = 1;

	// deletion is done this way for validation purposes
	$posts = $DB_site->query("SELECT postid, parentid FROM " . TABLE_PREFIX . "post WHERE threadid = $threadid");
	while ($post = $DB_site->fetch_array($posts))
	{
		if ($deletepost["$post[postid]"] == 'yes')
		{
			if ($post['parentid'] == 0)
			{ // first post is getting deleted; need to update the new first post's title

				if ($firstchild = $DB_site->query_first("
					SELECT postid
					FROM " . TABLE_PREFIX . "post
					WHERE threadid = $threadid AND
						parentid = $post[postid]
					ORDER BY dateline LIMIT 1
				"))
				{
					$DB_site->query("
						UPDATE " . TABLE_PREFIX . "post
						SET title = '" . addslashes($threadinfo['title']) . "'
						WHERE postid = $firstchild[postid]");
				}
			}
			delete_post($post['postid'], $foruminfo['countposts'], $threadid, $type, array('userid' => $bbuserinfo['userid'], 'username' => $bbuserinfo['username'], 'reason' => $deletereason, 'keepattachments' => $keepattachments));
		}
		else
		{
			$deletethread = 0;
		}
	}

	if ($deletethread)
	{
		delete_thread($threadid, $foruminfo['countposts'], $type);
	}
	else
	{
		build_thread_counters($threadid);
	}
	build_forum_counters($threadinfo['forumid']);

	if ($deletethread)
	{
		$url = "forumdisplay.php?$session[sessionurl]f=$threadinfo[forumid]";
	}
	else
	{
		$url = "showthread.php?$session[sessionurl]t=$threadid";
	}
	eval(print_standard_redirect('redirect_deleteposts'));

}

// ############################### start retrieve ip ###############################
if ($_REQUEST['do'] == 'getip')
{
	// check moderator permissions for getting ip
	if (!can_moderate($threadinfo['forumid'], 'canviewips'))
	{
		print_no_permission();
	}

	$postinfo['hostaddress'] = @gethostbyaddr($postinfo['ipaddress']);

	eval(print_standard_error('thread_displayip', 1, 0));
}

// ############################### start move thread ###############################
if ($_REQUEST['do'] == 'movethread')
{
	$templatename = 'threadadmin_movethread';

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	// check forum permissions for this forum
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$forumperms = fetch_permissions($forumid);
		if (!($forumperms & CANVIEW) OR !($forumperms & CANMOVE))
		{
			print_no_permission();
		}
		else
		{
			if (!$threadinfo['open'] AND !($forumperms & CANOPENCLOSE))
			{
				$_REQUEST['forceredirect'] = 1;
				$url = "showthread.php?$session[sessionurl]t=$threadid";
				eval(print_standard_redirect('redirect_threadclosed'));
			}
			if (!is_first_poster($threadid))
			{
				print_no_permission();
			}
		}
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$title = &$threadinfo['title'];

	$curforumid = $threadinfo['forumid'];
	$moveforumbits = construct_move_forums_options();

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);
}

// ############################### start do move thread ###############################
if ($_POST['do'] == 'domovethread')
{
	globalize($_POST, array(
		'threadid' => INT,
		'method' => STR,
		'title' => STR
	));

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	// check whether dest can contain posts
	$destforumid = verify_id('forum', $_POST['forumid']);
	$destforuminfo = fetch_foruminfo($destforumid);
	if (!$destforuminfo['cancontainthreads'] OR $destforuminfo['link'])
	{
		eval(print_standard_error('error_moveillegalforum'));
	}

	// check source forum permissions
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & CANVIEW) OR !($forumperms & CANMOVE))
		{
			print_no_permission();
		}
		else
		{
			if (!$threadinfo['open'] AND !($forumperms & CANOPENCLOSE))
			{
				$_REQUEST['forceredirect'] = 1;
				$url = "showthread.php?$session[sessionurl]t=$threadid";
				eval(print_standard_redirect('redirect_threadclosed'));
			}
			if (!is_first_poster($threadid))
			{
				print_no_permission();
			}
		}
	}

	// check destination forum permissions
	$forumperms = fetch_permissions($destforuminfo['forumid']);
	if (!($forumperms & CANVIEW))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($destforuminfo['forumid'], $destforuminfo['password']);

	// check to see if this thread is being returned to a forum it's already been in
	// if a redirect exists already in the destination forum, remove it
	if ($checkprevious = $DB_site->query_first("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE forumid = $destforuminfo[forumid] AND open = 10 AND pollid = $threadid"))
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "thread WHERE threadid = " . intval($checkprevious['threadid']));
	}

	// get a valid method variable, set default to move with redirect if not specified
	switch($method)
	{
		case 'copy':
		case 'move':
		case 'movered':
			break;

		default:
			$method = 'movered';
	}

	// check to see if this thread is being moved to the same forum it's already in but allow copying to the same forum
	if ($destforuminfo['forumid'] == $threadinfo['forumid'] AND $method  != 'copy')
	{
		eval(print_standard_error('error_movesameforum'));
	}

	$title = htmlspecialchars_uni($title);
	if (!empty($title) AND $title != $threadinfo['title'])
	{
		$oldtitle = $threadinfo['title'];
		$threadinfo['title'] = $title;
		$updatetitle = true;
	}
	else
	{
		$oldtitle = $threadinfo['title'];
		$updatetitle = false;
	}

	switch($method)
	{
		// ***************************************************************
		// move the thread wholesale into the destination forum
		case 'move':

			log_moderator_action($threadinfo, construct_phrase($vbphrase['thread_moved_to_x'], $destforuminfo['title']));

			// update forumid/notes and unstick to prevent abuse
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "thread SET
					title = '" . addslashes($threadinfo['title']) . "',
					forumid = " . intval($destforuminfo['forumid']) . ",
					sticky = 0
				WHERE threadid = $threadid
			");

			break;
		// ***************************************************************


		// ***************************************************************
		// move the thread into the destination forum and leave a redirect
		case 'movered':

			log_moderator_action($threadinfo, construct_phrase($vbphrase['thread_moved_with_redirect_to_a'], $destforuminfo['title']));

			$DB_site->query("
				INSERT INTO " . TABLE_PREFIX . "thread(
					threadid, title, lastpost, forumid, pollid, open, replycount, postusername, postuserid, lastposter, dateline, views, iconid, visible
				) VALUES (
					NULL,
					'" . addslashes($threadinfo['title']) . "',
					" . intval($threadinfo['lastpost']) . ",
					" . intval($threadinfo['forumid']) . ",
					" . intval($threadinfo['threadid']) . ",
					10,
					" . intval($threadinfo['replycount']) . ",
					'" . addslashes($threadinfo['postusername']) . "',
					" . intval($threadinfo['postuserid']) . ",
					'" . addslashes($threadinfo['lastposter']) . "',
					" . intval($threadinfo['dateline']) . ",
					" . intval($threadinfo['views']) . ",
					" . intval($threadinfo['iconid']) . ",
					" . intval($threadinfo['visible']) . "
				)
			");

			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "thread SET
					title = '" . addslashes($threadinfo['title']) . "',
					forumid = " . intval($destforuminfo['forumid']) . "
				WHERE threadid = $threadid
			");

			break;
		// ***************************************************************


		// ***************************************************************
		// make a copy of the thread in the redirect forum
		case 'copy':

			log_moderator_action($threadinfo, construct_phrase($vbphrase['thread_copied_to_x'], $destforuminfo['title']));

			if ($threadinfo['pollid'] AND $threadinfo['open'] != 10)
			{
				// We have a poll, need to duplicate it!
				if ($pollinfo = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "poll WHERE pollid = $threadinfo[pollid]"))
				{
					$DB_site->query("
						INSERT INTO " . TABLE_PREFIX . "poll
							(question,dateline,options,votes,active,numberoptions,timeout,multiple)
						VALUES
							('" . addslashes($pollinfo['question']) . "', $pollinfo[dateline],
							 '" . addslashes($pollinfo['options']) . "', '" . addslashes($pollinfo['votes']) . "',
							 $pollinfo[active], $pollinfo[numberoptions], $pollinfo[timeout], $pollinfo[multiple])
					");
					$oldpollid = $threadinfo['pollid'];
					$threadinfo['pollid'] = $DB_site->insert_id();

					$pollvotes = $DB_site->query("SELECT userid, votedate, voteoption FROM " . TABLE_PREFIX . "pollvote WHERE pollid = $oldpollid");
					$insertsql = '';
					while ($pollvote = $DB_site->fetch_array($pollvotes))
					{
						if ($insertsql)
						{
							$insertsql .= ',';
						}
						$insertsql .=  "($threadinfo[pollid], $pollvote[userid], $pollvote[votedate], $pollvote[voteoption])";
					}
					if ($insertsql)
					{
						$DB_site->query("INSERT INTO " . TABLE_PREFIX . "pollvote (pollid, userid, votedate, voteoption) VALUES $insertsql");
					}
				}
			}

			// duplicate thread, save a few columns
			$newthreadinfo = $threadinfo;
			unset($newthreadinfo['vote'], $newthreadinfo['threadid'], $newthreadinfo['sticky'], $newthreadinfo['votenum'], $newthreadinfo['votetotal'], $newthreadinfo['isdeleted'], $newthreadinfo['del_userid'], $newthreadinfo['del_username'], $newthreadinfo['del_reason'], $newthreadinfo['issubscribed'], $newthreadinfo['emailupdate'], $newthreadinfo['folderid']);
			$newthreadinfo['forumid'] = $destforuminfo['forumid'];
			$DB_site->query(fetch_query_sql($newthreadinfo, 'thread'));
			$newthreadid = $DB_site->insert_id();

			require_once('./includes/functions_file.php');

			// duplicate posts
			$posts = $DB_site->query("
				SELECT post.*,
					deletionlog.userid AS deleteduserid, deletionlog.username AS deletedusername, deletionlog.reason AS deletedreason,
					NOT ISNULL(deletionlog.primaryid) AS isdeleted
				FROM " . TABLE_PREFIX . "post AS post
				LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
				WHERE threadid = $threadid
				ORDER BY dateline
			");
			$firstpost = false;
			$userbyuserid = array();
			$postarray = array();
			while ($post = $DB_site->fetch_array($posts))
			{
				if ($post['title'] == $oldtitle AND $updatetitle)
				{
					$post['title'] = $threadinfo['title'];
					$update_post_title = true;
				}
				else
				{
					$update_post_title = false;
				}

				$oldpostid = $post['postid'];

				if ($post['isdeleted'])
				{
					$deleteinfo = array(
						'isdeleted' => true,
						'deleteduserid' => intval($post['deleteduserid']),
						'deletedusername' => $post['deletedusername'],
						'deletedreason' => $post['deletedreason'],
					);
				}
				else
				{
					$deleteinfo = array(
						'isdeleted' => false
					);
				}

				// unset these fields so that fetch_query_sql() doesn't try to insert them into post
				unset($post['postid'], $post['deleteduserid'], $post['deletedusername'], $post['deletedreason'], $post['isdeleted']);

				$post['threadid'] = $newthreadid;
				$DB_site->query(fetch_query_sql($post, 'post'));
				$newpostid = $DB_site->insert_id();

				if (!$firstpost)
				{
					$DB_site->query("
						UPDATE " . TABLE_PREFIX . "thread
						SET firstpostid = $newpostid
						WHERE threadid = $newthreadid
					");
					$firstpost = true;
				}

				// Duplicate any deleted posts into the deletionlog
				if ($deleteinfo['isdeleted'])
				{
					$deletedpostsql .= "$comma($newpostid, 'post', $deleteinfo[deleteduserid], '" . addslashes($deleteinfo['deletedusername']) . "', '" . addslashes($deleteinfo['deletedreason']) . "')";
					$comma = ',';
				}

				$attachments = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "attachment WHERE postid = $oldpostid");
				while ($attachment = $DB_site->fetch_array($attachments))
				{
					$DB_site->query("
						INSERT INTO " . TABLE_PREFIX . "attachment (
							userid, dateline, filename, filedata, visible, counter, filesize, filehash, postid, thumbnail, thumbnail_filesize
						) VALUES (
							$attachment[userid],
							$attachment[dateline],
							'" . addslashes($attachment['filename']) . "',
							'" . $DB_site->escape_string($attachment['filedata']) . "',
							$attachment[visible],
							0,
							$attachment[filesize],
							'" . addslashes($attachment['filehash']) . "',
							$newpostid,
							'" . $DB_site->escape_string($attachment['thumbnail']) . "',
							$attachment[thumbnail_filesize]
						)
					");
					$newattachmentid = $DB_site->insert_id();
					if ($vboptions['attachfile'])
					{
						copy(fetch_attachment_path($attachment['userid'], $attachment['attachmentid']), fetch_attachment_path($attachment['userid'], $newattachmentid));
						// Copy thumbnail
						@copy(fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], true), fetch_attachment_path($attachment['userid'], $newattachmentid, true));
					}
				}

				$parentcasesql .= " WHEN parentid = $oldpostid THEN $newpostid";
				$parentids .= ",$oldpostid";

				// Source forum doesn't indexposts so we must generate these new words
				if (!$foruminfo['indexposts'] AND $destforuminfo['indexposts'])
				{
					build_post_index($newpostid, $destforuminfo);
				}
				else if ($foruminfo['indexposts'] AND $destforuminfo['indexposts'])
				{
					if ($update_post_title == true)
					{
						// we have a new title for this post, so it needs to be reindexed
						build_post_index($newpostid, $destforuminfo);
					}
					else
					{
						// Source forum indexes posts so we can duplicate the words we already have
						$postarray["$oldpostid"] = $newpostid;
					}
				}

				if ($destforuminfo['countposts'] AND $post['userid'])
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
			}

			// Duplicate word entries in the postindex
			if (!empty($postarray) AND $vboptions['copypostindex'])
			{
				$DB_site->query("CREATE TABLE " . TABLE_PREFIX . "postindex_temp$newthreadid (
					wordid INT UNSIGNED NOT NULL DEFAULT '0',
					postid INT UNSIGNED NOT NULL DEFAULT '0',
					intitle SMALLINT UNSIGNED NOT NULL DEFAULT '0',
					score SMALLINT UNSIGNED NOT NULL DEFAULT '0'
				)"); // indexes left off intentionally

				$postcase = '';
				foreach ($postarray AS $oldid => $newid)
				{
					$postcase .= "WHEN $oldid THEN $newid\n";
				}

				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "postindex_temp$newthreadid
						(wordid, postid, intitle, score)
					SELECT wordid, CASE postid $postcase ELSE postid END AS postid,
						intitle, score
						FROM " . TABLE_PREFIX . "postindex AS postindex
						WHERE postid IN (" . implode(',', array_keys($postarray)) . ")
				");

				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "postindex
						(wordid, postid, intitle, score)
					SELECT wordid, postid, intitle, score FROM " . TABLE_PREFIX . "postindex_temp$newthreadid
				");

				$DB_site->query("DROP TABLE IF EXISTS " . TABLE_PREFIX . "postindex_temp$newthreadid");
			}

			// Insert Deleted Posts
			if ($deletedpostsql)
			{
				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "deletionlog
					(primaryid, type, userid, username, reason)
					VALUES
					$deletedpostsql
				");
			}

			// reconnect parent/child posts in the new thread
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "post
				SET parentid =
				CASE
					$parentcasesql
					ELSE parentid
				END
				WHERE threadid = $newthreadid AND
					parentid IN (0$parentids)
			");

			// Update User Post Counts
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
					$postcasesql .= " WHEN userid IN (0$userids) THEN $postcount";
				}

				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "user
					SET posts = posts +
					CASE
						$postcasesql
						ELSE 0
					END
					WHERE userid IN (0$alluserids)
				");
			}

			break;
		// ***************************************************************

	} // end switch($method)

	// Update Post Count if we move from a counting forum to a non counting or vice-versa..
	if (($method == 'move' OR $method == 'movered') AND (($foruminfo['countposts'] AND !$destforuminfo['countposts']) OR (!$foruminfo['countposts'] AND $destforuminfo['countposts'])))
	{
		$posts = $DB_site->query("
			SELECT userid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadinfo[threadid] AND
				userid <> 0
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

			$operator = iif($destforuminfo['countposts'], '+', '-');

			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "user
				SET posts = posts $operator
				CASE
					$casesql
					ELSE 0
				END
				WHERE userid IN (0$alluserids)
			");
		}
	}

	if ($updatetitle)
	{
		// Reindex first post to set up title properly.
		$getfirstpost = $DB_site->query_first("
			SELECT postid, title, pagetext
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadid
			ORDER BY dateline
			LIMIT 1
		");
		delete_post_index($getfirstpost['postid'], $getfirstpost['title'], $getfirstpost['pagetext']);
		build_post_index($getfirstpost['postid'] , $foruminfo);
	}

	build_forum_counters($threadinfo['forumid']);
	if ($threadinfo['forumid'] != $destforuminfo['forumid'])
	{
		build_forum_counters($destforuminfo['forumid']);
	}

	// unsubscribe users who can't view the forum the thread is now in
	$users = $DB_site->query("
		SELECT user.userid, usergroupid, membergroupids, (options & $_USEROPTIONS[hasaccessmask]) AS hasaccessmask
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread, " . TABLE_PREFIX . "user AS user
		WHERE subscribethread.userid = user.userid AND subscribethread.threadid = $threadid
	");
	$deleteuser = '0';
	while ($thisuser = $DB_site->fetch_array($users))
	{
		$userperms = fetch_permissions($destforuminfo['forumid'], $thisuser['userid'], $thisuser);
		if (($userperms & CANVIEW) AND ($threadinfo['postuserid'] == $thisuser['userid'] OR ($userperms & CANVIEWOTHERS)))
		{
			// don't delete
			continue;
		}
		else

		{
			$deleteuser .=  ',' . $thisuser['userid'];
		}
	}

	if ($deleteuser)
	{
		$query = "DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE threadid = $threadid AND userid IN ($deleteuser)";
		$DB_site->query($query);
	}

	if ($method == 'copy' AND $newthreadid)
	{
		$threadid = $newthreadid;
	}
	$url = "showthread.php?$session[sessionurl]t=$threadid";
	eval(print_standard_redirect('redirect_movethread'));
}

// ############################### start edit thread ###############################
if ($_REQUEST['do'] == 'editthread')
{
	$templatename = 'threadadmin_editthread';

	// only mods with the correct permissions should be able to access this
	if (!can_moderate($threadinfo['forumid'], 'caneditthreads'))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$show['undeleteoption'] = iif($threadinfo['isdeleted'] == 1 AND (can_moderate($threadinfo['forumid'], 'canremoveposts') OR can_moderate($threadinfo['forumid'], 'candeleteposts')) AND can_moderate($threadinfo['forumid'], 'canmanagethreads'), true, false);
	$show['removeoption'] = iif($show['undeleteoption'] AND can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);

	$visiblechecked = iif($threadinfo['visible'], HTML_CHECKED);
	$visiblehidden = iif($threadinfo['visible'], 'yes');
	$openchecked = iif($threadinfo['open'], HTML_CHECKED);
	$stickychecked = iif($threadinfo['sticky'], HTML_CHECKED);

	require_once('./includes/functions_newpost.php');
	$posticons = construct_icons($threadinfo['iconid'], $foruminfo['allowicons']);

	$logs = $DB_site->query("
		SELECT moderatorlog.dateline, moderatorlog.userid, moderatorlog.action,
			user.username,
			post.postid, post.title
		FROM " . TABLE_PREFIX . "moderatorlog AS moderatorlog
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderatorlog.userid)
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (moderatorlog.postid = post.postid)
		WHERE moderatorlog.threadid = $threadid
		ORDER BY dateline
	");

	while ($log = $DB_site->fetch_array($logs))
	{
		exec_switch_bg();
		$log['dateline'] = vbdate($vboptions['logdateformat'], $log['dateline']);
		eval('$logbits .= "' . fetch_template('threadadmin_logbit') . '";');
	}
	$show['modlog'] = iif($logbits, true, false);

}

// ############################### start update thread ###############################
if ($_POST['do'] == 'updatethread')
{
	globalize($_POST, array(
		'threadid' => INT,
		'visible' => STR,
		'open' => STR,
		'sticky' => STR,
		'iconid' => INT,
		'notes' => STR_NOHTML,
		'threadstatus' => INT,
		'reason' => STR,
		'title' => STR
	));

	// only mods with the correct permissions should be able to access this
	if (!can_moderate($threadinfo['forumid'], 'caneditthreads'))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	if ($title == '')
	{
		eval(print_standard_error('error_notitle'));
	}

	$visible = iif($visible == 'yes', 1, 0);
	$open = iif($open == 'yes', 1, 0);
	$sticky = iif($sticky == 'yes', 1, 0);

	if (!can_moderate($threadinfo['forumid'], 'canopenclose') AND !$forumperms['canopenclose'])
	{
		$open = $threadinfo['open'];
	}

	if ($threadstatus == 1 AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{ // undelete
		undelete_thread($threadinfo['threadid'], $foruminfo['countposts']);
		$threaddeleted = -1;
	}
	else if ($threadstatus == 2 AND can_moderate($threadinfo['forumid'], 'canremoveposts'))
	{ // remove
		delete_thread($threadinfo['threadid'], $foruminfo['countposts'], 1);
		$threaddeleted = 1;
	}
	else
	{
		$threaddeleted = 0;
	}

	if ($threaddeleted != 1)
	{
		// Reindex first post to set up title properly.
		$getfirstpost = $DB_site->query_first("
			SELECT postid, title, pagetext
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadid
			ORDER BY dateline
			LIMIT 1
		");
		$getfirstpost['threadtitle'] = htmlspecialchars_uni($title);
		delete_post_index($getfirstpost['postid'], $getfirstpost['title'], $getfirstpost['pagetext']);
		build_post_index($getfirstpost['postid'] , $foruminfo, 1, $getfirstpost);

		if (!empty($reason))
		{
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "deletionlog SET
					reason = '" . addslashes(htmlspecialchars_uni($reason)) . "'
				WHERE primaryid = $threadid AND type = 'thread'
			");
		}

		if ($vboptions['similarthreadsearch'])
		{
			require_once('./includes/functions_search.php');
			$similarthreads = ",similar = '" . addslashes(fetch_similar_threads($title, $threadid)) . "'";
		}
		else
		{
			$similarthreads = '';
		}


		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "thread SET
				visible = $visible,
				open = $open,
				sticky = $sticky ,
				title = '" . addslashes(htmlspecialchars_uni($title)) . "',
				iconid = $iconid,
				notes = '". addslashes($notes) ."'
				$similarthreads
			WHERE threadid = $threadid
		");
	}

	build_forum_counters($threadinfo['forumid']);

	log_moderator_action($threadinfo, construct_phrase($vbphrase['thread_edited_visible_x_open_y_sticky_z'], $visible, $open, $sticky));

	if (!$visible)
	{
		if (!$getfirstpost['postid'])
		{
			$getfirstpost = $DB_site->query_first("
				SELECT postid
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = $threadid
				ORDER BY dateline ASC
			");
		}
		// Make sure we do have a firstpost (were we deleted during this edit?)
		if ($getfirstpost['postid'])
		{
			$DB_site->query("REPLACE INTO " . TABLE_PREFIX . "moderation (threadid, postid, type) VALUES ($threadid, $getfirstpost[postid], 'thread')");
		}
	}

	if (!$getfirstpost['postid'])
	{
		$getfirstpost = $DB_site->query_first("
			SELECT postid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadid
			ORDER BY dateline ASC
		");
	}
	// Make sure we do have a firstpost (were we deleted during this edit?)
	if ($getfirstpost['postid'])
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "post SET
				title = '" . addslashes(htmlspecialchars_uni($title)) . "',
				iconid = $iconid
			WHERE postid = $getfirstpost[postid]
		");
	}

	if (!$visible OR $threaddeleted == 1 OR ($threadinfo['isdeleted'] AND $threaddeleted != -1))
	{
		$url = "forumdisplay.php?$session[sessionurl]f=$threadinfo[forumid]";
	}
	else
	{
		$url = "showthread.php?$session[sessionurl]t=$threadid";
	}
	eval(print_standard_redirect('redirect_editthread'));
}

// ############################### start merge threads ###############################
if ($_REQUEST['do'] == 'mergethread')
{
	$templatename = 'threadadmin_mergethread';

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	// check forum permissions for this forum
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);
}

// ############################### start do merge threads ###############################
if ($_POST['do'] == 'domergethread')
{

	globalize($_POST, array('mergethreadurl' => STR, 'title' => STR));

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	// check forum permissions for this forum
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	// relative URLs will do bad things here, so don't let them through; thanks Paul! :)
	if (stristr($mergethreadurl, 'goto=next'))
	{
		eval(print_standard_error('error_mergebadurl'));
	}

	// eliminate everything but the query string
	if ($strpos = strpos($mergethreadurl, '?'))
	{
		$mergethreadurl = substr($mergethreadurl, $strpos);
	}
	else
	{
		eval(print_standard_error('error_mergebadurl'));
	}

	// pull out the thread/postid
	if (preg_match('#(threadid|t)=([0-9]+)#', $mergethreadurl, $matches))
	{
		$mergethreadid = intval($matches[2]);
	}
	else if (preg_match('#(postid|p)=([0-9]+)#', $mergethreadurl, $matches))
	{
		$mergepostid = verify_id('post', $matches[2], 0);
		if ($mergepostid == 0)
		{
			// do invalid url
			eval(print_standard_error('error_mergebadurl'));
		}

		$postinfo = fetch_postinfo($mergepostid);
		$mergethreadid = $postinfo['threadid'];
	}
	else
	{
		eval(print_standard_error('error_mergebadurl'));
	}

	$mergethreadid = verify_id('thread', $mergethreadid);
	$mergethreadinfo = fetch_threadinfo($mergethreadid);
	$mergeforuminfo = fetch_foruminfo($mergethreadinfo['forumid']);

	if (!$mergethreadinfo['visible'] OR $mergethreadinfo['isdeleted'] OR $mergethreadid == $threadid)
	{
		eval(print_standard_error('error_invalidid'));
	}

	// check forum permissions for the merge forum
	$mergeforumperms = fetch_permissions($mergethreadinfo['forumid']);
	if (!($mergeforumperms & CANVIEW) OR !can_moderate($mergethreadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($mergeforuminfo['forumid'], $mergeforuminfo['password']);

	// get the first post from each thread -- we only need to reindex those
	$thrd_firstpost = $DB_site->query_first("
		SELECT postid, dateline
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = $threadinfo[threadid]
		ORDER BY dateline ASC
	");
	$mrgthrd_firstpost = $DB_site->query_first("
		SELECT postid, dateline
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = $mergethreadinfo[threadid]
		ORDER BY dateline ASC
	");

	// sort out polls
	$pollcode = '';
	if ($mergethreadinfo['pollid'] != 0)
	{ // merge thread has poll ...
		if ($threadinfo['pollid'] == 0)
		{ // ... and original thread doesn't
			$pollcode = ',pollid = ' . $mergethreadinfo['pollid'];
		}
		else
		{ // ... and original does
			// if the poll isn't found anywhere else, delete the merge thread's poll
			if (!$poll = $DB_site->query_first("
				SELECT threadid
				FROM " . TABLE_PREFIX . "thread
				WHERE pollid = $mergethreadinfo[pollid] AND
					threadid <> $mergethreadinfo[threadid]
				"))
			{
				$DB_site->query("DELETE FROM " . TABLE_PREFIX . "poll WHERE pollid = $mergethreadinfo[pollid]");
				$DB_site->query("DELETE FROM " . TABLE_PREFIX . "pollvote WHERE pollid = $mergethreadinfo[pollid]");
			}
		}
	}

	// move posts
	$DB_site->query("UPDATE " . TABLE_PREFIX . "post SET threadid = $threadid WHERE threadid = $mergethreadid");


	// update first post relationships
	if ($thrd_firstpost['dateline'] > $mrgthrd_firstpost['dateline'])
	{
		// thread being merged into is newer, so the merged thread's first post should become this thread's first post
		$DB_site->query("UPDATE " . TABLE_PREFIX . "post SET parentid = $mrgthrd_firstpost[postid] WHERE postid = $thrd_firstpost[postid]");
	}
	else
	{
		$DB_site->query("UPDATE " . TABLE_PREFIX . "post SET parentid = $thrd_firstpost[postid] WHERE postid = $mrgthrd_firstpost[postid]");
	}

	$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET title = '" . addslashes(htmlspecialchars_uni($title)) . "'$pollcode WHERE threadid = $threadid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "thread WHERE threadid = $mergethreadid");
	$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET pollid = $threadid WHERE open = 10 AND pollid = $mergethreadid"); // update redirects
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "threadrate WHERE threadid = $mergethreadid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE threadid = $mergethreadid");

	// update postindex for the 2 posts who's titles may have changed (first post of each thread)
	delete_post_index($thrd_firstpost['postid']);
	delete_post_index($mrgthrd_firstpost['postid']);
	build_post_index($thrd_firstpost['postid'] , $foruminfo);
	build_post_index($mrgthrd_firstpost['postid'] , $foruminfo);

	build_thread_counters($threadid);
	build_forum_counters($threadinfo['forumid']);
	if ($mergethreadinfo['forumid'] != $threadinfo['forumid'])
	{
		build_forum_counters($mergethreadinfo['forumid']);
	}

	log_moderator_action($threadinfo, construct_phrase($vbphrase['thread_merged_with_x'], $mergethreadinfo['title']));

	$url = "showthread.php?$session[sessionurl]t=$threadid";
	eval(print_standard_redirect('redirect_mergethread'));

}

// ############################### start split thread ###############################
if ($_REQUEST['do'] == 'splitthread')
{
	$templatename = 'threadadmin_splitthread';

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	if ($bbuserinfo['threadedmode'])
	{
		$show['children'] = true;
	}

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);

	$postbits = &construct_post_tree('threadadmin_postbit', $threadid);
	$parentpostassoc = &construct_js_post_parent_assoc($parentassoc);

	$curforumid = $threadinfo['forumid'];
	$moveforumbits = construct_move_forums_options();

}

// ############################### start do split thread ###############################
if ($_POST['do'] == 'dosplitthread')
{
	globalize($_POST, array('checkpost', 'newforumid' => INT, 'title' => STR));

	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	$splitpost = $checkpost;
	unset($splitpost[0]); // make sure this isn't set -- could do some weird things

	$doyes = 0;
	$dono = 0;
	if (is_array($splitpost))
	{
		$splitcheck = '';
		foreach ($splitpost AS $postid => $val)
		{
			$splitcheck .= ',' . intval($postid);
		}
		reset($splitpost);
		$splitcheck = substr($splitcheck, 1);
		if (!$splitcheck)
		{
			$dono = 1;
		}
		else

		{
			$count = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "post WHERE threadid = $threadid AND postid NOT IN ($splitcheck)");
			if ($count['count'] == 0)
			{ // that means all posts were selected
				$doyes = 1;
			}
		}
	}
	else
	{
		$dono = 1;
	}
	if ($doyes == 0 AND $dono == 1)
	{ // Selected no posts to split
		eval(print_standard_error('error_nosplitposts'));
	}
	else if ($doyes == 1 AND $dono == 0)
	{ // Selected all posts to split
		eval(print_standard_error('error_cantsplitall'));
	}
	if ($newforumid)
	{
		$newforumid = verify_id('forum', $newforumid);
		$destforuminfo = fetch_foruminfo($newforumid);
		if (!$destforuminfo['cancontainthreads'] OR $destforuminfo['link'])
		{
			eval(print_standard_error('error_moveillegalforum'));
		}
	}


	$newthreadnotes  = construct_phrase($vbphrase['thread_split_from_threadid_a_by_b_on_x_at_d'], $threadid, $bbuserinfo['username'], vbdate($vboptions['dateformat'], TIMENOW), vbdate($vboptions['timeformat'], TIMENOW));
	$newthreadnotes .= ' ' . $threadinfo['notes'];

	// Move post info to new thread...
	$parentassoc = array();
	$wasmoved = array();
	$userbyuserid = array();
	$posts = $DB_site->query("SELECT postid, parentid, dateline, userid FROM " . TABLE_PREFIX . "post WHERE threadid = $threadid ORDER BY dateline");
	while ($post = $DB_site->fetch_array($posts))
	{
		if (!$newthreadid)
		{ //prevent a thread from being created if the posts have already been split
			$DB_site->query("
				INSERT INTO " . TABLE_PREFIX . "thread(title, lastpost, forumid, open, replycount, postusername, postuserid, lastposter, dateline, views,
					 iconid, notes, visible)
				VALUES
					('" . addslashes(htmlspecialchars_uni($title)) . "', " . intval($threadinfo[lastpost]) . ", " . intval($newforumid) . ",
					 " . intval($threadinfo[open]) . ", " . intval($threadinfo[replycount]) . ", '" . addslashes($threadinfo[postusername]) . "',
					 " . intval($threadinfo[postuserid]) . ", '" . addslashes($threadinfo[lastposter]) . "',
					 " . intval($threadinfo[dateline]) . ", 0, ". intval($threadinfo[iconid]) . ",
					 '" . addslashes($newthreadnotes) . "',
					 " . intval($threadinfo[visible]) . ")
			");
			$newthreadid = $DB_site->insert_id();
		}

		$parentassoc["$post[postid]"] = $post['parentid'];
		if ($splitpost["$post[postid]"] == 'yes')
		{
			$movepostids .= ",$post[postid]";

			if ($post['userid'] AND (($foruminfo['countposts'] AND !$destforuminfo['countposts']) OR (!$foruminfo['countposts'] AND $destforuminfo['countposts'])))
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
		}
	}

	$DB_site->query("UPDATE " . TABLE_PREFIX . "post SET threadid = $newthreadid WHERE postid IN (0$movepostids)");

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
			$postcasesql .= " WHEN userid IN (0$userids) THEN $postcount";
		}

		$operator = iif($destforuminfo['countposts'], '+', '-');

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET posts = posts $operator
			CASE
				$postcasesql
				ELSE 0
			END
			WHERE userid IN (0$alluserids)
		");
	}

	$parentupdate = array();

	// update parentids
	$nosplittop = 0;
	$splittop = 0;
	foreach ($parentassoc AS $postid => $parentid)
	{
		if ($splitpost["$postid"] != 'yes' AND $splitpost["$parentid"] == 'yes')
		{
			// this post wasn't moved, but it's parent was, so we need to walk up the chain to find the next post that
			// wasn't moved and make this post a child of that one
			do
   			{
				$parentid = $parentassoc["$parentid"];
			}
			while ($splitpost["$parentid"] == 'yes');

			if ($parentid !== NULL)
			{
				if ($parentid == 0)
				{
					// this prevents two posts from becoming the topmost post
					if ($nosplittop == 0)
					{
						$nosplittop = $postid;
					}
					else
					{
						$parentid = $nosplittop;
					}
				}
				$parentcasesql .= " WHEN postid = $postid THEN " . intval($parentid);
				$allpostids .= ",$postid";
			}
		}
		else if ($splitpost["$postid"] == 'yes' AND $splitpost["$parentid"] != 'yes')
		{
			// this post was split, but it's parent wasn't
			do
			{
				$parentid = $parentassoc["$parentid"];
			}
			while ($splitpost["$parentid"] != 'yes' AND $parentid != 0); // $parentid check to prevent infinite loop

			if ($parentid !== NULL)
			{
				if ($parentid == 0)
				{
					// this prevents two posts from becoming the topmost post
					if ($splittop == 0)
					{
						$splittop = $postid;
					}
					else
					{
						$parentid = $splittop;
					}
				}
				$parentcasesql .= " WHEN postid = $postid THEN " . intval($parentid);
				$allpostids .= ",$postid";
			}
		}
	}

	if ($parentcasesql)
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "post
			SET parentid =
			CASE
				$parentcasesql
				ELSE parentid
			END
			WHERE postid IN (0$allpostids)
		");
	}

	// update new thread's first post to have the correct title
	// $DB_site->query("UPDATE " . TABLE_PREFIX . "post SET title = '" . addslashes(htmlspecialchars_uni($title)) . "' WHERE threadid = $newthreadid AND parentid = 0 AND title = ''");

	// Update first post in each thread as title information in relation to the sames words being in the first post may have changed now.
	$getfirstpost = $DB_site->query_first("SELECT postid, title, pagetext FROM " . TABLE_PREFIX . "post WHERE threadid = $threadid ORDER BY dateline LIMIT 1");
	delete_post_index($getfirstpost['postid'], $getfirstpost['title'], $getfirstpost['pagetext']);
	build_post_index($getfirstpost['postid'] , $foruminfo);

	$getfirstpost = $DB_site->query_first("SELECT postid, title, pagetext FROM " . TABLE_PREFIX . "post WHERE threadid = $newthreadid ORDER BY dateline LIMIT 1");
	delete_post_index($getfirstpost['postid'],$getfirstpost['title'], $getfirstpost['pagetext']);
	build_post_index($getfirstpost['postid'] , $foruminfo);

	$postdeleted = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $getfirstpost[postid] AND type='post'");
	if ($postdeleted['primaryid'])
	{ // first post is deleted, make thread deleted instead
		$threaddeleted = 1;
		$DB_site->query("UPDATE " . TABLE_PREFIX . "deletionlog SET primaryid = $newthreadid, type = 'thread' WHERE primaryid = $getfirstpost[postid] AND type='post'");
	}

	build_thread_counters($threadid);
	build_thread_counters($newthreadid);
	build_forum_counters($threadinfo['forumid']);
	if ($newforumid != $threadinfo['forumid'])
	{
		build_forum_counters($newforumid);
	}

	log_moderator_action($threadinfo, construct_phrase($vbphrase['thread_split_to_x'], $newthreadid));

	$url = "showthread.php?$session[sessionurl]t=$newthreadid";
	eval(print_standard_redirect('redirect_splitthread'));
}

// ############################### start stick / unstick thread ###############################
if ($_POST['do'] == 'stick')
{
	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		eval(print_standard_error('error_invalidid'));
	}

	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	if ($threadinfo['sticky'])
	{
		$threadinfo['sticky'] = 0;
		$logaction = $vbphrase['unstuck_thread'];
		$action = $vbphrase['unstuck'];
	}
	else
	{
		$threadinfo['sticky'] = 1;
		$logaction = $vbphrase['stuck_thread'];
		$action = $vbphrase['stuck'];
	}
	log_moderator_action($threadinfo, $logaction);

	$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET sticky = $threadinfo[sticky] WHERE threadid = $threadid");

	$_REQUEST['forceredirect'] = 1;
	$url = "showthread.php?$session[sessionurl]t=$threadid";
	eval(print_standard_redirect('redirect_sticky'));
}

// ############################### start remove redirects ###############################
if ($_POST['do'] == 'removeredirect')
{
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "thread WHERE open = 10 AND pollid = $threadid");

	$_REQUEST['forceredirect'] = 1;
	$url = "showthread.php?$session[sessionurl]t=$threadid";
	eval(print_standard_redirect('redirects_removed'));
}

// ############################### start manage post ###############################
if ($_REQUEST['do'] == 'managepost')
{
	$templatename = 'threadadmin_managepost';

	if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		print_no_permission();
	}

	$show['undeleteoption'] = iif($postinfo['isdeleted'] == 1 AND (can_moderate($threadinfo['forumid'], 'canremoveposts') OR can_moderate($threadinfo['forumid'], 'candeleteposts')), true, false);

	require_once('./includes/functions_bbcodeparse.php');
	$postinfo['pagetext'] = parse_bbcode($postinfo['pagetext'], $forumid);

	$postinfo['postdate'] = vbdate($vboptions['dateformat'], $postinfo['dateline'], 1);
	$postinfo['posttime'] = vbdate($vboptions['timeformat'], $postinfo['dateline']);

	$visiblechecked = iif($postinfo['visible'], HTML_CHECKED);

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);

}

// ############################### start manage post ###############################
if ($_POST['do'] == 'domanagepost')
{
	globalize($_POST, array('poststatus' => INT, 'visible', 'reason' => STR));

	$visible = iif($visible == 'yes', 1, 0);

	if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		print_no_permission();
	}

	if ($poststatus == 1)
	{ // undelete
		undelete_post($postid, $foruminfo['countposts']);
		$postdeleted = -1;
	}
	else if ($poststatus == 2 AND can_moderate($threadinfo['forumid'], 'canremoveposts'))
	{ // remove
		delete_post($postid, $foruminfo['countposts'], $threadinfo['threadid'], 1);
		$postdeleted = 1;
	}
	else
	{
		$postdeleted = 0;
	}

	if (!$visible)
	{
		$DB_site->query("REPLACE INTO " . TABLE_PREFIX . "moderation (threadid, postid, type) VALUES ($threadid, $postid, 'reply')");
	}

	if ($postdeleted != 1)
	{
		$DB_site->query("UPDATE " . TABLE_PREFIX . "post SET visible = $visible WHERE postid = $postid");
		if ($reason)
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "deletionlog SET reason = '" . addslashes(htmlspecialchars_uni($reason)) . "' WHERE primaryid = $postid AND type = 'post'");
		}
		$url = "showthread.php?$session[sessionurl]p=$postid#post$postid";
	}
	else
	{
		$url = "showthread.php?$session[sessionurl]t=$threadid";
	}

	eval(print_standard_redirect('redirect_post_manage'));
}

// ############################### all done, do shell template ###############################

if ($templatename != '')
{
	// draw navbar
	eval('$navbar = "' . fetch_template('navbar') . '";');

	// spit out the final HTML if we have got this far
	eval('$HTML = "' . fetch_template($templatename) . '";');
	eval('print_output("' . fetch_template('THREADADMIN') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11481 $
|| ####################################################################
\*======================================================================*/
?>