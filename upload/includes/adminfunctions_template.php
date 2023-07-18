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

// note #1: arrays used by functions in this code are declared at the bottom of the page
// note #2: REMEMBER to update the $template_table_query if the table changes!!!

/**
* Expand and collapse button labels
*/
define('EXPANDCODE', '&laquo; &raquo;');
define('COLLAPSECODE', '&raquo; &laquo;');

/**
* Size in rows of template editor <select>
*/
define('TEMPLATE_EDITOR_ROWS', 25);

/**
* List of special purpose templates used by css.php and build_style()
*/
$_query_common_templates = array(
	'header',
	'footer',
	'headinclude'
);

global $_query_special_templates;
 $_query_special_templates = array(
	// message editor menu contents
	'editor_jsoptions_font',
	'editor_jsoptions_size',
	// message editor interface styles
	'editor_styles_button_normal',
	'editor_styles_button_hover',
	'editor_styles_button_down',
	'editor_styles_button_selected',
	'editor_styles_menu_normal',
	'editor_styles_menu_hover',
	'editor_styles_menu_down',
	'editor_styles_popup_down',
);

/**
* Initialize the IDs for colour preview boxes
*/
$numcolors = 0;

/**
* Query used for creating the temporary template table
*/
$template_table_query = "
CREATE TABLE " . TABLE_PREFIX . "template_temp (
	templateid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	styleid SMALLINT NOT NULL DEFAULT '0',
	title VARCHAR(100) NOT NULL DEFAULT '',
	template MEDIUMTEXT,
	template_un MEDIUMTEXT,
	templatetype enum('template','stylevar','css','replacement') NOT NULL default 'template',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(100) NOT NULL DEFAULT '',
	version VARCHAR(30) NOT NULL DEFAULT '',
	product VARCHAR(25) NOT NULL,
	PRIMARY KEY (templateid),
	UNIQUE KEY title (title, styleid, templatetype)
)
";

/**
* Fields selected when copying the template table to template_temp
*/
$template_table_fields = 'styleid, title, template, template_un, templatetype, dateline, username, version, product';

// #############################################################################
/**
* Trims the string passed to it
*
* @param	string	(ref) String to be trimmed
*/
function array_trim(&$val)
{
	$val = trim($val);
}

// #############################################################################
/**
* Returns an SQL query string to update a single template
*
* @param	string	Title of template
* @param	string	Un-parsed template HTML
* @param	integer	Style ID for template
* @param	array	(ref) array('template' => array($title => true))
* @param	string	The name of the product this template is associated with
*
* @return	string
*/
function fetch_template_update_sql($title, $template, $dostyleid, &$delete, $product = 'vbulletin')
{
	global $vbulletin, $_query_special_templates, $template_cache;

	$oldtemplate = $template_cache['template']["$title"];

	if (is_array($template))
	{
		array_walk($template, 'array_trim');
		$template = "background: $template[background]; color: $template[color]; padding: $template[padding]; border: $template[border];";
	}

	// check if template should be deleted
	if (!empty($delete['tempate'][$title]))
	{
		return "### DELETE TEMPLATE $title ###
			DELETE FROM " . TABLE_PREFIX . "template
			WHERE templateid = $oldtemplate[templateid]
		";
	}

	if ($template == $oldtemplate['template_un'])
	{
		return false;
	}
	else
	{
		// check for copyright removal
		if ($title == 'footer' // only check footer template
			AND strpos($template, '$vbphrase[powered_by_vbulletin]') === false // template to be saved has no copyright
			AND strpos($oldtemplate['template_un'], '$vbphrase[powered_by_vbulletin]') !== false // pre-saved template includes copyright - therefore a removal attempt is being made
		)
		{
			print_stop_message('you_can_not_remove_vbulletin_copyright');
		}

		// parse template conditionals
		if (!in_array($title, $_query_special_templates))
		{
			$parsedtemplate = compile_template($template);

			$errors = check_template_errors($parsedtemplate);

			// halt if errors in conditionals
			if (!empty($errors))
			{
				print_stop_message('error_in_template_x_y', $title, "<i>$errors</i>");
			}
		}
		else
		{
			$parsedtemplate =& $template;
		}

		$full_product_info = fetch_product_list(true);

		return "
			### REPLACE TEMPLATE: $title ###
			REPLACE INTO " . TABLE_PREFIX . "template
				(styleid, title, template, template_un, templatetype, dateline, username, version, product)
			VALUES
				(" . intval($dostyleid) . ",
				'" . $vbulletin->db->escape_string($title) . "',
				'" . $vbulletin->db->escape_string($parsedtemplate) . "',
				'" . $vbulletin->db->escape_string($template) . "',
				'template',
				" . TIMENOW . ",
				'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
				'" . $vbulletin->db->escape_string($full_product_info["$product"]['version']) . "',
				'" . $vbulletin->db->escape_string($product) . "')
		";
	}

}

// #############################################################################
/**
* Checks the style id of a template item and works out if it is inherited or not
*
* @param	integer	Style ID from template record
*
* @return	string	CSS class name to use to display item
*/
function fetch_inherited_color($itemstyleid, $styleid)
{

	switch ($itemstyleid)
	{
		case $styleid: // customized in current style, or is master set
			if ($styleid == -1)
			{
				return 'col-g';
			}
			else
			{
				return 'col-c';
			}
		case -1: // inherited from master set
			return 'col-g';
		default: // inhertited from parent set
			return 'col-i';
	}

}

// #############################################################################
/**
* Returns an array of all styles that are parents to the style specified
*
* @param	integer	Style ID
*
* @return	array
*/
function fetch_template_parentlist($styleid)
{
	global $vbulletin;

	$ts_info = $vbulletin->db->query_first("SELECT parentid FROM " . TABLE_PREFIX . "style WHERE styleid = $styleid");

	$ts_array = $styleid;

	if ($ts_info['parentid'] != 0)
	{
		#$ts_array .= ',' . fetch_style_parentlist($ts_info['parentid']);
		$ts_array .= ',' . fetch_template_parentlist($ts_info['parentid']);
	}

	if (substr($ts_array, -2) != '-1')
	{
		$ts_array .= '-1';
	}

	return $ts_array;
}

// #############################################################################
/**
* Saves the correct style parentlist to each style in the database
*/
function build_template_parentlists()
{
	global $vbulletin;

	$styles = $vbulletin->db->query_read("SELECT styleid, title, parentlist, parentid, userselect FROM " . TABLE_PREFIX . "style ORDER BY parentid");
	while($style = $vbulletin->db->fetch_array($styles))
	{
		$parentlist = fetch_template_parentlist($style['styleid']);
		if ($parentlist != $style['parentlist'])
		{
			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "style
				SET parentlist = '" . $vbulletin->db->escape_string($parentlist) . "'
				WHERE styleid = $style[styleid]
			");
		}
	}

}

// #############################################################################
/**
* Returns the style parentlist for the specified style
*
* @param	integer	Style ID
*
* @return	string
*/
function fetch_style_parentlist($styleid)
{
	global $vbulletin, $ts_cache;

	static $ts_arraycache;

	if (isset($ts_arraycache["$styleid"]))
	{
		return $ts_arraycache["$styleid"];
	}
	elseif (isset($ts_cache["$styleid"]))
	{
		return $ts_cache["$styleid"]['parentlist'];
	}
	else
	{
		$ts_info = $vbulletin->db->query_first("
			SELECT parentlist
			FROM " . TABLE_PREFIX . "style
			WHERE styleid = $styleid
		");
		$ts_arraycache["$styleid"] = $ts_info['parentlist'];
		return $ts_info['parentlist'];
	}
}

// #############################################################################
/**
* Fetches a list of template IDs for the specified style
*
* @param	integer	Style ID
* @param	boolean	If true, returns a list of template ids; if false, goes ahead and runs the update query
* @param	mixed	A comma-separated list of style parent ids (if false, will query to fetch the list)
*
* @return	mixed	Either the list of template ids, or nothing
*/
function build_template_id_cache($styleid, $doreturn = false, $parentids = false)
{
	global $vbulletin;

	if ($styleid == -1)
	{
		// doesn't have a cache
		return '';
	}

	if ($parentids == 0)
	{
		$style = $vbulletin->db->query_first("
			SELECT styleid, title, parentlist
			FROM " . TABLE_PREFIX . "style
			WHERE styleid = $styleid
		");
		if (empty($style))
		{
			trigger_error('Invalid styleid specified', E_USER_ERROR);
		}
	}
	else
	{
		$style['parentlist'] = $parentids;
	}

	$parents = explode(',', $style['parentlist']);
	$i = sizeof($parents);
	$totalparents = $i;
	foreach($parents AS $setid)
	{
		if ($setid != -1)
		{
			$querySele = ",\nt$i.templateid AS templateid_$i, t$i.title AS title$i, t$i.styleid AS styleid_$i $querySele";
			$queryJoin = "\nLEFT JOIN " . TABLE_PREFIX . "template AS t$i ON (t1.title=t$i.title AND t$i.styleid=$setid)$queryJoin";
			$i--;
		}
	}

	$bbcodestyles = array();
	$templatelist = array();
	$templates = $vbulletin->db->query_read("
		SELECT t1.templateid AS templateid_1, t1.title $querySele
		FROM " . TABLE_PREFIX . "template AS t1 $queryJoin
		WHERE t1.styleid = -1
		ORDER BY t1.title
	");
	while ($template = $vbulletin->db->fetch_array($templates, DBARRAY_BOTH))
	{
		for ($tid = $totalparents; $tid > 0; $tid--)
	{
			if ($template["templateid_$tid"])
			{
				$templatelist["$template[title]"] = $template["templateid_$tid"];
				if (preg_match('#^bbcode_[code|html|php|quote]+$#si', trim($template['title'])))
				{
					$bbcodetemplate = $template['title'] . '_styleid';
					if ($template["styleid_$tid"])
					{
						$templatelist["$bbcodetemplate"] = $template["styleid_$tid"];
					}
					else
					{
						$templatelist["$bbcodetemplate"] = -1;
					}
				}
				break;
			}
		}
	}

	$customdone = array();
	$customtemps = $vbulletin->db->query_read("
		SELECT t1.templateid, t1.title, INSTR(',$style[parentlist],', CONCAT(',', t1.styleid, ',') ) AS ordercontrol, t1.styleid
		FROM " . TABLE_PREFIX . "template AS t1
		LEFT JOIN " . TABLE_PREFIX . "template AS t2 ON (t2.title=t1.title AND t2.styleid=-1)
		WHERE t1.styleid IN (" . substr(trim($style['parentlist']), 0, -3) . ") AND
		t2.title IS NULL
		ORDER BY title, ordercontrol
	");
	while ($template = $vbulletin->db->fetch_array($customtemps))
	{
		if ($customdone["$template[title]"])
		{
			continue;
		}
		$customdone["$template[title]"] = 1;
		$templatelist["$template[title]"] = $template['templateid'];

		if (preg_match('#^bbcode_[code|html|php|quote]+$#si', trim($template['title'])))
		{
			$bbcodetemplate = $template['title'] . '_styleid';
			$templatelist["$bbcodetemplate"] = $template['styleid'];
		}
	}

	$templatelist = serialize($templatelist);

	if (!$doreturn)
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "style
			SET templatelist = '" . $vbulletin->db->escape_string($templatelist) . "'
			WHERE styleid = $styleid
		");
	}
	else
	{
		return $templatelist;
	}
}

// #############################################################################
/**
* Builds all data from the template table into the fields in the style table
*
* @param	boolean	If true, will drop the template table and rebuild, so that template ids are renumbered from zero
* @param	boolean	If true, will fix styles with no parent style specified
* @param	string	If set, will redirect to specified URL on completion
*/
function build_all_styles($renumber = 0, $install = 0, $goto = '')
{
	global $vbulletin, $template_table_query, $template_table_fields, $vbphrase;

	// -----------------------------------------------------------------------------
	// -----------------------------------------------------------------------------
	// this bit of text is used for upgrade scripts where the phrase system
	// is not available it should NOT be converted into phrases!!!
	$phrases = array(
		'master_style' => 'MASTER STYLE',
		'done' => 'Done',
		'style' => 'Style',
		'styles' => 'Styles',
		'templates' => 'Templates',
		'css' => 'CSS',
		'stylevars' => 'Stylevars',
		'replacement_variables' => 'Replacement Variables',
		'controls' => 'Controls',
		'rebuild_style_information' => 'Rebuild Style Information',
		'updating_style_information_for_each_style' => 'Updating style information for each style',
		'updating_styles_with_no_parents' => 'Updating style sets with no parent information',
		'updated_x_styles' => 'Updated %1$s Styles',
		'no_styles_needed_updating' => 'No Styles Needed Updating',
	);
	foreach ($phrases AS $key => $val)
	{
		if (!isset($vbphrase["$key"]))
		{
			$vbphrase["$key"] = $val;
		}
	}
	// -----------------------------------------------------------------------------
	// -----------------------------------------------------------------------------

	if (!empty($goto))
	{
		$form_tags = true;
	}

	echo "<!--<p>&nbsp;</p>-->
	<blockquote>" . iif($form_tags, "<form>") . "<div class=\"tborder\">
	<div class=\"tcat\" style=\"padding:4px\" align=\"center\"><b>" . $vbphrase['rebuild_style_information'] . "</b></div>
	<div class=\"alt1\" style=\"padding:4px\">\n<blockquote>
	";
	vbflush();

	// useful for restoring utterly broken (or pre vb3) styles
	if ($install)
	{
		echo "<p><b>" . $vbphrase['updating_styles_with_no_parents'] . "</b></p>\n<ul class=\"smallfont\">\n";
		vbflush();
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "style
			SET parentid = -1,
			parentlist = CONCAT(styleid,',-1')
			WHERE parentid = 0
		");
		$affected = $vbulletin->db->affected_rows();
		if ($affected)
		{
			echo "<li>" . construct_phrase($vbphrase['updated_x_styles'], $affected) . "</li>\n";
			vbflush();
		}
		else
		{
			echo "<li>" . $vbphrase['no_styles_needed_updating'] . "</li>\n";
			vbflush();
		}
		echo "</ul>\n";
		vbflush();
	}

	// creates a temporary table in order to renumber all templates from 1 to n sequentially
	if ($renumber)
	{
		echo "<p><b>" . $vbphrase['updating_template_ids'] . "</b></p>\n<ul class=\"smallfont\">\n";
		vbflush();
		$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . "template_temp");
		$vbulletin->db->query_write($template_table_query);
		echo "<li>" . $vbphrase['temporary_template_table_created'] . "</li>\n";
		vbflush();

		/*insert query*/
		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "template_temp
			($template_table_fields)
			SELECT $template_table_fields FROM " . TABLE_PREFIX . "template ORDER BY styleid, templatetype, title
		");
		$rows = $vbulletin->db->affected_rows();
		echo "<li>" . construct_phrase($vbphrase['temporary_template_table_populated_with_x_templates'], $rows) . "</li>\n";
		vbflush();

		$vbulletin->db->query_write("DROP TABLE " . TABLE_PREFIX . "template");
		echo "<li>" . $vbphrase['old_template_table_dropped'] . "</li>\n";
		vbflush();

		$vbulletin->db->query_write("ALTER TABLE " . TABLE_PREFIX . "template_temp RENAME " . TABLE_PREFIX . "template");
		echo "<li>" . $vbphrase['temporary_template_table_renamed'] . "</li>\n";
		vbflush();

		echo "</ul>\n";
		vbflush();
	}

	// the main bit.
	echo "<p><b>" . $vbphrase['updating_style_information_for_each_style'] . "</b></p>\n";
	vbflush();

	build_template_parentlists();

	$styleactions = array('docss' => 1, 'dostylevars' => 1, 'doreplacements' => 1, 'doposteditor' => 1);
	if (defined('NO_POST_EDITOR_BUILD'))
	{
		$styleactions['doposteditor'] = 0;
	}
	build_style(-1, $vbphrase['master_style'], $styleactions);

	echo "</blockquote></div>";
	if ($form_tags)
	{
		echo "
		<div class=\"tfoot\" style=\"padding:4px\" align=\"center\">
		<input type=\"button\" class=\"button\" value=\" " . $vbphrase['done'] . " \" onclick=\"window.location='$goto';\" />
		</div>";
	}
	echo "</div>" . iif($form_tags, "</form>") . "</blockquote>
	";
	vbflush();

	build_style_datastore();
}

// #############################################################################
/**
* Displays a style rebuild (build_style) in a nice user-friendly info page
*
* @param	integer	Style ID to rebuild
* @param	string	Title of style
* @param	boolean	Build CSS?
* @param	boolean	Build Stylevars?
* @param	boolean	Build Replacements?
* @param	boolean	Build Post Editor?
*/
function print_rebuild_style($styleid, $title = '', $docss = 1, $dostylevars = 1, $doreplacements = 1, $doposteditor = 1)
{
	global $vbulletin, $vbphrase;

	$styleid = intval($styleid);

	if (empty($title))
	{
		if ($styleid == -1)
		{
			$title = $vbphrase['master_style'];
		}
		else
		{
			DEVDEBUG('Querying first style name');
			$getstyle = $vbulletin->db->query_first("
				SELECT title
				FROM " . TABLE_PREFIX . "style
				WHERE styleid = $styleid
			");
			if (!$getstyle)
			{
				return;
			}

			$title = $getstyle['title'];
		}
	}

	echo "<p>&nbsp;</p>
	<blockquote><form><div class=\"tborder\">
	<div class=\"tcat\" style=\"padding:4px\" align=\"center\"><b>" . $vbphrase['rebuild_style_information'] . "</b></div>
	<div class=\"alt1\" style=\"padding:4px\">\n<blockquote>
	<p><b>" . construct_phrase($vbphrase['updating_style_information_for_x'], $title) . "</b></p>
	<ul class=\"lci\">\n";
	vbflush();

	build_style($styleid, $title, array(
		'docss' => $docss,
		'dostylevars' => $dostylevars,
		'doreplacements' => $doreplacements,
		'doposteditor' => $doposteditor
	));

	echo "</ul>\n<p><b>" . $vbphrase['done'] . "</b></p>\n</blockquote></div>
	</div></form></blockquote>
	";
	vbflush();
}

// #############################################################################
/**
* Attempts to delete the file specified in the <link rel /> for this style
*
* @param	integer	Style ID
* @param	string	CSS contents
*/
function delete_css_file($styleid, $csscontents)
{
	if (preg_match('#@import url\("(clientscript/vbulletin_css/style-\w{8}-0*' . $styleid . '\.css)"\);#siU', $csscontents, $match))
	{
		// attempt to delete old css file
		@unlink("./$match[1]");
	}
}

// #############################################################################
/**
* Attempts to create a new css file for this style
*
* @param	string	CSS filename
* @param	string	CSS contents
*
* @return	boolean	Success
*/
function write_css_file($filename, $contents)
{
	// attempt to write new css file - store in database if unable to write file
	$destination = DIR . "/$filename";
	if ($fp = @fopen($destination, 'w') AND !is_demo_mode())
	{
		fwrite($fp, $contents);
		@fclose($fp);
		return true;
	}
	else
	{
		@fclose($fp);
		return false;
	}
}

// #############################################################################
/**
* Converts all data from the template table for a style into the style table
*
* @param	integer	Style ID
* @param	string	Title of style
* @param	array	Array of actions set to true/false: docss/dostylevars/doreplacements/doposteditor
* @param	string	List of parent styles
* @param	string	Indent for HTML printing
*/
function build_style($styleid, $title = '', $actions, $parentlist = '', $indent = '')
{
	global $vbulletin, $_queries, $vbphrase, $_query_special_templates;
	static $phrase, $csscache;

	if (($actions['doreplacements'] OR $actions['docss']) AND $vbulletin->options['storecssasfile'])
	{
		$actions['docss'] = true;
		$actions['doreplacements'] = true;
	}

	if ($styleid != -1)
	{
		$QUERY = array();
		// echo the title and start the listings
		echo "$indent<li><b>$title</b> ... <span class=\"smallfont\">";
		vbflush();

		// build the templateid cache
		$templatelist = build_template_id_cache($styleid, 1, $parentlist);
		$QUERY[] = "templatelist = '" . $vbulletin->db->escape_string($templatelist)  . "'";
		echo "($vbphrase[templates]) ";
		vbflush();

		// cache special templates
		if ($actions['docss'] OR $actions['dostylevars'] OR $actions['doreplacements'] OR $actions['doposteditor'])
		{
			// get special templates for this style
			$template_cache = array();
			$templateids = implode(',' , vb_unserialize($templatelist));
			$templates = $vbulletin->db->query_read("
				SELECT title, template, templatetype
				FROM " . TABLE_PREFIX . "template
				WHERE templateid IN ($templateids)
					AND (templatetype <> 'template' OR title IN('" . implode("', '", $_query_special_templates) . "'))
			");
			while ($template = $vbulletin->db->fetch_array($templates))
			{
				$template_cache["$template[templatetype]"]["$template[title]"] = $template;
			}
			$vbulletin->db->free_result($templates);
		}

		// style vars
		if ($actions['dostylevars'])
		{
			// rebuild the stylevars field for this style
			$stylevars = array();
			foreach($template_cache['stylevar'] AS $template)
			{
				// set absolute paths for image directories
				/*if (substr($template['title'], 0, 7) == 'imgdir_')
				{
					if (!preg_match('#^https?://#i', $template['template']))
					{
						$template['template'] = "$template[template]";
					}
				}*/
				$stylevars["$template[title]"] = $template['template'];
			}

			$QUERY[] = "stylevars = '" . $vbulletin->db->escape_string(serialize($stylevars)) . '\'';
			echo "($vbphrase[stylevars]) ";
			vbflush();
		}

		// replacements
		if ($actions['doreplacements'])
		{
			// rebuild the replacements field for this style
			$replacements = array();

			if (!empty($template_cache['replacement']) AND is_array($template_cache['replacement']))
			{
				foreach($template_cache['replacement'] AS $template)
				{
					// set the key to be a case-insentitive preg find string
					$replacementkey = '#' . preg_quote($template['title'], '#') . '#si';

					$replacements["$replacementkey"] = $template['template'];
				}
				$QUERY[] = 'replacements = \'' . $vbulletin->db->escape_string(serialize($replacements)) . '\'';
			}
			else
			{
				$QUERY[] = 'replacements = \'\'';
			}
			echo "($vbphrase[replacement_variables]) ";
			vbflush();
		}

		// css
		if ($actions['docss'])
		{
			// build a quick cache with the ~old~ contents of the css fields from the style table
			if (!is_array($csscache))
			{
				$csscache = array();
				$fetchstyles = $vbulletin->db->query_read("SELECT styleid, css FROM " . TABLE_PREFIX . "style");
				while ($fetchstyle = $vbulletin->db->fetch_array($fetchstyles))
				{
					$fetchstyle['css'] .= "\n";
					$csscache["$fetchstyle[styleid]"] = $fetchstyle['css'];
				}
			}

			// rebuild the css field for this style
			$css = array();
			if (!empty($template_cache['css']) AND is_array($template_cache['css']))
			{
				foreach($template_cache['css'] AS $template)
				{
					$css["$template[title]"] = vb_unserialize($template['template']);
				}
			}

			// build the CSS contents
			$csscolors = array();
			$css = construct_css($css, $styleid, $title, $csscolors);

			// attempt to delete the old css file if it exists
			delete_css_file($styleid, $csscache["$styleid"]);

			$adblock_is_evil = str_replace('ad', 'be', substr(md5(microtime()), 8, 8));
			$cssfilename = 'clientscript/vbulletin_css/style-' . $adblock_is_evil . '-' . str_pad($styleid, 5, '0', STR_PAD_LEFT) . '.css';

			// if we are going to store CSS as files, run replacement variable substitution on the file to be saved
			if ($vbulletin->options['storecssasfile'])
			{
				$css = process_replacement_vars($css, array('styleid' => $styleid, 'replacements' => serialize($replacements)));
				$css = preg_replace_callback('#(?<=[^a-z0-9-]|^)url\((\'|"|)(.*)\\1\)#iU', "rewrite_css_file_url_callback", $css);
				if (write_css_file($cssfilename, $css))
				{
					$css = "@import url(\"$cssfilename\");";
				}
			}

			$fullcsstext = "<style type=\"text/css\" id=\"vbulletin_css\">\r\n" .
				"/**\r\n* vBulletin " . $vbulletin->options['templateversion'] . " CSS\r\n* Style: '$title'; Style ID: $styleid\r\n*/\r\n" .
				"$css\r\n</style>\r\n" .
				"<link rel=\"stylesheet\" type=\"text/css\" href=\"clientscript/vbulletin_important.css?v=" . $vbulletin->options['simpleversion'] . "\" />"
			;

			$QUERY[] = "css = '" . $vbulletin->db->escape_string($fullcsstext) . "'";
			$QUERY[] = "csscolors = '" . $vbulletin->db->escape_string(serialize($csscolors)) . "'";

			echo "($vbphrase[css]) ";
			vbflush();
		}

		// post editor styles
		if ($actions['doposteditor'])
		{
			$editorstyles = array();
			if (!empty($template_cache['template']) AND is_array($template_cache['template']))
			{
				foreach ($template_cache['template'] AS $template)
				{
					if (substr($template['title'], 0, 13) == 'editor_styles')
					{
						$title = 'pi' . substr($template['title'], 13);
						$item = fetch_posteditor_styles($template['template']);
						$editorstyles["$title"] = array($item['background'], $item['color'], $item['padding'], $item['border']);
					}
				}

				$QUERY[] = 'editorstyles = \'' . $vbulletin->db->escape_string(serialize($editorstyles)) . '\'';
			}
			echo "($vbphrase[controls]) ";
			vbflush();
		}

		// do the style update query
		if (sizeof($QUERY))
		{
			$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "style SET\n" . implode(",\n", $QUERY) . "\nWHERE styleid = $styleid");
		}

		// finish off the listings
		echo "</span><b>" . $vbphrase['done'] . "</b>.<br />&nbsp;</li>\n"; vbflush();
	}

	$childsets = $vbulletin->db->query_read("
		SELECT styleid, title, parentlist
		FROM " . TABLE_PREFIX . "style
		WHERE parentid = $styleid
	");
	if ($vbulletin->db->num_rows($childsets))
	{
		echo "$indent<ul class=\"ldi\">\n";
		while ($childset = $vbulletin->db->fetch_array($childsets))
		{
			build_style($childset['styleid'], $childset['title'], $actions, $childset['parentlist'], $indent . "\t");
		}
		echo "$indent</ul>\n";
	}
}

// #############################################################################
/**
* Extracts a color value from a css string
*
* @param	string	CSS color value
*
* @return	string
*/
function fetch_color_value($csscolor)
{
	if (preg_match('/^(rgb\s*\([0-9,\s]+\)|(#?\w+))(\s|$)/siU', $csscolor, $match))
	{
		return $match[1];
	}
	else
	{
		return $csscolor;
	}
}

/**
 * Attempts to return a six-character hex value for a given color value (hex, rgb or named)
 *
 * @param	string	CSS color value
 * @return	string
 */
function fetch_color_hex_value($csscolor)
{
	static $html_color_names = null,
	       $html_color_names_regex = null,
	       $system_color_names = null,
	       $system_color_names_regex = null;

	if (!is_array($html_color_names))
	{
		require_once(DIR . '/includes/html_color_names.php');

		$html_color_names_regex = implode('|', array_keys($html_color_names));

		$system_color_names = (
			strpos(strtolower(USER_AGENT), 'macintosh') !== false
			? $system_color_names_mac
			: $system_color_names_win
		);

		$system_color_names_regex = implode('|', array_keys($system_color_names));
	}

	$hexcolor = '';

	// match a hex color
	if (preg_match('/\#([0-9a-f]{6}|#[0-9a-f]{3})($|[^0-9a-f])/siU', $csscolor, $match))
	{
		if (strlen($match[1]) == 3)
		{
			$hexcolor .= $match[1]{0} . $match[1]{0} . $match[1]{1} . $match[1]{1} . $match[1]{2} . $match[1]{2};
		}
		else
		{
			$hexcolor .= $match[1];
		}
	}
	// match an RGB color
	else if (preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/siU', $csscolor, $match))
	{
		for ($i = 1; $i <= 3; $i++)
		{
			$hexcolor .= str_pad(dechex($match["$i"]), 2, 0, STR_PAD_LEFT);
		}
	}
	// match a named color
	else if (preg_match("/(^|[^\w])($html_color_names_regex)($|[^\w])/siU", $csscolor, $match))
	{
		$hexcolor = $html_color_names[strtolower($match[2])];
	}
	// match a named system color (CSS2, deprecated)
	else if (preg_match("/(^|[^\w])($system_color_names_regex)($|[^\w])/siU", $csscolor, $match))
	{
		$hexcolor = $system_color_names[strtolower($match[2])];
	}
	else
	{
		// failed to match a color
		return false;
	}

	return strtoupper($hexcolor);
}

// #############################################################################
/**
* Reads the input from the CSS editor and builds it into CSS code
*
* @param	array	Submitted data from css.php?do=edit
* @param	integer	Style ID
* @param	string	Title of style
* @param	array	(ref) Array of extracted CSS colour values
*
* @return	string
*/
function construct_css($css, $styleid, $styletitle, &$csscolors)
{
	global $vbulletin;

	// remove the 'EXTRA' definition and stuff it in at the end :)
	$extra = isset($css['EXTRA']) ? trim($css['EXTRA']['all']) : '';
	$extra2 = isset($css['EXTRA2']) ? trim($css['EXTRA2']['all']) : '';
	unset($css['EXTRA'], $css['EXTRA2']);

	// initialise the stylearray
	$cssarray = array();

	// order for writing out CSS variables
	$css_write_order = array(
		'body',
		'.page',
		'td, th, p, li',
		'.tborder',
		'.tcat',
		'.thead',
		'.tfoot',
		'.alt1, .alt1Active',
		'.alt2, .alt2Active',
		'.inlinemod',
		'.wysiwyg',
		'textarea, .bginput',
		'.button',
		'select',
		'.smallfont',
		'.time',
		'.navbar',
		'.highlight',
		'.fjsel',
		'.fjdpth0',
		'.fjdpth1',
		'.fjdpth2',
		'.fjdpth3',
		'.fjdpth4',

		'.panel',
		'.panelsurround',
		'legend',

		'.vbmenu_control',
		'.vbmenu_popup',
		'.vbmenu_option',
		'.vbmenu_hilite',
	);

	($hook = vBulletinHook::fetch_hook('css_output_build')) ? eval($hook) : false;

	// loop through the $css_write_order array to make sure we
	// write the css into the template in the correct order

	foreach($css_write_order AS $itemname)
	{
		unset($links, $thisitem);

		if (!empty($css["$itemname"]) && is_array($css["$itemname"]))
		{
			foreach($css["$itemname"] AS $cssidentifier => $value)
			{
				if (preg_match('#^\.(\w+)#si', $itemname, $match))
				{
					$itemshortname = $match[1];
				}
				else
				{
					$itemshortname = $itemname;
				}

				switch ($cssidentifier)
				{
					// do normal links
					case 'LINK_N':
					{
						if ($getlinks = construct_link_css($itemname, $cssidentifier, $value))
						{
							$links['normal'] = $getlinks;
						}
					}
					break;

					// do visited links
					case 'LINK_V':
					{
						if ($getlinks = construct_link_css($itemname, $cssidentifier, $value))
						{
							$links['visited'] = $getlinks;
						}
					}
					break;

					// do hover links
					case 'LINK_M':
					{
						if ($getlinks = construct_link_css($itemname, $cssidentifier, $value))
						{
							$links['hover'] = $getlinks;
						}
					}
					break;

					// do extra attributes
					case 'EXTRA':
					case 'EXTRA2':
					{
						if (!empty($value))
						{
							$value = "\t" . str_replace("\r\n", "\r\n\t", $value);
							$thisitem[] = "$value\r\n";
						}
					}
					break;

					// do font bits
					case 'font':
					{
						if ($getfont = construct_font_css($value))
						{
							$thisitem[] = $getfont;
						}
					}
					break;

					// normal attributes
					default:
					{
						$value = trim($value);
						if ($value != '')
						{
							switch ($cssidentifier)
							{
								case 'background':
								{
									$csscolors["{$itemshortname}_bgcolor"] = fetch_color_value($value);
								}
								break;

								case 'color':
								{
									$csscolors["{$itemshortname}_fgcolor"] = fetch_color_value($value);
								}
								break;
							}
							$thisitem[] = "\t$cssidentifier: $value;\r\n";
						}
					}

				}
			}
		}
		// add the item to the css if it's not blank
		if (!empty($thisitem))
		{
			$cssarray[] = "$itemname\r\n{\r\n" . implode('', $thisitem) . "}\r\n" . (empty($links) ? '' : $links['normal'] . $links['visited'] . $links['hover']);

			if ($itemname == 'select')
			{
				$optioncss = array();
				if ($optionsize = trim($css["$itemname"]['font']['size']))
				{
					$optioncss[] = "\tfont-size: $optionsize;\r\n";
				}
				if ($optionfamily = trim($css["$itemname"]['font']['family']))
				{
					$optioncss[] = "\tfont-family: $optionfamily;\r\n";
				}
				$cssarray[] = "option, optgroup\r\n{\r\n" . implode('', $optioncss) . "}\r\n";
			}
			else if ($itemname == 'textarea, .bginput')
			{
				$optioncss = array();
				if ($optionsize = trim($css["$itemname"]['font']['size']))
				{
					$optioncss[] = "\tfont-size: $optionsize;\r\n";
				}
				if ($optionfamily = trim($css["$itemname"]['font']['family']))
				{
					$optioncss[] = "\tfont-family: $optionfamily;\r\n";
				}
				$cssarray[] = ".bginput option, .bginput optgroup\r\n{\r\n" . implode('', $optioncss) . "}\r\n";
			}
		}
	}

	// generate hex colors
	foreach ($css_write_order AS $itemname)
	{
		if (!empty($css["$itemname"]) && is_array($css["$itemname"]))
		{
			$itemshortname = (strpos($itemname, '.') === 0 ? substr($itemname, 1) : $itemname);

			foreach($css["$itemname"] AS $cssidentifier => $value)
			{
				switch ($cssidentifier)
				{
					case 'LINK_N':
					case 'LINK_V':
					case 'LINK_M':
					{
						if ($value['color'] != '')
						{
							$csscolors[$itemshortname . '_' . strtolower($cssidentifier) . '_fgcolor'] = fetch_color_value($value['color']);
						}

						if ($value['background'] != '')
						{
							$csscolors[$itemshortname . '_' . strtolower($cssidentifier) . '_bgcolor'] = fetch_color_value($value['background']);
						}
					}
					break;

					// do extra attributes
					case 'EXTRA':
					case 'EXTRA2':
					{
						if (preg_match('#border(-color)?\s*\:\s*([^;]+);#siU', $value, $match))
						{
							$csscolors[$itemshortname . '_border_color'] = fetch_color_value($match[2]);
						}
					}
					break;
				}
			}
		}
	}

	$csscolors_hex = array();

	foreach ($csscolors AS $colorname => $colorvalue)
	{
		$hexcolor = fetch_color_hex_value($colorvalue);

		if ($hexcolor !== false)
		{
			$csscolors_hex[$colorname . '_hex'] = $hexcolor;
		}
	}

	$csscolors = array_merge($csscolors, $csscolors_hex);

	($hook = vBulletinHook::fetch_hook('css_output_build_end')) ? eval($hook) : false;

	return trim(implode('', $cssarray) . "$extra\r\n$extra2");
}

// #############################################################################
/**
* Returns a URL for use in CSS, dealing with directory nesting etc.
*
* @param	string	URL
* @param	string	Quote type (single quote, double quote)
*
* @return	string	example: url('/path/to/file.ext')
*/
function rewrite_css_file_url($url, $delimiter = '')
{
	static $iswritable = null;
	if ($iswritable === null)
	{
		$iswritable = is_writable(DIR . '/clientscript/vbulletin_css/');
	}

	$url = str_replace('\\"', '"', $url);
	$delimiter = str_replace('\\"', '"', $delimiter);

	if (!$iswritable OR preg_match('#^(https?://|/)#i', $url))
	{
		return "url($delimiter$url$delimiter)";
	}
	else
	{
		return "url($delimiter../../$url$delimiter)";
	}
}

/**
 * Callback function for rewritting css file url
 * 
 * @param  string $url       
 * @param  string $delimiter 
 * @return [type]            
 */
function rewrite_css_file_url_callback($matches)
{
	return rewrite_css_file_url($matches[2], $matches[1]);
}

// #############################################################################
/**
* Takes the font style input from css.php?do=edit and returns valid CSS
*
* @param	array	Array of values from form
*
* @return	string
*/
function construct_font_css($font)
{
	// possible values for CSS 'font-weight' attribute
	$css_font_weight = array('normal', 'bold', 'bolder', 'lighter');

	// possible values for CSS 'font-style' attribute
	$css_font_style = array('normal', 'italic', 'oblique');

	// possible values for CSS 'font-variant' attribute
	$css_font_variant = array('normal', 'small-caps');

	foreach($font AS $key => $value)
	{
		$font["$key"] = trim($value);
	}

	$out = '';

	if (!empty($font['size']) AND !empty($font['family']))
	{

		foreach ($font AS $value)
		{
			$out .= "$value ";
		}
		$out = trim($out);
		if (!empty($out))
		{
			$out = "\tfont: $out;\r\n";
		}

	}
	else
	{

		if (!empty($font['size']))
		{
			$out .= "\tfont-size: $font[size];\r\n";
		}
		if (!empty($font['family']))
		{
			$out .= "\tfont-family: $font[family];\r\n";
		}
		if (!empty($font['style']))
		{
			$stylebits = explode(' ', $font['style']);
			foreach($stylebits AS $bit)
			{
				$bit = strtolower($bit);
				if (in_array($bit, $css_font_weight) OR preg_match('/[1-9]{1}00/', $bit))
				{
					$out .= "\tfont-weight: $bit;\r\n";
				}
				if (in_array($bit, $css_font_style))
				{
					$out .= "\tfont-style: $bit;\r\n";
				}
				if (in_array($bit, $css_font_variant))
				{
					$out .= "\tfont-variant: $bit;\r\n";
				}
				if (preg_match('/(pt|\.|%)/siU', $bit))
				{
					$out .= "\tline-height: $bit;\r\n";
				}
			}
		}

	}

	if (trim($out) == '')
	{
		return false;
	}
	else
	{
		return $out;
	}

}

// #############################################################################
/**
* Takes the link style input from css.php?do=edit and returns valid CSS
*
* @param	array	Items from form
* @param	string	Link type (LINK_N, LINK_V etc.)
* @param	array	Attributes array
*
* @return	string
*/
function construct_link_css($item, $what, $array)
{
	$out = '';
	foreach($array AS $attribute => $value)
	{
		$value = trim($value);
		if (!empty($value))
		{
			$out .= "\t$attribute: $value;\r\n";
		}
	}

	if (!empty($out))
	{
		$item_bits = '';
		$items = explode(',', $item);
		foreach ($items AS $one_item)
		{
			$one_item = trim($one_item);
			if (!empty($one_item))
			{
				if ($what == 'LINK_N')
				{
					$item_bits .= ", $one_item a:link, {$one_item}_alink";
				}
				else if ($what == 'LINK_V')
				{
					$item_bits .= ", $one_item a:visited, {$one_item}_avisited";
				}
				else
				{
					$item_bits .= ", $one_item a:hover, $one_item a:active, {$one_item}_ahover";
				}
			}
		}
		$item_bits = str_replace('body a:', 'a:', substr($item_bits, 2));
		switch ($what)
		{
			case 'LINK_N':
				return "$item_bits\r\n{\r\n$out}\r\n";
			case 'LINK_V':
				return "$item_bits\r\n{\r\n$out}\r\n";
			default:
				return "$item_bits\r\n{\r\n$out}\r\n";
		}
	}
	else
	{
		return false;
	}
}

// #############################################################################
/**
* Prints out a style editor block, as seen in template.php?do=modify
*
* @param	integer	Style ID
* @param	array	Style info array
*/
function print_style($styleid, $style = array())
{
	global $vbulletin, $stylevar, $stylecache, $masterset;
	global $only, $_query_special_templates;
	global $SHOWTEMPLATE, $vbphrase;

	$titlesonly =& $vbulletin->GPC['titlesonly'];
	$expandset =& $vbulletin->GPC['expandset'];
	$group =& $vbulletin->GPC['group'];
	$searchstring =& $vbulletin->GPC['searchstring'];

	if ($styleid == -1)
	{
		$THISstyleid = 0;
		$style['title'] = $vbphrase['master_style'];
		$style['templatelist'] = serialize($masterset);
	}
	else
	{
		$THISstyleid = $styleid;
	}

	if ($expandset == 'all' OR $expandset == $styleid)
	{
		$showstyle = 1;
	}
	else
	{
		$showstyle = 0;
	}

	// show the header row
	$printstyleid = iif($styleid == -1, 'm', $styleid);
	echo "
	<!-- start header row for style '$style[styleid]' -->
	<table cellpadding=\"2\" cellspacing=\"0\" border=\"0\" width=\"100%\" class=\"stylerow\">
	<tr>
		<td><label for=\"userselect_$styleid\" title=\"$vbphrase[allow_user_selection]\">&nbsp; " . construct_depth_mark($style['depth'], '- - ', iif($vbulletin->debug AND $styleid != -1, '- - ')) . iif($styleid != -1, "<input type=\"checkbox\" name=\"userselect[$styleid]\" value=\"1\" tabindex=\"1\"" . iif($style['userselect'], ' checked="checked"') . " id=\"userselect_$styleid\" onclick=\"check_children($styleid, this.checked)\" />") . "</label><a href=\"../" . $vbulletin->options['forumhome'] . ".php?" . $vbulletin->session->vars['sessionurl'] . "styleid=$styleid\" target=\"_blank\" title=\"$vbphrase[view_your_forum_using_this_style]\">$style[title]</a></td>
		<td align=\"$stylevar[right]\" nowrap=\"nowrap\">
			" . iif($styleid != -1, "<input type=\"text\" class=\"bginput\" name=\"displayorder[$styleid]\" value=\"$style[displayorder]\" tabindex=\"1\" size=\"2\" title=\"$vbphrase[display_order]\" />") . "
			&nbsp;
			<select name=\"styleEdit_$printstyleid\" id=\"menu_$styleid\" onchange=\"Sdo(this.options[this.selectedIndex].value, $styleid);\" class=\"bginput\">
				<optgroup label=\"" . $vbphrase['template_options'] . "\">
					<option value=\"template_templates\">" . $vbphrase['edit_templates'] . "</option>
					<option value=\"template_addtemplate\">" . $vbphrase['add_new_template'] . "</option>
					" . iif($styleid != -1, "<option value=\"template_revertall\">" . $vbphrase['revert_all_templates'] . "</option>") . "
				</optgroup>
				<optgroup label=\"" . $vbphrase['edit_fonts_colors_etc'] . "\">
					<option value=\"css_all\" selected=\"selected\">$vbphrase[all_style_options]</option>
					<option value=\"css_templates\">$vbphrase[common_templates]</option>
					<option value=\"css_stylevars\">$vbphrase[stylevars]</option>
					<option value=\"css_maincss\">$vbphrase[main_css]</option>
					<option value=\"css_replacements\">$vbphrase[replacement_variables]</option>
					<option value=\"css_posteditor\">$vbphrase[toolbar_menu_options]</option>
				</optgroup>
				<optgroup label=\"" . $vbphrase['edit_style_options'] . "\">
					" . iif($styleid != -1, '<option value="template_editstyle">' . $vbphrase['edit_settings'] . '</option>') . "
					<option value=\"template_addstyle\">" . $vbphrase['add_child_style'] . "</option>
					<option value=\"template_download\">" . $vbphrase['download'] . "</option>
					" . iif($styleid != -1, '<option value="template_delete" class="col-c">' . $vbphrase['delete_style'] . '</option>') . "
				</optgroup>
			</select><input type=\"button\" class=\"button\" value=\"$vbphrase[go]\" onclick=\"Sdo(this.form.styleEdit_$printstyleid.options[this.form.styleEdit_$printstyleid.selectedIndex].value, $styleid);\" />
			&nbsp;
			<input type=\"button\" class=\"button\" tabindex=\"1\"
			value=\"" . iif($showstyle, COLLAPSECODE, EXPANDCODE) . "\" title=\"" . iif($showstyle, $vbphrase['collapse_templates'], $vbphrase['expand_templates']) . "\"
			onclick=\"window.location='template.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;group=$group" .iif($showstyle, '', "&amp;expandset=$styleid") . "';\" />
			&nbsp;
		</td>
	</tr>
	</table>
	<!-- end header row for style '.$style[styleid]' -->
	";

	if ($showstyle)
	{

		if (empty($searchstring))
		{
			$searchconds = '';
		}
		elseif ($titlesonly)
		{
			$searchconds = "AND t1.title LIKE('%" . $vbulletin->db->escape_string_like($searchstring) . "%')";
		}
		else
		{
			$searchconds = "AND ( t1.title LIKE('%" . $vbulletin->db->escape_string_like($searchstring) . "%') OR template_un LIKE('%" . $vbulletin->db->escape_string_like($searchstring) . "%') ) ";
		}

		// query templates
		$templateids = implode(',' , vb_unserialize($style['templatelist']));
		$templates = $vbulletin->db->query_read("
			SELECT templateid, t1.title, styleid, templatetype, dateline, username
			FROM " . TABLE_PREFIX . "template AS t1
			WHERE templatetype IN('template', 'replacement') $searchconds
			AND templateid IN($templateids)
			#AND title NOT IN('" . implode("', '", $_query_special_templates) . "')
			ORDER BY t1.title
			# expandset: '$expandset'
		");

		// just exit if no templates found
		$numtemplates = $vbulletin->db->num_rows($templates);
		if ($numtemplates == 0)
		{
			return;
		}

		echo "\n<!-- start template list for style '$style[styleid]' -->\n";

		if (FORMTYPE)
		{
			echo "<table cellpadding=\"0\" cellspacing=\"10\" border=\"0\" align=\"center\"><tr valign=\"top\">\n";
			echo "<td>\n<select name=\"tl$THISstyleid\" id=\"templatelist$THISstyleid\" class=\"darkbg\" size=\"" . TEMPLATE_EDITOR_ROWS . "\" style=\"font-weight:bold; width:350px\"\n\t";
			echo "onchange=\"Tprep(this.options[this.selectedIndex], $THISstyleid, 1);";
			echo "\"\n\t";
			echo "ondblclick=\"Tdo(Tprep(this.options[this.selectedIndex], $THISstyleid, 0), '');\">\n";
			echo "\t<option class=\"templategroup\" value=\"\">- - " . construct_phrase($vbphrase['x_templates'], $style['title']) . " - -</option>\n";
		}
		else
		{
			echo "<center><div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; margin: 8px; text-align: $stylevar[left];" . (is_browser('opera') ? " padding-$stylevar[left]:20px;" : '') . "\">\n<ul>\n";
			echo '<li class="templategroup"><b>' . $vbphrase['all_template_groups'] . '</b>' .
				construct_link_code("<b>" . EXPANDCODE . "</b>", "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;expandset=$expandset&amp;group=all", 0, $vbphrase['expand_all_template_groups']).
				construct_link_code("<b>" . COLLAPSECODE . "</b>", "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;expandset=$expandset", 0, $vbphrase['collapse_all_template_groups']).
				"<br />&nbsp;</li>\n";
		}

		while ($template = $vbulletin->db->fetch_array($templates))
		{
			if ($template['templatetype'] == 'replacement')
			{
				$replacements["$template[templateid]"] = $template;
			}
			else
			{
				// don't show the special templates used for building the text editor style / options
				if (in_array($template['title'], $_query_special_templates))
				{
					continue;
				}
				else
				{
					$m = substr(strtolower($template['title']), 0, iif($n = strpos($template['title'], '_'), $n, strlen($template['title'])));
					if ($template['styleid'] != -1 AND !isset($masterset["$template[title]"]) AND !isset($only["$m"]))
					{
						$customtemplates["$template[templateid]"] = $template;
					}
					else
					{
						$maintemplates["$template[templateid]"] = $template;
					}
				}
			}
		}

		// custom templates
		if (!empty($customtemplates))
		{

			if (FORMTYPE)
			{
				echo "<optgroup label=\"\">\n";
				echo "\t<option class=\"templategroup\" value=\"\">" . $vbphrase['custom_templates'] . "</option>\n";
			}
			else
			{
				echo "<li class=\"templategroup\"><b>" . $vbphrase['custom_templates'] . "</b>\n<ul class=\"ldi\">\n";
			}

			foreach($customtemplates AS $template)
			{
				echo $SHOWTEMPLATE($template, $styleid, 1); vbflush();
			}

			if (FORMTYPE)
			{
				echo "</optgroup><!--<optgroup label=\"\"></optgroup>-->";
			}
			else

			{
				echo "</li>\n</ul>\n";
			}
		}

		// main templates
		if (!empty($maintemplates))
		{

			$lastgroup = '';
			$echo_ul = 0;

			foreach($maintemplates AS $template)
			{
				$showtemplate = 1;
				if (!empty($lastgroup) AND strpos(strtolower(" $template[title]"), $lastgroup) == 1)
				{
					if ($group == 'all' OR $group == $lastgroup)
					{
						echo $SHOWTEMPLATE($template, $styleid, $echo_ul);
						vbflush();
					}
				}
				else
				{
					foreach($only AS $thisgroup => $display)
					{
						if ($lastgroup != $thisgroup AND $echo_ul == 1)
						{
							if (FORMTYPE)
							{
								// do nothing
								echo "</optgroup><!--<optgroup label=\"\"></optgroup>-->\n";
							}
							else

							{
								echo "\t</ul>\n</li>\n";
							}
							$echo_ul = 0;
						}
						if (strpos(strtolower(" $template[title]"), $thisgroup) == 1)
						{
							$lastgroup = $thisgroup;
							if ($group == 'all' OR $group == $lastgroup)
							{
								if (FORMTYPE)
								{
									echo "<optgroup label=\"\">\n";
									echo "\t<option class=\"templategroup\" value=\"[]\"" . iif($group == $thisgroup AND empty($vbulletin->GPC['templateid']), ' selected="selected"', '') . ">" . construct_phrase($vbphrase['x_templates'], $display) . " &laquo;</option>\n";
								}
								else
								{
									echo "<li class=\"templategroup\"><b>" . construct_phrase($vbphrase['x_templates'], $display) . "</b>" . construct_link_code("<b>" . COLLAPSECODE . "</b>", "template.php?" . $vbulletin->session->vars['sessionurl'] . "expandset=$expandset\" name=\"$thisgroup", 0, $vbphrase['collapse_template_group']) . "\n";
									echo "\t<ul class=\"ldi\">\n";
								}
								$echo_ul = 1;
							}
							else
							{
								if (FORMTYPE)
								{
									echo "\t<option class=\"templategroup\" value=\"[$thisgroup]\">" . construct_phrase($vbphrase['x_templates'], $display) . " &raquo;</option>\n";
								}
								else
								{
									echo "<li class=\"templategroup\"><b>" . construct_phrase($vbphrase['x_templates'], $display) . "</b>" . construct_link_code('<b>' . EXPANDCODE . '</b>', "template.php?" . $vbulletin->session->vars['sessionurl'] . "group=$thisgroup&amp;expandset=$expandset#$thisgroup", 0, $vbphrase['expand_template_group']) . "</li>\n";
								}
								$showtemplate = 0;
							}
							break;
						}
					} // end foreach($only)

					if ($showtemplate)
					{
						echo $SHOWTEMPLATE($template, $styleid, $echo_ul);
						vbflush();
					}
				} // end if template string same AS last
			} // end foreach ($maintemplates)
		}

		if (FORMTYPE)
		{

			echo "</select>\n";
			echo "</td>\n<td width=\"100%\" align=\"center\" valign=\"top\">";
			echo "
			<table cellpadding=\"4\" cellspacing=\"1\" border=\"0\" class=\"tborder\" width=\"300\">
			<tr align=\"center\">
				<td class=\"tcat\"><b>$vbphrase[controls]</b></td>
			</tr>
			<tr>
				<td class=\"alt2\" align=\"center\" style=\"font: 11px tahoma, verdana, arial, helvetica, sans-serif\">
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\"$vbphrase[customize]\" id=\"cust$THISstyleid\" onclick=\"Tdo(Tprep(this.form.tl{$THISstyleid}[this.form.tl$THISstyleid.selectedIndex], $THISstyleid, 0), '');\" />
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\"" . trim(construct_phrase($vbphrase['expand_x'], '')) . '/' . trim(construct_phrase($vbphrase['collapse_x'], '')) . "\" id=\"expa$THISstyleid\" onclick=\"Tdo(Tprep(this.form.tl{$THISstyleid}[this.form.tl$THISstyleid.selectedIndex], $THISstyleid, 0), '');\" /><br />
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\" $vbphrase[edit] \" id=\"edit$THISstyleid\" onclick=\"Tdo(Tprep(this.form.tl{$THISstyleid}[this.form.tl$THISstyleid.selectedIndex], $THISstyleid, 0), '');\" />
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\"$vbphrase[view_original]\" id=\"orig$THISstyleid\" onclick=\"Tdo(Tprep(this.form.tl{$THISstyleid}[this.form.tl$THISstyleid.selectedIndex], $THISstyleid, 0), 'vieworiginal');\" />
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\"$vbphrase[revert]\" id=\"kill$THISstyleid\" onclick=\"Tdo(Tprep(this.form.tl{$THISstyleid}[this.form.tl$THISstyleid.selectedIndex], $THISstyleid, 0), 'killtemplate');\" />
					<div class=\"darkbg\" style=\"margin: 4px; padding: 4px; border: 2px inset; text-align: $stylevar[left]\" id=\"helparea$THISstyleid\">
						" . construct_phrase($vbphrase['x_templates'], '<b>' . $style['title'] . '</b>') . "
					</div>
					<input type=\"button\" class=\"button\" value=\"" . EXPANDCODE . "\" title=\"" . $vbphrase['expand_all_template_groups'] . "\" onclick=\"Texpand('all', '$expandset');\" />
					<b>" . $vbphrase['all_template_groups'] . "</b>
					<input type=\"button\" class=\"button\" value=\"" . COLLAPSECODE . "\" title=\"" . $vbphrase['collapse_all_template_groups'] . "\" onclick=\"Texpand('', '$expandset');\" />
				</td>
			</tr>
			</table>
			<br />
			<table cellpadding=\"4\" cellspacing=\"1\" border=\"0\" class=\"tborder\" width=\"300\">
			<tr align=\"center\">
				<td class=\"tcat\"><b>$vbphrase[color_key]</b></td>
			</tr>
			<tr>
				<td class=\"alt2\">
				<div class=\"darkbg\" style=\"margin: 4px; padding: 4px; border: 2px inset; text-align: $stylevar[left]\">
				<span class=\"col-g\">" . $vbphrase['template_is_unchanged_from_the_default_style'] . "</span><br />
				<span class=\"col-i\">" . $vbphrase['template_is_inherited_from_a_parent_style'] . "</span><br />
				<span class=\"col-c\">" . $vbphrase['template_is_customized_in_this_style'] . "</span>
				</div>
				</td>
			</tr>
			</table>
			";

			/*
			// might come back to this at some point...
			if (!empty($replacements))
			{
				$numreplacements = sizeof($replacements);
				echo "<br />\n<b>Replacement Variables:</b><br />\n<select name=\"rep$THISstyleid\" size=\"" . iif($numreplacements > ADMIN_MAXREPLACEMENTS, ADMIN_MAXREPLACEMENTS, $numreplacements) . "\" class=\"bginput\" style=\"width:350px\">\n";
				foreach($replacements AS $replacement)
				{
					echo $SHOWTEMPLATE($replacement, $styleid, 0, 1);
					vbflush();
				}
				echo "</select>\n";
			}
			*/

			echo "\n</td>\n</tr>\n</table>\n
			<script type=\"text/javascript\">
			<!--
			if (document.forms.tform.tl$THISstyleid.selectedIndex > 0)
			{
				Tprep(document.forms.tform.tl$THISstyleid.options[document.forms.tform.tl$THISstyleid.selectedIndex], $THISstyleid, 1);
			}
			//-->
			</script>";

		}
		else
		{
			echo "</ul>\n</div></center>\n";
		}

		echo "<!-- end template list for style '$style[styleid]' -->\n\n";

	} // end if($showstyle)

} // end function

// #############################################################################
/**
* Constructs a single template item for the style editor form
*
* @param	array	Template info array
* @param	integer	Style ID of style being shown
* @param	boolean	No longer used
* @param	boolean	HTMLise template titles?
*
* @return	string	Template <option>
*/
function construct_template_option($template, $styleid, $doindent = false, $htmlise = true)
{
	global $vbulletin;

	static $isdevsite;

	if ($vbulletin->GPC['templateid'] == $template['templateid'])
	{
		$selected = ' selected="selected"';
	}
	else
	{
		$selected = '';
	}

	if ($htmlise)
	{
		$template['title'] = htmlspecialchars_uni($template['title']);
	}

	if ($styleid == -1)
	{
		return "\t<option value=\"$template[templateid]\" i=\"$template[username];$template[dateline]\"$selected>$indent$template[title]</option>\n";
	}
	else
	{
		switch ($template['styleid'])
		{
			// template is inherited from the master set
			case -1:
			{
				return "\t<option class=\"col-g\" value=\"~\" i=\"$template[username];$template[dateline]\"$selected>$indent$template[title]</option>\n";
			}

			// template is customized for this specific style
			case $styleid:
			{
				return "\t<option class=\"col-c\" value=\"$template[templateid]\" i=\"$template[username];$template[dateline]\"$selected>$indent$template[title]</option>\n";
			}

			// template is customized in a parent style - (inherited)
			default:
			{
				return "\t<option class=\"col-i\" value=\"[$template[templateid]]\" tsid=\"$template[styleid]\" i=\"$template[username];$template[dateline]\" tsid=\"$template[styleid]\"$selected>$indent$template[title]</option>\n";
			}
		}
	}

}

// #############################################################################
/**
* Equivalent to construct_template_option(), but creates an <a> instead of an <option>
*
* @param	array	Template info array
* @param	integer	Style ID of style being shown
* @param	boolean	Indent HTML code?
* @param	boolean	Not used any more
*
* @return	string	Template <a> link
*/
function construct_template_link($template, $styleid, $doindent = false, $htmlise = false)
{
	global $LINKEXTRA, $info, $templateid, $vbulletin, $vbphrase;

	if ($doindent)
	{
		$indent = "\t";
	}
	else
	{
		$indent = '';
	}

	if ($styleid == -1)
	{ // (debug option)
		return "$indent<li class=\"col-g\">$template[title]" .
			construct_link_code($vbphrase['edit'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;templateid=$template[templateid]&amp;dostyleid=$template[styleid]$LINKEXTRA").
			construct_link_code($vbphrase['delete'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=delete&amp;templateid=$template[templateid]&amp;dostyleid=$template[styleid]$LINKEXTRA").
		"</li>\n";
	}
	else
	{
		switch ($template['styleid'])
		{
			case -1: // template is inherited from the master set
				return "$indent<li class=\"col-g\">$template[title]" .
					construct_link_code($vbphrase['customize'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=add&amp;dostyleid=$styleid&amp;title=" . urlencode($template['title']) . "$LINKEXTRA") . "</li>\n";
			case $styleid: // template is customized for this specific style
				return "$indent<li class=\"col-c\">$template[title]" .
					construct_link_code($vbphrase['edit'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;templateid=$template[templateid]&amp;dostyleid=$template[styleid]$LINKEXTRA").
					construct_link_code($vbphrase['revert'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=delete&amp;templateid=$template[templateid]&amp;dostyleid=$template[styleid]$LINKEXTRA").
					construct_link_code($vbphrase['view_original'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=view&amp;title=" . urlencode($template['title']), 1).
				"</li>\n";
			default: // template is customized in a parent style - (inherited)
				return "$indent<li class=\"col-i\">$template[title]" .
					construct_link_code($vbphrase['customize_further'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=add&amp;dostyleid=$styleid&amp;templateid=$template[templateid]$LINKEXTRA").
					construct_link_code($vbphrase['view_original'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=view&amp;title=" . urlencode($template['title']), 1).
				"</li>\n";
		}
	}

}

// #############################################################################
/**
* Processes a template into PHP code for eval()
*
* @param	string	Unprocessed template
* @param	boolean	Halt on error?
*
* @return	string
*/
function process_template_conditionals($template, $haltonerror = true)
{
	global $vbphrase;

	$if_lookfor = '<if condition=';
	$if_location = -1;
	$if_end_lookfor = '</if>';
	$if_end_location = -1;

	$else_lookfor = '<else />';
	$else_location = -1;

	$condition_value = '';
	$true_value = '';
	$false_value = '';

	$template_cond = $template;

	static $safe_functions;
	if (!is_array($safe_functions))
	{
		$safe_functions = array(
			// logical stuff
			0 => 'and',              // logical and
			1 => 'or',               // logical or
			2 => 'xor',              // logical xor

			// built-in variable checking functions
			'in_array',              // used for checking
			'is_array',              // used for checking
			'is_numeric',            // used for checking
			'isset',                 // used for checking
			'empty',                 // used for checking
			'defined',               // used for checking
			'array',                 // used for checking

			// vBulletin-defined functions
			'can_moderate',          // obvious one
			'can_moderate_calendar', // another obvious one
			'exec_switch_bg',        // harmless function that we use sometimes
			'is_browser',            // function to detect browser and versions
			'is_member_of',          // function to check if $user is member of $usergroupid
		);

		($hook = vBulletinHook::fetch_hook('template_safe_functions')) ? eval($hook) : false;
	}

	// #############################################################################

	while (1)
	{

		$condition_end = 0;
		$strlen = strlen($template_cond);

		$if_location = strpos($template_cond, $if_lookfor, $if_end_location + 1); // look for opening <if>
		if ($if_location === false)
		{ // conditional started not found
			break;
		}

		$condition_start = $if_location + strlen($if_lookfor) + 2; // the beginning of the conditional

		$delimiter = $template_cond[$condition_start - 1];
		if ($delimiter != '"' AND $delimiter != '\'')
		{ // ensure the conditional is surrounded by a valid character
			$if_end_location = $if_location + 1;
			continue;
		}

		$if_end_location = strpos($template_cond, $if_end_lookfor, $condition_start + 3); // location of conditional terminator
		if ($if_end_location === false)
		{ // move this code above the rest, if no end condition is found then the code below would get stuck
			return str_replace("\\'", '\'', $template_cond); // no </if> found -- return the original template
		}

		for ($i = $condition_start; $i < $strlen; $i++)
		{ // find the end of the conditional
			if ($template_cond["$i"] == $delimiter AND $template_cond[$i - 2] != '\\' AND $template_cond[$i + 1] == '>')
			{ // this char is delimiter and not preceded by backslash
				$condition_end = $i - 1;
				break;
			}
		}
		if (!$condition_end)
		{ // couldn't find an end to the condition, so don't even parse the template anymore
			return str_replace("\\'", '\'', $template_cond);
		}

		$condition_value = substr($template_cond, $condition_start, $condition_end-$condition_start);
		if (empty($condition_value))
		{
			// something went wrong
			$if_end_location = $if_location + 1;
			continue;
		}
		else if (strpos($condition_value, '`') !== false)
		{
			print_stop_message('expression_contains_backticks_x_please_rewrite_without', htmlspecialchars_uni('<if condition="' . stripslashes($condition_value) . '">'));
		}
		else
		{
			if (preg_match_all('#([a-z0-9_\x7f-\xff\\\\{}$>-\\]]+)(\s|/\*.*\*/|(\#|//)[^\r\n]*(\r|\n))*\(#si', $condition_value, $matches))
			{
				$functions = array();

				foreach($matches[1] AS $key => $match)
				{
					if (!in_array(strtolower($match), $safe_functions))
					{
						$funcpos = strpos($condition_value, $matches[0]["$key"]);
						$functions[] = array(
							'func' => stripslashes($match),
							'usage' => stripslashes(substr($condition_value, $funcpos, (strpos($condition_value, ')', $funcpos) - $funcpos + 1))),
						);
					}
				}

				if (!empty($functions) AND $haltonerror)
				{
					unset($safe_functions[0], $safe_functions[1], $safe_functions[2]);

					$errormsg = "
					$vbphrase[template_condition_contains_functions]:<br /><br />
					<code>" . htmlspecialchars_uni('<if condition="' . stripslashes($condition_value) . '">') . '</code><br /><br />
					<table cellpadding="4" cellspacing="1" width="100%">
					<tr>
						<td class="thead">' . $vbphrase['function_name'] . '</td>
						<td class="thead">' . $vbphrase['usage_in_expression'] . '</td>
					</tr>';

					foreach($functions AS $error)
					{
						$errormsg .= "<tr><td class=\"alt2\"><code>" . htmlspecialchars_uni($error['func']) . "</code></td><td class=\"alt2\"><code>" . htmlspecialchars_uni($error['usage']) . "</code></td></tr>\n";
					}

					$errormsg .= "
					</table>
					<br />$vbphrase[with_a_few_exceptions_function_calls_are_not_permitted]<br />
					<code>". implode('() ', $safe_functions) . '()</code>';

					echo "<p>&nbsp;</p><p>&nbsp;</p>";
					print_form_header('', '', 0, 1, '', '65%');
					print_table_header($vbphrase['vbulletin_message']);
					print_description_row("<blockquote><br />$errormsg<br /><br /></blockquote>");
					print_table_footer(2, construct_button_code($vbphrase['go_back'], 'javascript:history.back(1)'));
					print_cp_footer();
				}
			}
		}

		if ($template_cond[$condition_end + 2] != '>')
		{ // the > doesn't come right after the condition must be malformed
			$if_end_location = $if_location + 1;
			continue;
		}

		// look for recursive case in the if block -- need to do this so the correct </if> is looked at
		$recursive_if_loc = $if_location;
		while (1)
		{
			$recursive_if_loc = strpos($template_cond, $if_lookfor, $recursive_if_loc + 1); // find an if case
			if ($recursive_if_loc === false OR $recursive_if_loc >= $if_end_location)
			{ //not found or out of bounds
				break;
			}

			// the bump first level's recursion back one </if> at a time
			$recursive_if_end_loc = $if_end_location;
			$if_end_location = strpos($template_cond, $if_end_lookfor, $recursive_if_end_loc + 1);
			if ($if_end_location === false)
			{
				return str_replace("\\'", "'", $template_cond); // no </if> found -- return the original template
			}
		}

		$else_location = strpos($template_cond, $else_lookfor, $condition_end + 3); // location of false portion

		// this is needed to correctly identify the <else /> tag associated with the outermost level
		while (1)
		{
			if ($else_location === false OR $else_location >= $if_end_location)
			{ // else isn't found/in a valid area
				$else_location = -1;
				break;
			}

			$temp = substr($template_cond, $condition_end + 3, $else_location - $condition_end + 3);
			$opened_if = substr_count($temp, $if_lookfor); // <if> tags opened between the outermost <if> and the <else />
			$closed_if = substr_count($temp, $if_end_lookfor); // <if> tags closed under same conditions
			if ($opened_if == $closed_if)
			{ // if this is true, we're back to the outermost level
				// and this is the correct else
				break;
			}
			else
			{
				// keep looking for correct else case
				$else_location = strpos($template_cond, $else_lookfor, $else_location + 1);
			}
		}

		if ($else_location == -1)
		{ // no else clause
			$read_length = $if_end_location - strlen($if_end_lookfor) + 1 - $condition_end + 1; // number of chars to read
			$true_value = substr($template_cond, $condition_end + 3, $read_length); // the true portion
			$false_value = '';
		}
		else
		{
			$read_length = $else_location - $condition_end - 3; // number of chars to read
			$true_value = substr($template_cond, $condition_end + 3, $read_length); // the true portion

			$read_length = $if_end_location - strlen($if_end_lookfor) - $else_location - 3; // number of chars to read
			$false_value = substr($template_cond, $else_location + strlen($else_lookfor), $read_length); // the false portion
		}

		if (strpos($true_value, $if_lookfor) !== false)
		{
			$true_value = process_template_conditionals($true_value, $haltonerror);
		}
		if (strpos($false_value, $if_lookfor) !== false)
		{
			$false_value = process_template_conditionals($false_value, $haltonerror);
		}

		// clean up the extra slashes
		$str_find = array('\\"', '\\\\');
		$str_replace = array('"', '\\');
		if ($delimiter == "'")
		{
			$str_find[] = "\\'";
			$str_replace[] = "'";
		}

		$str_find[] = '\\$delimiter';
		$str_replace[] =  $delimiter;

		$condition_value = str_replace($str_find, $str_replace, $condition_value);

		if (!function_exists('replace_template_variables'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}

		$condition_value = replace_template_variables($condition_value, true);

		$conditional = "\".(($condition_value) ? (\"$true_value\") : (\"$false_value\")).\"";
		$template_cond = substr_replace($template_cond, $conditional, $if_location, $if_end_location + strlen($if_end_lookfor) - $if_location);

		$if_end_location = $if_location + strlen($conditional) - 1; // adjust searching position for the replacement above
	}

	return str_replace("\\'", "'", $template_cond);
}

// #############################################################################
/**
* Processes <phrase> tags into construct_phrase() PHP code for eval
*
* @param	string	Name of tag
* @param	string	Text to be processed
* @param	string	Name of processor function
* @param	string	Extra arguments for processor function
*
* @return	string
*/
function process_template_phrases($tagname, $text, $functionhandle, $extraargs = '')
{
	$tagname = strtolower($tagname);
	$open_tag = "<$tagname";
	$open_tag_len = strlen($open_tag);
	$close_tag = "</$tagname>";
	$close_tag_len = strlen($close_tag);

	$beginsearchpos = 0;
	do {
		$textlower = strtolower($text);
		$tagbegin = @strpos($textlower, $open_tag, $beginsearchpos);
		if ($tagbegin === false)
		{
			break;
		}

		$strlen = strlen($text);

		// we've found the beginning of the tag, now extract the options
		$inquote = '';
		$found = false;
		$tagnameend = false;
		for ($optionend = $tagbegin; $optionend <= $strlen; $optionend++)
		{
			$char = $text{$optionend};
			if (($char == '"' OR $char == "'") AND $inquote == '')
			{
				$inquote = $char; // wasn't in a quote, but now we are
			}
			else if (($char == '"' OR $char == "'") AND $inquote == $char)
			{
				$inquote = ''; // left the type of quote we were in
			}
			else if ($char == '>' AND !$inquote)
			{
				$found = true;
				break; // this is what we want
			}
			else if (($char == '=' OR $char == ' ') AND !$tagnameend)
			{
				$tagnameend = $optionend;
			}
		}
		if (!$found)
		{
			break;
		}
		if (!$tagnameend)
		{
			$tagnameend = $optionend;
		}
		$offset = $optionend - ($tagbegin + $open_tag_len);
		$tagoptions = substr($text, $tagbegin + $open_tag_len, $offset);
		$acttagname = substr($textlower, $tagbegin + 1, $tagnameend - $tagbegin - 1);
		if ($acttagname != $tagname)
		{
			$beginsearchpos = $optionend;
			continue;
		}

		// now find the "end"
		$tagend = strpos($textlower, $close_tag, $optionend);
		if ($tagend === false)
		{
			break;
		}

		// if there are nested tags, this </$tagname> won't match our open tag, so we need to bump it back
		$nestedopenpos = strpos($textlower, $open_tag, $optionend);
		while ($nestedopenpos !== false AND $tagend !== false)
		{
			if ($nestedopenpos > $tagend)
			{ // the tag it found isn't actually nested -- it's past the </$tagname>
				break;
			}
			$tagend = strpos($textlower, $close_tag, $tagend + $close_tag_len);
			$nestedopenpos = strpos($textlower, $open_tag, $nestedopenpos + $open_tag_len);
		}
		if ($tagend === false)
		{
			$beginsearchpos = $optionend;
			continue;
		}

		$localbegin = $optionend + 1;
		$localtext = $functionhandle($tagoptions, substr($text, $localbegin, $tagend - $localbegin), $tagname, $extraargs);

		$text = substr_replace($text, $localtext, $tagbegin, $tagend + $close_tag_len - $tagbegin);

		// this adjusts for $localtext having more/less characters than the amount of text it's replacing
		$beginsearchpos = $tagbegin + strlen($localtext);
	} while ($tagbegin !== false);

	return $text;
}

// #############################################################################
/**
* Processes a <phrase> tag
*
* @param	string	Options
* @param	string	Text of phrase
*
* @return	string
*/
function parse_phrase_tag($options, $phrasetext)
{
	$options = stripslashes($options);

	$i = 1;
	$param = array();
	do
	{
		$attribute = parse_tag_attribute("$i=", $options);
		if ($attribute !== false)
		{
			$param[] = $attribute;
		}
		$i++;
	} while ($attribute !== false);

	if (sizeof($param) > 0)
	{
		$return = '" . construct_phrase("' . $phrasetext . '"';
		foreach ($param AS $argument)
		{
			$argument = str_replace(array('\\', '"'), array('\\\\', '\"'), $argument);
			$return .= ', "' . $argument . '"';
		}
		$return .= ') . "';
	}
	else
	{
		$return = $phrasetext;
	}

	return $return;
}

// #############################################################################
/**
* Parses an attribute within a <phrase>
*
* @param	string	Option
* @param	string	Text
*
* @return	string
*/
function parse_tag_attribute($option, $text)
{
	if (($position = strpos($text, $option)) !== false)
	{
		$delimiter = $position + strlen($option);
		if ($text{$delimiter} == '"')
		{ // read to another "
			$delimchar = '"';
		}
		else if ($text{$delimiter} == '\'')
		{
			$delimchar = '\'';
		}
		else
		{ // read to a space
			$delimchar = ' ';
		}
		$delimloc = strpos($text, $delimchar, $delimiter + 1);
		if ($delimloc === false)
		{
			$delimloc = strlen($text);
		}
		else if ($delimchar == '"' OR $delimchar == '\'')
		{
			// don't include the delimiters
			$delimiter++;
		}
		return trim(substr($text, $delimiter, $delimloc - $delimiter));
	}
	else
	{
		return false;
	}
}

// #############################################################################
/**
* Processes a raw template for conditionals, phrases etc into PHP code for eval()
*
* @param	string	Template
*
* @return	string
*/
function compile_template($template, $haltonerror = true)
{
	$orig_template = $template;

	$template = preg_replace('#[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]#', '', $template);
	$template = addslashes($template);
	$template = process_template_conditionals($template, $haltonerror);

	$template = process_template_phrases('phrase', $template, 'parse_phrase_tag');

	if (!function_exists('replace_template_variables'))
	{
		require_once(DIR . '/includes/functions_misc.php');
	}

	$template = replace_template_variables($template, false);

	($hook = vBulletinHook::fetch_hook('template_compile')) ? eval($hook) : false;

	$template = str_replace('\\\\$', '\\$', $template);

	if (function_exists('token_get_all'))
	{
		$tokens = @token_get_all('<?php $var = "' . $template . '"; ?>');

		foreach ($tokens AS $token)
		{
			if (is_array($token))
			{
				switch ($token[0])
				{
					case T_INCLUDE:
					case T_INCLUDE_ONCE:
					case T_REQUIRE:
					case T_REQUIRE_ONCE:
					{
						global $vbphrase;
						echo "<p>&nbsp;</p><p>&nbsp;</p>";
						print_form_header('', '', 0, 1, '', '65%');
						print_table_header($vbphrase['vbulletin_message']);
						print_description_row($vbphrase['file_inclusion_not_permitted']);
						print_table_footer(2, construct_button_code($vbphrase['go_back'], 'javascript:history.back(1)'));
						print_cp_footer();
						exit;
					}
				}
			}
		}
	}

	if (function_exists('verify_demo_template'))
	{
		verify_demo_template($template);
	}

	return $template;
}

// #############################################################################
/**
* Builds the $stylecache array used in style editing
*
* This is a recursive function - call it as cache_styles() with no arguments
*
* @param	boolean	Not used
* @param	integer	Style ID to start with
* @param	integer	Current depth
*/
function cache_styles($getids = false, $styleid = -1, $depth = 0)
{
	global $vbulletin, $stylecache, $count;
	static $i, $cache;

	// check to see if we have already got the results from the database
	if (empty($cache))
	{
		$styles = $vbulletin->db->query_read("SELECT * FROM " . TABLE_PREFIX . "style ORDER BY displayorder");
		define('STYLECOUNT', $vbulletin->db->num_rows($styles));
		while ($style = $vbulletin->db->fetch_array($styles))
		{
			if (trim($style['parentlist']) == '')
			{
				$parentlist = fetch_template_parentlist($style['styleid']);

				$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "style
					SET parentlist = '" . $vbulletin->db->escape_string($parentlist) . "'
					WHERE styleid = " . intval($style['styleid'])  . "
				");

				$style['parentlist'] = $parentlist;
			}

			if (trim($style['templatelist']) == '')
			{
				$style['templatelist'] = build_template_id_cache($style['styleid'], true, $style['parentlist']);

				$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "style
					SET templatelist = '" . $vbulletin->db->escape_string($style['templatelist']) . "'
					WHERE styleid = " . intval($style['styleid'])
				);
			}

			$cache["$style[parentid]"]["$style[displayorder]"]["$style[styleid]"] = $style;
		}
	}

	// database has already been queried
	if (is_array($cache["$styleid"]))
	{
		foreach ($cache["$styleid"] AS $holder)
		{
			foreach ($holder AS $style)
			{
				$stylecache["$style[styleid]"] = $style;
				$stylecache["$style[styleid]"]['depth'] = $depth;
				cache_styles($getids, $style['styleid'], $depth + 1);

			} // end foreach ($holder AS $style)
		} // end foreach ($tcache["$styleid"] AS $holder)
	} // end if (found $tcache["$styleid"])

}

// #############################################################################
/**
* Builds the stylecache and saves it into the datastore
*
* @return	array	$stylecache
*/
function build_style_datastore()
{
	global $stylecache, $vbulletin;

	if (!is_array($stylecache))
	{
		cache_styles();
		// this should not ever be needed unless the user has edited the database
		if (STYLECOUNT != sizeof($stylecache))
		{
			trigger_error('Invalid row in the style table', E_USER_ERROR);
		}
	}

	$localstylecache = array();

	foreach ($stylecache AS $styleid => $style)
	{
		$localstyle = array();
		$localstyle['styleid'] = $style['styleid'];
		$localstyle['title'] = $style['title'];
		$localstyle['parentid'] = $style['parentid'];
		$localstyle['displayorder'] = $style['displayorder'];
		$localstyle['userselect'] = $style['userselect'];

		($hook = vBulletinHook::fetch_hook('admin_style_datastore')) ? eval($hook) : false;

		$datastorecache["$localstyle[parentid]"]["$localstyle[displayorder]"][] = $localstyle;
	}

	build_datastore('stylecache', serialize($datastorecache), 1);

	return $datastorecache;
}

// #############################################################################
/**
* Prints a row containing a <select> showing the available styles
*
* @param	string	Name for <select>
* @param	integer	Selected style ID
* @param	string	Name of top item in <select>
* @param	string	Title of row
* @param	boolean	Display top item?
*/
function print_style_chooser_row($name = 'parentid', $selectedid = -1, $topname = NULL, $title = NULL, $displaytop = true)
{
	global $stylecache, $vbphrase;

	if ($topname === NULL)
	{
	   $topname = $vbphrase['no_parent_style'];
	}
	if ($title === NULL)
	{
	   $title = $vbphrase['parent_style'];
	}

	cache_styles();

	$styles = array();

	if ($displaytop)
	{
		$styles['-1'] = $topname;
	}

	foreach($stylecache AS $style)
	{
		$styles["$style[styleid]"] = construct_depth_mark($style['depth'], '--', iif($displaytop, '--')) . " $style[title]";
	}

	print_select_row($title, $name, $styles, $selectedid);
}

// #############################################################################
/**
* If a template item is customized, returns HTML to allow revertion
*
* @param	integer	Style ID of template item
* @param	string	Template type (replacement / stylevar etc.)
* @param	string	Name of template record
*
* @return	array	array('info' => x, 'revertcode' => 'y')
*/
function construct_revert_code($itemstyleid, $templatetype, $varname)
{
	global $vbphrase, $vbulletin;

	if ($templatetype == 'replacement')
	{
		$revertword = 'delete';
	}
	else
	{
		$revertword = 'revert';
	}

	switch ($itemstyleid)
	{
		case -1:
			return array('info' => '', 'revertcode' => '&nbsp;');
		case $vbulletin->GPC['dostyleid']:
			return array('info' => "($vbphrase[customized_in_this_style])", 'revertcode' => "<label for=\"del_{$templatetype}_{$varname}\">" . $vbphrase["$revertword"] . "<input type=\"checkbox\" name=\"delete[$templatetype][$varname]\" id=\"del_{$templatetype}_{$varname}\" value=\"1\" tabindex=\"1\" title=\"" . $vbphrase["$revertword"] . "\" /></label>");
		default:
			return array('info' => '(' . construct_phrase($vbphrase['customized_in_a_parent_style_x'], $itemstyleid) . ')', 'revertcode' => '&nbsp;');
	}
}

// #############################################################################
/**
* Makes an entry for a common template on the style editor page
*
* @param	string	Template title
* @param	string	Template variable name
*
* @return	string
*/
function construct_edit_menu_code($title, $varname)
{
	global $template_cache, $vbulletin;

	$template = $template_cache['template']["$varname"];

	$color = fetch_inherited_color($template['styleid'], $vbulletin->GPC['dostyleid']);
	$revertcode = construct_revert_code($template['styleid'], 'template', $varname);

	$out = "<fieldset title=\"$title\"><legend>$title</legend><div class=\"smallfont\" style=\"padding: 2px; text-align: center\"><textarea class=\"$color\" name=\"commontemplate[$varname]\" tabindex=\"1\" cols=\"20\" rows=\"10\" style=\"width: 90%\" wrap=\"off\">" . htmlspecialchars_uni($template['template_un']) . "</textarea>";
	if ($revertcode['info'])
	{
		$out .= "<div>$revertcode[info]<br />$revertcode[revertcode]</div>";
	}
	$out .= '</div></fieldset>';
	return $out;
}

// #############################################################################
/**
* Prints a row containing a textarea for editing one of the 'common templates'
*
* @param	string	Template variable name
*/
function print_common_template_row($varname)
{
	global $template_cache, $vbphrase, $vbulletin;

	$template = $template_cache['template']["$varname"];
	$description = $vbphrase["{$varname}_desc"];

	$color = fetch_inherited_color($template['styleid'], $vbulletin->GPC['dostyleid']);
	$revertcode = construct_revert_code($template['styleid'], 'template', $varname);

	print_textarea_row(
		"<b>$varname</b> <dfn>$description</dfn><span class=\"smallfont\"><br /><br />$revertcode[info]<br /><br />$revertcode[revertcode]</span>",
		"commontemplate[$varname]",
		$template['template_un'],
		8, 70, 1, 0, 'ltr',
		"$color\" style=\"font: 9pt courier new"
	);
}

// #############################################################################
/**
* Prints a row containing a textarea for editing a replacement variable
*
* @param	string	Find text
* @param	string	Replace text
* @param	integer	Number of rows for textarea
* @param	integer	Number of columns for textarea
*/
function print_replacement_row($find, $replace, $rows = 2, $cols = 50)
{
	global $replacement_info, $vbulletin;
	static $rcount;

	$rcount++;

	$color = fetch_inherited_color($replacement_info["$find"], $vbulletin->GPC['dostyleid']);
	$revertcode = construct_revert_code($replacement_info["$find"], 'replacement', $rcount);

	construct_hidden_code("replacement[$rcount][find]", $find);
	print_cells_row(array(
		'<pre>' . htmlspecialchars_uni($find) . '</pre>',
		"\n\t<span class=\"smallfont\"><textarea name=\"replacement[$rcount][replace]\" class=\"$color\" rows=\"$rows\" cols=\"$cols\" tabindex=\"1\">" . htmlspecialchars_uni($replace) . "</textarea><br />$revertcode[info]</span>\n\t",
		"<span class=\"smallfont\">$revertcode[revertcode]</span>"
	));

}

// #############################################################################
/**
* Prints a row containing an input for editing a stylevar
*
* @param	string	Stylevar title
* @param	string	Stylevar varname
* @param	integer	Size of text box
*/
function print_stylevar_row($title, $varname, $size = 30, $validation_regex = '', $failsafe_value = '')
{
	global $stylevars, $stylevar_info, $vbulletin;

	$color = fetch_inherited_color($stylevar_info["$varname"], $vbulletin->GPC['dostyleid']);
	$revertcode = construct_revert_code($stylevar_info["$varname"], 'stylevar', $varname);

	if ($help = construct_table_help_button("stylevar[$varname]"))
	{
		$helplink = "&nbsp;$help";
	}

	if ($validation_regex != '')
	{
		construct_hidden_code("stylevar[_validation][$varname]", htmlspecialchars_uni($validation_regex));
		construct_hidden_code("stylevar[_failsafe][$varname]", htmlspecialchars_uni($failsafe_value));
	}

	print_cells_row(array(
		"<span title=\"\$stylevar[$varname]\">$title</span>",
		"<span class=\"smallfont\"><input type=\"text\" class=\"$color\" title=\"\$stylevar[$varname]\" name=\"stylevar[$varname]\" tabindex=\"1\" value=\"" . htmlspecialchars_uni($stylevars["$varname"]) . "\" size=\"$size\" dir=\"ltr\" /><br />$revertcode[info]</span>",
		"<span class=\"smallfont\">$revertcode[revertcode]</span>$helplink"
	));
}

// #############################################################################
/**
* Returns a <fieldset> containing inputs for editing a forumjump entry
*
* @param	string	Title of item
* @param	string	CSS class name
* @param	integer	Size of text box
*
* @return	string
*/
function construct_forumjump_css_row($title, $classname, $size = 20)
{
	global $css, $css_info, $vbphrase, $color, $stylevar, $vbulletin;

	$color = fetch_inherited_color($css_info["$classname"], $vbulletin->GPC['dostyleid']);
	$revertcode = construct_revert_code($css_info["$classname"], 'css', $classname);

	$output = "
		<fieldset title=\"$title\">
			<legend>" . iif($revertcode['revertcode'] != '&nbsp;', " <span class=\"normal\" style=\"float:$stylevar[right]\">$revertcode[revertcode]</span>") . "$title $revertcode[info]</legend>
			<table cellpadding=\"0\" cellspacing=\"2\" border=\"0\" width=\"100%\">
			<colgroup span=\"2\">
				<col width=\"50%\"></col>
				<col width=\"50%\" align=\"right\"></col>
			</colgroup>
			" . construct_css_input_row($vbphrase['background'], "['$classname']['background']", $color, true, 20) . "
			" . construct_css_input_row($vbphrase['font_color'], "['$classname']['color']", $color, true, 20) . "
			</table>
		</fieldset>
	";

	return $output;
}

// #############################################################################
/**
* Returns a row with an input box for use in the CSS editor
*
* @param	string	Title of item
* @param	array	Item info array
* @param	string	CSS class to display with
* @param	boolean	True if the value is a colour (will show colour picker widget)
* @param	integer	Size of input box
*
* @return	string
*/
function construct_css_input_row($title, $item, $class = 'bginput', $iscolor = false, $size = 30)
{
	global $css, $readonly, $color, $numcolors;

	eval('$value = $css' . $item . ';');
	$name = "css" . str_replace("['", "[", str_replace("']", "]", $item));

	if ($iscolor)
	{
		return construct_color_row($title, $name, $value, $class, $size - 8);
	}

	$value = htmlspecialchars_uni($value);
	$readonly = iif($readonly, ' readonly="readonly"', '');

	return "
		<tr>
			<td>$title</td>
			<td><input type=\"text\" class=\"$class\" name=\"$name\" value=\"$value\" title=\"\$$name\" tabindex=\"1\" size=\"$size\" dir=\"ltr\" /></td>
		</tr>
	";
}

// #############################################################################
/**
* Returns a cell containing inputs for editing the LINK section of a CSS item
*
* @param	string	Item title
* @param	array	Item info array
* @param	string	Link type (N, V etc)
* @param	string	CSS class to display with
*
* @return	string
*/
function construct_link_css_input_row($title, $item, $subitem, $color = 'bginput')
{
	global $vbphrase;

	$title = construct_phrase($vbphrase['x_links_css'], $title);

	return '
		<td>
		<fieldset title="' . $title . '">
		<legend>' . $title . '</legend>
		<table cellpadding="0" cellspacing="2" border="0" width="100%">
		<col width="100%"></col>
		' . construct_css_input_row($vbphrase['background'], "['$item']['LINK_$subitem']['background']", $color, true, 16) . '
		' . construct_css_input_row($vbphrase['font_color'], "['$item']['LINK_$subitem']['color']", $color, true, 16) . '
		' . construct_css_input_row($vbphrase['text_decoration'], "['$item']['LINK_$subitem']['text-decoration']", $color, false, 16) . '
		</table>
		</fieldset>
		</td>
	';
}

// #############################################################################
/**
* Returns styles for post editor interface from template
*
* @param	string	Template contents
*
* @return	array
*/
function fetch_posteditor_styles($template)
{
	$item = array();

	preg_match_all('#([a-z0-9-]+):\s*([^\s].*);#siU', $template, $regs);

	foreach ($regs[1] AS $key => $cssname)
	{
		$item[strtolower($cssname)] = trim($regs[2]["$key"]);
	}

	return $item;
}

// #############################################################################
/**
* Returns a <fieldset> for editing post editor styles
*
* @param	string	Item title
* @param	string	Item varname
*
* @return	string
*/
function construct_posteditor_style_code($title, $varname)
{
	global $template_cache, $vbphrase, $stylevar, $vbulletin;

	$template = $template_cache['template']["$varname"];

	$color = fetch_inherited_color($template['styleid'], $vbulletin->GPC['dostyleid']);
	$revertcode = construct_revert_code($template['styleid'], 'template', $varname);

	$item = fetch_posteditor_styles($template['template_un']);

	$out = "
	<fieldset title=\"$title\">
		<legend>$title</legend>
		<div class=\"smallfont\" style=\"padding: 2px\">
		<table cellpadding=\"0\" cellspacing=\"2\" border=\"0\" width=\"100%\">
		<col width=\"50\"></col>
		<col></col>
		<col align=\"$stylevar[right]\"></col>
		<tr>
			<td rowspan=\"5\"><img src=\"control_examples/" . substr($varname, 14) . ".gif\" alt=\"\" title=\"$title\" /></td>
			" . construct_color_row($vbphrase['background'], "commontemplate[$varname][background]", htmlspecialchars_uni($item['background']), $color, 12, false) . "
		</tr>
		<tr>
			" . construct_color_row($vbphrase['font_color'], "commontemplate[$varname][color]", htmlspecialchars_uni($item['color']), $color, 12, false) . "
		</tr>
		<tr>
			<td>$vbphrase[padding]</td>
			<td><input type=\"text\" class=\"$color\" name=\"commontemplate[$varname][padding]\" size=\"20\" value=\"" . htmlspecialchars_uni($item['padding']) . "\" tabindex=\"1\" dir=\"ltr\" /></td>
		</tr>
		<tr>
			<td>$vbphrase[border]</td>
			<td><input type=\"text\" class=\"$color\" name=\"commontemplate[$varname][border]\" size=\"20\" value=\"" . htmlspecialchars_uni($item['border']) . "\" tabindex=\"1\" dir=\"ltr\" /></td>
		</tr>";
	if ($revertcode['info'])
	{
		$out .= "
		<tr>
			<td>$revertcode[info]</td>
			<td>$revertcode[revertcode]</td>
		</tr>";
	}
	else
	{
		$out .= "
		<tr>
			<td colspan=\"2\">&nbsp;</td>
		</tr>";
	}
	$out .= "
		</table>
		</div>
	</fieldset>";

	return $out;
}

// #############################################################################
/**
* Returns a row containing a <select> for use in selecting text alignment
*
* @param	string	Item title
* @param	array	Item info array
*
* @return	string
*/
function construct_text_align_code($title, $item)
{
	global $css, $color, $vbphrase;

	// this is currently disabled
	return false;

	$alignoptions = array(
		'' => '(' . $vbphrase['inherit'] . ')',
		'left' => $vbphrase['align_left'],
		'center' => $vbphrase['align_center'],
		'right' => $vbphrase['align_right'],
		'justify' => $vbphrase['justified']
	);

	eval("\$value = \$css" . $item . ";");
	return "\t\t<tr><td>$title</td><td>\n\t<select class=\"$color\" name=\"css" . str_replace("['", "[", str_replace("']", "]", $item)) . "\" tabindex=\"1\">\n" . construct_select_options($alignoptions, $value) . "\t</select>\n\t</td></tr>\n";
}

// #############################################################################
/**
* Returns a row containing an input and a color picker widget
*
* @param	string	Item title
* @param	string	Item varname
* @param	string	Item value
* @param	string	CSS class to display with
* @param	integer	Size of input box
* @param	boolean	Surround code with <tr> ... </tr> ?
*
* @return	string
*/
function construct_color_row($title, $name, $value, $class = 'bginput', $size = 22, $printtr = true)
{
	global $numcolors;

	$value = htmlspecialchars_uni($value);

	$html = '';
	if ($printtr)
	{
		$html .= "
		<tr>\n";
	}
	$html .= "
			<td>$title</td>
			<td>
				<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
				<tr>
					<td><input type=\"text\" class=\"$class\" name=\"$name\" id=\"color_$numcolors\" value=\"$value\" title=\"\$$name\" tabindex=\"1\" size=\"$size\" onchange=\"preview_color($numcolors)\" dir=\"ltr\" />&nbsp;</td>
					<td><div id=\"preview_$numcolors\" class=\"colorpreview\" onclick=\"open_color_picker($numcolors, event)\"></div></td>
				</tr>
				</table>
			</td>
	";
	if ($printtr)
	{
		$html .= "	</tr>\n";
	}

	$numcolors ++;

	return $html;
}

// #############################################################################
/**
* Prints a row containing an <input type="text" />
*
* @param	string	Title for row
* @param	string	Name for input field
* @param	string	Value for input field
* @param	boolean	Whether or not to htmlspecialchars the input field value
* @param	integer	Size for input field
* @param	integer	Max length for input field
* @param	string	Text direction for input field
* @param	mixed	If specified, overrides the default CSS class for the input field
*/
function print_color_input_row($title, $name, $value = '', $htmlise = true, $size = 35, $maxlength = 0, $direction = '', $inputclass = false)
{
	global $vbulletin, $numcolors, $stylevar;

	$direction = verify_text_direction($direction);

	print_label_row(
		$title,
		"<div id=\"ctrl_$name\">
			<input style=\"float:$stylevar[left]; margin-$stylevar[right]: 4px\" type=\"text\" class=\"" . iif($inputclass, $inputclass, 'bginput') . "\" name=\"$name\" id=\"color_$numcolors\" value=\"" . iif($htmlise, htmlspecialchars_uni($value), $value) . "\" size=\"$size\"" . iif($maxlength, " maxlength=\"$maxlength\"") . " dir=\"$direction\" tabindex=\"1\"" . iif($vbulletin->debug, " title=\"name=&quot;$name&quot;\"") . " onchange=\"preview_color($numcolors)\" />
			<div style=\"float:$stylevar[left]\" id=\"preview_$numcolors\" class=\"colorpreview\" onclick=\"open_color_picker($numcolors, event)\"></div>
		</div>",
		'', 'top', $name
	);

	$numcolors++;
}

// #############################################################################
/**
* Builds the color picker popup item for the style editor
*
* @param	integer	Width of each color swatch (pixels)
* @param	string	CSS 'display' parameter (default: 'none')
*
* @return	string
*/
function construct_color_picker($size = 12, $display = 'none')
{
	global $vbulletin, $colorPickerWidth, $colorPickerType;

	$previewsize = 3 * $size;
	$surroundsize = $previewsize * 2;
	$colorPickerWidth = 21 * $size + 22;

	$html = "
	<style type=\"text/css\">
	#colorPicker
	{
		background: black;
		position: absolute;
		left: 0px;
		top: 0px;
		width: {$colorPickerWidth}px;
	}
	#colorFeedback
	{
		border: solid 1px black;
		border-bottom: none;
		width: {$colorPickerWidth}px;
	}
	#colorFeedback input
	{
		font: 11px verdana, arial, helvetica, sans-serif;
	}
	#colorFeedback button
	{
		width: 19px;
		height: 19px;
	}
	#txtColor
	{
		border: inset 1px;
		width: 70px;
	}
	#colorSurround
	{
		border: inset 1px;
		white-space: nowrap;
		width: {$surroundsize}px;
		height: 15px;
	}
	#colorSurround td
	{
		background-color: none;
		border: none;
		width: {$previewsize}px;
		height: 15px;
	}
	#swatches
	{
		background-color: black;
		width: {$colorPickerWidth}px;
	}
	#swatches td
	{
		background: black;
		border: none;
		width: {$size}px;
		height: {$size}px;
	}
	</style>
	<div id=\"colorPicker\" style=\"display:$display\" oncontextmenu=\"switch_color_picker(1); return false\" onmousewheel=\"switch_color_picker(event.wheelDelta * -1); return false;\">
	<table id=\"colorFeedback\" class=\"tcat\" cellpadding=\"0\" cellspacing=\"4\" border=\"0\" width=\"100%\">
	<tr>
		<td><button onclick=\"col_click('transparent'); return false\"><img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/colorpicker_transparent.gif\" title=\"'transparent'\" alt=\"\" /></button></td>
		<td>
			<table id=\"colorSurround\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
			<tr>
				<td id=\"oldColor\" onclick=\"close_color_picker()\"></td>
				<td id=\"newColor\"></td>
			</tr>
			</table>
		</td>
		<td width=\"100%\"><input id=\"txtColor\" type=\"text\" value=\"\" size=\"8\" /></td>
		<td style=\"white-space:nowrap\">
			<input type=\"hidden\" name=\"colorPickerType\" id=\"colorPickerType\" value=\"$colorPickerType\" />
			<button onclick=\"switch_color_picker(1); return false\"><img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/colorpicker_toggle.gif\" alt=\"\" /></button>
			<button onclick=\"close_color_picker(); return false\"><img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/colorpicker_close.gif\" alt=\"\" /></button>
		</td>
	</tr>
	</table>
	<table id=\"swatches\" cellpadding=\"0\" cellspacing=\"1\" border=\"0\">\n";

	$colors = array(
		'00', '33', '66',
		'99', 'CC', 'FF'
	);

	$specials = array(
		'#000000', '#333333', '#666666',
		'#999999', '#CCCCCC', '#FFFFFF',
		'#FF0000', '#00FF00', '#0000FF',
		'#FFFF00', '#00FFFF', '#FF00FF'
	);

	$green = array(5, 4, 3, 2, 1, 0, 0, 1, 2, 3, 4, 5);
	$blue = array(0, 0, 0, 5, 4, 3, 2, 1, 0, 0, 1, 2, 3, 4, 5, 5, 4, 3, 2, 1, 0);

	for ($y = 0; $y < 12; $y++)
	{
		$html .= "\t<tr>\n";

		$html .= construct_color_picker_element(0, $y, '#000000');
		$html .= construct_color_picker_element(1, $y, $specials["$y"]);
		$html .= construct_color_picker_element(2, $y, '#000000');

		for ($x = 3; $x < 21; $x++)
		{
			$r = floor((20 - $x) / 6) * 2 + floor($y / 6);
			$g = $green["$y"];
			$b = $blue["$x"];

			$html .= construct_color_picker_element($x, $y, '#' . $colors["$r"] . $colors["$g"] . $colors["$b"]);
		}

		$html .= "\t</tr>\n";
	}

	$html .= "\t</table>
	</div>
	<script type=\"text/javascript\">
	<!--
	var tds = fetch_tags(fetch_object(\"swatches\"), \"td\");
	for (var i = 0; i < tds.length; i++)
	{
		tds[i].onclick = swatch_click;
		tds[i].onmouseover = swatch_over;
	}
	//-->
	</script>\n";

	return $html;
}

// #############################################################################
/**
* Builds a single color swatch for the color picker gadget
*
* @param	integer	Current X coordinate
* @param	integer	Current Y coordinate
* @param	string	Color
*
* @return	string
*/
function construct_color_picker_element($x, $y, $color)
{
	global $vbulletin;
	return "\t\t<td style=\"background:$color\" id=\"sw$x-$y\"><img src=\"../" . $vbulletin->options['cleargifurl'] . "\" alt=\"\" style=\"width:11px; height:11px\" /></td>\r\n";
}

// #############################################################################
/**
* Prints a block of controls for editing a CSS item on css.php?do=edit
*
* @param	string	Item title
* @param	string	Item description
* @param	array	Item info array
* @param	boolean	Print links edit section
* @param	boolean	Print table break
*/
function print_css_row($title, $description, $item, $dolinks = false, $restarttable = true)
{
	global $bgcounter, $css, $css_info, $color, $vbphrase, $stylevar, $vbulletin;
	static $item_js;

	++$item_js;

	$color = fetch_inherited_color($css_info["$item"], $vbulletin->GPC['dostyleid']);

	$title = htmlspecialchars_uni($title);
	switch ($css_info["$item"])
	{
		case -1:
			$tblhead_title = $title;
			$revertlink = '';
			$revertctrl = '';
			break;
		case $vbulletin->GPC['dostyleid']:
			$tblhead_title = "$title <span class=\"normal\">(" . $vbphrase['customized_in_this_style'] . ")</span>";
			$revertlink = 'title=' . urlencode($title) . '&amp;item=' . urlencode($item);
			$revertctrl = "<label for=\"rvcss_$item\">$vbphrase[revert_this_group_of_settings]<input type=\"checkbox\" id=\"rvcss_$item\" name=\"delete[css][$item]\" value=\"1\" tabindex=\"1\" title=\"$vbphrase[revert]\" /></label>";
			break;
		default:
			$tblhead_title = "$title <span class=\"normal\">(" . construct_phrase($vbphrase['customized_in_a_parent_style_x'], $css_info["$item"]) . ")</span>";
			$revertlink = 'title=' . urlencode($title) . '&amp;item=' . urlencode($item);
			$revertctrl = '';
			break;
	}

	echo "\n\n<!-- START $title CSS -->\n\n";

	print_column_style_code(array('width: 50%', 'width: 50%'));
	print_table_header($tblhead_title, 2);

	print_label_row(
		"\n\t<fieldset title=\"$vbphrase[standard_css]\">
		<legend>$vbphrase[standard_css]</legend>
		<table cellpadding=\"0\" cellspacing=\"2\" border=\"0\" width=\"100%\">
		<col width=\"50%\"></col>\n" .
		construct_css_input_row($vbphrase['background'], "['$item']['background']", $color, true) .
		construct_css_input_row($vbphrase['font_color'], "['$item']['color']", $color, true) .
		construct_css_input_row($vbphrase['font_style'], "['$item']['font']['style']", $color) .
		construct_css_input_row($vbphrase['font_size'], "['$item']['font']['size']", $color) .
		construct_css_input_row($vbphrase['font_family'], "['$item']['font']['family']", $color) .
		construct_text_align_code($vbphrase['alignment'], "['$item']['text-align']", $color) .  "
		</table>
		</fieldset>\n\t",

		"
		<fieldset id=\"extra_a_$item_js\" title=\"$vbphrase[extra_css]\">
		<legend>$vbphrase[extra_css]</legend>
		<div align=\"center\" style=\"padding: 2px\">
		<textarea name=\"css[$item][EXTRA]\" rows=\"4\" cols=\"50\" class=\"$color\" style=\"padding: 2px; width: 90%\" tabindex=\"1\" dir=\"ltr\">" . htmlspecialchars_uni($css["$item"]['EXTRA']) . "</textarea>
		</div>
		</fieldset>
		" . iif($description != '', "<fieldset id=\"desc_a_$item_js\" title=\"$vbphrase[description]\" style=\"margin-bottom:4px;\">
		<legend>$vbphrase[description]</legend>
		<div class=\"smallfont\" style=\"margin:4px 4px 0px 4px\">
			<img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/cp_help.gif\" alt=\"$title\" align=\"$stylevar[right]\" style=\"padding:0px 0px 0px 2px\" />
			$description
		</div>
		</fieldset>") . "\n"
	, 'alt2');
	if (is_browser('mozilla'))
	{
		echo "<script type=\"text/javascript\">reflow_fieldset('a_$item_js', true);</script>\n";
	}

	if ($dolinks)
	{
		print_description_row('
		<table cellpadding="4" cellspacing="0" border="0" width="100%">
		<tr>
		' . construct_link_css_input_row($vbphrase['normal_link'], $item, 'N', $color) . '
		' . construct_link_css_input_row($vbphrase['visited_link'], $item, 'V', $color) . '
		' . construct_link_css_input_row($vbphrase['hover_link'], $item, 'M', $color) . '
		</tr>
		</table>
		', 0, 2, 'alt2" style="padding: 0px');
	}

	if ($revertctrl != '')
	{
		print_description_row('<div class="smallfont" style="text-align: center">' . $revertctrl . '</div>', 0, 2, 'thead');
	}

	print_description_row("
		<div class=\"alt1\" style=\"border:inset 1px; padding:2px 10px 2px 10px; float:$stylevar[left]\">" . construct_phrase($vbphrase['css_selector_x'], "<code>$item</code>") . "</div>
		<!--" . iif($revertlink != '', "<input type=\"button\" class=\"button\" style=\"font-weight:normal\" value=\"$vbphrase[show_default]\" tabindex=\"1\" onclick=\"js_show_default_item('$revertlink', $dolinks);\" />") . "-->
		<input type=\"submit\" class=\"button\" style=\"font-weight:normal\" value=\"  " . $vbphrase['save_css'] . "  \" tabindex=\"1\" />
	", 0, 2, 'tfoot" align="right');

	echo "\n\n<!-- END $title CSS -->\n\n";

	if ($restarttable)
	{
		print_table_break(' ');
	}
}

// #############################################################################
/**
* Reads results of form submission and updates special templates accordingly
*
* @param	array	Array of data from form
* @param	string	Variable type
* @param	string	Variable type name
*/
function build_special_templates($newtemplates, $templatetype, $vartype)
{
	global $vbulletin, $template_cache;

	DEVDEBUG('------------------------');

	foreach ($template_cache["$templatetype"] AS $title => $oldtemplate)
	{
		// ignore the '_validation' and '_failsafe' keys
		if ($title == '_validation' OR $title == '_failsafe')
		{
			continue;
		}

		// just carry on if there is no data for the current $newtemplate
		if (!isset($newtemplates["$title"]))
		{
			DEVDEBUG("\$$vartype" . "['$title'] is not set");
			continue;
		}

		// if delete the customized template, delete and continue
		if ($vbulletin->GPC['delete']["$vartype"]["$title"])
		{
			if ($vbulletin->GPC['dostyleid'] != -1)
			{
				$vbulletin->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "template
					WHERE title = '" . $vbulletin->db->escape_string($title) . "' AND
					templatetype = '$templatetype' AND
					styleid = " . $vbulletin->GPC['dostyleid'] . "
				");
				DEVDEBUG("$vartype $title (reverted)");

				if ($templatetype == 'stylevar' AND $title == 'codeblockwidth')
				{
					$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");
				}
			}
			continue;
		}

		// check for what to do with the template
		switch($templatetype)
		{
			case 'stylevar':
			{
				$newtemplate = $newtemplates["$title"];

				if (isset($newtemplates['_validation']["$title"]))
				{
					if (!preg_match($newtemplates['_validation']["$title"], $newtemplate))
					{
						$newtemplate = $newtemplates['_failsafe']["$title"];
					}
				}
				break;
			}
			case 'css':
				$newtemplate = serialize($newtemplates["$title"]);
				break;
			case 'replacement':
				$newtemplate = $newtemplates["$title"];
				break;
		}

		if ($newtemplate != $oldtemplate['template'])
		{
			// update existing $vartype template
			if ($oldtemplate['styleid'] == $vbulletin->GPC['dostyleid'])
			{
				$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "template
					SET template = '" . $vbulletin->db->escape_string($newtemplate) . "',
					dateline = " . TIMENOW . ",
					username = '" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "'
					WHERE title = '" . $vbulletin->db->escape_string($title) . "' AND
					templatetype = '$templatetype' AND
					styleid = " . $vbulletin->GPC['dostyleid'] . "
				");
				DEVDEBUG("$vartype $title (updated)");
			// insert new $vartype template
			}
			else
			{
				/*insert query*/
				$vbulletin->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "template
					(styleid, templatetype, title, dateline, username, template)
					VALUES
					(" . intval($vbulletin->GPC['dostyleid']) . ", '$templatetype', '" . $vbulletin->db->escape_string($title) . "', " . TIMENOW . ", '" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "', '" . $vbulletin->db->escape_string($newtemplate) . "')
				");
				DEVDEBUG("$vartype $title (inserted)");
			}

			if ($templatetype == 'stylevar' AND $title == 'codeblockwidth')
			{
				$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");
			}
		}
		else
		{
			DEVDEBUG("$vartype $title (not changed)");
		}

	} // end foreach($template_cache)

}

// #############################################################################
/**
* Prints a row containing template search javascript controls
*/
function print_template_javascript()
{
	global $stylevar, $vbphrase, $vbulletin;

	print_phrase_ref_popup_javascript();

	echo '<script type="text/javascript" src="../clientscript/vbulletin_templatemgr.js?v=' . $vbulletin->options['simpleversion'] . '"></script>';
	echo '<script type="text/javascript">
<!--
	var textarea_id = "' . $vbulletin->textarea_id . '";
	var vbphrase = { \'not_found\' : "' . fetch_js_safe_string($vbphrase['not_found']) . '" };
// -->
</script>
';

	print_label_row(
	iif(is_browser('ie') OR is_browser('mozilla', '20040707'), $vbphrase['search_in_template'], $vbphrase['additional_functions']), 
	iif(is_browser('ie') OR is_browser('mozilla', '1.7'), '
	<input type="text" class="bginput" name="string" accesskey="t" value="' . htmlspecialchars_uni($vbulletin->GPC['searchstring']) . '" size="20" onChange="n=0;" tabindex="1" />
	<input type="button" class="button" style="font-weight:normal" value=" ' . $vbphrase['find'] . ' " accesskey="f" onClick="findInPage(document.cpform.string.value);" tabindex="1" /> &nbsp;'
	) . '<input type="button" class="button" style="font-weight:normal" value=" ' . $vbphrase['copy'] . ' " accesskey="c" onclick="HighlightAll();" tabindex="1" /> &nbsp;' .
	iif(is_browser('ie') OR is_browser('mozilla', '1.7'), '
	<input type="button" class="button" style="font-weight:normal" value="' . $vbphrase['view_quickref'] . '" accesskey="v" onclick="js_open_phrase_ref(0, 0);" tabindex="1" />
	<script type="text/javascript">document.cpform.string.onkeypress = findInPageKeyPress;</script> '
	));  
}

// ###########################################################################################
// START XML STYLE FILE FUNCTIONS

/// #############################################################################
/**
* Reads XML style file and imports data from it into the database
*
* @param	string	XML data
* @param	integer	Style ID
* @param	integer	Parent style ID
* @param	string	New style title
* @param	boolean	Allow vBulletin version mismatch
* @param	integer	Display order for new style
* @param	boolean	Allow user selection of new style
*/
function xml_import_style($xml = false, $styleid = -1, $parentid = -1, $title = '', $anyversion = false, $displayorder = 1, $userselect = true)
{

	// $GLOBALS['path'] needs to be passed into this function or reference $vbulletin->GPC['path']

	global $vbulletin, $vbphrase;

	print_dots_start('<b>' . $vbphrase['importing_style'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	require_once(DIR . '/includes/class_xml.php');

	$xmlobj = new vB_XML_Parser($xml, $vbulletin->GPC['path']);
	if ($xmlobj->error_no == 1)
	{
			print_dots_stop();
			print_stop_message('no_xml_and_no_path');
	}
	else if ($xmlobj->error_no == 2)
	{
			print_dots_stop();
			print_stop_message('please_ensure_x_file_is_located_at_y', 'vbulletin-style.xml', $vbulletin->GPC['path']);
	}

	$arr = $xmlobj->parse();

	if(!$arr)
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line());
	}

	elseif (empty($arr['templategroup']))
	{
		print_dots_stop();
		print_stop_message('invalid_file_specified');
	}

	$version = $arr['vbversion'];
	$master = ($arr['type'] == 'master' ? 1 : 0);
	$title = (empty($title) ? $arr['name'] : $title);
	$product = (empty($arr['product']) ? 'vbulletin' : $arr['product']);

	$arr = $arr['templategroup'];
	if (empty($arr[0]))
	{
		$arr = array($arr);
	}

	$full_product_info = fetch_product_list(true);
	$product_info = $full_product_info["$product"];

	// version check
	if ($version != $product_info['version'] AND !$anyversion AND !$master)
	{
		print_dots_stop();
		print_stop_message('upload_file_created_with_different_version', $product_info['version'], $version);
	}

	if ($master)
	{
		// overwrite master style
		echo "<h3>$vbphrase[master_style]</h3>\n<p>$vbphrase[please_wait]</p>";
		vbflush();
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE styleid = -10 AND (product = '" . $vbulletin->db->escape_string($product) . "'" . iif($product == 'vbulletin', " OR product = ''") . ")");
		$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "template SET styleid = -10 WHERE styleid = -1 AND (product = '" . $vbulletin->db->escape_string($product) . "'" . iif($product == 'vbulletin', " OR product = ''") . ")");
		$styleid = -1;
	}
	else
	{
		if ($styleid == -1)
		{
			// creating a new style
			if ($test = $vbulletin->db->query_first("SELECT styleid FROM " . TABLE_PREFIX . "style WHERE title = '" . $vbulletin->db->escape_string($title) . "'"))
			{
				print_dots_stop();
				print_stop_message('style_already_exists', $title);
			}
			else
			{
				echo "<h3><b>" . construct_phrase($vbphrase['creating_a_new_style_called_x'], $title) . "</b></h3>\n<p>$vbphrase[please_wait]</p>";
				vbflush();
				/*insert query*/
				$styleresult = $vbulletin->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "style
					(title, parentid, displayorder, userselect)
					VALUES
					('" . $vbulletin->db->escape_string($title) . "', $parentid, $displayorder, " . ($userselect ? 1 : 0) . ")
				");
				$styleid = $vbulletin->db->insert_id($styleresult);
			}
		}
		else
		{
			// overwriting an existing style
			if ($getstyle = $vbulletin->db->query_first("SELECT title FROM " . TABLE_PREFIX . "style WHERE styleid = $styleid"))
			{
				echo "<h3><b>" . construct_phrase($vbphrase['overwriting_style_x'], $getstyle['title']) . "</b></h3>\n<p>$vbphrase[please_wait]</p>";
				vbflush();
			}
			else
			{
				print_dots_stop();
				print_stop_message('cant_overwrite_non_existent_style');
			}
		}
	}

	// types array...
	$types = array($vbphrase['template'], $vbphrase['stylevar'], $vbphrase['css'], $vbphrase['replacement_variable']);

	$querybits = array();
	$querytemplates = 0;


	foreach ($arr AS $templategroup)
	{
		if (empty($templategroup['template'][0]))
		{
			$tg = array($templategroup['template']);
		}
		else
		{
			$tg =& $templategroup['template'];
		}

		foreach($tg AS $template)
		{
			$title = $vbulletin->db->escape_string($template['name']);
			$template['template'] = $vbulletin->db->escape_string($template['value']);
			$template['username'] = $vbulletin->db->escape_string($template['username']);

			if ($template['templatetype'] != 'template')
			{
				// template is a special template
				$querybits[] = "($styleid, '$template[templatetype]', '$title', '$template[template]', '', $template[date], '$template[username]', '" . $vbulletin->db->escape_string($template['version']) . "', '" . $vbulletin->db->escape_string($product) . "')";
			}
			else
			{
				// template is a standard template
				$querybits[] = "($styleid, '$template[templatetype]', '$title', '" . $vbulletin->db->escape_string(compile_template($template['value'])) . "', '$template[template]', $template[date], '$template[username]', '" . $vbulletin->db->escape_string($template['version']) . "', '" . $vbulletin->db->escape_string($product) . "')";
			}


			if (++$querytemplates % 20 == 0)
			{
				/*insert query*/
				$vbulletin->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "template
					(styleid, templatetype, title, template, template_un, dateline, username, version, product)
					VALUES
					" . implode(',', $querybits) . "
				");
				$querybits = array();
			}
		}
	}

	// insert any remaining templates
	if (!empty($querybits))
	{
		/*insert query*/
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "template
			(styleid, templatetype, title, template, template_un, dateline, username, version, product)
			VALUES
			" . implode(',', $querybits) . "
		");
	}
	unset($querybits);

	// Get AdSense flag
	$adsensedeployed = $vbulletin->db->query_first("SELECT data FROM " . TABLE_PREFIX . "datastore WHERE title = 'adsensedeployed'");

	// Restore any adsense templates before delete
	if ($master AND !empty($adsensedeployed['data']))
	{
		// Get the template titles
		$save = array();
		$save_tables = $vbulletin->db->query_read("
			SELECT title
			FROM " . TABLE_PREFIX . "template
			WHERE templatetype = 'template'
				AND styleid = -10
				AND product IN('vbulletin', '')
				AND title LIKE 'ad\_%'
		");

		while ($table = $vbulletin->db->fetch_array($save_tables))
		{
			$save[] =  "'" . $vbulletin->db->escape_string($table['title']) . "'";
		}

		// Are there any
		if (count($save))
		{
			// Delete any style id -1 ad templates that may of just been imported.
			$vbulletin->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "template
				WHERE templatetype = 'template'
					AND styleid = -1
					AND product IN('vbulletin', '')
					AND title IN (" . implode(',', $save) . ")
			");

			// Replace the -1 templates with the -10 before they are deleted
			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "template
				SET styleid = -1
				WHERE templatetype = 'template'
					AND styleid = -10
					AND product IN('vbulletin', '')
					AND title IN (" . implode(',', $save) . ")
			");
		}
	}

	// now delete any templates that were moved into the temporary styleset for safe-keeping
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE styleid = -10 AND (product = '" . $vbulletin->db->escape_string($product) . "'" . iif($product == 'vbulletin', " OR product = ''") . ")");

	print_dots_stop();

}

// #############################################################################
/**
* Converts a version number string into an array that can be parsed
* to determine if which of several version strings is the newest.
*
* @param	string	Version string to parse
*
* @return	array	Array of 6 bits, in decreasing order of influence; a higher bit value is newer
*/
function fetch_version_array($version)
{
	// parse for a main and subversion
	if (preg_match('#^([a-z]+ )?([0-9\.]+)[\s-]*([a-z].*)$#i', trim($version), $match))
	{
		$main_version = $match[2];
		$sub_version = $match[3];
	}
	else
	{
		$main_version = $version;
		$sub_version = '';
	}

	$version_bits = explode('.', $main_version);

	// pad the main version to 4 parts (1.1.1.1)
	if (sizeof($version_bits) < 4)
	{
		for ($i = sizeof($version_bits); $i < 4; $i++)
		{
			$version_bits["$i"] = 0;
		}
	}

	// default sub-versions
	$version_bits[4] = 0; // for alpha, beta, rc, pl, etc
	$version_bits[5] = 0; // alpha, beta, etc number

	if (!empty($sub_version))
	{
		// match the sub-version
		if (preg_match('#^(A|ALPHA|B|BETA|G|GAMMA|RC|RELEASE CANDIDATE|GOLD|STABLE|FINAL|PL|PATCH LEVEL)\s*(\d*)\D*$#i', $sub_version, $match))
		{
			switch (strtoupper($match[1]))
			{
				case 'A':
				case 'ALPHA';
					$version_bits[4] = -4;
					break;

				case 'B':
				case 'BETA':
					$version_bits[4] = -3;
					break;

				case 'G':
				case 'GAMMA':
					$version_bits[4] = -2;
					break;

				case 'RC':
				case 'RELEASE CANDIDATE':
					$version_bits[4] = -1;
					break;

				case 'PL':
				case 'PATCH LEVEL';
					$version_bits[4] = 1;
					break;

				case 'GOLD':
				case 'STABLE':
				case 'FINAL':
				default:
					$version_bits[4] = 0;
					break;
			}

			$version_bits[5] = $match[2];
		}
	}

	// sanity check -- make sure each bit is an int
	for ($i = 0; $i <= 5; $i++)
	{
		$version_bits["$i"] = intval($version_bits["$i"]);
	}

	return $version_bits;
}

// #############################################################################
/**
* Compares two version strings. Returns true if the first parameter is
* newer than the second.
*
* @param	string	Version string; usually the latest version
* @param	string	Version string; usually the current version
*
* @return	bool	True if the first argument is newer than the second
*/
function is_newer_version($new_version_str, $cur_version_str)
{
	// if they're the same, don't even bother
	if ($cur_version_str != $new_version_str)
	{
		$cur_version = fetch_version_array($cur_version_str);
		$new_version = fetch_version_array($new_version_str);

		// iterate parts
		for ($i = 0; $i <= 5; $i++)
		{
			if ($new_version["$i"] != $cur_version["$i"])
			{
				// true if newer is greater
				return ($new_version["$i"] > $cur_version["$i"]);
			}
		}
	}

	return false;
}

// #############################################################################
/**
* Converts a version number such as 3.0.0 Beta 6 into an integer for comparison purposes
*
* Example:
* 3.0.0 beta 6
* (main version: 3.0.0, sub version: beta, sub release: 6)
* returns an integer like this:
* (main version){5,}(sub version){1}(sub release){2}
* Main version is sub divided to \d+?\d{2}\d{2} .
*
* Supports versions such as 3.99.99 beta 99 too.
* Direct comparison between versions integers is possible.
*
* @param	string	Version number string
*
* @return	integer
*/
function convert_version_to_int($version)
{
	$split = explode(' ', strtolower($version));
	$size = sizeof($split);

	$outputversion = 0;
	$type = 'none';

	for ($i = 0; $i < $size; $i++)
	{
		$token = trim($split[$i]);
		if (!$token)
		{
			continue;
		}

		if (preg_match('#^(\d+)\.(\d+)\.(\d+)$#', $token, $matches))
		{
			// matches X.Y.Z style
			$beginning = intval(sprintf('%02d%02d%02d', $matches[1], $matches[2], $matches[3]));
			$outputversion += $beginning * 1000;
		}
		else if ($token == 'rc')
		{
			$type = 'rc';
			$outputversion += 600;
		}
		else if ($token == 'release' AND trim($split[$i + 1]) == 'candidate')
		{
			$i++; // skip 'candidate';
			$type = 'rc';
			$outputversion  += 600;
		}
		else if ($token == 'gamma' OR $token == 'g')
		{
			$type = 'gamma';
			$outputversion += 500;
		}
		else if ($token == 'beta' OR $token == 'b')
		{
			$type = 'beta';
			$outputversion += 400;
		}
		else if ($token == 'alpha' OR $token == 'a')
		{
			$type = 'alpha';
			$outputversion += 200;
		}
		else if (preg_match('#^\d+$#', $token))
		{
			// is just a number, so it's probably the number in "beta X"
			$outputversion += sprintf('%02d', $token);
		}
	}
	if ($type == 'none')
	{
		// no type found, so assume that this is a final version
		$outputversion += 900;
	}

	return $outputversion;
}

/**
* Function used for usort'ing a collection of templates.
* This function will return newer versions first.
*
* @param	array	First version
* @param	array	Second version
*
* @return	integer	-1, 0, 1
*/
function history_compare($a, $b)
{
	// if either of them does not have a version, make it look really old to the
	// comparison tool so it doesn't get bumped all the way up when its not supposed to
	if (!$a['version'])
	{
		$a['version'] = "0.0.0";
	}

	if (!$b['version'])
	{
		$b['version'] = "0.0.0";
	}

	// these return values are backwards to sort in descending order
	if (is_newer_version($a['version'], $b['version']))
	{
		return -1;
	}
	else if (is_newer_version($b['version'], $a['version']))
	{
		return 1;
	}
	else
	{
		if($a['type'] == $b['type'])
		{
			return ($a['dateline'] > $b['dateline']) ? -1 : 1;
		}
		else if($a['type'] == "historical")
		{
			return 1;
		}
		else
		{
			return -1;
		}
	}
}

// #############################################################################
/**
* Collects errors encountered while parsing a template and returns them
*
* @param	string	Template PHP code
*
* @return	string
*/
function check_template_errors($template)
{
	// Attempt to enable display_errors so that this eval actually returns something in the event of an error
	@ini_set('display_errors', 'On');

	require_once(DIR . '/includes/functions_calendar.php'); // to make sure can_moderate_calendar exists

	if (preg_match('#^(.*)<if condition=(\\\\"|\')(.*)\\2>#siU', $template, $match))
	{
		// remnants of a conditional -- that means something is malformed, probably missing a </if>
		return fetch_error('template_conditional_end_missing_x', (substr_count($match[1], "\n") + 1));
	}

	if (preg_match('#^(.*)</if>#siU', $template, $match))
	{
		// remnants of a conditional -- missing beginning
		return fetch_error('template_conditional_beginning_missing_x', (substr_count($match[1], "\n") + 1));
	}

	if (strpos(@ini_get('disable_functions'), 'ob_start') !== false)
	{
		// alternate method in case OB is disabled; probably not as fool proof
		@ini_set('track_errors', true);
		$oldlevel = error_reporting();
		eval('$devnull = "' . $template . '";');
		error_reporting($oldlevel);

		if (strpos(strtolower($php_errormsg), 'parse') !== false)
		{
			// only return error if we think there's a parse error
			// best workaround to ignore "undefined variable" type errors
			return $php_errormsg;
		}
		else
		{
			return '';
		}
	}
	else
	{
		$olderrors = @ini_get('display_errors');
		$oldlevel = error_reporting();

		ob_start();
		eval('$devnull = "' . $template . '";');
		$errors = ob_get_contents();
		ob_end_clean();

		error_reporting($oldlevel);
		if ($olderrors !== false)
		{
			@ini_set('display_errors', $olderrors);
		}

		return $errors;
	}
}

/**
* Fetches a current or historical template.
*
* @param	integer	The ID (in the appropriate table) of the record you want to fetch
* @param	string	Type of template you want to fetch; should be "current" or "historical"
*
* @return	array	The data for the matching record
*/
function fetch_template_current_historical(&$id, $type)
{
	global $vbulletin, $db;

	$id = intval($id);

	if ($type == 'current')
	{
		return $db->query_first("
			SELECT *, template_un AS templatetext
			FROM " . TABLE_PREFIX . "template
			WHERE templateid = $id
		");
	}
	else
	{
		return $db->query_first("
			SELECT *, template AS templatetext
			FROM " . TABLE_PREFIX . "templatehistory
			WHERE templatehistoryid = $id
		");
	}
}


// ******************************** DECLARE ARRAYS AND GLOBAL VARS ******************************

/**
* Template group names => phrases
*
* @var	array
*/
$only = array
(
	// phrased groups
	'buddylist'      => empty($vbphrase['group_buddy_list']) ? '' : $vbphrase['group_buddy_list'],
	'calendar'       => empty($vbphrase['group_calendar']) ? '' : $vbphrase['group_calendar'],
	'faq'            => empty($vbphrase['group_faq']) ? '' : $vbphrase['group_faq'],
	'reputation'     => empty($vbphrase['group_user_reputation']) ? '' : $vbphrase['group_user_reputation'],
	'poll'           => empty($vbphrase['group_poll']) ? '' : $vbphrase['group_poll'],
	'pm'             => empty($vbphrase['group_private_message']) ? '' : $vbphrase['group_private_message'],
	'register'       => empty($vbphrase['group_registration']) ? '' : $vbphrase['group_registration'],
	'search'         => empty($vbphrase['group_search']) ? '' : $vbphrase['group_search'],
	'usercp'         => empty($vbphrase['group_user_control_panel']) ? '' : $vbphrase['group_user_control_panel'],
	'usernote'       => empty($vbphrase['group_user_note']) ? '' : $vbphrase['group_user_note'],
	'whosonline'     => empty($vbphrase['group_whos_online']) ? '' : $vbphrase['group_whos_online'],
	'showgroup'      => empty($vbphrase['group_show_groups']) ? '' : $vbphrase['group_show_groups'],
	'posticon'       => empty($vbphrase['group_post_icon']) ? '' : $vbphrase['group_post_icon'],
	'userfield'      => empty($vbphrase['group_user_profile_field']) ? '' : $vbphrase['group_user_profile_field'],
	'bbcode'         => empty($vbphrase['group_bb_code_layout']) ? '' : $vbphrase['group_bb_code_layout'],
	'help'           => empty($vbphrase['group_help']) ? '' : $vbphrase['group_help'],
	'editor'         => empty($vbphrase['group_editor']) ? '' : $vbphrase['group_editor'],
	'forumdisplay'   => empty($vbphrase['group_forum_display']) ? '' : $vbphrase['group_forum_display'],
	'forumhome'      => empty($vbphrase['group_forum_home']) ? '' : $vbphrase['group_forum_home'],
	'pagenav'        => empty($vbphrase['group_page_navigation']) ? '' : $vbphrase['group_page_navigation'],
	'postbit'        => empty($vbphrase['group_postbit']) ? '' : $vbphrase['group_postbit'],
	'posthistory'    => empty($vbphrase['group_posthistory']) ? '' : $vbphrase['group_posthistory'],
	'threadbit'      => empty($vbphrase['group_threadbit']) ? '' : $vbphrase['group_threadbit'],
	'im_'            => empty($vbphrase['group_instant_messaging']) ? '' : $vbphrase['group_instant_messaging'],
	'memberinfo'     => empty($vbphrase['group_member_info']) ? '' : $vbphrase['group_member_info'],
	'memberlist'     => empty($vbphrase['group_members_list']) ? '' : $vbphrase['group_members_list'],
	'moderation'     => empty($vbphrase['group_moderation']) ? '' : $vbphrase['group_moderation'],
	'modify'         => empty($vbphrase['group_modify_user_option']) ? '' : $vbphrase['group_modify_user_option'],
	'new'            => empty($vbphrase['group_new_posting']) ? '' : $vbphrase['group_new_posting'],
	'showthread'     => empty($vbphrase['group_show_thread']) ? '' : $vbphrase['group_show_thread'],
	'smiliepopup'    => empty($vbphrase['group_smilie_popup']) ? '' : $vbphrase['group_smilie_popup'],
	'subscribe'      => empty($vbphrase['group_subscribed_thread']) ? '' : $vbphrase['group_subscribed_thread'],
	'whoposted'      => empty($vbphrase['group_who_posted']) ? '' : $vbphrase['group_who_posted'],
	'threadadmin'    => empty($vbphrase['group_thread_administration']) ? '' : $vbphrase['group_thread_administration'],
	'navbar'         => empty($vbphrase['group_navigation_breadcrumb']) ? '' : $vbphrase['group_navigation_breadcrumb'],
	'printthread'    => empty($vbphrase['group_printable_thread']) ? '' : $vbphrase['group_printable_thread'],
	'attachmentlist' => empty($vbphrase['group_attachment_list']) ? '' : $vbphrase['group_attachment_list'],
	'userinfraction' => empty($vbphrase['group_user_infraction']) ? '' : $vbphrase['group_user_infraction'],
	'subscription'   => empty($vbphrase['group_paid_subscriptions']) ? '' : $vbphrase['group_paid_subscriptions'],
	'announcement'   => empty($vbphrase['announcement']) ? '' : $vbphrase['announcement'],
	'visitormessage' => empty($vbphrase['group_visitor_message']) ? '' : $vbphrase['group_visitor_message'],
	'humanverify'    => empty($vbphrase['group_human_verification']) ? '' : $vbphrase['group_human_verification'],
	'socialgroups'	 => empty($vbphrase['group_socialgroups']) ? '' : $vbphrase['group_socialgroups'],
	'picture'        => empty($vbphrase['group_picture_comment']) ? '' : $vbphrase['group_picture_comment'],
	'ad_'            => empty($vbphrase['group_ad_location']) ? '' : $vbphrase['group_ad_location'],
	'album'          => empty($vbphrase['group_album']) ? '' : $vbphrase['group_album'],
	'tag'            => empty($vbphrase['tag']) ? '' : $vbphrase['tag'],
	'aaa' => 'AAA Old Backup'
);

if (class_exists('vBulletinHook'))
{
	($hook = vBulletinHook::fetch_hook('template_groups')) ? eval($hook) : false;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 93074 $
|| # $Date: 2017-02-22 21:13:10 -0800 (Wed, 22 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
