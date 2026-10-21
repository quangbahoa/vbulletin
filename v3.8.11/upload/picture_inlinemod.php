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
if ($_REQUEST['do'] == 'inlinemerge' OR $_POST['do'] == 'doinlinemerge')
{
	define('GET_EDIT_TEMPLATES', true);
}
define('THIS_SCRIPT', 'picture_inlinemod');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('threadmanage', 'posting', 'inlinemod');

// get special data templates from the datastore
$specialtemplates = array();

$globaltemplates = array(
	'threadadmin_authenticate'
);

$actiontemplates = array(
	'inlinedelete'  => array('picturecomment_deletemessages'),
	'picturedelete' => array('moderation_deletepictures'),
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_picturecomment.php');
require_once(DIR . '/includes/modfunctions.php');
require_once(DIR . '/includes/functions_log_error.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0)
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}
@set_time_limit(0);

$itemlimit = 200;

// This is a list of ids that were checked on the page we submitted from
$vbulletin->input->clean_array_gpc('p', array(
	'picturecommentlist' => TYPE_ARRAY_KEYS_INT,
	'picturelist'        => TYPE_ARRAY_KEYS_INT,
	'pictureid'          => TYPE_UINT,
));

$vbulletin->input->clean_array_gpc('c', array(
	'vbulletin_inlinepicturecomment' => TYPE_STR,
));

if (!empty($vbulletin->GPC['vbulletin_inlinepicturecomment']))
{
	$commentlist = explode('-', $vbulletin->GPC['vbulletin_inlinepicturecomment']);
	$commentlist = $vbulletin->input->clean($commentlist, TYPE_ARRAY_UINT);

	$vbulletin->GPC['picturecommentlist'] = array_unique(array_merge($commentlist, $vbulletin->GPC['picturecommentlist']));
}

if (!$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

switch ($_POST['do'])
{
	case 'doinlinedelete':
	case 'dopicturedelete':
	{
		$inline_mod_authenticate = true;
		break;
	}
	default:
	{
		$inline_mod_authenticate = false;
		($hook = vBulletinHook::fetch_hook('picturecomment_inlinemod_authenticate_switch')) ? eval($hook) : false;
	}
}

if ($inline_mod_authenticate AND !inlinemod_authenticated())
{
	show_inline_mod_login();
}

switch ($_POST['do'])
{
	case 'inlinedelete':
	case 'inlineapprove':
	case 'inlineunapprove':
	case 'inlineundelete':

		if (!$vbulletin->options['pc_enabled'])
		{
			print_no_permission();
		}

		if (empty($vbulletin->GPC['picturecommentlist']))
		{
			standard_error(fetch_error('you_did_not_select_any_valid_messages'));
		}

		if (count($vbulletin->GPC['picturecommentlist']) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_messages', $itemlimit));
		}

		$messageids = implode(', ', $vbulletin->GPC['picturecommentlist']);
		break;

	case 'doinlinedelete':

		if (!$vbulletin->options['pc_enabled'])
		{
			print_no_permission();
		}

		$vbulletin->input->clean_array_gpc('p', array(
			'messageids' => TYPE_STR,
		));
		$messageids = explode(',', $vbulletin->GPC['messageids']);
		$messageids = $vbulletin->input->clean($messageids, TYPE_ARRAY_UINT);

		if (count($messageids) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_messages', $itemlimit));
		}
		break;

	case 'pictureapprove':
	case 'picturedelete':

		if (empty($vbulletin->GPC['picturelist']))
		{
			standard_error(fetch_error('you_did_not_select_any_valid_pictures'));
		}

		if (count($vbulletin->GPC['picturelist']) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_pictures', $itemlimit));
		}

		$pictureids = implode(', ', $vbulletin->GPC['picturelist']);
		break;

	case 'dopicturedelete':

		require_once(DIR . '/includes/functions_album.php');

		$vbulletin->input->clean_array_gpc('p', array(
			'pictureids' => TYPE_STR,
		));
		$pictureids = explode(',', $vbulletin->GPC['pictureids']);
		$pictureids = $vbulletin->input->clean($pictureids, TYPE_ARRAY_UINT);

		if (count($pictureids) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_pictures', $itemlimit));
		}
		break;
}

// set forceredirect for IIS
$forceredirect = (strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false);

$messagelist = $messagearray = $userlist = array();

($hook = vBulletinHook::fetch_hook('picturecomment_inlinemod_start')) ? eval($hook) : false;

if ($_POST['do'] == 'clearpicture')
{
	setcookie('vbulletin_inlinepicture', '', TIMENOW - 3600, '/');

	eval(print_standard_redirect('redirect_inline_messagelist_cleared', true, $forceredirect));
}

if ($_POST['do'] == 'clearmessage')
{
	setcookie('vbulletin_inlinepicturecomment', '', TIMENOW - 3600, '/');

	eval(print_standard_redirect('redirect_inline_messagelist_cleared', true, $forceredirect));
}

if ($_POST['do'] == 'inlineapprove' OR $_POST['do'] == 'inlineunapprove')
{
	$insertrecords = array();

	$approve = ($_POST['do'] == 'inlineapprove');

	// Validate Messages
	$messages = $db->query_read_slave("
		SELECT picturecomment.*, picture.userid AS picture_userid, picture.caption AS picture_caption
		FROM " . TABLE_PREFIX . "picturecomment AS picturecomment
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = picturecomment.pictureid)
		WHERE picturecomment.commentid IN ($messageids)
			AND picturecomment.state IN (" . ($approve ? "'moderation'" : "'visible', 'deleted'") . ")
	");
	while ($message = $db->fetch_array($messages))
	{
		$pictureinfo = array(
			'pictureid' => $message['pictureid'],
			'userid'    => $message['picture_userid']
		);

		if ($message['state'] == 'deleted' AND !can_moderate(0, 'candeletepicturecomments'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_messages'));
		}
		else if (!fetch_user_picture_message_perm('canmoderatemessages', $pictureinfo, $message))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_moderate_messages'));
		}

		$messagearray["$message[commentid]"] = $message;
		$userlist["$pictureinfo[userid]"] = true;

		if (!$approve)
		{
			$insertrecords[] = "($message[commentid], 'picturecomment', " . TIMENOW . ")";
		}
	}

	if (empty($messagearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_messages'));
	}

	// Set message state
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "picturecomment
		SET state = '" . ($approve ? 'visible' : 'moderation') . "'
		WHERE commentid IN (" . implode(',', array_keys($messagearray)) . ")
	");

	if ($approve)
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "moderation
			WHERE primaryid IN(" . implode(',', array_keys($messagearray)) . ")
				AND type = 'picturecomment'
		");
	}
	else	// Unapprove
	{
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "moderation
				(primaryid, type, dateline)
			VALUES
				" . implode(',', $insertrecords) . "
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "deletionlog
			WHERE type = 'picturecomment' AND
				primaryid IN(" . implode(',', array_keys($messagearray)) . ")
		");
	}

	foreach (array_keys($userlist) AS $userid)
	{
		build_picture_comment_counters($userid);
	}

	foreach ($messagearray AS $commentinfo)
	{
		log_moderator_action($commentinfo,
			($approve ? 'pc_by_x_on_y_approved' : 'pc_by_x_on_y_unapproved'),
			array($commentinfo['postusername'], fetch_trimmed_title($commentinfo['picture_caption'], 50))
		);
	}

	setcookie('vbulletin_inlinepicturecomment', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('picturecomment_inlinemod_approveunapprove')) ? eval($hook) : false;

	if ($approve)
	{
		eval(print_standard_redirect('redirect_inline_approvedmessages', true, $forceredirect));
	}
	else
	{
		eval(print_standard_redirect('redirect_inline_unapprovedmessages', true, $forceredirect));
	}
}

if ($_POST['do'] == 'pictureapprove')
{
	if (!can_moderate(0, 'canmoderatepictures'))
	{
		standard_error(fetch_error('you_do_not_have_permission_to_moderate_pictures'));
	}

	$albumarray = array();
	$picturearray = array();

	// Validate Pictures
	$pictures = $db->query_read_slave("
		SELECT picture.pictureid, picture.state, picture.caption,
			albumpicture.albumid,
			album.title AS album_title, album.coverpictureid,
			user.username
		FROM " . TABLE_PREFIX . "picture AS picture
		INNER JOIN " . TABLE_PREFIX . "albumpicture AS albumpicture ON (albumpicture.pictureid = picture.pictureid)
		INNER JOIN " . TABLE_PREFIX . "album AS album ON (album.albumid = albumpicture.albumid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = album.userid)
		WHERE picture.pictureid IN ($pictureids)
			AND picture.state = 'moderation'
	");
	while ($picture = $db->fetch_array($pictures))
	{
		$picturearray["$picture[pictureid]"] = $picture;
		if (!isset($albumarray["$picture[albumid]"]))
		{
			$albumarray["$picture[albumid]"] = array(
				'coverpictureid' => $picture['coverpictureid'],
				'pictureid' => $picture['pictureid']
			);
		}
	}

	if (empty($picturearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_pictures'));
	}

	// Set message state
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "picture
		SET state = 'visible'
		WHERE pictureid IN (" . implode(',', array_keys($picturearray)) . ")
	");

	foreach ($albumarray AS $albumid => $coverinfo)
	{
		$albuminfo = array('albumid' => $albumid);
		$albumdata = datamanager_init('Album', $vbulletin, ERRTYPE_SILENT);
		$albumdata->set_existing($albuminfo);

		if (!$coverinfo['coverpictureid'])
		{
			// no cover yet, so pick the first picture
			$albumdata->set('coverpictureid', $coverinfo['pictureid']);
		}

		$albumdata->rebuild_counts();
		$albumdata->save();
		unset($albumdata);
	}

	if (can_moderate(0, 'canmoderatepicturecomments'))
	{
		foreach ($picturearray AS $picture)
		{
			log_moderator_action($picture, 'picture_x_in_y_by_z_approved',
				array(fetch_trimmed_title($picture['caption'], 50), $picture['album_title'], $picture['username'])
			);
		}
	}

	setcookie('vbulletin_inlinepicture', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('picture_inlinemod_approve')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_approvedpictures', true, $forceredirect));
}

if ($_POST['do'] == 'picturedelete')
{
	$picturearray = $albumarray = array();

	$pictures = $db->query_read_slave("
		SELECT picture.pictureid, albumpicture.albumid, picture.state
		FROM " . TABLE_PREFIX . "picture AS picture
		LEFT JOIN " . TABLE_PREFIX . "albumpicture AS albumpicture ON (albumpicture.pictureid = picture.pictureid)
		WHERE picture.pictureid IN ($pictureids)
	");
	while ($picture = $db->fetch_array($pictures))
	{
		if ($picture['state'] == 'moderation' AND !can_moderate(0, 'canmoderatepictures'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_moderate_pictures'));
		}
		else if (!can_moderate(0, 'candeletealbumpicture'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_delete_pictures'));
		}

		$picturearray["$picture[pictureid]"] = $picture;
		$albumarray["$picture[albumid]"] = true;
	}

	if (empty($picturearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_pictures'));
	}

	$albumcount = count($albumarray);
	$picturecount = count($picturearray);

	$url =& $vbulletin->url;

	$navbits = array('' => $vbphrase['delete_pictures']);
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('picture_inlinemod_delete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('moderation_deletepictures') . '");');

}

if ($_POST['do'] == 'dopicturedelete')
{
	$picturearray = $albumarray = array();

	$pictures = $db->query_read_slave("
		SELECT picture.pictureid, picture.state, picture.caption,
			albumpicture.albumid, album.title AS album_title,
			user.username
		FROM " . TABLE_PREFIX . "picture AS picture
		LEFT JOIN " . TABLE_PREFIX . "albumpicture AS albumpicture ON (albumpicture.pictureid = picture.pictureid)
		LEFT JOIN " . TABLE_PREFIX . "album AS album ON (album.albumid = albumpicture.albumid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = album.userid)
		WHERE picture.pictureid IN (" . implode(',', $pictureids) . ")
	");
	while ($picture = $db->fetch_array($pictures))
	{
		if ($picture['state'] == 'moderation' AND !can_moderate(0, 'canmoderatepictures'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_moderate_pictures'));
		}
		else if (!can_moderate(0, 'candeletealbumpicture'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_delete_pictures'));
		}

		$picturedata = datamanager_init(fetch_picture_dm_name(), $vbulletin, ERRTYPE_SILENT, 'picture');
		$picturedata->set_existing($picture);
		$picturedata->delete();
		unset($picturedata);

		log_moderator_action($picture, 'picture_x_in_y_by_z_deleted',
			array(fetch_trimmed_title($picture['caption'], 50), $picture['album_title'], $picture['username'])
		);
	}

	// empty cookie
	setcookie('vbulletin_inlinepicture', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('picture_inlinemod_dodelete')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_deletedpictures', true, $forceredirect));

}

if ($_POST['do'] == 'inlinedelete')
{
	$show['removemessages'] = false;
	$show['deletemessages'] = false;
	$show['deleteoption'] = false;
	$checked = array('delete' => 'checked="checked"');
	$picturelist = array();

	// Validate Messages
	$messages = $db->query_read_slave("
		SELECT picturecomment.*, picture.userid AS picture_userid
		FROM " . TABLE_PREFIX . "picturecomment AS picturecomment
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = picturecomment.pictureid)
		WHERE picturecomment.commentid IN ($messageids)
	");
	while ($message = $db->fetch_array($messages))
	{
		$pictureinfo = array(
			'pictureid' => $message['pictureid'],
			'userid' => $message['picture_userid']
		);

		$canmoderatemessages = fetch_user_picture_message_perm('canmoderatemessages', $pictureinfo, $message);
		$candeletemessages = fetch_user_picture_message_perm('candeletemessages', $pictureinfo, $message);
		$canremovemessages = can_moderate(0, 'canremovepicturecomments');

		if ($message['state'] == 'moderation' AND !$canmoderatemessages)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_messages'));
		}
		else if ($message['state'] == 'deleted' AND !$candeletemessages)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_messages'));
		}
		else
		{
			$show['deletemessages'] = $candeletemessages;
			if ($canremovemessages)
			{
				$show['removemessages'] = true;
				if (!$candeletemessages)
				{
					$checked = array('remove' => 'checked="checked"');
				}
			}

			if (!$candeletemessages AND !$canremovemessages)
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_messages'));
			}
			else if ($candeletemessages AND $canremovemessages)
			{
				$show['deleteoption'] = true;
			}
		}

		$messagearray["$message[commentid]"] = $message;
		$picturelist["$message[pictureid]"] = true;
		$userlist["$pictureinfo[userid]"] = true;
	}

	if (empty($messagearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_messages'));
	}

	$messagecount = count($messagearray);
	$picturecount = count($picturelist);

	$url =& $vbulletin->url;

	$navbits = array('' => $vbphrase['delete_messages']);
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('picturecomment_inlinemod_delete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('picturecomment_deletemessages') . '");');

}

if ($_POST['do'] == 'doinlinedelete')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'deletetype'   => TYPE_UINT, // 1 - Soft Deletion, 2 - Physically Remove
		'deletereason' => TYPE_NOHTMLCOND,
	));

	$physicaldel = ($vbulletin->GPC['deletetype'] == 2) ? true : false;

	// Validate Messages
	$messages = $db->query_read_slave("
		SELECT picturecomment.*, picture.userid AS picture_userid, picture.caption AS picture_caption
		FROM " . TABLE_PREFIX . "picturecomment AS picturecomment
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = picturecomment.pictureid)
		WHERE picturecomment.commentid IN (" . implode(',', $messageids) . ")
	");
	while ($message = $db->fetch_array($messages))
	{
		$pictureinfo = array(
			'pictureid' => $message['pictureid'],
			'userid' => $message['picture_userid']
		);

		$canmoderatemessages = fetch_user_picture_message_perm('canmoderatemessages', $pictureinfo, $message);
		$candeletemessages = fetch_user_picture_message_perm('candeletemessages', $pictureinfo, $message);
		$canremovemessages = can_moderate(0, 'canremovepicturecomments');

		if ($message['state'] == 'moderation' AND !$canmoderatemessages)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_messages'));
		}
		else if ($message['state'] == 'deleted' AND !$candeletemessages)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_messages'));
		}
		else
		{
			if (($physicaldel AND !$canremovemessages) OR (!$physicaldel AND !$candeletemessages))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_messages'));
			}
		}

		$messagearray["$message[commentid]"] = $message;
		$userlist["$pictureinfo[userid]"] = true;
	}

	if (empty($messagearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_messages'));
	}

	foreach($messagearray AS $commentid => $message)
	{
		$dataman = datamanager_init('PictureComment', $vbulletin, ERRTYPE_SILENT);
		$dataman->set_existing($message);
		$dataman->set_info('hard_delete', $physicaldel);
		$dataman->set_info('reason', $vbulletin->GPC['deletereason']);
		$dataman->delete();
		unset($dataman);

		if (can_moderate(0, 'candeletepicturecomments'))
		{
			log_moderator_action($message,
				($physicaldel ? 'pc_by_x_on_y_removed' : 'pc_by_x_on_y_soft_deleted'),
				array($message['postusername'], fetch_trimmed_title($message['picture_caption'], 50))
			);
		}
	}

	foreach(array_keys($userlist) AS $userid)
	{
		build_picture_comment_counters($userid);
	}

	// empty cookie
	setcookie('vbulletin_inlinepicturecomment', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('picturecomment_inlinemod_dodelete')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_deletedmessages', true, $forceredirect));
}

if ($_POST['do'] == 'inlineundelete')
{
	// Validate Messages
	$messages = $db->query_read_slave("
		SELECT picturecomment.*, picture.userid AS picture_userid, picture.caption AS picture_caption
		FROM " . TABLE_PREFIX . "picturecomment AS picturecomment
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = picturecomment.pictureid)
		WHERE picturecomment.commentid IN ($messageids)
			AND picturecomment.state = 'deleted'
	");
	while ($message = $db->fetch_array($messages))
	{
		$pictureinfo = array(
			'pictureid' => $message['pictureid'],
			'userid' => $message['picture_userid']
		);
		if (!can_moderate(0, 'candeletepicturecomments'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_messages'));
		}

		$messagearray["$message[commentid]"] = $message;
		$userlist["$pictureinfo[userid]"] = true;
	}

	if (empty($messagearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_messages'));
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "deletionlog
		WHERE type = 'picturecomment' AND
			primaryid IN(" . implode(',', array_keys($messagearray)) . ")
	");
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "picturecomment
		SET state = 'visible'
		WHERE commentid IN(" . implode(',', array_keys($messagearray)) . ")
	");

	foreach(array_keys($userlist) AS $userid)
	{
		build_picture_comment_counters($userid);
	}

	if (can_moderate(0, 'candeletepicturecomments'))
	{
		foreach ($messagearray AS $message)
		{
			log_moderator_action($message, 'pc_by_x_on_y_undeleted',
				array($message['postusername'], fetch_trimmed_title($message['picture_caption'], 50))
			);
		}
	}

	// empty cookie
	setcookie('vbulletin_inlinepicturecomment', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('picturecomment_inlinemod_undelete')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_undeletedmessages', true, $forceredirect));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
