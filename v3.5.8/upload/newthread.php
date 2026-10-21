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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('GET_EDIT_TEMPLATES', true);
define('THIS_SCRIPT', 'newthread');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('threadmanage', 'posting');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'attachmentcache',
	'ranks',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'newpost_attachment',
	'newpost_attachmentbit',
	'newthread'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_editor.php');
require_once(DIR . '/includes/functions_bigthree.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$attachtypes =& $vbulletin->attachmentcache;

// ### STANDARD INITIALIZATIONS ###
$checked = array();
$newpost = array();
$postattach = array();

// get decent textarea size for user's browser
$textareacols = fetch_textarea_width();

// sanity checks...
if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'newthread';
}

($hook = vBulletinHook::fetch_hook('newthread_start')) ? eval($hook) : false;

if (!$foruminfo['forumid'])
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['forum'], $vbulletin->options['contactuslink'])));
}

if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
{
	eval(standard_error(fetch_error('forumclosed')));
}

$forumperms = fetch_permissions($forumid);
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

// ############################### start post thread ###############################
if ($_POST['do'] == 'postthread')
{
	// Variables reused in templates
	$posthash = $vbulletin->input->clean_gpc('p', 'posthash', TYPE_NOHTML);
	$poststarttime = $vbulletin->input->clean_gpc('p', 'poststarttime', TYPE_UINT);

	$vbulletin->input->clean_array_gpc('p', array(
		'wysiwyg'			=> TYPE_BOOL,
		'message'			=> TYPE_STR,
		'postpoll'			=> TYPE_BOOL,
		'subject'			=> TYPE_STR,
		'iconid'			=> TYPE_UINT,
		'signature'			=> TYPE_BOOL,
		'preview'			=> TYPE_STR,
		'disablesmilies'	=> TYPE_BOOL,
		'rating'			=> TYPE_UINT,
		'polloptions'		=> TYPE_UINT,
		'folderid'			=> TYPE_UINT,
		'emailupdate'		=> TYPE_UINT,
		'stickunstick'		=> TYPE_BOOL,
		'openclose'			=> TYPE_BOOL,
		'parseurl'			=> TYPE_BOOL,
		'username'			=> TYPE_STR,
		'loggedinuser'      => TYPE_INT
	));

	if ($vbulletin->GPC['loggedinuser'] != 0 AND $vbulletin->userinfo['userid'] == 0)
	{
		// User was logged in when writing post but isn't now. If we got this
		// far, guest posts are allowed, but they didn't enter a username so
		// they'll get an error. Force them to log back in.
		standard_error(fetch_error('session_timed_out_login'), '', false, 'STANDARD_ERROR_LOGIN');
	}

	($hook = vBulletinHook::fetch_hook('newthread_post_start')) ? eval($hook) : false;

	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$newpost['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $foruminfo['allowhtml']);
	}
	else
	{
		$newpost['message'] =& $vbulletin->GPC['message'];
	}

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostpoll']))
	{
		$vbulletin->GPC['postpoll'] = false;
	}

	$newpost['title'] =& $vbulletin->GPC['subject'];
	$newpost['iconid'] =& $vbulletin->GPC['iconid'];
	$newpost['parseurl'] = ($foruminfo['allowbbcode'] AND $vbulletin->GPC['parseurl']);
	$newpost['signature'] =& $vbulletin->GPC['signature'];
	$newpost['preview'] =& $vbulletin->GPC['preview'];
	$newpost['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
	$newpost['rating'] =& $vbulletin->GPC['rating'];
	$newpost['username'] =& $vbulletin->GPC['username'];
	$newpost['postpoll'] =& $vbulletin->GPC['postpoll'];
	$newpost['polloptions'] =& $vbulletin->GPC['polloptions'];
	$newpost['folderid'] =& $vbulletin->GPC['folderid'];
	$newpost['emailupdate'] =& $vbulletin->GPC['emailupdate'];
	$newpost['poststarttime'] = $poststarttime;
	$newpost['posthash'] = $posthash;
	// moderation options
	$newpost['stickunstick'] =& $vbulletin->GPC['stickunstick'];
	$newpost['openclose'] =& $vbulletin->GPC['openclose'];

	build_new_post('thread', $foruminfo, array(), array(), $newpost, $errors);

	if (sizeof($errors) > 0)
	{
		// ### POST HAS ERRORS ###
		$postpreview = construct_errors($errors); // this will take the preview's place
		construct_checkboxes($newpost);
		$_REQUEST['do'] = 'newthread';
		$newpost['message'] = htmlspecialchars_uni($newpost['message']);
	}
	else if ($newpost['preview'])
	{
		if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'])
		{
			// Attachments added
			$attachs = $db->query_read("
				SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
					IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
					attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
				FROM " . TABLE_PREFIX . "attachment AS attachment
				LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
				WHERE posthash = '" . $db->escape_string($posthash) . "'
					AND userid = " . $vbulletin->userinfo['userid'] . "
				ORDER BY attachmentid
			");
			while ($attachment = $db->fetch_array($attachs))
			{
				if (!$attachment['build_thumbnail'])
				{
					$attachment['hasthumbnail'] = false;
				}
				$postattach["$attachment[attachmentid]"] = $attachment;
			}
		}

		// ### PREVIEW POST ###
		$postpreview = process_post_preview($newpost, 0 , $postattach);
		$_REQUEST['do'] = 'newthread';
		$newpost['message'] = htmlspecialchars_uni($newpost['message']);
	}
	else
	{
		// ### NOT PREVIEW - ACTUAL POST ###
		$threadinfo = fetch_threadinfo($newpost['threadid']); // need the forumread variable from this
		mark_thread_read($threadinfo, $foruminfo, $vbulletin->userinfo['userid'], TIMENOW);

		if ($newpost['postpoll'])
		{
			$forceredirect = false;
			$vbulletin->url = 'poll.php?' . $vbulletin->session->vars['sessionurl'] . "t=$newpost[threadid]&polloptions=$newpost[polloptions]";
		}
		else if ($newpost['visible'])
		{
			$forceredirect = false;
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$newpost[postid]#post$newpost[postid]";
		}
		else
		{
			$forceredirect = true;
			$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[forumid]";
		}

		($hook = vBulletinHook::fetch_hook('newthread_post_complete')) ? eval($hook) : false;
		eval(print_standard_redirect('redirect_postthanks', true, $forceredirect));
	} // end if
}

// ############################### start new thread ###############################
if ($_REQUEST['do'] == 'newthread')
{
	($hook = vBulletinHook::fetch_hook('newthread_form_start')) ? eval($hook) : false;

	$posticons = construct_icons($newpost['iconid'], $foruminfo['allowicons']);

	if (!isset($checked['parseurl']))
	{
		$checked['parseurl'] = 'checked="checked"';
	}

	if (!isset($checked['postpoll']))
	{
		$checked['postpoll'] = '';
	}

	if (!isset($newpost['polloptions']))
	{
		$polloptions = 4;
	}
	else
	{
		$polloptions = $newpost['polloptions'];
	}

	// Get subscribed thread folders
	$newpost['folderid'] = iif($newpost['folderid'], $newpost['folderid'], 0);
	$folders = unserialize($vbulletin->userinfo['subfolders']);
	// Don't show the folderjump if we only have one folder, would be redundant ;)
	if (sizeof($folders) > 1)
	{
		require_once(DIR . '/includes/functions_misc.php');
		$folderbits = construct_folder_jump(1, $newpost['folderid'], false, $folders);
	}
	$show['subscribefolders'] = iif($folderbits, true, false);

	// get the checked option for auto subscription
	$emailchecked = fetch_emailchecked($threadinfo, $vbulletin->userinfo, $newpost);

	// check to see if signature required
	if ($vbulletin->userinfo['userid'] AND !$postpreview)
	{
		if ($vbulletin->userinfo['signature'] != '')
		{
			$checked['signature'] = 'checked="checked"';
		}
		else
		{
			$checked['signature'] = '';
		}
	}

	if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostpoll'])
	{
		$show['poll'] = true;
	}
	else
	{
		$show['poll'] = false;
	}

	// get attachment options
	require_once(DIR . '/includes/functions_file.php');
	$inimaxattach = fetch_max_upload_size();

	$maxattachsize = vb_number_format($inimaxattach, 1, true);
	$attachcount = 0;
	$attach_editor = array();
	$attachment_js = '';

	if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'])
	{
		if (!$posthash OR !$poststarttime)
		{
			$poststarttime = TIMENOW;
			$posthash = md5($poststarttime . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);
		}
		else
		{
			if (empty($postattach))
			{
				$currentattaches = $db->query_read("
					SELECT dateline, filename, filesize, attachmentid
					FROM " . TABLE_PREFIX . "attachment
					WHERE posthash = '" . $db->escape_string($newpost['posthash']) . "'
						AND userid = " . $vbulletin->userinfo['userid']
				);

				while ($attach = $db->fetch_array($currentattaches))
				{
					$postattach["$attach[attachmentid]"] = $attach;
				}
			}

			if (!empty($postattach))
			{
				foreach($postattach AS $attachmentid => $attach)
				{
					$attach['extension'] = strtolower(file_extension($attach['filename']));
					$attach['filename'] = htmlspecialchars_uni($attach['filename']);
					$attach['filesize'] = vb_number_format($attach['filesize'], 1, true);
					$attach['imgpath'] = "$stylevar[imgdir_attach]/$attach[extension].gif";
					$show['attachmentlist'] = true;
					eval('$attachments .= "' . fetch_template('newpost_attachmentbit') . '";');

					$attachment_js .= construct_attachment_add_js($attachmentid, $attach['filename'], $attach['filesize'], $attach['extension']);

					$attach_editor["$attachmentid"] = $attach['filename'];
				}
			}

		}
		$attachurl = "f=$foruminfo[forumid]";
		$newpost_attachmentbit = prepare_newpost_attachmentbit();
		eval('$attachmentoption = "' . fetch_template('newpost_attachment') . '";');

		$attach_editor['hash'] = $foruminfo['forumid'];
		$attach_editor['url'] = "newattachment.php?$session[sessionurl]f=$foruminfo[forumid]&amp;poststarttime=$poststarttime&amp;posthash=$posthash";
	}
	else
	{
		$attachmentoption = '';
	}

	$editorid = construct_edit_toolbar(
		$newpost['message'],
		0,
		$foruminfo['forumid'],
		$foruminfo['allowsmilies'],
		1,
		($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'])
	);

	$subject = $newpost['title'];

	// get username code
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// can this user open / close this thread?
	if (($vbulletin->userinfo['userid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']) OR can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
		$threadinfo['open'] = 1;
		$show['openclose'] = true;
		$show['closethread'] = true;
	}
	else
	{
		$show['openclose'] = false;
	}
	// can this user stick this thread?
	if (can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$threadinfo['sticky'] = 0;
		$show['stickunstick'] = true;
		$show['unstickthread'] = false;
	}
	else
	{
		$show['stickunstick'] = false;
	}
	if ($show['openclose'] OR $show['stickunstick'])
	{
		($hook = vBulletinHook::fetch_hook('newthread_form_threadmanage')) ? eval($hook) : false;
		eval('$threadmanagement = "' . fetch_template('newpost_threadmanage') . '";');
	}
	else
	{
		$threadmanagement = '';
	}

	// draw nav bar
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
	}
	$navbits[''] = $vbphrase['post_new_thread'];
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	construct_forum_rules($foruminfo, $forumperms);

	$show['parseurl'] = $foruminfo['allowbbcode'];
	$show['misc_options'] = ($vbulletin->userinfo['signature'] != '' OR $show['parseurl'] OR !empty($disablesmiliesoption));
	$show['additional_options'] = ($show['misc_options'] OR !empty($attachmentoption) OR $show['member'] OR $show['poll'] OR !empty($threadmanagement));

	($hook = vBulletinHook::fetch_hook('newthread_form_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('newthread') . '");');

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14285 $
|| ####################################################################
\*======================================================================*/
?>