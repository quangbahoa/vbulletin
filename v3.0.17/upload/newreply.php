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
define('THIS_SCRIPT', 'newreply');

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
	'newreply',
	'newpost_attachment',
	'newreply_reviewbit',
	'newreply_reviewbit_ignore',
	'newreply_reviewbit_ignore_global',
	'newpost_attachmentbit'
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
	$_REQUEST['do'] = 'newreply';
}

$threadid = intval($_REQUEST['threadid']);

// ### GET QUOTE FEATURES ###
// check for valid thread or post
if ($postid AND $_POST['do'] != 'postreply' AND empty($_REQUEST['noquote']))
{
	$postinfo = verify_id('post', $postid, 0, 1);
	$postid = $postinfo['postid'];
	if ($postid AND $postinfo['visible'] == 1 AND !$postinfo['isdeleted'])
	{
		$threadid = $postinfo['threadid'];
		if (!$postinfo['userid'])
		{
			$originalposter = $postinfo['username'];
		}
		else
		{
			$getusername = $DB_site->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = " . intval($postinfo['userid']));
			$originalposter = $getusername['username'];
		}
		$originalposter = fetch_quote_username($originalposter);

		$postdate = vbdate($vboptions['dateformat'], $postinfo['dateline']);
		$posttime = vbdate($vboptions['timeformat'], $postinfo['dateline']);
		$pagetext = htmlspecialchars_uni($postinfo['pagetext']);
		$pagetext = trim(strip_quotes($pagetext));
		eval('$newpost[\'message\'] = "' . fetch_template('newpost_quote', 1, 0) . '";');

		// fetch the quoted post title
		$newpost['title'] = htmlspecialchars_uni(fetch_quote_title($postinfo['title'], $threadinfo['title']));
	}
}
else if ($postid AND $_POST['do'] != 'postreply')
{
	$newpost['title'] = htmlspecialchars_uni(fetch_quote_title('', $threadinfo['title']));
}

// ### CHECK IF ALLOWED TO POST ###
if (!$threadinfo['visible'] OR $threadinfo['isdeleted'])
{
	$idname = $vbphrase['thread'];
	eval(print_standard_error('invalidid'));
}

if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
{
	eval(print_standard_error('forumclosed'));
}

if (!$threadinfo['open'])
{
	if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
		$url = "showthread.php?$session[sessionurl]t=$threadid";
		eval(print_standard_error('threadclosed'));
	}
}

$forumperms = fetch_permissions($foruminfo['forumid']);
if (($bbuserinfo['userid'] != $threadinfo['postuserid'] OR !$bbuserinfo['userid']) AND (!($forumperms & CANVIEWOTHERS) OR !($forumperms & CANREPLYOTHERS)))
{
	print_no_permission();
}
if (!($forumperms & CANVIEW) OR (!($forumperms & CANREPLYOWN) AND $bbuserinfo['userid'] == $threadinfo['postuserid']))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

if (!($forumperms & CANPOSTATTACHMENT) AND isset($_FILES['attachment']))
{
	unset($_FILES['attachment']);
}

// *********************************************************************************
// Tachy goes to coventry
if (in_coventry($thread['postuserid']) AND !can_moderate($thread['forumid']))
{
	$idname = $vbphrase['thread'];
	eval(print_standard_error('error_invalidid'));
}

// ############################### start post reply ###############################
if ($_POST['do'] == 'postreply')
{
	globalize($_POST, array(
		'posthash' => STR_NOHTML,
		'poststarttime' => STR_NOHTML
	));

	// ### PREP INPUT (should eventually all come in array direct from form) ###
	if (isset($_POST['WYSIWYG_HTML']))
	{
		require_once('./includes/functions_wysiwyg.php');
		$newpost['message'] = convert_wysiwyg_html_to_bbcode($_POST['WYSIWYG_HTML'], $foruminfo['allowhtml']);
	}
	else
	{
		$newpost['message'] = $_POST['message'];
	}

	if ($_POST['quickreply'])
	{
		$originalposter = fetch_quote_username($getpost['username']);
		$pagetext = trim(strip_quotes($getpost['pagetext']));
		eval('$quotemessage = "' . fetch_template('newpost_quote', 1, 0) . '";');
		$newpost['message'] = "$quotemessage $newpost[message]";
		unset($_POST['WYSIWYG_HTML']);
	}

	if ($_POST['fromquickreply'])
	{
		// We only add notifications to threads that don't have one if the user defaults to it, do nothing else!
		if ($bbuserinfo['autosubscribe'] != -1 AND !$threadinfo['issubscribed'])
		{
			$_POST['folderid'] = 0;
			$_POST['emailupdate'] = $bbuserinfo['autosubscribe'];
		}
		else if ($threadinfo['issubscribed'])
		{ // Don't alter current settings
			$_POST['folderid'] = $threadinfo['folderid'];
			$_POST['emailupdate'] = $threadinfo['emailupdate'];
		}
		else
		{ // Don't don't add!
			$_POST['emailupdate'] = 9999;
		}

		// fetch the quoted post title
		$_POST['title'] = fetch_quote_title($postinfo['title'], $threadinfo['title']);
	}

	$newpost['title'] = $_POST['title'];
	$newpost['iconid'] = $_POST['iconid'];
	$newpost['parseurl'] = $_POST['parseurl'];
	$newpost['signature'] = $_POST['signature'];
	$newpost['preview'] = $_POST['preview'];
	$newpost['disablesmilies'] = $_POST['disablesmilies'];
	$newpost['rating'] = $_POST['rating'];
	$newpost['username'] = $_POST['username'];
	$newpost['folderid'] = $_POST['folderid'];
	$newpost['emailupdate'] = $_POST['emailupdate'];
	$newpost['quickreply'] = $_POST['quickreply'];
	$newpost['hasattachment'] = $_POST['hasattachment'];
	$newpost['poststarttime'] = $poststarttime;
	$newpost['posthash'] = $posthash;
	// moderation options
	$newpost['stickunstick'] = $_POST['stickunstick'];
	$newpost['openclose'] = $_POST['openclose'];

	build_new_post('reply', $foruminfo, $threadinfo, $_POST['postid'], $newpost, $errors);

	if (sizeof($errors) > 0)
	{
		// ### POST HAS ERRORS ###
		$postpreview = construct_errors($errors); // this will take the preview's place
		construct_checkboxes($newpost);
		$_REQUEST['do'] = 'newreply';
		$newpost['message'] = htmlspecialchars_uni($newpost['message']);
	}
	else if ($newpost['preview'])
	{
		// ### PREVIEW POST ###
		$postpreview = process_post_preview($newpost);
		$_REQUEST['do'] = 'newreply';
		$newpost['message'] = htmlspecialchars_uni($newpost['message']);
	}
	else
	{

		// ### NOT PREVIEW - ACTUAL POST ###
		if ($newpost['visible'])
		{
			if (($tview = intval(fetch_bbarray_cookie('thread_lastview', $threadinfo['threadid']))) != $threadinfo['lastpost'])
			{
				$url = "showthread.php?$session[sessionurl]p=$newpost[postid]&amp;posted=1#post$newpost[postid]";
			}
			else
			{
				$url = "showthread.php?$session[sessionurl]p=$newpost[postid]#post$newpost[postid]";
			}
		}
		else
		{
			$_REQUEST['forceredirect'] = 1;
			$url = "forumdisplay.php?$session[sessionurl]f=$foruminfo[forumid]";
		}
		eval(print_standard_redirect('redirect_postthanks'));

	} // end if

}

// ############################### start new reply ###############################
if ($_REQUEST['do'] == 'newreply')
{

	// falls down from preview post and has already been sent through htmlspecialchars() in build_new_post()
	$title = $newpost['title'];

	construct_edit_toolbar($newpost['message'], 0, $foruminfo['forumid'], iif($foruminfo['allowsmilies'], 1, 0));

	// *********************************************************************
	// get options checks

	$posticons = construct_icons(intval($newpost['iconid']), $foruminfo['allowicons']);

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
		$attachurl = "t=$threadid";
		eval('$attachmentoption = "' . fetch_template('newpost_attachment') . '";');
	}
	else
	{
		$attachmentoption = '';
	}

	// get rating options
	if ($foruminfo['allowratings'] AND ($forumperms & CANTHREADRATE))
	{
		if ($rating = $DB_site->query_first("
			SELECT vote, threadrateid
			FROM " . TABLE_PREFIX . "threadrate
			WHERE userid = $bbuserinfo[userid]
				AND threadid = $threadid
		"))
		{
			if ($vboptions['votechange'])
			{
				$rate["$rating[vote]"] = ' '.HTML_SELECTED;
				$show['threadrating'] = true;
			}
			else
			{
				$show['threadrating'] = false;
			}
		}
		else
		{
			$show['threadrating'] = true;
		}
	}
	else
	{
		$show['threadrating'] = false;
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

	// Get subscribed thread folders
	// for now..
	if ($newpost['folderid'])
	{
		$folderid = $newpost['folderid'];
	}
	else
	{
		if ($threadinfo['issubscribed'])
		{
			$folderid = $threadinfo['folderid'];
		}
		else
		{
			$folderid = 0;
		}
	}
	$folders = unserialize($bbuserinfo['subfolders']);

	// Don't show the folderjump if we only have one folder, would be redundant ;)
	if (sizeof($folders) > 1)
	{
		require_once('./includes/functions_misc.php');
		$folderbits = construct_folder_jump(1, $folderid, false, $folders);
	}
	$show['subscribefolders'] = iif($folderbits, true, false);

	// get the checked option for auto subscription
	$emailchecked = fetch_emailchecked($threadinfo, $bbuserinfo, $newpost);

	// auto-parse URL
	if (!isset($checked['parseurl']))
	{
		$checked['parseurl'] = HTML_CHECKED;
	}

	if ($bbuserinfo['userid'] AND !$postpreview)
	{
		// signature
		if ($bbuserinfo['signature'] != '')
		{
			$checked['signature'] = HTML_CHECKED;
		}
		else
		{
			$checked['signature'] = '';
		}
	}

	// *********************************************************************
	// get thread review bits

	// get ignored users
	$ignore = array();
	$bbuserinfo['ignorelist'] = trim($bbuserinfo['ignorelist']);
	if ($bbuserinfo['ignorelist'] != '')
	{
		$ignorelist = explode(' ', $bbuserinfo['ignorelist']);
		foreach ($ignorelist AS $ignoreuserid)
		{
			$ignoreuserid = intval($ignoreuserid);
			if ($ignoreuserid)
			{
				$ignore["$ignoreuserid"] = 1;
			}
		}
	}
	if (!empty($ignore))
	{
		eval('$ignoreduser = "' . fetch_template('newreply_reviewbit_ignore') . '";');
	}

	// get thread review
	$threadreviewbits = '';

	if (($bbuserinfo['maxposts'] != -1) AND ($bbuserinfo['maxposts']))
	{
		$vboptions['maxposts'] = $bbuserinfo['maxposts'];
	}

	if ($Coventry = fetch_coventry('string') AND !can_moderate($forumid))
	{
		$globalignore = "AND post.userid NOT IN ($Coventry) ";
	}
	else
	{
		$globalignore = '';
	}

	$posts = $DB_site->query("
		SELECT post.postid, IF(post.userid = 0, post.username, user.username) AS username,
			post.pagetext, post.allowsmilie, post.userid, post.dateline
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND deletionlog.type = 'post')
		WHERE post.visible = 1
			$globalignore
			AND post.threadid = $threadid
			AND deletionlog.primaryid IS NULL
		ORDER BY dateline DESC, postid DESC
		LIMIT " . ($vboptions[maxposts] + 1)
	);
	while ($post = $DB_site->fetch_array($posts))
	{
		if ($postcounter++ < $vboptions['maxposts'])
		{
			exec_switch_bg();
			$posttime = vbdate($vboptions['timeformat'], $post['dateline']);
			$postdate = vbdate($vboptions['dateformat'], $post['dateline'], 1);
			$username = $post['username'];

			// do posts from ignored users
			if (in_coventry($post['userid']) AND can_moderate($foruminfo['forumid']))
			{
				eval('$reviewmessage = "' . fetch_template('newreply_reviewbit_ignore_global') . '";');
			}
			else if ($ignore["$post[userid]"])
			{
				$reviewmessage = $ignoreduser;
			}
			else
			{
				require_once('./includes/functions_bbcodeparse.php');
				$reviewmessage = parse_bbcode($post['pagetext'], $foruminfo['forumid'], $post['allowsmilie']);
			}
			eval('$threadreviewbits .= "' . fetch_template('newreply_reviewbit') . '";');
		}
		else
		{
			break;
		}
	}
	if ($DB_site->num_rows($posts) > $vboptions['maxposts'])
	{
		$show['reviewmore'] = true;
	}
	else
	{
		$show['reviewmore'] = false;
	}

	$currentpage = urlencode("newreply.php?do=newreply&p=$postinfo[postid]&noquote=" . intval($_REQUEST['noquote']));
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// *********************************************************************
	// finish the page

	construct_forum_rules($foruminfo, $forumperms);

	// draw nav bar
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $forumcache["$forumID"]['title'];
		$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
	}
	if ($postid)
	{
		$navbits["showthread.php?$session[sessionurl]p=$postid#post$postid"] = $threadinfo['title'];
	}
	else
	{
		$navbits["showthread.php?$session[sessionurl]t=$threadinfo[threadid]"] = $threadinfo['title'];
	}
	$navbits[''] = $vbphrase['reply_to_thread'];

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	// complete
	eval('print_output("' . fetch_template('newreply') . '");');

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12825 $
|| ####################################################################
\*======================================================================*/
?>