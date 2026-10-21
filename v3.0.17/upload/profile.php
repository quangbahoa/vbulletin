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
define('GET_EDIT_TEMPLATES', 'editsignature,updatesignature');
define('THIS_SCRIPT', 'profile');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user', 'timezone', 'posting');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'banemail',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'USERCP_SHELL',
	'usercp_nav_folderbit'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'editprofile' => array(
		'modifyprofile',
		'modifyprofile_birthday',
		'userfield_checkbox_option',
		'userfield_optional_input',
		'userfield_radio',
		'userfield_radio_option',
		'userfield_select',
		'userfield_select_option',
		'userfield_select_multiple',
		'userfield_textarea',
		'userfield_textbox',
	),
	'editoptions' => array(
		'modifyoptions',
		'modifyoptions_timezone',
		'userfield_checkbox_option',
		'userfield_optional_input',
		'userfield_radio',
		'userfield_radio_option',
		'userfield_select',
		'userfield_select_option',
		'userfield_select_multiple',
		'userfield_textarea',
		'userfield_textbox',
	),
	'editavatar' => array(
		'modifyavatar',
		'help_avatars_row',
		'modifyavatar_category',
		'modifyavatarbit',
		'modifyavatarbit_custom',
		'modifyavatarbit_noavatar',
		'modifyavatar_custom'
	),
	'editlist' => array(
		'modifylist',
		'modifylistbit'
	),
	'editusergroups' => array(
		'modifyusergroups',
		'modifyusergroups_joinrequestbit',
		'modifyusergroups_memberbit',
		'modifyusergroups_nonmemberbit',
		'modifyusergroups_displaybit',
		'modifyusergroups_groupleader',
	),
	'editsignature' => array(
		'modifysignature',
		'forumrules'
	),
	'updatesignature' => array(
		'modifysignature',
		'forumrules'
	),
	'editpassword' => array(
		'modifypassword'
	),
	'editprofilepic' => array(
		'modifyprofilepic'
	),
	'joingroup' => array(
		'modifyusergroups_requesttojoin',
		'modifyusergroups_groupleader'
	),
	'editattachments' => array(
		'GENERIC_SHELL',
		'modifyattachmentsbit',
		'modifyattachments'
	),
	'addlist' => array(
		'modifylist_adduser',
	),
	'removelist' => array(
		'modifylist_removeuser',
	),
);

$actiontemplates['none'] = &$actiontemplates['editprofile'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_user.php');
require_once('./includes/functions_register.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'editprofile';
}

if (!($permissions['forumpermissions'] & CANVIEW))
{
	print_no_permission();
}

if (empty($bbuserinfo['userid']))
{
	print_no_permission();
}

// set shell template name
$shelltemplatename = 'USERCP_SHELL';

// initialise onload event
$onload = '';

// start the navbar
$navbits = array("usercp.php?$session[sessionurl]" => $vbphrase['user_control_panel']);

// ############################### start dst autodetect switch ###############################
if ($_POST['do'] == 'dst')
{
	if ($bbuserinfo['dstauto'])
	{
		switch ($bbuserinfo['dstonoff'])
		{
			case 1:
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "user SET
					options = options - $_USEROPTIONS[dstonoff]
					WHERE userid = $bbuserinfo[userid]
						AND (options & $_USEROPTIONS[dstonoff])
				");
				break;
			case 0:
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "user SET
					options = options + $_USEROPTIONS[dstonoff]
					WHERE userid = $bbuserinfo[userid]
						AND !(options & $_USEROPTIONS[dstonoff])
				");
				break;
		}
	}
	//$url = "usercp.php?$session[sessionurl]";
	eval(print_standard_redirect('redirect_dst'));
}

// ############################################################################
// ############################### EDIT PASSWORD ##############################
// ############################################################################

if ($_REQUEST['do'] == 'editpassword')
{

	// draw cp nav bar
	construct_usercp_nav('password');

	// check for password history retention
	$passwordhistory = $permissions['passwordhistory'];

	$navbits[''] = $vbphrase['edit_email_and_password'];

	// show Optional because password expired
	$show['password_optional'] = !$show['passwordexpired'];
	$templatename = 'modifypassword';
}

// ############################### start update password ###############################
if ($_POST['do'] == 'updatepassword')
{

	globalize($_POST, array('currentpassword' => STR, 'currentpassword_md5' => STR, 'newpassword' => STR, 'newpasswordconfirm' => STR, 'newpassword_md5' => STR, 'newpasswordconfirm_md5' => STR, 'email' => STR, 'emailconfirm' => STR));

	// validate old password
	if (strlen($currentpassword_md5) == 32)
	{
		if (md5($currentpassword_md5 . $bbuserinfo['salt']) != $bbuserinfo['password'])
		{
			eval(print_standard_error('badpassword'));
		}
	}
	else if (md5(md5($currentpassword) . $bbuserinfo['salt']) != $bbuserinfo['password'])
	{
		eval(print_standard_error('badpassword'));
	}

	if ($newpassword != $newpasswordconfirm OR (strlen($newpassword_md5) == 32 AND $newpassword_md5 != $newpasswordconfirm_md5))
	{
		eval(print_standard_error('passwordmismatch'));
	}

	if (!empty($newpassword) OR !empty($newpassword_md5))
	{
		if (strlen($newpassword_md5) == 32)
		{
			$newpassword = md5($newpassword_md5 . $bbuserinfo['salt']);
		}
		else
		{
			$newpassword = md5(md5($newpassword) . $bbuserinfo['salt']);
		}

		// delete old password history
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "passwordhistory WHERE userid=$bbuserinfo[userid] AND passworddate <= FROM_UNIXTIME(" . (TIMENOW - $permissions['passwordhistory'] * 86400) . ")");

		// check to see if the new password is invalid due to previous use
		if ($permissions['passwordhistory'] AND $historycheck = $DB_site->query_first("SELECT UNIX_TIMESTAMP(passworddate) AS passworddate FROM " . TABLE_PREFIX . "passwordhistory WHERE userid=$bbuserinfo[userid] AND password = '" . addslashes($newpassword) . "'"))
		{
			eval(print_standard_error('passwordhistory'));
		}

	}

	// check to see if email address has changed
	if ($email != $bbuserinfo['email'])
	{
		if (!$vboptions['allowkeepbannedemail'] OR $bbuserinfo['email'] != $email)
		{
			if (is_banned_email($email))
			{
				eval(print_standard_error('error_banemail'));
			}
		}

		if ($email == '' OR $emailconfirm == '')
		{
			eval(print_standard_error('error_fieldmissing'));
		}

		if ($vboptions['requireuniqueemail'] AND $bbuserinfo['email'] != $email AND $checkuser = $DB_site->query_first("SELECT userid,username,email FROM " . TABLE_PREFIX . "user WHERE email = '" . addslashes($email) . "' AND userid <> $bbuserinfo[userid]"))
		{
			if ($checkuser['userid'] != $bbuserinfo['userid'])
			{
				eval(print_standard_error('error_emailtaken'));
			}
		}

		if ($email != $emailconfirm)
		{
			eval(print_standard_error('error_emailmismatch'));
		}

		// check valid email address
		if (!is_valid_email($email))
		{
			eval(print_standard_error('error_bademail'));
		}

		// *** EMAIL CHANGE SECTION ***
		if ($vboptions['verifyemail'] AND $email != $bbuserinfo['email'] AND !can_moderate())
		{
			$newemailaddress = 1;
			$_REQUEST['forceredirect'] = 1;

			// wait lets check if we have an entry first!
			$activation_exists = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "useractivation WHERE userid = $bbuserinfo[userid] AND type = 0");

			if (!empty($activation_exists['usergroupid']))
			{
				$usergroupid = $activation_exists['usergroupid'];
			}
			else
			{
				$usergroupid = $bbuserinfo['usergroupid'];
			}
			$activateid = build_user_activation_id($bbuserinfo['userid'], $usergroupid, 0);

			$username = unhtmlspecialchars($bbuserinfo['username']);
			$userid = $bbuserinfo['userid'];

			eval(fetch_email_phrases('activateaccount_change'));
			vbmail($email, $subject, $message, true);
			$bbuserinfo['usergroupid'] = 3;
		}
		else
		{
			$newemailaddress = 0;
		}

		$newemail = 'email = "' . addslashes($email) . '" ,' ;
	}
	else
	{
		$newemail = '';
	}

	if (!empty($newpassword))
	{
		// insert record into password history
		$DB_site->query("INSERT INTO " . TABLE_PREFIX . "passwordhistory (userid, password, passworddate) VALUES ($bbuserinfo[userid], '" . addslashes($newpassword) . "', NOW())");

		$newpassword = "password = '" . addslashes($newpassword) . "', passworddate = NOW(),";
	}	else {
		$newpassword = '';
	}

	if ($newpassword OR $newemail)
	{
		$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET $newpassword $newemail usergroupid = " . intval($bbuserinfo['usergroupid']) . " WHERE userid = $bbuserinfo[userid]");
	}

	if ($newemailaddress)
	{
		$url = "usercp.php?$session[sessionurl]";
		eval(print_standard_redirect('redirect_updatethanks_newemail'));
	}
	else
	{
		$url = "usercp.php?$session[sessionurl]";
		eval(print_standard_redirect('redirect_updatethanks'));
	}

}

// ############################################################################
// ######################### EDIT BUDDY/IGNORE LISTS ##########################
// ############################################################################
if ($_REQUEST['do'] == 'editlist')
{

	$userlist = 'buddy';

	// get buddy ids
	if ($bbuserinfo['buddylist'])
	{
		$buddy_ids = explode(' ', trim($bbuserinfo['buddylist']));
	}
	else
	{
		$buddy_ids = array();
	}
	// get ignore ids
	if ($bbuserinfo['ignorelist'])
	{
		$ignore_ids = explode(' ', trim($bbuserinfo['ignorelist']));
	}
	else
	{
		$ignore_ids = array();
	}
	// get merged list
	$bothlists = array_merge($buddy_ids, $ignore_ids);

	$listusers = array();
	if (!empty($bothlists))
	{
		$users = $DB_site->query("
			SELECT userid, username FROM " . TABLE_PREFIX . "user
			WHERE userid IN (" . implode(',', $bothlists) . ")
			ORDER BY username
		");
		while ($userinfo = $DB_site->fetch_array($users))
		{
			if ($userinfo['userid'] != 0)
			{
				if (in_array($userinfo['userid'], $buddy_ids))
				{
					$listusers['buddy']["$userinfo[userid]"] = $userinfo['username'];
				}
				if (in_array($userinfo['userid'], $ignore_ids))
				{
					$listusers['ignore']["$userinfo[userid]"] = $userinfo['username'];
				}
			}
		}
	}

	$buddy_listbits = '';
	if (!empty($listusers['buddy']))
	{
		foreach ($listusers['buddy'] AS $userid => $username)
		{
			eval('$buddy_listbits .= "' . fetch_template('modifylistbit') . '";');
		}
	}

	$ignore_listbits = '';
	if (!empty($listusers['ignore']))
	{
		foreach ($listusers['ignore'] AS $userid => $username)
		{
			eval('$ignore_listbits .= "' . fetch_template('modifylistbit') . '";');
		}
	}

	// draw cp nav bar
	construct_usercp_nav('buddylist');

	$navbits[''] = $vbphrase['buddy_ignore_lists'];
	$templatename = 'modifylist';
}

// ############################### start update list ###############################
if ($_POST['do'] == 'updatelist')
{

	globalize($_POST, array('userlist' => STR, 'listbits'));

	if ($userlist != 'buddy')
	{
		$userlist = 'ignore';
	}
	$var = $userlist . 'list';

	// cache exiting list user ids
	unset($useridcache);
	$ids = str_replace(' ', ',', trim($bbuserinfo["$var"]));
	if ($ids != '')
	{
		$users = $DB_site->query("
			SELECT username, usergroupid, user.userid, moderator.userid as moduserid
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON(user.userid = moderator.userid)
			WHERE user.userid IN($ids)
		");
		while ($user = $DB_site->fetch_array($users))
		{
			$user['username'] = strtolower($user['username']);
			$useridcache["$user[username]"] = $user;
		}
	}

	$listids = '';
	foreach ($listbits AS $key => $val)
	{
		$val = addslashes(htmlspecialchars_uni(trim(strtolower($val))));
		if (!empty($val))
		{
			if (!is_array($useridcache["$val"]))
			{
				if ($userid = $DB_site->query_first("
					SELECT userid, username, usergroupid, membergroupids
					FROM " . TABLE_PREFIX . "user AS user
					WHERE username = '$val'
				"))
				{
					$useridcache["$val"] = $userid;
				}
			}
			else
			{
				$userid = $useridcache["$val"];
			}
			if ($userid['userid'])
			{
				$uglist = $userid['usergroupid'] . iif(trim($userid['membergroupids']), ",$userid[membergroupids]");
				if ($var == 'ignorelist' AND !$vboptions['ignoremods'] AND can_moderate(0, '', $userid['userid'], $uglist) AND !($permissions['adminpermissions'] & CANCONTROLPANEL))
				{
					$username = $userid['username'];
					eval(print_standard_error('error_listignoreuser'));
				}
				else if ($bbuserinfo['userid'] == $userid['userid'])
				{
					eval(print_standard_error("cantlistself_$userlist"));
				}
				else
				{
					if (empty($done["$userid[userid]"]))
					{
						$listids .= " $userid[userid]";
						$done["$userid[userid]"] = 1;
					}
				}
			}
			else
			{
				$username = $val;
				eval(print_standard_error('error_listbaduser'));
			}
		}
	}

	$listids = trim($listids);

	require_once('./includes/functions_databuild.php');
	build_usertextfields($var, $listids);

	if (is_array($printdebug))
	{
		foreach ($printdebug AS $line => $text)
		{
			$out .= $text;
		}
	}

	//$url = "usercp.php?$session[sessionurl]";
	eval(print_standard_redirect("updatelist_$userlist"));

}

// ############################### start remove from list ###############################
if ($_REQUEST['do'] == 'removelist')
{

	globalize($_REQUEST, array('userid' => INT, 'userlist' => STR));

	if ($userlist != 'buddy')
	{
		$userlist = 'ignore';
		$show['buddylist'] = false;
	}
	else
	{
		$show['buddylist'] = true;
	}
	$var = $userlist . 'list';

	$userinfo = verify_id('user', $userid, 1, 1);

	$list = explode(' ', trim($bbuserinfo["$var"]));
	if (!in_array($userid, $list))
	{
		eval(print_standard_error('error_notonlist'));
	}
	else
	{
		// start the navbar
		$navbits = array("usercp.php?$session[sessionurl]" => $vbphrase['user_control_panel']);
		$navbits[''] = $vbphrase['buddy_ignore_lists'];
		// make navbar
		$navbits = construct_navbits($navbits);
		eval('$navbar = "' . fetch_template('navbar') . '";');

		construct_usercp_nav('buddylist');
		eval('$HTML = "' . fetch_template('modifylist_removeuser') . '";');
		eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
	}

}

// ############################### start do remove from list ###############################
if ($_POST['do'] == 'doremovelist')
{
	globalize($_POST, array('userlist' => STR, 'userid' => INT));

	if ($userlist != 'buddy')
	{
		$userlist = 'ignore';
	}
	$var = $userlist . 'list';

	$userinfo = verify_id('user', $userid, 1, 1);
	$userid = $userinfo['userid'];

	$splitlist = explode(' ', $bbuserinfo["$var"]);

	foreach ($splitlist AS $key => $val)
	{
		if ($val == $userid)
		{
			unset($splitlist["$key"]);
		}
	}

	$bbuserinfo["$var"] = implode(' ', $splitlist);
	$bbuserinfo["$var"] = trim($bbuserinfo["$var"]);

	require_once('./includes/functions_databuild.php');
	build_usertextfields($var, $bbuserinfo["$var"]);

	eval(print_standard_redirect("removelist_$userlist"));

}

// ############################### start remove from list ###############################
if ($_REQUEST['do'] == 'addlist')
{

	globalize($_REQUEST, array('userid' => INT, 'userlist' => STR));

	if ($userlist != 'buddy')
	{
		$userlist = 'ignore';
		$show['buddylist'] = false;
	}
	else
	{
		$show['buddylist'] = true;
	}
	$var = $userlist . 'list';

	if ($bbuserinfo['userid'] == $userid)
	{
		eval(print_standard_error("cantlistself_$userlist"));
	}

	$userinfo = verify_id('user', $userid, 1, 1);
	$userid = $userinfo['userid'];
	$uglist = $userinfo['usergroupid'] . iif(trim($userinfo['membergroupids']), ",$userinfo[membergroupids]");

	if ($var == 'ignorelist' AND !$vboptions['ignoremods'] AND can_moderate(0, '', $userid, $uglist) AND !($permissions['adminpermissions'] & CANCONTROLPANEL))
	{
		$username = $userinfo['username'];
		eval(print_standard_error('error_listignoreuser'));
	}

	// start the navbar
	$navbits = array("usercp.php?$session[sessionurl]" => $vbphrase['user_control_panel']);
	$navbits[''] = $vbphrase['buddy_ignore_lists'];
	// make navbar
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	construct_usercp_nav('buddylist');
	eval('$HTML = "' . fetch_template('modifylist_adduser') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

}

// ############################### start do add to list ###############################
if ($_POST['do'] == 'doaddlist')
{
	globalize($_POST, array('userid' => INT, 'userlist' => STR));

	if ($userlist != 'buddy')
	{
		$userlist = 'ignore';
	}
	$var = $userlist . 'list';

	if ($bbuserinfo['userid'] == $userid)
	{
		eval(print_standard_error("cantlistself_$userlist"));
	}

	$userinfo = verify_id('user', $userid, 1, 1);
	$userid = $userinfo['userid'];
	$uglist = $userinfo['usergroupid'] . iif(trim($userinfo['membergroupids']), ",$userinfo[membergroupids]");

	if ($var == 'ignorelist' AND !$vboptions['ignoremods'] AND can_moderate(0, '', $userid, $uglist) AND !($permissions['adminpermissions'] & CANCONTROLPANEL))
	{
		$username = $userinfo['username'];
		eval(print_standard_error('error_listignoreuser'));
	}

	$splitlist = explode(' ', $bbuserinfo["$var"]);

	$found = 0;
	foreach ($splitlist AS $key => $val)
	{
		if ($val == $userid)
		{
			$found = 1;
		}
	}
	if (!$found)
	{
		$bbuserinfo["$var"] .= " $userid";
	}
	$bbuserinfo["$var"] = trim($bbuserinfo["$var"]);

	require_once('./includes/functions_databuild.php');
	build_usertextfields($var, $bbuserinfo["$var"]);

	eval(print_standard_redirect("addlist_$userlist"));

}

// ############################################################################
// ALL FUNCTIONS BELOW HERE REQUIRE 'canmodifyprofile' PERMISSION, SO CHECK IT

if (!($permissions['genericpermissions'] & CANMODIFYPROFILE))
{
	print_no_permission();
}

// ############################################################################
// ############################### EDIT PROFILE ###############################
// ############################################################################
if ($_REQUEST['do'] == 'editprofile')
{

	unset($tempcustom);

	exec_switch_bg();
	// Set birthday fields right here!
	if (empty($bbuserinfo['birthday']))
	{
		$daydefaultselected = HTML_SELECTED;
		$monthdefaultselected = HTML_SELECTED;
	}
	else
	{
		$birthday = explode('-', $bbuserinfo['birthday']);
		$dayname = 'day'. $birthday[1] . 'selected';
		$$dayname = HTML_SELECTED;
		$monthname = 'month' . $birthday[0] . 'selected';
		$$monthname = HTML_SELECTED;
		if (date('Y') > $birthday[2] AND $birthday[2] != '0000')
		{
			$year = $birthday[2];
		}
	}

	// custom user title
	if ($permissions['genericpermissions'] & CANUSECUSTOMTITLE)
	{
		exec_switch_bg();
		if ($bbuserinfo['customtitle'] == 2)
		{
			$bbuserinfo['usertitle'] = htmlspecialchars_uni($bbuserinfo['usertitle']);
		}
		$show['customtitleoption'] = true;
	}
	else
	{
		$show['customtitleoption'] = false;
	}

	require_once('./includes/functions_misc.php');
	// Set birthday required or optional
	if (REGOPTION_REQBIRTHDAY & $vboptions['defaultregoptions'])
	{
		$show['birthday_required'] = true;
	}
	else
	{
		$show['birthday_optional'] = true;
	}

	// Get Custom profile fields
	$customfields = array();
	fetch_profilefields(0);

	// draw cp nav bar
	construct_usercp_nav('profile');

	eval('$birthdaybit = "' . fetch_template('modifyprofile_birthday') . '";');
	$navbits[''] = $vbphrase['edit_profile'];
	$templatename = 'modifyprofile';
}

// ############################### start update profile ###############################
if ($_POST['do'] == 'updateprofile')
{

	// start custom censor text (moved from functions.php)
	function fetch_censored_custom_title($text)
	{
		global $vboptions;
		static $ctcensorwords;

		if (empty($ctcensorwords))
		{
			$vboptions['ctCensorWords'] = preg_quote($vboptions['ctCensorWords'], '#');
			$ctcensorwords = preg_split('#\s+#', $vboptions['ctCensorWords'], -1, PREG_SPLIT_NO_EMPTY);
		}


		foreach ($ctcensorwords AS $censorword)
		{
			if (substr($censorword, 0, 2) == '\\{')
			{
				$censorword = substr($censorword, 2, -2);
				$text = preg_replace('#(?<=[^A-Za-z]|^)' . $censorword . '(?=[^A-Za-z]|$)#si', str_repeat($vboptions['censorchar'], strlen($censorword)), $text);
			}
			else

			{
				$text = preg_replace("#$censorword#si", str_repeat($vboptions['censorchar'], strlen($censorword)), $text);
			}
		}

		return $text;
	}
	// end function

	globalize($_POST, array('resettitle' => STR, 'aim' => STR, 'yahoo' => STR, 'icq' => STR, 'msn' => STR, 'coppauser' => INT, 'parentemail' => STR,
							'customtext' => STR, 'day' => INT, 'month' => INT, 'year' => INT, 'homepage' => STR, 'oldbirthday' => STR, 'gotopassword'));

	if (empty($icq))
	{
		$icq = '';
	}
	if (!empty($msn) AND !is_valid_email($msn))
	{
		// error bad msn name
		eval(print_standard_error('error_badmsn'));
	}

	// check coppa things
	if ($coppauser)
	{
		if ($parentemail == '')
		{
			eval(print_standard_error('error_fieldmissing'));
		}

		eval(fetch_email_phrases('parentcoppa'));
		vbmail($parentemail, $subject, $message, true);
	}
	else
	{
		$parentemail = '';
		$coppauser = 0;
	}

	$userfields = verify_profilefields(0);

	// Custom User Title Code!
	if ($permissions['genericpermissions'] & CANUSECUSTOMTITLE)
	{
		if ($resettitle)
		{
			if (empty($usergroupcache["$bbuserinfo[usergroupid]"]['usertitle']))
			{
				$gettitle = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "usertitle WHERE minposts<=$bbuserinfo[posts] ORDER BY minposts DESC LIMIT 1");
				$customtext = $gettitle['title'];
			}
			else
			{
				$customtext = $usergroupcache["$bbuserinfo[usergroupid]"]['usertitle'];
			}
			$customtitle = 0;
		}
		else if ($customtext)
		{
			// 2 signifies that a NON ADMIN set the title and we need to htmlspecialchars when ever displaying the title.
			$customtitle = iif($permissions['adminpermissions'] & CANCONTROLPANEL, 1, 2);
			$customtext = fetch_censored_text($customtext);
			if (!can_moderate() OR (can_moderate() AND !$vboptions['ctCensorMod']))
			{
				$customtext = fetch_censored_custom_title($customtext);
			}
			if ($customtitle == 2) // Non Admin
			{
				if ($vboptions['ctMaxChars'] > 0)
				{
					$customtext = substr($customtext, 0, $vboptions['ctMaxChars']);
				}
				$customtext = fetch_word_wrapped_string($customtext, 25);
			}
		}
		else
		{
			$customtitle = $bbuserinfo['customtitle'];
			$customtext = $bbuserinfo['usertitle'];
		}
	}
	else
	{
		 $customtitle = $bbuserinfo['customtitle'];
		 $customtext = $bbuserinfo['usertitle'];
	}

	// Birthday Stuff...
	require_once('./includes/functions_misc.php');
	require_once('./includes/functions_register.php');
	if (
		($day == -1 AND $month != -1) OR
		($day != -1 AND $month == -1) OR
		(bitwise(REGOPTION_REQBIRTHDAY, $vboptions['defaultregoptions']) AND ($day == -1 OR $month == -1))
	)
	{
		eval(print_standard_error('error_birthdayfield'));
	}

	if (($day == -1) AND ($month == -1))
	{
		$birthday = '';
		$birthday_search = '';
	}
	else
	{
		if ($month < 10 AND $month > 0)
		{
			$month = '0' . $month;
		}
		if ($day < 10 AND $day > 0)
		{
			$day = '0' . $day;
		}
		if (($year > 1901) AND ($year < date('Y')))
		{
			if (checkdate($month, $day, $year))
			{
				$birthday = "$month-$day-$year";
				$birthday_search = "$year-$month-$day";
			}
			else
			{
				eval(print_standard_error('error_birthdayfield'));
			}
		}
		else if ($year >= date('Y'))
		{
			eval(print_standard_error('error_birthdayfield'));
		}
		else
		{
			if (checkdate($month, $day, 1996)) // Allow Feb 29th if the user doesn't specify a year..
			{
				$birthday = "$month-$day-0000";
				$birthday_search = "0000-$month-$day";
			}
			else
			{
				eval(print_standard_error('error_birthdayfield'));
			}
		}
	}
	if ($homepage)
	{
		if (preg_match('#^www\.#si', $homepage))
		{
			$homepage = 'http://' . $homepage;
		}
		else if (!preg_match('#^[a-z0-9]+://#si', $homepage))
		{
			// homepage doesn't match the http://-style format in the beginning -- possible attempted exploit
			$homepage = '';
		}
	}

	$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET
		birthday = '" . addslashes($birthday) . "',
		birthday_search = '". addslashes($birthday_search) . "',
		usertitle = '" . addslashes($customtext) . "',
		customtitle = $customtitle,
		parentemail = '" . addslashes(htmlspecialchars_uni($parentemail)) . "',
		homepage = '" . addslashes(htmlspecialchars_uni($homepage)) . "',
		icq = '" . addslashes(htmlspecialchars_uni($icq)) . "',
		aim = '" . addslashes(htmlspecialchars_uni($aim)) . "',
		yahoo = '" . addslashes(htmlspecialchars_uni($yahoo)) . "',
		msn = '" . addslashes(htmlspecialchars_uni($msn)) . "',
		usergroupid = $bbuserinfo[usergroupid]
		WHERE userid = $bbuserinfo[userid]
	");

	// insert custom user fields
	if (!empty($userfields))
	{
		$DB_site->query("UPDATE " . TABLE_PREFIX . "userfield SET userid=$bbuserinfo[userid]$userfields WHERE userid=$bbuserinfo[userid]");
	}

	if ($vboptions['showbirthdays'] AND $oldbirthday != $birthday)
	{
		require_once('./includes/functions_databuild.php');
		build_birthdays($birthday);
	}

	if (empty($gotopassword))
	{
		$url = "usercp.php?$session[sessionurl]";
	}
	else
	{
		$url = "profile.php?$session[sessionurl]do=editpassword";
	}
	eval(print_standard_redirect('redirect_updatethanks'));

}

// ############################################################################
// ############################### EDIT OPTIONS ###############################
// ############################################################################
if ($_REQUEST['do'] == 'editoptions')
{
	require_once('./includes/functions_misc.php');

	// check the appropriate checkboxes
	$checked = array();
	foreach ($bbuserinfo AS $key => $val)
	{
		if ($val != 0)
		{
			$checked["$key"] = HTML_CHECKED;
		}
		else

		{
			$checked["$key"] = '';
		}
	}

	// invisible option
	$show['invisibleoption'] = iif(bitwise($permissions['genericpermissions'], CANINVISIBLE), true, false);

	// reputation options
	if ($permissions['genericpermissions'] & CANHIDEREP AND $vboptions['reputationenable'])
	{
		if ($bbuserinfo['showreputation'])
		{
			$checked['showreputation'] = HTML_CHECKED;
		}
		$show['reputationoption'] = true;
	}
	else
	{
		$show['reputationoption'] = false;
	}

	// PM options
	$show['pmoptions'] = iif($vboptions['enablepms'] AND $permissions['pmquota'] > 0, true, false);

	// autosubscribe selected option
	$bbuserinfo['autosubscribe'] = verify_subscription_choice($bbuserinfo['autosubscribe'], $bbuserinfo, 9999);
	$emailchecked = array("$bbuserinfo[autosubscribe]" => HTML_SELECTED);

	// threaded mode options
	if ($bbuserinfo['threadedmode'] == 1 OR $bbuserinfo['threadedmode'] == 2)
	{
		$threaddisplaymode["$bbuserinfo[threadedmode]"] = HTML_SELECTED;
	}
	else
	{
		if ($bbuserinfo['postorder'] == 0)
		{
			$threaddisplaymode[0] = HTML_SELECTED;
		}
		else
		{
			$threaddisplaymode[3] = HTML_SELECTED;
		}
	}

	// default days prune
	if ($bbuserinfo['daysprune'] == 0)
	{
		$daysdefaultselected = HTML_SELECTED;
	}
	else
	{
		if ($bbuserinfo['daysprune'] == '-1')
		{
			$bbuserinfo['daysprune'] = 'all';
		}
		$dname = 'days' . $bbuserinfo['daysprune'] . 'selected';
		$$dname = HTML_SELECTED;
	}

	// daylight savings time
	$selectdst = array();
	if ($bbuserinfo['dstauto'])
	{
		$selectdst[2] = HTML_SELECTED;
	}
	else if ($bbuserinfo['dstonoff'])
	{
		$selectdst[1] = HTML_SELECTED;
	}
	else
	{
		$selectdst[0] = HTML_SELECTED;
	}

	$timezoneoptions = '';
	foreach (fetch_timezone() AS $optionvalue => $timezonephrase)
	{
		$optiontitle = $vbphrase["$timezonephrase"];
		$optionselected = iif($optionvalue == $bbuserinfo['timezoneoffset'], HTML_SELECTED, '');
		eval('$timezoneoptions .= "' . fetch_template('option') . '";');
	}
	eval('$timezoneoptions = "' . fetch_template('modifyoptions_timezone') . '";');

	// start of the week
	if ($bbuserinfo['startofweek'] > 0)
	{
		$dname = 'day' . $bbuserinfo['startofweek'] . 'selected';
		$$dname = HTML_SELECTED;
	}
	else
	{
		$day1selected = HTML_SELECTED;
	}

	// bb code editor options
	require_once('./includes/functions_editor.php');
	$allowwysiwyg = is_wysiwyg_compatible(2);
	$showenhanced = iif($allowwysiwyg == 2 OR $bbuserinfo['showvbcode'] == 2, true, false);

	$checkvbcode = $selectvbcode = array();
	$checkvbcode["$bbuserinfo[showvbcode]"] = HTML_CHECKED;
	$selectvbcode["$bbuserinfo[showvbcode]"] = HTML_SELECTED;

	$show['wyswiygoption'] = iif($vboptions['allowvbcodebuttons'] == 2, true, false);

	//MaxPosts by User
	$optionArray = explode(',', $vboptions['usermaxposts']);
	$foundmatch = 0;
	foreach ($optionArray AS $optionvalue)
	{
		if ($optionvalue == $bbuserinfo['maxposts'])
		{
			$optionselected = HTML_SELECTED;
			$foundmatch = 1;
		}
		else
		{
			$optionselected = '';
		}
		$optiontitle = construct_phrase($vbphrase['show_x_posts_per_page'], $optionvalue);
		eval ('$maxpostsoptions .= "' . fetch_template('option') . '";');
	}
	if ($foundmatch == 0)
	{
		$postsdefaultselected = HTML_SELECTED;
	}

	if ($vboptions['allowchangestyles'])
	{
		$stylecount = 0;
		if (!empty($stylechoosercache))
		{
			$stylesetlist = construct_style_options();
		}
		$show['styleoption'] = iif($stylecount > 1, true, false);
	}
	else
	{
		$show['styleoption'] = false;
	}

	// get language options
	$languagelist = '';
	$languages = fetch_language_titles_array('', 0);
	if (sizeof($languages) > 1)
	{
		foreach ($languages AS $optionvalue => $optiontitle)
		{
			$optionselected = iif($bbuserinfo['languageid'] == $optionvalue, HTML_SELECTED, '');
			eval('$languagelist .= "' . fetch_template('option') . '";');
		}
		$show['languageoption'] = true;
	}
	else
	{
		$show['languageoption'] = false;
	}

	$bgclass1 = 'alt1'; // Login Section
	$bgclass3 = 'alt1'; // Messaging Section
	$bgclass3 = 'alt1'; // Thread View Section
	$bgclass4 = 'alt1'; // Date/Time Section
	$bgclass5 = 'alt1'; // Other Section

	// Get custom otions
	$customfields = array();
	fetch_profilefields(1);

	// draw cp nav bar
	construct_usercp_nav('options');

	$navbits[''] = $vbphrase['edit_options'];
	$templatename = 'modifyoptions';
}

// ############################### start update options ###############################
if ($_POST['do'] == 'updateoptions')
{
	require_once('./includes/functions_misc.php');

	globalize($_POST, array('newstyleset' => INT, 'dst' => INT, 'showvbcode' => INT, 'pmpopup' => INT, 'umaxposts' => INT, 'prunedays' => INT,
							'timezoneoffset', 'startofweek' => INT, 'languageid' => INT, 'threadedmode' => INT, 'invisible' => INT, 'autosubscribe' => INT,
							'options', 'modifyavatar'));

	// make sure $timezoneoffset is a numeric value
	$timezoneoffset += 0;

	// verify that autosubscribe choice is valid
	$autosubscribe = verify_subscription_choice($autosubscribe, $bbuserinfo, -1);

	// sort out thread display mode
	if ($threadedmode == 3)
	{
		$options['postorder'] = 1;
		$threadedmode = 0;
	}
	else if ($threadedmode == 0)
	{
		$options['postorder'] = 0;
	}

	// set threadedmode to linear / oldest first if threadedmode is disabled
	if (!$vboptions['allowthreadedmode'] AND $threadedmode > 0)
	{
		$options['postorder'] = 0;
		$threadedmode = 0;
	}

	// set current style
	if ($vboptions['allowchangestyles'] == 1)
	{
		$newstyleset = intval($newstyleset);
		$updatestyles = "styleid = $newstyleset,";
		if ($newstyleset != $bbuserinfo['styleid'])
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "session SET styleid=$newstyleset WHERE sessionhash='" . addslashes($session['dbsessionhash']) . "'");
		}
		vbsetcookie('styleid', '', 1);
	}
	else
	{
		$updatestyles = '';
	}

	// set threaded/linear mode
	vbsetcookie('threadedmode', '', 1);

	switch ($dst)
	{
		case 2:
			$options['dstauto'] = 1;
			$options['dstonoff'] = $bbuserinfo['dstonoff'];
			break;
		case 1:
			$options['dstauto'] = 0;
			$options['dstonoff'] = 1;
			break;
		case 0:
			$options['dstauto'] = 0;
			$options['dstonoff'] = 0;
			break;
	}

	$options['hasaccessmask'] = $bbuserinfo['hasaccessmask'];

	if (!($permissions['genericpermissions'] & CANHIDEREP))
	{
		$options['showreputation'] = 1;
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "user SET
			" . $updatestyles . "
			showvbcode = $showvbcode,
			pmpopup = $pmpopup,
			maxposts = $umaxposts,
			daysprune = $prunedays,
			timezoneoffset = '$timezoneoffset',
			startofweek = $startofweek,
			languageid = $languageid,
			threadedmode = $threadedmode,
			autosubscribe = $autosubscribe,
			options = " . convert_array_to_bits($options, $_USEROPTIONS) . "
		WHERE userid = $bbuserinfo[userid]
	");

	$userfields = verify_profilefields(1);

	// insert custom user fields
	if (!empty($userfields))
	{
		$DB_site->query("UPDATE " . TABLE_PREFIX . "userfield SET userid=$bbuserinfo[userid]$userfields WHERE userid=$bbuserinfo[userid]");
	}

	if (!empty($modifyavatar))
	{
		$url = "profile.php?$session[sessionurl]do=editavatar";
	}
	else
	{
		$url = "usercp.php?$session[sessionurl]";
	}

	eval(print_standard_redirect('redirect_updatethanks'));

}

// ############################################################################
// ############################## EDIT SIGNATURE ##############################
// ############################################################################

// ########################### start update signature #########################
if ($_POST['do'] == 'updatesignature')
{

	globalize($_POST, array('WYSIWYG_HTML', 'message' => STR, 'preview'));

	if (isset($WYSIWYG_HTML))
	{
		require_once('./includes/functions_wysiwyg.php');
		$signature = convert_wysiwyg_html_to_bbcode($WYSIWYG_HTML, $vboptions['allowhtml']);
	}
	else
	{
		$signature = trim($message);
	}

	if ($vboptions['maximages'] != 0)
	{
		require_once('./includes/functions_bbcodeparse.php');
		$parsedsig = parse_bbcode($signature, 0, $vboptions['allowsmilies'], 1);

		require_once('./includes/functions_misc.php');
		if (fetch_character_count($parsedsig, '<img') > $vboptions['maximages'])
		{
			$preview = 'true';
			eval('$errors[] = "' . fetch_phrase('toomanyimages', PHRASETYPEID_ERROR) . '";');
		}
	}
	if (vbstrlen(strip_bbcode($signature, false, false, false)) > $vboptions['sigmax'])
	{
		$preview = 'true';
		eval('$errors[] = "' . fetch_phrase('sigtoolong', PHRASETYPEID_ERROR) . '";');
	}

	// add # to color tags using hex if it's not there
	$signature = preg_replace('#\[color=(&quot;|"|\'|)([a-f0-9]{6})\\1]#i', '[color=\1#\2\1]', $signature);

	require_once('./includes/functions_newpost.php');
	$signature = convert_url_to_bbcode($signature);

	if (isset($preview))
	{
		if (is_array($errors))
		{
			$errorlist = '';
			foreach ($errors AS $key => $errormessage)
			{
				eval('$errorlist .= "' . fetch_template('newpost_errormessage') . '";');
			}
			$show['errors'] = true;
		}

		require_once('./includes/functions_bbcodeparse.php');
		$previewmessage = parse_bbcode2($signature, $vboptions['allowhtml'], $vboptions['allowbbimagecode'], $vboptions['allowsmilies'], $vboptions['allowbbcode']);
		// save a conditional by just overwriting the phrase
		$vbphrase['submit_message'] = &$vbphrase['save_signature'];
		eval('$preview = "' . fetch_template('newpost_preview') . '";');
		$_REQUEST['do'] = 'editsignature';
	}
	else
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "usertextfield
			SET signature = '" . addslashes($signature) . "'
			WHERE userid = $bbuserinfo[userid]
		");

		$url = "usercp.php?$session[sessionurl]";
		eval(print_standard_redirect('redirect_updatethanks'));
	}
}

// ############################ start edit signature ##########################
if ($_REQUEST['do'] == 'editsignature')
{
	require_once('./includes/functions_newpost.php');

	if (!$vboptions['allowsignatures'])
	{
		eval(print_standard_error('error_signaturedisabled'));
	}

	if (!($permissions['genericpermissions'] & CANUSESIGNATURE))
	{
		eval(print_standard_error('nosignaturepermission'));
	}

	$htmlcodeon = iif($vboptions['allowhtml'], $vbphrase['on'], $vbphrase['off']);
	$bbcodeon = iif($vboptions['allowbbcode'], $vbphrase['on'], $vbphrase['off']);
	$imgcodeon = iif($vboptions['allowbbimagecode'], $vbphrase['on'], $vbphrase['off']);
	$smilieson = iif($vboptions['allowsmilies'], $vbphrase['on'], $vbphrase['off']);

	// only show posting code allowances in forum rules template
	$show['codeonly'] = true;

	eval('$forumrules = "' . fetch_template('forumrules') . '";');

	if (!isset($preview))
	{
		$signature = $bbuserinfo['signature'];
	}

	require_once('./includes/functions_editor.php');

	// set message box width to usercp size
	$stylevar['messagewidth'] = $stylevar['messagewidth_usercp'];
	construct_edit_toolbar(htmlspecialchars_uni($signature), 0, 0, $vboptions['allowsmilies']);

	construct_usercp_nav('signature');

	$navbits[''] = $vbphrase['edit_signature'];
	$templatename = 'modifysignature';
}

// ############################################################################
// ############################### EDIT AVATAR ################################
// ############################################################################
if ($_REQUEST['do'] == 'editavatar')
{

	globalize($_REQUEST, array('pagenumber' => INT, 'categoryid' => INT));

	if (!$vboptions['avatarenabled'])
	{
		eval(print_standard_error('error_avatardisabled'));
	}

	// initialise vars
	$avatarchecked["$bbuserinfo[avatarid]"] = HTML_CHECKED;
	$categorycache = array();
	$bbavatar = array();
	$donefirstcategory = 0;

	// variables that will become templates
	$avatarlist = '';
	$nouseavatarchecked = '';
	$categorybits = '';
	$predefined_section = '';
	$custom_section = '';

	// initialise the bg class
	$bgclass = 'alt1';

	// ############### DISPLAY USER'S AVATAR ###############
	if ($bbuserinfo['avatarid'] != 0)
	{
	// using a predefined avatar

		$avatar = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "avatar WHERE avatarid = $bbuserinfo[avatarid]");
		$avatarid = $avatar['avatarid'];
		eval('$currentavatar = "' . fetch_template('modifyavatarbit') . '";');
		// store avatar info in $bbavatar for later use
		$bbavatar = $avatar;
	}
	else
	{
	// not using a predefined avatar, check for custom

		if ($avatar = $DB_site->query_first("SELECT dateline FROM " . TABLE_PREFIX . "customavatar WHERE userid=$bbuserinfo[userid]"))
		{
		// using a custom avatar
			if ($vboptions['usefileavatar'])
			{
				$bbuserinfo['avatarurl'] = "$vboptions[avatarurl]/avatar$bbuserinfo[userid]_$bbuserinfo[avatarrevision].gif";
			}
			else
			{
				$bbuserinfo['avatarurl'] = "image.php?u=$bbuserinfo[userid]&dateline=$avatar[dateline]";
			}
			eval('$currentavatar = "' . fetch_template('modifyavatarbit_custom') . '";');
		}
		else
		{
		// no avatar specified
			$nouseavatarchecked = HTML_CHECKED;
			$avatarchecked[0] = '';
			eval('$currentavatar = "' . fetch_template('modifyavatarbit_noavatar') . '";');
		}
	}
	// get rid of any lingering $avatar variables
	unset($avatar);

	$membergroups = fetch_membergroupids_array($bbuserinfo);

	// ############### DISPLAY AVATAR CATEGORIES ###############
	// get all the available avatar categories
	$avperms = $DB_site->query("
		SELECT imagecategorypermission.imagecategoryid, usergroupid
		FROM " . TABLE_PREFIX . "imagecategorypermission AS imagecategorypermission, " . TABLE_PREFIX . "imagecategory AS imagecategory
		WHERE imagetype = 1
			AND imagecategorypermission.imagecategoryid = imagecategory.imagecategoryid
		ORDER BY imagecategory.displayorder
	");
	$noperms = array();
	while ($avperm = $DB_site->fetch_array($avperms))
	{
		$noperms["$avperm[imagecategoryid]"][] = $avperm['usergroupid'];
	}
	foreach($noperms AS $imagecategoryid => $usergroups)
	{
		if (!count(array_diff($membergroups, $usergroups)))
		{
			$badcategories .= ",$imagecategoryid";
		}
	}

	$categories = $DB_site->query("
		SELECT imagecategory.*, COUNT(avatarid) AS avatars
		FROM " . TABLE_PREFIX . "imagecategory AS imagecategory
		LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON
			(avatar.imagecategoryid=imagecategory.imagecategoryid)
		WHERE imagetype=1
		AND avatar.minimumposts <= $bbuserinfo[posts]
		AND avatar.avatarid <> $bbuserinfo[avatarid]
		AND imagecategory.imagecategoryid NOT IN (0$badcategories)
		GROUP BY imagecategory.imagecategoryid
		ORDER BY imagecategory.displayorder
	");

	while ($category = $DB_site->fetch_array($categories))
	{
		if ($category['avatars'])
		{
			// only get categories containing at least one avatar
			if (!$donefirstcategory OR $category['imagecategoryid'] == $categoryid)
			{
				$displaycategory = $category;
				$donefirstcategory = 1;
			}
			$categorycache["$category[imagecategoryid]"] = $category;
		}
	}
	unset($category);
	$DB_site->free_result($categories);

	// get the id of the avatar category we want to display
	if ($categoryid == 0)
	{
		if ($bbuserinfo['avatarid'] != 0 AND !empty($categorycache["$bbavatar[imagecategoryid]"]))
		{
			$displaycategory = $bbavatar;
		}
		$categoryid = $displaycategory['imagecategoryid'];
	}

	// make the category <select> list
	$optionselected["$categoryid"] = HTML_SELECTED;
	foreach ($categorycache AS $category)
	{
		$thiscategoryid = $category['imagecategoryid'];
		$selected = iif($thiscategoryid == $categoryid, HTML_SELECTED, '');
		eval('$categorybits .= "' . fetch_template('modifyavatar_category') . '";');
	}

	// ############### GET TOTAL NUMBER OF AVATARS IN THIS CATEGORY ###############
	// get the total number of avatars in this category
	$totalavatars = $categorycache["$categoryid"]['avatars'];

	// get perpage parameters for table display
	$perpage = $vboptions['numavatarsperpage'];
	sanitize_pageresults($totalavatars, $pagenumber, $perpage, 100, 25);
	// get parameters for query limits
	$startat = ($pagenumber - 1) * $perpage;

	// make variables for 'displaying avatars x to y of z' text
	$first = $startat + 1;
	$last = $startat + $perpage;
	if ($last > $totalavatars)
	{
		$last = $totalavatars;
	}

	// ############### DISPLAY PREDEFINED AVATARS ###############
	if ($totalavatars)
	{
		$pagenav = construct_page_nav($totalavatars, "profile.php?$session[sessionurl]do=editavatar&categoryid=$categoryid");

		$avatars = $DB_site->query("
			SELECT avatar.*, imagecategory.title AS category
			FROM " . TABLE_PREFIX . "avatar AS avatar LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
			WHERE minimumposts <= $bbuserinfo[posts]
			AND avatar.imagecategoryid=$categoryid
			AND avatarid<>$bbuserinfo[avatarid]
			ORDER BY avatar.displayorder
			LIMIT $startat,$perpage
		");
		$avatarsonthispage = $DB_site->num_rows($avatars);

		$cols = intval($vboptions['numavatarswide']);
		$cols = iif($cols, $cols, 5);
		$cols = iif($cols > $avatarsonthispage, $avatarsonthispage, $cols);

		$bits = array();
		while ($avatar = $DB_site->fetch_array($avatars))
		{
			$categoryname = $avatar['category'];
			$avatarid = $avatar['avatarid'];
			eval('$bits[] = "' . fetch_template('modifyavatarbit') . '";');
			if (sizeof($bits) == $cols)
			{
				$avatarcells = implode('', $bits);
				$bits = array();
				eval('$avatarlist .= "' . fetch_template('help_avatars_row') . '";');
				exec_switch_bg();
			}
		}

		// initialize remaining columns
		$remainingcolumns = 0;

		$remaining = sizeof($bits);
		if ($remaining)
		{
			$remainingcolumns = $cols - $remaining;
			$avatarcells = implode('', $bits);
			eval('$avatarlist .= "' . fetch_template('help_avatars_row') . '";');
			exec_switch_bg();
		}

		$show['forumavatars'] = true;
	}
	else
	{
		$show['forumavatars'] = false;
	}
	// end code for predefined avatars

	// ############### DISPLAY CUSTOM AVATAR CONTROLS ###############
	require_once('./includes/functions_file.php');
	$inimaxattach = fetch_max_attachment_size();

	if ($permissions['genericpermissions'] & CANUSEAVATAR)
	{
		$show['customavatar'] = true;
		$permissions['avatarmaxsize'] = vb_number_format($permissions['avatarmaxsize'], 1, true);
	}
	else
	{
		$show['customavatar'] = false;
	}

	// draw cp nav bar
	construct_usercp_nav('avatar');

	$navbits[''] = $vbphrase['edit_avatar'];
	$templatename = 'modifyavatar';
}

// ############################################################################
// ############################### EDIT AVATAR ################################
// ############################################################################
if ($_REQUEST['do'] == 'editprofilepic')
{
	if ($vboptions['profilepicenabled'] AND ($permissions['genericpermissions'] & CANPROFILEPIC))
	{
		$profilepic = $DB_site->query_first("
			SELECT userid, dateline
			FROM " . TABLE_PREFIX . "customprofilepic
			WHERE userid = $bbuserinfo[userid]
		");

		$show['profilepic'] = iif($profilepic, true, false);

		$permissions['profilepicmaxsize'] = vb_number_format($permissions['profilepicmaxsize'], 1, true);

		// draw cp nav bar
		construct_usercp_nav('profilepic');

		$navbits[''] = $vbphrase['edit_profile_picture'];
		$templatename = 'modifyprofilepic';
	}
	else
	{
		print_no_permission();
	}
}

// ############################### start update avatar ###############################
if ($_POST['do'] == 'updateavatar')
{

	globalize($_POST, array('avatarid' => INT, 'avatarurl' => STR));

	if (!($permissions['genericpermissions'] & CANMODIFYPROFILE))
	{
		print_no_permission();
	}

	if (!$vboptions['avatarenabled'])
	{
		eval(print_standard_error('error_avatardisabled'));
	}

	$useavatar = iif($avatarid == -1, 0, 1);

	if ($useavatar)
	{
		if ($avatarid == 0)
		{
			// begin custom avatar code
			require_once('./includes/functions_upload.php');
			require_once('./includes/functions_file.php');
			process_image_upload('avatar', $avatarurl);
			// end custom avatar code
		}
		else
		{
			// start predefined avatar code
			$avatarid = verify_id('avatar', $avatarid);
			$avatarinfo = $DB_site->query_first("SELECT minimumposts FROM " . TABLE_PREFIX . "avatar WHERE avatarid=$avatarid");
			if ($avatarinfo['minimumposts'] > $bbuserinfo['posts'])
			{
				// not enough posts error
				eval(print_standard_error('error_avatarmoreposts'));
			}
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "customavatar WHERE userid=$bbuserinfo[userid]");
			@unlink("$vboptions[avatarpath]/avatar$bbuserinfo[userid]_$bbuserinfo[avatarrevision].gif");
			// end predefined avatar code
		}
	}
	else
	{
		// not using an avatar

		$avatarid = 0;
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "customavatar WHERE userid=$bbuserinfo[userid]");
		@unlink("$vboptions[avatarpath]/avatar$bbuserinfo[userid]_$bbuserinfo[avatarrevision].gif");
	}

	$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET avatarid = " . intval($avatarid) . " WHERE userid = $bbuserinfo[userid]");

	$url = "profile.php?$session[sessionurl]do=editavatar";
	eval(print_standard_redirect('redirect_updatethanks'));

}

// ############################### start update profile pic###########################
if ($_POST['do'] == 'updateprofilepic')
{
	globalize($_POST, array('deleteprofilepic' => INT, 'avatarurl' => STR));

	if (!($permissions['genericpermissions'] & CANPROFILEPIC))
	{
		print_no_permission();
	}

	if (!$vboptions['profilepicenabled'])
	{
		print_no_permission();
	}

	if ($deleteprofilepic)
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "customprofilepic WHERE userid = $bbuserinfo[userid]");
	}
	else
	{
		require_once('./includes/functions_upload.php');
		require_once('./includes/functions_file.php');
		// cut avatarurl down in case it is biggie sized to avoid a potential out of memory error I suppose
		if ($avatarurl != '')
		{
			$avatarurl = substr($avatarurl, 0, 500);
		}
		process_image_upload('profilepic', $avatarurl);
	}

	$url = "profile.php?$session[sessionurl]do=editprofilepic";
	eval(print_standard_redirect('redirect_updatethanks'));
}

// ############################### start choose displayed usergroup ###############################

if ($_POST['do'] == 'updatedisplaygroup')
{

	globalize($_POST, array('usergroupid' => INT));

	$membergroups = fetch_membergroupids_array($bbuserinfo);

	if ($usergroupid == 0)
	{
		$idname = $vbphrase['usergroup'];
		eval(print_standard_error('invalidid'));
	}

	if (!in_array($usergroupid, $membergroups))
	{
		eval(print_standard_error('notmemberofdisplaygroup'));
	}
	else
	{
		$usergroup = $usergroupcache["$bbuserinfo[usergroupid]"];
		if ($usergroupid == $bbuserinfo['usergroupid'] OR $usergroup = $DB_site->query_first("SELECT title, usertitle FROM " . TABLE_PREFIX . "usergroup WHERE usergroupid = $usergroupid AND canoverride = 1"))
		{
			if ($bbuserinfo['customtitle'] == 0 AND $usergroup['usertitle'] == '')
			{
				$usertitle = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "usertitle WHERE minposts < $bbuserinfo[posts] ORDER BY minposts DESC LIMIT 1");
				$usergroup['usertitle'] = $usertitle['title'];
			}
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "user SET displaygroupid=$usergroupid"
				. iif($bbuserinfo['customtitle'] OR $usergroup['usertitle'] == '', '', ", usertitle='" . addslashes($usergroup['usertitle']) . "' ") . "
				WHERE userid=$bbuserinfo[userid]
			");
			eval(print_standard_redirect('usergroup_displaygroupupdated'));
		}
		else
		{
			eval(print_standard_error('usergroup_invaliddisplaygroup'));
		}
	}

}

// *************************************************************************

if ($_POST['do'] == 'leavegroup')
{

	globalize($_POST, array('usergroupid' => INT));

	$membergroups = fetch_membergroupids_array($bbuserinfo);

	if (empty($membergroups))
	{ // check they have membergroups
		eval(print_standard_error('usergroup_cantleave_notmember'));
	}
	else if (!in_array($usergroupid, $membergroups))
	{ // check they are a member before leaving
		eval(print_standard_error('usergroup_cantleave_notmember'));
	}
	else
	{
		if ($usergroupid == $bbuserinfo['usergroupid'])
		{
			// trying to leave primary usergroup
			eval(print_standard_error('usergroup_cantleave_primary'));
		}
		else if ($check = $DB_site->query_first("SELECT usergroupleaderid FROM " . TABLE_PREFIX . "usergroupleader WHERE usergroupid=$usergroupid AND userid=$bbuserinfo[userid]"))
		{
			// trying to leave a group of which user is a leader
			eval(print_standard_error('usergroup_cantleave_groupleader'));
		}
		else
		{
			$newmembergroups = array();
			foreach ($membergroups AS $groupid)
			{
				if ($groupid != $bbuserinfo['usergroupid'] AND $groupid != $usergroupid)
				{
					$newmembergroups[] = $groupid;
				}
			}
			$newmembergroups_str = implode(',', $newmembergroups);
			$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET membergroupids='" . $newmembergroups_str . "'" . iif($bbuserinfo['displaygroupid'] == $usergroupid, ', displaygroupid=0', '') . " WHERE userid=$bbuserinfo[userid]");

			$old_canview = array();
			foreach ($bbuserinfo['forumpermissions'] AS $forumid => $perms)
			{
				if ($perms & CANVIEW)
				{
					$old_canview[] = $forumid;
				}
			}

			$bbuserinfo['membergroupids'] = $newmembergroups_str;
			unset($bbuserinfo['forumpermissions']);
			cache_permissions($bbuserinfo);
			$remove_subs = array();
			foreach ($old_canview AS $forumid)
			{
				if (!($bbuserinfo['forumpermissions']["$forumid"] & CANVIEW))
				{
					$remove_subs[] = $forumid;
				}
			}

			if (sizeof($remove_subs) > 0)
			{
				$forum_list = implode(',', $remove_subs);
				$DB_site->query("
					DELETE FROM " . TABLE_PREFIX . "subscribeforum
					WHERE userid = $bbuserinfo[userid]
						AND forumid IN ($forum_list)
				");

				$threads = $DB_site->query("
					SELECT subscribethread.threadid
					FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
					INNER JOIN " . TABLE_PREFIX . "thread AS thread
						ON (thread.threadid = subscribethread.threadid)
					WHERE subscribethread.userid = $bbuserinfo[userid]
						AND thread.forumid IN ($forum_list)
				");
				$remove_thread = array();
				while ($thread = $DB_site->fetch_array($threads))
				{
					$remove_thread[] = $thread['threadid'];
				}
				if (sizeof($remove_thread) > 0)
				{
					$DB_site->query("
						DELETE FROM " . TABLE_PREFIX . "subscribethread
						WHERE userid = $bbuserinfo[userid]
							AND threadid IN (" . implode(',', $remove_thread) . ")
					");
				}
			}

			eval(print_standard_redirect('usergroup_nolongermember', 0));
		}
	}

}

// *************************************************************************

if ($_POST['do'] == 'insertjoinrequest')
{

	globalize($_POST, array('usergroupid' => INT));

	$url = "profile.php?do=editusergroups";

	if ($request = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "usergrouprequest WHERE userid=$bbuserinfo[userid] AND usergroupid=$usergroupid"))
	{
		// request already exists, just say okay...
		eval(print_standard_redirect('usergroup_requested'));
	}
	else

	{
		// insert the request
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "usergrouprequest
				(userid,usergroupid,reason,dateline)
			VALUES
				($bbuserinfo[userid], $usergroupid, '" . addslashes(htmlspecialchars_uni($_POST['reason'])) . "', " . TIMENOW . ")
		");
		eval(print_standard_redirect('usergroup_requested'));
	}

}

// *************************************************************************

if ($_POST['do'] == 'joingroup')
{

	globalize($_POST, array('usergroupid' => INT));

	$membergroups = fetch_membergroupids_array($bbuserinfo);

	if (in_array($usergroupid, $membergroups))
	{

		eval(print_standard_error('usergroup_already_member'));

	}
	else
	{

		// check to see that usergroup exists and is public
		if ($usergroupcache["$usergroupid"]['ispublicgroup'])
		{
			$usergroup = $usergroupcache["$usergroupid"];

			// check to see if group is moderated
			$leaders = $DB_site->query("
				SELECT ugl.userid, username
				FROM " . TABLE_PREFIX . "usergroupleader AS ugl
				INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
				WHERE ugl.usergroupid = $usergroupid
			");
			if ($DB_site->num_rows($leaders))
			{
				// group is moderated: show join request page

				$_groupleaders = array();
				while ($leader = $DB_site->fetch_array($leaders))
				{
					eval('$_groupleaders[] = "' . fetch_template('modifyusergroups_groupleader') . '";');
				}
				$groupleaders = implode(', ', $_groupleaders);

				$navbits["profile.php?$session[sessionurl]do=editusergroups"] = $vbphrase['group_memberships'];
				$navbits[''] = $vbphrase['join_request'];

				// draw cp nav bar
				construct_usercp_nav('usergroups');
				$templatename = 'modifyusergroups_requesttojoin';

			}
			else
			{

				// group is not moderated: update user & join group
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "user SET
						membergroupids = '" . iif($bbuserinfo['membergroupids'], "$bbuserinfo[membergroupids],$usergroupid", $usergroupid) . "'
					WHERE userid=$bbuserinfo[userid]
				");
				$usergroupname = $usergroup['title'];
				eval(print_standard_redirect('usergroup_welcome'));

			}

		}
		else
		{
			eval(print_standard_error('usergroup_notpublic'));
		}
	}

}

// *************************************************************************

if ($_REQUEST['do'] == 'editusergroups')
{
	// draw cp nav bar
	construct_usercp_nav('usergroups');

	// check to see if there are usergroups available
	$haspublicgroups = false;
	foreach ($usergroupcache AS $usergroup)
	{
		if ($usergroup['ispublicgroup'] or $usergroup['canoverride'])
		{
			$haspublicgroups = true;
			break;
		}
	}

	if (!$haspublicgroups)
	{
		eval(print_standard_error('no_public_usergroups'));
	}
	else
	{
		$membergroups = fetch_membergroupids_array($bbuserinfo);

		// query user's usertitle based on posts ladder
		$usertitle = $DB_site->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "usertitle
			WHERE minposts < $bbuserinfo[posts]
			ORDER BY minposts DESC
			LIMIT 1
		");

		// get array of all usergroup leaders
		$bbuserleader = array();
		$leaders = array();
		$groupleaders = $DB_site->query("
			SELECT ugl.*, user.username
			FROM " . TABLE_PREFIX . "usergroupleader AS ugl
			INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		");
		while ($groupleader = $DB_site->fetch_array($groupleaders))
		{
			if ($groupleader['userid'] == $bbuserinfo['userid'])
			{
				$bbuserleader[] = $groupleader['usergroupid'];
			}
			$leaders["$groupleader[usergroupid]"]["$groupleader[userid]"] = $groupleader;
		}
		unset($groupleader);
		$DB_site->free_result($groupleaders);

		// notify about new join requests if user is a group leader
		$joinrequestbits = '';
		if (!empty($bbuserleader))
		{
			$joinrequests = $DB_site->query("
				SELECT usergroup.title, usergroup.opentag, usergroup.closetag, usergroup.usergroupid, COUNT(usergrouprequestid) AS requests
				FROM " . TABLE_PREFIX . "usergroup AS usergroup
				LEFT JOIN " . TABLE_PREFIX . "usergrouprequest AS req USING(usergroupid)
				WHERE usergroup.usergroupid IN(" . implode(',', $bbuserleader) . ")
				GROUP BY usergroup.usergroupid
				ORDER BY usergroup.title
			");
			while ($joinrequest = $DB_site->fetch_array($joinrequests))
			{
				exec_switch_bg();
				$joinrequest['requests'] = vb_number_format($joinrequest['requests']);
				eval('$joinrequestbits .= "' . fetch_template('modifyusergroups_joinrequestbit') . '";');
			}
			unset($joinrequest);
			$DB_site->free_result($joinrequests);
		}

		$show['joinrequests'] = iif($joinrequestbits != '', true, false);

		// get usergroups
		$groups = array();
		foreach ($usergroupcache AS $usergroupid => $usergroup)
		{
			if ($usergroup['usertitle'] == '')
			{
				$usergroup['usertitle'] = $usertitle['title'];
			}
			if (in_array($usergroupid, $membergroups))
			{
				$groups['member']["$usergroupid"] = $usergroup;
			}
			else if ($usergroup['ispublicgroup'])
			{
				$groups['notmember']["$usergroupid"] = $usergroup;
				$couldrequest[] = $usergroupid;
			}
		}

		// do groups user is NOT a member of
		$nonmembergroupbits = '';
		if (is_array($groups['notmember']))
		{
			// get array of join requests for this user
			$requests = array();
			$joinrequests = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "usergrouprequest WHERE userid=$bbuserinfo[userid] AND usergroupid IN (" . implode(',', $couldrequest) . ')');
			while ($joinrequest = $DB_site->fetch_array($joinrequests))
			{
				$requests["$joinrequest[usergroupid]"] = $joinrequest;
			}
			unset($joinrequest);
			$DB_site->free_result($joinrequests);

			foreach ($groups['notmember'] AS $usergroupid => $usergroup)
			{
				$joinrequested = 0;
				exec_switch_bg();
				if (is_array($leaders["$usergroupid"]))
				{
					$_groupleaders = array();
					foreach ($leaders["$usergroupid"] AS $leader)
					{
						eval('$_groupleaders[] = "' . fetch_template('modifyusergroups_groupleader') . '";');
					}
					$ismoderated = 1;
					$groupleaders = implode(', ', $_groupleaders);
					if (isset($requests["$usergroupid"]))
					{
						$joinrequest = $requests["$usergroupid"];
						$joinrequest['date'] = vbdate($vboptions['dateformat'], $joinrequest['dateline'], 1);
						$joinrequest['time'] = vbdate($vboptions['timeformat'], $joinrequest['dateline'], 1);
						$joinrequested = 1;
					}
				}
				else

				{
					$ismoderated = 0;
					$groupleaders = '';
				}
				eval('$nonmembergroupbits .= "' . fetch_template('modifyusergroups_nonmemberbit') . '";');
			}
		}

		$show['nonmembergroups'] = iif($nonmembergroupbits != '', true, false);

		// set primary group info
		$primarygroupid = $bbuserinfo['usergroupid'];
		$primarygroup = $groups['member']["$bbuserinfo[usergroupid]"];

		// do groups user IS a member of
		$membergroupbits = '';
		foreach ($groups['member'] AS $usergroupid => $usergroup)
		{
			if ($usergroupid != $bbuserinfo['usergroupid'] AND $usergroup['ispublicgroup'])
			{
				exec_switch_bg();
				if ($usergroup['usertitle'] == '')
				{
					$usergroup['usertitle'] = $usertitle['title'];
				}
				if (isset($leaders["$usergroupid"]["$bbuserinfo[userid]"]))
				{
					$show['isleader'] = true;
				}
				else

				{
					$show['isleader'] = false;
				}
				eval('$membergroupbits .= "' . fetch_template('modifyusergroups_memberbit') . '";');
			}
		}

		$show['membergroups'] = iif($membergroupbits != '', true, false);

		// do groups user could use as display group
		$checked = array();
		if ($bbuserinfo['displaygroupid'])
		{
			$checked["$bbuserinfo[displaygroupid]"] = HTML_CHECKED;
		}
		else
		{
			$checked["$bbuserinfo[usergroupid]"] = HTML_CHECKED;
		}
		$displaygroupbits = '';
		foreach ($groups['member'] AS $usergroupid => $usergroup)
		{
			if ($usergroupid != $bbuserinfo['usergroupid'] AND $usergroup['canoverride'])
			{
				exec_switch_bg();
				eval('$displaygroupbits .= "' . fetch_template('modifyusergroups_displaybit') . '";');
			}
		}

		$show['displaygroups'] = iif($displaygroupbits != '', true, false);

		if (!$show['joinrequests'] AND !$show['nonmembergroups'] AND !$show['membergroups'] AND !$show['displaygroups'])
		{
			eval(print_standard_error('no_public_usergroups'));
		}

		$navbits[''] = $vbphrase['group_memberships'];
		$templatename = 'modifyusergroups';
	}
}

if ($_POST['do'] == 'deleteusergroups')
{
	globalize($_POST, array('usergroupid' => INT, 'deletebox'));

	if ($usergroupid)
	{
		// check permission to do authorizations in this group
		if (!$leadergroup = $DB_site->query_first("
			SELECT usergroupleaderid
			FROM " . TABLE_PREFIX . "usergroupleader AS usergroupleader
			WHERE userid = $bbuserinfo[userid]
				AND usergroupid = $usergroupid
		"))
		{
			print_no_permission();
		}

		if (is_array($deletebox) AND !empty($deletebox))
		{
			foreach ($deletebox AS $userid => $value)
			{
				$userids .= ',' . intval($userid);
			}

			$users = $DB_site->query("
				SELECT u.userid, u.membergroupids, u.usergroupid, u.displaygroupid
				FROM " . TABLE_PREFIX . "user AS u
				LEFT JOIN " . TABLE_PREFIX . "usergroupleader AS ugl ON (u.userid = ugl.userid AND ugl.usergroupid = $usergroupid)
				WHERE u.userid IN (0$userids) AND ugl.usergroupleaderid IS NULL
			");
			while ($user = $DB_site->fetch_array($users))
			{
				$membergroups = fetch_membergroupids_array($user, false);
				$newmembergroups = array();
				foreach($membergroups AS $groupid)
				{
					if ($groupid != $user['usergroupid'] AND $groupid != $usergroupid)
					{
						$newmembergroups[] = $groupid;
					}
				}

				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "user
					SET membergroupids='" . implode(',', $newmembergroups) . "'" . iif($user['displaygroupid'] == $usergroupid, ', displaygroupid=0', '') . "
					WHERE userid = $user[userid]
				");
			}

			$url = "memberlist.php?$session[sessionurl]usergroupid=$usergroupid";
			eval(print_standard_redirect('redirect_removedusers'));
		}
		else
		{
			// Print didn't select any users to delete
			eval(print_standard_error('usergroupleader_deleted'));
		}
	}
	else
	{
		print_no_permission();
	}

}

// ############################### Delete attachments for current user #################
if ($_POST['do'] == 'deleteattachments')
{
	if (!$bbuserinfo['userid'])
	{
		print_no_permission();
	}

	globalize($_POST, array('deletebox', 'perpage' => INT, 'pagenumber' => INT, 'showthumbs' => INT, 'userid' => INT));

	if (!is_array($deletebox))
	{
		eval(print_standard_error('error_attachdel'));
	}

	// Get forums that allow canview access
	foreach ($bbuserinfo['forumpermissions'] AS $forumid => $perm)
	{
		if (($perm & CANVIEW) AND ($perm & CANGETATTACHMENT))
		{
			if ($userid != $bbuserinfo['userid'] AND !($perm & CANVIEWOTHERS))
			{
				// Viewing non-self and don't have permission to view other's threads in this forum
				continue;
			}
			$forumids .= ",$forumid";
		}
	}

	foreach ($deletebox AS $attachmentid => $value)
	{
		$idlist .= ',' . intval($attachmentid);
	}
	// Verify that $bbuserinfo owns these attachments before allowing deletion
	$validids = $DB_site->query("
		SELECT attachment.attachmentid, attachment.postid, post.threadid, thread.forumid, thread.open, attachment.userid, post.dateline as p_dateline,
			IF(attachment.postid = 0, 1, 0) AS inprogress
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (attachment.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(attachment.postid = deletionlog.primaryid AND type = 'post')
		WHERE attachmentid IN (0$idlist) AND attachment.userid = $userid
			AND	((forumid IN(0$forumids) AND thread.visible = 1 AND post.visible = 1 AND deletionlog.primaryid IS NULL) " . iif($userid==$bbuserinfo['userid'], "OR attachment.postid = 0") . ")
	");
	unset($idlist);
	while ($attachment = $DB_site->fetch_array($validids))
	{
		if (!$attachment['inprogress'])
		{
			if (!$attachment['open'] AND !can_moderate($attachment['forumid'], 'canopenclose') AND !$vboptions['allowclosedattachdel'])
			{
				continue;
			}
			else if (!can_moderate($attachment['forumid'], 'caneditposts'))
			{
				$forumperms = fetch_permissions($attachment['forumid']);
				if (!($forumperms & CANEDITPOST) OR $bbuserinfo['userid'] != $attachment['userid'])
				{
					continue;
				}
				else
				{
					if (!$vboptions['allowattachdel'] AND $vboptions['edittimelimit'] AND $attachment['p_dateline'] < TIMENOW - $vboptions['edittimelimit'] * 60)
					{
						continue;
					}
				}
			}
		}

		$attachmentinfo["$attachment[attachmentid]"] = $attachment;
		$idlist .= ',' . $attachment['attachmentid'];
	}

	require_once('./includes/functions_file.php');
	if (!empty($attachmentinfo))
	{
		foreach($attachmentinfo AS $attachmentid => $attacharray)
		{
			if ($attacharray['postid'])
			{
				if ($vboptions['attachfile'])
				{
					@unlink(fetch_attachment_path($bbuserinfo['userid'], $attachmentid));
				}
				// Decremement attach counters
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "post
					SET attach = attach - 1
					WHERE postid = $attacharray[postid]
				");
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "thread
					SET attach = attach - 1
					WHERE threadid = $attacharray[threadid]
				");
			}
		}
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "attachment
			WHERE attachmentid IN (0$idlist)
		");
	}

	$url = "profile.php?$session[sessionurl]do=editattachments&amp;pp=$perpage&amp;page=$pagenumber&amp;showthumbs=$showthumbs&amp;userid=$userid";
	eval(print_standard_redirect('redirect_attachdel'));

}

// ############################### List of attachments for current user ################
if ($_REQUEST['do'] == 'editattachments')
{
	globalize($_REQUEST, array('perpage' => INT, 'pagenumber' => INT, 'showthumbs' => INT, 'userid' => INT));

	$templatename = 'modifyattachments';

	$show['attachments'] = true;

	if (!$userid OR $userid == $bbuserinfo['userid'])
	{
		// show own attachments in user cp
		$userid = $bbuserinfo['userid'];
		$username = $bbuserinfo['username'];
		$show['attachquota'] = true;
	}
	else
	{
		// show someone else's attachments
		$userinfo = verify_id('user', $userid, 1, 1);
		$username = $userinfo['username'];
		$show['otheruserid'] = true;
	}

	// Get forums that allow canview access
	foreach ($bbuserinfo['forumpermissions'] AS $forumid => $perm)
	{
		if (($perm & CANVIEW) AND ($perm & CANGETATTACHMENT))
		{
			if ($userid != $bbuserinfo['userid'] AND !($perm & CANVIEWOTHERS))
			{
				// Viewing non-self and don't have permission to view other's threads in this forum
				continue;
			}
			$forumids .= ",$forumid";
		}
	}

	// Get attachment count
	$attachments = $DB_site->query_first("
		SELECT COUNT(*) AS total,
			SUM(filesize) AS sum
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(attachment.postid = deletionlog.primaryid AND type = 'post')
		WHERE attachment.userid = $userid
			AND	((forumid IN(0$forumids) AND thread.visible = 1 AND post.visible = 1 AND deletionlog.primaryid IS NULL) " . iif($userid==$bbuserinfo['userid'], "OR attachment.postid = 0") . ")
	");

	$totalattachments = intval($attachments['total']);
	$attachsum = intval($attachments['sum']);

	if (!$totalattachments AND $userid != $bbuserinfo['userid'])
	{
		eval(print_standard_error('error_noattachments'));
	}
	else if (!$totalattachments)
	{
		$show['attachments'] = false;
		$show['attachquota'] = false;
	}
	else
	{
		if ($permissions['attachlimit'])
		{
			if ($attachsum >= $permissions['attachlimit'])
			{
				$totalsize = 0;
				$attachsize = 100;
			}
			else
			{
				$attachsize = ceil($attachsum / $permissions['attachlimit'] * 100);
				$totalsize = 100 - $attachsize;
			}

			$attachlimit = vb_number_format($permissions['attachlimit'], 1, true);
		}

		$attachsum = vb_number_format($attachsum, 1, true);

		if ($showthumbs)
		{
			$maxperpage = 10;
			$defaultperpage = 10;
		}
		else
		{
			$maxperpage = 200;
			$defaultperpage = 20;
		}
		sanitize_pageresults($totalattachments, $pagenumber, $perpage, $maxperpage, $defaultperpage);

		$limitlower = ($pagenumber - 1) * $perpage + 1;
		$limitupper = ($pagenumber) * $perpage;

		if ($limitupper > $totalattachments)
		{
			$limitupper = $totalattachments;
			if ($limitlower > $totalattachments)
			{
				$limitlower = $totalattachments - $perpage;
			}
		}
		if ($limitlower <= 0)
		{
			$limitlower = 1;
		}

		// Get attachment info
		$attachments = $DB_site->query("
			SELECT thread.forumid, post.postid, post.threadid AS p_threadid, post.title AS p_title, post.dateline AS p_dateline, attachment.attachmentid,
				thread.title AS t_title, attachment.filename, attachment.counter, attachment.filesize AS size, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail,
				thumbnail_filesize, user.username, thread.open, attachment.userid " . iif($userid==$bbuserinfo['userid'], ", IF(attachment.postid = 0, 1, 0) AS inprogress") . "
			FROM " . TABLE_PREFIX . "attachment AS attachment
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(attachment.postid = deletionlog.primaryid AND type = 'post')
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (attachment.userid = user.userid)
			WHERE attachment.userid = $userid
				AND ((forumid IN (0$forumids) AND thread.visible = 1 AND post.visible = 1 AND deletionlog.primaryid IS NULL) " . iif($userid==$bbuserinfo['userid'], "OR attachment.postid = 0") . ")
			ORDER BY attachment.attachmentid DESC
			LIMIT " . ($limitlower - 1) . ", $perpage
		");

		$template['attachmentlistbits'] = '';
		while ($post = $DB_site->fetch_array($attachments))
		{
			$post['filename'] = htmlspecialchars_uni($post['filename']);

			if (!$post['p_title'])
			{
				$post['p_title'] = '&laquo;' . $vbphrase['n_a'] . '&raquo;';
			}

			$post['counter'] = vb_number_format($post['counter']);
			$post['size'] = vb_number_format($post['size'], 1, true);
			$post['postdate'] = vbdate($vboptions['dateformat'], $post['p_dateline'], true);
			$post['posttime'] = vbdate($vboptions['timeformat'], $post['p_dateline']);

			$post['attachmentextension'] = strtolower(file_extension($post['filename']));
			$show['thumbnail'] = iif($post['hasthumbnail'] == 1 AND $vboptions['attachthumbs'] AND $showthumbs AND $post['postid'], 1, 0);
			$show['inprogress'] = iif(!$post['postid'], true, false);

			$show['deletebox'] = false;

			if ($post['inprogress'])
			{
				$show['deletebox'] = true;
			}
			else if ($post['open'] OR $vboptions['allowclosedattachdel'] OR can_moderate($post['forumid'], 'canopenclose'))
			{
				if (can_moderate($post['forumid'], 'caneditposts'))
				{
					$show['deletebox'] = true;
				}
				else
				{
					$forumperms = fetch_permissions($post['forumid']);
					if (($forumperms & CANEDITPOST AND $bbuserinfo['userid'] == $post['userid']))
					{
						if ($vboptions['allowattachdel'] OR !$vboptions['edittimelimit'] OR $post['p_dateline'] >= TIMENOW - $vboptions['edittimelimit'] * 60)
						{
							$show['deletebox'] = true;
						}
					}
				}
			}

			if ($show['deletebox'])
			{
				$show['deleteoption'] = true;
			}

			eval('$template[\'attachmentlistbits\'] .= "' . fetch_template('modifyattachmentsbit') . '";');
		}

		$sorturl = "profile.php?$session[sessionurl]do=editattachments&amp;pp=$perpage&amp;showthumbs=$showthumbs";
		if ($userid != $bbuserinfo['userid'])
		{
			$sorturl .= "&amp;userid=$userid";
		}
		$pagenav = construct_page_nav($totalattachments, $sorturl);

		$totalattachments = vb_number_format($totalattachments);

		$show['attachlimit'] = $permissions['attachlimit'];
		$show['currentattachsize'] = $attachsize;
		$show['totalattachsize'] = $totalsize;
		$show['thumbnails'] = $showthumbs;
	}

	if ($userid == $bbuserinfo['userid'])
	{
		// show $bbuserinfo's attachments in usercp
		construct_usercp_nav('attachments');
		$navbits[''] = construct_phrase($vbphrase['attachments_posted_by_x'], $bbuserinfo['username']);
	}
	else
	{
		// show some other user's attachments
		$pagetitle = construct_phrase($vbphrase['attachments_posted_by_x'], $username);

		$navbits = array(
			"member.php?$session[sessionurl]u=$userid" => $vbphrase['view_profile'],
			'' => $pagetitle
		);

		$shelltemplatename = 'GENERIC_SHELL';
	}
}

// #############################################################################
// spit out final HTML if we have got this far

if ($templatename != '')
{
	// make navbar
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	// shell template
	eval('$HTML = "' . fetch_template($templatename) . '";');
	eval('print_output("' . fetch_template($shelltemplatename) . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13123 $
|| ####################################################################
\*======================================================================*/
?>