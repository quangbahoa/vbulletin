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
define('TIMENOW', time());
define('SAPI_NAME', php_sapi_name());

// try to force display_errors on
@ini_set('display_errors', true);

// define current directory
if (!defined('CWD'))
{
	define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
}

// #############################################################################
// fetch the core classes
require_once(CWD . '/includes/class_core.php');

// initialize the data registry
$vbulletin =& new vB_Registry();

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

// load database class
switch (strtolower($vbulletin->config['Database']['dbtype']))
{
	// load standard MySQL class
	case 'mysql':
	case '':
	{
		$db =& new vB_Database($vbulletin);
		break;
	}

	// load MySQLi class
	case 'mysqli':
	{
		$db =& new vB_Database_MySQLi($vbulletin);
		break;
	}

	// load extended, non MySQL class
	default:
	{
	// this is not implemented fully yet
	//	$db = 'vB_Database_' . $vbulletin->config['Database']['dbtype'];
	//	$db =& new $db($vbulletin);
		die('Fatal error: Database class not found');
	}
}

$db->appshortname = 'vBulletin (' . VB_AREA . ')';

if (!defined('SKIPDB'))
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
		$vbulletin->config['Mysqli']['charset']
	);
	if (!empty($vbulletin->config['Database']['force_sql_mode']))
	{
		$db->force_sql_mode('');
	}

	// make $db a member of $vbulletin
	$vbulletin->db =& $db;

	// #############################################################################
	// fetch options and other data from the datastore

	if (VB_AREA == 'Upgrade')
	{
		$optionstemp = false;

		$db->hide_errors();
			$optionstemp = $db->query_first("SELECT template FROM template WHERE title = 'options'");
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
			$datastore_class = (!empty($vbulletin->config['Datastore']['class'])) ? $vbulletin->config['Datastore']['class'] : 'vB_Datastore';

			if ($datastore_class != 'vB_Datastore')
			{
				require_once(DIR . '/includes/class_datastore.php');
			}
			$vbulletin->datastore =& new $datastore_class($vbulletin, $db);
			$vbulletin->datastore->fetch($specialtemplates);
		}
	}

	// ## Load latest bitfields, overwrite datastore versions (if they exist)
	// ## (so latest upgrade script can access any new permissions)
	require_once(DIR . '/includes/class_bitfield_builder.php');
	if (vB_Bitfield_Builder::build_datastore() !== false)
	{
		$myobj =& vB_Bitfield_Builder::init();
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
$hookobj =& vBulletinHook::init();
$vbulletin->pluginlist = '';

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14012 $
|| ####################################################################
\*======================================================================*/
?>