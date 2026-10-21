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

// ###################### Start makecpnav #######################
// quick method of building the cpnav template
function construct_usercp_nav($selectedcell = 'usercp', $option = 0)
{
	global $navclass, $cpnav, $session, $gobutton, $stylevar, $vbphrase;
	global $vboptions, $bbuserinfo, $messagecounters, $subscribecounters, $DB_site;
	global $show, $usergroupcache, $subscriptioncache;

	$cells = array(
		'usercp',

		'signature',
		'profile',
		'options',
		'password',
		'avatar',
		'profilepic',

		'pm_messagelist',
		'pm_newpm',
		'pm_trackpm',
		'pm_editfolders',

		'substhreads_listthreads',
		'substhreads_editfolders',

		'event_reminders',
		'paid_subscriptions',
		'usergroups',
		'buddylist',
		'attachments'
	);

	if (!$vboptions['subscriptionmethods'])
	{
		$show['paidsubscriptions'] = false;
	}
	else
	{
		// cache all the subscriptions - should move this to a datastore object at some point
		require_once('./includes/functions_subscriptions.php');
		cache_user_subscriptions();

		$show['paidsubscriptions'] = iif(empty($subscriptioncache), false, true);
	}

	// check to see if there are usergroups available
	$show['publicgroups'] = false;
	foreach ($usergroupcache AS $usergroup)
	{
		if ($usergroup['ispublicgroup'] OR ($usergroup['canoverride'] AND is_member_of($bbuserinfo, $usergroup['usergroupid'])))
		{
			$show['publicgroups'] = true;
			break;
		}
	}

	// set the class for each cell/group
	$navclass = array();
	foreach ($cells AS $cellname)
	{
		$navclass["$cellname"] = 'alt2';
	}
	$navclass["$selectedcell"] = 'alt1';

	// variable to hold templates for pm / subs folders
	$cpnav = array();

	// get PM folders
	$cpnav['pmfolders'] = '';
	$pmfolders = array('0' => $vbphrase['inbox'], '-1' => $vbphrase['sent_items']);
	if (!empty($bbuserinfo['pmfolders']))
	{
		$pmfolders = $pmfolders + unserialize($bbuserinfo['pmfolders']);
	}
	foreach ($pmfolders AS $folderid => $foldername)
	{
		$linkurl = "private.php?$session[sessionurl]folderid=$folderid";
		eval('$cpnav[\'pmfolders\'] .= "' . fetch_template('usercp_nav_folderbit') . '";');
	}

	// get subscriptions folders
	$cpnav['subsfolders'] = '';
	$subsfolders = unserialize($bbuserinfo['subfolders']);
	if (!empty($subsfolders))
	{
		foreach ($subsfolders AS $folderid => $foldername)
		{
			$linkurl = "subscription.php?$session[sessionurl]folderid=$folderid";
			eval('$cpnav[\'subsfolders\'] .= "' . fetch_template('usercp_nav_folderbit') . '";');
		}
	}
	if ($cpnav['subsfolders'] == '')
	{
		$linkurl = "subscription.php?$session[sessionurl]folderid=0";
		$foldername = $vbphrase['subscriptions'];
		eval('$cpnav[\'subsfolders\'] .= "' . fetch_template('usercp_nav_folderbit') . '";');
	}
}

// ###################### Start getavatarurl #######################
function fetch_avatar_url($userid)
{
	global $DB_site, $session, $vboptions;

	if ($avatarinfo = $DB_site->query_first("
		SELECT user.avatarid, user.avatarrevision, avatarpath, NOT ISNULL(avatardata) AS hascustom, customavatar.dateline
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON avatar.avatarid = user.avatarid
		LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON customavatar.userid = user.userid
		WHERE user.userid = $userid"))
	{
		if (!empty($avatarinfo['avatarpath']))
		{
			return $avatarinfo['avatarpath'];
		}
		else if ($avatarinfo['hascustom'])
		{
			if ($vboptions['usefileavatar'])
			{
				return "$vboptions[avatarurl]/avatar{$userid}_{$avatarinfo[avatarrevision]}.gif";
			}
			else
			{
				return "image.php?u=$userid&amp;dateline=$avatarinfo[dateline]";
			}
		}
		else
		{
			return '';
		}
	}
}

// ###################### Start makesalt #######################
// generates a totally random string of $length chars
function fetch_user_salt($length = 3)
{
	$salt = '';
	for ($i = 0; $i < $length; $i++)
	{
		$salt .= chr(rand(32, 126));
	}
	return $salt;
}

// ###################### Start verifyprofilefields #######################
function verify_profilefields($formtype = 0)
{
	global $DB_site, $_POST, $vboptions;

	// check extra profile fields
	$profilefields = $DB_site->query("
		SELECT profilefieldid,required,title,size,maxlength,type,data,optional, regex
		FROM " . TABLE_PREFIX . "profilefield
		WHERE editable = 1
			AND form " . iif($formtype, '>= 1', '= 0'). "
	");
	while ($profilefield = $DB_site->fetch_array($profilefields))
	{
		//globalize($_POST, array("field$profilefield[profilefieldid]", "field$profilefield[profilefieldid]" . '_opt'));
		$varname = 'field' . $profilefield['profilefieldid'];
		$value = $_POST["$varname"];
		$optionalvar = 'field' . $profilefield['profilefieldid'] . '_opt';
		$value_opt = $_POST["$optionalvar"];
		$bitwise = 0;
		if ($profilefield['type'] == 'input' OR $profilefield['type'] == 'textarea')
		{
			$value = substr(fetch_censored_text($value), 0, $profilefield['maxlength']);
		}
		else if ($profilefield['type'] == 'radio' OR $profilefield['type'] == 'select')
		{
			if ($profilefield['optional'] AND $value_opt)
			{
				$value = substr(fetch_censored_text($value_opt), 0, $profilefield['maxlength']);
			}
			else
			{
				$data = unserialize($profilefield['data']);
				$value -= 1;
				if (!empty($data["$value"]))
				{
					$value = unhtmlspecialchars(trim($data["$value"]));	
				}
				else
				{
					$value = '';	
				}
			}
		}
		else if (($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple') AND is_array($value))
		{
			if (($profilefield['size'] == 0) OR (sizeof($value) <= $profilefield['size']))
			{
				foreach($value AS $key => $val)
				{
					$bitwise += pow(2, $val - 1);
				}
				$value = $bitwise;
			}
			else
			{
				eval(print_standard_error('error_checkboxsize'));
			}
		}
		if ($profilefield['regex'])
		{
			if (!preg_match('#' . str_replace('#', '\#', $profilefield['regex']) . '#siU', $value))
			{
				if ($value != '')
				{
					eval(print_standard_error('error_regexincorrect'));
					$value = '';
				}

			}
		}

		if ($profilefield['required'] == 1 AND empty($value))
		{
			eval(print_standard_error('error_requiredfieldmissing'));
		}

		$userfields .= ", $varname = '" . addslashes(htmlspecialchars_uni($value)) . "'";
	}

	return $userfields;

}

// ###################### Start getprofilefields #######################
function fetch_profilefields($formtype = 0) // 0 indicates a profile field, 1 indicates an option field
{

	global $DB_site, $vboptions, $stylevar, $customfields, $bgclass;
	global $vbphrase, $altbgclass, $bgclass1, $tempclass, $bbuserinfo;

	// get extra profile fields
	$profilefields = $DB_site->query("
		SELECT * FROM " . TABLE_PREFIX . "profilefield
		WHERE editable = 1
			AND form " . iif($formtype, '>= 1', '= 0'). "
		ORDER BY displayorder
	");
	while ($profilefield=$DB_site->fetch_array($profilefields))
	{
		if ($formtype == 1 AND in_array($profilefield['type'], array('select', 'select_multiple')))
		{
			$show['optionspage'] = true;
		}
		else
		{
			$show['optionspage'] = false;
		}
		$profilefieldname="field$profilefield[profilefieldid]";
		$optionalname = $profilefieldname . '_opt';
		$optional = '';
		$optionalfield = '';

		if ($profilefield['required'] == 1 AND $profilefield['form'] == 0) // Ignore the required setting for fields on the options page
		{
			exec_switch_bg(1);
		}
		else
		{
			exec_switch_bg($profilefield['form']);
		}

		if ($profilefield['type'] == 'input')
		{
			eval('$tempcustom = "' . fetch_template('userfield_textbox') . '";');
		}
		else if ($profilefield['type'] == 'textarea')
		{
			eval('$tempcustom = "' . fetch_template('userfield_textarea') . '";');
		}
		else if ($profilefield['type'] == 'select')
		{
			$data = unserialize($profilefield['data']);
			$selectbits = '';
			$foundselect = 0;
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
				else if ($profilefield['def'] && $key == 1)
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
			eval('$tempcustom = "' . fetch_template('userfield_select') . '";');
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
			eval('$tempcustom = "' . fetch_template('userfield_radio') . '";');
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
			eval('$tempcustom = "' . fetch_template('userfield_radio') . '";');
		}
		else if ($profilefield['type'] == 'select_multiple')
		{
			$data = unserialize($profilefield['data']);
			$selectbits = '';
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
			eval('$tempcustom = "' . fetch_template('userfield_select_multiple') . '";');
		}

		if ($profilefield['required'] == 1 AND $profilefield['form'] == 0) // Ignore the required setting for fields on the options page
		{
			$customfields['required'] .= $tempcustom;
		}
		else
		{
			if ($profilefield['form'] == 0)
			{
				$customfields['regular'] .= $tempcustom;
			}
			else // not implemented
			{
				switch ($profilefield['form'])
				{
					case 1:
						$customfields['login'] .= $tempcustom;
						break;
					case 2:
						$customfields['messaging'] .= $tempcustom;
						break;
					case 3:
						$customfields['threadview'] .= $tempcustom;
						break;
					case 4:
						$customfields['datetime'] .= $tempcustom;
						break;
					case 5:
						$customfields['other'] .= $tempcustom;
						break;
					default:
				}
			}
		}


	}
}

// ###################### Start checkbannedemail #######################
function is_banned_email($email)
{
	global $vboptions, $datastore;

	if ($vboptions['enablebanning'] AND !empty($datastore['banemail']))
	{
		$bannedemails = preg_split('/\s+/', $datastore['banemail'], -1, PREG_SPLIT_NO_EMPTY);

		foreach ($bannedemails AS $bannedemail)
		{
			if (is_valid_email($bannedemail))
			{
				$regex = '^' . preg_quote($bannedemail, '#') . '$';
			}
			else
			{
				$regex = preg_quote($bannedemail, '#');
			}

			if (preg_match("#$regex#i", $email))
			{
				return 1;
			}
		}
	}

	return 0;
}

// ###################### Start useractivation #######################
function build_user_activation_id($userid, $usergroupid, $type)
{
	global $DB_site;

	if ($usergroupid == 3 OR $usergroupid == 0)
	{ // stop them getting stuck in email confirmation group forever :)
		$usergroupid = 2;
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "useractivation WHERE userid = $userid AND type = $type");
	$activateid = vbrand(0,100000000);
	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "useractivation
			(userid, dateline, activationid, type, usergroupid)
		VALUES
			($userid, " . TIMENOW . ", $activateid , $type, $usergroupid)
	");

	return $activateid;
}

// ###################### Start regstring #######################
function fetch_registration_string($length)
{
	$chars = '2346789ABCDEFGHJKLMNPRTWXYZ';
	// . 'abcdefghjkmnpqrstwxyz'; easier to read with all uppercase

	for ($x = 1; $x <= $length; $x++)
	{
		$number = rand(1, strlen($chars));
		$word .= substr($chars, $number - 1, 1);
 	}

 	return $word;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12082 $
|| ####################################################################
\*======================================================================*/
?>