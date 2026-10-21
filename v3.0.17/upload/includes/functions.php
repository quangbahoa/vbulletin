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

// predefine the phrasetypeids of a few phrase groups
define('PHRASETYPEID_HOLIDAY',		35);
define('PHRASETYPEID_ERROR',		1000);
define('PHRASETYPEID_REDIRECT',		2000);
define('PHRASETYPEID_MAILMSG',		3000);
define('PHRASETYPEID_MAILSUB',		4000);
define('PHRASETYPEID_SETTING',		5000);
define('PHRASETYPEID_ADMINHELP',	6000);
define('PHRASETYPEID_FAQTITLE',		7000);
define('PHRASETYPEID_FAQTEXT',		8000);
define('PHRASETYPEID_CPMESSAGE',	9000);

// this defines what adds the indent to forums in the forumjump menu
define('FORUM_PREPEND', '&nbsp; &nbsp; ');

// ###################### Start construct phrase #######################
// this function is actually just a wrapper for sprintf
// but makes identification of phrase code easier
// and will not error if there are no additional arguments
function construct_phrase()
{
	static $argpad;

	$args = func_get_args();
	$numargs = sizeof($args);

	// if we have only one argument, just return the argument
	if ($numargs < 2)
	{
		return $args[0];
	}
	else
	{
		// call sprintf() on the first argument of this function
		$phrase = @call_user_func_array('sprintf', $args);
		if ($phrase !== false)
		{
			return $phrase;
		}
		else
		{
			// if that failed, add some extra arguments for debugging
			for ($i = $numargs; $i < 10; $i++)
			{
				$args["$i"] = "[ARG:$i UNDEFINED]";
			}
			if ($phrase = @call_user_func_array('sprintf', $args))
			{
				return $phrase;
			}
			// if it still doesn't work, just return the un-parsed text
			else
			{
				return $args[0];
			}
		}
	}
}

// ##################### Start vbstrtolower ###########################
// Only converts A-Z to a-z, doesn't change any other characters
function vbstrtolower($string)
{
	global $stylevar;
	if (function_exists('mb_strtolower') AND $newstring = @mb_strtolower($string, $stylevar['charset']))
	{
		return $newstring;
	}
	else
	{
		$length = strlen($string);
		for ($x = 0; $x < $length; $x++)
		{
			$decvalue = ord(substr($string, $x, 1));
			if ($decvalue >= 65 AND $decvalue <= 90)
			{
				$newstring .= chr($decvalue + 32);
			}
			else
			{
				$newstring .= chr($decvalue);
			}
		}

		return $newstring;
	}
}

// ##################### Start vbstrlen ###########################
// Converts html entities to a regular character so strlen can be performed
function vbstrlen($string)
{
	$string = preg_replace('#&\#([0-9]+);#', '_', $string);
	return strlen($string);
}

// ####################### Start sanitize_pageresults #####################
function sanitize_pageresults($numresults, &$page, &$perpage, $maxperpage = 20, $defaultperpage = 20)
{
	$perpage = intval($perpage);
	if ($perpage < 1)
	{
		$perpage = $defaultperpage;
	}
	else if ($perpage > $maxperpage)
	{
		$perpage = $maxperpage;
	}

	$numpages = ceil($numresults / $perpage);

	if ($page < 1)
	{
		$page = 1;
	}
	else if ($page > $numpages)
	{
		$page = $numpages;
	}
}

// ###################### Start validemail #######################
function is_valid_email($email)
{
	// checks for a valid email format
	return preg_match('#^[a-z0-9.!\#$%&\'*+-/=?^_`{|}~]+@([0-9.]+|([^\s\'"<>]+\.+[a-z]{2,6}))$#si', $email);
}

// ###################### Start vb_number_format #######################
// format a number with user's own decimal and thousands chars
function vb_number_format($number, $decimals = 0, $bytesize = false)
{
	global $bbuserinfo, $vbphrase;

	if ($bytesize)
	{
		if ($number >= 1073741824)
		{
			$number = $number / 1073741824;
			$decimals = 2;
			$type = " $vbphrase[gigabytes]";
		}
		else if ($number >= 1048576)
		{
			$number = $number / 1048576;
			$decimals = 2;
			$type = " $vbphrase[megabytes]";
		}
		else if ($number >= 1024)
		{
			$number = $number / 1024;
			$decimals = 1;
			$type = " $vbphrase[kilobytes]";
		}
		else
		{
			$decimals = 0;
			$type = " $vbphrase[bytes]";
		}
	}

	return str_replace('_', '&nbsp;', number_format($number, $decimals, $bbuserinfo['lang_decimalsep'], $bbuserinfo['lang_thousandsep'])) . $type;
}

// ###################### Start getmembergroupids #######################
// returns an array of usergroupids from all the usergroups a user belongs to
function fetch_membergroupids_array($user, $getprimary = true)
{
	if ($user['membergroupids'])
	{
		$membergroups = explode(',', str_replace(' ', '', $user['membergroupids']));
	}
	else
	{
		$membergroups = array();
	}

	if ($getprimary)
	{
		$membergroups[] = $user['usergroupid'];
	}

	return array_unique($membergroups);
}

// ###################### Start is member of #######################
// returns true/false if a $userinfo belongs to $usergroupid
// $userinfo must contain (userid, usergroupid, membergroupids)
function is_member_of($userinfo, $usergroupid)
{
	static $user_memberships;

	if ($userinfo['usergroupid'] == $usergroupid)
	{
		// user's primary usergroup is $usergroupid - return true
		return true;
	}
	else if (!is_array($user_memberships["$userinfo[userid]"]))
	{
		// fetch membergroup ids
		$user_memberships["$userinfo[userid]"] = fetch_membergroupids_array($userinfo);
	}

	// return true/false depending on membergroup ids
	return in_array($usergroupid, $user_memberships["$userinfo[userid]"]);
}

// ###################### Start is_in_coventry #######################
function in_coventry($userid, $includeself = false)
{
	global $vboptions, $bbuserinfo;
	static $Coventry;

	// if user is guest, or user is bbuser, user is NOT in Coventry.
	if ($userid == 0 OR ($userid == $bbuserinfo['userid'] AND $includeself == false))
	{
		return false;
	}

	if (!is_array($Coventry))
	{
		if (trim($vboptions['globalignore']) != '')
		{
			$Coventry = preg_split('#\s+#s', $vboptions['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
		}
		else
		{
			$Coventry = array();
		}
	}

	// if Coventry is empty, user is not in Coventry
	if (empty($Coventry))
	{
		return false;
	}

	// return whether or not user's id is in Coventry
	return in_array($userid, $Coventry);
}

// ###################### Start queryphrase #######################
function fetch_phrase($phrasename, $phrasetypeid, $strreplace = '', $doquotes = true, $alllanguages = false, $languageid = -1)
{
	// we need to do some caching in this function I believe
	global $DB_site;
	static $phrase_cache;

	if (!empty($strreplace))
	{
		if (strpos("$phrasename", $strreplace) !== false)
		{
			$phrasename = substr($phrasename, strlen($strreplace));
		}
	}

	$phrasetypeid = intval($phrasetypeid);

	if (!isset($phrase_cache["{$phrasetypeid}-{$phrasename}"]))
	{
		$getphrases = $DB_site->query("
			SELECT text, languageid
			FROM " . TABLE_PREFIX . "phrase
			WHERE phrasetypeid = $phrasetypeid
				AND varname = '" . addslashes($phrasename) . "'"
				. iif(!$alllanguages, "AND languageid IN (-1, 0, " . intval(LANGUAGEID) . ")")
		);
		while ($getphrase = $DB_site->fetch_array($getphrases))
		{
			$phrase_cache["{$phrasetypeid}-{$phrasename}"]["$getphrase[languageid]"] = $getphrase['text'];
		}
		unset($getphrase);
		$DB_site->free_result($getphrases);
	}

	$phrase = &$phrase_cache["{$phrasetypeid}-{$phrasename}"];

	if ($languageid == -1)
	{ // didn't pass in a languageid as this is the default value so use the browsing user's languageid
		$languageid = LANGUAGEID;
	}
	else if ($languageid == 0)
	{ // the user is using forum default
		global $vboptions;
		$languageid = $vboptions['languageid'];
	}

	if (isset($phrase[$languageid]))
	{
		$messagetext = $phrase[$languageid];
	}
	else if (isset($phrase[0]))
	{
		$messagetext = $phrase[0];
	}
	else if (isset($phrase['-1']))
	{
		$messagetext = $phrase['-1'];
	}
	else
	{
		$messagetext = "Could not find phrase '$phrasename'.";
	}

	$messagetext = preg_replace('#\{([0-9])+\}#sU', '%\\1$s', $messagetext);

	if ($doquotes)
	{
		return str_replace("\\'", "'", addslashes($messagetext));
	}
	else
	{
		return $messagetext;
	}

}

// ###################### Start iif #######################
function iif($expression, $returntrue, $returnfalse = '')
{
	return ($expression ? $returntrue : $returnfalse);
}

// ###################### Start blankAsciiStrip #######################
// note: blank removal currently causes problems with double byte languages!
function strip_blank_ascii($text, $replace)
{
	global $vboptions;

	if (trim($vboptions['blankasciistrip']) != '')
	{
		$blanks = preg_split('#\s+#', $vboptions['blankasciistrip'], -1, PREG_SPLIT_NO_EMPTY);
		foreach($blanks AS $key => $char)
		{
			$blanks["$key"] = chr(intval($char));
		}
		$text = str_replace($blanks, $replace, $text);
	}

	return $text;
}

// ###################### Start censortext #######################
function fetch_censored_text($text)
{
	global $vboptions;
	static $censorwords;

	if ($vboptions['enablecensor'] AND !empty($vboptions['censorwords']))
	{
		if (empty($censorwords))
		{
			$vboptions['censorwords'] = preg_quote($vboptions['censorwords'], '#');
			$censorwords = preg_split('#\s+#', $vboptions['censorwords'], -1, PREG_SPLIT_NO_EMPTY);
		}

		foreach ($censorwords AS $censorword)
		{
			if (substr($censorword, 0, 2) == '\\{')
			{
				if (substr($censorword, -2, 2) == '\\}')
				{
					// prevents errors from the replace if the { and } are mismatched
					$censorword = substr($censorword, 2, -2);
				}
				$text = preg_replace('#(?<=[^a-z]|^)' . $censorword . '(?=[^a-z]|$)#si', str_repeat($vboptions['censorchar'], strlen($censorword)), $text);
			}
			else
			{
				$text = preg_replace("#$censorword#si", str_repeat($vboptions['censorchar'], strlen($censorword)), $text);
			}
		}
	}

	// strip any admin-specified blank ascii chars
	$text = strip_blank_ascii($text, $vboptions['censorchar']);

	return $text;
}

// ###################### Start dowordwrap #######################
function fetch_word_wrapped_string($text, $limit = false)
{
	global $vboptions;

	if ($limit)
	{
		$vboptions['wordwrap'] = $limit;
	}

	if ($vboptions['wordwrap'] != 0 AND !empty($text))
	{
		return preg_replace('#([^\s&/<>"\\-\[\]]|&[\#a-z0-9]{1,7};){' . $vboptions['wordwrap'] . '}#i', '$0  ', $text);
	}
	else
	{
		return $text;
	}
}

// ###################### Start trimthreadtitle #######################
// trims last thread title for forumhome if too long
function fetch_trimmed_title($title, $chars = -1)
{
	global $vboptions;

	if ($chars == -1)
	{
		$chars = $vboptions['lastthreadchars'];
	}

	if ($chars)
	{
		// limit to 10 lines (\n{240}1234567890 does weird things to the thread preview)
		$titlearr = preg_split('#(\r\n|\n|\r)#', $title);
		$title = '';
		$i = 0;
		foreach ($titlearr AS $key)
		{
			$title .= "$key\n";
			$i++;
			if ($i >= 10)
			{
				break;
			}
		}
		$title = trim($title);
		unset($titlearr);

		if (strlen($title) > $chars)
		{
			// trim text to specified char length, then trim after last space to avoid half-words
			return substr($title, 0, strrpos(substr($title, 0, $chars), ' ')) . '...';
		}
		else
		{
			return $title;
		}
	}
	else
	{
		return $title;
	}
}

// ###################### Start checkipban #######################
function verify_ip_ban()
{
	// checkes to see if the current ip address is banned
	global $vboptions, $session;

	$vboptions['banip'] = trim($vboptions['banip']);
	if ($vboptions['enablebanning'] == 1 AND $vboptions['banip'])
	{
		$addresses = explode(' ', preg_replace("/[[:space:]]+/", " ", $vboptions['banip']) );
		foreach ($addresses AS $val)
		{
			if (strpos(' ' . IPADDRESS, ' ' . trim($val)) !== false)
			{
				eval(print_standard_error('error_banip'));
			}
		}
	}
}

// ###################### Start getextension #######################
function file_extension($filename)
{
	return substr(strrchr($filename, '.'), 1);
}

// ###################### Start doshutdown #######################
$shutdownqueries = array();
function exec_shut_down()
{
	global $shutdownqueries, $DB_site;
	global $bbuserinfo, $session, $foruminfo, $threadinfo, $calendarinfo, $permissions, $vboptions;

	if ($bbuserinfo['badlocation'])
	{
		$threadinfo = array('threadid' => 0);
		$foruminfo = array('forumid' => 0);
		$calendarinfo = array('calendarid' => 0);
	}

	if (!$vboptions['bbactive'] AND !($permissions['adminpermissions'] & CANCONTROLPANEL))
	{ // Forum is disabled and this is not someone with admin access
		$bbuserinfo['badlocation'] = 2;
	}

	$shutdownqueries['sessionupdate'] = str_replace('###REPLACE###', ',inforum = ' . intval($foruminfo['forumid']) . ', inthread = ' . intval($threadinfo['threadid']) . ', incalendar = ' . intval($calendarinfo['calendarid']) . ', badlocation = ' . intval($bbuserinfo['badlocation']), $shutdownqueries['sessionupdate']);

	$search = array(
		'###REPLACE1###',
		'###REPLACE2###'
	);
	$replace = array(
		',inforum, inthread, incalendar, badlocation',
		',' . intval($foruminfo['forumid']) . ', ' . intval($threadinfo['threadid']) . ', ' . intval($calendarinfo['calendarid']) . ',' . intval($bbuserinfo['badlocation'])
	);
	$shutdownqueries['sessioninsert'] = str_replace($search, $replace, $shutdownqueries['sessioninsert']);

	if (is_array($shutdownqueries))
	{
		$DB_site->reporterror = 0;
		foreach($shutdownqueries AS $query)
		{
			if (!empty($query))
			{
				$DB_site->query($query);
			}
		}
		$DB_site->reporterror = 1;
	}

	exec_mail_queue();

	$shutdownqueries = array(); // stop the queries from being reexecuted for whatever reason
	// bye bye!
}

// ###################### Start vb_send_mail #######################
function vb_send_mail($toemail, $subject, $message, $header)
{
	global $vboptions;

	if (!class_exists('Mail'))
	{
		require_once('./includes/mail.php');
	}

	if (false) // will eventually use $vboptions to determine this
	{
		$mailObj = new SmtpMail($toemail, $subject, $message, $header, $vboptions['webmasteremail'], (boolean)$vboptions['needfromemail']);
	}
	else
	{
		$mailObj = new Mail($toemail, $subject, $message, $header, $vboptions['webmasteremail'], (boolean)$vboptions['needfromemail']);
	}

	return $mailObj->success;
}

// ###################### Start domailqueue #######################
function exec_mail_queue()
{
	global $datastore, $vboptions, $DB_site;

	if (isset($datastore['mailqueue']) AND $datastore['mailqueue'] > 0 AND $vboptions['usemailqueue'])
	{
		// mailqueue template holds number of emails awaiting sending
		//$vboptions['emailsendnum'] = 5; // number to send at once

		$emails = $DB_site->query("
			SELECT *
			FROM " . TABLE_PREFIX . "mailqueue
			ORDER BY mailqueueid DESC
			LIMIT " . $vboptions['emailsendnum']
		);

		$newmail = 0;
		$emailarray = array();
		while ($email = $DB_site->fetch_array($emails))
		{
			// count up number of mails about to send
			$mailqueueids .= ',' . $email['mailqueueid'];
			$newmail++;
			$emailarray[] = $email;
		}
		if (!empty($mailqueueids))
		{
			// remove mails from queue - to stop duplicates being sent
			$DB_site->query("
				DELETE FROM " . TABLE_PREFIX . "mailqueue
				WHERE mailqueueid IN (0 " . $mailqueueids . ")
			");

			require_once(dirname(__FILE__) . '/mail.php');

			foreach ($emailarray AS $index => $email)
			{
				// send those mails
				vb_send_mail($email['toemail'], $email['subject'], $email['message'], $email['header']);
			}

			$newmail = 'data - ' . intval($newmail);
		}
		else
		{
			$newmail = 0;
		}

		// update number of mails remaining
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "datastore
			SET data = " . $newmail . ",
			data = IF( data < 0, 0, data )
			WHERE title = 'mailqueue'
		");
		build_datastore();
	}
}

if (!NOSHUTDOWNFUNC)
{
	register_shutdown_function('exec_shut_down');
}

// ###################### Start vb_rand #######################
function vbrand($min, $max, $seed = -1)
{

	if (!defined('RAND_SEEDED'))
	{
		if ($seed == -1)
		{
			$seed = (double) microtime() * 1000000;
		}

		mt_srand($seed);
		define('RAND_SEEDED', true);
	}

	return mt_rand($min, $max);
}

// ###################### Start globalize #######################
function globalize(&$var_array, $var_names)
{
	global $_FILES;
	// takes variables from a $_REQUEST, $_POST style array
	// and makes them into global variables

	foreach ($var_names AS $varname => $type)
	{
		if (is_numeric($varname)) // This handles the case where you send a variable in without giving its type, i..e. 'foo' => INT
		{
			$varname = $type;
			$type = '';
		}
		if (isset($var_array["$varname"]) OR $type == 'INT' OR $type == 'FILE')
		{
			switch ($type)
			{
				// integer value - run intval() on data
				case 'INT':
					$var_array["$varname"] = intval($var_array["$varname"]);
					break;

				// html-safe string - trim and htmlspecialchars data
				case 'STR_NOHTML':
					$var_array["$varname"] = htmlspecialchars_uni(trim($var_array["$varname"]));
					break;

				// string - trim data
				case 'STR':
					$var_array["$varname"] = trim($var_array["$varname"]);
					break;

				// file - get data from $_FILES array
				case 'FILE':
					if (isset($_FILES["$varname"]))
					{
						$var_array["$varname"] = $_FILES["$varname"];
					}
					break;

				// Do nothing, i.e. arrays, etc.
				default:
			}
			$GLOBALS["$varname"] = &$var_array["$varname"];
		}
	}
}

// ###################### Start getlanguagefields #######################
function fetch_language_fields_sql($addtable = 1)
{
	global $phrasegroups;

	$phrasegroups[] = 'global';

	if ($addtable)
	{
		$prefix = 'language.';
	}
	else
	{
		$prefix = '';
	}

	$sql = '';
	foreach($phrasegroups AS $group)
	{
		$group = preg_replace('#[^a-z0-9_]#i', '', $group); // just to be safe...
		$sql .= ",
			{$prefix}phrasegroup_$group AS phrasegroup_$group";
	}

	$sql .= ",
			{$prefix}options AS lang_options,
			{$prefix}languagecode AS lang_code,
			{$prefix}charset AS lang_charset,
			{$prefix}locale AS lang_locale,
			{$prefix}imagesoverride AS lang_imagesoverride,
			{$prefix}dateoverride AS lang_dateoverride,
			{$prefix}timeoverride AS lang_timeoverride,
			{$prefix}registereddateoverride AS lang_registereddateoverride,
			{$prefix}calformat1override AS lang_calformat1override,
			{$prefix}calformat2override AS lang_calformat2override,
			{$prefix}logdateoverride AS lang_logdateoverride,
			{$prefix}decimalsep AS lang_decimalsep,
			{$prefix}thousandsep AS lang_thousandsep";

	return $sql;
}

// ###################### Start appendphrasegroup #######################
function fetch_phrase_group($groupname)
{
	global $DB_site, $vbphrase, $bbuserinfo, $vboptions, $phrasegroups;

	if (in_array($groupname, $phrasegroups))
	{
		// this group is already in $vbphrase
		return;
	}
	$phrasegroups[] = $groupname;

	$group = $DB_site->query_first("
		SELECT phrasegroup_$groupname AS $groupname
		FROM " . TABLE_PREFIX . "language
		WHERE languageid = " . intval(iif($bbuserinfo['languageid'], $bbuserinfo['languageid'], $vboptions['languageid']))
	);

	$vbphrase = array_merge($vbphrase, unserialize($group["$groupname"]));
}

// ###################### Start makequery #######################
// returns an UPDATE/INSERT query string for use in those big queries with loads of fields...
// $queryvalues is an associative array of $x[fieldname] = $value
// $table is the table to work with;
// $condition should be the condition string used for an UPDATE query.
// $exclusions is an array of fieldnames that are to be left as is
function fetch_query_sql($queryvalues, $table, $condition = '', $exclusions = '')
{

	if (empty($exclusions))
	{
		$exclusions = array();
	}

	$numfields = sizeof($queryvalues);
	$i = 1;

	if (!empty($condition))
	{
		$querystring = "\n### UPDATE QUERY GENERATED BY fetch_query_sql() ###\n";
		foreach($queryvalues AS $fieldname => $value)
		{
			$querystring .= "\t$fieldname = " . iif(is_numeric($value) OR in_array($fieldname, $exclusions), "'$value'", "'" . addslashes($value) . "'") . iif($i++ == $numfields, "\n", ",\n");
		}
		return "UPDATE " . TABLE_PREFIX . "$table SET\n$querystring$condition";
	}
	else
	{
		#$fieldlist = $table . 'id, ';
		#$valuelist = 'NULL, ';
		$fieldlist = '';
		$valuelist = '';
		foreach($queryvalues AS $fieldname => $value)
		{
			$endbit = iif($i++ == $numfields, '', ', ');
			$fieldlist .= $fieldname . $endbit;
			$valuelist .= iif(is_numeric($value) OR in_array($fieldname, $exclusions), "'$value'", "'" . addslashes($value) . "'") . $endbit;
		}
		return "\n### INSERT QUERY GENERATED BY fetch_query_sql() ###\nINSERT INTO " . TABLE_PREFIX . "$table\n\t($fieldlist)\nVALUES\n\t($valuelist)";
	}
}

// ###################### Start getMuserName #######################
function fetch_musername(&$user, $displaygroupfield = 'displaygroupid', $usernamefield = 'username')
{
	global $usergroupcache;

	$username = $user["$usernamefield"];

	if (isset($usergroupcache["$user[$displaygroupfield]"]))
	{
		// use $displaygroupid
		$displaygroupid = $user["$displaygroupfield"];
	}
	else if (isset($usergroupcache["$user[usergroupid]"]))
	{
		// use primary usergroupid
		$displaygroupid = $user['usergroupid'];
	}
	else
	{
		// use guest usergroup
		$displaygroupid = 1;
	}

	$user['musername'] = $usergroupcache["$displaygroupid"]['opentag'] . $username . $usergroupcache["$displaygroupid"]['closetag'];
	$user['displaygrouptitle'] = $usergroupcache["$displaygroupid"]['title'];
	$user['displayusertitle'] = $usergroupcache["$displaygroupid"]['usertitle'];

	return $user['musername'];
}

// ###################### Start getforuminfo #######################
function fetch_foruminfo(&$forumid, $usecache = true)
{
	global $DB_site, $_FORUMOPTIONS;
	global $forumcache;

	$forumid = intval($forumid);
	if (!$usecache OR !isset($forumcache["$forumid"]))
	{
		$forumcache["$forumid"] = $DB_site->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "forum
			WHERE forumid = $forumid
		");
	}

	if (!$forumcache["$forumid"])
	{
		return 0;
	}

	// decipher 'options' bitfield
	$forumcache["$forumid"]['options'] = intval($forumcache["$forumid"]['options']);
	foreach($_FORUMOPTIONS AS $optionname => $optionval)
	{
		$forumcache["$forumid"]["$optionname"] = iif(($forumcache["$forumid"]['options'] & $optionval), 1, 0);
	}

	return $forumcache["$forumid"];
}

// ###################### Start getthreadinfo #######################
function fetch_threadinfo(&$threadid)
{
	global $DB_site, $threadcache, $bbuserinfo, $vboptions;

	$threadid = intval($threadid);
	if (!isset($threadcache["$threadid"]))
	{
		$threadcache["$threadid"] = $DB_site->query_first("
			SELECT NOT ISNULL(deletionlog.primaryid) AS isdeleted, deletionlog.userid AS del_userid,
			deletionlog.username AS del_username, deletionlog.reason AS del_reason,
			" . iif($bbuserinfo['userid'] AND ($vboptions['threadsubscribed'] AND THIS_SCRIPT == 'showthread') OR THIS_SCRIPT == 'editpost' OR THIS_SCRIPT == 'newreply' OR THIS_SCRIPT == 'postings', 'NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed, emailupdate, folderid,')
			  . iif($vboptions['threadvoted'] AND $bbuserinfo['userid'], 'threadrate.vote,') . "
			thread.*
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
			" . iif($bbuserinfo['userid'] AND ($vboptions['threadsubscribed'] AND THIS_SCRIPT == 'showthread') OR THIS_SCRIPT == 'editpost' OR THIS_SCRIPT == 'newreply' OR THIS_SCRIPT == 'postings', "LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON (subscribethread.threadid = thread.threadid AND subscribethread.userid = $bbuserinfo[userid])") . "
			" . iif($vboptions['threadvoted'] AND $bbuserinfo['userid'], "LEFT JOIN " . TABLE_PREFIX . "threadrate AS threadrate ON (threadrate.threadid = thread.threadid AND threadrate.userid = $bbuserinfo[userid])") . "
			WHERE thread.threadid = $threadid
		");
	}

	return $threadcache["$threadid"];
}

// ###################### Start getpostinfo #######################
function fetch_postinfo(&$postid)
{
	global $DB_site;
	global $postcache;

	$postid = intval($postid);
	if (!isset($postcache["$postid"]))
	{
		$postcache["$postid"] = $DB_site->query_first("
			SELECT post.*,
			NOT ISNULL(deletionlog.primaryid) AS isdeleted, deletionlog.userid AS del_userid,
			deletionlog.username AS del_username, deletionlog.reason AS del_reason,
			editlog.userid AS edit_userid, editlog.dateline AS edit_dateline, editlog.reason AS edit_reason
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND deletionlog.type = 'post')
			LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON (editlog.postid = post.postid)
			WHERE post.postid = $postid
		");
	}

	return $postcache["$postid"];
}

// ###################### Start getuserinfo #######################
// set $option to load other relevant information
function fetch_userinfo(&$userid, $option = 0)
{
	global $DB_site, $usercache, $vboptions, $vbphrase, $bbuserinfo, $permissions, $_USEROPTIONS, $phrasegroups, $usergroupcache;

	// Use bitwise to set $option table // see fetch_userinfo() in the getinfo section of member.php if you are confused
	// 1 - Join the reputationlevel table to get the user's reputation description
	// 2 - Get avatar
	// 4 - Process user's online location
	// 8 - Join the customprofilpic table to get the userid just to check if we have a picture
	// 16 - Join the administrator table to get various admin options
	// and so on.

	if ($userid == $bbuserinfo['userid'] AND $option != 0 AND isset($usercache["$userid"]))
	{
		// clear the cache if we are looking at ourself and need to add one of the JOINS to our information.
		unset($usercache["$userid"]);
	}

	$userid = intval($userid);

	// return the cached result if it exists
	if (isset($usercache["$userid"]))
	{
		return $usercache["$userid"];
	}

	// no cache available - query the user
	if (!isset($vbphrase))
	{
		$DB_site->reporterror = 0;
	}
	$user = $DB_site->query_first("
		SELECT " .
		iif(($option & 16), ' administrator.*, ') . "
		userfield.*, usertextfield.*, user.*, UNIX_TIMESTAMP(passworddate) AS passworddate,
		IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid" .
		iif(($option & 1) AND $vboptions['reputationenable'] == 1, ', level') .
		iif(($option & 2) AND $vboptions['avatarenabled'], ', avatar.avatarpath, NOT ISNULL(customavatar.avatardata) AS hascustomavatar, customavatar.dateline AS avatardateline').
		iif(($option & 8), ', customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline') .
		iif(!isset($vbphrase), fetch_language_fields_sql(), '') . "
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (user.userid = userfield.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid) " .
		iif(($option & 1) AND $vboptions['reputationenable'] == 1, "LEFT JOIN  " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON (user.reputationlevelid = reputationlevel.reputationlevelid) ").
		iif(($option & 2) AND $vboptions['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) ") .
		iif(($option & 8), "LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid) ") .
		iif(($option & 16), "LEFT JOIN " . TABLE_PREFIX . "administrator AS administrator ON (administrator.userid = user.userid) ") .
		iif(!isset($vbphrase), "INNER JOIN " . TABLE_PREFIX . "language AS language ON (language.languageid = IF(user.languageid = 0, " . intval($vboptions['languageid']) . ", user.languageid)) ")."
		WHERE user.userid = $userid
	");
	if (!isset($vbphrase))
	{
		$DB_site->reporterror = 1;
	}

	if (!$user)
	{
		return array();
	}

	// decipher 'options' bitfield
	$user['options'] = intval($user['options']);
	foreach($_USEROPTIONS AS $optionname => $optionval)
	{
		$user["$optionname"] = iif($user['options'] & $optionval, 1, 0);
		DEVDEBUG("$optionname = $user[$optionname]");
	}

	// make a username variable that is safe to pass through URL links
	$user['urlusername'] = urlencode(unhtmlspecialchars($user['username']));

	// make a username variable that is surrounded by their display group markup
	$user['musername'] = fetch_musername($user);

	// get the user's real styleid (not the cookie value)
	$user['realstyleid'] = $user['styleid'];

	if ($option & 4)
	{ // Process Location info for this user
		require_once('./includes/functions_online.php');
		$user = fetch_user_location_array($user);
	}

	$usercache["$userid"] = $user;
	return $usercache["$userid"];
}

// ###################### Start getforumarray #######################
function fetch_forum_parent_list($forumid)
{
	global $DB_site, $forumcache;
	static $forumarraycache;

	if (isset($forumcache["$forumid"]['parentlist']))
	{
		DEVDEBUG("CACHE parentlist from forum $forumid");
		return $forumcache["$forumid"]['parentlist'];
	}
	else
	{
		if (isset($forumarraycache["$forumid"]))
		{
			return $forumarraycache["$forumid"];
		}
		else if (isset($forumcache["$forumid"]['parentlist']))
		{
			return $forumcache["$forumid"]['parentlist'];
		}
		else
		{
			DEVDEBUG("QUERY parentlist from forum $forumid");
			$foruminfo = $DB_site->query_first("
				SELECT parentlist
				FROM " . TABLE_PREFIX . "forum
				WHERE forumid = $forumid
			");
			$forumarraycache["$forumid"] = $foruminfo['parentlist'];
			return $foruminfo['parentlist'];
		}
	}
}

// ###################### Start getforumlist #######################
function fetch_forum_clause_sql($forumid, $field = 'forumid', $joiner = 'OR', $parentlist = '')
{
	// this function returns something like: (forumid=1 OR forumid=2 OR forumid=3)
	// the 'OR' is specified in $joiner
	// the 'forumid' is specified in $field
	// $forumid specifies which forum's parentlist to search.

	global $DB_site;

	if (empty($parentlist))
	{
		$parentlist = fetch_forum_parent_list($forumid);
	}

	if (empty($parentlist))
	{
		// prevents an error, and is at least somewhat correct
		$parentlist = '-1,' . intval($forumid);
	}

	if (strtoupper($joiner) == 'OR')
	{
		return "$field IN ($parentlist)";
	}
	else
	{
		return "($field = '" . implode(explode(',', $parentlist), "' $joiner $field = '") . '\')';
	}

}

// ###################### Start verifyid #######################
function verify_id($idname, &$id, $alert = 1, $selall = 0, $options = 0)
{
	// verifies an id number and returns a correct one if it can be found
	// returns 0 if none found
	global $DB_site, $vboptions, $session, $threadcache, $forumcache, $postcache, $usercache, $threadidcache;
	global $forumidcache, $postidcache, $useridcache, $bbuserinfo, $vbphrase;

	$id = intval($id);
	if (empty($id))
	{
		if ($alert)
		{
			$idname = $vbphrase["$idname"];
			eval(print_standard_error('error_noid'));
		}
		else
		{
			return 0;
		}
	}

	if ($selall == 1)
	{
		$selid = '*';
	}
	else
	{
		if ($idname == 'thread' AND $threadidcache["$id"])
		{
			return $threadidcache["$id"];
		}
		else if ($idname == 'forum' AND $forumidcache["$id"])
		{
			return $forumidcache["$id"];
		}
		else if ($idname == 'post' AND $postidcache["$id"])
		{
			return $postidcache["$id"];
		}
		else if ($idname == 'user' AND $useridcache["$id"])
		{
			return $useridcache["$id"];
		}
		$selid = $idname . 'id';
	}

	switch ($idname)
	{
		case 'thread':
			$tempcache = fetch_threadinfo($id);
			if (!$tempcache AND $alert)
			{
				$idname = $vbphrase['thread'];
				eval(print_standard_error('invalidid'));
			}
			if ($selall != 1)
			{
				return $tempcache['threadid'];
			}
			else
			{
				return $tempcache;
			}
		case 'forum':
			$tempcache = fetch_foruminfo($id);
			if (!$tempcache AND $alert)
			{
				$idname = $vbphrase['forum'];
				eval(print_standard_error('invalidid'));
			}
			if ($selall != 1)
			{
				return $tempcache['forumid'];
			}
			else
			{
				return $tempcache;
			}
		case 'post':
			$tempcache = fetch_postinfo($id);
			if (!$tempcache AND $alert)
			{
				$idname = $vbphrase['post'];
				eval(print_standard_error('invalidid'));
			}
			if ($selall != 1)
			{
				return $tempcache['postid'];
			}
			else
			{
				return $tempcache;
			}
		case 'user':
			$tempcache = fetch_userinfo($id, $options);
			if (!$tempcache AND $alert)
			{
				$idname = $vbphrase['user'];
				eval(print_standard_error('invalidid'));
			}
			if ($selall != 1)
			{
				return $tempcache['userid'];
			}
			else
			{
				return $tempcache;
			}
		default:
			if (!$check = $DB_site->query_first("SELECT $selid FROM " . TABLE_PREFIX . "$idname WHERE $idname" . "id=$id"))
			{
				if ($alert)
				{ // show alert?
					$idname = $vbphrase["$idname"];
					eval(print_standard_error('invalidid'));
				}
				if ($selall == 1)
				{
					return array();
				}
				else
				{
					return 0;
				}
			}
			else
			{
				if ($selall != 1)
				{
					if ($idname == 'thread')
					{
						$threadidcache["$check[$selid]"] = $check["$selid"];
					}
					else if ($idname == 'forum')
					{
						$forumidcache["$check[$selid]"] = $check["$selid"];
					}
					else if ($idname == 'post')
					{
						$postidcache["$check[$selid]"] = $check["$selid"];
					}
					else if ($idname == 'user')
					{
						$useridcache["$check[$selid]"] = $check["$selid"];
					}
					return $check["$selid"];
				}
				else
				{
					if ($idname == 'thread')
					{
						$threadcache["$check[threadid]"] = $check;
					}
					else if ($idname == 'forum')
					{
						$forumcache["$check[forumid]"] = $check;
					}
					else if ($idname == 'post')
					{
						$postcache["$check[postid]"] = $check;
					}
					else if ($idname == 'user')
					{
						$usercache["$check[userid]"] = $check;
					}
					return $check;
				}
			}
	}
}

// ###################### Start vbmail_start #######################
// start a series of bulk email
function vbmail_start()
{
	global $bulkon, $mailcounter, $mailsql;

	$bulkon = true;
	$mailcounter = 0;
	$mailsql = '';
}

// ###################### Start vbmail #######################
// a level of abstraction from the PHP mail function
// should probably be replaced with a socket call at some point :)
function vbmail($toemail, $subject, $message, $notsubscription = false, $from = '' , $uheaders = '', $username = '')
{
	global $DB_site, $vboptions, $stylevar, $vbphrase;

	$sendmail_path = @ini_get('sendmail_path');
	if (!$sendmail_path)
	{
		// no sendmail, so we're using SMTP to send mail
		$delimiter = "\r\n";
	}
	else
	{
		$delimiter = "\n";
	}

	$toemail = fetch_email_first_line_string($toemail);

	if (!empty($toemail))
	{
		$toemail = unhtmlspecialchars($toemail);
		$subject = fetch_email_first_line_string($subject);
		$message = preg_replace("#(\r\n|\r|\n)#s", $delimiter, trim($message));

		if ((strtolower($stylevar['charset']) == 'iso-8859-1' OR $stylevar['charset'] == '') AND preg_match('/&[a-z0-9#]+;/i', $message))
		{
			$message = utf8_encode($message);
			$subject = utf8_encode($subject);
			$username = utf8_encode($username);

			$encoding = 'UTF-8';
			$unicode_decode = true;
		}
		else
		{
			// we know nothing about the message's encoding in relation to UTF-8,
			// so we can't modify the message at all; just set the encoding
			$encoding = $stylevar['charset'];
			$unicode_decode = false;
		}
		// theses lines may need to call convert_int_to_utf8 directly
		$message = unhtmlspecialchars($message, $unicode_decode);
		$subject = unhtmlspecialchars($subject, $unicode_decode);
		if ($unicode_decode)
		{
			$subject = inline_mime_encode($subject, 'utf-8');
		}

		$from = fetch_email_first_line_string($from);
		if (empty($from))
		{
			if (isset($vbphrase['x_mailer']))
			{
				$mailfromname = construct_phrase(fetch_email_first_line_string($vbphrase['x_mailer']), $vboptions['bbtitle']);
			}
			else
			{
				$mailfromname = "$vboptions[bbtitle] Forums";
			}
			if ($unicode_decode == true)
			{
				$mailfromname = utf8_encode($mailfromname);

			}
			$mailfromname = unhtmlspecialchars($mailfromname, $unicode_decode);
			if ($unicode_decode)
			{
				$mailfromname = inline_mime_encode($mailfromname, 'utf-8');
			}

			$headers .= "From: \"$mailfromname\" <$vboptions[webmasteremail]>" . $delimiter;
			$headers .= 'Return-Path: ' . $vboptions['webmasteremail'] . $delimiter;
		}
		else
		{
			if ($username)
			{
				$mailfromname = "$username @ $vboptions[bbtitle]";
			}
			else
			{
				$mailfromname = $from;
			}
			if ($unicode_decode == true)
			{
				$mailfromname = utf8_encode($mailfromname);

			}
			$mailfromname = unhtmlspecialchars($mailfromname, $unicode_decode);
			if ($unicode_decode)
			{
				$mailfromname = inline_mime_encode($mailfromname, 'utf-8');
			}

			$headers .= "From: \"$mailfromname\" <$from>" . $delimiter;
			$headers .= 'Return-Path: ' . $from . $delimiter;
		}

		if ($_SERVER['HTTP_HOST'] OR $_ENV['HTTP_HOST'])
		{
			$http_host = iif($_SERVER['HTTP_HOST'], $_SERVER['HTTP_HOST'], $_ENV['HTTP_HOST']);
		}
		else if ($_SERVER['SERVER_NAME'] OR $_ENV['SERVER_NAME'])
		{
			$http_host = iif($_SERVER['SERVER_NAME'], $_SERVER['SERVER_NAME'], $_ENV['SERVER_NAME']);
		}
		$http_host = trim($http_host);
		if (!$http_host)
		{
			$http_host = substr(md5($message), 6, 12) . '.vb_unknown.unknown';
		}
		$msgid = '<' . gmdate('YmdHs') . '.' . substr(md5($message . microtime()), 0, 6) . vbrand(100000, 999999) . '@' . $http_host . '>';
		$headers .= 'Message-ID: ' . $msgid . $delimiter;

		$headers .= preg_replace("#(\r\n|\r|\n)#s", $delimiter, $uheaders);
		unset($uheaders);

		$headers .= "X-Priority: 3" . $delimiter;
		$headers .= "X-Mailer: vBulletin Mail via PHP" . $delimiter;
		$headers .= 'MIME-Version: 1.0' . $delimiter;
		$headers .= 'Content-Type: text/plain' . iif($encoding, "; charset=\"$encoding\"") . $delimiter;
		$headers .= "Content-Transfer-Encoding: 8bit" . $delimiter;

		if ($vboptions['usemailqueue'] AND !$notsubscription)
		{
			$data = '(' . TIMENOW .' , "' . addslashes($toemail) . '" , "' . addslashes($subject) . '" , "' . addslashes($message) . '" , "' . addslashes($headers) . '" )';

			global $bulkon , $mailsql , $mailcounter;

			if ($bulkon)
			{
				if (!empty($mailsql))
				{
					$mailsql .= ', ';
				}

				$mailsql .= $data;
				$mailcounter++;

				// current insert exceeds half megabyte, insert it and start over
				if (strlen($mailsql) > 524288)
				{
					vbmail_end();
					vbmail_start();
				}
			}
			else
			{
				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "mailqueue
					(dateline, toemail, subject, message, header)
					VALUES
					" . $data
				);
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "datastore
					SET data = data + 1
					WHERE title = 'mailqueue'
				");
				build_datastore();
			}
		}
		else
		{

			vb_send_mail($toemail, $subject, $message, $headers);
		}

	} // end if($toemail)
}

// ###################### Start vbmail_end #######################
// ends a series of bulk email
function vbmail_end()
{
	global $bulkon , $mailsql , $mailcounter, $DB_site, $vboptions;

	$bulkon = false;

	if ($mailcounter AND $mailsql AND $vboptions['usemailqueue'])
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "mailqueue
			(dateline,toemail,subject,message,header)
			VALUES
			" . $mailsql
		);
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "datastore
			SET data = data + " . intval($mailcounter) . "
			WHERE title = 'mailqueue'
		");

		build_datastore();

		unset($mailsql);
		$mailcounter = 0;
	}
}

// ###################### Start emailfirstlineonly #######################
// gets only the first line of a string -- good to prevent errors when sending emails (above)
function fetch_email_first_line_string($text)
{
	$text = preg_replace("/(\r\n|\r|\n)/s", "\r\n", trim($text));
	$pos = strpos($text, "\r\n");
	if ($pos !== false)
	{
		return substr($text, 0, $pos);
	}
	return $text;
}

// ###################### Start getemailphrase #######################
function fetch_email_phrases($email_phrase, $languageid = -1, $emailsub_phrase = '', $varprefix = '')
{
	// Returns 2 lines of eval()-able code -- one sets $message, the other $subject.
	// Use $varprefix to prefix $message (ie, $varprefix = 'test' -> $testmessage, $testsubject)

	if (empty($emailsub_phrase))
	{
		$emailsub_phrase = $email_phrase;
	}

	return '$' . $varprefix . 'message = "' . fetch_phrase($email_phrase, PHRASETYPEID_MAILMSG, 'email_', true, iif($languageid >= 0, true, ''), $languageid) . '";' .
		'$' . $varprefix . 'subject = "' . fetch_phrase($emailsub_phrase, PHRASETYPEID_MAILSUB, 'emailsubject_', true, iif($languageid >= 0, true, ''), $languageid) . '";';
}

// ###################### Start inline_mime_encode ####################
function inline_mime_encode($str, $encoding = 'utf-8')
{
	//return "=?$encoding?B?" . base64_encode($str) . "?=";

	// it seems that a lot of mail clients still take issue with utf-8 in headers,
	// so we're not going to do any inline translation...
	return $str;
}

// ###################### Start strip_quotes #######################
function strip_quotes($text)
{
	$lowertext = strtolower($text);

	// find all [quote tags
	$start_pos = array();
	$curpos = 0;
	do
	{
		$pos = strpos($lowertext, '[quote', $curpos);
		if ($pos !== false)
		{
			$start_pos["$pos"] = 'start';
			$curpos = $pos + 6;
		}
	}
	while ($pos !== false);

	if (sizeof($start_pos) == 0)
	{
		return $text;
	}

	// find all [/quote] tags
	$end_pos = array();
	$curpos = 0;
	do
	{
		$pos = strpos($lowertext, '[/quote]', $curpos);
		if ($pos !== false)
		{
			$end_pos["$pos"] = 'end';
			$curpos = $pos + 8;
		}
	}
	while ($pos !== false);

	if (sizeof($end_pos) == 0)
	{
		return $text;
	}

	// merge them together and sort based on position in string
	$pos_list = $start_pos + $end_pos;
	ksort($pos_list);

	do
	{
		// build a stack that represents when a quote tag is opened
		// and add non-quote text to the new string
		$stack = array();
		$newtext = '';
		$substr_pos = 0;
		foreach ($pos_list AS $pos => $type)
		{
			$stacksize = sizeof($stack);
			if ($type == 'start')
			{
				// empty stack, so add from the last close tag or the beginning of the string
				if ($stacksize == 0)
				{
					$newtext .= substr($text, $substr_pos, $pos - $substr_pos);
				}
				array_push($stack, $pos);
			}
			else
			{
				// pop off the latest opened tag
				if ($stacksize)
				{
					array_pop($stack);
					$substr_pos = $pos + 8;
				}
			}
		}

		// add any trailing text
		$newtext .= substr($text, $substr_pos);

		// check to see if there's a stack remaining, remove those points
		// as key points, and repeat. Allows emulation of a non-greedy-type
		// recursion.
		if ($stack)
		{
			foreach ($stack AS $pos)
			{
				unset($pos_list["$pos"]);
			}
		}
	}
	while ($stack);

	return $newtext;
}

// ###################### Start stripvbcode #######################
// this seems to work better than vbcodestrip... will need more testing though
function strip_bbcode($message, $stripquotes = false, $fast_and_dirty = false, $showlinks = true)
{
	$find = array();
	$replace = array();

	if ($stripquotes)
	{
		// [quote=username] and [quote]
		$message = strip_quotes($message);
	}

	// a really quick and rather nasty way of removing vbcode
	if ($fast_and_dirty)
	{
		// any old thing in square brackets
		$find[] = '#\[.*/?\]#siU';
		$replace[] = '';

		$message = preg_replace($find, $replace, $message);
	}
	// the preferable way to remove vbcode
	else
	{
		// simple links
		$find[] = '#\[(email|url)=("??)(.+)\\2\]\\3\[/\\1\]#siU';
		$replace[] = '\3';

		// named links
		if ($showlinks)
		{
			$find[] = '#\[(email|url)=("??)(.+)\\2\](.+)\[/\\1\]#siU';
			$replace[] = '\4 (\3)';
		}
		else
		{
			$find[] = '#\[(email|url)=("??)(.+)\\2\](.+)\[/\\1\]#siU';
			$replace[] = '\4';
		}

		// replace links (and quotes if specified) from message
		$message = preg_replace($find, $replace, $message);

		// strip out all other instances of [x]...[/x]
		while (preg_match_all('#\[(\w+?)(?>[^\]]*?)\](.*)(\[/\1\])#siU', $message, $regs))
		{
			foreach ($regs[0] AS $key => $val)
			{
				$message = str_replace($val, $regs[2]["$key"], $message);
			}
		}
		$message = str_replace('[*]', ' ', $message);
	}

	return trim($message);
}

// ###################### Start get_bbarraycookie #######################
function fetch_bbarray_cookie($cookiename, $id)
{
	// gets the value for a array stored in a cookie
	global $_COOKIE;

	$cookie_name = COOKIE_PREFIX . $cookiename; // name of cookie variable
	$cache_name = 'bb_cache_' . $cookiename; // name of cache variable
	global $$cache_name; // internal array for cacheing purposes

	$cookie = &$_COOKIE["$cookie_name"];
	$cache =  &$$cache_name;
	if (isset($cookie) AND !isset($cache))
	{
		$cache = @unserialize(convert_bbarray_cookie($cookie));
	}

	if (isset($cache))
	{
		return $cache["$id"];
	}

}

// ###################### Start set_bbarraycookie #######################
function set_bbarray_cookie($cookiename, $id, $value, $permanent = 0)
{
	// sets the value for a array and sets the cookie
	global $_COOKIE;

	$cookie_name = COOKIE_PREFIX . $cookiename; // name of cookie variable
	$cache_name = 'bb_cache_' . $cookiename; // name of cache variable
	global $$cache_name; // internal array for cacheing purposes

	$cookie = $_COOKIE["$cookie_name"];
	$cache = &$$cache_name;
	if (isset($cookie) AND !isset($cache))
	{
		$cache = @unserialize(convert_bbarray_cookie($cookie));
	}

	$cache["$id"] = $value;

	vbsetcookie($cookiename, convert_bbarray_cookie(serialize($cache), 'set'), $permanent);

}

// ###################### Start convert_bbarraycookie #######################
function convert_bbarray_cookie($cookie, $dir = 'get')
{
	//function replaces all those none safe characters so we dont waste space
	//and attempts to work around the PHP unserialize() issue

	if ($dir == 'set')
	{
		$cookie = str_replace(array('{', '}', ':', ';'), array('-', '_', 'x', 'y'), $cookie);
		// prefix cookie with 32 character hash
		$cookie = md5($cookie . 'VBS4AAFB47') . $cookie;
	}
	else
	{
		$firstpart = substr($cookie, 0, 32);
		$cookie = substr($cookie, 32);
		if (md5($cookie . 'VBS4AAFB47') == $firstpart)
		{
			$cookie = str_replace(array('-', '_', 'x', 'y'), array('{', '}', ':', ';'), $cookie);
		}
		else
		{
			$cookie = '';
		}
	}
	return $cookie;

}

// ###################### Start addslashes_like #######################
function addslashes_like($text)
{
	// this is a version of addslashes that also escapes % and _ for use in the LIKE SQL function
	return str_replace(array('%', '_') , array('\%' , '\_') , addslashes($text));
}

// ###################### Start addslashes_js #######################
function addslashes_js($text, $quotetype = "'")
{
	// this is a version of escapes text ready to go into a JS string quoted by '' not ""
	if ($quotetype == "'")
	{
		// single quotes
		return str_replace(array('\\', '\'', "\n", "\r") , array('\\\\', "\\'","\\n", "\\r") , $text);
	}
	else
	{
		// double quotes
		return str_replace(array('\\', '"', "\n", "\r") , array('\\\\', "\\\"","\\n", "\\r") , $text);
	}
}

// ###################### Start htmlspecialchars_uni #######################
function htmlspecialchars_uni($text)
{
	// this is a version of htmlspecialchars that still allows unicode to function correctly
	$text = preg_replace('/&(?!#[0-9]+;)/si', '&amp;', $text); // translates all non-unicode entities

	return str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $text);
}

// ###################### Start genpostvarsarray #######################
function construct_post_vars_html()
{
	global $_POST;

	if ($_POST['postvars'])
	{
		return '<input type="hidden" name="postvars" value="' . htmlspecialchars_uni($_POST['postvars']) . '" />' . "\n";
	}
	else if (sizeof($_POST) > 0)

	{
		return '<input type="hidden" name="postvars" value="' . htmlspecialchars_uni(serialize($_POST)) . '" />' . "\n";
	}
	else
	{
		return '';
	}
}

// ###################### Start makehiddenvarshtml #######################
function construct_hidden_var_fields($serializedarr)
{
	$temp = unserialize($serializedarr);

	if (!is_array($temp))
	{
		return '';
	}

	$html = '';
	foreach ($temp AS $key => $val)
	{
		if ($key == 'submit' OR $key == 'action' OR $key == 'method')
		{ // reserved in JS
			continue;
		}
		$html .= '<input type="hidden" name="' . htmlspecialchars_uni($key) . '" value="' . htmlspecialchars_uni($val) . '" />' . "\n";
	}

	return $html;
}

// ###################### Start altbgclass #######################
// reads in $bgclass and returns the alternate table class
// $alternate allows us to have multiple classes on one page without them overwriting each other
function exec_switch_bg($alternate = 0)
{
	global $bgclass, $altbgclass;
	static $tempclass;

	if ($tempclass != '')
	{
		$bgclass = $tempclass;
		$tempclass = '';
	}

	if ($alternate > 0)
	{
		$varname = 'bgclass' . $alternate;
		global $$varname;

		if ($$varname == 'alt1')
		{
			$$varname = 'alt2';
			$altbgclass = 'alt1';
		}
		else
		{
			$$varname = 'alt1';
			$altbgclass = 'alt2';
		}
		$tempclass = $bgclass;
		$bgclass = $$varname;
	}
	else
	{
		if ($bgclass == 'alt1')
		{
			$bgclass = 'alt2';
			$altbgclass = 'alt1';
		}
		else
		{
			$bgclass = 'alt1';
			$altbgclass = 'alt2';
		}
	}

	return $bgclass;
}

// ###################### Start getpagenav #######################
// template-based page splitting system from 3dfrontier.com :)
function construct_page_nav($results, $address, $address2 = '')
{

	global $perpage, $pagenumber, $vboptions, $vbphrase, $stylevar, $_REQUEST, $show;

	$curpage = 0;
	$pagenav = '';
	$firstlink = '';
	$prevlink = '';
	$lastlink = '';
	$nextlink = '';

	if ($results <= $perpage)
	{
		$show['pagenav'] = false;
		return '';
	}

	$show['pagenav'] = true;

	$total = vb_number_format($results);
	$totalpages = ceil($results / $perpage);

	$show['prev'] = false;
	$show['next'] = false;
	$show['first'] = false;
	$show['last'] = false;

	if ($pagenumber > 1)
	{
		$prevpage = $pagenumber - 1;
		$prevnumbers = fetch_start_end_total_array($prevpage, $perpage, $results);
		$show['prev'] = true;
	}
	if ($pagenumber < $totalpages)
	{
		$nextpage = $pagenumber + 1;
		$nextnumbers = fetch_start_end_total_array($nextpage, $perpage, $results);
		$show['next'] = true;
	}

	while ($curpage++ < $totalpages)
	{
		if (($curpage <= $pagenumber - $vboptions['pagenavpages'] OR $curpage >= $pagenumber + $vboptions['pagenavpages']) AND $vboptions['pagenavpages'] != 0)
		{
			if ($curpage == 1)
			{
				$firstnumbers = fetch_start_end_total_array(1, $perpage, $results);
				$show['first'] = true;
			}
			if ($curpage == $totalpages)
			{
				$lastnumbers = fetch_start_end_total_array($totalpages, $perpage, $results);
				$show['last'] = true;
			}
		}
		else
		{
			if ($curpage == $pagenumber)
			{
				$numbers = fetch_start_end_total_array($curpage, $perpage, $results);
				eval('$pagenav .= "' . fetch_template('pagenav_curpage') . '";');
			}
			else
			{
				$pagenumbers = fetch_start_end_total_array($curpage, $perpage, $results);
				eval('$pagenav .= "' . fetch_template('pagenav_pagelink') . '";');
			}
		}
	}

	eval('$pagenav = "' . fetch_template('pagenav') . '";');
	return $pagenav;
}

// ###################### Start getStartEndTotal #######################
// returns an array so you can print 'Showing results $arr[first] to $arr[last] of $totalresults'
function fetch_start_end_total_array($pagenumber, $perpage, $total)
{
	$first = $perpage * ($pagenumber - 1);
	$last = $first + $perpage;

	if ($last > $total)
	{
		$last = $total;
	}
	$first++;

	return array('first' => vb_number_format($first), 'last' => vb_number_format($last));
}

// ###################### Start makenavbits #######################
// this function will also set the GLOBAL $pagetitle
// to equal whatever is the last item in the navbits
function construct_navbits($nav_array)
{
	global $pagetitle, $stylevar, $vboptions, $vbphrase, $scriptpath, $url, $show;

	$code = array(
		'breadcrumb' => '',
		'lastelement' => ''
	);

	$lastelement = sizeof($nav_array);
	$counter = 0;

	foreach($nav_array AS $nav_url => $nav_title)
	{
		$pagetitle = $nav_title;

		$elementtype = iif(++$counter == $lastelement, 'lastelement', 'breadcrumb');
		$show['breadcrumb'] = iif($elementtype == 'breadcrumb', true, false);

		if (empty($nav_title))
		{
			continue;
		}

		eval('$code["$elementtype"] .= "' . fetch_template('navbar_link') . '";');
	}

	$scriptpath = htmlspecialchars_uni($scriptpath);

	return $code;

}

// ###################### Start print output #######################
function print_output($vartext, $sendheader = 1)
{
	global $pagestarttime, $query_count, $querytime, $DB_site, $bbuserinfo;
	global $vbphrase, $vboptions, $stylevar, $_REQUEST;

	if ($vboptions['addtemplatename'])
	{
		if ($doctypepos = strpos($vartext, $stylevar['htmldoctype']))
		{
			$comment = substr($vartext, 0, $doctypepos);
			$vartext = substr($vartext, $doctypepos + strlen($stylevar['htmldoctype']));
			$vartext = $stylevar['htmldoctype'] . "\n" . $comment . $vartext;
		}
	}

	if (DB_QUERIES)
	{
		$pageendtime = microtime();

		$starttime = explode(' ', $pagestarttime);
		$endtime = explode(' ', $pageendtime);

		$totaltime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];

		$vartext .= "<!-- Page generated in " . vb_number_format($totaltime, 5) . " seconds with $query_count queries -->";
	}

	// ####################################################################
	// temporary code
	global $_TEMPLATEQUERIES, $tempusagecache, $DEVDEBUG, $_SERVER, $debug;
	if ($debug)
	{
		$pageendtime = microtime();
		$starttime = explode(' ', $pagestarttime);
		$endtime = explode(' ', $pageendtime);
		$totaltime = vb_number_format($endtime[0] - $starttime[0] + $endtime[1] - $starttime[1], 5);
		devdebug('php_sapi_name(): ' . SAPI_NAME);

		$messages = '';
		if (is_array($DEVDEBUG))
		{
			foreach($DEVDEBUG AS $debugmessage)
			{
				$messages .= "\t<option>" . htmlspecialchars_uni($debugmessage) . "</option>\n";
			}
		}

		$debughtml =  '<hr />';
		//$debughtml .= "<ul><a href=\"#\" onclick=\"set_cookie('vbulletin_collapse', ''); window.location=window.location\">vbulletin_collapse</a>:<br /><li>" . str_replace("\n", '</li><li>', $_COOKIE['vbulletin_collapse']) . "</li></ul>";
		$debughtml .= "\n<form name=\"debugger\" action=\"\">\n<div align=\"center\">\n<!--querycount-->Executed <b>$query_count</b> queries<!--/querycount-->" . iif($_TEMPLATEQUERIES, " (<b>" . sizeof($_TEMPLATEQUERIES) . "</b> queries for uncached templates)", '') . " . ";
		$debughtml .= " (<a href=\"" . htmlspecialchars_uni(SCRIPTPATH) . iif(strpos(SCRIPTPATH, '?') === false, '?', '&amp;') . "tempusage=1\">Template Usage</a>) (<a href=\"" . htmlspecialchars_uni(SCRIPTPATH) . iif(strpos(SCRIPTPATH, '?') === false, '?', '&amp;') . "explain=1\">Explain</a>)<br />\n";
		$debughtml .= "<select>\n\t<option>(Page Generated in $totaltime Seconds)</option>\n$messages</select>\n";

		if (is_array($tempusagecache))
		{
			global $vbcollapse;
			$debughtml .= "\n<br /><br />\n<table class=\"tborder\" cellpadding=\"3\" cellspacing=\"1\" align=\"center\" width=\"40%\">\n";
			$debughtml .= "<thead><tr align=\"$stylevar[left]\"><td class=\"thead\"><a style=\"float:$stylevar[right]\" href=\"#\" onclick=\"return toggle_collapse('templateusage')\"><img src=\"$stylevar[imgdir_button]/collapse_thead$vbcollapse[collapseimg_templateusage].gif\" alt=\"\" border=\"0\" /></a>Template Usage</td></tr></thead><tbody id=\"collapseobj_templateusage\" style=\"$vbcollapse[collapseobj_templateusage]\">\n";
			$hiddentemps = '';

			ksort($tempusagecache);
			foreach ($tempusagecache AS $tempname => $times)
			{
				$debughtml .= "<tr><td class=\"alt1\" align=\"$stylevar[left]\"><span class=\"smallfont\">" . iif($_TEMPLATEQUERIES["$tempname"], "<font color=\"red\"><b>$tempname</b></font>", $tempname) . " ($times)</span></td></tr>\n";
				$hiddentemps .= "'$tempname',\n";
			}

			$debughtml .= "</tbody></table>\n\n<!--\n$hiddentemps-->\n";
			$debughtml .= "</div>\n</form>";
		}
	}
	else
	{
		$debughtml = '';
	}

	if ($debug AND $debughtml != '')
	{
		$vartext = str_replace('</body>', "<!--start debug html-->$debughtml<!--end debug html-->\n</body>", $vartext);
	}


	// end temporary code
	// ####################################################################

	$output = process_replacement_vars($vartext, $sendheader);

	if ($debug AND function_exists('memory_get_usage'))
	{
		$output = preg_replace('#(<!--querycount-->Executed <b>\d+</b> queries<!--/querycount-->)#siU', 'Memory Usage: <strong>' . number_format((memory_get_usage() / 1024)) . 'KB</strong>, \1', $output);
	}

	// parse PHP include ##################
	if (!is_demo_mode())
	{
		eval(fetch_template('phpinclude_end', -1, 0));
	}

	if ($vboptions['gzipoutput'] AND !headers_sent())
	{
		$output = fetch_gzipped_text($output, $vboptions['gziplevel']);
	}

	if ($sendheader AND !@ini_get('zlib.output_compression'))
	{
		@header('Content-Length: ' . strlen($output));
	}

	// show regular page
	if (!DB_QUERIES)
	{
		echo $output;
	}
	// show explain
	else
	{
		echo "\n<b>Page generated in $totaltime seconds with $query_count queries,\nspending $querytime doing MySQL queries and " . ($totaltime - $querytime) . " doing PHP things.\n\n<hr />Shutdown Queries:</b><hr />\n\n";
	}

	if (NOSHUTDOWNFUNC)
	{
		exec_shut_down();
	}

	// broken if zlib.output_compression is on with Apache 2
	if (SAPI_NAME != 'apache2handler' AND SAPI_NAME != 'apache2filter')
	{
		flush();
	}
	exit;
}

// ###################### Start dovars #######################
function process_replacement_vars($newtext, $sendheader = 1)
{
	// parses replacement vars

	global $DB_site, $vboptions, $style, $stylevar, $newpmmsg, $_SERVER, $debug;
	static $replacementvars;

	if (connection_status())
	{
		exit;
	}

	// do vBulletin 3 replacement variables
	if (!empty($style['replacements']))
	{
		if (!isset($replacementvars))
		{
			$replacementvars = unserialize($style['replacements']);
		}

		// this is WAY too slow!
		//$newtext = strtr($newtext, $replacementvars);

		// using str_replace() has case-sensitivity issues...
		//$newtext = str_replace(array_keys($replacementvars), $replacementvars, $newtext);

		// this is slower than str_replace() but is case-insensitive, so we'll use it.
		$newtext = preg_replace(array_keys($replacementvars), $replacementvars, $newtext);
	}

	return $newtext;
}

// ###################### Start show_nopermission #######################
function print_no_permission()
{
	global $vboptions, $logincode, $url, $bbuserinfo, $session, $stylevar;

	// generate 'logged in as:' box or username and pwd box
	if (!$logincode)
	{
		$logincode = construct_login_code();
	}

	$postvars = construct_post_vars_html();

	$bbuserinfo['badlocation'] = 1; // Used by exec_shut_down();

	if ($bbuserinfo['userid'])
	{
		eval(print_standard_error('nopermission_loggedin', true));
	}
	else
	{
		$scriptpath = htmlspecialchars_uni(SCRIPTPATH);

		define('VB_ERROR_PERMISSION', true);

		eval(print_standard_error('nopermission_loggedout', false));
	}
}

// ###################### Start getstandardredirect #######################
function print_standard_redirect($redir_phrase, $doquery = 1)
{
	// call this within an eval to display a standard redirect
	// $GLOBALS['url'] should include the url to be redirected to

	if ($doquery)
	{
		return 'standard_redirect("' . fetch_phrase($redir_phrase, PHRASETYPEID_REDIRECT, 'redirect_') . '", "$url");';
	}
	else
	{
		return 'standard_redirect("' . $redir_phrase . '", "$url");';
	}

}

// ###################### Start getstandarderror #######################
function print_standard_error($err_phrase, $doquery = 1, $savebadlocation = 1)
{
	// set $savebadlocation to 0 to not have WOL track this call to standarderror as an ERROR condition
	// call this within an eval to display a standard error

	if ($doquery)
	{
		return 'standard_error("' . fetch_phrase($err_phrase, PHRASETYPEID_ERROR, 'error_') . "\", '', $savebadlocation);";
	}
	else
	{
		return 'standard_error("' . $err_phrase . "\", '', $savebadlocation);";
	}
}

// ###################### Start standarderror #######################
function standard_error($error = '', $headinsert = '', $savebadlocation = 1)
{
	// print standard error page
	global $header, $footer, $headinclude, $forumjump, $timezone;
	global $vboptions, $vbphrase, $stylevar, $session, $logincode, $bbuserinfo;
	global $_POST, $pmbox, $vbphrase, $_USEROPTIONS, $show, $scriptpath;

	construct_forum_jump();

	$title = $vboptions['bbtitle'];
	$pagetitle = &$title;
	$errormessage = $error;

	if (!$bbuserinfo['badlocation'] AND $savebadlocation)
	{
		$bbuserinfo['badlocation'] = 3;
	}

	if (defined('VB_ERROR_PERMISSION') AND VB_ERROR_PERMISSION == true)
	{
		$show['permission_error'] = true;
	}
	else
	{
		$show['permission_error'] = false;
	}

	if (defined('VB_ERROR_LITE') AND VB_ERROR_LITE == true)
	{
		$navbits = $navbar = '';
		$templatename = 'STANDARD_ERROR_LITE';

		unset($shutdownqueries['pmpopup']); // we aren't going to get a PM popup here because we don't have the footer
	}
	else
	{
		$navbits = construct_navbits(array('' => $vbphrase['vbulletin_message']));
		eval('$navbar = "' . fetch_template('navbar') . '";');
		$templatename = 'STANDARD_ERROR';
	}

	eval('print_output("' . fetch_template($templatename) . '");');
	exit;

}

// ###################### Start standardredirect #######################
function standard_redirect($message = '', $url = '')
{
	// print standard redirect page

	global $header, $footer, $headinclude, $forumjump;
	global $timezone, $vboptions, $vbphrase, $stylevar, $session, $logincode;
	global $_POST, $postvars, $formfile, $_REQUEST, $_SERVER, $_ENV;

	if ($vboptions['useheaderredirect'] AND !$_REQUEST['forceredirect'] AND !headers_sent() AND !$postvars)
	{
		exec_header_redirect($url);
	}

	$title = $vboptions['bbtitle'];

	$pagetitle = $title;
	$errormessage = $message;

	$url = str_replace(chr(0), '', $url);
	$url = str_replace('&amp;', '&', $url); //moved here to stop the htmlspecialchars_uni() later having a negative effect

	// make sure the url is a full, valid url
	if (!preg_match('#^[a-z]+://#i', $url))
	{
		// make sure we get the correct value from a multitude of server setups
		if ($_SERVER['HTTP_HOST'] OR $_ENV['HTTP_HOST'])
		{
			$http_host = iif($_SERVER['HTTP_HOST'], $_SERVER['HTTP_HOST'], $_ENV['HTTP_HOST']);
		}
		else if ($_SERVER['SERVER_NAME'] OR $_ENV['SERVER_NAME'])
		{
			$http_host = iif($_SERVER['SERVER_NAME'], $_SERVER['SERVER_NAME'], $_ENV['SERVER_NAME']);
		}

		// if we don't have this, then we're not going to redirect correctly,
		// so let's assume that we're going to be OK with just a relative URL
		if ($http_host = trim($http_host))
		{
			$method = iif($_SERVER['SERVER_PORT'] == 443, 'https://', 'http://');

			if ($url{0} != '/')
			{
				if (($dirpath = dirname(SCRIPT)) != '/')
				{
					if ($dirpath == '\\')
					{
						$dirpath = '/';
					}
					else
					{
						$dirpath .= '/';
					}
				}
			}
			else
			{
				$dirpath = '';
			}
			$dirpath .= $url;

			$url = $method . $http_host . $dirpath;
		}
	}

	$js_url = addslashes(str_replace('"', '&quot;', $url));
	$url = htmlspecialchars_uni($url); // make sure that no XSS can be done by breaking out of the HTML tags

	$url = preg_replace(
		array('/&#0*59;?/', '/&#x0*3B;?/i', '#;#'),
		'%3B',
		$url
	);
	$url = preg_replace('#&amp%3B#i', '&amp;', $url);

	unset($shutdownqueries['pmpopup']); // we aren't going to get a PM popup here because we don't have the footer

	eval('print_output("' . fetch_template('STANDARD_REDIRECT') . '");');
	exit;

}

// ###################### Start cachetemplates #######################
function cache_templates($templates, $templateidlist)
{
	// $templateslist: comma delimited list
	global $templatecache, $DB_site, $templateassoc, $vboptions;

	if (empty($templateassoc))
	{
		$templateassoc = unserialize($templateidlist);
	}

	if ($vboptions['legacypostbit'] AND in_array('postbit', $templates))
	{
		$templateassoc['postbit'] = $templateassoc['postbit_legacy'];
	}

	foreach ($templates AS $template)
	{
		$templateids[] = intval($templateassoc["$template"]);
	}

	if (!empty($templateids))
	{
		// run query
		$temps = $DB_site->query("
			SELECT title, template
			FROM " . TABLE_PREFIX . "template
			WHERE templateid IN (" . implode(',', $templateids) . ")
		");

		// cache templates
		while ($temp = $DB_site->fetch_array($temps))
		{
			if (empty($templatecache["$temp[title]"]))
			{
				$templatecache["$temp[title]"] = $temp['template'];
			}
		}
		$DB_site->free_result($temps);
	}

}

// ###################### Start gettemplate #######################
function fetch_template($templatename, $escape = 0, $gethtmlcomments = 1)
{
	// gets a template from the db or from the local cache
	global $templatecache, $DB_site, $vboptions, $style;
	global $tempusagecache, $templateassoc;

	// use legacy postbit if necessary
	if ($vboptions['legacypostbit'] AND $templatename == 'postbit')
	{
		$templatename = 'postbit_legacy';
	}

	if (isset($templatecache["$templatename"]))
	{
		$template = $templatecache["$templatename"];
	}
	else
	{
		DEVDEBUG("Uncached template: $templatename");
		$GLOBALS['_TEMPLATEQUERIES']["$templatename"] = true;

		$fetch_tid = intval($templateassoc["$templatename"]);
		if (!$fetch_tid)
		{
			$gettemp = array('template' => '');
		}
		else
		{
			$gettemp = $DB_site->query_first("
				SELECT template
				FROM " . TABLE_PREFIX . "template
				WHERE templateid = $fetch_tid
			");
		}
		$template = $gettemp['template'];
		$templatecache["$templatename"] = $template;
	}

	// **************************
	/*
	if ($template == '<<< FILE >>>')
	{
		$template = addslashes(implode('', file("./templates/$templatename.html")));
		$templatecache["$templatename"] = $template;
	}
	*/
	// **************************

	switch($escape)
	{
		case 1:
			// escape template
			$template = addslashes($template);
			$template = str_replace("\\'", "'", $template);
			break;

		case -1:
			// unescape template
			$template = stripslashes($template);
			break;
	}

	$tempusagecache["$templatename"]++;

	if ($vboptions['addtemplatename'] AND $gethtmlcomments)
	{
		$templatename = preg_replace('#[^a-z0-9_]#i', '', $templatename);
		return "<!-- BEGIN TEMPLATE: $templatename -->\n$template\n<!-- END TEMPLATE: $templatename -->";
	}

	return $template;
}

// ###################### Start makeforumjump #######################
function construct_forum_jump($parentid = -1, $addbox = 1, $prependchars = '', $permission = '')
{
	global $DB_site, $optionselected, $usecategories, $jumpforumid, $jumpforumtitle, $jumpforumbits, $curforumid, $daysprune;
	global $vboptions, $stylevar, $vbphrase, $defaultselected, $forumjump, $bbuserinfo, $selectedone, $session, $_FORUMOPTIONS;
	global $frmjmpsel; // allows context sensitivity for non-forum areas
	global $iforumcache, $forumcache, $gobutton, $permissions;

	if (!isset($iforumcache))
	{
		require_once('./includes/functions_forumlist.php');
		// cache_ordered_forums is probably going to be moved back to functions.php

		// get the iforumcache, as we use it all over the place, not just for forumjump
		cache_ordered_forums(1, 1);
	}

	if (!$vboptions['useforumjump'])
	{
		return;
	}

	if (empty($iforumcache["$parentid"]) OR !is_array($iforumcache["$parentid"]))
	{
		return;
	}

	foreach($iforumcache["$parentid"] AS $holder)
	{
		foreach($holder AS $forumid)
		{

			//$GLOBALS['_permsgetter_'] = 'makeforumjump';
			//$forumperms = fetch_permissions($forum['forumid']);
			$forumperms = $bbuserinfo['forumpermissions']["$forumid"];
			if ((!($forumperms & CANVIEW) AND $vboptions['hideprivateforums']) OR !($forumcache["$forumid"]['options'] & $_FORUMOPTIONS['showonforumjump']) OR !$forumcache["$forumid"]['displayorder'] OR !($forumcache["$forumid"]['options'] & $_FORUMOPTIONS['active']))
			{
				continue;
			}
			else
			{
				// set $forum from the $forumcache
				$forum = $forumcache["$forumid"];

				$optionvalue = $forumid;
				$optiontitle = $prependchars . " $forum[title]";

				$optionclass = 'fjdpth' . iif($forum['depth'] > 4, 4, $forum['depth']);

				if ($curforumid == $optionvalue)
				{
					$optionselected = HTML_SELECTED;
					$optionclass = 'fjsel';
					$selectedone = 1;
				}
				else
				{
					$optionselected = '';
				}

				eval('$jumpforumbits .= "' . fetch_template('option') . '";');

				construct_forum_jump($optionvalue, 0, $prependchars . FORUM_PREPEND, $forumperms);

			} // if can view

		} // end foreach ($holder as $forum)
	} // end foreach ($iforumcache[$parentid] as $holder)

	if ($addbox)
	{
		if ($selectedone != 1)
		{
			$defaultselected = HTML_SELECTED;
		}
		if (!is_array($frmjmpsel))
		{
			$frmjmpsel = array();
		}
		if (empty($daysprune))
		{
			$daysprune = '';
		}
		else
		{
			$daysprune = intval($daysprune);
		}
		eval('$forumjump = "' . fetch_template('forumjump') . '";');
	}
}

// ###################### Start gzipoutput #######################
function fetch_gzipped_text($text, $level = 1)
{
	global $_SERVER, $nozip;

	$returntext = $text;

	if (function_exists('crc32') AND function_exists('gzcompress') AND !$nozip)
	{
		if (strpos(' ' . $_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false)
		{
			$encoding = 'x-gzip';
		}
		if (strpos(' ' . $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)
		{
			$encoding = 'gzip';
		}

		if ($encoding)
		{
			header('Content-Encoding: ' . $encoding);

			if (false AND function_exists('gzencode') AND PHP_VERSION > '4.2')
			{
				$returntext = gzencode($text, $level);
			}
			else
			{
				$size = strlen($text);
				$crc = crc32($text);

				$returntext = "\x1f\x8b\x08\x00\x00\x00\x00\x00\x00\xff";
				$returntext .= substr(gzcompress($text, $level), 2, -4);
				$returntext .= pack('V', $crc);
				$returntext .= pack('V', $size);
			}
		}
	}
	return $returntext;
}

// ###################### Start vbheaders_sent #######################
function vbheaders_sent(&$filename, &$linenum)
{
	if (PHP_VERSION > '4.3.0')
	{
		return headers_sent($filename, $linenum);
	}
	else
	{
		return headers_sent();
	}
}

// ###################### Start vbsetcookie #######################
function vbsetcookie($name, $value = '', $permanent = 1, $httponly = false)
{
	global $vboptions, $_SERVER;

	if ($permanent)
	{
		$expire = TIMENOW + 60 * 60 * 24 * 365;
	}
	else
	{
		$expire = 0;
	}

	if ($_SERVER['SERVER_PORT'] == '443')
	{
		// we're using SSL
		$secure = 1;
	}
	else
	{
		$secure = 0;
	}

	// IE for Mac doesn't support httponly
	$httponly = (($httponly AND (is_browser('ie') AND is_browser('mac'))) ? false : $httponly);

	$name = COOKIE_PREFIX . $name;

	$filename = 'N/A';
	$linenum = 0;

	if (!vbheaders_sent($filename, $linenum))
	{ // consider showing an error message if there not sent using above variables?
		if ($value == '' AND strlen($vboptions['cookiepath']) > 1 AND strpos($vboptions['cookiepath'], '/') !== false)
		{
			// this will attempt to unset the cookie at each directory up the path.
			// ie, cookiepath = /test/vb3/. These will be unset: /, /test, /test/, /test/vb3, /test/vb3/
			// This should hopefully prevent cookie conflicts when the cookie path is changed.
			$dirarray = explode('/', preg_replace('#/+$#', '', $vboptions['cookiepath']));
			$alldirs = '';
			foreach ($dirarray AS $thisdir)
			{
				$alldirs .= "$thisdir";
				if (!empty($thisdir))
				{ // try unsetting without the / at the end
					exec_vbsetcookie($name, $value, $expire, $alldirs, $vboptions['cookiedomain'], $secure, $httponly);
				}
				$alldirs .= "/";
				exec_vbsetcookie($name, $value, $expire, $alldirs, $vboptions['cookiedomain'], $secure, $httponly);
			}
		}
		else
		{
			exec_vbsetcookie($name, $value, $expire, $vboptions['cookiepath'], $vboptions['cookiedomain'], $secure, $httponly);
		}
	}
	else if (!DB_QUERIES)
	{ //show some sort of error message
		global $templateassoc, $DB_site;
		if (empty($templateassoc))
		{
			// this is being called before templates have been cached, so just get the default one
			$template = $DB_site->query_first("
				SELECT templateid
				FROM " . TABLE_PREFIX . "template
				WHERE title = 'STANDARD_ERROR' AND styleid = -1
			");
			$templateassoc = array('STANDARD_ERROR' => $template['templateid']);
		}
		eval(print_standard_error('cant_set_cookies'));
	}
}

// #############################################################################
/**
* Calls PHP's setcookie() or sends raw headers if 'httponly' is required.
* Should really only be called through vbsetcookie()
*
* @param	string	Name
* @param	string	Value
* @param	int		Expire
* @param	string	Path
* @param	string	Domain
* @param	boolean	Secure
* @param	boolean	HTTP only
*
* @return	boolean	True on success
*/
function exec_vbsetcookie($name, $value, $expires, $path = '', $domain = '', $secure = false, $httponly = false)
{
	if ($httponly AND $value)
	{
		// cookie names and values may not contain any of the characters listed
		foreach (array(",", ";", " ", "\t", "\r", "\n", "\013", "\014") AS $bad_char)
		{
			if (strpos($name, $bad_char) !== false OR strpos($value, $bad_char) !== false)
			{
				return false;
			}
		}

		// name and value
		$cookie = "Set-Cookie: $name=" . urlencode($value);

		// expiry
		$cookie .= ($expires > 0 ? '; expires=' . gmdate("D, d-M-Y H:i:s T", $expires) : '');

		// path
		$cookie .= ($path ? "; path=$path" : '');

		// domain
		$cookie .= ($domain ? "; domain=$domain" : '');

		// secure
		$cookie .= ($secure ? '; secure' : '');

		// httponly
		$cookie .= ($httponly ? '; httponly' : '');

		header($cookie, false);
		return true;
	}
	else
	{
		return setcookie($name, $value, $expires, $path, $domain, $secure);
	}
}

// ##################### Start fetch_time_data ######################
function fetch_time_data()
{
	global $bbuserinfo, $vboptions, $timediff, $datenow, $timenow, $copyrightyear;

	$bbuserinfo['tzoffset'] = $bbuserinfo['timezoneoffset']; // preserve timzoneoffset for profile editing and proper event display

	if ($bbuserinfo['dstonoff'])
	{
		// DST is on, add an hour
		$bbuserinfo['tzoffset']++;

		if (substr($bbuserinfo['tzoffset'], 0, 1) != '-')
		{
			// recorrect so that it has + sign, if necessary
			$bbuserinfo['tzoffset'] = '+' . $bbuserinfo['tzoffset'];
		}
	}

	// some stuff for the gmdate bug
	$vboptions['hourdiff'] = (date('Z', TIMENOW) / 3600 - $bbuserinfo['tzoffset']) * 3600;

	if ($bbuserinfo['tzoffset'])
	{
		if ($bbuserinfo['tzoffset'] > 0 AND strpos($bbuserinfo['tzoffset'], '+') === false)
		{
			$bbuserinfo['tzoffset'] = '+' . $bbuserinfo['tzoffset'];
		}
		if (abs($bbuserinfo['tzoffset']) == 1)
		{
			$timediff = " $bbuserinfo[tzoffset] hour";
		}
		else
		{
			$timediff = " $bbuserinfo[tzoffset] hours";
		}
	}
	else
	{
		$timediff = '';
	}

	$datenow       = vbdate($vboptions['dateformat'], TIMENOW);
	$timenow       = vbdate($vboptions['timeformat'], TIMENOW);
	$copyrightyear = vbdate('Y', TIMENOW, false, false);
}

// If vbdate() is called with a $format other than than one in $vboptions[], set $locale to false unless you
// dynamically set the date() and strftime() formats in the vbdate() call.
function vbdate($format, $timestamp = TIMENOW, $doyestoday = false, $locale = true, $adjust = true, $gmdate = false)
{
	global $bbuserinfo, $vboptions, $vbphrase;

	$hourdiff = $vboptions['hourdiff'];

	if ($bbuserinfo['lang_locale'] AND $locale)
	{
		if ($gmdate)
		{
			$datefunc = 'gmstrftime';
		}
		else
		{
			$datefunc = 'strftime';
		}
	}
	else
	{
		if ($gmdate)
		{
			$datefunc = 'gmdate';
		}
		else
		{
			$datefunc = 'date';
		}
	}
	if (!$adjust)
	{
		$hourdiff = 0;
	}
	$timestamp_adjusted = max(0, $timestamp - $hourdiff);

	if ($format == $vboptions['dateformat'] AND $doyestoday AND $vboptions['yestoday'])
	{
		if ($vboptions['yestoday'] == 1)
		{
			if (!defined('TODAYDATE'))
			{
				define ('TODAYDATE', vbdate('n-j-Y', TIMENOW, false, false));
				define ('YESTDATE', vbdate('n-j-Y', TIMENOW - 86400, false, false));
				define ('TOMDATE', vbdate('n-j-Y', TIMENOW + 86400, false, false));
	   		}

			$datetest = @date('n-j-Y', $timestamp - $hourdiff);

			if ($datetest == TODAYDATE)
			{
				$returndate = $vbphrase['today'];
			}
			else if ($datetest == YESTDATE)
			{
				$returndate = $vbphrase['yesterday'];
			}
			else
			{
				$returndate = $datefunc($format, $timestamp_adjusted);
			}
		}
		else
		{
			$timediff = TIMENOW - $timestamp;

			if ($timediff < 3600)
			{
				if ($timediff < 120)
				{
					$returndate = $vbphrase['1_minute_ago'];
				}
				else
				{
					$returndate = construct_phrase($vbphrase['x_minutes_ago'], intval($timediff / 60));
				}
			}
			else if ($timediff < 7200)
			{
				$returndate = $vbphrase['1_hour_ago'];
			}
			else if ($timediff < 86400)
			{
				$returndate = construct_phrase($vbphrase['x_hours_ago'], intval($timediff / 3600));
			}
			else if ($timediff < 172800)
			{
				$returndate = $vbphrase['1_day_ago'];
			}
			else if ($timediff < 604800)
			{
				$returndate = construct_phrase($vbphrase['x_days_ago'], intval($timediff / 86400));
			}
			else if ($timediff < 1209600)
			{
				$returndate = $vbphrase['1_week_ago'];
			}
			else if ($timediff < 3024000)
			{
				$returndate = construct_phrase($vbphrase['x_weeks_ago'], intval($timediff / 604900));
			}
			else
			{
				$returndate = $datefunc($format, $timestamp_adjusted);
			}
		}

		return $returndate;
	}
	else
	{
		return $datefunc($format, $timestamp_adjusted);
	}
}

// ###################### Start makelogincode #######################
function construct_login_code()
{
	global $DB_site, $bbuserinfo, $session, $stylevar, $_POST, $vboptions, $vbphrase;

	if ($bbuserinfo['userid'] == 0)
	{
		eval('$logincode = "' . fetch_template('username_loggedout') . '";');
	}
	else
	{
		eval('$logincode = "' . fetch_template('username_loggedin') . '";');
	}

	return $logincode;
}

// ###################### Start unhtmlspecialchars #######################
function unhtmlspecialchars($text, $doUniCode = false)
{
	if ($doUniCode)
	{
		$text = preg_replace('/&#([0-9]+);/esiU', "convert_int_to_utf8('\\1')", $text);
	}

	return str_replace(array('&lt;', '&gt;', '&quot;', '&amp;'), array('<', '>', '"', '&'), $text);
}

// ###################### Start convert_int_to_utf8 #######################
function convert_int_to_utf8($intval)
{
	$intval = intval($intval);
	switch ($intval)
	{
		// 1 byte, 7 bits
		case 0:
			return chr(0);
		case ($intval & 0x7F):
			return chr($intval);

		// 2 bytes, 11 bits
		case ($intval & 0x7FF):
			return chr(0xC0 | (($intval >> 6) & 0x1F)) .
				chr(0x80 | ($intval & 0x3F));

		// 3 bytes, 16 bits
		case ($intval & 0xFFFF):
			return chr(0xE0 | (($intval >> 12) & 0x0F)) .
				chr(0x80 | (($intval >> 6) & 0x3F)) .
				chr (0x80 | ($intval & 0x3F));

		// 4 bytes, 21 bits
		case ($intval & 0x1FFFFF):
			return chr(0xF0 | ($intval >> 18)) .
				chr(0x80 | (($intval >> 12) & 0x3F)) .
				chr(0x80 | (($intval >> 6) & 0x3F)) .
				chr(0x80 | ($intval & 0x3F));
	}
}

// ###################### Start DEVDEBUG #######################
function DEVDEBUG($text = '')
{
	if ($GLOBALS['debug'])
	{
		$GLOBALS['DEVDEBUG'][] = $text;
	}
}

// ###################### Start exec headers #######################
function exec_headers($headers = 1, $nocache = 1)
{
	global $vboptions, $noheader, $bbuserinfo;

	$sendcontent = true;
	if ($vboptions['addheaders'] AND !$noheader AND $headers)
	{
		// default headers
		if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
		{
			header('Status: 200 OK');
		}
		else
		{
			header('HTTP/1.1 200 OK');
		}
		@header('Content-Type: text/html' . iif($bbuserinfo['lang_charset'] != '', "; charset=$bbuserinfo[lang_charset]"));
		$sendcontent = false;
	}

	if ($vboptions['nocacheheaders'] AND !$noheader AND $nocache)
	{
		// no caching
		exec_nocache_headers($sendcontent);
	}
	else if (!$noheader)
	{
		@header("Cache-Control: private");
		@header("Pragma: private");
		if ($sendcontent)
		{
			@header('Content-Type: text/html' . iif($bbuserinfo['lang_charset'] != '', "; charset=$bbuserinfo[lang_charset]"));
		}
	}
}

// ###################### Start exec nocache headers #######################
function exec_nocache_headers($sendcontent = true)
{
	global $bbuserinfo;
	static $sentheaders;

	if (!$sentheaders)
	{
		@header("Expires: 0"); // Date in the past
		#@header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
		#@header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
		@header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", false);
		@header("Pragma: no-cache"); // HTTP/1.0
		if ($sendcontent)
		{
			@header('Content-Type: text/html' . iif($bbuserinfo['lang_charset'] != '', "; charset=$bbuserinfo[lang_charset]"));
		}
	}

	$sentheaders = true;
}

// ###################### Start exec_header_redirect #######################
function exec_header_redirect($url)
{
	global $shutdownqueries, $_SERVER, $_ENV;

	// enforces HTTP 1.1 compliance
	if (!preg_match('#^[a-z]+://#i', $url))
	{
		// make sure we get the correct value from a multitude of server setups
		if ($_SERVER['HTTP_HOST'] OR $_ENV['HTTP_HOST'])
		{
			$http_host = iif($_SERVER['HTTP_HOST'], $_SERVER['HTTP_HOST'], $_ENV['HTTP_HOST']);
		}
		else if ($_SERVER['SERVER_NAME'] OR $_ENV['SERVER_NAME'])
		{
			$http_host = iif($_SERVER['SERVER_NAME'], $_SERVER['SERVER_NAME'], $_ENV['SERVER_NAME']);
		}

		// if we don't have this, then we're not going to redirect correctly,
		// so let's assume that we're going to be OK with just a relative URL
		if ($http_host = trim($http_host))
		{
			$method = iif($_SERVER['SERVER_PORT'] == 443, 'https://', 'http://');

			if ($url{0} != '/')
			{
				if (($dirpath = dirname(SCRIPT)) != '/')
				{
					if ($dirpath == '\\')
					{
						$dirpath = '/';
					}
					else
					{
						$dirpath .= '/';
					}
				}
			}
			else
			{
				$dirpath = '';
			}
			$dirpath .= $url;

			$url = $method . $http_host . $dirpath;
		}
	}

	$url = str_replace('&amp;', '&', $url); // prevent possible oddity

	if (strpos($url, "\r\n") !== false)
	{
		trigger_error("Header may not contain more than a single header, new line detected.", E_USER_ERROR);
	}

	header("Location: $url");
	unset($shutdownqueries['pmpopup']);

	if (NOSHUTDOWNFUNC)
	{
		exec_shut_down();
	}

	exit;
}

// ###################### Start checkforumpwd #######################
function verify_forum_password($forumid, $password, $showerror = true)
{
	global $permissions, $bbuserinfo, $stylevar, $scriptpath;

	if (!$password OR ($permissions['adminpermissions'] & CANCONTROLPANEL) OR ($permissions['adminpermissions'] & ISMODERATOR) OR can_moderate($forumid))
	{
		return true;
	}

	$foruminfo = fetch_foruminfo($forumid);
	$parents = explode(',', $foruminfo['parentlist']);
	foreach ($parents AS $fid)
	{ // get the pwd from any parent forums -- allows pwd cookies to cascade down
		if ($temp = fetch_bbarray_cookie('forumpwd', $fid) AND $temp === md5($bbuserinfo['userid'] . $password))
		{
			return true;
		}
	}

	// didn't match the password in any cookie
	if ($showerror)
	{
		// forum password is bad - show error
		$postvars = construct_post_vars_html();
		eval(print_standard_error('error_forumpasswordmissing'));
	}
	else
	{
		// forum password is bad - return false
		return false;
	}
}

// ###################### Start bits2array #######################
// takes a bitfield and the array describing the resulting fields
function convert_bits_to_array(&$bitfield, $_FIELDNAMES)
{
	$bitfield = intval($bitfield);
	$arry = array();
	foreach ($_FIELDNAMES AS $field => $bitvalue)
	{
		if ($bitfield & $bitvalue)
		{
			$arry["$field"] = 1;
		}
		else

		{
			$arry["$field"] = 0;
		}
	}
	return $arry;
}

// ###################### Start querypermissions #######################
// returns the full set of permissions for the specified user (called by global or init)
// returns combined usergroup permissions AND all individual forum permissions
function cache_permissions(&$user, $getforumpermissions=true)
{
	global $DB_site, $vboptions, $_BITFIELD, $_INTPERMS, $_SERVER;
	global $usergroupcache, $forumcache, $forumpermissioncache;

	// these are the arrays created by this function
	global $forumcache, $cpermscache, $calendarcache;
	static $accesscache = array();

	$intperms = array();
	$_PERMQUERY = array();

	// set the usergroupid of the user's primary usergroup
	$USERGROUPID = $user['usergroupid'];

	if ($USERGROUPID == 0)
	{ // set a default usergroupid if none is set
		$USERGROUPID = 1;
	}

	// initialise $membergroups - make an array of the usergroups to which this user belongs
	$membergroupids = fetch_membergroupids_array($user);

	// build usergroup permissions
	if (sizeof($membergroupids) == 1 OR !($usergroupcache["$USERGROUPID"]['genericoptions'] & ALLOWMEMBERGROUPS) )
	{
		// if primary usergroup doesn't allow member groups then get rid of them!
		$membergroupids = array($USERGROUPID);

		// just return the permissions for the user's primary group (user is only a member of a single group)
		$user['permissions'] = $usergroupcache["$USERGROUPID"];
	}
	else
	{
		// return the merged array of all user's membergroup permissions (user has additional member groups)
		foreach($membergroupids AS $usergroupid)
		{
			foreach ($_BITFIELD['usergroup'] AS $dbfield => $permfields)
			{
				$user['permissions']["$dbfield"] |= $usergroupcache["$usergroupid"]["$dbfield"];
			}
			foreach ($_INTPERMS AS $dbfield => $precedence)
			{
				// put in some logic to handle $precedence here .. see init.php
				if (!$precedence)
				{
					if ($usergroupcache["$usergroupid"]["$dbfield"] > $intperms["$dbfield"])
					{
						$intperms["$dbfield"] = $usergroupcache["$usergroupid"]["$dbfield"];
					}
				}
				else
				{
					if ($usergroupcache["$usergroupid"]["$dbfield"] == 0 OR (isset($intperms["$dbfield"]) AND $intperms["$dbfield"] == 0)) // Set value to 0 as it overrides all
					{
						$intperms["$dbfield"] = 0;
					}
					else if ($usergroupcache["$usergroupid"]["$dbfield"] > $intperms["$dbfield"])
					{
						$intperms["$dbfield"] = $usergroupcache["$usergroupid"]["$dbfield"];
					}
				}
			}
		}
		$user['permissions'] = array_merge($usergroupcache["$USERGROUPID"], $user['permissions'], $intperms);
	}

	// if we do not need to grab the forum/calendar permissions
	// then just return what we have so far
	if ($getforumpermissions == false)
	{
		return $user['permissions'];
	}

	foreach(array_keys($forumcache) AS $forumid)
	{
		foreach($membergroupids AS $usergroupid)
		{
			$user['forumpermissions']["$forumid"] |= $forumcache["$forumid"]['permissions']["$usergroupid"];
		}
	}

	// do access mask stuff if required
	if ($vboptions['enableaccess'] AND $user['hasaccessmask'] == 1)
	{
		if (empty($accesscache["$user[userid]"]))
		{
			// query access masks
			$_PERMQUERY[3] = "
				SELECT access.*, forum.forumid
				FROM " . TABLE_PREFIX . "forum AS forum
				LEFT JOIN " . TABLE_PREFIX . "access AS access ON (userid = $user[userid] AND FIND_IN_SET(access.forumid, forum.parentlist))
				WHERE NOT (ISNULL(access.forumid))
			";
			$accessmasks = $DB_site->query($_PERMQUERY[3]);
			while ($access = $DB_site->fetch_array($accessmasks))
			{
				$accesscache["$user[userid]"]["$access[forumid]"] = $access['accessmask'];
			}
			unset($access);
			$DB_site->free_result($accessmasks);
		}

		// if an access mask is set for a forum, set the permissions accordingly
		if (!empty($accesscache["$user[userid]"]))
		{
			foreach ($accesscache["$user[userid]"] AS $forumid => $accessmask)
			{
				if ($accessmask == 0) // disable access
				{
					$user['forumpermissions']["$forumid"] = 0;
				}
				else // use combined permissions
				{
					$user['forumpermissions']["$forumid"] = $user['permissions']['forumpermissions'];
				}
			}
		}
	} // end if access masks enabled and is logged in user

	if (!empty($user['membergroupids']))
	{
		$sqlcondition = "IN($USERGROUPID, $user[membergroupids])";
	}
	else
	{
		$sqlcondition = "= $USERGROUPID";
	}


	// query calendar permissions
	if (THIS_SCRIPT == 'online' OR THIS_SCRIPT == 'calendar' OR (THIS_SCRIPT == 'index' AND $vboptions['showevents']))
	{ // Only query calendar permissions when accessing the calendar or subscriptions or index.php
		$_PERMQUERY[4] = "
		SELECT calendarpermission.usergroupid, calendarpermission.calendarpermissions,calendar.calendarid,calendar.title, displayorder
		FROM " . TABLE_PREFIX . "calendar AS calendar
		LEFT JOIN " . TABLE_PREFIX . "calendarpermission AS calendarpermission ON (calendarpermission.calendarid=calendar.calendarid AND usergroupid IN(" . implode(', ', $membergroupids) . "))
		ORDER BY displayorder ASC
		";
		$cpermscache = array();
		$calendarcache = array();
		$displayorder = array();
		$calendarpermissions = $DB_site->query($_PERMQUERY[4]);
		while ($calendarpermission = $DB_site->fetch_array($calendarpermissions))
		{
			$cpermscache["$calendarpermission[calendarid]"]["$calendarpermission[usergroupid]"] = intval($calendarpermission['calendarpermissions']);
			$calendarcache["$calendarpermission[calendarid]"] = $calendarpermission['title'];
			$displayorder["$calendarpermission[calendarid]"] = $calendarpermission['displayorder'];
		}
		unset($calendarpermission);
		$DB_site->free_result($calendarpermissions);

		// Combine the calendar permissions for all member groups
		foreach($cpermscache AS $calendarid => $cpermissions)
		{
			$user['calendarpermissions']["$calendarid"] = 0;
			foreach($membergroupids AS $usergroupid)
			{
				if (!empty($displayorder["$calendarid"]))
				{ // leave permissions at 0 for calendars that aren't being displayed
					if (isset($cpermissions["$usergroupid"]))
					{
						$user['calendarpermissions']["$calendarid"] |= $cpermissions["$usergroupid"];
					}
					else

					{
						$user['calendarpermissions']["$calendarid"] |= $usergroupcache["$usergroupid"]['calendarpermissions'];
					}
				}
			}
		}
	}

	return $user['permissions'];

}

// ###################### Start getpermissions #######################
function fetch_permissions($forumid = 0, $userid = -1, $userinfo = false)
{
	// gets permissions, depending on given userid and forumid
	global $DB_site, $usercache, $bbuserinfo, $vboptions;
	global $permscache, $usergroupcache;

	$userid = intval($userid);
	if ($userid == -1)
	{
		$userid = $bbuserinfo['userid'];
		$usergroupid = $bbuserinfo['usergroupid'];
	}

	// ########## #DEBUG# CODE ##############
	$DEBUG_MESSAGE = iif(isset($GLOBALS['_permsgetter_']), "($GLOBALS[_permsgetter_])", '(unspecified)'). " fetch_permissions($forumid, $userid, $usergroupid,'$parentlist'); ";
	unset($GLOBALS['_permsgetter_']);
	// ########## END #DEBUG# CODE ##############

	if ($userid == $bbuserinfo['userid'])
	{
		// we are getting permissions for $bbuserinfo
		// so return permissions built in querypermissions
		if ($forumid)
		{
			DEVDEBUG($DEBUG_MESSAGE."-> cached fperms for forum $forumid");
			return $bbuserinfo['forumpermissions']["$forumid"];
		}
		else
		{
			DEVDEBUG($DEBUG_MESSAGE.'-> cached combined permissions');
			return $bbuserinfo['permissions'];
		}
	}
	else
	{
	// we are getting permissions for another user...
		if (!is_array($userinfo))
		{
			return 0;
		}
		if ($forumid)
		{
			DEVDEBUG($DEBUG_MESSAGE."-> trying to get forumpermissions for non \$bbuserinfo");
			cache_permissions($userinfo);
			return $userinfo['forumpermissions']["$forumid"];
		}
		else
		{
			DEVDEBUG($DEBUG_MESSAGE."-> trying to get combined permissions for non \$bbuserinfo");
			return cache_permissions($userinfo, false);
		}
	}

}

// ###################### Start getmodpermissions #######################
function fetch_moderator_permissions($forumid, $userid = -1)
{
	// gets permissions, depending on given userid and forumid
	global $DB_site, $bbuserinfo, $imodcache;
	static $modpermscache;

	if ($userid == -1)
	{
		$userid = $bbuserinfo['userid'];
	}

	if (isset($modpermscache["$forumid"]["$userid"]))
	{
		DEVDEBUG("  CACHE \$modpermscache cache result");
		return $modpermscache["$forumid"]["$userid"];
	}

	if (isset($imodcache))
	{
		if (isset($imodcache["$forumid"]["$userid"]))
		{
			DEVDEBUG("  CACHE first result from imodcache");
			$getperms = $imodcache["$forumid"]["$userid"];
		}
		else
		{
			$parentlist = explode(',', fetch_forum_parent_list($forumid));
			foreach($parentlist AS $parentid)
			{
				if (isset($imodcache["$parentid"]["$userid"]))
				{
					DEVDEBUG("  CACHE looped result from imodcache");
					$getperms = $imodcache["$parentid"]["$userid"];
				}
			}
		}
	}
	else
	{
		$forumlist = fetch_forum_clause_sql($forumid, 'forumid');
		if (!empty($forumlist))
		{
			$forumlist = 'AND ' . $forumlist;
		}
		DEVDEBUG("  QUERY: get mod permissions for user $userid");
		$getperms = $DB_site->query_first("
			SELECT permissions, FIND_IN_SET(forumid, '" . fetch_forum_parent_list($forumid) . "') AS pos
			FROM " . TABLE_PREFIX . "moderator
			WHERE userid = $userid $forumlist
			ORDER BY pos ASC
			LIMIT 1
		");
	}

	$modpermscache["$forumid"]["$userid"] = intval($getperms['permissions']);
	return $getperms['permissions'];

}

// ###################### Start ismoderator #######################
function can_moderate($forumid = 0, $do = '', $userid = -1, $usergroupids = '')
{
	global $bbuserinfo, $DB_site, $_BITFIELD, $imodcache;
	static $modcache;

	$userid = intval($userid);

	if ($userid == -1)
	{
		$userid = $bbuserinfo['userid'];
	}

	if ($userid == 0)
	{
		return 0;
	}

	if ($userid == $bbuserinfo['userid'])
	{
		if ($bbuserinfo['permissions']['adminpermissions'] & ISMODERATOR)
		{
			DEVDEBUG('  USER IS A SUPER MODERATOR');
			return 1;
		}
	}
	else
	{
		if (!$usergroupids)
		{
			$tempuser = $DB_site->query_first("SELECT usergroupid, membergroupids FROM " . TABLE_PREFIX . "user WHERE userid = $userid");
			if (!$tempuser)
			{
				return false;
			}
			$usergroupids = $tempuser['usergroupid'] . iif(trim($tempuser['membergroupids']), ",$tempuser[membergroupids]");
		}
		$issupermod = $DB_site->query_first("
			SELECT usergroupid
			FROM " . TABLE_PREFIX . "usergroup
			WHERE usergroupid IN ($usergroupids)
				AND (adminpermissions & " . ISMODERATOR . ") != 0
			LIMIT 1
		");
		if ($issupermod)
		{
			DEVDEBUG('  USER IS A SUPER MODERATOR');
			return 1;
		}

	}

	// if we got this far, user is not a super moderator

	if ($forumid == 0)
	{ // just check to see if the user is a moderator of any forum
		if (isset($imodcache))
		{ // loop through imodcache to find user
			DEVDEBUG("looping through imodcache to find userid $userid");
			foreach ($imodcache AS $forummods)
			{
				if (isset($forummods["$userid"]))
				{
					if (!$do)
					{
						return 1;
					}
					else if ($forummods['permissions'] & $_BITFIELD['moderatorpermissions']["$do"])

					{
						return 1;
					}
				}
			}
			return 0;
		}
		else
		{ // imodcache is not set - do a query

			if (isset($modcache["$userid"]["$do"]))
			{
				return $modcache["$userid"]["$do"];
			}

			$modcache["$userid"]["$do"] = 0;

			DEVDEBUG('QUERY: is the user a moderator (any forum)?');
			$ismod_all = $DB_site->query("SELECT moderatorid, permissions FROM " . TABLE_PREFIX . "moderator WHERE userid = $userid");
			while ($ismod = $DB_site->fetch_array($ismod_all))
			{
				if ($do)
				{
					if ($ismod['permissions'] & $_BITFIELD['moderatorpermissions']["$do"])
					{
						$modcache["$userid"]["$do"] = 1;
						break;
					}
				}
				else
				{
					$modcache["$userid"]["$do"] = 1;
					break;
				}
			}

			return $modcache["$userid"]["$do"];
		}
	}
	else
	{ // check to see if user is a moderator of specific forum
		if ($getmodperms = fetch_moderator_permissions($forumid, $userid) AND empty($do))
		{ // check if user is a mod - no specific permission required
			return 1;
		}
		else
		{ // check if user is a mod and has permissions to '$do'
			if ($getmodperms & $_BITFIELD['moderatorpermissions']["$do"])
			{
				return 1;
			}
			else
			{
				return 0;
			}  // if has perms for this action
		}// if is mod for forum and no action set
	} // if forumid=0
}

// ###################### Start demomode #######################
// if DEMO_MODE is defined and set to true in config.php
// this function will return false, the main purpose of
// which is to disable parsing of phpinclude templates
// and other stuff that is undesirable for a board running
// with a publicly accessible admin control panel
function is_demo_mode()
{
	return (defined('DEMO_MODE') AND DEMO_MODE == true) ? true : false;
}

if (!function_exists('file_get_contents'))
{
	// use file_get_contents as it will provide improvements for those in 4.3.0 and above
	// but older versions wont notice any difference.
	function file_get_contents($filename)
	{
		if ($handle = @fopen ($filename, "rb"))
		{
			do
			{
				$data = fread($handle, 8192);
				if (strlen($data) == 0)
				{
					break;
				}
				$contents .= $data;
			} while(true);
			@fclose ($handle);
			return $contents;
		}
		return false;
	}
}

// #################### Start is browser ##########################
// browser detection script
function is_browser($browser, $version = 0)
{
	global $_SERVER;
	static $is;
	if (!is_array($is))
	{
		$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
		$is = array(
			'opera' => 0,
			'ie' => 0,
			'mozilla' => 0,
			'firebird' => 0,
			'firefox' => 0,
			'camino' => 0,
			'konqueror' => 0,
			'safari' => 0,
			'webkit' => 0,
			'webtv' => 0,
			'netscape' => 0,
			'mac' => 0
		);

		// detect opera
			# Opera/7.11 (Windows NT 5.1; U) [en]
			# Mozilla/4.0 (compatible; MSIE 6.0; MSIE 5.5; Windows NT 5.0) Opera 7.02 Bork-edition [en]
			# Mozilla/4.0 (compatible; MSIE 6.0; MSIE 5.5; Windows NT 4.0) Opera 7.0 [en]
			# Mozilla/4.0 (compatible; MSIE 5.0; Windows 2000) Opera 6.0 [en]
			# Mozilla/4.0 (compatible; MSIE 5.0; Mac_PowerPC) Opera 5.0 [en]
		if (strpos($useragent, 'opera') !== false)
		{
			preg_match('#opera(/| )([0-9\.]+)#', $useragent, $regs);
			$is['opera'] = $regs[2];
		}

		// detect internet explorer
			# Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; Q312461)
			# Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.0.3705)
			# Mozilla/4.0 (compatible; MSIE 5.22; Mac_PowerPC)
			# Mozilla/4.0 (compatible; MSIE 5.0; Mac_PowerPC; e504460WanadooNL)
		if (strpos($useragent, 'msie ') !== false AND !$is['opera'])
		{
			preg_match('#msie ([0-9\.]+)#', $useragent, $regs);
			$is['ie'] = $regs[1];
		}

		// detect macintosh
		if (strpos($useragent, 'mac') !== false)
		{
			$is['mac'] = 1;
		}

		// detect safari
			# Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en-us) AppleWebKit/74 (KHTML, like Gecko) Safari/74
			# Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en) AppleWebKit/51 (like Gecko) Safari/51
		if (strpos($useragent, 'applewebkit') !== false AND $is['mac'])
		{
			preg_match('#applewebkit/(\d+)#', $useragent, $regs);
			$is['webkit'] = $regs[1];

			if (strpos($useragent, 'safari') !== false)
			{
				preg_match('#safari/([0-9\.]+)#', $useragent, $regs);
				$is['safari'] = $regs[1];
			}
		}

		// detect konqueror
			# Mozilla/5.0 (compatible; Konqueror/3.1; Linux; X11; i686)
			# Mozilla/5.0 (compatible; Konqueror/3.1; Linux 2.4.19-32mdkenterprise; X11; i686; ar, en_US)
			# Mozilla/5.0 (compatible; Konqueror/2.1.1; X11)
		if (strpos($useragent, 'konqueror') !== false)
		{
			preg_match('#konqueror/([0-9\.-]+)#', $useragent, $regs);
			$is['konqueror'] = $regs[1];
		}

		// detect mozilla
			# Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.4b) Gecko/20030504 Mozilla
			# Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.2a) Gecko/20020910
			# Mozilla/5.0 (X11; U; Linux 2.4.3-20mdk i586; en-US; rv:0.9.1) Gecko/20010611
		if (strpos($useragent, 'gecko') !== false AND !$is['safari'] AND !$is['konqueror'])
		{
			preg_match('#gecko/(\d+)#', $useragent, $regs);
			$is['mozilla'] = $regs[1];

			// detect firebird / firefox
				# Mozilla/5.0 (Windows; U; WinNT4.0; en-US; rv:1.3a) Gecko/20021207 Phoenix/0.5
				# Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.4b) Gecko/20030516 Mozilla Firebird/0.6
				# Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.4a) Gecko/20030423 Firebird Browser/0.6
				# Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.6) Gecko/20040206 Firefox/0.8
			if (strpos($useragent, 'firefox') !== false OR strpos($useragent, 'firebird') !== false OR strpos($useragent, 'phoenix') !== false)
			{
				preg_match('#(phoenix|firebird|firefox)( browser)?/([0-9\.]+)#', $useragent, $regs);
				$is['firebird'] = $regs[3];

				if ($regs[1] == 'firefox')
				{
					$is['firefox'] = $regs[3];
				}
			}

			// detect camino
				# Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en-US; rv:1.0.1) Gecko/20021104 Chimera/0.6
			if (strpos($useragent, 'chimera') !== false OR strpos($useragent, 'camino') !== false)
			{
				preg_match('#(chimera|camino)/([0-9\.]+)#', $useragent, $regs);
				$is['camino'] = $regs[2];
			}
		}

		// detect web tv
		if (strpos($useragent, 'webtv') !== false)
		{
			preg_match('#webtv/([0-9\.]+)#', $useragent, $regs);
			$is['webtv'] = $regs[1];
		}

		// detect pre-gecko netscape
		if (preg_match('#mozilla/([1-4]{1})\.([0-9]{2}|[1-8]{1})#', $useragent, $regs))
		{
			$is['netscape'] = "$regs[1].$regs[2]";
		}
	}

	// sanitize the incoming browser name
	$browser = strtolower($browser);
	if (substr($browser, 0, 3) == 'is_')
	{
		$browser = substr($browser, 3);
	}

	// return the version number of the detected browser if it is the same as $browser
	if ($is["$browser"])
	{
		// $version was specified - only return version number if detected version is >= to specified $version
		if ($version)
		{
			if ($is["$browser"] >= $version)
			{
				return $is["$browser"];
			}
		}
		else
		{
			return $is["$browser"];
		}
	}

	// if we got this far, we are not the specified browser, or the version number is too low
	return 0;
}

// ###################### Start fetch stylevars #######################
// function to fetch stylevars array
function fetch_stylevars(&$style, $userinfo)
{
	global $_BITFIELD;

	if (is_array($style))
	{
		// first let's get the basic stylevar array
		$stylevar = unserialize($style['stylevars']);
		unset($style['stylevars']);

		// if we have a buttons directory override, use it
		if ($userinfo['lang_imagesoverride'])
		{
			$stylevar['imgdir_button'] = str_replace('<#>', $style['styleid'], $userinfo['lang_imagesoverride']);
		}
	}
	else
	{
		$stylevar = array();
	}

	// get text direction and left/right values
	if ($userinfo['lang_options'] & $_BITFIELD['languageoptions']['direction'])
	{
		// standard left-to-right layout
		$stylevar['textdirection'] = 'ltr';
		$stylevar['left'] = 'left';
		$stylevar['right'] = 'right';
	}
	else
	{
		// reversed right-to-left layout
		$stylevar['textdirection'] = 'rtl';
		$stylevar['left'] = 'right';
		$stylevar['right'] = 'left';
	}

	// get the 'lang' attribute for <html> tags
	$stylevar['languagecode'] = $userinfo['lang_code'];

	// get the 'charset' attribute
	$stylevar['charset'] = $userinfo['lang_charset'];

	// merge in css colors if available
	if (!empty($style['csscolors']))
	{
		$stylevar = array_merge($stylevar, unserialize($style['csscolors']));
		unset($style['csscolors']);
	}

	// get CSS width for outerdivwidth from outertablewidth
	if (strpos($stylevar['outertablewidth'], '%') === false)
	{
		$stylevar['outerdivwidth'] = $stylevar['outertablewidth'] . 'px';
	}
	else
	{
		$stylevar['outerdivwidth'] = $stylevar['outertablewidth'];
	}

	// get CSS width for divwidth from tablewidth
	if (strpos($stylevar['tablewidth'], '%') === false)
	{
		$stylevar['divwidth'] = $stylevar['tablewidth'] . 'px';
	}
	else if ($stylevar['tablewidth'] == '100%')
	{
		$stylevar['divwidth'] = 'auto';
	}
	else
	{
		$stylevar['divwidth'] = $stylevar['tablewidth'];
	}

	return $stylevar;
}

// ###################### Start fetch options overrides #######################
// function to override settings in $vboptions
function fetch_options_overrides($userinfo)
{
	global $vboptions;

	if ($userinfo['lang_dateoverride'] != '')
	{
		$vboptions['dateformat'] = $userinfo['lang_dateoverride'];
	}
	if ($userinfo['lang_timeoverride'] != '')
	{
		$vboptions['timeformat'] = $userinfo['lang_timeoverride'];
	}
	if ($userinfo['lang_registereddateoverride'] != '')
	{
		$vboptions['registereddateformat'] = $userinfo['lang_registereddateoverride'];
	}
	if ($userinfo['lang_calformat1override'] != '')
	{
		$vboptions['calformat1'] = $userinfo['lang_calformat1override'];
	}
	if ($userinfo['lang_calformat2override'] != '')
	{
		$vboptions['calformat2'] = $userinfo['lang_calformat2override'];
	}
	if ($userinfo['lang_logdateoverride'] != '')
	{
		$vboptions['logdateformat'] = $userinfo['lang_logdateoverride'];
	}
	if ($userinfo['lang_locale'] != '')
	{
		$locale1 = setlocale(LC_TIME, $userinfo['lang_locale']);
		$locale2 = setlocale(LC_CTYPE, $userinfo['lang_locale']);
	}

}

// ###################### Start fetch vbphrase array #######################
// function to build the initial $vbphrase array
function init_language()
{
	global $vboptions, $_BITFIELD, $bbuserinfo, $phrasegroups;
	global $copyrightyear, $timediff, $timenow, $datenow;

	// define languageid
	define('LANGUAGEID', iif(empty($bbuserinfo['languageid']), $vboptions['languageid'], $bbuserinfo['languageid']));

	// define language direction (preferable to use $stylevar[textdirection])
	define('LANGUAGE_DIRECTION', iif(($bbuserinfo['lang_options'] & $_BITFIELD['languageoptions']['direction']), 'ltr', 'rtl'));

	// define html language code (lang="xyz") (preferable to use $stylevar[languagecode])
	define('LANGUAGE_CODE', $bbuserinfo['lang_code']);

	// initialize the $vbphrase array
	$vbphrase = array();

	// populate the $vbphrase array with phrase groups
	foreach ($phrasegroups AS $phrasegroup)
	{
		$tmp = unserialize($bbuserinfo["phrasegroup_$phrasegroup"]);
		if (is_array($tmp))
		{
			$vbphrase = array_merge($vbphrase, $tmp);
		}
		unset($bbuserinfo["phrasegroup_$phrasegroup"], $tmp);
	}

	// prepare phrases for construct_phrase / sprintf use
	//$vbphrase = preg_replace('/\{([0-9])+\}/siU', '%\\1$s', $vbphrase);

	// pre-parse some global phrases
	$tzoffset = iif($bbuserinfo['tzoffset'], " $bbuserinfo[tzoffset]", '');
	$vbphrase['all_times_are_gmt_x_time_now_is_y'] = construct_phrase($vbphrase['all_times_are_gmt_x_time_now_is_y'], $tzoffset, $timenow, $datenow);
	$vbphrase['vbulletin_copyright'] = construct_phrase($vbphrase['vbulletin_copyright'], $vboptions['templateversion'], $copyrightyear);
	$vbphrase['powered_by_vbulletin'] = construct_phrase($vbphrase['powered_by_vbulletin'], $vboptions['templateversion'], $copyrightyear);
	$vbphrase['timezone'] = construct_phrase($vbphrase['timezone'], $timediff, $timenow, $datenow);

	// all done
	return $vbphrase;
}

// ###################### Return a specific Time Zone #############
function fetch_timezone($offset = 'all')
{
	$timezones = array(
		'-12'  => 'timezone_gmt_minus_1200',
		'-11'  => 'timezone_gmt_minus_1100',
		'-10'  => 'timezone_gmt_minus_1000',
		'-9'   => 'timezone_gmt_minus_0900',
		'-8'   => 'timezone_gmt_minus_0800',
		'-7'   => 'timezone_gmt_minus_0700',
		'-6'   => 'timezone_gmt_minus_0600',
		'-5'   => 'timezone_gmt_minus_0500',
		'-4'   => 'timezone_gmt_minus_0400',
		'-3.5' => 'timezone_gmt_minus_0330',
		'-3'   => 'timezone_gmt_minus_0300',
		'-2'   => 'timezone_gmt_minus_0200',
		'-1'   => 'timezone_gmt_minus_0100',
		'0'    => 'timezone_gmt_plus_0000',
		'1'    => 'timezone_gmt_plus_0100',
		'2'    => 'timezone_gmt_plus_0200',
		'3'    => 'timezone_gmt_plus_0300',
		'3.5'  => 'timezone_gmt_plus_0330',
		'4'    => 'timezone_gmt_plus_0400',
		'4.5'  => 'timezone_gmt_plus_0430',
		'5'    => 'timezone_gmt_plus_0500',
		'5.5'  => 'timezone_gmt_plus_0530',
		'6'    => 'timezone_gmt_plus_0600',
		'7'    => 'timezone_gmt_plus_0700',
		'8'    => 'timezone_gmt_plus_0800',
		'9'    => 'timezone_gmt_plus_0900',
		'9.5'  => 'timezone_gmt_plus_0930',
		'10'   => 'timezone_gmt_plus_1000',
		'11'   => 'timezone_gmt_plus_1100',
		'12'   => 'timezone_gmt_plus_1200'
	);

	if ($offset === 'all')
	{
		return $timezones;
	}
	else
	{
		return $timezones["$offset"];
	}
}

// ###################### Start style options #######################
function construct_style_options($styleid = -1, $depthmark = '', $init = true, $quickchooser = false)
{
	global $DB_site, $bbuserinfo, $stylevar, $vboptions, $vbphrase, $stylecount, $stylechoosercache;

	$thisstyleid = iif($quickchooser, $bbuserinfo['styleid'], $bbuserinfo['realstyleid']);
	if ($thisstyleid == 0 AND $quickchooser)
	{
		$thisstyleid = $vboptions['styleid'];
	}

	// initialize various vars
	if ($init)
	{
		$stylesetlist = '';
		// set the user's 'real style id'
		if (!isset($bbuserinfo['realstyleid']))
		{
			$bbuserinfo['realstyleid'] = $bbuserinfo['styleid'];
		}

		if (!$quickchooser)
		{
			if ($thisstyleid == 0)
			{
				$optionselected = HTML_SELECTED;
			}
			$optionvalue = 0;
			$optiontitle = $vbphrase['use_forum_default'];
			eval ('$stylesetlist .= "' . fetch_template('option') . '";');
		}
	}

	// check to see that the current styleid exists
	// and workaround a very very odd bug (#2079)
	if (is_array($stylechoosercache["$styleid"]))
	{
		$cache = &$stylechoosercache["$styleid"];
	}
	else if (is_array($stylechoosercache[$styleid]))
	{
		$cache = &$stylechoosercache[$styleid];
	}
	else
	{
		return;
	}

	// loop through the stylechoosercache to get results
	foreach ($cache AS $x)
	{
		foreach ($x AS $style)
		{
			if ($style['userselect'])
			{
				$stylecount++;
				if ($thisstyleid == $style['styleid'])
				{
					$optionselected = HTML_SELECTED;
				}
				else
				{
					$optionselected = '';
				}
				$optionvalue = $style['styleid'];
				$optiontitle = $depthmark . ' ' . $style['title'];
				eval ('$stylesetlist .= "' . fetch_template('option') . '";');
				$stylesetlist .= construct_style_options($style['styleid'], $depthmark . '--', false, $quickchooser);
			}
			else
			{
				$stylesetlist .= construct_style_options($style['styleid'], $depthmark, false, $quickchooser);
			}
		}
	}

	return $stylesetlist;
}

// ###################### Start build datastore #######################
function build_datastore($title = '', $data = '')
{
	global $DB_site;

	if ($title != '')
	{
		$DB_site->query("
			REPLACE INTO " . TABLE_PREFIX . "datastore
				(title, data)
			VALUES
				('" . addslashes(trim($title)) . "', '" . addslashes(trim($data)) . "')
		");
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15862 $
|| ####################################################################
\*======================================================================*/
?>