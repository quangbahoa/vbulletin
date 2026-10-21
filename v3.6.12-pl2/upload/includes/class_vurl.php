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

define('VURL_URL',                 1);
define('VURL_TIMEOUT',             2);
define('VURL_POST',                4);
define('VURL_HEADER',              8);
define('VURL_POSTFIELDS',         16);
define('VURL_ENCODING',           32);
define('VURL_USERAGENT',          64);
define('VURL_RETURNTRANSFER',    128);
define('VURL_HTTPHEADER',        256);

define('VURL_CLOSECONNECTION',  1024);
define('VURL_FOLLOWLOCATION',   2048);
define('VURL_MAXREDIRS',        4096);
define('VURL_NOBODY',           8192);
define('VURL_CUSTOMREQUEST',   16384);
define('VURL_MAXSIZE',         32768);
define('VURL_DIEONMAXSIZE',    65536);
define('VURL_VALIDSSLONLY',   131072);

define('VURL_ERROR_MAXSIZE',       1);
define('VURL_ERROR_SSL',           2);
define('VURL_ERROR_URL',           4);
define('VURL_ERROR_NOLIB',         8);

/**
* vBulletin remote url class
*
* This class handles sending and returning data to remote urls via cURL and fsockopen
*/
class vB_vURL
{
	/**
	* vBulletin Registry Object
	*
	* @string
	*/
	var $registry = null;

	/**
	* Error code
	*
	* @int
	*/
	var $error = 0;

	/**
	* Ability to use cURL
	*
	* @boolean
	*/
	var $usecurl = false;

	/**
	* fsockopen will always be used if curl is not available. This controls whether we still try fsockopen if curl fails upon curl_exec() - fsockopen is most likely going to fail as well
	*
	* @boolean
	*/
	var $stoponcurl = true;

	/**
	* Ability to use fsockopen()
	*
	* @boolean
	*/
	var $usefsockopen = true;

	/**
	* cURL Handler
	*
	* @resource
	*/
	var $ch = null;

	/**
	* Options bitfield
	*
	* @integer
	*/
	var $bitoptions = 0;

	/**
	* String that holds the cURL callback data
	*
	* @string
	*/
	var $curlresponse = '';

	/**
	* String that holds the cURL callback data
	*
	* @string
	*/
	var $curlheader = '';

	/**
	* List of headers by key
	*
	* @array
	*/
	var $headerkey = array();

	/**
	* Options Array
	*
	* @array
	*/
	var $options = array();

	function reset()
	{
		$this->bitoptions = 0;
		$this->headerkey = array();
		$this->error = 0;
		$this->curlresponse = '';
		$this->curlheader = '';

		$this->options = array(
			VURL_TIMEOUT    => 15,
			VURL_POSTFIELDS => '',
			VURL_ENCODING   => '',
			VURL_USERAGENT  => '',
			VURL_URL        => '',
			VURL_HTTPHEADER => array(),
			VURL_MAXREDIRS  => 5,
			VURL_USERAGENT  => 'vBulletin via PHP'
		);
	}

	/**
	* Constructor
	*
	* @param	object	vBulletin Registry Object
	*/
	function vB_vURL(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error('vB_vURL::Registry object is not an object', E_USER_ERROR);
		}

		$this->usecurl = (function_exists('curl_init') AND $this->ch = curl_init());
		$this->reset();
	}

	/**
	* On/Off options
	*
	* @param		integer	one of the VURL_* defines
	* @param		mixed		option to set
	*
	*/
	function set_option($option, $extra)
	{
		switch ($option)
		{
			case VURL_POST:
			case VURL_HEADER:
			case VURL_NOBODY:
			case VURL_FOLLOWLOCATION:
			case VURL_RETURNTRANSFER:
			case VURL_CLOSECONNECTION:
			case VURL_DIEONMAXSIZE:
			case VURL_VALIDSSLONLY:
				if ($extra == 1 OR $extra == true)
				{
					$this->bitoptions = $this->bitoptions | $option;
				}
				else
				{
					$this->bitoptions = $this->bitoptions & ~$option;
				}
				break;
			case VURL_TIMEOUT:
				if ($extra == 1 OR $extra == true)
				{
					$this->options[VURL_TIMEOUT] = intval($extra);
				}
				else
				{
					$this->options[VURL_TIMEOUT] = 15;
				}
				break;
			case VURL_POSTFIELDS:
				if ($extra == 1 OR $extra == true)
				{
					$this->options[VURL_POSTFIELDS] = $extra;
				}
				else
				{
					$this->options[VURL_POSTFIELDS] = '';
				}
				break;
			case VURL_ENCODING:
			case VURL_USERAGENT:
			case VURL_URL:
			case VURL_CUSTOMREQUEST:
				$this->options["$option"] = $extra;
				break;
			case VURL_HTTPHEADER:
				if (is_array($extra))
				{
					$this->headerkey = array();
					$this->options[VURL_HTTPHEADER] = $extra;
					foreach ($extra AS $line)
					{
						list($header, $value) = explode(': ', $line, 2);
						$this->headerkey[strtolower($header)] = $value;
					}
				}
				else
				{
					$this->options[VURL_HTTPHEADER] = array();
					$this->headerkey = array();
				}
				break;
			case VURL_MAXSIZE:
			case VURL_MAXREDIRS:
				$this->options["$option"]	= intval($extra);
				break;
		}
	}

	/**
	* The do it all function
	*
	* @param		boolean	exec has been called recursively
	*
	* @return	mixed		false on failure, array or string on success
	*/
	function exec($followlocation = false)
	{
		static $counter = 0;

		if (empty($this->options[VURL_URL]))
		{
			trigger_error('Must set URL with set_option(VURL_URL, $url)', E_USER_ERROR);
		}

		if (!$followlocation AND $this->options[VURL_USERAGENT])
		{
			$this->options[VURL_HTTPHEADER][] = 'User-Agent: ' . $this->options[VURL_USERAGENT];
		}
		if (!$followlocation AND $this->bitoptions & VURL_CLOSECONNECTION)
		{
			$this->options[VURL_HTTPHEADER][] = 'Connection: close';
		}

		$urlinfo = @parse_url($this->options[VURL_URL]);
		if (empty($urlinfo['port']))
		{
			if ($urlinfo['scheme'] == 'https')
			{
				$urlinfo['port'] = 443;
			}
			else
			{
				$urlinfo['port'] = 80;
			}
		}

		if (!$followlocation AND $this->usecurl)
		{
			$this->ch = curl_init();

			$no_ssl_use_fsockopen = false;

			if ($urlinfo['scheme'] == 'https')
			{
				// curl_version crashes if no zlib support in cURL (php <= 5.2.5)
				$curlinfo = curl_version();
				if (empty($curlinfo['ssl_version']))
				{
					$no_ssl_use_fsockopen = true;
				}
			}

			if ($no_ssl_use_fsockopen)
			{
				$this->usecurl = false;
				curl_close($this->ch);
			}
			else
			{
				curl_setopt($this->ch, CURLOPT_URL, $this->options[VURL_URL]);
				curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->options[VURL_TIMEOUT]);
				if ($this->options[VURL_CUSTOMREQUEST])
				{
					curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $this->options[VURL_CUSTOMREQUEST]);
				}
				else if ($this->bitoptions & VURL_POST)
				{
					curl_setopt($this->ch, CURLOPT_POST, 1);
					curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->options[VURL_POSTFIELDS]);
				}
				else
				{
					curl_setopt($this->ch, CURLOPT_POST, 0);
				}
				curl_setopt($this->ch, CURLOPT_HEADER, ($this->bitoptions & VURL_HEADER) ? 1 : 0);
				curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->options[VURL_HTTPHEADER]);
				curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, ($this->bitoptions & VURL_RETURNTRANSFER) ? 1 : 0);
				@curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, ($this->bitoptions & VURL_FOLLOWLOCATION) ? 1 : 0); // disabled in safe_mode/open_basedir in PHP 5.1.6/4.4.4
				if ($this->bitoptions & VURL_NOBODY)
				{
					curl_setopt($this->ch, CURLOPT_NOBODY, 1);
				}
				if ($this->bitoptions & VURL_FOLLOWLOCATION)
				{
					curl_setopt($this->ch, CURLOPT_MAXREDIRS, $this->options[VURL_MAXREDIRS]);
				}
				if ($this->options[VURL_ENCODING])
				{
					@curl_setopt($this->ch, CURLOPT_ENCODING, $this->options[VURL_ENCODING]); // this will work on versions of cURL after 7.10, though was broken on PHP 4.3.6/Win32
				}

				if ($this->options[VURL_MAXSIZE])
				{
					$this->curlresponse = '';
					$this->curlheader = '';
					curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array(&$this, 'curl_callback_header'));
					curl_setopt($this->ch, CURLOPT_WRITEFUNCTION, array(&$this, 'curl_callback_response'));
				}

				if (!($this->bitoptions & VURL_VALIDSSLONLY))
				{
					curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
					curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
				}

				$result = curl_exec($this->ch);

				if ($this->options[VURL_MAXSIZE] AND ($result !== false OR (!$this->options[VURL_DIEONMAXSIZE] AND !empty($this->curlresponse))))
				{
					if ($this->bitoptions & VURL_HEADER AND !($this->bitoptions & VURL_NOBODY))
					{	// headers AND body
						$result =& $this->curlresponse;
						$length = strlen($this->curlresponse) - strlen($this->curlheader);
					}
					else if ($this->bitoptions & VURL_HEADER)
					{	// just headers
						$result =& $this->curlheader;
					}
					else if (!($this->bitoptions & VURL_NOBODY))
					{	// Just body
						$result = preg_replace('#^' . preg_quote($this->curlheader, '#') . '#s', '', $this->curlresponse);
						$length = strlen($result);
					}

					if ($length AND $length > $this->options[VURL_MAXSIZE] AND $this->options[VURL_DIEONMAXSIZE])
					{
						$this->set_error(VURL_ERROR_MAXSIZE);
						return false;
					}
				}

				if ($urlinfo['scheme'] == 'https' AND $result === false AND curl_errno($this->ch) == '60') ## CURLE_SSL_CACERT problem with the CA cert (path? access rights?)
				{
					curl_setopt($this->ch, CURLOPT_CAINFO, DIR . '/includes/paymentapi/ca-bundle.crt');
					$result = curl_exec($this->ch);
				}

				curl_close($this->ch);

				if ($result !== false)
				{
					if ($this->bitoptions & VURL_RETURNTRANSFER)
					{
						if ($this->bitoptions & VURL_HEADER)
						{
							preg_match('#^(.*)\r\n\r\n(.*)$#sU', $result, $matches);

							if ($this->bitoptions & VURL_FOLLOWLOCATION)
							{
								while (preg_match("#\r\nLocation: #i", $matches[1]))
								{
									preg_match('#^(.*)\r\n\r\n(.*)$#sU', $matches[2], $matches);
								}
							}

							$headers = $this->build_headers($matches[1]);

							if ($this->bitoptions & VURL_NOBODY)
							{
								return $headers;
							}
							else
							{
								return array('headers' => $headers, 'body' => $matches[2]);
							}
						}
						else
						{
							return $result;
						}
					}
					else
					{
						return true;
					}
				}
				else if ($this->stoponcurl)
				{
					$this->set_error(VURL_ERROR_URL);
					curl_close($this->ch);
					return false;
				}
			}
		}

		if ($this->usefsockopen)
		{
			if ($urlinfo['scheme'] == 'https')
			{
				if (!function_exists('openssl_open'))
				{
					$this->set_error(VURL_ERROR_SSL);
					return false;
				}
				$scheme = 'ssl://';
			}

			if ($fp = @fsockopen($scheme . $urlinfo['host'], $urlinfo['port'], $errno, $errstr, $this->options[VURL_TIMEOUT]))
			{
				$headers = array();
				if ($this->bitoptions & VURL_NOBODY)
				{
					$this->options[VURL_CUSTOMREQUEST] = 'HEAD';
				}
				if ($this->options[VURL_CUSTOMREQUEST])
				{
					$headers[] = $this->options[VURL_CUSTOMREQUEST] . " $urlinfo[path]" . ($urlinfo['query'] ? "?$urlinfo[query]" : '') . " HTTP/1.0";
				}
				else if ($this->bitoptions & VURL_POST)
				{
					$headers[] = "POST $urlinfo[path]" . ($urlinfo['query'] ? "?$urlinfo[query]" : '') . " HTTP/1.0";
					if (empty($this->headerkey['content-type']))
					{
						$headers[] = 'Content-Type: application/x-www-form-urlencoded';
					}
					if (empty($this->headerkey['content-length']))
					{
						$headers[] = 'Content-Length: ' . strlen($this->options[VURL_POSTFIELDS]);
					}
				}
				else
				{
					$headers[] = "GET $urlinfo[path]" . ($urlinfo['query'] ? "?$urlinfo[query]" : '') . " HTTP/1.0";
				}
				$headers[] = "Host: $urlinfo[host]";
				if (!empty($this->options[VURL_HTTPHEADER]))
				{
					$headers = array_merge($headers, $this->options[VURL_HTTPHEADER]);
				}
				if ($this->options[VURL_ENCODING])
				{
					$encodemethods = explode(',', $this->options[VURL_ENCODING]);
					$finalmethods = array();
					foreach ($encodemethods AS $type)
					{
						$type = strtolower(trim($type));
						if ($type == 'gzip' AND function_exists('gzinflate'))
						{
							$finalmethods[] = 'gzip';
						}
						else if ($type == 'deflate' AND function_exists('gzinflate'))
						{
							$finalmethods[] = 'deflate';
						}
						else
						{
							$finalmethods[] = $type;
						}
					}

					if (!empty($finalmethods))
					{
						$headers[] = "Accept-Encoding: " . implode(', ', $finalmethods);
					}
				}

				$output = implode("\r\n", $headers) . "\r\n\r\n";
				if ($this->bitoptions & VURL_POST)
				{
					$output .= $this->options[VURL_POSTFIELDS];
				}

				if (fputs($fp, $output, strlen($output)))
				{
					stream_set_timeout($fp, $this->options[VURL_TIMEOUT]);
					$result = '';
					$headersize = 0;
					while (!feof($fp))
					{
						$results = @fread($fp, 2048);

						// try to grab the header size after the first packet, if we miss it then we won't worry about it as it just allows a slightly larger file to be read
						if (!$result AND preg_match("#(.*)\r\n\r\n#sU", $results, $headmatches))
						{
							$headersize = strlen($headmatches[0]);
						}
						$result .= $results;
						if ($this->options[VURL_MAXSIZE] AND (strlen($result) - $headersize) > $this->options[VURL_MAXSIZE])
						{
							if ($this->options[VURL_DIEONMAXSIZE])
							{
								$this->set_error(VURL_ERROR_MAXSIZE);
								return false;
							}
							else
							{
								break;
							}
						}
					}
					fclose($fp);

					preg_match('#^(.*)\r\n\r\n(.*)$#sU', $result, $matches);
					unset($result);

					if ($this->bitoptions & VURL_FOLLOWLOCATION AND preg_match("#\r\nLocation: (.*)\r\n#siU", $matches[1], $location) AND $counter < $this->options[VURL_MAXREDIRS])
					{
						$counter++;
						$this->set_option(VURL_URL, trim($location[1]));
						return $this->exec(true);
					}

					if ($this->bitoptions & VURL_RETURNTRANSFER)
					{
						if (function_exists('gzinflate'))
						{
							if (preg_match("#\r\nContent-encoding: gzip\r\n#i", $matches[1]))
							{
								if ($inflated = @gzinflate(substr($matches[2], 10)))
								{
									$matches[2] =& $inflated;
								}
							}
							else if (preg_match("#\r\nContent-encoding: deflate\r\n#i", $matches[1]))
							{
								if ($inflated = @gzinflate(substr($matches[2], 2)))
								{
									$matches[2] =& $inflated;
								}
								else if ($inflated = @gzinflate($matches[2]))
								{
									$matches[2] =& $inflated;
								}
							}
						}

						if ($this->bitoptions & VURL_HEADER)
						{
							$headers = $this->build_headers($matches[1]);

							if ($this->bitoptions & VURL_NOBODY)
							{
								return $headers;
							}
							else
							{
								return array('headers' => $headers, 'body' => $matches[2]);
							}
						}
						else if ($this->bitoptions & VURL_NOBODY)
						{
							return true;
						}
						else
						{
							return $matches[2];
						}
					}
					else
					{
						return true;
					}
				}
			}

			$this->set_error(VURL_ERROR_URL);
			return false;
		}

		$this->set_error(VURL_ERROR_NOLIB);
		return false;
	}

	/**
	* Build the headers array
	*
	* @param		string	string of headers split by "/r/n"
	*
	* @return	array
	*/
	function build_headers($data)
	{
			$returnedheaders = explode("\r\n", $data);
			$headers = array();
			foreach ($returnedheaders AS $line)
			{
				list($header, $value) = explode(': ', $line, 2);
				if (preg_match("#^http/(1\.[012]) ([12345]\d\d) (.*)#i", $header, $httpmatches))
				{
					$headers['http-response']['version'] = $httpmatches[1];
					$headers['http-response']['statuscode'] = $httpmatches[2];
					$headers['http-response']['statustext'] = $httpmatches[3];
				}
				else
				{
					$headers[strtolower($header)] = $value;
				}
			}

			return $headers;
	}

	/**
	* Set Error
	*
	* @param		integer	Error Code
	*
	*/
	function set_error($errorcode)
	{
		$this->error = $errorcode;
	}

	/**
	* Return Error
	*
	* @return	integer
	*/
	function fetch_error()
	{
		return $this->error;
	}

	function curl_callback_response(&$ch, $string)
	{
		$this->curlresponse .= $string;
		if ((strlen($this->curlresponse) - strlen($this->curlheader)) > $this->options[VURL_MAXSIZE])
		{
			if ($this->options[VURL_DIEONMAXSIZE])
			{
				$this->set_error(VURL_ERROR_MAXSIZE);
			}
			return false;
		}
		else
		{
			return strlen($string);
		}
	}

	function curl_callback_header(&$ch, $string)
	{
		$this->curlheader .= $string;
		return strlen($string);
	}

	function fetch_head($url)
	{
		$this->reset();
		$this->set_option(VURL_URL, $url);
		$this->set_option(VURL_RETURNTRANSFER, true);
		$this->set_option(VURL_HEADER, true);
		$this->set_option(VURL_NOBODY, true);
		$this->set_option(VURL_CUSTOMREQUEST, 'HEAD');
		$this->set_option(VURL_CLOSECONNECTION, 1);
		return $this->exec();
	}

	function fetch_body($url, $maxsize, $dieonmaxsize, $returnheaders)
	{
		$this->reset();
		$this->set_option(VURL_URL, $url);
		$this->set_option(VURL_RETURNTRANSFER, true);
		if (intval($maxsize))
		{
			$this->set_option(VURL_MAXSIZE, $maxsize);
		}
		if ($returnheaders)
		{
			$this->set_option(VURL_HEADER, true);
		}
		if ($dieonmaxsize)
		{
			$this->set_option(VURL_DIEONMAXSIZE, true);
		}
		return $this->exec();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15670 $
|| ####################################################################
\*======================================================================*/
?>