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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'announcement');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('postbit');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'rankphp',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'announcement',
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'postbit',
	'postbit_userinfo',
	'postbit_onlinestatus',
	'postbit_reputation',
	'bbcode_code',
	'bbcode_html',
	'bbcode_php',
	'bbcode_quote',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_bigthree.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

globalize($_REQUEST, array('announcementid' => INT));
if ($announcementid)
{
	$announcementinfo = verify_id('announcement', $announcementid, 1, 1);
	if ($announcementinfo['forumid'] != -1)
	{
		$_REQUEST['forumid'] = $announcementinfo['forumid'];
	}
}

$foruminfo = verify_id('forum', $_REQUEST['forumid'], 1, 1);

$curforumid = $foruminfo['forumid'];
construct_forum_jump();

$forumperms = fetch_permissions($foruminfo['forumid']);
if (!($forumperms & CANVIEW))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$forumlist = fetch_forum_clause_sql($foruminfo['forumid'], 'announcement.forumid');

$announcements = $DB_site->query("
	SELECT announcementid, startdate, enddate, announcement.title, pagetext, allowhtml, allowbbcode, allowsmilies, views,
	user.*, userfield.*, usertextfield.*,
	IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
	" . iif($vboptions['avatarenabled'], ",avatar.avatarpath, NOT ISNULL(customavatar.avatardata) AS hascustomavatar, customavatar.dateline AS avatardateline") . "
	" . iif($vboptions['reputationenable'], ", level") . "
	FROM  " . TABLE_PREFIX . "announcement AS announcement
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid=announcement.userid)
	LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid=announcement.userid)
	LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid=announcement.userid)
	" . iif($vboptions['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid=user.avatarid)
	LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid=announcement.userid)") . "
	" . iif($vboptions['reputationenable'], "LEFT JOIN " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON(user.reputationlevelid=reputationlevel.reputationlevelid)") . "
	" . iif($announcementid, "WHERE announcementid = $announcementid", "
		WHERE startdate <= " . (TIMENOW - $vboptions['hourdiff']) . "
			AND enddate >= " . (TIMENOW - $vboptions['hourdiff']) . "
			AND $forumlist
		ORDER BY startdate DESC
	")
);

if ($DB_site->num_rows($announcements) == 0)
{ // no announcements
	$idname = $vbphrase['announcement'];
	eval(print_standard_error('error_invalidid'));
}

if (!$vboptions['oneannounce'] AND $announcementid)
{
	$anncount = $DB_site->query_first("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "announcement AS announcement
		WHERE startdate <= " . TIMENOW . "
			AND enddate >= " . TIMENOW . "
			AND $forumlist
	");
	$anncount['total'] = intval($anncount['total']);
	$show['viewall'] = iif($anncount['total'] > 1, true, false);
}
else
{
	$show['viewall'] = false;
}

require_once('./includes/functions_showthread.php');

// Deprecated as of 3.0.2, use $show['announcement']
$show['start_until_end'] = true;
//
$show['announcement'] = true;

$counter = 0;
$anncids = '0';
$announcebits = '';
while ($post = $DB_site->fetch_array($announcements))
{
	$counter++;
	$post['counter'] = $counter;

	$post['musername'] = fetch_musername($post);

	$post['dateline'] = $post['startdate'];

	$post['startdate'] = vbdate($vboptions['dateformat'], $post['startdate'], false, true, false);
	$post['enddate'] = vbdate($vboptions['dateformat'], $post['enddate'], false, true, false);

	if ($post['startdate'] > $bbuserinfo['lastvisit'])
	{
		$post['statusicon'] = 'new';
	}
	else
	{
		$post['statusicon'] = 'old';
	}

	$announcebits .= construct_postbit($post, 'postbit', 'announcement');
	$anncids .= ", $post[announcementid]";
}

if ($anncids)
{
	$DB_site->shutdown_query("
		UPDATE " . TABLE_PREFIX . "announcement
		SET views = views + 1
		WHERE announcementid IN ($anncids)
	");
}

// build navbar
$navbits = array();
$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
foreach ($parentlist AS $forumID)
{
	$forumTitle = $forumcache["$forumID"]['title'];
	$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
}
$navbits["$vboptions[forumhome].php"] = $vbphrase['announcements'];

$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');

eval('print_output("' . fetch_template('announcement') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 10900 $
|| ####################################################################
\*======================================================================*/
?>