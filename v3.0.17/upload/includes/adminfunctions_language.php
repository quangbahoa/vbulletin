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

// ###################### Start getlanguages #######################
function fetch_languages_array($languageid = 0, $baseonly = false)
{
	global $DB_site, $_BITFIELD, $vbphrase;

	$languages = $DB_site->query('
		SELECT languageid, title
		' . iif($baseonly == false, ', userselect, options, languagecode, charset, imagesoverride, dateoverride, timeoverride, registereddateoverride,
			calformat1override, calformat2override, logdateoverride, decimalsep, thousandsep, locale,
			IF(options & ' . $_BITFIELD['languageoptions']['direction'] . ', \'ltr\', \'rtl\') AS direction'
		) . '
		FROM ' . TABLE_PREFIX . 'language
		' . iif($languageid, "WHERE languageid = $languageid", 'ORDER BY title')
	);

	if ($DB_site->num_rows($languages) == 0)
	{
		print_stop_message('invalid_language_specified');
	}

	if ($languageid)
	{
		return $DB_site->fetch_array($languages);
	}
	else
	{
		$languagearray = array();
		while ($language = $DB_site->fetch_array($languages))
		{
			$languagearray["$language[languageid]"] = $language;
		}
		return $languagearray;
	}

}

// ###################### Start getphrasetypes #######################
function fetch_phrasetypes_array($doUcFirst = false)
{
	global $DB_site;

	$out = array();
	$phrasetypes = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "phrasetype WHERE editrows <> 0");
	while ($phrasetype = $DB_site->fetch_array($phrasetypes))
	{
		$out["$phrasetype[phrasetypeid]"] = $phrasetype;
		$out["$phrasetype[phrasetypeid]"]['field'] = $phrasetype['title'];
		$out["$phrasetype[phrasetypeid]"]['fieldname'] = $phrasetype['fieldname'];
		$out["$phrasetype[phrasetypeid]"]['title'] = iif($doUcFirst, ucfirst($phrasetype['title']), $phrasetype['title']);
	}
	ksort($out);

	return $out;
}

// ###################### Start update_language #######################
function build_language($languageid = -1, $phrasearray = 0)
{
	global $DB_site, $vboptions;
	static $masterlang;

	// update all languages if this is the master language
	if ($languageid == -1)
	{
		$languages = $DB_site->query("SELECT languageid FROM " . TABLE_PREFIX . "language");
		while ($language = $DB_site->fetch_array($languages))
		{
			build_language($language['languageid']);
		}
		return;
	}

	// get phrase types for language update
	$gettypes = array();
	$phrasetypes = array();
	$getphrasetypes = $DB_site->query("
		SELECT phrasetypeid, fieldname AS title
		FROM " . TABLE_PREFIX . "phrasetype
		WHERE editrows <> 0 AND
		phrasetypeid < 1000
	");
	while ($getphrasetype = $DB_site->fetch_array($getphrasetypes))
	{
		$gettypes[] = $getphrasetype['phrasetypeid'];
		$phrasetypes["$getphrasetype[phrasetypeid]"] = $getphrasetype['title'];
	}
	unset($getphrasetype);
	$DB_site->free_result($getphrasetypes);

	if (empty($masterlang))
	{
		$masterlang = array();

		$phrases = $DB_site->query("
			SELECT phrasetypeid, varname, text
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid IN(-1,0) AND
			phrasetypeid IN (" . implode(',', $gettypes) . ")
		");
		while ($phrase = $DB_site->fetch_array($phrases))
		{
			$masterlang["$phrase[phrasetypeid]"]["$phrase[varname]"] = $phrase['text'];
		}
	}

	// get phrases for language update
	$phrasearray = $masterlang;
	$phrasetemplate = array();
	$phrases = $DB_site->query("
		SELECT varname, text, phrasetypeid
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid = $languageid AND phrasetypeid IN (" . implode(',', $gettypes) . ")
	");

	while ($phrase = $DB_site->fetch_array($phrases, DBARRAY_BOTH))
	{
		$phrasearray["$phrase[phrasetypeid]"]["$phrase[varname]"] = $phrase['text'];
	}
	unset($phrase);
	$DB_site->free_result($phrases);

	$SQL = 'title = title';

	foreach($phrasearray as $phrasetypeid => $phrases)
	{
		ksort($phrases);
		$cachefield = $phrasetypes["$phrasetypeid"];
		$phrases = preg_replace('/\{([0-9])+\}/siU', '%\\1$s', $phrases);
		$cachetext = addslashes(serialize($phrases));
		$SQL .= ", phrasegroup_$cachefield = '$cachetext'";
	}

	$DB_site->query("UPDATE " . TABLE_PREFIX . "language SET $SQL WHERE languageid = $languageid");

}

// ###################### Start get_custom_phrases #######################
function fetch_custom_phrases($languageid, $phrasetypeid = 0)
{
	global $DB_site;

	if ($languageid == -1)
	{
		return array();
	}

	switch ($phrasetypeid)
	{
		case 0:
			$phrasetypeSQL = '';
			break;
		case -1:
			$phrasetypeSQL = 'AND p1.phrasetypeid < 1000';
			break;
		default:
			$phrasetypeSQL = "AND p1.phrasetypeid=$phrasetypeid";
			break;
	}

	$phrases = $DB_site->query("
		SELECT p1.varname AS p1var, p1.text AS default_text, p1.phrasetypeid, IF(p1.languageid = -1, 'MASTER', 'USER') AS type,
		p2.phraseid, p2.varname AS p2var, p2.text, NOT ISNULL(p2.phraseid) AS found
		FROM " . TABLE_PREFIX . "phrase AS p1
		LEFT JOIN " . TABLE_PREFIX . "phrase AS p2 ON (p2.varname = p1.varname AND p2.phrasetypeid = p1.phrasetypeid AND p2.languageid = $languageid)
		WHERE p1.languageid = 0 $phrasetypeSQL
		ORDER BY p1.varname
	");

	if ($DB_site->num_rows($phrases))
	{

		while($phrase = $DB_site->fetch_array($phrases, DBARRAY_ASSOC))
		{
			if ($phrase['p2var'] != NULL)
			{
				$phrase['varname'] = $phrase['p2var'];
			}
			else

			{
				$phrase['varname'] = $phrase['p1var'];
			}
			if ($phrase['found'] == 0)
			{
				$phrase['text'] = $phrase['default_text'];
			}
			$phrasearray[] = $phrase;
		}
		$DB_site->free_result($phrases);
		return $phrasearray;

	}
	else
	{
		return array();
	}

}

// ###################### Start get_standard_phrases #######################
function fetch_standard_phrases($languageid, $phrasetypeid = 0, $offset = 0)
{
	global $DB_site;

	switch ($phrasetypeid)
	{
		case 0:
			$phrasetypeSQL = '';
			break;
		case -1:
			$phrasetypeSQL = 'AND p1.phrasetypeid < 1000';
			break;
		default:
			$phrasetypeSQL = "AND p1.phrasetypeid = $phrasetypeid";
			break;
	}

	$phrases = $DB_site->query("
		SELECT p1.varname AS p1var, p1.text AS default_text, p1.phrasetypeid, IF(p1.languageid = -1, 'MASTER', 'USER') AS type,
		p2.phraseid, p2.varname As p2var, p2.text, NOT ISNULL(p2.phraseid) AS found
		FROM " . TABLE_PREFIX . "phrase AS p1
		LEFT JOIN " . TABLE_PREFIX . "phrase AS p2 ON (p2.varname = p1.varname AND p2.phrasetypeid = p1.phrasetypeid AND p2.languageid = $languageid)
		WHERE p1.languageid = -1 $phrasetypeSQL
		ORDER BY p1.varname
	");

	while ($phrase = $DB_site->fetch_array($phrases, DBARRAY_ASSOC))
	{
		if ($phrase['p2var'] != NULL)
		{
			$phrase['varname'] = $phrase['p2var'];
		}
		else
		{
			$phrase['varname'] = $phrase['p1var'];
		}
		if ($phrase['found'] == 0)
		{
			$phrase['text'] = $phrase['default_text'];
		}
		$phrasearray["$offset"] = $phrase;
		$offset++;
	}

	$DB_site->free_result($phrases);

	return $phrasearray;

}

// ###################### Start xml_otag_language #######################
// opening tag function
function xml_parse_language_otag($parser, $name, $attrs)
{
	global $arr, $phraseType, $phraseName, $langinfo, $numphrases, $insettings, $settingname;

	switch($name)
	{
		case 'language':
			$langinfo['title'] = $attrs['name'];
			$langinfo['vbversion'] = $attrs['vbversion'];
			$langinfo['ismaster'] = iif($attrs['type'] == 'master', 1, 0);
			$langinfo['just_phrases'] = iif($attrs['type'] == 'phrases', 1, 0);
			$phraseName = false;
			$insettings = false;
		break;

		case 'phrasetype':
			if ($attrs['fieldname'])
			{
				$phraseType = $attrs['fieldname'];
			}
			else
			{
				$phraseType = $attrs['name'];
			}
			$arr["$phraseType"] = array();
			$phraseName = false;
			$insettings = false;
		break;

		case 'phrase':
			$numphrases++;
			$phraseName = $attrs['name'];
			$arr["$phraseType"]["$phraseName"] = '';
			$insettings = false;
		break;

		case 'settings':
			$insettings = true;
		break;

		default:
			if ($insettings)
			{
				$settingname = $name;
				$langinfo["$settingname"] = '';
			}
		break;
	}
}

// ###################### Start xml_ctag_language #######################
function xml_parse_language_ctag($parser, $name)
{
	global $phraseName, $insettings;

	if ($name == 'phrasetype' OR $name == 'language' OR $name == 'phrase')
	{
		$phraseName = false;
		$insettings = false;
	}
}

// ###################### Start xml_cdata_language #######################
function xml_parse_language_cdata($parser, $data)
{
	global $arr, $phraseType, $phraseName, $insettings, $settingname, $langinfo;

	if ($phraseName)
	{
		$arr["$phraseType"]["$phraseName"] .= $data;
	}
	else if ($insettings AND $settingname)
	{
		$langinfo["$settingname"] .= $data;
	}
}

// ###################### Start xml_importlanguage #######################
function xml_import_language($xml = false, $languageid = -1, $title = '', $anyversion = 0, $userselect = 1)
{
	global $DB_site, $vboptions, $arr, $langinfo, $numphrases, $vbphrase;

	print_dots_start('<b>' . $vbphrase['importing_language'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

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
			print_stop_message('please_ensure_x_file_is_located_at_y', 'vbulletin-language.xml', $GLOBALS['path']);
		}
	}

	// initialize vars
	$numphrases = 0;
	$arr = array();

	// create parser
	$parser = xml_parser_create('ISO-8859-1');

	// set parser options
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_set_element_handler($parser, 'xml_parse_language_otag', 'xml_parse_language_ctag');
	xml_set_character_data_handler($parser, 'xml_parse_language_cdata');

	// parse the XML
	if (!@xml_parse($parser, $xml))
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', xml_error_string(xml_get_error_code($parser)), xml_get_current_line_number($parser));
	}

	// free the parser
	xml_parser_free($parser);

	// ***********
	#print_dots_stop();
	#print_array($langinfo);
	#exit;
	// ***********

	if (empty($arr))
	{
		print_dots_stop();
		print_stop_message('invalid_or_empty_language_file');
	}

	$version = $langinfo['vbversion'];
	$master = $langinfo['ismaster'];
	$just_phrases = $langinfo['just_phrases'];

	$title = iif(empty($title), $langinfo['title'], $title);

	foreach ($langinfo AS $key => $val)
	{
		$langinfo["$key"] = addslashes(trim($val));
	}
	$langinfo['options'] = intval($langinfo['options']);

	if ($version != $vboptions['templateversion'] AND !$anyversion AND !$master)
	{
		print_dots_stop();
		print_stop_message('upload_file_created_with_different_version', $vboptions['templateversion'], $version);
	}

	// prepare for import
	if ($master)
	{
		// lets stop it from dieing cause someone borked a previous update
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "phrase WHERE languageid = -10");
		// master style
		echo "<h3>$vbphrase[master_language]</h3>\n<p>$vbphrase[please_wait]</p>";
		flush();
		$DB_site->query("UPDATE " . TABLE_PREFIX . "phrase SET languageid = -10 WHERE languageid = -1");
		$languageid = -1;
	}
	else
	{
		if ($languageid == 0)
		{
			// creating a new language
			if ($just_phrases)
			{
				print_dots_stop();
				print_stop_message('language_only_phrases', $title);
			}
			else if ($test = $DB_site->query_first("SELECT languageid FROM " . TABLE_PREFIX . "language WHERE title = '" . addslashes($title) . "'"))
			{
				print_dots_stop();
				print_stop_message('language_already_exists', $title);
			}
			else
			{
				echo "<h3><b>" . construct_phrase($vbphrase['creating_a_new_language_called_x'], $title) . "</b></h3>\n<p>$vbphrase[please_wait]</p>";
				flush();
				$sql = "
					INSERT INTO " . TABLE_PREFIX . "language (
						title, options, languagecode, charset,
						dateoverride, timeoverride, decimalsep, thousandsep,
						registereddateoverride, calformat1override, calformat2override, locale, logdateoverride
					) VALUES (
						'" . addslashes($title) . "', $langinfo[options], '$langinfo[languagecode]', '$langinfo[charset]',
						'$langinfo[dateoverride]', '$langinfo[timeoverride]', '$langinfo[decimalsep]', '$langinfo[thousandsep]',
						'$langinfo[registereddateoverride]', '$langinfo[calformat1override]', '$langinfo[calformat2override]', '$langinfo[locale]', '$langinfo[logdateoverride]'
					)
				";
				$DB_site->query($sql);
				$languageid = $DB_site->insert_id();
			}
		}
		else
		{
			// overwriting an existing language
			if ($getlanguage = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "language WHERE languageid = $languageid"))
			{
				if (!$just_phrases)
				{
					echo "<h3><b>" . construct_phrase($vbphrase['overwriting_language_x'], $getlanguage['title']) . "</b></h3>\n<p>$vbphrase[please_wait]</p>";
					flush();

					$sql = "
						UPDATE " . TABLE_PREFIX . "language SET
							options = $langinfo[options],
							languagecode = '$langinfo[languagecode]',
							charset = '$langinfo[charset]',
							locale = '$langinfo[locale]',
							imagesoverride = '$langinfo[imagesoverride]',
							dateoverride = '$langinfo[dateoverride]',
							timeoverride = '$langinfo[timeoverride]',
							decimalsep = '$langinfo[decimalsep]',
							thousandsep = '$langinfo[thousandsep]',
							registereddateoverride = '$langinfo[registereddateoverride]',
							calformat1override = '$langinfo[calformat1override]',
							calformat2override = '$langinfo[calformat2override]',
							logdateoverride = '$langinfo[logdateoverride]'
						WHERE languageid = $languageid
					";
					$DB_site->query($sql);

					$sql = "UPDATE " . TABLE_PREFIX . "phrase SET languageid = -10 WHERE languageid = $languageid";
					$DB_site->query($sql);
				}
			}
			else
			{
				print_stop_message('cant_overwrite_non_existent_language');
			}
		}
	}

	// get phrase types
	$phraseTypes = array();
	foreach(fetch_phrasetypes_array(false) as $phraseType)
	{
		$phraseTypes["$phraseType[title]"] = $phraseType['phrasetypeid'];
		$phraseTypeFields["$phraseType[fieldname]"] = $phraseType['phrasetypeid'];
	}

	if (!$master)
	{
		$globalPhrases = array();
		$getphrases = $DB_site->query("
			SELECT varname, phrasetypeid
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid IN (0, -1)
		");
		while($getphrase = $DB_site->fetch_array($getphrases))
		{
			$globalPhrases["$getphrase[varname]~$getphrase[phrasetypeid]"] = true;
		}
	}

	// import master language
	require_once('./includes/functions_xml.php');
	foreach($arr as $phraseType => $phrases)
	{
		$sql = array();
		$phraseTypeId = intval($phraseTypeFields["$phraseType"]);
		if (!$phraseTypeId)
		{
			$phraseTypeId = intval($phraseTypes["$phraseType"]);
		}
		foreach($phrases as $phraseName => $phrase)
		{
			if ($master)
			{
				$insertLanguageId = -1;
			}
			else if (!isset($globalPhrases["$phraseName~$phraseTypeId"]))
			{
				$insertLanguageId = 0;
			}
			else
			{
				$insertLanguageId = $languageid;
			}
			$sql[] = "($insertLanguageId, $phraseTypeId, '" . addslashes($phraseName) . "', '" . addslashes(xml_unescape_cdata($phrase)) . "')";
		}
		if ($just_phrases)
		{
			$sql = "REPLACE INTO " . TABLE_PREFIX . "phrase\n(languageid, phrasetypeid, varname, text)\nVALUES\n" . implode(",\n", $sql);
		}
		else
		{
			$sql = "INSERT INTO " . TABLE_PREFIX . "phrase\n(languageid, phrasetypeid, varname, text)\nVALUES\n" . implode(",\n", $sql);
		}
		$DB_site->query($sql);
	}

	// I AM LEAVING THIS INACTIVE FOR NOW, AS THE 'FIND ORPHANS' TOOL DOES THE SAME JOB BUT
	// LEAVES THE DECISION OVER WHAT TO DO WITH ORPHANS TO THE ADMINISTRATOR'S DISCRETION - KD
	/*
	// now find any phrases that have been orphaned and move them into the custom language
	if ($master)
	{
		// this query finds phrases in languageid -10 (the outgoing language -1)
		// whose varnames do not exist in the new language -1 or language 0
		// but which have translations in other languages
		$orphans = $DB_site->query("
			SELECT DISTINCT orphan.varname, orphan.phrasetypeid
			FROM " . TABLE_PREFIX . "phrase AS orphan
			LEFT JOIN " . TABLE_PREFIX . "phrase AS phrase ON (phrase.languageid IN(-1, 0) AND phrase.varname = orphan.varname)# AND phrase.phrasetypeid = orphan.phrasetypeid
			INNER JOIN " . TABLE_PREFIX . "phrase AS custom ON (custom.languageid > 0 AND custom.varname = orphan.varname)# AND custom.phrasetypeid = orphan.phrasetypeid
			WHERE orphan.languageid = -10
			AND phrase.phraseid IS NULL
		");
		if ($DB_site->num_rows($orphans))
		{
			$orphansql = array();
			while ($orphan = $DB_site->fetch_array($orphans))
			{
				$orphansql[] = "(varname = '" . addslashes($orphan['varname']) . "' AND phrasetypeid = $orphan[phrasetypeid])";
			}

			// if orphan phrases were found, update the old master phrase parents
			// to be custom phrase parents so that the translated phrases are not orphaned
			$q = "
				UPDATE " . TABLE_PREFIX . "phrase
				SET languageid = 0
				WHERE languageid = -10 AND
				(
					" . implode('
					OR
					', $orphansql) . "
				)";
			$DB_site->query($q);
		}
		$DB_site->free_result($orphans);
	}
	*/

	// now delete any phrases that were moved into the temporary language for safe-keeping
	$sql = "DELETE FROM " . TABLE_PREFIX . "phrase WHERE languageid = -10";
	$DB_site->query($sql);

	print_dots_stop();
}

// ###################### Start makeSQL #######################
function fetch_field_like_sql($field, $isbinary = false)
{
	global $casesensitive;
	$searchstring = addslashes($GLOBALS['searchstring']);
	if ($casesensitive)
	{
		return "BINARY $field LIKE '%" . addslashes_like($searchstring) . "%'";
	}
	else
	{
		if ($isbinary)
		{
			return "UPPER($field) LIKE UPPER('%" . addslashes_like($searchstring) . "%')";
		}
		else
		{
			return "$field LIKE '%" . addslashes_like($searchstring) . "%'";
		}
	}
}

// ###################### Start getlangtype #######################
function fetch_language_type_string($languageid, $title)
{
   global $vbphrase;
	switch($languageid)
	{
		case -1:
			return $vbphrase['standard_phrase'];
		case  0:
			return $vbphrase['custom_phrase'];
		default:
			return construct_phrase($vbphrase['x_translation'], $title);
	}
}

// ###################### Start highlightSearch #######################
function fetch_highlighted_search_results($text)
{
	global $searchstring;
	return preg_replace('/(' . preg_quote(htmlspecialchars_uni($searchstring), '/') . ')/siU', '<span class="col-i" style="text-decoration:underline;">\\1</span>', htmlspecialchars_uni($text));
}

// ###################### Start wraptags #######################
// wraps a tag around a string. optional condition (3) to set wrap tags or no wrap tags
// example: fetch_tag_wrap('hello', 'span class="myspan"', $one==$one);
// returns: <span class="myspan">hello</span>
function fetch_tag_wrap($text, $tag, $condition = '1=1')
{
	if ($condition)
	{
		if ($pos = strpos($tag, ' '))
		{
			$endtag = substr($tag, 0, $pos);
		}
		else
		{
			$endtag = $tag;
		}
		return "<$tag>$text</$endtag>";
	}
	else
	{
		return $text;
	}
}

// ###################### Start show_language #######################
function print_language_row($language)
{
	global $debug, $vboptions, $typeoptions, $vbphrase;
	$languageid = $language['languageid'];

	$cell = array();
	$cell[] = iif($debug AND $languageid != -1, '-- ', '') . fetch_tag_wrap($language['title'], 'b', $languageid == $vboptions['languageid']);
	/*$cell[] = "<select name=\"edit$languageid\" onchange=\"if(this.options[this.selectedIndex].value != 0) { window.location=('language.php?$session[sessionurl]do=edit&amp;languageid=$languageid&amp;phrasetypeid=' + this.options[this.selectedIndex].value); }\" class=\"bginput\">
		<option value=\"0\">" . $vbphrase['edit_phrases'] . "</option>
		<optgroup>
		" . construct_select_options($typeoptions) . "</optgroup>
		</select>";*/
	$cell[] = "<a href=\"language.php?$session[sessionurl]do=edit&amp;languageid=$languageid\">" . construct_phrase($vbphrase['edit_translate_x_y_phrases'], $language['title'], '') . "</a>";
	$cell[] =
		iif($languageid != -1,
			construct_link_code($vbphrase['edit_settings'], "language.php?$session[sessionurl]do=edit_settings&amp;languageid=$languageid").
			construct_link_code($vbphrase['delete'], "language.php?$session[sessionurl]do=delete&amp;languageid=$languageid")
		) .
		construct_link_code($vbphrase['download'], "language.php?$session[sessionurl]do=download&amp;languageid=$languageid", 1)
	;
	$cell[] = iif($languageid != -1, "<input type=\"button\" class=\"button\" value=\"$vbphrase[set_default]\" tabindex=\"1\"" . iif($languageid == $vboptions['languageid'], ' disabled="disabled"') . " onclick=\"window.location='language.php?$session[sessionurl]do=setdefault&amp;languageid=$languageid';\" />", '');
	print_cells_row($cell, 0, '', -2);
}

// #############################################################################
function print_phrase_row($phrase, $editrows, $key = 0, $dir = 'ltr')
{
	global $vbphrase, $languageid, $vboptions;
	static $bgcount;

	if ($languageid == -1)
	{
		$phrase['found'] = 0;
	}

	if ($bgcount++ % 2 == 0)
	{
		$class = 'alt1';
		$altclass = 'alt2';
	}
	else
	{
		$class = 'alt2';
		$altclass = 'alt1';
	}

	construct_hidden_code('def[' . urlencode($phrase['varname']) . ']', $phrase['text']);

	print_label_row(
		"<span class=\"smallfont\" title=\"\$vbphrase['$phrase[varname]']\" style=\"word-spacing:-5px\">
		<b>" . str_replace('_', '_ ', $phrase['varname']) . "</b>
		</span>" . iif($phrase['found'], " <dfn><br /><label for=\"rvt$phrase[phraseid]\"><input type=\"checkbox\" name=\"rvt[$phrase[varname]]\" id=\"rvt$phrase[phraseid]\" value=\"$phrase[phraseid]\" tabindex=\"1\" />$vbphrase[revert]</label></dfn>"),
		"<div class=\"$altclass\" style=\"padding:4px; border:inset 1px;\"><span class=\"smallfont\" title=\"" . $vbphrase['default_text'] . "\">" .
		iif($phrase['found'], "<label for=\"rvt$phrase[phraseid]\">" . nl2br(htmlspecialchars_uni($phrase['default_text'])) . "</label>", nl2br(htmlspecialchars_uni($phrase['default_text']))) .
		"</span></div><textarea class=\"code-" . iif($phrase['found'], 'c', 'g') . "\" name=\"phr[" . urlencode($phrase['varname']) . "]\" rows=\"$editrows\" cols=\"70\" tabindex=\"1\" dir=\"$dir\">" . htmlspecialchars_uni($phrase['text']) . "</textarea>",
		$class
	);
	print_description_row('<img src="../' . $vboptions['cleargifurl'] . '" width="1" height="1" alt="" />', 0, 2, 'thead');

	#print_description_row($phrase['varname'], 0, 2, 'thead');
	#print_description_row("
	#	<fieldset style=\"margin-bottom: 8px\">
	#	<legend>" . $vbphrase['default_text'] . "</legend>
	#	<div class=\"smallfont\">" . nl2br(htmlspecialchars_uni($phrase['default_text'])) . "</div>
	#	" . iif($phrase['found'], "<label for=\"rvt$phrase[phraseid]\"><input type=\"checkbox\" name=\"rvt[$phrase[varname]]\" id=\"rvt$phrase[phraseid]\" value=\"$phrase[phraseid]\" />$vbphrase[revert]</label>") . "
	#	</fieldset>
	#	<textarea class=\"code-" . iif($phrase['found'], 'c', 'g') . "\" name=\"phr[$phrase[varname]]\" rows=\"$editrows\" cols=\"70\" style=\"width: 100%\">" . htmlspecialchars_uni($phrase['text']) . "</textarea>
	#", 0, 2, $class . '" style="padding: 10px');

	$i++;
}

// #############################################################################
function construct_wrappable_varname($varname, $extrastyles = '', $classname = 'smallfont', $tagname = 'span')
{
	return "<$tagname" . iif($classname, " class=\"$classname\"") . " style=\"word-spacing:-5px;" . iif($extrastyles, " $extrastyles") . "\" title=\"$varname\">" . str_replace('_', '_ ', $varname) . "</$tagname>";
}

// #############################################################################
// turns 'my_phrase_varname_3' into $varname = 'my_phrase_varname' ; $phrasetypeid = 3;
function fetch_varname_phrasetypeid($key)
{
	global $varname, $phrasetypeid;

	$lastuscorepos = strrpos($key, '_');

	$varname = substr($key, 0, $lastuscorepos);
	$phrasetypeid = intval(substr($key, $lastuscorepos + 1));
}

// #############################################################################
// function to allow modifications to add a phrasetype easily
function add_phrase_type($phrasegroup_name, $phrasegroup_title)
{
	global $DB_site;

	// first lets check if it exists
	if ($check = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "phrasetype WHERE fieldname = '$phrasegroup_name'"))
	{
		return false;
	}
	else
	{ // check max id
		$max_rows = $DB_site->query_first("SELECT MAX(phrasetypeid) + 1 AS max FROM " . TABLE_PREFIX . "phrasetype WHERE phrasetypeid < 1000");
		$phrasetypeid = $max_rows['max'];
		if ($phrasetypeid)
		{
			$DB_site->query("INSERT INTO " . TABLE_PREFIX . "phrasetype (phrasetypeid, fieldname, title, editrows) VALUES ($phrasetypeid, '" . addslashes($phrasegroup_name) . "', '" . addslashes($phrasegroup_title) . "', 3)");
			$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "language ADD phrasegroup_" . addslashes($phrasegroup_name) . " MEDIUMTEXT NOT NULL");
			return $phrasetypeid;
		}
	}
	return false;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11091 $
|| ####################################################################
\*======================================================================*/
?>