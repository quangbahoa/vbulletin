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
define('THIS_SCRIPT', 'newthread');

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
	'newpost_attachment',
	'newpost_attachmentbit',
	'newthread'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_newpost.php');
require_once('./includes/functions_editor.php');
require_once('./includes/functions_bigthree.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$attachtypes = unserialize($datastore['attachmentcache']);

// ### STANDARD INITIALIZATIONS ###
$checked = array();
$newpost = array();

// get decent textarea size for user's browser
$textareacols = fetch_textarea_width();

// sanity checks...
if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'newthread';
}

$forumid = verify_id('forum', $_REQUEST['forumid']);

if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
{
	eval(print_standard_error('error_forumclosed'));
}

$forumperms = fetch_permissions($forumid);
if (!($forumperms & CANVIEW) OR !($forumperms & CANPOSTNEW))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

// ############################### start post thread ###############################
if ($_POST['do'] == 'postthread')
{

	globalize($_POST, array('posthash' => STR_NOHTML, 'poststarttime' => STR_NOHTML));

	if (isset($_POST['WYSIWYG_HTML']))
	{
		require_once('./includes/functions_wysiwyg.php');
		$newpost['message'] = convert_wysiwyg_html_to_bbcode($_POST['WYSIWYG_HTML'], $foruminfo['allowhtml']);
	}
	else
	{
		$newpost['message'] = &$_POST['message'];
	}

	if (!($forumperms & CANPOSTPOLL))
	{
		unset($_POST['postpoll']);
	}

	$newpost['title'] = $_POST['subject'];
	$newpost['iconid'] = $_POST['iconid'];
	$newpost['parseurl'] = $_POST['parseurl'];
	$newpost['email'] = $_POST['email'];
	$newpost['signature'] = $_POST['signature'];
	$newpost['preview'] = $_POST['preview'];
	$newpost['disablesmilies'] = $_POST['disablesmilies'];
	$newpost['rating'] = $_POST['rating'];
	$newpost['username'] = $_POST['username'];
	$newpost['postpoll'] = $_POST['postpoll'];
	$newpost['polloptions'] = intval($_POST['polloptions']);
	$newpost['folderid'] = $_POST['folderid'];
	$newpost['emailupdate'] = $_POST['emailupdate'];
	$newpost['poststarttime'] = $poststarttime;
	$newpost['posthash'] = $posthash;
	// moderation options
	$newpost['stickunstick'] = $_POST['stickunstick'];
	$newpost['openclose'] = $_POST['openclose'];

	build_new_post('thread', $foruminfo, array(), 0, $newpost, $errors);

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
		// ### PREVIEW POST ###
		$postpreview = process_post_preview($newpost);
		$_REQUEST['do'] = 'newthread';
		$newpost['message'] = htmlspecialchars_uni($newpost['message']);
	}
	else
	{
		// ### NOT PREVIEW - ACTUAL POST ###
		if ($newpost['postpoll'])
		{
			$url = "poll.php?$session[sessionurl]t=$newpost[threadid]&amp;polloptions=$newpost[polloptions]";
		}
		else if ($newpost['visible'])
		{
			$url = "showthread.php?$session[sessionurl]p=$newpost[postid]#post$newpost[postid]";
		}
		else
		{
			$_REQUEST['forceredirect'] = 1;
			$url = "forumdisplay.php?$session[sessionurl]f=$foruminfo[forumid]";
		}
		eval(print_standard_redirect('redirect_postthanks'));
	} // end if
}

// ############################### start new thread ###############################
if ($_REQUEST['do'] == 'newthread')
{

	construct_edit_toolbar($newpost['message'], 0, $foruminfo['forumid'], $foruminfo['allowsmilies']);

	$posticons = construct_icons(intval($newpost['iconid']), $foruminfo['allowicons']);

	if (!isset($checked['parseurl']))
	{
		$checked['parseurl'] = HTML_CHECKED;
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
		$polloptions = intval($newpost['polloptions']);
	}

	// Get subscribed thread folders
	$newpost['folderid'] = iif($newpost['folderid'], $newpost['folderid'], 0);
	$folders = unserialize($bbuserinfo['subfolders']);
	// Don't show the folderjump if we only have one folder, would be redundant ;)
	if (sizeof($folders) > 1)
	{
		require_once('./includes/functions_misc.php');
		$folderbits = construct_folder_jump(1, $newpost['folderid'], false, $folders);
	}
	$show['subscribefolders'] = iif($folderbits, true, false);

	// get the checked option for auto subscription
	$emailchecked = fetch_emailchecked($threadinfo, $bbuserinfo, $newpost);

	// check to see if signature required
	if ($bbuserinfo['userid'] AND !$postpreview)
	{
		if ($bbuserinfo['signature'] != '')
		{
			$checked['signature'] = HTML_CHECKED;
		}
		else
		{
			$checked['signature'] = '';
		}
	}

	if ($forumperms & CANPOSTPOLL)
	{
		$show['poll'] = true;
	}
	else
	{
		$show['poll'] = false;
	}

	// get attachment options
	require_once('./includes/functions_file.php');
	$inimaxattach = fetch_max_attachment_size();

	$maxattachsize = vb_number_format($inimaxattach, 1, true);
	$attachcount = 0;
	if ($forumperms & CANPOSTATTACHMENT AND $bbuserinfo['userid'])
	{
		if (!$posthash OR !$poststarttime)
		{
			$poststarttime = TIMENOW;
			$posthash = md5($poststarttime . $bbuserinfo['userid'] . $bbuserinfo['salt']);
		}
		else
		{
			$currentattaches = $DB_site->query("
				SELECT filename, filesize
				FROM " . TABLE_PREFIX . "attachment
				WHERE posthash = '" . addslashes($newpost['posthash']) . "'
					AND userid = $bbuserinfo[userid]
			");

			while ($attach = $DB_site->fetch_array($currentattaches))
			{
				$attach['extension'] = strtolower(file_extension($attach['filename']));
				$attach['filename'] = htmlspecialchars_uni($attach['filename']);
				$attach['filesize'] = vb_number_format($attach['filesize'], 1, true);
				$show['attachmentlist'] = true;
				eval('$attachments .= "' . fetch_template('newpost_attachmentbit') . '";');
			}
		}
		$attachurl = "f=$foruminfo[forumid]";
		eval('$attachmentoption = "' . fetch_template('newpost_attachment') . '";');
	}
	else
	{
		$attachmentoption = '';
	}


	$subject = $newpost['title'];

	// get username code
	$currentpage = urlencode("newthread.php?f=$foruminfo[forumid]");
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// can this user open / close this thread?
	if (($bbuserinfo['userid'] AND $forumperms & CANOPENCLOSE) OR can_moderate($threadinfo['forumid'], 'canopenclose'))
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
		$forumTitle = $forumcache["$forumID"]['title'];
		$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
	}
	$navbits[''] = $vbphrase['post_new_thread'];
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	construct_forum_rules($foruminfo, $forumperms);

	eval('print_output("' . fetch_template('newthread') . '");');

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 10852 $
|| ####################################################################
\*======================================================================*/
?>