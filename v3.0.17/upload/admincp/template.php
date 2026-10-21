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
@set_time_limit(0);
ignore_user_abort(true);
if ($HTTP_POST_VARS['do'] == 'updatetemplate' OR $HTTP_POST_VARS['do'] == 'inserttemplate' OR $_REQUEST['do'] == 'createfiles')
{
	// double output buffering does some weird things, so turn it off in these two cases
	$nozip = 1;
}

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 13174 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('style');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
if ($_SERVER['HTTP_HOST'] == 'www.vbulletin.com')
{
	$debug = 1;
}
require_once('./includes/adminfunctions_template.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminstyles'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['templateid'] != 0, 'template id = ' . $_REQUEST['templateid'], iif($_REQUEST['dostyleid']!=0, 'style id = ' . $_REQUEST['dostyleid'])));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

globalize($_REQUEST, array('group', 'searchstring', 'titlesonly', 'searchset'));
$searchset = intval($searchset);

// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}
else
{
	$nozipDos = array('inserttemplate', 'rebuild', 'kill', 'insertstyle', 'killstyle', 'updatestyle');
	if (in_array($_REQUEST['do'], $nozipDos))
	{
		$nozip = true;
	}
}

if ($_REQUEST['do'] != 'download')
{
	print_cp_header($vbphrase['style_manager'], iif($_REQUEST['do'] == 'files', 'js_fetch_style_title()'));
	?><script type="text/javascript" src="../clientscript/vbulletin_templatemgr.js"></script><?php
}

// #############################################################################
// find custom templates that need updating

if ($_REQUEST['do'] == 'findupdates')
{
	// query custom templates
	$customcache = array();
	$templates = $DB_site->query("
		SELECT tCustom.templateid, tCustom.title, tCustom.styleid,
		tCustom.username AS customuser, tCustom.dateline AS customdate, tCustom.version AS customversion,
		tGlobal.username AS globaluser, tGlobal.dateline AS globaldate, tGlobal.version AS globalversion
		FROM " . TABLE_PREFIX . "template AS tCustom
		INNER JOIN " . TABLE_PREFIX . "template AS tGlobal ON (tGlobal.styleid = -1 AND tGlobal.title = tCustom.title)
		WHERE tCustom.styleid <> -1
			AND tCustom.templatetype = 'template'
		ORDER BY tCustom.title
	");
	while ($template = $DB_site->fetch_array($templates))
	{
		if (is_newer_version($template['globalversion'], $template['customversion']))
		{
			$customcache["$template[styleid]"]["$template[templateid]"] = $template;
		}
	}

	if (empty($customcache))
	{
		define('CP_REDIRECT', 'javascript:history.back(1)');
		print_stop_message('all_templates_are_up_to_date');
	}

	cache_styles();

	print_form_header('', '');
	print_table_header($vbphrase['updated_default_templates']);
	print_description_row('<span class="smallfont">' . construct_phrase($vbphrase['updated_default_templates_desc'], $vboptions['templateversion']) . '</span>');
	print_table_break(' ');

	foreach($stylecache AS $styleid => $style)
	{
		if (is_array($customcache["$styleid"]))
		{
			print_description_row($style['title'], 0, 2, 'thead');
			foreach($customcache["$styleid"] AS $templateid => $template)
			{
				$globaldate = vbdate($vboptions['dateformat'], $template['globaldate']);
				$globaltime = vbdate($vboptions['timeformat'], $template['globaldate']);
				if ($template['customdate'])
				{
					$customdate = vbdate($vboptions['dateformat'], $template['customdate']);
					$customtime = vbdate($vboptions['timeformat'], $template['customdate']);
				}
				else
				{
					$customdate = $vbphrase['n_a'];
					$customtime = $vbphrase['n_a'];
					$template['customuser'] = $vbphrase['n_a'];
				}
				if (!$template['customversion'])
				{
					$template['customversion'] = $vbphrase['n_a'];
				}
				print_label_row("
					<b>$template[title]</b><br />
					<span class=\"smallfont\">" .
						construct_phrase($vbphrase['default_template_updated_desc'], $template['globalversion'], $template['globaluser'], $template['customversion'], $template['customuser'])
					. '</span>',
				'<span class="smallfont">&nbsp;<br />' .
					construct_link_code($vbphrase['edit_template'], "template.php?$session[sessionurl]do=edit&amp;templateid=$templateid", 1) . '<br />' .
					construct_link_code($vbphrase['revert'], "template.php?$session[sessionurl]do=delete&amp;templateid=$templateid&amp;styleid=$styleid", 1) .
				'</span>'
				);
			}
		}
	}
	print_table_footer();

}

// #############################################################################
// download style

if ($_REQUEST['do'] == 'download')
{

	if (function_exists('set_time_limit') AND get_cfg_var('safe_mode') == 0)
	{
		@set_time_limit(1200);
	}

	globalize($_REQUEST, array(
		'dostyleid' => INT,
		'filename' => STR,
		'title' => STR,
		'mode' => INT
	));

	// --------------------------------------------
	// work out what we are supposed to do

	$styleid = &$dostyleid;

	// set a default filename
	if (empty($filename))
	{
		$filename = 'vbulletin-style.xml';
	}

	if ($styleid == -1)
	{
		// set the style title as 'master style'
		$style = array('title' => $vbphrase['master_style']);

		$sqlcondition = "styleid = -1";
	}
	else
	{
		// query everything from the specified style
		$style = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "style WHERE styleid = $styleid");

		if ($mode == 1)
		{
			// get all items from this style and all parent styles (except master)
			$sqlcondition = "styleid <> -1 AND templateid IN(" . implode(',', unserialize($style['templatelist'])) . ")";
		}
		else
		{
			// get only items customized in THIS style
			$sqlcondition = "styleid = $styleid";
		}
	}

	// set a default title
	if ($title == '' OR $styleid == -1)
	{
		$title = $style['title'];
	}

	$title = htmlspecialchars($title);

	// --------------------------------------------
	// query the templates and put them in an array

	$templates = array();

	$gettemplates = $DB_site->query("
		SELECT title, templatetype, username, dateline, version,
		IF(templatetype = 'template', template_un, template) AS template
		FROM " . TABLE_PREFIX . "template
		WHERE $sqlcondition
		ORDER BY title
	");
	while ($gettemplate = $DB_site->fetch_array($gettemplates))
	{
		switch($gettemplate['templatetype'])
		{
			case 'template': // regular template
				$isgrouped = false;
				foreach(array_keys($only) AS $group)
				{
					if (strpos(strtolower(" $gettemplate[title]"), $group) == 1)
					{
						$templates["$group"][] = $gettemplate;
						$isgrouped = true;
					}
				}
				if (!$isgrouped)
				{
					$templates['zzz'][] = $gettemplate;
				}
			break;

			case 'stylevar': // stylevar
				$templates['StyleVar Special Templates'][] = $gettemplate;
			break;

			case 'css': // css
				$templates['CSS Special Templates'][] = $gettemplate;
			break;

			case 'replacement': // replacement
				$templates['Replacement Var Special Templates'][] = $gettemplate;
			break;
		}
	}
	unset($template);
	$DB_site->free_result($templates);

	ksort($templates);

	$only['zzz'] = 'Ungrouped Templates';

	// --------------------------------------------
	// now output the XML

	$xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";
	$xml .= "<style name=\"$title\" vbversion=\"$vboptions[templateversion]\" type=\"" . iif($styleid == -1, 'master', 'custom') . "\">\r\n\r\n";

	require_once('./includes/functions_xml.php');
	foreach($templates AS $group => $grouptemplates)
	{
		$xml .= "\t<templategroup name=\"" . iif(isset($only["$group"]), $only["$group"], $group) . "\">\r\n";
		foreach($grouptemplates AS $template)
		{
			$xml .= "\t\t<template name=\"" . htmlspecialchars($template['title']) . "\" templatetype=\"$template[templatetype]\" date=\"$template[dateline]\" username=\"$template[username]\" version=\"" . htmlspecialchars_uni($template['version']) ."\"><![CDATA[" . xml_escape_cdata($template['template']) . "]]></template>\r\n";
		}
		$xml .= "\t</templategroup>\r\n\r\n";
	}

	$xml .= "</style>";

	require_once('./includes/functions_file.php');
	file_download($xml, $filename, 'text/xml');

}

// #############################################################################
// upload style

if ($_POST['do'] == 'upload')
{
	globalize($_POST, array(
		'overwritestyleid' => INT,
		'serverfile' => STR,
		'parentid' => INT,
		'title' => STR,
		'anyversion' => INT,
		'displayorder' => INT,
		'userselect' => INT,
		'stylefile' => FILE
	));
	$styleid = &$overwritestyleid;

	// got an uploaded file?
	if (file_exists($stylefile['tmp_name']))
	{
		$xml = file_read($stylefile['tmp_name']);
	}
	// no uploaded file - got a local file?
	else if (file_exists($serverfile))
	{
		$xml = file_read($serverfile);
	}
	// no uploaded file and no local file - ERROR
	else
	{
		print_stop_message('no_file_uploaded_and_no_local_file_found');
	}

	xml_import_style($xml, $styleid, $parentid, $title, $anyversion, $displayorder, $userselect);

	print_cp_redirect("template.php?$session[sessionurl]do=rebuild", 0);

}

// #############################################################################
// file manager
if ($_REQUEST['do'] == 'files')
{

	cache_styles();
	?>
	<script type="text/javascript">
	<!--
	function js_confirm_upload(tform, filefield)
	{
		if (filefield.value == "")
		{
			return confirm("<?php echo construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], '" + tform.serverfile.value + "'); ?>");
		}
		return true;
	}
	function js_fetch_style_title()
	{
		styleid = document.forms.downloadform.dostyleid.options[document.forms.downloadform.dostyleid.selectedIndex].value;
		document.forms.downloadform.title.value = style[styleid];
	}
	var style = new Array();
	style['-1'] = "<?php echo $vbphrase['master_style'] . '";';
	foreach($stylecache AS $styleid => $style)
	{
		echo "\n\tstyle['$styleid'] = \"" . addslashes($style['title']) . "\";";
		$styleoptions["$styleid"] = construct_depth_mark($style['depth'], '--', iif($debug, '--', '')) . ' ' . $style['title'];
	}
	echo "\n";
	?>
	// -->
	</script>
	<?php

	print_form_header('template', 'download', 0, 1, 'downloadform" target="download');
	print_table_header($vbphrase['download']);
	print_label_row($vbphrase['style'], '
		<select name="dostyleid" onchange="js_fetch_style_title();" tabindex="1" class="bginput">
		' . iif($debug, '<option value="-1">' . $vbphrase['master_style'] . '</option>') . '
		' . construct_select_options($styleoptions, $_REQUEST['dostyleid']) . '
		</select>
	', '', 'top', 'dostyleid');
	print_input_row($vbphrase['title'], 'title');
	print_input_row($vbphrase['filename'], 'filename', 'vbulletin-style.xml');
	print_label_row($vbphrase['options'], '
		<span class="smallfont">
		<label for="rb_mode_0"><input type="radio" name="mode" value="0" id="rb_mode_0" tabindex="1" checked="checked" />' . $vbphrase['get_customizations_from_this_style_only'] . '</label><br />
		<label for="rb_mode_1"><input type="radio" name="mode" value="1" id="rb_mode_1" tabindex="1" />' . $vbphrase['get_customizations_from_parent_styles'] . '</label>
		</span>
	', '', 'top', 'mode');
	print_submit_row($vbphrase['download']);

	print_form_header('template', 'upload', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.stylefile);');
	print_table_header($vbphrase['import_style_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'stylefile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './install/vbulletin-style.xml');
	print_style_chooser_row('overwritestyleid', -1, '(' . $vbphrase['create_new_style'] . ')', $vbphrase['overwrite_style'], 1);
	print_yes_no_row($vbphrase['ignore_style_version'], 'anyversion', 0);
	print_description_row($vbphrase['following_options_apply_only_if_new_style'], 0, 2, 'thead" style="font-weight:normal; text-align:center');
	print_input_row($vbphrase['title_for_uploaded_style'], 'title');
	print_style_chooser_row('parentid', -1, $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	print_input_row($vbphrase['display_order'], 'displayorder', 1);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', 1);

	print_submit_row($vbphrase['import']);

}

// #############################################################################
// find & replace
if ($_REQUEST['do'] == 'replace')
{
	globalize($_REQUEST, array(
		'dostyleid' => INT,
		'startat_template' => INT,
		'startat_style' => INT,
		'requirerebuild' => INT,
		'test' => INT,
		'regex' => INT,
		'searchstring',
		'replacestring'
	));
	$styleid = &$dostyleid;
	$perpage = 50;

	if (!trim($searchstring) AND !trim($replacestring))
	{
		print_stop_message('please_complete_required_fields');
	}

	$editmaster = false;
	$limit_style = $startat_style;
	if ($styleid == -1)
	{
		$conds = 'AND styleid ' . iif($debug, '<> -2', '> 0');
		if ($debug)
		{
			if ($startat_style == 0)
			{
				$editmaster = true;
			}
			else
			{
				$limit_style--; // since 0 means the master style, we have to renormalize
			}
		}
	}
	else
	{
		$conds = "AND styleid = $styleid";
	}

	if ($editmaster != true)
	{
		$styleinfo = $DB_site->query_first("SELECT styleid, title, templatelist FROM " . TABLE_PREFIX . "style WHERE 1=1 $conds LIMIT $limit_style, 1");
		if (!$styleinfo)
		{
			// couldn't grab a style, so we're done -- rebuild styles if necessary
			if ($requirerebuild)
			{
				build_all_styles(0, 0, "template.php?$session[sessionurl]do=search");
				print_cp_footer();
				exit;
			}
			else
			{
				define('CP_REDIRECT', 'template.php?do=search');
				print_stop_message('completed_search_successfully');
			}
		}
		$templatelist = unserialize($styleinfo['templatelist']);
	}
	else
	{
		$styleinfo = array(
			'styleid' => -1,
			'title' => 'MASTER STYLE'
		);
		$templatelist = array();

		$tids = $DB_site->query("SELECT title, templateid FROM " . TABLE_PREFIX . "template WHERE styleid = -1");
		while ($tid = $DB_site->fetch_array($tids))
		{
			$templatelist["$tid[title]"] = $tid['templateid'];
		}
		$styleinfo['templatelist'] = serialize($templatelist); // for sanity
	}
	echo "<p><b>" . construct_phrase($vbphrase['search_in_x'], "<i>$styleinfo[title]</i>") . "</b></p>\n";

	$loopend = $startat_template + $perpage;
	$process_templates = array(0);
	$i = 0;

	foreach ($templatelist AS $title => $tid)
	{
		if ($i >= $startat_template AND $i < $loopend)
		{
			$process_templates[] = $tid;
		}
		if ($i >= $loopend)
		{
			break;
		}
		$i++;
	}
	if ($i != $loopend)
	{
		// didn't get the $perpage templates, so we're done with this style
		$styledone = true;
	}
	else
	{
		$styledone = false;
	}

	$templates = $DB_site->query("
		SELECT templateid, styleid, title, template_un
		FROM " . TABLE_PREFIX . "template
		WHERE templateid IN (" . implode(', ', $process_templates) . ")
	");

	$page = $startat_template / $perpage + 1;
	$first = $startat_template + 1;
	$last = $startat_template + $DB_site->num_rows($templates);

	echo "<p><b>$vbphrase[search_results]</b><br />$vbphrase[page] $page, $vbphrase[templates] $first - $last</p>" . iif($test, "<p><i>$vbphrase[test_replace_only]</i></p>") . "\n";
	if ($regex)
	{
		echo "<p span=\"smallfont\"><b>" . $vbphrase['regular_expression_used'] . ":</b> " . htmlspecialchars_uni("#$searchstring#siU") . "</p>\n";
	}
	echo "<ol class=\"smallfont\" start=\"$first\">\n";

	while ($temp = $DB_site->fetch_array($templates))
	{

		echo "<li><a href=\"template.php?$session[sessionurl]do=edit&amp;templateid=$temp[templateid]&amp;dostyleid=$temp[styleid]\">$temp[title]</a>\n";
		flush();

		if ($test == 1)
		{
			if ($regex == 1)
			{
				$encodedsearchstr = str_replace('(?&lt;', '(?<', htmlspecialchars_uni($searchstring));
			}
			else
			{
				$encodedsearchstr = preg_quote(htmlspecialchars_uni($searchstring), '#');
			}
			$newtemplate = preg_replace("#$encodedsearchstr#siU", '<span class="col-i" style="text-decoration:underline;">' . htmlspecialchars_uni($replacestring) . '</span>', htmlspecialchars_uni($temp['template_un']));

			if ($newtemplate != htmlspecialchars_uni($temp['template_un']))
			{
				echo "<hr />\n<font size=\"+1\"><b>$temp[title]</b></font> (templateid: $temp[templateid], styleid: $temp[styleid])\n<pre class=\"smallfont\">" . str_replace("\t", " &nbsp; &nbsp; ", $newtemplate) . "</pre><hr />\n</li>\n";
			}
			else
			{
				echo ' (' . $vbphrase['0_matches_found'] . ")</li>\n";
			}
		}
		else
		{
			if ($regex == 1)
			{
				$newtemplate = preg_replace("#$searchstring#siU", $replacestring, $temp['template_un']);
			}
			else
			{
				$usedstr = preg_quote($searchstring, '#');
				$newtemplate = preg_replace("#$usedstr#siU", $replacestring, $temp['template_un']);
			}

			if ($newtemplate != $temp['template_un'])
			{
				if ($temp['styleid'] == $styleinfo['styleid'])
				{
					$DB_site->query("
						UPDATE " . TABLE_PREFIX . "template SET
							template = '" . addslashes(compile_template($newtemplate)) . "',
							template_un = '" . addslashes($newtemplate) . "',
							dateline = " . TIMENOW . ",
							username = '" . addslashes($bbuserinfo['username']) . "',
							version = '" . addslashes($vboptions['templateversion']) . "'
						WHERE templateid = $temp[templateid]
					");
				}
				else
				{
					$DB_site->query("
						INSERT INTO " . TABLE_PREFIX . "template
							(styleid, title, template, template_un, dateline, username, version)
						VALUES
							($styleinfo[styleid],
							 '" . addslashes($temp['title']) . "',
							 '" . addslashes(compile_template($newtemplate)) . "',
							 '" . addslashes($newtemplate) . "',
							 " . TIMENOW . ",
							 '" . addslashes($bbuserinfo['username']) . "',
							 '" . addslashes($vboptions['templateversion']) . "')
					");
					$requirerebuild = 1;
				}
				echo "<span class=\"col-i\"><b>" . $vbphrase['done'] . "</b></span></li>\n";
			}
			else
			{
				echo ' (' . $vbphrase['0_matches_found'] . ")</li>\n";
			}
		}
		flush();
	}
	echo "</ol>\n";

	if ($styledone == true)
	{
		// Go to the next style. If we're only doing replacements in one style,
		// this will trigger the finished message.
		$startat_style++;
		$loopend = 0;
	}

	$nextpage =
		"template.php?$session[sessionurl]do=replace&amp;regex=$regex&amp;requirerebuild=$requirerebuild".
		"&amp;test=$test&amp;dostyleid=$styleid&amp;startat_template=$loopend&amp;startat_style=$startat_style" .
		"&amp;searchstring=" . urlencode($searchstring) . "&amp;replacestring=" . urlencode($replacestring);


	echo "<p><b>" . construct_link_code($vbphrase['next_page'], $nextpage) . "</b></p>\n";

	if ($test)
	{
		print_cp_footer();
	}
	else
	{
		print_cp_redirect($nextpage, 1);
	}
}

// #############################################################################
// form for search / find & replace
if ($_REQUEST['do'] == 'search')
{

	// search only
	print_form_header('template', 'modify', false, true, 'sform', '90%', '', true, 'get');
	print_table_header($vbphrase['search_templates']);
	print_style_chooser_row("searchset", $_REQUEST['dostyleid'], $vbphrase['search_in_all_styles'] . iif($debug, ' (' . $vbphrase['including_master_style'] . ')'), $vbphrase['search_in_style'], 1);
	print_textarea_row($vbphrase['search_for_text'], "searchstring");
	print_yes_no_row($vbphrase['search_titles_only'], "titlesonly", 0);
	print_submit_row($vbphrase['find']);

	// search & replace
	print_form_header('template', 'replace', 0, 1, 'srform');
	print_table_header($vbphrase['find_and_replace_in_templates']);
	print_style_chooser_row("dostyleid", $_REQUEST['dostyleid'], $vbphrase['search_in_all_styles'] .  iif($debug, ' (' . $vbphrase['including_master_style'] . ')'), $vbphrase['search_in_style'], 1);
	print_textarea_row($vbphrase['search_for_text'], 'searchstring', $searchstring, 5, 60, 1, 0);
	print_textarea_row($vbphrase['replace_with_text'], 'replacestring', $replacestring, 5, 60, 1, 0);
	print_yes_no_row($vbphrase['test_replace_only'], 'test', iif(!isset($test), 1, $test));
	print_yes_no_row($vbphrase['use_regular_expressions'], 'regex', $regex);
	print_submit_row($vbphrase['find']);

	print_form_header('', '', 0, 1, 'regexform');
	print_table_header($vbphrase['notes_for_using_regex_in_find_replace']);
	print_description_row($vbphrase['regex_help']);
	print_table_footer(2, $vbphrase['strongly_recommend_testing_regex_replace']);

}

// #############################################################################
// query to insert a new style
// $dostyleid then gets passed to 'updatestyle' for cache and template list rebuild
if ($_POST['do'] == 'insertstyle')
{
	$insert = $DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "style
		(title)
		VALUES
		('" . addslashes($_POST['title']) . "')
	");

	$_POST['displayorder'] = intval($_POST['displayorder']);
	if ($_POST['displayorder'] == 0)
	{
		$_POST['displayorder'] = 1;
	}

	$_POST['dostyleid'] = $DB_site->insert_id($insert);
	$_POST['do'] = 'updatestyle';

}

// #############################################################################
// form to create a new style
if ($_REQUEST['do'] == 'addstyle')
{
	cache_styles();
	$parentid = intval($_REQUEST['parentid']);
	if ($parentid > 0 AND is_array($stylecache["$parentid"]))
	{
		$title = construct_phrase($vbphrase['child_of_x'], $stylecache["$parentid"]['title']);
	}

	print_form_header('template', 'insertstyle');
	print_table_header($vbphrase['add_new_style']);
	print_style_chooser_row('parentid', $parentid, $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	print_input_row($vbphrase['title'], 'title', $title);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', 1);
	print_input_row($vbphrase['display_order'], 'displayorder');
	print_submit_row($vbphrase['save']);

}

// #############################################################################
// query to update a style
// also rebuilds parent lists and template id cache if parentid is altered
if ($_POST['do'] == 'updatestyle')
{
	globalize($_POST, array(
		'parentid' => INT,
		'oldparentid' => INT,
		'dostyleid' => INT,
		'userselect' => INT,
		'displayorder' => INT,
		'title' => STR
	));

	// SANITY CHECK (prevent invalid nesting)
	if ($parentid == $dostyleid)
	{
		print_stop_message('cant_parent_style_to_self');
	}
	$ts_info = $DB_site->query_first("
		SELECT styleid, title, parentlist
		FROM " . TABLE_PREFIX . "style WHERE styleid = $parentid
	");
	$parents = explode(',', $ts_info['parentlist']);
	foreach($parents AS $childid)
	{
		if ($childid == $dostyleid)
		{
			print_stop_message('cant_parent_x_to_child');
		}
	}
	// end Sanity check

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "style
		SET title = '" . addslashes($title) . "',
		parentid = $parentid,
		userselect = $userselect,
		displayorder = $displayorder
		WHERE styleid = $dostyleid
	");

	build_style_datastore();

	if ($parentid != $oldparentid)
	{
		build_template_parentlists();
		print_rebuild_style($dostyleid, $title, 1, 1, 1, 1);
		print_cp_redirect("template.php?$session[sessionurl]do=modify&expandset=$dostyleid&modify&group=$group", 1);
	}
	else
	{
		define('CP_REDIRECT', "template.php?do=modify&expandset=$dostyleid&modify&group=$group");
		print_stop_message('saved_style_x_successfully', $title);
	}


}

// #############################################################################
// form to edit a style
if ($_REQUEST['do'] == 'editstyle')
{
	globalize($_REQUEST, array('dostyleid' => INT));

	$style = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "style WHERE styleid = $dostyleid");

	print_form_header('template', 'updatestyle');
	construct_hidden_code('dostyleid', $dostyleid);
	construct_hidden_code('oldparentid', $style['parentid']);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['style'], $style['title'], $style['styleid']), 2, 0);
	print_style_chooser_row('parentid', $style['parentid'], $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	print_input_row($vbphrase['title'], 'title', $style['title']);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', $style['userselect']);
	print_input_row($vbphrase['display_order'], 'displayorder', $style['displayorder']);
	print_submit_row($vbphrase['save']);

}

// #############################################################################
// kill a style, set parents for child forums and update template id caches for dependent styles
if ($_POST['do'] == 'killstyle')
{
	globalize($_POST, array(
		'dostyleid' => INT,
		'parentid' => INT,
		'parentlist' => STR
	));

	// check to see if we are deleting the last style
	$check = $DB_site->query_first("SELECT COUNT(*) AS numstyles FROM " . TABLE_PREFIX . "style");

	// Delete css file
	if ($vboptions['storecssasfile'] AND $fetchstyle = $DB_site->query_first("SELECT css FROM " . TABLE_PREFIX . "style WHERE styleid = $dostyleid"))
	{
		$fetchstyle['css'] .= "\n";
		$css = substr($fetchstyle['css'], 0, strpos($fetchstyle['css'], "\n"));

		// attempt to delete the old css file if it exists
		delete_css_file($dostyleid, $css);
	}

	if ($check['numstyles'] <= 1)
	{
		// there is only one style remaining. we will completely empty the style table and start again

		// zap all non-master templates
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "template WHERE styleid <> -1");

		// empty the style table
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "style");

		// insert a new default style
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "style
				(title, parentid, parentlist, userselect, displayorder)
			VALUES
				('Default Style', -1, '1,-1', 1, 1)
		");

		// set this to be the default style in $vboptions
		$DB_site->query("UPDATE " . TABLE_PREFIX . "setting SET value = 1 WHERE varname = 'styleid'");

		// rebuild $vboptions
		require_once('./includes/adminfunctions_options.php');
		build_options();
	}
	else
	{
		// this is not the last style, just delete it and sort out any child styles

		// zap templates belonging to this style
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "template WHERE styleid = $dostyleid");

		// delete the style itself
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "style WHERE styleid = $dostyleid");

		// update parent info for child styles
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "style
			SET parentid = $parentid,
			parentlist = '" . addslashes($parentlist) . "'
			WHERE parentid = $dostyleid
		");
	}

	build_all_styles(0, 0, "template.php?$session[sessionurl]do=modify&amp;group=$group");

	print_cp_redirect("template.php?$session[sessionurl]do=modify&amp;group=$group", 1);

}

// #############################################################################
// delete style - confirmation for style deletion
if ($_REQUEST['do'] == 'deletestyle')
{
	$styleid = intval($_REQUEST['dostyleid']);

	if ($styleid == $vboptions['styleid'])
	{
		print_stop_message('cant_delete_default_style');
	}

	// look at how many styles are being deleted
	$count = $DB_site->query_first("SELECT COUNT(*) AS styles FROM " . TABLE_PREFIX . "style WHERE userselect = 1");
	// check that this isn't the last one that we're about to delete
	$last = $DB_site->query_first("SELECT userselect FROM " . TABLE_PREFIX . "style WHERE styleid = $styleid");
	if ($count['styles'] == 1 AND $last['userselect'] == 1)
	{
		print_stop_message('cant_delete_last_style');
	}

	$style = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "style WHERE styleid = $styleid");
	$hidden = array();
	$hidden['parentid'] = $style['parentid'];
	$hidden['parentlist'] = $style['parentlist'];
	print_delete_confirmation('style', $styleid, 'template', 'killstyle', 'style', $hidden, $vbphrase['please_be_aware_this_will_delete_custom_templates']);

}

// #############################################################################
// do revert all templates in a style
if ($_POST['do'] == 'dorevertall')
{
	globalize($_POST, array(
		'dostyleid' => INT,
		'group' => STR,
	));

	if ($dostyleid != -1 AND $style = $DB_site->query_first("SELECT styleid, parentid, parentlist, title FROM " . TABLE_PREFIX . "style WHERE styleid = $dostyleid"))
	{
		// select any templates in this style that are customized from the master style
		$templates = $DB_site->query("
			SELECT t1.templateid, t1.title
			FROM " . TABLE_PREFIX . "template AS t1
			INNER JOIN " . TABLE_PREFIX . "template AS t2 ON(t2.styleid = -1 AND t2.title = t1.title)
			WHERE t1.templatetype = 'template'
			AND t1.styleid = $style[styleid]
		");
		if ($DB_site->num_rows($templates) == 0)
		{
			print_stop_message('nothing_to_do');
		}
		else
		{
			$deletetemplates = array();

			while ($template = $DB_site->fetch_array($templates))
			{
				$deletetemplates["$template[title]"] = $template['templateid'];
			}
			$DB_site->free_result($templates);

			if (!empty($deletetemplates))
			{
				$DB_site->query("DELETE FROM " . TABLE_PREFIX . "template WHERE templateid IN(" . implode(',', $deletetemplates) . ")");

				print_rebuild_style($style['styleid'], '', 0, 0, 0, 0);
			}

			print_cp_redirect("template.php?$session[sessionurl]do=modify&amp;group=$group&amp;expandset=$style[styleid]", 1);
		}
	}
	else
	{
		print_stop_message('invalid_style_specified');
	}
}

// #############################################################################
// revert all templates in a style
if ($_REQUEST['do'] == 'revertall')
{
	globalize($_REQUEST, array(
		'dostyleid' => INT,
		'group' => STR,
	));

	if ($dostyleid != -1 AND $style = $DB_site->query_first("SELECT styleid, title FROM " . TABLE_PREFIX . "style WHERE styleid = $dostyleid"))
	{
		// select any templates in this style that are customized from the master style
		$templates = $DB_site->query("
			SELECT t1.title
			FROM " . TABLE_PREFIX . "template AS t1
			INNER JOIN " . TABLE_PREFIX . "template AS t2 ON(t2.styleid = -1 AND t2.title = t1.title)
			WHERE t1.templatetype = 'template'
			AND t1.styleid = $style[styleid]
		");
		if ($DB_site->num_rows($templates) == 0)
		{
			print_stop_message('nothing_to_do');
		}
		else
		{
			$templatelist = '';
			while ($template = $DB_site->fetch_array($templates))
			{
				$templatelist .= "<li>$template[title]</li>\n";
			}
			$DB_site->free_result($templatelist);

			echo "<br /><br />";

			print_form_header('template', 'dorevertall');
			print_table_header($vbphrase['revert_all_templates']);
			print_description_row("
				<blockquote><br />
				" . construct_phrase($vbphrase["revert_all_templates_from_style_x"], $style['title'], $templatelist) . "
				<br /></blockquote>
			");
			construct_hidden_code('dostyleid', $style['styleid']);
			construct_hidden_code('group', $group);
			print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
		}
	}
	else
	{
		print_stop_message('invalid_style_specified');
	}
}

// #############################################################################
// insert queries and cache rebuilt for template insertion
if ($_POST['do'] == 'inserttemplate')
{
	globalize($_POST, array(
		'dostyleid' => INT,
		'title' => STR,
		'template',
		'return'
	));
	if (is_demo_mode() AND ($title == 'phpinclude_start' OR $title == 'phpinclude_end'))
	{
		print_cp_message("This board is running in demo mode.\nThe $title template is disabled.");
	}
	$styleid = &$dostyleid;

	// remove escaped CDATA (just in case user is pasting template direct from an XML editor
	// where the CDATA tags will have been escaped by our escaper...
	//$template = xml_unescape_cdata($template);

	if (!$title)
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($title == 'footer' AND empty($_POST['confirmremoval']))
	{
		if (strpos($template, '$vbphrase[powered_by_vbulletin]') === false)
		{
			print_form_header('template', 'inserttemplate', 0, 1, '', '75%');
			construct_hidden_code('confirmremoval', 1);
			construct_hidden_code('title', $title);
			construct_hidden_code('template', $template);
			construct_hidden_code('group', $_POST['group']);
			construct_hidden_code('dostyleid', $styleid);
			print_table_header($vbphrase['confirm_removal_of_copyright_notice']);
			print_description_row($vbphrase['it_appears_you_are_removing_vbulletin_copyright']);
			print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
			exit;
		}
	}

	$template_un = $template;
	$template = compile_template($template);

	// error checking on conditionals
	if (empty($_POST['confirmerrors']))
	{
		if ($title != 'phpinclude_start' AND $title != 'phpinclude_end')
		{
			$errors = check_template_errors($template);
		}
		else
		{
			$errors = '';
		}
		if (!empty($errors))
		{
			print_form_header('template', 'inserttemplate', 0, 1, '', '75%');
			construct_hidden_code('confirmerrors', 1);
			construct_hidden_code('title', $title);
			construct_hidden_code('template', $template_un);
			construct_hidden_code('templateid', $templateid);
			construct_hidden_code('group', $_POST['group']);
			construct_hidden_code('dostyleid', $_POST['dostyleid']);
			print_table_header($vbphrase['vbulletin_message']);
			print_description_row(construct_phrase($vbphrase['template_eval_error'], $errors));
			print_submit_row($vbphrase['continue'], 0, 2, $vbphrase['go_back']);
			exit;
		}
	}

	// check if template already exists
	if (!$preexists = $DB_site->query_first("SELECT templateid FROM " . TABLE_PREFIX . "template WHERE title = '" . addslashes($title) . "' AND styleid = $styleid"))
	{
		$result = $DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "template
				(styleid, title, template, template_un, dateline, username, version)
			VALUES
				($styleid,
				'" . addslashes("$title") . "',
				'" . addslashes("$template") . "',
				'" . addslashes("$template_un") . "',
				" . TIMENOW . ",
				'" . addslashes($bbuserinfo['username']) . "',
				'" . addslashes($vboptions['templateversion']) . "')
		");
		$templateid = $DB_site->insert_id($result);
		// now to update the template id list for this style and all its dependents...
		print_rebuild_style($styleid, '', 0, 0, 0, 0);
	}
	else
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "template SET
				template = '" . addslashes($template) . "',
				template_un = '" . addslashes($template_un) . "',
				dateline = " . TIMENOW . ",
				username = '" . addslashes($bbuserinfo['username']) . "',
				version = '" . addslashes($vboptions['templateversion']) . "'
			WHERE styleid = $styleid
				AND title = '" . addslashes($title) . "'
		");
	}

	if (isset($return))
	{
		$goto = "template.php?do=edit&amp;templateid=$templateid&amp;group=$group";
	}
	else
	{
		$goto = "template.php?$session[sessionurl_js]do=modify&expandset=$styleid&group=$group&templateid=$templateid";
	}

	print_cp_redirect($goto, 1);
}

// #############################################################################
// add a new template form
if ($_REQUEST['do'] == 'add')
{
	globalize($_REQUEST, array(
		'dostyleid' => INT,
		'templateid' => INT,
		'title' => STR,
		'group' => STR
	));
	if (is_demo_mode() AND ($title == 'phpinclude_start' OR $title == 'phpinclude_end'))
	{
		print_cp_message("This board is running in demo mode.\nThe $title template is disabled.");
	}
	$styleid = &$dostyleid;

	if ($styleid == -1)
	{
		$style['title'] = $vbphrase['global_templates'];
	}
	else
	{
		$style = $DB_site->query_first("SELECT title FROM " . TABLE_PREFIX . "style WHERE styleid = $styleid");
	}

	if ($title)
	{
		$templateinfo = $DB_site->query_first("
			SELECT * FROM " . TABLE_PREFIX . "template
			WHERE styleid = -1 AND title = '" . addslashes($title) . "'
		");
	}
	else if ($templateid)
	{
		$templateinfo = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "template WHERE templateid = $templateid");
		$title = $templateinfo['title'];
	}

	print_form_header('template', 'inserttemplate');
	print_table_header(iif($title,
		construct_phrase($vbphrase['customize_template_x'], $title),
		$vbphrase['add_new_template']
	));

	construct_hidden_code('group', $group);
	print_style_chooser_row('dostyleid', $styleid, $vbphrase['master_style'], $vbphrase['style'], iif($debug == 1, 1, 0));
	print_input_row($vbphrase['title'], 'title', $title);
	print_textarea_row($vbphrase['template'] . '
			<br /><br />
			<span class="smallfont">' .
			iif($title, construct_link_code($vbphrase['show_default'], "template.php?$session[sessionurl]do=view&amp;title=$title", 1) . '<br /><br />', '') .
			'<!--' . $vbphrase['wrap_text'] . '<input type="checkbox" unselectable="on" onclick="set_wordwrap(\'ta_template\', this.checked);" accesskey="w" checked="checked" />-->
			</span>',
		'template', $templateinfo['template_un'], 22, 75, true, true, 'ltr', 'code');
	print_template_javascript();
	print_submit_row($vbphrase['save'], '_default_', 2, '', "<input type=\"submit\" class=\"button\" tabindex=\"1\" name=\"return\" value=\"$vbphrase[save] &amp; $vbphrase[reload]\" accesskey=\"e\" />");

}

// #############################################################################
// simple update query for an existing template
if ($_POST['do'] == 'updatetemplate')
{
	globalize($_POST, array(
		'templateid' => INT,
		'title' => STR,
		'oldtitle' => STR,
		'template',
		'group' => STR,
		'dostyleid' => INT,
		'string' => STR,
		'searchstring',
		'return',
		'confirmerrors',
	));

	// remove escaped CDATA (just in case user is pasting template direct from an XML editor
	// where the CDATA tags will have been escaped by our escaper...
	// $template = xml_unescape_cdata($template);
	$template_un = $template;
	$template = compile_template($template);

	if (is_demo_mode() AND ($title == 'phpinclude_start' OR $title == 'phpinclude_end'))
	{
		print_cp_message("This board is running in demo mode.\nThe $title template is disabled.");
	}

	$old_template = $DB_site->query_first("
		SELECT title, styleid
		FROM " . TABLE_PREFIX . "template
		WHERE templateid = $templateid
	");
	if ($title != $old_template['title'] AND $DB_site->query_first("
		SELECT templateid
		FROM " . TABLE_PREFIX . "template
		WHERE styleid = $old_template[styleid] AND title = '" . addslashes($title) . "'
	"))
	{
		print_stop_message('template_x_exists', $title);
	}

	// error checking on conditionals
	if (empty($confirmerrors))
	{
		if ($title != 'phpinclude_start' AND $title != 'phpinclude_end')
		{
			$errors = check_template_errors($template);
		}
		else
		{
			$errors = '';
		}
		if (!empty($errors))
		{
			print_form_header('template', 'updatetemplate', 0, 1, '', '75%');
			construct_hidden_code('confirmerrors', 1);
			construct_hidden_code('title', $title);
			construct_hidden_code('template', $template_un);
			construct_hidden_code('templateid', $templateid);
			construct_hidden_code('group', $group);
			construct_hidden_code('searchstring', $searchstring);
			construct_hidden_code('dostyleid', $dostyleid);
			print_table_header($vbphrase['vbulletin_message']);
			print_description_row(construct_phrase($vbphrase['template_eval_error'], $errors));
			print_submit_row($vbphrase['continue'], 0, 2, $vbphrase['go_back']);
			print_cp_footer();
			exit;
		}
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "template SET
			title = '" . addslashes($title) . "',
			template = '" . addslashes($template) . "',
			template_un = '" . addslashes($template_un) . "',
			dateline = " . TIMENOW . ",
			username = '" . addslashes($bbuserinfo['username']) . "',
			version = '" . addslashes($vboptions['templateversion']) . "'
		WHERE templateid = $templateid
	");

	if (isset($return))
	{
		$goto = "template.php?do=edit&amp;templateid=$templateid&amp;group=$group&searchstring=" . urlencode($searchstring);
	}
	else
	{
		$goto = "template.php?do=modify&amp;expandset=$dostyleid&amp;group=$group&amp;templateid=$templateid&amp;searchstring=" . urlencode($searchstring);
	}

	if ($title == $oldtitle)
	{
		if (isset($return))
		{
			print_cp_redirect($goto);
		}
		else
		{
			$_REQUEST['do'] = 'modify';
			$_REQUEST['expandset'] = $dostyleid;
		}

		$_REQUEST['group'] = $group;
		$_REQUEST['searchstring'] = iif ($string, $string, $searchstring);
		$_REQUEST['templateid'] = $templateid;

		//define('CP_REDIRECT', $goto);
		//print_stop_message('saved_template_x_successfully', $title);
	}
	else
	{
		print_rebuild_style($dostyleid, '', 0, 0, 0, 0);
		print_cp_redirect($goto, 1);
	}
}

// #############################################################################
// edit form for an existing template
if ($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'templateid' => INT,
		'group' => STR,
		'searchstring'
	));

	$template = $DB_site->query_first("
		SELECT template.*, style.title AS style
		FROM " . TABLE_PREFIX . "template AS template
		LEFT JOIN " . TABLE_PREFIX . "style AS style USING(styleid)
		WHERE templateid = $templateid
	");
	if ($template['styleid'] == -1)
	{
		$template['style'] = $vbphrase['global_templates'];
	}
	if (is_demo_mode() AND ($template['title'] == 'phpinclude_start' OR $template['title'] == 'phpinclude_end'))
	{
		print_cp_message("This board is running in demo mode.\nThe $template[title] template is disabled.");
	}
	print_form_header('template', 'updatetemplate');
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['template'], $template['title'], $template['templateid']));
	construct_hidden_code('templateid', $template['templateid']);
	construct_hidden_code('group', $group);
	construct_hidden_code('searchstring', $searchstring);
	construct_hidden_code('dostyleid', $template['styleid']);
	construct_hidden_code('oldtitle', $template['title']);

	$backlink = "template.php?$session[sessionurl]do=modify&amp;expandset=$template[styleid]&amp;group=$group&amp;templateid=$templateid&amp;searchstring=" . urlencode($searchstring);

	print_label_row($vbphrase['style'], "<a href=\"$backlink\" title=\"" . $vbphrase['edit_templates'] . "\"><b>$template[style]</b></a>");
	print_input_row($vbphrase['title'], 'title', $template['title']);
	print_textarea_row($vbphrase['template'] . '
			<br /><br />
			<span class="smallfont">' .
			iif($template['styleid'] != -1, construct_link_code($vbphrase['show_default'], "template.php?$session[sessionurl]do=view&amp;title=$template[title]", 1) . '<br /><br />', '') .
			'<!--' . $vbphrase['wrap_text'] . '<input type="checkbox" unselectable="on" onclick="set_wordwrap(\'ta_template\', this.checked);" accesskey="w" checked="checked" />-->
			</span>',
		'template', $template['template_un'], 22, 75, true, true, 'ltr', 'code');
	print_template_javascript();
	print_submit_row($vbphrase['save'], '_default_', 2, '', "<input type=\"submit\" class=\"button\" tabindex=\"1\" name=\"return\" value=\"$vbphrase[save] &amp; $vbphrase[reload]\" accesskey=\"e\" />");

}

// #############################################################################
// kill a template and update template id caches for dependent styles
if ($_POST['do'] == 'kill')
{
	globalize($_POST, array('templateid' => INT));

	$template = $DB_site->query_first("SELECT styleid FROM " . TABLE_PREFIX . "template WHERE templateid = $templateid");

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "template WHERE templateid='$templateid'");
	print_rebuild_style($template['styleid'], '', 0, 0, 0, 0);

	print_cp_redirect("template.php?$session[sessionurl]do=modify&amp;expandset=$template[styleid]&amp;group=$group", 1);

}

// #############################################################################
// confirmation for template deletion
if ($_REQUEST['do'] == 'delete')
{
	globalize($_REQUEST, array(
		'templateid' => INT,
		'dostyleid' => INT,
		'group' => STR
	));
	$styleid = &$dostyleid;

	$hidden = array();
	$hidden['group'] = $group;
	print_delete_confirmation('template', $templateid, 'template', 'kill', 'template', $hidden, $vbphrase['please_be_aware_template_is_inherited']);

}

// #############################################################################
// lets the user see the original template
if ($_REQUEST['do'] == 'view')
{
	globalize($_REQUEST, array('title'));

	$template = $DB_site->query_first("
		SELECT templateid, styleid, title, template_un
		FROM " . TABLE_PREFIX . "template
		WHERE styleid = -1 AND title = '" . addslashes($title) . "'
	");

	print_form_header('', '');
	print_table_header($vbphrase['show_default']);
	print_textarea_row($template['title'], '--[-ORIGINAL-TEMPLATE-]--', $template['template_un'], 20, 80);
	print_table_footer();
}


// #############################################################################
// update display order values
if ($_POST['do'] == 'dodisplayorder')
{
	globalize($_POST, array(
		'displayorder',
		'userselect'
	));

	$styles = $DB_site->query("SELECT styleid, parentid, title, displayorder, userselect FROM " . TABLE_PREFIX . "style");
	if ($DB_site->num_rows($styles))
	{
		while ($style = $DB_site->fetch_array($styles))
		{
			$order = intval($displayorder["$style[styleid]"]);
			$uperm = intval($userselect["$style[styleid]"]);
			if ($style['displayorder'] != $order OR $style['userselect'] != $uperm)
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "style
					SET displayorder = $order,
					userselect = $uperm
					WHERE styleid = $style[styleid]
				");
			}
		}
	}

	$_REQUEST['do'] = "modify";

	build_style_datastore();

}

// #############################################################################
// main template list display
if ($_REQUEST['do'] == 'modify')
{
	// populate the stylecache
	cache_styles();

	$expandset = htmlspecialchars_uni($_REQUEST['expandset']);

	// sort out parameters for searching
	if ($searchstring)
	{
		$group = 'all';
		if ($searchset > 0)
		{
			$expandset = $searchset;
		}
		else
		{
			$parentlist = '-1';
			$expandset = 'all';
		}
	}
	else
	{
		$searchstring = '';
	}

	if (is_numeric($expandset))
	{
		$style = $DB_site->query_first("SELECT parentlist FROM " . TABLE_PREFIX . "style WHERE styleid = $expandset");
		$parentlist = $style['parentlist'];
	}

	// display the nice interface to people with a decent browser (MSIE >=4)
	if (!empty($enhanced_template_editor) OR (empty($standard_template_editor) AND (is_browser('ie', '4.0') OR is_browser('mozilla'))))
	{
		define('FORMTYPE', 1);
		$SHOWTEMPLATE = 'construct_template_option';
	}
	else
	{
		define('FORMTYPE', 0);
		$SHOWTEMPLATE = 'construct_template_link';
	}

	if ($debug)
	{
		$JS_STYLETITLES[] = "\"0\" : \"" . $vbphrase['master_style'] . "\"";
		$prepend = '--';
	}

	foreach($stylecache AS $style)
	{
		$JS_STYLETITLES[] = "\"$style[styleid]\" : \"" . addslashes($style['title']) . "\"";
		$JS_STYLEPARENTS[] = "\"$style[styleid]\" : \"$style[parentid]\"";
	}

	$JS_MONTHS = array();
	$i = 0;
	$months = array('january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december');
	foreach($months AS $month)
	{
		$JS_MONTHS[] = "\"$i\" : \"" . $vbphrase["$month"] . "\"";
		$i++;
	}

	foreach (array(
		'click_the_expand_collapse_button',
		'this_template_has_been_customized_in_a_parent_style',
		'this_template_has_not_been_customized',
		'this_template_has_been_customized_in_this_style',
		'template_last_edited_js',
		'x_templates',
		'download_style_advanced_options'
		) AS $phrasename)
	{
		$JS_PHRASES[] = "\"$phrasename\" : \"" . fetch_js_safe_string($vbphrase["$phrasename"]) . "\"";
	}

?>

<script type="text/javascript">
<!--
var SESSIONHASH = "<?php echo $session['sessionhash']; ?>";
var EXPANDSET = "<?php echo $expandset; ?>";
var GROUP = "<?php echo $group; ?>";
var SEARCHSTRING = "<?php echo urlencode($searchstring); ?>";
var STYLETITLE = { <?php echo implode(', ', $JS_STYLETITLES); ?> };
var STYLEPARENTS = { <?php echo implode(', ', $JS_STYLEPARENTS); ?> };
var MONTH = { <?php echo implode(', ', $JS_MONTHS); ?> };
var vbphrase = {
	<?php echo implode(",\r\n\t", $JS_PHRASES) . "\r\n"; ?>
};

// -->
</script>

<?php
if (!FORMTYPE)
{
	print_form_header('', '');
	print_table_header("$vbphrase[styles] &amp; $vbphrase[templates]");
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="col-g">' . $vbphrase['template_is_unchanged_from_the_default_style'] . '</li>
		<li class="col-i">' . $vbphrase['template_is_inherited_from_a_parent_style'] . '</li>
		<li class="col-c">' . $vbphrase['template_is_customized_in_this_style'] . '</li>
		</ul></div>
	');
	print_table_footer();
}
else
{
	echo "<br />\n";
}

if ($help = construct_help_button('', NULL, '', 1))
{
	$pagehelplink = "<div style=\"float:$stylevar[right]\">$help</div>";
}
else
{
	$pagehelplink = '';
}

?>

<form action="template.php" method="post" tabindex="1" name="tform">
<input type="hidden" name="do" value="dodisplayorder" />
<input type="hidden" name="s" value="<?php echo htmlspecialchars_uni($session['sessionhash']); ?>" />
<input type="hidden" name="expandset" value="<?php echo htmlspecialchars_uni($expandset); ?>" />
<input type="hidden" name="group" value="<?php echo htmlspecialchars_uni($group); ?>" />
<div align="center">
<div class="tborder" style="width:90%; text-align:<?php echo $stylevar['left']; ?>">
<div class="tcat" style="padding:4px; text-align:center"><?php echo $pagehelplink; ?><b><?php echo $vbphrase['style_manager']; ?></b></div>
<div class="stylebg">

<?php

	if (!empty($expandset))
	{
		DEVDEBUG("Querying master template ids");
		$masters = $DB_site->query("
			SELECT templateid, title
			FROM " . TABLE_PREFIX . "template
			WHERE templatetype = 'template'
				AND styleid = -1
			ORDER BY title
		");
		while ($master = $DB_site->fetch_array($masters))
		{
			$masterset["$master[title]"] = $master['templateid'];
		}
	}
	else
	{
		$masterset = array();
	}

	$LINKEXTRA = '';
	if (!empty($group))
	{
		$LINKEXTRA .= "&amp;group=$group";
	}
	if (!empty($searchstring))
	{
		$LINKEXTRA .= "&amp;searchstring=" . urlencode($searchstring);
	}

	if ($debug)
	{
		print_style(-1);
	}
	foreach($stylecache AS $styleid => $style)
	{
		print_style($styleid, $style);
	}

?>
</div>
<table cellpadding="2" cellspacing="0" border="0" width="100%" class="tborder" style="border: 0px">
<tr>
	<td class="tfoot" align="center">
		<input type="submit" class="button" tabindex="1" value="<?php echo $vbphrase['save_display_order']; ?>" />
		<input type="button" class="button" tabindex="1" value="<?php echo $vbphrase['search_in_templates']; ?>" onclick="window.location='template.php?<?php echo $session['sessionurl']; ?>do=search';" />
	</td>
</tr>
</table>
</div>
</div>
</form>
<?php

	echo '<p align="center" class="smallfont">' .
		construct_link_code($vbphrase['add_new_style'], "template.php?$session[sessionurl]do=addstyle");
	if ($debug)
	{
		echo construct_link_code($vbphrase['rebuild_all_styles'], "template.php?$session[sessionurl]do=rebuild&amp;goto=template.php?$session[sessionurl]") .
		construct_link_code('Compare Remote/Local Global Templates', '../docs/templatedev.php" target="templatedev', 0);
	}
	echo "</p>\n";


	// search only
	/*
	print_form_header('template', 'modify');
	print_table_header($vbphrase['search_templates']);
	construct_hidden_code('searchset', -1);
	construct_hidden_code('titlesonly', 0);
	print_input_row($vbphrase['search_for_text'], 'searchstring', $searchstring);
	print_description_row('<input type="button" value="Submit with GET" onclick="window.location = (\'template.php?do=modify&amp;searchset=-1&amp;searchstring=\' + this.form.searchstring.value)" />');
	print_submit_row($vbphrase['find']);
	*/

}

// #############################################################################
// rebuilds all parent lists and id cache lists
if ($_REQUEST['do'] == 'rebuild')
{
	globalize($_REQUEST, array(
		'renumber' => INT,
		'install' => INT
	));

	echo "<p>&nbsp;</p>";

	build_all_styles($renumber, $install, "template.php?$session[sessionurl]");

}

// #############################################################################
// create template files

if ($_REQUEST['do'] == 'createfiles' AND $debug)
{
	// this action requires that a web-server writable folder called
	// 'template_dump' exists in the root of the vbulletin directory

	globalize($_REQUEST, array(
		'filename' => STR,
		'title' => STR,
		'mode' => INT,
		'dostyleid' => INT
	));
	$styleid = &$dostyleid;

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if (function_exists('set_time_limit') AND get_cfg_var('safe_mode')==0)
	{
		@set_time_limit(1200);
	}

	chdir('./template_dump');

	$templates = $DB_site->query("
		SELECT title, templatetype, username, dateline, template_un AS template
		FROM " . TABLE_PREFIX . "template
		WHERE styleid = $styleid
			AND templatetype = 'template'
			" . iif($mode == 1, "AND templateid IN($templateids)") . "
		ORDER BY title
	");
	echo "<ol>\n";
	while ($template = $DB_site->fetch_array($templates))
	{
		echo "<li><b class=\"col-c\">$template[title]</b>: Parsing... ";
		$text = str_replace("\r\n", "\n", $template['template']);
		$text = str_replace("\n", "\r\n", $text);
		echo 'Writing... ';
		$fp = fopen("./$template[title].htm", 'w+');
		fwrite($fp, $text);
		fclose($fp);
		echo "<span class=\"col-i\">Done</span></li>\n";
	}
	echo "</ol>\n";
}

// #############################################################################
// hex convertor
if ($_REQUEST['do'] == 'colorconverter')
{
	globalize($_REQUEST, array(
		'hex' => STR_NOHTML,
		'rgb' => STR_NOHTML,
		'hexdec', 'dechex'
	));

	if ($dechex)
	{
		$rgb = preg_split('#\s*,\s*#si', $rgb, -1, PREG_SPLIT_NO_EMPTY);
		$hex = '#';
		foreach ($rgb AS $i => $value)
		{
			$hex .= strtoupper(str_pad(dechex($value), 2, '0', STR_PAD_LEFT));
		}
		$rgb = implode(',', $rgb);
	}
	else if ($hexdec)
	{
		if (preg_match('/#?([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})/siU', $hex, $matches))
		{
			$rgb = array();
			for ($i = 1; $i <= 3; $i++)
			{
				$rgb[] = hexdec($matches["$i"]);
			}
			$rgb = implode(',', $rgb);
			$hex = strtoupper("#$matches[1]$matches[2]$matches[3]");
		}
	}

	print_form_header('template', 'colorconverter');
	print_table_header('Color Converter');
	print_label_row('Hexadecimal Color (#xxyyzz)', "<span style=\"padding:4px; background-color:$hex\"><input type=\"text\" class=\"bginput\" name=\"hex\" value=\"$hex\" size=\"20\" maxlength=\"7\" /> <input type=\"submit\" class=\"button\" name=\"hexdec\" value=\"Hex &raquo; RGB\" /></span>");
	print_label_row('RGB Color (r,g,b)', "<span style=\"padding:4px; background-color:rgb($rgb)\"><input type=\"text\" class=\"bginput\" name=\"rgb\" value=\"$rgb\" size=\"20\" maxlength=\"11\" /> <input type=\"submit\" class=\"button\" name=\"dechex\" value=\"RGB &raquo; Hex\" /></span>");
	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13174 $
|| ####################################################################
\*======================================================================*/
?>