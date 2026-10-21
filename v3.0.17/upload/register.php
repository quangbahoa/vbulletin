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
define('THIS_SCRIPT', 'register');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('timezone', 'user', 'register');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'banemail'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'register',
	'register_rules',
	'register_verify_age',
	'register_coppaform',
	'register_imagebit',
	'userfield_textbox',
	'userfield_checkbox_option',
	'userfield_optional_input',
	'userfield_radio',
	'userfield_radio_option',
	'userfield_select',
	'userfield_select_option',
	'userfield_select_multiple',
	'userfield_textarea',
	'modifyoptions_timezone',
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
require_once('./includes/functions_user.php');
require_once('./includes/functions_misc.php');
require_once('./includes/functions_register.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_REQUEST['do']) AND empty($_REQUEST['a']))
{
	$_REQUEST['do'] = 'signup';
}

if ($url == REFERRER)
{
	$url = urlencode($url);
}

// ############################### start checkdate ###############################
if ($_REQUEST['do'] == 'checkdate')
{
	// check their birthdate
	globalize($_REQUEST, array('month' => INT, 'year' => INT, 'day' => INT));
	$current['year'] = date('Y');
	$current['month'] = date('m');

	if ($month == 0 OR !preg_match('#^\d{4}$#', $year))
	{
		eval(print_standard_error('select_valid_month_and_year'));
	}

	if ($year < ($current['year'] - 13) OR ($year == ($current['year'] - 13) AND $month <= $current['month']))
	{
		// just regular adult registration
		exec_header_redirect("register.php?$session[sessionurl]do=signup&who=adult&url=$url" . iif($month, "&month=$month") . iif($year, "&year=$year") . iif($day, "&day=$day"));
	}
	else
	{
		if ($vboptions['usecoppa'] == 2)
		{
			// turn away as they're under 13
			eval(print_standard_error('error_under_thirteen_registration_denied'));
		}
		else
		{
			// use the coppa registration
			exec_header_redirect("register.php?$session[sessionurl]do=signup&who=coppa&url=$url" . iif($month, "&month=$month") . iif($year, "&year=$year") . iif($day, "&day=$day"));
		}
	}

	exit;
}

// ############################### start signup ###############################
if ($_REQUEST['do'] == 'signup')
{
	globalize($_REQUEST, array(
		'month' => INT,
		'day' => INT,
		'year' => INT
	));

	if (!$vboptions['allowregistration'])
	{
		eval(print_standard_error('error_noregister'));
	}

	if ($bbuserinfo['userid'] != 0 AND !$vboptions['allowmultiregs'])
	{
		eval(print_standard_error('error_alreadyregistered'));
	}

	if (!$vboptions['usecoppa'])
	{
		// don't use COPPA - assume adult
		$who = 'adult';
	}
	else
	{
		$who = trim($_REQUEST['who']);
	}

	if ($who == 'coppa' AND $vboptions['usecoppa'] == 1)
	{
		$show['coppa'] = true;
		$templatename = 'register_rules';
	}
	else if ($who == 'adult')
	{
		$show['coppa'] = false;
		$templatename = 'register_rules';
	}
	else
	{
		$templatename = 'register_verify_age';
	}

	eval('print_output("' . fetch_template($templatename) . '");');
}

// ############################### start add member ###############################
if ($_POST['do'] == 'addmember')
{

	globalize($_POST, array('options'));

	if (!$vboptions['allowregistration'])
	{
		eval(print_standard_error('error_noregister'));
	}

	// check for multireg
	if ($bbuserinfo['userid'] != 0 AND !$vboptions['allowmultiregs'])
	{
		$username = $bbuserinfo['username'];
		eval(print_standard_error('error_alreadyregistered'));
	}

	$errors = array();

	// check username does not contain semi-colons
	if (preg_match('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $_POST['username']))
	{
		//eval(print_standard_error('error_username_semicolon'));
		eval('$errors[10] = "' . fetch_phrase('username_semicolon', PHRASETYPEID_ERROR) . '";');
	}

	// strip 'blank' ascii chars if admin wants to do so
	$_POST['username'] = strip_blank_ascii($_POST['username'], ' ');

	// convert any whitespace to a single space to prevent users entering 'user    one' to look like 'user one'
	$_POST['username'] = trim(preg_replace('#\s+#si', ' ', $_POST['username']));

	// do add user

	$unicode_name = preg_replace('/&#([0-9]+);/esiU', "convert_int_to_utf8('\\1')", $_POST['username']);
	if (!empty($_POST['username']) AND
		$checkuser = $DB_site->query_first("
			SELECT username
			FROM " . TABLE_PREFIX . "user
			WHERE username IN ('" . addslashes(htmlspecialchars_uni($_POST['username'])) . "', '" . addslashes(htmlspecialchars_uni($unicode_name)) . "')
		")
	)
	{
		$username = htmlspecialchars_uni($_POST['username']);
		eval('$errors[20] = "' . fetch_phrase('usernametaken', PHRASETYPEID_ERROR) . '";');
	}

	// check for valid email address
	if (!empty($_POST['email']) AND !empty($_POST['emailconfirm']) AND !is_valid_email($_POST['email']))
	{
		eval('$errors[30] = "' . fetch_phrase('bademail', PHRASETYPEID_ERROR) . '";');
	}

	// check for banned email address
	if (!empty($_POST['email']) AND is_banned_email($_POST['email']))
	{
		eval('$errors[40] = "' . fetch_phrase('banemail', PHRASETYPEID_ERROR) . '";');
	}

	// check for unique email address
	if (!empty($_POST['email']) AND $vboptions['requireuniqueemail'] AND $checkuser = $DB_site->query_first("SELECT username,email FROM " . TABLE_PREFIX . "user WHERE email='" . addslashes($_POST['email']) . "'"))
	{
		eval('$errors[50] = "' . fetch_phrase('emailtaken', PHRASETYPEID_ERROR) . '";');
	}

	// check for missing fields
	if (($_POST['coppauser'] AND empty($_POST['parentemail'])) OR empty($_POST['username']) OR empty($_POST['email']) OR empty($_POST['emailconfirm']) OR (empty($_POST['password']) AND empty($_POST['password_md5'])) OR (empty($_POST['passwordconfirm']) AND empty($_POST['passwordconfirm_md5'])))
	{
		eval('$errors[60] = "' . fetch_phrase('fieldmissing', PHRASETYPEID_ERROR) . '";');
	}

	// check for matching passwords
	if ($_POST['password'] != $_POST['passwordconfirm'] OR (strlen($_POST['password_md5']) == 32 AND $_POST['password_md5'] != $_POST['passwordconfirm_md5']))
	{
		eval('$errors[70] = "' . fetch_phrase('passwordmismatch', PHRASETYPEID_ERROR) . '";');
	}

	// check for matching email addresses
	if ($_POST['email'] != $_POST['emailconfirm'])
	{
		eval('$errors[80] = "' . fetch_phrase('emailmismatch', PHRASETYPEID_ERROR) . '";');
	}

	// check for min username length
	if (!empty($_POST['username']) AND vbstrlen($_POST['username']) < $vboptions['minuserlength'])
	{
		eval('$errors[90] = "' . fetch_phrase('usernametooshort', PHRASETYPEID_ERROR) . '";');
	}
	// check for max username length
	else if (vbstrlen($_POST['username']) > $vboptions['maxuserlength'])
	{
		eval('$errors[100] = "' . fetch_phrase('usernametoolong', PHRASETYPEID_ERROR) . '";');
	}

	// check referrer
	$testreferrerid['userid'] = 0;
	if ($vboptions['usereferrer'] AND $bbuserinfo['userid'] == 0)
	{
		 if ($_POST['referrername'])
		 {
			if (!$testreferrerid = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . addslashes(htmlspecialchars_uni($_POST['referrername'])) . "'"))
			{
				eval('$errors[130] = "' . fetch_phrase('badreferrer', PHRASETYPEID_ERROR) . '";');
			}
		 }
	}

	// Check Reg Image
	if ($vboptions['regimagecheck'] AND $vboptions['gdversion'])
	{
		$imagestamp = trim(str_replace(' ', '', $_POST['imagestamp']));

		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "regimage
			WHERE regimagehash = '" . addslashes($_POST['imagehash']) . "'
				AND imagestamp = '" . addslashes($imagestamp) . "'
		");

		if (!$DB_site->affected_rows())
		{
	  		//eval(print_standard_error('error_register_imagecheck'));
	  		eval('$errors[140] = "' . fetch_phrase('register_imagecheck', PHRASETYPEID_ERROR) . '";');
	  	}

	  	unset($imagestamp);
	  	unset($_POST['imagehash']);
	  	unset($_POST['imagestamp']);
	}

	// assign user to group 3 if email needs verification
	if ($vboptions['verifyemail'])
	{
		$newusergroupid = 3;
	}
	else
	{
		if ($vboptions['moderatenewmembers'] OR $_POST['coppauser'])
		{
			$newusergroupid = 4;
		}
		else
		{
			$newusergroupid = 2;
		}
	}

	// get user title from usergroupcache
	$usergroup = $usergroupcache["$newusergroupid"];
	if ($usergroup['usertitle'] == '')
	{
		$gettitle = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "usertitle WHERE minposts<=0 ORDER BY minposts DESC LIMIT 1");
		$usertitle = $gettitle['title'];
	}
	else
	{
		$usertitle = $usergroup['usertitle'];
	}

	// check for censored words in username
	if ($_POST['username'] != fetch_censored_text($_POST['username']))
	{
		//eval(print_standard_error('error_censorfield'));
		eval('$errors[150] = "' . fetch_phrase('censorfield', PHRASETYPEID_ERROR) . '";');
	}

	// check for illegal username
	if (!empty($vboptions['illegalusernames']))
	{
		$usernames = preg_split('/\s+/', $vboptions['illegalusernames'], -1, PREG_SPLIT_NO_EMPTY);
		foreach ($usernames AS $val)
		{
			if (strpos(strtolower($_POST['username']), strtolower($val)) !== false)
			{
				$username = &$val;
				eval('$errors[160] = "' . fetch_phrase('usernametaken', PHRASETYPEID_ERROR) . '";');
			}
		}
	}

	// check extra profile fields
	$userfields = '';
	$userfieldsnames = '(userid';
	$profilefields = $DB_site->query("
		SELECT maxlength, profilefieldid, required, title, size, type, data, optional, def, regex
		FROM " . TABLE_PREFIX . "profilefield
		WHERE editable > 0
		ORDER BY displayorder
	");
	while ($profilefield = $DB_site->fetch_array($profilefields))
	{
		$havefields = 1;
		$varname = "field$profilefield[profilefieldid]";
		$$varname = $_POST["$varname"];
		$optionalvar = $varname . '_opt';
		$$optionalvar = $_POST["$optionalvar"];
		$bitwise = 0;

		if ($profilefield['type'] == 'input' OR $profilefield['type'] == 'textarea')
		{
			if ($profilefield['required'])
			{
				$$varname = substr(fetch_censored_text($$varname), 0, $profilefield['maxlength']);
			}
			else if ($profilefield['data'])
			{
				$$varname = unhtmlspecialchars($profilefield['data']);
			}
			else
			{
				continue;
			}
		}
		if ($profilefield['type'] == 'radio' OR $profilefield['type'] == 'select')
		{
			if ($profilefield['required'])
			{
				if ($$varname == 0)
				{
					$$varname = '';
				}
				else
				{
					$data = unserialize($profilefield['data']);
					foreach ($data AS $key => $val)
					{
						$key++;
						if ($key == $$varname)
						{
							$$varname = trim($val);
							break;
						}
					}
				}
				if ($profilefield['optional'] AND $$optionalvar)
				{
					$$varname = substr(fetch_censored_text($$optionalvar), 0, $profilefield['maxlength']);
				}
			}
			else if ($profilefield['def'])
			{
				$data = unserialize($profilefield['data']);
				$$varname = unhtmlspecialchars($data[0]);
			}
			else
			{
				continue;
			}
		}
		if ($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple')
		{

			if ($profilefield['required'])
			{
				if (is_array($$varname))
				{
					foreach ($$varname AS $key => $val)
					{
						$bitwise += pow(2, $val - 1);
					}
					if (($profilefield['size'] != 0) AND (sizeof($$varname) > $profilefield['size']))
					{
						eval('$errors[170] = "' . fetch_phrase('checkboxsize', PHRASETYPEID_ERROR) . '";');
					}
					$$varname = $bitwise;
				}
			}
			else
			{
				continue;
			}
		}

		if ($profilefield['regex'])
		{
			if (!preg_match('#' . str_replace('#', '\#', $profilefield['regex']) . '#siU', $$varname))
			{
				if ($$varname != '')
				{
					eval('$errors[185] = "' . fetch_phrase('regexincorrect', PHRASETYPEID_ERROR) . '";');
				}

			}
		}

		if ($profilefield['required'] == 1 AND $$varname == '')
		{
			eval('$errors[180] = "' . fetch_phrase('requiredfieldmissing', PHRASETYPEID_ERROR) . '";');
		}

		$userfieldsnames.= ",field$profilefield[profilefieldid]";
		$userfields .= ',\'' . addslashes(htmlspecialchars_uni($$varname)) . "'";

		$bbuserinfo["$varname"] = $$varname;
	}
	$userfieldsnames .= ')';

	if (bitwise(REGOPTION_REQBIRTHDAY, $vboptions['defaultregoptions']))
	{
    	$day = intval($_POST['day']);
    	$month = intval($_POST['month']);
    	$year = intval($_POST['year']);

    	if ($day == -1 OR $month == -1)
    	{
    	    eval('$errors[190] = "' . fetch_phrase('birthdayfield', PHRASETYPEID_ERROR) . '";');
    	}
    	else
    	{
    	    if (($year > 1901) AND ($year < date('Y')))
    	    {
    	        if (checkdate($month, $day, $year))
    	        {
    	            $birthday = str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT) . '-' . $year;
    	            $birthday_search = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    	        }
    	        else
    	        {
    	            eval('$errors[190] = "' . fetch_phrase('birthdayfield', PHRASETYPEID_ERROR) . '";');
    	        }
    	    }
    	    else if ($year >= date('Y'))
    	    {
    	        eval('$errors[190] = "' . fetch_phrase('birthdayfield', PHRASETYPEID_ERROR) . '";');
    	    }
    	    else
    	    {
    	        if (checkdate($month, $day, 1996)) // Allow Feb 29th if the user doesn't specify a year..
    	        {
    	            $birthday = str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT) . '-0000';
    	            $birthday_search = '0000-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    	        }
    	        else
    	        {
    	            eval('$errors[190] = "' . fetch_phrase('birthdayfield', PHRASETYPEID_ERROR) . '";');
    	        }
    	    }
    	    if ($vboptions['showbirthdays'])
    	    {
    	        $todayneggmt = date('n-j', TIMENOW + (-12 - $vboptions['timeoffset']) * 3600);
    	        $todayposgmt = date('n-j', TIMENOW + (12 - $vboptions['timeoffset']) * 3600);
    	        if ($todayneggmt == $month . '-' . $day OR $todayposgmt == $month . '-' . $day)
    	        {
    	            require_once('./includes/functions_databuild.php');
    	            build_birthdays();
    	        }
    	    }
    	}
	}
	else
	{
		$birthday = '';
		$birthday_search = '';
	}

	if (sizeof($errors) > 0)
	{
		$_REQUEST['do'] = 'register';
		foreach ($errors AS $index => $error)
		{
			$errorlist .= "<li>$error</li>";
		}

		if ($_POST['timezoneoffset'] < 0)
		{
			$arrayindex = 'n' . (-$_POST['timezoneoffset'] * 10);
			$timezonesel["$arrayindex"] = HTML_SELECTED;
		}
		else
		{
			$arrayindex = $_POST['timezoneoffset'] * 10;
			$timezonesel["$arrayindex"] = HTML_SELECTED;
		}

		$username = htmlspecialchars_uni($_POST['username']);
		$email = htmlspecialchars_uni($_POST['email']);
		$emailconfirm = htmlspecialchars_uni($_POST['emailconfirm']);
		$parentemail = htmlspecialchars_uni($_POST['parentemail']);
		$dstsel["$_POST[dst]"] = HTML_SELECTED;
		$show['errors'] = true;
	}
	else
	{
		$show['errors'] = false;
		// Delete the regimage so that no other processes can try to use it.
		if ($vboptions['regimagecheck'] AND $vboptions['gdversion'])
		{
		  	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "regimage WHERE regimagehash = '" . addslashes($_POST['imagehash']) . "'");
		}

		$salt = fetch_user_salt(3);
		if (strlen($_POST['password_md5']) == 32)
		{
			$hashedpassword = md5($_POST['password_md5'] . $salt);
		}
		else
		{
			$hashedpassword = md5(md5($_POST['password']) . $salt);
		}

		// Determine this user's reputationlevelid.
		$reputationlevel = $DB_site->query_first("
			SELECT reputationlevelid
			FROM " . TABLE_PREFIX . "reputationlevel
			WHERE minimumreputation <= " . intval($vboptions['reputationdefault']) . "
			ORDER BY minimumreputation DESC
			LIMIT 1
		");

		// Set Registration Defaults

		$regoption = array();
		if (bitwise(REGOPTION_SUBSCRIBE_NONE, $vboptions['defaultregoptions']))
		{
			$regoption['autosubscribe'] = -1;
		}
		else if (bitwise(REGOPTION_SUBSCRIBE_NONOTIFY, $vboptions['defaultregoptions']))
		{
			$regoption['autosubscribe'] = 0;
		}
		else if (bitwise(REGOPTION_SUBSCRIBE_INSTANT, $vboptions['defaultregoptions']))
		{
			$regoption['autosubscribe'] = 1;
		}
		else if (bitwise(REGOPTION_SUBSCRIBE_DAILY, $vboptions['defaultregoptions']))
		{
			$regoption['autosubscribe'] = 2;
		}
		else
		{
			$regoption['autosubscribe'] = 3;
		}

		if (bitwise(REGOPTION_VBCODE_NONE, $vboptions['defaultregoptions']))
		{
			$regoption['showvbcode'] = 0;
		}
		else if (bitwise(REGOPTION_VBCODE_STANDARD, $vboptions['defaultregoptions']))
		{
			$regoption['showvbcode'] = 1;
		}
		else
		{
			$regoption['showvbcode'] = 2;
		}

		if (bitwise(REGOPTION_THREAD_LINEAR_OLDEST, $vboptions['defaultregoptions']))
		{
			$regoption['threadedmode'] = 0;
			$options['postorder'] = 0;
		}
		else if (bitwise(REGOPTION_THREAD_LINEAR_NEWEST, $vboptions['defaultregoptions']))
		{
			$regoption['threadedmode'] = 0;
			$options['postorder'] = 1;
		}
		else if (bitwise(REGOPTION_THREAD_THREADED, $vboptions['defaultregoptions']))
		{
			$regoption['threadedmode'] = 1;
			$options['postorder'] = 0;
		}
		else if (bitwise(REGOPTION_THREAD_HYBRID, $vboptions['defaultregoptions']))
		{
			$regoption['threadedmode'] = 2;
			$options['postorder'] = 0;
		}
		else
		{
			$regoption['threadedmode'] = 0;
			$options['postorder'] = 0;
		}

		$regoption['pmpopup'] = bitwise(REGOPTION_PMPOPUP, $vboptions['defaultregoptions']);

		$regoptions = array();
		// check coppa things
		if ($_POST['coppauser'])
		{
			$username = $_POST['username'];
			$password = $_POST['password'];
			eval(fetch_email_phrases('parentcoppa'));
			vbmail($_POST['parentemail'], $subject, $message, true);
			$options['coppauser'] = 1;
		}
		else
		{
			$_POST['parentemail'] = '';
			$options['coppauser'] = 0;
		}

		// check daylight saving stuff
		switch ($_POST['dst'])
		{
			case 2:
				$options['dstauto'] = 1;
				$options['dstonoff'] = 0;
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

		$options['invisible'] = iif(bitwise(REGOPTION_INVISIBLEMODE, $vboptions['defaultregoptions']), 1, 0);
		$options['receivepm'] = iif(bitwise(REGOPTION_ENABLEPM, $vboptions['defaultregoptions']), 1, 0);
		$options['emailonpm'] = iif(bitwise(REGOPTION_EMAILONPM, $vboptions['defaultregoptions']), 1, 0);
		$options['showreputation'] = iif(bitwise(REGOPTION_SHOWREPUTATION, $vboptions['defaultregoptions']), 1, 0);
		$options['showvcard'] = iif(bitwise(REGOPTION_VCARD, $vboptions['defaultregoptions']), 1, 0);
		$options['showsignatures'] = iif(bitwise(REGOPTION_SIGNATURE, $vboptions['defaultregoptions']), 1, 0);
		$options['showavatars'] = iif(bitwise(REGOPTION_AVATAR, $vboptions['defaultregoptions']), 1, 0);
		$options['showimages'] = iif(bitwise(REGOPTION_IMAGE, $vboptions['defaultregoptions']), 1, 0);

		$options = convert_array_to_bits($options, $_USEROPTIONS);

		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "user
				(username, salt, password, passworddate, email, parentemail,
				showvbcode, usertitle, joindate, daysprune, lastvisit, lastactivity, usergroupid, timezoneoffset,
				options, maxposts, threadedmode, startofweek, ipaddress, pmpopup, referrerid,
				reputationlevelid, reputation, autosubscribe, birthday, birthday_search)
			VALUES
				('" . addslashes(htmlspecialchars_uni($_POST['username'])) . "',
				'" . addslashes($salt) . "',
				'" . addslashes($hashedpassword) . "',
				NOW(),
				'" . addslashes(htmlspecialchars_uni($_POST['email'])) . "',
				'" . addslashes(htmlspecialchars_uni($_POST['parentemail'])) . "',
				$regoption[showvbcode],
				'" . addslashes($usertitle) . "',
				" . TIMENOW . ",
				0,
				" . TIMENOW . ",
				" . TIMENOW . ",
				" . intval($newusergroupid) . ",
				'" . addslashes($_POST['timezoneoffset']) . "',
				$options,
				-1,
				$regoption[threadedmode],
				1,
				'" . addslashes(IPADDRESS) . "',
				$regoption[pmpopup],
				" . intval($testreferrerid['userid']) . ",
				" . intval($reputationlevel['reputationlevelid']) . ",
				" . intval($vboptions['reputationdefault']) . ",
				$regoption[autosubscribe],
				'$birthday',
				'$birthday_search'
			)
		");
		$userid = $DB_site->insert_id();
		// Insert user text fields
		$DB_site->query("INSERT INTO " . TABLE_PREFIX . "usertextfield (userid) VALUES ($userid)");
		// insert custom user fields
		$DB_site->query("INSERT INTO " . TABLE_PREFIX . "userfield $userfieldsnames VALUES ($userid$userfields)");
		// insert record into password history
        $DB_site->query("INSERT INTO " . TABLE_PREFIX . "passwordhistory (userid, password, passworddate) VALUES ($userid, '" . addslashes($hashedpassword) . "', NOW())");

		$bbuserinfo['userid'] = $userid;

		// save user count and new user id to template
		require_once('./includes/functions_databuild.php');
		build_user_statistics();

		$DB_site->query("UPDATE " . TABLE_PREFIX . "session SET userid=$userid WHERE sessionhash='" . addslashes($session['dbsessionhash']) . "'");

		if ($vboptions['newuseremail'] != '')
		{
			if ($havefields)
			{
				 $DB_site->data_seek(0, $profilefields);
				 while ($profilefield = $DB_site->fetch_array($profilefields))
				 {
					$cfield = '';
				 	$varname = "field$profilefield[profilefieldid]";

				 	if ($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple')
				 	{
				 		$data = unserialize($profilefield['data']);

						foreach ($data AS $key => $value)
				 		{
							$pow = pow(2, $key);
				 			if (pow(2, $key) & $$varname)
				 			{
								$cfield .= (!empty($cfield) ? ', ' : '') . $data["$key"];
				 			}
				 		}
					}
					else
					{
						$cfield = $$varname;
					}
					$customfields .= "$profilefield[title] : $cfield\n";
				 }
			}

			$username = $_POST['username'];
			$email = $_POST['email'];
			eval(fetch_email_phrases('newuser', 0));

			$newemails = explode(' ', $vboptions['newuseremail']);
			foreach ($newemails AS $toemail)
			{
				if (trim($toemail))
				{
					vbmail($toemail, $subject, $message);
				}
			}
		}

		$username = htmlspecialchars_uni($_POST['username']);
		$email = htmlspecialchars_uni($_POST['email']);

		// sort out emails and usergroups
		if ($vboptions['verifyemail'])
		{
			$activateid = build_user_activation_id($userid, 2, 0);

			eval(fetch_email_phrases('activateaccount'));

			vbmail($email, $subject, $message, true);

		}
		else if (!$vboptions['moderatenewmembers'] AND $vboptions['welcomemail'])
		{
			eval(fetch_email_phrases('welcomemail'));
			vbmail($email, $subject, $message);
		}

		$url = urldecode($url);
		if ($coppauser)
		{
			$_REQUEST['do'] = 'coppaform';
		}
		else
		{
			if ($vboptions['verifyemail'])
			{
				eval(print_standard_error('error_registeremail', 1, 0));
			}
			else
			{
				if ($vboptions['moderatenewmembers'])
				{
					eval(print_standard_error('error_moderateuser', 1, 0));
				}
				else
				{
					$url = str_replace('"', '', $url);
					if (!$url)
					{
						$url = "$vboptions[forumhome].php?$session[sessionurl]";
					}
					else
					{
						$url = iif(strpos($url, 'register.php') !== false, "$vboptions[forumhome].php?$session[sessionurl]", $url);
					}

					eval(print_standard_error('registration_complete', 1, 0));
				}
			}
		}
	}
}

// ############################### start register ###############################
if ($_REQUEST['do'] == 'register')
{

	globalize($_REQUEST, array(
		'month' => INT,
		'day' => INT,
		'year' => INT,
		'agree' => INT,
		'options'
	));

	if (empty($agree))
	{
		eval(print_standard_error('register_not_agreed'));
	}
	if (!$vboptions['allowregistration'])
	{
		eval(print_standard_error('error_noregister'));
	}

	if ($bbuserinfo['userid'] != 0 AND !$vboptions['allowmultiregs'])
	{
		eval(print_standard_error('error_alreadyregistered'));
	}

	if (!$errorlist)
	{
		if ($vboptions['timeoffset'] < 0)
		{
			$timezonesel['n' . (-$vboptions['timeoffset'] * 10)] = HTML_SELECTED;
		}
		else
		{
			$index = $vboptions['timeoffset'] * 10;
			$timezonesel["$index"] = HTML_SELECTED;
		}
	}

	if ($errorlist)
	{
		$checkedoff['adminemail'] = iif($options['adminemail'], HTML_CHECKED);
		$checkedoff['showemail'] = iif($options['showemail'], HTML_CHECKED);
	}
	else
	{
		$checkedoff['adminemail'] = iif(bitwise(REGOPTION_ADMINEMAIL, $vboptions['defaultregoptions']), HTML_CHECKED);
		$checkedoff['showemail'] = iif(bitwise(REGOPTION_RECEIVEEMAIL, $vboptions['defaultregoptions']), HTML_CHECKED);
	}

	if (bitwise(REGOPTION_REQBIRTHDAY, $vboptions['defaultregoptions']))
	{
		$show['birthday'] = true;
		$monthselected["$month"] = HTML_SELECTED;
		$dayselected["$day"] = HTML_SELECTED;

	    if ($year == 0)
	    {
	        $year = '';
    	}
	}
	else
	{
		$show['birthday'] = false;
	}

	if ($vboptions['allowhtml'])
	{
		$htmlonoff = $vbphrase['on'];
	}
	else
	{
		$htmlonoff = $vbphrase['off'];
	}
	if ($vboptions['allowbbcode'])
	{
		$bbcodeonoff = $vbphrase['on'];
	}
	else
	{
		$bbcodeonoff = $vbphrase['off'];
	}
	if ($vboptions['allowbbimagecode'])
	{
		$imgcodeonoff = $vbphrase['on'];
	}
	else
	{
		$imgcodeonoff = $vbphrase['off'];
	}
	if ($vboptions['allowsmilies'])
	{
		$smiliesonoff = $vbphrase['on'];
	}
	else
	{
		$smiliesonoff = $vbphrase['off'];
	}

	// image verification
	if ($vboptions['regimagecheck'] AND $vboptions['gdversion'])
	{
		// Transfer a successful image match over when other errors have occurred
		if ($errorlist AND $_POST['imagehash'])
		{
			$imagestamp = htmlspecialchars_uni($_POST['imagestamp']);
			$imagehash = htmlspecialchars_uni($_POST['imagehash']);
		}
		else
		{
			$string = fetch_registration_string(6);

			$imagehash = md5(uniqid(rand(), 1));

			// Gen hash and insert into database;
			$DB_site->query("INSERT INTO " . TABLE_PREFIX . "regimage (regimagehash, imagestamp, dateline) VALUES ('" . addslashes($imagehash) . "', '" . addslashes($string) . "', " . TIMENOW . ")");
		}
	}

	// Referrer
	if ($vboptions['usereferrer'] AND $bbuserinfo['userid'] == 0)
	{
		exec_switch_bg();
		if ($errorlist)
		{
			$referrername = htmlspecialchars_uni($_POST['referrername']);
		}
		else if ($_COOKIE[COOKIE_PREFIX . 'referrerid'])
		{
			if ($referrername = $DB_site->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = ".intval($_COOKIE[COOKIE_PREFIX . 'referrerid'])))
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
	$who = trim($_REQUEST['who']);
	if ($who != 'adult')
	{
		$bgclass1 = 'alt1';
	}

	$customfields_other = '';
	$customfields_profile = '';
	$customfields_option = '';

	$profilefields = $DB_site->query("
		SELECT *
		FROM " . TABLE_PREFIX . "profilefield
		WHERE editable > 0 AND required <> 0
		ORDER BY displayorder
	");
	while ($profilefield = $DB_site->fetch_array($profilefields))
	{
		$profilefieldname = "field$profilefield[profilefieldid]";
		$optionalname = $profilefieldname . '_opt';
		$optionalfield = '';
		$optional = '';
		if (!$errorlist)
		{
			unset($bbuserinfo["$profilefieldname"]);
		}

		if ($profilefield['required'] == 2)
		{
			// not required to be filled in but still show
			$profile_variable = &$customfields_other;
		}
		else // required to be filled in
		{
			if ($profilefield['form'])
			{
				$profile_variable = &$customfields_option;
			}
			else
			{
				$profile_variable = &$customfields_profile;
			}
		}

		if ($profilefield['type'] == 'input')
		{
			if ($profilefield['data'])
			{
				$bbuserinfo["$profilefieldname"] = $profilefield['data'];
			}
			else
			{
				$bbuserinfo["$profilefieldname"] = htmlspecialchars_uni($bbuserinfo["$profilefieldname"]);
			}
			eval('$profile_variable .= "' . fetch_template('userfield_textbox') . '";');
		}
		else if ($profilefield['type'] == 'textarea')
		{
			if ($profilefield['data'])
			{
				$bbuserinfo["$profilefieldname"] = $profilefield['data'];
			}
			else
			{
				$bbuserinfo["$profilefieldname"] = htmlspecialchars_uni($bbuserinfo["$profilefieldname"]);
			}
			eval('$profile_variable .= "' . fetch_template('userfield_textarea') . '";');
		}
		else if ($profilefield['type'] == 'select')
		{
			$data = unserialize($profilefield['data']);
			$selectbits = '';
			foreach ($data AS $key => $val)
			{
				$key++;
				$selected = '';
				if ($bbuserinfo["$profilefieldname"])
				{
					if (trim($val) == $bbuserinfo["$profilefieldname"])
					{
						$selected = HTML_SELECTED;
						$foundselect = 1;
					}
				}
				else if ($profilefield['def'] AND $key == 1)
				{
					$selected = HTML_SELECTED;
					$foundselect = 1;
				}

				eval('$selectbits .= "' . fetch_template('userfield_select_option') . '";');
			}

			if ($profilefield['optional'])
			{
				if (!$foundselect AND $bbuserinfo["$profilefieldname"])
				{
					$optional = $bbuserinfo["$profilefieldname"];
				}
				eval('$optionalfield = "' . fetch_template('userfield_optional_input') . '";');
			}
			if (!$foundselect)
			{
				$selected = HTML_SELECTED;
			}
			else
			{
				$selected = '';
			}
			$show['noemptyoption'] = iif($profilefield['def'] != 2, true, false);
			eval('$profile_variable .= "' . fetch_template('userfield_select') . '";');
		}
		else if ($profilefield['type'] == 'radio')
		{
			$data = unserialize($profilefield['data']);
			$radiobits = '';
			$foundfield = 0;

			foreach ($data AS $key => $val)
			{
				$key++;
				$checked = '';
				if (!$bbuserinfo["$profilefieldname"] AND $key == 1 AND $profilefield['def'] == 1)
				{
					$checked = HTML_CHECKED;
				}
				else if (trim($val) == $bbuserinfo["$profilefieldname"])
				{
					$checked = HTML_CHECKED;
					$foundfield = 1;
				}
				eval('$radiobits .= "' . fetch_template('userfield_radio_option') . '";');
			}
			if ($profilefield['optional'])
			{
				if (!$foundfield AND $bbuserinfo["$profilefieldname"])
				{
					$optional = $bbuserinfo["$profilefieldname"];
				}
				eval('$optionalfield = "' . fetch_template('userfield_optional_input') . '";');
			}
			eval('$profile_variable .= "' . fetch_template('userfield_radio') . '";');
		}
		else if ($profilefield['type'] == 'checkbox')
		{
			$data = unserialize($profilefield['data']);
			$radiobits = '';
			$perline = 0;
			foreach ($data AS $key => $val)
			{
				if ($bbuserinfo["$profilefieldname"] & pow(2,$key))
				{
					$checked = HTML_CHECKED;
				}
				else
				{
					$checked = '';
				}
				$key++;
				eval('$radiobits .= "' . fetch_template('userfield_checkbox_option') . '";');
				$perline++;
				if ($profilefield['def'] > 0 AND $perline >= $profilefield['def'])
				{
					$radiobits .= '<br />';
					$perline = 0;
				}
			}
			eval('$profile_variable .= "' . fetch_template('userfield_radio') . '";');
		}
		else if ($profilefield['type'] == 'select_multiple')
		{
			$data = unserialize($profilefield['data']);
			$selectbits = '';
			$selected = '';
			foreach ($data AS $key => $val)
			{
				if ($bbuserinfo["$profilefieldname"] & pow(2, $key))
				{
					$selected = HTML_SELECTED;
				}
				else
				{
					$selected = '';
				}
				$key++;
				eval('$selectbits .= "' . fetch_template('userfield_select_option') . '";');
			}
			eval('$profile_variable .= "' . fetch_template('userfield_select_multiple') . '";');
		}
	}

	if (!$_POST['who'])
	{
		$who = iif($_POST['coppauser'], 'coppa', 'adult');
	}

	$show['coppa'] = $usecoppa = iif($who == 'adult' OR !$vboptions['usecoppa'], false, true);
	$show['customfields_profile'] = iif($customfields_profile OR $show['birthday'], true, false);
	$show['customfields_option'] = iif($customfields_option, true, false);
	$show['customfields_other'] = iif($customfields_other, true, false);

	foreach (fetch_timezone() AS $optionvalue => $timezonephrase)
	{
		$optiontitle = $vbphrase["$timezonephrase"];
		$optionselected = iif($optionvalue == $vboptions['timeoffset'], HTML_SELECTED, '');
		eval('$timezoneoptions .= "' . fetch_template('option') . '";');
	}
	eval('$timezoneoptions = "' . fetch_template('modifyoptions_timezone') . '";');

	eval('print_output("' . fetch_template('register') . '");');
}

// ############################### start activate form ###############################
if ($_REQUEST['a'] == 'ver')
{
	// get username and password
	if ($bbuserinfo['userid'] == 0)
	{
		$bbuserinfo['username'] = '';
	}

	$navbits = construct_navbits(array('' => $vbphrase['activate_your_account']));

	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('activateform') . '");');
}

// ############################### start activate ###############################
if ($_REQUEST['do'] == 'activate')
{
	if ($userinfo = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username='" . addslashes(htmlspecialchars_uni($_REQUEST['username'])) . "'"))
	{

		$_REQUEST['u'] = $userinfo['userid'];
		$_REQUEST['a'] = 'act';
		$_REQUEST['i'] = $_REQUEST['activateid'];
	}
	else
	{
		eval(print_standard_error('error_badlogin'));
	}
}

if ($_REQUEST['a'] == 'act')
{


	// do activate account
	$u = intval($_REQUEST['u']);
	$i = intval($_REQUEST['i']);

	$userinfo = verify_id('user', $u, 1, 1);

	if ($userinfo['usergroupid'] == 3)
	{

		// check valid activation id
		$user = $DB_site->query_first("
			SELECT activationid, usergroupid
			FROM " . TABLE_PREFIX . "useractivation
			WHERE activationid=$i AND userid=$userinfo[userid] AND type=0
		");
		if (!$user OR $_REQUEST['i'] != $user['activationid'])
		{
			// send email again
			eval(print_standard_error('error_invalidactivateid'));
		}

		// delete activationid
		//$DB_site->query("DELETE FROM " . TABLE_PREFIX . "useractivation WHERE userid=$userinfo[userid] AND type=0");

		if ($userinfo['coppauser'] OR ($vboptions['moderatenewmembers'] AND !$userinfo['posts']))
		{
			// put user in moderated group
			$user['usergroupid'] = 4;
		}
		if (empty($user['usergroupid']))
		{
			$user['usergroupid'] = 2; // sanity check
		}

		// ### UPDATE USER TITLE ###
		$dotitle = '';

		$getusergroupid = iif($userinfo['displaygroupid'] != $userinfo['usergroupid'], $userinfo['displaygroupid'], $user['usergroupid']);
		$usergroup = $usergroupcache["$getusergroupid"];

		if (!$userinfo['customtitle'])
		{
			if (!$usergroup['usertitle'])
			{
				$gettitle = $DB_site->query_first("
					SELECT title
					FROM " . TABLE_PREFIX . "usertitle
					WHERE minposts <= " . intval($userinfo['posts']) . "
					ORDER BY minposts DESC
				");
				$usertitle = $gettitle['title'];
			}
			else
			{
				$usertitle = $usergroup['usertitle'];
			}
			$dotitle = ', usertitle = \'' . addslashes($usertitle) . '\'';
		}

		// ### DO THE UG/TITLE UPDATE ###
		$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET usergroupid=$user[usergroupid] $dotitle WHERE userid=$u");

		if ($userinfo['coppauser'] OR ($vboptions['moderatenewmembers'] AND !$userinfo['posts']))
		{
			// put user in moderated group
			$username = $userinfo['username'];
			eval(print_standard_error('error_moderateuser'));
		}
		else
		{
			// activate account
			$username = unhtmlspecialchars($userinfo['username']);
			if ($vboptions['welcomemail'] AND !$userinfo['posts'])
			{
				eval(fetch_email_phrases('welcomemail'));
				vbmail($userinfo['email'], $subject, $message);
			}

			$username = $userinfo['username'];
			eval(print_standard_error('registration_complete'));
		}
	}
	else
	{
		if ($userinfo['usergroupid'] == 4)
		{
			// In Moderation Queue
			eval(print_standard_error('activate_moderation'));
		}
		else
		{
			// Already activated
			eval(print_standard_error('activate_wrongusergroup'));
		}
	}

}

// ############################### start request activation email ###############################
if ($_REQUEST['do'] == 'requestemail')
{
	globalize($_REQUEST, array('email'));

	if ($email)
	{
		$email = htmlspecialchars_uni($email);
	}
	else if ($bbuserinfo['userid'])
	{
		$email = $bbuserinfo['email'];
	}
	else
	{
		$email = '';
	}

	$navbits = construct_navbits(array(
		"register.php?$session[sessionurl]a=ver" => $vbphrase['activate_your_account'],
		'' => $vbphrase['email_activation_codes']
	));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('activate_requestemail') . '");');
}

if ($_POST['do'] == 'emailcode')
{
	$users = $DB_site->query("SELECT user.userid, user.usergroupid, username, email, activationid, languageid FROM " . TABLE_PREFIX . "user AS user LEFT JOIN " . TABLE_PREFIX . "useractivation AS useractivation ON(user.userid = useractivation.userid AND type = 0) WHERE email = '" . addslashes(htmlspecialchars_uni($_REQUEST['email'])) . "'");

	if ($DB_site->num_rows($users))
	{
		while ($user = $DB_site->fetch_array($users))
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
					$user['activationid'] = vbrand(0,100000000);
					$DB_site->query("UPDATE " . TABLE_PREFIX . "useractivation SET dateline=" . TIMENOW . ",activationid=$user[activationid] WHERE userid=$user[userid] AND type=0");
				}

				$userid = $user['userid'];
				$username = $user['username'];
				$activateid = $user['activationid'];

				eval(fetch_email_phrases('activateaccount', $user['languageid']));

				vbmail($user['email'], $subject, $message, true);
			}
		}

		$url = "$vboptions[forumhome].php?$session[sessionurl]";
		$_REQUEST['forceredirect'] = 1;

		eval(print_standard_redirect('redirect_lostactivatecode'));
	}
	else
	{
		eval(print_standard_error('error_invalidemail'));
	}

}

// ############################### start coppa form ###############################
if ($_REQUEST['do'] == 'coppaform')
{
	if ($bbuserinfo['userid'] != 0)
	{
		$bbuserinfo['signature'] = nl2br($bbuserinfo['signature']);

		if ($bbuserinfo['showemail'])
		{
			$bbuserinfo['showemail'] = $vbphrase['no'];
		}
		else
		{
			$bbuserinfo['showemail'] = $vbphrase['yes'];
		}
	}
	else
	{
		$bbuserinfo['username'] = '';
		$bbuserinfo['homepage'] = 'http://';
	}

	eval('print_output("' . fetch_template('register_coppaform') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13281 $
|| ####################################################################
\*======================================================================*/
?>