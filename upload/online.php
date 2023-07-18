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
define('THIS_SCRIPT', 'online');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('wol');

// get special data templates from the datastore
$specialtemplates = array(
	'maxloggedin',
	'wol_spiders',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'forumdisplay_sortarrow',
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'im_skype',
	'WHOSONLINE',
	'whosonlinebit'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'resolveip' => array(
		'whosonline_resolveip'
	)
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_online.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!$vbulletin->options['WOLenable'])
{
	eval(standard_error(fetch_error('whosonlinedisabled')));
}

if (!($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonline']))
{
	print_no_permission();
}


// #######################################################################
// resolve an IP in Who's Online (this uses the WOL permissions)
if ($_REQUEST['do'] == 'resolveip')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'ipaddress' => TYPE_NOHTML,
		'ajax'      => TYPE_BOOL
	));

	// can we actually resolve this?
	if (!($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonlineip']))
	{
		print_no_permission();
	}

	$resolved_host = htmlspecialchars_uni(@gethostbyaddr($vbulletin->GPC['ipaddress']));
	$ipaddress =& $vbulletin->GPC['ipaddress']; // no html'd already

	if ($vbulletin->GPC['ajax'])
	{
		if (empty($resolved_host))
		{
			$resolved_host = $ipaddress;
		}

		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_tag('ipaddress', $resolved_host, array('original' => $ipaddress));
		$xml->print_xml();
	}
	else
	{
		$navbits = construct_navbits(array(
			'online.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['whos_online'],
			'' => $vbphrase['resolve_ip_address']
		));
		eval('$navbar = "' . fetch_template('navbar') . '";');

		eval('print_output("' . fetch_template('whosonline_resolveip') . '");');
	}

	exit;
}

// #######################################################################
// default action

$datecut = TIMENOW - $vbulletin->options['cookietimeout'];
$wol_event = array();
$wol_pm = array();
$wol_calendar = array();
$wol_user = array();
$wol_forum = array();
$wol_link = array();
$wol_thread = array();
$wol_post = array();

// Variables reused in templates
$perpage = $vbulletin->input->clean_gpc('r', 'perpage', TYPE_UINT);
$pagenumber = $vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);
$sortfield = $vbulletin->input->clean_gpc('r', 'sortfield', TYPE_NOHTML);
$sortorder = $vbulletin->input->clean_gpc('r', 'sortorder', TYPE_NOHTML);

$vbulletin->input->clean_array_gpc('r', array(
	'who'		=> TYPE_STR,
	'ua'		=> TYPE_BOOL,
));

($hook = vBulletinHook::fetch_hook('online_start')) ? eval($hook) : false;

// We can support multi page but we still have to grab every record and just throw away what we don't use.
// set defaults
$perpage = sanitize_perpage($perpage, 200, $vbulletin->options['maxthreads']);

if (!$pagenumber)
{
	$pagenumber = 1;
}

$limitlower = ($pagenumber - 1) * $perpage + 1;
$limitupper = $pagenumber * $perpage;

if ($sortorder != 'desc')
{
	$sortorder = 'asc';
	$oppositesort = 'desc';
}
else
{ // $sortorder = 'desc'
	$sortorder = 'desc';
	$oppositesort = 'asc';
}

switch ($sortfield)
{
	case 'location':
		$sqlsort = 'session.location';
		break;
	case 'time':
		$sqlsort = 'session.lastactivity';
		break;
	case 'host':
		$sqlsort = 'session.host';
		break;
	default:
		$sqlsort = 'user.username';
		$sortfield = 'username';
}

$allonly = $vbphrase['all'];
$membersonly = $vbphrase['members'];
$spidersonly = $vbphrase['spiders'];
$guestsonly = $vbphrase['guests'];
$whoselected = array();
$uaselected = array();

switch ($vbulletin->GPC['who'])
{
	case 'members':
		$showmembers = true;
		$whoselected[1] = 'selected="selected"';
		break;
	case 'guests':
		$showguests = true;
		$whoselected[2] = 'selected="selected"';
		break;
	case 'spiders':
		$showspiders = true;
		$whoselected[3] = 'selected="selected"';
		break;
	default:
		$showmembers = true;
		$showguests = true;
		$showspiders = true;
		$vbulletin->GPC['who'] = '';
		$whoselected[0] = 'selected="selected"';
}

if ($vbulletin->GPC['ua'])
{
	$uaselected[1] = 'selected="selected"';
}
else
{
	$uaselected[0] = 'selected="selected"';
}

$reloadurl = ($perpage != 20 ? "pp=$perpage&amp;" : '') .
	($pagenumber != 1 ? "page=$pagenumber&amp;" : '') .
	($sortfield != 'username' ? "sort=$sortfield&amp;" : '') .
	($sortorder == 'desc' ? 'order=desc&amp;' : '') .
	($vbulletin->GPC['who'] != '' ? 'who=' . $vbulletin->GPC['who'] . '&amp;' : '') .
	($vbulletin->GPC['ua'] ? 'ua=1&amp;' : '');

$reloadurl = preg_replace('#&amp;$#s', '', $reloadurl);

if (!empty($reloadurl))
{
	$reloadurl = 'online.php?' . $vbulletin->session->vars['sessionurl'] . $reloadurl;
}
else
{
	$reloadurl = 'online.php' . $vbulletin->session->vars['sessionurl_q'];
}

$sorturl = 'online.php?' . $vbulletin->session->vars['sessionurl'] .
	($vbulletin->GPC['who'] != '' ? 'who=' . $vbulletin->GPC['who'] . '&amp;' : '') . ($vbulletin->GPC['ua'] ? 'ua=1&amp;' : '');

$sorturl = preg_replace('#&amp;$#s', '', $sorturl);

$show['sorturlnoargs'] = ($sorturl == 'online.php?' . $vbulletin->session->vars['sessionurl']);

eval("\$sortarrow[$sortfield] = \"" . fetch_template('forumdisplay_sortarrow') . '";');

$hook_query_fields = $hook_query_joins = $hook_query_where = '';
($hook = vBulletinHook::fetch_hook('online_query')) ? eval($hook) : false;

$allusers = $db->query_read_slave("
	SELECT user.username, session.useragent, session.location, session.lastactivity, user.userid, user.options, session.host, session.badlocation, session.incalendar, user.aim, user.icq, user.msn, user.yahoo, user.skype,
 	IF(user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid, user.usergroupid
	$hook_query_fields
	FROM " . TABLE_PREFIX . "session AS session
	". iif($vbulletin->options['WOLguests'], " LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid) ", ", " . TABLE_PREFIX . "user AS user") ."
	$hook_query_joins
	WHERE session.lastactivity > $datecut
		". iif(!$vbulletin->options['WOLguests'], " AND session.userid = user.userid", "") ."
		$hook_query_where
	ORDER BY $sqlsort $sortorder
");

$moderators = $db->query_read_slave("SELECT DISTINCT userid FROM " . TABLE_PREFIX . "moderator WHERE forumid <> -1");
while ($mods = $db->fetch_array($moderators))
{
	$mod["{$mods[userid]}"] = 1;
}

$count = 0;
$userinfo = array();
$guests = array();

// get buddylist
$buddy = array();
if (trim($vbulletin->userinfo['buddylist']))
{
	$buddylist = preg_split('/( )+/', trim($vbulletin->userinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
	foreach ($buddylist AS $buddyuserid)
	{
		$buddy["$buddyuserid"] = 1;
	}
}

// Refresh cache if the XML has changed
if ($vbulletin->options['enablespiders'] AND $lastupdate = @filemtime(DIR . '/includes/xml/spiders_vbulletin.xml') AND $lastupdate != $vbulletin->wol_spiders['lu'])
{
	require_once(DIR . '/includes/class_xml.php');
	$xmlobj = new vB_XML_Parser(false, DIR . '/includes/xml/spiders_vbulletin.xml');
	$spiderdata = $xmlobj->parse();
	$spiders = array();

	if (is_array($spiderdata['spider']))
	{
		foreach ($spiderdata['spider'] AS $spiderling)
		{
			$addresses = array();
			$identlower = strtolower($spiderling['ident']);
			$spiders['agents']["$identlower"]['name'] = $spiderling['name'];
			$spiders['agents']["$identlower"]['type'] = $spiderling['type'];
			if (is_array($spiderling['addresses']['address']) AND !empty($spiderling['addresses']['address']))
			{
				if (empty($spiderling['addresses']['address'][0]))
				{
					$addresses[0] = $spiderling['addresses']['address'];
				}
				else
				{
					$addresses = $spiderling['addresses']['address'];
				}

				foreach ($addresses AS $key => $address)
				{
					if (in_array($address['type'], array('range', 'single', 'CIDR')))
					{
						$address['type'] = strtolower($address['type']);

						switch($address['type'])
						{
							case 'single':
								$iplong = vb_ip2long($address['value']);
								if ($iplong != -1 AND $iplong !== false)
								{
									$spiders['agents']["$identlower"]['lookup'][] = array(
										'startip' => $iplong,
									);
								}
								break;
							case 'range':
								$ips = explode('-', $address['value']);
								$startip = vb_ip2long(trim($ips[0]));
								$endip = vb_ip2long(trim($ips[1]));
								if ($startip != -1 AND $startip !== false AND $endip != -1 AND $endip !== false AND $startip <= $endip)
								{
									$spiders['agents']["$identlower"]['lookup'][] = array(
										'startip' => $startip,
										'endip'   => $endip,
									);
								}
								break;
							case 'cidr':
								$ipsplit = explode('/', $address['value']);

								$startip = vb_ip2long($ipsplit[0]);
								$mask = $ipsplit[1];

								if ($startip != -1 AND $startip !== false AND $mask <= 31 AND $mask >= 0)
								{
									$hostbits = 32 - $mask;
									$hosts = (2**$hostbits) - 1; // Number of specified IPs

									$endip = $startip + $hosts;

									$spiders['agents']["$identlower"]['lookup'][] = array(
										'startip' => $startip,
										'endip'   => $endip,
									);
								}
								break;
						}
					}
				}

			}
			$spiders['spiderstring'] .= ($spiders['spiderstring'] ? '|' : '') . preg_quote($spiderling['ident'], '#');
		}

		$spiders['lu'] = $lastupdate;


		// Write out spider cache
		require_once(DIR . '/includes/functions_misc.php');
		build_datastore('wol_spiders', serialize($spiders), 1);
		$vbulletin->wol_spiders =& $spiders;
	}
	unset($spiderdata, $xmlobj);
}

require_once(DIR . '/includes/class_postbit.php');
while ($users = $db->fetch_array($allusers))
{
	if ($users['userid'])
	{ // Reg'd Member
		if (!$showmembers)
		{
			continue;
		}

		$users = array_merge($users, convert_bits_to_array($users['options'] , $vbulletin->bf_misc_useroptions));

		$key = $users['userid'];
		if ($key == $vbulletin->userinfo['userid'])
		{ // in case this is the first view for the user, fake it that show up to themself
			$foundviewer = true;
		}
		if (empty($userinfo["$key"]['lastactivity']) OR ($userinfo["$key"]['lastactivity'] < $users['lastactivity']))
		{
			unset($userinfo["$key"]); // need this to sort by lastactivity
			$userinfo["$key"] = $users;
			fetch_musername($users);
			$userinfo["$key"]['musername'] = $users['musername'];
			$userinfo["$key"]['useragent'] = htmlspecialchars_uni($users['useragent']);
			construct_im_icons($userinfo["$key"]);
			if ($users['invisible'])
			{
				if (($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehidden']) OR $key == $vbulletin->userinfo['userid'])
				{
					$userinfo["$key"]['hidden'] = '*';
					$userinfo["$key"]['invisible'] = 0;
				}
			}
			if ($vbulletin->options['WOLresolve'] AND ($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonlineip']))
			{
				$userinfo["$key"]['host'] = @gethostbyaddr($users['host']);
			}
			$userinfo["$key"]['buddy'] = $buddy["$key"];
		}
	}
	else
	{ // Guest or Spider..
		$spider = '';

		if ($vbulletin->options['enablespiders'] AND !empty($vbulletin->wol_spiders))
		{
			if (preg_match('#(' . $vbulletin->wol_spiders['spiderstring'] . ')#si', $users['useragent'], $agent))
			{
				$agent = strtolower($agent[1]);

				// Check ip address
				if (!empty($vbulletin->wol_spiders['agents']["$agent"]['lookup']))
				{
					$ourip = vb_ip2long($users['host']);
					foreach ($vbulletin->wol_spiders['agents']["$agent"]['lookup'] AS $key => $ip)
					{
						if ($ip['startip'] AND $ip['endip']) // Range or CIDR
						{
							if ($ourip >= $ip['startip'] AND $ourip <= $ip['endip'])
							{
								$spider = $vbulletin->wol_spiders['agents']["$agent"];
								break;
							}
						}
						else if ($ip['startip'] == $ourip) // Single IP
						{
							$spider = $vbulletin->wol_spiders['agents']["$agent"];
							break;
						}
					}
				}
				else
				{
					$spider = $vbulletin->wol_spiders['agents']["$agent"];
				}
			}
		}

		if ($spider)
		{
			if (!$showspiders)
			{
				continue;
			}
			$guests["$count"] = $users;
			$guests["$count"]['spider'] = $spider['name'];
			$guests["$count"]['spidertype'] = $spider['type'];
		}
		else
		{
			if (!$showguests)
			{
				continue;
			}
			$guests["$count"] = $users;
		}

		$guests["$count"]['username'] = $vbphrase['guest'];
		$guests["$count"]['invisible'] = 0;
		$guests["$count"]['displaygroupid'] = 1;
		fetch_musername($guests["$count"]);
		if ($vbulletin->options['WOLresolve'] AND ($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonlineip']))
		{
			$guests["$count"]['host'] = @gethostbyaddr($users['host']);
		}
		$guests["$count"]['count'] = $count + 1;
		$guests["$count"]['useragent'] = htmlspecialchars_uni($users['useragent']);
		$count++;

		($hook = vBulletinHook::fetch_hook('online_user')) ? eval($hook) : false;
	}
}

if (!$foundviewer AND $vbulletin->userinfo['userid'] AND ($vbulletin->GPC['who'] == '' OR $vbulletin->GPC['who'] == 'members'))
{ // Viewing user did not show up so fake him
	construct_im_icons($vbulletin->userinfo);
	$userinfo["{$vbulletin->userinfo['userid']}"] = $vbulletin->userinfo;
	$userinfo["{$vbulletin->userinfo['userid']}"]['location'] = '/online.php';
	$userinfo["{$vbulletin->userinfo['userid']}"]['host'] = IPADDRESS;
	$userinfo["{$vbulletin->userinfo['userid']}"]['lastactivity'] = TIMENOW;
	fetch_musername($userinfo["{$vbulletin->userinfo['userid']}"]);
	$userinfo["{$vbulletin->userinfo['userid']}"]['hidden'] = iif($vbulletin->userinfo['invisible'], '*');
	$userinfo["{$vbulletin->userinfo['userid']}"]['invisible'] = 0;

	if ($vbulletin->options['WOLresolve'] AND ($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonlineip']))
	{
		$userinfo["{$vbulletin->userinfo['userid']}"]['host'] = @gethostbyaddr($userinfo["{$vbulletin->userinfo['userid']}"]['host']);
	}
}

$show['ip'] = iif($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonlineip'], true, false);
$show['ajax_resolve'] = ($show['ip'] AND !$vbulletin->options['WOLresolve']);
$show['useragent'] = iif($vbulletin->GPC['ua'], true, false);
$show['hidden'] = iif($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehidden'], true, false);
$show['badlocation'] = iif($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonlinebad'], true, false);

if (is_array($userinfo))
{
	foreach ($userinfo AS $key => $val)
	{
		if (!$val['invisible'])
		{
			$userinfo["$key"] = process_online_location($val, 1);
		}
	}
}

if (is_array($guests))
{
	foreach ($guests AS $key => $val)
	{
		$guests["$key"] = process_online_location($val, 1);
	}
}

convert_ids_to_titles();

$onlinecolspan = 4;

$bgclass = 'alt1';

if ($vbulletin->options['enablepms'])
{
	$onlinecolspan++;
}

if ($vbulletin->options['displayemails'] OR $vbulletin->options['enablepms'])
{
	$onlinecolspan++;
	exec_switch_bg();
	$contactclass = $bgclass;
}

if ($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonlineip'])
{
	$onlinecolspan++;
	exec_switch_bg();
	$ipclass = $bgclass;
}

if ($vbulletin->options['showimicons'])
{
	$onlinecolspan += 4;
	exec_switch_bg();
	exec_switch_bg();
	exec_switch_bg();
	exec_switch_bg();
}

$numbervisible = 0;
$numberinvisible = 0;
if (is_array($userinfo))
{
	foreach ($userinfo AS $key => $val)
	{
		if (!$val['invisible'])
		{
			$onlinebits .= construct_online_bit($val, 1);
			$numbervisible++;
		}
		else
		{
			$numberinvisible++;
		}
	}
}

$numberguests = 0;
if (is_array($guests))
{
	foreach ($guests AS $key => $val)
	{
		if ($val['activity'] == 'logout' AND $val['badlocation'] == 0)
		{
			continue;
		}
		$numberguests++;
		$onlinebits .= construct_online_bit($val, 1);
	}
}

$totalonline = $numbervisible + $numberguests;

// ### MAX LOGGEDIN USERS ################################
if (intval($vbulletin->maxloggedin['maxonline']) <= $totalonline)
{
	$vbulletin->maxloggedin['maxonline'] = $totalonline;
	$vbulletin->maxloggedin['maxonlinedate'] = TIMENOW;
	build_datastore('maxloggedin', serialize($vbulletin->maxloggedin), 1);
}
$recordusers = $vbulletin->maxloggedin['maxonline'];
$recorddate = vbdate($vbulletin->options['dateformat'], $vbulletin->maxloggedin['maxonlinedate'], true);
$recordtime = vbdate($vbulletin->options['timeformat'], $vbulletin->maxloggedin['maxonlinedate']);

$currenttime = vbdate($vbulletin->options['timeformat']);
$metarefresh = '';

$show['refresh'] = false;
if ($vbulletin->options['WOLrefresh'])
{
	$show['refresh'] = true;
	$refreshargs = ($vbulletin->GPC['who'] ? '&amp;who=' . $vbulletin->GPC['who'] : '') . ($vbulletin->GPC['ua'] ? '&amp;ua=1' : '');
	$refreshargs_js = ($vbulletin->GPC['who'] ? '&who=' . $vbulletin->GPC['who'] : '') . ($vbulletin->GPC['ua'] ? '&ua=1' : '');
	$refreshtime = $vbulletin->options['WOLrefresh'] * 10;
}

$frmjmpsel['wol'] = ' selected="selected" class="fjsel"';
construct_forum_jump();

$pagenav = construct_page_nav($pagenumber, $perpage, $totalonline, 'online.php?' . $vbulletin->session->vars['sessionurl'] . "sort=$sortfield&amp;order=$sortorder&amp;pp=$perpage" . iif($vbulletin->GPC['who'], '&amp;who=' . $vbulletin->GPC['who']) . iif($vbulletin->GPC['ua'], '&amp;ua=1'));
$numbervisible += $numberinvisible;

$colspan = 2;
$colspan = iif($show['ip'], $colspan + 1, $colspan);
$colspan = iif($vbulletin->options['showimicons'], $colspan + 1, $colspan);

($hook = vBulletinHook::fetch_hook('online_complete')) ? eval($hook) : false;

$navbits = construct_navbits(array('' => $vbphrase['whos_online']));
eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('WHOSONLINE') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
