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

error_reporting(E_ALL & ~E_NOTICE);

// ###################### Start getHelpPhraseName #######################
// return the correct short name for a help topic
function fetch_help_phrase_short_name($item, $suffix = '')
{
	return $item['script'] . iif($item['action'], '_' . str_replace(',', '_', $item['action'])) . iif($item['optionname'], "_$item[optionname]") . $suffix;
}

// ###################### Start xml_import_helptopics #######################
// import XML help topics - call this function like this:
//		$path = './path/to/install/vbulletin-adminhelp.xml';
//		xml_import_help_topics();
function xml_import_help_topics($xml = false)
{
	global $vbulletin, $vbphrase;

	print_dots_start('<b>' . $vbphrase['importing_admin_help'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	require_once(DIR . '/includes/class_xml.php');

	$xmlobj = new vB_XML_Parser($xml, (empty($GLOBALS['path']) ? '' : $GLOBALS['path']));
	if ($xmlobj->error_no == 1)
	{
			print_dots_stop();
			print_stop_message('no_xml_and_no_path');
	}
	else if ($xmlobj->error_no == 2)
	{
			print_dots_stop();
			print_stop_message('please_ensure_x_file_is_located_at_y', 'vbulletin-adminhelp.xml', $GLOBALS['path']);
	}

	if(!$arr = $xmlobj->parse())
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line());
	}

	if (!$arr['helpscript'])
	{
		print_dots_stop();
		print_stop_message('invalid_file_specified');
	}

	$product = (empty($arr['product']) ? 'vbulletin' : $arr['product']);
	$has_phrases = (!empty($arr['hasphrases']));
	$arr = $arr['helpscript'];

	if ($product == 'vbulletin')
	{
		$product_sql = "product IN ('vbulletin', '')";
	}
	else
	{
		$product_sql = "product = '" . $vbulletin->db->escape_string($product) . "'";
	}

	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "adminhelp
		WHERE $product_sql
			 AND volatile = 1
	");
	if ($has_phrases)
	{
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE $product_sql
				AND fieldname = 'cphelptext'
				AND languageid = -1
		");
	}

	// Deal with single entry
	if (!is_array($arr[0]))
	{
		$arr = array($arr);
	}


	foreach($arr AS $helpscript)
	{
		$help_sql = array();
		$phrase_sql = array();
		$help_sql_len = 0;
		$phrase_sql_len = 0;

		// Deal with single entry
		if (!empty($helpscript['helptopic'][0]) AND !is_array($helpscript['helptopic'][0]))
		{
			$helpscript['helptopic'] = array($helpscript['helptopic']);
		}

		foreach ($helpscript['helptopic'] AS $topic)
		{
			$help_sql[] = "
				('" . $vbulletin->db->escape_string($helpscript['name']) . "',
				'" . (empty($topic['act']) ? '' : $vbulletin->db->escape_string($topic['act'])) . "',
				'" . (empty($topic['opt']) ? '' : $vbulletin->db->escape_string($topic['opt'])) . "',
				" . (empty($topic['disp']) ? 0 : intval($topic['disp'])) . ",
				1,
				'" . $vbulletin->db->escape_string($product) . "')
			";
			$help_sql_len += strlen(end($help_sql));

			if ($has_phrases)
			{
				$phrase_name = fetch_help_phrase_short_name(array(
					'script' => $helpscript['name'],
					'action' => empty($topic['act']) ? '' : $topic['act'],
					'optionname' => empty($topic['opt']) ? '' : $topic['opt']
				));

				if (isset($topic['text']['value']))
				{
					$phrase_sql[] = "
						(-1,
						'cphelptext',
						'{$phrase_name}_text',
						'" . $vbulletin->db->escape_string($topic['text']['value']) . "',
						'" . $vbulletin->db->escape_string($product) . "',
						'" . $vbulletin->db->escape_string($topic['text']['username']) . "',
						" . intval($topic['text']['date']) . ",
						'" . $vbulletin->db->escape_string($topic['text']['version']) . "')
					";

					$phrase_sql_len += strlen(end($phrase_sql));

				}

				if (isset($topic['title']['value']))
				{
					$phrase_sql[] = "
						(-1,
						'cphelptext',
						'{$phrase_name}_title',
						'" . $vbulletin->db->escape_string($topic['title']['value']) . "',
						'" . $vbulletin->db->escape_string($product) . "',
						'" . $vbulletin->db->escape_string($topic['title']['username']) . "',
						" . intval($topic['title']['date']) . ",
						'" . $vbulletin->db->escape_string($topic['title']['version']) . "')
					";
					$phrase_sql_len += strlen(end($phrase_sql));
				}
			}

			if ($phrase_sql_len > 102400)
			{
				// insert max of 100k of phrases at a time
				/*insert query*/
				$vbulletin->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, fieldname, varname, text, product, username, dateline, version)
					VALUES
						" . implode(",\n", $phrase_sql)
				);

				$phrase_sql = array();
				$phrase_sql_len = 0;
			}

			if ($help_sql_len > 102400)
			{
				// insert max of 100k of phrases at a time
				/*insert query*/
				$vbulletin->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "adminhelp
						(script, action, optionname, displayorder, volatile, product)
					VALUES
						" . implode(",\n\t", $help_sql)
				);

				$help_sql = array();
				$help_sql_len = 0;
			}
		}

		if ($help_sql)
		{
			/*insert query*/
			$vbulletin->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "adminhelp
					(script, action, optionname, displayorder, volatile, product)
				VALUES
					" . implode(",\n\t", $help_sql)
			);
		}

		if ($phrase_sql)
		{
			/*insert query*/
				$vbulletin->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, fieldname, varname, text, product, username, dateline, version)
					VALUES
						" . implode(",\n", $phrase_sql)
				);
		}
	}

	// stop the 'dots' counter feedback
	print_dots_stop();

	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
