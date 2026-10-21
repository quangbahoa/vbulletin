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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* @package	vBulletin
* @version	$Revision: 92875 $
* @date		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
*/
class vB_DataManager_Picture extends vB_DataManager
{
	/**
	* Array of recognized and required fields for album picture inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'pictureid'          => array(TYPE_UINT,       REQ_INCR),
		'userid'             => array(TYPE_UINT,       REQ_YES),
		'caption'            => array(TYPE_NOHTMLCOND, REQ_NO),
		'extension'          => array(TYPE_NOHTMLCOND, REQ_YES,  VF_METHOD),
		'filedata'           => array(TYPE_BINARY,     REQ_NO,   VF_METHOD),
		'filesize'           => array(TYPE_UINT,       REQ_YES),
		'width'              => array(TYPE_UINT,       REQ_NO),
		'height'             => array(TYPE_UINT,       REQ_NO),
		'thumbnail'          => array(TYPE_BINARY,     REQ_NO,   VF_METHOD),
		'thumbnail_dateline' => array(TYPE_UINT,       REQ_NO),
		'thumbnail_filesize' => array(TYPE_UINT,       REQ_NO),
		'thumbnail_width'    => array(TYPE_UINT,       REQ_NO),
		'thumbnail_height'   => array(TYPE_UINT,       REQ_NO),
		'idhash'             => array(TYPE_STR,        REQ_AUTO),
		'state'              => array(TYPE_STR,        REQ_NO,   VF_METHOD),
	);

	/**
	*
	* @var	string  The main table this class deals with
	*/
	var $table = 'picture';

	/**
	* Arrays to store stuff to save to picture-related tables
	*
	* @var	array
	*/
	var $picture = array();

	var $info = array(
		'albums'               => array(),
		'dateline'             => 0,
		'auto_count_update'    => true,
		'have_updated_usercss' => false
	);

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('pictureid = %1$d', 'pictureid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		if (!is_subclass_of($this, 'vB_DataManager_Picture'))
		{
			trigger_error("Direct Instantiation of vB_DataManager_Picture class prohibited.", E_USER_ERROR);
		}

		parent::__construct($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('picturedata_start')) ? eval($hook) : false;
	}

	/**
	 * Makes the extension lowercase
	 *
	 * @param	string	The Extension
	 *
	 * @return	true
	 */
	function verify_extension(&$extension)
	{
		$extension = strtolower($extension);
		return true;
	}

	/**
	* Set the visible state of the picture
	*
	* @param	string	State (visible or moderation)
	*
	* @return	boolean
	*/
	function verify_state(&$state)
	{
		if ($state != 'visible' AND $state != 'moderation')
		{
			$state = 'visible';
		}

		return true;
	}

	/**
	* Set the filehash/filesize of the file
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_filedata(&$filedata)
	{
		if (strlen($filedata) > 0)
		{
			$this->set('filesize', strlen($filedata));
		}

		return true;
	}

	/**
	* Set the filesize of the thumbnail
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_thumbnail(&$thumbnail)
	{
		if (strlen($thumbnail) > 0)
		{
			$this->set('thumbnail_filesize', strlen($thumbnail));
		}
		return true;
	}

	/**
	* Any code to run before deleting.
	*
	* @param	Boolean Do the query?
	*
	* @return	true
	*/
	function pre_delete($doquery = true)
	{
		@ignore_user_abort(true);

		return true;
	}

	/**
	 * Code to run before saving
	 *
	 * @param	boolean Do the query?
	 *
	 * @return	boolean	Whether this code executed correctly
	 *
	 */
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->fetch_field('idhash'))
		{
			$this->set('idhash', md5(fetch_random_string()));
		}

		if (!$this->fetch_field('thumbnail_dateline'))
		{
			$this->set('thumbnail_dateline', TIMENOW);
		}

		// Set picture moderated if need be
		if (!$this->condition AND !$this->fetch_field('state'))
		{
			$should_moderate = (
				$this->registry->options['albums_pictures_moderation']
					OR
				!($this->registry->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['picturefollowforummoderation'])
			);

			if ($should_moderate AND !can_moderate(0, 'canmoderatepictures'))
			{
				$this->set('state', 'moderation');
			}
			else
			{
				$this->set('state', 'visible');
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('picturedata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	 * Code to run after saving
	 *
	 * @param	boolean	Do the query?
	 *
	 * @return	boolean	Whether this code executed correctly
	 *
	 */
	function post_save_each($doquery = true)
	{
		$pictureid = intval($this->fetch_field('pictureid'));

		if (!$this->condition AND !empty($this->info['albums']))
		{
			$dateline = (!$this->info['dateline'] ? TIMENOW : $this->info['dateline']);

			$albuminsert = array();
			$albumids = array();
			foreach ($this->info['albums'] AS $album)
			{
				$albumids[] = intval($album['albumid']);
				$albuminsert[] = "(" . intval($album['albumid']) . ", $pictureid, $dateline)";
			}

			$this->registry->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "albumpicture
					(albumid, pictureid, dateline)
				VALUES " . implode(', ', $albuminsert)
			);

			if ($this->info['auto_count_update'])
			{
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "album SET
						" . ($this->fetch_field('state') == 'visible'
							?	"visible = visible + 1, lastpicturedate = IF($dateline > lastpicturedate, $dateline, lastpicturedate)"
							: "moderation = moderation + 1") . "
					WHERE albumid IN (" . implode(',', $albumids) . ")
				");
			}
		}
		else if (($this->existing['state'] == 'moderation') AND ($this->fetch_field('state') == 'visible'))
		{
			$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "albumpicture
					SET dateline = " . TIMENOW . "
					WHERE pictureid = $pictureid
			");
		}

		($hook = vBulletinHook::fetch_hook('picturedata_postsave')) ? eval($hook) : false;
		return parent::post_save_each($doquery);
	}

	/**
	 * Code to run after deleting
	 *
	 * @param	boolean	Do the query?
	 *
	 * @return	boolean	Was this code executed correctly?
	 *
	 */
	function post_delete($doquery = true)
	{
		$albums = array();

		$albums_sql = $this->registry->db->query_read("
			SELECT album.*
			FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
			INNER JOIN " . TABLE_PREFIX . "album AS album ON (album.albumid = albumpicture.albumid)
			WHERE albumpicture.pictureid = " . $this->fetch_field('pictureid')
		);
		while ($album = $this->registry->db->fetch_array($albums_sql))
		{
			$albums[] = $album;
		}

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "albumpicture
			WHERE pictureid = " . $this->fetch_field('pictureid')
		);

		if ($this->info['auto_count_update'] AND $albums)
		{
			foreach ($albums AS $album)
			{
				$albumdata = datamanager_init('Album', $this->registry, ERRTYPE_SILENT);
				$albumdata->set_existing($album);
				$albumdata->rebuild_counts();

				// for a picture to have been set to the cover, it must have been visible
				if ($albumdata->fetch_field('coverpictureid') == $this->fetch_field('pictureid'))
				{
					if ($album['visible'] - 1 > 0)
					{
						$new_cover = $this->registry->db->query_first("
							SELECT albumpicture.pictureid
							FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
							INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (albumpicture.pictureid = picture.pictureid)
							WHERE albumpicture.albumid = $album[albumid] AND picture.state = 'visible'
							ORDER BY albumpicture.dateline ASC
							LIMIT 1
						");
					}

					$albumdata->set('coverpictureid', ($new_cover['pictureid'] ? $new_cover['pictureid']: 0));
				}
				$albumdata->save();
			}
		}

		$del_usercss = array();
		foreach ($albums AS $album)
		{
			$del_usercss[] = "'$album[albumid]," . $this->fetch_field('pictureid') . "'";
		}

		if ($del_usercss)
		{
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "usercss
				WHERE property = 'background_image'
					AND value IN (" . implode(',', $del_usercss) . ")
					AND userid = " . intval($this->fetch_field('userid')) . "
			");
			$this->info['have_updated_usercss'] = ($this->registry->db->affected_rows() > 0);
		}

		$groups = array();

		$groups_sql = $this->registry->db->query_read("
			SELECT DISTINCT socialgroup.*
			FROM " . TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture
			INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (socialgroup.groupid = socialgrouppicture.groupid)
			WHERE socialgrouppicture.pictureid = " . $this->fetch_field('pictureid')
		);
		while ($group = $this->registry->db->fetch_array($groups_sql))
		{
			$groups[] = $group;
		}

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "socialgrouppicture
			WHERE pictureid = " . $this->fetch_field('pictureid')
		);

		foreach ($groups AS $group)
		{
			$groupdata = datamanager_init('SocialGroup', $this->registry, ERRTYPE_SILENT);
			$groupdata->set_existing($group);
			$groupdata->rebuild_picturecount();
			$groupdata->save();
		}

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "picturecomment
			WHERE pictureid = " . $this->fetch_field('pictureid')
		);

		require_once(DIR . '/includes/functions_picturecomment.php');
		build_picture_comment_counters($this->fetch_field('userid'));

		($hook = vBulletinHook::fetch_hook('picturedata_delete')) ? eval($hook) : false;
		return parent::post_delete($doquery);
	}
}

/**
 * Concrete version of the picture DM for database storage.
 *
 * @package vBulletin
 * @version	$Revision: 92875 $
 * @date	$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
 *
 */
class vB_DataManager_Picture_Database extends vB_DataManager_Picture
{
	/**
	 * Code to run before saving
	 *
	 * @param	boolean Do the query?
	 *
	 * @return	boolean	Whether this code executed correctly
	 *
	 */
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$parent = parent::pre_save($doquery);
		if (!$parent)
		{
			return $parent;
		}

		if (!empty($this->info['filedata']))
		{
			$this->setr('filedata', $this->info['filedata']);
		}
		if (!empty($this->info['thumbnail']))
		{
			$this->setr('thumbnail', $this->info['thumbnail']);
		}

		return true;
	}
}

/**
 * Concrete version of the picture DM for filesystem storage.
 *
 * @package vBulletin
 * @version	$Revision: 92875 $
 * @date	$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
 *
 */
class vB_DataManager_Picture_Filesystem extends vB_DataManager_Picture
{
	/**
	 * Code to run before saving
	 *
	 * @param	boolean Do the query?
	 *
	 * @return	boolean	Whether this code executed correctly
	 *
	 */
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$parent = parent::pre_save($doquery);
		if (!$parent)
		{
			return $parent;
		}

		return true;

	}

	/**
	 * Code to run after saving
	 *
	 * @param	boolean	Do the query?
	 *
	 * @return	boolean	Whether this code executed correctly
	 *
	 */
	function post_save_each($doquery = true)
	{
		$failed = false;
		$failed_full = false;

		require_once(DIR . '/includes/functions_album.php');
		$picture = array_merge($this->existing, $this->picture);

		// Check for filedata in an information field
		if (!empty($this->info['filedata']))
		{
			$filename = verify_picture_fs_path($picture, false) ? fetch_picture_fs_path($picture, false) : '';
			if ($filename AND $fp = fopen($filename, 'wb'))
			{
				if (!fwrite($fp, $this->info['filedata']))
				{
					$failed = true;
					$failed_full = true;
				}
				fclose($fp);

				#remove possible existing thumbnail in case no thumbnail is written in the next step.
				$thumb_path = fetch_picture_fs_path($picture, true);
				if (file_exists($thumb_path))
				{
					@unlink($thumb_path);
				}
			}
			else
			{
				$failed = true;
			}
		}

		if (!$failed AND !empty($this->info['thumbnail']))
		{
			// write out thumbnail now
			$filename = verify_picture_fs_path($picture, true) ? fetch_picture_fs_path($picture, true) : '';
			if ($filename AND $fp = fopen($filename, 'wb'))
			{
				if (!fwrite($fp, $this->info['thumbnail']))
				{
					$failed = true;
				}
				fclose($fp);
			}
			else
			{
				$failed = true;
			}
		}

		parent::post_save_each($doquery);

		if ($failed)
		{
			if ($this->condition === null) // Insert, delete attachment
			{
				$this->condition = "pictureid = " . $this->fetch_field('pictureid');
				$this->log = false;
				$this->delete();
			}

			$this->error('upload_copyfailed',
				'',
				$failed_full ? fetch_picture_fs_path($picture) : fetch_picture_fs_path($picture, true)
			);
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{
		require_once(DIR . '/includes/functions_album.php');
		$picture = array_merge($this->existing, $this->picture);

		@unlink(fetch_picture_fs_path($picture));
		@unlink(fetch_picture_fs_path($picture, true));

		return parent::post_delete($doquery);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
