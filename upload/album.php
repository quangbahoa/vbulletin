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
define('THIS_SCRIPT', 'album');
define('CSRF_PROTECTION', true);
define('GET_EDIT_TEMPLATES', 'picture');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('album', 'user', 'posting');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'memberinfo_usercss'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'addalbum' => array(
		'album_edit'
	),
	'addpictures' => array(
		'album_picture_upload',
		'album_picture_uploadbit',
	),
	'editpictures' => array(
		'album_picture_edit',
		'album_picture_editbit',
	),
	'addgroup' => array(
		'album_addgroup',
		'album_addgroup_groupbit'
	),
	'report' => array(
		'newpost_usernamecode',
		'reportitem',
	),
	'picture' => array(
		'album_pictureview',
		'picturecomment_commentarea',
		'picturecomment_css',
		'picturecomment_form',
		'picturecomment_message',
		'picturecomment_message_deleted',
		'picturecomment_message_ignored',
		'picturecomment_message_global_ignored',
		'showthread_quickreply'
	),
	'album' => array(
		'album_picturelist',
		'album_picturebit',
		'album_picturebit_checkbox'
	),
	'user' => array(
		'album_list',
		'albumbit'
	),
	'moderated' => array(
		'album_moderatedcomments',
		'picturecomment_css',
		'picturecomment_message_moderatedview'
	),
	'unread' => array(
		'album_picturebit_unread',
		'album_unreadcomments'
	),
	'latest' => array(
		'album_latestbit',
		'album_list'
	),
	'overview' => array(
		'album_latestbit',
		'album_list',
		'albumbit'
	)
);
$actiontemplates['updatealbum'] = $actiontemplates['editalbum'] = $actiontemplates['addalbum'];

if (empty($_REQUEST['do']))
{
	if (!empty($_REQUEST['albumid']))
	{
		if (!empty($_REQUEST['pictureid']))
		{
			$_REQUEST['do'] = 'picture';
		}
		else
		{
			$_REQUEST['do'] = 'album';
		}
	}
	else if ($_REQUEST['u'] OR $_REQUEST['userid'])
	{
		$_REQUEST['do'] = 'user';
	}
	else if($_REQUEST['do'] != 'latest')
	{
		$_REQUEST['do'] = 'overview';
	}
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_album.php');
require_once(DIR . '/includes/functions_user.php');

if (!
	(
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']
		AND
		$permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
		AND
		$permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']
	)
)
{
	print_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'albumid'   => TYPE_UINT,
	'pictureid' => TYPE_UINT,
	'userid'    => TYPE_UINT,
));

$moderatedpictures = (
	(
		$vbulletin->options['albums_pictures_moderation']
			OR
		!($vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['picturefollowforummoderation'])
	)
		AND
	!can_moderate(0, 'canmoderatepictures')
);

($hook = vBulletinHook::fetch_hook('album_start_precheck')) ? eval($hook) : false;

// if we specify an album, make sure our user context is sane
if ($vbulletin->GPC['albumid'])
{
	$albuminfo = fetch_albuminfo($vbulletin->GPC['albumid']);
	if (!$albuminfo)
	{
		standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
	}

	$vbulletin->GPC['userid'] = $albuminfo['userid'];
}

if ($vbulletin->GPC['pictureid'])
{
	$pictureinfo = fetch_pictureinfo($vbulletin->GPC['pictureid'], $albuminfo['albumid']);
	if (!$pictureinfo)
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}
}

if ($_REQUEST['do'] == 'overview')
{
	if ((!$vbulletin->GPC['userid'] AND !$vbulletin->userinfo['userid']) OR !($vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']))
	{
		$_REQUEST['do'] = 'latest';
	}
}

// don't need userinfo if we're only viewing latest
if ($_REQUEST['do'] != 'latest')
{
	if (!$vbulletin->GPC['userid'])
	{
		if (!($vbulletin->GPC['userid'] = $vbulletin->userinfo['userid']))
		{
			print_no_permission();
		}
	}

	$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1, FETCH_USERINFO_USERCSS);

	// don't show stuff for users awaiting moderation
	if ($userinfo['usergroupid'] == 4 AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		print_no_permission();
	}

	cache_permissions($userinfo, false);
	if (!can_moderate(0, 'caneditalbumpicture') AND !($userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']))
	{
		print_no_permission();
	}

	if (!can_view_profile_section($userinfo['userid'], 'albums'))
	{
		// private album that we can not see
		standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
	}

	// determine if we can see this user's private albums and run the correct permission checks
	if (!empty($albuminfo))
	{
		if ($albuminfo['state'] == 'private' AND !can_view_private_albums($userinfo['userid']))
		{
			// private album that we can not see
			standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
		}
		else if ($albuminfo['state'] == 'profile' AND !can_view_profile_albums($userinfo['userid']))
		{
			// profile album that we can not see
			standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
		}
	}

	$usercss = construct_usercss($userinfo, $show['usercss_switch']);
	$show['usercss_switch'] = ($show['usercss_switch'] AND $vbulletin->userinfo['userid'] != $userinfo['userid']);
	construct_usercss_switch($show['usercss_switch'], $usercss_switch_phrase);
}

($hook = vBulletinHook::fetch_hook('album_start_postcheck')) ? eval($hook) : false;

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if ($_POST['do'] == 'killalbum')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'delete' => TYPE_BOOL
	));

	if ($vbulletin->userinfo['userid'] != $albuminfo['userid'] AND !can_moderate(0, 'candeletealbumpicture'))
	{
		print_no_permission();
	}

	if (!$vbulletin->GPC['delete'])
	{
		standard_error(fetch_error('no_checkbox_item_not_deleted'));
	}

	$albumdata = datamanager_init('Album', $vbulletin, ERRTYPE_STANDARD);
	$albumdata->set_existing($albuminfo);
	$albumdata->delete();

	if ($albuminfo['userid'] != $vbulletin->userinfo['userid'] AND can_moderate(0, 'caneditalbumpicture'))
	{
		require_once(DIR . '/includes/functions_log_error.php');
		log_moderator_action($albuminfo, 'album_x_by_y_deleted',
			array($albuminfo['title'], $userinfo['username'])
		);
	}
	unset($albumdata);

	$vbulletin->url = 'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$albuminfo[userid]";
	eval(print_standard_redirect('album_deleted'));
}

// #######################################################################
if ($_POST['do'] == 'updatealbum' OR $_REQUEST['do'] == 'addalbum' OR $_REQUEST['do'] == 'editalbum')
{
	if (empty($albuminfo['albumid']))
	{
		// adding new, can only add in your own
		if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
		{
			print_no_permission();
		}
	}
	else
	{
		// editing: only in your own or moderators
		if ($userinfo['userid'] != $vbulletin->userinfo['userid'] AND !can_moderate(0, 'caneditalbumpicture'))
		{
			print_no_permission();
		}
	}
}

// #######################################################################
if ($_POST['do'] == 'updatealbum')
{
	$vbulletin->input->clean_array_gpc('p', array(
		// albumid cleaned at the beginning
		'title'       => TYPE_NOHTML,
		'description' => TYPE_NOHTML,
		'albumtype'   => TYPE_STR
	));

	$albumdata = datamanager_init('Album', $vbulletin, ERRTYPE_ARRAY);
	if (!empty($albuminfo['albumid']))
	{
		$albumdata->set_existing($albuminfo);
		$albumdata->rebuild_counts();
	}
	else
	{
		$albumdata->set('userid', $vbulletin->userinfo['userid']);
	}

	$albumdata->set('title', $vbulletin->GPC['title']);
	$albumdata->set('description', $vbulletin->GPC['description']);

	// if changing an album to a profile album, be sure we actually have perm to change it
	if ($vbulletin->GPC['albumtype'] == 'profile' AND $albuminfo['state'] != 'profile')
	{
		$creator = fetch_userinfo($albumdata->fetch_field('userid'));
		cache_permissions($creator);

		$can_profile_album = (
			$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_profile_styling']
			AND $creator['permissions']['usercsspermissions'] & $vbulletin->bf_ugp_usercsspermissions['caneditbgimage']
		);

		if (!$can_profile_album)
		{
			$vbulletin->GPC['albumtype'] = 'public';
		}
	}
	$albumdata->set('state', $vbulletin->GPC['albumtype']);

	$albumdata->pre_save();

	($hook = vBulletinHook::fetch_hook('album_album_update')) ? eval($hook) : false;

	if ($albumdata->errors)
	{
		$formdata = array_merge($albumdata->existing, $albumdata->album);

		require_once(DIR . '/includes/functions_newpost.php');
		$errortable = construct_errors($albumdata->errors);

		$_REQUEST['do'] = 'addalbum';
		define('PREVIEW_ERRORS', true);
	}
	else
	{
		$albumdata->save();

		if (!empty($albuminfo['albumid']) AND $albuminfo['userid'] != $vbulletin->userinfo['userid'] AND can_moderate(0, 'caneditalbumpicture'))
		{
			require_once(DIR . '/includes/functions_log_error.php');
			log_moderator_action($albuminfo, 'album_x_by_y_edited',
				array($albuminfo['title'], $userinfo['username'])
			);
		}

		$vbulletin->url = 'album.php?' . $vbulletin->session->vars['sessionurl'] . 'albumid=' . $albumdata->fetch_field('albumid');
		eval(print_standard_redirect('album_added_edited'));
	}

	unset($albumdata);
}

// #######################################################################
if ($_REQUEST['do'] == 'addalbum' OR $_REQUEST['do'] == 'editalbum')
{
	// $formdata will fall through on preview
	if (empty($formdata))
	{
		if (!empty($albuminfo))
		{
			$formdata = $albuminfo;
		}
		else
		{
			$formdata = array(
				'albumid'     => 0,
				'title'       => '',
				'description' => '',
				'state'       => 'public',
				'userid'      => $vbulletin->userinfo['userid']
			);
		}
	}

	$formdata['albumtype_' . $formdata['state']] = 'checked="checked"';

	$show['delete_option'] = (!defined('PREVIEW_ERRORS') AND !empty($albuminfo['albumid']) AND
		($vbulletin->userinfo['userid'] == $albuminfo['userid'] OR can_moderate(0, 'candeletealbumpicture'))
	);

	$show['album_used_in_css'] = false;

	if (!empty($albuminfo['albumid']))
	{
		if ($db->query_first("
			SELECT selector
			FROM " . TABLE_PREFIX . "usercss
			WHERE userid = $albuminfo[userid]
				AND property = 'background_image'
				AND value LIKE '$albuminfo[albumid],%'
			LIMIT 1
		"))
		{
			$show['album_used_in_css'] = true;
		}
	}

	// if permitted to customize profile, or album is already a profile-type, show the profile-type option
	$creator = fetch_userinfo($formdata['userid']);
	cache_permissions($creator);

	$show['albumtype_profile'] = (
		$albuminfo['state'] == 'profile'
		OR (
			$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_profile_styling']
			AND $creator['permissions']['usercsspermissions'] & $vbulletin->bf_ugp_usercsspermissions['caneditbgimage']
		)
	);

	($hook = vBulletinHook::fetch_hook('album_album_edit')) ? eval($hook) : false;

	// navbar and final output
	$navbits = construct_navbits(array(
		'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => $vbphrase['albums'],
		'' => (!empty($albuminfo['albumid']) ? $vbphrase['edit_album'] : $vbphrase['add_album'])
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('album_edit') . '");');
}

// #######################################################################
if ($_POST['do'] == 'updatepictures')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'pictures'       => TYPE_ARRAY_ARRAY,
		'coverpictureid' => TYPE_UINT,
		'frompicture'    => TYPE_BOOL
	));

	if (empty($albuminfo))
	{
		standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
	}

	if ($userinfo['userid'] != $vbulletin->userinfo['userid'] AND !can_moderate(0, 'caneditalbumpicture'))
	{
		print_no_permission();
	}

	$can_delete = ($vbulletin->userinfo['userid'] == $albuminfo['userid'] OR can_moderate(0, 'candeletealbumpicture'));

	$pictureids = array_map('intval', array_keys($vbulletin->GPC['pictures']));

	if (!$pictureids)
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	$new_coverid = 0;
	$cover_moved = false;
	$destinations = array();
	$need_css_rebuild = false;
	$updatecounter = 0;
	$deleted_picture = false;
	$delete_usercss = array();
	$update_usercss = array();

	// Fetch possible destination albums
	$destination_result = $db->query_read("
		SELECT albumid, userid, title, coverpictureid, state
		FROM " . TABLE_PREFIX . "album
		WHERE userid = $userinfo[userid]
	");

	$destinations = array();

	if ($db->num_rows($destination_result))
	{
		while ($album = $db->fetch_array($destination_result))
		{
			$destinations[$album['albumid']] = $album;
		}
	}
	$db->free_result($destination_result);

	$picture_sql = $db->query_read("
		SELECT picture.pictureid, picture.userid, picture.caption, picture.extension, picture.filesize, picture.state,
			picture.idhash, picture.thumbnail_filesize, albumpicture.dateline
		FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = albumpicture.pictureid)
		WHERE albumpicture.albumid = $albuminfo[albumid]
			AND albumpicture.pictureid IN (" . implode(',', $pictureids) . ")
	");
	while ($picture = $db->fetch_array($picture_sql))
	{
		$picturedata = datamanager_init(fetch_picture_dm_name(), $vbulletin, ERRTYPE_SILENT, 'picture');
		$picturedata->set_existing($picture);

		($hook = vBulletinHook::fetch_hook('album_picture_update')) ? eval($hook) : false;

		if ($vbulletin->GPC['pictures']["$picture[pictureid]"]['delete'])
		{
			// if we can't delete, then we're not going to do the update either
			if ($can_delete)
			{
				$picturedata->delete();
				if ($picturedata->info['have_updated_usercss'])
				{
					$need_css_rebuild = true;
				}

				$deleted_picture = true;

				if ($albuminfo['userid'] != $vbulletin->userinfo['userid']
					AND can_moderate(0, 'caneditalbumpicture'))
				{
					require_once(DIR . '/includes/functions_log_error.php');
					log_moderator_action($picture, 'picture_x_in_y_by_z_deleted',
						array(fetch_trimmed_title($picture['caption'], 50), $albuminfo['title'], $userinfo['username'])
					);
				}
			}
		}
		else
		{
			if ($picture['state'] == 'moderation' AND can_moderate(0, 'canmoderatepictures') AND $vbulletin->GPC['pictures']["$picture[pictureid]"]['approve'])
			{
				// need to increase picture counter
				$picturedata->set('state', 'visible');
				$updatecounter++;

				// album has been recently updated
				exec_album_updated($vbulletin->userinfo, $albuminfo);
			}

			// only album owner can move pictures
			if ($vbulletin->userinfo['userid'] == $albuminfo['userid'])
			{
				$picture_moved = false;

				$album = $vbulletin->GPC['pictures']["$picture[pictureid]"]['album'];
				if (isset($destinations[$album]) AND ($album != $albuminfo['albumid']))
				{
					$vbulletin->db->query_write("
						UPDATE " . TABLE_PREFIX . "albumpicture
						SET albumid = $album
						WHERE pictureid = $picture[pictureid]
						AND albumid = $albuminfo[albumid]
					");

					if ($db->affected_rows())
					{
						$updatecounter = true;

						if (('private' == $destinations[$album]['state']) AND ('private' != $albuminfo['state']))
						{
							$delete_usercss[] = "'$albuminfo[albumid],$picture[pictureid]'";
						}
						else
						{
							$update_usercss["'$albuminfo[albumid],$picture[pictureid]'"] = "'$album,$picture[pictureid]'";
						}

						$picture_moved = $album;
						$destinations[$album]['moved_pictures'][] = $picture[pictureid];
					}

					if ($picture['pictureid'] == $albuminfo['coverpictureid'] AND (!$new_coverid))
					{
						$cover_moved = true;
					}
				}
			}

			$picturedata->set('caption', $vbulletin->GPC['pictures']["$picture[pictureid]"]['caption']);
			$picturedata->save();

			if ($albuminfo['userid'] != $vbulletin->userinfo['userid']
				AND $vbulletin->GPC['pictures']["$picture[pictureid]"]['caption'] != $picture['caption']
				AND can_moderate(0, 'caneditalbumpicture'))
			{
				require_once(DIR . '/includes/functions_log_error.php');
				log_moderator_action($picture, 'picture_x_in_y_by_z_edited',
					array(fetch_trimmed_title($picture['caption'], 50), $albuminfo['title'], $userinfo['username'])
				);
			}

			if (!$picture_moved)
			{
				if ($picture['pictureid'] == $vbulletin->GPC['coverpictureid'] AND $picturedata->fetch_field('state') == 'visible')
				{
					$new_coverid = $picture['pictureid'];
					$cover_moved = false;
				}
				else if (!$vbulletin->GPC['coverpictureid'] AND !$new_coverid AND (!$albuminfo['coverpictureid'] OR $cover_moved)
					AND $picturedata->fetch_field('state') == 'visible'
				)
				{
					// not setting a cover and there's no existing cover -> set to this pic
					$new_coverid = $picture['pictureid'];
					$cover_moved = false;
				}
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('album_picture_update_complete')) ? eval($hook) : false;

	if (sizeof($delete_usercss))
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "usercss
			WHERE property = 'background_image'
				AND value IN (" . implode(',', $delete_usercss) . ")
				AND userid = $userinfo[userid]
		");

		$need_css_rebuild = ($need_css_rebuild OR $db->affected_rows());
	}

	if (sizeof($update_usercss))
	{
		foreach ($update_usercss AS $oldvalue => $newvalue)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "usercss
				SET value = $newvalue
				WHERE property = 'background_image'
				AND value = $oldvalue
				AND userid = $userinfo[userid]
			");

			$need_css_rebuild = ($need_css_rebuild OR $db->affected_rows());
		}
	}

	if ($cover_moved)
	{
		// try and find a new cover
		$new_coverid = $db->query_first("
			SELECT albumpicture.pictureid
			FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
			INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (albumpicture.pictureid = picture.pictureid)
			WHERE albumpicture.albumid = $albuminfo[albumid] AND picture.state = 'visible'
			ORDER BY albumpicture.dateline ASC
			LIMIT 1
		");

		$new_coverid = $new_coverid['pictureid'] ? $new_coverid['pictureid'] : 0;
	}

	// update all albums that pictures were moved to
	foreach ($destinations as $albumid => $album)
	{
		if (sizeof($album['moved_pictures']))
		{
			$albumdata = datamanager_init('Album', $vbulletin, ERRTYPE_SILENT);
			$albumdata->set_existing($album);

			if (!$album['coverpictureid'])
			{
				$albumdata->set('coverpictureid', array_shift($album['moved_pictures']));
			}

			$albumdata->rebuild_counts();
			$albumdata->save();
			unset($albumdata);
		}
	}

	if ($new_coverid OR $updatecounter)
	{
		$albumdata = datamanager_init('Album', $vbulletin, ERRTYPE_SILENT);
		$albumdata->set_existing($albuminfo);

		if ($new_coverid OR $cover_moved)
		{
			$albumdata->set('coverpictureid', $new_coverid);
		}
		if ($updatecounter)
		{
			$albumdata->rebuild_counts();
		}
		$albumdata->save();
		unset($albumdata);
	}

	if ($need_css_rebuild)
	{
		require_once(DIR . '/includes/class_usercss.php');
		$usercss = new vB_UserCSS($vbulletin, $albuminfo['userid'], false);
		$usercss->update_css_cache();
	}

	if ($vbulletin->GPC['frompicture'] AND sizeof($pictureids) == 1 AND !$deleted_picture)
	{
		$pictureid = reset($pictureids);
		$vbulletin->url = 'album.php?' . $vbulletin->session->vars['sessionurl'] . "albumid=" . ($picture_moved ? $picture_moved : $albuminfo[albumid]) . "&amp;pictureid=$pictureid";
	}
	else
	{
		$vbulletin->url = 'album.php?' . $vbulletin->session->vars['sessionurl'] . "albumid=$albuminfo[albumid]";
	}

	eval(print_standard_redirect('pictures_updated'));
}

// #######################################################################
if ($_REQUEST['do'] == 'editpictures')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber'  => TYPE_UINT,
		'pictureids'  => TYPE_ARRAY_UINT,
		'errors'      => TYPE_ARRAY_NOHTML,
		'frompicture' => TYPE_BOOL
	));

	if (empty($albuminfo))
	{
		standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
	}

	if ($userinfo['userid'] != $vbulletin->userinfo['userid'] AND !can_moderate(0, 'caneditalbumpicture'))
	{
		print_no_permission();
	}

	if ($vbulletin->GPC['pictureid'])
	{
		$vbulletin->GPC['pictureids'][] = $vbulletin->GPC['pictureid'];
	}

	$show['delete_option'] = ($vbulletin->userinfo['userid'] == $albuminfo['userid'] OR can_moderate(0, 'candeletealbumpicture'));

	$display = $db->query_first("
		SELECT COUNT(*) AS picturecount
		FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = albumpicture.pictureid)
		WHERE albumpicture.albumid = $albuminfo[albumid]
			" . ($vbulletin->GPC['pictureids'] ? "AND albumpicture.pictureid IN (" . implode(',', $vbulletin->GPC['pictureids']) . ")" : '') . "
			" . (!can_moderate(0, 'canmoderatepictures') ? "AND (picture.state = 'visible' OR picture.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
	");

	if (!$display['picturecount'])
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	// pagination setup
	if ($vbulletin->GPC['pagenumber'] < 1)
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}

	//$perpage = $vbulletin->options['album_pictures_perpage'];
	$perpage = 999999; // disable page nav
	$total_pages = max(ceil($display['picturecount'] / $perpage), 1); // 0 pictures still needs an empty page
	$pagenumber = ($vbulletin->GPC['pagenumber'] > $total_pages ? $total_pages : $vbulletin->GPC['pagenumber']);
	$start = ($pagenumber - 1) * $perpage;

	$background_pictures = array();
	$background_picture_sql = $db->query_read("
		SELECT value
		FROM " . TABLE_PREFIX . "usercss
		WHERE userid = $albuminfo[userid]
			AND property = 'background_image'
			AND value LIKE '$albuminfo[albumid],%'
	");
	while ($background_picture = $db->fetch_array($background_picture_sql))
	{
		preg_match('#^(\d+),(\d+)$#', $background_picture['value'], $match);
		$match[2] = intval($match[2]);
		$background_pictures["$match[2]"] = $match[2];
	}

	if ($vbulletin->userinfo['userid'] == $albuminfo['userid'])
	{
		$album_options = '';
		$album_result = $db->query_read("
			SELECT albumid, title
			FROM " . TABLE_PREFIX . "album
			WHERE userid = $userinfo[userid]
		");

		if ($db->num_rows($album_result) > 1)
		{
			while ($album = $db->fetch_array($album_result))
			{
				$optiontitle = $album['title'];
				$optionvalue = $album['albumid'];
				$optionselected = ($album['albumid'] == $albuminfo['albumid']) ? 'selected="selected"' : '';

				eval('$album_options .= "' . fetch_template('option') . '";');
			}

			$show['move_to_album'] = true;
		}
		$db->free_result($album_result);
	}

	$picture_sql = $db->query_read("
		SELECT picture.pictureid, picture.userid, picture.caption, picture.extension, picture.filesize, picture.idhash, picture.state,
			picture.thumbnail_filesize, picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height,
			albumpicture.dateline
		FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = albumpicture.pictureid)
		WHERE albumpicture.albumid = $albuminfo[albumid]
			" . ($vbulletin->GPC['pictureids'] ? "AND albumpicture.pictureid IN (" . implode(',', $vbulletin->GPC['pictureids']) . ")" : '') . "
			" . (!can_moderate(0, 'canmoderatepictures') ? "AND (picture.state = 'visible' OR picture.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
		ORDER BY albumpicture.dateline DESC
		LIMIT $start, $perpage
	");

	$picturebits = '';
	$show['leave_cover'] = true;
	while ($picture = $db->fetch_array($picture_sql))
	{
		if ($picture['pictureid'] == $albuminfo['coverpictureid'])
		{
			$show['leave_cover'] = false;
			$cover_checked = ' checked="checked"';
		}
		else
		{
			$cover_checked = '';
		}

		$picture['usedincss'] = isset($background_pictures["$picture[pictureid]"]);

		$picture['caption_preview'] = fetch_censored_text(fetch_trimmed_title(
			$picture['caption'],
			$vbulletin->options['album_captionpreviewlen']
		));

		$picture['thumburl'] = ($picture['thumbnail_filesize'] ? fetch_picture_url($picture, $albuminfo, true) : '');
		$picture['dimensions'] = ($picture['thumbnail_width'] ? "width=\"$picture[thumbnail_width]\" height=\"$picture[thumbnail_height]\"" : '');

		($hook = vBulletinHook::fetch_hook('album_picture_editbit')) ? eval($hook) : false;

		$show['album_cover'] = ($picture['state'] == 'visible' OR can_moderate(0, 'canmoderatepictures'));
		$show['approve_option'] = ($picture['state'] == 'moderation' AND can_moderate(0, 'canmoderatepictures'));

		eval('$picturebits .= "' . fetch_template('album_picture_editbit') . '";');
	}

	$pagenav = construct_page_nav($pagenumber, $perpage, $display['picturecount'],
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "do=editpictures&amp;albumid=$albuminfo[albumid]",
		($vbulletin->GPC['pictureids'] ? "&amp;pictureids[]=" . implode('&amp;pictureids[]=', $vbulletin->GPC['pictureids']) : '')
	);

	$frompicture = $vbulletin->GPC['frompicture'];

	// error handling
	if ($vbulletin->GPC['errors'])
	{
		$error_file = '';
		foreach ($vbulletin->GPC['errors'] AS $error_name)
		{
			$error_files .= "<li>$error_name</li>";
		}

		$error_message = fetch_error('multiple_pictures_uploaded_errors_file_x', $error_files);
	}
	else
	{
		$error_message = '';
	}

	($hook = vBulletinHook::fetch_hook('album_picture_edit_complete')) ? eval($hook) : false;

	// navbar and final output
	$navbits = construct_navbits(array(
		'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => $vbphrase['albums'],
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "albumid=$albuminfo[albumid]" => $albuminfo['title_html'],
		'' => $vbphrase['edit_pictures']
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('album_picture_edit') . '");');
}

// #######################################################################
if ($_REQUEST['do'] == 'addpictures' OR $_POST['do'] == 'uploadpictures')
{
	if (empty($albuminfo))
	{
		standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
	}

	// adding new, can only add in your own
	if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	// lets make sure they aren't already over the limits
	if ($userinfo['permissions']['albummaxpics'])
	{
		// assume we are uploading 1 pic (at least)
		$totalpics_overage = fetch_count_overage($userinfo['userid'], $userinfo['permissions']['albummaxpics'], 0);
		if ($totalpics_overage >= 0)
		{
			standard_error(fetch_error('upload_total_album_pics_countfull', vb_number_format($totalpics_overage)));
		}
	}

	if ($vbulletin->options['album_maxpicsperalbum'])
	{
		$albumpics_overage = ($albuminfo['visible'] + $albuminfo['moderation'] - $vbulletin->options['album_maxpicsperalbum']);
		if ($albumpics_overage >= 0)
		{
			standard_error(fetch_error('upload_album_pics_countfull', vb_number_format($albumpics_overage)));
		}
	}

	if ($userinfo['permissions']['albummaxsize'])
	{
		// we don't know the size of the image yet, so ignore it and error if we have 0 bytes (or less) remaining
		$size_overage = fetch_size_overage($userinfo['userid'], $userinfo['permissions']['albummaxsize'], 0);
		if ($size_overage >= 0)
		{
			standard_error(fetch_error('upload_album_sizefull', vb_number_format($size_overage, 0, true)));
		}
	}

	// these values are negative (non-overage), so we need to flip them around for a "remaining" value
	$pics_remain = null;
	if (isset($totalpics_overage))
	{
		$pics_remain = -1 * $totalpics_overage;
	}

	if (isset($albumpics_overage))
	{
		$temp_remain = -1 * $albumpics_overage;
		if ($pics_remain === null OR $temp_remain < $pics_remain)
		{
			$pics_remain = $temp_remain;
		}
	}

	if ($pics_remain !== null)
	{
		$max_uploads = min($pics_remain, max(1, $vbulletin->options['album_uploadamount']));
	}
	else
	{
		$max_uploads = max(1, $vbulletin->options['album_uploadamount']);
	}
}

// #######################################################################
if ($_POST['do'] == 'uploadpictures')
{
	$vbulletin->input->clean_array_gpc('f', array(
		'pictures' => TYPE_ARRAY_FILE,
	));

	// PHP outputs an array of pictures like this: picture[name][0] = x, [name][1] = y...
	// this code attempts to turn it into picture[0][name] = x, [1][name] = y
	if (is_array($vbulletin->GPC['pictures']['name']))
	{
		// multiple pics
		$uploads = array();
		$keys = array_keys($vbulletin->GPC['pictures']);
		$uploadids = array_keys($vbulletin->GPC['pictures']['name']);

		foreach ($uploadids AS $uploadid)
		{
			$upload["$uploadid"] = array();
			foreach ($keys AS $key)
			{
				$uploads["$uploadid"]["$key"] = $vbulletin->GPC['pictures']["$key"]["$uploadid"];
			}
		}
	}
	else
	{
		$uploads = array($vbulletin->GPC['pictures']);
	}

	require_once(DIR . '/includes/class_upload.php');
	require_once(DIR . '/includes/class_image.php');

	$pictureids = array();

	$uploadcount = 0;

	($hook = vBulletinHook::fetch_hook('album_picture_upload_setup')) ? eval($hook) : false;

	foreach ($uploads AS $uploadid => $uploadinfo)
	{
		if ($uploadinfo['name'] == '')
		{
			continue;
		}

		$uploadcount++;
		if ($uploadcount > $max_uploads)
		{
			// form was edited to try to add more pics than allowed
			break;
		}

		$vbulletin->GPC['upload'] = $uploadinfo; // the class reads from this
		$upload = new vB_Upload_AlbumPicture($vbulletin);

		$upload->data = datamanager_init(fetch_picture_dm_name(), $vbulletin, ERRTYPE_STANDARD, 'picture');
		$upload->image = vB_Image::fetch_library($vbulletin);
		$upload->albums = array($albuminfo);

		$upload->maxwidth = $userinfo['permissions']['albumpicmaxwidth'];
		$upload->maxheight = $userinfo['permissions']['albumpicmaxheight'];
		$upload->maxuploadsize = $userinfo['permissions']['albumpicmaxsize'];

		($hook = vBulletinHook::fetch_hook('album_picture_upload_process')) ? eval($hook) : false;

		if (!$pictureid = $upload->process_upload())
		{
			$errors["$uploadid"] = $upload->fetch_error();
		}
		else
		{
			$pictureids["$uploadid"] = $pictureid;
		}
	}

	$error_names = array();

	if (!$pictureids AND !$errors)
	{
		// didn't select any pics to upload
		standard_error(fetch_error('no_select_pictures_upload'));
	}
	else if (!$pictureids AND $errors)
	{
		// no pics accepted, all errored
		if (sizeof($errors) == 1)
		{
			// only 1 file, show detailed
			standard_error(reset($errors));
		}
		else
		{
			// more than one file, show only names
			$error_files = '';
			foreach (array_keys($errors) AS $uploadid)
			{
				$error_files .= '<li>' . htmlspecialchars_uni($uploads["$uploadid"]['name']) . '</li>';
			}

			standard_error(fetch_error('multiple_pictures_uploaded_errors_file_x', $error_files));
		}
	}
	else if ($pictureids AND $errors)
	{
		// pics uploaded and errors, show only names
		foreach (array_keys($errors) AS $uploadid)
		{
			$error_names[] = urlencode($uploads["$uploadid"]['name']);
		}
	}
	// else only pics got through; no errors

	($hook = vBulletinHook::fetch_hook('album_picture_upload_complete')) ? eval($hook) : false;

	if (!$moderatedpictures AND $pictureids AND !$albuminfo['coverpictureid'])
	{
		// no cover -> set cover to the first pic uploaded
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "album SET
				coverpictureid = " . reset($pictureids) . "
			WHERE albumid = $albuminfo[albumid]
		");
	}

	// add to updated list
	if (can_moderate(0, 'canmoderatepictures')
		OR
		(!$vbulletin->options['albums_pictures_moderation']
		 AND
		 ($vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['picturefollowforummoderation']))
		)
	{
		exec_album_updated($vbulletin->userinfo, $albuminfo);
	}

	$vbulletin->url = 'album.php?' . $vbulletin->session->vars['sessionurl'] .
		"do=editpictures&amp;albumid=$albuminfo[albumid]" .
		"&amp;pictureids[]=" . implode('&amp;pictureids[]=', $pictureids) .
		($error_names ? "&amp;errors[]=" . implode('&amp;errors[]=', $error_names) : '');

	eval(print_standard_redirect('pictures_uploaded'));
}

// #######################################################################
if ($_REQUEST['do'] == 'addpictures')
{
	$uploadbits = '';
	for ($i = 0; $i < $max_uploads; $i++)
	{
		eval('$uploadbits .= "' . fetch_template('album_picture_uploadbit') . '";');
	}

	// let's show the information about remaining space if applicable (not an edit)
	$show['max_pic_limit'] = ($pics_remain !== null);
	$show['max_totalsize_limit'] = $userinfo['permissions']['albummaxsize'];
	$show['max_picsize_limit'] = $userinfo['permissions']['albumpicmaxsize'];
	$show['max_dim_limit'] = ($userinfo['permissions']['albumpicmaxwidth'] OR $userinfo['permissions']['albumpicmaxheight']);

	if ($show['max_pic_limit'] OR $show['max_totalsize_limit'] OR $show['max_picsize_limit'] OR $show['max_dim_limit'])
	{
		$show['limit_info'] = true;

		$limit_info = array(
			'pic_remain'       => vb_number_format($pics_remain),
			'totalsize_remain' => vb_number_format($size_overage * -1, 0, true),
			'width_limit'      => ($userinfo['permissions']['albumpicmaxwidth'] ? vb_number_format($userinfo['permissions']['albumpicmaxwidth']) : $vbphrase['unlimited']),
			'height_limit'     => ($userinfo['permissions']['albumpicmaxheight'] ? vb_number_format($userinfo['permissions']['albumpicmaxheight']) : $vbphrase['unlimited']),
			'picsize_limit'    => vb_number_format($userinfo['permissions']['albumpicmaxsize'], 0, true)
		);
	}

	$show['moderation'] = $moderatedpictures;

	($hook = vBulletinHook::fetch_hook('album_picture_add')) ? eval($hook) : false;

	// navbar and final output
	$navbits = construct_navbits(array(
		'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => $vbphrase['albums'],
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "albumid=$albuminfo[albumid]" => $albuminfo['title_html'],
		'' => $vbphrase['upload_pictures']
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('album_picture_upload') . '");');
}

// #######################################################################
if ($_POST['do'] == 'doaddgroupmult')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'groupid'    => TYPE_UINT,
		'pictureids' => TYPE_ARRAY_UINT,
		'cancel'     => TYPE_STR,
		'pagenumber' => TYPE_UINT
	));

	if ($vbulletin->GPC['cancel'])
	{
		exec_header_redirect(
			'album.php?' . $vbulletin->session->vars['sessionurl'] . 'albumid=' . $albuminfo['albumid'] .
			($vbulletin->GPC['pagenumber'] > 1 ? '&amp;page=' . $vbulletin->GPC['pagenumber'] : '')
		);
	}

	if (empty($albuminfo))
	{
		standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
	}

	if ($userinfo['userid'] != $vbulletin->userinfo['userid']
		OR !($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
		OR !($vbulletin->options['socnet_groups_albums_enabled'])
		OR !($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']))
	{
		print_no_permission();
	}

	if (!$vbulletin->GPC['groupid'])
	{
		standard_error(fetch_error('must_select_valid_group_add_pictures'));
	}

	if (empty($vbulletin->GPC['pictureids']))
	{
		standard_error(fetch_error('must_select_valid_pictures_add_group'));
	}

	require_once(DIR . '/includes/functions_socialgroup.php');
	$group = fetch_socialgroupinfo($vbulletin->GPC['groupid']);

	if (!$group OR $group['membertype'] != 'member' OR !($group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_albums']))
	{
		print_no_permission();
	}

	if ($vbulletin->GPC['pictureids'])
	{
		$picture_sql = $db->query_read("
			SELECT albumpicture.pictureid
			FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
			INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (albumpicture.pictureid = picture.pictureid)
			LEFT JOIN " . TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture ON
				(socialgrouppicture.pictureid = albumpicture.pictureid AND socialgrouppicture.groupid = $group[groupid])
			WHERE albumpicture.albumid = $albuminfo[albumid]
				AND albumpicture.pictureid IN (" . implode(',', $vbulletin->GPC['pictureids']) . ")
				AND picture.state = 'visible'
		");

		$pictures = array();
		while ($picture = $db->fetch_array($picture_sql))
		{
			$pictures[] = "($group[groupid], $picture[pictureid], " . TIMENOW . ")";
		}

		($hook = vBulletinHook::fetch_hook('album_picture_doaddgroups_multiple')) ? eval($hook) : false;

		if ($pictures)
		{
			$db->query_write("
				INSERT IGNORE " . TABLE_PREFIX . "socialgrouppicture
					(groupid, pictureid, dateline)
				VALUES
					" . implode(',', $pictures)
			);

			$groupdm = datamanager_init('SocialGroup', $vbulletin, ERRTYPE_STANDARD);
			$groupdm->set_existing($group);
			$groupdm->rebuild_picturecount();
			$groupdm->save();
		}
	}

	$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=grouppictures&amp;groupid=' . $group['groupid'];
	eval(print_standard_redirect('pictures_added'));
}

// #######################################################################
if ($_POST['do'] == 'doaddgroup')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'groupids'    => TYPE_ARRAY_UINT,
		'groupsshown' => TYPE_ARRAY_UINT
	));

	if (empty($pictureinfo) OR $pictureinfo['state'] == 'moderation')
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	if ($userinfo['userid'] != $vbulletin->userinfo['userid']
		OR !($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
		OR !($vbulletin->options['socnet_groups_albums_enabled'])
		OR !($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'])
		OR !($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups']))
	{
		print_no_permission();
	}

	if ($vbulletin->GPC['groupsshown'])
	{
		$delete = array();
		$insert = array();
		$changed_groups = array();

		$groups_sql = $db->query_read("
			SELECT socialgroup.*, IF(socialgrouppicture.pictureid IS NULL, 0, 1) AS picingroup
			FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
			INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
				(socialgroupmember.groupid = socialgroup.groupid AND socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN ". TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture ON
				(socialgrouppicture.groupid = socialgroup.groupid AND socialgrouppicture.pictureid = $pictureinfo[pictureid])
			WHERE socialgroup.groupid IN (" . implode(',', $vbulletin->GPC['groupsshown']) . ")
				AND socialgroupmember.type = 'member'
				AND socialgroup.options & " . $vbulletin->bf_misc_socialgroupoptions['enable_group_albums'] . "
		");

		while ($group = $db->fetch_array($groups_sql))
		{
			if (!empty($vbulletin->GPC['groupids']["$group[groupid]"]) AND !$group['picingroup'])
			{
				$insert[] = "($group[groupid], $pictureinfo[pictureid], " . TIMENOW . ")";
				$changed_groups["$group[groupid]"] = $group;
			}
			else if (empty($vbulletin->GPC['groupids']["$group[groupid]"]) AND $group['picingroup'])
			{
				$delete[] = $group['groupid'];
				$changed_groups["$group[groupid]"] = $group;
			}
		}

		($hook = vBulletinHook::fetch_hook('album_picture_doaddgroups')) ? eval($hook) : false;

		if ($insert)
		{
			$db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "socialgrouppicture
					(groupid, pictureid, dateline)
				VALUES
					" . implode(',', $insert)
			);
		}

		if ($delete)
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "socialgrouppicture
				WHERE pictureid = $pictureinfo[pictureid]
					AND groupid IN (" . implode(',', $delete) . ")
			");
		}

		foreach ($changed_groups AS $group)
		{
			$groupdm = datamanager_init('SocialGroup', $vbulletin, ERRTYPE_STANDARD);
			$groupdm->set_existing($group);
			$groupdm->rebuild_picturecount();
			$groupdm->save();
		}
	}

	$vbulletin->url = 'album.php?' . $vbulletin->session->vars['sessionurl'] . "albumid=$albuminfo[albumid]&amp;pictureid=$pictureinfo[pictureid]";
	eval(print_standard_redirect('groups_picture_changed'));
}

// #######################################################################
if ($_REQUEST['do'] == 'addgroup')
{
	if (empty($pictureinfo) OR $pictureinfo['state'] == 'moderation')
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	if ($userinfo['userid'] != $vbulletin->userinfo['userid']
		OR !($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
		OR !($vbulletin->options['socnet_groups_albums_enabled'])
		OR !($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'])
		OR !($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups']))
	{
		print_no_permission();
	}

	$groups_sql = $db->query_read_slave("
		SELECT socialgroup.*, IF(socialgrouppicture.pictureid IS NULL, 0, 1) AS picingroup
		FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
		INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
			(socialgroupmember.groupid = socialgroup.groupid AND socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . ")
		LEFT JOIN ". TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture ON
			(socialgrouppicture.groupid = socialgroup.groupid AND socialgrouppicture.pictureid = $pictureinfo[pictureid])
		WHERE socialgroupmember.type = 'member'
			AND socialgroup.options & " . $vbulletin->bf_misc_socialgroupoptions['enable_group_albums'] . "
		ORDER BY socialgroup.name
	");
	if ($db->num_rows($groups_sql) == 0)
	{
		standard_error(fetch_error('not_member_groups_find_some', $vbulletin->session->vars['sessionurl_q']));
	}

	require_once(DIR . '/includes/functions_socialgroup.php');

	$groupbits = '';
	while ($group = $db->fetch_array($groups_sql))
	{
		$group = prepare_socialgroup($group);
		$group_checked = ($group['picingroup'] ? ' checked="checked"' : '');

		eval('$groupbits .= "' . fetch_template('album_addgroup_groupbit') . '";');
	}

	$pictureinfo = prepare_pictureinfo_thumb($pictureinfo, $albuminfo);

	($hook = vBulletinHook::fetch_hook('album_picture_addgroups')) ? eval($hook) : false;

	// navbar and final output
	$navbits = construct_navbits(array(
		'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => $vbphrase['albums'],
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "albumid=$albuminfo[albumid]" => $albuminfo['title_html'],
		'' => $vbphrase['add_picture_to_groups']
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('album_addgroup') . '");');
}

// #######################################################################
if ($_REQUEST['do'] == 'report' OR $_POST['do'] == 'sendemail')
{
	require_once(DIR . '/includes/class_reportitem.php');

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$reportthread = ($rpforumid = $vbulletin->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
	$reportemail = ($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']);

	if (!$reportthread AND !$reportemail)
	{
		eval(standard_error(fetch_error('emaildisabled')));
	}

	$reportobj = new vB_ReportItem_AlbumPicture($vbulletin);
	$reportobj->set_extrainfo('album', $albuminfo);
	$reportobj->set_extrainfo('user', $userinfo);
	$reportobj->set_extrainfo('picture', $pictureinfo);
	$perform_floodcheck = $reportobj->need_floodcheck();

	if ($perform_floodcheck)
	{
		$reportobj->perform_floodcheck_precommit();
	}

	if (empty($pictureinfo))
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	if ($pictureinfo['state'] == 'moderation' AND !can_moderate(0, 'canmoderatepictures') AND $vbulletin->userinfo['userid'] != $pictureinfo['userid'])
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	($hook = vBulletinHook::fetch_hook('report_start')) ? eval($hook) : false;

	if ($_REQUEST['do'] == 'report')
	{
		// draw nav bar
		$navbits = construct_navbits(array(
			'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
			'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => $vbphrase['albums'],
			'album.php?' . $vbulletin->session->vars['sessionurl'] . "albumid=$albuminfo[albumid]" => $albuminfo['title_html'],
			'' => $vbphrase['report_picture']
		));

		require_once(DIR . '/includes/functions_editor.php');
		$textareacols = fetch_textarea_width();
		eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

		eval('$navbar = "' . fetch_template('navbar') . '";');
		$url =& $vbulletin->url;

		($hook = vBulletinHook::fetch_hook('report_form_start')) ? eval($hook) : false;

		$forminfo = $reportobj->set_forminfo($pictureinfo);
		eval('print_output("' . fetch_template('reportitem') . '");');
	}

	if ($_POST['do'] == 'sendemail')
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'reason' => TYPE_STR,
		));

		if ($vbulletin->GPC['reason'] == '')
		{
			eval(standard_error(fetch_error('noreason')));
		}

		$reportobj->do_report($vbulletin->GPC['reason'], $pictureinfo);

		$url =& $vbulletin->url;
		eval(print_standard_redirect('redirect_reportthanks'));
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'picture')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber'  => TYPE_UINT,
		'perpage'     => TYPE_UINT,
		'commentid'   => TYPE_UINT,
		'showignored' => TYPE_BOOL,
	));

	if (empty($pictureinfo) OR ($pictureinfo['state'] == 'moderation' AND !can_moderate(0, 'canmoderatepictures') AND $pictureinfo['userid'] != $vbulletin->userinfo['userid']) AND !can_moderate(0, 'caneditalbumpicture'))
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	$pictureinfo['adddate'] = vbdate($vbulletin->options['dateformat'], $pictureinfo['dateline'], true);
	$pictureinfo['addtime'] = vbdate($vbulletin->options['timeformat'], $pictureinfo['dateline']);

	$pictureurl = create_full_url("picture.php?albumid=$albuminfo[albumid]&pictureid=$pictureinfo[pictureid]");
	if (!preg_match('#^[a-z]+://#i', $pictureurl))
	{
		$pictureurl = $vbulletin->options['bburl'] . "/picture.php?albumid=$albuminfo[albumid]&pictureid=$pictureinfo[pictureid]";

	}
	$pictureinfo['pictureurl'] = htmlspecialchars_uni($pictureurl);
	$pictureinfo['caption_censored'] = fetch_censored_text($pictureinfo['caption']);

	$show['picture_owner'] = ($userinfo['userid'] == $vbulletin->userinfo['userid']);

	$show['edit_picture_option'] = ($userinfo['userid'] == $vbulletin->userinfo['userid'] OR can_moderate(0, 'caneditalbumpicture'));

	$show['add_group_link'] = ($userinfo['userid'] == $vbulletin->userinfo['userid']
		AND $vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->options['socnet_groups_albums_enabled']
		AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']
		AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups']
		AND $pictureinfo['state'] != 'moderation'
	);

	$show['reportlink'] = (
		$vbulletin->userinfo['userid']
		AND ($vbulletin->options['rpforumid'] OR
			($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']))
	);

	$navpictures_sql = $db->query_read_slave("
		SELECT albumpicture.pictureid
		FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (albumpicture.pictureid = picture.pictureid)
		WHERE albumpicture.albumid = $albuminfo[albumid]
		" . ((!can_moderate(0, 'canmoderatepictures') AND $pictureinfo['userid'] != $vbulletin->userinfo['userid']) ? "AND picture.state = 'visible'" : "") . "
		ORDER BY albumpicture.dateline DESC
	");
	$pic_location = fetch_picture_location_info($navpictures_sql, $pictureinfo['pictureid']);

	($hook = vBulletinHook::fetch_hook('album_picture')) ? eval($hook) : false;

	if ($vbulletin->options['pc_enabled'] AND $pictureinfo['state'] == 'visible')
	{
		require_once(DIR . '/includes/functions_picturecomment.php');

		$pagenumber = $vbulletin->GPC['pagenumber'];
		$perpage = $vbulletin->GPC['perpage'];
		$picturecommentbits = fetch_picturecommentbits($pictureinfo, $messagestats, $pagenumber, $perpage, $vbulletin->GPC['commentid'], $vbulletin->GPC['showignored']);

		$pagenavbits = array(
			"albumid=$albuminfo[albumid]",
			"pictureid=$pictureinfo[pictureid]"
		);
		if ($perpage != $vbulletin->options['pc_perpage'])
		{
			$pagenavbits[] = "pp=$perpage";
		}
		if ($vbulletin->GPC['showignored'])
		{
			$pagenavbits[] = 'showignored=1';
		}

		$pagenav = construct_page_nav($pagenumber, $perpage, $messagestats['total'],
			'album.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavbits),
			''
		);

		$editorid = fetch_picturecomment_editor($pictureinfo, $pagenumber, $messagestats);
		if ($editorid)
		{
			eval('$picturecomment_form = "' . fetch_template('picturecomment_form') . '";');
		}
		else
		{
			$picturecomment_form = '';
		}

		$show['picturecomment_options'] = ($picturecomment_form OR $picturecommentbits);

		eval('$picturecomment_commentarea = "' . fetch_template('picturecomment_commentarea') . '";');
		eval('$picturecomment_css = "' . fetch_template('picturecomment_css') . '";');
	}
	else
	{
		$picturecomment_commentarea = '';
		$picturecomment_css = '';
	}

	$show['moderation'] = ($pictureinfo['state'] == 'moderation');

	($hook = vBulletinHook::fetch_hook('album_picture_complete')) ? eval($hook) : false;

	// navbar and final output
	$navbits = construct_navbits(array(
		'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => $vbphrase['albums'],
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "albumid=$albuminfo[albumid]" => $albuminfo['title_html'],
		'' => $vbphrase['view_picture']
	));

	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('album_pictureview') . '");');
}

// #######################################################################
if ($_REQUEST['do'] == 'album')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber' => TYPE_UINT,
		'addgroup'   => TYPE_BOOL
	));

	if (empty($albuminfo))
	{
		standard_error(fetch_error('invalidid', $vbphrase['album'], $vbulletin->options['contactuslink']));
	}

	if ($vbulletin->GPC['addgroup'] AND $albuminfo['userid'] != $vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$show['add_group_row'] = ($userinfo['userid'] == $vbulletin->userinfo['userid']
		AND $vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->options['socnet_groups_albums_enabled']
		AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']
		AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups']
	);

	($hook = vBulletinHook::fetch_hook('album_album')) ? eval($hook) : false;

	if ($vbulletin->GPC['addgroup'] AND $show['add_group_row'])
	{
		// need a list of groups this user is in
		$groups_sql = $db->query_read_slave("
			SELECT socialgroup.groupid, socialgroup.name
			FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
			INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
				(socialgroupmember.groupid = socialgroup.groupid AND socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . ")
			WHERE socialgroupmember.type = 'member'
				AND socialgroup.options & " . $vbulletin->bf_misc_socialgroupoptions['enable_group_albums'] . "
			ORDER BY socialgroup.name
		");
		if ($db->num_rows($groups_sql) == 0)
		{
			standard_error(fetch_error('not_member_groups_find_some', $vbulletin->session->vars['sessionurl_q']));
		}

		$group_options = '';
		while ($group = $db->fetch_array($groups_sql))
		{
			$optionvalue = $group['groupid'];
			$optiontitle = fetch_censored_text($group['name']);
			eval('$group_options .= "' . fetch_template('option') . '";');
		}

		$show['add_group_form'] = true;
		$show['add_group_row'] = false;
		$show['private_notice'] = ($albuminfo['state'] == 'private');

		$perpage = 999999; // disable pagination
	}
	else
	{
		$show['add_group_form'] = false;

		$perpage = $vbulletin->options['album_pictures_perpage'];
	}

	if ($vbulletin->GPC['pagenumber'] < 1)
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}

	$input_pagenumber = $vbulletin->GPC['pagenumber'];

	if (can_moderate(0, 'canmoderatepictures') OR $albuminfo['userid'] == $vbulletin->userinfo['userid'])
	{
		$totalpictures = $albuminfo['visible'] + $albuminfo['moderation'];
	}
	else
	{
		$totalpictures = $albuminfo['visible'];
	}

	$total_pages = max(ceil($totalpictures / $perpage), 1); // 0 pictures still needs an empty page
	$pagenumber = ($vbulletin->GPC['pagenumber'] > $total_pages ? $total_pages : $vbulletin->GPC['pagenumber']);
	$start = ($pagenumber - 1) * $perpage;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('album_album_query')) ? eval($hook) : false;

	$pictures = $db->query_read("
		SELECT picture.pictureid, picture.userid, picture.caption, picture.extension, picture.filesize, picture.idhash, picture.state,
			picture.thumbnail_filesize, picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height,
			albumpicture.dateline
			$hook_query_fields
		FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = albumpicture.pictureid)
		$hook_query_joins
		WHERE albumpicture.albumid = $albuminfo[albumid]
			" . ((!can_moderate(0, 'canmoderatepictures') AND $albuminfo['userid'] != $vbulletin->userinfo['userid']) ? "AND picture.state = 'visible'" : "") . "
			$hook_query_where
		ORDER BY albumpicture.dateline DESC
		LIMIT $start, $perpage
	");

	// work out the effective picturebit height/width including any borders and paddings; the +4 works around an IE float issue
	$picturebit_height = $vbulletin->options['album_thumbsize'] + (($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding']) * 2 + 4;
	$picturebit_width = $vbulletin->options['album_thumbsize'] + (($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding']) * 2;

	$picturebits = '';
	$picnum = 0;
	while ($picture = $db->fetch_array($pictures))
	{
		$picture = prepare_pictureinfo_thumb($picture, $albuminfo);

		if ($picnum % $vbulletin->options['album_pictures_perpage'] == 0)
		{
			$show['page_anchor'] = true;
			$page_anchor = ($picnum / $vbulletin->options['album_pictures_perpage']) + 1;
		}
		else
		{
			$show['page_anchor'] = false;
		}

		$picnum++;

		if ($picture['state'] == 'moderation')
		{
			$show['moderation'] = true;
		}
		else
		{
			$show['moderation'] = false;
			$have_visible = true;
		}

		($hook = vBulletinHook::fetch_hook('album_album_picturebit')) ? eval($hook) : false;

		if ($show['add_group_form'] AND $picture['state'] == 'visible')
		{
			eval('$picturebits .= "' . fetch_template('album_picturebit_checkbox') . '";');
		}
		else
		{
			eval('$picturebits .= "' . fetch_template('album_picturebit') . '";');
		}
	}

	$pagenav = construct_page_nav($pagenumber, $perpage, $totalpictures, 'album.php?' . $vbulletin->session->vars['sessionurl'] . "albumid=$albuminfo[albumid]", '');

	$show['add_group_form'] = ($have_visible AND $show['add_group_form'] AND $picturebits) ? true : false;
	$show['add_group_row'] = ($have_visible AND $show['add_group_row'] AND $picturebits) ? true : false;
	$show['edit_album_option'] = ($userinfo['userid'] == $vbulletin->userinfo['userid'] OR can_moderate(0, 'caneditalbumpicture'));
	$show['add_picture_option'] = (
		$userinfo['userid'] == $vbulletin->userinfo['userid']
		AND fetch_count_overage($userinfo['userid'], $vbulletin->userinfo['permissions']['albummaxpics']) <= 0
		AND (
			!$vbulletin->options['album_maxpicsperalbum']
			OR $totalpictures - $vbulletin->options['album_maxpicsperalbum'] < 0
		)
	);

	if ($albuminfo['state'] == 'private')
	{
		$show['personalalbum'] = true;
		$albumtype = $vbphrase['private_album_paren'];
	}
	else if ($albuminfo['state'] == 'profile')
	{
		$show['personalalbum'] = true;
		$albumtype = $vbphrase['profile_album_paren'];
	}

	($hook = vBulletinHook::fetch_hook('album_album_complete')) ? eval($hook) : false;

	// navbar and final output
	$navbits = construct_navbits(array(
		'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => $vbphrase['albums'],
		'' => $albuminfo['title_html']
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	//$headinclude .= '<style type="text/css">#picturebits table { border: 1px solid red; }</style>';

	eval('print_output("' . fetch_template('album_picturelist') . '");');
}

// #######################################################################
if ($_REQUEST['do'] == 'latest' OR $_REQUEST['do'] == 'overview')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber' => TYPE_UINT
	));

	$perpage = $vbulletin->options['albums_perpage'];

	// only show latest if we're not showing more specific user albums
	if ((!$userinfo OR !$vbulletin->GPC['pagenumber']) AND $vbulletin->options['album_recentalbumdays'])
	{
		// Create collection
		require_once(DIR . '/includes/class_groupmessage.php');
		$collection_factory = new vB_Collection_Factory($vbulletin);
		$collection = $collection_factory->create('album', false, $vbulletin->GPC['pagenumber'], $perpage);

		// Set counts for view
		list($pagestart, $pageend, $pageshown, $pagetotal) = array_values($collection->fetch_counts());

		// Nasty kludge
		$album_width = $album_height = $vbulletin->options['album_thumbsize'];
		$album_height += (($stylevar['cellpadding'] * 2) + 52);
		$album_width += ($stylevar['cellpadding'] * 2) + 16;

		// Get actual resolved page number in case input was normalised
		if ($collection->fetch_count())
		{
			$pagenumber = $collection->fetch_pagenumber();

			// Create bit factory
			$bitfactory = new vB_Bit_Factory($vbulletin, 'album');

			// Build message bits for all items
			$latestbits = '';
			while ($item = $collection->fetch_item())
			{
				$bit = $bitfactory->create($item);
				$bit->set_template('album_latestbit');
				$latestbits .= $bit->construct();
			}

			// Construct page navigation
			$latest_pagenav = construct_page_nav($pagenumber, $perpage, $pagetotal, 'album.php?' . $vbulletin->session->vars['sessionurl'] . "do=latest");

			$show['latestalbums'] = true;
		}
		unset($collection_factory, $collection);

		if (!$userinfo)
		{
			// navbar and final output
			$navbits = construct_navbits(array(
				'album.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['albums'],
				'' => $vbphrase['recently_updated_albums']
			));
			eval('$navbar = "' . fetch_template('navbar') . '";');

			$custompagetitle = $vbphrase['recently_updated_albums'];
		}
		else
		{
			// navbar and final output
			$navbits = construct_navbits(array(
				'album.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['albums'],
				'' => $vbphrase['overview']
			));
			eval('$navbar = "' . fetch_template('navbar') . '";');

			$custompagetitle = $vbphrase['albums'];
		}

		($hook = vBulletinHook::fetch_hook('album_latest_complete')) ? eval($hook) : false;
	}

	$templatename = 'album_list';

	// also show user albums
	if ($userinfo)
	{
		$_REQUEST['do'] = 'user';
	}
	else
	{
		if (!$latestbits)
		{
			standard_error(fetch_error('no_recently_updated_albums'));
		}
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'user')
{
	// was profile privacy condition - moved up to top of file
	if (true)
	{
		$show['user_albums'] = true;
		$vbulletin->input->clean_array_gpc('r', array(
			'pagenumber' => TYPE_UINT
		));

		$state = array('public');
		if (can_view_private_albums($userinfo['userid']))
		{
			$state[] = 'private';
		}
		if (can_view_profile_albums($userinfo['userid']))
		{
			$state[] = 'profile';
		}

		$albumcount = $db->query_first("
			SELECT COUNT(*) AS total
			FROM " . TABLE_PREFIX . "album
			WHERE userid = $userinfo[userid]
				AND state IN ('" . implode("', '", $state) . "')
		");

		if ($vbulletin->GPC['pagenumber'] < 1)
		{
			$vbulletin->GPC['pagenumber'] = 1;
		}

		$perpage = $vbulletin->options['albums_perpage'];
		$total_pages = max(ceil($albumcount['total'] / $perpage), 1); // handle the case of 0 albums
		$pagenumber = ($vbulletin->GPC['pagenumber'] > $total_pages ? $total_pages : $vbulletin->GPC['pagenumber']);
		$start = ($pagenumber - 1) * $perpage;

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('album_user_query')) ? eval($hook) : false;

		// fetch data and prepare data
		$albums = $db->query_read("
			SELECT album.*,
				picture.pictureid, picture.extension, picture.idhash,
				picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height
				$hook_query_fields
			FROM " . TABLE_PREFIX . "album AS album
			LEFT JOIN " . TABLE_PREFIX . "picture AS picture ON (album.coverpictureid = picture.pictureid AND picture.thumbnail_filesize > 0)
			$hook_query_joins
			WHERE album.userid = $userinfo[userid]
				AND album.state IN ('" . implode("', '", $state) . "')
				$hook_query_where
			ORDER BY album.lastpicturedate DESC
			LIMIT $start, $perpage
		");

		$albumbits = '';
		while ($album = $db->fetch_array($albums))
		{
			$album['picturecount'] = vb_number_format($album['visible']);
			$album['picturedate'] = vbdate($vbulletin->options['dateformat'], $album['lastpicturedate'], true);
			$album['picturetime'] = vbdate($vbulletin->options['timeformat'], $album['lastpicturedate']);

			$album['description_html'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($album['description'])));
			$album['title_html'] = fetch_word_wrapped_string(fetch_censored_text($album['title']));

			$album['coverthumburl'] = ($album['pictureid'] ? fetch_picture_url($album, $album, true) : '');
			$album['coverdimensions'] = ($album['thumbnail_width'] ? "width=\"$album[thumbnail_width]\" height=\"$album[thumbnail_height]\"" : '');

			if ($album['state'] == 'private')
			{
				$show['personalalbum'] = true;
				$albumtype = $vbphrase['private_album_paren'];
			}
			else if ($album['state'] == 'profile')
			{
				$show['personalalbum'] = true;
				$albumtype = $vbphrase['profile_album_paren'];
			}
			else
			{
				$show['personalalbum'] = false;
			}

			if ($album['moderation'] AND (can_moderate(0, 'canmoderatepictures') OR $vbulletin->userinfo['userid'] == $album['userid']))
			{
				$show['moderated'] = true;
				$album['moderatedcount'] = vb_number_format($album['moderation']);
			}
			else
			{
				$show['moderated'] = false;
			}

			($hook = vBulletinHook::fetch_hook('album_user_albumbit')) ? eval($hook) : false;

			eval('$albumbits .= "' . fetch_template('albumbit') . '";');
		}

		$pagenav = construct_page_nav($pagenumber, $perpage, $albumcount['total'],
			'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]", ''
		);

		$show['add_album_option'] = ($userinfo['userid'] == $vbulletin->userinfo['userid']);

		($hook = vBulletinHook::fetch_hook('album_user_complete')) ? eval($hook) : false;
	}

	if (!$navbits)
	{
	// navbar and final output
	$navbits = construct_navbits(array(
		'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
		'' => $vbphrase['albums']
	));

	eval('$navbar = "' . fetch_template('navbar') . '";');

		$custompagetitle = ($custompagetitle ? $custompagetitle : construct_phrase($vbphrase['xs_albums'], $userinfo['username']));
}

	$templatename = 'album_list';
}


// #######################################################################
if ($_REQUEST['do'] == 'moderated')
{
	if (!$vbulletin->options['pc_enabled'])
	{
		print_no_permission();
	}

	if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!($vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment']))
	{
		print_no_permission();
	}

	require_once(DIR . '/includes/functions_picturecomment.php');
	require_once(DIR . '/includes/class_bbcode.php');
	require_once(DIR . '/includes/class_picturecomment.php');
	require_once(DIR . '/includes/functions_bigthree.php');

	$coventry = fetch_coventry('string');

	$bbcode = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	// note: this code assumes that albumpicture and picture are 1:1 because they are in 3.7
	$comments = $db->query_read_slave("
		SELECT picturecomment.*, user.*, picturecomment.ipaddress AS messageipaddress,
			picture.caption, picture.extension, picture.filesize, picture.idhash,
			picture.thumbnail_filesize, picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height,
			albumpicture.albumid
		FROM " . TABLE_PREFIX . "picture AS picture
		INNER JOIN " . TABLE_PREFIX . "albumpicture AS albumpicture ON (albumpicture.pictureid = picture.pictureid)
		INNER JOIN " . TABLE_PREFIX . "picturecomment AS picturecomment ON
			(picturecomment.pictureid = picture.pictureid AND picturecomment.state = 'moderation')
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (picturecomment.postuserid = user.userid)
		WHERE picture.userid = " . $vbulletin->userinfo['userid'] . "
			" . ($coventry ? "AND picturecomment.postuserid NOT IN ($coventry)" : '') . "
		ORDER BY picturecomment.dateline ASC
	");

	$picturecommentbits = '';
	$moderated_count = 0;

	while ($comment = $db->fetch_array($comments))
	{
		// $comment contains comment, picture, and album info
		$pictureinfo = array(
			'pictureid' => $comment['pictureid'],
			'albumid' => $comment['albumid'],
			'userid' => $vbulletin->userinfo['userid'],
			'caption' => $comment['caption'],
			'extension' => $comment['extension'],
			'filesize' => $comment['filesize'],
			'idhash' => $comment['idhash'],
			'thumbnail_filesize' => $comment['thumbnail_filesize'],
			'thumbnail_dateline' => $comment['thumbnail_dateline'],
			'thumbnail_width' => $comment['thumbnail_width'],
			'thumbnail_height' => $comment['thumbnail_height'],
		);

		$albuminfo = array(
			'albumid' => $comment['albumid'],
			'userid' => $vbulletin->userinfo['userid']
		);

		$pictureinfo = prepare_pictureinfo_thumb($pictureinfo, $albuminfo);

		$factory = new vB_Picture_CommentFactory($vbulletin, $bbcode, $pictureinfo);

		$response_handler = new vB_Picture_Comment_ModeratedView($vbulletin, $factory, $bbcode, $pictureinfo, $comment);
		$response_handler->cachable = false;

		$picturecommentbits .= $response_handler->construct();
		$moderated_count++;
	}

	if ($moderated_count != $vbulletin->userinfo['pcmoderatedcount'])
	{
		// back counter -- likely tachy based, rebuild all counters
		build_picture_comment_counters($vbulletin->userinfo['userid']);
	}

	if (!$picturecommentbits)
	{
		standard_error(
			fetch_error('no_picture_comments_awaiting_approval', 'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]"),
			'',
			false
		);
	}

	// this is a small kludge to let me use fetch_user_picture_message_perm
	// all pictures will be from this user and userid is the only value used
	$pictureinfo = array(
		'userid' => $userinfo['userid']
	);
	$show['delete'] = fetch_user_picture_message_perm('candeletemessages', $pictureinfo);
	$show['undelete'] = fetch_user_picture_message_perm('canundeletemessages', $pictureinfo);
	$show['approve'] = fetch_user_picture_message_perm('canmoderatemessages', $pictureinfo);
	$show['inlinemod'] = ($show['delete'] OR $show['undelete'] OR $show['approve']);

	eval('$picturecomment_css = "' . fetch_template('picturecomment_css') . '";');

	($hook = vBulletinHook::fetch_hook('album_moderated_complete')) ? eval($hook) : false;

	// navbar and final output
	$navbits = construct_navbits(array(
		'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_albums'], $userinfo['username']),
		'' => $vbphrase['picture_comments_awaiting_approval']
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('album_moderatedcomments') . '");');
}

// #######################################################################
if ($_REQUEST['do'] == 'unread')
{
	if (!$vbulletin->options['pc_enabled'])
	{
		print_no_permission();
	}

	if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	require_once(DIR . '/includes/functions_bigthree.php');
	$coventry = fetch_coventry('string');

	$pictures = $db->query_read_slave("
		SELECT picture.pictureid, picture.caption, picture.extension, picture.filesize, picture.idhash,
			picture.thumbnail_filesize, picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height,
			albumpicture.albumid, MIN(picturecomment.commentid) AS unreadcommentid, COUNT(*) AS unreadcomments
		FROM " . TABLE_PREFIX . "picture AS picture
		INNER JOIN " . TABLE_PREFIX . "albumpicture AS albumpicture ON (albumpicture.pictureid = picture.pictureid)
		INNER JOIN " . TABLE_PREFIX . "picturecomment AS picturecomment ON
			(picturecomment.pictureid = picture.pictureid AND picturecomment.state = 'visible' AND picturecomment.messageread = 0)
		WHERE picture.userid = " . $vbulletin->userinfo['userid'] . "
			" . ($coventry ? "AND picturecomment.postuserid NOT IN ($coventry)" : '') . "
		GROUP BY picture.pictureid
		ORDER BY unreadcommentid ASC
	");

	// work out the effective picturebit height/width including any borders and paddings; the +4 works around an IE float issue
	$picturebit_height = $vbulletin->options['album_thumbsize'] + (($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding']) * 2 + 4;
	$picturebit_width = $vbulletin->options['album_thumbsize'] + (($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding']) * 2;

	$picturebits = '';
	$unread_count = 0;

	while ($picture = $db->fetch_array($pictures))
	{
		// $comment contains picture and album info
		$picture = prepare_pictureinfo_thumb($picture, $picture);

		$picture['unreadcomments'] = vb_number_format($picture['unreadcomments']);

		($hook = vBulletinHook::fetch_hook('album_unread_picturebit')) ? eval($hook) : false;

		eval('$picturebits .= "' . fetch_template('album_picturebit_unread') . '";');
	}

	if ($moderated_count != $vbulletin->userinfo['pcunreadcount'])
	{
		// back counter -- likely tachy based, rebuild all counters
		require_once(DIR . '/includes/functions_picturecomment.php');
		build_picture_comment_counters($vbulletin->userinfo['userid']);
	}

	if (!$picturebits)
	{
		standard_error(
			fetch_error('no_unread_picture_comments', 'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]"),
			'',
			false
		);
	}

	($hook = vBulletinHook::fetch_hook('album_unread_complete')) ? eval($hook) : false;

	// navbar and final output
	$navbits = construct_navbits(array(
		'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_profile'], $userinfo['username']),
		'album.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => construct_phrase($vbphrase['xs_albums'], $userinfo['username']),
		'' => $vbphrase['unread_picture_comments']
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('album_unreadcomments') . '");');
}



// #######################################################################
if ($templatename != '')
{
	$custompagetitle = empty($custompagetitle) ? $pagetitle : $custompagetitle;
	eval('print_output("' . fetch_template($templatename) . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
