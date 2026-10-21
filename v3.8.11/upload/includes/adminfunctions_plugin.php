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

/**
* Deletes all the associated data for a specific from the database.
* Only known master (volatile) data is removed. For example, customized versions
* of the templates are left.
*
* @param	string	Product ID to delete
* @param	string	Whether the deletion needs to work on a 3.5 DB (for upgrade scripts)
*/
function delete_product($productid, $compliant_35 = false)
{
	global $vbulletin;

	$productid = $vbulletin->db->escape_string($productid);

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "product WHERE productid = '$productid'");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "productcode WHERE productid = '$productid'");
	if ($compliant_35 == false)
	{
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "productdependency WHERE productid = '$productid'");
	}
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "plugin WHERE product = '$productid'");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "phrase WHERE product = '$productid' AND languageid = -1");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "phrasetype WHERE product = '$productid'");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE product = '$productid' AND styleid = -1");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "setting WHERE product = '$productid' AND volatile = 1");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "settinggroup WHERE product = '$productid' AND volatile = 1");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "adminhelp WHERE product = '$productid' AND volatile = 1");
	if ($compliant_35 == false)
	{
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "cron WHERE product = '$productid' AND volatile = 1");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "faq WHERE product = '$productid' AND volatile = 1");
	}
	$vbulletin->db->hide_errors();
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "moderatorlog WHERE product = '$productid'");
	$vbulletin->db->show_errors();
}

/**
* Used to do sorting of version number strings via usort().
* adminfunctions_template.php must be required before you use this!
*
* @param	string	Version number string 1
* @param	string	Version number string 2
*
* @return	integer	0 if the same, -1 if #1 < #2, 1 if #1 > #2
*/
function version_sort($a, $b)
{
	if ($a == $b)
	{
		return 0;
	}
	else if ($a == '*')
	{
		// * < non-*
		return -1;
	}
	else if ($b == '*')
	{
		// any non-* > *
		return 1;
	}

	return (is_newer_version($a, $b) ? 1 : -1);
}

/**
* Fetches an array of products dependent on a specific product, though whether
* this is a parent-child or child-parent relationship is determined based on
* the construction of the dependency list.
* If the parent is the key, this function will recursively find a list of children.
* If the child is the key, the function will recursively find a list of parents.
*
* @param	string	Product to find parents/children for
* @param	array	An array of dependencies to pull from in form [pid][] => pid
*
* @return	array	Array of children/parents
*/
function fetch_product_dependencies($productid, &$dependency_list)
{
	if (!is_array($dependency_list["$productid"]))
	{
		return array();
	}

	$list = array();
	foreach ($dependency_list["$productid"] AS $subproductid)
	{
		// only traverse this branch if we haven't done it before -- prevent infinte recursion
		if (!isset($list["$subproductid"]))
		{
			$list["$subproductid"] = $subproductid;
			$list = array_merge(
				$list,
				fetch_product_dependencies($subproductid, $dependency_list)
			);
		}
	}

	return $list;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
