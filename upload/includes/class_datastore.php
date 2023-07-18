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

if (!class_exists('vB_Datastore'))
{
	exit;
}

// #############################################################################
// Memcached

/**
* Class for fetching and initializing the vBulletin datastore from a Memcache Server
*
* @package	vBulletin
* @version	$Revision: 92875 $
* @date		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
*/
class vB_Datastore_Memcached extends vB_Datastore
{
	/**
	* The Memcache object
	*
	* @var	Memcache
	*/
	var $memcache = null;

	/**
	* To prevent locking when the memcached has been restarted we want to use add rather than set
	*
	* @var	boolean
	*/
	var $memcache_set = true;

	/**
	* To verify a connection is still active
	*
	* @var	boolean
	*/
	var $memcache_connected = false;

	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;

	/**
	* Constructor - establishes the database object to use for datastore queries
	*
	* @param	vB_Registry	The registry object
	* @param	vB_Database	The database object
	*/
	function __construct(&$registry, &$dbobject)
	{
		parent::__construct($registry, $dbobject);

		if (!class_exists('Memcache'))
		{
			trigger_error('Memcache is not installed', E_USER_ERROR);
		}

		$this->memcache = new Memcache;
	}

	/**
	* Connect Wrapper for Memcache
	*
	* @return	integer	When a new connection is made 1 is returned, 2 if a connection already existed
	*/
	function connect()
	{
		if (!$this->memcache_connected)
		{
			if (is_array($this->registry->config['Misc']['memcacheserver']))
			{
				if (method_exists($this->memcache, 'addServer'))
				{
					foreach (array_keys($this->registry->config['Misc']['memcacheserver']) AS $key)
					{
						$this->memcache->addServer(
							$this->registry->config['Misc']['memcacheserver'][$key],
							$this->registry->config['Misc']['memcacheport'][$key],
							$this->registry->config['Misc']['memcachepersistent'][$key],
							$this->registry->config['Misc']['memcacheweight'][$key],
							$this->registry->config['Misc']['memcachetimeout'][$key],
							$this->registry->config['Misc']['memcacheretry_interval'][$key]
						);
					}
				}
				else if (!$this->memcache->connect($this->registry->config['Misc']['memcacheserver'][1], $this->registry->config['Misc']['memcacheport'][1], $this->registry->config['Misc']['memcachetimeout'][1]))
				{
					trigger_error('Unable to connect to memcache server', E_USER_ERROR);
				}
			}
			else if (!$this->memcache->connect($this->registry->config['Misc']['memcacheserver'], $this->registry->config['Misc']['memcacheport']))
			{
				trigger_error('Unable to connect to memcache server', E_USER_ERROR);
			}
			$this->memcache_connected = true;
			return 1;
		}
		return 2;
	}

	/**
	* Close Wrapper for Memcache
	*/
	function close()
	{
		if ($this->memcache_connected)
		{
			$this->memcache->close();
			$this->memcache_connected = false;
		}
	}

	/**
	* Fetches the contents of the datastore from a Memcache Server
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($itemarray)
	{
		$this->connect();

		$this->memcache_set = false;

		$itemlist = array();

		foreach ($this->defaultitems AS $item)
		{
			$this->do_fetch($item, $itemlist);
		}

		if (is_array($itemarray))
		{
			foreach ($itemarray AS $item)
			{
				$this->do_fetch($item, $itemlist);
			}
		}

		$this->store_result = true;

		// some of the items we are looking for were not found, lets get them in one go
		if (!empty($itemlist))
		{
			$this->do_db_fetch(implode(',', $itemlist));
		}

		$this->memcache_set = true;

		$this->check_options();

		// set the version number variable
		$this->registry->versionnumber =& $this->registry->options['templateversion'];

		$this->close();
	}

	/**
	* Fetches the data from shared memory and detects errors
	*
	* @param	string	title of the datastore item
	* @param	array	A reference to an array of items that failed and need to fetched from the database
	*
	* @return	boolean
	*/
	function do_fetch($title, &$itemlist)
	{
		$ptitle = $this->prefix . $title;

		if (($data = $this->memcache->get($ptitle)) === false)
		{ // appears its not there, lets grab the data
			$itemlist[] = "'" . $this->dbobject->escape_string($title) . "'";
			return false;
		}
		$this->register($title, $data);
		return true;
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function register($title, $data, $unserialize_detect = 2)
	{
		if ($this->store_result === true)
		{
			$this->build($title, $data);
		}
		parent::register($title, $data, $unserialize_detect);
	}

	/**
	* Updates the appropriate cache file
	*
	* @param	string	title of the datastore item
	*
	* @return	void
	*/
	function build($title, $data)
	{
		$ptitle = $this->prefix . $title;
		$check = $this->connect();

		if ($this->memcache_set)
		{
			$this->memcache->set($ptitle, $data, MEMCACHE_COMPRESSED);
		}
		else
		{
			$this->memcache->add($ptitle, $data, MEMCACHE_COMPRESSED);
		}
		// if we caused the connection above, then close it
		if ($check == 1)
		{
			$this->close();
		}
	}
}

// #############################################################################
// APC - This is now replaced with APCu.

/**
* Class for fetching and initializing the vBulletin datastore from APC
*
* @package	vBulletin
* @version	$Revision: 92875 $
* @date		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
*/
class vB_Datastore_APC extends vB_Datastore
{
	function __construct($items)
	{
		trigger_error('APC is no longer supported, please use the replacement ACPu.', E_USER_ERROR);
	}
}

// #############################################################################
// APCU - This replaces the older APC, and requires ACPu to be installed.

/**
* Class for fetching and initializing the vBulletin datastore from APCu
*
* @package	vBulletin
* @version	$Revision: 92875 $
* @date		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
*/
class vB_Datastore_APCU extends vB_Datastore
{
	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;

	/**
	* Fetches the contents of the datastore from APCu
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($itemarray)
	{
		if (!function_exists('apcu_fetch'))
		{
			trigger_error('APCu is not installed.', E_USER_ERROR);
		}

		$db =& $this->dbobject;

		$itemlist = array();

		foreach ($this->defaultitems AS $item)
		{
			$this->do_fetch($item, $itemlist);
		}

		if (is_array($itemarray))
		{
			foreach ($itemarray AS $item)
			{
				$this->do_fetch($item, $itemlist);
			}
		}

		$this->store_result = true;

		// some of the items we are looking for were not found, lets get them in one go
		if (!empty($itemlist))
		{
			$this->do_db_fetch(implode(',', $itemlist));
		}

		$this->check_options();

		// set the version number variable
		$this->registry->versionnumber =& $this->registry->options['templateversion'];
	}

	/**
	* Fetches the data from shared memory and detects errors
	*
	* @param	string	title of the datastore item
	* @param	array	A reference to an array of items that failed and need to fetched from the database
	*
	* @return	boolean
	*/
	function do_fetch($title, &$itemlist)
	{
		$ptitle = $this->prefix . $title;

		if (($data = apcu_fetch($ptitle)) === false)
		{ // appears its not there, lets grab the data, lock the shared memory and put it in
			$itemlist[] = "'" . $this->dbobject->escape_string($title) . "'";
			return false;
		}
		$this->register($title, $data);
		return true;
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function register($title, $data, $unserialize_detect = 2)
	{
		if ($this->store_result === true)
		{
			$this->build($title, $data);
		}
		parent::register($title, $data, $unserialize_detect);
	}

	/**
	* Updates the appropriate cache file
	*
	* @param	string	title of the datastore item
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function build($title, $data)
	{
		$ptitle = $this->prefix . $title;

		apcu_delete($ptitle);
		apcu_add($ptitle, $data);
	}
}

// #############################################################################
// XCache

/**
* Class for fetching and initializing the vBulletin datastore from XCache
*
* @package	vBulletin
* @version	$Revision: 92875 $
* @date		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
*/
class vB_Datastore_XCache extends vB_Datastore
{
	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;

	function __construct($registry, $dbobject)
	{
		/* PHP 7.x.x is not supported by xCache, 
		and its unlikely it ever will be, try using ACPu */ 
		if (version_compare(PHP_VERSION, '7.0.0', '>='))
		{
			trigger_error('xCache is not compatible with PHP 7.x.x, try ACPu', E_USER_ERROR);
		}
		else
		{
			parent::__construct($registry, $dbobject);
		}
	}

	/**
	* Fetches the contents of the datastore from XCache
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($itemarray)
	{
		if (!function_exists('xcache_get'))
		{
			trigger_error('Xcache not installed', E_USER_ERROR);
		}

		if (!ini_get('xcache.var_size'))
		{
			trigger_error('Storing of variables is not enabled within XCache', E_USER_ERROR);
		}

		$db =& $this->dbobject;

		$itemlist = array();

		foreach ($this->defaultitems AS $item)
		{
			$this->do_fetch($item, $itemlist);
		}

		if (is_array($itemarray))
		{
			foreach ($itemarray AS $item)
			{
				$this->do_fetch($item, $itemlist);
			}
		}

		$this->store_result = true;

		// some of the items we are looking for were not found, lets get them in one go
		if (!empty($itemlist))
		{
			$this->do_db_fetch(implode(',', $itemlist));
		}

		$this->check_options();

		// set the version number variable
		$this->registry->versionnumber =& $this->registry->options['templateversion'];
	}

	/**
	* Fetches the data from shared memory and detects errors
	*
	* @param	string	title of the datastore item
	* @param	array	A reference to an array of items that failed and need to fetched from the database
	*
	* @return	boolean
	*/
	function do_fetch($title, &$itemlist)
	{
		$ptitle = $this->prefix . $title;

		if (!xcache_isset($ptitle))
		{ // appears its not there, lets grab the data, lock the shared memory and put it in
			$itemlist[] = "'" . $this->dbobject->escape_string($title) . "'";
			return false;
		}
		$data = xcache_get($ptitle);
		$this->register($title, $data);
		return true;
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function register($title, $data, $unserialize_detect = 2)
	{
		if ($this->store_result === true)
		{
			$this->build($title, $data);
		}
		parent::register($title, $data, $unserialize_detect);
	}

	/**
	* Updates the appropriate cache file
	*
	* @param	string	title of the datastore item
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function build($title, $data)
	{
		$ptitle = $this->prefix . $title;

		xcache_unset($ptitle);
		xcache_set($ptitle, $data);
	}

}

// #############################################################################
// datastore using FILES instead of database for storage

/**
* Class for fetching and initializing the vBulletin datastore from files
*
* @package	vBulletin
* @version	$Revision: 92875 $
* @date		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
*/
class vB_Datastore_Filecache extends vB_Datastore
{
	/**
	* Default items that are always loaded by fetch() when using the file method;
	*
	* @var	array
	*/
	var $cacheableitems = array(
		'options',
		'bitfields',
		'forumcache',
		'usergroupcache',
		'stylecache',
		'languagecache',
		'products',
		'pluginlist',
	);

	/**
	* Constructor - establishes the database object to use for datastore queries
	*
	* @param	vB_Registry	The registry object
	* @param	vB_Database	The database object
	*/
	function __construct(&$registry, &$dbobject)
	{
		parent::__construct($registry, $dbobject);

		if (defined('SKIP_DEFAULTDATASTORE'))
		{
			$this->cacheableitems = array('options', 'bitfields');
		}
	}

	/**
	* Fetches the contents of the datastore from cache files
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($itemarray)
	{
		$include_return = @include_once(DATASTORE . '/datastore_cache.php');
		if ($include_return === false)
		{
			if (VB_AREA == 'AdminCP')
			{
				trigger_error('Datastore cache file does not exist. Please reupload includes/datastore/datastore_cache.php from the original download.', E_USER_ERROR);
			}
			else
			{
				parent::fetch($itemarray);
				return;
			}
		}

		$itemlist = array();
		foreach ($this->cacheableitems AS $item)
		{
			if ($$item === '' OR !isset($$item))
			{
				if (VB_AREA == 'AdminCP')
				{
					$$item = $this->fetch_build($item);
				}
				else
				{
					$itemlist[] = "'" . $this->dbobject->escape_string($item) . "'";
					continue;
				}
			}
			if ($this->register($item, $$item) === false)
			{
				trigger_error('Unable to register some datastore items', E_USER_ERROR);
			}

			unset($$item);
		}

		foreach ($this->defaultitems AS $item)
		{
			if (!in_array($item, $this->cacheableitems))
			{
				$itemlist[] = "'" . $this->dbobject->escape_string($item) . "'";
			}
		}

		if (is_array($itemarray))
		{
			foreach ($itemarray AS $item)
			{
				$itemlist[] = "'" . $this->dbobject->escape_string($item) . "'";
			}
		}

		if (!empty($itemlist))
		{
			$this->do_db_fetch(implode(',', $itemlist));
		}

		$this->check_options();

		// set the version number variable
		$this->registry->versionnumber =& $this->registry->options['templateversion'];
	}

	/**
	* Updates the appropriate cache file
	*
	* @param	string	title of the datastore item
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function build($title, $data)
	{
		if (!in_array($title, $this->cacheableitems))
		{
			return;
		}

		if (!file_exists(DATASTORE . '/datastore_cache.php'))
		{
			// file doesn't exist so don't try to write to it
			return;
		}

		$data_code = var_export(vb_unserialize(trim($data)), true);

		if ($this->lock())
		{
			$cache = file_get_contents(DATASTORE . '/datastore_cache.php');

			// this is equivalent to the old preg_match system, but doesn't have problems with big files (#23186)
			$open_match = strpos($cache, "### start $title ###");
			if ($open_match) // we don't want to match the first character either!
			{
				// matched and not at the beginning
				$preceding = $cache[$open_match - 1];
				if ($preceding != "\n" AND $preceding != "\r")
				{
					$open_match = false;
				}
			}

			if ($open_match)
			{
				$close_match = strpos($cache, "### end $title ###", $open_match);
				if ($close_match) // we don't want to match the first character either!
				{
					// matched and not at the beginning
					$preceding = $cache[$close_match - 1];
					if ($preceding != "\n" AND $preceding != "\r")
					{
						$close_match = false;
					}
				}
			}

			// if we matched the beginning and end, then update the cache
			if (!empty($open_match) AND !empty($close_match))
			{
				$replace_start = $open_match - 1; // include the \n
				$replace_end = $close_match + strlen("### end $title ###");
				$cache = substr_replace($cache, "\n### start $title ###\n$$title = $data_code;\n### end $title ###", $replace_start, $replace_end - $replace_start);
			}

			// try an atomic operation first, if that fails go for the old method
			$atomic = false;
			if (($fp = @fopen(DATASTORE . '/datastore_cache_atomic.php', 'w')))
			{
				fwrite($fp, $cache);
				fclose($fp);
				$atomic = $this->atomic_move(DATASTORE . '/datastore_cache_atomic.php', DATASTORE . '/datastore_cache.php');
			}

			if (!$atomic AND ($fp = @fopen(DATASTORE . '/datastore_cache.php', 'w')))
			{
				fwrite($fp, $cache);
				fclose($fp);
			}

			$this->unlock();

			/*insert query*/
			$this->dbobject->query_write("
				REPLACE INTO " . TABLE_PREFIX . "adminutil
					(title, text)
				VALUES
					('datastore', '" . $this->dbobject->escape_string($cache) . "')
			");
		}
		else
		{
			trigger_error('Could not obtain file lock', E_USER_ERROR);
		}
	}

	/**
	* Obtains a lock for the datastore. Attempt to get the lock multiple times before failing.
	*
	* @param	string	title of the datastore item
	*
	* @return	boolean
	*/
	function lock($title = '')
	{
		$lock_attempts = 5;
		while ($lock_attempts >= 1)
		{
			$result = $this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "adminutil SET
					text = UNIX_TIMESTAMP()
				WHERE title = 'datastorelock' AND text < UNIX_TIMESTAMP() - 15
			");
			if ($this->dbobject->affected_rows() > 0)
			{
				return true;
			}
			else
			{
				$lock_attempts--;
				sleep(1);
			}
		}

		return false;
	}

	/**
	* Releases the datastore lock
	*
	* @param	string	title of the datastore item
	*
	* @return	void
	*/
	function unlock($title = '')
	{
		$this->dbobject->query_write("UPDATE " . TABLE_PREFIX . "adminutil SET text = 0 WHERE title = 'datastorelock'");
	}

	/**
	* Fetches the specified datastore item from the database and tries
	* to update the file cache with it. Data is automatically unserialized.
	*
	* @param	string	Datastore item to fetch
	*
	* @return	mixed	Data from datastore (unserialized if fetched)
	*/
	function fetch_build($title)
	{
		$data = '';
		$dataitem = $this->dbobject->query_first("
			SELECT title, data
			FROM " . TABLE_PREFIX . "datastore
			WHERE title = '" . $this->dbobject->escape_string($title) ."'
		");
		if (!empty($dataitem['title']))
		{
			$this->build($dataitem['title'], $dataitem['data']);
			$data = vb_unserialize($dataitem['data']);
		}

		return $data;
	}

	/**
	* Perform an atomic move where a request may occur before a file is written
	*
	* @param	string	Source Filename
	* @param	string	Destination Filename
	*
	* @return	boolean
	*/
	function atomic_move($sourcefile, $destfile)
	{
		if (!@rename($sourcefile, $destfile))
		{
			if (copy($sourcefile, $destfile))
			{
				unlink($sourcefile);
				return true;
			}
			return false;
		}
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
