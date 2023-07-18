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

// to call a hook:
//	require_once(DIR . '/includes/class_hook.php');
//	($hook = vBulletinHook::fetch_hook('unique_hook_name')) ? eval($hook) : false;

/**
* Works the vBulletin Plugin Hook System
*
* @package 		vBulletin
* @version		$Revision: 92875 $
* @author		Kier & Mike
* @date 		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
* @copyright 	www.vbulletin.com/license.html
*
*/
class vBulletinHook
{
	/**
	* This holds the plugin data
	*
	* @var    array
	*/
	var $pluginlist = array();

	/**
	* This keeps track of which hooks have been used
	*
	* @var	array
	*/
	var $hookusage = array();

	protected static $instance = null;

	/**
	* Constructor - unserializes the plugin data from the datastore when class is initiated
	*
	* @return	none
	*/
	function __construct()
	{
	}

	/**
	* Sets the plugin list array
	*/
	function set_pluginlist(&$pluginlist)
	{
		$this->pluginlist =& $pluginlist;
	}

	/**
	* Singleton emulation - use this function to instantiate the class
	*
	* @return	vBulletinHook
	*/
	public static function &init()
	{
		if (!self::$instance)
		{
			self::$instance = new vBulletinHook();
		}

		return self::$instance;
	}

	/**
	* Returns any code attached to a hook with a specific name
	*
	* @param	string	hookname	The name of the hook (location) to be executed
	*
	* @return	string
	*/
	function fetch_hook_object($hookname)
	{
		if (!empty($this->pluginlist["$hookname"]))
		{
			if (!isset($this->hookusage["$hookname"]))
			{
				$this->hookusage["$hookname"] = true;
			}
			return $this->pluginlist["$hookname"];
		}
		else
		{
			if (!isset($this->hookusage["$hookname"]))
			{
				$this->hookusage["$hookname"] = false;
			}
			return '';
		}
	}

	/**
	* Returns any code attached to a hook with a specific name. Used when the object is not in scope already.
	*
	* @param	string	hookname	The name of the hook (location) to be executed
	*
	* @return	string
	*/
	public static function fetch_hook($hookname = false)
	{
		if (!$hookname)
		{
			return false;
		}

		$obj = vBulletinHook::init();

		return $obj->fetch_hook_object($hookname);
	}

	/**
	* Builds the datastore for the hooks into the database.
	*/
	public static function build_datastore(&$dbobject)
	{
		$code = array();
		$admincode = array();

		$adminlocations = array();

		require_once(DIR . '/includes/class_xml.php');
		$handle = opendir(DIR . '/includes/xml/');
		while (($file = readdir($handle)) !== false)
		{
			if (!preg_match('#^hooks_(.*).xml$#i', $file, $matches))
			{
				continue;
			}

			$xmlobj = new vB_XML_Parser(false, DIR . "/includes/xml/$file");
			$xml = $xmlobj->parse();

			if (!is_array($xml['hooktype'][0]))
			{
				$xml['hooktype'] = array($xml['hooktype']);
			}

			foreach ($xml['hooktype'] AS $key => $hooktype)
			{
				if (!is_numeric($key))
				{
					continue;
				}

				if (!is_array($hooktype['hook']))
				{
					$hooktype['hook'] = array($hooktype['hook']);
				}

				foreach ($hooktype['hook'] AS $hook)
				{
					if ((is_array($hook) AND !empty($hook['admin'])) OR !empty($hooktype['admin']))
					{
						$adminlocations[(is_string($hook) ? $hook : $hook['value'])] = true;
					}
				}
			}
		}

		$plugins = $dbobject->query_read("
			SELECT plugin.*,
				IF(product.productid IS NULL, 0, 1) AS foundproduct,
				IF(plugin.product = 'vbulletin', 1, product.active) AS productactive
			FROM " . TABLE_PREFIX . "plugin AS plugin
			LEFT JOIN " . TABLE_PREFIX . "product AS product ON(product.productid = plugin.product)
			WHERE plugin.active = 1
				AND plugin." . "phpcode <> ''
			ORDER BY plugin.executionorder ASC
		");
		while ($plugin = $dbobject->fetch_array($plugins))
		{
			if ($plugin['foundproduct'] AND !$plugin['productactive'])
			{
				continue;
			}
			else if (!empty($adminlocations["$plugin[hookname]"]))
			{
				if (!isset($admincode[$plugin['hookname']]))
				{
					$admincode[$plugin['hookname']] = '';
				}
				
				$admincode["$plugin[hookname]"] .= "$plugin[phpcode]\r\n";
			}
			else
			{
				if (!isset($code[$plugin['hookname']]))
				{
					$code[$plugin['hookname']] = '';
				}
				
				$code["$plugin[hookname]"] .= "$plugin[phpcode]\r\n";
			}
		}
		$dbobject->free_result($plugins);

		build_datastore('pluginlist', serialize($code), 1);
		build_datastore('pluginlistadmin', serialize($admincode), 1);

		return true;
	}

	/**
	* Fetches the array of hooks that have been used.
	*/
	public static function fetch_hookusage()
	{
		$obj = vBulletinHook::init();
		return $obj->hookusage;
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
