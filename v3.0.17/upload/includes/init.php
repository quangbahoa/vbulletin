<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

// $HTTP_POST_FILES to check for PHP < 4.1.0 -- code below handles other cases
if (isset($_REQUEST['GLOBALS']) OR isset($HTTP_POST_FILES['GLOBALS']))
{
	echo 'Request tainting attempted.';
	exit;
}

@ini_set('pcre.backtrack_limit', -1);

function vb_error_handler($errno, $errstr, $errfile, $errline)
{
	if (!error_reporting())
	{
		return;
	}

	switch ($errno)
	{
		case E_WARNING:
		case E_USER_WARNING:
			if (!ini_get('display_errors'))
			{
				// place this here to prevent messages on all pages in case ini_get is disabled
				return;
			}
			$errfile = str_replace(getcwd(), '', $errfile);
			echo "<br /><strong>Warning</strong>: $errstr in <strong>$errfile</strong> on line <strong>$errline</strong><br />";
		break;
	}
}
set_error_handler('vb_error_handler');

$pagestarttime = microtime();
define('TIMENOW', time());

if (@ini_get('zlib.output_compression'))
{
	$nozip = true;
}

// #############################################################################
// set which variables can pass through globals filter
$_allowedvars = array(
	'GLOBALS',          // of course :)
	'_GET',             // }
	'_POST',            // } system variables
	'_COOKIE',          // }
	'_REQUEST',         // }
	'_SERVER',          // }
	'_ENV',             // |
	'_FILES',           // }
	'specialtemplates', // special templates from datastore table
	'globaltemplates',  // used for template cacheing
	'actiontemplates',  // templates for specific script actions
	'phrasegroups',     // phrase groups (in addition to 'global')
	//'actionphrases',	// phrase groups for specific actions
	'noheader',         // used to suppress the default vB headers
	'nodb',             // suppress database connection
	'nozip',            // suppress gzipping
	'steptitles',       // step titles for upgrade scripts
	'pagestarttime',    // microtime() from top of page
	'_allowedvars',     // this array :-)
	'___db_user',		// lycos has special variables
	'___db_host',		// lycos has special variables
);

// #############################################################################
// Standardize names of arrays for people not on PHP 4.1
if (PHP_VERSION  < '4.1.0')
{
	$_GET = &$HTTP_GET_VARS;
	$_POST = &$HTTP_POST_VARS;
	$_COOKIE = &$HTTP_COOKIE_VARS;
	$_SERVER = &$HTTP_SERVER_VARS;
	$_ENV = &$HTTP_ENV_VARS;
	$_FILES = &$HTTP_POST_FILES;
	$_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
}

// #############################################################################
// unset all other variables created by register_globals on
if (is_array($GLOBALS))
{
	function deregister_globals($_allowedvars)
	{
		foreach ($GLOBALS AS $_arrykey => $_arryval)
		{
			if (!in_array($_arrykey, $_allowedvars) AND $_arrykey != '_arrykey' AND $_arrykey != '_arryval')
			{
				unset($GLOBALS["$_arrykey"]);
			}
		}
	}
	deregister_globals($_allowedvars);
}
else
{
	die('<strong>Fatal Error:</strong> Invalid URL.');
}

// #############################################################################
// re-register globals -- this code should not be necessary by final version
if (!defined('NO_REGISTER_GLOBALS'))
{
	// define NO_REGISTER_GLOBALS to emulate register_globals as off
	foreach (array('_GET', '_POST', '_COOKIE', '_SERVER', '_ENV') AS $_superglobal)
	{
		if (is_array($$_superglobal))
		{
			foreach ($$_superglobal AS $_arrykey => $_arryval)
			{
				$$_arrykey = ${$_superglobal}["$_arrykey"];
			}
		}
	}
	if (is_array($_FILES))
	{
		foreach ($_FILES AS $_arrykey => $_arryval)
		{
			foreach($_arryval AS $_k => $_v)
			{
				$_tmp = "$_arrykey" . "_$_k";
				$$_tmp = $_v;
			}
			$$_arrykey = $_FILES["$_arrykey"]['tmp_name'];
		}
	}
}
// end temporary global re-registration code
// #############################################################################

// #############################################################################
// translate f/t/p/u/a into forumid/threadid/postid/userid/announcementid

function convert_short_varnames(&$array, $add_to_REQUEST = false)
{
	foreach (array(
		'f' => 'forumid',
		't' => 'threadid',
		'p' => 'postid',
		'u' => 'userid',
		'a' => 'announcementid',
		'c' => 'calendarid',
		'e' => 'eventid',
		'pp' => 'perpage',
		'page' => 'pagenumber',
		'sort' => 'sortfield',
		'order' => 'sortorder',
	) AS $shortname => $longname)
	{
		if (isset($array["$shortname"]) AND !isset($array["$longname"]))
		{
			$array["$longname"] = $array["$shortname"];
			if ($add_to_REQUEST)
			{
				$GLOBALS['_REQUEST']["$longname"] = $array["$shortname"];
			}
		}
	}
}

convert_short_varnames($_GET, true);
convert_short_varnames($_POST, true);
unset($_COOKIE['userid']);
if (!isset($_GET['userid']) AND !isset($_POST['userid']))
{
	unset($_REQUEST['userid']);
}
else if (isset($_GET['userid']) OR isset($_POST['userid']))
{
	$_REQUEST['userid'] = isset($_POST['userid']) ? $_POST['userid'] : $_GET['userid'];
}

// #############################################################################
// deal with magic_quotes nastiness in GPC data
if (get_magic_quotes_gpc())
{
	function exec_gpc_stripslashes(&$arr)
	{
		if (is_array($arr))
		{
			foreach($arr AS $_arrykey => $_arryval)
			{
				if (is_string($_arryval))
				{
					$arr["$_arrykey"] = stripslashes($_arryval);
				}
				else if (is_array($_arryval))
				{
					$arr["$_arrykey"] = exec_gpc_stripslashes($_arryval);
				}
			}
		}
		return $arr;
	}

	$_GET = exec_gpc_stripslashes($_GET);
	$_POST = exec_gpc_stripslashes($_POST);
	$_COOKIE = exec_gpc_stripslashes($_COOKIE);
	if (is_array($_FILES))
	{
		foreach ($_FILES AS $key => $val)
		{
			$_FILES[$key]['tmp_name'] = str_replace('\\', '\\\\', $val['tmp_name']);
		}
	}
	$_FILES = exec_gpc_stripslashes($_FILES);
	$_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
}
set_magic_quotes_runtime(0);

// #############################################################################
// start prep shutdown function - change this to ** true ** if you CAN'T use register_shutdown_function()
define('NOSHUTDOWNFUNC', false);

function xss_clean($var)
{
	static $find, $replace;
	if (empty($find))
	{
		$find = array('"', '<', '>');
		$replace = array('&quot;', '&lt;', '&gt;');
	}
	$var = preg_replace('/javascript/i', 'java script', $var);
	return str_replace($find, $replace, $var);
}

// #############################################################################
// establish client IP address
define('IPADDRESS', $_SERVER['REMOTE_ADDR']);

// check several settings for the ip; good for not grabbing proxy IPs, but can still be problematic
if ($_SERVER['HTTP_CLIENT_IP'])
{
	define('ALT_IP', $_SERVER['HTTP_CLIENT_IP']);
}
else if ($_SERVER['HTTP_X_FORWARDED_FOR'] AND preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))
{
	// make sure we dont pick up an internal IP defined by RFC1918
	foreach ($matches[0] AS $ip)
	{
		if (!preg_match("#^(10|172\.16|192\.168)\.#", $ip))
		{
			define('ALT_IP', $ip);
			break;
		}
	}
}
else if ($_SERVER['HTTP_FROM'])
{
	define('ALT_IP', $_SERVER['HTTP_FROM']);
}
else
{
	define('ALT_IP', $_SERVER['REMOTE_ADDR']);
}

// #############################################################################
// determine URL / referrer of current page
if ($_ENV['REQUEST_URI'] OR $_SERVER['REQUEST_URI'])
{
	$scriptpath = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : $_ENV['REQUEST_URI'];
}
else
{
	if ($_ENV['PATH_INFO'] OR $_SERVER['PATH_INFO'])
	{
		$scriptpath = $_SERVER['PATH_INFO'] ? $_SERVER['PATH_INFO']: $_ENV['PATH_INFO'];
	}
	else if ($_ENV['REDIRECT_URL'] OR $_SERVER['REDIRECT_URL'])
	{
		$scriptpath = $_SERVER['REDIRECT_URL'] ? $_SERVER['REDIRECT_URL']: $_ENV['REDIRECT_URL'];
	}
	else
	{
		$scriptpath = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_ENV['PHP_SELF'];
	}

	if ($_ENV['QUERY_STRING'] OR $_SERVER['QUERY_STRING'])
	{
		$scriptpath .= '?' . ($_SERVER['QUERY_STRING'] ? $_SERVER['QUERY_STRING'] : $_ENV['QUERY_STRING']);
	}
}

$quest_pos = strpos($scriptpath, '?');
if ($quest_pos !== false)
{
	$script = urldecode(substr($scriptpath, 0, $quest_pos));
	$scriptpath = $script . substr($scriptpath, $quest_pos);
}
else
{
	$scriptpath = urldecode($scriptpath);
}

$scriptpath = preg_replace('/(s|sessionhash)=[a-z0-9]{32}?&?/', '', $scriptpath);
$scriptpath = xss_clean($scriptpath);
$script = preg_replace('#(\?.*)#', '', $scriptpath);
$wolpath = $scriptpath;

if (!defined('THIS_SCRIPT') AND strpos(strtolower($script), 'global.php') !== false)
{
	die('<p><strong>Critical Error</strong><br />global.php must not be called directly.</p>');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	// Tag the variables back on to the filename if we are coming from POST so that WOL can access them.
	foreach ($_POST AS $_var => $_value)
	{
		switch ($_var)
		{
			case 'forumid': case 'f':
				$_tackon .= ($_tackon ? '&' : '') . "forumid=$_value";
				break;
			case 'threadid': case 't':
				$_tackon .= ($_tackon ? '&' : '') . "threadid=$_value";
				break;
			case 'postid': case 'p':
				$_tackon .= ($_tackon ? '&' : '') . "postid=$_value";
				break;
			case 'userid': case 'u':
				$_tackon .= ($_tackon ? '&' : '') . "userid=$_value";
				break;
			case 'do':
			case 'eventid': case 'e':
				$_tackon .= ($_tackon ? '&' : '') . "eventid=$_value";
				break;
			case 'calendarid': case 'c':
				$_tackon .= ($_tackon ? '&' : '') . "calendarid=$_value";
				break;
			case 'method': // postings.php
			case 'dowhat': // private.php
				$_tackon .= ($_tackon ? '&' : '') . "$_var=$_value";
				break;
		}
	}
	if ($_tackon)
	{
		$wolpath .= '?' . $_tackon;
	}
}

define('WOLPATH', $wolpath);
define('SCRIPTPATH', $scriptpath);
define('SCRIPT', $script);

define('SESSION_IDHASH', md5($_SERVER['HTTP_USER_AGENT'] . ALT_IP )); // this should *never* change during a session
define('SESSION_HOST', substr(IPADDRESS, 0, 15));
define('USER_AGENT', $_SERVER['HTTP_USER_AGENT']);
define('REFERRER', $_SERVER['HTTP_REFERER']);

define('SAPI_NAME', php_sapi_name());

if (empty($_REQUEST['url']))
{
	$url = REFERRER;
}
else
{
	if ($_REQUEST['url'] == REFERRER)
	{
		$url = 'index.php';
	}
	else
	{
		$url = &$_REQUEST['url'];
	}
}
if ($url == SCRIPTPATH OR empty($url))
{
	$url = 'index.php';
}
$url = xss_clean($url);

define ('REFERRER_PASSTHRU', $url);

// #############################################################################
// deal with session bypass situation
if (!defined('SESSION_BYPASS'))
{
	if (!empty($_REQUEST['bypass']))
	{
		define('SESSION_BYPASS', 1);
	}
	else
	{
		define('SESSION_BYPASS', 0);
	}
}

// #############################################################################
// initialise variables and additional constants
unset(
	$_allowedvars,
	$_arrykey,
	$_arryval,
	$_superglobal,
	$vboptions,
	$vbtemplate,
	$session,
	$bbuserinfo,
	$forumcache,
	$threadcache,
	$foruminfo,
	$templatecache,
	$postcache,
	$threadidcache,
	$forumidcache,
	$postidcache,
	$useridcache,
	$urlSearchArray,
	$urlReplaceArray,
	$emailSearchArray,
	$emailReplaceArray,
	$iforumcache,
	$ipermcache,
	$iaccesscache,
	$usergroupdef,
	$noperms,
	$usergroupcache,
	$vars,
	$usercache,
	$forumarraycache,
	$permscache,
	$bb_cache_thread_lastview,
	$bb_cache_thread_rate,
	$bb_cache_poll_voted,
	$bb_cache_forum_lastview,
	$parsed_postcache
);

// standard defs
define('HTML_SELECTED', 'selected="selected"');
define('HTML_CHECKED', 'checked="checked"');

// this variable is used for template conditionals
$show = array();

// #############################################################################
// trim the $_POST['do'] string
if (isset($_POST['do']))
{
	$_POST['do'] = trim($_POST['do']);
}

// #############################################################################
// initialize database
if (empty($nodb))
{
	unset($vblocalurl, $vblocalopen, $debug); // Not needed with register_globals off but oh well :)

	// load config
	if (!file_exists('./includes/config.php'))
	{
		echo "includes/config.php does not exist. Cannot continue.";
		exit;
	}
	require('./includes/config.php');

	define('TABLE_PREFIX', $tableprefix);
	define('COOKIE_PREFIX', (empty($cookieprefix)) ? 'bb' : $cookieprefix);
	define('DEBUG', !empty($debug));
	$debug = !empty($debug);

	if ($debug)
	{
		if ($_GET['explain'] OR $_POST['explain'])
		{
			define('DB_EXPLAIN', true);
			define('DB_QUERIES', true);
		}
		elseif ($_GET['showqueries'] OR $_POST['showqueries'])
		{
			define('DB_EXPLAIN', false);
			define('DB_QUERIES', true);
		}
		else
		{
			define('DB_EXPLAIN', false);
			define('DB_QUERIES', false);
		}
	}
	else
	{
		define('DB_EXPLAIN', false);
		define('DB_QUERIES', false);
	}

	if (DB_QUERIES)
	{
		echo '<pre>';
	}

	// load db class -- supports only mysql at the moment
	require_once('./includes/db_mysql.php');

	$DB_site = new DB_Sql_vb;

	$DB_site->appname = 'vBulletin';
	$DB_site->appshortname = 'vBulletin (' . VB_AREA . ')';
	$DB_site->database = $dbname;

	$DB_site->connect($servername, $dbusername, $dbpassword, $usepconnect);

	// demo mode stuff
	if (defined('DEMO_MODE') AND DEMO_MODE AND function_exists('vbulletin_demo_init'))
	{
		vbulletin_demo_init();
	}

	unset($servername, $dbusername, $dbpassword, $usepconnect);
	// end init db

	// load options, special templates and default language
	$optionsfound = false;
	if (defined('VB3UPGRADE'))
	{
		// ###################### Load vB2 Style Options #######################
		$DB_site->reporterror = 0;
		$optionstemp = $DB_site->query_first("SELECT template FROM template WHERE title = 'options'");
		$DB_site->reporterror = 1;
		if ($optionstemp)
		{
			eval($optionstemp['template']);
			$versionnumber = $templateversion;
			$optionsfound = true;
		}
		// work out the absolute path to vBulletin
		$_var = @strpos($bburl, '/', 8);
	}
	if (!defined('VB3INSTALL') AND $optionsfound === false)
	{
		// initialise
		$datastore = array();
		$vboptions = array();
		$forumcache = array();
		$usergroupcache = array();
		$stylechoosercache = array();

		if (!is_array($specialtemplates))
		{
			$specialtemplates = array();
		}

		// add default special templates
		if (!CACHE_DATASTORE_FILE OR !defined('CACHE_DATASTORE_FILE'))
		{
			$specialtemplates = array_merge(array(
				'options',
				'cron',
				'forumcache',
				'usergroupcache',
				'stylecache'
			), $specialtemplates);
		}
		else
		{
			require_once('./includes/datastore_cache.php');
			$optionsfound = !empty($vboptions);
			$specialtemplates = array_merge(array(
				'cron'
			), $specialtemplates);
		}

		if (!empty($specialtemplates))
		{
			$datastoretemp = $DB_site->query("
				SELECT title, data
				FROM " . TABLE_PREFIX . "datastore
				WHERE title IN ('" . implode("', '", array_map('addslashes', $specialtemplates)) . "')
			");
			unset($specials, $specialtemplates);

			while ($storeitem = $DB_site->fetch_array($datastoretemp))
			{
				switch($storeitem['title'])
				{
					// get $vboptions array
					case 'options':
					{
						$vboptions = unserialize($storeitem['data']);
						if (is_array($vboptions) AND isset($vboptions['languageid']))
						{
							$optionsfound = true;
						}
						if ($url == 'index.php')
						{
							$url = "$vboptions[forumhome].php";
						}
						$versionnumber = &$vboptions['templateversion'];
					}
					break;

					// get $forumcache array
					case 'forumcache':
					{
						$forumcache = unserialize($storeitem['data']);
					}
					break;

					// get $usergroupcache array
					case 'usergroupcache':
					{
						$usergroupcache = unserialize($storeitem['data']);
					}
					break;

					// Wol Spider information
					case 'wol_spiders':
					{
						$datastore['wol_spiders'] = unserialize($storeitem['data']);
					}
					break;

					// smiliecache information
					case 'smiliecache':
					{
						$smiliecache = unserialize($storeitem['data']);
					}
					break;

					case 'stylecache':
					{
						$stylechoosercache = unserialize($storeitem['data']);
					}
					break;

					// stuff the data into the $datastore array
					default:
					{
						$datastore["$storeitem[title]"] = $storeitem['data'];
					}
					break;
				}
			}

			// Fatal Error
			if (!$optionsfound)
			{
				require_once('./includes/adminfunctions.php');
				require_once('./includes/functions.php');
				$vboptions = build_options();
				if ($url == 'index.php')
				{
					$url = "$vboptions[forumhome].php";
				}
				$versionnumber = &$vboptions['templateversion'];
			}

			unset($storeitem);
			$DB_site->free_result($datastoretemp);
		}
	}
}

// referrer check for POSTs
if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' AND !defined('SKIP_REFERRER_CHECK'))
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
		$referrer_parts = @parse_url($_SERVER['HTTP_REFERER']);
		$http_host = preg_replace('#^www\.#i', '', $http_host);
		$http_port = intval($referrer_parts['port']);
		$refhost = $referrer_parts['host'] . (!empty($http_port) ? ":$http_port" : '');

		if (!preg_match('#' . preg_quote($http_host, '#') . '$#siU', $refhost))
		{
			die('POST requests from foreign hosts are not allowed.');
		}
	}
}

// #############################################################################
// do a callback to modify any variables that might need modifying based on HTTP input
// eg: doing a conditional redirect based on a $goto value, and $noheader must be set
if (function_exists('exec_postvar_call_back'))
{
	exec_postvar_call_back();
}

// #############################################################################
// initialize bitfield values for permissions and options
// note: only permissions that are included in the usergroup table
// should be added as entries within the $_bitfield array.
$_BITFIELD = array();

// field names for forum permissions
$_BITFIELD['usergroup']['forumpermissions'] = array(
	'canview'           => 1,
	'canviewothers'     => 2,
	'cansearch'         => 4,
	'canemail'          => 8,
	'canpostnew'        => 16,
	'canreplyown'       => 32,
	'canreplyothers'    => 64,
	'caneditpost'       => 128,
	'candeletepost'     => 256,
	'candeletethread'   => 512,
	'canopenclose'      => 1024,
	'canmove'           => 2048,
	'cangetattachment'  => 4096,
	'canpostattachment' => 8192,
	'canpostpoll'       => 16384,
	'canvote'           => 32768,
	'canthreadrate'     => 65536,
	'isalwaysmoderated' => 131072,
	'canseedelnotice'   => 262144,
);

// field names for private message permissions
$_BITFIELD['usergroup']['pmpermissions'] = array(
	'cantrackpm'        => 1,
	'candenypmreceipts' => 2
);

// field names for calendar permissions
$_BITFIELD['usergroup']['calendarpermissions'] = array(
	'canviewcalendar'    => 1,
	'canpostevent'       => 2,
	'caneditevent'       => 4,
	'candeleteevent'     => 8,
	'canviewothersevent' => 16, // Set to no to make a "Private Calendar"
);

// field names for who's online permissions
$_BITFIELD['usergroup']['wolpermissions'] = array(
	'canwhosonline'         => 1,
	'canwhosonlineip'       => 2,
	'canwhosonlinefull'     => 4,
	'canwhosonlinebad'      => 8,
	'canwhosonlinelocation' => 16,
);

// field names for administrative permissions
$_BITFIELD['usergroup']['adminpermissions'] = array(
	'ismoderator'           => 1,
	'cancontrolpanel'       => 2,
	'canadminsettings'      => 4,
	'canadminstyles'        => 8,
	'canadminlanguages'     => 16,
	'canadminforums'        => 32,
	'canadminthreads'       => 64,
	'canadmincalendars'     => 128,
	'canadminusers'         => 256,
	'canadminpermissions'   => 512,
	'canadminfaq'           => 1024,
	'canadminimages'        => 2048,
	'canadminbbcodes'       => 4096,
	'canadmincron'          => 8192,
	'canadminmaintain'      => 16384,
	'canadminupgrade'       => 32768
);

// field names for general permissions
$_BITFIELD['usergroup']['genericpermissions'] = array(
	'canviewmembers'           => 1,
	'canmodifyprofile'         => 2,
	'caninvisible'             => 4,
	'canviewothersusernotes'   => 8,
	'canmanageownusernotes'    => 16,
	'canseehidden'             => 32,
	'canbeusernoted'           => 64,
	'canprofilepic'            => 128,
	'canseeraters'             => 256, // Permission removed in 3.0.2 ## Maintain backwards compatibility with 3.0.0 and 3.0.1
	'canuseavatar'             => 512,
	'canusesignature'          => 1024,
	'canusecustomtitle'        => 2048,
	'canseeprofilepic'         => 4096,
	'canviewownusernotes'	   => 8192,
	'canmanageothersusernotes' => 16384,
	'canpostownusernotes'      => 32768,
	'canpostothersusernotes'   => 65536,
	'caneditownusernotes'      => 131072,
	'canseehiddencustomfields' => 262144,

	// Reputation

	'canseeownrep'             => 256,
	'canuserep'                => 524288,
	'canhiderep'               => 1048576,
	'cannegativerep'           => 2097152,
	'canseeothersrep'          => 4194304,
	'canhaverepleft'           => 8388608,
);

// field names for usergroup display options
$_BITFIELD['usergroup']['genericoptions'] = array(
	'showgroup'             => 1,
	'showbirthday'          => 2,
	'showmemberlist'        => 4,
	'showeditedby'          => 8,
	'allowmembergroups'     => 16,
	'isbannedgroup'         => 32
);

// ### INSERT PLUGIN USERGROUP PERMISSIONS BITFIELDS HERE ###
// ----------------------------------------------------------

// ----------------------------------------------------------
// ###  END PLUGIN USERGROUP PERMISSIONS BITFIELDS HERE   ###

// now take all the usergroup bitfields and define constants for spot permission checks
foreach($_BITFIELD['usergroup'] AS $_permgroup)
{
	foreach($_permgroup AS $_constname => $_constval)
	{
		define(strtoupper($_constname), $_constval);
	}
}
unset($_constname, $_constval, $_permgroup);

// Calendar Moderator permissions
$_BITFIELD['calmoderatorpermissions'] = array(
	'caneditevents'         => 1,
	'candeleteevents'       => 2,
	'canmoderateevents'     => 4,
	'canviewips'            => 8,
	'canmoveevents'         => 16
);

// Forum Moderator permissions
$_BITFIELD['moderatorpermissions'] = array(
	'caneditposts'           => 1,
	'candeleteposts'         => 2,
	'canopenclose'           => 4,
	'caneditthreads'         => 8,
	'canmanagethreads'       => 16,
	'canannounce'            => 32,
	'canmoderateposts'       => 64,
	'canmoderateattachments' => 128,
	'canmassmove'            => 256,
	'canmassprune'           => 512,
	'canviewips'             => 1024,
	'canviewprofile'         => 2048,
	'canbanusers'            => 4096,
	'canunbanusers'          => 8192,
	'newthreademail'         => 16384,
	'newpostemail'           => 32768,
	'cansetpassword'         => 65536,
	'canremoveposts'         => 131072,
	'caneditsigs'            => 262144,
	'caneditavatar'          => 524288,
	'caneditpoll'            => 1048576,
	'caneditprofilepic'      => 2097152,
	'caneditreputation'      => 4194304
);

// Language options bitfields (not really needed at this time; here for expansion purposes)
$_BITFIELD['languageoptions'] = array(
	'direction' => 1 // on = left-to-right, off = right-to-left
);

// inheritable non-bitfield permissions:
// 0 => indicates that a 0 value does not take precedence over greater values, ex "0 disables this option"
// 1 => indicates that a 0 values takes precedence over greater values, ex "Set to 0 for unlimited"
$_INTPERMS = array(
	'pmquota'               => 0,
	'pmsendmax'             => 0,
	'attachlimit'           => 1,
	'avatarmaxwidth'        => 1,
	'avatarmaxheight'       => 1,
	'avatarmaxsize'         => 1,
	'profilepicmaxwidth'    => 1,
	'profilepicmaxheight'   => 1,
	'profilepicmaxsize'     => 1
);

// Defined constants used for user field.
$_USEROPTIONS = array(
	'showsignatures'    => 1,
	'showavatars'       => 2,
	'showimages'        => 4,
	'coppauser'         => 8,
	'adminemail'        => 16,
	'showvcard'         => 32,
	'dstauto'           => 64,
	'dstonoff'          => 128,
	'showemail'         => 256,
	'invisible'         => 512,
	'showreputation'    => 1024,
	'receivepm'         => 2048,
	'emailonpm'         => 4096,
	'hasaccessmask'     => 8192,
	//'emailnotification' => 16384, // this value is now handled by the user.autosubscribe field
	'postorder'         => 32768,
);

// Defined contants used for forum field.
$_FORUMOPTIONS = array(
	'active'			=> 1,
	'allowposting'      => 2,
	'cancontainthreads' => 4,
	'moderatenewpost'   => 8,
	'moderatenewthread' => 16,
	'moderateattach'    => 32,
	'allowbbcode'       => 64,
	'allowimages'       => 128,
	'allowhtml'         => 256,
	'allowsmilies'      => 512,
	'allowicons'        => 1024,
	'allowratings'      => 2048,
	'countposts'        => 4096,
	'canhavepassword'   => 8192,
	'indexposts'        => 16384,
	'styleoverride'     => 32768,
	'showonforumjump'   => 65536,
	'warnall'           => 131072
);

define('SIMPLE_VERSION', preg_replace('#[^\d]#s', '', $vboptions['templateversion']));

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15856 $
|| ####################################################################
\*======================================================================*/
?>