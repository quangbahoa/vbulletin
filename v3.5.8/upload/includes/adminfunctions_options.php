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

error_reporting(E_ALL & ~E_NOTICE);

/**
* Prints a setting group for use in options.php?do=options
*
* @param	string	Settings group ID
* @param	boolean	Show advanced settings?
*/
function print_setting_group($dogroup, $advanced = 0)
{
	global $settingscache, $grouptitlecache, $vbulletin, $vbphrase, $bgcounter, $settingphrase, $stylevar, $gdinfo;

	if (!is_array($settingscache["$dogroup"]))
	{
		return;
	}

	print_table_header(
		$settingphrase["settinggroup_$grouptitlecache[$dogroup]"]
		 . iif($vbulletin->debug,
		 	'<span class="normal">' .
			construct_link_code($vbphrase['edit'], "options.php?" . $vbulletin->session->vars['sessionurl'] . "do=editgroup&amp;grouptitle=$dogroup") .
			construct_link_code($vbphrase['delete'], "options.php?" . $vbulletin->session->vars['sessionurl'] . "do=removegroup&amp;grouptitle=$dogroup") .
			construct_link_code($vbphrase['add_setting'], "options.php?" . $vbulletin->session->vars['sessionurl'] . "do=addsetting&amp;grouptitle=$dogroup") .
			'</span>'
		)
	);

	$bgcounter = 1;

	foreach ($settingscache["$dogroup"] AS $settingid => $setting)
	{

		if (($advanced OR !$setting['advanced']) AND !empty($setting['varname']))
		{
			print_description_row(
				iif($vbulletin->debug, '<div class="smallfont" style="float:' . $stylevar['right'] . '">' . construct_link_code($vbphrase['edit'], "options.php?" . $vbulletin->session->vars['sessionurl'] . "do=editsetting&varname=$setting[varname]") . construct_link_code($vbphrase['delete'], "options.php?" . $vbulletin->session->vars['sessionurl'] . "do=removesetting&varname=$setting[varname]") . '</div>') .
				'<div>' . $settingphrase["setting_$setting[varname]_title"] . "<a name=\"$setting[varname]\"></a></div>",
				0, 2, "optiontitle\" title=\"\$vbulletin->options['" . $setting['varname'] . "']"
			);

			// make sure all rows use the alt1 class
			$bgcounter--;

			$description = "<div class=\"smallfont\" title=\"\$vbulletin->options['$setting[varname]']\">" . $settingphrase["setting_$setting[varname]_desc"] . '</div>';
			$name = "setting[$setting[varname]]";
			$right = "<span class=\"smallfont\">$vbphrase[error]</span>";

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

				case 'magickfonts':
				{
					if (!empty($vbulletin->options['magickpath']))
					{
						require_once(DIR . '/includes/class_image.php');
						$image = new vB_Image_Magick($vbulletin);

						$fonts = $image->fetch_fonts();

						if (!empty($fonts))
						{
							print_select_row($description, $name, $fonts, $setting['value'], 1, 6);
						}
						else
						{
							$error = $image->fetch_error();
							print_label_row($description, "<span class=\"smallfont\"><b>$vbphrase[imagemagick_error]</b><br />" . htmlspecialchars_uni($error) . "</span>", '', 'top', 50);
						}
					}
					else
					{
						print_label_row($description, $vbphrase['specify_magick_path_above'], '', 'top', NULL, 50);
					}
				}
				break;

				case 'holidays':
				{
					require_once(DIR . '/includes/functions_calendar.php');
					$endtable = 0;
					foreach ($_CALENDARHOLIDAYS AS $holiday => $value)
					{

						$holidaytext .= iif(!$endtable, "<tr>\n");
						$checked = ($setting['value'] & $value) ? 'checked="checked"' : '';
						$holidaytext .= "<td><label for=\"hol$value\"><input id =\"hol$value\" type=\"checkbox\" name=\"{$name}[]\" value=\"$value\" $checked /><span class=\"smallfont\">$vbphrase[$holiday]</span></label></td>\n";
						$holidaytext .= iif($endtable, "</tr>\n");
						$endtable = iif($endtable, 0, 1);
					}
					print_label_row($description, '<table cellspacing="2" cellpadding="0" border="0">' . $holidaytext . '</tr></table>', '', 'top', NULL, 50);
					break;
				}

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

				// cookiepath / cookiedomain options
				case 'cookiepath':
				case 'cookiedomain':
				{
					$func = 'fetch_valid_' . $setting['optioncode'] . 's';

					$cookiesettings = $func(($setting['optioncode'] == 'cookiepath' ? $vbulletin->script : $_SERVER['HTTP_HOST']), $vbphrase['blank']);

					$setting['found'] = in_array($setting['value'], array_keys($cookiesettings));

					$setting['html'] = "
					<fieldset>
						<legend>$vbphrase[suggested_settings]</legend>
						<div style=\"padding:4px\">
							<select name=\"setting[$setting[varname]]\" tabindex=\"1\" class=\"bginput\">" .
								construct_select_options($cookiesettings, $setting['value']) . "
							</select>
						</div>
					</fieldset>
					<br />
					<fieldset>
						<legend>$vbphrase[custom_setting]</legend>
						<div style=\"padding:4px\">
							<label for=\"{$settingid}o\"><input type=\"checkbox\" id=\"{$settingid}o\" name=\"setting[{$settingid}_other]\" tabindex=\"1\" value=\"1\"" . ($setting['found'] ? '' : ' checked="checked"') . " />$vbphrase[use_custom_setting]
							</label><br />
							<input type=\"text\" class=\"bginput\" size=\"25\" name=\"setting[{$settingid}_value]\" value=\"" . ($setting['found'] ? '' : $setting['value']) . "\" />
						</div>
					</fieldset>";

					print_label_row($description, $setting['html'], '', 'top', NULL, 50);
				}
				break;

				// just a label
				default:
				{
					$handled = false;
					($hook = vBulletinHook::fetch_hook('admin_options_print')) ? eval($hook) : false;
					if (!$handled)
					{
						eval("\$right = \"$setting[optioncode]\";");
						print_label_row($description, $right, '', 'top', $name, 50);
					}
				}
				break;
			}

			//print_description_row("<h2 align=\"center\">" . ($setting['datatype'] ? $setting['datatype'] : 'free') . "</h2>");
		}
	}
}

/**
* Validates the provided value of a setting against its datatype
*
* @param	mixed	(ref) Setting value
* @param	string	Setting datatype ('number', 'boolean' or other)
*
* @return	mixed	Setting value
*/
function validate_setting_value(&$value, $datatype)
{
	switch ($datatype)
	{
		case 'number':
			$value += 0;
			break;
		case 'boolean':
			$value = $value ? 1 : 0;
			break;
		default:
			$value = trim($value);
	}

	return $value;
}

/**
* Returns a list of valid settings for $vbulletin->options['cookiedomain'] based on $_SERVER['HTTP_HOST']
*
* @param	string	$_SERVER['HTTP_HOST']
* @param	string	Phrase to use for blank option
*
* @return	array
*/
function fetch_valid_cookiedomains($http_host, $blank_phrase)
{
	$cookiedomains = array('' => $blank_phrase);
	$domain = $http_host;

	while (substr_count($domain, '.') > 1)
	{
		$dotpos = strpos($domain, '.');
		$newdomain = substr($domain, $dotpos);
		$cookiedomains["$newdomain"] = $newdomain;
		$domain = substr($domain, $dotpos + 1);
	}

	return $cookiedomains;
}

/**
* Returns a list of valid settings for $vbulletin->options['cookiepath'] based on $vbulletin->script
*
* @param	string	$vbulletin->script
*
* @return	array
*/
function fetch_valid_cookiepaths($script)
{
	$cookiepaths = array('/' => '/');
	$curpath = '/';

	$path = preg_split('#/#', substr($script, 0, strrpos($script, '/')), -1, PREG_SPLIT_NO_EMPTY);

	for ($i = 0; $i < sizeof($path) - 1; $i++)
	{
		$curpath .= "$path[$i]/";
		$cookiepaths["$curpath"] = $curpath;
	}

	return $cookiepaths;
}

/**
* Imports settings from an XML settings file
*
* Call as follows:
* $path = './path/to/install/vbulletin-settings.xml';
* xml_import_settings($xml);
*
* @param	mixed	Either XML string or boolean false to use $path global variable
*/
function xml_import_settings($xml = false)
{
	global $vbulletin, $vbphrase;

	print_dots_start('<b>' . $vbphrase['importing_settings'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	require_once(DIR . '/includes/class_xml.php');

	$xmlobj = new XMLparser($xml, $GLOBALS['path']);
	if ($xmlobj->error_no == 1)
	{
			print_dots_stop();
			print_stop_message('no_xml_and_no_path');
	}
	else if ($xmlobj->error_no == 2)
	{
			print_dots_stop();
			print_stop_message('please_ensure_x_file_is_located_at_y', 'vbulletin-settings.xml', $GLOBALS['path']);
	}

	if(!$arr = $xmlobj->parse())
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line());
	}

	if (!$arr['settinggroup'])
	{
		print_dots_stop();
		print_stop_message('invalid_file_specified');
	}

	$product = (empty($arr['product']) ? 'vbulletin' : $arr['product']);

	// delete old volatile settings and settings that might conflict with new ones...
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "settinggroup WHERE volatile = 1 AND (product = '" . $vbulletin->db->escape_string($product) . "'" . iif($product == 'vbulletin', " OR product = ''") . ')');
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "setting WHERE volatile = 1 AND (product = '" . $vbulletin->db->escape_string($product) . "'" . iif($product == 'vbulletin', " OR product = ''") . ')');

	// run through imported array
	if (!is_array($arr['settinggroup'][0]))
	{
		$arr['settinggroup'] = array($arr['settinggroup']);
	}
	foreach($arr['settinggroup'] AS $group)
	{
		// need check to make sure group product== xml product before inserting new settinggroup
		if (empty($group['product']) OR $group['product'] == $product)
		{
			// insert setting group
			/*insert query*/
			$vbulletin->db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "settinggroup
				(grouptitle, displayorder, volatile, product)
				VALUES
				('" . $vbulletin->db->escape_string($group['name']) . "', " . intval($group['displayorder']) . ", 1, '" . $vbulletin->db->escape_string($product) . "')
			");
		}

		// build insert query for this group's settings
		$qBits = array();
		if (!is_array($group['setting'][0]))
		{
			$group['setting'] = array($group['setting']);
		}
		foreach($group['setting'] AS $setting)
		{
			if (isset($vbulletin->options["$setting[varname]"]))
			{
				$newvalue = $vbulletin->options["$setting[varname]"];
			}
			else
			{
				$newvalue = $setting['defaultvalue'];
			}
			$qBits[] = "(
				'" . $vbulletin->db->escape_string($setting['varname']) . "',
				'" . $vbulletin->db->escape_string($group['name']) . "',
				'" . $vbulletin->db->escape_string(trim($newvalue)) . "',
				'" . $vbulletin->db->escape_string(trim($setting['defaultvalue'])) . "',
				'" . $vbulletin->db->escape_string(trim($setting['datatype'])) . "',
				'" . $vbulletin->db->escape_string($setting['optioncode']) . "',
				" . intval($setting['displayorder']) . ",
				" . intval($setting['advanced']) . ",
				1,
				'" . $vbulletin->db->escape_string($product) . "'\n\t)";
		}
		// run settings insert query
		/*insert query*/
		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "setting
			(varname, grouptitle, value, defaultvalue, datatype, optioncode, displayorder, advanced, volatile, product)
			VALUES
			" . implode(",\n\t", $qBits));
	}

	// rebuild the $vbulletin->options array
	build_options();

	// stop the 'dots' counter feedback
	print_dots_stop();

}

/**
* Fetches an array of style titles for use in select menus
*
* @param	string	Prefix for titles
* @param	boolean	Display top level style?
*
* @return	array
*/
function fetch_style_title_options_array($titleprefix = '', $displaytop = false)
{
	require_once(DIR . '/includes/adminfunctions_template.php');
	global $stylecache;

	cache_styles();
	$out = array();

	foreach($stylecache AS $style)
	{
		$out["$style[styleid]"] = $titleprefix . construct_depth_mark($style['depth'], '--', iif($displaytop, '--', '')) . " $style[title]";
	}

	return $out;
}

/**
* Fetches information about GD
*
* @return	array
*/
function fetch_gdinfo()
{
	$gdinfo = array();

	if (function_exists('gd_info'))
	{
		$gdinfo = gd_info();
	}
	else if (function_exists('phpinfo') AND function_exists('ob_start'))
	{
		if (@ob_start())
		{
			eval('@phpinfo();');
			$info = @ob_get_contents();
			@ob_end_clean();
			preg_match('/GD Version[^<]*<\/td><td[^>]*>(.*?)<\/td><\/tr>/si', $info, $hits);
			$gdinfo = array(
				'GD Version' => $hits[1]
			);
		}
	}

	if (empty($gdinfo['GD Version']))
	{
		$gdinfo['GD Version'] = $vbphrase['n_a'];
	}
	else
	{
		$gdinfo['version'] = preg_replace('#[^\d\.]#', '', $gdinfo['GD Version']);
	}

	return $gdinfo;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13693 $
|| ####################################################################
\*======================================================================*/
?>