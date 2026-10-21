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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'faq');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('fronthelp');

// get special data templates from the datastore
$specialtemplates = array('attachmentcache');

// pre-cache templates used by all actions
$globaltemplates = array(
	'FAQ',
	'faqbit',
	'faqbit_link'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_faq.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'main';
}

// initialize some important arrays
$displayorder = array();
$ifaqcache = array();
$faqcache = array();
$attachtypes = unserialize($datastore['attachmentcache']);

// initialize some template bits
$faqbits = '';
$faqlinks = '';
$navbits = array();

// #############################################################################

if ($_REQUEST['do'] == 'search')
{
	globalize($_REQUEST, array('q' => STR, 'match' => STR, 'titlesonly' => INT));

	if ($q == '')
	{
		eval(print_standard_error('error_searchspecifyterms'));
	}

	$phraseIds = array();		// array to store phraseids of phrases to search
	$whereText = array();		// array to store 'text LIKE(something)' entries
	$faqnames = array();		// array to store FAQ shortnames that match the search query
	$find = array();			// array to store all find words

	$phrasetypeSql = iif($titlesonly, '= ' . PHRASETYPEID_FAQTITLE, 'IN(' . PHRASETYPEID_FAQTITLE . ', ' . PHRASETYPEID_FAQTEXT . ')');

	// get a list of phrase ids to search in
	$query = "
		SELECT phraseid, phrasetypeid, varname
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN(-1, 0, " . LANGUAGEID . ")
			AND phrasetypeid $phrasetypeSql
		ORDER BY languageid
	";
	$out .= "<pre>" . htmlspecialchars($query) . "</pre>";

	$phrases = $DB_site->query($query);
	while ($phrase = $DB_site->fetch_array($phrases))
	{
		$phraseIds["$phrase[varname]_$phrase[phrasetypeid]"] = $phrase['phraseid'];
	}
	unset($phrase);
	$DB_site->free_result($phrases);
	$out .= "<p>matched phrases: " . sizeof($phraseIds) . "</p>";

	switch($match)
	{
		case 'all':
			$search = preg_split('#\s+#', $q);
			$matchSql = ' AND ';
			break;
		case 'phr':
			$search = array($q);
			$matchSql = ' ';
			break;
		default: // any
			$search = preg_split('#\s+#',$q);
			$matchSql = ' OR ';
			break;
	}
	unset($match);

	foreach ($search AS $word)
	{
		$find[] = preg_quote($word, '#'); // = '/(?<=[^\w=])(' . preg_quote($word, '/') . ')(?=[^\w=])/ie';

		$whereText[] = "text LIKE('%" . addslashes_like($word) . "%')";
	}
	$query = "
		SELECT varname AS faqname
		FROM " . TABLE_PREFIX . "phrase AS phrase
		WHERE phraseid IN(" . implode(', ', $phraseIds) . ")
			AND (" . implode($matchSql, $whereText) . ")
	";
	$out .= "<pre>" . htmlspecialchars($query) . "</pre>";

	$phrases = $DB_site->query($query);
	if (!$DB_site->num_rows($phrases))
	{
		eval(print_standard_error('error_searchnoresults'));
	}
	while ($phrase = $DB_site->fetch_array($phrases))
	{
		$faqcache["$phrase[faqname]"] = $phrase;
		$ifaqcache['faqroot']["$phrase[faqname]"] = &$faqcache["$phrase[faqname]"];
		$out .= "<div>Matched " . iif($phrase['phrasetypeid'] == PHRASETYPEID_FAQTITLE, 'Title', 'Body') . " for FAQ <b>$phrase[faqname]</b></div>";
	}

	$query = "
		SELECT faqname, faqparent, phrase.text AS title
		FROM " . TABLE_PREFIX . "faq AS faq
		INNER JOIN " . TABLE_PREFIX . "phrase AS phrase ON(phrase.phrasetypeid = " . PHRASETYPEID_FAQTITLE . " AND phrase.varname = faq.faqname)
		WHERE phrase.languageid IN(-1, 0, " . LANGUAGEID . ")
			AND (
			faqparent IN('" . implode("', '", array_keys($faqcache)) . "')
			OR
			faqname IN('" . implode("', '", array_keys($faqcache)) . "')
		)
	";
	$out .= "<pre>" . htmlspecialchars($query) . "</pre>";

	$faqs = $DB_site->query($query);
	while ($faq = $DB_site->fetch_array($faqs))
	{
		$faqcache["$faq[faqname]"] = $faq;
		if ($ifaqcache['faqroot']["$faq[faqname]"] != '')
		{
			$ifaqcache['faqroot']["$faq[faqname]"] = &$faqcache["$faq[faqname]"];
		}
		else
		{
			$ifaqcache["$faq[faqparent]"]["$faq[faqname]"] = &$faqcache["$faq[faqname]"];
		}
	}
	unset($faq);
	$DB_site->free_result($faqs);

	fetch_faq_text_array($ifaqcache['faqroot']);

	$faqparent = 'faqroot';
	foreach ($ifaqcache['faqroot'] AS $faqname => $faq)
	{
		eval('$faq[\'text\'] = "' . str_replace(array("\\'", '\\\\$'), array("'", '\\$'), addslashes($faq['text'])) . '";');
		construct_faq_item($faq, $find);
	}

	$q = htmlspecialchars_uni($q);

	// construct navbits
	$navbits = array(
		"faq.php?$session[sessionurl]" => $vbphrase['faq'],
		'' => $vbphrase['search_results']
	);
}

// #############################################################################

if ($_REQUEST['do'] == 'main')
{
	globalize($_REQUEST, array('faq' => STR));

	// get parent variable
	if ($faq == '')
	{
		$faqparent = 'faqroot';
	}
	else
	{
		$faqparent = addslashes($faq);
	}

	// set initial navbar entry
	if ($faqparent == 'faqroot')
	{
		$navbits[''] = $vbphrase['faq'];
	}
	else
	{
		$navbits["faq.php?$session[sessionurl]"] = $vbphrase['faq'];
	}

	cache_ordered_faq();

	// get bits for faq text cache
	$faqtext = array();
	if (is_array($ifaqcache["$faqparent"]))
	{
		fetch_faq_text_array($ifaqcache["$faqparent"]);
	}
	else
	{
		$idname = $vbphrase['faq_item'];
		eval(print_standard_error('invalidid'));
	}

	// display FAQs
	$faq = array();
	foreach ($ifaqcache["$faqparent"] AS $faq)
	{
		if ($faq['displayorder'] > 0)
		{
			eval('$faq[\'text\'] = "' . str_replace(array("\\'", '\\\\$'), array("'", '\\$'), addslashes($faq['text'])) . '";');
			construct_faq_item($faq, $find, $replace, $replace);
		}
	}

	$faqtitle = $faqcache["$faqparent"]['title'];
	$show['faqtitle'] = iif ($faqtitle, true, false);

	// get navbar stuff
	$parents = array();
	fetch_faq_parents($faqcache["$faqparent"]['faqname']);
	foreach (array_reverse($parents) AS $key => $val)
	{
		$navbits["$key"] = $val;
	}

}

// #############################################################################

// parse search <select> options
$titleselect = array();
$matchselect = array();
if ($_REQUEST['do'] == 'search')
{
	$titleselect["$titlesonly"] = HTML_SELECTED;
	$matchselect["$match"] = HTML_SELECTED;
}
else
{
	$titleselect[0] = HTML_SELECTED;
	$matchselect['all'] = HTML_SELECTED;
}

$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('FAQ') . '");');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11607 $
|| ####################################################################
\*======================================================================*/
?>