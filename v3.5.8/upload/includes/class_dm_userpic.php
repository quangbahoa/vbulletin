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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
*  vB_DataManager_Avatar
*  vB_DataManager_ProfilePic
* Abstract class to do data save/delete operations for Userpics.
* You should call the fetch_library() function to instantiate the correct
* object based on how userpics are being stored unless calling the multiple
* datamanager. There is no support for manipulating the FS via the multiple
* datamanager at the present.
*
* @package	vBulletin
* @version	$Revision: 12956 $
* @date		$Date: 2005-08-05 13:07:06 -0500 (Fri, 05 Aug 2005) $
*/
class vB_DataManager_Userpic extends vB_DataManager
{

	/**
	* Array of recognized and required fields for avatar inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'userid'   => array(TYPE_UINT,     REQ_YES),
		'filedata' => array(TYPE_NOTRIM,   REQ_NO, VF_METHOD),
		'dateline' => array(TYPE_UNIXTIME, REQ_AUTO),
		'filename' => array(TYPE_STR,      REQ_YES),
		'visible'  => array(TYPE_UINT,     REQ_NO),
		'filesize' => array(TYPE_UINT,     REQ_YES),
		'width'    => array(TYPE_UINT,     REQ_NO),
		'height'   => array(TYPE_UINT,     REQ_NO),
	);

	/**
	*
	* @var	string  The main table this class deals with
	*/
	var $table = 'customavatar';

	/**
	* Revision field to update
	*
	* @var	string
	*/
	var $revision = 'avatarrevision';

	/**
	* Path to image directory
	*
	* @var	string
	*/
	var $filepath = 'customavatars';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('userid = %1$d', 'userid');

	/**
	* Fetches the appropriate subclass based on how the userpics are being stored.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*
	* @return	vB_DataManager_Userpic	Subclass of vB_DataManager_Userpic
	*/
	function &fetch_library(&$registry, $errtype = ERRTYPE_STANDARD, $classtype = 'userpic_avatar')
	{
		// Library
		if ($registry->options['usefileavatar'])
		{
			$newclass =& new vB_DataManager_Userpic_Filesystem($registry, $errtype);
		}
		else
		{
			$class = 'vB_DataManager_' . $classtype;
			$newclass =& new $class($registry, $errtype);
		}

		switch (strtolower($classtype))
		{
			case 'userpic_avatar':
				$newclass->table = 'customavatar';
				$newclass->revision = 'avatarrevision';
				$newclass->filepath =& $registry->options['avatarpath'];
				break;
			case 'userpic_profilepic':
				$newclass->table = 'customprofilepic';
				$newclass->revision = 'profilepicrevision';
				$newclass->filepath =& $registry->options['profilepicpath'];
				break;
		}

		return $newclass;
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
	* Any code to run before deleting.
	*
	* @param	Boolean Do the query?
	*/
	function pre_delete($doquery = true)
	{
		@ignore_user_abort(true);

		return true;
	}

	/**
	*
	*
	*
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition)
		{
			# Check if we need to insert or overwrite this image.
			if ($this->fetch_field('userid') AND $this->registry->db->query_first("
				SELECT userid
				FROM " . TABLE_PREFIX . $this->table . "
				WHERE userid = " . $this->fetch_field('userid'))
			)
			{
				$this->condition = "userid = " . $this->fetch_field('userid');
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('userpicdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function fetch_path($userid, $revision)
	{
		return $this->filepath . "/" . preg_replace("#^custom#si", '', $this->table) . $userid . "_" . $revision . ".gif";
	}
	
	function post_save_each($doquery = true)
	{		
		($hook = vBulletinHook::fetch_hook('userpicdata_postsave')) ? eval($hook) : false;	
		return parent::post_save_each($doquery);
	}

	function post_delete($doquery = true)
	{		
		($hook = vBulletinHook::fetch_hook('userpicdata_delete')) ? eval($hook) : false;
		return parent::post_delete($doquery);
	}

}

class vB_DataManager_Userpic_Avatar extends vB_DataManager_Userpic
{
	function vB_DataManager_Userpic_Avatar(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		$this->table = 'customavatar';
		$this->revision = 'avatarrevision';
		$this->filepath =& $registry->options['avatarpath'];

		parent::vB_DataManager_Userpic($registry, $errtype);
	}
}

class vB_DataManager_Userpic_Profilepic extends vB_DataManager_Userpic
{
	function vB_DataManager_Userpic_Profilepic(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		$this->table = 'customprofilepic';
		$this->revision = 'profilepicrevision';
		$this->filepath =& $registry->options['profilepicpath'];

		parent::vB_DataManager_Userpic($registry, $errtype);
	}
}

class vB_DataManager_Userpic_Filesystem extends vB_DataManager_Userpic
{

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if ($file =& $this->fetch_field('filedata'))
		{
			$this->setr_info('filedata', $file);
			$this->do_unset('filedata');
			$this->set('filesize', strlen($this->info['filedata']));
			if (!is_writable($this->filepath))
			{
				$this->error('upload_invalid_avatarpath');
				return false;
			}
		}

		return parent::pre_save($doquery);
	}

	function post_save_each($doquery = true)
	{
		# Check if revision was passed as an info object or as existing
		if (isset($this->info["{$this->revision}"]))
		{
			$revision = $this->info["{$this->revision}"];
		}
		else if ($this->fetch_field($this->revision) !== null)
		{
			$revision = $this->fetch_field($this->revision);
		}

		// We were given an image and a revision number so write out a new image.
		if (!empty($this->info['filedata']) AND isset($revision))
		{
			if ($filenum = fopen($this->fetch_path($this->fetch_field('userid'), $revision + 1), 'wb'))
			{
				@unlink($this->fetch_path($this->fetch_field('userid'), $revision));
				@fwrite($filenum, $this->info['filedata']);
				@fclose($filenum);

				// init user data manager
				$userdata =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
				$userdata->setr('userid', $this->fetch_field('userid'));
				$userdata->condition = "userid = " . $this->fetch_field('userid');
				$userdata->set($this->revision, $revision + 1);
				$userdata->save();
				unset($userdata);

				($hook = vBulletinHook::fetch_hook('userpicdata_postsave')) ? eval($hook) : false;

				return true;
			}
			else
			{
				($hook = vBulletinHook::fetch_hook('userpicdata_postsave')) ? eval($hook) : false;

				$this->error('upload_invalid_avatarpath');
				return false;
			}
		}
		else
		{
			($hook = vBulletinHook::fetch_hook('userpicdata_postsave')) ? eval($hook) : false;

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

		$users = $this->registry->db->query_read("
			SELECT
				userid, {$this->revision} AS revision
			FROM " . TABLE_PREFIX . "user
			WHERE " . $this->condition
		);
		while ($user = $this->registry->db->fetch_array($users))
		{
			@unlink($this->fetch_path($user['userid'], $user['revision']));
		}

		($hook = vBulletinHook::fetch_hook('userpicdata_delete')) ? eval($hook) : false;

	}

}

/**
* Class to do data update operations for multiple userpics simultaneously
*
* @package	vBulletin
* @version	$Revision: 12956 $
* @date		$Date: 2005-08-05 13:07:06 -0500 (Fri, 05 Aug 2005) $
*/
class vB_DataManager_Userpic_Avatar_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_Userpic_Avatar';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'userid';

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition, $limit = 0, $offset = 0)
	{
		$query = "SELECT userid, dateline, filename, visible, filesize, width, height FROM " . TABLE_PREFIX . "customavatar AS customavatar";
		if ($condition)
		{
			$query .= " WHERE $condition";
		}

		$limit = intval($limit);
		$offset = intval($offset);
		if ($limit)
		{
			$query .= " LIMIT $offset, $limit";
		}

		return $query;
	}
}

class vB_DataManager_Userpic_Profilepic_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_Userpic_Profilepic';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'userid';

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition, $limit = 0, $offset = 0)
	{
		$query = "SELECT userid, dateline, filename, visible, filesize, width, height FROM " . TABLE_PREFIX . "customprofilepic AS customprofilepic";
		if ($condition)
		{
			$query .= " WHERE $condition";
		}

		$limit = intval($limit);
		$offset = intval($offset);
		if ($limit)
		{
			$query .= " LIMIT $offset, $limit";
		}

		return $query;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12956 $
|| ####################################################################
\*======================================================================*/
?>