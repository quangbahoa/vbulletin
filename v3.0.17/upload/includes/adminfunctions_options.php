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

error_reporting(E_ALL & ~E_NOTICE);

// ###################### Start displaysettinggroup #######################
// display settings group(s)
function print_setting_group($dogroup, $advanced = 0)
{
	global $settingscache, $grouptitlecache, $debug, $session, $vbphrase, $bgcounter, $settingphrase, $stylevar, $gdinfo;

	if (!is_array($settingscache["$dogroup"]))
	{
		return;
	}

	print_table_header(
		$settingphrase["settinggroup_$grouptitlecache[$dogroup]"]
		 . iif($debug,
		 	'<span class="normal">' .
			construct_link_code($vbphrase['edit'], "options.php?$session[sessionurl]do=editgroup&amp;grouptitle=$dogroup") .
			construct_link_code($vbphrase['delete'], "options.php?$session[sessionurl]do=removegroup&amp;grouptitle=$dogroup") .
			construct_link_code($vbphrase['add_setting'], "options.php?$session[sessionurl]do=addsetting&amp;grouptitle=$dogroup") .
			'</span>'
		)
	);

	$bgcounter = 1;

	foreach ($settingscache["$dogroup"] AS $settingid => $setting)
	{

		if (($advanced OR !$setting['advanced']) AND !empty($setting['varname']))
		{
			print_description_row(
				iif($debug, '<div class="smallfont" style="float:' . $stylevar['right'] . '">' . construct_link_code($vbphrase['edit'], "options.php?$session[sessionurl]do=editsetting&varname=$setting[varname]") . construct_link_code($vbphrase['delete'], "options.php?$session[sessionurl]do=removesetting&varname=$setting[varname]") . '</div>') .
				'<div>' . $settingphrase["setting_$setting[varname]_title"] . "<a name=\"$setting[varname]\"></a></div>",
				0, 2, "optiontitle\" title=\"\$vboptions['" . $setting['varname'] . "']"
			);

			// make sure all rows use the alt1 class
			$bgcounter--;

			$description = "<div class=\"smallfont\" title=\"\$vboptions['$setting[varname]']\">" . $settingphrase["setting_$setting[varname]_desc"] . '</div>';
			$name = "setting[$setting[varname]]";

			switch ($setting['optioncode'])
			{
				// input type="text"
				case '':
				{
					print_input_row($description, $name, $setting['value'], 1, 40);
				}
				break;

				// input type="radio"
				case 'yesno':
				{
					print_yes_no_row($description, $name, $setting['value']);
				}
				break;

				// textarea
				case 'textarea':
				{
					print_textarea_row($description, $name, $setting['value'], 8, 40);
				}
				break;

				// cp folder options
				case 'cpstylefolder':
				{
					if ($folders = fetch_cpcss_options() AND !empty($folders))
					{
						print_select_row($description, $name, $folders, $setting['value'], 1, 6);
					}
					else
					{
						print_input_row($description, $name, $setting['value'], 1, 40);
					}
				}
				break;

				// just a label
				default:
				{
					eval("\$right = \"$setting[optioncode]\";");
					print_label_row($description, $right, '', 'top', $name);
				}
				break;
			}
		}
	}
}

// ###################### Start xml_settings_otag #######################
// parse XML opening tag
function xml_parse_settings_otag($parser, $name, $attrs)
{
	global $arr, $settingGroup, $settingVarname, $settingField;

	switch($name)
	{
		case 'settinggroup':
			$settingGroup = $attrs['name'];
			$arr["$settingGroup"] = array(
				'displayorder' => $attrs['displayorder'],
				'settings' => array()
			);
			$settingField = false;
		break;

		case 'setting':
			$settingVarname = $attrs['varname'];
			$arr["$settingGroup"]['settings']["$settingVarname"] = array('displayorder' => $attrs['displayorder'], 'advanced' => $attrs['advanced']);
			$settingField = false;
		break;

		case 'optioncode':
			$settingField = 'optioncode';
			$arr["$settingGroup"]['settings']["$settingVarname"]["$settingField"] = '';
		break;

		case 'defaultvalue':
			$settingField = 'defaultvalue';
			$arr["$settingGroup"]['settings']["$settingVarname"]["$settingField"] = '';
		break;
	}
}

// ###################### Start xml_settings_ctag #######################
// parse XML closing tag
function xml_parse_settings_ctag($parser, $name)
{
	global $settingField;

	if ($settingField == 'optioncode' OR $settingField == 'defaultvalue' OR $settingField == 'setting')
	{
		$settingField = false;
	}
}

// ###################### Start xml_settings_cdata #######################
// parse XML cdata
function xml_parse_settings_cdata($parser, $data)
{
	global $arr, $settingGroup, $settingVarname, $settingField;

	if ($settingField)
	{
		$arr["$settingGroup"]['settings']["$settingVarname"]["$settingField"] .= $data;
	}

}

// ###################### Start xml_importsettings #######################
// import XML settings - call this function like this:
//		$path = './path/to/install/vbulletin-settings.xml';
//		xml_import_settings();
function xml_import_settings($xml = false)
{
	global $DB_site, $vboptions, $vbphrase, $arr;

	print_dots_start('<b>' . $vbphrase['importing_settings'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	if ($xml == false)
	{
		if (empty($GLOBALS['path']))
		{
			print_dots_stop();
			print_stop_message('no_xml_and_no_path');
		}
		elseif (!($xml = file_read($GLOBALS['path'])))
		{
			print_dots_stop();
			print_stop_message('please_ensure_x_file_is_located_at_y', 'vbulletin-settings.xml', $GLOBALS['path']);
		}
	}

	// initialize vars
	$arr = array();

	// create parser
	$parser = xml_parser_create('ISO-8859-1');

	// set parser options
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_set_element_handler($parser, 'xml_parse_settings_otag', 'xml_parse_settings_ctag');
	xml_set_character_data_handler($parser, 'xml_parse_settings_cdata');

	// parse the XML
	if (!@xml_parse($parser, $xml))
	{
		print_stop_message('xml_error_x_at_line_y', xml_error_string(xml_get_error_code($parser)), xml_get_current_line_number($parser));
	}

	// free the parser
	xml_parser_free($parser);

	if (empty($arr))
	{
		print_stop_message('invalid_or_empty_x_file');
	}

	// delete old volatile settings and settings that might conflict with new ones...
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "settinggroup WHERE volatile = 1");
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "setting WHERE volatile = 1");

	// run through imported array
	foreach(array_keys($arr) AS $grouptitle)
	{
		// insert setting group
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "settinggroup
			(grouptitle, displayorder, volatile)
			VALUES
			('" . addslashes($grouptitle) . "', " . intval($arr["$grouptitle"]['displayorder']) . ", 1)
		");

		// build insert query for this group's settings
		$qBits = array();
		foreach($arr["$grouptitle"]['settings'] AS $varname => $setting)
		{
			// changed this line, as we do the version number automatically in upgradecore.php (if (defined('SCRIPTCOMPLETE')) .....)
			//if ((isset($vboptions["$varname"]) AND $varname != 'templateversion') OR ($varname == 'templateversion' AND VB_AREA == 'Upgrade'))
			if (isset($vboptions["$varname"]))
			{
				$newvalue = $vboptions["$varname"];
			}
			else
			{
				$newvalue = $setting['defaultvalue'];
			}
			$qBits[] = "(
				'" . addslashes($varname) . "',
				'" . addslashes($grouptitle) . "',
				'" . addslashes(trim($newvalue)) . "',
				'" . addslashes(trim($setting['defaultvalue'])) . "',
				'" . addslashes($setting['optioncode']) . "',
				" . intval($setting['displayorder']) . ",
				" . intval($setting['advanced']) . ",
				1\n\t)";
		}
		// run settings insert query
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "setting
			(varname, grouptitle, value, defaultvalue, optioncode, displayorder, advanced, volatile)
			VALUES
			" . implode(",\n\t", $qBits));
	}

	// rebuild the $vboptions array
	build_options();

	// stop the 'dots' counter feedback
	print_dots_stop();

}


// ###################### Start getstylesarray #######################
function fetch_style_title_options_array($titleprefix = '', $displaytop = false)
{
	require_once('./includes/adminfunctions_template.php');
	global $stylecache;

	cache_styles();
	$out = array();

	foreach($stylecache AS $style)
	{
		$out["$style[styleid]"] = $titleprefix . construct_depth_mark($style['depth'], '--', iif($displaytop, '--', '')) . " $style[title]";
	}

	return $out;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11091 $
|| ####################################################################
\*======================================================================*/
?>