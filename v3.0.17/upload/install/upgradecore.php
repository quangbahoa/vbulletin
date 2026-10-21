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
ignore_user_abort(true);
define('NO_IMPORT_DOTS', true);
define('VB_AREA', 'Upgrade');

// try to force display_errors on
@ini_set('display_errors', true);

// #########################################################################
// ########################## VSERVERS SETTING #############################
// ####### Set this to 1 if you're on Vservers and get disconnected ########
// ############## after running an ALTER TABLE command #####################
// #########################################################################
$onvservers = 0;
// #########################################################################

// attempt to extend php timeout limit
if (function_exists('set_time_limit') AND get_cfg_var('safe_mode') == 0)
{
	@set_time_limit(0);
}

// disable shutdown functions
define('NOSHUTDOWNFUNC', 1);

// check for valid php version
verify_vb3_enviroment();

// require necessary files
chdir('../');
require_once('./includes/init.php');
require_once('./includes/functions.php');
$vboptions['nocacheheaders'] = 1;
exec_headers();
require_once('./includes/adminfunctions.php');

// globalize and initialize variables
globalize($_REQUEST, array(
	'do' => STR,
	'step' => STR,
	'startat' => INT,
	'perpage' => INT
));

$step = iif(empty($step), 'welcome', intval($step));
$nozip = true;
$query = array();
$explain = array();
$phrasegroups = array('cpglobal');

// login code
if (THIS_SCRIPT == 'upgrade1.php') # and $step < sizeof($steptitles)
{
	// vb2 admin authentication
	//verify_vb2_login();
	$stylevar = array(
		'textdirection' => 'ltr',
		'left' => 'left',
		'right' => 'right',
		'languagecode' => 'en',
		'charset' => 'ISO-8859-1'
	);
}
else
{
	// vb3 admin authentication
	require_once('./includes/sessions.php');

	// initialize $vbphrase and set language constants
	$vbphrase = init_language();
	$_tmp = NULL;
	$stylevar = fetch_stylevars($_tmp, $bbuserinfo);
}
require_once('./install/upgrade_language_en.php');

// fetch step titles and die if they aren't found
$steptitles = $upgrade_phrases[THIS_SCRIPT]['steps'];
if (empty($steptitles))
{
	die($upgradecore_phrases['step_titles_undefined']);
}

// authenticate with customer number
require_once('./install/authenticate.php');

// assuming we've got through the authentication process, show the upgradeHeader.
if (empty($do))
{
	print_upgrade_header(fetch_step_title($step));
}

// ***************************************************************************************************************************

// #############################################################################
// backup system
if ($_REQUEST['step'] == 'backup')
{
	// #########################################################################
	// dumps an sql table
	function fetch_table_dump_sql($table)
	{
		global $DB_site;

		$tabledump = "DROP TABLE IF EXISTS $table;\n";
		$tabledump .= "CREATE TABLE $table (\n";

		$firstfield = 1;

		// get columns and spec
		$fields = $DB_site->query("SHOW FIELDS FROM $table");
		while ($field = $DB_site->fetch_array($fields, MYSQL_BOTH))
		{
			if (!$firstfield)
			{
				$tabledump .= ",\n";
			}
			else
			{
				$firstfield=0;
			}
			$tabledump .= " $field[Field] $field[Type]";
			if (!empty($field["Default"]))
			{
				// get default value
				$tabledump .= " DEFAULT '$field[Default]'";
			}
			if ($field['Null'] != "YES")
			{
				// can field be null
				$tabledump .= " NOT NULL";
			}
			if ($field['Extra'] != "")
			{
				// any extra info?
				$tabledump .= " $field[Extra]";
			}
		}

		// get keys list
		$keys = $DB_site->query("SHOW KEYS FROM $table");
		while ($key = $DB_site->fetch_array($keys, MYSQL_BOTH))
		{
			$kname = $key['Key_name'];
			if ($kname != "PRIMARY" and $key['Non_unique'] == 0)
			{
				$kname = "UNIQUE|$kname";
			}
			if(!is_array($index["$kname"]))
			{
				$index["$kname"] = array();
			}
			$index["$kname"][] = $key['Column_name'];
		}

		// get each key info
		if (is_array($index))
		{
			foreach ($index as $kname => $columns)
			{
				$tabledump .= ",\n";
				$colnames = implode($columns,",");

				if($kname == "PRIMARY"){
					// do primary key
					$tabledump .= " PRIMARY KEY ($colnames)";
				}
				else
				{
					// do standard key
					if (substr($kname,0,6) == 'UNIQUE')
					{
						// key is unique
						$kname = substr($kname,7);
					}

					$tabledump .= " KEY $kname ($colnames)";

				}
			}
		}

		$tabledump .= "\n);\n\n";

		// get data
		$rows = $DB_site->query("SELECT * FROM $table");
		$numfields = mysql_num_fields($rows);
		while ($row = $DB_site->fetch_array($rows, MYSQL_BOTH))
		{
			$tabledump .= "INSERT INTO $table VALUES(";

			$fieldcounter=-1;
			$firstfield = 1;
			// get each field's data
			while (++$fieldcounter < $numfields)
			{
				if (!$firstfield)
				{
					$tabledump .= ",";
				}
				else
				{
					$firstfield = 0;
				}

				if (!isset($row[$fieldcounter]))
				{
					$tabledump .= "NULL";
				}
				else
				{
					$tabledump .= "'" . addslashes($row["$fieldcounter"]) . "'";
				}
			}
			$tabledump .= ");\n";
		}
		return $tabledump;
	}

	// #########################################################################
	// dumps a table to CSV
	function construct_csv_backup($table,$separator,$quotes,$showhead)
	{
		global $DB_site;

		$quotes = stripslashes($quotes);

		// get columns for header row
		if ($showhead)
		{
			$firstfield = 1;
			$fields = $DB_site->query("SHOW FIELDS FROM $table");
			while ($field = $DB_site->fetch_array($fields, MYSQL_BOTH))
			{
				if (!$firstfield)
				{
					$contents .= $separator;
				}
				else
				{
					$firstfield = 0;
				}
				$contents .= $quotes . $field['Field'] . $quotes;
			}
		}
		$contents .= "\n";


		// get data
		$rows = $DB_site->query("SELECT * FROM $table");
		$numfields = mysql_num_fields($rows);
		while ($row = $DB_site->fetch_array($rows, MYSQL_BOTH))
		{
			$fieldcounter = -1;
			$firstfield = 1;
			while (++$fieldcounter < $numfields)
			{
				if (!$firstfield)
				{
					$contents .= $separator;
				}
				else
				{
					$firstfield = 0;
				}

				if (!isset($row["$fieldcounter"]))
				{
					$contents .= "NULL";
				}
				else
				{
					$contents .= $quotes . addslashes($row[$fieldcounter]).$quotes;
				}
			}
			$contents .= "\n";
		}
		return $contents;
	}


	if (empty($do))
	{
		$do = 'choose';
	}

	// dump CSV table
	if ($do == 'csvtable')
	{
		header("Content-disposition: $table.csv");
		header("Content-type: text/plain");
		echo construct_csv_backup($table,$separator,$quotes,$showhead);
		exit;
	}

	// dump SQL table / database
	if ($do == 'sqltable')
	{
		header("Content-type: text/plain");
		if (!empty($table) and $table != 'all tables')
		{
			header("Content-disposition: $table.sql");
			echo fetch_table_dump_sql($table);
		}
		else

		{
			header("Content-disposition: vbulletin.sql");
			$result = $DB_site->query("SHOW tables");
			while ($currow = $DB_site->fetch_array($result, MYSQL_NUM))
			{
				echo fetch_table_dump_sql($currow[0]) . "\n\n\n";
			}
		}

		echo "\r\n\r\n\r\n### {$upgradecore_phrases['vb_db_dump_completed']} ###";

		exit;
	}

	if ($do == 'choose')
	{
		print_upgrade_header();
		echo '</div>';

		print_form_header('','');
		print_table_header($upgradecode_phrases['vb_database_backup_system']);
		print_description_row($upgradecore_phrases['dump_database_desc']);
		print_table_footer();

		$sqltable = array('all tables' => $upgradecore_phrases['dump_all_tables']);
		$tables = $DB_site->query("SHOW TABLES");
		while ($table = $DB_site->fetch_array($tables, MYSQL_NUM))
		{
			$sqltable["$table[0]"] = $table[0];
		}

		print_form_header('upgrade1', 'sqltable');
		print_table_header($upgradecore_phrases['dump_data_to_sql']);
		construct_hidden_code('step', 'backup');
		print_label_row($upgradecore_phrases['choose_table_to_dump'], '<select name="table" class="bginput">' . construct_select_options($sqltable) . '</select>');
		print_submit_row($upgradecore_phrases['dump_tables'], 0);

		unset($sqltable['all tables']);

		print_form_header('upgrade1', 'csvtable');
		print_table_header($upgradecore_phrases['dump_data_to_csv']);
		construct_hidden_code('step', 'backup');
		print_label_row($upgradecore_phrases['backup_individual_table'], '<select name="table" class="bginput">' . construct_select_options($sqltable) . '</select>');
		print_input_row($upgradecore_phrases['field_seperator'], 'separator', ',', 0, 15);
		print_input_row($upgradecore_phrases['quote_character'], 'quotes', "'", 0, 15);
		print_yes_no_row($upgradecore_phrases['show_column_names'], 'showhead', 1);
		print_submit_row($upgradecore_phrases['dump_table'], 0);

		define('NO_LOG', true);
		$step = 0;
		print_next_step();

	}
}




// ***************************************************************************************************************************




// #########################################################################
// ######################### USER LOGIN FUNCTIONS ##########################
// #########################################################################

// #########################################################################
// vB2 / vB3 Upgrade Login Form
function print_upgrade_login_form($vbversion = 3)
{
	if ($vbversion == 3)
	{
		print_cp_login();
	}

	global $vboptions, $stylevar, $bbtitle, $templateversion, $step, $bbuserinfo, $upgradecore_phrases;
	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html><head>
	<title><?php echo $upgradecore_phrases['vb3_upgrade_system']; ?></title>
	<link rel="stylesheet" href="<?php echo iif($vbversion == 3, "../cpstyles/$vboptions[cpstylefolder]/controlpanel.css", '../cpstyles/vBulletin_3_Default/controlpanel.css'); ?>" />
	</head>
	<body onload="document.forms.loginform.vb_login_username.focus()">
	<p>&nbsp;</p><p>&nbsp;</p>
	<?php if ($vbversion == 2) { ?>
	<form action="upgrade1.php" method="post" name="loginform" />
	<input type="hidden" name="redirect" value="upgrade1.php" />
	<?php } else if ($vbversion == 3) { ?>
	<script type="text/javascript" src="../clientscript/vbulletin_md5.js"></script>
	<form action="../login.php" method="post" name="loginform" onsubmit="md5hash(vb_login_password,vb_login_md5password,vb_login_md5password_utf);" />
	<input type="hidden" name="s" value="<?php echo $session['dbsessionhash']; ?>" />
	<input type="hidden" name="url" value="<?php echo htmlspecialchars_uni(SCRIPTPATH); ?>" />
	<input type="hidden" name="do" value="login" />
	<input type="hidden" name="cplogin" value="1" />
	<input type="hidden" name="forceredirect" value="1" />
	<input type="hidden" name="vb_login_md5password" value="" />
	<input type="hidden" name="vb_login_md5password_utf" value="" />
	<?php } ?>
	<input type="hidden" name="step" value="<?php echo intval($step); ?>" />
	<table cellpadding="0" cellspacing="0" border="0" width="450" align="center" class="tborder"><tr><td>
	<table cellpadding="4" cellspacing="0" border="0" width="100%">
	<tr><td class="tcat" align="center" colspan="2"><b><?php echo $upgradecore_phrases['please_login']; ?></b></td></tr>
	</table>
	<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody"><tr valign="bottom">
		<td><img src="../cpstyles/<?php echo iif(THIS_SCRIPT == 'upgrade1.php', 'vBulletin_3_Default', "$vboptions[cpstylefolder]"); ?>/cp_logo.gif" width="160" height="66" alt="cplogo" title="vBulletin &copy;2000 - <?php echo date('Y'); ?> Jelsoft Enterprises Ltd." border="0" /></td>
		<td><b><a href="../"><?php echo $bbtitle.$vboptions['bbtitle']; ?></a></b><br /><?php echo $upgradecore_phrases['vb3_upgrade_system']; ?><br />&nbsp;</td>
	</tr></table>
	<table cellpadding="4" cellspacing="0" border="0" class="tfoot" width="100%"><tr>
		<td align="<?php echo $stylevar['right']; ?>"><?php echo $upgradecore_phrases['username']; ?></td>
		<td><input type="text" style="padding-left:5px; font-weight:bold; width:250px" name="vb_login_username" size="40" accesskey="u" /></td>
	</tr><tr>
		<td align="<?php echo $stylevar['right']; ?>"><?php echo $upgradecore_phrases['password']; ?></td>
		<td><input type="password" style="padding-left:5px; font-weight:bold; width:250px" name="vb_login_password" size="40" accesskey="p" /></td>
	</tr>
	<tr><td colspan="2" align="center"><input type="submit" class="button" value="  <?php echo $upgradecore_phrases['login']; ?>  " /></td></tr>
	</table>
	</td></tr></table>
	</form>
	</body></html>
	<?php
	exit;
}



// #########################################################################
// ############# GENERIC UPGRADE / INSTALL FUNCTIONS PROTOTYPES ############
// #########################################################################



// #########################################################################
// checks the environment for vB3 conditions
// call this BEFORE calling init.php or any other files
function verify_vb3_enviroment()
{
	global $upgradecore_phrases;

	// php version check
	if (PHP_VERSION < '4.0.6')
	{
		echo "<p>{$upgradecore_phrases['php_version_too_old']}</p>";
		exit;
	}

	// config file check
	if (!file_exists('./../includes/config.php'))
	{
		echo "<p>{$upgradecore_phrases['ensure_config_exists']}.</p>";
		exit;
	}
}

// #########################################################################
// starts gzip encoding and echoes out the <html> page header
function print_upgrade_header($steptitle = '')
{
	global $vboptions, $nozip, $session, $steptitles, $step, $numsteps, $stylevar, $upgradecore_phrases;

	if (defined('DONE_HEADER'))
	{
		return;
	}

	if ($vboptions['gzipoutput'] and !headers_sent() and function_exists('ob_start') and function_exists('crc32') and function_exists('gzcompress') and !$nozip)
	{
		ob_start();
	}

	$numsteps = sizeof($steptitles);
	if ($steptitle)
	{
		$stepstring = sprintf($upgradecore_phrases['step_x_of_y'], $step, $numsteps);
	}

	// Get versions of .xml files for header diagnostics
	if ($fp = @fopen('./install/vbulletin-style.xml', 'rb'))
	{
		$data = fread($fp, 256);

		if (preg_match('#vbversion="(.*?)"#', $data, $matches))
		{
			$style_xml = $matches[1];
		}
		else
		{
			$style_xml = "<strong>{$upgradecore_phrases['unknown']}</strong>";
		}
		fclose($fp);
	}
	else
	{
		$style_xml = "<strong>{$upgradecore_phrases['file_not_found']}</strong>";
	}

	if ($fp = @fopen('./install/vbulletin-language.xml', 'rb'))
	{
		$data = fread($fp, 256);

		if (preg_match('#vbversion="(.*?)"#', $data, $matches))
		{
			$language_xml = $matches[1];
		}
		else
		{
			$language_xml = "<strong>{$upgradecore_phrases['unknown']}</strong>";
		}
		fclose($fp);
	}
	else
	{
		$language_xml = "<strong>{$upgradecore_phrases['file_not_found']}</strong>";
	}

	if ($fp = @fopen('./install/vbulletin-settings.xml', 'rb'))
	{
		$data = fread($fp, 300);

		if (preg_match('#<defaultvalue>(.*?)</defaultvalue>#', $data, $matches))
		{
			$settings_xml = $matches[1];
		}
		else
		{
			$settings_xml = "<strong>{$upgradecore_phrases['unknown']}</strong>";
		}
		fclose($fp);
	}
	else
	{
		$settings_xml = "<strong>{$upgradecore_phrases['file_not_found']}</strong>";
	}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<meta content="text/html; charset=windows-1252" http-equiv="Content-Type" />
	<title><?php echo $upgradecore_phrases['vb3_upgrade_system'] . " " . $steptitle; ?></title>
	<link rel="stylesheet" href="<?php echo iif(THIS_SCRIPT == 'upgrade1.php', '../cpstyles/vBulletin_3_Default/controlpanel.css', "../cpstyles/$vboptions[cpstylefolder]/controlpanel.css"); ?>" />
	<style type="text/css">
	#all {
		margin: 10px;
	}
	#all p, #all td, #all li, #all div {
		font-size: 11px;
		font-family: verdana, arial, helvetica, sans-serif;
	}
	</style>
</head>
<body style="margin:0px">
<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody" style="border:outset 2px">
<tr>
	<td width="160"><img src="../cpstyles/<?php echo iif(THIS_SCRIPT == 'upgrade1.php', 'vBulletin_3_Default', "$vboptions[cpstylefolder]"); ?>/cp_logo.gif" alt="" title="vBulletin 3 &copy;2000 - <?php echo date('Y'); ?>, Jelsoft Enterprises Ltd." /></td>
	<td style="padding-left:50px">
		<a href="upgrade.php"><b><?php echo $upgradecore_phrases['vb3_upgrade_system']; ?></b><br />
		<?php echo $upgradecore_phrases['may_take_some_time']; ?></a><br />
		<br />
		<b style="font-size:10pt;"><?php echo $steptitle; ?></b> <?php echo $stepstring; ?>
	</td>
	<td nowrap="nowrap" align="<?php echo $stylevar['right']; ?>">
		<strong><?php echo $upgradecore_phrases['xml_file_versions']; ?></strong><br /><br />
		vbulletin-style.xml<br />
		vbulletin-settings.xml<br />
		vbulletin-language.xml
	</td>
	<td nowrap="nowrap"><br /><br />
		<?php echo $style_xml; ?><br />
		<?php echo $settings_xml; ?><br />
		<?php echo $language_xml; ?>
	</td>
</tr>
</table>
<div id="all">
<?php
	if ($steptitle)
	{
		echo "<p style=\"font-size:10pt;\"><b><u>$steptitle</u></b></p>\n";
	}

	// spit all this stuff out
	flush();
	define('DONE_HEADER', true);
}

// #########################################################################
// ends gzip encoding & finishes the page off
function print_upgrade_footer()
{
	unset($GLOBALS['DEVDEBUG']);
	print_cp_footer();
}

// #########################################################################
// logs the current location of the user
function log_upgrade_step()
{
	global $DB_site, $step, $startat, $steptitles, $perpage, $upgradecore_phrases;

	if (defined('SCRIPTCOMPLETE'))
	{
		echo "<ul><li>" . $upgradecore_phrases['update_v_number'];
		$DB_site->query("UPDATE " . TABLE_PREFIX . "setting SET value = '" . VERSION . "' WHERE varname = 'templateversion'");
		build_options();
		echo "<b>{$upgradecore_phrases['done']}</b></li></ul>";
	}

	if (is_numeric($step) and !defined('NO_LOG'))
	{
		// use time() not TIMENOW to actually time the script's execution
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "upgradelog(script, steptitle, step, startat, perpage, dateline)
			VALUES ('" . THIS_SCRIPT . "', '" . addslashes($steptitles["$step"]) . "', " . iif(defined('SCRIPTCOMPLETE'), 0, $step) . ", $startat, $perpage, " . time() . ")
		");
	}
}

// #########################################################################
// gets the appropriate step title from the $steptitles array
function fetch_step_title($step)
{
	global $steptitles, $upgradecore_phrases;
	if (isset($steptitles["$step"]))
	{
		return sprintf($upgradecore_phrases['step_title'], $step, $steptitles["$step"]);
	}
}

// #########################################################################
// redirects browser to next page in a multi-cycle step
function print_next_page($delay = 1)
{
	global $step, $perpage, $startat, $session, $upgradecore_phrases;

	log_upgrade_step();

	define('NONEXTSTEP', true);

	$startat = $startat + $perpage;

	print_cp_redirect(THIS_SCRIPT . "?step=$step&startat=$startat#end", $delay);

	?>
	</div>
	<form action="<?php echo THIS_SCRIPT; ?>" method="get">
	<input type="hidden" name="step" value="<?php echo $step; ?>" />
	<input type="hidden" name="startat" value="<?php echo $startat; ?>" />
	<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody" style="padding:4px; border:outset 2px;">
	<tr align="center">
		<td><b><?php echo $upgradecore_phrases['batch_complete']; ?></b><br />vBulletin &copy;2000 - <?php echo date('Y'); ?>, Jelsoft Enterprises Ltd.</td>
		<td><input type="submit" class="button" value="<?php echo $upgradecore_phrases['next_batch']; ?>" /></td>
	</tr>
	</table>
	</form>
	<?php

}

// #########################################################################
// displays a form at the bottom of the page to link to next step
function print_next_step()
{
	global $step, $numsteps, $perpage, $session, $upgradecore_phrases, $vbphrase;

	// do nothing if print_next_page() or nextStep has already been called
	if (defined('NONEXTSTEP'))
	{
		return;
	}

	define('NONEXTSTEP', true);

	// reset $perpage to tell the upgrade log that any multi-page steps are complete
	$perpage = 0;

	log_upgrade_step();

	$nextstep = $step + 1;
	if (defined('SCRIPTCOMPLETE'))
	{
		$formaction = 'upgrade.php';
		$buttonvalue = ' ' . $vbphrase['proceed'] . ' ';
		$buttontitle = '';
	}
	else if ($step >= $numsteps)
	{
		$formaction = 'upgrade.php';
		$buttonvalue = ' ' . $vbphrase['proceed'] . ' ';
		$buttontitle = '';
	}
	else
	{
		$formaction = THIS_SCRIPT;
		$buttonvalue = sprintf($upgradecore_phrases['next_step'], $nextstep, $numsteps);
		$buttontitle = fetch_step_title($nextstep);

		// automatic advance - enable if you want to get through upgrades quickly without reading the text
		if (defined('UPGRADE_AUTOPROCEED') and $GLOBALS['debug'] and $step != 'welcome')
		{
			print_cp_redirect(THIS_SCRIPT . "?step=$nextstep", 0.5);
		}
	}

	?>
	</div>
	<form action="<?php echo $formaction; ?>" method="get" name="nextStep">
	<?php
	if (!defined('SCRIPTCOMPLETE'))
	{
		echo '<input type="hidden" name="step" value="' . $nextstep . '" />';
	}
	?>
	<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody" style="padding:4px; border:outset 2px;">
	<tr align="center">
		<td><b><?php echo $upgradecore_phrases['click_button_to_proceed']; ?></b><br />vBulletin &copy;2000 - <?php echo date('Y'); ?>, Jelsoft Enterprises Ltd.</td>
		<td><input type="submit" class="button" value="<?php echo $buttonvalue; ?>" title="<?php echo $buttontitle; ?>" /></td>
	</tr>
	</table>
	</form>
	<?php

}

// #########################################################################
// returns "page (pagenumber) of (totalpages)"
function construct_upgrade_page_hint($numresults, $startat, $perpage)
{
	global $upgradecore_phrases;
	$numpages = ceil($numresults / $perpage) + 1;
	$curpage = $startat / $perpage + 1;
	if ($curpage > $numpages)
	{
		$numpages = $curpage;
	}
	return sprintf($upgradecore_phrases['page_x_of_y'], $curpage, $numpages);
}

// #########################################################################
// runs through the $queries array and does the queries
function exec_queries($useLItag = false, $getids = false)
{
	global $DB_site, $query, $explain, $onvservers, $inserts;

	$inserts = array();

	if (is_array($query))
	{
		echo '<ul>';
		foreach ($query AS $key => $val)
		{
			echo "<li>$explain[$key]</li>\n";
			echo "<!-- ".htmlspecialchars_uni($val)." -->\n\n";
			flush();
			if ($onvservers == 1 and substr($val, 0, 5) == 'ALTER')
			{
				$DB_site->reporterror=0;
			}
			$DB_site->query($val);
			if ($getids)
			{
				$inserts[] = $DB_site->insert_id();
			}
			if ($onvservers == 1 and substr($val, 0, 5) == 'ALTER')
			{
				$DB_site->link_id=0;
				@mysql_close();

				sleep(1);
				$DB_site->connect();

				$DB_site->reporterror = 1;
			}
			//echo $DB_site->affected_rows();
		}
		echo '</ul>';
	}

	// the following only unsets the local copy! See unset()'s reference
	//unset($query);
	//unset($explain);
	unset($GLOBALS['query'], $GLOBALS['explain']);
}

// #########################################################################
// echoes out the string and flushes the output
function echo_flush($string)
{
	echo $string;
	flush();
}

// #############################################################################
// find illegal users
function fetch_illegal_usernames($download = false)
{
	global $DB_site, $upgradecore_phrases;

	$users = $DB_site->query("
		SELECT userid, username FROM user
		WHERE username LIKE('%;%')
	");
	if ($DB_site->num_rows($users))
	{
		$illegals = array();
		while ($user = $DB_site->fetch_array($users))
		{
			$user['uusername'] = unhtmlspecialchars($user['username']);
			if (strpos($user['uusername'], ';') !== false)
			{
				$illegals["$user[userid]"] = $user['uusername'];
			}
		}
		if (empty($illegals))
		{
			return false;
		}
		else if ($download)
		{
			$txt = "{$upgradecore_phrases['semicolons_file_intro']}\r\n";
			foreach($illegals as $userid => $username)
			{
				$txt .= "--------------------------------------------------------------------------------\r\n";
				$txt .= $username;
				$padlength = 70 - strlen($username) - strlen("$userid");
				for($i = 0; $i < $padlength; $i++)
				{
					$txt .= ' ';
				}
				$txt .= "(userid: $userid)\r\n";
			}
			$txt .= '--------------------------------------------------------------------------------';

			require_once('./includes/functions_file.php');
			file_download($txt, $upgradecore_phrases['illegal_user_names'], 'text/plain');
		}
		else
		{
			return $illegals;
		}
	}
	else
	{
		return false;
	}
}



// #########################################################################
// ################### FORUM UPDATE / IMPORT FUNCTIONS #####################
// #########################################################################



// ###################### Start makechildlist ########################
// returns the parentlist of a particular forum
function construct_child_list($forumid)
{
	global $DB_site;

	if ($forumid == -1)
	{
		return '-1';
	}

	$childlist = $forumid;

	$children = $DB_site->query("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum
		WHERE parentlist LIKE '%,$forumid,%'
	");
	while ($child = $DB_site->fetch_array($children))
	{
		$childlist .= ',' . $child['forumid'];
	}

	$childlist .= ',-1';

	return $childlist;

}

// ###################### Start updatechildlists #######################
// updates the child list for all forums
function build_forum_child_lists()
{
	global $DB_site;
	$forums = $DB_site->query("SELECT forumid FROM " . TABLE_PREFIX . "forum");
	while ($forum = $DB_site->fetch_array($forums))
	{
		$childlist = construct_child_list($forum['forumid']);
		$DB_site->query("UPDATE " . TABLE_PREFIX . "forum SET childlist = '$childlist' WHERE forumid = $forum[forumid]");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15324 $
|| ####################################################################
\*======================================================================*/
?>