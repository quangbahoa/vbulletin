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
define('THIS_SCRIPT', 'showpost');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('showthread', 'postbit');

// get special data templates from the datastore
$specialtemplates = array(
	'rankphp',
	'smiliecache',
	'bbcodecache',
	'hidprofilecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'postbit',
	'postbit_attachment',
	'postbit_attachmentimage',
	'postbit_attachmentthumbnail',
	'postbit_attachmentmoderated',
	'postbit_editedby',
	'postbit_ip',
	'postbit_onlinestatus',
	'postbit_reputation',
	'bbcode_code',
	'bbcode_html',
	'bbcode_php',
	'bbcode_quote',
	'SHOWTHREAD_SHOWPOST'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_bigthree.php');
require_once('./includes/functions_showthread.php');
require_once('./includes/functions_bbcodeparse.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

globalize($_REQUEST, array('highlight', 'postcount' => INT));

// words to highlight from the search engine
if (!empty($highlight))
{
	$highlight = str_replace('\*', '[a-z]*', preg_quote(strtolower($highlight), '/'));
	$highlightwords = explode(' ', $highlight);
	foreach ($highlightwords AS $val)
	{
		if ($val == 'or' OR $val == 'and' OR $val == 'not')
		{
			continue;
		}
		$replacewords[] = $val;
	}
}

// #######################################################################
// ############################# SHOW POST ###############################
// #######################################################################

$postid = verify_id('post', $_REQUEST['postid']);

$forum = &$foruminfo;
$thread = &$threadinfo;

if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
{
	$idname = $vbphrase['thread'];
	eval(print_standard_error('error_invalidid'));
}

$forumperms = fetch_permissions($threadinfo['forumid']);
if (!($forumperms & CANVIEW))
{
	print_no_permission();
}
if (!($forumperms & CANVIEWOTHERS) AND ($threadinfo['postuserid'] != $bbuserinfo['userid'] OR $bbuserinfo['userid'] == 0))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$post = $DB_site->query_first("
	SELECT
		post.*, post.username AS postusername, post.ipaddress AS ip,
		user.*, userfield.*, usertextfield.*,
		" . iif($foruminfo['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
		IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
		" . iif($vboptions['avatarenabled'], ',avatar.avatarpath, NOT ISNULL(customavatar.avatardata) AS hascustomavatar, customavatar.dateline AS avatardateline') . "
		" . iif($vboptions['reputationenable'], ',level') . ",
		NOT ISNULL(deletionlog.primaryid) AS isdeleted,
		post_parsed.pagetext_html, post_parsed.hasimages
		" . iif(!($permissions['genericpermissions'] & CANSEEHIDDENCUSTOMFIELDS), $datastore['hidprofilecache']) . "
	FROM " . TABLE_PREFIX . "post AS post
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
	LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
	LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
	" . iif($foruminfo['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
	" . iif($vboptions['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") .
		iif($vboptions['reputationenable'], " LEFT JOIN " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON(user.reputationlevelid = reputationlevel.reputationlevelid)") . "
	LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND type = 'post')
	LEFT JOIN " . TABLE_PREFIX . "post_parsed AS post_parsed ON(post_parsed.postid = post.postid)
	WHERE post.postid = $postid
");

if ((!$post['visible'] OR $post['isdeleted']) AND !can_moderate($threadinfo['forumid']))
{
	$idname = $vbphrase['post'];
	eval(print_standard_error('error_invalidid'));
}

$post['postcount'] = $postcount;

// Tachy goes to coventry
if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
{
	// do not show post if part of a thread from a user in Coventry and bbuser is not mod
	$idname = $vbphrase['thread'];
	eval(print_standard_error('invalidid'));
}
if (in_coventry($post['userid']) AND !can_moderate($threadinfo['forumid']))
{
	// do not show post if posted by a user in Coventry and bbuser is not mod
	$idname = $vbphrase['post'];
	eval(print_standard_error('invalidid'));
}

// check for attachments
if ($post['attach'])
{
	$attachments = $DB_site->query("
		SELECT filename, filesize, visible, attachmentid, counter, postid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize
		FROM " . TABLE_PREFIX . "attachment
		WHERE postid = $postid
		ORDER BY dateline
	");
	while ($attachment = $DB_site->fetch_array($attachments))
	{
		$post['attachments']["$attachment[attachmentid]"] = $attachment;
	}
}

if (!($forumperms & CANGETATTACHMENT))
{
	$vboptions['viewattachedimages'] = 0;
	$vboptions['attachthumbs'] = 0;
}
$post['musername'] = fetch_musername($post);

$saveparsed = ''; // inialise
// see if the lastpost time of this thread is older than the cache max age limit
if ($vboptions['cachemaxage'] == 0 OR TIMENOW - ($vboptions['cachemaxage'] * 60 * 60 * 24) > $threadinfo['lastpost'])
{
	$stopsaveparsed = 1;
}
else
{
	$stopsaveparsed = 0;
}

$show['spacer'] = false;
$postbits = construct_postbit($post);

// save post to cache if relevant
if (!empty($parsed_postcache['text']) AND !$stopsaveparsed)
{
	$saveparsed = "($post[postid], " . intval($threadinfo['lastpost']) . ", " . $parsed_postcache['images'] . ", '" . addslashes($parsed_postcache['text']) . "')";
	$DB_site->shutdown_query("
		REPLACE INTO " . TABLE_PREFIX . "post_parsed (postid,dateline,hasimages,pagetext_html)
		VALUES $saveparsed
	");
}

eval('print_output("' . fetch_template('SHOWTHREAD_SHOWPOST') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11200 $
|| ####################################################################
\*======================================================================*/
?>