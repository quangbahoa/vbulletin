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

if (!is_object($DB_site))
{
	exit;
}

// select all banned users who are due to have their ban lifted
$bannedusers = $DB_site->query("
	### SELECTING BANNED USERS WHO ARE DUE TO BE RESTORED ###
	SELECT userban.*, user.username, user.posts
	FROM " . TABLE_PREFIX . "userban AS userban
	LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
	WHERE liftdate <> 0 AND liftdate < " . TIMENOW . "
");

// do we have some results?
if ($DB_site->num_rows($bannedusers))
{
	// some users need to have their bans lifted
	$userids = array();
	while ($banneduser = $DB_site->fetch_array($bannedusers))
	{
		// get usergroup info
		$getusergroupid = iif($banneduser['displaygroupid'], $banneduser['displaygroupid'], $banneduser['usergroupid']);
		$usergroup = $usergroupcache["$getusergroupid"];
		if ($banneduser['customtitle'])
		{
			$usertitle = $banneduser['usertitle'];
		}
		else if (!$usergroup['usertitle'])
		{
			$gettitle = $DB_site->query_first("
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
		$dotitle = 'usertitle = \'' . addslashes($usertitle) . '\',';

		// update users to get their old usergroupid/displaygroupid/usertitle back
		$DB_site->query("
			### LIFT BAN ON USER " . addslashes($banneduser['username']) ." ###
			UPDATE " . TABLE_PREFIX . "user
			SET	$dotitle
				usergroupid = $banneduser[usergroupid],
				displaygroupid = $banneduser[displaygroupid],
				customtitle = $banneduser[customtitle]
			WHERE userid = $banneduser[userid]
		");
		$users["$banneduser[userid]"] = $banneduser['username'];
	}

	// delete ban records
	$DB_site->query("
		### DELETE PROCESSED BAN RECORDS ###
		DELETE FROM " . TABLE_PREFIX . "userban
		WHERE userid IN(" . implode(', ', array_keys($users)) . ")
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

$DB_site->free_result($bannedusers);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9305 $
|| ####################################################################
\*======================================================================*/
?>