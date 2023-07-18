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

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('GET_EDIT_TEMPLATES', true);
define('THIS_SCRIPT', 'newthread');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'threadmanage',
	'postbit',
	'posting',
	'prefix'
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'ranks',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'newpost_attachment',
	'newpost_attachmentbit',
	'newthread',
	'humanverify',
	'optgroup',
	'postbit_attachment',
	'postbit_attachmentimage',
	'postbit_attachmentthumbnail',
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
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$show['tag_option'] = ($vbulletin->options['threadtagging'] AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['cantagown']));

// ############################### start post thread ###############################
if ($_POST['do'] == 'postthread')
{
	// Variables reused in templates
	$posthash = $vbulletin->input->clean_gpc('p', 'posthash', TYPE_NOHTML);
	$poststarttime = $vbulletin->input->clean_gpc('p', 'poststarttime', TYPE_UINT);

	$vbulletin->input->clean_array_gpc('p', array(
		'wysiwyg'         => TYPE_BOOL,
		'preview'         => TYPE_STR,
		'message'         => TYPE_STR,
		'subject'         => TYPE_STR,
		'iconid'          => TYPE_UINT,
		'rating'          => TYPE_UINT,
		'prefixid'        => TYPE_NOHTML,
		'taglist'         => TYPE_NOHTML,

		'postpoll'        => TYPE_BOOL,
		'polloptions'     => TYPE_UINT,

		'signature'       => TYPE_BOOL,
		'disablesmilies'  => TYPE_BOOL,
		'parseurl'        => TYPE_BOOL,
		'folderid'        => TYPE_UINT,
		'emailupdate'     => TYPE_UINT,
		'stickunstick'    => TYPE_BOOL,
		'openclose'       => TYPE_BOOL,

		'username'        => TYPE_STR,
		'loggedinuser'    => TYPE_INT,

		'humanverify'     => TYPE_ARRAY,

		'podcasturl'      => TYPE_STR,
		'podcastsize'     => TYPE_UINT,
		'podcastexplicit' => TYPE_BOOL,
		'podcastkeywords' => TYPE_STR,
		'podcastsubtitle' => TYPE_STR,
		'podcastauthor'   => TYPE_STR,
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

	require_once(DIR . '/includes/functions_prefix.php');

	if (can_use_prefix($vbulletin->GPC['prefixid']))
	{
		$newpost['prefixid'] =& $vbulletin->GPC['prefixid'];
	}

	if ($show['tag_option'])
	{
		$newpost['taglist'] =& $vbulletin->GPC['taglist'];
	}
	$newpost['parseurl']        = (($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) AND $foruminfo['allowbbcode'] AND $vbulletin->GPC['parseurl']);
	$newpost['signature']       =& $vbulletin->GPC['signature'];
	$newpost['preview']         =& $vbulletin->GPC['preview'];
	$newpost['disablesmilies']  =& $vbulletin->GPC['disablesmilies'];
	$newpost['rating']          =& $vbulletin->GPC['rating'];
	$newpost['username']        =& $vbulletin->GPC['username'];
	$newpost['postpoll']        =& $vbulletin->GPC['postpoll'];
	$newpost['polloptions']     =& $vbulletin->GPC['polloptions'];
	$newpost['folderid']        =& $vbulletin->GPC['folderid'];
	$newpost['humanverify']     =& $vbulletin->GPC['humanverify'];
	$newpost['poststarttime']   = $poststarttime;
	$newpost['posthash']        = $posthash;
	// moderation options
	$newpost['stickunstick']    =& $vbulletin->GPC['stickunstick'];
	$newpost['openclose']       =& $vbulletin->GPC['openclose'];
	$newpost['podcasturl']      =& $vbulletin->GPC['podcasturl'];
	$newpost['podcastsize']     =& $vbulletin->GPC['podcastsize'];
	$newpost['podcastexplicit'] =& $vbulletin->GPC['podcastexplicit'];
	$newpost['podcastkeywords'] =& $vbulletin->GPC['podcastkeywords'];
	$newpost['podcastsubtitle'] =& $vbulletin->GPC['podcastsubtitle'];
	$newpost['podcastauthor']   =& $vbulletin->GPC['podcastauthor'];

	if ($vbulletin->GPC_exists['emailupdate'])
	{
		$newpost['emailupdate'] =& $vbulletin->GPC['emailupdate'];
	}
	else
	{
		$array = array_keys(fetch_emailchecked(array(), $vbulletin->userinfo));
		$newpost['emailupdate'] = array_pop($array);
	}

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
	{
		$newpost['emailupdate'] = 0;
	}

	build_new_post('thread', $foruminfo, array(), array(), $newpost, $errors);

	if (sizeof($errors) > 0)
	{
		// ### POST HAS ERRORS ###
		$postpreview = construct_errors($errors); // this will take the preview's place
		construct_checkboxes($newpost);
		$_REQUEST['do'] = 'newthread';
		$newpost['message'] = htmlspecialchars_uni($newpost['message']);
		$podcasturl = htmlspecialchars_uni($newpost['podcasturl']);
		$podcastsize = ($newpost['podcastsize']) ? $newpost['podcastsize'] : '';
		$podcastkeywords = htmlspecialchars_uni($newpost['podcastkeywords']);
		$podcastsubtitle = htmlspecialchars_uni($newpost['podcastsubtitle']);
		$podcastauthor = htmlspecialchars_uni($newpost['podcastauthor']);
		$explicitchecked = $newpost['podcastexplicit'] ? 'checked="checked"' : '';
	}
	else if ($newpost['preview'])
	{
		if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'] AND !empty($vbulletin->userinfo['attachmentextensions']))
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
		$podcasturl = htmlspecialchars_uni($newpost['podcasturl']);
		$podcastsize = ($newpost['podcastsize']) ? $newpost['podcastsize'] : '';
		$podcastkeywords = htmlspecialchars_uni($newpost['podcastkeywords']);
		$podcastsubtitle = htmlspecialchars_uni($newpost['podcastsubtitle']);
		$podcastauthor = htmlspecialchars_uni($newpost['podcastauthor']);
		$explicitchecked = $newpost['podcastexplicit'] ? 'checked="checked"' : '';
	}
	else
	{
		// ### NOT PREVIEW - ACTUAL POST ###
		$threadinfo = fetch_threadinfo($newpost['threadid']); // need the forumread variable from this
		mark_thread_read($threadinfo, $foruminfo, $vbulletin->userinfo['userid'], TIMENOW);

		($hook = vBulletinHook::fetch_hook('newthread_post_complete')) ? eval($hook) : false;
		if ($newpost['postpoll'])
		{
			$vbulletin->url = 'poll.php?' . $vbulletin->session->vars['sessionurl'] . "t=$newpost[threadid]&polloptions=$newpost[polloptions]";
			if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
			{
				eval(print_standard_redirect('redirect_postthanks', true, true));
			}
			else
			{
				eval(print_standard_redirect('redirect_postthanks_nopermission', true, true));
			}
		}
		else if ($newpost['visible'])
		{
			if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
			{
				$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$newpost[postid]#post$newpost[postid]";
				eval(print_standard_redirect('redirect_postthanks'));
			}
			else
			{
				$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[forumid]";
				eval(print_standard_redirect('redirect_postthanks_nopermission', true, true));
			}
		}
		else
		{
			$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[forumid]";
			eval(print_standard_redirect('redirect_postthanks_moderate', true, true));
		}
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
	$folders = vb_unserialize($vbulletin->userinfo['subfolders']);
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

	if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'] AND !empty($vbulletin->userinfo['attachmentextensions']))
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

		if (!isset($postid))
		{
			// this is just to avoid warnings while rendering newpost_attachment
			$postid = '';
		}
		
		eval('$attachmentoption = "' . fetch_template('newpost_attachment') . '";');

		$attach_editor['hash'] = $foruminfo['forumid'];
		$attach_editor['url'] = "newattachment.php?" . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[forumid]&amp;poststarttime=$poststarttime&amp;posthash=$posthash";
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
		($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'] AND !empty($vbulletin->userinfo['attachmentextensions']))
	);

	$subject = $newpost['title'];

	// display prefixes
	require_once(DIR . '/includes/functions_prefix.php');
	$prefix_options = fetch_prefix_html($foruminfo['forumid'], $newpost['prefixid'], true);

	// get username code
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	$show['podcasturl'] = ($foruminfo['podcast']);

	// can this user open / close this thread?
	if (($vbulletin->userinfo['userid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']) OR can_moderate($foruminfo['forumid'], 'canopenclose'))
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
	if (can_moderate($foruminfo['forumid'], 'canmanagethreads'))
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

	if (fetch_require_hvcheck('post'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verification = vB_HumanVerify::fetch_library($vbulletin);
		$human_verify = $verification->output_token();
	}
	else
	{
		$human_verify = '';
	}

	if ($show['tag_option'])
	{
		$tags_remain = null;
		if ($vbulletin->options['tagmaxthread'])
		{
			$tags_remain = $vbulletin->options['tagmaxthread'];
		}
		$forum_id = empty($threadinfo['forumid']) ? 0 : $threadinfo['forumid'];
		
		if ($vbulletin->options['tagmaxstarter'] AND !can_moderate($forum_id, 'caneditthreads'))
		{
			$tags_remain = ($tags_remain === null ? $vbulletin->options['tagmaxstarter'] : min($tags_remain, $vbulletin->options['tagmaxstarter']));
		}

		$show['tags_remain'] = ($tags_remain !== null);
		$tags_remain = vb_number_format($tags_remain);
		$tag_delimiters = addslashes_js($vbulletin->options['tagdelimiter']);
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

	$show['parseurl'] = (($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) AND $foruminfo['allowbbcode']);
	$show['misc_options'] = ($vbulletin->userinfo['signature'] != '' OR $show['parseurl'] OR !empty($disablesmiliesoption));
	$show['additional_options'] = ($show['misc_options'] OR !empty($attachmentoption) OR $show['member'] OR $show['poll'] OR !empty($threadmanagement));

	($hook = vBulletinHook::fetch_hook('newthread_form_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('newthread') . '");');

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
