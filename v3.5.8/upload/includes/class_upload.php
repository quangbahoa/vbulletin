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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Abstracted class that handles POST data from $_FILES
*
* @package	vBulletin
* @version	$Revision: 15466 $
* @date		$Date: 2006-08-04 11:34:42 -0500 (Fri, 04 Aug 2006) $
*/
class vB_Upload_Abstract
{

	/**
	* Any errors that were encountered during the upload or verification process
	*
	* @var	array
	*/
	var $error = '';

	/**
	* Main registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Image object for verifying and resizing
	*
	* @var	vB_Image
	*/
	var $image = null;

	/**
	* Object for save/delete operations
	*
	* @var	vB_DataManager
	*/
	var $upload = null;

	/**
	* Information about the upload that we are working with
	*
	* @var	array
	*/
	var $data = null;

	/**
	* Width and Height up Uploaded Image
	*
	* @var	array
	*/
	var $imginfo = array();

	/**
	* Maximum size of uploaded file. Set to zero to not check
	*
	* @var	int
	*/
	var $maxuploadsize = 0;

	/**
	* Maximum pixel width of uploaded image. Set to zero to not check
	*
	* @var	int
	*/
	var $maxwidth = 0;

	/**
	* Maximum pixel height of uploaded image. Set to zero to not check
	*
	* @var	int
	*/
	var $maxheight = 0;

	/**
	* Information about user who owns the image being uploaded. Mostly we care about $userinfo['userid']
	*
	* @var	array
	*/
	var $userinfo = array();

	/**
	* Whether to display an error message if the upload forum is sent in empty or invalid (false = Multiple Upload Forms)
	*
	* @var  bool
	*/
	var $emptyfile = true;

	/**
	* Whether or not animated GIFs are allowed to be uploaded
	*
	* @var boolean
	*/
	var $allowanimation = null;

	function vB_Upload_Abstract(&$registry)
	{
		if (!is_subclass_of($this, 'vB_Upload_Abstract'))
		{
			trigger_error('Direct Instantiation of vB_Upload_Abstract prohibited.', E_USER_ERROR);
			return NULL;
		}
		else
		{
			$this->registry =& $registry;
			// change this to save a file as someone else
			$this->userinfo =& $this->registry->userinfo;
		}
	}

	/**
	* Set warning
	*
	* @param	string	Varname of error phrase
	* @param	mixed	Value of 1st variable
	* @param	mixed	Value of 2nd variable
	* @param	mixed	Value of Nth variable
	*/
	function set_warning()
	{
		$args = func_get_args();

		$this->error = call_user_func_array('fetch_error', $args);
	}

	/**
	* Set error state and removes any uploaded file
	*
	* @param	string	Varname of error phrase
	* @param	mixed	Value of 1st variable
	* @param	mixed	Value of 2nd variable
	* @param	mixed	Value of Nth variable
	*/
	function set_error()
	{
		$args = func_get_args();

		$this->error = call_user_func_array('fetch_error', $args);

		if (!empty($this->upload['location']))
		{
			@unlink($this->upload['location']);
		}
	}

	/**
	* Returns the current error
	*
	*/
	function &fetch_error()
	{
		return $this->error;
	}

	/**
	* This function accepts a file via URL or from $_FILES, verifies it, and places it in a temporary location for processing
	*
	* @var	mixed	Valid options are: (a) a URL to a file to retrieve or (b) a pointer to a file in the $_FILES array
	*/
	function accept_upload(&$upload)
	{
		$this->error = '';
		if (!is_array($upload) AND strval($upload) != '')
		{
			$this->upload['extension'] = strtolower(file_extension($upload));

			// Check extension here so we can save grabbing a large file that we aren't going to use
			if (!$this->is_valid_extension($this->upload['extension']))
			{
				$this->set_error('upload_invalid_file');
				return false;
			}

			// Admins can upload any size file
			if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
			{
				$this->maxuploadsize = 0;
			}
			else
			{
				$this->maxuploadsize = $this->fetch_max_uploadsize($this->upload['extension']);
				if (!$this->maxuploadsize)
				{
					$newmem = 20971520;
				}
			}

			if (ini_get('allow_url_fopen') == 0 /*AND !function_exists('curl_init')*/)
			{
				$this->set_error('upload_fopen_disabled');
				return false;
			}
			else if ($filesize = $this->fetch_remote_filesize($upload))
			{
				$filetolarge = false;
				if ($this->maxuploadsize AND $filesize > $this->maxuploadsize)
				{
					$filetolarge = true;
				}
				else
				{
					if (function_exists('memory_get_usage') AND $memory_limit = @ini_get('memory_limit') AND $memory_limit != -1)
					{	// Make sure we have enough memory to process this file
						$memorylimit = vb_number_format($memory_limit, 0, false, null, '');
						$memoryusage = memory_get_usage();
						$freemem = $memorylimit - $memoryusage;
						@ini_set('memory_limit', (!empty($newmem)) ? $freemem + $newmem : $freemem + $filesize);
					}

					// some webservers deny us if we don't have an user_agent
					@ini_set('user_agent', 'PHP');
					if (!($handle = @fopen($upload, 'rb')))
					{
						$this->set_error('retrieval_of_remote_file_failed');
						return false;
					}
					while (!feof($handle))
					{
						$contents .= fread($handle, 8192);
						if ($this->maxuploadsize AND strlen($contents) > $this->maxuploadsize)
						{
							$filetolarge = true;
							break;
						}
					}
					fclose($handle);
				}

				if ($filetolarge)
				{
					$this->set_error('upload_remoteimage_toolarge');
					return false;
				}
			}
			else
			{
				$this->set_error('upload_invalid_url');
				return false;
			}

			// write file to temporary directory...
			if ($this->registry->options['safeupload'])
			{
				// ... in safe mode
				$this->upload['location'] = $this->registry->options['tmppath'] . '/vbupload' . $this->userinfo['userid'] . substr(TIMENOW, -4);
			}
			else
			{
				// ... in normal mode
				$this->upload['location'] = $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'] ? tempnam(ini_get('upload_tmp_dir'), 'vbupload') : @tempnam(ini_get('upload_tmp_dir'), 'vbupload');
			}

			$fp = $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'] ? fopen($this->upload['location'], 'wb') : @fopen($this->upload['location'], 'wb');
			if ($fp AND $this->upload['location'])
			{
				@fwrite($fp, $contents);
				@fclose($fp);
			}
			else
			{
				$this->set_error('upload_writefile_failed');
				return false;
			}

			$this->upload['filesize'] = @filesize($this->upload['location']);
			$this->upload['filename'] = basename($upload);
			$this->upload['extension'] = strtolower(file_extension($this->upload['filename']));
			$this->upload['thumbnail'] = '';
			$this->upload['filestuff'] = '';
			$this->upload['url'] = true;
		}
		else
		{
			$this->upload['filename'] = trim($upload['name']);
			$this->upload['filesize'] = intval($upload['size']);
			$this->upload['location'] = trim($upload['tmp_name']);
			$this->upload['extension'] = strtolower(file_extension($this->upload['filename']));
			$this->upload['thumbnail'] = '';
			$this->upload['filestuff'] = '';

			if ($this->upload['error'] == 4 OR $this->upload['location'] == 'none' OR $this->upload['location'] == '' OR $this->upload['filename'] == '' OR !$this->upload['filesize'] OR !is_uploaded_file($this->upload['location']))
			{
				if ($this->emptyfile OR $this->upload['filename'] != '')
				{
					$this->set_error('upload_file_failed');
				}
				return false;
			}
			else if ($this->upload['error'] AND PHP_VERSION >= '4.2.0')
			{
				// Encountered PHP upload error
				if (!($maxupload = @ini_get('upload_max_filesize')))
				{
					$maxupload = 10485760;
				}
				$maxattachsize = vb_number_format($maxupload, 1, true);

				switch($this->upload['error'])
				{
					case '1': // UPLOAD_ERR_INI_SIZE
					case '2': // UPLOAD_ERR_FORM_SIZE
						$this->set_error('upload_file_exceeds_php_limit', $maxattachsize);
						break;
					case '3': // UPLOAD_ERR_PARTIAL
						$this->set_error('upload_file_partially_uploaded');
						break;
					default:
						$this->set_error('upload_invalid_file');
				}

				return false;
			}

			if ($this->registry->options['safeupload'])
			{
				$temppath = $this->registry->options['tmppath'] . '/' . $this->registry->session->fetch_sessionhash();
				$moveresult = $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'] ? move_uploaded_file($this->upload['location'], $temppath) : @move_uploaded_file($this->upload['location'], $temppath);
				if (!$moveresult)
				{
					$this->set_error('upload_unable_move');
					return false;
				}
				$this->upload['location'] = $temppath;
			}
		}

		return true;

	}

	/**
	* Requests headers of remote file to retrieve size without downloading the file
	*
	* @var	string	URL of remote file to retrieve size from
	*/
	function fetch_remote_filesize($url)
	{
		// since cURL supports any protocol we should check its http(s)
		preg_match('#^((http|ftp)s?):\/\/#i', $url, $check);
		if (ini_get('allow_url_fopen') != 0 AND $check[1] == 'http')
		{
			$urlinfo = @parse_url($url);

			if (empty($urlinfo['port']))
			{
				$urlinfo['port'] = 80;
			}

			if ($fp = @fsockopen($urlinfo['host'], $urlinfo['port'], $errno, $errstr, 30))
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
					if (stristr($header, 'Content-Length') !== false)
					{
						$matches = array();
						preg_match('#(\d+)#', $header, $matches);
						return sprintf('%u', $matches[0]);
					}
				}
			}
		}
		else if (false AND !empty($check) AND function_exists('curl_init') AND $ch = curl_init())
		{
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
			/* Need to enable this for self signed certs, do we want to do that?
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			*/

			$header = curl_exec($ch);
			curl_close($ch);

			if ($header !== false)
			{
				preg_match('#Content-Length: (\d+)#i', $header, $matches);
				return sprintf('%u', $matches[1]);
			}
		}
		return false;
	}

	/**
	* Verifies a valid remote url for retrieval or verifies a valid uploaded file
	*
	*/
	function process_upload() {}

	/**
	* Saves a file that has been verified
	*
	*/
	function save_upload() {}

	/**
	* Public
	* Checks if supplied extension can be used
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*/
	function is_valid_extension()
	{}

	/**
	* Public
	* Returns the maximum filesize for the specified extension
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	integer
	*/
	function fetch_max_uploadsize()
	{}
}

class vB_Upload_Attachment extends vB_Upload_Abstract
{
	/**
	* Information about the forum that this attachment is in
	*
	* @var array
	*/
	var $foruminfo = null;

	/**
	* Information about the post that this attachment belongs to (if applicable)
	*
	* @var array
	*/
	var $postinfo = null;

	function fetch_max_uploadsize($extension)
	{
		if (!empty($this->registry->attachmentcache["$extension"]['size']))
		{
			return $this->registry->attachmentcache["$extension"]['size'];
		}
		else
		{
			return 0;
		}
	}

	function is_valid_extension($extension)
	{
		return !empty($this->registry->attachmentcache["$extension"]);
	}

	function process_upload($uploadstuff = '')
	{
		if ($this->registry->attachmentcache === null)
		{
			trigger_error('vB_Upload_Attachment: Attachment cache not specfied. Can not continue.', E_USER_ERROR);
		}

		if ($this->accept_upload($uploadstuff))
		{
			// Verify Extension is proper
			if (empty($this->registry->attachmentcache["{$this->upload['extension']}"]))
			{
				$this->set_error('upload_invalid_file');
				return false;
			}

			$this->maxuploadsize = $this->fetch_max_uploadsize($this->upload['extension']);
			if ($this->maxuploadsize > 0 AND $this->upload['filesize'] > $this->maxuploadsize)
			{
				$this->set_error('upload_file_exceeds_forum_limit', vb_number_format($this->upload['filesize'], 1, true), vb_number_format($this->maxuploadsize, 1, true));
				return false;
			}
			// is this a filetype that can be processed as an image?
			if ($this->image->is_valid_info_extension($this->upload['extension']))
			{
				$this->maxwidth = $this->registry->attachmentcache["{$this->upload['extension']}"]['width'];
				$this->maxheight = $this->registry->attachmentcache["{$this->upload['extension']}"]['height'];

				if ($this->imginfo = $this->image->fetch_image_info($this->upload['location']))
				{
					if (!$this->imginfo[2])
					{
						$this->set_error('upload_invalid_image');
						return false;
					}

					if ($this->image->fetch_imagetype_from_extension($this->upload['extension']) != $this->imginfo[2])
					{
						$this->set_error('upload_invalid_image_extension', $this->imginfo[2]);
						return false;
					}

					if (($this->maxwidth > 0 AND $this->imginfo[0] > $this->maxwidth) OR ($this->maxheight > 0 AND $this->imginfo[1] > $this->maxheight))
					{
						$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
						return false;
					}
				}
				else if ($this->upload['extension'] != 'pdf')
				{	// don't error on .pdf imageinfo failures
					if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						$this->set_error('upload_imageinfo_failed_x', htmlspecialchars_uni($this->image->fetch_error()));
					}
					else
					{
						$this->set_error('upload_invalid_image');
					}
					return false;
				}

				// Generate Thumbnail
				if ($this->registry->attachmentcache["{$this->upload['extension']}"]['thumbnail'] AND $this->registry->options['attachthumbs'])
				{
					$this->upload['thumbnail'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $this->registry->options['attachthumbssize'], $this->registry->options['attachthumbssize'], $this->registry->options['thumbquality']);
					if (empty($this->upload['thumbnail']['filedata']) AND !empty($this->upload['thumbnail']['imageerror']) AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						if (($error = $this->image->fetch_error()) !== false AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
						{
							$this->set_warning('thumbnail_failed_x', htmlspecialchars_uni($error));
						}
						else
						{
							$this->set_warning($this->upload['thumbnail']['imageerror']);
						}
					}
				}
			}

			if (!$this->check_attachment_overage())
			{
				return false;
			}
			if (!($this->upload['filestuff'] = @file_get_contents($this->upload['location'])))
			{
				$this->set_error('upload_file_failed');
				return false;
			}
			@unlink($this->upload['location']);
			return $this->save_upload();
		}
		else
		{
			return false;
		}
	}

	function check_attachment_overage()
	{
		if ($this->registry->options['attachtotalspace'])
		{
			$attachdata = $this->registry->db->query_first("SELECT SUM(filesize) AS sum FROM " . TABLE_PREFIX . "attachment");
			if (($attachdata['sum'] + $this->upload['filesize']) > $this->registry->options['attachtotalspace'])
			{
				$overage = vb_number_format($attachdata['sum'] + $this->upload['filesize'] - $this->registry->options['attachtotalspace'], 1, true);
				$admincpdir = $this->registry->config['Misc']['admincpdir'];

				eval(fetch_email_phrases('attachfull', 0));
				vbmail($this->registry->options['webmasteremail'], $subject, $message);

				$this->set_error('upload_attachfull_total', $overage);
				return false;
			}
		}

		if ($this->userinfo['permissions']['attachlimit'])
		{
			// Get forums that allow canview access
			foreach ($this->userinfo['forumpermissions'] AS $forumid => $fperm)
			{
				if (($fperm & $this->registry->bf_ugp_forumpermissions['canview']) AND ($fperm & $this->registry->bf_ugp_forumpermissions['canviewthreads']) AND ($fperm & $this->registry->bf_ugp_forumpermissions['cangetattachment']))
				{
					$forumids .= ",$forumid";
				}
			}

			$attachdata = $this->registry->db->query_first("
				SELECT SUM(attachment.filesize) AS sum
				FROM " . TABLE_PREFIX . "attachment AS attachment
				LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
				LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
				WHERE attachment.userid = " . $this->userinfo['userid'] . "
					AND	((forumid IN(0$forumids) AND post.visible <> 2 AND thread.visible <> 2) OR attachment.postid = 0)
			");
			if (($attachdata['sum'] + $this->upload['filesize']) > $this->userinfo['permissions']['attachlimit'])
			{
				$overage = vb_number_format($attachdata['sum'] + $this->upload['filesize'] - $this->userinfo['permissions']['attachlimit'], 1, true);

				$this->set_error('upload_attachfull_user', $overage, $this->registry->session->vars['sessionurl']);
				return false;
			}
		}

		if (!$this->registry->options['allowduplicates'])
		{
			// read file
			$filehash = md5(@file_get_contents($this->upload['location']));

			if ($threadresult = $this->registry->db->query_first("
				SELECT post.postid, post.threadid, thread.title, posthash, attachment.filename
				FROM " . TABLE_PREFIX . "attachment AS attachment
				LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
				LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
				WHERE attachment.userid = " . $this->userinfo['userid'] . "
					AND attachment.filehash = '" . $this->registry->db->escape_string($filehash) . "'
				LIMIT 1
			"))
			{
				// Attachment of an existing post
				if ($threadresult['postid'])
				{
					if ($this->postinfo['postid'] != $threadresult['postid'] OR $this->upload['filename'] != $threadresult['filename'])
					{	// doesn't belong to our post or the filename differs so it won't be overwritten
						$this->set_error('upload_attachexists', $this->registry->session->vars['sessionurl'], $threadresult['threadid'], $threadresult['title']);
						return false;
					}
				}
				else
				{	// Attachment currently being added or abandoned
					if ($threadresult['posthash'] != $this->postinfo['posthash'])
					{	// Doesn't belong to our post
						if ($this->userinfo['userid'] == $this->registry->userinfo['userid'])
						{
							$this->set_error('upload_attach_in_progress_delete_here', $this->registry->session->vars['sessionurl']);
						}
						else
						{
							$this->set_error('upload_attach_in_progress', $this->registry->session->vars['sessionurl']);
						}
						return false;
					}
					else if ($this->upload['filename'] != $threadresult['filename'])
					{	// Belongs to our post but has a different filename //-> won't be overwritten so don't allow
						$this->set_error('upload_attach_exists_this_post');
						return false;
					}
				}
			}
		}

		return true;
	}

	function save_upload()
	{
		$this->data->set('dateline', TIMENOW);
		$this->data->set('thumbnail_dateline', TIMENOW);
		if ($this->data->fetch_field('visible') === null)
		{
			if (isset($this->foruminfo['moderateattach']))
			{
				$visible = ((!$this->foruminfo['moderateattach'] OR can_moderate($this->foruminfo['forumid'], 'canmoderateattachments')) ? 1 : 0);
			}
			else
			{
				#default an attachment with no specified visibility to true
				$visible = 1;
			}
			$this->data->set('visible', $visible);
		}
		$this->data->setr('userid', $this->userinfo['userid']);
		$this->data->setr('filename', $this->upload['filename']);
		$this->data->setr('posthash', $this->postinfo['posthash']);
		$this->data->setr_info('filedata', $this->upload['filestuff']);
		$this->data->setr_info('thumbnail', $this->upload['thumbnail']['filedata']);
		$this->data->setr_info('postid', $this->postinfo['postid']);

		// Update an existing attachment of the same name, rather than insert a new one or throw an "Attachment Already Exists" error
		// I don't think this is actually used so ignore it for now
		$this->data->set_info('updateexisting', true);

		if (!($result = $this->data->save()))
		{
			if (empty($this->data->errors[0]) OR !($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				$this->set_error('upload_file_failed');
			}
			else
			{
				$this->error =& $this->data->errors[0];
			}
		}

		unset($this->upload);

		return $result;
	}
}

class vB_Upload_Userpic extends vB_Upload_Abstract
{
	function fetch_max_uploadsize($extension)
	{
		return $this->maxuploadsize;
	}

	function is_valid_extension($extension)
	{
		return !empty($this->image->info_extensions["{$this->upload['extension']}"]);
	}

	function process_upload($uploadurl = '')
	{
		if ($uploadurl == '' OR $uploadurl == 'http://www.')
		{
			$uploadstuff =& $this->registry->GPC['upload'];
		}
		else
		{
			if (is_uploaded_file($this->registry->GPC['upload']['tmp_name']))
			{
				$uploadstuff =& $this->registry->GPC['upload'];
			}
			else
			{
				$uploadstuff =& $uploadurl;
			}
		}

		if ($this->accept_upload($uploadstuff))
		{
			if ($this->imginfo = $this->image->fetch_image_info($this->upload['location']))
			{
				if ($this->image->is_valid_resize_extension(file_extension($this->upload['filename'])))
				{

					if (!$this->imginfo[2])
					{
						$this->set_error('upload_invalid_image');
						return false;
					}

					if ($this->image->fetch_imagetype_from_extension($this->upload['extension']) != $this->imginfo[2])
					{
						$this->set_error('upload_invalid_image_extension', $this->imginfo[2]);
						return false;
					}
				}
				else
				{
					$this->set_error('upload_invalid_image');
					return false;
				}

				if ($this->allowanimation === false AND $this->imginfo[2] == 'GIF' AND $this->imginfo['scenes'] > 1)
				{
					$this->set_error('upload_invalid_animatedgif');
					return false;
				}

				if (($this->maxwidth AND $this->imginfo[0] > $this->maxwidth) OR ($this->maxheight AND $this->imginfo[1] > $this->maxheight) OR $this->image->fetch_must_convert($this->imginfo[2]))
				{
					// shrink-a-dink a big fat image or an invalid image for browser display (PSD, BMP, etc)
					$this->upload['thumbnail'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $this->maxwidth, $this->maxheight, $this->registry->options['thumbquality']);
					if (empty($this->upload['thumbnail']['filedata']))
					{
						$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
						return false;
					}
					else
					{
						$this->upload['filesize'] =& $this->upload['thumbnail']['filesize'];
						$this->upload['filestuff'] =& $this->upload['thumbnail']['filedata'];
						$this->imginfo[0] =& $this->upload['thumbnail']['width'];
						$this->imginfo[1] =& $this->upload['thumbnail']['height'];
					}
				}
			}
			else
			{
				if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
				{
					$this->set_error('upload_imageinfo_failed_x', htmlspecialchars_uni($this->image->fetch_error()));
				}
				else
				{
					$this->set_error('upload_invalid_file');
				}
				return false;
			}

			if ($this->maxuploadsize AND $this->upload['filesize'] > $this->maxuploadsize)
			{
				$this->set_error('upload_file_exceeds_forum_limit', vb_number_format($this->upload['filesize'], 1, true), vb_number_format($this->maxuploadsize, 1, true));
				return false;
			}

			if (!$this->upload['filestuff'])
			{
				if (!($this->upload['filestuff'] = @file_get_contents($this->upload['location'])))
				{
					$this->set_error('upload_file_failed');
					return false;
				}
			}
			@unlink($this->upload['location']);

			return $this->save_upload();
		}
		else
		{
			return false;
		}
	}

	function save_upload()
	{

		$this->data->set('userid', $this->userinfo['userid']);
		$this->data->set('dateline', TIMENOW);
		$this->data->set('filename', $this->upload['filename']);
		$this->data->set('width', $this->imginfo[0]);
		$this->data->set('height', $this->imginfo[1]);
		$this->data->setr('filedata', $this->upload['filestuff']);
		$this->data->set_info('avatarrevision', $this->userinfo['avatarrevision']);
		$this->data->set_info('profilepicrevision', $this->userinfo['profilepicrevision']);

		if (!($result = $this->data->save()))
		{
			if (empty($this->data->errors[0]) OR !($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				$this->set_error('upload_file_failed');
			}
			else
			{
				$this->error =& $this->data->errors[0];
			}
		}

		unset($this->upload);

		return $result;
	}
}

class vB_Upload_Image extends vB_Upload_Abstract
{
	/**
	* Path that uploaded image is to be saved to
	*
	* @var	string
	*/
	var $path = '';

	function is_valid_extension($extension)
	{
		return !empty($this->image->info_extensions["{$this->upload['extension']}"]);
	}

	function process_upload($uploadurl = '')
	{
		if ($uploadurl == '' OR $uploadurl == 'http://www.')
		{
			$uploadstuff =& $this->registry->GPC['upload'];
		}
		else
		{
			if (is_uploaded_file($this->registry->GPC['upload']['tmp_name']))
			{
				$uploadstuff =& $this->registry->GPC['upload'];
			}
			else
			{
				$uploadstuff =& $uploadurl;
			}
		}

		if ($this->accept_upload($uploadstuff))
		{
			if ($this->image->is_valid_resize_extension(file_extension($this->upload['filename'])))
			{
				if ($this->imginfo = $this->image->fetch_image_info($this->upload['location']))
				{
					if (!$this->image->fetch_must_convert($this->imginfo[2]))
					{
						if (!$this->imginfo[2])
						{
							$this->set_error('upload_invalid_image');
							return false;
						}

						if ($this->image->fetch_imagetype_from_extension($this->upload['extension']) != $this->imginfo[2])
						{
							$this->set_error('upload_invalid_image_extension', $this->imginfo[2]);
							return false;
						}
					}
					else
					{
						$this->set_error('upload_invalid_image');
						return false;
					}
				}
				else
				{
					$this->set_error('upload_imageinfo_failed_x', htmlspecialchars_uni($this->image->fetch_error()));
					return false;
				}
			}
			else
			{
				$this->set_error('upload_invalid_image');
				return false;
			}

			if (!$this->upload['filestuff'])
			{
				if (!($this->upload['filestuff'] = file_get_contents($this->upload['location'])))
				{
					$this->set_error('upload_file_failed');
					return false;
				}
			}
			@unlink($this->upload['location']);

			return $this->save_upload();
		}
		else
		{
			return false;
		}
	}

	function save_upload()
	{
		if (!is_writable($this->path) OR !($fp = fopen($this->path . '/' . $this->upload['filename'], 'wb')))
		{
			$this->set_error('invalid_file_path_specified');
			return false;
		}

		if (@fwrite($fp, $this->upload['filestuff']) === false)
		{
			$this->set_error('error_writing_x', $this->upload['filename']);
			return false;
		}

		@fclose($fp);
		return $this->path . '/' . $this->upload['filename'];
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15466 $
|| ####################################################################
\*======================================================================*/
?>