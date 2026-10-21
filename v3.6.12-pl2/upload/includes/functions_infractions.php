<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

function build_user_infractions($points, $infractions, $warnings)
{
	global $vbulletin;

	$warningsql = array();
	$infractionsql = array();
	$ipointssql = array();
	$querysql = array();
	$userids = array();

	// ############################ WARNINGS #################################
	$wa = array();
	foreach($warnings AS $userid => $warning)
	{
		$wa["$warning"][] = $userid;
		$userids["$userid"] = $userid;
	}
	unset($warnings);

	foreach($wa AS $warning => $users)
	{
		$warningsql[] = "WHEN userid IN(" . implode(', ', $users) . ") THEN $warning";
	}
	unset($wa);
	if (!empty($warningsql))
	{
		$querysql[] = "
		warnings = CAST(warnings AS SIGNED) -
		CASE
			" . implode(" \r\n", $warningsql) . "
		ELSE 0
		END";
	}
	unset($warningsql);

	// ############################ INFRACTIONS ##############################
	$if = array();
	foreach($infractions AS $userid => $infraction)
	{
		$if["$infraction"][] = $userid;
		$userids["$userid"] = $userid;
	}
	unset($infractions);
	foreach($if AS $infraction => $users)
	{
		$infractionsql[] = "WHEN userid IN(" . implode(', ', $users) . ") THEN $infraction";
	}
	unset($if);
	if (!empty($infractionsql))
	{
		$querysql[] = "
		infractions = CAST(infractions AS SIGNED) -
		CASE
			" . implode(" \r\n", $infractionsql) . "
		ELSE 0
		END";
	}
	unset($infractionsql);

	// ############################ POINTS ###################################
	$ip = array();
	foreach($points AS $userid => $point)
	{
		$ip["$point"][] = $userid;
	}
	unset($points);
	foreach($ip AS $point => $users)
	{
		$ipointssql[] = "WHEN userid IN(" . implode(', ', $users) . ") THEN $point";
	}
	unset($ip);
	if (!empty($ipointssql))
	{
		$querysql[] = "
		ipoints = CAST(ipoints AS SIGNED) -
		CASE
			" . implode(" \r\n", $ipointssql) . "
		ELSE 0
		END";
	}
	unset($ipointssql);

	if (!empty($querysql))
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET " . implode(', ', $querysql) . "
			WHERE userid IN (" . implode(', ', $userids) . ")
		");

		return true;
	}
	else
	{
		return false;
	}
}

function build_infractiongroupids($userids)
{
	global $vbulletin;
	static $infractiongroups = array(), $beenhere;

	if (!$beenhere)
	{
		$beenhere = true;
		$groups = $vbulletin->db->query_read_slave("
			SELECT usergroupid, orusergroupid, pointlevel, override
			FROM " . TABLE_PREFIX . "infractiongroup
			ORDER BY pointlevel
		");
		while ($group = $vbulletin->db->fetch_array($groups))
		{
			$infractiongroups["$group[usergroupid]"]["$group[pointlevel]"][] = array(
				'orusergroupid' => $group['orusergroupid'],
				'override'      => $group['override'],
			);
		}
	}

	if (!empty($infractiongroups))
	{
		$users = $vbulletin->db->query_read("
			SELECT user.*
			FROM " . TABLE_PREFIX . "user AS user
			WHERE userid IN (" . implode(', ', $userids) . ")
				AND infractiongroupids <> ''
		");
		while ($user = $vbulletin->db->fetch_array($users))
		{
			$infractioninfo = fetch_infraction_groups($infractiongroups, $user['userid'], $user['ipoints'], $user['usergroupid']);

			if (($groupids = implode(',', $infractioninfo['infractiongroupids'])) != $user['infractiongroupids'] OR $infractioninfo['infractiongroupid'] != $user['infractiongroupid'])
			{
				$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
				$userdata->set_existing($user);
				$userdata->set('infractiongroupids', $groupids);
				$userdata->set('infractiongroupid', $infractioninfo['infractiongroupid']);
				$userdata->save();
			}
		}
	}
}

/**
* Takes valid data and sets it as part of the data to be saved
*
* @param	array		List of infraction groups
* @param integer  Userid of user
* @param	integer	Infraction Points
* @param interger Usergroupid
*
* @return array	User's final infraction groups
*/
function fetch_infraction_groups(&$infractiongroups, $userid, $ipoints, $usergroupid)
{
	static $cache;

	if (!is_array($data))
	{
		$data = array();
	}

	$infractiongroupids = array();

	if (!empty($infractiongroups["$usergroupid"]))
	{
		foreach($infractiongroups["$usergroupid"] AS $pointlevel => $orusergroupids)
		{
			if ($pointlevel <= $ipoints)
			{
				foreach($orusergroupids AS $infinfo)
				{
					$data['infractiongroupids']["$infinfo[orusergroupid]"] = $infinfo['orusergroupid'];
					if ($infinfo['override'] AND $cache["$userid"]['pointlevel'] <= $pointlevel)
					{
						$cache["$userid"]['pointlevel'] = $pointlevel;
						$cache["$userid"]['infractiongroupid'] = $infinfo['orusergroupid'];
					}
				}
			}
			else
			{
				break;
			}
		}
	}

	if (!is_array($data['infractiongroupids']))
	{
		$data['infractiongroupids'] = array();
	}

	if ($usergroupid != -1)
	{
		$temp = fetch_infraction_groups($infractiongroups, $userid, $ipoints, -1);
		$data['infractiongroupids'] = array_merge($data['infractiongroupids'], $temp['infractiongroupids']);
	}

	if (!is_array($data['infractiongroupids']))
	{
		$data['infractiongroupids'] = array();
	}

	$data['infractiongroupid'] = intval($cache["$userid"]['infractiongroupid']);
	return $data;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 16941 $
|| ####################################################################
\*======================================================================*/
?>