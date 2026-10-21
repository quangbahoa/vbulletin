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

// ###################### Start fetch_coventry #######################
// gets a list of userids in Coventry. Specify 'string' as your argument
// if you want a comma-separated string rather than an array
function fetch_coventry($returntype = 'array')
{
	global $vboptions, $bbuserinfo;
	static $Coventry;

	if (!isset($Coventry))
	{
		if (trim($vboptions['globalignore']) != '')
		{
			$Coventry = preg_split('#\s+#s', $vboptions['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($Coventry AS $key => $value)
			{
				$Coventry["$key"] = intval($value);
			}
			$bbuserkey = array_search($bbuserinfo['userid'], $Coventry);
			if ($bbuserkey !== FALSE AND $bbuserkey !== NULL)
			{
				unset($Coventry["$bbuserkey"]);
			}
		}
		else
		{
			$Coventry = array();
		}
	}

	if ($returntype === 'array')
	{
		// return array
		return $Coventry;
	}
	else
	{
		// return comma-separated string
		return implode(',', $Coventry);
	}
}

// ###################### Start getOnlineStatus #######################
// work out if bbuser can see online status of user
// also puts in + and * symbols as $user[buddymark] and $user[invisiblemark]
function fetch_online_status(&$user, $setstatusimage = false)
{
	global $bbuserinfo, $permissions, $vboptions, $stylevar, $vbphrase;
	static $buddylist, $datecut;

	// get variables used by this function
	if (!is_array($buddylist))
	{
		$datecut = TIMENOW - $vboptions['cookietimeout'];

		if ($bbuserinfo['buddylist'] = trim($bbuserinfo['buddylist']))
		{
			$buddylist = preg_split('/\s+/', $bbuserinfo['buddylist'], -1, PREG_SPLIT_NO_EMPTY);
		}
		else
		{
			$buddylist = array();
		}
	}

	// is the user on bbuser's buddylist?
	if (in_array($user['userid'], $buddylist))
	{
		$user['buddymark'] = '+';
	}
	else
	{
		$user['buddymark'] = '';
	}

	// set the invisible mark to nothing by default
	$user['invisiblemark'] = '';

	$onlinestatus = 0;
	// now decide if we can see the user or not
	if ($user['lastactivity'] > $datecut AND $user['lastvisit'] != $user['lastactivity'])
	{
		if ($user['invisible'])
		{
			if (($permissions['genericpermissions'] & CANSEEHIDDEN) OR $user['userid'] == $bbuserinfo['userid'])
			{
				// user is online and invisible BUT bbuser can see them
				$user['invisiblemark'] = '*';
				$onlinestatus = 2;
			}
		}
		else
		{
			// user is online and visible
			$onlinestatus = 1;
		}
	}

	if ($setstatusimage)
	{
		eval('$user[\'onlinestatus\'] = "' . fetch_template('postbit_onlinestatus') . '";');
	}

	return $onlinestatus;
}

// ###################### Start getforumrules #######################
function construct_forum_rules($foruminfo, $permissions)
{
	// array of foruminfo and permissions for this forum
	global $vboptions, $forumrules, $session, $stylevar, $vbphrase, $vbcollapse, $show;

	$bbcodeon = iif($foruminfo['allowbbcode'], $vbphrase['on'], $vbphrase['off']);
	$imgcodeon = iif($foruminfo['allowimages'], $vbphrase['on'], $vbphrase['off']);
	$htmlcodeon = iif($foruminfo['allowhtml'], $vbphrase['on'], $vbphrase['off']);
	$smilieson = iif($foruminfo['allowsmilies'], $vbphrase['on'], $vbphrase['off']);

	$can['postnew'] = (($permissions & CANPOSTNEW) AND $foruminfo['allowposting']);
	$can['replyown'] = (($permissions & CANREPLYOWN) AND $foruminfo['allowposting']);
	$can['replyothers'] = (($permissions & CANREPLYOTHERS) AND $foruminfo['allowposting']);
	$can['editpost'] = $permissions & CANEDITPOST;
	$can['postattachment'] = (($permissions & CANPOSTATTACHMENT) AND $foruminfo['allowposting']);

	$notword = $vbphrase['not'];
	$rules['postnew'] = iif($can['postnew'], '', $notword);
	$rules['postreply'] = iif($can['replyown'] OR $can['replyothers'], '', $notword);
	$rules['edit'] = iif($can['editpost'], '', $notword);
	$rules['attachment'] = iif(($can['postattachment']) AND ($can['postnew'] OR $can['replyown'] OR $can['replyothers']), '', $notword);

	eval('$forumrules = "' . fetch_template('forumrules') . '";');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13432 $
|| ####################################################################
\*======================================================================*/
?>