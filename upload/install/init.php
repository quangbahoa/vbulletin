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

// Disable unserialize warnings
define('DISABLE_VBUS_ERRORS', true);

if (!defined('VB_AREA') AND !defined('THIS_SCRIPT'))
{
	echo 'VB_AREA or THIS_SCRIPT must be defined to continue';
	exit;
}

if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS']))
{
	echo 'Request tainting attempted.';
	exit;
}

// set the current unix timestamp
if (!defined('TIMENOW'))
{
	define('TIMENOW', time());
}
define('SAPI_NAME', php_sapi_name());
define('SAFEMODE', (@ini_get('safe_mode') == 1 OR strtolower(@ini_get('safe_mode')) == 'on') ? true : false);

global $pagestarttime;
$pagestarttime = microtime();

// try to force display_errors on
@ini_set('display_errors', 'On');

// define current directory
if (!defined('CWD'))
{
	define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
}

// #############################################################################
// fetch the core classes
require_once(CWD . '/includes/class_core.php');

// initialize the data registry
$vbulletin = new vB_Registry();

// parse the configuration ini file
$vbulletin->fetch_config();

if (CWD == '.')
{
	// getcwd() failed and so we need to be told the full forum path in config.php
	if (!empty($vbulletin->config['Misc']['forumpath']))
	{
		define('DIR', $vbulletin->config['Misc']['forumpath']);
	}
	else
	{
		trigger_error('<strong>Configuration</strong>: You must insert a value for <strong>forumpath</strong> in config.php', E_USER_ERROR);
	}
}
else
{
	define('DIR', CWD);
}

if (!empty($vbulletin->config['Misc']['datastorepath']))
{
		define('DATASTORE', $vbulletin->config['Misc']['datastorepath']);
}
else
{
		define('DATASTORE', DIR . '/includes/datastore');
}

$dbtype = strtolower($vbulletin->config['Database']['dbtype']);

// Force MySQL to MySQLi
if ($dbtype == 'mysql')
{
	$dbtype = 'mysqli';
}
else if ($dbtype == 'mysql_slave')
{
	$dbtype = 'mysqli_slave';
}

//If type is missing, Force MySQLi 
$dbtype = $dbtype ? $dbtype : 'mysqli';

// Load database class
switch ($dbtype)
{
	// Load standard MySQL class
	case 'mysql':
	case 'mysql_slave':
	{
		$db = new vB_Database($vbulletin);
		break;
	}

	// Load MySQLi class
	case 'mysqli':
	case 'mysqli_slave':
	{
		$db = new vB_Database_MySQLi($vbulletin);
		break;
	}

	// Load extended, non MySQL class
	default:
	{
		@include_once(DIR . "/includes/class_database_$dbtype.php");
		$dbclass = "vB_Database_$dbtype";
		$db = new $dbclass($vbulletin);
	}
}

$db->appshortname = 'vBulletin (' . VB_AREA . ')';

if (!defined('SKIPDB'))
//if (!defined('SKIPDB') AND VB_AREA != 'Init')
{
	// we do not want to use the slave server at all during this process
	// as latency problems may occur
	$vbulletin->config['SlaveServer']['servername'] = '';
	// make database connection
	$db->connect(
		$vbulletin->config['Database']['dbname'],
		$vbulletin->config['MasterServer']['servername'],
		$vbulletin->config['MasterServer']['port'],
		$vbulletin->config['MasterServer']['username'],
		$vbulletin->config['MasterServer']['password'],
		$vbulletin->config['MasterServer']['usepconnect'],
		$vbulletin->config['SlaveServer']['servername'],
		$vbulletin->config['SlaveServer']['port'],
		$vbulletin->config['SlaveServer']['username'],
		$vbulletin->config['SlaveServer']['password'],
		$vbulletin->config['SlaveServer']['usepconnect'],
		$vbulletin->config['Mysqli']['ini_file'],
		(isset($vbulletin->config['Mysqli']['charset']) ? $vbulletin->config['Mysqli']['charset'] : '')
	);

	// Allow setting of SQL mode, not generally required
	if (isset($vbulletin->config['Database']['set_sql_mode']))
	{
		$db->force_sql_mode($vbulletin->config['Database']['set_sql_mode']);
	}
	else
	{
		$db->force_sql_mode(''); // Force blank mode if none set, avoids Strict Mode issues.
	}

	// make $db a member of $vbulletin
	$vbulletin->db =& $db;

	// #############################################################################
	// fetch options and other data from the datastore

	// grab the MySQL Version once and let every script use it.
	$mysqlversion = $db->query_first("SELECT version() AS version");
	define('MYSQL_VERSION', $mysqlversion['version']);

	if (VB_AREA == 'Upgrade')
	{
		$optionstemp = false;

		$db->hide_errors();
			$optionstemp = $db->query_first("SELECT template FROM template WHERE title = 'options' AND templatesetid = -1");
		$db->show_errors();

		// ## Found vB2 Options so use them...
		if ($optionstemp)
		{
			eval($optionstemp['template']);
			$vbulletin->options =& $vboptions;
			$vbulletin->versionnumber = $templateversion;
		}
		else
		{
			// we need our datastore table to be updated properly to function
			$db->hide_errors();
			$db->query_write("ALTER TABLE " . TABLE_PREFIX . "datastore ADD unserialize SMALLINT NOT NULL DEFAULT '2'");
			$db->show_errors();

			$datastore_class = (!empty($vbulletin->config['Datastore']['class'])) ? $vbulletin->config['Datastore']['class'] : 'vB_Datastore';

			if ($datastore_class != 'vB_Datastore')
			{
				require_once(DIR . '/includes/class_datastore.php');
			}
			$vbulletin->datastore = new $datastore_class($vbulletin, $db);
			$vbulletin->datastore->fetch($specialtemplates);
		}
	}
	else if (VB_AREA == 'Install')
	{ // load it up but don't actually call fetch, we need the ability to overwrite fields.
		$datastore_class = (!empty($vbulletin->config['Datastore']['class'])) ? $vbulletin->config['Datastore']['class'] : 'vB_Datastore';

		if ($datastore_class != 'vB_Datastore')
		{
			require_once(DIR . '/includes/class_datastore.php');
		}
		$vbulletin->datastore = new $datastore_class($vbulletin, $db);
	}

	// ## Load latest bitfields, overwrite datastore versions (if they exist)
	// ## (so latest upgrade script can access any new permissions)
	require_once(DIR . '/includes/class_bitfield_builder.php');
	if (vB_Bitfield_Builder::build_datastore() !== false)
	{
		$myobj = vB_Bitfield_Builder::init();
		require_once(DIR . '/includes/functions.php');
		require_once(DIR . '/includes/functions_misc.php');

		foreach (array_keys($myobj->datastore) AS $group)
		{
			$vbulletin->{'bf_' . $group} =& $myobj->datastore["$group"];
			foreach (array_keys($myobj->datastore["$group"]) AS $subgroup)
			{
				$vbulletin->{'bf_' . $group . '_' . $subgroup} =& $myobj->datastore["$group"]["$subgroup"];
			}
		}
	}
	else
	{
		trigger_error('Error Building Bitfields', E_USER_ERROR);
	}
}

// setup an empty hook class in case we run some of the main vB code
require_once(DIR . '/includes/class_hook.php');
$hookobj = vBulletinHook::init();
$vbulletin->pluginlist = '';

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 93608 $
|| # $Date: 2017-04-05 18:38:48 -0700 (Wed, 05 Apr 2017) $
|| ####################################################################
\*======================================================================*/
?>
