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

error_reporting(E_ALL & ~E_NOTICE);

// ###################### Start processupload #######################
// $type can be 'avatar' or 'profilepic'
function process_image_upload($type, $url = '', $userinfo = 0)
{
	global $DB_site, $_FILES, $upload;
	global $vboptions, $stylevar, $bbuserinfo;

	if (VB_AREA == 'ModCP' OR VB_AREA == 'AdminCP')
	{
		$incp = true;
	}

	if (!$userinfo)
	{
		$userinfo = &$bbuserinfo;
	}
	else
	{
		cache_permissions($userinfo, false);
	}

	$imagepermissions = &$userinfo['permissions'];

	// check permissions to upload files
	switch($type)
	{
		// profile pic
		case 'profilepic':
			$checkperm = CANPROFILEPIC;
			break;

		// default: avatar
		default:
			$type = 'avatar';
			$checkperm = CANUSEAVATAR;
	}

	if (!($imagepermissions['genericpermissions'] & $checkperm) AND !$incp)
	{
		return false;
	}

	// get uploaded file details

	$upload = trim($_FILES['upload']['tmp_name']);
	$upload_name = trim($_FILES['upload']['name']);
	// get linked file details
	$url = trim($url);

	// make up a temporary name
	$tmp_name = 'vbupload' . substr(TIMENOW, -4);

	// do we have an uploaded file?
	if (is_uploaded_file($upload))
	{
		// check file exists on server
		if ($vboptions['safeupload'])
		{
			$path = $vboptions['tmppath'] . "/$tmp_name";
			if (move_uploaded_file($upload, $path) AND file_exists($path))
			{
				$filename = $path;
			}
		}
		else if (file_exists($upload))
		{
			$filename = $upload;
		}
	}
	// do we have a linked file and permission to use it?
	else if ($url AND $url != 'http://www.')
	{
		require_once('./includes/functions_file.php');
		// fopen is disabled
		if (ini_get('allow_url_fopen') == 0)
		{
			if ($incp)
			{
				print_stop_message('error_cannot_find_image_url_nofopen');
			}
			else
			{
				if ($imagepermissions['adminpermissions'] & CANCONTROLPANEL)
				{
					eval(print_standard_error('error_imagebadurl_nofopen'));
				}
				else
				{
					eval(print_standard_error('error_imagebadurl'));
				}
			}
		}
		else if ($filesize = remote_filesize($url))
		{
			$filetolarge = false;
			$maxsize = &$imagepermissions[$type . 'maxsize'];
			if ($maxsize AND $filesize > $maxsize)
			{
				$filetolarge = true;
			}
			else
			{
				// some webservers deny us if we don't have an user_agent
				@ini_set('user_agent', 'PHP');
				$handle = @fopen($url, 'rb');
				if (!$handle)
				{
					print_stop_message('error_cannot_find_image_url');
				}
				while (!feof($handle))
				{
					$contents .= fread($handle, 8192);
					if ($maxsize AND strlen($contents) > $maxsize)
					{
						$filetolarge = true;
						break;
					}
				}
				fclose($handle);
			}

			if ($filetolarge OR ($maxsize AND strlen($contents) > $maxsize))
			{
				// file size too big
				if ($incp)
				{
					print_stop_message('the_uploaded_image_is_too_big', $maxsize);
				}
				else
				{
					eval(print_standard_error('error_imagetoobig'));
				}
			}
		}
		else
		{
			// could not open file
			if ($incp)
			{
				print_stop_message('error_cannot_find_image_url');
			}
			else
			{
				eval(print_standard_error('error_imagebadurl'));
			}
		}

		// write file to temporary directory...
		if ($vboptions['safeupload'])
		{
			// ... in safe mode
			$filename = $vboptions['tmppath'] . "/$tmp_name";
			$filenum = @fopen($filename, 'wb');
			@fwrite($filenum, $contents);
			@fclose($filenum);
		}
		else
		{
			// ... in normal mode
			$filename = tempnam(ini_get('upload_tmp_dir'), 'vbupload');
			$fp = @fopen($filename, 'wb');
			@fwrite($fp, $contents);
			@fclose($fp);
		}
	}
	// uh oh... got neither
	else
	{
		if ($incp)
		{
			print_stop_message('there_has_been_an_error_in_the_upload');
		}
		else
		{
			eval(print_standard_error('error_imageuploaderror'));
		}
	}

	// check we have a filename
	if (!file_exists($filename))
	{
		return false;
	}

	// check the image filesize and dimensions
	verify_dimensions($type, $filename, $upload_name, $userinfo);

	// read the file
	$filestuff = @file_get_contents($filename);
	@unlink($filename);

	// insert into database
	build_image($type, $filestuff, $upload_name, $userinfo);

	return true;
}

// ###################### Start checkdimensions #######################
function verify_dimensions($type, $location, $filename, &$userinfo)
{
	global $DB_site, $_FILES;
	global $vboptions, $stylevar;

	$extension_map = array(
		'gif' => '1',
		'jpg' => '2',
		'jpeg'=> '2',
		'jpe' => '2',
		'png' => '3',
	);

	if (VB_AREA == 'ModCP' OR VB_AREA == 'AdminCP')
	{
		$incp = true;
	}

	$imagepermissions = &$userinfo['permissions'];

	if (!file_exists($location))
	{
		if ($incp)
		{
			print_stop_message('there_has_been_an_error_in_the_upload');
		}
		else
		{
			eval(print_standard_error('error_imageuploaderror'));
		}
	}

	// get maximum filesize/dimensions etc.
	$maxwidth = &$imagepermissions[$type . 'maxwidth'];
	$maxheight = &$imagepermissions[$type . 'maxheight'];
	$maxsize = &$imagepermissions[$type . 'maxsize'];

	$validfile = false;
	// Verify that file is playing nice
	$fp = fopen($location, 'rb');
	if ($fp)
	{
		$header = fread($fp, 200);
		fclose($fp);
		if (!preg_match('#<html|<head|<body|<script#si', $header))
		{
			$validfile = true;
		}
	}

	// check valid image
	if ($validfile AND $imginfo = @getimagesize($location))
	{
		if (VB_AREA != 'AdminCP' AND (($maxwidth AND $imginfo[0] > $maxwidth) OR ($maxheight AND $imginfo[1] > $maxheight)))
		{
			@unlink($location);
			if ($incp)
			{
				print_stop_message('the_uploaded_image_is_too_large', $maxwidth, $maxheight);
			}
			else
			{
				eval(print_standard_error('error_imagebaddimensions'));
			}
		}
		$extension = strtolower(file_extension($filename));

		if (($imginfo[2] != 1 AND $imginfo[2] != 2 AND $imginfo[2] != 3) OR $extension_map["$extension"] != $imginfo[2])
		{ // .gif, .jpg, .png
			@unlink($location);
			if ($incp)
			{
				print_stop_message('the_uploaded_file_is_not_valid');
			}
			else
			{
				eval(print_standard_error('error_imagenotimage'));
			}
		}
	}
	else
	{
		@unlink($location);
		if ($incp)
		{
			print_stop_message('the_uploaded_file_is_not_valid');
		}
		else
		{
			eval(print_standard_error('error_imagenotimage'));
		}
	}

	// read file
	$filesize = @filesize($location);
	if ($maxsize AND $filesize > $maxsize AND VB_AREA != 'AdminCP')
	{
		// file size too big
		@unlink($location);
		if ($incp)
		{
			print_stop_message('the_uploaded_image_is_too_big', $maxsize);
		}
		else
		{
			eval(print_standard_error('error_imagetoobig'));
		}
	}

	if ($vboptions['usefileavatar'] AND $type == 'avatar')
	{ // store avatars as files
		@unlink("$vboptions[avatarpath]/avatar$userinfo[userid]_$userinfo[avatarrevision].gif");
		copy($location , "$vboptions[avatarpath]/avatar$userinfo[userid]_" . ($userinfo['avatarrevision'] + 1) . '.gif');
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET avatarrevision = avatarrevision + 1
			WHERE userid = " . intval($userinfo['userid']) . "
		");
	}


	return true;
}

// ###################### Start insertpic #######################
function build_image($type, $filestuff, $upload_name, &$userinfo)
{
	global $DB_site;

	$table = 'custom' . $type;
	$data = $type . 'data';

	if ($exists = $DB_site->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "$table
			WHERE userid = $userinfo[userid]
	"))
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "$table
			SET
			filename = '" . addslashes($upload_name) . "',
			dateline = " . TIMENOW . ",
			$data = '" . $DB_site->escape_string($filestuff) . "',
			filesize = " . strlen($filestuff) . "
			WHERE userid = $userinfo[userid]
		");
	}
	else
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "$table
			(userid, $data, dateline, filename, filesize)
			VALUES
			($userinfo[userid], '" . $DB_site->escape_string($filestuff) . "', " . TIMENOW . ", '" . addslashes($upload_name) . "', " . strlen($filestuff) . ")
		");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15854 $
|| ####################################################################
\*======================================================================*/
?>