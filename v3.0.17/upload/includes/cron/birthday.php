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

$today = date('m-d', TIMENOW);

$ids = '0';
foreach($usergroupcache AS $usergroupid => $usergroup)
{
	if ($usergroup['genericoptions'] & SHOWBIRTHDAY AND !in_array($usergroup['usergroupid'], array(1, 3, 4)))
	{
		$ids .= ",$usergroupid";
	}
}

$birthdays = $DB_site->query("
	SELECT username, email, languageid
	FROM " . TABLE_PREFIX . "user
	WHERE birthday LIKE '$today-%' AND
	(options & $_USEROPTIONS[adminemail]) AND
	usergroupid IN ($ids)
");

vbmail_start();

while ($userinfo = $DB_site->fetch_array($birthdays))
{
	$username = unhtmlspecialchars($userinfo['username']);
	eval(fetch_email_phrases('birthday', $userinfo['languageid']));
	vbmail($userinfo['email'], $subject, $message);
	$emails .= iif($emails, ', ');
	$emails .= $userinfo['username'];
}

vbmail_end();

if ($emails)
{
	log_cron_action('Birthday Email sent to: ' . $emails, $nextitem);
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9613 $
|| ####################################################################
\*======================================================================*/
?>