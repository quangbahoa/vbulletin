<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'moderation');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user', 'forumdisplay', 'inlinemod');

if ($_REQUEST['do'] == 'viewpics')
{
	$phrasegroups[] = 'album';
}

// get special data templates from the datastore
$specialtemplates = array(
	'iconcache',
	'noavatarperms'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'USERCP_SHELL',
	'usercp_nav_folderbit',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'viewthreads' => array(
		'forumdisplay_sortarrow',
		'moderation_threads',
		'threadadmin_imod_menu_thread',
		'threadbit',
		'threadbit_deleted',
	),
	'viewposts' => array(
		'moderation_posts',
		'search_results_postbit',
		'threadadmin_imod_menu_post',
	),
	'viewvms' => array(
		'moderation_filter',
		'moderation_visitormessages',
		'memberinfo_visitormessage',
		'memberinfo_visitormessage_deleted',
		'memberinfo_visitormessage_ignored',
		'memberinfo_css',
	),
	'viewgms' => array(
		'moderation_filter',
		'moderation_groupmessages',
		'memberinfo_css',
		'socialgroups_message',
		'socialgroups_message_deleted',
		'socialgroups_message_ignored',
	),
	'viewpcs' => array(
		'moderation_filter',
		'moderation_picturecomments',
		'picturecomment_css',
		'picturecomment_message_moderatedview',
	),
	'viewpics' => array(
		'moderation_filter',
		'moderation_picturebit',
		'moderation_pictures',
		'picturecomment_css',
	),
);

$actiontemplates['none'] =& $actiontemplates['viewthreads'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_forumlist.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'viewthreads';
}

cache_moderators($vbulletin->userinfo['userid']);

// start the navbits breadcrumb
$navbits = array('usercp.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['user_control_panel']);

($hook = vBulletinHook::fetch_hook('moderation_start')) ? eval($hook) : false;


// ############################### start view threads ###############################
if ($_REQUEST['do'] == 'viewthreads')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'daysprune'  => TYPE_INT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML,
		'type'       => TYPE_NOHTML,
		'forumid'    => TYPE_UINT,
	));

	($hook = vBulletinHook::fetch_hook('moderation_threads_start')) ? eval($hook) : false;

	// Values that are reused in templates
	$sortfield  =& $vbulletin->GPC['sortfield'];
	$perpage    =& $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$daysprune  =& $vbulletin->GPC['daysprune'];
	$type       =& $vbulletin->GPC['type'];
	$forumid    =& $vbulletin->GPC['forumid'];

	if ($type == 'deleted')
	{
		$table = 'deletionlog';
		$permission = '';
		if (!can_moderate())
		{
			print_no_permission();
		}
		$threadselect = ", deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason";
		$threadjoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND deletionlog.type = 'thread')";
		$threadfrom = "FROM " . TABLE_PREFIX . "deletionlog AS deletionlog
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (deletionlog.primaryid = thread.threadid)";
		$show['deleted'] = true;
	}
	else
	{
		$type = 'moderated';
		$table = 'moderation';
		$permission = 'canmoderateposts';
		if (!can_moderate(0, 'canmoderateposts'))
		{
			print_no_permission();
		}
		$threadselect = '';
		$threadjoin = "LEFT JOIN " . TABLE_PREFIX . "moderation AS moderation ON(thread.threadid = moderation.primaryid AND moderation.type = 'thread')";
		$threadfrom = "FROM " . TABLE_PREFIX . "moderation AS moderation
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (moderation.primaryid = thread.threadid)";
	}

	if ($vbulletin->options['threadmarking'])
	{
		cache_ordered_forums(1);
	}

	$modforums = array();
	if ($forumid)
	{
		require_once(DIR . '/includes/functions_misc.php');
		$forums = fetch_child_forums($forumid, 'ARRAY');
		$forums[] = $forumid;
		$forums = array_flip($forums);
	}
	else
	{
		$forums = $vbulletin->forumcache;
	}

	foreach ($forums AS $mforumid => $null)
	{
		$forumperms = $vbulletin->userinfo['forumpermissions']["$mforumid"];
		if (can_moderate($mforumid, $permission)
			AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canview']
			AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']
		)
		{
			$modforums[] = $mforumid;
		}
	}

	if (empty($modforums))
	{
		print_no_permission();
	}

	$forumjumpbits =  construct_mod_forum_jump(-1, $forumid, '', $permission);

	$show['inlinemod'] = true;
	$url = SCRIPTPATH;
	if ($show['popups'])
	{
		eval('$threadadmin_imod_menu = "' . fetch_template('threadadmin_imod_menu_thread') . '";');
	}
	else
	{
		$threadadmin_imod_menu = '';
	}

	if (!$daysprune)
	{
		$daysprune = ($vbulletin->userinfo['daysprune']) ? $vbulletin->userinfo['daysprune'] : 30;
	}
	$datecut = ($daysprune != -1) ? "AND $table.dateline >= " . (TIMENOW - ($daysprune * 86400)) : '';


	// complete form fields on page
	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
	$daysprunesel = array($daysprunesel => 'selected="selected"');

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$vbulletin->GPC['sortorder'] = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	$sqlsortfield2 = '';

	switch ($sortfield)
	{
		case 'title':
		case 'lastpost':
		case 'replycount':
		case 'views':
		case 'postusername':
			$sqlsortfield = 'thread.' . $sortfield;
			break;
		case 'voteavg':
			$sqlsortfield = 'voteavg';
			$sqlsortfield2 = 'votenum';
			break;
		default:
			$handled = false;
			($hook = vBulletinHook::fetch_hook('moderation_threads_sort')) ? eval($hook) : false;
			if (!$handled)
			{
				$sqlsortfield = 'thread.lastpost';
				$sortfield = 'lastpost';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('moderation_threadsquery_threadscount')) ? eval($hook) : false;

	$threadscount = $db->query_first_slave("
		SELECT COUNT(*) AS threads
		$hook_query_fields
		$threadfrom
		$hook_query_joins
		WHERE type = 'thread'
			AND forumid IN (" . implode(', ', $modforums) . ")
			$datecut
			$hook_query_where
	");
	$totalthreads = $threadscount['threads'];

	// set defaults
	sanitize_pageresults($totalthreads, $pagenumber, $perpage, 200, $vbulletin->options['maxthreads']);

	// display threads
	$limitlower = ($pagenumber - 1) * $perpage;
	$limitupper = ($pagenumber) * $perpage;

	if ($limitupper > $totalthreads)
	{
		$limitupper = $totalthreads;
		if ($limitlower > $totalthreads)
		{
			$limitlower = ($totalthreads - $perpage) - 1;
		}
	}
	if ($limitlower < 0)
	{
		$limitlower = 0;
	}

	$colspan = 1;

	if ($totalthreads)
	{
		$lastread = array();
		$threadids = array();
		$show['threadicons'] = false;
		$colspan = 6;

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('moderation_threadsquery_threadid')) ? eval($hook) : false;

		// Fetch ids
		$threads = $db->query_read_slave("
			SELECT thread.threadid, thread.forumid,
				IF(votenum >= " . $vbulletin->options['showvotes'] . ", votenum, 0) AS votenum,
				IF(votenum >= " . $vbulletin->options['showvotes'] . " AND votenum > 0, votetotal / votenum, 0) AS voteavg
				$hook_query_fields
				$threadfrom
				$hook_query_joins
			WHERE type = 'thread'
				AND forumid IN (" . implode(', ', $modforums) . ")
				$datecut
				$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder" . (!empty($sqlsortfield2) ? ", $sqlsortfield2 $sqlsortorder" : '') . "
			LIMIT $limitlower, $perpage
		");
		while ($thread = $db->fetch_array($threads))
		{
			$threadids[] = $thread['threadid'];
			// get last read info for each thread
			if (empty($lastread["$thread[forumid]"]))
			{
				if ($vbulletin->options['threadmarking'])
				{
					$lastread["$thread[forumid]"] = max($vbulletin->forumcache["$thread[forumid]"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
				}
				else
				{
					$lastread["$thread[forumid]"] = max(intval(fetch_bbarray_cookie('forum_view', $thread['forumid'])), $vbulletin->userinfo['lastvisit']);
				}
			}
			if (!$show['threadicons'] AND ($vbulletin->forumcache["$thread[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons']))
			{
				$show['threadicons'] = true;
				$colspan++;
			}
		}
		$limitlower++;

		// get thread preview?
		if ($vbulletin->options['threadpreview'] > 0 AND $type == 'moderated')
		{
			$previewfield = 'post.pagetext AS preview,';
			$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
		}
		else
		{
			$previewfield = '';
			$previewjoin = '';
		}

		$threadbits = '';
		$pagenav = '';
		$counter = 0;
		$toread = 0;

		$vbulletin->options['showvotes'] = intval($vbulletin->options['showvotes']);

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('moderation_threadsquery')) ? eval($hook) : false;

		$threads = $db->query_read_slave("
			SELECT
				IF(votenum >= " . $vbulletin->options['showvotes'] . ", votenum, 0) AS votenum,
				IF(votenum >= " . $vbulletin->options['showvotes'] . " AND votenum > 0, votetotal / votenum, 0) AS voteavg,
				$previewfield thread.threadid, thread.title AS threadtitle, lastpost, forumid, pollid, open, replycount, postusername,
				postuserid, lastposter, lastpostid, thread.dateline, views, thread.iconid AS threadiconid, notes, thread.visible, thread.attach,
				thread.prefixid, thread.taglist, hiddencount, deletedcount
				$threadselect
				" . ($vbulletin->options['threadmarking'] ? ", threadread.readtime AS threadread" : '') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "thread AS thread
			$threadjoin
			$previewjoin
			" . ($vbulletin->options['threadmarking'] ? " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")" : '') . "
			$hook_query_joins
			WHERE thread.threadid IN (" . implode(', ', $threadids) . ")
				$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder" . (!empty($sqlsortfield2) ? ", $sqlsortfield2 $sqlsortorder" : '') . "
		");
		unset($sqlsortfield, $sqlsortorder, $sqlsortfield2);

		require_once(DIR . '/includes/functions_forumdisplay.php');

		// Get Dot Threads
		$dotthreads = fetch_dot_threads_array(implode(', ', $threadids));
		if ($vbulletin->options['showdots'] AND $vbulletin->userinfo['userid'])
		{
			$show['dotthreads'] = true;
		}
		else
		{
			$show['dotthreads'] = false;
		}

		if ($vbulletin->options['threadpreview'] AND $vbulletin->userinfo['ignorelist'])
		{
			// Get Buddy List
			$buddy = array();
			if (trim($vbulletin->userinfo['buddylist']))
			{
				$buddylist = preg_split('/( )+/', trim($vbulletin->userinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
					foreach ($buddylist AS $buddyuserid)
				{
					$buddy["$buddyuserid"] = 1;
				}
			}
			DEVDEBUG('buddies: ' . implode(', ', array_keys($buddy)));
			// Get Ignore Users
			$ignore = array();
			if (trim($vbulletin->userinfo['ignorelist']))
			{
				$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
				foreach ($ignorelist AS $ignoreuserid)
				{
					if (!$buddy["$ignoreuserid"])
					{
						$ignore["$ignoreuserid"] = 1;
					}
				}
			}
			DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));
		}

		$foruminfo['allowratings'] = true;
		$show['threadratings'] = true;
		$show['threadrating'] = true;

		while ($thread = $db->fetch_array($threads))
		{
			// unset the thread preview if it can't be seen
			$forumperms = fetch_permissions($thread['forumid']);
			if ($vbulletin->options['threadpreview'] > 0 AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
			{
				$thread['preview'] = '';
			}

			$threadid = $thread['threadid'];
			// build thread data
			$thread = process_thread_array($thread, $lastread["$thread[forumid]"]);

			($hook = vBulletinHook::fetch_hook('threadbit_display')) ? eval($hook) : false;

			// Soft Deleted Thread
			if ($thread['visible'] == 2)
			{
				$thread['deletedcount']++;
				$show['threadtitle'] = true;
				$show['deletereason'] = (!empty($thread['del_reason'])) ?  true : false;
				$show['viewthread'] = true;
				$show['managethread'] = (can_moderate($thread['forumid'], 'candeleteposts') OR can_moderate($thread['forumid'], 'canremoveposts')) ? true : false;
				$show['moderated'] = ($thread['hiddencount'] > 0 AND can_moderate($thread['forumid'], 'canmoderateposts')) ? true : false;
				$show['deletedthread'] = true;
				eval('$threadbits .= "' . fetch_template('threadbit_deleted') . '";');
			}
			else
			{
				if (!$thread['visible'])
				{
					$thread['hiddencount']++;
				}
				$show['moderated'] = ($thread['hiddencount'] > 0 AND can_moderate($thread['forumid'], 'canmoderateposts')) ? true : false;
				$show['deletedthread'] = ($thread['deletedcount'] > 0) ? true : false;
				eval('$threadbits .= "' . fetch_template('threadbit') . '";');
			}

		}

		$db->free_result($threads);
		unset($threadids);
		$sorturl = 'moderation.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewthreads&amp;type=" . $type . "&amp;pp=$perpage&amp;daysprune=$daysprune&amp;forumid=$forumid";
		$pagenav = construct_page_nav($pagenumber, $perpage, $totalthreads, $sorturl . "&amp;sort=$sortfield" . iif(!empty($vbulletin->GPC['sortorder']), "&amp;order=" . $vbulletin->GPC['sortorder']));
		$oppositesort = iif($vbulletin->GPC['sortorder'] == 'asc', 'desc', 'asc');

		eval('$sortarrow[' . $sortfield . '] = "' . fetch_template('forumdisplay_sortarrow') . '";');

		$show['havethreads'] = true;
	}
	else
	{
		$totalthreads = 0;
		$show['havethreads'] = false;
	}

	if ($type == 'moderated')
	{
		$show['delete'] = (can_moderate(0, 'canremoveposts') OR can_moderate(0, 'candeleteposts'));
	}
	else
	{
		$show['delete'] = can_moderate(0, 'canremoveposts');
	}
	$show['undelete'] = can_moderate(0, 'candeleteposts');

	$navbits[''] = $vbphrase['moderation'];
	$navbits = construct_navbits($navbits);

	// build the cp nav

	construct_usercp_nav($type . 'threads');

	($hook = vBulletinHook::fetch_hook('moderation_threads_complete')) ? eval($hook) : false;

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('moderation_threads') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

// ############################### start view posts ###############################
if ($_REQUEST['do'] == 'viewposts')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'daysprune'  => TYPE_INT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML,
		'type'       => TYPE_NOHTML,
	));

	($hook = vBulletinHook::fetch_hook('moderation_posts_start')) ? eval($hook) : false;

	// Values that are reused in templates
	$sortfield  =& $vbulletin->GPC['sortfield'];
	$perpage    =& $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$daysprune  =& $vbulletin->GPC['daysprune'];
	$type       =& $vbulletin->GPC['type'];

	if ($type == 'deleted')
	{
		$table = 'deletionlog';
		$permission = '';
		if (!can_moderate())
		{
			print_no_permission();
		}
		$postselect = ",pdeletionlog.userid AS pdel_userid, pdeletionlog.username AS pdel_username, pdeletionlog.reason AS pdel_reason,
			tdeletionlog.userid AS tdel_userid, tdeletionlog.username AS tdel_username, tdeletionlog.reason AS tdel_reason";
		$postjoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS tdeletionlog ON (thread.threadid = tdeletionlog.primaryid AND tdeletionlog.type = 'thread')
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS pdeletionlog ON(post.postid = pdeletionlog.primaryid AND pdeletionlog.type = 'post')";
		$postfrom = "FROM " . TABLE_PREFIX . "deletionlog AS deletionlog
		INNER JOIN " . TABLE_PREFIX . "post AS post ON (deletionlog.primaryid = post.postid)";
		$show['deleted'] = true;
		$posttype = 'post';
	}
	else
	{
		$type = 'moderated';
		$table = 'moderation';
		$permission = 'canmoderateposts';
		if (!can_moderate(0, 'canmoderateposts'))
		{
			print_no_permission();
		}
		$postselect = '';
		$postjoin = '';
		$postfrom = "FROM " . TABLE_PREFIX . "moderation AS moderation
		INNER JOIN " . TABLE_PREFIX . "post AS post ON (moderation.primaryid = post.postid)";
		$posttype = 'reply';
	}

	if ($vbulletin->options['threadmarking'])
	{
		cache_ordered_forums(1);
	}

	$modforums = array();
	if ($forumid)
	{
		require_once(DIR . '/includes/functions_misc.php');
		$forums = fetch_child_forums($forumid, 'ARRAY');
		$forums[] = $forumid;
		$forums = array_flip($forums);
	}
	else
	{
		$forums = $vbulletin->forumcache;
	}

	foreach ($forums AS $mforumid => $null)
	{
		$forumperms = $vbulletin->userinfo['forumpermissions']["$mforumid"];
		if (can_moderate($mforumid, $permission)
			AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canview']
			AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']
			AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']
		)
		{
			$modforums[] = $mforumid;
		}
	}

	if (empty($modforums))
	{
		print_no_permission();
	}

	$forumjumpbits =  construct_mod_forum_jump(-1, $forumid, '', $permission);

	$show['inlinemod'] = true;
	$url = SCRIPTPATH;
	if ($show['popups'])
	{
		eval('$threadadmin_imod_menu = "' . fetch_template('threadadmin_imod_menu_post') . '";');
	}
	else
	{
		$threadadmin_imod_menu = '';
	}

	if (!$daysprune)
	{
		$daysprune = ($vbulletin->userinfo['daysprune']) ? $vbulletin->userinfo['daysprune'] : 30;
	}
	$datecut = ($daysprune != -1) ? "AND $table.dateline >= " . (TIMENOW - ($daysprune * 86400)) : '';


	// complete form fields on page
	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
	$daysprunesel = array($daysprunesel => 'selected="selected"');

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$vbulletin->GPC['sortorder'] = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	switch ($sortfield)
	{
		case 'title':
		case 'dateline':
		case 'username':
			$sqlsortfield = 'post.' . $sortfield;
			break;
		default:
			$handled = false;
			($hook = vBulletinHook::fetch_hook('moderation_posts_sort')) ? eval($hook) : false;
			if (!$handled)
			{
				$sqlsortfield = 'post.dateline';
				$sortfield = 'dateline';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('moderation_postsquery_postscount')) ? eval($hook) : false;

	$postscount = $db->query_first_slave("
		SELECT COUNT(*) AS posts
		$hook_query_fields
		$postfrom
		$hook_query_joins
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		WHERE type = '$posttype'
			AND forumid IN (" . implode(', ', $modforums) . ")
			$datecut
			$hook_query_where
	");
	$totalposts = $postscount['posts'];

	// set defaults
	sanitize_pageresults($totalposts, $pagenumber, $perpage, 200, 4);

	// display posts
	$limitlower = ($pagenumber - 1) * $perpage;
	$limitupper = ($pagenumber) * $perpage;

	if ($limitupper > $totalposts)
	{
		$limitupper = $totalposts;
		if ($limitlower > $totalposts)
		{
			$limitlower = ($totalposts - $perpage) - 1;
		}
	}
	if ($limitlower < 0)
	{
		$limitlower = 0;
	}
	if ($totalposts)
	{
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('moderation_postsquery_postid')) ? eval($hook) : false;

		$lastread = array();
		$postids = array();
		// Fetch ids
		$posts = $db->query_read_slave("
			SELECT post.postid, thread.forumid
				$hook_query_fields
				$postfrom
				$hook_query_joins
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			WHERE type = '$posttype'
				AND forumid IN (" . implode(', ', $modforums) . ")
				$datecut
				$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder
			LIMIT $limitlower, $perpage
		");
		while ($post = $db->fetch_array($posts))
		{
			$postids[] = $post['postid'];
			// get last read info for each thread
			if (empty($lastread["$post[forumid]"]))
			{
				if ($vbulletin->options['threadmarking'])
				{
					$lastread["$post[forumid]"] = max($vbulletin->forumcache["$post[forumid]"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
				}
				else
				{
					$lastread["$post[forumid]"] = max(intval(fetch_bbarray_cookie('forum_view', $post['forumid'])), $vbulletin->userinfo['lastvisit']);
				}
			}
		}
		$limitlower++;

		$hasposts = true;
		$postbits = '';
		$pagenav = '';
		$counter = 0;
		$toread = 0;

		$vbulletin->options['showvotes'] = intval($vbulletin->options['showvotes']);

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('moderation_postsquery')) ? eval($hook) : false;

		$posts = $db->query_read_slave("
			SELECT
				post.postid, post.title AS posttitle, post.dateline AS postdateline,
				post.iconid AS posticonid, post.pagetext, post.visible,
				IF(post.userid = 0, post.username, user.username) AS username,
				thread.threadid, thread.title AS threadtitle, thread.iconid AS threadiconid, thread.replycount,
				IF(thread.views = 0, thread.replycount + 1, thread.views) AS views, thread.firstpostid,
				thread.pollid, thread.sticky, thread.open, thread.lastpost, thread.forumid, thread.visible AS thread_visible,
				user.userid
				$postselect
				" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], ', threadread.readtime AS threadread') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			$postjoin
			" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")") . "
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
			$hook_query_joins
			WHERE post.postid IN (" . implode(', ', $postids) . ")
				$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder
		");
		unset($sqlsortfield, $sqlsortorder);

		require_once(DIR . '/includes/functions_forumdisplay.php');

		while ($post = $db->fetch_array($posts))
		{
			$item['forumtitle'] = $vbulletin->forumcache["$item[forumid]"]['title'];

			// do post folder icon
			if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
			{
				// new if post hasn't been read or made since forum was last read
				$isnew = ($post['postdateline'] > $post['threadread'] AND $post['postdateline'] > $vbulletin->forumcache["$post[forumid]"]['forumread']);
			}
			else
			{
				$isnew = ($post['postdateline'] > $vbulletin->userinfo['lastvisit']);
			}

			if ($isnew)
			{
				$post['post_statusicon'] = 'new';
				$post['post_statustitle'] = $vbphrase['unread'];
			}
			else
			{
				$post['post_statusicon'] = 'old';
				$post['post_statustitle'] = $vbphrase['old'];
			}

			// allow icons?
			$post['allowicons'] = $vbulletin->forumcache["$post[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'];

			// get POST icon from icon cache
			$post['posticonpath'] =& $vbulletin->iconcache["$post[posticonid]"]['iconpath'];
			$post['posticontitle'] =& $vbulletin->iconcache["$post[posticonid]"]['title'];

			// show post icon?
			if ($post['allowicons'])
			{
				// show specified icon
				if ($post['posticonpath'])
				{
					$post['posticon'] = true;
				}
				// show default icon
				else if (!empty($vbulletin->options['showdeficon']))
				{
					$post['posticon'] = true;
					$post['posticonpath'] = $vbulletin->options['showdeficon'];
					$post['posticontitle'] = '';
				}
				// do not show icon
				else
				{
					$post['posticon'] = false;
					$post['posticonpath'] = '';
					$post['posticontitle'] = '';
				}
			}
			// do not show post icon
			else
			{
				$post['posticon'] = false;
				$post['posticonpath'] = '';
				$post['posticontitle'] = '';
			}

			$post['pagetext'] = preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siU', '', $post['pagetext']);

			// get first 200 chars of page text
			$post['pagetext'] = htmlspecialchars_uni(fetch_censored_text(trim(fetch_trimmed_title(strip_bbcode($post['pagetext'], 1), 200))));

			// get post title
			if ($post['posttitle'] == '')
			{
				$post['posttitle'] = fetch_trimmed_title($post['pagetext'], 50);
			}
			else
			{
				$post['posttitle'] = fetch_censored_text($post['posttitle']);
			}

			// format post text
			$post['pagetext'] = nl2br($post['pagetext']);

			// get info from post
			$post = process_thread_array($post, $lastread["$post[forumid]"], $post['allowicons']);

			$show['managepost'] = (can_moderate($post['forumid'], 'candeleteposts') OR can_moderate($post['forumid'], 'canremoveposts')) ? true : false;
			$show['approvepost'] = (can_moderate($post['forumid'], 'canmoderateposts')) ? true : false;
			$show['managethread'] = (can_moderate($post['forumid'], 'canmanagethreads')) ? true : false;
			$show['disabled'] = ($show['managethread'] OR $show['managepost'] OR $show['approvepost']) ? false : true;

			$show['moderated'] = (!$post['visible'] OR (!$post['thread_visible'] AND $post['postid'] == $post['firstpostid'])) ? true : false;

			if ($post['pdel_userid'])
			{
				$post['del_username'] =& $post['pdel_username'];
				$post['del_userid'] =& $post['pdel_userid'];
				$post['del_reason'] = fetch_censored_text($post['pdel_reason']);
				$show['deleted'] = true;
			}
			else if ($post['tdel_userid'])
			{
				$post['del_username'] =& $post['tdel_username'];
				$post['del_userid'] =& $post['tdel_userid'];
				$post['del_reason'] = fetch_censored_text($post['tdel_reason']);
				$show['deleted'] = true;
			}
			else
			{
				$show['deleted'] = false;
			}

			exec_switch_bg();

			($hook = vBulletinHook::fetch_hook('search_results_postbit')) ? eval($hook) : false;

			eval('$postbits .= "' . fetch_template('search_results_postbit') . '";');
		}

		$db->free_result($posts);
		unset($postids);
		$sorturl = 'moderation.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewposts&amp;type=" . $type . "&amp;pp=$perpage&amp;daysprune=$daysprune";
		$pagenav = construct_page_nav($pagenumber, $perpage, $totalposts, $sorturl . "&amp;sort=$sortfield" . iif(!empty($vbulletin->GPC['sortorder']), "&amp;order=" . $vbulletin->GPC['sortorder']));
		$show['haveposts'] = true;
	}
	else
	{
		$totalposts = 0;
		$show['haveposts'] = false;
	}

	if ($type == 'moderated')
	{
		$show['delete'] = (can_moderate(0, 'canremoveposts') OR can_moderate(0, 'candeleteposts'));
	}
	else
	{
		$show['delete'] = can_moderate(0, 'canremoveposts');
	}
	$show['undelete'] = can_moderate(0, 'candeleteposts');

	$navbits[''] = $vbphrase['moderation'];
	$navbits = construct_navbits($navbits);

	// build the cp nav
	construct_usercp_nav($type . 'posts');

	($hook = vBulletinHook::fetch_hook('moderation_posts_complete')) ? eval($hook) : false;

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('moderation_posts') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

if ($_REQUEST['do'] == 'viewvms')
{
	require_once(DIR . '/includes/functions_visitormessage.php');

	// Check whether Visitor Messages are on on this board

	if(!($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_visitor_messaging']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'daysprune'  => TYPE_INT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML,
		'type'       => TYPE_NOHTML,
	));

	($hook = vBulletinHook::fetch_hook('moderation_visitor_messages_start')) ? eval($hook) : false;

	// Values that are reused in templates
	$sortfield  =& $vbulletin->GPC['sortfield'];
	$perpage    =& $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$daysprune  =& $vbulletin->GPC['daysprune'];
	$type       =& $vbulletin->GPC['type'];
	$messagetype = 'viewvms';

	if ($type == 'deleted')
	{
		$messagephrase = $vbphrase['deleted_visitor_messages'];
		$table = 'deletionlog';
		$permission = '';
		if (!can_moderate(0,'canmoderatevisitormessages'))
		{
			print_no_permission();
		}
		$postselect = ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason";
		$postfrom = "
			FROM " . TABLE_PREFIX . "deletionlog AS deletionlog
			INNER JOIN " . TABLE_PREFIX . "visitormessage AS visitormessage ON (deletionlog.primaryid = visitormessage.vmid AND deletionlog. type = 'visitormessage')
		";
		$show['deleted'] = true;
	}
	else
	{
		$messagephrase = $vbphrase['moderated_visitor_messages'];
		$type = 'moderated';
		$table = 'moderation';
		$permission = 'canmoderatevisitormessages';
		if (!can_moderate(0, $permission))
		{
			print_no_permission();
		}
		$postselect = '';
		$postjoin = '';
		$postfrom = "
			FROM " . TABLE_PREFIX . "moderation AS moderation
			INNER JOIN " . TABLE_PREFIX . "visitormessage AS visitormessage ON (moderation.primaryid = visitormessage.vmid AND moderation.type = 'visitormessage')
		";
	}

	$show['inlinemod'] = true;
	$url = SCRIPTPATH;

	if (!$daysprune)
	{
		$daysprune = ($vbulletin->userinfo['daysprune']) ? $vbulletin->userinfo['daysprune'] : 30;
	}
	$datecut = ($daysprune != -1) ? "AND $table.dateline >= " . (TIMENOW - ($daysprune * 86400)) : '';

	// complete form fields on page
	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
	$daysprunesel = array($daysprunesel => 'selected="selected"');

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$vbulletin->GPC['sortorder'] = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	switch ($sortfield)
	{
		case 'username':
			$sqlsortfield = 'visitormessage.postusername';
			break;

		case 'dateline':
			$sqlsortfield = 'visitormessage.' . $sortfield;
			break;

		default:
			$handled = false;
			($hook = vBulletinHook::fetch_hook('moderation_visitor_messages_sort')) ? eval($hook) : false;
			if (!$handled)
			{
				$sqlsortfield = 'visitormessage.dateline';
				$sortfield = 'dateline';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('moderation_visitor_messages_query')) ? eval($hook) : false;

	if ($vbulletin->GPC['perpage'] == 0)
	{
		$perpage = $vbulletin->options['vm_perpage'];
	}
	else if ($vbulletin->GPC['perpage'] > $vbulletin->options['vm_maxperpage'])
	{
		$perpage = $vbulletin->options['vm_maxperpage'];
	}

	require_once(DIR . '/includes/class_bbcode.php');
	require_once(DIR . '/includes/class_visitormessage.php');

	$bbcode =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
	$factory =& new vB_Visitor_MessageFactory($vbulletin, $bbcode, $userinfo);

	$messagebits = '';
	$counter = 0;
	do
	{
		if (!$pagenumber)
		{
			$pagenumber = 1;
		}
		$start = ($pagenumber - 1) * $perpage;

		$messagebits = '';
		$messages = $vbulletin->db->query_read("
			SELECT SQL_CALC_FOUND_ROWS
				visitormessage.*, user1.*, visitormessage.ipaddress AS messageipaddress, visitormessage.userid AS profileuserid, user2.username AS profileusername
				$postselect
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				$hook_query_fields
			$postfrom
			LEFT JOIN " . TABLE_PREFIX . "user AS user1 ON (visitormessage.postuserid = user1.userid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user2 ON (visitormessage.userid = user2.userid)
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user1.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user1.userid)" : "") . "
			$deljoinsql
			$hook_query_joins
			WHERE 1=1
				$datecut
				$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder
			LIMIT $start, $perpage
		");
		list($messagetotal) = $vbulletin->db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

		if ($start >= $messagetotal)
		{
			$pagenumber = ceil($messagetotal / $perpage);
		}
	}
	while ($start >= $messagetotal AND $messagetotal);

	$show['profile'] = true;
	while ($message = $db->fetch_array($messages))
	{
		$response_handler =& $factory->create($message);
		$response_handler->cachable = false;
		$response_handler->converse = false;
		$messagebits .= $response_handler->construct();
	}

	$pagenavbits = array(
		"do=viewvms"
	);
	if ($perpage != $vbulletin->options['vm_perpage'])
	{
		$pagenavbits[] = "pp=$perpage";
	}
	if ($daysprune != ($vbulletin->userinfo['daysprune'] ? $vbulletin->userinfo['daysprune'] : 30))
	{
		$pagenavbits[] = "daysprune=$daysprune";
	}
	if ($sortfield != 'dateline')
	{
		$pagenavbits[] = "sortfield=$sortfield";
	}
	if ($vbulletin->GPC['sortorder'] == 'asc')
	{
		$pagenavbits[] = 'order=' . $vbulletin->GPC['sortorder'];
	}
	if ($type == 'deleted')
	{
		$pagenavbits[] = 'type=deleted';
	}

	$pagenavurl = 'moderation.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavbits);
	$pagenav = construct_page_nav($pagenumber, $perpage, $messagetotal, $pagenavurl);

	$show['havemessages'] = $messagetotal ? true : false;

	$first = $messagetotal ? ($pagenumber - 1) * $perpage + 1 : 0;
	$last = ($last = $perpage * $pagenumber) > $messagetotal ? $messagetotal : $last;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	unset($sqlsortfield, $sqlsortorder);

	$navbits[''] = $vbphrase['moderation'];
	$navbits = construct_navbits($navbits);

	construct_usercp_nav($type . 'vms');

	($hook = vBulletinHook::fetch_hook('moderation_visitor_messages_complete')) ? eval($hook) : false;

	eval('$headinclude .= "' . fetch_template('memberinfo_css') . '";');
	eval('$moderation_filter = "' . fetch_template('moderation_filter') . '";');

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('moderation_visitormessages') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

if ($_REQUEST['do'] == 'viewgms')
{
	require_once(DIR . '/includes/functions_socialgroup.php');

	// Check whether Group Messages are switched on this board

	if (!($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']) OR !$vbulletin->options['socnet_groups_msg_enabled'])
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'daysprune'  => TYPE_INT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML,
		'type'       => TYPE_NOHTML,
	));

	($hook = vBulletinHook::fetch_hook('moderation_group_messages_start')) ? eval($hook) : false;

	// Values that are reused in templates
	$sortfield  =& $vbulletin->GPC['sortfield'];
	$perpage    =& $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$daysprune  =& $vbulletin->GPC['daysprune'];
	$type       =& $vbulletin->GPC['type'];
	$messagetype = 'viewgms';

	if ($type == 'deleted')
	{
		$messagephrase = $vbphrase['deleted_social_group_messages'];
		$table = 'deletionlog';
		$permission = '';
		if (!can_moderate(0, 'canmoderategroupmessages'))
		{
			print_no_permission();
		}
		$postselect = ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason";
		$postfrom = "
			FROM " . TABLE_PREFIX . "deletionlog AS deletionlog
			INNER JOIN " . TABLE_PREFIX . "groupmessage AS groupmessage ON (deletionlog.primaryid = groupmessage.gmid AND deletionlog.type = 'groupmessage')
		";
		$show['deleted'] = true;
	}
	else
	{
		$messagephrase = $vbphrase['moderated_social_group_messages'];
		$type = 'moderated';
		$table = 'moderation';
		$permission = 'canmoderategroupmessages';
		if (!can_moderate(0, $permission))
		{
			print_no_permission();
		}
		$postselect = '';
		$postjoin = '';
		$postfrom = "
			FROM " . TABLE_PREFIX . "moderation AS moderation
			INNER JOIN " . TABLE_PREFIX . "groupmessage AS groupmessage ON (moderation.primaryid = groupmessage.gmid AND moderation.type = 'groupmessage')
		";
	}

	$show['inlinemod'] = true;
	$url = SCRIPTPATH;

	if (!$daysprune)
	{
		$daysprune = ($vbulletin->userinfo['daysprune']) ? $vbulletin->userinfo['daysprune'] : 30;
	}
	$datecut = ($daysprune != -1) ? "AND $table.dateline >= " . (TIMENOW - ($daysprune * 86400)) : '';

	// complete form fields on page
	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
	$daysprunesel = array($daysprunesel => 'selected="selected"');

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$vbulletin->GPC['sortorder'] = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	switch ($sortfield)
	{
		case 'username':
			$sqlsortfield = 'groupmessage.postusername';
			break;

		case 'dateline':
			$sqlsortfield = 'groupmessage.' . $sortfield;
			break;

		default:
			$handled = false;
			($hook = vBulletinHook::fetch_hook('moderation_group_messages_sort')) ? eval($hook) : false;
			if (!$handled)
			{
				$sqlsortfield = 'groupmessage.dateline';
				$sortfield = 'dateline';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('moderation_group_messages_query')) ? eval($hook) : false;

	if ($vbulletin->GPC['perpage'] == 0)
	{
		$perpage = $vbulletin->options['vm_perpage'];
	}
	else if ($vbulletin->GPC['perpage'] > $vbulletin->options['vm_maxperpage'])
	{
		$perpage = $vbulletin->options['vm_maxperpage'];
	}

	require_once(DIR . '/includes/class_bbcode.php');
	require_once(DIR . '/includes/class_groupmessage.php');

	$bbcode =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
	$factory =& new vB_Group_MessageFactory($vbulletin, $bbcode, $null);

	$messagebits = '';
	$counter = 0;
	do
	{
		if (!$pagenumber)
		{
			$pagenumber = 1;
		}
		$start = ($pagenumber - 1) * $perpage;

		$messagebits = '';
		$messages = $vbulletin->db->query_read("
			SELECT SQL_CALC_FOUND_ROWS
				groupmessage.*, user.*, groupmessage.ipaddress AS messageipaddress, socialgroup.name
				$postselect
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				$hook_query_fields
			$postfrom
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (groupmessage.postuserid = user.userid)
			LEFt JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (groupmessage.groupid = socialgroup.groupid)
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			$deljoinsql
			$hook_query_joins
			WHERE 1=1
				$datecut
				$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder
			LIMIT $start, $perpage
		");
		list($messagetotal) = $vbulletin->db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

		if ($start >= $messagetotal)
		{
			$pagenumber = ceil($messagetotal / $perpage);
		}
	}
	while ($start >= $messagetotal AND $messagetotal);

	$show['group'] = true;
	while ($message = $db->fetch_array($messages))
	{
		$message['name_html'] = fetch_word_wrapped_string(fetch_censored_text($message['name']));
		$response_handler =& $factory->create($message);
		$response_handler->cachable = false;
		$messagebits .= $response_handler->construct();
	}

	$pagenavbits = array(
		"do=viewgms"
	);
	if ($perpage != $vbulletin->options['vm_perpage'])
	{
		$pagenavbits[] = "pp=$perpage";
	}
	if ($daysprune != ($vbulletin->userinfo['daysprune'] ? $vbulletin->userinfo['daysprune'] : 30))
	{
		$pagenavbits[] = "daysprune=$daysprune";
	}
	if ($sortfield != 'dateline')
	{
		$pagenavbits[] = "sortfield=$sortfield";
	}
	if ($vbulletin->GPC['sortorder'] == 'asc')
	{
		$pagenavbits[] = 'order=' . $vbulletin->GPC['sortorder'];
	}
	if ($type == 'deleted')
	{
		$pagenavbits[] = 'type=deleted';
	}

	$pagenavurl = 'moderation.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavbits);
	$pagenav = construct_page_nav($pagenumber, $perpage, $messagetotal, $pagenavurl);

	$show['havemessages'] = $messagetotal ? true : false;

	$first = $messagetotal ? ($pagenumber - 1) * $perpage + 1 : 0;
	$last = ($last = $perpage * $pagenumber) > $messagetotal ? $messagetotal : $last;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	unset($sqlsortfield, $sqlsortorder);

	$navbits[''] = $vbphrase['moderation'];
	$navbits = construct_navbits($navbits);

	construct_usercp_nav($type . 'gms');

	($hook = vBulletinHook::fetch_hook('moderation_group_messages_complete')) ? eval($hook) : false;

	eval('$headinclude .= "' . fetch_template('memberinfo_css') . '";');
	eval('$moderation_filter = "' . fetch_template('moderation_filter') . '";');

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('moderation_groupmessages') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

if ($_REQUEST['do'] == 'viewpcs')
{
	require_once(DIR . '/includes/functions_picturecomment.php');

	// Check whether PC's are actually on on this board

	if (!($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']) OR !$vbulletin->options['pc_enabled'])
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'daysprune'  => TYPE_INT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML,
		'type'       => TYPE_NOHTML,
	));

	($hook = vBulletinHook::fetch_hook('moderation_picture_comments_start')) ? eval($hook) : false;

	// Values that are reused in templates
	$sortfield  =& $vbulletin->GPC['sortfield'];
	$perpage    =& $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$daysprune  =& $vbulletin->GPC['daysprune'];
	$type       =& $vbulletin->GPC['type'];
	$messagetype = 'viewpcs';

	if ($type == 'deleted')
	{
		$messagephrase = $vbphrase['deleted_picture_comments'];
		$table = 'deletionlog';
		$permission = '';
		if (!can_moderate(0, 'canmoderatepicturecomments'))
		{
			print_no_permission();
		}
		$postselect = ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason";
		$postfrom = "
			FROM " . TABLE_PREFIX . "deletionlog AS deletionlog
			INNER JOIN " . TABLE_PREFIX . "picturecomment AS picturecomment ON (deletionlog.primaryid = picturecomment.commentid AND deletionlog.type = 'picturecomment')
		";
		$show['deleted'] = true;
	}
	else
	{
		$messagephrase = $vbphrase['moderated_picture_comments'];
		$type = 'moderated';
		$table = 'moderation';
		$permission = 'canmoderatepicturecomments';
		if (!can_moderate(0, $permission))
		{
			print_no_permission();
		}
		$postselect = '';
		$postjoin = '';
		$postfrom = "
			FROM " . TABLE_PREFIX . "moderation AS moderation
			INNER JOIN " . TABLE_PREFIX . "picturecomment AS picturecomment ON (moderation.primaryid = picturecomment.commentid AND moderation.type = 'picturecomment')
		";
	}

	$show['inlinemod'] = true;
	$url = SCRIPTPATH;

	if (!$daysprune)
	{
		$daysprune = ($vbulletin->userinfo['daysprune']) ? $vbulletin->userinfo['daysprune'] : 30;
	}
	$datecut = ($daysprune != -1) ? "AND $table.dateline >= " . (TIMENOW - ($daysprune * 86400)) : '';

	// complete form fields on page
	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
	$daysprunesel = array($daysprunesel => 'selected="selected"');

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$vbulletin->GPC['sortorder'] = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	switch ($sortfield)
	{
		case 'username':
			$sqlsortfield = 'picturecomment.postusername';
			break;

		case 'dateline':
			$sqlsortfield = 'picturecomment.' . $sortfield;
			break;

		default:
			$handled = false;
			($hook = vBulletinHook::fetch_hook('moderation_picture_comments_sort')) ? eval($hook) : false;
			if (!$handled)
			{
				$sqlsortfield = 'picturecomment.dateline';
				$sortfield = 'dateline';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('moderation_picture_comments_query')) ? eval($hook) : false;

	if ($vbulletin->GPC['perpage'] == 0)
	{
		$perpage = $vbulletin->options['vm_perpage'];
	}
	else if ($vbulletin->GPC['perpage'] > $vbulletin->options['vm_maxperpage'])
	{
		$perpage = $vbulletin->options['vm_maxperpage'];
	}

	require_once(DIR . '/includes/class_bbcode.php');
	require_once(DIR . '/includes/class_picturecomment.php');

	$bbcode =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$messagebits = '';
	$counter = 0;
	do
	{
		if (!$pagenumber)
		{
			$pagenumber = 1;
		}
		$start = ($pagenumber - 1) * $perpage;

		$messagebits = '';
		$messages = $vbulletin->db->query_read("
			SELECT SQL_CALC_FOUND_ROWS
				picturecomment.*, user.*, picturecomment.ipaddress AS messageipaddress, user1.username AS pictureowner_name, user1.userid AS pictureowner_userid, albumpicture.albumid, picture.caption
				$postselect
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				,picture.extension, picture.filesize, picture.idhash,
				picture.thumbnail_filesize, picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height
				$hook_query_fields
			$postfrom
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (picturecomment.postuserid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = picturecomment.pictureid)
			LEFT JOIN " . TABLE_PREFIX . "albumpicture AS albumpicture ON (albumpicture.pictureid = picture.pictureid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user1 ON (picture.userid = user1.userid)
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			$deljoinsql
			$hook_query_joins
			WHERE 1=1
				$datecut
				$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder
			LIMIT $start, $perpage
		");
		list($messagetotal) = $vbulletin->db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

		if ($start >= $messagetotal)
		{
			$pagenumber = ceil($messagetotal / $perpage);
		}
	}
	while ($start >= $messagetotal AND $messagetotal);

	require_once(DIR . '/includes/functions_album.php');

	$show['picture'] = true;
	while ($comment = $db->fetch_array($messages))
	{
		// $comment contains comment, picture, and album info
		$pictureinfo = prepare_pictureinfo_thumb($comment, $comment);

		$factory =& new vB_Picture_CommentFactory($vbulletin, $bbcode, $pictureinfo);

		$response_handler =& new vB_Picture_Comment_ModeratedView($vbulletin, $factory, $bbcode, $pictureinfo, $comment);
		$response_handler->cachable = false;

		$messagebits .= $response_handler->construct();

		unset($factory);
		unset($response_handler);
	}

	$pagenavbits = array(
		"do=viewpcs"
	);
	if ($perpage != $vbulletin->options['vm_perpage'])
	{
		$pagenavbits[] = "pp=$perpage";
	}
	if ($daysprune != ($vbulletin->userinfo['daysprune'] ? $vbulletin->userinfo['daysprune'] : 30))
	{
		$pagenavbits[] = "daysprune=$daysprune";
	}
	if ($sortfield != 'dateline')
	{
		$pagenavbits[] = "sortfield=$sortfield";
	}
	if ($vbulletin->GPC['sortorder'] == 'asc')
	{
		$pagenavbits[] = 'order=' . $vbulletin->GPC['sortorder'];
	}
	if ($type == 'deleted')
	{
		$pagenavbits[] = 'type=deleted';
	}

	$pagenavurl = 'moderation.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavbits);
	$pagenav = construct_page_nav($pagenumber, $perpage, $messagetotal, $pagenavurl);

	$show['havemessages'] = $messagetotal ? true : false;

	$first = $messagetotal ? ($pagenumber - 1) * $perpage + 1 : 0;
	$last = ($last = $perpage * $pagenumber) > $messagetotal ? $messagetotal : $last;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	unset($sqlsortfield, $sqlsortorder);

	$navbits[''] = $vbphrase['moderation'];
	$navbits = construct_navbits($navbits);

	construct_usercp_nav($type . 'pcs');

	($hook = vBulletinHook::fetch_hook('moderation_picture_comments_complete')) ? eval($hook) : false;

	eval('$headinclude .= "' . fetch_template('picturecomment_css') . '";');
	eval('$moderation_filter = "' . fetch_template('moderation_filter') . '";');

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('moderation_picturecomments') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

if ($_REQUEST['do'] == 'viewpics')
{
	require_once(DIR . '/includes/functions_picturecomment.php');

	if (!($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'daysprune'  => TYPE_INT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML,
	));

	($hook = vBulletinHook::fetch_hook('moderation_picture_start')) ? eval($hook) : false;

	// Values that are reused in templates
	$sortfield  =& $vbulletin->GPC['sortfield'];
	$perpage    =& $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$daysprune  =& $vbulletin->GPC['daysprune'];
	$messagetype = 'viewpics';

	$messagephrase = $vbphrase['moderated_pictures'];
	$permission = 'canmoderatepictures';
	if (!can_moderate(0, $permission))
	{
		print_no_permission();
	}

	$show['inlinemod'] = true;
	$url = SCRIPTPATH;

	if (!$daysprune)
	{
		$daysprune = ($vbulletin->userinfo['daysprune']) ? $vbulletin->userinfo['daysprune'] : 30;
	}
	$datecut = ($daysprune != -1) ? "AND albumpicture.dateline >= " . (TIMENOW - ($daysprune * 86400)) : '';

	// complete form fields on page
	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
	$daysprunesel = array($daysprunesel => 'selected="selected"');

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$vbulletin->GPC['sortorder'] = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	switch ($sortfield)
	{
		case 'username':
			$sqlsortfield = 'user.username';
			break;

		case 'dateline':
			$sqlsortfield = 'albumpicture.' . $sortfield;
			break;

		default:
			$handled = false;
			($hook = vBulletinHook::fetch_hook('moderation_picture_sort')) ? eval($hook) : false;
			if (!$handled)
			{
				$sqlsortfield = 'albumpicture.dateline';
				$sortfield = 'dateline';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('moderation_picture_query')) ? eval($hook) : false;

	if ($vbulletin->GPC['perpage'] == 0)
	{
		$perpage = $vbulletin->options['vm_perpage'];
	}
	else if ($vbulletin->GPC['perpage'] > $vbulletin->options['vm_maxperpage'])
	{
		$perpage = $vbulletin->options['vm_maxperpage'];
	}

	$messagebits = '';
	$counter = 0;
	do
	{
		if (!$pagenumber)
		{
			$pagenumber = 1;
		}
		$start = ($pagenumber - 1) * $perpage;

		$picturebits = '';
		$pictures = $vbulletin->db->query_read("
			SELECT SQL_CALC_FOUND_ROWS
				user.*, albumpicture.dateline, albumpicture.albumid, album.title AS albumtitle
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				,picture.pictureid, picture.extension, picture.filesize, picture.idhash, picture.caption,
				picture.thumbnail_filesize, picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height
				$hook_query_fields
			FROM " . TABLE_PREFIX . "picture AS picture
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (picture.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "albumpicture AS albumpicture ON (albumpicture.pictureid = picture.pictureid)
			LEFT JOIN " . TABLE_PREFIX . "album AS album ON (album.albumid = albumpicture.albumid)
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			$hook_query_joins
			WHERE picture.state = 'moderation'
				$datecut
				$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder
			LIMIT $start, $perpage
		");
		list($picturetotal) = $vbulletin->db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

		if ($start >= $picturetotal)
		{
			$pagenumber = ceil($picturetotal / $perpage);
		}
	}
	while ($start >= $picturetotal AND $picturetotal);

	require_once(DIR . '/includes/functions_album.php');

	$show['picture'] = true;
	while ($picture = $db->fetch_array($pictures))
	{
		fetch_musername($picture);
		$picture['albumtitle'] = fetch_word_wrapped_string(fetch_censored_text($picture['albumtitle']));
		// $picture contains comment, picture, and album info
		$pictureinfo = prepare_pictureinfo_thumb($picture, $picture);
		eval('$picturebits .= "' . fetch_template('moderation_picturebit') . '";');
	}

	$pagenavbits = array(
		"do=viewpics"
	);
	if ($perpage != $vbulletin->options['vm_perpage'])
	{
		$pagenavbits[] = "pp=$perpage";
	}
	if ($daysprune != ($vbulletin->userinfo['daysprune'] ? $vbulletin->userinfo['daysprune'] : 30))
	{
		$pagenavbits[] = "daysprune=$daysprune";
	}
	if ($sortfield != 'dateline')
	{
		$pagenavbits[] = "sortfield=$sortfield";
	}
	if ($vbulletin->GPC['sortorder'] == 'asc')
	{
		$pagenavbits[] = 'order=' . $vbulletin->GPC['sortorder'];
	}
	if ($type == 'deleted')
	{
		$pagenavbits[] = 'type=deleted';
	}

	$pagenavurl = 'moderation.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavbits);
	$pagenav = construct_page_nav($pagenumber, $perpage, $picturetotal, $pagenavurl);

	$show['havepictures'] = $picturetotal ? true : false;

	$first = $picturetotal ? ($pagenumber - 1) * $perpage + 1 : 0;
	$last = ($last = $perpage * $pagenumber) > $picturetotal ? $picturetotal : $last;

	$show['delete'] = (can_moderate(0, 'candeletealbumpicture'));

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	unset($sqlsortfield, $sqlsortorder);

	$navbits[''] = $vbphrase['moderation'];
	$navbits = construct_navbits($navbits);

	construct_usercp_nav('moderatedpics');

	($hook = vBulletinHook::fetch_hook('moderation_picture_complete')) ? eval($hook) : false;

	eval('$headinclude .= "' . fetch_template('picturecomment_css') . '";');
	eval('$moderation_filter = "' . fetch_template('moderation_filter') . '";');

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('moderation_pictures') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 31700 $
|| ####################################################################
\*======================================================================*/
?>
