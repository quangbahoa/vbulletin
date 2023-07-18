<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(0);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('GET_EDIT_TEMPLATES', true);
define('THIS_SCRIPT', 'newattachment');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'newattachment',
	'newattachmentbit',
	'newpost_attachmentbit',
	'newattachment_errormessage',
	'newattachment_keybit',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_file.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!$vbulletin->userinfo['userid']) // Guests can not post attachments
{
	print_no_permission();
}

// Variables that are reused in templates
$editpost      =& $vbulletin->input->clean_gpc('r', 'editpost',      TYPE_BOOL);
$poststarttime =& $vbulletin->input->clean_gpc('r', 'poststarttime', TYPE_UINT);
$posthash      =& $vbulletin->input->clean_gpc('r', 'posthash',      TYPE_NOHTML);

if ($postid AND $vbulletin->GPC['editpost'])
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
	eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
}

$forumperms = fetch_permissions($foruminfo['forumid']);

// No permissions to post attachments in this forum or no permission to view threads in this forum.
if (empty($vbulletin->userinfo['attachmentextensions']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
{
	print_no_permission();
}

if ((!$postid AND !$foruminfo['allowposting']) OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
{
	eval(standard_error(fetch_error('forumclosed')));
}

if ($threadid) // newreply.php or editpost.php called
{
	if ($threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
	}
	if (!$threadinfo['open'])
	{
		if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
		{
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
			eval(standard_error(fetch_error('threadclosed')));
		}
	}
	if (($vbulletin->userinfo['userid'] != $threadinfo['postuserid']) AND (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])))
	{
		print_no_permission();
	}

	// don't call this part on editpost.php (which will have a $postid)
	if (!$postid AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) AND $vbulletin->userinfo['userid'] == $threadinfo['postuserid'])
	{
		print_no_permission();
	}
}
else if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew'])) // newthread.php
{
	print_no_permission();
}

if ($postid) // editpost.php
{
	if (!can_moderate($threadinfo['forumid'], 'caneditposts'))
	{
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['caneditpost']))
		{
			print_no_permission();
		}
		else
		{
			if ($vbulletin->userinfo['userid'] != $postinfo['userid'])
			{
				// check user owns this post
				print_no_permission();
			}
			else
			{
				// check for time limits
				if ($postinfo['dateline'] < (TIMENOW - ($vbulletin->options['edittimelimit'] * 60)) AND $vbulletin->options['edittimelimit'])
				{
					eval(standard_error(fetch_error('edittimelimit', $vbulletin->options['edittimelimit'], $vbulletin->options['contactuslink'])));
				}
			}
		}
	}
}

$parentattach = '';
$parentclickattach = '';
$new_attachlist_js = '';

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

if ($posthash != md5($poststarttime . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('newattachment_start')) ? eval($hook) : false;

$show['errors'] = false;

$currentattaches = $db->query_first("
	SELECT COUNT(*) AS count
	FROM " . TABLE_PREFIX . "attachment
	WHERE posthash = '$posthash'
		AND userid = " . $vbulletin->userinfo['userid']
);
$attachcount = $currentattaches['count'];

if ($postid)
{
	$currentattaches = $db->query_first("
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
	$attach_username = $vbulletin->userinfo['username'];
}

if (!$foruminfo['allowposting'] AND !$attachcount)
{
	eval(standard_error(fetch_error('forumclosed')));
}

// ##################### Add Attachment to Post ####################
if ($_POST['do'] == 'manageattach')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'upload'	=> TYPE_STR,
		'delete'	=> TYPE_ARRAY_STR,
	));

	if (!$vbulletin->GPC['upload'])
	{
		if (!empty($vbulletin->GPC['delete']))
		{
			$attachdata = datamanager_init('Attachment', $vbulletin, ERRTYPE_STANDARD);

			$popid = array_keys($vbulletin->GPC['delete']);
			$attachmentid = intval($popid[0]);

			$attachdata->condition = "attachmentid = $attachmentid";
			if ($postid)
			{
				$attachdata->condition .= " AND (attachment.postid = $postid OR attachment.posthash = '" . $db->escape_string($posthash) . "')";
			}
			else
			{
				$attachdata->condition .= " AND attachment.posthash = '" . $db->escape_string($posthash) . "'";
			}
			if ($attachdata->delete())
			{
				$show['updateparent'] = true;
			}
		}
	}
	else
	{	// Attach file...
		$errors = array();
		$vbulletin->input->clean_gpc('f', 'attachment', TYPE_FILE);
		$vbulletin->input->clean_gpc('p', 'attachmenturl', TYPE_ARRAY_STR);

		require_once(DIR . '/includes/class_upload.php');
		require_once(DIR . '/includes/class_image.php');

		if ($postid)  // Editing a post
		{
			$userinfo = fetch_userinfo($postinfo['userid']);
			cache_permissions($userinfo, true);
			$postinfo['posthash'] =& $posthash;
		}
		else
		{
			$postinfo = array('posthash' => $posthash);
		}

		// check for any funny business
		$filecount = 1;
		if (!empty($vbulletin->GPC['attachment']['tmp_name']))
		{
			foreach ($vbulletin->GPC['attachment']['tmp_name'] AS $filename)
			{
				if (!empty($filename))
				{
					if ($filecount > $vbulletin->options['attachboxcount'])
					{
						@unlink($filename);
					}
					$filecount++;
				}
			}
		}

		// Move any urls into the attachment array if we allow url upload
		if ($vbulletin->options['attachurlcount'])
		{
			$urlcount = 1;
			foreach ($vbulletin->GPC['attachmenturl'] AS $url)
			{
				if (!empty($url) AND $urlcount <= $vbulletin->options['attachurlcount'])
				{
					$index = count($vbulletin->GPC['attachment']['name']);
					$vbulletin->GPC['attachment']['name']["$index"] = $url;
					$vbulletin->GPC['attachment']['url']["$index"] = true;
					$urlcount++;
				}
			}
		}

		$uploadsum = count($vbulletin->GPC['attachment']['name']);
		for ($x = 0; $x < $uploadsum; $x++)
		{
			// These are created each go around to insure memory has been freed
			$attachdata = datamanager_init('Attachment', $vbulletin, ERRTYPE_ARRAY);
			$upload = new vB_Upload_Attachment($vbulletin);
			$image = vB_Image::fetch_library($vbulletin);

			$upload->data =& $attachdata;
			$upload->image =& $image;
			if ($uploadsum > 1)
			{
				$upload->emptyfile = false;
			}

			$upload->foruminfo =& $foruminfo;

			if ($postid)  // Editing a post
			{
				$upload->userinfo =& $userinfo;
			}

			$upload->postinfo =& $postinfo;

			if ($vbulletin->GPC['attachment']['url']["$x"])
			{
				$attachment =& $vbulletin->GPC['attachment']['name']["$x"];
			}
			else
			{
				$attachment = array(
					'name'     =>& $vbulletin->GPC['attachment']['name']["$x"],
					'tmp_name' =>& $vbulletin->GPC['attachment']['tmp_name']["$x"],
					'error'    =>&	$vbulletin->GPC['attachment']['error']["$x"],
					'size'     =>& $vbulletin->GPC['attachment']['size']["$x"],
				);
			}

			$attachcount++;
			if (!$foruminfo['allowposting'])
			{
				$error = $vbphrase['this_forum_is_not_accepting_new_attachments'];
				$errors[] = array(
					'filename' => $attachment['name'],
					'error'    => $error
				);
			}
			else if ($vbulletin->options['attachlimit'] AND $attachcount > $vbulletin->options['attachlimit'])
			{
				$error = construct_phrase($vbphrase['you_may_only_attach_x_files_per_post'], $vbulletin->options['attachlimit']);
				$errors[] = array(
					'filename' => $attachment['name'],
					'error'    => $error
				);
			}
			else
			{

				if ($attachmentid = $upload->process_upload($attachment))
				{
					if ($vbulletin->userinfo['userid'] != $postinfo['userid'] AND can_moderate($threadinfo['forumid'], 'caneditposts'))
					{
						$postinfo['attachmentid'] =& $attachmentid;
						$postinfo['forumid'] =& $foruminfo['forumid'];
						require_once(DIR . '/includes/functions_log_error.php');
						log_moderator_action($postinfo, 'attachment_uploaded');
					}
				}
				else
				{
					$attachcount--;
				}

				if ($error = $upload->fetch_error())
				{
					$errors[] = array(
						'filename' => is_array($attachment) ? $attachment['name'] : $attachment,
						'error'    => $error,
					);
				}

			}
		}

		($hook = vBulletinHook::fetch_hook('newattachment_attach')) ? eval($hook) : false;

		if (!empty($errors))
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
// could use an UNION in 4.1+

$stopat = 1;
$currentattaches1 = $db->query_read("
	SELECT dateline, filename, filesize, attachmentid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail
	FROM " . TABLE_PREFIX . "attachment
	WHERE posthash = '$posthash'
		AND userid = " . iif($postinfo['postid'], $postinfo['userid'], $vbulletin->userinfo['userid']) . "
	ORDER BY attachmentid
");
if ($postid) // Attachments are being added from edit post
{
	$stopat = 2;
	$currentattaches2 = $db->query_read_slave("
		SELECT dateline, filename, filesize, attachmentid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail
		FROM " . TABLE_PREFIX . "attachment
		WHERE postid = $postid
		ORDER BY attachmentid
	");
}

require_once(DIR . '/includes/functions_editor.php');
$wysiwyg = is_wysiwyg_compatible();

$attachcount = 0;
$totalsize = 0;
for ($x = $stopat; $x > 0; $x--)
{
	$currentattaches =& ${currentattaches . $x};
	while ($attach = $db->fetch_array($currentattaches))
	{
		$attach['extension'] = strtolower(file_extension($attach['filename']));
		$attach['filename'] = htmlspecialchars_uni($attach['filename']);
		$attachcount++;
		$totalsize += intval($attach['filesize']);
		$attach['filesize'] = vb_number_format($attach['filesize'], 1, true);
		$show['thumbnail'] = iif($attach['hasthumbnail'], true, false);
		eval('$attachments .= "' . fetch_template('newattachmentbit') . '";');

		eval('$parentattach .= "' . fetch_template('newpost_attachmentbit', 0, 0) . '";');

		$new_attachlist_js .= "window.opener.vB_Attachments.add($attach[attachmentid], '" . addslashes_js($attach['filename']) . "', '" . addslashes_js($attach['filesize']) . "', '$stylevar[imgdir_attach]/$attach[extension].gif');\n";

		if ($wysiwyg == 1)
		{
			$attach['filename'] = fetch_trimmed_title($attach['filename'], 12);
		}
		$parentclickattach .= "attachoptions[$attach[attachmentid]] = new Array();\n";
		$parentclickattach .= "attachoptions[$attach[attachmentid]][\"f\"] = \"$attach[filename]\";\n";
		$parentclickattach .= "attachoptions[$attach[attachmentid]][\"e\"] = \"$attach[extension]\";\n";
	}
}

$totallimit = vb_number_format($totalsize, 1, true);

if ($postid)
{
	$userinfo = fetch_userinfo($postinfo['userid']);
	cache_permissions($userinfo, true);
	$perms = $userinfo['forumpermissions'];
	$attachlimit = $userinfo['permissions']['attachlimit'];
}
else
{
	$userinfo =& $vbulletin->userinfo;
	$perms = $vbulletin->userinfo['forumpermissions'];
	$attachlimit = $permissions['attachlimit'];
}

if ($attachlimit)
{
	// Get forums that allow canview access
	foreach ($perms AS $pforumid => $fperm)
	{
		if (($fperm & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($fperm & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
		{
			$forumids .= ",$pforumid";
		}
	}
	unset($pforumid);

	$attachdata = $db->query_first("
		SELECT SUM(attachment.filesize) AS sum
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		WHERE attachment.userid = " . iif($postid, $postinfo['userid'], $vbulletin->userinfo['userid']) . "
				AND	((forumid IN(-1$forumids) AND thread.visible <> 2 AND post.visible <> 2)
				OR attachment.postid = 0)
	");
	$attachsum = intval($attachdata['sum']);

	($hook = vBulletinHook::fetch_hook('newattachment_attachsum')) ? eval($hook) : false;

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
if (($attachcount >= $vbulletin->options['attachlimit'] AND $vbulletin->options['attachlimit']) OR !$foruminfo['allowposting'])
{
	$show['attachoption'] = false;
	if (!$foruminfo['allowposting'])
	{
		$show['forumclosed'] = true;
	}
}
else
{
	// If we have unlimited attachments, set filesleft to box count
	if ($vbulletin->options['attachboxcount'])
	{
		$show['attachoption'] = true;
		$show['attachfile'] = true;
		$filesleft = iif($vbulletin->options['attachlimit'], $vbulletin->options['attachlimit'] - $attachcount, $vbulletin->options['attachboxcount']);
		$filesleft = iif($filesleft < $vbulletin->options['attachboxcount'], $filesleft, $vbulletin->options['attachboxcount']);

		$boxcount = 1;
		$attachinput = '';
		while ($boxcount <= $filesleft)
		{
			$attachinput .= "<input type=\"file\" class=\"bginput\" name=\"attachment[]\" size=\"30\" /><br />\n";
			$boxcount++;
		}
	}

	if ($vbulletin->options['attachurlcount'] AND (ini_get('allow_url_fopen') != 0 OR function_exists('curl_init')))
	{
		$show['attachoption'] = true;
		$show['attachurl'] = true;
		$filesleft = iif($vbulletin->options['attachlimit'], $vbulletin->options['attachlimit'] - $attachcount, $vbulletin->options['attachurlcount']);
		$filesleft = iif($filesleft < $vbulletin->options['attachurlcount'], $filesleft, $vbulletin->options['attachurlcount']);

		$boxcount = 1;
		$attachurlinput = '';
		while ($boxcount <= $filesleft)
		{
			$attachurlinput .= "<input type=\"text\" class=\"bginput\" name=\"attachmenturl[]\" size=\"30\" dir=\"ltr\" /><br />\n";
			$boxcount++;
		}
	}

	$vbphrase['upload_word'] = iif (is_browser('safari'), $vbphrase['choose_file'], $vbphrase['browse']);
}

$show['attachmentlist'] = iif($attachments, true, false);

$inimaxattach = fetch_max_upload_size();
if ($parentattach)
{
	$parentattach = str_replace('"', '\"', $parentattach);
	$show['updateparent'] = true;
}

($hook = vBulletinHook::fetch_hook('newattachment_complete')) ? eval($hook) : false;

foreach($userinfo['attachmentpermissions'] AS $filetype => $extension)
{
	if (!empty($extension['permissions']))
	{
		exec_switch_bg();
		$extension['size'] = $extension['size'] > 0 ? vb_number_format($extension['size'], 1, true) : '-';
		$extension['width'] = $extension['width'] > 0 ? $extension['width'] : '-';
		$extension['height'] = $extension['height'] > 0 ? $extension['height'] : '-';
		$extension['extension'] = $filetype;
		eval('$attachkeybits .= "' . fetch_template('newattachment_keybit') . '";');
	}
}
$show['updateparent'] = true;
// complete
eval('print_output("' . fetch_template('newattachment') . '");');


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
