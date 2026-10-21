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
ignore_user_abort(true);
// attempt to extend php timeout limit
if (function_exists('set_time_limit') AND get_cfg_var('safe_mode') == 0)
{
	@set_time_limit(0);
}

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_IMPORT_DOTS', true);
define('VB_AREA', 'Upgrade');
define('NOZIP', 1);
define('TIMENOW', time());
chdir('./../');

// ########################## REQUIRE BACK-END ############################
require_once('./install/init.php');
require_once(DIR . '/includes/functions.php');
require_once(DIR . '/includes/adminfunctions.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (!defined('VERSION'))
{
	define('VERSION', defined('FILE_VERSION') ? FILE_VERSION : '');
}

require_once(DIR . '/install/upgrade_language_en.php');

// check for valid php version
verify_vb3_enviroment();

exec_nocache_headers();

$vbulletin->input->clean_array_gpc('r', array(
	'step' => TYPE_STR,
	'startat' => TYPE_UINT,
	'perpage' => TYPE_UINT,
));

$vbulletin->GPC['step'] = empty($vbulletin->GPC['step']) ? 'welcome' : intval($vbulletin->GPC['step']);
$query = array();
$explain = array();

$steptitles = $upgrade_phrases[THIS_SCRIPT]['steps'];
// authenticate with customer number
require_once(DIR . '/install/authenticate.php');

// assuming we've got through the authentication process, show the upgradeHeader.
if (empty($_REQUEST['do']))
{
	print_upgrade_header(fetch_step_title($vbulletin->GPC['step']));

	if ($vbulletin->GPC['step'] == 'welcome')
	{
		if (empty($vbulletin->config['Database']['force_sql_mode']))
		{
			// check to see if MySQL is running strict mode and recommend disabling it
			$db->hide_errors();
			$strict_mode_check = $db->query_first("SHOW VARIABLES LIKE 'sql\_mode'");
			if (strpos(strtolower($strict_mode_check['Value']), 'strict_') !== false)
			{
				echo "<p><strong>{$upgradecore_phrases['mysql_strict_mode']}</strong></p>";
				print_upgrade_footer();
				exit;
			}
			$db->show_errors();
		}
	}
}

// ***************************************************************************************************************************

// #############################################################################
// backup system
if ($vbulletin->GPC['step'] == 'backup')
{
	if ($vbulletin->options['templateversion'] != PREV_VERSION)
	{
		print_form_header('','');
		print_table_header($upgradecode_phrases['vb_database_backup_system']);
		print_description_row($upgradecore_phrases['backup_after_upgrade']);
		print_table_footer();
		define('NO_LOG', true);
		exit;
	}
	// #########################################################################
	// dumps an sql table
	function fetch_table_dump_sql($table)
	{
		global $vbulletin;

		$tabledump = "DROP TABLE IF EXISTS $table;\n";
		$tabledump .= "CREATE TABLE $table (\n";

		$firstfield = 1;

		// get columns and spec
		$fields = $vbulletin->db->query_write("SHOW FIELDS FROM $table");
		while ($field = $vbulletin->db->fetch_array($fields, DBARRAY_BOTH))
		{
			if (!$firstfield)
			{
				$tabledump .= ",\n";
			}
			else
			{
				$firstfield = 0;
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
		$keys = $vbulletin->db->query_write("SHOW KEYS FROM $table");
		while ($key = $vbulletin->db->fetch_array($keys, DBARRAY_BOTH))
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
		$rows = $vbulletin->db->query_write("SELECT * FROM $table");
		$numfields = $vbulletin->db->num_fields($rows);
		while ($row = $vbulletin->db->fetch_array($rows, DBARRAY_BOTH))
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
					$tabledump .= "'" . $vbulletin->db->escape_string($row["$fieldcounter"]) . "'";
				}
			}
			$tabledump .= ");\n";
		}
		return $tabledump;
	}

	// #########################################################################
	// dumps a table to CSV
	function construct_csv_backup($table, $separator, $quotes, $showhead)
	{
		global $vbulletin;

		$quotes = stripslashes($quotes);

		// get columns for header row
		if ($showhead)
		{
			$firstfield = 1;
			$fields = $vbulletin->db->query_write("SHOW FIELDS FROM $table");
			while ($field = $vbulletin->db->fetch_array($fields, DBARRAY_BOTH))
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
		$rows = $vbulletin->db->query_write("SELECT * FROM $table");
		$numfields = mysql_num_fields($rows);
		while ($row = $vbulletin->db->fetch_array($rows, DBARRAY_BOTH))
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
					$contents .= $quotes . $vbulletin->db->escape_string($row[$fieldcounter]).$quotes;
				}
			}
			$contents .= "\n";
		}
		return $contents;
	}


	if (empty($_REQUEST['do']))
	{
		$_REQUEST['do'] = 'choose';
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'table' => TYPE_STR,
		'separator' => TYPE_STR,
		'quotes' => TYPE_STR,
		'showhead' => TYPE_INT
	));

	// dump CSV table
	if ($_REQUEST['do'] == 'csvtable')
	{
		header("Content-disposition: attachment; filename=\"" . preg_replace('#[\r\n]#', '', $vbulletin->GPC['table']) . ".csv\"");
		header("Content-type: text/plain");
		echo construct_csv_backup($vbulletin->GPC['table'], $vbulletin->GPC['separator'], $vbulletin->GPC['quotes'], $vbulletin->GPC['showhead']);
		exit;
	}

	// dump SQL table / database
	if ($_REQUEST['do'] == 'sqltable')
	{
		header("Content-type: text/plain");
		if (!empty($vbulletin->GPC['table']) and $vbulletin->GPC['table'] != 'all tables')
		{
			header("Content-disposition: attachment; filename=\"" . preg_replace('#[\r\n]#', '', $vbulletin->GPC['table']) . ".sql\"");
			echo fetch_table_dump_sql($vbulletin->GPC['table']);
		}
		else
		{
			header("Content-disposition: attachment; filename=\"vbulletin.sql\"");
			$result = $db->query_write("SHOW tables");
			while ($currow = $db->fetch_row($result))
			{
				echo fetch_table_dump_sql($currow[0]) . "\n\n\n";
			}
		}

		echo "\r\n\r\n\r\n### {$upgradecore_phrases['vb_db_dump_completed']} ###";

		exit;
	}

	if ($_REQUEST['do'] == 'choose')
	{
		print_upgrade_header();
		echo '</div>';

		print_form_header('','');
		print_table_header($upgradecode_phrases['vb_database_backup_system']);
		print_description_row($upgradecore_phrases['dump_database_desc']);
		print_table_footer();

		$sqltable = array('all tables' => $upgradecore_phrases['dump_all_tables']);
		$tables = $db->query_write("SHOW TABLES");
		while ($table = $db->fetch_array($tables, DBARRAY_NUM))
		{
			$sqltable["$table[0]"] = $table[0];
		}

		print_form_header('upgrade_300b3', 'sqltable');
		print_table_header($upgradecore_phrases['dump_data_to_sql']);
		construct_hidden_code('step', 'backup');
		print_label_row($upgradecore_phrases['choose_table_to_dump'], '<select name="table" class="bginput">' . construct_select_options($sqltable) . '</select>');
		print_submit_row($upgradecore_phrases['dump_tables'], 0);

		unset($sqltable['all tables']);

		print_form_header('upgrade_300b3', 'csvtable');
		print_table_header($upgradecore_phrases['dump_data_to_csv']);
		construct_hidden_code('step', 'backup');
		print_label_row($upgradecore_phrases['backup_individual_table'], '<select name="table" class="bginput">' . construct_select_options($sqltable) . '</select>');
		print_input_row($upgradecore_phrases['field_seperator'], 'separator', ',', 0, 15);
		print_input_row($upgradecore_phrases['quote_character'], 'quotes', "'", 0, 15);
		print_yes_no_row($upgradecore_phrases['show_column_names'], 'showhead', 1);
		print_submit_row($upgradecore_phrases['dump_table'], 0);

		define('NO_LOG', true);
		$vbulletin->GPC['step'] = 0;
		print_next_step();

	}
}




// ***************************************************************************************************************************

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
	if (PHP_VERSION < '4.1.0')
	{
		echo "<p>{$upgradecore_phrases['php_version_too_old']}</p>";
		exit;
	}

	// config file check
	if (!file_exists(DIR . '/includes/config.php'))
	{
		echo "<p>{$upgradecore_phrases['ensure_config_exists']}</p>";
		exit;
	}

	if (($err = verify_optimizer_environment()) !== true)
	{
		echo "<p>{$upgradecore_phrases[$err]}</p>";
		exit;
	}
}

// #########################################################################
// starts gzip encoding and echoes out the <html> page header
function print_upgrade_header($steptitle = '')
{
	global $vbulletin, $steptitles, $numsteps, $stylevar, $upgradecore_phrases;

	if (defined('DONE_HEADER'))
	{
		return;
	}

	if ($vbulletin->options['gzipoutput'] and !headers_sent() and function_exists('ob_start') and function_exists('crc32') and function_exists('gzcompress') and !$vbulletin->nozip)
	{
		ob_start();
	}

	$numsteps = sizeof($steptitles);
	if ($steptitle)
	{
		$stepstring = sprintf($upgradecore_phrases['step_x_of_y'], $vbulletin->GPC['step'], $numsteps);
	}

	// Get versions of .xml files for header diagnostics
	if ($fp = @fopen(DIR . '/install/vbulletin-style.xml', 'rb'))
	{
		$data = @fread($fp, 256);

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

	if ($fp = @fopen(DIR . '/install/vbulletin-language.xml', 'rb'))
	{
		$data = @fread($fp, 256);

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

	if ($fp = @fopen(DIR . '/install/vbulletin-settings.xml', 'rb'))
	{
		$data = @fread($fp, 300);

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

	if ($fp = @fopen(DIR . '/install/vbulletin-adminhelp.xml', 'rb'))
	{
		$data = @fread($fp, 300);

		if (preg_match('#vbversion="(.*?)"#', $data, $matches))
		{
			$help_xml = $matches[1];
		}
		else
		{
			$help_xml = "<strong>{$upgradecore_phrases['unknown']}</strong>";
		}
		fclose($fp);
	}
	else
	{
		$help_xml = "<strong>{$upgradecore_phrases['file_not_found']}</strong>";
	}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="<?php echo $stylevar['textdirection']; ?>" lang="<?php echo $stylevar['languagecode']; ?>">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $stylevar['charset']; ?>" />
	<title><?php echo $upgradecore_phrases['vb3_upgrade_system'] . " " . $steptitle; ?></title>
	<link rel="stylesheet" href="<?php echo THIS_SCRIPT == 'upgrade_300b3.php' ? '../cpstyles/vBulletin_3_Default/controlpanel.css' : "../cpstyles/{$vbulletin->options['cpstylefolder']}/controlpanel.css"; ?>" />
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
	<td width="160"><img src="../cpstyles/<?php echo THIS_SCRIPT == 'upgrade_300b3.php' ? 'vBulletin_3_Default' : $vbulletin->options['cpstylefolder']; ?>/cp_logo.gif" alt="" title="vBulletin 3 &copy;2000 - <?php echo date('Y'); ?>, Jelsoft Enterprises Ltd." /></td>
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
		vbulletin-language.xml<br />
		vbulletin-adminhelp.xml
	</td>
	<td nowrap="nowrap"><br /><br />
		<?php echo $style_xml; ?><br />
		<?php echo $settings_xml; ?><br />
		<?php echo $language_xml; ?><br />
		<?php echo $help_xml; ?>
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
	vbflush();
	define('DONE_HEADER', true);
}

// #########################################################################
// ends gzip encoding & finishes the page off
function print_upgrade_footer()
{
	global $vbulletin;

	unset($vbulletin->debug);
	print_cp_footer();
}

// #########################################################################
// logs the current location of the user
function log_upgrade_step()
{
	global $vbulletin, $steptitles, $upgradecore_phrases;

	if (THIS_SCRIPT == 'finalupgrade.php')
	{
		return;
	}

	if (defined('SCRIPTCOMPLETE'))
	{
		echo "<ul><li>" . $upgradecore_phrases['update_v_number'];
		$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '" . VERSION . "' WHERE varname = 'templateversion'");
		build_options();
		echo "<b>{$upgradecore_phrases['done']}</b></li></ul>";
	}

	if (is_numeric($vbulletin->GPC['step']) and !defined('NO_LOG'))
	{
		// use time() not TIMENOW to actually time the script's execution
		/*insert query*/
		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "upgradelog(script, steptitle, step, startat, perpage, dateline)
			VALUES ('" . THIS_SCRIPT . "', '" . $vbulletin->db->escape_string($steptitles["{$vbulletin->GPC['step']}"]) . "', " . (defined('SCRIPTCOMPLETE') ? 0 : $vbulletin->GPC['step']) . ", {$vbulletin->GPC['startat']}, {$vbulletin->GPC['perpage']}, " . time() . ")
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
	global $vbulletin, $upgradecore_phrases;

	log_upgrade_step();

	define('NONEXTSTEP', true);

	$vbulletin->GPC['startat'] = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	print_cp_redirect(THIS_SCRIPT . "?step={$vbulletin->GPC['step']}&startat={$vbulletin->GPC['startat']}#end", $delay);

	?>
	</div>
	<form action="<?php echo THIS_SCRIPT; ?>" method="get">
	<input type="hidden" name="step" value="<?php echo $vbulletin->GPC['step']; ?>" />
	<input type="hidden" name="startat" value="<?php echo $vbulletin->GPC['startat']; ?>" />
	<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody" style="padding:4px; border:outset 2px;">
	<tr align="center">
		<td><b><?php echo $upgradecore_phrases['batch_complete']; ?></b><br />vBulletin &copy;2000 - <?php echo date('Y'); ?>, Jelsoft Enterprises Ltd.</td>
		<td><input type="submit" class="button" accesskey="s" value="<?php echo $upgradecore_phrases['next_batch']; ?>" /></td>
	</tr>
	</table>
	</form>
	<?php

}

// #########################################################################
// displays a form at the bottom of the page to link to next step
function print_next_step()
{
	global $vbulletin, $numsteps, $upgradecore_phrases, $vbphrase;

	// do nothing if print_next_page() or nextStep has already been called
	if (defined('NONEXTSTEP'))
	{
		return;
	}

	define('NONEXTSTEP', true);

	// reset $perpage to tell the upgrade log that any multi-page steps are complete
	$vbulletin->GPC['perpage'] = 0;

	log_upgrade_step();

	$nextstep = $vbulletin->GPC['step'] + 1;
	if (defined('SCRIPTCOMPLETE'))
	{
		$formaction = 'upgrade.php';
		$buttonvalue = ' ' . $vbphrase['proceed'] . ' ';
		$buttontitle = '';

		if ($vbulletin->config['Misc']['upgrade_autoproceed'] == 'full' AND $vbulletin->debug)
		{
			print_cp_redirect('upgrade.php', 0.5);
		}

	}
	else if ($vbulletin->GPC['step'] >= $numsteps)
	{
		$formaction = 'upgrade.php';
		$buttonvalue = ' ' . $vbphrase['proceed'] . ' ';
		$buttontitle = '';

		if ($vbulletin->config['Misc']['upgrade_autoproceed'] == 'full' AND $vbulletin->debug)
		{
			print_cp_redirect('upgrade.php', 0.5);
		}
	}
	else
	{
		$formaction = THIS_SCRIPT;
		$buttonvalue = sprintf($upgradecore_phrases['next_step'], $nextstep, $numsteps);
		$buttontitle = fetch_step_title($nextstep);

		// automatic advance - enable if you want to get through upgrades quickly without reading the text
		if ($vbulletin->config['Misc']['upgrade_autoproceed'] AND $vbulletin->debug AND ($vbulletin->GPC['step'] != 'welcome' OR $vbulletin->config['Misc']['upgrade_autoproceed'] == 'full'))
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
		<td><input type="submit" class="button" accesskey="s" value="<?php echo $buttonvalue; ?>" title="<?php echo $buttontitle; ?>" /></td>
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
	global $vbulletin, $query, $explain, $inserts;

	$inserts = array();

	if (is_array($query))
	{
		echo '<ul>';
		foreach ($query AS $key => $val)
		{
			if (is_array($val))
			{	// turn error reporting off
				$val = array_pop($val);
				$vbulletin->db->hide_errors();
			}
			echo "<li>$explain[$key]</li>\n";
			echo "<!-- " . htmlspecialchars_uni($val) . " -->\n\n";
			vbflush();
			$vbulletin->db->query_write($val);
			if ($getids)
			{
				$inserts[] = $vbulletin->db->insert_id();
			}
			if (!$vbulletin->db->reporterror)
			{	// Turn error reporting back on
				$vbulletin->db->errno = 0;
				$vbulletin->db->show_errors();
			}
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
	vbflush();
}

// #############################################################################
// find illegal users
function fetch_illegal_usernames($download = false)
{
	global $vbulletin, $upgradecore_phrases;

	$users = $vbulletin->db->query_read("
		SELECT userid, username FROM user
		WHERE username LIKE('%;%')
	");
	if ($vbulletin->db->num_rows($users))
	{
		$illegals = array();
		while ($user = $vbulletin->db->fetch_array($users))
		{
			$user['uusername'] = unhtmlspecialchars($user['username']);
			if (strpos($user['uusername'], ';') !== false)
			{
				$illegals["{$user['userid']}"] = $user['uusername'];
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

			require_once(DIR . '/includes/functions_file.php');
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
	global $vbulletin;

	if ($forumid == -1)
	{
		return '-1';
	}

	$childlist = $forumid;

	$children = $vbulletin->db->query_read("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum
		WHERE parentlist LIKE '%,$forumid,%'
	");
	while ($child = $vbulletin->db->fetch_array($children))
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
	global $vbulletin;

	$forums = $vbulletin->db->query_read("SELECT forumid FROM " . TABLE_PREFIX . "forum");
	while ($forum = $vbulletin->db->fetch_array($forums))
	{
		$childlist = construct_child_list($forum['forumid']);
		$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "forum SET childlist = '$childlist' WHERE forumid = $forum[forumid]");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15107 $
|| ####################################################################
\*======================================================================*/
?>