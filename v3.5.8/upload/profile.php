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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
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
	'ranks',
	'noavatarperms'
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

$actiontemplates['none'] =& $actiontemplates['editprofile'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'editprofile';
}

if (!($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
{
	print_no_permission();
}

if (empty($vbulletin->userinfo['userid']))
{
	print_no_permission();
}

// set shell template name
$shelltemplatename = 'USERCP_SHELL';
$templatename = '';

// initialise onload event
$onload = '';

// start the navbar
$navbits = array('usercp.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['user_control_panel']);

($hook = vBulletinHook::fetch_hook('profile_start')) ? eval($hook) : false;

// ############################### start dst autodetect switch ###############################
if ($_POST['do'] == 'dst')
{
	if ($vbulletin->userinfo['dstauto'])
	{
		$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
		$userdata->set_existing($vbulletin->userinfo);

		switch ($vbulletin->userinfo['dstonoff'])
		{
			case 1:
			{
				if ($vbulletin->userinfo['options'] & $vbulletin->bf_misc_useroptions['dstonoff'])
				{
					$userdata->set_bitfield('options', 'dstonoff', 0);
				}
			}
			break;

			case 0:
			{
				if (!($vbulletin->userinfo['options'] & $vbulletin->bf_misc_useroptions['dstonoff']))
				{
					$userdata->set_bitfield('options', 'dstonoff', 1);
				}
			}
			break;
		}

		($hook = vBulletinHook::fetch_hook('profile_dst')) ? eval($hook) : false;

		$userdata->save();
	}

	eval(print_standard_redirect('redirect_dst'));
}

// ############################################################################
// ############################### EDIT PASSWORD ##############################
// ############################################################################

if ($_REQUEST['do'] == 'editpassword')
{
	($hook = vBulletinHook::fetch_hook('profile_editpassword_start')) ? eval($hook) : false;

	// draw cp nav bar
	construct_usercp_nav('password');

	// check for password history retention
	$passwordhistory = $permissions['passwordhistory'];

	// don't let banned people edit their email (see bug 2142)
	if ($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isbannedgroup'])
	{
		$show['edit_email_field'] = false;
		$navbits[''] = $vbphrase['edit_password'];
	}
	else
	{
		$show['edit_email_field'] = true;
		$navbits[''] = $vbphrase['edit_email_and_password'];
	}

	// show Optional because password expired
	$show['password_optional'] = !$show['passwordexpired'];
	$templatename = 'modifypassword';
}

// ############################### start update password ###############################
if ($_POST['do'] == 'updatepassword')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'currentpassword'        => TYPE_STR,
		'currentpassword_md5'    => TYPE_STR,
		'newpassword'            => TYPE_STR,
		'newpasswordconfirm'     => TYPE_STR,
		'newpassword_md5'        => TYPE_STR,
		'newpasswordconfirm_md5' => TYPE_STR,
		'email'                  => TYPE_STR,
		'emailconfirm'           => TYPE_STR
	));

	// instanciate the data manager class
	$userdata =& datamanager_init('user', $vbulletin, ERRTYPE_STANDARD);
	$userdata->set_existing($vbulletin->userinfo);

	($hook = vBulletinHook::fetch_hook('profile_updatepassword_start')) ? eval($hook) : false;

	// validate old password
	if ($userdata->hash_password($userdata->verify_md5($vbulletin->GPC['currentpassword_md5']) ? $vbulletin->GPC['currentpassword_md5'] : $vbulletin->GPC['currentpassword'], $vbulletin->userinfo['salt']) != $vbulletin->userinfo['password'])
	{
		eval(standard_error(fetch_error('badpassword', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'])));
	}

	// update password
	if (!empty($vbulletin->GPC['newpassword']) OR !empty($vbulletin->GPC['newpassword_md5']))
	{
		// are we using javascript-hashed password strings?
		if ($userdata->verify_md5($vbulletin->GPC['newpassword_md5']))
		{
			$vbulletin->GPC['newpassword'] =& $vbulletin->GPC['newpassword_md5'];
			$vbulletin->GPC['newpasswordconfirm'] =& $vbulletin->GPC['newpasswordconfirm_md5'];
		}

		// check that new passwords match
		if ($vbulletin->GPC['newpassword'] != $vbulletin->GPC['newpasswordconfirm'])
		{
			eval(standard_error(fetch_error('passwordmismatch')));
		}

		// check to see if the new password is invalid due to previous use
		if ($userdata->check_password_history($userdata->hash_password($vbulletin->GPC['newpassword'], $vbulletin->userinfo['salt']), $permissions['passwordhistory']))
		{
			eval(standard_error(fetch_error('passwordhistory', $permissions['passwordhistory'])));
		}

		// everything is good - send the singly-hashed MD5 to the password update routine
		$userdata->set('password', ($vbulletin->GPC['newpassword_md5'] ? $vbulletin->GPC['newpassword_md5'] : $vbulletin->GPC['newpassword']));

		// Update cookie if we have one
		$vbulletin->input->clean_array_gpc('c', array(
			COOKIE_PREFIX . 'password' => TYPE_STR,
			COOKIE_PREFIX . 'userid' => TYPE_UINT)
		);

		if (md5($vbulletin->userinfo['password'] . COOKIE_SALT) == $vbulletin->GPC[COOKIE_PREFIX . 'password'] AND
			$vbulletin->GPC[COOKIE_PREFIX . 'userid'] == $vbulletin->userinfo['userid']
		)
		{
			vbsetcookie('password', md5(md5($vbulletin->GPC['newpassword'] . $vbulletin->userinfo['salt']) . COOKIE_SALT), true, true);
		}
	}

	// update email only if user is not banned (see bug 2142) and email is changed
	if (!($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isbannedgroup']) AND $vbulletin->GPC['email'] != $vbulletin->userinfo['email'])
	{
		// check that new email addresses match
		if ($vbulletin->GPC['email'] != $vbulletin->GPC['emailconfirm'])
		{
			eval(standard_error(fetch_error('emailmismatch')));
		}

		// set the email field to be updated
		$userdata->set('email', $vbulletin->GPC['email']);

		// generate an activation ID if required
		if ($vbulletin->options['verifyemail'] AND !can_moderate())
		{
			$userdata->set('usergroupid', 3);
			$userdata->set_info('override_usergroupid', true);

			$activate = true;

			// wait lets check if we have an entry first!
			$activation_exists = $db->query_first("
				SELECT * FROM " . TABLE_PREFIX . "useractivation
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND type = 0
			");

			if (!empty($activation_exists['usergroupid']))
			{
				$usergroupid = $activation_exists['usergroupid'];
			}
			else
			{
				$usergroupid = $vbulletin->userinfo['usergroupid'];
			}
			$activateid = build_user_activation_id($vbulletin->userinfo['userid'], $usergroupid, 0);

			$username = unhtmlspecialchars($vbulletin->userinfo['username']);
			$userid = $vbulletin->userinfo['userid'];

			eval(fetch_email_phrases('activateaccount_change'));
			vbmail($vbulletin->GPC['email'], $subject, $message, true);
		}
		else
		{
			$activate = false;
		}
	}

	($hook = vBulletinHook::fetch_hook('profile_updatepassword_complete')) ? eval($hook) : false;

	// save the data
	$userdata->save();

	if ($activate)
	{
		$vbulletin->url = 'usercp.php' . $vbulletin->session->vars['sessionurl_q'];
		eval(print_standard_redirect('redirect_updatethanks_newemail', true, true));
	}
	else
	{
		$vbulletin->url = 'usercp.php' . $vbulletin->session->vars['sessionurl_q'];
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
	if (trim($vbulletin->userinfo['buddylist']))
	{
		$buddy_ids = preg_split('#\s+#si', trim($vbulletin->userinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
	}
	else
	{
		$buddy_ids = array();
	}
	// get ignore ids
	if (trim($vbulletin->userinfo['ignorelist']))
	{
		$ignore_ids = preg_split('#\s+#si', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
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
		$users = $db->query_read("
			SELECT userid, username FROM " . TABLE_PREFIX . "user
			WHERE userid IN (" . implode(',', $bothlists) . ")
			ORDER BY username
		");
		while ($userinfo = $db->fetch_array($users))
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

	($hook = vBulletinHook::fetch_hook('profile_editlist')) ? eval($hook) : false;

	// draw cp nav bar
	construct_usercp_nav('buddylist');

	$navbits[''] = $vbphrase['buddy_ignore_lists'];
	$templatename = 'modifylist';
}

// ############################### start update list ###############################
if ($_POST['do'] == 'updatelist')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'userlist'	=> TYPE_NOHTML,
		'listbits'	=> TYPE_ARRAY_NOHTML,
	));

	if ($vbulletin->GPC['userlist'] != 'buddy')
	{
		$vbulletin->GPC['userlist'] = 'ignore';
	}
	$var = $vbulletin->GPC['userlist'] . 'list';

	($hook = vBulletinHook::fetch_hook('profile_updatelist_start')) ? eval($hook) : false;

	// cache exiting list user ids
	unset($useridcache);
	$ids = str_replace(' ', ',', trim($vbulletin->userinfo["$var"]));
	if ($ids != '')
	{
		$users = $db->query_read("
			SELECT username, usergroupid, user.userid, moderator.userid as moduserid
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON(user.userid = moderator.userid)
			WHERE user.userid IN($ids)
		");
		while ($user = $db->fetch_array($users))
		{
			$user['username'] = strtolower($user['username']);
			$useridcache["{$user['username']}"] = $user;
		}
	}

	if (sizeof($vbulletin->GPC['listbits']) > 1000)
	{
		eval(standard_error(fetch_error('listlimit')));
	}

	$listids = '';
	foreach ($vbulletin->GPC['listbits'] AS $key => $val)
	{
		$val = $db->escape_string(strtolower($val));

		if (!empty($val))
		{
			($hook = vBulletinHook::fetch_hook('profile_updatelist_user')) ? eval($hook) : false;

			if (!is_array($useridcache["$val"]))
			{
				if ($userid = $db->query_first("
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
				if ($var == 'ignorelist' AND !$vbulletin->options['ignoremods'] AND can_moderate(0, '', $userid['userid'], $uglist) AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
				{
					eval(standard_error(fetch_error('listignoreuser', $userid['username'])));
				}
				else if ($vbulletin->GPC['userlist'] == 'ignore' AND $vbulletin->userinfo['userid'] == $userid['userid']) // this code only prevents users from adding themselves to their ignore list
				// else if ($vbulletin->userinfo['userid'] == $userid['userid']) // this code prevents users from adding themselves to their ignore AND buddy lists
				{
					eval(standard_error(fetch_error('cantlistself_' . $vbulletin->GPC['userlist'])));
				}
				else
				{
					if (empty($done["{$userid['userid']}"]))
					{
						$listids .= " $userid[userid]";
						$done["{$userid['userid']}"] = 1;
					}
				}
			}
			else
			{
				eval(standard_error(fetch_error('listbaduser', $val, $vbulletin->session->vars['sessionurl_q'])));
			}
		}
	}

	$listids = trim($listids);

	require_once(DIR . '/includes/functions_databuild.php');
	build_usertextfields($var, $listids);

	if (is_array($printdebug))
	{
		foreach ($printdebug AS $line => $text)
		{
			$out .= $text;
		}
	}

	($hook = vBulletinHook::fetch_hook('profile_updatelist_complete')) ? eval($hook) : false;

	eval(print_standard_redirect('updatelist_' . $vbulletin->GPC['userlist']));
}

// ############################### start remove from list ###############################
if ($_REQUEST['do'] == 'removelist')
{

	$vbulletin->input->clean_array_gpc('r', array(
		'userid' =>		TYPE_UINT,
		'userlist' =>	TYPE_STR
	));

	if ($vbulletin->GPC['userlist'] != 'buddy')
	{
		$userlist = 'ignore';
		$show['buddylist'] = false;
	}
	else
	{
		$userlist = 'buddy';
		$show['buddylist'] = true;
	}
	$var = $userlist . 'list';

	$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1);

	($hook = vBulletinHook::fetch_hook('profile_removelist')) ? eval($hook) : false;

	$list = explode(' ', trim($vbulletin->userinfo["$var"]));
	if (!in_array($userinfo['userid'], $list))
	{
		eval(standard_error(fetch_error('notonlist', $userinfo['username'])));
	}
	else
	{
		// start the navbar
		$navbits = array('usercp.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['user_control_panel']);
		$navbits[''] = $vbphrase['buddy_ignore_lists'];
		// make navbar
		$navbits = construct_navbits($navbits);
		eval('$navbar = "' . fetch_template('navbar') . '";');

		construct_usercp_nav('buddylist');

		$url =& $vbulletin->url;
		eval('$HTML = "' . fetch_template('modifylist_removeuser') . '";');
		eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
	}

}

// ############################### start do remove from list ###############################
if ($_POST['do'] == 'doremovelist')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'userlist'	=> TYPE_STR,
		'userid'	=> TYPE_UINT,
	));

	if ($vbulletin->GPC['userlist'] != 'buddy')
	{
		$vbulletin->GPC['userlist'] = 'ignore';
	}
	$var = $vbulletin->GPC['userlist'] . 'list';

	$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1);
	$userid = $userinfo['userid'];

	($hook = vBulletinHook::fetch_hook('profile_doremovelist')) ? eval($hook) : false;

	$splitlist = explode(' ', $vbulletin->userinfo["$var"]);

	foreach ($splitlist AS $key => $val)
	{
		if ($val == $userid)
		{
			unset($splitlist["$key"]);
		}
	}

	$vbulletin->userinfo["$var"] = implode(' ', $splitlist);
	$vbulletin->userinfo["$var"] = trim($vbulletin->userinfo["$var"]);

	require_once(DIR . '/includes/functions_databuild.php');
	build_usertextfields($var, $vbulletin->userinfo["$var"]);

	eval(print_standard_redirect('removelist_' . $vbulletin->GPC['userlist']));

}

// ############################### start add to list ###############################
if ($_REQUEST['do'] == 'addlist')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid'	=> TYPE_UINT,
		'userlist'	=> TYPE_STR
	));

	if ($vbulletin->GPC['userlist'] != 'buddy')
	{
		$userlist = 'ignore';
		$show['buddylist'] = false;
	}
	else
	{
		$userlist = 'buddy';
		$show['buddylist'] = true;
	}
	$var = $userlist . 'list';

	if ($userlist == 'ignore' AND $vbulletin->userinfo['userid'] == $vbulletin->GPC['userid'])
	{
		eval(standard_error(fetch_error("cantlistself_$userlist")));
	}

	$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1);
	$userid = $userinfo['userid'];
	$uglist = $userinfo['usergroupid'] . iif(trim($userinfo['membergroupids']), ",$userinfo[membergroupids]");

	if ($var == 'ignorelist' AND !$vbulletin->options['ignoremods'] AND can_moderate(0, '', $userid, $uglist) AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		eval(standard_error(fetch_error('listignoreuser', $userinfo['username'])));
	}

	($hook = vBulletinHook::fetch_hook('profile_addlist')) ? eval($hook) : false;

	// start the navbar
	$navbits = array('usercp.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['user_control_panel']);
	$navbits[''] = $vbphrase['buddy_ignore_lists'];
	// make navbar
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	construct_usercp_nav('buddylist');

	$url =& $vbulletin->url;
	eval('$HTML = "' . fetch_template('modifylist_adduser') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

}

// ############################### start do add to list ###############################
if ($_POST['do'] == 'doaddlist')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid'	=> TYPE_UINT,
		'userlist'	=> TYPE_STR,
	));

	if ($vbulletin->GPC['userlist'] != 'buddy')
	{
		$vbulletin->GPC['userlist'] = 'ignore';
	}
	$var = $vbulletin->GPC['userlist'] . 'list';

	if ($vbulletin->GPC['userlist'] == 'ignore' AND $vbulletin->userinfo['userid'] == $vbulletin->GPC['userid'])
	{
		eval(standard_error(fetch_error('cantlistself_' . $vbulletin->GPC['userlist'])));
	}

	$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1);
	$userid = $userinfo['userid'];
	$uglist = $userinfo['usergroupid'] . iif(trim($userinfo['membergroupids']), ",$userinfo[membergroupids]");

	($hook = vBulletinHook::fetch_hook('profile_doaddlist_start')) ? eval($hook) : false;

	if ($var == 'ignorelist' AND !$vbulletin->options['ignoremods'] AND can_moderate(0, '', $userid, $uglist) AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		eval(standard_error(fetch_error('listignoreuser', $userinfo['username'])));
	}

	$splitlist = explode(' ', $vbulletin->userinfo["$var"]);

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
		$vbulletin->userinfo["$var"] .= " $userid";
	}
	$vbulletin->userinfo["$var"] = trim($vbulletin->userinfo["$var"]);

	require_once(DIR . '/includes/functions_databuild.php');
	build_usertextfields($var, $vbulletin->userinfo["$var"]);

	($hook = vBulletinHook::fetch_hook('profile_doaddlist_complete')) ? eval($hook) : false;

	eval(print_standard_redirect('addlist_' . $vbulletin->GPC['userlist']));

}

// ############################################################################
// ALL FUNCTIONS BELOW HERE REQUIRE 'canmodifyprofile' PERMISSION, SO CHECK IT

if (!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canmodifyprofile']) AND !$templatename)
{
	print_no_permission();
}

// ############################################################################
// ############################### EDIT PROFILE ###############################
// ############################################################################
if ($_REQUEST['do'] == 'editprofile')
{
	unset($tempcustom); // from functions_user.php?

	($hook = vBulletinHook::fetch_hook('profile_editprofile_start')) ? eval($hook) : false;

	exec_switch_bg();
	// Set birthday fields right here!
	if (empty($vbulletin->userinfo['birthday']))
	{
		$dayselected['default'] = 'selected="selected"';
		$monthselected['default'] = 'selected="selected"';
	}
	else
	{
		$birthday = explode('-', $vbulletin->userinfo['birthday']);

		$dayselected["$birthday[1]"] = 'selected="selected"';
		$monthselected["$birthday[0]"] = 'selected="selected"';

		if (date('Y') > $birthday[2] AND $birthday[2] != '0000')
		{
			$year = $birthday[2];
		}
	}
	$sbselected = array($vbulletin->userinfo['showbirthday'] => 'selected="selected"');

	// custom user title
	if ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle'])
	{
		exec_switch_bg();
		if ($vbulletin->userinfo['customtitle'] == 2)
		{
			$vbulletin->userinfo['usertitle'] = htmlspecialchars_uni($vbulletin->userinfo['usertitle']);
		}
		$show['customtitleoption'] = true;
	}
	else
	{
		$show['customtitleoption'] = false;
	}

	require_once(DIR . '/includes/functions_misc.php');
	// Set birthday required or optional
	$show['birthday_readonly'] = false;
	if ($vbulletin->options['reqbirthday'])
	{
		$show['birthday_required'] = true;
		if ($birthday[2] > 1901 AND $birthday[2] < date('Y') AND @checkdate($birthday[0], $birthday[1], $birthday[2]))
		{
			$vbulletin->options['calformat1'] = mktimefix($vbulletin->options['calformat1'], $birthday[2]);
			if ($birthday[2] >= 1970)
			{
				$yearpass = $birthday[2];
			}
			else
			{
				// day of the week patterns repeat every 28 years, so
				// find the first year >= 1970 that has this pattern
				$yearpass = $birthday[2] + 28 * ceil((1970 - $birthday[2]) / 28);
			}
			$birthdate = vbdate($vbulletin->options['calformat1'], mktime(0, 0, 0, $birthday[0], $birthday[1], $yearpass), false, true, false);
			$show['birthday_readonly'] = true;
		}
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
	$vbulletin->input->clean_array_gpc('p', array(
		// coppa stuff
		'coppauser'    => TYPE_BOOL,
		'parentemail'  => TYPE_STR,
		// IM handles / homepage
		'aim'          => TYPE_STR,
		'yahoo'        => TYPE_STR,
		'icq'          => TYPE_STR,
		'msn'          => TYPE_STR,
		'skype'        => TYPE_STR,
		'homepage'     => TYPE_STR,
		// user title
		'resettitle'   => TYPE_STR,
		'customtext'   => TYPE_STR,
		// birthday fields
		'day'          => TYPE_INT,
		'month'        => TYPE_INT,
		'year'         => TYPE_INT,
		'oldbirthday'  => TYPE_STR,
		'showbirthday' => TYPE_UINT,
		// redirect button
		'gotopassword' => TYPE_NOCLEAN,
		// custom profile fields
		'userfield'    => TYPE_ARRAY,
	));

	// init user data manager
	$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
	$userdata->set_existing($vbulletin->userinfo);

	// coppa stuff
	$userdata->set_info('coppauser', $vbulletin->GPC['coppauser']);
	$userdata->set('parentemail', $vbulletin->GPC['parentemail']);

	// easy stuff
	$userdata->set('icq', $vbulletin->GPC['icq']);
	$userdata->set('msn', $vbulletin->GPC['msn']);
	$userdata->set('aim', $vbulletin->GPC['aim']);
	$userdata->set('yahoo', $vbulletin->GPC['yahoo']);
	$userdata->set('skype', $vbulletin->GPC['skype']);
	$userdata->set('homepage', $vbulletin->GPC['homepage']);
	$userdata->set('birthday', $vbulletin->GPC);
	$userdata->set('showbirthday', $vbulletin->GPC['showbirthday']);

	// custom profile fields
	$userdata->set_userfields($vbulletin->GPC['userfield']);

	if ($vbulletin->userinfo['usertitle'] != $vbulletin->GPC['customtext'] AND
		!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND
		$vbulletin->options['ctMaxChars'] > 0
	)
	{
		// only trim title if changing custom title and not an admin
		$vbulletin->GPC['customtext'] = substr($vbulletin->GPC['customtext'], 0, $vbulletin->options['ctMaxChars']);
	}

	// custom user title
	$userdata->set_usertitle(
		$vbulletin->GPC['customtext'],
		$vbulletin->GPC['resettitle'],
		$vbulletin->usergroupcache[$vbulletin->userinfo['displaygroupid']],
		($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
		($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) ? true : false
	);

	($hook = vBulletinHook::fetch_hook('profile_updateprofile')) ? eval($hook) : false;

	// save the data
	$userdata->save();

	if (empty($vbulletin->GPC['gotopassword']))
	{
		$vbulletin->url = 'usercp.php' . $vbulletin->session->vars['sessionurl_q'];
	}
	else
	{
		$vbulletin->url = 'profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editpassword';
	}

	eval(print_standard_redirect('redirect_updatethanks'));
}

// ############################################################################
// ############################### EDIT OPTIONS ###############################
// ############################################################################
if ($_REQUEST['do'] == 'editoptions')
{
	require_once(DIR . '/includes/functions_misc.php');

	($hook = vBulletinHook::fetch_hook('profile_editoptions_start')) ? eval($hook) : false;

	// check the appropriate checkboxes
	$checked = array();
	foreach ($vbulletin->userinfo AS $key => $val)
	{
		if ($val != 0)
		{
			$checked["$key"] = 'checked="checked"';
		}
		else

		{
			$checked["$key"] = '';
		}
	}

	// invisible option
	$show['invisibleoption'] = iif(bitwise($permissions['genericpermissions'], $vbulletin->bf_ugp_genericpermissions['caninvisible']), true, false);

	// reputation options
	if ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canhiderep'] AND $vbulletin->options['reputationenable'])
	{
		if ($vbulletin->userinfo['showreputation'])
		{
			$checked['showreputation'] = 'checked="checked"';
		}
		$show['reputationoption'] = true;
	}
	else
	{
		$show['reputationoption'] = false;
	}

	// PM options
	$show['pmoptions'] = iif($vbulletin->options['enablepms'] AND $permissions['pmquota'] > 0, true, false);

	// autosubscribe selected option
	$vbulletin->userinfo['autosubscribe'] = verify_subscription_choice($vbulletin->userinfo['autosubscribe'], $vbulletin->userinfo, 9999);
	$emailchecked = array($vbulletin->userinfo['autosubscribe'] => 'selected="selected"');

	// threaded mode options
	if ($vbulletin->userinfo['threadedmode'] == 1 OR $vbulletin->userinfo['threadedmode'] == 2)
	{
		$threaddisplaymode["{$vbulletin->userinfo['threadedmode']}"] = 'selected="selected"';
	}
	else
	{
		if ($vbulletin->userinfo['postorder'] == 0)
		{
			$threaddisplaymode[0] = 'selected="selected"';
		}
		else
		{
			$threaddisplaymode[3] = 'selected="selected"';
		}
	}

	// default days prune
	if ($vbulletin->userinfo['daysprune'] == 0)
	{
		$daysdefaultselected = 'selected="selected"';
	}
	else
	{
		if ($vbulletin->userinfo['daysprune'] == '-1')
		{
			$vbulletin->userinfo['daysprune'] = 'all';
		}
		$dname = 'days' . $vbulletin->userinfo['daysprune'] . 'selected';
		$$dname = 'selected="selected"';
	}

	// daylight savings time
	$selectdst = array();
	if ($vbulletin->userinfo['dstauto'])
	{
		$selectdst[2] = 'selected="selected"';
	}
	else if ($vbulletin->userinfo['dstonoff'])
	{
		$selectdst[1] = 'selected="selected"';
	}
	else
	{
		$selectdst[0] = 'selected="selected"';
	}

	require_once(DIR . '/includes/functions_misc.php');
	$timezoneoptions = '';
	foreach (fetch_timezone() AS $optionvalue => $timezonephrase)
	{
		$optiontitle = $vbphrase["$timezonephrase"];
		$optionselected = iif($optionvalue == $vbulletin->userinfo['timezoneoffset'], 'selected="selected"', '');
		eval('$timezoneoptions .= "' . fetch_template('option') . '";');
	}
	eval('$timezoneoptions = "' . fetch_template('modifyoptions_timezone') . '";');

	// start of the week
	if ($vbulletin->userinfo['startofweek'] > 0)
	{
		$dname = 'day' . $vbulletin->userinfo['startofweek'] . 'selected';
		$$dname = 'selected="selected"';
	}
	else
	{
		$day1selected = 'selected="selected"';
	}

	// bb code editor options
	if (!is_array($vbulletin->options['editormodes_array']))
	{
		$vbulletin->options['editormodes_array'] = unserialize($vbulletin->options['editormodes']);
	}
	$max_editormode = max($vbulletin->options['editormodes_array']);
	if ($vbulletin->userinfo['showvbcode'] > $max_editormode)
	{
		$vbulletin->userinfo['showvbcode'] = $max_editormode;
	}
	$show['editormode_picker'] = $max_editormode ? true : false;
	$show['editormode_wysiwyg'] = $max_editormode > 1 ? true : false;
	$checkvbcode = array($vbulletin->userinfo['showvbcode'] => ' checked="checked"');
	$selectvbcode = array($vbulletin->userinfo['showvbcode'] => ' selected="selected"');

	//MaxPosts by User
	$optionArray = explode(',', $vbulletin->options['usermaxposts']);
	$foundmatch = 0;
	foreach ($optionArray AS $optionvalue)
	{
		if ($optionvalue == $vbulletin->userinfo['maxposts'])
		{
			$optionselected = 'selected="selected"';
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
		$postsdefaultselected = 'selected="selected"';
	}

	if ($vbulletin->options['allowchangestyles'])
	{
		$stylecount = 0;
		if ($vbulletin->stylecache !== null)
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
			$optionselected = iif($vbulletin->userinfo['languageid'] == $optionvalue, 'selected="selected"', '');
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
	require_once(DIR . '/includes/functions_misc.php');
	$vbulletin->input->clean_array_gpc('p', array(
		'newstyleset' => TYPE_INT,
		'dst' => TYPE_INT,
		'showvbcode' => TYPE_INT,
		'pmpopup' => TYPE_INT,
		'umaxposts' => TYPE_INT,
		'prunedays' => TYPE_INT,
		'timezoneoffset' => TYPE_NUM,
		'startofweek' => TYPE_INT,
		'languageid' => TYPE_INT,
		'threadedmode' => TYPE_INT,
		'invisible' => TYPE_INT,
		'autosubscribe' => TYPE_INT,
		'options' => TYPE_ARRAY_BOOL,
		'set_options' => TYPE_ARRAY_BOOL,
		'modifyavatar' => TYPE_NOCLEAN,
		'userfield' => TYPE_ARRAY
	));

	$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
	$userdata->set_existing($vbulletin->userinfo);

	// reputation
	if (!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canhiderep']))
	{
		$vbulletin->GPC['options']['showreputation'] = 1;
	}

	// options bitfield
	foreach ($vbulletin->bf_misc_useroptions AS $key => $val)
	{
		if (isset($vbulletin->GPC['options']["$key"]) OR isset($vbulletin->GPC['set_options']["$key"]))
		{
			$value = $vbulletin->GPC['options']["$key"];
			$userdata->set_bitfield('options', $key, $value);
		}
	}

	// style set
	if ($vbulletin->userinfo['realstyleid'] != $vbulletin->GPC['newstyleset'])
	{
		$userdata->set('styleid', $vbulletin->GPC['newstyleset']);
	}

	// language
	$userdata->set('languageid', $vbulletin->GPC['languageid']);

	// autosubscribe
	$userdata->set('autosubscribe', $vbulletin->GPC['autosubscribe']);

	// threaded mode
	$userdata->set('threadedmode', $vbulletin->GPC['threadedmode']);

	// time zone offset
	$userdata->set('timezoneoffset', $vbulletin->GPC['timezoneoffset']);

	$userdata->set('showvbcode', $vbulletin->GPC['showvbcode']);
	$userdata->set('pmpopup', $vbulletin->GPC['pmpopup']);
	$userdata->set('maxposts', $vbulletin->GPC['umaxposts']);
	$userdata->set('daysprune', $vbulletin->GPC['prunedays']);
	$userdata->set('startofweek', $vbulletin->GPC['startofweek']);

	// custom profile fields
	$userdata->set_userfields($vbulletin->GPC['userfield']);

	// daylight savings
	$userdata->set_dst($vbulletin->GPC['dst']);

	($hook = vBulletinHook::fetch_hook('profile_updateoptions')) ? eval($hook) : false;

	$userdata->save();

	if (!empty($vbulletin->GPC['modifyavatar']))
	{
		$vbulletin->url = 'profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editavatar';
	}
	else
	{
		$vbulletin->url = 'usercp.php' . $vbulletin->session->vars['sessionurl_q'];
	}

	eval(print_standard_redirect('redirect_updatethanks'));

}

// ############################################################################
// ############################## EDIT SIGNATURE ##############################
// ############################################################################

// ########################### start update signature #########################
if ($_POST['do'] == 'updatesignature')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'wysiwyg'		=> TYPE_BOOL,
		'message'		=> TYPE_STR,
		'preview'		=> TYPE_STR,
	));

	if (!$vbulletin->options['allowsignatures'])
	{
		eval(standard_error(fetch_error('signaturedisabled')));
	}

	if (!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusesignature']))
	{
		eval(standard_error(fetch_error('nosignaturepermission')));
	}

	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$signature = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $vbulletin->options['allowhtml']);
	}
	else
	{
		$signature = $vbulletin->GPC['message'];
	}

	($hook = vBulletinHook::fetch_hook('profile_updatesignature_start')) ? eval($hook) : false;

	if ($vbulletin->options['maximages'])
	{
		require_once(DIR . '/includes/class_bbcode_alt.php');
		$bbcode_parser =& new vB_BbCodeParser_ImgCheck($vbulletin, fetch_tag_list());
		$parsedsig = $bbcode_parser->parse($signature, 0, $vbulletin->options['allowsmilies'], true);

		require_once(DIR . '/includes/functions_misc.php');
		$imagecount = fetch_character_count($parsedsig, '<img');
		if ($imagecount > $vbulletin->options['maximages'])
		{
			$vbulletin->GPC['preview'] = 'true';
			$errors[] = fetch_error('toomanyimages', $imagecount, $vbulletin->options['maximages']);
		}
	}
	if ($vbulletin->options['sigmax'] AND (vbstrlen(strip_bbcode($signature, false, false, false)) > $vbulletin->options['sigmax']))
	{
		$vbulletin->GPC['preview'] = 'true';
		$errors[] = fetch_error('sigtoolong', $vbulletin->options['sigmax']);
	}

	// add # to color tags using hex if it's not there
	$signature = preg_replace('#\[color=(&quot;|"|\'|)([a-f0-9]{6})\\1]#i', '[color=\1#\2\1]', $signature);

	if ($vbulletin->options['allowbbcode'])
	{
		require_once(DIR . '/includes/functions_newpost.php');
		$signature = convert_url_to_bbcode($signature);
	}

	if ($vbulletin->GPC['preview'] != '')
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

		require_once(DIR . '/includes/class_bbcode.php');
		$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
		$previewmessage = $bbcode_parser->parse($signature, 0, $vbulletin->options['allowsmilies']);

		// save a conditional by just overwriting the phrase
		$vbphrase['submit_message'] =& $vbphrase['save_signature'];
		eval('$preview = "' . fetch_template('newpost_preview') . '";');
		$_REQUEST['do'] = 'editsignature';
	}
	else
	{
		// init user data manager
		$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
		$userdata->set_existing($vbulletin->userinfo);

		$userdata->set('signature', $signature);

		($hook = vBulletinHook::fetch_hook('profile_updatesignature_complete')) ? eval($hook) : false;

		$userdata->save();

		$vbulletin->url = 'usercp.php' . $vbulletin->session->vars['sessionurl_q'];
		eval(print_standard_redirect('redirect_updatethanks'));
	}
}

// ############################ start edit signature ##########################
if ($_REQUEST['do'] == 'editsignature')
{
	require_once(DIR . '/includes/functions_newpost.php');

	if (!$vbulletin->options['allowsignatures'])
	{
		eval(standard_error(fetch_error('signaturedisabled')));
	}

	if (!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusesignature']))
	{
		eval(standard_error(fetch_error('nosignaturepermission')));
	}

	($hook = vBulletinHook::fetch_hook('profile_editsignature_start')) ? eval($hook) : false;

	$htmlcodeon = iif($vbulletin->options['allowhtml'], $vbphrase['on'], $vbphrase['off']);
	$bbcodeon = iif($vbulletin->options['allowbbcode'], $vbphrase['on'], $vbphrase['off']);
	$imgcodeon = iif($vbulletin->options['allowbbimagecode'], $vbphrase['on'], $vbphrase['off']);
	$smilieson = iif($vbulletin->options['allowsmilies'], $vbphrase['on'], $vbphrase['off']);

	// only show posting code allowances in forum rules template
	$show['codeonly'] = true;

	eval('$forumrules = "' . fetch_template('forumrules') . '";');

	if ($vbulletin->GPC['preview'] == '')
	{
		$signature = $vbulletin->userinfo['signature'];
	}

	if ($signature)
	{
		require_once(DIR . '/includes/class_bbcode.php');
		$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
		$previewmessage = $bbcode_parser->parse($signature, 0, $vbulletin->options['allowsmilies']);

		// save a conditional by just overwriting the phrase
		$vbphrase['submit_message'] =& $vbphrase['save_signature'];
		eval('$preview = "' . fetch_template('newpost_preview') . '";');
	}

	require_once(DIR . '/includes/functions_editor.php');

	// set message box width to usercp size
	$stylevar['messagewidth'] = $stylevar['messagewidth_usercp'];
	$editorid = construct_edit_toolbar(htmlspecialchars_uni($signature), 0, 0, $vbulletin->options['allowsmilies']);

	construct_usercp_nav('signature');

	$navbits[''] = $vbphrase['edit_signature'];
	$templatename = 'modifysignature';
}

// ############################################################################
// ############################### EDIT AVATAR ################################
// ############################################################################
if ($_REQUEST['do'] == 'editavatar')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber'	=> TYPE_UINT,
		'categoryid'	=> TYPE_UINT
	));

	if (!$vbulletin->options['avatarenabled'])
	{
		eval(standard_error(fetch_error('avatardisabled')));
	}

	($hook = vBulletinHook::fetch_hook('profile_editavatar_start')) ? eval($hook) : false;

	// initialise vars
	$avatarchecked["{$vbulletin->userinfo['avatarid']}"] = 'checked="checked"';
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
	if ($vbulletin->userinfo['avatarid'])
	{
	// using a predefined avatar

		$avatar = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "avatar WHERE avatarid = " . $vbulletin->userinfo['avatarid']);
		$avatarid =& $avatar['avatarid'];
		eval('$currentavatar = "' . fetch_template('modifyavatarbit') . '";');
		// store avatar info in $bbavatar for later use
		$bbavatar = $avatar;
	}
	else
	{
	// not using a predefined avatar, check for custom

		if ($avatar = $db->query_first("SELECT dateline, width, height FROM " . TABLE_PREFIX . "customavatar WHERE userid=" . $vbulletin->userinfo['userid']))
		{
		// using a custom avatar
			if ($vbulletin->options['usefileavatar'])
			{
				$vbulletin->userinfo['avatarurl'] = $vbulletin->options['avatarurl'] . '/avatar' . $vbulletin->userinfo['userid'] . '_' . $vbulletin->userinfo['avatarrevision'] . '.gif';
			}
			else
			{
				$vbulletin->userinfo['avatarurl'] = 'image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $vbulletin->userinfo['userid'] . "&amp;dateline=$avatar[dateline]";
			}
			if ($avatar['width'] AND $avatar['height'])
			{
				$vbulletin->userinfo['avatarurl'] .= "\" width=\"$avatar[width]\" height=\"$avatar[height]";
			}
			eval('$currentavatar = "' . fetch_template('modifyavatarbit_custom') . '";');
		}
		else
		{
		// no avatar specified
			$nouseavatarchecked = 'checked="checked"';
			$avatarchecked[0] = '';
			eval('$currentavatar = "' . fetch_template('modifyavatarbit_noavatar') . '";');
		}
	}
	// get rid of any lingering $avatar variables
	unset($avatar);

	$membergroups = fetch_membergroupids_array($vbulletin->userinfo);

	// ############### DISPLAY AVATAR CATEGORIES ###############
	// get all the available avatar categories
	$avperms = $db->query_read("
		SELECT imagecategorypermission.imagecategoryid, usergroupid
		FROM " . TABLE_PREFIX . "imagecategorypermission AS imagecategorypermission, " . TABLE_PREFIX . "imagecategory AS imagecategory
		WHERE imagetype = 1
			AND imagecategorypermission.imagecategoryid = imagecategory.imagecategoryid
		ORDER BY imagecategory.displayorder
	");
	$noperms = array();
	while ($avperm = $db->fetch_array($avperms))
	{
		$noperms["{$avperm['imagecategoryid']}"][] = $avperm['usergroupid'];
	}
	foreach($noperms AS $imagecategoryid => $usergroups)
	{
		if (!count(array_diff($membergroups, $usergroups)))
		{
			$badcategories .= ",$imagecategoryid";
		}
	}

	$categories = $db->query_read("
		SELECT imagecategory.*, COUNT(avatarid) AS avatars
		FROM " . TABLE_PREFIX . "imagecategory AS imagecategory
		LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON
			(avatar.imagecategoryid=imagecategory.imagecategoryid)
		WHERE imagetype=1
		AND avatar.minimumposts <= " . $vbulletin->userinfo['posts'] . "
		AND avatar.avatarid <> " . $vbulletin->userinfo['avatarid'] . "
		AND imagecategory.imagecategoryid NOT IN (0$badcategories)
		GROUP BY imagecategory.imagecategoryid
		ORDER BY imagecategory.displayorder
	");

	while ($category = $db->fetch_array($categories))
	{
		if ($category['avatars'])
		{
			// only get categories containing at least one avatar
			if (!$donefirstcategory OR $category['imagecategoryid'] == $vbulletin->GPC['categoryid'])
			{
				$displaycategory = $category;
				$donefirstcategory = 1;
			}
			$categorycache["{$category['imagecategoryid']}"] = $category;
		}
	}
	unset($category);
	$db->free_result($categories);

	// get the id of the avatar category we want to display
	if ($vbulletin->GPC['categoryid'] == 0)
	{

		if ($vbulletin->userinfo['avatarid'] != 0 AND !empty($categorycache["{$bbavatar['imagecategoryid']}"]))
		{
			$displaycategory = $bbavatar;
		}
		$vbulletin->GPC['categoryid'] = $displaycategory['imagecategoryid'];
	}

	// make the category <select> list
	$optionselected["{$vbulletin->GPC['categoryid']}"] = 'selected="selected"';
	if (count($categorycache) > 1)
	{
		$show['categories'] = true;
		foreach ($categorycache AS $category)
		{
			$thiscategoryid = $category['imagecategoryid'];
			$selected = iif($thiscategoryid == $vbulletin->GPC['categoryid'], 'selected="selected"', '');
			eval('$categorybits .= "' . fetch_template('modifyavatar_category') . '";');
		}
	}
	else
	{
		$show['categories'] = false;
		$categorybits = '';
	}

	// ############### GET TOTAL NUMBER OF AVATARS IN THIS CATEGORY ###############
	// get the total number of avatars in this category
	$totalavatars = $categorycache["{$vbulletin->GPC['categoryid']}"]['avatars'];

	// get perpage parameters for table display
	$perpage = $vbulletin->options['numavatarsperpage'];
	sanitize_pageresults($totalavatars, $vbulletin->GPC['pagenumber'], $perpage, 100, 25);
	// get parameters for query limits
	$startat = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;

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
		$pagenav = construct_page_nav($vbulletin->GPC['pagenumber'], $perpage, $totalavatars, 'profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editavatar&amp;categoryid=' . $vbulletin->GPC['categoryid']);

		$avatars = $db->query_read("
			SELECT avatar.*, imagecategory.title AS category
			FROM " . TABLE_PREFIX . "avatar AS avatar LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
			WHERE minimumposts <= " . $vbulletin->userinfo['posts'] . "
			AND avatar.imagecategoryid=" . $vbulletin->GPC['categoryid'] . "
			AND avatarid <> " . $vbulletin->userinfo['avatarid'] . "
			ORDER BY avatar.displayorder
			LIMIT $startat,$perpage
		");
		$avatarsonthispage = $db->num_rows($avatars);

		$cols = intval($vbulletin->options['numavatarswide']);
		$cols = iif($cols, $cols, 5);
		$cols = iif($cols > $avatarsonthispage, $avatarsonthispage, $cols);

		$bits = array();
		while ($avatar = $db->fetch_array($avatars))
		{
			$categoryname = $avatar['category'];
			$avatarid =& $avatar['avatarid'];

			($hook = vBulletinHook::fetch_hook('profile_editavatar_bit')) ? eval($hook) : false;

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
	require_once(DIR . '/includes/functions_file.php');
	$inimaxattach = fetch_max_upload_size();

	if ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'])
	{
		$show['customavatar'] = true;
		$permissions['avatarmaxsize'] = vb_number_format($permissions['avatarmaxsize'], 1, true);

		$maxnote = '';
		if ($permissions['avatarmaxsize'] AND ($permissions['avatarmaxwidth'] OR $permissions['avatarmaxheight']))
		{
			$maxnote = construct_phrase($vbphrase['note_maximum_size_x_y_or_z'], $permissions['avatarmaxwidth'], $permissions['avatarmaxheight'], $permissions['avatarmaxsize']);
		}
		else if ($permissions['avatarmaxsize'])
		{
			$maxnote = construct_phrase($vbphrase['note_maximum_size_x'], $permissions['avatarmaxsize']);
		}
		else if ($permissions['avatarmaxwidth'] OR $permissions['avatarmaxheight'])
		{
			$maxnote = construct_phrase($vbphrase['note_maximum_size_x_y_pixels'], $permissions['avatarmaxwidth'], $permissions['avatarmaxheight']);
		}
		$show['maxnote'] = (!empty($maxnote)) ? true : false;
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
	($hook = vBulletinHook::fetch_hook('profile_editprofilepic')) ? eval($hook) : false;

	if ($vbulletin->options['profilepicenabled'] AND ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canprofilepic']))
	{
		if ($profilepic = $db->query_first("
			SELECT userid, dateline, height, width
			FROM " . TABLE_PREFIX . "customprofilepic
			WHERE userid = " . $vbulletin->userinfo['userid']
		))
		{
			if ($vbulletin->options['usefileavatar'])
			{
				$vbulletin->userinfo['profileurl'] = $vbulletin->options['profilepicurl'] . '/profilepic' . $vbulletin->userinfo['userid'] . '_' . $vbulletin->userinfo['profilepicrevision'] . '.gif';
			}
			else
			{
				$vbulletin->userinfo['profileurl'] = 'image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $vbulletin->userinfo['userid'] . "&amp;dateline=$profilepic[dateline]&amp;type=profile";
			}

			if ($profilepic['width'] AND $profilepic['height'])
			{
				$vbulletin->userinfo['profileurl'] .= "\" width=\"$profilepic[width]\" height=\"$profilepic[height]";
			}
			$show['profilepic'] = true;
		}

		$permissions['profilepicmaxsize'] = vb_number_format($permissions['profilepicmaxsize'], 1, true);

		$maxnote = '';
		if ($permissions['profilepicmaxsize'] AND ($permissions['profilepicmaxwidth'] OR $permissions['profilepicmaxheight']))
		{
			$maxnote = construct_phrase($vbphrase['note_maximum_size_x_y_or_z'], $permissions['profilepicmaxwidth'], $permissions['profilepicmaxheight'], $permissions['profilepicmaxsize']);
		}
		else if ($permissions['profilepicmaxsize'])
		{
			$maxnote = construct_phrase($vbphrase['note_maximum_size_x'], $permissions['profilepicmaxsize']);
		}
		else if ($permissions['profilepicmaxwidth'] OR $permissions['profilepicmaxheight'])
		{
			$maxnote = construct_phrase($vbphrase['note_maximum_size_x_y_pixels'], $permissions['profilepicmaxwidth'], $permissions['profilepicmaxheight']);
		}
		$show['maxnote'] = (!empty($maxnote)) ? true : false;


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
	if (!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canmodifyprofile']))
	{
		print_no_permission();
	}

	if (!$vbulletin->options['avatarenabled'])
	{
		eval(standard_error(fetch_error('avatardisabled')));
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'avatarid'  => TYPE_INT,
		'avatarurl' => TYPE_STR,
	));

	($hook = vBulletinHook::fetch_hook('profile_updateavatar_start')) ? eval($hook) : false;

	$useavatar = iif($vbulletin->GPC['avatarid'] == -1, 0, 1);

	if ($useavatar)
	{
		if ($vbulletin->GPC['avatarid'] == 0)
		{
			$vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);

			// begin custom avatar code
			require_once(DIR . '/includes/class_upload.php');
			require_once(DIR . '/includes/class_image.php');

			$upload = new vB_Upload_Userpic($vbulletin);

			$upload->data =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_STANDARD, 'userpic');
			$upload->image =& vB_Image::fetch_library($vbulletin);
			$upload->maxwidth = $vbulletin->userinfo['permissions']['avatarmaxwidth'];
			$upload->maxheight = $vbulletin->userinfo['permissions']['avatarmaxheight'];
			$upload->maxuploadsize = $vbulletin->userinfo['permissions']['avatarmaxsize'];
			$upload->allowanimation = ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cananimateavatar']) ? true : false;

			if (!$upload->process_upload($vbulletin->GPC['avatarurl']))
			{
				eval(standard_error($upload->fetch_error()));
			}
		}
		else
		{
			// start predefined avatar code
			$vbulletin->GPC['avatarid'] = verify_id('avatar', $vbulletin->GPC['avatarid']);
			$avatarinfo = $db->query_first("
				SELECT avatarid, minimumposts, imagecategoryid
				FROM " . TABLE_PREFIX . "avatar
				WHERE avatarid = " . $vbulletin->GPC['avatarid']
			);

			if ($avatarinfo['minimumposts'] > $vbulletin->userinfo['posts'])
			{
				// not enough posts error
				eval(standard_error(fetch_error('avatarmoreposts')));
			}

			$membergroups = fetch_membergroupids_array($vbulletin->userinfo);

			$avperms = $db->query_read("
				SELECT usergroupid
				FROM " . TABLE_PREFIX . "imagecategorypermission
				WHERE imagecategoryid = $avatarinfo[imagecategoryid]
			");

			$noperms = array();
			while ($avperm = $db->fetch_array($avperms))
			{
				$noperms[] = $avperm['usergroupid'];
			}
			if (!count(array_diff($membergroups, $noperms)))
			{
				eval(standard_error(fetch_error('invalid_avatar_specified')));
			}

			$userpic =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_STANDARD, 'userpic');
			$userpic->condition = 'userid = ' . $vbulletin->userinfo['userid'];
			$userpic->delete();

			// end predefined avatar code
		}
	}
	else
	{
		// not using an avatar

		$vbulletin->GPC['avatarid'] = 0;
		$userpic =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_STANDARD, 'userpic');
		$userpic->condition = 'userid = ' . $vbulletin->userinfo['userid'];
		$userpic->delete();
	}

	// init user data manager
	$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
	$userdata->set_existing($vbulletin->userinfo);

	$userdata->set('avatarid', $vbulletin->GPC['avatarid']);

	($hook = vBulletinHook::fetch_hook('profile_updateavatar_complete')) ? eval($hook) : false;

	$userdata->save();

	$vbulletin->url = 'profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editavatar';
	eval(print_standard_redirect('redirect_updatethanks'));

}

// ############################### start update profile pic###########################
if ($_POST['do'] == 'updateprofilepic')
{

	if (!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canprofilepic']))
	{
		print_no_permission();
	}

	if (!$vbulletin->options['profilepicenabled'])
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'deleteprofilepic' => TYPE_BOOL,
		'avatarurl'        => TYPE_STR,
	));

	($hook = vBulletinHook::fetch_hook('profile_updateprofilepic_start')) ? eval($hook) : false;

	if ($vbulletin->GPC['deleteprofilepic'])
	{
		$userpic =& datamanager_init('Userpic_Profilepic', $vbulletin, ERRTYPE_STANDARD, 'userpic');
		$userpic->condition = "userid = " . $vbulletin->userinfo['userid'];
		$userpic->delete();
	}
	else
	{
		$vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);

		require_once(DIR . '/includes/class_upload.php');
		require_once(DIR . '/includes/class_image.php');

		$upload = new vB_Upload_Userpic($vbulletin);

		$upload->data =& datamanager_init('Userpic_Profilepic', $vbulletin, ERRTYPE_STANDARD, 'userpic');
		$upload->image =& vB_Image::fetch_library($vbulletin);
		$upload->maxwidth = $vbulletin->userinfo['permissions']['profilepicmaxwidth'];
		$upload->maxheight = $vbulletin->userinfo['permissions']['profilepicmaxheight'];
		$upload->maxuploadsize = $vbulletin->userinfo['permissions']['profilepicmaxsize'];
		$upload->allowanimation = ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cananimateprofilepic']) ? true : false;

		if (!$upload->process_upload($vbulletin->GPC['avatarurl']))
		{
			eval(standard_error($upload->fetch_error()));
		}
	}

	($hook = vBulletinHook::fetch_hook('profile_updateprofilepic_complete')) ? eval($hook) : false;

	$vbulletin->url = 'profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editprofilepic';
	eval(print_standard_redirect('redirect_updatethanks'));
}

// ############################### start choose displayed usergroup ###############################

if ($_POST['do'] == 'updatedisplaygroup')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'usergroupid' => TYPE_UINT
	));

	$membergroups = fetch_membergroupids_array($vbulletin->userinfo);

	if ($vbulletin->GPC['usergroupid'] == 0)
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['usergroup'], $vbulletin->options['contactuslink'])));
	}

	if (!in_array($vbulletin->GPC['usergroupid'], $membergroups))
	{
		eval(standard_error(fetch_error('notmemberofdisplaygroup')));
	}
	else
	{
		$display_usergroup = $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"];

		if ($vbulletin->GPC['usergroupid'] == $vbulletin->userinfo['usergroupid'] OR $display_usergroup['canoverride'])
		{
			$vbulletin->userinfo['displaygroupid'] = $vbulletin->GPC['usergroupid'];

			// init user data manager
			$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
			$userdata->set_existing($vbulletin->userinfo);

			$userdata->set('displaygroupid', $vbulletin->GPC['usergroupid']);

			$userdata->set_usertitle(
				$vbulletin->userinfo['customtitle'] ? $vbulletin->userinfo['usertitle'] : '',
				false,
				$display_usergroup,
				($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
				($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cancontrolpanel']) ? true : false
			);

			($hook = vBulletinHook::fetch_hook('profile_updatedisplaygroup')) ? eval($hook) : false;

			$userdata->save();

			eval(print_standard_redirect('usergroup_displaygroupupdated'));
		}
		else
		{
			eval(standard_error(fetch_error('usergroup_invaliddisplaygroup')));
		}
	}
}

// *************************************************************************

if ($_POST['do'] == 'leavegroup')
{
	$vbulletin->input->clean_gpc('p', 'usergroupid', TYPE_UINT);

	$membergroups = fetch_membergroupids_array($vbulletin->userinfo);

	if (empty($membergroups))
	{ // check they have membergroups
		eval(standard_error(fetch_error('usergroup_cantleave_notmember')));
	}
	else if (!in_array($vbulletin->GPC['usergroupid'], $membergroups))
	{ // check they are a member before leaving
		eval(standard_error(fetch_error('usergroup_cantleave_notmember')));
	}
	else
	{
		if ($vbulletin->GPC['usergroupid'] == $vbulletin->userinfo['usergroupid'])
		{
			// trying to leave primary usergroup
			eval(standard_error(fetch_error('usergroup_cantleave_primary')));
		}
		else if ($check = $db->query_first("SELECT usergroupleaderid FROM " . TABLE_PREFIX . "usergroupleader WHERE usergroupid = " . $vbulletin->GPC['usergroupid'] . " AND userid=" . $vbulletin->userinfo['userid']))
		{
			// trying to leave a group of which user is a leader
			eval(standard_error(fetch_error('usergroup_cantleave_groupleader')));
		}
		else
		{
			$newmembergroups = array();
			foreach ($membergroups AS $groupid)
			{
				if ($groupid != $vbulletin->userinfo['usergroupid'] AND $groupid != $vbulletin->GPC['usergroupid'])
				{
					$newmembergroups[] = $groupid;
				}
			}

			// init user data manager
			$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
			$userdata->set_existing($vbulletin->userinfo);
			$userdata->set('membergroupids', $newmembergroups);
			if ($vbulletin->userinfo['displaygroupid'] == $vbulletin->GPC['usergroupid'])
			{
				$userdata->set('displaygroupid', 0);
				$userdata->set_usertitle(
					$vbulletin->userinfo['customtitle'] ? $vbulletin->userinfo['usertitle'] : '',
					false,
					$vbulletin->usergroupcache["{$vbulletin->userinfo['usergroupid']}"],
					($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
					($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cancontrolpanel']) ? true : false
				);
			}

			($hook = vBulletinHook::fetch_hook('profile_leavegroup')) ? eval($hook) : false;

			$userdata->save();

			eval(print_standard_redirect('usergroup_nolongermember'));
		}
	}

}

// *************************************************************************

if ($_POST['do'] == 'insertjoinrequest')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'usergroupid' => TYPE_UINT,
		'reason'      => TYPE_NOHTML,
	));

	($hook = vBulletinHook::fetch_hook('profile_insertjoinrequest')) ? eval($hook) : false;

	$vbulletin->url = "profile.php?do=editusergroups";

	if ($request = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "usergrouprequest WHERE userid=" . $vbulletin->userinfo['userid'] . " AND usergroupid=" . $vbulletin->GPC['usergroupid']))
	{
		// request already exists, just say okay...
		eval(print_standard_redirect('usergroup_requested'));
	}
	else

	{
		// insert the request
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "usergrouprequest
				(userid,usergroupid,reason,dateline)
			VALUES
				(" . $vbulletin->userinfo['userid'] . ", " . $vbulletin->GPC['usergroupid'] . ", '" . $db->escape_string($vbulletin->GPC['reason']) . "', " . TIMENOW . ")
		");
		eval(print_standard_redirect('usergroup_requested'));
	}

}

// *************************************************************************

if ($_POST['do'] == 'joingroup')
{
	$usergroupid = $vbulletin->input->clean_gpc('p', 'usergroupid', TYPE_UINT);

	$membergroups = fetch_membergroupids_array($vbulletin->userinfo);

	if (in_array($usergroupid, $membergroups))
	{
		eval(standard_error(fetch_error('usergroup_already_member')));
	}
	else
	{
		// check to see that usergroup exists and is public
		if ($vbulletin->usergroupcache["$usergroupid"]['ispublicgroup'])
		{
			$usergroup = $vbulletin->usergroupcache["$usergroupid"];

			// check to see if group is moderated
			$leaders = $db->query_read("
				SELECT ugl.userid, username
				FROM " . TABLE_PREFIX . "usergroupleader AS ugl
				INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
				WHERE ugl.usergroupid = $usergroupid
			");
			if ($db->num_rows($leaders))
			{
				// group is moderated: show join request page

				$_groupleaders = array();
				while ($leader = $db->fetch_array($leaders))
				{
					eval('$_groupleaders[] = "' . fetch_template('modifyusergroups_groupleader') . '";');
				}
				$groupleaders = implode(', ', $_groupleaders);

				$navbits['profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editusergroups'] = $vbphrase['group_memberships'];
				$navbits[''] = $vbphrase['join_request'];

				($hook = vBulletinHook::fetch_hook('profile_joingroup_moderated')) ? eval($hook) : false;

				// draw cp nav bar
				construct_usercp_nav('usergroups');
				$templatename = 'modifyusergroups_requesttojoin';

			}
			else
			{

				// group is not moderated: update user & join group
				$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
				$userdata->set_existing($vbulletin->userinfo);
				$userdata->set('membergroupids', (($vbulletin->userinfo['membergroupids'] == '') ? $usergroupid : $vbulletin->userinfo['membergroupids'] . ',' . $usergroupid));

				($hook = vBulletinHook::fetch_hook('profile_joingroup_unmoderated')) ? eval($hook) : false;

				$userdata->save();

				$usergroupname = $usergroup['title'];
				eval(print_standard_redirect('usergroup_welcome'));
			}

		}
		else
		{
			eval(standard_error(fetch_error('usergroup_notpublic')));
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
	foreach ($vbulletin->usergroupcache AS $usergroup)
	{
		if ($usergroup['ispublicgroup'] or $usergroup['canoverride'])
		{
			$haspublicgroups = true;
			break;
		}
	}

	($hook = vBulletinHook::fetch_hook('profile_editusergroups_start')) ? eval($hook) : false;

	if (!$haspublicgroups)
	{
		eval(standard_error(fetch_error('no_public_usergroups')));
	}
	else
	{
		$membergroups = fetch_membergroupids_array($vbulletin->userinfo);

		// query user's usertitle based on posts ladder
		$usertitle = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "usertitle
			WHERE minposts < " . $vbulletin->userinfo['posts'] . "
			ORDER BY minposts DESC
			LIMIT 1
		");

		// get array of all usergroup leaders
		$bbuserleader = array();
		$leaders = array();
		$groupleaders = $db->query_read("
			SELECT ugl.*, user.username
			FROM " . TABLE_PREFIX . "usergroupleader AS ugl
			INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		");
		while ($groupleader = $db->fetch_array($groupleaders))
		{
			if ($groupleader['userid'] == $vbulletin->userinfo['userid'])
			{
				$bbuserleader[] = $groupleader['usergroupid'];
			}
			$leaders["$groupleader[usergroupid]"]["$groupleader[userid]"] = $groupleader;
		}
		unset($groupleader);
		$db->free_result($groupleaders);

		// notify about new join requests if user is a group leader
		$joinrequestbits = '';
		if (!empty($bbuserleader))
		{
			$joinrequests = $db->query_read("
				SELECT usergroup.title, usergroup.opentag, usergroup.closetag, usergroup.usergroupid, COUNT(usergrouprequestid) AS requests
				FROM " . TABLE_PREFIX . "usergroup AS usergroup
				LEFT JOIN " . TABLE_PREFIX . "usergrouprequest AS req USING(usergroupid)
				WHERE usergroup.usergroupid IN(" . implode(',', $bbuserleader) . ")
				GROUP BY usergroup.usergroupid
				ORDER BY usergroup.title
			");
			while ($joinrequest = $db->fetch_array($joinrequests))
			{
				exec_switch_bg();
				$joinrequest['requests'] = vb_number_format($joinrequest['requests']);
				eval('$joinrequestbits .= "' . fetch_template('modifyusergroups_joinrequestbit') . '";');
			}
			unset($joinrequest);
			$db->free_result($joinrequests);
		}

		$show['joinrequests'] = iif($joinrequestbits != '', true, false);

		// get usergroups
		$groups = array();
		foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
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
			$joinrequests = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "usergrouprequest WHERE userid=" . $vbulletin->userinfo['userid'] . " AND usergroupid IN (" . implode(',', $couldrequest) . ')');
			while ($joinrequest = $db->fetch_array($joinrequests))
			{
				$requests["$joinrequest[usergroupid]"] = $joinrequest;
			}
			unset($joinrequest);
			$db->free_result($joinrequests);

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
						$joinrequest['date'] = vbdate($vbulletin->options['dateformat'], $joinrequest['dateline'], 1);
						$joinrequest['time'] = vbdate($vbulletin->options['timeformat'], $joinrequest['dateline'], 1);
						$joinrequested = 1;
					}
				}
				else

				{
					$ismoderated = 0;
					$groupleaders = '';
				}

				($hook = vBulletinHook::fetch_hook('profile_editusergroups_nonmemberbit')) ? eval($hook) : false;

				eval('$nonmembergroupbits .= "' . fetch_template('modifyusergroups_nonmemberbit') . '";');
			}
		}

		$show['nonmembergroups'] = iif($nonmembergroupbits != '', true, false);

		// set primary group info
		$primarygroupid = $vbulletin->userinfo['usergroupid'];
		$primarygroup = $groups['member']["{$vbulletin->userinfo['usergroupid']}"];

		// do groups user IS a member of
		$membergroupbits = '';
		foreach ($groups['member'] AS $usergroupid => $usergroup)
		{
			if ($usergroupid != $vbulletin->userinfo['usergroupid'] AND $usergroup['ispublicgroup'])
			{
				exec_switch_bg();
				if ($usergroup['usertitle'] == '')
				{
					$usergroup['usertitle'] = $usertitle['title'];
				}
				if (isset($leaders["$usergroupid"]["{$vbulletin->userinfo['userid']}"]))
				{
					$show['isleader'] = true;
				}
				else
				{
					$show['isleader'] = false;
				}

				($hook = vBulletinHook::fetch_hook('profile_editusergroups_memberbit')) ? eval($hook) : false;

				eval('$membergroupbits .= "' . fetch_template('modifyusergroups_memberbit') . '";');
			}
		}

		$show['membergroups'] = iif($membergroupbits != '', true, false);

		// do groups user could use as display group
		$checked = array();
		if ($vbulletin->userinfo['displaygroupid'])
		{
			$checked["{$vbulletin->userinfo['displaygroupid']}"] = 'checked="checked"';
		}
		else
		{
			$checked["{$vbulletin->userinfo['usergroupid']}"] = 'checked="checked"';
		}
		$displaygroupbits = '';
		foreach ($groups['member'] AS $usergroupid => $usergroup)
		{
			if ($usergroupid != $vbulletin->userinfo['usergroupid'] AND $usergroup['canoverride'])
			{
				exec_switch_bg();

				($hook = vBulletinHook::fetch_hook('profile_editusergroups_displaybit')) ? eval($hook) : false;

				eval('$displaygroupbits .= "' . fetch_template('modifyusergroups_displaybit') . '";');
			}
		}

		$show['displaygroups'] = iif($displaygroupbits != '', true, false);

		if (!$show['joinrequests'] AND !$show['nonmembergroups'] AND !$show['membergroups'] AND !$show['displaygroups'])
		{
			eval(standard_error(fetch_error('no_public_usergroups')));
		}

		$navbits[''] = $vbphrase['group_memberships'];
		$templatename = 'modifyusergroups';
	}
}

if ($_POST['do'] == 'deleteusergroups')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'usergroupid'	=> TYPE_UINT,
		'deletebox'		=> TYPE_ARRAY_BOOL
	));

	($hook = vBulletinHook::fetch_hook('profile_deleteusergroups_start')) ? eval($hook) : false;

	if ($vbulletin->GPC['usergroupid'])
	{
		// check permission to do authorizations in this group
		if (!$leadergroup = $db->query_first("
			SELECT usergroupleaderid
			FROM " . TABLE_PREFIX . "usergroupleader AS usergroupleader
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND usergroupid = " . $vbulletin->GPC['usergroupid'] . "
		"))
		{
			print_no_permission();
		}

		if (!empty($vbulletin->GPC['deletebox']))
		{
			foreach (array_keys($vbulletin->GPC['deletebox']) AS $userid)
			{
				$userids .= ',' . intval($userid);
			}

			$users = $db->query_read("
				SELECT u.userid, u.membergroupids, u.usergroupid, u.displaygroupid
				FROM " . TABLE_PREFIX . "user AS u
				LEFT JOIN " . TABLE_PREFIX . "usergroupleader AS ugl ON (u.userid = ugl.userid AND ugl.usergroupid = " . $vbulletin->GPC['usergroupid'] . ")
				WHERE u.userid IN (0$userids) AND ugl.usergroupleaderid IS NULL
			");
			while ($user = $db->fetch_array($users))
			{
				$membergroups = fetch_membergroupids_array($user, false);
				$newmembergroups = array();
				foreach($membergroups AS $groupid)
				{
					if ($groupid != $user['usergroupid'] AND $groupid != $vbulletin->GPC['usergroupid'])
					{
						$newmembergroups[] = $groupid;
					}
				}

				// init user data manager
				$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
				$userdata->set_existing($user);
				$userdata->set('membergroupids', $newmembergroups);
				if ($user['displaygroupid'] == $vbulletin->GPC['usergroupid'])
				{
					$userdata->set('displaygroupid', 0);
				}
				($hook = vBulletinHook::fetch_hook('profile_deleteusergroups_process')) ? eval($hook) : false;
				$userdata->save();
			}

			$vbulletin->url = 'memberlist.php?' . $vbulletin->session->vars['sessionurl'] . 'usergroupid=' . $vbulletin->GPC['usergroupid'];
			eval(print_standard_redirect('redirect_removedusers'));
		}
		else
		{
			// Print didn't select any users to delete
			eval(standard_error(fetch_error('usergroupleader_deleted')));
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
	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'deletebox'		=> TYPE_ARRAY_BOOL,
		'perpage'		=> TYPE_UINT,
		'pagenumber'	=> TYPE_UINT,
		'showthumbs'	=> TYPE_BOOL,
		'userid'		=> TYPE_UINT
	));

	if (empty($vbulletin->GPC['deletebox']))
	{
		eval(standard_error(fetch_error('attachdel')));
	}

	($hook = vBulletinHook::fetch_hook('profile_deleteattachments_start')) ? eval($hook) : false;

	// Get forums that allow canview access
	foreach ($vbulletin->userinfo['forumpermissions'] AS $forumid => $perm)
	{
		if (($perm & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($perm & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) AND ($perm & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
		{
			if ($userid != $vbulletin->userinfo['userid'] AND !($perm & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
			{
				// Viewing non-self and don't have permission to view other's threads in this forum
				continue;
			}
			$forumids .= ",$forumid";
		}
	}

	foreach (array_keys($vbulletin->GPC['deletebox']) AS $attachmentid)
	{
		$idlist .= ',' . intval($attachmentid);
	}
	// Verify that $vbulletin->userinfo owns these attachments before allowing deletion
	$validids = $db->query_read("
		SELECT attachment.attachmentid, attachment.postid, post.threadid, thread.forumid, thread.open, attachment.userid, post.dateline as p_dateline,
			IF(attachment.postid = 0, 1, 0) AS inprogress
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (attachment.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
		WHERE attachmentid IN (0$idlist) AND attachment.userid = " . $vbulletin->GPC['userid'] . "
			AND	((forumid IN(0$forumids) AND thread.visible = 1 AND post.visible = 1) " . iif($vbulletin->GPC['userid'] == $vbulletin->userinfo['userid'], "OR attachment.postid = 0") . ")
	");
	unset($idlist);
	while ($attachment = $db->fetch_array($validids))
	{
		if (!$attachment['inprogress'])
		{
			if (!$attachment['open'] AND !can_moderate($attachment['forumid'], 'canopenclose') AND !$vbulletin->options['allowclosedattachdel'])
			{
				continue;
			}
			else if (!can_moderate($attachment['forumid'], 'caneditposts'))
			{
				$forumperms = fetch_permissions($attachment['forumid']);
				if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['caneditpost']) OR $vbulletin->userinfo['userid'] != $attachment['userid'])
				{
					continue;
				}
				else
				{
					if (!$vbulletin->options['allowattachdel'] AND $vbulletin->options['edittimelimit'] AND $attachment['p_dateline'] < TIMENOW - $vbulletin->options['edittimelimit'] * 60)
					{
						continue;
					}
				}
			}
		}

		$idlist .= ',' . $attachment['attachmentid'];
	}

	require_once(DIR . '/includes/functions_file.php');
	if (!empty($idlist))
	{
		$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_STANDARD);
		$attachdata->condition = "attachmentid IN (-1$idlist)";
		$attachdata->delete();
	}

	($hook = vBulletinHook::fetch_hook('profile_deleteattachments_complete')) ? eval($hook) : false;

	$vbulletin->url = 'profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editattachments&amp;pp=' . $vbulletin->GPC['perpage'] . '&amp;page=' . $vbulletin->GPC['pagenumber'] . '&amp;showthumbs=' . $vbulletin->GPC['showthumbs'] . '&amp;u=' . $vbulletin->GPC['userid'];
	eval(print_standard_redirect('redirect_attachdel'));

}

// ############################### List of attachments for current user ################
if ($_REQUEST['do'] == 'editattachments')
{
	// Variables reused in templates
	$perpage = $vbulletin->input->clean_gpc('r', 'perpage', TYPE_UINT);
	$pagenumber = $vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);
	$showthumbs = $vbulletin->input->clean_gpc('r', 'showthumbs', TYPE_BOOL);

	$vbulletin->input->clean_array_gpc('r', array(
		'userid'		=> TYPE_UINT
	));

	$templatename = 'modifyattachments';

	$show['attachment_list'] = true;

	if (!$vbulletin->GPC['userid'] OR $vbulletin->GPC['userid'] == $vbulletin->userinfo['userid'])
	{
		// show own attachments in user cp
		$userid = $vbulletin->userinfo['userid'];
		$username = $vbulletin->userinfo['username'];
		$show['attachquota'] = true;
	}
	else
	{
		// show someone else's attachments
		$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1);
		$userid = $userinfo['userid'];
		$username = $userinfo['username'];
		$show['otheruserid'] = true;
	}

	($hook = vBulletinHook::fetch_hook('profile_editattachments_start')) ? eval($hook) : false;

	// Get forums that allow canview access
	foreach ($vbulletin->userinfo['forumpermissions'] AS $forumid => $perm)
	{
		if (($perm & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($perm & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) AND ($perm & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
		{
			if ($userid != $vbulletin->userinfo['userid'] AND !($perm & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
			{
				// Viewing non-self and don't have permission to view other's threads in this forum
				continue;
			}
			$forumids .= ",$forumid";
		}
	}

	// Get attachment count
	$attachments = $db->query_first("
		SELECT COUNT(*) AS total,
			SUM(filesize) AS sum
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		WHERE attachment.userid = $userid
			AND	((forumid IN(0$forumids) AND thread.visible = 1 AND post.visible = 1) " . iif($userid==$vbulletin->userinfo['userid'], "OR attachment.postid = 0") . ")
	");

	$totalattachments = intval($attachments['total']);
	$attachsum = intval($attachments['sum']);

	if (!$totalattachments AND $userid != $vbulletin->userinfo['userid'])
	{
		eval(standard_error(fetch_error('noattachments')));
	}
	else if (!$totalattachments)
	{
		$show['attachment_list'] = false;
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
		$attachments = $db->query_read("
			SELECT thread.forumid, post.postid, post.threadid AS p_threadid, post.title AS p_title, post.dateline AS p_dateline, attachment.attachmentid,
				thread.title AS t_title, attachment.filename, attachment.counter, attachment.filesize AS size, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail,
				thumbnail_filesize, user.username, thread.open, attachment.userid " . iif($userid==$vbulletin->userinfo['userid'], ", IF(attachment.postid = 0, 1, 0) AS inprogress") . ",
				attachment.dateline, attachment.thumbnail_dateline
			FROM " . TABLE_PREFIX . "attachment AS attachment
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (attachment.userid = user.userid)
			WHERE attachment.userid = $userid
				AND ((forumid IN (0$forumids) AND thread.visible = 1 AND post.visible = 1) " . iif($userid==$vbulletin->userinfo['userid'], "OR attachment.postid = 0") . ")
			ORDER BY attachment.attachmentid DESC
			LIMIT " . ($limitlower - 1) . ", $perpage
		");

		$template['attachmentlistbits'] = '';
		while ($post = $db->fetch_array($attachments))
		{
			$post['filename'] = htmlspecialchars_uni($post['filename']);

			if (!$post['p_title'])
			{
				$post['p_title'] = '&laquo;' . $vbphrase['n_a'] . '&raquo;';
			}

			$post['counter'] = vb_number_format($post['counter']);
			$post['size'] = vb_number_format($post['size'], 1, true);
			$post['postdate'] = vbdate($vbulletin->options['dateformat'], $post['p_dateline'], true);
			$post['posttime'] = vbdate($vbulletin->options['timeformat'], $post['p_dateline']);

			$post['attachmentextension'] = strtolower(file_extension($post['filename']));
			$show['thumbnail'] = iif($post['hasthumbnail'] == 1 AND $vbulletin->options['attachthumbs'] AND $showthumbs, 1, 0);
			$show['inprogress'] = iif(!$post['postid'], true, false);

			$show['deletebox'] = false;

			if ($post['inprogress'])
			{
				$show['deletebox'] = true;
			}
			else if ($post['open'] OR $vbulletin->options['allowclosedattachdel'] OR can_moderate($post['forumid'], 'canopenclose'))
			{
				if (can_moderate($post['forumid'], 'caneditposts'))
				{
					$show['deletebox'] = true;
				}
				else
				{
					$forumperms = fetch_permissions($post['forumid']);
					if (($forumperms & $vbulletin->bf_ugp_forumpermissions['caneditpost'] AND $vbulletin->userinfo['userid'] == $post['userid']))
					{
						if ($vbulletin->options['allowattachdel'] OR !$vbulletin->options['edittimelimit'] OR $post['p_dateline'] >= TIMENOW - $vbulletin->options['edittimelimit'] * 60)
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

			($hook = vBulletinHook::fetch_hook('profile_editattachments_bit')) ? eval($hook) : false;

			eval('$template[\'attachmentlistbits\'] .= "' . fetch_template('modifyattachmentsbit') . '";');
		}

		$sorturl = 'profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editattachments';
		if ($userid != $vbulletin->userinfo['userid'])
		{
			$sorturl .= "&amp;u=$userid";
		}
		if ($perpage != $defaultperpage)
		{
			$sorturl .= "&amp;pp=$perpage";
		}
		if ($showthumbs)
		{
			$sorturl .= "&amp;showthumbs=1";
		}

		$pagenav = construct_page_nav($pagenumber, $perpage, $totalattachments, $sorturl);

		$totalattachments = vb_number_format($totalattachments);

		$show['attachlimit'] = $permissions['attachlimit'];
		$show['currentattachsize'] = $attachsize;
		$show['totalattachsize'] = $totalsize;
		$show['thumbnails'] = $showthumbs;
	}

	($hook = vBulletinHook::fetch_hook('profile_editattachments_complete')) ? eval($hook) : false;

	if ($userid == $vbulletin->userinfo['userid'])
	{
		// show $vbulletin->userinfo's attachments in usercp
		construct_usercp_nav('attachments');
		$navbits[''] = construct_phrase($vbphrase['attachments_posted_by_x'], $vbulletin->userinfo['username']);
	}
	else
	{
		// show some other user's attachments
		$pagetitle = construct_phrase($vbphrase['attachments_posted_by_x'], $username);

		$navbits = array(
			'member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userid" => $vbphrase['view_profile'],
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

	($hook = vBulletinHook::fetch_hook('profile_complete')) ? eval($hook) : false;

	// shell template
	eval('$HTML = "' . fetch_template($templatename) . '";');
	eval('print_output("' . fetch_template($shelltemplatename) . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15866 $
|| ####################################################################
\*======================================================================*/
?>