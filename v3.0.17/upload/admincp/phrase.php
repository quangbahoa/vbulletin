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
define('CVS_REVISION', '$RCSfile$ - $Revision: 11201 $');
define('NO_REGISTER_GLOBALS', 1);

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
log_admin_action(iif(isset($_REQUEST['phraseid']), "phrase id = " . $_REQUEST['phraseid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// #############################################################################

if ($_REQUEST['do'] == 'quickref')
{
	globalize($_REQUEST, array(
		'languageid' => INT,
		'phrasetypeid' => INT
	));

	if ($languageid == 0)
	{
		$languageid = $vboptions['languageid'];
	}
	if ($phrasetypeid == 0)
	{
		$phrasetypeid = 1;
	}

	$languages = fetch_languages_array();
	if ($debug)
	{
		$langoptions['-1'] = $vbphrase['master_language'];
	}
	foreach($languages AS $id => $lang)
	{
		$langoptions["$id"] = $lang['title'];
	}
	$phrasetypes = fetch_phrasetypes_array();
	foreach($phrasetypes AS $id => $type)
	{
		$typeoptions["$id"] = $type['title'] . ' ' . $vbphrase['phrases'];
	}

	define('NO_PAGE_TITLE', true);
	print_cp_header("$vbphrase[quickref] $langoptions[$languageid] $typeoptions[$phrasetypeid]", '', '', 0);

	$phrasearray = array();

	if ($languageid != -1)
	{
		$custom = fetch_custom_phrases($languageid, $phrasetypeid);
		if (!empty($custom))
		{
			foreach($custom AS $phrase)
			{
				$phrasearray[htmlspecialchars_uni($phrase['text'])] = $phrase['varname'];
			}
		}
	}

	$standard = fetch_standard_phrases($languageid, $phrasetypeid);

	if (is_array($standard))
	{
		foreach($standard AS $phrase)
		{
			$phrasearray[htmlspecialchars_uni($phrase['text'])] = $phrase['varname'];
		}
		$tval = $langoptions["$languageid"] . ' ' . $typeoptions["$phrasetypeid"];
	}
	else
	{
		$tval = construct_phrase($vbphrase['no_x_phrases_defined'], '<i>' . $typeoptions["$phrasetypeid"] . '</i>');
	}

	$directionHtml = 'dir="' . $languages["$languageid"]['direction'] . '"';

	print_form_header('phrase', 'quickref', 0, 1, 'cpform', '100%', '', 0);
	print_table_header($vbphrase['quickref'] . ' </b>' . $langoptions["$languageid"] . ' ' . $typeoptions["$phrasetypeid"] . '<b>');
	print_label_row("<select size=\"10\" class=\"bginput\" onchange=\"
		if (this.options[this.selectedIndex].value != '')
		{
			this.form.tvar.value = '\$" . "vbphrase[' + this.options[this.selectedIndex].text + ']';
			this.form.tbox.value = this.options[this.selectedIndex].value;
		}
		\">" . construct_select_options($phrasearray) . '</select>','
		<input type="text" class="bginput" name="tvar" size="35" class="button" /><br />
		<textarea name="tbox" class="darkbg" style="font: 11px verdana" rows="8" cols="35" ' . $directionHtml . '>' . $tval . '</textarea>
		');
	print_description_row('
		<center>
		<select name="languageid" accesskey="l" class="bginput">' . construct_select_options($langoptions, $languageid) . '</select>
		<select name="phrasetypeid" accesskey="t" class="bginput">' . construct_select_options($typeoptions, $phrasetypeid) . '</select>
		<input type="submit" class="button" value="' . $vbphrase['view'] . '" accesskey="s" />
		<input type="button" class="button" value="' . $vbphrase['close'] . '" accesskey="c" onclick="self.close()" />
		</center>
	', 0, 2, 'thead');
	print_table_footer();

	unset($DEVDEBUG);
	print_cp_footer();

}

// #############################################################################

if ($_POST['do'] == 'completeorphans')
{
	globalize($_POST, array(
		'del', // phrases to delete
		'keep' // phrases to keep
	));

	if (is_array($del) AND !empty($del))
	{
		$delcondition = array();

		foreach ($del AS $key)
		{
			fetch_varname_phrasetypeid($key);
			$delcondition[] = "(varname = '" . addslashes($varname) . "' AND phrasetypeid = $phrasetypeid)";
		}

		$q = "
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE " . implode('
			OR ', $delcondition);

		//print_query($q);
		$DB_site->query($q);
	}

	if (is_array($keep) AND !empty($keep))
	{
		$insertsql = array();

		$phrases = $DB_site->query("
			SELECT *
			FROM " . TABLE_PREFIX . "phrase
			WHERE phraseid IN(" . implode(', ', $keep) . ")
		");
		while ($phrase = $DB_site->fetch_array($phrases))
		{
			$insertsql[] = "(0, $phrase[phrasetypeid], '" . addslashes($phrase['varname']) . "', '" . addslashes($phrase['text']) . "')";
		}
		$DB_site->free_result($phrases);

		$q = "
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, phrasetypeid, varname, text)
			VALUES
				" . implode(',
				', $insertsql);

		//print_query($q);
		$DB_site->query($q);

	}

	exec_header_redirect("language.php?$session[sessionurl]do=rebuild&goto=" . urlencode("phrase.php?$session[sessionurl]"));
}

// #############################################################################

print_cp_header($vbphrase['phrase_manager']);

// #############################################################################

if ($_POST['do'] == 'manageorphans')
{
	globalize($_POST, array('phr'));

	print_form_header('phrase', 'completeorphans');

	$hidden_code_num = 0;
	$keepnames = array();

	foreach ($phr AS $key => $keep)
	{
		if ($keep)
		{
			fetch_varname_phrasetypeid($key);
			$keepnames[] = "(varname = '" . addslashes($varname) . "' AND phrasetypeid = $phrasetypeid)";
		}
		else
		{
			construct_hidden_code("del[$hidden_code_num]", $key);
			$hidden_code_num ++;
		}
	}

	print_table_header($vbphrase['find_orphan_phrases']);

	if (empty($keepnames))
	{
		// there are no phrases to keep, just show a message telling admin to click to proceed
		print_description_row('<blockquote><p><br />' . $vbphrase['delete_all_orphans_notes'] . '</p></blockquote>');
	}
	else
	{
		// there are some phrases to keep, show a message explaining the page
		print_description_row($vbphrase['keep_orphans_notes']);

		$orphans = array();

		$phrases = $DB_site->query("
			SELECT *
			FROM " . TABLE_PREFIX . "phrase
			WHERE " . implode('
			OR ', $keepnames)
		);
		while ($phrase = $DB_site->fetch_array($phrases))
		{
			$orphans["$phrase[varname]_$phrase[phrasetypeid]"]["$phrase[languageid]"] = array('phraseid' => $phrase['phraseid'], 'text' => $phrase['text']);
		}
		$DB_site->free_result($phrases);

		$languages = fetch_languages_array();
		$phrasetypes = fetch_phrasetypes_array();

		foreach ($orphans AS $key => $languageids)
		{
			fetch_varname_phrasetypeid($key);

			if (isset($languageids["$vboptions[languageid]"]))
			{
				$checked = $vboptions['languageid'];
			}
			else
			{
				$checked = 0;
			}

			$bgclass = fetch_row_bgclass();

			echo "<tr valign=\"top\">\n";
			echo "\t<td class=\"$bgclass\">" . construct_wrappable_varname($varname, 'font-weight:bold;') . " <dfn>" . construct_phrase($vbphrase['x_phrases'], $phrasetypes["$phrasetypeid"]['title']) . "</dfn></td>\n";
			echo "\t<td style=\"padding:0px\">\n\t\t<table cellpadding=\"2\" cellspacing=\"1\" border=\"0\" width=\"100%\">\n\t\t<col width=\"65%\"><col width=\"35%\" align=\"$stylevar[right]\">\n";

			$i = 0;
			$tr_bgclass = iif(($bgcounter % 2) == 0, 'alt2', 'alt1');

			foreach ($languages AS $languageid => $language)
			{
				if (isset($languageids["$languageid"]))
				{
					if ($checked)
					{
						if ($languageid == $checked)
						{
							$checkedhtml = ' checked="checked"';
						}
						else
						{
							$checkedhtml = '';
						}
					}
					else if ($i == 0)
					{
						$checkedhtml = ' checked="checked"';
					}
					else
					{
						$checkedhtml = '';
					}
					$i++;
					$phrase = &$orphans["$key"]["$languageid"];

					echo "\t\t<tr class=\"$tr_bgclass\">\n";
					echo "\t\t\t<td class=\"smallfont\"><label for=\"p$phrase[phraseid]\"><i>$phrase[text]</i></label></td>\n";
					echo "\t\t\t<td class=\"smallfont\"><label for=\"p$phrase[phraseid]\"><b>$language[title]</b><input type=\"radio\" name=\"keep[$key]\" value=\"$phrase[phraseid]\" id=\"p$phrase[phraseid]\" tabindex=\"1\"$checkedhtml /></label></td>\n";
					echo "\t\t</tr>\n";
				}
			}

			echo "\n\t\t</table>\n";
			echo "\t\t<div class=\"$bgclass\">&nbsp;</div>\n";
			echo "\t</td>\n</tr>\n";
		}
	}

	print_submit_row($vbphrase['continue'], iif(empty($keepnames), false, " $vbphrase[reset] "));
}

// #############################################################################

if ($_REQUEST['do'] == 'findorphans')
{
	// get info for the languages and phrase types
	$languages = fetch_languages_array();
	$phrasetypes = fetch_phrasetypes_array();

	// query phrases that do not have a parent phrase in language -1 or 0
	$phrases = $DB_site->query("
		SELECT orphan.varname, orphan.languageid, orphan.phrasetypeid
		FROM " . TABLE_PREFIX . "phrase AS orphan
		LEFT JOIN " . TABLE_PREFIX . "phrase AS phrase ON (phrase.languageid IN(-1, 0) AND phrase.varname = orphan.varname AND phrase.phrasetypeid = orphan.phrasetypeid)
		WHERE orphan.languageid NOT IN(-1, 0)
		AND phrase.phraseid IS NULL
		ORDER BY orphan.varname
	");

	if ($DB_site->num_rows($phrases) == 0)
	{
		$DB_site->free_result($phrases);
		print_stop_message('no_phrases_matched_your_query');
	}

	$orphans = array();
	while ($phrase = $DB_site->fetch_array($phrases))
	{
		$orphans["$phrase[varname]_$phrase[phrasetypeid]"]["$phrase[languageid]"] = true;
	}
	$DB_site->free_result($phrases);

	// get the number of columns for the table
	$colspan = sizeof($languages) + 2;

	print_form_header('phrase', 'manageorphans');
	print_table_header($vbphrase['find_orphan_phrases'], $colspan);

	// make the column headings
	$headings = array($vbphrase['varname']);
	foreach ($languages AS $language)
	{
		$headings[] = $language['title'];
	}
	$headings[] = '<input type="button" class="button" value="' . $vbphrase['keep_all'] . '" onclick="js_check_all_option(this.form, 1)" /> <input type="button" class="button" value="' . $vbphrase['delete_all'] . '" onclick="js_check_all_option(this.form, 0)" />';
	print_cells_row($headings, 1);

	// init the counter for our id attributes in label tags
	$i = 0;

	foreach ($orphans AS $key => $languageids)
	{
		// split the array key
		fetch_varname_phrasetypeid($key);

		// make the first cell
		$cell = array(construct_wrappable_varname($varname, 'font-weight:bold;') . " <dfn>" . construct_phrase($vbphrase['x_phrases'], $phrasetypes["$phrasetypeid"]['title']) . "</dfn>");

		// either display a tick or not depending on whether a translation exists
		foreach ($languages AS $languageid => $language)
		{
			if (isset($languageids["$languageid"]))
			{
				$yesno = 'yes';
			}
			else
			{
				$yesno = 'no';
			}

			$cell[] = "<img src=\"../cpstyles/$vboptions[cpstylefolder]/cp_tick_$yesno.gif\" alt=\"\" />";
		}

		$i++;
		$cell[] = "
		<label for=\"k_$i\"><input type=\"radio\" id=\"k_$i\" name=\"phr[{$varname}_$phrasetypeid]\" value=\"1\" tabindex=\"1\" />$vbphrase[keep]</label>
		<label for=\"d_$i\"><input type=\"radio\" id=\"d_$i\" name=\"phr[{$varname}_$phrasetypeid]\" value=\"0\" tabindex=\"1\" checked=\"checked\" />$vbphrase[delete]</label>
		";

		print_cells_row($cell);
	}

	print_submit_row($vbphrase['continue'], " $vbphrase[reset] ", $colspan);
}

// #############################################################################

if ($_POST['do'] == 'dosearch')
{
	globalize($_POST, array(
		'searchstring' => STR,
		'searchwhere' => INT,
		'casesensitive' => INT
	));

	if ($searchstring == '')
	{
		print_stop_message('please_complete_required_fields');
	}

	switch($searchwhere)
	{
		case 0: $sql = fetch_field_like_sql('text'); break;
		case 1: $sql = fetch_field_like_sql('varname', true); break;
		case 10: $sql = fetch_field_like_sql('text') . ' OR ' . fetch_field_like_sql('varname', true); break;
	}

	$phrases = $DB_site->query("
		SELECT phrase.*, language.title FROM " . TABLE_PREFIX . "phrase AS phrase
		LEFT JOIN " . TABLE_PREFIX . "language AS language USING(languageid)
		WHERE $sql
		ORDER BY phrasetypeid DESC
	");
	if ($DB_site->num_rows($phrases) == 0)
	{
		print_stop_message('no_phrases_matched_your_query');
	}

	$phrasearray = array();
	while ($phrase = $DB_site->fetch_array($phrases))
	{
		$phrasearray["$phrase[phrasetypeid]"]["$phrase[varname]"]["$phrase[languageid]"] = $phrase;
	}

	unset($phrase);
	$DB_site->free_result($phrases);

	$phrasetypes = fetch_phrasetypes_array();

	print_form_header('phrase', 'edit');
	print_table_header($vbphrase['search_results'], 4);

	foreach($phrasearray AS $phrasetypeid => $x)
	{
		// display the header for the phrasetype
		print_description_row(construct_phrase($vbphrase['x_phrases_containing_y'], $phrasetypes["$phrasetypeid"]['title'], htmlspecialchars_uni($searchstring)), 0, 4, 'thead" align="center');

		// sort the phrases alphabetically by $varname
		ksort($x);
		foreach($x AS $varname => $y)
		{
			foreach($y AS $phrase)
			{
				$cell = array();
				$cell[] = '<b>' . iif($searchwhere > 0, fetch_highlighted_search_results($varname), $varname) . '</b>';
				$cell[] = '<span class="smallfont">' . fetch_language_type_string($phrase['languageid'], $phrase['title']) . '</span>';
				$cell[] = '<span class="smallfont">' . iif($searchwhere%10 == 0, fetch_highlighted_search_results($phrase['text']), htmlspecialchars_uni($phrase['text'])) . '</span>';
				$cell[] = "<input type=\"submit\" class=\"button\" value=\" $vbphrase[edit] \" name=\"e[$phrasetypeid][" . urlencode($varname) . "]\" />";
				print_cells_row($cell, 0, 0, -2);
			} // end foreach($y)
		} // end foreach($x)
	} // end foreach($phrasearray)

	print_table_footer();

	$_REQUEST['do'] = 'search';

}

// #############################################################################

if ($_REQUEST['do'] == 'search')
{
	globalize($_POST, array(
		'searchstring',
		'searchwhere' => INT,
		'casesensitive' => INT
	));

	print_form_header('phrase', 'dosearch');
	print_table_header($vbphrase['search_in_phrases']);
	print_input_row($vbphrase['search_for_text'], 'searchstring', $searchstring, 1, 50);
	$where = array("$searchwhere" => ' checked="checked"');
	print_label_row(construct_phrase($vbphrase['search_in_x'], '...'),'
		<label for="rb_sw_0"><input type="radio" name="searchwhere" id="rb_sw_0" value="0" tabindex="1"' . $where[0] . ' />' . $vbphrase['phrase_text_only'] . '</label><br />
		<label for="rb_sw_1"><input type="radio" name="searchwhere" id="rb_sw_1" value="1" tabindex="1"' . $where[1] . ' />' . $vbphrase['phrase_name_only'] . '</label><br />
		<label for="rb_sw_10"><input type="radio" name="searchwhere" id="rb_sw_10" value="10" tabindex="1"' . $where[10] . ' />' . $vbphrase['phrase_text_and_phrase_name'] . '</label>', '', 'top', 'searchwhere');
	print_yes_no_row($vbphrase['case_sensitive'], 'casesensitive', $casesensitive);
	print_submit_row($vbphrase['find']);

}

// #############################################################################

if ($_POST['do'] == 'kill')
{
	globalize($_POST, array(
		'phraseid' => INT,
		'phrasetypeid' => INT,
		'page' => INT,
		'perpage' => INT
	));

	if ($getvarname = $DB_site->query_first("SELECT varname, phrasetypeid FROM " . TABLE_PREFIX . "phrase WHERE phraseid = $phraseid"))
	{
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname = '" . addslashes($getvarname['varname']) . "'
				AND phrasetypeid = $getvarname[phrasetypeid]
		");

		build_language(-1);

		define('CP_REDIRECT', "phrase.php?$session[sessionurl]phrasetypeid=$phrasetypeid&amp;page=$page&amp;perpage=$perpage");
		print_stop_message('deleted_phrase_successfully');
	}
	else
	{
		print_stop_message('invalid_phrase_specified');
	}
}

// #############################################################################

if ($_REQUEST['do'] == 'delete')
{
	globalize($_REQUEST, array(
		'phraseid' => INT,
		'page' => INT,
		'perpage' => INT,
	));

	//Check if Phrase belongs to Master Language -> only able to delete if $debug=1
	$getvarname = $DB_site->query_first("SELECT varname, phrasetypeid FROM " . TABLE_PREFIX . "phrase WHERE phraseid=$phraseid");
	$ismasterphrase = $DB_site->query_first("
		SELECT languageid FROM " . TABLE_PREFIX . "phrase
		WHERE varname = '" . $getvarname['varname'] . "' AND
			languageid = '-1'" . iif($getvarname['phrasetypeid'], " AND
			phrasetypeid = " . $getvarname['phrasetypeid'], '')
	);
	if (!$debug AND $ismasterphrase)
	{
		print_stop_message('cant_delete_master_phrase');
	}

	print_delete_confirmation('phrase', $phraseid, 'phrase', 'kill', 'phrase', array('phrasetypeid' => $getvarname['phrasetypeid'], 'page' => $page, 'perpage' => $perpage), $vbphrase['if_you_delete_this_phrase_translations_will_be_deleted']);

}

// #############################################################################

if ($_POST['do'] == 'update')
{
	globalize($_POST, array(
		'phrasetypeid' => INT,
		'languageid' => INT,
		'oldvarname' => STR,
		'varname' => STR,
		'text'
	));

	$text[0] = trim($text[0]);

	if ((empty($text[0]) AND $text[0] != '0') OR empty($varname))
	{
		print_stop_message('please_complete_required_fields');
	}

	if (!preg_match('#^[a-z0-9_\[\]]+$#i', $varname)) // match a-z, A-Z, 0-9, ',', _ only .. allow [] for help items
	{
		print_stop_message('invalid_phrase_varname');
	}

	if ($varname != $oldvarname AND $test = $DB_site->query_first("SELECT phraseid FROM " . TABLE_PREFIX . "phrase WHERE varname = '" . addslashes($varname) . "' AND languageid IN(0,-1) AND phrasetypeid = $phrasetypeid"))
	{
		print_stop_message('variable_name_exists', $oldvarname, $varname);
	}

	// delete old phrases
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "phrase WHERE varname = '" . addslashes($oldvarname) . "' AND phrasetypeid = $phrasetypeid");

	// now set some variables and go ahead to the insert action
	$update = 1;
	$_POST['ismaster'] = iif($languageid == -1, 1, 0);
	$_POST['do'] = 'insert';

}

// #############################################################################

if ($_POST['do'] == 'insert')
{
	globalize($_POST, array(
		'phrasetypeid' => INT,
		'varname' => STR,
		'text',
		'ismaster' => INT,
		'page' => INT,
		'perpage' => INT
	));

	$text[0] = trim($text[0]);

	if ((empty($text[0]) AND $text[0] != '0') OR empty($varname))
	{
		print_stop_message('please_complete_required_fields');
	}

	if (!preg_match('#^[a-z0-9_\[\]]+$#i', $varname)) // match a-z, A-Z, 0-9, ',', _ only .. allow [] for help items
	{
		print_stop_message('invalid_phrase_varname');
	}

	if (empty($update) AND $test = $DB_site->query_first("SELECT phraseid FROM " . TABLE_PREFIX . "phrase WHERE varname = '" . addslashes($varname) . "' AND languageid IN(0,-1) AND phrasetypeid = $phrasetypeid"))
	{
		print_stop_message('there_is_already_phrase_named_x', $varname);
	}

	if ($ismaster)
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "phrase
			(languageid, varname, text, phrasetypeid)
			VALUES
			(-1, '" . addslashes($varname) . "', '" . addslashes($text[0]) . "', $phrasetypeid)
		");
		unset($text[0]);
	}

	foreach($text AS $languageid => $txt)
	{
		$languageid = intval($languageid);
		$txt = trim($txt);
		if (!empty($txt) OR $txt == '0')
		{
			$DB_site->query("
				INSERT INTO " . TABLE_PREFIX . "phrase
				(languageid, varname, text, phrasetypeid)
				VALUES
				($languageid, '" . addslashes($varname) . "', '" . addslashes($txt) . "', $phrasetypeid)
			");
		}
	}

	build_language(-1);

	define('CP_REDIRECT', "phrase.php?$session[sessionurl]phrasetypeid=$phrasetypeid&amp;page=$page&amp;perpage=$perpage");
	print_stop_message('saved_phrase_x_successfully', $varname);
}

// #############################################################################

if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
?>
<script type="text/javascript">
function copy_default_text(targetlanguage)
{
	var deftext = fetch_object("default_phrase").value
	if (deftext == "")
	{
		alert("<?php echo $vbphrase['default_text_is_empty']; ?>");
	}
	else
	{
		fetch_object("text_" + targetlanguage).value = deftext;
	}
}
</script>
<?php
}

// #############################################################################

if ($_REQUEST['do'] == 'add')
{
	globalize($_REQUEST, array(
		'phrasetypeid' => INT,
		'page' => INT,
		'perpage' => INT
	));

	// make phrasetype options
	$phrasetypes = fetch_phrasetypes_array();
	$typeoptions = array();
	foreach($phrasetypes AS $id => $phrasetype)
	{
		$typeoptions["$id"] = $phrasetype['title'];
	}

	print_form_header('phrase', 'insert');
	print_table_header($vbphrase['add_new_phrase']);

	if ($debug)
	{
		print_yes_no_row(construct_phrase($vbphrase['insert_into_master_language_developer_option'], "<b></b>"), 'ismaster', iif($debug, 1, 0));
	}

	print_select_row($vbphrase['phrase_type'], 'phrasetypeid', $typeoptions, $phrasetypeid);

	// main input fields
	print_input_row($vbphrase['varname'], 'varname', '', 1, 60);
	print_label_row(
		$vbphrase['text'],
		"<textarea name=\"text[0]\" id=\"default_phrase\" rows=\"5\" cols=\"60\" wrap=\"virtual\" tabindex=\"1\" dir=\"ltr\"" . iif($debug, ' title="name=&quot;text[0]&quot;"') . "></textarea>",
		'', 'top', 'text[0]'
	);

	// do translation boxes
	print_table_header($vbphrase['translations']);
	print_description_row("
			<li>$vbphrase[phrase_translation_desc_1]</li>
			<li>$vbphrase[phrase_translation_desc_2]</li>
			<li>$vbphrase[phrase_translation_desc_3]</li>
		",
		0, 2, 'tfoot'
	);
	$languages = fetch_languages_array();
	foreach($languages AS $languageid => $lang)
	{
		print_label_row(
			construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . " <dfn>($vbphrase[optional])</dfn><br /><input type=\"button\" class=\"button\" value=\"$vbphrase[copy_default_text]\" tabindex=\"1\" onclick=\"copy_default_text($languageid);\" />",
			"<textarea name=\"text[$languageid]\" id=\"text_$languageid\" rows=\"5\" cols=\"60\" tabindex=\"1\" wrap=\"virtual\" dir=\"$lang[direction]\"></textarea>"
		);
		print_description_row('<img src="../' . $vboptions['cleargifurl'] . '" width="1" height="1" alt="" />', 0, 2, 'thead');
	}

	construct_hidden_code('page', $page);
	construct_hidden_code('perpage', $perpage);
	print_submit_row($vbphrase['save']);

}

// #############################################################################

if ($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'phraseid' => INT,
		'e',
		'page' => INT,
		'perpage' => INT,
	));
	$editvarname = &$e;

	// make phrasetype options
	$phrasetypes = fetch_phrasetypes_array();
	$typeoptions = array();
	foreach($phrasetypes AS $id => $phrasetype)
	{
		$typeoptions["$id"] = $phrasetype['title'];
	}

	if (empty($editvarname))
	{
		$phrase = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "phrase WHERE phraseid = $phraseid");
	}
	else

	{
		foreach($editvarname AS $phrasetypeid => $varnames)
		{
			foreach($varnames AS $varname => $null)
			{
				$varname = urldecode($varname);
				$phrase['phrasetypeid'] = $phrasetypeid;
				$phrase = $DB_site->query_first("
					SELECT * FROM " . TABLE_PREFIX . "phrase
					WHERE varname = '" . addslashes($varname) . "' AND
					phrasetypeid = " . $phrase['phrasetypeid'] . "
					ORDER BY languageid
					LIMIT 1
				");
				break;
			}
		}
	}

	if (!$phrase['phraseid'] OR !$phrase['varname'])
	{
		print_stop_message('no_phrases_matched_your_query');
	}

	// delete link
	if ($debug OR $phrase['languageid'] != '-1')
	{
		print_form_header('phrase', 'delete');
		construct_hidden_code('phraseid', $phrase['phraseid']);
		print_table_header($vbphrase['if_you_would_like_to_remove_this_phrase'] . ' &nbsp; &nbsp; <input type="submit" class="button" tabindex="1" value="' . $vbphrase['delete'] . '" />');
		print_table_footer();
	}

	//. '<input type="hidden" id="default_phrase" value="' . htmlspecialchars_uni($phrase['text']) . '" />'

	print_form_header('phrase', 'update', false, true, 'phraseform');

	print_table_header(construct_phrase($vbphrase['x_y_id_z'], iif(
		$phrase['languageid'] == 0,
		$vbphrase['custom_phrase'],
		$vbphrase['standard_phrase']
	), $phrase['varname'], $phrase['phraseid']));
	construct_hidden_code('mode', $mode);
	construct_hidden_code('oldvarname', $phrase['varname']);

	if ($debug)
	{
		print_select_row($vbphrase['language'], 'languageid', array('-1' => $vbphrase['master_language'], '0' => $vbphrase['custom_language']), $phrase['languageid']);
		print_select_row($vbphrase['phrase_type'], 'phrasetypeid', $typeoptions, $phrase['phrasetypeid']);
	}
	else
	{
		construct_hidden_code('languageid', $phrase['languageid']);
		construct_hidden_code('phrasetypeid', $phrase['phrasetypeid']);
	}

	if ($phrase['languageid'] == 0 OR $debug)
	{
		print_input_row($vbphrase['varname'], 'varname', $phrase['varname'], 1, 50);
		print_label_row(
			$vbphrase['text'],
			"<textarea name=\"text[0]\" id=\"default_phrase\" rows=\"4\" cols=\"50\" wrap=\"virtual\" tabindex=\"1\" dir=\"ltr\"" . iif($debug, ' title="name=&quot;text[0]&quot;"') . ">" . htmlspecialchars_uni($phrase['text']) . "</textarea>",
			'', 'top', 'text[0]'
		);
	}
	else
	{
		print_label_row($vbphrase['varname'], '$vbphrase[<b>' . $phrase['varname'] . '</b>]');
		construct_hidden_code('varname', $phrase['varname']);
		print_label_row($vbphrase['text'], htmlspecialchars_uni($phrase['text']) . '<input type="hidden" id="default_phrase" value="' . htmlspecialchars_uni($phrase['text']) . '" />');
		construct_hidden_code('text[0]', $phrase['text']);
	}

	// do translation boxes
	print_table_header($vbphrase['translations']);
	print_description_row("
			<li>$vbphrase[phrase_translation_desc_1]</li>
			<li>$vbphrase[phrase_translation_desc_2]</li>
			<li>$vbphrase[phrase_translation_desc_3]</li>
		",
		0, 2, 'tfoot'
	);

	$translations = $DB_site->query("
		SELECT languageid, text
		FROM " . TABLE_PREFIX . "phrase
		WHERE varname = '" . addslashes($phrase['varname']) . "' AND
		languageid <> $phrase[languageid] AND
		phrasetypeid = $phrase[phrasetypeid]
	");
	while ($translation = $DB_site->fetch_array($translations))
	{
		$text["$translation[languageid]"] = $translation['text'];
	}
	$languages = fetch_languages_array();
	foreach($languages AS $languageid => $lang)
	{
		print_label_row(
			construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . " <dfn>($vbphrase[optional])</dfn><br /><input type=\"button\" class=\"button\" class=\"smallfont\" value=\"$vbphrase[copy_default_text]\" tabindex=\"1\" onclick=\"copy_default_text($languageid);\" />",
			"<textarea name=\"text[$languageid]\" id=\"text_$languageid\" rows=\"5\" cols=\"60\" tabindex=\"1\" wrap=\"virtual\" dir=\"$lang[direction]\">" . htmlspecialchars_uni($text["$languageid"]) . "</textarea>"
		);
		print_description_row('<img src="../' . $vboptions['cleargifurl'] . '" width="1" height="1" alt="" />', 0, 2, 'thead');
	}

	construct_hidden_code('page', $page);
	construct_hidden_code('perpage', $perpage);
	print_submit_row($vbphrase['save']);

}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{
	globalize($_REQUEST, array(
		'phrasetypeid' => INT,
		'perpage' => INT,
		'page' => INT,
		'showpt',
	));

	/*if (!is_array($showpt))
	{
		$showpt = array('master' => 1, 'custom' => 1);
	}
	$checked = array();
	foreach ($showpt AS $type => $yesno)
	{
		$checked["$type$yesno"] = ' checked="checked"';
	}*/

	$phrasetypes = fetch_phrasetypes_array();

	// make sure $phrasetypeid is valid
	if ($phrasetypeid != -1 AND !isset($phrasetypes["$phrasetypeid"]))
	{
		$phrasetypeid = 1;
	}

	// check display values are valid
	if ($perpage < 1)
	{
		$perpage = 15;
	}
	if ($page < 1)
	{
		$page = 1;
	}

	// count phrases
	$countphrases = $DB_site->query_first("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "phrase AS phrase
		WHERE languageid IN(-1, 0)
		" . iif($phrasetypeid != -1, "AND phrasetypeid = $phrasetypeid")
	);

	$numphrases = &$countphrases['total'];
	$numpages = ceil($numphrases / $perpage);

	if ($page > $numpages)
	{
		$page = $numpages;
	}

	$showprev = false;
	$shownext = false;

	if ($page > 1)
	{
		$showprev = true;
	}
	if ($page < $numpages)
	{
		$shownext = true;
	}

	$pageoptions = array();
	for ($i = 1; $i <= $numpages; $i++)
	{
		$pageoptions["$i"] = "$vbphrase[page] $i / $numpages";
	}

	$phraseoptions = array('-1' => 'ALL PHRASE GROUPS');
	foreach($phrasetypes AS $id => $type)
	{
		$phraseoptions["$id"] = $type['title'];
	}

	print_form_header('phrase', 'modify', false, true, 'navform', '90%', '', true, 'get');
	echo '
	<colgroup span="5">
		<col style="white-space:nowrap"></col>
		<col></col>
		<col width="100%" align="center"></col>
		<col style="white-space:nowrap"></col>
		<col></col>
	</colgroup>
	<tr>
		<td class="thead">' . $vbphrase['phrase_type'] . ':</td>
		<td class="thead"><select name="phrasetypeid" class="bginput" tabindex="1" onchange="this.form.page.selectedIndex = 0; this.form.submit()">' . construct_select_options($phraseoptions, $phrasetypeid) . '</select></td>
		<td class="thead">' .
			'<input type="button"' . iif(!$showprev, ' disabled="disabled"') . ' class="button" value="&laquo; ' . $vbphrase['prev'] . '" tabindex="1" onclick="this.form.page.selectedIndex -= 1; this.form.submit()" />' .
			'<select name="page" tabindex="1" onchange="this.form.submit()" class="bginput">' . construct_select_options($pageoptions, $page) . '</select>' .
			'<input type="button"' . iif(!$shownext, ' disabled="disabled"') . ' class="button" value="' . $vbphrase['next'] . ' &raquo;" tabindex="1" onclick="this.form.page.selectedIndex += 1; this.form.submit()" />
		</td>
		<td class="thead">' . $vbphrase['phrases_to_show_per_page'] . ':</td>
		<td class="thead"><input type="text" class="bginput" name="perpage" value="' . $perpage . '" tabindex="1" size="5" /></td>
		<td class="thead"><input type="submit" class="button" value=" ' . $vbphrase['go'] . ' " tabindex="1" accesskey="s" /></td>
	</tr>';
	print_table_footer();

	/*print_form_header('phrase', 'modify');
	print_table_header($vbphrase['controls'], 3);
	echo '
	<tr>
		<td class="tfoot">
			<select name="phrasetypeid" class="bginput" tabindex="1" onchange="this.form.page.selectedIndex = 0; this.form.submit()">' . construct_select_options($phraseoptions, $phrasetypeid) . '</select><br />
			<table cellpadding="0" cellspacing="0" border="0">
			<tr>
				<td><b>Show Master Phrases?</b> &nbsp; &nbsp; &nbsp;</td>
				<td><label for="rb_smy"><input type="radio" name="showpt[master]" id="rb_smy" value="1"' . $checked['master1'] . ' />' . $vbphrase['yes'] . '</label></td>
				<td><label for="rb_smn"><input type="radio" name="showpt[master]" id="rb_smn" value="0"' . $checked['master0'] . ' />' . $vbphrase['no'] . '</label></td>
			</tr>
			<tr>
				<td><b>Show Custom Phrases?</b> &nbsp; &nbsp; &nbsp;</td>
				<td><label for="rb_scy"><input type="radio" name="showpt[custom]" id="rb_scy" value="1"' . $checked['custom1'] . ' />' . $vbphrase['yes'] . '</label></td>
				<td><label for="rb_scn"><input type="radio" name="showpt[custom]" id="rb_scn" value="0"' . $checked['custom0'] . ' />' . $vbphrase['no'] . '</label></td>
			</tr>
			</table>
		</td>
		<td class="tfoot" align="center">
			<div style="margin-bottom:4px"><b>' . $vbphrase['phrases_to_show_per_page'] . ':</b> <input type="text" class="bginput" name="perpage" value="' . $perpage . '" tabindex="1" size="5" /></div>
			<input type="button"' . iif(!$showprev, ' disabled="disabled"') . ' class="button" value="&laquo; ' . $vbphrase['prev'] . '" tabindex="1" onclick="this.form.page.selectedIndex -= 1; this.form.submit()" />' .
			'<select name="page" tabindex="1" onchange="this.form.submit()" class="bginput">' . construct_select_options($pageoptions, $page) . '</select>' .
			'<input type="button"' . iif(!$shownext, ' disabled="disabled"') . ' class="button" value="' . $vbphrase['next'] . ' &raquo;" tabindex="1" onclick="this.form.page.selectedIndex += 1; this.form.submit()" />
		</td>
		<td class="tfoot" align="center"><input type="submit" class="button" value=" ' . $vbphrase['go'] . ' " tabindex="1" accesskey="s" /></td>
	</tr>
	';
	print_table_footer();*/

	print_phrase_ref_popup_javascript();

	?>
	<script type="text/javascript">
	<!--
	function js_edit_phrase(id)
	{
		window.location = "phrase.php?s=<?php echo $session['sessionhash']; ?>&do=edit&phraseid=" + id;
	}
	// -->
	</script>
	<?php

	$masterphrases = $DB_site->query("
		SELECT varname, phrasetypeid
		FROM " . TABLE_PREFIX . "phrase AS phrase
		WHERE languageid IN(-1, 0)
		" . iif($phrasetypeid > 0, "AND phrasetypeid = $phrasetypeid") . "
		ORDER BY phrasetypeid, varname
		LIMIT " . (($page - 1) * $perpage) . ", $perpage
	");
	$phrasenames = array();
	while ($masterphrase = $DB_site->fetch_array($masterphrases))
	{
		$phrasenames[] = "(varname = '" . addslashes($masterphrase['varname']) . "' AND phrasetypeid = $masterphrase[phrasetypeid])";
	}
	unset($masterphrase);
	$DB_site->free_result($masterphrases);

	$cphrases = array();
	if (!empty($phrasenames))
	{
		$phrases = $DB_site->query("
			SELECT phraseid, languageid, varname, phrasetypeid
			FROM " . TABLE_PREFIX . "phrase AS phrase
			WHERE " . implode("
			OR ", $phrasenames) . "
			ORDER BY phrasetypeid, varname
		");
		unset($phrasenames);
		while ($phrase = $DB_site->fetch_array($phrases))
		{
			$cphrases["$phrase[phrasetypeid]"]["$phrase[varname]"]["$phrase[languageid]"] = $phrase['phraseid'];
		}
		unset($phrase);
		$DB_site->free_result($phrases);
	}

	$languages = fetch_languages_array();
	$numlangs = sizeof($languages);
	$colspan = $numlangs + 2;

	print_form_header('phrase', 'add');
	construct_hidden_code('phrasetypeid', $phrasetypeid);

	echo "\t<colgroup span=\"" . (sizeof($languages) + 1) . "\"></colgroup>\n";
	echo "\t<col style=\"white-space:nowrap\"></col>\n";

	// show phrases
	foreach($cphrases AS $phrasetypeid => $varnames)
	{
		print_table_header(construct_phrase($vbphrase['x_phrases'], $phrasetypes["$phrasetypeid"]['title']) . " <span class=\"normal\">(phrasetypeid = $phrasetypeid)</span>", $colspan);

		$headings = array($vbphrase['varname']);
		foreach($languages AS $languageid => $language)
		{
			$headings[] = "<a href=\"javascript:js_open_phrase_ref($language[languageid],$phrasetypeid);\" title=\"" . $vbphrase['view_quickref'] . ": $language[title]\">$language[title]</a>";
		}
		$headings[] = '';
		print_cells_row($headings, 0, 'thead');

		ksort($varnames);
		foreach($varnames AS $varname => $phrase)
		{
			$cell = array(construct_wrappable_varname($varname, 'font-weight:bold;', 'smallfont', 'span'));
			if (isset($phrase['-1']))
			{
				$phraseid = $phrase['-1'];
				$custom = 0;
			}
			else

			{
				$phraseid = $phrase['0'];
				$custom = 1;
			}
			foreach(array_keys($languages) AS $languageid)
			{
				$cell[] = "<img src=\"../cpstyles/$vboptions[cpstylefolder]/cp_tick_" . iif(isset($phrase["$languageid"]), 'yes', 'no') . ".gif\" alt=\"\" />";
			}
			$cell[] = '<span class="smallfont">' . construct_link_code(fetch_tag_wrap($vbphrase['edit'], 'span class="col-i"', $custom==1), "phrase.php?$session[sessionurl]do=edit&amp;phraseid=$phraseid&amp;page=$page&amp;perpage=$perpage") . iif($custom OR $debug, construct_link_code(fetch_tag_wrap($vbphrase['delete'], 'span class="col-i"', $custom==1), "phrase.php?$session[sessionurl]do=delete&amp;phraseid=$phraseid&amp;page=$page&amp;perpage=$perpage"), '') . '</span>';
			print_cells_row($cell, 0, 0, 0, 'top', 1);
		}
	}

	print_table_footer($colspan, "
		<input type=\"button\" class=\"button\" value=\"" . $vbphrase['search_in_phrases'] . "\" tabindex=\"1\" onclick=\"window.location='phrase.php?$session[sessionurl]&amp;do=search';\" />
		&nbsp; &nbsp;
		<input type=\"button\" class=\"button\" value=\"" . $vbphrase['add_new_phrase'] . "\" tabindex=\"1\" onclick=\"window.location='phrase.php?$session[sessionurl]do=add&amp;phrasetypeid=$phrasetypeid&amp;page=$page&amp;perpage=$perpage';\" />
		&nbsp; &nbsp;
		<input type=\"button\" class=\"button\" value=\"" . $vbphrase['find_orphan_phrases'] . "\" tabindex=\"1\" onclick=\"window.location='phrase.php?$session[sessionurl]do=findorphans';\" />
	");


}

// #############################################################################

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11201 $
|| ####################################################################
\*======================================================================*/
?>