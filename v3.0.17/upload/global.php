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

// identify where we are
define('VB_AREA', 'Forum');

// #############################################################################
// Start initialisation
require_once('./includes/init.php');

// #############################################################################
// Start functions
if (DB_QUERIES)
{
	// start functions parse timer
	echo "Parsing functions.php\n";
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$beforetime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	echo "Time before: $beforetime\n";
	if (function_exists('memory_get_usage'))
	{
		echo "Memory Before: " . number_format((memory_get_usage() / 1024)) . 'KB' . " \n";
	}
}

require_once('./includes/functions.php');

if (DB_QUERIES)
{
	// end functions parse timer
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$aftertime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	echo "Time after:  $aftertime\n";
	echo "Time taken: " . ($aftertime - $beforetime) . "\n";
	if (function_exists('memory_get_usage'))
	{
		echo "Memory After: " . number_format((memory_get_usage() / 1024)) . 'KB' . " \n";
	}
	echo "\n<hr />\n\n";
}

// #############################################################################
// turn off popups if they are not available to this browser
if ($vboptions['usepopups'])
{
	if ((is_browser('ie', 5) AND !is_browser('mac')) OR is_browser('mozilla') OR is_browser('firebird') OR is_browser('opera', 7) OR is_browser('webkit') OR is_browser('konqueror', 3.2))
	{
		// use popups
	}
	else
	{
		// don't use popups
		$vboptions['usepopups'] = 0;
	}
}

// #############################################################################
// set a variable used by the spacer templates to detect IE versions less than 6
if (is_browser('ie') AND !is_browser('ie', 6))
{
	$show['old_explorer'] = true;
}
else
{
	$show['old_explorer'] = false;
}

// #############################################################################
// read the list of collapsed menus from the 'vbulletin_collapse' cookie
$vbcollapse = array();
if (!empty($_COOKIE['vbulletin_collapse']))
{
	$_val = preg_split('#\n#', $_COOKIE['vbulletin_collapse'], -1, PREG_SPLIT_NO_EMPTY);
	foreach ($_val AS $_key)
	{
		$vbcollapse["collapseobj_$_key"] = 'display:none;';
		$vbcollapse["collapseimg_$_key"] = '_collapsed';
		$vbcollapse["collapsecel_$_key"] = '_collapsed';
	}
}

// #############################################################################
// start sessions

// start server too busy
$servertoobusy = false;
if ($vboptions['loadlimit'] > 0 AND PHP_OS == 'Linux' AND @file_exists('/proc/loadavg') AND $filestuff = @file_get_contents('/proc/loadavg'))
{
	$loadavg = explode(' ', $filestuff);
	if (trim($loadavg[0]) > $vboptions['loadlimit'])
	{
		$servertoobusy = true;
	}
}

if (DB_QUERIES)
{
	// start functions parse timer
	$queryoutput = "<i>Processing sessions.php\n";
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$beforetime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	$queryoutput .= "Time before: $beforetime\n";
	if (function_exists('memory_get_usage'))
	{
		$queryoutput .= "Memory Before: " . number_format((memory_get_usage() / 1024)) . 'KB' . " \n";
	}
	$queryoutput .= "</i>\n";
}

require_once('./includes/sessions.php');

// #############################################################################
// do headers
exec_headers();

if (DB_QUERIES)
{
	echo $queryoutput;

	// end functions parse timer
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$aftertime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	echo "<i>End sessions.php processing\nTime after:  $aftertime\n";
	echo "Time taken: " . ($aftertime - $beforetime) . "\n";
	if (function_exists('memory_get_usage'))
	{
		echo "Memory After: " . number_format((memory_get_usage() / 1024)) . 'KB' . " \n";
	}
	echo "</i>\n<hr />\n\n";
}

// #############################################################################
// set the referrer cookie if URI contains a referrerid
if (!$bbuserinfo['userid'] AND $vboptions['usereferrer'] AND !$_COOKIE[COOKIE_PREFIX . 'referrerid'] AND $_REQUEST['referrerid'])
{
	if ($referrerid = verify_id('user', $_REQUEST['referrerid'], 0))
	{
		vbsetcookie('referrerid', $referrerid);
	}
}

// #############################################################################
// Get date / time info
// override date/time settings if specified
fetch_options_overrides($bbuserinfo);
fetch_time_data();

// global $bbuserinfo setup -- this has to happen after fetch_options_overrides
if ($bbuserinfo['lastvisit'])
{
	$bbuserinfo['lastvisitdate'] = vbdate($vboptions['dateformat'] . ' ' . $vboptions['timeformat'], $bbuserinfo['lastvisit']);
}
else
{
	$bbuserinfo['lastvisitdate'] = -1;
}

// get some useful info
$templateversion = &$vboptions['templateversion'];

// #############################################################################
// initialize $vbphrase and set language constants
$vbphrase = init_language();

// set a default username
if ($bbuserinfo['username'] == '')
{
	$bbuserinfo['username'] = $vbphrase['unregistered'];
}

// #############################################################################
// CACHE PERMISSIONS AND GRAB $permissions
// get the combined permissions for the current user
// this also creates the $fpermscache containing the user's forum permissions

$permissions = cache_permissions($bbuserinfo);
$bbuserinfo['permissions'] = &$permissions;
// #############################################################################

// figure out the chosen style settings
$codestyleid = 0;

// automatically query $getpost, $threadinfo & $foruminfo if $threadid exists
if ($_REQUEST['postid'] AND $postinfo = verify_id('post', $_REQUEST['postid'], 0, 1))
{
	$getpost = $postinfo; // Not needed other than to maintain newreply.php for now.
	$postid = $postinfo['postid'];
	$_REQUEST['threadid'] = $postinfo['threadid'];

}

// automatically query $threadinfo & $foruminfo if $threadid exists
if ($_REQUEST['threadid'] AND $threadinfo = verify_id('thread', $_REQUEST['threadid'], 0, 1))
{
	$threadid = $threadinfo['threadid'];
	$forumid = $threadinfo['forumid'];
	if ($forumid)
	{
		$foruminfo = fetch_foruminfo($threadinfo['forumid']);
		if (($foruminfo['styleoverride'] == 1 OR $bbuserinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
		{
			$codestyleid = $foruminfo['styleid'];
		}
	}
}
// automatically query $foruminfo if $forumid exists
else if ($_REQUEST['forumid'])
{
	$foruminfo = verify_id('forum', $_REQUEST['forumid'], 0, 1);
	$forumid = $foruminfo['forumid'];
	if (($foruminfo['styleoverride'] == 1 OR $bbuserinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
	{
		$codestyleid = $foruminfo['styleid'];
	}
}
// automatically query forum for style info if $pollid exists
else if ($_REQUEST['pollid'])
{
	$pollid = intval($_REQUEST['pollid']);
	$getforum = $DB_site->query_first("SELECT forum.forumid, styleid, ((options & $_FORUMOPTIONS[styleoverride]) != 0) AS styleoverride FROM " . TABLE_PREFIX . "forum AS forum, " . TABLE_PREFIX . "thread AS thread WHERE forum.forumid = thread.forumid AND pollid = $pollid");
	if (($getforum['styleoverride'] == 1 OR $bbuserinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
	{
		$codestyleid = $getforum['styleid'];
	}
	unset($getforum);
}

// #############################################################################
// ######################## START TEMPLATES & STYLES ###########################
// #############################################################################

$userselect = false;

// is style in the forum/thread set?
if ($codestyleid)
{
	// style specified by forum
	$styleid = $codestyleid;
	$userselect = true;
}
else if ($bbuserinfo['styleid'] > 0 AND ($vboptions['allowchangestyles'] == 1 OR ($bbuserinfo['permissions']['adminpermissions'] & CANCONTROLPANEL)))
{
	// style specified in user profile
	$styleid = $bbuserinfo['styleid'];
}
else
{
	// no style specified - use default
	$styleid = $vboptions['styleid'];
}

// #############################################################################
// if user can control panel, allow selection of any style (for testing purposes)
// otherwise only allow styles that are user-selectable
$styleid = intval($styleid);

$style = $DB_site->query_first("
	SELECT * FROM " . TABLE_PREFIX . "style
	WHERE (styleid = $styleid" . iif(!($permissions['adminpermissions'] & CANCONTROLPANEL) AND !$userselect, ' AND userselect = 1') . ")
		OR styleid = $vboptions[styleid]
	ORDER BY styleid " . iif($styleid > $vboptions['styleid'], 'DESC', 'ASC') . "
	LIMIT 1
");
define('STYLEID', $style['styleid']);

// #############################################################################
//prepare default templates/phrases

$_templatedo = iif(empty($_REQUEST['do']), 'none', $_REQUEST['do']);

if (is_array($actionphrases["$_templatedo"]))
{
	$phrasegroups = array_merge($phrasegroups, $actionphrases["$_templatedo"]);
}

if (is_array($actiontemplates["$_templatedo"]))
{
	$globaltemplates = array_merge($globaltemplates, $actiontemplates["$_templatedo"]);
}

// templates to be included in every single page...
$globaltemplates = array_merge($globaltemplates, array(
	// the really important ones
	'header',
	'footer',
	'headinclude',
	'phpinclude_start',
	'phpinclude_end',
	// new private message script
	'pm_popup_script',
	// navbar construction
	'navbar',
	'navbar_link',
	// forumjump and go button
	'forumjump',
	'gobutton',
	'option',
	// multi-page navigation
	'pagenav',
	'pagenav_curpage',
	'pagenav_pagelink',
	'threadbit_pagelink',
	// misc useful
	'spacer_open',
	'spacer_close',
	'username_loggedout',
	'username_loggedin',
	'timezone',
	'STANDARD_ERROR',
	'STANDARD_REDIRECT'
	//'board_inactive_warning'
));

// if we are in a message editing page then get the editor templates
if (defined('GET_EDIT_TEMPLATES'))
{
	$_get_edit_templates = explode(',', GET_EDIT_TEMPLATES);
	if (GET_EDIT_TEMPLATES === true OR in_array($_REQUEST['do'], $_get_edit_templates))
	{
		$globaltemplates = array_merge($globaltemplates, array(
			// message area for wysiwyg / non wysiwyg
			'editor_clientscript',
			'editor_toolbar_off',
			'editor_toolbar_standard',
			'editor_toolbar_wysiwyg',
			// javascript menu builders
			'editor_jsoptions_font',
			'editor_jsoptions_size',
			// wysiwyg smilie menu templates
			'editor_smiliemenu_category',
			'editor_smiliemenu_smilie',
			// smiliebox templates
			'editor_smilie_wysiwyg',
			'editor_smilie_standard',
			'editor_smiliebox',
			'editor_smiliebox_category',
			'editor_smiliebox_row',
			'editor_smiliebox_straggler',
			// needed for thread preview
			'bbcode_code',
			'bbcode_html',
			'bbcode_php',
			'bbcode_quote',
			// misc often used
			'newpost_threadmanage',
			'newpost_disablesmiliesoption',
			'newpost_preview',
			'newpost_quote',
			'posticonbit',
			'posticons',
			'newpost_usernamecode',
			'newpost_errormessage',
			'forumrules'
		));
	}

}

// now get all the templates we have specified
cache_templates($globaltemplates, $style['templatelist']);
unset($globaltemplates, $actiontemplates, $_get_edit_templates, $_templatedo);

// #############################################################################
// get style variables
$stylevar = fetch_stylevars($style, $bbuserinfo);

// #############################################################################
// parse PHP include
if (!is_demo_mode())
{
	@ob_start();
	eval(fetch_template('phpinclude_start', -1, 0));
	$phpinclude_output = @ob_get_contents();
	@ob_end_clean();
}

// #############################################################################
// get new private message popup
$newpmmsg = 0;
$shownewpm = false;
if ($vboptions['checknewpm'] AND $bbuserinfo['userid'] AND $bbuserinfo['pmpopup'] == 2)
{
	$DB_site->shutdown_query("UPDATE " . TABLE_PREFIX . "user SET pmpopup = 1 WHERE userid = $bbuserinfo[userid]", 'pmpopup');
	if (THIS_SCRIPT != 'private')
	{
		$newpmmsg = 1;
		$newpm = $DB_site->query_first("
			SELECT pm.pmid, title, fromusername
			FROM " . TABLE_PREFIX . "pmtext AS pmtext
			LEFT JOIN " . TABLE_PREFIX . "pm AS pm USING(pmtextid)
			WHERE pm.userid = $bbuserinfo[userid]
			ORDER BY dateline DESC
			LIMIT 1
		");
		$newpm['username'] = addslashes(unhtmlspecialchars($newpm['fromusername'], true));
		$newpm['title'] = addslashes(unhtmlspecialchars($newpm['title'], true));
		$shownewpm = true;
	}
}

// #############################################################################
// set up the vars for the private message area of the navbar
$pmbox = array();
$pmbox['lastvisitdate'] = vbdate($vboptions['dateformat'], $bbuserinfo['lastvisit'], 1);
$pmbox['lastvisittime'] = vbdate($vboptions['timeformat'], $bbuserinfo['lastvisit']);
$pmunread_html = iif($bbuserinfo['pmunread'], "<strong>$bbuserinfo[pmunread]</strong>", $bbuserinfo['pmunread']);
$vbphrase['unread_x_nav_compiled'] = construct_phrase($vbphrase['unread_x_nav'], $pmunread_html);
$vbphrase['total_x_nav_compiled'] = construct_phrase($vbphrase['total_x_nav'], $bbuserinfo['pmtotal']);

// #############################################################################
// Generate Style Chooser Dropdown
if ($vboptions['allowchangestyles'])
{

	$stylecount = 0;
	$quickchooserbits = construct_style_options(-1, '--', true, true);
	$show['quickchooser'] = iif ($stylecount > 1, true, false);
	unset($stylecount);
}
else
{
	$show['quickchooser'] = false;
}

// #############################################################################
// do cron stuff - goes into footer
if ($datastore['cron'] <= TIMENOW)
{
	$cronimage = '<img src="' . $vboptions['bburl'] . '/cron.php?' . $session['sessionurl'] . '&amp;rand=' .  vbrand(1, 1000000) . '" alt="" width="1" height="1" border="0" />';
}
else
{
	$cronimage = '';
}

$show['admincplink'] = iif($permissions['adminpermissions'] & CANCONTROLPANEL, true, false);
// This generates an extra query for non-admins/supermods on many pages so we have chosen to only display it to supermods & admins
// $show['modcplink'] = iif(can_moderate(), true, false);
 $show['modcplink'] = iif ($permissions['adminpermissions'] & CANCONTROLPANEL OR $permissions['adminpermissions'] & ISMODERATOR, true, false);

$show['registerbutton'] = iif($vboptions['allowregistration'] AND (!$bbuserinfo['userid'] OR $vboptions['allowmultiregs']), true, false);
$show['searchbuttons'] = iif($permissions['forumpermissions'] & CANSEARCH AND $vboptions['enablesearches'], true, false);
if ($bbuserinfo['userid'])
{
	$show['guest'] = false;
	$show['member'] = true;
}
else
{
	$show['guest'] = true;
	$show['member'] = false;
}
$show['detailedtime'] = iif($vboptions['yestoday'] == 2, true, false);
$show['popups'] = iif($vboptions['usepopups'], true, false);
$show['pmstats'] = iif($bbuserinfo['options'] & $_USEROPTIONS['receivepm'] AND $permissions['pmquota'] > 0, true, false);
$show['pmtracklink'] = iif($permissions['pmpermissions'] & CANTRACKPM, true, false);

$show['siglink'] = iif($permissions['genericpermissions'] & CANUSESIGNATURE, true, false);
$show['avatarlink'] = iif($vboptions['avatarenabled'], true, false);
$show['profilepiclink'] = iif($permissions['genericpermissions'] & CANPROFILEPIC AND $vboptions['profilepicenabled'], true, false);
$show['wollink'] = iif($permissions['wolpermissions'] & CANWHOSONLINE, true, false);

$show['spacer'] = true; // used in postbit template
$show['dst_correction'] = iif((($sessioncreated AND THIS_SCRIPT == 'index') OR THIS_SCRIPT == 'usercp') AND $bbuserinfo['dstauto'] == 1 AND $bbuserinfo['userid'], true, false);
$show['contactus'] = iif($vboptions['contactuslink'] AND ((!$bbuserinfo['userid'] AND $vboptions['contactustype']) OR ($bbuserinfo['userid'])), true, false);

// parse some global templates
eval('$timezone = "' . fetch_template('timezone') . '";');
eval('$gobutton = "' . fetch_template('gobutton') . '";');
eval('$spacer_open = "' . fetch_template('spacer_open') . '";');
eval('$spacer_close = "' . fetch_template('spacer_close') . '";');

// parse headinclude, header & footer
eval('$headinclude = "' . fetch_template('headinclude') . '";');
eval('$header = "' . fetch_template('header') . '";');
eval('$footer = "' . fetch_template('footer') . '";');
// Redirect if this forum has a link
if (trim($foruminfo['link']) != '')
{
	// get permission to view forum
	$_permsgetter_ = 'forumdisplay';
	$forumperms = fetch_permissions($forumid);
	if (!($forumperms & CANVIEW))
	{
		print_no_permission();
	}
	exec_header_redirect($foruminfo['link']);
}
if ($shownewpm)
{
	if ($bbuserinfo['pmunread'] == 1)
	{
		$pmpopupurl = "private.php?$session[sessionurl_js]do=showpm&pmid=$newpm[pmid]";
	}
	else
	{
		$pmpopupurl = "private.php?$session[sessionurl_js]";
	}
	eval('$footer .= "' . fetch_template('pm_popup_script') . '";');
}

// #############################################################################
// ######################### END TEMPLATES & STYLES ############################
// #############################################################################

// #############################################################################
// phpinfo display for support purposes
if ($_REQUEST['do'] == 'phpinfo')
{
	if ($vboptions['allowphpinfo'] AND !is_demo_mode())
	{
		phpinfo();
		exit;
	}
	else
	{
		eval(print_standard_error('admin_disabled_php_info'));
	}
}

// #############################################################################
// check to see if server is too busy. this is checked at the end of session.php
if ($servertoobusy AND !($permissions['adminpermissions'] & CANCONTROLPANEL) AND THIS_SCRIPT != 'login')
{
	$vboptions['useforumjump'] = 0;
	eval(print_standard_error('error_toobusy'));
}

// #############################################################################
// check that board is active - if not admin, then display error
if (!$vboptions['bbactive'] AND THIS_SCRIPT != 'login')
{
	if (!($permissions['adminpermissions'] & CANCONTROLPANEL))
	{
		$show['enableforumjump'] = true;
		eval('standard_error("' . str_replace("\'", "'", addslashes($vboptions['bbclosedreason'])) . '");');
		unset($shutdownqueries['lastvisit']);
	}
	else
	{
		// show the board disabled warning message so that admins don't leave the board turned off by accident
		eval('$warning = "' . fetch_template('board_inactive_warning') . '";');
		$header = $warning . $header;
		$footer .= $warning;
	}
}

// #############################################################################
// password expiry system
if ($bbuserinfo['userid'] AND $permissions['passwordexpires'])
{
	$passworddaysold = floor((TIMENOW - $bbuserinfo['passworddate']) / 86400);

	if ($passworddaysold >= $permissions['passwordexpires'])
	{
		if ((THIS_SCRIPT != 'login' AND THIS_SCRIPT != 'profile') OR (THIS_SCRIPT == 'profile' AND $_REQUEST['do'] != 'editpassword' AND $_POST['do'] != 'updatepassword'))
		{
			eval(print_standard_error('passwordexpired'));
		}
		else
		{
			$show['passwordexpired'] = true;
		}
	}
}
else
{
	$passworddaysold = 0;
	$show['passwordexpired'] = false;
}

// #############################################################################
// check permission to view forum
if (!($permissions['forumpermissions'] & CANVIEW))
{
	$allowed_scripts = array(
		'register',
		'login',
		'image',
		'sendmessage',
	);
	if (!in_array(THIS_SCRIPT, $allowed_scripts))
	{
		if (defined('DIE_QUIETLY'))
		{
			exit;
		}
		else
		{
			print_no_permission();
		}
	}
	else
	{
		$_doArray = array('contactus', 'docontactus', 'register', 'signup', 'requestemail', 'emailcode', 'activate', 'login', 'logout', 'lostpw', 'emailpassword', 'addmember', 'coppaform', 'resetpassword', 'regcheck', 'checkdate');
		if (THIS_SCRIPT == 'sendmessage' AND $_REQUEST['do'] == '')
		{
			$_REQUEST['do'] = 'contactus';
		}
		$_aArray = array('act', 'ver', 'pwd');
		if (!in_array($_REQUEST['do'], $_doArray) AND !in_array($_REQUEST['a'], $_aArray))
		{
			if (defined('DIE_QUIETLY'))
			{
				exit;
			}
			else
			{
				print_no_permission();
			}
		}
		unset($_doArray, $_aArray);
	}
}

// #############################################################################
// check for IP ban on user
verify_ip_ban();

// #############################################################################
// build $logincode template
$logincode = construct_login_code();

if (DB_QUERIES)
{
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$aftertime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	echo "End call of global.php:  $aftertime\n";
	echo "\n<hr />\n\n";
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13188 $
|| ####################################################################
\*======================================================================*/
?>