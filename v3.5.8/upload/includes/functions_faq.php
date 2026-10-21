<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ###################### Start makeFaqJump #######################
// get complete faq listings
function construct_faq_jump($parent = 0, $depth = 0)
{
	global $ifaqcache, $faqcache, $faqjumpbits, $faqparent, $vbphrase, $vbulletin;

	if (!is_array($ifaqcache["$parent"]))
	{
		return;
	}

	foreach($ifaqcache["$parent"] AS $key1 => $faq)
	{
		$optiontitle = str_repeat('--', $depth) . ' ' . $faq['title'];
		$optionvalue = 'faq.php?' . $vbulletin->session->vars['sessionurl'] . "faq=$parent#faq_$faq[faqname]";
		$optionselected = iif($faq['faqname'] == $faqparent, ' ' . 'selected="selected"');

		eval('$faqjumpbits .= "' . fetch_template('option') . '";');

		if (is_array($ifaqcache["$faq[faqname]"]))
		{
			construct_faq_jump($faq['faqname'], $depth + 1);
		}
	}
}

// ###################### Start getFaqParents #######################
// get parent titles function for navbar
function fetch_faq_parents($faqname)
{
	global $ifaqcache, $faqcache, $parents, $vbulletin;
	static $i;

	$faq = $faqcache["$faqname"];
	if (is_array($ifaqcache["$faq[faqparent]"]))
	{
		$key = iif($i++, 'faq.php?' . $vbulletin->session->vars['sessionurl'] . "faq=$faq[faqname]");
		$parents["$key"] = $faq['title'];
		fetch_faq_parents($faq['faqparent']);
	}
}

// ###################### Start showFaqItem #######################
// show an faq entry
function construct_faq_item($faq, $find = '')
{
	global $vbulletin, $stylevar, $ifaqcache, $faqbits, $faqlinks, $show, $vbphrase;

	$faq['text'] = trim($faq['text']);
	if (is_array($find))
	{
		$faq['title'] = preg_replace('#(^|>)([^<]+)(?=<|$)#sUe', "process_highlight_faq('\\2', \$find, '\\1', '<u>\\\\1</u>')", $faq['title']);
		$faq['text'] = preg_replace('#(^|>)([^<]+)(?=<|$)#sUe', "process_highlight_faq('\\2', \$find, '\\1', '<span class=\"highlight\">\\\\1</span>')", $faq['text']);
	}

	$faqsublinks = '';
	if (is_array($ifaqcache["$faq[faqname]"]))
	{
		foreach($ifaqcache["$faq[faqname]"] AS $subfaq)
		{
			if ($subfaq['displayorder'] > 0)
			{
				eval('$faqsublinks .= "' . fetch_template('faqbit_link') . '";');
			}
		}
	}

	$show['faqsublinks'] = iif ($faqsublinks, true, false);
	$show['faqtext'] = iif ($faq['text'], true, false);

	($hook = vBulletinHook::fetch_hook('faq_item_display')) ? eval($hook) : false;

	eval('$faqbits .= "' . fetch_template('faqbit') . '";');
}

// ###################### Start getFaqText #######################
// get text for FAQ entries
function fetch_faq_text_array($faqnames)
{
	global $vbulletin, $faqcache, $out, $header;

	$faqtext = array();
	$textcache = array();
	foreach($faqnames AS $faq)
	{
		$out .= "<div>Get text for <b>$faq[faqname]</b></div>";
		$faqtext[] = $vbulletin->db->escape_string($faq['faqname']);
	}

	$query = "
		SELECT varname, text, languageid
		FROM " . TABLE_PREFIX . "phrase AS phrase
		WHERE phrasetypeid = " . PHRASETYPEID_FAQTEXT . " AND
			languageid IN(-1, 0, " . LANGUAGEID . ") AND
			varname IN('" . implode("', '",  $faqtext) . "')
	";
	$out .= "<pre>" . htmlspecialchars($query) . "</pre>";

	$faqtexts = $vbulletin->db->query_read($query);
	$out .= '<p>matched phrases: ' . $vbulletin->db->num_rows($faqtexts) . '</p>';
	while($faqtext = $vbulletin->db->fetch_array($faqtexts))
	{
		$textcache["$faqtext[languageid]"]["$faqtext[varname]"] = $faqtext['text'];
	}
	unset($faqtext);
	$vbulletin->db->free_result($faqtexts);

	// sort with languageid
	ksort($textcache);

	foreach($textcache AS $faqtexts)
	{
		foreach($faqtexts AS $faqname => $faqtext)
		{
			$faqcache["$faqname"]['text'] = $faqtext;
		}
	}
}

// ###################### Start makeAdminFaqRow #######################
function print_faq_admin_row($faq, $prefix = '')
{
	global $ifaqcache, $vbphrase, $vbulletin;

	$cell = array(
		// first column
		$prefix . '<b></b>' . iif(is_array($ifaqcache["$faq[faqname]"]), '<a href="faq.php?' . $vbulletin->session->vars['sessionurl'] . 'faq=' . urlencode($faq['faqname']) . "\" title=\"$vbphrase[show_child_faq_entries]\">$faq[title]</a>", $faq['title']) . '<b></b>',
		// second column
		"<input type=\"text\" class=\"bginput\" size=\"4\" name=\"order[$faq[faqname]]\" title=\"$vbphrase[display_order]\" tabindex=\"1\" value=\"$faq[displayorder]\" />",
		// third column
		construct_link_code($vbphrase['edit'], 'faq.php?' . $vbulletin->session->vars['sessionurl'] . 'do=edit&amp;faq=' . urlencode($faq['faqname'])) .
		construct_link_code($vbphrase['add_child_faq_item'], "faq.php?" . $vbulletin->session->vars['sessionurl'] . 'do=add&amp;faq=' . urlencode($faq['faqname'])) .
		construct_link_code($vbphrase['delete'], 'faq.php?' . $vbulletin->session->vars['sessionurl'] . 'do=delete&amp;faq=' . urlencode($faq['faqname'])),
	);
	print_cells_row($cell);
}

// ###################### Start getifaqcache #######################
function cache_ordered_faq($gettext = false)
{
	global $vbulletin, $faqcache, $ifaqcache;

	// ordering arrays
	$displayorder = array();
	$languageorder = array();

	// data cache arrays
	$faqcache = array();
	$ifaqcache = array();
	$phrasecache = array();

	$phrasetypecondition = iif($gettext, "IN(" . PHRASETYPEID_FAQTITLE . ", " . PHRASETYPEID_FAQTEXT . ")", "= " . PHRASETYPEID_FAQTITLE);

	$phrases = $vbulletin->db->query_read("
		SELECT varname, text, languageid, phrasetypeid
		FROM " . TABLE_PREFIX . "phrase AS phrase
		WHERE phrasetypeid $phrasetypecondition AND
			languageid IN(-1, 0, " . LANGUAGEID . ")
	");
	while ($phrase = $vbulletin->db->fetch_array($phrases))
	{
		$languageorder["$phrase[languageid]"][] = $phrase;
	}

	ksort($languageorder);

	foreach($languageorder AS $phrases)
	{
		foreach($phrases AS $phrase)
		{
			if ($phrase['phrasetypeid'] == PHRASETYPEID_FAQTITLE)
			{
				$phrasecache["$phrase[varname]"]['title'] = $phrase['text'];
			}
			else
			{
				$phrasecache["$phrase[varname]"]['text'] = $phrase['text'];
			}
		}
	}
	unset($languageorder);

	$faqs = $vbulletin->db->query_read("SELECT faqname, faqparent, displayorder FROM " . TABLE_PREFIX . "faq");
	while ($faq = $vbulletin->db->fetch_array($faqs))
	{
		$faq['title'] = $phrasecache["$faq[faqname]"]['title'];
		if ($gettext)
		{
			$faq['text'] = $phrasecache["$faq[faqname]"]['text'];
		}
		$faqcache["$faq[faqname]"] = $faq;
		$displayorder["$faq[displayorder]"][] =& $faqcache["$faq[faqname]"];
	}
	unset($faq);
	$vbulletin->db->free_result($faqs);

	unset($phrasecache);
	ksort($displayorder);

	foreach($displayorder AS $faqs)
	{
		foreach($faqs AS $faq)
		{
			$ifaqcache["$faq[faqparent]"]["$faq[faqname]"] =& $faqcache["$faq[faqname]"];
		}
	}
}

// ###################### Start getFaqParentOptions #######################
function fetch_faq_parent_options($thisitem = '', $parentname = 'faqroot', $depth = 1)
{
	global $ifaqcache, $parentoptions;

	if (!is_array($parentoptions))
	{
		$parentoptions = array();
	}

	foreach($ifaqcache["$parentname"] AS $faq)
	{
		if ($faq['faqname'] != $thisitem)
		{
			$parentoptions["$faq[faqname]"] = str_repeat('--', $depth) . ' ' . $faq['title'];
			if (is_array($ifaqcache["$faq[faqname]"]))
			{
				fetch_faq_parent_options($thisitem, $faq['faqname'], $depth + 1);
			}
		}
	}
}

// ###################### Start getFaqDeleteList #######################
function fetch_faq_delete_list($parentname)
{
	global $ifaqcache, $vbulletin;

	if (!is_array($ifaqcache))
	{
		cache_ordered_faq();
	}

	static $deletelist;
	if (!is_array($deletelist))
	{
		$deletelist = array('\'' . $vbulletin->db->escape_string($parentname) . '\'');
	}

	if (is_array($ifaqcache["$parentname"]))
	{
		foreach($ifaqcache["$parentname"] AS $faq)
		{
			$deletelist[] = '\'' . $vbulletin->db->escape_string($faq['faqname']) . '\'';
			fetch_faq_delete_list($faq['faqname']);
		}
	}

	return $deletelist;
}

// ###################### Start process_highlight_faq #######################
function process_highlight_faq($text, $words, $prepend, $replace)
{
	$text = str_replace('\"', '"', $text);
	foreach ($words AS $replaceword)
	{
		//$text = preg_replace('#(?<=[\s"\]>()]|^)(' . $replaceword . ')(([.,:;-?!()\s"<\[]|$))#siU', '<span class="highlight">\\1</span>\\2', $text);
		$text = preg_replace('#(?<=[^\w=]|^)(\w*' . $replaceword . '\w*)(?=[^\w=]|$)#siU', $replace, $text);
	}

	return "$prepend$text";
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12580 $
|| ####################################################################
\*======================================================================*/
?>