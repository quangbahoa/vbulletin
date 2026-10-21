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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/class_bbcode.php');

/**
* BB code parser for the WYSIWYG editor
*
* @package 		vBulletin
* @version		$Revision: 14200 $
* @date 		$Date: 2006-02-02 11:45:25 -0600 (Thu, 02 Feb 2006) $
*
*/
class vB_BbCodeParser_Wysiwyg extends vB_BbCodeParser
{
	/**
	* List of tags the WYSIWYG BB code parser should not parse.
	*
	* @var	array
	*/
	var $unparsed_tags = array(
		'thread',
		'post',
		'php',
		//'code', // leave this parsed, because <space><space> needs to be replaced
		'html',
		'quote',
		'highlight',
		'noparse'
	);

	/**
	* Type of WYISWYG parser (IE or Mozilla at this point)
	*
	* @var	string
	*/
	var $type = '';

	/**
	* Constructor. Sets up the tag list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		List of tags to parse
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_BbCodeParser_Wysiwyg(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, $append_custom_tags);

		// change all unparsable tags to use the unparsable callback
		foreach ($this->unparsed_tags AS $remove)
		{
			if (isset($this->tag_list['option']["$remove"]))
			{
				$this->tag_list['option']["$remove"]['callback'] = 'handle_wysiwyg_unparsable';
				unset($this->tag_list['option']["$remove"]['html']);
			}
			if (isset($this->tag_list['no_option']["$remove"]))
			{
				$this->tag_list['no_option']["$remove"]['callback'] = 'handle_wysiwyg_unparsable';
				unset($this->tag_list['no_option']["$remove"]['html']);
			}
		}

		$this->type = is_browser('ie') ? 'ie' : 'moz_css';
	}

	/**
	* Loads any user specified custom BB code tags into the $tag_list. These tags
	* will not be parsed. They are loaded simply for directive parsing.
	*/
	function append_custom_tags()
	{
		if ($this->custom_fetched == true)
		{
			return;
		}

		$this->custom_fetched = true;
		// this code would make nice use of an interator
		if ($this->registry->bbcodecache !== null) // get bbcodes from the datastore
		{
			foreach($this->registry->bbcodecache AS $customtag)
			{
				$has_option = $customtag['twoparams'] ? 'option' : 'no_option';
				$customtag['bbcodetag'] = strtolower($customtag['bbcodetag']);

				// str_replace is stop gap until custom tags are updated to the new format
				$this->tag_list["$has_option"]["$customtag[bbcodetag]"] = array(
					'callback' => 'handle_wysiwyg_unparsable',
					'strip_empty' => true
				);
			}
		}
		else // query bbcodes out of the database
		{
			$bbcodes = $this->registry->db->query_read("
				SELECT bbcodetag, bbcodereplacement, twoparams
				FROM " . TABLE_PREFIX . "bbcode
			");
			while ($customtag = $this->registry->db->fetch_array($bbcodes))
			{
				$has_option = $customtag['twoparams'] ? 'option' : 'no_option';

				// str_replace is stop gap until custom tags are updated to the new format
				$this->tag_list["$has_option"]["$customtag[bbcodetag]"] = array(
					'callback' => 'handle_wysiwyg_unparsable',
					'strip_empty' => true
				);
			}
		}
	}

	/**
	* Handles an [img] tag.
	*
	* @param	string	The text to search for an image in.
	* @param	string	Whether to parse matching images into pictures or just links.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_img($bbcode, $do_imgcode, $has_img_code = false)
	{
		if ($has_img_code == 1 OR $has_img_code == 3)
		{
			if ($do_imgcode AND ($this->registry->userinfo['userid'] == 0 OR $this->registry->userinfo['showimages']))
			{
				// do [img]xxx[/img]
				$bbcode = preg_replace('#\[img\]\s*(https?://([^<>*"' . iif(!$this->registry->options['allowdynimg'], '?&') . ']+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_bbcode_img_match('\\1')", $bbcode);
			}
			$bbcode = preg_replace('#\[img\]\s*(https?://([^<>*"]+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_bbcode_url(str_replace('\\\"', '\"', '\\1'), '')", $bbcode);
		}

		return $bbcode;
	}

	/**
	* Handles a [code] tag. In WYSIYWYG parsing, keeps the tag but replaces
	* <space><space> with a non-breaking space followed by a space.
	*
	* @param	string	The code to display
	*
	* @return	string	Tag with spacing replaced
	*/
	function handle_bbcode_code($code)
	{
		$current_tag =& $this->current_tag;

		$code = str_replace('  ', ' &nbsp;', $code);
		$code = preg_replace('#(\r\n|\n|\r|<p>)( )(?!([\r\n]}|<p>))#i', '$1&nbsp;', $code);

		return "[$current_tag[name]]" . $code . "[/$current_tag[name]]";
	}

	/**
	* Perform word wrapping on the text. WYSIWYG parsers should not
	* perform wrapping, so this function does nothing.
	*
	* @param	string	Text to be used for wrapping
	*
	* @return	string	Input string (unmodified)
	*/
	function do_word_wrap($text)
	{
		return $text;
	}

	/**
	* Parses out specific white space before or after cetain tags, rematches
	* tags where necessary, and processes line breaks.
	*
	* @param	string	Text to process
	* @param	bool	Whether to translate newlines to HTML breaks (unused)
	*
	* @return	string	Processed text
	*/
	function parse_whitespace_newlines($text, $do_nl2br = true)
	{
		$whitespacefind = array(
			'#(\r\n|\n|\r)?( )*(\[\*\]|\[/list|\[list|\[indent)#si',
			'#(/list\]|/indent\])( )*(\r\n|\n|\r)?#si'
		);
		$whitespacereplace = array(
			'\3',
			'\1'
		);
		$text = preg_replace($whitespacefind, $whitespacereplace, $text);

		if ($this->is_wysiwyg('ie'))
		{
			// this fixes an issue caused by odd nesting of tags. This causes IE's
			// WYSIWYG editor to display the output as vB will display it
			$rematch_find = array(
				'#\[(b)\](.*)\[/\\1\]#siUe',
				'#\[(i)\](.*)\[/\\1\]#siUe',
				'#\[(u)\](.*)\[/\\1\]#siUe',
				'#\[(left)\](.*)\[/\\1\]#siUe',
				'#\[(center)\](.*)\[/\\1\]#siUe',
				'#\[(right)\](.*)\[/\\1\]#siUe',
			);
			$text = preg_replace($rematch_find, "\$this->bbcode_rematch_tags_wysiwyg('\\2', '\\1')", $text);

			$text = '<p>' . preg_replace('#(\r\n|\n|\r)#', "</p>\n<p>", trim($text)) . '</p>';

			$text = preg_replace('#(\[list(=(&quot;|"|\'|)(.*)\\3)?\])(((?>[^\[]*?|(?R))|(?>.))*)(\[/list(=\\3\\4\\3)?\])#siUe', "\$this->remove_wysiwyg_breaks('\\0')", $text);
			$text = preg_replace('#<p>\s*</p>(?!\s*\[list|$)#i', '<p>&nbsp;</p>', $text);

			$text = str_replace('<p></p>', '', $text);

		}
		else
		{
			$text = nl2br($text);
		}

		// convert tabs to four &nbsp;
		$text = str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $text);

		return $text;
	}

	/**
	* Parse an input string with BB code to a final output string of HTML
	*
	* @param	string	Input Text (BB code)
	* @param	bool	Whether to parse smilies
	* @param	bool	Whether to allow HTML (for smilies)
	*
	* @return	string	Ouput Text (HTML)
	*/
	function parse_bbcode($input_text, $do_smilies, $do_html = false)
	{
		$text = $this->parse_array($this->fix_tags($this->build_parse_array($input_text)), $do_smilies, $do_html);

		if ($this->is_wysiwyg('ie'))
		{
			$text = preg_replace('#<p><(p|div) align="([a-z]+)">(.*)</\\1></p>#siU', '<p align="\\2">\\3</p>', $text);

			// by now, any empty p tags are things that used to be in the form of <p>[tag][/tag]</p>,
			// so we need to leave them to be equivalent to the nl2br version
			$text = preg_replace('#<p></p>#siU', '<p>&nbsp;</p>', $text);
		}

		// need to display smilies in code/php/html tags as literals
		$text = preg_replace('#\[(code|php|html)\](.*)\[/\\1\]#siUe', "\$this->strip_smilies(str_replace('\\\"', '\"', '\\0'), true)", $text);

		return $text;
	}

	/**
	* Call back to handle any tag that the WYSIWYG editor can't handle. This
	* parses the tag, but returns an unparsed version of it. The advantage of
	* this method is that any parsing directives (no parsing, no smilies, etc)
	* will still be applied to the text within.
	*
	* @param	string	Text inside the tag
	*
	* @return	string	The unparsed tag and the text within it
	*/
	function handle_wysiwyg_unparsable($text)
	{
		return '[' . $this->current_tag['name'] .
			($this->current_tag['option'] !== false ?
				('=' . $this->current_tag['delimiter'] . $this->current_tag['option'] . $this->current_tag['delimiter']) :
				''
			) . ']' . $text . '[/' . $this->current_tag['name'] . ']';
	}

	/**
	* Handles a single bullet of a list
	*
	* @param	string	Text of bullet
	*
	* @return	string	HTML for bullet
	*/
	function handle_bbcode_list_element($text)
	{
		$bad_tag_list = '(br|p|li|ul|ol)';

		$exploded = preg_split("#(\r\n|\n|\r)#", $text);

		$output = '';
		foreach ($exploded AS $value)
		{
			if (!preg_match('#(</' . $bad_tag_list . '>|<' . $bad_tag_list . '\s*/>)$#iU', $value))
			{
				if (trim($value) == '')
				{
					$value = '&nbsp;';
				}
				$output .= $value . "<br />\n";
			}
			else
			{
				$output .= "$value\n";
			}
		}
		$output = preg_replace('#<br />+\s*$#i', '', $output);

		return "<li>$output</li>";
	}

	/**
	* Returns whether this parser is a WYSIWYG parser if no type is specified.
	* If a type is specified, it checks whether our type matches
	*
	* @param	string|null	Type of parser to match; null represents any type
	*
	* @return	bool		True if it is; false otherwise
	*/
	function is_wysiwyg($type = null)
	{
		if ($type == null)
		{
			return true;
		}
		else
		{
			return ($this->type == $type);
		}
	}

	/**
	* Automatically inserts a closing tag before a line break and reopens it after.
	* Also wraps the text in the tag. Workaround for IE WYSIWYG issue.
	*
	* @param	string	Text to search through
	* @param	string	Tag to close and reopen
	*
	* @return	string	Processed text
	*/
	function bbcode_rematch_tags_wysiwyg($innertext, $tagname)
	{
		// This function replaces line breaks with [/tag]\n[tag].
		// It is intended to be used on text inside [tag] to fix an IE WYSIWYG issue.

		$innertext = str_replace('\"', '"', $innertext);
		return "[$tagname]" . preg_replace('#(\r\n|\n|\r)#', "[/$tagname]\n[$tagname]", $innertext) . "[/$tagname]";
	}

	/**
	* Removes IE's WYSIWYG breaks from within a list.
	*
	* @param	string	Text to remove breaks from. Should start with [list] and end with [/list]
	*
	* @return	string	Text with breaks removed
	*/
	function remove_wysiwyg_breaks($fulltext)
	{
		$fulltext = str_replace('\"', '"', $fulltext);
		preg_match('#^(\[list(=(&quot;|"|\'|)(.*)\\3)?\])(.*?)(\[/list(=\\3\\4\\3)?\])$#siU', $fulltext, $matches);
		$prepend = $matches[1];
		$innertext = $matches[5];

		$find = array("</p>\n<p>", '<br />', '<br>');
		$replace = array("\n", "\n", "\n");
		$innertext = str_replace($find, $replace, $innertext);

		if ($this->is_wysiwyg('ie'))
		{
			return '</p>' . $prepend . $innertext . '[/list]<p>';
		}
		else
		{
			return $prepend . $innertext . '[/list]';
		}
	}
}

/**
* BB code parser for the image checks. Only [img] and [attach] tags are actually
* parsed with this parser to prevent user-added <img> tags from counting.
*
* @package 		vBulletin
* @version		$Revision: 14200 $
* @date 		$Date: 2006-02-02 11:45:25 -0600 (Thu, 02 Feb 2006) $
*
*/
class vB_BbCodeParser_ImgCheck extends vB_BbCodeParser
{
	/**
	* Constructor. Sets up the tag list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		List of tags to parse
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_BbCodeParser_ImgCheck(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, $append_custom_tags);

		// change all unparsable tags to use the unparsable callback
		// [img] and [attach] tags are not parsed via the normal parser
		foreach ($this->tag_list['option'] AS $tagname => $info)
		{
			if (isset($this->tag_list['option']["$tagname"]))
			{
				$this->tag_list['option']["$tagname"]['callback'] = 'handle_unparsable';
				unset($this->tag_list['option']["$tagname"]['html']);
			}
		}

		foreach ($this->tag_list['no_option'] AS $tagname => $info)
		{
			if (isset($this->tag_list['no_option']["$tagname"]))
			{
				$this->tag_list['no_option']["$tagname"]['callback'] = 'handle_unparsable';
				unset($this->tag_list['no_option']["$tagname"]['html']);
			}
		}
	}

	/**
	* Loads any user specified custom BB code tags into the $tag_list. These tags
	* will not be parsed. They are loaded simply for directive parsing.
	*/
	function append_custom_tags()
	{
		if ($this->custom_fetched == true)
		{
			return;
		}

		$this->custom_fetched = true;
		// this code would make nice use of an interator
		if ($this->registry->bbcodecache !== null) // get bbcodes from the datastore
		{
			foreach($this->registry->bbcodecache AS $customtag)
			{
				$has_option = $customtag['twoparams'] ? 'option' : 'no_option';
				$customtag['bbcodetag'] = strtolower($customtag['bbcodetag']);

				// str_replace is stop gap until custom tags are updated to the new format
				$this->tag_list["$has_option"]["$customtag[bbcodetag]"] = array(
					'callback' => 'handle_unparsable',
					'strip_empty' => true
				);
			}
		}
		else // query bbcodes out of the database
		{
			$bbcodes = $this->registry->db->query_read("
				SELECT bbcodetag, bbcodereplacement, twoparams
				FROM " . TABLE_PREFIX . "bbcode
			");
			while ($customtag = $this->registry->db->fetch_array($bbcodes))
			{
				$has_option = $customtag['twoparams'] ? 'option' : 'no_option';

				// str_replace is stop gap until custom tags are updated to the new format
				$this->tag_list["$has_option"]["$customtag[bbcodetag]"] = array(
					'callback' => 'handle_unparsable',
					'strip_empty' => true
				);
			}
		}
	}

	/**
	* Call back to replace any tag with itself. In the context of this class,
	* very few tags are actually parsed.
	*
	* @param	string	Text inside the tag
	*
	* @return	string	The unparsed tag and the text within it
	*/
	function handle_unparsable($text)
	{
		$current_tag =& $this->current_tag;

		return "[$current_tag[name]" .
			($current_tag['option'] !== false ?
				"=$current_tag[delimiter]$current_tag[option]$current_tag[delimiter]" :
				''
			) . "]$text [/$current_tag[name]]";
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14200 $
|| ####################################################################
\*======================================================================*/
?>