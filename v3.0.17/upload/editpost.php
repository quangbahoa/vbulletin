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
define('GET_EDIT_TEMPLATES', true);
define('THIS_SCRIPT', 'editpost');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('threadmanage', 'posting');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'attachmentcache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'editpost',
	'editpost_attachment',
	'newpost_attachment',
	'newpost_attachmentbit'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_newpost.php');
require_once('./includes/functions_bigthree.php');
require_once('./includes/functions_editor.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$attachtypes = unserialize($datastore['attachmentcache']);

// ### STANDARD INITIALIZATIONS ###
$checked = array();
$edit = array();

// get decent textarea size for user's browser
$textareacols = fetch_textarea_width();

// sanity checks...
if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// verify postid
$postid = verify_id('post', $_REQUEST['postid']);

$postinfo = fetch_postinfo($postid);
if (!$postinfo['visible'] OR $postinfo['isdeleted'])
{
	$idname = $vbphrase['post'];
	eval(print_standard_error('error_invalidid'));
}

$threadinfo = fetch_threadinfo($postinfo['threadid']);
if ($vboptions['wordwrap'])
{
	$threadinfo['title'] = fetch_word_wrapped_string($threadinfo['title']);
}

if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
{
	$idname = $vbphrase['thread'];
	eval(print_standard_error('error_invalidid'));
}

// get permissions info
$_permsgetter_ = 'edit post';
$forumperms = fetch_permissions($threadinfo['forumid']);

$foruminfo = fetch_foruminfo($threadinfo['forumid'], false);

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

// ############################### start permissions checking ###############################
if ($_REQUEST['do'] == 'deletepost')
{

	// is post being deleted? if so check delete specific permissions
	if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		if (!$threadinfo['open'])
		{
			$url = "showthread.php?$session[sessionurl]t=$postinfo[threadid]";
			eval(print_standard_redirect('redirect_threadclosed'));
		}
		if (!($forumperms & CANDELETEPOST))
		{
			print_no_permission();
		}
		else
		{
			if ($bbuserinfo['userid'] != $postinfo['userid'])
			{
				// check user owns this post since they failed the Mod Delete permission check for this forum
				print_no_permission();
			}
		}
	}
}
else
{
	// otherwise, post is being edited
	if (!can_moderate($threadinfo['forumid'], 'caneditposts'))
	{ // check for moderator
		if (!$threadinfo['open'])
		{
			$url = "showthread.php?$session[sessionurl]t=$threadinfo[threadid]";
			eval(print_standard_error('threadclosed'));
		}
		if (!($forumperms & CANEDITPOST))
		{
			print_no_permission();
		}
		else
		{
			if ($bbuserinfo['userid'] != $postinfo['userid'])
			{
				// check user owns this post
				print_no_permission();
			}
			else
			{
				// check for time limits
				if ($postinfo['dateline'] < (TIMENOW - ($vboptions['edittimelimit'] * 60)) AND $vboptions['edittimelimit'] != 0)
				{
					eval(print_standard_error('error_edittimelimit'));
				}
			}
		}
	}
}

// ############################### start update post ###############################
if ($_POST['do'] == 'updatepost')
{

	globalize($_POST, array('posthash' => STR_NOHTML, 'poststarttime' => INT, 'stickunstick' => INT, 'openclose' => INT));
	// Make sure the posthash is valid

	if (md5($poststarttime . $bbuserinfo['userid'] . $bbuserinfo['salt']) != $posthash)
	{
		$posthash = 'invalid posthash'; // don't phrase me
	}

	// ### PREP INPUT (should eventually all come in array direct from form) ###
	if ($_POST['WYSIWYG_HTML'] != '')
	{
		require_once('./includes/functions_wysiwyg.php');
		$edit['message'] = trim(convert_wysiwyg_html_to_bbcode($_POST['WYSIWYG_HTML'], $foruminfo['allowhtml']));
	}
	else
	{
		$edit['message'] = trim($_POST['message']);
	}

	// remove empty bbcodes
	$edit['message'] = strip_empty_bbcode($edit['message']);

	// add # to color tags using hex if it's not there
	$edit['message'] = preg_replace('#\[color=(&quot;|"|\'|)([a-f0-9]{6})\\1]#i', '[color=\1#\2\1]', $edit['message']);

	// remove /list=x remnants
	if (stristr($edit['message'], '/list=') != false)
	{
		$edit['message'] = preg_replace('#/list=[a-z0-9]+\]#siU', '/list]', $edit['message']);
	}

	// remove extra whitespace between [list] and first element
	$edit['message'] = preg_replace('#(\[list(=(&quot;|"|\'|)([^\]]*)\\3)?\])\s+#i', "\\1\n", $edit['message']);

	$edit['title'] = trim($_POST['title']);
	$edit['iconid'] = intval($_POST['iconid']);

	$edit['parseurl'] = intval($_POST['parseurl']);
	$edit['signature'] = intval($_POST['signature']);
	$edit['disablesmilies'] = intval($_POST['disablesmilies']);
	$edit['enablesmilies'] = $edit['allowsmilie'] = iif($edit['disablesmilies'], 0, 1);

	$edit['reason'] = htmlspecialchars_uni(fetch_censored_text(trim($_POST['reason'])));
	$edit['preview'] = trim($_POST['preview']);
	$edit['folderid'] = intval($_POST['folderid']);
	$edit['emailupdate'] = intval($_POST['emailupdate']);

	if ($edit['parseurl'])
	{
		$edit['message'] = convert_url_to_bbcode($edit['message']);
	}

	$postusername = $bbuserinfo['username'];

	verify_post_errors('editpost', $edit, $errors);

	if (sizeof($errors) > 0)
	{
		// ### POST HAS ERRORS ###
		$postpreview = construct_errors($errors);
		construct_checkboxes($edit);
		//cache_templates($tempusedcache['editpost'], $style['templatelist']);
		$previewpost = true;
		$_REQUEST['do'] = 'editpost';
	}
	else if ($edit['preview'])
	{
		// ### PREVIEW POST ###
		$postpreview = process_post_preview($edit, $postinfo['userid']);
		//cache_templates($tempusedcache['editpost'], $style['templatelist']);
		$previewpost = true;
		$_REQUEST['do'] = 'editpost';
	}
	else
	{
		// ### POST HAS NO ERRORS ###

		// Delete user's previous edit if we don't save edits for this group and they didn't give a reason
		if (!$edit['reason'] AND $postinfo['edit_userid'] == $bbuserinfo['userid'] AND !($permissions['genericoptions'] & SHOWEDITEDBY))
		{
			$DB_site->query("
				DELETE FROM " . TABLE_PREFIX . "editlog
				WHERE postid = $postid
			");
		}
		else if ((($permissions['genericoptions'] & SHOWEDITEDBY) AND $postinfo['dateline'] < (TIMENOW - ($vboptions['noeditedbytime'] * 60))) OR !empty($edit['reason']))
		{
			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "editlog (postid, userid, username, dateline, reason)
				VALUES ($postid, $bbuserinfo[userid], '" . addslashes($bbuserinfo['username']) . "', " . TIMENOW . ", '" . addslashes($edit['reason']) . "')
			");
		}

		// remove /list=x remnants
		if (stristr($edit['message'], '/list=') != false)
		{
			$edit['message'] = preg_replace('#/list=[a-z0-9]+\]#siU', '/list]', $edit['message']);
		}

		$edit['title'] = fetch_censored_text(fetch_no_shouting_text($edit['title']));

		require_once('./includes/functions_login.php');
		$edit['message'] = fetch_censored_text(fetch_removed_sessionhash($edit['message']));
		$edit['message'] = fetch_no_shouting_text($edit['message']);

		$date = vbdate($vboptions['dateformat'], TIMENOW);
		$time = vbdate($vboptions['timeformat'], TIMENOW);

		// initialize thread / forum update clauses
		$threadupdate = array();
		$modlogsql = array();
		$forumupdate = false;

		// find out if first post
		$getpost = $DB_site->query_first("
			SELECT postid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid=$threadinfo[threadid]
			ORDER BY dateline LIMIT 1
		");
		if ($getpost['postid'] == $postid AND $edit['title'] != '' AND ($postinfo['dateline'] + $vboptions['editthreadtitlelimit'] * 60) > TIMENOW)
		{
			// need to update thread title and iconid
			$threadupdate[] = "title = '" . addslashes(htmlspecialchars_uni($edit['title'])) . "', iconid = $edit[iconid]";
			// do we need to update the forum counters?
			$forumupdate = iif($foruminfo['lastthreadid'] == $threadinfo['threadid'], true, false);
		}

		// can this user open/close this thread if they want to?
		if ($openclose AND (($threadinfo['postuserid'] != 0 AND $threadinfo['postuserid'] == $bbuserinfo['userid'] AND $forumperms & CANOPENCLOSE) OR can_moderate($threadinfo['forumid'], 'canopenclose')))
		{
			if ($threadinfo['open'])
			{
				$open = 0;
				$string = $vbphrase['closed_thread'];
			}
			else
			{
				$open = 1;
				$string = $vbphrase['opened_thread'];
			}
			$threadupdate[] = "open = $open";
			$modlogsql[] = "($bbuserinfo[userid], " . TIMENOW . ", $threadinfo[forumid], $threadinfo[threadid], 0, '" . addslashes($string) . "')";
		}
		// can this user stick/unstick this thread if they want to?
		if ($stickunstick AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
		{
			if ($threadinfo['sticky'])
			{
				$stick = 0;
				$string = $vbphrase['unstuck_thread'];
			}
			else
			{
				$stick = 1;
				$string = $vbphrase['stuck_thread'];
			}
			$threadupdate[] = "sticky = $stick";
			$modlogsql[] = "($bbuserinfo[userid], " . TIMENOW . ", $threadinfo[forumid], $threadinfo[threadid], 0, '" . addslashes($string) . "')";
		}

		// if this is a mod edit, then log it
		if ($bbuserinfo['userid'] != $postinfo['userid'] AND can_moderate($threadinfo['forumid'], 'caneditposts'))
		{
			$string = construct_phrase($vbphrase['post_x_edited'], $postinfo['title']);
			$modlogsql[] = "($bbuserinfo[userid], " . TIMENOW . ", $threadinfo[forumid], $threadinfo[threadid], $postid, '" . addslashes($string) . "')";
		}

		require_once('./includes/functions_databuild.php');

		if (!empty($threadupdate))
		{
			// do thread update
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "thread
				SET " . implode(', ', $threadupdate) . "
				WHERE threadid = $threadinfo[threadid]
			");
		}

		// moderator log
		if (!empty($modlogsql))
		{
			$DB_site->query("
				INSERT INTO " . TABLE_PREFIX . "moderatorlog (userid, dateline, forumid, threadid, postid, action)
				VALUES " . implode(', ', $modlogsql)
			);
		}

		// do forum update if necessary
		if ($forumupdate)
		{
			build_forum_counters($threadinfo['forumid']);
		}

		// Are we editing someone else's post? If so load that users subscription info for this thread.
		if ($bbuserinfo['userid'] != $postinfo['userid'])
		{
			if ($otherthreadinfo = $DB_site->query_first("
				SELECT emailupdate, folderid
				FROM " . TABLE_PREFIX . "subscribethread
				WHERE threadid = $threadinfo[threadid] AND
					userid = $postinfo[userid]"))
			{
				$threadinfo['issubscribed'] = true;
				$threadinfo['emailupdate'] = $otherthreadinfo['emailupdate'];
				$threadinfo['folderid'] = $otherthreadinfo['folderid'];
			}
		}

		// ### DO THREAD SUBSCRIPTION ###
		// We use $postinfo[userid] so that we update the user who posted this, not the user who is editing this
		if (!$threadinfo['issubscribed'] AND $edit['emailupdate'] != 9999)
		{ // user is not subscribed to this thread so insert it
			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid)
				VALUES ($postinfo[userid], $threadinfo[threadid], $edit[emailupdate], $edit[folderid])
			");
		}
		else
		{ // User is subscribed, see if they changed the settings for this thread
			if ($edit['emailupdate'] == 9999)
			{	// Remove this subscription, user chose 'No Subscription'
				$DB_site->query("
					DELETE FROM " . TABLE_PREFIX . "subscribethread
					WHERE threadid = $threadinfo[threadid]
						AND	userid = $postinfo[userid]");
			}
			else if ($threadinfo['emailupdate'] != $edit['emailupdate'] OR $threadinfo['folderid'] != $edit['folderid'])
			{
				// User changed the settings so update the current record
				$DB_site->query("
					REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid)
					VALUES ($postinfo[userid], $threadinfo[threadid], $edit[emailupdate], $edit[folderid])
				");
			}
		}

		$attachments = $DB_site->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "attachment
			WHERE posthash = '" . addslashes($posthash) . "'
				### AND	userid = $bbuserinfo[userid] Sep 19, 2003 Change to allow owner to always own any attachment on their post ###
		");
		$newattachments = $attachments['count'];
		if ($newattachments)
		{
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "attachment
				SET postid = $postid, posthash = ''
				WHERE posthash = '" . addslashes($posthash) . "'
					### AND	userid = $bbuserinfo[userid] Sep 19, 2003 Change to allow owner to always own any attachment on their post ###
				");
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "thread
				SET attach = attach + $newattachments
				WHERE threadid = $threadinfo[threadid]
			");
		}

		delete_post_index($postid);

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "post
			SET title = '" . addslashes(htmlspecialchars_uni($edit['title'])) . "',
				pagetext = '" . addslashes($edit['message']) . "',
				allowsmilie = $edit[allowsmilie],
				showsignature = $edit[signature],
				iconid = $edit[iconid],
				attach = attach + $newattachments
			WHERE postid = $postid
		");
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "post_parsed WHERE postid = $postid");

		build_post_index($postid , $foruminfo);

		$url = "showthread.php?$session[sessionurl]p=$postid#post$postid";
		eval(print_standard_redirect('redirect_editthanks'));

	}
}

// ############################### start edit post ###############################
if ($_REQUEST['do'] == 'editpost')
{
	if (!$checked['disablesmilies'])
	{
		$checked['disablesmilies'] = iif($postinfo['allowsmilie'] OR $_REQUEST['disablesmilies'], '', HTML_CHECKED);
	}

	// get message
	if ($edit['message'] != '')
	{
		$newpost['message'] = htmlspecialchars_uni($edit['message']);
	}
	else
	{
		$newpost['message'] = htmlspecialchars_uni($postinfo['pagetext']);
	}

	construct_edit_toolbar($newpost['message'], 0, $foruminfo['forumid'], iif($foruminfo['allowsmilies'], 1, 0), iif($postinfo['allowsmilie'] AND $_REQUEST['disablesmilies'] != 1, 1, 0));

	// handle checkboxes
	if (!$checked['parseurl'])
	{
		$checked['parseurl'] = HTML_CHECKED;
	}
	if (!$checked['signature'])
	{
		$checked['signature'] = iif($postinfo['showsignature'], HTML_CHECKED, '');
	}

	// Are we editing someone else's post? If so load that users folders and subscription info for this thread.
	if ($bbuserinfo['userid'] != $postinfo['userid'])
	{
		$userfolders = $DB_site->query_first("
			SELECT subfolders, signature
			FROM " . TABLE_PREFIX . "usertextfield
			WHERE userid = $postinfo[userid]
		");

		// temporarily assign this user's signature to bbuserinfo so we can see whether or not to show the sig checkbox option
		$bbuserinfo['signature'] = $userfolders['signature'];

		if ($userfolders['subfolders'] == '')
		{
			$folders = array();
		}
		else
		{
			$folders = unserialize($userfolders['subfolders']);
		}
		if (empty($folders)) // catch user who has no folders or an empty serialized array
		{
			$folders = array($vbphrase['subscriptions']);
		}
		if ($otherthreadinfo = $DB_site->query_first("
			SELECT emailupdate, folderid
			FROM " . TABLE_PREFIX . "subscribethread
			WHERE threadid = $threadinfo[threadid] AND
				userid = $postinfo[userid]"))
		{
			$threadinfo['issubscribed'] = true;
			$threadinfo['emailupdate'] = $otherthreadinfo['emailupdate'];
			$threadinfo['folderid'] = $otherthreadinfo['folderid'];
		}
	}
	else
	{
		$folders = unserialize($bbuserinfo['subfolders']);
	}

	// Get subscribed thread folders
	if ($threadinfo['issubscribed'])
	{
		$folderselect["$threadinfo[folderid]"] = HTML_SELECTED;
	}
	else
	{
		$folderselect[0] = HTML_SELECTED;
	}

	// Don't show the folderjump if we only have one folder, would be redundant ;)
	if (sizeof($folders) > 1)
	{
		require_once('./includes/functions_misc.php');
		$folderbits = construct_folder_jump(1, $threadinfo['folderid'], false, $folders);
		$show['subscriptionfolders'] = true;
	}

	// get the checked option for auto subscription
	$emailchecked = fetch_emailchecked($threadinfo);

	if ($previewpost)
	{
		$newpost['reason'] = $edit['reason'];
	}
	else if ($bbuserinfo['userid'] == $postinfo['edit_userid'])
	{
		// Only carry the reason over if the editing user owns the previous edit
		$newpost['reason'] = $postinfo['edit_reason'];
	}

	$postinfo['postdate'] = vbdate($vboptions['dateformat'], $postinfo['dateline']);
	$postinfo['posttime'] = vbdate($vboptions['timeformat'], $postinfo['dateline']);

	// find out if first post
	$getpost = $DB_site->query_first("
		SELECT postid
		FROM " . TABLE_PREFIX . "post
		WHERE threadid=$threadinfo[threadid]
		ORDER BY dateline
		LIMIT 1
	");
	if ($getpost['postid'] == $postid)
	{
		$isfirstpost = true;
	}
	else
	{
		$isfirstpost = false;
	}
	if ($isfirstpost AND $postinfo['title'] == '' AND ($postinfo['dateline'] + $vboptions['editthreadtitlelimit'] * 60) > TIMENOW)
	{
		$postinfo['title'] = $threadinfo['title'];
	}

	if ($edit['title'] != '')
	{
		$title = htmlspecialchars_uni($edit['title']);
	}
	else
	{
		$title = $postinfo['title'];
	}

	if ($postinfo['userid'])
	{
		$userinfo = fetch_userinfo($postinfo['userid']);
		$postinfo['username'] = $userinfo['username'];
	}

	if ($edit['iconid'])
	{
		$posticons = construct_icons($edit['iconid'], $foruminfo['allowicons']);
	}
	else
	{
		$posticons = construct_icons($postinfo['iconid'], $foruminfo['allowicons']);
	}

	// edit / add attachment
	if ($forumperms & CANPOSTATTACHMENT AND $bbuserinfo['userid'])
	{
		require_once('./includes/functions_file.php');
		$inimaxattach = fetch_max_attachment_size();
		$maxattachsize = vb_number_format($inimaxattach, 1, true);

		if (!$posthash OR !$poststarttime)
		{
			$poststarttime = TIMENOW;
			$posthash = md5($poststarttime . $bbuserinfo['userid'] . $bbuserinfo['salt']);

		}
		// <-- This is done in two queries since Mysql will not use an index on an OR query which gives a full table scan of the attachment table
		// Attachments that existed before the edit began.
		$currentattaches1 = $DB_site->query("
			SELECT filename, filesize, attachmentid
			FROM " . TABLE_PREFIX . "attachment
			WHERE postid = $postinfo[postid]
			ORDER BY dateline
		");
		// Attachments added since the edit began. Used when editpost is reloaded due to an error on the user side
		$currentattaches2 = $DB_site->query("
			SELECT filename, filesize, attachmentid
			FROM " . TABLE_PREFIX . "attachment
			WHERE posthash = '$posthash'
				AND userid = $bbuserinfo[userid]
			ORDER BY dateline
		");
		$attachcount = 0;
		for ($x = 1; $x <= 2; $x++)
		{
			$currentattaches = &${currentattaches . $x};
			while ($attach = $DB_site->fetch_array($currentattaches))
			{
				$attachcount++;
				$attach['extension'] = strtolower(file_extension($attach['filename']));
				$attach['filename'] = htmlspecialchars_uni($attach['filename']);
				$attach['filesize'] = vb_number_format($attach['filesize'], 1, true);
				$show['attachmentlist'] = true;
				eval('$attachments .= "' . fetch_template('newpost_attachmentbit') . '";');
			}
		}

		if (!$foruminfo['allowposting'] AND $attachcount == 0)
		{
			$attachmentoption = '';
		}
		else
		{
			$attachurl = "p=$postinfo[postid]&editpost=1";
			eval('$attachmentoption = "' . fetch_template('newpost_attachment') . '";');
		}
	}
	else
	{
		$attachmentoption = '';
	}

	if ($isfirstpost AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$show['deletepostoption'] = true;
	}
	else if (!$isfirstpost AND can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		$show['deletepostoption'] = true;
	}
	else if (((($forumperms & CANDELETEPOST) AND !$isfirstpost) OR (($forumperms & CANDELETETHREAD) AND $isfirstpost)) AND $bbuserinfo['userid'] == $postinfo['userid'])
	{
		$show['deletepostoption'] = true;
	}
	else
	{
		$show['deletepostoption'] = false;
	}

	// can this user open / close this thread?
	if (($threadinfo['postuserid'] AND $threadinfo['postuserid'] == $bbuserinfo['userid'] AND $forumperms & CANOPENCLOSE) OR can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
		$show['openclose'] = true;
	}
	else
	{
		$show['openclose'] = false;
	}
	// can this user stick this thread?
	if (can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$show['stickunstick'] = true;
	}
	else
	{
		$show['stickunstick'] = false;
	}
	if ($show['openclose'] OR $show['stickunstick'])
	{
		$show['closethread'] = iif($threadinfo['open'], true, false);
		$show['unstickthread'] = iif($threadinfo['sticky'], true, false);
		eval('$threadmanagement = "' . fetch_template('newpost_threadmanage') . '";');
	}
	else
	{
		$threadmanagement = '';
	}

	$show['physicaldeleteoption'] = iif (can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);
	$show['keepattachmentsoption'] = iif ($attachcount, true, false);
	$show['firstpostnote'] = $isfirstpost;

	construct_forum_rules($foruminfo, $forumperms);

	$currentpage = urlencode("editpost.php?do=editpost&p=$postinfo[postid]");
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// draw nav bar
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = &$forumcache["$forumID"]['title'];
		$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
	}
	$navbits["showthread.php?$session[sessionurl]p=$postid#post$postid"] = $threadinfo['title'];
	$navbits[''] = $vbphrase['edit_post'];
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('editpost') . '");');

}

// ############################### start delete post ###############################
if ($_POST['do'] == 'deletepost')
{

	globalize($_POST, array('deletepost' => STR, 'reason' => STR, 'keepattachments' => INT));

	if ($deletepost)
	{
		//get first post in thread
		$getfirst = $DB_site->query_first("
			SELECT postid, dateline
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $postinfo[threadid]
			ORDER BY dateline
			LIMIT 1
		");
		if ($getfirst['postid'] == $postid)
		{
			// delete thread
			if ($forumperms & CANDELETETHREAD OR can_moderate($threadinfo['forumid'], 'canmanagethreads'))
			{
				if ($deletepost == 'remove' AND can_moderate($threadinfo['forumid'], 'canremoveposts'))
				{
					$removaltype = 1;
				}
				else
				{
					$removaltype = 0;
				}
				require_once('./includes/functions_databuild.php');
				delete_thread($threadinfo['threadid'], $foruminfo['countposts'], $removaltype, array('userid' => $bbuserinfo['userid'], 'username' => $bbuserinfo['username'], 'reason' => $reason, 'keepattachments' => $keepattachments));

				if ($foruminfo['lastthreadid'] != $threadinfo['threadid'])
				{
					// just decrement the reply and thread counter for the forum
					$DB_site->query("
						UPDATE " . TABLE_PREFIX . "forum
						SET	threadcount = threadcount - 1, replycount = replycount - $threadinfo[replycount] - 1
						WHERE forumid = $threadinfo[forumid]
					");
				}
				else
				{
					// this thread is the one being displayed as the thread with the last post...
					// so get a new thread to display.
					build_forum_counters($threadinfo['forumid']);
				}
				$url = "forumdisplay.php?$session[sessionurl]f=$threadinfo[forumid]";
				eval(print_standard_redirect('redirect_deletethread'));
			}
			else
			{
				print_no_permission();
			}
		}
		else
		{
			//delete just this post
			if ($deletepost == 'remove' AND can_moderate($threadinfo['forumid'], 'canremoveposts'))
			{
				$removaltype = 1;
			}
			else
			{
				$removaltype = 0;
			}
			require_once('./includes/functions_databuild.php');
			delete_post($postid, $foruminfo['countposts'], $threadinfo['threadid'], $removaltype, array('userid' => $bbuserinfo['userid'], 'username' => $bbuserinfo['username'], 'reason' => $reason, 'keepattachments' => $keepattachments));
			build_thread_counters($threadinfo['threadid']);

			if ($foruminfo['lastthreadid'] != $threadinfo['threadid'])
			{
				// just decrement the reply counter
				$DB_site->query("UPDATE " . TABLE_PREFIX . "forum SET replycount = replycount - 1 WHERE forumid = $threadinfo[forumid]");
			}
			else
			{
				// this thread is the one being displayed as the thread with the last post...
				// need to get the lastpost datestamp and lastposter name from the thread.
				build_forum_counters($threadinfo['forumid']);
			}
			$url = "showthread.php?$session[sessionurl]t=$threadinfo[threadid]";
			eval(print_standard_redirect('redirect_deletepost'));

		}
	}
	else
	{
		$url = "showthread.php?$session[sessionurl]p=$postid#post$postid";
		eval(print_standard_redirect('redirect_nodelete'));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11372 $
|| ####################################################################
\*======================================================================*/
?>