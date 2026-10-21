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
define('CVS_REVISION', '$RCSfile$ - $Revision: 13986 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array(
	'timezone',
	'cpoption',
	'user',
	'cpuser',
	'holiday',
);

$specialtemplates = array(
	'banemail',
);

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

$vbulletin->input->clean_array_gpc('r', array(
	'varname' => TYPE_STR,
	'dogroup' => TYPE_STR,
));

// intercept direct call to do=options with $varname specified instead of $dogroup
if ($_REQUEST['do'] == 'options' AND !empty($vbulletin->GPC['varname']))
{
	if ($vbulletin->GPC['varname'] == '[all]')
	{
		// go ahead and show all settings
		$vbulletin->GPC['dogroup'] = '[all]';
	}
	else if ($group = $db->query_first("SELECT varname, grouptitle FROM " . TABLE_PREFIX . "setting WHERE varname = '" . $db->escape_string($vbulletin->GPC['varname']) . "'"))
	{
		// redirect to show the correct group and use and anchor to jump to the correct variable
		exec_header_redirect('options.php?' . $vbulletin->session->vars['sessionurl_js'] . "do=options&dogroup=$group[grouptitle]#$group[varname]");
	}
	else
	{
		// could not find a matching group - just carry on as if nothing happened
		$_REQUEST['do'] = 'options';
	}
}

require_once(DIR . '/includes/adminfunctions_options.php');
require_once(DIR . '/includes/functions_misc.php');

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
$phrases = $db->query_read("
	SELECT varname, text
	FROM " . TABLE_PREFIX . "phrase
	WHERE phrasetypeid = " . PHRASETYPEID_SETTING . " AND
		languageid IN(-1, 0, " . LANGUAGEID . ")
	ORDER BY languageid ASC
");
while($phrase = $db->fetch_array($phrases))
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
	$vbulletin->input->clean_array_gpc('r', array(
		'product' => TYPE_STR
	));

	$setting = array();
	$settinggroup = array();

	$groups = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "settinggroup
		WHERE volatile = 1
		ORDER BY displayorder
	");
	while ($group = $db->fetch_array($groups))
	{
		$settinggroup["$group[grouptitle]"] = $group;
	}

	$sets = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "setting
		WHERE volatile = 1
			AND (product = '" . $db->escape_string($vbulletin->GPC['product']) . "'" . iif($vbulletin->GPC['product'] == 'vbulletin', " OR product = ''") . ")
		ORDER BY displayorder");
	while ($set = $db->fetch_array($sets))
	{
		$setting["$set[grouptitle]"][] = $set;
	}
	unset($set);
	$db->free_result($sets);

	require_once(DIR . '/includes/class_xml.php');
	$xml = new XMLexporter();
	$xml->add_group('settinggroups', array('product' => $vbulletin->GPC['product']));

	foreach($settinggroup AS $grouptitle => $group)
	{
		if (!empty($setting["$grouptitle"]))
		{
			$group = $settinggroup["$grouptitle"];
			$xml->add_group('settinggroup', array('name' => htmlspecialchars($group['grouptitle']), 'displayorder' => $group['displayorder'], 'product' => $group['product']));
			foreach($setting["$grouptitle"] AS $set)
			{
				$arr = array('varname' => $set['varname'], 'displayorder' => $set['displayorder']);
				if ($set['advanced'])
				{
					$arr['advanced'] = 1;
				}
				$xml->add_group('setting', $arr);

				if ($set['datatype'])
				{
					$xml->add_tag('datatype', $set['datatype']);
				}
				if ($set['optioncode'] != '')
				{
					$xml->add_tag('optioncode', $set['optioncode']);
				}
				if ($set['defaultvalue'] != '')
				{
					$xml->add_tag('defaultvalue', iif($set['varname'] == 'templateversion', $vbulletin->options['templateversion'], $set['defaultvalue']));
				}
				$xml->close_group();
			}
			$xml->close_group();
		}
	}

	$xml->close_group();

	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";

	$doc .= $xml->output();
	$xml = null;

	require_once(DIR . '/includes/functions_file.php');
	file_download($doc, 'vbulletin-settings.xml', 'text/xml');

}

// ***********************************************************************

print_cp_header($vbphrase['vbulletin_options']);

// ###################### Start do import settings XML #######################
if ($_POST['do'] == 'doimport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'serverfile'   => TYPE_STR,
	));

	$vbulletin->input->clean_array_gpc('f', array(
		'settingsfile' => TYPE_FILE
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}
	// got an uploaded file?
	if (file_exists($vbulletin->GPC['settingsfile']['tmp_name']))
	{
		$xml = file_read($vbulletin->GPC['settingsfile']['tmp_name']);
	}
	// no uploaded file - got a local file?
	else if (file_exists($vbulletin->GPC['serverfile']))
	{
		$xml = file_read($vbulletin->GPC['serverfile']);
	}
	// no uploaded file and no local file - ERROR
	else
	{
		print_stop_message('no_file_uploaded_and_no_local_file_found');
	}

	xml_import_settings($xml);

	print_cp_redirect("options.php?" . $vbulletin->session->vars['sessionurl'], 0);
}

// ###################### Start import settings XML #######################
if ($_REQUEST['do'] == 'files')
{
	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	// download form
	print_form_header('options', 'download', 0, 1, 'downloadform" target="download');
	print_table_header($vbphrase['download']);
	print_select_row($vbphrase['product'], 'product', fetch_product_list());
	print_submit_row($vbphrase['download']);

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
	$vbulletin->input->clean_array_gpc('p', array(
		'title' => TYPE_STR
	));

	// get some info
	$group = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "settinggroup WHERE grouptitle = '" . $db->escape_string($vbulletin->GPC['title']) . "'");

	// query settings from this group
	$settings = array();
	$sets = $db->query_read("SELECT varname FROM " . TABLE_PREFIX . "setting WHERE grouptitle = '$group[grouptitle]'");
	while ($set = $db->fetch_array($sets))
	{
		$settings[] = $db->escape_string($set['varname']);
	}

	// build list of phrases to be deleted
	$phrases = array("settinggroup_$group[grouptitle]");
	foreach($settings AS $varname)
	{
		$phrases[] = 'setting_' . $varname . '_title';
		$phrases[] = 'setting_' . $varname . '_desc';
	}

	// delete phrases
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (-1,0) AND
			phrasetypeid = " . PHRASETYPEID_SETTING . " AND
			varname IN ('" . implode("', '", $phrases) . "')
	");

	// delete settings
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "setting
		WHERE varname IN ('" . implode("', '", $settings) . "')
	");

	// delete group
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "settinggroup
		WHERE grouptitle = '" . $db->escape_string($group['grouptitle']) . "'
	");

	build_options();

	define('CP_REDIRECT', 'options.php');
	print_stop_message('deleted_setting_group_successfully');

}

// ###################### Start remove setting group #######################
if ($_REQUEST['do'] == 'removegroup')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'grouptitle' => TYPE_STR
	));

	print_delete_confirmation('settinggroup', $vbulletin->GPC['grouptitle'], 'options', 'killgroup');
}

// ###################### Start insert setting group #######################
if ($_POST['do'] == 'insertgroup')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'group' => TYPE_ARRAY
	));

	// insert setting place-holder
	/*insert query*/
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "settinggroup
			(grouptitle, product)
		VALUES
			('" . $db->escape_string($vbulletin->GPC['group']['grouptitle']) . "',
			'" . $db->escape_string($vbulletin->GPC['group']['product']) . "')
	");

	// insert associated phrases
	$languageid = iif($vbulletin->GPC['group']['volatile'], -1, 0);
	/*insert query*/
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "phrase
			(languageid, phrasetypeid, varname, text, product)
		VALUES
			($languageid,
			" . PHRASETYPEID_SETTING . ",
			'settinggroup_" . $db->escape_string($vbulletin->GPC['group']['grouptitle']) . "',
			'" . $db->escape_string($vbulletin->GPC['group']['title']) . "',
			'" . $db->escape_string($vbulletin->GPC['group']['product']) . "')
	");

	// fall through to 'updategroup' for the real work...
	$_POST['do'] = 'updategroup';
}

// ###################### Start update setting group #######################
if ($_POST['do'] == 'updategroup')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'group' => TYPE_ARRAY,
		'oldproduct' => TYPE_STR
	));

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "settinggroup SET
			displayorder = " . intval($vbulletin->GPC['group']['displayorder']) . ",
			volatile = " . intval($vbulletin->GPC['group']['volatile']) . ",
			product = '" . $db->escape_string($vbulletin->GPC['group']['product']) . "'
		WHERE grouptitle = '" . $db->escape_string($vbulletin->GPC['group']['grouptitle']) . "'
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "phrase SET
			text = '" . $db->escape_string($vbulletin->GPC['group']['title']) . "',
			product = '" . $db->escape_string($vbulletin->GPC['group']['product']) . "'
		WHERE languageid IN(-1, 0)
			AND varname = 'settinggroup_" . $db->escape_string($vbulletin->GPC['group']['grouptitle']) . "'
	");

	$settingnames = array();
	$phrasenames = array();

	$settings = $db->query_read("
		SELECT varname, product
		FROM " . TABLE_PREFIX . "setting
		WHERE grouptitle = '" . $db->escape_string($vbulletin->GPC['group']['grouptitle']) . "'
		AND product = '" . $db->escape_string($vbulletin->GPC['oldproduct']) . "'
	");
	while ($setting = $db->fetch_array($settings))
	{
		$settingnames[] = "'" . $db->escape_string($setting['varname']) . "'";
		$phrasenames[] = "'" . $db->escape_string('setting_' . $setting['varname'] . '_desc') . "'";
		$phrasenames[] = "'" . $db->escape_string('setting_' . $setting['varname'] . '_title') . "'";
	}
	if ($db->num_rows($settings))
	{
		$q1 = "
			UPDATE " . TABLE_PREFIX . "setting
			SET product = '" . $db->escape_string($vbulletin->GPC['group']['product']) . "'
			WHERE varname IN(
				" . implode(",\n				", $settingnames) . ")
		";
		$db->query_write($q1);

		$q2 = "
			UPDATE " . TABLE_PREFIX . "phrase
			SET product = '" . $db->escape_string($vbulletin->GPC['group']['product']) . "'
			WHERE varname IN(
				" . implode(",\n				", $phrasenames) . "
			) AND phrasetypeid = " . PHRASETYPEID_SETTING . "
		";
		$db->query_write($q2);
	}

	define('CP_REDIRECT', 'options.php?do=options&amp;dogroup=' . $vbulletin->GPC['group']['grouptitle']);
	print_stop_message('saved_setting_group_x_successfully', $vbulletin->GPC['group']['title']);
}

// ###################### Start edit setting group #######################
if ($_REQUEST['do'] == 'editgroup' OR $_REQUEST['do'] == 'addgroup')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'grouptitle' => TYPE_STR,
	));

	if ($_REQUEST['do'] == 'editgroup')
	{
		$group = $db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "settinggroup
			WHERE grouptitle = '" . $db->escape_string($vbulletin->GPC['grouptitle']) . "'
		");
		$phrase = $db->query_first("
			SELECT text FROM " . TABLE_PREFIX . "phrase
			WHERE languageid IN (-1,0) AND
				phrasetypeid = " . PHRASETYPEID_SETTING . " AND
				varname = 'settinggroup_" . $db->escape_string($group['grouptitle']) . "'
		");
		$group['title'] = $phrase['text'];
		$pagetitle = construct_phrase($vbphrase['x_y_id_z'], $vbphrase['setting_group'], $group['title'], $group['grouptitle']);
		$formdo = 'updategroup';
	}
	else
	{
		$ordercheck = $db->query_first("
			SELECT displayorder
			FROM " . TABLE_PREFIX . "settinggroup
			ORDER BY displayorder DESC
		");
		$group = array(
			'displayorder' => $ordercheck['displayorder'] + 10,
			'volatile' => iif($vbulletin->debug, 1, 0)
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
	construct_hidden_code('oldproduct', $group['product']);
	print_select_row($vbphrase['product'], 'group[product]', fetch_product_list(), $group['product']);
	print_input_row($vbphrase['display_order'], 'group[displayorder]', $group['displayorder']);
	if ($vbulletin->debug)
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
	$vbulletin->input->clean_array_gpc('p', array(
		'title' => TYPE_STR
	));

	// get some info
	$setting = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "setting WHERE varname = '" . $db->escape_string($vbulletin->GPC['title']) . "'");

	// delete phrases
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (-1, 0) AND
			phrasetypeid = " . PHRASETYPEID_SETTING . " AND
			varname IN ('setting_" . $db->escape_string($setting['varname']) . "_title', 'setting_" . $db->escape_string($setting['varname']) . "_desc')
	");

	// delete setting
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "setting WHERE varname = '" . $db->escape_string($setting['varname']) . "'");
	build_options();

	define('CP_REDIRECT', 'options.php?do=options&amp;dogroup=' . $setting['grouptitle']);
	print_stop_message('deleted_setting_successfully');
}

// ###################### Start remove setting #######################
if ($_REQUEST['do'] == 'removesetting')
{
	print_delete_confirmation('setting', $vbulletin->GPC['varname'], 'options', 'killsetting');
}

// ###################### Start insert setting #######################
if ($_POST['do'] == 'insertsetting')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'setting' => TYPE_ARRAY
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if ($s = $db->query_first("
		SELECT varname
		FROM " . TABLE_PREFIX . "setting
		WHERE varname = '" . $db->escape_string($vbulletin->GPC['setting']['varname']) . "'
	"))
	{
		print_stop_message('there_is_already_setting_named_x', $vbulletin->GPC['setting']['varname']);
	}

	// insert setting place-holder
	/*insert query*/
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "setting
			(varname, value, product)
		VALUES
			('" . $db->escape_string($vbulletin->GPC['setting']['varname']) . "',
			'" . $db->escape_string($vbulletin->GPC['setting']['defaultvalue']) . "',
			'" . $db->escape_string($vbulletin->GPC['setting']['product']) . "')
	");

	// insert associated phrases
	$languageid = iif($vbulletin->GPC['setting']['volatile'], -1, 0);
	/*insert query*/
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "phrase
			(languageid, phrasetypeid, varname, text, product)
		VALUES
			($languageid,
			" . PHRASETYPEID_SETTING . ",
			'setting_" . $db->escape_string($vbulletin->GPC['setting']['varname']) . "_title',
			'" . $db->escape_string($vbulletin->GPC['setting']['title']) . "',
			'" . $db->escape_string($vbulletin->GPC['setting']['product']) . "')
	");
	/*insert query*/
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "phrase
			(languageid, phrasetypeid, varname, text, product)
		VALUES
			($languageid,
			" . PHRASETYPEID_SETTING . ",
			'setting_" . $db->escape_string($vbulletin->GPC['setting']['varname']) . "_desc',
			'" . $db->escape_string($vbulletin->GPC['setting']['description']) . "',
			'" . $db->escape_string($vbulletin->GPC['setting']['product']) . "')
	");

	// fall through to 'updatesetting' for the real work...
	$_POST['do'] = 'updatesetting';
}

// ###################### Start update setting #######################
if ($_POST['do'] == 'updatesetting')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'setting' => TYPE_ARRAY,
		'oldproduct' => TYPE_STR
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "setting SET
			grouptitle = '" . $db->escape_string($vbulletin->GPC['setting']['grouptitle']) . "',
			optioncode = '" . $db->escape_string($vbulletin->GPC['setting']['optioncode']) . "',
			defaultvalue = '" . $db->escape_string($vbulletin->GPC['setting']['defaultvalue']) . "',
			displayorder = " . intval($vbulletin->GPC['setting']['displayorder']) . ",
			volatile = " . intval($vbulletin->GPC['setting']['volatile']) . ",
			datatype = '" . $db->escape_string($vbulletin->GPC['setting']['datatype']) . "',
			product = '" . $db->escape_string($vbulletin->GPC['setting']['product']) . "'
		WHERE varname = '" . $db->escape_string($vbulletin->GPC['setting']['varname']) . "'
	");

	$newlang = iif($vbulletin->GPC['setting']['volatile'], -1, 0);

	$phrases = $db->query_read("
		SELECT varname, text, languageid, product
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (-1,0)
			AND phrasetypeid = " . PHRASETYPEID_SETTING . "
			AND varname IN ('setting_" . $db->escape_string($vbulletin->GPC['setting']['varname']) . "_title', 'setting_" . $db->escape_string($vbulletin->GPC['setting']['varname']) . "_desc')
	");

	while ($phrase = $db->fetch_array($phrases))
	{
		if ($phrase['varname'] == "setting_" . $vbulletin->GPC['setting']['varname'] . "_title")
		{
			$q = "
				UPDATE " . TABLE_PREFIX . "phrase SET
					languageid = " . iif($vbulletin->GPC['setting']['volatile'], -1, 0) . ",
					text = '" . $db->escape_string($vbulletin->GPC['setting']['title']) . "',
					product = '" . $db->escape_string($vbulletin->GPC['setting']['product']) . "'
				WHERE languageid = $phrase[languageid]
					AND varname = 'setting_" . $db->escape_string($vbulletin->GPC['setting']['varname']) . "_title'
			";
			$db->query_write($q);
		}
		else if ($phrase['varname'] == "setting_" . $vbulletin->GPC['setting']['varname'] . "_desc")
		{
			$q = "
				UPDATE " . TABLE_PREFIX . "phrase SET
					languageid = " . iif($vbulletin->GPC['setting']['volatile'], -1, 0) . ",
					text = '" . $db->escape_string($vbulletin->GPC['setting']['description']) . "',
					product = '" . $db->escape_string($vbulletin->GPC['setting']['product']) . "'
				WHERE languageid = $phrase[languageid]
					AND varname = 'setting_" . $db->escape_string($vbulletin->GPC['setting']['varname']) . "_desc'
			";
			$db->query_write($q);
		}
	}

	build_options();

	define('CP_REDIRECT', 'options.php?do=options&amp;dogroup=' . $vbulletin->GPC['setting']['grouptitle']);
	print_stop_message('saved_setting_x_successfully', $vbulletin->GPC['setting']['title']);
}

// ###################### Start edit / add setting #######################
if ($_REQUEST['do'] == 'editsetting' OR $_REQUEST['do'] == 'addsetting')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'grouptitle' => TYPE_STR
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	$settinggroups = array();
	$groups = $db->query_read("SELECT grouptitle FROM " . TABLE_PREFIX . "settinggroup ORDER BY displayorder");
	while ($group = $db->fetch_array($groups))
	{
		$settinggroups["$group[grouptitle]"] = $settingphrase["settinggroup_$group[grouptitle]"];
	}

	if ($_REQUEST['do'] == 'editsetting')
	{
		$setting = $db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "setting
			WHERE varname = '" . $db->escape_string($vbulletin->GPC['varname']) . "'
		");
		$phrases = $db->query_read("
			SELECT varname, text
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid = " . iif($setting['volatile'], -1, 0) . " AND
				phrasetypeid = " . PHRASETYPEID_SETTING . " AND
			varname IN ('setting_" . $db->escape_string($setting['varname']) . "_title', 'setting_" . $db->escape_string($setting['varname']) . "_desc')
		");
		while ($phrase = $db->fetch_array($phrases))
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
		$ordercheck = $db->query_first("
			SELECT displayorder FROM " . TABLE_PREFIX . "setting
			WHERE grouptitle='" . $db->escape_string($vbulletin->GPC['grouptitle']) . "'
			ORDER BY displayorder DESC
		");
		$setting = array(
			'grouptitle' => $vbulletin->GPC['grouptitle'],
			'displayorder' => $ordercheck['displayorder'] + 10,
			'volatile' => iif($vbulletin->debug, 1, 0)
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
	print_select_row($vbphrase['product'], 'setting[product]', fetch_product_list(), $setting['product']);
	print_input_row($vbphrase['title'], 'setting[title]', $setting['title']);
	print_textarea_row($vbphrase['description'], 'setting[description]', $setting['description'], 4, 50);
	print_textarea_row($vbphrase['option_code'], 'setting[optioncode]', $setting['optioncode'], 4, 50);

	switch ($setting['datatype'])
	{
		case 'number': $checked = array('number' => ' checked="checked"'); break;
		case 'boolean': $checked = array('boolean' => ' checked="checked"'); break;
		default: $checked = array('free' => ' checked="checked"');
	}
	print_label_row($vbphrase['data_validation_type'], '
		<div class="smallfont">
		<label for="rb_dt_free"><input type="radio" name="setting[datatype]" id="rb_dt_free" tabindex="1" value="free"' . $checked['free'] . ' />' . $vbphrase['datatype_free'] . '</label>
		<label for="rb_dt_number"><input type="radio" name="setting[datatype]" id="rb_dt_number" tabindex="1" value="number"' . $checked['number'] . ' />' . $vbphrase['datatype_numeric'] . '</label>
		<label for="rb_dt_boolean"><input type="radio" name="setting[datatype]" id="rb_dt_boolean" tabindex="1" value="boolean"' . $checked['boolean'] . ' />' . $vbphrase['datatype_boolean'] . '</label>
		</div>
	');

	print_textarea_row($vbphrase['default'], 'setting[defaultvalue]', $setting['defaultvalue'], 4, 50);
	print_input_row($vbphrase['display_order'], 'setting[displayorder]', $setting['displayorder']);
	if ($vbulletin->debug)
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
	$vbulletin->input->clean_array_gpc('p', array(
		'setting'  => TYPE_ARRAY,
		'advanced' => TYPE_BOOL
	));

	if (!empty($vbulletin->GPC['setting']))
	{
		$varnames = array();
		foreach(array_keys($vbulletin->GPC['setting']) AS $varname)
		{
			$varnames[] = $db->escape_string($varname);
		}

		$oldsettings = $db->query_read("
			SELECT value, varname, datatype
			FROM " . TABLE_PREFIX . "setting
			WHERE varname IN ('" . implode("', '", $varnames) . "')
			ORDER BY varname
		");
		while ($oldsetting = $db->fetch_array($oldsettings))
		{
			switch ($oldsetting['varname'])
			{
				// **************************************************
				case 'memberlistfields':
				case 'defaultregoptions':
				case 'allowedbbcodes':
				case 'postelements':
				case 'activememberoptions':
				case 'showeaster':
				{
					$bitfield = 0;
					foreach ($vbulletin->GPC['setting']["$oldsetting[varname]"] AS $bitval)
					{
						$bitfield += $bitval;
					}
					$vbulletin->GPC['setting']["$oldsetting[varname]"] = $bitfield;
				}
				break;

				// **************************************************
				case 'bbcode_html_colors':
				{
					$vbulletin->GPC['setting']['bbcode_html_colors'] = serialize($vbulletin->GPC['setting']['bbcode_html_colors']);
				}
				break;

				// **************************************************
				case 'styleid':
				{
					$db->query_write("
						UPDATE " . TABLE_PREFIX . "style
						SET userselect = 1
						WHERE styleid = " . $vbulletin->GPC['setting']['styleid'] . "
					");
				}
				break;

				// **************************************************
				case 'banemail':
				{
					build_datastore('banemail', $vbulletin->GPC['setting']['banemail']);
					$vbulletin->GPC['setting']['banemail'] = '';
				}
				break;

				// **************************************************
				case 'editormodes':
				{
					$vbulletin->input->clean_array_gpc('p', array('fe' => TYPE_UINT, 'qr' => TYPE_UINT, 'qe' => TYPE_UINT));

					$vbulletin->GPC['setting']['editormodes'] = serialize(array(
						'fe' => $vbulletin->GPC['fe'],
						'qr' => $vbulletin->GPC['qr'],
						'qe' => $vbulletin->GPC['qe']
					));
				}
				break;

				// **************************************************
				case 'cookiepath':
				case 'cookiedomain':
				{
					if ($vbulletin->GPC['setting'][$oldsetting['varname'] . '_other'] AND $vbulletin->GPC['setting'][$oldsetting['varname'] . '_value'])
					{
						$vbulletin->GPC['setting'][$oldsetting['varname']] = $vbulletin->GPC['setting'][$oldsetting['varname'] . '_value'];
					}
				}
				break;

				default:
					($hook = vBulletinHook::fetch_hook('admin_options_processing')) ? eval($hook) : false;

			}

			$newvalue = validate_setting_value($vbulletin->GPC['setting']["$oldsetting[varname]"], $oldsetting['datatype']);

			// this is a strict type check because we want '' to be different from 0
			// some special cases below only use != checks to see if the logical value has changed
			if ($oldsetting['value'] !== $newvalue)
			{
				switch ($oldsetting['varname'])
				{
					case 'showevents':
					case 'showholidays':
						if ($oldsetting['value'] != $newvalue)
						{
							require_once(DIR . '/includes/functions_databuild.php');
							build_events();
						}
					break;

					case 'languageid':
					{
						if ($oldsetting['value'] != $newvalue)
						{
							$vbulletin->options['languageid'] = $newvalue;
							require_once(DIR . '/includes/adminfunctions_language.php');
							build_language($vbulletin->options['languageid']);
						}
					}
					break;

					case 'cpstylefolder':
					{
						$admindm =& datamanager_init('Admin', $vbulletin, ERRTYPE_CP);
						$admindm->set_existing($vbulletin->userinfo);
						$admindm->set('cssprefs', $newvalue);
						$admindm->save();
						unset($admindm);
					}
					break;

					case 'storecssasfile':
					{
						if (!is_demo_mode() AND $oldsetting['value'] != $newvalue)
						{
							$vbulletin->options['storecssasfile'] = $newvalue;
							require_once(DIR . '/includes/adminfunctions_template.php');
							print_rebuild_style(-1, '', 1, 0, 0, 0);
						}
					}
					break;

					case 'codemaxlines':
					{
						if ($oldsetting['value'] != $newvalue)
						{
							$db->query_write("DELETE FROM " . TABLE_PREFIX . "post_parsed");
						}
					}
					break;
				}

				if (is_demo_mode() AND in_array($oldsetting['varname'], array('storecssasfile', 'attachfile', 'usefileavatar', 'errorlogdatabase', 'errorlogsecurity', 'safeupload', 'tmppath')))
				{
					continue;
				}

				$db->query_write("
					UPDATE " . TABLE_PREFIX . "setting
					SET value = '" . $db->escape_string($newvalue) . "'
					WHERE varname = '" . $db->escape_string($oldsetting['varname']) . "'
				");
			}
		}
		build_options();

		define('CP_REDIRECT', 'options.php?do=options&amp;dogroup=' . $vbulletin->GPC['dogroup'] . '&amp;advanced= ' . $vbulletin->GPC['advanced']);
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
	$gdinfo = fetch_gdinfo();

	require_once(DIR . '/includes/adminfunctions_language.php');

	$vbulletin->input->clean_array_gpc('r', array(
		'advanced' => TYPE_BOOL,
		'expand'   => TYPE_BOOL,
	));

	// display links to settinggroups and create settingscache
	$settingscache = array();
	$options = array('[all]' => '-- ' . $vbphrase['show_all_settings'] . ' --');
	$lastgroup = '';

	$settings = $db->query_read("
		SELECT setting.*, settinggroup.grouptitle
		FROM " . TABLE_PREFIX . "settinggroup AS settinggroup
		LEFT JOIN " . TABLE_PREFIX . "setting AS setting USING(grouptitle)
		" . iif($vbulletin->debug, '', 'WHERE settinggroup.displayorder <> 0') . "
		ORDER BY settinggroup.displayorder, setting.displayorder
	");

	if (empty($vbulletin->GPC['dogroup']) AND $vbulletin->GPC['expand'])
	{
		while ($setting = $db->fetch_array($settings))
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
		$linktext =& $vbphrase['collapse_setting_groups'];
	}
	else
	{
		while ($setting = $db->fetch_array($settings))
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
		$linktext =& $vbphrase['expand_setting_groups'];
	}
	$db->free_result($settings);

	$optionsmenu = "\n\t<select name=\"" . iif($vbulletin->GPC['expand'], 'varname', 'dogroup') . "\" class=\"bginput\" tabindex=\"1\" " . iif(empty($vbulletin->GPC['dogroup']), 'ondblclick="this.form.submit();" size="20"', 'onchange="this.form.submit();"') . " style=\"width:350px\">\n" . construct_select_options($options, iif($vbulletin->GPC['dogroup'], $vbulletin->GPC['dogroup'], '[all]')) . "\t</select>\n\t";

	print_form_header('options', 'options', 0, 1, 'groupForm', '90%', '', 1, 'get');

	if (empty($vbulletin->GPC['dogroup'])) // show the big <select> with no options
	{
		print_table_header($vbphrase['vbulletin_options']);
		print_label_row($vbphrase['settings_to_edit'] . iif($vbulletin->debug,
			'<br /><table><tr><td><fieldset><legend>Developer Options</legend>
			<div style="padding: 2px"><a href="options.php?' . $vbulletin->session->vars['sessionurl'] . 'do=addgroup">' . $vbphrase['add_new_setting_group'] . '</a></div>
			<div style="padding: 2px"><a href="options.php?' . $vbulletin->session->vars['sessionurl'] . 'do=files">' . $vbphrase['download_upload_settings'] . '</a></div>' .
			'</fieldset></td></tr></table>') . "<p><a href=\"options.php?" . $vbulletin->session->vars['sessionurl'] . "expand=$altmode\">$linktext</a></p>", $optionsmenu);
		print_submit_row($vbphrase['edit_settings'], 0);
	}
	else // show the small list with selected setting group(s) options
	{
		print_table_header("$vbphrase[setting_group] $optionsmenu <input type=\"submit\" value=\"$vbphrase[go]\" class=\"button\" tabindex=\"1\" />");
		print_table_footer();

		// show selected settings
		print_form_header('options', 'dooptions');
		construct_hidden_code('dogroup', $vbulletin->GPC['dogroup']);
		construct_hidden_code('advanced', $vbulletin->GPC['advanced']);

		if ($vbulletin->GPC['dogroup'] == '[all]') // show all settings groups
		{
			foreach ($grouptitlecache AS $curgroup => $group)
			{
				print_setting_group($curgroup, $vbulletin->GPC['advanced']);
				print_description_row("<input type=\"submit\" class=\"button\" value=\" $vbphrase[save] \" tabindex=\"1\" title=\"" . $vbphrase['save_settings'] . "\" />", 0, 2, 'tfoot" style="padding:1px" align="right');
				print_table_break(' ');
			}
		}
		else
		{
			print_setting_group($vbulletin->GPC['dogroup'], $vbulletin->GPC['advanced']);
		}

		print_submit_row($vbphrase['save']);
	}
}

// #################### Start Change Search Type #####################
if ($_REQUEST['do'] == 'searchtype')
{
	require_once(DIR . '/includes/class_dbalter.php');

	$db_alter =& new vB_Database_Alter_MySQL($db);
	$db_alter->fetch_table_info('post');
	$convertpost = iif($db_alter->fetch_table_type() != 'MYISAM', true, false);

	$db_alter->fetch_table_info('thread');
	$convertthread = iif($db_alter->fetch_table_type() != 'MYISAM', true, false);

	$warning2 = iif($convertpost OR $convertthread, $vbphrase['your_post_and_thread_table_will_be_converted']);

	print_form_header('options', 'dosearchtype');
	print_table_header("$vbphrase[search_type]");
	if ($vbulletin->options['fulltextsearch'])
	{
		print_description_row($vbphrase['your_forum_is_currently_using_fulltext_search']);
		print_yes_no_row($vbphrase['remove_fulltext_indices'], 'deleteindex', true);
	}
	else
	{
		print_description_row(construct_phrase($vbphrase['your_forum_is_currently_using_default_search'], TABLE_PREFIX, $warning1, $warning2));
		print_yes_no_row($vbphrase['empty_postindex_and_word'], 'deletepostindex', false);
	}
	print_submit_row($vbphrase['go'], 0);

}

// #################### Start Change Search Type #####################
if ($_POST['do'] == 'dosearchtype')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'deleteindex'     => TYPE_BOOL,
		'deletepostindex' => TYPE_BOOL
	));

	require_once(DIR . '/includes/class_dbalter.php');

	$db_alter =& new vB_Database_Alter_MySQL($db);
	if ($vbulletin->options['fulltextsearch'])
	{
		if ($vbulletin->GPC['deleteindex'])
		{
			if ($db_alter->fetch_table_info('post'))
			{
				$db_alter->drop_index('title');
			}
			else
			{
				print_stop_message('dbalter_' . $db_alter->fetch_error(), $db_alter->fetch_error_message());
			}

			if ($db_alter->fetch_table_info('thread'))
			{
				$db_alter->drop_index('title');
			}
			else
			{
				print_stop_message('dbalter_' . $db_alter->fetch_error(), $db_alter->fetch_error_message());
			}
		}
	}
	else
	{
		// add indices
		if ($db_alter->fetch_table_info('post'))
		{
			if(!$db_alter->add_index('title', array('title', 'pagetext'), 'fulltext', true))
			{
				print_stop_message('dbalter_' . $db_alter->fetch_error(), $db_alter->fetch_error_message());
			}
		}
		else
		{
			print_stop_message('dbalter_' . $db_alter->fetch_error(), $db_alter->fetch_error_message());
		}

		if ($db_alter->fetch_table_info('thread'))
		{
			if (!$db_alter->add_index('title', array('title'), 'fulltext', true))
			{
				$error = $db_alter->fetch_error();
				$errormsg = $db_alter->fetch_error_message();
				// Remove index that was added to post above.
				if ($db_alter->fetch_table_info('post'))
				{
					$db_alter->drop_index('title');
				}
				print_stop_message('dbalter_' . $error, $errormsg);
			}
		}
		else
		{
			$error = $db_alter->fetch_error();
			$errormsg = $db_alter->fetch_error_message();
			// Remove index that was added to post above.
			if ($db_alter->fetch_table_info('post'))
			{
				$db_alter->drop_index('title');
			}
			print_stop_message('dbalter_' . $error, $errormsg);
		}

		// now empty postindex and word if we were given the ok
		if ($vbulletin->GPC['deletepostindex'])
		{
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "postindex");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "word");
		}
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "setting
		SET value = " . iif($vbulletin->options['fulltextsearch'], 0, 1) . "
		WHERE varname = 'fulltextsearch'
	");
	build_options();
	define('CP_REDIRECT', 'index.php');
	print_stop_message('saved_settings_successfully');

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13986 $
|| ####################################################################
\*======================================================================*/
?>