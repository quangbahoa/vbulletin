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

// #############################################################################
/**
* Fetch array of podcast categories
*
* @param	string	text for the left cell of the table row
* @param	string	name of the <select>
* @param	mixed	selected <option>
*
*/
function print_podcast_chooser($title, $name, $selectedid = -1)
{
	print_select_row($title, $name, fetch_podcast_categories(), $selectedid, true);
}

/**
* Fetch array of podcast categories
*
* @return	array		Array of categories
*/
function fetch_podcast_categories()
{

	require_once(DIR . '/includes/class_xml.php');
	$xmlobj = new vB_XML_Parser(false, DIR . '/includes/xml/podcast_vbulletin.xml');
	$podcastdata = $xmlobj->parse();

	$categories = array('');
	if (is_array($podcastdata['category']))
	{
		foreach ($podcastdata['category'] AS $cats)
		{
			$categories[] = '-- ' . $cats['name'];
			if (is_array($cats['sub']['name']))
			{
				foreach($cats['sub']['name'] AS $subcats)
				{
					$categories[] = '---- ' . $subcats;
				}
			}
		}
	}

	return $categories;
}

/**
* Fetch array of podcast categories
*
* @return	array		Array of categories
*/
function fetch_podcast_categoryarray($categoryid)
{

	require_once(DIR . '/includes/class_xml.php');
	$xmlobj = new vB_XML_Parser(false, DIR . '/includes/xml/podcast_vbulletin.xml');
	$podcastdata = $xmlobj->parse();

	$key = 1;
	$output = array();
	if (is_array($podcastdata['category']))
	{
		foreach ($podcastdata['category'] AS $cats)
		{
			if ($key == $categoryid)
			{
				$output[] = htmlspecialchars_uni($cats['name']);
				break;
			}
			$key++;
			if (is_array($cats['sub']['name']))
			{
				foreach($cats['sub']['name'] AS $subcats)
				{
					if ($key == $categoryid)
					{
						$output[] = htmlspecialchars_uni($cats['name']);
						$output[] = htmlspecialchars_uni($subcats);
						break(2);
					}
					$key++;
				}
			}
		}
	}

	return $output;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
