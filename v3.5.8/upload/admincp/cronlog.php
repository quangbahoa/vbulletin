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
define('CVS_REVISION', '$RCSfile$ - $Revision: 13048 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('logging');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadmincron'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['scheduled_task_log']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'choose';
}

// ###################### Start view #######################
if ($_REQUEST['do'] == 'view')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'	=> TYPE_INT,
		'cronid'	=> TYPE_INT,
		'orderby'	=> TYPE_STR,
		'page'		=> TYPE_INT
	));

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 15;
	}

	if (!empty($vbulletin->GPC['cronid']))
	{
		$sqlconds = 'WHERE cronlog.cronid = ' . $vbulletin->GPC['cronid'];
	}

	$counter = $db->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "cronlog AS cronlog $sqlconds");
	$totalpages = ceil($counter['total'] / $vbulletin->GPC['perpage']);

	if (empty($vbulletin->GPC['page']))
	{
		$vbulletin->GPC['page'] = 1;
	}

	$startat = ($vbulletin->GPC['page'] - 1) * $vbulletin->GPC['perpage'];

	switch($vbulletin->GPC['orderby'])
	{
		case 'action':
			$order = 'cronid ASC, dateline DESC';
			break;
		case 'date':
		default:
			$order = 'dateline DESC';
	}

	$logs = $db->query_read("
		SELECT cronlog.*, cron.title
		FROM " . TABLE_PREFIX . "cronlog AS cronlog
		LEFT JOIN " . TABLE_PREFIX . "cron AS cron ON (cronlog.cronid = cron.cronid)
		$sqlconds
		ORDER BY $order
		LIMIT $startat, " . $vbulletin->GPC['perpage']
	);

	if ($db->num_rows($logs))
	{
		if ($vbulletin->GPC['page'] != 1)
		{
			$prv = $vbulletin->GPC['page'] - 1;
			$firstpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"&laquo; " . $vbphrase['first_page'] . "\" onclick=\"window.location='cronlog.php?" . $vbulletin->session->vars['sessionurl'] . "do=view" .
				"&cronid=" . $vbulletin->GPC['cronid'] .
				"&pp=" . $vbulletin->GPC['perpage'] .
				"&orderby=" . $vbulletin->GPC['orderby'] . "&page=1'\">";
			$prevpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"&lt; " . $vbphrase['prev_page'] . "\" onclick=\"window.location='cronlog.php?" . $vbulletin->session->vars['sessionurl'] . "do=view" .
				"&cronid=" . $vbulletin->GPC['cronid'] .
				"&pp=" . $vbulletin->GPC['perpage'] .
				"&orderby=" . $vbulletin->GPC['orderby'] . "&page=$prv'\">";
		}

		if ($vbulletin->GPC['page'] != $totalpages)
		{
			$nxt = $vbulletin->GPC['page'] + 1;
			$page_button = "cronlog.php?" . $vbulletin->session->vars['sessionurl'] . "do=view&cronid=" . $vbulletin->GPC['cronid'] . "&pp=" . $vbulletin->GPC['perpage'] . "&orderby=" . $vbulletin->GPC['orderby'];
			$nextpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"" . $vbphrase['next_page'] . " &gt;\" onclick=\"window.location='$page_button&page=$nxt'\">";
			$lastpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"" . $vbphrase['last_page'] . " &raquo;\" onclick=\"window.location='$page_button&page=$totalpages'\">";
		}

		print_form_header('cronlog', 'remove');
		print_description_row(construct_link_code($vbphrase['restart'], "cronlog.php?" . $vbulletin->session->vars['sessionurl'] . ""), 0, 4, 'thead', $stylevar['right']);
		print_table_header(construct_phrase($vbphrase['scheduled_task_log_viewer_page_x_y_there_are_z_total_log_entries'], vb_number_format($vbulletin->GPC['page']), vb_number_format($totalpages), vb_number_format($counter['total'])), 4);

		$headings = array();
		$headings[] = $vbphrase['id'];
		$headings[] = "<a href=\"cronlog.php?" . $vbulletin->session->vars['sessionurl'] . "do=view" .
			"&cronid=" . $vbulletin->GPC['cronid'] .
			"&pp=" . $vbulletin->GPC['perpage'] .
			"&orderby=action" .
			"&page=" . $vbulletin->GPC['page'] . "\" title=\"" . $vbphrase['order_by_action'] . "\">" . $vbphrase['action'] . "</a>";
		$headings[] = "<a href=\"cronlog.php?" . $vbulletin->session->vars['sessionurl'] . "do=view" .
			"&cronid=" . $vbulletin->GPC['cronid'] .
			"&pp=" . $vbulletin->GPC['perpage'] .
			"&orderby=date" .
			"&page=" . $vbulletin->GPC['page'] . "\" title=\"" . $vbphrase['order_by_date'] . "\">" . $vbphrase['date'] . "</a>";
		$headings[] = $vbphrase['info'];
		print_cells_row($headings, 1);

		while ($log = $db->fetch_array($logs))
		{
			$cell = array();
			$cell[] = $log['cronlogid'];
			$cell[] = $log['title'];
			$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['logdateformat'], $log['dateline']) . '</span>';
			$cell[] = $log['description'];

			print_cells_row($cell, 0, 0, -4);
		}

		print_table_footer(4, "$firstpage $prevpage &nbsp; $nextpage $lastpage");
	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

// ###################### Start prune log #######################
if ($_POST['do'] == 'prunelog')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'cronid' 	=> TYPE_INT,
		'daysprune' => TYPE_INT
	));

	$sqlconds = '';
	if ($vbulletin->GPC['cronid'])
	{
		$sqlconds = "\nAND cronid = " . $vbulletin->GPC['cronid'];
	}

	$datecut = TIMENOW - (86400 * $vbulletin->GPC['daysprune']);

	$logs = $db->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "cronlog WHERE dateline < $datecut $sqlconds");

	if ($logs['total'])
	{
		print_form_header('cronlog', 'doprunelog');
		construct_hidden_code('datecut', $datecut);
		construct_hidden_code('cronid', $vbulletin->GPC['cronid']);
		print_table_header($vbphrase['prune_scheduled_task_log']);
		print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_prune_x_log_entries_from_scheduled_task_log'], vb_number_format($logs['total'])));
		print_submit_row($vbphrase['yes'], 0, 0, $vbphrase['no']);
	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

// ###################### Start do prune log #######################
if ($_POST['do'] == 'doprunelog')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'cronid'  => TYPE_INT,
		'datecut' => TYPE_INT
	));

	$sqlconds = '';
	if (!empty($vbulletin->GPC['cronid']))
	{
		$sqlconds = "\nAND cronid = " . $vbulletin->GPC['cronid'];
	}

	$db->query_write("DELETE FROM " . TABLE_PREFIX . "cronlog WHERE dateline < " . $vbulletin->GPC['datecut'] . " $sqlconds");

	define('CP_REDIRECT', 'cronlog.php?do=choose');
	print_stop_message('pruned_scheduled_task_log_successfully');
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'choose')
{
	$cronjobs = $db->query_read("
		SELECT title, cronid
		FROM " . TABLE_PREFIX . "cron
	");
	$filelist = array();
	$filelist[0] = $vbphrase['all_scheduled_tasks'];
	while ($file = $db->fetch_array($cronjobs))
	{
		$filelist[$file['cronid']] = $file['title'];
	}

	$perpage = array(5 => 5, 10 => 10, 15 => 15, 20 => 20, 25 => 25, 30 => 30, 40 => 40, 50 => 50, 100 => 100);
	$orderby = array('date' => $vbphrase['date'], 'action' => $vbphrase['action']);

	print_form_header('cronlog', 'view');
	print_table_header($vbphrase['scheduled_task_log_viewer']);

	print_select_row($vbphrase['log_entries_to_show_per_page'], 'perpage', $perpage, 15);
	print_select_row($vbphrase['show_only_entries_generated_by'], 'cronid', $filelist);
	print_select_row($vbphrase['order_by'], 'orderby', $orderby);

	print_submit_row($vbphrase['view'], 0);

	print_form_header('cronlog', 'prunelog');
	print_table_header($vbphrase['prune_scheduled_task_log']);
	print_select_row($vbphrase['remove_entries_related_to_action'], 'cronid', $filelist);
	print_input_row($vbphrase['remove_entries_older_than_days'], 'daysprune', 30);
	print_submit_row($vbphrase['prune'], 0);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13048 $
|| ####################################################################
\*======================================================================*/
?>
