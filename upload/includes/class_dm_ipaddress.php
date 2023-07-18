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
* Class to do data save/delete operations for IP Address Data
*
* Example usage:
*
* $ipdata = datamanager_init('IP_Data', $vbulletin, ERRTYPE_STANDARD);
*
* @package	vBulletin
* @version	$Revision: 92424 $
* @date		$Date: 2017-01-15 14:08:51 +0000 (Sun, 15 Jan 2017) $
*/
class vB_DataManager_IPAddress extends vB_DataManager
{
	/**
	* Array of recognised and required fields for poll, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'ipid'			=> array(TYPE_UINT,	REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'contentid'		=> array(TYPE_UINT,	REQ_YES, VF_METHOD, 'verify_nonzero'),
		'contenttype'	=> array(TYPE_STR,	REQ_YES, VF_METHOD),
		'dateline'      => array(TYPE_UINT,	REQ_AUTO, VF_METHOD, 'verify_nonzero'),
		'ip'			=> array(TYPE_STR,	REQ_YES, VF_METHOD, 'verify_ipaddress'),
		'altip'			=> array(TYPE_STR,	REQ_NO, VF_METHOD, 'verify_ipaddress'),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('ipid = %1$d', 'ipid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'ipaddress';

	// Skip save
	var $skip_update = false;
	
	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

		// Defaults
		if (defined('IPADDRESS')
		AND !defined('SKIP_IPADDRESS_DEFAULTS'))
		{
			$this->set_info('ip', IPADDRESS); 
			$this->set_info('altip', ALT_IP);
		}
	}

	/**
	* Format the data for saving
	*
	* @param	bool
	*
	* @return 	boolean	Function result
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		// If ip is missing, set to defaults
		if (!($ip = $this->fetch_field('ip')))
		{
			if (!($ip = $this->info['ip']))
			{
				$ip = '0.0.0.0';
			}
		}

		// If altip is missing, set to defaults
		if (!($altip = $this->fetch_field('altip')))
		{
			if (!($altip = $this->info['altip']))
			{
				$altip = $ip;
			}
		}

		// If dateline missing, set to now
		if (!$this->fetch_field('dateline'))
		{
			$this->set('dateline', TIMENOW);
		}

		// Compress and set the ip data
		$this->set('ip', compress_ip($ip, false));
		$this->set('altip', compress_ip($altip, false));

		// Set contenttype if override exists
		if ($this->verify_contenttype($this->info['contenttype']))
		{
			$this->set('contenttype', $this->info['contenttype']);
		}
		
		return true;
	}

	/**
	* Saves the data from the object into the specified database tables
	*
	* We change the default for $replace to true, and then call the parent.
	*/
	function save($doquery = true, $delayed = false, $affected_rows = false, $replace = true, $ignore = false)
	{
		// We default $replace to true, and then call the parent.
		return parent::save($doquery, $delayed, $affected_rows, $replace, $ignore);
	}

	/**
	* Verifies the content type
	*
	* @param	string	The type
	*
	* @return 	boolean	Returns true if the type is valid
	*/
	function verify_contenttype($type)
	{
		return in_array($type, array('groupmessage','visitormessage','picturecomment','other'));
	}

	/**
	* Updates a content record
	*
	* @param	string	The type
	* @param	string	The column name
	* @param	int		The ip address record id
	* @param	int		The content record id
	* @param	string	The ip address field name
	*
	* @return 	boolean	Returns true if the type is valid
	*/
	function update_content($type, $fieldid, $ipid, $contentid, $filedname = 'ipaddress')
	{
		$this->$type = array();
		$this->{$type}[$filedname] = $ipid;
		$sql = $this->fetch_update_sql(TABLE_PREFIX, $type, "$fieldid = $contentid");

		return $this->dbobject->query_write($sql);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92424 $
|| # $Date: 2017-01-15 14:08:51 +0000 (Sun, 15 Jan 2017) $
|| ####################################################################
\*======================================================================*/
?>
