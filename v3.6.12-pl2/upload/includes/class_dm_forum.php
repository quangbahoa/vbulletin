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

if (!class_exists('vB_DataManager'))
{
	exit;
}

// required for convert_to_valid_html() and others
require_once(DIR . '/includes/adminfunctions.php');

/**
* Class to do data save/delete operations for FORUMS
*
* Example usage (updates forum with forumid = 12):
*
* $f = new vB_DataManager_Forum();
* $f->set_condition('forumid = 12');
* $f->set_info('forumid', 12);
* $f->set('parentid', 5);
* $f->set('title', 'Forum with changed parent');
* $f->save();
*
* @package	vBulletin
* @version	$Revision: 17682 $
* @date		$Date: 2007-07-23 11:36:10 -0500 (Mon, 23 Jul 2007) $
*/
class vB_DataManager_Forum extends vB_DataManager
{
	/**
	* Array of recognised and required fields for forums, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'forumid'           => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'styleid'           => array(TYPE_INT,        REQ_NO,   'if ($data < 0) { $data = 0; } return true;'),
		'title'             => array(TYPE_STR,        REQ_YES,  VF_METHOD),
		'title_clean'       => array(TYPE_STR,        REQ_YES),
		'description'       => array(TYPE_STR,        REQ_NO,   VF_METHOD),
		'description_clean' => array(TYPE_STR,        REQ_NO),
		'options'           => array(TYPE_ARRAY_BOOL, REQ_AUTO),
		'displayorder'      => array(TYPE_UINT,       REQ_NO),
		'replycount'        => array(TYPE_UINT,       REQ_NO),
		'lastpost'          => array(TYPE_UINT,       REQ_NO),
		'lastposter'        => array(TYPE_STR,        REQ_NO),
		'lastpostid'        => array(TYPE_UINT,       REQ_NO),
		'lastthread'        => array(TYPE_STR,        REQ_NO),
		'lastthreadid'      => array(TYPE_UINT,       REQ_NO),
		'lasticonid'        => array(TYPE_INT,        REQ_NO),
		'threadcount'       => array(TYPE_UINT,       REQ_NO),
		'daysprune'         => array(TYPE_INT,        REQ_AUTO, 'if ($data == 0) { $data = -1; } return true;'),
		'newpostemail'      => array(TYPE_STR,        REQ_NO,   VF_METHOD, 'verify_emaillist'),
		'newthreademail'    => array(TYPE_STR,        REQ_NO,   VF_METHOD, 'verify_emaillist'),
		'parentid'          => array(TYPE_INT,        REQ_YES,  VF_METHOD),
		'password'          => array(TYPE_NOTRIM,     REQ_NO),
		'link'              => array(TYPE_STR,        REQ_NO), // do not use verify_link on this -- relative redirects are prefectly valid
		'parentlist'        => array(TYPE_STR,        REQ_AUTO, 'return preg_match(\'#^(\d+,)*-1$#\', $data);'),
		'childlist'         => array(TYPE_STR,        REQ_AUTO),
		'showprivate'       => array(TYPE_UINT,       REQ_NO,   'if ($data > 3) { $data = 0; } return true;'),
		'defaultsortfield'  => array(TYPE_STR,        REQ_NO),
		'defaultsortorder'  => array(TYPE_STR,        REQ_NO,   'if ($data != "asc") { $data = "desc"; } return true;')
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	* For example: var $bitfields = array('options' => 'bf_misc_useroptions', 'permissions' => 'bf_misc_moderatorpermissions')
	*
	* @var	array
	*/
	var $bitfields = array('options' => 'bf_misc_forumoptions');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'forum';

	/**
	* Array to store stuff to save to forum table
	*
	* @var	array
	*/
	var $forum = array();

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('forumid = %1$d', 'forumid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Forum(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('forumdata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies that the given forum title is valid
	*
	* @param	string	Title
	*
	* @return	boolean
	*/
	function verify_title(&$title)
	{
		$this->set('title_clean', htmlspecialchars_uni(strip_tags($title), false));
		$title = convert_to_valid_html($title);


		if ($title == '')
		{
			$this->error('invalid_title_specified');
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Converts & to &amp; and sets description_clean for use in meta tags
	*
	* @param	string	Title
	*
	* @return	boolean
	*/
	function verify_description(&$description)
	{
		$this->set('description_clean', htmlspecialchars_uni(strip_tags($description), false));
		$description = convert_to_valid_html($description);

		return true;
	}

	/**
	* Converts an array of 1/0 options into the options bitfield
	*
	* @param	array	Array of 1/0 values keyed with the bitfield names for the forum options bitfield
	*
	* @return	boolean	Returns true on success
	*/
	function verify_options(&$options)
	{
		#require_once(DIR . '/includes/functions_misc.php');
		#return $options = convert_array_to_bits($options, $this->registry->bf_misc_forumoptions);
		trigger_error("Can't set \$this->forum[options] directly - use \$this->set_bitfield('options', $bitname, $onoff) instead", E_USER_ERROR);
	}

	/**
	* Validates a space-separated list of email addresses, prevents duplicates etc.
	*
	* @param	string	Whitespace-separated list of email addresses
	*
	* @return	boolean
	*/
	function verify_emaillist(&$emails)
	{
		$emaillist = array();

		foreach (preg_split('#\s+#s', $emails, -1, PREG_SPLIT_NO_EMPTY) AS $email)
		{
			if ($this->verify_email($email))
			{
				$emaillist["$email"] = $email;
			}
		}

		$emails = implode(' ', $emaillist);

		return true;
	}

	/**
	* Verifies that the parent forum specified exists and is a valid parent for this forum
	*
	* @param	integer	Parent forum ID
	*
	* @return	boolean	Returns true if the parent id is valid, and the parent forum specified exists
	*/
	function verify_parentid(&$parentid)
	{
		if ($parentid == $this->fetch_field('forumid'))
		{
			$this->error('cant_parent_forum_to_self');
			return false;
		}
		else if ($parentid <= 0)
		{
			$parentid = -1;
			return true;
		}
		else if (!isset($this->registry->forumcache["$parentid"]))
		{
			$this->error('invalid_forum_specified');
			return false;
		}
		else if ($this->condition !== null)
		{
			return $this->is_subforum_of($this->fetch_field('forumid'), $parentid);
		}
		else
		{
			// no condition specified, so it's not an existing forum...
			return true;
		}
	}

	/**
	* Verifies that a given forum parent id is not one of its own children
	*
	* @param	integer	The ID of the current forum
	* @param	integer	The ID of the forum's proposed parentid
	*
	* @return	boolean	Returns true if the children of the given parent forum does not include the specified forum... or something
	*/
	function is_subforum_of($forumid, $parentid)
	{
		if (empty($this->registry->iforumcache))
		{
			cache_ordered_forums(0, 1);
		}

		if (is_array($this->registry->iforumcache["$forumid"]))
		{
			foreach ($this->registry->iforumcache["$forumid"] AS $curforumid)
			{
				if ($curforumid == $parentid OR !$this->is_subforum_of($curforumid, $parentid))
				{
					$this->error('cant_parent_forum_to_child');
					return false;
				}
			}
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

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('forumdata_presave')) ? eval($hook) : false;

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
		if ($this->condition AND $this->info['applypwdtochild'] AND isset($this->forum['password']) AND $this->forum['password'] != $this->existing['password'])
		{
			$this->dbobject->query_write("
                 UPDATE " . TABLE_PREFIX . "forum
                 SET password = '" . $this->dbobject->escape_string($this->forum['password']) . "'
                 WHERE FIND_IN_SET('" . $this->existing['forumid'] . "', parentlist)
            ");
		}

		($hook = vBulletinHook::fetch_hook('forumdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed once after all records are updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		if (empty($this->info['disable_cache_rebuild']))
		{
			require_once(DIR . '/includes/adminfunctions.php');
			build_forum_permissions();
		}
	}

	/**
	* Deletes a forum and its associated data from the database
	*/
	function delete()
	{
		// fetch list of forums to delete
		$forumlist = '';

		$forums = $this->dbobject->query_read_slave("SELECT forumid FROM " . TABLE_PREFIX . "forum WHERE " . $this->condition);
		while($thisforum = $this->dbobject->fetch_array($forums))
		{
			$forumlist .= ',' . $thisforum['forumid'];
		}
		$this->dbobject->free_result($forums);

		$forumlist = substr($forumlist, 1);

		if ($forumlist == '')
		{
			// nothing to do
			$this->error('invalid_forum_specified');
		}
		else
		{
			$condition = "forumid IN ($forumlist)";

			// delete from extra data tables
			$this->db_delete(TABLE_PREFIX, 'forumpermission', $condition);
			$this->db_delete(TABLE_PREFIX, 'access',          $condition);
			$this->db_delete(TABLE_PREFIX, 'moderator',       $condition);
			$this->db_delete(TABLE_PREFIX, 'announcement',    $condition);
			$this->db_delete(TABLE_PREFIX, 'subscribeforum',  $condition);
			$this->db_delete(TABLE_PREFIX, 'tachyforumpost',  $condition);
			$this->db_delete(TABLE_PREFIX, 'podcast',         $condition);

			require_once(DIR . '/includes/functions_databuild.php');

			// delete threads in specified forums
			$threads = $this->dbobject->query_read_slave("SELECT * FROM " . TABLE_PREFIX . "thread WHERE $condition");
			while ($thread = $this->dbobject->fetch_array($threads))
			{
				$threadman =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($thread);
				$threadman->set_info('skip_moderator_log', true);
				$threadman->delete($this->registry->forumcache["$thread[forumid]"]['options'] & $this->registry->bf_misc_forumoptions['countposts']);
				unset($threadman);
			}
			$this->dbobject->free_result($threads);

			$this->db_delete(TABLE_PREFIX, 'forum', $condition);

			build_forum_permissions();

			($hook = vBulletinHook::fetch_hook('forumdata_delete')) ? eval($hook) : false;
		}
	}
}

/**
* Class to do data update operations for multiple FORUMS simultaneously
*
* @package	vBulletin
* @version	$Revision: 17682 $
* @date		$Date: 2007-07-23 11:36:10 -0500 (Mon, 23 Jul 2007) $
*/
class vB_DataManager_Forum_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_Forum';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'forumid';

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
		$query = "SELECT * FROM " . TABLE_PREFIX . "forum AS forum";
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
|| # CVS: $RCSfile$ - $Revision: 17682 $
|| ####################################################################
\*======================================================================*/
?>