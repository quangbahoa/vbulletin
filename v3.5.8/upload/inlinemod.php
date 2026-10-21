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
if ($_REQUEST['do'] == 'mergeposts' OR $_POST['do'] == 'domergeposts')
{
	define('GET_EDIT_TEMPLATES', true);
}
define('THIS_SCRIPT', 'inlinemod');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('threadmanage', 'posting', 'inlinemod');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'THREADADMIN'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'mergethread'  => array('threadadmin_mergethreads'),
	'deletethread' => array('threadadmin_deletethreads'),
	'movethread'   => array('threadadmin_movethreads'),
	'moveposts'    => array('threadadmin_moveposts'),
	'mergeposts'   => array('threadadmin_mergeposts'),
	'domergeposts' => array('threadadmin_mergeposts'),
	'deleteposts'  => array('threadadmin_deleteposts'),
);

// ####################### PRE-BACK-END ACTIONS ##########################
require_once('./global.php');
require_once(DIR . '/includes/functions_editor.php');
require_once(DIR . '/includes/functions_threadmanage.php');
require_once(DIR . '/includes/functions_databuild.php');
require_once(DIR . '/includes/functions_log_error.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// Wouldn't be fun if someone tried to manipulate every post in the database ;)
// Should be made into options I suppose - too many and you exceed what a cookie can hold anyway
$postlimit = 500;
$threadlimit = 200;

if (!can_moderate())
{
	print_no_permission();
}

// This is a list of ids that were checked on the page we submitted from
$vbulletin->input->clean_array_gpc('p', array(
	'tlist' => TYPE_ARRAY_KEYS_INT,
	'plist' => TYPE_ARRAY_KEYS_INT,
));

// If we have javascript, all ids should be in here
$vbulletin->input->clean_array_gpc('c', array(
	'vbulletin_inlinethread' => TYPE_STR,
	'vbulletin_inlinepost'   => TYPE_STR,
));

// Combine ids sent from the form and what we have in the cookie
if (!empty($vbulletin->GPC['vbulletin_inlinethread']))
{
	$tlist = explode('-', $vbulletin->GPC['vbulletin_inlinethread']);
	$tlist = $vbulletin->input->clean($tlist, TYPE_ARRAY_UINT);

	$vbulletin->GPC['tlist'] = array_unique(array_merge($tlist, $vbulletin->GPC['tlist']));
}

if (!empty($vbulletin->GPC['vbulletin_inlinepost']))
{
	$plist = explode('-', $vbulletin->GPC['vbulletin_inlinepost']);
	$plist = $vbulletin->input->clean($plist, TYPE_ARRAY_UINT);

	$vbulletin->GPC['plist'] = array_unique(array_merge($plist, $vbulletin->GPC['plist']));
}

switch ($_POST['do'])
{
	case 'open':
	case 'close':
	case 'stick':
	case 'unstick':
	case 'deletethread':
	case 'undeletethread':
	case 'approvethread':
	case 'unapprovethread':
	case 'movethread':
	case 'mergethread':

		if (empty($vbulletin->GPC['tlist']))
		{
			eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
		}

		if (count($vbulletin->GPC['tlist']) > $threadlimit)
		{
			eval(standard_error(fetch_error('you_are_limited_to_working_with_x_threads', $threadlimit)));
		}

		$threadids = implode(',', $vbulletin->GPC['tlist']);
		break;

	case 'dodeletethreads':
	case 'domovethreads':
	case 'domergethreads':
		$vbulletin->input->clean_array_gpc('p', array(
			'threadids'   => TYPE_STR,
		));

		$threadids = explode(',', $vbulletin->GPC['threadids']);
		foreach ($threadids AS $index => $threadid)
		{
			if (intval($threadid) == 0)
			{
				unset($threadids["$index"]);
			}
			else
			{
				$threadids["$index"] = intval($threadid);
			}

		}

		if (empty($threadids))
		{
			eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
		}

		if (count($threadids) > $threadlimit)
		{
			eval(standard_error(fetch_error('you_are_limited_to_working_with_x_threads', $threadlimit)));
		}

		break;

	case 'deleteposts':
	case 'undeleteposts':
	case 'approveposts':
	case 'unapproveposts':
	case 'mergeposts':
	case 'moveposts':
	case 'approveattachments':
	case 'unapproveattachments':

		if (empty($vbulletin->GPC['plist']))
		{
			eval(standard_error(fetch_error('no_applicable_posts_selected')));
		}

		if (count($vbulletin->GPC['plist']) > $postlimit)
		{
			eval(standard_error(fetch_error('you_are_limited_to_working_with_x_posts', $postlimit)));
		}
		$postids = implode(',', $vbulletin->GPC['plist']);
		break;

	case 'dodeleteposts':
	case 'domergeposts':
	case 'domoveposts':
		$vbulletin->input->clean_array_gpc('p', array(
			'postids'   => TYPE_STR,
		));

		$postids = explode(',', $vbulletin->GPC['postids']);
		foreach ($postids AS $index => $postid)
		{
			if (intval($postid) == 0)
			{
				unset($postids["$index"]);
			}
			else
			{
				$postids["$index"] = intval($postid);
			}
		}

		if (empty($postids))
		{
			eval(standard_error(fetch_error('no_applicable_posts_selected')));
		}

		if (count($postids) > $postlimit)
		{
			eval(standard_error(fetch_error('you_are_limited_to_working_with_x_posts', $postlimit)));
		}
		break;

	case 'clearthread':
	case 'clearpost':
		break;

	default: // throw and error about invalid $_REQUEST['do']
		$handled_do = false;
		($hook = vBulletinHook::fetch_hook('inlinemod_action_switch')) ? eval($hook) : false;
		if (!$handled_do)
		{
			eval(standard_error(fetch_error('invalid_action')));
		}
}

$threadarray = array();
$postarray = array();
$postinfos = array();
$forumlist = array();
$threadlist = array();

($hook = vBulletinHook::fetch_hook('inlinemod_start')) ? eval($hook) : false;

// ############################### Empty Thread Cookie ###############################
if ($_POST['do'] == 'clearthread')
{
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_clearthread')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_threadlist_cleared'));
}

// ############################### Empty Post Cookie ###############################
if ($_POST['do'] == 'clearpost')
{
	setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_clearpost')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_postlist_cleared'));
}

// ############################### start do open / close thread ###############################
if ($_POST['do'] == 'open' OR $_POST['do'] == 'close')
{

	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, visible, forumid, postuserid, title
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN ($threadids)
			AND open = " . ($_POST['do'] == 'open' ? 0 : 1) . "
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($thread['forumid'], 'canopenclose'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_openclose_threads', $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}

		$threadarray["$thread[threadid]"] = $thread;
	}

	if (!empty($threadarray))
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "thread
			SET open = " . ($_POST['do'] == 'open' ? 1 : 0) . "
			WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")

		");

		foreach (array_keys($threadarray) AS $threadid)
		{
			$modlog[] = array(
				'userid'   =>& $vbulletin->userinfo['userid'],
				'forumid'  =>& $threadarray["$threadid"]['forumid'],
				'threadid' => $threadid,
			);
		}

		log_moderator_action($modlog, ($_POST['do'] == 'open') ? 'opened_thread' : 'closed_thread');
	}

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_closeopen')) ? eval($hook) : false;

	if ($_POST['do'] == 'open')
	{
		eval(print_standard_redirect('redirect_inline_opened'));
	}
	else
	{
		eval(print_standard_redirect('redirect_inline_closed'));
	}
}

// ############################### start do stick / unstick thread ###############################
if ($_POST['do'] == 'stick' OR $_POST['do'] == 'unstick')
{
	$redirect = array();

	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, open, visible, forumid, postuserid, title
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN ($threadids)
			AND sticky = " . ($_POST['do'] == 'stick' ? 0 : 1) . "
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($thread['forumid'], 'canmanagethreads'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_stickunstick_threads', $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}
		else if (!$thread['visible'] AND !can_moderate($foruminfo['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($foruminfo['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}

		$threadarray["$thread[threadid]"] = $thread;
		if ($thread['open'] == 10)
		{
			$redirect[] = $thread['threadid'];
		}
	}

	if (!empty($threadarray))
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "thread
			SET sticky = " . ($_POST['do'] == 'stick' ? 1 : 0) . "
			WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
		");

		foreach (array_keys($threadarray) AS $threadid)
		{
			if (!in_array($threadid, $redirect))
			{	// Don't add log entry for (un)sticking a redirect
				$modlog[] = array(
					'userid'   =>& $vbulletin->userinfo['userid'],
					'forumid'  =>& $threadarray["$threadid"]['forumid'],
					'threadid' => $threadid,
				);
			}
		}

		log_moderator_action($modlog, ($_POST['do'] == 'stick') ? 'stuck_thread' : 'unstuck_thread');
	}

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_stickunstick')) ? eval($hook) : false;

	if ($_POST['do'] == 'stick')
	{
		eval(print_standard_redirect('redirect_inline_stuck'));
	}
	else
	{
		eval(print_standard_redirect('redirect_inline_unstuck'));
	}
}

// ############################### start do delete thread ###############################
if ($_POST['do'] == 'deletethread')
{
	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, open, visible, forumid, title, postuserid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN ($threadids)
	");

	$show['removethreads'] = true;
	$show['deletethreads'] = true;
	$show['deleteoption'] = true;
	$checked = array('delete' => 'checked="checked"');

	$redirectcount = 0;
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if ($thread['open'] == 10)
		{
			if (!can_moderate($thread['forumid'], 'canmanagethreads'))
			{
				// No permission to remove redirects.
				eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_thread_redirects', $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
			}
			else
			{
				$redirectcount++;
			}
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}
		else if (!can_moderate($thread['forumid'], 'canremoveposts'))
		{
			if (!can_moderate($thread['forumid'], 'candeleteposts'))
			{
				eval(standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
			}
			else if (!$show['deletethreads'])
			{
				eval(standard_error(fetch_error('you_do_not_share_delete_permission')));
			}
			else
			{
				$show['removethreads'] = false;
				$show['deleteoption'] = false;
			}
		}
		else if (!can_moderate($thread['forumid'], 'candeleteposts'))
		{
			if (!can_moderate($thread['forumid'], 'canremoveposts'))
			{
				eval(standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
			}
			else if (!$show['removethreads'])
			{
				eval(standard_error(fetch_error('you_do_not_share_delete_permission')));
			}
			else
			{
				$checked = array('remove' => 'checked="checked"');
				$show['deletethreads'] = false;
				$show['deleteoption'] = false;
			}
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"] = true;
	}

	if (empty($threadarray))
	{
		eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
	}

	($hook = vBulletinHook::fetch_hook('inlinemod_deletethread')) ? eval($hook) : false;

	$threadcount = count($threadarray);
	$forumcount = count($forumlist);

	if ($threadcount == $redirectcount)
	{	// selected all redirects so delet-o-matic them

		$delinfo = array(
			'userid' => $vbulletin->userinfo['userid'],
			'username' => $vbulletin->userinfo['username'],
			'reason' => '',
			'keepattachments' => 0
		);

		foreach ($threadarray AS $threadid => $thread)
		{
			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$threadman->set_existing($thread);
			$threadman->delete(false, true, $delinfo);
			unset($threadman);
		}

		// empty cookie
		setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

		($hook = vBulletinHook::fetch_hook('inlinemod_dodeletethread')) ? eval($hook) : false;

		eval(print_standard_redirect('redirect_inline_deleted'));
	}
	else
	{
		$navbits[''] = $vbphrase['delete_threads'];
		$template = 'threadadmin_deletethreads';
	}
}

// ############################### start dodelete threads ###############################
if ($_POST['do'] == 'dodeletethreads')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'deletetype'      => TYPE_UINT, 	// 1=leave message; 2=removal
		'deletereason'    => TYPE_STR,
		'keepattachments' => TYPE_BOOL,
	));

	$physicaldel = iif($vbulletin->GPC['deletetype'] == 1, false, true);

	$delinfo = array(
		'userid' => $vbulletin->userinfo['userid'],
		'username' => $vbulletin->userinfo['username'],
		'reason' => $vbulletin->GPC['deletereason'],
		'keepattachments' => $vbulletin->GPC['keepattachments']
	);

	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, open, visible, forumid, title, postuserid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN(" . implode(',', $threadids) . ")
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if ($thread['open'] == 10 AND !can_moderate($thread['forumid'], 'canmanagethreads'))
		{
			// No permission to remove redirects.
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_thread_redirects', $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}
		else if ($thread['open'] != 10)
		{
			if (!can_moderate($thread['forumid'], 'canremoveposts') AND $physicaldel)
			{
				eval(standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
			}
			else if (!can_moderate($thread['forumid'], 'candeleteposts') AND !$physicaldel)
			{
				eval(standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
			}
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"] = true;
	}

	if (empty($threadarray))
	{
			eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
	}

	foreach ($threadarray AS $threadid => $thread)
	{
		$countposts = $vbulletin->forumcache["$thread[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['countposts'];
		if (!$physicaldel AND $thread['visible'] == 2)
		{
			# Thread is already soft deleted
			continue;
		}

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threadman->set_existing($thread);

		// Redirect
		if ($thread['open'] == 10)
		{
			$threadman->delete(false, true, $delinfo);
		}
		else
		{
			$threadman->delete($countposts, $physicaldel, $delinfo);
		}
		unset($threadman);
	}

	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_dodeletethread')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_deleted'));
}

// ############################### start do undelete thread ###############################
if ($_POST['do'] == 'undeletethread')
{

	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, visible, forumid, title, postuserid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN ($threadids)
			AND visible = 2
			AND open <> 10
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($thread['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"] = true;
	}

	if (empty($threadarray))
	{
			eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
	}

	foreach ($threadarray AS $threadid => $thread)
	{
		$countposts = $vbulletin->forumcache["$thread[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['countposts'];
		undelete_thread($thread['threadid'], $countposts, $thread);
	}

	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_undeletethread')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_undeleted'));
}

// ############################### start do approve thread ###############################
if ($_POST['do'] == 'approvethread')
{

	$countingthreads = array();
	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, visible, forumid, postuserid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN($threadids)
			AND visible = 0
			AND open <> 10
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}


		if (!can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"] = true;

		$foruminfo = fetch_foruminfo($thread['forumid']);
		if ($foruminfo['countposts'])
		{	// this thread is in a counting forum
			$countingthreads[] = $thread['threadid'];
		}
	}

	if (empty($threadarray))
	{
			eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
	}

	// Set threads visible
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "thread
		SET visible = 1
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
	");

	if (!empty($countingthreads))
	{	// Update post count for visible posts
		$userbyuserid = array();
		$posts = $db->query_read("
			SELECT userid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid IN(" . implode(',', $countingthreads) . ")
				AND visible = 1
				AND userid > 0
		");
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

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "moderation
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
			AND type = 'thread'
	");

	foreach ($threadarray AS $threadid => $thread)
	{
		$modlog[] = array(
			'userid'   =>& $vbulletin->userinfo['userid'],
			'forumid'  =>& $thread['forumid'],
			'threadid' => $threadid,
		);
	}

	log_moderator_action($modlog, 'approved_thread');

	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_approvethread')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_approvedthreads'));
}

// ############################### start do unapprove thread ###############################
if ($_POST['do'] == 'unapprovethread')
{

	$threadarray = array();
	$countingthreads = array();
	$modrecords = array();

	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, visible, forumid, title, postuserid, firstpostid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN($threadids)
			AND visible > 0
			AND open <> 10
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}


		if (!can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($foruminfo['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"] = true;

		$foruminfo = fetch_foruminfo($thread['forumid']);
		if ($thread['visible'] AND $foruminfo['countposts'])
		{	// this thread is visible AND in a counting forum
			$countingthreads[] = $thread['threadid'];
		}

		$modrecords[] = "($thread[threadid], $thread[firstpostid], 'thread')";
	}

	if (empty($threadarray))
	{
			eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
	}

	// Set threads hidden
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "thread
		SET visible = 0
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
	");

	if (!empty($countingthreads))
	{	// Update post count for visible posts
		$userbyuserid = array();
		$posts = $db->query_read("
			SELECT userid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid IN(" . implode(',', $countingthreads) . ")
				AND visible = 1
				AND userid > 0
		");
		while ($post = $db->fetch_array($posts))
		{
			if (!isset($userbyuserid["$post[userid]"]))
			{
				$userbyuserid["$post[userid]"] = -1;
			}
			else
			{
				$userbyuserid["$post[userid]"]--;
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

	// Insert Moderation Records
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "moderation
		(threadid, postid, type)
		VALUES
		" . implode(',', $modrecords) . "
	");

	// Clean out deletionlog
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "deletionlog
		WHERE primaryid IN(" . implode(',', array_keys($threadarray)) . ")
			AND type = 'thread'
	");

	foreach ($threadarray AS $threadid => $thread)
	{
		$modlog[] = array(
			'userid'   =>& $vbulletin->userinfo['userid'],
			'forumid'  =>& $thread['forumid'],
			'threadid' => $threadid,
		);
	}

	log_moderator_action($modlog, 'unapproved_thread');

	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_unapprovethread')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_unapprovedthreads'));
}

// ############################### start do move thread ###############################
if ($_POST['do'] == 'movethread')
{

	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, open, visible, forumid, title, postuserid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN ($threadids)
	");

	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if ($thread['open'] == 10 AND !can_moderate($thread['forumid'], 'canmanagethreads'))
		{
			// No permission to remove redirects.
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_thread_redirects', $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"]++;
	}

	if (empty($threadarray))
	{
			eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
	}

	$threadcount = count($threadarray);
	$forumcount = count($forumlist);

	asort($forumlist, SORT_NUMERIC);
	$curforumid = array_pop($array = array_keys($forumlist));
	$moveforumbits = construct_move_forums_options();

	($hook = vBulletinHook::fetch_hook('inlinemod_movethread')) ? eval($hook) : false;

	$navbits[''] = $vbphrase['move_threads'];
	$template = 'threadadmin_movethreads';
}

// ############################### start do domove thread ###############################
if ($_POST['do'] == 'domovethreads')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'destforumid' => TYPE_UINT,
		'method'      => TYPE_STR,
	));

	// check whether dest can contain posts
	$destforumid = verify_id('forum', $vbulletin->GPC['destforumid']);
	$destforuminfo = fetch_foruminfo($destforumid);
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

	$countingthreads = array();
	$redirectids = array();

	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, visible, open, pollid, title, postuserid, forumid
		" . ($vbulletin->GPC['method'] == 'movered' ? ", lastpost, replycount, postusername, lastposter, dateline, views, iconid" : "") . "
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN(" . implode(',', $threadids) . ")
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($thread['forumid'], 'canmanagethreads'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}

		if ($thread['visible'] == 2 AND !can_moderate($destforuminfo['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts_in_destination_forum')));
		}
		else if (!$thread['visible'] AND !can_moderate($destforuminfo['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts_in_destination_forum')));
		}

		// Ignore all threads that are already in the destination forum
		if ($thread['forumid'] == $destforuminfo['forumid'])
		{
			$sameforum = true;
			continue;
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"] = true;

		if ($thread['open'] == 10)
		{
			$redirectids["$thread[pollid]"][] = $thread['threadid'];
		}
		else if ($thread['visible'])
		{
			$countingthreads[] = $thread['threadid'];
		}
	}

	if (empty($threadarray))
	{
		if ($sameforum)
		{
			eval(standard_error(fetch_error('thread_is_already_in_the_forum')));
		}
		else
		{
			eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
		}
	}

	// check to see if these threads are being returned to a forum they've already been in
	// if redirects exist in the destination forum, remove them
	$checkprevious = $db->query_read("
		SELECT threadid
		FROM " . TABLE_PREFIX . "thread
		WHERE forumid = $destforuminfo[forumid]
			AND open = 10
			AND pollid IN(" . implode(',', array_keys($threadarray)) . ")
	");
	while ($check = $db->fetch_array($checkprevious))
	{
		$old_redirect =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$old_redirect->set_existing($check);
		$old_redirect->delete(false, true, NULL, false);
		unset($old_redirect);
	}

	// check to see if a redirect is being moved to a forum where its destination thread already exists
	// if so delete the redirect
	if (!empty($redirectids))
	{
		$checkprevious = $db->query_read("
			SELECT threadid
			FROM " . TABLE_PREFIX . "thread
			WHERE forumid = $destforuminfo[forumid]
				AND threadid IN(" . implode(',', array_keys($redirectids)) . ")

		");
		while ($check = $db->fetch_array($checkprevious))
		{
			if (!empty($redirectids["$check[threadid]"]))
			{
				foreach($redirectids["$check[threadid]"] AS $threadid)
				{
					$old_redirect =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
					$old_redirect->set_existing($threadarray["$threadid"]);
					$old_redirect->delete(false, true, NULL, false);
					unset($old_redirect);

					# Remove redirect threadids from $threadarray so no log entry is entered below or new redirect is added
					unset($threadarray["$threadid"]);
				}
			}
		}
	}

	if (!empty($threadarray))
	{
		// Move threads
		// If mod can not manage threads in destination forum then unstick all moved threads
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "thread
			SET forumid = $destforuminfo[forumid]
			" . (!can_moderate($destforuminfo['forumid'], 'canmanagethreads') ? ", sticky = 0" : "") . "
			WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
		");

		// update canview status of thread subscriptions
		update_subscriptions(array('threadids' => array_keys($threadarray)));

		$movelog = array();
		// Insert Redirects FUN FUN FUN
		if ($vbulletin->GPC['method'] == 'movered')
		{
			foreach($threadarray AS $threadid => $thread)
			{
				if ($thread['visible'] == 1)
				{
					$thread['open'] = 10;
					$thread['pollid'] = $threadid;
					unset($thread['threadid']);
					$redir =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
					foreach (array_keys($thread) AS $field)
					{
						// bypassing the verify_* calls; this data should be valid as is
						$redir->setr($field, $thread["$field"], true, false);
					}
					$redir->save();
					unset($redir);
				}
				else
				{
					// else this is a moderated or deleted thread so leave no redirect behind
					// insert modlog entry of just "move", not "moved with redirect"
					// unset threadarray[threadid] so thread_moved_with_redirect log entry is not entered below.

					unset($threadarray["$threadid"]);
					$movelog = array(
						'userid'   =>& $vbulletin->userinfo['userid'],
						'forumid'  =>& $thread['forumid'],
						'threadid' => $threadid,
					);
				}
			}
		}

		if (!empty($movelog))
		{
			log_moderator_action($movelog, 'thread_moved_to_x', $destforuminfo['title']);
		}

		if (!empty($threadarray))
		{
			foreach ($threadarray AS $threadid => $thread)
			{
				$modlog[] = array(
					'userid'   =>& $vbulletin->userinfo['userid'],
					'forumid'  =>& $thread['forumid'],
					'threadid' => $threadid,
				);
			}

			log_moderator_action($modlog, ($vbulletin->GPC['method'] == 'move') ? 'thread_moved_to_x' : 'thread_moved_with_redirect_to_a', $destforuminfo['title']);

			if (!empty($countingthreads))
			{
				$posts = $db->query_read("
					SELECT userid, threadid
					FROM " . TABLE_PREFIX . "post
					WHERE threadid IN(" . implode(',', $countingthreads) . ")
						AND visible = 1
						AND	userid > 0
				");
				$userbyuserid = array();
				while ($post = $db->fetch_array($posts))
				{
					$foruminfo = fetch_foruminfo($threadarray["$post[threadid]"]['forumid']);
					if ($foruminfo['countposts'] AND !$destforuminfo['countposts'])
					{	// Take away a post
						if (!isset($userbyuserid["$post[userid]"]))
						{
							$userbyuserid["$post[userid]"] = -1;
						}
						else
						{
							$userbyuserid["$post[userid]"]--;
						}
					}
					else if (!$foruminfo['countposts'] AND $destforuminfo['countposts'])
					{	// Add a post
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

				if (!empty($userbyuserid))
				{
					$userbypostcount = array();
					$alluserids = '';

					foreach ($userbyuserid AS $postuserid => $postcount)
					{
						$alluserids .= ",$postuserid";
						$userbypostcount["$postcount"] .= ",$postuserid";
					}
					foreach ($userbypostcount AS $postcount => $userids)
					{
						$casesql .= " WHEN userid IN (0$userids) THEN $postcount";
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
		}
	}

	foreach(array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}
	build_forum_counters($destforuminfo['forumid']);

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_domovethread')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_moved', true, true));
}

// ############################### start do merge thread ###############################
if ($_POST['do'] == 'mergethread')
{
	if (!can_moderate($foruminfo['forumid'], 'canmanagethreads'))
	{
		print_no_permission();
	}

	$foundpoll = false;

	// Validate threads
	$threads = $db->query_read("
		SELECT threadid, visible, open, pollid, title, postuserid, forumid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN($threadids)
			AND open <> 10
		ORDER BY dateline
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($thread['forumid'], 'canmanagethreads'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}

		if ($thread['pollid'])
		{
			if ($foundpoll)
			{
				eval(standard_error(fetch_error('merged_thread_can_contain_one_poll')));
			}
			else
			{
				$foundpoll = true;
			}
		}

		if (empty($title))
		{
			$title = $thread['title'];
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"]++;
	}

	if (empty($threadarray))
	{
			eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
	}

	$threadcount = count($threadarray);
	$forumcount = count($forumlist);

	if ($threadcount == 1)
	{
		eval(standard_error(fetch_error('not_much_would_be_accomplished_by_merging')));
	}

	$max = 0;
	foreach ($forumlist AS $forumid => $count)
	{
		if ($count > $max)
		{
			$curforumid = $forumid;
		}
	}

	foreach ($threadarray AS $thread)
	{
		$optiontitle = "[{$thread['threadid']}] $thread[title]";
		$optionvalue = $thread['threadid'];
		$optionclass = '';
		eval('$movethreadbits .= "' . fetch_template('option') . '";');
	}

	$moveforumbits = construct_move_forums_options();

	($hook = vBulletinHook::fetch_hook('inlinemod_mergethread')) ? eval($hook) : false;

	// draw navbar
	$navbits = array();

	$navbits[''] = $vbphrase['merge_threads'];
	$template = 'threadadmin_mergethreads';

}

// ############################### start do domerge thread ###############################
if ($_POST['do'] == 'domergethreads')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'destforumid'  => TYPE_UINT,
		'destthreadid' => TYPE_UINT,
		'redirect'     => TYPE_BOOL,
	));

	// check whether dest can contain posts
	$destforumid = verify_id('forum', $vbulletin->GPC['destforumid']);
	$destforuminfo = fetch_foruminfo($destforumid);
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

	if ($vbulletin->GPC['type'] == 1)
	{	// Mod cannot create merged hidden thread if they can't moderateposts dest forum
		if (!can_moderate($destforuminfo['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts_in_destination_forum')));
		}
	}
	else if ($vbulletin->GPC['type'] == 2)
	{	// Mod can not create merged deleted thread if they can't deletethreads in dest forum
		if (!can_moderate($destforuminfo['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts_in_destination_forum')));
		}
	}

	$counter = array(
		'moderated' => array(),
		'normal'    => array(),
		'deleted'   => array()
	);

	$destthread = 0;
	$pollinfo = array();
	$firstthread = array();
	$views = 0;
	$firstpostids = array();

	$sticky = 1;

	// Validate threads
	$threads = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN(" . implode(',', $threadids) . ")
			AND open <> 10
		ORDER BY dateline
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($thread['forumid'], 'canmanagethreads'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $vbphrase['n_a'], $thread['title'], $vbulletin->forumcache["$thread[forumid]"]['title'])));
		}

		if ($thread['pollid'])
		{
			if (!empty($pollinfo))
			{
				eval(standard_error(fetch_error('merged_thread_can_contain_one_poll')));
			}
			else
			{
				$pollinfo = array(
					'pollid'    => $thread['pollid'],
					'votenum'   => $thread['votenum'],
					'votetotal' => $thread['votetotal'],
					'threadid'  => $thread['threadid'],
				);
			}
		}

		if (empty($firstthread))
		{
			$firstthread = $thread;
		}

		if ($thread['threadid'] == $vbulletin->GPC['destthreadid'])
		{
			$destthread = $thread;
		}
		else
		{
			switch($thread['visible'])
			{
				case '0':
					$counter['moderated'][] = $thread['threadid'];
					break;
				case '1':
					$counter['normal'][] = $thread['threadid'];
					break;
				case '2':
					$counter['deleted'][] = $thread['threadid'];
					break;
				default: // Invalid State
					continue;
			}
		}

		$threadarray["$thread[threadid]"] = $thread;
		$views += $thread['views'];
		$firstpostids[] = $thread['firstpostid'];
		$forumlist["$thread[forumid]"] = true;
	}

	if (empty($threadarray) OR empty($destthread))
	{
		eval(standard_error(fetch_error('you_did_not_select_any_valid_threads')));
	}

	if (count($threadarray) == 1)
	{
		eval(standard_error(fetch_error('not_much_would_be_accomplished_by_merging')));
	}

	@ignore_user_abort(true);

	$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
	$threadman->set_existing($destthread);
	$threadman->set('forumid', $destforuminfo['forumid']);
	$threadman->set('views', $views);

	// One poll given - duplicate it into dest thread unless the dest thread already has the poll
	if ($pollinfo !== false AND $destthread['threadid'] != $pollinfo['threadid'])
	{
		$threadman->set('pollid', $pollinfo['pollid']);
		$threadman->set('votenum', $pollinfo['votenum']);
		$threadman->set('votetotal', $pollinfo['votetotal']);

		// Remove poll from source thread so delete_thread doesn't remove it
		$pollthreadinfo = array('threadid' => $pollinfo['threadid']);
		$threadpollman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
		$threadpollman->set_existing($pollthreadinfo);
		$threadpollman->set('pollid', 0);
		$threadpollman->set('votenum', 0);
		$threadpollman->set('votetotal', 0);
		$threadpollman->save();
		unset($threadpollman);
	}

	$threadman->save();
	unset($threadman);

	// Merged thread contains moderated threads
	if (count($counter['moderated']))
	{
		// Change any moderation entries for hidden threads to point to hidden posts
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "moderation
			SET threadid = $destthread[threadid],
				type = 'reply'
			WHERE threadid IN(" . implode(',', $counter['moderated']) . ")
				AND type = 'thread'
		");
	}

	// Update any moderation entries for hidden posts to point to their new master
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "moderation
		SET threadid = $destthread[threadid]
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
			AND type = 'reply'
	");

	// Merged thread contains deleted threads
	if (count($counter['deleted']))
	{
		// Remove any deletion records for deleted threads as they are now undeleted
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "deletionlog
			WHERE primaryid IN(" . implode(',', $counter['deleted']) . ")
				AND type = 'thread'
		");
	}

	// Update parentids
	// Not certain about this -  seems that having a parentid of 0 is equal to having a parentid of the first postid so perhaps this is needless
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "post
		SET parentid = $firstthread[firstpostid]
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
			AND postid <> $firstthread[firstpostid]
			AND parentid = 0
	");

	// Update Redirects
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "thread
		SET pollid = $destthread[threadid]
		WHERE open = 10
			AND pollid IN(" . implode(',', array_keys($threadarray)) . ")
	");

	$userbyuserid = array();

	$hiddenthreads = array_merge($counter['deleted'], $counter['moderated']);

	// Source Dest  Visible Thread    Hidden Thread
	// Yes    Yes   +hidden           -visible
	// Yes    No    -visible          -visible
	// No     Yes   +visible,+hidden  ~
	// No     No    ~                 ~

	$posts = $db->query_read("
		SELECT userid, threadid
		FROM " . TABLE_PREFIX . "post
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
			AND visible = 1
			AND userid > 0
	");
	while ($post = $db->fetch_array($posts))
	{
		$set = 0;

		$foruminfo = fetch_foruminfo($threadarray["$post[threadid]"]['forumid']);

		// visible thread that merges moderated or deleted threads into a counting forum
		// increment post counts belonging to hidden/deleted threads
		if ($destthread['visible'] == 1 AND $destforuminfo['countposts'] AND in_array($post['threadid'], $hiddenthreads))
		{
			$set = 1;
		}

		// hidden thread that merges visible threads from a counting forum
		// OR visible thread that merges visible threads from a counting forum into a non counting forum
		// decrement post counts belonging to visible threads
		else if ($foruminfo['countposts'] AND (($destthread['visible'] != 1) OR ($destthread['visible'] == 1 AND !$destforuminfo['countposts'])) AND in_array($post['threadid'], $counter['normal']))
		{
			$set = -1;
		}

		// Visible thread that merges visible threads from a non counting forum into a counting forum
		// Increment post counts belonging to visible threads
		else if ($destthread['visible'] == 1 AND !$foruminfo['countposts'] AND $destforuminfo['countposts'] AND in_array($post['threadid'], $counter['normal']))
		{
			$set = 1;
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

	// Update first post in each thread as title information in relation to the sames words being in the first post may have changed now.
	foreach ($firstpostids AS $firstpostid)
	{
		delete_post_index($firstpostid);
		build_post_index($firstpostid, $destforuminfo);
	}

	// Update post threadids
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "post
		SET threadid = $destthread[threadid]
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
	");

	// Update subscribed threads
	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "subscribethread
		SET threadid = $destthread[threadid]
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
	");

	$users = array();
	$ratings = $db->query_read("
		SELECT threadrateid, threadid, userid, vote, ipaddress
		FROM " . TABLE_PREFIX . "threadrate
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
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
			WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
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
			$sql[] = "($destthread[threadid], $userid, $vote, '" . $db->escape_string($ipaddress) . "')";
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
		}
		unset($sql);
	}

	// Remove destthread from the threadarray now so we don't lose it.
	unset($threadarray["{$destthread['threadid']}"]);

	// We had multiple subscriptions so remove all but the main one now
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "subscribethread
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
	");

	// Update Moderator Log entries
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "moderatorlog
		SET threadid = $destthread[threadid]
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
	");

	// Remove source threads now
	foreach ($threadarray AS $threadid => $thread)
	{
		$foruminfo = fetch_foruminfo($thread['forumid']);
		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
		$threadman->set_existing($thread);
		if ($vbulletin->GPC['redirect'])
		{
			$threadman->set('open', 10);
			$threadman->set('pollid', $destthread['threadid']);
			$threadman->set('visible', 1);
			$threadman->save();
		}
		else
		{
			$threadman->delete($foruminfo['countposts'], true);
		}
		unset($threadman);
	}

	build_thread_counters($destthread['threadid']);
	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// Add log entries
	$threadinfo = array(
		'threadid'  => $destthread['threadid'],
		'foruminfo' => $destforuminfo['forumid'],
	);
	log_moderator_action($threadinfo, 'thread_merged_from_multiple_threads');

	if (empty($forumlist["$destforuminfo[forumid]"]))
	{
		build_forum_counters($destforuminfo['forumid']);
	}

	// Update canview status of thread subscriptions
	update_subscriptions(array('threadids' => array($destthread['threadid'])));

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_domergethread')) ? eval($hook) : false;

	$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$destthread[threadid]";
	eval(print_standard_redirect('redirect_inline_mergedthreads', true, true));
}

// ############################### start delete posts ###############################
if ($_REQUEST['do'] == 'deleteposts')
{
	$templatename = 'threadadmin_deleteposts';
	$show['removeposts'] = true;
	$show['deleteposts'] = true;
	$show['deleteoption'] = true;
	$checked = array('delete' => 'checked="checked"');

	// Validate posts
	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.visible, post.title,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN ($postids)
	");

	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}
		else if (!can_moderate($post['forumid'], 'canremoveposts'))
		{
			if (!can_moderate($post['forumid'], 'candeleteposts'))
			{
				eval(standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
			}
			else if (!$show['deleteposts'])
			{
				eval(standard_error(fetch_error('you_do_not_share_delete_permission')));
			}
			else
			{
				$show['removeposts'] = false;
				$show['deleteoption'] = false;
			}
		}
		else if (!can_moderate($post['forumid'], 'candeleteposts'))
		{
			if (!can_moderate($post['forumid'], 'canremoveposts'))
			{
				eval(standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
			}
			else if (!$show['removeposts'])
			{
				eval(standard_error(fetch_error('you_do_not_share_delete_permission')));
			}
			else
			{
				$checked = array('remove' => 'checked="checked"');
				$show['deleteposts'] = false;
				$show['deleteoption'] = false;
			}
		}

		$postarray["$post[postid]"] = true;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
	}

	if (empty($postarray))
	{
		eval(standard_error(fetch_error('no_applicable_posts_selected')));
	}

	$postcount = count($postarray);
	$threadcount = count($threadlist);
	$forumcount = count($forumlist);

	($hook = vBulletinHook::fetch_hook('inlinemod_deleteposts')) ? eval($hook) : false;

	// draw navbar
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
	}

	$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]"] = $threadinfo['title'];
	$navbits[''] = $vbphrase['delete_posts'];
	$template = 'threadadmin_deleteposts';

}

// ############################### start do delete posts ###############################
if ($_POST['do'] == 'dodeleteposts')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'deletetype'      => TYPE_UINT,	// 1 = soft delete post, 2 = physically remove.
		'keepattachments' => TYPE_BOOL,
		'deletereason'    => TYPE_STR
	));

	$physicaldel = iif($vbulletin->GPC['deletetype'] == 1, false, true);

	// Validate posts
	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.parentid, post.visible, post.title,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.firstpostid, thread.visible AS thread_visible
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN (" . implode(',', $postids) . ")
	");

	$deletethreads = array();
	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}
		else if (!can_moderate($post['forumid'], 'canremoveposts') AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}

		if (!can_moderate($post['forumid'], 'canremoveposts') AND $physicaldel)
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}
		else if (!can_moderate($post['forumid'], 'candeleteposts') AND !$physicaldel)
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_delete_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
	}

	if (empty($postarray))
	{
		eval(standard_error(fetch_error('no_applicable_posts_selected')));
	}

	$firstpost = false;
	$gotothread = true;
	foreach ($postarray AS $postid => $post)
	{
		$foruminfo = fetch_foruminfo($post['forumid']);

		if (!$firstpost)
		{
			$firstpost = $postid;
		}

		$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$postman->set_existing($post);
		$postman->delete($foruminfo['countposts'], $post['threadid'], $physicaldel, array(
			'userid' => $vbulletin->userinfo['userid'],
			'username' => $vbulletin->userinfo['username'],
			'reason' => $vbulletin->GPC['deletereason'],
			'keepattachments' => $vbulletin->GPC['keepattachments']
		));
		unset($postman);

		if ($vbulletin->GPC['threadid'] == $post['threadid'] AND $post['postid'] == $post['firstpostid'])
		{	// we've deleted the thread that we activated this action from so we can only return to the forum
			$gotothread = false;
		}
		else if ($post['postid'] == $postinfo['postid'] AND $physicaldel)
		{	// we came in via a post, which we have deleted so we have to go back to the thread
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . 't=' . $vbulletin->GPC['threadid'];
		}
	}

	foreach(array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}

	foreach(array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_dodeleteposts')) ? eval($hook) : false;

	if ($gotothread)
	{
		// Actually let's do nothing and redirect to where we were
	}
	else if ($vbulletin->GPC['forumid'])
	{	// redirect to the forum that we activated from since we hard deleted the thread
		$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . 'f=' . $vbulletin->GPC['forumid'];
	}
	else
	{
		// this really shouldn't happen...
		$vbulletin->url = $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'];
	}
	eval(print_standard_redirect('redirect_inline_deletedposts'));
}

// ############################### start do delete posts ###############################
if ($_POST['do'] == 'undeleteposts')
{

	// Validate posts
	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.parentid, post.visible, post.title, post.userid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.firstpostid, thread.visible AS thread_visible,
			forum.options AS forum_options
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING (forumid)
		WHERE postid IN ($postids)
			AND (post.visible = 2 OR (post.visible = 1 AND thread.visible = 2 AND post.postid = thread.firstpostid))
	");

	$deletethreads = array();
	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
	}

	foreach ($postarray AS $postid => $post)
	{
		$tinfo = array(
			'threadid' => $post['threadid'],
			'forumid' => $post['forumid'],
			'visible' => $post['thread_visible'],
			'firstpostid' => $post['firstpostid']
		);
		undelete_post($post['postid'], $post['forum_options'] & $vbulletin->bf_misc_forumoptions['countposts'], $post, $tinfo);
	}

	foreach (array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}

	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_undeleteposts')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_undeleteposts'));
}

// ############################### start do approve attachments ###############################
if ($_POST['do'] == 'approveattachments' OR $_POST['do'] == 'unapproveattachments')
{
	// validate posts
	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.visible, post.title, post.userid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible,
			thread.firstpostid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN ($postids)
	");

	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if ((!$post['thread_visible'] OR !$post['visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if (($post['thread_visible'] == 2 OR $post['visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}
		else if (!can_moderate($post['forumid'], 'canmoderateattachments'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_attachments', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
	}

	if (empty($postarray))
	{
		eval(standard_error(fetch_error('no_applicable_posts_selected')));
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "attachment
		SET visible = " . ($_POST['do'] == 'approveattachments' ? 1 : 0) . "
		WHERE postid IN (" . implode(',', array_keys($postarray)) . ")
	");

	// empty cookie
	setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');

	if ($_POST['do'] == 'approveattachments')
	{
		($hook = vBulletinHook::fetch_hook('inlinemod_approveattachments')) ? eval($hook) : false;
		eval(print_standard_redirect('redirect_inline_approvedattachments'));
	}
	else
	{
		($hook = vBulletinHook::fetch_hook('inlinemod_unapproveattachments')) ? eval($hook) : false;
		eval(print_standard_redirect('redirect_inline_unapprovedattachments'));
	}
}

// ############################### start do approve posts ###############################
if ($_POST['do'] == 'approveposts')
{
	// Validate posts
	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.visible, post.title, post.userid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible,
			thread.firstpostid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN ($postids)
			AND (post.visible = 0 OR (post.visible = 1 AND thread.visible = 0 AND post.postid = thread.firstpostid))
	");

	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if ($post['thread_visible'] == 2 AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
	}

	if (empty($postarray))
	{
		eval(standard_error(fetch_error('no_applicable_posts_selected')));
	}

	foreach ($postarray AS $postid => $post)
	{
		$foruminfo = fetch_foruminfo($post['forumid']);

		// Can't send $thread without considering that thread_visible may change if we approve the first post of a thread
		approve_post($postid, $foruminfo['countposts'], true, $post);
	}

	foreach (array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}
	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_approveposts')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_approvedposts'));
}

// ############################### start do unapprove posts ###############################
if ($_POST['do'] == 'unapproveposts')
{

	// Validate posts
	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.visible, post.title, post.userid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible,
			thread.firstpostid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN ($postids)
			AND (post.visible > 0 OR (post.visible = 1 AND thread.visible > 0 AND post.postid = thread.firstpostid))
	");

	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
	}

	if (empty($postarray))
	{
		eval(standard_error(fetch_error('no_applicable_posts_selected')));
	}

	foreach ($postarray AS $postid => $post)
	{
		$foruminfo = fetch_foruminfo($post['forumid']);

		// Can't send $thread without considering that thread_visible may change if we approve the first post of a thread
		unapprove_post($postid, $foruminfo['countposts'], true, $post);
	}

	foreach (array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}

	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_unapproveposts')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_unapprovedposts'));
}

// ############################### start do merge posts ###############################
if ($_POST['do'] == 'domergeposts')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'username'       => TYPE_NOHTML,
		'postid'         => TYPE_UINT,
		'title'          => TYPE_STR,
		'reason'         => TYPE_NOHTML,
		'wysiwyg'  		 => TYPE_BOOL,
		'message'        => TYPE_STR,
		'parseurl'       => TYPE_BOOL,
		'signature'      => TYPE_BOOL,
		'disablesmilies' => TYPE_BOOL,
	));

	// ### PREP INPUT ###
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$edit['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $foruminfo['allowhtml']);
	}
	else
	{
		$edit['message'] =& $vbulletin->GPC['message'];
	}

	preg_match('#^(\d+)\|(.+)$#', $vbulletin->GPC['username'], $matches);
	$userid = intval($matches[1]);
	$username = $matches[2];

	$attachtotal = 0;
	$destpost = array();

	$validname = false;
	$validdate = false;

	// Validate posts
	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.visible, post.title, post.username, post.dateline, post.attach,
			post.userid, thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible, thread.firstpostid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN (" . implode(',', $postids) . " )
		ORDER BY post.dateline
	");

	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($post['forumid'], 'canmanagethreads'))
		{
				eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}
		else if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}

		if ($post['username'] == $username AND $post['userid'] == $userid)
		{
			$validname = true;
		}

		$attachtotal += $post['attach'];

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;

		if ($post['postid'] == $vbulletin->GPC['postid'])
		{
			$destpost = $post;
		}
	}

	if (empty($postarray))
	{
		eval(standard_error(fetch_error('no_applicable_posts_selected')));
	}
	else if (count($postarray) == 1)
	{
		eval(standard_error(fetch_error('not_much_would_be_accomplished_by_merging')));
	}
	else if (empty($destpost))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
	}

	if (!$validname)
	{
		$userid = $destpost['userid'];
		$username = $destpost['username'];
	}

	if (!$userid AND $attachtotal)
	{
			eval(standard_error(fetch_error('guest_posts_may_not_contain_attachments')));
	}

	$edit['parseurl'] = ($vbulletin->GPC['parseurl'] AND $vbulletin->forumcache["$destpost[forumid]"]['allowbbcode']);
	$edit['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
	$edit['enablesmilies'] = $edit['allowsmilie'] = ($edit['disablesmilies']) ? 0 : 1;
	$edit['reason'] = fetch_censored_text($vbulletin->GPC['reason']);
	$edit['title'] = $vbulletin->GPC['title'];

	// Update First Post
	$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	$postman->set_existing($destpost);
	$postman->set_info('parseurl', $edit['parseurl']);
	$postman->set('pagetext', $edit['message']);
	$postman->set('userid', $userid, true, false); // Bypass verify
	$postman->set('username', $username, true, false); // Bypass verify
	$postman->set('dateline', $destpost['dateline']);
	$postman->set('attach', $attachtotal);
	$postman->set('title', $edit['title']);
	$postman->set('allowsmilie', $edit['enablesmilies']);

	($hook = vBulletinHook::fetch_hook('inlinemod_domergeposts_process')) ? eval($hook) : false;

	$postman->pre_save();

	if ($postman->errors)
	{
		$errors = $postman->errors;
	}

	if (sizeof($errors) > 0)
	{
		unset($postman);
		// ### POST HAS ERRORS ###
		$errorreview = construct_errors($errors);
		construct_checkboxes($edit);
		$previewpost = true;
		$postids = implode(',', $postids);
		$_REQUEST['do'] = 'mergeposts';
	}
	else
	{

		$postman->save();
		unset($postman);

		// Update Attachments to point to new owner
		if ($attachtotal)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "attachment
				SET postid = $destpost[postid],
					userid = $userid
				WHERE postid IN (" . implode(',', array_keys($postarray)) . ",$destpost[postid])
			");
		}

		if ($destpost['threadid'] != $threadinfo['threadid'])
		{	// retrieve threadinfo for the owner of the first post
			$threadinfo = fetch_threadinfo($destpost['threadid']);
			$foruminfo = fetch_foruminfo($threadinfo['forumid']);
		}

		if ($userid != $destpost['userid'] AND $threadinfo['visible'] == 1 AND $destpost['visible'] == 1 AND $foruminfo['countposts'])
		{
			if ($userid)
			{	// need to give this a user a post for now owning the merged post
				$user =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
				$userinfo = array('userid' => $userid);
				$user->set_existing($userinfo);
				$user->set('posts', 'posts + 1', false);
				$user->save();
				unset($user);
			}

			if ($destpost['userid'])
			{	// need to take a post from this user since they no longer own the merged post
				$user =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
				$userinfo = array('userid' => $destpost['userid']);
				$user->set_existing($userinfo);
				$user->set('posts', 'posts - 1', false);
				$user->save();
				unset($user);
			}

		}

		// Remove destpost from the postarray so as to not delete it!
		unset($postarray["$destpost[postid]"]);
		$deletedthreads = array();

		// Delete Posts that are not the firstpost in a thread
		foreach($postarray AS $postid => $post)
		{
			if (!empty($deletedthreads["$post[threadid]"]))
			{	// we already deleted the firstpost of this thread and hence all of its posts so no need to do anything else with this post
				continue;
			}

			$foruminfo = fetch_foruminfo($post['forumid']);

			if ($post['postid'] == $post['firstpostid'])
			{	// this is a firstpost so check if we can delete this thread or we need to give the thread a new firstpost before we call delete

				if ($getfirstpost = $db->query_first("
					SELECT postid
					FROM " . TABLE_PREFIX . "post
					WHERE threadid = $post[threadid]
						AND postid NOT IN (" . implode(',', array_keys($postarray)) . ")
					ORDER BY dateline
					LIMIT 1
				"))
				{

					$db->query_write("
						UPDATE " . TABLE_PREFIX . "thread
						SET firstpostid = $getfirstpost[postid]
						WHERE threadid = $post[threadid]
					");

					$post['firstpostid'] = $getfirstpost['postid'];
					// Also update the threadcache
					$threadcache["$post[threadid]"]['firstpostid'] = $getfirstpost['postid'];
				}
				else
				{ // there are no posts left or we plan to delete them all so mark this thread as deleted now
					$deletedthreads["$post[threadid]"] = true;
				}
			}

			$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$postman->set_info('skip_moderator_log', true);
			$postman->set_existing($post);
			$postman->delete($foruminfo['countposts'], $post['threadid'], true, NULL, false);
			unset($postman);
		}

		$reason = fetch_censored_text($vbulletin->GPC['reason']);

		// Delete user's previous edit if we don't save edits for this group and they didn't give a reason
		if (!$edit['reason'] AND !($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['showeditedby']))
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "editlog
				WHERE postid = $destpost[postid]
			");
		}
		else if ((($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['showeditedby']) AND $destpost['dateline'] < (TIMENOW - ($vbulletin->options['noeditedbytime'] * 60))) OR !empty($edit['reason']))
		{
			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "editlog (postid, userid, username, dateline, reason)
				VALUES ($destpost[postid], " . $vbulletin->userinfo['userid'] . ", '" . $db->escape_string($vbulletin->userinfo['username']) . "', " . TIMENOW . ", '" . $db->escape_string($edit['reason']) . "')
			");
		}

		// Need to update thread
		if ($destpost['postid'] == $threadinfo['firstpostid'] AND $vbulletin->GPC['title'] != '' AND ($destpost['dateline'] + $vbulletin->options['editthreadtitlelimit'] * 60) > TIMENOW)
		{
			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$threadman->set_existing($threadinfo);
			$threadman->set_info('skip_first_post_update', true);
			$threadman->set('title', $vbulletin->GPC['title']);
			$threadman->save();
		}

		$threadinfo['postid'] = $destpost['postid'];
		log_moderator_action($threadinfo, 'post_merged_from_multiple_posts');

		foreach(array_keys($threadlist) AS $threadid)
		{
			build_thread_counters($threadid);
		}

		foreach(array_keys($forumlist) AS $forumid)
		{
			build_forum_counters($forumid);
		}

		// empty cookie
		setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');

		($hook = vBulletinHook::fetch_hook('inlinemod_domergeposts_complete')) ? eval($hook) : false;

		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$destpost[postid]#post$destpost[postid]";
		eval(print_standard_redirect('redirect_inline_mergedposts'));
	}
}

// ############################### start merge posts ###############################
if ($_REQUEST['do'] == 'mergeposts')
{

	if ($previewpost)
	{
		$checked['parseurl'] = ($edit['parseurl']) ? 'checked="checked"' : '';
		$checked['disablesmilies'] = ($edit['disablesmilies']) ? 'checked="checked"' : '';
	}
	else
	{
		$checked['parseurl'] = 'checked="checked"';
	}

	$userselect = array();
	$postselect = array();
	$pagetext = '';

	// Validate posts
	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.visible, post.title, post.username, post.dateline, post.pagetext,
			post.userid, thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN ($postids)
		ORDER BY post.dateline
	");

	$counter = 1;
	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($post['forumid'], 'canmanagethreads'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}
		else if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}

		$userselect["$post[userid]|$post[username]"] = $post['username']; // Allow guest usernames so key off username
		$postselect["$post[postid]"] = construct_phrase($vbphrase['x_y_by_z'], $counter, vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $post['dateline']), $post['username']);

		if (empty($titlebit))
		{
			$titlebit = $post['thread_title'];
		}

		$js_titles .= "threadtitles[$post[postid]] = '" . addslashes_js($post['thread_title']) . "';\n";

		($hook = vBulletinHook::fetch_hook('inlinemod_mergeposts_post')) ? eval($hook) : false;

		$pagetext .= (!empty($pagetext) ? "\n\n" : "") . $post['pagetext'];

		$postarray["$post[postid]"] = true;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
		$counter++;
	}

	if (empty($postarray))
	{
		eval(standard_error(fetch_error('no_applicable_posts_selected')));
	}
	else if (count($postarray) == 1)
	{
		eval(standard_error(fetch_error('not_much_would_be_accomplished_by_merging')));
	}

	$postcount = count($postarray);
	$threadcount = count($threadlist);
	$forumcount = count($forumlist);

	if ($previewpost)
	{
		$pagetext = htmlspecialchars_uni($edit['message']);
	}
	else
	{
		$pagetext = htmlspecialchars_uni($pagetext);
	}
	$editorid = construct_edit_toolbar($pagetext, 0, $foruminfo['forumid'], iif($foruminfo['allowsmilies'], 1, 0), 1);

	$usernamebit = '';
	if (count($userselect) > 1)
	{
		$guests = array();
		uasort($userselect, 'strnatcasecmp'); // alphabetically sort usernames
		foreach ($userselect AS $optionvalue => $optiontitle)
		{
			preg_match('#^(\d+)\|(.+)$#', $optionvalue, $matches);
			if (!intval($matches[1]))
			{
				$guests[] = $optiontitle;
			}
			else
			{
				$optionselected = ($optionvalue == "$userid|$username") ? "selected='selected'" : "";
				eval('$usernamebit .= "' . fetch_template('option') . '";');
			}
		}

		if (!empty($guests))
		{
			$usernamebit .= "<optgroup label=\"$vbphrase[guests]\">\n";
			foreach ($guests AS $optiontitle)
			{
				$optionvalue = "0|$username";
				$optionselected = ($optionvalue == "$userid|$username") ? "selected='selected'" : "";
				eval('$usernamebit .= "' . fetch_template('option') . '";');
			}
			$usernamebit .= "</optgroup>\n";
		}
		$show['userchoice'] = true;
	}

	$postlistbit = '';

	foreach ($postselect AS $optionvalue => $optiontitle)
	{
		$optionselected = ($optionvalue == $vbulletin->GPC['postid']) ? "selected='selected'" : "";
		eval('$postlistbit .= "' . fetch_template('option') . '";');
	}


	($hook = vBulletinHook::fetch_hook('inlinemod_mergeposts_complete')) ? eval($hook) : false;

	// draw navbar
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
	}

	$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]"] = $threadinfo['title'];
	$navbits[''] = $vbphrase['merge_posts'];
	$template = 'threadadmin_mergeposts';
}

// ############################### start move posts ###############################
if ($_REQUEST['do'] == 'moveposts')
{
	// Validate posts
	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.visible, post.title,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN ($postids)
		ORDER BY post.dateline
	");

	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
			!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
			)
		{
			continue;
		}

		if (!can_moderate($post['forumid'], 'canmanagethreads'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}
		else if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
	}

	if (empty($postarray))
	{
		eval(standard_error(fetch_error('no_applicable_posts_selected')));
	}

	$postcount = count($postarray);
	$threadcount = count($threadlist);
	$forumcount = count($forumlist);

	if ($postcount == 1)
	{
		$post = array_pop($postarray);
	}
	else
	{
		$post = array('title' => '');
	}

	$curforumid = $foruminfo['forumid'];
	$moveforumbits = construct_move_forums_options();

	($hook = vBulletinHook::fetch_hook('inlinemod_moveposts')) ? eval($hook) : false;

	// draw navbar
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
	}

	$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]"] = $threadinfo['title'];
	$navbits[''] = $vbphrase['move_posts'];
	$template = 'threadadmin_moveposts';
}

// ############################### start do move posts ###############################
if ($_POST['do'] == 'domoveposts')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'type'           => TYPE_UINT,
		'title'          => TYPE_NOHTML,
		'destforumid'    => TYPE_UINT,
		'mergethreadurl' => TYPE_STR
	));

	if ($vbulletin->GPC['type'] == 0)
	{	// Move to new thread
		if (empty($vbulletin->GPC['title']))
		{
			eval(standard_error(fetch_error('notitle')));
		}

		// check whether dest can contain posts
		$destforumid = verify_id('forum', $vbulletin->GPC['destforumid']);
		$destforuminfo = fetch_foruminfo($destforumid);
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
	else
	{
		// Validate destination thread
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
			$destthreadid = intval($matches[2]);
		}
		else if (preg_match('#(postid|p)=([0-9]+)#', $vbulletin->GPC['mergethreadurl'], $matches))
		{
			$destpostid = verify_id('post', $matches[2], 0);
			if ($destpostid == 0)
			{
				// do invalid url
				eval(standard_error(fetch_error('mergebadurl')));
			}

			$postinfo = fetch_postinfo($destpostid);
			$destthreadid = $postinfo['threadid'];
		}
		else
		{
			eval(standard_error(fetch_error('mergebadurl')));
		}

		$destthreadid = verify_id('thread', $destthreadid);
		$destthreadinfo = fetch_threadinfo($destthreadid);
		$destforuminfo = fetch_foruminfo($destthreadinfo['forumid']);

		if (($destthreadinfo['isdeleted'] AND !can_moderate($destthreadinfo['forumid'], 'candeleteposts')) OR (!$destthreadinfo['visible'] AND !can_moderate($destthreadinfo['forumid'], 'canmoderateposts')))
		{
			if (can_moderate($destthreadinfo['forumid']))
			{
				print_no_permission();
			}
			else
			{
				eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
			}
		}
	}

	$firstpost = array();
	$parentassoc = array();
	$userbyuserid = array();

	$posts = $db->query_read("
		SELECT post.postid, post.threadid, post.visible, post.title, post.username, post.dateline, post.parentid, post.userid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible, thread.firstpostid,
			thread.sticky, thread.open, thread.iconid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		WHERE postid IN (" . implode(',', $postids) . ")
		ORDER BY post.dateline

	");
	while ($post = $db->fetch_array($posts))
	{

		if (!can_moderate($post['forumid'], 'canmanagethreads'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}
		else if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts')));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts', $post['title'], $post['thread_title'], $vbulletin->forumcache["$post[forumid]"]['title'])));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($destforuminfo['forumid'], 'candeleteposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_threads_and_posts_in_destination_forum')));
		}
		else if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($destforuminfo['forumid'], 'canmoderateposts'))
		{
			eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_threads_and_posts_in_destination_forum')));
		}

		// Ignore posts that are already in the destination thread
		if ($post['threadid'] == $destthreadinfo['threadid'])
		{
			continue;
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;

		if (empty($firstpost))
		{
			$firstpost = $post;
		}

		$parentassoc["$post[postid]"] = $post['parentid'];
	}

	if (empty($postarray))
	{
		eval(standard_error(fetch_error('no_applicable_posts_selected')));
	}

	if ($vbulletin->GPC['type'] == 0)
	{	// Create a new thread
		$destthreadinfo = array(
			'open'         => $firstpost['open'],
			'icondid'      => $firstpost['iconid'],
			'visible'      => $firstpost['thread_visible'],
			'forumid'      => $destforuminfo['forumid'],
			'title'        => $vbulletin->GPC['title'],
			'views'        => 0,
			'dateline'     => TIMENOW,
			'postuserid'   => $firstpost['userid'],
			'postusername' => $firstpost['username'],
			'sticky'       => $firstpost['sticky']
		);

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threadman->setr('forumid', $destthreadinfo['forumid'], true, false);
		$threadman->setr('title', $destthreadinfo['title'], true, false);
		$threadman->setr('iconid', $destthreadinfo['iconid'], true, false);
		$threadman->setr('open', $destthreadinfo['open'], true, false);
		$threadman->setr('views', $destthreadinfo['views']);
		$threadman->setr('visible', $destthreadinfo['visible'], true, false);
		// Rest of thread field will be populated by the build_thread_counters() call
		$destthreadinfo['threadid'] = $threadman->save();
		unset($threadman);
	}

	if ($firstpost['dateline'] <= $destthreadinfo['dateline'])
	{	// destination thread has a new first post (this will always be true for $type == 0)
		if ($firstpost['visible'] != 1)
		{	// Unhide the new first post since all first posts are visible
			$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$postman->set_existing($firstpost);
			$postman->set('visible', 1);
			$postman->save();
			unset($postman);

			// we need to give this user back his post if this is a visible thread in a counting forum
			if ($destthreadinfo['visible'] == 1 AND $destforuminfo['countposts'])
			{
				$userman =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
				$userman->set_existing($firstpost);
				$userman->set('posts', 'posts + 1', false);
				$userman->save();
				unset($userman);
			}

			if ($firstpost['firstpostid'] != $firstpost['postid'])
			{	// We didn't take the thread's first post so remove some records
				if (!$firstpost['visible'])
				{	// remove new first post's old moderation record
					$db->query_write("
						DELETE FROM " . TABLE_PREFIX . "moderation
						WHERE postid = $firstpost[postid]
							AND type = 'reply'
					");
				}
				else
				{	// remove new first post's old deletionlog record
					$db->query_write("
						DELETE FROM " . TABLE_PREFIX . "deletionlog
						WHERE primaryid = $firstpost[postid]
							AND type = 'post'
					");
				}
			}
		}

		if (!$destthreadinfo['visible'])
		{	// Moderated thread so overwrite moderation record
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation
				WHERE threadid = $destthreadinfo[threadid]
					AND type = 'thread'
			");

			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "moderation
				(threadid, postid, type)
				VALUES
				($destthreadinfo[threadid], $firstpost[postid], 'thread')
			");
		}
		else if ($destthreadinfo['visible'] == 2)
		{	// Deleted thread so overwrite the deletionlog entry
			// $type = 0, this inserts a new record
			// $type = 1, this overwrites the record with the same info
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "deletionlog
				(primaryid, type, userid, username)
				VALUES
				($destthreadinfo[threadid], 'thread', " . $vbulletin->userinfo['userid'] . ", '" . $vbulletin->userinfo['username'] . "')
			");
		}
	}

	// Move posts to their new thread
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "post
		SET threadid = $destthreadinfo[threadid]
		WHERE postid IN (" . implode(',', array_keys($postarray)) . ")
	");

	$userbyuserid = array();
	foreach ($postarray AS $postid => $post)
	{
		if ($post['userid'] AND $post['visible'] == 1)
		{
			$foruminfo = fetch_foruminfo($post['forumid']);

			if ($foruminfo['countposts'] AND $post['thread_visible'] == 1 AND (!$destforuminfo['countposts'] OR ($destforuminfo['countposts'] AND $destthreadinfo['visible'] != 1)))
			{	// Take away a post
				if (!isset($userbyuserid["$post[userid]"]))
				{
					$userbyuserid["$post[userid]"] = -1;
				}
				else
				{
					$userbyuserid["$post[userid]"]--;
				}
			}
			else if ($destforuminfo['countposts'] AND $destthreadinfo['visible'] == 1 AND (!$foruminfo['countposts'] OR ($foruminfo['countposts'] AND $post['thread_visible'] != 1)))
			{	// Add a post
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

		// Let's deal with the residual thread(s) now
		if ($post['postid'] == $post['firstpostid'])
		{	// we moved a first post so thread must be tinkered with

			// Do we have any posts left in this thread?
			if ($firstleftpost = $db->query_first("
				SELECT postid, visible, threadid, title, pagetext
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = $post[threadid]
				ORDER BY dateline
				LIMIT 1
			"))
			{
				if (!$post['thread_visible'])
				{	// Moderated thread
					// Update this thread's moderation record to reflect the new first post
					$db->query_write("
						UPDATE " . TABLE_PREFIX . "moderation
						SET postid = $firstleftpost[postid]
						WHERE threadid = $post[threadid]
							AND type = 'thread'
					");
				}

				if (!$firstleftpost['visible'])
				{	// new first post is moderated so we must remove it's moderation record
					$db->query_write("
						DELETE FROM " . TABLE_PREFIX . "moderation
						WHERE postid = $firstleftpost[postid]
							AND type = 'reply'
					");
				}
				else if ($firstleftpost['visible'] == 2)
				{	// new first post is deleted so we must removed it's deletionlog record
					$db->query_write("
						DELETE FROM " . TABLE_PREFIX . "deletionlog
						WHERE primaryid = $firstleftpost[postid]
							AND type = 'post'
					");
				}

				if ($firstleftpost['visible'] != 1)
				{	// post is not visible so we need to set it visible since first posts are always visible
					$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
					$postman->set_existing($firstleftpost);
					$postman->set('visible', 1);
					$postman->save();
					unset($postman);

					$foruminfo = fetch_foruminfo($post['forumid']);
					// we need to give this user back his post if this is a visible thread in a counting forum
					if ($post['thread_visible'] == 1 AND $foruminfo['countposts'])
					{
						$userman =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
						$userman->set_existing($firstleftpost);
						$userman->set('posts', 'posts + 1', false);
						$userman->save();
						unset($userman);
					}
				}

				// Update first post in each thread as title information in relation to the sames words being in the first post may have changed now.
				delete_post_index($firstleftpost['postid'], $firstleftpost['title'], $firstleftpost['pagetext']);
				build_post_index($firstleftpost['postid'] , $foruminfo);
			}
			else	// we moved all of the thread :eek: delete the empty thread!
			{
				$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($post);
				$threadman->delete(false, true, NULL, false);
				unset($threadman);
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
		foreach ($userbypostcount AS $postcount => $userids)
		{
			$casesql .= " WHEN userid IN (0$userids) THEN $postcount";
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

	$parentupdate = array();

	// update parentids
	$nosplittop = 0;
	$splittop = 0;
	$allpostids = '';
	foreach ($parentassoc AS $postid => $parentid)
	{
		if (empty($postarray["$postid"]) AND !empty($postarray["$parentid"]))
		{
			// this post wasn't moved, but it's parent was, so we need to walk up the chain to find the next post that
			// wasn't moved and make this post a child of that one
			do
			{
				$parentid = $parentassoc["$parentid"];
			}
			while (!empty($postarray["$parentid"]));

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
		else if (!empty($postarray["$postid"]) AND empty($postarray["$parentid"]))
		{
			// this post was split, but it's parent wasn't
			do
			{
				$parentid = $parentassoc["$parentid"];
			}
			while (empty($postarray["$parentid"]) AND $parentid != 0); // $parentid check to prevent infinite loop

			//if ($parentid !== NULL) FIXED BUG 166
			//{
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
			//}
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

	$getfirstpost = $db->query_first("
		SELECT postid, title, pagetext
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = $destthreadinfo[threadid]
		ORDER BY dateline
		LIMIT 1
	");
	delete_post_index($getfirstpost['postid'], $getfirstpost['title'], $getfirstpost['pagetext']);
	build_post_index($getfirstpost['postid'] , $destforuminfo);

	foreach (array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}

	if (empty($threadlist["$destthreadinfo[threadid]"]))
	{
		build_thread_counters($destthreadinfo['threadid']);
	}

	foreach(array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	if (empty($forumlist["$destforuminfo[forumid]"]))
	{
		build_forum_counters($destforuminfo['forumid']);
	}

	log_moderator_action($threadinfo, 'thread_split_to_x', $destthreadinfo['threadid']);

	// empty cookie
	setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_domoveposts')) ? eval($hook) : false;

	$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$destthreadinfo[threadid]";
	eval(print_standard_redirect('redirect_inline_movedposts'));
}

$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');

($hook = vBulletinHook::fetch_hook('inlinemod_complete')) ? eval($hook) : false;

$url =& $vbulletin->url;
// spit out the final HTML if we have got this far
eval('$HTML = "' . fetch_template($template) . '";');
eval('print_output("' . fetch_template('THREADADMIN') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 16479 $
|| ####################################################################
\*======================================================================*/
?>
