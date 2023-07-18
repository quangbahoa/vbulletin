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

// identify where we are
define('VB_AREA', 'Forum');

define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));

// #############################################################################
// Start initialisation
require_once(CWD . '/includes/init.php');

$vbulletin->input->clean_array_gpc('r', array(
	'referrerid' => TYPE_UINT,
	'postid'     => TYPE_UINT,
	'threadid'   => TYPE_UINT,
	'forumid'    => TYPE_INT,
	'pollid'     => TYPE_UINT,
	'a'          => TYPE_STR,
	'mode'       => TYPE_STR,		// Threaded mode // may conflict with other 'mode' variables?
	'nojs'       => TYPE_BOOL,
));

$vbulletin->input->clean_array_gpc('p', array(
	'ajax' => TYPE_BOOL,
));

// #############################################################################
// turn off popups if they are not available to this browser
if ($vbulletin->options['usepopups'])
{
	if ((is_browser('ie', 5) AND !is_browser('mac')) OR is_browser('mozilla') OR is_browser('firebird') OR is_browser('opera', 7) OR is_browser('webkit') OR is_browser('konqueror', 3.2))
	{
		// use popups
	}
	else
	{
		// don't use popups
		$vbulletin->options['usepopups'] = 0;
	}
}

// #############################################################################
// set a variable used by the spacer templates to detect IE versions less than 6
$show['old_explorer'] = (is_browser('ie') AND !is_browser('ie', 6));

// #############################################################################
// read the list of collapsed menus from the 'vbulletin_collapse' cookie
$vbcollapse = array();
if (!empty($vbulletin->GPC['vbulletin_collapse']))
{
	$val = preg_split('#\n#', $vbulletin->GPC['vbulletin_collapse'], -1, PREG_SPLIT_NO_EMPTY);
	foreach ($val AS $key)
	{
		$vbcollapse["collapseobj_$key"] = 'display:none;';
		$vbcollapse["collapseimg_$key"] = '_collapsed';
		$vbcollapse["collapsecel_$key"] = '_collapsed';
	}
	unset($val);
}

// #############################################################################
// start server too busy
$servertoobusy = false;

if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN' AND $vbulletin->options['loadlimit'] > 0)
{
	if(!is_array($vbulletin->loadcache) OR $vbulletin->loadcache['lastcheck'] < (TIMENOW - 60))
	{
		update_loadavg();
	}


	if ($vbulletin->loadcache['loadavg'] > $vbulletin->options['loadlimit'])
	{
		$servertoobusy = true;
	}
}

// #############################################################################
// do headers
exec_headers();

// #############################################################################
// set the referrer cookie if URI contains a referrerid
if ($vbulletin->GPC['referrerid'] AND !$vbulletin->GPC[COOKIE_PREFIX . 'referrerid'] AND !$vbulletin->userinfo['userid'] AND $vbulletin->options['usereferrer'])
{
	if ($referrerid = verify_id('user', $vbulletin->GPC['referrerid'], 0))
	{
		vbsetcookie('referrerid', $referrerid);
	}
}

// #############################################################################
// Get date / time info
// override date/time settings if specified
fetch_options_overrides($vbulletin->userinfo);
fetch_time_data();

// global $vbulletin->userinfo setup -- this has to happen after fetch_options_overrides
if ($vbulletin->userinfo['lastvisit'])
{
	$vbulletin->userinfo['lastvisitdate'] = vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $vbulletin->userinfo['lastvisit']);
}
else
{
	$vbulletin->userinfo['lastvisitdate'] = -1;
}

// get some useful info
$templateversion =& $vbulletin->options['templateversion'];

// #############################################################################
// initialize $vbphrase and set language constants
$vbphrase = init_language();

// set a default username
if ($vbulletin->userinfo['username'] == '')
{
	$vbulletin->userinfo['username'] = $vbphrase['unregistered'];
}

// #############################################################################
// CACHE PERMISSIONS AND GRAB $permissions
// get the combined permissions for the current user
// this also creates the $fpermscache containing the user's forum permissions

$permissions = cache_permissions($vbulletin->userinfo);

// #############################################################################

// figure out the chosen style settings
$codestyleid = 0;

// Init post/thread/forum values
$postinfo = array();
$threadinfo = array();
$foruminfo = array();

// automatically query $postinfo, $threadinfo & $foruminfo if $threadid exists
if ($vbulletin->GPC['postid'] AND $postinfo = verify_id('post', $vbulletin->GPC['postid'], 0, 1))
{
	$postid =& $postinfo['postid'];
	$vbulletin->GPC['threadid'] =& $postinfo['threadid'];
}

// automatically query $threadinfo & $foruminfo if $threadid exists
if ($vbulletin->GPC['threadid'] AND $threadinfo = verify_id('thread', $vbulletin->GPC['threadid'], 0, 1))
{
	$threadid =& $threadinfo['threadid'];
	$vbulletin->GPC['forumid'] = $forumid = $threadinfo['forumid'];
	if ($forumid)
	{
		$foruminfo = fetch_foruminfo($threadinfo['forumid']);
		if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
		{
			$codestyleid = $foruminfo['styleid'];
		}
	}

	if ($vbulletin->GPC['pollid'])
	{
		$pollinfo = verify_id('poll', $vbulletin->GPC['pollid'], 0, 1);
		$pollid =& $pollinfo['pollid'];
	}
}
// automatically query $foruminfo if $forumid exists
else if ($vbulletin->GPC['forumid'])
{
	$foruminfo = verify_id('forum', $vbulletin->GPC['forumid'], 0, 1);
	$forumid =& $foruminfo['forumid'];

	if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
	{
		$codestyleid =& $foruminfo['styleid'];
	}
}
// automatically query forum for style info if $pollid exists
else if ($vbulletin->GPC['pollid'] AND THIS_SCRIPT == 'poll')
{
	$pollinfo = verify_id('poll', $vbulletin->GPC['pollid'], 0, 1);
	$pollid =& $pollinfo['pollid'];

	$threadinfo = fetch_threadinfo($pollinfo['threadid']);

	$threadid =& $threadinfo['threadid'];

	$foruminfo = fetch_foruminfo($threadinfo['forumid']);
	$forumid =& $foruminfo['forumid'];

	if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
	{
		$codestyleid = $foruminfo['styleid'];
	}
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
	$vbulletin->userinfo['styleid'] = $styleid;
	$userselect = true;
}
else if ($vbulletin->userinfo['styleid'] > 0 AND ($vbulletin->options['allowchangestyles'] == 1 OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])))
{
	// style specified in user profile
	$styleid = $vbulletin->userinfo['styleid'];
}
else
{
	// no style specified - use default
	$styleid = $vbulletin->options['styleid'];
	$vbulletin->userinfo['styleid'] = $styleid;
}

// #############################################################################
// if user can control panel, allow selection of any style (for testing purposes)
// otherwise only allow styles that are user-selectable
$styleid = intval($styleid);
$style = NULL;

($hook = vBulletinHook::fetch_hook('style_fetch')) ? eval($hook) : false;

if (!is_array($style))
{
	$style = $db->query_first_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "style
		WHERE (styleid = $styleid" . iif(!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND !$userselect, ' AND userselect = 1') . ")
			OR styleid = " . $vbulletin->options['styleid'] . "
		ORDER BY styleid " . iif($styleid > $vbulletin->options['styleid'], 'DESC', 'ASC') . "
		LIMIT 1
	");
}
define('STYLEID', $style['styleid']);

// #############################################################################
//prepare default templates

$_templatedo = iif(empty($_REQUEST['do']), 'none', $_REQUEST['do']);

if (!is_array($globaltemplates))
{
	$globaltemplates = array();
}

if (isset($actiontemplates["$_templatedo"]) AND is_array($actiontemplates["$_templatedo"]))
{
	$globaltemplates = array_merge($globaltemplates, $actiontemplates["$_templatedo"]);
}

// Choose proper human verification template
if ($vbulletin->options['hv_type'] AND in_array('humanverify', $globaltemplates))
{
	$globaltemplates[] = 'humanverify_' . strtolower($vbulletin->options['hv_type']);
}

// templates to be included in every single page...
$globaltemplates = array_merge($globaltemplates, array(
	// the really important ones
	'header',
	'footer',
	'headinclude',
	// ad location templates
	'ad_header_logo',
	'ad_header_end',
	'ad_navbar_below',
	'ad_footer_start',
	'ad_footer_end',
	// new private message script
	'pm_popup_script',
	// navbar construction
	'navbar',
	'navbar_link',
	'navbar_noticebit',
	'navbar_notifications_menubit',
	// forumjump and go button
	'forumjump',
	'gobutton',
	'option',
	// multi-page navigation
	'pagenav',
	'pagenav_curpage',
	'pagenav_pagelink',
	'pagenav_pagelinkrel',
	'threadbit_pagelink',
	// misc useful
	'spacer_open',
	'spacer_close',
	'STANDARD_ERROR',
	'STANDARD_REDIRECT'
	//'board_inactive_warning'
));

// if we are in a message editing page then get the editor templates
$show['editor_css'] = false;
if (defined('GET_EDIT_TEMPLATES'))
{
	$_get_edit_templates = explode(',', GET_EDIT_TEMPLATES);
	if (GET_EDIT_TEMPLATES === true OR in_array($_REQUEST['do'], $_get_edit_templates))
	{
		$globaltemplates = array_merge($globaltemplates, array(
			// message stuff 3.5
			'editor_toolbar_on',
			'editor_smilie',
			// message area for wysiwyg / non wysiwyg
			'editor_css',
			'editor_clientscript',
			'editor_toolbar_off',
			// javascript menu builders
			'editor_jsoptions_font',
			'editor_jsoptions_size',
			// smiliebox templates
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

		$show['editor_css'] = true;
	}
}

($hook = vBulletinHook::fetch_hook('cache_templates')) ? eval($hook) : false;

// now get all the templates we have specified
cache_templates($globaltemplates, $style['templatelist']);
unset($globaltemplates, $actiontemplates, $_get_edit_templates, $_templatedo);

// #############################################################################
// initialize $template_hook variable - used for hooks within templates
$template_hook = array();

// #############################################################################
// get style variables
$stylevar = fetch_stylevars($style, $vbulletin->userinfo);

if (defined('CSRF_ERROR'))
{
	define('VB_ERROR_LITE', true);
	eval('$headinclude = "' . fetch_template('headinclude') . '";');

	$ajaxerror = $vbulletin->GPC['ajax'] ? '_ajax' : '';

	switch (CSRF_ERROR)
	{
		case 'missing':
			eval(standard_error(fetch_error('security_token_missing', $vbulletin->options['contactuslink'])));
			break;

		case 'guest':
			eval(standard_error(fetch_error('security_token_guest' . $ajaxerror)));
			break;

		case 'timeout':
			eval(standard_error(fetch_error('security_token_timeout' . $ajaxerror, $vbulletin->options['contactuslink'])));
			break;

		case 'invalid':
		default:
			eval(standard_error(fetch_error('security_token_invalid', $vbulletin->options['contactuslink'])));
	}
	exit;
}

// #############################################################################
// parse PHP include
@ob_start();
($hook = vBulletinHook::fetch_hook('global_start')) ? eval($hook) : false;
$phpinclude_output = @ob_get_contents();
@ob_end_clean();

// #############################################################################
// get new private message popup
$shownewpm = false;
if (!empty($vbulletin->userinfo['pmpopup']) AND $vbulletin->userinfo['pmpopup'] == 2 AND $vbulletin->options['checknewpm'] AND $vbulletin->userinfo['userid'] AND !defined('NOPMPOPUP'))
{
	$userdm = datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
	$userdm->set_existing($vbulletin->userinfo);
	$userdm->set('pmpopup', 1);
	$userdm->save(true, 'pmpopup');	// 'pmpopup' tells db_update to issue a shutdownquery of the same name
	unset($userdm);

	if (THIS_SCRIPT != 'private' AND THIS_SCRIPT != 'login')
	{
		$newpm = $db->query_first("
			SELECT pm.pmid, title, fromusername
			FROM " . TABLE_PREFIX . "pmtext AS pmtext
			LEFT JOIN " . TABLE_PREFIX . "pm AS pm USING(pmtextid)
			WHERE pm.userid = " . $vbulletin->userinfo['userid'] . "
				AND pm.folderid = 0
			ORDER BY dateline DESC
			LIMIT 1
		");
		$newpm['username'] = addslashes_js(unhtmlspecialchars($newpm['fromusername'], true), '"');
		$newpm['title'] = addslashes_js(unhtmlspecialchars($newpm['title'], true), '"');
		$shownewpm = true;
	}
}

// #############################################################################
// set up the vars for the private message area of the navbar
$pmbox = array();
$pmbox['lastvisitdate'] = vbdate($vbulletin->options['dateformat'], $vbulletin->userinfo['lastvisit'], 1);
$pmbox['lastvisittime'] = vbdate($vbulletin->options['timeformat'], $vbulletin->userinfo['lastvisit']);
$pmunread_html = construct_phrase(((!empty($vbulletin->userinfo['pmunread'])) ? $vbphrase['numeric_value_emphasized'] : $vbphrase['numeric_value']), (empty($vbulletin->userinfo['pmunread']) ? 0 : $vbulletin->userinfo['pmunread']));
$vbphrase['unread_x_nav_compiled'] = construct_phrase($vbphrase['unread_x_nav'], $pmunread_html);
$vbphrase['total_x_nav_compiled'] = construct_phrase($vbphrase['total_x_nav'], (empty($vbulletin->userinfo['pmtotal']) ? 0 : $vbulletin->userinfo['pmtotal']));

// #############################################################################
// Generate Language Chooser Dropdown

$languagecount = 0;
$languagechooserbits = construct_language_options('--', true);
$show['languagechooser'] = ($languagecount > 1 AND empty($_POST['do'])) ? true : false;
unset($languagecount);

// #############################################################################
// Generate Style Chooser Dropdown
if ($vbulletin->options['allowchangestyles'] AND empty($_POST['do']))
{
	$stylecount = 0;
	$quickchooserbits = construct_style_options(-1, '--', true, true);
	$show['quickchooser'] = ($stylecount > 1 ? true : false);
	unset($stylecount);
}
else
{
	$show['quickchooser'] = false;
}

// #############################################################################
// do cron stuff - goes into footer
if ($vbulletin->cron <= TIMENOW)
{
	$cronimage = '<img src="' . create_full_url('cron.php?' . $vbulletin->session->vars['sessionurl'] . 'rand=' .  TIMENOW) . '" alt="" width="1" height="1" border="0" />';
}
else
{
	$cronimage = '';
}

$show['rtl'] = ($stylevar['textdirection'] == 'rtl');

$show['admincplink'] = iif($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'], true, false);
// This generates an extra query for non-admins/supermods on many pages so we have chosen to only display it to supermods & admins
// $show['modcplink'] = iif(can_moderate(), true, false);
$show['modcplink'] = ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR $vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']);

$show['registerbutton'] = (!$show['search_engine'] AND $vbulletin->options['allowregistration'] AND (!$vbulletin->userinfo['userid'] OR $vbulletin->options['allowmultiregs']));
$show['searchbuttons'] = (!$show['search_engine'] AND $vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['cansearch'] AND $vbulletin->options['enablesearches']);
$show['quicksearch'] = (!fetch_require_hvcheck('search'));
$show['memberslist'] = ($vbulletin->options['enablememberlist'] AND $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']);

$loggedout = false;
if (THIS_SCRIPT == 'login' AND $_REQUEST['do'] == 'logout' AND $vbulletin->userinfo['userid'] != 0)
{
	$vbulletin->input->clean_gpc('r', 'logouthash', TYPE_STR);
	if (verify_security_token($vbulletin->GPC['logouthash'], $vbulletin->userinfo['securitytoken_raw']))
	{
		$loggedout = true;
	}
}
if (!$vbulletin->userinfo['userid'] OR $loggedout)
{
	$show['guest'] = true;
	$show['member'] = false;
}
else
{
	$show['guest'] = false;
	$show['member'] = true;
}

$show['detailedtime'] = iif($vbulletin->options['yestoday'] == 2, true, false);

$show['popups'] = iif(!$show['search_engine'] AND $vbulletin->options['usepopups'] AND !$vbulletin->GPC['nojs'], true, false);
if ($show['popups'])
{
	// this isn't what $show is for, but it's a variable that's available in many places
	$show['nojs_link'] = $vbulletin->scriptpath . (strpos($vbulletin->scriptpath, '?') ? '&amp;' : '?') . 'nojs=1';
}
else
{
	$show['nojs_link'] = '';
}

if ($vbulletin->options['enablepms'] AND (!empty($vbulletin->userinfo['pmunread']) OR (!empty($vbulletin->userinfo['receivepm']) AND $vbulletin->userinfo['permissions']['pmquota'])))
{
	if ($vbulletin->userinfo['pmtotal'] < $vbulletin->userinfo['permissions']['pmquota'])
	{
		if (($vbphrase['pmpercent_nav_compiled'] = number_format(floor($vbulletin->userinfo['pmtotal'] / $vbulletin->userinfo['permissions']['pmquota'] * 100), 0)) >= 90)
		{
			$show['pmwarning'] = true;
		}
		else
		{
			$show['pmwarning'] = false;
		}
	}
	else if ($vbulletin->userinfo['permissions']['pmquota'])
	{
		$show['pmwarning'] = true;
		$vbphrase['pmpercent_nav_compiled'] = '100';
	}
	else
	{
		$show['pmwarning'] = false;
	}
	$show['pmstats'] = true;
}
else
{
	$show['pmstats'] = false;
	$show['pmwarning'] = false;
}
$show['pmmainlink'] = ($vbulletin->options['enablepms'] AND ($vbulletin->userinfo['permissions']['pmquota'] OR $vbulletin->userinfo['pmtotal']));
$show['pmtracklink'] = ($vbulletin->userinfo['permissions']['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm']);
$show['pmsendlink'] = ($vbulletin->userinfo['permissions']['pmquota']);

$show['siglink'] = ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusesignature']);
$show['avatarlink'] = ($vbulletin->options['avatarenabled']);
$show['profilepiclink'] = ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canprofilepic'] AND $vbulletin->options['profilepicenabled']);
$show['wollink'] = ($vbulletin->userinfo['permissions']['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonline']);

$show['spacer'] = true; // used in postbit template
if (THIS_SCRIPT == 'register')
{
	// see 3.6 bug 876 -- causes double redirect and error when activating
	$show['dst_correction'] = false;
}
else
{
	$show['dst_correction'] = (($vbulletin->session->vars['loggedin'] == 1 OR $vbulletin->session->created OR THIS_SCRIPT == 'usercp') AND $vbulletin->userinfo['dstauto'] == 1 AND $vbulletin->userinfo['userid']);
}
$show['contactus'] = ($vbulletin->options['contactuslink'] AND ((!$vbulletin->userinfo['userid'] AND $vbulletin->options['contactustype']) OR ($vbulletin->userinfo['userid'])));

$show['forumdesc'] = ($vbulletin->options['nav_forumdesc'] AND !empty($foruminfo['description']) AND trim($foruminfo['description']) != '' AND in_array(THIS_SCRIPT, array('newthread', 'newreply', 'forumdisplay', 'showthread', 'announcement', 'editpost', 'poll', 'report', 'sendmessage', 'threadrate')));
$show['foruminfo'] = (THIS_SCRIPT == 'forumdisplay' AND $vbulletin->userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canview']) ? true : false;
if (THIS_SCRIPT == 'showthread' AND $threadinfo['threadid'])
{
	if (!($vbulletin->userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canview'])
		OR
	(((!$threadinfo['visible'] AND !can_moderate($foruminfo['forumid'], 'canmoderateposts'))) OR ($threadinfo['isdeleted'] AND !can_moderate($foruminfo['forumid'])))
		OR
	(in_coventry($threadinfo['postuserid']) AND !can_moderate($foruminfo['forumid']))
		OR
	(!($vbulletin->userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		OR
	(!($vbulletin->userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
		OR
		!verify_forum_password($foruminfo['forumid'], $foruminfo['password'], false))
	{
		$show['threadinfo'] = false;
	}
	else
	{
		$show['threadinfo'] = true;
	}
}
else
{
	$show['threadinfo'] = false;
}

// you may define this if you don't want the password in the login box to be zapped onsubmit; good for integration
$show['nopasswordempty'] = defined('DISABLE_PASSWORD_CLEARING') ? 1 : 0; // this nees to be an int for the templates

$ad_location = array();

// parse some global templates
eval('$gobutton = "' . fetch_template('gobutton') . '";');
eval('$spacer_open = "' . fetch_template('spacer_open') . '";');
eval('$spacer_close = "' . fetch_template('spacer_close') . '";');

($hook = vBulletinHook::fetch_hook('parse_templates')) ? eval($hook) : false;

// parse headinclude, header & footer
$admincpdir =& $vbulletin->config['Misc']['admincpdir'];
$modcpdir =& $vbulletin->config['Misc']['modcpdir'];

// advertising location setup
eval('$ad_location[\'ad_header_logo\'] = "' . fetch_template('ad_header_logo') . '";');
eval('$ad_location[\'ad_header_end\'] = "' . fetch_template('ad_header_end') . '";');
eval('$ad_location[\'ad_navbar_below\'] = "' . fetch_template('ad_navbar_below') . '";');
eval('$ad_location[\'ad_footer_start\'] = "' . fetch_template('ad_footer_start') . '";');
eval('$ad_location[\'ad_footer_end\'] = "' . fetch_template('ad_footer_end') . '";');

// process editor css if required
if ($show['editor_css'])
{
	require_once(DIR . '/includes/functions_editor.php');
	construct_editor_styles_js($style['editorstyles']);
	eval('$editor_css = "' . fetch_template('editor_css') . '";');
}

// #############################################################################
// handle notices
if (!empty($vbulletin->noticecache) AND is_array($vbulletin->noticecache))
{
	$notices = '';
	$return_link = $vbulletin->scriptpath;

	require_once(DIR . '/includes/functions_notice.php');
	if ($vbulletin->userinfo['userid'] == 0)
	{
		$vbulletin->userinfo['musername'] = fetch_musername($vbulletin->userinfo);
	}
	foreach (fetch_relevant_notice_ids() AS $_noticeid)
	{
		$show['notices'] = true;
		if (($vbulletin->noticecache["$_noticeid"]["dismissible"] == 1) AND $vbulletin->userinfo['userid'])
		{
			// only show the dismiss link for registered users; guest who wants to dismiss?  Register please.
			$show['dismiss_link'] = true;
		}
		else
		{
			$show['dismiss_link'] = false;
		}
		$notice_html = str_replace(array('{musername}', '{username}', '{userid}', '{sessionurl}'), array($vbulletin->userinfo['musername'], $vbulletin->userinfo['username'], $vbulletin->userinfo['userid'], $vbulletin->session->vars['sessionurl']), $vbphrase["notice_{$_noticeid}_html"]);

		($hook = vBulletinHook::fetch_hook('notices_noticebit')) ? eval($hook) : false;

		eval('$notices .= "' . fetch_template('navbar_noticebit') . '";');
	}
}
else
{
	$show['notices'] = false;
	$notices = '';
}

// #############################################################################
// set up user notifications
$show['notifications'] = false;
if ($vbulletin->userinfo['userid'])
{
	$notifications = array();

	if ($show['pmstats'])
	{
		$notifications['pmunread'] = array(
			'phrase' => $vbphrase['unread_private_messages'],
			'link'   => 'private.php' . $vbulletin->session->vars['sessionurl_q'],
			'order'  => 10
		);
	}

	if (
		$vbulletin->userinfo['vm_enable']
			AND
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_visitor_messaging']
			AND
		$permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
	)
	{
		$notifications['vmunreadcount'] = array(
			'phrase' => $vbphrase['unread_profile_visitor_messages'],
			'link'   => 'member.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $vbulletin->userinfo['userid'] . '&amp;tab=visitor_messaging',
			'order'  => 20
		);

		if ($permissions['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile'])
		{
			$notifications['vmmoderatedcount'] = array(
				'phrase' => $vbphrase['profile_visitor_messages_awaiting_approval'],
				'link'   => 'member.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $vbulletin->userinfo['userid'] . '&amp;tab=visitor_messaging',
				'order'  => 30
			);
		}
	}

	// check for incoming friend requests if user has permission to use the friends system
	if (($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_friends']) AND ($permissions['genericpermissions2'] & $vbulletin->bf_ugp_genericpermissions2['canusefriends']))
	{
		$notifications['friendreqcount'] = array(
			'phrase' => $vbphrase['incoming_friend_requests'],
			'link'   => 'profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=buddylist#irc',
			'order'  => 40
		);
	}

	// social group invitations and join requests
	if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
	{
		// check for requests to join your own social groups, if user has permission to create groups
		if ($permissions['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['cancreategroups'])
		{
			$notifications['socgroupreqcount'] = array(
				'phrase' => $vbphrase['requests_to_join_your_social_groups'],
				'link'   => 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=requests',
				'order'  => 50
			);
		}

		// check for invitations to join social groups, if user has permission to join groups
		if ($permissions['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups'])
		{
			$notifications['socgroupinvitecount'] = array(
				'phrase' => $vbphrase['invitations_to_join_social_groups'],
				'link'   => 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=invitations',
				'order'  => 60
			);
		}
	}

	// picture comment notifications
	if ($vbulletin->options['pc_enabled']
		AND $vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']
		AND $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
		AND $permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']
		AND $permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']
	)
	{
		$notifications['pcunreadcount'] = array(
			'phrase' => $vbphrase['unread_picture_comments'],
			'link'   => 'album.php?' . $vbulletin->session->vars['sessionurl'] . 'do=unread',
			'order'  => 70
		);

		if ($permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment'])
		{
			$notifications['pcmoderatedcount'] = array(
				'phrase' => $vbphrase['picture_comments_awaiting_approval'],
				'link'   => 'album.php?' . $vbulletin->session->vars['sessionurl'] . 'do=moderated',
				'order'  => 80
			);
		}
	}

	if (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->options['socnet_groups_msg_enabled']
		AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
	)
	{
		$notifications['gmmoderatedcount'] = array(
			'phrase' => $vbphrase['group_messages_awaiting_approval'],
			'link'   => 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=moderatedgms',
			'order'  => 90
		);
	}

	($hook = vBulletinHook::fetch_hook('notifications_list')) ? eval($hook) : false;

	$notifications_order = array();
	foreach ($notifications AS $userfield => $notification)
	{
		$notifications_order["$notification[order]"]["$userfield"] = $userfield;
	}

	ksort($notifications_order);

	$notifications_total = 0;
	$notifications_menubits = '';

	foreach ($notifications_order AS $notification_order => $userfields)
	{
		ksort($notifications_order["$notification_order"]);

		foreach ($userfields AS $userfield)
		{
			$notification =& $notifications["$userfield"];

			if ($vbulletin->userinfo["$userfield"] > 0)
			{
				$show['notifications'] = true;
			}

			$notifications_total += $vbulletin->userinfo["$userfield"];
			$notification['total'] = vb_number_format($vbulletin->userinfo["$userfield"]);

			eval('$notifications_menubits .= "' . fetch_template('navbar_notifications_menubit') . '";');
		}
	}

	$notifications_total = vb_number_format($notifications_total);
}

// #############################################################################
// Determine display of certain navbar Quick Links
$show['quick_links_groups'] = (
	$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
	AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']
);
$show['quick_links_albums'] = (
	$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']
	AND $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
	AND $permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']
);
$show['friends_and_contacts'] = (
	$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_friends']
	AND $vbulletin->userinfo['permissions']['genericpermissions2'] & $vbulletin->bf_ugp_genericpermissions2['canusefriends']
);
$show['communitylink'] = ($show['quick_links_groups'] OR $show['quick_links_albums'] OR $vbulletin->userinfo['userid'] OR $show['memberslist']);

// #############################################################################
// page number is used in meta tags (sometimes)
$pagenumber = $vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);
eval('$headinclude = "' . fetch_template('headinclude') . '";');
eval('$header = "' . fetch_template('header') . '";');
eval('$footer = "' . fetch_template('footer') . '";');

// #############################################################################
// Redirect if this forum has a link
// check if this forum is a link to an outside site
if ((isset($foruminfo['link']) AND trim($foruminfo['link']) != '') AND (THIS_SCRIPT != 'subscription' OR $_REQUEST['do'] != 'removesubscription'))
{
	// get permission to view forum
	$_permsgetter_ = 'forumdisplay';
	$forumperms = fetch_permissions($forumid);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		print_no_permission();
	}

	// add session hash to local links if necessary
	if (preg_match('#^([a-z0-9_]+\.php)(\?.*$)?#i', $foruminfo['link'], $match))
	{
		if ($match[2])
		{
			// we have a ?xyz part, put session url at beginning if necessary
			$query_string = preg_replace('/([^a-z0-9])(s|sessionhash)=[a-z0-9]{32}(&amp;|&)?/', '\\1', $match[2]);
			$foruminfo['link'] = $match[1] . '?' . $vbulletin->session->vars['sessionurl_js'] . substr($query_string, 1);
		}
		else
		{
			$foruminfo['link'] .= $vbulletin->session->vars['sessionurl_q'];
		}
	}

	exec_header_redirect($foruminfo['link'], true);
}

// #############################################################################
// Check for pm popup
if ($shownewpm)
{
	if ($vbulletin->userinfo['pmunread'] == 1)
	{
		$pmpopupurl = 'private.php?' . $vbulletin->session->vars['sessionurl_js'] . "do=showpm&pmid=$newpm[pmid]";
	}
	else
	{
		if (!empty($vbulletin->session->vars['sessionurl_js']))
		{
			$pmpopupurl = 'private.php?' . $vbulletin->session->vars['sessionurl_js'];
		}
		else
		{
			$pmpopupurl = 'private.php';
		}
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
	if ($vbulletin->options['allowphpinfo'] AND !is_demo_mode())
	{
		phpinfo();
		exit;
	}
	else
	{
		eval(standard_error(fetch_error('admin_disabled_php_info')));
	}
}

// #############################################################################
// check to see if server is too busy. this is checked at the end of session.php
if ($servertoobusy AND !($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND THIS_SCRIPT != 'login')
{
	$vbulletin->options['useforumjump'] = 0;
	eval(standard_error(fetch_error('toobusy')));
}

// #############################################################################
// check that board is active - if not admin, then display error
if (!$vbulletin->options['bbactive'] AND THIS_SCRIPT != 'login')
{
	if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		if (THIS_SCRIPT == 'external')
		{
			// don't output HTML for external data
			exit;
		}
		$show['enableforumjump'] = true;
		eval('standard_error("' . str_replace("\\'", "'", addslashes($vbulletin->options['bbclosedreason'])) . '");');
		unset($db->shutdownqueries['lastvisit']);
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
if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['passwordexpires'])
{
	$passworddaysold = floor((TIMENOW - $vbulletin->userinfo['passworddate']) / 86400);

	if ($passworddaysold >= $vbulletin->userinfo['permissions']['passwordexpires'])
	{
		if ((THIS_SCRIPT != 'login' AND THIS_SCRIPT != 'profile' AND THIS_SCRIPT != 'ajax')
			OR (THIS_SCRIPT == 'profile' AND $_REQUEST['do'] != 'editpassword' AND $_POST['do'] != 'updatepassword')
			OR (THIS_SCRIPT == 'ajax' AND $_REQUEST['do'] != 'imagereg' AND $_REQUEST['do'] != 'securitytoken' AND $_REQUEST['do'] != 'dismissnotice')
		)
		{
			eval(standard_error(fetch_error('passwordexpired',
				$passworddaysold,
				$vbulletin->session->vars['sessionurl']
			)));
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
// password same as username?
if (!defined('ALLOW_SAME_USERNAME_PASSWORD') AND $vbulletin->userinfo['userid'])
{
	// save the resource on md5'ing if the option is not enabled or guest
	if ($vbulletin->userinfo['password'] == md5(md5($vbulletin->userinfo['username']) . $vbulletin->userinfo['salt']))
	{
		if ((THIS_SCRIPT != 'login' AND THIS_SCRIPT != 'profile') OR (THIS_SCRIPT == 'profile' AND $_REQUEST['do'] != 'editpassword' AND $_POST['do'] != 'updatepassword'))
		{
			eval(standard_error(fetch_error('username_same_as_password',
				$vbulletin->session->vars['sessionurl']
			)));
		}
	}
}

// #############################################################################
// check required profile fields
if ($vbulletin->session->vars['profileupdate'] AND THIS_SCRIPT != 'login' AND THIS_SCRIPT != 'profile')
{
	$vbulletin->options['useforumjump'] = 0;
	eval(standard_error(fetch_error('updateprofilefields', $vbulletin->session->vars['sessionurl'])));
}

// #############################################################################
// check permission to view forum
if (!($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
{
	$allowed_scripts = array(
		'register',
		'login',
		'image',
		'sendmessage',
		'subscription',
		'ajax'
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
		$_doArray = array('contactus', 'docontactus', 'register', 'signup', 'requestemail', 'emailcode', 'activate', 'login', 'logout', 'lostpw', 'emailpassword', 'addmember', 'coppaform', 'resetpassword', 'regcheck', 'checkdate', 'removesubscription', 'imagereg', 'verifyusername');
		if (THIS_SCRIPT == 'sendmessage' AND $_REQUEST['do'] == '')
		{
			$_REQUEST['do'] = 'contactus';
		}
		if (THIS_SCRIPT == 'register' AND $_REQUEST['do'] == '' AND $vbulletin->GPC['a'] == '')
		{
			$_REQUEST['do'] = 'signup';
		}
		$_aArray = array('act', 'ver', 'pwd');
		if (!in_array($_REQUEST['do'], $_doArray) AND !in_array($vbulletin->GPC['a'], $_aArray))
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

// Set up threaded mode
if ($vbulletin->GPC['threadid'] AND $vbulletin->options['allowthreadedmode'])
{
	if ($vbulletin->GPC['mode'] != '' AND THIS_SCRIPT == 'showthread')
	{
		// Look for command to switch types on the query string
		switch ($vbulletin->GPC['mode'])
		{
			case 'threaded':
				$threadedmode = 1;
				$threadedCookieVal = 'threaded';
				break;
			case 'hybrid':
				$threadedmode = 2;
				$threadedCookieVal = 'hybrid';
				break;
			default:
				$threadedmode = 0;
				$threadedCookieVal = 'linear';
				break;
		}
		vbsetcookie('threadedmode', $threadedCookieVal);
		$vbulletin->GPC[COOKIE_PREFIX . 'threadedmode'] = $threadedCookieVal;
		unset($threadedCookieVal);
	}
	// Look for existing cookie, set from previous call to statement above us
	else if ($vbulletin->GPC[COOKIE_PREFIX . 'threadedmode'])
	{
		switch ($vbulletin->GPC[COOKIE_PREFIX . 'threadedmode'])
		{
			case 'threaded':
				$threadedmode = 1;
				break;
			case 'hybrid':
				$threadedmode = 2;
				break;
			default:
				$threadedmode = 0;
				break;
		}
	}
}

($hook = vBulletinHook::fetch_hook('global_setup_complete')) ? eval($hook) : false;

if (!empty($db->explain))
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
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
