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
define('THIS_SCRIPT', 'register');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('timezone', 'user', 'register', 'cprofilefield');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'banemail',
	'ranks',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'humanverify',
	'register',
	'register_rules',
	'register_verify_age',
	'register_coppaform',
	'userfield_textbox',
	'userfield_checkbox_option',
	'userfield_optional_input',
	'userfield_radio',
	'userfield_radio_option',
	'userfield_select',
	'userfield_select_option',
	'userfield_select_multiple',
	'userfield_textarea',
	'userfield_wrapper',
	'modifyoptions_timezone',
	'modifyprofile_birthday',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'requestemail' => array(
		'activate_requestemail'
	),
	'none' => array(
		'activateform'
	)
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_misc.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_gpc('r', 'a', TYPE_NOHTML);
$vbulletin->input->clean_gpc('r', 'u', TYPE_NOHTML);
$coppaage = $vbulletin->input->clean_gpc('c', COOKIE_PREFIX . 'coppaage', TYPE_STR);

if (empty($_REQUEST['do']) AND $vbulletin->GPC['a'] == '')
{
	$_REQUEST['do'] = 'signup';
}

($hook = vBulletinHook::fetch_hook('register_start')) ? eval($hook) : false;

// ############################### start checkdate ###############################
if ($_REQUEST['do'] == 'checkdate')
{
	// check their birthdate
	$vbulletin->input->clean_array_gpc('r', array(
		'month' => TYPE_UINT,
		'year'  => TYPE_UINT,
		'day'   => TYPE_UINT,
	));

	$current['year'] = date('Y');
	$current['month'] = date('m');
	$current['day'] = date('d');

	if ($vbulletin->GPC['month'] == 0 OR $vbulletin->GPC['day'] == 0 OR !preg_match('#^\d{4}$#', $vbulletin->GPC['year']) OR $vbulletin->GPC['year'] < 1901 OR $vbulletin->GPC['year'] > $current['year'])
	{
		eval(standard_error(fetch_error('select_valid_dob', $current['year'])));
	}

	($hook = vBulletinHook::fetch_hook('register_checkdate')) ? eval($hook) : false;

	if ($vbulletin->options['usecoppa'] AND $vbulletin->options['checkcoppa'] AND $coppaage)
	{
		$dob = explode('-', $coppaage);
		$month = $dob[0];
		$day = $dob[1];
		$year = $dob[2];
	}

	if ($vbulletin->GPC['year'] < 1970 OR (mktime(0, 0, 0, $vbulletin->GPC['month'], $vbulletin->GPC['day'], $vbulletin->GPC['year']) <= mktime(0, 0, 0, $current['month'], $current['day'], $current['year'] - 13)))
	{
		$_REQUEST['do'] = 'signup';
	}
	else
	{
		if ($vbulletin->options['checkcoppa'] AND $vbulletin->options['usecoppa'])
		{
			vbsetcookie('coppaage', $vbulletin->GPC['month'] . '-' . $vbulletin->GPC['day'] . '-' . $vbulletin->GPC['year'], 1);
		}

		if ($vbulletin->options['usecoppa'] == 2)
		{
			// turn away as they're under 13
			eval(standard_error(fetch_error('under_thirteen_registration_denied')));
		}
		else
		{
			$_REQUEST['do'] = 'signup';
		}
	}
}

// ############################### start signup ###############################
if ($_REQUEST['do'] == 'signup')
{
	$current['year'] = date('Y');
	$current['month'] = date('m');
	$current['day'] = date('d');

	if (!$vbulletin->options['allowregistration'])
	{
		eval(standard_error(fetch_error('noregister')));
	}

	if ($vbulletin->userinfo['userid'] AND !$vbulletin->options['allowmultiregs'])
	{
		eval(standard_error(fetch_error('alreadyregistered', $vbulletin->userinfo['username'], $vbulletin->session->vars['sessionurl'])));
	}

	if ($vbulletin->options['usecoppa'])
	{
		if ($vbulletin->options['checkcoppa'] AND $coppaage)
		{
			$dob = explode('-', $coppaage);
			$month = $dob[0];
			$day = $dob[1];
			$year = $dob[2];
		}
		else
		{
			$month = $vbulletin->input->clean_gpc('r', 'month', TYPE_UINT);
			$year = $vbulletin->input->clean_gpc('r', 'year', TYPE_UINT);
			$day = $vbulletin->input->clean_gpc('r', 'day', TYPE_UINT);
		}

		if (!$month OR !$day OR !$year)
		{	// Show age controls
			$templatename = 'register_verify_age';
		}
		else	// verify age
		{
			if ($year < 1970 OR (mktime(0, 0, 0, $month, $day, $year) <= mktime(0, 0, 0, $current['month'], $current['day'], $current['year'] - 13)))
			{	// this user is >13
				$show['coppa'] = false;
				$templatename = 'register_rules';
			}
			else if ($vbulletin->options['usecoppa'] == 2)
			{
				if ($vbulletin->options['checkcoppa'])
				{
					vbsetcookie('coppaage', $month . '-' . $day . '-' . $year, 1);
				}
				eval(standard_error(fetch_error('under_thirteen_registration_denied')));
			}
			else
			{
				if ($vbulletin->options['checkcoppa'])
				{
					vbsetcookie('coppaage', $month . '-' . $day . '-' . $year, 1);
				}
				$show['coppa'] = true;
				$templatename = 'register_rules';
			}
		}
	}
	else
	{
		$show['coppa'] = false;
		$templatename = 'register_rules';
	}

	($hook = vBulletinHook::fetch_hook('register_signup')) ? eval($hook) : false;

	$url =& $vbulletin->url;
	eval('print_output("' . fetch_template($templatename) . '");');
}

// ############################### start add member ###############################
if ($_POST['do'] == 'addmember')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'options'             => TYPE_ARRAY_BOOL,
		'username'            => TYPE_STR,
		'email'               => TYPE_STR,
		'emailconfirm'        => TYPE_STR,
		'parentemail'         => TYPE_STR,
		'password'            => TYPE_STR,
		'password_md5'        => TYPE_STR,
		'passwordconfirm'     => TYPE_STR,
		'passwordconfirm_md5' => TYPE_STR,
		'referrername'        => TYPE_NOHTML,
		'coppauser'           => TYPE_BOOL,
		'day'                 => TYPE_UINT,
		'month'               => TYPE_UINT,
		'year'                => TYPE_UINT,
		'timezoneoffset'      => TYPE_NUM,
		'dst'                 => TYPE_UINT,
		'userfield'           => TYPE_ARRAY,
		'showbirthday'        => TYPE_UINT,
		'humanverify'         => TYPE_ARRAY,
	));

	if (!$vbulletin->options['allowregistration'])
	{
		eval(standard_error(fetch_error('noregister')));
	}

	// check for multireg
	if ($vbulletin->userinfo['userid'] AND !$vbulletin->options['allowmultiregs'])
	{
		eval(standard_error(fetch_error('alreadyregistered', $vbulletin->userinfo['username'], $vbulletin->session->vars['sessionurl'])));
	}

	// init user datamanager class
	$userdata = datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);

	// coppa option
	if ($vbulletin->options['usecoppa'])
	{
		$current['year'] = date('Y');
		$current['month'] = date('m');
		$current['day'] = date('d');

		$month = $vbulletin->GPC['month'];
		$year = $vbulletin->GPC['year'];
		$day = $vbulletin->GPC['day'];

		if ($year > 1970 AND mktime(0, 0, 0, $month, $day, $year) > mktime(0, 0, 0, $current['month'], $current['day'], $current['year'] - 13))
		{
			if ($vbulletin->options['checkcoppa'])
			{
				vbsetcookie('coppaage', $month . '-' . $day . '-' . $year, 1);
			}

			if ($vbulletin->options['usecoppa'] == 2)
			{
				standard_error(fetch_error('under_thirteen_registration_denied'));
			}

			$vbulletin->GPC['coppauser'] = true;

		}
		else
		{
			$vbulletin->GPC['coppauser'] = false;
		}
	}
	else
	{
		$vbulletin->GPC['coppauser'] = false;
	}

	$userdata->set_info('coppauser', $vbulletin->GPC['coppauser']);
	$userdata->set_info('coppapassword', $vbulletin->GPC['password']);
	$userdata->set_bitfield('options', 'coppauser', $vbulletin->GPC['coppauser']);
	$userdata->set('parentemail', $vbulletin->GPC['parentemail']);

	// check for missing fields
	if (empty($vbulletin->GPC['username'])
		OR empty($vbulletin->GPC['email'])
		OR empty($vbulletin->GPC['emailconfirm'])
		OR ($vbulletin->GPC['coppauser'] AND empty($vbulletin->GPC['parentemail']))
		OR (empty($vbulletin->GPC['password']) AND empty($vbulletin->GPC['password_md5']))
		OR (empty($vbulletin->GPC['passwordconfirm']) AND empty($vbulletin->GPC['passwordconfirm_md5']))
	)
	{
		$userdata->error('fieldmissing');
	}

	// check for matching passwords
	if ($vbulletin->GPC['password'] != $vbulletin->GPC['passwordconfirm'] OR (strlen($vbulletin->GPC['password_md5']) == 32 AND $vbulletin->GPC['password_md5'] != $vbulletin->GPC['passwordconfirm_md5']))
	{
		$userdata->error('passwordmismatch');
	}

	// check for matching email addresses
	if ($vbulletin->GPC['email'] != $vbulletin->GPC['emailconfirm'])
	{
		$userdata->error('emailmismatch');
	}
	$userdata->set('email', $vbulletin->GPC['email']);

	$userdata->set('username', $vbulletin->GPC['username']);

	// set password
	$userdata->set('password', ($vbulletin->GPC['password_md5'] ? $vbulletin->GPC['password_md5'] : $vbulletin->GPC['password']));

	// check referrer
	if ($vbulletin->GPC['referrername'] AND !$vbulletin->userinfo['userid'])
	{
		$userdata->set('referrerid', $vbulletin->GPC['referrername']);
	}

	// Human Verification
	if (fetch_require_hvcheck('register'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify = vB_HumanVerify::fetch_library($vbulletin);
		if (!$verify->verify_token($vbulletin->GPC['humanverify']))
		{
			$userdata->error($verify->fetch_error());
		}
	}
	// Set specified options
	if (!empty($vbulletin->GPC['options']))
	{
		foreach ($vbulletin->GPC['options'] AS $optionname => $onoff)
		{
			$userdata->set_bitfield('options', $optionname, $onoff);
		}
	}

	// assign user to usergroup 3 if email needs verification
	if ($vbulletin->options['verifyemail'])
	{
		$newusergroupid = 3;
	}
	else if ($vbulletin->options['moderatenewmembers'] OR $vbulletin->GPC['coppauser'])
	{
		$newusergroupid = 4;
	}
	else
	{
		$newusergroupid = 2;
	}
	// set usergroupid
	$userdata->set('usergroupid', $newusergroupid);

	// set languageid
	$userdata->set('languageid', $vbulletin->userinfo['languageid']);

	// set user title
	$userdata->set_usertitle('', false, $vbulletin->usergroupcache["$newusergroupid"], false, false);

	// set profile fields
	$customfields = $userdata->set_userfields($vbulletin->GPC['userfield'], true, 'register');

	// set birthday
	$userdata->set('showbirthday', $vbulletin->GPC['showbirthday']);
	$userdata->set('birthday', array(
		'day'   => $vbulletin->GPC['day'],
		'month' => $vbulletin->GPC['month'],
		'year'  => $vbulletin->GPC['year']
	));

	// set time options
	$userdata->set_dst($vbulletin->GPC['dst']);
	$userdata->set('timezoneoffset', $vbulletin->GPC['timezoneoffset']);

	// register IP address
	$userdata->set('ipaddress', IPADDRESS);

	($hook = vBulletinHook::fetch_hook('register_addmember_process')) ? eval($hook) : false;

	$userdata->pre_save();

	// check for errors
	if (!empty($userdata->errors))
	{
		$_REQUEST['do'] = 'register';

		$errorlist = '';
		foreach ($userdata->errors AS $index => $error)
		{
			$errorlist .= "<li>$error</li>";
		}

		$username = htmlspecialchars_uni($vbulletin->GPC['username']);
		$email = htmlspecialchars_uni($vbulletin->GPC['email']);
		$emailconfirm = htmlspecialchars_uni($vbulletin->GPC['emailconfirm']);
		$parentemail = htmlspecialchars_uni($vbulletin->GPC['parentemail']);
		$selectdst = array($vbulletin->GPC['dst'] => 'selected="selected"');
		$sbselected = array($vbulletin->GPC['showbirthday'] => 'selected="selected"');
		$show['errors'] = true;
	}
	else
	{
		$show['errors'] = false;

		// save the data
		$vbulletin->userinfo['userid']
			= $userid
			= $userdata->save();

		if ($userid)
		{
			$userinfo = fetch_userinfo($userid,0,0,0,true); // Read Master
			$userdata_rank = datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdata_rank->set_existing($userinfo);
			$userdata_rank->set('posts', 0);
			$userdata_rank->save();

			// force a new session to prevent potential issues with guests from the same IP, see bug #2459
			require_once(DIR . '/includes/functions_login.php');
			$vbulletin->session->created = false;
			process_new_login('', false, '');

			// send new user email
			if ($vbulletin->options['newuseremail'] != '')
			{
				$username = $vbulletin->GPC['username'];
				$email = $vbulletin->GPC['email'];

				if ($birthday = $userdata->fetch_field('birthday'))
				{
					$bday = explode('-', $birthday);
					$year = vbdate('Y', TIMENOW, false, false);
					$month = vbdate('n', TIMENOW, false, false);
					$day = vbdate('j', TIMENOW, false, false);
					if ($year > $bday[2] AND $bday[2] > 1901 AND $bday[2] != '0000')
					{
						require_once(DIR . '/includes/functions_misc.php');
						$vbulletin->options['calformat1'] = mktimefix($vbulletin->options['calformat1'], $bday[2]);
						if ($bday[2] >= 1970)
						{
							$yearpass = $bday[2];
						}
						else
						{
							// day of the week patterns repeat every 28 years, so
							// find the first year >= 1970 that has this pattern
							$yearpass = $bday[2] + 28 * ceil((1970 - $bday[2]) / 28);
						}
						$birthday = vbdate($vbulletin->options['calformat1'], mktime(0, 0, 0, $bday[0], $bday[1], $yearpass), false, true, false);
					}
					else
					{
						// lets send a valid year as some PHP3 don't like year to be 0
						$birthday = vbdate($vbulletin->options['calformat2'], mktime(0, 0, 0, $bday[0], $bday[1], 1992), false, true, false);
					}

					if ($birthday == '')
					{
						// Should not happen; fallback for win32 bug regarding mktime and dates < 1970
						if ($bday[2] == '0000')
						{
							$birthday = "$bday[0]-$bday[1]";
						}
						else
						{
							$birthday = "$bday[0]-$bday[1]-$bday[2]";
						}
					}
				}

				if ($userdata->fetch_field('referrerid') AND $vbulletin->GPC['referrername'])
				{
					$referrer = unhtmlspecialchars($vbulletin->GPC['referrername']);
				}
				else
				{
					$referrer = $vbphrase['n_a'];
				}
				$ipaddress = IPADDRESS;

				eval(fetch_email_phrases('newuser', 0));

				$newemails = explode(' ', $vbulletin->options['newuseremail']);
				foreach ($newemails AS $toemail)
				{
					if (trim($toemail))
					{
						vbmail($toemail, $subject, $message);
					}
				}
			}

			$username = htmlspecialchars_uni($vbulletin->GPC['username']);
			$email = htmlspecialchars_uni($vbulletin->GPC['email']);

			// sort out emails and usergroups
			if ($vbulletin->options['verifyemail'])
			{
				$activateid = build_user_activation_id($userid, (($vbulletin->options['moderatenewmembers'] OR $vbulletin->GPC['coppauser']) ? 4 : 2), 0);

				eval(fetch_email_phrases('activateaccount'));

				vbmail($email, $subject, $message, true);

			}
			else if ($newusergroupid == 2)
			{
				if ($vbulletin->options['welcomemail'])
				{
					eval(fetch_email_phrases('welcomemail'));
					vbmail($email, $subject, $message);
				}
			}

			($hook = vBulletinHook::fetch_hook('register_addmember_complete')) ? eval($hook) : false;

			if ($vbulletin->GPC['coppauser'])
			{
				$_REQUEST['do'] = 'coppaform';
			}
			else
			{
				if ($vbulletin->options['verifyemail'])
				{
					eval(standard_error(fetch_error('registeremail', $username, $email, create_full_url($vbulletin->url . $vbulletin->session->vars['sessionurl_q'])), '', false));
				}
				else
				{
					$vbulletin->url = str_replace('"', '', $vbulletin->url);
					if (!$vbulletin->url)
					{
						$vbulletin->url = $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'];
					}
					else
					{
						$vbulletin->url = iif(strpos($vbulletin->url, 'register.php') !== false, $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'], $vbulletin->url);
					}

					if ($vbulletin->options['moderatenewmembers'])
					{
						eval(standard_error(fetch_error('moderateuser', $username, $vbulletin->options['forumhome'], $vbulletin->session->vars['sessionurl_q']), '', false));
					}
					else
					{
						eval(standard_error(fetch_error('registration_complete', $username, $vbulletin->session->vars['sessionurl'], $vbulletin->options['bburl'] . '/' . $vbulletin->options['forumhome'] . '.php'), '', false));
					}
				}
			}
		}
	}
}
else if ($_GET['do'] == 'addmember')
{
	// hmm, this probably happened because of a template edit that put the login box in the header.
	exec_header_redirect($vbulletin->options['forumhome'] . '.php');
}

// ############################### start register ###############################
if ($_REQUEST['do'] == 'register')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'agree'   => TYPE_BOOL,
		'year'    => TYPE_UINT,
		'month'   => TYPE_UINT,
		'day'     => TYPE_UINT,
		'options' => TYPE_ARRAY_BOOL,
		'who'     => TYPE_NOHTML,
	));

	// Variables that are used in templates
	$agree =& $vbulletin->GPC['agree'];
	$year =& $vbulletin->GPC['year'];
	$month =& $vbulletin->GPC['month'];
	$day =& $vbulletin->GPC['day'];

	$url = $vbulletin->url;

	if (!$vbulletin->GPC['agree'])
	{
		eval(standard_error(fetch_error('register_not_agreed', $vbulletin->options['forumhome'], $vbulletin->session->vars['sessionurl_q'])));
	}
	if (!$vbulletin->options['allowregistration'])
	{
		eval(standard_error(fetch_error('noregister')));
	}

	if ($vbulletin->userinfo['userid'] AND !$vbulletin->options['allowmultiregs'])
	{
		eval(standard_error(fetch_error('alreadyregistered', $vbulletin->userinfo['username'], $vbulletin->session->vars['sessionurl'])));
	}

	($hook = vBulletinHook::fetch_hook('register_form_start')) ? eval($hook) : false;

	if ($errorlist)
	{
		$checkedoff['adminemail'] = iif($vbulletin->GPC['options']['adminemail'], 'checked="checked"');
		$checkedoff['showemail'] = iif($vbulletin->GPC['options']['showemail'], 'checked="checked"');
	}
	else
	{
		$checkedoff['adminemail'] = iif(bitwise($vbulletin->bf_misc_regoptions['adminemail'], $vbulletin->options['defaultregoptions']), 'checked="checked"');
		$checkedoff['showemail'] = iif(bitwise($vbulletin->bf_misc_regoptions['receiveemail'], $vbulletin->options['defaultregoptions']), 'checked="checked"');
	}

	if ($vbulletin->options['reqbirthday'] AND !$vbulletin->options['usecoppa'])
	{
		$show['birthday'] = true;
		$monthselected[str_pad($vbulletin->GPC['month'], 2, '0', STR_PAD_LEFT)] = 'selected="selected"';
		$dayselected[str_pad($vbulletin->GPC['day'], 2, '0', STR_PAD_LEFT)] = 'selected="selected"';

	    if ($year == 0)
	    {
	        $year = '';
    	}

		// Default Birthday Privacy option to show all
		if (empty($errorlist))
		{
			$sbselected = array(2 => 'selected="selected"');
		}
		eval('$birthdayfields = "' . fetch_template('modifyprofile_birthday') . '";');
	}
	else
	{
		$show['birthday'] = false;

		$birthdayfields = '';
	}

	$htmlonoff = ($vbulletin->options['allowhtml'] ? $vbphrase['on'] : $vbphrase['off']);
	$bbcodeonoff = ($vbulletin->options['allowbbcode'] ? $vbphrase['on'] : $vbphrase['off']);
	$imgcodeonoff = ($vbulletin->options['allowbbimagecode'] ? $vbphrase['on'] : $vbphrase['off']);
	$smiliesonoff = ($vbulletin->options['allowsmilies'] ? $vbphrase['on'] : $vbphrase['off']);

	// human verification
	if (fetch_require_hvcheck('register'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify = vB_HumanVerify::fetch_library($vbulletin);
		$human_verify = $verify->output_token();
	}

	// Referrer
	if ($vbulletin->options['usereferrer'] AND !$vbulletin->userinfo['userid'])
	{
		exec_switch_bg();
		if ($errorlist)
		{
			$referrername = $vbulletin->GPC['referrername'];
		}
		else if ($vbulletin->GPC[COOKIE_PREFIX . 'referrerid'])
		{
			if ($referrername = $db->query_first_slave("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = " . $vbulletin->GPC[COOKIE_PREFIX . 'referrerid']))
			{
				$referrername = $referrername['username'];
			}
		}
		$show['referrer'] = true;
	}
	else
	{
		$show['referrer'] = false;
	}

	// get extra profile fields
	if ($vbulletin->GPC['who'] != 'adult')
	{
		$bgclass1 = 'alt1';
	}

	$customfields_other = '';
	$customfields_profile = '';
	$customfields_option = '';

	$profilefields = $db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "profilefield
		WHERE editable > 0 AND required <> 0
		ORDER BY displayorder
	");
	while ($profilefield = $db->fetch_array($profilefields))
	{
		$profilefieldname = "field$profilefield[profilefieldid]";
		$optionalname = $profilefieldname . '_opt';
		$optionalfield = '';
		$optional = '';
		$profilefield['title'] = $vbphrase[$profilefieldname . '_title'];
		$profilefield['description'] = $vbphrase[$profilefieldname . '_desc'];
		if (!$errorlist)
		{
			unset($vbulletin->userinfo["$profilefieldname"]);
		}
		elseif (isset($vbulletin->GPC['userfield']["$profilefieldname"]))
		{
			$vbulletin->userinfo["$profilefieldname"] = $vbulletin->GPC['userfield']["$profilefieldname"];
		}

		$custom_field_holder = '';

		if ($profilefield['type'] == 'input')
		{
			if ($profilefield['data'] !== '')
			{
				$vbulletin->userinfo["$profilefieldname"] = $profilefield['data'];
			}
			else
			{
				$vbulletin->userinfo["$profilefieldname"] = htmlspecialchars_uni($vbulletin->userinfo["$profilefieldname"]);
			}
			eval('$custom_field_holder = "' . fetch_template('userfield_textbox') . '";');
		}
		else if ($profilefield['type'] == 'textarea')
		{
			if ($profilefield['data'] !== '')
			{
				$vbulletin->userinfo["$profilefieldname"] = $profilefield['data'];
			}
			else
			{
				$vbulletin->userinfo["$profilefieldname"] = htmlspecialchars_uni($vbulletin->userinfo["$profilefieldname"]);
			}
			eval('$custom_field_holder = "' . fetch_template('userfield_textarea') . '";');
		}
		else if ($profilefield['type'] == 'select')
		{
			$data = vb_unserialize($profilefield['data']);
			$selectbits = '';
			foreach ($data AS $key => $val)
			{
				$key++;
				$selected = '';
				if (isset($vbulletin->userinfo["$profilefieldname"]))
				{
					if (trim($val) == $vbulletin->userinfo["$profilefieldname"])
					{
						$selected = 'selected="selected"';
						$foundselect = 1;
					}
				}
				else if ($profilefield['def'] AND $key == 1)
				{
					$selected = 'selected="selected"';
					$foundselect = 1;
				}

				eval('$selectbits .= "' . fetch_template('userfield_select_option') . '";');
			}

			if ($profilefield['optional'])
			{
				if (!$foundselect AND $vbulletin->userinfo["$profilefieldname"])
				{
					$optional = htmlspecialchars_uni($vbulletin->userinfo["$profilefieldname"]);
				}
				eval('$optionalfield = "' . fetch_template('userfield_optional_input') . '";');
			}
			if (!$foundselect)
			{
				$selected = 'selected="selected"';
			}
			else
			{
				$selected = '';
			}
			$show['noemptyoption'] = iif($profilefield['def'] != 2, true, false);
			eval('$custom_field_holder = "' . fetch_template('userfield_select') . '";');
		}
		else if ($profilefield['type'] == 'radio')
		{
			$data = vb_unserialize($profilefield['data']);
			$radiobits = '';
			$foundfield = 0;
			$perline = 0;
			$unclosedtr = true;

			foreach ($data AS $key => $val)
			{
				$key++;
				$checked = '';
				if (!$vbulletin->userinfo["$profilefieldname"] AND $key == 1 AND $profilefield['def'] == 1)
				{
					$checked = 'checked="checked"';
				}
				else if (trim($val) == $vbulletin->userinfo["$profilefieldname"])
				{
					$checked = 'checked="checked"';
					$foundfield = 1;
				}
				if ($perline == 0)
				{
					$radiobits .= '<tr>';
				}
				eval('$radiobits .= "' . fetch_template('userfield_radio_option') . '";');
				$perline++;
				if ($profilefield['perline'] > 0 AND $perline >= $profilefield['perline'])
				{
					$radiobits .= '</tr>';
					$perline = 0;
					$unclosedtr = false;
				}
			}
			if ($unclosedtr)
			{
				$radiobits .= '</tr>';
			}
			if ($profilefield['optional'])
			{
				if (!$foundfield AND $vbulletin->userinfo["$profilefieldname"])
				{
					$optional = htmlspecialchars_uni($vbulletin->userinfo["$profilefieldname"]);
				}
				eval('$optionalfield = "' . fetch_template('userfield_optional_input') . '";');
			}
			eval('$custom_field_holder = "' . fetch_template('userfield_radio') . '";');
		}
		else if ($profilefield['type'] == 'checkbox')
		{
			$data = vb_unserialize($profilefield['data']);
			$radiobits = '';
			$perline = 0;
			$unclosedtr = true;
			foreach ($data AS $key => $val)
			{
				if ($vbulletin->userinfo["$profilefieldname"] & pow(2,$key))
				{
					$checked = 'checked="checked"';
				}
				else
				{
					$checked = '';
				}
				$key++;
				if ($perline == 0)
				{
					$radiobits .= '<tr>';
				}
				eval('$radiobits .= "' . fetch_template('userfield_checkbox_option') . '";');
				$perline++;
				if ($profilefield['perline'] > 0 AND $perline >= $profilefield['perline'])
				{
					$radiobits .= '</tr>';
					$perline = 0;
					$unclosedtr = false;
				}
			}
			if ($unclosedtr)
			{
				$radiobits .= '</tr>';
			}
			eval('$custom_field_holder = "' . fetch_template('userfield_radio') . '";');
		}
		else if ($profilefield['type'] == 'select_multiple')
		{
			$data = vb_unserialize($profilefield['data']);
			$selectbits = '';
			$selected = '';

			if ($profilefield['height'] == 0)
			{
				$profilefield['height'] = count($data);
			}

			foreach ($data AS $key => $val)
			{
				if ($vbulletin->userinfo["$profilefieldname"] & pow(2, $key))
				{
					$selected = 'selected="selected"';
				}
				else
				{
					$selected = '';
				}
				$key++;
				eval('$selectbits .= "' . fetch_template('userfield_select_option') . '";');
			}
			eval('$custom_field_holder = "' . fetch_template('userfield_select_multiple') . '";');
		}

		if ($profilefield['required'] == 2)
		{
			// not required to be filled in but still show
			$profile_variable =& $customfields_other;
		}
		else // required to be filled in
		{
			if ($profilefield['form'])
			{
				$profile_variable =& $customfields_option;
			}
			else
			{
				$profile_variable =& $customfields_profile;
			}
		}

		eval('$profile_variable .= "' . fetch_template('userfield_wrapper') . '";');
	}

	if (!$vbulletin->GPC['who'])
	{
		$vbulletin->GPC['who'] = iif($vbulletin->GPC['coppauser'], 'coppa', 'adult');
	}

	$show['coppa'] = $usecoppa = ($vbulletin->GPC['who'] == 'adult' OR !$vbulletin->options['usecoppa']) ? false : true;
	$show['customfields_profile'] = ($customfields_profile OR $show['birthday']) ? true : false;
	$show['customfields_option'] = ($customfields_option) ? true : false;
	$show['customfields_other'] = ($customfields_other) ? true : false;
	$show['email'] = ($vbulletin->options['enableemail'] AND $vbulletin->options['displayemails']) ? true : false;

	$vbulletin->input->clean_array_gpc('p', array(
		'timezoneoffset' => TYPE_NUM
	));

	// where do we send in timezoneoffset?
	if ($vbulletin->GPC['timezoneoffset'])
	{
		$timezonesel = $vbulletin->GPC['timezoneoffset'];
	}
	else
	{
		$timezonesel = $vbulletin->options['timeoffset'];
	}

	require_once(DIR . '/includes/functions_misc.php');
	$timezoneoptions = '';
	foreach (fetch_timezone() AS $optionvalue => $timezonephrase)
	{
		$optiontitle = $vbphrase["$timezonephrase"];
		$optionselected = iif($optionvalue == $timezonesel, 'selected="selected"', '');
		eval('$timezoneoptions .= "' . fetch_template('option') . '";');
	}
	eval('$timezoneoptions = "' . fetch_template('modifyoptions_timezone') . '";');

	($hook = vBulletinHook::fetch_hook('register_form_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('register') . '");');
}

// ############################### start activate form ###############################
if ($vbulletin->GPC['a'] == 'ver')
{
	// get username and password
	if (!$vbulletin->userinfo['userid'])
	{
		$vbulletin->userinfo['username'] = '';
	}

	if ($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])
	{
		$navbits = construct_navbits(array('' => $vbphrase['activate_your_account']));
		eval('$navbar = "' . fetch_template('navbar') . '";');
	}
	else
	{
		$navbar = '';
	}

	($hook = vBulletinHook::fetch_hook('register_activateform')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('activateform') . '");');
}

// ############################### start activate ###############################
if ($_REQUEST['do'] == 'activate')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'username'		=> TYPE_NOHTML,
		'activateid'	=> TYPE_STR,

		// These three are cleaned so that they will exist and not be overwritten in the next step

		'u'				=> TYPE_UINT,
		'a'				=> TYPE_NOHTML,
		'i'				=> TYPE_STR,
	));

	if ($userinfo = $db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username='" . $db->escape_string($vbulletin->GPC['username']) . "'"))
	{
		$vbulletin->GPC['u'] = $userinfo['userid'];
		$vbulletin->GPC['a'] = 'act';
		$vbulletin->GPC['i'] = $vbulletin->GPC['activateid'];
	}
	else
	{
		eval(standard_error(fetch_error('badlogin', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'], $strikes)));
	}
}

if ($vbulletin->GPC['a'] == 'act')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'u'		=> TYPE_UINT,
		'i'		=> TYPE_STR,
	));

	$userinfo = verify_id('user', $vbulletin->GPC['u'], 1, 1);

	($hook = vBulletinHook::fetch_hook('register_activate_start')) ? eval($hook) : false;

	if ($userinfo['usergroupid'] == 3)
	{
		// check valid activation id
		$user = $db->query_first("
			SELECT activationid, usergroupid, emailchange
			FROM " . TABLE_PREFIX . "useractivation
			WHERE activationid = '" . $db->escape_string($vbulletin->GPC['i']) . "'
				AND userid = $userinfo[userid]
				AND type = 0
		");
		if (!$user OR $vbulletin->GPC['i'] != $user['activationid'])
		{
			// send email again
			eval(standard_error(fetch_error('invalidactivateid', $vbulletin->session->vars['sessionurl'], $vbulletin->options['contactuslink'])));
		}

		// delete activationid
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "useractivation WHERE userid=$userinfo[userid] AND type=0");

		/*
		This shouldn't be needed any more since we handle this during registration
		if ($userinfo['coppauser'] OR ($vbulletin->options['moderatenewmembers'] AND !$userinfo['posts']))
		{
			// put user in moderated group
			$user['usergroupid'] = 4;
		}*/

		if (empty($user['usergroupid']))
		{
			$user['usergroupid'] = 2; // sanity check
		}

		// ### DO THE UG/TITLE UPDATE ###

		$getusergroupid = iif($userinfo['displaygroupid'] != $userinfo['usergroupid'], $userinfo['displaygroupid'], $user['usergroupid']);

		$user_usergroup =& $vbulletin->usergroupcache["$user[usergroupid]"];
		$display_usergroup =& $vbulletin->usergroupcache["$getusergroupid"];

		// init user data manager
		$userdata = datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
		$userdata->set_existing($userinfo);
		$userdata->set('usergroupid', $user['usergroupid']);
		$userdata->set_usertitle(
			$user['customtitle'] ? $user['usertitle'] : '',
			false,
			$display_usergroup,
			($user_usergroup['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
			($user_usergroup['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cancontrolpanel']) ? true : false
		);

		require_once(DIR . '/includes/functions_ranks.php');
		if ($user['userid'] == $vbulletin->userinfo['userid'])
		{
			$vbulletin->userinfo['usergroupid'] = $user['usergroupid'];
			$vbulletin->userinfo['displaygroupid'] = $user['usergroupid'];
		}

		// see 3.6.x bug #176
		//$userinfo['usergroupid'] = $user['usergroupid'];

		($hook = vBulletinHook::fetch_hook('register_activate_process')) ? eval($hook) : false;

		if ($userinfo['coppauser'] OR ($vbulletin->options['moderatenewmembers'] AND !$userinfo['posts']))
		{
			// put user in moderated group
			$userdata->save();
			eval(standard_error(fetch_error('moderateuser', $userinfo['username'], $vbulletin->options['forumhome'], $vbulletin->session->vars['sessionurl_q']), '', false));
		}
		else
		{
			// activate account
			$userdata->save();

			$username = unhtmlspecialchars($userinfo['username']);
			if (!$user['emailchange'])
			{
				if ($vbulletin->options['welcomemail'])
				{
					eval(fetch_email_phrases('welcomemail'));
					vbmail($userinfo['email'], $subject, $message);
				}

				$userdata->send_welcomepm();
			}

			if ($user['emailchange'])
			{
				eval(standard_error(fetch_error('emailchanged', htmlspecialchars_uni($userinfo['email'])), '', false));
			}
			else
			{
				eval(standard_error(fetch_error('registration_complete', $userinfo['username'], $vbulletin->session->vars['sessionurl'], $vbulletin->options['bburl'] . '/' . $vbulletin->options['forumhome'] . '.php'), '', false));
			}
		}
	}
	else
	{
		if ($userinfo['usergroupid'] == 4)
		{
			// In Moderation Queue
			eval(standard_error(fetch_error('activate_moderation'), '', false));
		}
		else
		{
			// Already activated
			eval(standard_error(fetch_error('activate_wrongusergroup')));
		}
	}

}

// ############################### start request activation email ###############################
if ($_REQUEST['do'] == 'requestemail')
{
	$email = $vbulletin->input->clean_gpc('r', 'email', TYPE_NOHTML);

	if ($vbulletin->userinfo['userid'] AND $vbulletin->GPC['email'] === '')
	{
		$email = $vbulletin->userinfo['email'];
	}
	else
	{
		$email = $vbulletin->GPC['email'];
	}

	if ($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])
	{
		$navbits = construct_navbits(array(
			'register.php?' . $vbulletin->session->vars['sessionurl'] . 'a=ver' => $vbphrase['activate_your_account'],
			'' => $vbphrase['email_activation_codes']
		));
		eval('$navbar = "' . fetch_template('navbar') . '";');
	}
	else
	{
		$navbar = '';
	}

	($hook = vBulletinHook::fetch_hook('register_requestemail')) ? eval($hook) : false;

	$url =& $vbulletin->url;
	eval('print_output("' . fetch_template('activate_requestemail') . '");');
}

// ############################### process request activation email #############################
if ($_POST['do'] == 'emailcode')
{
	$vbulletin->input->clean_gpc('r', 'email', TYPE_NOHTML);

	$users = $db->query_read_slave("
		SELECT user.userid, user.usergroupid, username, email, activationid, languageid
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "useractivation AS useractivation ON(user.userid = useractivation.userid AND type = 0)
		WHERE email = '" . $db->escape_string($vbulletin->GPC['email']) . "'"
	);

	if ($db->num_rows($users))
	{
		while ($user = $db->fetch_array($users))
		{
			if ($user['usergroupid'] == 3)
			{ // only do it if the user is in the correct usergroup
				// make random number
				if (empty($user['activationid']))
				{ //none exists so create one
					$user['activationid'] = build_user_activation_id($user['userid'], 2, 0);
				}
				else
				{
					$user['activationid'] = fetch_random_string(40);
					$db->query_write("
						UPDATE " . TABLE_PREFIX . "useractivation SET
							dateline = " . TIMENOW . ",
							activationid = '$user[activationid]'
						WHERE userid = $user[userid]
							AND type = 0
					");
				}

				$userid = $user['userid'];
				$username = $user['username'];
				$activateid = $user['activationid'];

				($hook = vBulletinHook::fetch_hook('register_emailcode_user')) ? eval($hook) : false;

				eval(fetch_email_phrases('activateaccount', $user['languageid']));

				vbmail($user['email'], $subject, $message, true);
			}
		}

		eval(print_standard_redirect('redirect_lostactivatecode', true, true));
	}
	else
	{
		eval(standard_error(fetch_error('invalidemail', $vbulletin->options['contactuslink'])));
	}

}

// ############################### start coppa form ###############################
if ($_REQUEST['do'] == 'coppaform')
{
	if ($vbulletin->userinfo['userid'])
	{
		$vbulletin->userinfo['signature'] = nl2br($vbulletin->userinfo['signature']);

		if ($vbulletin->userinfo['showemail'])
		{
			$vbulletin->userinfo['showemail'] = $vbphrase['no'];
		}
		else
		{
			$vbulletin->userinfo['showemail'] = $vbphrase['yes'];
		}
	}
	else
	{
		$vbulletin->userinfo['username'] = '';
		$vbulletin->userinfo['homepage'] = 'http://';
	}

	($hook = vBulletinHook::fetch_hook('register_coppaform')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('register_coppaform') . '");');
}

// ############################### start delete activation request ###############################
if ($_REQUEST['do'] == 'deleteactivation')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'u'		=> TYPE_UINT,
		'i'		=> TYPE_STR,
	));

	$userinfo = verify_id('user', $vbulletin->GPC['u'], 1, 1);

	if ($userinfo['usergroupid'] == 3)
	{
		// check valid activation id
		$user = $db->query_first("
			SELECT userid, activationid, usergroupid
			FROM " . TABLE_PREFIX . "useractivation
			WHERE activationid = '" . $db->escape_string($vbulletin->GPC['i']) . "'
				AND userid = $userinfo[userid]
				AND type = 0
		");

		if (!$user OR $vbulletin->GPC['i'] != $user['activationid'])
		{
			eval(standard_error(fetch_error('invalidactivateid', $vbulletin->session->vars['sessionurl'], $vbulletin->options['contactuslink'])));
		}

		eval(standard_error(fetch_error('activate_deleterequest', $user['activationid'], $user['userid'])));
	}
	else
	{
		eval(standard_error(fetch_error('activate_wrongusergroup')));
	}
}

// ############################### start kill activation request ###############################
if ($_REQUEST['do'] == 'killactivation')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'u'		=> TYPE_UINT,
		'i'		=> TYPE_STR,
	));

	$userinfo = verify_id('user', $vbulletin->GPC['u'], 1, 1);

	if ($userinfo['usergroupid'] == 3)
	{
		// check valid activation id
		$user = $db->query_first("
			SELECT activationid, usergroupid
			FROM " . TABLE_PREFIX . "useractivation
			WHERE activationid = '" . $db->escape_string($vbulletin->GPC['i']) . "'
				AND userid = $userinfo[userid]
				AND type = 0
		");

		if (!$user OR $vbulletin->GPC['i'] != $user['activationid'])
		{
			eval(standard_error(fetch_error('invalidactivateid', $vbulletin->session->vars['sessionurl'], $vbulletin->options['contactuslink'])));
		}

		$userdata = datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
		$userdata->set_existing($userinfo);
		$userdata->set_bitfield('options', 'receiveemail', 0);
		$userdata->set_bitfield('options', 'noactivationmails', 1);
		$userdata->save();

		eval(standard_error(fetch_error('activate_requestdeleted')));
	}
	else
	{
		eval(standard_error(fetch_error('activate_wrongusergroup')));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 94041 $
|| # $Date: 2017-05-15 16:41:54 -0700 (Mon, 15 May 2017) $
|| ####################################################################
\*======================================================================*/
?>
