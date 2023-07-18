<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
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
			$istyles = vb_unserialize($GLOBALS['style']['editorstyles']);
		}
		else
		{
			$istyles = vb_unserialize($editorstyles);
		}
	}

	if (!is_array($istyles))
	{
		return '';
	}

	$istyle = array();
	foreach ($istyles AS $key => $array)
	{
		$istyle[] = "\"$key\" : [ \"$array[0]\", \"$array[1]\", \"$array[2]\", \"$array[3]\" ]";
	}

	return implode(", ", $istyle);
}

// #############################################################################
/**
* Returns the maximum compatible editor mode depending on permissions, options and browser
*
* @param	integer	The requested editor mode (-1 = user default, 0 = simple textarea, 1 = standard editor controls, 2 = wysiwyg controls)
* @param	string	Editor type (full = 'fe', quick reply = 'qr')
*
* @return	integer	The maximum possible mode (0, 1, 2)
*/
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
	if (empty($vbulletin->options['editormodes_array']) OR !is_array($vbulletin->options['editormodes_array']))
	{
		$vbulletin->options['editormodes_array'] = vb_unserialize($vbulletin->options['editormodes']);
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
		if (!is_browser('opera') OR is_browser('opera', '9.0'))
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
			else if (false AND is_browser('opera', '9.0'))
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

// #############################################################################
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
		$string = fetch_template($template, 1, 0);
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

// #############################################################################
/**
* Prepares the templates for a message editor
*
* @param	string	The text to be initially loaded into the editor
* @param	boolean	Is the initial text HTML (rather than plain text or bbcode)?
* @param	mixed	Forum ID of the forum into which we are posting. Special rules apply for values of 'privatemessage', 'usernote', 'calendar', 'announcement' and 'nonforum'
* @param	boolean	Allow smilies?
* @param	boolean	Parse smilies in the text of the message?
* @param	boolean	Allow attachments?
* @param	string	Editor type - either 'fe' for full editor or 'qr' for quick reply
* @param	string	Force the editor to use the specified value as its editorid, rather than making one up
*
* @return	string	Editor ID
*/
function construct_edit_toolbar($text = '', $ishtml = false, $forumid = 0, $allowsmilie = true, $parsesmilie = true, $can_attach = false, $editor_type = 'fe', $force_editorid = '')
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
	if ($forumid == 'signature')
	{
		$sig_perms =& $vbulletin->userinfo['permissions']['signaturepermissions'];
		$sig_perms_bits =& $vbulletin->bf_ugp_signaturepermissions;

		$can_toolbar = ($sig_perms & $sig_perms_bits['canbbcode']) ? true : false;

		$show['img_bbcode']   = ($sig_perms & $sig_perms_bits['allowimg']) ? true : false;
		$show['font_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodefont'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_FONT) ? true : false;
		$show['size_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodesize'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_SIZE) ? true : false;
		$show['color_bbcode'] = ($sig_perms & $sig_perms_bits['canbbcodecolor'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_COLOR) ? true : false;
		$show['basic_bbcode'] = ($sig_perms & $sig_perms_bits['canbbcodebasic'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_BASIC) ? true : false;
		$show['align_bbcode'] = ($sig_perms & $sig_perms_bits['canbbcodealign'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_ALIGN) ? true : false;
		$show['list_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodelist'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_LIST) ? true : false;
		$show['code_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodecode'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_CODE) ? true : false;
		$show['html_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodehtml'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_HTML) ? true : false;
		$show['php_bbcode']   = ($sig_perms & $sig_perms_bits['canbbcodephp'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_PHP) ? true : false;
		$show['url_bbcode']   = ($sig_perms & $sig_perms_bits['canbbcodelink'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) ? true : false;
		$show['quote_bbcode'] = ($sig_perms & $sig_perms_bits['canbbcodequote']) ? true : false;
	}
	else
	{
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
		$show['quote_bbcode'] = true; // can't disable this anywhere but in sigs
	}

	$ajax_extra = '';

	$allow_custom_bbcode = true;

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
			$ajax_extra = "calendarid=$calendarinfo[calendarid]";
			break;

		case 'announcement':
			$can_toolbar = true;
			$show['img_bbcode'] = true;
			break;

		case 'signature':
			// see above -- these are handled earlier
			break;

		case 'visitormessage':
		case 'groupmessage':
		case 'picturecomment':
		{
			switch($forumid)
			{
				case 'groupmessage':
					$allowedoption = $vbulletin->options['sg_allowed_bbcode'];
				break;

				case 'picturecomment':
					$allowedoption = $vbulletin->options['pc_allowed_bbcode'];
				break;

				default:
					$allowedoption = $vbulletin->options['vm_allowed_bbcode'];
				break;
			}

			$show['font_bbcode']  = ($show['font_bbcode']  AND $allowedoption & ALLOW_BBCODE_FONT)  ? true : false;
			$show['size_bbcode']  = ($show['size_bbcode']  AND $allowedoption & ALLOW_BBCODE_SIZE)  ? true : false;
			$show['color_bbcode'] = ($show['color_bbcode'] AND $allowedoption & ALLOW_BBCODE_COLOR) ? true : false;
			$show['basic_bbcode'] = ($show['basic_bbcode'] AND $allowedoption & ALLOW_BBCODE_BASIC) ? true : false;
			$show['align_bbcode'] = ($show['align_bbcode'] AND $allowedoption & ALLOW_BBCODE_ALIGN) ? true : false;
			$show['list_bbcode']  = ($show['list_bbcode']  AND $allowedoption & ALLOW_BBCODE_LIST)  ? true : false;
			$show['code_bbcode']  = ($show['code_bbcode']  AND $allowedoption & ALLOW_BBCODE_CODE)  ? true : false;
			$show['html_bbcode']  = ($show['html_bbcode']  AND $allowedoption & ALLOW_BBCODE_HTML)  ? true : false;
			$show['php_bbcode']   = ($show['php_bbcode']   AND $allowedoption & ALLOW_BBCODE_PHP)   ? true : false;
			$show['url_bbcode']   = ($show['url_bbcode']   AND $allowedoption & ALLOW_BBCODE_URL)   ? true : false;
			$show['quote_bbcode'] = ($show['quote_bbcode'] AND $allowedoption & ALLOW_BBCODE_QUOTE) ? true : false;
			$show['img_bbcode']   = ($allowedoption & ALLOW_BBCODE_IMG) ? true : false;

			$can_toolbar = (
				$show['font_bbcode'] OR $show['size_bbcode'] OR $show['color_bbcode'] OR
				$show['basic_bbcode'] OR $show['align_bbcode'] OR $show['list_bbcode'] OR
				$show['code_bbcode'] OR $show['html_bbcode'] OR $show['php_bbcode'] OR
				$show['url_bbcode'] OR $show['quote_bbcode'] OR $show['img_bbcode']
			);

			$allow_custom_bbcode = ($allowedoption & ALLOW_BBCODE_CUSTOM ? true : false);
		}
		break;

		case 'nonforum':
			$can_toolbar = $vbulletin->options['allowbbcode'];
			$show['img_bbcode'] = $vbulletin->options['allowbbimagecode'];
			break;

		default:
			if (intval($forumid))
			{
				$forum = fetch_foruminfo($forumid);
				$can_toolbar = $forum['allowbbcode'];
				$show['img_bbcode'] = $forum['allowimages'];
			}
			else
			{
				$can_toolbar = false;
				$show['img_bbcode'] = false;
			}

			($hook = vBulletinHook::fetch_hook('editor_toolbar_switch')) ? eval($hook) : false;
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

		case 'qr_small':
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_QR';
			}
			else
			{
				$editorid = $force_editorid;
			}

			$editor_height = 60;

			$editor_template_name = 'showthread_quickreply';
			break;

		case 'qr_pm':
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_QR';
			}
			else
			{
				$editorid = $force_editorid;
			}

			$editor_height = 120;

			$editor_template_name = 'pm_quickreply';
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

			$editor_height = 200;

			$editor_template_name = 'postbit_quickedit';
			break;

		case 'qenr':
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_QE';
			}
			else
			{
				$editorid = $force_editorid;
			}

			$editor_height = 200;

			$editor_template_name = 'memberinfo_quickedit';
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
		'font_options_array' => '',
		'size_options_array' => '',
		'istyle_array'       => '',
		'normalmode'         => 'false'
	);
	$vBeditTemplate = array(
		'extrabuttons'       => '',
		'clientscript'       => '',
		'fontfeedback'       => '',
		'sizefeedback'       => '',
		'smiliepopup'        => ''
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
		$vBeditTemplate['extrabuttons'] = construct_editor_extra_buttons($editorid, $allow_custom_bbcode);

		if ($toolbartype == 2)
		{
			// got to parse the message to be displayed from bbcode into HTML
			if ($text !== '')
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

	if ($editor_type == 'qr' OR $editor_type == 'qr_small')
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
			$smilies = $vbulletin->db->query_read_slave("
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
						if (empty($prevcategory))
						{
							$prevcategory = $smilie['category'];
						}
						if ($i++ < $vbulletin->options['wysiwyg_smtotal'])
						{
							$vBeditJs['smilie_options_array']["$smilie[category]"][] = "\t\t'$smilie[smilieid]' : new Array('" . addslashes_js($smilie['smiliepath']) . "', '" . addslashes_js($smilie['smilietext']) . "', '" . addslashes_js($smilie['title']) . "')";
						}
						else
						{
							$vBeditJs['smilie_options_array']["$prevcategory"][] = "\t\t'more' : '" . addslashes_js($vbphrase['show_all_smilies']) . "'\n";
							break;
						}
						$prevcategory = $smilie['category'];
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
					$smiliebits = '';
					while ($smilie = $vbulletin->db->fetch_array($smilies) AND $i++ < $vbulletin->options['smtotal'])
					{
						$smiliehtml = "<img src=\"$smilie[smiliepath]\" id=\"{$editorid}_smilie_$smilie[smilieid]\" alt=\"" . htmlspecialchars_uni($smilie['smilietext']) . "\" title=\"" . htmlspecialchars_uni($smilie['title']) . "\" border=\"0\" class=\"inlineimg\" />";
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

	// check that $editor_css has been built
	if (!isset($GLOBALS['editor_css']))
	{
		eval('$GLOBALS[\'editor_css\'] = "' . fetch_template('editor_css') . '";');
		$GLOBALS['headinclude'] .= "<!-- Editor CSS automatically added by " . substr(strrchr(__FILE__, DIRECTORY_SEPARATOR), 1) . " at line " . __LINE__ . " -->\n" . $GLOBALS['editor_css'];
	}

	eval('$vBeditTemplate[\'clientscript\'] = "' . fetch_template('editor_clientscript') . '";');

	$ajax_extra = addslashes_js($ajax_extra);
	$editortype = ($toolbartype == 2 ? 1 : 0);
	$show['is_wysiwyg_editor'] = intval($editortype);
	eval('$messagearea = "' . fetch_template($editor_template_name) . '";');

	return $editorid;
}

// #############################################################################
/**
* Returns the extra buttons as defined by the bbcode editor
*
* @param	string	ID of the editor of which these buttons will be a part
* @param 	boolean	Set to false to disable custom bbcode buttons
*
* @return	string	Extra buttons HTML
*/
function construct_editor_extra_buttons($editorid, $allow_custom_bbcode = true)
{
	global $vbphrase, $vbulletin;

	$extrabuttons = '';

	if ($allow_custom_bbcode)
	{
		foreach ($vbulletin->bbcodecache AS $bbcode)
		{
			if ($bbcode['buttonimage'] != '')
			{
				$tag = strtoupper($bbcode['bbcodetag']);

				$alt = construct_phrase($vbphrase['wrap_x_tags'], $tag);

				$extrabuttons .= "<td><div class=\"imagebutton\" id=\"{$editorid}_cmd_wrap$bbcode[twoparams]_$bbcode[bbcodetag]\"><img src=\"$bbcode[buttonimage]\" alt=\"$alt\" width=\"21\" height=\"20\" border=\"0\" /></div></td>\n";
			}
		}
	}

	return $extrabuttons;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
