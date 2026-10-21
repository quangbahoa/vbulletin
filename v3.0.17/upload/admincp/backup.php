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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (function_exists('set_time_limit') AND ini_get('safe_mode') == '')
{
	@set_time_limit(0);
}
if (isset($_REQUEST['do']) AND ($_REQUEST['do'] == 'csvtable' OR $_REQUEST['do'] == 'sqltable'))
{
	$noheader = 1;
}
$nozip = 1;

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 13177 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('sql');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_backup.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminmaintain'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif(!empty($_REQUEST['table']), "Table = $_REQUEST[table]", ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'choose';
}

// #############################################################################

if ($_POST['do'] == 'csvtable')
{
	globalize($_POST, array(
		'table' => STR,
		'showhead' => INT,
		'separator',
		'quotes'
	));

	header('Content-disposition: filename=' . $table . '.csv');
	header('Content-type: unknown/unknown');

	echo construct_csv_backup($table, $separator, $quotes, $showhead);

	exit;

}

// #############################################################################

if ($_POST['do'] == 'sqltable')
{
	globalize($_POST, array('table'));

	header('Content-disposition: filename=vbulletin.sql');
	header('Content-type: unknown/unknown');

	$result = $DB_site->query("SHOW tables");
	foreach($table AS $key => $val)
	{
		if ($val == 1)
		{
			fetch_table_dump_sql($key);
			echo "\n\n\n";
		}
	}

	echo "\r\n\r\n\r\n### VBULLETIN DATABASE DUMP COMPLETED ###";

	exit;

}

// #############################################################################

print_cp_header($vbphrase['database_backup']);

// #############################################################################

if ($_REQUEST['do'] == 'choose')
{
	print_form_header('backup', 'sqltable');
	print_table_header($vbphrase['database_backup']);
	// mention that database backup is dodgy :)
	print_description_row($vbphrase['php_backup_warning']);
	print_table_break();

	print_table_header($vbphrase['database_table_to_include_in_backup']);
	print_label_row(
		$vbphrase['table_name'],
		'<input type="button" class="button" value=" ' . $vbphrase['all_yes'] . ' " onclick="js_check_all_option(this.form, 1)" /> <input type="button" class="button" value=" ' . $vbphrase['all_no'] . ' " onclick="js_check_all_option(this.form, 0)" />',
		'thead'
	);

	$result = $DB_site->query('SHOW tables');
	while ($currow = $DB_site->fetch_array($result, DBARRAY_NUM))
	{
		if ($currow[0] != TABLE_PREFIX . 'word' AND $currow[0] != TABLE_PREFIX . 'postindex')
		{
			print_yes_no_row($currow[0], "table[$currow[0]]", 1);
		}
	}

	print_yes_no_row(TABLE_PREFIX . 'word', "table[" . TABLE_PREFIX . "word]", 1);
	print_yes_no_row(TABLE_PREFIX . 'postindex', "table[" . TABLE_PREFIX . "postindex]", 1);

	print_submit_row($vbphrase['go']);

	print_form_header('backup', 'sqlfile');
	print_table_header($vbphrase['backup_database_to_a_file_on_the_server']);
	print_description_row($vbphrase['backup_file_warning']);
	print_input_row($vbphrase['path_and_file_to_save_backup_to'], 'filename', './forumbackup-' . vbdate(str_replace(array('\\', '/', ' '), '', $vboptions['dateformat']), TIMENOW) . '-' . substr(md5('VBS4AAFB47' . TIMENOW), 0, 5) . '.sql', 0, 60);
	print_submit_row($vbphrase['save']);

	print_form_header('backup', 'csvtable');
	print_table_header($vbphrase['csv_backup_of_single_database_table']);

	echo "<tr class='" . fetch_row_bgclass() . "'>\n<td><p>" . $vbphrase['table_name'] . "</p></td>\n<td><p>";
	echo "<select name=\"table\" size=\"1\" tabindex=\"1\" class=\"bginput\">\n";

	$result = $DB_site->query('SHOW tables');
	while ($currow = $DB_site->fetch_array($result, DBARRAY_NUM))
	{
		echo '<option value="' . $currow[0] . '">' . $currow[0] . "</option>\n";
	}

	echo "</select></p></td></tr>\n\n";

	print_input_row($vbphrase['separator_character'], 'separator', ',');
	print_input_row($vbphrase['quote_character'], 'quotes', "'");
	print_yes_no_row($vbphrase['add_column_names'], 'showhead', 1);

	print_submit_row($vbphrase['go']);

}

// ###################### Dumping to SQL File ####################
if ($_POST['do'] == 'sqlfile')
{
	globalize($_POST, array('filename' => STR));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if (file_exists($filename))
	{
		print_stop_message('file_x_already_exists', htmlspecialchars($filename));
	}

	$filehandle = fopen($filename, 'w');
	$result = $DB_site->query('SHOW tables');
	while ($currow = $DB_site->fetch_array($result, DBARRAY_NUM))
	{
		fetch_table_dump_sql($currow[0], $filehandle);
		fwrite($filehandle, "\n\n\n");
		echo '<p>' . construct_phrase($vbphrase['processing_x'], $currow[0]) . '</p>';
	}
	fwrite($filehandle, "\n\n\n### VBULLETIN DATABASE DUMP COMPLETED ###");
	fclose($filehandle);

	print_stop_message('completed_database_backup_successfully');

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13177 $
|| ####################################################################
\*======================================================================*/
?>