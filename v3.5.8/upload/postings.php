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
define('THIS_SCRIPT', 'postings');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('threadmanage');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'THREADADMIN',
	'threadadmin_postbit'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'editthread' => array(
		'threadadmin_editthread',
		'threadadmin_logbit',
		'posticonbit',
		'posticons'
	),
	'deleteposts' => array('threadadmin_deleteposts'),
	'deletethread' => array('threadadmin_deletethread'),
	'managepost' => array('threadadmin_managepost'),
	'mergethread' => array('threadadmin_mergethread'),
	'movethread' => array('threadadmin_movethread'),
	'splitthread' => array('threadadmin_splitthread'),
);

// ####################### PRE-BACK-END ACTIONS ##########################
require_once('./global.php');
require_once(DIR . '/includes/functions_threadmanage.php');
require_once(DIR . '/includes/functions_databuild.php');
require_once(DIR . '/includes/functions_log_error.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ###################### Start makepostingsnav #######################
// shortcut function to make $navbits for navbar
function construct_postings_nav($foruminfo, $threadinfo)
{
	global $vbulletin, $vbphrase;

	$navbits = array();

	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
	}
	$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]"] = $threadinfo['title'];

	switch ($_REQUEST['do'])
	{
		case 'movethread':   $navbits[''] = $vbphrase['move_copy_thread']; break;
		case 'editthread':   $navbits[''] = $vbphrase['edit_thread']; break;
		case 'deletethread': $navbits[''] = $vbphrase['delete_thread']; break;
		case 'deleteposts':  $navbits[''] = $vbphrase['delete_posts']; break;
		case 'mergethread':  $navbits[''] = $vbphrase['merge_threads']; break;
		case 'splitthread':  $navbits[''] = $vbphrase['split_thread']; break;
	}

	return construct_navbits($navbits);
}

$idname = $vbphrase['thread'];

switch ($_REQUEST['do'])
{
	case 'openclosethread':
	case 'dodeletethread':
	case 'dodeleteposts':
	case 'domovethread':
	case 'updatethread':
	case 'domergethread':
	case 'dosplitthread':
	case 'stick':
	case 'removeredirect':
	case 'deletethread':
	case 'deleteposts':
	case 'movethread':
	case 'editthread':
	case 'mergethread':
	case 'splitthread':

		if (!$threadinfo['threadid'])
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
		}
		break;

	case 'getip':
		break;
	case 'domanagepost':
	case 'managepost':

		if (!$postinfo['postid'])
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
		}
		else if (!$threadinfo['threadid'])
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
		}
		break;

	case 'editpoll':

		if (!$pollinfo['pollid'])
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['poll'], $vbulletin->options['contactuslink'])));
		}
		else if (!$threadinfo['threadid'])
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
		}
		else
		{
			exec_header_redirect('poll.php?' . $vbulletin->session->vars['sessionurl'] . 'do=polledit&pollid=' . $pollinfo['pollid']);
		}
		break; // never get here but ... :p

	default: // throw and error about invalid $_REQUEST['do']
		$handled_do = false;
		($hook = vBulletinHook::fetch_hook('threadmanage_action_switch')) ? eval($hook) : false;
		if (!$handled_do)
		{
			eval(standard_error(fetch_error('invalid_action')));
		}

}

// ensure that thread notes are run through htmlspecialchars
if (is_array($threadinfo))
{
	$threadinfo['notes'] = htmlspecialchars_uni($threadinfo['notes']);
}

$show['softdelete'] = iif(can_moderate($threadinfo['forumid'], 'candeleteposts'), true, false);
$show['harddelete'] = iif(can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);

// set $threadedmode (continued from global.php)
if ($vbulletin->options['allowthreadedmode'])
{
	if (!isset($threadedmode))
	{
		DEVDEBUG('$threadedmode is empty');
		if ($vbulletin->userinfo['threadedmode'] == 3)
		{
			$threadedmode = 0;
		}
		else
		{
			$threadedmode = $vbulletin->userinfo['threadedmode'];
		}
	}

	switch ($threadedmode)
	{
		case 1:
			$show['threadedmode'] = true;
			$show['hybridmode'] = false;
			$show['linearmode'] = false;
			break;
		case 2:
			$show['threadedmode'] = false;
			$show['hybridmode'] = true;
			$show['linearmode'] = false;
			break;
		default:
			$show['threadedmode'] = false;
			$show['hybridmode'] = false;
			$show['linearmode'] = true;
		break;
	}
}
else
{
	DEVDEBUG('Threadedmode disabled by admin');
	$threadedmode = 0;
}

($hook = vBulletinHook::fetch_hook('threadmanage_start')) ? eval($hook) : false;

// ############################### start do open / close thread ###############################
if ($_POST['do'] == 'openclosethread')
{
	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	// permission check
	if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']))
		{
			print_no_permission();
		}
		else
		{
			if (!is_first_poster($threadid))
			{
				print_no_permission();
			}
		}
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// handles mod log
	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set('open', ($threadman->fetch_field('open') == 1 ? 0 : 1));

	($hook = vBulletinHook::fetch_hook('threadmanage_openclose')) ? eval($hook) : false;

	$threadman->save();

	if ($threadinfo['open'])
	{
		$action = $vbphrase['closed'];
	}
	else
	{
		$action = $vbphrase['opened'];
	}

	$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
	eval(print_standard_redirect('redirect_openclose', true, true));

}

// ############################### start delete thread ###############################
if ($_REQUEST['do'] == 'deletethread')
{
	$templatename = 'threadadmin_deletethread';

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'canremoveposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	// permission check
	if (!can_moderate($threadinfo['forumid'], 'candeleteposts') AND !can_moderate($threadinfo['forumid'], 'canremoveposts'))
	{
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread']))
		{
			print_no_permission();
		}
		else if ($threadinfo['dateline'] < (TIMENOW - ($vbulletin->options['edittimelimit'] * 60)) AND $vbulletin->options['edittimelimit'] != 0)
		{
			print_no_permission();
		}
		else
		{
			if (!$threadinfo['open'])
			{
				$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
				eval(print_standard_redirect('redirect_threadclosed'));
			}
			// make sure this thread is owned by the user trying to delete it
			if (!is_first_poster($threadid))
			{
				print_no_permission();
			}
		}
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);

}

// ############################### start do delete thread ###############################
if ($_POST['do'] == 'dodeletethread')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'deletetype'		=> TYPE_UINT, 	// 1=leave message; 2=removal
		'deletereason'		=> TYPE_STR,
		'keepattachments'	=> TYPE_BOOL,
		)
	);

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'canremoveposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	$physicaldel = false;
	if (!can_moderate($threadinfo['forumid'], 'candeleteposts') AND !can_moderate($threadinfo['forumid'], 'canremoveposts'))
	{
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread']))
		{
			print_no_permission();
		}
		else if ($threadinfo['dateline'] < (TIMENOW - ($vbulletin->options['edittimelimit'] * 60)) AND $vbulletin->options['edittimelimit'] != 0)
		{
			print_no_permission();
		}
		else
		{
			if (!$threadinfo['open'])
			{
				$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
				eval(print_standard_redirect('redirect_threadclosed'));
			}
			if (!is_first_poster($threadinfo['threadid']))
			{
				print_no_permission();
			}
		}
	}
	else
	{
		if (!can_moderate($threadinfo['forumid'], 'canremoveposts'))
		{
			$physicaldel = false;
		}
		else if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
		{
			$physicaldel = true;
		}
		else
		{
			$physicaldel = iif($vbulletin->GPC['deletetype'] == 1, false, true);
		}
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$delinfo = array(
		'userid' => $vbulletin->userinfo['userid'],
		'username' => $vbulletin->userinfo['username'],
		'reason' => $vbulletin->GPC['deletereason'],
		'keepattachments' => $vbulletin->GPC['keepattachments']
	);

	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->delete($foruminfo['countposts'], $physicaldel, $delinfo);
	unset($threadman);

	build_forum_counters($threadinfo['forumid']);

	$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$threadinfo[forumid]";
	eval(print_standard_redirect('redirect_deletethread'));

}

// ############################### start retrieve ip ###############################
if ($_REQUEST['do'] == 'getip')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'ip' => TYPE_NOHTML
	));

	// check moderator permissions for getting ip
	if (!can_moderate($threadinfo['forumid'], 'canviewips'))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	if (!empty($vbulletin->GPC['ip']) AND preg_match('#^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$#', $vbulletin->GPC['ip']))
	{
		$postinfo['ipaddress'] =& $vbulletin->GPC['ip'];
	}
	else if (!$postinfo['postid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
	}

	$postinfo['hostaddress'] = @gethostbyaddr($postinfo['ipaddress']);

	($hook = vBulletinHook::fetch_hook('threadmanage_getip')) ? eval($hook) : false;

	eval(standard_error(fetch_error('thread_displayip', $postinfo['ipaddress'], $postinfo['hostaddress']), '', 0));
}

// ############################### start move thread ###############################
if ($_REQUEST['do'] == 'movethread')
{
	$templatename = 'threadadmin_movethread';

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	// check forum permissions for this forum
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$forumperms = fetch_permissions($forumid);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canmove']))
		{
			print_no_permission();
		}
		else
		{
			if (!$threadinfo['open'] AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']))
			{
				$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
				eval(print_standard_redirect('redirect_threadclosed', true, true));
			}
			if (!is_first_poster($threadinfo['threadid']))
			{
				print_no_permission();
			}
		}
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$title =& $threadinfo['title'];

	$curforumid = $threadinfo['forumid'];
	$moveforumbits = construct_move_forums_options();

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);
}

// ############################### start do move thread ###############################
if ($_POST['do'] == 'domovethread')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'destforumid' => TYPE_UINT,
		'method'      => TYPE_STR,
		'title'       => TYPE_NOHTML,
	));

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	// check whether dest can contain posts
	$destforumid = verify_id('forum', $vbulletin->GPC['destforumid']);
	$destforuminfo = fetch_foruminfo($destforumid);
	if (!$destforuminfo['cancontainthreads'] OR $destforuminfo['link'])
	{
		eval(standard_error(fetch_error('moveillegalforum')));
	}

	if (($threadinfo['isdeleted'] AND !can_moderate($destforuminfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($destforuminfo['forumid'], 'canmoderateposts')))
	{
		## Insert proper phrase about not being able to move a hidden thread to a forum you can't moderateposts in or a deleted thread to a forum you can't deletethreads in
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}

	// check source forum permissions
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canmove']))
		{
			print_no_permission();
		}
		else
		{
			if (!$threadinfo['open'] AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']))
			{
				$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
				eval(print_standard_redirect('redirect_threadclosed', true, true));
			}
			if (!is_first_poster($threadid))
			{
				print_no_permission();
			}
		}
	}

	// check destination forum permissions
	$forumperms = fetch_permissions($destforuminfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);
	verify_forum_password($destforuminfo['forumid'], $destforuminfo['password']);

	// check to see if this thread is being returned to a forum it's already been in
	// if a redirect exists already in the destination forum, remove it
	if ($checkprevious = $db->query_first("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE forumid = $destforuminfo[forumid] AND open = 10 AND pollid = $threadid"))
	{
		$old_redirect =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
		$old_redirect->set_existing($checkprevious);
		$old_redirect->delete(false, true, NULL, false);
		unset($old_redirect);
	}

	// get a valid method variable, set default to move with redirect if not specified
	switch($vbulletin->GPC['method'])
	{
		case 'copy':
		case 'move':
		case 'movered':
			break;

		default:
			$vbulletin->GPC['method'] = 'movered';
	}

	// check to see if this thread is being moved to the same forum it's already in but allow copying to the same forum
	if ($destforuminfo['forumid'] == $threadinfo['forumid'] AND $vbulletin->GPC['method']  != 'copy')
	{
		eval(standard_error(fetch_error('movesameforum')));
	}

	($hook = vBulletinHook::fetch_hook('threadmanage_move_start')) ? eval($hook) : false;

	if ($vbulletin->GPC['title'] != '' AND $vbulletin->GPC['title'] != $threadinfo['title'])
	{
		$oldtitle = $threadinfo['title'];
		$threadinfo['title'] = unhtmlspecialchars($vbulletin->GPC['title']);
		$updatetitle = true;
	}
	else
	{
		$oldtitle = $threadinfo['title'];
		$updatetitle = false;
	}

	switch($vbulletin->GPC['method'])
	{
		// ***************************************************************
		// move the thread wholesale into the destination forum
		case 'move':
			// update forumid/notes and unstick to prevent abuse
			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
			$threadman->set_info('skip_moderator_log', true);
			$threadman->set_existing($threadinfo);
			if ($updatetitle)
			{
				$threadman->set('title', $threadinfo['title']);
				if ($vbulletin->options['similarthreadsearch'])
				{
					require_once(DIR . '/includes/functions_search.php');
					$threadman->set('similar', fetch_similar_threads(fetch_censored_text($vbulletin->GPC['title']), $threadinfo['threadid']));
				}
			}
			else
			{	// Bypass check since title wasn't modified
				$threadman->set('title', $threadinfo['title'], true, false);
			}
			$threadman->set('forumid', $destforuminfo['forumid']);

			// If mod can not manage threads in destination forum then unstick thread
			if (!can_moderate($destforuminfo['forumid'], 'canmanagethreads'))
			{
				$threadman->set('sticky', 0);
			}

			($hook = vBulletinHook::fetch_hook('threadmanage_move_simple')) ? eval($hook) : false;

			$threadman->save();

			log_moderator_action($threadinfo, 'thread_moved_to_x', $destforuminfo['title']);

			break;
		// ***************************************************************


		// ***************************************************************
		// move the thread into the destination forum and leave a redirect
		case 'movered':

			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
			$threadman->set_info('skip_moderator_log', true);
			$threadman->set_existing($threadinfo);
			if ($updatetitle)
			{
				$threadman->set('title', $threadinfo['title']);
				if ($vbulletin->options['similarthreadsearch'])
				{
					require_once(DIR . '/includes/functions_search.php');
					$threadman->set('similar', fetch_similar_threads(fetch_censored_text($vbulletin->GPC['title']), $threadinfo['threadid']));
				}
			}
			else
			{	// Bypass check since title wasn't modified
				$threadman->set('title', $threadinfo['title'], true, false);
			}
			$threadman->set('forumid', $destforuminfo['forumid']);

			// If mod can not manage threads in destination forum then unstick thread
			if (!can_moderate($destforuminfo['forumid'], 'canmanagethreads'))
			{
				$threadman->set('sticky', 0);
			}

			($hook = vBulletinHook::fetch_hook('threadmanage_move_redirect_orig')) ? eval($hook) : false;

			$threadman->save();
			unset($threadman);

			if ($threadinfo['visible'] == 1)
			{	// Insert redirect for visible thread
				log_moderator_action($threadinfo, 'thread_moved_with_redirect_to_a', $destforuminfo['title']);

				$redirdata = array(
					'lastpost' => intval($threadinfo['lastpost']),
					'forumid' => intval($threadinfo['forumid']),
					'pollid' => intval($threadinfo['threadid']),
					'open' => 10,
					'replycount' => intval($threadinfo['replycount']),
					'postusername' => $threadinfo['postusername'],
					'postuserid' => intval($threadinfo['postuserid']),
					'lastposter' => $threadinfo['lastposter'],
					'dateline' => intval($threadinfo['dateline']),
					'views' => intval($threadinfo['views']),
					'iconid' => intval($threadinfo['iconid']),
					'visible' => 1
				);

				$redir =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
				foreach (array_keys($redirdata) AS $field)
				{
					// bypassing the verify_* calls; this data should be valid as is
					$redir->setr($field, $redirdata["$field"], true, false);
				}

				if ($updatetitle)
				{
					$redir->set('title', $threadinfo['title']);
				}
				else
				{	// Bypass check since title wasn't modified
					$redir->set('title', $threadinfo['title'], true, false);
				}

				($hook = vBulletinHook::fetch_hook('threadmanage_move_redirect_notice')) ? eval($hook) : false;

				$redir->save();
				unset($redir);
			}
			else
			{	// leave no redirect for hidden or deleted threads
				log_moderator_action($threadinfo, 'thread_moved_to_x', $destforuminfo['title']);
			}

			break;
		// ***************************************************************


		// ***************************************************************
		// make a copy of the thread in the redirect forum
		case 'copy':

			log_moderator_action($threadinfo, 'thread_copied_to_x', $destforuminfo['title']);

			if ($threadinfo['pollid'] AND $threadinfo['open'] != 10)
			{
				// We have a poll, need to duplicate it!
				if ($pollinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "poll WHERE pollid = $threadinfo[pollid]"))
				{
					$poll =& datamanager_init('Poll', $vbulletin, ERRTYPE_STANDARD);
					$poll->set('question',	$pollinfo['question']);
					$poll->set('dateline',	$pollinfo['dateline']);
					foreach (explode('|||', $pollinfo['options']) AS $option)
					{
						$poll->set_option($option);
					}
					$poll->set('active',	$pollinfo['active']);
					$poll->set('timeout',	$pollinfo['timeout']);
					$poll->set('multiple',	$pollinfo['multiple']);
					$poll->set('public',	$pollinfo['public']);

					if ($pollinfo['multiple'])
					{
						$poll->set('voters', $pollinfo['voters']);
					}

					$oldpollid = $threadinfo['pollid'];
					$threadinfo['pollid'] = $poll->save();

					$pollvotes = $db->query_read("SELECT userid, votedate, voteoption FROM " . TABLE_PREFIX . "pollvote WHERE pollid = $oldpollid");

					while ($pollvote = $db->fetch_array($pollvotes))
					{
						$new_pollvote =& datamanager_init('PollVote', $vbulletin, ERRTYPE_STANDARD);
						if ($pollinfo['multiple'])
						{
							$new_pollvote->set_info('skip_voters', true);
						}
						$new_pollvote->set('pollid', 		$threadinfo['pollid']);
						$new_pollvote->set('votedate', 		$pollvote['votedate']);
						$new_pollvote->set('voteoption', 	$pollvote['voteoption']);
						$new_pollvote->set('userid', 		$pollvote['userid']);
						$new_pollvote->save();
					}
				}
			}

			// duplicate thread, save a few columns
			$newthreadinfo = $threadinfo;
			$delinfo = array(
				'userid'   => $threadinfo['del_userid'],
				'username' => $threadinfo['del_username'],
				'reason'   => $threadinfo['del_reason'],
			);

			unset($newthreadinfo['vote'], $newthreadinfo['threadid'], $newthreadinfo['sticky'], $newthreadinfo['votenum'], $newthreadinfo['votetotal'], $newthreadinfo['isdeleted'], $newthreadinfo['del_userid'], $newthreadinfo['del_username'], $newthreadinfo['del_reason'], $newthreadinfo['issubscribed'], $newthreadinfo['emailupdate'], $newthreadinfo['folderid']);
			$newthreadinfo['forumid'] = $destforuminfo['forumid'];

			$threadcopy =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
			foreach (array_keys($threadcopy->validfields) AS $field)
			{
				if (isset($newthreadinfo["$field"]))
				{
					// bypassing the verify_* calls; this data should be valid as is
					$threadcopy->setr($field, $newthreadinfo["$field"], true, false);
				}
			}

			if ($updatetitle AND $vbulletin->options['similarthreadsearch'])
			{
				require_once(DIR . '/includes/functions_search.php');
				$threadcopy->set('similar', fetch_similar_threads(fetch_censored_text($vbulletin->GPC['title']), $threadinfo['threadid']));
			}

			($hook = vBulletinHook::fetch_hook('threadmanage_move_copy_threadcopy')) ? eval($hook) : false;
			$newthreadid = $threadcopy->save();
			$newthreadinfo['threadid'] = $newthreadid;
			unset($threadcopy);

			require_once(DIR . '/includes/functions_file.php');

			// duplicate posts
			$posts = $db->query_read("
				SELECT post.*,
					deletionlog.userid AS deleteduserid, deletionlog.username AS deletedusername, deletionlog.reason AS deletedreason,
					NOT ISNULL(deletionlog.primaryid) AS isdeleted
				FROM " . TABLE_PREFIX . "post AS post
				LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND type = 'post')
				WHERE threadid = $threadid
				ORDER BY dateline
			");

			$firstpost = false;
			$userbyuserid = array();
			$postarray = array();
			$postassoc = array();

			$deleteinfo = array();
			$hiddeninfo = array();

			while ($post = $db->fetch_array($posts))
			{
				if ($post['title'] == $oldtitle AND $updatetitle)
				{
					$post['title'] = $threadinfo['title'];
					$update_post_title = true;
				}
				else
				{
					$update_post_title = false;
				}

				$oldpostid = $post['postid'];
				unset($post['postid']);

				$post['threadid'] = $newthreadid;

				$postcopy =& datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
				foreach (array_keys($postcopy->validfields) AS $field)
				{
					if (isset($post["$field"]))
					{
						// bypassing the verify_* calls; this data should be valid as is
						$postcopy->setr($field, $post["$field"], true, false);
					}
				}
				($hook = vBulletinHook::fetch_hook('threadmanage_move_copy_postcopy')) ? eval($hook) : false;
				$newpostid = $postcopy->save();
				unset($postcopy);

				if (!$firstpost)
				{
					if (!$threadinfo['visible'])
					{	// Insert Moderation Record
						$db->query_write("
							INSERT INTO " . TABLE_PREFIX . "moderation
							(threadid, postid, type)
							VALUES
							($newthreadid, $newpostid, 'thread')
						");
					}
					else if ($threadinfo['visible'] == 2)
					{
						// Insert Deletion record
						$db->query_write("
							INSERT INTO " . TABLE_PREFIX . "deletionlog
							(primaryid, type, userid, username, reason)
							VALUES
							($newthreadid, 'thread', " . intval($delinfo['userid']) . ", '" . $db->escape_string($delinfo['username']) . "', '" . $db->escape_string($delinfo['reason']) . "')
						");
					}

					$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
					$threadman->set_existing($newthreadinfo);
					$threadman->set('firstpostid', $newpostid);
					$threadman->save();
					unset($threadman);

					$firstpost = true;
				}

				if (!$post['visible'])
				{
					$hiddeninfo[] = "($newthreadid, $newpostid, 'post')";
				}
				else if ($post['visible'] == 2)
				{
					$deleteinfo[] = "($newpostid, 'post', " . intval($post['deleteduserid']) . ", '" . $db->escape_string($post['deletedusername']) . "', '". $db->escape_string($post['deletedreason']) . "')";
				}

				$parentcasesql .= " WHEN parentid = $oldpostid THEN $newpostid";
				$parentids .= ",$oldpostid"; // doubles as a list of original post IDs
				$postassoc["$oldpostid"] = $newpostid; // same as $postarray, but set in all cases; for attachments

				// Source forum doesn't indexposts so we must generate these new words
				if (!$foruminfo['indexposts'] AND $destforuminfo['indexposts'])
				{
					build_post_index($newpostid, $destforuminfo);
				}
				else if ($foruminfo['indexposts'] AND $destforuminfo['indexposts'])
				{
					if ($update_post_title == true)
					{
						// we have a new title for this post, so it needs to be reindexed
						build_post_index($newpostid, $destforuminfo);
					}
					else
					{
						// Source forum indexes posts so we can duplicate the words we already have
						$postarray["$oldpostid"] = $newpostid;
					}
				}

				if ($destforuminfo['countposts'] AND $post['userid'] AND $threadinfo['visible'] == 1 AND $post['visible'] == 1)
				{
					if (!isset($userbyuserid["$post[userid]"]))
					{
						$userbyuserid["$post[userid]"] = 1;
					}
					else
					{
						$userbyuserid["$post[userid]"]++;
					}
				}
			}

			// need to read filedata in chunks and update in chunks!
			$attachments = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "attachment WHERE postid IN (0$parentids)");
			while ($attachment = $db->fetch_array($attachments))
			{
				$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_ARRAY);
				$attachdata->setr('userid', $attachment['userid']);
				$attachdata->setr('dateline', $attachment['dateline']);
				$attachdata->setr('filename', $attachment['filename']);
				$attachdata->setr('postid', $postassoc["$attachment[postid]"]);
				$attachdata->setr('visible', $attachment['visible']);
				if ($vbulletin->options['attachfile'])
				{
					$attachdata->set('filedata', @file_get_contents(fetch_attachment_path($attachment['userid'], $attachment['attachmentid'])));
					$attachdata->set('thumbnail', @file_get_contents(fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], true)));
				}
				else
				{
					$attachdata->setr('filedata', $attachment['filedata']);
					$attachdata->setr('thumbnail', $attachment['thumbnail']);
				}
				$attachdata->save();
				unset($attachdata);
			}

			// Duplicate word entries in the postindex
			if (!empty($postarray) AND $vbulletin->options['copypostindex'] AND !$vbulletin->options['fulltextsearch'])
			{
				$db->query_write("CREATE TABLE " . TABLE_PREFIX . "postindex_temp$newthreadid (
					wordid INT UNSIGNED NOT NULL DEFAULT '0',
					postid INT UNSIGNED NOT NULL DEFAULT '0',
					intitle SMALLINT UNSIGNED NOT NULL DEFAULT '0',
					score SMALLINT UNSIGNED NOT NULL DEFAULT '0'
				)"); // indexes left off intentionally

				$postcase = '';
				foreach ($postarray AS $oldid => $newid)
				{
					$postcase .= "WHEN $oldid THEN $newid\n";
				}

				/*insert query*/
				$db->query_write("
					INSERT INTO " . TABLE_PREFIX . "postindex_temp$newthreadid
						(wordid, postid, intitle, score)
					SELECT wordid, CASE postid $postcase ELSE postid END AS postid,
						intitle, score
						FROM " . TABLE_PREFIX . "postindex AS postindex
						WHERE postid IN (" . implode(',', array_keys($postarray)) . ")
				");

				/*insert query*/
				$db->query_write("
					INSERT INTO " . TABLE_PREFIX . "postindex
						(wordid, postid, intitle, score)
					SELECT wordid, postid, intitle, score FROM " . TABLE_PREFIX . "postindex_temp$newthreadid
				");

				$db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . "postindex_temp$newthreadid");
			}

			// Insert Deleted Posts
			if (!empty($deletedinfo))
			{
				/*insert query*/
				$db->query_write("
					INSERT INTO " . TABLE_PREFIX . "deletionlog
					(primaryid, type, userid, username, reason)
					VALUES
					" . implode(', ', $deletedinfo) . "
				");
			}

			// Insert Moderated Posts
			if (!empty($hiddeninfo))
			{
				/*insert query*/
				$db->query_write("
					INSERT INTO " . TABLE_PREFIX . "moderation
					(threadid, postid, type)
					VALUES
					" . implode(', ', $hiddeninfo) . "
				");
			}

			// Insert Deleted Posts
			if (!empty($deleteinfo))
			{
				/*insert query*/
				$db->query_write("
					INSERT INTO " . TABLE_PREFIX . "deletionlog
					(primaryid, type, userid, username, reason)
					VALUES
					" . implode(', ', $deleteinfo) . "
				");
			}

			// reconnect parent/child posts in the new thread
			if ($parentcasesql)
			{
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "post SET
						parentid = CASE $parentcasesql ELSE parentid END
					WHERE threadid = $newthreadid AND parentid IN (0$parentids)
				");
			}

			// Update User Post Counts
			if (!empty($userbyuserid))
			{
				$userbypostcount = array();
				foreach ($userbyuserid AS $postuserid => $postcount)
				{
					$alluserids .= ",$postuserid";
					$userbypostcount["$postcount"] .= ",$postuserid";
				}
				foreach ($userbypostcount AS $postcount => $userids)
				{
					$postcasesql .= " WHEN userid IN (0$userids) THEN $postcount";
				}

				$db->query_write("
					UPDATE " . TABLE_PREFIX . "user SET
						posts = posts + CASE $postcasesql ELSE 0 END
					WHERE userid IN (0$alluserids)
				");
			}

			break;
		// ***************************************************************

	} // end switch($method)

	// Update Post Count if we move from a counting forum to a non counting or vice-versa..
	// Source Dest  Visible Thread    Hidden Thread
	// Yes    Yes   ~           	  ~
	// Yes    No    -visible          ~
	// No     Yes   +visible          ~
	// No     No    ~                 ~
	if ($threadinfo['visible'] AND ($vbulletin->GPC['method'] == 'move' OR $vbulletin->GPC['method'] == 'movered') AND (($foruminfo['countposts'] AND !$destforuminfo['countposts']) OR (!$foruminfo['countposts'] AND $destforuminfo['countposts'])))
	{
		$posts = $db->query_read("
			SELECT userid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadinfo[threadid]
				AND	userid > 0
				AND visible = 1
		");
		$userbyuserid = array();
		while ($post = $db->fetch_array($posts))
		{
			if (!isset($userbyuserid["$post[userid]"]))
			{
				$userbyuserid["$post[userid]"] = 1;
			}
			else
			{
				$userbyuserid["$post[userid]"]++;
			}
		}

		if (!empty($userbyuserid))
		{
			$userbypostcount = array();
			foreach ($userbyuserid AS $postuserid => $postcount)
			{
				$alluserids .= ",$postuserid";
				$userbypostcount["$postcount"] .= ",$postuserid";
			}
			foreach ($userbypostcount AS $postcount => $userids)
			{
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount";
			}

			$operator = ($destforuminfo['countposts'] ? '+' : '-');

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET posts = posts $operator
					CASE
						$casesql
						ELSE 0
					END
				WHERE userid IN (0$alluserids)
			");
		}
	}

	if ($updatetitle)
	{
		// Reindex first post to set up title properly.
		$getfirstpost = $db->query_first("
			SELECT postid, title, pagetext
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadid
			ORDER BY dateline
			LIMIT 1
		");
		delete_post_index($getfirstpost['postid'], $getfirstpost['title'], $getfirstpost['pagetext']);
		build_post_index($getfirstpost['postid'] , $foruminfo);
	}

	build_forum_counters($threadinfo['forumid']);
	if ($threadinfo['forumid'] != $destforuminfo['forumid'])
	{
		build_forum_counters($destforuminfo['forumid']);
	}

	// Update canview status of thread subscriptions
	update_subscriptions(array('threadids' => array($threadid)));

	if ($vbulletin->GPC['method'] == 'copy' AND $newthreadid)
	{
		$threadid = $newthreadid;
	}

	($hook = vBulletinHook::fetch_hook('threadmanage_move_complete')) ? eval($hook) : false;

	$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
	eval(print_standard_redirect('redirect_movethread'));
}

// ############################### start manage post ###############################
if ($_REQUEST['do'] == 'managepost')
{
	$templatename = 'threadadmin_managepost';

	if ($postinfo['postid'] == $threadinfo['firstpostid'])
	{	// first post
		// redirect to edit thread
		$_REQUEST['do'] = 'editthread';
	}
	else
	{
		if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
		{
			print_no_permission();
		}

		verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

		$show['undeleteoption'] = iif($postinfo['isdeleted'] AND (can_moderate($threadinfo['forumid'], 'canremoveposts') OR can_moderate($threadinfo['forumid'], 'candeleteposts')), true, false);


		require_once(DIR . '/includes/class_bbcode.php');
		$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
		$postinfo['pagetext'] = $bbcode_parser->parse($postinfo['pagetext'], $forumid);

		$postinfo['postdate'] = vbdate($vbulletin->options['dateformat'], $postinfo['dateline'], 1);
		$postinfo['posttime'] = vbdate($vbulletin->options['timeformat'], $postinfo['dateline']);

		$visiblechecked = iif($postinfo['visible'], 'checked="checked"');

		// draw nav bar
		$navbits = construct_postings_nav($foruminfo, $threadinfo);
	}
}

// ############################### start edit thread ###############################
if ($_REQUEST['do'] == 'editthread')
{
	$templatename = 'threadadmin_editthread';

	// only mods with the correct permissions should be able to access this
	if (!can_moderate($threadinfo['forumid'], 'caneditthreads') OR ($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts') AND !can_moderate($threadinfo['forumid'], 'canremoveposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$show['undeleteoption'] = ($threadinfo['visible'] == 2 AND can_moderate($threadinfo['forumid'], 'candeleteposts')) ? true : false;
	$show['removeoption'] = ($threadinfo['visible'] == 2 AND can_moderate($threadinfo['forumid'], 'canremoveposts')) ? true : false;
	$show['moderateoption'] = (can_moderate($threadinfo['forumid'], 'canmoderateposts') AND $threadinfo['visible'] != 2) ? true : false;

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);

	$visiblechecked = iif($threadinfo['visible'], 'checked="checked"');
	$visiblehidden = iif($threadinfo['visible'], 'yes');
	$openchecked = iif($threadinfo['open'], 'checked="checked"');
	$stickychecked = iif($threadinfo['sticky'], 'checked="checked"');

	require_once(DIR . '/includes/functions_newpost.php');
	$posticons = construct_icons($threadinfo['iconid'], $foruminfo['allowicons']);

	$show['ipaddress'] = can_moderate($threadinfo['forumid'], 'canviewips') ? true : false;

	$logs = $db->query_read("
		SELECT moderatorlog.dateline, moderatorlog.userid, moderatorlog.action, moderatorlog.type, moderatorlog.postid, moderatorlog.ipaddress,
			user.username,
			post.title
		FROM " . TABLE_PREFIX . "moderatorlog AS moderatorlog
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderatorlog.userid)
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (moderatorlog.postid = post.postid)
		WHERE moderatorlog.threadid = $threadid
		ORDER BY dateline
	");

	while ($log = $db->fetch_array($logs))
	{
		exec_switch_bg();

		if ($log['type'])
		{
			$phrase = fetch_modlogactions($log['type']);

			if ($unserialized = unserialize($log['action']))
			{
				array_unshift($unserialized, $vbphrase["$phrase"]);
				$log['action'] = call_user_func_array('construct_phrase', $unserialized);
			}
			else
			{
				$log['action'] = construct_phrase($vbphrase["$phrase"], $log['action']);
			}
		}

		if ($log['title'] == '')
		{
			$log['title'] = $vbphrase['n_a'];
		}

		$log['dateline'] = vbdate($vbulletin->options['logdateformat'], $log['dateline']);
		$log['ipaddress'] = htmlspecialchars_uni($log['ipaddress']); // Sanity ;0
		eval('$logbits .= "' . fetch_template('threadadmin_logbit') . '";');
	}
	$show['modlog'] = iif($logbits, true, false);

}

// ############################### start update thread ###############################
if ($_POST['do'] == 'updatethread')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'visible'      => TYPE_BOOL,
		'open'         => TYPE_BOOL,
		'sticky'       => TYPE_BOOL,
		'iconid'       => TYPE_UINT,
		'notes'        => TYPE_NOHTML,
		'threadstatus' => TYPE_UINT,
		'reason'       => TYPE_NOHTML,
		'title'        => TYPE_STR
	));

	// only mods with the correct permissions should be able to access this
	if (!can_moderate($threadinfo['forumid'], 'caneditthreads'))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	if ($vbulletin->GPC['title'] == '')
	{
		eval(standard_error(fetch_error('notitle')));
	}

	if (!can_moderate($threadinfo['forumid'], 'canopenclose') AND !$forumperms['canopenclose'])
	{
		$vbulletin->GPC['open'] = $threadinfo['open'];
	}

	if ($threadinfo['visible'] == 2)
	{	// Editing a deleted thread
		if ($vbulletin->GPC['threadstatus'] == 1 AND can_moderate($threadinfo['forumid'], 'candeleteposts'))
		{ // undelete
			undelete_thread($threadinfo['threadid'], $foruminfo['countposts']);
			$threaddeleted = -1;
		}
		else if ($vbulletin->GPC['threadstatus'] == 2 AND can_moderate($threadinfo['forumid'], 'canremoveposts'))
		{ // remove
			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
			$threadman->set_existing($threadinfo);
			$threadman->delete($foruminfo['countposts'], true);
			unset($threadman);

			$threaddeleted = 1;
		}
		else
		{
			if ($vbulletin->GPC['reason'] != '')
			{
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "deletionlog SET
						reason = '" . $db->escape_string($vbulletin->GPC['reason']) . "'
					WHERE primaryid = $threadinfo[threadid] AND type = 'thread'
				");
			}
			$threaddeleted = 0;
		}
	}
	else
	{	// Editing a non deleted thread
		if (can_moderate($threadinfo['forumid'], 'canmoderateposts'))
		{
			if ($threadinfo['visible'] == 1 AND !$vbulletin->GPC['visible'])
			{
				unapprove_thread($threadid, $foruminfo['countposts'], true, $threadinfo);
			}
			else if (!$threadinfo['visible'] AND $vbulletin->GPC['visible'])
			{
				approve_thread($threadid, $foruminfo['countposts'], true, $threadinfo);
			}
		}
		$threaddeleted = 0;
	}

	if ($threaddeleted != 1)
	{
		// Reindex first post to set up title properly.
		$getfirstpost = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadinfo[threadid]
			ORDER BY dateline
			LIMIT 1
		");
		$getfirstpost['threadtitle'] =& $vbulletin->GPC['title'];
		delete_post_index($getfirstpost['postid'], $getfirstpost['title'], $getfirstpost['pagetext']);
		build_post_index($getfirstpost['postid'] , $foruminfo, 1, $getfirstpost);

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
		$threadman->set_info('skip_moderator_log', true);
		$threadman->set_existing($threadinfo);
		$threadman->set('open', $vbulletin->GPC['open']);
		$threadman->set('sticky', $vbulletin->GPC['sticky']);
		$threadman->set('iconid', $vbulletin->GPC['iconid'], true, false);
		$threadman->set('notes', $vbulletin->GPC['notes']);
		if ($vbulletin->options['similarthreadsearch'])
		{
			require_once(DIR . '/includes/functions_search.php');
			$threadman->set('similar', fetch_similar_threads(fetch_censored_text($vbulletin->GPC['title']), $threadinfo['threadid']));
		}

		// re-enable mod logging for the title since we don't include it in the other log info
		$threadman->set_info('skip_moderator_log', false);
		$threadman->set('title', $vbulletin->GPC['title']);

		($hook = vBulletinHook::fetch_hook('threadmanage_update')) ? eval($hook) : false;
		$threadman->save();

	}

	build_forum_counters($threadinfo['forumid']);

	log_moderator_action($threadinfo, 'thread_edited_visible_x_open_y_sticky_z', array($vbulletin->GPC['visible'], $vbulletin->GPC['open'], $vbulletin->GPC['sticky']));

	if ((!$vbulletin->GPC['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')) OR $threaddeleted == 1 OR ($threadinfo['isdeleted'] AND $threaddeleted != -1))
	{
		$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$threadinfo[forumid]";
	}
	else
	{
		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
	}
	eval(print_standard_redirect('redirect_editthread'));
}

// ############################### start merge threads ###############################
if ($_REQUEST['do'] == 'mergethread')
{
	$templatename = 'threadadmin_mergethread';

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	// check forum permissions for this forum
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);
}

// ############################### start do merge threads ###############################
if ($_POST['do'] == 'domergethread')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'mergethreadurl'	=> TYPE_STR,
		'title'				=> TYPE_STR
	));

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	// check forum permissions for this forum
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	// relative URLs will do bad things here, so don't let them through; thanks Paul! :)
	if (stristr($vbulletin->GPC['mergethreadurl'], 'goto=next'))
	{
		eval(standard_error(fetch_error('mergebadurl')));
	}

	// eliminate everything but the query string
	if ($strpos = strpos($vbulletin->GPC['mergethreadurl'], '?'))
	{
		$vbulletin->GPC['mergethreadurl'] = substr($vbulletin->GPC['mergethreadurl'], $strpos);
	}
	else
	{
		eval(standard_error(fetch_error('mergebadurl')));
	}

	// pull out the thread/postid
	if (preg_match('#(threadid|t)=([0-9]+)#', $vbulletin->GPC['mergethreadurl'], $matches))
	{
		$mergethreadid = intval($matches[2]);
	}
	else if (preg_match('#(postid|p)=([0-9]+)#', $vbulletin->GPC['mergethreadurl'], $matches))
	{
		$mergepostid = verify_id('post', $matches[2], 0);
		if ($mergepostid == 0)
		{
			// do invalid url
			eval(standard_error(fetch_error('mergebadurl')));
		}

		$postinfo = fetch_postinfo($mergepostid);
		$mergethreadid = $postinfo['threadid'];
	}
	else
	{
		eval(standard_error(fetch_error('mergebadurl')));
	}

	$mergethreadid = verify_id('thread', $mergethreadid);
	$mergethreadinfo = fetch_threadinfo($mergethreadid);
	$mergeforuminfo = fetch_foruminfo($mergethreadinfo['forumid']);

	if ($mergethreadid == $threadid OR ($mergethreadinfo['isdeleted'] AND !can_moderate($mergethreadinfo['forumid'], 'candeleteposts')) OR (!$mergethreadinfo['visible'] AND !can_moderate($mergethreadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($mergethreadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	// check forum permissions for the merge forum
	$mergeforumperms = fetch_permissions($mergethreadinfo['forumid']);
	if (!($mergeforumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !can_moderate($mergethreadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($mergeforuminfo['forumid'], $mergeforuminfo['password']);

	// get the first post from each thread -- we only need to reindex those
	$thrd_firstpost = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = $threadinfo[threadid]
		ORDER BY dateline ASC
		LIMIT 1
	");
	$mrgthrd_firstpost = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = $mergethreadinfo[threadid]
		ORDER BY dateline ASC
		LIMIT 1
	");

	($hook = vBulletinHook::fetch_hook('threadmanage_merge_start')) ? eval($hook) : false;

	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set('title', $vbulletin->GPC['title']);
	$threadman->set('views', $threadinfo['views'] + $mergethreadinfo['views']);

	// sort out polls
	if ($mergethreadinfo['pollid'] != 0)
	{ // merge thread has poll ...
		if ($threadinfo['pollid'] == 0)
		{ // ... and original thread doesn't
			$threadman->set('pollid', $mergethreadinfo['pollid']);
			$threadcache["$mergethreadinfo[threadid]"]['pollid'] = 0;
		}
		else
		{ // ... and original does
			// if the poll isn't found anywhere else, delete the merge thread's poll
			if (!$poll = $db->query_first("
				SELECT threadid
				FROM " . TABLE_PREFIX . "thread
				WHERE pollid = $mergethreadinfo[pollid] AND
					threadid <> $mergethreadinfo[threadid] AND
					open <> 10
				"))
			{
				$pollman =& datamanager_init('Poll', $vbulletin, ERRTYPE_STANDARD);
				$pollman->set_existing($mergethreadinfo);
				$pollman->delete();
			}
		}
	}

	$threadman->save();

	// Update Post Count if we merge from a counting forum to a non counting or vice-versa.. hidden thread to a visible thread, moderated to visible (and so on)
	// Source Dest  Visible Thread    Hidden Thread
	// Yes    Yes   +hidden           -visible
	// Yes    No    -visible          -visible
	// No     Yes   +visible,+hidden  ~
	// No     No    ~                 ~

	if 	(($threadinfo['visible'] AND $foruminfo['countposts'] AND ($mergethreadinfo['visible'] != 1 OR ($mergethreadinfo['visible'] == 1 AND !$mergeforuminfo['countposts'])))
			OR
		($mergethreadinfo['visible'] == 1 AND $mergeforuminfo['countposts'] AND ($threadinfo['visible'] != 1 OR ($threadinfo['visible'] == 1 AND !$foruminfo['countposts']))))
	{
		$posts = $db->query_read("
			SELECT userid, threadid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $mergethreadinfo[threadid]
				AND visible = 1
				AND userid > 0
		");
		while ($post = $db->fetch_array($posts))
		{
			$set = 0;

			// Visible thread that merges a visible thread from a non counting forum into a counting forum - Increment post counts belonging to visible threads
			// visible thread that merges a moderated or deleted thread into a counting forum - increment post counts belonging to a hidden/deleted source thread
			if ($threadinfo['visible'] AND $foruminfo['countposts'] AND ($mergethreadinfo['visible'] != 1 OR ($mergethreadinfo['visible'] == 1 AND !$mergeforuminfo['countposts'])))
			{
				$set = 1;
			}

			// hidden thread that merges a visible thread from a counting forum
			// OR visible thread that merges a visible thread from a counting forum into a non counting forum
			// decrement post counts belonging to a visible source thread
			else if ($mergethreadinfo['visible'] == 1 AND $mergeforuminfo['countposts'] AND ($threadinfo['visible'] != 1 OR ($threadinfo['visible'] == 1 AND !$foruminfo['countposts'])))
			{
				$set = -1;
			}

			if ($set != 0)
			{
				if (!isset($userbyuserid["$post[userid]"]))
				{
					$userbyuserid["$post[userid]"] = $set;
				}
				else if ($set == -1)
				{
					$userbyuserid["$post[userid]"]--;
				}
				else
				{
					$userbyuserid["$post[userid]"]++;
				}
			}
		}

		if (!empty($userbyuserid))
		{
			$userbypostcount = array();
			$alluserids = '';
			foreach ($userbyuserid AS $postuserid => $postcount)
			{
				$alluserids .= ",$postuserid";
				$userbypostcount["$postcount"] .= ",$postuserid";
			}
			foreach($userbypostcount AS $postcount => $userids)
			{
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount\n";
			}

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET posts = posts +
				CASE
					$casesql
					ELSE 0
				END
				WHERE userid IN (0$alluserids)
			");
		}
	}

	// move posts
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "post
		SET threadid = $threadinfo[threadid]
		WHERE threadid = $mergethreadinfo[threadid]
	");

	// update first post relationships
	if ($thrd_firstpost['dateline'] > $mrgthrd_firstpost['dateline'])
	{
		if (!$threadinfo['visible'])
		{
			// Update thread moderation record to reflect new first post
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "moderation
				SET postid = $mrgthrd_firstpost[postid]
				WHERE threadid = $threadinfo[threadid]
					AND type = 'thread'
			");

			// Update original first post to now be moderated, insert moderation record
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "moderation
				(threadid, postid, type)
				VALUES
				($threadinfo[threadid], $thrd_firstpost[postid], 'reply')
			");
		}

		// thread being merged into is newer, so the merged thread's first post should become this thread's first post
		$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
		$postman->set_existing($thrd_firstpost);
		$postman->set('parentid', $mrgthrd_firstpost['postid']);

		// Update original first post to now be moderated
		if (!$threadinfo['visible'])
		{
			$postman->set('visible', 0);
		}
		$postman->save();
	}
	else
	{
		if (!$mergethreadinfo['visible'])
		{
			// Change moderation entry for a hidden thread to point to a hidden post
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "moderation
				SET threadid = $threadinfo[threadid],
					type = 'reply'
				WHERE threadid = $mergethreadinfo[threadid]
					AND type = 'thread'
			");
		}

		$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
		$postman->set_existing($mrgthrd_firstpost);
		$postman->set('parentid', $thrd_firstpost['postid']);

		// Update merged thread's first post to be hidden since the thread was
		if (!$mergethreadinfo['visible'])
		{
			$postman->set('visible', 0);
		}
		$postman->save();
	}
	unset($postman);

	// Update any moderation entries for hidden posts to point to their new master
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "moderation
		SET threadid = $threadinfo[threadid]
		WHERE threadid = $mergethreadinfo[threadid]
			AND type = 'reply'
	");

	// Update redirects
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "thread
		SET pollid = $threadinfo[threadid]
		WHERE open = 10
			AND pollid = $mergethreadinfo[threadid]
	");

	$users = array();
	$ratings = $db->query_read("
		SELECT threadrateid, threadid, userid, vote, ipaddress
		FROM " . TABLE_PREFIX . "threadrate
		WHERE threadid IN($mergethreadinfo[threadid], $threadinfo[threadid])
	");
	while ($rating = $db->fetch_array($ratings))
	{
		$id = (!empty($rating['userid'])) ? $rating['userid'] : $rating['ipaddress'];
		$users["$id"]['vote'] += $rating['vote'];
		$users["$id"]['total'] += 1;
	}

	if (!empty($users))
	{
		$sql = array();
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "threadrate
			WHERE threadid IN($mergethreadinfo[threadid], $threadinfo[threadid])
		");

		foreach ($users AS $id => $rating)
		{
			if (is_int($id))
			{
				$userid = $id;
				$ipaddress = '';
			}
			else
			{
				$userid = 0;
				$ipaddress = $id;
			}

			$vote = round($rating['vote'] / $rating['total']);
			$sql[] = "($threadinfo[threadid], $userid, $vote, '" . $db->escape_string($ipaddress) . "')";
		}
		unset($users);

		if (!empty($sql))
		{
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "threadrate
					(threadid, userid, vote, ipaddress)
				VALUES
					" . implode(",\n", $sql)
			);
			unset($sql);
		}
	}

	// Update subscribed threads
	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "subscribethread
		SET threadid = $threadinfo[threadid]
		WHERE threadid = $mergethreadinfo[threadid]
	");

	// Update Moderation Log entries
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "moderatorlog
		SET threadid = $threadinfo[threadid]
		WHERE threadid = $mergethreadinfo[threadid]
	");

	if ($mergethreadinfo['forumid'] != $threadinfo['forumid'])
	{
		// update canview status of thread subscriptions
		update_subscriptions(array('threadids' => array($threadinfo['threadid'])));
	}

	// remove remnants of merge thread
	$merge_thread =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	$merge_thread->set_existing($mergethreadinfo);
	$merge_thread->delete(false, true, NULL, false);
	unset($merge_thread);

	// update postindex for the 2 posts who's titles may have changed (first post of each thread)
	delete_post_index($thrd_firstpost['postid']);
	delete_post_index($mrgthrd_firstpost['postid']);
	build_post_index($thrd_firstpost['postid'] , $foruminfo);
	build_post_index($mrgthrd_firstpost['postid'] , $foruminfo);

	build_thread_counters($threadinfo['threadid']);
	build_forum_counters($threadinfo['forumid']);
	if ($mergethreadinfo['forumid'] != $threadinfo['forumid'])
	{
		build_forum_counters($mergethreadinfo['forumid']);
	}

	log_moderator_action($threadinfo, 'thread_merged_with_x', $mergethreadinfo['title']);

	($hook = vBulletinHook::fetch_hook('threadmanage_merge_complete')) ? eval($hook) : false;

	$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
	eval(print_standard_redirect('redirect_mergethread'));

}

// ############################### start split thread ###############################
if ($_REQUEST['do'] == 'splitthread')
{
	$templatename = 'threadadmin_splitthread';

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	if ($threadedmode)
	{
		$show['children'] = true;
	}

	// draw nav bar
	$navbits = construct_postings_nav($foruminfo, $threadinfo);

	$postbits =& construct_post_tree('threadadmin_postbit', $threadid);
	$parentpostassoc =& construct_js_post_parent_assoc($parentassoc);

	$curforumid = $threadinfo['forumid'];
	$moveforumbits = construct_move_forums_options();

}

// ############################### start do split thread ###############################
if ($_POST['do'] == 'dosplitthread')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'checkpost'		=> TYPE_ARRAY_BOOL,
		'newforumid'	=> TYPE_UINT,
		'title'			=> TYPE_NOHTML,
	));

	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	unset($vbulletin->GPC['checkpost'][0]); // make sure this isn't set -- could do some weird things

	($hook = vBulletinHook::fetch_hook('threadmanage_split_start')) ? eval($hook) : false;

	$doyes = 0;
	$dono = 0;
	if (!empty($vbulletin->GPC['checkpost']))
	{
		$splitcheck = '';
		foreach ($vbulletin->GPC['checkpost'] AS $postid => $val)
		{
			$splitcheck .= ',' . intval($postid);
		}
		$splitcheck = substr($splitcheck, 1);
		if (!$splitcheck)
		{
			$dono = 1;
		}
		else
		{
			$count = $db->query_first("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = $threadinfo[threadid]
					AND postid NOT IN ($splitcheck)
			");
			if ($count['count'] == 0)
			{ // that means all posts were selected
				$doyes = 1;
			}
		}
	}
	else
	{
		$dono = 1;
	}
	if ($doyes == 0 AND $dono == 1)
	{ // Selected no posts to split
		eval(standard_error(fetch_error('nosplitposts')));
	}
	else if ($doyes == 1 AND $dono == 0)
	{ // Selected all posts to split
		eval(standard_error(fetch_error('cantsplitall')));
	}
	if ($vbulletin->GPC['newforumid'])
	{
		$newforumid = verify_id('forum', $vbulletin->GPC['newforumid']);
		$destforuminfo = fetch_foruminfo($newforumid);
		if (!$destforuminfo['cancontainthreads'] OR $destforuminfo['link'])
		{
			eval(standard_error(fetch_error('moveillegalforum')));
		}
		// check destination forum permissions
		$forumperms = fetch_permissions($destforuminfo['forumid']);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			print_no_permission();
		}
	}

	$newthreadnotes  = construct_phrase($vbphrase['thread_split_from_threadid_a_by_b_on_x_at_d'], $threadinfo['threadid'], $vbulletin->userinfo['username'], vbdate($vbulletin->options['dateformat'], TIMENOW), vbdate($vbulletin->options['timeformat'], TIMENOW));
	$newthreadnotes .= ' ' . $threadinfo['notes'];

	// Move post info to new thread...
	$parentassoc = array();
	$wasmoved = array();
	$userbyuserid = array();
	$posts = $db->query_read("
		SELECT postid, parentid, dateline, userid
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = $threadinfo[threadid]
		ORDER BY dateline
	");
	while ($post = $db->fetch_array($posts))
	{
		if (!$newthreadid)
		{
			//prevent a thread from being created if the posts have already been split
			$newthreadinfo = array(
				'title' => $vbulletin->GPC['title'],
				'lastpost' => $threadinfo['lastpost'],
				'forumid' => $newforumid,
				'open' => $threadinfo['open'],
				'replycount' => $threadinfo['replycount'],
				'hiddencount' => $threadinfo['hiddencount'],
				'postusername' => $threadinfo['postusername'],
				'postuserid' => $threadinfo['postuserid'],
				'lastposter' => $threadinfo['lastposter'],
				'dateline' => $threadinfo['dateline'],
				'views' => 0,
				'iconid' => $threadinfo['iconid'],
				'notes' => $newthreadnotes,
				'visible' => $threadinfo['visible']
			);

			$threadcopy =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
			foreach (array_keys($threadcopy->validfields) AS $field)
			{
				if (isset($newthreadinfo["$field"]))
				{
					// bypassing the verify_* calls; this data should be valid as is
					$threadcopy->setr($field, $newthreadinfo["$field"], true, false);
				}
			}
			($hook = vBulletinHook::fetch_hook('threadmanage_split_newthread')) ? eval($hook) : false;
			$newthreadid = $threadcopy->save();
			unset($threadcopy);
		}

		$parentassoc["{$post['postid']}"] = $post['parentid'];
		if ($vbulletin->GPC['checkpost']["{$post['postid']}"])
		{
			$movepostids .= ",$post[postid]";

			if ($post['userid'] AND (($foruminfo['countposts'] AND !$destforuminfo['countposts']) OR (!$foruminfo['countposts'] AND $destforuminfo['countposts'])))
			{
				if (!isset($userbyuserid["{$post['userid']}"]))
				{
					$userbyuserid["{$post['userid']}"] = 1;
				}
				else
				{
					$userbyuserid["{$post['userid']}"]++;
				}
			}
		}
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "post
		SET threadid = $newthreadid
		WHERE postid IN (0$movepostids)
	");

	if (!empty($userbyuserid))
	{
		$userbypostcount = array();
		foreach ($userbyuserid AS $postuserid => $postcount)
		{
			$alluserids .= ",$postuserid";
			$userbypostcount["$postcount"] .= ",$postuserid";
		}
		foreach($userbypostcount AS $postcount => $userids)
		{
			$postcasesql .= " WHEN userid IN (0$userids) THEN $postcount";
		}

		$operator = iif($destforuminfo['countposts'], '+', '-');

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET posts = posts $operator
			CASE
				$postcasesql
			ELSE 0
			END
			WHERE userid IN (0$alluserids)
		");
	}

	$parentupdate = array();

	// update parentids
	$nosplittop = 0;
	$splittop = 0;
	foreach ($parentassoc AS $postid => $parentid)
	{
		if (!$vbulletin->GPC['checkpost']["$postid"] AND $vbulletin->GPC['checkpost']["$parentid"])
		{
			// this post wasn't moved, but it's parent was, so we need to walk up the chain to find the next post that
			// wasn't moved and make this post a child of that one
			do
			{
				$parentid = $parentassoc["$parentid"];
			}
			while ($vbulletin->GPC['checkpost']["$parentid"]);

			if ($parentid !== NULL)
			{
				if ($parentid == 0)
				{
					// this prevents two posts from becoming the topmost post
					if ($nosplittop == 0)
					{
						$nosplittop = $postid;
					}
					else
					{
						$parentid = $nosplittop;
					}
				}
				$parentcasesql .= " WHEN postid = $postid THEN " . intval($parentid);
				$allpostids .= ",$postid";
			}
		}
		else if ($vbulletin->GPC['checkpost']["$postid"] AND !$vbulletin->GPC['checkpost']["$parentid"])
		{
			// this post was split, but it's parent wasn't
			do
			{
				$parentid = $parentassoc["$parentid"];
			}
			while (!$vbulletin->GPC['checkpost']["$parentid"] AND $parentid != 0); // $parentid check to prevent infinite loop

			if ($parentid !== NULL)
			{
				if ($parentid == 0)
				{
					// this prevents two posts from becoming the topmost post
					if ($splittop == 0)
					{
						$splittop = $postid;
					}
					else
					{
						$parentid = $splittop;
					}
				}
				$parentcasesql .= " WHEN postid = $postid THEN " . intval($parentid);
				$allpostids .= ",$postid";
			}
		}
	}

	if ($parentcasesql)
	{

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "post
			SET parentid =
			CASE
				$parentcasesql
			ELSE
				parentid
			END
			WHERE postid IN (0$allpostids)
		");
	}

	// update new thread's first post to have the correct title
	// $db->query_write("UPDATE " . TABLE_PREFIX . "post SET title = '" . $db->escape_string($vbulletin->GPC['title']) . "' WHERE threadid = $newthreadid AND parentid = 0 AND title = ''");

	// Update first post in each thread as title information in relation to the sames words being in the first post may have changed now.
	$getfirstpost = $db->query_first("SELECT postid, title, pagetext FROM " . TABLE_PREFIX . "post WHERE threadid = $threadid ORDER BY dateline LIMIT 1");
	delete_post_index($getfirstpost['postid'], $getfirstpost['title'], $getfirstpost['pagetext']);
	build_post_index($getfirstpost['postid'] , $foruminfo);

	$getfirstpost = $db->query_first("SELECT postid, title, pagetext FROM " . TABLE_PREFIX . "post WHERE threadid = $newthreadid ORDER BY dateline LIMIT 1");
	delete_post_index($getfirstpost['postid'],$getfirstpost['title'], $getfirstpost['pagetext']);
	build_post_index($getfirstpost['postid'] , $foruminfo);

	$postdeleted = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $getfirstpost[postid] AND type='post'");
	if ($postdeleted['primaryid'])
	{ // first post is deleted, make thread deleted instead
		$threaddeleted = 1;
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "deletionlog
			SET primaryid = $newthreadid, type = 'thread'
			WHERE primaryid = $getfirstpost[postid] AND type='post'
		");

		$threaddata =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
		$threaddata->set_condition("threadid = $newthreadid");
		$threaddata->set('visible', 2);
		$threaddata->save();
	}

	build_thread_counters($threadid);
	build_thread_counters($newthreadid);
	build_forum_counters($threadinfo['forumid']);
	if ($newforumid != $threadinfo['forumid'])
	{
		build_forum_counters($newforumid);
	}

	log_moderator_action($threadinfo, 'thread_split_to_x', $newthreadid);

	($hook = vBulletinHook::fetch_hook('threadmanage_split_complete')) ? eval($hook) : false;

	$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$newthreadid";
	eval(print_standard_redirect('redirect_splitthread'));
}

// ############################### start stick / unstick thread ###############################
if ($_POST['do'] == 'stick')
{
	if (($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')) OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		if (can_moderate($threadinfo['forumid']))
		{
			print_no_permission();
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
		}
	}

	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// handles mod log
	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set('sticky', ($threadman->fetch_field('sticky') == 1 ? 0 : 1));

	($hook = vBulletinHook::fetch_hook('threadmanage_stickunstick')) ? eval($hook) : false;
	$threadman->save();

	if ($threadinfo['sticky'])
	{
		$action = $vbphrase['unstuck'];
	}
	else
	{
		$action = $vbphrase['stuck'];
	}

	$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
	eval(print_standard_redirect('redirect_sticky', true, true));
}

// ############################### start remove redirects ###############################
if ($_POST['do'] == 'removeredirect')
{
	if (!can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$redirects = $db->query_read("
		SELECT threadid, forumid
		FROM " . TABLE_PREFIX . "thread
		WHERE open = 10 AND pollid = $threadid
	");
	while ($redirect = $db->fetch_array($redirects))
	{
		verify_forum_password($redirect['forumid'], $vbulletin->forumcache["$redirect[forumid]"]['password']);

		$old_redirect =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
		$old_redirect->set_existing($redirect);
		$old_redirect->delete(false, true);
		unset($old_redirect);
	}

	($hook = vBulletinHook::fetch_hook('threadmanage_removeredirect')) ? eval($hook) : false;

	$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
	eval(print_standard_redirect('redirects_removed', true, true));
}

// ############################### start manage post ###############################
if ($_POST['do'] == 'domanagepost')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'poststatus'	=> TYPE_UINT,
		'reason'		=> TYPE_NOHTML,
	));


	if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		print_no_permission();
	}

	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	if ($vbulletin->GPC['poststatus'] == 1)
	{
		// undelete
		$postdeleted = -1;
	}
	else if ($vbulletin->GPC['poststatus'] == 2 AND can_moderate($threadinfo['forumid'], 'canremoveposts'))
	{
		// remove
		$postdeleted = 1;
	}
	else
	{
		// leave as is
		$postdeleted = 0;
	}

	if ($postdeleted != 1)
	{
		$db->query_write("UPDATE " . TABLE_PREFIX . "deletionlog SET reason = '" . $db->escape_string($vbulletin->GPC['reason']) . "' WHERE primaryid = $postid AND type = 'post'");
	}

	if ($postdeleted == -1)
	{
		undelete_post($postid, $foruminfo['countposts'], $postinfo, $threadinfo);
	}
	else if ($postdeleted == 1)
	{
		$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
		$postman->set_existing($postinfo);
		$postman->delete($foruminfo['countposts'], $threadinfo['threadid'], 1);
		unset($postman);
	}

	($hook = vBulletinHook::fetch_hook('threadmanage_managepost')) ? eval($hook) : false;

	if ($postdeleted != 1)
	{
		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$postid#post$postid";
	}
	else
	{
		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
	}

	eval(print_standard_redirect('redirect_post_manage'));
}

// ############################### all done, do shell template ###############################

if ($templatename != '')
{
	// draw navbar
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('threadmanage_complete')) ? eval($hook) : false;

	// spit out the final HTML if we have got this far
	eval('$HTML = "' . fetch_template($templatename) . '";');
	eval('print_output("' . fetch_template('THREADADMIN') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14418 $
|| ####################################################################
\*======================================================================*/
?>
