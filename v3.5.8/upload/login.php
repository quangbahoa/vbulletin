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
define('THIS_SCRIPT', 'login');

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
		'lostpw'
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
	$vbulletin->input->clean_gpc('r', 'logouthash', TYPE_STR);

	if ($vbulletin->userinfo['userid'] != 0 AND $vbulletin->GPC['logouthash'] != $vbulletin->userinfo['logouthash'])
	{
		eval(standard_error(fetch_error('logout_error', $vbulletin->session->vars['sessionurl'], $vbulletin->userinfo['logouthash'])));
	}

	process_logout();

	$vbulletin->url = fetch_replaced_session_url($vbulletin->url);
	if (strpos($vbulletin->url, 'do=logout') !== false)
	{
		$vbulletin->url = $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'];
	}
	$show['member'] = false;
	eval(standard_error(fetch_error('cookieclear', $vbulletin->url, $vbulletin->options['forumhome'], $vbulletin->session->vars['sessionurl_q']), '', false));
}

// ############################### start do login ###############################
// this was a _REQUEST action but where do we all login via request?
if ($_POST['do'] == 'login')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'vb_login_username' => TYPE_STR,
		'vb_login_password' => TYPE_STR,
		'vb_login_md5password' => TYPE_STR,
		'vb_login_md5password_utf' => TYPE_STR,
		'postvars' => TYPE_STR,
		'cookieuser' => TYPE_BOOL,
		'logintype' => TYPE_STR,
		'cssprefs' => TYPE_STR,
	));

	// can the user login?
	$strikes = verify_strike_status($vbulletin->GPC['vb_login_username']);

	if ($vbulletin->GPC['vb_login_username'] == '')
	{
		eval(standard_error(fetch_error('badlogin', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'], $strikes)));
	}

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
		$vbulletin->userinfo = array(
			'userid' => 0,
			'usergroupid' => 1
		);
		eval(standard_error(fetch_error('badlogin', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'], $strikes)));
	}

	exec_unstrike_user($vbulletin->GPC['vb_login_username']);

	// create new session
	process_new_login($vbulletin->GPC['logintype'], $vbulletin->GPC['cookieuser'], $vbulletin->GPC['cssprefs']);

	// do redirect
	do_login_redirect();

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

	$url =& $vbulletin->url;
	eval('print_output("' . fetch_template('lostpw') . '");');
}

// ############################### start email password ###############################
if ($_POST['do'] == 'emailpassword')
{

	$vbulletin->input->clean_gpc('p', 'email', TYPE_STR);

	if ($vbulletin->GPC['email'] == '')
	{
		eval(standard_error(fetch_error('invalidemail', $vbulletin->options['contactuslink'])));
	}

	require_once(DIR . '/includes/functions_user.php');

	$users = $db->query_read("
		SELECT userid, username, email, languageid
		FROM " . TABLE_PREFIX . "user
		WHERE email = '" . $db->escape_string($vbulletin->GPC['email']) . "'
	");
	if ($db->num_rows($users))
	{
		while ($user = $db->fetch_array($users))
		{
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
		'userid' => TYPE_UINT,
		'u' => TYPE_UINT,
		'activationid' => TYPE_UINT,
		'i' => TYPE_UINT
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

	// make random number
	$newpassword = vbrand(0, 100000000);

	// init user data manager
	$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
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
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13987 $
|| ####################################################################
\*======================================================================*/
?>