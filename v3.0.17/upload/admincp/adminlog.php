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
define('CVS_REVISION', '$RCSfile$ - $Revision: 12669 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('logging');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// ###################### Start listLogFiles #######################
// function to return an array of log files in the filesystem
function fetch_log_file_array($type = 'database')
{
	global $vboptions, $vbphrase;

	$filelist = array();

	switch($type)
	{
		case 'database':
			$option = 'errorlogdatabase';
			$title = $vbphrase['vbulletin_database_errors'];
			break;

		case 'security':
			$option = 'errorlogsecurity';
			$title = $vbphrase['admin_control_panel_failed_logins'];
			break;

		default:
			return $filelist;
	}

	if ($filebase = trim($vboptions["$option"]))
	{
		$slashpos = strrpos($filebase, '/');
		if ($slashpos === false)
		{
			$basedir = '.';
		}
		else
		{
			$basedir = substr($filebase, 0, $slashpos);
		}
		if ($handle = @opendir($basedir))
		{
			$filebase = substr($filebase, $slashpos + 1);
			$namelength = strlen($filebase);
			while ($file = readdir($handle))
			{
				if (strpos($file, $filebase) === 0)
				{
					if ($unixdate = intval(substr($file, $namelength, -4)))
					{
						$date = vbdate("$vboptions[dateformat] $vboptions[timeformat]", $unixdate);
					}
					else
					{
						$date = '(Current Version)';
					}
					$key = $type . '_' . $unixdate;
					$filelist["$key"] = "$title $date";
				}
			}
			@closedir($handle);
			return $filelist;
		}
		else
		{
			echo '<p>' . $vbphrase['invalid_directory_specified'] . '</p>';
		}
	}
	else
	{
		return false;
	}
}

// #############################################################################
if ($_POST['do'] == 'viewlogfile' AND can_access_logs($canviewadminlog, 1, '<p>' . $vbphrase['control_panel_log_viewing_restricted'] .'</p>'))
{
	globalize($_REQUEST, array('filename' => STR, 'delete' => STR));

	$filebits = explode('_', $filename);
	$type = trim($filebits[0]);
	$date = intval($filebits[1]);

	switch($type)
	{
		case 'database':
		case 'security':
		{
			if ($filename = trim($vboptions["errorlog$type"]))
			{
				$filename = $filename . iif($date, $date) . '.log';
				if (file_exists($filename))
				{
					if ($delete)
					{
						if (can_access_logs($canpruneadminlog, 0, '<p>' . $vbphrase['log_file_deletion_restricted'] . '</p>'))
						{
							if (@unlink($filename))
							{
								print_stop_message('deleted_file_successfully');
							}
							else
							{
								print_stop_message('unable_to_delete_file');
							}
						}
					}
					else
					{
						require_once('./includes/functions_file.php');
						file_download(implode('', file($filename)), substr($filename, strrpos($filename, '/') + 1), 'baa');
					}
				}
				else
				{
					print_stop_message('invalid_file_specified');
				}
			}
		}
	}

	$_REQUEST['do'] = 'logfiles';

}

// #############################################################################
print_cp_header($vbphrase['control_panel_log']);
// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'choose';
}

// ###################### Start view db error log #######################
if ($_REQUEST['do'] == 'logfiles' AND can_access_logs($canviewadminlog, 1, '<p>' . $vbphrase['control_panel_log_viewing_restricted'] . '</p>'))
{
	// get database and security log files list
	$dblogs = fetch_log_file_array('database');
	$cplogs = fetch_log_file_array('security');

	if ($dblogs === false AND $cplogs === false)
	{
		print_stop_message('no_log_file_defined_in_vbulletin_options');
	}

	if ($dblogs)
	{
		$dblogs = '<optgroup label="vBulletin Database Errors">' . construct_select_options($dblogs) . '</optgroup>';
	}
	if ($cplogs)
	{
		$cplogs = '<optgroup label="Admin Control Panel Failed Logins">' . construct_select_options($cplogs) . '</optgroup>';
	}

	print_form_header('adminlog', 'viewlogfile');
	print_table_header($vbphrase['view_logs']);
	print_label_row($vbphrase['logs'], '<select name="filename" size="15" tabindex="1" class="bginput">' . $dblogs . $cplogs  . '<option value="">' . str_repeat('&nbsp; ', 40) . '</option></select>');
	print_table_footer(2, '<input type="submit" class="button" value=" ' . $vbphrase['view'] . ' " accesskey="s" tabindex="1" /> <input type="submit" class="button" value=" ' . $vbphrase['delete'] . ' " name="delete" tabindex="1" />');
}

// ###################### Start view #######################
if ($_REQUEST['do'] == 'view' AND can_access_logs($canviewadminlog, 1, '<p>' . $vbphrase['control_panel_log_viewing_restricted'] . '</p>'))
{
	globalize($_REQUEST, array(
		'userid' => INT,
		'script' => STR,
		'perpage' => INT,
		'page' => INT,
		'orderby' => STR
	));

	if ($userid OR $script)
	{
		$sqlconds = 'WHERE 1=1 ';
		if ($userid)
		{
			$sqlconds .= "AND adminlog.userid = $userid ";
		}
		if ($script)
		{
			$sqlconds .= 'AND adminlog.script = \'' . addslashes($script) . '\' ';
		}
	}
	else
	{
		$sqlconds = '';
	}

	if ($perpage < 1)
	{
		$perpage = 15;
	}
	if ($page < 1)
	{
		$page = 1;
	}
	$startat = ($page - 1) * $perpage;

	$counter = $DB_site->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "adminlog AS adminlog $sqlconds");
	$totalpages = ceil($counter['total'] / $perpage);

	switch($orderby)
	{
		case 'user':
			$order = 'username ASC,adminlogid DESC';
			break;
		case 'date':
			$order = 'adminlogid DESC';
			break;
		case 'script':
			$order = 'script ASC,adminlogid DESC';
			break;
		default:
			$orderby = '';
			$order = 'adminlogid DESC';
	}

	$logs = $DB_site->query("
		SELECT adminlog.*,user.username
		FROM " . TABLE_PREFIX . "adminlog AS adminlog
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		$sqlconds
		ORDER BY $order
		LIMIT $startat, $perpage
	");

	if ($DB_site->num_rows($logs))
	{

		if ($page != 1)
		{
			$prv = $page - 1;
			$firstpage = "<input type=\"button\" class=\"button\" value=\"&laquo; " . $vbphrase['first_page'] . "\" tabindex=\"1\" onclick=\"window.location='adminlog.php?$session[sessionurl]do=view&script=$script&userid=$userid&perpage=$perpage&orderby=$orderby&page=1'\">";
			$prevpage = "<input type=\"button\" class=\"button\" value=\"&lt; " . $vbphrase['prev_page'] . "\" tabindex=\"1\" onclick=\"window.location='adminlog.php?$session[sessionurl]do=view&script=$script&userid=$userid&perpage=$perpage&orderby=$orderby&page=$prv'\">";
		}

		if ($page != $totalpages)
		{
			$nxt = $page + 1;
			$nextpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['next_page'] . " &gt;\" tabindex=\"1\" onclick=\"window.location='adminlog.php?$session[sessionurl]do=view&script=$script&userid=$userid&perpage=$perpage&orderby=$orderby&page=$nxt'\">";
			$lastpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['last_page'] . " &raquo;\" tabindex=\"1\" onclick=\"window.location='adminlog.php?$session[sessionurl]do=view&script=$script&userid=$userid&perpage=$perpage&orderby=$orderby&page=$totalpages'\">";
		}

		print_form_header('adminlog', 'remove');
		print_description_row(construct_link_code($vbphrase['restart'], "adminlog.php?$session[sessionurl]"), 0, 7, 'thead', $stylevar['right']);
		print_table_header(construct_phrase($vbphrase['control_panel_log_viewer_page_x_y_there_are_z_total_log_entries'], $page, vb_number_format($totalpages), vb_number_format($counter['total'])), 7);

		$headings = array();
		$headings[] = $vbphrase['id'];
		$headings[] = "<a href='adminlog.php?$session[sessionurl]do=view&script=$script&userid=$userid&perpage=$perpage&orderby=user&page=$page' title='" . $vbphrase['order_by_username'] . "'>" . $vbphrase['username'] . "</a>";
		$headings[] = "<a href='adminlog.php?$session[sessionurl]do=view&script=$script&userid=$userid&perpage=$perpage&orderby=date&page=$page' title='" . $vbphrase['order_by_date'] . "'>" . $vbphrase['date'] . "</a>";
		$headings[] = "<a href='adminlog.php?$session[sessionurl]do=view&script=$script&userid=$userid&perpage=$perpage&orderby=script&page=$page' title='" . $vbphrase['order_by_script'] . "'>" . $vbphrase['script'] . "</a>";
		$headings[] = $vbphrase['action'];
		$headings[] = $vbphrase['info'];
		$headings[] = $vbphrase['ip_address'];
		print_cells_row($headings, 1);

		while ($log = $DB_site->fetch_array($logs))
		{
			$cell = array();
			$cell[] = $log['adminlogid'];
			$cell[] = iif(!empty($log['username']), "<a href=\"user.php?$session[sessionurl]do=edit&userid=$log[userid]\"><b>$log[username]</b></a>", $vbphrase['n_a']);
			$cell[] = '<span class="smallfont">' . vbdate($vboptions['logdateformat'], $log['dateline']) . '</span>';
			$cell[] = $log['script'];
			$cell[] = $log['action'];
			$cell[] = $log['extrainfo'];
			$cell[] = '<span class="smallfont">' . iif($log['ipaddress'], "<a href=\"usertools.php?$session[sessionurl]do=gethost&ip=$log[ipaddress]\">$log[ipaddress]</a>", '&nbsp;') . '</span>';
			print_cells_row($cell);
		}

		print_table_footer(7, "$firstpage $prevpage &nbsp; $nextpage $lastpage");

	}
	else
	{
		print_stop_message('no_log_entries_matched_your_query');
	}
}

// ###################### Start prune log #######################
if ($_REQUEST['do'] == 'prunelog' AND can_access_logs($canpruneadminlog, 0, '<p>' . $vbphrase['control_panel_log_pruning_permission_restricted'] . '</p>'))
{
	globalize($_REQUEST, array(
		'userid' => INT,
		'script' => STR,
		'daysprune' => INT,
	));

	$datecut = TIMENOW - (86400 * $daysprune);
	$query = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "adminlog AS adminlog WHERE dateline < $datecut";

	if ($userid)
	{
		$query .= "\nAND userid = $userid";
	}
	if ($script != '')
	{
		$query .= "\nAND script = '" . addslashes($script) . "'";
	}

	$logs = $DB_site->query_first($query);
	if ($logs['total'])
	{
		print_form_header('adminlog', 'doprunelog');
		construct_hidden_code('datecut', $datecut);
		construct_hidden_code('script', $script);
		construct_hidden_code('userid', $userid);
		print_table_header($vbphrase['prune_control_panel_log']);
		print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_prune_x_log_entries_from_control_panel_log'], vb_number_format($logs['total'])));
		print_submit_row($vbphrase['yes'], 0, 0, $vbphrase['no']);
	}
	else
	{
		print_stop_message('no_log_entries_matched_your_query');
	}
}

// ###################### Start do prune log #######################
if ($_POST['do'] == 'doprunelog' AND can_access_logs($canpruneadminlog, 0, '<p>' . $vbphrase['control_panel_log_pruning_permission_restricted'] . '</p>'))
{
	globalize($_POST, array(
		'userid' => INT,
		'script' => STR,
		'datecut' => INT
	));

	$query = "DELETE FROM " . TABLE_PREFIX . "adminlog WHERE dateline < $datecut";
	if ($script)
	{
		$query .= "\nAND script='" . addslashes($script) . "'";
	}
	if ($userid)
	{
		$query .= "\nAND userid = $userid";
	}

	$DB_site->query($query);

	define('CP_REDIRECT', 'adminlog.php?do=choose');
	print_stop_message('pruned_control_panel_log_successfully');
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'choose')
{

	if (can_access_logs($canviewadminlog, 1))
	{
		$show_admin_log = true;
	}
	else
	{
		echo '<p>' . $vbphrase['control_panel_log_viewing_restricted'] . '</p>';
	}

	if ($show_admin_log)
	{
		log_admin_action();

		$files = $DB_site->query("
			SELECT DISTINCT adminlog.script
			FROM " . TABLE_PREFIX . "adminlog AS adminlog
			ORDER BY script
		");
		$filelist = array('no_value' => $vbphrase['all_scripts']);
		while ($file = $DB_site->fetch_array($files))
		{
			$filelist["$file[script]"] = $file['script'];
		}

		$users = $DB_site->query("
			SELECT DISTINCT adminlog.userid, user.username
			FROM " . TABLE_PREFIX . "adminlog AS adminlog
			LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
			ORDER BY username
		");
		$userlist = array('no_value' => $vbphrase['all_users']);
		while ($user = $DB_site->fetch_array($users))
		{
			$userlist["$user[userid]"] = $user['username'];
		}

		$perpage_options = array(
			5 => 5,
			10 => 10,
			15 => 15,
			20 => 20,
			25 => 25,
			30 => 30,
			40 => 40,
			50 => 50,
			100 => 100,
			'15.1' => '------------------------------'
		);

		print_form_header('adminlog', 'view');
		print_table_header($vbphrase['control_panel_log_viewer']);
		print_select_row($vbphrase['log_entries_to_show_per_page'], 'perpage', $perpage_options, 15);
		print_select_row($vbphrase['show_only_entries_relating_to_script'], 'script', $filelist);
		print_select_row($vbphrase['show_only_entries_generated_by'], 'userid', $userlist);
		print_select_row($vbphrase['order_by'], 'orderby', array('date' => $vbphrase['date'], 'user' => $vbphrase['user'], 'script' => $vbphrase['script']), 'date');
		print_submit_row($vbphrase['view'], 0);

		if (can_access_logs($canpruneadminlog, 1))
		{
			print_form_header('adminlog', 'prunelog');
			print_table_header($vbphrase['prune_control_panel_log']);
			print_label_row($vbphrase['remove_entries_relating_to_script'], '<select name="script" tabindex="1" class="bginput">' . construct_select_options($filelist) . '</select>', '', 'top', 'pscript');
			print_label_row($vbphrase['remove_entries_logged_by_user'], '<select name="userid" tabindex="1" class="bginput">' . construct_select_options($userlist) . '</select>', '', 'top', 'puserid');
			print_input_row($vbphrase['remove_entries_older_than_days'], 'daysprune', 30);
			print_submit_row($vbphrase['prune_control_panel_log'], 0);
		}
		else
		{
			echo '<p>' . $vbphrase['control_panel_log_pruning_permission_restricted'] . '</p>';
		}
	}
}

echo '<p class="smallfont" align="center"><a href="javascript:js_open_help(\'adminlog\', \'restrict\', \'\');">' . $vbphrase['want_to_access_grant_access_to_this_script'] . '</a></p>';

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12669 $
|| ####################################################################
\*======================================================================*/
?>