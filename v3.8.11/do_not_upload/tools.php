<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

define('VERSION', '3.8.11');
define('THIS_SCRIPT', 'tools.php');
define('VB_AREA', 'tools');

if (strlen('b54d2f30c39510935e812b415f989a5d') == 32)
{
	/**
	* @ignore
	*/
	define('CUSTOMER_NUMBER', 'b54d2f30c39510935e812b415f989a5d');
}
else
{
	/**
	* @ignore
	*/
	define('CUSTOMER_NUMBER', md5(strtoupper('b54d2f30c39510935e812b415f989a5d')));
}

if (file_exists('./../includes/init.php'))
{ // need to go up a single directory, we must be in includes / admincp / modcp / install
	chdir('./../');
}
else
{
	die('Please place this file within the admincp / install folder');
}

require_once('./install/init.php');
require_once(DIR . '/includes/adminfunctions.php');

$specialtemplates = array();
$datastore_class = (!empty($vbulletin->config['Datastore']['class'])) ? $vbulletin->config['Datastore']['class'] : 'vB_Datastore';

if ($datastore_class != 'vB_Datastore')
{
	require_once(DIR . '/includes/class_datastore.php');
}
$vbulletin->datastore = new $datastore_class($vbulletin, $db);
$vbulletin->datastore->fetch($specialtemplates);

$type = $vbulletin->input->clean_gpc('r', 'type', TYPE_STR);
$customerid = $vbulletin->input->clean_gpc('p', 'customerid', TYPE_STR);
$bbcustomerid = $vbulletin->input->clean_gpc('c', 'bbcustomerid', TYPE_STR);

// #############################################################################
if ($_POST['do'] == 'login')
{
	if (md5(strtoupper($vbulletin->GPC['customerid'])) == CUSTOMER_NUMBER)
	{
		setcookie('bbcustomerid', md5(strtoupper($vbulletin->GPC['customerid'])), 0, '/', '');
		$vbulletin->GPC['bbcustomerid'] = CUSTOMER_NUMBER;
		$_REQUEST['do'] = '';
	}
}

// #############################################################################
if ($vbulletin->GPC['bbcustomerid'] !== CUSTOMER_NUMBER)
{
	global $stylevar;

	// set the style folder
	if (empty($vbulletin->options['cpstylefolder']))
	{
		$vbulletin->options['cpstylefolder'] = 'vBulletin_3_Silver';
	}
	// set the forumhome script
	if (empty($vbulletin->options['forumhome']))
	{
		$vbulletin->options['forumhome'] = 'index';
	}
	// set the version
	$vbulletin->options['templateversion'] = VERSION;

	define('NO_PAGE_TITLE', true);
	print_cp_header('Tools');

	?>
	<form action="<?php echo THIS_SCRIPT; ?>?do=login" method="post">
	<input type="hidden" name="do" value="login" />
	<p>&nbsp;</p><p>&nbsp;</p>
	<table class="tborder" cellpadding="0" cellspacing="0" border="0" width="450" align="center"><tr><td>

		<!-- header -->
		<div class="tcat" style="padding:4px; text-align:center"><b>Enter Customer Number</b></div>
		<!-- /header -->

		<!-- logo and version -->
		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody">
		<tr valign="bottom">
			<td><img src="../cpstyles/<?php echo $vbulletin->options['cpstylefolder']; ?>/cp_logo.gif" alt="" border="0" /></td>
			<td>
				<b><a href="../<?php echo $vbulletin->options['forumhome']; ?>.php"><?php echo $vbulletin->options['bbtitle']; ?></a></b><br />
				<?php echo 'vBulletin ' . $vbulletin->options['templateversion'] . ' Tools'; ?><br />
				&nbsp;
			</td>
		</tr>
		</table>
		<!-- /logo and version -->

		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="logincontrols">
		<col width="50%" style="text-align:right; white-space:nowrap"></col>
		<col></col>
		<col width="50%"></col>
		<!-- login fields -->
		<tr valign="top">
			<td>&nbsp;<br />Customer Number<br />&nbsp;</td>
			<td class="smallfont"><input type="text" style="padding-left:5px; font-weight:bold; width:250px" name="customerid" value="" tabindex="1" /><br />This is the number with which you log in to the vBulletin.com Members' Area</td>
			<td>&nbsp;</td>
		</tr>
		<!-- /login fields -->
		<!-- submit row -->
		<tr>
			<td colspan="3" align="center">
				<input type="submit" class="button" value="Enter Tools System" accesskey="s" tabindex="3" />
			</td>
		</tr>
		<!-- /submit row -->
		</table>
	</td></tr></table>
	</form>
	<?php

	unset($debug, $GLOBALS['DEVDEBUG']);
	print_cp_footer();
}

#####################################
# phrases for import systems
#####################################
$vbphrase['importing_language'] = 'Importing Language';
$vbphrase['importing_style'] = 'Importing Style';
$vbphrase['importing_admin_help'] = 'Importing Admin Help';
$vbphrase['importing_settings'] = 'Importing Setting';
$vbphrase['please_wait'] = 'Please Wait';
$vbphrase['language'] = 'Language';
$vbphrase['master_language'] = 'Master Language';
$vbphrase['admin_help'] = 'Admin Help';
$vbphrase['style'] = 'Style';
$vbphrase['styles'] = 'Styles';
$vbphrase['settings'] = 'Settings';
$vbphrase['master_style'] = 'MASTER STYLE';
$vbphrase['templates'] = 'Templates';
$vbphrase['css'] = 'CSS';
$vbphrase['stylevars'] = 'Stylevars';
$vbphrase['replacement_variables'] = 'Replacement Variables';
$vbphrase['controls'] = 'Controls';
$vbphrase['rebuild_style_information'] = 'Rebuild Style Information';
$vbphrase['updating_style_information_for_each_style'] = 'Updating style information for each style';
$vbphrase['updating_styles_with_no_parents'] = 'Updating style sets with no parent information';
$vbphrase['updated_x_styles'] = 'Updated %1$s Styles';
$vbphrase['no_styles_needed_updating'] = 'No Styles Needed Updating';
$vbphrase['processing_complete_proceed'] = 'Processing Complete - Proceed';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>vBulletin Tools</title>
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
	<link rel="stylesheet" href="../cpstyles/vBulletin_3_Silver/controlpanel.css" />
	<script type="text/javascript">var SESSIONHASH = "";</script>
	<script type="text/javascript" src="../clientscript/vbulletin_global.js"></script>


</head>
<body style="margin:0px">
<!-- END CONTROL PANEL HEADER -->
<?php

if (empty($_REQUEST['do']))
{

	// Get versions of .xml files for header diagnostics
	if ($fp = @fopen('./install/vbulletin-style.xml', 'rb'))
	{
		$data = fread($fp, 256);

		if (preg_match('#vbversion="(.*?)"#', $data, $matches))
		{
			$style_xml = $matches[1];
		}
		else
		{
			$style_xml = 'Unknown';
		}
		fclose($fp);
	}
	else
	{
		$style_xml = 'N/A';
	}

	if ($fp = @fopen('./install/vbulletin-language.xml', 'rb'))
	{
		$data = fread($fp, 256);

		if (preg_match('#vbversion="(.*?)"#', $data, $matches))
		{
			$language_xml = $matches[1];
		}
		else
		{
			$language_xml = 'Unknown';
		}
		fclose($fp);
	}
	else
	{
		$language_xml = 'N/A';
	}

	if ($fp = @fopen('./install/vbulletin-settings.xml', 'rb'))
	{
		$data = fread($fp, 300);

		if (preg_match('#<defaultvalue>(.*?)</defaultvalue>#', $data, $matches))
		{
			$settings_xml = $matches[1];
		}
		else
		{
			$settings_xml = 'Unknown';
		}
		fclose($fp);
	}
	else
	{
		$settings_xml = 'N/A';
	}

	print_form_header();

	echo "<h2>vBulletin Tools</h2>";

	print_table_header('Import XML Files');
	print_column_style_code(array('width:30%'));
	print_label_row(construct_link_code('Style', THIS_SCRIPT . '?do=xml&amp;type=style'), "This will take the latest style from ./install/vbulletin-style.xml<dfn>Version: <b>$style_xml</b></dfn>");
	print_label_row(construct_link_code('Settings', THIS_SCRIPT . '?do=xml&amp;type=settings'), "This will take the latest settings from ./install/vbulletin-settings.xml<dfn>Version: <b>$settings_xml</b></dfn>");
	print_label_row(construct_link_code('Language', THIS_SCRIPT . '?do=xml&amp;type=language'), "This will take the latest language from ./install/vbulletin-language.xml<dfn>Version: <b>$language_xml</b></dfn>");
	print_label_row(construct_link_code('Admin Help', THIS_SCRIPT . '?do=xml&amp;type=adminhelp'), 'This will take the latest admin help from ./install/vbulletin-adminhelp.xml');
	print_table_break();

	print_table_header('Datastore Cache');
	print_column_style_code(array('width:30%'));
	print_label_row(construct_link_code('Usergroup / Forum Cache', THIS_SCRIPT . '?do=cache&amp;type=forum'), 'Update the forum and usergroup cache');
	print_label_row(construct_link_code('Options Cache', THIS_SCRIPT . '?do=cache&amp;type=options'), 'Update the options cache from the setting table');
	print_label_row(construct_link_code('Bitfield Cache', THIS_SCRIPT . '?do=bitfields'), 'Update the bitfields cache from the xml/bitfields_<em>???</em>.xml files');
	print_table_break();

	print_table_header('Cookies');
	print_column_style_code(array('width:30%'));
	$vbulletin->options['cookiedomain'] = iif($vbulletin->options['cookiedomain'] == '', ' ( blank ) ', '<b>' . htmlspecialchars_uni($vbulletin->options['cookiedomain']) . '</b>');
	$vbulletin->options['cookiepath'] = iif($vbulletin->options['cookiepath'] == '', ' ( blank ) ', '<b>' . htmlspecialchars_uni($vbulletin->options['cookiepath']) . '</b>');
	print_label_row('Cookie Prefix', '<b>' . htmlspecialchars_uni(COOKIE_PREFIX) . '</b> (<em>set in includes/config.php</em>)');
	print_label_row(construct_link_code('Reset Cookie Domain', THIS_SCRIPT . '?do=cookie&amp;type=domain'), 'Reset the cookie domain to be blank<dfn>Currently: ' . $vbulletin->options['cookiedomain'] . '</dfn>');
	print_label_row(construct_link_code('Reset Cookie Path', THIS_SCRIPT . '?do=cookie&amp;type=path'), 'Reset the cookie path to be <b>/</b><dfn>Currently: ' . $vbulletin->options['cookiepath'] . '</dfn>');
	print_table_break();

	print_table_header('MySQL');
	print_column_style_code(array('width:30%'));
	print_label_row(construct_link_code('Run Query', THIS_SCRIPT . '?do=mysql&amp;type=query'), 'This allows you to run alter and update queries on the database');
	print_label_row(construct_link_code('Repair Tables', THIS_SCRIPT . '?do=mysql&amp;type=repair'), 'You can select tables that need repaired here');
	print_label_row(construct_link_code('Reset Admin Access', THIS_SCRIPT . '?do=user&amp;type=access'), 'Reset admin access for a user');
	print_table_break();

	$randnumb = vbrand(0, 100000000);
	print_table_header('Other Tools');
	print_column_style_code(array('width:30%'));
	print_label_row(construct_link_code($vbulletin->options['bbactive'] ? 'Turn Off Forum' : 'Turn On Forum', THIS_SCRIPT . '?do=bbactive'), 'Your forum is <b>' . ($vbulletin->options['bbactive'] ? 'On' : 'Off') . '</b>');
	$childcount = $db->query_first("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "forum
		WHERE childlist = ''
	");
	if ($childcount['count'])
	{
		print_label_row(construct_link_code('Rebuild', THIS_SCRIPT . '?do=childlist'), 'You have forum with empty childlists, which is not good.');
	}
	print_label_row(construct_link_code('Default Language', THIS_SCRIPT . '?do=language'), 'Reset board default language.');
	print_table_break();

	print_table_header('Time');
	print_column_style_code(array('width:30%'));
	print_label_row('System Time', $systemdate = date('r T'));
	print_label_row('Your Time', $userdate = vbdate('r T'));
	print_table_footer();
}
else if ($_REQUEST['do'] == 'xml')
{
	switch ($vbulletin->GPC['type'])
	{
		case 'style':
			require_once('./includes/adminfunctions_template.php');

			if (!($xml = file_read('./install/vbulletin-style.xml')))
			{
				echo '<p>Uh oh, ./install/vbulletin-style.xml doesn\'t appear to exist! Upload it and refresh the page.</p>';
				break;
			}

			echo '<p>Importing vbulletin-style.xml</p>';

			xml_import_style($xml);

			// define those phrases that are used for the import
			$vbphrase['style'] = 'Style';
			$vbphrase['please_wait'] = 'Please Wait';

			build_all_styles(0, 1);
		break;
		case 'settings':
			require_once('./includes/adminfunctions_options.php');

			if (!($xml = file_read('./install/vbulletin-settings.xml')))
			{
				echo '<p>Uh oh, ./install/vbulletin-settings.xml doesn\'t appear to exist! Upload it and refresh the page.</p>';
				print_cp_footer();
			}

			echo '<p>Importing vbulletin-settings.xml';
			xml_import_settings($xml);
			echo '<br /><span class="smallfont"><b>Okay</b></span></p>';
		break;
		case 'language':
			require_once('./includes/adminfunctions_language.php');

			if (!($xml = file_read('./install/vbulletin-language.xml')))
			{
				echo '<p>Uh oh, ./install/vbulletin-language.xml doesn\'t appear to exist! Upload it and refresh the page.</p>';
				print_cp_footer();
			}

			echo '<p>Importing vbulletin-language.xml';
			xml_import_language($xml);
			build_language();
			echo '<br /><span class="smallfont"><b>Okay</b></span></p>';
		break;
		case 'adminhelp':
			require_once('./includes/adminfunctions_help.php');

			if (!($xml = file_read('./install/vbulletin-adminhelp.xml')))
			{
				echo '<p>Uh oh, ./install/vbulletin-adminhelp.xml doesn\'t appear to exist! Upload it and refresh the page.</p>';
				print_cp_footer();
			}

			echo '<p>Importing vbulletin-adminhelp.xml';
			xml_import_help_topics($xml);
			echo "<br /><span class=\"smallfont\"><b>Okay</b></span></p>";
		break;
	}
	define('SCRIPT_REDIRECT', true);
}
else if ($_REQUEST['do'] == 'cache')
{
	switch ($vbulletin->GPC['type'])
	{
		case 'forum':
			build_forum_permissions();
			define('SCRIPT_REDIRECT', true);
		break;
		case 'options':
			build_options();
			define('SCRIPT_REDIRECT', true);
		break;
	}
}
else if ($_REQUEST['do'] == 'cookie')
{
	switch ($vbulletin->GPC['type'])
	{
		case 'domain':
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "setting
				SET value = ''
				WHERE varname = 'cookiedomain'
			");
			build_options();
			define('SCRIPT_REDIRECT', true);
		break;
		case 'path':
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "setting
				SET value = '/'
				WHERE varname = 'cookiepath'
			");
			build_options();
			define('SCRIPT_REDIRECT', true);
		break;
	}
}
else if ($_REQUEST['do'] == 'bitfields')
{
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);
	build_forum_permissions();
	define('SCRIPT_REDIRECT', true);
}
else if ($_REQUEST['do'] == 'mysql')
{
	$vbulletin->input->clean_array_gpc('p', array('query' => TYPE_STR, 'tables' => TYPE_ARRAY));

	switch ($vbulletin->GPC['type'])
	{
		case 'query':
			if (empty($vbulletin->GPC['query']) OR !preg_match('#^(Alter|Update)#si', $vbulletin->GPC['query']))
			{
				print_form_header('tools', 'mysql');
				construct_hidden_code('type', 'query');
				print_table_header('Please paste alter / update query below');
				print_textarea_row('Query to run', 'query','', 6, 60, 0, 0);
				print_submit_row('Run', '');
			}
			else
			{
				$db->query_write($vbulletin->GPC['query']);
				define('SCRIPT_REDIRECT', true);
			}
			break;
		case 'repair':
			if (empty($vbulletin->GPC['tables']))
			{
				print_form_header('tools', 'mysql');
				construct_hidden_code('type', 'repair');
				print_table_header('Please select tables to repair');
				print_label_row('Table', "<input type=\"checkbox\" name=\"allbox\" title=\"Check All\" onclick=\"js_check_all(this.form);\" />Check All", 'thead');
				$result = $db->query_write("SHOW TABLE STATUS");
				while ($currow = $db->fetch_array($result, DBARRAY_NUM))
				{
					if (!in_array(strtolower($currow[1]), array('heap', 'memory')))
					{
						print_checkbox_row($currow[0], "tables[$currow[0]]", 0);
					}
				}
				print_submit_row('Repair', '');
			}
			else
			{
				foreach($vbulletin->GPC['tables'] AS $key => $val)
				{
					if ($val == 1)
					{
						echo "Repairing $key<br />\n";
						flush();
						$db->query_write("REPAIR TABLE $key");
						echo "Repair Complete<br />\n";
					}
				}
				echo "Overall Repair complete<br />";
				define('SCRIPT_REDIRECT', true);
			}
		break;
	}
}
else if ($_REQUEST['do'] == 'user')
{
	$vbulletin->input->clean_array_gpc('p', array('user' => TYPE_STR));

	switch ($vbulletin->GPC['type'])
	{
		case 'access':
		if (empty($vbulletin->GPC['user']))
		{
			print_form_header('tools', 'user');
			construct_hidden_code('type', 'access');
			print_table_header('Enter username to restore access to');
			print_input_row('User Name', 'user', '');
			print_submit_row('Submit', '');
		}
		else
		{
			$userid = $db->query_first("SELECT userid, usergroupid FROM " . TABLE_PREFIX . "user WHERE username = '" . $db->escape_string(htmlspecialchars_uni($vbulletin->GPC['user'])) . "'");
			if (empty($userid['userid']))
			{
				echo '<p align="center">Invalid username</p>';
			}
			else
			{
				// lets check that usergroupid 6 is still admin
				$ugroup = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "usergroup WHERE usergroupid = 6 AND (adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] . ")");
				if (empty($ugroup['usergroupid']))
				{ // lets give them admin permissions again
					$db->query_write("UPDATE " . TABLE_PREFIX . "usergroup SET adminpermissions = 3 WHERE usergroupid = 6");
					build_forum_permissions();
				}
				/*insert query*/
				$db->query_write("REPLACE INTO " . TABLE_PREFIX . "administrator
					(userid, adminpermissions)
				VALUES
					($userid[userid], " . (array_sum($vbulletin->bf_ugp_adminpermissions) - 3) . ")
				");
				$db->query_write("UPDATE " . TABLE_PREFIX . "user SET usergroupid = 6 WHERE userid = $userid[userid]");
				define('SCRIPT_REDIRECT', true);
			}
		}
		break;
	}
}
if ($_REQUEST['do'] == 'childlist')
{
	function construct_child_list($forumid)
	{
		global $db;

		if ($forumid == -1)
		{
			return '-1';
		}

		$childlist = $forumid;

		$children = $db->query_read("SELECT forumid FROM " . TABLE_PREFIX . "forum WHERE parentid = " . intval($forumid));
		while ($child = $db->fetch_array($children))
		{
			$childlist .= ',' . $child['forumid'];
		}

		$childlist .= ',-1';

		return $childlist;
	}

	$forums = $db->query_read("SELECT forumid FROM " . TABLE_PREFIX . "forum WHERE childlist = ''");

	$count = 0;
	while ($forum = $db->fetch_array($forums))
	{
		$childlist = construct_child_list($forum['forumid']);
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "forum
			SET childlist = '$childlist'
			WHERE forumid = $forum[forumid]
		");
		$count++;
	}
	build_forum_permissions();
	echo "<p align=\"center\">Updated $count forums</p>";
	define('SCRIPT_REDIRECT', true);
}
else if ($_REQUEST['do'] == 'bbactive')
{
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "setting
		SET value = " . ($vbulletin->options['bbactive'] ? 0 : 1) . "
		WHERE varname = 'bbactive'
	");
	build_options();
	define('SCRIPT_REDIRECT', true);
}
else if ($_REQUEST['do'] == 'language')
{
	$vbulletin->input->clean_array_gpc('p', array('languageid' => TYPE_UINT));

	require_once(DIR . '/includes/adminfunctions_language.php');

	$languages = $db->query_read('SELECT * FROM ' . TABLE_PREFIX . 'language');
	if ($db->num_rows($languages) == 0)
	{
		// this is just taken from install.php
		$db->query_write("INSERT INTO " . TABLE_PREFIX . "language (title, languagecode, charset, decimalsep, thousandsep) VALUES ('English (US)', 'en', 'ISO-8859-1', '.', ',')");
		$_languageid = $db->insert_id();

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "setting
			SET value = " . $_languageid . "
			WHERE varname = 'languageid'
		");

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET languageid = 0
		");
		build_options();
		build_language($_languageid);
		build_language_datastore();
		define('SCRIPT_REDIRECT', true);
	}
	else
	{
		$sellanguages = array();
		while ($language = $db->fetch_array($languages))
		{
			$sellanguages[$language['languageid']] = $language['title'];
		}

		$languageids = implode(',', array_keys($sellanguages));

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET languageid = 0
			WHERE languageid NOT IN ($languageids)
		");

		if (empty($vbulletin->GPC['languageid']))
		{
				print_form_header('tools', 'language');
				print_table_header('Select the new default language');
				print_select_row('Language', 'languageid', $sellanguages, $vbulletin->options['languageid']);
				print_submit_row('Submit', '');
		}
		else
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "setting
				SET value = " . $vbulletin->GPC['languageid'] . "
				WHERE varname = 'languageid'
			");
			build_options();
			build_language($vbulletin->GPC['languageid']);
			build_language_datastore();
			define('SCRIPT_REDIRECT', true);
		}
	}
}

if (defined('SCRIPT_REDIRECT'))
{
	echo '<p align="center" class="smallfont"><a href="tools.php" onclick="javascript:clearTimeout(timerID);">' . $vbphrase['processing_complete_proceed'] . '</a></p>';
	echo "\n<script type=\"text/javascript\">\n";
	echo "myvar = \"\"; timeout = " . (10) . ";
	function exec_refresh()
	{
		window.status=\"" . $vbphrase['redirecting'] . "\"+myvar; myvar = myvar + \" .\";
		timerID = setTimeout(\"exec_refresh();\", 100);
		if (timeout > 0)
		{ timeout -= 1; }
		else { clearTimeout(timerID); window.status=\"\"; window.location=\"tools.php\"; }
	}
	exec_refresh();";
	echo "\n</script>\n";
}

?>
<!-- START CONTROL PANEL FOOTER -->
<p align="center" class="smallfont"><a href="https://www.vbulletin.com/" target="_blank" class="copyright">vBulletin <?php echo VERSION; ?>, Copyright &copy;2000-<?php echo date('Y'); ?>, vBulletin Solutions Inc.</a></p>
</body>
</html>
<?php

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
