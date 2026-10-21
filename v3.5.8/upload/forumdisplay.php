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
define('THIS_SCRIPT', 'forumdisplay');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('forumdisplay', 'inlinemod');

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
		'forumrules',
		'threadadmin_imod_menu_thread',
	)
);

// ####################### PRE-BACK-END ACTIONS ##########################
function exec_postvar_call_back()
{
	global $vbulletin;

	$vbulletin->input->clean_array_gpc('r', array(
		'forumid'	=> TYPE_STR,
	));

	// jump from forumjump
	switch ($vbulletin->GPC['forumid'])
	{
		case 'search':	$goto = 'search'; break;
		case 'pm':		$goto = 'private'; break;
		case 'wol':		$goto = 'online'; break;
		case 'cp':		$goto = 'usercp'; break;
		case 'subs':	$goto = 'subscription'; break;
		case 'home':
		case '-1':		$goto = $vbulletin->options['forumhome']; break;
	}

	// intval() forumid since having text in it is not expected anywhere else and it can't be "cleaned" a second time
	$vbulletin->GPC['forumid'] = intval($vbulletin->GPC['forumid']);

	if ($goto != '')
	{
		if (!empty($vbulletin->session->vars['sessionurl_js']))
		{
			exec_header_redirect("$goto.php?" . $vbulletin->session->vars['sessionurl_js']);
		}
		else
		{
			exec_header_redirect("$goto.php");
		}
	}
	// end forumjump redirects
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_forumlist.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumdisplay.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

($hook = vBulletinHook::fetch_hook('forumdisplay_start')) ? eval($hook) : false;

// ############################### start mark forums read ###############################
if ($_REQUEST['do'] == 'markread')
{
	if (!$foruminfo['forumid'])
	{
		if ($vbulletin->userinfo['userid'])
		{
			// init user data manager
			$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
			$userdata->set_existing($vbulletin->userinfo);
			$userdata->set('lastactivity', TIMENOW);
			$userdata->set('lastvisit', TIMENOW - 1);
			$userdata->save();

			if ($vbulletin->options['threadmarking'])
			{
				$query = '';
				foreach ($vbulletin->forumcache AS $fid => $finfo)
				{
					// mark the forum and all child forums read
					$query .= ", ($fid, " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ")";
				}

				if ($query)
				{
					$query = substr($query, 2);
					$db->query_write("
						REPLACE INTO " . TABLE_PREFIX . "forumread
							(forumid, userid, readtime)
						VALUES
							$query
					");
				}
			}
		}
		else
		{
			vbsetcookie('lastvisit', TIMENOW);
		}

		$vbulletin->url = $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'];
		eval(print_standard_redirect('markread'));
	}
	else
	{
		// temp work around code, I need to find another way to mass set some values to the cookie
		$vbulletin->input->clean_gpc('c', COOKIE_PREFIX . 'forum_view', TYPE_STR);
		$bb_cache_forum_view = unserialize(convert_bbarray_cookie($vbulletin->GPC[COOKIE_PREFIX . 'forum_view']));

		require_once(DIR . '/includes/functions_misc.php');
		$childforums = fetch_child_forums($foruminfo['forumid'], 'ARRAY');
		if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
		{
			$query = "($foruminfo[forumid], " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ")";

			foreach ($childforums AS $val)
			{
				// mark the forum and all child forums read
				$query .= ", ($val, " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ")";
			}

			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "forumread
					(forumid, userid, readtime)
				VALUES
					$query
			");
		}
		else
		{
			foreach ($childforums AS $val)
			{
				// mark the forum and all child forums read
				$bb_cache_forum_view["$val"] = TIMENOW;
			}
			set_bbarray_cookie('forum_view', $foruminfo['forumid'], TIMENOW);
		}

		if ($foruminfo['parentid'] == -1)
		{
			$vbulletin->url = $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'];
		}
		else
		{
			$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[parentid]";
		}
		eval(print_standard_redirect('markread_single'));
	}
}

// Don't allow access to anything below if an invalid $forumid was specified
if (!$foruminfo['forumid'])
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['forum'], $vbulletin->options['contactuslink'])));
}

// ############################### start enter password ###############################
if ($_REQUEST['do'] == 'doenterpwd')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'newforumpwd' => TYPE_STR,
		'url' => TYPE_STR,
		'postvars' => TYPE_STR,
	));

	if ($foruminfo['password'] == $vbulletin->GPC['newforumpwd'])
	{
		// set a temp cookie for guests
		if (!$vbulletin->userinfo['userid'])
		{
			set_bbarray_cookie('forumpwd', $foruminfo['forumid'], md5($vbulletin->userinfo['userid'] . $vbulletin->GPC['newforumpwd']));
		}
		else
		{
			set_bbarray_cookie('forumpwd', $foruminfo['forumid'], md5($vbulletin->userinfo['userid'] . $vbulletin->GPC['newforumpwd']), 1);
		}

		if ($vbulletin->GPC['url'] == $vbulletin->options['forumhome'] . '.php')
		{
			$vbulletin->GPC['url'] = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[forumid]";
		}
		else if ($vbulletin->GPC['url'] != '' AND $vbulletin->GPC['url'] != 'forumdisplay.php')
		{
			$vbulletin->GPC['url'] = str_replace('"', '', $vbulletin->GPC['url']);
		}
		else
		{
			$vbulletin->GPC['url'] = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[forumid]";
		}

		// Allow POST based redirection...
		if ($vbulletin->GPC['postvars'] != '')
		{
			$temp = unserialize($vbulletin->GPC['postvars']);
			if ($temp['do'] != 'doenterpwd')
			{ // ...but prevent an infinite loop
				require_once(DIR . '/includes/functions_misc.php');
				$vbulletin->GPC['postvars'] = construct_hidden_var_fields($vbulletin->GPC['postvars']);
			}
			else
			{
				$vbulletin->GPC['postvars'] = '';
			}
		}

		eval(print_standard_redirect('forumpasswordcorrect'));
	}
	else
	{
		require_once(DIR . '/includes/functions_misc.php');

		$vbulletin->GPC['url'] = str_replace('&amp;', '&', $vbulletin->GPC['url']);
		$postvars = construct_post_vars_html();
		eval(standard_error(fetch_error('forumpasswordincorrect',
			$vbulletin->session->vars['sessionhash'],
			htmlspecialchars_uni($vbulletin->GPC['url']),
			$foruminfo['forumid'],
			$postvars,
			$stylevar['cellpadding'],
			$stylevar['cellspacing']
		)));
	}
}

// ###### END SPECIAL PATHS

// These $_REQUEST values will get used in the sort template so they are assigned to normal variables
$perpage =  $vbulletin->input->clean_gpc('r', 'perpage', TYPE_UINT);
$pagenumber = $vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);
$daysprune = $vbulletin->input->clean_gpc('r', 'daysprune', TYPE_INT);
$sortfield = $vbulletin->input->clean_gpc('r', 'sortfield', TYPE_STR);

// get permission to view forum
$_permsgetter_ = 'forumdisplay';
$forumperms = fetch_permissions($foruminfo['forumid']);
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
{
	print_no_permission();
}

// disable thread preview if we can't view threads
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
{
	$vbulletin->options['threadpreview'] = 0;
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$show['newthreadlink'] = iif(!$show['search_engine'] AND $foruminfo['allowposting'], true, false);
$show['threadicons'] = iif ($foruminfo['allowicons'], true, false);
$show['threadratings'] = iif ($foruminfo['allowratings'], true, false);

// get vbulletin->iforumcache - for use by makeforumjump and forums list
// fetch the forum even if they are invisible since its needed
// for the title but we'll unset that further down
cache_ordered_forums(1, 1);

if (!$daysprune)
{
	if ($vbulletin->userinfo['daysprune'])
	{
		$daysprune = $vbulletin->userinfo['daysprune'];
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
	$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
	$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
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

		($hook = vBulletinHook::fetch_hook('forumdisplay_moderator')) ? eval($hook) : false;

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

// get an array of child forum ids for this forum
$foruminfo['childlist'] = explode(',', $foruminfo['childlist']);

$comma = '';

// define max depth for forums display based on $vbulletin->options[forumhomedepth]
define('MAXFORUMDEPTH', $vbulletin->options['forumdisplaydepth']);

if ($vbulletin->options['showforumusers'])
{
	$datecut = TIMENOW - $vbulletin->options['cookietimeout'];
	$forumusers = $db->query_read("
		SELECT user.username, (user.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ") AS invisible, user.usergroupid, session.userid, session.inforum, session.lastactivity,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
		FROM " . TABLE_PREFIX . "session AS session
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = session.userid)
		WHERE session.lastactivity > $datecut
		ORDER BY" . iif($vbulletin->options['showforumusers'] == 1, " username ASC,") . " lastactivity DESC
	");

	$numberregistered = 0;
	$numberguest = 0;
	$doneuser = array();

	if ($vbulletin->userinfo['userid'])
	{
		// fakes the user being in this forum
		$vbulletin->userinfo['joingroupid'] = iif($vbulletin->userinfo['displaygroupid'], $vbulletin->userinfo['displaygroupid'], $vbulletin->userinfo['usergroupid']);
		$loggedin = array(
			'userid' => $vbulletin->userinfo['userid'],
			'username' => $vbulletin->userinfo['username'],
			'invisible' => $vbulletin->userinfo['invisible'],
			'invisiblemark' => $vbulletin->userinfo['invisiblemark'],
			'inforum' => $foruminfo['forumid'],
			'lastactivity' => TIMENOW,
			'musername' => fetch_musername($vbulletin->userinfo, 'joingroupid')
		);
		$numberregistered = 1;
		fetch_online_status($loggedin);

		($hook = vBulletinHook::fetch_hook('forumdisplay_loggedinuser')) ? eval($hook) : false;

		eval('$activeusers = "' . fetch_template('forumdisplay_loggedinuser') . '";');
		$doneuser["{$vbulletin->userinfo['userid']}"] = 1;
		$comma = ', ';
	}

	$inforum = array();

	// this require the query to have lastactivity ordered by DESC so that the latest location will be the first encountered.
	while ($loggedin = $db->fetch_array($forumusers))
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

					($hook = vBulletinHook::fetch_hook('forumdisplay_loggedinuser')) ? eval($hook) : false;

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

	if (!$vbulletin->userinfo['userid'])
	{
		$numberguest = ($numberguest == 0) ? 1 : $numberguest;
	}
	$totalonline = $numberregistered + $numberguest;
	unset($joingroupid, $key, $datecut , $comma, $invisibleuser, $userinfo, $userid, $loggedin, $index, $value, $forumusers, $parentarray );

	$show['activeusers'] = true;
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

	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
	{
		$lastread_child = max($vbulletin->forumcache["$val"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
	}
	else
	{
		$lastread_child = max(intval(fetch_bbarray_cookie('forum_view', $val)), $vbulletin->userinfo['lastvisit']);
	}

	if ($vbulletin->forumcache["$val"]['lastpost'] >= $lastread_child)
	{
		$unreadchildforums = 1;
		break;
	}
}

$forumbits = construct_forum_bit($foruminfo['forumid']);

if (can_moderate($foruminfo['forumid']))
{
	$show['adminoptions'] = true;
}
else
{
	$show['adminoptions'] = false;
}
if ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
{
	$show['addmoderator'] = true;
}
else
{
	$show['addmoderator'] = false;
}

$curforumid = $foruminfo['forumid'];
construct_forum_jump();

/////////////////////////////////
if ($foruminfo['cancontainthreads'])
{
	/////////////////////////////////
	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
	{
		$foruminfo['forumread'] = $vbulletin->forumcache["$foruminfo[forumid]"]['forumread'];
		$lastread = max($foruminfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
	}
	else
	{
		$bbforumview = intval(fetch_bbarray_cookie('forum_view', $foruminfo['forumid']));
		$lastread = max($bbforumview, $vbulletin->userinfo['lastvisit']);
	}

	// Inline Moderation
	$show['movethread'] = (can_moderate($forumid, 'canmanagethreads')) ? true : false;
	$show['deletethread'] = (can_moderate($forumid, 'candeleteposts') OR can_moderate($forumid, 'canremoveposts')) ? true : false;
	$show['approvethread'] = (can_moderate($forumid, 'canmoderateposts')) ? true : false;
	$show['openthread'] = (can_moderate($forumid, 'canopenclose')) ? true : false;
	$show['inlinemod'] = ($show['movethread'] OR $show['deletethread'] OR $show['approvethread'] OR $show['openthread']) ? true : false;
	$url = $show['inlinemod'] ? SCRIPTPATH : '';

	// fetch popup menu
	if ($show['popups'] AND $show['inlinemod'])
	{
		eval('$threadadmin_imod_menu_thread = "' . fetch_template('threadadmin_imod_menu_thread') . '";');
	}
	else
	{
		$threadadmin_imod_thread_menu = '';
	}

	// get announcements

	$announcebits = '';

	$announcements = $db->query_read("
		SELECT
			announcementid, startdate, title, announcement.views,
			user.username, user.userid, user.usertitle, user.customtitle
		FROM " . TABLE_PREFIX . "announcement AS announcement
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = announcement.userid)
		WHERE startdate <= " . (TIMENOW - $vbulletin->options['hourdiff']) . "
			AND enddate >= " . (TIMENOW - $vbulletin->options['hourdiff']) . "
			AND " . fetch_forum_clause_sql($foruminfo['forumid'], 'forumid') . "
		ORDER BY startdate DESC
		" . iif($vbulletin->options['oneannounce'], "LIMIT 1"));

	while ($announcement = $db->fetch_array($announcements))
	{
		if ($announcement['customtitle'] == 2)
		{
			$announcement['usertitle'] = htmlspecialchars_uni($announcement['usertitle']);
		}
		$announcement['postdate'] = vbdate($vbulletin->options['dateformat'], $announcement['startdate'], false, true, false);
		if ($announcement['startdate'] > $vbulletin->userinfo['lastvisit'])
		{
			$announcement['statusicon'] = 'new';
		}
		else
		{
			$announcement['statusicon'] = 'old';
		}
		$announcement['views'] = vb_number_format($announcement['views']);
		$announcementidlink = iif(!$vbulletin->options['oneannounce'] , "&amp;a=$announcement[announcementid]");

		($hook = vBulletinHook::fetch_hook('forumdisplay_announcement')) ? eval($hook) : false;

		eval('$announcebits .= "' . fetch_template('forumdisplay_announcement') . '";');
	}

	// display threads
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
	{
		$limitothers = "AND postuserid = " . $vbulletin->userinfo['userid'] . " AND " . $vbulletin->userinfo['userid'] . " <> 0";
	}
	else
	{
		$limitothers = '';
	}

	// filter out deletion notices if can't be seen
	if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice'] OR can_moderate($foruminfo['forumid']))
	{
		$deljoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND type = 'thread')";
	}
	else
	{
		$deljoin = '';
	}

	// remove threads from users on the global ignore list if user is not a moderator
	if ($Coventry = fetch_coventry('string') AND !can_moderate($foruminfo['forumid']))
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
		$datecut = "AND thread.lastpost >= " . (TIMENOW - ($daysprune * 86400));
		$show['noposts'] = false;
	}
	else
	{
		$datecut = '';
		$show['noposts'] = true;
	}

	// complete form fields on page
	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
	$daysprunesel = array($daysprunesel => 'selected="selected"');

	$vbulletin->input->clean_array_gpc('r', array(
		'sortorder' => TYPE_STR,
	));

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
		$vbulletin->GPC['sortorder'] = 'desc';
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	switch ($sortfield)
	{
		case 'title':
			$sqlsortfield = 'thread.title';
			break;
		case 'lastpost':
			$sqlsortfield = 'thread.lastpost';
			break;
		case 'replycount':
		case 'views':
		case 'postusername':
			$sqlsortfield = $vbulletin->GPC['sortfield'];
			break;
		case 'voteavg':
			if ($foruminfo['allowratings'])
			{
				$sqlsortfield = 'voteavg';
				break;
			} // else, use last post
		default:
			$handled = false;
			($hook = vBulletinHook::fetch_hook('forumdisplay_sort')) ? eval($hook) : false;
			if (!$handled)
			{
				$sqlsortfield = 'thread.lastpost';
				$sortfield = 'lastpost';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	if (!can_moderate($forumid, 'canmoderateposts'))
	{
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice']))
		{
			$visiblethreads = " AND visible = 1 ";
		}
		else
		{
			$visiblethreads = " AND visible IN (1,2)";
		}
	}
	else
	{
		$visiblethreads = " AND visible IN (0,1,2)";
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('forumdisplay_query_threadscount')) ? eval($hook) : false;

	# Include visible IN (0,1,2) in order to hit upon the 4 column index
	$threadscount = $db->query_first("
		SELECT COUNT(*) AS threads,
		SUM(IF(thread.lastpost >= $lastread AND open <> 10, 1, 0)) AS newthread
		$hook_query_fields
		FROM " . TABLE_PREFIX . "thread AS thread
		$hook_query_joins
		WHERE forumid = $foruminfo[forumid]
			AND sticky = 0
			$visiblethreads
			$globalignore
			$datecut
			$limitothers
			$hook_query_where
	");
	$totalthreads = $threadscount['threads'];
	$newthreads = $threadscount['newthread'];

	// set defaults
	sanitize_pageresults($totalthreads, $pagenumber, $perpage, 200, $vbulletin->options['maxthreads']);

	// get number of sticky threads for the first page
	// on the first page there will be the sticky threads PLUS the $perpage other normal threads
	// not quite a bug, but a deliberate feature!
	if ($pagenumber == 1 OR $vbulletin->options['showstickies'])
	{
		$stickies = $db->query_read("
			SELECT threadid, lastpost, open
			FROM " . TABLE_PREFIX . "thread AS thread
			WHERE forumid = $foruminfo[forumid]
				AND sticky = 1
				$visiblethreads
				$limitothers
				$globalignore
		");
		while ($thissticky = $db->fetch_array($stickies))
		{
			$stickycount++;
			if ($thissticky['lastpost'] >= $lastread AND $thissticky['open'] <> 10)
			{
				$newthreads++;
			}
			$stickyids .= ",$thissticky[threadid]";
		}
		$db->free_result($stickies);
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
		$vbulletin->options['showvotes'] = intval($vbulletin->options['showvotes']);
		$votequery = "
			IF(votenum >= " . $vbulletin->options['showvotes'] . ", votenum, 0) AS votenum,
			IF(votenum >= " . $vbulletin->options['showvotes'] . " AND votenum > 0, votetotal / votenum, 0) AS voteavg,
		";
	}
	else
	{
		$votequery = '';
	}

	if ($vbulletin->options['threadpreview'] > 0)
	{
		$previewfield = "post.pagetext AS preview,";
		$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
	}
	else
	{
		$previewfield = '';
		$previewjoin = '';
	}

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
	{
		$lastpost_info = 'IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost, ' .
			'IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter';

		$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
			"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
	}
	else
	{
		$lastpost_info = 'thread.lastpost, lastposter';
		$tachyjoin = '';
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('forumdisplay_query_threadid')) ? eval($hook) : false;

	$getthreadids = $db->query_read("
		SELECT " . iif($sortfield == 'voteavg', $votequery) . " threadid
			$hook_query_fields
		FROM " . TABLE_PREFIX . "thread AS thread
		$hook_query_joins
		WHERE forumid = $foruminfo[forumid]
			AND sticky = 0
			$visiblethreads
			$globalignore
			$datecut
			$limitothers
			$hook_query_where
		ORDER BY sticky DESC, $sqlsortfield $sqlsortorder
		LIMIT $limitlower, $perpage
	");

	$ids = '';
	while ($thread = $db->fetch_array($getthreadids))
	{
		$ids .= ',' . $thread['threadid'];
	}

	$ids .= $stickyids;

	$db->free_result($getthreadids);
	unset ($thread, $getthreadids);

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('forumdisplay_query')) ? eval($hook) : false;

	$threads = $db->query_read("
		SELECT $votequery $previewfield
			thread.threadid, thread.title AS threadtitle, thread.forumid, pollid, open, replycount, postusername, postuserid, thread.iconid AS threadiconid,
			$lastpost_info, thread.dateline, IF(views<=replycount, replycount+1, views) AS views, notes, thread.visible, sticky, votetotal, thread.attach,
			hiddencount
			" . iif($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid'], ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed") . "
			" . iif($deljoin, ", deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason")
			. iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], ', threadread.readtime AS threadread') . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "thread AS thread
			$deljoin
			" . iif($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON(subscribethread.threadid = thread.threadid AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . " AND canview = 1)") . "
			" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")") . "
			$previewjoin
			$tachyjoin
			$hook_query_joins
		WHERE thread.threadid IN (0$ids) $hook_query_where
		ORDER BY sticky DESC, $sqlsortfield $sqlsortorder
	");
	unset($limitothers, $delthreadlimit, $deljoin,$datecut, $votequery, $sqlsortfield, $sqlsortorder, $threadids);

	// Get Dot Threads
	$dotthreads = fetch_dot_threads_array($ids);
	if ($vbulletin->options['showdots'] AND $vbulletin->userinfo['userid'])
	{
		$show['dotthreads'] = true;
	}
	else
	{
		$show['dotthreads'] = false;
	}

	unset($ids);

	// prepare sort things for column header row:
	$sorturl = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumid&amp;daysprune=$daysprune";
	$oppositesort = iif($vbulletin->GPC['sortorder'] == 'asc', 'desc', 'asc');

	if ($totalthreads > 0 OR $stickyids)
	{
		if ($totalthreads > 0)
		{
			$limitlower++;
		}
		// check to see if there are any threads to display. If there are, do so, otherwise, show message

		if ($vbulletin->options['threadpreview'] > 0)
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

		$show['threads'] = true;
		$threadbits = '';
		$threadbits_sticky = '';

		$counter = 0;
		$toread = 0;

		while ($thread = $db->fetch_array($threads))
		{ // AND $counter++ < $perpage)

			// build thread data
			$thread = process_thread_array($thread, $lastread, $foruminfo['allowicons']);
			$realthreadid = $thread['realthreadid'];

			if ($thread['sticky'])
			{
				$threadbit =& $threadbits_sticky;
			}
			else
			{
				$threadbit =& $threadbits;
			}

			($hook = vBulletinHook::fetch_hook('threadbit_display')) ? eval($hook) : false;

			// Soft Deleted Thread
			if ($thread['visible'] == 2)
			{
				$show['threadtitle'] = (can_moderate($forumid) OR ($vbulletin->userinfo['userid'] != 0 AND $vbulletin->userinfo['userid'] == $thread['postuserid'])) ? true : false;
				$show['deletereason'] = (!empty($thread['del_reason'])) ?  true : false;
				$show['viewthread'] = (can_moderate($forumid)) ? true : false;
				$show['managethread'] = (can_moderate($forumid, 'candeleteposts') OR can_moderate($forumid, 'canremoveposts')) ? true : false;
				$show['moderated'] = ($thread['hiddencount'] > 0 AND can_moderate($forumid, 'canmoderateposts')) ? true : false;
				eval('$threadbit .= "' . fetch_template('threadbit_deleted') . '";');
			}
			else
			{
				$show['moderated'] = ((!$thread['visible'] OR $thread['hiddencount'] > 0) AND can_moderate($forumid, 'canmoderateposts')) ? true : false;
				if (!$thread['visible'])
				{
					$thread['hiddencount']++;
				}
				eval('$threadbit .= "' . fetch_template('threadbit') . '";');
			}
		}
		$db->free_result($threads);
		unset($thread, $counter);

		$pagenav = construct_page_nav($pagenumber, $perpage, $totalthreads, 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumid", ""
			. (!empty($vbulletin->GPC['perpage']) ? "&amp;pp=$perpage" : "")
			. (!empty($vbulletin->GPC['sortfield']) ? "&amp;sort=$sortfield" : "")
			. (!empty($vbulletin->GPC['sortorder']) ? "&amp;order=" . $vbulletin->GPC['sortorder'] : "")
			. (!empty($vbulletin->GPC['daysprune']) ? "&amp;daysprune=$daysprune" : "")
		);

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
	mark_forum_read($foruminfo, $vbulletin->userinfo['userid'], TIMENOW);
}

construct_forum_rules($foruminfo, $forumperms);

$show['forumsearch'] = iif (!$show['search_engine'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['cansearch'] AND $vbulletin->options['enablesearches'], true, false);
$show['forumslist'] = iif ($forumshown, true, false);
$show['stickies'] = iif ($threadbits_sticky != '', true, false);

($hook = vBulletinHook::fetch_hook('forumdisplay_complete')) ? eval($hook) : false;

eval('print_output("' . fetch_template('FORUMDISPLAY') . '");');


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14225 $
|| ####################################################################
\*======================================================================*/
?>