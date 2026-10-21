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

define('GIF', 1);
define('JPG', 2);
define('PNG', 3);

if (function_exists('imagegif') AND version_compare(PHP_VERSION, '4.3.9', '>='))
{
	define('IMAGEGIF', true);
}
else
{
	define('IMAGEGIF', false);
}

if (function_exists('imagejpeg'))
{
	define('IMAGEJPEG', true);
}
else
{
	define('IMAGEJPEG', false);
}

if (function_exists('imagepng') AND $vbulletin->options['thumbpng'])
{
	define('IMAGEPNG', true);
}
else
{
	define('IMAGEPNG', false);
}

/**
* Abstracted image class
*
* @package 		vBulletin
* @version		$Revision: 15853 $
* @date 		$Date: 2006-11-06 13:55:50 -0600 (Mon, 06 Nov 2006) $
*
*/
class vB_Image
{
	/**
	* Constructor
	* Does nothing :p
	*
	* @return	void
	*/
	function vB_Image() {}

	/**
	* Select image library
	*
	* @return	object
	*/
	function &fetch_library(&$registry, $type = 'image')
	{
		// Library used for thumbnails, image functions
		if ($type == 'image')
		{
			$selectclass = 'vB_Image_' . ($registry->options['imagetype'] ? $registry->options['imagetype'] : 'GD');
		}
		// Library used for Verification Image
		else
		{
			$selectclass = 'vB_Image_' . ($registry->options['regimagetype'] ? $registry->options['regimagetype'] : 'GD');
		}
		$object = new $selectclass($registry);
		return $object; // function defined as returning & must return a defined variable
	}
}

/**
* Abstracted image class
*
* @package 		vBulletin
* @version		$Revision: 15853 $
* @date 		$Date: 2006-11-06 13:55:50 -0600 (Mon, 06 Nov 2006) $
*
*/
class vB_Image_Abstract
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* @var	array
	*/
	var $resize_extensions = array();

	/**
	* @var	array
	*/
	var $info_extensions = array();

	/**
	* @var	array
	*/
	var $must_convert_types = array();

	/**
	* @var	mixed
	*/
	var $imageinfo = null;

	/**
	* @var	array $extension_map
	*/
	var $extension_map = array(
		'gif' => 'GIF',
		'jpg' => 'JPEG',
		'jpeg'=> 'JPEG',
		'jpe' => 'JPEG',
		'png' => 'PNG',
		'bmp' => 'BMP',
		'tif' => 'TIFF',
		'tiff'=> 'TIFF',
		'psd' => 'PSD',
		'pdf' => 'PDF',
	);

	/**
	* Constructor
	* Don't allow direct construction of this abstract class
	*
	* @return	void
	*/
	function vB_Image()
	{
		if (!is_subclass_of($this, 'vB_Image_Abstract'))
		{
			trigger_error('Direct Instantiation of vB_Image_Abstract prohibited.', E_USER_ERROR);
			return NULL;
		}
	}

	/**
	*Public
	*
	*
	* @param	string	$type		Type of image from $info_extensions
	*
	* @return	bool
	*/
	function fetch_must_convert($type)
	{
		return !empty($this->must_convert_types["$type"]);
	}

	/**
	* Public
	* Checks if supplied extension can be used by fetch_image_info
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*/
	function is_valid_info_extension($extension)
	{
		return !empty($this->info_extensions[strtolower($extension)]);
	}

	/**
	* Public
	* Checks if supplied extension can be used by fetch_thumbnail
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*/
	function is_valid_resize_extension($extension)
	{
		return !empty($this->resize_extensions[strtolower($extension)]);
	}

	/**
	* Public
	* Checks if supplied extension can be used by fetch_thumbnail
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*/
	function fetch_imagetype_from_extension($extension)
	{
		return $this->extension_map[strtolower($extension)];
	}

	/**
	* Private
	* Checks for HTML tags that can be exploited via IE
	*
	* @param string	filename
	*
	* @return bool
	*/
	function verify_image_file($filename)
	{
		// Verify that file is playing nice
		$fp = fopen($filename, 'rb');
		if ($fp)
		{
			$header = fread($fp, 200);
			fclose($fp);
			if (preg_match('#<html|<head|<body|<script#si', $header))
			{
				return false;
			}
		}
		else
		{
			return false;
		}

		return true;
	}

	/**
	* Public
	* Retrieve info about image
	*
	* @param	string	filename	Location of file
	* @param	string	extension	Extension of file name
	*
	* @return	array	[0]			int		width
	*					[1]			int		height
	*					[2]			string	type ('GIF', 'JPEG', 'PNG', 'PSD', 'BMP', 'TIFF',) (and so on)
	*					[scenes]	int		scenes
	*					[channels]	int		Number of channels (GREYSCALE = 1, RGB = 3, CMYK = 4)
	*					[bits]		int		Number of bits per pixel
	*					[library]	string	Library Identifier
	*/
	function fetch_image_info() {}

	/**
	* Public
	* Output an image based on a string
	*
	* @param	string	string	String to output
	* @param bool		moveabout	move text about
	*
	* @return	void
	*/
	function print_image_from_string() {}

	/**
	* Public
	* Returns an array containing a thumbnail, creation time, thumbnail size and any errors
	*
	* @param	string	filename	filename of the source file
	* @param	string	location	location of the source file
	* @param	int		newsize		new size of image (longest side of image)
	* @param	int		quality		Jpeg Quality
	*
	* @return	array
	*/
	function fetch_thumbnail() {}

	/**
	* Public
	* Return Error from graphics library
	*
	* @return	mixed
	*/
	function fetch_error() {}

	/**
	* Public
	* Returns an array containing the useable fonts, well they are supposed to be useable :rolleyes:
	*
	* @return	array
	*/
	function fetch_fonts() {}
}

/**
* Image class for ImageMagick
*
* @package 		vBulletin
* @version		$Revision: 15853 $
* @date 		$Date: 2006-11-06 13:55:50 -0600 (Mon, 06 Nov 2006) $
*
*/
class vB_Image_Magick extends vB_Image_Abstract
{

	/**
	* @var	string
	*/
	var $convertpath = '/usr/local/bin/convert';

	/**
	* @var	string
	*/
	var $identifypath = '/usr/local/bin/identify';

	/**
	* @var	integer
	*/
	var $returnvalue = 0;

	/**
	* @var  string
	*/
	var $identifyformat = '';

	/**
	* @var	string
	*/
	var $convertoptions = array(
		'width' => '100',
		'height' => '100',
		'quality' => '75',
	);

	/**
	* @var	string
	*/
	var $font = 'Helvetica';

	/**
	* @var  string
	*
	*/
	var $error = '';

	/**
	* Constructor
	* Sets ImageMagick paths to convert and identify
	*
	* @return	void
	*/
	function vB_Image_Magick(&$registry)
	{

		$this->registry = &$registry;
		$path = preg_replace('#[/\\\]+$#', '', $this->registry->options['magickpath']);

		if (preg_match('#^WIN#i', PHP_OS))
		{
			$this->identifypath = $path . '\identify.exe';
			$this->convertpath = $path . '\convert.exe';
		}
		else
		{
			$this->identifypath = $path .  '/identify';
			$this->convertpath = $path . '/convert';
		}

		if ($this->registry->options['gdfont'] AND @is_readable($this->registry->options['gdfont']))
		{
			$this->font = $this->registry->options['gdfont'];
		}
		else if ($this->registry->options['magickfont'])
		{
			$this->font = $this->registry->options['magickfont'];
		}

		$this->must_convert_types = array(
			'PSD'  => true,
			'BMP'  => true,
			'TIFF' => true,
			'PDF'  => true,
		);

		$this->resize_extensions = array(
			'gif'  => true,
			'jpg'  => true,
			'jpe'  => true,
			'jpeg' => true,
			'png'  => true,
			'psd'  => true,
			'pdf'  => true,
			'bmp'  => true,
			'tiff' => true,
			'tif'  => true,
		);
		$this->info_extensions =& $this->resize_extensions;

	}

	/**
	* Private
	* Return Error from fetch_im_exec()
	*
	* @return	string
	*/
	function fetch_error()
	{
		if (!empty($this->error))
		{
			return implode("\n", $this->error);
		}
		else
		{
			return false;
		}
	}

	/**
	* Private
	* Generic call to imagemagick binaries
	*
	* @param	string	command	ImageMagick binary to execute
	* @param	string	args	Arguments to the ImageMagick binary
	*
	* @return	mixed
	*/
	function fetch_im_exec($command, $args, $needoutput = false, $dieongs = true)
	{
		if (!function_exists('exec'))
		{
			$this->error = array('PHP ERROR exec() has been disabled.');
			return false;
		}

		$imcommands = array(
			'identify' => &$this->identifypath,
			'convert'=> &$this->convertpath,
		);

		$input = $imcommands["$command"] . ' ' . $args . ' 2>&1';
		$exec = @exec($input, $output, $this->returnvalue);

		if ($this->returnvalue OR $exec === null)
		{	// error was encountered
			if (!empty($output))
			{	// command issued by @exec failed
				if (strpos(strtolower(implode(' ', $output)), 'postscript delegate failed') !== false)
				{
					$output[] = 'Install Ghostscript to thumbnail .pdf files';
				}
				$this->error = $output;
			}
			else if (!empty($php_errormsg))
			{	// @exec failed so display error and remove path reveal
				$this->error = array('PHP ERROR', str_replace($this->registry->options['magickpath'] . '\\', '', $php_errormsg));
			}
			else if ($this->returnvalue == -1)
			{	// @exec failed but we don't have $php_errormsg to tell us why
				$this->error = array('PHP ERROR exec()');
			}
			return false;
		}
		else
		{
			$this->error = '';
			if (!empty($output))
			{	// $output is an array of returned text
				// This is for IM which doesn't return false for failed font
				if (strpos(strtolower(implode(' ', $output)), 'unable to read font') !== false)
				{
					$this->error = $output;
					return false;
				}

				if (strpos(strtolower(implode(' ', $output)), 'postscript delegate failed') !== false)
				{	// this is for IM 6.2.4+ which doesn't return false for exec(convert.exe) on .pdf when GS isn't installed
					$this->error = array('Install Ghostscript to thumbnail .pdf files');
				}
				return $output;
			}
			else if (empty($output) AND $needoutput)
			{	// $output is empty and we expected something back
				return false;
			}
			else
			{	// $output is empty and we didn't expect anything back
				return true;
			}
		}
	}

	/**
	* Private
	* Identify an image
	*
	* @param	string	$filename File to obtain image information from
	*
	* @return	mixed
	*/
	function fetch_identify_info($filename)
	{
		$fp = @fopen($filename, 'rb');
		if (($header = @fread($fp, 4)) == '%PDF')
		{	// this is a PDF so only look at frame 0 to save mucho processing time
			$frame0 = '[0]';
		}
		@fclose($fp);

		$execute = (!empty($this->identifyformat) ? "-format {$this->identifyformat} \"$filename\"" : "\"$filename\"") . $frame0;

		if ($result = $this->fetch_im_exec('identify', $execute, true))
		{
			if (empty($result) OR !is_array($result))
			{
				return false;
			}
			do
			{
				$last = array_pop($result);
			}
			while (!empty($result) AND $last == '');
			$temp = explode('###', $last);
			if (count($temp) < 5)
			{
				return false;
			}
			$temp['bits'] = $temp[5];
			switch($temp[4])
			{
				case 'PseudoClassGray':
				case 'PseudoClassGrayMatte':
				case 'PseudoClassRGB':
				case 'PseudoClassRGBMatte':
					$temp['channels'] = 1;
					break;
				case 'DirectClassRGB':
					$temp['channels'] = 3;
					break;
				case 'DirectClassCMYK':
					$temp['channels'] = 4;
					break;
				default:
					$temp['channels'] = 1;
			}
			$temp['scenes'] = $temp[3];
			$temp['library'] = 'IM';
			unset($temp[3], $temp[4], $temp[5]);
			return $temp;
		}
		else
		{
			return false;
		}
	}

	/**
	* Private
	* Set image size for convert
	*
	* @param	width	Width of new image
	* @param	height	Height of new image
	* @param	quality Quality of Jpeg images
	*
	* @return	void
	*/
	function set_convert_options($width = 100, $height = 100, $quality = 75)
	{
		$this->convertoptions['width'] = $width;
		$this->convertoptions['height'] = $height;
		$this->convertoptions['quality'] = $quality;
	}

	/**
	* Private
	* Convert an image
	*
	* @param	string	filename	Image file to convert
	* @param	string	output		Image file to write converted image to
	* @param	string	extension	Filetype
	* @param	boolean	thumbnail	Generate a thumbnail for display in a browser
	* @param	boolean	sharpen		Sharpen the output
	*
	* @return	mixed
	*/
	function fetch_converted_image($filename, $output, $extension, $thumbnail = true, $sharpen = true)
	{
		$execute = '';

		// if either dimension is 0, don't specify a size option
		if ($this->convertoptions['width'] > 0 AND $this->convertoptions['height'] > 0)
		{
			$size = $this->convertoptions['width'] . 'x' . $this->convertoptions['height'];
			$execute .= " -size $size";
		}

		if ($thumbnail)
		{
			// Only specify scene 1 if this is a PSD or a PDF -- allows animated gifs to be resized..
			$execute .= (in_array($extension, array('PDF', 'PSD'))) ? " \"{$filename}\"[0] " : " \"$filename\"";
			if ($size)
			{	// have to use -thumbnail here .. -sample looks BAD for animated gifs
				$execute .= " -thumbnail \"$size>\" ";
			}
		}
		else
		{
			$execute .= " \"$filename\"";
		}
		$execute .= ($sharpen) ? " -sharpen 0x1 " : '';

		// ### Convert a CMYK jpg to RGB since IE/Firefox will not display CMYK inline .. conversion is ugly since we don't specify profiles
		if ($this->imageinfo['channels'] == 4 AND $thumbnail)
		{
			$execute .= ' -colorspace RGB ';
		}

		if ($thumbnail)
		{
			if ($extension == 'PNG' OR $extension == 'PSD')
			{
				$execute .= " -depth 8 -colors 256 -quality {$this->convertoptions['quality']} PNG:";
			}
			else if ($this->fetch_must_convert($extension) OR $extension == 'JPEG')
			{
				$execute .= " -quality {$this->convertoptions['quality']} JPEG:";
			}
		}

		$execute .= "\"$output\"";

		if ($zak = $this->fetch_im_exec('convert', $execute))
		{
			return $zak;
		}
		else if ($sharpen AND !empty($this->error[0]) AND strpos($this->error[0], 'image smaller than radius') !== false)
		{	// try to resize again, but without sharpen
			$this->error = '';
			return $this->fetch_converted_image($filename, $output, $extension, $thumbnail, false);
		}
		else
		{
			return false;
		}
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_image_info($filename)
	{
		if (!$this->verify_image_file($filename))
		{
			return false;
		}

		$this->identifyformat = '%w###%h###%m###%n###%r###%z';
		$this->imageinfo = $this->fetch_identify_info($filename);
		return $this->imageinfo;
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_thumbnail($filename, $location, $maxwidth = 100, $maxheight = 100, $quality = 75)
	{
		$thumbnail = array(
			'filedata' => '',
			'filesize' => 0,
			'dateline' => 0,
			'imageerror' => '',
		);

		if ($this->is_valid_resize_extension(file_extension($filename)))
		{
			if ($imageinfo = $this->fetch_image_info($location))
			{
				if ($this->fetch_imagetype_from_extension(file_extension($filename)) != $imageinfo[2])
				{
					$thumbnail['imageerror'] = 'thumbnail_notcorrectimage';
				}
				else if ($imageinfo[0] > $maxwidth OR $imageinfo[1] > $maxheight OR $this->fetch_must_convert($imageinfo[2]))
				{
					if ($this->registry->options['safeupload'])
					{
						$tmpname = $this->registry->options['tmppath'] . '/' . md5(uniqid(microtime()) . $this->registry->userinfo['userid']);
					}
					else
					{
						if (!($tmpname = @tempnam(ini_get('upload_tmp_dir'), 'vbthumb')))
						{
							$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
							return $thumbnail;
						}
					}

					$this->set_convert_options($maxwidth, $maxheight, $quality);
					if ($result = $this->fetch_converted_image($location, $tmpname, $imageinfo[2]))
					{
						if ($imageinfo = $this->fetch_image_info($tmpname))
						{
							$thumbnail['width'] = $imageinfo[0];
							$thumbnail['height'] = $imageinfo[1];
						}
						$thumbnail['filesize'] = filesize($tmpname);
						$thumbnail['dateline'] = TIMENOW;
						$thumbnail['filedata'] = file_get_contents($tmpname);
					}
					else
					{
						$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
					}
					@unlink($tmpname);
				}
				else
				{
					if ($imageinfo[0] > 0 AND $imageinfo[1] > 0)
					{
						$thumbnail['filedata'] = @file_get_contents($location);
						$thumbnail['imageerror'] = 'thumbnailalready';
					}
					else
					{
						$thumbnail['filedata'] = '';
						$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
					}
				}
			}
			else
			{
				$thumbnail['filedata'] = '';
				$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
			}
		}

		if (!empty($thumbnail['filedata']))
		{
			$thumbnail['filesize'] = strlen($thumbnail['filedata']);
			$thumbnail['dateline'] = TIMENOW;
		}
		return $thumbnail;
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*/
	function fetch_fonts()
	{
		$fonts = array();
		$gsfonts = array();

		// Check for freetype library on non-windows systems
		if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN')
		{
			if ($result = $this->fetch_im_exec('identify', '-list configure', true))
			{
				foreach ($result AS $line)
				{
					// We've found a LIBS line and it doesn't list freetype so assume ImageMagick doesn't support fonts
					if (preg_match('#^LIBS#si', $line) AND !preg_match('#-lfreetype#si', $line))
					{
						$this->error = array('ImageMagick doesn\'t have freetype support.', 'Compile with ./configure -lfreetype');
						return false;
					}
				}
			}
			else
			{
				if (empty($this->error))
				{
					$this->error = array('Upgrade ImageMagick to 6.0 or newer');
				}
				else
				{
					$this->error[] = 'Upgrade ImageMagick to 6.0 or newer';
				}
				return false;
			}
		}

		if ($result = $this->fetch_im_exec('identify', '-list type', true))
		{
			foreach ($result AS $key => $value)
			{
				if (preg_match('#type-ghostscript.mgk$#', $value))
				{
					$path = str_replace('Path: ', '', $value);
					require_once(DIR . '/includes/class_xml.php');
					$xmlobj = new XMLparser(false, $path);
					if ($xml = $xmlobj->parse())
					{
						if (is_array($xml['type']))
						{
							foreach ($xml['type'] AS $key => $font)
							{
								if (!preg_match('#^@ghostscript_font_dir#', $font['glyphs']))
								{
									$gsfonts["$font[name]"] = $font['glyphs'];
								}
								else
								{
									$gsfonts["$font[name]"] = 'Bobbin Threadbare';
								}
							}
						}
						else
						{
							return false;
						}
					}
					else
					{
						return false;
					}
				}
				if (preg_match('#^Path: Windows Fonts#', $value))
				{
					// remove the gsfonts since we should be done with them now;
					unset($gsfonts);
				}
				if (preg_match('#^(\S+)\s+\S+\s+\S+\s+\S+#', $value, $matches))
				{
					if ($matches[1] != 'Name')
					{
						if (!empty($gsfonts["$matches[1]"]))
						{
							if ($gsfonts["$matches[1]"] != 'Bobbin Threadbare')
							{
								$fontpath = $gsfonts["$matches[1]"];
								$fonts["$fontpath"] = $matches[1];
							}
						}
						else
						{
							$fonts["$matches[1]"] = $matches[1];
						}
					}
				}
			}

			if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN')
			{
				// this could be slow on some servers, we shall see
				@exec('locate -q .ttf .pfb .afm', $output);
				if (!empty($output))
				{
					$afm = array();
					$pfb = array();
					foreach($output AS $fontfile)
					{
						$filename = basename($fontfile);
						switch (file_extension($fontfile))
						{
							case 'ttf':
								$fonts["$fontfile"] = $filename;
							break;
							case 'afm':
								$file = basename($filename, '.afm');
								$afm["$file"] = true;
							break;
							case 'pfb':
								$file = basename($filename, '.pfb');
								$pfb["$file"] = $fontfile;
							break;
						}
					}
					foreach ($pfb AS $filename => $font)
					{
						if (!empty($afm["$filename"]))
						{
							$fonts["$font"] = $filename . '.pfb';
						}
					}
				}
			}

			if (!empty($fonts))
			{
				return $fonts;
			}
			else
			{
				$this->error = array('Try installing Ghostscript and then reinstall ImageMagick');
				return false;
			}
		}
		else
		{
			return false;
		}
	}

	/**
	* See function definition in vB_Image_Abstract
	*/
	function print_image_from_string($string, $moveabout = true)
	{
		static $randseed;

		if ($this->registry->options['safeupload'])
		{
			$tmpname = $this->registry->options['tmppath'] . '/' . md5(uniqid(microtime()) . $this->registry->userinfo['userid']);
		}
		else
		{
			if (!($tmpname = @tempnam(ini_get('upload_tmp_dir'), 'vb')))
			{
				echo 'Could not create temporary file.';
				return false;
			}
		}

		$execute = " -size 199x59 xc:white -font $this->font -pointsize 32 ";

		if ($moveabout)
		{
			if (!$randseed)
			{
				$randseed = (double) microtime() * 1000000;
				mt_srand($randseed);
			}

			// Generate a random color..
			$r = mt_rand(50, 200);
			$b = mt_rand(50, 200);
			$g = mt_rand(50, 200);

			$execute .= " -fill \"rgb($r,$b,$g)\" ";

			// Randomly move the letters up and down
			for ($x = 0; $x < strlen($string); $x++)
			{
				$execute .= $this->annotate($string["$x"]);
			}
		}
		else
		{
			$execute .= $this->annotate("\"$string\"", false, false);
		}

		$execute .= " -swirl 40 -bordercolor black -border 1 GIF:\"$tmpname\"";
		if ($result = $this->fetch_im_exec('convert', $execute))
		{
			header('Content-Type: image/gif');
			if ($filesize = @filesize($tmpname))
			{	// this is here because of a stupid Win32 CGI thingymajig. filesize fails but readfile works, go figure
				header("Content-Length: $filesize");
			}
			readfile($tmpname);
			@unlink($tmpname);
		}
		else
		{
			echo htmlspecialchars_uni($this->fetch_error());
			@unlink($tmpname);
			return false;
		}
	}

	/**
	* Private
	* Return a letter position command
	*
	* @param	string	letter	Character to position
	*
	* @return	string
	*/
	function annotate($letter, $slant = false, $random = true)
	{
		static $randseed;
		if (!$randseed)
		{
			$randseed = (double) microtime() * 1000000;
			mt_srand($randseed);
		}

		// Start position
		static $position = 10;

		// Character Slant
		static $slants = array(
			'0x0',		// Normal
			'0x20',		// Slant Right
			'20x20',	// Slant Down
			'335x335',	// Slant Up
		);

		// Can't use slants AND swirl at the same time, it just looks bad ;)
		if ($slant)
		{
			$coord = mt_rand(0, 3);
			$coord = $slants["$coord"];
		}
		else
		{
			$coord = $slants[0];
		}

		if ($random)
		{
			// Y Axis position, random from 32 to 48
			$y = mt_rand(32, 48);
		}
		else
		{
			$y = 40;
		}

		$output = " -annotate $coord+$position+$y $letter ";
		$position += 30;

		return $output;

	}
}

/**
* Image class for GD Image Library
*
* @package 		vBulletin
* @version		$Revision: 15853 $
* @date 		$Date: 2006-11-06 13:55:50 -0600 (Mon, 06 Nov 2006) $
*
*/
class vB_Image_GD extends vB_Image_Abstract
{
	function vB_Image_GD(&$registry)
	{
		$this->registry = &$registry;

		$this->info_extensions = array(
			'gif' => true,
			'jpg' => true,
			'jpe' => true,
			'jpeg'=> true,
			'png' => true,
			'psd' => true,
			'bmp' => true,
		);

		if (PHP_VERSION >= '4.2.0')
		{
			$this->info_extensions['tiff'] = true;
			$this->info_extenstions['tif'] = true;
		}

		$this->resize_extensions = array(
			'gif' => true,
			'jpg' => true,
			'jpe' => true,
			'jpeg' => true,
			'png' => true,
		);
	}

	/**
	* Private
	* Output an image
	*
	* @param	object	filename	Image file to convert
	* @param	int		output		Image file to write converted image to
	* @param	bool	headers		Generate image header
	* @param	int		quality		Jpeg Quality
	*
	* @return	void
	*/
	// ###################### Start print_image #######################
	function print_image(&$image, $type = JPG, $headers = true, $quality = 75)
	{
		// Determine what image type to output
		switch($type)
		{
			case GIF:
				if (!IMAGEGIF)
				{
					if (IMAGEJPEG)
					{
						$type = JPG;
					}
					else if (IMAGEPNG)
					{
						$type = PNG;
					}
					else // nothing!
					{
						imagedestroy($image);
						return false;
					}
				}
				break;

			case PNG:
				if (!IMAGEPNG)
				{
					if (IMAGEJPEG)
					{
						$type = JPG;
					}
					else if (IMAGEGIF)
					{
						$type = GIF;
					}
					else // nothing!
					{
						imagedestroy($image);
						return false;
					}
				}
				break;

			default:	// JPG
				if (!IMAGEJPEG)
				{
					if (IMAGEGIF)
					{
						$type = GIF;
					}
					else if (IMAGEPNG)
					{
						$type = PNG;
					}
					else // nothing!
					{
						imagedestroy($image);
						return false;
					}
				}
				else
				{
					$type = JPG;
				}
				break;
		}

		/* If you are calling print_image inside ob_start in order to capture the image
			remember any headers still get sent to the browser. Mozilla is not happy with this */

		switch ($type)
		{
			case GIF:
				if ($headers)
				{
					header('Content-type: image/gif');
				}
				imagegif($image);
				imagedestroy($image);
				return true;

			case PNG:
				if ($headers)
				{
					header('Content-type: image/png');
				}
				imagepng($image);
				imagedestroy($image);
				return true;

			case JPG:
				if ($headers)
				{
					header('Content-type: image/jpeg');
				}
				imagejpeg($image, '', $quality);
				imagedestroy($image);
				return true;

			default:
				imagedestroy($image);
				return false;
		}
	}

	////////////////////////////////////////////////////////////////////////////////////////////////
	////
	////                  p h p U n s h a r p M a s k
	////
	////		Original Unsharp mask algorithm by Torstein Hønsi 2003.
	////		thoensi@netcom.no
	////		Formatted for vBulletin usage by Freddie Bingham
	////
	///////////////////////////////////////////////////////////////////////////////////////////////
	/**
	* Private
	* Sharpen an image
	*
	* @param	object		finalimage
	* @param	int			float
	* @param	radius		float
	* @param	threshold	float
	*
	* @return	void
	*/
	function unsharpmask(&$finalimage, $amount = 100, $radius = .5, $threshold = 3)
	{
		// $finalimg is an image that is already created within php using
		// imgcreatetruecolor. No url! $img must be a truecolor image.

		// Attempt to calibrate the parameters to Photoshop:
		if ($amount > 500)
		{
			$amount = 500;
		}
		$amount = $amount * 0.016;
		if ($radius > 50)
		{
			$radius = 50;
		}
		$radius = $radius * 2;
		if ($threshold > 255)
		{
			$threshold = 255;
		}

		$radius = abs(round($radius)); 	// Only integers make sense.
		if ($radius == 0)
		{
			return true;
		}

		$w = imagesx($finalimage);
		$h = imagesy($finalimage);
		$imgCanvas = imagecreatetruecolor($w, $h);
		$imgCanvas2 = imagecreatetruecolor($w, $h);
		$imgBlur = imagecreatetruecolor($w, $h);
		$imgBlur2 = imagecreatetruecolor($w, $h);
		imagecopy ($imgCanvas, $finalimage, 0, 0, 0, 0, $w, $h);
		imagecopy ($imgCanvas2, $finalimage, 0, 0, 0, 0, $w, $h);

		// Gaussian blur matrix:
		//
		//	1	2	1
		//	2	4	2
		//	1	2	1
		//
		//////////////////////////////////////////////////

		// Move copies of the image around one pixel at the time and merge them with weight
		// according to the matrix. The same matrix is simply repeated for higher radii.
		for ($i = 0; $i < $radius; $i++)
		{
			imagecopy ($imgBlur, $imgCanvas, 0, 0, 1, 1, $w - 1, $h - 1); // up left
			imagecopymerge ($imgBlur, $imgCanvas, 1, 1, 0, 0, $w, $h, 50); // down right
			imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 1, 0, $w - 1, $h, 33.33333); // down left
			imagecopymerge ($imgBlur, $imgCanvas, 1, 0, 0, 1, $w, $h - 1, 25); // up right
			imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 1, 0, $w - 1, $h, 33.33333); // left
			imagecopymerge ($imgBlur, $imgCanvas, 1, 0, 0, 0, $w, $h, 25); // right
			imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 20 ); // up
			imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 16.666667); // down
			imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 0, $w, $h, 50); // center
			imagecopy ($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);

			// During the loop above the blurred copy darkens, possibly due to a roundoff
			// error. Therefore the sharp picture has to go through the same loop to
			// produce a similar image for comparison. This is not a good thing, as processing
			// time increases heavily.
			imagecopy ($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h);
			imagecopymerge ($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 50);
			imagecopymerge ($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 33.33333);
			imagecopymerge ($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 25);
			imagecopymerge ($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 33.33333);
			imagecopymerge ($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 25);
			imagecopymerge ($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 20 );
			imagecopymerge ($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 16.666667);
			imagecopymerge ($imgBlur2, $imgCanvas2, 0, 0, 0, 0, $w, $h, 50);
			imagecopy ($imgCanvas2, $imgBlur2, 0, 0, 0, 0, $w, $h);
		}
		imagedestroy($imgBlur);
		imagedestroy($imgBlur2);

		// Calculate the difference between the blurred pixels and the original
		// and set the pixels
		for ($x = 0; $x < $w; $x++)
		{ // each row
			for ($y = 0; $y < $h; $y++)
			{ // each pixel

				$rgbOrig = ImageColorAt($imgCanvas2, $x, $y);
				$rOrig = (($rgbOrig >> 16) & 0xFF);
				$gOrig = (($rgbOrig >> 8) & 0xFF);
				$bOrig = ($rgbOrig & 0xFF);

				$rgbBlur = ImageColorAt($imgCanvas, $x, $y);

				$rBlur = (($rgbBlur >> 16) & 0xFF);
				$gBlur = (($rgbBlur >> 8) & 0xFF);
				$bBlur = ($rgbBlur & 0xFF);

				// When the masked pixels differ less from the original
				// than the threshold specifies, they are set to their original value.
				$rNew = (abs($rOrig - $rBlur) >= $threshold) ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))	: $rOrig;
				$gNew = (abs($gOrig - $gBlur) >= $threshold) ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))	: $gOrig;
				$bNew = (abs($bOrig - $bBlur) >= $threshold) ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))	: $bOrig;

				if (($rOrig != $rNew) OR ($gOrig != $gNew) OR ($bOrig != $bNew))
				{
    				$pixCol = ImageColorAllocate($finalimage, $rNew, $gNew, $bNew);
    				ImageSetPixel($finalimage, $x, $y, $pixCol);
				}
			}
		}
		imagedestroy($imgCanvas);
		imagedestroy($imgCanvas2);

		return true;
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function print_image_from_string($string, $moveabout = true)
	{

		$image_width = 201;
		$image_height = 61;

		for ($x = 0; $x < strlen($string); $x++)
		{
			$newstring .= $string["$x"] . ' ';
		}
		$string = '  ' . $newstring . ' ';

		if ($this->registry->options['gdversion'] == 1)
		{
			$image = imagecreate($image_width, $image_height);
		}
		else
		{
			$image = imagecreatetruecolor($image_width, $image_height);
		}

		$randseed = (double) microtime() * 1000000;
		mt_srand($randseed);

		// Generate a random color..
		$r = mt_rand(50, 200);
		$b = mt_rand(50, 200);
		$g = mt_rand(50, 200);

		// end colour selection
		// *********************************************************

		$background_color = imagecolorallocate($image, 255, 255, 255); //white background
		imagefill($image, 0, 0, $background_color); // For GD2+
		if ($moveabout)
		{
			$text_color = imagecolorallocate($image, $r, $g, $b);
		}
		else
		{
			$text_color = imagecolorallocate($image, 0, 0, 0);
		}

		if ($this->registry->options['gdfont'] AND @is_readable($this->registry->options['gdfont']) AND function_exists('imagettfbbox'))
		{	// Use the specified ttf font
			$size = 10;
			do
			{
				$box = imagettfbbox ($size, 0, $this->registry->options['gdfont'], $string);
				$width = $box[2] - $box[0];
				$height = $box[5] - $box[3];
				$size += 1;
			}
			while ($width < $image_width AND $height < $image_height);
			$size -= 1;

			$x = ($image_width - $width) / 2;
			$y = (($image_height + $height) / 2) + abs($height);

			imagettftext($image, $size, 0, $x, $y, $text_color, $this->registry->options['gdfont'], $string);
			$swirl = true;
		}
		else
		{	// use the built in font. YUCK
			// Temp image that creates string
			$temp_width  = 135;
			$temp_height = 20;
			if ($this->registry->options['gdversion'] == 1)
			{
				$temp = imagecreate($temp_width, $temp_height);
			}
			else
			{
				$temp = imagecreatetruecolor($temp_width, $temp_height);
			}

			$background_color = imagecolorallocate($temp, 255, 255, 255); //white background
			imagefill($temp, 0, 0, $background_color); // For GD2+

			imagestring($temp, 3, 0, 2, $string, $text_color);
			imagecopyresized($image, $temp, 0, 0, 0, 0, $image_width, $image_height, $temp_width, $temp_height);
			imagedestroy($temp);
			$swirl = false;
		}

		if ($swirl)
		{
			$image = $this->swirl($image, .001, $moveabout);
		}
		$image = $this->wave($image, 8, $moveabout);
		if (function_exists('imagefilter'))
		{
			@imagefilter($image, IMG_FILTER_SMOOTH, 12);
		}
		else if (PHP_VERSION != '4.3.2')
		{
			$image = $this->blur($image, .5);
		}

		// Black
		$text_color = imagecolorallocate($image, 0, 0, 0);
		// draw a border
		imageline($image, 0, 0, $image_width, 0, $text_color);
		imageline($image, 0, 0, 0, $image_height, $text_color);
		imageline($image, $image_width - 1, 0, $image_width - 1, $image_height, $text_color);
		imageline($image, 0, $image_height - 1, $image_width, $image_height - 1, $text_color);

		$this->print_image($image, GIF);
	}

	/**
	* Private
	* Apply a swirl/twirl filter to an image
	*
	* @param	image	image			Image file to convert
	* @param	float	output			Degree of twirl
	* @param	bool	randirection	Randomize direction of swirl (clockwise/counterclockwise)
	*
	* @return	object	image
	*/
	function swirl(&$image, $degree = .005, $randirection = true)
	{
		$image_width = imagesx($image);
		$image_height = imagesy($image);

		if ($this->registry->options['gdversion'] == 1)
		{
			$temp = imagecreate($image_width, $image_height);
		}
		else
		{
			$temp = imagecreatetruecolor($image_width, $image_height);
		}

		if ($randirection)
		{
			$degree = (mt_rand(0, 1) == 1) ? $degree : $degree * -1;
		}

		$middlex = floor($image_width / 2);
		$middley = floor($image_height / 2);

		for ($x = 0; $x < $image_width; $x++)
		{
			for ($y = 0; $y < $image_height; $y++)
			{
				$xx = $x - $middlex;
				$yy = $y - $middley;

				$theta = atan2($yy, $xx);

				$radius = sqrt($xx * $xx + $yy * $yy);

				$radius -= 5;

				$newx = $middlex + ($radius * cos($theta + $degree * $radius));
				$newy = $middley + ($radius * sin($theta + $degree * $radius));

				if (($newx > 0 AND $newx < $image_width) AND ($newy > 0 AND $newy < $image_height))
				{
					$index = imagecolorat($image, $newx, $newy);
                    $colors = imagecolorsforindex($image, $index);
                    $color = imagecolorresolve($temp, $colors['red'], $colors['green'], $colors['blue']);
				}
				else
				{
					$color = imagecolorresolve($temp, 255, 255, 255);
				}

				imagesetpixel($temp, $x, $y, $color);
			}
		}

		return $temp;
	}

	/**
	* Private
	* Apply a wave filter to an image
	*
	* @param	image	image			Image  to convert
	* @param	int		wave			Amount of wave to apply
	* @param	bool	randirection	Randomize direction of wave
	*
	* @return	image
	*/
	function wave(&$image, $wave = 10, $randirection = true)
	{
		$image_width = imagesx($image);
		$image_height = imagesy($image);

		if ($this->registry->options['gdversion'] == 1)
		{
			$temp = imagecreate($image_width, $image_height);
		}
		else
		{
			$temp = imagecreatetruecolor($image_width, $image_height);
		}

		if ($randirection)
		{
			$direction = (mt_rand(0, 1) == 1) ? true : false;
		}

		$middlex = floor($image_width / 2);
		$middley = floor($image_height / 2);

		for ($x = 0; $x < $image_width; $x++)
		{
			for ($y = 0; $y < $image_height; $y++)
			{

				$xo = $wave * sin(2 * 3.1415 * $y / 128);
				$yo = $wave * cos(2 * 3.1415 * $x / 128);

				if ($direction)
				{
					$newx = $x - $xo;
					$newy = $y - $yo;
				}
				else
				{
					$newx = $x + $xo;
					$newy = $y + $yo;
				}

				if (($newx > 0 AND $newx < $image_width) AND ($newy > 0 AND $newy < $image_height))
				{
					$index = imagecolorat($image, $newx, $newy);
                    $colors = imagecolorsforindex($image, $index);
                    $color = imagecolorresolve($temp, $colors['red'], $colors['green'], $colors['blue']);
				}
				else
				{
					$color = imagecolorresolve($temp, 255, 255, 255);
				}

				imagesetpixel($temp, $x, $y, $color);
			}
		}

		return $temp;
	}

	/**
	* Private
	* Apply a blur filter to an image
	*
	* @param	image	image			Image  to convert
	* @param	int		radius			Radius of blur
	*
	* @return	image
	*/
	function blur(&$image, $radius = .5)
	{
		$radius = ($radius > 50) ? 100 : abs(round($radius * 2));

		if ($radius == 0)
		{
			return $image;
		}

		$w = imagesx($image);
		$h = imagesy($image);

		if ($this->registry->options['gdversion'] == 1)
		{
			$imgCanvas = imagecreate($w, $h);
			$imgBlur = imagecreate($w, $h);
		}
		else
		{
			$imgCanvas = imagecreatetruecolor($w, $h);
			$imgBlur = imagecreatetruecolor($w, $h);
		}
		imagecopy ($imgCanvas, $image, 0, 0, 0, 0, $w, $h);

		// Gaussian blur matrix:
		//
		//	1	2	1
		//	2	4	2
		//	1	2	1
		//
		//////////////////////////////////////////////////

		// Move copies of the image around one pixel at the time and merge them with weight
		// according to the matrix. The same matrix is simply repeated for higher radii.
		for ($i = 0; $i < $radius; $i++)
		{
			imagecopy($imgBlur, $imgCanvas, 0, 0, 1, 1, $w - 1, $h - 1); // up left
			imagecopymerge($imgBlur, $imgCanvas, 1, 1, 0, 0, $w, $h, 50); // down right
			imagecopymerge($imgBlur, $imgCanvas, 0, 1, 1, 0, $w - 1, $h, 33.33333); // down left
			imagecopymerge($imgBlur, $imgCanvas, 1, 0, 0, 1, $w, $h - 1, 25); // up right
			imagecopymerge($imgBlur, $imgCanvas, 0, 0, 1, 0, $w - 1, $h, 33.33333); // left
			imagecopymerge($imgBlur, $imgCanvas, 1, 0, 0, 0, $w, $h, 25); // right
			imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 20 ); // up
			imagecopymerge($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 16.666667); // down
			imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 0, $w, $h, 50); // center
			imagecopy($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);
		}
		imagedestroy($imgBlur);
		return $imgCanvas;
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_image_info($filename)
	{
		static $types = array(
			1 => 'GIF',
			2 => 'JPEG',
			3 => 'PNG',
			4 => 'SWF',
			5 => 'PSD',
			6 => 'BMP',
			7 => 'TIFF',
			8 => 'TIFF',
			9 => 'JPC',
			10=> 'JP2',
			11=> 'JPX',
			12=> 'JB2',
			13=> 'SWC',
			14=> 'IFF',
			15=> 'WBMP',
			16=> 'XBM',
		);

		if (!$this->verify_image_file($filename))
		{
			return false;
		}

		// use PHP's getimagesize if it works
		if ($imageinfo = getimagesize($filename))
		{
 			$this->imageinfo = array(
				0          => $imageinfo[0],
				1          => $imageinfo[1],
				2          => $types["$imageinfo[2]"],
				'channels' => $imageinfo['channels'],
				'bits'     => $imageinfo['bits'],
				'scenes'   => 1,
				'library'  => 'GD',
			);

			if ($this->imageinfo[2] == 'GIF')
			{	// get scenes
				$data = file_get_contents($filename);
				// Look for a Global Color table char and the Image seperator character
				$this->imageinfo['scenes'] = count(preg_split('#\x00[\x00-\xFF]\x00\x2C#', $data)) - 1;
			}

			return $this->imageinfo;
		}
		// getimagesize barfs on some jpegs but we can try to create an image to find the dimensions
		else if (function_exists('imagecreatefromjpeg') AND $img = @imagecreatefromjpeg($filename))
		{
			$this->imageinfo = array(
				0          => imagesx($img),
				1          => imagesy($img),
				2          => 'JPEG',
				'channels' => 3,
				'bits'     => 8,
				'library'  => 'GD',
			);
			imagedestroy($img);

			return $this->imageinfo;
		}
		else
		{
			return false;
		}
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_thumbnail($filename, $location, $maxwidth = 100, $maxheight = 100, $quality = 75, $labelimage = false, $drawborder = false)
	{

		$thumbnail = array(
			'filedata' => '',
			'filesize' => 0,
			'dateline' => 0,
			'imageerror' => '',
		);

		if ($validfile = $this->is_valid_resize_extension(file_extension($filename)) AND $imageinfo = $this->fetch_image_info($location))
		{
			$new_width = $width = $imageinfo[0];
			$new_height = $height = $imageinfo[1];

			if ($this->fetch_imagetype_from_extension(file_extension($filename)) != $imageinfo[2])
			{
				$thumbnail['imageerror'] = 'thumbnail_notcorrectimage';
			}
			else if ($width > $maxwidth OR $height > $maxheight)
			{
				$memoryok = true;
				if (function_exists('memory_get_usage') AND $memory_limit = @ini_get('memory_limit') AND $memory_limit != -1)
				{
					$memorylimit = vb_number_format($memory_limit, 0, false, null, '');
					$memoryusage = memory_get_usage();
					$freemem = $memorylimit - $memoryusage;
					$checkmem = true;
					$tmemory = $width * $height * ($imageinfo[2] == 'JPEG' ? 5 : 2) + 7372.8 + sqrt(sqrt($width * $height));
					$tmemory += ($this->registry->options['gdversion'] == 1) ? 100000 : 166000; // fudge factor, object overhead, etc

					if ($freemem > 0 AND $tmemory > $freemem AND $tmemory <= ($memorylimit * 3))
					{	// attempt to increase memory within reason, no more than triple
						@ini_set('memory_limit', $memorylimit + $tmemory);

						$memory_limit = @ini_get('memory_limit');
						$memorylimit = vb_number_format($memory_limit, 0, false, null, '');
						$memoryusage = memory_get_usage();
						$freemem = $memorylimit - $memoryusage;
					}
				}

				switch($imageinfo[2])
				{
					case 'GIF':
						if (function_exists('imagecreatefromgif'))
						{
							if ($checkmem)
							{
								if ($freemem > 0 AND $tmemory > $freemem)
								{
									$thumbnail['imageerror'] = 'thumbnail_notenoughmemory';
									$memoryok = false;
								}
							}
							if ($memoryok AND !$image = @imagecreatefromgif($location))
							{
								$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
							}
						}
						else
						{
							$thumbnail['imageerror'] = 'thumbnail_nosupport';
						}
						break;
					case 'JPEG':
						if (function_exists('imagecreatefromjpeg'))
						{
							if ($checkmem)
							{
								if ($freemem > 0 AND $tmemory > $freemem)
								{
									$thumbnail['imageerror'] = 'thumbnail_notenoughmemory';
									$memoryok = false;
								}
							}

							if ($memoryok AND !$image = @imagecreatefromjpeg($location))
							{
								$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
							}
						}
						else
						{
							$thumbnail['imageerror'] = 'thumbnail_nosupport';
						}
						break;
					case 'PNG':
						if (function_exists('imagecreatefrompng') AND $this->registry->options['thumbpng'])
						{
							if ($checkmem)
							{
								if ($freemem > 0 AND $tmemory > $freemem)
								{
									$thumbnail['imageerror'] = 'thumbnail_notenoughmemory';
									$memoryok = false;
								}
							}
							if ($memoryok AND !$image = @imagecreatefrompng($location))
							{
								$thumbnail['imagerror'] = 'thumbnail_nocreateimage';
							}
						}
						else
						{
							$thumbnail['imageerror'] = 'thumbnail_nosupport';
						}
						break;
				}

				if ($image)
				{
					$xratio = $width / $maxwidth;
					$yratio = $height / $maxheight;
					if ($xratio > $yratio)
					{
						$new_width = round($width / $xratio);
						$new_height = round($height / $xratio);
					}
					else
					{
						$new_width = round($width / $yratio);
						$new_height = round($height / $yratio);
					}

					#$drawborder = true;
					#$labelimage = true;

					if ($drawborder)
					{
						$create_width = $new_width + 2;
						$create_height = $new_height + 2;
						$dest_x_start = 1;
						$dest_y_start = 1;
					}
					else
					{
						$create_width = $new_width;
						$create_height = $new_height;
						$dest_x_start = 0;
						$dest_y_start = 0;
					}

					if ($labelimage)
					{
						$font = 3;
						$labelboxheight = 15;

						$filesize = @filesize($location);
						if ($filesize / 1024 < 1)
						{
							$filesize = 1024;
						}
						$string = ((!empty($width) AND !empty($height)) ? "{$width}x{$height}" : '') . (!empty($filesize) ? ' ' . number_format($filesize / 1024, 0, '', '') . 'kb' : '');

						if (($length = (strlen($string) * imagefontwidth($font))) > ($new_width - 2))
						{	// have to increase the thumbnail width to hold the labelbox
							$create_width = ($length - $new_width + 4) + $create_width;
							$labelboxend = $create_width - 2;

							$dest_x_start = ($create_width - $new_width) / 2;
						}
						else
						{
							$labelboxend = $new_width;
						}

						$create_height += $labelboxheight;
						if ($drawborder)
						{
							$label_x_start = ($labelboxend - (strlen($string) * imagefontwidth($font))) / 2 + 1;
							$label_y_start =  ($labelboxheight - imagefontheight($font)) / 2 + $new_height + 1;
						}
						else
						{
							$label_x_start =  ($labelboxend - (strlen($string) * imagefontwidth($font))) / 2 + 0;
							$label_y_start =  ($labelboxheight - imagefontheight($font)) / 2 + $new_height;
						}
					}

					if ($this->registry->options['gdversion'] == 1)
					{
						if (!($finalimage = @imagecreate($create_width, $create_height)))
						{
							$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
							imagedestroy($image);
						}

						$bgcolor = imagecolorallocate($finalimage, 200, 200, 200);
						$textcolor = imagecolorallocate($finalimage, 0, 0, 0);
						imagefill($finalimage, 0, 0, $bgcolor);

						imagecopyresized($finalimage, $image, $dest_x_start, $dest_y_start, 0, 0, $new_width, $new_height, $width, $height);
						imagedestroy($image);
					}
					else
					{
						if (!($finalimage = @imagecreatetruecolor($create_width, $create_height)))
						{
							$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
							imagedestroy($image);
						}

						$bgcolor = imagecolorallocate($finalimage, 200, 200, 200);
						$textcolor = imagecolorallocate($finalimage, 0, 0, 0);
						imagefill($finalimage, 0, 0, $bgcolor);

						@imagecopyresampled($finalimage, $image, $dest_x_start, $dest_y_start, 0, 0, $new_width, $new_height, $width, $height);
						imagedestroy($image);
						if (PHP_VERSION != '4.3.2')
						{
							$this->unsharpmask($finalimage);
						}
					}

					if ($labelimage)
					{
						imagestring($finalimage, $font, $label_x_start, $label_y_start, $string, $textcolor);
					}

					if ($drawborder)
					{
						imageline($finalimage, 0, 0, $create_width, 0, $textcolor);
						imageline($finalimage, 0, 0, 0, $create_height, $textcolor);
						imageline($finalimage, $create_width - 1, 0, $create_width - 1, $create_height, $textcolor);
						imageline($finalimage, 0, $create_height - 1, $create_width, $create_height - 1, $textcolor);
					}

					ob_start();
						$this->print_image($finalimage, $imageinfo[2], false, $quality);
						$thumbnail['filedata'] = ob_get_contents();
					ob_end_clean();
					$thumbnail['width'] = $new_width;
					$thumbnail['height'] = $new_height;
				}
			}
			else
			{
				if ($imageinfo[0] == 0 AND $imageinfo[1] == 0) // getimagesize() failed
				{
					$thumbnail['filedata'] = '';
					$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
				}
				else
				{
					$thumbnail['filedata'] = @file_get_contents($location);
					$thumbnail['imageerror'] = 'thumbnailalready';
				}
			}
		}
		else if (!$validfile)
		{
			$thumbnail['filedata'] = '';
			$thumbnail['imageerror'] = 'thumbnail_nosupport';
		}

		if (!empty($thumbnail['filedata']))
		{
			$thumbnail['filesize'] = strlen($thumbnail['filedata']);
			$thumbnail['dateline'] = TIMENOW;
		}
		return $thumbnail;
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_error()
	{
		return false;
	}

	/**
	* This function is no implemented in this class at the present
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_fonts() {}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15853 $
|| ####################################################################
\*======================================================================*/
?>