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
define('CVS_REVISION', '$RCSfile$ - $Revision: 12949 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('logging', 'cron');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (is_demo_mode() OR !can_administer('canadmincron'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'cronid' => TYPE_INT
));
log_admin_action(iif($vbulletin->GPC['cronid'] != 0, 'cron id = ' . $vbulletin->GPC['cronid']));

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
	$vbulletin->input->clean_array_gpc('r', array(
		'cronid' => TYPE_INT
	));

	if ($nextitem = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "cron WHERE cronid = " . $vbulletin->GPC['cronid']))
	{
		// Force custom scripts to use $vbulletin->db to follow function standards of only globaling $vbulletin
		// This will cause an error to be thrown when a script is run manually since it will silently fail when cron.php runs if $db-> is accessed
		unset($db);
				
		echo "<p><b>$nextitem[title]</b></p>";
		require_once(DIR . '/includes/functions_cron.php');
		include_once(DIR . '/' . $nextitem['filename']);
		echo "<p>$vbphrase[done]</p>";
		
		$db =& $vbulletin->db;
	}
	else
	{
		print_stop_message('invalid_action_specified');
	}
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'cronid' => TYPE_INT
	));

	print_form_header('cronadmin', 'update');
	if (!empty($vbulletin->GPC['cronid']))
	{
		$cron = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "cron WHERE cronid = " . intval($vbulletin->GPC['cronid']));
		if (is_numeric($cron['minute']))
		{
			$cron['minute'] = array(0 => $cron['minute']);
		}
		else
		{
			$cron['minute'] = unserialize($cron['minute']);
		}
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
			'minute' => array (0 => -1),
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

	$selects = '';
	for($x = 0; $x < 4; $x++)
	{
		if ($x == 1)
		{
			$minutes = array(-2 => '-') + $minutes;
			unset($minutes[-1]);
		}
		if (!isset($cron['minute'][$x]))
		{
			$cron['minute'][$x] = -2;
		}
		$selects .= "<select name=\"minute[$x]\" tabindex=\"1\" class=\"bginput\">\n";
		$selects .= construct_select_options($minutes, $cron['minute'][$x]);
		$selects .= "</select>\n";
	}
	print_label_row($vbphrase['minute'], $selects, '', 'top', 'minute');
	print_yes_no_row($vbphrase['log_entries'], 'loglevel', $cron['loglevel']);
	print_input_row($vbphrase['filename'], 'filename' , $cron['filename']);
	print_submit_row($vbphrase['save']);
}

// ###################### Start do update #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'filename' 	=> TYPE_STR,
		'title' 	=> TYPE_STR,
		'weekday' 	=> TYPE_STR,
		'day' 		=> TYPE_STR,
		'hour' 		=> TYPE_STR,
		'minute' 	=> TYPE_ARRAY,
		'cronid' 	=> TYPE_INT,
		'filename' 	=> TYPE_STR,
		'loglevel' 	=> TYPE_INT
	));

	if ($vbulletin->GPC['filename'] == '' OR $vbulletin->GPC['filename'] == './includes/cron/.php')
	{
		print_stop_message('invalid_filename_specified');
	}
	if ($vbulletin->GPC['title'] == '')
	{
		print_stop_message('invalid_title_specified');
	}

	$vbulletin->GPC['weekday'] 	= str_replace('*', '-1', $vbulletin->GPC['weekday']);
	$vbulletin->GPC['day']		= str_replace('*', '-1', $vbulletin->GPC['day']);
	$vbulletin->GPC['hour']		= str_replace('*', '-1', $vbulletin->GPC['hour']);

	// need to deal with minute properly :)
	sort($vbulletin->GPC['minute'], SORT_NUMERIC);
	$newminute = array();


	foreach ($vbulletin->GPC['minute'] AS $time)
	{
		$newminute["$time"] = true;
	}
	// removed duplicates now lets remove -2

	unset($newminute["-2"]);
	if ($newminute["-1"])
	{ // its run every minute so lets just ignore every other entry
		$newminute = array(0 => -1);
	}
	else
	{ // array keys please :)
		$newminute = array_keys($newminute);
	}

	if (empty($vbulletin->GPC['cronid']))
	{
		// add new
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "cron
			(
				weekday,
				day,
				hour,
				minute,
				filename,
				loglevel,
				title
			)
			VALUES
			(
				" . intval($vbulletin->GPC['weekday']) . " ,
				" . intval($vbulletin->GPC['day']) . " ,
				" . intval($vbulletin->GPC['hour']) . " ,
				'" . $db->escape_string(serialize($newminute)) . "' ,
				'" . $db->escape_string($vbulletin->GPC['filename']) . "',
				" . $vbulletin->GPC['loglevel'] . ",
				'" . $db->escape_string($vbulletin->GPC['title']) . "' )
		");

		$vbulletin->GPC['cronid'] = $db->insert_id();
	}
	else
	{
		// update
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "cron
			SET title = '" . $db->escape_string($vbulletin->GPC['title']) . "',
			loglevel = " . intval($vbulletin->GPC['loglevel']) . ",
			weekday = " . intval($vbulletin->GPC['weekday']) . ",
			day = " . intval($vbulletin->GPC['day']) . ",
			hour = " . intval($vbulletin->GPC['hour']) . ",
			minute = '" . $db->escape_string(serialize($newminute)) . "',
			filename = '" . $db->escape_string($vbulletin->GPC['filename']) . "'
			WHERE cronid = " . intval($vbulletin->GPC['cronid'])
		);
	}

	require_once(DIR . '/includes/functions_cron.php');
	build_cron_item($vbulletin->GPC['cronid']);
	build_cron_next_run();

	define('CP_REDIRECT', 'cronadmin.php?do=modify');
	print_stop_message('saved_scheduled_task_x_successfully', $vbulletin->GPC['title']);
}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'cronid' 	=> TYPE_INT
	));

	print_form_header('cronadmin', 'kill');
	construct_hidden_code('cronid', $vbulletin->GPC['cronid']);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_scheduled_task']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Start Kill #######################
if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'cronid' 	=> TYPE_INT
	));

	$db->query_write("DELETE FROM " . TABLE_PREFIX . "cron WHERE cronid = " . $vbulletin->GPC['cronid']);

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
			'hour'		=> $cron['hour'],
			'day'		=> $cron['day'],
			'month'		=> -1,
			'weekday'	=> $cron['weekday']
		);

		// set '-1' fields as
		foreach ($t AS $field => $value)
		{
			$t["$field"] = iif($value == -1, '*', $value);
		}

		if (is_numeric($cron['minute']))
		{
			$cron['minute'] = array(0 => $cron['minute']);
		}
		else
		{
			$cron['minute'] = unserialize($cron['minute']);
		}

		if ($cron['minute'][0] == -1)
		{
			$t['minute'] = '*';
		}
		else
		{
			$minutes = array();
			foreach ($cron['minute'] AS $nextminute)
			{
				$minutes[] = str_pad($nextminute, 2, 0, STR_PAD_LEFT);
			}
			$t['minute'] = implode(',', $minutes);
		}

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

	$crons = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "cron ORDER BY nextrun");

	?>
	<script type="text/javascript">
	<!--
	function js_cron_jump(cronid)
	{
		task = eval("document.cpform.c" + cronid + ".options[document.cpform.c" + cronid + ".selectedIndex].value");
		switch (task)
		{
			case 'edit': window.location = "cronadmin.php?<?php echo $vbulletin->session->vars['sessionurl_js']; ?>do=edit&cronid=" + cronid; break;
			case 'kill': window.location = "cronadmin.php?<?php echo $vbulletin->session->vars['sessionurl_js']; ?>do=remove&cronid=" + cronid; break;
			default: return false; break;
		}
	}
	function js_run_cron(cronid)
	{
		window.location = "<?php echo "cronadmin.php?" . $vbulletin->session->vars['sessionurl_js'] . "do=runcron&cronid="; ?>" + cronid;
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

	while ($cron = $db->fetch_array($crons))
	{
		$timerule = fetch_cron_timerule($cron);
		$cell = array(
			$timerule['minute'],
			$timerule['hour'],
			$timerule['day'],
			$timerule['month'],
			$timerule['weekday'],
			'<b>' . $cron['title'] . '</b>',
			vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $cron['nextrun']),
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
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12949 $
|| ####################################################################
\*======================================================================*/
?>
