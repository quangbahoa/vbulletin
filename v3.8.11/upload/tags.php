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
define('THIS_SCRIPT', 'tags');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('inlinemod', 'search');

// get special data templates from the datastore
$specialtemplates = array(
	'tagcloud',
	'iconcache'
);

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'cloud' => array(
		'tag_cloud_box',
		'tag_cloud_headinclude',
		'tag_cloud_link',
		'tag_cloud_page'
	),
	'tag' => array(
		'tag_search',
		'threadadmin_imod_menu_thread',
		'threadbit'
	)
);

if (empty($_REQUEST['do']))
{
	if (empty($_REQUEST['tag']))
	{
		$_REQUEST['do'] = 'cloud';
	}
	else
	{
		$_REQUEST['do'] = 'tag';
	}
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumdisplay.php');

if (!$vbulletin->options['threadtagging'])
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('tags_start')) ? eval($hook) : false;

// #######################################################################
if ($_REQUEST['do'] == 'cloud')
{
	require_once(DIR . '/includes/functions_search.php');

	$tag_cloud = fetch_tagcloud('usage');
	if ($tag_cloud)
	{
		eval('$tag_cloud_headinclude .= "' . fetch_template('tag_cloud_headinclude') . '";');
	}
	else
	{
		$tag_cloud_headinclude = '';
	}

	$navbits = construct_navbits(array(
		'' => $vbphrase['tags'],
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('tags_cloud_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('tag_cloud_page') . '");');
}

// #######################################################################
if ($_REQUEST['do'] == 'tag')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'tag' => TYPE_NOHTML,
		'pagenumber' => TYPE_UINT,
		'perpage' => TYPE_UINT
	));

	if (!$vbulletin->GPC['tag'])
	{
		standard_error(fetch_error('invalidid', $vbphrase['tag'], $vbulletin->options['contactuslink']));
	}

	$tag = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "tag
		WHERE tagtext = '" . $db->escape_string($vbulletin->GPC['tag']) . "'
	");
	if (!$tag)
	{
		standard_error(fetch_error('no_content_tagged_with_x', $vbulletin->GPC['tag']));
	}

	// get forum ids for all forums user is allowed to view
	$forumids = array_keys($vbulletin->forumcache);
	$self_only = array();

	foreach ($forumids AS $key => $forumid)
	{
		$forum = $vbulletin->forumcache["$forumid"];

		$forumperms = fetch_permissions($forumid);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !verify_forum_password($forumid, $forum['password'], false))
		{
			unset($forumids["$key"]);
			continue;
		}

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
		{
			$self_only[] = $forumid;
		}
	}

	if (!$forumids)
	{
		print_no_permission();
	}

	// needed to prevent mass amounts of queries
	require_once(DIR . '/includes/functions_forumlist.php');
	cache_moderators();

	$coventry = fetch_coventry('string');
	$globalignore = ($coventry ? "AND thread.postuserid NOT IN ($coventry) " : '');

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
	{
		$tachyjoin = "
			LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON
				(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "tachythreadcounter AS tachythreadcounter ON
				(tachythreadcounter.threadid = thread.threadid AND tachythreadcounter.userid = " . $vbulletin->userinfo['userid'] . ")
		";
		$tachy_columns = "
			IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost,
			IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter,
			IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid,
			IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount) AS replycount,
			IF(thread.views<=IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount), IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount)+1, thread.views) AS views
		";

	}
	else
	{
		$tachyjoin = '';
		$tachy_columns = 'thread.lastpost, thread.lastposter, thread.lastpostid, thread.replycount, IF(thread.views<=thread.replycount, thread.replycount+1, thread.views) AS views';
	}

	$hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('tags_list_query_limit')) ? eval($hook) : false;

	$threadid_sql = $db->query_read_slave("
		SELECT thread.threadid, $tachy_columns
		FROM " . TABLE_PREFIX . "thread AS thread
		INNER JOIN " . TABLE_PREFIX . "tagthread AS tagthread ON
			(tagthread.tagid = $tag[tagid] AND tagthread.threadid = thread.threadid)
		$tachyjoin
		$hook_query_joins
		WHERE thread.forumid IN(" . implode(', ', $forumids) . ")
			" . ($self_only ? 'AND IF(thread.forumid IN (' . implode(',', $self_only) . '), thread.postuserid = ' . $vbulletin->userinfo['userid'] . ', 1)' : '') . "
			AND thread.visible = 1
			AND thread.sticky IN (0, 1)
			AND thread.open <> 10
			$globalignore
			$hook_query_where
		ORDER BY lastpost DESC
		LIMIT " . intval($vbulletin->options['maxresults'])
	);

	$totalthreads = $db->num_rows($threadid_sql);
	if (!$totalthreads)
	{
		standard_error(fetch_error('no_content_tagged_with_x', $vbulletin->GPC['tag']));
	}

	if ($vbulletin->GPC['pagenumber'] <= 1)
	{
		$db->query_write("INSERT INTO " . TABLE_PREFIX . "tagsearch (tagid, dateline) VALUES ($tag[tagid], " . TIMENOW . ")");
	}

	$pagenumber = $vbulletin->GPC['pagenumber'];
	$perpage = $vbulletin->GPC['perpage'];

	sanitize_pageresults($totalthreads, $pagenumber, $perpage, 200, $vbulletin->options['maxthreads']);

	if ($pagenumber > 1)
	{
		$db->data_seek($threadid_sql, ($pagenumber - 1) * $perpage);
	}

	$threadids = array();
	$resultnum = 0;
	while ($thread = $db->fetch_array($threadid_sql))
	{
		$threadids[] = $thread['threadid'];

		$resultnum++;
		if ($resultnum >= $perpage)
		{
			break;
		}
	}
	$db->free_result($threadid_sql);

	if ($vbulletin->userinfo['userid'])
	{
		cache_ordered_forums(1);
	}

	// now move on to actual display code

	$hook_query_fields = $hook_query_joins = '';
	($hook = vBulletinHook::fetch_hook('tags_list_query_data')) ? eval($hook) : false;

	$thread_sql = $db->query_read_slave("
		SELECT
			thread.threadid, thread.title AS threadtitle, thread.forumid, pollid, open, postusername, postuserid, thread.iconid AS threadiconid,
			thread.dateline, notes, thread.visible, sticky, votetotal, thread.attach, $tachy_columns,
			thread.prefixid, thread.taglist, hiddencount, deletedcount
			" . ($vbulletin->options['threadpreview'] > 0 ? ', post.pagetext AS preview' : '') . "
			" . (($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid']) ? ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed" : "") . "
			" . (($vbulletin->userinfo['userid']) ? ", threadread.readtime AS threadread" : "") . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "thread AS thread
			" . (($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid']) ?  " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON(subscribethread.threadid = thread.threadid AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . " AND canview = 1)" : "") . "
			" . (($vbulletin->userinfo['userid']) ? " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
			" . ($vbulletin->options['threadpreview'] > 0 ? "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)" : '') . "
			$tachyjoin
			$hook_query_joins
		WHERE thread.threadid IN (" . implode(',', $threadids) . ")
	");

	$threads = array();
	$lastread = array();
	$managethread = $movethread = $deletethread = $approvethread = $openthread = array();

	$dotthreads = fetch_dot_threads_array(implode(',', $threadids));
	if ($vbulletin->options['showdots'] AND $vbulletin->userinfo['userid'])
	{
		$show['dotthreads'] = true;
	}
	else
	{
		$show['dotthreads'] = false;
	}

	while ($thread = $db->fetch_array($thread_sql))
	{
		$threads["$thread[threadid]"] = $thread;

		// get forum read times if needed
		if (!isset($lastread["$thread[forumid]"]))
		{
			if ($vbulletin->userinfo['userid'])
			{
				$forum = $vbulletin->forumcache["$thread[forumid]"];
				$lastread["$thread[forumid]"] = max($forum['forumread'], (TIMENOW - ($vbulletin->options['markinglimit'] * 86400)));
			}
			else
			{
				$forumview = intval(fetch_bbarray_cookie('forum_view', $thread['forumid']));
				$lastread["$thread[forumid]"] = ($forumview > $vbulletin->userinfo['lastvisit'] ? $forumview : $vbulletin->userinfo['lastvisit']);
			}
		}

		// check inline mod stuff
		if (can_moderate($thread['forumid'], 'canmanagethreads'))
		{
			$movethread["$thread[threadid]"] = 1;
			$show['movethread'] = true;
		}

		if (can_moderate($thread['forumid'], 'candeleteposts') OR can_moderate($thread['forumid'], 'canremoveposts'))
		{
			$deletethread["$thread[threadid]"] = 1;
			$show['deletethread'] = true;
		}

		if (can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			$approvethread["$thread[threadid]"] = 1;
			$show['approvethread'] = true;
		}

		if (can_moderate($thread['forumid'], 'canopenclose'))
		{
			$openthread["$thread[threadid]"] = 1;
			$show['openthread'] = true;

		}
		if ($vbulletin->forumcache["$thread[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'])
		{
			$show['threadicons'] = true;
		}
	}
	$db->free_result($thread_sql);

	if (!empty($managethread) OR !empty($movethread) OR !empty($deletethread) OR !empty($approvethread) OR !empty($openthread))
	{
		$show['inlinemod'] = true;
		$show['spamctrls'] = $show['deletethread'];
	}

	$columncount = 6;
	if ($show['threadicons'])
	{
		$columncount++;
	}
	if ($show['inlinemod'])
	{
		$columncount++;
	}

	$show['forumlink'] = true;
	$threadbits = '';

	($hook = vBulletinHook::fetch_hook('tags_list_threads')) ? eval($hook) : false;

	foreach ($threadids AS $threadid)
	{
		$thread = $threads["$threadid"];

		$forumperms = fetch_permissions($thread['forumid']);
		if ($vbulletin->options['threadpreview'] > 0 AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		{
			$thread['preview'] = '';
		}

		$thread = process_thread_array($thread, $lastread["$thread[forumid]"]);

		$show['disabled'] = ($movethread["$thread[threadid]"] OR $deletethread["$thread[threadid]"] OR $approvethread["$thread[threadid]"] OR $openthread["$thread[threadid]"]) ? false : true;

		eval('$threadbits .= "' . fetch_template('threadbit') . '";');
	}

	if ($show['popups'] AND $show['inlinemod'])
	{
		eval('$threadadmin_imod_menu = "' . fetch_template('threadadmin_imod_menu_thread') . '";');
	}

	$pagenav = construct_page_nav($pagenumber, $perpage, $totalthreads,
		'tags.php?tag=' . urlencode(unhtmlspecialchars($tag['tagtext']))
		. ($perpage != $vbulletin->options['maxthreads'] ? "&amp;pp=$perpage" : '')
	);

	$navbits = construct_navbits(array(
		'tags.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['tags'],
		'' => construct_phrase($vbphrase['threads_tagged_with_x'], $tag['tagtext'])
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('tags_list_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('tag_search') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
