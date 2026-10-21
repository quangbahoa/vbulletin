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

if (!defined('VB_AREA') AND !defined('THIS_SCRIPT'))
{
	echo 'VB_AREA and THIS_SCRIPT must be defined to continue';
	exit;
}

if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS']))
{
	echo 'Request tainting attempted.';
	exit;
}

@ini_set('pcre.backtrack_limit', -1);

@date_default_timezone_set(date_default_timezone_get());

// start the page generation timer
global $pagestarttime;
$pagestarttime = microtime();
define('TIMESTART', microtime());

// set the current unix timestamp
define('TIMENOW', time());

// Define safe_mode
define('SAFEMODE', (@ini_get('safe_mode') == 1 OR strtolower(@ini_get('safe_mode')) == 'on') ? true : false);

// define current directory
if (!defined('CWD'))
{
	define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
}

// Get any missing $_SERVER headers
if (function_exists('getallheaders'))
{
	$headers = getallheaders();
	foreach ($headers AS $header => $value)
	{
		$header_name = strtr('HTTP_' . strtoupper($header), '-', '_');
		if (!isset($_SERVER[$header_name])) 
		{
			$_SERVER[$header_name] = $value; 
		};
	}
}

// #############################################################################
// fetch the core includes
require_once(CWD . '/includes/class_core.php');
set_error_handler('vbulletin_error_handler');
set_exception_handler('vbulletin_exception_handler');

// initialize the data registry
$vbulletin = new vB_Registry();

// load the IP data & constants
$vbulletin->fetch_ip_data();

// Add AdSense if present
$vbulletin->adsense_pub_id = '';
$vbulletin->adsense_host_id = '';

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

if ($vbulletin->debug)
{
	restore_error_handler();
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

// #############################################################################
// Load database class
switch ($dbtype)
{
	// Load standard MySQL class
	case 'mysql':
	{
		if ($vbulletin->debug AND ($vbulletin->input->clean_gpc('r', 'explain', TYPE_UINT) OR (defined('POST_EXPLAIN') AND !empty($_POST))))
		{
			// load 'explain' database class
			require_once(DIR . '/includes/class_database_explain.php');
			$db = new vB_Database_Explain($vbulletin);
		}
		else
		{
			$db = new vB_Database($vbulletin);
		}
		break;
	}

	case 'mysql_slave':
	{
		require_once(DIR . '/includes/class_database_slave.php');
		$db = new vB_Database_Slave($vbulletin);
		break;
	}

	// Load MySQLi class
	case 'mysqli':
	{
		if ($vbulletin->debug AND ($vbulletin->input->clean_gpc('r', 'explain', TYPE_UINT) OR (defined('POST_EXPLAIN') AND !empty($_POST))))
		{
			// load 'explain' database class
			require_once(DIR . '/includes/class_database_explain.php');
			$db = new vB_Database_MySQLi_Explain($vbulletin);
		}
		else
		{
			$db = new vB_Database_MySQLi($vbulletin);
		}
		break;
	}

	case 'mysqli_slave':
	{
		require_once(DIR . '/includes/class_database_slave.php');
		$db = new vB_Database_Slave_MySQLi($vbulletin);
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

// get core functions
if (!empty($db->explain))
{
	$db->timer_start('Including Functions.php');
	require_once(DIR . '/includes/functions.php');
	$db->timer_stop(false);
}
else
{
	require_once(DIR . '/includes/functions.php');
}

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

if (defined('DEMO_MODE') AND DEMO_MODE AND function_exists('vbulletin_demo_init_db'))
{
	vbulletin_demo_init_db();
}

// make $db a member of $vbulletin
$vbulletin->db =& $db;

// #############################################################################
// fetch options and other data from the datastore
if (!empty($db->explain))
{
	$db->timer_start('Datastore Setup');
}

$datastore_class = (!empty($vbulletin->config['Datastore']['class'])) ? $vbulletin->config['Datastore']['class'] : 'vB_Datastore';

if ($datastore_class != 'vB_Datastore')
{
	require_once(DIR . '/includes/class_datastore.php');
}
$vbulletin->datastore = new $datastore_class($vbulletin, $db);
$vbulletin->datastore->fetch($specialtemplates);

if ($vbulletin->bf_ugp === null)
{
	echo '<div>vBulletin datastore error caused by one or more of the following:
		<ol>
			' . (function_exists('mmcache_get') ? '<li>Turck MMCache has been detected on your server, first try disabling Turck MMCache</li>' : '') . '
			<li>You may have uploaded vBulletin 3.6 files without also running the vBulletin upgrade script. If you have not run the upgrade script, do so now.</li>
			<li>The datastore cache may have been corrupted. Run <em>Rebuild Bitfields</em> from <em>tools.php</em>, which you can upload from the <em>do_not_upload</em> folder of the vBulletin package.</li>
		</ol>
	</div>';

	trigger_error('vBulletin datastore cache incomplete or corrupt', E_USER_ERROR);
}

if (!empty($db->explain))
{
	$db->timer_stop(false);
}

if ($vbulletin->options['cookietimeout'] < 60)
{
	// values less than 60 will probably break things, so prevent that
	$vbulletin->options['cookietimeout'] = 60;
}

// #############################################################################
/**
* If shutdown functions are allowed, register exec_shut_down to be run on exit.
* Disable shutdown function for IIS CGI with Gzip enabled since it just doesn't work, sometimes, unless we kill the content-length header
* Also disable for PHP4 due to the echo() timeout issue
*/
define('SAPI_NAME', php_sapi_name());
define('NOSHUTDOWNFUNC', true);

// fetch url of referring page after we have access to vboptions['forumhome']
$vbulletin->url = $vbulletin->input->fetch_url();
define('REFERRER_PASSTHRU', $vbulletin->url);

// #############################################################################
// demo mode stuff
if (defined('DEMO_MODE') AND DEMO_MODE AND function_exists('vbulletin_demo_init_page'))
{
	vbulletin_demo_init_page();
}

// #############################################################################
// setup the hooks & plugins system
if ($vbulletin->options['enablehooks'] OR defined('FORCE_HOOKS'))
{
	require_once(DIR . '/includes/class_hook.php');
	$hookobj = vBulletinHook::init();
	if ($vbulletin->options['enablehooks'] AND !defined('DISABLE_HOOKS'))
	{
		if (!empty($vbulletin->pluginlistadmin) AND is_array($vbulletin->pluginlistadmin))
		{
			$vbulletin->pluginlist = array_merge($vbulletin->pluginlist, $vbulletin->pluginlistadmin);
			unset($vbulletin->pluginlistadmin);
		}
		$hookobj->set_pluginlist($vbulletin->pluginlist);
	}
	unset($hookobj);
}
else
{
	// make a null class for optimization
	/**
	* @ignore
	*/
	class vBulletinHook {
		public static function init(){ return new vBulletinHook(); }
		public static function fetch_hook() { return false; }
		public static function fetch_hookusage() { return array(); }
	}
	$vbulletin->pluginlist = '';
}

// if you push things onto this, you need to include the single quotes!
$datastore_fetch = array();

($hook = vBulletinHook::fetch_hook('init_startup')) ? eval($hook) : false;

if (!empty($datastore_fetch))
{
	$vbulletin->datastore->do_db_fetch(implode(',', $datastore_fetch));
}
unset($datastore_fetch);

// #############################################################################
// do a callback to modify any variables that might need modifying based on HTTP input
// eg: doing a conditional redirect based on a $goto value or $vbulletin->noheader must be set
if (function_exists('exec_postvar_call_back'))
{
	exec_postvar_call_back();
}

// #############################################################################
// initialize $show variable - used for template conditionals
$show = array();

// #############################################################################
// Clean Cookie Vars
$vbulletin->input->clean_array_gpc('c', array(
	'vbulletin_collapse'           => TYPE_STR,
	COOKIE_PREFIX . 'referrerid'   => TYPE_UINT,
	COOKIE_PREFIX . 'userid'       => TYPE_UINT,
	COOKIE_PREFIX . 'password'     => TYPE_STR,
	COOKIE_PREFIX . 'lastvisit'    => TYPE_UINT,
	COOKIE_PREFIX . 'lastactivity' => TYPE_UINT,
	COOKIE_PREFIX . 'threadedmode' => TYPE_NOHTML,
	COOKIE_PREFIX . 'sessionhash'  => TYPE_NOHTML,
	COOKIE_PREFIX . 'styleid'      => TYPE_UINT,
	COOKIE_PREFIX . 'languageid'   => TYPE_UINT,
));

// #############################################################################
// Setup session
if (!empty($db->explain))
{
	$db->timer_start('Session Handling');
}

$vbulletin->input->clean_array_gpc('r', array(
	's'       => TYPE_NOHTML,
	'styleid' => TYPE_INT,
	'langid'  => TYPE_INT,
));

// conditional used in templates to hide things from search engines.
$show['search_engine'] = ($vbulletin->superglobal_size['_COOKIE'] == 0 AND preg_match("#(google|msnbot|yahoo! slurp)#si", $_SERVER['HTTP_USER_AGENT']));

// handle session input
$sessionhash = (!empty($vbulletin->GPC['s']) ? $vbulletin->GPC['s'] : $vbulletin->GPC[COOKIE_PREFIX . 'sessionhash']); // override cookie

// Set up user's chosen language
if ($vbulletin->GPC['langid'] AND !empty($vbulletin->languagecache["{$vbulletin->GPC['langid']}"]['userselect']))
{
	$languageid =& $vbulletin->GPC['langid'];
	vbsetcookie('languageid', $languageid);
}
else if ($vbulletin->GPC[COOKIE_PREFIX . 'languageid'] AND !empty($vbulletin->languagecache[$vbulletin->GPC[COOKIE_PREFIX . 'languageid']]['userselect']))
{
	$languageid = $vbulletin->GPC[COOKIE_PREFIX . 'languageid'];
}
else
{
	$languageid = 0;
}

// Set up user's chosen style
if ($vbulletin->GPC['styleid'])
{
	$styleid =& $vbulletin->GPC['styleid'];
	vbsetcookie('styleid', $styleid);
}
else if ($vbulletin->GPC[COOKIE_PREFIX . 'styleid'])
{
	$styleid = $vbulletin->GPC[COOKIE_PREFIX . 'styleid'];
}
else
{
	$styleid = 0;
}

($hook = vBulletinHook::fetch_hook('init_startup_session_setup_start')) ? eval($hook) : false;

// build the session and setup the environment
$vbulletin->session = new vB_Session($vbulletin, $sessionhash, $vbulletin->GPC[COOKIE_PREFIX . 'userid'], $vbulletin->GPC[COOKIE_PREFIX . 'password'], $styleid, $languageid);

// Hide sessionid in url if we are a search engine or if we have a cookie
$vbulletin->session->set_session_visibility($show['search_engine'] OR $vbulletin->superglobal_size['_COOKIE'] > 0);
$vbulletin->userinfo =& $vbulletin->session->fetch_userinfo();
$vbulletin->session->do_lastvisit_update($vbulletin->GPC[COOKIE_PREFIX . 'lastvisit'], $vbulletin->GPC[COOKIE_PREFIX . 'lastactivity']);
// put the sessionhash into contact-us links automatically if required (issueid 21522)
if ($vbulletin->session->visible AND $vbulletin->options['contactuslink'] != '' AND substr(strtolower($vbulletin->options['contactuslink']), 0, 7) != 'mailto:')
{
	if (strpos($vbulletin->options['contactuslink'], '?') !== false)
	{
		$vbulletin->options['contactuslink'] = str_replace('?', '?' . $vbulletin->session->vars['sessionurl'], $vbulletin->options['contactuslink']);
	}
	else
	{
		$vbulletin->options['contactuslink'] .= $vbulletin->session->vars['sessionurl_q'];
	}
}

($hook = vBulletinHook::fetch_hook('init_startup_session_setup_complete')) ? eval($hook) : false;

// CSRF Protection for POST requests
if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST')
{
	if ($vbulletin->userinfo['userid'] > 0 AND defined('CSRF_PROTECTION') AND CSRF_PROTECTION === true)
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'securitytoken' => TYPE_STR,
		));

		if (!in_array($_POST['do'], $vbulletin->csrf_skip_list))
		{
			if (!verify_security_token($vbulletin->GPC['securitytoken'], $vbulletin->userinfo['securitytoken_raw']))
			{
				switch ($vbulletin->GPC['securitytoken'])
				{
					case '':
						define('CSRF_ERROR', 'missing');
						break;
					case 'guest':
						define('CSRF_ERROR', 'guest');
						break;
					case 'timeout':
						define('CSRF_ERROR', 'timeout');
						break;
					default:
						define('CSRF_ERROR', 'invalid');
				}
			}
		}
	}
	else if (!defined('CSRF_PROTECTION') AND !defined('SKIP_REFERRER_CHECK'))
	{
		if ($_SERVER['HTTP_HOST'] OR $_ENV['HTTP_HOST'])
		{
			$http_host = ($_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : $_ENV['HTTP_HOST']);
		}
		else if ($_SERVER['SERVER_NAME'] OR $_ENV['SERVER_NAME'])
		{
			$http_host = ($_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : $_ENV['SERVER_NAME']);
		}

		if ($http_host AND $_SERVER['HTTP_REFERER'])
		{
			$http_host = strtolower(preg_replace('#:80$#', '', trim($http_host)));
			$referrer_parts = @parse_url($_SERVER['HTTP_REFERER']);
			$ref_port = isset($referrer_parts['port']) ? intval($referrer_parts['port']) : 0;
			$ref_host = strtolower($referrer_parts['host'] . ((!empty($ref_port) AND $ref_port != '80') ? ":$ref_port" : ''));

			if ($http_host == $ref_host)
			{	/* Instant match is good enough
				no need to check anything further. */
				$pass_ref_check = true;
			}
			else
			{
				$pass_ref_check = false;
				$allowed = array('.paypal.com');
				$allowed[] = '.'.preg_replace('#^www\.#i', '', $http_host);
				$whitelist = preg_split('#\s+#', $vbulletin->options['allowedreferrers'], -1, PREG_SPLIT_NO_EMPTY); // Get whitelist
				$allowed = array_unique(is_array($whitelist) ? array_merge($allowed,$whitelist) : $allowed); // Merge and de-duplicate.

				foreach ($allowed AS $host)
				{
					$host = strtolower($host);
					if (substr($host,0,1) == '.' AND 
					(preg_match('#' . preg_quote($host, '#') . '$#siU', $ref_host) OR substr($host,1) == $ref_host))
					{
						$pass_ref_check = true;
						break;
					}
				}
				unset($allowed, $whitelist);
			}

			if ($pass_ref_check == false)
			{
				die('In order to accept POST requests originating from this domain, the admin must add the domain to the whitelist.');
			}
		}
	}
}


// Google Web Accelerator can display sensitive data ignoring any headers regarding caching
// it's a good thing for guests but not for anyone else
if ($vbulletin->userinfo['userid'] > 0 AND isset($_SERVER['HTTP_X_MOZ']) AND strpos($_SERVER['HTTP_X_MOZ'], 'prefetch') !== false)
{
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		header('Status: 403 Forbidden');
	}
	else
	{
		header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
	}
	die('Prefetching is not allowed due to the various privacy issues that arise.');
}

// use the session-specified style if there is one
if ($vbulletin->session->vars['styleid'] != 0)
{
	$vbulletin->userinfo['styleid'] = $vbulletin->session->vars['styleid'];
}

if (!empty($db->explain))
{
	$db->timer_stop(false);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 93801 $
|| # $Date: 2017-04-25 06:47:39 -0700 (Tue, 25 Apr 2017) $
|| ####################################################################
\*======================================================================*/
?>
