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

// ###################### Start displayposttree #######################
$parentassoc = array();
function &construct_post_tree($templatename, $threadid, $parentid = 0, $depth = 1)
{
	global $DB_site, $bbuserinfo, $stylevar, $vboptions, $parentassoc, $show, $vbphrase;
	static $postcache;

	if (!$bbuserinfo['threadedmode'] AND $bbuserinfo['postorder'])
	{
		$postorder = 'DESC';
	}

	$depthnext = $depth + 2;
	if (!$postcache)
	{
		$posts = $DB_site->query("
			SELECT post.parentid, post.postid, post.userid, post.pagetext, post.dateline,
				IF(user.username <> '', user.username, post.username) AS username,
				NOT ISNULL(deletionlog.primaryid) AS isdeleted
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = post.userid
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND type = 'post')
			WHERE post.threadid = $threadid
			ORDER BY dateline $postorder
		");
		while ($post = $DB_site->fetch_array($posts))
		{
			if (!$bbuserinfo['threadedmode'])
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
			$post['postdate'] = vbdate($vboptions['dateformat'], $post['dateline'], true);
			$post['posttime'] = vbdate($vboptions['timeformat'], $post['dateline']);

			// cut page text short if too long
			if (strlen($post['pagetext']) > 100)
			{
				$spacepos = strpos($post['pagetext'], ' ', 100);
				if ($spacepos != 0)
				{
					$post['pagetext'] = substr($post['pagetext'], 0, $spacepos) . '...';
				}
			}
			$post['pagetext'] = nl2br(htmlspecialchars_uni($post['pagetext']));

			eval('$postbits .=  "' . fetch_template($templatename) . '";');

			$ret = &construct_post_tree($templatename, $threadid, $post['postid'], $depthnext);
			$postbits .= $ret;
		}
	}

	return $postbits;
}

// ###################### Start genjsparentpostassoc #######################
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

// ###################### Start getmoveforums #######################
function construct_move_forums_options($parentid = -1, $addbox = 1, $prependchars = '', $permission = '')
{
	global $DB_site, $optionselected, $jumpforumid, $jumpforumtitle, $jumpforumbits, $_FORUMOPTIONS;
	global $vboptions, $bbuserinfo, $session, $vbphrase;
	global $iforumcache, $forumcache, $curforumid;
	static $prependlength;

	if (empty($prependlength))
	{
		$prependlength = strlen(FORUM_PREPEND);
	}

	if (!isset($iforumcache))
	{
		require_once('./includes/functions_forumlist.php');

		// get the iforumcache, as we use it all over the place, not just for forumjump
		cache_ordered_forums(0, 1);
	}
	if (empty($iforumcache["$parentid"]) OR !is_array($iforumcache["$parentid"]))
	{
		return;
	}

	if ($addbox == 1)
	{
		$jumpforumbits = '';
	}

	foreach($iforumcache["$parentid"] AS $holder)
	{
		foreach($holder AS $forumid)
		{
			$forumperms = $bbuserinfo['forumpermissions']["$forumid"];
			if (!($forumperms & CANVIEW))
			{
				continue;
			}
			else
			{
				// set $forum from the $forumcache
				$forum = $forumcache["$forumid"];

				$optionvalue = $forumid;
				$optiontitle = $prependchars . " $forum[title]";

				if ($forum['link'])
				{
					$optiontitle .= " ($vbphrase[link])";
				}
				else if (!($forum['options'] & $_FORUMOPTIONS['cancontainthreads']))
				{
					$optiontitle .= " ($vbphrase[category])";
				}
				else if (!($forum['options'] & $_FORUMOPTIONS['allowposting']))
				{
					$optiontitle .= " ($vbphrase[no_posting])";
				}

				$optionclass = 'fjdpth' . iif($forum['depth'] > 3, 3, $forum['depth']);

				if ($curforumid == $optionvalue)
				{
					$optionselected = ' ' . HTML_SELECTED;
					$optionclass = 'fjsel';
					$selectedone = 1;
				}
				else
				{
					$optionselected = '';
				}
				eval('$jumpforumbits .= "' . fetch_template('option') . '";');

				construct_move_forums_options($optionvalue, 0, $prependchars . FORUM_PREPEND, $forumperms);

			} // if can view

		} // end foreach ($holder AS $forum)
	} // end foreach ($iforumcache[$parentid] AS $holder)

	return $jumpforumbits;
}

// ###################### Start isfirstposter #######################
function is_first_poster($threadid, $userid = -1)
{
	global $DB_site, $bbuserinfo;

	if ($userid == -1)
	{
		$userid = $bbuserinfo['userid'];
	}
	$firstpostinfo = $DB_site->query_first("
		SELECT userid
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = " . intval($threadid) . "
		ORDER BY dateline
	");
	return ($firstpostinfo['userid'] == $userid);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9540 $
|| ####################################################################
\*======================================================================*/
?>