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
* Fetches either the entire languages array, or a single language
*
* @param	integer	Lanugage ID - if specified, will return only that language, otherwise all languages
* @param	boolean	If true, fetch only languageid and title
*
* @return	array
*/
function fetch_languages_array($languageid = 0, $baseonly = false)
{
	global $vbulletin, $vbphrase;

	$languages = $vbulletin->db->query_read("
		SELECT languageid, title
		" . iif($baseonly == false, ', userselect, options, languagecode, charset, imagesoverride, dateoverride, timeoverride, registereddateoverride,
			calformat1override, calformat2override, logdateoverride, decimalsep, thousandsep, locale,
			IF(options & ' . $vbulletin->bf_misc_languageoptions['direction'] . ', \'ltr\', \'rtl\') AS direction'
		) . "
		FROM " . TABLE_PREFIX . "language
		" . iif($languageid, "WHERE languageid = $languageid", 'ORDER BY title')
	);

	if ($vbulletin->db->num_rows($languages) == 0)
	{
		print_stop_message('invalid_language_specified');
	}

	if ($languageid)
	{
		return $vbulletin->db->fetch_array($languages);
	}
	else
	{
		$languagearray = array();
		while ($language = $vbulletin->db->fetch_array($languages))
		{
			$languagearray["$language[languageid]"] = $language;
		}
		return $languagearray;
	}

}

/**
* Fetches an array of existing phrase types from the database
*
* @param	boolean	If true, will return names run through ucfirst()
*
* @return	array
*/
function fetch_phrasetypes_array($doUcFirst = false)
{
	global $vbulletin;

	$out = array();
	$phrasetypes = $vbulletin->db->query_read("SELECT * FROM " . TABLE_PREFIX . "phrasetype WHERE editrows <> 0");
	while ($phrasetype = $vbulletin->db->fetch_array($phrasetypes))
	{
		$out["{$phrasetype['phrasetypeid']}"] = $phrasetype;
		$out["{$phrasetype['phrasetypeid']}"]['field'] = $phrasetype['title'];
		$out["{$phrasetype['phrasetypeid']}"]['fieldname'] = $phrasetype['fieldname'];
		$out["{$phrasetype['phrasetypeid']}"]['title'] = ($doUcFirst ? ucfirst($phrasetype['title']) : $phrasetype['title']);
	}
	ksort($out);

	return $out;
}

/**
* Builds the languages datastore item
*
* @return	array	The data inserted into datastore
*/
function build_language_datastore()
{
	global $vbulletin;

	$languagecache = array();
	$languages = $vbulletin->db->query_read("
		SELECT languageid, title, userselect
		FROM " . TABLE_PREFIX . "language
		ORDER BY title
	");
	while ($language = $vbulletin->db->fetch_array($languages))
	{
		$languagecache["$language[languageid]"] = $language;
	}

	build_datastore('languagecache', serialize($languagecache));

	return $languagecache;
}

/**
* Reads a langauge or languages and updates the language db table with the denormalized phrase cache
*
* @param	integer	ID of language to be built; if -1, build all
* @param	integer	Not sure actually... any ideas?
*/
function build_language($languageid = -1, $phrasearray = 0)
{
	global $vbulletin;
	static $masterlang, $jsphrases = null;

	// load js safe phrases
	if ($jsphrases === null)
	{
		require_once(DIR . '/includes/class_xml.php');
		$xmlobj = new XMLparser(false, DIR . '/includes/xml/js_safe_phrases.xml');
		$safephrases = $xmlobj->parse();

		$jsphrases = array();

		if (is_array($safephrases['phrase']))
		{
			foreach ($safephrases['phrase'] AS $varname)
			{
				$jsphrases["$varname"] = true;
			}
		}
		unset($safephrases, $xmlobj);
	}


	// update all languages if this is the master language
	if ($languageid == -1)
	{
		$languages = $vbulletin->db->query_read("SELECT languageid FROM " . TABLE_PREFIX . "language");
		while ($language = $vbulletin->db->fetch_array($languages))
		{
			build_language($language['languageid']);
		}
		return;
	}

	// get phrase types for language update
	$gettypes = array();
	$phrasetypes = array();
	$getphrasetypes = $vbulletin->db->query_read("
		SELECT phrasetypeid, fieldname AS title
		FROM " . TABLE_PREFIX . "phrasetype
		WHERE editrows <> 0 AND
		phrasetypeid < 1000
	");
	while ($getphrasetype = $vbulletin->db->fetch_array($getphrasetypes))
	{
		$gettypes[] = $getphrasetype['phrasetypeid'];
		$phrasetypes["{$getphrasetype['phrasetypeid']}"] = $getphrasetype['title'];
	}
	unset($getphrasetype);
	$vbulletin->db->free_result($getphrasetypes);

	if (empty($masterlang))
	{
		$masterlang = array();

		$phrases = $vbulletin->db->query_read("
			SELECT phrasetypeid, varname, text
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid IN(-1,0) AND
			phrasetypeid IN (" . implode(',', $gettypes) . ")
		");
		while ($phrase = $vbulletin->db->fetch_array($phrases))
		{
			if (isset($jsphrases["$phrase[varname]"]))
			{
				$phrase['text'] = fetch_js_safe_string($phrase['text']);
			}
			if (strpos($phrase['text'], '{1}') !== false)
			{
				$phrase['text'] = str_replace('%', '%%', $phrase['text']);
			}
			$masterlang["{$phrase['phrasetypeid']}"]["$phrase[varname]"] = $phrase['text'];
		}
	}

	// get phrases for language update
	$phrasearray = $masterlang;
	$phrasetemplate = array();
	$phrases = $vbulletin->db->query_read("
		SELECT varname, text, phrasetypeid
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid = $languageid AND phrasetypeid IN (" . implode(',', $gettypes) . ")
	");

	while ($phrase = $vbulletin->db->fetch_array($phrases, DBARRAY_BOTH))
	{
		if (isset($jsphrases["$phrase[varname]"]))
		{
			$phrase['text'] = fetch_js_safe_string($phrase['text']);
		}
		if (strpos($phrase['text'], '{1}') !== false)
		{
			$phrase['text'] = str_replace('%', '%%', $phrase['text']);
		}
		$phrasearray["{$phrase['phrasetypeid']}"]["$phrase[varname]"] = $phrase['text'];
	}
	unset($phrase);
	$vbulletin->db->free_result($phrases);

	$SQL = 'title = title';

	foreach($phrasearray as $phrasetypeid => $phrases)
	{
		ksort($phrases);
		$cachefield = $phrasetypes["$phrasetypeid"];
		$phrases = preg_replace('/\{([0-9]+)\}/siU', '%\\1$s', $phrases);
		$cachetext = $vbulletin->db->escape_string(serialize($phrases));
		$SQL .= ", phrasegroup_$cachefield = '$cachetext'";
	}

	$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "language SET $SQL WHERE languageid = $languageid");

}

/**
* Fetches an array of phrases not present in the master language
*
* @param	integer	Language ID - language from which to fetch phrases
* @param	integer	Phrase type ID - 0 = all, -1 = all normal (< 1000), x = specified ID
*
* @return	array	array(array('varname' => 'phrase_varname', 'text' => 'Phrase Text'), array(... ))
*/
function fetch_custom_phrases($languageid, $phrasetypeid = 0)
{
	global $vbulletin;

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
			$phrasetypeSQL = "AND p1.phrasetypeid = $phrasetypeid";
			break;
	}

	$phrases = $vbulletin->db->query_read("
		SELECT p1.varname AS p1var, p1.text AS default_text, p1.phrasetypeid, IF(p1.languageid = -1, 'MASTER', 'USER') AS type,
		p2.phraseid, p2.varname AS p2var, p2.text, NOT ISNULL(p2.phraseid) AS found
		FROM " . TABLE_PREFIX . "phrase AS p1
		LEFT JOIN " . TABLE_PREFIX . "phrase AS p2 ON (p2.varname = p1.varname AND p2.phrasetypeid = p1.phrasetypeid AND p2.languageid = $languageid)
		WHERE p1.languageid = 0 $phrasetypeSQL
		ORDER BY p1.varname
	");

	if ($vbulletin->db->num_rows($phrases))
	{

		while($phrase = $vbulletin->db->fetch_array($phrases, DBARRAY_ASSOC))
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
		$vbulletin->db->free_result($phrases);
		return $phrasearray;

	}
	else
	{
		return array();
	}

}

/**
* Fetches an array of phrases found in the master set
*
* @param	integer	Language ID
* @param	integer	Phrase type ID - 0 = all, -1 = all normal (< 1000), x = specified ID
* @param	integer	Offset key for returned array
*
* @return	array	array(array('varname' => 'phrase_varname', 'text' => 'Phrase Text'), array(... ))
*/
function fetch_standard_phrases($languageid, $phrasetypeid = 0, $offset = 0)
{
	global $vbulletin;

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

	$phrases = $vbulletin->db->query_read("
		SELECT p1.varname AS p1var, p1.text AS default_text, p1.phrasetypeid, IF(p1.languageid = -1, 'MASTER', 'USER') AS type,
		p2.phraseid, p2.varname As p2var, p2.text, NOT ISNULL(p2.phraseid) AS found
		FROM " . TABLE_PREFIX . "phrase AS p1
		LEFT JOIN " . TABLE_PREFIX . "phrase AS p2 ON (p2.varname = p1.varname AND p2.phrasetypeid = p1.phrasetypeid AND p2.languageid = $languageid)
		WHERE p1.languageid = -1 $phrasetypeSQL
		ORDER BY p1.varname
	");

	while ($phrase = $vbulletin->db->fetch_array($phrases, DBARRAY_ASSOC))
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

	$vbulletin->db->free_result($phrases);

	return $phrasearray;

}

/**
* Imports a language from a language XML file
*
* @param	string	XML language string
* @param	integer	Language to overwrite
* @param	string	Override title for imported language
* @param	boolean	Allow import of language from mismatched vBulletin version
* @param	boolean	Allow user-select of imported language
*/
function xml_import_language($xml = false, $languageid = -1, $title = '', $anyversion = false, $userselect = true)
{
	global $vbulletin, $vbphrase;

	print_dots_start('<b>' . $vbphrase['importing_language'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

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
			print_stop_message('please_ensure_x_file_is_located_at_y', 'vbulletin-language.xml', $GLOBALS['path']);
	}

	if(!$arr =& $xmlobj->parse())
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line());
	}

	if (!$arr['phrasetype'])
	{
		print_dots_stop();
		print_stop_message('invalid_file_specified');
	}

	$title = (empty($title) ? $arr['name'] : $title);
	$version = $arr['vbversion'];
	$master = ($arr['type'] == 'master' ? 1 : 0);
	$just_phrases = ($arr['type'] == 'phrases' ? 1 : 0);

	if (!empty($arr['settings']))
	{
		$langinfo = $arr['settings'];
	}

	$langinfo['product'] = (empty($arr['product']) ? 'vbulletin' : $arr['product']);

	$arr = $arr['phrasetype'];

	foreach ($langinfo AS $key => $val)
	{
		$langinfo["$key"] = $vbulletin->db->escape_string(trim($val));
	}
	$langinfo['options'] = intval($langinfo['options']);

	if ($version != $vbulletin->options['templateversion'] AND !$anyversion AND !$master)
	{
		print_dots_stop();
		print_stop_message('upload_file_created_with_different_version', $vbulletin->options['templateversion'], $version);
	}

	// prepare for import
	if ($master)
	{
		// lets stop it from dieing cause someone borked a previous update
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "phrase WHERE languageid = -10");
		// master style
		echo "<h3>$vbphrase[master_language]</h3>\n<p>$vbphrase[please_wait]</p>";
		vbflush();
		$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "phrase SET languageid = -10 WHERE languageid = -1 AND (product = '" . $vbulletin->db->escape_string($langinfo['product']) . "'" . iif($langinfo['product'] == 'vbulletin', " OR product = ''") . ")");
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
			else if ($test = $vbulletin->db->query_first("SELECT languageid FROM " . TABLE_PREFIX . "language WHERE title = '" . $vbulletin->db->escape_string($title) . "'"))
			{
				print_dots_stop();
				print_stop_message('language_already_exists', $title);
			}
			else
			{
				echo "<h3><b>" . construct_phrase($vbphrase['creating_a_new_language_called_x'], $title) . "</b></h3>\n<p>$vbphrase[please_wait]</p>";
				vbflush();
				/*insert query*/
				$vbulletin->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "language (
						title, options, languagecode, charset,
						dateoverride, timeoverride, decimalsep, thousandsep,
						registereddateoverride, calformat1override, calformat2override, locale, logdateoverride
					) VALUES (
						'" . $vbulletin->db->escape_string($title) . "', $langinfo[options], '$langinfo[languagecode]', '$langinfo[charset]',
						'$langinfo[dateoverride]', '$langinfo[timeoverride]', '$langinfo[decimalsep]', '$langinfo[thousandsep]',
						'$langinfo[registereddateoverride]', '$langinfo[calformat1override]', '$langinfo[calformat2override]', '$langinfo[locale]', '$langinfo[logdateoverride]'
					)
				");
				$languageid = $vbulletin->db->insert_id();
			}
		}
		else
		{
			// overwriting an existing language
			if ($getlanguage = $vbulletin->db->query_first("SELECT title FROM " . TABLE_PREFIX . "language WHERE languageid = $languageid"))
			{
				if (!$just_phrases)
				{
					echo "<h3><b>" . construct_phrase($vbphrase['overwriting_language_x'], $getlanguage['title']) . "</b></h3>\n<p>$vbphrase[please_wait]</p>";
					vbflush();

					$vbulletin->db->query_write("
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
					");

					$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "phrase SET languageid = -10 WHERE languageid = $languageid AND (product = '" . $vbulletin->db->escape_string($langinfo['product']) . "'" . iif($langinfo['product'] == 'vbulletin', " OR product = ''") . ")");
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
		$getphrases = $vbulletin->db->query_read("
			SELECT varname, phrasetypeid
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid IN (0, -1)
		");
		while ($getphrase = $vbulletin->db->fetch_array($getphrases))
		{
			$globalPhrases["$getphrase[varname]~$getphrase[phrasetypeid]"] = true;
		}
	}

	// import language
	if (!is_array($arr[0]))
	{
		$arr = array($arr);
	}

	foreach (array_keys($arr) AS $key)
	{
		$phraseTypes =& $arr["$key"];

		$sql = array();
		$strlen = 0;

		$phraseType = (empty($phraseTypes['fieldname']) ? $phraseTypes['name'] : $phraseTypes['fieldname']);
		$phraseTypeId = intval($phraseTypeFields["$phraseType"]);
		if (!$phraseTypeId)
		{
			$phraseTypeId = intval($phraseTypes["$phraseType"]);
		}
		if (!is_array($phraseTypes['phrase'][0]))
		{
			$phraseTypes['phrase'] = array($phraseTypes['phrase']);
		}

		foreach($phraseTypes['phrase'] AS $phrase)
		{
			if ($master)
			{
				$insertLanguageId = -1;
			}
			else if (!isset($globalPhrases["$phrase[name]~$phraseTypeId"]))
			{
				$insertLanguageId = 0;
			}
			else
			{
				$insertLanguageId = $languageid;
			}

			$sql[] = "($insertLanguageId, $phraseTypeId, '" . $vbulletin->db->escape_string($phrase['name']) . "', '" . $vbulletin->db->escape_string($phrase['value']) . "', '" . $vbulletin->db->escape_string($langinfo['product']) . "')";
			$strlen += strlen(end($sql));

			if ($strlen > 102400)
			{
				// insert max of 100k of phrases at a time
				/*insert query*/
				$vbulletin->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, phrasetypeid, varname, text, product)
					VALUES
						" . implode(",\n", $sql)
				);

				$sql = array();
				$strlen = 0;
			}
		}

		if ($sql)
		{
			/*insert query*/
			$vbulletin->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, phrasetypeid, varname, text, product)
				VALUES
					" . implode(",\n", $sql)
			);
		}

		unset($arr["$key"], $phraseTypes);
	}

	unset($sql, $arr);

	// now delete any phrases that were moved into the temporary language for safe-keeping
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "phrase WHERE languageid = -10 AND (product = '" . $vbulletin->db->escape_string($langinfo['product']) . "'" . iif($langinfo['product'] == 'vbulletin', " OR product = ''") . ")");

	print_dots_stop();
}

/**
* Fetch SQL clause for haystack LIKE needle
*
* @param	string	Needle
* @param	string	Field to search (varname or text)
* @param	boolean	Search field is binary?
* @param	boolean	Do case-sensitive search?
*
* @return	string	'haystack LIKE needle' variant
*/
function fetch_field_like_sql($searchstring, $field, $isbinary = false, $casesensitive = false)
{
	global $vbulletin;

	if ($casesensitive)
	{
		return "BINARY $field LIKE '%" . $vbulletin->db->escape_string_like($searchstring) . "%'";
	}
	else if ($isbinary)
	{
		return "UPPER($field) LIKE UPPER('%" . $vbulletin->db->escape_string_like($searchstring) . "%')";
	}
	else
	{
		return "$field LIKE '%" . $vbulletin->db->escape_string_like($searchstring) . "%'";
	}
}

/**
* Fetches a string specifying the type of a phrase
*
* @param	integer	Language ID of phrase
* @param	string	Phrase name
*
* @return	string	Either $vbphrase['standard_phrase'], $vbphrase['custom_phrase'] or construct_phrase($vbphrase['x_translation'], $title)
*/
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

/**
* Highlights search terms in text
*
* @param	string	Needle
* @param	string	Haystack
*
* @return	string	Highlighted HTML
*/
function fetch_highlighted_search_results($searchstring, $text)
{
	return preg_replace('/(' . preg_quote(htmlspecialchars_uni($searchstring), '/') . ')/siU', '<span class="col-i" style="text-decoration:underline;">\\1</span>', htmlspecialchars_uni($text));
}

/**
* Wraps an HTML tag around a string.
*
* @param	string	Text to be wrapped
* @param	string	Tag name and attributes (eg: 'span class="smallfont"')
* @param	mixed	Optional - if evaluates to false, wrapping will not occur
*
* @return	string
*/
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

/**
* Prints a language row for use in language.php?do=modify
*
* @param	array	Language array containing languageid, title
*/
function print_language_row($language)
{
	global $vbulletin, $typeoptions, $vbphrase;
	$languageid = $language['languageid'];

	$cell = array();
	$cell[] = iif($vbulletin->debug AND $languageid != -1, '-- ', '') . fetch_tag_wrap($language['title'], 'b', $languageid == $vbulletin->options['languageid']);
	$cell[] = "<a href=\"language.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;dolanguageid=$languageid\">" . construct_phrase($vbphrase['edit_translate_x_y_phrases'], $language['title'], '') . "</a>";
	$cell[] =
		iif($languageid != -1,
			construct_link_code($vbphrase['edit_settings'], "language.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit_settings&amp;dolanguageid=$languageid").
			construct_link_code($vbphrase['delete'], "language.php?" . $vbulletin->session->vars['sessionurl'] . "do=delete&amp;dolanguageid=$languageid")
		) .
		construct_link_code($vbphrase['download'], "language.php?" . $vbulletin->session->vars['sessionurl'] . "do=files&amp;dolanguageid=$languageid")
	;
	$cell[] = iif($languageid != -1, "<input type=\"button\" class=\"button\" value=\"$vbphrase[set_default]\" tabindex=\"1\"" . iif($languageid == $vbulletin->options['languageid'], ' disabled="disabled"') . " onclick=\"window.location='language.php?" . $vbulletin->session->vars['sessionurl'] . "do=setdefault&amp;dolanguageid=$languageid';\" />", '');
	print_cells_row($cell, 0, '', -2);
}

/**
* Prints a phrase row for use in language.php?do=edit
*
* @param	array	Phrase array containing phraseid, varname, text, languageid
* @param	integer	Number of rows for textarea
* @param	integer	Not used?
* @param	string	ltr or rtl for direction
*/
function print_phrase_row($phrase, $editrows, $key = 0, $dir = 'ltr')
{
	global $vbphrase, $vbulletin;
	static $bgcount;

	if ($vbulletin->GPC['languageid'] == -1)
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
	print_description_row('<img src="../' . $vbulletin->options['cleargifurl'] . '" width="1" height="1" alt="" />', 0, 2, 'thead');

	$i++;
}

/**
* Constructs a version of a phrase varname that is browser-wrappable for display
*
* @param	string	Phrase varname
* @param	string	Extra CSS
* @param	string	CSS Classname
* @param	string	Wrap return value in this tag (eg: span, div)
*
* @return	string	HTML string
*/
function construct_wrappable_varname($varname, $extrastyles = '', $classname = 'smallfont', $tagname = 'span')
{
	return "<$tagname" . iif($classname, " class=\"$classname\"") . " style=\"word-spacing:-5px;" . iif($extrastyles, " $extrastyles") . "\" title=\"$varname\">" . str_replace('_', '_ ', $varname) . "</$tagname>";
}

/**
* Turns 'my_phrase_varname_3' into $varname = 'my_phrase_varname' ; $phrasetypeid = 3;
*
* @param	string	Incoming phrase varname (my_phrase_varname_3)
* @param	string	(Reference) Outgoing phrase varname
* @param	integer	(Reference)	Outgoing phrase phrasetypeid
*/
function fetch_varname_phrasetypeid($key, &$varname, &$phrasetypeid)
{
	$lastuscorepos = strrpos($key, '_');

	$varname = substr($key, 0, $lastuscorepos);
	$phrasetypeid = intval(substr($key, $lastuscorepos + 1));
}

/**
* Allows plugins etc. to add a phrasetype easily
*
* @param	string	Phrasetype name
* @param	string	Phrasetype title
* @param	string	Product ID
*
* @return	mixed	If insert succeeds, returns inserted phrasetypeid
*/
function add_phrase_type($phrasegroup_name, $phrasegroup_title, $productid = 'vbulletin')
{
	global $vbulletin;

	// first lets check if it exists
	if ($check = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "phrasetype WHERE fieldname = '$phrasegroup_name'"))
	{
		return false;
	}
	else
	{ // check max id
		$max_rows = $vbulletin->db->query_first("SELECT MAX(phrasetypeid) + 1 AS max FROM " . TABLE_PREFIX . "phrasetype WHERE phrasetypeid < 1000");
		$phrasetypeid = $max_rows['max'];
		if ($phrasetypeid)
		{
			/*insert query*/
			$vbulletin->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "phrasetype
				(phrasetypeid, fieldname, title, editrows, product)
				VALUES
				($phrasetypeid, '" . $vbulletin->db->escape_string($phrasegroup_name) . "', '" . $vbulletin->db->escape_string($phrasegroup_title) . "', 3, '" . $vbulletin->db->escape_string($productid) . "')
			");
			$vbulletin->db->query_write("ALTER TABLE " . TABLE_PREFIX . "language ADD phrasegroup_" . $vbulletin->db->escape_string($phrasegroup_name) . " MEDIUMTEXT NOT NULL");
			return $phrasetypeid;
		}
	}
	return false;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14519 $
|| ####################################################################
\*======================================================================*/
?>