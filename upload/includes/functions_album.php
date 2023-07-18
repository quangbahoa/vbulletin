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

/**
* Fetches album information from the database. Also does some preparation
* on the data for display.
*
* @param	integer	ID of the album
*
* @return	array	Array of album info
*/
function fetch_albuminfo($albumid)
{
	global $vbulletin;

	$albuminfo = $vbulletin->db->query_first("
		SELECT album.*, user.username
		FROM " . TABLE_PREFIX . "album AS album
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (album.userid = user.userid)
		WHERE album.albumid = " . intval($albumid)
	);
	if (!$albuminfo)
	{
		return array();
	}

	$albuminfo['title_html'] = fetch_word_wrapped_string(fetch_censored_text($albuminfo['title']));
	$albuminfo['description_html'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($albuminfo['description'])));
	$albuminfo['picturecount'] = (!can_moderate(0, 'canmoderatepictures') AND $albuminfo['userid'] != $vbulletin->userinfo['userid']) ?
		$albuminfo['visible'] :
		$albuminfo['visible'] + $albuminfo['moderation']
	;

	($hook = vBulletinHook::fetch_hook('album_fetch_albuminfo')) ? eval($hook) : false;

	return $albuminfo;
}

/**
* Fetches picture info for the specified picture/album combination. That is,
* the picture must be in the specified album. Also does some preperation on
* the data for display.
*
* @param	integer	ID of picture
* @param	integer	ID of album
*
* @return	array	Array of picture information
*/
function fetch_pictureinfo($pictureid, $albumid)
{
	global $vbulletin;

	$pictureinfo = $vbulletin->db->query_first("
		SELECT picture.pictureid, picture.userid, picture.caption, picture.extension, picture.filesize,
			picture.width, picture.height, picture.reportthreadid, picture.state,
			picture.idhash, picture.thumbnail_filesize, albumpicture.dateline, albumpicture.albumid
		FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = albumpicture.pictureid)
		WHERE albumpicture.albumid = " . intval($albumid) . "
			AND albumpicture.pictureid = " . intval($pictureid)
	);

	if (!$pictureinfo)
	{
		return array();
	}

	$pictureinfo['caption_html'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($pictureinfo['caption'])));

	($hook = vBulletinHook::fetch_hook('album_fetch_pictureinfo')) ? eval($hook) : false;

	return $pictureinfo;
}

/**
* Prepares a picture array for thumbnail display.
*
* @param	array	Array of picture info
* @param	array	Container info (either for a group or album); changes thumbnail URL
*
* @return	array	Array of picture info modified
*/
function prepare_pictureinfo_thumb($pictureinfo, $displaytypeinfo)
{
	global $vbulletin;

	$pictureinfo['caption_preview'] = fetch_censored_text(fetch_trimmed_title(
		$pictureinfo['caption'],
		$vbulletin->options['album_captionpreviewlen']
	));

	$pictureinfo['thumburl'] = ($pictureinfo['thumbnail_filesize'] ? fetch_picture_url($pictureinfo, $displaytypeinfo, true) : '');
	$pictureinfo['dimensions'] = ($pictureinfo['thumbnail_width'] ? "width=\"$pictureinfo[thumbnail_width]\" height=\"$pictureinfo[thumbnail_height]\"" : '');
	$pictureinfo['date'] = vbdate($vbulletin->options['dateformat'], $pictureinfo['dateline'], true);
	$pictureinfo['time'] = vbdate($vbulletin->options['timeformat'], $pictureinfo['dateline']);

	($hook = vBulletinHook::fetch_hook('album_prepare_thumb')) ? eval($hook) : false;

	return $pictureinfo;
}

/**
* Determines information about this picture's location in the specified
* album or group. Returns an array with information about the first, previous,
* next, and/or last pictures.
*
* @param	resource	DB query result
* @param	integer		Current picture ID
*
* @return	array		Information about previous/next pictures (including phrases)
*/
function fetch_picture_location_info($navpictures_sql, $pictureid)
{
	global $vbphrase, $show, $vbulletin;

	$navpictures = array();
	$output = array();
	$cur_pic_position = -1;

	$key = 0;
	while ($navpicture = $vbulletin->db->fetch_array($navpictures_sql))
	{
		$navpictures["$key"] = $navpicture['pictureid'];

		if ($navpicture['pictureid'] == $pictureid)
		{
			$cur_pic_position = $key;
		}

		if ($cur_pic_position > 0 AND $key == $cur_pic_position + 1)
		{
			// we've matched at something other than the first entry,
			// and we're one past this current key, which means that we have a next and prev.
			// If we matched at key 0, we need to get the last entry, so keep going.
			break;
		}

		$key++;
	}

	$show['picture_nav'] = ($cur_pic_position > -1 AND sizeof($navpictures) > 1);

	if ($show['picture_nav'])
	{
		if (isset($navpictures[$cur_pic_position - 1]))
		{
			// have a previous pic
			$output['prev_pictureid'] = $navpictures[$cur_pic_position - 1];
			$output['prev_text'] = $vbphrase['previous_picture'];
			$output['prev_text_short'] = $vbphrase['prev_picture_short'];
		}
		else
		{
			// go to end
			$output['prev_pictureid'] = end($navpictures);
			$output['prev_text'] = $vbphrase['last_picture'];
			$output['prev_text_short'] = $vbphrase['last_picture_short'];
		}

		if (isset($navpictures[$cur_pic_position + 1]))
		{
			// have a next pic
			$output['next_pictureid'] = $navpictures[$cur_pic_position + 1];
			$output['next_text'] = $vbphrase['next_picture'];
			$output['next_text_short'] = $vbphrase['next_picture_short'];
		}
		else
		{
			// go to beginning
			$output['next_pictureid'] = $navpictures[0];
			$output['next_text'] = $vbphrase['first_picture'];
			$output['next_text_short'] = $vbphrase['first_picture_short'];
		}
	}

	$output['pic_position'] = $cur_pic_position + 1; // make it start at 1, instead of 0

	return $output;
}

/**
* Fetches the overage value for total number of pictures for a user.
*
* @param	integer	User ID to look for
* @param	integer	Maximum number of pics allowed; 0 means no limit
* @param	integer	Number of images they are currently uploading that aren't in the DB yet
*
* @return	integer	Amount of overage; <= 0 means no overage
*/
function fetch_count_overage($userid, $maxpics, $upload_count = 1)
{
	global $vbulletin;

	if (!$maxpics)
	{
		// never over
		return -1;
	}

	$count = $vbulletin->db->query_first("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "picture
		WHERE userid = " . intval($userid)
	);

	return $count['total'] + $upload_count - $maxpics;
}

/**
* Fetches the overage value for total filesize of pictures for a user.
*
* @param	integer	User ID to look for
* @param	integer	Maximum total filesize allowed; 0 means no limit
* @param	integer	Number of bytes they are currently uploading that aren't in the DB yet
*
* @return	integer	Amount of overage; <= 0 means no overage
*/
function fetch_size_overage($userid, $maxsize, $upload_bytes = 0)
{
	global $vbulletin;

	if (!$maxsize)
	{
		// never over
		return -1;
	}

	$size = $vbulletin->db->query_first("
		SELECT SUM(filesize) AS totalsize
		FROM " . TABLE_PREFIX . "picture
		WHERE userid = " . intval($userid)
	);

	return $size['totalsize'] + $upload_bytes - $maxsize;
}

/**
* Determines whether the current browsing user can see private albums
* for the specified album owner.
*
* @param	integer	Album owner user ID
*
* @return	boolean True if yes
*/
function can_view_private_albums($albumuserid)
{
	global $vbulletin;
	static $albumperms_cache = array();

	$albumuserid = intval($albumuserid);
	if (isset($albumperms_cache["$albumuserid"]))
	{
		return $albumperms_cache["$albumuserid"];
	}

	if ($vbulletin->userinfo['userid'] == $albumuserid)
	{
		$can_see_private = true;
	}
	else if ($vbulletin->userinfo['userid'] == 0)
	{
		$can_see_private = false;
	}
	else if (can_moderate(0, 'caneditalbumpicture') OR can_moderate(0, 'candeletealbumpicture'))
	{
		$can_see_private = true;
	}
	else
	{
		$friend_record = $vbulletin->db->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "userlist
			WHERE userid = $albumuserid
				AND relationid = " . $vbulletin->userinfo['userid'] . "
				AND type = 'buddy'
		");
		$can_see_private = ($friend_record ? true : false);
	}

	($hook = vBulletinHook::fetch_hook('album_can_see_private')) ? eval($hook) : false;

	$albumperms_cache["$albumuserid"] = $can_see_private;
	return $can_see_private;
}

/**
* Determines whether the current browsing user can see profile albums
* for the specified album owner.
*
* @param	integer	Album owner user ID
*
* @return	boolean True if yes
*/
function can_view_profile_albums($albumuserid)
{
	global $vbulletin;

	$albumuserid = intval($albumuserid);

	return ($vbulletin->userinfo['userid'] == $albumuserid OR can_moderate(0, 'caneditalbumpicture') OR can_moderate(0, 'candeletealbumpicture')) ? true : false;
}

/**
* Fetches the file system path for a specified picture.
*
* @param	array	Array of picture info
* @param	boolean	True if we want to fetch the thumb URL
* @param	boolean	True if we want to return the filename (not just the dir path)
*
* @return	string	FS Path
*/
function fetch_picture_fs_path($pictureinfo, $thumb = false, $with_filename = true)
{
	global $vbulletin;

	if ($vbulletin->options['album_dataloc'] == 'fs_directthumb' AND $thumb == true)
	{
		$filepath = $vbulletin->options['album_thumbpath'];
	}
	else
	{
		$filepath = $vbulletin->options['album_picpath'];
	}

	$path = $filepath . '/' . floor($pictureinfo['pictureid'] / 1000);

	if ($with_filename)
	{
		if ($thumb)
		{
			$path .= "/$pictureinfo[idhash]_$pictureinfo[pictureid].$pictureinfo[extension]";
		}
		else
		{
			$path .= "/$pictureinfo[pictureid].picture";
		}
	}

	return $path;
}

/**
* Verifies that the picture filesystem path is created as necessary.
*
* @param	array	Array of picture info (to deduce the path)
* @param	boolean	True if you want to verify the thunbnail path
*
* @return	string|bool	Path if successful, false otherwise
*/
function verify_picture_fs_path($pictureinfo, $thumb = false)
{
	global $vbulletin;

	if (!function_exists('vbmkdir'))
	{
		require_once(DIR . '/includes/functions_file.php');
	}

	$path = fetch_picture_fs_path($pictureinfo, $thumb, false);
	if (vbmkdir($path))
	{
		return $path;
	}
	else
	{
		return false;
	}
}

/**
* Fetches the URL used to display a picture.
*
* @param	array	Array of picture info
* @param	array	Array of container info (eg, group or album info array)
* @param	boolean	True if you want the thumbnail URL
*
* @return	string	Picture URL. Generally relative to the main vB directory.
*/
function fetch_picture_url($pictureinfo, $displaytypeinfo, $thumb = false)
{
	global $vbulletin;

	if ($vbulletin->options['album_dataloc'] == 'fs_directthumb' AND $thumb == true)
	{
		return $vbulletin->options['album_thumburl'] . '/' . floor($pictureinfo['pictureid'] / 1000)
			. "/$pictureinfo[idhash]_$pictureinfo[pictureid].$pictureinfo[extension]?dl=$pictureinfo[thumbnail_dateline]";
	}
	else
	{
		if (isset($displaytypeinfo['albumid']))
		{
			$displaybit = "&amp;albumid=$displaytypeinfo[albumid]";
		}
		else if (isset($displaytypeinfo['groupid']))
		{
			$displaybit = "&amp;groupid=$displaytypeinfo[groupid]";
		}
		else
		{
			$displaybit = '';
		}

		return 'picture.php?' . $vbulletin->session->vars['sessionurl']
			. "pictureid=$pictureinfo[pictureid]$displaybit&amp;dl=$pictureinfo[thumbnail_dateline]"
			. ($thumb ? '&amp;thumb=1' : '');
	}
}

/**
* Mini-factory method to create the correct data manager based
* the format pictures are stored in.
*
* @return	string	Name of DM to instantiate
*/
function fetch_picture_dm_name()
{
	global $vbulletin;

	if ($vbulletin->options['album_dataloc'] == 'db')
	{
		return 'Picture_Database';
	}
	else
	{
		return 'Picture_Filesystem';
	}
}

/**
* Checks an album and adds it to the recently updated albums list
* if it is generally viewable by other users.
*
* @param array mixed $userinfo					Info array of the album owner
* @param array mixed $albuminfo					Info array of the album
*/
function exec_album_updated($userinfo, $albuminfo)
{
	global $vbulletin;

	cache_permissions($userinfo);

	if (4 == $userinfo['usergroupid'])
	{
		return;
	}

	if (!($userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']))
	{
		return;
	}

	if (!$albuminfo['albumid'])
	{
		return;
	}

	$vbulletin->db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "albumupdate
			(albumid, dateline)
		VALUES
			(" . intval($albuminfo['albumid']) . ", " . TIMENOW . ")
	");
}

/**
 * Rebuilds Album Update cache
 */
function exec_rebuild_album_updates()
{
	global $vbulletin;

	$vbulletin->db->query_write("TRUNCATE " . TABLE_PREFIX . "albumupdate");

	if (!$vbulletin->options['album_recentalbumdays'])
	{
		return;
	}

	$results = $vbulletin->db->query_read("
		SELECT album.albumid, album.userid, album.lastpicturedate, user.usergroupid, user.infractiongroupids, user.infractiongroupid
		FROM " . TABLE_PREFIX . "album AS album
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (album.userid = user.userid)
		WHERE lastpicturedate > " . (TIMENOW - $vbulletin->options['album_recentalbumdays'] * 86400) . "
			AND state = 'public'
			AND visible > 0
	");

	$recent_updates = array();
	while ($result = $vbulletin->db->fetch_array($results))
	{
		cache_permissions($result, false);

		if ((4 != $result['permissions']['usergroupid']) AND (4 != $result['infractiongroupid']) AND ($result['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']))
		{
			$recent_updates[] = "($result[albumid], $result[lastpicturedate])";
		}
	}
	$vbulletin->db->free_result($results);

	if (sizeof($recent_updates))
	{
		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "albumupdate
				(albumid, dateline)
			VALUES
				" . implode (',', $recent_updates) . "
		");
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
