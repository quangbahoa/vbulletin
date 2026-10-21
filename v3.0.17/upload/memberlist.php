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
define('THIS_SCRIPT', 'memberlist');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user', 'search');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'none' => array(
		'memberlist',
		'memberlist_letter',
		'memberlist_results_header',
		'memberlist_resultsbit',
		'memberlist_resultsbit_field',

		'im_aim',
		'im_icq',
		'im_msn',
		'im_yahoo',

		'forumdisplay_sortarrow',
		'postbit_reputation',
	),
	'search' => array(
		'memberlist_search',
		'memberlist_search_radio',
		'memberlist_search_select',
		'memberlist_search_select_multiple',
		'memberlist_search_select',
		'memberlist_search_textbox',
		'memberlist_search_optional_input',

		'userfield_select_option',
		'userfield_radio_option',
		'userfield_checkbox_option',
	)
);

$actiontemplates['getall'] = &$actiontemplates['none'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_misc.php');
require_once('./includes/functions_showthread.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// enabled check
if (!$vboptions['enablememberlist'])
{
	eval(print_standard_error('error_nomemberlist'));
}

// permissions check
if (!($permissions['forumpermissions'] & CANVIEW) OR !($permissions['genericpermissions'] & CANVIEWMEMBERS))
{
	print_no_permission();
}

// default action
if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'getall';
}

// globalize variables
globalize($_REQUEST, array(
	'pagenumber' => INT, // pagenumber
	'perpage' => INT, // results per page
	'ltr' => STR, // start letter or results to find
	'sortfield' => STR, // field by which to sort results
	'sortorder' => STR, // order in which to sort
	'ausername' => STR,
	'homepage' => STR,
	'email' => STR,
	'icq' => STR_NOHTML,
	'aim' => STR,
	'yahoo' => STR,
	'msn' => STR,
	'joindateafter' => STR,
	'joindatebefore' => STR,
	'lastpostafter' => STR,
	'lastpostbefore' => STR,
	'postslower' => INT,
	'postsupper' => INT,
	'usergroupid' => INT
));

// set defaults and sensible values

if ($sortfield == '')
{
	$sortfield = 'username';
}
if ($sortorder == '')
{
	$sortorder = 'asc';
}

// which fields to display?
$show['homepagecol'] = bitwise($vboptions['memberlistfields'], 1);
$show['searchcol'] = bitwise($vboptions['memberlistfields'], 2);
$show['datejoinedcol'] = bitwise($vboptions['memberlistfields'], 4);
$show['postscol'] = bitwise($vboptions['memberlistfields'], 8);
$show['usertitlecol'] = bitwise($vboptions['memberlistfields'], 16);
$show['lastvisitcol'] = bitwise($vboptions['memberlistfields'], 32);
$show['reputationcol'] = iif(bitwise($vboptions['memberlistfields'], 64) AND $vboptions['reputationenable'], 1, 0);
$show['avatarcol'] = iif(bitwise($vboptions['memberlistfields'], 128) AND $vboptions['avatarenabled'], 1, 0);
$show['birthdaycol'] = bitwise($vboptions['memberlistfields'], 256);
$show['agecol'] = bitwise($vboptions['memberlistfields'], 512);
$show['emailcol'] = bitwise($vboptions['memberlistfields'], 1024);
$show['customfields'] = bitwise($vboptions['memberlistfields'], 2048);
$show['imicons'] = bitwise($vboptions['memberlistfields'], 4096);
$show['profilepiccol'] = iif(bitwise($vboptions['memberlistfields'], 8192) AND $permissions['genericpermissions'] & CANSEEPROFILEPIC, 1, 0);
$show['advancedlink'] = false;

// work out total columns
$totalcols = $show['emailcol'] + $show['homepagecol'] + $show['searchcol'] + $show['datejoinedcol'] + $show['postscol'] + $show['lastvisitcol'] + $show['reputationcol'] + $show['avatarcol'] + $show['birthdaycol'] + $show['agecol'] + $show['profilepiccol'];

// build forum jump
construct_forum_jump();

// #############################################################################
// show results
if ($_REQUEST['do'] == 'getall')
{
	$show['advancedlink'] = iif (!$usergroupid, true, false);

	// get conditions
	$condition = '1=1';

	if ($ausername)
	{
		$condition  .=  " AND username LIKE '%" . addslashes_like(htmlspecialchars_uni($ausername)) . "%' ";
	}

	if ($vboptions['usememberlistadvsearch'])
	{
		if ($email)
		{
			$condition .= " AND email LIKE '%" . addslashes_like(htmlspecialchars_uni($email)) . "%' ";
		}
		if ($homepage)
		{
			$condition .= " AND homepage LIKE '%" . addslashes_like(htmlspecialchars_uni($homepage)) . "%' ";
		}
		if ($icq)
		{
			$condition .= " AND icq LIKE '%" . addslashes_like($icq) . "%' ";
		}
		if ($aim)
		{
			$condition .= " AND REPLACE(aim, ' ', '') LIKE '%" . addslashes_like(htmlspecialchars_uni(str_replace(' ', '', $aim))) . "%' ";
		}
		if ($yahoo)
		{
			$condition .= " AND yahoo LIKE '%" . addslashes_like(htmlspecialchars_uni($yahoo)) . "%' ";
		}
		if ($msn)
		{
			$condition .= " AND msn LIKE '%" . addslashes_like(htmlspecialchars_uni($msn)) . "%' ";
		}
		if ($joindateafter)
		{
			$condition .= " AND joindate > UNIX_TIMESTAMP('" . addslashes(strtolower($joindateafter)) . "')";
		}
		if ($joindatebefore)
		{
			$condition .= " AND joindate < UNIX_TIMESTAMP('" . addslashes(strtolower($joindatebefore)) . "')";
		}
		if ($lastpostafter)
		{
			$condition .= " AND lastpost > UNIX_TIMESTAMP('" . addslashes(strtolower($lastpostafter)) . "')";
		}
		if ($lastpostbefore)
		{
			$condition .= " AND lastpost < UNIX_TIMESTAMP('" . addslashes(strtolower($lastpostbefore)) . "')";
		}
		if ($postslower)
		{
			$condition .= " AND posts > $postslower";
		}
		if ($postsupper)
		{
			$condition .= " AND posts < $postsupper";
		}

		// Process Custom Fields..
		$userfields = '';
		$profilefields = $DB_site->query("
			SELECT profilefieldid, type, data, optional, title, memberlist, searchable
			FROM " . TABLE_PREFIX . "profilefield
			WHERE form = 0 "
				. iif(!($permissions['genericpermissions'] & CANSEEHIDDENCUSTOMFIELDS), "	AND hidden = 0") . "
			ORDER BY displayorder
		");

		$urladd = '';
		$profileinfo = array();
		while ($profilefield = $DB_site->fetch_array($profilefields))
		{
			$varname = "field$profilefield[profilefieldid]";

			if ($profilefield['memberlist'])
			{
				$profilefield['varname'] = $varname;
				if ($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple')
				{
						$profilefield['data'] = unserialize($profilefield['data']);
				}
				$profileinfo[] = $profilefield;
			}
			if (!$profilefield['searchable'])
			{
				continue;
			}

			$optionalvar = $varname . '_opt';
			if (isset($_REQUEST["$varname"]))
			{
				$value = &$_REQUEST["$varname"];
			}
			else
			{
				$value = '';
			}

			if (isset($_REQUEST["$optionalvar"]))
			{
				$optvalue = &$_REQUEST["$optionalvar"];
			}
			else
			{
				$optvalue = '';
			}
			$bitwise = 0;
			$sql = '';
			if ($value == '')
			{
				continue;
			}
			if ($profilefield['type'] == 'input' OR $profilefield['type'] == 'textarea')
			{
				$condition .= " AND $varname LIKE '%" . addslashes_like(htmlspecialchars_uni(trim($value))) . "%' ";
				$urladd .= "&amp;$varname=" . urlencode($value);
			}
			if ($profilefield['type'] == 'radio' OR $profilefield['type'] == 'select')
			{
				if (!$value AND (!isset($$optionalvar) OR $$optionalvar == ''))
				{ // The select field was left blank!
					continue; // and the optional field is also empty
				}

				$data = unserialize($profilefield['data']);

				foreach ($data AS $key => $val)
				{
					$key++;
					if ($key == $value)
					{
						$val = trim($val);
						$sql = " AND $varname LIKE '" . addslashes_like($val) . '\' ';
						$url = "&amp;$varname=" . intval($value);
						break;
					}
				}

				if ($profilefield['optional'] AND $optvalue != '')
				{
					$sql = " AND $varname LIKE '%" . addslashes_like(htmlspecialchars_uni(trim($optvalue))) . "%' ";
					$url = "&amp;$varname=" . urlencode($optvalue);
				}
				$condition .= $sql;
				$urladd .= $url;
			}

			if (($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple') AND is_array($value))
			{
				foreach ($value AS $key => $val)
				{
					$condition .= " AND $varname & ". pow(2, $val - 1) . ' ';
					$urladd .= "&amp;$varname" . '[' . urlencode($key) . ']=' . urlencode($val);
				}
			}
		}
	}
	if ($ltr != '')
	{
		if ($ltr == '#')
		{
			$condition = "username NOT REGEXP(\"^[a-zA-Z]\")";
		}
		else
		{
			$ltr = chr(intval(ord($ltr)));
			$condition = 'username LIKE("' . addslashes_like($ltr) . '%")';
		}
	}

	$show['usergroup'] = iif($usergroupid , true, false);

	// Limit to a specific group for usergroup leaders
	if ($usergroupid)
	{
		// check permission to do authorizations in this group
		if (!$leadergroup = $DB_site->query_first("
			SELECT usergroupleader.usergroupleaderid, usergroup.title
			FROM " . TABLE_PREFIX . "usergroupleader AS usergroupleader
			LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroupleader.usergroupid = usergroup.usergroupid)
			WHERE usergroupleader.userid = $bbuserinfo[userid]
				AND usergroupleader.usergroupid = $usergroupid
		"))
		{
			print_no_permission();
		}
		$leadergroup['mtitle'] = $usergroupcache["$usergroupid"]['opentag'] . $leadergroup['title'] . $usergroupcache["$usergroupid"]['closetag'];
		$condition .= " AND FIND_IN_SET('$usergroupid', membergroupids)";
		$usergrouplink = "&amp;usergroupid=$usergroupid";
	}
	else if ($vboptions['memberlistposts'])
	{
		$condition .= " AND posts >= $vboptions[memberlistposts]";
	}

	// specify this if the primary sort will have a lot of tie values (ie, reputation)
	$secondarysortsql = '';
	switch ($sortfield)
	{
		case 'username':
			$sqlsort = 'user.username';
			break;
		case 'joindate':
			$sqlsort = 'user.joindate';
			break;
		case 'posts':
			$sqlsort = 'user.posts';
			break;
		case 'lastvisit':
			$sqlsort = 'lastvisittime';
			break;
		case 'reputation':
			$sqlsort = iif($show['reputationcol'], 'reputationscore', 'user.username');
			$secondarysortsql = ', user.username';
			break;
		default:
			$sqlsort = 'user.username';
			$sortfield = 'username';
	}

	$sortorder = strtolower($sortorder);

	if ($sortorder != 'asc')
	{
		$sortorder = 'desc';
		$oppositesort = 'asc';
	}
	else
	{ // $sortorder = 'ASC'
		$oppositesort = 'desc';
	}


	$sorturl = "memberlist.php?$session[sessionurl]" . "postslower=$postslower&amp;postsupper=$postsupper&amp;ausername=" . urlencode($ausername) . "&amp;homepage=" . urlencode($homepage) . "&amp;icq=" . urlencode($icq) . "&amp;aim=" . urlencode($aim) . "&amp;yahoo=" . urlencode($yahoo) . "&amp;msn=" . urlencode($msn) . "&amp;joindateafter=" . urlencode($joindateafter) . "&amp;joindatebefore=" . urlencode($joindatebefore) . "&amp;lastpostafter=" . urlencode($lastpostafter) . "&amp;lastpostbefore=" . urlencode($lastpostbefore) . iif($usergroupid, "&amp;usergroupid=$usergroupid") . iif(isset($urladd), $urladd);

	eval('$sortarrow[' . $sortfield . '] = "' . fetch_template('forumdisplay_sortarrow') . '";');

	// Seems quicker to grab the ids rather than doing a JOIN
	$ids = -1;
	$hiderepids = -1;
	$hidereparray = array();

	foreach ($usergroupcache AS $ugroupid => $usergroup)
	{
		if ($usergroup['genericoptions'] & SHOWMEMBERLIST)
		{
			$ids .= ",$ugroupid";
		}
		else if ($usergroupid)
		{
			$ids .= ",$ugroupid";
		}

		if ($usergroup['genericpermissions'] & CANHIDEREP)
		{
			$hiderepids .= ",$ugroupid";
			$hidereparray[] = $ugroupid;
		}
	}
	$selectedletter = &$ltr;

	// build letter selector
	// start with non-alpha characters
	$currentletter = '#';
	$linkletter = urlencode('#');
	eval('$letterbits = "' . fetch_template('memberlist_letter') . '";');
	// now do alpha-characters
	for ($i=65; $i < 91; $i++)
	{
		$currentletter = chr($i);
		$linkletter = &$currentletter;
		$show['selectedletter'] = iif($selectedletter == $currentletter, true, false);
		eval('$letterbits .= "' . fetch_template('memberlist_letter') . '";');
	}

	$userscount = $DB_site->query_first("
		SELECT COUNT(*) AS users
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield USING (userid)
		WHERE $condition
			AND	user.usergroupid IN ($ids)
	");
	$totalusers = $userscount['users'];

	if (!$totalusers)
	{
		eval(print_standard_error('error_searchnoresults'));
	}

	// set defaults
	sanitize_pageresults($totalusers, $pagenumber, $perpage, 100, $vboptions['memberlistperpage']);

	$memberlistbit = '';
	$limitlower = ($pagenumber - 1) * $perpage+1;
	$limitupper = ($pagenumber) * $perpage;
	$counter = 0;

	if ($limitupper > $totalusers)
	{
		$limitupper = $totalusers;
		if ($limitlower > $totalusers)
		{
			$limitlower = $totalusers-$perpage;
		}
	}
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}

	if ($permissions['genericpermissions'] & CANSEEHIDDEN)
	{
		$lastvisitcond = " , lastactivity AS lastvisittime ";
	}
	else
	{
		$lastvisitcond = " , IF((options & $_USEROPTIONS[invisible] AND user.userid <> $bbuserinfo[userid]), joindate, lastactivity) AS lastvisittime ";
	}

	if ($show['reputationcol'])
	{
		$repcondition = ",IF((NOT(options & $_USEROPTIONS[showreputation]) AND (user.usergroupid IN ($hiderepids)";

		if (!empty($hidereparray))
		{
			foreach($hidereparray AS $value)
			{
				$repcondition .= " OR FIND_IN_SET('$value', membergroupids)";
			}
		}
		$repcondition .= ")), 0, reputation) AS reputationscore,level";
	}

	$users = $DB_site->query("
		SELECT user.*,usertextfield.*,userfield.*, user.userid, options,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
		$repcondition
		" . iif($show['avatarcol'], ',avatar.avatarpath,NOT ISNULL(customavatar.avatardata) AS hascustomavatar,customavatar.dateline AS avatardateline') ."
		" . iif($show['profilepiccol'], ',customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline') . "
		$lastvisitcond
		" . iif($usergroupid, ", NOT ISNULL(usergroupleader.usergroupid) AS isleader") . "
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid=user.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid=user.userid)
		" . iif($show['reputationcol'], "LEFT JOIN " . TABLE_PREFIX . "reputationlevel AS reputationlevel ON(user.reputationlevelid=reputationlevel.reputationlevelid) ") . "
		" . iif($show['avatarcol'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
		" . iif($show['profilepiccol'], "LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid) ") . "
		" . iif($usergroupid, "LEFT JOIN " . TABLE_PREFIX . "usergroupleader AS usergroupleader ON (user.userid = usergroupleader.userid AND usergroupleader.usergroupid=$usergroupid) ") . "
		WHERE $condition
			AND user.usergroupid IN ($ids)
		ORDER BY $sqlsort $sortorder $secondarysortsql
		LIMIT " . ($limitlower-1) . ", $perpage
	");

	$counter = 0;
	$memberlistbits = '';
	$today_year = vbdate('Y', TIMENOW, false, false);
	$today_month = vbdate('n', TIMENOW, false, false);
	$today_day = vbdate('j', TIMENOW, false, false);
	while ($userinfo = $DB_site->fetch_array($users) AND $counter++ < $perpage)
	{
		$userinfo = array_merge($userinfo , convert_bits_to_array($userinfo['options'] , $_USEROPTIONS));

		// format posts number
		$userinfo['posts'] = vb_number_format($userinfo['posts']);

		$userinfo['musername'] = fetch_musername($userinfo);
		$userinfo['datejoined'] = vbdate($vboptions['dateformat'], $userinfo['joindate'], true);
		$userinfo['lastvisit'] = vbdate($vboptions['dateformat'], $userinfo['lastvisittime'], true);

		if ($userinfo['lastpost'])
		{
			$show['searchlink'] = true;
		}
		else
		{
			$show['searchlink'] = false;
		}
		if ($userinfo['showemail'] AND $vboptions['displayemails'] AND (!$vboptions['secureemail'] OR ($vboptions['secureemail'] AND $vboptions['enableemail'])))
		{
			$show['emaillink'] = true;
		}
		else
		{
			$show['emaillink'] = false;
		}

		construct_im_icons($userinfo, true);

		if ($userinfo['homepage'] != '' AND $userinfo['homepage'] != 'http://')
		{
			$show['homepagelink'] = true;
		}
		else
		{
			$show['homepagelink'] = false;
		}
		if ($userinfo['receivepm'] AND $bbuserinfo['receivepm'] AND $permissions['pmquota'] AND $vboptions['enablepms'])
		{
			$show['pmlink'] = true;
		}
		else
		{
			$show['pmlink'] = false;
		}
		if ($show['birthdaycol'] OR $show['agecol'])
		{
			if (empty($userinfo['birthday']))
			{
				$userinfo['birthday'] = '&nbsp;';
			}
			else
			{
				$bday = explode('-', $userinfo['birthday']);
				if (date('Y') > $bday[2] AND $bday[2] > 1901 AND $bday[2] != '0000')
				{
					$birthdayformat = mktimefix($vboptions['calformat1'], $bday[2]);
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
					$userinfo['birthday'] = vbdate($birthdayformat, mktime(0, 0, 0, $bday[0], $bday[1], $yearpass), false, true, false);
					if ($today_year > $bday[2] AND $bday[2] != '0000')
					{
						$userinfo['age'] = $today_year - $bday[2];
						if ($bday[0] > $today_month)
						{
							$userinfo['age']--;
						}
						else if ($bday[0] == $today_month AND $today_day < $bday[1])
						{
							$userinfo['age']--;
						}
					}
					else
					{
						$userinfo['age'] = '&nbsp;';
					}
				}
				else
				{
					// lets send a valid year as some PHP3 don't like year to be 0
					$userinfo['birthday'] = vbdate($vboptions['calformat2'], mktime(0, 0, 0, $bday[0], $bday[1], 1992), false, true, false);
				}
				if ($userinfo['birthday'] == '')
				{ // This should not be blank but win32 has a bug in regards to mktime and dates < 1970
					if ($bday[2] == '0000')
					{
						$userinfo['birthday'] = "$bday[0]-$bday[1]";
					}
					else
					{
						$userinfo['birthday'] = "$bday[0]-$bday[1]-$bday[2]";
					}
				}
			}
		}
		if ($show['reputationcol'])
		{
			$checkperms = cache_permissions($userinfo, false);
			fetch_reputation_image($userinfo, $checkperms);
		}
		if ($show['profilepiccol'] AND $userinfo['profilepic'])
		{
			$userinfo['profilepic'] = "<img src=\"image.php?u=$userinfo[userid]&amp;type=profile&amp;dateline=$userinfo[profilepicdateline]\" alt=\"\" title=\"$userinfo[username]'s picture\" border=\"0\" />";
		}
		else
		{
			$userinfo['profilepic'] = '&nbsp;';
		}
		if ($show['avatarcol'])
		{
			if ($userinfo['avatarid'])
			{
				$avatarurl = $userinfo['avatarpath'];
			}
			else
			{
				if ($userinfo['hascustomavatar'] AND $vboptions['avatarenabled'])
				{
					if ($vboptions['usefileavatar'])
					{
						$avatarurl = "$vboptions[avatarurl]/avatar$userinfo[userid]_$userinfo[avatarrevision].gif";
					}
					else
					{
						$avatarurl = "image.php?$session[sessionurl]u=$userinfo[userid]&amp;dateline=$userinfo[avatardateline]";
					}
				}
				else
				{
					$avatarurl = '';
				}
			}
			if ($avatarurl == '')
			{
				$show['avatar'] = false;
			}
			else
			{
				$show['avatar'] = true;
			}
		}

		if ($userinfo['customtitle'] == 2)
		{
			$userinfo['usertitle'] = htmlspecialchars_uni($userinfo['usertitle']);
		}
		if ($userinfo['usertitle'] == '')
		{
			$userinfo['usertitle'] = '&nbsp;';
		}

		$bgclass = iif(($totalcols % 2) == 1, 'alt2', 'alt1');

		$customfields = '';
		if ($show['customfields'] AND !empty($profileinfo))
		{
			foreach ($profileinfo AS $index => $value)
			{
				if ($userinfo["$value[varname]"] != '')
				{
					if ($value['type'] == 'checkbox' OR $value['type'] == 'select_multiple')
					{
						unset($customfield);
						foreach ($value['data'] AS $key => $val)
						{
							if ($userinfo["$value[varname]"] & pow(2, $key))
							{
								$customfield .= iif($customfield, ', ') . $val;
							}
						}
					}
					else
					{
						$customfield = $userinfo["$value[varname]"];
					}
				}
				else
				{
					$customfield = '&nbsp;';
				}

				exec_switch_bg();
				eval('$customfields .= "' . fetch_template('memberlist_resultsbit_field') . '";');
			}
		}

		$show['hideleader'] = iif ($userinfo['isleader'], true, false);

		$bgclass = 'alt1';
		eval('$memberlistbits .= "' . fetch_template('memberlist_resultsbit') . '";');
	}  // end while

	$pagenav = construct_page_nav($totalusers, "memberlist.php?$session[sessionurl]do=$_REQUEST[do]", "&amp;ltr=" . urlencode($ltr) . "&amp;pp=$perpage&amp;order=$sortorder&amp;postslower=$postslower&amp;postsupper=$postsupper&amp;sort=$sortfield&amp;ausername=" . urlencode($ausername) . "&amp;homepage=" . urlencode($homepage) . "&amp;icq=" . urlencode($icq) . "&amp;aim=" . urlencode($aim) . "&amp;yahoo=" . urlencode($yahoo) . "&amp;msn=" . urlencode($msn) . "&amp;joindateafter=" . urlencode($joindateafter) . "&amp;joindatebefore=" . urlencode($joindatebefore) . "&amp;lastpostafter=" . urlencode($lastpostafter) . "&amp;lastpostbefore=" . urlencode($lastpostbefore) . iif($usergroupid, "&amp;usergroupid=$usergroupid") . iif(isset($urladd), $urladd));

	unset($customfieldsheader);
	if ($show['customfields'] AND is_array($profileinfo))
	{
		foreach ($profileinfo AS $index => $customfield)
		{
			$customfield = $customfield['title'];
			eval('$customfieldsheader .= "' . fetch_template('memberlist_results_header') . '";');
		}
	}
	// build navbar
	$navbits = array('' => $vbphrase['members_list']);

	$templatename = 'memberlist';

}

// #############################################################################
// advanced search
if ($_REQUEST['do'] == 'search')
{
	if (!$vboptions['usememberlistadvsearch'])
	{
		eval(print_standard_error('error_nomemberlistsearch'));
	}

	$bgclass = 'alt1';
	// get extra profile fields
	$profilefields = $DB_site->query("
		SELECT *
		FROM " . TABLE_PREFIX . "profilefield
		WHERE searchable = 1
			AND form = 0
			" . iif(!($permissions['genericpermissions'] & CANSEEHIDDENCUSTOMFIELDS), " AND hidden = 0") . "
		ORDER BY displayorder
	");

	$customfields = '';
	while ($profilefield = $DB_site->fetch_array($profilefields))
	{
		$profilefieldname="field$profilefield[profilefieldid]";
		$optionalname = $profilefieldname . '_opt';
		exec_switch_bg();
		$optional = '';
		$optionalfield = '';
		if ($profilefield['type'] == 'input' OR $profilefield['type'] == 'textarea')
		{
			$bbuserinfo["$profilefieldname"] = '';
			eval('$customfields .= "' . fetch_template('memberlist_search_textbox') . '";');
		}
		else if ($profilefield['type'] == 'select')
		{
			$profilefield['def'] = 0;
			$data = unserialize($profilefield['data']);
			$selectbits = '';
			$selected = '';
			foreach ($data AS $key => $val)
			{
				$key++;
				eval('$selectbits .= "' . fetch_template('userfield_select_option') . '";');
			}
			if ($profilefield['optional'])
			{
				eval('$optionalfield = "' . fetch_template('memberlist_search_optional_input') . '";');
			}
			$selected = HTML_SELECTED;
			eval('$customfields .= "' . fetch_template('memberlist_search_select') . '";');
		}
		else if ($profilefield['type'] == 'radio')
		{
			$data = unserialize($profilefield['data']);
			$radiobits = '';
			$checked = '';
			foreach ($data AS $key => $val)
			{
				$key++;
				eval('$radiobits .= "' . fetch_template('userfield_radio_option') . '";');
			}
			if ($profilefield['optional'])
			{
				eval('$optionalfield = "' . fetch_template('memberlist_search_optional_input') . '";');
			}
			eval('$customfields .= "' . fetch_template('memberlist_search_radio') . '";');
		}
		else if ($profilefield['type'] == 'checkbox')
		{
			$data = unserialize($profilefield['data']);
			$radiobits = '';
			$perline = 0;
			$checked = '';
			foreach ($data AS $key => $val)
			{
				$key++;
				eval('$radiobits .= "' . fetch_template('userfield_checkbox_option') . '";');
				$perline++;
				if ($profilefield['def'] > 0 AND $perline >= $profilefield['def'])
				{
					$radiobits .= '<br />';
					$perline = 0;
				}
			}
			eval('$customfields .= "' . fetch_template('memberlist_search_radio') . '";');
		}
		else if ($profilefield['type'] == 'select_multiple')
		{
			$data = unserialize($profilefield['data']);
			$selected = '';
			$selectbits = '';
			foreach ($data AS $key => $val)
			{
				$key++;
				eval('$selectbits .= "' . fetch_template('userfield_select_option') . '";');
			}
			eval('$customfields .= "' . fetch_template('memberlist_search_select_multiple') . '";');
		}
	}

	// build navbar
	$navbits = array(
		"memberlist.php?$session[sessionurl]" => $vbphrase['members_list'],
		'' => $vbphrase['advanced_search']
	);

	$templatename = 'memberlist_search';
}

// now spit out the HTML, assuming we got this far with no errors or redirects.

if ($templatename != '')
{
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template($templatename) . '");');
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13227 $
|| ####################################################################
\*======================================================================*/
?>