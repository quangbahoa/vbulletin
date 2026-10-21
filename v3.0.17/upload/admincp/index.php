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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 15900 $');
define('NO_REGISTER_GLOBALS', 1);

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

globalize($_REQUEST, array(
	'redirect' => STR_NOHTML,
	'nojs' => INT,
	'loc' => STR_NOHTML
));

// #############################################################################
// ################################## REDIRECTOR ###############################
// #############################################################################

if (!empty($redirect))
{
	require_once('./includes/functions_login.php');
	$redirect = fetch_replaced_session_url($redirect);

	print_cp_header($vbphrase['redirecting_please_wait'], '', "<meta http-equiv=\"Refresh\" content=\"0; URL=$redirect\">");
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
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "cpsession WHERE userid = $bbuserinfo[userid] AND hash = '" . addslashes($_COOKIE[COOKIE_PREFIX . 'cpsession']) . "'");
	vbsetcookie('customerid', '', 0);
	exec_header_redirect("index.php?$session[sessionurl_js]");
}

// #############################################################################
// ################################# SAVE NOTES ################################
// #############################################################################

if ($_POST['do'] == 'notes')
{
	globalize($_POST, array('notes' => STR));
	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "administrator
		SET notes = '" . addslashes(htmlspecialchars_uni($notes)) . "'
		WHERE userid = $bbuserinfo[userid]
	");
	$bbuserinfo['notes'] = htmlspecialchars_uni($notes);
	$_REQUEST['do'] = 'home';
}

// #############################################################################
// ############################### SAVE NAV PREFS ##############################
// #############################################################################

if ($_REQUEST['do'] == 'navprefs')
{
	globalize($_REQUEST, array('numgroups' => INT, 'expand' => INT));

	if ($expand)
	{
		$_REQUEST['navprefs'] = array();
		for ($i = 0; $i < $numgroups; $i++)
		{
			$_REQUEST['navprefs'][] = $i;
		}
		$_REQUEST['navprefs'] = implode(',', $_REQUEST['navprefs']);
	}
	else
	{
		$_REQUEST['navprefs'] = '';
	}

	$_REQUEST['navprefs'] = preg_replace('#[^a-z0-9_,]#i', '', $_REQUEST['navprefs']);

	$_REQUEST['do'] = 'savenavprefs';
}

if ($_REQUEST['do'] == 'buildnavprefs')
{
	globalize($_REQUEST, array('prefs' => STR, 'dowhat' => STR, 'id' => INT));

	$prefs = preg_replace('#[^a-z0-9_,]#i', '', $prefs);
	$_tmp = preg_split('#,#', $prefs, -1, PREG_SPLIT_NO_EMPTY);
	$_navprefs = array();

	foreach ($_tmp AS $_val)
	{
		$_navprefs["$_val"] = $_val;
	}
	unset($_tmp);

	if ($dowhat == 'collapse')
	{
		// remove an item from the list
		unset($_navprefs["$id"]);
	}
	else
	{
		// add an item to the list
		$_navprefs["$id"] = $id;
		ksort($_navprefs);
	}

	$_REQUEST['navprefs'] = implode(',', $_navprefs);
	$_REQUEST['do'] = 'savenavprefs';
}

if ($_REQUEST['do'] == 'savenavprefs')
{
	globalize($_REQUEST, array('navprefs' => STR));

	if (preg_match('#^[0-9,]*$#', $navprefs))
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "administrator
			SET navprefs = '" . addslashes($navprefs) . "'
			WHERE userid = $bbuserinfo[userid]
		");
	}

	$navprefs = preg_replace('#[^a-z0-9_,]#i', '', $navprefs);

	$_NAVPREFS = preg_split('#,#', $navprefs, -1, PREG_SPLIT_NO_EMPTY);
	$_REQUEST['do'] = 'nav';
}

// #############################################################################
// ################################ BUILD FRAMESET #############################
// #############################################################################

if ($_REQUEST['do'] == 'frames' OR empty($_REQUEST['do']))
{

$navframe = "<frame src=\"index.php?$session[sessionurl]do=nav" . iif($nojs, '&amp;nojs=1') . "\" name=\"nav\" scrolling=\"yes\" frameborder=\"0\" marginwidth=\"0\" marginheight=\"0\" border=\"no\" />\n";
$headframe = "<frame src=\"index.php?$session[sessionurl]do=head\" name=\"head\" scrolling=\"no\" noresize=\"noresize\" frameborder=\"0\" marginwidth=\"10\" marginheight=\"0\" border=\"no\" />\n";
$mainframe = "<frame src=\"" . iif(!empty($loc) AND !preg_match('#^[a-z]+:#i', $loc), $loc, "index.php?$session[sessionurl]do=home") . "\" name=\"main\" scrolling=\"yes\" frameborder=\"0\" marginwidth=\"10\" marginheight=\"10\" border=\"no\" />\n";

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
<title><?php echo $vboptions['bbtitle'] . ' ' . $vbphrase['admin_control_panel']; ?></title>
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
if (typeof(vb_version) != "undefined" && isNewerVersion("<?php echo $vboptions['templateversion']; ?>", vb_version))
{
	var current_version = "<?php echo $vboptions['templateversion']; ?>";
	var latest_string = "<?php echo $vbphrase['latest_version_available_x']; ?>";
	var current_string = "<?php echo $vbphrase['you_are_running_vbulletin_version_x']; ?>";
	var download_string = "<?php echo $vbphrase['download_vbulletin_x_from_members_area']; ?>";

	document.writeln('<table cellpadding="4" cellspacing="0" border="0" align="center" width="90%" class="tborder"><tr><td class="tcat" align="center"><b><?php echo $vbphrase['there_is_a_newer_vbulletin_version']; ?></b></td></tr><tr><td class="alt1" align="center">');
	document.writeln('<p><b><a href="http://www.vbulletin.com/forum/showthread.ph' + 'p?postid=' + vb_announcementid + '" target="_blank">' + construct_phrase(latest_string, vb_version) + "</a></b></p>");
	document.writeln('<p>' + construct_phrase(current_string, current_version.bold()) + '</p>');
	document.writeln('<p><a href="http://www.vbulletin.com/members/" target="_blank">' + construct_phrase(download_string, vb_version.bold()) + '</a></p>');
	document.writeln('</td></tr></table>');
}
//-->
</script>

<?php
}

// *******************************
// Admin Quick Stats -- Toggable via the CP
if ($vboptions['adminquickstats'])
{

	$waiting = $DB_site->query_first("SELECT COUNT(*) AS users FROM " . TABLE_PREFIX . "user WHERE usergroupid = 4");

	$starttime = mktime(0, 0, 0, date('m'), date('d'), date('Y'));

	$attach = $DB_site->query_first("SELECT SUM(filesize) AS size FROM " . TABLE_PREFIX . "attachment");
	$avatar = $DB_site->query_first("SELECT SUM(filesize) AS size FROM " . TABLE_PREFIX . "customavatar");
	$profile = $DB_site->query_first("SELECT SUM(filesize) AS size FROM " . TABLE_PREFIX . "customprofilepic");

	$newusers = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "user WHERE joindate >= $starttime");
	$newthreads = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "thread WHERE dateline >= $starttime");
	$newposts = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "post WHERE dateline >= $starttime");
	$users = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "user WHERE lastactivity >= $starttime");

	$mysqlversion = $DB_site->query_first("SELECT VERSION() AS version");

	$indexsize = 0;
	$datasize = 0;
	if ($mysqlversion['version'] >= '3.23')
	{
		$DB_site->reporterror = 0;
		$tables = $DB_site->query("SHOW TABLE STATUS");
		$errno = $DB_site->errno;
		$DB_site->reporterror = 1;
		if (!$errno)
		{
			while ($table = $DB_site->fetch_array($tables))
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

	$DB_site->reporterror = 0;
	if ($variables = $DB_site->query_first("SHOW VARIABLES LIKE 'max_allowed_packet'"))
	{
		$maxpacket = $variables['Value'];
	}
	else
	{
		$maxpacket = $vbphrase['n_a'];
	}
	$DB_site->reporterror = 1;

	$attachcount = $DB_site->query_first("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "attachment AS attachment
		INNER JOIN " . TABLE_PREFIX . "post USING (postid)
		WHERE attachment.visible = 0
	");
	$eventcount = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "event WHERE visible = 0");

	if (strpos(SAPI_NAME, 'apache') !== false AND preg_match('#(Apache)/([0-9\.]+)\s#siU', $_SERVER['SERVER_SOFTWARE'], $wsregs))
	{
		$webserver = "$wsregs[1] v$wsregs[2]";
	}
	else
	{
		$webserver = SAPI_NAME;
	}

	$serverinfo = iif(ini_get('safe_mode') == 1 OR strtolower(ini_get('safe_mode')) == 'on', "<br />$vbphrase[safe_mode]");
	$serverinfo .= iif(ini_get('file_uploads') == 0 OR strtolower(ini_get('file_uploads')) == 'off', "<br />$vbphrase[file_uploads_disabled]");
	$postcount = $DB_site->query_first("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "moderation AS moderation
		INNER JOIN " . TABLE_PREFIX . "post USING (postid)
		WHERE moderation.type='reply'
	");
	$threadcount = $DB_site->query_first("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "moderation AS moderation
		INNER JOIN " . TABLE_PREFIX . "thread USING (threadid)
		WHERE moderation.type='thread'
	");
	$memorylimit = ini_get('memory_limit');

	print_form_header('index', 'home');
	print_table_header($vbphrase['welcome_to_the_vbulletin_admin_control_panel'], 6);

	print_cells_row(array(
		$vbphrase['server_type'], PHP_OS . $serverinfo,

		$vbphrase['database_data_usage'], convert_kb_to_mb($datasize),
		$vbphrase['users_awaiting_moderation'], vb_number_format($waiting['users']) . ' ' . construct_link_code($vbphrase['view'], "user.php?$session[sessionurl]do=moderate"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['web_server'], $webserver,

		$vbphrase['database_index_usage'], convert_kb_to_mb($indexsize),
		$vbphrase['threads_awaiting_moderation'], vb_number_format($threadcount['count']) . ' ' . construct_link_code($vbphrase['view'], "../$modcpdir/moderate.php?$session[sessionurl]do=posts"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		'PHP', PHP_VERSION,
		$vbphrase['attachment_usage'], convert_kb_to_mb($attach['size']),
		$vbphrase['posts_awaiting_moderation'], vb_number_format($postcount['count']) . ' ' . construct_link_code($vbphrase['view'], "../$modcpdir/moderate.php?$session[sessionurl]do=posts#postlist"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_max_post_size'], iif(ini_get('post_max_size'), ini_get('post_max_size'), $vbphrase['n_a']),
		$vbphrase['custom_avatar_usage'], convert_kb_to_mb($avatar['size']),
		$vbphrase['attachments_awaiting_moderation'], vb_number_format($attachcount['count']) . ' ' . construct_link_code($vbphrase['view'], "../$modcpdir/moderate.php?$session[sessionurl]do=attachments"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_memory_limit'], iif($memorylimit AND $memorylimit != '-1', $memorylimit, $vbphrase['none']),
		$vbphrase['custom_profile_picture_usage'], convert_kb_to_mb($profile['size']),
		$vbphrase['events_awaiting_moderation'], vb_number_format($eventcount['count']) . ' ' . construct_link_code($vbphrase['view'], "../$modcpdir/moderate.php?$session[sessionurl]do=events"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['mysql'], $mysqlversion['version'],
		$vbphrase['unique_registered_visitors_today'], vb_number_format($users['count']),
		$vbphrase['new_threads_today'], vb_number_format($newthreads['count']),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['mysql_max_packet_size'], convert_kb_to_mb($maxpacket),
		$vbphrase['new_users_today'], vb_number_format($newusers['count']),
		$vbphrase['new_posts_today'], vb_number_format($newposts['count']),
	), 0, 0, -5, 'top', 1, 1);
	print_table_footer();

}

// *************************************
// Administrator Notes

print_form_header('index', 'notes');
print_table_header($vbphrase['administrator_notes'], 1);
print_description_row("<textarea name=\"notes\" style=\"width: 90%\" rows=\"9\">$bbuserinfo[notes]</textarea>", false, 1, '', 'center');
print_submit_row($vbphrase['save'], 0, 1);

// *************************************
// QUICK ADMIN LINKS

print_table_start();
print_table_header($vbphrase['quick_administrator_links']);

if ($stats = @exec('uptime 2>&1') AND trim($stats) != '')
{
	if (preg_match("#: ([\d.,]+),?\s+([\d.,]+),?\s+([\d.,]+)$#", $stats, $regs))
	{

		$datecut = TIMENOW - $vboptions['cookietimeout'];
		$guestsarry = $DB_site->query_first("SELECT COUNT(host) AS sessions FROM " . TABLE_PREFIX . "session WHERE userid = 0 AND lastactivity > $datecut");
		$membersarry = $DB_site->query("SELECT DISTINCT userid FROM " . TABLE_PREFIX . "session WHERE userid <> 0 AND lastactivity > $datecut");

		$guests = intval($guestsarry['sessions']);
		$members = intval($DB_site->num_rows($membersarry));

		$regs[1] = vb_number_format($regs[1], 2);
		$regs[2] = vb_number_format($regs[2], 2);
		$regs[3] = vb_number_format($regs[3], 2);

		// ### MAX LOGGEDIN USERS ################################
		$maxusers = unserialize($datastore['maxloggedin']);
		if (intval($maxusers['maxonline']) <= ($guests + $members))
		{
			$maxusers['maxonline'] = $guests + $members;
			$maxusers['maxonlinedate'] = TIMENOW;
			build_datastore('maxloggedin', serialize($maxusers));
		}

		print_label_row($vbphrase['server_load_averages'], "$regs[1]&nbsp;&nbsp;$regs[2]&nbsp;&nbsp;$regs[3] | " . construct_phrase($vbphrase['users_online_x_members_y_guests'], vb_number_format($guests + $members), vb_number_format($members), vb_number_format($guests)), '', 'top', NULL, false);
	}
}

//require_once('./includes/adminfunctions_reminders.php');
//$reminders = fetch_reminders_array();
//print_label_row($vbphrase['due_tasks'], construct_phrase($vbphrase['you_have_x_tasks_due'], $reminders['total']) . construct_link_code($vbphrase['view_reminders'], "reminder.php?$session[sessionurl]"));

if (can_administer('canadminusers'))
{
	print_label_row($vbphrase['quick_user_finder'], '
		<form action="user.php" method="post" style="display:inline">
		<input type="hidden" name="s" value="' . $session['sessionhash'] . '" />
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
				'http://www.vbulletin.com/members/' => $vbphrase['members_area'],
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

// *************************************
// vBULLETIN CREDITS
require_once('./includes/vbulletin_credits.php');

?>

<div class="smallfont" align="center">
<!--<?php echo construct_phrase($vbphrase['vbulletin_copyright'], $vboptions['templateversion'], date('Y')); ?><br />-->
<script type="text/javascript">
<!--
if (typeof(vb_version) != "undefined")
{
	var this_vb_version = "<?php echo $vboptions['templateversion']; ?>";
	if (isNewerVersion(this_vb_version, vb_version))
	{
		document.writeln('<a href="http://www.vbulletin.com/forum/showthread.ph' + 'p?postid=' + vb_announcementid + '" target="_blank">' + construct_phrase(latest_string, vb_version) + '</a><br />' + construct_phrase(current_string, this_vb_version.bold()));
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
	require_once('./includes/adminfunctions_navpanel.php');
	print_cp_header();

	echo "\n<div>";
	?><img src="../cpstyles/<?php echo $vboptions['cpstylefolder']; ?>/cp_logo.gif" title="<?php echo $vbphrase['admin_control_panel']; ?>" alt="" border="0" hspace="4" <?php $df = print_form_middle("VBS4AAFB47"); ?> vspace="4" /><?php
	echo "</div>\n\n" . iif(is_demo_mode(), "<div align=\"center\"><b>DEMO MODE</b></div>\n\n") . "<div style=\"width:168px; padding: 4px\">\n";

	// cache nav prefs
	can_administer();

	construct_nav_spacer();

	// *************************************************

	$printhr = false;
	if (can_administer('canadminsettings'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['vbulletin_options'], 'options.php?null=0', '<br />');
		if ($debug == 1)
		{
			construct_nav_option($vbphrase['admin_help_manager'], 'help.php?do=manage&script=NOSCRIPT');
		}
		construct_nav_group($vbphrase['vbulletin_options'], '<hr />');
	}
	if ($printhr == true)
	{
		construct_nav_spacer();
	}

	// *************************************************

	$printhr = false;
	if (can_administer('canadminstyles'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['style_manager'], 'template.php?do=modify', '<br />');
		construct_nav_option($vbphrase['search_in_templates'], 'template.php?do=search', '<br />');
		construct_nav_option($vbphrase['replacement_variable_manager'], 'replacement.php?do=modify', '<br />');
		construct_nav_option($vbphrase['download_upload_styles'], 'template.php?do=files', '<br />');
		construct_nav_option($vbphrase['find_updated_templates'], 'template.php?do=findupdates', '<br />');
		construct_nav_group("$vbphrase[styles] &amp; $vbphrase[templates]", '<hr />');
	}
	// ***
	if (can_administer('canadminlanguages'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['language_manager'], 'language.php?do=modify', '<br />');
		construct_nav_option($vbphrase['phrase_manager'], 'phrase.php?do=modify&phrasetypeid=1', '<br />');
		construct_nav_option($vbphrase['search_in_phrases'], 'phrase.php?do=search', '<br />');
		construct_nav_option($vbphrase['download_upload_languages'], 'language.php?do=files');
		construct_nav_group("$vbphrase[languages] &amp; $vbphrase[phrases]", '<hr />');
	}
	// ***
	if (can_administer('canadminfaq'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['faq_manager'], 'faq.php?null=0', '|');
		construct_nav_option($vbphrase['add_new_faq_item'], 'faq.php?do=add');
		construct_nav_group($vbphrase['faq']);
	}
	if ($printhr == true)
	{
		construct_nav_spacer();
	}

	// *************************************************

	construct_nav_option($vbphrase['announcement_manager'], 'announcement.php?do=modify', '|');
	construct_nav_option($vbphrase['add_new_announcement'], 'announcement.php?do=add');
	construct_nav_group($vbphrase['announcements']);
	// ***
	if (can_administer('canadminforums'))
	{
		construct_nav_option($vbphrase['forum_manager'], 'forum.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_forum'], 'forum.php?do=add', '|');
		construct_nav_option($vbphrase['forum_permissions'], 'forumpermission.php?do=modify', '<br />');
		construct_nav_option($vbphrase['show_all_moderators'], 'moderator.php?do=showlist', '<br />');
		construct_nav_option($vbphrase['view_permissions'], 'resources.php');
		construct_nav_group("$vbphrase[forums] &amp; $vbphrase[moderators]");
	}
	construct_nav_spacer();

	// *************************************************

	$printhr = false;
	if (can_administer('canadmincalendars'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['calendar_manager'], 'admincalendar.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_calendar'], 'admincalendar.php?do=add', '|');
		construct_nav_option($vbphrase['calendar_permissions'], 'calendarpermission.php?do=modify', '<br />');
		construct_nav_option($vbphrase['holiday_manager'], 'admincalendar.php?do=modifyholiday', '<br />');
		construct_nav_group("$vbphrase[calendars] &amp; $vbphrase[moderators]");
	}
	if ($printhr == true)
	{
		construct_nav_spacer();
	}

	// *************************************************

	if (can_administer('canadminthreads'))
	{
		construct_nav_option($vbphrase['prune'], 'thread.php?do=prune', '|');
		construct_nav_option($vbphrase['move'], 'thread.php?do=move', '|');
		construct_nav_option($vbphrase['unsubscribe'], 'thread.php?do=unsubscribe', '<br />');
		construct_nav_option($vbphrase['strip_poll'], 'thread.php?do=killpoll', '|');
		construct_nav_option($vbphrase['who_voted'], 'thread.php?do=votes');
		construct_nav_group("$vbphrase[threads] &amp; $vbphrase[posts]");
	}
	// ***
	construct_nav_option($vbphrase['moderate_threads'], "../$modcpdir/moderate.php?do=posts", '<br />');
	construct_nav_option($vbphrase['moderate_posts'], "../$modcpdir/moderate.php?do=posts#posts", '<br />');
	construct_nav_option($vbphrase['moderate_attachments'], "../$modcpdir/moderate.php?do=attachments", '<br />');
	construct_nav_option($vbphrase['moderate_events'], "../$modcpdir/moderate.php?do=events");
	construct_nav_group($vbphrase['moderation'], '<hr />', "$df");
	// ***
	if (can_administer('canadminthreads'))
	{
		construct_nav_option($vbphrase['search'], 'attachment.php?do=intro', '|');
		construct_nav_option($vbphrase['moderate_attachments'], "../$modcpdir/moderate.php?do=attachments", '|');
		construct_nav_option($vbphrase['attachment_statistics'], 'attachment.php?do=stats', '<br />');
		construct_nav_option($vbphrase['attachment_storage_type'], 'attachment.php?do=storage', '<br />');
		construct_nav_option($vbphrase['extensions_and_sizes'], 'attachment.php?do=types');
		construct_nav_group($vbphrase['attachments']);
	}
	construct_nav_spacer();

	// *************************************************

	$printhr = false;
	if (can_administer('canadminusers'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['add_new_user'], 'user.php?do=add', '|');
		construct_nav_option($vbphrase['search_for_users'], 'user.php?do=modify', '|');
		construct_nav_option($vbphrase['merge_users'], 'usertools.php?do=merge', '|');
		construct_nav_option($vbphrase['ban_user'], "../$modcpdir/banning.php?do=banuser", '<br />');
		construct_nav_option($vbphrase['prune_users'], 'user.php?do=prune', '|');
		construct_nav_option($vbphrase['private_message_statistics'], 'usertools.php?do=pmstats', '<br />');
		construct_nav_option($vbphrase['referrals'], 'usertools.php?do=referrers', '|');
		construct_nav_option($vbphrase['search_ip_addresses'], 'usertools.php?do=doips', '<br />');
		construct_nav_option($vbphrase['view_banned_users'], "../$modcpdir/banning.php?do=modify", '<br />');
		construct_nav_option($vbphrase['send_email_to_users'], 'email.php?do=start', '|');
		construct_nav_option($vbphrase['generate_mailing_list'], 'email.php?do=genlist', '<br />');
		construct_nav_option($vbphrase['access_masks'], 'accessmask.php?do=modify', '<br />');
		construct_nav_group($vbphrase['users']);
	}
	// ***
	if (can_administer('canadminpermissions'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['usergroup_manager'], 'usergroup.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_usergroup'], 'usergroup.php?do=add', '|');
		construct_nav_option($vbphrase['join_requests'], "usergroup.php?do=viewjoinrequests", '<br />');
		construct_nav_option($vbphrase['promotions'], "usergroup.php?do=modifypromotion", '<br />');
		construct_nav_option($vbphrase['forum_permissions'], 'forumpermission.php?do=modify', '<br />');
		construct_nav_option($vbphrase['administrator_permissions'], 'adminpermissions.php?do=modify');
		construct_nav_group($vbphrase['usergroups']);
	}
	// ***
	if (can_administer('canadminusers'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['user_title_manager'], 'usertitle.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_user_title'], 'usertitle.php?do=add');
		construct_nav_group($vbphrase['user_titles']);
		// ***
		construct_nav_option($vbphrase['user_rank_manager'], 'ranks.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_user_rank'], 'ranks.php?do=add');
		construct_nav_group($vbphrase['user_ranks']);
		// ***
		construct_nav_option($vbphrase['user_reputation_manager'], 'adminreputation.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_user_reputation'], 'adminreputation.php?do=add');
		construct_nav_option($vbphrase['view_reputation_comments'], 'adminreputation.php?do=list');
		construct_nav_group($vbphrase['user_reputations']);
		// ***
		construct_nav_option($vbphrase['user_profile_field_manager'], 'profilefield.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_user_profile_field'], 'profilefield.php?do=add');
		construct_nav_group($vbphrase['user_profile_fields'], '<hr />');
	}
	// ***
	if (can_administer('canadminusers'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['subscription_manager'], 'subscriptions.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_subscription'], 'subscriptions.php?do=add');
		construct_nav_option($vbphrase['test_communication'], 'diagnostic.php?do=payments');
		construct_nav_group($vbphrase['subscriptions'], '<hr />');
	}
	if ($printhr == true)
	{
		construct_nav_spacer();
	}

	// *************************************************

	$printhr = false;
	if (can_administer('canadminimages'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['avatar_manager'], 'image.php?do=modify&table=avatar', '|');
		construct_nav_option($vbphrase['add_new_avatars'], 'image.php?do=add&table=avatar', '<br />');
		construct_nav_option($vbphrase['upload_avatar'], 'image.php?do=upload&table=avatar', '|');
		construct_nav_option($vbphrase['avatar_storage_type'], 'avatar.php?do=storage', '<br />');
		construct_nav_group($vbphrase['avatars']);
		// ***
		construct_nav_option($vbphrase['post_icon_manager'], 'image.php?do=modify&table=icon', '|');
		construct_nav_option($vbphrase['add_new_post_icon'], 'image.php?do=add&table=icon', '|');
		construct_nav_option($vbphrase['upload_post_icon'], 'image.php?do=upload&table=icon', '<br />');
		construct_nav_group($vbphrase['post_icons']);
		// ***
		construct_nav_option($vbphrase['smilie_manager'], 'image.php?do=modify&table=smilie', '|');
		construct_nav_option($vbphrase['add_new_smilie'], 'image.php?do=add&table=smilie', '|');
		construct_nav_option($vbphrase['upload_smilie'], 'image.php?do=upload&table=smilie', '<br />');
		construct_nav_group($vbphrase['smilies']);
	}
	// ***
	if (can_administer('canadminbbcodes'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['bb_code_manager'], 'bbcode.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_bb_code'], 'bbcode.php?do=add');
		construct_nav_group($vbphrase['custom_bb_codes'], '<hr />');
	}
	if ($printhr == true)
	{
		construct_nav_spacer();
	}

	// *************************************************

	if (can_administer('canadmincron'))
	{
		construct_nav_option($vbphrase['scheduled_task_manager'], 'cronadmin.php?do=modify', '|');
		construct_nav_option($vbphrase['add_new_scheduled_task'], 'cronadmin.php?do=edit', '<br />' );
		construct_nav_option($vbphrase['scheduled_task_log'], 'cronlog.php?do=choose', '<br />');
		construct_nav_group($vbphrase['scheduled_tasks']);
	}
	// ***
	construct_nav_option($vbphrase['statistics'], 'stats.php?do=index', '<br />');
	construct_nav_option($vbphrase['control_panel_log'], 'adminlog.php?do=choose', '|');
	construct_nav_option($vbphrase['moderator_log'], 'modlog.php?do=choose', '<br />');
	construct_nav_option($vbphrase['scheduled_task_log'], 'cronlog.php?do=choose', '<br />');
	if (!empty($vboptions['errorlogdatabase']) OR !empty($vboptions['errorlogsecurity']))
	{
		construct_nav_option($vbphrase['log_manager'], 'adminlog.php?do=logfiles', '<br />');
	}
	construct_nav_group("$vbphrase[statistics] &amp; $vbphrase[logs]", '<hr />');
	construct_nav_spacer();

	// *************************************************

	$printhr = false;
	if (can_administer('canadminmaintain'))
	{
		$printhr = true;
		construct_nav_option($vbphrase['database_backup'], 'backup.php?do=choose', '<br />');
		construct_nav_option($vbphrase['repair_optimize_tables'], 'repair.php?do=list', '<br />');
		construct_nav_option($vbphrase['update_counters'], 'misc.php?do=chooser', '|');
		construct_nav_option($vbphrase['diagnostics'], 'diagnostic.php?do=list', '<br />');
		if (file_exists('./impex/index.php'))
		{
			construct_nav_option("$vbphrase[import] / $vbphrase[export]", '../impex/index.php', '<br />');
		}
		construct_nav_option($vbphrase['execute_sql_query'], 'queries.php?do=modify');
		if (!is_demo_mode())
		{
			construct_nav_option($vbphrase['view_php_info'], 'index.php?do=phpinfo');
		}
		construct_nav_group("$vbphrase[import] &amp; $vbphrase[maintenance]");
	}
	if ($printhr == true)
	{
		construct_nav_spacer();
	}

	print_nav_panel();

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
		<td style="text-align:<?php echo $stylevar['left']; ?>"><a href="http://www.vbulletin.com/" target="_blank"><b><?php echo $vbphrase['admin_control_panel']; ?></b> (vBulletin <?php echo $versionnumber.print_form_middle("VBS4AAFB47"); ?>)<?php echo iif(is_demo_mode(), ' <b>DEMO MODE</b>'); ?></a></td>
		<td><a href="http://www.vbulletin.com/members/" target="_blank"><script type="text/javascript"> document.write(construct_phrase('<?php echo $vbphrase['latest_version_available_x']; ?>', vb_version));</script></a></td>
		<td style="white-space:nowrap; text-align:<?php echo $stylevar['right']; ?>; font-weight:bold">
			<a href="../<?php echo $vboptions['forumhome']; ?>.php?<?php echo $session['sessionurl']; ?>" target="_blank"><?php echo $vbphrase['forum_home_page']; ?></a>
			|
			<a href="index.php?<?php echo $session['sessionurl']; ?>do=cplogout" onclick="return confirm('<?php echo $vbphrase['sure_you_want_to_log_out_of_cp']; ?>');"  target="_top"><?php echo $vbphrase['log_out']; ?></a>
		</td>
	</tr>
	</table>
	<?php

	define('NO_CP_COPYRIGHT', true);
	unset($DEVDEBUG);
	print_cp_footer();

}

// ################################ SHOW PHP INFO #############################

if ($_REQUEST['do'] == 'phpinfo' AND !is_demo_mode())
{
	phpinfo();
	exit;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15900 $
|| ####################################################################
\*======================================================================*/
?>