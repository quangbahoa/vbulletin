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

// ###################### Start getHelpPhraseName #######################
// return the correct short name for a help topic
function fetch_help_phrase_short_name($item, $suffix = '')
{
	return $item['script'] . iif($item['action'], '_' . str_replace(',', '_', $item['action'])) . iif($item['optionname'], "_$item[optionname]") . $suffix;
}

// ###################### Start xml_helptopics_otag #######################
// parse XML opening tag
function xml_parse_help_topics_otag($parser, $name, $attrs)
{
	global $arr, $counter, $tagname, $scriptName;

	switch($name)
	{
		case 'helpscript':
			$counter = 0;
			$scriptName = $attrs['name'];
			$arr["$scriptName"] = array();
			$tagname = false;
		break;
		case 'helptopic':
			$counter++;
			$arr["$scriptName"]["$counter"] = array
			(
				'action' => $attrs['act'],
				'optionname' => $attrs['opt'],
				'displayorder' => intval($attrs['disp'])
			);
			$tagname = false;
		break;
	}
}

// ###################### Start xml_helptopics_ctag #######################
// parse XML closing tag
function xml_parse_help_topics_ctag($parser, $name)
{
	global $tagname;

	if ($tagname == 'helptitle' or $tagname == 'helptext')
	{
		$tagname = false;
	}
}

// ###################### Start xml_helptopics_cdata #######################
// parse XML cdata
function xml_parse_help_topics_cdata($parser, $data)
{
	global $arr, $counter, $tagname, $scriptName;

	if ($tagname)
	{
		$arr["$scriptName"]["$counter"]["$tagname"] .= $data;
	}

}

// ###################### Start xml_import_helptopics #######################
// import XML help topics - call this function like this:
//		$path = './path/to/install/vbulletin-adminhelp.xml';
//		xml_import_help_topics();
function xml_import_help_topics($xml = false)
{
	global $DB_site, $vboptions, $vbphrase, $arr;

	print_dots_start('<b>' . $vbphrase['importing_admin_help'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

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
			print_stop_message('please_ensure_x_file_is_located_at_y', 'vbulletin-adminhelp.xml', $GLOBALS['path']);
		}
	}

	// initialize vars
	$arr = array();

	// create parser
	$parser = xml_parser_create('ISO-8859-1');

	// set parser options
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_set_element_handler($parser, 'xml_parse_help_topics_otag', 'xml_parse_help_topics_ctag');
	xml_set_character_data_handler($parser, 'xml_parse_help_topics_cdata');

	// parse the XML
	if (!@xml_parse($parser, $xml))
	{
		print_stop_message('xml_error_x_at_line_y', xml_error_string(xml_get_error_code($parser)), xml_get_current_line_number($parser));
	}

	// free the parser
	xml_parser_free($parser);

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "adminhelp " . iif($check = $DB_site->query_first("SELECT adminhelpid FROM " . TABLE_PREFIX . "adminhelp WHERE volatile <> 1"), 'WHERE volatile = 1'));

	foreach($arr AS $script => $scripttopics)
	{
		$helpsql = array();
		foreach($scripttopics AS $topic)
		{
			$helpsql[] = "('" . addslashes($script) . "', '" . addslashes($topic['action']) . "', '" . addslashes($topic['optionname']) . "', " . addslashes($topic['displayorder']) . ", 1)";
		}
		$helpsql = "INSERT INTO " . TABLE_PREFIX . "adminhelp\n\t(script, action, optionname, displayorder, volatile)\nVALUES\n\t" . implode(",\n\t", $helpsql);
		$DB_site->query($helpsql);
	}

	// stop the 'dots' counter feedback
	print_dots_stop();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11091 $
|| ####################################################################
\*======================================================================*/
?>