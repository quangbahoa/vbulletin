<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Fetches information about the selected message with permission checks
*
* @param	integer	The post we want info about
* @param	boolean	Should we throw an error if there is the id is invalid?
* @param	mixed	Should a permission check be performed as well
*
* @return	array	Array of information about the message or prints an error if it doesn't exist / permission problems
*/
function verify_groupmessage($gmid, $alert = true, $perm_check = true)
{
	global $vbulletin, $vbphrase;

	$messageinfo = fetch_groupmessageinfo($gmid);
	if (!$messageinfo)
	{
		if ($alert)
		{
			standard_error(fetch_error('invalidid', $vbphrase['social_group'], $vbulletin->options['contactuslink']));
		}
		else
		{
			return 0;
		}
	}

	if ($perm_check)
	{
		if ($messageinfo['state'] == 'deleted')
		{
			$can_view_deleted = (can_moderate(0, 'canmoderategroupmessages') OR
				($messageinfo['group_ownerid'] == $vbulletin->userinfo['userid']
					AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
				)
			);

			if (!$can_view_deleted)
			{
				standard_error(fetch_error('invalidid', $vbphrase['message'], $vbulletin->options['contactuslink']));
			}
		}

		if ($messageinfo['state'] == 'moderation')
		{
			$can_view_message = (
				can_moderate(0, 'canmoderategroupmessages')
				OR $messageinfo['postuserid'] == $vbulletin->userinfo['userid']
				OR (
					$messageinfo['group_ownerid'] == $vbulletin->userinfo['userid']
					AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
				)
			);

			if (!$can_view_message)
			{
				standard_error(fetch_error('invalidid', $vbphrase['message'], $vbulletin->options['contactuslink']));
			}
		}

//	 	Need coventry support first
//		if (in_coventry($userinfo['userid']) AND !can_moderate())
//		{
//			standard_error(fetch_error('invalidid', $vbphrase['gmessage'], $vbulletin->options['contactuslink']));
//		}
	}

	return $messageinfo;
}

/**
* Fetches information about the selected user message entry
*
* @param	integer	gmid of requested
*
* @return	array|false	Array of information about the user message or false if it doesn't exist
*/
function fetch_groupmessageinfo($gmid)
{
	global $vbulletin;
	static $groupmessagecache;

	$gmid = intval($gmid);
	if (!isset($groupmessagecache["$gmid"]))
	{
		$groupmessagecache["$gmid"] = $vbulletin->db->query_first("
			SELECT groupmessage.*, socialgroup.creatoruserid AS group_ownerid
			FROM " . TABLE_PREFIX . "groupmessage AS groupmessage
			LEFT JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (groupmessage.groupid = socialgroup.groupid)
			WHERE groupmessage.gmid = $gmid
		");
	}

	if (!$groupmessagecache["$gmid"])
	{
		return false;
	}
	else
	{
		return $groupmessagecache["$gmid"];
	}
}

/**
* Parse message content for preview
*
* @param	array		Message and disablesmilies options
*
* @return	string	Eval'd html for display as the preview message
*/
function process_group_message_preview($message)
{
	global $vbulletin, $vbphrase, $stylevar, $show;

	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$previewhtml = '';
	if ($previewmessage = $bbcode_parser->parse($message['message'], 'socialmessage', $message['disablesmilies'] ? 0 : 1))
	{
		eval('$previewhtml = "' . fetch_template('visitormessage_preview'). '";');
	}

	return $previewhtml;
}

/**
 * Fetches information regarding a Social Group
 *
 * @param	integer	Group ID
 *
 * @return	array	Group Information
 *
 */
function fetch_socialgroupinfo($groupid)
{
	global $vbulletin;

	// This is here for when we are doing inline moderation - it takes away the need to repeatedly query the database
	// if we are deleting all the messages in the same group
	static $groupcache;

	if (is_array($groupcache["$groupid"]))
	{
		return $groupcache["$groupid"];
	}

	$groupcache["$groupid"] = prepare_socialgroup($vbulletin->db->query_first("
		SELECT socialgroup.*,
			user.username AS creatorusername
			" . ($vbulletin->userinfo['userid'] ? ', socialgroupmember.type AS membertype': '') . "
		FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (socialgroup.creatoruserid = user.userid)
		" . ($vbulletin->userinfo['userid'] ?
			"LEFT JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
				(socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . " AND socialgroupmember.groupid = socialgroup.groupid)" :
			'') . "
		WHERE socialgroup.groupid = " . intval($groupid) . "
	"));

	return $groupcache["$groupid"];
}

/**
 * Takes information regardign a group, and prepares the information within it
 * for display
 *
 * @param	array	Group Array
 *
 * @return	array	Group Array with prepared information
 *
 */
function prepare_socialgroup($group)
{
	global $vbulletin;

	if (!is_array($group))
	{
		return array();
	}

	$group['joindate'] = ($group['joindate'] ? vbdate($vbulletin->options['dateformat'], $group['joindate'], true) : '');
	$group['createdate'] = ($group['createdate'] ? vbdate($vbulletin->options['dateformat'], $group['createdate'], true) : '');

	$group['members'] = vb_number_format($group['members']);
	$group['moderatedmembers'] = vb_number_format($group['moderatedmembers']);

	if (!($group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_albums']))
	{
		// albums disabled in this group - force 0 pictures
		$group['picturecount'] = 0;
	}
	$group['rawpicturecount'] = $group['picturecount'];
	$group['picturecount'] = vb_number_format($group['picturecount']);

	$group['rawname'] = $group['name'];
	$group['rawdescription'] = $group['description'];

	$group['name'] = fetch_word_wrapped_string(fetch_censored_text($group['name']));
 	$group['shortdescription'] = fetch_word_wrapped_string(fetch_censored_text(fetch_trimmed_title($group['description'], 200)));
	$group['description'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($group['description'])));

	$group['is_owner'] = ($group['creatoruserid'] == $vbulletin->userinfo['userid']);

	$group['is_automoderated'] = (
		$group['options'] & $vbulletin->bf_misc_socialgroupoptions['owner_mod_queue']
		AND $vbulletin->options['sg_allow_owner_mod_queue']
		AND !$vbulletin->options['social_moderation']
	);

	$group['canviewcontent'] = (
		(
			!($group['options'] & $vbulletin->bf_misc_socialgroupoptions['join_to_view'])
			OR !$vbulletin->options['sg_allow_join_to_view']
		) // The above means that you dont have to join to view
		OR $group['membertype'] == 'member'
		// Or can moderate comments
		OR can_moderate(0, 'canmoderategroupmessages')
		OR can_moderate(0, 'canremovegroupmessages')
		OR can_moderate(0, 'candeletegroupmessages')
		OR can_moderate(0, 'candeletegroupmessages')
	);

 	$group['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $group['lastpost'], true);
 	$group['lastposttime'] = vbdate($vbulletin->options['timeformat'], $group['lastpost']);

 	$group['lastposterid'] = $group['canviewcontent'] ? $group['lastposterid'] : 0;
 	$group['lastposter'] = $group['canviewcontent'] ? $group['lastposter'] : '';

	($hook = vBulletinHook::fetch_hook('group_prepareinfo')) ? eval($hook) : false;

	return $group;
}


/**
 * Fetches information regarding a specific group Picture
 *
 * @param	integer	Picture ID
 * @param	integer	Group ID
 *
 * @return	array	Picture information
 *
 */
function fetch_socialgroup_picture($pictureid, $groupid)
{
	global $vbulletin;

	$picture = $vbulletin->db->query_first("
		SELECT picture.pictureid, picture.userid, picture.caption, picture.extension, picture.filesize,
			picture.width, picture.height, picture.reportthreadid,
			picture.idhash, picture.thumbnail_filesize,
			socialgrouppicture.dateline, socialgrouppicture.groupid, user.username
		FROM " . TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = socialgrouppicture.pictureid)
		INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
			(socialgroupmember.userid = picture.userid AND socialgroupmember.groupid = $groupid AND socialgroupmember.type = 'member')
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = picture.userid)
		WHERE socialgrouppicture.groupid = $groupid
			AND socialgrouppicture.pictureid = $pictureid
	");

	($hook = vBulletinHook::fetch_hook('group_fetch_pictureinfo')) ? eval($hook) : false;

	return $picture;
}


/**
 * Rebuilds Group Counter information
 *
 * @param	integer	Group ID
 *
 */
function build_group_counters($groupid)
{
	global $vbulletin;

	if (!($groupid = intval($groupid)))
	{
		return;
	}

	$messages = $vbulletin->db->query_first("
		SELECT
			SUM(IF(state = 'visible', 1, 0)) AS visible,
			SUM(IF(state = 'deleted', 1, 0)) AS deleted,
			SUM(IF(state = 'moderation', 1, 0)) AS moderation
		FROM " . TABLE_PREFIX . "groupmessage
		WHERE groupid = $groupid
	");

	$lastpost = $vbulletin->db->query_first("
		SELECT user.username, gm.postuserid, gm.dateline, gm.gmid
		FROM " . TABLE_PREFIX . "groupmessage AS gm
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = gm.postuserid)
		WHERE gm.groupid = $groupid AND gm.state = 'visible'
		ORDER BY gm.dateline DESC
		LIMIT 1
	");

	$groupinfo = fetch_socialgroupinfo($groupid);

	$dataman =& datamanager_init('SocialGroup', $vbulletin, ERRTYPE_SILENT);
	$dataman->set_existing($groupinfo);

	$dataman->set('lastpost', $lastpost['dateline']);
	$dataman->set('lastposter', $lastpost['username']);
	$dataman->set('lastposterid', $lastpost['postuserid']);
	$dataman->set('lastgmid', $lastpost['gmid']);
	$dataman->set('visible', $messages['visible']);
	$dataman->set('deleted', $messages['deleted']);
	$dataman->set('moderation', $messages['moderation']);

	($hook = vBulletinHook::fetch_hook('group_build_counters')) ? eval($hook) : false;

	$dataman->save();

	list($pendingcountforowner) = $vbulletin->db->query_first("
		SELECT SUM(moderation) FROM " . TABLE_PREFIX . "socialgroup
		WHERE creatoruserid = " . $groupinfo['creatoruserid']
	, DBARRAY_NUM);

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "user
		SET gmmoderatedcount = " . intval($pendingcountforowner) . "
		WHERE userid = " . $groupinfo['creatoruserid']
	);
}


/**
 * Whether the current logged in user can join the group
 *
 * @param	array	Group information
 *
 * @return	boolean
 *
 */
function can_join_group($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups']
		AND
		(
			$group['membertype'] == 'invited'
			OR
			(
				$group['type'] != 'inviteonly'
				AND empty($group['membertype'])
			)
		)

	);
}

/**
 * Whether the currently logged in user can leave the group
 *
 * @param	array	Group Information
 *
 * @return	boolean
 *
 */
function can_leave_group($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND !empty($group['membertype'])
		AND !$group['is_owner']
	);
}

/**
 * Whether the currently logged in user can edit the group
 *
 * @param	array	Group Information
 *
 * @return	boolean
 *
 */

function can_edit_group($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND
		(
			(
				$group['is_owner']
				AND
				(
					$vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['caneditowngroups']
					OR $group['members'] == 1
				)
			)
			OR can_moderate(0, 'caneditsocialgroups')
		)
	);
}

/**
 * Whether the currently logged in user can delete the group
 *
 * @param	array	Group Information
 *
 * @return	boolean
 *
 */
function can_delete_group($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND
		(
			(
				$group['is_owner']
				AND
				(
					$vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['candeleteowngroups']
					OR
					(
						$group['members'] == 1
						AND $vbulletin->options['sg_allow_delete_empty_group']
					)
				)
			)
			OR can_moderate(0, 'candeletesocialgroups')
		)
	);
}

/**
 * Fetches information regarding moderating permissions for a social group and
 * the logged in user
 *
 * @param	string	The Permission to be fetched
 * @param	array	Information regarding the group
 *
 * @return	boolean	Whether or not the logged in user has the permission
 * 					or not for the group specified
 */
function fetch_socialgroup_modperm($perm, $group = array())
{
	global $vbulletin;

	$userinfo = $vbulletin->userinfo;

	switch ($perm)
	{
		case 'canmoderategroupmessages':
		case 'candeletegroupmessages':
		{
			return (
				can_moderate(0, $perm)
				OR (
					$userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
					AND $group['is_owner']
				)
			);
		}
		break;

		case 'canremovegroupmessages':
		{
			return can_moderate(0, 'canremovegroupmessages');
		}
		break;

		case 'canundeletegroupmessages':
		{
			return can_moderate(0, 'candeletegroupmessages');
		}
		break;

		case 'canviewdeleted':
		{
			return (
				can_moderate(0, 'canmoderategroupmessages') 
				OR (
					$userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
					AND $group['is_owner']
				)
			);
		}
		break;

		case 'canremovepicture':
		{
			return (
				can_moderate(0, 'caneditalbumpicture')
				OR (
					$userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
					AND $group['is_owner']
				)
			);
		}
		break;

		case 'caninvitemoderatemembers':
		{
			return (
				$group['is_owner']
				OR can_moderate(0, 'candeletesocialgroups')
			);
		}
		break;

		case 'canmanagemembers':
		{
			return (
				(
					(
						($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups'])
						AND $group['is_owner']
					)
					OR can_moderate(0, 'candeletesocialgroups')
				)
				AND $group['members'] > 1
			);
		}
		break;
	}

	return false;
}

/**
 * Determines whether we can edit a specific group message
 *
 * @param	array	Message Information
 * @param	array	Group Information
 *
 * @return	boolean
 */
function can_edit_group_message($messageinfo, $group)
{
	global $vbulletin;

	switch ($messageinfo['state'])
	{
		case 'deleted':
		{
			$canviewdeleted = (
				fetch_socialgroup_modperm('candeletegroupmessages', $group)
				OR (
					$vbulletin->userinfo['userid'] == $messageinfo['postuserid']
					AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanagemessages']
				)
			);

			if (!$canviewdeleted)
			{
				return false;
			}

			return fetch_socialgroup_modperm('candeletegroupmessages', $group) AND can_moderate(0, 'caneditgroupmessages');

		}
		break;

		default:
		{
			if (
				$messageinfo['postuserid'] == $vbulletin->userinfo['userid']
				AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanagemessages']
			)
			{
				return true;
			}
		}
	}

	return can_moderate(0, 'caneditgroupmessages');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 27273 $
|| ####################################################################
\*======================================================================*/
?>
