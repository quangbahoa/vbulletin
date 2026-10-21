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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 9762 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('sql');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminmaintain'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['repair_optimize_tables']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

// ###################### Start checktable #######################
function exec_sql_table_check($table)
{
	global $DB_site, $optimizetables, $repairtables, $vbphrase;

	$nooptimize = 0;
	$error = 0;

	if ($repairtables)
	{
		$checkmsgs = $DB_site->query("CHECK TABLE `$table`");
		while ($msg = $DB_site->fetch_array($checkmsgs, DBARRAY_NUM))
		{
			if ($msg[2] == 'error')
			{
				if ($msg[3] == 'The handler for the table doesn\'t support check/repair') // nb: this is the MySQL error message, it does not need phrasing
				{
					$msg[2] = 'status';
					$msg[3] = $vbphrase['this_table_does_not_support_repair_optimize'];
					$nooptimize = 1;
				}
				else
				{
					$error = 1;
				}
			}

			$cells = array(
				$table,
				ucfirst($msg[1]),
				iif($error, '<b>' . ucfirst($msg[2]) . '</b>', ucfirst($msg[2])) . ': ' . $msg[3]
			);
			print_cells_row($cells, 0, '', -4);
		}

		if ($error)
		{
			$repairmsg = $DB_site->query_first("REPAIR TABLE `$table`");
			if ($repairmsg[3]!='OK')
			{
				$error2 = 1;
			}
			else
			{
				$error2 = 0;
				$error = 0;
			}

			$cells = array(
				$table,
				ucfirst($msg[1]),
				iif($error2, '<b>' . ucfirst($msg[2]) . '</b>', ucfirst($msg[2])),
			);
			print_cells_row($cells);
		}
	} // end repairing

	if ($optimizetables AND !$error AND !$error2 AND !$nooptimize)
	{
		$opimizemsgs = $DB_site->query("OPTIMIZE TABLE `$table`");
		while ($msg = $DB_site->fetch_array($opimizemsgs, DBARRAY_NUM))
		{
			if ($msg[2] == 'error')
			{
				$error = 1;
			}

			$cells = array(
				$table,
				ucfirst($msg[1]),
				iif($error, '<b>' . ucfirst($msg[2]) . '</b>', ucfirst($msg[2])) . ': ' . $msg[3],
			);
			print_cells_row($cells, 0, '', -4);
		}
	} // end optimizing
}

// ######################### Start do repair #####################
if ($_POST['do'] == 'dorepair')
{
	globalize($_POST, array(
		'tableserial' => STR,
		'tablelist',
		'optimizetables',
		'repairtables',
		'converttables' => INT,
		'isamtablelist'
	));

	// This will work on some servers, for what it's worth.
	echo '<p align="center">' . $vbphrase['please_wait'] . '</p>';
	flush();

	if (!empty($tableserial))
	{
		$tablelist = unserialize($tableserial);
	}

	print_form_header('repair', 'dorepair');

	if ($converttables == 1 AND is_array($isamtablelist))
	{
		$DB_site->reporterror = 0;
		print_table_header(construct_phrase($vbphrase['convert_tables_from_x_to_y'], '<b>ISAM</b>', '<b>MyISAM</b>'));
		print_cells_row(array($vbphrase['table'], $vbphrase['status']), 1);
		foreach ($isamtablelist AS $index => $value)
		{
			$cells = array();
			$cells[] = construct_phrase($vbphrase['convert_x_from_y_to_z'], "<i>$value</i>", 'ISAM', 'MyISAM');
			$DB_site->query("ALTER TABLE `$value` TYPE=MyISAM");
			if ($DB_site->errno == 0)
			{
				$cells[] = $vbphrase['okay'];
			}
			else
			{
				$cells[] = $DB_site->errno . ': ' . $DB_site->errdesc;
			}
			print_cells_row($cells);
		}
		$DB_site->reporterror = 1;
		print_table_break();
	}

	print_table_header($vbphrase['results'], 3);
	print_cells_row(array($vbphrase['table'], $vbphrase['action'], $vbphrase['message']), 1);

	if (is_array($tablelist) AND ($optimizetables != 0 OR $repairtables != 0))
	{
		foreach ($tablelist AS $tablename)
		{
			exec_sql_table_check($tablename);
		}
	}
	else
	{
		print_description_row($vbphrase['nothing_to_do'], 0, 3);
	}

	construct_hidden_code('optimizetables', $optimizetables);
	construct_hidden_code('repairtables', $repairtables);
	construct_hidden_code('tableserial', serialize($tablelist));

	print_submit_row($vbphrase['repeat_process'], '', 3);
}

// ######################### Start table list ####################
if ($_REQUEST['do'] == 'list')
{
	print_form_header('repair', 'dorepair', 0, 1, 'cpform', '65%');
	print_table_header($vbphrase['repair_optimize_tables']);
	print_label_row($vbphrase['table'], "<input type=\"checkbox\" name=\"allbox\" title=\"$vbphrase[check_all]\" onclick=\"js_check_all(this.form);\" />$vbphrase[check_all]", 'thead');

	$mysqlversion = $DB_site->query_first("SELECT VERSION() AS version");

	if ($mysqlversion['version'] < '3.23')
	{
		$tables = $DB_site->query("SHOW TABLES");
	}
	else
	{
		// mysql show this to be in as of 3.23 but notes that it went through changes -- what changes?
		$tables = $DB_site->query("SHOW TABLE STATUS");
	}

	$isamtables = array();

	$nullcount = 0;

	while ($table = $DB_site->fetch_array($tables, DBARRAY_NUM))
	{
		if (strtolower($table[1]) != 'heap')
		{
			print_checkbox_row($table[0], "tablelist[$nullcount]", false, $table[0], false);
			$nullcount ++;
			if ($table[1] == 'ISAM')
			{
				$isamtables[] = $table[0];
			}
		}
	}

	if (isset($isamtables[0]))
	{
		$nullcount = 0;
		print_table_break('', '65%');
		print_table_header($vbphrase['isam_tables']);
		print_description_row('<span class="smallfont">' . construct_phrase($vbphrase['you_are_running_mysql_version_x_convert_to_myisam'], $mysqlversion['version']) . '</span>');
		foreach ($isamtables AS $index => $value)
		{
			print_checkbox_row($value, "isamtablelist[$nullcount]", false, $value);
			$nullcount++;
		}
	}

	print_table_break('', '65%');

	if ($mysqlversion['version'] < '3.23')
	{
		// can't use REPAIR TABLE xxxx
		print_description_row($vbphrase['this_will_only_optimize_the_selected_tables'], 0, 2);
		construct_hidden_code('optimizetables', 1);
		construct_hidden_code('repairtables', 0);
	}
	else
	{
		// can use REPAIR TABLE xxxx
		print_table_header($vbphrase['options']);
		if (isset($isamtables[0]))
		{
			print_yes_no_row(construct_phrase($vbphrase['convert_tables_from_x_to_y'], 'ISAM', 'MyISAM'), 'converttables', 1);
		}
		print_yes_no_row($vbphrase['optimize_tables'], 'optimizetables', 1);
		print_yes_no_row($vbphrase['repair_tables'], 'repairtables', 1);
	}
	print_submit_row($vbphrase['continue']);

	echo '<a name="fixunique">&nbsp;</a>';

	print_form_header('repair', 'fixunique', 0, 1, 'bla', '65%');
	print_table_header($vbphrase['fix_unique_indexes']);
	print_description_row($vbphrase['fix_unique_indexes_intro']);
	print_submit_row($vbphrase['fix_unique_indexes'], false);
}

// ######################### Start fix unique indexes #####################
if ($_REQUEST['do'] == 'fixunique')
{
	globalize($_REQUEST, array('tableid' => INT));
	require_once('./install/mysql-schema.php');

	function fix_unique_index($tableid)
	{
		global $vbphrase, $DB_site, $uniquetables;

		if (isset($uniquetables["$tableid"]))
		{
			$table = &$uniquetables["$tableid"];
		}
		else
		{
			return -1;
		}

		$keys = array();
		$checkindexes = $DB_site->query("SHOW KEYS FROM " . TABLE_PREFIX . "$table[name]");
		while ($checkindex = $DB_site->fetch_array($checkindexes))
		{
			if ($checkindex['Non_unique'] == 0)
			{
				$keys["$checkindex[Key_name]"][] = $checkindex['Column_name'];
			}
		}
		$DB_site->free_result($checkindexes);

		foreach ($keys AS $keyname => $keyfields)
		{
			$keys["$keyname"] = implode(', ', $keyfields);
		}

		$fields = implode(', ', $table['fields']);

		$gotunique = in_array($fields, $keys);

		if ($gotunique)
		{
			echo "<div>" . construct_phrase($vbphrase['table_x_has_unique_index'], "<strong>$table[name]</strong>") . "</div>";
			$nexttableid = fix_unique_index($tableid + 1);
		}
		else
		{
			echo "<p>" . construct_phrase($vbphrase['replacing_unique_index_on_table_x'], "<strong>$table[name]</strong>") . "</p><ul>";

			$fields = implode(', ', $table['fields']);

			$findquery = "SELECT $fields, COUNT(*) AS occurences
				FROM " . TABLE_PREFIX . "$table[name]
				GROUP BY $fields
				HAVING occurences > 1";
			$dupes = $DB_site->query($findquery);

			if ($numdupes = $DB_site->num_rows($dupes))
			{
				echo "<li>" . construct_phrase($vbphrase['found_x_duplicate_record_occurences'], "<strong>$numdupes</strong>") . "<ol>";

				while ($dupe = $DB_site->fetch_array($dupes))
				{
					$cond = array();

					foreach ($dupe AS $fieldname => $field)
					{
						if ($fieldname != 'occurences')
						{
							$cond[] = "$fieldname = " . iif(is_numeric($field), $field, "'" . addslashes($field) . "'");
						}
					}

					$dupesquery = "DELETE FROM " . TABLE_PREFIX . "$table[name] WHERE " . implode(" AND ", $cond) . " ";

					if ($table['autoinc'])
					{
						$max = $DB_site->query_first("
							SELECT MAX($table[autoinc]) AS maxid
							FROM " . TABLE_PREFIX . "$table[name]
							WHERE " . implode("\nAND ", $cond) . "
						");
						$dupesquery .= "AND $table[autoinc] <> $max[maxid]";
					}
					else
					{
						$dupesquery .= "LIMIT " . ($dupe['occurences'] - 1);
					}

					$DB_site->query($dupesquery);
					echo "<li>$vbphrase[deleted_duplicate_occurence]</li>";
				}
				$DB_site->free_result($dupes);

				echo "</ol></li>";
			}

			$killindexquery = "ALTER TABLE " . TABLE_PREFIX . "$table[name] DROP INDEX $table[keyname]";
			echo "<li>$vbphrase[dropping_non_unique_index] <!--<pre>$killindexquery</pre>-->";
			$DB_site->query($killindexquery);
			echo "$vbphrase[done]</li>";

			$createindexquery = "ALTER TABLE " . TABLE_PREFIX . "$table[name] ADD " . iif($table['name'] == 'access', 'PRIMARY', 'UNIQUE') . " KEY $table[keyname] ($fields)";
			echo "<li>$vbphrase[creating_unique_index] <!--<pre>$createindexquery</pre>-->";
			$DB_site->query($createindexquery);
			echo "$vbphrase[done]</li>";

			echo "</ul>";

			$nexttableid = $tableid + 1;
		}

		return $nexttableid;
	}

	$uniquetables = array();

	foreach ($schema['CREATE']['query'] AS $tablename => $query)
	{
		if (preg_match('#unique key (\w+)\s*\(([\w, ]+)\)#siU', $query, $regs) OR ($tablename == 'access' AND preg_match('#primary key (\w+)\s*\(([\w, ]+)\)#siU', $query, $regs)))
		{
			if (preg_match('#\t+(\w+id)\s+[\w- ]+AUTO_INCREMENT#iU', $query, $regs2))
			{
				$autoinc = $regs2[1];
			}
			else
			{
				$autoinc = false;
			}

			$uniquetables[] = array(
				'name'    => $tablename,
				'keyname' => $regs[1],
				'fields'  => preg_split('#\s*,\s*#si', $regs[2], -1, PREG_SPLIT_NO_EMPTY),
				'autoinc' => $autoinc
			);
		}
	}

	echo "<p><strong>$vbphrase[fix_unique_indexes]</strong></p>";

	//while ($nexttableid >= 0)
	//{
		$nexttableid = fix_unique_index($tableid);
	//}

	if ($nexttableid >= 0)
	{
		print_form_header('repair', 'fixunique', 0, 1, 'cpform', '25%');
		construct_hidden_code('tableid', $nexttableid);
		print_table_header("<div style=\"white-space:nowrap\">$vbphrase[fix_unique_indexes]</div>");
		print_submit_row($vbphrase['continue'], false);
	}
	else
	{
		print_form_header('repair', '', 0, 1, 'cpform', '65%');
		print_table_header($vbphrase['fix_unique_indexes']);
		print_description_row($vbphrase['all_unique_indexes_checked']);
		print_submit_row($vbphrase['proceed'], false);
	}

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9762 $
|| ####################################################################
\*======================================================================*/
?>