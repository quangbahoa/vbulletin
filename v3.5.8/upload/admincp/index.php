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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 15899 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cphome');
$specialtemplates = array('maxloggedin');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// #############################################################################
// ########################### START MAIN SCRIPT ###############################
// #############################################################################

if (empty($_REQUEST['do']))
{
	log_admin_action();
}

// #############################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'redirect' => TYPE_STR,
	'nojs' 		=> TYPE_BOOL,
));

// #############################################################################
// ################################## REDIRECTOR ###############################
// #############################################################################

if (!empty($vbulletin->GPC['redirect']))
{
	require_once(DIR . '/includes/functions_login.php');
	$redirect = htmlspecialchars_uni(fetch_replaced_session_url($vbulletin->GPC['redirect']));

	print_cp_header($vbphrase['redirecting_please_wait'], '', "<meta http-equiv=\"Refresh\" content=\"0; URL=$redirect\" />");
	echo "<p>&nbsp;</p><blockquote><p>$vbphrase[redirecting_please_wait]</p></blockquote>";
	print_cp_footer();
	exit;
}

// #############################################################################
// ############################### LOG OUT OF CP ###############################
// #############################################################################

if ($_REQUEST['do'] == 'cplogout')
{
	vbsetcookie('cpsession', '', false, true);
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "cpsession WHERE userid = " . $vbulletin->userinfo['userid'] . " AND hash = '" . $db->escape_string($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']) . "'");
	vbsetcookie('customerid', '', 0);
	if (!empty($vbulletin->session->vars['sessionurl_js']))
	{
		exec_header_redirect('index.php?' . $vbulletin->session->vars['sessionurl_js']);
	}
	else
	{
		exec_header_redirect('index.php');
	}
}

// #############################################################################
// ################################# SAVE NOTES ################################
// #############################################################################

if ($_POST['do'] == 'notes')
{
	$vbulletin->input->clean_array_gpc('p', array('notes' => TYPE_STR));

	$admindm =& datamanager_init('Admin', $vbulletin, ERRTYPE_CP);
	$admindm->set_existing($vbulletin->userinfo);
	$admindm->set('notes', $vbulletin->GPC['notes']);
	$admindm->save();
	unset($admindm);

	$vbulletin->userinfo['notes'] = htmlspecialchars_uni($vbulletin->GPC['notes']);
	$_REQUEST['do'] = 'home';
}

// #############################################################################
// ############################### SAVE NAV PREFS ##############################
// #############################################################################

$vbulletin->input->clean_array_gpc('r', array('navprefs' => TYPE_STR));
$vbulletin->GPC['navprefs'] = preg_replace('#[^a-z0-9_,]#i', '', $vbulletin->GPC['navprefs']);

if ($_REQUEST['do'] == 'navprefs')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'groups'	=> TYPE_STR,
		'expand'	=> TYPE_BOOL,
		'navprefs'	=> TYPE_STR
	));

	$vbulletin->GPC['groups'] = preg_replace('#[^a-z0-9_,]#i', '', $vbulletin->GPC['groups']);

	if ($vbulletin->GPC['expand'])
	{
		$groups = explode(',', $vbulletin->GPC['groups']);

		foreach ($groups AS $group)
		{
			if (empty($group))
			{
				continue;
			}

			$vbulletin->input->clean_gpc('r', "num$group", TYPE_UINT);

			for ($i = 0; $i < $vbulletin->GPC["num$group"]; $i++)
			{
				$vbulletin->GPC['navprefs'][] = $group . "_$i";
			}
		}

		$vbulletin->GPC['navprefs'] = implode(',', $vbulletin->GPC['navprefs']);
	}
	else
	{
		$vbulletin->GPC['navprefs'] = '';
	}

	$vbulletin->GPC['navprefs'] = preg_replace('#[^a-z0-9_,]#i', '', $vbulletin->GPC['navprefs']);

	$_REQUEST['do'] = 'savenavprefs';
}

if ($_REQUEST['do'] == 'buildbitfields')
{
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);
	build_forum_permissions();

	define('CP_REDIRECT', 'index.php');
	print_stop_message('rebuilt_bitfields_successfully');

}

if ($_REQUEST['do'] == 'buildnavprefs')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefs' 	=> TYPE_STR,
		'dowhat'	=> TYPE_STR,
		'id'		=> TYPE_INT
	));

	$vbulletin->GPC['prefs'] = preg_replace('#[^a-z0-9_,]#i', '', $vbulletin->GPC['prefs']);
	$_tmp = preg_split('#,#', $vbulletin->GPC['prefs'], -1, PREG_SPLIT_NO_EMPTY);
	$_navprefs = array();

	foreach ($_tmp AS $_val)
	{
		$_navprefs["$_val"] = $_val;
	}
	unset($_tmp);

	if ($vbulletin->GPC['dowhat'] == 'collapse')
	{
		// remove an item from the list
		unset($_navprefs[$vbulletin->GPC['id']]);
	}
	else
	{
		// add an item to the list
		$_navprefs[$vbulletin->GPC['id']] = $vbulletin->GPC['id'];
		ksort($_navprefs);
	}

	$vbulletin->GPC['navprefs'] = implode(',', $_navprefs);
	$_REQUEST['do'] = 'savenavprefs';
}

if ($_REQUEST['do'] == 'savenavprefs')
{
	$admindm =& datamanager_init('Admin', $vbulletin, ERRTYPE_CP);
	$admindm->set_existing($vbulletin->userinfo);
	$admindm->set('navprefs', $vbulletin->GPC['navprefs']);
	$admindm->save();
	unset($admindm);

	$_NAVPREFS = preg_split('#,#', $vbulletin->GPC['navprefs'], -1, PREG_SPLIT_NO_EMPTY);
	$_REQUEST['do'] = 'nav';
}

// #############################################################################
// ################################ BUILD FRAMESET #############################
// #############################################################################

if ($_REQUEST['do'] == 'frames' OR empty($_REQUEST['do']))
{
	$vbulletin->input->clean_array_gpc('r', array(
		'loc' 		=> TYPE_NOHTML
	));

	$navframe = "<frame src=\"index.php?" . $vbulletin->session->vars['sessionurl'] . "do=nav" . iif($vbulletin->GPC['nojs'], '&amp;nojs=1') . "\" name=\"nav\" scrolling=\"yes\" frameborder=\"0\" marginwidth=\"0\" marginheight=\"0\" border=\"no\" />\n";
	$headframe = "<frame src=\"index.php?" . $vbulletin->session->vars['sessionurl'] . "do=head\" name=\"head\" scrolling=\"no\" noresize=\"noresize\" frameborder=\"0\" marginwidth=\"10\" marginheight=\"0\" border=\"no\" />\n";
	$mainframe = "<frame src=\"" . iif(!empty($vbulletin->GPC['loc']) AND !preg_match('#^[a-z]+:#i', $vbulletin->GPC['loc']), $vbulletin->GPC['loc'], "index.php?" . $vbulletin->session->vars['sessionurl'] . "do=home") . "\" name=\"main\" scrolling=\"yes\" frameborder=\"0\" marginwidth=\"10\" marginheight=\"10\" border=\"no\" />\n";

	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">
	<html dir="<?php echo $stylevar['textdirection']; ?>" lang="<?php echo $stylevar['languagecode']; ?>">
	<head>
	<script type="text/javascript">
	<!--
	// get out of any containing frameset
	if (self.parent.frames.length != 0)
	{
		self.parent.location.replace(document.location.href);
	}
	// -->
	</script>
	<title><?php echo $vbulletin->options['bbtitle'] . ' ' . $vbphrase['admin_control_panel']; ?></title>
	</head>

	<?php

	if ($stylevar['textdirection'] == 'ltr')
	{
	// left-to-right frameset
	?>
	<frameset cols="195,*"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">
		<?php echo $navframe; ?>
		<frameset rows="20,*"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">
			<?php echo $headframe; ?>
			<?php echo $mainframe; ?>
		</frameset>
	</frameset>
	<?php
	}
	else
	{
	// right-to-left frameset
	?>
	<frameset cols="*,195"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">
		<frameset rows="20,*"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">
			<?php echo $headframe; ?>
			<?php echo $mainframe; ?>
		</frameset>
		<?php echo $navframe; ?>
	</frameset>
	<?php
	}

	?>

	<noframes>
		<body>
			<p><?php echo $vbphrase['no_frames_support']; ?></p>
		</body>
	</noframes>
	</html>
	<?php
}

// ################################ MAIN FRAME #############################

if ($_REQUEST['do'] == 'home')
{

print_cp_header($vbphrase['welcome_to_the_vbulletin_admin_control_panel']);

// there have been problems reported with fsockopen() in some PHP versions.
// this should work around it though
if (phpversion() < '4.3.0' OR phpversion() > '4.3.2' OR @ini_get('allow_url_fopen') != 1)
{
	$fp = @fsockopen('version.vbulletin.com', 80, $errno, $errstr, 3);
}
else
{
	$fp = @fopen('http://version.vbulletin.com', 'r');
}
if ($fp)
{
	fclose($fp);
?>
<script type="text/javascript" src="http://version.vbulletin.com/versioncheck.js"></script>
<script type="text/javascript" src="http://version.vbulletin.com/version.js?v=<?php echo SIMPLE_VERSION; ?>&amp;id=VBS4AAFB47"></script>
<script type="text/javascript">
<!--
if (typeof(vb_version) != "undefined" && isNewerVersion("<?php echo $vbulletin->options['templateversion']; ?>", vb_version))
{
	var current_version = "<?php echo $vbulletin->options['templateversion']; ?>";
	var latest_string = "<?php echo $vbphrase['latest_version_available_x']; ?>";
	var current_string = "<?php echo $vbphrase['you_are_running_vbulletin_version_x']; ?>";
	var download_string = "<?php echo $vbphrase['download_vbulletin_x_from_members_area']; ?>";

	document.writeln('<table cellpadding="4" cellspacing="0" border="0" align="center" width="90%" class="tborder"><tr><td class="tcat" align="center"><b><?php echo $vbphrase['there_is_a_newer_vbulletin_version']; ?></b></td></tr><tr><td class="alt1" align="center">');
	document.writeln('<p><b><a href="http://www.vbulletin.com/forum/showthread.ph' + 'p?p=' + vb_announcementid + '" target="_blank">' + construct_phrase(latest_string, vb_version) + "</a></b></p>");
	document.writeln('<p>' + construct_phrase(current_string, current_version.bold()) + '</p>');
	document.writeln('<p><a href="http://members.vbulletin.com/" target="_blank">' + construct_phrase(download_string, vb_version.bold()) + '</a></p>');
	document.writeln('</td></tr></table>');
}
//-->
</script>

<?php
}

// look to see if MySQL is running in strict mode
if (empty($vbulletin->config['Database']['force_sql_mode']))
{
	// check to see if MySQL is running strict mode and recommend disabling it
	$db->hide_errors();
	$strict_mode_check = $db->query_first("SHOW VARIABLES LIKE 'sql\_mode'");
	if (strpos(strtolower($strict_mode_check['Value']), 'strict_') !== false)
	{
		print_table_start();
		print_table_header($vbphrase['mysql_strict_mode_warning']);
		print_description_row($vbphrase['mysql_running_strict_mode']);
		print_table_footer(2, '', '', false);
	}
	$db->show_errors();
}

// check if a PHP optimizer with known issues is installed
if (($err = verify_optimizer_environment()) !== true)
{
	print_table_start();
	print_table_header($vbphrase['problematic_php_optimizer_found']);
	print_description_row($vbphrase["$err"]);
	print_table_footer(2, '', '', false);
}

// before the quick stats, display the number of templates that need updating
require_once(DIR . '/includes/adminfunctions_template.php');
$need_updates = 0;

$update_templates = $db->query_read("
	SELECT tCustom.version AS customversion, tGlobal.version AS globalversion
	FROM " . TABLE_PREFIX . "template AS tCustom
	INNER JOIN " . TABLE_PREFIX . "template AS tGlobal ON (tGlobal.styleid = -1 AND tGlobal.title = tCustom.title)
	INNER JOIN " . TABLE_PREFIX . "style AS style ON (style.styleid = tCustom.styleid)
	WHERE tCustom.styleid <> -1
		AND tCustom.templatetype = 'template'
");
while ($update_template = $db->fetch_array($update_templates))
{
	if (is_newer_version($update_template['globalversion'], $update_template['customversion']))
	{
		$need_updates++;
	}
}

if ($need_updates)
{
	print_table_start();
	print_table_header($vbphrase['out_of_date_custom_templates_found']);
	print_description_row(construct_phrase(
		$vbphrase['currently_x_customized_templates_updated'],
		$need_updates,
		$vbulletin->session->vars['sessionurl']
	));
	print_table_footer(2, '', '', false);
}

// *******************************
// Admin Quick Stats -- Toggable via the CP
$starttime = mktime(0, 0, 0, date('m'), date('d'), date('Y'));

$mysqlversion = $db->query_first("SELECT VERSION() AS version");

if ($vbulletin->options['adminquickstats'])
{
	$attach = $db->query_first("SELECT SUM(filesize) AS size FROM " . TABLE_PREFIX . "attachment");
	$avatar = $db->query_first("SELECT SUM(filesize) AS size FROM " . TABLE_PREFIX . "customavatar");
	$profile = $db->query_first("SELECT SUM(filesize) AS size FROM " . TABLE_PREFIX . "customprofilepic");

	$newusers = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "user WHERE joindate >= $starttime");
	$newthreads = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "thread WHERE dateline >= $starttime");
	$newposts = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "post WHERE dateline >= $starttime");
	$users = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "user WHERE lastactivity >= $starttime");

	$indexsize = 0;
	$datasize = 0;
	if ($mysqlversion['version'] >= '3.23')
	{
		$db->hide_errors();
		$tables = $db->query_write("SHOW TABLE STATUS");
		$errno = $db->errno;
		$db->show_errors();
		if (!$errno)
		{
			while ($table = $db->fetch_array($tables))
			{
				$datasize += $table['Data_length'];
				$indexsize += $table['Index_length'];
			}
			if (!$indexsize)
			{
				$indexsize = $vbphrase['n_a'];
			}
			if (!$datasize)
			{
				$datasize = $vbphrase['n_a'];
			}
		}
		else
		{
			$datasize = $vbphrase['n_a'];
			$indexsize = $vbphrase['n_a'];
		}
	}
}

$db->hide_errors();
if ($variables = $db->query_first("SHOW VARIABLES LIKE 'max_allowed_packet'"))
{
	$maxpacket = $variables['Value'];
}
else
{
	$maxpacket = $vbphrase['n_a'];
}
$db->show_errors();

if (preg_match('#(Apache)/([0-9\.]+)\s#siU', $_SERVER['SERVER_SOFTWARE'], $wsregs))
{
	$webserver = "$wsregs[1] v$wsregs[2]";
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		$addsapi = true;
	}
}
else if (preg_match('#Microsoft-IIS/([0-9\.]+)#siU', $SERVER['SERVER_SOFTWARE'], $wsregs))
{
	$webserver = "IIS v$wsregs[1]";
	$addsapi = true;
}
else if (preg_match('#Zeus/([0-9\.]+)#siU', $SERVER['SERVER_SOFTWARE'], $wsregs))
{
	$webserver = "Zeus v$wsregs[1]";
	$addsapi = true;
}
else if (strtoupper($_SERVER['SERVER_SOFTWARE']) == 'APACHE')
{
	$webserver = 'Apache';
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		$addsapi = true;
	}
}
else
{
	$webserver = SAPI_NAME;
}

if ($addsapi)
{
	$webserver .= ' (' . SAPI_NAME . ')';
}

$serverinfo = iif(ini_get('safe_mode') == 1 OR strtolower(ini_get('safe_mode')) == 'on', "<br />$vbphrase[safe_mode]");
$serverinfo .= iif(ini_get('file_uploads') == 0 OR strtolower(ini_get('file_uploads')) == 'off', "<br />$vbphrase[file_uploads_disabled]");

$memorylimit = ini_get('memory_limit');

// ###### Users to Moderate
$waiting = $db->query_first("SELECT COUNT(*) AS users FROM " . TABLE_PREFIX . "user WHERE usergroupid = 4");

// ##### Attachments to Moderate
$attachcount = $db->query_first("
	SELECT COUNT(*) AS count
	FROM " . TABLE_PREFIX . "attachment AS attachment
	INNER JOIN " . TABLE_PREFIX . "post AS post USING (postid)
	WHERE attachment.visible = 0
");

// ##### Events to Moderate
$eventcount = $db->query_first("
	SELECT COUNT(*) AS count
	FROM " . TABLE_PREFIX . "event AS event
	INNER JOIN " . TABLE_PREFIX ."calendar AS calendar USING (calendarid)
	WHERE event.visible = 0
");

// ##### Posts to Moderate
$postcount = $db->query_first("
	SELECT COUNT(*) AS count
	FROM " . TABLE_PREFIX . "moderation AS moderation
	INNER JOIN " . TABLE_PREFIX . "post AS post USING (postid)
	WHERE moderation.type='reply'
");

// ##### Threads to Moderate
$threadcount = $db->query_first("
	SELECT COUNT(*) AS count
	FROM " . TABLE_PREFIX . "moderation AS moderation
	INNER JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
	WHERE moderation.type='thread'
");

print_form_header('index', 'home');
if ($vbulletin->options['adminquickstats'])
{
	print_table_header($vbphrase['welcome_to_the_vbulletin_admin_control_panel'], 6);
	print_cells_row(array(
		$vbphrase['server_type'], PHP_OS . $serverinfo,

		$vbphrase['database_data_usage'], vb_number_format($datasize, 2, true),
		$vbphrase['users_awaiting_moderation'], vb_number_format($waiting['users']) . ' ' . construct_link_code($vbphrase['view'], "user.php?" . $vbulletin->session->vars['sessionurl'] . "do=moderate"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['web_server'], $webserver,

		$vbphrase['database_index_usage'], vb_number_format($indexsize, 2, true),
		$vbphrase['threads_awaiting_moderation'], vb_number_format($threadcount['count']) . ' ' . construct_link_code($vbphrase['view'], '../' . $vbulletin->config['Misc']['modcpdir'] . '/moderate.php?' . $vbulletin->session->vars['sessionurl'] . "do=posts"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		'PHP', PHP_VERSION,
		$vbphrase['attachment_usage'], vb_number_format($attach['size'], 2, true),
		$vbphrase['posts_awaiting_moderation'], vb_number_format($postcount['count']) . ' ' . construct_link_code($vbphrase['view'],'../' . $vbulletin->config['Misc']['modcpdir'] . '/moderate.php?' . $vbulletin->session->vars['sessionurl'] . "do=posts#postlist"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_max_post_size'], ($postmaxsize = ini_get('post_max_size')) ? vb_number_format($postmaxsize, 2, true) : $vbphrase['n_a'],
		$vbphrase['custom_avatar_usage'], vb_number_format($avatar['size'], 2, true),
		$vbphrase['attachments_awaiting_moderation'], vb_number_format($attachcount['count']) . ' ' . construct_link_code($vbphrase['view'], '../' . $vbulletin->config['Misc']['modcpdir'] . '/moderate.php?' . $vbulletin->session->vars['sessionurl'] . "do=attachments"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_max_upload_size'], ($postmaxuploadsize = ini_get('upload_max_filesize')) ? vb_number_format($postmaxuploadsize, 2, true) : $vbphrase['n_a'],
		$vbphrase['custom_profile_picture_usage'], vb_number_format($profile['size'], 2, true),
		$vbphrase['events_awaiting_moderation'], vb_number_format($eventcount['count']) . ' ' . construct_link_code($vbphrase['view'], '../' . $vbulletin->config['Misc']['modcpdir'] . '/moderate.php?' . $vbulletin->session->vars['sessionurl'] . "do=events"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_memory_limit'], ($memorylimit AND $memorylimit != '-1') ? vb_number_format($memorylimit, 2, true) : $vbphrase['none'],
		$vbphrase['unique_registered_visitors_today'], vb_number_format($users['count']),
		$vbphrase['new_threads_today'], vb_number_format($newthreads['count']),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['mysql_version'], $mysqlversion['version'],
		$vbphrase['new_users_today'], vb_number_format($newusers['count']),
		$vbphrase['new_posts_today'], vb_number_format($newposts['count']),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array($vbphrase['mysql_max_packet_size'], vb_number_format($maxpacket, 2, 1), '&nbsp;', '&nbsp;', '&nbsp;', '&nbsp;'), 0, 0, -5, 'top', 1, 1);
}
else
{
	print_table_header($vbphrase['welcome_to_the_vbulletin_admin_control_panel'], 4);
	print_cells_row(array(
		$vbphrase['server_type'], PHP_OS . $serverinfo,
		$vbphrase['users_awaiting_moderation'], vb_number_format($waiting['users']) . ' ' . construct_link_code($vbphrase['view'], "user.php?" . $vbulletin->session->vars['sessionurl'] . "do=moderate")
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['web_server'], $webserver,
		$vbphrase['threads_awaiting_moderation'], vb_number_format($threadcount['count']) . ' ' . construct_link_code($vbphrase['view'], '../' . $vbulletin->config['Misc']['modcpdir'] . '/moderate.php?' . $vbulletin->session->vars['sessionurl'] . "do=posts")
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		'PHP', PHP_VERSION,
		$vbphrase['posts_awaiting_moderation'], vb_number_format($postcount['count']) . ' ' . construct_link_code($vbphrase['view'],'../' . $vbulletin->config['Misc']['modcpdir'] . '/moderate.php?' . $vbulletin->session->vars['sessionurl'] . "do=posts#postlist")
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_max_post_size'], ($postmaxsize = ini_get('post_max_size')) ? vb_number_format($postmaxsize, 2, true) : $vbphrase['n_a'],
		$vbphrase['attachments_awaiting_moderation'], vb_number_format($attachcount['count']) . ' ' . construct_link_code($vbphrase['view'], '../' . $vbulletin->config['Misc']['modcpdir'] . '/moderate.php?' . $vbulletin->session->vars['sessionurl'] . "do=attachments")
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_max_upload_size'], ($postmaxuploadsize = ini_get('upload_max_filesize')) ? vb_number_format($postmaxuploadsize, 2, true) : $vbphrase['n_a'],
		$vbphrase['events_awaiting_moderation'], vb_number_format($eventcount['count']) . ' ' . construct_link_code($vbphrase['view'], '../' . $vbulletin->config['Misc']['modcpdir'] . '/moderate.php?' . $vbulletin->session->vars['sessionurl'] . "do=events")
	), 0, 0, -5, 'top', 1, 1);
	if ($memorylimit AND $memorylimit != '-1')
	{
		print_cells_row(array(
			$vbphrase['php_memory_limit'], vb_number_format($memorylimit, 2, true),
			'&nbsp;', '&nbsp;'
		), 0, 0, -5, 'top', 1, 1);
	}
	print_cells_row(array(
		$vbphrase['mysql_version'], $mysqlversion['version'],
		'&nbsp;', '&nbsp;'
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array($vbphrase['mysql_max_packet_size'], vb_number_format($maxpacket, 2, 1), '&nbsp;', '&nbsp;'), 0, 0, -5, 'top', 1, 1);
}

print_table_footer();

($hook = vBulletinHook::fetch_hook('admin_index_main1')) ? eval($hook) : false;

// *************************************
// Administrator Notes

print_form_header('index', 'notes');
print_table_header($vbphrase['administrator_notes'], 1);
print_description_row("<textarea name=\"notes\" style=\"width: 90%\" rows=\"9\">" . $vbulletin->userinfo['notes'] . "</textarea>", false, 1, '', 'center');
print_submit_row($vbphrase['save'], 0, 1);

($hook = vBulletinHook::fetch_hook('admin_index_main2')) ? eval($hook) : false;

// *************************************
// QUICK ADMIN LINKS

print_table_start();
print_table_header($vbphrase['quick_administrator_links']);

$datecut = TIMENOW - $vbulletin->options['cookietimeout'];
$guestsarry = $db->query_first("SELECT COUNT(host) AS sessions FROM " . TABLE_PREFIX . "session WHERE userid = 0 AND lastactivity > $datecut");
$membersarry = $db->query_read("SELECT DISTINCT userid FROM " . TABLE_PREFIX . "session WHERE userid <> 0 AND lastactivity > $datecut");
$guests = intval($guestsarry['sessions']);
$members = intval($db->num_rows($membersarry));

// ### MAX LOGGEDIN USERS ################################
if (intval($vbulletin->maxloggedin['maxonline']) <= ($guests + $members))
{
	$vbulletin->maxloggedin['maxonline'] = $guests + $members;
	$vbulletin->maxloggedin['maxonlinedate'] = TIMENOW;
	build_datastore('maxloggedin', serialize($vbulletin->maxloggedin));
}

if ($stats = @exec('uptime 2>&1') AND trim($stats) != '' AND preg_match("#: ([\d.,]+),?\s+([\d.,]+),?\s+([\d.,]+)$#", $stats, $regs))
{
	$regs[1] = vb_number_format($regs[1], 2);
	$regs[2] = vb_number_format($regs[2], 2);
	$regs[3] = vb_number_format($regs[3], 2);

	print_label_row($vbphrase['server_load_averages'], "$regs[1]&nbsp;&nbsp;$regs[2]&nbsp;&nbsp;$regs[3] | " . construct_phrase($vbphrase['users_online_x_members_y_guests'], vb_number_format($guests + $members), vb_number_format($members), vb_number_format($guests)), '', 'top', NULL, false);
}
else
{
	print_label_row($vbphrase['users_online'], construct_phrase($vbphrase['x_y_members_z_guests'], vb_number_format($guests + $members), vb_number_format($members), vb_number_format($guests)), '', 'top', NULL, false);
}

//require_once(DIR . '/includes/adminfunctions_reminders.php');
//$reminders = fetch_reminders_array();
//print_label_row($vbphrase['due_tasks'], construct_phrase($vbphrase['you_have_x_tasks_due'], $reminders['total']) . construct_link_code($vbphrase['view_reminders'], "reminder.php?" . $vbulletin->session->vars['sessionurl']));

if (can_administer('canadminusers'))
{
	print_label_row($vbphrase['quick_user_finder'], '
		<form action="user.php" method="post" style="display:inline">
		<input type="hidden" name="s" value="' . $vbulletin->session->vars['sessionhash'] . '" />
		<input type="hidden" name="do" value="find" />
		<input type="text" class="bginput" name="user[username]" size="30" tabindex="1" />
		<input type="submit" value=" ' . $vbphrase['find'] . ' " class="button" tabindex="1" />
		<input type="submit" class="button" value="' . $vbphrase['exact_match'] . '" tabindex="1" name="user[exact]" />
		</form>
		', '', 'top', NULL, false
	);
}

print_label_row($vbphrase['php_function_lookup'], '
	<form action="http://www.ph' . 'p.net/manual-lookup.ph' . 'p" method="get" style="display:inline">
	<input type="text" class="bginput" name="function" size="30" tabindex="1" />
	<input type="submit" value=" ' . $vbphrase['find'] . ' " class="button" tabindex="1" />
	</form>
	', '', 'top', NULL, false
);
print_label_row($vbphrase['mysql_language_lookup'], '
	<form action="http://www.mysql.com/search/" method="get" style="display:inline">
	<input type="hidden" name="doc" value="1" />
	<input type="hidden" name="m" value="o" />
	<input type="text" class="bginput" name="q" size="30" tabindex="1" />
	<input type="submit" value=" ' . $vbphrase['find'] . ' " class="button" tabindex="1" />
	</form>
	', '', 'top', NULL, false
);
print_label_row($vbphrase['useful_links'], '
	<form style="display:inline">
	<select onchange="if (this.options[this.selectedIndex].value != \'\') { window.open(this.options[this.selectedIndex].value); } return false;" tabindex="1" class="bginput">
		<option value="">-- ' . $vbphrase['useful_links'] . ' --</option>' . construct_select_options(array(
			'vBulletin' => array(
				'http://www.vbulletin.com/' => $vbphrase['home_page'] . ' (vBulletin.com)',
				'http://members.vbulletin.com/' => $vbphrase['members_area'],
				'http://www.vbulletin.com/forum/' => $vbphrase['community_forums'],
				'http://www.vbulletin.com/docs/html/' => $vbphrase['reference_manual']
			),
			'PHP' => array(
				'http://www.ph' . 'p.net/' => $vbphrase['home_page'] . ' (PHP.net)',
				'http://www.ph' . 'p.net/manual/' => $vbphrase['reference_manual'],
				'http://www.ph' . 'p.net/downloads.ph' . 'p' => $vbphrase['download_latest_version']
			),
			'MySQL' => array(
				'http://www.mysql.com/' => $vbphrase['home_page'] . ' (MySQL.com)',
				'http://www.mysql.com/documentation/' => $vbphrase['reference_manual'],
				'http://www.mysql.com/downloads/' => $vbphrase['download_latest_version'],
			)
	)) . '</select>
	</form>
	', '', 'top', NULL, false
);
print_table_footer(2, '', '', false);

($hook = vBulletinHook::fetch_hook('admin_index_main3')) ? eval($hook) : false;

// *************************************
// vBULLETIN CREDITS
require_once(DIR . '/includes/vbulletin_credits.php');

?>

<div class="smallfont" align="center">
<!--<?php echo construct_phrase($vbphrase['vbulletin_copyright'], $vbulletin->options['templateversion'], date('Y')); ?><br />-->
<script type="text/javascript">
<!--
if (typeof(vb_version) != "undefined")
{
	var this_vb_version = "<?php echo $vbulletin->options['templateversion']; ?>";
	if (isNewerVersion(this_vb_version, vb_version))
	{
		document.writeln('<a href="http://www.vbulletin.com/forum/showthread.ph' + 'p?p=' + vb_announcementid + '" target="_blank">' + construct_phrase(latest_string, vb_version) + '</a><br />' + construct_phrase(current_string, this_vb_version.bold()));
	}
	else
	{
		document.write(construct_phrase('<?php echo $vbphrase['your_version_of_vbulletin_is_up_to_date']; ?>', this_vb_version));
	}
}
// -->
</script>
</div>

<?php

echo $reminders['script'];

unset($DEVDEBUG);
print_cp_footer();

}

// ################################ NAVIGATION FRAME #############################

if ($_REQUEST['do'] == 'nav')
{
	require_once(DIR . '/includes/adminfunctions_navpanel.php');
	print_cp_header();

	echo "\n<div>";
	?><img src="../cpstyles/<?php echo $vbulletin->options['cpstylefolder']; ?>/cp_logo.gif" title="<?php echo $vbphrase['admin_control_panel']; ?>" alt="" border="0" hspace="4" <?php $df = print_form_middle("VBS4AAFB47"); ?> vspace="4" /><?php
	echo "</div>\n\n" . iif(is_demo_mode(), "<div align=\"center\"><b>DEMO MODE</b></div>\n\n") . "<div style=\"width:168px; padding: 4px\">\n";

	// cache nav prefs
	can_administer();
	construct_nav_spacer();

	$navigation = array(); // [displayorder][phrase/text] = array([group], [options][disporder][])

	require_once(DIR . '/includes/class_xml.php');

	$navfiles = array();
	if ($handle = @opendir(DIR . '/includes/xml/'))
	{
		while (($file = readdir($handle)) !== false)
		{
			if (!preg_match('#^cpnav_(.*).xml$#i', $file, $matches))
			{
				continue;
			}

			$navfiles["$matches[1]"] = $file;
		}
		closedir($handle);
	}

	if (empty($navfiles['vbulletin']))	// opendir failed or cpnav_vbulletin.xml is missing
	{
		if (is_readable(DIR . '/includes/xml/cpnav_vbulletin.xml'))
		{
			$navfiles['vbulletin'] = 'cpnav_vbulletin.xml';
		}
		else
		{
			echo construct_phrase($vbphrase['could_not_open_x'], DIR . '/includes/xml/cpnav_vbulletin.xml');
			exit;
		}
	}

	foreach ($navfiles AS $nav_file => $file)
	{
		$xmlobj = new XMLparser(false, DIR . "/includes/xml/$file");
		$xml =& $xmlobj->parse();

		if ($xml['product'] AND empty($vbulletin->products["$xml[product]"]))
		{
			// attached to a specific product and that product isn't enabled
			continue;
		}

		if (!is_array($xml['navgroup'][0]))
		{
			$xml['navgroup'] = array($xml['navgroup']);
		}

		foreach ($xml['navgroup'] AS $navgroup)
		{
			// do we have access to this group
			if (empty($navgroup['permissions']) OR can_administer($navgroup['permissions']))
			{
				$group_displayorder = intval($navgroup['displayorder']);
				$group_key = (isset($navgroup['phrase']) AND isset($vbphrase["$navgroup[phrase]"])) ? $vbphrase["$navgroup[phrase]"] : $navgroup['text'];

				if (!isset($navigation["$group_displayorder"]["$group_key"]))
				{
					$navigation["$group_displayorder"]["$group_key"] = array('options' => array());
				}
				$local_options =& $navigation["$group_displayorder"]["$group_key"]['options'];

				if (!is_array($navgroup['navoption'][0]))
				{
					$navgroup['navoption'] = array($navgroup['navoption']);
				}
				foreach ($navgroup['navoption'] AS $navoption)
				{
					if (!empty($navoption['debug']) AND $vbulletin->debug != 1)
					{
						continue;
					}

					$navoption['link'] = str_replace(
						array(
							'{$vbulletin->config[Misc][modcpdir]}',
							'{$vbulletin->config[Misc][admincpdir]}'
						),
						array($vbulletin->config['Misc']['modcpdir'], $vbulletin->config['Misc']['admincpdir']),
						$navoption['link']
					);
					$navoption['text'] = (isset($navoption['phrase']) AND isset($vbphrase["$navoption[phrase]"])) ? $vbphrase["$navoption[phrase]"] : $navoption['text'];

					$local_options[intval($navoption['displayorder'])]["$navoption[text]"] = $navoption;
				}

				if (!isset($navigation["$group_displayorder"]["$group_key"]['group']) OR $xml['master'])
				{
					unset($navgroup['navoption']);
					$navgroup['nav_file'] = $nav_file;
					$navgroup['text'] = $group_key;

					$navigation["$group_displayorder"]["$group_key"]['group'] = $navgroup;
				}
			}
		}

		$xmlobj = null;
		unset($xml);
	}

	($hook = vBulletinHook::fetch_hook('admin_index_navigation')) ? eval($hook) : false;

	// sort groups by display order
	ksort($navigation);
	foreach ($navigation AS $group_keys)
	{
		foreach ($group_keys AS $group_key => $navgroup_holder)
		{
			// sort options by display order
			ksort($navgroup_holder['options']);

			foreach ($navgroup_holder['options'] AS $navoption_holder)
			{
				foreach ($navoption_holder AS $navoption)
				{
					construct_nav_option($navoption['text'], $navoption['link']);
				}
			}

			// have all the options, so do the group
			construct_nav_group($navgroup_holder['group']['text'], $navgroup_holder['group']['nav_file']);

			if ($navgroup_holder['group']['hr'] == 'true')
			{
				construct_nav_spacer();
			}
		}
	}

	print_nav_panel();

	unset($navigation);

	echo "</div>\n";
	// *************************************************

	define('NO_CP_COPYRIGHT', true);
	unset($DEVDEBUG);
	print_cp_footer();

}

// #############################################################################
// ################################# HEADER FRAME ##############################
// #############################################################################

if ($_REQUEST['do'] == 'head')
{
	ignore_user_abort(true);

	define('IS_NAV_PANEL', true);
	if (phpversion() < '4.3.0' OR phpversion() > '4.3.2' OR @ini_get('allow_url_fopen') != 1)
	{
		$fp = @fsockopen('version.vbulletin.com', 80, $errno, $errstr, 3);
	}
	else
	{
		$fp = @fopen('http://version.vbulletin.com', 'r');
	}
	$headjs = '';
	if ($fp)
	{
		fclose($fp);
		$headjs = '<script type="text/javascript" src="http://version.vbulletin.com/version.js?v=' . SIMPLE_VERSION . '&amp;id=VBS4AAFB47"></script>';
	}
	print_cp_header('', '', $headjs);

	?>
	<script type="text/javascript">
	<!--
	if (typeof(vb_version) == "undefined")
	{
		var vb_version = '<?php echo $vbphrase['n_a']; ?>';
	}
	// -->
	</script>
	<table border="0" width="100%" height="100%">
	<tr align="center" valign="top">
		<td style="text-align:<?php echo $stylevar['left']; ?>"><a href="http://www.vbulletin.com/" target="_blank"><b><?php echo $vbphrase['admin_control_panel']; ?></b> (vBulletin <?php echo $vbulletin->versionnumber . print_form_middle('VBS4AAFB47'); ?>)<?php echo iif(is_demo_mode(), ' <b>DEMO MODE</b>'); ?></a></td>
		<td><a href="http://members.vbulletin.com/" target="_blank"><script type="text/javascript"> document.write(construct_phrase('<?php echo $vbphrase['latest_version_available_x']; ?>', vb_version));</script></a></td>
		<td style="white-space:nowrap; text-align:<?php echo $stylevar['right']; ?>; font-weight:bold">
			<a href="../<?php echo $vbulletin->options['forumhome']; ?>.php<?php echo $vbulletin->session->vars['sessionurl_q']; ?>" target="_blank"><?php echo $vbphrase['forum_home_page']; ?></a>
			|
			<a href="index.php?<?php echo $vbulletin->session->vars['sessionurl']; ?>do=cplogout" onclick="return confirm('<?php echo $vbphrase['sure_you_want_to_log_out_of_cp']; ?>');"  target="_top"><?php echo $vbphrase['log_out']; ?></a>
		</td>
	</tr>
	</table>
	<?php

	define('NO_CP_COPYRIGHT', true);
	unset($DEVDEBUG);
	print_cp_footer();

}

// ################################ SHOW PHP INFO #############################

if ($_REQUEST['do'] == 'phpinfo')
{
	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}
	else
	{
		phpinfo();
		exit;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15899 $
|| ####################################################################
\*======================================================================*/
?>
