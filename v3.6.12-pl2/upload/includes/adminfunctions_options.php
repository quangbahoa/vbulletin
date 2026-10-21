<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
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

	print_column_style_code(array('width:45%', 'width:55%'));

	echo "<thead>\r\n";

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
	
	echo "</thead>\r\n";

	$bgcounter = 1;

	foreach ($settingscache["$dogroup"] AS $settingid => $setting)
	{
		if (($advanced OR !$setting['advanced']) AND !empty($setting['varname']))
		{
			echo '<tbody>';
			print_description_row(
				iif($vbulletin->debug, '<div class="smallfont" style="float:' . $stylevar['right'] . '">' . construct_link_code($vbphrase['edit'], "options.php?" . $vbulletin->session->vars['sessionurl'] . "do=editsetting&amp;varname=$setting[varname]") . construct_link_code($vbphrase['delete'], "options.php?" . $vbulletin->session->vars['sessionurl'] . "do=removesetting&amp;varname=$setting[varname]") . '</div>') .
				'<div>' . $settingphrase["setting_$setting[varname]_title"] . "<a name=\"$setting[varname]\"></a></div>",
				0, 2, "optiontitle\" title=\"\$vbulletin->options['" . $setting['varname'] . "']"
			);
			echo "</tbody><tbody id=\"tbody_$settingid\">\r\n";

			// make sure all rows use the alt1 class
			$bgcounter--;

			$description = "<div class=\"smallfont\" title=\"\$vbulletin->options['$setting[varname]']\">" . $settingphrase["setting_$setting[varname]_desc"] . '</div>';
			$name = "setting[$setting[varname]]";
			$right = "<span class=\"smallfont\">$vbphrase[error]</span>";
			$width = 40;
			$rows = 8;

			if (preg_match('#^input:?(\d+)$#s', $setting['optioncode'], $matches))
			{
				$width = $matches[1];
				$setting['optioncode'] = '';
			}
			else if (preg_match('#^textarea:?(\d+)(,(\d+))?$#s', $setting['optioncode'], $matches))
			{
				$rows = $matches[1];
				if ($matches[2])
				{
					$width = $matches[3];
				}
				$setting['optioncode'] = 'textarea';
			}
			else if (preg_match('#^bitfield:(.*)$#siU', $setting['optioncode'], $matches))
			{
				$setting['optioncode'] = 'bitfield';
				$setting['bitfield'] =& fetch_bitfield_definitions($matches[1]);
			}
			else if (preg_match('#^(select|radio):(piped|eval)(\r\n|\n|\r)(.*)$#siU', $setting['optioncode'], $matches))
			{
				$setting['optioncode'] = "$matches[1]:$matches[2]";
				$setting['optiondata'] = trim($matches[4]);
			}
			else if (preg_match('#^usergroup:?(\d+)$#s', $setting['optioncode'], $matches))
			{
				$size = intval($matches[1]);
				$setting['optioncode'] = 'usergroup';
			}

			switch ($setting['optioncode'])
			{
				// input type="text"
				case '':
				{
					print_input_row($description, $name, $setting['value'], 1, $width);
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
					print_textarea_row($description, $name, $setting['value'], $rows, "$width\" style=\"width:90%");
				}
				break;

				// bitfield
				case 'bitfield':
				{
					$setting['value'] = intval($setting['value']);
					$setting['html'] = '';

					if ($setting['bitfield'] === NULL)
					{
						print_label_row($description, construct_phrase("<strong>$vbphrase[settings_bitfield_error]</strong>", implode(',', vB_Bitfield_Builder::fetch_errors())), '', 'top', $name, 40);
					}
					else
					{
						#$setting['html'] .= "<fieldset><legend>$vbphrase[yes] / $vbphrase[no]</legend>";
						$setting['html'] .= "<div id=\"ctrl_setting[$setting[varname]]\" class=\"smallfont\">\r\n";
						$setting['html'] .= "<input type=\"hidden\" name=\"setting[$setting[varname]][0]\" value=\"0\" />\r\n";
						foreach ($setting['bitfield'] AS $key => $value)
						{
							$value = intval($value);
							$setting['html'] .= "<table style=\"width:175px; float:$stylevar[left]\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr valign=\"top\">
							<td><input type=\"checkbox\" name=\"setting[$setting[varname]][$value]\" id=\"setting[$setting[varname]]_$key\" value=\"$value\"" . (($setting['value'] & $value) ? ' checked="checked"' : '') . " /></td>
							<td width=\"100%\" style=\"padding-top:4px\"><label for=\"setting[$setting[varname]]_$key\" class=\"smallfont\">" . fetch_phrase_from_key($key) . "</label></td>\r\n</tr></table>\r\n";
						}
						
						$setting['html'] .= "</div>\r\n";
						#$setting['html'] .= "</fieldset>";
						print_label_row($description, $setting['html'], '', 'top', $name, 40);
					}
				}
				break;

				// select:piped
				case 'select:piped':
				{
					print_select_row($description, $name, fetch_piped_options($setting['optiondata']), $setting['value']);
				}
				break;

				// radio:piped
				case 'radio:piped':
				{
					print_radio_row($description, $name, fetch_piped_options($setting['optiondata']), $setting['value'], 'smallfont');
				}
				break;

				// select:eval
				case 'select:eval':
				{
					$options = null;

					eval($setting['optiondata']);

					if (is_array($options) AND !empty($options))
					{
						print_select_row($description, $name, $options, $setting['value']);
					}
					else
					{
						print_input_row($description, $name, $setting['value']);
					}
				}
				break;

				// radio:eval
				case 'radio:eval':
				{
					$options = null;

					eval($setting['optiondata']);

					if (is_array($options) AND !empty($options))
					{
						print_radio_row($description, $name, $options, $setting['value'], 'smallfont');
					}
					else
					{
						print_input_row($description, $name, $setting['value']);
					}
				}
				break;

				case 'username':
				{
					if (intval($setting['value']) AND $userinfo = $vbulletin->db->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = " . intval($setting['value'])))
					{
						print_input_row($description, $name, $userinfo['username'], false);
					}
					else
					{
						print_input_row($description, $name);
					}
					break;
				}

				case 'usergroup':
				{
					$usergrouplist = array();
					foreach ($vbulletin->usergroupcache AS $usergroup)
					{
						$usergrouplist["$usergroup[usergroupid]"] = $usergroup['title'];
					}

					if ($size > 1)
					{
						print_select_row($description, $name . '[]', array(0 => '') + $usergrouplist, unserialize($setting['value']), false, $size, true);
					}
					else
					{
						print_select_row($description, $name, $usergrouplist, $setting['value']);
					}
					break;
				}

				// arbitrary number of <input type="text" />
				case 'multiinput':
				{
					$setting['html'] = "<div id=\"ctrl_$setting[varname]\"><fieldset id=\"multi_input_fieldset_$setting[varname]\" style=\"padding:4px\">";

					$setting['values'] = unserialize($setting['value']);
					$setting['values'] = (is_array($setting['values']) ? $setting['values'] : array());
					$setting['values'][] = '';

					foreach ($setting['values'] AS $key => $value)
					{
						$setting['html'] .= "<div id=\"multi_input_container_$setting[varname]_$key\">" . ($key + 1) . " <input type=\"text\" class=\"bginput\" name=\"setting[$setting[varname]][$key]\" id=\"multi_input_$setting[varname]_$key\" size=\"40\" value=\"" . htmlspecialchars_uni($value) . "\" tabindex=\"1\" /></div>";
					}

					$i = sizeof($setting['values']);
					if ($i == 0)
					{
						$setting['html'] .= "<div><input type=\"text\" class=\"bginput\" name=\"setting[$setting[varname]][$i]\" size=\"40\" tabindex=\"1\" /></div>";
					}

					$setting['html'] .= "
						</fieldset>
						<div class=\"smallfont\"><a href=\"#\" onclick=\"return multi_input['$setting[varname]'].add()\">Add Another Option</a></div>
						<script type=\"text/javascript\">
						<!--
						multi_input['$setting[varname]'] = new vB_Multi_Input('$setting[varname]', $i, '" . $vbulletin->options['cpstylefolder'] . "');
						//-->
						</script>
					";

					print_label_row($description, $setting['html']);
					break;
				}

				// default registration options
				case 'defaultregoptions':
				{
					$setting['value'] = intval($setting['value']);

					$checkbox_options = array(
						'receiveemail' => 'display_email',
						'adminemail' => 'receive_admin_emails',
						'invisiblemode' => 'invisible_mode',
						'vcard' => 'allow_vcard_download',
						'signature' => 'display_signatures',
						'avatar' => 'display_avatars',
						'image' => 'display_images',
						'showreputation' => 'display_reputation',
						'enablepm' => 'receive_private_messages',
						'emailonpm' => 'send_notification_email_when_a_private_message_is_received',
						'pmpopup' => 'pop_up_notification_box_when_a_private_message_is_received',
					);

					$setting['value'] = intval($setting['value']);

					$setting['html'] = '';
					#$setting['html'] .= "<fieldset><legend>$vbphrase[yes] / $vbphrase[no]</legend>";
					$setting['html'] .= "<div id=\"ctrl_setting[$setting[varname]]\" class=\"smallfont\">\r\n";
					$setting['html'] .= "<input type=\"hidden\" name=\"setting[$setting[varname]][0]\" value=\"0\" />\r\n";
					foreach ($checkbox_options AS $key => $phrase)
					{
						$value = $vbulletin->bf_misc_regoptions["$key"];

						$setting['html'] .= "<table style=\"width:175px; float:$stylevar[left]\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr valign=\"top\">
						<td><input type=\"checkbox\" name=\"setting[$setting[varname]][$value]\" id=\"setting[$setting[varname]]_$key\" value=\"$value\"" . (($setting['value'] & $value) ? ' checked="checked"' : '') . " /></td>
						<td width=\"100%\" style=\"padding-top:4px\"><label for=\"setting[$setting[varname]]_$key\" class=\"smallfont\">" . fetch_phrase_from_key($phrase) . "</label></td>\r\n</tr></table>\r\n";
					}
					#$setting['html'] .= "</fieldset>";
					print_label_row($description, $setting['html'], '', 'top', $name, 40);
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

				// cookiepath / cookiedomain options
				case 'cookiepath':
				case 'cookiedomain':
				{
					$func = 'fetch_valid_' . $setting['optioncode'] . 's';

					$cookiesettings = $func(($setting['optioncode'] == 'cookiepath' ? $vbulletin->script : $_SERVER['HTTP_HOST']), $vbphrase['blank']);

					$setting['found'] = in_array($setting['value'], array_keys($cookiesettings));

					$setting['html'] = "
					<div id=\"ctrl_$setting[varname]\">
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
					</fieldset>
					</div>";

					print_label_row($description, $setting['html'], '', 'top', $name, 50);
				}
				break;

				// just a label
				default:
				{
					$handled = false;
					($hook = vBulletinHook::fetch_hook('admin_options_print')) ? eval($hook) : false;
					if (!$handled)
					{
						eval("\$right = \"<div id=\\\"ctrl_setting[$setting[varname]]\\\">$setting[optioncode]</div>\";");
						print_label_row($description, $right, '', 'top', $name, 50);
					}
				}
				break;
			}

			echo "</tbody>\r\n";

			$valid = exec_setting_validation_code($setting['varname'], $setting['value'], $setting['validationcode']);

			echo "<tbody id=\"tbody_error_$settingid\" style=\"display:" . (($valid === 1 OR $valid === true) ? 'none' : '') . "\"><tr><td class=\"alt1 smallfont\" colspan=\"2\"><div style=\"padding:4px; border:solid 1px red; background-color:white; color:black\"><strong>$vbphrase[error]</strong>:<div id=\"span_error_$settingid\">$valid</div></div></td></tr></tbody>";

			//print_description_row("<h2 align=\"center\">" . ($setting['datatype'] ? $setting['datatype'] : 'free') . "</h2>");
		}
	}
}

/**
* Attempts to run validation code on a setting
*
* @param	string	Setting varname
* @param	mixed	Setting value
* @param	string	Setting validation code
*
* @return	mixed
*/
function exec_setting_validation_code($varname, $value, $validation_code)
{
	if ($validation_code != '')
	{
		$validation_function = create_function('&$data', $validation_code);
		$validation_result = $validation_function($value);

		if ($validation_result === false OR $validation_result === null)
		{
			$valid = fetch_error("setting_validation_error_$varname");
			if (preg_match('#^Could#i', $valid) AND preg_match("#'" . preg_quote("setting_validation_error_$varname", '#') . "'#i", $valid))
			{
				$valid = fetch_error("you_did_not_enter_a_valid_value");
			}
			return $valid;
		}
		else
		{
			return $validation_result;
		}
	}

	return 1;
}

/**
* Validates the provided value of a setting against its datatype
*
* @param	mixed	(ref) Setting value
* @param	string	Setting datatype ('number', 'boolean' or other)
* @param	boolean	Represent boolean with 1/0 instead of true/false
* @param boolean  Query database for username type
*
* @return	mixed	Setting value
*/
function validate_setting_value(&$value, $datatype, $bool_as_int = true, $username_query = true)
{
	global $vbulletin;

	switch ($datatype)
	{
		case 'number':
			$value += 0;
			break;

		case 'boolean':
			$value = ($bool_as_int ? ($value ? 1 : 0) : ($value ? true : false));
			break;

		case 'bitfield':
			if (is_array($value))
			{
				$bitfield = 0;
				foreach ($value AS $bitval)
				{
					$bitfield += $bitval;
				}
				$value = $bitfield;
			}
			else
			{
				$value += 0;
			}
			break;

		case 'username':
			$value = trim($value);
			if ($username_query)
			{
				if (empty($value))
				{
					$value =  0;
				}
				else if ($userinfo = $vbulletin->db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . $vbulletin->db->escape_string(htmlspecialchars($value)) . "'"))
				{
					$value = $userinfo['userid'];
				}
				else
				{
					$value = false;
				}
			}
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

	$xmlobj = new vB_XML_Parser($xml, $GLOBALS['path']);
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
				1" . (!defined('UPGRADE_COMPAT') ? ",
					'" . $vbulletin->db->escape_string($setting['validationcode']) . "',
					" . intval($setting['blacklist']) . ",
					'" . $vbulletin->db->escape_string($product) . "'" : '') . "\n\t)";
		}
		// run settings insert query
		/*insert query*/
		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "setting
			(varname, grouptitle, value, defaultvalue, datatype, optioncode, displayorder,
			advanced, volatile" . (!defined('UPGRADE_COMPAT') ? ', validationcode, blacklist, product' : '') . ")
			VALUES
			" . implode(",\n\t", $qBits));
	}

	// rebuild the $vbulletin->options array
	build_options();

	// stop the 'dots' counter feedback
	print_dots_stop();

}

/**
* Restores a settings backup from an XML file
*
* Call as follows:
* $path = './path/to/install/vbulletin-settings.xml';
* xml_import_settings($xml);
*
* @param	mixed	Either XML string or boolean false to use $path global variable
* @param bool	Ignore blacklisted settings
*/
function xml_restore_settings($xml = false, $blacklist = true)
{
	global $vbulletin, $vbphrase;
	$newsettings = array();

	print_dots_start('<b>' . $vbphrase['importing_settings'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	require_once(DIR . '/includes/class_xml.php');

	$xmlobj = new vB_XML_Parser($xml, $GLOBALS['path']);
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

	if(!$newsettings = $xmlobj->parse())
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line());
	}

	if (!$newsettings['setting'])
	{
		print_dots_stop();
		print_stop_message('invalid_file_specified');
	}

	$product = (empty($newsettings['product']) ? 'vbulletin' : $newsettings['product']);

	foreach($newsettings['setting'] AS $setting)
	{
		// Loop to update all the settings
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "setting
			SET value='" . $vbulletin->db->escape_string($setting['value']) . "'
			WHERE varname ='" . $vbulletin->db->escape_string($setting['varname']) . "'
				AND product ='" . $vbulletin->db->escape_string($product) . "'
				" . ($blacklist ? "AND blacklist = 0" : "") . "
		");

	}

	unset($newsettings);

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
			preg_match('/GD Version[^<]*<\/td><td[^>]*>(.*?)<\/td><\/tr>/si', $info, $version);
			preg_match('/FreeType Linkage[^<]*<\/td><td[^>]*>(.*?)<\/td><\/tr>/si', $info, $freetype);
			$gdinfo = array(
				'GD Version' => $version[1],
				'FreeType Linkage'   => $freetype[1],
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

	if (preg_match('#with (unknown|freetype|TTF)( library)?#si', trim($gdinfo['FreeType Linkage']), $freetype))
	{
		$gdinfo['freetype'] = $freetype[1];
	}

	return $gdinfo;
}

/**
* Fetches an array describing the bits in the requested bitfield
*
* @param	string	Represents the array key required... use x|y|z to fetch ['x']['y']['z']
*
* @return	array	Reference to the requested array from includes/xml/bitfield_{product}.xml
*/
function &fetch_bitfield_definitions($string)
{
	static $bitfields = null;

	if ($bitfields === null)
	{
		require_once(DIR . '/includes/class_bitfield_builder.php');
		$bitfields = vB_Bitfield_Builder::return_data();
	}

	$keys = "['" . implode("']['", preg_split('#\|#si', $string, -1, PREG_SPLIT_NO_EMPTY)) . "']";

	eval('$return =& $bitfields' . $keys . ';');

	return $return;
}

/**
* Attempts to fetch the text of a phrase from the given key.
* If the phrase is not found, the key is returned.
*
* @param	string	Phrase key
*
* @return	string
*/
function fetch_phrase_from_key($phrase_key)
{
	global $vbphrase;

	return (isset($vbphrase["$phrase_key"])) ? $vbphrase["$phrase_key"] : $phrase_key;
}

/**
* Returns an array of options and phrase values from a piped list
* such as 0|no\n1|yes\n2|maybe
*
* @param	string	Piped data
*
* @return	array
*/
function fetch_piped_options($piped_data)
{
	$options = array();

	$option_lines = preg_split("#(\r\n|\n|\r)#s", $piped_data, -1, PREG_SPLIT_NO_EMPTY);
	foreach ($option_lines AS $option)
	{
		if (preg_match('#^([^\|]+)\|(.+)$#siU', $option, $option_match))
		{
			$option_text = explode('(,)', $option_match[2]);
			foreach (array_keys($option_text) AS $idx)
			{
				$option_text["$idx"] = fetch_phrase_from_key(trim($option_text["$idx"]));
			}
			$options["$option_match[1]"] = implode(', ', $option_text);
		}
	}

	return $options;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 17557 $
|| ####################################################################
\*======================================================================*/
?>
