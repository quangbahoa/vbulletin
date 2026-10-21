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
define('THIS_SCRIPT', 'printthread');
define('NO_REGISTER_GLOBALS', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('showthread', 'postbit');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'printthread',
	'printthreadbit',
	'printthreadbit_ignore',
	'bbcode_code',
	'bbcode_html',
	'bbcode_php',
	'bbcode_quote',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_bbcodeparse.php');
require_once('./includes/functions_bigthree.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

globalize($_REQUEST, array('perpage' => INT, 'pagenumber' => INT));

// oldest first or newest first
if ($bbuserinfo['postsorder'] == 0)
{
	$postorder = '';
}
else
{
	$postorder = 'DESC';
}

$threadid = verify_id('thread', $_REQUEST['threadid']);
if ($vboptions['wordwrap'])
{
	$threadinfo['title'] = fetch_word_wrapped_string($threadinfo['title']);
}

if (!$threadinfo['visible'] OR $threadinfo['isdeleted'] OR (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid'])))
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

if ($threadinfo['open'] == 10)
{
	exec_header_redirect("printthread.php?$session[sessionurl_js]t=$threadinfo[pollid]");
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

// split thread over pages if necessary
$countposts = $DB_site->query_first("
	SELECT COUNT(*) AS total
	FROM " . TABLE_PREFIX . "post AS post
	LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND type = 'post')
	WHERE threadid=$threadid AND visible=1 AND deletionlog.primaryid IS NULL
");
$totalposts = $countposts['total'];


$checkmax = explode(',', $vboptions['usermaxposts'] . ',' . $vboptions['maxposts']);
$maxperpage = max($checkmax);

if (empty($perpage) OR $perpage < 1)
{
	$perpage = iif($bbuserinfo['maxposts'] > 0, $bbuserinfo['maxposts'], $vboptions['maxposts']);
}

if ($perpage > $maxperpage)
{
	$perpage = $vboptions['maxposts'];
}

if ($pagenumber < 1)
{
	$pagenumber = 1;
}
$startat = ($pagenumber - 1) * $perpage;

$pagenav = construct_page_nav($totalposts, "printthread.php?$session[sessionurl]t=$threadid", "&amp;pp=$perpage");
// end page splitter

$ignore = array();
if (trim($bbuserinfo['ignorelist']))
{
	$ignorelist = preg_split('/( )+/', trim($bbuserinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
	foreach ($ignorelist AS $ignoreuserid)
	{
		$ignore["$ignoreuserid"] = 1;
	}
}

$posts = $DB_site->query("
	SELECT post.*,post.username AS postusername,user.username
	FROM " . TABLE_PREFIX . "post AS post
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
	LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND type = 'post')
	WHERE post.threadid=$threadid AND post.visible=1 AND deletionlog.primaryid IS NULL
	ORDER BY dateline $postorder
	LIMIT $startat, $perpage
");

$postbits = '';
while ($post = $DB_site->fetch_array($posts))
{
	// hide users in Coventry from non-staff members
	if ($tachyuser = in_coventry($post['userid']) AND !can_moderate($threadinfo['forumid']))
	{
		continue;
	}

	if ($tachyuser)
	{
		$show['adminignore'] = true;
		$maintemplatename = 'printthreadbit_ignore';
	}
	else if ($ignore["$post[userid]"])
	{
		$show['adminignore'] = false;
		$maintemplatename = 'printthreadbit_ignore';
	}
	else
	{
		$maintemplatename = 'printthreadbit';
	}

	$post['postdate'] = vbdate($vboptions['dateformat'], $post['dateline']);
	$post['posttime'] = vbdate($vboptions['timeformat'], $post['dateline']);

	if ($vboptions['wordwrap'])
	{
		$post['title'] = fetch_word_wrapped_string($post['title']);
	}

	if (!$post['userid'])
	{
		$post['username'] = $post['postusername'];
	}

	$post['message'] = parse_bbcode($post['pagetext'], $foruminfo['forumid'], 0);

	eval('$postbits .= "' . fetch_template($maintemplatename) . '";');

}

eval('print_output("' . fetch_template('printthread') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11549 $
|| ####################################################################
\*======================================================================*/
?>