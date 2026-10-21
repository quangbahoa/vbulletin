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
define('CVS_REVISION', '$RCSfile$ - $Revision: 13117 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('help_faq');
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

print_cp_header("$vbphrase[admin_help]");

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
			$helpphrase["$phrase[varname]"] = $phrase['text'];
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

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13117 $
|| ####################################################################
\*======================================================================*/
?>