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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'reputation');
define('VB_ERROR_LITE', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('reputation');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'reputation',
	'reputation_adjust',
	'reputation_reasonbits',
	'reputation_yourpost',
	'reputationbit',
	'STANDARD_ERROR_LITE'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_reputation.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if ($_REQUEST['do'] == 'close')
{
	$show['closewindow'] = true;
	$reputationbit = '';
	eval('print_output("' . fetch_template('reputation') . '");');
}
else
{
	$show['closewindow'] = false;
}

if (!$vboptions['reputationenable'])
{
	eval(print_standard_error('error_reputationdisabled'));
}

$postid = verify_id('post', $_REQUEST['postid']);
if (!$threadid)
{ // Should be set in global.php
	$getthread = $DB_site->query_first("SELECT threadid FROM " . TABLE_PREFIX . "post WHERE postid='$postid'");
	$threadid = $getthread['threadid'];
}
$thread = verify_id('thread', $threadid,1,1);

if (!$thread['visible'])
{
	$idname = $vbphrase['thread'];
	eval(print_standard_error('error_invalidid'));
}
$foruminfo = fetch_foruminfo($thread['forumid']);
$forumperms = fetch_permissions($thread['forumid']);
if (!($forumperms & CANVIEW))
{
	print_no_permission();
}
if (!($forumperms & CANVIEWOTHERS) AND ($thread['postuserid'] != $bbuserinfo['userid'] OR $bbuserinfo['userid'] == 0))
{
	print_no_permission();
}

if ((!($permissions['genericpermissions'] & CANUSEREP) AND $bbuserinfo['userid'] != $postinfo['userid']) OR !$bbuserinfo['userid'])
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$userid = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "post WHERE postid = $postid");
$userinfo = fetch_userinfo($userid['userid']);
$userid = $userinfo['userid'];

if ($usergroupcache["$userinfo[usergroupid]"]['genericoptions'] & ISBANNEDGROUP)
{
	eval(print_standard_error('error_reputationbanned'));
}

if (!$userid)
{
	$idname = $vbphrase['user'];
	eval(print_standard_error('error_invalidid'));
}


if ($_POST['do'] == 'addreputation')
{  // adjust reputation ratings

	globalize($_POST, array('reputation', 'reason'));

	if ($userid == $bbuserinfo['userid'])
	{
		eval(print_standard_error('error_reputationownpost'));
	}

	$score = fetch_reppower($bbuserinfo, $permissions, $reputation);

	// Check if the user has already reputation this post
	if ($repeat = $DB_site->query_first("
		SELECT postid
		FROM " . TABLE_PREFIX . "reputation
		WHERE postid = $postid AND
			whoadded = $bbuserinfo[userid]
	"))
	{
		eval(print_standard_error('error_reputationsamepost'));
	}

	if (!($permissions['adminpermissions'] & CANCONTROLPANEL))
	{
		if ($vboptions['maxreputationperday'] >= $vboptions['reputationrepeat'])
		{
			$klimit = ($vboptions['maxreputationperday'] + 1);
		}
		else
		{
			$klimit = ($vboptions['reputationrepeat'] + 1);
		}
		$checks = $DB_site->query("
			SELECT userid, dateline
			FROM " . TABLE_PREFIX . "reputation
			WHERE whoadded = $bbuserinfo[userid]
			ORDER BY dateline DESC
			LIMIT 0, $klimit
		");

		$i = 0;
		while ($check = $DB_site->fetch_array($checks))
		{
			if (($i < $vboptions['reputationrepeat']) AND ($check['userid'] == $userid))
			{
				eval(print_standard_error('error_reputationsameuser'));
			}
			if (($i + 1) == $vboptions['maxreputationperday'] AND (($check['dateline'] + 86400) > TIMENOW))
			{
				eval(print_standard_error('error_reputationtoomany'));

			}
			$i++;
		}
	}

	$userinfo['reputation'] += $score;

	// Determine this user's reputationlevelid.
	$reputationlevel = $DB_site->query_first("
		SELECT reputationlevelid
		FROM " . TABLE_PREFIX . "reputationlevel
		WHERE $userinfo[reputation] >= minimumreputation
		ORDER BY minimumreputation
		DESC LIMIT 1
	");
	$reputationlevelid = intval($reputationlevel['reputationlevelid']);

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user
		SET	reputation = $userinfo[reputation],
			reputationlevelid = $reputationlevelid
		WHERE userid = $userinfo[userid]
	");

	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "reputation (postid, reputation, userid, whoadded, reason, dateline)
		VALUES ($postid, $score, $userid, $bbuserinfo[userid], '" . addslashes(fetch_censored_text($reason)) . "','" . TIMENOW . "')
	");

	$url = "reputation.php?$session[sessionurl]do=close&amp;p=$postid";
	eval(print_standard_redirect('redirect_reputationadd'));
	// redirect or close window here
}
else
{
	if ($bbuserinfo['userid'] == $userid)
	{ // is this your own post?

		if ($postreputations = $DB_site->query("
			SELECT reputation, reason
			FROM " . TABLE_PREFIX . "reputation
			WHERE postid = $postid
			ORDER BY dateline DESC
		"))
		{

			require_once('./includes/functions_bbcodeparse.php');
			while ($postreputation = $DB_site->fetch_array($postreputations))
			{
				$total += $postreputation['reputation'];
				if(strlen($postreputation['reason']) > 0)
				{
					if ($postreputation['reputation'] > 0)
					{
						$posneg = 'pos';
					}
					else if ($postreputation['reputation'] < 0)
					{
						$posneg = 'neg';
					}
					else
					{
						$posneg = 'balance';
					}
					$reason = parse_bbcode($postreputation['reason']);
					exec_switch_bg();
					eval('$reputation_reasonbits .= "' . fetch_template('reputation_reasonbits') . '";');
				}
			}

			if ($total == 0)
			{
				$reputation = $vbphrase['even'];
			}
			else if ($total > 0 AND $total <= 5)
			{
				$reputation = $vbphrase['somewhat_positive'];
			}
			else if ($total > 5 AND $total <= 15)
			{
				$reputation = $vbphrase['positive'];
			}
			else if ($total > 15 AND $total <= 25)
			{
				$reputation = $vbphrase['very_positive'];
			}
			else if ($total > 25)
			{
				$reputation = $vbphrase['extremely_positive'];
			}
			else if ($total < 0 AND $total >= -5)
			{
				$reputation = $vbphrase['somewhat_negative'];
			}
			else if ($total < -5 AND $total >= -15)
			{
				$reputation = $vbphrase['negative'];
			}
			else if ($total < -15 AND $total >= -25)
			{
				$reputation = $vbphrase['very_negative'];
			}
			else if ($total < -25)
			{
				$reputation = $vbphrase['extremely_negative'];
			}
		}
		else
		{
			$reputation = $vbphrase['even'];
		}

		eval('$reputationbit = "' . fetch_template('reputation_yourpost') . '";');

	}
	else
	{  // Not Your Post

		if ($repeat = $DB_site->query_first("
			SELECT postid
			FROM " . TABLE_PREFIX . "reputation
			WHERE postid = $postid AND
				whoadded= $bbuserinfo[userid]
			"))
		{
			eval(print_standard_error('error_reputationsamepost'));
		}

		if (!($permissions['adminpermissions'] & CANCONTROLPANEL))
		{
			if ($vboptions['maxreputationperday'] >= $vboptions['reputationrepeat'])
			{
				$klimit = ($vboptions['maxreputationperday'] + 1);
			}
			else
			{
				$klimit = ($vboptions['reputationrepeat'] + 1);
			}
			$checks = $DB_site->query("
				SELECT userid,dateline
				FROM " . TABLE_PREFIX . "reputation
				WHERE whoadded = $bbuserinfo[userid]
				ORDER BY dateline DESC
				LIMIT 0,$klimit
			");

			$i = 0;
			while ($check = $DB_site->fetch_array($checks))
			{
				if (($i < $vboptions['reputationrepeat']) AND ($check['userid'] == $userid))
				{
					eval(print_standard_error('error_reputationsameuser'));
				}
				if (($i + 1) == $vboptions['maxreputationperday'] AND (($check['dateline'] + 86400) > TIMENOW))
				{
					eval(print_standard_error('error_reputationtoomany'));
				}
				$i++;
			}
		}

		$show['negativerep'] = iif($permissions['genericpermissions'] & CANNEGATIVEREP, true, false);

		eval('$reputationbit = "' . fetch_template('reputationbit') . '";');
	}
	eval('print_output("' . fetch_template('reputation') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9645 $
|| ####################################################################
\*======================================================================*/
?>