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

error_reporting(E_ALL & ~E_NOTICE);

define('ALLOW_BBCODE_BASIC', 1);
define('ALLOW_BBCODE_COLOR', 2);
define('ALLOW_BBCODE_SIZE', 4);
define('ALLOW_BBCODE_FONT', 8);
define('ALLOW_BBCODE_ALIGN', 16);
define('ALLOW_BBCODE_LIST', 32);
define('ALLOW_BBCODE_URL', 64);
define('ALLOW_BBCODE_CODE', 128);
define('ALLOW_BBCODE_PHP', 256);
define('ALLOW_BBCODE_HTML', 512);

// ###################### Start fetch_bbcode_html_colors #######################
// Workaround for limitation of the Zend Engine with global variables from a
// file included within a function
function fetch_bbcode_html_colors()
{
	global $bbcode_html_colors;
	$bbcode_html_colors = array
	(
		'attribs'	=> '#0000FF',
		'table'		=> '#008080',
		'form'		=> '#FF8000',
		'script'	=> '#800000',
		'style'		=> '#800080',
		'a'			=> '#008000',
		'img'		=> '#800080',
		'if'		=> '#FF0000',
		'default'	=> '#000080'
	);
}
fetch_bbcode_html_colors();

// ###################### Start get_bbcode_definitions #######################
// gets a list of the hard-coded bbcodes, checking options for each bbcode enabled
function fetch_bbcode_definitions()
{
	global $vboptions, $vbphrase, $session;

	// initialise arrays
	$bbcodes = array
	(
		'standard' => array('find' => array(), 'replace' => array(), 'recurse' => array()),
		'custom' => array('find' => array(), 'replace' => array(), 'recurse' => array())
	);

	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_BASIC)
	{
		// [B]
		$bbcodes['standard']['find']['[b]'] = '#\[b\](.*)\[/b\]#esiU';
		$bbcodes['standard']['replace']['[b]'] = "handle_bbcode_parameter('\\1','" . str_replace("'", "\'", '<b>\1</b>') . "')";
		$bbcodes['standard']['recurse']['b'][0] = array('replace' => 'b');
		// [I]
		$bbcodes['standard']['find']['[i]'] = '#\[i\](.*)\[/i\]#esiU';
		$bbcodes['standard']['replace']['[i]'] = "handle_bbcode_parameter('\\1','" . str_replace("'", "\'", '<i>\1</i>') . "')";
		$bbcodes['standard']['recurse']['i'][0] = array('replace' => 'i');
		// [U]
		$bbcodes['standard']['find']['[u]'] = '#\[u\](.*)\[/u\]#esiU';
		$bbcodes['standard']['replace']['[u]'] = "handle_bbcode_parameter('\\1','" . str_replace("'", "\'", '<u>\1</u>') . "')";
		$bbcodes['standard']['recurse']['u'][0] = array('replace' => 'u');
	}
	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_COLOR)
	{
		// [COLOR=XXX]
		$bbcodes['standard']['find']['[color='] = '#\[color=(&quot;|"|\'|)(\#?\w+)\\1\](.*)\[/color\]#esiU'; // {option} allows # and alpha-numeric
		$bbcodes['standard']['replace']['[color='] = "handle_bbcode_parameter('\\3','" . str_replace("'", "\'", '<font color="\2">\3</font>') . "')";
		$bbcodes['standard']['recurse']['color'][1] = array('replace_html' => '<font color="\5">\7</font>');
	}
	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_SIZE)
	{
		// [SIZE=XXX]
		$bbcodes['standard']['find']['[size='] = '#\[size=(&quot;|"|\'|)([0-9\+\-]+)\\1\](.*)\[/size\]#esiU'; // {option} allows +, - and numeric
		$bbcodes['standard']['replace']['[size='] = "handle_bbcode_parameter('\\3','" . str_replace("'", "\'", '<font size="\2">\3</font>') . "')";
		$bbcodes['standard']['recurse']['size'][1] = array('replace_html' => '<font size="\5">\7</font>');
	}
	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_FONT)
	{
		// [FONT=XXX]
		$bbcodes['standard']['find']['[font='] = '#\[font=(&quot;|"|\'|)([^["`\':=<>&]+)\\1\](.*)\[/font\]#esiU'; // {option} allows single quotes, spaces, commas, underscores, dashes and alpha-numeric
		$bbcodes['standard']['replace']['[font='] = "handle_bbcode_parameter('\\3','" . str_replace("'", "\'", '<font face="\2">\3</font>') . "')";
		$bbcodes['standard']['recurse']['font'][1] = array('replace_html' => '<font face="\5">\7</font>');
	}
	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_ALIGN)
	{
		$blocktag = iif($vboptions['divnotpara'], 'div', 'p');
		// [LEFT]
		$bbcodes['standard']['find']['[left]'] = '#\[left\](.*)\[/left\](<br />|<br>)??#esiU';
		$bbcodes['standard']['replace']['[left]'] = "handle_bbcode_parameter('\\1','" . str_replace("'", "\'", '<' . $blocktag . ' align="left">\1</' . $blocktag . '>') . "')";
		$bbcodes['standard']['recurse']['left'][0] = array('replace_html' => "<$blocktag align=\"left\">\\7</$blocktag>");
		// [CENTER]
		$bbcodes['standard']['find']['[center]'] = '#\[center\](.*)\[/center\](<br />|<br>)??#esiU';
		$bbcodes['standard']['replace']['[center]'] = "handle_bbcode_parameter('\\1','" . str_replace("'", "\'", '<' . $blocktag . ' align="center">\1</' . $blocktag . '>') . "')";
		$bbcodes['standard']['recurse']['center'][0] = array('replace_html' => "<$blocktag align=\"center\">\\7</$blocktag>");
		// [RIGHT]
		$bbcodes['standard']['find']['[right]'] = '#\[right\](.*)\[/right\](<br />|<br>)??#esiU';
		$bbcodes['standard']['replace']['[right]'] = "handle_bbcode_parameter('\\1','" . str_replace("'", "\'", '<' . $blocktag . ' align="right">\1</' . $blocktag . '>') . "')";
		$bbcodes['standard']['recurse']['right'][0] = array('replace_html' => "<$blocktag align=\"right\">\\7</$blocktag>");
		// [INDENT]
		$bbcodes['standard']['find']['[indent]'] = '#\[indent\](.*)\[/indent\](<br />|<br>)??#esiU';
		$bbcodes['standard']['replace']['[indent]'] = "handle_bbcode_parameter('\\1','" . str_replace("'", "\'", '<blockquote>\1</blockquote>') . "')";
		$bbcodes['standard']['recurse']['indent'][0] = array('replace' => 'blockquote');
	}
	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_PHP)
	{
		// [PHP] - this is the first custom tag because it does weird things to HTML
		$bbcodes['custom']['find']['[php]'] = '#\[php\](<br>|<br />|\r\n|\n|\r)??(.*)(<br>|<br />|\r\n|\n|\r)??\[/php\]#esiU';
		$bbcodes['custom']['replace']['[php]'] = "handle_bbcode_php('\\2')";
		$bbcodes['custom']['recurse']['php'][0] = array('handler' => 'handle_bbcode_php');
	}
	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_LIST)
	{
		// [LIST=XXX]
		// #$bbcodes['standard']['find']['[list='] = '#\[list=(&quot;|"|\'|)(.*)\\1\](.*)\[/list(=\\1\\2\\1)?\]#esiU';
		// $bbcodes['standard']['replace']['[list='] = "handle_bbcode_list('\\3', '\\2')";
		$bbcodes['standard']['find']['[list='] = '#\s*(\[list=(&quot;|"|\'|)([^\]]*)\\2\](.*)\[/list(=\\2\\3\\2)?\])\s*#siUe';
		$bbcodes['standard']['replace']['[list='] = "handle_bbcode_list('\\1')";
		$bbcodes['standard']['recurse']['list'][1] = array('handler' => 'handle_bbcode_list');
		// [LIST]
		// $bbcodes['standard']['find']['[list]'] = '#\[list\](.*)\[/list\]#esiU';
		// $bbcodes['standard']['replace']['[list]'] = "handle_bbcode_list('\\1')";
		$bbcodes['standard']['find']['[list]'] = '#\s*(\[list\](.*)\[/list\])\s*#esi';
		$bbcodes['standard']['replace']['[list]'] = "handle_bbcode_list('\\1')";
		$bbcodes['standard']['recurse']['list'][0] = array('handler' => 'handle_bbcode_list');
		// [INDENT] (repeat from align section as indent is needed for lists)
		$bbcodes['standard']['find']['[indent]'] = '#\[indent\](.*)\[/indent\]#esiU';
		$bbcodes['standard']['replace']['[indent]'] = "handle_bbcode_parameter('\\1','" . str_replace("'", "\'", '<blockquote>\1</blockquote>') . "')";
		$bbcodes['standard']['recurse']['indent'][0] = array('replace' => 'blockquote');
	}
	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_URL)
	{
		// [EMAIL]
		$bbcodes['standard']['find']['[email]'] = '#\[email\]([^<>]+)\[/email\]#esiU';
		$bbcodes['standard']['replace']['[email]'] = "handle_bbcode_url('\\1', '', 'email')";
		$bbcodes['standard']['recurse']['email'][0] = array('handler' => 'handle_bbcode_url');
		// [EMAIL=XXX]
		$bbcodes['standard']['find']['[email='] = '#\[email=(&quot;|"|\'|)([^<>]+)\\1\](.*)\[/email\]#esiU';
		$bbcodes['standard']['replace']['[email='] = "handle_bbcode_url('\\3', '\\2', 'email')";
		$bbcodes['standard']['recurse']['email'][1] = array('handler' => 'handle_bbcode_url');
		// [URL]
		$bbcodes['standard']['find']['[url]'] = '#\[url\](.*)\[/url\]#esiU';
		$bbcodes['standard']['replace']['[url]'] = "handle_bbcode_url('\\1', '', 'url')";
		$bbcodes['standard']['recurse']['url'][0] = array('handler' => 'handle_bbcode_url');
		// [URL=XXX]
		$bbcodes['standard']['find']['[url='] = '#\[url=(&quot;|"|\'|)(.*)\\1\](.*)\[/url\]#esiU';
		$bbcodes['standard']['replace']['[url='] = "handle_bbcode_url('\\3', '\\2', 'url')";
		$bbcodes['standard']['recurse']['url'][1] = array('handler' => 'handle_bbcode_url');
		// [THREAD]
		$bbcodes['custom']['find']['[thread]'] = '#\[thread\]\s*(\d+)\s*\[/thread\]#esiU';
		$bbcodes['custom']['replace']['[thread]'] = "handle_bbcode_parameter('\\1', '" . str_replace("'", "\'", '<a href="showthread.php?' . $session['sessionurl'] . 't=\1" target="_blank">' . $vboptions['bburl'] . '/showthread.php?t=\1</a>') . "')";
		$bbcodes['custom']['recurse']['thread'][0] = array('replace_html' => "<a href=\"showthread.php?$session[sessionurl]t=\\7\" target=\"_blank\">$vboptions[bburl]/showthread.php?t=\\7</a>");
		// [THREAD=XXX]
		$bbcodes['custom']['find']['[thread='] = '#\[thread=(\d+)\](.*)\[/thread\]#esiU';
		$bbcodes['custom']['replace']['[thread='] = "handle_bbcode_parameter('\\2', '" . str_replace("'", "\'", '<a href="showthread.php?' . $session['sessionurl'] . 't=\1" target="_blank" title="' . htmlspecialchars_uni($vboptions['bbtitle']) . ' - ' . $vbphrase['thread'] . ' \1">\2</a>') . "')";
		$bbcodes['custom']['recurse']['thread'][1] = array('replace_html' => "<a href=\"showthread.php?$session[sessionurl]t=\\5\" title=\"" . htmlspecialchars_uni($vboptions['bbtitle']) . " - $vbphrase[thread] \\5\" target=\"_blank\">\\7</a>");
		// [POST]
		$bbcodes['custom']['find']['[post]'] = '#\[post\]\s*(\d+)\s*\[/post\]#esiU';
		$bbcodes['custom']['replace']['[post]'] = "handle_bbcode_parameter('\\1', '" . str_replace("'", "\'", '<a href="showthread.php?' . $session['sessionurl'] . 'p=\1#post\1" target="_blank">' . $vboptions['bburl'] . '/showthread.php?p=\1</a>') . "')";
		$bbcodes['custom']['recurse']['post'][0] = array('replace_html' => "<a href=\"showthread.php?$session[sessionurl]p=\\7#post\\7\" target=\"_blank\">$vboptions[bburl]/showthread.php?p=\\7</a>");
		// [POST=XXX]
		$bbcodes['custom']['find']['[post='] = '#\[post=(\d+)\](.*)\[/post\]#esiU';
		$bbcodes['custom']['replace']['[post='] = "handle_bbcode_parameter('\\2', '" . str_replace("'", "\'", '<a href="showthread.php?' . $session['sessionurl'] . 'p=\1#post\1" target="_blank" title="' . htmlspecialchars_uni($vboptions['bbtitle']) . ' - ' . $vbphrase['post'] . ' \1">\2</a>') . "')";
		$bbcodes['custom']['recurse']['post'][1] = array('replace_html' => "<a href=\"showthread.php?$session[sessionurl]p=\\5#post\\5\" title=\"" . htmlspecialchars_uni($vboptions['bbtitle']) . " - $vbphrase[post] \\5\" target=\"_blank\">\\7</a>");
	}
	// see above for [php] tag
	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_CODE)
	{
		//[CODE]
		$bbcodes['custom']['find']['[code]'] = '#\[code\](<br>|<br />|\r\n|\n|\r)??(.*)(<br>|<br />|\r\n|\n|\r)??\[/code\]#esiU';
		$bbcodes['custom']['replace']['[code]'] = "handle_bbcode_code('\\2')";
		$bbcodes['custom']['recurse']['code'][0] = array('handler' => 'handle_bbcode_code');
	}
	if ($vboptions['allowedbbcodes'] & ALLOW_BBCODE_HTML)
	{
		// [HTML]
		$bbcodes['custom']['find']['[html]'] = '#\[html\](<br>|<br />|\r\n|\n|\r)??(.*)(<br>|<br />|\r\n|\n|\r)??\[/html\]#esiU';
		$bbcodes['custom']['replace']['[html]'] = "handle_bbcode_html('\\2')";
		$bbcodes['custom']['recurse']['html'][0] = array('handler' => 'handle_bbcode_html');
	}

	// [QUOTE]
	$bbcodes['custom']['find']['[quote]'] = '#\[quote\](<br>|<br />|\r\n|\n|\r)??(.*)(<br>|<br />|\r\n|\n|\r)??\[/quote\]#esiU';
	$bbcodes['custom']['replace']['[quote]'] = "handle_bbcode_quote('\\2')";
	$bbcodes['custom']['recurse']['quote'][0] = array('handler' => 'handle_bbcode_quote');

	// [QUOTE=XXX]
	$bbcodes['custom']['find']['[quote='] = '#\[quote=(&quot;|"|\'|)(.*)\\1\](<br>|<br />|\r\n|\n|\r)??(.*)(<br>|<br />|\r\n|\n|\r)??\[/quote\]#esiU';
	$bbcodes['custom']['replace']['[quote='] = "handle_bbcode_quote('\\4', '\\2')";
	$bbcodes['custom']['recurse']['quote'][1] = array('handler' => 'handle_bbcode_quote');

	// [HIGHLIGHT]
	$bbcodes['custom']['find']['[highlight]'] = '#\[highlight\](.*)\[/highlight\]#esiU';
	$bbcodes['custom']['replace']['[highlight]'] = "handle_bbcode_parameter('\\1', '" . str_replace("'", "\'", '<span class="highlight">\1</span>') . "')";
	$bbcodes['custom']['recurse']['highlight'][0] = array('replace_html' => "<span class=\"highlight\">\\7</span>");

	return $bbcodes;
}

// ###################### Start bbcodeparse #######################
function parse_bbcode($bbcode, $forumid = 0, $allowsmilie = 1, $isimgcheck = 0, $parsedtext = '', $parsedhasimages = 0, $iswysiwyg = 0)
{
	// $parsedtext contains text that has already been turned into HTML and just needs images checking
	// $parsedhasimages specifies if the text has images in that need parsing

	global $vboptions, $parsed_postcache;

	$donl2br = 1;

	if (empty($forumid))
	{
		$forumid = 'nonforum';
	}

	switch($forumid)
	{
		// parse private message
		case 'privatemessage':
			$dohtml = $vboptions['privallowhtml'];
			$dobbcode = $vboptions['privallowbbcode'];
			$dobbimagecode = $vboptions['privallowbbimagecode'];
			$dosmilies = $vboptions['privallowsmilies'];
			break;

		// parse user note
		case 'usernote':
			$dohtml = $vboptions['unallowhtml'];
			$dobbcode = $vboptions['unallowvbcode'];
			$dobbimagecode = $vboptions['unallowimg'];
			$dosmilies = $vboptions['unallowsmilies'];
			break;

		// parse non-forum item
		case 'nonforum':
			$dohtml = $vboptions['allowhtml'];
			$dobbcode = $vboptions['allowbbcode'];
			$dobbimagecode = $vboptions['allowbbimagecode'];
			$dosmilies = $vboptions['allowsmilies'];
			if ($allowsmilie != 1)
			{
				$dosmilies = $allowsmilie;
			}
			break;

		case 'announcement':
			global $post;
			$dohtml = $post['allowhtml'];
			if ($dohtml)
			{
				$donl2br = 0;
			}
			$dobbcode = $post['allowbbcode'];
			$dobbimagecode = $post['allowbbcode'];
			$dosmilies = $allowsmilie;
			break;

		// parse forum item
		default:
			$forum = fetch_foruminfo($forumid);
			$dohtml = $forum['allowhtml'];
			$dobbimagecode = $forum['allowimages'];
			$dosmilies = $forum['allowsmilies'];
			if ($allowsmilie != 1)
			{
				$dosmilies = $allowsmilie;
			}
			$dobbcode = $forum['allowbbcode'];
			break;
	}

	if (!empty($parsedtext))
	{
		if ($parsedhasimages)
		{
			return handle_bbcode_img($parsedtext, $dobbimagecode);
		}
		else
		{
			return $parsedtext;
		}
	}
	else
	{
		if ($isimgcheck)
		{ // do this since we're only checking for smilies and IMG code
			$dobbcode = 0;
		}
		return parse_bbcode2($bbcode, $dohtml, $dobbimagecode, $dosmilies, $dobbcode, $iswysiwyg, $donl2br);
	}
}

// ###################### Start checkparam #######################
// called by the preg_replace for custom bbcodes - ensures that
// users can't get around censor text by adding empty bbcodes
// such as 'c[b][/b]ensoredword' into their messages
function handle_bbcode_parameter($param, $return)
{
	if (trim($param) != '')
	{
		return str_replace('\\"', '"', $return);
	}
}

// ###################### Start handle_custom_bbcode #######################
function handle_custom_bbcode($param, $option, $return)
{
	if (trim($param) == '')
	{
		return '';
	}

	$param = str_replace('\\"', '"', $param);
	$return = str_replace('\\"', '"', $return);
	$option = str_replace(array('\\"', '['), array('"', '&#91;'), $option);

	$return = preg_replace('#%(?!\d+\$s)#', '%%', $return);
	return sprintf($return, $param, $option);
}

// ###################### Start bbcodeparse2 #######################
function parse_bbcode2($bbcode, $dohtml, $dobbimagecode, $dosmilies, $dobbcode, $iswysiwyg = 0, $donl2br = 1)
{
// parses text for vB code, smilies and censoring

	global $DB_site, $vboptions, $bbuserinfo, $templatecache, $smiliecache;
	global $html_allowed;

	if ($vboptions['wordwrap'] != 0 AND !$iswysiwyg)
	{
		$bbcode = fetch_word_wrapped_string($bbcode);
	}

	$html_allowed = true;
	// ********************* REMOVE HTML CODES ***************************
	if (!$dohtml)
	{
		/*static $html_find = array('&lt;', '&gt;', '<', '>');
		static $html_replace = array('&amp;lt;', '&amp;gt;', '&lt;','&gt;');

		$bbcode = str_replace($html_find, $html_replace, $bbcode);*/
		$bbcode = htmlspecialchars_uni($bbcode);
		$html_allowed = false;
	} // end html

	// ********************* PARSE SMILIES ***************************
	if ($dosmilies)
	{
		static $smilie_find, $smilie_replace;

		if (empty($smilie_find) OR empty($smilie_replace))
		{
			if (isset($smiliecache))
			{
				// we can get the smilies from the smiliecache php template
				DEVDEBUG('returning smilies from the template cache');
				if (is_array($smiliecache))
				{
					foreach ($smiliecache AS $smilie)
					{
						if (trim($smilie['smilietext']) != '')
						{
							if (!$dohtml)
							{
								$smilie_find[] = htmlspecialchars_uni(trim($smilie['smilietext']));
							}
							else
							{
								$smilie_find[] = trim($smilie['smilietext']);
							}
							// if you change this HTML tag, make sure you change the smilie remover in code/php/html tag handlers!
							if ($iswysiwyg)
							{
								$smilie_replace[] = "<img src=\"$smilie[smiliepath]\" border=\"0\" alt=\"\" title=\"$smilie[title]\" smilieid=\"$smilie[smilieid]\" class=\"inlineimg\" />";
							}
							else
							{
								$smilie_replace[] = "<img src=\"$smilie[smiliepath]\" border=\"0\" alt=\"\" title=\"$smilie[title]\" class=\"inlineimg\" />";
							}
						}
					}
				}
			}
			else
			{
				// we have to get the smilies from the database
				DEVDEBUG('querying smilies for parse_bbcode2();');
				$smilies = $DB_site->query("
					SELECT smilietext, smiliepath, smilieid FROM " . TABLE_PREFIX . "smilie
				");
				while ($smilie = $DB_site->fetch_array($smilies))
				{
					if(trim($smilie['smilietext']) != '')
					{
						if (!$dohtml)
						{
							$smilie_find[] = htmlspecialchars_uni(trim($smilie['smilietext']));
						}
						else
						{
							$smilie_find[] = trim($smilie['smilietext']);
						}
						// if you change this HTML tag, make sure you change the smilie remover in code/php/html tag handlers!
						if ($iswysiwyg)
							{
								$smilie_replace[] = "<img src=\"$smilie[smiliepath]\" border=\"0\" alt=\"\" title=\"$smilie[title]\" smilieid=\"$smilie[smilieid]\" class=\"inlineimg\" />";
							}
							else
							{
								$smilie_replace[] = "<img src=\"$smilie[smiliepath]\" border=\"0\" alt=\"\" title=\"$smilie[title]\" class=\"inlineimg\" />";
							}
					}
				}
			}
		}

		// str_replace the text using the smilie_find and smilie_replace arrays
		#$bbcode = str_replace($smilie_find, $smilie_replace, $bbcode);

		// alternative method to avoid parsing HTML entities as smilies:
		foreach($smilie_find AS $smiliekey => $smiliefind)
		{
			$bbcode = preg_replace('/(?<!&amp|&quot|&lt|&gt|&copy|&#[0-9]{1}|&#[0-9]{2}|&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5})' . preg_quote($smiliefind, '/') . '/s', $smilie_replace["$smiliekey"], $bbcode);
		}
	} // end smilies

	// do new lines
	$wysiwygtype = null;
	if ($iswysiwyg == 1)
	{
		$whitespacefind = array(
			'#(\r\n|\n|\r)?( )*(\[\*\]|\[/list|\[list|\[indent)#si',
			'#(/list\]|/indent\])( )*(\r\n|\n|\r)?#si'
		);
		$whitespacereplace = array(
			'\3',
			'\1'
		);
		$bbcode = preg_replace($whitespacefind, $whitespacereplace, $bbcode);

		if (is_browser('ie'))
		{
			$wysiwygtype = 'ie';

			// this fixes an issue caused by odd nesting of tags. This causes IE's
			// WYSIWYG editor to display the output as vB will display it
			$rematch_find = array(
				'#\[b\](.*)\[/b\]#siUe',
				'#\[i\](.*)\[/i\]#siUe',
				'#\[u\](.*)\[/u\]#siUe',
			);
			$rematch_replace = array(
				"bbcode_rematch_tags_wysiwyg('\\1', 'b')",
				"bbcode_rematch_tags_wysiwyg('\\1', 'i')",
				"bbcode_rematch_tags_wysiwyg('\\1', 'u')",
			);
			$bbcode = preg_replace($rematch_find, $rematch_replace, $bbcode);

			$bbcode = '<p style="margin:0px">' . preg_replace('#(\r\n|\n|\r)#', "</p>\n<p style=\"margin:0px\">", trim($bbcode)) . '</p>';
		}
		else
		{
			$bbcode = nl2br($bbcode);
			$wysiwygtype = 'moz_css';
		}
		$bbcode = preg_replace('#(\[list(=(&quot;|"|\'|)(.*)\\3)?\])(((?>[^\[]*?|(?R))|(?>.))*)(\[/list(=\\3\\4\\3)?\])#siUe', "remove_wysiwyg_breaks('\\0', \$wysiwygtype)", $bbcode);

		//$bbcode = preg_replace('#\[list#i', '</p>[list', $bbcode);
		//$bbcode = preg_replace('#\[/list(=(&quot;|"|\'|)[a-z0-9+]\\2)?](?!\[\*\])#i', '[/list\\1]<p style="margin:0px">', $bbcode);

		$bbcode = preg_replace('#<p style="margin:0px">\s*</p>(?!\s*\[list|$)#i', '<p style="margin:0px">&nbsp;</p>', $bbcode);
		$bbcode = str_replace('<p style="margin:0px"></p>', '', $bbcode);

		// convert tabs to four &nbsp;
		$bbcode = str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $bbcode);
	}
	// new lines to <br />
	else
	{
		$whitespacefind = array(
			'#(\r\n|\n|\r)?( )*(\[\*\]|\[/list|\[list|\[indent)#si',
			'#(/list\]|/indent\])( )*(\r\n|\n|\r)?#si'
		);
		$whitespacereplace = array(
			'\3',
			'\1'
		);
		$bbcode = preg_replace($whitespacefind, $whitespacereplace, $bbcode);

		if ($donl2br)
		{
			$bbcode = nl2br($bbcode);
		}
	}

	// ********************* PARSE BBCODE TAGS ***************************
	if ($dobbcode AND strpos($bbcode, '[') !== false AND strpos($bbcode, ']') !== false)
	{
		switch($vboptions['usebbcodeparserecurse'])
		{
			case 1:
				$parsefunc = 'parse_bbcode_recurse';
				break;

			case 0:
				$parsefunc = 'parse_bbcode_regex';
				break;

			default:
				$parsefunc = 'parse_bbcode_regexrecurse';
		}
		$bbcode = $parsefunc($bbcode, $iswysiwyg);

		if ($wysiwygtype == 'ie')
		{
			$bbcode = preg_replace('#<p style="margin:0px"><(p|div) align="([a-z]+)">(.*)</\\1></p>#siU', '<p style="margin:0px" align="\\2">\\3</p>', $bbcode);
		}
		if ($iswysiwyg)
		{
			// need to display smilies in code/php/html tags as literals
			$bbcode = preg_replace('#\[(code|php|html)\](.*)\[/\\1\]#siUe', "strip_smilies(str_replace('\\\"', '\"', '\\0'), true)", $bbcode);
		}
	}

	// parse out nasty active scripting codes
	static $global_find = array('/javascript:/si', '/about:/si', '/vbscript:/si', '/&(?![a-z0-9#]+;)/si');
	static $global_replace = array('javascript<b></b>:', 'about<b></b>:', 'vbscript<b></b>:', '&amp;');
	$bbcode = preg_replace($global_find, $global_replace, $bbcode);

	// run the censor
	$bbcode = fetch_censored_text($bbcode);
	$has_img_tag = contains_bbcode_img_tags($bbcode);

	// save the cached post
	global $stopsaveparsed, $parsed_postcache;
	if (!$stopsaveparsed AND $parsed_postcache['skip'] != true)
	{
		$parsed_postcache['text'] = $bbcode;
		$parsed_postcache['images'] = $has_img_tag;
	}

	// do [img] tags if the item contains images
	if(($dobbcode OR $dobbimagecode) AND $has_img_tag)
	{
		$bbcode = handle_bbcode_img($bbcode, $dobbimagecode);
	}

	return $bbcode;
}

// ###################### Start remove_wysiwyg_breaks #######################
function bbcode_rematch_tags_wysiwyg($innertext, $tagname)
{
	// This function replaces line breaks with [/tag]\n[tag].
	// It is intended to be used on text inside [tag] to fix an IE WYSIWYG issue.

	$innertext = str_replace('\"', '"', $innertext);
	return "[$tagname]" . preg_replace('#(\r\n|\n|\r)#', "[/$tagname]\n[$tagname]", $innertext) . "[/$tagname]";
}

// ###################### Start remove_wysiwyg_breaks #######################
function remove_wysiwyg_breaks($fulltext, $wysiwygtype = 'ie')
{
	$fulltext = str_replace('\"', '"', $fulltext);
	preg_match('#^(\[list(=(&quot;|"|\'|)(.*)\\3)?\])(.*?)(\[/list(=\\3\\4\\3)?\])$#siU', $fulltext, $matches);
	$prepend = $matches[1];
	$innertext = $matches[5];

	$find = array("</p>\n<p style=\"margin:0px\">", '<br />', '<br>');
	$replace = array("\n", "\n", "\n");
	$innertext = str_replace($find, $replace, $innertext);

	if ($wysiwygtype == 'ie')
	{
		return '</p>' . $prepend . $innertext . '[/list]<p style="margin:0px">';
	}
	else
	{
		return $prepend . $innertext . '[/list]';
	}
}

// ###################### Start bbcodeparse2_regexrecurse #######################
function parse_bbcode_regexrecurse($bbcode, $iswysiwyg)
{
	global $DB_site, $vboptions, $bbuserinfo, $templatecache, $datastore, $wysiwygparse, $session;
	static $BBCODES;

	$wysiwygparse = $iswysiwyg;

	if (empty($BBCODES['standard']))
	{
		$BBCODES = fetch_bbcode_definitions();

		$doubleRegex = '/(\[)(%s)(=)(&quot;|"|\'|)([^"]*)(\\4)\](.*)(\[\/%s\])/esiU';
		$singleRegex = '/(\[)(%s)(\])(.*)(\[\/%s\])/esiU';

		if (isset($datastore['bbcodecache'])) // get bbcodes from the datastore
		{
			$bbcodecache = unserialize($datastore['bbcodecache']);

			foreach ($bbcodecache AS $bbregex)
			{
				if ($bbregex['twoparams'])
				{
					$regex = sprintf($doubleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
					$bbregex['bbcodereplacement'] = str_replace(array('\\7', '\\5'), array('%1$s', '%2$s'), $bbregex['bbcodereplacement']);
					$tagname = "[$bbregex[bbcodetag]=";
					$checkparam = '\\7';
					$checkoption = '\\5';
				}
				else
				{
					$regex = sprintf($singleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
					$bbregex['bbcodereplacement'] = str_replace('\\4', '%1$s', $bbregex['bbcodereplacement']);
					$tagname = "[$bbregex[bbcodetag]]";
					$checkparam = '\\4';
					$checkoption = '';
				}
				$BBCODES['custom']['find']["$tagname"] = $regex;
				$BBCODES['custom']['replace']["$tagname"] = "handle_custom_bbcode('$checkparam', '$checkoption', '" . str_replace("'", "\'", $bbregex['bbcodereplacement']) . "')";
			}
		}
		else // query bbcodes out of the database
		{
			$bbcodes = $DB_site->query("
				SELECT bbcodetag, bbcodereplacement, twoparams
				FROM " . TABLE_PREFIX . "bbcode
			");
			while ($bbregex = $DB_site->fetch_array($bbcodes))
			{
				if ($bbregex['twoparams'])
				{
					$regex = sprintf($doubleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
					$bbregex['bbcodereplacement'] = str_replace(array('\\7', '\\5'), array('%1$s', '%2$s'), $bbregex['bbcodereplacement']);
					$tagname = "[$bbregex[bbcodetag]=";
					$checkparam = '\\7';
					$checkoption = '\\5';
				}
				else
				{
					$regex = sprintf($singleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
					$bbregex['bbcodereplacement'] = str_replace('\\4', '%1$s', $bbregex['bbcodereplacement']);
					$tagname = "[$bbregex[bbcodetag]]";
					$checkparam = '\\4';
					$checkoption = '';
				}
				$BBCODES['custom']['find']["$tagname"] = $regex;
				$BBCODES['custom']['replace']["$tagname"] = "handle_custom_bbcode('$checkparam', '$checkoption', '" . str_replace("'", "\'", $bbregex['bbcodereplacement']) . "')";
			}
		}
	}

	if ($iswysiwyg) // text to show in the WYSIWYG editor box
	{
		$bbcode_find = $BBCODES['standard']['find'];
		$bbcode_replace = $BBCODES['standard']['replace'];
	}
	else // text to show everywhere else
	{
		//$bbcode_find = array_merge($BBCODES['standard']['find'], $BBCODES['custom']['find']);
		//$bbcode_replace = array_merge($BBCODES['standard']['replace'], $BBCODES['custom']['replace']);

		$bbcode_find = array_merge($BBCODES['custom']['find'], $BBCODES['standard']['find']);
		$bbcode_replace = array_merge($BBCODES['custom']['replace'], $BBCODES['standard']['replace']);
	}

	foreach($bbcode_find AS $tag => $findregex)
	{
		// if using option, $tag will be '[xxx='
		// if not using option, $tag will be '[xxx]'

		while (stristr($bbcode, $tag) !== false)
		{
			// make a copy of the text pre-replacement for later comparison
			$origtext = $bbcode;

			$bbcode = preg_replace($findregex, $bbcode_replace["$tag"], $bbcode);

			// check to see if the preg_replace actually did anything... if it didn't, break the loop
			if ($origtext == $bbcode)
			{
				break;
			}
		}
	}

	return $bbcode;
}

// ###################### Start bbcodeparse2_regex #######################
function parse_bbcode_regex($bbcode, $iswysiwyg)
{
	global $DB_site, $vboptions, $bbuserinfo, $templatecache, $datastore, $wysiwygparse, $session;
	static $BBCODES;

	$wysiwygparse = $iswysiwyg;

	if (empty($BBCODES['standard']))
	{
		$BBCODES = fetch_bbcode_definitions();

		$doubleRegex = '/(\[)(%s)(=)(&quot;|"|\'|)(.*)(\\4)\](.*)(\[\/%s\])/esiU';
		$singleRegex = '/(\[)(%s)(\])(.*)(\[\/%s\])/esiU';

		if (isset($datastore['bbcodecache']))
		{ // we can get the bbcode from the bbcodecache php template
			DEVDEBUG("returning bbcodes from the template cache");
			$bbcodecache = unserialize($datastore['bbcodecache']);
			foreach($bbcodecache AS $bbregex)
			{
				if ($bbregex['twoparams'])
				{
					$regex = sprintf($doubleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
					$checkparam = 7;
				}
				else
				{
					$regex = sprintf($singleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
					$checkparam = 4;
				}
				for ($i = 0; $i < 3; $i++)
				{
					$BBCODES['custom']['find'][] = $regex;
					$BBCODES['custom']['replace'][] = "handle_bbcode_parameter('\\$checkparam','" . str_replace("'", "\'", $bbregex['bbcodereplacement']) . "')";
				}
			}
		}
		else
		{ // we have to get the bbcodes from the database
			DEVDEBUG("querying bbcodes for parse_bbcode2();");
			$bbcodes = $DB_site->query("
				SELECT bbcodetag, bbcodereplacement, twoparams
				FROM " . TABLE_PREFIX . "bbcode
			");
			while($bbregex = $DB_site->fetch_array($bbcodes))
			{
				if ($bbregex['twoparams'])
				{
					$regex = sprintf($doubleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
					$checkparam = 7;
				}
				else
				{
					$regex = sprintf($singleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
					$checkparam = 4;
				}
				for ($i = 0; $i < 3; $i++)
				{
					$BBCODES['custom']['find'][] = $regex;
					$BBCODES['custom']['replace'][] = "handle_bbcode_parameter('\\$checkparam','" . str_replace("'","\'",$bbregex['bbcodereplacement']) . "')";
				}
			}
		}
	}

	if ($iswysiwyg) // text to show in the WYSIWYG editor box
	{
		$bbcode_find = $BBCODES['standard']['find'];
		$bbcode_replace = $BBCODES['standard']['replace'];
	}
	else // text to show everywhere else
	{
		$bbcode_find = array_merge($BBCODES['standard']['find'], $BBCODES['custom']['find']);
		$bbcode_replace = array_merge($BBCODES['standard']['replace'], $BBCODES['custom']['replace']);
	}

	// do the actual replacement
	$bbcode = preg_replace($bbcode_find, $bbcode_replace, $bbcode);

	return $bbcode;
}

// ###################### Start bbcodeparse2_recurse #######################
function parse_bbcode_recurse($bbcode, $iswysiwyg)
{
	global $DB_site, $vboptions, $bbuserinfo, $templatecache, $datastore, $wysiwygparse;
	static $BBCODES;

	$wysiwygparse = $iswysiwyg;

	// just get rid of old closing list tags
	if (stristr($bbcode, '/list=') != false)
	{
		$bbcode = preg_replace('#/list=[a-z0-9]\]#siU', '/list]', $bbcode);
	}

	if (empty($BBCODES['standard']))
	{
		$BBCODES = fetch_bbcode_definitions();

		if (isset($datastore['bbcodecache']))
		{ // we can get the bbcode from the bbcodecache php template
			DEVDEBUG("returning bbcodes from the template cache");
			if (!isset($bbcodecache))
			{
				$bbcodecache = unserialize($datastore['bbcodecache']);
			}
			foreach($bbcodecache AS $thisbbcode)
			{
				$BBCODES['custom']['recurse']["$thisbbcode[bbcodetag]"]["$thisbbcode[twoparams]"] = array('replace_html' => $thisbbcode['bbcodereplacement']);
			}
		}
		else
		{ // we have to get the bbcodes from the database
			DEVDEBUG("querying bbcodes for parse_bbcode2();");
			$bbcodes = $DB_site->query("
				SELECT bbcodetag, bbcodereplacement, twoparams
				FROM " . TABLE_PREFIX . "bbcode
			");
			while($thisbbcode = $DB_site->fetch_array($bbcodes))
			{
				$BBCODES['custom']['recurse']["$thisbbcode[bbcodetag]"]["$thisbbcode[twoparams]"] = array('replace_html' => $thisbbcode['bbcodereplacement']);
			}
		}
	}

	if ($iswysiwyg) // text to show in wysiwyg editor
	{
		$bbcode_search = &$BBCODES['standard']['recurse'];
	}
	else // text to show everywhere else
	{
		$bbcode_search = &array_merge($BBCODES['standard']['recurse'], $BBCODES['custom']['recurse']);
	}

	$startpos = 0;

	// process all the bbcode positions

	do
	{
		$tag = array('begin_open_pos' => strpos($bbcode, '[', $startpos));
		if ($tag['begin_open_pos'] === false)
		{
			break;
		}
		if ($bbcode[ $tag['begin_open_pos'] + 1 ] == '/')
		{ // this is a close tag -- ignore it
			$startpos = $tag['begin_open_pos'] + 1;
			continue;
		}

		$strlen = strlen($bbcode);

		$inquote = false;
		$hasoption = 0;
		$jumpto = 0;
		for ($i = $tag['begin_open_pos']; $i <= $strlen; $i++)
		{
			$char = $bbcode{$i};

			switch ($char)
			{
				case '[':
					if (!$inquote AND $i != $tag['begin_open_pos'])
					{
						$jumpto = $i;
					}
					break;
				#case ' ':
				#	$jumpto = $i;
				#	break;
				case ']':
					if (!$inquote)
					{
						$tag['begin_end_pos'] = $i + 1; // "+ 1" includes the ]
					}
					else
					{
						$jumpto = $i;
					}
					break;
				case '=':
					if (!$inquote AND !$hasoption)
					{
						// only do this stuff on the *first* =
						$hasoption = 1;
						$tag['name_end_pos'] = $i;
						$tag['option_open_pos'] = $i + 1;
					}
					break;
				case '\'': // break missing intentionally
				case '"':
					if (!$hasoption)
					{
						$jumpto = $i;
					}
					else if (!$inquote)
					{
						$inquote = $char;
						$tag['option_open_pos'] = $i + 1;
					}
					else if ($char == $inquote)
					{
						$inquote = false;
						$tag['option_end_pos'] = $i;
					}
					break;
			}

			if ($jumpto OR $tag['begin_end_pos'])
			{
				break;
			}
		}

		if (empty($startpos) AND $i == $strlen + 1) // added by JP. Was getting infinite loops on parsing [QUOTE] l: [ : : integ [/QUOTE]
		{
			break;
		}

		if ($jumpto)
		{
			$startpos = $jumpto;
			continue;
		}

		if (!$tag['name_end_pos'])
		{
			$tag['name_end_pos'] = $tag['begin_end_pos'] - 1;
		}
		if ($hasoption AND !$tag['option_end_pos'])
		{
			$tag['option_end_pos'] = $tag['begin_end_pos'] - 1;
		}

		$bbcode_lower = strtolower($bbcode);

		$tag['name'] = substr($bbcode_lower, $tag['begin_open_pos'] + 1, $tag['name_end_pos'] - ($tag['begin_open_pos'] + 1));
		if (!isset($bbcode_search["$tag[name]"]["$hasoption"]))
		{
			// the tag is one that isn't going to be translated anyway, so don't waste time on it
			$startpos = $tag['begin_end_pos'];
			continue;
		}

		if ($hasoption)
		{
			$tag['option'] = substr($bbcode, $tag['option_open_pos'], $tag['option_end_pos'] - $tag['option_open_pos']);
		}
		else
		{
			$tag['option'] = '';
		}

		$tag['close_open_pos'] = strpos($bbcode_lower, "[/$tag[name]]", $tag['begin_end_pos']);
		if ($tag['close_open_pos'] === false)
		{
			$startpos = $tag['begin_end_pos'];
			continue;
		}

		$recursivetags = substr_count(substr($bbcode_lower, $tag['begin_end_pos'], $tag['close_open_pos'] - $tag['begin_end_pos']), "[/$tag[name]]");
		$bumped = 0;
		for ($i = 0; $i < $recursivetags; $i++)
		{
			$tag['close_open_pos'] = strpos($bbcode_lower, "[/$tag[name]]", $tag['close_open_pos'] + 1);
			if ($tag['close_open_pos'] === false)
			{ // no closing tag found, so stop parsing
				$bumped = -1;
				break;
			}
			$bumped++;
		}
		if ($bumped != $recursivetags)
		{
			$startpos = $tag['begin_end_pos'];
			continue;
		}
		$tag['close_end_pos'] = strpos($bbcode_lower, ']', $tag['close_open_pos'] + 1) + 1;

		$data = substr($bbcode, $tag['begin_end_pos'], $tag['close_open_pos'] - $tag['begin_end_pos']);

		// standard replace
		if (isset($bbcode_search["$tag[name]"]["$hasoption"]['replace']))
		{
			$htmltag = $bbcode_search["$tag[name]"]["$hasoption"]['replace'];
			$parseddata = "<$htmltag>$data</$htmltag>";
		}
		// html replace
		else if (isset($bbcode_search["$tag[name]"]["$hasoption"]['replace_html']))
		{
			$parseddata = str_replace(array('\5', '\7', '\4'), array($tag['option'], $data, $data), $bbcode_search["$tag[name]"]["$hasoption"]['replace_html']);
		}
		// special handler replace
		else if (isset($bbcode_search["$tag[name]"]["$hasoption"]['handler']))
		{
			$function = $bbcode_search["$tag[name]"]["$hasoption"]['handler'];
			$parseddata = $function($data, $tag['option']);
		}
		// nothing to do
		else
		{
			continue;
		}
		$bbcode = substr_replace($bbcode, $parseddata, $tag['begin_open_pos'], $tag['close_end_pos'] - $tag['begin_open_pos']);

		$startpos = $tag['begin_end_pos'];
	}
	while (1);

	return $bbcode;
}

// ###################### Start hasimages #######################
function contains_bbcode_img_tags($bbcode)
{
	return iif(strpos(strtolower($bbcode), '[img') !== false, 1, 0);
}

// ###################### Start bbcodeparseimgcode #######################
function handle_bbcode_img($bbcode, $dobbimagecode)
{
	global $vboptions, $bbuserinfo;

	if($dobbimagecode AND ($bbuserinfo['userid'] == 0 OR $bbuserinfo['showimages']))
	{
		// do [img]xxx[/img]
		$bbcode = preg_replace('#\[img\]\s*(https?://([^<>*"' . iif(!$vboptions['allowdynimg'], '?&') . ']+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "handle_bbcode_img_match('\\1')", $bbcode);
	}
	$bbcode = preg_replace('#\[img\]\s*(https?://([^<>*"]+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "handle_bbcode_url('\\1', '', 'url')", $bbcode);

	return $bbcode;
}

// ###################### Start handle_bbcode_img_match #######################
// this is only called by handle_bbcode_img
function handle_bbcode_img_match($link)
{
	$link = strip_smilies(str_replace('\\"', '"', $link));

	// remove double spaces -- fixes issues with wordwrap
	$link = str_replace('  ', '', $link);

	return '<img src="' .  $link . '" border="0" alt="" />';
}

// ###################### Start bbcodehandler_quote #######################
function handle_bbcode_quote($message, $username = '')
{
	global $vboptions, $vbphrase, $stylevar, $show;

	// remove empty codes
	if (trim($message) == '')
	{
		return '';
	}

	// remove unnecessary escaped quotes
	$message = str_replace('\\"', '"', $message);
	$username = str_replace('\\"', '"', $username);

	// remove smilies from username
	$username = strip_smilies($username);
	$show['username'] = iif($username != '', true, false);

	global $stopsaveparsed, $parsed_postcache;
	if ($stopsaveparsed OR $parsed_postcache['skip'] == true OR !$vboptions['cachemaxage'])
	{
		$show['iewidthfix'] = (is_browser('ie') AND !(is_browser('ie', 6)));
	}
	else
	{
		// this post may be cached, so we can't allow this "fix" to be included in that cache
		$show['iewidthfix'] = false;
	}

	eval('$html = "' . fetch_template('bbcode_quote') . '";');
	return $html;
}

// ###################### Start bbcodehandler_php #######################
function handle_bbcode_php($code)
{
	global $vboptions, $vbphrase, $stylevar, $highlight_errors;
	static $codefind1, $codereplace1, $codefind2, $codereplace2;

	// remove empty codes
	if (trim($code) == '')
	{
		return '';
	}

	//remove smilies
	$code = strip_smilies(str_replace('\\"', '"', $code));

	if (!is_array($codefind))
	{
		$codefind1 = array(
			'<br>',		// <br> to nothing
			'<br />'	// <br /> to nothing
		);
		$codereplace1 = array(
			'',
			''
		);

		$codefind2 = array(
			'&gt;',		// &gt; to >
			'&lt;',		// &lt; to <
			'&quot;',	// &quot; to ",
			'&amp;',	// &amp; to &
		);
		$codereplace2 = array(
			'>',
			'<',
			'"',
			'&',
		);
	}

	// remove htmlspecialchars'd bits and excess spacing
	$code = trim(str_replace($codefind1, $codereplace1, $code));
	$blockheight = fetch_block_height($code); // fetch height of block element
	$code = str_replace($codefind2, $codereplace2, $code); // finish replacements

	// do we have an opening <? tag?
	if (!preg_match('#^\s*<\?#si', $code))
	{
		// if not, replace leading newlines and stuff in a <?php tag and a closing tag at the end
		$code = "<?php BEGIN__VBULLETIN__CODE__SNIPPET $code \r\nEND__VBULLETIN__CODE__SNIPPET ?>";
		$addedtags = true;
	}
	else
	{
		$addedtags = false;
	}


	// highlight the string
	$oldlevel = error_reporting(0);
	if (PHP_VERSION  >= '4.2.0')
	{
		$buffer = highlight_string($code, true);
	}
	else
	{
		@ob_start();
		highlight_string($code);
		$buffer = @ob_get_contents();
		@ob_end_clean();
	}
	error_reporting($oldlevel);

	// if we added tags above, now get rid of them from the resulting string
	if ($addedtags)
	{
		$search = array(
			'#(<|&lt;)\?php( |&nbsp;)BEGIN__VBULLETIN__CODE__SNIPPET#siU',
			'#(<(span|font).*>)(<|&lt;)\?(</\\2>(<\\2.*>))php( |&nbsp;)BEGIN__VBULLETIN__CODE__SNIPPET#siU',
			'#END__VBULLETIN__CODE__SNIPPET( |&nbsp;)\?(>|&gt;)#siU'
		);
		$replace = array(
			'',
			'\\5',
			''
		);
		$buffer = preg_replace($search, $replace, $buffer);
	}

	$buffer = str_replace('[', '&#91;', $buffer);
	$buffer = preg_replace('/&amp;#([0-9]+);/', '&#$1;', $buffer); // allow unicode entities back through
	$code = &$buffer;

	eval('$html = "' . fetch_template('bbcode_php') . '";');
	return $html;
}

// ###################### Start bbcodehandler_code #######################
function handle_bbcode_code($code)
{
	global $vboptions, $vbphrase, $stylevar;

	// remove empty codes
	if (trim($code) == '')
	{
		return '';
	}

	// remove unnecessary line breaks and escaped quotes
	$code = str_replace(array('<br>', '<br />', '\\"'), array('', '', '"'), $code);

	// remove smilies
	$code = strip_smilies($code);

	// fetch height of block element
	$blockheight = fetch_block_height($code);

	eval('$html = "' . fetch_template('bbcode_code') . '";');
	return $html;
}

// ###################### Start bbcodehandler_html #######################
function handle_bbcode_html($code)
{
	global $vboptions, $vbphrase, $stylevar, $html_allowed;
	static $regexfind, $regexreplace;

	// remove empty codes
	if (trim($code) == '')
	{
		return '';
	}

	//remove smilies
	$code = strip_smilies(str_replace('\\"', '"', $code));

	if (!is_array($regexfind))
	{
		$regexfind = array(
			'#<br( /)?>#siU',				// strip <br /> codes
			'#(&amp;\w+;)#siU',				// do html entities
			'#&lt;!--(.*)--&gt;#siU',		// italicise comments
			'#&lt;(.+)&gt;#esiU'			// push code through the tag handler
		);
		$regexreplace = array(
			'',								// strip <br /> codes
			'<b><i>\1</i></b>',				// do html entities
			'<i>&lt;!--\1--&gt;</i>',		// italicise comments
			"handle_bbcode_html_tag('\\1')"	// push code through the tag handler
		);
	}

	if ($html_allowed)
	{
		$regexfind[] = '#\<(.+)\>#esiU';
		$regexreplace[] = "handle_bbcode_html_tag(htmlspecialchars_uni(stripslashes('\\1')))";
	}
	// parse the code
	$code = preg_replace($regexfind, $regexreplace, $code);

	// how lame but HTML might not be on in signatures
	if ($html_allowed)
	{
		$regexfind = array_pop($regexfind);
		$regexreplace = array_pop($regexreplace);
	}

	$code = str_replace('[', '&#91;', $code);

	// fetch height of block element
	$blockheight = fetch_block_height($code);

	eval('$html = "' . fetch_template('bbcode_html') . '";');
	return $html;
}

// ###################### Start bbcodehandler_html_tag #######################
function handle_bbcode_html_tag($tag)
{
	global $bbcode_html_colors;

	if (empty($bbcode_html_colors))
	{
		fetch_bbcode_html_colors();
	}

	// change any embedded URLs so they don't cause any problems
	$tag = preg_replace('#\[(email|url)=&quot;(.*)&quot;\]#siU', '[$1="$2"]', $tag);

	// find if the tag has attributes
	$spacepos = strpos($tag, ' ');
	if ($spacepos != false)
	{
		// tag has attributes - get the tag name and parse the attributes
		$tagname = substr($tag, 0, $spacepos);
		$tag = preg_replace('# (\w+)=&quot;(.*)&quot;#siU', ' \1=<font color="' . $bbcode_html_colors['attribs'] . '">&quot;\2&quot;</font>', $tag);
	}
	else
	{
		// no attributes found
		$tagname = $tag;
	}
	// remove leading slash if there is one
	if ($tag{0} == '/')
	{
		$tagname = substr($tagname, 1);
	}
	// convert tag name to lower case
	$tagname = strtolower($tagname);

	// get highlight colour based on tag type
	switch($tagname)
	{
		// table tags
		case 'table':
		case 'tr':
		case 'td':
		case 'th':
		case 'tbody':
		case 'thead':
			$tagcolor = $bbcode_html_colors['table'];
			break;
		// form tags
		case 'form';
		case 'input':
		case 'select':
		case 'option':
		case 'textarea':
		case 'label':
		case 'fieldset':
		case 'legend':
			$tagcolor = $bbcode_html_colors['form'];
			break;
		// script tags
		case 'script':
			$tagcolor = $bbcode_html_colors['script'];
			break;
		// style tags
		case 'style':
			$tagcolor = $bbcode_html_colors['style'];
			break;
		// anchor tags
		case 'a':
			$tagcolor = $bbcode_html_colors['a'];
			break;
		// img tags
		case 'img':
			$tagcolor = $bbcode_html_colors['img'];
			break;
		// if (vB Conditional) tags
		case 'if':
		case 'else':
		case 'elseif':
			$tagcolor = $bbcode_html_colors['if'];
			break;
		// all other tags
		default:
			$tagcolor = $bbcode_html_colors['default'];
			break;
	}

	$tag = '<font color="' . $tagcolor . '">&lt;' . str_replace('\\"', '"', $tag) . '&gt;</font>';
	return $tag;
}

// ###################### Start bbcodehandler_list2 #######################
// replacement for bbcodehandler_list... experimental at this time
function handle_bbcode_list($string)
{
	#echo '<p><b>$string</b><br />' . nl2br(htmlspecialchars($string)) . '</p>';
	global $BBCODES, $wysiwygparse;
	// might need this in the future
	//$string = stripslashes($string);
	$string = str_replace('\"', '"', $string);
	$str = $string;

	// getList
	$slashlist = strpos($str, ']', stripos($str, '[/list')) + 1;
	$tmp = substr($str, 0, $slashlist);
	$openlist = strlen($tmp) - stripos(strrev($tmp), strrev('[list')) - strlen('[list');
	$getList = substr($str, $openlist, ($slashlist - $openlist));

	#echo '<p><b>$getList</b><br />' . htmlspecialchars($getList) . '</p>';

	// processList
	if (preg_match('#\s*(\[list(=(&quot;|"|\'|)([^\]]*)\\3)?\](.*)\[/list(=\\3\\4\\3)?\])\s*#si', $getList, $regs))
	{
		$getList = $regs[0];
		#echo '<p><b>Regex Match</b><br />' . htmlspecialchars($regs[0]) . '</p>';
		$str = preg_split('#\s*\[\*\]#s', $regs[5], -1, PREG_SPLIT_NO_EMPTY);

		if (empty($str))
		{
			return preg_replace('#\s*' . preg_quote($getList, '#') . '\s*#s', nl2br("\n\n"), $string);
		}

		if ($regs[4])
		{
			switch ($regs[4])
			{
				case 'A':
					$listtype = 'upper-alpha';
					break;
				case 'a':
					$listtype = 'lower-alpha';
					break;
				case 'I':
					$listtype = 'upper-roman';
					break;
				case 'i':
					$listtype = 'lower-roman';
					break;
				case '1': //break missing intentionally
				default:
					$listtype = 'decimal';
					break;
			}
		}
		else
		{
			$listtype = '';
		}

		$processList = iif($listtype, '<ol style="list-style-type: ' . $listtype . '">', '<ul>');

		$bad_tag_list = '(br|p|li|ul|ol)';

		foreach ($str AS $key => $val)
		{
			$firstbit = strtolower(substr($val, 0, 3));
			if ($firstbit === '<ul' OR $firstbit === '<ol' OR $firstbit === '<li' OR $firstbit == '')
			{
				$processList .= $val;
			}
			else
			{
				if ($wysiwygparse)
				{
					$exploded = preg_split("#(\r\n|\n|\r)#", $val);

					$val = '';
					foreach ($exploded AS $value)
					{
						if (!preg_match('#(</' . $bad_tag_list . '>|<' . $bad_tag_list . '\s*/>)$#iU', $value))
						{
							if (trim($value) == '')
							{
								$value = '&nbsp;';
							}
							//$val .= '<p style="margin:0px">' . $value . "</p>";
							$val .= $value . "<br />\n";
						}
						else
						{
							$val .= "$value\n";
						}
					}
					$val = preg_replace('#<br />+\s*$#i', '', $val);

				}
				$processList .= '<li>' . $val . '</li>';
			}
		}

		$processList .= iif($listtype, '</ol>', '</ul>');

		#echo '<p><b>$processList</b><br />' . htmlspecialchars($processList) . '</p>';

		// replace found list characters with parsed list characters
		if ($wysiwygparse)
		{
			$processList = str_replace('<p style="margin:0px"></p>', '', $processList);
		}

		//$out = preg_replace('#\s*' . preg_quote($getList, '#') . '\s*#s', str_replace(array('\\', '$'), array('\\\\', '\$'), $processList), $string);
		$out = str_replace($getList, $processList, $string);
		#echo '<p><b>Return Value</b><br />' . nl2br(htmlspecialchars($out)) . '</p><hr />';
		return $out;
	}
	else
	{
		return $string;
	}

}

// ###################### Start handle_bbcode_url #######################
function handle_bbcode_url($text, $link, $type = 'url')
{
	global $wysiwygparse;

	if (trim($text) == '')
	{
		return '';
	}

	$rightlink = trim($link);
	if (empty($rightlink))
	{
		// no option -- use param
		$rightlink = trim($text);
	}
	$rightlink = strip_smilies(str_replace('\\"', '"', $rightlink));
	$rightlink = str_replace(
		array('`', '"', "'", '[', '<', '>'),
		array('&#96;', '&quot;', '&#39;', '&#91;', '&lt;', '&gt;'),
		$rightlink
	);

	if ($type == 'url' AND !preg_match('#^[a-z0-9]+://#si', $rightlink))
	{
		$rightlink = "http://$rightlink";
	}

	if (!trim($link) OR $text == $rightlink)
	{
		$tmp = unhtmlspecialchars($rightlink);
		if (strlen($tmp) > 55 AND !$wysiwygparse)
		{
			$text = htmlspecialchars_uni(substr($tmp, 0, 35) . '...' . substr($tmp, -15));
		}
	}

	// remove double spaces -- fixes issues with wordwrap
	$rightlink = str_replace('  ', '', $rightlink);

	// strip extra quotes from hyperlink
	$text = str_replace('\"', '"', $text);

	if ($type == 'url')
	{
		// standard URL hyperlink
		return "<a href=\"$rightlink\" target=\"_blank\">$text</a>";
	}
	else
	{
		// email hyperlink (mailto:)
		if (is_valid_email($rightlink))
		{
			return "<a href=\"mailto:$rightlink\">$text</a>";
		}
		else
		{
			// not a valid email - don't link it
			return $text;
			//return "<span title=\"$rightlink\">$text</span>";
		}
	}
}

// ###################### Start strip_smilies #######################
// removes smilies that were replaced with bbcode parser; use in code/php/html/quote=username tags
function strip_smilies($text, $iswysiwyg = false)
{
	global $smiliecache, $datastore;
	static $smilie_find, $smilie_replace;

	if (empty($smilie_find))
	{
		$smilie_find = array();
		$smilie_replace = array();

		if (is_array($smiliecache))
		{
			foreach ($smiliecache AS $smilie)
			{
				if (trim($smilie['smilietext']) != '')
				{
					if ($iswysiwyg)
					{
						$smilie_find[] = "<img src=\"$smilie[smiliepath]\" border=\"0\" alt=\"\" title=\"$smilie[title]\" smilieid=\"$smilie[smilieid]\" class=\"inlineimg\" />";
					}
					else
					{
						$smilie_find[] = "<img src=\"$smilie[smiliepath]\" border=\"0\" alt=\"\" title=\"$smilie[title]\" class=\"inlineimg\" />";
					}
					$smilie_replace[] = htmlspecialchars_uni(trim($smilie['smilietext']));
				}
			}
		}
	}

	return str_replace($smilie_find, $smilie_replace, $text);

}

// ###################### Start stripos #######################
// case-insensitive version of strpos - syntax identical
if (!function_exists('stripos'))
{
	function stripos($haystack, $needle, $offset = 0)
	{
		$foundstring = stristr(substr($haystack, $offset), $needle);
		return $foundstring === false ? false : strlen($haystack) - strlen($foundstring);
	}
}

// ###################### Start fetch block height #######################
// function to return appropriate box height based on # lines in code block
function fetch_block_height($code)
{
	global $vboptions;
	static $maxlines;

	if (!isset($maxlines))
	{
		$maxlines = $vboptions['codemaxlines'];
	}

	// establish a reasonable number for the line count in the code block
	$numlines = max(substr_count($code, "\n"), substr_count($code, "<br />")) + 1;

	// set a maximum number of lines...
	if ($numlines > $maxlines AND $maxlines > 0)
	{
		$numlines = $maxlines;
	}
	else if ($numlines < 1)
	{
		$numlines = 1;
	}

	// return height in pixels
	return ($numlines) * 16 + 18;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14635 $
|| ####################################################################
\*======================================================================*/
?>