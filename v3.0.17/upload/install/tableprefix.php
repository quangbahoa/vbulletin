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
ignore_user_abort(true);
define('NOSHUTDOWNFUNC', 1);

if (function_exists('set_time_limit') and get_cfg_var('safe_mode') == 0)
{
	@set_time_limit(1200);
}

$phrasegroups = array();
$specialtemplates = array();

// set this to 1 if you're on Vservers and get disconnected after running an ALTER TABLE command
$onvservers = 0;

$nozip = true;

if (empty($admincpdir))
{
	$admincpdir = 'admincp';
}
chdir("../$admincpdir");
require_once('./global.php');

// #############################################################################

print_cp_header('Rename Tables with New Table Prefix');

// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// #############################################################################

function fetch_renamed_table_name($table, $oldprefix, $newprefix, $dobold = false)
{
	static $prefixlength;

	if (!isset($prefixlength))
	{
		$prefixlength = strlen($oldprefix);
	}

	$name = array('old' => $table);

	if (substr($table, 0, $prefixlength) == $oldprefix)
	{
		$table = substr($table, $prefixlength);
		if ($dobold)
		{
			$name['old'] = "<b>$oldprefix</b>$table";
		}
	}

	$name['new'] = iif($dobold, "<b>$newprefix</b>", $newprefix) . $table;

	return $name;

}

// #############################################################################

if ($_POST['do'] == 'rename')
{

	$_POST['newprefix'] = trim($_POST['newprefix']);

	if ($_POST['newprefix'] == TABLE_PREFIX)
	{
		print_cp_message("
			You have not chosen a new table prefix, this script can not continue.<br /><br />
			(Old table prefix: '<b>" . TABLE_PREFIX . "</b>', new table prefix: '<b>$_POST[newprefix]</b>')
		");
	}

	?>
	<div align="center">
	<div class="tborder" style="width:90%;">
	<div class="tcat" style="padding:4px"><b>Renaming Tables With New Table Prefix</b></div>
	<div class="alt1" style="padding:4px; text-align:<?php echo $stylevar['left']; ?>">
	<b>Please read the notes at the bottom of this page when processing is complete.</b>
	<ul class="smallfont">
	<?php

	flush();

	$valid = array();
	$tables = $DB_site->query("SHOW TABLES");
	while ($table = $DB_site->fetch_array($tables, MYSQL_NUM))
	{
		$valid["$table[0]"] = true;
	}
	unset($table);
	$DB_site->free_result($tables);

	foreach(array_keys($_POST['rename']) as $table)
	{

		if (!isset($valid["$table"]))
		{
			echo "<li>Table '<i>$table</i>' does not exist!</li>\n";
			flush();
		}
		else

		{
			$name = fetch_renamed_table_name($table, TABLE_PREFIX, $_POST['newprefix']);
			echo "<li>Renaming <b>$name[old]</b> to <b>$name[new]</b>... ";
			flush();
			$DB_site->query("ALTER TABLE $name[old] RENAME $name[new]");
			echo "Okay</li>\n";
		}

	}

	?>
	</ul>
	</div>
	<div class="tcat" style="padding:4px"><b>Renaming Process Complete</b></div>
	<div class="alt2" style="padding:4px; text-align:<?php echo $stylevar['left']; ?>">
	<p>This script has now completed.<br />
	Your table prefix has been changed from
	'<b><?php echo TABLE_PREFIX; ?></b>' to '<b><?php echo $_POST['newprefix']; ?></b>'.</p>
	<p>You must now edit your <i>includes/config.php</i> file to include the line:</p>
	<pre>$tableprefix = '<?php echo $_POST['newprefix']; ?>';</pre>
	<p><b><u>Your vBulletin installation will not function until you do this.</u></b></p>
	</div>
	</div>
	</div>
	<?php

}

// #############################################################################

if ($_POST['do'] == 'confirm')
{
	if (array_sum($_POST['rename']) == 0)
	{
		print_cp_message('Sorry, no tables were submitted to be renamed.');
	}
	else
	{

		$_POST['newprefix'] = trim($_POST['newprefix']);

		if ($_POST['newprefix'] == TABLE_PREFIX)
		{
			print_cp_message("
				You have not chosen a new table prefix, this script can not continue.<br /><br />
				(Old table prefix: '<b>" . TABLE_PREFIX . "</b>', new table prefix: '<b>$_POST[newprefix]</b>')
			");
		}

		$prefixlength = strlen($tableprefix);
		$dorename = array();
		$warn = array();
		$dotables = '';
		$warntables = '';

		print_form_header('tableprefix', 'rename');
		print_table_header("Confirm Table Prefix Change (From '" . TABLE_PREFIX . "' to '$_POST[newprefix]')");
		construct_hidden_code('newprefix', $_POST['newprefix']);

		foreach($_POST['rename'] as $table => $yesno)
		{
			if ($yesno)
			{
				construct_hidden_code("rename[$table]", 1);
				$dorename[] = $table;
				$tablename = fetch_renamed_table_name($table, TABLE_PREFIX, $_POST['newprefix'], true);
				$dotables .= "<tr class=\"alt2\"><td class=\"smallfont\">$tablename[old]</td><td class=\"smallfont\">$tablename[new]</td></tr>";
				if (substr($table, 0, $prefixlength) != TABLE_PREFIX)
				{
					$warn[] = $table;
				}
			}
		}

		if (!empty($warn))
		{
			foreach($warn as $table)
			{
				$warntables .= "<li>$table</li>";
			}
			print_description_row('
				<b>WARNING!</b><br />The following tables do <b>not</b> have the
				original table prefix! These tables may not be part of vBulletin.
				<ul>' . $warntables . '</ul>
				Are you <b>absolutely sure</b> you want to continue?
			');
			print_description_row('<div align="center"><input type="button" class="button" value="No, Go Back" onclick="history.back(1);" /></div>', 0, 2, 'tfoot');
		}
		else

		{
			$bgcounter++;
		}

		if (!empty($dorename))
		{
			print_description_row('
				<p>You have chosen to rename the following tables:</p>
				<table cellpadding="1" cellspacing="0" border="0" class="alt1" width="90%" align="center"><tr><td>
				<table cellpadding="3" cellspacing="1" border="0" width="100%">
				<tr><td><b>Original Table Name</b></td><td><b>New Table Name</b></td></tr>
				' . $dotables . '
				</table></td></tr></table>
			');
		}

		print_description_row('
			<p>If you are <b>sure</b> you want to continue and rename the tables listed
			above, click the rename button below.</p>
			<p><b>Note:</b> This is your final chance to stop. Clicking the button below will alter
			all the tables listed above, requiring you to edit your config.php file
			before your vBulletin will work again.</p>
		');

		print_submit_row('Rename Tables', 0, 2, ' Go Back ');

	}

}

// #############################################################################

if ($_REQUEST['do'] == 'choose')
{
	print_form_header('tableprefix', 'confirm');
	print_table_header('Rename SQL Tables');
	print_label_row('Old Table Prefix', '<div class="bginput" style="margin-top:2px; width:230px">' . TABLE_PREFIX . '</div>');
	print_input_row('New Table Prefix', 'newprefix', TABLE_PREFIX);
	print_label_row('<b>Table Name</b>', '<input type="button" class="button" value="Set All Yes" onclick="js_check_all_option(this.form, 1);" /> <input type="button" class="button" value=" Set All No " onclick="js_check_all_option(this.form, 0);" />', 'tfoot');
	print_description_row('
		<p>Below is a list of all the tables in your database.
		You should make sure that you select <b>only</b> tables that are part of vBulletin.</p>
		<p><b>If you are not sure, do not continue with this operation.</b></p>
	');
	$prefixlength = strlen($tableprefix);
	$tables = $DB_site->query("SHOW TABLES");
	while($table = mysql_fetch_array($tables, MYSQL_NUM))
	{
		print_yes_no_row("Rename Table <b>$table[0]</b>?", "rename[$table[0]]", iif(substr($table[0], 0, $prefixlength) == $tableprefix, 1, 0));
	}
	print_submit_row('Rename Tables');

}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{

	echo '<p>&nbsp;</p><p>&nbsp;</p>';

	print_form_header('tableprefix', 'choose');
	print_table_header('vBulletin 3 Table Prefix Rename System');
	print_description_row('
		<p>This script will allow you to rename your vBulletin SQL tables
		in order to accomodate a new table prefix value in <i>includes/config.php</i>.</p>
		<p><b>Note:</b> Do not alter the $tableprefix value in config.php until this script
		has completed and you are given a message telling you to edit the value.</p>
		<p>Your table prefix is currently set to \'<b>' . TABLE_PREFIX . '</b>\'.</p>
	');
	print_submit_row('Continue', 0);

}

// #############################################################################

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9778 $
|| ####################################################################
\*======================================================================*/
?>