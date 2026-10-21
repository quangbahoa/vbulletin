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
define('CVS_REVISION', '$RCSfile$ - $Revision: 9093 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('calendar', 'cppermission');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadmincalendars'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['calendarpermissionid'] != 0, "calendarpermission id = " . $_REQUEST['calendarpermissionid'], iif($_REQUEST['calendarid'] != 0, "calendar id = ". $_REQUEST['calendarid'] . iif($_REQUEST['usergroupid'] != 0, " / usergroup id = " . $_REQUEST['usergroupid']))));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['calendar_permissions_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'calendarpermissionid' => INT,
		'calendarid' => INT,
		'usergroupid' => INT,
	));

	?>
	<script type="text/javascript">
	<!--
	function js_set_custom()
	{
		if (document.cpform.useusergroup[1].checked == false)
		{
			if (confirm('<?php echo addslashes($vbphrase['must_enable_custom_permissions']);?>'))
			{
				document.cpform.useusergroup[1].checked = true;
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return true;
		}
	}
	// -->
	</script>
	<?php

	print_form_header('calendarpermission', 'doupdate');

	if ($calendarpermissionid)
	{
		$getperms = $DB_site->query_first("
			SELECT calendarpermission.*, usergroup.title AS grouptitle, calendar.title AS calendartitle
			FROM " . TABLE_PREFIX . "calendarpermission AS calendarpermission
			INNER JOIN " . TABLE_PREFIX . "calendar AS calendar ON (calendar.calendarid = calendarpermission.calendarid)
			INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = calendarpermission.usergroupid)
			WHERE calendarpermissionid = $calendarpermissionid
		");
		$usergroup['title'] = $getperms['grouptitle'];
		$calendar['title'] = $getperms['calendartitle'];
		construct_hidden_code('calendarpermissionid', $calendarpermissionid);
	}
	else
	{
		$calendar = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "calendar WHERE calendarid = $calendarid");
		$usergroup = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "usergroup WHERE usergroupid = $usergroupid");
		$_permsgetter_ = 'usergroup permissions';

		$getperms = $DB_site->query_first("
			SELECT usergroup.title as grouptitle, calendarpermissions
			FROM " . TABLE_PREFIX . "usergroup AS usergroup
			WHERE usergroupid = $usergroupid
		");

		construct_hidden_code('calendarpermission[usergroupid]', $usergroupid);
		construct_hidden_code('calendarid', $calendarid);
	}
	$calendarpermission = convert_bits_to_array($getperms['calendarpermissions'], $_BITFIELD['usergroup']['calendarpermissions']);

	print_table_header(construct_phrase($vbphrase['edit_calendar_permissions_for_usergroup_x_in_calendar_y'], $usergroup['title'], $calendar['title']));
	print_description_row('
		<label for="uug_1"><input type="radio" name="useusergroup" value="1" id="uug_1" tabindex="1" onclick="this.form.reset(); this.checked=true;"' . iif(!$calendarpermissionid, ' checked="checked"', '') . ' />' . $vbphrase['use_default_permissions'] . '</label>
		<br />
		<label for="uug_0"><input type="radio" name="useusergroup" value="0" id="uug_0" tabindex="1"' . iif($calendarpermissionid, ' checked="checked"', '') . ' />' . $vbphrase['use_custom_permissions'] . '</label>
	', 0, 2, 'tfoot', '', 'mode');
	print_table_break();
	print_label_row(
		'<b>' . $vbphrase['custom_calendar_permissions'] . '</b>','
		<input type="button" value="' . $vbphrase['all_yes'] . '" onclick="if (js_set_custom()) { js_check_all_option(this.form, 1); }" class="button" />
		<input type="button" value=" ' . $vbphrase['all_no'] . ' " onclick="if (js_set_custom()) { js_check_all_option(this.form, 0); }" class="button" />
	', 'tcat', 'middle');

	print_yes_no_row($vbphrase['can_view_calendar'], 'calendarpermission[canviewcalendar]', $calendarpermission['canviewcalendar'], 'js_set_custom();');
	print_yes_no_row($vbphrase['can_post_events'], 'calendarpermission[canpostevent]', $calendarpermission['canpostevent'], 'js_set_custom();');
	print_yes_no_row($vbphrase['can_edit_own_events'], 'calendarpermission[caneditevent]', $calendarpermission['caneditevent'], 'js_set_custom();');
	print_yes_no_row($vbphrase['can_delete_own_events'], 'calendarpermission[candeleteevent]', $calendarpermission['candeleteevent'], 'js_set_custom();');
	print_yes_no_row($vbphrase['can_view_others_events'], 'calendarpermission[canviewothersevent]', $calendarpermission['canviewothersevent'], 'js_set_custom();');

	print_submit_row($vbphrase['save']);

}

// ###################### Start do update #######################
if ($_POST['do'] == 'doupdate')
{
	globalize($_POST, array(
		'calendarid' => INT,
		'calendarpermissionid' => INT,
		'useusergroup' => INT,
		'calendarpermission'
	));

	// note: $getforum is called to get a forumid to jump to on the target page...
	$infoquery = "
		SELECT calendar.calendarid,calendar.title AS calendartitle,usergroup.title AS grouptitle
		FROM " . TABLE_PREFIX . "calendarpermission AS calendarpermission
		INNER JOIN " . TABLE_PREFIX . "calendar AS calendar ON (calendar.calendarid = calendarpermission.calendarid)
		INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = calendarpermission.usergroupid)
		WHERE calendarpermissionid = $calendarpermissionid
	";

	define('CP_REDIRECT', "calendarpermission.php?do=modify#calendar$info[calendarid]");
	if ($useusergroup)
	{
		// use usergroup defaults. delete calendarpermission if it exists
		if ($calendarpermissionid)
		{
			$info = $DB_site->query_first($infoquery);
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "calendarpermission WHERE calendarpermissionid = $calendarpermissionid");

			print_stop_message('deleted_calendar_permissions_successfully');
		}
		else
		{
			print_stop_message('invalid_calendar_permissions_specified');
		}
	}
	else
	{
		require_once('./includes/functions_misc.php');
		$calendarpermission['calendarpermissions'] = convert_array_to_bits($calendarpermission, $_BITFIELD['usergroup']['calendarpermissions'], 1);

		if ($calendarid)
		{
			$calendarpermission['calendarid'] = $calendarid;
			$query = fetch_query_sql($calendarpermission, 'calendarpermission');
			$DB_site->query($query);
			$info['calendarid'] = $calendarid;
			$calendarinfo = $DB_site->query_first("SELECT title AS calendartitle FROM " . TABLE_PREFIX . "calendar WHERE calendarid=$calendarid");
			$groupinfo = $usergroupcache["$calendarpermission[usergroupid]"];

			print_stop_message('saved_calendar_permissions_successfully');
		}
		else
		{
			$query = fetch_query_sql($calendarpermission, 'calendarpermission' ,"WHERE calendarpermissionid = $calendarpermissionid");
			$DB_site->query($query);
			$info = $DB_site->query_first($infoquery);

			print_stop_message('saved_calendar_permissions_successfully');
		}
	}
}

// ###################### Start fpgetstyle #######################
function fetch_forumpermission_style($color = '', $canview)
{
	if ($canview == 0)
	{
		if ($canview == 0)
		{
			$canview = 'list-style-type:circle;';
		}
		else
		{
			$canview = '';
		}
		return " style=\"$color$canview\"";
	}
	else
	{
		return '';
	}
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{

	print_form_header('', '');
	print_table_header($vbphrase['calendar_permissions']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset">	<ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="col-g">' . $vbphrase['standard_using_default_usergroup_permissions'] . '</li>
		<li class="col-c">' . $vbphrase['customized_using_custom_permissions_for_this_usergroup'] . '</li>
		</ul></div>
	');

	print_table_footer();

	// Calendar cache, will move to function...
	$calendars = $DB_site->query("SELECT calendarid, title FROM " . TABLE_PREFIX . "calendar ORDER BY displayorder");
	$calendarcache = array();
	while ($calendar = $DB_site->fetch_array($calendars))
	{
		$calendarcache["$calendar[calendarid]"] = $calendar['title'];
	}
	unset($calendar);
	$DB_site->free_result($calendars);

	// query forum permissions
	$calendarpermissions = $DB_site->query("
		SELECT usergroupid, calendar.calendarid, calendarpermissions,
		NOT (ISNULL(calendarpermission.calendarid)) AS hasdata, calendarpermissionid
		FROM " . TABLE_PREFIX . "calendar AS calendar
		LEFT JOIN " . TABLE_PREFIX . "calendarpermission AS calendarpermission ON (calendarpermission.calendarid = calendar.calendarid)
	");

	$permscache = array();
	while ($cperm = $DB_site->fetch_array($calendarpermissions))
	{
		if ($cperm['hasdata'])
		{
			$temp = array();
			$temp['calendarpermissionid'] = $cperm['calendarpermissionid'];
			$temp['calendarpermissions'] = $cperm['calendarpermissions'];
			$permscache[$cperm['calendarid']][$cperm['usergroupid']] = $temp;
		}
	}

	echo '<center><div class="tborder" style="width: 89%">';
	echo '<div class="alt1" style="padding: 8px">';
	echo '<div class="darkbg" style="padding: 4px; border: 2px inset; text-align: ' . $stylevar['left'] . '">';

	$ident = '   ';
	echo "$indent<ul class=\"lsq\">\n";
	foreach ($calendarcache AS $calendarid => $title)
	{
		// forum title and links
		echo "$indent<li><b><a name=\"calendar$calendarid\" href=\"admincalendar.php?$session[sessionurl]do=edit&amp;calendarid=$calendarid\">$title</a></b>";
		echo "$indent\t<ul class=\"usergroups\">\n";
		foreach ($usergroupcache AS $usergroupid => $usergroup)
		{
			$fp = $permscache[$calendarid][$usergroupid];
			if (is_array($fp))
			{
				$fp['class'] = ' class="col-c"';
				$fp['link'] = "calendarpermissionid=$fp[calendarpermissionid]";
			}
			else

			{
				$fp['class'] = '';
				$fp['link'] = "calendarid=$calendarid&amp;usergroupid=$usergroupid";
			}
			echo "$indent\t<li$fp[class]" . iif($fp['calendarpermissions'] & CANVIEW, '', ' style="list-style-type:circle;"') . '>' . construct_link_code($vbphrase['edit'], "calendarpermission.php?$session[sessionurl]do=edit&amp;$fp[link]") . $usergroup['title'] . "</li>\n";
			unset($permscache[$forumid][$usergroupid]);
		}
		echo "$indent\t</ul><br />\n";
		echo "$indent</li>\n";
	}
	echo "$indent</ul>\n";

	echo "</div></div></div></center>";

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9093 $
|| ####################################################################
\*======================================================================*/
?>