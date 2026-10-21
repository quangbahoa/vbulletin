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
define('CVS_REVISION', '$RCSfile$ - $Revision: 12669 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('timezone', 'cpoption', 'user', 'cpuser');
$specialtemplates = array('banemail', 'wol_spiders');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// intercept direct call to do=options with $varname specified instead of $dogroup
if ($_REQUEST['do'] == 'options' && !empty($_REQUEST['varname']))
{
	globalize($_REQUEST, array('varname' => STR));

	if ($varname == '[all]')
	{
		// go ahead and show all settings
		$_REQUEST['dogroup'] = '[all]';
	}
	else if ($group = $DB_site->query_first("SELECT varname, grouptitle FROM " . TABLE_PREFIX . "setting WHERE varname = '" . addslashes($varname) . "'"))
	{
		// redirect to show the correct group and use and anchor to jump to the correct variable
		exec_header_redirect("options.php?$session[sessionurl_js]do=options&dogroup=$group[grouptitle]#$group[varname]");
	}
	else
	{
		// could not find a matching group - just carry on as if nothing happened
		$_REQUEST['do'] = 'options';
	}
}

require_once('./includes/adminfunctions_options.php');
require_once('./includes/functions_misc.php');
require_once('./includes/functions_register.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminsettings'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// query settings phrases
$settingphrase = array();
$phrases = $DB_site->query("
	SELECT varname, text
	FROM " . TABLE_PREFIX . "phrase
	WHERE phrasetypeid = " . PHRASETYPEID_SETTING . " AND
		languageid IN(-1, 0, " . LANGUAGEID . ")
	ORDER BY languageid ASC
");
while($phrase = $DB_site->fetch_array($phrases))
{
	$settingphrase["$phrase[varname]"] = $phrase['text'];
}

// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'options';
}

// ###################### Start download XML settings #######################

if ($_REQUEST['do'] == 'download')
{
	$setting = array();
	$settinggroup = array();

	$groups = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "settinggroup WHERE volatile = 1 ORDER BY displayorder");
	while ($group = $DB_site->fetch_array($groups))
	{
		$settinggroup["$group[grouptitle]"] = $group;
	}

	$sets = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "setting WHERE volatile = 1 ORDER BY displayorder");
	while ($set = $DB_site->fetch_array($sets))
	{
		$setting["$set[grouptitle]"][] = $set;
	}

	$xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";
	$xml .= "<settinggroups>\r\n\r\n";

	require_once('./includes/functions_xml.php');
	foreach($settinggroup AS $grouptitle => $group)
	{
		$group = $settinggroup["$grouptitle"];
		$xml .= "<settinggroup name=\"" . htmlspecialchars($group['grouptitle']) . "\" displayorder=\"$group[displayorder]\">\r\n";
		foreach($setting["$grouptitle"] AS $set)
		{
			$xml .= "\t<setting varname=\"$set[varname]\" displayorder=\"$set[displayorder]\"" . iif($set['advanced'], ' advanced="1"') . ">\r\n";
			if ($set['optioncode'] != '')
			{
				if (preg_match('/[\<\>\&\'\"\[\]]/', $set['optioncode']))
				{
					$set['optioncode'] = '<![CDATA[' . xml_escape_cdata($set['optioncode']) . ']]>';
				}
				$xml .= "\t\t<optioncode>$set[optioncode]</optioncode>\r\n";
			}
			if ($set['defaultvalue'] != '')
			{
				if (preg_match('/[\<\>\&\'\"\[\]]/', $set['defaultvalue']))
				{
					$set['defaultvalue'] = '<![CDATA[' . xml_escape_cdata($set['defaultvalue']) . ']]>';
				}
				$xml .= "\t\t<defaultvalue>" . iif($set['varname'] == 'templateversion', $vboptions['templateversion'], $set['defaultvalue']) . "</defaultvalue>\r\n";
			}
			$xml .= "\t</setting>\r\n";
		}
		$xml .= "</settinggroup>\r\n";
	}

	$xml .= "\r\n</settinggroups>";

	require_once('./includes/functions_file.php');
	file_download($xml, 'vbulletin-settings.xml', 'text/xml');

}

// ***********************************************************************

print_cp_header($vbphrase['vbulletin_options']);

// ###################### Start do import settings XML #######################
if ($_POST['do'] == 'doimport')
{
	globalize($_POST, array(
		'serverfile' => STR,
		'settingsfile' => FILE
	));
	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}
	// got an uploaded file?
	if (file_exists($settingsfile['tmp_name']))
	{
		$xml = file_read($settingsfile['tmp_name']);
	}
	// no uploaded file - got a local file?
	else if (file_exists($serverfile))
	{
		$xml = file_read($serverfile);
	}
	// no uploaded file and no local file - ERROR
	else
	{
		print_stop_message('no_file_uploaded_and_no_local_file_found');
	}

	xml_import_settings($xml);

	print_cp_redirect("options.php?$session[sessionurl]", 0);
}

// ###################### Start import settings XML #######################
if ($_REQUEST['do'] == 'import')
{
	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}
	?>
	<script type="text/javascript">
	<!--
	function js_confirm_upload(tform, filefield)
	{
		if (filefield.value == "")
		{
			return confirm("<?php echo construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], '" + tform.serverfile.value + "'); ?>");
		}
		return true;
	}
	//-->
	</script>
	<?php

	print_form_header('options', 'doimport', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.settingsfile);');
	print_table_header($vbphrase['import_settings_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'settingsfile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './install/vbulletin-settings.xml');
	print_submit_row($vbphrase['import'], 0);
}

// ###################### Start kill setting group #######################
if ($_POST['do'] == 'killgroup')
{
	globalize($_POST, array('title' => STR));

	// get some info
	$group = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "settinggroup WHERE grouptitle = '$title'");

	// query settings from this group
	$settings = array();
	$sets = $DB_site->query("SELECT varname FROM " . TABLE_PREFIX . "setting WHERE grouptitle = '$group[grouptitle]'");
	while ($set = $DB_site->fetch_array($sets))
	{
		$settings[] = $set['varname'];
	}

	// build list of phrases to be deleted
	$phrases = array("settinggroup_$group[grouptitle]");
	foreach($settings AS $varname)
	{
		$phrases[] = 'setting_' . $varname . '_title';
		$phrases[] = 'setting_' . $varname . '_desc';
	}

	// delete phrases
	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (-1,0) AND
			phrasetypeid = " . PHRASETYPEID_SETTING . " AND
			varname IN ('" . implode("', '", $phrases) . "')
	");

	// delete settings
	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "setting
		WHERE varname IN ('" . implode("', '", $settings) . "')
	");

	// delete group
	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "settinggroup
		WHERE grouptitle = '$group[grouptitle]'
	");

	build_options();

	define('CP_REDIRECT', 'options.php');
	print_stop_message('deleted_setting_group_successfully');

}

// ###################### Start remove setting group #######################
if ($_REQUEST['do'] == 'removegroup')
{
	globalize($_REQUEST, array('grouptitle' => STR));

	print_delete_confirmation('settinggroup', addslashes($grouptitle), 'options', 'killgroup');
}

// ###################### Start insert setting group #######################
if ($_POST['do'] == 'insertgroup')
{
	globalize($_POST, array('group'));

	// insert setting place-holder
	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "settinggroup
		(grouptitle)
		VALUES
		('" . addslashes($group['grouptitle']) . "')
	");

	// insert associated phrases
	$languageid = iif($group['volatile'], -1, 0);
	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "phrase
		(languageid, phrasetypeid, varname, text)
		VALUES
		($languageid, " . PHRASETYPEID_SETTING . ", 'settinggroup_$group[grouptitle]', '" . addslashes($group['title']) . "')
	");

	// fall through to 'updategroup' for the real work...
	$_POST['do'] = 'updategroup';
}

// ###################### Start update setting group #######################
if ($_POST['do'] == 'updategroup')
{
	globalize($_POST, array('group'));

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "settinggroup SET
		displayorder = " . intval($group['displayorder']) . ",
		volatile = " . intval($group['volatile']) . "
		WHERE grouptitle = '" . addslashes($group['grouptitle']) . "'
	");

	$phrase = $DB_site->query_first("
		SELECT text, languageid
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (-1,0) AND
			phrasetypeid = " . PHRASETYPEID_SETTING . " AND
			varname = 'settinggroup_$group[grouptitle]'
	");

	if ($phrase['text'] != $group['title'])
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "phrase
			SET text='" . addslashes($group['title']) . "'
			WHERE languageid = $phrase[languageid] AND
				varname = 'settinggroup_$group[grouptitle]'
		");
	}

	define('CP_REDIRECT', 'options.php?do=options&amp;dogroup=' . $group['grouptitle']);
	print_stop_message('saved_setting_group_x_successfully', $group['title']);
}

// ###################### Start edit setting group #######################
if ($_REQUEST['do'] == 'editgroup' OR $_REQUEST['do'] == 'addgroup')
{
	globalize($_REQUEST, array('grouptitle' => STR));

	if ($_REQUEST['do'] == 'editgroup')
	{
		$group = $DB_site->query_first("
			SELECT * FROM " . TABLE_PREFIX . "settinggroup
			WHERE grouptitle = '" . addslashes($grouptitle) . "'
		");
		$phrase = $DB_site->query_first("
			SELECT text FROM " . TABLE_PREFIX . "phrase
			WHERE languageid IN (-1,0) AND
				phrasetypeid = " . PHRASETYPEID_SETTING . " AND
				varname = 'settinggroup_$group[grouptitle]'
		");
		$group['title'] = $phrase['text'];
		$pagetitle = construct_phrase($vbphrase['x_y_id_z'], $vbphrase['setting_group'], $group['title'], $group['grouptitle']);
		$formdo = 'updategroup';
	}
	else
	{
		$ordercheck = $DB_site->query_first("
			SELECT displayorder
			FROM " . TABLE_PREFIX . "settinggroup
			ORDER BY displayorder DESC
		");
		$group = array(
			'displayorder' => $ordercheck['displayorder'] + 10,
			'volatile' => iif($debug, 1, 0)
		);
		$pagetitle = $vbphrase['add_new_setting_group'];
		$formdo = 'insertgroup';
	}

	print_form_header('options', $formdo);
	print_table_header($pagetitle);
	if ($_REQUEST['do'] == 'editgroup')
	{
		print_label_row($vbphrase['varname'], "<b>$group[grouptitle]</b>");
		construct_hidden_code('group[grouptitle]', $group['grouptitle']);
	}
	else
	{
		print_input_row($vbphrase['varname'], 'group[grouptitle]', $group['grouptitle']);
	}
	print_input_row($vbphrase['title'], 'group[title]', $group['title']);
	print_input_row($vbphrase['display_order'], 'group[displayorder]', $group['displayorder']);
	if ($debug)
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'group[volatile]', $group['volatile']);
	}
	else
	{
		construct_hidden_code('group[volatile]', $group['volatile']);
	}
	print_submit_row($vbphrase['save']);

}

// ###################### Start kill setting #######################
if ($_POST['do'] == 'killsetting')
{
	globalize($_POST, array('title' => STR));

	// get some info
	$setting = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "setting WHERE varname = '$title'");

	// delete phrases
	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (-1,0) AND
			phrasetypeid = " . PHRASETYPEID_SETTING . " AND
			varname IN ('setting_$setting[varname]_title', 'setting_$setting[varname]_desc')
	");

	// delete setting
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "setting WHERE varname = '$setting[varname]'");
	build_options();

	define('CP_REDIRECT', 'options.php?do=options&amp;dogroup=' . $setting['grouptitle']);
	print_stop_message('deleted_setting_successfully');
}

// ###################### Start remove setting #######################
if ($_REQUEST['do'] == 'removesetting')
{
	globalize($_REQUEST, array('varname' => STR));

	print_delete_confirmation('setting', addslashes($varname), 'options', 'killsetting');
}

// ###################### Start insert setting #######################
if ($_POST['do'] == 'insertsetting')
{
	globalize($_POST, array('setting'));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if ($s = $DB_site->query_first("
		SELECT varname
		FROM " . TABLE_PREFIX . "setting
		WHERE varname = '" . addslashes($setting['varname']) . "'
	"))
	{
		print_stop_message('there_is_already_setting_named_x', $setting['varname']);
	}

	// insert setting place-holder
	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "setting
		(varname, value)
		VALUES
		('" . addslashes($setting['varname']) . "', '" . addslashes($setting['defaultvalue']) . "')
	");

	// insert associated phrases
	$languageid = iif($setting['volatile'], -1, 0);
	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "phrase
		(languageid, phrasetypeid, varname, text)
		VALUES
		($languageid, " . PHRASETYPEID_SETTING . ", 'setting_$setting[varname]_title', '" . addslashes($setting['title']) . "')
	");
	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "phrase
		(languageid, phrasetypeid, varname, text)
		VALUES
		($languageid, " . PHRASETYPEID_SETTING . ", 'setting_$setting[varname]_desc', '" . addslashes($setting['description']) . "')
	");

	// fall through to 'updatesetting' for the real work...
	$_POST['do'] = 'updatesetting';
}

// ###################### Start update setting #######################
if ($_POST['do'] == 'updatesetting')
{
	globalize($_POST, array('setting'));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "setting SET
			grouptitle = '" . addslashes($setting['grouptitle']) . "',
			optioncode = '" . addslashes($setting['optioncode']) . "',
			defaultvalue = '" . addslashes($setting['defaultvalue']) . "',
			displayorder = " . intval($setting['displayorder']) . ",
			volatile = " . intval($setting['volatile']) . "
		WHERE varname = '" . addslashes($setting['varname']) . "'
	");

	$newlang = iif($setting['volatile'], -1, 0);

	$phrases = $DB_site->query("
		SELECT varname, text, languageid
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (-1,0)
			AND phrasetypeid = " . PHRASETYPEID_SETTING . "
			AND varname IN ('setting_$setting[varname]_title', 'setting_$setting[varname]_desc')
	");

	while ($phrase = $DB_site->fetch_array($phrases))
	{
		if ($phrase['varname'] == "setting_$setting[varname]_title")
		{
			if ($phrase['text'] != $setting['title'] OR $newlang != $phrase['languageid'])
			{
				$q = "
					UPDATE " . TABLE_PREFIX . "phrase SET
						languageid = " . iif($setting['volatile'], -1, 0) . ",
						text = '" . addslashes($setting['title']) . "'
					WHERE languageid = $phrase[languageid]
						AND varname = 'setting_$setting[varname]_title'
				";
				$DB_site->query($q);
				//echo "<pre>$q\n<b>Affected rows: " . $DB_site->affected_rows() . "</b></pre>";
			}
		}
		else if ($phrase['varname'] == "setting_$setting[varname]_desc")
		{
			if ($phrase['text'] != $setting['description'] OR $newlang != $phrase['languageid'])
			{
				$q = "
					UPDATE " . TABLE_PREFIX . "phrase SET
						languageid = " . iif($setting['volatile'], -1, 0) . ",
						text = '" . addslashes($setting['description']) . "'
					WHERE languageid = $phrase[languageid]
						AND varname = 'setting_$setting[varname]_desc'
				";
				$DB_site->query($q);
				//echo "<pre>$q\n<b>Affected rows: " . $DB_site->affected_rows() . "</b></pre>";
			}
		}
	}

	build_options();

	require_once('./includes/functions_databuild.php');
	build_events();

	define('CP_REDIRECT', 'options.php?do=options&amp;dogroup=' . $setting['grouptitle']);
	print_stop_message('saved_setting_x_successfully', $setting['title']);
}

// ###################### Start edit / add setting #######################
if ($_REQUEST['do'] == 'editsetting' OR $_REQUEST['do'] == 'addsetting')
{
	globalize($_REQUEST, array(
		'varname' => STR,
		'grouptitle' => STR
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	$settinggroups = array();
	$groups = $DB_site->query("SELECT grouptitle FROM " . TABLE_PREFIX . "settinggroup ORDER BY displayorder");
	while ($group = $DB_site->fetch_array($groups))
	{
		$settinggroups["$group[grouptitle]"] = $settingphrase["settinggroup_$group[grouptitle]"];
	}

	if ($_REQUEST['do'] == 'editsetting')
	{
		$setting = $DB_site->query_first("
			SELECT * FROM " . TABLE_PREFIX . "setting
			WHERE varname = '" . addslashes($varname) . "'
		");
		$phrases = $DB_site->query("
			SELECT varname, text
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid = " . iif($setting['volatile'], -1, 0) . " AND
				phrasetypeid = " . PHRASETYPEID_SETTING . " AND
			varname IN ('setting_$setting[varname]_title', 'setting_$setting[varname]_desc')
		");
		while ($phrase = $DB_site->fetch_array($phrases))
		{
			if ($phrase['varname'] == "setting_$setting[varname]_title")
			{
				$setting['title'] = $phrase['text'];
			}
			else if ($phrase['varname'] == "setting_$setting[varname]_desc")
			{
				$setting['description'] = $phrase['text'];
			}
		}
		$pagetitle = construct_phrase($vbphrase['x_y_id_z'], $vbphrase['setting'], $setting['title'], $setting['varname']);
		$formdo = 'updatesetting';
	}
	else
	{
		$ordercheck = $DB_site->query_first("
			SELECT displayorder FROM " . TABLE_PREFIX . "setting
			WHERE grouptitle='" . addslashes($grouptitle) . "'
			ORDER BY displayorder DESC
		");
		$setting = array(
			'grouptitle' => $grouptitle,
			'displayorder' => $ordercheck['displayorder'] + 10,
			'volatile' => iif($debug, 1, 0)
		);
		$pagetitle = $vbphrase['add_new_setting'];
		$formdo = 'insertsetting';
	}

	print_form_header('options', $formdo);
	print_table_header($pagetitle);
	if ($_REQUEST['do'] == 'editsetting')
	{
		construct_hidden_code('setting[varname]', $setting['varname']);
		print_label_row($vbphrase['varname'], "<b>$setting[varname]</b>");
	}
	else
	{
		print_input_row($vbphrase['varname'], 'setting[varname]', $setting['varname']);
	}
	print_select_row($vbphrase['setting_group'], 'setting[grouptitle]', $settinggroups, $setting['grouptitle']);
	print_input_row($vbphrase['title'], 'setting[title]', $setting['title']);
	print_textarea_row($vbphrase['description'], 'setting[description]', $setting['description'], 4, 50);
	print_textarea_row($vbphrase['option_code'], 'setting[optioncode]', $setting['optioncode'], 4, 50);
	print_textarea_row($vbphrase['default'], 'setting[defaultvalue]', $setting['defaultvalue'], 4, 50);
	print_input_row($vbphrase['display_order'], 'setting[displayorder]', $setting['displayorder']);
	if ($debug)
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'setting[volatile]', $setting['volatile']);
	}
	else
	{
		construct_hidden_code('setting[volatile]', $setting['volatile']);
	}
	print_submit_row($vbphrase['save']);
}

// ###################### Start do options #######################
if ($_POST['do'] == 'dooptions')
{
	globalize($_POST, array(
		'setting',
		'dogroup' => STR,
		'advanced' => INT
	));

	if (is_array($setting))
	{
		$varnames = array();
		foreach(array_keys($setting) AS $varname)
		{
			$varnames[] = $varname;
		}

		$oldsettings = $DB_site->query("
			SELECT value, varname
			FROM " . TABLE_PREFIX . "setting
			WHERE varname IN ('" . implode("', '", $varnames) . "')
			ORDER BY varname
		");
		while ($oldsetting = $DB_site->fetch_array($oldsettings))
		{
			switch($oldsetting['varname'])
			{
				// **************************************************
				case 'memberlistfields':
				case 'defaultregoptions':
				case 'allowedbbcodes':
				case 'subscriptionmethods':
				case 'postelements':
				{
					$bitfield = 0;
					foreach ($setting["$oldsetting[varname]"] AS $bitval)
					{
						$bitfield += $bitval;
					}
					$setting["$oldsetting[varname]"] = $bitfield;
				}
				break;

				// **************************************************
				case 'bbcode_html_colors':
				{
					$setting['bbcode_html_colors'] = serialize($setting['bbcode_html_colors']);
				}
				break;

				// **************************************************
				case 'styleid':
				{
					$DB_site->query("
						UPDATE " . TABLE_PREFIX . "style
						SET userselect = 1
						WHERE styleid = $setting[styleid]
					");
				}
				break;

				// **************************************************
				case 'banemail':
				{
					build_datastore('banemail', $setting['banemail']);
					$setting['banemail'] = '';
				}
				break;

				// **************************************************
				case 'spiderdesc':
				{
					$wolarray = array('spiderdesc' => trim($setting['spiderdesc']));
					$tempspiderdesc = $setting['spiderdesc'];
					$setting['spiderdesc'] = '';
				}
				break;

				// **************************************************
				case 'spiderstrings':
				{
					$wolarray['spiderstrings'] = trim($setting['spiderstrings']);

					$spiderarray = array();
					$spiderstring = preg_replace('#(\r\n|\n)#s', '|', preg_quote(trim($setting['spiderstrings']), '#'));
					$spiderstringarray = explode("\n", $setting['spiderstrings']);
					$spiderdescarray = explode("\n", $tempspiderdesc);
					foreach ($spiderstringarray AS $index => $value)
					{
						$spiderarray[strtolower(trim($value))] = trim($spiderdescarray["$index"]);
					}
					$wolarray['spiderstring'] = $spiderstring;
					$wolarray['spiderarray'] = $spiderarray;

					build_datastore('wol_spiders', serialize($wolarray));
					$setting['spiderstrings'] = '';
				}
				break;

			}

			if ($oldsetting['value'] != $setting["$oldsetting[varname]"])
			{
				switch ($oldsetting['varname'])
				{
					case 'languageid':
					{
						$vboptions['languageid'] = $setting["$oldsetting[varname]"];
						require_once('./includes/adminfunctions_language.php');
						build_language($vboptions['languageid']);
					}
					break;

					case 'cpstylefolder':
					{
						$cssprefs = str_replace(array('..', '/'), '', $setting["$oldsetting[varname]"]); // get rid of harmful characters
						if ($cssprefs != '' AND @file_exists("./cpstyles/$cssprefs/controlpanel.css"))
						{
							$DB_site->query("UPDATE " . TABLE_PREFIX . "administrator SET cssprefs = '" . addslashes($cssprefs) . "' WHERE userid = $bbuserinfo[userid]");
						}
					}
					break;

					case 'storecssasfile':
					{
						if (is_demo_mode())
						{
							continue;
						}
						$vboptions['storecssasfile'] = $setting["$oldsetting[varname]"];
						require_once('./includes/adminfunctions_template.php');
						print_rebuild_style(-1, '', 1, 0, 0, 0);
					}
					break;

					case 'codemaxlines':
					{
						if ($setting['cachemaxage'] > 0)
						{
							$DB_site->query("DELETE FROM " . TABLE_PREFIX . "post_parsed");
						}
					}
					break;
				}

				if (is_demo_mode() AND in_array($oldsetting['varname'], array('storecssasfile', 'attachfile', 'usefileavatar', 'errorlogdatabase', 'errorlogsecurity', 'safeupload', 'tmppath')))
				{
					continue;
				}

				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "setting
					SET value = '" . addslashes(trim($setting["$oldsetting[varname]"])) . "'
					WHERE varname = '" . addslashes($oldsetting['varname']) . "'
				");
			}
		}
		build_options();

		define('CP_REDIRECT', 'options.php?do=options&amp;dogroup=' . $dogroup . '&amp;advanced= ' . $advanced);
		print_stop_message('saved_settings_successfully');
	}
	else
	{
		print_stop_message('nothing_to_do');
	}

}

// ###################### Start modify options #######################
if ($_REQUEST['do'] == 'options')
{
	// Try to determine GD settings
	if (function_exists('gd_info'))
	{
		$gdinfo = gd_info();
	}
	else if (function_exists('phpinfo') AND function_exists('ob_start'))
	{
		if (@ob_start())
		{
			eval('phpinfo();');
			$info = @ob_get_contents();
			@ob_end_clean();

			preg_match('/<b>GD Version<\/b><\/td><td align="left">(.*?)<\/td><\/tr>/si', $info, $hits);
			$gdinfo = array(
				'GD Version' => $hits[1]
			);
		}
	}

	if (empty($gdinfo['GD Version']))
	{
		$gdinfo['GD Version'] = $vbphrase['n_a'];
	}

	require_once('./includes/adminfunctions_language.php');

	globalize($_REQUEST, array(
		'dogroup' => STR,
		'advanced' => INT,
		'expand' => INT
	));

	// display links to settinggroups and create settingscache
	$settingscache = array();
	$options = array('[all]' => '-- ' . $vbphrase['show_all_settings'] . ' --');
	$lastgroup = '';

	$settings = $DB_site->query("
		SELECT setting.*, settinggroup.grouptitle
		FROM " . TABLE_PREFIX . "settinggroup AS settinggroup
		LEFT JOIN " . TABLE_PREFIX . "setting AS setting USING(grouptitle)
		" . iif($debug, '', 'WHERE settinggroup.displayorder <> 0') . "
		ORDER BY settinggroup.displayorder, setting.displayorder
	");

	if (empty($dogroup) AND $expand)
	{
		while ($setting = $DB_site->fetch_array($settings))
		{
			$settingscache["$setting[grouptitle]"]["$setting[varname]"] = $setting;
			if ($setting['grouptitle'] != $lastgroup)
			{
				$grouptitlecache["$setting[grouptitle]"] = $setting['grouptitle'];
				$grouptitle = $settingphrase["settinggroup_$setting[grouptitle]"];
			}
			$options["$grouptitle"]["$setting[varname]"] = $settingphrase["setting_$setting[varname]_title"];
			$lastgroup = $setting['grouptitle'];
		}

		$altmode = 0;
		$linktext = &$vbphrase['collapse_setting_groups'];
	}
	else
	{
		while ($setting = $DB_site->fetch_array($settings))
		{
			$settingscache["$setting[grouptitle]"]["$setting[varname]"] = $setting;
			if ($setting['grouptitle'] != $lastgroup)
			{
				$grouptitlecache["$setting[grouptitle]"] = $setting['grouptitle'];
				$options["$setting[grouptitle]"] = $settingphrase["settinggroup_$setting[grouptitle]"];
			}
			$lastgroup = $setting['grouptitle'];
		}

		$altmode = 1;
		$linktext = &$vbphrase['expand_setting_groups'];
	}
	$DB_site->free_result($settings);

	$optionsmenu = "\n\t<select name=\"" . iif($expand, 'varname', 'dogroup') . "\" class=\"bginput\" tabindex=\"1\" " . iif(empty($dogroup), 'ondblclick="this.form.submit();" size="20"', 'onchange="this.form.submit();"') . " style=\"width:350px\">\n" . construct_select_options($options, iif($dogroup, $dogroup, '[all]')) . "\t</select>\n\t";

	print_form_header('options', 'options', 0, 1, 'groupForm', '90%', '', 1, 'get');

	if (empty($dogroup)) // show the big <select> with no options
	{
		print_table_header($vbphrase['vbulletin_options']);
		print_label_row($vbphrase['settings_to_edit'] . iif($debug,
			'<br /><table><tr><td><fieldset><legend>Developer Options</legend>
			<div style="padding: 2px"><a href="options.php?' . $session['sessionurl'] . 'do=addgroup">' . $vbphrase['add_new_setting_group'] . '</a></div>
			<div style="padding: 2px"><a href="options.php?' . $session['sessionurl'] . 'do=download" target="download">' . $vbphrase['download_settings_xml_file'] . '</a></div>
			<div style="padding: 2px"><a href="options.php?' . $session['sessionurl'] . 'do=import">' . $vbphrase['import_settings_xml_file'] . '</a></div>' .
			'</fieldset></td></tr></table>') . "<p><a href=\"options.php?$session[sessionurl]expand=$altmode\">$linktext</a></p>", $optionsmenu);
		print_submit_row($vbphrase['edit_settings'], 0);
	}
	else // show the small list with selected setting group(s) options
	{
		print_table_header("$vbphrase[setting_group] $optionsmenu <input type=\"submit\" value=\"$vbphrase[go]\" class=\"button\" tabindex=\"1\" />");
		print_table_footer();

		// show selected settings
		print_form_header('options', 'dooptions');
		construct_hidden_code('dogroup', $dogroup);
		construct_hidden_code('advanced', $advanced);

		if ($dogroup == '[all]') // show all settings groups
		{
			foreach ($grouptitlecache AS $curgroup => $group)
			{
				print_setting_group($curgroup, $advanced);
				print_description_row("<input type=\"submit\" class=\"button\" value=\" $vbphrase[save] \" tabindex=\"1\" title=\"" . $vbphrase['save_settings'] . "\" />", 0, 2, 'tfoot" style="padding:1px" align="right');
				print_table_break(' ');
			}
		}
		else
		{
			print_setting_group($dogroup, $advanced);
		}

		print_submit_row($vbphrase['save']);
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12669 $
|| ####################################################################
\*======================================================================*/
?>