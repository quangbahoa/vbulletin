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

// predefine the phrasetypeids of a few phrase groups
define('PHRASETYPEID_HOLIDAY',   35);
define('PHRASETYPEID_ERROR',     1000);
define('PHRASETYPEID_REDIRECT',  2000);
define('PHRASETYPEID_MAILMSG',   3000);
define('PHRASETYPEID_MAILSUB',   4000);
define('PHRASETYPEID_SETTING',   5000);
define('PHRASETYPEID_ADMINHELP',	6000);
define('PHRASETYPEID_FAQTITLE',  7000);
define('PHRASETYPEID_FAQTEXT',   8000);
//define('PHRASETYPEID_CPMESSAGE',	9000); - now merged with phrasetypeid = 1000

// this defines what adds the indent to forums in the forumjump menu
define('FORUM_PREPEND', '&nbsp; &nbsp; ');

// defines for the datamanagers
define('ERRTYPE_ARRAY',    0);
define('ERRTYPE_STANDARD', 1);
define('ERRTYPE_CP',       2);
define('ERRTYPE_SILENT',   3);

define('VF_TYPE',       0);
define('VF_REQ',        1);
define('VF_CODE',       2);
define('VF_METHODNAME', 3);

define('VF_METHOD', '_-_mEtHoD_-_');

define('REQ_NO',   0);
define('REQ_YES',  1);
define('REQ_AUTO', 2);
define('REQ_INCR', 3);

/**
* @ignore
*/
define('COOKIE_SALT', 'VBS4AAFB47');

// #############################################################################
/**
* Essentially a wrapper for the ternary operator.
*
* @deprecated	Deprecated as of 3.5. Use the ternary operator.
*
* @param	string	Expression to be evaluated
* @param	mixed	Return this if the expression evaluates to true
* @param	mixed	Return this if the expression evaluates to false
*
* @return	mixed	Either the second or third parameter of this function
*/
function iif($expression, $returntrue, $returnfalse = '')
{
	return ($expression ? $returntrue : $returnfalse);
}

// #############################################################################
/**
* Class factory. This is used for instantiating the extended classes.
*
* @param	string			The type of the class to be called (user, forum etc.)
* @param	vB_Registry		An instance of the vB_Registry object.
* @param	integer			One of the ERRTYPE_x constants
* @param	string			Option to force loading a class from a specific file; no extension
*
* @return	vB_DataManager	An instance of the desired class
*/
function &datamanager_init($classtype, &$registry, $errtype = ERRTYPE_STANDARD, $forcefile = '')
{
	static $called;

	if (empty($called))
	{
		// include the abstract base class
		require_once(DIR . '/includes/class_dm.php');
		$called = true;
	}

	if (preg_match('#^\w+$#', $classtype))
	{
		$classtype = strtolower($classtype);
		if ($forcefile)
		{
			$classfile = preg_replace('#[^a-z0-9_]#i', '', $forcefile);
		}
		else
		{
			$classfile = str_replace('_multiple', '', $classtype);
		}
		require_once(DIR . '/includes/class_dm_' . $classfile . '.php');

		switch($classtype)
		{
			case 'attachment':
				$object = vB_DataManager_Attachment::fetch_library($registry, $errtype);
				break;
			case 'userpic_avatar':
			case 'userpic_profilepic':
				$object = vB_DataManager_Userpic::fetch_library($registry, $errtype, $classtype);
				break;
			default:
				$classname = 'vB_DataManager_' . $classtype;
				$object = new $classname($registry, $errtype);
		}

		return $object;
	}
}

// #############################################################################
/**
* Converts A-Z to a-z, doesn't change any other characters
*
* @param	string	String to convert to lowercase
*
* @return	string	Lowercase string
*/
function vbstrtolower($string)
{
	global $stylevar;
	if (function_exists('mb_strtolower') AND $newstring = @mb_strtolower($string, $stylevar['charset']))
	{
		return $newstring;
	}
	else
	{
		return strtr($string,
			'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			'abcdefghijklmnopqrstuvwxyz'
		);
	}
}

// #############################################################################
/**
* Converts html entities to a regular character so strlen can be performed
*
* @param	string	String to be measured
*/
function vbstrlen($string)
{
	$string = preg_replace('#&\#([0-9]+);#', '_', $string);

	global $stylevar;
	if (function_exists('mb_strlen') AND $length = @mb_strlen($string, $stylevar['charset']))
	{
		return $length;
	}
	else
	{
		return strlen($string);
	}
}

// #############################################################################
/**
* Formats a number with user's own decimal and thousands chars
*
* @param	mixed	Number to be formatted: integer / 8MB / 16 GB / 6.0 KB / 3M / 5K / ETC
* @param	integer	Number of decimal places to display
* @param	boolean	Special case for byte-based numbers
*
* @return	mixed	The formatted number
*/
function vb_number_format($number, $decimals = 0, $bytesize = false, $decimalsep = null, $thousandsep = null)
{
	global $vbulletin, $vbphrase;

	$type = '';

	if (empty($number))
	{
		return 0;
	}
	else if (preg_match('#^(\d+(?:\.\d+)?)(?>\s*)([mkg])b?$#i', trim($number), $matches))
	{
		switch(strtolower($matches[2]))
		{
			case 'g':
				$number = $matches[1] * 1073741824;
				break;
			case 'm':
				$number = $matches[1] * 1048576;
				break;
			case 'k':
				$number = $matches[1] * 1024;
				break;
			default:
				$number = $matches[1] * 1;
		}
	}

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

	if ($decimalsep === null)
	{
		$decimalsep = $vbulletin->userinfo['lang_decimalsep'];
	}
	if ($thousandsep === null)
	{
		$thousandsep = $vbulletin->userinfo['lang_thousandsep'];
	}

	return str_replace('_', '&nbsp;', number_format($number, $decimals, $decimalsep, $thousandsep)) . $type;
}

// #############################################################################
/**
* vBulletin's own random number generator
*
* @param	integer	Minimum desired value
* @param	integer	Maximum desired value
* @param	mixed	Seed for the number generator (if not specified, a new seed will be generated)
*/
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

// #############################################################################
/**
* Returns an array of usergroupids from all the usergroups to which a user belongs
*
* @param	array	User info array - must contain usergroupid and membergroupid fields
* @param	boolean	Whether or not to fetch the user's primary group as part of the returned array
*
* @return	array	Usergroup IDs to which the user belongs
*/
function fetch_membergroupids_array(&$user, $getprimary = true)
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

// #############################################################################
/**
* Works out if a user is a member of the specified usergroup(s)
*
* This function can be overloaded to test multiple usergroups: is_member_of($user, 1, 3, 4, 6...)
*
* @param	array	User info array - must contain userid, usergroupid and membergroupids fields
* @param	integer	Usergroup ID to test
*
* @return	boolean
*/
function is_member_of(&$userinfo, $usergroupid)
{
	static $user_memberships;

	if (func_num_args() == 2)
	{
		// only one group specified - stuff it into an array
		if (is_array($usergroupid))
		{
			$groups = $usergroupid;
		}
		else
		{
			$groups = array($usergroupid);
		}
	}
	else
	{
		// many groups specified - put them into an array
		$groups = func_get_args();
		unset($groups[0]);
	}

	if (!is_array($user_memberships["$userinfo[userid]"]))
	{
		// fetch membergroup ids for this user
		$user_memberships["$userinfo[userid]"] = fetch_membergroupids_array($userinfo);
	}

	foreach ($groups AS $usergroupid)
	{
		// is current group user's primary usergroup, or one of their membergroups?
		if ($userinfo['usergroupid'] == $usergroupid OR in_array($usergroupid, $user_memberships["$userinfo[userid]"]))
		{
			// yes - return true
			return true;
		}
	}

	// if we get here then the user doesn't belong to any of the groups.
	return false;
}

// #############################################################################
/**
* Works out if the specified user is 'in Coventry'
*
* @param	integer	User ID
* @param	boolean	Whether or not to confirm that the visiting user is himself in Coventry or not
*
* @return	boolean
*/
function in_coventry($userid, $includeself = false)
{
	global $vbulletin;
	static $Coventry;

	// if user is guest, or user is bbuser, user is NOT in Coventry.
	if ($userid == 0 OR ($userid == $vbulletin->userinfo['userid'] AND $includeself == false))
	{
		return false;
	}

	if (!is_array($Coventry))
	{
		if (trim($vbulletin->options['globalignore']) != '')
		{
			$Coventry = preg_split('#\s+#s', $vbulletin->options['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
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


// #############################################################################
/**
* Replaces any non-printing ASCII characters with the specified string
*
* Note: blank removal currently causes problems with double byte languages!
*
* @param	string	Text to be processed
* @param	string	String with which to replace non-printing characters
*
* @return	string
*/
function strip_blank_ascii($text, $replace)
{
	global $vbulletin;
	static $blanks = null;

	if ($blanks === null AND trim($vbulletin->options['blankasciistrip']) != '')
	{
		$blanks = array();

		$raw_blanks = preg_split('#\s+#', $vbulletin->options['blankasciistrip'], -1, PREG_SPLIT_NO_EMPTY);
		foreach ($raw_blanks AS $code_point)
		{
			if ($code_point[0] == 'u')
			{
				// this is a unicode character to remove
				$code_point = intval(substr($code_point, 1));
				$force_unicode = true;
			}
			else
			{
				$code_point = intval($code_point);
				$force_unicode = false;
			}

			if ($code_point > 255 OR $force_unicode)
			{
				// outside ASCII range or forced Unicode, so the chr function wouldn't work anyway
				$blanks[] = '&#' . $code_point . ';';
				$blanks[] = convert_int_to_utf8($code_point);
			}
			else
			{
				$blanks[] = chr($code_point);
			}
		}
	}

	if ($blanks)
	{
		$text = str_replace($blanks, $replace, $text);
	}

	return $text;
}

// #############################################################################
/**
* Replaces any instances of words censored in $vbulletin->options['censorwords'] with $vbulletin->options['censorchar']
*
* @param	string	Text to be censored
*
* @return	string
*/
function fetch_censored_text($text)
{
	global $vbulletin;
	static $censorwords;

	if ($vbulletin->options['enablecensor'] AND !empty($vbulletin->options['censorwords']))
	{
		if (empty($censorwords))
		{
			$vbulletin->options['censorwords'] = preg_quote($vbulletin->options['censorwords'], '#');
			$censorwords = preg_split('#\s+#', $vbulletin->options['censorwords'], -1, PREG_SPLIT_NO_EMPTY);
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
				// words are delimited by ASCII characters outside of A-Z and a-z
				$text = preg_replace(
					'#(?<=[\x00-\x40\x5b-\x60\x7b-\x7f]|^)' . $censorword . '(?=[\x00-\x40\x5b-\x60\x7b-\x7f]|$)#si',
					str_repeat($vbulletin->options['censorchar'], vbstrlen($censorword)),
					$text
				);
			}
			else
			{
				$text = preg_replace("#$censorword#si", str_repeat($vbulletin->options['censorchar'], vbstrlen($censorword)), $text);
			}
		}
	}

	// strip any admin-specified blank ascii chars
	$text = strip_blank_ascii($text, $vbulletin->options['censorchar']);

	return $text;
}

// #############################################################################
/**
* Attempts to intelligently wrap excessively long strings onto multiple lines
*
* @param	string	Text to be wrapped
* @param	integer	If specified, max word wrap length
*
* @return	string
*/
function fetch_word_wrapped_string($text, $limit = false)
{
	global $vbulletin;

	if ($limit !== false)
	{
		$vbulletin->options['wordwrap'] = $limit;
	}

	if ($vbulletin->options['wordwrap'] > 0 AND !empty($text))
	{
		return preg_replace('#(?>[^\s&/<>"\\-\[\]]|&[\#a-z0-9]{1,7};){' . $vbulletin->options['wordwrap'] . '}#i', '$0  ', $text);
	}
	else
	{
		return $text;
	}
}

// #############################################################################
/**
* Trims a string to the specified length while keeping whole words
*
* @param	string	String to be trimmed
* @param	integer	Number of characters to aim for in the trimmed string
*
* @return	string
*/
function fetch_trimmed_title($title, $chars = -1)
{
	global $vbulletin;

	if ($chars == -1)
	{
		$chars = $vbulletin->options['lastthreadchars'];
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

		if (vbstrlen($title) > $chars)
		{
			$title = substr($title, 0, $chars);
			if (($pos = strrpos($title, ' ')) !== false)
			{
				$title = substr($title, 0, $pos);
			}
			return $title . '...';
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

// #############################################################################
/**
* Checks to see if the IP address of the visiting user is banned from visiting
*
* This function will show an error and halt execution if the IP is banned.
*/
function verify_ip_ban()
{
	// make sure we can contact the admin
	if (THIS_SCRIPT == 'sendmessage' AND (empty($_REQUEST['do']) OR $_REQUEST['do'] == 'contactus' OR $_REQUEST['do'] == 'docontactus'))
	{
		return;
	}

	global $vbulletin;

	$user_ipaddress = IPADDRESS . '.';

	if ($vbulletin->options['enablebanning'] == 1 AND $vbulletin->options['banip'] = trim($vbulletin->options['banip']))
	{
		$addresses = preg_split('#\s+#', $vbulletin->options['banip'], -1, PREG_SPLIT_NO_EMPTY);
		foreach ($addresses AS $banned_ip)
		{
			if (strpos($banned_ip, '*') === false AND $banned_ip{strlen($banned_ip) - 1} != '.')
			{
				$banned_ip .= '.';
			}

			$banned_ip_regex = str_replace('\*', '(.*)', preg_quote($banned_ip, '#'));
			if (preg_match('#^' . $banned_ip_regex . '#U', $user_ipaddress))
			{
				eval(standard_error(fetch_error('banip', $vbulletin->options['contactuslink'])));
			}
		}
	}
}

// #############################################################################
/**
* Fetches the remaining characters in a filename after the final dot
*
* @param	string	The filename to test
*
* @return	string	The extension of the provided file
*/
function file_extension($filename)
{
	return substr(strrchr($filename, '.'), 1);
}

// #############################################################################
/**
* Tests a string to see if it's a valid email address
*
* @param	string	Email address
*
* @return	boolean
*/
function is_valid_email($email)
{
	// checks for a valid email format
	return preg_match('#^[a-z0-9.!\#$%&\'*+-/=?^_`{|}~]+@([0-9.]+|([^\s\'"<>]+\.+[a-z]{2,6}))$#si', $email);
}

// #############################################################################
/**
* Reads the email message queue and delivers a number of pending emails to the message sender
*/
function exec_mail_queue()
{
	global $vbulletin;

	if ($vbulletin->mailqueue !== null AND $vbulletin->mailqueue > 0 AND $vbulletin->options['usemailqueue'])
	{
		// mailqueue template holds number of emails awaiting sending
		if (!class_exists('vB_Mail'))
		{
			require_once(DIR . '/includes/class_mail.php');
		}

		$mail =& vB_QueueMail::fetch_instance();
		$mail->exec_queue();
	}
}

// #############################################################################
/**
* Begin adding email to the mail queue
*/
function vbmail_start()
{
	if (!class_exists('vB_Mail'))
	{
		require_once(DIR . '/includes/class_mail.php');
	}
	$mail =& vB_QueueMail::fetch_instance();
	$mail->set_bulk(true);
}

// #############################################################################
/**
* Starts the process of sending an email - either immediately or by adding it to the mail queue.
*
* @param	string	Destination email address
* @param	string	Email message subject
* @param	string	Email message body
* @param	boolean	If true, do not use the mail queue and send immediately
* @param	string	Optional name/email to use in 'From' header
* @param	string	Additional headers
* @param	string	Username of person sending the email
*/
function vbmail($toemail, $subject, $message, $notsubscription = false, $from = '', $uheaders = '', $username = '')
{
	if (defined('DISABLE_MAIL'))
	{
		// define this in config.php -- good for test boards,
		// that you don't want people to stumble upon
		return true;
	}

	global $vbulletin;

	if (!class_exists('vB_Mail'))
	{
		require_once(DIR . '/includes/class_mail.php');
	}

	if ($vbulletin->options['usemailqueue'] AND !$notsubscription)
	{
		$mail =& vB_QueueMail::fetch_instance();
	}
	else if ($vbulletin->options['use_smtp'])
	{
		$mail =& new vB_SmtpMail($vbulletin);
	}
	else
	{
		$mail =& new vB_Mail($vbulletin);
	}

	$mail->start($toemail, $subject, $message, $from, $uheaders, $username);
	$mail->send();
}

// #############################################################################
/**
* Stop adding mail to the mail queue and insert the mailqueue data for sending later
*/
function vbmail_end()
{
	if (!class_exists('vB_Mail'))
	{
		require_once(DIR . '/includes/class_mail.php');
	}
	$mail =& vB_QueueMail::fetch_instance();
	$mail->set_bulk(false);
}

// #############################################################################
/**
* Returns a portion of an SQL query to select language fields from the database
*
* @param	boolean	If true, select 'language.fieldname' otherwise 'fieldname'
*
* @return	string
*/
function fetch_language_fields_sql($addtable = true)
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

// #############################################################################
/**
* Returns an UPDATE or INSERT query string for use in big queries with loads of fields...
*
* @param	array	Array of fieldname = value pairs - array('userid' => 21, 'username' => 'John Doe')
* @param	string	Name of the table into which the data should be saved
* @param	string	SQL condition to add to the query string
* @param	array	Array of field names that should be ignored from the $queryvalues array
*
* @return	string
*/
function fetch_query_sql($queryvalues, $table, $condition = '', $exclusions = '')
{
	global $vbulletin;

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
			if (!preg_match('#^\w+$#', $fieldname))
			{
				continue;
			}
			$querystring .= "\t`$fieldname` = " . iif(is_numeric($value) OR in_array($fieldname, $exclusions), "'$value'", "'" . $vbulletin->db->escape_string($value) . "'") . iif($i++ == $numfields, "\n", ",\n");
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
			if (!preg_match('#^\w+$#', $fieldname))
			{
				continue;
			}
			$endbit = iif($i++ == $numfields, '', ', ');
			$fieldlist .= "`" . $fieldname . "`" . $endbit;
			$valuelist .= iif(is_numeric($value) OR in_array($fieldname, $exclusions), "'$value'", "'" . $vbulletin->db->escape_string($value) . "'") . $endbit;
		}
		return "\n### INSERT QUERY GENERATED BY fetch_query_sql() ###\nINSERT INTO " . TABLE_PREFIX . "$table\n\t($fieldlist)\nVALUES\n\t($valuelist)";
	}
}

// #############################################################################
/**
* Returns the username surrounded by the HTML markup defined by its specified displaygroupid
*
* @param	array	(ref) User info array
* @param	string	Name of the field representing displaygroupid in the User info array
* @param	string	Name of the field representing username in the User info array
*
* @return	string
*/
function fetch_musername(&$user, $displaygroupfield = 'displaygroupid', $usernamefield = 'username')
{
	global $vbulletin;

	$username = $user["$usernamefield"];

	if (isset($vbulletin->usergroupcache["$user[$displaygroupfield]"]))
	{
		// use $displaygroupid
		$displaygroupid = $user["$displaygroupfield"];
	}
	else if (isset($vbulletin->usergroupcache["$user[usergroupid]"]))
	{
		// use primary usergroupid
		$displaygroupid = $user['usergroupid'];
	}
	else
	{
		// use guest usergroup
		$displaygroupid = 1;
	}

	$user['musername'] = $vbulletin->usergroupcache["$displaygroupid"]['opentag'] . $username . $vbulletin->usergroupcache["$displaygroupid"]['closetag'];
	$user['displaygrouptitle'] = $vbulletin->usergroupcache["$displaygroupid"]['title'];
	$user['displayusertitle'] = $vbulletin->usergroupcache["$displaygroupid"]['usertitle'];

	return $user['musername'];
}

// #############################################################################
/**
* Returns an array containing info for the specified forum, or false if forum is not found
*
* @param	integer	(ref) Forum ID
* @param	boolean	Whether or not to return the result from the forumcache if it exists
*
* @return	mixed
*/
function fetch_foruminfo(&$forumid, $usecache = true)
{
	global $vbulletin;

	$forumid = intval($forumid);
	if (!$usecache OR !isset($vbulletin->forumcache["$forumid"]))
	{
		$vbulletin->forumcache["$forumid"] = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "forum
			WHERE forumid = $forumid
		");
	}

	if (!$vbulletin->forumcache["$forumid"])
	{
		return false;
	}

	// decipher 'options' bitfield
	$vbulletin->forumcache["$forumid"]['options'] = intval($vbulletin->forumcache["$forumid"]['options']);
	foreach($vbulletin->bf_misc_forumoptions AS $optionname => $optionval)
	{
		$vbulletin->forumcache["$forumid"]["$optionname"] = (($vbulletin->forumcache["$forumid"]['options'] & $optionval) ? 1 : 0);
	}

	($hook = vBulletinHook::fetch_hook('fetch_foruminfo')) ? eval($hook) : false;

	return $vbulletin->forumcache["$forumid"];
}

// #############################################################################
/**
* Returns an array containing info for the speficied thread, or false if thread is not found
*
* @param	integer	(ref) Thread ID
*
* @return	mixed
*/
function fetch_threadinfo(&$threadid, $usecache = true)
{
	global $vbulletin, $threadcache;

	$threadid = intval($threadid);
	if (!isset($threadcache["$threadid"]))
	{
		$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);
		$threadcache["$threadid"] = $vbulletin->db->query_first("
			SELECT IF(visible = 2, 1, 0) AS isdeleted,
			" . (THIS_SCRIPT == 'postings' ? " deletionlog.userid AS del_userid,
			deletionlog.username AS del_username, deletionlog.reason AS del_reason," : "") . "

			" . iif($vbulletin->userinfo['userid'] AND ($vbulletin->options['threadsubscribed'] AND THIS_SCRIPT == 'showthread') OR THIS_SCRIPT == 'editpost' OR THIS_SCRIPT == 'newreply' OR THIS_SCRIPT == 'postings', 'NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed, emailupdate, folderid,')
			  . iif($vbulletin->options['threadvoted'] AND $vbulletin->userinfo['userid'], 'threadrate.vote,')
			  . iif($marking, 'threadread.readtime AS threadread, forumread.readtime AS forumread,') . "
			thread.*
			FROM " . TABLE_PREFIX . "thread AS thread
			" . (THIS_SCRIPT == 'postings' ? "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')" : "") . "
			" . iif($vbulletin->userinfo['userid'] AND ($vbulletin->options['threadsubscribed'] AND THIS_SCRIPT == 'showthread') OR THIS_SCRIPT == 'editpost' OR THIS_SCRIPT == 'newreply' OR THIS_SCRIPT == 'postings', "LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON (subscribethread.threadid = thread.threadid AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . " AND canview = 1)") . "
			" . iif($vbulletin->options['threadvoted'] AND $vbulletin->userinfo['userid'], "LEFT JOIN " . TABLE_PREFIX . "threadrate AS threadrate ON (threadrate.threadid = thread.threadid AND threadrate.userid = " . $vbulletin->userinfo['userid'] . ")") . "
			" . iif($marking, "
				LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = thread.forumid AND forumread.userid = " . $vbulletin->userinfo['userid'] . ")
			") . "
			WHERE thread.threadid = $threadid
		");
	}

	($hook = vBulletinHook::fetch_hook('fetch_threadinfo')) ? eval($hook) : false;

	return $threadcache["$threadid"];
}

// #############################################################################
/**
* Returns an array contining info for the specified post, or false if post is not found
*
* @param	integer	(ref) Post ID
*
* @return	mixed
*/
function fetch_postinfo(&$postid)
{
	global $vbulletin;
	global $postcache;

	$postid = intval($postid);
	if (!isset($postcache["$postid"]))
	{
		$postcache["$postid"] = $vbulletin->db->query_first("
			SELECT post.*,
			IF(visible = 2, 1, 0) AS isdeleted,
			" . (THIS_SCRIPT == 'postings' ? " deletionlog.userid AS del_userid,
			deletionlog.username AS del_username, deletionlog.reason AS del_reason," : "") . "

			editlog.userid AS edit_userid, editlog.dateline AS edit_dateline, editlog.reason AS edit_reason
			FROM " . TABLE_PREFIX . "post AS post
			" . (THIS_SCRIPT == 'postings' ? "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND deletionlog.type = 'post')" : "") . "
			LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON (editlog.postid = post.postid)
			WHERE post.postid = $postid
		");
	}

	($hook = vBulletinHook::fetch_hook('fetch_postinfo')) ? eval($hook) : false;

	return $postcache["$postid"];
}

// #############################################################################
/**
* Fetches an array containing info for the specified user, or false if user is not found
*
* Values for Option parameter:
* 1 - Join the reputationlevel table to get the user's reputation description
* 2 - Get avatar
* 4 - Process user's online location
* 8 - Join the customprofilpic table to get the userid just to check if we have a picture
* 16 - Join the administrator table to get various admin options
* Therefore: Option = 6 means 'Get avatar' and 'Process online location'
* See fetch_userinfo() in the do=getinfo section of member.php if you are still confused
*
* @param	integer	(ref) User ID
* @param	integer	Bitfield Option (see description)
*
* @return	array	The information for the requested user
*/
function fetch_userinfo(&$userid, $option = 0, $languageid = 0)
{
	global $vbulletin, $usercache, $vbphrase, $permissions, $phrasegroups;

	if ($userid == $vbulletin->userinfo['userid'] AND $option != 0 AND isset($usercache["$userid"]))
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

	$hook_query_fields = $hook_query_joins = '';
	($hook = vBulletinHook::fetch_hook('fetch_userinfo_query')) ? eval($hook) : false;

	// no cache available - query the user
	$user = $vbulletin->db->query_first("
		SELECT " .
			iif(($option & 16), ' administrator.*, ') . "
			userfield.*, usertextfield.*, user.*, UNIX_TIMESTAMP(passworddate) AS passworddate,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid" .
			iif(($option & 1) AND $vbulletin->options['reputationenable'] == 1, ', level') .
			iif(($option & 2) AND $vbulletin->options['avatarenabled'], ', avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, customavatar.width AS avwidth, customavatar.height AS avheight').
			iif(($option & 8), ', customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline, customprofilepic.width AS ppwidth, customprofilepic.height AS ppheight') .
			iif(!isset($vbphrase), fetch_language_fields_sql(), '') . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (user.userid = userfield.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid) " .
		iif(($option & 1) AND $vbulletin->options['reputationenable'] == 1, "LEFT JOIN  " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON (user.reputationlevelid = reputationlevel.reputationlevelid) ").
		iif(($option & 2) AND $vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) ") .
		iif(($option & 8), "LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid) ") .
		iif(($option & 16), "LEFT JOIN " . TABLE_PREFIX . "administrator AS administrator ON (administrator.userid = user.userid) ") .
		iif(!isset($vbphrase), "INNER JOIN " . TABLE_PREFIX . "language AS language ON (language.languageid = " . (!empty($languageid) ? $languageid : "IF(user.languageid = 0, " . intval($vbulletin->options['languageid']) . ", user.languageid)") . ") ") . "
		$hook_query_joins
		WHERE user.userid = $userid
	");

	if (!$user)
	{
		return $user;
	}

	$user['languageid'] = (!empty($languageid) ? $languageid : $user['languageid']);

	// decipher 'options' bitfield
	$user['options'] = intval($user['options']);

	foreach ($vbulletin->bf_misc_useroptions AS $optionname => $optionval)
	{
		$user["$optionname"] = ($user['options'] & $optionval ? 1 : 0);
		//DEVDEBUG("$optionname = $user[$optionname]");
	}

	// make a username variable that is safe to pass through URL links
	$user['urlusername'] = urlencode(unhtmlspecialchars($user['username']));

	// make a username variable that is surrounded by their display group markup
	$user['musername'] = fetch_musername($user);

	// get the user's real styleid (not the cookie value)
	$user['realstyleid'] = $user['styleid'];

	// set the logout hash
	$user['logouthash'] = md5($user['userid'] . $user['salt'] . COOKIE_SALT);

	if ($option & 4)
	{ // Process Location info for this user
		require_once(DIR . '/includes/functions_online.php');
		$user = fetch_user_location_array($user);
	}

	($hook = vBulletinHook::fetch_hook('fetch_userinfo')) ? eval($hook) : false;

	$usercache["$userid"] = $user;
	return $usercache["$userid"];
}

// #############################################################################
/**
* Returns the parentlist of the specified forum
*
* @param	integer	Forum ID
*
* @return	string	Comma separated list of parent forum IDs
*/
function fetch_forum_parent_list($forumid)
{
	global $vbulletin;
	static $forumarraycache;

	$forumid = intval($forumid);

	if (isset($vbulletin->forumcache["$forumid"]['parentlist']))
	{
		DEVDEBUG("CACHE parentlist from forum $forumid");
		return $vbulletin->forumcache["$forumid"]['parentlist'];
	}
	else
	{
		if (isset($forumarraycache["$forumid"]))
		{
			return $forumarraycache["$forumid"];
		}
		else if (isset($vbulletin->forumcache["$forumid"]['parentlist']))
		{
			return $vbulletin->forumcache["$forumid"]['parentlist'];
		}
		else
		{
			DEVDEBUG("QUERY parentlist from forum $forumid");
			$foruminfo = $vbulletin->db->query_first("
				SELECT parentlist
				FROM " . TABLE_PREFIX . "forum
				WHERE forumid = $forumid
			");
			$forumarraycache["$forumid"] = $foruminfo['parentlist'];
			return $foruminfo['parentlist'];
		}
	}
}

// #############################################################################
/**
* Returns an SQL condition like (forumid = 1 OR forumid = 2 OR forumid = 3) for each of a forum's parents
*
* @param	integer	Forum ID
* @param	string	The name of the field to be used in the clause
* @param	string	The 'joiner' word - could be 'OR' or 'AND' etc.
* @param	string	The parentlist of the specified forum (comma separated string)
*
* @return	string
*/
function fetch_forum_clause_sql($forumid, $field = 'forumid', $joiner = 'OR', $parentlist = '')
{
	global $vbulletin;

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

// #############################################################################
/**
* Multi-purpose function to verify that an item exists and fetch data at the same time
*
* This function works with threads, forums, posts, users and other tables that obey the {item}id, title convention.
* If the data is not found and execution is not halted, a false value will be returned
*
* @param	string	Name of the ID field to be fetched (forumid, threadid etc.)
* @param	integer	(ref) ID of the item to be fetched
* @param	boolean	If true, halt and show error when data is not found
* @param	boolean	If true, 'SELECT *' instead of selecting just the ID field
* @param	integer	Bitfield options to be passed to fetch_userinfo()
*
* @return	mixed
*/
function verify_id($idname, &$id, $alert = true, $selall = false, $options = 0)
{
	// verifies an id number and returns a correct one if it can be found
	// returns 0 if none found
	global $vbulletin, $vbphrase;

	$id = intval($id);
	if (empty($id))
	{
		if ($alert)
		{
			eval(standard_error(fetch_error('noid', $vbphrase["$idname"], $vbulletin->options['contactuslink'])));
		}
		else
		{
			return 0;
		}
	}

	$selid = ($selall ? '*' : $idname . 'id');

	switch ($idname)
	{
		case 'thread':
		case 'forum':
		case 'post':
			$function = 'fetch_' . $idname . 'info';
			$tempcache = $function($id);
			if (!$tempcache AND $alert)
			{
				eval(standard_error(fetch_error('invalidid', $vbphrase["$idname"], $vbulletin->options['contactuslink'])));
			}
			return ($selall ? $tempcache : $tempcache[$idname . 'id']);

		case 'user':
			$tempcache = fetch_userinfo($id, $options);
			if (!$tempcache AND $alert)
			{
				eval(standard_error(fetch_error('invalidid', $vbphrase["$idname"], $vbulletin->options['contactuslink'])));
			}
			return ($selall ? $tempcache : $tempcache[$idname . 'id']);

		default:
			if (!$check = $vbulletin->db->query_first("SELECT $selid FROM " . TABLE_PREFIX . "$idname WHERE $idname" . "id = $id"))
			{
				if ($alert)
				{
					eval(standard_error(fetch_error('invalidid', $vbphrase["$idname"], $vbulletin->options['contactuslink'])));
				}

				return ($selall ? array() : 0);
			}
			else
			{
				return ($selall ? $check : $check["$selid"]);
			}
	}
}

// #############################################################################
/**
* Strips away [quote] tags and their contents from the specified string
*
* @param	string	Text to be stripped of quote tags
*
* @return	string
*/
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

// #############################################################################
/**
* Strips away bbcode from a given string, leaving plain text
*
* @param	string	Text to be stripped of bbcode tags
* @param	boolean	If true, strip away quote tags AND their contents
* @param	boolean	If true, use the fast-and-dirty method rather than the shiny and nice method
*
* @return	string
*/
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
		$find[] = '#\[(email|url)=("??)(.+)\\2\](.+)\[/\\1\]#siU';
		$replace[] = ($showlinks ? '\4 (\3)' : '\4');

		// replace links (and quotes if specified) from message
		$message = preg_replace($find, $replace, $message);

		// strip out all other instances of [x]...[/x]
		while(preg_match_all('#\[(\w+?)(?>[^\]]*?)\](.*)(\[/\1\])#siU', $message, $regs))
		{
			foreach($regs[0] AS $key => $val)
			{
				$message  = str_replace($val, $regs[2]["$key"], $message);
			}
		}
		$message = str_replace('[*]', ' ', $message);
	}

	return trim($message);
}

// #############################################################################
/**
* Returns a gzip-compressed version of the specified string
*
* @param	string	Text to be gzipped
* @param	integer	Level of Gzip compression (1-10)
*
* @return	string
*/
function fetch_gzipped_text($text, $level = 1)
{
	global $vbulletin;

	$returntext = $text;

	if (function_exists('crc32') AND function_exists('gzcompress') AND !$vbulletin->nozip)
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
			$vbulletin->donegzip = true;
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

// #############################################################################
/**
* Checks whether or not any headers have been sent to the browser yet
*
* @param	string	(ref) File name (> PHP 4.3.x)
* @param	integer	(ref) Line number (> PHP 4.3.x)
*
* @return	boolean	True if headers have been sent
*/
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

// #############################################################################
/**
* Sets a cookie based on vBulletin environmental settings
*
* @param	string	Cookie name
* @param	mixed	Value to store in the cookie
* @param	boolean	If true, do not set an expiry date for the cookie
* @param	boolean	Set HTTP Only Cookie
*/
function vbsetcookie($name, $value = '', $permanent = true, $httponly = false)
{
	if (defined('NOCOOKIES'))
	{
		return;
	}

	global $vbulletin;

	if ($permanent)
	{
		$expire = TIMENOW + 60 * 60 * 24 * 365;
	}
	else
	{
		$expire = 0;
	}

	// IE for Mac doesn't support httponly
	$httponly = (($httponly AND (is_browser('ie') AND is_browser('mac'))) ? false : $httponly);

	// check for SSL
	$secure = (($_SERVER['HTTPS'] == 'on' OR $_SERVER['HTTPS'] == '1') ? true : false);

	$name = COOKIE_PREFIX . $name;

	$filename = 'N/A';
	$linenum = 0;

	if (!vbheaders_sent($filename, $linenum))
	{ // consider showing an error message if they're not sent using above variables?

		if ($value === '' OR $value === false)
		{
			// this will attempt to unset the cookie at each directory up the path.
			// ie, path to file = /test/vb3/. These will be unset: /, /test, /test/, /test/vb3, /test/vb3/
			// This should hopefully prevent cookie conflicts when the cookie path is changed.

			if ($_SERVER['PATH_INFO'] OR $_ENV['PATH_INFO'])
			{
				$scriptpath = $_SERVER['PATH_INFO'] ? $_SERVER['PATH_INFO'] : $_ENV['PATH_INFO'];
			}
			else if ($_SERVER['REDIRECT_URL'] OR $_ENV['REDIRECT_URL'])
			{
				$scriptpath = $_SERVER['REDIRECT_URL'] ? $_SERVER['REDIRECT_URL'] : $_ENV['REDIRECT_URL'];
			}
			else
			{
				$scriptpath = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_ENV['PHP_SELF'];
			}

			$scriptpath = preg_replace(
				array(
					'#/[^/]+\.php$#i',
					'#/(' . preg_quote($vbulletin->config['Misc']['admincpdir'], '#') . '|' . preg_quote($vbulletin->config['Misc']['modcpdir'], '#') . ')(/|$)#i'
				),
				'',
				$scriptpath
			);

			$dirarray = explode('/', preg_replace('#/+$#', '', $scriptpath));

			$alldirs = '';
			$havepath = false;
			if (!defined('SKIP_AGGRESSIVE_LOGOUT'))
			{
				// sending this many headers has caused problems with a few
				// servers, especially with IIS. Defining SKIP_AGGRESSIVE_LOGOUT
				// reduces the number of cookie headers returned.
				foreach ($dirarray AS $thisdir)
				{
					$alldirs .= "$thisdir";

					if ($alldirs == $vbulletin->options['cookiepath'] OR "$alldirs/" == $vbulletin->options['cookiepath'])
					{
						$havepath = true;
					}

					if (!empty($thisdir))
					{
						// try unsetting without the / at the end
						exec_vbsetcookie($name, $value, $expire, $alldirs, $vbulletin->options['cookiedomain'], $secure, $httponly);
					}

					$alldirs .= "/";
					exec_vbsetcookie($name, $value, $expire, $alldirs, $vbulletin->options['cookiedomain'], $secure, $httponly);
				}
			}

			if ($havepath == false)
			{
				exec_vbsetcookie($name, $value, $expire, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], $secure, $httponly);
			}
		}
		else
		{
			exec_vbsetcookie($name, $value, $expire, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], $secure, $httponly);
		}
	}
	else if (!$vbulletin->db->explain)
	{ //show some sort of error message
		global $templateassoc, $vbulletin;
		if (empty($templateassoc))
		{
			// this is being called before templates have been cached, so just get the default one
			$template = $vbulletin->db->query_first("
				SELECT templateid
				FROM " . TABLE_PREFIX . "template
				WHERE title = 'STANDARD_ERROR' AND styleid = -1
			");
			$templateassoc = array('STANDARD_ERROR' => $template['templateid']);
		}
		eval(standard_error(fetch_error('cant_set_cookies', $filename, $linenum)));
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

// #############################################################################
/**
* Returns the value for an array stored in a cookie
*
* @param	string	Name of the cookie
* @param	mixed	ID of the data within the cookie
*
* @return	mixed
*/
function fetch_bbarray_cookie($cookiename, $id)
{
	global $vbulletin;

	$cookie_name = COOKIE_PREFIX . $cookiename; // name of cookie variable
	$cache_name = 'bb_cache_' . $cookiename; // name of cache variable
	global $$cache_name; // internal array for cacheing purposes

	$cookie =& $vbulletin->input->clean_gpc('c', $cookie_name, TYPE_STR);
	$cache =  &$$cache_name;
	if ($cookie != '' AND !isset($cache))
	{
		$cache = @unserialize(convert_bbarray_cookie($cookie));
	}

	if (isset($cache))
	{
		return $cache["$id"];
	}

}

// #############################################################################
/**
* Sets the value for data stored in an array-cookie
*
* @param	string	Name of the cookie
* @param	mixed	ID of the data within the cookie
* @param	mixed	Value for the data
* @param	boolean	If true, make this a permanent cookie
*/
function set_bbarray_cookie($cookiename, $id, $value, $permanent = false)
{
	// sets the value for a array and sets the cookie
	global $vbulletin;

	$cookie_name = COOKIE_PREFIX . $cookiename; // name of cookie variable
	$cache_name = 'bb_cache_' . $cookiename; // name of cache variable
	global $$cache_name; // internal array for cacheing purposes

	$cookie =& $vbulletin->input->clean_gpc('c', $cookie_name, TYPE_STR);
	$cache =& $$cache_name;
	if ($cookie != '' AND !isset($cache))
	{
		$cache = @unserialize(convert_bbarray_cookie($cookie));
	}

	$cache["$id"] = $value;

	vbsetcookie($cookiename, convert_bbarray_cookie(serialize($cache), 'set'), $permanent);

}

// #############################################################################
/**
* Replaces all those none safe characters so we dont waste space in array cookie values with URL entities
*
* @param	string	Cookie array
* @param	string	Direction ('get' or 'set')
*
* @return	array
*/
function convert_bbarray_cookie($cookie, $dir = 'get')
{
	if ($dir == 'set')
	{
		$cookie = str_replace(array('"', ':', ';'), array('.', '-', '_'), $cookie);
		// prefix cookie with 32 character hash
		$cookie = md5($cookie . COOKIE_SALT) . $cookie;
	}
	else
	{
		$firstpart = substr($cookie, 0, 32);
		$cookie = substr($cookie, 32);
		if (md5($cookie . COOKIE_SALT) == $firstpart)
		{
			$cookie = str_replace(array('.', '-', '_'), array('"', ':', ';'), $cookie);
		}
		else
		{
			$cookie = '';
		}
	}
	return $cookie;
}

// #############################################################################
/**
* Reads $bgclass and returns the alternate table class
*
* @param	integer	If > 0, allows us to have multiple classes on one page without them overwriting each other
*
* @return	string	CSS class name
*/
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

// #############################################################################
/**
* Ensures that the variables for a multi-page display are sane
*
* @return	integer	Maximum posts perpage that a user can see
*/
function sanitize_maxposts($perpage = 0)
{
	global $vbulletin;
	$max = intval(max(explode(',', $vbulletin->options['usermaxposts'])));

	if ($max AND $vbulletin->userinfo['maxposts'])
	{
		if (!$perpage)
		{
			return $vbulletin->userinfo['maxposts'] == -1 ? $vbulletin->options['maxposts'] : $vbulletin->userinfo['maxposts'];
		}
		else if ($perpage == -1)
		{
			return $max;
		}
		else
		{
			return ($perpage > $max ? $max : $perpage);
		}
	}
	else if (!empty($vbulletin->options['maxposts']))
	{
		return $vbulletin->options['maxposts'];
	}
	else
	{
		return 10;
	}
}

// #############################################################################
/**
* Ensures that the variables for a multi-page display are sane
*
* @param	integer	Total number of items to be displayed
* @param	integer	(ref) Current page number
* @param	integer	(ref) Desired number of results to show per-page
* @param	integer	Maximum allowable results to show per-page
* @param	integer	Default number of results to show per-page
*/
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

// #############################################################################
/**
* Returns the HTML for multi-page navigation - based on code from 3dfrontier.com
*
* @param	integer	Total number of items found
* @param	string	Base address for links eg: showthread.php?t=99{&page=4}
* @param	string	Ending portion of address for links
*
* @return	string	Page navigation HTML
*/
function construct_page_nav($pagenumber, $perpage, $results, $address, $address2 = '')
{
	global $vbulletin, $vbphrase, $stylevar, $show;

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

	// create array of possible relative links that we might have (eg. +10, +20, +50, etc.)
	if (!is_array($vbulletin->options['pagenavsarr']))
	{
		$vbulletin->options['pagenavsarr'] = preg_split('#\s+#s', $vbulletin->options['pagenavs'], -1, PREG_SPLIT_NO_EMPTY);
	}

	while ($curpage++ < $totalpages)
	{
		($hook = vBulletinHook::fetch_hook('pagenav_page')) ? eval($hook) : false;

		if (abs($curpage - $pagenumber) >= $vbulletin->options['pagenavpages'] AND $vbulletin->options['pagenavpages'] != 0)
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
			// generate relative links (eg. +10,etc).
			if (in_array(abs($curpage - $pagenumber), $vbulletin->options['pagenavsarr']) AND $curpage != 1 AND $curpage != $totalpages)
			{
				$pagenumbers = fetch_start_end_total_array($curpage, $perpage, $results);
				$relpage = $curpage - $pagenumber;
				if ($relpage > 0)
				{
					$relpage = '+' . $relpage;
				}
				eval('$pagenav .= "' . fetch_template('pagenav_pagelinkrel') . '";');
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

	($hook = vBulletinHook::fetch_hook('pagenav_complete')) ? eval($hook) : false;

	eval('$pagenav = "' . fetch_template('pagenav') . '";');
	return $pagenav;
}

// #############################################################################
/**
* Returns an array so you can print 'Showing results $arr[first] to $arr[last] of $totalresults'
*
* @param	integer	Current page number
* @param	integer	Results to show per-page
* @param	integer	Total results found
*
* @return	array	In the format of - array('first' => x, 'last' => y)
*/
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

// #############################################################################
/**
* Returns the HTML for the navigation breadcrumb in the navbar
*
* This function will also set the GLOBAL $pagetitle to equal whatever is the last item in the navbits
*
* @param	array	Array of link => title pairs from which to build the link chain
*
* @return	string
*/
function construct_navbits($nav_array)
{
	global $pagetitle, $stylevar, $vbulletin, $vbphrase, $show;

	$code = array(
		'breadcrumb' => '',
		'lastelement' => ''
	);

	$lastelement = sizeof($nav_array);
	$counter = 0;

	if (is_array($nav_array))
	{
		foreach($nav_array AS $nav_url => $nav_title)
		{
			$pagetitle = $nav_title;

			$elementtype = iif(++$counter == $lastelement, 'lastelement', 'breadcrumb');
			$show['breadcrumb'] = iif($elementtype == 'breadcrumb', true, false);

			if (empty($nav_title))
			{
				continue;
			}

			($hook = vBulletinHook::fetch_hook('navbits')) ? eval($hook) : false;

			eval('$code["$elementtype"] .= "' . fetch_template('navbar_link') . '";');
		}
	}

	return $code;
}

// #############################################################################
/**
* Construct Phrase
*
* this function is actually just a wrapper for sprintf but makes identification of phrase code easier
* and will not error if there are no additional arguments. The first parameter is the phrase text, and
* the (unlimited number of) following parameters are the variables to be parsed into that phrase.
*
* @param	string	Text of the phrase
* @param	mixed	First variable to be inserted
* @param	mixed	Nth variable to be inserted
*
* @return	string	The parsed phrase
*/
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

// #############################################################################
/**
* Returns 2 lines of eval()-able code -- one sets $message, the other $subject.
*
* @param	string	Name of email phrase to fetch
* @param	integer	Language ID from which to pull the phrase (see fetch_phrase $languageid)
* @param	string	If not empty, select the subject phrase with the given name
* @param	string	Optional prefix  for $message/$subject variable names (eg: $varprefix = 'test' -> $testmessage, $testsubject)
*
* @return	string
*/
function fetch_email_phrases($email_phrase, $languageid = -1, $emailsub_phrase = '', $varprefix = '')
{
	if (empty($emailsub_phrase))
	{
		$emailsub_phrase = $email_phrase;
	}

	if (!function_exists('fetch_phrase'))
	{
		require_once(DIR . '/includes/functions_misc.php');
	}

	return
		'$' . $varprefix . 'message = "' . fetch_phrase($email_phrase, PHRASETYPEID_MAILMSG, 'email_', true, iif($languageid >= 0, true, ''), $languageid) . '";' .
		'$' . $varprefix . 'subject = "' . fetch_phrase($emailsub_phrase, PHRASETYPEID_MAILSUB, 'emailsubject_', true, iif($languageid >= 0, true, ''), $languageid) . '";';
}

// #############################################################################
/**
* Fetches an error phrase from the database and inserts values for its embedded variables
*
* @param	string	Varname of error phrase
* @param	mixed	Value of 1st variable
* @param	mixed	Value of 2nd variable
* @param	mixed	Value of Nth variable
*
* @return	string	The parsed phrase text
*/
function fetch_error()
{
	$args = func_get_args();

	// Allow an array of phrase and variables to be passed in as arg0 (for some internal functions)
	if (is_array($args[0]))
	{
		$args = $args[0];
	}

	if (!function_exists('fetch_phrase'))
	{
		require_once(DIR . '/includes/functions_misc.php');
	}

	$args[0] = fetch_phrase($args[0], PHRASETYPEID_ERROR, '', false);

	if (sizeof($args) > 1)
	{
		return call_user_func_array('construct_phrase', $args);
	}
	else
	{
		return $args[0];
	}
}

// #############################################################################
/**
* Halts execution and shows an error message stating that the visitor does not have permission to view the page
*/
function print_no_permission()
{
	global $vbulletin, $stylevar, $vbphrase;

	require_once(DIR . '/includes/functions_misc.php');

	$vbulletin->userinfo['badlocation'] = 1; // Used by exec_shut_down();

	($hook = vBulletinHook::fetch_hook('error_nopermission')) ? eval($hook) : false;

	$usergroupid = $vbulletin->userinfo['usergroupid'];

	if ($vbulletin->usergroupcache["$usergroupid"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isbannedgroup'])
	{
		$reason = $vbulletin->db->query_first("
			SELECT reason, liftdate
			FROM " . TABLE_PREFIX . "userban
			WHERE userid = " . $vbulletin->userinfo['userid']
		);

		// Check for a date or a perm ban
		if ($reason['liftdate'])
		{
			$date = vbdate($vbulletin->options['dateformat'] . ', ' . $vbulletin->options['timeformat'], $reason['liftdate']);
		}
		else
		{
			$date = $vbphrase['never'];
		}

		if (!$reason['reason'])
		{
			$reason['reason'] = $vbphrase['none'];
		}

		eval(standard_error(fetch_error('nopermission_banned', $reason['reason'], $date)));
	}

	if ($vbulletin->userinfo['userid'])
	{
		//eval(print_standard_error('nopermission_loggedin', true));
		eval(standard_error(fetch_error('nopermission_loggedin',
			$vbulletin->userinfo['username'],
			$stylevar['right'],
			$vbulletin->session->vars['sessionurl'],
			$vbulletin->userinfo['logouthash'],
			$vbulletin->options['forumhome']
		)));
	}
	else
	{
		define('VB_ERROR_PERMISSION', true);

		//eval(print_standard_error('nopermission_loggedout', false)); -- //HUH??
		eval(standard_error(fetch_error('nopermission_loggedout')));
	}
}

// #############################################################################
/**
* Returns eval()-able code to initiate a standard error
*
* @deprecated	Deprecated since 3.5. Use standard_error(fetch_error(...)) instead.
*
* @param	string	Name of error phrase
* @param	boolean	If false, use the name of error phrase as the phrase text itself
* @param	boolean	If true, set the visitor's status on WOL to error page
*
* @return	string
*/
function print_standard_error($err_phrase, $doquery = true, $savebadlocation = true)
{
	die("<h1><em>print_standard_error(...)</em><br />is now redundant. Instead, use<br /><em>standard_error(fetch_error(...))</em></h1>");

	if ($doquery)
	{
		if (!function_exists('fetch_phrase'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}

		return 'standard_error("' . fetch_phrase($err_phrase, PHRASETYPEID_ERROR, 'error_') . "\", '', " . intval($savebadlocation) . ");";
	}
	else
	{
		return 'standard_error("' . $err_phrase . "\", '', " . intval($savebadlocation) . ");";
	}
}

// #############################################################################
/**
* Halts execution and shows the specified error message
*
* @param	string	Error message
* @param	string	Optional HTML code to insert in the <head> of the error page
* @param	boolean	If true, set the visitor's status on WOL to error page
* @param	string	Optional template to force the display to use. Ignored if showing a lite error
*/
function standard_error($error = '', $headinsert = '', $savebadlocation = true, $override_template = '')
{
	global $header, $footer, $headinclude, $forumjump, $timezone, $gobutton;
	global $vbulletin, $vbphrase, $stylevar;
	global $pmbox, $vbphrase, $show;

	construct_forum_jump();

	$title = $vbulletin->options['bbtitle'];
	$pagetitle =& $title;
	$errormessage = $error;

	if (!$vbulletin->userinfo['badlocation'] AND $savebadlocation)
	{
		$vbulletin->userinfo['badlocation'] = 3;
	}

	require_once(DIR . '/includes/functions_misc.php');
	$postvars = construct_post_vars_html();

	if (defined('VB_ERROR_PERMISSION') AND VB_ERROR_PERMISSION == true)
	{
		$show['permission_error'] = true;
	}
	else
	{
		$show['permission_error'] = false;
	}

	$show['search_noindex'] = (bool)($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']);

	$navbits = $navbar = '';
	if (defined('VB_ERROR_LITE') AND VB_ERROR_LITE == true)
	{
		$templatename = 'STANDARD_ERROR_LITE';
		define('NOPMPOPUP', 1); // No Footer here
	}
	else
	{
		if ($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])
		{
			$show['forumdesc'] = false;
			$navbits = construct_navbits(array('' => $vbphrase['vbulletin_message']));
			eval('$navbar = "' . fetch_template('navbar') . '";');
		}
		$templatename = ($override_template ? preg_replace('#[^a-z0-9_]#i', '', $override_template) : 'STANDARD_ERROR');
	}

	($hook = vBulletinHook::fetch_hook('error_generic')) ? eval($hook) : false;

	eval('print_output("' . fetch_template($templatename) . '");');
	exit;
}

// #############################################################################
/**
* Returns eval()-able code to initiate a standard redirect
*
* The global variable $url should contain the URL target for the redirect
*
* @param	string	Name of redirect phrase
* @param	boolean	If false, use the name of redirect phrase as the phrase text itself
*
* @return	string
*/
function print_standard_redirect($redir_phrase, $doquery = true, $forceredirect = false)
{
	if ($doquery)
	{
		if (!function_exists('fetch_phrase'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}

		$phrase = fetch_phrase($redir_phrase, PHRASETYPEID_REDIRECT, 'redirect_');
		// addslashes run in fetch_phrase
	}
	else
	{
		$phrase = addslashes($redir_phrase);
	}

	return 'standard_redirect("' . $phrase . '", ' . intval($forceredirect) . ');';
}

// #############################################################################
/**
* Halts execution and redirects to the address specified
*
* If the 'useheaderredirect' option is on, the system will attempt to redirect invisibly using header('Location...
* However, 'useheaderredirect' is overridden by setting $forceredirect to a true value.
*
* @param	string	Redirect message
* @param	string	URL to which to redirect the browser
*/
function standard_redirect($message = '', $forceredirect = false)
{
	global $header, $footer, $headinclude, $forumjump;
	global $timezone, $vbulletin, $vbphrase, $stylevar, $pagestarttime;

	static
		$str_find     = array('"',      '<',    '>'),
		$str_replace  = array('&quot;', '&lt;', '&gt;');

	if ($vbulletin->db->explain)
	{
		$pageendtime = microtime();

		$starttime = explode(' ', $pagestarttime);
		$endtime = explode(' ', $pageendtime);

		$totaltime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];

		$vartext .= "<!-- Page generated in " . vb_number_format($totaltime, 5) . " seconds with " . $vbulletin->db->querycount . " queries -->";

		$querytime = $vbulletin->db->time_total;
		echo "\n<b>Page generated in $totaltime seconds with " . $vbulletin->db->querycount . " queries,\nspending $querytime doing MySQL queries and " . ($totaltime - $querytime) . " doing PHP things.\n\n<hr />Shutdown Queries:</b>" . (defined('NOSHUTDOWNFUNC') ? " <b>DISABLED</b>" : '') . "<hr />\n\n";
		exit;
	}

	if ($vbulletin->options['useheaderredirect'] AND !$forceredirect AND !headers_sent() AND !$vbulletin->GPC['postvars'])
	{
		exec_header_redirect($vbulletin->url);
	}

	$title = $vbulletin->options['bbtitle'];

	$pagetitle = $title;
	$errormessage = $message;

	$url = unhtmlspecialchars($vbulletin->url);
	$url = str_replace(chr(0), '', $url);
	$url = create_full_url($url);
	$url = str_replace($str_find, $str_replace, $url);
	$js_url = addslashes_js($url, '"'); // " has been replaced by &quot;

	$url = preg_replace(
		array('/&#0*59;?/', '/&#x0*3B;?/i', '#;#'),
		'%3B',
		$url
	);
	$url = preg_replace('#&amp%3B#i', '&amp;', $url);

	define('NOPMPOPUP', 1); // No footer here

	require_once(DIR . '/includes/functions_misc.php');
	$postvars = construct_hidden_var_fields($vbulletin->GPC['postvars']);
	$formfile =& $url;

	($hook = vBulletinHook::fetch_hook('redirect_generic')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('STANDARD_REDIRECT') . '");');
	exit;
}

// #############################################################################
/**
* Halts execution and redirects to the specified URL invisibly
*
* @param	string	Destination URL
*/
function exec_header_redirect($url)
{
	global $vbulletin;

	$url = create_full_url($url);

	if (class_exists('vBulletinHook'))
	{
		// this can be called when we don't have the hook class
		($hook = vBulletinHook::fetch_hook('header_redirect')) ? eval($hook) : false;
	}

	$url = str_replace('&amp;', '&', $url); // prevent possible oddity

	if (strpos($url, "\r\n") !== false)
	{
		trigger_error("Header may not contain more than a single header, new line detected.", E_USER_ERROR);
	}

	if (PHP_VERSION >= '4.3.0')
	{
		header("Location: $url", 0, 301);
	}
	else if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		header("Location: $url");
		// Call the status header after Location so we are sure to wipe out the 302 header sent by PHP
		header('Status: 301 Moved Permanently');
	}
	else
	{
		header("Location: $url");
		header('HTTP/1.1 301 Moved Permanently');
	}

	define('NOPMPOPUP', 1);
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	exit;
}

// #############################################################################
/**
* Translates a relative URL to a fully-qualified URL. URLs not beginning with
* a / are assumed to be within the main vB-directory
*
* @param	string	Relative URL
*
* @param	string	Fully-qualified URL
*/
function create_full_url($url)
{
	// enforces HTTP 1.1 compliance
	if (!preg_match('#^[a-z]+://#i', $url))
	{
		// make sure we get the correct value from a multitude of server setups
		if ($_SERVER['HTTP_HOST'] OR $_ENV['HTTP_HOST'])
		{
			$http_host = ($_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : $_ENV['HTTP_HOST']);
		}
		else if ($_SERVER['SERVER_NAME'] OR $_ENV['SERVER_NAME'])
		{
			$http_host = ($_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : $_ENV['SERVER_NAME']);
		}

		// if we don't have this, then this isn't going to work correctly,
		// so let's assume that we're going to be OK with just a relative URL
		if ($http_host = trim($http_host))
		{
			$method = (($_SERVER['HTTPS'] == 'on' OR $_SERVER['HTTPS'] == '1') ? 'https://' : 'http://');

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

	return $url;
}

// #############################################################################
/**
* Fetches a number of templates from the database and puts them into the templatecache
*
* @param	array	List of template names to be fetched
* @param	string	Serialized array of template name => template id pairs
*/
function cache_templates($templates, $templateidlist)
{
	global $vbulletin, $templateassoc;

	if (empty($templateassoc))
	{
		$templateassoc = unserialize($templateidlist);
	}

	if ($vbulletin->options['legacypostbit'] AND in_array('postbit', $templates))
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
		$temps = $vbulletin->db->query_read("
			SELECT title, template
			FROM " . TABLE_PREFIX . "template
			WHERE templateid IN (" . implode(',', $templateids) . ")
		");

		// cache templates
		while ($temp = $vbulletin->db->fetch_array($temps))
		{
			if (empty($vbulletin->templatecache["$temp[title]"]))
			{
				$vbulletin->templatecache["$temp[title]"] = $temp['template'];
			}
		}
		$vbulletin->db->free_result($temps);
	}

	$vbulletin->bbcode_style = array(
		'code'  => &$templateassoc['bbcode_code_styleid'],
		'html'  => &$templateassoc['bbcode_html_styleid'],
		'php'   => &$templateassoc['bbcode_php_styleid'],
		'quote' => &$templateassoc['bbcode_quote_styleid']
	);

}

// #############################################################################
/**
* Returns a single template from the templatecache or the database
*
* @param	string	Name of template to be fetched
* @param	integer	Escape quotes in template? 1: escape template; -1: unescape template; 0: do nothing
* @param	boolean	Wrap template in HTML comments showing the template name?
*
* @return	string
*/
function fetch_template($templatename, $escape = 0, $gethtmlcomments = true)
{
	// gets a template from the db or from the local cache
	global $style, $vbulletin, $tempusagecache, $templateassoc;

	// use legacy postbit if necessary
	if ($vbulletin->options['legacypostbit'] AND $templatename == 'postbit')
	{
		$templatename = 'postbit_legacy';
	}

	if (isset($vbulletin->templatecache["$templatename"]))
	{
		$template = $vbulletin->templatecache["$templatename"];
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
			$gettemp = $vbulletin->db->query_first("
				SELECT template
				FROM " . TABLE_PREFIX . "template
				WHERE templateid = $fetch_tid
			");
		}
		$template = $gettemp['template'];
		$vbulletin->templatecache["$templatename"] = $template;
	}

	// **************************
	/*
	if ($template == '<<< FILE >>>')
	{
		$template = addslashes(implode('', file("./templates/$templatename.html")));
		$vbulletin->templatecache["$templatename"] = $template;
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

	if ($vbulletin->options['addtemplatename'] AND $gethtmlcomments)
	{
		$templatename = preg_replace('#[^a-z0-9_]#i', '', $templatename);
		return "<!-- BEGIN TEMPLATE: $templatename -->\n$template\n<!-- END TEMPLATE: $templatename -->";
	}

	return $template;
}

// #############################################################################
/**
* Returns the HTML for the forum jump menu
*
* @param	integer	ID of the parent forum for the group to be shown (-1 for all)
* @param	boolean	If true, evaluate the forumjump template too
* @param	string	Characters to prepend to forum titles to indicate depth
* @param	string	Not sure actually...
*/
function construct_forum_jump($parentid = -1, $addbox = true, $prependchars = '', $permission = '')
{
	global $vbulletin, $optionselected, $usecategories, $jumpforumid, $jumpforumtitle, $jumpforumbits, $curforumid, $daysprune;
	global $stylevar, $vbphrase, $defaultselected, $forumjump, $selectedone;
	global $frmjmpsel; // allows context sensitivity for non-forum areas
	global $gobutton;
	static $complete = false;

	if ($complete OR !$vbulletin->options['useforumjump'] OR !($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		return;
	}

	if (empty($vbulletin->iforumcache))
	{
		require_once(DIR . '/includes/functions_forumlist.php');
		// cache_ordered_forums is probably going to be moved back to functions.php

		// get the vbulletin->iforumcache, as we use it all over the place, not just for forumjump
		cache_ordered_forums(1, 1);
	}

	if (empty($vbulletin->iforumcache["$parentid"]) OR !is_array($vbulletin->iforumcache["$parentid"]))
	{
		return;
	}

	foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
		if ((!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND !$vbulletin->options['showprivateforums']) OR !($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['showonforumjump']) OR !$vbulletin->forumcache["$forumid"]['displayorder'] OR !($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['active']))
		{
			continue;
		}
		else
		{
			// set $forum from the $vbulletin->forumcache
			$forum = $vbulletin->forumcache["$forumid"];

			$optionvalue = $forumid;
			$optiontitle = $prependchars . " $forum[title_clean]";

			$optionclass = 'fjdpth' . iif($forum['depth'] > 4, 4, $forum['depth']);

			if ($curforumid == $optionvalue)
			{
				$optionselected = 'selected="selected"';
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
	} // end foreach ($vbulletin->iforumcache[$parentid] AS $forumid)

	if ($addbox)
	{
		if ($selectedone != 1)
		{
			$defaultselected = 'selected="selected"';
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

		($hook = vBulletinHook::fetch_hook('forumjump')) ? eval($hook) : false;

		eval('$forumjump = "' . fetch_template('forumjump') . '";');

		// prevent forumjump from being built more than once
		$complete = true;
	}
}

// #############################################################################
/**
* Sets various time and date related variables according to visitor's preferences
*
* Sets $timediff, $datenow, $timenow, $copyrightyear
*/
function fetch_time_data()
{
	global $vbulletin, $timediff, $datenow, $timenow, $copyrightyear;

	$vbulletin->userinfo['tzoffset'] = $vbulletin->userinfo['timezoneoffset']; // preserve timzoneoffset for profile editing and proper event display

	if ($vbulletin->userinfo['dstonoff'])
	{
		// DST is on, add an hour
		$vbulletin->userinfo['tzoffset']++;

		if (substr($vbulletin->userinfo['tzoffset'], 0, 1) != '-')
		{
			// recorrect so that it has + sign, if necessary
			$vbulletin->userinfo['tzoffset'] = '+' . $vbulletin->userinfo['tzoffset'];
		}
	}

	// some stuff for the gmdate bug
	$vbulletin->options['hourdiff'] = (date('Z', TIMENOW) / 3600 - $vbulletin->userinfo['tzoffset']) * 3600;

	if ($vbulletin->userinfo['tzoffset'])
	{
		if ($vbulletin->userinfo['tzoffset'] > 0 AND strpos($vbulletin->userinfo['tzoffset'], '+') === false)
		{
			$vbulletin->userinfo['tzoffset'] = '+' . $vbulletin->userinfo['tzoffset'];
		}
		if (abs($vbulletin->userinfo['tzoffset']) == 1)
		{
			$timediff = ' ' . $vbulletin->userinfo['tzoffset'] . ' hour';
		}
		else
		{
			$timediff = ' ' . $vbulletin->userinfo['tzoffset'] . ' hours';
		}
	}
	else
	{
		$timediff = '';
	}

	$datenow       = vbdate($vbulletin->options['dateformat'], TIMENOW);
	$timenow       = vbdate($vbulletin->options['timeformat'], TIMENOW);
	$copyrightyear = vbdate('Y', TIMENOW, false, false);
}

// #############################################################################
/**
* Formats a UNIX timestamp into a human-readable string according to vBulletin prefs
*
* Note: Ifvbdate() is called with a date format other than than one in $vbulletin->options[],
* set $locale to false unless you dynamically set the date() and strftime() formats in the vbdate() call.
*
* @param	string	Date format string (same syntax as PHP's date() function)
* @param	integer	Unix time stamp
* @param	boolean	If true, attempt to show strings like "Yesterday, 12pm" instead of full date string
* @param	boolean	If true, and user has a language locale, use strftime() to generate language specific dates
* @param	boolean	If true, don't adjust time to user's adjusted time .. (think gmdate instead of date!)
* @param	boolean	If true, uses gmstrftime() and gmdate() instead of strftime() and date()
*
* @return	string	Formatted date string
*/
function vbdate($format, $timestamp = TIMENOW, $doyestoday = false, $locale = true, $adjust = true, $gmdate = false)
{
	global $vbulletin, $vbphrase;

	$hourdiff = $vbulletin->options['hourdiff'];

	if ($vbulletin->userinfo['lang_locale'] AND $locale)
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

	if ($format == $vbulletin->options['dateformat'] AND $doyestoday AND $vbulletin->options['yestoday'])
	{
		if ($vbulletin->options['yestoday'] == 1)
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

// #############################################################################
/**
* Returns a string where HTML entities have been converted back to their original characters
*
* @param	string	String to be parsed
* @param	boolean	Convert unicode characters back from HTML entities?
*
* @return	string
*/
function unhtmlspecialchars($text, $doUniCode = false)
{
	if ($doUniCode)
	{
		$text = preg_replace('/&#([0-9]+);/esiU', "convert_int_to_utf8('\\1')", $text);
	}

	return str_replace(array('&lt;', '&gt;', '&quot;', '&amp;'), array('<', '>', '"', '&'), $text);
}

// #############################################################################
/**
* Converts an integer into a UTF-8 character string
*
* @param	integer	Integer to be converted
*
* @return	string
*/
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

// #############################################################################
/**
* Converts Unicode entities of the format %uHHHH where each H is a hexadecimal
* character to &#DDDD; or the appropriate UTF-8 character based on current charset.
*
* @param	string	Encoded text
*
* @return	string	Decoded text
*/
function convert_urlencoded_unicode($text)
{
	global $stylevar;

	$is_utf8 = (strtolower($stylevar['charset']) == 'utf-8');

	$return = preg_replace(
		'#%u([0-9A-F]{1,4})#ie',
		"convert_unicode_char_to_charset(hexdec('\\1'), \$stylevar['charset'])",
		$text
	);

	if (!$is_utf8 AND function_exists('html_entity_decode'))
	{
		// this converts certain &#123; entities to their actual character
		// set values; don't do this if using UTF-8 as it's already done above.
		// note: we don't want to convert &gt;, etc as that undoes the effects of STR_NOHTML
		$return = preg_replace('#&([a-z]+);#i', '&amp;$1;', $return);
		$return = @html_entity_decode($return, ENT_NOQUOTES, $stylevar['charset']);
	}

	return $return;
}

/**
* Converts a single unicode character to the desired character set if possible.
* Attempts to use iconv if it's available.
* Callback function for the regular expression in convert_urlencoded_unicode.
*
* @param	integer	Unicode code point value
* @param	string	Character to convert to
*
* @return	string	Character in desired character set or as an HTML entity
*/
function convert_unicode_char_to_charset($unicode_int, $charset)
{
	$is_utf8 = (strtolower($charset) == 'utf-8');

	if ($is_utf8)
	{
		return convert_int_to_utf8($unicode_int);
	}

	if (function_exists('iconv'))
	{
		// convert this character -- if unrepresentable, it should fail
		$output = @iconv('UTF-8', $charset, convert_int_to_utf8($unicode_int));
		if ($output !== false AND $output !== '')
		{
			return $output;
		}
	}

	return "&#$unicode_int;";
}

// #############################################################################
/**
* Stuffs a message into the $DEVDEBUG array
*
* @param	string	Message to store
*/
function devdebug($text = '')
{
	global $vbulletin;

	if ($vbulletin->debug)
	{
		$GLOBALS['DEVDEBUG'][] = $text;
	}
}

// #############################################################################
/**
* Sends the appropriate HTTP headers for the page that is being displayed
*
* @param	boolean	If true, send HTTP 200
* @param	boolean	If true, send no-cache headers
*/
function exec_headers($headers = true, $nocache = true)
{
	global $vbulletin;

	$sendcontent = true;
	if ($vbulletin->options['addheaders'] AND !$vbulletin->noheader AND $headers)
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
		@header('Content-Type: text/html' . iif($vbulletin->userinfo['lang_charset'] != '', '; charset=' . $vbulletin->userinfo['lang_charset']));
		$sendcontent = false;
	}

	if ($vbulletin->options['nocacheheaders'] AND !$vbulletin->noheader AND $nocache)
	{
		// no caching
		exec_nocache_headers($sendcontent);
	}
	else if (!$vbulletin->noheader)
	{
		@header("Cache-Control: private");
		@header("Pragma: private");
		if ($sendcontent)
		{
			@header('Content-Type: text/html' . iif($vbulletin->userinfo['lang_charset'] != '', '; charset=' . $vbulletin->userinfo['lang_charset']));
		}
	}
}

// #############################################################################
/**
* Sends no-cache HTTP headers
*
* @param	boolean	If true, send content-type header
*/
function exec_nocache_headers($sendcontent = true)
{
	global $vbulletin;
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
			@header('Content-Type: text/html' . iif($vbulletin->userinfo['lang_charset'] != '', '; charset=' . $vbulletin->userinfo['lang_charset']));
		}
	}

	$sentheaders = true;
}

// #############################################################################
/**
* Returns whether or not the visiting user can view the specified password-protected forum
*
* @param	integer	Forum ID
* @param	string	Provided password
* @param	boolean	If true, show error when access is denied
*
* @return	boolean
*/
function verify_forum_password($forumid, $password, $showerror = true)
{
	global $vbulletin, $stylevar;

	if (!$password OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']) OR can_moderate($forumid))
	{
		return true;
	}

	$foruminfo = fetch_foruminfo($forumid);
	$parents = explode(',', $foruminfo['parentlist']);
	foreach ($parents AS $fid)
	{ // get the pwd from any parent forums -- allows pwd cookies to cascade down
		if ($temp = fetch_bbarray_cookie('forumpwd', $fid) AND $temp === md5($vbulletin->userinfo['userid'] . $password))
		{
			return true;
		}
	}

	// didn't match the password in any cookie
	if ($showerror)
	{
		require_once(DIR . '/includes/functions_misc.php');

		// forum password is bad - show error
		eval(standard_error(fetch_error('forumpasswordmissing',
			$vbulletin->session->vars['sessionhash'],
			$vbulletin->scriptpath,
			$forumid,
			construct_post_vars_html(),
			$stylevar['cellpadding'],
			$stylevar['cellspacing']
		)));
	}
	else
	{
		// forum password is bad - return false
		return false;
	}
}

// #############################################################################
/**
* Converts a bitfield into an array of 1 / 0 values based on the array describing the resulting fields
*
* @param	integer	(ref) Bitfield
* @param	array	Array containing field definitions - array('canx' => 1, 'cany' => 2, 'canz' => 4) etc
*
* @return	array
*/
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

// #############################################################################
/**
* Returns the full set of permissions for the specified user (called by global or init)
*
* @param	array	(ref) User info array
* @param	boolean	If true, returns combined usergroup permissions AND all individual forum permissions
* @param boolean	Reset the accesscache array for permissions following access mask update. Only allows one reset.
*
* @return	array	Permissions component of user info array
*/
function cache_permissions(&$user, $getforumpermissions = true, $resetaccess = false)
{
	global $vbulletin, $forumpermissioncache;

	// these are the arrays created by this function
	global $cpermscache, $calendarcache, $_PERMQUERY;
	static $accesscache = array(), $reset;

	if ($resetaccess AND !$reset)
	{	// Reset the accesscache array for permissions following access mask update. Only allows one reset.
		$accesscache = array();
		$reset = true;
	}

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
	if (sizeof($membergroupids) == 1 OR !($vbulletin->usergroupcache["$USERGROUPID"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['allowmembergroups']) )
	{
		// if primary usergroup doesn't allow member groups then get rid of them!
		$membergroupids = array($USERGROUPID);

		// just return the permissions for the user's primary group (user is only a member of a single group)
		$user['permissions'] = $vbulletin->usergroupcache["$USERGROUPID"];
	}
	else
	{
		// return the merged array of all user's membergroup permissions (user has additional member groups)
		foreach($membergroupids AS $usergroupid)
		{
			foreach ($vbulletin->bf_ugp AS $dbfield => $permfields)
			{
				$user['permissions']["$dbfield"] |= $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
			}
			foreach ($vbulletin->bf_misc_intperms AS $dbfield => $precedence)
			{
				// put in some logic to handle $precedence here .. see init.php
				if (!$precedence)
				{
					if ($vbulletin->usergroupcache["$usergroupid"]["$dbfield"] > $intperms["$dbfield"])
					{
						$intperms["$dbfield"] = $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
					}
				}
				else
				{
					if ($vbulletin->usergroupcache["$usergroupid"]["$dbfield"] == 0 OR (isset($intperms["$dbfield"]) AND $intperms["$dbfield"] == 0)) // Set value to 0 as it overrides all
					{
						$intperms["$dbfield"] = 0;
					}
					else if ($vbulletin->usergroupcache["$usergroupid"]["$dbfield"] > $intperms["$dbfield"])
					{
						$intperms["$dbfield"] = $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
					}
				}
			}
		}
		$user['permissions'] = array_merge($vbulletin->usergroupcache["$USERGROUPID"], $user['permissions'], $intperms);
	}

	if (defined('SKIP_SESSIONCREATE') AND $user['userid'] == $vbulletin->userinfo['userid'] AND !($user['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
	{	// grant canview for usergroup if session skipping is defined.
		$user['permissions']['forumpermissions'] += $vbulletin->bf_ugp_forumpermissions['canview'];
	}

	// if we do not need to grab the forum/calendar permissions
	// then just return what we have so far
	if ($getforumpermissions == false)
	{
		return $user['permissions'];
	}

	if (!is_array($user['forumpermissions']))
	{
		$user['forumpermissions'] = array();
	}

	foreach (array_keys($vbulletin->forumcache) AS $forumid)
	{
		foreach ($membergroupids AS $usergroupid)
		{
			$user['forumpermissions']["$forumid"] |= $vbulletin->forumcache["$forumid"]['permissions']["$usergroupid"];
		}
	}

	// do access mask stuff if required
	if ($vbulletin->options['enableaccess'] AND $user['hasaccessmask'] == 1)
	{
		if (empty($accesscache["$user[userid]"]))
		{
			// query access masks
			// the ordercontrol is required! (3.5 bug 1878)
			$_PERMQUERY[3] = "
				SELECT access.*, forum.forumid,
					FIND_IN_SET(access.forumid, forum.parentlist) AS ordercontrol
				FROM " . TABLE_PREFIX . "forum AS forum
				INNER JOIN " . TABLE_PREFIX . "access AS access ON (access.userid = $user[userid] AND FIND_IN_SET(access.forumid, forum.parentlist))
				ORDER BY ordercontrol DESC
			";
			$accesscache["$user[userid]"] = array();
			$accessmasks = $vbulletin->db->query_read($_PERMQUERY[3]);
			while ($access = $vbulletin->db->fetch_array($accessmasks))
			{
				$accesscache["$user[userid]"]["$access[forumid]"] = $access['accessmask'];
			}
			unset($access);
			$vbulletin->db->free_result($accessmasks);
		}

		// if an access mask is set for a forum, set the permissions accordingly
		// If this is empty then the user really has no access masks but the switch is turned on?!?
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
		else
		{
			// says the user has access masks, but doesn't actually
			// so turn them off
			$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdm->set_existing($user);
			$userdm->set_bitfield('options', 'hasaccessmask', false);
			$userdm->save();
			unset($userdm);
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
	if (THIS_SCRIPT == 'online' OR THIS_SCRIPT == 'calendar' OR (THIS_SCRIPT == 'index' AND $vbulletin->options['showevents']))
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
		$calendarpermissions = $vbulletin->db->query_read($_PERMQUERY[4]);
		while ($calendarpermission = $vbulletin->db->fetch_array($calendarpermissions))
		{
			$cpermscache["$calendarpermission[calendarid]"]["$calendarpermission[usergroupid]"] = intval($calendarpermission['calendarpermissions']);
			$calendarcache["$calendarpermission[calendarid]"] = $calendarpermission['title'];
			$displayorder["$calendarpermission[calendarid]"] = $calendarpermission['displayorder'];
		}
		unset($calendarpermission);
		$vbulletin->db->free_result($calendarpermissions);

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
						$user['calendarpermissions']["$calendarid"] |= $vbulletin->usergroupcache["$usergroupid"]['calendarpermissions'];
					}
				}
			}
		}
	}

	return $user['permissions'];

}

// #############################################################################
/**
* Returns permissions for given forum and user
*
* @param	integer	Forum ID
* @param	integer	User ID
* @param	array	User info array
*
* @return	mixed
*/
function fetch_permissions($forumid = 0, $userid = -1, $userinfo = false)
{
	// gets permissions, depending on given userid and forumid
	global $vbulletin, $usercache, $permscache;

	$userid = intval($userid);
	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
		$usergroupid = $vbulletin->userinfo['usergroupid'];
	}

	// ########## #DEBUG# CODE ##############
	$DEBUG_MESSAGE = iif(isset($GLOBALS['_permsgetter_']), "($GLOBALS[_permsgetter_])", '(unspecified)'). " fetch_permissions($forumid, $userid, $usergroupid,'$parentlist'); ";
	unset($GLOBALS['_permsgetter_']);
	// ########## END #DEBUG# CODE ##############

	if ($userid == $vbulletin->userinfo['userid'])
	{
		// we are getting permissions for $vbulletin->userinfo
		// so return permissions built in querypermissions
		if ($forumid)
		{
			DEVDEBUG($DEBUG_MESSAGE."-> cached fperms for forum $forumid");
			return $vbulletin->userinfo['forumpermissions']["$forumid"];
		}
		else
		{
			DEVDEBUG($DEBUG_MESSAGE.'-> cached combined permissions');
			return $vbulletin->userinfo['permissions'];
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

// #############################################################################
/**
* Returns moderator permissions bitfield for the given forum and user
*
* @param	integer	Forum ID
* @param	integer	User ID
*
* @return	integer
*/
function fetch_moderator_permissions($forumid, $userid = -1)
{
	// gets permissions, depending on given userid and forumid
	global $vbulletin, $imodcache;
	static $modpermscache;

	$forumid = intval($forumid);

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
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
		$getperms = $vbulletin->db->query_first("
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

// #############################################################################
/**
* Returns whether or not the given user can perform a specific moderation action in the specified forum
*
* @param	integer	Forum ID
* @param	string	If you want to check a particular moderation permission, name it here
* @param	integer	User ID
* @param	string	Comma separated list of usergroups to which the user belongs
*
* @return	boolean
*/
function can_moderate($forumid = 0, $do = '', $userid = -1, $usergroupids = '')
{
	global $vbulletin, $imodcache;
	static $modcache;

	$userid = intval($userid);
	$forumid = intval($forumid);

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
	}

	if ($userid == 0)
	{
		return false;
	}

	if ($userid == $vbulletin->userinfo['userid'])
	{
		if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
		{
			DEVDEBUG('  USER IS A SUPER MODERATOR');
			$return = true;
			($hook = vBulletinHook::fetch_hook('can_moderate_supermod')) ? eval($hook) : false;
			return $return;
		}
	}
	else
	{
		if (!$usergroupids)
		{
			$tempuser = $vbulletin->db->query_first("SELECT usergroupid, membergroupids FROM " . TABLE_PREFIX . "user WHERE userid = $userid");
			if (!$tempuser)
			{
				return false;
			}
			$usergroupids = $tempuser['usergroupid'] . iif(trim($tempuser['membergroupids']), ",$tempuser[membergroupids]");
		}
		$issupermod = $vbulletin->db->query_first("
			SELECT usergroupid
			FROM " . TABLE_PREFIX . "usergroup
			WHERE usergroupid IN ($usergroupids)
				AND (adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ") != 0
			LIMIT 1
		");
		if ($issupermod)
		{
			DEVDEBUG('  USER IS A SUPER MODERATOR');
			return true;
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
						return true;
					}
					else if ($forummods['permissions'] & $vbulletin->bf_misc_moderatorpermissions["$do"])
					{
						return true;
					}
				}
			}
			return false;
		}
		else
		{ // imodcache is not set - do a query

			if (isset($modcache["$userid"]["$do"]))
			{
				return $modcache["$userid"]["$do"];
			}

			$modcache["$userid"]["$do"] = 0;

			DEVDEBUG('QUERY: is the user a moderator (any forum)?');
			$ismod_all = $vbulletin->db->query_read("SELECT moderatorid, permissions FROM " . TABLE_PREFIX . "moderator WHERE userid = $userid");
			while ($ismod = $vbulletin->db->fetch_array($ismod_all))
			{
				if ($do)
				{
					if ($ismod['permissions'] & $vbulletin->bf_misc_moderatorpermissions["$do"])
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
			return true;
		}
		else
		{ // check if user is a mod and has permissions to '$do'
			if ($getmodperms & $vbulletin->bf_misc_moderatorpermissions["$do"])
			{
				return true;
			}
			else
			{
				$return = false;
				if (!isset($vbulletin->bf_misc_moderatorpermissions["$do"]))
				{
					($hook = vBulletinHook::fetch_hook('can_moderate_forum')) ? eval($hook) : false;
				}
				return $return;
			}  // if has perms for this action
		}// if is mod for forum and no action set
	} // if forumid=0
}

// #############################################################################
/**
* Returns whether or not vBulletin is running in demo mode
*
* if DEMO_MODE is defined and set to true in config.php this function will return false,
* the main purpose of which is to disable parsing of stuff that is undesirable for a
* board running with a publicly accessible admin control panel
*
* @return	boolean
*/
function is_demo_mode()
{
	return (defined('DEMO_MODE') AND DEMO_MODE == true) ? true : false;
}

// #############################################################################
/**
* Browser detection system - returns whether or not the visiting browser is the one specified
*
* @param	string	Browser name (opera, ie, mozilla, firebord, firefox... etc. - see $is array)
* @param	float	Minimum acceptable version for true result (optional)
*
* @return	boolean
*/
function is_browser($browser, $version = 0)
{
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

// #############################################################################
/**
* Returns the complete array of StyleVars
*
* @param	array	(ref) Style info array
* @param	array	User info array
*
* @return	array
*/
function fetch_stylevars(&$style, $userinfo)
{
	global $vbulletin;

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
	if ($userinfo['lang_options'] & $vbulletin->bf_misc_languageoptions['direction'])
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

	if ($userinfo['lang_options'] & $vbulletin->bf_misc_languageoptions['dirmark'])
	{
		$stylevar['dirmark'] = ($stylevar['textdirection'] == 'ltr') ? '&lrm;' : '&rlm;';
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

// #############################################################################
/**
* Function to override various settings in $vbulletin->options depending on user preferences
*
* @param	array	User info array
*/
function fetch_options_overrides($userinfo)
{
	global $vbulletin;

	if ($userinfo['lang_dateoverride'] != '')
	{
		$vbulletin->options['dateformat'] = $userinfo['lang_dateoverride'];
	}
	if ($userinfo['lang_timeoverride'] != '')
	{
		$vbulletin->options['timeformat'] = $userinfo['lang_timeoverride'];
	}
	if ($userinfo['lang_registereddateoverride'] != '')
	{
		$vbulletin->options['registereddateformat'] = $userinfo['lang_registereddateoverride'];
	}
	if ($userinfo['lang_calformat1override'] != '')
	{
		$vbulletin->options['calformat1'] = $userinfo['lang_calformat1override'];
	}
	if ($userinfo['lang_calformat2override'] != '')
	{
		$vbulletin->options['calformat2'] = $userinfo['lang_calformat2override'];
	}
	if ($userinfo['lang_logdateoverride'] != '')
	{
		$vbulletin->options['logdateformat'] = $userinfo['lang_logdateoverride'];
	}
	if ($userinfo['lang_locale'] != '')
	{
		$locale1 = setlocale(LC_TIME, $userinfo['lang_locale']);
		$locale2 = setlocale(LC_CTYPE, $userinfo['lang_locale']);
	}
}

// #############################################################################
/**
* Returns the initial $vbphrase array
*
* @return	array
*/
function init_language()
{
	global $vbulletin, $phrasegroups;
	global $copyrightyear, $timediff, $timenow, $datenow;

	// define languageid
	define('LANGUAGEID', iif(empty($vbulletin->userinfo['languageid']), $vbulletin->options['languageid'], $vbulletin->userinfo['languageid']));

	// define language direction (preferable to use $stylevar[textdirection])
	define('LANGUAGE_DIRECTION', iif(($vbulletin->userinfo['lang_options'] & $vbulletin->bf_misc_languageoptions['direction']), 'ltr', 'rtl'));

	// define html language code (lang="xyz") (preferable to use $stylevar[languagecode])
	define('LANGUAGE_CODE', $vbulletin->userinfo['lang_code']);

	// initialize the $vbphrase array
	$vbphrase = array();

	// populate the $vbphrase array with phrase groups
	foreach ($phrasegroups AS $phrasegroup)
	{
		$tmp = unserialize($vbulletin->userinfo["phrasegroup_$phrasegroup"]);
		if (is_array($tmp))
		{
			$vbphrase = array_merge($vbphrase, $tmp);
		}
		unset($vbulletin->userinfo["phrasegroup_$phrasegroup"], $tmp);
	}

	// prepare phrases for construct_phrase / sprintf use
	//$vbphrase = preg_replace('/\{([0-9]+)\}/siU', '%\\1$s', $vbphrase);

	// pre-parse some global phrases
	$tzoffset = iif($vbulletin->userinfo['tzoffset'], ' ' . $vbulletin->userinfo['tzoffset']);
	$vbphrase['all_times_are_gmt_x_time_now_is_y'] = construct_phrase($vbphrase['all_times_are_gmt_x_time_now_is_y'], $tzoffset, $timenow, $datenow);
	$vbphrase['vbulletin_copyright'] = construct_phrase($vbphrase['vbulletin_copyright'], $vbulletin->options['templateversion'], $copyrightyear);
	$vbphrase['powered_by_vbulletin'] = construct_phrase($vbphrase['powered_by_vbulletin'], $vbulletin->options['templateversion'], $copyrightyear);
	$vbphrase['timezone'] = construct_phrase($vbphrase['timezone'], $timediff, $timenow, $datenow);

	// all done
	return $vbphrase;
}

// #############################################################################
/**
* Constructs a language chooser HTML menu
*
* @param	integer	Style ID
* @param	boolean	Whether or not this will build the quick chooser menu
*
* @return	string
*/
function construct_language_options($depthmark = '', $quickchooser = false)
{
	global $vbulletin, $vbphrase, $languagecount;

	$thislanguageid = iif($quickchooser, $vbulletin->userinfo['languageid'], $vbulletin->userinfo['reallanguageid']);
	if ($thislanguageid == 0 AND $quickchooser)
	{
		$thislanguageid = $vbulletin->options['languageid'];
	}

	$languagelist = '';
	// set the user's 'real language id'
	if (!isset($vbulletin->userinfo['reallanguageid']))
	{
		$vbulletin->userinfo['reallanguageid'] = $vbulletin->userinfo['languageid'];
	}

	if (!$quickchooser)
	{
		if ($thislanguageid == 0)
		{
			$optionselected = 'selected="selected"';
		}
		$optionvalue = 0;
		$optiontitle = $vbphrase['use_forum_default'];
		eval ('$languagelist .= "' . fetch_template('option') . '";');
	}

	if ($vbulletin->languagecache === null)
	{
		$vbulletin->languagecache = array();
	}

	foreach ($vbulletin->languagecache AS $language)
	{
		if ($language['userselect']) # OR $vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
		{
			$languagecount++;
			if ($thislanguageid == $language['languageid'])
			{
				$optionselected = 'selected="selected"';
			}
			else
			{
				$optionselected = '';
			}
			$optionvalue = $language['languageid'];
			$optiontitle = $depthmark . ' ' . $language['title'];
			eval ('$languagelist .= "' . fetch_template('option') . '";');
		}
	}

	return $languagelist;
}

// #############################################################################
/**
* Constructs a style chooser HTML menu
*
* @param	integer	Style ID
* @param	string	String repeated before style name to indicate nesting
* @param	boolean	Whether or not to initialize this function (this function is recursive)
* @param	boolean	Whether or not this will build the quick chooser menu
*
* @return	string
*/
function construct_style_options($styleid = -1, $depthmark = '', $init = true, $quickchooser = false)
{
	global $vbulletin, $stylevar, $vbphrase, $stylecount;

	$thisstyleid = iif($quickchooser, $vbulletin->userinfo['styleid'], $vbulletin->userinfo['realstyleid']);
	if ($thisstyleid == 0 AND $quickchooser)
	{
		$thisstyleid = $vbulletin->options['styleid'];
	}

	// initialize various vars
	if ($init)
	{
		$stylesetlist = '';
		// set the user's 'real style id'
		if (!isset($vbulletin->userinfo['realstyleid']))
		{
			$vbulletin->userinfo['realstyleid'] = $vbulletin->userinfo['styleid'];
		}

		if (!$quickchooser)
		{
			if ($thisstyleid == 0)
			{
				$optionselected = 'selected="selected"';
			}
			$optionvalue = 0;
			$optiontitle = $vbphrase['use_forum_default'];
			eval ('$stylesetlist .= "' . fetch_template('option') . '";');
		}
	}

	// check to see that the current styleid exists
	// and workaround a very very odd bug (#2079)
	if (is_array($vbulletin->stylecache["$styleid"]))
	{
		$cache =& $vbulletin->stylecache["$styleid"];
	}
	else if (is_array($vbulletin->stylecache[$styleid]))
	{
		$cache =& $vbulletin->stylecache[$styleid];
	}
	else
	{
		return;
	}

	// loop through the stylecache to get results
	foreach ($cache AS $x)
	{
		foreach ($x AS $style)
		{
			if ($style['userselect'] OR $vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
			{
				$stylecount++;
				if ($thisstyleid == $style['styleid'])
				{
					$optionselected = 'selected="selected"';
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

// #############################################################################
/**
* Saves the specified data into the datastore
*
* @param	string	The name of the datastore item to save
* @param	mixed	The data to be saved
*/
function build_datastore($title = '', $data = '')
{
	global $vbulletin;

	if ($title != '')
	{
		/*insert query*/
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "datastore
				(title, data)
			VALUES
				('" . $vbulletin->db->escape_string(trim($title)) . "', '" . $vbulletin->db->escape_string(trim($data)) . "')
		");

		if (method_exists($vbulletin->datastore, 'build'))
		{
			$vbulletin->datastore->build($title, $data);
		}
	}
}

// #############################################################################
/**
* Escapes quotes in strings destined for Javascript
*
* @param	string	String to be prepared for Javascript
* @param	string	Type of quote (single or double quote)
*
* @return	string
*/
function addslashes_js($text, $quotetype = "'")
{
	if ($quotetype == "'")
	{
		// single quotes
		return str_replace(array('\\', '\'', "\n", "\r"), array('\\\\', "\\'","\\n", "\\r"), $text);
	}
	else
	{
		// double quotes
		return str_replace(array('\\', '"', "\n", "\r"), array('\\\\', "\\\"","\\n", "\\r"), $text);
	}
}

// #############################################################################
/**
* Returns the provided string with occurences of replacement variables replaced with their appropriate replacement values
*
* @param	string	Text containing replacement variables
*
* @return	string
*/
function process_replacement_vars($newtext)
{
	global $vbulletin, $style, $stylevar;
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

		$newtext = preg_replace(array_keys($replacementvars), $replacementvars, $newtext);
	}

	return $newtext;
}

// #############################################################################
/**
* Finishes off the current page (using templates), prints it out to the browser and halts execution
*
* @param	string	The HTML of the page to be printed
* @param	boolean	Send the content length header?
*/
function print_output($vartext, $sendheader = true)
{
	global $pagestarttime, $querytime, $vbulletin;
	global $vbphrase, $stylevar;

	if ($vbulletin->options['addtemplatename'])
	{
		if ($doctypepos = @strpos($vartext, $stylevar['htmldoctype']))
		{
			$comment = substr($vartext, 0, $doctypepos);
			$vartext = substr($vartext, $doctypepos + strlen($stylevar['htmldoctype']));
			$vartext = $stylevar['htmldoctype'] . "\n" . $comment . $vartext;
		}
	}

	if (!empty($vbulletin->db->explain) OR $vbulletin->debug)
	{
		$pageendtime = microtime();

		$starttime = explode(' ', $pagestarttime);
		$endtime = explode(' ', $pageendtime);

		$totaltime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];

		$vartext .= "<!-- Page generated in " . vb_number_format($totaltime, 5) . " seconds with " . $vbulletin->db->querycount . " queries -->";
	}

	// --------------------------------------------------------------------
	// temporary code
	global $_TEMPLATEQUERIES, $tempusagecache, $DEVDEBUG;
	if ($vbulletin->debug)
	{
		devdebug('php_sapi_name(): ' . SAPI_NAME);

		$messages = '';
		if (is_array($DEVDEBUG))
		{
			foreach($DEVDEBUG AS $debugmessage)
			{
				$messages .= "\t<option>" . htmlspecialchars_uni($debugmessage) . "</option>\n";
			}
		}

		$debughtml = '<table cellpadding="0" cellspacing="0" border="0" width="' . $stylevar['outertablewidth'] . '" align="center"><tr><td>';
		$debughtml .=  '<hr />';
		//$debughtml .= "<ul><a href=\"#\" onclick=\"set_cookie('vbulletin_collapse', ''); window.location=window.location\">vbulletin_collapse</a>:<br /><li>" . str_replace("\n", '</li><li>', $_COOKIE['vbulletin_collapse']) . "</li></ul>";
		$debughtml .= "\n<form name=\"debugger\" action=\"" . $vbulletin->scriptpath . "\">\n<div align=\"center\">\n<!--querycount-->Executed <b>{$vbulletin->db->querycount}</b> queries<!--/querycount-->" . iif($_TEMPLATEQUERIES, " (<b>" . sizeof($_TEMPLATEQUERIES) . "</b> queries for uncached templates)", '') . " . ";
		$debughtml .= " (<a href=\"" . $vbulletin->scriptpath . iif(strpos($vbulletin->scriptpath, '?') === false, '?', '&amp;') . "tempusage=1\">Template Usage</a>) (<a href=\"" . ($vbulletin->scriptpath) . iif(strpos($vbulletin->scriptpath, '?') === false, '?', '&amp;') . "explain=1\">Explain</a>)<br />\n";
		$debughtml .= "<select>\n\t<option>(Page Generated in " . vb_number_format($totaltime, 5) . " Seconds)</option>\n$messages</select>\n";

		if (is_array($tempusagecache))
		{
			global $vbcollapse;
			$debughtml .= "\n<br /><br />\n<table class=\"tborder\" cellpadding=\"3\" cellspacing=\"1\" align=\"center\" width=\"40%\">\n";
			$debughtml .= "<thead><tr align=\"$stylevar[left]\"><td class=\"thead\"><a style=\"float:$stylevar[right]\" href=\"#\" onclick=\"return toggle_collapse('templateusage')\"><img id=\"collapseimg_templateusage\" src=\"$stylevar[imgdir_button]/collapse_thead$vbcollapse[collapseimg_templateusage].gif\" alt=\"\" border=\"0\" /></a>Template Usage</td></tr></thead><tbody id=\"collapseobj_templateusage\" style=\"$vbcollapse[collapseobj_templateusage]\">\n";
			$hiddentemps = '';

			ksort($tempusagecache);
			foreach ($tempusagecache AS $tempname => $times)
			{
				$debughtml .= "<tr><td class=\"alt1\" align=\"$stylevar[left]\"><span class=\"smallfont\">" . iif($_TEMPLATEQUERIES["$tempname"], "<font color=\"red\"><b>$tempname</b></font>", $tempname) . " ($times)</span></td></tr>\n";
				$hiddentemps .= "'$tempname',\n";
			}

			$debughtml .= "</tbody></table>\n\n<!--\n$hiddentemps-->\n";
			$debughtml .= "</div>\n</form></td></tr></table>";
		}
	}
	else
	{
		$debughtml = '';
	}

	if ($vbulletin->debug AND $debughtml != '')
	{
		$vartext = str_replace('</body>', "<!--start debug html-->$debughtml<!--end debug html-->\n</body>", $vartext);
	}

	// end temporary code
	// --------------------------------------------------------------------

	$output = process_replacement_vars($vartext);

	if ($vbulletin->debug AND function_exists('memory_get_usage'))
	{
		$output = preg_replace('#(<!--querycount-->Executed <b>\d+</b> queries<!--/querycount-->)#siU', 'Memory Usage: <strong>' . number_format((memory_get_usage() / 1024)) . 'KB</strong>, \1', $output);
	}

	// parse PHP include ##################
	if (!is_demo_mode())
	{
		($hook = vBulletinHook::fetch_hook('global_complete')) ? eval($hook) : false;
	}

	if ($vbulletin->options['gzipoutput'] AND !headers_sent())
	{
		$output = fetch_gzipped_text($output, $vbulletin->options['gziplevel']);

		if ($sendheader AND $vbulletin->donegzip)
		{
			@header('Content-Length: ' . strlen($output));
		}
	}

	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}

	// show regular page
	if (empty($vbulletin->db->explain))
	{
		echo $output;
	}
	// show explain
	else
	{
		$querytime = $vbulletin->db->time_total;
		echo "\n<b>Page generated in $totaltime seconds with " . $vbulletin->db->querycount . " queries,\nspending $querytime doing MySQL queries and " . ($totaltime - $querytime) . " doing PHP things.\n\n<hr />Shutdown Queries:</b>" . (defined('NOSHUTDOWNFUNC') ? " <b>DISABLED</b>" : '') . "<hr />\n\n";
	}

	// broken if zlib.output_compression is on with Apache 2
	if (SAPI_NAME != 'apache2handler' AND SAPI_NAME != 'apache2filter')
	{
		flush();
	}
	exit;
}

// #############################################################################
/**
* Performs general clean-up after the system exits, such as running shutdown queries
*/
function exec_shut_down()
{
	global $vbulletin;
	global $foruminfo, $threadinfo, $calendarinfo;

	if (VB_AREA == 'Install' OR VB_AREA == 'Upgrade')
	{
		return;
	}

	$vbulletin->db->unlock_tables();

	if ($vbulletin->userinfo['badlocation'])
	{
		$threadinfo = array('threadid' => 0);
		$foruminfo = array('forumid' => 0);
		$calendarinfo = array('calendarid' => 0);
	}

	if (!$vbulletin->options['bbactive'] AND !($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{ // Forum is disabled and this is not someone with admin access
		$vbulletin->userinfo['badlocation'] = 2;
	}

	if (class_exists('vBulletinHook'))
	{
		($hook = vBulletinHook::fetch_hook('global_shutdown')) ? eval($hook) : false;
	}

	if (is_object($vbulletin->session))
	{
		if (!defined('LOCATION_BYPASS'))
		{
			$vbulletin->session->set('inforum', $foruminfo['forumid']);
			$vbulletin->session->set('inthread', $threadinfo['threadid']);
			$vbulletin->session->set('incalendar', $calendarinfo['calendarid']);
		}
		$vbulletin->session->set('badlocation', $vbulletin->userinfo['badlocation']);
		if ($vbulletin->session->vars['loggedin'] == 1 AND !$vbulletin->session->created)
		{
			# If loggedin = 1, this is out first page view after a login so change value to 2 to signify we are past the first page view
			# We do a DST update check if loggedin = 1
			$vbulletin->session->set('loggedin', 2);
		}
		$vbulletin->session->save();
	}

	if (is_array($vbulletin->db->shutdownqueries))
	{
		$vbulletin->db->hide_errors();
		foreach($vbulletin->db->shutdownqueries AS $name => $query)
		{
			if (!empty($query) AND ($name !== 'pmpopup' OR !defined('NOPMPOPUP')))
			{
				$vbulletin->db->query_write($query);
			}
		}
		$vbulletin->db->show_errors();
	}

	exec_mail_queue();

	// Make sure the database connection is closed since it can get hung up for a long time on php4 do to the mysterious echo() lagging issue
	// If NOSHUTDOWNFUNC is defined then this function should always be the last one called, before echoing of data
	if (defined('NOSHUTDOWNFUNC'))
	{
		$vbulletin->db->close();
	}
	$vbulletin->db->shutdownqueries = array();
	// bye bye!
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15862 $
|| ####################################################################
\*======================================================================*/
?>