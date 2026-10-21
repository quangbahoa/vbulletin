<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);
if (!is_object($vbulletin->db))
{
	exit;
}

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('ONEDAY', 86400);
define('TWODAYS', 172800);
define('FIVEDAYS', 432000);
define('SIXDAYS', 518400);

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// Send the reminder email only twice. After 1 day and then 5 Days.
$users = $vbulletin->db->query_read("
	SELECT user.userid, user.usergroupid, username, email, activationid, user.languageid
	FROM " . TABLE_PREFIX . "user AS user
	LEFT JOIN " . TABLE_PREFIX . "useractivation AS useractivation ON (user.userid=useractivation.userid AND type = 0)
	WHERE user.usergroupid = 3
		AND ((joindate >= " . (TIMENOW - TWODAYS) . " AND joindate <= " . (TIMENOW - ONEDAY) . ") OR (joindate >= " . (TIMENOW - SIXDAYS) . " AND joindate <= " . (TIMENOW - FIVEDAYS) . "))
		AND NOT (user.options & " . $vbulletin->bf_misc_useroptions['noactivationmails'] . ")
");
vbmail_start();

$emails = '';

while ($user = $vbulletin->db->fetch_array($users))
{
	// make random number
	if (empty($user['activationid']))
	{ //none exists so create one
		$user['activationid'] = vbrand(0, 100000000);
		/*insert query*/
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "useractivation
				(userid, dateline, activationid, type, usergroupid)
			VALUES
				($user[userid], " . TIMENOW . ", $user[activationid], 0, 2)
		");
	}
	else
	{
		$user['activationid'] = vbrand(0, 100000000);
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "useractivation SET
			dateline = " . TIMENOW . ",
			activationid = $user[activationid]
			WHERE userid = $user[userid] AND type = 0
		");
	}

	$userid = $user['userid'];
	$username = $user['username'];
	$activateid = $user['activationid'];

	eval(fetch_email_phrases('activateaccount', $user['languageid']));

	vbmail($user['email'], $subject, $message);

	$emails .= iif($emails, ', ');
	$emails .= $user['username'];
}

if ($emails)
{
	log_cron_action($emails, $nextitem, 1);
}

vbmail_end();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 31700 $
|| ####################################################################
\*======================================================================*/
?>