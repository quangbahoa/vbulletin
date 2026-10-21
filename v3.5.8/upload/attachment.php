<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@ini_set('zlib.output_compression', 'Off');
@set_time_limit(0);
if (@ini_get('output_handler') == 'ob_gzhandler' AND @ob_get_length() !== false)
{	// if output_handler = ob_gzhandler, turn it off and remove the header sent by PHP
	@ob_end_clean();
	header('Content-Encoding:');
}

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'attachment');
define('NOHEADER', 1);
define('NOZIP', 1);
define('NOCOOKIES', 1);
define('NOPMPOPUP', 1);

if ($_GET['stc'] == 1) // we were called as <img src=> from showthread.php
{
	define('NOSHUTDOWNFUNC', 1);
}

// Immediately send back the 304 Not Modified header if this image is cached, don't load global.php
// 3.5.x allows overwriting of attachments so we add the dateline to attachment links to avoid caching
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) OR !empty($_SERVER['HTTP_IF_NONE_MATCH']))
{
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 304 Not Modified');
	}
	else
	{
		header('HTTP/1.1 304 Not Modified');
	}
	// remove the content-type and X-Powered headers to emulate a 304 Not Modified response as close as possible
	header('Content-Type:');
	header('X-Powered-By:');
	if (!empty($_REQUEST['attachmentid']))
	{
		header('Etag: "' . intval($_REQUEST['attachmentid']) . '"');
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

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'attachmentid'	=> TYPE_UINT,
	'thumb'			=> TYPE_BOOL,
	'postid'		=> TYPE_UINT,
));

$hook_query_fields = $hook_query_joins = $hook_query_where = '';
($hook = vBulletinHook::fetch_hook('attachment_start')) ? eval($hook) : false;

$idname = $vbphrase['attachment'];

$imagetype = !empty($vbulletin->GPC['thumb']) ? 'thumbnail' : 'filedata';

if (!$attachmentinfo = $db->query_first("
	SELECT filename, attachment.postid, attachment.userid, attachmentid,
		" . ((!empty($vbulletin->GPC['thumb'])
			? 'attachment.thumbnail AS filedata, thumbnail_dateline AS dateline, thumbnail_filesize AS filesize,'
			: 'attachment.dateline, SUBSTRING(filedata, 1, 2097152) AS filedata, filesize,')) . "
		attachment.visible, mimetype, thread.forumid, thread.threadid, thread.postuserid,
		post.visible AS post_visible, thread.visible AS thread_visible
		$hook_query_fields
	FROM " . TABLE_PREFIX . "attachment AS attachment
	LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype ON (attachmenttype.extension = attachment.extension)
	LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
	LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
	$hook_query_joins
	WHERE " . ($vbulletin->GPC['postid'] ? "attachment.postid = " . $vbulletin->GPC['postid'] : "attachmentid = " . $vbulletin->GPC['attachmentid']) . "
		$hook_query_where
"))
{
	eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
}

if ($attachmentinfo['postid'] == 0)
{	// Attachment that is in progress but hasn't been finalized
	if ($vbulletin->userinfo['userid'] != $attachmentinfo['userid'] AND !can_moderate($attachmentinfo['forumid'], 'caneditposts'))
	{	// Person viewing did not upload it
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}
	// else allow user to view the attachment (from the attachment manager for example)
}
else
{
	$forumperms = fetch_permissions($attachmentinfo['forumid']);

	$threadinfo = array('threadid' => $attachmentinfo['threadid']); // used for session.inthread
	$foruminfo = array('forumid' => $attachmentinfo['forumid']); // used for session.inforum

	# Block attachments belonging to soft deleted posts and threads
	if (!can_moderate($attachmentinfo['forumid']) AND ($attachmentinfo['post_visible'] == 2 OR $attachmentinfo['thread_visible'] == 2))
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}

	# Block attachments belonging to moderated posts and threads
	if (!can_moderate($attachmentinfo['forumid'], 'canmoderateposts') AND ($attachmentinfo['post_visible'] == 0 OR $attachmentinfo['thread_visible'] == 0))
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment'])  OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($attachmentinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0)))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($attachmentinfo['forumid'], $vbulletin->forumcache["$attachmentinfo[forumid]"]['password']);

	if (!$attachmentinfo['visible'] AND !can_moderate($attachmentinfo['forumid'], 'canmoderateattachments') AND $attachmentinfo['userid'] != $vbulletin->userinfo['userid'])
	{
		eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
	}
}

$extension = strtolower(file_extension($attachmentinfo['filename']));

if ($vbulletin->options['attachfile'])
{
	require_once(DIR . '/includes/functions_file.php');
	if ($vbulletin->GPC['thumb'])
	{
		$attachpath = fetch_attachment_path($attachmentinfo['userid'], $attachmentinfo['attachmentid'], true);
	}
	else
	{
		$attachpath = fetch_attachment_path($attachmentinfo['userid'], $attachmentinfo['attachmentid']);
	}

	if ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	{
		if (!($fp = fopen($attachpath, 'rb')))
		{
			exit;
		}
	}
	else if (!($fp = @fopen($attachpath, 'rb')))
	{
		$filedata = base64_decode('R0lGODlhAQABAIAAAMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
		$filesize = strlen($filedata);
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');             // Date in the past
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
		header('Cache-Control: no-cache, must-revalidate');           // HTTP/1.1
		header('Pragma: no-cache');                                   // HTTP/1.0
		header("Content-disposition: inline; filename=clear.gif");
		header('Content-transfer-encoding: binary');
		header("Content-Length: $filesize");
		header('Content-type: image/gif');
		echo $filedata;
		exit;
	}
}

// send jpeg header for PDF, BMP, TIF, TIFF, and PSD thumbnails as they are jpegs
if ($vbulletin->GPC['thumb'] AND in_array($extension, array('bmp', 'tif', 'tiff', 'psd', 'pdf')))
{
	$attachmentinfo['filename'] = preg_replace('#.(bmp|tiff?|psd|pdf)$#i', '.jpg', $attachmentinfo['filename']);
	$mimetype = array('Content-type: image/jpeg');
}
else
{
	$mimetype = unserialize($attachmentinfo['mimetype']);
}

header('Cache-control: max-age=31536000');
header('Expires: ' . gmdate("D, d M Y H:i:s", TIMENOW + 31536000) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $attachmentinfo['dateline']) . ' GMT');
header('ETag: "' . $attachmentinfo['attachmentid'] . '"');
// header('Accept-Ranges: bytes');
// Need to add HTTP-RANGE support

// look for entities in the file name, and if found try to convert
// the filename to UTF-8
$filename = $attachmentinfo['filename'];
if (preg_match('~&#([0-9]+);~', $filename))
{
	if (function_exists('iconv'))
	{
		$filename = @iconv($stylevar['charset'], 'UTF-8//IGNORE', $filename);
	}

	$filename = preg_replace(
		'~&#([0-9]+);~e',
		"convert_int_to_utf8('\\1')",
		$filename
	);
	$filename_charset = 'utf-8';
}
else
{
	$filename_charset = $stylevar['charset'];
}

$filename = preg_replace('#[\r\n]#', '', $filename);

// Opera and IE have not a clue about this, mozilla puts on incorrect extensions.
if (is_browser('mozilla'))
{
	$filename = "filename*=" . $filename_charset . "''" . rawurlencode($filename);
	//$filename = "filename==?$stylevar[charset]?B?" . base64_encode($filename) . "?=";
}
else
{
	// other browsers seem to want names in UTF-8
	if ($filename_charset != 'utf-8' AND function_exists('iconv'))
	{
		$filename = @iconv($filename_charset, 'UTF-8//IGNORE', $filename);
	}

	if (is_browser('opera'))
	{
		// Opera does not support encoded file names
		$filename = 'filename="' . str_replace('"', '', $filename) . '"';
	}
	else
	{
		// encode the filename to stay within spec
		$filename = 'filename="' . rawurlencode($filename) . '"';
	}
}

if (in_array($extension, array('jpg', 'jpe', 'jpeg', 'gif', 'png')))
{
	header("Content-disposition: inline; $filename");
	header('Content-transfer-encoding: binary');
}
else
{
	// force txt files to be downloaded because of a possible XSS issue
	header("Content-disposition: attachment; $filename");
}

header('Content-Length: ' . $attachmentinfo['filesize']);

if (is_array($mimetype))
{
	foreach ($mimetype AS $header)
	{
		if (!empty($header))
		{
			header($header);
		}
	}
}
else
{
	header('Content-type: unknown/unknown');
}

($hook = vBulletinHook::fetch_hook('attachment_display')) ? eval($hook) : false;

// update views counter
if (!$vbulletin->GPC['thumb'] AND connection_status() == 0)
{
	if ($vbulletin->options['attachmentviewslive'])
	{
		// doing it as they happen; not using a DM to avoid overhead
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "attachment SET
				counter = counter + 1
			WHERE attachmentid = $attachmentinfo[attachmentid]
		");
	}
	else
	{
		// or doing it once an hour
		$query = "INSERT INTO " . TABLE_PREFIX . "attachmentviews (attachmentid)
			VALUES ($attachmentinfo[attachmentid])
		";
		defined('NOSHUTDOWNFUNC') ? $db->query_write($query) : $db->shutdown_query($query);
	}
}

if ($vbulletin->options['attachfile'])
{
	if (defined('NOSHUTDOWNFUNC'))
	{
		if ($_GET['stc'] == 1)
		{
			$db->close();
		}
		else
		{
			exec_shut_down();
		}
	}

	while (!feof($fp) AND connection_status() == 0)
	{	// You can limit bandwidth by decreasing the integer sent into fread()
		echo @fread($fp, 1048576);
		flush();
	}
	@fclose($fp);
}
else
{
	$count = 1;
	while (!empty($attachmentinfo['filedata']) AND connection_status() == 0)
	{
		echo $attachmentinfo['filedata'];
		flush();

		if (strlen($attachmentinfo['filedata']) == 2097152)
		{
			$startat = (2097152 * $count) + 1;
			$attachmentinfo = $db->query_first("
				SELECT attachmentid, SUBSTRING(filedata, $startat, 2097152) AS filedata
				FROM " . TABLE_PREFIX . "attachment
				WHERE attachmentid = $attachmentinfo[attachmentid]
			");
			$count++;
		}
		else
		{
			$attachmentinfo['filedata'] = '';
		}
	}

	if (defined('NOSHUTDOWNFUNC'))
	{
		if ($_GET['stc'] == 1)
		{
			$db->close();
		}
		else
		{
			exec_shut_down();
		}
	}
}

($hook = vBulletinHook::fetch_hook('attachment_complete')) ? eval($hook) : false;

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15469 $
|| ####################################################################
\*======================================================================*/
?>