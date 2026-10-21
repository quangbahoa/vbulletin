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
define('CVS_REVISION', '$RCSfile$ - $Revision: 10864 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('bbcode');
$specialtemplates = array('bbcodecache');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminbbcodes'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['bbcodeid'] != 0, "bbcode id = $_REQUEST[bbcodeid]"));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['bb_code_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

$bbcodes = $DB_site->query("SELECT bbcodetag, bbcodereplacement, twoparams FROM " . TABLE_PREFIX . "bbcode");
$searcharray = array();
$replacementarray = array();
$doubleRegex = "/(\[)(%s)(=)(['\"]?)([^\"']*)(\\4])(.*)(\[\/%s\])/siU";
$singleRegex = "/(\[)(%s)(])(.*)(\[\/%s\])/siU";

while ($bbcode = $DB_site->fetch_array($bbcodes))
{
	if ($bbcode['twoparams'])
	{
		$regex = sprintf($doubleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
	}
	else
	{
		$regex = sprintf($singleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
	}
	$searcharray[] = $regex;
	$replacementarray[] = $bbcode['bbcodereplacement'];
}

// ########################################### ADD #####################################################

if($_REQUEST['do'] == 'add')
{
	print_form_header('bbcode', 'insert');
	print_table_header($vbphrase['add_new_bb_code']);
	print_input_row($vbphrase['title'], 'title');
	print_input_row($vbphrase['tag'], 'bbcodetag');
	print_textarea_row($vbphrase['replacement'], 'bbcodereplacement', '', 5, 60);
	print_input_row($vbphrase['example'], 'bbcodeexample');
	print_textarea_row($vbphrase['description'], 'bbcodeexplanation', '', 10, 60);
	print_yes_no_row($vbphrase['use_option'], 'twoparams', 0);
	print_input_row($vbphrase['button_image_desc'], 'buttonimage', '');
	print_submit_row($vbphrase['save']);

	print_form_header('', '');
	print_description_row('<span class="smallfont">' .$vbphrase['bb_code_explanations']. '</span>');
	print_table_footer();
}

// ############################################## INSERT #########################################

if($_POST['do'] == 'insert')
{
	globalize($_POST, array(
		'title' => STR,
		'bbcodetag' => STR,
		'bbcodereplacement' => STR,
		'bbcodeexample' => STR,
		'bbcodeexplanation' => STR,
		'twoparams' => INT,
		'buttonimage' => STR
	));

	if (!$bbcodetag OR !$bbcodereplacement)
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($check = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "bbcode WHERE bbcodetag = '" . addslashes($bbcodetag) . "' AND twoparams = $twoparams"))
	{
		print_stop_message('there_is_already_bb_code_named_x', htmlspecialchars_uni($bbcodetag));
	}

	if ($twoparams)
	{
		$bbcodereplacement = str_replace('{param}', '\\7', $bbcodereplacement);
		$bbcodereplacement = str_replace('{option}', '\\5', $bbcodereplacement);
	}
	else
	{
		$bbcodereplacement = str_replace('{param}', '\\4', $bbcodereplacement);
	}

	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "bbcode (bbcodeid, bbcodetag, bbcodereplacement, bbcodeexample, bbcodeexplanation, twoparams, title, buttonimage)
		VALUES
		(
			NULL,
			'" . trim(addslashes(preg_quote($bbcodetag))) . "',
			'" . trim(addslashes($bbcodereplacement)) . "',
			'" . trim(addslashes($bbcodeexample)) . "',
			'" . trim(addslashes($bbcodeexplanation)) . "',
			$twoparams,
			'" . addslashes($title) . "',
			'" . addslashes($buttonimage) . "'
		)
	");

	build_bbcode_cache();

	define('CP_REDIRECT', 'bbcode.php?do=modify');
	print_stop_message('saved_bb_code_x_successfully', "[$bbcodetag]");
}

// ##################################### EDIT ####################################

if($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array('bbcodeid' => INT));

	$bbcode = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "bbcode WHERE bbcodeid = $bbcodeid");

	if($bbcode['twoparams'])
	{
		$bbcode['bbcodereplacement'] = str_replace('\\7', '{param}', $bbcode['bbcodereplacement']);
		$bbcode['bbcodereplacement'] = str_replace('\\5', '{option}', $bbcode['bbcodereplacement']);
	}
	else
	{
		$bbcode['bbcodereplacement'] = str_replace('\\4', '{param}', $bbcode['bbcodereplacement']);
	}

	print_form_header('bbcode', 'doupdate');
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['bb_code'], $bbcode['bbcodetag'], $bbcode['bbcodeid']), 2, 0);
	construct_hidden_code('bbcodeid', $bbcodeid);
	print_input_row($vbphrase['title'], 'title', $bbcode['title']);
	print_input_row($vbphrase['tag'], 'bbcodetag',stripslashes($bbcode['bbcodetag'])); //stripslashes to undo preg_quote
	print_textarea_row($vbphrase['replacement'], 'bbcodereplacement', $bbcode['bbcodereplacement'], 5, 60);
	print_input_row($vbphrase['example'], 'bbcodeexample', $bbcode['bbcodeexample']);
	print_textarea_row($vbphrase['description'], 'bbcodeexplanation', $bbcode['bbcodeexplanation'], 10, 60);
	print_yes_no_row($vbphrase['use_option'], 'twoparams', $bbcode['twoparams']);
	print_input_row($vbphrase['button_image_desc'], 'buttonimage', $bbcode['buttonimage']);
	print_submit_row($vbphrase['save']);

	print_form_header('', '');
	print_description_row('<span class="smallfont">' .$vbphrase['bb_code_explanations']. '</span>');
	print_table_footer();
}

####################################### UPDATE ############################################

if($_POST['do'] == 'doupdate')
{
	globalize($_POST, array(
		'bbcodeid' => INT,
		'title' => STR,
		'bbcodetag' => STR,
		'bbcodereplacement' => STR,
		'bbcodeexample' => STR,
		'bbcodeexplanation' => STR,
		'twoparams' => INT,
		'buttonimage' => STR
	));

	if (!$bbcodetag OR !$bbcodereplacement)
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($check = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "bbcode WHERE (bbcodetag = '" . addslashes($bbcodetag) . "' AND twoparams = $twoparams) AND bbcodeid <>  $bbcodeid"))
	{
		print_stop_message('there_is_already_bb_code_named_x', htmlspecialchars_uni($bbcodetag));
	}

	if ($twoparams)
	{
		$bbcodereplacement = str_replace('{param}', '\\7', $bbcodereplacement);
		$bbcodereplacement = str_replace('{option}', '\\5', $bbcodereplacement);
	}
	else
	{
		$bbcodereplacement = str_replace('{param}', '\\4', $bbcodereplacement);
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "bbcode SET
		title = '" . addslashes($title) . "',
		bbcodetag = '" . addslashes(preg_quote($bbcodetag)) . "',
		bbcodereplacement = '" . addslashes($bbcodereplacement) . "',
		bbcodeexample = '" . addslashes($bbcodeexample) . "',
		bbcodeexplanation = '" . addslashes($bbcodeexplanation) . "',
		twoparams = '" . addslashes($twoparams) . "',
		buttonimage = '" . addslashes($buttonimage) . "'
		WHERE bbcodeid = $bbcodeid
	");

	build_bbcode_cache();

	define('CP_REDIRECT', 'bbcode.php?do=modify');
	print_stop_message('saved_bb_code_x_successfully', "[$bbcodetag]");
}

// ####################################### REMOVE #####################################

if ($_REQUEST['do'] == 'remove')
{
	globalize($_REQUEST, array('bbcodeid' => INT));

	print_delete_confirmation('bbcode', $bbcodeid, 'bbcode', 'kill', 'bb_code');
}

// ######################################## KILL #####################################

if($_POST['do'] == 'kill')
{
	globalize($_POST, array('bbcodeid' => INT));

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "bbcode WHERE bbcodeid = $bbcodeid");
	build_bbcode_cache();

	$_REQUEST['do'] = 'modify';

}

// ######################################### TEST ######################################

if($_POST['do'] == 'test')
{
	globalize($_POST, array('text' => STR));

	$parsedText = nl2br(preg_replace($searcharray, $replacementarray, $text));

	print_form_header('bbcode', 'test');
	print_table_header($vbphrase['test_your_bb_code']);
	print_label_row($vbphrase['this_is_how_your_test_appeard_after_bb_code_formatting'], '<table border="0" cellspacing="0" cellpadding="4" width="100%" class="tborder"><tr class="alt2"><td>' . iif(!empty($parsedText), $parsedText, '<i>' . $vbphrase['n_a'] . '</i>') . '</td></tr></table>');
	print_textarea_row($vbphrase['enter_text_with_bb_code'], 'text', $text, 15, 60);
	print_submit_row($vbphrase['go']);

	$donetest = 1;
	$_REQUEST['do'] = 'modify';

}

// ####################################### MODIFY #####################################

if($_REQUEST['do'] == 'modify')
{
	//print_array(unserialize($datastore['bbcodecache']));

	$bbcodes = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "bbcode");

	print_form_header('bbcode', 'add');
	print_table_header($vbphrase['bb_code_manager'], 6);
	print_cells_row(array($vbphrase['title'], $vbphrase['bb_code'], $vbphrase['html'], $vbphrase['replacement'], $vbphrase['button_image'], $vbphrase['controls']), 1, '', -5);

	while ($bbcode = $DB_site->fetch_array($bbcodes))
	{
		$class = fetch_row_bgclass();
		$altclass = iif($class == 'alt1', 'alt2', 'alt1');

		if ($bbcode['twoparams'])
		{
			$regex = sprintf($doubleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
		}
		else
		{
			$regex = sprintf($singleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
		}

		$parsedCode = preg_replace($regex, $bbcode[bbcodereplacement], $bbcode['bbcodeexample']);

		$cell = array();
		$cell[] = "<b>$bbcode[title]</b>";
		$cell[] = "<div class=\"$altclass\" style=\"padding:2px; border:solid 1px; width:200px; height:75px; overflow:auto\"><span class=\"smallfont\">" . htmlspecialchars_uni($bbcode['bbcodeexample']) . '</span></div>';
		$cell[] = "<div class=\"$altclass\" style=\"padding:2px; border:solid 1px; width:200px; height:75px; overflow:auto\"><span class=\"smallfont\">" . htmlspecialchars_uni($parsedCode) . '</span></div>';
		$cell[] = $parsedCode;

		$src = $bbcode['buttonimage'];
		if (substr(strtolower($src), 0, 7) != 'http://')
		{
			$src = "../$src";
		}

		$cell[] = iif($bbcode['buttonimage'], "<img style=\"background:buttonface; border:solid 1px highlight\" src=\"$src\" alt=\"\" />", $vbphrase['n_a']);
		$cell[] = construct_link_code($vbphrase['edit'], "bbcode.php?$session[sessionurl]do=edit&bbcodeid=$bbcode[bbcodeid]") . construct_link_code($vbphrase['delete'],"bbcode.php?$session[sessionurl]do=remove&bbcodeid=$bbcode[bbcodeid]");
		print_cells_row($cell, 0, $class, -4);
	}

	print_submit_row($vbphrase['add_new_bb_code'], false, 6);

	if (empty($donetest))
	{
		print_form_header('bbcode', 'test');
		print_table_header($vbphrase['test_your_bb_code']);
		print_textarea_row($vbphrase['enter_text_with_bb_code'], 'text', '', 15, 60);
		print_submit_row($vbphrase['go']);
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 10864 $
|| ####################################################################
\*======================================================================*/
?>