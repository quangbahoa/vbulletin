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
@set_time_limit(0);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('GET_EDIT_TEMPLATES', true);
define('THIS_SCRIPT', 'newattachment');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting');

// get special data templates from the datastore
$specialtemplates = array(
	'attachmentcache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'newattachment',
	'newattachmentbit',
	'newpost_attachmentbit',
	'newattachment_errormessage'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_newpost.php');
require_once('./includes/functions_file.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!$bbuserinfo['userid']) // Guests can not post attachments
{
	print_no_permission();
}

$attachtypes = unserialize($datastore['attachmentcache']);

globalize($_REQUEST, array('editpost' => INT));

if ($postid AND $editpost)
{
	$postid = verify_id('post', $postid);
}
else if ($threadid)
{
	$threadid = verify_id('thread', $threadid);
	unset($postid); // We don't want the post from the thread we are replying to..
}
else if ($forumid)
{
	$forumid = verify_id('forum', $forumid);
}
else
{
	$idname = $vbphrase['post'];
	eval(print_standard_error('invalidid'));
}

$forumperms = fetch_permissions($foruminfo['forumid']);

// No permissions to post attachments in this forum or no permission to view threads in this forum.
if (!($forumperms & CANPOSTATTACHMENT) OR !($forumperms & CANVIEW))
{
	print_no_permission();
}

if ((!$postid AND !$foruminfo['allowposting']) OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
{
	eval(print_standard_error('forumclosed'));
}

if ($threadid) // newreply.php or editpost.php called
{
	if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
	{
		$idname = $vbphrase['thread'];
		eval(print_standard_error('invalidid'));
	}
	if (!$threadinfo['open'])
	{
		if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
		{
			$url = "showthread.php?$session[sessionurl]t=$threadid";
			eval(print_standard_error('threadclosed'));
		}
	}
	if (($bbuserinfo['userid'] != $threadinfo['postuserid']) AND (!($forumperms & CANVIEWOTHERS) OR !($forumperms & CANREPLYOTHERS)))
	{
		print_no_permission();
	}

	// don't call this part on editpost.php (which will have a $postid)
	if (!$postid AND !($forumperms & CANREPLYOWN) AND $bbuserinfo['userid'] == $threadinfo['postuserid'])
	{
		print_no_permission();
	}
}
else if (!($forumperms & CANPOSTNEW)) // newthread.php
{
	print_no_permission();
}

if ($postid) // editpost.php
{
	if (!$postinfo['visible'] OR $postinfo['isdeleted'])
	{
		$idname = $vbphrase['post'];
		eval(print_standard_error('error_invalidid'));
	}
	if (!can_moderate($threadinfo['forumid'], 'caneditposts'))
	{
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
				if ($postinfo['dateline'] < (TIMENOW - ($vboptions['edittimelimit'] * 60)) AND $vboptions['edittimelimit'])
				{
					eval(print_standard_error('error_edittimelimit'));
				}
			}
		}
	}
}

$parentattach = '';

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

globalize($_REQUEST, array('poststarttime' => STR_NOHTML, 'posthash' => STR_NOHTML));

if ($posthash != md5($poststarttime . $bbuserinfo['userid'] . $bbuserinfo['salt']))
{
	print_no_permission();
}

$show['errors'] = false;

$currentattaches = $DB_site->query_first("
	SELECT COUNT(*) AS count
	FROM " . TABLE_PREFIX . "attachment
	WHERE posthash = '$posthash'
		AND userid = $bbuserinfo[userid]
");
$attachcount = $currentattaches['count'];

if ($postid)
{
	$currentattaches = $DB_site->query_first("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "attachment
		WHERE postid = $postid
	");
	$attachcount += $currentattaches['count'];
	$show['postowner'] = true;
	$attach_username = $postinfo['username'];
}
else
{
	$show['postowner'] = false;
	$attach_username = $bbuserinfo['username'];
}

if (!$foruminfo['allowposting'] AND !$attachcount)
{
	eval(print_standard_error('forumclosed'));
}

// ##################### Add Attachment to Post ####################
if ($_POST['do'] == 'manageattach')
{
	if (!$_POST['upload'])
	{
		foreach ($_POST AS $index => $value)
		{
			if (substr($index, 0, 6) == 'delete')
			{
				$attachmentid = intval(substr($index, 6));
				$ranquery = false;
				if ($postid)
				{
					if ($vboptions['attachfile'])
					{
						// Could have been posted by another user
						$userid = $DB_site->query_first("
							SELECT userid FROM " . TABLE_PREFIX . "attachment
							WHERE attachmentid = $attachmentid
								AND	postid = $postid
						");
						$deletearray = array($attachmentid => $userid['userid']);
						delete_attachment_files($deletearray);
					}
					$DB_site->query("
						DELETE FROM " . TABLE_PREFIX . "attachment
						WHERE attachmentid = $attachmentid
							AND	postid = $postid
					");
					if ($DB_site->affected_rows() > 0) // this was an attachment already attached to this post...
					{
						// Decremement attach counters
						$DB_site->query("
							UPDATE " . TABLE_PREFIX . "post
							SET attach = attach - 1
							WHERE postid = $postid
						");
						$DB_site->query("
							UPDATE " . TABLE_PREFIX . "thread
							SET attach = attach - 1
							WHERE threadid = $threadinfo[threadid]
						");
						// Store note about this post being edited in case the user doesn't choose to go any further with the edit
						if (($permissions['genericoptions'] & SHOWEDITEDBY) AND $postinfo['dateline'] < (TIMENOW - ($vboptions['noeditedbytime'] * 60)))
						{
							$DB_site->query("
								REPLACE INTO " . TABLE_PREFIX . "editlog
									(postid, userid, username, dateline)
								VALUES
									($postid, $bbuserinfo[userid], '" . addslashes($bbuserinfo['username']) . "', " . TIMENOW . ")"
							);
						}
						$ranquery = true;
						if ($bbuserinfo['userid'] != $postinfo['userid'] AND can_moderate($threadinfo['forumid'], 'caneditposts'))
						{
							require_once('./includes/functions_log_error.php');
							log_moderator_action($postinfo, $vbphrase['attachment_removed']);
						}
					}

					$show['updateparent'] = true;
				}
				if (!$ranquery)
				{
					if ($vboptions['attachfile'])
					{
						// Could have been posted by another user
						@unlink(fetch_attachment_path(iif($postinfo['postid'], $postinfo['userid'], $bbuserinfo['userid']), $attachmentid));
						// Delete thumbnail
						@unlink(fetch_attachment_path(iif($postinfo['postid'], $postinfo['userid'], $bbuserinfo['userid']), $attachmentid, true));
					}
					$DB_site->query("
						DELETE FROM " . TABLE_PREFIX . "attachment
						WHERE posthash = '$posthash'
							AND userid = " . iif($postinfo['postid'], $postinfo['userid'], $bbuserinfo['userid']) . "
							AND attachmentid = $attachmentid
					");
					$show['updateparent'] = true;
				}
			}

		}
	}
	else
	{	// Attach file...
		foreach ($_FILES AS $upload => $attachment)
		{
			$attachcount++;
			if (!$foruminfo['allowposting'])
			{
				$error = $vbphrase['this_forum_is_not_accepting_new_attachments'];
				$errors[] = array(
					'filename' => htmlspecialchars_uni($attachment['name']),
					'error' => $error
				);
			}
			else if ($vboptions['attachlimit'] AND $attachcount > $vboptions['attachlimit'])
			{
				$error = construct_phrase($vbphrase['you_may_only_attach_x_files_per_post'], $vboptions['attachlimit']);
				$errors[] = array(
					'filename' => htmlspecialchars_uni($attachment['name']),
					'error' => $error
				);
			}
			else
			{
				if (process_upload($foruminfo['moderateattach'], $attachment, $errors))
				{
					if (build_attachment($foruminfo['moderateattach'], $attachment, $errors))
					{
						if ($bbuserinfo['userid'] != $postinfo['userid'] AND can_moderate($threadinfo['forumid'], 'caneditposts'))
						{
							require_once('./includes/functions_log_error.php');
							log_moderator_action($postinfo, $vbphrase['attachment_uploaded']);
						}
					}
				}
				else
				{
					$attachcount--;
				}
			}
		}
		if (is_array($errors))
		{
			$errorlist = '';
			foreach ($errors AS $error)
			{
				$filename = htmlspecialchars_uni($error['filename']);
				$errormessage = $error['error'];
				eval('$errorlist .= "' . fetch_template('newattachment_errormessage') . '";');
			}
			$show['errors'] = true;
		}
	}
}

// <-- This is done in two queries since Mysql will not use an index on an OR query which gives a full table scan of the attachment table

$stopat = 1;
$currentattaches1 = $DB_site->query("
	SELECT filename, filesize, attachmentid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail
	FROM " . TABLE_PREFIX . "attachment
	WHERE posthash = '$posthash'
		AND userid = " . iif($postinfo['postid'], $postinfo['userid'], $bbuserinfo['userid']) . "
	ORDER BY dateline
");
if ($postid) // Attachments are being added from edit post
{
	$stopat = 2;
	$currentattaches2 = $DB_site->query("
		SELECT filename, filesize, attachmentid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail
		FROM " . TABLE_PREFIX . "attachment
		WHERE postid = $postid
		ORDER BY dateline
	");
}

$attachcount = 0;
$totalsize = 0;
for ($x = $stopat; $x > 0; $x--)
{
	$currentattaches = &${currentattaches . $x};
	while ($attach = $DB_site->fetch_array($currentattaches))
	{
		$attach['extension'] = strtolower(file_extension($attach['filename']));
		$attach['filename'] = htmlspecialchars_uni($attach['filename']);
		$attachcount++;
		$totalsize += intval($attach['filesize']);
		$attach['filesize'] = vb_number_format($attach['filesize'], 1, true);
		$show['thumbnail'] = iif($attach['hasthumbnail'], true, false);
		eval('$attachments .= "' . fetch_template('newattachmentbit') . '";');

		eval('$parentattach .= "' . fetch_template('newpost_attachmentbit', 0, 0) . '";');
	}
}

$totallimit = vb_number_format($totalsize, 1, true);

if ($postid)
{
	$user = fetch_userinfo($postinfo['userid']);
	cache_permissions($user, true);
	$perms = $user['forumpermissions'];
	$attachlimit = $user['permissions']['attachlimit'];
}
else
{
	$perms = $bbuserinfo['forumpermissions'];
	$attachlimit = $permissions['attachlimit'];
}

if ($attachlimit)
{
	// Get forums that allow canview access
	foreach ($perms AS $pforumid => $fperm)
	{
		if (($fperm & CANVIEW) AND ($fperm & CANGETATTACHMENT))
		{
			$forumids .= ",$pforumid";
		}
	}
	unset($pforumid);

	$attachdata = $DB_site->query_first("
		SELECT SUM(attachment.filesize) AS sum
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (attachment.postid = deletionlog.primaryid AND type = 'post')
		WHERE attachment.userid = " . iif($postid, $postinfo['userid'], $bbuserinfo['userid']) . "
				AND	((forumid IN(0$forumids) AND deletionlog.primaryid IS NULL) OR attachment.postid = 0)
	");
	$attachsum = intval($attachdata['sum']);
	if ($attachsum >= $attachlimit)
	{
		$totalsize = 0;
		$attachsize = 100;
	}
	else
	{
		$attachsize = ceil($attachsum / $attachlimit * 100);
		$totalsize = 100 - $attachsize;
	}

	$attachsum = vb_number_format($attachsum, 1, true);
	$attachlimit = vb_number_format($attachlimit, 1, true);
	$show['attachmentlimits'] = true;
	$show['currentsize'] = iif($attachsize, true, false);
	$show['totalsize'] = iif($totalsize, true, false);
}
else
{
	$show['attachmentlimits'] = false;
	$show['currentsize'] = false;
	$show['totalsize'] = false;
}

if (($attachcount >= $vboptions['attachlimit'] AND $vboptions['attachlimit']) OR !$foruminfo['allowposting'])
{
	$show['attachoption'] = false;
	if (!$foruminfo['allowposting'])
	{
		$show['forumclosed'] = true;
	}
}
else
{
	$vboptions['attachboxcount'] = iif ($vboptions['attachboxcount'], $vboptions['attachboxcount'], 1);
	// If we have unlimited attachments, set filesleft to box count
	$filesleft = iif($vboptions['attachlimit'], $vboptions['attachlimit'] - $attachcount, $vboptions['attachboxcount']);
	$filesleft = iif ($filesleft < $vboptions['attachboxcount'], $filesleft, $vboptions['attachboxcount']);

	$show['attachoption'] = true;
	$boxcount = 1;
	$attachinput = '';
	while ($boxcount <= $filesleft)
	{
		$attachinput .= "<input type=\"file\" class=\"bginput\" name=\"attachment$boxcount\" />\n";
		$boxcount++;
	}

	$vbphrase['upload_word'] = iif (is_browser('safari'), $vbphrase['choose_file'], $vbphrase['browse']);
}

$show['attachmentlist'] = iif($attachments, true, false);

$inimaxattach = fetch_max_attachment_size();
if ($parentattach)
{
	$parentattach = str_replace('"', '\"', $parentattach);
	$show['updateparent'] = true;
}

$vbphrase['select_a_file_to_attach'] = str_replace('"', '\"', $vbphrase['select_a_file_to_attach']);

// complete
eval('print_output("' . fetch_template('newattachment') . '");');


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11097 $
|| ####################################################################
\*======================================================================*/
?>