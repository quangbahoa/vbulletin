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
define('THIS_SCRIPT', 'forumdisplay');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('forumdisplay');

// get special data templates from the datastore
$specialtemplates = array(
	'iconcache',
	'mailqueue'
);

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'none' => array(
		'FORUMDISPLAY',
		'threadbit',
		'threadbit_deleted',
		'forumdisplay_announcement',
		'forumhome_lastpostby',
		'forumhome_forumbit_level1_post',
		'forumhome_forumbit_level2_post',
		'forumhome_forumbit_level1_nopost',
		'forumhome_forumbit_level2_nopost',
		'forumhome_subforumbit_nopost',
		'forumhome_subforumseparator_nopost',
		'forumdisplay_loggedinuser',
		'forumhome_moderator',
		'forumdisplay_moderator',
		'forumdisplay_sortarrow',
		'forumhome_subforumbit_post',
		'forumhome_subforumseparator_post',
		'forumrules'
	)
);

// ####################### PRE-BACK-END ACTIONS ##########################
function exec_postvar_call_back()
{
	global $_REQUEST, $session, $vboptions;

	// jump from forumjump
	switch ($_REQUEST['forumid'])
	{
		case 'search':	$goto = 'search'; break;
		case 'pm':		$goto = 'private'; break;
		case 'wol':		$goto = 'online'; break;
		case 'cp':		$goto = 'usercp'; break;
		case 'subs':	$goto = 'subscription'; break;
		case 'home':
		case '-1':		$goto = $vboptions['forumhome']; break;
	}

	if ($goto != '')
	{
		require_once('./includes/functions.php');
		exec_header_redirect("$goto.php?$session[sessionurl_js]");
	}
	// end forumjump redirects
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');



require_once('./includes/functions_forumlist.php');
require_once('./includes/functions_bigthree.php');
require_once('./includes/functions_forumdisplay.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ############################### start mark forums read ###############################
if ($_REQUEST['do'] == 'markread')
{
	$forumid = intval($_REQUEST['forumid']);

	if (!$forumid)
	{
		if ($bbuserinfo['userid'])
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET lastactivity = " . TIMENOW . ", lastvisit=" . (TIMENOW - 1) . " WHERE userid=$bbuserinfo[userid]");
		}
		else
		{
			vbsetcookie('lastvisit', TIMENOW);
		}

		$url = "$vboptions[forumhome].php?$session[sessionurl]";
		eval(print_standard_redirect('markread'));
	}
	else
	{
		// temp work around code, I need to find another way to mass set some values to the cookie
		$bb_cache_forum_view = unserialize(convert_bbarray_cookie($_COOKIE[COOKIE_PREFIX . 'forum_view']));

		require_once('./includes/functions_misc.php');
		$childforums = fetch_child_forums($forumid, 'ARRAY');
		foreach ($childforums AS $val)
		{ // mark the forum and all child forums read
			$bb_cache_forum_view["$val"] = TIMENOW;
		}
		set_bbarray_cookie('forum_view', $forumid, TIMENOW);

		if ($foruminfo['parentid'] == -1)
		{
			$url = "$vboptions[forumhome].php?$session[sessionurl]";
		}
		else
		{
			$url = "forumdisplay.php?$session[sessionurl]f=$foruminfo[parentid]";
		}
		eval(print_standard_redirect('markread_single'));
	}
}

// ############################### start enter password ###############################
if ($_REQUEST['do'] == 'doenterpwd')
{
	globalize($_REQUEST, array('forumid' => INT, 'newforumpwd' => STR, 'url' => STR, 'postvars'));

	$foruminfo = verify_id('forum', $forumid, 1, 1);

	if ($foruminfo['password'] == $newforumpwd)
	{
		// set a temp cookie for guests
		if (!$bbuserinfo['userid'])
		{
			set_bbarray_cookie('forumpwd', $forumid, md5($bbuserinfo['userid'] . $newforumpwd));
		}
		else
		{
			set_bbarray_cookie('forumpwd', $forumid, md5($bbuserinfo['userid'] . $newforumpwd), 1);
		}

		if ($url == "$vboptions[forumhome].php")
		{
			$url = "forumdisplay.php?$session[sessionurl]f=$forumid";
		}
		else if ($url != '' AND $url != 'forumdisplay.php')
		{
			$url = str_replace('"', '', $url);
		}
		else
		{
			$url = "forumdisplay.php?$session[sessionurl]f=$forumid";
		}

		// Allow POST based redirection...
		if ($postvars)
		{
			$temp = unserialize($postvars);
			if ($temp['do'] != 'doenterpwd')
			{ // ...but prevent an infinite loop
				$postvars = construct_hidden_var_fields($postvars);
				$formfile = $url;
			}
			else
			{
				$postvars = '';
			}
		}
		eval(print_standard_redirect('forumpasswordcorrect'));
	}
	else
	{
		$postvars = construct_post_vars_html();
		eval(print_standard_error('forumpasswordincorrect'));
	}
}

// ###### END SPECIAL PATHS

globalize($_REQUEST, array('perpage' => INT, 'pagenumber' => INT, 'daysprune' => INT));

// needs this to show error if forum does not exist
$foruminfo = verify_id('forum', $forumid, 1, 1);

// get permission to view forum
$_permsgetter_ = 'forumdisplay';
$forumperms = fetch_permissions($forumid);
if (!($forumperms & CANVIEW))
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$show['newthreadlink'] = iif($foruminfo['allowposting'], true, false);
$show['threadicons'] = iif ($foruminfo['allowicons'], true, false);
$show['threadratings'] = iif ($foruminfo['allowratings'], true, false);

// get iforumcache - for use by makeforumjump and forums list
// fetch the forum even if they are invisible since its needed
// for the title but we'll unset that further down
cache_ordered_forums(1, 1);

if (!$daysprune)
{
	if ($bbuserinfo['daysprune'] != 0)
	{
		$daysprune = $bbuserinfo['daysprune'];
	}
	else
	{
		$daysprune = iif($foruminfo['daysprune'], $foruminfo['daysprune'], 30);
	}
}

// ### GET FORUMS, PERMISSIONS, MODERATOR iCACHES ########################
cache_moderators();

// draw nav bar
$navbits = array();
$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
foreach ($parentlist AS $forumID)
{
	$forumTitle = $forumcache["$forumID"]['title'];
	$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
}

// pop the last element off the end of the $nav array so that we can show it without a link
array_pop($navbits);

$navbits[''] = $foruminfo['title'];
$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');

$moderatorslist = '';
$listexploded = explode(',', $foruminfo['parentlist']);
$showmods = array();
$show['moderators'] = false;
$totalmods = 0;
foreach ($listexploded AS $parentforumid)
{
	if (!$imodcache["$parentforumid"])
	{
		continue;
	}
	foreach ($imodcache["$parentforumid"] AS $moderator)
	{
		if ($showmods["$moderator[userid]"] === true)
		{
			continue;
		}
		$showmods["$moderator[userid]"] = true;
		if ($moderatorslist == '')
		{
			$show['moderators'] = true;
			eval('$moderatorslist = "' . fetch_template('forumdisplay_moderator') . '";');
		}
		else
		{
			eval('$moderatorslist .= ", ' . fetch_template('forumdisplay_moderator') . '";');
		}
		$totalmods++;
	}
}

// ### BUILD FORUMS LIST #################################################

$comma = '';

// get an array of child forum ids for this forum
$foruminfo['childlist'] = explode(',', $foruminfo['childlist']);

// define max depth for forums display based on $vboptions[forumhomedepth]
define('MAXFORUMDEPTH', $vboptions['forumdisplaydepth']);

if ($vboptions['showforumusers'])
{
	$datecut = TIMENOW - $vboptions['cookietimeout'];
	$forumusers = $DB_site->query("
		SELECT user.username, (user.options & $_USEROPTIONS[invisible]) AS invisible, user.usergroupid, session.userid, session.inforum, session.lastactivity,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
		FROM " . TABLE_PREFIX . "session AS session
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = session.userid)
		WHERE session.lastactivity > $datecut
		ORDER BY" . iif($vboptions['showforumusers'] == 1, " username ASC,") . " lastactivity DESC
	");

	$numberregistered = 0;
	$doneuser = array();

	if ($bbuserinfo['userid'])
	{

		// fakes the user being in this forum
		$bbuserinfo['joingroupid'] = iif($bbuserinfo['displaygroupid'], $bbuserinfo['displaygroupid'], $bbuserinfo['usergroupid']);
		$loggedin = array(
			'userid' => $bbuserinfo['userid'],
			'username' => $bbuserinfo['username'],
			'invisible' => $bbuserinfo['invisible'],
			'invisiblemark' => $bbuserinfo['invisiblemark'],
			'inforum' => $foruminfo['forumid'],
			'lastactivity' => TIMENOW,
			'musername' => fetch_musername($bbuserinfo, 'joingroupid')
		);
		$numberregistered = 1;
		fetch_online_status($loggedin);
		eval('$activeusers = "' . fetch_template('forumdisplay_loggedinuser') . '";');
		$doneuser["$bbuserinfo[userid]"] = 1;
		$comma = ', ';
	}

	$inforum = array();

	$numberguest = 0;

	// this require the query to have lastactivity ordered by DESC so that the latest location will be the first encountered.
	while ($loggedin = $DB_site->fetch_array($forumusers))
	{
		if (empty($doneuser["$loggedin[userid]"]))
		{
			if (in_array($loggedin['inforum'], $foruminfo['childlist']) AND $loggedin['inforum'] != -1)
			{
				if (!$loggedin['userid'])
				{
					// this is a guest
					$numberguest++;
					$inforum["$loggedin[inforum]"]++;
				}
				else
				{
					$numberregistered++;
					$inforum["$loggedin[inforum]"]++;
					if (fetch_online_status($loggedin))
					{
						$loggedin['musername'] = fetch_musername($loggedin);
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
	unset($joingroupid, $key, $datecut , $comma, $invisibleuser, $userinfo, $userid, $loggedin, $index, $value, $forumusers, $parentarray );

	$show['activeusers'] = iif ($activeusers != '', true, false);
}
else
{
	$show['activeusers'] = false;
}

// #############################################################################
// get read status for this forum and children
$unreadchildforums = 0;
foreach ($foruminfo['childlist'] AS $val)
{
	if ($val == -1 OR $val == $foruminfo['forumid'])
	{
		continue;
	}

	if ($forumcache["$val"]['lastpost'] >= intval(fetch_bbarray_cookie('forum_view', $val)) AND $forumcache["$val"]['lastpost'] >= $bbuserinfo['lastvisit'])
	{
		$unreadchildforums = 1;
	}
}

$forumbits = construct_forum_bit($forumid);

if (can_moderate($forumid))
{
	$show['adminoptions'] = true;
}
else
{
	$show['adminoptions'] = false;
}
if ($permissions['adminpermissions'] & CANCONTROLPANEL)
{
	$show['addmoderator'] = true;
}
else
{
	$show['addmoderator'] = false;
}

$curforumid = $forumid;
construct_forum_jump();

/////////////////////////////////
if ($foruminfo['cancontainthreads'])
{
	/////////////////////////////////
	$bbforumview = intval(fetch_bbarray_cookie('forum_view', $foruminfo['forumid']));
	if ($bbforumview > $bbuserinfo['lastvisit'])
	{
		$lastread = $bbforumview;
	}
	else
	{
		$lastread = $bbuserinfo['lastvisit'];
	}

	// get announcements

	$announcebits = '';

	$announcements = $DB_site->query("
		SELECT
			announcementid, startdate, title, announcement.views,
			user.username, user.userid, user.usertitle, user.customtitle
		FROM " . TABLE_PREFIX . "announcement AS announcement
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = announcement.userid)
		WHERE startdate <= " . (TIMENOW - $vboptions['hourdiff']) . "
			AND enddate >= " . (TIMENOW - $vboptions['hourdiff']) . "
			AND " . fetch_forum_clause_sql($foruminfo['forumid'], 'forumid') . "
		ORDER BY startdate DESC
		" . iif($vboptions['oneannounce'], "LIMIT 1"));

	while ($announcement = $DB_site->fetch_array($announcements))
	{
		if ($announcement['customtitle'] == 2)
		{
			$announcement['usertitle'] = htmlspecialchars_uni($announcement['usertitle']);
		}
		$announcement['postdate'] = vbdate($vboptions['dateformat'], $announcement['startdate'], false, true, false);
		if ($announcement['startdate'] > $lastread)
		{
			$announcement['statusicon'] = 'new';
		}
		else
		{
			$announcement['statusicon'] = 'old';
		}
		$announcement['views'] = vb_number_format($announcement['views']);
		$announcementidlink = iif(!$vboptions['oneannounce'] , "&amp;announcementid=$announcement[announcementid]");

		eval('$announcebits .= "' . fetch_template('forumdisplay_announcement') . '";');
	}

	// display threads
	if (!($forumperms & CANVIEWOTHERS))
	{
		$limitothers = "AND postuserid = $bbuserinfo[userid] AND $bbuserinfo[userid] <> 0";
	}
	else
	{
		$limitothers = '';
	}

	// filter out deletion notices if can't be seen
	if (!($forumperms & CANSEEDELNOTICE) AND !can_moderate($forumid))
	{
		$delthreadlimit = "AND deletionlog.primaryid IS NULL";
		$deljoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND type = 'thread')";
	}
	else
	{
		$delthreadlimit = '';
		$deljoin = '';
	}

	// remove threads from users on the global ignore list if user is not a moderator
	if ($Coventry = fetch_coventry('string') AND !can_moderate($forumid))
	{
		$globalignore = "AND postuserid NOT IN ($Coventry) ";
	}
	else
	{
		$globalignore = '';
	}

	// look at thread limiting options
	$stickyids = '';
	$stickycount = 0;
	if ($daysprune != -1)
	{
		$datecut = "AND lastpost >= " . (TIMENOW - ($daysprune * 86400));
		$show['noposts'] = false;
	}
	else
	{
		$datecut = '';
		$show['noposts'] = true;
	}

	// complete form fields on page
	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
	$daysprunesel = array($daysprunesel => HTML_SELECTED);

	globalize($_REQUEST , array('sortfield', 'sortorder' => STR_NOHTML));

	// look at sorting options:
	if ($sortorder != 'asc')
	{
		$sqlsortorder = 'DESC';
		$order = array('desc' => HTML_SELECTED);
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => HTML_SELECTED);
	}

	switch ($sortfield)
	{
		case 'title':
			$sqlsortfield = 'thread.title';
			break;
		case 'lastpost':
		case 'replycount':
		case 'views':
		case 'postusername':
			$sqlsortfield = $sortfield;
			break;
		case 'voteavg':
			if ($foruminfo['allowratings'])
			{
				$sqlsortfield = 'voteavg';
				break;
			} // else, use last post
		default:
			$sqlsortfield = 'lastpost';
			$sortfield = 'lastpost';
	}
	$sort = array($sortfield => HTML_SELECTED);

	$threadscount = $DB_site->query_first("
		SELECT COUNT(*) AS threads,
		SUM(IF(lastpost>=$lastread AND open<>10,1,0)) AS newthread
		FROM " . TABLE_PREFIX . "thread AS thread
		$deljoin
		WHERE forumid = $foruminfo[forumid]
			AND sticky = 0
			AND visible = 1
			$globalignore
			$datecut
			$limitothers
			$delthreadlimit
	");
	$totalthreads = $threadscount['threads'];
	$newthreads = $threadscount['newthread'];

	// set defaults
	sanitize_pageresults($totalthreads, $pagenumber, $perpage, 200, $vboptions['maxthreads']);

	// get number of sticky threads for the first page
	// on the first page there will be the sticky threads PLUS the $perpage other normal threads
	// not quite a bug, but a deliberate feature!
	if ($pagenumber == 1 OR $vboptions['showstickies'])
	{
		$stickies = $DB_site->query("
			SELECT threadid
			FROM " . TABLE_PREFIX . "thread AS thread
			$deljoin
			WHERE forumid = $foruminfo[forumid]
				AND visible = 1
				AND sticky = 1
				$limitothers
				$globalignore
				$delthreadlimit
		");
		while ($thissticky = $DB_site->fetch_array($stickies))
		{
			$stickycount++;
			$stickyids .= ",$thissticky[threadid]";
		}
		$DB_site->free_result($stickies);
		unset($thissticky, $stickies);
	}


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

	if ($foruminfo['allowratings'])
	{
		$vboptions['showvotes'] = intval($vboptions['showvotes']);
		$votequery = "
			IF(votenum >= $vboptions[showvotes], votenum, 0) AS votenum,
			IF(votenum >= $vboptions[showvotes] AND votenum > 0, votetotal / votenum, 0) AS voteavg,
		";
	}
	else
	{
		$votequery = '';
	}

	if ($vboptions['threadpreview'] > 0)
	{
		$previewfield = "post.pagetext AS preview,";
		$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
	}
	else
	{
		$previewfield = '';
		$previewjoin = '';
	}

	$getthreadids = $DB_site->query("
		SELECT " . iif($sortfield == 'voteavg', $votequery) . " threadid
		FROM " . TABLE_PREFIX . "thread AS thread
		$deljoin
		WHERE forumid = $foruminfo[forumid]
			AND sticky = 0
			AND visible = 1
			$globalignore
			$datecut
			$limitothers
			$delthreadlimit
		ORDER BY sticky DESC, $sqlsortfield $sqlsortorder
		LIMIT $limitlower, $perpage
	");

	$ids = '';
	while ($thread = $DB_site->fetch_array($getthreadids))
	{
		$ids .= ',' . $thread['threadid'];
	}

	$ids .= $stickyids;

	$DB_site->free_result($getthreadids);
	unset ($thread, $getthreadids);

	$threads = $DB_site->query("
		SELECT $votequery $previewfield
			thread.threadid, thread.title AS threadtitle, lastpost, thread.forumid, pollid, open, replycount, postusername, postuserid, thread.iconid AS threadiconid,
			lastposter, thread.dateline, IF(views<=replycount, replycount+1, views) AS views, notes, thread.visible, sticky, votetotal, thread.attach
			" . iif($vboptions['threadsubscribed'] AND $bbuserinfo['userid'], ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed") . "
			" . iif(!$deljoin, ", NOT ISNULL(deletionlog.primaryid) AS isdeleted, deletionlog.userid AS del_userid,
				deletionlog.username AS del_username, deletionlog.reason AS del_reason") . "
		FROM " . TABLE_PREFIX . "thread AS thread
		" . iif(!$deljoin, " LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND type = 'thread')") . "
		" . iif($vboptions['threadsubscribed'] AND $bbuserinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON(subscribethread.threadid = thread.threadid AND subscribethread.userid = $bbuserinfo[userid])") . "
		$previewjoin
		WHERE thread.threadid IN (0$ids)
		ORDER BY sticky DESC, $sqlsortfield $sqlsortorder
	");
	unset($limitothers, $delthreadlimit, $deljoin,$datecut, $votequery, $sqlsortfield, $sqlsortorder, $threadids);

	// Get Dot Threads
	$dotthreads = fetch_dot_threads_array($ids);
	if ($vboptions['showdots'] AND $bbuserinfo['userid'])
	{
		$show['dotthreads'] = true;
	}
	else
	{
		$show['dotthreads'] = false;
	}

	unset($ids);

	// prepare sort things for column header row:
	$sorturl = "forumdisplay.php?$session[sessionurl]f=$forumid&amp;daysprune=$daysprune";
	$oppositesort = iif($sortorder == 'asc', 'desc', 'asc');

	if ($totalthreads > 0 OR $stickyids)
	{
		if ($totalthreads > 0)
		{
			$limitlower++;
		}
		// check to see if there are any threads to display. If there are, do so, otherwise, show message

		if ($bbuserinfo['maxposts'] != -1 AND $bbuserinfo['maxposts'])
		{
			$vboptions['maxposts'] = $bbuserinfo['maxposts'];
		}

		// get the icon cache
		$iconcache = unserialize($datastore['iconcache']);

		if ($vboptions['threadpreview'] > 0)
		{
			// Get Buddy List
			$buddy = array();
			if (trim($bbuserinfo['buddylist']))
			{
				$buddylist = preg_split('/( )+/', trim($bbuserinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
				foreach ($buddylist AS $buddyuserid)
				{
					$buddy["$buddyuserid"] = 1;
				}
			}
			DEVDEBUG('buddies: ' . implode(', ', array_keys($buddy)));
			// Get Ignore Users
			$ignore = array();
			if (trim($bbuserinfo['ignorelist']))
			{
				$ignorelist = preg_split('/( )+/', trim($bbuserinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
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

		$show['threads'] = true;
		$threadbits = '';
		$threadbits_sticky = '';

		$counter = 0;
		$toread = 0;
		while ($thread = $DB_site->fetch_array($threads))
		{ // AND $counter++<$perpage)

			// build thread data
			$thread = process_thread_array($thread, $lastread, $foruminfo['allowicons']);

			if ($thread['sticky'])
			{
				$threadbit = &$threadbits_sticky;
			}
			else
			{
				$threadbit = &$threadbits;
			}

			if ($thread['isdeleted'])
			{
				$show['threadtitle'] = iif (can_moderate($forumid) OR ($bbuserinfo['userid'] != 0 AND $bbuserinfo['userid'] == $thread['postuserid']), true, false);
				$show['deletereason'] = iif ($thread['del_reason'] != '', true, false);
				$show['viewthread'] = iif (can_moderate($forumid), true, false);
				$show['managethread'] = iif (can_moderate($forumid, 'candeleteposts') OR can_moderate($forumid, 'canremoveposts') OR can_moderate($forumid, 'canmanagethreads'), true, false);
				eval('$threadbit .= "' . fetch_template('threadbit_deleted') . '";');
			}
			else
			{
				eval('$threadbit .= "' . fetch_template('threadbit') . '";');
			}
		}
		$DB_site->free_result($threads);
		unset($thread, $counter);

		$pagenav = construct_page_nav($totalthreads, "forumdisplay.php?$session[sessionurl]f=$forumid", "&amp;sort=$sortfield&amp;order=$sortorder&amp;pp=$perpage&amp;daysprune=$daysprune");

		eval('$sortarrow[' . $sortfield . '] = "' . fetch_template('forumdisplay_sortarrow') . '";');
	}
	unset($threads, $dotthreads);

	// get colspan for bottom bar
	$foruminfo['bottomcolspan'] = 6;
	if ($foruminfo['allowicons'])
	{
		$foruminfo['bottomcolspan']++;
	}
	if ($foruminfo['allowratings'])
	{
		$foruminfo['bottomcolspan']++;
	}

	$show['threadslist'] = true;

	/////////////////////////////////
} // end forum can contain threads
else
{
	$show['threadslist'] = false;
}
/////////////////////////////////
if ($newthreads < 1 AND $unreadchildforums < 1)
{
	// mark a single forum as read as it appears all threads are read
	set_bbarray_cookie('forum_view', $foruminfo['forumid'], TIMENOW);
}
construct_forum_rules($foruminfo, $forumperms);

//remove html to stop the breaking of the meta description
$foruminfo['description'] = strip_tags($foruminfo['description']);

$show['forumdescription'] = iif ($foruminfo['description'] != '', true, false);
$show['forumsearch'] = iif ($forumperms & CANSEARCH AND $vboptions['enablesearches'], true, false);
$show['forumslist'] = iif ($forumshown, true, false);
$show['stickies'] = iif ($threadbits_sticky != '', true, false);

eval('print_output("' . fetch_template('FORUMDISPLAY') . '");');


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12825 $
|| ####################################################################
\*======================================================================*/
?>