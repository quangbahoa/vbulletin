<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
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
define('CSRF_PROTECTION', true);
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

$_POST['ajax'] = 1;

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_xml.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

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

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_group('users');

	if ($fragment != '')
	{
		$users = $db->query_read_slave("
			SELECT userid, username FROM " . TABLE_PREFIX . "user
			WHERE username LIKE('" . $db->escape_string_like($fragment) . "%')
			ORDER BY username
			LIMIT 15
		");
		while ($user = $db->fetch_array($users))
		{
			$xml->add_tag('user', $user['username'], array('userid' => $user['userid']));
		}
	}

	$xml->close_group();
	$xml->print_xml();
}

// #############################################################################
// update thread title

if ($_POST['do'] == 'updatethreadtitle')
{
	$vbulletin->input->clean_array_gpc('p', array('threadid' => TYPE_UINT, 'title' => TYPE_STR));

	// allow edit if...
	if (
		$threadinfo
		AND
		can_moderate($threadinfo['forumid'], 'caneditthreads') // ...user is moderator
		OR
		(
			$threadinfo['open']
			AND
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
		if (!can_moderate($threadinfo['forumid']))
		{
			$threaddata->set_info('skip_moderator_log', true);
		}

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

			cache_ordered_forums(1);

			if ($vbulletin->forumcache["$threadinfo[forumid]"]['lastthreadid'] == $threadinfo['threadid'])
			{
				require_once(DIR . '/includes/functions_databuild.php');
				build_forum_counters($threadinfo['forumid']);
			}

			// we do not appear to log thread title updates
			$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
			$xml->add_tag('linkhtml', $threaddata->thread['title']);
			$xml->print_xml();
			exit;
		}
	}

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_tag('linkhtml', $threadinfo['title']);
	$xml->print_xml();
}

// #############################################################################
// toggle thread open/close

if ($_POST['do'] == 'updatethreadopen')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'threadid' => TYPE_UINT,
		'src' => TYPE_NOHTML
	));

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

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_tag('imagesrc', $vbulletin->GPC['src']);
	$xml->print_xml();
}

// #############################################################################
// return a post in an editor

if ($_POST['do'] == 'quickedit')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'postid' => TYPE_UINT,
		'editorid' => TYPE_STR
	));

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	if (!$vbulletin->options['quickedit'])
	{
		// if quick edit has been disabled after showthread is loaded, return a string to indicate such
		$xml->add_tag('disabled', 'true');
		$xml->print_xml();
	}
	else
	{
		$vbulletin->GPC['editorid'] = preg_replace('/\W/s', '', $vbulletin->GPC['editorid']);

		if (!$postinfo['postid'])
		{
			$xml->add_tag('error', 'invalidid');
			$xml->print_xml();
		}

		if ((!$postinfo['visible'] OR $postinfo ['isdeleted']) AND !can_moderate($threadinfo['forumid']))
		{
			$xml->add_tag('error', 'nopermission');
			$xml->print_xml();
		}

		if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
		{
			$xml->add_tag('error', 'nopermission');
			$xml->print_xml();
		}

		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		{
			$xml->add_tag('error', 'nopermission');
			$xml->print_xml();
		}
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
		{
			$xml->add_tag('error', 'nopermission');
			$xml->print_xml();
		}

		// check if there is a forum password and if so, ensure the user has it set
		verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

		// Tachy goes to coventry
		if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
		{
			// do not show post if part of a thread from a user in Coventry and bbuser is not mod
			$xml->add_tag('error', 'nopermission');
			$xml->print_xml();
		}
		if (in_coventry($postinfo['userid']) AND !can_moderate($threadinfo['forumid']))
		{
			// do not show post if posted by a user in Coventry and bbuser is not mod
			$xml->add_tag('error', 'nopermission');
			$xml->print_xml();
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

		//exec_ajax_content_type_header('text/html', $ajax_charset);
		//echo "<textarea rows=\"10\" cols=\"60\" title=\"" . $vbulletin->GPC['editorid'] . "\">" . $postinfo['pagetext'] . '</textarea>';

		require_once(DIR . '/includes/functions_editor.php');

		$forum_allowsmilies = ($foruminfo['allowsmilies'] ? 1 : 0);
		$editor_parsesmilies = ($forum_allowsmilies AND $postinfo['allowsmilie'] ? 1 : 0);

		$post =& $postinfo;

		construct_edit_toolbar(htmlspecialchars_uni($postinfo['pagetext']), 0, $foruminfo['forumid'], $forum_allowsmilies, $postinfo['allowsmilie'], false, 'qe', $vbulletin->GPC['editorid']);

		$xml->add_group('quickedit');
		$xml->add_tag('editor', $messagearea, array(
			'reason' => $postinfo['edit_reason'],
			'parsetype' => $foruminfo['forumid'],
			'parsesmilies' => $editor_parsesmilies,
			'mode' => $show['is_wysiwyg_editor']
		));
		$xml->close_group();
		$xml->print_xml();
	}
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

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	require_once(DIR . '/includes/functions_wysiwyg.php');

	if ($vbulletin->GPC['parsetype'] == 'calendar')
	{
		require_once(DIR . '/includes/functions_calendar.php');
		$vbulletin->input->clean_gpc('p', 'calendarid', TYPE_UINT);
		$calendarinfo = verify_id('calendar', $vbulletin->GPC['calendarid'], 0, 1);
		if ($calendarinfo)
		{
			$getoptions = convert_bits_to_array($calendarinfo['options'], $_CALENDAROPTIONS);
			$geteaster = convert_bits_to_array($calendarinfo['holidays'], $_CALENDARHOLIDAYS);
			$calendarinfo = array_merge($calendarinfo, $getoptions, $geteaster);
		}
	}

	if ($vbulletin->GPC['towysiwyg'])
	{
		// from standard to wysiwyg
		$xml->add_tag('message', parse_wysiwyg_html(htmlspecialchars_uni($vbulletin->GPC['message']), false, $vbulletin->GPC['parsetype'], $vbulletin->GPC['allowsmilie']));
	}
	else
	{
		// from wysiwyg to standard
		switch ($vbulletin->GPC['parsetype'])
		{
			case 'calendar':
				$dohtml = $calendarinfo['allowhtml']; break;

			case 'privatemessage':
				$dohtml = $vbulletin->options['privallowhtml']; break;

			case 'usernote':
				$dohtml = $vbulletin->options['unallowhtml']; break;

			case 'nonforum':
				$dohtml = $vbulletin->options['allowhtml']; break;

			case 'signature':
				$dohtml = ($vbulletin->userinfo['permissions']['signaturepermissions'] & $vbulletin->bf_ugp_signaturepermissions['allowhtml']); break;

			default:
				if (intval($vbulletin->GPC['parsetype']))
				{
					$parsetype = intval($vbulletin->GPC['parsetype']);
					$foruminfo = fetch_foruminfo($parsetype);
					$dohtml = $foruminfo['allowhtml']; break;
				}
				else
				{
					$dohtml = false;
				}

				($hook = vBulletinHook::fetch_hook('editor_switch_wysiwyg_to_standard')) ? eval($hook) : false;
		}

		$xml->add_tag('message', convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $dohtml));
	}

	$xml->print_xml();
}

// #############################################################################
// mark forums read

if ($_POST['do'] == 'markread')
{
	$vbulletin->input->clean_gpc('p', 'forumid', TYPE_UINT);

	require_once(DIR . '/includes/functions_misc.php');
	$mark_read_result = mark_forums_read($foruminfo['forumid']);

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_group('readmarker');

	$xml->add_tag('phrase', $mark_read_result['phrase']);
	$xml->add_tag('url', $mark_read_result['url']);

	$xml->add_group('forums');
	if (is_array($mark_read_result['forumids']))
	{
		foreach ($mark_read_result['forumids'] AS $forumid)
		{
			$xml->add_tag('forum', $forumid);
		}
	}
	$xml->close_group();

	$xml->close_group();
	$xml->print_xml();
}

// ###########################################################################
// Image Verification

if ($_POST['do'] == 'imagereg')
{
	$vbulletin->input->clean_gpc('p', 'imagehash', TYPE_STR);

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	if ($vbulletin->options['regimagetype'])
	{
		require_once(DIR . '/includes/functions_regimage.php');
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "regimage
			WHERE regimagehash = '" . $db->escape_string($vbulletin->GPC['imagehash']) . "'
		");
		$xml->add_tag('imagehash', fetch_regimage_hash());
	}
	else
	{
		$xml->add_tag('error', fetch_error('register_imagecheck'));
	}
	$xml->print_xml();
}

($hook = vBulletinHook::fetch_hook('ajax_complete')) ? eval($hook) : false;

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 26402 $
|| ####################################################################
\*======================================================================*/
?>