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
$phrasegroups = array('reputation', 'user');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_reputation.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['reputationlevelid'] != 0, " reputationlevel id = " . $_REQUEST['reputationlevelid'], iif($_REQUEST['minimumreputation'] != 0, "minimum reputation = " . $_REQUEST['minimumreputation'], '')));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_reputation_manager']);

// *************************************************************************************************

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// *************************************************************************************************

if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{

	print_form_header('adminreputation', 'update');
	if ($_REQUEST['reputationlevelid'])
	{
		$reputationlevel = $DB_site->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "reputationlevel
				WHERE reputationlevelid = $_REQUEST[reputationlevelid]
		");
		construct_hidden_code('reputationlevelid', $_REQUEST[reputationlevelid]);
		construct_hidden_code('oldminimum', $reputation['minimumreputation']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['reputation_level'], '<i>' . htmlspecialchars_uni($reputationlevel['level']) . '</i>', $reputationlevel['minimumreputation']));
	}
	else
	{
		print_table_header($vbphrase['add_new_reputation_level']);
	}

	print_input_row($vbphrase['description'], 'reputationlevel[level]', $reputationlevel['level'], 0);
	print_input_row($vbphrase['minimum_reputation_level'], 'reputationlevel[minimumreputation]', $reputationlevel['minimumreputation']);
	print_submit_row(iif($_REQUEST['reputationlevelid'], $vbphrase['update'], $vbphrase['save']));

}

// *************************************************************************************************

if ($_POST['do'] == 'update')
{

	globalize($_POST, array('reputationlevelid' => INT, 'oldminimum', 'reputationlevel'));
	$reputationlevel['level'] = htmlspecialchars_uni($reputationlevel['level']);

	if ($reputationlevelid)
	{ // edit
		$sql = " AND reputationlevelid <> $reputationlevelid ";
	}

	$reputationlevel['minimumreputation'] = intval($reputationlevel['minimumreputation']);
	if (!$DB_site->query_first("SELECT reputationlevelid FROM " . TABLE_PREFIX . "reputationlevel WHERE minimumreputation = $reputationlevel[minimumreputation]" . $sql))
	{

		define('CP_REDIRECT', 'adminreputation.php?do=modify');
		if ($reputationlevelid)
		{ // edit
			$DB_site->query(fetch_query_sql($reputationlevel, 'reputationlevel',"WHERE reputationlevelid=$reputationlevelid"));
			if ($oldminimum != $minimumreputation)
			{ // need to update user table
				build_reputationids();
			}
			print_stop_message('saved_reputation_level_x_successfully', $reputationlevel['level']);
		}
		else
		{
			$DB_site->query(fetch_query_sql($reputationlevel, 'reputationlevel'));
			build_reputationids();
			print_stop_message('saved_reputation_level_x_successfully', $reputationlevel['level']);
		}

	}
	else
	{
		print_stop_message('no_permission_duplicate_reputation');
	}
}

// *************************************************************************************************

if ($_REQUEST['do'] == 'remove')
{
	$minrep = intval($_REQUEST['minimumreputation']);

	print_form_header('adminreputation', 'kill');
	construct_hidden_code('minimumreputation', $minrep);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_delete_the_reputation_level_x'], "<i>$minrep</i>"));
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// *************************************************************************************************

if ($_POST['do'] == 'kill')
{

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "reputationlevel WHERE minimumreputation=" . intval($_POST['minimumreputation']));

	build_reputationids();

	define('CP_REDIRECT', 'adminreputation.php?do=modify');
	print_stop_message('deleted_reputation_level_successfully');

}

// *************************************************************************************************

if ($_POST['do'] == 'updateminimums')
{
	if (is_array($_POST['reputation']))
	{
		foreach($_POST['reputation'] AS $index => $value)
		{
			if ($found["$value"])
			{
				print_stop_message('no_permission_duplicate_reputation');
			}
			else
			{
				$found["$value"] = 1;
			}
		}

		foreach ($_POST['reputation'] AS $index => $value)
		{
			$index = intval($index);

			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "reputationlevel
				SET minimumreputation = " . intval($value) . "
				WHERE reputationlevelid = $index
			");
		}

		build_reputationids();

	}

	define('CP_REDIRECT', 'adminreputation.php?do=modify');
	print_stop_message('saved_reputation_level_x_successfully', '');
}

// *************************************************************************************************

if ($_REQUEST['do'] == 'list' OR $_REQUEST['do'] == 'dolist')
{
	globalize($_REQUEST, array(
		'leftby' => STR_NOHTML,
		'leftfor' => STR_NOHTML,
		'start',
		'end',
		'userid' => INT,
		'whoadded' => INT,
		'page' => INT,
		'perpage' => INT,
		'orderby' => STR,
		'startstamp' => INT,
		'endstamp' => INT,
	));

	$start = iif($startstamp, $startstamp, $start);
	$end = iif($endstamp, $endstamp, $end);

	if ($whoaddedinfo = verify_id('user', $whoadded, 0, 1))
	{
		$leftby = $whoaddedinfo['username'];
	}
	else
	{
		$whoadded = 0;
	}

	if ($userinfo = verify_id('user', $userid, 0, 1))
	{
		$leftfor = $userinfo['username'];
	}
	else
	{
		$userid = 0;
	}

	// Default View Values
	if (!$start)
	{
		$start = TIMENOW - 3600 * 24 * 30;
	}

	if (!$end)
	{
		$end = TIMENOW;
	}

	print_form_header('adminreputation', 'dolist');
	print_table_header($vbphrase['view_reputation_comments']);
	print_input_row($vbphrase['leftfor'], 'leftfor', $leftfor, 0);
	print_input_row($vbphrase['leftby'], 'leftby', $leftby, 0);
	print_time_row($vbphrase['start_date'], 'start', $start, false);
	print_time_row($vbphrase['end_date'], 'end', $end, false);
	print_submit_row($vbphrase['go']);

}

// *************************************************************************************************

if ($_REQUEST['do'] == 'dolist')
{
	require_once('./includes/functions_misc.php');
	if ($startstamp)
	{
		$start = $startstamp;
	}
	else
	{
		$start = vbmktime(0, 0, 0, $start['month'], $start['day'], $start['year']);
	}

	if ($endstamp)
	{
		$end = $endstamp;
	}
	else
	{
		$end = vbmktime(0, 0, 0, $end['month'], $end['day'], $end['year']);
	}

	if ($start >= $end)
	{
		print_stop_message('start_date_after_end');
	}

	if ($leftby)
	{
		if (!$leftby_user = $DB_site->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . addslashes($leftby) . "'
		"))
		{
			print_stop_message('could_not_find_user_x', $leftby);
		}
		$whoadded = $leftby_user['userid'];
	}

	if ($leftfor)
	{
		if (!$leftfor_user = $DB_site->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . addslashes($leftfor) . "'
		"))
		{
			print_stop_message('could_not_find_user_x', $leftfor);
		}
		$userid = $leftfor_user['userid'];
	}

	if ($whoadded)
	{
		$condition = "WHERE rep.whoadded = $whoadded";
	}
	if ($userid)
	{
		$condition .= iif (!$condition, "WHERE", " AND") . " rep.userid = $userid";
	}
	if ($start)
	{
		$condition .= iif (!$condition, "WHERE", " AND") . " rep.dateline >= $start";
	}
	if ($end)
	{
		$condition .= iif (!$condition, "WHERE", " AND") . " rep.dateline <= $end";
	}

	$count = $DB_site->query_first("
		SELECT count(*) AS count
		FROM " . TABLE_PREFIX . "reputation AS rep
		$condition
	");

	$totalrep = $count['count'];

	if (!$totalrep)
	{
		print_stop_message('no_matches_found');
	}

	switch($orderby)
	{
		case 'leftbyuser':
			$orderbysql = 'leftby_user.username';
			break;
		case 'leftforuser':
			$orderbysql = 'leftfor_user.username';
			break;
		default:
			$orderbysql = 'rep.dateline';
			$orderby = 'dateline';
	}

	sanitize_pageresults($totalrep, $page, $perpage);
	$startat = ($page - 1) * $perpage;
	$totalpages = ceil($totalrep / $perpage);

	$comments = $DB_site->query("
		SELECT rep.postid, rep.userid AS userid, whoadded, rep.reason, rep.dateline, rep.reputationid, rep.reputation,
			leftfor_user.username AS leftfor_username,
			leftby_user.username AS leftby_username,
			post.title
		FROM " . TABLE_PREFIX . "reputation AS rep
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (rep.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "user AS leftby_user ON (rep.whoadded = leftby_user.userid)
		LEFT JOIN " . TABLE_PREFIX . "user AS leftfor_user ON (rep.userid = leftfor_user.userid)
		$condition
		ORDER BY $orderbysql
		LIMIT $startat, $perpage
	");

	if ($page != 1)
	{
		$prv = $page - 1;
		$firstpage = "<input type=\"button\" class=\"button\" value=\"&laquo; " . $vbphrase['first_page'] . "\" tabindex=\"1\" onclick=\"window.location='adminreputation.php?$session[sessionurl]do=dolist&userid=$userid&whoadded=$whoadded&perpage=$perpage&page=1&startstamp=$start&endstamp=$end&orderby=$orderby'\">";
		$prevpage = "<input type=\"button\" class=\"button\" value=\"&lt; " . $vbphrase['prev_page'] . "\" tabindex=\"1\" onclick=\"window.location='adminreputation.php?$session[sessionurl]do=dolist&userid=$userid&whoadded=$whoadded&perpage=$perpage&page=$prv&startstamp=$start&endstamp=$end&orderby=$orderby'\">";
	}

	if ($page != $totalpages)
	{
		$nxt = $page + 1;
		$nextpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['next_page'] . " &gt;\" tabindex=\"1\" onclick=\"window.location='adminreputation.php?$session[sessionurl]do=dolist&userid=$userid&whoadded=$whoadded&perpage=$perpage&page=$nxt&startstamp=$start&endstamp=$end&orderby=$orderby'\">";
		$lastpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['last_page'] . " &raquo;\" tabindex=\"1\" onclick=\"window.location='adminreputation.php?$session[sessionurl]do=dolist&userid=$userid&whoadded=$whoadded&perpage=$perpage&page=$totalpages&startstamp=$start&endstamp=$end&orderby=$orderby'\">";
	}

	print_form_header('adminreputation', 'dolist');
	print_table_header(construct_phrase($vbphrase['x_reputation_comments_page_y_z'], vb_number_format($totalrep), $page, vb_number_format($totalpages)), 6);

	$headings = array();
	$headings[] = "<a href='adminreputation.php?$session[sessionurl]do=dolist&amp;userid=$userid&amp;whoadded=$whoadded&amp;perpage=$perpage&amp;orderby=leftbyuser&amp;page=$page&amp;startstamp=$start&amp;endstamp=$end' title='" . $vbphrase['order_by_username'] . "'>" . $vbphrase['leftby'] . "</a>";
	$headings[] = "<a href='adminreputation.php?$session[sessionurl]do=dolist&amp;userid=$userid&amp;whoadded=$whoadded&amp;perpage=$perpage&amp;orderby=leftforuser&amp;page=$page&amp;startstamp=$start&amp;endstamp=$end' title='" . $vbphrase['order_by_username'] . "'>" . $vbphrase['leftfor'] . "</a>";
	$headings[] = "<a href='adminreputation.php?$session[sessionurl]do=dolist&amp;userid=$userid&amp;whoadded=$whoadded&amp;perpage=$perpage&amp;orderby=date&amp;page=$page&amp;startstamp=$start&amp;endstamp=$end' title='" . $vbphrase['order_by_date'] . "'>" . $vbphrase['date'] . "</a>";
	$headings[] = $vbphrase['reputation'];
	$headings[] = $vbphrase['reason'];
	$headings[] = $vbphrase['controls'];
	print_cells_row($headings, 1);

	while ($comment = $DB_site->fetch_array($comments))
	{
		$cell = array();
		$cell[] = "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$comment[whoadded]\"><b>$comment[leftby_username]</b></a>";
		$cell[] = "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$comment[userid]\"><b>$comment[leftfor_username]</b></a>";
		$cell[] = '<span class="smallfont">' . vbdate($vboptions['logdateformat'], $comment['dateline']) . '</span>';
		$cell[] = $comment['reputation'];
		$cell[] = iif ($comment['reason'], '<span class="smallfont">' . construct_link_code(htmlspecialchars_uni($comment['reason']), "../showthread.php?$session[sessionurl]postid=$comment[postid]", 1) . '</span>');
		$cell[] = construct_link_code($vbphrase['edit'], "adminreputation.php?$session[sessionurl]do=editreputation&reputationid=$comment[reputationid]") .
			' ' . construct_link_code($vbphrase['delete'], "adminreputation.php?$session[sessionurl]do=deletereputation&reputationid=$comment[reputationid]");
		print_cells_row($cell);
	}

	print_table_footer(6, "$firstpage $prevpage &nbsp; $nextpage $lastpage");
}

// *************************************************************************************************

if ($_REQUEST['do'] == 'editreputation')
{
	globalize($_REQUEST, array('reputationid' => INT));

	if ($repinfo = $DB_site->query_first("
		SELECT rep.*, whoadded.username as whoadded_username, user.username, thread.title
		FROM " . TABLE_PREFIX . "reputation AS rep
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (rep.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "user AS whoadded ON (rep.whoadded = whoadded.userid)
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (rep.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
		WHERE reputationid = $reputationid
	"))
	{
		print_form_header('adminreputation', 'doeditreputation');
		print_table_header($vbphrase['edit_reputation']);
		print_label_row($vbphrase['thread'], iif($repinfo['title'], "<a href=\"../showthread.php?$session[sessionurl]postid=$repinfo[postid]\">$repinfo[title]</a>"));
		print_label_row($vbphrase['leftby'], $repinfo['whoadded_username']);
		print_label_row($vbphrase['leftfor'], $repinfo['username']);
		print_input_row($vbphrase['comment'], 'reputation[reason]', $repinfo['reason']);
		print_input_row($vbphrase['reputation'], 'reputation[reputation]', $repinfo['reputation'], 0, 5);
		construct_hidden_code('reputationid', $reputationid);
		construct_hidden_code('oldreputation', $repinfo[reputation]);
		construct_hidden_code('userid', $repinfo['userid']);
		print_submit_row();
	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

// *************************************************************************************************

if ($_POST['do'] == 'doeditreputation')
{

	globalize($_POST, array('reputation', 'reputationid' => INT, 'oldreputation' => INT, 'userid' => INT));

	$DB_site->query(fetch_query_sql($reputation, 'reputation', "WHERE reputationid=$reputationid"));

	if ($oldreputation != $reputation['reputation'])
	{
		$diff = $oldreputation - $reputation['reputation'];
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user
			SET reputation = reputation - $diff
			WHERE userid = $userid
		");
	}

	define('CP_REDIRECT', "adminreputation.php?do=list&amp;userid=$userid");

	print_stop_message('saved_reputation_successfully');

}

// *************************************************************************************************

if ($_POST['do'] == 'killreputation')
{
	globalize($_POST, array('reputationid'));

	$repinfo = verify_id('reputation', $reputationid, 0, 1);

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user
		SET reputation = reputation - $repinfo[reputation]
		WHERE userid = $repinfo[userid]
	");

	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "reputation
		WHERE reputationid = $reputationid
	");

	define('CP_REDIRECT', "adminreputation.php?do=list&amp;userid=$repinfo[userid]");

	print_stop_message('deleted_reputation_successfully');
}

// *************************************************************************************************

if ($_REQUEST['do'] == 'deletereputation')
{

	print_delete_confirmation('reputation', intval($_REQUEST['reputationid']), 'adminreputation', 'killreputation');

}

if ($_REQUEST['do'] == 'modify')
{

	$reputationlevels = $DB_site->query("
		SELECT *
		FROM " . TABLE_PREFIX . "reputationlevel
		ORDER BY minimumreputation
	");

	print_form_header('adminreputation', 'updateminimums');
	print_table_header($vbphrase['user_reputation_manager'], 3);
	print_cells_row(array($vbphrase['reputation_level'], $vbphrase['minimum_reputation_level'], $vbphrase['controls']), 1);

	while ($reputationlevel = $DB_site->fetch_array($reputationlevels))
	{
		$cell = array();
		$cell[] = "$vbphrase[user] <b>$reputationlevel[level]</b>";
		$cell[] = "<input type=\"text\" class=\"bginput\" tabindex=\"1\" name=\"reputation[$reputationlevel[reputationlevelid]]\" value=\"$reputationlevel[minimumreputation]\" size=\"5\" />";
		$cell[] = construct_link_code($vbphrase['edit'], "adminreputation.php?$session[sessionurl]do=edit&reputationlevelid=$reputationlevel[reputationlevelid]") . construct_link_code($vbphrase['delete'], "adminreputation.php?$session[sessionurl]do=remove&minimumreputation=$reputationlevel[minimumreputation]");
		print_cells_row($cell);
	}

	print_submit_row($vbphrase['update'], $vbphrase['reset'], 3);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12669 $
|| ####################################################################
\*======================================================================*/
?>