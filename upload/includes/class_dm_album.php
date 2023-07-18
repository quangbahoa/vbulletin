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
* Class to do data save/delete operations for albums
*
* @package	vBulletin
* @version	$Revision: 92875 $
* @date		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
*/
class vB_DataManager_Album extends vB_DataManager
{
	/**
	* Array of recognised and required fields for RSS feeds, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'albumid'         => array(TYPE_UINT,       REQ_INCR, 'return ($data > 0);'),
		'userid'          => array(TYPE_UINT,       REQ_YES),
		'createdate'      => array(TYPE_UNIXTIME,   REQ_AUTO),
		'lastpicturedate' => array(TYPE_UNIXTIME,   REQ_NO),
		'visible'         => array(TYPE_UINT,       REQ_NO),
		'moderation'      => array(TYPE_UINT,       REQ_NO),
		'title'           => array(TYPE_NOHTMLCOND, REQ_YES, VF_METHOD),
		'description'     => array(TYPE_NOHTMLCOND, REQ_NO),
		'state'           => array(TYPE_STR,        REQ_NO, 'if (!in_array($data, array(\'public\', \'private\', \'profile\'))) { $data = \'public\'; } return true; '),
		'coverpictureid'  => array(TYPE_UINT,       REQ_NO)
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'album';

	/**
	* Arrays to store stuff to save to album-related tables
	*
	* @var	array
	*/
	var $album = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('albumid = %1$d', 'albumid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('albumdata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies that the title of the album is valid. Errors if not valid.
	*
	* @param	string	Title of album
	*
	* @return	boolean	True if valid
	*/
	function verify_title(&$title)
	{
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);
		$title = trim($title);

		if ($title === '')
		{
			$this->error('album_title_no_empty');
			return false;
		}

		return true;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition)
		{
			// setup some default values for fields if we didn't explicitly set them
			if (empty($this->setfields['createdate']))
			{
				$this->set('createdate', TIMENOW);
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('albumdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		if ($this->condition AND $this->fetch_field('state') == 'private' AND $this->existing['state'] != 'private')
		{
			// making an existing album non-public, get rid of bg images
			$this->remove_usercss_background_image();
		}

		($hook = vBulletinHook::fetch_hook('albumdata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$pictures = array();
		$picture_sql = $this->registry->db->query_read("
			SELECT albumpicture.pictureid, picture.idhash, picture.extension
			FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
			LEFT JOIN " . TABLE_PREFIX . "picture AS picture ON (albumpicture.pictureid = picture.pictureid)
			WHERE albumpicture.albumid = " . $this->fetch_field('albumid')
		);
		while ($picture = $this->registry->db->fetch_array($picture_sql))
		{
			$pictures["$picture[pictureid]"] = $picture;
		}
		$this->registry->db->free_result($picture_sql);

		if ($pictures)
		{
			if ($this->registry->options['album_dataloc'] != 'db')
			{
				// remove from fs
				foreach ($pictures AS $picture)
				{
					@unlink(fetch_picture_fs_path($picture));
					@unlink(fetch_picture_fs_path($picture, true));
				}
			}

			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "picture
				WHERE pictureid IN (" . implode(',', array_keys($pictures)) . ")
			");


			// delete based on picture id as this means that when a picture is deleted,
			// it's removed from all albums automatically
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "albumpicture
				WHERE pictureid IN (" . implode(',', array_keys($pictures)) . ")
			");

			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "picturecomment
				WHERE pictureid IN (" . implode(',', array_keys($pictures)) . ")
			");

			require_once(DIR . '/includes/functions_picturecomment.php');
			build_picture_comment_counters($this->fetch_field('userid'));

			$groups = array();

			$groups_sql = $this->registry->db->query_read("
				SELECT DISTINCT socialgroup.*
				FROM " . TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture
				INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (socialgroup.groupid = socialgrouppicture.groupid)
				WHERE socialgrouppicture.pictureid IN (" . implode(',', array_keys($pictures)) . ")
			");
			while ($group = $this->registry->db->fetch_array($groups_sql))
			{
				$groups[] = $group;
			}
			$this->registry->db->free_result($groups_sql);

			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "socialgrouppicture
				WHERE pictureid IN (" . implode(',', array_keys($pictures)) . ")
			");

			foreach ($groups AS $group)
			{
				$groupdata = datamanager_init('SocialGroup', $this->registry, ERRTYPE_SILENT);
				$groupdata->set_existing($group);
				$groupdata->rebuild_picturecount();
				$groupdata->save();
				unset($groupdata);
			}
		}

		$this->remove_usercss_background_image();

		($hook = vBulletinHook::fetch_hook('albumdata_delete')) ? eval($hook) : false;

		return true;
	}


	/**
	 * Removes a Background Image from a customised UserCSS
	 *
	 */
	function remove_usercss_background_image()
	{
		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "usercss
			WHERE property = 'background_image'
				AND value LIKE '" . $this->fetch_field('albumid') . ",%'
				AND userid = " . intval($this->fetch_field('userid')) . "
		");
		if ($this->registry->db->affected_rows() AND $this->fetch_field('userid'))
		{
			require_once(DIR . '/includes/class_usercss.php');
			$usercss = new vB_UserCSS($this->registry, $this->fetch_field('userid'), false);
			$usercss->update_css_cache();
		}
	}

	/**
	 * Rebuilds counts for an album
	 *
	 */
	function rebuild_counts()
	{
		if (!$this->fetch_field('albumid'))
		{
			return;
		}

		$counts = $this->registry->db->query_first("
			SELECT
				SUM(IF(picture.state = 'visible', 1, 0)) AS visible,
				SUM(IF(picture.state = 'moderation', 1, 0)) AS moderation,
				MAX(IF(picture.state = 'visible', albumpicture.dateline, 0)) AS lastpicturedate
			FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
			INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (albumpicture.pictureid = picture.pictureid)
			WHERE albumpicture.albumid = " . $this->fetch_field('albumid') . "

		");

		$this->set('visible', $counts['visible']);
		$this->set('moderation', $counts['moderation']);
		$this->set('lastpicturedate', $counts['lastpicturedate']);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
