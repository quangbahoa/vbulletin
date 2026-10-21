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

define('GIF', 1);
define('JPG', 2);
define('PNG', 3);

// For the time being define IMAGEGIF as false as hacked versions of GD that claim to support .gif creation don't seem to always work :/
if (function_exists('imagegif'))
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

if (function_exists('imagepng') AND $vboptions['thumbpng'])
{
	define('IMAGEPNG', true);
}
else
{
	define('IMAGEPNG', false);
}

// ###################### Start vbimage #######################
function vbimage(&$image, $type = 2, $headers = 1)
{

	// Well if we don't have JPG, try PNG
	if ($type == JPG AND !IMAGEJPEG)
	{
		$type = PNG;
	}

	/* If you are calling vbimage inside ob_start in order to capture the image
		remember any headers still get sent to the browser. Mozilla is not happy with this */

	// Try to create a gif from a gif.
	if ($type == GIF AND IMAGEGIF)
	{
		if ($headers)
		{
			header('Content-type: image/gif');
		}
		imagegif($image);
		return true;
	}

	// Try to create a PNG from a PNG or a GIF from a PNG if step 1 failed
	if (($type == PNG OR $type == GIF) AND IMAGEPNG)
	{
		if ($headers)
		{
			header('Content-type: image/png');
		}
		imagepng($image);
		return true;
	}

	// Try to create a jpg from a jpg, or a jpg from a PNG or GIF if step 1 and 2 failed
	if (IMAGEJPEG)
	{
		if ($headers)
		{
			header('Content-type: image/jpeg');
		}
		imagejpeg($image);
		return true;
	}

	return false;
}

// ###################### Start vbthumbnail #######################
function fetch_thumbnail_from_image($attachment)
{
	global $vboptions, $DB_site;

	$thumbnail = array(
		'filedata' => '',
		'filesize' => 0,
		'dateline' => 0,
	);

	$validimagetypes = array('gif', 'jpg', 'jpeg', 'jpe', 'png');
	if (in_array(strtolower(file_extension($attachment['name'])), $validimagetypes))
	{
		$imageinfo = getimagesize($attachment['tmp_name']);
		$new_width = $width = $imageinfo[0];
		$new_height = $height = $imageinfo[1];
		if ($width > $vboptions['attachthumbssize'] OR $height > $vboptions['attachthumbssize'])
		{
			switch($imageinfo[2])
			{
				case GIF:
					if (function_exists('imagecreatefromgif'))
					{
						if (!$image = @imagecreatefromgif($attachment['tmp_name']))
						{
							$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
						}
					}
					else
					{
						$thumbnail['imageerror'] = 'thumbnail_nogif';
					}
					break;
				case JPG:
					if (function_exists('imagecreatefromjpeg'))
					{
						if (!$image = @imagecreatefromjpeg($attachment['tmp_name']))
						{
							$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
						}
					}
					else
					{
						$thumbnail['imageerror'] = 'thumbnail_nojpg';
					}
					break;
				case PNG:
					if (function_exists('imagecreatefrompng') AND $vboptions['thumbpng'])
					{
						if (!$image = @imagecreatefrompng($attachment['tmp_name']))
						{
							$thumbnail['imagerror'] = 'thumbnail_nocreateimage';
						}
					}
					else
					{
						$thumbnail['imageerror'] = 'thumbnail_nopng';
					}
					break;
			}
			if ($image)
			{
				$xratio = $width / $vboptions['attachthumbssize'];
				$yratio = $height / $vboptions['attachthumbssize'];
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
				if ($vboptions['gdversion'] == 1)
				{
					if (!($finalimage = @imagecreate($new_width, $new_height)))
					{
						$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
						imagedestroy($image);
						return $thumbnail;
					}
					imagecopyresized($finalimage, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
					imagedestroy($image);
				}
				else
				{
					if (!($finalimage = @imagecreatetruecolor($new_width, $new_height)))
					{
						$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
						imagedestroy($image);
						return $thumbnail;
					}
					@imagecopyresampled($finalimage, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
					imagedestroy($image);
					if (PHP_VERSION != '4.3.2' AND $vboptions['attachthumbs'] == 2)
					{
						UnsharpMask($finalimage);
					}
				}

				ob_start();
					vbimage($finalimage, $imageinfo[2], 0);
					imagedestroy($finalimage);
					$thumbnail['filedata'] = ob_get_contents();
				ob_end_clean();
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
				$thumbnail['filedata'] = @file_get_contents($attachment['tmp_name']);
				$thumbnail['imageerror'] = 'thumbnailalready';
			}
		}
	}

	$thumbnail['filesize'] = strlen($thumbnail['filedata']);
	$thumbnail['dateline'] = iif(empty($thumbnail['filedata']), 0, TIMENOW);
	if (!$vboptions['attachfile'])
	{
		$thumbnail['filedata'] = $DB_site->escape_string($thumbnail['filedata']);
	}

	return $thumbnail;
}

////////////////////////////////////////////////////////////////////////////////////////////////
////
////                  p h p U n s h a r p M a s k
////
////		Unsharp mask algorithm by Torstein Hønsi 2003.
////		thoensi@netcom.no
////		Please leave this notice.
////
///////////////////////////////////////////////////////////////////////////////////////////////

function UnsharpMask(&$img, $amount = 100, $radius = .5, $threshold = 3)
{

	// $img is an image that is already created within php using
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

	$w = imagesx($img);
	$h = imagesy($img);
	$imgCanvas = imagecreatetruecolor($w, $h);
	$imgCanvas2 = imagecreatetruecolor($w, $h);
	$imgBlur = imagecreatetruecolor($w, $h);
	$imgBlur2 = imagecreatetruecolor($w, $h);
	imagecopy ($imgCanvas, $img, 0, 0, 0, 0, $w, $h);
	imagecopy ($imgCanvas2, $img, 0, 0, 0, 0, $w, $h);


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

			$pixCol = imagecolorallocate ($img, $rNew, $gNew, $bNew);
			imagesetpixel ($img, $x, $y, $pixCol);
		}
	}
	imagedestroy($imgCanvas);
	imagedestroy($imgCanvas2);

	return true;
}




/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 10873 $
|| ####################################################################
\*======================================================================*/
?>