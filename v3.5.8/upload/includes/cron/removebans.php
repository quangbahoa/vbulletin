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
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// select all banned users who are due to have their ban lifted
$bannedusers = $vbulletin->db->query_read("
	SELECT user.*, userban.*
	FROM " . TABLE_PREFIX . "userban AS userban
	LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
	WHERE liftdate <> 0 AND liftdate < " . TIMENOW . "
	### SELECTING BANNED USERS WHO ARE DUE TO BE RESTORED ###
");

// do we have some results?
if ($vbulletin->db->num_rows($bannedusers))
{
	// some users need to have their bans lifted
	$userids = array();
	while ($banneduser = $vbulletin->db->fetch_array($bannedusers))
	{
		// get usergroup info
		$getusergroupid = iif($banneduser['displaygroupid'], $banneduser['displaygroupid'], $banneduser['usergroupid']);
		$usergroup = $vbulletin->usergroupcache["$getusergroupid"];
		if ($banneduser['customtitle'])
		{
			$usertitle = $banneduser['usertitle'];
		}
		else if (!$usergroup['usertitle'])
		{
			$gettitle = $vbulletin->db->query_first("
				SELECT title
				FROM " . TABLE_PREFIX . "usertitle
				WHERE minposts <= " . intval($banneduser[posts]) . "
				ORDER BY minposts DESC
			");
			$usertitle = $gettitle['title'];
		}
		else
		{
			$usertitle = $usergroup['usertitle'];
		}

		// update users to get their old usergroupid/displaygroupid/usertitle back
		$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
		$userdm->set_existing($banneduser);
		$userdm->set('usertitle', $usertitle);
		$userdm->set('usergroupid', $banneduser['usergroupid']);
		$userdm->set('displaygroupid', $banneduser['displaygroupid']);
		$userdm->set('customtitle', $banneduser['customtitle']);

		$userdm->save();
		unset($userdm);

		$users["$banneduser[userid]"] = $banneduser['username'];
	}

	// delete ban records
	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "userban
		WHERE userid IN(" . implode(', ', array_keys($users)) . ")
		### DELETE PROCESSED BAN RECORDS ###
	");

	$logmessage = 'Lifted ban on users: ' . implode(', ', $users) . '.';

	// log the cron action
	log_cron_action($logmessage, $nextitem);
}
/*
else
{
	$logmessage = 'No users due to have ban lifted';
}
*/

$vbulletin->db->free_result($bannedusers);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11853 $
|| ####################################################################
\*======================================================================*/
?>