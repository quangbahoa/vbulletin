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
define('THIS_SCRIPT', 'showpostedithistory');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'posting',
);

// get special data templates from the datastore
$specialtemplates = array(
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'posthistory',
	'posthistory_listbit',
	'posthistory_content_not_changed',
	'posthistory_content_changed',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'newver'    => TYPE_UINT,
	'oldver'    => TYPE_UINT,
));

if (!$postinfo['postid'] OR !$vbulletin->options['postedithistory'])
{
	standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink']));
}

if ((!$postinfo['visible'] OR $postinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
{
	standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink']));
}

if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
{
	standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink']));
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
if (!can_moderate($threadinfo['forumid'], 'caneditposts'))
{
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['caneditpost']))
	{
		print_no_permission();
	}
	else if ($vbulletin->userinfo['userid'] != $postinfo['userid'])
	{
		print_no_permission();
	}
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$thread = verify_id('thread', $postinfo['threadid'], 1, 1);
$forum = fetch_foruminfo($thread['forumid']);

// #######################################################################

($hook = vBulletinHook::fetch_hook('posthistory_start')) ? eval($hook) : false;

// new ver is the max of the 2 compared version, old the min. Also, make sure they're different
$newver = max($vbulletin->GPC['oldver'], $vbulletin->GPC['newver']);
$oldver = min($vbulletin->GPC['oldver'], $vbulletin->GPC['newver']);
$oldver = ($oldver == $newver ? 0 : $oldver);

$compare = array();

// when we are comparing the two versions
if ($_REQUEST['do'] == 'compare' AND $newver AND $oldver)
{
	$histories_result = $db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "postedithistory
		WHERE postedithistoryid IN (" . $newver . ", " . $oldver . ")
			AND postid = " . $postinfo['postid'] . "
		ORDER BY dateline DESC
	");
}

// if there wasn't two versions then show the full list
if (empty($histories_result) OR $db->num_rows($histories_result) < 2)
{
	$histories_result = $db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "postedithistory
		WHERE postid = " . $postinfo['postid'] . "
		ORDER BY dateline DESC
	");
}

if ($db->num_rows($histories_result) < 2)
{
	// we need at least 2 history points to compare. If we don't have that, error.
	standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink']));
}

// fetching the history list
$historybits = '';
$shown_original = false;

while ($history = $db->fetch_array($histories_result))
{
	// Don't show two original posts
	if ($history['original'] AND $shown_original)
	{
		continue;
	}

	if ($newver == $history['postedithistoryid'])
	{
		$compare['newver'] = $history;
	}
	else if ($oldver == $history['postedithistoryid'])
	{
		$compare['oldver'] = $history;
	}

	// when we do not have selected versions, we select the latest two (latest the new, before the latest the old)
	if (!$newver)
	{
		$newver = $history['postedithistoryid'];
	}
	else if (!$oldver AND $newver != $history['postedithistoryid'])
	{
		$oldver = $history['postedithistoryid'];
	}

	exec_switch_bg();

	// set the sting date and time
	$history['strdate'] = vbdate($vbulletin->options['dateformat'], $history['dateline']);
	$history['strtime'] = vbdate($vbulletin->options['timeformat'], $history['dateline']);

	// when the line is the original post, we set the reason based on the phrases
	if ($history['original'])
	{
		$history['reason'] = $vbphrase['original_post'];
		$shown_original = true;
	}

	$newver_selected = ($newver == $history['postedithistoryid'] ? 'checked="checked"' : '');
	$oldver_selected = ($oldver == $history['postedithistoryid'] ? 'checked="checked"' : '');

	$history['reason'] = fetch_word_wrapped_string($history['reason']);

	($hook = vBulletinHook::fetch_hook('posthistory_history_bits')) ? eval($hook) : false;
	eval('$historybits .= "' . fetch_template('posthistory_listbit') . '";');
}

// we do compare when we have two selected version from the database
$form_do = 'compare';
$button_text = $vbphrase['compare_versions'];
$show['comparetable'] = false;

if ($_REQUEST['do'] == 'compare')
{
	$show['comparetable'] = true;
	$show['titlecompare'] = false;
	$comparebits = '';

	if ($compare['oldver'] AND $compare['newver'])
	{
		// make the diff
		require_once(DIR . '/includes/class_diff.php');
		$textdiff_obj = new vB_Text_Diff($compare['oldver']['pagetext'], $compare['newver']['pagetext']);
		$diff = $textdiff_obj->fetch_diff();

		($hook = vBulletinHook::fetch_hook('posthistory_compare')) ? eval($hook) : false;

		foreach ($diff AS $diffrow)
		{
			$compare_show = array();

			if ($diffrow->old_class == 'unchanged' AND $diffrow->new_class == 'unchanged')
			{ // no change
				$compare_show['olddata'] = fetch_word_wrapped_string(nl2br(htmlspecialchars_uni(implode("\n", $diffrow->fetch_data_old()))));
				$compare_show['template'] = 'posthistory_content_not_changed';
			}
			else
			{ // something has changed
				$compare_show['olddata'] = fetch_word_wrapped_string(nl2br(htmlspecialchars_uni(implode("\n", $diffrow->fetch_data_old()))));
				$compare_show['newdata'] = fetch_word_wrapped_string(nl2br(htmlspecialchars_uni(implode("\n", $diffrow->fetch_data_new()))));
				$compare_show['template'] = 'posthistory_content_changed';
			}

			($hook = vBulletinHook::fetch_hook('posthistory_comparebit')) ? eval($hook) : false;

			eval('$comparebits .= "' . fetch_template($compare_show['template']) . '";');
		}

		$show['titlecompare'] = ($compare['oldver']['title'] != $compare['newver']['title']);
		$oldtitle = ($compare['oldver']['title'] !== '' ? $compare['oldver']['title'] : '&nbsp;');
		$newtitle = ($compare['newver']['title'] !== '' ? $compare['newver']['title'] : '&nbsp;');

		$form_do = 'list';
		$button_text = $vbphrase['go_back'];
	}
}

// #############################################################################
// draw navbar
$navbits = array();
$parentlist = array_reverse(explode(',', substr($forum['parentlist'], 0, -3)));
foreach ($parentlist AS $forumid)
{
	$forum_title = $vbulletin->forumcache["$forumid"]['title'];
	$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumid"] = $forum_title;
}
$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$postinfo[postid]"] = $thread['title'];
$navbits[''] = $vbphrase['post_edit_history'];

$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');

($hook = vBulletinHook::fetch_hook('posthistory_complete')) ? eval($hook) : false;

// #############################################################################
// output page
eval('print_output("' . fetch_template('posthistory') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
