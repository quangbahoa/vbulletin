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
define('NOSHUTDOWNFUNC', 1);
define('NOCOOKIES', 1);
define('THIS_SCRIPT', 'image');
define('CSRF_PROTECTION', true);
define('VB_AREA', 'Forum');
define('NOPMPOPUP', 1);

if ((!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) OR !empty($_SERVER['HTTP_IF_NONE_MATCH'])) AND $_GET['type'] != 'regcheck')
{
	// Don't check modify date as URLs contain unique items to nullify caching
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 304 Not Modified');
	}
	else
	{
		header('HTTP/1.1 304 Not Modified');
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
if ($_REQUEST['type'] == 'dberror') // do not require back-end
{
	header('Content-type: image/gif');
	readfile('./includes/database_error_image.jpg');
	exit;
}
else if ($_REQUEST['type'] == 'ieprompt')
{
	header('Content-type: image/gif');
	readfile('./includes/ieprompt.jpg');
	exit;
}
else if ($_REQUEST['type'] == 'profile') // do not modify this $_REQUEST
{
	define('LOCATION_BYPASS', 1);
	require_once('./global.php');
}
else
{
	define('SKIP_SESSIONCREATE', 1);
	define('SKIP_USERINFO', 1);
	define('SKIP_DEFAULTDATASTORE', 1);
	define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
	require_once(CWD . '/includes/init.php');
}

$vbulletin->input->clean_array_gpc('r', array(
	'type'   => TYPE_STR,
	'userid' => TYPE_UINT,
));

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if ($vbulletin->GPC['userid'] == 0)
{
	$vbulletin->GPC['type'] = 'regcheck';
}

if ($vbulletin->GPC['type'] == 'regcheck')
{
	require_once(DIR . '/includes/class_image.php');

	$vbulletin->input->clean_array_gpc('r', array(
		'imagehash' => TYPE_STR,
		'i'         => TYPE_STR,
	));

	$moveabout = true;
	if ($vbulletin->GPC['imagehash'] == '' OR $vbulletin->GPC['imagehash'] == 'test')
	{
		$imageinfo = array(
			'imagestamp' => 'vBulletin',
		);

		$moveabout = false;
	}
	else  if (!$vbulletin->options['regimagetype'] OR $vbulletin->GPC['imagehash'] == '' OR !($imageinfo = $db->query_first("SELECT imagestamp FROM " . TABLE_PREFIX . "regimage WHERE regimagehash = '" . $db->escape_string($vbulletin->GPC['imagehash']) . "' AND viewed = 0")))
	{
		header('Content-type: image/gif');
		readfile(DIR . '/' . $vbulletin->options['cleargifurl']);
		exit;
	}
	else
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "regimage
			SET viewed = 1
			WHERE regimagehash = '" . $db->escape_string($vbulletin->GPC['imagehash']) . "' AND
				viewed = 0
		");
		if ($db->affected_rows() == 0)
		{	// image managed to get viewed by someone else between the $imageinfo query above and now
			header('Content-type: image/gif');
			readfile(DIR . '/' . $vbulletin->options['cleargifurl']);
			exit;
		}
	}

	if ($vbulletin->GPC['i'] == 'gd')
	{
		$image = new vB_Image_GD($vbulletin);
	}
	else if ($vbulletin->GPC['i'] == 'im')
	{
		$image = new vB_Image_Magick($vbulletin);
	}
	else
	{
		$image =& vB_Image::fetch_library($vbulletin, 'regimage');
	}

	$db->close();
	$image->print_image_from_string($imageinfo['imagestamp'], $moveabout);
}
else
{
	$vbulletin->input->clean_array_gpc('r', array(
		'dateline' => TYPE_UINT,
	));

	if ($vbulletin->GPC['type'] == 'profile')
	{
		$table = 'customprofilepic';
		// No permissions to see profile pics
		if (!$vbulletin->options['profilepicenabled'] OR (!($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseeprofilepic']) AND $vbulletin->userinfo['userid'] != $vbulletin->GPC['userid']))
		{
			exec_shut_down();	// Update location with 'No permission to view profile picture'
			header('Content-type: image/gif');
			readfile(DIR . '/' . $vbulletin->options['cleargifurl']);
			exit;
		}
	}
	else if ($vbulletin->GPC['type'] == 'sigpic')
	{
		$table = 'sigpic';
	}
	else
	{
		$table = 'customavatar';
		($hook = vBulletinHook::fetch_hook('image_table')) ? eval($hook) : false;
	}

	if ($imageinfo = $db->query_first_slave("
			SELECT filedata, dateline, filename
			FROM " . TABLE_PREFIX . "$table
			WHERE userid = " . $vbulletin->GPC['userid'] . " AND visible = 1
		"))
	{
		($hook = vBulletinHook::fetch_hook('image_exists')) ? eval($hook) : false;

		header('Cache-control: max-age=31536000');
		header('Expires: ' . gmdate('D, d M Y H:i:s', (TIMENOW + 31536000)) . ' GMT');
		header('Content-disposition: inline; filename=' . $imageinfo['filename']);
		header('Content-transfer-encoding: binary');
		header('Content-Length: ' . strlen($imageinfo['filedata']));
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $imageinfo['dateline']) . ' GMT');
		header('ETag: "' . $imageinfo['dateline'] . '-' . $vbulletin->GPC['userid'] . '"');
		$extension = trim(substr(strrchr(strtolower($imageinfo['filename']), '.'), 1));
		if ($extension == 'jpg' OR $extension == 'jpeg')
		{
			header('Content-type: image/jpeg');
		}
		else if ($extension == 'png')
		{
			header('Content-type: image/png');
		}
		else
		{
			header('Content-type: image/gif');
		}
		$db->close();
		echo $imageinfo['filedata'];
	}
	else
	{
		($hook = vBulletinHook::fetch_hook('image_missing')) ? eval($hook) : false;

		header('Content-type: image/gif');
		readfile(DIR . '/' . $vbulletin->options['cleargifurl']);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 26402 $
|| ####################################################################
\*======================================================================*/
?>