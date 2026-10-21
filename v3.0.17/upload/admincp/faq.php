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
define('CVS_REVISION', '$RCSfile$ - $Revision: 9690 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cphome', 'help_faq', 'fronthelp');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_faq.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminfaq'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['faq_manager']);

// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// #############################################################################

if ($_POST['do'] == 'kill')
{
	$faqname = &$_POST['faqname'];

	// get list of items to delete
	$faqDeleteNames = implode(', ', fetch_faq_delete_list($faqname));

	// delete faq
	 $DB_site->query("
	 	DELETE FROM " . TABLE_PREFIX . "faq
		WHERE faqname IN($faqDeleteNames)
	");

	// delete phrases
	 $DB_site->query("
	 	DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname IN ($faqDeleteNames)
		AND phrasetypeid IN (" . PHRASETYPEID_FAQTITLE . ", " . PHRASETYPEID_FAQTEXT . ")
	");

	// get parent item
	$parent = $faqcache["$faqname"]['faqparent'];

	define('CP_REDIRECT', "faq.php?faq=$parent");
	print_stop_message('deleted_faq_item_successfully');
}

// #############################################################################

if ($_REQUEST['do'] == 'delete')
{
	print_delete_confirmation('faq', addslashes($_REQUEST['faq']), 'faq', 'kill', 'faq_item', '', $vbphrase['please_note_deleting_this_item_will_remove_children']);
}

// #############################################################################

if ($_POST['do'] == 'update')
{
	// check that we are not parenting this FAQ item to itself or to one of its children
	$faqarray = array();
	$_faqname = trim($_POST['faqparent']);
	$_faqparent = trim($_POST['faq']);

	if (trim($_POST['deftitle']) == '')
	{
		print_stop_message('invalid_title_specified');
	}
	if (!preg_match('#^[a-z0-9_]+$#i', trim($_POST['faq'])))
	{
		print_stop_message('invalid_faq_varname');
	}

	$getfaqs = $DB_site->query("SELECT faqname, faqparent FROM " . TABLE_PREFIX . "faq");
	while ($getfaq = $DB_site->fetch_array($getfaqs))
	{
		$faqarray["$getfaq[faqname]"] = $getfaq['faqparent'];
	}
	$DB_site->free_result($getfaqs);

	if ($_faqname == $_faqparent)
	{
		print_stop_message('cant_parent_faq_item_to_self');
	}
	else
	{
		while ($_faqname != 'faqroot' AND $_faqname != '' AND $i++ < 100)
		{
			$_faqname = $faqarray["$_faqname"];
			if ($_faqname == $_faqparent)
			{
				print_stop_message('cant_parent_faq_item_to_child');
			}
		}
	}
	// end parent validation section

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname = '" . addslashes($_POST['faq']) . "'
			AND phrasetypeid IN(" . PHRASETYPEID_FAQTITLE . ", " . PHRASETYPEID_FAQTEXT . ")
	");

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "faq
		WHERE faqname = '" . addslashes($_POST['faq']) . "'
	");

	$_POST['do'] = 'insert';
}

// #############################################################################

if ($_POST['do'] == 'insert')
{
	$vars = array(
		'faq' => STR,
		'faqparent' => STR,
		'volatile' => INT,
		'displayorder' => INT,
		'title' => NULL,
		'text' => NULL,
		'deftitle' => STR,
		'deftext' => STR,
	);

	globalize($_POST, $vars);

	if ($deftitle == '')
	{
		print_stop_message('invalid_title_specified');
	}
	if (!preg_match('#^[a-z0-9_]+$#i', $faq))
	{
		print_stop_message('invalid_faq_varname');
	}

	// ensure that the faq name is in 'word_word_word' format
	$fixedfaq = strtolower(preg_replace('#\s+#s', '_', $faq));
	if ($fixedfaq !== $faq)
	{
		print_form_header('faq', 'insert');
		print_table_header($vbphrase['faq_link_name_changed']);
		print_description_row(construct_phrase($vbphrase['to_maintain_compatibility_with_the_system_name_changed'], $faq, $fixedfaq));
		print_input_row($vbphrase['varname'], 'faq', $fixedfaq);

		$faq = $fixedfaq;

		foreach(array_keys($vars) AS $varname)
		{
			$var = $$varname;
			if (is_array($var))
			{
				foreach($var AS $_varname => $value)
				{
					construct_hidden_code($varname . "[$_varname]", $value);
				}
			}
			else if ($varname != 'faq')
			{
				construct_hidden_code($varname, $var);
			}
		}

		print_submit_row($vbphrase['continue'], 0, 2, $vbphrase['go_back']);

		print_cp_footer();
		exit;
	}

	if ($check = $DB_site->query_first("SELECT faqname FROM " . TABLE_PREFIX . "faq WHERE faqname = '" . addslashes($faq) . "'"))
	{
		print_stop_message('there_is_already_faq_item_named_x', $check['faqname']);
	}

	if ($check = $DB_site->query_first("SELECT varname FROM " . TABLE_PREFIX . "phrase WHERE varname = '" . addslashes($faq) . "' AND phrasetypeid IN (" . PHRASETYPEID_FAQTITLE . "," . PHRASETYPEID_FAQTEXT . ")"))
	{
		print_stop_message('there_is_already_phrase_named_x', $check['varname']);
	}

	$faqname = addslashes($faq);

	// set base language versions
	$baselang = iif($volatile, -1, 0);
	$title["$baselang"] = &$deftitle;
	$text["$baselang"] = &$deftext;

	$insertSql = array();

	foreach(array_keys($title) AS $languageid)
	{
		$newtitle = trim($title["$languageid"]);
		$newtext = trim($text["$languageid"]);

		if ($newtitle OR $newtext)
		{
			$insertSql[] = "($languageid, '$faqname', '" . addslashes($newtitle) . "', " . PHRASETYPEID_FAQTITLE . ")";
			$insertSql[] = "($languageid, '$faqname', '" . addslashes($newtext) . "', " . PHRASETYPEID_FAQTEXT . ")";
		}
	}

	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "phrase
		(languageid, varname, text, phrasetypeid)
		VALUES
		" . implode(",\n\t", $insertSql)
	);

	$DB_site->query("
		REPLACE INTO " . TABLE_PREFIX . "faq
		(faqname, faqparent, displayorder, volatile)
		VALUES
		('$faqname', '" . addslashes($faqparent) . "', $displayorder, $volatile)");

	define('CP_REDIRECT', "faq.php?faq=$faqparent");
	print_stop_message('saved_faq_x_successfully', $deftitle);
}

// #############################################################################

if ($_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'add')
{
	require_once('./includes/adminfunctions_language.php');

	$faqphrase = array();

	if ($_REQUEST['do'] == 'edit')
	{
		$faqname = addslashes(trim($_REQUEST['faq']));

		$faq = $DB_site->query_first("
			SELECT * FROM " . TABLE_PREFIX . "faq AS faq
			WHERE faqname = '$faqname'
		");
		if (!$faq)
		{
			print_stop_message('no_matches_found');
		}

		$phrases = $DB_site->query("
			SELECT text, languageid, phrasetypeid
			FROM " . TABLE_PREFIX . "phrase AS phrase
			WHERE varname = '$faqname'
				AND phrasetypeid IN (" . PHRASETYPEID_FAQTITLE . ", " . PHRASETYPEID_FAQTEXT . ")
		");
		while ($phrase = $DB_site->fetch_array($phrases))
		{
			if ($phrase['phrasetypeid'] == PHRASETYPEID_FAQTITLE)
			{
				$faqphrase["$phrase[languageid]"]['title'] = $phrase['text'];
			}
			else
			{
				$faqphrase["$phrase[languageid]"]['text'] = $phrase['text'];
			}
		}

		print_form_header('faq', 'update');
		construct_hidden_code('faq', $faq['faqname']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['faq_item'], $faqphrase['-1']['title'], $faq['faqname']));
	}
	else
	{
		$faq = array(
			'faqparent' => iif($_REQUEST['faq'], $_REQUEST['faq'], 'faqroot'),
			'displayorder' => 1,
			'volatile' => iif($debug, 1, 0)
		);

		?>
		<script type="text/javascript">
		<!--
		function js_check_shortname(theform, checkvb)
		{
			theform.faq.value = theform.faq.value.toLowerCase();

			if (checkvb && theform.faq.value.substring(0, 3) == 'vb_')
			{
				alert(" <?php echo $vbphrase['you_may_not_call_your_faq_items_vb_xxx']; ?>");
				return false;
			}

			for (i = 0; i < theform.faqparent.options.length; i++)
			{
				if (theform.faq.value == theform.faqparent.options[i].value)
				{
					alert(" <?php echo $vbphrase['sorry_there_is_already_an_item_called']; ?> '" + theform.faq.value + "'");
					return false;
				}
			}
			return true;
		}
		//-->
		</script>
		<?php

		print_form_header('faq', 'insert', 0, 1, 'cpform" onsubmit="return js_check_shortname(this, ' . iif($debug, 'false', 'true') . ');');
		print_table_header($vbphrase['add_new_faq_item']);
		print_input_row($vbphrase['varname'], 'faq', 'new_faq_item', 0, '35" onblur="js_check_shortname(this.form, ' . iif($debug, 'false', 'true') . ');');
	}

	cache_ordered_faq();

	$parentoptions = array('faqroot' => $vbphrase['no_parent_faq_item']);
	fetch_faq_parent_options($faq['faqname']);

	print_select_row($vbphrase['parent_faq_item'], 'faqparent', $parentoptions, $faq['faqparent']);

	if (is_array($faqphrase['-1']))
	{
		$defaultlang = -1;
	}
	else
	{
		$defaultlang = 0;
	}

	if ($debug OR $defaultlang == 0)
	{
		print_input_row($vbphrase['title'], 'deftitle', $faqphrase["$defaultlang"]['title'], 1, 69);
		print_textarea_row($vbphrase['text'], 'deftext', $faqphrase["$defaultlang"]['text'], 10, 70);
	}
	else
	{
		construct_hidden_code('deftitle', $faqphrase["$defaultlang"]['title'], 1, 69);
		construct_hidden_code('deftext', $faqphrase["$defaultlang"]['text'], 10, 70);
		print_label_row($vbphrase['title'], htmlspecialchars($faqphrase["$defaultlang"]['title']));
		print_label_row($vbphrase['text'], nl2br(htmlspecialchars($faqphrase["$defaultlang"]['text'])));
	}

	print_input_row($vbphrase['display_order'], 'displayorder', $faq['displayorder']);

	if ($debug)
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'volatile', $faq['volatile']);
	}
	else
	{
		construct_hidden_code('volatile', $faq['volatile']);
	}

	print_table_header($vbphrase['translations']);
	$languages = fetch_languages_array();
	foreach($languages AS $languageid => $lang)
	{

		print_input_row("$vbphrase[title] <dfn>(" . construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . ")</dfn>", "title[$languageid]", $faqphrase["$languageid"]['title'], 1, 69, 0, $lang['direction']);
		// reset bgcounter so that both entries are the same colour
		$bgcounter --;
		print_textarea_row("$vbphrase[text] <dfn>(" . construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . ")</dfn>", "text[$languageid]", $faqphrase["$languageid"]['text'], 4, 70, 1, 1, $lang['direction']);
		print_description_row('<img src="../' . $vboptions['cleargifurl'] . '" width="1" height="1" alt="" />', 0, 2, 'thead');
	}

	print_submit_row($vbphrase['save']);
}

// #############################################################################

if ($_POST['do'] == 'updateorder')
{
	$order = &$_POST['order'];

	if (empty($order) OR !is_array($order))
	{
		print_stop_message('invalid_array_specified');
	}

	$faqnames = array();

	foreach($order AS $faqname => $displayorder)
	{
		$order["$faqname"] = intval($displayorder);
		$faqnames[] = "'" . addslashes($faqname) . "'";
	}

	$faqs = $DB_site->query("
		SELECT faqname, displayorder
		FROM " . TABLE_PREFIX . "faq AS faq
		WHERE faqname IN (" . implode(', ', $faqnames) . ")
	");
	while($faq = $DB_site->fetch_array($faqs))
	{
		if ($faq['displayorder'] != $order["$faq[faqname]"])
		{
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "faq
				SET displayorder = " . $order["$faq[faqname]"] . "
				WHERE faqname = '" . addslashes($faq['faqname']) . "'
			");
		}
	}

	define('CP_REDIRECT', "faq.php?faq=$_POST[faqparent]");
	print_stop_message('saved_display_order_successfully');
}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{
	globalize($_REQUEST, array('faq' => STR));

	$faqparent = iif(empty($faq), 'faqroot', $faq);

	cache_ordered_faq();

	if (!is_array($ifaqcache["$faqparent"]))
	{
		$faqparent = $faqcache["$faqparent"]['faqparent'];

		if (!is_array($ifaqcache["$faqparent"]))
		{
			print_stop_message('invalid_faq_item_specified');
		}
	}

	$parents = array();
	fetch_faq_parents($faqcache["$faqparent"]['faqname']);
	$parents = array_reverse($parents);

	$nav = "<a href=\"faq.php?$session[sessionurl]\">$vbphrase[faq]</a>";
	if (!empty($parents))
	{
		$i = 1;
		foreach($parents AS $link => $name)
		{
			$nav .= '<br />' . str_repeat('&nbsp; &nbsp; ', $i) . iif(empty($link), $name, "<a href=\"$link\">$name</a>");
			$i ++;
		}
		$nav .= '
			<span class="smallfont">' .
			construct_link_code($vbphrase['edit'], "faq.php?$session[sessionurl]do=edit&amp;faq=" . urlencode($faqparent)) .
			construct_link_code($vbphrase['add_child_faq_item'], "faq.php?$session[sessionurl]do=add&amp;faq=" . urlencode($faqparent)) .
			construct_link_code($vbphrase['delete'], "faq.php?$session[sessionurl]do=delete&amp;faq=" . urlencode($faqparent)) .
			'</span>';
	}

	print_form_header('faq', 'updateorder');
	construct_hidden_code('faqparent', $faqparent);
	print_table_header($vbphrase['faq_manager'], 3);
	print_description_row("<b>$nav</b>", 0, 3);
	print_cells_row(array($vbphrase['title'], $vbphrase['display_order'], $vbphrase['controls']), 1);

	foreach($ifaqcache["$faqparent"] AS $faq)
	{
		print_faq_admin_row($faq);
		if (is_array($ifaqcache["$faq[faqname]"]))
		{
			foreach($ifaqcache["$faq[faqname]"] AS $subfaq)
			{
				print_faq_admin_row($subfaq, '&nbsp; &nbsp; &nbsp;');
			}
		}
	}

	print_submit_row($vbphrase['save_display_order'], false, 3);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9690 $
|| ####################################################################
\*======================================================================*/
?>