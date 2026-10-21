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
define('THIS_SCRIPT', 'showthread');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting', 'postbit', 'showthread');

// get special data templates from the datastore
$specialtemplates = array(
	'rankphp',
	'smiliecache',
	'bbcodecache',
	'mailqueue',
	'hidprofilecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'forumdisplay_loggedinuser',
	'forumrules',
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'postbit',
	'postbit_attachment',
	'postbit_attachmentimage',
	'postbit_attachmentthumbnail',
	'postbit_attachmentmoderated',
	'postbit_deleted',
	'postbit_editedby',
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
	'polloptions_table',
	'polloption',
	'polloption_multiple',
	'pollresults_table',
	'pollresult',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ####################### PRE-BACK-END ACTIONS ##########################
function exec_postvar_call_back()
{
	global $_REQUEST;
	if ($_REQUEST['goto'] == 'lastpost' OR $_REQUEST['goto'] == 'newpost' OR $_REQUEST['goto'] == 'postid')
	{
		global $noheader;
		$noheader = 1;
	}
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_bigthree.php');
require_once('./includes/functions_showthread.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

globalize($_REQUEST, array(
	'perpage' => INT,
	'pagenumber' => INT,
	'highlight' => STR,
	'posted'	=> INT,
	'goto'
));

// *********************************************************************************
// set $threadedmode (continued from sessions.php)
if ($vboptions['allowthreadedmode'])
{
	if (!isset($threadedmode))
	{
		DEVDEBUG('$threadedmode is empty');
		if ($bbuserinfo['threadedmode'] == 3)
		{
			$threadedmode = 0;
		}
		else
		{
			$threadedmode = $bbuserinfo['threadedmode'];
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
if (!empty($_GET['highlight']))
{
	$highlightwords = iif($goto, '&', '&amp;') . 'highlight=' . urlencode($_GET['highlight']);
}
else
{
	$highlightwords = '';
}

// ##############################################################################
// ####################### HANDLE HEADER() CALLS ################################
// ##############################################################################
switch($goto)
{
	// *********************************************************************************
	// go to next newest
	case 'nextnewest':
		$thread = verify_id('thread', $threadid, 1, 1);
		if ($getnextnewest = $DB_site->query_first("
			SELECT threadid
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND type = 'thread')
			WHERE forumid = $thread[forumid] AND lastpost > $thread[lastpost] AND visible = 1 AND open <> 10
			AND deletionlog.primaryid IS NULL
			ORDER BY lastpost
			LIMIT 1
		"))
		{
			$threadid = $getnextnewest['threadid'];
			unset ($thread);
		}
		else
		{
			eval(print_standard_error('error_nonextnewest'));
		}
		break;
	// *********************************************************************************
	// go to next oldest
	case 'nextoldest':
		$thread = verify_id('thread', $threadid, 1, 1);
		if ($getnextoldest = $DB_site->query_first("
			SELECT threadid
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND type = 'thread')
			WHERE forumid = $thread[forumid] AND lastpost < $thread[lastpost] AND visible = 1 AND open <> 10
			AND deletionlog.primaryid IS NULL
			ORDER BY lastpost DESC
			LIMIT 1
		"))
		{
			$threadid = $getnextoldest['threadid'];
			unset($thread);
		}
		else
		{
			eval(print_standard_error('error_nonextoldest'));
		}
		break;
	// *********************************************************************************
	// goto last post
	case 'lastpost':
		$threadid = intval($_REQUEST['threadid']);
		if (!empty($_REQUEST['forumid']))
		{ // this one needs to stay AS $_REQUEST!
			$forumid = verify_id('forum', $forumid, 1, 0);

			$thread = $DB_site->query_first("
				SELECT threadid
				FROM " . TABLE_PREFIX . "thread AS thread
				LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND type = 'thread')
				WHERE forumid IN ($foruminfo[childlist]) AND visible = 1 AND (sticky = 1 OR sticky = 0)
				AND lastpost >= " . ($foruminfo['lastpost'] - 30) . " AND open <> 10 AND deletionlog.primaryid IS NULL
				ORDER BY lastpost DESC
				LIMIT 1
			");
			$threadid = $thread['threadid'];
		}

		if (!empty($threadid))
		{
			if ($getlastpost = $DB_site->query_first("
				SELECT MAX(postid) AS postid
				FROM " . TABLE_PREFIX . "post AS post
				LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND type = 'post')
				WHERE threadid = " . intval($threadid) . " AND visible = 1 AND deletionlog.primaryid IS NULL
				LIMIT 1
			"))
			{
				if ($threadedmode != 1) // if linear or hybrid
				{
					exec_header_redirect("showthread.php?$session[sessionurl_js]p=$getlastpost[postid]$highlightwords#post$getlastpost[postid]");
				}
				else // if threaded
				{
					$postid = $getlastpost['postid'];
				}
			}
		}
		break;
	// *********************************************************************************
	// goto newest unread post
	case 'newpost':
		$threadinfo = verify_id('thread', $threadid, 1, 1);

		if (($tview = intval(fetch_bbarray_cookie('thread_lastview', $threadid))) > $bbuserinfo['lastvisit'])
		{
			$bbuserinfo['lastvisit'] = $tview;
		}

		$posts = $DB_site->query_first("
			SELECT MIN(postid) AS postid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadinfo[threadid] AND visible = 1 AND dateline > $bbuserinfo[lastvisit]
			LIMIT 1
		");
		if ($posts['postid'])
		{
			exec_header_redirect("showthread.php?$session[sessionurl_js]p=$posts[postid]$highlightwords#post$posts[postid]");
		}
		else
		{
			exec_header_redirect("showthread.php?$session[sessionurl_js]t=$threadinfo[threadid]&goto=lastpost$highlightwords");
		}
		break;
	// *********************************************************************************
}
// end switch($goto)

// *********************************************************************************
// workaround for header redirect issue from forms with enctype in IE
// (use a scrollIntoView javascript call in the <body> onload event)
$onload = '';

// *********************************************************************************
// set $perpage

if (!$perpage)
{
	$perpage = $bbuserinfo['maxposts'];
}

$checkmax = explode(',', $vboptions['usermaxposts'] . ',' . $vboptions['maxposts']);
if ($perpage < 1 OR $perpage > max($checkmax))
{
	$perpage = $vboptions['maxposts'];
}

// *********************************************************************************
// set post order
if ($bbuserinfo['postorder'] == 0)
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
$threadinfo = &$thread;

// *********************************************************************************
// check for visible / deleted thread
if ((!$thread['visible'] OR $thread['isdeleted']) AND !can_moderate($thread['forumid']))
{
	$idname = $vbphrase['thread'];
	eval(print_standard_error('error_invalidid'));
}

// *********************************************************************************
// jump page if thread is actually a redirect
if ($thread['open'] == 10)
{
	exec_header_redirect("showthread.php?$session[sessionurl_js]t=$thread[pollid]");
}

// *********************************************************************************
// Tachy goes to coventry
if (in_coventry($thread['postuserid']) AND !can_moderate($thread['forumid']))
{
	$idname = $vbphrase['thread'];
	eval(print_standard_error('error_invalidid'));
}

// *********************************************************************************
// do word wrapping for the thread title
if ($vboptions['wordwrap'] != 0)
{
	$thread['title'] = fetch_word_wrapped_string($thread['title']);
}

$thread['title'] = fetch_censored_text($thread['title']);

// *********************************************************************************
// words to highlight from the search engine
if (!empty($highlight))
{

	$highlight = preg_replace('#\*+#s', '*', $highlight);
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
$foruminfo = &$forum;

// *********************************************************************************
// check forum permissions
$forumperms = fetch_permissions($thread['forumid']);
if (!($forumperms & CANVIEW))
{
	print_no_permission();
}
if (!($forumperms & CANVIEWOTHERS) AND ($thread['postuserid'] != $bbuserinfo['userid'] OR $bbuserinfo['userid'] == 0))
{
	print_no_permission();
}

// *********************************************************************************
// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

// *********************************************************************************
// get ignored users
$ignore = array();
if (trim($bbuserinfo['ignorelist']))
{
	$ignorelist = preg_split('/( )+/', trim($bbuserinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
	foreach ($ignorelist AS $ignoreuserid)
	{
		$ignore["$ignoreuserid"] = 1;
	}
}
DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));

// *********************************************************************************
// filter out deletion notices if can't be seen
if (!($forumperms & CANSEEDELNOTICE) AND !can_moderate($threadinfo['forumid']))
{
	$delthreadlimit = "AND deletionlog.primaryid IS NULL";
	$deljoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND type = 'post')";
	$linkdeleted = false;
}
else
{
	$delthreadlimit = '';
	$deljoin = '';
	$linkdeleted = true;
}

$show['viewpost'] = iif(can_moderate($thread['forumid']), true, false);
$show['managepost'] = iif(can_moderate($thread['forumid'], 'candeleteposts') OR can_moderate($thread['forumid'], 'canremoveposts'), true, false);

// *********************************************************************************
// find the page that we should be on to display this post
if (!empty($postid) AND $threadedmode == 0)
{
	$postinfo = verify_id('post', $postid, 1, 1);
	$threadid = $postinfo['threadid'];

	$getpagenum = $DB_site->query_first("
		SELECT COUNT(*) AS posts
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND type = 'post')
		WHERE threadid = $threadid AND visible = 1 AND deletionlog.primaryid IS NULL
		AND dateline " . iif(!$postorder, '<=', '>=') . " $postinfo[dateline]
	");
	$pagenumber = ceil($getpagenum['posts'] / $perpage);
}

// *********************************************************************************
// update views counter
if ($vboptions['threadviewslive'])
{
	// doing it as they happen
	$DB_site->shutdown_query("
		UPDATE " . TABLE_PREFIX . "thread
		SET views = views + 1
		WHERE threadid = " . intval($threadinfo['threadid'])
	);
}
else
{
	// or doing it once an hour
	$DB_site->shutdown_query("
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
		$thread['rating'] = round($thread['votetotal'] / $thread['votenum']);
		if ($thread['votenum'] >= $vboptions['showvotes'])
		{
			$show['rating'] = true;
		}
	}

	devdebug("threadinfo[vote] = $threadinfo[vote]");

	if ($threadinfo['vote'])
	{
		$voteselected["$threadinfo[vote]"] = HTML_SELECTED;
		$votechecked["$threadinfo[vote]"] = HTML_CHECKED;
	}
	else
	{
		$voteselected[0] = HTML_SELECTED;
		$votechecked[0] = HTML_CHECKED;
	}
}

// *********************************************************************************
// get some vars from the referring page in order
// to put a nice back-to-forum link in the navbar
/*
unset($back);
if (strpos($_SERVER['HTTP_REFERER'], 'forumdisplay') !== false)
{
	if ($vars = strchr($_SERVER['HTTP_REFERER'], '&'))
	{
		$pairs = explode('&', $vars);
		foreach ($pairs AS $v)
		{
			$var = explode('=', $v);
			if ($var[1] != '' and $var[0] != 'forumid')
			{
				$back["$var[0]"] = $var[1];
			}
		}
	}
}
*/

// *********************************************************************************
// set page number
if ($pagenumber < 1)
{
	$pagenumber = 1;
}
else if ($pagenumber > ceil(($thread['replycount'] + 1) / $perpage))
{
	$pagenumber = ceil(($thread['replycount'] + 1) / $perpage);
}
// *********************************************************************************
// initialise some stuff...
$limitlower = ($pagenumber - 1) * $perpage;
$limitupper = ($pagenumber) * $perpage;
$counter = 0;
$threadview = intval(fetch_bbarray_cookie('thread_lastview', $thread['threadid']));
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
	$pollinfo = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "poll
		WHERE pollid = $pollid
	");

	$pollinfo['question'] = parse_bbcode(unhtmlspecialchars($pollinfo['question']), $forum['forumid'], 1);

	$splitoptions = explode('|||', $pollinfo['options']);
	$splitvotes = explode('|||', $pollinfo['votes']);

	$showresults = 0;
	$uservoted = 0;
	if (!($forumperms & CANVOTE))
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

	if ($pollinfo['timeout'] AND !$showresults)
	{
		$pollendtime = vbdate($vboptions['timeformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
		$pollenddate = vbdate($vboptions['dateformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
		$show['pollenddate'] = true;
	}
	else
	{
		$show['pollenddate'] = false;
	}

	$option['open'] = $stylevar['left'][0];
	$option['close'] = $stylevar['right'][0];

	foreach ($splitvotes AS $index => $value)
	{
		$pollinfo['numbervotes'] += $value;
	}

	if ($bbuserinfo['userid'] > 0 AND $pollinfo['numbervotes'] > 0)
	{
		$pollvotes = $DB_site->query("
			SELECT voteoption
			FROM " . TABLE_PREFIX . "pollvote
			WHERE userid = $bbuserinfo[userid] AND pollid = $pollid
		");
		if ($DB_site->num_rows($pollvotes) > 0)
		{
			$uservoted = 1;
		}
	}

	if ($showresults OR $uservoted)
	{
		if ($uservoted)
		{
			$uservote = array();
			while ($pollvote = $DB_site->fetch_array($pollvotes))
			{
				$uservote["$pollvote[voteoption]"] = 1;
			}
		}
	}

	foreach ($splitvotes AS $index => $value)
	{
		$arrayindex = $index + 1;
		$option['uservote'] = iif($uservote["$arrayindex"], true, false);
		$option['question'] = parse_bbcode($splitoptions["$index"], $forum['forumid'], 1);

		// public link
		if ($pollinfo['public'] AND $value)
		{
			$option['votes'] = '<a href="poll.php?' . $session['sessionurl'] . 'do=showresults&amp;pollid=' . $pollinfo['pollid'] . '">' . $value . '</a>';
		}
		else
		{
			$option['votes'] = $value;   //get the vote count for the option
		}

		$option['number'] = $counter;  //number of the option

		//Now we check if the user has voted or not
		if ($showresults OR $uservoted)
		{ // user did vote or poll is closed

			if ($value == 0)
			{
				$option['percent'] = 0;
			}
			else if ($pollinfo['multiple'])
			{
				$option['percent'] = vb_number_format(($value < $pollinfo['voters']) ? $value / $pollinfo['voters'] * 100 : 100, 2);
			}
			else
			{
				$option['percent'] = vb_number_format(($value < $pollinfo['numbervotes']) ? $value / $pollinfo['numbervotes'] * 100 : 100, 2);
			}

			$option['graphicnumber'] = $option['number'] % 6 + 1;
			$option['barnumber'] = round($option['percent']) * 2;

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

			eval('$pollbits .= "' . fetch_template('pollresult') . '";');
		}
		else
		{
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
	!$thread['isdeleted'] AND !is_browser('netscape') AND $vboptions['quickreply'] AND $bbuserinfo['userid']
	AND (
		($bbuserinfo['userid'] == $threadinfo['postuserid'] AND $forumperms & CANREPLYOWN)
		OR
		($bbuserinfo['userid'] != $threadinfo['postuserid'] AND $forumperms & CANREPLYOTHERS)
	) AND
	($thread['open'] OR can_moderate($threadinfo['forumid'], 'canopenclose'))
)
{
	switch($vboptions['quickreply'])
	{
		case 1: // WYSIWYG
			$vboptions['allowvbcodebuttons'] = 3;
			break;
		case 2: // ENHANCED
			$vboptions['allowvbcodebuttons'] = 1;
			break;
		case 3: // STANDARD
			$vboptions['allowvbcodebuttons'] = 0;
			break;
	}
	$SHOWQUICKREPLY = true;
}
else
{
	$SHOWQUICKREPLY = false;
	$WYSIWYG = 0;
	$quickreply = '';
}
$show['largereplybutton'] = (!$thread['isdeleted'] AND !$show['threadedmode'] AND $forum['allowposting']);
if (!$forum['allowposting'])
{
	$SHOWQUICKREPLY = false;
}

$saveparsed = ''; // inialise
// see if the lastpost time of this thread is older than the cache max age limit
if ($vboptions['cachemaxage'] == 0 OR TIMENOW - ($vboptions['cachemaxage'] * 60 * 60 * 24) > $thread['lastpost'])
{
	$stopsaveparsed = 1;
}
else
{
	$stopsaveparsed = 0;
}

################################################################################
####################### SHOW THREAD IN LINEAR MODE #############################
################################################################################
if ($threadedmode == 0)
{

	// allow deleted posts to not be counted in number of posts displayed on the page;
	// prevents issue with page count on forum display being incorrect
	$ids = '';
	$lastpostid = 0;
	if ($deljoin)
	{
		$totalposts = $threadinfo['replycount'] + 1;

		$getpostids = $DB_site->query("
			SELECT postid FROM " . TABLE_PREFIX . "post AS post
			$deljoin
			WHERE threadid = $threadid AND visible = 1 $delthreadlimit
			ORDER BY dateline $postorder
			LIMIT $limitlower, $perpage
		");
		while ($post = $DB_site->fetch_array($getpostids))
		{
			if (!isset($qrfirstpostid))
			{
				$qrfirstpostid = $post['postid'];
			}
			$qrlastpostid = $post['postid'];
			$ids .= ',' . $post['postid'];
		}
		$DB_site->free_result($getpostids);

		$lastpostid = $qrlastpostid;
	}
	else
	{

		$getpostids = $DB_site->query("
			SELECT postid, NOT ISNULL(deletionlog.primaryid) AS isdeleted
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND type = 'post')
			WHERE threadid = $threadid AND visible = 1
			ORDER BY dateline $postorder
		");
		$totalposts = 0;
		if ($limitlower != 0)
		{
			$limitlower++;
		}
		while ($post = $DB_site->fetch_array($getpostids))
		{
			if (!isset($qrfirstpostid))
			{
				$qrfirstpostid = $post['postid'];
			}
			$qrlastpostid = $post['postid'];
			if (!$post['isdeleted'])
			{
				$totalposts++;
			}
			if ($totalposts < $limitlower OR $totalposts > $limitupper)
			{
				continue;
			}

			// remember, these are only added if they're going to be displayed
			$ids .= ',' . $post['postid'];
			$lastpostid = $post['postid'];
		}
		$DB_site->free_result($getpostids);
	}
	$postids = "post.postid IN (0" . $ids . ")";

	// load attachments
	if ($thread['attach'])
	{
		$attachments = $DB_site->query("
			SELECT filename, filesize, visible, attachmentid, counter, postid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize
			FROM " . TABLE_PREFIX . "attachment
			WHERE postid IN (-1" . $ids . ")
			ORDER BY dateline
		");
		$postattach = array();
		while ($attachment = $DB_site->fetch_array($attachments))
		{
			$postattach["$attachment[postid]"]["$attachment[attachmentid]"] = $attachment;
		}
	}

	$posts = $DB_site->query("
		SELECT
			post.*, post.username AS postusername, post.ipaddress AS ip,
			user.*, userfield.*, usertextfield.*,
			" . iif($forum['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
			" . iif($vboptions['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.avatardata) AS hascustomavatar, customavatar.dateline AS avatardateline,') . "
			" . iif($vboptions['reputationenable'], 'level,') . "
			" . iif(!$deljoin, 'NOT ISNULL(deletionlog.primaryid) AS isdeleted, deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason,') . "
			editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
			editlog.reason AS edit_reason,
			post_parsed.pagetext_html, post_parsed.hasimages,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
			" . iif(!($permissions['genericpermissions'] & CANSEEHIDDENCUSTOMFIELDS), $datastore['hidprofilecache']) . "
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
		" . iif($forum['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
		" . iif($vboptions['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") .
			iif($vboptions['reputationenable'], " LEFT JOIN " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON(user.reputationlevelid = reputationlevel.reputationlevelid)") . "
		" . iif(!$deljoin, "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = post.postid AND deletionlog.type = 'post')") . "
		LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "post_parsed AS post_parsed ON(post_parsed.postid = post.postid)
		WHERE $postids
		ORDER BY dateline $postorder
	");

	if (!($forumperms & CANGETATTACHMENT))
	{
		$vboptions['viewattachedimages'] = 0;
		$vboptions['attachthumbs'] = 0;
	}

	$postcount = ($pagenumber - 1 ) * $perpage;
	if ($postorder)
	{ // Newest first
		if ($totalposts > $postcount + $perpage)
		{
			$postcount = $totalposts - $postcount + 1;
		}
		else
		{
			$postcount = $totalposts - $postcount + 1;
		}
	}

	$counter = 0;
	$postbits = '';

	while ($post = $DB_site->fetch_array($posts))
	{
		if (!$post['isdeleted'])
		{
			// fix to prevent deleted posts that are the last post on the page from not being shown
			if ($counter >= $perpage)
			{
				break;
			}
			$counter++;
			if ($postorder)
			{
				$post['postcount'] = --$postcount;
			}
			else
			{
				$post['postcount'] = ++$postcount;
			}
		}

		// get first and last post ids for this page (for big reply buttons)
		if (!isset($FIRSTPOSTID))
		{
			$FIRSTPOSTID = $post['postid'];
		}
		$LASTPOSTID = $post['postid'];

		DEVDEBUG(">>> " . $vbphrase['members_list']);

		$parsed_postcache = array('text' => '', 'images' => 1, 'skip' => false);

		$post['musername'] = fetch_musername($post);
		$post['islastshown'] = ($post['postid'] == $lastpostid);
		$post['attachments'] = &$postattach["$post[postid]"];

		if ($post['isdeleted'])
		{
			$template = 'postbit_deleted';
		}
		else
		{
			$template = 'postbit';
		}
		$postbits .= construct_postbit($post, $template);

		if (!empty($parsed_postcache['text']) AND !$stopsaveparsed)
		{
			if (!empty($saveparsed))
			{
				$saveparsed .= ',';
			}
			$saveparsed .= "($post[postid], " . intval($thread['lastpost']) . ', ' . $parsed_postcache['images'] . ", '" . addslashes($parsed_postcache['text']) . "')";
		}

		if ($post['dateline'] > $displayed_dateline)
		{
			$displayed_dateline = $post['dateline'];
			if ($displayed_dateline <= $threadview)
			{
				$updatethreadcookie = true;
			}
		}

	}
	$DB_site->free_result($posts);
	unset($post);

	DEVDEBUG("First Post: $FIRSTPOSTID; Last Post: $LASTPOSTID");

	$pagenav = construct_page_nav($totalposts, "showthread.php?$session[sessionurl]t=$threadid", "&amp;pp=$perpage$highlightwords");

	if ($thread['lastpost'] > $bbuserinfo['lastvisit'])
	{
		// do blue arrow link
		if ($firstnew)
		{
			$firstunread = '#post' . $firstnew;
			$show['firstunreadlink'] = true;
		}
		else
		{
			$firstunread = 'showthread.php?' . $session['sessionurl'] . 't=' . $threadid . '&goto=newpost';
			$show['firstunreadlink'] = true;
		}
	}
	else
	{
		$firstunread = '';
		$show['firstunreadlink'] = false;
	}

################################################################################
################ SHOW THREAD IN THREADED OR HYBRID MODE ########################
################################################################################
}
else
{

	require_once('./includes/functions_threadedmode.php');

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
	$cache_postids = '';

	// get all posts
	$listposts = $DB_site->query("
		SELECT
			post.*, post.username AS postusername, post.ipaddress AS ip,
			NOT ISNULL(deletionlog.primaryid) AS isdeleted,
			user.*, userfield.*
			" . iif(!can_moderate(), $datastore['hidprofilecache']) . "
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND type = 'post')
		WHERE threadid = $threadid AND post.visible = 1
		ORDER BY postid
	");

	// $toppostid is the first post in the thread
	// $curpostid is the postid passed from the URL, or if not specified, the first post in the thread
	$ids = '';
	while ($post = $DB_site->fetch_array($listposts))
	{
		if ($post['isdeleted'] AND $deljoin)
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
				if ($threadedmode == 2 AND empty ($_REQUEST['postid']))
				{
					$_REQUEST['postid'] = $curpostid;
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
		$userarray["$post[userid]"] = addslashes($post['username']);

		$totalposts++;
		$ids .= ",$post[postid]";
	}
	$DB_site->free_result($listposts);

	// hooks child posts up to new parent if actual parent has been deleted
	if (count($deletedparents) > 0 AND $deljoin)
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
		$attachments = $DB_site->query("
			SELECT filename, filesize, visible, attachmentid, counter, postid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize
			FROM " . TABLE_PREFIX . "attachment
			WHERE postid IN (-1$ids)
		");
		while ($attachment = $DB_site->fetch_array($attachments))
		{
			$postattach["$attachment[postid]"]["$attachment[attachmentid]"] = $attachment;
		}
	}

	// get list of usernames from post list
	$userjs = '';
	foreach ($userarray AS $userid => $username)
	{
		if ($userid)
		{
			$userjs .= "pu[$userid] = \"$username\";\n";
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
		$idname = $vbphrase['post'];
		eval(print_standard_error('error_invalidid'));
	}

	if ($threadedmode == 2) // hybrid display mode
	{
		$numhybrids = sizeof($hybridposts);

		if ($pagenumber < 1)
		{
			$pagenumber = 1;
		}
		$startat = ($pagenumber - 1) * $perpage;
		if ($startat > $numhybrids)
		{
			$pagenumber = 1;
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
				$cache_postids .= ",$hybridposts[$i]";
				$LASTPOSTID = $hybridposts["$i"];
			}
		}
		$pagenav = construct_page_nav($numhybrids, "showthread.php?$session[sessionurl]p=$_REQUEST[postid]", "&amp;pp=$perpage$highlightwords");

	}
	else // threaded display mode
	{
		$FIRSTPOSTID = $curpostid;
		$LASTPOSTID = $curpostid;

		// sort out which posts to cache:
		if (!$vboptions['threaded_maxcache'])
		{
			$vboptions['threaded_maxcache'] = 999999;
		}

		// cache $vboptions['threaded_maxcache'] posts
		// take 0.25 from above $curpostid
		// and take 0.75 below
		if (sizeof($postorder) <= $vboptions['threaded_maxcache']) // cache all, thread is too small!
		{
			$startat = 0;
		}
		else
		{
			if (($curpostidkey + ($vboptions['threaded_maxcache'] * 0.75)) > sizeof($postorder))
			{
				$startat = sizeof($postorder) - $vboptions['threaded_maxcache'];
			}
			else if (($curpostidkey - ($vboptions['threaded_maxcache'] * 0.25)) < 0)
			{
				$startat = 0;
			}
			else
			{
				$startat = intval($curpostidkey - ($vboptions['threaded_maxcache'] * 0.25));
			}
		}
		unset($curpostidkey);

		foreach ($postorder AS $postkey => $postid)
		{
			if ($postkey > ($startat + $vboptions['threaded_maxcache'])) // got enough entries now
			{
				break;
			}
			if ($postkey >= $startat AND empty($morereplies["$postid"]))
			{
				$cache_postids .= ',' . $postid;
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

	$cache_postids = substr($cache_postids, 1);
	if (empty($cache_postids))
	{
		// umm... something weird happened. Just prevent an error.
		$idname = $vbphrase['post'];
		eval(print_standard_error('error_invalidid'));
	}

	$cacheposts = $DB_site->query("
		SELECT
			post.*, post.username AS postusername, post.ipaddress AS ip,
			user.*, userfield.*, usertextfield.*,
			" . iif($forum['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
			" . iif($vboptions['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.avatardata) AS hascustomavatar, customavatar.dateline AS avatardateline,') . "
			" . iif($vboptions['reputationenable'], 'level,') . "
			" . iif(!$deljoin, "NOT ISNULL(deletionlog.primaryid) AS isdeleted, deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason,") . "
			editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
			editlog.reason AS edit_reason,
			post_parsed.pagetext_html, post_parsed.hasimages,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
			" . iif(!can_moderate(), $datastore['hidprofilecache']) . "
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
		" . iif($forum['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
		" . iif($vboptions['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") .
			iif($vboptions['reputationenable'], " LEFT JOIN " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON(user.reputationlevelid = reputationlevel.reputationlevelid)") . "
		" . iif(!$deljoin, "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND type = 'post')") . "
		LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "post_parsed AS post_parsed ON(post_parsed.postid = post.postid)
		WHERE post.postid IN (" . $cache_postids . ")
	");

	// re-initialise the $postarray variable
	$postarray = array();
	while ($post = $DB_site->fetch_array($cacheposts))
	{
		$postarray["$post[postid]"] = $post;
	}

	if (!($forumperms & CANGETATTACHMENT))
	{
		$vboptions['viewattachedimages'] = 0;
		$vboptions['attachthumbs'] = 0;
	}

	// init
	$postcount = 0;
	$postbits = '';
	$saveparsed = '';
	$jspostbits = '';

	foreach (explode(',', $cache_postids) AS $id)
	{
		// get the post from the post array
		if (!isset($postarray["$id"]))
		{
			continue;
		}
		$post = $postarray["$id"];

		$post['musername'] = fetch_musername($post);
		$post['postcount'] = ++$postcount;

		$parsed_postcache = array('text' => '', 'images' => 1);

		$template = iif($post['isdeleted'], 'postbit_deleted', 'postbit');

		$post['attachments'] = &$postattach["$post[postid]"];

		$bgclass = 'alt2';
		if ($threadedmode == 2) // hybrid display mode
		{
			$postbits .= construct_postbit($post, $template);
		}
		else // threaded display mode
		{
			$postbit = construct_postbit($post, $template);

			if ($curpostid == $post['postid'])
			{
				$curpostdateline = $post['dateline'];
				$curpostbit = $postbit;
			}
			$postbit = preg_replace('#</script>#i', "<' + '/scr' + 'ipt>", addslashes_js($postbit));
			$jspostbits .= "pd[$post[postid]] = '$postbit';\n";

		} // end threaded mode

		if (!empty($parsed_postcache['text']) AND !$stopsaveparsed)
		{
			if (!empty($saveparsed))
			{
				$saveparsed .= ',';
			}
			$saveparsed .= "($post[postid], " . intval($thread['lastpost']) . ', ' . $parsed_postcache['images'] . ", '" . addslashes($parsed_postcache['text']) . "')";
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
	$DB_site->free_result($cacheposts);

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
	$show['postlistwidth'] = $postlistwidth AND is_browser('ie');
	eval('$threadlist = "' . fetch_template('showthread_list') . '";');
	unset($curpostbit, $post, $cacheposts, $parsed_postcache, $postbit);

}

################################################################################
########################## END LINEAR / THREADED ###############################
################################################################################

// *********************************************************************************
//set thread last view
if ($thread['pollid'] AND $vboptions['updatelastpost'] AND ($displayed_dateline == $thread['lastpost'] OR $threadview == $thread['lastpost']) AND $pollinfo['lastvote'] > $thread['lastpost'])
{
	$displayed_dateline = $pollinfo['lastvote'];
}

if ((!$posted OR $updatethreadcookie) AND $displayed_dateline AND $displayed_dateline > $threadview)
{
	set_bbarray_cookie('thread_lastview', $threadid, $displayed_dateline);
}

if (DB_QUERIES)
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
	$DB_site->shutdown_query("
		REPLACE INTO " . TABLE_PREFIX . "post_parsed (postid, dateline, hasimages, pagetext_html)
		VALUES $saveparsed
	");
	unset($saveparsed);
}

// *********************************************************************************
// Get users browsing this thread
if ($vboptions['showthreadusers'])
{
	$datecut = TIMENOW - $vboptions['cookietimeout'];
	$browsers = '';
	$comma = '';

	// Don't put the inthread value in the WHERE clause as it might not be the newest location!
	$threadusers = $DB_site->query("
		SELECT user.username, user.usergroupid, user.membergroupids,
			session.userid, session.inthread, session.lastactivity,
			IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid,
			IF(user.options & $_USEROPTIONS[invisible], 1, 0) AS invisible
		FROM " . TABLE_PREFIX . "session AS session
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = session.userid)
		WHERE session.lastactivity > $datecut
		ORDER BY " . iif($$vboptions['showthreadusers'] == 1, " username ASC,") . " lastactivity DESC
	");

	$numberguest = 0;
	$numberregistered = 0;
	$doneuser = array();

	if ($bbuserinfo['userid']) // fakes the user being in this thread
	{
		$bbuserinfo['joingroupid'] = iif($bbuserinfo['displaygroupid'], $bbuserinfo['displaygroupid'], $bbuserinfo['usergroupid']);
		$loggedin = array(
			'userid' => $bbuserinfo['userid'],
			'username' => $bbuserinfo['username'],
			'invisible' => $bbuserinfo['invisible'],
			'invisiblemark' => $bbuserinfo['invisiblemark'],
			'inthread' => $threadinfo['threadid'],
			'lastactivity' => TIMENOW,
			'musername' => fetch_musername($bbuserinfo, 'joingroupid')
		);
		$numberregistered = 1;
		$numbervisible = 1;
		fetch_online_status($loggedin);
		eval('$activeusers = "' . fetch_template('forumdisplay_loggedinuser') . '";');
		$doneuser["$bbuserinfo[userid]"] = 1;
		$comma = ', ';
	}

	// this requires the query to have lastactivity ordered by DESC so that the latest location will be the first encountered.
	while ($loggedin = $DB_site->fetch_array($threadusers))
	{
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
					$loggedin['musername'] = fetch_musername($loggedin);
					$numberregistered++;
					if (fetch_online_status($loggedin))
					{
						eval('$activeusers .= "' . $comma . fetch_template('forumdisplay_loggedinuser') . '";');
						$comma = ', ';
					}
				}
			}
			if ($loggedin['userid'])
			{
				$doneuser["$loggedin[userid]"] = 1;
			}
		}
	}

	$totalonline = $numberregistered + $numberguest;

	$DB_site->free_result($threadusers);
	unset($comma, $userinfos, $userid, $userinfo, $loggedin, $threadusers, $datecut);
}

// *********************************************************************************
// get similar threads
if (/*$vboptions['similarthreadsearch'] AND*/ $vboptions['showsimilarthreads'] AND $thread['similar'])
{
	$simthrds = $DB_site->query("
		SELECT thread.threadid, thread.forumid, thread.title, postusername, postuserid, thread.lastpost, thread.replycount, forum.title AS forumtitle
			" . iif($vboptions['threadpreview'], ",post.pagetext AS preview") . "
			" . iif($vboptions['threadsubscribed'] AND $bbuserinfo['userid'], ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed") . "
		FROM " . TABLE_PREFIX . "thread AS thread
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid=thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND type = 'thread')
		" . iif($vboptions['threadpreview'], "LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = thread.firstpostid)") . "
		" . iif($vboptions['threadsubscribed'] AND $bbuserinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON(subscribethread.threadid = thread.threadid AND subscribethread.userid = $bbuserinfo[userid])") . "
		WHERE thread.threadid IN ($thread[similar]) AND thread.visible = 1 AND deletionlog.primaryid IS NULL
			" . iif (($permissions['adminpermissions'] & CANCONTROLPANEL) OR ($permissions['adminpermissions'] & ISMODERATOR) OR can_moderate($forumid), '', "AND forum.password = ''") . "
		ORDER BY lastpost DESC
	");

	$similarthreadbits = '';
	while ($simthread = $DB_site->fetch_array($simthrds))
	{
		$fperms = fetch_permissions($simthread['forumid']);
		if (($fperms & CANVIEW) AND
			(($fperms & CANVIEWOTHERS) OR ($bbuserinfo['userid'] != 0 AND $simthread['postuserid'] == $bbuserinfo['userid']))
		)
		{
			// format thread preview if there is one
			if ($ignore["$simthread[postuserid]"])
			{
				$simthread['preview'] = '';
			}
			else if (isset($simthread['preview']) AND $vboptions['threadpreview'] > 0)
			{
				$simthread['preview'] = strip_quotes($simthread['preview']);
				$simthread['preview'] = htmlspecialchars_uni(fetch_trimmed_title(strip_bbcode($simthread['preview'], false, true), $vboptions['threadpreview']));
			}

			$simthread['lastreplydate'] = vbdate($vboptions['dateformat'], $simthread['lastpost'], true);
			$simthread['lastreplytime'] = vbdate($vboptions['timeformat'], $simthread['lastpost']);
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
if ($SHOWQUICKREPLY)
{
	require_once('./includes/functions_editor.php');

	$WYSIWYG = is_wysiwyg_compatible();
	$istyles_js = construct_editor_styles_js();

	// set show signature hidden field
	$showsig = iif($bbuserinfo['signature'], 1, 0);

	// set quick reply initial id
	if ($threadedmode == 1)
	{
		$qrpostid = $curpostid;
		$QRrequireclick = 0;
	}
	else if ($vboptions['quickreplyclick'])
	{
		$qrpostid = 0;
		$QRrequireclick = 1;
	}
	else
	{
		$qrpostid = 'who cares';
		$QRrequireclick = 0;
	}

	if (!$QRrequireclick AND $WYSIWYG >= 1)
	{
		$onload .= " editInit();";
	}

	// append some CSS to the headinclude template
	$headinclude .= "
		<!-- set up CSS for the editor -->
		<link rel=\"stylesheet\" type=\"text/css\" href=\"clientscript/vbulletin_editor.css\" />
		<style type=\"text/css\">
		<!--
		#vBulletin_editor {
			background: {$istyles[pi_button_normal][0]};
			padding: $stylevar[cellpadding]px;
		}
		#controlbar, .controlbar {
			background: {$istyles[pi_button_normal][0]};
		}
		.imagebutton {
			background: {$istyles[pi_button_normal][0]};
			color: {$istyles[pi_button_normal][1]};
			padding: {$istyles[pi_button_normal][2]};
			border: {$istyles[pi_button_normal][3]};
		}
		-->
		</style>
	";
	$vbphrase['click_quick_reply_icon'] = addslashes_js($vbphrase['click_quick_reply_icon']);
	eval('$quickreply = "' . fetch_template('showthread_quickreply') . '";');
}

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

if ( ( ( ( ($forumperms & CANOPENCLOSE) OR ($forumperms & CANMOVE)) AND $thread['postuserid'] == $bbuserinfo['userid']) OR can_moderate($forumid) ) AND !$thread['isdeleted']) // end big if
{
	$show['adminoptions'] = true;
}
else
{
	$show['adminoptions'] = false;
}

// #############################################################################
// Setup Add Poll Conditional
if (($bbuserinfo['userid'] != $threadinfo['postuserid'] AND !can_moderate($foruminfo['forumid'], 'caneditpoll')) OR !($forumperms & CANPOSTNEW) OR !($forumperms & CANPOSTPOLL) OR $threadinfo['pollid'] OR (!can_moderate($foruminfo['forumid'], 'caneditpoll') AND $vboptions['addpolltimeout'] AND TIMENOW - ($vboptions['addpolltimeout'] * 60) > $threadinfo['dateline']))
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
// draw navbar
$navbits = array();
$parentlist = array_reverse(explode(',', substr($forum['parentlist'], 0, -3)));
foreach ($parentlist AS $forumID)
{
	$forumTitle = $forumcache["$forumID"]['title'];
	$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
}
$navbits[''] = $thread['title'];

$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');

// #############################################################################
// setup $show variables
$show['search'] = iif ($forumperms & CANSEARCH AND $vboptions['enablesearches'], true, false);
$show['activeusers'] = iif($activeusers != '', true, false);
$show['subscribed'] = iif($threadinfo['issubscribed'], true, false);

$show['threadrating'] = iif($forum['allowratings'] AND $forumperms & CANTHREADRATE, true, false);
$show['ratethread'] = iif($show['threadrating'] AND (!$threadinfo['vote'] OR $vboptions['votechange']), true, false);

$show['closethread'] = iif($threadinfo['open'], true, false);
$show['unstick'] = iif($threadinfo['sticky'], true, false);

if (!$show['threadrating'] OR !$vboptions['allowthreadedmode'])
{
	$nodhtmlcolspan = 'colspan="2"';
}

// #############################################################################
// output page
eval('print_output("' . fetch_template('SHOWTHREAD') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14763 $
|| ####################################################################
\*======================================================================*/
?>