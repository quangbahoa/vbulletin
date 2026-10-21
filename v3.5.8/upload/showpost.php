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
define('THIS_SCRIPT', 'showpost');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('showthread', 'postbit');

// get special data templates from the datastore
$specialtemplates = array(
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
	'im_skype',
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
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/class_postbit.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'highlight'	=> TYPE_STR,
	'postcount'	=> TYPE_UINT
));

// words to highlight from the search engine
if (!empty($vbulletin->GPC['highlight']))
{
	$highlight = str_replace('\*', '[a-z]*', preg_quote(strtolower($vbulletin->GPC['highlight']), '/'));
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

if (!$postinfo['postid'])
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
}

if ((!$postinfo['visible'] OR $postinfo ['isdeleted']) AND !can_moderate($threadinfo['forumid']))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
}

if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
}

$forumperms = fetch_permissions($threadinfo['forumid']);
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
{
	print_no_permission();
}
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$hook_query_fields = $hook_query_joins = '';
($hook = vBulletinHook::fetch_hook('showpost_start')) ? eval($hook) : false;

$post = $db->query_first("
	SELECT
		post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
		user.*, userfield.*, usertextfield.*,
		" . iif($foruminfo['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
		IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
		" . iif($vbulletin->options['avatarenabled'], ',avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight') . "
		" . iif($vbulletin->options['reputationenable'], ',level') . ",
		editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline, editlog.reason AS edit_reason,
		post_parsed.pagetext_html, post_parsed.hasimages
		" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->hidprofilecache) . "
		$hook_query_fields
	FROM " . TABLE_PREFIX . "post AS post
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
	LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
	LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
	" . iif($foruminfo['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
	" . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") .
		iif($vbulletin->options['reputationenable'], " LEFT JOIN " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON(user.reputationlevelid = reputationlevel.reputationlevelid)") . "
	LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
	LEFT JOIN " . TABLE_PREFIX . "post_parsed AS post_parsed ON(post_parsed.postid = post.postid AND post_parsed.styleid_code = " . $vbulletin->bbcode_style['code'] . " AND post_parsed.styleid_html = " . $vbulletin->bbcode_style['html'] . " AND post_parsed.styleid_php = " . $vbulletin->bbcode_style['php'] . " AND post_parsed.styleid_quote = " . $vbulletin->bbcode_style['quote'] . ")
	$hook_query_joins
	WHERE post.postid = $postid
");

// Tachy goes to coventry
if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
{
	// do not show post if part of a thread from a user in Coventry and bbuser is not mod
	eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
}
if (in_coventry($post['userid']) AND !can_moderate($threadinfo['forumid']))
{
	// do not show post if posted by a user in Coventry and bbuser is not mod
	eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
}

// check for attachments
if ($post['attach'])
{
	$attachments = $db->query_read("
		SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
			postid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
			attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
		FROM " . TABLE_PREFIX . "attachment
		LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
		WHERE postid = $postid
		ORDER BY attachmentid
	");
	while ($attachment = $db->fetch_array($attachments))
	{
		if (!$attachment['build_thumbnail'])
		{
			$attachment['hasthumbnail'] = false;
		}
		$post['attachments']["$attachment[attachmentid]"] = $attachment;
	}
}

if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
{
	$vbulletin->options['viewattachedimages'] = 0;
	$vbulletin->options['attachthumbs'] = 0;
}

$saveparsed = ''; // inialise

$show['spacer'] = false;

$post['postcount'] =& $vbulletin->GPC['postcount'];

$postbit_factory =& new vB_Postbit_Factory();
$postbit_factory->registry =& $vbulletin;
$postbit_factory->forum =& $foruminfo;
$postbit_factory->thread =& $threadinfo;
$postbit_factory->cache = array();
$postbit_factory->bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

$postbit_obj =& $postbit_factory->fetch_postbit('post');
$postbit_obj->highlight =& $replacewords;
$postbit_obj->cachable = (!$post['pagetext_html'] AND $vbulletin->options['cachemaxage'] > 0 AND (TIMENOW - ($vbulletin->options['cachemaxage'] * 60 * 60 * 24)) <= $threadinfo['lastpost']);

($hook = vBulletinHook::fetch_hook('showpost_post')) ? eval($hook) : false;

$postbits = $postbit_obj->construct_postbit($post);

// save post to cache if relevant
if ($postbit_obj->cachable)
{
	/*insert query*/
	$db->shutdown_query("
		REPLACE INTO " . TABLE_PREFIX . "post_parsed (postid,dateline,hasimages,pagetext_html,styleid_code,styleid_html,styleid_php,styleid_quote)
		VALUES (
			$post[postid], " .
			intval($threadinfo['lastpost']) . ", " .
			intval($postbit_obj->post_cache['has_images']) . ", '" .
			$db->escape_string($postbit_obj->post_cache['text']) . "', " .
			intval($vbulletin->bbcode_style['code']) . ", " .
			intval($vbulletin->bbcode_style['html']) . ", " .
			intval($vbulletin->bbcode_style['php']) . ", " .
			intval($vbulletin->bbcode_style['quote']) . "
			)
	");
}

($hook = vBulletinHook::fetch_hook('showpost_complete')) ? eval($hook) : false;

eval('print_output("' . fetch_template('SHOWTHREAD_SHOWPOST') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13798 $
|| ####################################################################
\*======================================================================*/
?>