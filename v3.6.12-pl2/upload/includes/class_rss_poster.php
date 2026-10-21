<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
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

require_once(DIR . '/includes/class_xml.php');

function &fetch_file_via_socket($rawurl, $postfields = array())
{
	$url = @parse_url($rawurl);

	if (!$url OR empty($url['host']))
	{
		return false;
		//trigger_error('Invalid URL specified to fetch_file_via_socket()', E_USER_ERROR);
	}

	if ($url['scheme'] == 'https')
	{
		$url['port'] = ($url['port'] ? $url['port'] : 443);
	}
	else
	{
		$url['port'] = ($url['port'] ? $url['port'] : 80);
	}
	$url['path'] = ($url['path'] ? $url['path'] : '/');

	if (empty($postfields))
	{
		if ($url['query'])
		{
			$url['path'] .= "?$url[query]";
		}
		$url['query'] = '';
		$method = 'GET';
	}
	else
	{
		$fields = array();
		foreach($postfields AS $key => $value)
		{
			if (!empty($value))
			{
				$fields[] = $key . '=' . urlencode($value);
			}
		}
		$url['query'] = implode('&', $fields);
		$method = 'POST';
	}

	$communication = false;

	if (function_exists('curl_init') AND $ch = curl_init())
	{
		curl_setopt($ch, CURLOPT_URL, $rawurl);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		if ($method == 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $url['query']);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'vBulletin via cURL/PHP');
		@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // disabled in safe_mode/open_basedir in PHP 5.1.6
		@curl_setopt($ch, CURLOPT_ENCODING, 'gzip'); // this will work on versions of cURL after 7.10, though was broken on PHP 4.3.6

		$full_result = curl_exec($ch);
		if ($full_result === false AND curl_errno($ch) == '60') ## CURLE_SSL_CACERT problem with the CA cert (path? access rights?)
		{
			curl_setopt($ch, CURLOPT_CAINFO, DIR . '/includes/paymentapi/ca-bundle.crt');
			$full_result = curl_exec($ch);
		}
		curl_close($ch);

		if ($full_result !== false)
		{
			$communication = true;
		}
	}

	if (!$communication)
	{
		if (VB_AREA == 'AdminCP')
		{
			$fp = fsockopen($url['host'], $url['port'], $errno, $errstr, 5);
		}
		else
		{
			$fp = @fsockopen($url['host'], $url['port'], $errno, $errstr, 5);
		}
		if (!$fp)
		{
			return false;
			//trigger_error("Unable to connect to host <i>$url[host]</i>.<br />$errstr", E_USER_ERROR);
		}
		socket_set_timeout($fp, 5);

		$headers = "$method $url[path] HTTP/1.0\r\n";
		$headers .= "Host: $url[host]\r\n";
		$headers .= "User-Agent: vBulletin RSS Reader\r\n";
		if (function_exists('gzinflate'))
		{
			$headers .= "Accept-Encoding: gzip\r\n";
		}
		if ($method == 'POST')
		{
			$headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$headers .= "Content-Length: " . strlen($url['query']) . "\r\n";
		}
		$headers .= "\r\n";

		fwrite($fp, $headers . $url['query']);

		$full_result = '';
		while (!feof($fp))
		{
			$result = fgets($fp, 1024);
			$full_result .= $result;
		}

		fclose($fp);
	}

	preg_match('#^(.*)\r\n\r\n(.*)$#sU', $full_result, $matches);
	unset($full_result);

	// when communication is true we've used cURL so lets check for redirect
	if ($communication)
	{
		while (preg_match("#\r\nLocation: #i", $matches[1]))
		{
			preg_match('#^(.*)\r\n\r\n(.*)$#sU', $matches[2], $matches);
		}
	}

	if (function_exists('gzinflate') AND preg_match("#\r\nContent-encoding: gzip\r\n#i", $matches[1]))
	{
		if ($inflated = @gzinflate(substr($matches[2], 10)))
		{
			$matches[2] =& $inflated;
		}
	}

	return array('headers' => $matches[1], 'body' => $matches[2]);
}

function getElementsByTagName(&$array, $tagname, $init = false)
{
	static $output = array();

	if ($init)
	{
		$output = array();
	}

	if (is_array($array))
	{
		foreach (array_keys($array) AS $key)
		{
			if ($key === $tagname) // encountered an oddity with RDF feeds where == was evaluating to true when key was 0 and $tagname was 'item'
			{
				if (is_array($array["$key"]))
				{
					if ($array["$key"][0])
					{
						foreach (array_keys($array["$key"]) AS $item_key)
						{
							$output[] =& $array["$key"]["$item_key"];
						}
					}
					else
					{
						$output[] =& $array["$key"];
					}
				}
			}
			else if (is_array($array["$key"]))
			{
				getElementsByTagName($array["$key"], $tagname);
			}
		}
	}

	return $output;
}

class vB_RSS_Poster
{
	var $registry = null;
	var $xml_string = null;
	var $xml_array = null;
	var $xml_object = null;
	var $template = null;

	function vB_RSS_Poster(&$registry)
	{
		$this->registry =& $registry;
	}

	function set_xml_string(&$xml_string)
	{
		$this->xml_string =& $xml_string;
	}

	function fetch_xml($url)
	{
		$xml_string =& fetch_file_via_socket($url);
		if ($xml_string === false OR empty($xml_string['body']))
		{ // error returned
			if (VB_AREA == 'AdminCP')
			{
				trigger_error('Unable to fetch RSS Feed', E_USER_WARNING);
			}
		}
		$xml_string = $xml_string['body'];

		// There are some RSS feeds that embed (HTML) tags within the description without
		// CDATA. While this is actually invalid, try to workaround it by wrapping the
		// contents in CDATA if it contains a < and is not in CDATA already.
		// This must be done before parsing because our parser can't handle the output.
		if (preg_match_all('#(<description>)(.*)(</description>)#siU', $xml_string, $matches, PREG_SET_ORDER))
		{
			foreach ($matches AS $match)
			{
				if (strpos(strtoupper($match[2]), '<![CDATA[') === false AND strpos($match[2], '<') !== false)
				{
					// no CDATA tag, but we have an HTML tag
					$output = $match[1] . '<![CDATA[' . vB_XML_Builder::escape_cdata($match[2]) . ']]>' . $match[3];
					$xml_string = str_replace($match[0], $output, $xml_string);
				}
			}
		}

		$this->set_xml_string($xml_string);
	}

	function parse_xml()
	{
		$this->xml_object = new vB_XML_Parser($this->xml_string);
		if ($this->xml_object->parse_xml())
		{
			$this->xml_array =& $this->xml_object->parseddata;
			return true;
		}
		else
		{
			$this->xml_array = array();
			return false;
		}
	}

	function fetch_item($id = -1)
	{
		static $count = 0;

		if (is_array($this->xml_array['channel']['item'][0]))
		{
			#echo "<p>it's an array</p>";
			$item =& $this->xml_array['channel']['item'][($id == -1 ? $count++ : $id)];
		}
		else if ($count == 0 OR $id == 0)
		{
			#echo "<p>it's not an array</p>";
			$item =& $this->xml_array['channel']['item'];
		}
		else
		{
			#echo "<p>it's broken</p>";
			$item = null;
		}

		return $item;
	}

	function fetch_items()
	{
		return getElementsByTagName($this->xml_array, 'item', true);
	}

	function parse_template($template, $item, $unhtmlspecialchars = true)
	{
		if (preg_match_all('#\{rss:([\w:]+)\}#siU', $template, $matches))
		{
			foreach ($matches[1] AS $field)
			{
				if (is_array($item["$field"]))
				{
					if (is_string($item["$field"]['value']))
					{
						$replace =& $item["$field"]['value'];
					}
					else
					{
						$replace = '';
					}
				}
				else
				{
					$replace =& $item["$field"];
				}
				$template = preg_replace("#\{rss:$field\}#siU", str_replace('$', '\$', $replace), $template);
			}
		}

		if ($unhtmlspecialchars)
		{
			$template = unhtmlspecialchars($template);
		}

		return $template;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 26552 $
|| ####################################################################
\*======================================================================*/
?>