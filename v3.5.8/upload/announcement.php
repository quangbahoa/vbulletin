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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'announcement');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('postbit');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'announcement',
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'im_skype',
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
require_once(DIR . '/includes/functions_bigthree.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vbulletin->input->clean_gpc('r', 'announcementid', TYPE_UINT);

($hook = vBulletinHook::fetch_hook('announcement_start')) ? eval($hook) : false;

if ($vbulletin->GPC['announcementid'])
{
	$announcementinfo = verify_id('announcement', $vbulletin->GPC['announcementid'], 1, 1);
	if ($announcementinfo['forumid'] != -1)
	{
		$vbulletin->GPC['forumid'] = $announcementinfo['forumid'];
	}
}

$foruminfo = verify_id('forum', $vbulletin->GPC['forumid'], 1, 1);

$curforumid = $foruminfo['forumid'];
construct_forum_jump();

$forumperms = fetch_permissions($foruminfo['forumid']);

if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$forumlist = fetch_forum_clause_sql($foruminfo['forumid'], 'announcement.forumid');

$announcements = $db->query_read("
	SELECT announcementid, announcementid AS postid, startdate, enddate, announcement.title, pagetext, allowhtml, allowbbcode, allowsmilies, views,
		user.*, userfield.*, usertextfield.*,
		IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
		" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
		" . ($vbulletin->options['reputationenable'] ? ", level" : "") . "
	FROM  " . TABLE_PREFIX . "announcement AS announcement
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid=announcement.userid)
	LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid=announcement.userid)
	LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid=announcement.userid)
	" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid=user.avatarid)
	LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid=announcement.userid)" : "") . "
	" . ($vbulletin->options['reputationenable'] ? "LEFT JOIN " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON(user.reputationlevelid=reputationlevel.reputationlevelid)" : "") . "
	" . (!empty($vbulletin->GPC['announcementid']) ? "WHERE announcementid = " . $vbulletin->GPC['announcementid'] : "
		WHERE startdate <= " . (TIMENOW - $vbulletin->options['hourdiff']) . "
			AND enddate >= " . (TIMENOW - $vbulletin->options['hourdiff']) . "
			AND $forumlist
		ORDER BY startdate DESC
	")
);

if ($db->num_rows($announcements) == 0)
{ // no announcements
	eval(standard_error(fetch_error('invalidid', $vbphrase['announcement'], $vbulletin->options['contactuslink'])));
}

if (!$vbulletin->options['oneannounce'] AND $announcementid)
{
	$anncount = $db->query_first("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "announcement AS announcement
		WHERE startdate <= " . (TIMENOW - $vbulletin->options['hourdiff']) . "
			AND enddate >= " . (TIMENOW - $vbulletin->options['hourdiff']) . "
			AND $forumlist
	");
	$anncount['total'] = intval($anncount['total']);
	$show['viewall'] = $anncount['total'] > 1 ? true : false;
}
else
{
	$show['viewall'] = false;
}

require_once(DIR . '/includes/class_postbit.php');

$show['announcement'] = true;

$counter = 0;
$anncids = '0';
$announcebits = '';

$postbit_factory =& new vB_Postbit_Factory();
$postbit_factory->registry =& $vbulletin;
$postbit_factory->forum =& $foruminfo;
$postbit_factory->cache = array();
$postbit_factory->bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

while ($post = $db->fetch_array($announcements))
{
	$postbit_obj =& $postbit_factory->fetch_postbit('announcement');

	$post['counter'] = ++$counter;

	$announcebits .= $postbit_obj->construct_postbit($post);
	$anncids .= ", $post[announcementid]";
}

if ($anncids)
{
	$db->shutdown_query("
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
	$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
	$navbits["forumdisplay.php?" . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
}
$navbits[$vbulletin->options['forumhome']. '.php'] = $vbphrase['announcements'];

$navbits = construct_navbits($navbits);

($hook = vBulletinHook::fetch_hook('announcement_complete')) ? eval($hook) : false;

eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('announcement') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13799 $
|| ####################################################################
\*======================================================================*/
?>