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
define('NOSHUTDOWNFUNC', 1);
define('NOCOOKIES', 1);
define('THIS_SCRIPT', 'picture');
define('CSRF_PROTECTION', true);
define('VB_AREA', 'Forum');
define('NOPMPOPUP', 1);
define('LOCATION_BYPASS', 1);

if ((!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) OR !empty($_SERVER['HTTP_IF_NONE_MATCH'])))
{
	// Don't check modify date as URLs contain unique items to nullify caching
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 304 Not Modified');
	}
	else
	{
		header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
	}
	exit;
}

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_album.php');
require_once(DIR . '/includes/functions_user.php');

$vbulletin->input->clean_array_gpc('r', array(
	'pictureid' => TYPE_UINT,
	'albumid'   => TYPE_UINT,
	'groupid'   => TYPE_UINT,
	'thumb'     => TYPE_BOOL
));

($hook = vBulletinHook::fetch_hook('picture_start')) ? eval($hook) : false;

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']))
{
	$imageinfo = false;
}
else if ($vbulletin->GPC['albumid'])
{
	$imageinfo = $db->query_first_slave("
		SELECT picture.pictureid, picture.userid, picture.extension, picture.idhash, picture.state,
			albumpicture.dateline, album.state AS albumstate, profileblockprivacy.requirement AS privacy_requirement,
			" . ($vbulletin->GPC['thumb'] ?
				"picture.thumbnail AS filedata, picture.thumbnail_filesize AS filesize" :
				'picture.filedata, picture.filesize'
			) . "
		FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (albumpicture.pictureid = picture.pictureid)
		INNER JOIN " . TABLE_PREFIX . "album AS album ON (albumpicture.albumid = album.albumid)
		LEFT JOIN " . TABLE_PREFIX . "profileblockprivacy AS profileblockprivacy ON
			(profileblockprivacy.userid = picture.userid AND profileblockprivacy.blockid = 'albums')
		WHERE albumpicture.albumid = " . $vbulletin->GPC['albumid'] . " AND albumpicture.pictureid = " . $vbulletin->GPC['pictureid']
	);
}
else if ($vbulletin->GPC['groupid'])
{
	$imageinfo = $db->query_first_slave("
		SELECT picture.pictureid, picture.userid, picture.extension, picture.idhash, picture.state,
			socialgrouppicture.dateline, 'public' AS albumstate,
			" . ($vbulletin->GPC['thumb'] ?
				"picture.thumbnail AS filedata, picture.thumbnail_filesize AS filesize" :
				'picture.filedata, picture.filesize'
			) . "
		FROM " . TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (socialgrouppicture.pictureid = picture.pictureid)
		INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
			(socialgroupmember.userid = picture.userid AND socialgroupmember.groupid = socialgrouppicture.groupid
				AND socialgroupmember.type = 'member')
		" . ((!$vbulletin->GPC['thumb'] AND !can_moderate(0, 'caneditalbumpicture')) ?
			"INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS browsingmember ON
				(browsingmember.userid = " . $vbulletin->userinfo['userid'] . " AND browsingmember.groupid = socialgrouppicture.groupid)" :
				''
		) . "
		WHERE socialgrouppicture.groupid = " . $vbulletin->GPC['groupid'] . " AND socialgrouppicture.pictureid = " . $vbulletin->GPC['pictureid']
	);
}
else
{
	$imageinfo = null;
}

($hook = vBulletinHook::fetch_hook('picture_imageinfo')) ? eval($hook) : false;

$have_image = ($imageinfo ? true : false);

if ($have_image AND $imageinfo['state'] == 'moderation' AND !can_moderate(0, 'canmoderatepictures') AND $imageinfo['userid'] != $vbulletin->userinfo['userid'] AND !can_moderate(0, 'caneditalbumpicture'))
{
	$have_image = false;
}

if ($have_image)
{
	if ($vbulletin->options['album_dataloc'] == 'db')
	{
		$have_image = strlen($imageinfo['filedata']) > 0;
	}
	else
	{
		$have_image = file_exists(fetch_picture_fs_path($imageinfo, $vbulletin->GPC['thumb']));
	}
}

if ($have_image)
{
	if ($vbulletin->GPC['albumid'] AND $imageinfo['privacy_requirement'])
	{
		if (fetch_user_relationship($imageinfo['userid'], $vbulletin->userinfo['userid']) < $imageinfo['privacy_requirement'])
		{
			$have_image = false;
		}
	}

	if ($imageinfo['albumstate'] != 'profile' AND !($vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']))
	{	// user's w/o viewing permission can only view profile category pictures directly
		$have_image = false;
	}

	if ($imageinfo['albumstate'] == 'private')
	{
		if (!can_view_private_albums($imageinfo['userid']))
		{
			// private album we can't see
			$have_image = false;
		}
	}
}

($hook = vBulletinHook::fetch_hook('picture_haveimage')) ? eval($hook) : false;

if ($have_image)
{
	header('Cache-control: max-age=31536000');
	header('Expires: ' . gmdate('D, d M Y H:i:s', (TIMENOW + 31536000)) . ' GMT');
	header('Content-disposition: inline; filename=' . "user$imageinfo[userid]_pic$imageinfo[pictureid]_$imageinfo[dateline]" . ($vbulletin->GPC['thumb'] ? '_thumb' : '') . ".$imageinfo[extension]");
	header('Content-transfer-encoding: binary');
	if ($imageinfo['filesize'])
	{
		header('Content-Length: ' . $imageinfo['filesize']);
	}
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $imageinfo['dateline']) . ' GMT');
	header('ETag: "' . $imageinfo['dateline'] . '-' . $imageinfo['pictureid'] . ($vbulletin->GPC['thumb'] ? '-thumb' : '') . '"');

	if ($imageinfo['extension'] == 'jpg' OR $imageinfo['extension'] == 'jpeg')
	{
		header('Content-type: image/jpeg');
	}
	else if ($imageinfo['extension'] == 'png')
	{
		header('Content-type: image/png');
	}
	else
	{
		header('Content-type: image/gif');
	}
	$db->close();

	if ($vbulletin->options['album_dataloc'] == 'db')
	{
		echo $imageinfo['filedata'];
	}
	else
	{
		@readfile(fetch_picture_fs_path($imageinfo, $vbulletin->GPC['thumb']));
	}
}
else
{
	header('Content-type: image/gif');
	readfile(DIR . '/' . $vbulletin->options['cleargifurl']);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 93801 $
|| # $Date: 2017-04-25 06:47:39 -0700 (Tue, 25 Apr 2017) $
|| ####################################################################
\*======================================================================*/
?>
