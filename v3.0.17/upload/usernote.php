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
define('THIS_SCRIPT', 'usernote');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting', 'postbit');

// get special data templates from the datastore
$specialtemplates = array(
	'rankphp',
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'forumrules'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'none' => array(
		'im_aim',
		'im_icq',
		'im_yahoo',
		'im_msn',
		'postbit',
		'postbit_onlinestatus',
		'postbit_reputation',
		'usernote_nonotes',
		'bbcode_code',
		'bbcode_html',
		'bbcode_php',
		'bbcode_quote',
		'usernote',
		'newpost_usernamecode'
	),
	'newnote' => array(
		'usernote_note'
	)
);

$actiontemplates['viewuser'] = &$actiontemplates['none'];
$actiontemplates['editnote'] = $actiontemplates['newnote'];

// get the editor templates if required
if (in_array($_REQUEST['do'], array('newnote', 'editnote')))
{
	define('GET_EDIT_TEMPLATES', true);
}

// ####################### PRE-BACK-END ACTIONS ##########################
function parse_usernote_bbcode($bbcode, $smilies=1)
{
	require_once('./includes/functions_bbcodeparse.php');
	global $vboptions;

	if ($vboptions['unallowsmilies'] == 0)
	{
		$smilies = 0;
	}
	$bbcode = parse_bbcode2($bbcode, $vboptions['unallowhtml'], $vboptions['unallowimg'], $smilies, $vboptions['unallowvbcode']);

	return $bbcode;
}


// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_bigthree.php');
require_once('./includes/functions_editor.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if ($_REQUEST['usernoteid'])
{
	$noteinfo = verify_id('usernote', $_REQUEST['usernoteid'], 1, 1);
	$userinfo = fetch_userinfo($noteinfo['userid']);
}
else
{
	$userinfo = verify_id('user', $_REQUEST['userid'], 1, 1);
}

$userperms = cache_permissions($userinfo, false);

if (!($userperms['genericpermissions'] & CANBEUSERNOTED))
{
	eval(print_standard_error('error_usernotenotallowed'));
}

$viewself = iif($userinfo['userid'] == $bbuserinfo['userid'], true, false);

// User viewing self and has no permission to do so
if ($viewself AND !($permissions['genericpermissions'] & CANVIEWOWNUSERNOTES))
{
	eval(print_standard_error('error_nousernoteself'));
}
// User viewiing others and has no permission to do so
if (!$viewself AND !($permissions['genericpermissions'] & CANVIEWOTHERSUSERNOTES))
{
	eval(print_standard_error('error_nousernoteothers'));
}

// get decent textarea size for user's browser
$textareacols = fetch_textarea_width();
construct_forum_jump();

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'viewuser';
}

$do = $_REQUEST['do'];

$bbcodeon = iif($vboptions['unallowvbcode'], $vbphrase['on'], $vbphrase['off']);
$imgcodeon = iif($vboptions['unallowimg'], $vbphrase['on'], $vbphrase['off']);
$htmlcodeon = iif($vboptions['unallowhtml'], $vbphrase['on'], $vbphrase['off']);
$smilieson = iif($vboptions['unallowsmilies'], $vbphrase['on'], $vbphrase['off']);

// only show posting code allowances in forum rules template
$show['codeonly'] = true;

eval('$forumrules = "' . fetch_template('forumrules') . '";');

$currentpage = SCRIPTPATH;
eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

// ########################### Delete Note #######################################
if ($_POST['do'] == 'deletenote')
{
	$noteinfo = verify_id('usernote', $_POST['usernoteid'], 1, 1);

	if ($noteinfo['posterid'] == $bbuserinfo['userid'] AND $permissions['genericpermissions'] & CANEDITOWNUSERNOTES)
	{
		// User has permissions to edit any notes that have posted no matter what the other manage permissions are set to..
	}
	else
	{
		if ($viewself AND !($permissions['genericpermissions'] & CANMANAGEOWNUSERNOTES))
		{
			print_no_permission();
		}
		else if (!$viewself AND !($permissions['genericpermissions'] & CANMANAGEOTHERSUSERNOTES))
		{
			print_no_permission();
		}
	}

	$userid = intval($_POST['userid']);
	if ($_POST['deletenotechecked'] == 'yes')
	{
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "usernote
			WHERE usernoteid = $noteinfo[usernoteid]
		");
		$url = "usernote.php?$session[sessionurl]u=$userid";
		eval(print_standard_redirect('redirect_deleteusernote'));
	}
	else
	{
		$url = "usernote.php?$session[sessionurl]u=$userid";
		eval(print_standard_redirect('redirect_nodeletenote'));
	}
}

// ############################### Start Edit User Note ##########################
if ($_REQUEST['do'] == 'editnote')
{
	if ($noteinfo['posterid'] == $bbuserinfo['userid'] AND $permissions['genericpermissions'] & CANEDITOWNUSERNOTES)
	{
		// User has permissions to edit any notes that have posted no matter what the other manage permissions are set to..
	}
	else
	{
		if ($viewself AND !($permissions['genericpermissions'] & CANMANAGEOWNUSERNOTES))
		{
			print_no_permission();
		}
		else if (!$viewself AND !($permissions['genericpermissions'] & CANMANAGEOTHERSUSERNOTES))
		{
			print_no_permission();
		}
	}

	$checked = array();

	$checked['parseurl'] = HTML_CHECKED;
	$checked['disablesmilies'] = iif($noteinfo['allowsmilies'], '', HTML_CHECKED);
	if ($vboptions['unallowsmilies'] == 1)
	{
		eval('$disablesmiliesoption = "' . fetch_template('newpost_disablesmiliesoption') . '";');
	}

	// include useful functions
	require_once('./includes/functions_newpost.php');
	construct_edit_toolbar($noteinfo['message'], 0, 'usernote');

	$show['editnote'] = true;

	// generate navbar
	$navbits = array(
		"member.php?$session[sessionurl]u=$userinfo[userid]" => $vbphrase['view_profile'],
		"usernote.php?$session[sessionurl]u=$userinfo[userid]" => construct_phrase($vbphrase['user_notes_for_x'], $userinfo['username']),
		$vbphrase['edit_user_note']
	);

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('usernote_note') . '");');
}

// ############################### Add/Update User Note ################################
if ($_POST['do'] == 'donote')
{

	globalize($_POST, array('disablesmilies', 'title' => STR, 'message' => STR, 'preview', 'parseurl' => INT, 'usernoteid' => INT, 'WYSIWYG_HTML'));

	$noteinfo = verify_id('usernote', $usernoteid, 0, 1);

	if ($noteinfo['usernoteid']) // existing note => edit
	{
		if ($noteinfo['posterid'] == $bbuserinfo['userid'] AND $permissions['genericpermissions'] & CANEDITOWNUSERNOTES)
		{
			// User has permissions to edit any notes that have posted no matter what the other manage permissions are set to..
		}
		else
		{
			if ($viewself AND !($permissions['genericpermissions'] & CANMANAGEOWNUSERNOTES))
			{
				print_no_permission();
			}
			else if (!$viewself AND !($permissions['genericpermissions'] & CANMANAGEOTHERSUSERNOTES))
			{
				print_no_permission();
			}
		}
	}
	else // new note
	{
		if ($viewself AND !($permissions['genericpermissions'] & CANPOSTOWNUSERNOTES))
		{
			print_no_permission();
		}
		else if (!$viewself AND !($permissions['genericpermissions'] & CANPOSTOTHERSUSERNOTES))
		{
			print_no_permission();
		}
	}

	$allowsmilies = iif($disablesmilies, 0, 1);
	$preview = iif($preview != '', 1, 0);

	// include useful functions
	require_once('./includes/functions_newpost.php');

	// unwysiwygify the incoming data
	if (isset($WYSIWYG_HTML))
	{
		require_once('./includes/functions_wysiwyg.php');
		$message = convert_wysiwyg_html_to_bbcode($WYSIWYG_HTML, $vboptions['unallowhtml']);
	}

	if (empty($message))
	{
		eval(print_standard_error('error_nosubject'));
	}

	$title = htmlspecialchars_uni(fetch_censored_text($title));
	if ($vboptions['wordwrap'] != 0)
	{
		$title = fetch_word_wrapped_string($title);
	}

	require_once('./includes/functions_newpost.php');

	// remove all caps subjects
	$title = fetch_no_shouting_text($title);

	$message = fetch_censored_text($message);
	if ($parseurl)
	{
		$message = convert_url_to_bbcode($message);
	}
	// remove sessionhash from urls:
	$message = preg_replace('/(s|sessionhash)=[a-z0-9]{32}&{0,1}/', '' ,$message);
	$message = fetch_no_shouting_text($message);
	if (vbstrlen($message) > $vboptions['postmaxchars'] AND $vboptions['postmaxchars'] != 0)
	{
		eval(print_standard_error('error_toolong'));
	}
	if (vbstrlen($message) < $vboptions['postminchars'] OR $message == '')
	{
		eval(print_standard_error('error_tooshort'));
	}

	if ($usernoteid)
	{ // Edited note.
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "usernote
			SET message = '" . addslashes($message) . "',
				title = '" . addslashes($title) . "',
				allowsmilies = $allowsmilies
			WHERE usernoteid = $usernoteid
		");
	}
	else
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "usernote (message, dateline, userid, posterid, title, allowsmilies)
			VALUES ('" . addslashes($message) . "', " . TIMENOW . ", $userinfo[userid], $bbuserinfo[userid], '" . addslashes($title) . "', $allowsmilies)
		");
	}

	$url = "usernote.php?$session[sessionurl]do=viewuser&amp;u=$userinfo[userid]";
	eval(print_standard_redirect('redirect_usernoteaddevent'));

}

// ############################### Start Add User Note ##########################
if ($_REQUEST['do'] == 'newnote')
{

	if ($viewself AND !($permissions['genericpermissions'] & CANPOSTOWNUSERNOTES))
	{
		print_no_permission();
	}
	else if (!$viewself AND !($permissions['genericpermissions'] & CANPOSTOTHERSUSERNOTES))
	{
		print_no_permission();
	}

	if (empty($checked['parseurl']))
	{
		$checked['parseurl'] = HTML_CHECKED;
	}

	if ($vboptions['unallowsmilies'] == 1)
	{
		eval('$disablesmiliesoption = "' . fetch_template('newpost_disablesmiliesoption') . '";');
	}

	$show['editnote'] = false;

	// include useful functions
	require_once('./includes/functions_newpost.php');
	construct_edit_toolbar($eventinfo['event'], 0, 'usernote');

	// generate navbar
	$navbits = array(
		"member.php?$session[sessionurl]u=$userinfo[userid]" => $vbphrase['view_profile'],
		"usernote.php?$session[sessionurl]u=$userinfo[userid]" => construct_phrase($vbphrase['user_notes_for_x'], $userinfo['username']),
		$vbphrase['post_user_note']
	);

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('usernote_note') . '");');
}

// ############################### Start Get User Notes##########################
if ($_REQUEST['do'] == 'viewuser')
{
	globalize($_REQUEST, array('perpage' => INT, 'pagenumber' => INT));

	require_once('./includes/functions_showthread.php');

	// *********************************************************************************
	// get ignored users
	$ignore = array();
	if (trim($bbuserinfo['ignorelist']))
	{
		$ignorelist = preg_split('/( )+/', trim($bbuserinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
		foreach ($ignorelist AS $ignoreuserid)
		{
			$ignore["$ignoreuserid"] = 1;
		}
	}

	if (empty($perpage))
	{
		if ($bbuserinfo['maxposts'] != -1 AND $bbuserinfo['maxposts'] != 0)
		{
			$perpage = $bbuserinfo['maxposts'];
		}
		else
		{
			$perpage = $vboptions['maxposts'];
		}
	}

	if (empty($pagenumber))
	{
		$pagenumber = 1;
	}

	$limitlower = ($pagenumber - 1) * $perpage + 1;
	$limitupper = ($pagenumber) * $perpage;

	$notescount=$DB_site->query_first("
		SELECT COUNT(*) AS notes FROM " . TABLE_PREFIX . "usernote
		WHERE userid = $userinfo[userid]
	");
	$totalnotes = $notescount['notes'];
	if ($limitupper > $totalnotes)
	{
		$limitupper = $totalnotes;
		if ($limitlower > $totalnotes)
		{
			$limitlower = $totalnotes - $perpage;
		}
	}
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}

	$counter = 0;
	$postcount = ($pagenumber - 1 ) * $perpage;

	$notes = $DB_site->query("
		SELECT usernote.*, usernote.username as postusername, user.*, userfield.*,
		IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid,
		IF(posterid=0, 0, user.userid) AS userid
		" . iif($vboptions['avatarenabled'],",avatar.avatarpath,NOT ISNULL(customavatar.avatardata) AS hascustomavatar,customavatar.dateline AS avatardateline") . "
		" . iif($vboptions['reputationenable'], ",level") . "
		FROM " . TABLE_PREFIX . "usernote AS usernote
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(usernote.posterid=user.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid=user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON(user.usergroupid=usergroup.usergroupid)
		" . iif($vboptions['avatarenabled'],"LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid=user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid=user.userid)") .
		iif($vboptions['reputationenable'], " LEFT JOIN " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON(user.reputationlevelid=reputationlevel.reputationlevelid)") . "
		WHERE usernote.userid = $userinfo[userid]
		ORDER BY usernote.dateline LIMIT " . ($limitlower - 1) . ", $perpage
	");

	while ($post = $DB_site->fetch_array($notes))
	{
		$post['postcount'] = ++$postcount;
		$post['musername'] = fetch_musername($post);
		$post['pagetext'] = $post['message'];
		$notebits .= construct_postbit($post, 'postbit', 'usernote');
	}

	$show['notes'] = iif($notebits != '', true, false);

	$DB_site->free_result($notes);
	unset($note);

	$pagenav = construct_page_nav($totalnotes, "usernote.php?$session[sessionurl]u=$userinfo[userid]&pp=$perpage");

	// generate navbar
	$navbits = array(
		"member.php?$session[sessionurl]u=$userinfo[userid]" => $vbphrase['view_profile'],
		construct_phrase($vbphrase['user_notes_for_x'], $userinfo['username'])
	);

	$show['addnote'] = true;
	if ($viewself AND !($permissions['genericpermissions'] & CANPOSTOWNUSERNOTES))
	{
		$show['addnote'] = false;
	}
	else if (!$viewself AND !($permissions['genericpermissions'] & CANPOSTOTHERSUSERNOTES))
	{
		$show['addnote'] = false;
	}

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('usernote') . '");');

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11371 $
|| ####################################################################
\*======================================================================*/
?>