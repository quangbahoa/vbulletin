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
define('THIS_SCRIPT', 'misc');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('fronthelp');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'buddylist' => array(
		'BUDDYLIST',
		'buddylistbit'
	),
	'whoposted' => array(
		'WHOPOSTED',
		'whopostedbit'
	),
	'showavatars' => array(
		'help_avatars',
		'help_avatars_avatar',
		'help_avatars_category',
		'help_avatars_row',
	),
	'bbcode' => array(
		'help_bbcodes',
		'help_bbcodes_bbcode',
		'help_bbcodes_link',
	),
	'getsmilies' => array(
		'smiliepopup',
		'smiliepopup_category',
		'smiliepopup_row',
		'smiliepopup_smilie',
		'smiliepopup_straggler'
	),
	'showsmilies' => array(
		'help_smilies',
		'help_smilies_smilie',
		'help_smilies_category',
	)
);
$actiontemplates['none'] = &$actiontemplates['showsmilies'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// redirect in case anyone has linked to it
if ($_REQUEST['do'] == 'attachments')
{
	exec_header_redirect("profile.php?$session[sessionurl_js]do=editattachments");
}

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'showsmilies';
}

// ############################### start buddylist ###############################
if ($_REQUEST['do'] == 'buddylist')
{
	if (!$bbuserinfo['userid'])
	{
		print_no_permission();
	}
	globalize($_REQUEST, array('buddies' => STR));

	if (trim($bbuserinfo['buddylist']))
	{
		$buddylist = preg_split('/( )+/', trim($bbuserinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
		$datecut = TIMENOW - $vboptions['cookietimeout'];

		$buddys = $DB_site->query("
			SELECT
			user.username, (user.options & $_USEROPTIONS[invisible]) AS invisible, user.userid, session.lastactivity
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "session AS session ON(session.userid = user.userid)
			WHERE user.userid IN (" . implode(',', $buddylist) . ")
			ORDER BY username ASC,session.lastactivity DESC
		");

		$onlineusers = '';
		$offlineusers = '';
		$newusersound = '';
		$lastonline = array();

		if (isset($buddies))
		{
			$buddies = trim(urldecode($buddies));
			$lastonline = explode(' ', $buddies);
		}
		$buddies = '0 ';
		$show['playsound'] = false;

		require_once('./includes/functions_bigthree.php');
		while ($buddy = $DB_site->fetch_array($buddys))
		{
			if ($doneuser["$buddy[userid]"])
			{
				continue;
			}

			$doneuser["$buddy[userid]"] = true;

			if ($onlineresult = fetch_online_status($buddy))
			{
				if ($onlineresult == 1)
				{
					$buddy['statusicon'] = 'online';
				}
				else
				{
					$buddy['statusicon'] = 'invisible';
				}
				$buddies .= $buddy['userid'] . ' ';
			}
			else
			{
				$buddy['statusicon'] = 'offline';
			}

			$show['highlightuser'] = false;

			if ($buddy['statusicon'] != 'offline')
			{
				if (!in_array($buddy['userid'], $lastonline) AND !empty($lastonline))
				{
					$show['playsound'] = true;
					$show['highlightuser'] = true;
					// add name to top of list
					eval('$onlineusers = "' . fetch_template('buddylistbit') . '" . $onlineusers;');
				}
				else
				{
					eval('$onlineusers .= "' . fetch_template('buddylistbit') . '";');
				}
			}
			else
			{
				eval('$offlineusers .= "' . fetch_template('buddylistbit') . '";');
			}
		}
	}

	$buddies = urlencode(trim($buddies));
	unset($shutdownqueries['pmpopup']);
	eval('print_output("' . fetch_template('BUDDYLIST') . '");');
}

// ############################### start who posted ###############################
if ($_REQUEST['do'] == 'whoposted')
{
	// global.php handles $threadid..
	//$threadid = intval($threadid);

	$thread = verify_id('thread', $threadid, 1, 1);
	$forumperms = fetch_permissions($thread['forumid']);

	if (!($forumperms & CANVIEW))
	{
		print_no_permission();
	}
	if (!($forumperms & CANVIEWOTHERS) AND ($thread['postuserid'] != $bbuserinfo['userid'] OR !$bbuserinfo['userid']))
	{
		print_no_permission();
	}

	$posts = $DB_site->query("
		SELECT COUNT(postid) AS posts,
		post.username AS postuser,user.userid,user.username
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND type = 'post')
		WHERE threadid=" . intval($threadid) . "
			AND visible = 1
			AND deletionlog.primaryid IS NULL
		GROUP BY userid
		ORDER BY posts DESC
	");

	$totalposts = 0;
	if ($DB_site->num_rows($posts))
	{
		require_once('./includes/functions_bigthree.php');
		while ($post = $DB_site->fetch_array($posts))
		{
			// hide users in Coventry
			$ast = '';
			if (in_coventry($post['userid']) AND !can_moderate($thread['forumid']))
			{
				continue;
			}

			exec_switch_bg();
			if ($post['username'] == '')
			{
				$post['username'] = $post['postuser'];
			}
			$post['username'] .=  $ast;
			$totalposts += $post['posts'];
			$show['memberlink'] = iif ($post['userid'], true, false);
			eval('$posters .= "' . fetch_template('whopostedbit') . '";');
		}
		$totalposts = vb_number_format($totalposts);
		unset($shutdownqueries['pmpopup']);
		eval('print_output("' . fetch_template('WHOPOSTED') . '");');
	}
	else
	{
		$idname = $vbphrase['thread'];
		eval(print_standard_error('error_invalidid'));
	}
}

// ############################### start show smilies ###############################
if ($_REQUEST['do'] == 'showsmilies')
{

	$smiliebits = '';

	$smilies = $DB_site->query("
		SELECT smilietext,smiliepath,smilie.title,imagecategory.title AS category
		FROM " . TABLE_PREFIX . "smilie AS smilie
		LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
		ORDER BY imagecategory.displayorder, smilie.displayorder
	");

	while ($smilie = $DB_site->fetch_array($smilies))
	{
		if ($smilie['category'] != $lastcat)
		{
			eval('$smiliebits .= "' . fetch_template('help_smilies_category') . '";');
		}
		exec_switch_bg();
		eval('$smiliebits .= "' . fetch_template('help_smilies_smilie') . '";');
		$lastcat = $smilie['category'];
	}

	$navbits = construct_navbits(array(
		"faq.php?$session[sessionurl]" => $vbphrase['faq'],
		'' => $vbphrase['smilie_list']
	));

	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('help_smilies') . '");');
}

// ############################### start show avatars ###############################
if ($_REQUEST['do'] == 'showavatars')
{
	$minposts = 0;
	$avatarbits = '';

	$avatars = $DB_site->query("
		SELECT avatar.title,minimumposts,avatarpath,imagecategory.title AS category
		FROM " . TABLE_PREFIX . "avatar AS avatar
		LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory ON (imagecategory.imagecategoryid=avatar.imagecategoryid)
		LEFT JOIN " . TABLE_PREFIX . "imagecategorypermission AS perm ON (perm.imagecategoryid=avatar.imagecategoryid AND perm.usergroupid=$bbuserinfo[usergroupid])
		WHERE ISNULL(perm.imagecategoryid)
		ORDER BY minimumposts,imagecategory.displayorder,avatar.displayorder
	");

	// check to see that there are some avatars to display
	if ($DB_site->num_rows($avatars))
	{
		exec_switch_bg();
		while ($avatar = $DB_site->fetch_array($avatars))
		{
			// initialise the remaining columns number
			$remainingcolumns = 0;

			// display the category bar if required
			if ($avatar['category'] != $lastcat OR $avatar['minimumposts'] != $minposts)
			{
				// echo out any straggler avatars still waiting to be displayed
				$remaining = sizeof($bits);
				if ($remaining > 0)
				{
					$remainingcolumns = $vboptions['numavatarswide'] - $remaining;
					$avatarcells = implode('', $bits);
					eval('$avatarbits .= "' . fetch_template('help_avatars_row') . '";');
					$bits = array();
				}
				// get the category bar
				eval('$avatarbits .= "' . fetch_template('help_avatars_category') . '";');
			}
			// make an array entry containing the current avatar
			eval('$bits[] = "' . fetch_template('help_avatars_avatar') . '";');

			// display a row of avatars if the counter is high enough
			if (sizeof($bits) == $vboptions['numavatarswide'])
			{
				exec_switch_bg();
				$avatarcells = implode('', $bits);
				eval('$avatarbits .= "' . fetch_template('help_avatars_row') . '";');
				$bits = array();
			}

			// set the last category and last minposts
			$lastcat = $avatar['category'];
			$minposts = $avatar['minimumposts'];
		}

		// initialize the remaining columns number
		$remainingcolumns = 0;

		// echo out any straggler avatars still waiting to be displayed
		$remaining = sizeof($bits);
		if ($remaining > 0)
		{
			$remainingcolumns = $vboptions['numavatarswide'] - $remaining;
			$avatarcells = implode('', $bits);
			eval('$avatarbits .= "' . fetch_template('help_avatars_row') . '";');
		}

	} // end if num_rows($avatars)

	$navbits = construct_navbits(array(
		"faq.php?$session[sessionurl]" => $vbphrase['faq'],
		'' => $vbphrase['avatar_list']
	));

	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('help_avatars') . '");');

}

// ############################### start bbcode ###############################
if ($_REQUEST['do'] == 'bbcode')
{
	require_once('./includes/functions_bbcodeparse.php');
	require_once('./includes/functions_misc.php');

	$allowbbcodebasic = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_BASIC);
	$allowbbcodecolor = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_COLOR);
	$allowbbcodesize = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_SIZE);
	$allowbbcodefont = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_FONT);
	$allowbbcodealign = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_ALIGN);
	$allowbbcodelist = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_LIST);
	$allowbbcodeurl = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_URL);
	$allowbbcodecode = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_CODE);
	$allowbbcodephp = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_PHP);
	$allowbbcodehtml = bitwise($vboptions['allowedbbcodes'], ALLOW_BBCODE_HTML);

	$template['bbcodebits'] = '';

	$doubleRegex = "/(\[)(%s)(=)(['\"]?)([^\"']*)(\\4])(.*)(\[\/%s\])/siU";
	$singleRegex = "/(\[)(%s)(])(.*)(\[\/%s\])/siU";

	$bbcodes = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "bbcode ORDER BY bbcodetag, twoparams");
	while ($bbcode = $DB_site->fetch_array($bbcodes))
	{
		if ($bbcode['twoparams'])
		{
			$regex = sprintf($doubleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
		}
		else
		{
			$regex = sprintf($singleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
		}
		$bbcode['output'] = preg_replace($regex, $bbcode['bbcodereplacement'], $bbcode['bbcodeexample']);

		$bbcode['bbcodeexample'] = htmlspecialchars_uni($bbcode['bbcodeexample']);
		if ($bbcode['twoparams'])
		{
			$bbcode['tag'] = '[' . $bbcode['bbcodetag'] . '=<span class="highlight">' . $vbphrase['option'] . '</span>]<span class="highlight">' . $vbphrase['value'] . '</span>[/' . $bbcode['bbcodetag'] . ']';
		}
		else
		{
			$bbcode['tag'] = '[' . $bbcode['bbcodetag'] . ']<span class="highlight">' . $vbphrase['value'] . '</span>[/' . $bbcode['bbcodetag'] . ']';
		}
		eval('$template[\'bbcodebits\'] .= "' . fetch_template('help_bbcodes_bbcode') . '";');
		eval('$template[\'bbcodelinks\'] .= "' . fetch_template('help_bbcodes_link') . '";');
	}

	$navbits = construct_navbits(array(
		"faq.php?$session[sessionurl]" => $vbphrase['faq'],
		'' => $vbphrase['bbcode_list']
	));

	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('help_bbcodes') . '");');
}

// ############################### start any page ###############################
if ($_REQUEST['do'] == 'debug_page' AND $_REQUEST['template'] != '')
{
	if (!$debug)
	{
		print_no_permission();
	}
	$template_name = preg_replace('#[^a-z0-9_]#i', '', $_REQUEST['template']);
	$navbits = construct_navbits(array('' => htmlspecialchars_uni($template_name)));
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template($template_name) . '");');
}

if ($_REQUEST['do'] == 'page' AND $_REQUEST['template'] != '')
{
	$template_name = preg_replace('#[^a-z0-9_]#i', '', $_REQUEST['template']);
	$navbits = construct_navbits(array('' => htmlspecialchars_uni($template_name)));
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template('custom_' . $template_name) . '");');
}

// ############################### Popup Smilies for vbCode ################
if ($_REQUEST['do'] == 'getsmilies')
{
	globalize($_REQUEST, array('wysiwyg' => INT, 'getsmilies' => STR_NOHTML));

	$show['wysiwyg'] = $wysiwyg;

	$smilies = $DB_site->query("
		SELECT smilietext AS text, smiliepath AS path, smilie.title, smilieid,
		imagecategory.title AS category
		FROM " . TABLE_PREFIX . "smilie AS smilie
		LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
		ORDER BY imagecategory.displayorder, smilie.displayorder
	");

	$smcache = array();
	while ($smilie = $DB_site->fetch_array($smilies))
	{
		$smcache["$smilie[category]"][] = $smilie;
	}

	$popup_smiliesbits = '';
	$bits = array();
	exec_switch_bg();
	foreach ($smcache AS $category => $smilies)
	{
		if (sizeof($bits) == 1)
		{
			eval('$smiliecells = "' . fetch_template('smiliepopup_straggler') . '";');
			eval('$smiliebits .= "' . fetch_template('smiliepopup_row') . '";');
		}
		eval('$smiliebits .= "' . fetch_template('smiliepopup_category') . '";');
		$bits = array();
		foreach ($smilies AS $smilie)
		{
			$smilie['js'] = addslashes($smilie['text']);
			eval('$bits[] = "' . fetch_template('smiliepopup_smilie') . '";');
			if (sizeof($bits) == 2)
			{
				exec_switch_bg();
				$smiliecells = implode('', $bits);
				eval('$smiliebits .= "' . fetch_template('smiliepopup_row') . '";');
				$bits = array();
			}
		}
	}
	if (sizeof($bits) == 1)
	{
		eval('$smiliecells = "' . fetch_template('smiliepopup_straggler') . '";');
		eval('$smiliebits .= "' . fetch_template('smiliepopup_row') . '";');
	}

	unset($shutdownqueries['pmpopup']);
	eval('print_output("' . fetch_template('smiliepopup') . '");');

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11277 $
|| ####################################################################
\*======================================================================*/
?>