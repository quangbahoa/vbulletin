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
define('THIS_SCRIPT', 'usercp');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user', 'infractionlevel');

// get special data templates from the datastore
$specialtemplates = array(
	'iconcache',
	'noavatarperms',
	'smiliecache',
	'bbcodecache',
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
	'forumhome_markread_script',
	'forumdisplay_loggedinuser',
	// private messages templates
	'pm_messagelistbit',
	'pm_messagelistbit_ignore',
	'pm_messagelistbit_user',
	// reputation templates
	'usercp_reputationbits',
	// infraction templates
	'userinfraction_infobit',
	'usercp_newvisitormessagebit',
	'usercp_pendingfriendbit',
	'usercp_groupinvitebit',
	'usercp_groupattentionbit',
	'socialgroups_css',
	'socialgroups_discussion',
	'socialgroups_grouplist_bit',
	'socialgroups_groupmodlist_bit'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_forumlist.php');
require_once(DIR . '/includes/functions_user.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!$vbulletin->userinfo['userid'] OR !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
{
	print_no_permission();
}

// main page:

($hook = vBulletinHook::fetch_hook('usercp_start')) ? eval($hook) : false;

// ############################### start reputation ###############################

$show['reputation'] = false;

if ($vbulletin->options['reputationenable'])
{
	$vbulletin->options['showuserrates'] = intval($vbulletin->options['showuserrates']);
	$vbulletin->options['showuserraters'] = $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseeownrep'];
	$reputations = $db->query_read_slave("
		SELECT
			reputation.whoadded, reputation.postid, reputation.reputation, reputation.reason, reputation.dateline,
			user.userid, user.username, post.threadid, thread.title
		FROM " . TABLE_PREFIX . "reputation AS reputation
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (reputation.postid = post.postid AND post.visible = 1)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid AND thread.visible = 1)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = reputation.whoadded)
		WHERE reputation.userid = " . $vbulletin->userinfo['userid'] . "
			" . iif($vbulletin->options['showuserraters'] AND trim($vbulletin->userinfo['ignorelist']), " AND reputation.whoadded NOT IN (0," . str_replace(' ', ',', trim($vbulletin->userinfo['ignorelist'])). ")") . "
		ORDER BY reputation.dateline DESC
		LIMIT 0, " . $vbulletin->options['showuserrates']
	);

	$reputationcommentbits = '';
	if ($vbulletin->options['showuserraters'])
	{
		$reputationcolspan = 5;
		$reputationbgclass = 'alt2';
	}
	else
	{
		$reputationcolspan = 4;
		$reputationbgclass = 'alt1';
	}

	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	while ($reputation = $db->fetch_array($reputations))
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
		$reputation['timeline'] = vbdate($vbulletin->options['timeformat'], $reputation['dateline']);
		$reputation['dateline'] = vbdate($vbulletin->options['dateformat'], $reputation['dateline']);
		$reputation['reason'] = $bbcode_parser->parse($reputation['reason']);
		if (vbstrlen($reputation['title']) > 25)
		{
			$reputation['title'] = fetch_trimmed_title($reputation['title'], 24);
		}

		($hook = vBulletinHook::fetch_hook('usercp_reputationbit')) ? eval($hook) : false;

		eval('$reputationcommentbits .= "' . fetch_template('usercp_reputationbits') . '";');
		$show['reputation'] = true;
	}
	unset($bbcode_parser);
}

// ############################### start pending friends ###############################

$show['pendingfriendrequests'] = false;
if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_friends'])
{
	list($pendingfriendrequests) = $vbulletin->db->query_first("
		SELECT COUNT(*)
		FROM " . TABLE_PREFIX . "userlist AS userlist
		LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist_ignore ON (userlist_ignore.userid = " . $vbulletin->userinfo['userid'] . " AND userlist_ignore.relationid = userlist.userid AND userlist_ignore.type = 'ignore')
		WHERE userlist.relationid = " . $vbulletin->userinfo['userid'] . "
			AND userlist.friend = 'pending' AND userlist_ignore.type IS NULL
	", DBARRAY_NUM);

	$show['pendingfriendrequests'] = ($pendingfriendrequests ? true : false);

	if ($show['pendingfriendrequests'])
	{
		$pendingfriends = $vbulletin->db->query_read("
			SELECT user.*
			FROM " . TABLE_PREFIX . "userlist AS userlist
			LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist_ignore ON (userlist_ignore.userid = " . $vbulletin->userinfo['userid'] . " AND userlist_ignore.relationid = userlist.userid AND userlist_ignore.type = 'ignore')
			INNER JOIN " . TABLE_PREFIX . "user AS user ON
				(user.userid = userlist.userid)
			WHERE userlist.relationid = " . $vbulletin->userinfo['userid'] . "
				AND userlist.friend = 'pending' AND userlist_ignore.type IS NULL
			ORDER BY user.lastactivity DESC
			LIMIT " . min(5, $pendingfriendrequests)
		);

		$pendingfriendbits = array();
		$pendingfriendbits_joined = '';
		$i = 0;

		while ($pendingfriend = $vbulletin->db->fetch_array($pendingfriends))
		{
			$loggedin =& $pendingfriend;
			fetch_musername($loggedin);

			$show['comma_leader'] = ($pendingfriendbits_joined != '');
			eval('$pendingfriendbits_joined .= "' . fetch_template('forumdisplay_loggedinuser') . '";');
		}

		if ($pendingfriendrequests > 5)
		{
			$pendingfriendstext = construct_phrase($vbphrase['you_have_pending_friend_requests_from_x_and_y_more'], $pendingfriendbits_joined, vb_number_format($pendingfriendrequests - 5));
		}
		else
		{
			$pendingfriendstext = construct_phrase($vbphrase['you_have_pending_friend_requests_from_x'], $pendingfriendbits_joined);
		}

		$pendingfriendrequests = vb_number_format($pendingfriendrequests);
	}
}

// ############################### start visitor messages ###############################

$show['newvisitormessages'] = false;
if (
	$vbulletin->userinfo['vm_enable']
		AND
	$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_visitor_messaging']
		AND
	$vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
)
{
	list($newvisitormessages) = $vbulletin->db->query_first("
		SELECT COUNT(*)
		FROM " . TABLE_PREFIX . "visitormessage
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
			AND state = 'visible'
			AND postuserid <>  " . $vbulletin->userinfo['userid'] . "
			AND messageread = 0
		GROUP BY userid
	", DBARRAY_NUM);

	$show['newvisitormessages'] = ($newvisitormessages ? true : false);

	if ($show['newvisitormessages'])
	{
		$visitormessages = $db->query_read("
			SELECT user.username, user.userid, visitormessage.vmid, visitormessage.dateline, visitormessage.pagetext
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "visitormessage AS visitormessage ON (user.userid = visitormessage.postuserid)
			WHERE visitormessage.userid = "  . $vbulletin->userinfo['userid'] . "
				AND visitormessage.state = 'visible'
				AND visitormessage.messageread = 0
			ORDER BY visitormessage.dateline DESC
			LIMIT " . min($newvisitormessages, 5)
		);

		$newvisitormessagebits = '';
		while ($visitormessage = $db->fetch_array($visitormessages))
		{
			$visitormessage['formatteddate'] = vbdate($vbulletin->options['dateformat'], $visitormessage['dateline'], true);
			$visitormessage['formattedtime'] = vbdate($vbulletin->options['timeformat'], $visitormessage['dateline'], true);
			$visitormessage['summary'] = htmlspecialchars_uni(fetch_word_wrapped_string(fetch_censored_text(fetch_trimmed_title(strip_bbcode($visitormessage['pagetext'], true, true), 50))));

			$username = $visitormessage["username"];
			$userid = $visitormessage["userid"];
			eval('$userbit = "' . fetch_template('pm_messagelistbit_user') . '";');
			eval('$newvisitormessagebits .= "' . fetch_template('usercp_newvisitormessagebit') . '";');
		}

		$newpublicmessages = vb_number_format($newpublicmessages);
	}
}

// ############################### start social groups ###############################

$show['groupattention'] = false;

if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
{

	list($groupsneedattention) = $vbulletin->db->query_first("
		SELECT COUNT(*)
		FROM " . TABLE_PREFIX . "socialgroup
		WHERE creatoruserid = " . $vbulletin->userinfo['userid'] . "
			AND moderatedmembers > 0
	", DBARRAY_NUM);
	$show['groupattention'] = ($groupsneedattention ? true : false);

	if ($show['groupattention'])
	{
		$groups = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "socialgroup
			WHERE creatoruserid = " . $vbulletin->userinfo['userid'] . "
				AND moderatedmembers > 0
			ORDER by dateline ASC
		");

		$pendinggroups = array();

		while ($group = $db->fetch_array($groups))
		{
			$group['moderatedmembers'] = vb_number_format($group['moderatedmembers']);
			eval('$pendinggroups[] = "' . fetch_template('usercp_groupattentionbit') . '";');
		}

		$groups_awaiting = implode(', ', $pendinggroups);

		if ($groupsneedattention > 5)
		{
			$groupsawaitingtext = construct_phrase($vbphrase['x_and_y_more'], $groups_awaiting, vb_number_format($groupsneedtending - 5));
		}
		else
		{
			$groupsawaitingtext = $groups_awaiting;
		}

		$groupsneedattention = vb_number_format($groupsneedattention);
	}
}

$show['invitedgroups'] = false;

if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
{
	if ($vbulletin->userinfo['socgroupinvitecount'] > 0)
	{
		$show['invitedgroups'] = true;

		$groups = $db->query_read("
			SELECT socialgroup.* FROM " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember
			INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup
				ON (socialgroupmember.groupid = socialgroup.groupid)
			WHERE socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . "
				AND socialgroupmember.type = 'invited'
			ORDER BY socialgroupmember.dateline ASC
			LIMIT " . min($vbulletin->userinfo['socgroupinvitecount'], 5) . "
		");

		$pendinginvites = array();
		while($group = $db->fetch_array($groups))
		{
			eval('$pendinginvites[] = "' . fetch_template('usercp_groupinvitebit') . '";');
		}

		$pendinginvites_joined = implode(', ', $pendinginvites);
		if ($vbulletin->userinfo['socgroupinvitecount'] > 5)
		{
			$invitetext = construct_phrase($vbphrase['x_and_y_more'], $pendinginvites_joined, vb_number_format($vbulletin->userinfo['socgroupinvitecount'] - 5));
		}
		else
		{
			$invitetext = $pendinginvites_joined;
		}

		$vbulletin->userinfo['socgroupinvitecount'] = vb_number_format($vbulletin->userinfo['socgroupinvitecount']);
	}
}

// ############################### start picture comments ###############################
$show['picture_comment_block'] = false;

if ($vbulletin->options['pc_enabled']
	AND $vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']
	AND $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
	AND $permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']
	AND $permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']
)
{
	$show['picture_comment_block'] = ($show['picture_comment_block'] OR $vbulletin->userinfo['pcunreadcount']);
	$show['picture_comment_unread'] = ($vbulletin->userinfo['pcunreadcount'] != 0);

	$vbulletin->userinfo['pcunreadcount'] = vb_number_format($vbulletin->userinfo['pcunreadcount']);

	if ($permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment'])
	{
		$show['picture_comment_block'] = ($show['picture_comment_block'] OR $vbulletin->userinfo['pcmoderatedcount']);
		$show['picture_comment_moderated'] = ($vbulletin->userinfo['pcmoderatedcount'] != 0);

		$vbulletin->userinfo['pcmoderatedcount'] = vb_number_format($vbulletin->userinfo['pcmoderatedcount']);
	}
}

// ############################### start private messages ###############################

$show['privatemessages'] = false;
if ($vbulletin->options['enablepms'] AND ($permissions['pmquota'] > 0 OR $vbulletin->userinfo['pmtotal']))
{
	$pms = $db->query_read_slave("
		SELECT pm.*, pmtext.*, userlist_ignore.userid AS ignored
			" . iif($vbulletin->options['privallowicons'], ',icon.iconpath, icon.title AS icontitle') . "
		FROM " . TABLE_PREFIX . "pm AS pm
		INNER JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
		LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist_ignore ON
			(userlist_ignore.userid = " . $vbulletin->userinfo['userid'] . " AND userlist_ignore.relationid = pmtext.fromuserid AND userlist_ignore.type = 'ignore')
		" . iif($vbulletin->options['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
		WHERE pm.userid = " . $vbulletin->userinfo['userid'] . "
			AND pm.messageread = 0
		ORDER BY pmtext.dateline DESC
		LIMIT 5
	");
	if ($db->num_rows($pms))
	{
		$messagelistbits = '';
		$show['pmcheckbox'] = false;
		$shown_unread_pms = 0;

		require_once(DIR . '/includes/functions_bigthree.php');
		while ($pm = $db->fetch_array($pms))
		{
			($hook = vBulletinHook::fetch_hook('usercp_pmbit')) ? eval($hook) : false;

			if (in_coventry($pm['fromuserid']))
			{
				if (!can_moderate())
				{
					continue;
				}
				else
				{
					eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit_ignore') . '";');

					$shown_unread_pms++;
					$show['privatemessages'] = true;
				}
			}
			else if ($pm['ignored'])
			{
				eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit_ignore') . '";');

				$shown_unread_pms++;
				$show['privatemessages'] = true;
			}
			else
			{
				$pm['senddate'] = vbdate($vbulletin->options['dateformat'], $pm['dateline'], 1);
				$pm['sendtime'] = vbdate($vbulletin->options['timeformat'], $pm['dateline']);
				$pm['statusicon'] = 'new';
				$userid =& $pm['fromuserid'];
				$username =& $pm['fromusername'];

				$show['pmicon'] = iif($pm['iconpath'], true, false);
				$show['unread'] = iif(!$pm['messageread'], true, false);

				eval('$userbit = "' . fetch_template('pm_messagelistbit_user') . '";');
				eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit') . '";');

				$shown_unread_pms++;
				$show['privatemessages'] = true;
			}
		}

		$numpms = max($shown_unread_pms, $vbulletin->userinfo['pmunread']);
		$show['more_pms_link'] = ($numpms > 5);
	}
}


// ############################### start subscribed forums ###############################

// get only subscribed forums
cache_ordered_forums(1, 0, $vbulletin->userinfo['userid']);
$show['forums'] = false;
foreach ($vbulletin->forumcache AS $forumid => $forum)
{
	if ($forum['subscribeforumid'] != '')
	{
		$show['forums'] = true;
	}
}
if ($show['forums'])
{
	if ($vbulletin->options['showmoderatorcolumn'])
	{
		cache_moderators();
	}
	else
	{
		cache_moderators($vbulletin->userinfo['userid']);
	}
	fetch_last_post_array();

	$show['collapsable_forums'] = true;
	$forumbits = construct_forum_bit(-1, 0, 1);
	eval('$forumbits .= "' . fetch_template('forumhome_markread_script') . '";');
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

$show['threads'] = false;
$numthreads = 0;

// query thread ids
$readtimeout = TIMENOW - ($vbulletin->options['markinglimit'] * 86400);

if (in_coventry($vbulletin->userinfo['userid'], true))
{
	$lastpost_info = ", IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastposts";
	$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
		"(tachythreadpost.threadid = subscribethread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
}
else
{
	$lastpost_info = ', thread.lastpost AS lastposts';
	$tachyjoin = '';
}

$getthreads = $db->query_read_slave("
	SELECT thread.threadid, thread.forumid, thread.postuserid,
		IF(threadread.readtime IS NULL, $readtimeout, IF(threadread.readtime < $readtimeout, $readtimeout, threadread.readtime)) AS threadread,
		IF(forumread.readtime IS NULL, $readtimeout, IF(forumread.readtime < $readtimeout, $readtimeout, forumread.readtime)) AS forumread,
		subscribethread.subscribethreadid
		$lastpost_info
	FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
	INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (subscribethread.threadid = thread.threadid)
	LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")
	LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = thread.forumid AND forumread.userid = " . $vbulletin->userinfo['userid'] . ")
	$tachyjoin
	WHERE subscribethread.userid = " . $vbulletin->userinfo['userid'] . "
		AND thread.visible = 1
		AND subscribethread.canview = 1
	HAVING lastposts > IF(threadread > forumread, threadread, forumread)
");

if ($totalthreads = $db->num_rows($getthreads))
{
	$forumids = array();
	$threadids = array();
	$killthreads = array();
	while ($getthread = $db->fetch_array($getthreads))
	{
		$forumperms = fetch_permissions($getthread['forumid']);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR ($getthread['postuserid'] != $vbulletin->userinfo['userid'] AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
		{
			$killthreads[] = $getthread['subscribethreadid'];
			continue;
		}
		$forumids["$getthread[forumid]"] = true;
		$threadids[] = $getthread['threadid'];
	}
	$threadids = implode(',', $threadids);
}
unset($getthread);
$db->free_result($getthreads);

if (!empty($killthreads))
{
	// Update thread subscriptions
	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "subscribethread
		SET canview = 0
		WHERE subscribethreadid IN (" . implode(', ', $killthreads) . ")
	");
}

// if there are some results to show, query the data
if (!empty($threadids))
{
	// get last read info for each thread
	$lastread = array();
	foreach (array_keys($forumids) AS $forumid)
	{
		$lastread["$forumid"] = max($vbulletin->forumcache["$forumid"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
	}

	// get thread preview?
	if ($vbulletin->options['threadpreview'] > 0)
	{
		$previewfield = 'post.pagetext AS preview,';
		$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
	}
	else
	{
		$previewfield = '';
		$previewjoin = '';
	}

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
	{
		$lastpost_info = "IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost, " .
			"IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter, " .
			"IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid";

		$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
			"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
	}
	else
	{
		$lastpost_info = 'thread.lastpost, thread.lastposter, thread.lastpostid';
		$tachyjoin = '';
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('usercp_threads_query')) ? eval($hook) : false;

	$getthreads = $db->query_read_slave("
		SELECT $previewfield
			thread.threadid, thread.title AS threadtitle, thread.forumid, thread.pollid,
			thread.open, thread.replycount, thread.postusername, thread.postuserid,
			thread.prefixid, thread.taglist, thread.dateline, thread.views, thread.iconid AS threadiconid,
			thread.notes, thread.visible, threadread.readtime AS threadread,
			$lastpost_info
			$hook_query_fields
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")
		$previewjoin
		$tachyjoin
		$hook_query_joins
		WHERE thread.threadid IN($threadids)
			$hook_query_where
		ORDER BY lastpost DESC
	");

	require_once(DIR . '/includes/functions_forumdisplay.php');

	// Get Dot Threads
	$dotthreads = fetch_dot_threads_array($threadids);
	$subscribedthreadscolspan = 5;

	// check to see if there are any threads to display. If there are, do so, otherwise, show message
	if ($totalthreads = $db->num_rows($getthreads))
	{
		$threads = array();
		while ($getthread = $db->fetch_array($getthreads))
		{
			// unset the thread preview if it can't be seen
			$forumperms = fetch_permissions($getthread['forumid']);
			if ($vbulletin->options['threadpreview'] > 0 AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
			{
				$getthread['preview'] = '';
			}
			if ($vbulletin->forumcache[$getthread['forumid']]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'])
			{
				$show['threadicons'] = true;
				$subscribedthreadscolspan = 6;
			}
			$threads["$getthread[threadid]"] = $getthread;
		}
	}
	unset($getthread);
	$db->free_result($getthreads);

	$show['threadratings'] = false;

	if ($totalthreads)
	{
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

		$threadbits = '';
		foreach ($threads AS $threadid => $thread)
		{
			$thread = process_thread_array($thread, $lastread["$thread[forumid]"]);
			$show['unsubscribe'] = true;

			($hook = vBulletinHook::fetch_hook('threadbit_display')) ? eval($hook) : false;

			eval('$threadbits .= "' . fetch_template('threadbit') . '";');
			$numthreads ++;
		}

		$show['threads'] = true;
	}
}

// ############################## start subscribed to groups #################################

$show['socialgroups'] = false;

if (($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
	AND ($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'])
	AND $vbulletin->options['socnet_groups_msg_enabled']
)
{
	require_once(DIR . '/includes/functions_socialgroup.php');
	require_once(DIR . '/includes/class_socialgroup_search.php');

	$socialgroupsearch = new vB_SGSearch($vbulletin);
	$socialgroupsearch->add('subscribed', $vbulletin->userinfo['userid']);
	$socialgroupsearch->set_sort('lastpost', 'ASC');
	$socialgroupsearch->check_read(true);

 	($hook = vBulletinHook::fetch_hook('group_list_filter')) ? eval($hook) : false;

	if ($numsocialgroups = $socialgroupsearch->execute(true))
	{
		$groups = $socialgroupsearch->fetch_results();

		$show['pictureinfo'] = ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums'] AND $vbulletin->options['socnet_groups_albums_enabled']) ? true : false;

		$lastpostalt = ($show['pictureinfo'] ? 'alt2' : 'alt1');

		if (is_array($groups))
		{
			$grouplist = '';
			foreach ($groups AS $group)
			{
				$group = prepare_socialgroup($group);

				$show['pending_link'] = (fetch_socialgroup_modperm('caninvitemoderatemembers', $group) AND $group['moderatedmembers'] > 0);
				$show['lastpostinfo'] = ($group['lastpost']);

				($hook = vBulletinHook::fetch_hook('group_list_groupbit')) ? eval($hook) : false;

				eval('$grouplist .= "' . fetch_template("socialgroups_groupmodlist_bit") . '";');
			}
		}

		$show['socialgroups'] = true;
	}

	unset($socialgroupsearch);
}

// ############################ start new subscribed to discussions ##############################

$show['discussions'] = false;

if (($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
	AND ($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'])
	AND $vbulletin->options['socnet_groups_msg_enabled']
)
{
	require_once(DIR . '/includes/class_groupmessage.php');

	// Create message collection
	$collection_factory = new vB_Group_Collection_Factory($vbulletin);
	$collection = $collection_factory->create('discussion', false, $vbulletin->GPC['pagenumber'], false, false, true);

	$collection->set_ignore_marking(false);
	$collection->filter_show_unsubscribed(false);

	// Check if the user is subscribed to any discussions
	if ($collection->fetch_count())
	{
		if(!$vbulletin->input->clean_gpc('r', 'viewalldiscussions', TYPE_BOOL))
		{
			// only show unread
			$collection->filter_show_read(false);
		}

		$numdiscussions = $collection->fetch_count();

		// Show group name in messages
		$show['group'] = true;

		// Create bit factory
		$bit_factory = new vB_Group_Bit_Factory($vbulletin, $itemtype);

		// Build message bits for all items
		$messagebits = '';
		while ($item = $collection->fetch_item())
		{
			$group = fetch_socialgroupinfo($item['groupid']);

			// add group name to message
			$group['name'] = fetch_word_wrapped_string(fetch_censored_text($group['name']));

			// add bit
			$bit = $bit_factory->create($item, $group);
			$bit->show_moderation_tools(false);
			$messagebits .= $bit->construct();
		}

		$show['discussions'] = true;

		unset($bit, $bit_factory, $collection_factory, $collection);
	}
}

if (!empty($show['socialgroup']) OR $show['discussions'])
{
	// Include social groups css
	eval('$headinclude .= "' . fetch_template('socialgroups_css') . '";');
}

// ##################################### start infractions #######################################

require_once(DIR . '/includes/class_bbcode.php');
$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

$infractions = $db->query_read_slave("
	SELECT points, infraction.*, thread.title, thread.forumid, thread.postuserid, user.username,
	thread.visible AS thread_visible, post.visible, thread.postuserid, IF(ISNULL(post.postid) AND infraction.postid != 0, 1, 0) AS postdeleted
	FROM " . TABLE_PREFIX . "infraction AS infraction
	LEFT JOIN " . TABLE_PREFIX . "post AS post ON (infraction.postid = post.postid)
	LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON (infraction.whoadded = user.userid)
	WHERE infraction.userid = " . $vbulletin->userinfo['userid'] . "
	ORDER BY infraction.dateline DESC
	LIMIT 5
");
while ($infraction = $db->fetch_array($infractions))
{
	$show['threadtitle'] = true;
	$show['postdeleted'] = false;
	if ($infraction['postid'] != 0)
	{
		if ($infraction['postdeleted'])
		{
			$show['postdeleted'] = true;
		}
		else if ((!$infraction['visible'] OR !$infraction['thread_visible']) AND !can_moderate($infraction['forumid'], 'canmoderateposts'))
		{
			$show['threadtitle'] = false;
		}
		else if (($infraction['visible'] == 2 OR $infraction['thread_visible'] == 2) AND !can_moderate($infraction['forumid'], 'candeleteposts'))
		{
			$show['threadtitle'] = false;
		}
		else
		{
			$forumperms = fetch_permissions($infraction['forumid']);
			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				$show['threadtitle'] = false;
			}
			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($infraction['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
			{
				$show['threadtitle'] = false;
			}
		}
	}
	$show['expired'] = $show['reversed'] = $show['neverexpires'] = false;
	$card = ($infraction['points'] > 0) ? 'redcard' : 'yellowcard';
	$infraction['timeline'] = vbdate($vbulletin->options['timeformat'], $infraction['dateline']);
	$infraction['dateline'] = vbdate($vbulletin->options['dateformat'], $infraction['dateline']);
	switch($infraction['action'])
	{
		case 0:
			if ($infraction['expires'] != 0)
			{
				$infraction['expires_timeline'] = vbdate($vbulletin->options['timeformat'], $infraction['expires']);
				$infraction['expires_dateline'] = vbdate($vbulletin->options['dateformat'], $infraction['expires']);
				$show['neverexpires'] = false;
			}
			else
			{
				$show['neverexpires'] = true;
			}
			break;
		case 1:
			$show['expired'] = true;
			break;
		case 2:
			$show['reversed'] = true;
			break;
	}
	if (vbstrlen($infraction['title']) > 25)
	{
		$infraction['title'] = fetch_trimmed_title($infraction['title'], 24);
	}
	$infraction['reason'] = !empty($vbphrase['infractionlevel' . $infraction['infractionlevelid'] . '_title']) ? $vbphrase['infractionlevel' . $infraction['infractionlevelid'] . '_title'] : ($infraction['customreason'] ? $infraction['customreason'] : $vbphrase['n_a']);
	($hook = vBulletinHook::fetch_hook('usercp_infractioninfobit')) ? eval($hook) : false;

	eval('$infractionbits .= "' . fetch_template('userinfraction_infobit') . '";');
	$show['infractions'] = true;
}
unset($bbcode_parser);


require_once(DIR . '/includes/functions_misc.php');

// check if user can be invisible and is invisible
if (!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['caninvisible']) AND $vbulletin->userinfo['invisible'])
{
	// init user data manager
	$userdata = datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
	$userdata->set_existing($vbulletin->userinfo);
	$userdata->set_bitfield('options', 'invisible', 0);
	$userdata->save();
}

// draw cp nav bar
construct_usercp_nav('usercp');

$frmjmpsel['usercp'] = 'class="fjsel" selected="selected"';
construct_forum_jump();

($hook = vBulletinHook::fetch_hook('usercp_complete')) ? eval($hook) : false;

eval('$HTML = "' . fetch_template('USERCP') . '";');

// build navbar
$navbits = construct_navbits(array('' => $vbphrase['user_control_panel']));
eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92958 $
|| # $Date: 2017-02-16 19:57:42 -0800 (Thu, 16 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
