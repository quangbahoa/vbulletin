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

error_reporting(E_ALL & ~E_NOTICE);

// #################### Begin Build Ranks PHP Code function ################
function build_ranks()
{

	global $DB_site;

	$ranks = $DB_site->query("
		SELECT ranklevel, minposts, rankimg, ranks.usergroupid, type
		FROM " . TABLE_PREFIX . "ranks AS ranks
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup USING (usergroupid)
		ORDER BY ranks.usergroupid DESC, minposts DESC
	");

	// Don't escape out any variables from the $rank array
	// Also might consider changing this to something that gets updated in the user table once an hour instead
	// of eval'ing it on every page view.
	while ($rank = $DB_site->fetch_array($ranks))
	{
		if ($groupid != $rank['usergroupid'])
		{ // Move on to the next Usergroup (or the first!)
			if ($reset)
			{
				$output .= "\t}\n";
			}
			$reset = 0;
			if ($output)
			{
				$output .= '} else ';
			}
			if ($rank['usergroupid'] == 0)
			{
				$output .= "if (1 == 1) {\n";
			}
			else
			{
				// change 'displaygroupid' to 'usergroupid' to force users to use the ranks of their primary usergroup
				$output .= "if (\$post['displaygroupid'] == $rank[usergroupid]) {\n";
			}
		}
		$groupid = $rank['usergroupid'];
		if ($reset)
		{
			$output .= "\t} else ";
		}
		else
		{
			$output .= "\t";
		}
		$output .= "if (\$post['posts'] >= $rank[minposts]) {\n";
		$output .= "\t\t\$post['rank'] = \"";
		for ($x = $rank['ranklevel']; $x--; $x > 0)
		{
			if (!$rank['type'])
			{
				$output .= "<img src=\\\"$rank[rankimg]\\\" alt=\\\"\\\" border=\\\"0\\\" />";
			}
			else
			{
				$output .= str_replace(array('\\', '"'), array('\\\\', '\"'), $rank['rankimg']);
			}
		}
		$output .= "\";\n";
		$reset = 1;
	}


	if ($output)
	{
		$output .= "\t}\n}";
	}

	//$output = "\$post['rank'] = '';\n" . $output;
	//echo htmlspecialchars($output);
	//exit;

	build_datastore('rankphp', $output);

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 8893 $
|| ####################################################################
\*======================================================================*/
?>