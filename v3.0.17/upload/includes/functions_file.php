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

// ###################### Start remote_filesize #######################
// Retrieves the filesize of a remote file without retreiving the file
function remote_filesize($url)
{
	$urlinfo = @parse_url($url);

	if ($urlinfo['scheme'] != 'http')
	{
		return false;
	}
	else if (empty($urlinfo['port']))
	{
		$urlinfo['port'] = 80;
	}

	if ($fp = fsockopen($urlinfo['host'], $urlinfo['port'], $errno, $errstr, 30))
	{
		fwrite($fp, 'HEAD ' . $url . " HTTP/1.1\r\n");
		fwrite($fp, 'HOST: ' . $urlinfo['host'] . "\r\n");
		fwrite($fp, "Connection: close\r\n\r\n");

		while (!feof($fp))
		{
			$headers .= fgets($fp, 4096);
		}
		fclose ($fp);

		$headersarray = explode("\n", $headers);
		foreach($headersarray as $header)
		{
			if (strpos($header, 'Content-Length') === 0)
			{
				$matches = array();
				preg_match('#(\d+)#', $header, $matches);
				return sprintf('%u', $matches[0]);
			}
		}
		return false;
	}
	else
	{
		return false;
	}
}

// ###################### Start checkattachpath #######################
// Returns Attachment path
function fetch_attachment_path($userid, $attachmentid = 0, $thumb = false)
{
	global $vboptions;

	if ($vboptions['attachfile'] == 2) // expanded paths
	{
		$path = $vboptions['attachpath'] . '/' . implode('/', preg_split('//', $userid,  -1, PREG_SPLIT_NO_EMPTY));
	}
	else
	{
		$path = $vboptions['attachpath'] . '/' . $userid;
	}

	if ($attachmentid)
	{
		if ($thumb)
		{
			$path .= '/' . $attachmentid . '.thumb';
		}
		else
		{
			$path .= '/' . $attachmentid . '.attach';
		}
	}

	return $path;
}

// ###################### Start vbmkdir ###############################
// Recursive creation of file path
function vbmkdir($path, $mode = 0777)
{
	if (is_dir($path))
	{
		if (!(is_writable($path)))
		{
			@chmod($path, $mode);
		}
		return true;
	}
	else
	{
		$oldmask = @umask(0);
		$partialpath = dirname($path);
		if (!vbmkdir($partialpath, $mode))
		{
			return false;
		}
		else
		{
			return mkdir($path, $mode);
		}
	}
}

// ###################### Start checkattachpath #######################
// --> If we are saving attachments as files, this checks for the existence of a user's attachment dir
// Returns path on successful verification of existing path or creation of new path
function verify_attachment_path($userid, $attachmentid = 0, $thumb = false)
{
	global $vboptions;

	// Allow userid to be 0 since vB2 allowed guests to post attachments
	$userid = intval($userid);

	$path = fetch_attachment_path($userid);
	if (vbmkdir($path))
	{
		if ($attachmentid)
		{
			if ($thumb)
			{
				$path .= '/' . $attachmentid . '.thumb';
			}
			else
			{
				$path .= '/' . $attachmentid . '.attach';
			}
		}
		return $path;
	}
	else
	{
		return false;
	}
}

// ###################### Start downloadFile #######################
// must be called before outputting anything to the browser
function file_download($filestring, $filename, $filetype)
{
	if (!isset($isIE))
	{
		static $isIE;
		$isIE = iif(is_browser('ie') OR is_browser('opera'), true, false);
	}

	if ($isIE)
	{
		$filetype = 'application/octetstream';
	}
	else
	{
		$filetype = 'application/octet-stream';
	}

	$filetype = preg_replace('#[\r\n]#', '', $filetype);
	$filename = preg_replace('#[\r\n]#', '', $filename);

	header('Content-Type: ' . $filetype);
	header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . strlen($filestring));
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo $filestring;
	exit;
}

// ###################### Start getmaxattachsize #######################
function fetch_max_attachment_size()
{
	if ($temp = @ini_get('upload_max_filesize'))
	{
		if (ereg('[^0-9]', $temp))
		{
			// max attach size is defined in megabytes which doesn't work in the MAX_FILE_SIZE field
			return (intval($temp) * 1048576);
		}
		else
		{
			return $temp;
		}
	}
	else
	{
		return 10485760; // approx 10 megabytes :)
	}
}

// ###################### Start deletefileattachments #######################
function delete_attachment_files(&$ids)
{
	global $DB_site, $vboptions;

	if ($vboptions['attachfile'] AND is_array($ids))
	{
		foreach ($ids AS $attachmentid => $userid)
		{
			@unlink(fetch_attachment_path($userid, $attachmentid));
			@unlink(fetch_attachment_path($userid, $attachmentid, true));
		}
	}
}

// ###################### Start Attachment Upload #######################
function process_upload($moderate = 0, &$upload, &$errors)
{
	global $DB_site;
	global $vboptions, $stylevar, $bbuserinfo, $attachtypes, $permissions, $posthash, $postinfo, $vbphrase;

	$attachment = trim($upload['tmp_name']);
	$attachment_name = trim($upload['name']);
	$attachment_size = trim($upload['size']);

	// Encountered PHP upload error
	if ($upload['error'] AND $upload['error'] < 4 AND PHP_VERSION >= '4.2.0')
	{
		$inimaxattach = fetch_max_attachment_size();
		$maxattachsize = vb_number_format($inimaxattach, 1, true);
		switch($upload['error'])
		{
			case '1': // UPLOAD_ERR_INI_SIZE
			case '2': // UPLOAD_ERR_FORM_SIZE
				eval('$error = "' . fetch_phrase('attachtoobig_php', PHRASETYPEID_ERROR) . '";');
				break;
			case '3': // UPLOAD_ERR_PARTIAL
				eval('$error = "' . fetch_phrase('attachpartial', PHRASETYPEID_ERROR) . '";');
				break;
		}
		$errors[] = array(
			'filename' => $attachment_name,
			'error' => $error
		);
		return false;
	}

	if ($attachment == 'none' OR empty($attachment) OR empty($attachment_name))
	{
		return false;
	}

	$attachment_name2 = strtolower($attachment_name);
	$extension = strtolower(file_extension($attachment_name2));

	if (!$attachtypes["$extension"] OR !$attachtypes["$extension"]['enabled'])
	{
		// invalid extension
		eval('$error = "' . fetch_phrase('attachbadtype', PHRASETYPEID_ERROR) . '";');
		$errors[] = array(
			'filename' => $attachment_name,
			'error' => $error
		);
		return false;
	}

	if (!is_uploaded_file($attachment))
	{ // tsk tsk!
		return false;
	}

	if ($vboptions['attachfile'])
	{
		$attachpath = $vboptions['attachpath'] . '/' . fetch_sessionhash();
		move_uploaded_file($attachment, $attachpath);
		$attachment = $attachpath;
	}
	else if ($vboptions['safeupload'] AND !$vboptions['attachfile'])
	{
		$path = $vboptions['tmppath'] . '/' . fetch_sessionhash();
		move_uploaded_file($attachment, $path);
		$attachment = $path;
	}

	$upload['tmp_name'] = $attachment; // alter the path of the attachment for use in finalizeattachment.

	$filesize = filesize($attachment);

	$maxattachsize = $attachtypes["$extension"]['size'];
	if ($maxattachsize != 0 AND $filesize > $maxattachsize)
	{
		// too big!
		@unlink($attachment);
		$maxattachsize = vb_number_format($maxattachsize, 1, true);
		$filesize = vb_number_format($filesize, 1, true);
		eval('$error = "' . fetch_phrase('attachtoobig', PHRASETYPEID_ERROR) . '";');
		$errors[] = array(
			'filename' => $attachment_name,
			'error' => $error
		);
		return false;
	}

	$extensions = array(
		'gif' => '1',
		'jpg' => '2',
		'jpe' => '2',
		'jpeg'=> '2',
		'png' => '3',
		'swf' => '4',
		'psd' => '5',
		'bmp' => '6',
	);

	if (PHP_VERSION >= '4.2.0')
	{
		$extensions['tiff'] = '7';
		$extensions['tif'] = '7';
	}

	$extension_map = array(
		'gif' => $extensions['gif'],
		'jpg' => $extensions['jpg'],
		'jpeg'=> $extensions['jpg'],
		'jpe' => $extensions['jpg'],
		'png' => $extensions['png'],
		'bmp' => $extensions['bmp'],
		'tif' => $extensions['tif'],
		'tiff'=> $extensions['tif'],
		'psd' => $extensions['psd'],
		'swf' => $extensions['swf'],
	);

	if (!empty($extensions["$extension"]))
	{
		$validfile = false;
		// Verify that file is playing nice
		$fp = fopen($attachment, 'rb');
		if ($fp)
		{
			$header = fread($fp, 200);
			fclose($fp);
			if (!preg_match('#<html|<head|<body|<script#si', $header))
			{
				$validfile = true;
			}
		}

		if ($validfile AND $imginfo = @getimagesize($attachment))
		{
			$maxattachwidth = $attachtypes["$extension"]['width'];
			$maxattachheight = $attachtypes["$extension"]['height'];

			if (($maxattachwidth > 0 AND $imginfo[0] > $maxattachwidth) OR ($maxattachheight > 0 AND $imginfo[1] > $maxattachheight))
			{
				@unlink($attachment);
				eval('$error = "' . fetch_phrase('attachbaddimensions', PHRASETYPEID_ERROR) . '";');
				$errors[] = array(
					'filename' => $attachment_name,
					'error' => $error
				);
				return false;
			}

			if (!$imginfo[2] OR $extension_map["$extension"] != $imginfo[2])
			{
				@unlink($attachment);
				eval('$error = "' . fetch_phrase('attachnotimage', PHRASETYPEID_ERROR) . '";');
				$errors[] = array(
					'filename' => $attachment_name,
					'error' => $error
				);
				return false;
			}
		}
		else
		{
			@unlink($attachment);
			eval('$error = "' . fetch_phrase('attachnotimage', PHRASETYPEID_ERROR) . '";');
			$errors[] = array(
				'filename' => $attachment_name,
				'error' => $error
			);
			return false;
		}
	}

	if ($vboptions['attachtotalspace'])
	{
		$attachdata = $DB_site->query_first("SELECT SUM(filesize) AS sum FROM " . TABLE_PREFIX . "attachment");
		if (($attachdata['sum'] + $filesize) > $vboptions['attachtotalspace'])
		{
			$overage = vb_number_format($attachdata['sum'] + $filesize - $vboptions['attachtotalspace'], 1, true);

			eval(fetch_email_phrases('attachfull', 0));
			vbmail($vboptions['webmasteremail'], $subject, $message);

			@unlink($attachment);
			eval('$error = "' . fetch_phrase('attachfull_total', PHRASETYPEID_ERROR) . '";');
			$errors[] = array(
				'filename' => $attachment_name,
				'error' => $error
			);
			return false;
		}
	}

	if ($postinfo['postid'])
	{
		$user = fetch_userinfo($postinfo['userid']);
		cache_permissions($user, true);
		$perms = $user['forumpermissions'];
		$attachlimit = $user['permissions']['attachlimit'];
	}
	else
	{
		$perms = $bbuserinfo['forumpermissions'];
		$attachlimit = $permissions['attachlimit'];
	}


	if ($attachlimit)
	{
		// Get forums that allow canview access
		foreach ($perms AS $forumid => $fperm)
		{
			if (($fperm & CANVIEW) AND ($fperm & CANGETATTACHMENT))
			{
				$forumids .= ",$forumid";
			}
		}

		$attachdata = $DB_site->query_first("
			SELECT SUM(attachment.filesize) AS sum
			FROM " . TABLE_PREFIX . "attachment AS attachment
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (attachment.postid = deletionlog.primaryid AND type = 'post')
			WHERE attachment.userid = " . iif($postinfo['postid'], $postinfo['userid'], $bbuserinfo['userid']) . "
				AND	((forumid IN(0$forumids) AND deletionlog.primaryid IS NULL) OR attachment.postid = 0)
		");
		if (($attachdata['sum'] + $filesize) > $attachlimit)
		{
			$overage = vb_number_format($attachdata['sum'] + $filesize - $attachlimit, 1, true);
			@unlink($attachment);
			eval('$error = "' . fetch_phrase('attachfull_user', PHRASETYPEID_ERROR) . '";');
			$errors[] = array(
				'filename' => $attachment_name,
				'error' => $error
			);
			return false;
		}
	}

	if (!$vboptions['allowduplicates'])
	{
		// read file
		$filehash = md5(@file_get_contents($attachment));

		$threadresult = $DB_site->query_first("
			SELECT post.postid, post.threadid, thread.title, posthash
			FROM " . TABLE_PREFIX . "attachment AS attachment
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
			WHERE attachment.userid = " . iif($postinfo['postid'], $postinfo['userid'], $bbuserinfo['userid']) . "
				AND attachment.filehash = '$filehash'
			LIMIT 1
		");

		if ($threadresult)
		{
			@unlink($attachment);
			if ($threadresult['postid'])
			{
				eval('$error = "' . fetch_phrase('attachexists', PHRASETYPEID_ERROR) . '";');
			}
			else
			{
				// Create a phrase for this
				if ($threadresult['posthash'] == $posthash)
				{
					eval('$error = "' . fetch_phrase('attachexiststhispost', PHRASETYPEID_ERROR) . '";');
				}
				else
				{
					eval('$error = "' . fetch_phrase('attachexistsinprogress', PHRASETYPEID_ERROR) . '";');
				}
			}
			$errors[] = array(
				'filename' => $attachment_name,
				'error' => $error
			);
			return false;
		}
	}

	return true;
}

// ###################### Start finalizeattachment #######################
function build_attachment($moderate, &$upload, &$errors)
{
	global $vboptions, $DB_site, $bbuserinfo, $posthash, $postinfo, $errors, $permissions;

	$attachment = trim($upload['tmp_name']);
	$attachment_name = trim($upload['name']);
	$attachment_size = trim($upload['size']);

	if (!file_exists($attachment) OR !($filestuff = @file_get_contents($attachment)))
	{ // sanity check
		return;
	}

	$visible = iif($moderate, 0, 1);

	// read file
	$filesize = filesize($attachment);

	// ### Thumbnail Generation
	if ($vboptions['attachthumbs'] AND $vboptions['gdversion'])
	{
		require_once('./includes/functions_image.php');
		$thumbnail = fetch_thumbnail_from_image($upload);

		// Display thumbnail error to admins in an attempt to cut down on support requests due to failed thumbnails.
		if (empty($thumbnail['filedata']) AND !empty($thumbnail['imageerror']) AND $permissions['adminpermissions'] & CANCONTROLPANEL)
		{
			eval('$error = "' . fetch_phrase($thumbnail['imageerror'], PHRASETYPEID_ERROR) . '";');
			$errors[] = array(
				'filename' => $attachment_name,
				'error' => $error
			);
		}
	}
	else
	{
		$thumbnail = array(
			'filedata' => '',
			'dateline' => 0,
			'filesize' => 0,
		);
	}

	$posterid = iif($postinfo['postid'], $postinfo['userid'], $bbuserinfo['userid']);
	if (!$vboptions['attachfile'])
	{
		// add to db
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "attachment
				(userid, dateline, filename, filedata, filesize, visible, filehash, posthash, thumbnail, thumbnail_dateline, thumbnail_filesize)
			VALUES
				($posterid, " . TIMENOW . ", '" . addslashes($attachment_name) . "',
				 '" . $DB_site->escape_string($filestuff) . "', $filesize, $visible, '" . addslashes(md5($filestuff)) . "', '" . addslashes($posthash) . "', '$thumbnail[filedata]', $thumbnail[dateline], $thumbnail[filesize])
		");
	}
	else
	{
		if ($path = verify_attachment_path($posterid))
		{
			if (is_writable($path))
			{
				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "attachment
						(userid, dateline, filename, filesize, visible, filehash, posthash, thumbnail_dateline, thumbnail_filesize)
					VALUES
						($posterid, " . TIMENOW . ", '" . addslashes($attachment_name) . "', $filesize, $visible, '" . addslashes(md5($filestuff)) . "', '" . addslashes($posthash) . "', $thumbnail[dateline], $thumbnail[filesize])
				");
				$attachmentid = $DB_site->insert_id();

				if (!(@copy($attachment, fetch_attachment_path($posterid, $attachmentid))))
				{
					$DB_site->query("DELETE FROM " . TABLE_PREFIX . "attachment WHERE attachmentid = $attachmentid");
					eval('$error = "' . fetch_phrase('attachcopyfailed', PHRASETYPEID_ERROR) . iif($php_errormsg, "(<b>PHP :</b> " . htmlspecialchars_uni($php_errormsg)) . ')";');
					$errors[] = array(
						'filename' => $attachment_name,
						'error' => $error
					);
					@unlink($attachment);
					return false;
				}
				else if (!empty($thumbnail['filedata']))
				{
					// write out thumbnail now
					$filename = fetch_attachment_path($posterid, $attachmentid, true);
					$fp = fopen($filename, 'wb');
					fwrite($fp, $thumbnail['filedata']);
					fclose($fp);
					unset($thumbnail);
				}
			}
			else
			{
				@unlink($attachment);
				eval('$error = "' . fetch_phrase('attachwritefailed', PHRASETYPEID_ERROR) . '";');
				$errors[] = array(
					'filename' => $attachment_name,
					'error' => $error
				);
				return false;
			}
		}
		else
		{
			@unlink($attachment);
			eval('$error = "' . fetch_phrase('attachpathfailed', PHRASETYPEID_ERROR) . '";');
			$errors[] = array(
				'filename' => $attachment_name,
				'error' => $error
			);
			return false;
		}
	}

	@unlink($attachment);
	return true;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15854 $
|| ####################################################################
\*======================================================================*/
?>