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
define('THIS_SCRIPT', 'subscription');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user', 'forumdisplay');

// get special data templates from the datastore
$specialtemplates = array(
	'iconcache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'USERCP_SHELL',
	'usercp_nav_folderbit',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'viewsubscription' => array(
		'forumdisplay_multipagenav_more',
		'forumdisplay_multipagenav_pagenumber',
		'forumdisplay_multipagenav',
		'forumdisplay_gotonew',
		'forumdisplay_sortarrow',
		'threadbit',
		'SUBSCRIBE'
	),
	'addsubscription' => array(
		'subscribe_choosetype'
	),
	'editfolders' => array(
		'subscribe_folderbit',
		'subscribe_showfolders'
	),
	'dostuff' => array(
		'subscribe_move'
	)
);

$actiontemplates['none'] = &$actiontemplates['viewsubscription'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_user.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if ($bbuserinfo['userid'] == 0 OR !($permissions['forumpermissions'] & CANVIEW) OR $bbuserinfo['usergroupid'] == 3 OR $bbuserinfo['usergroupid'] == 4 OR ($permissions['genericoptions'] & ISBANNEDGROUP))
{
	print_no_permission();
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'viewsubscription';
}

// select correct part of forumjump
$frmjmpsel['subs'] = 'class="fjsel" selected="selected"';
construct_forum_jump();

// start the navbits breadcrumb
$navbits = array("usercp.php?$session[sessionurl]" => $vbphrase['user_control_panel']);

// ############################### start add subscription ###############################
if ($_REQUEST['do'] == 'addsubscription')
{
	globalize($_REQUEST, array('emailupdate' => INT, 'folderid' => INT));

	$_REQUEST['forceredirect'] = 1;

	if ($threadid)
	{
		require_once('./includes/functions_misc.php');

		$type = 'thread';
		$id = $threadinfo['threadid'];

		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & CANVIEW))
		{
			print_no_permission();
		}

		// check if there is a forum password and if so, ensure the user has it set
		verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

		if (!$_POST['threadid'])
		{
			// select the correct option
			$choice = verify_subscription_choice($bbuserinfo['autosubscribe'], $bbuserinfo, 9999);
			if ($choice == 9999)
			{
				$choice = 1;
			}

			$emailselected = array($choice => HTML_SELECTED);
			$emailchecked = array($choice => HTML_CHECKED);

			// check if there is a forum password and if so, ensure the user has it set
			verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

			$folderbits = construct_folder_jump(1);

			// find out what type of updates they want
			$navbits["subscription.php?$session[sessionurl]do=viewsubscription"] = $vbphrase['subscriptions'];

			$show['folders'] = iif ($folderbits != '', true, false);
		}
		else
		{
			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid)
				VALUES ($bbuserinfo[userid], $id, $emailupdate, $folderid)
			");

			$url = "showthread.php?$session[sessionurl]t=$id";
			eval(print_standard_redirect('redirect_subsadd_thread'));
		}

	}
	else if ($forumid)
	{
		$type = 'forum';
		$id = $foruminfo['forumid'];

		$forumperms = fetch_permissions($id);
		if (!($forumperms & CANVIEW))
		{
			print_no_permission();
		}

		// check if there is a forum password and if so, ensure the user has it set
		verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

		if (!$_POST['forumid'])
		{
			$emailselected = array(0 => HTML_SELECTED);
			$emailchecked = array(0 => HTML_CHECKED);
		}
		else
		{

			$DB_site->query("
				REPLACE INTO " . TABLE_PREFIX . "subscribeforum (userid, emailupdate, forumid)
				VALUES ($bbuserinfo[userid], $emailupdate, $forumid)
			");

			$url = "forumdisplay.php?$session[sessionurl]f=$id";
			eval(print_standard_redirect('redirect_subsadd_forum'));
		}
	}
	else
	{
		eval(print_standard_error('nosubtype'));
	}


	$navbits[''] = $vbphrase['add_subscription'];
	$navbits = construct_navbits($navbits);

	$show['subscribetothread'] = iif ($type == 'thread', true, false);

	construct_usercp_nav();
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('subscribe_choosetype') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

}

// ############################### start remove subscription ###############################
if ($_REQUEST['do'] == 'removesubscription' OR $_REQUEST['do'] == 'usub')
{

	globalize($_REQUEST, array('url', 'return'));

	if ($threadid)
	{
		$type = 'thread';
		$id = $threadid;
	}
	else if ($forumid)
	{
		$type = 'forum';
		$id = $forumid;
	}
	else
	{
		eval(print_standard_error('nosubtype'));
	}

	if (!$url)
	{
		$url = "usercp.php?$session[sessionurl]";
	}

	$id = verify_id($type, $id);
	$_REQUEST['forceredirect'] = true;

	if ($type == 'thread')
	{
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "subscribethread
			WHERE userid = $bbuserinfo[userid]
				AND threadid = $id
		");

		if ($return == 'ucp')
		{
			$url = "usercp.php?$session[sessionurl]";
		}
		else
		{
			$url = "showthread.php?$session[sessionurl]t=$id";
		}

		eval(print_standard_redirect('redirect_subsremove_thread'));
	}
	else
	{
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "subscribeforum
			WHERE userid = $bbuserinfo[userid]
				AND forumid = $id
		");

		eval(print_standard_redirect('redirect_subsremove_forum'));
	}
}

// ############################### start view threads ###############################
if ($_REQUEST['do'] == 'viewsubscription')
{

	globalize($_REQUEST , array('type' , 'folderid', 'perpage' => INT, 'pagenumber' => INT, 'sortfield', 'sortorder'));

	if ($folderid == 'all')
	{
		$getallfolders = true;
		$show['allfolders'] = true;
	}
	else
	{
		$folderid = intval($folderid);
	}

	$folderselect["$folderid"] = HTML_SELECTED;

	require_once('./includes/functions_misc.php');
	$folderjump = construct_folder_jump(1, $folderid); // This is the "Jump to Folder"

	// look at sorting options:
	if ($sortorder != 'asc')
	{
		$sortorder = 'desc';
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
		case 'lastpost':
		case 'replycount':
		case 'views':
		case 'postusername':
			$sqlsortfield = 'thread.' . $sortfield;
			break;
		default:
			$sqlsortfield = 'thread.lastpost';
			$sortfield = 'lastpost';
	}
	$sort = array($sortfield => HTML_SELECTED);

	$threadscount = $DB_site->query_first("
		SELECT COUNT(*) AS threads
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = subscribethread.threadid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND type = 'thread')
		WHERE subscribethread.userid = $bbuserinfo[userid] AND subscribethread.threadid = thread.threadid
			AND thread.visible = 1 AND deletionlog.primaryid IS NULL
		" . iif(!$getallfolders, "	AND folderid = $folderid") . "
	");

	$totalallthreads = $threadscount['threads'];

	// set defaults
	sanitize_pageresults($totalallthreads, $pagenumber, $perpage, 200, $vboptions['maxthreads']);

	// display threads
	$limitlower = ($pagenumber - 1) * $perpage + 1;
	$limitupper = ($pagenumber) * $perpage;

	if ($limitupper > $totalallthreads)
	{
		$limitupper = $totalallthreads;
		if ($limitlower > $totalallthreads)
		{
			$limitlower = $totalallthreads - $perpage;
		}
	}
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}

	$getthreads = $DB_site->query("
		SELECT thread.threadid, emailupdate, subscribethreadid, thread.forumid
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = subscribethread.threadid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND type = 'thread')
		WHERE subscribethread.threadid = thread.threadid AND subscribethread.userid = $bbuserinfo[userid]
			AND thread.visible = 1 AND deletionlog.primaryid IS NULL
		" . iif(!$getallfolders, "	AND folderid = $folderid") . "
		ORDER BY $sqlsortfield $sqlsortorder
		LIMIT " . ($limitlower - 1) . ", $perpage
	");

	if ($totalthreads = $DB_site->num_rows($getthreads))
	{
		$forumids = array();
		$threadids = array();
		$emailupdate = array();
		while ($getthread = $DB_site->fetch_array($getthreads))
		{
			$forumids["$getthread[forumid]"] = true;
			$threadids[] = $getthread['threadid'];
			$emailupdate["$getthread[threadid]"] = $getthread['emailupdate'];
			$subscribethread["$getthread[threadid]"] = $getthread['subscribethreadid'];
		}
		$threadids = implode(',', $threadids);
	}
	unset($getthread);
	$DB_site->free_result($getthreads);

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

		// get the icon cache
		$iconcache = unserialize($datastore['iconcache']);

		if (($bbuserinfo['maxposts'] != -1) AND ($bbuserinfo['maxposts'] != 0))
		{
			$vboptions['maxposts'] = $bbuserinfo['maxposts'];
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

		$hasthreads = true;
		$threadbits = '';
		$pagenav = '';
		$counter = 0;
		$toread = 0;

		$vboptions['showvotes'] = intval($vboptions['showvotes']);

		$threads = $DB_site->query("
			SELECT
				IF(votenum >= $vboptions[showvotes], votenum, 0) AS votenum,
				IF(votenum >= $vboptions[showvotes] AND votenum > 0, votetotal / votenum, 0) AS voteavg,
				$previewfield thread.threadid, thread.title AS threadtitle, lastpost, forumid, pollid, open, replycount, postusername,
				postuserid, lastposter, thread.dateline, views, thread.iconid AS threadiconid, notes, thread.visible, thread.attach
			FROM " . TABLE_PREFIX . "thread AS thread
			$previewjoin
			WHERE thread.threadid IN ($threadids)
			ORDER BY $sqlsortfield $sqlsortorder
		");
		unset($sqlsortfield, $sqlsortorder);

		require_once('./includes/functions_forumdisplay.php');

		// Get Dot Threads
		$dotthreads = fetch_dot_threads_array($threadids);
		if ($vboptions['showdots'] AND $bbuserinfo['userid'])
		{
			$show['dotthreads'] = true;
		}
		else
		{
			$show['dotthreads'] = false;
		}

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

		$foruminfo['allowratings'] = true;
		$show['threadicons'] = true;
		$show['notificationtype'] = true;
		$show['threadratings'] = true;
		$show['threadrating'] = true;

		while ($thread = $DB_site->fetch_array($threads))
		{
			$threadid = $thread['threadid'];
			// build thread data
			$thread = process_thread_array($thread, $lastread["$thread[forumid]"], 1);

			switch ($emailupdate["$thread[threadid]"])
			{
				case 0:
					$thread['notification'] = $vbphrase['none'];
					break;
				case 1:
					$thread['notification'] = $vbphrase['instant'];
					break;
				case 2:
					$thread['notification'] = $vbphrase['daily'];
					break;
				case 3:
					$thread['notification'] = $vbphrase['weekly'];
					break;
				default:
					$thread['notification'] = $vbphrase['n_a'];
			}

			eval('$threadbits .= "' . fetch_template('threadbit') . '";');

		}

		$DB_site->free_result($threads);
		unset($threadids);
		$sorturl = "subscription.php?$session[sessionurl]do=viewsubscription&amp;pp=$perpage&amp;folderid=$folderid&amp;type=" . urlencode($type);
		$pagenav = construct_page_nav($totalallthreads, $sorturl . "&amp;sort=$sortfield" . iif(!empty($sortorder), "&amp;order=$sortorder"));
		$oppositesort = iif($sortorder == 'asc', 'desc', 'asc');

		eval('$sortarrow[' . $sortfield . '] = "' . fetch_template('forumdisplay_sortarrow') . '";');

		$show['havethreads'] = true;
	}
	else
	{
		$show['havethreads'] = false;
	}

	$navbits[''] = $vbphrase['subscriptions'];
	$navbits = construct_navbits($navbits);

	// build the cp nav
	construct_usercp_nav('substhreads_listthreads');

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('SUBSCRIBE') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

// ########################## Do move of threads ##############################################
if ($_POST['do'] == 'movethread')
{

	globalize($_POST, array(
		'ids',
		'folderid' => INT
	));

	$ids = unserialize($ids);

	if (!is_array($ids) OR empty($ids))
	{
		$idname = $vbphrase['subscribed_threads'];
		eval(print_standard_error('invalidid'));
	}

	$subids = array();
	foreach ($ids AS $subid)
	{
		$id = intval($subid);
		$subids["$id"] = $id;
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "subscribethread
		SET folderid = $folderid
		WHERE userid = $bbuserinfo[userid] AND subscribethreadid IN(" . implode(', ', $subids) . ")");

	$url = "subscription.php?$session[sessionurl]folderid=$folderid";
	eval(print_standard_redirect('sub_threadsmoved'));

}

// ########################## Start Move / Delete / Update Email ##############################
if ($_POST['do'] == 'dostuff')
{

	globalize($_POST, array(
		'deletebox',
		'folderid',
		'emailupdate',
		'oldemailupdate',
		'what' => STR,
		)
	);

	if ($folderid != 'all')
	{
		$folderid = intval($folderid);
	}

	if (!is_array($deletebox))
	{
		eval(print_standard_error('error_subsnoselected'));
	}

	if (strstr($what, 'update'))
	{
		$notifytype = intval($what[6]);
		if ($notifytype < 0 OR $notifytype > 3)
		{
			$notifytype = 0;
		}
		$what = 'update';
	}

	switch($what)
	{
		// *************************
		// Delete Subscribed Threads
		case 'delete':

			$ids = '';
			foreach ($deletebox AS $threadid => $value)
			{
				$ids .= ',' . intval($threadid);
			}
			if ($ids)
			{
				$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE subscribethreadid IN (0$ids) AND userid = $bbuserinfo[userid]");
			}
			$url = "subscription.php?$session[sessionurl]do=viewsubscription&amp;folderid=$folderid";
			eval(print_standard_redirect('redirect_subupdate'));
			break;

		// *************************
		// Move to new Folder
		case 'move':

			$ids = array();
			foreach ($deletebox AS $id => $value)
			{
				$id = intval($id);
				$ids["$id"] = $id;
			}

			$numthreads = sizeof($ids);

			$ids = serialize($ids);
			unset($id, $deletebox);

			require_once('./includes/functions_misc.php');

			if ($folderid === 'all')
			{
				$exclusions = false;
			}
			else
			{
				$exclusions = array($folderid, -1);
			}

			$folderoptions = construct_folder_jump(1, 0, $exclusions);

			if ($folderoptions)
			{
				if ($folderid === 'all')
				{
					$fromfolder = $vbphrase['all'];
				}
				else
				{
					$folders = unserialize($bbuserinfo['subfolders']);
					$fromfolder = $folders["$folderid"];
				}

				// build the cp nav
				construct_usercp_nav('substhreads_listthreads');

				$navbits[''] = $vbphrase['subscriptions'];
				$navbits = construct_navbits($navbits);
				eval('$navbar = "' . fetch_template('navbar') . '";');

				eval('$HTML = "' . fetch_template('subscribe_move') . '";');
				eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
			}
			else
			{
				eval(print_standard_error('pm_nofolders'));
			}

			$url = "subscription.php?$session[sessionurl]do=viewsubscription&amp;folderid=$folderid";
			eval(print_standard_redirect('redirect_submove'));
			break;

		// *************************
		// Change Notification Type
		case 'update':

			$ids = '';
			foreach ($deletebox AS $threadid => $value)
			{
				$ids .= ',' . intval($threadid);
			}
			if ($ids)
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "subscribethread
					SET emailupdate = $notifytype
					WHERE subscribethreadid IN (0$ids) AND
						userid = $bbuserinfo[userid]
				");
			}

			$url = "subscription.php?$session[sessionurl]do=viewsubscription&amp;folderid=$folderid";
			eval(print_standard_redirect('redirect_subupdate'));
			break;

		// *****************************
		// unknown action specified
		default:
			$idname = $vbphrase['action'];
			eval(print_standard_error('invalidid'));
	}
}

// ############################### start edit folders ###############################
if ($_REQUEST['do'] == 'editfolders')
{

	$folders = unserialize($bbuserinfo['subfolders']);

	if (!$folders[0])
	{
		$defaultfolder = $vbphrase['subscriptions'];
		$folders[0] = $defaultfolder;
	}
	else
	{
		$defaultfolder = $folders[0];
	}

	natcasesort($folders);

	if (is_array($folders))
	{
		$foldercount = 1;
		foreach ($folders AS $folderid => $title)
		{
			eval('$folderboxes .= "' . fetch_template('subscribe_folderbit') . '";');
			$foldercount++;
		}
	}

	$foldercount = 1;
	$folderid = 0;
	$title = '';
	while ($foldercount < 4)
	{
		for ($x = $folderid + 1; 1 == 1; $x++)
		{
			if (!$folders["$x"])
			{
				$folderid = $x;
				break;
			}
		}
		eval('$newfolderboxes .= "' . fetch_template('subscribe_folderbit') . '";');
		$foldercount++;
	}

	// generate navbar
	$navbits["subscription.php?$session[sessionurl]do=viewsubscription"] = $vbphrase['subscriptions'];
	$navbits[''] = $vbphrase['edit_folders'];
	$navbits = construct_navbits($navbits);

	// build the cp nav
	construct_usercp_nav('substhreads_editfolders');

	$show['customfolders'] = iif($folderboxes != '', true, false);

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('subscribe_showfolders') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

} #end editfolders

// ############################### start update folders ###############################
if ($_POST['do'] == 'doeditfolders')
{

	globalize($_POST , array('folderlist'));

	$folders = unserialize($bbuserinfo['subfolders']);

	if (is_array($folderlist))
	{
		foreach ($folderlist AS $folderid => $title)
		{
			$title = trim($title);
			$folderid = intval($folderid);

			if (empty($title))
			{
				if ($folders["$folderid"])
				{
					$deletefolders .= iif($deletefolders, ',', '') . $folderid;
				}
				unset($folders["$folderid"]);
			}
			else
			{
				$folders["$folderid"] = htmlspecialchars_uni($title);
			}

		}
		if ($deletefolders)
		{
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "subscribethread
				SET folderid = 0
				WHERE folderid IN ($deletefolders) AND
					userid = $bbuserinfo[userid]
			");
		}
		if (!empty($folders))
		{
			natcasesort($folders);
		}

		require_once('./includes/functions_databuild.php');
		build_usertextfields('subfolders', iif(empty($folders), '', serialize($folders)));
	}

	$itemtype = $vbphrase['subscription'];
	$itemtypes = $vbphrase['subscriptions'];
	$url = "subscription.php?$session[sessionurl]do=viewsubscription";
	eval(print_standard_redirect('foldersedited'));

} #end doeditfolders


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12825 $
|| ####################################################################
\*======================================================================*/
?>