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


$parentassoc = array();

/**
 * Contructs a Post Tree
 *
 * @param	string	The template Name to use
 * @param	integer	The Thread ID
 * @param	integer	The "Root" post for which to work from
 * @param	integer	The current "Depth" within the tree
 *
 * @return	string	The Generated Tree
 *
 */
function &construct_post_tree($templatename, $threadid, $parentid = 0, $depth = 1)
{
	global $vbulletin, $stylevar, $parentassoc, $show, $vbphrase, $threadedmode;
	static $postcache;

	if (!$threadedmode AND $vbulletin->userinfo['postorder'])
	{
		$postorder = 'DESC';
	}

	$depthnext = $depth + 2;
	if (!$postcache)
	{
		$posts = $vbulletin->db->query_read_slave("
			SELECT post.parentid, post.postid, post.userid, post.pagetext, post.dateline, IF(visible = 2, 1, 0) AS isdeleted,
				IF(user.username <> '', user.username, post.username) AS username
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = post.userid
			WHERE post.threadid = $threadid
			ORDER BY dateline $postorder
		");
		while ($post = $vbulletin->db->fetch_array($posts))
		{
			if (!$threadedmode)
			{
				$post['parentid'] = 0;
			}
			$postcache[$post['parentid']][$post['postid']] = $post;
		}
		ksort($postcache);
	}
	$counter = 0;
	$postbits = '';
	if (is_array($postcache["$parentid"]))
	{
		foreach ($postcache["$parentid"] AS $post)
		{
			$parentassoc[$post['postid']] = $post['parentid'];

			if (($depth + 1) % 4 == 0)
			{ // alternate colors when switching depths; depth gets incremented by 2 each time
				$post['backcolor'] = '{firstaltcolor}';
				$post['bgclass'] = 'alt1';
			}
			else
			{
				$post['backcolor'] = '{secondaltcolor}';
				$post['bgclass'] = 'alt2';
			}
			$post['postdate'] = vbdate($vbulletin->options['dateformat'], $post['dateline'], true);
			$post['posttime'] = vbdate($vbulletin->options['timeformat'], $post['dateline']);

			// cut page text short if too long
			if (vbstrlen($post['pagetext']) > 100)
			{
				$spacepos = strpos($post['pagetext'], ' ', 100);
				if ($spacepos != 0)
				{
					$post['pagetext'] = substr($post['pagetext'], 0, $spacepos) . '...';
				}
			}
			$post['pagetext'] = nl2br(htmlspecialchars_uni($post['pagetext']));

			($hook = vBulletinHook::fetch_hook('threadmanage_construct_post_tree')) ? eval($hook) : false;

			eval('$postbits .=  "' . fetch_template($templatename) . '";');

			$ret =& construct_post_tree($templatename, $threadid, $post['postid'], $depthnext);
			$postbits .= $ret;
		}
	}

	return $postbits;
}

/**
 * Generated the Javascript for Parent Assosciations
 *
 * @param	array	Associative Array of Postids and ParentIDs
 *
 * @return	string	The Javascript
 *
 */
function &construct_js_post_parent_assoc(&$array)
{
	$parentassocjs = array();

	ksort($array);
	foreach ($array AS $postid => $parentid)
	{
		$parentassocjs[] = "$postid : $parentid";
	}

	return "var parentassoc = {\r\n\t" . implode(",\r\n\t", $parentassocjs) . "\r\n };";
}


/**
 * Constructs a Forum Jump Menu for use when moving an item to a new forum
 *
 * @param	integer	The "Root" ID from which to generate this Menu
 * @param	integer	A Forum ID to "exclude" from the menu
 * @param	integer	If 1, removes all previous information from the Forum Jump Menu
 * @param	string	Characters to prepend to the items in the Jump Box
 *
 * @return	string	The generated forum jump menu
 *
 */
function construct_move_forums_options($parentid = -1, $excludeforumid = NULL, $addbox = 1, $prependchars = '')
{
	global $vbulletin, $optionselected, $jumpforumid, $jumpforumtitle, $jumpforumbits, $vbphrase, $curforumid;
	static $prependlength;

	if (empty($prependlength))
	{
		$prependlength = strlen(FORUM_PREPEND);
	}

	if (empty($vbulletin->iforumcache))
	{
		// get the vbulletin->iforumcache, as we use it all over the place, not just for forumjump
		cache_ordered_forums(0, 1);
	}
	if (empty($vbulletin->iforumcache["$parentid"]) OR !is_array($vbulletin->iforumcache["$parentid"]))
	{
		return;
	}

	if ($addbox == 1)
	{
		$jumpforumbits = '';
	}

	foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		$forumperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			continue;
		}
		else
		{
			// set $forum from the $vbulletin->forumcache
			$forum = $vbulletin->forumcache["$forumid"];

			$optionvalue = $forumid;
			$optiontitle = $prependchars . " $forum[title]";

			if ($forum['link'])
			{
				$optiontitle .= " ($vbphrase[link])";
			}
			else if (!($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']))
			{
				$optiontitle .= " ($vbphrase[category])";
			}
			else if (!($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting']))
			{
				$optiontitle .= " ($vbphrase[no_posting])";
			}

			$optionclass = 'fjdpth' . iif($forum['depth'] > 3, 3, $forum['depth']);

			if ($curforumid == $optionvalue)
			{
				$optionselected = ' ' . 'selected="selected"';
				$optionclass = 'fjsel';
				$selectedone = 1;
			}
			else
			{
				$optionselected = '';
			}
			if ($excludeforumid == NULL OR $excludeforumid != $forumid)
			{
				eval('$jumpforumbits .= "' . fetch_template('option') . '";');
			}

			construct_move_forums_options($optionvalue, $excludeforumid, 0, $prependchars . FORUM_PREPEND);

		} // if can view
	} // end foreach ($vbulletin->iforumcache[$parentid] AS $forumid)

	return $jumpforumbits;
}

/**
 * Is this user the first poster in a threadid ?
 *
 * @param	integer	Thread ID to check
 * @param	integer	The User ID, or -1 for currently logged in user
 *
 * @return	boolean	Whether the user is the first poster.
 *
 */
function is_first_poster($threadid, $userid = -1)
{
	global $vbulletin;

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
	}
	$firstpostinfo = $vbulletin->db->query_first_slave("
		SELECT userid
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = " . intval($threadid) . "
		ORDER BY dateline
	");
	return ($firstpostinfo['userid'] == $userid);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
