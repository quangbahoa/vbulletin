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

/**
* Constructs the posticons selector interface
*
* @param	integer	Selected Icon ID
* @param	boolean	Allow icons?
*
* @return	string	posticons template
*/
function construct_icons($seliconid = 0, $allowicons = true)
{
	// returns the icons chooser for posting new messages
	global $vbulletin, $stylevar;
	global $vbphrase, $selectedicon, $show;

	$selectedicon = array('src' => $vbulletin->options['cleargifurl'], 'alt' => '');

	if (!$allowicons)
	{
		return false;
	}

	$membergroups = fetch_membergroupids_array($vbulletin->userinfo);
	$infractiongroups = explode(',', str_replace(' ', '', $vbulletin->userinfo['infractiongroupids']));

	($hook = vBulletinHook::fetch_hook('posticons_start')) ? eval($hook) : false;

	$avperms = $vbulletin->db->query_read_slave("
		SELECT imagecategorypermission.imagecategoryid, usergroupid
		FROM " . TABLE_PREFIX . "imagecategorypermission AS imagecategorypermission, " . TABLE_PREFIX . "imagecategory AS imagecategory
		WHERE imagetype = 2
			AND imagecategorypermission.imagecategoryid = imagecategory.imagecategoryid
		ORDER BY imagecategory.displayorder
	");
	$noperms = array();
	while ($avperm = $vbulletin->db->fetch_array($avperms))
	{
		$noperms["$avperm[imagecategoryid]"][] = $avperm['usergroupid'];
	}
	foreach($noperms AS $imagecategoryid => $usergroups)
	{
		foreach($usergroups AS $usergroupid)
		{
			if (in_array($usergroupid, $infractiongroups))
			{
				$badcategories .= ",$imagecategoryid";
			}
		}
		if (!count(array_diff($membergroups, $usergroups)))
		{
			$badcategories .= ",$imagecategoryid";
		}
	}

	$icons = $vbulletin->db->query_read_slave("
		SELECT iconid, iconpath, title
		FROM " . TABLE_PREFIX . "icon AS icon
		WHERE imagecategoryid NOT IN (0$badcategories)
		ORDER BY imagecategoryid, displayorder
	");

	if (!$vbulletin->db->num_rows($icons))
	{
		return false;
	}

	$numicons = 0;
	$show['posticons'] = false;

	while ($icon = $vbulletin->db->fetch_array($icons))
	{
		$show['posticons'] = true;
		if ($numicons % 7 == 0 AND $numicons != 0)
		{
			$posticonbits .= "</tr><tr><td>&nbsp;</td>";
		}

		$numicons++;

		$iconid = $icon['iconid'];
		$iconpath = $icon['iconpath'];
		$alttext = $icon['title'];
		if ($seliconid == $iconid)
		{
			$iconchecked = 'checked="checked"';
			$selectedicon = array('src' => $iconpath, 'alt' => $alttext);
		}
		else
		{
			$iconchecked = '';
		}

		($hook = vBulletinHook::fetch_hook('posticons_bit')) ? eval($hook) : false;

		eval('$posticonbits .= "' . fetch_template('posticonbit') . '";');

	}

	$remainder = $numicons % 7;

	if ($remainder)
	{
		$remainingspan = 2 * (7 - $remainder);
		$show['addedspan'] = true;
	}
	else
	{
		$remainingspan = 0;
		$show['addedspan'] = false;
	}

	if ($seliconid == 0)
	{
		$iconchecked = 'checked="checked"';
	}
	else
	{
		$iconchecked = '';
	}

	($hook = vBulletinHook::fetch_hook('posticons_complete')) ? eval($hook) : false;

	eval('$posticons = "' . fetch_template('posticons') . '";');

	return $posticons;

}

/**
* Converts the newpost_attachmentbit template for use with javascript/construct_phrase
*
* @return	string
*/
function prepare_newpost_attachmentbit()
{
	// do not globalize $session or $attach!

	$attach = array(
		'imgpath' => '%1$s',
		'attachmentid' => '%3$s',
		'dateline' => '%4$s',
		'filename' => '%5$s',
		'filesize' => '%6$s'
	);
	$session['sessionurl'] = '%2$s';

	eval('$template = "' . fetch_template('newpost_attachmentbit') . '";');

	return addslashes_js($template, "'");
}

/**
* Converts URLs in text to bbcode links
*
* @param	string	message text
*
* @return	string
*/
function convert_url_to_bbcode($messagetext)
{
	// attempts to discover what areas we should auto-parse in
	$skiptaglist = 'url|email|code|php|html';

	($hook = vBulletinHook::fetch_hook('url_to_bbcode')) ? eval($hook) : false;

	return preg_replace(
		'#(^|\[/(' . $skiptaglist . ')\])(.*(\[(' . $skiptaglist . ')|$))#siUe',
		"convert_url_to_bbcode_callback('\\3', '\\1')",
		$messagetext
	);
}

/**
* Callback function for convert_url_to_bbcode
*
* @param	string	Message text
* @param	string	Text to prepend
*
* @return	string
*/
function convert_url_to_bbcode_callback($messagetext, $prepend)
{
	// the auto parser - adds [url] tags around neccessary things
	$messagetext = str_replace('\"', '"', $messagetext);
	$prepend = str_replace('\"', '"', $prepend);

	static $urlSearchArray, $urlReplaceArray, $emailSearchArray, $emailReplaceArray;
	if (empty($urlSearchArray))
	{
		$taglist = '\[b|\[i|\[u|\[left|\[center|\[right|\[indent|\[quote|\[highlight|\[\*' .
			'|\[/b|\[/i|\[/u|\[/left|\[/center|\[/right|\[/indent|\[/quote|\[/highlight';
		$urlSearchArray = array(
			"#(^|(?<=[^_a-z0-9-=\]\"'/@]|(?<=" . $taglist . ")\]))((https?|ftp|gopher|news|telnet)://|www\.)((\[(?!/)|[^\s[^$`\"{}<>])+)(?!\[/url|\[/img)(?=[,.!')]*(\)\s|\)$|[\s[]|$))#siU"
		);

		$urlReplaceArray = array(
			"[url]\\2\\4[/url]"
		);

		$emailSearchArray = array(
			"/([ \n\r\t])([_a-z0-9-]+(\.[_a-z0-9-]+)*@[^\s]+(\.[a-z0-9-]+)*(\.[a-z]{2,4}))/si",
			"/^([_a-z0-9-]+(\.[_a-z0-9-]+)*@[^\s]+(\.[a-z0-9-]+)*(\.[a-z]{2,4}))/si"
		);

		$emailReplaceArray = array(
			"\\1[email]\\2[/email]",
			"[email]\\0[/email]"
		);
	}

	$text = preg_replace($urlSearchArray, $urlReplaceArray, $messagetext);
	if (strpos($text, "@"))
	{
		$text = preg_replace($emailSearchArray, $emailReplaceArray, $text);
	}

	return $prepend . $text;
}

// ###################### Start newpost #######################
function build_new_post($type = 'thread', $foruminfo, $threadinfo, $postinfo, &$post, &$errors)
{
	//NOTE: permissions are not checked in this function

	// $post is passed by reference, so that any changes (wordwrap, censor, etc) here are reflected on the copy outside the function
	// $post[] includes:
	// title, iconid, message, parseurl, email, signature, preview, disablesmilies, rating
	// $errors will become any error messages that come from the checks before preview kicks in
	global $vbulletin, $vbphrase, $forumperms;

	// ### PREPARE OPTIONS AND CHECK VALID INPUT ###
	$post['disablesmilies'] = intval($post['disablesmilies']);
	$post['enablesmilies'] = iif($post['disablesmilies'], 0, 1);
	$post['folderid'] = intval($post['folderid']);
	$post['emailupdate'] = intval($post['emailupdate']);
	$post['rating'] = intval($post['rating']);
	$post['podcastsize'] = intval($post['podcastsize']);
	/*$post['parseurl'] = intval($post['parseurl']);
	$post['email'] = intval($post['email']);
	$post['signature'] = intval($post['signature']);
	$post['preview'] = iif($post['preview'], 1, 0);
	$post['iconid'] = intval($post['iconid']);
	$post['message'] = trim($post['message']);
	$post['title'] = trim(preg_replace('/&#0*32;/', ' ', $post['title']));
	$post['username'] = trim($post['username']);
	$post['posthash'] = trim($post['posthash']);
	$post['poststarttime'] = trim($post['poststarttime']);*/

	// Make sure the posthash is valid
	if (md5($post['poststarttime'] . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']) != $post['posthash'])
	{
		$post['posthash'] = 'invalid posthash'; // don't phrase me
	}

	// OTHER SANITY CHECKS
	$threadinfo['threadid'] = intval($threadinfo['threadid']);

	// create data manager
	if ($type == 'thread')
	{
		$dataman =& datamanager_init('Thread_FirstPost', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	}
	else
	{
		$dataman =& datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	}

	// set info
	$dataman->set_info('preview', $post['preview']);
	$dataman->set_info('parseurl', $post['parseurl']);
	$dataman->set_info('posthash', $post['posthash']);
	$dataman->set_info('forum', $foruminfo);
	$dataman->set_info('thread', $threadinfo);
	if (!$vbulletin->GPC['fromquickreply'])
	{
		$dataman->set_info('show_title_error', true);
	}
	if ($foruminfo['podcast'] AND (!empty($post['podcasturl']) OR !empty($post['podcastexplicit']) OR !empty($post['podcastauthor']) OR !empty($post['podcastsubtitle']) OR !empty($post['podcastkeywords'])))
	{
		$dataman->set_info('podcastexplicit', $post['podcastexplicit']);
		$dataman->set_info('podcastauthor', $post['podcastauthor']);
		$dataman->set_info('podcastkeywords', $post['podcastkeywords']);
		$dataman->set_info('podcastsubtitle', $post['podcastsubtitle']);
		$dataman->set_info('podcasturl', $post['podcasturl']);
		if ($post['podcastsize'])
		{
			$dataman->set_info('podcastsize', $post['podcastsize']);
		}
	}

	// set options
	$dataman->setr('showsignature', $post['signature']);
	$dataman->setr('allowsmilie', $post['enablesmilies']);

	// set data
	$dataman->setr('userid', $vbulletin->userinfo['userid']);
	if ($vbulletin->userinfo['userid'] == 0)
	{
		$dataman->setr('username', $post['username']);
	}
	$dataman->setr('title', $post['title']);
	$dataman->setr('pagetext', $post['message']);
	$dataman->setr('iconid', $post['iconid']);

	// see if post has to be moderated or if poster in a mod
	if (
		((
			(
				($foruminfo['moderatenewthread'] AND $type == 'thread') OR ($foruminfo['moderatenewpost'] AND $type == 'reply')
			)
			OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['followforummoderation'])
		)
		AND !can_moderate($foruminfo['forumid']))
		OR
		($type == 'reply' AND (($postinfo['postid'] AND !$postinfo['visible']) OR !$threadinfo['visible']))
	)
	{
		$dataman->set('visible', 0);
		$post['visible'] = 0;
	}
	else
	{
		$dataman->set('visible', 1);
		$post['visible'] = 1;
	}

	if ($type != 'thread')
	{
		if ($postinfo['postid'] == 0)
		{
			// get parentid of the new post
			// we're not posting a new thread, so make this post a child of the first post in the thread
			$getfirstpost = $vbulletin->db->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE threadid=$threadinfo[threadid] ORDER BY dateline LIMIT 1");
			$parentid = $getfirstpost['postid'];
		}
		else
		{
			$parentid = $postinfo['postid'];
		}
		$dataman->setr('parentid', $parentid);
		$dataman->setr('threadid', $threadinfo['threadid']);
	}
	else
	{
		$dataman->setr('forumid', $foruminfo['forumid']);
	}

	// done!
	($hook = vBulletinHook::fetch_hook('newpost_process')) ? eval($hook) : false;

	if ($vbulletin->GPC['fromquickreply'] AND $post['preview'])
	{
		$errors = array();
	}
	else
	{
		$dataman->pre_save();
		if ($dataman->errors)
		{
			$errors = $dataman->errors;
		}
		if ($dataman->info['podcastsize'])
		{
			$post['podcastsize'] = $dataman->info['podcastsize'];
		}
	}

	if ($vbulletin->options['postimagecheck'] AND !$post['preview'] AND !$vbulletin->userinfo['userid'] AND $vbulletin->options['regimagetype'])
	{
		require_once(DIR . '/includes/functions_regimage.php');
		if (!verify_regimage_hash($post['imagehash'], $post['imagestamp']))
		{
	  		$errors[] = fetch_error('register_imagecheck');
	  	}
	}

	if ($post['preview'] OR sizeof($errors) > 0)
	{
		// preview or errors, so don't submit
		return;
	}

	// ### DUPE CHECK ###
	$dupehash = md5($foruminfo['forumid'] . $post['title'] . $post['message'] . $vbulletin->userinfo['userid'] . $type);
	$prevpostfound = false;
	$prevpostthreadid = 0;

	if ($prevpost = $vbulletin->db->query_first("
		SELECT posthash.threadid
		FROM " . TABLE_PREFIX . "posthash AS posthash
		WHERE posthash.userid = " . $vbulletin->userinfo['userid'] . " AND
			posthash.dupehash = '" . $vbulletin->db->escape_string($dupehash) . "' AND
			posthash.dateline > " . (TIMENOW - 300) . "
	"))
	{
		if (($type == 'thread' AND $prevpost['threadid'] == 0) OR ($type == 'reply' AND $prevpost['threadid'] == $threadinfo['threadid']))
		{
			$prevpostfound = true;
			$prevpostthreadid = $prevpost['threadid'];
		}
	}

	// Redirect user to forumdisplay since this is a duplicate post
	if ($prevpostfound)
	{
		if ($type == 'thread')
		{
			$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[forumid]";
			eval(print_standard_redirect('redirect_duplicatethread', true, true));
		}
		else
		{
			// with ajax quick reply we need to use the error system
			if ($vbulletin->GPC['ajax'])
			{
	  			$errors[] = fetch_error('duplicate_post');
				return;
			}
			else
			{
				$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$prevpostthreadid";
				eval(print_standard_redirect('redirect_duplicatepost', true, true));
			}
		}
	}

	$id = $dataman->save();
	if ($type == 'thread')
	{
		$post['threadid'] = $id;
		$threadinfo =& $dataman->thread;
		$post['postid'] = $dataman->fetch_field('firstpostid');
	}
	else
	{
		$post['postid'] = $id;
	}

	$set_open_status = false;
	$set_sticky_status = false;
	if ($vbulletin->GPC['openclose'] AND (($threadinfo['postuserid'] != 0 AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']) OR can_moderate($threadinfo['forumid'], 'canopenclose')))
	{
		$set_open_status = true;
	}
	if ($vbulletin->GPC['stickunstick'] AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$set_sticky_status = true;
	}

	if ($set_open_status OR $set_sticky_status)
	{
		$thread =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		if ($type == 'thread')
		{
			$thread->set_existing($dataman->thread);
			if ($set_open_status)
			{
				$post['postpoll'] = false;
			}
		}
		else
		{
			$thread->set_existing($threadinfo);
		}

		if ($set_open_status)
		{
			$thread->set('open', ($thread->fetch_field('open') == 1 ? 0 : 1));
		}
		if ($set_sticky_status)
		{
			$thread->set('sticky', ($thread->fetch_field('sticky') == 1 ? 0 : 1));
		}

		$thread->save();
	}

	// ### DO THREAD RATING ###
	build_thread_rating($post['rating'], $foruminfo, $threadinfo);

	// ### DO EMAIL NOTIFICATION ###
	if ($post['visible'] AND $type != 'thread' AND !in_coventry($vbulletin->userinfo['userid'], true)) // AND !$prevpostfound (removed as redundant - bug #22935)
	{
		exec_send_notification($threadinfo['threadid'], $vbulletin->userinfo['userid'], $post['postid']);
	}

	// ### DO THREAD SUBSCRIPTION ###
	if ($vbulletin->userinfo['userid'] != 0)
	{
		require_once(DIR . '/includes/functions_misc.php');
		$post['emailupdate'] = verify_subscription_choice($post['emailupdate'], $vbulletin->userinfo, 9999);

		($hook = vBulletinHook::fetch_hook('newpost_subscribe')) ? eval($hook) : false;

		if (!$threadinfo['issubscribed'] AND $post['emailupdate'] != 9999)
		{ // user is not subscribed to this thread so insert it
			/*insert query*/
			$vbulletin->db->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
					VALUES (" . $vbulletin->userinfo['userid'] . ", $threadinfo[threadid], $post[emailupdate], $post[folderid], 1)");
		}
		else
		{ // User is subscribed, see if they changed the settings for this thread
			if ($post['emailupdate'] == 9999)
			{	// Remove this subscription, user chose 'No Subscription'
				$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE threadid = $threadinfo[threadid] AND userid = " . $vbulletin->userinfo['userid']);
			}
			else if ($threadinfo['emailupdate'] != $post['emailupdate'] OR $threadinfo['folderid'] != $post['folderid'])
			{
				// User changed the settings so update the current record
				/*insert query*/
				$vbulletin->db->query_write("REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
					VALUES (" . $vbulletin->userinfo['userid'] . ", $threadinfo[threadid], $post[emailupdate], $post[folderid], 1)");
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('newpost_complete')) ? eval($hook) : false;

}

// ###################### Start ratethread #######################
function build_thread_rating($rating, $foruminfo, $threadinfo)
{
	// add thread rating into DB

	global $vbulletin;

	if ($rating >= 1 AND $rating <= 5 AND $foruminfo['allowratings'])
	{
		if ($vbulletin->userinfo['forumpermissions'][$foruminfo['forumid']] & $vbulletin->bf_ugp_forumpermissions['canthreadrate'])
		{ // see if voting allowed
			$vote = intval($rating);
			if ($ratingsel = $vbulletin->db->query_first("
				SELECT vote, threadrateid, threadid
				FROM " . TABLE_PREFIX . "threadrate
				WHERE userid = " . $vbulletin->userinfo['userid'] . " AND
				threadid = $threadinfo[threadid]
			"))
			{ // user has already voted
				if ($vbulletin->options['votechange'])
				{ // if allowed to change votes
					if ($vote != $ratingsel['vote'])
					{ // if vote is different to original
						$voteupdate = $vote - $ratingsel['vote'];

						$threadrate =& datamanager_init('ThreadRate', $vbulletin, ERRTYPE_SILENT);
						$threadrate->set_info('thread', $threadinfo);
						$threadrate->set_existing($ratingsel);
						$threadrate->set('vote', $vote);
						$threadrate->save();
					}
				}
			}
			else
			{	// insert new vote, post_save_each ++ the vote count of the thread
				$threadrate =& datamanager_init('ThreadRate', $vbulletin, ERRTYPE_SILENT);
				$threadrate->set_info('thread', $threadinfo);
				$threadrate->set('threadid', $threadinfo['threadid']);
				$threadrate->set('userid', $vbulletin->userinfo['userid']);
				$threadrate->set('vote', $vote);
				$threadrate->save();
			}
		}
	}
}

// ###################### Start processerrors #######################
function construct_errors($errors)
{
	global $vbulletin, $vbphrase, $stylevar, $show;

	$errorlist = '';
	foreach ($errors AS $key => $errormessage)
	{
		eval('$errorlist .= "' . fetch_template('newpost_errormessage') . '";');
	}
	$show['errors'] = true;
	eval('$errortable = "' . fetch_template('newpost_preview') . '";');

	return $errortable;
}

// ###################### Start processpreview #######################
function process_post_preview(&$newpost, $postuserid = 0, $attachmentinfo = NULL)
{
	global $vbphrase, $checked, $rate, $previewpost, $stylevar, $foruminfo, $vbulletin, $show;

	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
	if ($attachmentinfo)
	{
		$bbcode_parser->attachments =& $attachmentinfo;
	}

	$previewpost = 1;
	$bbcode_parser->unsetattach = true;
	$previewmessage = $bbcode_parser->parse($newpost['message'], $foruminfo['forumid'], iif($newpost['disablesmilies'], 0, 1));

	$post = array(
		'userid' => ($postuserid ? $postuserid : $vbulletin->userinfo['userid']),
	);

	if (!empty($attachmentinfo))
	{
		require_once(DIR . '/includes/class_postbit.php');
		$post['attachments'] =& $attachmentinfo;
		$postbit_factory =& new vB_Postbit_Factory();
		$postbit_factory->registry =& $vbulletin;
		$postbit_factory->forum =& $foruminfo;
		$postbit_obj =& $postbit_factory->fetch_postbit('post');
		$postbit_obj->post =& $post;
		$postbit_obj->process_attachments();
	}

	if ($post['userid'] != $vbulletin->userinfo['userid'])
	{
		$fetchsignature = $vbulletin->db->query_first("
			SELECT signature
			FROM " . TABLE_PREFIX . "usertextfield
			WHERE userid = $postuserid
		");
		$signature =& $fetchsignature['signature'];
	}
	else
	{
		$signature = $vbulletin->userinfo['signature'];
	}

	$show['signature'] = false;
	if ($newpost['signature'] AND trim($signature))
	{
		$userinfo = fetch_userinfo($post['userid'], 32);

		if ($post['userid'] != $vbulletin->userinfo['userid'])
		{
			cache_permissions($userinfo, false);
		}
		else
		{
			$userinfo['permissions'] =& $vbulletin->userinfo['permissions'];
		}

		if ($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusesignature'])
		{
			$bbcode_parser->set_parse_userinfo($userinfo);
			$post['signature'] = $bbcode_parser->parse($signature, 'signature');
			$bbcode_parser->set_parse_userinfo(array());
			$show['signature'] = true;
		}
	}

	if ($foruminfo['allowicons'] AND $newpost['iconid'])
	{
		if ($icon = $vbulletin->db->query_first_slave("
			SELECT title as title, iconpath
			FROM " . TABLE_PREFIX . "icon
			WHERE iconid = " . intval($newpost['iconid']) . "
		"))
		{
			$newpost['iconpath'] = $icon['iconpath'];
			$newpost['icontitle'] = $icon['title'];
		}
	}
	else if ($vbulletin->options['showdeficon'] != '')
	{
		$newpost['iconpath'] = $vbulletin->options['showdeficon'];
		$newpost['icontitle'] = $vbphrase['default'];
	}

	$show['messageicon'] = iif($newpost['iconpath'], true, false);
	$show['errors'] = false;

	($hook = vBulletinHook::fetch_hook('newpost_preview')) ? eval($hook) : false;

	if ($previewmessage != '')
	{
		eval('$postpreview = "' . fetch_template('newpost_preview')."\";");
	}
	else
	{
		$postpreview = '';
	}

	construct_checkboxes($newpost);

	if ($newpost['rating'])
	{
		$rate["$newpost[rating]"] = ' '.'selected="selected"';
	}

	return $postpreview;
}

/**
* Constructs checked code for checkboxes
*
* @param	array	Array of information to build the $checked variable on
* @param	array	Array of keys to include with the default keys
*/
function construct_checkboxes($post, $extra_keys = null)
{
	global $checked;
	$checked = array();

	// default array keys
	$default_keys = array('parseurl', 'disablesmilies', 'signature', 'postpoll', 'receipt', 'savecopy', 'stickunstick', 'openclose', 'sendanyway');

	if (is_array($extra_keys))
	{
		$default_keys = array_merge($default_keys, $extra_keys);
	}

	foreach ($default_keys AS $field_name)
	{
		$checked["$field_name"] = ($post["$field_name"] ? ' checked="checked"' : '');
	}
}

// ###################### Start stopshouting #######################
function fetch_no_shouting_text($text)
{
	// stops $text being all UPPER CASE
	global $vbulletin;

	// we only actually touch a-z with vbstrtolower()
	$effective_string = preg_replace('#[^a-z0-9\s]#i', '', $text);

	if ($vbulletin->options['stopshouting'] AND vbstrlen($effective_string) >= $vbulletin->options['stopshouting'] AND $effective_string == strtoupper($effective_string))
	{
		return vbucwords(vbstrtolower($text));
	}
	else
	{
		return $text;
	}
}

/**
* Capitalizes the first letter of each word, provided it is within a-z.
* Ignores locales.
*
* @param	string	Text to capitalize
*
* @return	string	Ucwords'd text
*/
function vbucwords($text)
{
	return preg_replace_callback(
		'#(^|\s)[a-z]#',
		create_function('$matches', 'return strtoupper($matches[0]);'),
		$text
	);
}

// ###################### Start sendnotification #######################
function exec_send_notification($threadid, $userid, $postid)
{
	// $threadid = threadid to send from;
	// $userid = userid of who made the post
	// $postid = only sent if post is moderated -- used to get username correctly

	global $vbulletin, $message, $postusername;

	if (!$vbulletin->options['enableemail'])
	{
		return;
	}

	$threadinfo = fetch_threadinfo($threadid);
	$foruminfo = fetch_foruminfo($threadinfo['forumid']);

	// get last reply time
	if ($postid)
	{
		$dateline = $vbulletin->db->query_first("
			SELECT dateline, pagetext
			FROM " . TABLE_PREFIX . "post
			WHERE postid = $postid
		");

		$pagetext_orig = $dateline['pagetext'];

		$lastposttime = $vbulletin->db->query_first("
			SELECT MAX(dateline) AS dateline
			FROM " . TABLE_PREFIX . "post AS post
			WHERE threadid = $threadid
				AND dateline < $dateline[dateline]
				AND visible = 1
		");
	}
	else
	{
		$lastposttime = $vbulletin->db->query_first("
			SELECT MAX(postid) AS postid, MAX(dateline) AS dateline
			FROM " . TABLE_PREFIX . "post AS post
			WHERE threadid = $threadid
				AND visible = 1
		");

		$pagetext = $vbulletin->db->query_first("
			SELECT pagetext
			FROM " . TABLE_PREFIX . "post
			WHERE postid = $lastposttime[postid]
		");
		$pagetext_orig = $pagetext['pagetext'];
		unset($pagetext);
	}

	$threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
	$foruminfo['title_clean'] = unhtmlspecialchars($foruminfo['title_clean']);

	$temp = $vbulletin->userinfo['username'];
	if ($postid)
	{
		$postinfo = fetch_postinfo($postid);
		$vbulletin->userinfo['username'] = unhtmlspecialchars($postinfo['username']);
	}
	else
	{
		$vbulletin->userinfo['username'] = unhtmlspecialchars(
			(!$vbulletin->userinfo['userid'] ? $postusername : $vbulletin->userinfo['username'])
		);
	}

	require_once(DIR . '/includes/class_bbcode_alt.php');
	$plaintext_parser =& new vB_BbCodeParser_PlainText($vbulletin, fetch_tag_list());
	$pagetext_cache = array(); // used to cache the results per languageid for speed

	$mod_emails = fetch_moderator_newpost_emails('newpostemail', $foruminfo['parentlist'], $language_info);

	($hook = vBulletinHook::fetch_hook('newpost_notification_start')) ? eval($hook) : false;

	$useremails = $vbulletin->db->query_read_slave("
		SELECT user.*, subscribethread.emailupdate, subscribethread.subscribethreadid
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (subscribethread.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		WHERE subscribethread.threadid = $threadid AND
			subscribethread.emailupdate IN (1, 4) AND
			subscribethread.canview = 1 AND
			" . ($userid ? "CONCAT(' ', IF(usertextfield.ignorelist IS NULL, '', usertextfield.ignorelist), ' ') NOT LIKE '% " . intval($userid) . " %' AND" : '') . "
			user.usergroupid <> 3 AND
			user.userid <> " . intval($userid) . " AND
			user.lastactivity >= " . intval($lastposttime['dateline']) . " AND
			(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
	");

	vbmail_start();

	$evalemail = array();
	while ($touser = $vbulletin->db->fetch_array($useremails))
	{
		if (!($vbulletin->usergroupcache["$touser[usergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
		{
			continue;
		}
		else if (in_array($touser['email'], $mod_emails))
		{
			// this user already received an email about this post via
			// a new post email for mods -- don't send another
			continue;
		}
		$touser['username'] = unhtmlspecialchars($touser['username']);
		$touser['languageid'] = iif($touser['languageid'] == 0, $vbulletin->options['languageid'], $touser['languageid']);
		$touser['auth'] = md5($touser['userid'] . $touser['subscribethreadid'] . $touser['salt'] . COOKIE_SALT);

		if (empty($evalemail))
		{
			$email_texts = $vbulletin->db->query_read_slave("
				SELECT text, languageid, fieldname
				FROM " . TABLE_PREFIX . "phrase
				WHERE fieldname IN ('emailsubject', 'emailbody') AND varname = 'notify'
			");

			while ($email_text = $vbulletin->db->fetch_array($email_texts))
			{
				$emails["$email_text[languageid]"]["$email_text[fieldname]"] = $email_text['text'];
			}

			require_once(DIR . '/includes/functions_misc.php');

			foreach ($emails AS $languageid => $email_text)
			{
				// lets cycle through our array of notify phrases
				$text_message = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailbody']), $emails['-1']['emailbody'], $email_text['emailbody'])));
				$text_message = replace_template_variables($text_message);
				$text_subject = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailsubject']), $emails['-1']['emailsubject'], $email_text['emailsubject'])));
				$text_subject = replace_template_variables($text_subject);

				$evalemail["$languageid"] = '
					$message = "' . $text_message . '";
					$subject = "' . $text_subject . '";
				';
			}
		}

		// parse the page text into plain text, taking selected language into account
		if (!isset($pagetext_cache["$touser[languageid]"]))
		{
			$plaintext_parser->set_parsing_language($touser['languageid']);
			$pagetext_cache["$touser[languageid]"] = $plaintext_parser->parse($pagetext_orig, $foruminfo['forumid']);
		}
		$pagetext = $pagetext_cache["$touser[languageid]"];

		($hook = vBulletinHook::fetch_hook('newpost_notification_message')) ? eval($hook) : false;

		eval(iif(empty($evalemail["$touser[languageid]"]), $evalemail["-1"], $evalemail["$touser[languageid]"]));

		if ($touser['emailupdate'] == 4 AND !empty($touser['icq']))
		{ // instant notification by ICQ
			$touser['email'] = $touser['icq'] . '@pager.icq.com';
		}

		vbmail($touser['email'], $subject, $message);
	}

	unset($plaintext_parser, $pagetext_cache);

	$vbulletin->userinfo['username'] = $temp;

	vbmail_end();
}

/**
* Fetches the email addresses of moderators to email when there is a new post
* or new thread in a forum.
*
* @param	string|array	A string or array of dbfields to check for email addresses; also doubles as mod perm names
* @param	string|array	A string (comma-delimited) or array of forum IDs to check
* @param	array			(By reference) An array of languageids associated with specific email addresses returned
*
* @return	array			Array of emails to mail
*/
function fetch_moderator_newpost_emails($fields, $forums, &$language_info)
{
	global $vbulletin;

	$language_info = array();

	if (!is_array($fields))
	{
		$fields = array($fields);
	}

	// figure out the fields to select and the permissions to check
	$field_names = '';
	$mod_perms = array();
	foreach ($fields AS $field)
	{
		if ($permfield = intval($vbulletin->bf_misc_moderatorpermissions["$field"]))
		{
			$mod_perms[] = "(moderator.permissions & $permfield)";
		}

		$field_names .= "$field, ' ',";
	}

	if (sizeof($fields) > 1)
	{
		// kill trailing comma
		$field_names = 'CONCAT(' . substr($field_names, 0, -1) . ')';
	}
	else
	{
		$field_names = reset($fields);
	}

	// figure out the forums worth checking
	if (is_array($forums))
	{
		$forums = implode(',', $forums);
	}
	if (!$forums)
	{
		return array();
	}

	// get a list of super mod groups
	$smod_groups = array();
	foreach ($vbulletin->usergroupcache AS $ugid => $groupinfo)
	{
		if ($groupinfo['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
		{
			// super mod group
			$smod_groups[] = $ugid;
		}
	}

	$newpostemail = '';

	$moderators = $vbulletin->db->query_read_slave("
		SELECT $field_names AS newpostemail
		FROM " . TABLE_PREFIX . "forum
		WHERE forumid IN (" . $vbulletin->db->escape_string($forums) . ")
	");
	while ($moderator = $vbulletin->db->fetch_array($moderators))
	{
		$newpostemail .= ' ' . trim($moderator['newpostemail']);
	}

	if ($mod_perms)
	{
		$mods = $vbulletin->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid
			FROM " . TABLE_PREFIX . "moderator AS moderator
			LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
			WHERE
				(
					(moderator.forumid IN (" . $vbulletin->db->escape_string($forums) . ") AND moderator.forumid <> -1)
					" . (!empty($smod_groups) ? "OR (user.usergroupid IN (" . implode(',', $smod_groups) . ") AND moderator.forumid = -1)" : '') . "
				)
				AND (" . implode(' OR ', $mod_perms) . ")
		");
		while ($mod = $vbulletin->db->fetch_array($mods))
		{
			$language_info["$mod[email]"] = $mod['languageid'];
			$newpostemail .= ' ' . $mod['email'];
		}
	}

	$emails = preg_split('#\s+#', trim($newpostemail), -1, PREG_SPLIT_NO_EMPTY);
	$emails = array_unique($emails);

	return $emails;
}

// ###################### Start fetch_quote_username #######################
// this deals with the problem of quoting usernames that contain square brackets.
// note the following:
//	alphanum + square brackets => WORKS
//	alphanum + square brackets + single quotes => WORKS
//	alphanum + square brackets + double quotes => WORKS
//	alphanum + square brackets + single quotes + double quotes => BREAKS (can't quote a string containing both types of quote)
function fetch_quote_username($username)
{
	$username = unhtmlspecialchars($username);

	if (strpos($username, '[') !== false OR strpos($username, ']') !== false)
	{
		if (strpos($username, "'") !== false)
		{
			return '"' . $username . '"';
		}
		else
		{
			return "'$username'";
		}
	}
	else
	{
		return $username;
	}
}

// ###################### Start fetch_quote_title #######################
// checks the parent post and thread for a title to fill the default title field
function fetch_quote_title($parentposttitle, $threadtitle)
{
	global $vbulletin, $vbphrase;

	if ($vbulletin->options['quotetitle'])
	{
		if ($parentposttitle != '')
		{
			$posttitle = $parentposttitle;
		}
		else
		{
			$posttitle = $threadtitle;
		}
		$posttitle = unhtmlspecialchars($posttitle);
		$posttitle = preg_replace('#^(' . preg_quote($vbphrase['reply_prefix'], '#') . '\s*)+#i', '', $posttitle);
		return "$vbphrase[reply_prefix] $posttitle";
	}
	else
	{
		return '';
	}
}

// ###################### Start fetch emailchecked #######################
// function to fetch the array containing the checked="checked" value for thread subscription
function fetch_emailchecked($threadinfo, $userinfo = false, $newpost = false)
{
	if (is_array($newpost) AND isset($newpost['emailupdate']))
	{
		$choice = $newpost['emailupdate'];
	}
	else
	{
		if ($threadinfo['issubscribed'])
		{
			$choice = $threadinfo['emailupdate'];
		}
		else if (is_array($userinfo) AND $userinfo['autosubscribe'] != -1)
		{
			$choice = $userinfo['autosubscribe'];
		}
		else
		{
			$choice = 9999;
		}
	}

	require_once(DIR . '/includes/functions_misc.php');
	$choice = verify_subscription_choice($choice, $userinfo, 9999, false);

	return array($choice => 'selected="selected"');
}

/**
* Fetches and prepares posts for quoting. Returned text is BB code.
*
* @param	array	Array of post IDs to pull from
* @param	integer	The ID of the thread that is being quoted into
* @param	integer	Returns the number of posts that were unquoted because of the value of the next argument
* @param	array	Returns the IDs of the posts that were actually quoted
* @param	string	Controls what posts are successfully quoted: all, only (only the thread ID), other (only other thread IDs)
* @param	boolean	Whether to undo the htmlspecialchars calls; useful when returning HTML to be entered via JS
*/
function fetch_quotable_posts($quote_postids, $threadid, &$unquoted_posts, &$quoted_post_ids, $limit_thread = 'only', $unhtmlspecialchars = false)
{
	global $vbulletin;

	$unquoted_posts = 0;
	$quoted_post_ids = array();

	$quote_postids = array_diff_assoc(array_unique(array_map('intval', $quote_postids)), array(0));

	// limit to X number of posts
	if ($vbulletin->options['mqlimit'] > 0)
	{
		$quote_postids = array_slice($quote_postids, 0, $vbulletin->options['mqlimit']);
	}

	if (empty($quote_postids))
	{
		// nothing to quote
		return '';
	}

	$quote_post_data = $vbulletin->db->query_read_slave("
		SELECT post.postid, post.title, post.pagetext, post.dateline, post.userid, post.visible AS postvisible,
			IF(user.username <> '', user.username, post.username) AS username,
			thread.threadid, thread.title AS threadtitle, thread.postuserid, thread.visible AS threadvisible,
			forum.forumid, forum.password
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (post.userid = user.userid)
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (thread.forumid = forum.forumid)
		WHERE post.postid IN (" . implode(',', $quote_postids) . ")
	");

	$quote_posts = array();
	while ($quote_post = $vbulletin->db->fetch_array($quote_post_data))
	{
		if (
			((!$quote_post['postvisible'] OR $quote_post['postvisible'] == 2) AND !can_moderate($quote_post['forumid'])) OR
			((!$quote_post['threadvisible'] OR $quote_post['threadvisible'] == 2) AND !can_moderate($quote_post['forumid']))
		)
		{
			// no permission to view this post
			continue;
		}

		$forumperms = fetch_permissions($quote_post['forumid']);
		if (
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])) OR
			(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($quote_post['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0)) OR
			!verify_forum_password($quote_post['forumid'], $quote_post['password'], false) OR
			(in_coventry($quote_post['postuserid']) AND !can_moderate($quote_post['forumid'])) OR
			(in_coventry($quote_post['userid']) AND !can_moderate($quote_post['forumid']))
		)
		{
			// no permission to view this post
			continue;
		}

		if (($limit_thread == 'only' AND $quote_post['threadid'] != $threadid) OR
			($limit_thread == 'other' AND $quote_post['threadid'] == $threadid) OR $limit_thread == 'all')
		{
			$unquoted_posts++;
			continue;
		}

		$quote_posts["$quote_post[postid]"] = $quote_post;
	}

	$message = '';
	foreach ($quote_postids AS $quote_postid)
	{
		if (!isset($quote_posts["$quote_postid"]))
		{
			continue;
		}
		$quote_post =& $quote_posts["$quote_postid"];

		$originalposter = fetch_quote_username($quote_post['username'] . ";$quote_post[postid]");
		$postdate = vbdate($vbulletin->options['dateformat'], $quote_post['dateline']);
		$posttime = vbdate($vbulletin->options['timeformat'], $quote_post['dateline']);
		$pagetext = htmlspecialchars_uni($quote_post['pagetext']);
		$pagetext = trim(strip_quotes($pagetext));

		($hook = vBulletinHook::fetch_hook('newreply_quote')) ? eval($hook) : false;
		eval('$message .= "' . fetch_template('newpost_quote', 0, false) . '\n";');

		$quoted_post_ids[] = $quote_postid;
	}

	if ($unhtmlspecialchars)
	{
		$message = unhtmlspecialchars($message);
	}

	return $message;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 26664 $
|| ####################################################################
\*======================================================================*/
?>
