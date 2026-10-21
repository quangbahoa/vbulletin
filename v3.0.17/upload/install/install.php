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

define('THIS_SCRIPT', 'install.php');
define('VERSION', '3.0.17'); // note, also check the bottom of includes/init.php for SIMPLE_VERSION definition
chdir('./../');

$phrasegroups = array();
$specialtemplates = array();

if ($HTTP_GET_VARS['step'] > 2 OR $HTTP_POST_VARS['step'] > 2 OR $_GET['step'] > 2 OR $_POST['step'] > 2)
{

	define('VB3INSTALL', true);
	require_once('./includes/init.php');
	require_once('./install/installcore.php');
	// connected to the database now lets load schema
	require_once('./install/mysql-schema.php');
}
else
{
	if (PHP_VERSION < '4.1.0')
	{
		$_GET = &$HTTP_GET_VARS;
		$_POST = &$HTTP_POST_VARS;
		$_COOKIE = &$HTTP_COOKIE_VARS;
		$_SERVER = &$HTTP_SERVER_VARS;
		$_ENV = &$HTTP_ENV_VARS;
		$_FILES = &$HTTP_POST_FILES;
		$_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
	}

	if ($HTTP_ENV_VARS['REQUEST_URI'] OR $HTTP_SERVER_VARS['REQUEST_URI'])
	{
		$scriptpath = $HTTP_SERVER_VARS['REQUEST_URI'] ? $HTTP_SERVER_VARS['REQUEST_URI'] : $HTTP_ENV_VARS['REQUEST_URI'];
	}
	else
	{
		if ($HTTP_ENV_VARS['PATH_INFO'] OR $HTTP_SERVER_VARS['PATH_INFO'])
		{
			$scriptpath = $HTTP_SERVER_VARS['PATH_INFO'] ? $HTTP_SERVER_VARS['PATH_INFO']: $HTTP_ENV_VARS['PATH_INFO'];
		}
		else if ($HTTP_ENV_VARS['REDIRECT_URL'] OR $HTTP_SERVER_VARS['REDIRECT_URL'])
		{
			$scriptpath = $HTTP_SERVER_VARS['REDIRECT_URL'] ? $HTTP_SERVER_VARS['REDIRECT_URL']: $HTTP_ENV_VARS['REDIRECT_URL'];
		}
		else
		{
			$scriptpath = $HTTP_SERVER_VARS['PHP_SELF'] ? $HTTP_SERVER_VARS['PHP_SELF'] : $HTTP_ENV_VARS['PHP_SELF'];
		}

		if ($HTTP_ENV_VARS['QUERY_STRING'] OR $HTTP_SERVER_VARS['QUERY_STRING'])
		{
			$scriptpath .= '?' . ($HTTP_SERVER_VARS['QUERY_STRING'] ? $HTTP_SERVER_VARS['QUERY_STRING'] : $HTTP_ENV_VARS['QUERY_STRING']);
		}
	}
	define('SCRIPTPATH', $scriptpath);
	require_once('./install/installcore.php');
}

// #############################################################################
if ($step == 'welcome')
{
	echo "<blockquote>\n";
	echo $install_phrases['welcome'];
	echo "</blockquote>\n";
}

if ($step == 1)
{
	if (!file_exists('./includes/config.php'))
	{
		$step = $step - 1;
		echo "<p>{$install_phrases['cant_find_config']}</p>";
	}
	else if (!is_readable('./includes/config.php'))
	{
		$step = $step - 1;
		echo "<p>{$install_phrases['cant_read_config']}</p>";
	}
	else
	{
		echo "<p>{$install_phrases['config_exists']}</p>";
	}
}

if ($step == 2)
{
	require_once('./includes/config.php');

	define('DB_EXPLAIN', false);
	define('DB_QUERIES', false);

	// load db class -- supports only mysql at the moment
	require_once('./includes/db_mysql.php');

	$DB_site = new DB_Sql_vb;

	$DB_site->appname = 'vBulletin';
	$DB_site->appshortname = 'vBulletin (' . VB_AREA . ')';
	$DB_site->database = $dbname;

	// turn off errors
	$DB_site->reporterror = 0;

	echo "<p>{$install_phrases['attach_to_db']}</p>";

	$DB_site->connect($servername, $dbusername, $dbpassword, $usepconnect);
	unset($servername, $dbusername, $dbpassword, $usepconnect);
	$errno = $DB_site->geterrno();

	if ($DB_site->link_id)
	{
		if ($errno)
		{ // error found
			if ($errno == 1049)
			{
				echo "<p>{$install_phrases['no_db_found_will_create']}</p>";
				$DB_site->query("CREATE DATABASE $dbname");
				echo "<p>{$install_phrases['attempt_to_connect_again']}</p>";
				$DB_site->select_db($dbname);
				$errno = $DB_site->geterrno();
				if ($errno == 1049)
				{ // unable to create database
					echo "<p>{$install_phrases['unable_to_create_db']}</p>";
					define('HIDEPROCEED', true);
				}
				else
				{
					echo "<p>{$install_phrases['database_creation_successful']}</p>";
				}
			}
			else
			{ // Unknown Error
				echo "<p>{$install_phrases['connect_failed']}</p>";
				echo "<p>" . sprintf($install_phrases['db_error_num'], $DB_site->errno) . "</p>";
				echo "<p>" . sprintf($install_phrases['db_error_desc'], $DB_site->errdesc) . "</p>";
				echo "<p>{$install_phrases['check_dbserver']}</p>";
				define('HIDEPROCEED', true);
			}
		}
		else
		{ // connection suceeded and database already exists
			echo "<p>{$install_phrases['connection_succeeded']}</p>";
			$DB_site->query("SHOW FIELDS FROM " . $tableprefix . "user");
			if ($DB_site->geterrno() == 0)
			{ // echo vB already exists message
				echo "<p><font size=\"+1\"><b>{$install_phrases['vb_installed_maybe_upgrade']}</b></font></p>";
			}
			echo "<p>{$install_phrases['wish_to_empty_db']}</p>";
		}
	}
	else
	{ // Unable to connect to database
		echo "<p><font size=\"+1\" color=\"red\"><b>{$install_phrases['no_connect_permission']}</b></font></p>";
		define('HIDEPROCEED', true);
	}
	// end init db
}

if ($step == 3)
{
	if ($_GET['emptydb'])
	{
		if (!$_GET['confirm'])
		{
			$skipstep = true;
			echo $install_phrases['empty_db'];
			define('HIDEPROCEED', true);
		}
		else
		{
			echo "<p>{$install_phrases['resetting_db']} ";
			$result = $DB_site->query("SHOW tables");
			while ($currow = $DB_site->fetch_array($result, DBARRAY_NUM))
			{
				$DB_site->query("DROP TABLE IF EXISTS $currow[0]");
			}
			echo $install_phrases['succeeded'] . '</p>';
		}
	}
	if (!$skipstep)
	{
		$query = &$schema['CREATE']['query'];
		$explain = &$schema['CREATE']['explain'];
		exec_queries();
		if ($DB_site->errno)
		{
			echo "<p>{$install_phrases['script_reported_errors']}</p>";
			echo "<p>{$install_phrases['errors_were']}</p>";
			echo "<p>" . sprintf($install_phrases['db_error_num'], $DB_site->errno) . "</p>";
			echo "<p>" . sprintf($install_phrases['db_error_desc'], $DB_site->errdesc) . "</p>";
		}
		else
		{
			echo "<p>{$install_phrases['tables_setup']}</p>";
		}
	}
}

if ($step == 4)
{
	$query = &$schema['ALTER']['query'];
	$explain = &$schema['ALTER']['explain'];
	exec_queries();
}

if ($step == 5)
{
	$query = &$schema['INSERT']['query'];
	$explain = &$schema['INSERT']['explain'];
	exec_queries();
}

if ($step == 6)
{
	require_once('./includes/adminfunctions_language.php');

	if (!($xml = file_read('./install/vbulletin-language.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-language.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-language.xml');

	xml_import_language($xml);
	build_language();
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

if ($step == 7)
{
	require_once('./includes/adminfunctions_template.php');

	if (!($xml = file_read('./install/vbulletin-style.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-style.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-style.xml');

	xml_import_style($xml);
	build_all_styles(0, 1);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

if ($step == 8)
{
	require_once('./includes/adminfunctions_help.php');

	if (!($xml = file_read('./install/vbulletin-adminhelp.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-adminhelp.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-adminhelp.xml');

	xml_import_help_topics($xml);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

if ($step == 9)
{
	define('HIDEPROCEED', true);
	$port = ((!empty($_SERVER['SERVER_PORT']) AND $_SERVER['SERVER_PORT'] != 80) ? ':' . intval($_SERVER['SERVER_PORT']) : '');
	$vboptions['bburl'] = 'http://' . $_SERVER['SERVER_NAME'] . $port . substr(SCRIPTPATH,0, strpos(SCRIPTPATH, '/install/'));
	$vboptions['homeurl'] = 'http://' . $_SERVER['SERVER_NAME'];
	$webmaster = 'webmaster@' . preg_replace('#^www\.#', '', $_SERVER['SERVER_NAME']);

	print_form_header(substr(THIS_SCRIPT, 0, -strlen('.php')), '');
	construct_hidden_code('step', ($step + 1));
	print_table_header($install_phrases['general_settings']);
	print_input_row($install_phrases['bbtitle'], 'vboptions[bbtitle]', 'Forums');
	print_input_row($install_phrases['hometitle'], 'vboptions[hometitle]', '');
	print_input_row($install_phrases['bburl'], 'vboptions[bburl]', $vboptions['bburl']);
	print_input_row($install_phrases['homeurl'], 'vboptions[homeurl]', $vboptions['homeurl']);
	print_input_row($install_phrases['webmasteremail'], 'vboptions[webmasteremail]', $webmaster);
	print_input_row($install_phrases['cookiepath'], 'vboptions[cookiepath]', '/');
	print_input_row($install_phrases['cookiedomain'], 'vboptions[cookiedomain]', '');
	print_submit_row($vbphrase['proceed'], $vbphrase['reset']);

}

if ($step == 10)
{
	require_once('./includes/adminfunctions_options.php');

	$vboptions = $_POST['vboptions'];

	if (!($xml = file_read('./install/vbulletin-settings.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-settings.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-settings.xml');

	xml_import_settings($xml);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

if ($step == 11)
{
	define('HIDEPROCEED', true);

	print_form_header(substr(THIS_SCRIPT, 0, -strlen('.php')), '');
	construct_hidden_code('step', ($step + 1));
	print_table_header("{$install_phrases['fill_in_for_admin_account']}");
	print_input_row("<b>{$install_phrases['username']}</b>", 'username', '');
	print_password_row("<b>{$install_phrases['password']}</b>", 'password', '');
	print_password_row("<b>{$install_phrases['confirm_password']}</b>", 'confirmpassword', '');
	print_input_row("<b>{$install_phrases['email_address']}</b>", 'email', '');
	print_submit_row($vbphrase['proceed'], $vbphrase['reset']);
}

if ($step == 12)
{
	if (empty($_POST['username']) OR empty($_POST['password']) OR empty($_POST['confirmpassword']) OR empty($_POST['email']))
	{
		$step = $step - 2;
		echo "<p>{$install_phrases['complete_all_data']}</p>";
	}
	else if ($_POST['password'] != $_POST['confirmpassword'])
	{
		$step = $step - 2;
		echo "<p>{$install_phrases['password_not_match']}</p>";
	}
	else
	{
		require_once('./includes/functions_user.php');
		$salt = fetch_user_salt(3);
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "user
				(username, salt, password, email, joindate, lastvisit, lastactivity, usergroupid, passworddate, options, showvbcode)
			VALUES
				('" . addslashes($_POST['username']) . "', '" . addslashes($salt) . "', '" . addslashes(md5(md5($_POST['password']) . $salt)) . "', '" . addslashes($_POST['email']) . "', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 6, NOW(), 2135, 2)
		");
		$userid = $DB_site->insert_id();
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "usertextfield
				(userid)
			VALUES
				($userid)
		");
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "userfield
				(userid)
			VALUES
				($userid)
		");
		$DB_site->query("INSERT INTO " . TABLE_PREFIX . "administrator
			(userid, adminpermissions)
		VALUES
			($userid, " . (array_sum($_BITFIELD['usergroup']['adminpermissions'])-3) . ")
		");
		echo "<p>{$install_phrases['admin_added']}</p>";
	}
}

if ($step == 13)
{
	build_image_cache('smilie');
	build_image_cache('avatar');
	build_image_cache('icon');
	build_bbcode_cache();
	require_once('./includes/functions_databuild.php');
	build_user_statistics();
	build_forum_child_lists();
	build_forum_permissions();
	require_once('./includes/functions_cron.php');
	build_cron_next_run();

	echo "<blockquote>\n";
	echo "<p>" . sprintf($install_phrases['install_complete'], $admincpdir) . "</p>\n";
	echo "</blockquote>\n";

	define('HIDEPROCEED', true);
}

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15912 $
|| ####################################################################
\*======================================================================*/
?>