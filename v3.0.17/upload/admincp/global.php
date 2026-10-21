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
define('VB_AREA', 'AdminCP');
if (!is_array($phrasegroups))
{
	$phrasegroups = array();
}
$phrasegroups[] = 'cpglobal';
if (!is_array($specialtemplates))
{
	$specialtemplates = array();
}
$specialtemplates[] = 'mailqueue';

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('SESSION_BYPASS', 1);

// ###################### Start functions #######################
chdir('./../');

require_once('./includes/init.php');
require_once('./includes/functions.php');
require_once('./includes/adminfunctions.php');

// ###################### Start sessions #######################
require_once('./includes/sessions.php');

// ###################### Start headers (send no-cache) #######################
exec_nocache_headers();

if ($bbuserinfo['cssprefs'] != '')
{
	$vboptions['cpstylefolder'] = $bbuserinfo['cssprefs'];
}

# cache full permissions so scheduled tasks will have access to them
$permissions = cache_permissions($bbuserinfo);
$bbuserinfo['permissions'] = &$permissions;

if (!($permissions['adminpermissions'] & CANCONTROLPANEL))
{
	$checkpwd = 1;
}

// ###################### Get date / time info #######################
// override date/time settings if specified
fetch_options_overrides($bbuserinfo);
fetch_time_data();

// ############################################ LANGUAGE STUFF ####################################
// initialize $vbphrase and set language constants
$vbphrase = init_language();
$_tmp = NULL;
$stylevar = fetch_stylevars($_tmp, $bbuserinfo);

// ############################################ Check for files existance ####################################
if (empty($debug) and !defined('BYPASS_FILE_CHECK'))
{
	// check for files existance. Potential security risks!
	if (file_exists('./install/install.php') == true)
	{
		print_stop_message('security_alert_x_still_exists', 'install.php');
	}
}

// ############################################ Start Login Check ####################################
$cpsession = array();
if (!empty($_COOKIE[COOKIE_PREFIX . 'cpsession']))
{
	$cpsession = $DB_site->query_first("
		SELECT * FROM " . TABLE_PREFIX . "cpsession
		WHERE userid = $bbuserinfo[userid]
			AND hash = '" . addslashes($_COOKIE[COOKIE_PREFIX . 'cpsession']) . "'
			AND dateline > " . iif($vboptions['timeoutcontrolpanel'], intval(TIMENOW - $vboptions['cookietimeout']), intval(TIMENOW - 3600))
	);

	if (!empty($cpsession))
	{
		$DB_site->shutdown_query("
			UPDATE LOW_PRIORITY " . TABLE_PREFIX . "cpsession
			SET dateline = " . TIMENOW . "
			WHERE userid = $bbuserinfo[userid]
				AND hash = '" . addslashes($_COOKIE[COOKIE_PREFIX . 'cpsession']) . "'
		");
	}
}

if ($checkpwd OR ($vboptions['timeoutcontrolpanel'] AND !$session['loggedin']) OR empty($_COOKIE[COOKIE_PREFIX . 'cpsession']) OR $_COOKIE[COOKIE_PREFIX . 'cpsession'] != $cpsession['hash'] OR empty($cpsession))
{

	// #############################################################################
	// Put in some auto-repair ;)
	$check = array();

	$spectemps = $DB_site->query("SELECT title FROM " . TABLE_PREFIX . "datastore");
	while ($spectemp = $DB_site->fetch_array($spectemps))
	{
		$check["$spectemp[title]"] = true;
	}
	$DB_site->free_result($spectemps);

	if (!$check['maxloggedin'])
	{
		build_datastore('maxloggedin');
	}
	if (!$check['smiliecache'])
	{
		build_datastore('smiliecache');
		build_image_cache('smilie');
	}
	if (!$check['iconcache'])
	{
		build_datastore('iconcache');
		build_image_cache('icon');
	}
	if (!$check['bbcodecache'])
	{
		build_datastore('bbcodecache');
		build_bbcode_cache();
	}
	if (!$check['rankphp'])
	{
		build_datastore('rankphp');
		require_once('./includes/adminfunctions_ranks.php');
		build_ranks();
	}
	if (!$check['userstats'])
	{
		build_datastore('userstats');
		require_once('./includes/functions_databuild.php');
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
		build_datastore('attachmentcache');
	}
	if (!$check['wol_spiders'])
	{
		build_datastore('wol_spiders');
	}
	if (!$check['banemail'])
	{
		build_datastore('banemail');
	}
	if (!$check['stylecache'])
	{
		require_once('./includes/adminfunctions_template.php');
		build_style_datastore();
	}
	if (!$check['usergroupcache'] OR !$check['forumcache'])
	{
		build_forum_permissions();
	}
	// end auto-repair
	// #############################################################################

	print_cp_login();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15324 $
|| ####################################################################
\*======================================================================*/
?>