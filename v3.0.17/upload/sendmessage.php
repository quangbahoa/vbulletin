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

// ######################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'sendmessage');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('messaging');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'mailform',
	'sendtofriend',
	'contactus',
	'contactus_option',
	'newpost_errormessage',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'im' => array(
		'im_send_aim',
		'im_send_icq',
		'im_send_yahoo',
		'im_send_msn',
		'im_message'
	),
	'sendtofriend' => array(
		'newpost_usernamecode'
	)
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'contactus';
}

// ############################### start im message ###############################
if ($_REQUEST['do'] == 'im')
{
	globalize ($_REQUEST, array('type', 'userid' => INT));

	// verify userid
	$userinfo = verify_id('user', $userid, 1, 1, 15);

	switch ($type)
	{
		case 'aim':
		case 'yahoo':
			$userinfo["{$type}_link"] = urlencode($userinfo["$type"]);
			break;
		case 'icq':
			$userinfo['icq'] = trim(htmlspecialchars_uni($userinfo['icq']));
			break;
		default:
			$type = 'msn';
			break;
	}

	if (empty($userinfo["$type"]))
	{
		// user does not have this messaging meduim defined
		eval(print_standard_error('error_immethodnotdefined'));
	}

	// shouldn't be a problem hard-coding this text, as they are all commercial names
	$typetext = array(
		'msn'   => 'MSN',
		'icq'   => 'ICQ',
		'aim'   => 'AIM',
		'yahoo' => 'Yahoo!'
	);

	$typetext = $typetext["$type"];

	eval('$imtext = "' . fetch_template('im_send_' . $type) . '";');

	eval('print_output("' . fetch_template('im_message') . '");');

}

// ##################################################################################
// ALL other actions from here onward require email permissions, so check that now...
// *** email permissions ***
if (!$vboptions['enableemail'])
{
	eval(print_standard_error('error_emaildisabled'));
}

if (!can_moderate() AND $vboptions['emailfloodtime'] AND (TIMENOW - $vboptions['emailfloodtime']) <= $bbuserinfo['emailstamp'] AND $bbuserinfo['userid'])
{
	$timediff = $bbuserinfo['emailstamp'] + $vboptions['emailfloodtime'] - TIMENOW;
	eval(print_standard_error('error_emailfloodcheck'));
}

// initialize errors array
$errors = array();

// ############################### do contact webmaster ###############################
if ($_POST['do'] == 'docontactus')
{
	if (!$bbuserinfo['userid'] AND !$vboptions['contactustype'])
	{
		print_no_permission();
	}

	globalize($_POST, array(
		'name' => STR,
		'email' => STR,
		'subject' => STR,
		'message' => STR,
		'url' => STR,
		'other_subject' => STR,
		'imagestamp' => STR,
		'imagehash' => STR,
	));

	// check we have a message and a subject
	if ($message == '' OR (!isset($subject) AND $subject == '') OR ($vboptions['contactusoptions'] AND $subject == 'other' AND $other_subject == ''))
	{
		eval('$errors[] = "' . fetch_phrase('nosubject', PHRASETYPEID_ERROR) . '";');
	}

	// check for valid email address
	if (!is_valid_email($email))
	{
		eval('$errors[] = "' . fetch_phrase('bademail', PHRASETYPEID_ERROR) . '";');
	}

	if (!$bbuserinfo['userid'] AND $vboptions['contactustype'] == 2 AND $vboptions['gdversion'])
	{
		$imagestamp = str_replace(' ', '', $imagestamp);

		$ih = $DB_site->query_first("
			SELECT imagestamp
			FROM " . TABLE_PREFIX . "regimage
			WHERE regimagehash = '" . addslashes($imagehash) . "'
		");

		if (!$imagestamp OR strtoupper($imagestamp) != $ih['imagestamp'])
		{
			eval('$errors[] = "' . fetch_phrase('register_imagecheck', PHRASETYPEID_ERROR) . '";');
	  		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "regimage WHERE regimagehash = '" . addslashes($imagehash) . "'");
	  		unset($imagestamp);
	  		unset($imagehash);
		}
	}

	// if it's all good... send the email
	if (empty($errors))
	{
		if ($imagehash)
		{
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "regimage WHERE regimagehash = '" . addslashes($imagehash) . "'");
		}

		if ($vboptions['contactusoptions'])
		{
			if ($subject == 'other')
			{
				$subject = $other_subject;
			}
			else
			{
				$options = explode("\n", trim($vboptions['contactusoptions']));
				foreach($options AS $index => $title)
				{
					if ($index == $subject)
					{
						$subject = $title;
						break;
					}
				}
			}
		}
		$ip = IPADDRESS;
		eval(fetch_email_phrases('contactus', 0));

		vbmail($vboptions['webmasteremail'], $subject, $message, false, $email, '', $name);

		$_REQUEST['forceredirect'] = true;

		eval(print_standard_redirect('redirect_sentfeedback'));
	}
	// there are errors!
	else
	{
		$show['errors'] = true;
		foreach ($errors AS $errormessage)
		{
			eval('$errormessages .= "' . fetch_template('newpost_errormessage') . '";');
		}

		$_REQUEST['do'] = 'contactus';
	}

}

// ############################### start contact webmaster ###############################
if ($_REQUEST['do'] == 'contactus')
{
	if (!$bbuserinfo['userid'] AND !$vboptions['contactustype'])
	{
		print_no_permission();
	}

	globalize($_REQUEST, array(
		'name' => STR_NOHTML,
		'email' => STR_NOHTML,
		'subject' => INT,
		'message' => STR_NOHTML,
		'url' => STR_NOHTML,
	));

	// enter $bbuserinfo's name and email if necessary
	if ($name == '' AND $bbuserinfo['userid'] > 0)
	{
		$name = $bbuserinfo['username'];
	}
	if ($email == '' AND $bbuserinfo['userid'] > 0)
	{
		$email = $bbuserinfo['email'];
	}

	if ($vboptions['contactusoptions'])
	{
		$options = explode("\n", trim($vboptions['contactusoptions']));
		foreach($options AS $index => $title)
		{
			if ($subject == $index)
			{
				$checked = HTML_CHECKED;
			}
			eval('$contactusoptions .= "' . fetch_template('contactus_option') . '";');
			unset($checked);
		}
	}

	if (!$bbuserinfo['userid'] AND $vboptions['contactustype'] == 2 AND $vboptions['gdversion'])
	{
		if (!$imagehash OR empty($errors))
		{
			require_once('./includes/functions_user.php');
			$string = fetch_registration_string(6);
			$imagehash = md5(uniqid(rand(), 1));
			// Gen hash and insert into database;
			/*insert query*/
			$DB_site->query("INSERT INTO " . TABLE_PREFIX . "regimage (regimagehash, imagestamp, dateline) VALUES ('" . addslashes($imagehash) . "', '" . addslashes($string) . "', " . TIMENOW . ")");
		}
		$show['imagecheck'] = true;
	}

	// generate navbar
	$navbits = construct_navbits(array('' => $vbphrase['contact_us']));
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('contactus') . '");');
}

// ############################### start send to friend permissions ###############################
if ($_REQUEST['do'] == 'sendtofriend' OR $_POST['do'] == 'dosendtofriend')
{
	// set in global.php
	//	$threadid = verify_id('thread', $threadid);
	//	$threadinfo = fetch_threadinfo($threadid);
	//	$foruminfo = fetch_foruminfo($threadinfo['forumid']);

	$forumperms = fetch_permissions($threadinfo['forumid']);

	if (!($forumperms & CANVIEW) OR !($forumperms & CANEMAIL) OR (($threadinfo['postuserid'] != $bbuserinfo['userid']) AND !($forumperms & CANVIEWOTHERS)))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

}

// ############################### start send to friend ###############################
if ($_REQUEST['do'] == 'sendtofriend')
{

	if ($vboptions['wordwrap'] != 0)
	{
		$threadinfo['title'] = fetch_word_wrapped_string($threadinfo['title']);
	}

	$currentpage = urlencode("sendmessage.php?do=sendtofriend&t=$threadinfo[threadid]");
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// draw nav bar
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = &$forumcache["$forumID"]['title'];
		$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
	}
	$navbits["showthread.php?$session[sessionurl]t=$threadid"] = $threadinfo['title'];
	$navbits[''] = $vbphrase['email_to_friend'];

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('sendtofriend') . '");');

}

// ############################### start do send to friend ###############################
if ($_POST['do'] == 'dosendtofriend')
{
	globalize($_POST , array('sendtoname', 'sendtoemail', 'emailsubject', 'emailmessage', 'username', 'password'));

	if (empty($sendtoname) OR !is_valid_email($sendtoemail) OR empty($emailsubject) OR empty($emailmessage))
	{
		eval(print_standard_error('error_requiredfields'));
	}

	if (isset($username))
	{
		if (!trim($username))
		{
			eval(print_standard_error('error_nousername'));
		}
		if ($userinfo = $DB_site->query_first("SELECT user.*, userfield.* FROM " . TABLE_PREFIX . "user AS user," . TABLE_PREFIX . "userfield AS userfield WHERE username='" . addslashes(htmlspecialchars_uni($username)) . "' AND user.userid = userfield.userid"))
		{
			eval(print_standard_error('error_usernametaken'));
		}
		else
		{
			$postusername = htmlspecialchars_uni($username);
		}
	}
	else
	{
		$postusername = $bbuserinfo['username'];
	}

	eval(fetch_email_phrases('sendtofriend'));
	vbmail($sendtoemail, $emailsubject, $message);
	$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET emailstamp = " . TIMENOW . " WHERE userid=$bbuserinfo[userid]");

	$sendtoname = htmlspecialchars_uni($sendtoname);
	eval(print_standard_redirect('redirect_sentemail'));

}

// ############################### start mail member permissions ###############################
if ($_REQUEST['do'] == 'mailmember' OR $_POST['do'] == 'domailmember')
{
	globalize($_REQUEST, array('userid' => INT));

	//don't let people awaiting email confirmation use it either as their email may be fake
	if (!$bbuserinfo['userid'] OR $bbuserinfo['usergroupid'] == 3 OR $bbuserinfo['usergroupid'] == 4)
	{
		print_no_permission();
	}

	// check that the requested user actually exists
	if (!$destuserinfo = $DB_site->query_first("SELECT userid, username, usergroupid, email, (options & $_USEROPTIONS[showemail]) AS showemail, languageid FROM " . TABLE_PREFIX . "user WHERE userid = $userid"))
	{
		$idname = $vbphrase['user'];
		eval(print_standard_error('error_invalidid'));
	}
	else if ($destuserinfo['usergroupid'] == 3 OR $destuserinfo['usergroupid'] == 4)
	{ // user hasn't confirmed email address yet or is COPPA
		eval(print_standard_error('error_usernoemail'));
	}

}

// ############################### start mail member ###############################
if ($_REQUEST['do'] == 'mailmember')
{

	if ($vboptions['displayemails'] AND $destuserinfo['showemail'])
	{
		$destusername = $destuserinfo['username'];
		if ($vboptions['secureemail']) // use secure email form or not?
		{
			// generate navbar
			$navbits = construct_navbits(array('' => $vbphrase['email']));
			eval('$navbar = "' . fetch_template('navbar') . '";');

			eval('print_output("' . fetch_template('mailform') . '");');
		}
		else
		{
			// show the user's email address
			$destusername = $destuserinfo['username'];
			$email = htmlspecialchars_uni($destuserinfo['email']);

			eval(print_standard_error('error_showemail'));
		}
	}
	else
	{
		// user or admin has disabled sending of emails
		eval(print_standard_error('error_usernoemail'));
	}
}

// ############################### start do mail member ###############################
if ($_POST['do'] == 'domailmember')
{
	globalize($_POST, array('message', 'emailsubject'));

	$destuserid = $destuserinfo['userid'];

	if (!$vboptions['displayemails'] OR !$destuserinfo['showemail'] OR !$vboptions['enableemail'])
	{
		eval(print_standard_error('error_usernoemail'));
	}

	$message = trim($message);
	if (!$message)
	{
		eval(print_standard_error('error_nomessage'));
	}

	eval(fetch_email_phrases('usermessage', $destuserinfo['languageid']));

	vbmail($destuserinfo['email'], $emailsubject, $message, false, $bbuserinfo['email'], '', $bbuserinfo['username']);
	$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET emailstamp = " . TIMENOW . " WHERE userid=$bbuserinfo[userid]");

	// parse this next line with eval:
	$sendtoname = $destuserinfo['username'];

	eval(print_standard_redirect('redirect_sentemail'));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14302 $
|| ####################################################################
\*======================================================================*/
?>