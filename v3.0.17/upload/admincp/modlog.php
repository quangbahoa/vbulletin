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
define('CVS_REVISION', '$RCSfile$ - $Revision: 13174 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('logging');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['moderator_log']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'choose';
}

// ###################### Start view #######################
if ($_REQUEST['do'] == 'view')
{
	globalize($_REQUEST, array(
		'perpage' => INT,
		'page' => INT,
		'userid' => INT,
		'modaction' => STR_NOHTML,
		'orderby' => STR_NOHTML,
	));

	if ($perpage < 1)
	{
		$perpage = 15;
	}

	if ($userid OR $modaction)
	{
		$sqlconds = 'WHERE 1=1 ';
		if ($userid)
		{
			$sqlconds .= "AND moderatorlog.userid = $userid ";
		}
		if ($modaction)
		{
			$sqlconds .= 'AND moderatorlog.action LIKE "%' . addslashes_like($modaction) . '%" ';
		}
	}
	else
	{
		$sqlconds = '';
	}

	$counter = $DB_site->query_first("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "moderatorlog AS moderatorlog
		$sqlconds
	");
	$totalpages = ceil($counter['total'] / $perpage);

	if ($page < 1)
	{
		$page = 1;
	}
	$startat = ($page - 1) * $perpage;

	switch($orderby)
	{
		case 'user':
			$order = 'username ASC, dateline DESC';
			break;
		case 'modaction':
			$order = 'action ASC, dateline DESC';
			break;
		case 'date':
		default:
			$order = 'dateline DESC';
	}

	$logs = $DB_site->query("
		SELECT moderatorlog.*, user.username,
		post.title AS post_title, forum.title AS forum_title, thread.title AS thread_title, poll.question AS poll_question
		FROM " . TABLE_PREFIX . "moderatorlog AS moderatorlog
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderatorlog.userid)
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = moderatorlog.postid)
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = moderatorlog.forumid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = moderatorlog.threadid)
		LEFT JOIN " . TABLE_PREFIX . "poll AS poll ON (poll.pollid = moderatorlog.pollid)
		$sqlconds
		ORDER BY $order
		LIMIT $startat, $perpage
	");

	if ($DB_site->num_rows($logs))
	{

		if ($page != 1)
		{
			$prv = $page - 1;
			$firstpage = "<input type=\"button\" class=\"button\" value=\"&laquo; " . $vbphrase['first_page'] . "\" tabindex=\"1\" onclick=\"window.location='modlog.php?$session[sessionurl]do=view&modaction=$modaction&userid=$userid&perpage=$perpage&orderby=$orderby&page=1'\">";
			$prevpage = "<input type=\"button\" class=\"button\" value=\"&lt; " . $vbphrase['prev_page'] . "\" tabindex=\"1\" onclick=\"window.location='modlog.php?$session[sessionurl]do=view&modaction=$modaction&userid=$userid&perpage=$perpage&orderby=$orderby&page=$prv'\">";
		}

		if ($page != $totalpages)
		{
			$nxt = $page + 1;
			$nextpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['next_page'] . " &gt;\" tabindex=\"1\" onclick=\"window.location='modlog.php?$session[sessionurl]do=view&modaction=$modaction&userid=$userid&perpage=$perpage&orderby=$orderby&page=$nxt'\">";
			$lastpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['last_page'] . " &raquo;\" tabindex=\"1\" onclick=\"window.location='modlog.php?$session[sessionurl]do=view&modaction=$modaction&userid=$userid&perpage=$perpage&orderby=$orderby&page=$totalpages'\">";
		}

		print_form_header('modlog', 'remove');
		print_description_row(construct_link_code($vbphrase['restart'], "modlog.php?$session[sessionurl]"), 0, 5, 'thead', $stylevar['right']);
		print_table_header(construct_phrase($vbphrase['moderator_log_viewer_page_x_y_there_are_z_total_log_entries'], $page, vb_number_format($totalpages), vb_number_format($counter['total'])), 5);

		$headings = array();
		$headings[] = $vbphrase['id'];
		$headings[] = "<a href=\"modlog.php?$session[sessionurl]do=view&modaction=$modaction&userid=$userid&perpage=$perpage&orderby=user&page=$page\">".$vbphrase['username']."</a>";
		$headings[] = "<a href=\"modlog.php?$session[sessionurl]do=view&modaction=$modaction&userid=$userid&perpage=$perpage&orderby=date&page=$page\">".$vbphrase['date']."</a>";
		$headings[] = "<a href=\"modlog.php?$session[sessionurl]do=view&modaction=$modaction&userid=$userid&perpage=$perpage&orderby=modaction&page=$page\">".$vbphrase['action']."</a>";
		$headings[] = $vbphrase['info'];
		print_cells_row($headings, 1);

		$princids = array(
			'poll_question' => $vbphrase['question'],
			'post_title' => $vbphrase['post'],
			'thread_title' => $vbphrase['thread'],
			'forum_title' => $vbphrase['forum']
		);

		while ($log = $DB_site->fetch_array($logs))
		{
			$cell = array();
			$cell[] = $log['moderatorlogid'];
			$cell[] = "<a href=\"user.php?$session[sessionurl]do=edit&userid=$log[userid]\"><b>$log[username]</b></a>";
			$cell[] = '<span class="smallfont">' . vbdate($vboptions['logdateformat'], $log['dateline']) . '</span>';
			$cell[] = $log['action'];

			$celldata = '';
			reset($princids);
			foreach ($princids AS $sqlfield => $output)
			{
				if ($log[$sqlfield])
				{
					if ($celldata)
					{
						$celldata .= "<br />\n";
					}
					$celldata .= "<b>$output:</b> " . $log[$sqlfield];
				}
			}

			$cell[] = $celldata;
			print_cells_row($cell, 0, 0, -4);
		}

		print_table_footer(5, "$firstpage $prevpage &nbsp; $nextpage $lastpage");
	}
	else
	{
		print_stop_message('no_results_matched_your_query');
	}
}

// ###################### Start prune log #######################
if ($_REQUEST['do'] == 'prunelog'AND can_access_logs($canpruneadminlog, 0, '<p>' . $vbphrase['control_panel_log_pruning_permission_restricted'] . '</p>'))
{
	globalize($_REQUEST, array(
		'daysprune' => INT,
		'userid' => INT,
		'modaction' => STR
	));

	$datecut = TIMENOW - (86400 * $daysprune);
	$query = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "moderatorlog WHERE dateline < $datecut";

	if ($userid)
	{
		$query .= "\nAND userid = $userid";
	}
	if ($modaction)
	{
		$query .= "\nAND action LIKE '%" . addslashes_like($modaction) . "%'";
	}

	$logs = $DB_site->query_first($query);
	if ($logs['total'])
	{
		print_form_header('modlog', 'doprunelog');
		construct_hidden_code('datecut', $datecut);
		construct_hidden_code('modaction', $modaction);
		construct_hidden_code('userid', $userid);
		print_table_header($vbphrase['prune_moderator_log']);
		print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_prune_x_log_entries_from_moderator_log'], vb_number_format($logs['total'])));
		print_submit_row($vbphrase['yes'], 0, 0, $vbphrase['no']);
	}
	else
	{
		print_stop_message('no_logs_matched_your_query');
	}

}

// ###################### Start do prune log #######################
if ($_POST['do'] == 'doprunelog' AND can_access_logs($canpruneadminlog, 0, '<p>' . $vbphrase['control_panel_log_pruning_permission_restricted'] . '</p>'))
{
	globalize($_POST, array(
		'datecut' => INT,
		'modaction' => STR,
		'userid' => INT,
	));

	$sqlconds = ' ';
	if (!empty($modaction))
	{
		$sqlconds .= "AND action LIKE '%" . addslashes_like($modaction) . "%'";
	}
	if (!empty($userid))
	{
		$sqlconds .= " AND userid = $userid";
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "moderatorlog WHERE dateline < $datecut $sqlconds");

	define('CP_REDIRECT', 'modlog.php?do=choose');
	print_stop_message('pruned_moderator_log_successfully');
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'choose')
{
	$users = $DB_site->query("
		SELECT DISTINCT moderatorlog.userid, user.username
		FROM " . TABLE_PREFIX . "moderatorlog AS moderatorlog
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		ORDER BY username
	");
	$userlist = array('no_value' => $vbphrase['all_log_entries']);
	while ($user = $DB_site->fetch_array($users))
	{
		$userlist["$user[userid]"] = $user['username'];
	}

	print_form_header('modlog', 'view');
	print_table_header($vbphrase['moderator_log_viewer']);
	print_input_row($vbphrase['log_entries_to_show_per_page'], 'perpage', 15);
	print_select_row($vbphrase['show_only_entries_generated_by'], 'userid', $userlist);
	print_select_row($vbphrase['order_by'], 'orderby', array('date' => $vbphrase['date'], 'user' => $vbphrase['username'], 'modaction' => $vbphrase['action']), 'date');
	print_submit_row($vbphrase['view'], 0);

	if (can_access_logs($canpruneadminlog, 0, ''))
	{
		print_form_header('modlog', 'prunelog');
		print_table_header($vbphrase['prune_moderator_log']);
		print_select_row($vbphrase['remove_entries_logged_by_user'], 'userid', $userlist);
		print_input_row($vbphrase['remove_entries_older_than_days'], 'daysprune', 30);
		print_submit_row($vbphrase['prune_log_entries'], 0);
	}

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13174 $
|| ####################################################################
\*======================================================================*/
?>