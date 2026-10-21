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
define('CVS_REVISION', '$RCSfile$ - $Revision: 13174 $');
define('NO_REGISTER_GLOBALS', 1);
define('DEFAULT_FILENAME', 'vbulletin-language.xml');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('language');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_language.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminlanguages'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif(!empty($_REQUEST['languageid']), "Language ID = " . $_REQUEST['languageid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

globalize($_REQUEST, array('languageid' => INT));

$langglobals = array(
	'title' => STR_NOHTML,
	'userselect' => INT,
	'options',
	'languagecode' => STR,
	'charset' => STR,
	'locale' => STR,
	'imagesoverride' => STR,
	'dateoverride' => STR,
	'timeoverride' => STR,
	'registereddateoverride' => STR,
	'calformat1override' => STR,
	'calformat2override' => STR,
	'logdateoverride' => STR,
	'decimalsep' => STR,
	'thousandsep' => STR
);

// #############################################################################

if ($_REQUEST['do'] == 'download')
{
	globalize($_REQUEST, array(
		'filename' => STR,
		'languageid' => INT,
		'just_phrases',
	));

	if (empty($filename))
	{
		$filename = DEFAULT_FILENAME;
	}

	if (function_exists('set_time_limit') AND get_cfg_var('safe_mode')==0)
	{
		@set_time_limit(1200);
	}

	if ($languageid == -1)
	{
		$language['title'] = $vbphrase['master_language'];
	}
	else
	{
		$language = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "language WHERE languageid = $languageid");
	}

	$title = str_replace('"', '\"', $language['title']);
	$version = str_replace('"', '\"', $vboptions['templateversion']);

	$phrasetypes = fetch_phrasetypes_array(false);

	$phrases = array();
	$getphrases = $DB_site->query("
		SELECT varname, text, phrasetypeid
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid = $languageid OR languageid = 0
		ORDER BY languageid, phrasetypeid, varname
	");
	while ($getphrase = $DB_site->fetch_array($getphrases))
	{
		$phrases["$getphrase[phrasetypeid]"]["$getphrase[varname]"] = $getphrase;
	}
	unset($getphrase);
	$DB_site->free_result($getphrases);

	$xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";
	$xml .= "<language name=\"$title\" vbversion=\"$version\" type=\"" . iif($languageid == -1, 'master', iif($just_phrases, 'phrases', 'custom')) . "\">\r\n\r\n";

	if ($languageid != -1 AND !$just_phrases)
	{
		require_once('./includes/functions_xml.php');

		$xml .= "\t<settings>\r\n";
		$ignorefields = array('languageid', 'title', 'userselect');
		foreach ($language AS $fieldname => $value)
		{
			if (substr($fieldname, 0, 12) != 'phrasegroup_' AND !in_array($fieldname, $ignorefields))
			{
				if (preg_match('/[\<\>\&\'\"\[\]]/', $value))
				{
					$value = xml_escape_cdata($value);
				}
				$xml .= "\t\t<$fieldname><![CDATA[$value]]></$fieldname>\r\n";
			}
		}
		$xml .= "\t</settings>\r\n\r\n";
	}

	foreach($phrases AS $phrasetypeid => $typephrases)
	{
		require_once('./includes/functions_xml.php');

		$xml .= "\t<phrasetype name=\"" . $phrasetypes["$phrasetypeid"]['title'] . "\" fieldname=\"" . $phrasetypes["$phrasetypeid"]['fieldname'] . "\">\r\n";
		foreach($typephrases AS $phrase)
		{
			if (preg_match('/[\<\>\&\'\"\[\]]/', $phrase['text']))
			{
				$phrase['text'] = xml_escape_cdata($phrase['text']);
			}
			$xml .= "\t\t<phrase name=\"$phrase[varname]\"><![CDATA[$phrase[text]]]></phrase>\r\n";
		}

		$xml .= "\t</phrasetype>\r\n\r\n";
	}

	$xml .= "</language>";

	require_once('./includes/functions_file.php');
	file_download($xml, $filename, 'text/xml');
}

// ##########################################################################

print_cp_header($vbphrase['language_manager']);

// #############################################################################
// #############################################################################

if ($_POST['do'] == 'update')
{
	globalize($_POST, array(
		'phrasetypeid' => INT,
		'page' => INT,
		'def', // default text values array (hidden fields)
		'phr', // changed text values array (textarea fields)
		'rvt', // revert phrases array (checkbox fields)
	));

	$updatelanguage = false;

	if (!empty($rvt))
	{
		$updatelanguage = true;

		$query = "
			### DELETE REVERTED PHRASES FROM LANGUAGE:$languageid, PHRASETYPE:$phrasetypeid ###
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE phraseid IN(" . implode(', ', $rvt) . ")
		";

		$DB_site->query($query);

		// unset reverted phrases
		foreach (array_keys($rvt) AS $varname)
		{
			unset($def["$varname"]);
		}
	}

	$sql = array();

	foreach (array_keys($def) AS $varname)
	{
		$defphrase = &$def["$varname"];
		$newphrase = &$phr["$varname"];
		$varname = urldecode($varname);

		if ($newphrase != $defphrase)
		{
			$sql[] = "($languageid, $phrasetypeid, '" . addslashes($varname) . "', '" . addslashes($newphrase) . "')";
		}
	}

	if (!empty($sql))
	{
		$updatelanguage = true;

		$query = "
			### UPDATE CHANGED PHRASES FROM LANGUAGE:$languageid, PHRASETYPE:$phrasetypeid ###
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, phrasetypeid, varname, text)
			VALUES
				" . implode(",\n\t\t\t\t", $sql) . "
		";

		$DB_site->query($query);
	}

	if ($updatelanguage)
	{
		build_language($languageid);
	}

	define('CP_REDIRECT', "language.php?do=edit&amp;languageid=$languageid&amp;phrasetypeid=$phrasetypeid&amp;page=$page");
	print_stop_message('saved_language_successfully');
}

// #############################################################################
// #############################################################################

// ##########################################################################

if ($_POST['do'] == 'upload')
{
	ignore_user_abort(true);

	globalize($_POST, array(
		'title' => STR,
		'serverfile' => STR,
		'anyversion' => INT,
		'userselect' => INT,
		'languagefile' => FILE
	));

	// got an uploaded file?
	if (file_exists($languagefile['tmp_name']))
	{
		$xml = file_read($languagefile['tmp_name']);
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

	xml_import_language($xml, $languageid, $title, $anyversion, $userselect);

	print_cp_redirect("language.php?$session[sessionurl]do=rebuild&amp;goto=language.php?$session[sessionurl]", 0);

}

// ##########################################################################

if ($_REQUEST['do'] == 'files')
{
	require_once('./includes/functions_misc.php');
	$languages = fetch_language_titles_array('', 1);

	// download form
	print_form_header('language', 'download', 0, 1, 'downloadform" target="download');
	print_table_header($vbphrase['download']);
	print_label_row($vbphrase['language'], '<select name="languageid" tabindex="1" class="bginput">' . iif($debug, '<option value="-1">' . MASTER_LANGUAGE . '</option>') . construct_select_options($languages) . '</select>', '', 'top', 'languageid');
	print_input_row($vbphrase['filename'], 'filename', DEFAULT_FILENAME);
	if ($debug == 1)
	{
		print_yes_no_row($vbphrase['just_fetch_phrases'], 'just_phrases', 0);
	}
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

	// upload form
	print_form_header('language', 'upload', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.languagefile);');
	print_table_header($vbphrase['import_language_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'languagefile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './install/vbulletin-language.xml');
	print_label_row($vbphrase['overwrite_language_dfn'], '<select name="languageid" tabindex="1" class="bginput"><option value="0">(' . $vbphrase['create_new_language'] . ')</option>' . construct_select_options($languages) . '</select>', '', 'top', 'olanguageid');
	print_input_row($vbphrase['title_for_uploaded_language'], 'title');
	print_yes_no_row($vbphrase['ignore_language_version'], 'anyversion', 0);
	print_submit_row($vbphrase['import']);

}

// ##########################################################################

if ($_REQUEST['do'] == 'rebuild')
{
	globalize($_REQUEST, array(
		'goto' => STR_NOHTML
	));

	$help = construct_help_button('', NULL, '', 1);

	echo "<p>&nbsp;</p>
	<blockquote><form><div class=\"tborder\">
	<div class=\"tcat\" style=\"padding:4px\" align=\"center\"><div style=\"float:$stylevar[right]\">$help</div><b>" . $vbphrase['rebuild_language_information'] . "</b></div>
	<div class=\"alt1\" style=\"padding:4px\">\n<blockquote>
	"; flush();

	$languages = fetch_languages_array();
	foreach($languages AS $languageid => $language)
	{
		echo "<p>" . construct_phrase($vbphrase['rebuilding_language_x'], "<b>$language[title]</b>") . iif($languageid == $vboptions['languageid'], " ($vbphrase[default])") . ' ...'; flush();
		build_language($languageid);
		echo "<b>" . $vbphrase['done'] . "</b></p>\n";
		flush();
	}

	echo "</blockquote></div>
	<div class=\"tfoot\" style=\"padding:4px\" align=\"center\">
		<input type=\"button\" class=\"button\" value=\" $vbphrase[done] \" onclick=\"window.location='" . str_replace("'", '', $goto) . "';\" />
	</div>
	</div></form></blockquote>
	"; flush();

}

// ##########################################################################

if ($_REQUEST['do'] == 'setdefault')
{
	if ($languageid == 0)
	{
		print_stop_message('invalid_language_specified');
	}

	$DB_site->query("UPDATE " . TABLE_PREFIX . "setting SET value = $languageid WHERE varname = 'languageid'");
	build_options();
	$vboptions['languageid'] = $languageid;

	build_language($languageid);

	$_REQUEST['do'] = 'modify';

}

// ##########################################################################

if ($_REQUEST['do'] == 'view')
{
	if ($languageid != -1)
	{
		$language = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "language WHERE languageid = $languageid");
		$phrase = unserialize($language['language']);
	}
	else
	{
		$phrase = array();
		$language['title'] = $vbphrase['master_language'];

		$getphrases = $DB_site->query("
			SELECT varname, text, phrasetypeid
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid = -1 AND phrasetypeid < 1000
			ORDER BY varname
		");
		while ($getphrase = $DB_site->fetch_array($getphrases))
		{
			$phrase["$getphrase[varname]"] = $getphrase['text'];
		}
	}

	if (!empty($phrase))
	{
		print_form_header('', '');
		print_table_header($vbphrase['view_language'] . " <span class=\"normal\">$language[title]<span>");
		print_cells_row(array($vbphrase['varname'], $vbphrase['replace_with_text']), 1, 0, 1);
		foreach($phrase AS $varname => $text)
		{
			print_cells_row(array("<span style=\"white-space: nowrap\">\$vbphrase[<b>$varname</b>]</span>", "<span class=\"smallfont\">" . htmlspecialchars_uni($text) . "</span>"), 0, 0, -1);
		}
		print_table_footer();
	}
	else
	{
		print_stop_message('no_phrases_defined');
	}

}

// ##########################################################################

if ($_POST['do'] == 'kill')
{
	if ($languageid == $vboptions['languageid'])
	{
		// show the 'can't delete default' error message
		$_REQUEST['do'] == 'delete';
	}
	else
	{
		$languages = $DB_site->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "language");
		if ($languages['total'] == 1)
		{
			print_stop_message('cant_delete_last_language');
		}
		else
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "user SET languageid = 0 WHERE languageid = $languageid");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "phrase WHERE languageid = $languageid");
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "language WHERE languageid = $languageid");

			define('CP_REDIRECT', 'language.php');
			print_stop_message('deleted_language_successfully');
		}
	}

}

// ##########################################################################

if ($_REQUEST['do'] == 'delete')
{

	if ($languageid == $vboptions['languageid'])
	{
		print_stop_message('cant_delete_default_language');
	}

	print_delete_confirmation('language', $languageid, 'language', 'kill', 'language', 0, $vbphrase['deleting_this_language_will_delete_custom_phrases']);

}

// ##########################################################################

if ($_POST['do'] == 'insert')
{
	globalize($_POST, array('direction' => INT));
	$tempopt = array('direction' => $direction);

	require_once('./includes/functions_misc.php');
	$_POST['options'] = convert_array_to_bits($tempopt, $_BITFIELD['languageoptions']);
	globalize($_POST, $langglobals);

	$newlang = array();
	foreach($langglobals AS $key => $val)
	{
		if (is_numeric($key))
		{
			$key = $val;
		}

		$newlang["$key"] = $_POST["$key"];
	}

	if (empty($newlang['title']))
	{
		print_stop_message('please_complete_required_fields');
	}

	// User has defined a locale.
	if ($newlang['locale'] != '')
	{
		if (!setlocale(LC_TIME, $newlang['locale']) OR !setlocale(LC_CTYPE, $newlang['locale']))
		{
			print_stop_message('invalid_locale', $newlang['locale']);
		}

		if ($newlang['dateoverride'] == '' OR $newlang['timeoverride'] == '' OR $newlang['registereddateoverride'] == '' OR $newlang['calformat1override'] == '' OR $newlang['calformat2override'] == '' OR $newlang['logdateoverride'] == '')
		{
			print_stop_message('locale_define_fill_in_all_overrides');
		}
	}

	$DB_site->query(fetch_query_sql($newlang, 'language'));
	$languageid = $DB_site->insert_id($result);

	build_language($languageid);

	define('CP_REDIRECT', 'language.php?languageid=' . $languageid);
	print_stop_message('saved_language_x_successfully', $newlang['title']);
}

// ##########################################################################

if ($_REQUEST['do'] == 'add')
{
	print_form_header('language', 'insert');
	print_table_header($vbphrase['add_new_language']);

	print_description_row($vbphrase['general_settings'], 0, 2, 'thead');
	print_input_row($vbphrase['title'], 'title');
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect');
	print_label_row($vbphrase['text_direction'],
		"<label for=\"rb_l2r\"><input type=\"radio\" name=\"direction\" id=\"rb_l2r\" value=\"1\" tabindex=\"1\" checked=\"checked\" />$vbphrase[left_to_right]</label><br />
		 <label for=\"rb_r2l\"><input type=\"radio\" name=\"direction\" id=\"rb_r2l\" value=\"0\" tabindex=\"1\" />$vbphrase[right_to_left]</label>",
		'', 'top', 'direction'
	);
	print_input_row($vbphrase['language_code'], 'languagecode', 'en');
	print_input_row($vbphrase['html_charset'] . "<code>&lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=<b>ISO-8859-1</b>&quot; /&gt;</code>", 'charset', 'ISO-8859-1');
	print_input_row($vbphrase['image_folder_override'], 'imagesoverride', '');

	print_description_row($vbphrase['date_time_formatting'], 0, 2, 'thead');
	print_input_row($vbphrase['locale'], 'locale', '');
	print_input_row($vbphrase['date_format_override'], 'dateoverride', '');
	print_input_row($vbphrase['time_format_override'], 'timeoverride', '');
	print_input_row($vbphrase['registereddate_format_override'], 'registereddateoverride', '');
	print_input_row($vbphrase['calformat1_format_override'], 'calformat1override', '');
	print_input_row($vbphrase['calformat2_format_override'], 'calformat2override', '');
	print_input_row($vbphrase['logdate_format_override'], 'logdateoverride', '');

	print_description_row($vbphrase['number_formatting'], 0, 2, 'thead');
	print_input_row($vbphrase['decimal_separator'], 'decimalsep', '.', 1, 3, 1);
	print_input_row($vbphrase['thousands_separator'], 'thousandsep', ',', 1, 3, 1);

	print_submit_row($vbphrase['save']);
}

// ##########################################################################

if ($_POST['do'] == 'update_settings')
{
	globalize($_POST, array(
		'direction' => INT,
		'isdefault' => INT
	));
	$tempopt = array('direction' => $direction);

	require_once('./includes/functions_misc.php');
	$_POST['options'] = convert_array_to_bits($tempopt, $_BITFIELD['languageoptions']);
	globalize($_POST, $langglobals);

	$langupdate = array();
	foreach($langglobals AS $key => $val)
	{
		if (is_numeric($key))
		{
			$key = $val;
		}

		$langupdate["$key"] = $_POST["$key"];
	}

	if ($isdefault AND $langupdate['userselect'] == 0)
	{
		print_stop_message('cant_delete_default_language');
	}

	// User has defined a locale.
	if ($langupdate['locale'] != '')
	{
		if (!setlocale(LC_TIME, $langupdate['locale']) OR !setlocale(LC_CTYPE, $langupdate['locale']))
		{
			print_stop_message('invalid_locale', $langupdate['locale']);
		}

		if ($langupdate['dateoverride'] == '' OR $langupdate['timeoverride'] == '' OR $langupdate['registereddateoverride'] == '' OR $langupdate['calformat1override'] == '' OR $langupdate['calformat2override'] == '' OR $langupdate['logdateoverride'] == '')
		{
			print_stop_message('locale_define_fill_in_all_overrides');
		}
	}

	$query = fetch_query_sql($langupdate, 'language', "WHERE languageid = $languageid");
	$DB_site->query($query);

	if ($isdefault AND $languageid != $vboptions['languageid'])
	{
		$do = 'setdefault';
	}
	else
	{
		$do = 'modify';
	}

	define('CP_REDIRECT', 'language.php?languageid=' . $languageid . '&amp;do=' . $do);
	print_stop_message('saved_language_x_successfully', $newlang['title']);
}

// ##########################################################################
if ($_REQUEST['do'] == 'edit_settings')
{
	$language = fetch_languages_array($languageid);

	print_form_header('language', 'update_settings');
	construct_hidden_code('languageid', $languageid);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['language'], $language['title'], $language['languageid']));

	print_description_row($vbphrase['general_settings'], 0, 2, 'thead');
	print_input_row($vbphrase['title'], 'title', $language['title'], 0);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', $language['userselect']);
	print_yes_no_row($vbphrase['is_default_language'], 'isdefault', iif($languageid == $vboptions['languageid'], 1, 0));
	print_label_row($vbphrase['text_direction'],
		'<label for="rb_l2r"><input type="radio" name="direction" id="rb_l2r" value="1" tabindex="1"' . iif($language['options'] & $_BITFIELD['languageoptions']['direction'], ' checked="checked"') . " />$vbphrase[left_to_right]</label><br />" . '
		 <label for="rb_r2l"><input type="radio" name="direction" id="rb_r2l" value="0" tabindex="1"' . iif(!($language['options'] & $_BITFIELD['languageoptions']['direction']), ' checked="checked"') . " />$vbphrase[right_to_left]</label>",
		'', 'top', 'direction'
	);
	print_input_row($vbphrase['language_code'], 'languagecode', $language['languagecode']);
	print_input_row($vbphrase['html_charset'] . "<code>&lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=<b>$language[charset]</b>&quot; /&gt;</code>", 'charset', $language['charset']);
	print_input_row($vbphrase['image_folder_override'], 'imagesoverride', $language['imagesoverride']);

	print_description_row($vbphrase['date_time_formatting'], 0, 2, 'thead');
	print_input_row($vbphrase['locale'], 'locale', $language['locale']);
	print_input_row($vbphrase['date_format_override'], 'dateoverride', $language['dateoverride']);
	print_input_row($vbphrase['time_format_override'], 'timeoverride', $language['timeoverride']);
	print_input_row($vbphrase['registereddate_format_override'], 'registereddateoverride', $language['registereddateoverride']);
	print_input_row($vbphrase['calformat1_format_override'], 'calformat1override', $language['calformat1override']);
	print_input_row($vbphrase['calformat2_format_override'], 'calformat2override', $language['calformat2override']);
	print_input_row($vbphrase['logdate_format_override'], 'logdateoverride', $language['logdateoverride']);

	print_description_row($vbphrase['number_formatting'], 0, 2, 'thead');
	print_input_row($vbphrase['decimal_separator'], 'decimalsep', $language['decimalsep'], 1, 3, 1);
	print_input_row($vbphrase['thousands_separator'], 'thousandsep', $language['thousandsep'], 1, 3, 1);

	print_submit_row($vbphrase['save']);

}

// ##########################################################################

if ($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'phrasetypeid' => INT,
		'page' => INT,
		'prev' => STR,
		'next' => STR
	));

	if ($prev != '' OR $next != '')
	{
		if ($prev != '')
		{
			$page -= 1;
		}
		else
		{
			$page += 1;
		}
	}

	if ($phrasetypeid < 1)
	{
		$phrasetypeid = 1;
	}

	// ***********************
	if ($languageid == -2)
	{
		print_cp_redirect("phrase.php?$session[sessionurl]phrasetypeid=$phrasetypeid",0);
	}
	else if ($languageid == 0)
	{
		$_REQUEST['do'] = 'modify';
	}
	else
	{
	// ***********************

	$perpage = 10;

	print_phrase_ref_popup_javascript();

	?>
	<script type="text/javascript">
	<!--
	function js_fetch_default(varname)
	{
		var P = eval('document.forms.cpform.P_' + varname);
		var D = eval('document.forms.cpform.D_' + varname);
		P.value = D.value;
	}

	function js_change_direction(direction, varname)
	{
		var P = eval('document.forms.cpform.P_' + varname);
		P.dir = direction;
	}
	// -->
	</script>
	<?php

	// build top options and get language info
	$languages = fetch_languages_array();
	if ($debug)
	{
		$mlanguages = array('-1' => array('languageid' => -1, 'title' => $vbphrase['master_language']));
		$languages = $mlanguages + $languages;
	}
	$langoptions = array();

	foreach($languages AS $langid => $lang)
	{
		$langoptions["$lang[languageid]"] = $lang['title'];
		if ($lang['languageid'] == $languageid)
		{
			$language = $lang;
		}
	}
	$langoptions['no_value'] = '__________________________________';

	$phrasetypeoptions = array();
	$phrasetypes = fetch_phrasetypes_array();
	foreach ($phrasetypes AS $id => $type)
	{
		$phrasetypeoptions["$type[phrasetypeid]"] = $type['title'];
	}
	$phrasetypeoptions['no_value'] = '__________________________________';

	print_phrase_ref_popup_javascript();

	// get custom phrases
	$numcustom = 0;
	if ($languageid != -1)
	{
		$custom_phrases = fetch_custom_phrases($languageid, $phrasetypeid);
		$numcustom = sizeof($custom_phrases);
	}
	// get inherited and customized phrases
	$standard_phrases = fetch_standard_phrases($languageid, $phrasetypeid, $numcustom);

	$numstandard = sizeof($standard_phrases);
	$totalphrases = $numcustom + $numstandard;

	$numpages = ceil($totalphrases / $perpage);

	if ($page < 1)
	{
		$page = 1;
	}
	if ($page > $numpages)
	{
		$page = $numpages;
	}
	$startat = ($page - 1) * $perpage;
	$endat = $startat + $perpage;
	if ($endat >= $totalphrases)
	{
		$endat = $totalphrases;
	}

	$i = 15;

	$p = 0;
	$pageoptions = array();
	for ($i = 0; $i < $totalphrases; $i += $perpage)
	{
		$p++;
		$firstphrase = $i;
		$lastphrase = $firstphrase + $perpage - 1;
		if ($lastphrase >= $totalphrases)
		{
			$lastphrase = $totalphrases - 1;
		}
		$pageoptions["$p"] = "$vbphrase[page] $p ";//<!--(" . ($firstphrase + 1) . " to " . ($lastphrase + 1) . ")-->";
	}

	$showprev = true;
	$shownext = true;
	if ($page == 1)
	{
		$showprev = false;
	}
	if ($page >= $numpages)
	{
		$shownext = false;
	}

	// #############################################################################

	print_form_header('language', 'edit', 0, 1, 'qform', '90%', '', 1, 'get');
	echo '
		<colgroup span="5">
			<col style="white-space:nowrap"></col>
			<col></col>
			<col></col>
			<col align="center" width="50%" style="white-space:nowrap"></col>
			<col align="center" width="50%"></col>
		</colgroup>
		<tr>
			<td class="thead">' . $vbphrase['language'] . ':</td>
			<td class="thead"><select name="languageid" onchange="this.form.submit()" class="bginput">' . construct_select_options($langoptions, $languageid) . '</select></td>
			<td class="thead" rowspan="2"><input type="submit" class="button" style="height:40px" value="  ' . $vbphrase['go'] . '  " /></td>
			<td class="thead" rowspan="2"><!--' . $vbphrase['page'] . ':-->
				<select name="page" onchange="this.form.submit()" class="bginput">' . construct_select_options($pageoptions, $page) . '</select><br />
				' . iif($showprev, ' <input type="submit" class="button" name="prev" value="&laquo; ' . $vbphrase['prev'] . '" />') . '
				' . iif($shownext, ' <input type="submit" class="button" name="next" value="' . $vbphrase['next'] . ' &raquo;" />') . '
			</td>
			<td class="thead" rowspan="2">' . "
				<input type=\"button\" class=\"button\" value=\"" . $vbphrase['view_quickref'] . "\" onclick=\"js_open_phrase_ref($languageid, $phrasetypeid);\" />
				<!--<input type=\"button\" class=\"button\" value=\"" . $vbphrase['view_summary'] . "\" onclick=\"window.location='language.php?$session[sessionurl]do=view&amp;languageid=$languageid';\" />-->
				<input type=\"button\" class=\"button\" value=\"$vbphrase[set_default]\" " . iif($languageid == -1 OR $languageid == $vboptions['languageid'], 'disabled="disabled"', "title=\"" . construct_phrase($vbphrase['set_language_as_default_x'], $language['title']) . "\" onclick=\"window.location='language.php?$session[sessionurl]do=setdefault&amp;languageid=$languageid';\"") . " />
			" . '</td>
		</tr>
		<tr>
			<td class="thead">' . $vbphrase['phrase_type'] . ':</td>
			<td class="thead"><select name="phrasetypeid" onchange="this.form.page.selectedIndex = 0; this.form.submit()" class="bginput">' . construct_select_options($phrasetypeoptions, $phrasetypeid) . '</select></td>
		</tr>
	';
	print_table_footer();

	$printers = array();

	$i = 0;
	if ($startat < $numcustom)
	{
		for ($i = $startat; $i < $endat AND $i < $numcustom; $i++)
		{
			$printers["$i"] = &$custom_phrases["$i"];
		}
	}
	if ($i < $endat)
	{
		if ($i == 0)
		{
			$i = $startat;
		}
		for ($i; $i < $endat AND $i < $totalphrases; $i++)
		{
			$printers["$i"] = &$standard_phrases["$i"];
		}
	}

	// ******************

	print_form_header('language', 'update');
	construct_hidden_code('languageid', $languageid);
	construct_hidden_code('phrasetypeid', $phrasetypeid);
	construct_hidden_code('page', $page);
	print_table_header(construct_phrase($vbphrase['edit_translate_x_y_phrases'], $languages["$languageid"]['title'], "<span class=\"normal\">{$phrasetypes[$phrasetypeid][title]}</span>") . ' <span class="normal">' . construct_phrase($vbphrase['page_x_of_y'], $page, $numpages) . '</span>');
	print_column_style_code(array('', '" width="20'));
	$lasttype = '';
	foreach ($printers AS $key => $blarg)
	{
		if ($lasttype != $blarg['type'])
		{
			print_label_row($vbphrase['varname'], $vbphrase['text'], 'thead');
		}
		print_phrase_row($blarg, $phrasetypes["$phrasetypeid"]['editrows'], $key, $language['direction']);

		$lasttype = $blarg['type'];
	}
	print_submit_row();

	// ******************

	if ($numpages > 1)
	{
		print_form_header('language', 'edit', 0, 1, 'qform', '90%', '', 1, 'get');
		construct_hidden_code('languageid', $languageid);
		construct_hidden_code('phrasetypeid', $phrasetypeid);
		$pagebuttons = '';
		for ($p = 1; $p <= $numpages; $p++)
		{
			$pagebuttons .= "\n\t\t\t\t<input type=\"submit\" class=\"button\" style=\"font:10px verdana\" name=\"page\" value=\"$p\" tabindex=\"1\" title=\"$vbphrase[page] $p\"" . iif($p == $page, ' disabled="disabled"') . ' />';
		}
		echo '
		<tr>' . iif($showprev, '
			<td class="thead"><input type="submit" class="button" name="prev" value="&laquo; ' . $vbphrase['prev'] . '" tabindex="1" /></td>') . '
			<td class="thead" width="100%" align="center"><input type="hidden" name="page" value="' . $page . '" />' . $pagebuttons . '
			</td>' . iif($shownext, '
			<td class="thead"><input type="submit" class="button" name="next" value="' . $vbphrase['next'] . ' &raquo;" tabindex="1" /></td>') . '
		</tr>
		';
		print_table_footer();
	}

	// ***********************
	} // end if ($languageid != 0)
	// ***********************

}

// ##########################################################################

if ($_REQUEST['do'] == 'modify')
{
	/*
	$typeoptions = array();
	$phrasetypes = fetch_phrasetypes_array();
	foreach($phrasetypes AS $id => $type)
	{
		$typeoptions["$id"] = construct_phrase($vbphrase['x_phrases'], $type['title']);
	}
	*/

	print_form_header('language', 'add');
	construct_hidden_code('goto', "language.php?$session[sessionurl]");
	print_table_header($vbphrase['language_manager'], 4);
	print_cells_row(array($vbphrase['language'], '', '', $vbphrase['default']), 1);

	if ($debug)
	{
		print_language_row(array('languageid' => -1, 'title' => "<i>$vbphrase[master_language]</i>"));
	}

	$languages = fetch_languages_array();

	foreach($languages AS $languageid => $language)
	{
		print_language_row($language);
	}

	print_description_row(
		construct_link_code($vbphrase['search_phrases'], "phrase.php?$session[sessionurl]do=search") .
		construct_link_code($vbphrase['view_quickref'], "javascript:js_open_phrase_ref(0,0);") .
		construct_link_code($vbphrase['rebuild_all_languages'], "language.php?$session[sessionurl]do=rebuild&amp;goto=language.php?$session[sessionurl]")
	, 0, 4, 'thead" style="text-align:center; font-weight:normal');

	print_table_footer(4, '
		<input type="submit" class="button" value="' . $vbphrase['add_new_language'] . '" tabindex="1" />
		<input type="button" class="button" value="' . $vbphrase['download_upload_language'] . '" tabindex="1" onclick="window.location=\'language.php?do=files\';" />
	');

	print_phrase_ref_popup_javascript();

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13174 $
|| ####################################################################
\*======================================================================*/
?>