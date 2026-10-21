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

require_once('./includes/functions_subscriptions.php');

cache_user_subscriptions();

if (is_array($subscriptioncache))
{
	foreach ($subscriptioncache as $key => $subscription)
	{
		// disable people :)
		$subscribers = $DB_site->query("
			SELECT userid
			FROM " . TABLE_PREFIX . "subscriptionlog
			WHERE subscriptionid = $subscription[subscriptionid]
				AND expirydate <= " . TIMENOW . "
				AND status = 1
		");

		while ($subscriber = $DB_site->fetch_array($subscribers))
		{
			delete_user_subscription($subscription['subscriptionid'], $subscriber['userid']);
		}
	}

	// time for the reminders
	$subscriptions_reminders = $DB_site->query("
		SELECT subscriptionlog.subscriptionid, subscriptionlog.userid, subscriptionlog.expirydate, user.username, user.email, user.languageid
		FROM " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = subscriptionlog.userid)
		WHERE subscriptionlog.expirydate >= " . (TIMENOW + (86400 * 2)) . "
			AND subscriptionlog.expirydate <= " . (TIMENOW + (86400 * 3)) . "
			AND status = 1
	");

	vbmail_start();
	while ($subscriptions_reminder = $DB_site->fetch_array($subscriptions_reminders))
	{
		$subscription_title = $subscriptioncache["$subscriptions_reminder[subscriptionid]"]['title'];

		$username = unhtmlspecialchars($subscriptions_reminder['username']);
		eval(fetch_email_phrases('paidsubscription_reminder', $subscriptions_reminder['languageid']));
		vbmail($subscriptions_reminder['email'], $subject, $message);
	}
	vbmail_end();
}

log_cron_action('Subscriptions Updated', $nextitem);
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9305 $
|| ####################################################################
\*======================================================================*/
?>