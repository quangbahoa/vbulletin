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

	$xmlobj = new XMLparser($xml, $GLOBALS['path']);
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
	$arr = $arr['helpscript'];

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "adminhelp WHERE (product = '" . $vbulletin->db->escape_string($product) . "'" . iif($product == 'vbulletin', " OR product = ''") . ")" . iif($check = $vbulletin->db->query_first("SELECT adminhelpid FROM " . TABLE_PREFIX . "adminhelp WHERE volatile <> 1"), ' AND volatile = 1'));
	// Deal with single entry
	if (!is_array($arr[0]))
	{
		$arr = array($arr);
	}
	foreach($arr AS $helpscript)
	{
		$helpsql = array();
		// Deal with single entry
		if (!is_array($helpscript['helptopic'][0]))
		{
			$topic = $helpscript['helptopic'];
			$helpsql[] = "('" . $vbulletin->db->escape_string($helpscript['name']) . "', '" . $vbulletin->db->escape_string($topic['act']) . "', '" . $vbulletin->db->escape_string($topic['opt']) . "', " . intval($topic['disp']) . ", 1, '" . $vbulletin->db->escape_string($product) . "')";
		}
		else
		{
			foreach($helpscript['helptopic'] AS $topic)
			{
				$helpsql[] = "('" . $vbulletin->db->escape_string($helpscript['name']) . "', '" . $vbulletin->db->escape_string($topic['act']) . "', '" . $vbulletin->db->escape_string($topic['opt']) . "', " . intval($topic['disp']) . ", 1, '" . $vbulletin->db->escape_string($product) . "')";
			}
		}
		/*insert query*/
		$vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "adminhelp\n\t(script, action, optionname, displayorder, volatile, product)\nVALUES\n\t" . implode(",\n\t", $helpsql));
	}

	// stop the 'dots' counter feedback
	print_dots_stop();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12918 $
|| ####################################################################
\*======================================================================*/
?>