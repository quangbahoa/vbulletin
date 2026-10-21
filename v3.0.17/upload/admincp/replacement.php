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
define('CVS_REVISION', '$RCSfile$ - $Revision: 8911 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('style');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_template.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminstyles'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['templateid'] != 0, "template id = " . $_REQUEST['templateid'], iif($_REQUEST['dostyleid'] != 0, "style id = " . $_REQUEST['dostyleid'])));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['replacement_variable_manager'], '', '<style type="text/css">.ldi li, .lsq a { font: 11px tahoma; list-style-type:disc; }</style>');

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// *********************** kill *********************
if ($_POST['do'] == 'kill')
{
	globalize($_POST, array(
		'templateid' => INT,
		'dostyleid' => INT
	));
	$styleid = &$dostyleid;

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "template WHERE templateid = $templateid");
	print_rebuild_style($styleid, '', 0, 0, 1, 0);

	print_cp_redirect("replacement.php?$session[sessionurl]do=modify", 1);

}

// *********************** remove *********************
if ($_REQUEST['do'] == 'remove')
{
	globalize($_REQUEST, array(
		'templateid' => INT,
		'dostyleid' => INT,
		'group' => STR
	));
	$styleid = &$dostyleid;

	$hidden = array();
	$hidden['dostyleid'] = $styleid;
	$hidden['group'] = $group;
	print_delete_confirmation('template', $templateid, 'replacement', 'kill', 'replacement_variable', $hidden, $vbphrase['please_be_aware_replacement_variable_is_inherited']);

}

// *********************** update *********************
if ($_POST['do'] == 'update')
{
	globalize($_POST, array(
		'templateid' => INT,
		'dostyleid' => INT,
		'oldfind',
		'findtext',
		'replacetext'
	));
	$styleid = &$dostyleid;

	$findtext = strtolower($findtext);



	/**/
	if ($styleid != -1)
	{
		$style = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "style WHERE styleid = $styleid");
		$templateids = implode(',' , unserialize($style['templatelist']));
		$templates = $DB_site->query("
			SELECT templateid, title, template, styleid
			FROM " . TABLE_PREFIX . "template
			WHERE templateid IN ($templateids)
				AND templatetype = 'replacement'
		");
	}
	else
	{
		$templates = $DB_site->query("
			SELECT templateid, title, template, styleid
			FROM " . TABLE_PREFIX . "template
			WHERE styleid = -1
				AND templatetype = 'replacement'
		");
	}
	while ($template = $DB_site->fetch_array($templates))
	{
		$templatecache[strtolower($template['title'])] = $template;
	}

	$changed = false;
	if (isset($templatecache["$findtext"]))
	{
		$existing = &$templatecache["$findtext"];

		// prevent duplicates
		if ($templateid == 0 AND $existing['styleid'] == $dostyleid)
		{
			print_stop_message('replacement_already_exists',
				htmlspecialchars($existing['title']),
				htmlspecialchars($existing['template']),
				"replacement.php?$session[sessionurl]do=edit&amp;dostyleid=$existing[styleid]&amp;templateid=$existing[templateid]"
			);
		}

		if ($existing['template'] != $replacetext)
		{
			$changed = true;
			if ($existing['styleid'] != $styleid)
			{
				$q = "
					INSERT INTO " . TABLE_PREFIX . "template
					(styleid, templatetype, title, template)
					VALUES
					($styleid, 'replacement', '" . addslashes($findtext) . "', '" . addslashes($replacetext) . "')
				";
				//echo "<p>INSERT</p><pre>$q</pre>";
				$DB_site->query($q);
			}
			else
			{
				$q = "
					UPDATE " . TABLE_PREFIX . "template
					SET template = '" . addslashes($replacetext) . "'
					WHERE templateid = $templateid
				";
				//echo "<p>UPDATE</p><pre>$q</pre>";
				$DB_site->query($q);
			}
		}
	}
	else
	{
		$changed = true;
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "template
			(styleid, templatetype, title, template)
			VALUES
			($styleid, 'replacement', '" . addslashes($findtext) . "', '" . addslashes($replacetext) . "')
		");
		//echo "<p>inserting a new one for some reason</p>";
		//print_array($_POST);
	}

	$goto = "replacement.php?$session[sessionurl]do=modify";
	if ($changed)
	{
		print_rebuild_style($styleid, iif($styleid == -1, MASTERSTYLE, $style['title']), 0, 0, 1, 0);
		print_cp_redirect($goto, 1);
	}
	else
	{
		define('CP_REDIRECT', $goto);
		print_stop_message('nothing_to_do');
	}
	/**/
}

// *********************** edit *********************
if ($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'templateid' => INT,
		'dostyleid' => INT,
		'group' => STR
	));
	$styleid = &$dostyleid;

	$style = $DB_site->query_first("SELECT styleid, title FROM " . TABLE_PREFIX . "style WHERE styleid = $styleid");
	$replacement = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "template WHERE templateid = $templateid");

	print_form_header('replacement', 'update');
	construct_hidden_code('templateid', $templateid);
	construct_hidden_code('dostyleid', $styleid);
	construct_hidden_code('oldfind', $replacement['title']);
	if ($replacement['styleid'] == $styleid)
	{
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['replacement_variable'], $replacement['title'], $replacement['templateid']));
	}
	else
	{
		print_table_header(construct_phrase($vbphrase['customize_replacement_variable_x'], $replacement['title']));
	}
	print_label_row($vbphrase['style'], iif($styleid == -1, MASTERSTYLE, $style['title']));
	print_input_row("$vbphrase[search_for_text] <dfn>($vbphrase[case_insensitive])</dfn>", 'findtext', $replacement['title']);
	print_textarea_row($vbphrase['replace_with_text'], 'replacetext', $replacement['template'], 5, 50);
	print_submit_row($vbphrase['save']);

}

// *********************** insert *********************
if ($_POST['do'] == 'insert')
{
	globalize($_POST, array(
		'dostyleid' => INT,
		'findtext',
		'replacetext'
	));

	$findtext = strtolower($findtext);

	if ($existing = $DB_site->query_first("
		SELECT templateid, styleid, title, template
		FROM " . TABLE_PREFIX . "template
		WHERE styleid = $dostyleid
			AND templatetype = 'replacement'
			AND title = '" . addslashes($findtext) . "'
	"))
	{
		print_stop_message('replacement_already_exists',
			htmlspecialchars($existing['title']),
			htmlspecialchars($existing['template']),
			"replacement.php?$session[sessionurl]do=edit&amp;dostyleid=$existing[styleid]&amp;templateid=$existing[templateid]"
		);
	}
	else
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "template
				(styleid, templatetype, title, template)
			VALUES
				($dostyleid, 'replacement', '" . addslashes($findtext) . "', '" . addslashes($replacetext) . "')
		");

		$style = $DB_site->query_first("SELECT styleid, title FROM " . TABLE_PREFIX . "style WHERE styleid = $dostyleid");

		print_rebuild_style($style['styleid'], iif($style['styleid'] == -1, MASTERSTYLE, $style['title']), 0, 0, 1, 0);
		print_cp_redirect("replacement.php?$session[sessionurl]do=modify", 1);
	}
}

// *********************** add *********************
if ($_REQUEST['do'] == 'add')
{
	globalize($_REQUEST, array(
		'dostyleid' => INT
	));
	$styleid = &$dostyleid;

	print_form_header('replacement', 'insert');
	print_table_header($vbphrase['add_new_replacement_variable']);
	print_style_chooser_row('dostyleid', $styleid, MASTERSTYLE, $vbphrase['style'], iif($debug == 1, 1, 0));
	print_input_row("$vbphrase[search_for_text] <dfn>($vbphrase[case_insensitive])</dfn>", 'findtext', '');
	print_textarea_row($vbphrase['replace_with_text'], 'replacetext', '', 5, 50);
	print_submit_row($vbphrase['save']);

}

// *********************** modify *********************
if ($_REQUEST['do'] == 'modify')
{
	// ###################### Start displayreplacements #######################
	function print_replacements($parentid = -1, $indent = "\t")
	{
		global $DB_site, $debug, $vbphrase;
		static $stylecache, $donecache;

		if ($parentid == -1 AND $debug)
		{
			echo "<ul class=\"lsq\">\n";
			echo "\t<li><b>" . MASTERSTYLE . "</b>" . construct_link_code($vbphrase['add_new_replacement_variable'], "replacement.php?$session[sessionurl]do=add&amp;dostyleid=-1") . "\n";
			echo "\t\t<ul class=\"ldi\">\n";
			$templates = $DB_site->query("
				SELECT templateid, title
				FROM " . TABLE_PREFIX . "template
				WHERE templatetype = 'replacement'
					AND styleid = -1
			");
			if ($DB_site->num_rows($templates))
			{
				while ($template = $DB_site->fetch_array($templates))
				{
					echo "\t\t<li>" . htmlspecialchars_uni($template['title']) . construct_link_code($vbphrase['edit'], "replacement.php?$session[sessionurl]do=edit&amp;dostyleid=-1&amp;templateid=$template[templateid]").construct_link_code($vbphrase['delete'], "replacement.php?$session[sessionurl]do=remove&amp;dostyleid=-1&amp;templateid=$template[templateid]") . "\n";
				}
			}
			else
			{
				echo "\t\t\t<li>" . $vbphrase['no_replacements_defined'] . "</li>\n";
			}
			echo "\t\t</ul><br />\n\t</li>\n</ul>\n<hr size=\"1\" />\n";
		}

		// initialise the style cache if not already created
		if (empty($stylecache))
		{
			$styles = $DB_site->query("
				SELECT *
				FROM " . TABLE_PREFIX . "style
				ORDER BY parentid, displayorder
			");
			while ($style = $DB_site->fetch_array($styles))
			{
				$stylecache["$style[parentid]"]["$style[displayorder]"]["$style[styleid]"] = $style;
			}
		}

		// initialise the 'donecache' if not already created
		if (empty($donecache))
		{
			$donecache = array();
		}

		// check to see if this style actually exists / has children
		if (!isset($stylecache["$parentid"]))
		{
			return;
		}

		foreach ($stylecache["$parentid"] AS $holder)
		{
			echo "$indent<ul class=\"lsq\">\n";
			foreach ($holder AS $styleid => $style)
			{
				echo "$indent<li><b>$style[title]</b>" . construct_link_code($vbphrase['add_new_replacement_variable'], "replacement.php?$session[sessionurl]do=add&amp;dostyleid=$styleid") . "\n";
				echo "\t$indent<ul class=\"ldi\">\n";
				$templateids = implode(',', unserialize($style['templatelist']));
				$templates = $DB_site->query("SELECT templateid, title, styleid, template FROM " . TABLE_PREFIX . "template WHERE templatetype = 'replacement' AND templateid IN($templateids) ORDER BY title");
				if ($DB_site->num_rows($templates))
				{
					while ($template = $DB_site->fetch_array($templates))
					{
						if (in_array($template['templateid'], $donecache))
						{
							echo "\t\t$indent<li class=\"col-i\">" . htmlspecialchars_uni($template['title']) . construct_link_code($vbphrase['customize'], "replacement.php?$session[sessionurl]do=edit&amp;dostyleid=$styleid&amp;templateid=$template[templateid]") . "</li>\n";
						}
						else if ($template['styleid'] != -1)

						{
							echo "\t\t$indent<li class=\"col-c\">" . htmlspecialchars_uni($template['title']) . construct_link_code($vbphrase['edit'], "replacement.php?$session[sessionurl]do=edit&amp;dostyleid=$styleid&amp;templateid=$template[templateid]") . construct_link_code($vbphrase['delete'], "replacement.php?$session[sessionurl]do=remove&amp;dostyleid=$styleid&amp;templateid=$template[templateid]") . "</li>\n";
							$donecache[] = $template['templateid'];
						}
						else
						{
							echo "\t\t$indent<li class=\"col-g\">" . htmlspecialchars_uni($template['title']) . construct_link_code($vbphrase['customize'], "replacement.php?$session[sessionurl]do=edit&amp;dostyleid=$styleid&amp;templateid=$template[templateid]") . "</li>\n";
						}
					}
				}
				else
				{
					echo "\t\t$indent<li>" . $vbphrase['no_replacements_defined'] . "</li>\n";
				}

				echo "$indent\t</ul><br />\n";
				print_replacements($styleid, "$indent\t");
				echo "$indent</li>\n";
			}
			echo "$indent</ul>\n";
			if ($style['parentid'] == -1)
			{
				echo "<hr size=\"1\" />\n";
			}
		}
	}

	print_form_header('', '');
	print_table_header($vbphrase['color_key']);
	print_description_row('
	<div class="darkbg" style="border: 2px inset;"><ul class="darkbg">
		<li class="col-g">' . $vbphrase['replacement_variable_is_unchanged_from_the_default_style'] . '</li>
		<li class="col-i">' . $vbphrase['replacement_variable_is_inherited_from_a_parent_style'] . '</li>
		<li class="col-c">' . $vbphrase['replacement_variable_is_customized_in_this_style'] . '</li>
	</ul></div>
	');
	print_table_footer();

	echo "<center>\n";
	echo "<div class=\"tborder\" style=\"width: 89%\">";
	echo "<div class=\"tcat\" style=\"padding:4px\" align=\"center\"><b>$vbphrase[replacement_variables]</b></div>\n";
	echo "<div class=\"alt1\" style=\"padding: 8px\">";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: $stylevar[left]\">\n";

	print_replacements();

	echo "</div></div></div>\n</center>\n";

}

unset($DEVDEBUG);
print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 8911 $
|| ####################################################################
\*======================================================================*/
?>