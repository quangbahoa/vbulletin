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
define('THIS_SCRIPT', 'usercp');
define('NO_REGISTER_GLOBALS', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user');

// get special data templates from the datastore
$specialtemplates = array(
	'iconcache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'USERCP_SHELL',
	'USERCP',
	'usercp_nav_folderbit',
	// subscribed threads templates
	'threadbit',
	// subscribed forums templates
	'forumhome_forumbit_level1_post',
	'forumhome_forumbit_level1_nopost',
	'forumhome_forumbit_level2_post',
	'forumhome_forumbit_level2_nopost',
	'forumhome_subforumbit_nopost',
	'forumhome_subforumbit_post',
	'forumhome_subforumseparator_nopost',
	'forumhome_subforumseparator_post',
	'forumhome_lastpostby',
	'forumhome_moderator',
	// private messages templates
	'pm_messagelist_userbit',
	'pm_messagelistbit',
	'pm_messagelistbit_user',
	'pm_messagelistbit_ignore',
	// reputation templates
	'usercp_reputationbits'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_forumlist.php');
require_once('./includes/functions_user.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!$bbuserinfo['userid'] OR !($permissions['forumpermissions'] & CANVIEW))
{
	print_no_permission();
}

// main page:

// ############################### start reputation ###############################

$show['reputation'] = false;
if ($vboptions['reputationenable'] AND ($bbuserinfo['showreputation'] OR !($permissions['genericpermissions'] & CANHIDEREP)))
{

	$vboptions['showuserrates'] = intval($vboptions['showuserrates']);
	$vboptions['showuserraters'] = $permissions['genericpermissions'] & CANSEEOWNREP;
	$reputations = $DB_site->query("
		SELECT
			user.username, reputation.whoadded,
			reputation.postid as postid,
			reputation.reputation, reputation.reason,
			post.threadid as threadid,
			reputation.dateline as dateline,
			thread.title as title
		FROM " . TABLE_PREFIX . "reputation AS reputation
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON(reputation.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(post.threadid = thread.threadid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = reputation.whoadded)
		WHERE reputation.userid = $bbuserinfo[userid]
		" . iif($vboptions['showuserraters'] AND trim($bbuserinfo['ignorelist']), " AND reputation.whoadded NOT IN (0," . str_replace(' ', ',', trim($bbuserinfo['ignorelist'])). ")") . "
		ORDER BY reputation.dateline DESC
		LIMIT 0, $vboptions[showuserrates]
	");

	$reputationcommentbits = '';
	if ($vboptions['showuserraters'])
	{
		$reputationcolspan = 5;
		$reputationbgclass = 'alt2';
	}
	else
	{
		$reputationcolspan = 4;
		$reputationbgclass = 'alt1';
	}

	require_once('./includes/functions_bbcodeparse.php');
	while ($reputation=$DB_site->fetch_array($reputations))
	{
		if ($reputation['reputation'] > 0)
		{
			$posneg = 'pos';
		}
		else if ($reputation['reputation'] < 0)
		{
			$posneg = 'neg';
		}
		else
		{
			$posneg = 'balance';
		}
		$reputation['timeline'] = vbdate($vboptions['timeformat'], $reputation['dateline']);
		$reputation['dateline'] = vbdate($vboptions['dateformat'], $reputation['dateline']);
		$reputation['reason'] = parse_bbcode($reputation['reason']);
		if (strlen($reputation['title']) > 25)
		{
			$reputation['title'] = substr($reputation['title'], 0, 23) . '...';
		}
		eval('$reputationcommentbits .= "' . fetch_template('usercp_reputationbits') . '";');
		$show['reputation'] = true;
	}
}

// ############################### start private messages ###############################

//get ignorelist info
//generates a hash, in the form of $ignore[(userid)]
//run checks to it by seeing if $ignore[###] returns anything
//if so, then user is ignored

$show['privatemessages'] = false;
if ($permissions['pmquota'] > 0)
{
	$pms = $DB_site->query("
		SELECT pm.*, pmtext.*
		" . iif($vboptions['privallowicons'], ',icon.iconpath, icon.title AS icontitle') . "
		FROM " . TABLE_PREFIX . "pm AS pm
		INNER JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
		" . iif($vboptions['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
		WHERE pm.userid = $bbuserinfo[userid]
		AND pmtext.dateline > $bbuserinfo[lastvisit]
		AND pm.messageread = 0
	");
	if ($DB_site->num_rows($pms))
	{
		// get ignored users
		if (empty($bbuserinfo['ignorelist']))
		{
			$ignoreusers = array();
		}
		else
		{
			$ignoreusers = explode(' ', $bbuserinfo['ignorelist']);
		}

		$messagelistbits = '';
		$show['pmcheckbox'] = false;
		$numpms = 0;

		require_once('./includes/functions_bigthree.php');
		while ($pm = $DB_site->fetch_array($pms))
		{
			if (in_coventry($pm['fromuserid']))
			{
				if (!can_moderate())
				{
					continue;
				}
				else
				{
					eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit_ignore') . '";');
					$numpms ++;
					$show['privatemessages'] = true;
				}
			}
			else if (in_array($pm['fromuserid'], $ignoreusers))
			{
				eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit_ignore') . '";');
				$numpms ++;
				$show['privatemessages'] = true;
			}
			else
			{
				$pm['senddate'] = vbdate($vboptions['dateformat'], $pm['dateline'], 1);
				$pm['sendtime'] = vbdate($vboptions['timeformat'], $pm['dateline']);
				$pm['statusicon'] = 'new';
				$userid = &$pm['fromuserid'];
				$username = &$pm['fromusername'];

				$show['pmicon'] = iif($pm['iconpath'], true, false);
				$show['unread'] = iif(!$pm['messageread'], true, false);

				eval('$userbit = "' . fetch_template('pm_messagelistbit_user') . '";');
				eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit') . '";');
				$numpms ++;
				$show['privatemessages'] = true;
			}
		}
	}
}


// ############################### start subscribed forums ###############################

// get only subscribed forums
cache_ordered_forums(1, 0, $bbuserinfo['userid']);
$show['forums'] = false;
foreach ($forumcache AS $forumid => $forum)
{
	if ($forum['subscribeforumid'] != '')
	{
		$show['forums'] = true;
	}
}
if ($show['forums'])
{
	if ($vboptions['showmoderatorcolumn'])
	{
		cache_moderators();
	}
	else
	{
		$imodcache = array();
		$mod = array();
	}
	fetch_last_post_array();
	$forumbits = construct_forum_bit(-1, 0, 1);
	if ($forumshown == 1)
	{
		$show['forums'] = true;
	}
	else
	{
		$show['forums'] = false;
	}
}

// ############################### start new subscribed to threads ###############################

if (!$bbuserinfo['lastvisit'])
{
	$thelastvisit = TIMENOW;
}
else
{
	$thelastvisit = $bbuserinfo['lastvisit'];
}

$show['threads'] = false;
$numthreads = 0;

// query thread ids
$getthreads = $DB_site->query("
	SELECT thread.threadid, thread.forumid
	FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
	LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(subscribethread.threadid = thread.threadid)
	LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND type = 'thread')
	WHERE subscribethread.userid = $bbuserinfo[userid]
	AND thread.visible = 1
	AND lastpost > $bbuserinfo[lastvisit]
	AND deletionlog.primaryid IS NULL
");
if ($totalthreads = $DB_site->num_rows($getthreads))
{
	$forumids = array();
	$threadids = array();
	while ($getthread = $DB_site->fetch_array($getthreads))
	{
		$forumids["$getthread[forumid]"] = true;
		$threadids[] = $getthread['threadid'];
	}
	$threadids = implode(',', $threadids);
}
unset($getthread);
$DB_site->free_result($getthreads);

// if there are some results to show, query the data
if ($totalthreads)
{
	// get last read info for each thread
	$lastread = array();
	foreach (array_keys($forumids) AS $forumid)
	{
		if ($bbforumview = intval(fetch_bbarray_cookie('forum_view', $forumid)))
		{
			$lastread["$forumid"] = $bbforumview;
		}
		else
		{
			$lastread["$forumid"] = $bbuserinfo['lastvisit'];
		}
	}

	// get thread preview?
	if ($vboptions['threadpreview'] > 0)
	{
		$previewfield = 'post.pagetext AS preview,';
		$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
	}
	else
	{
		$previewfield = '';
		$previewjoin = '';
	}

	$getthreads = $DB_site->query("
		SELECT $previewfield
			thread.threadid, thread.title AS threadtitle, lastpost, forumid, pollid, open, replycount, postusername, postuserid, lastposter,
			thread.dateline, views, thread.iconid AS threadiconid, notes, thread.visible
		FROM " . TABLE_PREFIX . "thread AS thread
		$previewjoin
		WHERE thread.threadid IN($threadids)
		ORDER BY lastpost DESC
	");

	require_once('./includes/functions_forumdisplay.php');

	// Get Dot Threads
	$dotthreads = fetch_dot_threads_array($threadids);

	// check to see if there are any threads to display. If there are, do so, otherwise, show message
	if ($totalthreads = $DB_site->num_rows($getthreads))
	{
		$threads = array();
		while ($getthread = $DB_site->fetch_array($getthreads))
		{
			$threads["$getthread[threadid]"] = $getthread;
		}
	}
	unset($getthread);
	$DB_site->free_result($getthreads);

	$show['threadratings'] = false;

	if ($totalthreads)
	{
		$show['threadicons'] = true;

		if (($bbuserinfo['maxposts'] != -1) AND ($bbuserinfo['maxposts'] != 0))
		{
		   $vboptions['maxposts'] = $bbuserinfo['maxposts'];
		}

		// get the icon cache
		$iconcache = unserialize($datastore['iconcache']);

		if ($vboptions['threadpreview'] AND $bbuserinfo['ignorelist'])
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

		$threadbits = '';
		foreach ($threads AS $threadid => $thread)
		{
			$thread = process_thread_array($thread, $lastread["$thread[forumid]"]);
			$show['unsubscribe'] = true;
			eval('$threadbits .= "' . fetch_template('threadbit') . '";');
			$numthreads ++;
		}

		$show['threads'] = true;
	}
}

require_once('./includes/functions_misc.php');

// check if user can be invisible and is invisible
$allowinvisible = bitwise($permissions['genericpermissions'], CANINVISIBLE);
if (!$allowinvisible AND $bbuserinfo['invisible'])
{
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user
		SET options = OPTIONS - $_USEROPTIONS[invisible]
		WHERE userid = $bbuserinfo[userid]
			AND OPTIONS & $_USEROPTIONS[invisible]
	");
}

// draw cp nav bar
construct_usercp_nav('usercp');

$frmjmpsel['usercp'] = 'class="fjsel" selected="selected"';
construct_forum_jump();

eval('$HTML = "' . fetch_template('USERCP') . '";');

// build navbar
$navbits = construct_navbits(array('' => $vbphrase['user_control_panel']));
eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13650 $
|| ####################################################################
\*======================================================================*/
?>