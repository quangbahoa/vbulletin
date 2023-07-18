<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

// #################### Fetch User's Rank ################
function &fetch_rank(&$userinfo)
{
	global $vbulletin;

	if (!is_array($vbulletin->ranks))
	{
		// grab ranks since we didn't include 'ranks' in $specialtemplates
		$vbulletin->ranks =& build_ranks();
	}

	$doneusergroup = array();
	$userrank = '';

	foreach ($vbulletin->ranks AS $rank)
	{
		$displaygroupid = empty($userinfo['displaygroupid']) ? $userinfo['usergroupid'] : $userinfo['displaygroupid'];
		if ($userinfo['posts'] >= $rank['m'] AND (!isset($doneusergroup["$rank[u]"]) OR $doneusergroup["$rank[u]"] === $rank['m'])
		AND
		(($rank['u'] > 0 AND is_member_of($userinfo, $rank['u'], false) AND (empty($rank['d']) OR $rank['u'] == $displaygroupid))
		OR
		($rank['u'] == 0 AND (empty($rank['d']) OR empty($userrank)))))
		{
			if (!empty($userrank) AND $rank['s'])
			{
				$userrank .= '<br />';
			}
			$doneusergroup["$rank[u]"] = $rank['m'];
			for ($x = $rank['l']; $x--; $x > 0)
			{
				if (empty($rank['t']))
				{
					$userrank .= "<img src=\"$rank[i]\" alt=\"\" border=\"\" />";
				}
				else
				{
					$userrank .= $rank['i'];
				}
			}
		}
	}

	return $userrank;
}

// #################### Begin Build Ranks PHP Code function ################
function &build_ranks()
{
	global $vbulletin;

	$ranks = $vbulletin->db->query_read_slave("
		SELECT ranklevel AS l, minposts AS m, rankimg AS i, type AS t, stack AS s, display AS d, ranks.usergroupid AS u
		FROM " . TABLE_PREFIX . "ranks AS ranks
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup USING (usergroupid)
		ORDER BY ranks.usergroupid DESC, minposts DESC
	");

	$rankarray = array();
	while ($rank = $vbulletin->db->fetch_array($ranks))
	{
		$rankarray[] = $rank;
	}

	build_datastore('ranks', serialize($rankarray), 1);

	return $rankarray;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
