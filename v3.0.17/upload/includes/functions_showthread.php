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
if (!is_object($DB_site))
{
	exit;
}

define('POST_SHOWAGE', 1);
define('POST_SHOWPOWER', 2);

require_once('./includes/functions_bbcodeparse.php');
require_once('./includes/functions_reputation.php');

// ###################### Start doimicons #######################
function construct_im_icons(&$userinfo, $ignore_off_setting = false)
{
	global $vboptions, $stylevar, $show, $vbphrase;

	$show['hasimicons'] = false;

	$userinfo['icq'] = $userinfo['icq'];
	if (!empty($userinfo['icq']) AND ($vboptions['showimicons'] OR $ignore_off_setting))
	{
		eval('$userinfo[\'icqicon\'] = "' . fetch_template('im_icq') . '";');
		$userinfo['showicq'] = true;
		$show['hasimicons'] = true;
	}
	else
	{
		$userinfo['icqicon'] = '';
		$userinfo['showicq'] = false;
	}

	if ($userinfo['aim'] != '' AND ($vboptions['showimicons'] OR $ignore_off_setting))
	{
		eval('$userinfo[\'aimicon\'] = "' . fetch_template('im_aim') . '";');
		$userinfo['showaim'] = true;
		$show['hasimicons'] = true;
	}
	else
	{
		$userinfo['aimicon'] = '';
		$userinfo['showaim'] = false;
	}

	if ($userinfo['yahoo'] != '' AND ($vboptions['showimicons'] OR $ignore_off_setting))
	{
		eval('$userinfo[\'yahooicon\'] = "' . fetch_template('im_yahoo') . '";');
		$userinfo['showyahoo'] = true;
		$show['hasimicons'] = true;
	}
	else
	{
		$userinfo['yahooicon'] = '';
		$userinfo['showyahoo'] = false;
	}

	if ($userinfo['msn'] != '' AND ($vboptions['showimicons'] OR $ignore_off_setting))
	{
		eval('$userinfo[\'msnicon\'] = "' . fetch_template('im_msn') . '";');
		$userinfo['showmsn'] = true;
		$show['hasimicons'] = true;
	}
	else
	{
		$userinfo['msnicon'] = '';
		$userinfo['showmsn'] = false;
	}

}

// ###################### Start getpostbit #######################
function construct_postbit($post, $maintemplatename = 'postbit', $alternate = '')
{
	// sorts through all the stuff to return the postbit template

	// user
	global $bbuserinfo, $session, $ignore, $permissions, $_REQUEST;
	// showthread
	global $counter, $firstnew, $highlight, $postid, $forum, $replacewords, $bgclass, $altbgclass;
	global $thread, $threadedmode, $tachyusers, $SHOWQUICKREPLY, $onload;
	global $spacer_open, $spacer_close, $parsed_postcache;
	// global options
	global $vboptions, $stylevar, $vbphrase, $DB_site, $datastore, $_USEROPTIONS, $style, $show, $usergroupcache;

	static $gotrank, $sigcache, $checkperms, $postelement, $month, $day, $year, $counter;

	// hide users in Coventry from non-staff members
	if ($tachyuser = in_coventry($post['userid']) AND !can_moderate($thread['forumid']))
	{
		return;
	}

	$counter ++;

	exec_switch_bg();

	$post = array_merge($post, convert_bits_to_array($post['options'], $_USEROPTIONS));

	if (!$vboptions['allowthreadedmode'])
	{
		$threadedmode = 0;
	}

	// set $scrolltothis value if necessary
	if (THIS_SCRIPT == 'showthread')
	{
		if ($post['postid'] == $postid)
		{
			$scrolltothis = " id=\"currentPost\"";
			if ($threadedmode == 0)
			{
				$onload = "if (is_ie || is_moz) { fetch_object('currentPost').scrollIntoView(true); }";
			}
		}
		else
		{
			$scrolltothis = '';
		}
	}

	// find first new post
	if (isset($bbuserinfo['lastvisit']))
	{
		if ($post['dateline'] > $bbuserinfo['lastvisit'] AND $firstnew == 0)
		{
			$firstnew = $post['postid'];
			$post['firstnewinsert' ] = '<a name="newpost"></a>';
		}
		else
		{
			$post['firstnewinsert'] = '';
		}
	}

	// format date/time
	$post['postdate'] = vbdate($vboptions['dateformat'], $post['dateline'], true);
	$post['posttime'] = vbdate($vboptions['timeformat'], $post['dateline']);

	// do word wrap
	if ($vboptions['wordwrap'])
	{
		$post['title'] = fetch_word_wrapped_string($post['title']);
	}
	$post['title'] = fetch_censored_text($post['title']);

	// get attachment info
	if (is_array($post['attachments']))
	{
		if (can_moderate($thread['forumid'], 'canmoderateattachments') OR $post['userid'] == $bbuserinfo['userid'])
		{
			$show['modattachmentlink'] = true;
		}
		else
		{
			$show['modattachmentlink'] = false;
		}
		$show['attachments'] = true;
		$show['moderatedattachment'] = $show['thumbnailattachment'] = $show['otherattachment'] = false;
		$show['imageattachment'] = $show['imageattachmentlink'] = false;
		$attachcount = sizeof($post['attachments']);
		$thumbcount = 0;
		if (!$vboptions['attachthumbs'] AND !$vboptions['viewattachedimages'])
		{
			$showimagesprev = $bbuserinfo['showimages'];
			$bbuserinfo['showimages'] = false;
		}
		foreach($post['attachments'] AS $attachmentid => $attachment)
		{
			if($attachment['thumbnail_filesize'] == $attachment['filesize'])
			{ // This is an image that is already thumbnail sized..
				$attachment['hasthumbnail'] = 0;
				$attachment['forceimage'] = 1;
			}
			$attachment['filename'] = fetch_censored_text(htmlspecialchars_uni($attachment['filename']));
			$attachment['attachmentextension'] = strtolower(file_extension($attachment['filename']));
			$attachment['filesize'] = vb_number_format($attachment['filesize'], 1, true);

			if ($attachment['visible'])
			{
				switch($attachment['attachmentextension'])
				{
					case 'gif':
					case 'jpg':
					case 'jpeg':
					case 'jpe':
					case 'png':
					case 'bmp':
						if (!$bbuserinfo['showimages'])
						{
							eval('$post[\'imageattachmentlinks\'] .= "' . fetch_template('postbit_attachment') . '";');
							$show['imageattachmentlink'] = true;
						}
						else if ($vboptions['attachthumbs'])
						{
							if ($attachment['hasthumbnail'])
							{
								$thumbcount++;
								if ($thumbcount >= $vboptions['attachrow'])
								{
									$thumbcount = 0;
									$show['br'] = true;
								}
								else
								{
									$show['br'] = false;
								}
								eval('$post[\'thumbnailattachments\'] .= "' . fetch_template('postbit_attachmentthumbnail') . '";');
								$show['thumbnailattachment'] = true;
							}
							else if ($attachment['forceimage'])
							{
								eval('$post[\'imageattachments\'] .= "' . fetch_template('postbit_attachmentimage') . '";');
								$show['imageattachment'] = true;
							}
							else
							{
								eval('$post[\'imageattachmentlinks\'] .= "' . fetch_template('postbit_attachment') . '";');
								$show['imageattachmentlink'] = true;
							}
						}
						else if ($vboptions['viewattachedimages'] == 2 OR ($vboptions['viewattachedimages'] == 1 AND $attachcount == 1))
						{
							eval('$post[\'imageattachments\'] .= "' . fetch_template('postbit_attachmentimage') . '";');
							$show['imageattachment'] = true;
						}
						else
						{
							eval('$post[\'imageattachmentlinks\'] .= "' . fetch_template('postbit_attachment') . '";');
							$show['imageattachmentlink'] = true;
						}
						break;
					default:
						eval('$post[\'otherattachments\'] .= "' . fetch_template('postbit_attachment') . '";');
						$show['otherattachment'] = true;
				}
			}
			else
			{
				eval('$post[\'moderatedattachments\'] .= "' . fetch_template('postbit_attachmentmoderated') . '";');
				$show['moderatedattachment'] = true;
			}
		}
		if (!$vboptions['attachthumbs'] AND !$vboptions['viewattachedimages'])
		{
			$bbuserinfo['showimages'] = $showimagesprev;
		}
	}
	else
	{
		$show['attachments'] = false;
	}

	// get edited by
	if ($post['edit_userid'])
	{
		$post['edit_date'] = vbdate($vboptions['dateformat'], $post['edit_dateline'], true);
		$post['edit_time'] = vbdate($vboptions['timeformat'], $post['edit_dateline']);
		$show['postedited'] = true;
	}
	else
	{
		$show['postedited'] = false;
	}

	// get new/old post statusicon
	if ($post['dateline'] > $bbuserinfo['lastvisit'])
	{
		$post['statusicon'] = 'new';
		$post['statustitle'] = $vbphrase['unread_date'];
	}
	else
	{
		$post['statusicon'] = 'old';
		$post['statustitle'] = $vbphrase['old'];
	}

	// show default icon
	if ((!$forum['allowicons'] OR $post['iconid'] == 0) AND THIS_SCRIPT != 'announcement')
	{
		if (!empty($vboptions['showdeficon']))
		{
			$post['iconpath'] = $vboptions['showdeficon'];
			$post['icontitle'] = $vbphrase['default'];
		}
	}

	// *******************************************************
	// not posted by an unregistered user so get profile stuff
	if ($post['userid'])
	{
		// get rank
		if (!$gotrank[$post['userid']])
		{
			eval($datastore['rankphp']);
			$gotrank["$post[userid]"] = $post['rank'];
		}
		else
		{
			$post['rank'] = $gotrank["$post[userid]"];
		}

		// get online status
		fetch_online_status($post, true);

		// get avatar
		if ($post['avatarid'])
		{
			$avatarurl = $post['avatarpath'];
		}
		else
		{
			if ($post['hascustomavatar'] AND $vboptions['avatarenabled'])
			{
				if ($vboptions['usefileavatar'])
				{
					$avatarurl = "$vboptions[avatarurl]/avatar$post[userid]_$post[avatarrevision].gif";
				}
				else
				{
					$avatarurl = "image.php?$session[sessionurl]u=$post[userid]&amp;dateline=$post[avatardateline]";
				}
			}
			else
			{
				$avatarurl = '';
			}
		}
		if (empty($avatarurl) OR ($bbuserinfo['userid'] > 0 AND !($bbuserinfo['showavatars'])))
		{
			$show['avatar'] = false;
		}
		else
		{
			$show['avatar'] = true;
		}

		if (empty($checkperms["$post[userid]"]))
		{
			$checkperms["$post[userid]"] = cache_permissions($post, false);
		}

		// Generate Reputation Power
		if ($vboptions['postelements'] & POST_SHOWPOWER AND $vboptions['reputationenable'])
		{
			if (!empty($postelement['reppower']["$post[userid]"]))
			{
				$post['reppower'] = $postelement['reppower']["$post[userid]"];
			}
			else
			{
				$post['reppower'] = fetch_reppower($post, $checkperms["$post[userid]"]);
				$postelement['reppower']["$post[userid]"] = $post['reppower'];
			}
			$show['reppower'] = true;
		}
		else
		{
			$show['reppower'] = false;
		}

		// get reputation
		if ($vboptions['reputationenable'])
		{
			fetch_reputation_image($post, $checkperms["$post[userid]"]);
			$show['reputation'] = true;
		}
		else
		{
			$show['reputation'] = false;
		}

		// get custom title
		if ($post['customtitle'] == 2)
		{ // user title is not set by admin staff, so parse it.
			$post['usertitle'] = htmlspecialchars_uni($post['usertitle']);
		}

		// get join date & posts per day
		$jointime = (TIMENOW - $post['joindate']) / 86400; // Days Joined
		if ($jointime < 1)
		{ // User has been a member for less than one day.
			$postsperday = $post['posts'];
		}
		else
		{
			$postsperday = vb_number_format($post['posts'] / $jointime, 2);
		}
		$post['joindate'] = vbdate($vboptions['registereddateformat'], $post['joindate']);

		// format posts number
		$post['posts'] = vb_number_format($post['posts']);

		// assign $userinfo from $post
		$userinfo = &$post;

		$show['profile'] = true;
		$show['search'] = true;
		$show['buddy'] = true;
		$show['emaillink'] = iif ($post['showemail'] AND $vboptions['displayemails'] AND (!$vboptions['secureemail'] OR ($vboptions['secureemail'] AND $vboptions['enableemail'])), true, false);
		$show['homepage'] = iif ($post['homepage'] != '' AND $post['homepage'] != 'http://', true, false);
		$show['pmlink'] = iif ($post['receivepm'] AND $vboptions['enablepms'], true, false);

		// IM icons
		construct_im_icons($post);

		// Generate Age
		if ($vboptions['postelements'] & POST_SHOWAGE)
		{
			if (!$year)
			{
				$year = vbdate('Y', TIMENOW, false, false);
				$month = vbdate('n', TIMENOW, false, false);
				$day = vbdate('j', TIMENOW, false, false);
			}
			if (empty($postelement['age']["$post[userid]"]))
			{
				$date = explode('-', $post['birthday']);
				if ($year > $date[2] AND $date[2] != '0000')
				{
					$post['age'] = $year - $date[2];
					if ($month < $date[0] OR ($month == $date[0] AND $day < $date[1]))
					{
						$post['age']--;
					}
					if ($post['age'] < 101)
					{
						$postelement['age']["$post[userid]"] = $post['age'];
					}
					else
					{
						unset($post['age']);
					}
				}
			}
			else
			{
				$post['age'] = $postelement['age']["$post[userid]"];
			}
		}

		// get signature
		if ($post['showsignature'] AND $vboptions['allowsignatures'] AND trim($post['signature']) != '' AND (!$bbuserinfo['userid'] OR $bbuserinfo['showsignatures']) AND $checkperms["$post[userid]"]['genericpermissions'] & CANUSESIGNATURE)
		{
			if (!isset($sigcache["$post[userid]"]))
			{
				$parsed_postcache['skip'] = true;
				$post['signature'] = parse_bbcode($post['signature'], 'nonforum', $vboptions['allowsmilies']);
				$sigcache["$post[userid]"] = $post['signature'];
			}
			else
			{
				$post['signature'] = $sigcache["$post[userid]"];
			}
		}
		else
		{
			$post['signature'] = '';
		}
	}
	else // posted by a guest - set defaults for profile stuff
	{
		$post['rank'] = '';
		$postsperday = 0;
		$post['displaygroupid'] = 1;
		$post['musername'] = $post['username'] = $post['postusername'];
		$post['musername'] = fetch_musername($post, 'displaygroupid', 'musername');
		$post['usertitle'] = $vbphrase['guest'];
		$post['joindate'] = '';
		$post['posts'] = 'n/a';
		$post['avatar'] = '';
		$post['profile'] = '';
		$post['email'] = '';
		$post['useremail'] = '';
		$post['icqicon'] = '';
		$post['aimicon'] = '';
		$post['yahooicon'] = '';
		$post['msnicon'] = '';
		$post['homepage'] = '';
		$post['findposts'] = '';
		$post['signature'] = '';
		$post['reputationdisplay'] = '';
		$onlinestatus = '';
		$onlineresult = 0;
		$show['avatar'] = false;
		$show['reputation'] = false;
		$show['pmlink'] = false;
		$show['homepage'] = false;
		$show['emaillink'] = false;
		$show['profile'] = false;
		$show['search'] = false;
		$show['buddy'] = false;
	}

	// do ip addresses
	$post['iplogged'] = '';
	if ($post['ip'] != '')
	{
		if ($vboptions['logip'] == 2)
		{
			$show['ip'] = true;
			eval('$post[\'iplogged\'] .= "' . fetch_template('postbit_ip') . '";');
		}
		else if ($vboptions['logip'] == 1 AND can_moderate($thread['forumid'], 'canviewips'))
		{
			$show['ip'] = false;
			eval('$post[\'iplogged\'] .= "' . fetch_template('postbit_ip') . '";');
		}
	}

	// do alternate postbit types
	switch($alternate)
	{
		// usernote style postbit
		case 'usernote':
			$post['message'] = parse_usernote_bbcode($post['pagetext'], $post['allowsmilies']);

			$post['editlink'] = "usernote.php?$session[sessionurl]do=editnote&usernoteid=$post[usernoteid]";
			$post['replylink'] = false;
			$post['forwardlink'] = false;
			$show['postcount'] = false;
			$show['reputationlink'] = false;
			$show['reportlink'] = false;
			break;

		// announcement style postbit
		case 'announcement':
			$post['message'] = parse_bbcode($post['pagetext'], 'announcement', $post['allowsmilies']);
			$post['editlink'] = false;
			$post['replylink'] = false;
			$post['forwardlink'] = false;
			$show['postcount'] = false;
			$show['reputationlink'] = false;
			$show['reportlink'] = false;
			break;

		// private message style postbit
		case 'pm':
			$privatemessage = true;

			$post['editlink'] = false;
			$post['replylink'] = "private.php?$session[sessionurl]do=newpm&amp;pmid=$post[pmid]";
			$post['forwardlink'] = "private.php?$session[sessionurl]do=newpm&amp;forward=1&amp;pmid=$post[pmid]";
			$show['postcount'] = false;
			$show['reputationlink'] = false;
			$show['reportlink'] = false;
			break;

		// showthread / showpost style Postbit
		default:
			if (!empty($post['pagetext_html']))
			{
				$parsed_postcache['skip'] = true;
				if ($post['hasimages'])
				{
					$post['message'] = handle_bbcode_img($post['pagetext_html'], $forum['allowimages']);
				}
				else
				{
					$post['message'] = &$post['pagetext_html'];
				}
			}
			else
			{
				$parsed_postcache['skip'] = false;
				$post['message'] = parse_bbcode($post['pagetext'], $forum['forumid'], $post['allowsmilie']);
			}

			// highlight words from search engine ($_REQUEST[highlight])
			// Highlight word in all posts even if we link to one post since if we come from "Last Page" in thread search results, we don't only care about the last post!
			if (is_array($replacewords)) // AND ($_REQUEST['postid'] == $post['postid'] OR empty($_REQUEST['postid'])) )
			{
				$post['message'] = preg_replace('#(^|>)([^<]+)(?=<|$)#sUe', "process_highlight_postbit('\\2', \$replacewords, '\\1')", $post['message']);
				$post['message'] = preg_replace('#<vb_highlight>(.*)</vb_highlight>#siU', '<span class="highlight">$1</span>', $post['message']);
			}

			// hide edit button if they can't use it
			$forumperms = fetch_permissions($thread['forumid']);
			if (
				!$post['isdeleted'] AND !$thread['isdeleted'] AND (
				can_moderate($thread['forumid'], 'caneditposts') OR
				can_moderate($thread['forumid'], 'candeleteposts') OR
				(
					$thread['open'] AND
					$post['userid'] == $bbuserinfo['userid'] AND
					($forumperms & CANEDITPOST) AND
					(	$post['dateline'] >= (TIMENOW - ($vboptions['edittimelimit'] * 60)) OR
						$vboptions['edittimelimit'] == 0
					)
				))
			)
			{
				// can edit or delete this post, so show the link
				$post['editlink'] = "editpost.php?$session[sessionurl]do=editpost&amp;p=$post[postid]";
			}
			else
			{
				$post['editlink'] = false;
			}

			if (!$post['isdeleted'] AND !$thread['isdeleted'] AND $forum['allowposting'])
			{
				$post['replylink'] = "newreply.php?$session[sessionurl]do=newreply&amp;p=$post[postid]";
			}
			else
			{
				$post['replylink'] = false;
			}
			$post['forwardlink'] = false;
			$show['reportlink'] = iif($bbuserinfo['userid'] AND $bbuserinfo['userid'] != $post['userid'], true, false);
			$show['postcount'] = iif($post['postcount'], true, false);
			$show['reputationlink'] = iif(($permissions['genericpermissions'] & CANUSEREP OR $post['userid'] == $bbuserinfo['userid']) AND $vboptions['reputationenable'] AND $bbuserinfo['userid'] AND $post['userid'] AND !($usergroupcache["$post[usergroupid]"]['genericoptions'] & ISBANNEDGROUP), true, false);
			break;
	}

	// do posts from ignored users
	if ($tachyuser AND THIS_SCRIPT != 'showpost' AND THIS_SCRIPT != 'private')
	{
		$maintemplatename = 'postbit_ignore_global';
	}
	else if ($ignore["$post[userid]"]/* AND !in_array($post['userid'], explode(' ', $bbuserinfo['buddylist']))*/)
	{
		$maintemplatename = 'postbit_ignore';
		$show['showpostlink'] = ($alternate != 'usernote');
	}

	$show['messageicon'] = iif($post['iconpath'], true, false);

	eval('$retval = "' . fetch_template($maintemplatename) . '";');
	return $retval;
}

// ###################### Start process_highlight_postbit #######################
function process_highlight_postbit($text, $words, $prepend)
{
	$text = str_replace('\"', '"', $text);
	foreach ($words AS $replaceword)
	{
		$text = preg_replace('#(?<=[\s"\]>()]|^)(' . $replaceword . ')(([.,:;-?!()\s"<\[]|$))#siU', '<vb_highlight>\\1</vb_highlight>\\2', $text);
		//$text = preg_replace('#(?<=[^\w=])(' . $replaceword . ')(?=[^\w=])#siU', '<span class="highlight">\\1</span>', $text);
	}

	return "$prepend$text";
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14571 $
|| ####################################################################
\*======================================================================*/
?>