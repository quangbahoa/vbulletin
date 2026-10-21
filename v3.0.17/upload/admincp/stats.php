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
define('CVS_REVISION', '$RCSfile$ - $Revision: 10834 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('stats');
$specialtemplates = array('userstats', 'maxloggedin');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['statistics']);

if (empty($_REQUEST['do']) OR $_REQUEST['do'] == 'index' OR $_REQUEST['do'] == 'top')
{
	print_form_header('stats', 'index');
	print_table_header($vbphrase['statistics']);
	print_label_row(construct_link_code($vbphrase['top_statistics'], 'stats.php?do=top'), '');
	print_label_row(construct_link_code($vbphrase['registration_statistics'], 'stats.php?do=reg'), '');
	print_label_row(construct_link_code($vbphrase['user_activity_statistics'], 'stats.php?do=activity'), '');
	print_label_row(construct_link_code($vbphrase['new_thread_statistics'], 'stats.php?do=thread'), '');
	print_label_row(construct_link_code($vbphrase['new_post_statistics'], 'stats.php?do=post'), '');
	print_table_footer();
}

if ($_REQUEST['do'] == 'top')
{ // Find most popular things below

	// user stats
	$userstats = unserialize($datastore['userstats']);

	// max logged in users
	$maxusers = unserialize($datastore['maxloggedin']);
	$recorddate = vbdate($vboptions['dateformat'], $maxusers['maxonlinedate'], 1);
	$recordtime = vbdate($vboptions['timeformat'], $maxusers['maxonlinedate']);

	// Most Posts
	$maxposts = $DB_site->query_first("SELECT userid, username, posts FROM " . TABLE_PREFIX . "user ORDER BY posts DESC");

	// Largest Thread
	$maxthread = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "thread ORDER BY replycount DESC");

	// Most Popular Thread
	$mostpopular = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "thread ORDER BY views DESC");

	// Most Popular Forum
	$popularforum = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "forum ORDER BY replycount DESC");

	print_form_header('');
	print_table_header($vbphrase['top']);

	print_label_row($vbphrase['newest_member'], construct_link_code($userstats['newusername'], "user.php?do=edit&userid=$userstats[newuserid]"));
	print_label_row($vbphrase['record_online_users'], "$maxusers[maxonline] ($recorddate $recordtime)");

	print_label_row($vbphrase['top_poster'], construct_link_code("$maxposts[username] - $maxposts[posts]", "user.php?do=edit&userid=$maxposts[userid]"));
	print_label_row($vbphrase['most_replied_thread'], construct_link_code($maxthread['title'], "../showthread.php?threadid=$maxthread[threadid]", true));
	print_label_row($vbphrase['most_viewed_thread'], construct_link_code($mostpopular['title'], "../showthread.php?threadid=$mostpopular[threadid]", true));
	print_label_row($vbphrase['most_popular_forum'], construct_link_code($popularforum['title'], "../forumdisplay.php?forumid=$popularforum[forumid]", true));
	print_table_footer();

}

globalize($_REQUEST, array('start', 'end', 'scope', 'sort'));

// Default View Values
if (empty($start))
{
	$start = TIMENOW - 3600 * 24 * 30;
}

if (empty($end))
{
	$end = TIMENOW;
}

switch ($sort)
{
	case 'ASC':
	case 'DESC':
		break;
	default:
		$sort = 'ASC';
		break;
}

switch ($_REQUEST['do'])
{

	case 'reg':
		$type = 'nuser';
		print_statistic_code($vbphrase['registration_statistics'], 'reg', $start, $end, $scope, $sort);
		break;
	case 'thread':
		$type = 'nthread';
		print_statistic_code($vbphrase['new_thread_statistics'], 'thread', $start, $end, $scope, $sort);
		break;
	case 'post':
		$type = 'npost';
		print_statistic_code($vbphrase['new_post_statistics'], 'post', $start, $end, $scope, $sort);
		break;
	case 'activity':
		$type = 'ausers';
		print_statistic_code($vbphrase['user_activity_statistics'], 'activity', $start, $end, $scope, $sort);
		break;
}

if (!empty($scope))
{ // we have a submitted form
	$start_time = mktime(0, 0, 0, $start['month'], $start['day'], $start['year']);
	$end_time = mktime(0, 0, 0, $end['month'], $end['day'], $end['year']);
	if ($start_time >= $end_time)
	{
		print_stop_message('start_date_after_end');
	}

	if ($type == 'activity')
	{
		$scope = 'daily';
	}

	switch ($scope)
	{
		case 'weekly':
			$sqlformat = '%U %Y';
			$phpformat = '# (! Y)';
			break;
		case 'monthly':
			$sqlformat = '%m %Y';
			$phpformat = '! Y';
			break;
		default:
			$sqlformat = '%w %U %m %Y';
			$phpformat = '! d, Y';
			break;
	}

	$statistics = $DB_site->query("
		SELECT SUM($type) AS total,
		DATE_FORMAT(from_unixtime(dateline), '$sqlformat') AS formatted_date,
		MAX(dateline) AS dateline
		FROM " . TABLE_PREFIX . "stats
		WHERE dateline >= $start_time
			AND dateline <= $end_time
		GROUP BY formatted_date
		ORDER BY dateline $sort
	");

	while ($stats = $DB_site->fetch_array($statistics))
	{ // we will now have each days total of the type picked and we can sort through it
		$month = strtolower(date('F', $stats['dateline']));
		$dates[] = str_replace(' ', '&nbsp;', str_replace('#', $vbphrase['week'] . '&nbsp;' . strftime('%U', $stats['dateline']), str_replace('!', $vbphrase["$month"], date($phpformat, $stats['dateline']))));
		$results[] = $stats['total'];
	}

	if (!sizeof($results))
	{
		//print_array($results);
		print_stop_message('no_matches_found');
	}

	// we'll need a poll image
	$style = $DB_site->query_first("
		SELECT stylevars FROM " . TABLE_PREFIX . "style
		WHERE styleid = $vboptions[styleid]
		LIMIT 1
	");
	$stylevars = unserialize($style['stylevars']);
	unset($style);

	print_form_header('');
	print_table_header($vbphrase['results'], 3);
	print_cells_row(array($vbphrase['date'], '&nbsp;', $vbphrase['total']), 1);
	$maxvalue = max($results);
	foreach ($results as $key => $value)
	{
		$i++;
		$bar = ($i % 6) + 1;
		if ($maxvalue == 0)
		{
			$percentage = 100;
		}
		else
		{
			$percentage = ceil(($value/$maxvalue) * 100);
		}
		print_statistic_result($dates["$key"], $bar, $value, $percentage);
	}
	print_table_footer(3);
}

function print_statistic_result($date, $bar, $value, $width)
{
	global $stylevars;
	$bgclass = fetch_row_bgclass();

	if (preg_match('#^(https?://|/)#i', $stylevars['imgdir_poll']))
	{
		$imgpath = $stylevars['imgdir_poll'];
	}
	else
	{
		$imgpath = '../' . $stylevars['imgdir_poll'];
	}

	echo '<tr><td width="0" class="' . $bgclass . '">' . $date . "</td>\n";
	echo '<td width="100%" class="' . $bgclass . '" nowrap="nowrap"><img src="' . $imgpath . '/bar' . $bar . '-l.gif" height="10" /><img src="' . $imgpath . '/bar' . $bar . '.gif" width="' . $width . '%" height="10" /><img src="' . $imgpath . '/bar' . $bar . "-r.gif\" height=\"10\" /></td>\n";
	echo '<td width="0%" class="' . $bgclass . '" nowrap="nowrap">' . $value . "</td></tr>\n";
}

function print_statistic_code($title, $name, $start, $end, $scope = 'daily', $sort = 'DESC')
{

	global $vbphrase;

	print_form_header('stats', $name);
	print_table_header($title);

	print_time_row($vbphrase['start_date'], 'start', $start, false);
	print_time_row($vbphrase['end_date'], 'end', $end, false);

	if ($name != 'activity')
	{
		print_select_row($vbphrase['scope'], 'scope', array('daily' => $vbphrase['daily'], 'weekly' => $vbphrase['weekly'], 'monthly' => $vbphrase['monthly']), $scope);
	}
	else
	{
		construct_hidden_code('scope', 'daily');
	}
	print_select_row($vbphrase['order_by_date'], 'sort', array('ASC' => $vbphrase['ascending'], 'DESC' => $vbphrase['descending']), $sort);
	print_submit_row($vbphrase['go']);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 10834 $
|| ####################################################################
\*======================================================================*/
?>