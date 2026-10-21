<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ###################### Start fetch_regimage_string #######################
function fetch_regimage_string($length)
{
	$somechars = '234689ABCEFGHJMNPQRSTWY';
	$morechars = '234689ABCEFGHJKMNPQRSTWXYZabcdefghjkmnpstwxyz';

	for ($x = 1; $x <= $length; $x++)
	{
		$chars = ($x <= 2 OR $x == $length) ? $morechars : $somechars;
		$number = rand(1, strlen($chars));
		$word .= substr($chars, $number - 1, 1);
 	}

 	return $word;
}

// ###################### Start fetch_regimage_hash #######################
function fetch_regimage_hash()
{
	global $vbulletin;

	$string = fetch_regimage_string(6);
	$regimagehash = md5(uniqid(rand(), 1));
	// Gen hash and insert into database;
	/*insert query*/
	$vbulletin->db->query_write("
		INSERT INTO " . TABLE_PREFIX . "regimage
			(regimagehash, imagestamp, dateline)
		VALUES
			('" . $vbulletin->db->escape_string($regimagehash) . "', '" . $vbulletin->db->escape_string($string) . "', " . TIMENOW . ")"
	);

	return $regimagehash;
}

// ###################### Start fetch_regimage_hash #######################
function verify_regimage_hash($imagehash, $imagestamp)
{
	global $vbulletin;

	$imagestamp = str_replace(' ', '', $imagestamp);

	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "regimage
		WHERE regimagehash = '" . $vbulletin->db->escape_string($imagehash) . "'
			AND imagestamp = '" . $vbulletin->db->escape_string($imagestamp) . "'
	");

	return ($vbulletin->db->affected_rows());
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13641 $
|| ####################################################################
\*======================================================================*/
?>