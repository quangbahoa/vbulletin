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

function fetch_table_dump_sql($table, $fp = 0)
{
	global $DB_site;

	if (is_demo_mode())
	{
		$fp = 0;
	}

	$tabledump = $DB_site->query_first("SHOW CREATE TABLE $table");
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
	$rows = $DB_site->query("SELECT * FROM $table");
	$numfields=$DB_site->num_fields($rows);
	while ($row = $DB_site->fetch_array($rows, DBARRAY_NUM))
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
				$tabledump .= "'" . $DB_site->escape_string($row["$fieldcounter"]) . "'";
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
	$DB_site->free_result($rows);
}

function strip_backticks(&$text)
{
	return $text;
	//$text = str_replace('`', '', $text);
}

function construct_csv_backup($table, $separator, $quotes, $showhead)
{
	global $DB_site;

	// get columns for header row
	if ($showhead)
	{
		$firstfield = 1;
		$fields = $DB_site->query("SHOW FIELDS FROM $table");
		while ($field = $DB_site->fetch_array($fields))
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
		$DB_site->free_result($fields);
	}
	$contents .= "\n";


	// get data
	$rows = $DB_site->query("SELECT * FROM $table");
	$numfields = $DB_site->num_fields($rows);
	while ($row = $DB_site->fetch_array($rows, DBARRAY_NUM))
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
	$DB_site->free_result($rows);

	return $contents;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11056 $
|| ####################################################################
\*======================================================================*/
?>