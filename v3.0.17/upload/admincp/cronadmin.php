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

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 11014 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('logging', 'cron');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadmincron'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['cronid'] != 0, 'cron id = ' . $_REQUEST['cronid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['scheduled_task_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start run cron #######################
if ($_REQUEST['do'] == 'runcron')
{
	globalize($_REQUEST, array('cronid' => INT));

	if ($nextitem = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "cron WHERE cronid = $cronid"))
	{
		echo "<p><b>$nextitem[title]</b></p>";
		require_once('./includes/functions_cron.php');
		require_once($nextitem['filename']);
		echo "<p>$vbphrase[done]</p>";
	}
	else
	{
		print_stop_message('invalid_action_specified');
	}
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	print_form_header('cronadmin', 'update');
	if (isset($_REQUEST['cronid']))
	{
		$cron = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "cron WHERE cronid = " . intval($_REQUEST['cronid']));
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['scheduled_task'], $cron['title'], $cron['cronid']));
		construct_hidden_code('cronid' , $cron['cronid']);
	}
	else
	{
		$cron = array(
			'cronid' => '',
			'weekday' => -1,
			'day' => -1,
			'hour' => -1,
			'minute' => -1,
			'filename' => './includes/cron/.php',
			'loglevel' => 0
		);
		print_table_header($vbphrase['add_new_scheduled_task']);
	}

	$weekdays = array(-1 => '*', 0 => $vbphrase['sunday'], $vbphrase['monday'], $vbphrase['tuesday'], $vbphrase['wednesday'], $vbphrase['thursday'], $vbphrase['friday'], $vbphrase['saturday']);
	$hours = array(-1 => '*', 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23);
	$days = array(-1 => '*', 1 => 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31);
	$minutes = array(-1 => '*');
	for ($x = 0; $x < 60; $x++)
	{
		$minutes[] = $x;
	}

	print_input_row($vbphrase['title'], 'title', $cron['title']);
	print_select_row($vbphrase['day_of_week'], 'weekday', $weekdays, $cron['weekday']);
	print_select_row($vbphrase['day_of_month'], 'day', $days, $cron['day']);
	print_select_row($vbphrase['hour'], 'hour', $hours, $cron['hour']);
	print_select_row($vbphrase['minute'], 'minute', $minutes, $cron['minute']);
	print_yes_no_row($vbphrase['log_entries'], 'loglevel', $cron['loglevel']);
	print_input_row($vbphrase['filename'], 'filename' , $cron['filename']);
	print_submit_row($vbphrase['save']);

}

// ###################### Start do update #######################
if ($_POST['do'] == 'update')
{
	globalize($_POST, array(
		'filename' => STR,
		'title' => STR,
		'weekday' => STR,
		'day' => STR,
		'hour' => STR,
		'minute' => STR,
		'cronid' => INT,
		'filename' => STR,
		'loglevel' => INT
	));

	if ($filename == '' OR $filename == './includes/cron/.php')
	{
		print_stop_message('invalid_filename_specified');
	}
	if ($title == '')
	{
		print_stop_message('invalid_title_specified');
	}

	$weekday = str_replace('*', '-1', $weekday);
	$day = str_replace('*', '-1', $day);
	$hour = str_replace('*', '-1', $hour);
	$minute = str_replace('*', '-1', $minute);

	if (empty($cronid))
	{
		// add new
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "cron
			(weekday, day, hour, minute, filename, loglevel, title)
			VALUES
			(" . intval($weekday) . " , " . intval($day) . " , " . intval($hour) . " , " . intval($minute) . " , '" . addslashes($filename) . "', " . intval($loglevel) . ", '" . addslashes($title) . "' )
		");

		$cronid = $DB_site->insert_id();

	}
	else
	{
		// update
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "cron
			SET title = '" . addslashes($title) . "',
			loglevel = " . intval($loglevel) . ",
			weekday = " . intval($weekday) . ",
			day = " . intval($day) . ",
			hour = " . intval($hour) . ",
			minute = " . intval($minute) . ",
			filename = '" . addslashes($filename) . "'
			WHERE cronid = " . intval($cronid)
		);
	}

	require_once('./includes/functions_cron.php');
	build_cron_item($cronid);
	build_cron_next_run();

	define('CP_REDIRECT', 'cronadmin.php?do=modify');
	print_stop_message('saved_scheduled_task_x_successfully', $title);

}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'remove')
{

	print_form_header('cronadmin', 'kill');
	construct_hidden_code('cronid', $_REQUEST['cronid']);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_scheduled_task']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

// ###################### Start Kill #######################
if ($_POST['do'] == 'kill')
{

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "cron WHERE cronid = " . intval($_POST['cronid']));

	define('CP_REDIRECT', 'cronadmin.php?do=modify');
	print_stop_message('deleted_scheduled_task_successfully');
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	function fetch_cron_timerule($cron)
	{
		global $vbphrase;

		$t = array(
			'hour' => $cron['hour'],
			'minute' => $cron['minute'],
			'day' => $cron['day'],
			'month' => -1,
			'weekday' => $cron['weekday']
		);

		// set '-1' fields as
		foreach ($t AS $field => $value)
		{
			$t["$field"] = iif($value == -1, '*', $value);
		}

		// pad the minute value if necessary
		$t['minute'] = iif($t['minute'] == '*', '*', str_pad($t['minute'], 2, 0, STR_PAD_LEFT));

		// set weekday to override day of month if necessary
		$days = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
		if ($t['weekday'] != '*')
		{
			$day = $days[intval($t['weekday'])];
			$t['weekday'] = $vbphrase[$day . "_abbr"];
			$t['day'] = '*';
		}

		return $t;
	}

	$crons = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "cron ORDER BY nextrun");

	?>
	<script type="text/javascript">
	<!--
	function js_cron_jump(cronid)
	{
		task = eval("document.cpform.c" + cronid + ".options[document.cpform.c" + cronid + ".selectedIndex].value");
		switch (task)
		{
			case 'edit': window.location = "cronadmin.php?s=<?php echo $session['sessionhash']; ?>&do=edit&cronid=" + cronid; break;
			case 'kill': window.location = "cronadmin.php?s=<?php echo $session['sessionhash']; ?>&do=remove&cronid=" + cronid; break;
			default: return false; break;
		}
	}
	function js_run_cron(cronid)
	{
		window.location = "<?php echo "cronadmin.php?$session[sessionurl]do=runcron&cronid="; ?>" + cronid;
	}
	//-->
	</script>
	<?php

	$options = array('edit' => $vbphrase['edit'], 'kill' => $vbphrase['delete']);

	print_form_header('cronadmin', 'edit');
	print_table_header($vbphrase['scheduled_task_manager'], 8);
	print_cells_row(array(
		'm',
		'h',
		'D',
		'M',
		'DoW',
		$vbphrase['title'],
		$vbphrase['next_time'],
		$vbphrase['controls']
	), 1, '', 1);

	while ($cron = $DB_site->fetch_array($crons))
	{
		$timerule = fetch_cron_timerule($cron);
		$cell = array(
			$timerule['minute'],
			$timerule['hour'],
			$timerule['day'],
			$timerule['month'],
			$timerule['weekday'],
			'<b>' . $cron['title'] . '</b>',
			vbdate($vboptions['dateformat'] . ' ' . $vboptions['timeformat'], $cron['nextrun']),
			"\n\t<select name=\"c$cron[cronid]\" onchange=\"js_cron_jump($cron[cronid]);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>" .
			"\n\t<input type=\"button\" class=\"button\" value=\"$vbphrase[go]\" onclick=\"js_cron_jump($cron[cronid]);\" />\n\t" .
			"\n\t<input type=\"button\" class=\"button\" value=\"$vbphrase[run_now]\" onclick=\"js_run_cron($cron[cronid]);\" />"
		);
		print_cells_row($cell, 0, '', -5);
	}

	print_description_row("<div class=\"smallfont\" align=\"center\">$vbphrase[all_times_are_gmt_x_time_now_is_y]</div>", 0, 8, 'thead');
	print_submit_row($vbphrase['add_new_scheduled_task'], 0, 8);

}
print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11014 $
|| ####################################################################
\*======================================================================*/
?>