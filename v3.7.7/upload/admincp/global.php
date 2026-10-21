<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// identify where we are
define('VB_AREA', 'AdminCP');
define('IN_CONTROL_PANEL', true);

if (!isset($phrasegroups) OR !is_array($phrasegroups))
{
	$phrasegroups = array();
}
$phrasegroups[] = 'cpglobal';

if (!isset($specialtemplates) OR !is_array($specialtemplates))
{
	$specialtemplates = array();
}
$specialtemplates[] = 'mailqueue';
$specialtemplates[] = 'pluginlistadmin';

// ###################### Start functions #######################
chdir('./../');
define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));

require_once(CWD . '/includes/init.php');
require_once(DIR . '/includes/adminfunctions.php');

// ###################### Start headers (send no-cache) #######################
exec_nocache_headers();

// Emulate IE7 rendering in IE8
if ($vbulletin->options['ie8render7'])
{
	@header('X-UA-Compatible: IE=7');
}

if ($vbulletin->userinfo['cssprefs'] != '')
{
	$vbulletin->options['cpstylefolder'] = $vbulletin->userinfo['cssprefs'];
}

# cache full permissions so scheduled tasks will have access to them
$permissions = cache_permissions($vbulletin->userinfo);
$vbulletin->userinfo['permissions'] =& $permissions;

if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
{
	$checkpwd = 1;
}

// ###################### Get date / time info #######################
// override date/time settings if specified
fetch_options_overrides($vbulletin->userinfo);
fetch_time_data();

// ############################################ LANGUAGE STUFF ####################################
// initialize $vbphrase and set language constants
$vbphrase = init_language();
$_tmp = NULL;
$stylevar = fetch_stylevars($_tmp, $vbulletin->userinfo);

// ############################################ Check for files existance ####################################
if (empty($vbulletin->debug) and !defined('BYPASS_FILE_CHECK'))
{
	// check for files existance. Potential security risks!
	if (file_exists(DIR . '/install/install.php') == true)
	{
		print_stop_message('security_alert_x_still_exists', 'install.php');
	}
	else if (file_exists(DIR . '/install/tools.php'))
	{
		print_stop_message('security_alert_tools_still_exists_in_x', 'install');
	}
	else if (file_exists(DIR . '/' . $vbulletin->config['Misc']['admincpdir'] . '/tools.php'))
	{
		print_stop_message('security_alert_tools_still_exists_in_x', $vbulletin->config['Misc']['admincpdir']);
	}
	else if (file_exists(DIR . '/' . $vbulletin->config['Misc']['modcpdir'] . '/tools.php'))
	{
		print_stop_message('security_alert_tools_still_exists_in_x', $vbulletin->config['Misc']['modcpdir']);
	}
}

// ############################################ Start Login Check ####################################
$cpsession = array();

$vbulletin->input->clean_array_gpc('p', array(
	'adminhash' => TYPE_STR
));

$vbulletin->input->clean_array_gpc('c', array(
	COOKIE_PREFIX . 'cpsession' => TYPE_STR,
));

if (!empty($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']))
{
	$cpsession = $db->query_first("
		SELECT * FROM " . TABLE_PREFIX . "cpsession
		WHERE userid = " . $vbulletin->userinfo['userid'] . "
			AND hash = '" . $db->escape_string($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']) . "'
			AND dateline > " . iif($vbulletin->options['timeoutcontrolpanel'], intval(TIMENOW - $vbulletin->options['cookietimeout']), intval(TIMENOW - 3600))
	);

	if (!empty($cpsession))
	{
		$db->query_write("
			UPDATE LOW_PRIORITY " . TABLE_PREFIX . "cpsession
			SET dateline = " . TIMENOW . "
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND hash = '" . $db->escape_string($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']) . "'
		");
	}
}

define('CP_SESSIONHASH', $cpsession['hash']);

if ($checkpwd OR ($vbulletin->options['timeoutcontrolpanel'] AND !$vbulletin->session->vars['loggedin']) OR empty($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']) OR $vbulletin->GPC[COOKIE_PREFIX . 'cpsession'] != $cpsession['hash'] OR empty($cpsession))
{

	// #############################################################################
	// Put in some auto-repair ;)
	$check = array();

	$spectemps = $db->query_read("SELECT title FROM " . TABLE_PREFIX . "datastore");
	while ($spectemp = $db->fetch_array($spectemps))
	{
		$check["$spectemp[title]"] = true;
	}
	$db->free_result($spectemps);

	if (!$check['maxloggedin'])
	{
		build_datastore('maxloggedin', '', 1);
	}
	if (!$check['smiliecache'])
	{
		build_datastore('smiliecache', '', 1);
		build_image_cache('smilie');
	}
	if (!$check['iconcache'])
	{
		build_datastore('iconcache', '', 1);
		build_image_cache('icon');
	}
	if (!$check['bbcodecache'])
	{
		build_datastore('bbcodecache', '', 1);
		build_bbcode_cache();
	}
	if (!$check['ranks'])
	{
		require_once(DIR . '/includes/functions_ranks.php');
		build_ranks();
	}
	if (!$check['userstats'])
	{
		build_datastore('userstats', '', 1);
		require_once(DIR . '/includes/functions_databuild.php');
		build_user_statistics();
	}
	if (!$check['mailqueue'])
	{
		build_datastore('mailqueue');
	}
	if (!$check['cron'])
	{
		build_datastore('cron');
	}
	if (!$check['attachmentcache'])
	{
		build_datastore('attachmentcache', '', 1);
	}
	if (!$check['wol_spiders'])
	{
		build_datastore('wol_spiders', '', 1);
	}
	if (!$check['banemail'])
	{
		build_datastore('banemail');
	}
	if (!$check['stylecache'])
	{
		require_once(DIR . '/includes/adminfunctions_template.php');
		build_style_datastore();
	}
	if (!$check['usergroupcache'] OR !$check['forumcache'])
	{
		build_forum_permissions();
	}
	if (!$check['bookmarksitecache'])
	{
		require_once(DIR . '/includes/adminfunctions_bookmarksite.php');
		build_bookmarksite_datastore();
	}
	if (!$check['noticecache'])
	{
		build_datastore('noticecache', '', 1);
	}
	if (!$check['loadcache'])
	{
		update_loadavg();
	}
	if (!$check['prefixcache'])
	{
		require_once(DIR . '/includes/adminfunctions_prefix.php');
		build_prefix_datastore();
	}

	($hook = vBulletinHook::fetch_hook('admin_global_datastore_check')) ? eval($hook) : false;

	// end auto-repair
	// #############################################################################

	print_cp_login();
}
else if ($_POST['do'] AND ADMINHASH != $vbulletin->GPC['adminhash'])
{
	print_cp_login(true);
}

if (file_exists(DIR . '/includes/version_vbulletin.php'))
{
	include_once(DIR . '/includes/version_vbulletin.php');
}
if (defined('FILE_VERSION_VBULLETIN') AND FILE_VERSION_VBULLETIN !== '')
{
	define('ADMIN_VERSION_VBULLETIN', FILE_VERSION_VBULLETIN);
}
else
{
	define('ADMIN_VERSION_VBULLETIN', $vbulletin->options['templateversion']);
}

($hook = vBulletinHook::fetch_hook('admin_global')) ? eval($hook) : false;

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 26608 $
|| ####################################################################
\*======================================================================*/
?>