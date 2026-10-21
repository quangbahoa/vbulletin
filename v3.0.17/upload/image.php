<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('LOCATION_BYPASS', 1);
define('THIS_SCRIPT', 'image');
define('VB_AREA', 'Forum');

if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) AND $_GET['type'] != 'regcheck')
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
if ($_GET['type'] == 'profile')
{
	require_once('./global.php');
}
else
{
	require_once('./includes/init.php');
}

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if ($_REQUEST['type'] == 'regcheck')
{
	require_once('./includes/functions_image.php');

	if ($_REQUEST['imagehash'] == 'test' AND $vboptions['gdversion'])
	{
		$imageinfo = array(
			'imagestamp' => 'vBulletin'
		);
	}
	else  if (!$vboptions['gdversion'] OR !$_REQUEST['imagehash'] OR !($imageinfo = $DB_site->query_first("SELECT imagestamp FROM " . TABLE_PREFIX . "regimage WHERE regimagehash = '" . addslashes($_REQUEST['imagehash']) . "'")))
	{
		header('Content-type: image/gif');
		readfile("./$vboptions[cleargifurl]");
		exit;
	}

	$string = $imageinfo['imagestamp'];
	for ($x = 0; $x < strlen($string); $x++)
	{
		$newstring .= $string["$x"] . ' ';
	}
	$string = '  ' . $newstring . ' ';

	// Temp image that creates string
	$temp_width  = 135;
	$temp_height = 20;
	// Resized image that blows up string.
	$image_width = 201;
	$image_height = 61;

	if ($vboptions['gdversion'] == 1)
	{
		$temp = imagecreate($temp_width, $temp_height);
		$image = imagecreate($image_width, $image_height);
	}
	else
	{
		$temp = imagecreatetruecolor($temp_width, $temp_height);
		$image = imagecreatetruecolor($image_width, $image_height);
	}

	// *********************************************************
	// create foreground and background colours from a selection
	$colors = array(
		1 => array('255,255,255', '0,0,0'),	// black on white
		2 => array('0,0,0', '255,255,255'),	// white on black
		#3 => array('255,0,0', '255,255,0'),	// yellow on red
		#4 => array('255,255,0', '255,0,0'),	// red on yellow
		#5 => array('255,128,0', '50,50,255'),	// blue on orange
		#6 => array('50,50,255', '255,128,0'),	// orange on blue
	);
	mt_srand((double)microtime() * 1000000);
	$randcolor = mt_rand(1, sizeof($colors));
	$bg = explode(',', $colors["$randcolor"][0]);
	$fg = explode(',', $colors["$randcolor"][1]);
	// end colour selection
	// *********************************************************

	$background_color = imagecolorallocate($temp, $bg[0], $bg[1], $bg[2]); //white background
	imagefill($temp, 0, 0, $background_color); // For GD2+
	$text_color = imagecolorallocate($temp, $fg[0], $fg[1], $fg[2]); //black text

	imagestring($temp, 5, 0, 2, $string, $text_color);
	imagecopyresized($image, $temp, 0, 0, 0, 0, $image_width, $image_height, $temp_width, $temp_height);
	imagedestroy($temp);

	$background_color = imagecolorallocate($image, $bg[0], $bg[1], $bg[2]); //white background
	$text_color = imagecolorallocate($image, $fg[0], $fg[1], $fg[2]); //black text

	// horizontal grid
	for ($x = 0; $x <= $image_height; $x += 20)
	{
		imageline($image, 0, $x, $image_width, $x, $text_color);
	}

	// vertical grid
	for ($x = 0; $x <= $image_width; $x += 20)
	{
		imageline($image, $x, 0, $x, $image_height, $text_color);
	}

	// random pixels
	$pixels = $image_width * $image_height / 10;
	for ($i = 0; $i < $pixels; $i++)
	{
		imagesetpixel($image, rand(0, $image_width), rand(0, $image_height), $text_color);
	}

	// get multipliers for waves
	$wavenum = 3;
	$wavemultiplier = ($wavenum * 360) / $image_width;

	// cosine wave
	$curX = 0;
	$curY = $image_height;
	for ($pt = 0; $pt < $image_width; $pt++)
	{
		$newX = $curX + 1;
		$newY = ($image_height/2) + (cos(deg2rad($newX * $wavemultiplier)) * ($image_height/2));
		ImageLine($image, $curX, $curY, $newX, $newY, $text_color);
		$curX = $newX;
		$curY = $newY;
	}

	// sine wave
	$curX = 0;
	$curY = 0;
	for ($pt = 0; $pt < $image_width; $pt++)
	{
		$newX = $curX + 1;
		$newY = ($image_height/2) + (sin(deg2rad($newX * $wavemultiplier - 90)) * ($image_height/2));
		ImageLine($image, $curX, $curY, $newX, $newY, $text_color);
		$curX = $newX;
		$curY = $newY;
	}

	vbimage($image);
}
else
{
	$userid = intval($_REQUEST['userid']);

	if ($_REQUEST['type'] == 'profile')
	{
		$data = 'profilepicdata';
		$table = 'customprofilepic';
		// No permissions to see profile pics
		if (!$vboptions['profilepicenabled'] OR (!($permissions['genericpermissions'] & CANSEEPROFILEPIC) AND $bbuserinfo['userid'] != $userid))
		{
			header('Content-type: image/gif');
			readfile("./$vboptions[cleargifurl]");
			exit;
		}
	}
	else
	{
		$data = 'avatardata';
		$table = 'customavatar';
	}

	if ($imageinfo = $DB_site->query_first("
		SELECT $data, dateline, filename
		FROM " . TABLE_PREFIX . "$table
		WHERE userid = $userid AND visible = 1
		"))
	{

		header('Cache-control: max-age=31536000');
		header('Expires: ' . gmdate('D, d M Y H:i:s', (TIMENOW + 31536000)) . ' GMT');
		header('Content-disposition: inline; filename=' . $imageinfo['filename']);
		header('Content-transfer-encoding: binary');
		header('Content-Length: ' . strlen($imageinfo["$data"]));
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $imageinfo['dateline']) . ' GMT');
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
		echo $imageinfo["$data"];
	}
	else
	{
		header('Content-type: image/gif');
		readfile("./$vboptions[cleargifurl]");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11004 $
|| ####################################################################
\*======================================================================*/
?>