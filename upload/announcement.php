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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'announcement');
define('CSRF_PROTECTION', true);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'postbit',
	'reputationlevel',
	'posting',
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'view' => array(
		'announcement',
		'im_aim',
		'im_icq',
		'im_msn',
		'im_yahoo',
		'im_skype',
		'postbit',
		'postbit_wrapper',
		'postbit_onlinestatus',
		'postbit_reputation',
		'bbcode_code',
		'bbcode_html',
		'bbcode_php',
		'bbcode_quote',
	),
	'edit' => array(
		'announcement_edit',
	),
);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'view';
}
else if ($_REQUEST['do'] == 'edit')
{
	define('GET_EDIT_TEMPLATES', true);
}

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vbulletin->input->clean_gpc('r', 'announcementid', TYPE_UINT);

($hook = vBulletinHook::fetch_hook('announcement_start')) ? eval($hook) : false;

// #############################################################################
// verify announcement id if specified
if ($vbulletin->GPC['announcementid'])
{
	$announcementinfo = verify_id('announcement', $vbulletin->GPC['announcementid'], 1, 1);
	if ($announcementinfo['forumid'] != -1 AND $_POST['do'] != 'update')
	{
		$vbulletin->GPC['forumid'] = $announcementinfo['forumid'];
	}
	$announcementinfo = array_merge($announcementinfo , convert_bits_to_array($announcementinfo['announcementoptions'], $vbulletin->bf_misc_announcementoptions));

	// verify that the visiting user has permission to view this announcement
	if (($announcementinfo['startdate'] > TIMENOW OR $announcementinfo['enddate'] < TIMENOW) AND !can_moderate($vbulletin->GPC['forumid'], 'canannounce'))
	{
		// announcement date is out of range and user is not a moderator
		print_no_permission();
	}
}

// #############################################################################
// delete an announcement
if ($_POST['do'] == 'delete')
{
	if ($vbulletin->input->clean_gpc('p', 'delete', TYPE_STR) == 'delete' AND can_moderate($announcementinfo['forumid'], 'canannounce'))
	{
		$anncdata = datamanager_init('Announcement', $vbulletin, ERRTYPE_STANDARD);
		$anncdata->set_existing($announcementinfo);
		$anncdata->delete();

		$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$announcementinfo[forumid]";
		eval(print_standard_redirect('deleted_announcement'));
	}
	else
	{
		exec_header_redirect('announcement.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&a=$announcementinfo[announcementid]");
	}
}

// #############################################################################
// insert or update an announcement
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'wysiwyg'     => TYPE_BOOL,
		'preview'     => TYPE_STR,
		'title'       => TYPE_STR,
		'message'     => TYPE_STR,
		'forumid'     => TYPE_INT,
		'startdate'   => TYPE_ARRAY_UINT,
		'enddate'     => TYPE_ARRAY_UINT,
		'options'     => TYPE_ARRAY_BOOL,
		'reset_views' => TYPE_BOOL
	));

	if (!can_moderate($vbulletin->GPC['forumid'], 'canannounce'))
	{
		// show no permission
		print_no_permission();
	}

	// unwysiwygify the incoming data
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$vbulletin->GPC['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $vbulletin->GPC['options']['allowhtml']);
	}

	$anncdata = datamanager_init('Announcement', $vbulletin, ERRTYPE_STANDARD);

	if ($announcementinfo)
	{
		$anncdata->set_existing($announcementinfo);

		if ($vbulletin->GPC['reset_views'])
		{
			define('RESET_VIEWS', true);
			$anncdata->set('views', 0);
		}
	}
	else
	{
		$anncdata->set('userid', $vbulletin->userinfo['userid']);
	}

	$anncdata->set('title', $vbulletin->GPC['title']);
	$anncdata->set('pagetext', $vbulletin->GPC['message']);
	$anncdata->set('forumid', $vbulletin->GPC['forumid']);
	$anncdata->set('startdate', $vbulletin->GPC['startdate']);
	$anncdata->set('enddate', $vbulletin->GPC['enddate']);

	foreach ($vbulletin->bf_misc_announcementoptions AS $key => $val)
	{
		$anncdata->set_bitfield('announcementoptions', $key, $vbulletin->GPC['options']["$key"]);
	}

	$announcementid = $anncdata->save();

	if ($announcementinfo)
	{
		if ($vbulletin->GPC['reset_views'])
		{
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "announcementread WHERE announcementid = $announcementinfo[announcementid]");
		}
		$announcementid = $announcementinfo['announcementid'];
	}

	$title = $anncdata->fetch_field('title');

	$vbulletin->url = 'announcement.php?' . $vbulletin->session->vars['sessionurl'] . "a=$announcementid";
	eval(print_standard_redirect('saved_announcement'));
}

// #############################################################################
// edit an announcement
if ($_REQUEST['do'] == 'edit')
{
	require_once(DIR . '/includes/functions_misc.php');
	require_once(DIR . '/includes/functions_editor.php');
	require_once(DIR . '/includes/functions_newpost.php');
	require_once(DIR . '/includes/modfunctions.php');

	if ($announcementinfo['announcementid'])
	{
		if (!can_moderate($announcementinfo['forumid'], 'canannounce'))
		{
			// show no permission
			print_no_permission();
		}

		$show['editing_mode'] = true;
		$announcementinfo['title'] = fetch_censored_text($announcementinfo['title']);
	}
	else
	{
		if (!can_moderate($vbulletin->GPC['forumid'], 'canannounce'))
		{
			// show no permission
			print_no_permission();
		}

		$announcementinfo = array(
			'forumid'             => $vbulletin->GPC['forumid'],
			'title'               => '',
			'pagetext'            => '',
			'startdate'           => TIMENOW,
			'enddate'             => vbmktime(0, 0, 0, vbdate('n', TIMENOW, false, false) + 1, vbdate('j', TIMENOW, false, false), vbdate('Y', TIMENOW, false, false)),
			'announcementoptions' => 29
		);

		$show['editing_mode'] = false;
	}

	$announcementinfo['title_safe'] = htmlspecialchars_uni($announcementinfo['title']);

	// checkboxes
	$checked = array();
	foreach ($vbulletin->bf_misc_announcementoptions AS $key => $val)
	{
		$checked["$key"] = ($announcementinfo['announcementoptions'] & $val ? ' checked="checked"' : '');
	}

	// date fields
	foreach (array('start', 'end') AS $date_type)
	{
		$GLOBALS["{$date_type}_date_array"] = array(
			'day'   => vbdate('j', $announcementinfo["{$date_type}date"], false, false),
			'month' => vbdate('n', $announcementinfo["{$date_type}date"], false, false),
			'year'  => vbdate('Y', $announcementinfo["{$date_type}date"], false, false)
		);

		$GLOBALS["{$date_type}_month_selected"] = array();
		for ($i = 1;  $i <= 12; $i++)
		{
			$GLOBALS["{$date_type}_month_selected"]["$i"] = ($i == $GLOBALS["{$date_type}_date_array"]['month'] ? ' selected="selected"' : '');
		}
	}

	// forum choice
	$forum_options_array = fetch_moderator_forum_options($vbphrase['all_forums'], ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']), false, 'canannounce', '--', false);
	$forum_options = '';
	foreach ($forum_options_array AS $optionvalue => $optiontitle)
	{
		if ($optionvalue == $announcementinfo['forumid'])
		{
			$optionselected = ' selected="selected"';
			$optionclass = 'fjsel';
		}
		else
		{
			$optionselected = '';
			$optionclass = '';
		}
		eval('$forum_options .= "' . fetch_template('option') . '";');
	}

	$post =& $announcementinfo;

	// build editor
	$editorid = construct_edit_toolbar(
		$announcementinfo['pagetext'],
		0, // is html?
		'announcement', // forumid
		true, // allow smilies
		($announcementinfo['announcementoptions'] & $vbulletin->bf_misc_announcementoptions['allowsmilies']) ? 1 : 0 // parse smilies
	);

	// build navbar
	$navbits = array();

	if ($announcementinfo['forumid'] == -1)
	{
		$navbits["announcement.php?" . $vbulletin->session->vars['sessionurl'] . "do=view&amp;a=$announcementinfo[announcementid]"] = $vbphrase['global_announcement'];
	}
	else
	{
		$foruminfo =& $vbulletin->forumcache["$announcementinfo[forumid]"];
		$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
		foreach ($parentlist AS $forumID)
		{
			$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
			$navbits["forumdisplay.php?" . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
		}
		$navbits['announcement.php?' . $vbulletin->session->vars['sessionurl'] . "f=$announcementinfo[forumid]"] = $vbphrase['announcements'];
	}

	if ($announcementinfo['announcementid'])
	{
		$navbits['announcement.php?' . $vbulletin->session->vars['sessionurl'] . "a=$announcementinfo[announcementid]"] = $announcementinfo['title'];
		$navbits[''] = $vbphrase['edit_announcement'];
	}
	else
	{
		$navbits[''] = $vbphrase['post_new_announcement'];
	}

	$navbits[''] = ($announcementinfo['announcementid'] ? $vbphrase['edit_announcement'] : $vbphrase['post_new_announcement']);
	$navbits = construct_navbits($navbits);

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template('announcement_edit') . '");');
}

// #############################################################################
if ($_REQUEST['do'] == 'view')
{
	$forumlist = '';
	if ($announcementinfo['forumid'] > -1 OR $vbulletin->GPC['forumid'])
	{
		$foruminfo = verify_id('forum', $vbulletin->GPC['forumid'], 1, 1);
		$curforumid = $foruminfo['forumid'];
		$forumperms = fetch_permissions($foruminfo['forumid']);

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		{
			print_no_permission();
		}

		// check if there is a forum password and if so, ensure the user has it set
		verify_forum_password($foruminfo['forumid'], $foruminfo['password']);
		$forumlist = fetch_forum_clause_sql($foruminfo['forumid'], 'announcement.forumid');
	}
	else if (!$announcementinfo['announcementid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['announcement'], $vbulletin->options['contactuslink'])));
	}

	construct_forum_jump();

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('announcement_query')) ? eval($hook) : false;

	$announcements = $db->query_read_slave("
		SELECT announcement.announcementid, announcement.announcementid AS postid, startdate, enddate, announcement.title, pagetext, announcementoptions, views,
			user.*, userfield.*, usertextfield.*,
			sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
			IF(user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
			" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			" . (($vbulletin->userinfo['userid']) ? ", NOT ISNULL(announcementread.announcementid) AS readannouncement" : "") . "
			$hook_query_fields
		FROM  " . TABLE_PREFIX . "announcement AS announcement
		" . (($vbulletin->userinfo['userid']) ? "LEFT JOIN " . TABLE_PREFIX . "announcementread AS announcementread ON(announcementread.announcementid = announcement.announcementid AND announcementread.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid=announcement.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid=announcement.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid=announcement.userid)
		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = announcement.userid)
		" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid=user.avatarid)
		LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid=announcement.userid)" : "") . "
		$hook_query_joins
		WHERE
			" . ($vbulletin->GPC['announcementid'] ?
				"announcement.announcementid = " . $vbulletin->GPC['announcementid'] :
				"startdate <= " . TIMENOW . " AND enddate >= " . TIMENOW . " " . (!empty($forumlist) ? "AND $forumlist" : "")
			) . "
			$hook_query_where
		ORDER BY startdate DESC, announcementid DESC
	");

	if ($db->num_rows($announcements) == 0)
	{ // no announcements
		eval(standard_error(fetch_error('invalidid', $vbphrase['announcement'], $vbulletin->options['contactuslink'])));
	}

	if (!$vbulletin->options['oneannounce'] AND $vbulletin->GPC['announcementid'] AND !empty($forumlist))
	{
		$anncount = $db->query_first_slave("
			SELECT COUNT(*) AS total
			FROM " . TABLE_PREFIX . "announcement AS announcement
			WHERE startdate <= " . TIMENOW . "
				AND enddate >= " . TIMENOW . "
				AND $forumlist
		");
		$anncount['total'] = intval($anncount['total']);
		$show['viewall'] = $anncount['total'] > 1 ? true : false;
	}
	else
	{
		$show['viewall'] = false;
	}

	require_once(DIR . '/includes/class_postbit.php');

	$show['announcement'] = true;

	$counter = 0;
	$anncids = array();
	$announcebits = '';
	$announceread = array();

	$postbit_factory = new vB_Postbit_Factory();
	$postbit_factory->registry =& $vbulletin;
	$postbit_factory->forum =& $foruminfo;
	$postbit_factory->cache = array();
	$postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	while ($post = $db->fetch_array($announcements))
	{
		$postbit_obj =& $postbit_factory->fetch_postbit('announcement');

		$post['counter'] = ++$counter;

		$announcebits .= $postbit_obj->construct_postbit($post);
		$anncids[] = $post['announcementid'];
		$announceread[] = "($post[announcementid], " . $vbulletin->userinfo['userid'] . ")";
	}

	if (!empty($anncids))
	{
		$db->shutdown_query("
			UPDATE " . TABLE_PREFIX . "announcement
			SET views = views + 1
			WHERE announcementid IN (" . implode(', ', $anncids) . ")
		");

		if ($vbulletin->userinfo['userid'])
		{
			$db->shutdown_query("
				REPLACE INTO " . TABLE_PREFIX . "announcementread
					(announcementid, userid)
				VALUES
					" . implode(', ', $announceread) . "
			");
		}
	}

	// show add/edit link?
	$show['post_new_announcement'] = can_moderate($foruminfo['forumid'], 'canannounce');

	// build navbar
	$navbits = array();

	if ($announcementinfo['forumid'] == -1)
	{
		$navbits["announcement.php?" . $vbulletin->session->vars['sessionurl'] . "a=$announcementinfo[announcementid]"] = $vbphrase['announcements'];
		$navbits[$vbulletin->options['forumhome']. '.php'] = $announcementinfo['title'];
		$show['global'] = true;
	}
	else
	{
		$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
		foreach ($parentlist AS $forumID)
		{
			$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
			$navbits["forumdisplay.php?" . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
		}
		$navbits[$vbulletin->options['forumhome']. '.php'] = $vbphrase['announcements'];
	}

	$navbits = construct_navbits($navbits);

	($hook = vBulletinHook::fetch_hook('announcement_complete')) ? eval($hook) : false;

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template('announcement') . '");');
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
