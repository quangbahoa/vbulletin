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
define('THIS_SCRIPT', 'threadtag');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting', 'showthread');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'newpost_errormessage',
	'tag_edit',
	'tag_managebit',
	'tagbit',
	'tagbit_wrapper'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_bigthree.php');

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'manage';
}

$threadinfo = verify_id('thread', $vbulletin->GPC['threadid'], 1, 1);

if (!$vbulletin->options['threadtagging'])
{
	print_no_permission();
}

// *********************************************************************************
// check for visible / deleted thread
if (((!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts'))) OR ($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'])))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
}

// *********************************************************************************
// jump page if thread is actually a redirect
if ($threadinfo['open'] == 10)
{
	exec_header_redirect('showthread.php?' . $vbulletin->session->vars['sessionurl_js'] . "t=$threadinfo[pollid]");
}

// *********************************************************************************
// Tachy goes to coventry
if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
}

// *********************************************************************************
// get forum info
$foruminfo = fetch_foruminfo($threadinfo['forumid']);

// *********************************************************************************
// check forum permissions
$forumperms = fetch_permissions($threadinfo['forumid']);
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
{
	print_no_permission();
}
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
{
	print_no_permission();
}

// *********************************************************************************
// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

if (!$foruminfo['allowposting'] OR (!$threadinfo['open'] AND !can_moderate($threadinfo['forumid'], 'canopenclose')))
{
	// thread is closed and can't be opened by this person
	$show['add_option'] = false;
	$show['manage_existing_option'] = can_moderate($threadinfo['forumid'], 'caneditthreads');
}
else
{
	$show['add_option'] = (
		(($forumperms & $vbulletin->bf_ugp_forumpermissions['cantagown']) AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])
		OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['cantagothers'])
	);

	$show['manage_existing_option'] = (
		$show['add_option']
		OR (($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletetagown']) AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])
		OR can_moderate($threadinfo['forumid'], 'caneditthreads')
	);
}

($hook = vBulletinHook::fetch_hook('threadtag_start')) ? eval($hook) : false;

if (!$show['add_option'] AND !$show['manage_existing_option'])
{
	print_no_permission();
}

// ##############################################################################
if ($_POST['do'] == 'managetags')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'tagskept' => TYPE_ARRAY_UINT,
		'tagsshown' => TYPE_ARRAY_UINT,
		'taglist' => TYPE_NOHTML,
		'ajax' => TYPE_BOOL
	));

	if ($vbulletin->GPC['ajax'])
	{
		$vbulletin->GPC['taglist'] = convert_urlencoded_unicode($vbulletin->GPC['taglist']);
	}

	if ($vbulletin->GPC['tagsshown'] AND $show['manage_existing_option'])
	{
		$tags_sql = $db->query_read("
			SELECT tag.*, tagthread.userid
			FROM " . TABLE_PREFIX . "tagthread AS tagthread
			INNER JOIN " . TABLE_PREFIX . "tag AS tag ON (tag.tagid = tagthread.tagid)
			WHERE tagthread.threadid = $threadinfo[threadid]
				AND tagthread.tagid IN (" . implode(',', $vbulletin->GPC['tagsshown']) . ")
		");

		$delete = array();
		while ($tag = $db->fetch_array($tags_sql))
		{
			if ($tag['userid'] == $vbulletin->userinfo['userid']
				OR (($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletetagown']) AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])
				OR can_moderate($threadinfo['forumid'], 'caneditthreads')
			)
			{
				if (!in_array($tag['tagid'], $vbulletin->GPC['tagskept']))
				{
					$delete[] = $tag['tagid'];
				}
			}
		}

		($hook = vBulletinHook::fetch_hook('threadtag_domanage_delete')) ? eval($hook) : false;

		if ($delete)
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "tagthread
				WHERE threadid = $threadinfo[threadid]
					AND tagid IN (" . implode(',', $delete) . ")
			");

			$threadinfo['taglist'] = rebuild_thread_taglist($threadinfo['threadid']);
		}
	}

	($hook = vBulletinHook::fetch_hook('threadtag_domanage_postdelete')) ? eval($hook) : false;

	if ($vbulletin->GPC['taglist'] AND $show['add_option'])
	{
		$errors = add_tags_to_thread($threadinfo, $vbulletin->GPC['taglist']);
	}
	else
	{
		$errors = array();
	}

	if ($vbulletin->GPC['ajax'])
	{
		$threadinfo = fetch_threadinfo($threadinfo['threadid'], false); // get updated tag list
		$tagcount = ($threadinfo['taglist'] ? count(explode(',', $threadinfo['taglist'])) : 0);

		require_once(DIR . '/includes/class_xml.php');

		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_group('tag');
			$xml->add_tag('taghtml', process_replacement_vars(fetch_tagbits($threadinfo)));
			if ($errors)
			{
				$errorlist = '';
				foreach ($errors AS $error)
				{
					$errorlist .= "\n   * $error";
				}
				$xml->add_tag('warning', fetch_error('tag_add_failed_plain', $errorlist));
			}
		$xml->close_group();
		$xml->print_xml();
	}
	else
	{
		if ($errors)
		{
			$errorlist = '';
			foreach ($errors AS $key => $errormessage)
			{
				eval('$errorlist .= "' . fetch_template('newpost_errormessage') . '";');
			}

			$errorlist = fetch_error('tag_add_failed_html', $errorlist, 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]#taglist");

			$_REQUEST['do'] = 'manage';
			define('ADD_ERROR', true);
		}
		else
		{
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]#taglist";
			eval(print_standard_redirect(fetch_error('tags_edited_successfully'), false));
		}
	}
}

// ##############################################################################
if ($_REQUEST['do'] == 'manage')
{
	$show['errors'] = defined('ADD_ERROR');
	if (!$show['errors'])
	{
		$valid_tag_html = '';
	}

	$tag_manage_options = '';
	$have_removal_tags = false;
	$mytags = 0;

	$tags_sql = $db->query_read("
		SELECT tag.*, tagthread.userid, user.username
		FROM " . TABLE_PREFIX . "tagthread AS tagthread
		INNER JOIN " . TABLE_PREFIX . "tag AS tag ON (tag.tagid = tagthread.tagid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = tagthread.userid)
		WHERE tagthread.threadid = $threadinfo[threadid]
		ORDER BY tag.tagtext
	");
	$total_tags = $db->num_rows($tags_sql);

	if ($total_tags == 0 AND !$show['add_option'])
	{
		print_no_permission();
	}

	while ($tag = $db->fetch_array($tags_sql))
	{
		$tag['ismine'] = ($tag['userid'] == $vbulletin->userinfo['userid']);
		$show['tag_checkbox'] = ($tag['ismine']
			OR (($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletetagown']) AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])
			OR can_moderate($threadinfo['forumid'], 'caneditthreads')
		);

		if ($show['tag_checkbox'])
		{
			$have_removal_tags = true;
		}

		if ($tag['ismine'])
		{
			$mytags++;
		}

		// only moderators can see who added a tag
		if (!can_moderate($threadinfo['forumid'], 'caneditthreads'))
		{
			$tag['username'] = '';
		}

		($hook = vBulletinHook::fetch_hook('threadtag_managebit')) ? eval($hook) : false;

		eval('$tag_manage_options .= "' . fetch_template('tag_managebit') . '";');
	}

	// determine the number of tags this person can add
	$user_tags_remain = null;

	if ($vbulletin->options['tagmaxthread'])
	{
		// check global limit
		$tags_remain = max(0, $vbulletin->options['tagmaxthread'] - $total_tags);
		if ($tags_remain == 0 AND !$have_removal_tags)
		{
			// thread full and no tags can be removed - error
			standard_error(fetch_error('thread_has_max_allowed_tags'));
		}

		$user_tags_remain = $tags_remain;
	}

	if (!can_moderate($threadinfo['forumid'], 'caneditthreads'))
	{
		$tags_remain = null;
		if ($vbulletin->options['tagmaxstarter'] AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])
		{
			$tags_remain = max(0, $vbulletin->options['tagmaxstarter'] - $mytags);
		}
		else if ($vbulletin->options['tagmaxuser'])
		{
			$tags_remain = max(0, $vbulletin->options['tagmaxuser'] - $mytags);
		}

		if ($tags_remain !== null)
		{
			if ($user_tags_remain == null)
			{
				$user_tags_remain = $tags_remain;
			}
			else
			{
				$user_tags_remain = min($tags_remain, $user_tags_remain);
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('threadtag_manage_tagsremain')) ? eval($hook) : false;

	$show['tag_limit_phrase'] = ($user_tags_remain !== null);
	$tags_remain = vb_number_format($user_tags_remain);
	$tag_delimiters = addslashes_js($vbulletin->options['tagdelimiter']);

	if ($vbulletin->GPC['ajax'])
	{
		eval('$html = "' . fetch_template('tag_edit_ajax') . '";');

		require_once(DIR . '/includes/class_xml.php');

		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_group('tag');
			$xml->add_tag('html', process_replacement_vars($html));
			$xml->add_tag('delimiters', $vbulletin->options['tagdelimiter']);
		$xml->close_group();
		$xml->print_xml();
	}

	// navbar and output
	$navbits = array();

	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumid)
	{
		$forum_title = $vbulletin->forumcache["$forumid"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumid"] = $forum_title;
	}
	$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]"] = $threadinfo['prefix_plain_html'] . ' ' . $threadinfo['title'];
	$navbits[''] = $vbphrase['tag_management'];

	$navbits = construct_navbits($navbits);

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template('tag_edit') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
