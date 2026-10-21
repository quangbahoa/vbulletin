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
define('THIS_SCRIPT', 'ajax');
define('LOCATION_BYPASS', 1);
define('NOPMPOPUP', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting');

// get special data templates from the datastore
$specialtemplates = array('bbcodecache');

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// browsers tend to interpret iso-8859-1 as windows-1252, but Microsoft.XMLHttp doesn't
// so we need to tell it too :-/
if (strtolower($vbulletin->userinfo['lang_charset']) == 'iso-8859-1')
{
	$ajax_charset = 'windows-1252';
}
else
{
	$ajax_charset = $vbulletin->userinfo['lang_charset'];
}

($hook = vBulletinHook::fetch_hook('ajax_start')) ? eval($hook) : false;

// #############################################################################
// user name search

if ($_POST['do'] == 'usersearch')
{
	$vbulletin->input->clean_array_gpc('p', array('fragment' => TYPE_STR));

	$vbulletin->GPC['fragment'] = convert_urlencoded_unicode($vbulletin->GPC['fragment']);

	if ($vbulletin->GPC['fragment'] != '' AND strlen($vbulletin->GPC['fragment']) >= 3)
	{
		$fragment = htmlspecialchars_uni($vbulletin->GPC['fragment']);
	}
	else
	{
		$fragment = '';
	}

	header('Content-Type: text/xml' . iif($ajax_charset != '', '; charset=' . $ajax_charset));
	echo '<?xml version="1.0" encoding="' . $ajax_charset . '"?>' . "\r\n<users>\r\n";

	if ($fragment != '')
	{
		$users = $db->query_read("
			SELECT userid, username FROM " . TABLE_PREFIX . "user
			WHERE username LIKE('" . $db->escape_string_like($fragment) . "%')
			ORDER BY username
			LIMIT 15
		");
		while ($user = $db->fetch_array($users))
		{
			echo "\t<user userid=\"$user[userid]\">$user[username]</user>\r\n";
		}
	}

	echo "</users>";
}

// #############################################################################
// update thread title

if ($_POST['do'] == 'updatethreadtitle')
{
	$vbulletin->input->clean_array_gpc('p', array('threadid' => TYPE_UINT, 'title' => TYPE_STR));

	@header('Content-Type: text/html' . iif($ajax_charset != '', '; charset=' . $ajax_charset));

	// allow edit if...
	if (
		can_moderate($threadinfo['forumid'], 'caneditthreads') // ...user is moderator
		OR
		(
			$threadinfo['postuserid'] == $vbulletin->userinfo['userid'] // ...user is thread first poster
			AND
			($forumperms = fetch_permissions($threadinfo['forumid'])) AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['caneditpost']) // ...user has edit own posts permissions
			AND
			($threadinfo['dateline'] + $vbulletin->options['editthreadtitlelimit'] * 60) > TIMENOW // ...thread was posted within editthreadtimelimit
		)
	)
	{
		$threadtitle = convert_urlencoded_unicode($vbulletin->GPC['title']);
		$threaddata =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threaddata->set_existing($threadinfo);
		$threaddata->set('title', $threadtitle);
		if ($vbulletin->options['similarthreadsearch'])
		{
			require_once(DIR . '/includes/functions_search.php');
			$threaddata->set('similar', fetch_similar_threads(fetch_censored_text($threadtitle), $threadinfo['threadid']));
		}

		$getfirstpost = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $threadinfo[threadid]
			ORDER BY dateline
			LIMIT 1
		");

		if ($threaddata->save())
		{
			// Reindex first post to set up title properly.
			require_once(DIR . '/includes/functions_databuild.php');
			delete_post_index($getfirstpost['postid'], $getfirstpost['title'], $getfirstpost['pagetext']);
			$getfirstpost['threadtitle'] = $threaddata->fetch_field('title');
			$getfirstpost['title'] =& $getfirstpost['threadtitle'];
			build_post_index($getfirstpost['postid'] , $foruminfo, 1, $getfirstpost);

			require_once(DIR . '/includes/functions_forumlist.php');
			cache_ordered_forums(1);

			if ($vbulletin->forumcache["$threadinfo[forumid]"]['lastthreadid'] == $threadinfo['threadid'])
			{
				require_once(DIR . '/includes/functions_databuild.php');
				build_forum_counters($threadinfo['forumid']);
			}

			// we do not appear to log thread title updates
			echo $threaddata->thread['title'];
			exit;
		}
	}

	echo $threadinfo['title'];
}

// #############################################################################
// toggle thread open/close

if ($_POST['do'] == 'updatethreadopen')
{
	$vbulletin->input->clean_array_gpc('p', array('threadid' => TYPE_UINT, 'src' => TYPE_NOHTML));

	if ($threadinfo['open'] == 10)
	{	// thread redirect
		exit;
	}

	// allow edit if...
	if (
		can_moderate($threadinfo['forumid'], 'canopenclose') // user is moderator
		OR
		(
			$threadinfo['postuserid'] == $vbulletin->userinfo['userid'] // user is thread first poster
			AND
			($forumperms = fetch_permissions($threadinfo['forumid'])) AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']) // user has permission to open / close own threads
		)
	)
	{
		if (strpos($vbulletin->GPC['src'], '_lock') !== false)
		{
			$open = 1;
		}
		else
		{
			$open = 0;
		}

		$threaddata =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threaddata->set_existing($threadinfo);
		$threaddata->set('open', $open); // note: mod logging will occur automatically
		if ($threaddata->save())
		{
			if ($open)
			{
				$vbulletin->GPC['src'] = str_replace('_lock', '', $vbulletin->GPC['src']);
			}
			else
			{
				$vbulletin->GPC['src'] = preg_replace('/(\_dot)?(\_hot)?(\_new)?(\.(gif|png|jpg))/', '\1\2_lock\3\4', $vbulletin->GPC['src']);
			}
		}
	}

	@header('Content-Type: text/plain' . iif($ajax_charset != '', '; charset=' . $ajax_charset));
	echo $vbulletin->GPC['src'];
}

// #############################################################################
// return a post in an editor

if ($_POST['do'] == 'quickedit')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'postid' => TYPE_UINT,
		'editorid' => TYPE_STR
	));

	if (!$vbulletin->options['quickedit'])
	{
		// if quick edit has been disabled after showthread is loaded, return a string to indicate such
		echo 'disabled';
		exit;
	}

	$vbulletin->GPC['editorid'] = preg_replace('/\W/s', '', $vbulletin->GPC['editorid']);

	if (!$postinfo['postid'])
	{
		exit;
	}

	if ((!$postinfo['visible'] OR $postinfo ['isdeleted']) AND !can_moderate($threadinfo['forumid']))
	{
		exit;
	}

	if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
	{
		exit;
	}

	$forumperms = fetch_permissions($threadinfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
	{
		exit;
	}
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
	{
		exit;
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// Tachy goes to coventry
	if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
	{
		// do not show post if part of a thread from a user in Coventry and bbuser is not mod
		exit;
	}
	if (in_coventry($postinfo['userid']) AND !can_moderate($threadinfo['forumid']))
	{
		// do not show post if posted by a user in Coventry and bbuser is not mod
		exit;
	}

	$show['managepost'] = iif(can_moderate($threadinfo['forumid'], 'candeleteposts') OR can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);
	$show['approvepost'] = (can_moderate($threadinfo['forumid'], 'canmoderateposts')) ? true : false;
	$show['managethread'] = (can_moderate($threadinfo['forumid'], 'canmanagethreads')) ? true : false;
	$show['quick_edit_form_tag'] = ($show['managethread'] OR $show['managepost'] OR $show['approvepost']) ? false : true;

	// Is this the first post in the thread?
	$isfirstpost = $postinfo['postid'] == $threadinfo['firstpostid'] ? true : false;

	if ($isfirstpost AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$show['deletepostoption'] = true;
	}
	else if (!$isfirstpost AND can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		$show['deletepostoption'] = true;
	}
	else if (((($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost']) AND !$isfirstpost) OR (($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread']) AND $isfirstpost)) AND $vbulletin->userinfo['userid'] == $postinfo['userid'])
	{
		$show['deletepostoption'] = true;
	}
	else
	{
		$show['deletepostoption'] = false;
	}

	$show['physicaldeleteoption'] = iif (can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);
	$show['keepattachmentsoption'] = iif ($postinfo['attach'], true, false);
	$show['firstpostnote'] = $isfirstpost;

	//header('Content-Type: text/html' . iif($ajax_charset != '', ';charset=' . strtolower($ajax_charset)));
	//echo "<textarea rows=\"10\" cols=\"60\" title=\"" . $vbulletin->GPC['editorid'] . "\">" . $postinfo['pagetext'] . '</textarea>';

	require_once(DIR . '/includes/functions_editor.php');

	$forum_allowsmilies = ($foruminfo['allowsmilies'] ? 1 : 0);
	$editor_parsesmilies = ($forum_allowsmilies AND $postinfo['allowsmilie'] ? 1 : 0);

	construct_edit_toolbar(htmlspecialchars_uni($postinfo['pagetext']), 0, $foruminfo['forumid'], $forum_allowsmilies, $postinfo['allowsmilie'], false, 'qe', $vbulletin->GPC['editorid']);

	header('Content-Type: text/xml' . iif($ajax_charset != '', ';charset=' . strtolower($ajax_charset)));
	echo '<?xml version="1.0" encoding="' . $ajax_charset . '"?>' .
		"\r\n<editor parsetype=\"$foruminfo[forumid]\" parsesmilies=\"$editor_parsesmilies\" mode=\"$show[is_wysiwyg_editor]\"><![CDATA[" .
		$messagearea .
		"]]></editor>";
}

// #############################################################################
// handle editor mode switching

if ($_POST['do'] == 'editorswitch')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'towysiwyg' => TYPE_BOOL,
		'message' => TYPE_STR,
		'parsetype' => TYPE_STR, // string to support non-forum options
		'allowsmilie' => TYPE_BOOL
	));

	$vbulletin->GPC['message'] = convert_urlencoded_unicode($vbulletin->GPC['message']);

	require_once(DIR . '/includes/functions_wysiwyg.php');

	if ($vbulletin->GPC['towysiwyg'])
	{
		// from standard to wysiwyg
		echo parse_wysiwyg_html($vbulletin->GPC['message'], false, $vbulletin->GPC['parsetype'], $vbulletin->GPC['allowsmilie']);
	}
	else
	{
		// from wysiwyg to standard
		switch ($vbulletin->GPC['parsetype'])
		{
			case 'calendar':
				$calendarinfo = verify_id('calendar', $vbulletin->GPC['parsetype'], 0, 1);
				$dohtml = $calendarinfo['allowhtml']; break;

			case 'privatemessage':
				$dohtml = $vbulletin->options['privallowhtml']; break;

			case 'usernote':
				$dohtml = $vbulletin->options['unallowhtml']; break;

			case 'nonforum':
				$dohtml = $vbulletin->options['allowhtml']; break;

			default:
				$parsetype = intval($vbulletin->GPC['parsetype']);
				$foruminfo = fetch_foruminfo($parsetype);
				$dohtml = $foruminfo['allowhtml']; break;
		}

		echo convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $dohtml);
	}
}

($hook = vBulletinHook::fetch_hook('ajax_complete')) ? eval($hook) : false;

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14418 $
|| ####################################################################
\*======================================================================*/
?>