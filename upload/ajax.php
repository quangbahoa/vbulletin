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
define('THIS_SCRIPT', 'ajax');
define('CSRF_PROTECTION', true);
define('LOCATION_BYPASS', 1);
define('NOPMPOPUP', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting');
switch ($_POST['do'])
{
	case 'fetchuserfield':
	case 'saveuserfield':
		$phrasegroups[] = 'cprofilefield';
		$phrasegroups[] = 'user';
		break;
	case 'verifyusername':
		$phrasegroups[] = 'register';
		break;
}

// get special data templates from the datastore
$specialtemplates = array('bbcodecache');

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'fetchuserfield' => array(
		'memberinfo_customfield_edit',
		'userfield_checkbox_option',
		'userfield_optional_input',
		'userfield_radio',
		'userfield_radio_option',
		'userfield_select',
		'userfield_select_option',
		'userfield_select_multiple',
		'userfield_textarea',
		'userfield_textbox',
	),
	'quickedit' => array(
		'editor_clientscript',
		'editor_css',
		'editor_jsoptions_font',
		'editor_jsoptions_size',
		'editor_smilie',
		'editor_smiliebox',
		'editor_smiliebox_row',
		'editor_smiliebox_straggler',
		'newpost_disablesmiliesoption',
		'postbit_quickedit',
	)
);

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
// tag search

if ($_POST['do'] == 'tagsearch')
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
	$xml->add_group('tags');

	if ($fragment != '')
	{
		$tags = $db->query_read_slave("
			SELECT tagtext
			FROM " . TABLE_PREFIX . "tag
			WHERE tagtext LIKE '" . $db->escape_string_like($fragment) . "%'
			ORDER BY tagtext
			LIMIT 15
		");
		while ($tag = $db->fetch_array($tags))
		{
			$xml->add_tag('tag', $tag['tagtext']);
		}
	}

	$xml->close_group();
	$xml->print_xml();
}

// #############################################################################
// update thread title

if ($_POST['do'] == 'updatethreadtitle')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'threadid' => TYPE_UINT,
		'title'    => TYPE_STR
	));

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
		$threaddata = datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
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
		'src'      => TYPE_NOHTML
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

		$threaddata = datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
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
		'postid'   => TYPE_UINT,
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

		$show['softdeleteoption'] = true;
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
		$xml->add_tag('editor', process_replacement_vars($messagearea), array(
			'reason'       => $postinfo['edit_reason'],
			'parsetype'    => $foruminfo['forumid'],
			'parsesmilies' => $editor_parsesmilies,
			'mode'         => $show['is_wysiwyg_editor']
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
		'towysiwyg'    => TYPE_BOOL,
		'message'      => TYPE_STR,
		'parsetype'    => TYPE_STR, // string to support non-forum options
		'allowsmilie'  => TYPE_BOOL,
		'allowbbcode'  => TYPE_BOOL, // run time editor option for announcements
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
	if ($vbulletin->GPC['parsetype'] == 'announcement')
	{	// oh this is a kludge but there is no simple way to changing the bbcode parser from using global $post with announcements without changing function arguments
		$post = array(
			'announcementoptions' => $vbulletin->GPC['allowbbcode'] ? $vbulletin->bf_misc_announcementoptions['allowbbcode'] : 0
		);
	}

	if ($vbulletin->GPC['towysiwyg'])
	{
		// from standard to wysiwyg
		$xml->add_tag('message', process_replacement_vars(parse_wysiwyg_html(htmlspecialchars_uni($vbulletin->GPC['message']), false, $vbulletin->GPC['parsetype'], $vbulletin->GPC['allowsmilie'])));
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

		$xml->add_tag('message', process_replacement_vars(convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $dohtml)));
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
	$vbulletin->input->clean_gpc('p', 'hash', TYPE_STR);

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	if ($vbulletin->options['hv_type'] == 'Image')
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify = vB_HumanVerify::fetch_library($vbulletin);
		$verify->delete_token($vbulletin->GPC['hash']);
		$output = $verify->generate_token();
		$xml->add_tag('hash', $output['hash']);
	}
	else
	{
		$xml->add_tag('error', fetch_error('humanverify_image_wronganswer'));
	}
	$xml->print_xml();
}

// ###########################################################################
// New Securitytoken

if ($_POST['do'] == 'securitytoken')
{
	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	$xml->add_tag('securitytoken', $vbulletin->userinfo['securitytoken']);

	$xml->print_xml();
}

// #############################################################################
// fetch a profile field editor
if ($_POST['do'] == 'fetchuserfield')
{
	require_once(DIR . '/includes/functions_user.php');

	$vbulletin->input->clean_array_gpc('p', array(
		'fieldid' => TYPE_UINT
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_group('response');

	if ($profilefield = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "profilefield WHERE profilefieldid = " . $vbulletin->GPC['fieldid']))
	{
		if ($profilefield['editable'] == 1 OR ($profilefield['editable'] == 2 AND empty($vbulletin->userinfo["field$profilefield[profilefieldid]"])))
		{
			$profilefield_template = fetch_profilefield($profilefield, 'memberinfo_customfield_edit');
			$xml->add_tag('template', process_replacement_vars($profilefield_template));
		}
		else
		{
			$xml->add_tag('error', fetch_error('profile_field_uneditable'));
			$xml->add_tag('uneditable', '1');
		}
	}
	else
	{
		// we want this person to refresh the page, so just throw a no perm error
		print_no_permission();
	}

	$xml->close_group();
	$xml->print_xml();
}

// #############################################################################
// dismisses a dismissible notice
if ($_POST['do'] == 'dismissnotice')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'noticeid'	=> TYPE_UINT
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$update_record = $db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "noticedismissed
			(noticeid, userid)
		VALUES
			(" . $vbulletin->GPC['noticeid'] . ", " . $vbulletin->userinfo['userid'] .")
	");

	// output XML
	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_group('response');
		$xml->add_tag('dismissed', $vbulletin->GPC['noticeid']);
	$xml->close_group();
	$xml->print_xml();
}

// #############################################################################
// save a profile field
if ($_POST['do'] == 'saveuserfield')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'fieldid'   => TYPE_UINT,
		'userfield' => TYPE_ARRAY
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canmodifyprofile']))
	{
		print_no_permission();
	}

	/**
	* Recursively converts unicode entities for AJAX saving
	*
	* @param	mixed	Item to be converted
	*
	* @return	mixed	Converted item
	*/
	function convert_urlencoded_unicode_recursive($item)
	{
		if (is_array($item))
		{
			foreach ($item AS $key => $value)
			{
				$item["$key"] = convert_urlencoded_unicode_recursive($value);
			}
		}
		else
		{
			$item = convert_urlencoded_unicode(trim($item));
		}

		return $item;
	}

	// handle AJAX posting of %u00000 entries
	$vbulletin->GPC['userfield'] = convert_urlencoded_unicode_recursive($vbulletin->GPC['userfield']);

	// init user datamanager
	$userdata = datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
	$userdata->set_existing($vbulletin->userinfo);
	$userdata->set_userfields($vbulletin->GPC['userfield']);
	$userdata->save();

	// fetch profilefield data
	$profilefield = $db->query_first("
		SELECT * FROM " . TABLE_PREFIX . "profilefield
		WHERE profilefieldid = " . $vbulletin->GPC['fieldid']
	);

	// get displayable profilefield value
	$new_value = (isset($userdata->userfield['field' . $vbulletin->GPC['fieldid']]) ?
		$userdata->userfield['field' . $vbulletin->GPC['fieldid']] :
		$vbulletin->userinfo['field' . $vbulletin->GPC['fieldid']]
	);
	fetch_profilefield_display($profilefield, $new_value);

	// output XML
	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_group('response');

	$returnvalue = $profilefield['value'] == '' ? $vbphrase['n_a'] : $profilefield['value'];
	$xml->add_tag('value', process_replacement_vars($returnvalue));
	if ($profilefield['editable'] == 2 AND !empty($new_value))
	{
		// this field is no longer editable
		$xml->add_tag('uneditable', '1');
	}

	$xml->close_group();
	$xml->print_xml();
}

// #############################################################################
// verify username during registration

if ($_POST['do'] == 'verifyusername')
{
	/**
	* Checks username status, and return status for registration
	* Values for the XML output includes:
	* username: a direct copy of the original Username, for references needs
	* status: valid / invalid username?
	* response: string of error message from the datamanager
	*/

	$vbulletin->input->clean_gpc('p', 'username', TYPE_STR);
	$vbulletin->GPC['username'] = convert_urlencoded_unicode($vbulletin->GPC['username']);

	$userdata = datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
	$userdata->set('username', $vbulletin->GPC['username']);
	if (!empty($userdata->errors))
	{
		$status = "invalid";
		$message = "";
		$image = $stylevar['imgdir_misc'] . "/cross.png";
		foreach ($userdata->errors AS $index => $error)
		{
			$message .= "$error";
		}
	}
	else
	{
		$status = "valid";
		$image = $stylevar['imgdir_misc'] . "/tick.png";
		$message = $vbphrase['username_is_valid'];
	}

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_group('response');
		$xml->add_tag('status', $status);
		$xml->add_tag('image', $image);
		$xml->add_tag('message', $message);
	$xml->close_group();
	$xml->print_xml();
}

($hook = vBulletinHook::fetch_hook('ajax_complete')) ? eval($hook) : false;

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
