<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'login');
define('CSRF_PROTECTION', true);
define('CSRF_SKIP_LIST', 'login');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'lostpw' => array(
		'lostpw',
		'humanverify'
	)
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_login.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_gpc('r', 'a', TYPE_STR);

if (empty($_REQUEST['do']) AND empty($vbulletin->GPC['a']))
{
	exec_header_redirect($vbulletin->options['forumhome'] . '.php');
}

// ############################### start logout ###############################
if ($_REQUEST['do'] == 'logout')
{
	define('NOPMPOPUP', true);

	$vbulletin->input->clean_gpc('r', 'logouthash', TYPE_STR);

	if ($vbulletin->userinfo['userid'] != 0 AND !verify_security_token($vbulletin->GPC['logouthash'], $vbulletin->userinfo['securitytoken_raw']))
	{
		eval(standard_error(fetch_error('logout_error', $vbulletin->session->vars['sessionurl'], $vbulletin->userinfo['securitytoken'])));
	}

	process_logout();

	$vbulletin->url = fetch_replaced_session_url($vbulletin->url);
	if (strpos($vbulletin->url, 'do=logout') !== false)
	{
		$vbulletin->url = $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'];
	}
	$show['member'] = false;
	eval(standard_error(fetch_error('cookieclear', create_full_url($vbulletin->url), $vbulletin->options['forumhome'], $vbulletin->session->vars['sessionurl_q']), '', false));

}

// ############################### start do login ###############################
// this was a _REQUEST action but where do we all login via request?
if ($_POST['do'] == 'login')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'vb_login_username'        => TYPE_STR,
		'vb_login_password'        => TYPE_STR,
		'vb_login_md5password'     => TYPE_STR,
		'vb_login_md5password_utf' => TYPE_STR,
		'postvars'                 => TYPE_BINARY,
		'cookieuser'               => TYPE_BOOL,
		'logintype'                => TYPE_STR,
		'cssprefs'                 => TYPE_STR,
	));

	// can the user login?
	$strikes = verify_strike_status($vbulletin->GPC['vb_login_username']);

	if ($vbulletin->GPC['vb_login_username'] == '')
	{
		eval(standard_error(fetch_error('badlogin', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'], $strikes)));
	}

	// make sure our user info stays as whoever we were (for example, we might be logged in via cookies already)
	$original_userinfo = $vbulletin->userinfo;

	if (!verify_authentication($vbulletin->GPC['vb_login_username'], $vbulletin->GPC['vb_login_password'], $vbulletin->GPC['vb_login_md5password'], $vbulletin->GPC['vb_login_md5password_utf'], $vbulletin->GPC['cookieuser'], true))
	{
		($hook = vBulletinHook::fetch_hook('login_failure')) ? eval($hook) : false;

		// check password
		exec_strike_user($vbulletin->userinfo['username']);

		if ($vbulletin->GPC['logintype'] === 'cplogin' OR $vbulletin->GPC['logintype'] === 'modcplogin')
		{
			// log this error if attempting to access the control panel
			require_once(DIR . '/includes/functions_log_error.php');
			log_vbulletin_error($vbulletin->GPC['vb_login_username'], 'security');
		}
		$vbulletin->userinfo = $original_userinfo;

		if ($vbulletin->options['usestrikesystem'])
		{
			eval(standard_error(fetch_error('badlogin_strikes', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'], $strikes)));
		}
		else
		{
			eval(standard_error(fetch_error('badlogin', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'])));
		}
	}

	exec_unstrike_user($vbulletin->GPC['vb_login_username']);

	// create new session
	process_new_login($vbulletin->GPC['logintype'], $vbulletin->GPC['cookieuser'], $vbulletin->GPC['cssprefs']);

	// do redirect
	do_login_redirect();

}
else if ($_GET['do'] == 'login')
{
	// add consistency with previous behavior
	exec_header_redirect($vbulletin->options['forumhome'] . '.php');
}

// ############################### start lost password ###############################
if ($_REQUEST['do'] == 'lostpw')
{
	$vbulletin->input->clean_gpc('r', 'email', TYPE_NOHTML);
	$email = $vbulletin->GPC['email'];

	if ($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])
	{
		$navbits = construct_navbits(array('' => $vbphrase['lost_password_recovery_form']));
		eval('$navbar = "' . fetch_template('navbar') . '";');
	}
	else
	{
		$navbar = '';
	}

	// human verification
	if (fetch_require_hvcheck('lostpw'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verification = vB_HumanVerify::fetch_library($vbulletin);
		$human_verify = $verification->output_token();
	}
	else
	{
		$human_verify = '';
	}

	$url =& $vbulletin->url;
	if (!empty($vbulletin->userinfo['email']))
	{
		$email = $vbulletin->userinfo['email'];
	}

	eval('print_output("' . fetch_template('lostpw') . '");');
}

// ############################### start email password ###############################
if ($_POST['do'] == 'emailpassword')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'email' => TYPE_STR,
		'userid' => TYPE_UINT,
		'humanverify'  => TYPE_ARRAY,
	));

	if ($vbulletin->GPC['email'] == '')
	{
		eval(standard_error(fetch_error('invalidemail', $vbulletin->options['contactuslink'])));
	}

	if (fetch_require_hvcheck('lostpw'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify = vB_HumanVerify::fetch_library($vbulletin);
		if (!$verify->verify_token($vbulletin->GPC['humanverify']))
		{
	  		standard_error(fetch_error($verify->fetch_error()));
	  	}
	}

	require_once(DIR . '/includes/functions_user.php');

	$users = $db->query_read_slave("
		SELECT userid, username, email, languageid
		FROM " . TABLE_PREFIX . "user
		WHERE email = '" . $db->escape_string($vbulletin->GPC['email']) . "'
	");
	if ($db->num_rows($users))
	{
		while ($user = $db->fetch_array($users))
		{
			if ($vbulletin->GPC['userid'] AND $vbulletin->GPC['userid'] != $user['userid'])
			{
				continue;
			}
			$user['username'] = unhtmlspecialchars($user['username']);

			$user['activationid'] = build_user_activation_id($user['userid'], 2, 1);

			eval(fetch_email_phrases('lostpw', $user['languageid']));
			vbmail($user['email'], $subject, $message, true);
		}

		$vbulletin->url = str_replace('"', '', $vbulletin->url);
		eval(print_standard_redirect('redirect_lostpw', true, true));
	}
	else
	{
		eval(standard_error(fetch_error('invalidemail', $vbulletin->options['contactuslink'])));
	}
}

// ############################### start reset password ###############################
if ($vbulletin->GPC['a'] == 'pwd' OR $_REQUEST['do'] == 'resetpassword')
{

	$vbulletin->input->clean_array_gpc('r', array(
		'userid'       => TYPE_UINT,
		'u'            => TYPE_UINT,
		'activationid' => TYPE_STR,
		'i'            => TYPE_STR
	));

	if (!$vbulletin->GPC['userid'])
	{
		$vbulletin->GPC['userid'] = $vbulletin->GPC['u'];
	}

	if (!$vbulletin->GPC['activationid'])
	{
		$vbulletin->GPC['activationid'] = $vbulletin->GPC['i'];
	}

	$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1);

	$user = $db->query_first("
		SELECT activationid, dateline
		FROM " . TABLE_PREFIX . "useractivation
		WHERE type = 1
			AND userid = $userinfo[userid]
	");

	if (!$user)
	{
		// no activation record, probably got back here after a successful request, back to home
		exec_header_redirect($vbulletin->options['forumhome'] . '.php');
	}

	if ($user['dateline'] < (TIMENOW - 24 * 60 * 60))
	{  // is it older than 24 hours?
		eval(standard_error(fetch_error('resetexpired', $vbulletin->session->vars['sessionurl'])));
	}

	if ($user['activationid'] != $vbulletin->GPC['activationid'])
	{ //wrong act id
		eval(standard_error(fetch_error('resetbadid', $vbulletin->session->vars['sessionurl'])));
	}

	// delete old activation id
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "useractivation WHERE userid = $userinfo[userid] AND type = 1");

	$newpassword = fetch_random_password(8);

	// init user data manager
	$userdata = datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
	$userdata->set_existing($userinfo);
	$userdata->set('password', $newpassword);
	$userdata->save();

	($hook = vBulletinHook::fetch_hook('reset_password')) ? eval($hook) : false;

	eval(fetch_email_phrases('resetpw', $userinfo['languageid']));
	vbmail($userinfo['email'], $subject, $message, true);

	eval(standard_error(fetch_error('resetpw', $vbulletin->session->vars['sessionurl'])));

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
