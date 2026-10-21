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

/*
$fontoptions = makeJavascriptArray('vbcode_font_options');
$sizeoptions = makeJavascriptArray('vbcode_size_options');
$coloroptions = makeJavascriptArray('vbcode_color_options');
*/

// ###################### Start WYSIWYG_getparsedhtml #######################
function parse_wysiwyg_html($html, $ishtml = 0, $forumid = 0, $allowsmilie = 1)
{
	global $vbulletin;

	if ($ishtml)
	{
		// parse HTML into vbcode
		// I DON'T THINK THIS IS EVER USED NOW - KIER
		$html = convert_wysiwyg_html_to_bbcode($html);
	}
	else
	{
		$html = unhtmlspecialchars($html, 0);
	}

	// parse the message back into WYSIWYG-friendly HTML
	require_once(DIR . '/includes/class_bbcode_alt.php');
	$wysiwyg_parser = new vB_BbCodeParser_Wysiwyg($vbulletin, fetch_tag_list());
	$wysiwyg_parser->set_parse_userinfo($vbulletin->userinfo);

	return $wysiwyg_parser->parse($html, $forumid, $allowsmilie);
}

// ###################### Start safeUrl #######################
function sanitize_url($type, $url, $delimiter = '\\"')
{
	static $find, $replace;
	if (!is_array($find))
	{
		$find =    array('<',    '>',    '\\"');
		$replace = array('&lt;', '&gt;', '"');
	}

	$delimiter = str_replace('\\"', '"', $delimiter);

	return str_replace('\\"', '"', $type) . '=' . $delimiter . str_replace($find, $replace, $url) . $delimiter;
}

// ###################### Start getsmilietext #######################
function fetch_smilie_text($smilieid)
{
	global $vbulletin;
	static $smilies;

	// build the smilies array if we haven't already
	if (!is_array($smilies))
	{
		$smilies = array();
		// attempt to get smilies from the datastore smiliecache
		if (is_array($vbulletin->smiliecache))
		{
			foreach($vbulletin->smiliecache AS $smilie)
			{
				$smilies["$smilie[smilieid]"] = $smilie['smilietext'];
			}
		}
		// query smilies from the database
		else
		{
			$getsmilies = $vbulletin->db->query_read_slave("SELECT smilieid, smilietext FROM " . TABLE_PREFIX . "smilie");
			while ($smilie = $vbulletin->db->fetch_array($getsmilies))
			{
				$smilies["$smilie[smilieid]"] = $smilie['smilietext'];
			}
			$vbulletin->db->free_result($getsmilies);
		}
	}

	// return the smilietext for this smilie
	return $smilies["$smilieid"];
}

// ###################### Start WYSIWYG_html2vbcode #######################
function convert_wysiwyg_html_to_bbcode($text, $allowhtml = false, $p_two_linebreak = false)
{
	global $vbulletin;

	// debug code
	$vbulletin->input->clean_gpc('r', 'showhtml', TYPE_BOOL);
	if ($vbulletin->debug AND $vbulletin->GPC['showhtml'])
	{
		$otext = $text;
	}

	// deal with some wierdness that can be caused with URL tags in the WYSIWYG editor
	$text = preg_replace('#<a href="([^"]*)\[([^"]+)"(.*)>(.*)\[\\2</a>#siU', '<a href="\1"\3>\4</a>[\2', $text);

	// make < and > safe in inside URL/IMG tags so they don't get stripped by strip_tags
	$text = preg_replace_callback('#(<[^<>]+ (src|href))=(\'|"|)??(.*)(\\3)#siU', 
	function($matches)
	{
		return sanitize_url($matches[1], $matches[4], $matches[3]);
	}, 
	$text);


	($hook = vBulletinHook::fetch_hook('wysiwyg_parse_start')) ? eval($hook) : false;

	// attempt to remove bad html and keep only that which we intend to parse
	if (!$allowhtml)
	{
		$text = str_replace('<br/>', '<br />', $text);
		$text = preg_replace('#<script[^>]*>(.*)</script>#siU', '', $text);
		$text = strip_tags($text, '<b><strong><i><em><u><a><div><span><p><blockquote><ol><ul><li><font><img><br><h1><h2><h3><h4><h5><h6><strike>');
	}

	// replace &nbsp; with a regular space
	$text = str_replace('&nbsp;', ' ', $text);

	// deal with newline characters
	if (is_browser('mozilla'))
	{
		$text = preg_replace('#(?<!<br>|<br />|\r)(\r\n|\n|\r)#', ' ', $text);
	}

	$text = preg_replace('#(\r\n|\n|\r)#', '', $text);

	// regex find / replace #1
	$pregfind = array
	(
		#'#^(<div>\s*)+#si',                                  // multiple <DIV>s at string start
		#'#(\s*</div>)+$#si',                                 // multiple </DIV>s at string end
		'#<(h[0-9]+)[^>]*>(.*)</\\1>#siU',                    // headings
		'#<br.*>#siU',                                        // <br> to newline
		'#<a name=[^>]*>(.*)</a>#siU',                         // kill named anchors
		'#\[url=(\'|"|&quot;|)<A href="(.*)/??">\\2/??</A>#siU'						// strip linked URLs from manually entered [url] tags (generic)
	);
	$pregreplace = array
	(
		#'<DIV>',                                             // multiple <DIV>s at string start
		#'</DIV>',                                            // multiple </DIV>s at string end
		is_bbcode_tag_allowed('b') ? "[B]\\2[/B]\n\n" : "\\2\n\n", // headings
		"\n",                                                 // <br> to newline
		'\1',                                                  // kill named anchors
		'[URL=$1$2'											//`strip linked URLs from manually entered [url] tags (generic)
	);

	// smilies
	$text = preg_replace_callback('#<img[^>]+smilieid="(\d+)".*>#siU', 
	function($matches)
	{
		return fetch_smilie_text($matches[1]);
	}, 
	$text);

	// img tag
	$text = preg_replace_callback('#<img[^>]+src=(\'|")(.*)(\\1).*>#siU', 
	function($matches)
	{
		return handle_wysiwyg_img($matches[2]);
	}, 
	$text);

	// strip html from php tags
	$text = preg_replace_callback('#\[(html|php)\]((?>[^\[]+?|(?R)|.))*\[/\\1\]#siU', 
	function($matches)
	{
		return strip_tags_callback($matches[0]);
	}, 
	$text);

	// Process the rest
	$text = preg_replace($pregfind, $pregreplace, $text);

	// recursive code parsers
	$text = parse_wysiwyg_recurse('b', $text, 'parse_wysiwyg_code_replacement', 'b');
	$text = parse_wysiwyg_recurse('strong', $text, 'parse_wysiwyg_code_replacement', 'b');
	$text = parse_wysiwyg_recurse('i', $text, 'parse_wysiwyg_code_replacement', 'i');
	$text = parse_wysiwyg_recurse('em', $text, 'parse_wysiwyg_code_replacement', 'i');
	$text = parse_wysiwyg_recurse('u', $text, 'parse_wysiwyg_code_replacement', 'u');
	$text = parse_wysiwyg_recurse('a', $text, 'parse_wysiwyg_anchor');
	$text = parse_wysiwyg_recurse('font', $text, 'parse_wysiwyg_font');
	$text = parse_wysiwyg_recurse('blockquote', $text, 'parse_wysiwyg_code_replacement', 'indent');
	$text = parse_wysiwyg_recurse('ol', $text, 'parse_wysiwyg_list');
	$text = parse_wysiwyg_recurse('ul', $text, 'parse_wysiwyg_list');
	$text = parse_wysiwyg_recurse('div', $text, 'parse_wysiwyg_div');
	$text = parse_wysiwyg_recurse('span', $text, 'parse_wysiwyg_span');
	$text = parse_wysiwyg_recurse('s', $text, 'parse_wysiwyg_code_replacement', 's');
	$text = parse_wysiwyg_recurse('strike', $text, 'parse_wysiwyg_code_replacement', 's');

	// ugly ugly hack, but allow p's to be treated as 2 line breaks in some situations
	$GLOBALS['p_two_linebreak'] = $p_two_linebreak;
	$text = parse_wysiwyg_recurse('p', $text, 'parse_wysiwyg_paragraph');

	// regex find / replace #2
	$pregfind = array(
		'#<li>(.*)((?=<li>)|</li>)#iU',    // fix some list issues
		'#<p></p>#i',                      // kill empty <p> tags
		'#<p.*>#iU',                       // kill any extra <p> tags
	);
	$pregreplace = array(
		"\\1\n",                           // fix some list issues
		'',                                // kill empty <p> tags
		"\n",                              // kill any extra <p> tags
	);

	$text = preg_replace($pregfind, $pregreplace, $text);

	// simple tag removals; mainly using PCRE for case insensitivity and /?
	$text = preg_replace('#</?(A|LI|FONT|IMG)>#siU', '', $text);

	// basic string replacements #2; don't replace &quot; because browsers don't auto-encode quotes
	$strfind = array
	(
		'&lt;',       // un-htmlspecialchars <
		'&gt;',       // un-htmlspecialchars >
		'&amp;',      // un-htmlspecialchars &
	);
	$strreplace = array
	(
		'<',          // un-htmlspecialchars <
		'>',          // un-htmlspecialchars >
		'&',          // un-htmlspecialchars &
	);

	if (is_array($vbulletin->smiliecache))
	{
		global $vbulletin;

		foreach ($vbulletin->smiliecache AS $smilie)
		{
			// [IMG]images/smilies/frown.gif[/IMG]
			$strfind[] = '[IMG]' . $smilie['smiliepath'] . '[/IMG]';
			$strreplace[] = $smilie['smilietext'];

			// [IMG]http://domain.com/forum/images/smilies/frown.gif[/IMG]
			$strfind[] = '[IMG]' . create_full_url($smilie['smiliepath']) . '[/IMG]';
			$strreplace[] = $smilie['smilietext'];
		}
	}

	$text = str_replace($strfind, $strreplace, $text);

	if (is_browser('mozilla'))
	{
		// mozilla treats line breaks before/after lists a little differently from IE (see #5774)
		$text = preg_replace('#\[(list)#i', "\n[\\1", $text);
		$text = preg_replace('#\[(/list)\]#i', "[\\1]\n", $text);
	}

	$text = preg_replace('#(?<!\r|\n|^)\[(/list|list|\*)\]#i', "\n[\\1]", $text);

	// replace advanced URL tags that should actually be basic ones
	$text = preg_replace('#\[URL=("|\'|)(.*)\\1\]\\2\[/URL\]#siU', '[URL]$2[/URL]', $text);

	// strip redundant alignment tag ([left] or [right])
	// commentted out because bad HTML can do bad things to this (nested alignments)
	/*global $stylevar;
	$redundanttag = iif($stylevar['textdirection'] == 'ltr', 'left', 'right');
	$text = preg_replace('#\[' . $redundanttag . '\](.*)(\r\n|\n|\r)??\[/' . $redundanttag . '\]#siU', '\\1', $text);*/

	($hook = vBulletinHook::fetch_hook('wysiwyg_parse_complete')) ? eval($hook) : false;

	// debug code
	$vbulletin->input->clean_gpc('r', 'showhtml', TYPE_BOOL);
	if ($vbulletin->debug AND $vbulletin->GPC['showhtml'])
	{
		$GLOBALS['header'] .= "<table class=\"tborder\" cellpadding=\"4\" cellspacing=\"1\" width=\"100%\">
		<tr><td class=\"thead\">WYSIWYG HTML</td></tr>
		<tr><td class=\"alt1\">" . nl2br(htmlspecialchars_uni($otext)) . "</td></tr>
		<tr><td class=\"thead\">Parsed BBcode</td></tr>
		<tr><td class=\"alt1\">" . nl2br(htmlspecialchars_uni($text)) . "</td></tr>\n</table>";
	}

	// return parsed text
	return trim($text);
}

/**
* Determines if the specified BB code tag is globally enabled.
*
* @param	string	Tag name
*
* @return	bool
*/
function is_bbcode_tag_allowed($tag)
{
	global $vbulletin;

	$flag_value = 0;

	switch (strtolower($tag))
	{
		case 'b':
		case 'i':
		case 'u':
		case 's':
			$flag_value = ALLOW_BBCODE_BASIC;
			break;

		case 'color':
			$flag_value = ALLOW_BBCODE_COLOR;
			break;

		case 'size':
			$flag_value = ALLOW_BBCODE_SIZE;
			break;

		case 'font':
			$flag_value = ALLOW_BBCODE_FONT;
			break;

		case 'left':
		case 'right':
		case 'center':
			$flag_value = ALLOW_BBCODE_ALIGN;
			break;

		case 'list':
			$flag_value = ALLOW_BBCODE_LIST;
			break;

		case 'indent':
			// allowed if either is enabled
			$flag_value = ALLOW_BBCODE_ALIGN | ALLOW_BBCODE_LIST;
			break;

		case 'email':
		case 'url':
		case 'thread':
		case 'post':
			$flag_value = ALLOW_BBCODE_URL;
			break;

		case 'php':
			$flag_value = ALLOW_BBCODE_PHP;
			break;

		case 'code':
			$flag_value = ALLOW_BBCODE_CODE;
			break;

		case 'html':
			$flag_value = ALLOW_BBCODE_HTML;
			break;

		default:
			return true;
	}

	return ($vbulletin->options['allowedbbcodes'] & $flag_value ? true : false);
}

// ###################### Start handle_wysiwyg_img #######################
// named differently since it's not parsed via parse_wysiwyg_recurse
function handle_wysiwyg_img($img_url)
{
	$img_url = str_replace('\\"', '"', $img_url);

	if (!preg_match('#^https?://#i', $img_url))
	{
		// relative URL, prefix it with the URL to this board
		$img_url = create_full_url($img_url);
	}

	return '[IMG]' . $img_url . '[/IMG]';
}

// ###################### Start parse_style_attribute #######################
function parse_style_attribute($tagoptions, &$prependtags, &$appendtags)
{
	$searchlist = array(
		array('tag' => 'left', 'option' => false, 'regex' => '#text-align:\s*(left);?#i'),
		array('tag' => 'center', 'option' => false, 'regex' => '#text-align:\s*(center);?#i'),
		array('tag' => 'right', 'option' => false, 'regex' => '#text-align:\s*(right);?#i'),
		array('tag' => 'color', 'option' => true, 'regex' => '#(?<![a-z0-9-])color:\s*([^;]+);?#i', 'match' => 1),
		array('tag' => 'font', 'option' => true, 'regex' => '#font-family:\s*(\'|)([^;,\']+)\\1[^;]*;?#i', 'match' => 2),
		array('tag' => 'b', 'option' => false, 'regex' => '#font-weight:\s*(bold);?#i'),
		array('tag' => 'i', 'option' => false, 'regex' => '#font-style:\s*(italic);?#i'),
		array('tag' => 'u', 'option' => false, 'regex' => '#text-decoration:\s*(underline);?#i'),
		array('tag' => 's', 'option' => false, 'regex' => '#text-decoration:\s*(line-through);?#i')
	);

	$style = parse_wysiwyg_tag_attribute('style=', $tagoptions);

	$style = preg_replace_callback(
		'#(?<![a-z0-9-])color:\s*rgb\((\d+),\s*(\d+),\s*(\d+)\)(;?)#i',
		function ($matches) 
		{
			return sprintf("color: #%02X%02X%02X" . $matches[4], $matches[1], $matches[2], $matches[3]);
		},
		$style
	);

	foreach ($searchlist AS $searchtag)
	{
		if (!is_bbcode_tag_allowed($searchtag['tag']))
		{
			continue;
		}

		if (preg_match($searchtag['regex'], $style, $matches))
		{
			$prependtags .= '[' . strtoupper($searchtag['tag']) . iif($searchtag['option'] == true, '=' . $matches["$searchtag[match]"]) . ']';
			$appendtags = '[/' . strtoupper($searchtag['tag']) . "]$appendtags";
		}
	}
}

// ###################### Start parse_wysiwyg_anchor #######################
function parse_wysiwyg_anchor($aoptions, $text)
{
	global $vbulletin;

	$href = parse_wysiwyg_tag_attribute('href=', $aoptions);

	if (!trim($href))
	{
		return parse_wysiwyg_recurse('a', $text, 'parse_wysiwyg_anchor');
	}

	if (substr($href, 0, 7) == 'mailto:')
	{
		$tag = 'email';
		$href = substr($href, 7);
	}
	else
	{
		$tag = 'url';
		if (!preg_match('#^[a-z0-9]+:#i', $href))
		{
			// relative URL, prefix it with the URL to this board
			$href = create_full_url($href);
		}
	}
	$tag = strtoupper($tag);

	if ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL)
	{
		return "[$tag=\"$href\"]" . parse_wysiwyg_recurse('a', $text, 'parse_wysiwyg_anchor') . "[/$tag]";
	}
	else
	{
		// can't auto link, return a plaintext version
		$inner_text = parse_wysiwyg_recurse('a', $text, 'parse_wysiwyg_anchor');
		if ($inner_text != $href)
		{
			return "$inner_text ($href)";
		}
		else
		{
			return $href;
		}
	}
}

// ###################### Start WYSIWYG_paraparser #######################
function parse_wysiwyg_paragraph($poptions, $text)
{
	$style = parse_wysiwyg_tag_attribute('style=', $poptions);
	$align = parse_wysiwyg_tag_attribute('align=', $poptions);

	// only allow left/center/right alignments
	switch ($align)
	{
		case 'left':
		case 'center':
		case 'right':
			break;
		default:
			$align = '';
	}

	$align = strtoupper($align);

	$prepend = '';
	$append = '';

	parse_style_attribute($poptions, $prepend, $append);
	if ($align AND is_bbcode_tag_allowed($align))
	{
		$prepend .= "[$align]";
		$append .= "[/$align]";
	}

	// ick ick -- hack to allow p's to be have 2 line breaks
	global $p_two_linebreak;
	if ($p_two_linebreak)
	{
		$append .= "\n\n";
	}
	else
	{
		$append .= "\n";
	}

	return $prepend . parse_wysiwyg_recurse('p', $text, 'parse_wysiwyg_paragraph') . $append;
}

// ###################### Start parse_wysiwyg_span #######################
function parse_wysiwyg_span($spanoptions, $text)
{

	$prependtags = '';
	$appendtags = '';
	parse_style_attribute($spanoptions, $prependtags, $appendtags);

	return $prependtags . parse_wysiwyg_recurse('span', $text, 'parse_wysiwyg_span') . $appendtags;
}

// ###################### Start WYSIWYG_divparser #######################
function parse_wysiwyg_div($divoptions, $text)
{
	$prepend = '';
	$append = '';

	parse_style_attribute($divoptions, $prepend, $append);
	$align = parse_wysiwyg_tag_attribute('align=', $divoptions);

	// only allow left/center/right alignments
	switch ($align)
	{
		case 'left':
		case 'center':
		case 'right':
			break;
		default:
			$align = '';
	}

	$align = strtoupper($align);

	if ($align AND is_bbcode_tag_allowed($align))
	{
		$prepend .= "[$align]";
		$append .= "[/$align]";
	}
	$append .= "\n";

	return $prepend . parse_wysiwyg_recurse('div', $text, 'parse_wysiwyg_div') . $append;
}

// ###################### Start WYSIWYG_listelementparser #######################
function parse_wysiwyg_list_element($listoptions, $text)
{
	if (!is_bbcode_tag_allowed('list'))
	{
		return "$text\n";
	}

	return '[*]' . rtrim($text);
}

// ###################### Start WYSIWYG_listparser #######################
function parse_wysiwyg_list($listoptions, $text, $tagname)
{
	$longtype = parse_wysiwyg_tag_attribute('style=', $listoptions);
	$listtype = trim(preg_replace('#"?LIST-STYLE-TYPE:\s*([a-z0-9_-]+);?"?#si', '\\1', $longtype));
	if (empty($listtype) AND $tagname == 'ol')
	{
		$listtype = 'decimal';
	}

	$text = preg_replace('#<li>((?'.'>[^[<]+?|(?!</li).)*)(?=</?ol|</?ul|<li|\[list|\[/list)#siU', '<li>\\1</li>', $text);
	$text = parse_wysiwyg_recurse('li', $text, 'parse_wysiwyg_list_element');

	if (!is_bbcode_tag_allowed('list'))
	{
		return $text;
	}

	$validtypes = array(
		'upper-alpha' => 'A',
		'lower-alpha' => 'a',
		'upper-roman' => 'I',
		'lower-roman' => 'i',
		'decimal' => '1'
	);
	if (!isset($validtypes["$listtype"]))
	{
		$opentag = '[LIST]'; // default to bulleted
	}
	else
	{
		$opentag = '[LIST=' . $validtypes[$listtype] . ']';
	}
	return $opentag . parse_wysiwyg_recurse($tagname, $text, 'parse_wysiwyg_list') . '[/LIST]';
}

// ###################### Start WYSIWYG_fontparser #######################
function parse_wysiwyg_font($fontoptions, $text)
{
	$tags = array(
		'font' => 'face=',
		'size' => 'size=',
		'color' => 'color='
	);
	$prependtags = '';
	$appendtags = '';

	$fontoptionlen = strlen($fontoptions);

	foreach ($tags AS $vbcode => $locate)
	{
		$optionvalue = parse_wysiwyg_tag_attribute($locate, $fontoptions);
		if ($optionvalue)
		{
			$vbcode = strtoupper($vbcode);
			$prependtags .= "[$vbcode=$optionvalue]";
			$appendtags = "[/$vbcode]$appendtags";
		}
	}

	parse_style_attribute($fontoptions, $prependtags, $appendtags);

	return $prependtags . parse_wysiwyg_recurse('font', $text, 'parse_wysiwyg_font') . $appendtags;
}

// ###################### Start WYSIWYG_bbcodereplace #######################
function parse_wysiwyg_code_replacement($options, $text, $tagname, $parseto)
{
	$useoptions = array(); // array of (key) tag name; (val) option to read. If tag name isn't found, no option is used

	if (trim($text) == '')
	{
		return '';
	}

	if (!is_bbcode_tag_allowed($parseto))
	{
		return $text;
	}

	$parseto = strtoupper($parseto);

	if (empty($useoptions["$tagname"]))
	{
		$text = parse_wysiwyg_recurse($tagname, $text, 'parse_wysiwyg_code_replacement', $parseto);
		return "[$parseto]{$text}[/$parseto]";
	}
	else
	{
		$optionvalue = parse_wysiwyg_tag_attribute($useoptions["$tagname"], $options);
		if ($optionvalue)
		{
			return "[$parseto=$optionvalue]{$text}[/$parseto]";
		}
		else
		{
			return "[$parseto]{$text}[/$parseto]";
		}
	}
}

// ###################### Start WYSIWYG_recursiveparser #######################
function parse_wysiwyg_recurse($tagname, $text, $functionhandle, $extraargs = '')
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

// ###################### Start WYSIWYG_readoption #######################
function parse_wysiwyg_tag_attribute($option, $text)
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
		return '';
	}
}

// ###################### Start strip_tags_callback #######################
function strip_tags_callback($text)
{
	$text = str_replace('\\"', '"', $text);
	return strip_tags($text, '<p>');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
