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

define('VURL_HANDLED',             1);
define('VURL_NEXT',                2);

define('VURL_STATE_HEADERS',  1);
define('VURL_STATE_LOCATION', 2);
define('VURL_STATE_BODY',     3);

/**
* vBulletin remote url class
*
* This class handles sending and returning data to remote urls via cURL
*
* @package 		vBulletin
* @version		$Revision: 92875 $
* @date 		$Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
*
*/
class vB_vURL
{
	/**
	* vBulletin Registry Object
	*
	* @var	string
	*/
	var $registry = null;

	/**
	* Error code
	*
	* @var	int
	*/
	var $error = 0;

	/**
	* Options bitfield
	*
	* @var	integer
	*/
	var $bitoptions = 0;

	/**
	* List of headers by key
	*
	* @var	array
	*/
	var $headerkey = array();

	/**
	* Options Array
	*
	* @var	array
	*/
	var $options = array();

	/**
	* Transport Object Array
	*
	* @var	array
	*/
	var $classnames = array('cURL');

	/**
	* Transport Object Array
	*
	* @var	array
	*/
	var $transports = array();

	/**
	* Temporary filename for storing result
	*
	* @var	string
	*/
	var $tmpfile = null;

	/**
	 * Resets the class to initial settings
	 *
	 */
	function reset()
	{
		$this->bitoptions = 0;
		$this->headerkey = array();
		$this->error = 0;

		$this->options = array(
			VURL_TIMEOUT    => 15,
			VURL_POSTFIELDS => '',
			VURL_ENCODING   => '',
			VURL_USERAGENT  => '',
			VURL_URL        => '',
			VURL_HTTPHEADER => array(),
			VURL_MAXREDIRS  => 5,
			VURL_USERAGENT  => 'vBulletin via PHP',
			VURL_DIEONMAXSIZE => 1
		);

		foreach (array_keys($this->transports) AS $tname)
		{
			$transport =& $this->transports[$tname];
			$transport->reset();
		}

	}

	/**
	* Constructor
	*
	* @param	object	vBulletin Registry Object
	*/
	function __construct(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error('vB_vURL::Registry object is not an object', E_USER_ERROR);
		}

		// create the objects we need
		foreach ($this->classnames AS $classname)
		{
			$fullclass = 'vB_vURL_' . $classname;
			if (class_exists($fullclass))
			{
				$this->transports["$classname"] = new $fullclass($this);
			}
		}
		$this->reset();
	}

	/**
	* Destructor for PHP 5+, this deals with the case that
	* people forget to either unlink or move the file.
	*/
	function __destruct()
	{
		if (file_exists($this->tmpfile))
		{
			@unlink($this->tmpfile);
		}
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
			case VURL_DIEONMAXSIZE:
				$this->options["$option"]	= intval($extra);
				break;
		}
	}

	/**
	* The do it all function
	*
	* @return	mixed		false on failure, array or string on success
	*/
	function exec()
	{
		$result = $this->exec2();

		if (is_array($result))
		{
			if (empty($result['body']) AND file_exists($result['body_file']))
			{
				$result['body'] = file_get_contents($result['body_file']);
				@unlink($result['body_file']);
			}
			if (!($this->bitoptions & VURL_HEADER))
			{
				return $result['body'];
			}
		}

		return $result;
	}

	/**
	* The function which formats the response array, removing what isn't required
	*
	* @param	array		response containng headers and body / body_file
	*
	* @return	mixed		true or array depending on response requested
	*/
	function format_response($response)
	{
		if ($this->bitoptions & VURL_RETURNTRANSFER)
		{
			if ($this->bitoptions & VURL_HEADER)
			{
				$headers = $this->build_headers($response['headers']);

				if ($this->bitoptions & VURL_NOBODY)
				{
					return $headers;
				}
				else
				{
					return $response;
				}
			}
			else if ($this->bitoptions & VURL_NOBODY)
			{
				@unlink($response['body_file']);
				return true;
			}
			else
			{
				unset($response['headers']);
				return $response;
			}
		}
		else
		{
			@unlink($response['body_file']);
			return true;
		}
	}

	/**
	* new vURL method which stores items in a file if it can until needed
	*
	* @return	mixed		false on failure, true or array depending on response requested
	*/
	function exec2()
	{
		if ($this->registry->options['safeupload'])
		{
			$this->tmpfile = $this->registry->options['tmppath'] . '/vbupload' . $this->registry->userinfo['userid'] . substr(TIMENOW, -4);
		}
		else
		{
			$this->tmpfile = @tempnam(ini_get('upload_tmp_dir'), 'vbupload');
		}

		if (empty($this->options[VURL_URL]))
		{
			trigger_error('Must set URL with set_option(VURL_URL, $url)', E_USER_ERROR);
		}

		if (!empty($this->options[VURL_USERAGENT]))
		{
			$this->options[VURL_HTTPHEADER][] = 'User-Agent: ' . $this->options[VURL_USERAGENT];
		}
		if ($this->bitoptions & VURL_CLOSECONNECTION)
		{
			$this->options[VURL_HTTPHEADER][] = 'Connection: close';
		}

		foreach (array_keys($this->transports) AS $tname)
		{
			$transport =& $this->transports[$tname];
			if (($result = $transport->exec()) === VURL_HANDLED  AND !$this->fetch_error())
			{
				return $this->format_response(array('headers' => $transport->response_header, 'body' => $transport->response_text, 'body_file' => $this->tmpfile));
			}

			if ($this->fetch_error())
			{
				return false;
			}

		}

		@unlink($this->tmpfile);
		$this->set_error(VURL_ERROR_NOLIB);
		return false;
	}

	/**
	* Build the headers array
	*
	* @param		string	string of headers split by "\r\n"
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
				if (preg_match('#^http/(1\.[012]) ([12345]\d\d) (.*)#i', $header, $httpmatches))
				{
					$headers['http-response']['version'] = $httpmatches[1];
					$headers['http-response']['statuscode'] = $httpmatches[2];
					$headers['http-response']['statustext'] = $httpmatches[3];
				}
				else if (!empty($header))
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

	/**
	 * Does a HTTP HEAD Request
	 *
	 * @param	string	The URL to do the head request on
	 *
	 * @return	mixed	False on Failure, Array or String on Success
	 *
	 */
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

	/**
	 * Does a HTTP Request, returning the body of the document
	 *
	 * @param	string	The URL
	 * @param	integer	The Maximum Size to get
	 * @param	boolean	Die when we reach the maximum Size?
	 * @param	boolean	Also Get headers?
	 *
	 * @return	mixed	False on Failure, Array or String on Success
	 *
	 */
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
		if (!$dieonmaxsize)
		{
			$this->set_option(VURL_DIEONMAXSIZE, false);
		}
		return $this->exec();
	}
}

class vB_vURL_cURL
{
	/**
	* String that holds the cURL callback data
	*
	* @var	string
	*/
	var $response_text = '';

	/**
	* String that holds the cURL callback data
	*
	* @var	string
	*/
	var $response_header = '';

	/**
	* cURL Handler
	*
	* @var	resource
	*/
	var $ch = null;

	/**
	* vB_vURL object
	*
	* @var	object
	*/
	var $vurl = null;

	/**
	* Filepointer to the temporary file
	*
	* @var	resource
	*/
	var $fp = null;

	/**
	* Length of the current response
	*
	* @var	integer
	*/
	var $response_length = 0;

	/**
	* Private variable when we request headers. Values are one of VURL_STATE_* constants.
	*
	* @var	int
	*/
	var $__finished_headers = VURL_STATE_HEADERS;

	/**
	* If the current result is when the max limit is reached
	*
	* @var	integer
	*/
	var $max_limit_reached = false;

	/**
	* Constructor
	*
	* @param	object	Instance of a vB_vURL Object
	*/
	function __construct(&$vurl_registry)
	{
		if (!is_a($vurl_registry, 'vB_vURL'))
		{
			trigger_error('Direct Instantiation of ' . __CLASS__ . ' prohibited.', E_USER_ERROR);
		}
		$this->vurl =& $vurl_registry;
	}

	/**
	* Callback for handling headers
	*
	* @param	resource	cURL object
	* @param	string		Request
	*
	* @return	integer		length of the request
	*/
	public function curl_callback_header(&$ch, $string)
	{
		if (trim($string) !== '')
		{
			$this->response_header .= $string;
		}
		return strlen($string);
	}

	/**
	* Callback for handling the request body
	*
	* @param	resource	cURL object
	* @param	string		Request
	*
	* @return	integer		length of the request
	*/
	public function curl_callback_response(&$ch, $response)
	{
		$chunk_length = strlen($response);

		/* We receive both headers + body */
		if ($this->vurl->bitoptions & VURL_HEADER)
		{
			if ($this->__finished_headers != VURL_STATE_BODY)
			{
				if ($this->vurl->bitoptions & VURL_FOLLOWLOCATION AND preg_match('#(?<=\r\n|^)Location:#i', $response))
				{
					$this->__finished_headers = VURL_STATE_LOCATION;
				}

				if ($response === "\r\n")
				{
					if ($this->__finished_headers == VURL_STATE_LOCATION)
					{
						// found a location -- still following it; reset the headers so they only match the new request
						$this->response_header = '';
						$this->__finished_headers = VURL_STATE_HEADERS;
					}
					else
					{
						// no location -- we're done
						$this->__finished_headers = VURL_STATE_BODY;
					}
				}

				return $chunk_length;
			}
		}

		// no filepointer and we're using or about to use more than 100k
		if (!$this->fp AND $this->response_length + $chunk_length >= 1024*100)
		{
			if ($this->fp = @fopen($this->vurl->tmpfile, 'wb'))
			{
				fwrite($this->fp, $this->response_text);
				unset($this->response_text);
			}
		}

		if ($this->fp AND $response)
		{
			fwrite($this->fp, $response);
		}
		else
		{
			$this->response_text .= $response;

		}

		$this->response_length += $chunk_length;

		if (!empty($this->vurl->options[VURL_MAXSIZE]) AND $this->response_length > $this->vurl->options[VURL_MAXSIZE])
		{
			$this->max_limit_reached = true;
			$this->vurl->set_error(VURL_ERROR_MAXSIZE);
			return false;
		}

		return $chunk_length;
	}

	/**
	* Clears all previous request info
	*/
	public function reset()
	{
		$this->response_text = '';
		$this->response_header = '';
		$this->response_length = 0;
		$this->__finished_headers = VURL_STATE_HEADERS;
		$this->max_limit_reached = false;
		$this->closeTempFile();
	}

	/**
	* Performs fetching of the file if possible
	*
	* @return	integer		Returns one of two constants, VURL_NEXT or VURL_HANDLED
	*/
	function exec()
	{

		$urlinfo = @parse_url($this->vurl->options[VURL_URL]);


		if(!$this->validateUrl($urlinfo))
		{
			return VURL_NEXT;
		}


		if (!function_exists('curl_init') OR ($this->ch = curl_init()) === false)
		{
			return VURL_NEXT;
		}


		curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->vurl->options[VURL_TIMEOUT]);
		if (!empty($this->vurl->options[VURL_CUSTOMREQUEST]))
		{
			curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $this->vurl->options[VURL_CUSTOMREQUEST]);
		}
		else if ($this->vurl->bitoptions & VURL_POST)
		{
			curl_setopt($this->ch, CURLOPT_POST, 1);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->vurl->options[VURL_POSTFIELDS]);
		}
		else
		{
			curl_setopt($this->ch, CURLOPT_POST, 0);
		}
		curl_setopt($this->ch, CURLOPT_HEADER, ($this->vurl->bitoptions & VURL_HEADER) ? 1 : 0);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->vurl->options[VURL_HTTPHEADER]);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, ($this->vurl->bitoptions & VURL_RETURNTRANSFER) ? 1 : 0);
		if ($this->vurl->bitoptions & VURL_NOBODY)
		{
			curl_setopt($this->ch, CURLOPT_NOBODY, 1);
		}

		//never use CURLOPT_FOLLOWLOCATION -- we need to make sure we are as careful with the
		//urls returned from the server as we are about the urls we initially load.
		//we'll loop internally up to the recommended tries.
		$redirect_tries = 1;

		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 0);
		if ($this->vurl->bitoptions & VURL_FOLLOWLOCATION)
		{
			$redirect_tries = $this->vurl->options[VURL_MAXREDIRS];
		}

		if ($redirect_tries < 1)
		{
			$redirect_tries = 1;
		}

		if ($this->vurl->options[VURL_ENCODING])
		{
			// this will work on versions of cURL after 7.10, though was broken on PHP 4.3.6/Win32
			@curl_setopt($this->ch, CURLOPT_ENCODING, $this->vurl->options[VURL_ENCODING]);
		}

		$this->response_text = '';
		$this->response_header = '';

		curl_setopt($this->ch, CURLOPT_WRITEFUNCTION, array(&$this, 'curl_callback_response'));
		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array(&$this, 'curl_callback_header'));

		if (!($this->vurl->bitoptions & VURL_VALIDSSLONLY))
		{
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
		}

		$url = $this->vurl->options[VURL_URL];

		$redirectCodes = array(301, 302, 307, 308);
		for ($i = $redirect_tries; $i > 0; $i--)
		{
			$isHttps = ($urlinfo['scheme'] == 'https');
			if ($isHttps)
			{
				// curl_version crashes if no zlib support in cURL (php <= 5.2.5)
				$curlinfo = curl_version();
				if (empty($curlinfo['ssl_version']))
				{
					curl_close($this->ch);
					return VURL_NEXT;
				}
			}

			$result = $this->execCurl($url, $isHttps);

			//if we don't have another iteration of the loop to go, skip the effort here.
			if (($i > 1) AND in_array(curl_getinfo($this->ch, CURLINFO_HTTP_CODE), $redirectCodes))
			{
				$url = curl_getinfo($this->ch, CURLINFO_REDIRECT_URL);
				$urlinfo = @parse_url($url);

				if(!$this->validateUrl($urlinfo))
				{
					$this->closeTempFile();
					return VURL_NEXT;
				}
			}
		}

		//if we are following redirects and still have a redirect code, its because we hit our limit without finding a real page
		//we want the fallback code to mimic the behavior of curl in this case
		if (($this->vurl->bitoptions & VURL_FOLLOWLOCATION) && in_array(curl_getinfo($this->ch, CURLINFO_HTTP_CODE), $redirectCodes))
		{
			$this->closeTempFile();
			return VURL_NEXT;
		}

		//close the connection and clean up the file.
		curl_close($this->ch);
		$this->closeTempFile();

		if ($result !== false OR (!$this->vurl->options[VURL_DIEONMAXSIZE] AND $this->max_limit_reached))
		{
			return VURL_HANDLED;
		}

		return VURL_NEXT;
	}


	private function closeTempFile()
	{
		if ($this->fp)
		{
			fclose($this->fp);
			$this->fp = null;
		}
	}

	/**
	 *	Actually load the url from the interweb
	 *	@param string $url
	 *	@params boolean $isHttps
	 *
	 *	@return string|false The result of curl_exec
	 */
	private function execCurl($url, $isHttps)
	{
		$this->reset();
		curl_setopt($this->ch, CURLOPT_URL, $url);
		$result = curl_exec($this->ch);

		if ($isHttps AND $result === false AND curl_errno($this->ch) == '60') ## CURLE_SSL_CACERT problem with the CA cert (path? access rights?)
		{
			curl_setopt($this->ch, CURLOPT_CAINFO, DIR . '/includes/paymentapi/ca-bundle.crt');
			$result = curl_exec($this->ch);
		}


		return $result;
	}


	/**
	 *	Determine if the url is safe to load
	 *
	 *	@param $urlinfo -- The parsed url info from vB_String::parseUrl -- scheme, port, host
	 * 	@return boolean
	 */
	private function validateUrl($urlinfo)
	{
		// VBV-11823, only allow http/https schemes
		if (!isset($urlinfo['scheme']) OR !in_array(strtolower($urlinfo['scheme']), array('http', 'https')))
		{
			return false;
		}

		// VBV-11823, do not allow localhost and 127.0.0.0/8 range by default
		if (!isset($urlinfo['host']) OR preg_match('#localhost|127\.(\d)+\.(\d)+\.(\d)+#i', $urlinfo['host']))
		{
			return false;
		}

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

		// VBV-11823, restrict detination ports to 80 and 443 by default
		// allow the admin to override the allowed ports in config.php (in case they have a proxy server they need to go to).
		$config = $this->vurl->registry->config;
		$allowedPorts = isset($config['Misc']['uploadallowedports']) ? $config['Misc']['uploadallowedports'] : array();
		if (!is_array($allowedPorts))
		{
			$allowedPorts = array(80, 443, $allowedPorts);
		}
		else
		{
			$allowedPorts = array_merge(array(80, 443), $allowedPorts);
		}

		if (!in_array($urlinfo['port'], $allowedPorts))
		{
			return false;
		}

		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
