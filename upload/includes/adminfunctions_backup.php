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

/**
* Prints text for an SQL query to recreate a table
*
* @param	string	Table name
* @param	integer	If set to a file pointer, will write SQL there instead
*/
function fetch_table_dump_sql($table, $fp = 0)
{
	global $vbulletin;

	if (is_demo_mode())
	{
		$fp = 0;
	}

	$tabledump = $vbulletin->db->query_first("SHOW CREATE TABLE $table");
	strip_backticks($tabledump['Create Table']);
	$tabledump = "DROP TABLE IF EXISTS $table;\n" . $tabledump['Create Table'] . ";\n\n";
	if ($fp)
	{
		fwrite($fp, $tabledump);
	}
	else
	{
		echo $tabledump;
	}

	// get data
	$rows = $vbulletin->db->query_read("SELECT * FROM $table");
	$numfields=$vbulletin->db->num_fields($rows);
	while ($row = $vbulletin->db->fetch_array($rows, DBARRAY_NUM))
	{
		$tabledump = "INSERT INTO $table VALUES(";

		$fieldcounter = -1;
		$firstfield = 1;
		// get each field's data
		while (++$fieldcounter < $numfields)
		{
			if (!$firstfield)
			{
				$tabledump .= ', ';
			}
			else
			{
				$firstfield = 0;
			}

			if (!isset($row["$fieldcounter"]))
			{
				$tabledump .= 'NULL';
			}
			else
			{
				$tabledump .= "'" . $vbulletin->db->escape_string($row["$fieldcounter"]) . "'";
			}
		}

		$tabledump .= ");\n";

		if ($fp)
		{
			fwrite($fp, $tabledump);
		}
		else
		{
			echo $tabledump;
		}
	}
	$vbulletin->db->free_result($rows);
}

/**
* Doesn't actually do anything at present
*
* @param	string	(ref) Text
*
* @return	string
*/
function strip_backticks(&$text)
{
	return $text;
	//$text = str_replace('`', '', $text);
}

/**
* Returns a CSV version of a table and its data
*
* @param	string	Name of table
* @param	string	Column separator
* @param	string	Quote character
* @param	boolean	Include column headings
*
* @return	string	CSV data
*/
function construct_csv_backup($table, $separator, $quotes, $showhead)
{
	global $vbulletin;

	// get columns for header row
	if ($showhead)
	{
		$firstfield = 1;
		$fields = $vbulletin->db->query_write("SHOW FIELDS FROM $table");
		while ($field = $vbulletin->db->fetch_array($fields))
		{
			if (!$firstfield)
			{
				$contents .= $separator;
			}
			else
			{
				$firstfield = 0;
			}
			$contents .= $quotes . $field['Field'] . $quotes;
		}
		$vbulletin->db->free_result($fields);
	}
	$contents .= "\n";


	// get data
	$rows = $vbulletin->db->query_read("SELECT * FROM $table");
	$numfields = $vbulletin->db->num_fields($rows);
	while ($row = $vbulletin->db->fetch_array($rows, DBARRAY_NUM))
	{

		$fieldcounter = -1;
		$firstfield = 1;
		while (++$fieldcounter < $numfields)
		{
			if (!$firstfield)
			{
				$contents .= $separator;
			}
			else
			{
				$firstfield = 0;
			}

			if (!isset($row["$fieldcounter"]))
			{
				$contents .= 'NULL';
			}
			else
			{
				$contents .= $quotes . addslashes($row["$fieldcounter"]) . $quotes;
			}
		}

		$contents .= "\n";
	}
	$vbulletin->db->free_result($rows);

	return $contents;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
