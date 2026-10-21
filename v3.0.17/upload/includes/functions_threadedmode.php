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

// ###################### Start findparents #######################
function fetch_post_parentlist($postid)
{
	global $postparent;

	$retlist = '';
	$postid = $postparent["$postid"];

	while ($postid != 0)
	{
		$retlist .= ",$postid";
		$postid = $postparent["$postid"];
	}

	return $retlist;
}

// ###################### Start fetch statusicon from child posts #######################
function fetch_statusicon_from_child_posts($postid)
{
	// looks through children to see if there are new posts or not
	global $postarray, $ipostarray, $bbuserinfo;

	if ($postarray["$postid"]['dateline'] > $bbuserinfo['lastvisit'])
	{
		return 1;
	}
	else
	{
		if (is_array($ipostarray["$postid"]))
		{ //if it has children look in there
			foreach($ipostarray["$postid"] AS $postid)
			{
				if (fetch_statusicon_from_child_posts($postid))
				{
					return 1;
				}
			}
		}
		return 0;
	}
}

// ###################### Start showPostLink #######################
function construct_threaded_post_link($post, $imageString, $depth, $haschildren, $highlightpost = false)
{
	global $vboptions, $stylevar, $session, $bgclass, $bbuserinfo, $curpostid, $parent_postids, $morereplies, $threadedmode, $vbphrase, $postattach;
	static $lasttitle;

	//print_array($post);

	if ($threadedmode == 2 AND $highlightpost)
	{
		$highlightpost = 1;
	}
	else
	{
		$highlightpost = 0;
	}

	// write 'more replies below' link
	if ($vboptions['threaded_listdepth'] != 0 AND $depth == $vboptions['threaded_listdepth'] AND $post['postid'] != $curpostid AND $haschildren AND ($vboptions['threaded_listdepth'] != 0 AND $depth == $vboptions['threaded_listdepth'] AND !strpos(' ,' . $curpostid . $parent_postids . ',' , ',' . $post['postid'] . ',' )))
	{
		$morereplies[$post['postid']] = 1;
		return "writeLink($post[postid], " . fetch_statusicon_from_child_posts($post['postid']) . ", 0, 0, \"$imageString\", \"\", \"more\", \"\", $highlightpost);\n";
	}

	// get time fields
	$post['date'] = vbdate($vboptions['dateformat'], $post['dateline'], 1);
	$post['time'] = vbdate($vboptions['timeformat'], $post['dateline']);

	// get status icon and paperclip
	$post['statusicon'] = iif($post['dateline'] > $bbuserinfo['lastvisit'], 1, 0);

	// get paperclip
	$post['paperclip'] = 0;
	if (is_array($postattach["$post[postid]"]))
	{
		foreach ($postattach["$post[postid]"] AS $attachment)
		{
			if ($attachment['visible'])
			{
				$post['paperclip'] = 1;
				break;
			}
		}
	}

	// echo some text from the post if no title
	if ($post['isdeleted'])
	{
		$post['title'] = $vbphrase['post_deleted'];
	}
	else if (empty($post['title']))
	{
		$pagetext = htmlspecialchars_uni($post['pagetext']);

		$pagetext = strip_bbcode($pagetext, 1);
		if (trim($pagetext) == '')
		{
			$post['title'] = $vbphrase['reply_prefix'] . ' ' . fetch_trimmed_title($lasttitle);
		}
		else
		{
			$post['title'] = '<i>' . fetch_trimmed_title($pagetext) . '</i>';
		}
	}
	else
	{
		$lasttitle = $post['title'];
		$post['title'] = fetch_trimmed_title($post['title']);
	}

	return "writeLink($post[postid], $post[statusicon], $post[paperclip], " . intval($post['userid']) . ", \"$imageString\", \"" . addslashes_js($post['title'], '"') . "\", \"" . addslashes_js($post['date'], '"') . "\", \"" . addslashes_js($post['time'], '"') . "\", $highlightpost);\n";

}

// ###################### Start getImageString #######################
function fetch_threaded_post_image_string($post, $depth)
{
	global $ipostarray, $vboptions;
	static $depthbits;

	$imgstring = array();
	$blanks = 0;

	for ($i = 1; $i < $depth; $i ++) // get initial images
	{
		if ($depthbits["$i"] == '-')
		{
			$blanks++;
		}
		else if ($blanks != 0)
		{
			$imgstring[] = $blanks;
			$imgstring[] = $depthbits["$i"];
			$blanks = 0;
		}
		else
		{
			$imgstring[] = $depthbits["$i"];
		}
	}

	if ($blanks != 0) // return blanks if there are any left over
	{
		$imgstring[] = $blanks;
	}

	// find out if current post is last at this level of the tree
	$lastElm = sizeof($ipostarray["$post[parentid]"]) - 1;
	if ($ipostarray["$post[parentid]"]["$lastElm"] == $post['postid'])
	{
		$islast = 1;
	}
	else
	{
		$islast = 0;
	}

	if ($islast == 1) // if post is not last in tree, use L graphic...
	{
		$depthbits["$depth"] = '-';
		$imgstring[] = 'L';

	}
	else // ... otherwise use T graphic
	{
		$depthbits["$depth"] = 'I';
		$imgstring[] = 'T';
	}

	return implode(',', $imgstring);

}

// ###################### Start orderPosts #######################
function sort_threaded_posts($parentid = 0, $depth = 0, $showpost = false)
{
	global $vboptions, $stylevar, $session, $ipostarray, $postarray, $links, $bgclass, $hybridposts;
	global $postorder, $parent_postids, $currentdepth, $curpostid, $cache_postids, $curpostidkey;
	global $_REQUEST;

	// make an indent for pretty HTML
	$indent = str_repeat('  ', $depth);

	foreach($ipostarray["$parentid"] AS $id)
	{

		if ($showpost OR $id == $_REQUEST['postid'])
		{
			$doshowpost = 1;
			$hybridposts[] = $id;
		}
		else
		{
			$doshowpost = 0;
		}

		if ($id == $curpostid)
		{
			// if we have reached the post that we're meant to be displaying
			// go $vboptions['threaded_listdepth'] deeper
			$vboptions['threaded_listdepth'] += $currentdepth;

			$curpostidkey = sizeof($postorder);
		}

		$haschildren = is_array($ipostarray["$id"]);

		// add this post to the postorder array
		$postorder[] = $id;

		// get post information from the $postarray
		$post = $postarray["$id"];

		// call the javascript-writing function for this link
		if (empty($links))
		{
			$links .= $indent . construct_threaded_post_link($post, '', $depth, $haschildren, $doshowpost);
		}
		else
		{
			$links .= $indent . construct_threaded_post_link($post, fetch_threaded_post_image_string($post, $depth), $depth, $haschildren, $doshowpost);
		}

		// if post has children
		// and we've not reached the maximum depth
		// and we're not on the tree that contains $curpostid (ie the postid to be displayed)
		// then print children
		if ($haschildren AND !($vboptions['threaded_listdepth'] != 0 AND $depth == $vboptions['threaded_listdepth'] AND !strpos(' ,' . $curpostid . $parent_postids . ',', ',' . $id . ',' )))
		{
			sort_threaded_posts($id, $depth +1, $doshowpost);
		}

		if ($id == $curpostid)
		{
			// undo the above
			$vboptions['threaded_listdepth'] -= $currentdepth;
		}

	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9154 $
|| ####################################################################
\*======================================================================*/
?>