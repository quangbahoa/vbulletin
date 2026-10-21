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
define('CVS_REVISION', '$RCSfile$ - $Revision: 12931 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('calendar', 'cppermission', 'holiday');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_calendar.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadmincalendars'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['moderatorid'] != 0, " moderator id = ".$_REQUEST['moderatorid'], iif($_REQUEST['calendarid'] != 0, "calendar id = ".$_REQUEST['calendarid'], '')));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$monthsarray = array();
foreach ($months AS $index => $month)
{
	$monthsarray["$index"] = $vbphrase["$month"];
}

$daysarray = array();
foreach ($days AS $index => $day)
{
	$daysarray["$index"] = $vbphrase["$day"];
}

$periodarray = array();
foreach ($period AS $index => $p)
{
	$periodarray["$index"] = $vbphrase["$p"];
}



print_cp_header($vbphrase['calendar_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start Add Custom Calendar Field #######################

if ($_REQUEST['do'] == 'addcustom')
{

	globalize($_REQUEST, array('calendarcustomfieldid' => INT, 'calendarid'));

	if ($calendarcustomfieldid)
	{ // edit
		$fieldinfo = $DB_site->query_first("
			SELECT * FROM " . TABLE_PREFIX . "calendarcustomfield
			WHERE calendarcustomfieldid = $calendarcustomfieldid
		");
		if (!empty($fieldinfo['options']))
		{
			$fieldinfo['options'] =  implode("\n", unserialize($fieldinfo['options']));
		}
		$action = construct_phrase($vbphrase['x_y_id_z'], $vbphrase['custom_field'], $fieldinfo['title'], $calendarcustomfieldid);
	}
	else if ($calendarid)
	{ // Add new
		$fieldinfo = array('length' => 25);
		$action = $vbphrase['add_new_custom_field'];
	}
	else
	{
		print_stop_message('must_save_calendar');
	}

	print_form_header('admincalendar', 'doaddcustom');
	construct_hidden_code('calendarid', $calendarid);
	construct_hidden_code('calendarcustomfieldid', $calendarcustomfieldid);
	print_table_header($action);
	print_input_row($vbphrase['title'], 'title', $fieldinfo['title']);
	print_textarea_row($vbphrase['description'], 'description', $fieldinfo['description']);
	print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'options', $fieldinfo['options']);
	print_yes_no_row($vbphrase['allow_user_to_input_their_own_value_for_this_custom_field'], 'allowentry', $fieldinfo['allowentry']);
	print_input_row($vbphrase['max_length_of_allowed_user_input'], 'length', $fieldinfo['length'], 1, 5);
	print_yes_no_row($vbphrase['field_required'], 'required', $fieldinfo['required']);
	print_submit_row($vbphrase['save']);
}


// ###################### Do Add Custom Calendar Field #######################
if ($_POST['do'] == 'doaddcustom')
{

	globalize($_POST, array(
			'title' => STR,
			'options' => STR,
			'description' => STR,
			'allowentry' => INT,
			'required' => INT,
			'calendarcustomfieldid' => INT,
			'calendarid' => INT,
			'length' => INT)
	);

	if (empty($title))
	{
		print_stop_message('invalid_custom_field_specified');
	}
	else if (empty($options) AND !$allowentry)
	{
		print_stop_message('must_specify_field_option');
	}
	if (!empty($options))
	{
		$optionsarray = explode("\n", htmlspecialchars_uni($options));
		$temp = array();
		array_unshift($optionsarray, 0);
		unset($optionsarray[0]);
		foreach ($optionsarray AS $index => $value)
		{
				$optionsarray["$index"] = trim($value);
		}
		$options = serialize($optionsarray);
	}

	if ($calendarcustomfieldid)
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "calendarcustomfield
			SET title = '" . addslashes($title) . "',
				options = '" . addslashes($options) . "',
				allowentry = $allowentry,
				required = $required,
				length = $length,
				description = '" . addslashes($description) . "'
			WHERE calendarcustomfieldid = $calendarcustomfieldid
		");
	}
	else if ($calendarid)
	{ // Add new Entry
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "calendarcustomfield	(calendarid, title, options, allowentry, required, length, description)
			VALUES ($calendarid, '" . addslashes($title) . "', '" . addslashes($options) . "', $allowentry, $required, $length, '" . addslashes($description) . "')
		");
	}

	define('CP_REDIRECT', "admincalendar.php?do=edit&calendarid=$calendarid");
	print_stop_message('saved_custom_field_x_successfully', $title);
}


// ###################### Remove Custom Calendar Field #######################
if ($_POST['do'] == 'killcustom')
{
	$_POST['calendarcustomfieldid'] = intval($_POST['calendarcustomfieldid']);
	$calendarid = $DB_site->query_first("
		SELECT calendarid FROM " . TABLE_PREFIX . "calendarcustomfield
		WHERE calendarcustomfieldid = $_POST[calendarcustomfieldid]
	");
	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "calendarcustomfield
		WHERE calendarcustomfieldid = $_POST[calendarcustomfieldid]
	");

	define('CP_REDIRECT', "admincalendar.php?do=edit&calendarid=$calendarid[calendarid]");
	print_stop_message('deleted_custom_field_successfully');
}

// ###################### Start add / edit Calendar #######################
if ($_REQUEST['do'] == 'add' or $_REQUEST['do'] == 'edit')
{

	print_form_header('admincalendar', 'update');

	$calendarid = intval($_REQUEST['calendarid']);

	$exampledaterange = (date('Y') - 3) . '-' . (date('Y') + 3);

	if ($_REQUEST['do'] == 'add')
	{
		// need to set default yes permissions!
		$calendar = array(
			'active' => 1,
			'allowbbcode' => 1,
			'allowimgcode' => 1,
			'allowsmilies' => 1,
			'startofweek' => 1,
			'showholidays' => 1,
			'showbirthdays' => 1,
			'showweekends' => 1,
			'cutoff' => 40,
			'eventcount' => 4,
			'birthdaycount' => 4,
			'daterange' => $exampledaterange,
			'usetimes' => 1,
			'usetranstime' => 1
		);

		$maxdisplayorder = $DB_site->query_first("
			SELECT MAX(displayorder) AS displayorder
			FROM " . TABLE_PREFIX . "calendar
		");
		$calendar['displayorder'] = $maxdisplayorder['displayorder'] + 1;

		print_table_header($vbphrase['add_new_calendar']);
	}
	else
	{
		$calendar = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "calendar WHERE calendarid = $calendarid");
		construct_hidden_code('calendarid', $calendarid);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['calendar'], $calendar['title'], $calendar['calendarid']));

		$calendar['daterange'] = $calendar['startyear'] . '-' . $calendar['endyear'];

		$customfields = $DB_site->query("
			SELECT title, calendarcustomfieldid
			FROM " . TABLE_PREFIX . "calendarcustomfield
			WHERE calendarid = $calendarid
		");
		$fieldcount = $DB_site->num_rows($customfields);

		$getoptions = convert_bits_to_array($calendar['options'], $_CALENDAROPTIONS);
		$calendar = array_merge($calendar, $getoptions);
		$geteaster = convert_bits_to_array($calendar['holidays'], $_CALENDARHOLIDAYS);
		$calendar = array_merge($calendar, $geteaster);

		if (!empty($calendar['neweventemail']))
		{
			$calendar['neweventemail'] =  implode("\n", unserialize($calendar['neweventemail']));
		}
	}

	print_input_row($vbphrase['title'], 'calendar[title]', $calendar['title']);
	//print_textarea_row($vbphrase['description'], 'calendar[description]', $calendar['description']);
	print_input_row("$vbphrase[display_order] <dfn>$vbphrase[zero_equals_no_display]</dfn>", 'calendar[displayorder]', $calendar['displayorder'], 1, 5);

	print_table_header($vbphrase['custom_fields'] . '&nbsp;&nbsp;&nbsp;' . construct_link_code($vbphrase['add_new_custom_field'], "admincalendar.php?$session[sessionurl]calendarid=$calendarid&do=addcustom"));
	if ($fieldcount > 0)
	{
		while ($field = $DB_site->fetch_array($customfields))
		{
			print_label_row($field['title'], construct_link_code($vbphrase['modify'], "admincalendar.php?$session[sessionurl]do=addcustom&calendarid=$calendarid&calendarcustomfieldid=$field[calendarcustomfieldid]") . ' ' . construct_link_code($vbphrase['delete'], "admincalendar.php?$session[sessionurl]do=deletecustom&calendarid=$calendarid&calendarcustomfieldid=$field[calendarcustomfieldid]"), '', 'top', 'customfields');
		}
	}

	print_table_header($vbphrase['moderation_options']);
	print_textarea_row($vbphrase['emails_to_notify_when_event'], 'calendar[neweventemail]', $calendar['neweventemail']);
	print_yes_no_row($vbphrase['moderate_events'] . ' <dfn>(' . $vbphrase['require_moderator_validation_before_new_events_are_displayed'] . ')</dfn>', 'calendar[moderatenew]', $calendar['moderatenew']);
	print_table_header($vbphrase['options']);

	print_input_row(construct_phrase($vbphrase['date_range_dfn'], $exampledaterange), 'calendar[daterange]', $calendar['daterange']);
	print_select_row($vbphrase['default_view'], 'options[default]', array(0 => $vbphrase['monthly'], $vbphrase['weekly']), $calendar['default']);
	print_select_row($vbphrase['start_of_week'], 'calendar[startofweek]', array(1 => $vbphrase['sunday'], $vbphrase['monday'], $vbphrase['tuesday'], $vbphrase['wednesday'], $vbphrase['thursday'], $vbphrase['friday'], $vbphrase['saturday']), $calendar['startofweek']);
	print_input_row($vbphrase['event_title_cutoff'], 'calendar[cutoff]', $calendar['cutoff'], 1, 5);
	print_input_row($vbphrase['event_count_max_events_per_day'], 'calendar[eventcount]', $calendar['eventcount'], 1, 5);
	print_input_row($vbphrase['birthday_count_max_birthdays_per_day'], 'calendar[birthdaycount]', $calendar['birthdaycount'], 1, 5);

	print_table_header($vbphrase['enable_disable_features']);
	print_yes_no_row($vbphrase['show_birthdays_on_this_calendar'], 'options[showbirthdays]', $calendar['showbirthdays']);
	print_yes_no_row($vbphrase['show_holidays_on_this_calendar'], 'options[showholidays]', $calendar['showholidays']);
	$endtable = 0;
	foreach ($_CALENDARHOLIDAYS AS $holiday => $value)
	{
		$holidaytext .= iif(!$endtable, "<tr>\n");
		$checked = iif($calendar["$holiday"], HTML_CHECKED);
		$holidaytext .= "<td><input type=\"checkbox\" name=\"holidays[$holiday]\" value=\"1\" $checked />$vbphrase[$holiday]</td>\n";
		$holidaytext .= iif($endtable, "</tr>\n");
		$endtable = iif($endtable, 0, 1);
	}
	print_label_row($vbphrase['show_easter_holidays_on_this_calendar'], '<table cellspacing="2" cellpadding="0" border="0">' . $holidaytext . '</tr></table>', '', 'top', 'holidays');

	print_yes_no_row($vbphrase['show_weekend'], 'options[showweekends]', $calendar['showweekends']);
	print_yes_no_row($vbphrase['allow_html'], 'options[allowhtml]', $calendar['allowhtml']);
	print_yes_no_row($vbphrase['allow_bbcode'], 'options[allowbbcode]', $calendar['allowbbcode']);
	print_yes_no_row($vbphrase['allow_img_code'], 'options[allowimgcode]', $calendar['allowimgcode']);
	print_yes_no_row($vbphrase['allow_smilies'], 'options[allowsmilies]', $calendar['allowsmilies']);

	print_submit_row($vbphrase['save']);
}

// ###################### Start Delete Custom Calendar Field #######################
if ($_REQUEST['do'] == 'deletecustom')
{

	print_delete_confirmation('calendarcustomfield', intval($_REQUEST['calendarcustomfieldid']), 'admincalendar', 'killcustom', 'custom_calendar_field');
}

// ###################### Start insert/update #######################
if ($_POST['do'] == 'update')
{

	globalize($_POST, array('calendarid' => INT, 'calendar', 'options', 'holidays'));

	require_once('./includes/functions_misc.php');
	$calendar['options'] = convert_array_to_bits($options, $_CALENDAROPTIONS);
	$calendar['holidays'] = convert_array_to_bits($holidays, $_CALENDARHOLIDAYS);

	$email = array();
	$emails = explode("\n", $calendar['neweventemail']);
	foreach ($emails AS $index => $value)
	{
		$value = trim($value);
		if (!empty($value))
		{
			$email[] = $value;
		}
	}
	$options = serialize($optionsarray);
	$calendar['neweventemail'] = serialize($email);

	$daterange = explode('-', $calendar['daterange']);
	$calendar['startyear'] = intval($daterange[0]);
	$calendar['endyear'] = intval($daterange[1]);
	unset($calendar['daterange']);
	if (!$calendar['startyear'] OR $calendar['startyear'] < 1970 OR $calendar['endyear'] > 2037 OR !$calendar['endyear']OR $calendar['startyear'] > $calendar['endyear'])
	{
		print_stop_message('invalid_date_range_specified');
	}

	define('CP_REDIRECT', 'admincalendar.php?do=modify');

	if (!empty($calendarid))
	{
		$DB_site->query(fetch_query_sql($calendar, 'calendar', "WHERE calendarid=$calendarid"));
		print_stop_message('saved_calendar_x_successfully', $calendar['title']);
	}
	else
	{
		$DB_site->query(fetch_query_sql($calendar, 'calendar'));
		print_stop_message('saved_calendar_x_successfully', $calendar['title']);
	}

}

// ###################### Start Modify Calendar #######################
if ($_REQUEST['do'] == 'modify')
{

	// a little javascript for the options menus
	?>
	<script type="text/javascript">
	function js_calendar_jump(calendarinfo)
	{
		action = eval("document.cpform.c" + calendarinfo + ".options[document.cpform.c" + calendarinfo + ".selectedIndex].value");
		if (action != '')
		{
			switch (action)
			{
				case 'edit':
					page = "admincalendar.php?do=edit&calendarid=";
					break;
				case 'view':
					page = "../calendar.php?calendarid=";
					break;
				case 'remove':
					page = "admincalendar.php?do=remove&calendarid=";
					break;
				case 'perms':
					page = "calendarpermission.php?do=modify&devnull=";
					break;
			}
			document.cpform.reset();
			jumptopage = page + calendarinfo + "&s=<?php echo $session['sessionhash']; ?>";
			if (action=='perms')
			{
				window.location = jumptopage + '#calendar' + calendarinfo;
			}
			else
			{
				window.location = jumptopage;
			}
		}
		else
		{
			alert('<?php echo $vbphrase['invalid_action_specified']; ?>');
		}
	}
	function js_moderator_jump(calendarinfo)
	{
		modinfo = eval("document.cpform.m" + calendarinfo + ".options[document.cpform.m" + calendarinfo + ".selectedIndex].value");
		document.cpform.reset();
		switch (modinfo)
		{
			case 'Add':
				window.location = "admincalendar.php?s=<?php echo $session['sessionhash']; ?>&do=addmod&calendarid=" + calendarinfo;
				break;
			case '':
				return false;
				break;
			default:
				window.location = "admincalendar.php?s=<?php echo $session['sessionhash']; ?>&do=editmod&moderatorid=" + modinfo;
		}
	}
	</script>
	<?php

	cache_calendar_moderators();

	$calendaroptions = array(
		'edit' => $vbphrase['edit'],
		'view' => $vbphrase['view'],
		'remove' => $vbphrase['delete'],
		'perms' => $vbphrase['permissions']
	);

	print_form_header('admincalendar', 'doorder');
	print_table_header($vbphrase['calendar_manager'], 4);
	print_description_row($vbphrase['if_you_change_display_order'],0,4);
	print_cells_row(array('&nbsp; ' . $vbphrase['title'], $vbphrase['controls'], $vbphrase['order_by'], $vbphrase['moderators']), 1, 'tcat');

	$calendars = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "calendar ORDER BY displayorder");
	while ($calendar = $DB_site->fetch_array($calendars))
	{
		$cell = array();
		$cell[] = "&nbsp;<b><a href=\"admincalendar.php?$session[sessionurl]do=edit&calendarid=$calendar[calendarid]\">$calendar[title]</a></b>";
		$cell[] = "\n\t<select name=\"c$calendar[calendarid]\" onchange=\"js_calendar_jump($calendar[calendarid]);\" class=\"bginput\">\n" . construct_select_options($calendaroptions) . "\t</select>\n\t<input type=\"button\" class=\"button\" value=\"".$vbphrase['go']."\" onclick=\"js_calendar_jump($calendar[calendarid]);\" />\n\t";
		$cell[] = "<input type=\"text\" class=\"bginput\" name=\"order[$calendar[calendarid]]\" value=\"$calendar[displayorder]\" tabindex=\"1\" size=\"3\" title=\"" . $vbphrase['order_by']  . 'ssss' ."\" />";

		$mods = array('no_value' => $vbphrase['moderators'].' (' . sizeof($cmodcache["$calendar[calendarid]"]) . ')', 'Add' => $vbphrase['add']);
		if (is_array($cmodcache["$calendar[calendarid]"]))
		{
			foreach ($cmodcache["$calendar[calendarid]"] AS $moderator)
			{
				$mods["$moderator[calendarmoderatorid]"] = "&gt; $moderator[username]";
			}
		}
		$cell[] = "\n\t<select name=\"m$calendar[calendarid]\" onchange=\"js_moderator_jump($calendar[calendarid]);\" class=\"bginput\">\n" . construct_select_options($mods) . "\t</select>\n\t<input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_moderator_jump($calendar[calendarid]);\" />\n\t";

		print_cells_row($cell);
	}

	print_table_footer(4, '<input type="submit" class="button" value="' . $vbphrase['save_display_order'] . '" accesskey="s" tabindex="1" />' . construct_button_code($vbphrase['add_new_calendar'], "admincalendar.php?$session[sessionurl]do=add"));

}

// ###################### Start do order #######################
if ($_POST['do'] == 'doorder')
{

	$order = $_POST['order'];

	if (is_array($order))
	{
		$calendars = $DB_site->query("SELECT calendarid,displayorder FROM " . TABLE_PREFIX . "calendar");
		while ($calendar = $DB_site->fetch_array($calendars))
		{
			$displayorder = intval($order["$calendar[calendarid]"]);
			if ($calendar['displayorder'] != $displayorder)
			{
				$DB_site->query("UPDATE " . TABLE_PREFIX . "calendar SET displayorder=$displayorder WHERE calendarid=$calendar[calendarid]");
			}
		}
	}

	define('CP_REDIRECT', 'admincalendar.php?do=modify');
	print_stop_message('saved_display_order_successfully');

}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'remove')
{

	print_delete_confirmation('calendar', intval($_REQUEST['calendarid']), 'admincalendar', 'kill', 'calendar', 0);

}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{

	$calendarid = intval($_POST['calendarid']);

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "event WHERE calendarid = $calendarid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "calendarpermission WHERE calendarid = $calendarid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "calendar WHERE calendarid = $calendarid");

	define('CP_REDIRECT', 'admincalendar.php');
	print_stop_message('deleted_calendar_successfully');

}

// ##################### Start Add/Edit Moderator ##########

if ($_REQUEST['do'] == 'addmod' or $_REQUEST['do'] == 'editmod')
{

	globalize($_REQUEST, array('moderatorid' => INT, 'calendarid' => INT));

	if (empty($moderatorid))
	{
		// add moderator - set default values
		$calendarinfo = $DB_site->query_first("SELECT calendarid, title AS calendartitle FROM " . TABLE_PREFIX . "calendar WHERE calendarid = $calendarid");
		$moderator = array(
			'caneditevents' => 1,
			'candeleteevents' => 1,
			'canmoderateevents' => 1,
			'canviewips' => 1,
			'canmoveevents' => 1,
			'calendarid' => $calendarinfo['calendarid'],
			'calendartitle' => $calendarinfo['calendartitle']
		);
		print_form_header('admincalendar', 'updatemod');
		print_table_header(construct_phrase($vbphrase['add_new_moderator_to_calendar_x'], $calendarinfo['calendartitle']));
	}
	else
	{
		// edit moderator - query moderator
		$moderator = $DB_site->query_first("
			SELECT calendarmoderatorid, calendarmoderator.userid, calendarmoderator.calendarid, permissions, user.username, title AS calendartitle
			FROM " . TABLE_PREFIX . "calendarmoderator AS calendarmoderator
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = calendarmoderator.userid)
			LEFT JOIN " . TABLE_PREFIX . "calendar AS calendar ON (calendar.calendarid = calendarmoderator.calendarid)
			WHERE calendarmoderatorid = $moderatorid
		");

		$perms = convert_bits_to_array($moderator['permissions'], $_BITFIELD['calmoderatorpermissions'], 1);
		$moderator = array_merge($perms, $moderator);

		// delete link
		print_form_header('admincalendar', 'removemod');
		construct_hidden_code('moderatorid', $moderatorid);
		print_table_header($vbphrase['if_you_would_like_to_remove_this_moderator'] . ' &nbsp; &nbsp; <input type="submit" class="button" value="' . $vbphrase['delete_moderator'] . '" style="font:bold 11px tahoma" />');
		print_table_footer();

		print_form_header('admincalendar', 'updatemod');
		construct_hidden_code('moderatorid', $moderatorid);
		print_table_header(construct_phrase($vbphrase['edit_moderator_x_for_calendar_y'], $moderator['username'], $moderator['calendartitle']));
	}

	print_calendar_chooser('moderator[calendarid]', $moderator['calendarid'], '', $vbphrase['calendar'], 0);
	if (empty($moderatorid))
	{
		print_input_row($vbphrase['moderator_username'], 'modusername', $moderator['username']);
	}
	else
	{
		print_label_row($vbphrase['moderator_username'], '<b>' . $moderator['username'] . '</b>');
	}

	print_table_header($vbphrase['calendar_permissions']);
	// post permissions
	print_yes_no_row($vbphrase['can_edit_events'], 'modperms[caneditevents]', $moderator['caneditevents']);
	print_yes_no_row($vbphrase['can_delete_events'], 'modperms[candeleteevents]', $moderator['candeleteevents']);
	print_yes_no_row($vbphrase['can_move_events'], 'modperms[canmoveevents]', $moderator['canmoveevents']);
	print_yes_no_row($vbphrase['can_moderate_events'], 'modperms[canmoderateevents]', $moderator['canmoderateevents']);
	print_yes_no_row($vbphrase['can_view_ip_addresses'], 'modperms[canviewips]', $moderator['canviewips']);

	print_submit_row(iif(!empty($moderatorid), $vbphrase['update'], $vbphrase['save']));

}

// ###################### Start insert / update moderator #######################
if ($_POST['do'] == 'updatemod')
{

	globalize($_POST, array('modusername', 'moderator', 'modperms', 'moderatorid' => INT));

	if (!$moderatorid)
	{
		$modusername = htmlspecialchars_uni($modusername);
		$userinfo = $DB_site->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username='" . addslashes($modusername) . "'
		");
	}
	else
	{
		$userinfo = $DB_site->query_first("
			SELECT user.username, user.userid
			FROM " . TABLE_PREFIX . "calendarmoderator AS calendarmoderator
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (calendarmoderator.userid = user.userid)
			WHERE calendarmoderatorid = $moderatorid
		");

		$modusername = $userinfo['username'];
	}

	$calendarinfo = $DB_site->query_first("
		SELECT calendarid,title
		FROM " . TABLE_PREFIX . "calendar
		WHERE calendarid = $moderator[calendarid]
	");

	if ($calendarinfo['calendarid'] AND ($userinfo['userid'] OR $moderatorid))
	{ // no errors

		require_once('./includes/functions_misc.php');
		$moderator['permissions'] = convert_array_to_bits($modperms, $_BITFIELD['calmoderatorpermissions'], 1);
		if (!empty($moderatorid))
		{ // update
			$DB_site->query(fetch_query_sql($moderator, 'calendarmoderator', "WHERE calendarmoderatorid=$moderatorid"));

			define('CP_REDIRECT', 'admincalendar.php');
			print_stop_message('saved_moderator_x_successfully', $modusername);
		}
		else
		{ // insert
			$moderator['userid'] = $userinfo['userid'];
			$DB_site->query(fetch_query_sql($moderator, 'calendarmoderator'));

			define('CP_REDIRECT', 'admincalendar.php');
			print_stop_message('saved_moderator_x_successfully', $modusername);
		}

	}
	else
	{ // error
		if (!$userinfo['userid'])
		{
			print_stop_message('no_moderator_matched_your_query');
		}
		if (!$calendarinfo['calendarid'])
		{
			print_stop_message('invalid_calendar_specified');
		}
	}

}

// ###################### Start Remove moderator #######################

if ($_REQUEST['do'] == 'removemod')
{
	print_delete_confirmation('calendarmoderator', intval($_REQUEST['moderatorid']), 'admincalendar', 'killmod', 'calendar_moderator');
}

// ###################### Start Kill moderator #######################

if ($_POST['do'] == 'killmod')
{
	$calendarmoderatorid = intval($_POST['calendarmoderatorid']);

	$getuserid = $DB_site->query_first("
		SELECT user.userid,usergroupid
		FROM " . TABLE_PREFIX . "calendarmoderator AS calendarmoderator
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE calendarmoderatorid = $calendarmoderatorid
	");
	if (!$getuserid)
	{
		print_stop_message('user_no_longer_moderator');
	}
	else
	{
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "calendarmoderator
			WHERE calendarmoderatorid = $calendarmoderatorid
		");

		define('CP_REDIRECT', 'admincalendar.php');
		print_stop_message('deleted_moderator_successfully');
	}
}

// ##################### Holidays ###################################
if ($_REQUEST['do'] == 'modifyholiday')
{

	print_form_header('', '');
	print_table_header($vbphrase['holidays']);
	print_table_footer();

	$holidays = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "holiday");

	?>
	<script type="text/javascript">
	function js_holiday_jump(holidayid, obj)
	{
		task = obj.options[obj.selectedIndex].value;
		switch (task)
		{
			case 'edit': window.location = "admincalendar.php?s=<?php echo $session['sessionhash']; ?>&do=updateholiday&holidayid=" + holidayid; break;
			case 'kill': window.location = "admincalendar.php?s=<?php echo $session['sessionhash']; ?>&do=removeholiday&holidayid=" + holidayid; break;
			default: return false; break;
		}
	}
	</script>
	<?php

	$options = array(
		'edit' => $vbphrase['edit'],
		'kill' => $vbphrase['delete'],
		'no_value' => '_________________'
	);

	print_form_header('admincalendar', 'updateholiday');
	print_cells_row(array($vbphrase['title'], $vbphrase['recurring_option'], $vbphrase['controls']), 1);

	while ($holiday = $DB_site->fetch_array($holidays))
	{
		$recuroptions = explode('|', $holiday['recuroption']);
		$cell = array();

		$cell[] = '<b>' . $vbphrase['holiday_title_' . $holiday['varname']] . '</b>';
		if ($holiday['recurring'] == 6)
		{
			$cell[] = construct_phrase($vbphrase['every_x_y'], $monthsarray["$recuroptions[0]"], $recuroptions[1]);
		}
		else
		{
			$cell[] = construct_phrase($vbphrase['the_x_y_of_z'], $periodarray["$recuroptions[0]"], $daysarray["$recuroptions[1]"], $monthsarray["$recuroptions[2]"]);
		}

		$cell[] = "\n\t<select name=\"u$holiday[holidayid]\" onchange=\"js_holiday_jump($holiday[holidayid], this);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>\n\t<input type=\"button\" class=\"button\" value=\"".$vbphrase['go']."\" onclick=\"js_holiday_jump($holiday[holidayid], this.form.u$holiday[holidayid]);\" />\n\t";
		print_cells_row($cell);
	}

	print_submit_row($vbphrase['add_new_holiday'], 0, 3);

}

// #####################Edit Hoiday###################################
if ($_REQUEST['do'] == 'updateholiday')
{
	globalize($_REQUEST, array('holidayid' => INT));

	if ($holidayid) // Existing Holiday
	{
		$holidayinfo = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "holiday WHERE holidayid = $holidayid");
		$options = explode('|', $holidayinfo['recuroption']);
		$checked = array($holidayinfo['recurring'] => 'checked="checked"');
	}
	else // New Holiday
	{
		$holidayinfo = array('allowsmilies' => 1);
		$checked = array(6 => 'checked="checked"');
	}

	print_form_header('admincalendar', 'saveholiday');
	construct_hidden_code('holidayid', $holidayid);
	if ($holidayid)
	{
		construct_hidden_code('holidayinfo[varname]', $holidayinfo['varname']);
	}
	if ($holidayid)
	{
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['holiday'], $vbphrase['holiday_title_' . $holidayinfo['varname']], $holidayinfo['holidayid']));
		print_label_row($vbphrase['varname'], $holidayinfo['varname']);
	}
	else
	{
		print_table_header($vbphrase['add_new_holiday']);
		print_input_row($vbphrase['varname'], 'holidayinfo[varname]');
	}
	print_input_row($vbphrase['title'], 'holidayinfo[title]', $vbphrase['holiday_title_' . $holidayinfo['varname']]);

	print_textarea_row($vbphrase['description'], 'holidayinfo[event]', $vbphrase['holiday_event_' . $holidayinfo['varname']]);

	print_label_row($vbphrase['recurring_option'],
		'<input type="radio" name="holidayinfo[recurring]" value="6" tabindex="1" ' . $checked[6] . '/>' .
		construct_phrase($vbphrase['every_x_y'], construct_month_select_html($options[0], 'month1'),  construct_day_select_html($options[1], 'day1')) . '
		<br /><input type="radio" name="holidayinfo[recurring]" value="7" tabindex="1" ' . $checked[7] . '/>' .
		construct_phrase($vbphrase['the_x_y_of_z'], '<select name="period" tabindex="1" class="bginput">' . construct_select_options($periodarray, $options[0]) . '</select>', '<select name="day2" tabindex="1" class="bginput">' . construct_select_options($daysarray, $options[1]) . '</select>', construct_month_select_html($options[2], 'month2')),
		'', 'top', 'recurring'
	);
	print_yes_no_row($vbphrase['allow_smilies'], 'holidayinfo[allowsmilies]', $holidayinfo['allowsmilies']);

	print_submit_row($vbphrase['save']);

}

// ################# Save or Create a Holiday ###################
if($_POST['do'] == 'saveholiday')
{
	globalize($_POST, array('holidayid' => INT, 'holidayinfo', 'month1', 'day1', 'month2', 'day2', 'period'));

	if ($holidayinfo['recurring'] == 6)
	{
		$holidayinfo['recuroption'] = $month1 . '|' . $day1;
	}
	else
	{
		$holidayinfo['recuroption'] = $period . '|' . $day2 . '|' . $month2;
	}

	if (empty($holidayid))
	{
		if (empty($holidayinfo['varname']) OR empty($holidayinfo['title']))
		{
			print_stop_message('please_complete_required_fields');
		}

		if (!preg_match('#^[a-z0-9_]+$#i', $holidayinfo['varname'])) // match a-z, A-Z, 0-9, ',', _ only
		{
			print_stop_message('invalid_phrase_varname');
		}

		if($phrases = $DB_site->query_first("
			SELECT varname, text, languageid
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid IN(0) AND
				phrasetypeid = " . PHRASETYPEID_HOLIDAY . " AND
				varname IN ('holiday_title_" . addslashes($holidayinfo['varname']) . "', 'holiday_event_" . addslashes($holidayinfo['varname']) . "')
		"))
		{
			print_stop_message('there_is_already_phrase_named_x', $holidayinfo['varname']);
		}

		$DB_site->query("INSERT INTO " . TABLE_PREFIX . "holiday (holidayid) VALUES (NULL)");
		$holidayid = $DB_site->insert_id();

		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "phrase
			(languageid, phrasetypeid, varname, text)
			VALUES
			(0, " . PHRASETYPEID_HOLIDAY . ", 'holiday_title_" . addslashes($holidayinfo['varname']) . "', '" . addslashes($holidayinfo['title']) . "')
		");
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "phrase
			(languageid, phrasetypeid, varname, text)
			VALUES
			(0, " . PHRASETYPEID_HOLIDAY . ", 'holiday_event_" . addslashes($holidayinfo['varname']) . "', '" . addslashes($holidayinfo['event']) . "')
		");
		require_once('./includes/adminfunctions_language.php');
		build_language();
	}
	else
	{
		if (empty($holidayinfo['title']))
		{
			print_stop_message('please_complete_required_fields');
		}
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "holiday
		SET allowsmilies = $holidayinfo[allowsmilies],
		recuroption = '$holidayinfo[recuroption]',
		recurring = $holidayinfo[recurring],
		varname = '" . addslashes($holidayinfo[varname]) . "'
		WHERE holidayid = $holidayid
	");

	$phrases = $DB_site->query("
		SELECT varname, text, languageid
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN(0) AND
			phrasetypeid = " . PHRASETYPEID_HOLIDAY . " AND
			varname IN ('holiday_title_" . addslashes($holidayinfo['varname']) . "', 'holiday_event_" . addslashes($holidayinfo['varname']) . "')
	");
	$dophraseupdate = false;
	while ($phrase = $DB_site->fetch_array($phrases))
	{
		if ($phrase['varname'] == "holiday_title_$holidayinfo[varname]")
		{
			if ($phrase['text'] != $holidayinfo['title'])
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "phrase
					SET text = '" . addslashes($holidayinfo['title']) . "'
					WHERE languageid = $phrase[languageid] AND
						varname = 'holiday_title_" . addslashes($holidayinfo['varname']) . "'
				");
				$dophraseupdate = true;
			}
		}
		else if ($phrase['varname'] == "holiday_event_$holidayinfo[varname]")
		{
			if ($phrase['text'] != $holidayinfo['event'])
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "phrase
					SET text = '" . addslashes($holidayinfo['event']) . "'
					WHERE languageid = $phrase[languageid] AND
						varname = 'holiday_event_" . addslashes($holidayinfo['varname']) . "'
				");
				$dophraseupdate = true;
			}
		}
	}
	if ($dophraseupdate)
	{
		require_once('./includes/adminfunctions_language.php');
		build_language();
	}

	define('CP_REDIRECT', 'admincalendar.php?do=modifyholiday');

	require_once('./includes/functions_databuild.php');
	build_events();

	if (empty($holidayid))
	{
		print_stop_message('saved_holiday_x_successfully', $holidayinfo['title']);
	}
	else
	{
		print_stop_message('saved_holiday_x_successfully', $holidayinfo['title']);
	}
}

// ################# Delete a Holiday ###########################
if ($_REQUEST['do'] == 'removeholiday')
{

	globalize($_REQUEST, array('holidayid' => INT));

	$holiday = $DB_site->query_first("SELECT varname FROM " . TABLE_PREFIX . "holiday WHERE holidayid = $holidayid");
	print_form_header('admincalendar', 'doremoveholiday', 0, 1, '', '75%');
	construct_hidden_code('holidayid', $holidayid);
	print_table_header(construct_phrase($vbphrase['confirm_deletion_of_holiday_x'], $vbphrase['holiday_title_' . $holiday['varname']]));
	print_description_row("
			<blockquote><br />
			".construct_phrase($vbphrase['are_you_sure_you_want_to_delete_the_holiday_called_x'], $vbphrase['holiday_title_' . $holiday['varname']], $_REQUEST['holidayid'])."
			<br /></blockquote>\n\t");
	print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);

}

// ################ Really Delete a Holiday ####################
if ($_POST['do'] == 'doremoveholiday')
{
	$_POST['holidayid'] = intval($_POST['holidayid']);

	$holidayinfo = $DB_site->query_first("SELECT varname FROM " . TABLE_PREFIX . "holiday WHERE holidayid = $_POST[holidayid]");

	// delete phrases
	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (0) AND
			phrasetypeid = " . PHRASETYPEID_HOLIDAY . " AND
			varname IN ('holiday_title_" . addslashes($holidayinfo['varname']) . "', 'holiday_event_" . addslashes($holidayinfo['varname']) . "')
	");

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "holiday WHERE holidayid=$_POST[holidayid]");

	require_once('./includes/adminfunctions_language.php');
	build_language();

	require_once('./includes/functions_databuild.php');
	build_events();

	define('CP_REDIRECT', 'admincalendar.php?do=modifyholiday');
	print_stop_message('deleted_holiday_successfully');
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12931 $
|| ####################################################################
\*======================================================================*/
?>