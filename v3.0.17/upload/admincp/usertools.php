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
define('CVS_REVISION', '$RCSfile$ - $Revision: 13174 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cpuser', 'forum', 'timezone', 'user');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_user.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

if (is_browser('webkit') AND isset($_POST['avatarid']) AND empty($_POST['do']))
{
    $_POST['do'] = $_REQUEST['do'] = 'updateavatar';
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['userid'] != 0, 'user id = ' . $_REQUEST['userid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_manager']);

// ###################### Start Remove User's Subscriptions #######################
if ($_REQUEST['do'] == 'removesubs')
{
	globalize($_REQUEST, array('userid' => INT));

	print_delete_confirmation('user', $userid, 'usertools', 'killsubs', 'subscriptions');
}

// ###################### Start Remove User's PMs #######################
if ($_POST['do'] == 'killsubs')
{
	globalize($_POST, array('userid' => INT));

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE userid = $userid");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "subscribeforum WHERE userid = $userid");

	define('CP_REDIRECT', "user.php?$session[sessionurl]do=edit&amp;userid=$userid");
	print_stop_message('deleted_subscriptions_successfully');
}

// ###################### Start Remove User's PMs #######################
if ($_REQUEST['do'] == 'removepms')
{
	globalize($_REQUEST, array('userid' => INT));

	print_delete_confirmation('user', $userid, 'usertools', 'killpms', 'private_messages_belonging_to_the_user');
}

// ###################### Start Remove User's PMs #######################
if ($_POST['do'] == 'killpms')
{
	globalize($_POST, array('userid' => INT));

	$result = delete_user_pms($userid);

	define('CP_REDIRECT', "user.php?do=edit&amp;userid=$userid");
	print_stop_message('deleted_x_pms_y_pmtexts_and_z_receipts', $result['pms'], $result['pmtexts'], $result['receipts']);
}

// ###################### Start Remove PMs Sent by User #######################
if ($_REQUEST['do'] == 'removesentpms')
{
	globalize($_REQUEST, array('userid' => INT));

	print_delete_confirmation('user', $userid, 'usertools', 'killsentpms', 'private_messages_sent_by_the_user');
}

// ###################### Start Remove User's PMs #######################
if ($_POST['do'] == 'killsentpms')
{
	globalize($_POST, array('userid' => INT));

	$user = $DB_site->query_first("SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE userid = $userid");

	$pmtextids = '0';
	$pmtexts = $DB_site->query("SELECT pmtextid FROM " . TABLE_PREFIX . "pmtext WHERE fromuserid = $userid");
	while ($pmtext = $DB_site->fetch_array($pmtexts))
	{
		$pmtextids .= ",$pmtext[pmtextid]";
	}
	$DB_site->free_result($pmtexts);

	define('CP_REDIRECT', "user.php?$session[sessionurl]do=edit&amp;userid=$userid");

	if ($pmtextids == '0')
	{
		print_stop_message('no_private_messages_matched_your_query');
	}
	else
	{
		$pmids = '0';
		$pmarray = array();
		$pms = $DB_site->query("
			SELECT pm.*, user.username
			FROM " . TABLE_PREFIX . "pm AS pm
			LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
			WHERE pm.pmtextid IN($pmtextids)
		");
		while ($pm = $DB_site->fetch_array($pms))
		{
			$pmids .= ",$pm[pmid]";
			$pmarray["$pm[username]"][] = $pm;
		}
		$DB_site->free_result($pms);

		$users = array();

		foreach($pmarray AS $username => $pms)
		{
			$pmunread = 0;
			foreach($pms AS $pm)
			{
				if ($pm['messageread'] == 0)
				{
					$pmunread ++;
				}
			}
			$pmtotal = sizeof($pms);
			$users["$pm[userid]"] = array('pmtotal' => $pmtotal, 'pmunread' => $pmunread);
		}

		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "pm WHERE pmid IN($pmids)");

		if (!empty($users))
		{
			$pmtotalsql = 'CASE userid ';
			$pmunreadsql = 'CASE userid ';
			foreach($users AS $id => $x)
			{
				$pmtotalsql .= "WHEN $id THEN pmtotal - $x[pmtotal] ";
				$pmunreadsql .= "WHEN $id THEN pmunread - $x[pmunread] ";
			}
			$pmtotalsql .= 'ELSE pmtotal END';
			$pmunreadsql .= 'ELSE pmunread END';

			$userids = implode(', ', array_keys($users));

			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "user
				SET pmtotal = $pmtotalsql,
				pmunread = $pmunreadsql
				WHERE userid IN($userids)
			");
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "user
				SET pmpopup = IF(pmpopup=2 AND pmunread = 0, 1, pmpopup)
				WHERE userid IN($userids)
			");
		}

		print_stop_message('deleted_private_messages_successfully');
	}
}

// ###################### Start Merge #######################
if ($_REQUEST['do'] == 'merge')
{

	print_form_header('usertools', 'domerge');
	print_table_header($vbphrase['merge_users']);
	print_description_row($vbphrase['merge_allows_you_to_join_two_user_accounts']);
	print_input_row($vbphrase['source_username'], 'sourceuser');
	print_input_row($vbphrase['destination_username'], 'destuser');
	print_submit_row($vbphrase['continue']);

}

// ###################### Start Do Merge #######################
if ($_POST['do'] == 'domerge')
{
	globalize($_POST, array(
		'sourceuser' => STR,
		'destuser' => STR
	));

	if ($sourceuser == '' OR !$sourceinfo = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes(htmlspecialchars_uni($sourceuser)) . "'"))
	{
		print_stop_message('invalid_source_username_specified');
	}
	if ($destuser == '' OR !$destinfo = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes(htmlspecialchars_uni($destuser)) . "'"))
	{
		print_stop_message('invalid_destination_username_specified');
	}
	if ($destuser == $sourceuser)
	{
		print_stop_message('source_and_destination_identical');
	}

	$noalter = explode(',', $undeletableusers);
	if (!empty($noalter[0]) AND (in_array($sourceinfo['userid'], $noalter) OR in_array($destinfo['userid'], $noalter)))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	print_form_header('usertools', 'reallydomerge');
	construct_hidden_code('sourceuserid', $sourceinfo['userid']);
	construct_hidden_code('destuserid', $destinfo['userid']);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_merge_x_into_y'], htmlspecialchars_uni($sourceuser), htmlspecialchars_uni($destuser)));
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Start Do Merge #######################
if ($_POST['do'] == 'reallydomerge')
{
// Get info on both users

	globalize($_REQUEST, array('sourceuserid' => INT, 'destuserid' => INT));

	$sourceinfo = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING(userid)
		WHERE user.userid = $sourceuserid
	");

	$destinfo = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING(userid)
		WHERE user.userid = $destuserid
	");

	// Update Subscribed Forums
	$insertsql = '';
	$subforums = $DB_site->query("
		SELECT forumid
		FROM " . TABLE_PREFIX . "subscribeforum
		WHERE userid = $destinfo[userid]
	");
	while ($forums = $DB_site->fetch_array($subforums))
	{
		$subscribedforums["$forums[forumid]"] = 1;
	}


	$subforums = $DB_site->query("
		SELECT forumid, emailupdate
		FROM " . TABLE_PREFIX . "subscribeforum
		WHERE userid = $sourceinfo[userid]
	");
	while ($forums = $DB_site->fetch_array($subforums))
	{
		if (!isset($subscribedforums[$forums['forumid']]))
		{
			if ($insertsql)
			{
				$insertsql .= ',';
			}
			$insertsql .= "($destinfo[userid], $forums[forumid], $forums[emailupdate])";
		}
	}
	if ($insertsql)
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "subscribeforum
				(userid, forumid, emailupdate)
			VALUES
				$insertsql
		");
	}

	// Update Subscribed Threads
	unset($insertsql);
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "subscribethread
		SET folderid = 0
		WHERE userid = $destinfo[userid]
	");
	$subthreads = $DB_site->query("
		SELECT threadid, emailupdate
		FROM " . TABLE_PREFIX . "subscribethread
		WHERE userid = $destinfo[userid]
	");
	while ($threads = $DB_site->fetch_array($subthreads))
	{
		$subscribedthreads[$threads['threadid']] = 1;
		$status[$threads['threadid']] = $threads['emailupdate'];
	}


	$subthreads = $DB_site->query("
		SELECT threadid, emailupdate
		FROM " . TABLE_PREFIX . "subscribethread
		WHERE userid = $sourceinfo[userid]
	");
	while ($threads = $DB_site->fetch_array($subthreads))
	{
		if (!isset($subscribedthreads[$threads['threadid']]))
		{
			if ($insertsql)
			{
				$insertsql .= ',';
			}
			$insertsql .= "($destinfo[userid], 0, $threads[threadid], $threads[emailupdate])";
		}
		else
		{
			if ($status[$threads['threadid']] != $threads['emailupdate'])
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "subscribethread
					SET emailupdate = $threads[emailupdate]
					WHERE userid = $destinfo[userid]
						AND threadid = $threads[threadid]
				");
			}
		}
	}
	if ($insertsql)
	{
		$DB_site->query("
			INSERT " . TABLE_PREFIX . "subscribethread
				(userid, folderid, threadid, emailupdate)
			VALUES
				$insertsql
		");
	}

	// Update Subscribed Events
	$insertsql = '';
	$events = $DB_site->query("
		SELECT eventid
		FROM " . TABLE_PREFIX . "subscribeevent
		WHERE userid = $destinfo[userid]
	");
	while ($event = $DB_site->fetch_array($event))
	{
		$subscribedevents["$event[eventid]"] = 1;
	}


	$events = $DB_site->query("
		SELECT eventid
		FROM " . TABLE_PREFIX . "subscribeevent
		WHERE userid = $sourceinfo[userid]
	");
	while ($event = $DB_site->fetch_array($events))
	{
		if (!isset($subscribedevents["$event[eventid]"]))
		{
			if ($insertsql)
			{
				$insertsql .= ',';
			}
			$insertsql .= "($destinfo[userid], $event[eventid])";
		}
	}
	if ($insertsql)
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "subscribeevent
				(userid, eventid)
			VALUES
				$insertsql
		");
	}

	// Merge relevant data in the user table
	// Went overboard with TRIM() to be safe, :)
	// The outside trim is to make sure we get rid of the middle ' ' if we are merging an empty list
	// It is ok to have duplicate ids in the buddy/ignore lists
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user
		SET posts = posts + $sourceinfo[posts],
		reputation = reputation + $sourceinfo[reputation],
		lastvisit = IF(lastvisit < $sourceinfo[lastvisit], $sourceinfo[lastvisit], lastvisit),
		lastactivity = IF(lastactivity < $sourceinfo[lastactivity], $sourceinfo[lastactivity], lastactivity),
		lastpost = IF(lastpost < $sourceinfo[lastpost], $sourceinfo[lastpost], lastpost),
		pmtotal = pmtotal + $sourceinfo[pmtotal],
		pmunread = pmunread + $sourceinfo[pmunread],
		joindate = IF(joindate > $sourceinfo[joindate], $sourceinfo[joindate], joindate)
		WHERE userid = $destinfo[userid]
	");

	// Update announcements
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "announcement
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// Update Attachments
	if ($vboptions['attachfile'])
	{
		$attachments = $DB_site->query("
			SELECT attachmentid
			FROM " . TABLE_PREFIX . "attachment
			WHERE userid = $sourceinfo[userid]
		");

		require_once('./includes/functions_file.php');
		while ($attachment = $DB_site->fetch_array($attachments))
		{
			$sourcefile = fetch_attachment_path($sourceinfo['userid'], $attachment['attachmentid']);
			if ($destfile = verify_attachment_path($destinfo['userid'], $attachment['attachmentid']))
			{
				copy($sourcefile, $destfile);
				$sourcethumb = fetch_attachment_path($sourceinfo['userid'], $attachment['attachmentid'], true);
				$destthumb = fetch_attachment_path($destinfo['userid'], $attachment['attachmentid'], true);
				@copy($sourcethumb, $destthumb);
			}
			@unlink($sourcefile);
			@unlink($sourcethumb);
		}
	}
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "attachment
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// Update Posts
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "post
		SET userid = $destinfo[userid],
		username = '" . addslashes($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	// Update Threads
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "thread
		SET postuserid = $destinfo[userid],
		postusername = '" . addslashes($destinfo['username']) . "'
		WHERE postuserid = $sourceinfo[userid]
	");

	// Update Deletion Log
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "deletionlog
		SET userid = $destinfo[userid],
		username = '" . addslashes($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	// Update Edit Log
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "editlog
		SET userid = $destinfo[userid],
		username = '" . addslashes($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	// Update Poll Votes
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "pollvote
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// Update Thread Ratings
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "threadrate
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// Update User Notes
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "usernote
		SET posterid = $destinfo[userid]
		WHERE posterid = $sourceinfo[userid]
	");
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "usernote
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// Update Calendar Events
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "event
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// Update Reputation Details
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "reputation
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// Update Private Messages
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "pm
		SET userid = $destinfo[userid], folderid = 0
		WHERE userid = $sourceinfo[userid]
	");

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "pmreceipt
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "pmreceipt
		SET touserid = $destinfo[userid],
		tousername = '" . addslashes($destinfo['username']) . "'
		WHERE touserid = $sourceinfo[userid]
	");

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "pmtext
		SET fromuserid = $destinfo[userid],
		fromusername = '" . addslashes($destinfo['username']) . "'
		WHERE fromuserid = $sourceinfo[userid]
	");

	$olduser = strlen($sourceinfo['username']);
	$newuser = strlen($destinfo['username']);
	$DB_site->query("UPDATE " . TABLE_PREFIX . "pmtext
		SET touserarray = REPLACE(touserarray, 'i:$sourceinfo[userid];s:$olduser:\"" . addslashes($sourceinfo['username']) . "\";','i:$destinfo[userid];s:$newuser:\"" . addslashes($destinfo['username']) . "\";')
	");

	// Update ignorelist, buddylist
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "usertextfield SET
		ignorelist = CONCAT(ignorelist, '$sourceinfo[ignorelist]'),
		buddylist = CONCAT(buddylist, '$sourceinfo[buddylist]')
		WHERE userid = $destinfo[userid]
	");

	// first check to see if a subscription exists
	$paidsubscriptions = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "subscriptionlog WHERE userid = $sourceinfo[userid]");
	while ($paidsubscription = $DB_site->fetch_array($paidsubscriptions))
	{
		if ($current = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "subscriptionlog WHERE userid = $destinfo[userid] AND subscriptionid = $paidsubscription[subscriptionid]"))
		{ // already exists
			if ($current['expirydate'] < $paidsubscription['expirydate'])
			{
				$DB_site->query("UPDATE " . TABLE_PREFIX . "subscriptionlog SET expirydate = $paidsubscription[expirydate] WHERE userid = $destinfo[userid] AND subscriptionid = $paidsubscription[subscriptionid]");
			}
		}
		else
		{ // its expired so lets subscribe them as normal
			require_once('./includes/functions_subscriptions.php');
			build_user_subscription($paidsubscription['subscriptionid'], $destinfo['userid'], $paidsubscription['regdate'], $paidsubscription['expirydate']);
		}
	}

	// Remove remnants of source user
	delete_user($sourceinfo['userid']);

	print_stop_message('user_accounts_merged', "$sourceinfo[username]", "$destinfo[username]");

}

// ###################### Start modify Profile Pic ###########
if ($_REQUEST['do'] == 'profilepic')
{
	globalize($_REQUEST, array('userid' => INT));

	$userinfo = fetch_userinfo($userid, 8); // 8 sets profilepic

	print_form_header('usertools', 'updateprofilepic', 1);
	construct_hidden_code('userid', $userinfo['userid']);
	print_table_header($vbphrase['change_profile_picture'] . ": <span class=\"normal\">$userinfo[username]</span>");
	if ($userinfo['profilepic'])
	{
		print_description_row("<div align=\"center\"><img src=\"../image.php?$session[sessionurl]userid=$userinfo[userid]&amp;type=profile&amp;dateline=$userinfo[profilepicdateline]\" alt=\"../image.php?$session[sessionurl]userid=$user[userid]&amp;type=profile\" title=\"$userinfo[username]'s Profile Picture\" /></div>");
		print_yes_no_row($vbphrase['use_profile_picture'], 'useprofilepic', 1);
	}
	else
	{
		construct_hidden_code('useprofilepic', 1);
	}
	print_input_row($vbphrase['enter_profile_picture_url'], 'profilepicurl', 'http://www.');
	print_upload_row($vbphrase['upload_profile_picture_from_computer'], 'upload');

	print_submit_row($vbphrase['save']);
}

// ###################### Start Update Profile Pic ################
if ($_POST['do'] == 'updateprofilepic')
{

	globalize($_POST, array('userid' => INT, 'useprofilepic' => INT, 'profilepicurl' => STR));

	$bbuserinfo_you = $bbuserinfo;
	$bbuserinfo = fetch_userinfo($userid); // bad...

	if ($useprofilepic)
	{
		require_once('./includes/functions_upload.php');
		process_image_upload('profilepic', $profilepicurl);
	}
	else
	{
		// not using a profilepic
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "customprofilepic WHERE userid = $bbuserinfo[userid]");
	}

	$bbuserinfo = $bbuserinfo_you; // isn't that a lame thing to have to do? Works, but it's not really nice... :/

	define('CP_REDIRECT', "user.php?do=modify&amp;userid=$userid");
	print_stop_message('saved_profile_picture_successfully');
}

// ###################### Start modify Avatar ################
if ($_REQUEST['do'] == 'avatar')
{
	globalize($_REQUEST, array(
		'userid' => INT,
		'perpage' => INT,
		'startpage' => INT,
	));

	$bbuserinfo = fetch_userinfo($userid);
	$avatarchecked[$bbuserinfo['avatarid']] = HTML_CHECKED;
	$nouseavatarchecked = '';
	if (!$avatarinfo = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "customavatar WHERE userid = $userid"))
	{
		// no custom avatar exists
		if (!$bbuserinfo['avatarid'])
		{
			// must have no avatar selected
			$nouseavatarchecked = HTML_CHECKED;
			$avatarchecked[0] = '';
		}
	}
	if ($startpage < 1)
	{
		$startpage = 1;
	}
	if ($perpage < 1)
	{
		$perpage = 25;
	}
	$avatarcount = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "avatar");
	$totalavatars = $avatarcount['count'];
	if (($startpage - 1) * $perpage > $totalavatars)
	{
		if ((($totalavatars / $perpage) - intval($totalavatars / $perpage)) == 0)
		{
			$startpage = $totalavatars / $perpage;
		}
		else
		{
			$startpage = intval($totalavatars / $perpage) + 1;
		}
	}
	$limitlower = ($startpage - 1) * $perpage + 1;
	$limitupper = $startpage * $perpage;
	if ($limitupper > $totalavatars)
	{
		$limitupper = $totalavatars;
		if ($limitlower > $totalavatars)
		{
			$limitlower = $totalavatars - $perpage;
		}
	}
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}
	$avatars = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "avatar ORDER BY title LIMIT " . ($limitlower-1) . ", $perpage");
	$avatarcount = 0;
	if ($totalavatars > 0)
	{
		print_form_header('usertools', 'avatar');
		construct_hidden_code('userid', $userid);
		print_table_header(
			$vbphrase['avatars_to_show_per_page'] .
			': <input type="text" name="perpage" value="' . intval($perpage) . '" size="5" tabindex="1" />
			<input type="submit" class="button" value="' . $vbphrase['go'] . '" tabindex="1" />
		');
		print_table_footer();
	}

	print_form_header('usertools', 'updateavatar', 1);
	print_table_header($vbphrase['avatars']);

	$output = "<table border=\"0\" cellpadding=\"6\" cellspacing=\"1\" class=\"tborder\" align=\"center\" width=\"100%\">";
	while ($avatar = $DB_site->fetch_array($avatars))
	{
		$avatarid = $avatar['avatarid'];
		$avatar['avatarpath'] = iif(substr($avatar['avatarpath'], 0, 7) != 'http://' AND $avatar['avatarpath']{0} != '/', '../', '') . $avatar['avatarpath'];
		if ($avatarcount == 0)
		{
			$output .= '<tr class="' . fetch_row_bgclass() . '">';
		}
		$output .= "<td valign=\"bottom\" align=\"center\" width=\"20%\"><label for=\"av$avatar[avatarid]\"><input type=\"radio\" name=\"avatarid\" id=\"av$avatar[avatarid]\" value=\"$avatar[avatarid]\" tabindex=\"1\" $avatarchecked[$avatarid] />";
		$output .= "<img src=\"$avatar[avatarpath]\" alt=\"\" /><br />$avatar[title]</label></td>";
		$avatarcount++;
		if ($avatarcount == 5)
		{
			echo '</tr>';
			$avatarcount = 0;
		}
	}
	if ($avatarcount != 0)
	{
		while ($avatarcount != 5)
		{
			$output .= '<td>&nbsp;</td>';
			$avatarcount++;
		}
		echo '</tr>';
	}
	if ((($totalavatars / $perpage) - intval($totalavatars / $perpage)) == 0)
	{
		$numpages = $totalavatars / $perpage;
	}
	else
	{
		$numpages = intval($totalavatars / $perpage) + 1;
	}
	if ($startpage == 1)
	{
		$starticon = 0;
		$endicon = $perpage - 1;
	}
	else
	{
		$starticon = ($startpage - 1) * $perpage;
		$endicon = ($perpage * $startpage) - 1 ;
	}
	if ($numpages > 1)
	{
		for ($x = 1; $x <= $numpages; $x++)
		{
			if ($x == $startpage)
			{
				$pagelinks .= " [<b>$x</b>] ";
			}
			else
			{
				$pagelinks .= " <a href=\"usertools.php?startpage=$x&perpage=$perpage&do=avatar&userid=$userid\">$x</a> ";
			}
		}
	}
	if ($startpage != $numpages)
	{
		$nextstart = $startpage + 1;
		$nextpage = " <a href=\"usertools.php?startpage=$nextstart&perpage=$perpage&do=avatar&userid=$userid\">" . $vbphrase['next_page'] . "</a>";
		$eicon = $endicon + 1;
	}
	else
	{
		$eicon = $totalavatars;
	}
	if ($startpage != 1)
	{
		$prevstart = $startpage - 1;
		$prevpage = "<a href=\"usertools.php?startpage=$prevstart&perpage=$perpage&do=avatar&userid=$userid\">" . $vbphrase['prev_page'] . "</a> ";
	}
	$sicon = $starticon + 1;
	if ($totalavatars > 0)
	{
		if ($pagelinks)
		{
			$colspan = 3;
		}
		else
		{
			$colspan = 5;
		}
		$output .= '<tr><td class="thead" align="center" colspan="' . $colspan . '">';
		$output .= construct_phrase($vbphrase['showing_avatars_x_to_y_of_z'], $sicon, $eicon, $totalavatars) . '</td>';
		if ($pagelinks)
		{
			$output .= "<td class=\"thead\" colspan=\"2\" align=\"center\">$vbphrase[page]: <span class=\"normal\">$prevpage $pagelinks $nextpage</span></td>";
		}
		$output .= '</tr>';
	}
	$output .= '</table>';

	if ($totalavatars > 0)
	{
		print_description_row($output);
	}

	if ($nouseavatarchecked)
	{
		print_description_row($vbphrase['user_has_no_avatar']);
	}
	else
	{
		print_yes_row($vbphrase['delete_avatar'], 'avatarid', $vbphrase['yes'], '', -1);
	}
	print_table_break();
	print_table_header($vbphrase['custom_avatar']);

	require_once('./includes/functions_user.php');
	$bbuserinfo['avatarurl'] = fetch_avatar_url($bbuserinfo['userid']);
	if (empty($bbuserinfo['avatarurl']) OR $bbuserinfo['avatarid'] != 0)
	{
		$bbuserinfo['avatarurl'] = '<img src="../' . $vboptions['cleargifurl'] . '" alt="" border="0" />';
	}
	else
	{
		$bbuserinfo['avatarurl'] = "<img src=\"../$bbuserinfo[avatarurl]\" alt=\"\" border=\"0\" />";
	}

	print_yes_row(
		iif($avatarchecked[0] != '',
			$vbphrase['use_current_avatar'] . ' ' . $bbuserinfo['avatarurl'],
			$vbphrase['add_new_custom_avatar']
		)
	, 'avatarid', $vbphrase['yes'], $avatarchecked[0], 0);
	print_input_row($vbphrase['enter_avatar_url'], 'avatarurl', 'http://www.');
	print_upload_row($vbphrase['upload_avatar_from_computer'], 'upload');
	construct_hidden_code('userid', $userid);
	print_submit_row($vbphrase['save']);
}

// ###################### Start Update Avatar ################
if ($_POST['do'] == 'updateavatar')
{

	globalize($_POST, array('userid' => INT, 'avatarid' => INT, 'avatarurl' => STR, 'useavatar' => INT));

	$useavatar = iif($avatarid == -1, 0, 1);

	$bbuserinfo_you = $bbuserinfo;
	$bbuserinfo = fetch_userinfo($userid); // bad...

	if ($useavatar)
	{
		if ($avatarid == 0)
		{
			// custom avatar
			require_once('./includes/functions_upload.php');
			process_image_upload('avatar', $avatarurl);
		}
		else
		{
			// predefined avatar
			// let the admin set the user to have any avatar, so don't include any of the checks
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "customavatar WHERE userid = $bbuserinfo[userid]");
			@unlink("$vboptions[avatarpath]/avatar$bbuserinfo[userid]_$bbuserinfo[avatarrevision].gif");
		}
	}
	else
	{
		// not using an avatar
		$avatarid = 0;
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "customavatar WHERE userid = $bbuserinfo[userid]");
		@unlink("$vboptions[avatarpath]/avatar$bbuserinfo[userid]_$bbuserinfo[avatarrevision].gif");
	}

	$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET avatarid = " . intval($avatarid) . " WHERE userid = $bbuserinfo[userid]");

	$bbuserinfo = $bbuserinfo_you; // isn't that a lame thing to have to do? Works, but it's not really nice... :/

	define('CP_REDIRECT', "user.php?do=modify&amp;userid=$userid");
	print_stop_message('saved_avatar_successfully');
}

// ############################# start user pm stats #########################
if ($_REQUEST['do'] == 'pmfolderstats')
{
	globalize($_REQUEST, array('userid' => INT));

	$user = $DB_site->query_first("
		SELECT user.*, usertextfield.*
		FROM " . TABLE_PREFIX . "user AS user
		INNER JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING(userid)
		WHERE user.userid = $userid
	");

	if (!$user['userid'])
	{
		print_stop_message('invalid_user_specified');
	}

	$foldernames = array('0' => $vboptions['inboxname'], '-1' => $vboptions['sentitemsname']);
	$foldernames = unserialize($user['pmfolders']);
	$foldernames['-1'] = $vbphrase['sent_items'];
	$foldernames['0'] = $vbphrase['inbox'];
	$folders = array();
	$pms = $DB_site->query("
		SELECT COUNT(*) AS messages, folderid
		FROM " . TABLE_PREFIX . "pm
		WHERE userid = $user[userid]
		GROUP BY folderid
	");
	if (!$DB_site->num_rows($pms))
	{
		print_stop_message('no_matches_found');
	}
	while ($pm = $DB_site->fetch_array($pms))
	{
		$pmtotal += $pm['messages'];
		$folders[$foldernames[$pm['folderid']]] = $pm['messages'];
	}

	print_form_header('user', 'edit');
	construct_hidden_code('userid', $user['userid']);
	print_table_header(construct_phrase($vbphrase['private_messages_for_x'], $user['username']) . "</b> (userid: $user[userid])<b>");
	print_cells_row(array($vbphrase['folder'], $vbphrase['number_of_messages']), 1);
	foreach($folders AS $foldername => $messages)
	{
		print_cells_row(array($foldername, $messages));
	}
	print_cells_row(array('<b>' . $vbphrase['total'] . '</b>', "<b>$pmtotal</b>"));
	print_description_row('<div align="center">' . construct_link_code($vbphrase['delete_private_messages'], "usertools.php?$session[sessionurl]do=removepms&amp;userid=$userid") . '</div>', 0, 2, 'thead');
	print_submit_row($vbphrase['edit_user'], 0);

}

// ############################# start PM stats #########################
if ($_REQUEST['do'] == 'pmstats')
{

	$pms = $DB_site->query("
		SELECT COUNT(*) AS total, userid
		FROM " . TABLE_PREFIX . "pm
		GROUP BY userid
		ORDER BY total DESC
	");

	print_form_header('usertools', 'viewpmstats');
	print_table_header($vbphrase['private_message_statistics'], 3);
	print_cells_row(array($vbphrase['number_of_messages'], $vbphrase['number_of_users'], $vbphrase['controls']), 1);

	$groups = array();
	while ($pm = $DB_site->fetch_array($pms))
	{
		$groups[$pm['total']]['total']++;
		$groups[$pm['total']]['ids'] .= $pm['userid'] . ' ';
	}
	foreach ($groups AS $key => $val)
	{
		$val['ids'] = str_replace(' ', ',', trim($val['ids']));
		$cell = array();
		$cell[] = $key . iif($vboptions['pmquota'] , "/$vboptions[pmquota]");
		$cell[] = $val['total'];
		$cell[] = construct_link_code(construct_phrase($vbphrase['list_users_with_x_messages'], $key), "usertools.php?$session[sessionurl]do=pmuserstats&pms=$key&ids=$val[ids]");
		print_cells_row($cell);
	}
	print_table_footer();

}

// ############################# start PM stats #########################
if ($_REQUEST['do'] == 'pmuserstats')
{
	globalize($_REQUEST, array('ids', 'pms' => INT));

	$ids = preg_replace('#[^0-9, ]#', '', $ids);

	if (empty($ids))
	{
		print_stop_message('no_users_matched_your_query');
	}

	$users = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "user WHERE userid IN($ids)");

	// a little javascript for the options menus
	?>
	<script type="text/javascript">
	function js_pm_jump(userid,username)
	{
		value = eval("document.cpform.u" + userid + ".options[document.cpform.u" + userid + ".selectedIndex].value");
		var page = '';
		switch (value)
		{
			case 'pmstats': page = "usertools.php?do=pmfolderstats&userid=" + userid; break;
			case 'profile': page = "user.php?do=edit&userid=" + userid; break;
			case 'pmuser': page = "../private.php?do=newpm&userid=" + userid; break;
			case 'delete': page = "usertools.php?do=removepms&userid=" + userid; break;
		}
		if (page != '')
		{
			window.location = page + "&s=<?php echo $session['sessionhash']; ?>";
		}
		else

		{
			window.location = "mailto:" + value;
		}
	}
	</script>
	<?php

	print_form_header('usertools', '');
	print_table_header(construct_phrase($vbphrase['users_with_x_private_messages_stored'], $pms), 3);
	print_cells_row(array($vbphrase['username'], $vbphrase['last_visit'], $vbphrase['options']), 1);
	while($user = $DB_site->fetch_array($users))
	{
		$cell = array();
		$cell[] = "<a href=\"../member.php?$session[sessionurl]do=getinfo&userid=$user[userid]\" target=\"_blank\">$user[username]</a>";
		$cell[] = vbdate("$vboptions[dateformat], $vboptions[timeformat]", $user['lastvisit']);
		$cell[] = "
		<select name=\"u$user[userid]\" onchange=\"js_pm_jump($user[userid], '$user[username]');\" tabindex=\"1\" class=\"bginput\">
			<option value=\"pmstats\">" . $vbphrase['view_private_message_statistics'] . "</option>
			<option value=\"profile\">" . $vbphrase['edit_user'] . "</option>
			<option value=\"$user[email]\">" . $vbphrase['send_email_to_user'] . "</option>
			<option value=\"pmuser\">" . $vbphrase['send_private_message_to_user'] . "</option>
			<option value=\"delete\">" . construct_phrase($vbphrase['delete_all_users_private_messages']) . "</option>
		</select><input type=\"button\" class=\"button\" value=\"$vbphrase[go]\" onclick=\"js_pm_jump($user[userid], '$user[username]');\" tabindex=\"1\" />\n\t";
		print_cells_row($cell);
	}
	print_table_footer();

}

// ############################# start do ips #########################
if ($_REQUEST['do'] == 'doips')
{
	if (function_exists('set_time_limit') AND get_cfg_var('safe_mode')==0)
	{
		@set_time_limit(0);
	}

	globalize($_REQUEST, array('depth' => INT, 'username' => STR, 'userid' => INT));

	if (empty($depth))
	{
		$depth = 1;
	}

	if ($username)
	{
		$getuserid = $DB_site->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . addslashes(htmlspecialchars_uni($_REQUEST['username'])) . "'
		");
		$userid = intval($getuserid['userid']);
		if (!$userid)
		{
			print_stop_message('invalid_user_specified');
		}
	}
	else if ($userid)
	{
		$userinfo = fetch_userinfo($userid);
		if (!$userinfo)
		{
			print_stop_message('invalid_user_specified');
		}
		$username = unhtmlspecialchars($userinfo['username']);
	}
	else
	{
		$userid = 0;
	}

	if ($_REQUEST['ipaddress'] OR $userid)
	{

		if ($_REQUEST['ipaddress'])
		{

			$results = construct_ip_usage_table($_REQUEST['ipaddress'], 0, $depth);
			if (!$results)
			{
				print_stop_message('no_matches_found');
			}
			print_form_header('', '');
			print_table_header(construct_phrase($vbphrase['ip_address_search_for_ip_address_x'], htmlspecialchars_uni($_REQUEST['ipaddress'])));
			$hostname = @gethostbyaddr($_REQUEST['ipaddress']);
			if ($hostname == $_REQUEST['ipaddress'])
			{
				$hostname = $vbphrase['could_not_resolve_hostname'];
			}
			print_description_row("<div style=\"margin-left:20px\"><a href=\"usertools.php?$session[sessionurl]do=gethost&amp;ip=$_REQUEST[ipaddress]\">$_REQUEST[ipaddress]</a> : <b>$hostname</b></div>");
			print_description_row($results);
			print_table_footer();
		}

		if ($userid)
		{

			print_form_header('', '');
			print_table_header(construct_phrase($vbphrase['ip_address_search_for_user_x'], htmlspecialchars_uni($username)));
			$results = construct_user_ip_table($userid, 0, $depth);
			if (!$results)
			{
				print_description_row($vbphrase['no_matches_found']);
			}
			else
			{
				print_description_row($results);
			}
			print_table_footer();
		}
	}

	print_form_header('usertools', 'doips');
	print_table_header($vbphrase['search_ip_addresses']);
	print_input_row($vbphrase['find_users_by_ip_address'], 'ipaddress', $_REQUEST['ipaddress']);
	print_input_row($vbphrase['find_ip_addresses_for_user'], 'username', $username);
	print_select_row($vbphrase['depth_to_search'], 'depth', array(1 => 1, 2 => 2), $depth);
	print_submit_row($vbphrase['find']);
}

// ############################# start gethost #########################
if ($_REQUEST['do'] == 'gethost')
{
	globalize($_REQUEST, array('ip' => STR_NOHTML));

	print_form_header('', '');
	print_table_header($vbphrase['ip_address']);
	print_label_row($vbphrase['ip_address'], $ip);
	$resolvedip = @gethostbyaddr($ip);
	if ($resolvedip == $ip)
	{
		print_label_row($vbphrase['host_name'], '<i>' . $vbphrase['n_a'] . '</i>');
	}
	else
	{
		print_label_row($vbphrase['host_name'], "<b>$resolvedip</b>");
	}
	print_table_footer();
}

// ############################# start referrers #########################
if ($_REQUEST['do'] == 'referrers')
{

	print_form_header('usertools', 'showreferrers');
	print_table_header($vbphrase['referrals']);
	print_description_row($vbphrase['please_input_referral_dates']);
	print_time_row($vbphrase['start_date'], 'startdate', TIMENOW - 24 * 60 * 60 * 31, 1, 0, 'middle');
	print_time_row($vbphrase['end_date'], 'enddate', TIMENOW, 1, 0, 'middle');
	print_submit_row($vbphrase['find']);

}

// ############################# start show referrers #########################
if ($_POST['do'] == 'showreferrers')
{
	globalize($_REQUEST, array('startdate', 'enddate'));

	require_once('./includes/functions_misc.php');
	if ($startdate['month'])
	{
		$startdate = vbmktime(intval($startdate['hour']), intval($startdate['minute']), 0, intval($startdate['month']), intval($startdate['day']), intval($startdate['year']));
		$datequery = "AND users.joindate >= $startdate ";
		$datestart = vbdate("$vboptions[dateformat] $vboptions[timeformat]", $startdate);
	}
	else
	{
		$startdate = 0;
	}

	if ($enddate['month'])
	{
		$enddate = vbmktime(intval($enddate['hour']), intval($enddate['minute']), 0, intval($enddate['month']), intval($enddate['day']), intval($enddate['year']));
		$datequery .= "AND users.joindate <= $enddate";
		$dateend = vbdate("$vboptions[dateformat] $vboptions[timeformat]", $enddate);
	}
	else
	{
		$enddate = 0;
	}

	if ($datestart OR $dateend)
	{
		$refperiod = construct_phrase($vbphrase['x_to_y'], $datestart, $dateend);
	}
	else
	{
		$refperiod = $vbphrase['all_time'];
	}

	$users = $DB_site->query("
		SELECT COUNT(*) AS count, user.username, user.userid FROM " . TABLE_PREFIX . "user AS users
		INNER JOIN " . TABLE_PREFIX . "user AS user ON(users.referrerid = user.userid)
		WHERE users.referrerid <> 0
		$datequery
		GROUP BY users.referrerid
		ORDER BY count DESC
	");
	if (!$DB_site->num_rows($users))
	{
		define('CP_REDIRECT', 'usertools.php?do=referrers');
		print_stop_message('no_referrals_matched_your_query');
	}
	else
	{
		print_form_header('', '');
		print_table_header($vbphrase['referrals'] . ' - ' .	$refperiod);
		print_cells_row(array($vbphrase['username'], $vbphrase['total']), 1);
		while ($user=$DB_site->fetch_array($users))
		{
			print_cells_row(array("<a href=\"usertools.php?$session[sessionurl]do=showreferrals&referrerid=$user[userid]&startdate=$startdate&enddate=$enddate\">$user[username]</a>", vb_number_format($user['count'])));
		}
		print_table_footer();
	}
}

// ############################# start show referrals #########################
if ($_REQUEST['do'] == 'showreferrals')
{
	globalize($_REQUEST, array(
		'startdate' => INT,
		'enddate' => INT,
		'referrerid' => INT)
	);

	if ($startdate)
	{
		$datequery = "AND joindate >= $startdate ";
		$datestart = vbdate("$vboptions[dateformat] $vboptions[timeformat]", $startdate);
	}
	if ($enddate)
	{
		$datequery .= "AND joindate <= $enddate";
		$dateend = vbdate("$vboptions[dateformat] $vboptions[timeformat]", $enddate);
	}

	if ($datestart OR $dateend)
	{
		$refperiod = construct_phrase($vbphrase['x_to_y'], $datestart, $dateend);
	}
	else
	{
		$refperiod = $vbphrase['all_time'];
	}

	$username = $DB_site->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = $referrerid");
	$users = $DB_site->query("
		SELECT username, posts, userid, joindate, lastvisit, email
		FROM " . TABLE_PREFIX . "user
		WHERE referrerid = $referrerid
		$datequery
		ORDER BY joindate DESC
	");

	print_form_header('', '');
	print_table_header(construct_phrase($vbphrase['referrals_for_x'], $username['username']) . ' - ' .	$refperiod, 5);
	print_cells_row(array(
		$vbphrase['username'],
		$vbphrase['post_count'],
		$vbphrase['email'],
		$vbphrase['join_date'],
		$vbphrase['last_visit']
	), 1);

	while($user = $DB_site->fetch_array($users))
	{
		$cell = array();
		$cell[] = "<a href=\"user.php?$session[sessionurl]do=edit&userid=$user[userid]\">$user[username]</a>";
		$cell[] = vb_number_format($user['posts']);
		$cell[] = "<a href=\"mailto:$user[email]\">$user[email]</a>";
		$cell[] = '<span class="smallfont">' . vbdate("$vboptions[dateformat], $vboptions[timeformat]", $user['joindate']) . '</span>';
		$cell[] = '<span class="smallfont">' . vbdate("$vboptions[dateformat], $vboptions[timeformat]", $user['lastvisit']) . '</span>';
		print_cells_row($cell);
	}
	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13174 $
|| ####################################################################
\*======================================================================*/
?>