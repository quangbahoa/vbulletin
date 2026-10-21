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

error_reporting(E_ALL & ~E_NOTICE);

// Attempt to load XML extension if we don't have the XML functions
// already loaded.
if (!function_exists('xml_set_element_handler'))
{
	$extension_dir = ini_get('extension_dir');
	if (strtoupper(substr(PHP_OS, 0, 3) == 'WIN'))
	{
		$extension_file = 'php_xml.dll';
	}
	else
	{
		$extension_file = 'xml.so';
	}
	if ($extension_dir AND file_exists($extension_dir . '/' . $extension_file))
	{
		ini_set('display_errors', true);
		dl($extension_file);
	}
}

$memory_limit = @ini_get('memory_limit');
if ($memory_limit AND ((strpos($memory_limit, 'M') AND intval($memory_limit) <= 8) OR intval($memory_limit) <= 9000000))
{
	@ini_set('memory_limit', 16 * 1024 * 1024);
}

/**
* Standard XML Parsing Object
*
* This class allows the parsing of an XML document to an array
*
* @package 		vBulletin
* @author		Scott MacVicar
* @version		$Revision: 13426 $
* @date 		$Date: 2005-10-06 18:05:59 -0500 (Thu, 06 Oct 2005) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/

class XMLparser
{
	/**
	* Internal PHP XML parser
	*
	* @var	resource
	*/
	var $xml_parser;

	/**
	* Error number (0 for no error)
	*
	* @var	integer
	*/
	var $error_no = 0;

	/**
	* The actual XML data being processed
	*
	* @var	integer
	*/
	var $xmldata = '';

	/**
	* The final, outputtable data
	*
	* @var	array
	*/
	var $parseddata = array();

	/**
	* Intermediate stack value used while parsing.
	*
	* @var	array
	*/
	var $stack = array();

	/**
	* Current CData being parsed
	*
	* @var	string
	*/
	var $cdata = '';

	/**
	* Number of tags open currently
	*
	* @var	integer
	*/
	var $tag_count = 0;

	/**
	* Constructor
	*
	* @param	mixed	XML data or boolean false
	* @param	string	Path to XML file to be parsed
	*/
	function XMLparser($xml, $path = '')
	{
		if ($xml !== false)
		{
			$this->xmldata = $xml;
		}
		else
		{
			if (empty($path))
			{
				$this->error_no = 1;
			}
			else if (!($this->xmldata = @file_get_contents($path)))
			{
				$this->error_no = 2;
			}
		}
	}

	/**
	* Parses XML document into an array
	*
	* @param	string	Encoding of the inputted XML file
	* @param	bool	Empty the XML data string after parsing
	*
	* @return	mixed	array or false on error
	*/
	function &parse($encoding = 'ISO-8859-1', $emptydata = true)
	{
		if (empty($this->xmldata) OR $this->error_no > 0)
		{
			return false;
		}

		$this->xml_parser = xml_parser_create($encoding);

		xml_parser_set_option($this->xml_parser, XML_OPTION_SKIP_WHITE, 0);
		xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, 0);
		xml_set_character_data_handler($this->xml_parser, array(&$this, 'handle_cdata'));
		xml_set_element_handler($this->xml_parser, array(&$this, 'handle_element_start'), array(&$this, 'handle_element_end'));

		xml_parse($this->xml_parser, $this->xmldata);
		$err = xml_get_error_code($this->xml_parser);

		if ($emptydata)
		{
			$this->xmldata = '';
			$this->stack = array();
			$this->cdata = '';
		}

		if ($err)
		{
			return false;
		}

		xml_parser_free($this->xml_parser);

		return $this->parseddata;
	}

	/**
	* XML parser callback. Handles CDATA values.
	*
	* @param	resource	Parser that called this
	* @param	string		The CDATA
	*/
	function handle_cdata(&$parser, $data)
	{
		$this->cdata .= $data;
	}

	/**
	* XML parser callback. Handles tag opens.
	*
	* @param	resource	Parser that called this
	* @param	string		The name of the tag opened
	* @param	array		The tag's attributes
	*/
	function handle_element_start(&$parser, $name, $attribs)
	{
		$this->cdata = '';

		foreach ($attribs AS $key => $val)
		{
			if (preg_match('#&[a-z]+;#i', $val))
			{
				$attribs["$key"] = unhtmlspecialchars($val);
			}
		}

		array_unshift($this->stack, array('name' => $name, 'attribs' => $attribs, 'tag_count' => ++$this->tag_count));
	}

	/**
	* XML parser callback. Handles tag closes.
	*
	* @param	resource	Parser that called this
	* @param	string		The name of the tag closed
	*/
	function handle_element_end(&$parser, $name)
	{
		$tag = array_shift($this->stack);
		if ($tag['name'] != $name)
		{
			// there's no reason this should actually happen -- it'd mean invalid xml
			return;
		}

		$output = $tag['attribs'];

		if (trim($this->cdata) !== '' OR $tag['tag_count'] == $this->tag_count)
		{
			if (sizeof($output) == 0)
			{
				$output = $this->unescape_cdata($this->cdata);
			}
			else
			{
				$this->add_node($output, 'value', $this->unescape_cdata($this->cdata));
			}
		}

		if (isset($this->stack[0]))
		{
			$this->add_node($this->stack[0]['attribs'], $name, $output);
		}
		else
		{
			// popped off the first element
			// this should complete parsing
			$this->parseddata = $output;
		}


		$this->cdata = '';
	}

	/**
	* Returns parser error string
	*
	* @return	mixed error message
	*/
	function error_string()
	{
		if ($errorstring = @xml_error_string($this->error_code()))
		{
			return $errorstring;
		}
		else
		{
			return 'unknown';
		}
	}

	/**
	* Returns parser error line number
	*
	* @return	int error line number
	*/
	function error_line()
	{
		if ($errorline = @xml_get_current_line_number($this->xml_parser))
		{
				return $errorline;
		}
		else
		{
			return 0;
		}
	}

	/**
	* Returns parser error code
	*
	* @return	int error line code
	*/
	function error_code()
	{
		if ($errorcode = @xml_get_error_code($this->xml_parser))
		{
			return $errorcode;
		}
		else
		{
			return 0;
		}
	}

	/**
	* Adds node with appropriate logic, multiple values get added to array where unique are their own entry
	*
	* @param	array	Reference to array node has to be added to
	* @param	string	Name of node
	* @param	string	Value of node
	*
	*/
	function add_node(&$children, $name, $value)
	{
		if (!is_array($children) OR !in_array($name, array_keys($children)))
		{ // not an array or its not currently set
			$children[$name] = $value;
		}
		else if (is_array($children[$name]) AND isset($children[$name][0]))
		{ // its the same tag and is already an array
			$children[$name][] = $value;
		}
		else
		{  // its the same tag but its not been made an array yet
			$children[$name] = array($children[$name]);
			$children[$name][] = $value;
		}
	}

	/**
	* Adds node with appropriate logic, multiple values get added to array where unique are their own entry
	*
	* @param	string	XML to have any of our custom CDATAs to be made into CDATA
	*
	*/
	function unescape_cdata($xml)
	{
		static $find, $replace;

		if (!is_array($find))
		{
			$find = array('«![CDATA[', ']]»', "\r\n", "\n");
			$replace = array('<![CDATA[', ']]>', "\n", "\r\n");
		}

		return str_replace($find, $replace, $xml);
	}
}

class XMLexporter
{
	var $open_tags = array();
	var $tabs = "";

	function XMLexporter()
	{
	}

	function add_group($tag, $attr = array())
	{
		$this->open_tags[] = $tag;
		$this->doc .= $this->tabs . $this->build_tag($tag, $attr) . "\n";
		$this->tabs .= "\t";
	}

	function close_group()
	{
		$tag = array_pop($this->open_tags);
		$this->tabs = substr($this->tabs, 0, -1);
		$this->doc .= $this->tabs . "</$tag>\n";
	}

	function add_tag($tag, $content = '', $attr = array(), $cdata = false)
	{
		$this->doc .= $this->tabs . $this->build_tag($tag, $attr, ($content === ''));
		if ($content !== '')
		{
			if ($cdata OR preg_match('/[\<\>\&\'\"\[\]]/', $content))
			{
				$this->doc .= '<![CDATA[' . $this->escape_cdata($content) . ']]>';
			}
			else
			{
				$this->doc .= $content;
			}
			$this->doc .= "</$tag>\n";
		}
	}

	function build_tag($tag, $attr, $closing = false)
	{
		$tmp = "<$tag";
		if (!empty($attr))
		{
			foreach ($attr AS $attr_name => $attr_key)
			{
				if (strpos($attr_key, '"') !== false)
				{
					$attr_key = htmlspecialchars_uni($attr_key);
				}
				$tmp .= " $attr_name=\"$attr_key\"";
			}
		}
		$tmp .= ($closing ? " />\n" : '>');
		return $tmp;
	}

	function escape_cdata($xml)
	{
		// strip invalid characters in XML 1.0:  00-08, 11-12 and 14-31
		// I did not find any character sets which use these characters.
		$xml = preg_replace('#[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]#', '', $xml);

		return str_replace(array('<![CDATA[', ']]>'), array('«![CDATA[', ']]»'), $xml);
	}

	function output()
	{
		return $this->doc;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13426 $
|| ####################################################################
\*======================================================================*/

?>