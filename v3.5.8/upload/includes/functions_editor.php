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

// #############################################################################
/**
* Returns a width for a textarea depending on stylevar/browser combo
*
* @return	integer
*/
function fetch_textarea_width()
{
	// attempts to fix netscape textarea width problems
	global $stylevar;

	if (is_browser('ie'))
	{
		// browser is IE
		return $stylevar['textareacols_ie4'];
	}
	else if (is_browser('mozilla'))
	{
		// browser is NS >= 6.x / Mozilla >= 1.x
		return $stylevar['textareacols_ns6'];
	}
	else if (is_browser('netscape'))
	{
		// browser is NS4
		return $stylevar['textareacols_ns4'];
	}
	else
	{
		// unknown browser - stick in a sensible value
		return 60;
	}
}

// #############################################################################
/**
* Builds a Javascript line to add a new attachment to the vB_Attachments object
*
* Assumes that all data is cleaned and htmlspecialchars'd
*
* @param	integer	Attachment ID
* @param	string	File name (myattachment.gif etc.)
* @param	string	Filesize (124 KB etc.)
* @param	string	Extension type (gif, jpg etc.)
* @param	string	(Optional) Javascript prefix, such as 'window.opener.'
*
* @return	string
*/
function construct_attachment_add_js($attachmentid, $filename, $filesize, $extension, $prefix = '')
{
	global $stylevar;

	return $prefix . "vB_Attachments.add($attachmentid, '" . addslashes_js($filename) . "', '" . addslashes_js($filesize) . "', '$stylevar[imgdir_attach]/$extension.gif');\n";
}

// #############################################################################
/**
* Returns the javascript required for the editor control styles
*
* @param	array	(Optional) Editor styles array / serialized array
*
* @return	string
*/
function construct_editor_styles_js($editorstyles = false)
{
	// istyles - CSS in order: background / color / padding / border
	global $istyles;

	if (!is_array($istyles))
	{
		if (!$editorstyles)
		{
			$istyles = unserialize($GLOBALS['style']['editorstyles']);
		}
		else
		{
			$istyles = unserialize($editorstyles);
		}
	}

	$istyle = array();
	foreach ($istyles AS $key => $array)
	{
		$istyle[] = "\"$key\" : [ \"$array[0]\", \"$array[1]\", \"$array[2]\", \"$array[3]\" ]";
	}

	return implode(", ", $istyle);
}

// ###################### Start wysiwyg_compatible #######################
// returns 0, 1, 2 depending on permissions, options and browser compatability
function is_wysiwyg_compatible($userchoice = -1, $editormode = 'fe')
{
	global $vbulletin;

	// Netscape 4... don't even bother to check user choice as the toolbars won't work
	if (is_browser('netscape') OR is_browser('webtv'))
	{
		return 0;
	}

	// check for a standard setting
	if ($userchoice == -1)
	{
		$userchoice = $vbulletin->userinfo['showvbcode'];
	}

	// unserialize the option if we need to
	if (!is_array($vbulletin->options['editormodes_array']))
	{
		$vbulletin->options['editormodes_array'] = unserialize($vbulletin->options['editormodes']);
	}

	// make sure we have a valid editor mode to check
	switch ($editormode)
	{
		case 'fe':
		case 'qr':
		case 'qe':
			break;
		default:
			$editormode = 'fe';
	}

	// check board options for toolbar permissions
	if ($userchoice > $vbulletin->options['editormodes_array']["$editormode"])
	{
		$choice = $vbulletin->options['editormodes_array']["$editormode"];
	}
	else
	{
		$choice = $userchoice;
	}

	$hook_return = null;
	($hook = vBulletinHook::fetch_hook('editor_wysiwyg_compatible')) ? eval($hook) : false;
	if ($hook_return !== null)
	{
		return $hook_return;
	}

	if ($choice == 2) // attempting to use WYSIWYG, check that we really can
	{
		if (!is_browser('opera'))
		{
			// Check Mozilla Browsers
			if (is_browser('firebird', '0.6.1') OR is_browser('camino', '0.9') OR (is_browser('mozilla', '20030312') AND !is_browser('firebird') AND !is_browser('camino')))
			{
				return 2;
			}
			else if (is_browser('ie', '5.5') AND !is_browser('mac'))
			{
				return 2;
			}
			else
			{
				return 1;
			}
		}
		else
		{
			// browser is incompatible - return standard toolbar
			return 1;
		}
	}
	else
	{
		// return standard or no toolbar
		return $choice;
	}
}

/**
* Builds the javascript arrays used by the editor system
*
* @return	array
*/
function construct_editor_js_arrays()
{
	global $vbulletin;

	// extract the variables from the vbcode_font_options and vbcode_size_options templates
	foreach(array('editor_jsoptions_font', 'editor_jsoptions_size') AS $template)
	{
		eval('$string = "' . fetch_template($template, 1, 0) . '";');
		$$template = preg_split('#\r?\n#s', $string, -1, PREG_SPLIT_NO_EMPTY);
	}

	// get the javascript vars to drive the editor
	$vBeditJs = array(
		'font_options_array' => '"' . implode('", "', $editor_jsoptions_font) . '"',
		'size_options_array' => implode(", ", $editor_jsoptions_size),
		'istyle_array' => construct_editor_styles_js()
	);

	return $vBeditJs;
}

// ###################### Start buildEditToolbar #######################
// DOTOOLBAR: 0 = no toolbar, 1 = standard toolbar, 2 = wysiwyg toolbar
function construct_edit_toolbar($text = '', $ishtml = 0, $forumid = 0, $allowsmilie = 1, $parsesmilie = 1, $can_attach = false, $editor_type = 'fe', $force_editorid = '')
{
	// standard stuff
	global $vbulletin, $vbphrase, $stylevar, $show;
	// templates generated by this function
	global $messagearea, $smiliebox, $disablesmiliesoption, $checked, $vBeditTemplate;
	// misc stuff built by this function
	global $istyles;

	// counter for editorid
	static $editorcount = 0;

	// determine what we can use
	// this was moved up here as I need the switch to determine if bbcode is enabled
	// to determine if a toolbar is usable
	require_once(DIR . '/includes/class_bbcode.php');
	$show['font_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_FONT)  ? true : false;
	$show['size_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_SIZE)  ? true : false;
	$show['color_bbcode'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_COLOR) ? true : false;
	$show['basic_bbcode'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_BASIC) ? true : false;
	$show['align_bbcode'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_ALIGN) ? true : false;
	$show['list_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_LIST)  ? true : false;
	$show['code_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_CODE)  ? true : false;
	$show['html_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_HTML)  ? true : false;
	$show['php_bbcode']   = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_PHP)   ? true : false;
	$show['url_bbcode']   = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL)   ? true : false;

	if (empty($forumid))
	{
		$forumid = 'nonforum';
	}
	switch($forumid)
	{
		case 'privatemessage':
			$can_toolbar = $vbulletin->options['privallowbbcode'];
			$show['img_bbcode'] = $vbulletin->options['privallowbbimagecode'];
			break;

		case 'usernote':
			$can_toolbar = $vbulletin->options['unallowvbcode'];
			$show['img_bbcode'] = $vbulletin->options['unallowimg'];
			break;

		case 'calendar':
			global $calendarinfo;
			$can_toolbar = $calendarinfo['allowbbcode'];
			$show['img_bbcode'] = $calendarinfo['allowimgcode'];
			break;

		case 'nonforum':
			$can_toolbar = $vbulletin->options['allowbbcode'];
			$show['img_bbcode'] = $vbulletin->options['allowbbimagecode'];
			break;

		default:
			$forum = fetch_foruminfo($forumid);
			$can_toolbar = $forum['allowbbcode'];
			$show['img_bbcode'] = $forum['allowimages'];
			break;
	}

	// set the editor mode
	if (isset($_REQUEST['wysiwyg']))
	{
		// 2 = wysiwyg; 1 = standard
		if ($_REQUEST['wysiwyg'])
		{
			$vbulletin->userinfo['showvbcode'] = 2;
		}
		else if ($vbulletin->userinfo['showvbcode'] == 0)
		{
			$vbulletin->userinfo['showvbcode'] = 0;
		}
		else
		{
			$vbulletin->userinfo['showvbcode'] = 1;
		}
	}
	$toolbartype = $can_toolbar ? is_wysiwyg_compatible(-1, $editor_type) : 0;

	$show['wysiwyg_compatible'] = (is_wysiwyg_compatible(2, $editor_type) == 2);
	$show['editor_toolbar'] = ($toolbartype > 0);

	switch ($editor_type)
	{
		case 'qr':
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_QR';
			}
			else
			{
				$editorid = $force_editorid;
			}

			$editor_height = 100;

			$editor_template_name = 'showthread_quickreply';
			break;

		case 'qe':
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_QE';
			}
			else
			{
				$editorid = $force_editorid;
			}

			$editor_height = 100;

			$editor_template_name = 'postbit_quickedit';
			break;

		default:
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_' . str_pad(++$editorcount, 3, 0, STR_PAD_LEFT);
			}
			else
			{
				$editorid = $force_editorid;
			}

			// set the height of the editor based on the editor_height cookie if it exists
			$editor_height = $vbulletin->input->clean_gpc('c', 'editor_height', TYPE_UINT);
			$editor_height = ($editor_height > 100) ? $editor_height : 250;

			$editor_template_name = ($toolbartype ? 'editor_toolbar_on' : 'editor_toolbar_off');
			break;
	}

	// init the variables used by the templates built by this function
	$vBeditJs = array(
		'font_options_array'   => '',
		'size_options_array'   => '',
		'istyle_array'         => '',
		'normalmode'           => 'false'
	);
	$vBeditTemplate = array(
		'extrabuttons'         => '',
		'clientscript'         => '',
		'fontfeedback'         => '',
		'sizefeedback'         => '',
		'smiliepopup'          => ''
	);

	($hook = vBulletinHook::fetch_hook('editor_toolbar_start')) ? eval($hook) : false;

	// show a post editing toolbar of some sort
	if ($show['editor_toolbar'])
	{
		if ($can_attach)
		{
			$show['attach'] = true;
		}

		$vBeditJs = construct_editor_js_arrays();

		// get extra buttons... experimental at the moment
		$vBeditTemplate['extrabuttons'] = construct_editor_extra_buttons($editorid);

		if ($toolbartype == 2)
		{
			// got to parse the message to be displayed from bbcode into HTML
			if ($text)
			{
				require_once(DIR . '/includes/functions_wysiwyg.php');
				$newpost['message'] = parse_wysiwyg_html($text, $ishtml, $forumid, iif($allowsmilie AND $parsesmilie, 1, 0));
			}
			else
			{
				$newpost['message'] = '';
			}

			$newpost['message'] = htmlspecialchars_uni($newpost['message']);
		}
		else
		{
			$newpost['message'] = $text;
			// set mode based on cookie set by javascript
			/*$vbulletin->input->clean_gpc('c', COOKIE_PREFIX . 'vbcodemode', TYPE_INT);
			$modechecked[$vbulletin->GPC[COOKIE_PREFIX . 'vbcodemode']] = 'checked="checked"';*/
		}

	}
	else
	{
		// do not show a post editing toolbar
		$newpost['message'] = $text;
	}

	// disable smilies option and clickable smilie
	$show['smiliebox'] = false;
	$smiliebox = '';
	$disablesmiliesoption = '';

	if ($editor_type == 'qr')
	{
		// no smilies
	}
	else if ($allowsmilie AND $show['editor_toolbar'])
	{
		// deal with disable smilies option
		if (!isset($checked['disablesmilies']))
		{
			$vbulletin->input->clean_gpc('r', 'disablesmilies', TYPE_BOOL);
			$checked['disablesmilies'] = iif($vbulletin->GPC['disablesmilies'], 'checked="checked"');
		}
		eval('$disablesmiliesoption = "' . fetch_template('newpost_disablesmiliesoption') . '";');

		if ($toolbartype AND ($vbulletin->options['smtotal'] > 0 OR $vbulletin->options['wysiwyg_smtotal'] > 0))
		{
			// query smilies
			$smilies = $vbulletin->db->query_read("
				SELECT smilieid, smilietext, smiliepath, smilie.title,
					imagecategory.title AS category
				FROM " . TABLE_PREFIX . "smilie AS smilie
				LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
				ORDER BY imagecategory.displayorder, imagecategory.title, smilie.displayorder
			");

			// get total number of smilies
			$totalsmilies = $vbulletin->db->num_rows($smilies);

			if ($totalsmilies > 0)
			{
				if ($vbulletin->options['wysiwyg_smtotal'] > 0)
				{
					$show['wysiwygsmilies'] = true;

					// smilie dropdown menu
					$vBeditJs['smilie_options_array'] = array();
					$i = 0;
					while ($smilie = $vbulletin->db->fetch_array($smilies))
					{
						if ($i++ < $vbulletin->options['wysiwyg_smtotal'])
						{
							$vBeditJs['smilie_options_array']["$smilie[category]"][] = "\t\t'$smilie[smilieid]' : new Array('" . addslashes_js($smilie['smiliepath']) . "', '" . addslashes_js($smilie['smilietext']) . "', '" . addslashes_js($smilie['title']) . "')";
						}
						else
						{
							$vBeditJs['smilie_options_array']["$smilie[category]"][] = "\t\t'more' : '" . addslashes_js($vbphrase['show_all_smilies']) . "'\n";
							break;
						}
					}
					foreach (array_keys($vBeditJs['smilie_options_array']) AS $category)
					{
						$vBeditJs['smilie_options_array']["$category"] = "\t'" . addslashes_js($category) . "' : {\n" . implode(",\n", $vBeditJs['smilie_options_array']["$category"]) . "}";
					}
					$vBeditJs['smilie_options_array'] = "\n" . implode(",\n", $vBeditJs['smilie_options_array']);
				}
				else
				{
					$show['wysiwygsmilies'] = false;
				}

				// clickable smilie box
				if ($vbulletin->options['smtotal'])
				{
					$vbulletin->db->data_seek($smilies, 0);
					$i = 0;
					$bits = array();
					while ($smilie = $vbulletin->db->fetch_array($smilies) AND $i++ < $vbulletin->options['smtotal'])
					{
						$smiliehtml = "<img src=\"$smilie[smiliepath]\" id=\"{$editorid}_smilie_$smilie[smilieid]\" alt=\"" . htmlspecialchars_uni($smilie['smilietext']) . "\" title=\"$smilie[title]\" border=\"0\" />";
						eval('$bits[] = "' . fetch_template('editor_smilie') . '";');

						if (sizeof($bits) == $vbulletin->options['smcolumns'])
						{
							$smiliecells = implode('', $bits);
							eval('$smiliebits .= "' . fetch_template('editor_smiliebox_row') . '";');
							$bits = array();
						}
					}

					// fill in empty cells if required
					$remaining = sizeof($bits);
					if ($remaining > 0)
					{
						$remainingcolumns = $vbulletin->options['smcolumns'] - $remaining;
						eval('$bits[] = "' . fetch_template('editor_smiliebox_straggler') . '";');
						$smiliecells = implode('', $bits);
						eval('$smiliebits .= "' . fetch_template('editor_smiliebox_row') . '";');
					}
					$show['moresmilieslink'] = iif ($totalsmilies > $vbulletin->options['smtotal'], true, false);
					$show['smiliebox'] = true;
				}

				$vbulletin->db->free_result($smilies);
			}
		}
		eval('$smiliebox = "' . fetch_template('editor_smiliebox') . '";');
	}

	($hook = vBulletinHook::fetch_hook('editor_toolbar_end')) ? eval($hook) : false;

	eval('$vBeditTemplate[\'clientscript\'] = "' . fetch_template('editor_clientscript') . '";');

	$editortype = ($toolbartype == 2 ? 1 : 0);
	$show['is_wysiwyg_editor'] = intval($editortype);
	eval('$messagearea = "' . fetch_template($editor_template_name) . '";');

	return $editorid;
}

// #################### Start fetch editor extra buttons ##########################
// returns the extra buttons as defined by the bbcode editor
function construct_editor_extra_buttons($editorid)
{
	global $vbphrase, $vbulletin;

	$extrabuttons = '';

	foreach ($vbulletin->bbcodecache AS $bbcode)
	{
		if ($bbcode['buttonimage'] != '')
		{
			$tag = strtoupper($bbcode['bbcodetag']);

			$alt = construct_phrase($vbphrase['wrap_x_tags'], $tag);

			$extrabuttons .= "<td><div class=\"imagebutton\" id=\"{$editorid}_cmd_wrap$bbcode[twoparams]_$bbcode[bbcodetag]\"><img src=\"$bbcode[buttonimage]\" alt=\"$alt\" width=\"21\" height=\"20\" border=\"0\" /></div></td>\n";
		}
	}

	return $extrabuttons;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13382 $
|| ####################################################################
\*======================================================================*/
?>