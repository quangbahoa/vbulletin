<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/liceNse.html # ||
|| #################################################################### ||
\*======================================================================*/

if (!class_exists('vB_Datastore'))
{
	exit;
}

// #############################################################################
// eAccelerator

class vB_Datastore_eAccelerator extends vB_Datastore
{
	/*
	Unfortunately, due to a design issue with eAccelerator
	we must disable this module at this time.

	The reason for this is that eAccelerator does not distinguish
	between memory allocated for cached scripts and memory allocated
	as shared memory storage.

	Therefore, the possibility exists for the administrator to turn
	off the board, which would then instruct eAccelerator to update
	its cache of the datastore. However, if the memory allocated is
	insufficient to store the new version of the datastore due to
	being filled with cached scripts, this will not be performed
	successfully, resulting in the OLD version of the datastore
	remaining, with the net result that the board does NOT turn off
	until the web server is restarted (which refreshes the shared
	memory)

	This problem affects anything read from the datastore, including
	the forumcache, the options cache, the usergroup cache, smilies,
	bbcodes, post icons...

	As a result we have no alternative but to totally disable the
	eAccelerator datastore module at this time. If at some point in
	the future this design issue is resolved, we will re-enable it.

	We still recommend running eAccelerator with PHP due to the huge
	performance benefits, but at this time it is not viable to use
	it for datastore cacheing. - Kier
	*/
}

/**
* Class for fetching and initializing the vBulletin datastore from eAccelerator
*
* @package	vBulletin
* @version	$Revision: 24184 $
* @date		$Date: 2007-10-15 06:27:16 -0500 (Mon, 15 Oct 2007) $
*/
class vB_Datastore_eAccelerator_This_Has_Problems extends vB_Datastore
{
	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;

	/**
	* Fetches the contents of the datastore from eAccelerator
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($itemarray)
	{
		if (!function_exists('eaccelerator_get'))
		{
			trigger_error('eAccelerator not installed', E_USER_ERROR);
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

		if (($data = eaccelerator_get($ptitle)) === null)
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

		if (!function_exists('eaccelerator_put'))
		{
			trigger_error('eAccelerator not installed', E_USER_ERROR);
		}
		if ($this->lock($ptitle))
		{
			eaccelerator_rm($ptitle);
			$check = eaccelerator_put($ptitle, $data);
			$this->unlock($ptitle);
			/* This was mainly for debugging, it does not matter now since we can fallback to the db method
			if ($check === false)
			{
				trigger_error('Unable to write to shared memory', E_USER_ERROR);
			}*/
		}
		else
		{
			trigger_error('Could not obtain shared memory lock', E_USER_ERROR);
		}
	}

	/**
	* Obtains a lock for the datastore
	*
	* @param	string	title of the datastore item
	*
	* @return	boolean
	*/
	function lock($title)
	{
		$lock_ex = eaccelerator_lock($title);
		$i = 0;
		while ($lock_ex === false AND ($i++ < 5))
		{
			$lock_ex = eaccelerator_lock($title);
			sleep(1);
		}
		return $lock_ex;
	}

	/**
	* Releases the datastore lock
	*
	* @param	string	title of the datastore item
	*
	* @return	void
	*/
	function unlock($title)
	{
		eaccelerator_unlock($title);
	}
}

// #############################################################################
// Memcached

/**
* Class for fetching and initializing the vBulletin datastore from a Memcache Server
*
* @package	vBulletin
* @version	$Revision: 24184 $
* @date		$Date: 2007-10-15 06:27:16 -0500 (Mon, 15 Oct 2007) $
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
	function vB_Datastore_Memcached(&$registry, &$dbobject)
	{
		parent::vB_Datastore($registry, $dbobject);

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
// APC

/**
* Class for fetching and initializing the vBulletin datastore from APC
*
* @package	vBulletin
* @version	$Revision: 24184 $
* @date		$Date: 2007-10-15 06:27:16 -0500 (Mon, 15 Oct 2007) $
*/
class vB_Datastore_APC extends vB_Datastore
{
	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;

	/**
	* Fetches the contents of the datastore from APC
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($itemarray)
	{
		if (!function_exists('apc_fetch'))
		{
			trigger_error('APC not installed', E_USER_ERROR);
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

		if (($data = apc_fetch($ptitle)) === false)
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

		apc_delete($ptitle);

		if (!function_exists('apc_store'))
		{
			trigger_error('APC not installed', E_USER_ERROR);
		}

		apc_store($ptitle, $data);
	}

}

// #############################################################################
// datastore using FILES instead of database for storage

/**
* Class for fetching and initializing the vBulletin datastore from files
*
* @package	vBulletin
* @version	$Revision: 24184 $
* @date		$Date: 2007-10-15 06:27:16 -0500 (Mon, 15 Oct 2007) $
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
	function vB_Datastore_Filecache(&$registry, &$dbobject)
	{
		parent::vB_Datastore($registry, $dbobject);

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

		$data_code = var_export(unserialize(trim($data)), true);

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
			$data = unserialize($dataitem['data']);
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
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 24184 $
|| ####################################################################
\*======================================================================*/
?>
