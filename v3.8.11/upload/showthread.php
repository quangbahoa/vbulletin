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
define('THIS_SCRIPT', 'showthread');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'posting',
	'postbit',
	'showthread',
	'inlinemod',
	'reputationlevel'
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'mailqueue',
	'bookmarksitecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'ad_showthread_beforeqr',
	'ad_showthread_firstpost',
	'ad_showthread_firstpost_start',
	'ad_showthread_firstpost_sig',
	'forumdisplay_loggedinuser',
	'forumrules',
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'im_skype',
	'postbit',
	'postbit_wrapper',
	'postbit_attachment',
	'postbit_attachmentimage',
	'postbit_attachmentthumbnail',
	'postbit_attachmentmoderated',
	'postbit_deleted',
	'postbit_ignore',
	'postbit_ignore_global',
	'postbit_ip',
	'postbit_onlinestatus',
	'postbit_reputation',
	'bbcode_code',
	'bbcode_html',
	'bbcode_php',
	'bbcode_quote',
	'SHOWTHREAD',
	'showthread_list',
	'showthread_similarthreadbit',
	'showthread_similarthreads',
	'showthread_quickreply',
	'showthread_bookmarksite',
	'tagbit',
	'tagbit_wrapper',
	'polloptions_table',
	'polloption',
	'polloption_multiple',
	'pollresults_table',
	'pollresult',
	'threadadmin_imod_menu_post',
	'editor_css',
	'editor_clientscript',
	'editor_jsoptions_font',
	'editor_jsoptions_size',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ####################### PRE-BACK-END ACTIONS ##########################
function exec_postvar_call_back()
{
	global $vbulletin;

	$vbulletin->input->clean_gpc('r', 'goto', TYPE_STR);

	if ($vbulletin->GPC['goto'] == 'newpost' OR $vbulletin->GPC['goto'] == 'postid')
	{
		$vbulletin->noheader = true;
	}
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/class_postbit.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

($hook = vBulletinHook::fetch_hook('showthread_start')) ? eval($hook) : false;

$vbulletin->input->clean_array_gpc('r', array(
	'perpage'    => TYPE_UINT,
	'pagenumber' => TYPE_UINT,
	'highlight'  => TYPE_STR,
	'posted'     => TYPE_BOOL,
));

// *********************************************************************************
// set $threadedmode (continued from global.php)
if ($vbulletin->options['allowthreadedmode'] AND !$show['search_engine'])
{
	if (!isset($threadedmode))
	{
		// Set threaded mode from user options if it doesn't exist in cookie or url passed form
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
	$vbulletin->options['allowthreadedmode'] = false;
	$show['threadedmode'] = false;
	$show['linearmode'] = true;
	$show['hybridmode'] = false;
}

// make an alternate class for the selected threadedmode
$modeclass = array();
for ($i = 0; $i < 3; $i++)
{
	$modeclass["$i"] = iif($i == $threadedmode, 'alt2', 'alt1');
}

// prepare highlight words
if (!empty($vbulletin->GPC['highlight']))
{
	$highlightwords = iif($vbulletin->GPC['goto'], '&', '&amp;') . 'highlight=' . urlencode($vbulletin->GPC['highlight']);
}
else
{
	$highlightwords = '';
}

// ##############################################################################
// ####################### HANDLE HEADER() CALLS ################################
// ##############################################################################
switch($vbulletin->GPC['goto'])
{
	// *********************************************************************************
	// go to next newest
	case 'nextnewest':
		$thread = verify_id('thread', $threadid, 1, 1);
		$forumperms = fetch_permissions($thread['forumid']);

		// remove threads from users on the global ignore list if user is not a moderator
		if ($coventry = fetch_coventry('string') AND !can_moderate($thread['forumid']))
		{
			$globalignore = "AND postuserid NOT IN ($coventry)";
		}
		else
		{
			$globalignore = '';
		}

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
		{
			$limitothers = "AND postuserid = " . $vbulletin->userinfo['userid'] . " AND " . $vbulletin->userinfo['userid'] . " <> 0";
		}
		else
		{
			$limitothers = '';
		}

		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
		{
			$lastpost_info = ",IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost";
			$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
				"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
			$lastpost_having = "HAVING lastpost > $thread[lastpost]";
		}
		else
		{
			$lastpost_info = "";
			$tachyjoin = "";
			$lastpost_having = "AND lastpost > $thread[lastpost]";
		}

		if ($getnextnewest = $db->query_first_slave("
			SELECT thread.threadid
				$lastpost_info
			FROM " . TABLE_PREFIX . "thread AS thread
			$tachyjoin
			WHERE forumid = $thread[forumid]
				AND visible = 1
				AND open <> 10
				$globalignore
				$limitothers
			$lastpost_having
			ORDER BY lastpost
			LIMIT 1
		"))
		{
			$threadid = $getnextnewest['threadid'];
			unset ($thread);
		}
		else
		{
			eval(standard_error(fetch_error('nonextnewest')));
		}
		break;
	// *********************************************************************************
	// go to next oldest
	case 'nextoldest':
		$thread = verify_id('thread', $threadid, 1, 1);
		$forumperms = fetch_permissions($thread['forumid']);

		// remove threads from users on the global ignore list if user is not a moderator
		if ($coventry = fetch_coventry('string') AND !can_moderate($thread['forumid']))
		{
			$globalignore = "AND postuserid NOT IN ($coventry)";
		}
		else
		{
			$globalignore = '';
		}

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
		{
			$limitothers = "AND postuserid = " . $vbulletin->userinfo['userid'] . " AND " . $vbulletin->userinfo['userid'] . " <> 0";
		}
		else
		{
			$limitothers = '';
		}

		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
		{
			$lastpost_info = ",IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost";
			$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
				"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
			$lastpost_having = "HAVING lastpost < $thread[lastpost]";
		}
		else
		{
			$lastpost_info = "";
			$tachyjoin = "";
			$lastpost_having = "AND lastpost < $thread[lastpost]";
		}

		if ($getnextoldest = $db->query_first_slave("
			SELECT thread.threadid
				$lastpost_info
			FROM " . TABLE_PREFIX . "thread AS thread
			$tachyjoin
			WHERE forumid = $thread[forumid]
				AND visible = 1
				AND open <> 10
				$globalignore
				$limitothers
			$lastpost_having
			ORDER BY lastpost DESC
			LIMIT 1
		"))
		{
			$threadid = $getnextoldest['threadid'];
			unset($thread);
		}
		else
		{
			eval(standard_error(fetch_error('nonextoldest')));
		}
		break;
	// *********************************************************************************
	// goto newest unread post
	case 'newpost':
		$threadinfo = verify_id('thread', $threadid, 1, 1);

		if ($vbulletin->userinfo['userid'])
		{
			$vbulletin->userinfo['lastvisit'] = max($threadinfo['threadread'], $threadinfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
		}
		else if (($tview = intval(fetch_bbarray_cookie('thread_lastview', $threadid))) > $vbulletin->userinfo['lastvisit'])
		{
			$vbulletin->userinfo['lastvisit'] = $tview;
		}

		$coventry = fetch_coventry('string');
		$posts = $db->query_first("
			SELECT MIN(postid) AS postid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadinfo[threadid]
				AND visible = 1
				AND dateline > " . intval($vbulletin->userinfo['lastvisit']) . "
				". ($coventry ? "AND userid NOT IN ($coventry)" : "") . "
			LIMIT 1
		");
		if ($posts['postid'])
		{
			exec_header_redirect('showthread.php?' . $vbulletin->session->vars['sessionurl_js'] . "p=$posts[postid]$highlightwords#post$posts[postid]");
		}
		else
		{
			exec_header_redirect('showthread.php?' . $vbulletin->session->vars['sessionurl_js'] . "p=$threadinfo[lastpostid]$highlightwords#post$threadinfo[lastpostid]");
		}
		break;
	// *********************************************************************************
}
// end switch($vbulletin->GPC['goto'])

// *********************************************************************************
// workaround for header redirect issue from forms with enctype in IE
// (use a scrollIntoView javascript call in the <body> onload event)
$onload = '';

// *********************************************************************************
// set $perpage

$perpage = sanitize_maxposts($vbulletin->GPC['perpage']);

// *********************************************************************************
// set post order
if ($vbulletin->userinfo['postorder'] == 0)
{
	$postorder = '';
}
else
{
	$postorder = 'DESC';
}

// *********************************************************************************
// get thread info
$thread = verify_id('thread', $threadid, 1, 1);
$threadinfo =& $thread;

($hook = vBulletinHook::fetch_hook('showthread_getinfo')) ? eval($hook) : false;

// *********************************************************************************
// check for visible / deleted thread
if (((!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))) OR ($thread['isdeleted'] AND !can_moderate($thread['forumid'])))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
}

// *********************************************************************************
// jump page if thread is actually a redirect
if ($thread['open'] == 10)
{
	exec_header_redirect('showthread.php?' . $vbulletin->session->vars['sessionurl_js'] . "t=$thread[pollid]");
}

// *********************************************************************************
// Tachy goes to coventry
if (in_coventry($thread['postuserid']) AND !can_moderate($thread['forumid']))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
}

// *********************************************************************************
// do word wrapping for the thread title
if ($vbulletin->options['wordwrap'] != 0)
{
	$thread['title'] = fetch_word_wrapped_string($thread['title']);
}

$thread['title'] = fetch_censored_text($thread['title']);

// *********************************************************************************
// words to highlight from the search engine
if (!empty($vbulletin->GPC['highlight']))
{

	$highlight = preg_replace('#\*+#s', '*', $vbulletin->GPC['highlight']);
	if ($highlight != '*')
	{
		$regexfind = array('\*', '\<', '\>');
		$regexreplace = array('[\w.:@*/?=]*?', '<', '>');
		$highlight = preg_quote(strtolower($highlight), '#');
		$highlight = explode(' ', $highlight);
		$highlight = str_replace($regexfind, $regexreplace, $highlight);
		foreach ($highlight AS $val)
		{
			if ($val = trim($val))
			{
				$replacewords[] = htmlspecialchars_uni($val);
			}
		}
	}
}

// *********************************************************************************
// make the forum jump in order to fill the forum caches
$curforumid = $thread['forumid'];
construct_forum_jump();

// *********************************************************************************
// get forum info
$forum = fetch_foruminfo($thread['forumid']);
$foruminfo =& $forum;

// *********************************************************************************
// check forum permissions
$forumperms = fetch_permissions($thread['forumid']);
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
{
	print_no_permission();
}
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($thread['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
{
	print_no_permission();
}

// *********************************************************************************
// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

// *********************************************************************************
// get ignored users
$ignore = array();
if (trim($vbulletin->userinfo['ignorelist']))
{
	$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
	foreach ($ignorelist AS $ignoreuserid)
	{
		$ignore["$ignoreuserid"] = 1;
	}
}
DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));

// *********************************************************************************
// filter out deletion notices if can't be seen
if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice'] OR can_moderate($threadinfo['forumid']))
{
	$deljoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND deletionlog.type = 'post')";
}
else
{
	$deljoin = '';
}

$show['viewpost'] = (can_moderate($threadinfo['forumid'])) ? true : false;
$show['managepost'] = iif(can_moderate($threadinfo['forumid'], 'candeleteposts') OR can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);
$show['approvepost'] = (can_moderate($threadinfo['forumid'], 'canmoderateposts')) ? true : false;
$show['managethread'] = (can_moderate($threadinfo['forumid'], 'canmanagethreads')) ? true : false;
$show['approveattachment'] = (can_moderate($threadinfo['forumid'], 'canmoderateattachments')) ? true : false;
$show['inlinemod'] = (!$show['threadedmode'] AND ($show['managethread'] OR $show['managepost'] OR $show['approvepost'])) ? true : false;
$show['spamctrls'] = ($show['inlinemod'] AND $show['managepost']);
$url = $show['inlinemod'] ? SCRIPTPATH : '';

// build inline moderation popup
if ($show['popups'] AND $show['inlinemod'])
{
	eval('$threadadmin_imod_menu_post = "' . fetch_template('threadadmin_imod_menu_post') . '";');
}
else
{
	$threadadmin_imod_menu_post = '';
}

// *********************************************************************************
// find the page that we should be on to display this post
if (!empty($postid) AND $threadedmode == 0)
{
	$postinfo = verify_id('post', $postid, 1, 1);
	$threadid = $postinfo['threadid'];
	$coventry = fetch_coventry('string');

	$getpagenum = $db->query_first("
		SELECT COUNT(*) AS posts
		FROM " . TABLE_PREFIX . "post AS post
		WHERE threadid = $threadid AND visible = 1
		" . ($coventry ? "AND post.userid NOT IN ($coventry)" : '') . "
		AND dateline " . iif(!$postorder, '<=', '>=') . " $postinfo[dateline]
	");
	$vbulletin->GPC['pagenumber'] = ceil($getpagenum['posts'] / $perpage);
}

// *********************************************************************************
// update views counter
if ($vbulletin->options['threadviewslive'])
{
	// doing it as they happen; for optimization purposes, this cannot use a DM!
	$db->shutdown_query("
		UPDATE " . TABLE_PREFIX . "thread
		SET views = views + 1
		WHERE threadid = " . intval($threadinfo['threadid'])
	);
}
else
{
	// or doing it once an hour
	$db->shutdown_query("
		INSERT INTO " . TABLE_PREFIX . "threadviews (threadid)
		VALUES (" . intval($threadinfo['threadid']) . ')'
	);
}

// *********************************************************************************
// display ratings if enabled
$show['rating'] = false;
if ($forum['allowratings'] == 1)
{
	if ($thread['votenum'] > 0)
	{
		$thread['voteavg'] = vb_number_format($thread['votetotal'] / $thread['votenum'], 2);
		$thread['rating'] = intval(round($thread['votetotal'] / $thread['votenum']));

		if ($thread['votenum'] >= $vbulletin->options['showvotes'])
		{
			$show['rating'] = true;
		}
	}

	devdebug("threadinfo[vote] = $threadinfo[vote]");

	if (!empty($threadinfo['vote']))
	{
		$voteselected["$threadinfo[vote]"] = 'selected="selected"';
		$votechecked["$threadinfo[vote]"] = 'checked="checked"';
	}
	else
	{
		$voteselected[0] = 'selected="selected"';
		$votechecked[0] = 'checked="checked"';
	}
}

// *********************************************************************************
// set page number
if ($vbulletin->GPC['pagenumber'] < 1)
{
	$vbulletin->GPC['pagenumber'] = 1;
}
else if ($vbulletin->GPC['pagenumber'] > ceil(($thread['replycount'] + 1) / $perpage))
{
	$vbulletin->GPC['pagenumber'] = ceil(($thread['replycount'] + 1) / $perpage);
}
// *********************************************************************************
// initialise some stuff...
$limitlower = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
$limitupper = ($vbulletin->GPC['pagenumber']) * $perpage;
$counter = 0;
if ($vbulletin->userinfo['userid'])
{
	$threadview = max($threadinfo['threadread'], $threadinfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
}
else
{
	$threadview = intval(fetch_bbarray_cookie('thread_lastview', $thread['threadid']));
	if (!$threadview)
	{
		$threadview = $vbulletin->userinfo['lastvisit'];
	}
}
$threadinfo['threadview'] = intval($threadview);
$displayed_dateline = 0;

################################################################################
############################### SHOW POLL ######################################
################################################################################
$poll = '';
if ($thread['pollid'])
{
	$pollbits = '';
	$counter = 1;
	$pollid = $thread['pollid'];

	$show['editpoll'] = iif(can_moderate($threadinfo['forumid'], 'caneditpoll'), true, false);

	// get poll info
	$pollinfo = $db->query_first_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "poll
		WHERE pollid = $pollid
	");

	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$pollinfo['question'] = $bbcode_parser->parse(unhtmlspecialchars($pollinfo['question']), $forum['forumid'], true);

	$splitoptions = explode('|||', $pollinfo['options']);
	$splitoptions = array_map('rtrim', $splitoptions);

	$splitvotes = explode('|||', $pollinfo['votes']);

	$showresults = 0;
	$uservoted = 0;
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canvote']))
	{
		$nopermission = 1;
	}

	if (!$pollinfo['active'] OR !$thread['open'] OR ($pollinfo['dateline'] + ($pollinfo['timeout'] * 86400) < TIMENOW AND $pollinfo['timeout'] != 0) OR $nopermission)
	{
		//thread/poll is closed, ie show results no matter what
		$showresults = 1;
	}
	else
	{
		//get userid, check if user already voted
		$voted = intval(fetch_bbarray_cookie('poll_voted', $pollid));
		if ($voted)
		{
			$uservoted = 1;
		}
	}

	($hook = vBulletinHook::fetch_hook('showthread_poll_start')) ? eval($hook) : false;

	if ($pollinfo['timeout'] AND !$showresults)
	{
		$pollendtime = vbdate($vbulletin->options['timeformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
		$pollenddate = vbdate($vbulletin->options['dateformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
		$show['pollenddate'] = true;
	}
	else
	{
		$show['pollenddate'] = false;
	}

	foreach ($splitvotes AS $index => $value)
	{
		$pollinfo['numbervotes'] += $value;
	}

	if ($vbulletin->userinfo['userid'] > 0)
	{
		$pollvotes = $db->query_read_slave("
			SELECT voteoption
			FROM " . TABLE_PREFIX . "pollvote
			WHERE userid = " . $vbulletin->userinfo['userid'] . " AND pollid = $pollid
		");
		if ($db->num_rows($pollvotes) > 0)
		{
			$uservoted = 1;
		}
	}

	if ($showresults OR $uservoted)
	{
		if ($uservoted)
		{
			$uservote = array();
			while ($pollvote = $db->fetch_array($pollvotes))
			{
				$uservote["$pollvote[voteoption]"] = 1;
			}
		}
	}

	$option['open'] = $stylevar['left'][0];
	$option['close'] = $stylevar['right'][0];

	foreach ($splitvotes AS $index => $value)
	{
		$arrayindex = $index + 1;
		$option['uservote'] = iif($uservote["$arrayindex"], true, false);
		$option['question'] = $bbcode_parser->parse($splitoptions["$index"], $forum['forumid'], true);

		// public link
		if ($pollinfo['public'] AND $value)
		{
			$option['votes'] = '<a href="poll.php?' . $vbulletin->session->vars['sessionurl'] . 'do=showresults&amp;pollid=' . $pollinfo['pollid'] . '">' . vb_number_format($value) . '</a>';
		}
		else
		{
			$option['votes'] = vb_number_format($value);   //get the vote count for the option
		}

		$option['number'] = $counter;  //number of the option

		if ($value <= 0)
		{
			$option['percentraw'] = 0;
		}
		else if ($pollinfo['multiple'])
		{
			$option['percentraw'] = ($value < $pollinfo['voters']) ? $value / $pollinfo['voters'] * 100 : 100;
		}
		else
		{
			$option['percentraw'] = ($value < $pollinfo['numbervotes']) ? $value / $pollinfo['numbervotes'] * 100 : 100;
		}

		$option['percent'] = vb_number_format($option['percentraw'], 2);

		//Now we check if the user has voted or not
		if ($showresults OR $uservoted)
		{ // user did vote or poll is closed
			$option['graphicnumber'] = $option['number'] % 6 + 1;
			$option['barnumber'] = round($option['percent']) * 2;
			$option['remainder'] = 201 - $option['barnumber'];

			// Phrase parts below
			if ($nopermission)
			{
				$pollstatus = $vbphrase['you_may_not_vote_on_this_poll'];
			}
			else if ($showresults)
			{
				$pollstatus = $vbphrase['this_poll_is_closed'];
			}
			else if ($uservoted)
			{
				$pollstatus = $vbphrase['you_have_already_voted_on_this_poll'];
			}

			($hook = vBulletinHook::fetch_hook('showthread_polloption')) ? eval($hook) : false;

			eval('$pollbits .= "' . fetch_template('pollresult') . '";');
		}
		else
		{
			($hook = vBulletinHook::fetch_hook('showthread_polloption')) ? eval($hook) : false;

			if ($pollinfo['multiple'])
			{
				eval('$pollbits .= "' . fetch_template('polloption_multiple') . '";');
			}
			else
			{
				eval('$pollbits .= "' . fetch_template('polloption') . '";');
			}
		}
		$counter++;
	}

	if ($pollinfo['multiple'])
	{
		$pollinfo['numbervotes'] = $pollinfo['voters'];
		$show['multiple'] = true;
	}

	if ($pollinfo['public'])
	{
		$show['publicwarning'] = true;
	}
	else
	{
		$show['publicwarning'] = false;
	}

	$displayed_dateline = $threadinfo['lastpost'];

	($hook = vBulletinHook::fetch_hook('showthread_poll_complete')) ? eval($hook) : false;

	if ($showresults OR $uservoted)
	{
		eval('$poll = "' . fetch_template('pollresults_table') . '";');
	}
	else
	{
		eval('$poll = "' . fetch_template('polloptions_table') . '";');
	}

}

// work out if quickreply should be shown or not
if (
	$vbulletin->options['quickreply']
	AND
	!$thread['isdeleted'] AND !is_browser('netscape') AND $vbulletin->userinfo['userid']
	AND (
		($vbulletin->userinfo['userid'] == $threadinfo['postuserid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown'])
		OR
		($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])
	)
	AND ($thread['open'] OR can_moderate($threadinfo['forumid'], 'canopenclose'))
	AND (!fetch_require_hvcheck('post'))
)
{
	$show['quickreply'] = true;
}
else
{
	$show['quickreply'] = false;
	$show['wysiwyg'] = 0;
	$quickreply = '';
}
$show['largereplybutton'] = (!$thread['isdeleted'] AND !$show['threadedmode'] AND $forum['allowposting'] AND !$show['search_engine']);
if (!$forum['allowposting'])
{
	$show['quickreply'] = false;
}

$show['multiquote_global'] = ($vbulletin->options['multiquote'] AND $vbulletin->userinfo['userid']);
if ($show['multiquote_global'])
{
	$vbulletin->input->clean_array_gpc('c', array(
		'vbulletin_multiquote' => TYPE_STR
	));
	$vbulletin->GPC['vbulletin_multiquote'] = explode(',', $vbulletin->GPC['vbulletin_multiquote']);
}

// post is cachable if option is enabled, last post is newer than max age, and this user
// isn't showing a sessionhash
$post_cachable = (
	$vbulletin->options['cachemaxage'] > 0 AND
	(TIMENOW - ($vbulletin->options['cachemaxage'] * 60 * 60 * 24)) <= $thread['lastpost'] AND
	$vbulletin->session->vars['sessionurl'] == ''
);
$saveparsed = '';
$save_parsed_sigs = '';

($hook = vBulletinHook::fetch_hook('showthread_post_start')) ? eval($hook) : false;

################################################################################
####################### SHOW THREAD IN LINEAR MODE #############################
################################################################################
if ($threadedmode == 0)
{
	// allow deleted posts to not be counted in number of posts displayed on the page;
	// prevents issue with page count on forum display being incorrect
	$ids = array(0);
	$lastpostid = 0;

	$hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('showthread_query_postids')) ? eval($hook) : false;

	if (empty($deljoin) AND !$show['approvepost'])
	{
		$totalposts = $threadinfo['replycount'] + 1;

		if (can_moderate($thread['forumid']))
		{
			$coventry = '';
		}
		else
		{
			$coventry = fetch_coventry('string');
		}

		$calc_found_rows = '';
		if(!empty($hook_query_where))
		{
			$calc_found_rows = 'SQL_CALC_FOUND_ROWS';
		}

		$getpostids = $db->query_read("
			SELECT $calc_found_rows post.postid, post.attach
			FROM " . TABLE_PREFIX . "post AS post
			$hook_query_joins
			WHERE post.threadid = $threadid
				AND post.visible = 1
				" . ($coventry ? "AND post.userid NOT IN ($coventry)" : '') . "
				$hook_query_where
			ORDER BY post.dateline $postorder
			LIMIT $limitlower, $perpage
		");

		if(!empty($hook_query_where))
		{
			$calc_found_rows_array = $db->query_first("SELECT FOUND_ROWS() AS found_rows");
			$totalposts = $calc_found_rows_array['found_rows'];
		}

		while ($post = $db->fetch_array($getpostids))
		{
			if (!isset($qrfirstpostid))
			{
				$qrfirstpostid = $post['postid'];
			}
			$qrlastpostid = $post['postid'];
			$ids[] = $post['postid'];
		}
		$db->free_result($getpostids);

		$lastpostid = $qrlastpostid;
	}
	else
	{
		$getpostids = $db->query_read("
			SELECT post.postid, post.visible, post.userid
			FROM " . TABLE_PREFIX . "post AS post
			$hook_query_joins
			WHERE post.threadid = $threadid
				AND post.visible IN (1
				" . (!empty($deljoin) ? ",2" : "") . "
				" . ($show['approvepost'] ? ",0" : "") . "
				)
				$hook_query_where
			ORDER BY post.dateline $postorder
		");
		$totalposts = 0;
		if ($limitlower != 0)
		{
			$limitlower++;
		}
		while ($post = $db->fetch_array($getpostids))
		{
			if (!isset($qrfirstpostid))
			{
				$qrfirstpostid = $post['postid'];
			}
			$qrlastpostid = $post['postid'];
			if ($post['visible'] == 1 AND !in_coventry($post['userid']))
			{
				$totalposts++;
			}
			if ($totalposts < $limitlower OR $totalposts > $limitupper)
			{
				continue;
			}

			// remember, these are only added if they're going to be displayed
			$ids[] = $post['postid'];
			$lastpostid = $post['postid'];
		}
		$db->free_result($getpostids);
	}
	$postids = "post.postid IN (" . implode(',', $ids) . ")";

	// load attachments
	if ($thread['attach'])
	{
		$attachments = $db->query_read("
			SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
				postid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
				attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
			FROM " . TABLE_PREFIX . "attachment
			LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
			WHERE postid IN (" . implode(',', $ids) . ")
			ORDER BY attachmentid
		");
		$postattach = array();
		while ($attachment = $db->fetch_array($attachments))
		{
			if (!$attachment['build_thumbnail'])
			{
				$attachment['hasthumbnail'] = false;
			}
			$postattach["$attachment[postid]"]["$attachment[attachmentid]"] = $attachment;
		}
	}

	$hook_query_fields = $hook_query_joins = '';
	($hook = vBulletinHook::fetch_hook('showthread_query')) ? eval($hook) : false;

	$posts = $db->query_read("
		SELECT
			post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
			user.*, userfield.*, usertextfield.*,
			" . iif($forum['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
			" . iif($vbulletin->options['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight,') . "
			" . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? 'spamlog.postid AS spamlog_postid,' : '') . "
			" . iif($deljoin, 'deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason,') . "
			editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
			editlog.reason AS edit_reason, editlog.hashistory,
			postparsed.pagetext_html, postparsed.hasimages,
			sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
			sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
			IF(user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
			" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
		" . iif($forum['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
		" . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
		" . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? "LEFT JOIN " . TABLE_PREFIX . "spamlog AS spamlog ON(spamlog.postid = post.postid)" : '') . "
			$deljoin
		LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
		LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
			$hook_query_joins
		WHERE $postids
		ORDER BY post.dateline $postorder
	");

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canseethumbnails']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
	{
		$vbulletin->options['attachthumbs'] = 0;
	}

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
	{
		$vbulletin->options['viewattachedimages'] = 0;
	}

	$postcount = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
	if ($postorder)
	{
		// Newest first
		$postcount = $totalposts - $postcount + 1;
	}

	$counter = 0;
	$postbits = '';

	$postbit_factory = new vB_Postbit_Factory();
	$postbit_factory->registry =& $vbulletin;
	$postbit_factory->forum =& $foruminfo;
	$postbit_factory->thread =& $thread;
	$postbit_factory->cache = array();
	$postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	while ($post = $db->fetch_array($posts))
	{
		if ($tachyuser = in_coventry($post['userid']) AND !can_moderate($thread['forumid']))
		{
			continue;
		}

		if ($post['visible'] == 1 AND !$tachyuser)
		{
			++$counter;
			if ($postorder)
			{
				$post['postcount'] = --$postcount;
			}
			else
			{
				$post['postcount'] = ++$postcount;
			}
		}

		if ($tachyuser)
		{
			$fetchtype = 'post_global_ignore';
		}
		else if (!empty($ignore["$post[userid]"]))
		{
			$fetchtype = 'post_ignore';
		}
		else if ($post['visible'] == 2)# OR ($thread['visible'] == 2 AND $postcount == 1))
		{
			$fetchtype = 'post_deleted';
		}
		else
		{
			$fetchtype = 'post';
		}

		($hook = vBulletinHook::fetch_hook('showthread_postbit_create')) ? eval($hook) : false;

		$postbit_obj = $postbit_factory->fetch_postbit($fetchtype);
		if ($fetchtype == 'post')
		{
			$postbit_obj->highlight =& $replacewords;
		}
		$postbit_obj->cachable = $post_cachable;

		$post['islastshown'] = ($post['postid'] == $lastpostid);
		$post['isfirstshown'] = ($counter == 1 AND $fetchtype == 'post' AND $post['visible'] == 1);
		$post['attachments'] =& $postattach["$post[postid]"];

		$parsed_postcache = array('text' => '', 'images' => 1, 'skip' => false);

		$postbits .= $postbit_obj->construct_postbit($post);

		// Only show after the first post, counter isn't incremented for deleted/moderated posts
		if ($post['isfirstshown'])
		{
			eval('$postbits .= "' . fetch_template('ad_showthread_firstpost') . '";');
		}

		if ($post_cachable AND $post['pagetext_html'] == '')
		{
			if (!empty($saveparsed))
			{
				$saveparsed .= ',';
			}
			$saveparsed .= "($post[postid], " . intval($thread['lastpost']) . ', ' . intval($postbit_obj->post_cache['has_images']) . ", '" . $db->escape_string($postbit_obj->post_cache['text']) . "', " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ")";
		}

		if (!empty($postbit_obj->sig_cache) AND $post['userid'])
		{
			if (!empty($save_parsed_sigs))
			{
				$save_parsed_sigs .= ',';
			}
			$save_parsed_sigs .= "($post[userid], " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ", '" . $db->escape_string($postbit_obj->sig_cache['text']) . "', " . intval($postbit_obj->sig_cache['has_images']) . ")";
		}

		// get first and last post ids for this page (for big reply buttons)
		if (!isset($FIRSTPOSTID))
		{
			$FIRSTPOSTID = $post['postid'];
		}
		$LASTPOSTID = $post['postid'];

		if ($post['dateline'] > $displayed_dateline)
		{
			$displayed_dateline = $post['dateline'];
			if ($displayed_dateline <= $threadview)
			{
				$updatethreadcookie = true;
			}
		}
	}
	$db->free_result($posts);
	unset($post);

	if ($postbits == '' AND $vbulletin->GPC['pagenumber'] > 1)
	{
		exec_header_redirect(
			'showthread.php?' . $vbulletin->session->vars['sessionurl_js'] . "t=$threadid&page=" . ($vbulletin->GPC['pagenumber'] - 1) .
			(!empty($vbulletin->GPC['perpage']) ? "&pp=$perpage" : "") .
			"$highlightwords"
		);
	}

	DEVDEBUG("First Post: $FIRSTPOSTID; Last Post: $LASTPOSTID");

	$pagenav = construct_page_nav($vbulletin->GPC['pagenumber'], $perpage, $totalposts, "showthread.php?" . $vbulletin->session->vars['sessionurl'] . "t=$threadid", ""
		. (!empty($vbulletin->GPC['perpage']) ? "&amp;pp=$perpage" : "")
		. "$highlightwords"
	);

	if ($thread['lastpost'] > $threadview)
	{
		if ($firstnew)
		{
			$firstunread = '#post' . $firstnew;
			$show['firstunreadlink'] = true;
		}
		else
		{
			$firstunread = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . 't=' . $threadid . '&amp;goto=newpost';
			$show['firstunreadlink'] = true;
		}
	}
	else
	{
		$firstunread = '';
		$show['firstunreadlink'] = false;
	}

	if ($vbulletin->userinfo['postorder'])
	{
		// disable ajax qr when displaying linear newest first
		$show['allow_ajax_qr'] = 0;
	}
	else
	{
		// only allow ajax on the last page of a thread when viewing oldest first
		$show['allow_ajax_qr'] = (($vbulletin->GPC['pagenumber'] == ceil($totalposts / $perpage)) ? 1 : 0);
	}

################################################################################
################ SHOW THREAD IN THREADED OR HYBRID MODE ########################
################################################################################
}
else
{
	// ajax qr doesn't work with threaded controls
	$show['allow_ajax_qr'] = 0;

	require_once(DIR . '/includes/functions_threadedmode.php');

	// save data
	$ipostarray = array();
	$postarray = array();
	$userarray = array();
	$postparent = array();
	$postorder = array();
	$hybridposts = array();
	$deletedparents = array();
	$totalposts = 0;
	$links = '';
	$cache_postids = array();

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('showthread_query_postids_threaded')) ? eval($hook) : false;

	// get all posts
	$listposts = $db->query_read("
		SELECT
			post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
			user.*, userfield.*
			" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
		$hook_query_joins
		WHERE threadid = $threadid
			$hook_query_where
		ORDER BY postid
	");

	// $toppostid is the first post in the thread
	// $curpostid is the postid passed from the URL, or if not specified, the first post in the thread
	$ids = array(0);
	while ($post = $db->fetch_array($listposts))
	{
		if (($post['visible'] == 2 AND !$deljoin) OR ($post['visible'] == 0 AND !$show['approvepost']) OR (in_coventry($post['userid']) AND !can_moderate($thread['forumid'])))
		{
			$deletedparents["$post[postid]"] = iif(isset($deletedparents["$post[parentid]"]), $deletedparents["$post[parentid]"], $post['parentid']);
			continue;
		}

		if (empty($toppostid))
		{
			$toppostid = $post['postid'];
		}
		if (empty($postid))
		{
			if (empty($curpostid))
			{
				$curpostid = $post['postid'];
				if ($threadedmode == 2 AND empty($vbulletin->GPC['postid']))
				{
					$vbulletin->GPC['postid'] = $curpostid;
				}
				$curpostparent = $post['parentid'];
			}
		}
		else
		{
			if ($post['postid'] == $postid)
			{
				$curpostid = $post['postid'];
				$curpostparent = $post['parentid'];
			}
		}

		$postparent["$post[postid]"] = $post['parentid'];
		$ipostarray["$post[parentid]"][] = $post['postid'];
		$postarray["$post[postid]"] = $post;
		$userarray["$post[userid]"] = $db->escape_string($post['username']);

		$totalposts++;
		$ids[] = $post['postid'];
	}
	$db->free_result($listposts);

	// hooks child posts up to new parent if actual parent has been deleted or hidden
	if (count($deletedparents) > 0)
	{
		foreach ($deletedparents AS $dpostid => $dparentid)
		{

			if (is_array($ipostarray[$dpostid]))
			{
				foreach ($ipostarray[$dpostid] AS $temppostid)
				{
					$postparent[$temppostid] = $dparentid;
					$ipostarray[$dparentid][] = $temppostid;
					$postarray[$temppostid]['parentid'] = $dparentid;
				}
				unset($ipostarray[$dpostid]);
			}

			if ($curpostparent == $dpostid)
			{
				$curpostparent = $dparentid;
			}
		}
	}

	unset($post, $listposts, $deletedparents);

	if ($thread['attach'])
	{
		$postattach = array();
		$attachments = $db->query_read("
			SELECT dateline, thumbnail_dateline,filename, filesize, visible, attachmentid, counter,
				postid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
				attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
			FROM " . TABLE_PREFIX . "attachment
			LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
			WHERE postid IN (" . implode(',', $ids) . ")
		");
		while ($attachment = $db->fetch_array($attachments))
		{
			if (!$attachment['build_thumbnail'])
			{
				$attachment['hasthumbnail'] = false;
			}
			$postattach["$attachment[postid]"]["$attachment[attachmentid]"] = $attachment;
		}
	}

	// get list of usernames from post list
	$userjs = '';
	foreach ($userarray AS $userid => $username)
	{
		if ($userid)
		{
			$userjs .= "pu[$userid] = \"" . addslashes_js($username) . "\";\n";
		}
	}
	unset($userarray, $userid, $username);

	$parent_postids = fetch_post_parentlist($curpostid);
	if (!$parent_postids)
	{
		$currentdepth = 0;
	}
	else
	{
		$currentdepth = sizeof(explode(',', $parent_postids));
	}

	sort_threaded_posts();

	if (empty($curpostid))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
	}

	if ($threadedmode == 2) // hybrid display mode
	{
		$numhybrids = sizeof($hybridposts);

		if ($vbulletin->GPC['pagenumber'] < 1)
		{
			$vbulletin->GPC['pagenumber'] = 1;
		}
		$startat = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
		if ($startat > $numhybrids)
		{
			$vbulletin->GPC['pagenumber'] = 1;
			$startat = 0;
		}
		$endat = $startat + $perpage;
		for ($i = $startat; $i < $endat; $i++)
		{
			if (isset($hybridposts["$i"]))
			{
				if (!isset($FIRSTPOSTID))
				{
					$FIRSTPOSTID = $hybridposts["$i"];
				}
				$cache_postids[] = $hybridposts[$i];
				$LASTPOSTID = $hybridposts["$i"];
			}
		}
		$pagenav = construct_page_nav($vbulletin->GPC['pagenumber'], $perpage, $numhybrids, 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . 'p=' . $vbulletin->GPC['postid'], ""
			. (!empty($vbulletin->GPC['perpage']) ? "&amp;pp=$perpage" : "")
			. "$highlightwords"
		);

	}
	else // threaded display mode
	{
		$FIRSTPOSTID = $curpostid;
		$LASTPOSTID = $curpostid;

		// sort out which posts to cache:
		if (!$vbulletin->options['threaded_maxcache'])
		{
			$vbulletin->options['threaded_maxcache'] = 999999;
		}

		// cache $vbulletin->options['threaded_maxcache'] posts
		// take 0.25 from above $curpostid
		// and take 0.75 below
		if (sizeof($postorder) <= $vbulletin->options['threaded_maxcache']) // cache all, thread is too small!
		{
			$startat = 0;
		}
		else
		{
			if (($curpostidkey + ($vbulletin->options['threaded_maxcache'] * 0.75)) > sizeof($postorder))
			{
				$startat = sizeof($postorder) - $vbulletin->options['threaded_maxcache'];
			}
			else if (($curpostidkey - ($vbulletin->options['threaded_maxcache'] * 0.25)) < 0)
			{
				$startat = 0;
			}
			else
			{
				$startat = intval($curpostidkey - ($vbulletin->options['threaded_maxcache'] * 0.25));
			}
		}
		unset($curpostidkey);

		foreach ($postorder AS $postkey => $postid)
		{
			if ($postkey > ($startat + $vbulletin->options['threaded_maxcache'])) // got enough entries now
			{
				break;
			}
			if ($postkey >= $startat AND empty($morereplies["$postid"]))
			{
				$cache_postids[] = $postid;
			}
		}

		// get next/previous posts for each post in the list
		// key: NAVJS[postid][0] = prev post, [1] = next post
		$NAVJS = array();
		$prevpostid = 0;
		foreach ($postorder AS $postid)
		{
			$NAVJS["$postid"][0] = $prevpostid;
			$NAVJS["$prevpostid"][1] = $postid;
			$prevpostid = $postid;
		}
		$NAVJS["$toppostid"][0] = $postid; //prev button for first post
		$NAVJS["$postid"][1] = $toppostid; //next button for last post

		$navjs = '';
		foreach ($NAVJS AS $postid => $info)
		{
			$navjs .= "pn[$postid] = \"$info[0],$info[1]\";\n";
		}

	}

	unset($ipostarray, $postparent, $postorder, $NAVJS, $postid, $info, $prevpostid, $postkey);

	if (empty($cache_postids))
	{
		// umm... something weird happened. Just prevent an error.
		eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('showthread_query')) ? eval($hook) : false;

	$cacheposts = $db->query_read("
		SELECT
			post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
			user.*, userfield.*, usertextfield.*,
			" . iif($forum['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
			" . iif($vbulletin->options['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,') . "
			" . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? 'spamlog.postid AS spamlog_postid,' : '') . "
			" . iif($deljoin, "deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason,") . "
			editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
			editlog.reason AS edit_reason, editlog.hashistory,
			postparsed.pagetext_html, postparsed.hasimages,
			sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
			sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
			IF(user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
			" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
		" . iif($forum['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
		" . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
		" . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? "LEFT JOIN " . TABLE_PREFIX . "spamlog AS spamlog ON(spamlog.postid = post.postid)" : '') . "
			$deljoin
		LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
		LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
			$hook_query_joins
		WHERE post.postid IN (" . implode(',', $cache_postids) . ") $hook_query_where
	");

	// re-initialise the $postarray variable
	$postarray = array();
	while ($post = $db->fetch_array($cacheposts))
	{
		$postarray["$post[postid]"] = $post;
	}

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
	{
		$vbulletin->options['viewattachedimages'] = 0;
		$vbulletin->options['attachthumbs'] = 0;
	}

	// init
	$postcount = 0;
	$postbits = '';
	$saveparsed = '';
	$jspostbits = '';

	$postbit_factory = new vB_Postbit_Factory();
	$postbit_factory->registry =& $vbulletin;
	$postbit_factory->forum =& $foruminfo;
	$postbit_factory->thread =& $thread;
	$postbit_factory->cache = array();
	$postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	foreach ($cache_postids AS $id)
	{
		// get the post from the post array
		if (!isset($postarray["$id"]))
		{
			continue;
		}
		$post = $postarray["$id"];

		if ($tachyuser = in_coventry($post['userid']) AND !can_moderate($thread['forumid']))
		{
			continue;
		}
		if ($tachyuser)
		{
			$fetchtype = 'post_global_ignore';
		}
		else if ($ignore["$post[userid]"])
		{
			$fetchtype = 'post_ignore';
		}
		else if ($post['visible'] == 2) #OR ($thread['visible'] == 2 AND $postcount == 0))
		{
			$fetchtype = 'post_deleted';
		}
		else
		{
			$fetchtype = 'post';
		}

		($hook = vBulletinHook::fetch_hook('showthread_postbit_create')) ? eval($hook) : false;

		$postbit_obj = $postbit_factory->fetch_postbit($fetchtype);
		if ($fetchtype == 'post')
		{
			$postbit_obj->highlight =& $replacewords;
		}
		$postbit_obj->cachable = $post_cachable;

		$post['postcount'] = ++$postcount;
		$post['attachments'] =& $postattach["$post[postid]"];

		$parsed_postcache = array('text' => '', 'images' => 1);

		$bgclass = 'alt2';
		if ($threadedmode == 2) // hybrid display mode
		{
			$postbits .= $postbit_obj->construct_postbit($post);
		}
		else // threaded display mode
		{
			$postbit = $postbit_obj->construct_postbit($post);

			if ($curpostid == $post['postid'])
			{
				$curpostdateline = $post['dateline'];
				$curpostbit = $postbit;
			}
			$postbit = preg_replace('#</script>#i', "<\\/scr' + 'ipt>", addslashes_js($postbit));
			$jspostbits .= "pd[$post[postid]] = '$postbit';\n";

		} // end threaded mode

		if ($post_cachable AND $post['pagetext_html'] == '')
		{
			if (!empty($saveparsed))
			{
				$saveparsed .= ',';
			}
			$saveparsed .= "($post[postid], " . intval($thread['lastpost']) . ', ' . intval($postbit_obj->post_cache['has_images']) . ", '" . $db->escape_string($postbit_obj->post_cache['text']) . "'," . intval(STYLEID) . ", " . intval(LANGUAGEID) . ")";
		}

		if (!empty($postbit_obj->sig_cache) AND $post['userid'])
		{
			if (!empty($save_parsed_sigs))
			{
				$save_parsed_sigs .= ',';
			}
			$save_parsed_sigs .= "($post[userid], " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ", '" . $db->escape_string($postbit_obj->sig_cache['text']) . "', " . intval($postbit_obj->sig_cache['has_images']) . ")";
		}

		if ($post['dateline'] > $displayed_dateline)
		{
			$displayed_dateline = $post['dateline'];
			if ($displayed_dateline <= $threadview)
			{
				$updatethreadcookie = true;
			}
		}

	} // end while ($post)
	$db->free_result($cacheposts);

	if ($threadedmode == 1)
	{
		$postbits = $curpostbit;
	}

	if (!preg_match('#[^0-9]#', $stylevar['outertablewidth']))
	{
		$postlistwidth = $stylevar['outertablewidth'] - 2 * ($stylevar['spacersize'] + $stylevar['cellpadding'] + $stylevar['cellspacing'] + 3);
		$postlistwidth .= 'px';
	}
	else
	{
		$postlistwidth = $stylevar['outertablewidth'];
	}

	if ($postlistwidth)
	{
		if (is_browser('ie'))
		{
			$show['postlistwidth'] = true;
		}
		else if (!preg_match('#[^0-9]#', $stylevar['outertablewidth']))
		{
			$show['postlistwidth'] = true;
		}
	}

	eval('$threadlist = "' . fetch_template('showthread_list') . '";');
	unset($curpostbit, $post, $cacheposts, $parsed_postcache, $postbit);

}

################################################################################
########################## END LINEAR / THREADED ###############################
################################################################################

$effective_lastpost = max($displayed_dateline, $thread['lastpost']);


// *********************************************************************************
//set thread last view
if ($thread['pollid'] AND $vbulletin->options['updatelastpost'] AND ($displayed_dateline == $thread['lastpost'] OR $threadview == $thread['lastpost']) AND $pollinfo['lastvote'] > $thread['lastpost'])
{
	$displayed_dateline = $pollinfo['lastvote'];
}

if ((!$vbulletin->GPC['posted'] OR $updatethreadcookie) AND $displayed_dateline AND $displayed_dateline > $threadview)
{
	mark_thread_read($threadinfo, $foruminfo, $vbulletin->userinfo['userid'], $displayed_dateline);
}

if ($db->explain)
{
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$aftertime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	echo "Time after parsing all posts:  $aftertime\n";
	if (function_exists('memory_get_usage'))
	{
		echo "Memory After: " . number_format((memory_get_usage() / 1024)) . 'KB' . " \n";
	}
	echo "\n<hr />\n\n";
}

// *********************************************************************************
// save parsed post HTML
if (!empty($saveparsed))
{
	$db->shutdown_query("
		REPLACE INTO " . TABLE_PREFIX . "postparsed (postid, dateline, hasimages, pagetext_html, styleid, languageid)
		VALUES $saveparsed
	");
	unset($saveparsed);
}
if (!empty($save_parsed_sigs))
{
	$db->shutdown_query("
		REPLACE INTO " . TABLE_PREFIX . "sigparsed (userid, styleid, languageid, signatureparsed, hasimages)
		VALUES $save_parsed_sigs
	");
	unset($save_parsed_sigs);
}

// *********************************************************************************
// prepare tags
$show['tag_box'] = false;

if ($vbulletin->options['threadtagging'])
{
	$tag_list = fetch_tagbits($thread);

	if (!$foruminfo['allowposting'])
	{
		// forum closed - tags can't be added, so only show edit if have tags
		$show['manage_tag'] = ($thread['taglist'] AND can_moderate($thread['forumid'], 'caneditthreads'));
	}
	else if (!$thread['open'] AND !can_moderate($thread['forumid'], 'canopenclose'))
	{
		// thread is closed and can't be opened by this person;
		$show['manage_tag'] = can_moderate($thread['forumid'], 'caneditthreads');
	}
	else
	{
		$show['manage_tag'] = (
			(($forumperms & $vbulletin->bf_ugp_forumpermissions['cantagown']) AND $thread['postuserid'] == $vbulletin->userinfo['userid'])
			OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['cantagothers'])
			OR (($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletetagown']) AND $thread['postuserid'] == $vbulletin->userinfo['userid'])
			OR can_moderate($thread['forumid'], 'caneditthreads')
		);
	}

	$show['tag_box'] = ($show['manage_tag'] OR $thread['taglist']);
}

// *********************************************************************************
// Get users browsing this thread
if (($vbulletin->options['showthreadusers'] == 1 OR $vbulletin->options['showthreadusers'] == 2 OR ($vbulletin->options['showthreadusers'] > 2 AND $vbulletin->userinfo['userid'])) AND !$show['search_engine'])
{
	$datecut = TIMENOW - $vbulletin->options['cookietimeout'];
	$browsers = '';

	$show['activeusers'] = iif(!$show['search_engine'], true, false);

	// Don't put the inthread value in the WHERE clause as it might not be the newest location!
	$threadusers = $db->query_read_slave("
		SELECT user.username, user.usergroupid, user.membergroupids,
			session.userid, session.inthread, session.lastactivity, session.badlocation,
			IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid,
			IF(user.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ", 1, 0) AS invisible
		FROM " . TABLE_PREFIX . "session AS session
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = session.userid)
		WHERE session.lastactivity > $datecut
		ORDER BY " . iif($vbulletin->options['showthreadusers'] == 1 OR $vbulletin->options['showthreadusers'] == 3, " username ASC,") . " lastactivity DESC
	");

	$numberguest = 0;
	$numberregistered = 0;
	$doneuser = array();

	if ($vbulletin->userinfo['userid']) // fakes the user being in this thread
	{
		$loggedin = array(
			'userid'        => $vbulletin->userinfo['userid'],
			'username'      => $vbulletin->userinfo['username'],
			'invisible'     => $vbulletin->userinfo['invisible'],
			'invisiblemark' => $vbulletin->userinfo['invisiblemark'],
			'inthread'      => $threadinfo['threadid'],
			'lastactivity'  => TIMENOW,
			'musername'     => $vbulletin->userinfo['musername'],
		);
		$numberregistered = 1;
		$numbervisible = 1;
		fetch_online_status($loggedin);

		$show['comma_leader'] = false;
		eval('$activeusers = "' . fetch_template('forumdisplay_loggedinuser') . '";');
		$doneuser["{$vbulletin->userinfo['userid']}"] = 1;
	}

	// this requires the query to have lastactivity ordered by DESC so that the latest location will be the first encountered.
	while ($loggedin = $db->fetch_array($threadusers))
	{
		if ($loggedin['badlocation'])
		{
			continue;
		}

		if (empty($doneuser["$loggedin[userid]"]))
		{
			if ($loggedin['inthread'] == $threadinfo['threadid'])
			{
				if ($loggedin['userid'] == 0) // Guest
				{
					$numberguest++;
				}
				else
				{
					fetch_musername($loggedin);
					$numberregistered++;

					($hook = vBulletinHook::fetch_hook('showthread_loggedinuser')) ? eval($hook) : false;

					if (fetch_online_status($loggedin))
					{
						$show['comma_leader'] = ($activeusers != '');
						eval('$activeusers .= "' . fetch_template('forumdisplay_loggedinuser') . '";');
					}
				}
			}
			if ($loggedin['userid'])
			{
				$doneuser["$loggedin[userid]"] = 1;
			}
		}
	}

	if (!$vbulletin->userinfo['userid'])
	{
		$numberguest = ($numberguest == 0) ? 1 : $numberguest;
		if ($numberregistered == 0)
		{
			$activeusers = '&nbsp;';
		}
	}
	$totalonline = $numberregistered + $numberguest;

	$db->free_result($threadusers);
	unset($userinfos, $userid, $userinfo, $loggedin, $threadusers, $datecut);
}

// *********************************************************************************
// get similar threads
if ($vbulletin->options['showsimilarthreads'] AND $thread['similar'])
{
	// don't show similar threads from coventry
	if ($coventry = fetch_coventry('string'))
	{
		$globalignore = "AND thread.postuserid NOT IN ($coventry)";
	}
	else
	{
		$globalignore = '';
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('showthread_similarthread_query')) ? eval($hook) : false;

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
	{
		$tachyselect = "
			IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost,
			IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount) AS replycount
		";
		$tachyjoin = "
			LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON
				(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "tachythreadcounter AS tachythreadcounter ON
				(tachythreadcounter.threadid = thread.threadid AND tachythreadcounter.userid = " . $vbulletin->userinfo['userid'] . ")
		";
	}
	else
	{
		$tachyselect = "thread.lastpost, thread.replycount";
		$tachyjoin = "";
	}

	$simthrds = $db->query_read_slave("
		SELECT thread.threadid, thread.forumid, thread.title, thread.prefixid, thread.taglist, postusername, postuserid,
			$tachyselect,
			forum.title AS forumtitle
			" . iif($vbulletin->options['threadpreview'], ",post.pagetext AS preview") . "
			" . iif($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid'], ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed") . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "thread AS thread
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = thread.forumid)
		" . iif($vbulletin->options['threadpreview'], "LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = thread.firstpostid)") . "
		" . iif($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON (subscribethread.threadid = thread.threadid AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . " AND canview = 1)") . "
		$hook_query_joins
		$tachyjoin
		WHERE thread.threadid IN ($thread[similar]) AND thread.visible = 1
			" . iif (($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']) OR can_moderate($forumid), '', "AND forum.password = ''") . "
			$globalignore
			$hook_query_where
		ORDER BY lastpost DESC
	");

	$similarthreadbits = '';
	$forum_active_cache = array();
	while ($simthread = $db->fetch_array($simthrds))
	{
		if (!isset($forum_active_cache["$simthread[forumid]"]))
		{
			$current_forum = $vbulletin->forumcache["$simthread[forumid]"];
			while (!empty($current_forum))
			{
				if (!($current_forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
				{
					// all children of this forum should be hidden now
					$forum_children = explode(',', trim($current_forum['childlist']));
					foreach ($forum_children AS $forumid)
					{
						if ($forumid == '-1')
						{
							continue;
						}
						$forum_active_cache["$forumid"] = false;
					}
					break;
				}

				$forum_active_cache["$current_forum[forumid]"] = true;
				$current_forum = $vbulletin->forumcache["$current_forum[parentid]"];
			}
		}

		if (!$forum_active_cache["$simthread[forumid]"])
		{
			continue;
		}

		$fperms = fetch_permissions($simthread['forumid']);
		if (($fperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND
			(($fperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) OR ($vbulletin->userinfo['userid'] != 0 AND $simthread['postuserid'] == $vbulletin->userinfo['userid']))
		)
		{
			// format thread preview if there is one
			if ($ignore["$simthread[postuserid]"])
			{
				$simthread['preview'] = '';
			}
			else if (isset($simthread['preview']) AND $vbulletin->options['threadpreview'] > 0)
			{
				$simthread['preview'] = strip_quotes($simthread['preview']);
				$simthread['preview'] = htmlspecialchars_uni(fetch_trimmed_title(strip_bbcode($simthread['preview'], false, true), $vbulletin->options['threadpreview']));
			}

			$simthread['lastreplydate'] = vbdate($vbulletin->options['dateformat'], $simthread['lastpost'], true);
			$simthread['lastreplytime'] = vbdate($vbulletin->options['timeformat'], $simthread['lastpost']);

			if ($simthread['prefixid'])
			{
				$simthread['prefix_plain_html'] = htmlspecialchars_uni($vbphrase["prefix_$simthread[prefixid]_title_plain"]);
				$simthread['prefix_rich'] = $vbphrase["prefix_$simthread[prefixid]_title_rich"];
			}
			else
			{
				$simthread['prefix_plain_html'] = '';
				$simthread['prefix_rich'] = '';
			}

			$simthread['title'] = fetch_censored_text($simthread['title']);

			($hook = vBulletinHook::fetch_hook('showthread_similarthreadbit')) ? eval($hook) : false;

			eval('$similarthreadbits .= "' . fetch_template('showthread_similarthreadbit') . '";');
		}
	}
	if ($similarthreadbits)
	{
		eval('$similarthreads = "' . fetch_template('showthread_similarthreads') . '";');
	}
	else
	{
		$similarthreads = '';
	}
	unset($similarthreadbits);
}
else
{
	$similarthreads = '';
}

// *********************************************************************************
// build quick reply if appropriate
if ($show['quickreply'])
{
	require_once(DIR . '/includes/functions_editor.php');

	$show['wysiwyg'] = ($forum['allowbbcode'] ? is_wysiwyg_compatible() : 0);
	$istyles_js = construct_editor_styles_js();

	// set show signature hidden field
	$showsig = iif($vbulletin->userinfo['signature'], 1, 0);

	// set quick reply initial id
	if ($threadedmode == 1)
	{
		$qrpostid = $curpostid;
		$show['qr_require_click'] = 0;
	}
	else if ($vbulletin->options['quickreply'] == 2)
	{
		$qrpostid = 0;
		$show['qr_require_click'] = 1;
	}
	else
	{
		$qrpostid = 'who cares';
		$show['qr_require_click'] = 0;
	}

	$editorid = construct_edit_toolbar('', 0, $foruminfo['forumid'], ($foruminfo['allowsmilies'] ? 1 : 0), 1, false, 'qr');
	$messagearea = "
		<script type=\"text/javascript\">
		<!--
			var threaded_mode = $threadedmode;
			var require_click = $show[qr_require_click];
			var is_last_page = $show[allow_ajax_qr]; // leave for people with cached JS files
			var allow_ajax_qr = $show[allow_ajax_qr];
			var ajax_last_post = " . intval($effective_lastpost) . ";
		// -->
		</script>
		$messagearea
	";

	if (is_browser('mozilla') AND $show['wysiwyg'] == 2)
	{
		// Mozilla WYSIWYG can't have the QR collapse button,
		// so remove that and force QR to be expanded
		$show['quickreply_collapse'] = false;

		unset(
			$vbcollapse["collapseobj_quickreply"],
			$vbcollapse["collapseimg_quickreply"],
			$vbcollapse["collapsecel_quickreply"]
		);
	}
	else
	{
		$show['quickreply_collapse'] = true;
	}
}
else if ($show['ajax_js'])
{
	require_once(DIR . '/includes/functions_editor.php');

	$vBeditJs = construct_editor_js_arrays();

	// check that $editor_css has been built
	if (!isset($GLOBALS['editor_css']))
	{
		eval('$GLOBALS[\'editor_css\'] = "' . fetch_template('editor_css') . '";');
		$GLOBALS['headinclude'] .= "<!-- Editor CSS automatically added by " . substr(strrchr(__FILE__, DIRECTORY_SEPARATOR), 1) . " at line " . __LINE__ . " -->\n" . $GLOBALS['editor_css'];
	}

	eval('$vBeditTemplate[\'clientscript\'] = "' . fetch_template('editor_clientscript') . '";');
}

$show['quickedit'] = ($vbulletin->options['quickedit'] AND !$show['threadedmode']);

// #############################################################################
// make a displayable version of the thread notes
if (!empty($thread['notes']))
{
	$thread['notes'] = str_replace('. ', ".\\n", $thread['notes']);
	$shownotes = true;
}
else
{
	$shownotes = false;
}

// #############################################################################
// display admin options if appropriate

$show['deleteposts'] = can_moderate($threadinfo['forumid'], 'candeleteposts') ? true : false;
$show['editthread'] = can_moderate($threadinfo['forumid'], 'caneditthreads') ? true : false;
$show['movethread'] = (can_moderate($threadinfo['forumid'], 'canmanagethreads') OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['canmove'] AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])) ? true : false;
$show['openclose'] = (can_moderate($threadinfo['forumid'], 'canopenclose') OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose'] AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])) ? true : false;
$show['moderatethread'] = (can_moderate($threadinfo['forumid'], 'canmoderateposts') ? true : false);
$show['deletethread'] = (($threadinfo['visible'] != 2 AND can_moderate($threadinfo['forumid'], 'candeleteposts')) OR can_moderate($threadinfo['forumid'], 'canremoveposts') OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread'] AND $vbulletin->userinfo['userid'] == $threadinfo['postuserid'] AND ($vbulletin->options['edittimelimit'] == 0 OR $threadinfo['dateline'] > (TIMENOW - ($vbulletin->options['edittimelimit'] * 60))))) ? true : false;
$show['adminoptions'] = ($show['editpoll'] OR $show['movethread'] OR $show['deleteposts'] OR $show['editthread'] OR $show['managethread'] OR $show['openclose'] OR $show['deletethread']) ? true : false;

// #############################################################################
// Setup Add Poll Conditional
if (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] AND !can_moderate($foruminfo['forumid'], 'caneditpoll')) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostpoll']) OR $threadinfo['pollid'] OR (!can_moderate($foruminfo['forumid'], 'caneditpoll') AND $vbulletin->options['addpolltimeout'] AND TIMENOW - ($vbulletin->options['addpolltimeout'] * 60) > $threadinfo['dateline']))
{
	$show['addpoll'] = false;
}
else
{
	$show['addpoll'] = true;
}

// #############################################################################
// show forum rules
construct_forum_rules($forum, $forumperms);

// #############################################################################
// build social bookmarking links
$guestuser = array(
	'userid'      => 0,
	'usergroupid' => 0,
);
cache_permissions($guestuser);

$bookmarksites = '';
if (
	$vbulletin->options['socialbookmarks'] AND is_array($vbulletin->bookmarksitecache) AND !empty($vbulletin->bookmarksitecache)
		AND
	$guestuser['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']
		AND
	$guestuser['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canview']
		AND
	$guestuser['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']
		AND
	($guestuser['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewothers'] OR $threadinfo['postuserid'] == 0)
)
{
	foreach($vbulletin->bookmarksitecache AS $bookmarksite)
	{
		$bookmarksite['link'] = str_replace(
			array('{URL}', '{TITLE}'),
			array(urlencode($vbulletin->options['bburl'] . '/showthread.php?t=' . $thread['threadid']), urlencode(($bookmarksite['utf8encode'])?utf8_encode($thread['title']):$thread['title'])),
			$bookmarksite['url']
		);

		($hook = vBulletinHook::fetch_hook('showthread_bookmarkbit')) ? eval($hook) : false;

		eval('$bookmarksites .= "' . fetch_template('showthread_bookmarksite') . '";');
	}
}
// #############################################################################
// draw navbar
$navbits = array();
$parentlist = array_reverse(explode(',', substr($forum['parentlist'], 0, -3)));
foreach ($parentlist AS $forumID)
{
	$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
	$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
}
$navbits[''] = $thread['prefix_rich'] . ' ' . $thread['title'];

$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');

// #############################################################################
// setup $show variables
$show['lightbox'] = ($vbulletin->options['lightboxenabled'] AND $vbulletin->options['usepopups']);
$show['search'] = (!$show['search_engine'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['cansearch'] AND $vbulletin->options['enablesearches'] AND (!fetch_require_hvcheck('search')));
$show['subscribed'] = iif($threadinfo['issubscribed'], true, false);
$show['threadrating'] = iif($forum['allowratings'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canthreadrate'], true, false);
$show['ratethread'] = iif($show['threadrating'] AND (!$threadinfo['vote'] OR $vbulletin->options['votechange']), true, false);
$show['closethread'] = iif($threadinfo['open'], true, false);
$show['approvethread'] = ($threadinfo['visible'] == 0 ? true : false);
$show['unstick'] = iif($threadinfo['sticky'], true, false);
$show['reputation'] = ($vbulletin->options['reputationenable']
	AND $vbulletin->userinfo['userid']
	AND $vbulletin->userinfo['permissions']['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']);
$show['sendtofriend'] = ($forumperms & $vbulletin->bf_ugp_forumpermissions['canemail']);

// next/prev links don't work for search engines or non-lastpost sort orders
$show['next_prev_links'] = (!$show['search_engine']
	AND ($foruminfo['defaultsortfield'] == 'lastpost' OR !$foruminfo['defaultsortfield'])
);

$stylevar['margin_3px_fix'] = ((!is_browser('ie') OR is_browser('ie', 7)) ? 3 - $stylevar['cellpadding'] : 0);

$pagenumber = $vbulletin->GPC['pagenumber'];

if (!$show['threadrating'] OR !$vbulletin->options['allowthreadedmode'])
{
	$nodhtmlcolspan = 'colspan="2"';
}

eval('$ad_location[\'ad_showthread_beforeqr\'] = "' . fetch_template('ad_showthread_beforeqr') . '";');

($hook = vBulletinHook::fetch_hook('showthread_complete')) ? eval($hook) : false;

// #############################################################################
// output page
eval('print_output("' . fetch_template('SHOWTHREAD') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
