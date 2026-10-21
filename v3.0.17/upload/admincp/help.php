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
define('CVS_REVISION', '$RCSfile$ - $Revision: 9381 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('help_faq', 'fronthelp');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_help.php');

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['adminhelpid'] != 0, "help id = " . $_REQUEST['adminhelpid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'answer';
}

// ############################### start download help XML ##############
if ($_REQUEST['do'] == 'download')
{
	// query topics
	$helptopics = array();
	$topics = $DB_site->query("
		SELECT * FROM " . TABLE_PREFIX . "adminhelp
		WHERE volatile = 1
		ORDER BY action, displayorder
	");
	while ($topic = $DB_site->fetch_array($topics))
	{
		$helptopics["$topic[script]"][] = $topic;
	}
	unset($topic);
	$DB_site->free_result($topics);

	$xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";
	$xml .= "<helptopics>\r\n";

	ksort($helptopics);
	foreach($helptopics AS $script => $scripttopics)
	{
		$xml .= "<helpscript name=\"$script\">\r\n";
		foreach($scripttopics AS $topic)
		{
			$phrasename = fetch_help_phrase_short_name($topic);
			$titlephrase = $phrasename . '_title';
			$textphrase = $phrasename . '_text';
			$xml .= "\t<helptopic " .
				iif($topic['action'], "act=\"$topic[action]\" ") .
				iif($topic['optionname'], "opt=\"$topic[optionname]\" ") .
				"disp=\"$topic[displayorder]\">";
			//$xml .= "\r\n\t\t<helptitle><![CDATA[" . xml_escape_cdata($helpphrase["$titlephrase"]) . "]]></helptitle>\r\n";
			//$xml .= "\t\t<helptext><![CDATA[" . xml_escape_cdata($helpphrase["$textphrase"]) . "]]></helptext>\r\n\t";
			$xml .= "</helptopic>\r\n";
		}
		$xml .= "</helpscript>\r\n";
	}

	$xml .= "</helptopics>";

	require_once('./includes/functions_file.php');
	file_download($xml, 'vbulletin-adminhelp.xml', 'text/xml');
}

// #########################################################################

print_cp_header($vbphrase['admin_help']);

if ($debug)
{
	print_form_header('', '', 0, 1, 'notaform');
	print_table_header($vbphrase['admin_help_manager']);
	print_description_row(
		construct_link_code($vbphrase['add_new_topic'], "help.php?$session[sessionurl]do=edit") .
		construct_link_code($vbphrase['edit_topics'], "help.php?$session[sessionurl]do=manage") .
		construct_link_code($vbphrase['import_admin_help_xml_file'], "help.php?$session[sessionurl]do=import") .
		construct_link_code($vbphrase['download_admin_help_xml_file'], "help.php?$session[sessionurl]do=download", 'download'), 0, 2, '', 'center');
	print_table_footer();
}

// ############################### start do upload help XML ##############
if ($_REQUEST['do'] == 'doimport')
{
	globalize($_POST, array(
		'serverfile' => STR,
		'helpfile' => FILE
	));

	// got an uploaded file?
	if (file_exists($helpfile['tmp_name']))
	{
		$xml = file_read($helpfile['tmp_name']);
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

	xml_import_help_topics($xml);

	echo '<p align="center">' . $vbphrase['imported_admin_help_successfully'] . '<br />' . construct_link_code($vbphrase['continue'], "help.php?$session[sessionurl]do=manage") . '</p>';
}

// ############################### start upload help XML ##############
if ($_REQUEST['do'] == 'import')
{
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
	//-->
	</script>
	<?php

	print_form_header('help', 'doimport', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.helpfile);');
	print_table_header($vbphrase['import_admin_help_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'helpfile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './install/vbulletin-adminhelp.xml');
	print_submit_row($vbphrase['import'], 0);
}

// ############################### start listing answers ##############
if ($_REQUEST['do'] == 'answer')
{
	globalize($_REQUEST, array(
		'page' => STR,
		'pageaction' => STR,
		'option' => STR
	));

	if (empty($page))
	{
		$fullpage = REFERRER;
	}
	else
	{
		$fullpage = $page;
	}

	if (!$fullpage)
	{
		print_stop_message('invalid_page_specified');
	}

	if ($strpos = strpos($fullpage, '?'))
	{
		$pagename = basename(substr($fullpage, 0, $strpos));
	}
	else
	{
		$pagename = basename($fullpage);
	}

	if ($strpos = strpos($pagename, '.'))
	{
		$pagename = substr($pagename, 0, $strpos); // remove the .php part as people may have different extensions
	}

	if (!empty($pageaction))
	{
		$action = $pageaction;
	}
	else if ($strpos AND preg_match('#do=([^&]+)(&|$)#sU', substr($fullpage, $strpos), $matches))
	{
		$action = $matches[1];
	}
	else
	{
		$action = '';
	}

	if (empty($option))
	{
		$option = NULL;
	}

	$helptopics = $DB_site->query("
		SELECT *, LENGTH(action) AS length
		FROM " . TABLE_PREFIX . "adminhelp
		WHERE script = '".addslashes($pagename)."' AND
			(action = '' OR FIND_IN_SET('" . addslashes($action) . "', action))
			" . iif($option !== NULL, "AND
			optionname = '" . addslashes($option) . "'") . " AND
			displayorder <> 0
		ORDER BY length, displayorder
	");
	if (($resultcount = $DB_site->num_rows($helptopics)) == 0)
	{
		print_stop_message('no_help_topics');
	}
	else
	{
		$general = array();
		$specific = array();
		$phraseSQL = array();
		while ($topic = $DB_site->fetch_array($helptopics))
		{
			$phrasename = addslashes(fetch_help_phrase_short_name($topic));
			$phraseSQL[] = "'$phrasename" . "_title'";
			$phraseSQL[] = "'$phrasename" . "_text'";

			if (!$topic['action'])
			{
				$general[] = $topic;
			}
			else
			{
				$specific[] = $topic;
			}
		}

		// query phrases
		$helpphrase = array();
		$phrases = $DB_site->query("
			SELECT varname, text, languageid
			FROM " . TABLE_PREFIX . "phrase
			WHERE phrasetypeid = " . PHRASETYPEID_ADMINHELP . "
				AND languageid IN(-1, 0, " . LANGUAGEID . ")
				AND varname IN(\n" . implode(",\n", $phraseSQL) . "\n)
			ORDER BY languageid ASC
		");
		while($phrase = $DB_site->fetch_array($phrases))
		{
			$helpphrase["$phrase[varname]"] = preg_replace('#\{\$([a-z0-9_]+(\[[a-z0-9_]+\])?)\}#ie', '(isset($\\1) AND !is_array($\\1)) ? $\\1 : \'$\\1\'', $phrase['text']);
		}

		if ($resultcount != 1)
		{
			print_form_header('', '');
			print_table_header($vbphrase['quick_help_topic_links'], 1);
			if (sizeof($specific))
			{
				print_description_row($vbphrase['action_specific_topics'], 0, 1, 'thead');
				foreach ($specific AS $topic)
				{
					print_description_row('<a href="#help' . $topic['adminhelpid'] . '">' . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . '</a>', 0, 1);
				}
			}
			if (sizeof($general))
			{
				print_description_row($vbphrase['general_topics'], 0, 1, 'thead');
				foreach ($general AS $topic)
				{
					print_description_row('<a href="#help' . $topic['adminhelpid'] . '">' . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . '</a>', 0, 1);
				}
			}
			print_table_footer();
		}

		if (sizeof($specific))
		{
			reset($specific);
			print_form_header('', '');
			if ($resultcount != 1)
			{
				print_table_header($vbphrase['action_specific_topics'], 1);
			}
			foreach ($specific AS $topic)
			{
				print_description_row("<a name=\"help$topic[adminhelpid]\">" . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . "</a>", 0, 1, 'thead');
				print_description_row($helpphrase[fetch_help_phrase_short_name($topic, '_text')]);
			}
			print_table_footer();
		}

		if (sizeof($general))
		{
			reset($general);
			print_form_header('', '');
			if ($resultcount != 1)
			{
				print_table_header($vbphrase['general_topics'], 1);
			}
			foreach ($general AS $topic)
			{
				print_description_row("<a name=\"help$topic[adminhelpid]\">" . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . "</a>", 0, 1, 'thead');
				print_description_row($helpphrase[fetch_help_phrase_short_name($topic, '_text')]);
			}
			print_table_footer();
		}
	}
}

// ############################### start form for adding/editing help topics ##############
if ($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array('adminhelpid' => INT));

	$helpphrase = array();

	print_form_header('help', 'doedit');
	if (empty($adminhelpid))
	{
		$adminhelpid = 0;
		$helpdata = array(
			'adminhelpid' => 0,
			'script' => '',
			'action' => '',
			'optionname' => '',
			'displayorder' => 1,
			'volatile' => iif($debug, 1, 0)
		);

		print_table_header($vbphrase['add_new_topic']);
	}
	else
	{
		$helpdata = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "adminhelp WHERE adminhelpid = $adminhelpid");

		$titlephrase = fetch_help_phrase_short_name($helpdata, '_title');
		$textphrase = fetch_help_phrase_short_name($helpdata, '_text');

		// query phrases
		$phrases = $DB_site->query("
			SELECT varname, text FROM " . TABLE_PREFIX . "phrase
			WHERE phrasetypeid = " . PHRASETYPEID_ADMINHELP . " AND
			languageid = " . iif($helpdata['volatile'], -1, 0) . " AND
			varname IN ('" . addslashes($titlephrase) . "', '" . addslashes($textphrase) . "')
		");
		while ($phrase = $DB_site->fetch_array($phrases))
		{
			$helpphrase["$phrase[varname]"] = $phrase['text'];
		}
		unset($phrase);
		$DB_site->free_result($phrases);

		construct_hidden_code('orig[script]', $helpdata['script']);
		construct_hidden_code('orig[action]', $helpdata['action']);
		construct_hidden_code('orig[optionname]', $helpdata['optionname']);
		construct_hidden_code('orig[title]', $helpphrase["$titlephrase"]);
		construct_hidden_code('orig[text]', $helpphrase["$textphrase"]);

		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['topic'], $helpdata['title'], $helpdata['adminhelpid']));
	}

	print_input_row($vbphrase['script'], 'help[script]', $helpdata['script']);
	print_input_row($vbphrase['action_leave_blank'], 'help[action]', $helpdata['action']);
	print_input_row($vbphrase['option'], 'help[optionname]', $helpdata['optionname']);
	print_input_row($vbphrase['display_order'], 'help[displayorder]', $helpdata['displayorder']);

	print_input_row($vbphrase['title'], 'title', $helpphrase["$titlephrase"]);
	print_textarea_row($vbphrase['text'], 'text', $helpphrase["$textphrase"], 8, 50);

	if ($debug)
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'help[volatile]', $helpdata['volatile']);
	}
	else
	{
		construct_hidden_code('help[volatile]', $helpdata['volatile']);
	}

	construct_hidden_code('adminhelpid', $adminhelpid);
	print_submit_row($vbphrase['save']);
}

// ############################### start actually adding/editing help topics ##############
if ($_POST['do'] == 'doedit')
{
	globalize($_POST, array(
		'adminhelpid' => INT,
		'help',
		'orig',
		'title' => STR,
		'text' => STR
	));

	if (!$help['script'])
	{
		print_stop_message('please_complete_required_fields');
	}

	$newphrasename = addslashes(fetch_help_phrase_short_name($help));

	$languageid = iif($help['volatile'], -1, 0);

	if (is_array($orig)) // update
	{
		$oldphrasename = addslashes(fetch_help_phrase_short_name($orig));

		// update help item
		$q[] = fetch_query_sql($help, 'adminhelp', "WHERE adminhelpid = $adminhelpid");

		// update phrase titles for all languages
		if ($newphrasename != $oldphrasename)
		{
			$q[] = "
				### UPDATE HELP TITLE PHRASES FOR ALL LANGUAGES ###
				UPDATE " . TABLE_PREFIX . "phrase
				SET varname = '$newphrasename" . "_title'
				WHERE phrasetypeid = " . PHRASETYPEID_ADMINHELP . "
				AND varname = '$oldphrasename" . "_title'";
			$q[] = "
				### UPDATE HELP TEXT PHRASES FOR ALL LANGUAGES ###
				UPDATE " . TABLE_PREFIX . "phrase
				SET varname = '$newphrasename" . "_text'
				WHERE phrasetypeid = " . PHRASETYPEID_ADMINHELP . "
				AND varname = '$oldphrasename" . "_text'";
		}

		// update phrase title contents for master language
		if ($orig['title'] != $title)
		{
			$q[] = "
			### UPDATE HELP TITLE CONTENTS PHRASES FOR MASTER LANGUAGE ###
			UPDATE " . TABLE_PREFIX . "phrase
			SET text = '" . addslashes($title) . "'
			WHERE phrasetypeid = " . PHRASETYPEID_ADMINHELP . "
			AND languageid = $languageid
			AND varname = '$newphrasename" . "_title'";
		}
		// update phrase text contents for master language
		if ($orig['text'] != $text)
		{
			$q[] = "
			### UPDATE HELP TEXT CONTENTS PHRASES FOR MASTER LANGUAGE ###
			UPDATE " . TABLE_PREFIX . "phrase
			SET text = '" . addslashes($text) . "'
			WHERE phrasetypeid = " . PHRASETYPEID_ADMINHELP . "
			AND languageid = $languageid
			AND varname = '$newphrasename" . "_text'";
		}
	}
	else // insert
	{
		if ($check = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "adminhelp WHERE script = '" . addslashes($help['script']) . "' AND action = '" . addslashes($help['action']) . "' AND optionname = '" . addslashes($help['optionname']) . "'"))
		{ // error message, this already exists
			// why phrase when its only available in debug mode and its meant for us?
			print_cp_message('This help item already exists.');
		}

		// insert help item
		$q[] = fetch_query_sql($help, 'adminhelp');

		// insert new phrases
		$q[] = "
			### INSERT NEW HELP PHRASES ###
			INSERT INTO " . TABLE_PREFIX . "phrase
				(languageid, phrasetypeid, varname, text)
			VALUES
				($languageid, " . PHRASETYPEID_ADMINHELP . ", '$newphrasename" . "_title', '" . addslashes($title) . "'),
				($languageid, " . PHRASETYPEID_ADMINHELP . ", '$newphrasename" . "_text', '" . addslashes($text) . "')";

	}


	foreach($q AS $sql)
	{
		//echo "<pre>" . htmlspecialchars($sql) . "</pre>";
		$DB_site->query($sql);
		//echo $DB_site->affected_rows();
	}


	define('CP_REDIRECT', "help.php?$session[sessionurl]do=manage&amp;script=$help[script]");
	print_stop_message('saved_topic_x_successfully', $title);

}

// ############################### start confirmation for deleting a help topic ##############
if ($_REQUEST['do'] == 'delete')
{
	globalize($_REQUEST, array('adminhelpid' => INT));

	print_delete_confirmation('adminhelp', $adminhelpid, 'help', 'dodelete', 'topic');
}

// ############################### start actually deleting the help topic ##############
if ($_POST['do'] == 'dodelete')
{
	globalize($_POST, array('adminhelpid' => INT));

	if ($help = $DB_site->query_first("SELECT script, action, optionname FROM " . TABLE_PREFIX . "adminhelp WHERE adminhelpid = $adminhelpid"))
	{
		// delete adminhelp entry
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "adminhelp WHERE adminhelpid = $adminhelpid");

		// delete associated phrases
		$phrasename = addslashes(fetch_help_phrase_short_name($help));
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE phrasetypeid = " . PHRASETYPEID_ADMINHELP . "
				AND varname IN ('$phrasename" . "_title', '$phrasename" . "_text')
		");

		// update language records
		require_once('./includes/adminfunctions_language.php');
		build_language();
	}

	define('CP_REDIRECT', 'help.php?do=manage');
	print_stop_message('deleted_topic_successfully');
}

// ############################### start list of existing help topics ##############
if ($_REQUEST['do'] == 'manage')
{
	globalize($_REQUEST, array('script' => STR));

	// query phrases
	$helpphrase = array();
	$phrases = $DB_site->query("SELECT varname, text FROM " . TABLE_PREFIX . "phrase WHERE phrasetypeid = " . PHRASETYPEID_ADMINHELP);
	while ($phrase = $DB_site->fetch_array($phrases))
	{
		$helpphrase["$phrase[varname]"] = $phrase['text'];
	}

	// query scripts
	$scripts = array();
	$getscripts = $DB_site->query("SELECT DISTINCT script FROM " . TABLE_PREFIX . "adminhelp");
	while ($getscript = $DB_site->fetch_array($getscripts))
	{
		$scripts["$getscript[script]"] = "$getscript[script].php";
	}
	unset($getscript);
	$DB_site->free_result($getscripts);

	// query topics
	$topics = array();
	$gettopics = $DB_site->query("
		SELECT adminhelpid, script, action, optionname, displayorder
		FROM " . TABLE_PREFIX . "adminhelp
		" . iif($script, "WHERE script = '" . addslashes($script) . "'") . "
		ORDER BY script, action, displayorder
	");
	while ($gettopic = $DB_site->fetch_array($gettopics))
	{
		$topics["$gettopic[script]"][] = $gettopic;
	}
	unset($gettopic);
	$DB_site->free_result($gettopics);

	// build the form
	print_form_header('help', 'manage', false, true, 'helpform' ,'90%', '', true, 'get');
	print_table_header($vbphrase['topic_manager'], 5);
	print_description_row('<div align="center">' . $vbphrase['script'] . ': <select name="script" tabindex="1" onchange="this.form.submit()" class="bginput"><option value="">' . $vbphrase['all_scripts'] . '</option>' . construct_select_options($scripts, $script) . '</select> <input type="submit" class="button" value="' . $vbphrase['go'] . '" tabindex="1" /></div>', 0, 5, 'thead');

	foreach($topics AS $script => $scripttopics)
	{
		print_table_header($script . '.php', 5);
		print_cells_row(
			array(
				$vbphrase['action'],
				$vbphrase['option'],
				$vbphrase['title'],
				$vbphrase['order_by'],
				''
			), 1, 0, -5
		);
		foreach($scripttopics AS $topic)
		{
			print_cells_row(
				array(
					'<span class="smallfont">' . $topic['action'] . '</span>',
					'<span class="smallfont">' . $topic['optionname'] . '</span>',
					'<span class="smallfont"><b>' . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . '</b></span>',
					'<span class="smallfont">' . $topic['displayorder'] . '</span>',
					'<span class="smallfont">' . construct_link_code($vbphrase['edit'], "help.php?$session[sessionurl]do=edit&amp;adminhelpid=$topic[adminhelpid]") . construct_link_code($vbphrase['delete'], "help.php?$session[sessionurl]do=delete&amp;adminhelpid=$topic[adminhelpid]") . '</span>'
				), 0, 0, -5
			);
		}
	}

	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9381 $
|| ####################################################################
\*======================================================================*/
?>