<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2016 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

// ###################### Start microtime_diff #######################
// get microtime difference between $starttime and NOW
function fetch_microtime_difference($starttime, $addtime = 0)
{
	$finishtime = microtime();
	$starttime = explode(' ', $starttime);
	$finishtime = explode(' ', $finishtime);
	return $finishtime[0] - $starttime[0] + $finishtime[1] - $starttime[1] + $addtime;
}

// ###################### Start mktimefix #######################
// mktime() workaround for < 1970
function mktimefix($format, $year)
{
	$two_digit_year = substr($year, 2);

	// note: the ISO (%G) replaces here are not always correct, but it's the best we can do
	$replace = array(
		'%Y' => $year,
		'%y' => $two_digit_year,
		'%G' => $year,
		'%g' => $two_digit_year,
		'Y'  => $year,
		'y'  => $two_digit_year,
		'o'  => $year
	);

	return str_replace(array_keys($replace), $replace, $format);
}

// ###################### Start getlanguagesarray #######################
function fetch_language_titles_array($titleprefix = '', $getall = true)
{
	global $vbulletin;

	$out = array();

	$languages = $vbulletin->db->query_read_slave("
		SELECT languageid, title
		FROM " . TABLE_PREFIX . "language
		" . iif($getall != true, ' WHERE userselect = 1')
	);
	while ($language = $vbulletin->db->fetch_array($languages))
	{
		$out["$language[languageid]"] = $titleprefix . $language['title'];
	}

	asort($out);

	return $out;
}

// ###################### Start makefolderjump #######################
function construct_folder_jump($foldertype = 0, $selectedid = false, $exclusions = false, $sentfolders = '')
{
	global $vbphrase, $folderid, $folderselect, $foldernames, $messagecounters, $subscribecounters, $folder;
	global $vbulletin;
	// 0 indicates PMs
	// 1 indicates subscriptions
	// get all folder names (for dropdown)
	// reference with $foldernames[#] .

	$folderjump = '';
	if (!is_array($foldernames))
	{
		$foldernames = array();
	}

	switch($foldertype)
	{
		case 0:
		    // get PM folders total
		    $pmcounts = $vbulletin->db->query_read_slave("
		        SELECT COUNT(*) AS total, folderid
		        FROM " . TABLE_PREFIX . "pm AS pm
		        WHERE userid = " . $vbulletin->userinfo['userid'] . "
		        GROUP BY folderid
		    ");
		    $messagecounters = array();
		    while ($pmcount = $vbulletin->db->fetch_array($pmcounts))
		    {
		        $messagecounters["$pmcount[folderid]"] = $pmcount['total'];
    		}

			$folderfield = 'pmfolders';
			$folders = array('0' => $vbphrase['inbox'], '-1' => $vbphrase['sent_items']);
			if (!empty($vbulletin->userinfo["$folderfield"]))
			{
				$userfolder = vb_unserialize($vbulletin->userinfo["$folderfield"]);
				if (is_array($userfolder))
				{
					$folders = $folders + $userfolder;
				}
			}
			$counters =& $messagecounters;
			break;
		case 1:

		    // get Subscription folder totals
		    $foldertotals = $vbulletin->db->query_read_slave("
		        SELECT COUNT(*) AS total, folderid
		        FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		        LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = subscribethread.threadid)
		        WHERE subscribethread.userid = " . $vbulletin->userinfo['userid'] . "
		            AND thread.visible = 1
		            AND canview = 1
		        GROUP BY folderid
		    ");
		    $subscribecounters = array();
		    while ($foldertotal = $vbulletin->db->fetch_array($foldertotals))
		    {
		        $subscribecounters["$foldertotal[folderid]"] = intval($foldertotal['total']);
    		}

			$folderfield = 'subfolders';
			$folders = iif($sentfolders, $sentfolders, vb_unserialize($vbulletin->userinfo["$folderfield"]));
			if (!$folders[0])
			{
				$folders[0] = $vbphrase['subscriptions'];
				asort($folders, SORT_STRING);
			}
			$counters =& $subscribecounters;
			break;
		default:
			return;
	}

	if (is_array($folders))
	{
		foreach($folders AS $_folderid => $_foldername)
		{
			if (is_array($exclusions) AND in_array($_folderid, $exclusions))
			{
				continue;
			}
			else
			{
				$foldernames["$_folderid"] = $_foldername;
				$folderjump .= "<option value=\"$_folderid\" " . iif($_folderid == $selectedid, 'selected="selected"') . ">$_foldername" . iif(is_array($counters), ' (' . intval($counters["$_folderid"]) . iif($foldertype == 1, " $vbphrase[threads])", " $vbphrase[messages])")) . "</option>\n";
				if ($_folderid == $selectedid AND $selectedid !== false)
				{
					$folder = $_foldername;
				}
			}
		}
	}

	return $folderjump;

}

// ###################### Start vbmktime #######################
function vbmktime($hours = 0, $minutes = 0, $seconds = 0, $month = 0, $day = 0, $year = 0)
{
	global $vbulletin;

	return mktime(intval($hours), intval($minutes), intval($seconds), intval($month), intval($day), intval($year)) + $vbulletin->options['hourdiff'];
}

// ###################### Start gmvbdate #####################
function vbgmdate($format, $timestamp, $doyestoday = false, $locale = true)
{
	return vbdate($format, $timestamp, $doyestoday, $locale, false, true);
}

// ###################### Start fetch period group #####################
function fetch_period_group($itemtime)
{
	global $vbphrase, $vbulletin;
	static $periods;

	// create the periods array if it does not exist
	if (empty($periods))
	{
		$daynum = -1;
		$i = 0;

		// make $vbulletin->userinfo's startofweek setting agree with the date() function
		$weekstart = $vbulletin->userinfo['startofweek'] - 1;

		// get the timestamp for the beginning of today, according to vbulletin->userinfo's timezone
		$timestamp = vbmktime(0, 0, 0, vbdate('m', TIMENOW, false, false), vbdate('d', TIMENOW, false, false), vbdate('Y', TIMENOW, false, false));

		// initialize $periods array with stamp for today
		$periods = array('today' => $timestamp);

		// create periods for today, yesterday and all days until we hit the start of the week
		while ($daynum != $weekstart AND $i++ < 7)
		{
			// take away 24 hours
			$timestamp -= 86400;

			// get the number of the current day
			$daynum = vbdate('w', $timestamp, false, false);

			if ($i == 1)
			{
				$periods['yesterday'] = $timestamp;
			}
			else
			{
				$periods[strtolower(vbdate('l', $timestamp, false, false))] = $timestamp;
			}
		}

		// create periods for Last Week, 2 Weeks Ago, 3 Weeks Ago and Last Month
		$periods['last_week'] = $timestamp -= (7 * 86400);
		$periods['2_weeks_ago'] = $timestamp -= (7 * 86400);
		$periods['3_weeks_ago'] = $timestamp -= (7 * 86400);
		$periods['last_month'] = $timestamp -= (7 * 86400);
	}

	foreach ($periods AS $periodname => $periodtime)
	{
		if ($itemtime >= $periodtime)
		{
			return $periodname;
		}
	}

	return 'older';
}

/**
 * Fetches a character group id for a string
 *
 * @param string $str
 * @return string
 */
function fetch_char_group($str)
{
	$str = trim($str);
	$chr = strtolower(fetch_try_to_ascii($str[0]));

	if (is_numeric($chr))
	{
		return '0_to_9';
	}
	else if($chr >= 'a' AND $chr <= 'h')
	{
		return 'a_to_h';
	}
	else if($chr >= 'i' AND $chr <= 'p')
	{
		return 'i_to_p';
	}
	else if($chr >= 'q' AND $chr <= 'z')
	{
		return 'q_to_z';
	}
	else
	{
		return 'other';
	}
}


/**
 * Tries to convert a character to it's closest non extended ascii equivelant
 *
 * @param string $chr							- The character to convert
 * @returns string								- The result
 */
function fetch_try_to_ascii($chr)
{
	$conv = array(
		'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a', 'Æ' => 'e', 'Ç' => 'c',
		'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
		'Ð' => 'd', 'Ñ' => 'n', 'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o', 'Ø' => 'o',
		'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u', 'Ý' => 'y', 'à' => 'a', 'á' => 'a', 'â' => 'a',
		'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
		'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o',
		'õ' => 'o', 'ö' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y'
	);

	return (isset($conv[$chr]) ? $conv[$chr] : $chr);
}

/**
 * Converts a timestamp into an array of dmY
 *
 * @param integer $timestamp
 * @returns array | boolean false
 */
function fetch_datearray_from_timestamp($timestamp = TIMENOW)
{
	global $vbulletin;

	if ($timestamp = $vbulletin->input->clean($timestamp, TYPE_UNIXTIME))
	{
		$datearray = array(
			'day' => date('d', $timestamp),
			'month' => date('n', $timestamp),
			'year' => date('Y', $timestamp)
		);

		return $datearray;
	}

	return false;
}

// ###################### Start array2bits #######################
// takes an array and returns the bitwise value
function convert_array_to_bits(&$arry, $_FIELDNAMES, $unset = 0)
{
	$bits = 0;
	foreach($_FIELDNAMES AS $fieldname => $bitvalue)
	{
		if ($arry["$fieldname"] == 1)
		{
			$bits += $bitvalue;
		}
		if ($unset)
		{
			unset($arry["$fieldname"]);
		}
	}
	return $bits;
}

// ###################### Start bitwise #######################
// Returns 1 if the bitwise is successful, 0 other wise
// usage bitwise($perms, UG_CANMOVE);
function bitwise($value, $bitfield)
{
	// Do not change this to return true/false!

	return iif(intval($value) & $bitfield, 1, 0);
}

// ###################### Start echoarray #######################
// recursively prints out an array
function print_array($array, $title = NULL, $htmlisekey = false, $indent = '')
{
	global $vbphrase;
	if ($title === NULL)
	{
		$title = 'My Array';
	}
	if (is_array($array))
	{
		echo iif(empty($indent), "<div class=\"echoarray\">\n") . "$indent<li><b" . iif(empty($indent), ' style="font-size: larger"', '').">" . iif($htmlisekey, htmlspecialchars_uni($title), $title) . "</b><ul>\n";
		foreach ($array AS $key => $val)
		{
			if (is_array($val))
			{
				print_array($val, $key, $htmlisekey, $indent."\t");
			}
			else
			{
				echo "$indent\t<li>" . iif($htmlisekey, htmlspecialchars_uni($key), $key) . " = '<i>" . htmlspecialchars_uni($val) . "</i>'</li>\n";
			}
		}
		echo iif(empty($indent), "</div>\n") . "$indent</ul></li>\n";
	}
}

// ###################### Start getChildForums #######################
function fetch_child_forums($parentid, $return = 'STRING', $glue = ',')
{
	global $vbulletin, $allforumcache;
	static $childlist;

	if (!is_array($allforumcache))
	{
		if ($return == 'ARRAY')
		{
			$childlist = array();
		}
		else
		{
			$childlist = 0;
		}
		foreach(array_keys($vbulletin->forumcache) AS $forumid)
		{
			$f =& $vbulletin->forumcache["$forumid"];
			$allforumcache["$f[parentid]"]["$forumid"] = $forumid;
		}
	}

	if (!is_array($allforumcache["$parentid"]))
	{
		return $childlist;
	}
	else
	{
		foreach($allforumcache["$parentid"] AS $forumid)
		{
			if ($return == 'ARRAY')
			{
				$childlist[] = $forumid;
			}
			else
			{
				$childlist .= "$glue$forumid";
			}
			fetch_child_forums($forumid, $return, $glue);
		}
	}

	return $childlist;
}

/**
* Marks a forum, its child forums and all contained posts as read
*
* @param	integer	Forum ID to be marked as read - leave blank to mark all forums as read
*
* @return	array	Array of affected forum IDs
*/
function mark_forums_read($forumid = false)
{
	global $vbulletin;

	$db =& $vbulletin->db;

	$return_url = $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'];
	$return_phrase = 'markread';
	$return_forumids = array();

	if (!$forumid)
	{
		if ($vbulletin->userinfo['userid'])
		{
			// init user data manager
			$userdata = datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
			$userdata->set_existing($vbulletin->userinfo);
			$userdata->set('lastactivity', TIMENOW);
			$userdata->set('lastvisit', TIMENOW - 1);
			$userdata->save();

			$query = '';
			foreach ($vbulletin->forumcache AS $fid => $finfo)
			{
				// mark the forum and all child forums read
				$query .= ", ($fid, " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ")";
			}

			if ($query)
			{
				$query = substr($query, 2);
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "forumread
						(forumid, userid, readtime)
					VALUES
						$query
				");
			}
		}
		else
		{
			vbsetcookie('lastvisit', TIMENOW);
		}

		$return_forumids = array_keys($vbulletin->forumcache);
	}
	else
	{
		// temp work around code, I need to find another way to mass set some values to the cookie
		$vbulletin->input->clean_gpc('c', COOKIE_PREFIX . 'forum_view', TYPE_STR);
		global $bb_cache_forum_view;
		$bb_cache_forum_view = vb_unserialize(convert_bbarray_cookie($vbulletin->GPC[COOKIE_PREFIX . 'forum_view']));

		require_once(DIR . '/includes/functions_misc.php');
		$childforums = fetch_child_forums($forumid, 'ARRAY');

		$return_forumids = $childforums;
		$return_forumids[] = $forumid;

		if ($vbulletin->userinfo['userid'])
		{
			$query = "($forumid, " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ")";

			foreach ($childforums AS $child_forumid)
			{
				// mark the forum and all child forums read
				$query .= ", ($child_forumid, " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ")";
			}

			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "forumread
					(forumid, userid, readtime)
				VALUES
					$query
			");

			require_once(DIR . '/includes/functions_bigthree.php');
			$foruminfo = fetch_foruminfo($forumid);
			$parent_marks = mark_forum_read($foruminfo, $vbulletin->userinfo['userid'], TIMENOW);
			if (is_array($parent_marks))
			{
				$return_forumids = array_unique(array_merge($return_forumids, $parent_marks));
			}
		}
		else
		{
			foreach ($childforums AS $child_forumid)
			{
				// mark the forum and all child forums read
				$bb_cache_forum_view["$child_forumid"] = TIMENOW;
			}
			set_bbarray_cookie('forum_view', $forumid, TIMENOW);
		}

		if ($vbulletin->forumcache["$forumid"]['parentid'] == -1)
		{
			$return_url = $vbulletin->options['forumhome'] . '.php' . $vbulletin->session->vars['sessionurl_q'];
		}
		else
		{
			$return_url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . 'f=' . $vbulletin->forumcache["$forumid"]['parentid'];
		}

		$return_phrase = 'markread_single';
	}

	return array(
		'url' => $return_url,
		'phrase' => $return_phrase,
		'forumids' => $return_forumids
	);
}

// ###################### Start verify autosubscribe #######################
// function to verify that the subscription type is valid
function verify_subscription_choice($choice, &$userinfo, $default = 9999, $doupdate = true)
{
	global $vbulletin;

	// check that the subscription choice is valid
	switch ($choice)
	{
		// the choice is good
		case 0:
		case 1:
		case 2:
		case 3:
			break;

		// check that ICQ number is valid
		case 4:
			if (!preg_match('#^[0-9\-]+$', $userinfo['icq']))
			{
				// icq number is bad
				if ($doupdate)
				{
					global $vbulletin;

					// init user datamanager
					$userdata = datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
					$userdata->set_existing($userinfo);
					$userdata->set('icq', '');
					$userdata->set('autosubscribe', 1);
					$userdata->save();
				}
				$userinfo['icq'] = '';
				$userinfo['autosubscribe'] = 1;
				$choice = 1;
			}
			break;

		// all other options
		default:
			$choice = $default;
			break;
	}

	return $choice;
}

// ###################### Start countchar #######################
function fetch_character_count($string, $char)
{
	//counts number of times $char occus in $string

	return substr_count(strtolower($string), strtolower($char));
}

/**
* Replaces legacy variable names in templates with their modern equivalents
*
* @param	string	Template to be processed
* @param	boolean	Handle replacement of vars outside of quotes
*
* @return	string
*/
function replace_template_variables($template, $do_outside_regex = false)
{
	// matches references to specifc arrays in templates and maps them to a better internal format
	// this function name is a slight misnomer; it can be run on phrases with variables in them too!

	// include the $, but escape it in the key
	static $variables = array(
		'\$vboptions' => '$GLOBALS[\'vbulletin\']->options',
		'\$bbuserinfo' => '$GLOBALS[\'vbulletin\']->userinfo',
		'\$session' => '$GLOBALS[\'vbulletin\']->session->vars',
	);

	// regexes to do the replacements; __FINDVAR__ and __REPLACEVAR__ are replaced before execution
	static $basic_find = array(
		'#\{__FINDVAR__\[(\\\\?\'|"|)([\w$[\]]+)\\1\]\}#',
		'#__FINDVAR__\[\$(\w+)\]#',
		'#__FINDVAR__\[(\w+)\]#',
	);
	static $basic_replace = array(
		'" . __REPLACEVAR__[$1$2$1] . "',
		'" . __REPLACEVAR__[$$1] . "',
		'" . __REPLACEVAR__[\'$1\'] . "',
	);

	foreach ($variables AS $findvar => $replacevar)
	{
		if ($do_outside_regex)
		{
			// this is handles replacing of vars outside of quotes
			do
			{
				// not in quotes = variable preceeded by an even number of ", does not count " escaped with an odd amount of \
				// ... this was a toughie!
				$new_template = preg_replace(
					array(
						'#^([^"]*?("(?>(?>(\\\\{2})+?)|\\\\"|[^"])*"([^"]*?))*)' . $findvar . '\[(\\\\?\'|"|)([\w$[\]]+)\\5\]#sU',
						'#^([^"]*?("(?>(?>(\\\\{2})+?)|\\\\"|[^"])*"([^"]*?))*)' . $findvar . '([^[]|$)#sU',
					),
					array(
						'$1' . $replacevar . '[$5$6$5]',
						'$1' . $replacevar . '$5',
					),
					$template
				);
				if ($new_template == $template)
				{
					break;
				}
				$template = $new_template;
			}
			while (true);
		}

		// these regular expressions handle replacement of vars inside quotes
		$this_find = str_replace('__FINDVAR__', $findvar, $basic_find);
		$this_replace = str_replace('__REPLACEVAR__', $replacevar, $basic_replace);

		$template = preg_replace($this_find, $this_replace, $template);
	}

	// straight replacements - for example $scriptpath becomes $GLOBALS['vbulletin']->scriptpath
	static $str_replace = array(
		'$scriptpath' => '" . $GLOBALS[\'vbulletin\']->scriptpath . "',
		'$navbar_reloadurl' => '" . $GLOBALS[\'vbulletin\']->reloadurl . "',
	);
	$template = str_replace(array_keys($str_replace), $str_replace, $template);

	return $template;
}

/**
* Returns a hidden input field containing the serialized $_POST array
*
* @return	string	HTML code containing hidden fields
*/
function construct_post_vars_html()
{
	global $vbulletin, $stylevar;

	$vbulletin->input->clean_gpc('p', 'postvars', TYPE_BINARY);
	if ($vbulletin->GPC['postvars'] != '' AND verify_client_string($vbulletin->GPC['postvars']) !== false)
	{
		return '<input type="hidden" name="postvars" value="' . htmlspecialchars_uni($vbulletin->GPC['postvars']) . '" />' . "\n";
	}
	else if ($vbulletin->superglobal_size['_POST'] > 0)
	{
		$postvars_encoded = json_encode($_POST);
		if (empty($postvars_encoded))
		{
			$postvars_utf8 = convert_array_utf8($_POST);
			$postvars_encoded = json_encode($postvars_utf8);
			if (empty($postvars_encoded))
			{
				// It still failed, give up
				trigger_error('Construct Postvars: json_encode failed; utf-8 conversion failed', E_USER_WARNING);
				return '';
			}
		}

		return '<input type="hidden" name="postvars" value="' . htmlspecialchars_uni(sign_client_string($postvars_encoded)) . '" />' . "\n";
	}
	else
	{
		return '';
	}
}

/**
* Returns a collection of <input type="hidden" /> fields containing the values specified in the serialized array provided
*
* @param	string	Serialized array of name=value pairs
*
* @return	string	HTML hidden fields
*/
function construct_hidden_var_fields($serializedarr)
{
	$temp = json_decode($serializedarr, true);

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
		$html .= construct_hidden_var_field_value($key, $val);
	}
	return $html;
}

function construct_hidden_var_field_value($key, $val, $key_prefix = '')
{
	global $vbulletin;

	$html = '';
	if (is_array($val))
	{
		if (empty($key_prefix))
		{
			$key_prefix = $key;
		}
		else
		{
			$key_prefix .= "[$key]";
		}
		foreach ($val AS $key2 => $val2)
		{
			$html .= construct_hidden_var_field_value($key2, $val2, $key_prefix);
		}
	}
	else
	{
		if ($key == 's' AND !$key_prefix)
		{
			$val = $vbulletin->session->vars['dbsessionhash'];
		}

		$key = (!empty($key_prefix) ? $key_prefix . "[$key]" : $key);
		$html .= '<input type="hidden" name="' . htmlspecialchars_uni($key) . '" value="' . htmlspecialchars_uni($val) . '" />' . "\n";
	}
	return $html;
}

/**
* Fetches a specific type of phrase from the database
*
* @param	string	Varname of the phrase to be fetched
* @param	integer	Phrase Type ID of the phrase to be fetched
* @param	string	String to be removed from the beginning of specified phrase varname (varname = 'moo_thing', strreplace = 'moo_' => varname = 'thing')
* @param	boolean	Whether or not to parse any quotes in the fetched text ready for eval()
* @param	boolean	Fetch phrase from all languages (?)
* @param	integer	Desired language ID from which to pull the phrase text
* @param	boolean	If true, converts '{1}' and '{2}' into '%1$s' and '%2$s' etc., in preparation for sprintf() parsing
*
* @return	string
*/
function fetch_phrase($phrasename, $fieldname, $strreplace = '', $doquotes = true, $alllanguages = false, $languageid = -1, $dobracevars = true)
{
	// we need to do some caching in this function I believe
	global $vbulletin, $vbphrase;
	static $phrase_cache;

	if (!defined('LANGUAGEID'))
	{
		define('LANGUAGEID', 0, true);
	}

	if (!empty($strreplace))
	{
		if (strpos("$phrasename", $strreplace) === 0)
		{
			$phrasename = substr($phrasename, strlen($strreplace));
		}
	}

	$languageid = intval($languageid);

	if (!isset($phrase_cache["{$fieldname}-{$phrasename}"]))
	{
		$getphrases = $vbulletin->db->query_read_slave("
			SELECT text, languageid, special
			FROM " . TABLE_PREFIX . "phrase AS phrase
			LEFT JOIN " . TABLE_PREFIX . "phrasetype USING (fieldname)
			WHERE phrase.fieldname = '" . $vbulletin->db->escape_string($fieldname) . "'
				AND varname = '" . $vbulletin->db->escape_string($phrasename) . "' "
				. iif(!$alllanguages, "AND languageid IN (-1, 0, " . ($languageid > 0 ? $languageid : intval(LANGUAGEID)) . ")")
		);
		while ($getphrase = $vbulletin->db->fetch_array($getphrases))
		{
			$phrase_cache["{$fieldname}-{$phrasename}"]["$getphrase[languageid]"] = $getphrase['text'];
			$phrase_cache["{$fieldname}-{$phrasename}"]['special'] = $getphrase['special'];
		}
		unset($getphrase);
		$vbulletin->db->free_result($getphrases);
	}

	$phrase =& $phrase_cache["{$fieldname}-{$phrasename}"];
	$special = $phrase['special'];

	if ($languageid == -1)
	{
		// didn't pass in a languageid as this is the default value so use the browsing user's languageid
		$languageid = LANGUAGEID;
	}
	else if ($languageid == 0)
	{
		// the user is using forum default
		$languageid = $vbulletin->options['languageid'];
	}

	if (isset($phrase["$languageid"]))
	{
		$messagetext = $phrase["$languageid"];
	}
	else if (isset($phrase[0]))
	{
		$messagetext = $phrase[0];
	}
	else if (isset($phrase['-1']))
	{
		$messagetext = $phrase['-1'];
	}
	else if (isset($vbphrase["$phrasename"]) AND (VB_AREA == 'Upgrade' OR VB_AREA == 'Install'))
	{
		$messagetext = $vbphrase["$phrasename"];
	}
	else
	{
		$messagetext = "Could not find phrase '$phrasename'.";
	}

	if ($dobracevars)
	{
		$messagetext = str_replace('%', '%%', $messagetext);
		$messagetext = preg_replace('#\{([0-9]+)\}#sU', '%\\1$s', $messagetext);
	}

	if ($doquotes)
	{
		$messagetext = str_replace("\\'", "'", addslashes($messagetext));
		if ($special)
		{
			// these phrases have variables in them. Thus, they could have variables like $vboptions that need to be replaced
			$messagetext = replace_template_variables($messagetext, false);
		}
	}

	return $messagetext;
}

/**
* Returns either the complete array of timezones, or the timezone phrase name corresponding to $offset
*
* @param	mixed	If specified, returns the corresponding timezone phrase name. Otherwise, all timezones will be returned
*
* @return	mixed
*/
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
		'-4.5' => 'timezone_gmt_minus_0430',
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
		'5.75' => 'timezone_gmt_plus_0545',
		'6'    => 'timezone_gmt_plus_0600',
		'6.5'  => 'timezone_gmt_plus_0630',
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

/**
 * Implodes an array using both values and keys
 *
 * @param string $glue1							- Glue between key and value
 * @param string $glue2							- Glue between value and key
 * @param mixed $array							- Arr to implode
 * @param boolean $skip_empty					- Whether to skip empty elements
 * @return string								- The imploded result
 */
function implode_both($glue1 = '', $glue2 = '', $array, $skip_empty = false)
{
	if (!is_array($array))
	{
		return '';
	}

	foreach ($array as $key => $val)
	{
		if (!$skip_empty OR !empty($val))
		{
			$array[$key] = $key . $glue1 . $val;
		}
		else
		{
			unset($array[$key]);
		}
	}

	return implode($glue2, $array);
}

/**
 * Implodes an assoc array into a partial url query string
 *
 * @param mixed $array							- Array to parse
 * @param boolean $skip_empty					- Whether to skip empty elements
 * @return string								- The parsed result
 */
function urlimplode($array, $skip_empty = true, $skip_urlencode = false)
{
	if (!$skip_urlencode)
	{
		foreach ($array AS $key => $value)
		{
			$array[$key] = urlencode($value);
		}
	}

	return implode_both('=', '&amp;', $array, $skip_empty);
}

/**
 * Checks if a user has currently exceeded their private message quota
 *
 * @param integer $userid						Id of the user to check
 * @return boolean								Whether they have exceeded their quota
 */
function fetch_privatemessage_throttle_reached($userid)
{
	global $vbulletin;

	if (!$vbulletin->options['pmthrottleperiod'])
	{
		return false;
	}

	if (!$vbulletin->userinfo['permissions']['pmthrottlequantity'])
	{
		return false;
	}

	$count = $vbulletin->db->query_first("
		SELECT COUNT(userid) AS total
		FROM " . TABLE_PREFIX . "pmthrottle
		WHERE userid = " . intval($vbulletin->userinfo['userid']) . "
		AND dateline > " . (TIMENOW - ($vbulletin->options['pmthrottleperiod'] * 60))
	);

	return ($count['total'] >= $vbulletin->userinfo['permissions']['pmthrottlequantity']);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 93801 $
|| # $Date: 2017-04-25 06:47:39 -0700 (Tue, 25 Apr 2017) $
|| ####################################################################
\*======================================================================*/
?>
