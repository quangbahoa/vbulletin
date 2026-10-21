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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'install.php');
define('VERSION', '3.5.8'); // change this in class_core as well!

define('TIMENOW', time());

// ########################## REQUIRE BACK-END ############################

if ($_GET['step'] > 2 OR $_POST['step'] > 2)
{
	require_once('./installcore.php');
	// connected to the database now lets load schema
	require_once(DIR . '/install/mysql-schema.php');
}
else
{
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
	define('SCRIPTPATH', $scriptpath);
	define('SKIPDB', true);

	require_once('./installcore.php');
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if ($vbulletin->GPC['step'] == 'welcome')
{
	echo "<blockquote>\n";
	echo $install_phrases['welcome'];
	if (function_exists('mmcache_get'))
	{
		echo $install_phrases['turck'];
	}
	echo "</blockquote>\n";
}

if ($vbulletin->GPC['step'] == 1)
{
	if (!file_exists(DIR . '/includes/config.php'))
	{
		$vbulletin->GPC['step']--;
		echo "<p>{$install_phrases['cant_find_config']}</p>";
	}
	else if (!is_readable(DIR . '/includes/config.php'))
	{
		$vbulletin->GPC['step']--;
		echo "<p>{$install_phrases['cant_read_config']}</p>";
	}
	else
	{
		echo "<p>{$install_phrases['config_exists']}</p>";
	}
}

if ($vbulletin->GPC['step'] == 2)
{
	// turn off errors
	$db->hide_errors();

	if (!function_exists($db->functions['connect']))
	{
		echo "<p>" . sprintf($install_phrases['database_functions_not_detected'], $vbulletin->config['Database']['dbtype']) . "</p>";
		define('HIDEPROCEED', true);
	}
	else
	{
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
			$vbulletin->config['SlaveServer']['usepconnect']
		);
		if (!empty($vbulletin->config['Database']['force_sql_mode']) AND $db->connection_write)
		{
			$db->force_sql_mode('');
			// small hack to prevent the above query from generating an error below
			$db->query('SELECT 1 + 1');
		}

		echo "<p>{$install_phrases['attach_to_db']}</p>";

		if ($db->connection_write)
		{
			if ($db->errno())
			{ // error found
				if ($db->errno() == 1049)
				{
					echo "<p>{$install_phrases['no_db_found_will_create']}</p>";
					$db->query_write("CREATE DATABASE " . $vbulletin->config['Database']['dbname']);
					echo "<p>{$install_phrases['attempt_to_connect_again']}</p>";
					$db->select_db($vbulletin->config['Database']['dbname']);
					if ($db->errno() == 1049)
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
					echo "<p>" . sprintf($install_phrases['db_error_num'], $db->errno()) . "</p>";
					echo "<p>" . sprintf($install_phrases['db_error_desc'], $db->error()) . "</p>";
					echo "<p>{$install_phrases['check_dbserver']}</p>";
					define('HIDEPROCEED', true);
				}
			}
			else
			{ // connection suceeded and database already exists
				echo "<p>{$install_phrases['connection_succeeded']}</p>";

				if (empty($vbulletin->config['Database']['force_sql_mode']))
				{
					// check to see if MySQL is running strict mode and recommend disabling it
					$strict_mode_check = $db->query_first("SHOW VARIABLES LIKE 'sql\_mode'");
					if (strpos(strtolower($strict_mode_check['Value']), 'strict_') !== false)
					{
						echo "<p><strong>{$install_phrases['mysql_strict_mode']}</strong></p>";
						print_upgrade_footer();
						exit;

					}
				}

				// see if there's a user table already
				$db->query_write("SHOW FIELDS FROM " . $vbulletin->config['Database']['tableprefix'] . "user");
				if ($db->errno() == 0)
				{ // echo vB already exists message
					define('HIDEPROCEED', true);
					echo "<p><font size=\"+1\"><b>{$install_phrases['vb_installed_maybe_upgrade']}</b></font></p>";
				}

				echo "<p>{$install_phrases['wish_to_empty_db']}</p>";
			}
		}
		else
		{ // Unable to connect to database
			echo "<p>" . sprintf($install_phrases['db_error_desc'], $db->error) . "</p>";
			echo "<p><font size=\"+1\" color=\"red\"><b>{$install_phrases['no_connect_permission']}</b></font></p>";
			define('HIDEPROCEED', true);
		}
	}
	// end init db
}

if ($vbulletin->GPC['step'] == 3)
{
	$vbulletin->input->clean_array_gpc('r', array(
		'emptydb' => TYPE_BOOL,
		'confirm' => TYPE_BOOL,
	));

	if ($vbulletin->GPC['emptydb'])
	{
		if (!$vbulletin->GPC['confirm'])
		{
			$skipstep = true;
			echo $install_phrases['empty_db'];
			define('HIDEPROCEED', true);
		}
		else
		{
			echo "<p>{$install_phrases['resetting_db']} ";
			$result = $db->query_write("SHOW tables");
			while ($currow = $db->fetch_array($result, DBARRAY_NUM))
			{
				$db->query_write("DROP TABLE IF EXISTS $currow[0]");
			}
			echo $install_phrases['succeeded'] . '</p>';
		}
	}
	if (!$skipstep)
	{
		$query =& $schema['CREATE']['query'];
		$explain =& $schema['CREATE']['explain'];
		exec_queries();
		if ($db->errno())
		{
			echo "<p>{$install_phrases['script_reported_errors']}</p>";
			echo "<p>{$install_phrases['errors_were']}</p>";
			echo "<p>" . sprintf($install_phrases['db_error_num'], $db->errno()) . "</p>";
			echo "<p>" . sprintf($install_phrases['db_error_desc'], $db->error()) . "</p>";
		}
		else
		{
			echo "<p>{$install_phrases['tables_setup']}</p>";
		}
	}
}

if ($vbulletin->GPC['step'] == 4)
{
	$query =& $schema['ALTER']['query'];
	$explain =& $schema['ALTER']['explain'];
	exec_queries();
}

if ($vbulletin->GPC['step'] == 5)
{
	$query =& $schema['INSERT']['query'];
	$explain =& $schema['INSERT']['explain'];
	exec_queries();
}

if ($vbulletin->GPC['step'] == 6)
{
	require_once(DIR . '/includes/adminfunctions_language.php');

	if (!($xml = file_read(DIR . '/install/vbulletin-language.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-language.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-language.xml');

	xml_import_language($xml);
	build_language();
	build_language_datastore();
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

if ($vbulletin->GPC['step'] == 7)
{
	require_once(DIR . '/includes/adminfunctions_template.php');

	if (!($xml = file_read(DIR . '/install/vbulletin-style.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-style.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-style.xml');

	xml_import_style($xml);
	build_all_styles(0, 1);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

if ($vbulletin->GPC['step'] == 8)
{
	require_once(DIR . '/includes/adminfunctions_help.php');

	if (!($xml = file_read(DIR . '/install/vbulletin-adminhelp.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-adminhelp.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-adminhelp.xml');

	xml_import_help_topics($xml);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

if ($vbulletin->GPC['step'] == 9)
{
	require_once(DIR . '/includes/adminfunctions_options.php');

	define('HIDEPROCEED', true);
	$port = ((!empty($_SERVER['SERVER_PORT']) AND $_SERVER['SERVER_PORT'] != 80) ? ':' . intval($_SERVER['SERVER_PORT']) : '');
	$vboptions['bburl'] = 'http://' . $_SERVER['SERVER_NAME'] . $port . substr(SCRIPTPATH,0, strpos(SCRIPTPATH, '/install/'));
	$vboptions['homeurl'] = 'http://' . $_SERVER['SERVER_NAME'] . $port;
	$webmaster = 'webmaster@' . preg_replace('#^www\.#', '', $_SERVER['SERVER_NAME']);

	print_form_header(substr(THIS_SCRIPT, 0, -strlen('.php')), '');
	construct_hidden_code('step', ($vbulletin->GPC['step'] + 1));
	print_table_header($install_phrases['general_settings']);
	print_input_row($install_phrases['bbtitle'], 'vboptions[bbtitle]', 'Forums', 0, 40);
	print_input_row($install_phrases['hometitle'], 'vboptions[hometitle]', '', 0, 40);
	print_input_row($install_phrases['bburl'], 'vboptions[bburl]', $vboptions['bburl'], 0, 40);
	print_input_row($install_phrases['homeurl'], 'vboptions[homeurl]', $vboptions['homeurl'], 0, 40);
	print_input_row($install_phrases['webmasteremail'], 'vboptions[webmasteremail]', $webmaster, 0, 40);
	print_label_row($install_phrases['cookiepath'], '
		<fieldset>
			<legend>' . $install_phrases['suggested_settings'] . '</legend>
			<div style="padding:4px">
				<select name="vboptions[cookiepath]" tabindex="1" class="bginput">' .
					construct_select_options(fetch_valid_cookiepaths($vbulletin->script), '/') . '
				</select>
			</div>
		</fieldset>
		<br />
		<fieldset>
			<legend>' . $install_phrases['custom_setting'] . '</legend>
			<div style="padding:4px">
				<label for="cookiepatho"><input type="checkbox" id="cookiepatho" name="cookiepath_other" tabindex="1" value="1" />' . $install_phrases['use_custom_setting'] . '
				</label><br />
				<input type="text" class="bginput" size="25" name="cookiepath_value" value="" />
			</div>
		</fieldset>
	');
	print_label_row($install_phrases['cookiedomain'], '
		<fieldset>
			<legend>' . $install_phrases['suggested_settings'] . '</legend>
			<div style="padding:4px">
				<select name="vboptions[cookiedomain]" tabindex="1" class="bginput">' .
					construct_select_options(fetch_valid_cookiedomains($_SERVER['HTTP_HOST'], $install_phrases['blank']), '') . '
				</select>
			</div>
		</fieldset>
		<br />
		<fieldset>
			<legend>' . $install_phrases['custom_setting'] . '</legend>
			<div style="padding:4px">
				<label for="cookiedomaino"><input type="checkbox" id="cookiedomaino" name="cookiedomain_other" tabindex="1" value="1" />' . $install_phrases['use_custom_setting'] . '
				</label><br />
				<input type="text" class="bginput" size="25" name="cookiedomain_value" value="" />
			</div>
		</fieldset>
	');
	print_submit_row($vbphrase['proceed'], $vbphrase['reset']);

}

if ($vbulletin->GPC['step'] == 10)
{
	require_once(DIR . '/includes/adminfunctions_options.php');

	$vbulletin->input->clean_array_gpc('p', array(
		'vboptions' => TYPE_ARRAY_NOHTML,
		'cookiepath_other' => TYPE_BOOL,
		'cookiepath_value' => TYPE_NOHTML,
		'cookiedomain_other' => TYPE_BOOL,
		'cookiedomain_value' => TYPE_NOHTML
	));

	$vbulletin->options =& $vbulletin->GPC['vboptions'];

	if (!($xml = file_read(DIR . '/install/vbulletin-settings.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-settings.xml') . '</p>';
		print_cp_footer();
	}

	if ($vbulletin->GPC['cookiepath_other'] AND $vbulletin->GPC['cookiepath_value'])
	{
		$vbulletin->options['cookiepath'] = $vbulletin->GPC['cookiepath_value'];
	}
	if ($vbulletin->GPC['cookiedomain_other'] AND $vbulletin->GPC['cookiedomain_value'])
	{
		$vbulletin->options['cookiedomain'] = $vbulletin->GPC['cookiedomain_value'];
	}

	$gdinfo = fetch_gdinfo();
	if ($gdinfo['version'] >= 2)
	{
		$vbulletin->options['gdversion'] = 2;
	}
	else if ($gdinfo['version'] >= 1)
	{
		$vbulletin->options['gdversion'] = 1;
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-settings.xml');

	xml_import_settings($xml);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

if ($vbulletin->GPC['step'] == 11)
{
	define('HIDEPROCEED', true);

	print_form_header(substr(THIS_SCRIPT, 0, -strlen('.php')), '');
	construct_hidden_code('step', ($vbulletin->GPC['step'] + 1));
	print_table_header("{$install_phrases['fill_in_for_admin_account']}");
	print_input_row("<b>{$install_phrases['username']}</b>", 'username', '');
	print_password_row("<b>{$install_phrases['password']}</b>", 'password', '');
	print_password_row("<b>{$install_phrases['confirm_password']}</b>", 'confirmpassword', '');
	print_input_row("<b>{$install_phrases['email_address']}</b>", 'email', '');
	print_submit_row($vbphrase['proceed'], $vbphrase['reset']);
}

if ($vbulletin->GPC['step'] == 12)
{
	$vbulletin->input->clean_array_gpc('p', array(
		'username' => TYPE_STR,
		'password' => TYPE_STR,
		'confirmpassword' => TYPE_STR,
		'email' => TYPE_STR,
	));

	if (empty($vbulletin->GPC['username']) OR empty($vbulletin->GPC['password']) OR empty($vbulletin->GPC['confirmpassword']) OR empty($vbulletin->GPC['email']))
	{
		$vbulletin->GPC['step'] = $vbulletin->GPC['step'] - 2;
		echo "<p>{$install_phrases['complete_all_data']}</p>";
	}
	else if ($vbulletin->GPC['password'] != $vbulletin->GPC['confirmpassword'])
	{
		$vbulletin->GPC['step'] = $vbulletin->GPC['step'] - 2;
		echo "<p>{$install_phrases['password_not_match']}</p>";
	}
	else
	{
		require_once(DIR . '/includes/functions_user.php');
		$salt = fetch_user_salt(3);
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "user
				(username, salt, password, email, joindate, lastvisit, lastactivity, usergroupid, passworddate, options, showvbcode)
			VALUES
				('" . $db->escape_string(htmlspecialchars_uni($vbulletin->GPC['username'])) . "', '" . $db->escape_string($salt) . "', '" . $db->escape_string(md5(md5($vbulletin->GPC['password']) . $salt)) . "', '" . $db->escape_string($vbulletin->GPC['email']) . "', " . TIMENOW . ", " . TIMENOW . ", " . TIMENOW . ", 6, FROM_UNIXTIME(" . TIMENOW . "), 2135, 2)
		");
		$userid = $db->insert_id();
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "usertextfield
				(userid)
			VALUES
				($userid)
		");
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "userfield
				(userid)
			VALUES
				($userid)
		");
		/*insert query*/
		$db->query_write("INSERT INTO " . TABLE_PREFIX . "administrator
			(userid, adminpermissions)
		VALUES
			($userid, " . (array_sum($vbulletin->bf_ugp_adminpermissions)-3) . ")
		");
		echo "<p>{$install_phrases['admin_added']}</p>";
	}
}

if ($vbulletin->GPC['step'] == 13)
{
	build_image_cache('smilie');
	build_image_cache('avatar');
	build_image_cache('icon');
	build_bbcode_cache();
	require_once(DIR . '/includes/functions_databuild.php');
	build_user_statistics();
	build_forum_child_lists();
	build_forum_permissions();
	require_once(DIR . '/includes/functions_cron.php');
	build_cron_next_run();
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	echo "<blockquote>\n";
	echo "<p>" . sprintf($install_phrases['install_complete'], $vbulletin->config['Misc']['admincpdir']) . "</p>\n";
	echo "</blockquote>\n";

	define('HIDEPROCEED', true);
}

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 16482 $
|| ####################################################################
\*======================================================================*/
?>