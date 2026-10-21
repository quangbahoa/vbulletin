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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 92875 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cphome', 'help_faq', 'fronthelp');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_faq.php');

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
	$vbulletin->input->clean_array_gpc('p', array(
		'faqname' => TYPE_STR
	));

	// get list of items to delete
	$faqDeleteNames = implode(', ', fetch_faq_delete_list($vbulletin->GPC['faqname']));

	// delete faq
	 $db->query_write("
		DELETE FROM " . TABLE_PREFIX . "faq
		WHERE faqname IN($faqDeleteNames)
	");

	// delete phrases
	 $db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname IN ($faqDeleteNames)
			AND fieldname IN ('faqtitle', 'faqtext')
	");

	// get parent item
	$parent = $faqcache[$vbulletin->GPC['faqname']]['faqparent'];

	define('CP_REDIRECT', "faq.php?faq=$parent");
	print_stop_message('deleted_faq_item_successfully');
}

// #############################################################################

if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'faq' => TYPE_STR
	));

	print_delete_confirmation('faq', $db->escape_string($vbulletin->GPC['faq']), 'faq', 'kill', 'faq_item', '', $vbphrase['please_note_deleting_this_item_will_remove_children']);
}

// #############################################################################

if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'faq' 			=> TYPE_STR,
		'faqparent' 	=> TYPE_STR,
		'deftitle'		=> TYPE_STR
	));

	if ($vbulletin->GPC['deftitle'] == '')
	{
		print_stop_message('invalid_title_specified');
	}
	if (!preg_match('#^[a-z0-9_]+$#i', $vbulletin->GPC['faq']))
	{
		print_stop_message('invalid_faq_varname');
	}

	if ($vbulletin->GPC['faqparent'] == $vbulletin->GPC['faq'])
	{
		print_stop_message('cant_parent_faq_item_to_self');
	}
	else
	{
		$faqarray = array();
		$getfaqs = $db->query_read("SELECT faqname, faqparent FROM " . TABLE_PREFIX . "faq");
		while ($getfaq = $db->fetch_array($getfaqs))
		{
			$faqarray["$getfaq[faqname]"] = $getfaq['faqparent'];
		}
		$db->free_result($getfaqs);

		$parent_item = $vbulletin->GPC['faqparent'];
		// Traverses up the parent list to check we're not moving an faq item to something already below it
		while ($parent_item != 'faqroot' AND $parent_item != '' AND $i++ < 100)
		{
			$parent_item = $faqarray["$parent_item"];
			if ($parent_item == $vbulletin->GPC['faq'])
			{
				print_stop_message('cant_parent_faq_item_to_child');
			}
		}
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname = '" . $db->escape_string($vbulletin->GPC['faq']) . "'
			AND fieldname IN('faqtitle', 'faqtext')
			" . (!$vbulletin->debug ? 'AND languageid <> -1' : '') . "
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "faq
		WHERE faqname = '" . $db->escape_string($vbulletin->GPC['faq']) . "'
	");

	$_POST['do'] = 'insert';
}

// #############################################################################

if ($_POST['do'] == 'insert')
{
	$vars = array(
		'faq' 			=> TYPE_STR,
		'faqparent'		=> TYPE_STR,
		'volatile'		=> TYPE_INT,
		'product'		=> TYPE_STR,
		'displayorder'	=> TYPE_INT,
		'title'			=> TYPE_ARRAY_STR,	// Originally NULL though not type checking incode, used as an array
		'text'			=> TYPE_ARRAY_STR,	// Originally NULL though not type checking incode, used as an array
		'deftitle'		=> TYPE_STR,
		'deftext'		=> TYPE_STR
	);

	$vbulletin->input->clean_array_gpc('r', $vars);

	if ($vbulletin->GPC['deftitle'] == '')
	{
		print_stop_message('invalid_title_specified');
	}

	if (!preg_match('#^[a-z0-9_]+$#i', $vbulletin->GPC['faq']))
	{
		print_stop_message('invalid_faq_varname');
	}

	// ensure that the faq name is in 'word_word_word' format
	$fixedfaq = strtolower(preg_replace('#\s+#s', '_', $vbulletin->GPC['faq']));
	if ($fixedfaq !== $vbulletin->GPC['faq'])
	{
		print_form_header('faq', 'insert');
		print_table_header($vbphrase['faq_link_name_changed']);
		print_description_row(construct_phrase($vbphrase['to_maintain_compatibility_with_the_system_name_changed'], $vbulletin->GPC['faq'], $fixedfaq));
		print_input_row($vbphrase['varname'], 'faq', $fixedfaq);

		$vbulletin->GPC['faq'] = $fixedfaq;

		foreach(array_keys($vars) AS $varname_outer)
		{
			$var &= $vbulletin->GPC[$varname_outer];
			if (is_array($var))
			{
				foreach($var AS $varname_inner => $value)
				{
					construct_hidden_code($varname_outer . "[$varname_inner]", $value);
				}
			}
			else if ($vbulletin->GPC['varname'] != 'faq')
			{
				construct_hidden_code($varname_outer, $var);
			}
		}

		print_submit_row($vbphrase['continue'], 0, 2, $vbphrase['go_back']);

		print_cp_footer();
		exit;
	}

	if ($check = $db->query_first("SELECT faqname FROM " . TABLE_PREFIX . "faq WHERE faqname = '" . $db->escape_string($vbulletin->GPC['faq']) . "'"))
	{
		print_stop_message('there_is_already_faq_item_named_x', $check['faqname']);
	}

	if ($check = $db->query_first("
		SELECT varname
		FROM " . TABLE_PREFIX . "phrase
		WHERE varname = '" . $db->escape_string($vbulletin->GPC['faq']) . "'
			AND fieldname IN ('faqtitle', 'faqtext')
			" . (!$vbulletin->debug ? 'AND languageid <> -1' : '') . "
	"))
	{
		print_stop_message('there_is_already_phrase_named_x', $check['varname']);
	}

	$faqname = $db->escape_string($vbulletin->GPC['faq']);

	// set base language versions
	$baselang = iif($vbulletin->GPC['volatile'], -1, 0);

	if ($baselang != -1 OR $vbulletin->debug)
	{
		// can't edit a master version if not in debug mode
		$vbulletin->GPC['title']["$baselang"] =& $vbulletin->GPC['deftitle'];
		$vbulletin->GPC['text']["$baselang"] =& $vbulletin->GPC['deftext'];
	}

	$full_product_info = fetch_product_list(true);
	$product_version = $full_product_info[$vbulletin->GPC['product']]['version'];

	$insertSql = array();

	foreach (array_keys($vbulletin->GPC['title']) AS $languageid)
	{
		$newtitle = trim($vbulletin->GPC['title']["$languageid"]);
		$newtext = trim($vbulletin->GPC['text']["$languageid"]);

		if ($newtitle OR $newtext)
		{
			$insertSql[] = "
				($languageid,
				'$faqname',
				'" . $db->escape_string($newtitle) . "',
				'faqtitle',
				'" . $db->escape_string($vbulletin->GPC['product']) . "',
				'" . $db->escape_string($vbulletin->userinfo['username']) . "',
				" . TIMENOW . ",
				'" . $db->escape_string($product_version) . "')
			";
			$insertSql[] = "
				($languageid,
				'$faqname',
				'" . $db->escape_string($newtext) . "',
				'faqtext',
				'" . $db->escape_string($vbulletin->GPC['product']) . "',
				'" . $db->escape_string($vbulletin->userinfo['username']) . "',
				" . TIMENOW . ",
				'" . $db->escape_string($product_version) . "')
			";
		}
	}

	if (!empty($insertSql))
	{
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "phrase
				(languageid, varname, text, fieldname, product, username, dateline, version)
			VALUES
				" . implode(",\n\t", $insertSql)
		);
	}

	/*insert query*/
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "faq
			(faqname, faqparent, displayorder, volatile, product)
		VALUES
			('$faqname',
			'" . $db->escape_string($vbulletin->GPC['faqparent']) . "',
			" . $vbulletin->GPC['displayorder'] . ",
			" . $vbulletin->GPC['volatile'] . ",
			'" . $db->escape_string($vbulletin->GPC['product']) . "')
	");

	define('CP_REDIRECT', "faq.php?faq= " . $vbulletin->GPC['faqparent']);
	print_stop_message('saved_faq_x_successfully', $vbulletin->GPC['deftitle']);
}

// #############################################################################

if ($_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'add')
{
	require_once(DIR . '/includes/adminfunctions_language.php');

	$faqphrase = array();

	if ($_REQUEST['do'] == 'edit')
	{
		$vbulletin->input->clean_array_gpc('r', array(
			'faq' => TYPE_STR
		));

		$faq = $db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "faq AS faq
			WHERE faqname = '" . $db->escape_string($vbulletin->GPC['faq']) . "'
		");
		if (!$faq)
		{
			print_stop_message('no_matches_found');
		}

		$phrases = $db->query_read("
			SELECT text, languageid, fieldname
			FROM " . TABLE_PREFIX . "phrase AS phrase
			WHERE varname = '" . $db->escape_string($vbulletin->GPC['faq']) . "'
				AND fieldname IN ('faqtitle', 'faqtext')
		");
		while ($phrase = $db->fetch_array($phrases))
		{
			if ($phrase['fieldname'] == 'faqtitle')
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
		$vbulletin->input->clean_array_gpc('r', array(
			'faq' => TYPE_STR
		));

		$faq = array(
			'faqparent' => iif($vbulletin->GPC['faq'], $vbulletin->GPC['faq'], 'faqroot'),
			'displayorder' => 1,
			'volatile' => iif($vbulletin->debug, 1, 0)
		);

		?>
		<script type="text/javascript">
		<!--
		function js_check_shortname(theform, checkvb)
		{
			theform.faq.value = theform.faq.value.toLowerCase();

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

		print_form_header('faq', 'insert', 0, 1, 'cpform" onsubmit="return js_check_shortname(this, ' . iif($vbulletin->debug, 'false', 'true') . ');');
		print_table_header($vbphrase['add_new_faq_item']);
		print_input_row($vbphrase['varname'], 'faq', '', 0, '35" onblur="js_check_shortname(this.form, ' . iif($vbulletin->debug, 'false', 'true') . ');');
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

	if ($vbulletin->debug OR $defaultlang == 0)
	{
		print_input_row($vbphrase['title'], 'deftitle', $faqphrase["$defaultlang"]['title'], 1, '70" style="width:100%');
		print_textarea_row($vbphrase['text'], 'deftext', $faqphrase["$defaultlang"]['text'], 10, '70" style="width:100%');
	}
	else
	{
		construct_hidden_code('deftitle', $faqphrase["$defaultlang"]['title'], 1, 69);
		construct_hidden_code('deftext', $faqphrase["$defaultlang"]['text'], 10, 70);
		print_label_row($vbphrase['title'], htmlspecialchars_uni($faqphrase["$defaultlang"]['title']));
		print_label_row($vbphrase['text'], nl2br(htmlspecialchars_uni($faqphrase["$defaultlang"]['text'])));
	}

	print_input_row($vbphrase['display_order'], 'displayorder', $faq['displayorder']);

	if ($vbulletin->debug)
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'volatile', $faq['volatile']);
	}
	else
	{
		construct_hidden_code('volatile', $faq['volatile']);
	}

	print_select_row($vbphrase['product'], 'product', fetch_product_list(), $faq['product']);

	print_table_header($vbphrase['translations']);
	$languages = fetch_languages_array();
	foreach($languages AS $languageid => $lang)
	{

		print_input_row("$vbphrase[title] <dfn>(" . construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . ")</dfn>", "title[$languageid]", $faqphrase["$languageid"]['title'], 1, 69, 0, $lang['direction']);
		// reset bgcounter so that both entries are the same colour
		$bgcounter --;
		print_textarea_row("$vbphrase[text] <dfn>(" . construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . ")</dfn>", "text[$languageid]", $faqphrase["$languageid"]['text'], 4, 70, 1, 1, $lang['direction']);
		print_description_row('<img src="../' . $vbulletin->options['cleargifurl'] . '" width="1" height="1" alt="" />', 0, 2, 'thead');
	}

	print_submit_row($vbphrase['save']);
}

// #############################################################################

if ($_POST['do'] == 'updateorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'order' 	=> TYPE_NOCLEAN,
		'faqparent'	=> TYPE_STR
	));

	if (empty($vbulletin->GPC['order']) OR !is_array($vbulletin->GPC['order']))
	{
		print_stop_message('invalid_array_specified');
	}

	$faqnames = array();

	foreach($vbulletin->GPC['order'] AS $faqname => $displayorder)
	{
		$vbulletin->GPC['order']["$faqname"] = intval($displayorder);
		$faqnames[] = "'" . $db->escape_string($faqname) . "'";
	}

	$faqs = $db->query_read("
		SELECT faqname, displayorder
		FROM " . TABLE_PREFIX . "faq AS faq
		WHERE faqname IN (" . implode(', ', $faqnames) . ")
	");
	while($faq = $db->fetch_array($faqs))
	{
		if ($faq['displayorder'] != $vbulletin->GPC['order']["$faq[faqname]"])
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "faq
				SET displayorder = " . $vbulletin->GPC['order']["$faq[faqname]"] . "
				WHERE faqname = '" . $db->escape_string($faq['faqname']) . "'
			");
		}
	}

	define('CP_REDIRECT', "faq.php?faq=" . $vbulletin->GPC['faqparent']);
	print_stop_message('saved_display_order_successfully');
}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'faq' 	=> TYPE_STR
	));

	$faqparent = iif(empty($vbulletin->GPC['faq']), 'faqroot', $vbulletin->GPC['faq']);

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

	$nav = "<a href=\"faq.php?" . $vbulletin->session->vars['sessionurl'] . "\">$vbphrase[faq]</a>";
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
			construct_link_code($vbphrase['edit'], "faq.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;faq=" . urlencode($faqparent)) .
			construct_link_code($vbphrase['add_child_faq_item'], "faq.php?" . $vbulletin->session->vars['sessionurl'] . "do=add&amp;faq=" . urlencode($faqparent)) .
			construct_link_code($vbphrase['delete'], "faq.php?" . $vbulletin->session->vars['sessionurl'] . "do=delete&amp;faq=" . urlencode($faqparent)) .
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
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
