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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'misc');
define('CSRF_PROTECTION', true);
if (in_array($_GET['do'], array('whoposted', 'buddylist', 'getsmilies')))
{
	define('NOPMPOPUP', 1);
}

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('fronthelp', 'register');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'buddylist' => array(
		'BUDDYLIST',
		'buddylistbit'
	),
	'whoposted' => array(
		'WHOPOSTED',
		'whopostedbit'
	),
	'showattachments' => array(
		'ATTACHMENTS',
		'attachmentbit',
	),
	'showavatars' => array(
		'help_avatars',
		'help_avatars_avatar',
		'help_avatars_category',
		'help_avatars_row',
	),
	'bbcode' => array(
		'help_bbcodes',
		'help_bbcodes_bbcode',
		'help_bbcodes_link',
		'bbcode_code',
		'bbcode_html',
		'bbcode_php',
		'bbcode_quote',
	),
	'getsmilies' => array(
		'smiliepopup',
		'smiliepopup_category',
		'smiliepopup_row',
		'smiliepopup_smilie',
		'smiliepopup_straggler'
	),
	'showsmilies' => array(
		'help_smilies',
		'help_smilies_smilie',
		'help_smilies_category',
	),
	'showrules' => array(
		'help_rules',
	)
);
$actiontemplates['none'] =& $actiontemplates['showsmilies'];

// allows proper template caching for the default action (showsmilies) if no valid action is specified
if (!empty($_REQUEST['do']) AND !isset($actiontemplates["$_REQUEST[do]"]))
{
	$actiontemplates["$_REQUEST[do]"] =& $actiontemplates['showsmilies'];
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// redirect in case anyone has linked to it
if ($_REQUEST['do'] == 'attachments')
{
	exec_header_redirect('profile.php?' . $vbulletin->session->vars['sessionurl_js'] . 'do=editattachments');
}

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

($hook = vBulletinHook::fetch_hook('misc_start')) ? eval($hook) : false;

// ############################### start buddylist ###############################
if ($_REQUEST['do'] == 'buddylist')
{
	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	($hook = vBulletinHook::fetch_hook('misc_buddylist_start')) ? eval($hook) : false;

	$buddies = $vbulletin->input->clean_gpc('r', 'buddies', TYPE_STR);

	$datecut = TIMENOW - $vbulletin->options['cookietimeout'];

	$buddys = $db->query_read_slave("
		SELECT
		user.username, (user.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ") AS invisible, user.userid, session.lastactivity
		FROM " . TABLE_PREFIX . "userlist AS userlist
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = userlist.relationid)
		LEFT JOIN " . TABLE_PREFIX . "session AS session ON(session.userid = user.userid)
		WHERE userlist.userid = {$vbulletin->userinfo['userid']} AND userlist.relationid = user.userid AND type = 'buddy'
		ORDER BY username ASC, session.lastactivity DESC
	");

	$onlineusers = '';
	$offlineusers = '';
	$newusersound = '';
	$lastonline = array();

	if (isset($buddies))
	{
		$buddies = urldecode($buddies);
		$lastonline = explode(' ', $buddies);
	}
	$buddies = '0 ';
	$show['playsound'] = false;

	require_once(DIR . '/includes/functions_bigthree.php');
	while ($buddy = $db->fetch_array($buddys))
	{
		if ($doneuser["$buddy[userid]"])
		{
			continue;
		}

		$doneuser["$buddy[userid]"] = true;

		if ($onlineresult = fetch_online_status($buddy))
		{
			if ($onlineresult == 1)
			{
				$buddy['statusicon'] = 'online';
			}
			else
			{
				$buddy['statusicon'] = 'invisible';
			}
			$buddies .= $buddy['userid'] . ' ';
		}
		else
		{
			$buddy['statusicon'] = 'offline';
		}

		$show['highlightuser'] = false;

		($hook = vBulletinHook::fetch_hook('misc_buddylist_bit')) ? eval($hook) : false;

		if ($buddy['statusicon'] != 'offline')
		{
			if (!in_array($buddy['userid'], $lastonline) AND !empty($lastonline))
			{
				$show['playsound'] = true;
				$show['highlightuser'] = true;
				// add name to top of list
				eval('$onlineusers = "' . fetch_template('buddylistbit') . '" . $onlineusers;');
			}
			else
			{
				eval('$onlineusers .= "' . fetch_template('buddylistbit') . '";');
			}
		}
		else
		{
			eval('$offlineusers .= "' . fetch_template('buddylistbit') . '";');
		}
	}

	$buddies = urlencode(trim($buddies));

	($hook = vBulletinHook::fetch_hook('misc_buddylist_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('BUDDYLIST') . '");');
}

// ############################### start who posted ###############################
if ($_REQUEST['do'] == 'whoposted')
{
	if (!$threadinfo['threadid'] OR $threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('misc_whoposted_start')) ? eval($hook) : false;

	$forumperms = fetch_permissions($threadinfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
	{
		print_no_permission();
	}
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR !$vbulletin->userinfo['userid']))
	{
		print_no_permission();
	}

	$posts = $db->query_read_slave("
		SELECT COUNT(postid) AS posts,
		post.username AS postuser,user.userid,user.username
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		WHERE threadid = $threadinfo[threadid]
			AND visible = 1
		GROUP BY userid
		ORDER BY posts DESC
	");

	$totalposts = 0;
	$posters = '';
	if ($db->num_rows($posts))
	{
		require_once(DIR . '/includes/functions_bigthree.php');
		while ($post = $db->fetch_array($posts))
		{
			// hide users in Coventry
			$ast = '';
			if (in_coventry($post['userid']) AND !can_moderate($threadinfo['forumid']))
			{
				continue;
			}

			exec_switch_bg();
			if ($post['username'] == '')
			{
				$post['username'] = $post['postuser'];
			}
			$post['username'] .=  $ast;
			$totalposts += $post['posts'];
			$post['posts'] = vb_number_format($post['posts']);
			$show['memberlink'] = iif ($post['userid'], true, false);
			eval('$posters .= "' . fetch_template('whopostedbit') . '";');
		}
		$totalposts = vb_number_format($totalposts);

		($hook = vBulletinHook::fetch_hook('misc_whoposted_complete')) ? eval($hook) : false;

		eval('print_output("' . fetch_template('WHOPOSTED') . '");');
	}
	else
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
	}
}

// ############################### start show attachments ###############################
if ($_REQUEST['do'] == 'showattachments')
{
	if (!$threadinfo['threadid'] OR $threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('misc_showattachments_start')) ? eval($hook) : false;

	$forumperms = fetch_permissions($threadinfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
	{
		print_no_permission();
	}
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR !$vbulletin->userinfo['userid']))
	{
		print_no_permission();
	}

	$attachs = $db->query_read_slave("
		SELECT attachment.*
		FROM " . TABLE_PREFIX . "post AS post
		INNER JOIN " . TABLE_PREFIX . "attachment AS attachment ON (attachment.postid=post.postid AND attachment.visible=1)
		WHERE threadid = $threadinfo[threadid]
			AND post.visible = 1
		ORDER BY filename DESC
	");

	if ($db->num_rows($attachs))
	{
		require_once(DIR . '/includes/functions_bigthree.php');
		while ($attachment = $db->fetch_array($attachs))
		{
			// hide users in Coventry
			$ast = '';
			if (in_coventry($attachment['userid']) AND !can_moderate($threadinfo['forumid']))
			{
				continue;
			}

			$attachment['filename'] = fetch_censored_text(htmlspecialchars_uni($attachment['filename']));
			$attachment['attachmentextension'] = strtolower(file_extension($attachment['filename']));
			$attachment['filesize'] = vb_number_format($attachment['filesize'], 1, true);

			exec_switch_bg();

			eval('$attachments .= "' . fetch_template('attachmentbit') . '";');
		}

		($hook = vBulletinHook::fetch_hook('misc_showattachments_complete')) ? eval($hook) : false;

		eval('print_output("' . fetch_template('ATTACHMENTS') . '");');
	}
	else
	{
		eval(standard_error(fetch_error('noattachments')));
	}
}

// ############################### start show avatars ###############################
if ($_REQUEST['do'] == 'showavatars')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber' => TYPE_UINT,
	));

	($hook = vBulletinHook::fetch_hook('misc_avatars_start')) ? eval($hook) : false;

	$perpage = $vbulletin->options['numavatarsperpage'];

	$totalavatars = $db->query_first_slave("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "avatar AS avatar
		LEFT JOIN " . TABLE_PREFIX . "imagecategorypermission AS perm ON (perm.imagecategoryid=avatar.imagecategoryid AND perm.usergroupid=" . $vbulletin->userinfo['usergroupid'] . ")
		WHERE ISNULL(perm.imagecategoryid)
	");
	$totalavatars = intval($totalavatars['count']);

	sanitize_pageresults($totalavatars, $vbulletin->GPC['pagenumber'], $perpage, 100, 25);
	$startat = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;

	$first = $startat + 1;
	$last = $startat + $perpage;
	if ($last > $totalavatars)
	{
		$last = $totalavatars;
	}

	$minposts = 0;
	$avatarbits = '';

	$avatars = $db->query_read_slave("
		SELECT avatar.title,minimumposts,avatarpath,imagecategory.title AS category
		FROM " . TABLE_PREFIX . "avatar AS avatar
		LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory ON (imagecategory.imagecategoryid=avatar.imagecategoryid)
		LEFT JOIN " . TABLE_PREFIX . "imagecategorypermission AS perm ON (perm.imagecategoryid=avatar.imagecategoryid AND perm.usergroupid=" . $vbulletin->userinfo['usergroupid'] . ")
		WHERE ISNULL(perm.imagecategoryid)
		ORDER BY minimumposts,imagecategory.displayorder,avatar.displayorder
		LIMIT $startat, $perpage
	");
	$avatarsonthispage = $db->num_rows($avatars);

	// check to see that there are some avatars to display
	if ($db->num_rows($avatars))
	{
		$pagenav = construct_page_nav($vbulletin->GPC['pagenumber'], $perpage, $totalavatars, 'misc.php?' . $vbulletin->session->vars['sessionurl'] . 'do=showavatars');

		exec_switch_bg();
		while ($avatar = $db->fetch_array($avatars))
		{
			// initialise the remaining columns number
			$remainingcolumns = 0;

			// display the category bar if required
			if ($avatar['category'] != $lastcat OR $avatar['minimumposts'] != $minposts)
			{
				// echo out any straggler avatars still waiting to be displayed
				$remaining = sizeof($bits);
				if ($remaining > 0)
				{
					$remainingcolumns = $vbulletin->options['numavatarswide'] - $remaining;
					$avatarcells = implode('', $bits);
					eval('$avatarbits .= "' . fetch_template('help_avatars_row') . '";');
					$bits = array();
				}
				// get the category bar
				eval('$avatarbits .= "' . fetch_template('help_avatars_category') . '";');

				$bgclass = 'alt1';
			}
			// make an array entry containing the current avatar
			eval('$bits[] = "' . fetch_template('help_avatars_avatar') . '";');

			// display a row of avatars if the counter is high enough
			if (sizeof($bits) == $vbulletin->options['numavatarswide'])
			{
				exec_switch_bg();
				$avatarcells = implode('', $bits);
				eval('$avatarbits .= "' . fetch_template('help_avatars_row') . '";');
				$bits = array();
			}

			// set the last category and last minposts
			$lastcat = $avatar['category'];
			$minposts = $avatar['minimumposts'];
		}

		// initialize the remaining columns number
		$remainingcolumns = 0;

		// echo out any straggler avatars still waiting to be displayed
		$remaining = sizeof($bits);
		if ($remaining > 0)
		{
			$remainingcolumns = $vbulletin->options['numavatarswide'] - $remaining;
			$avatarcells = implode('', $bits);
			eval('$avatarbits .= "' . fetch_template('help_avatars_row') . '";');
		}

	} // end if num_rows($avatars)

	$navbits = construct_navbits(array(
		'faq.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['faq'],
		'' => $vbphrase['avatar_list']
	));

	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('misc_avatars_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('help_avatars') . '");');

}

// ############################### start bbcode ###############################
if ($_REQUEST['do'] == 'bbcode')
{

	($hook = vBulletinHook::fetch_hook('misc_bbcode_start')) ? eval($hook) : false;
	require_once(DIR . '/includes/class_bbcode.php');

	$show['bbcodebasic'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_BASIC) ? true : false;
	$show['bbcodecolor'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_COLOR) ? true : false;
	$show['bbcodesize'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_SIZE) ? true : false;
	$show['bbcodefont'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_FONT) ? true : false;
	$show['bbcodealign'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_ALIGN) ? true : false;
	$show['bbcodelist'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_LIST) ? true : false;
	$show['bbcodeurl'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) ? true : false;
	$show['bbcodecode'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_CODE) ? true : false;
	$show['bbcodephp'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_PHP) ? true : false;
	$show['bbcodehtml'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_HTML) ? true : false;
	$show['bbcodesigpic'] = ($permissions['signaturepermissions'] & $vbulletin->bf_ugp_signaturepermissions['cansigpic']) ? true : false;

	$template['bbcodebits'] = '';

	$specialbbcode[] = array();

	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$bbcodes = $db->query_read_slave("SELECT * FROM " . TABLE_PREFIX . "bbcode ORDER BY bbcodetag, twoparams");
	while ($bbcode = $db->fetch_array($bbcodes))
	{
		$bbcode['output'] = $bbcode_parser->do_parse($bbcode['bbcodeexample'], false, false, true, false, true);

		$bbcode['bbcodeexample'] = htmlspecialchars_uni($bbcode['bbcodeexample']);
		if ($bbcode['twoparams'])
		{
			$bbcode['tag'] = '[' . $bbcode['bbcodetag'] . '=<span class="highlight">' . $vbphrase['option'] . '</span>]<span class="highlight">' . $vbphrase['value'] . '</span>[/' . $bbcode['bbcodetag'] . ']';
		}
		else
		{
			$bbcode['tag'] = '[' . $bbcode['bbcodetag'] . ']<span class="highlight">' . $vbphrase['value'] . '</span>[/' . $bbcode['bbcodetag'] . ']';
		}

		($hook = vBulletinHook::fetch_hook('misc_bbcode_bit')) ? eval($hook) : false;

		eval('$template[\'bbcodebits\'] .= "' . fetch_template('help_bbcodes_bbcode') . '";');
		eval('$template[\'bbcodelinks\'] .= "' . fetch_template('help_bbcodes_link') . '";');
	}

	$navbits = construct_navbits(array(
		'faq.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['faq'],
		'' => $vbphrase['bbcode_list']
	));

	$show['iewidthfix'] = (is_browser('ie') AND !(is_browser('ie', 6)));
	$stylevar['codeblockwidth'] = 'auto';

	$vbulletin->options['allowhtml'] = false;
	$vbulletin->options['allowbbcode'] = true;

	// ### CODE tag
	$specialbbcode['code'] = $bbcode_parser->parse("[code]<script type=\"text/javascript\">\n<!--\n\talert(\"Hello world!\");\n//-->\n</script>[/code]", 0, false);

	// ### HTML Tag
	$specialbbcode['html'] = $bbcode_parser->parse("[html]<img src=\"image.gif\" alt=\"image\" />\n<a href=\"testing.html\" target=\"_blank\">Testing</a>[/html]", 0, false);

	// ### PHP Tag
	$specialbbcode['php'] = $bbcode_parser->parse("[php]\$myvar = 'Hello World!';\nfor (\$i = 0; \$i < 10; \$i++)\n{\n\techo \$myvar . \"\\n\";\n}[/php]", 0, false);

	// ### Quote Tag
	$specialbbcode['quote1'] = $bbcode_parser->parse("[quote]Lorem ipsum dolor sit amet[/quote]", 0, false);
	$specialbbcode['quote2'] = $bbcode_parser->parse("[quote=John Doe]Lorem ipsum dolor sit amet[/quote]", 0, false);

	$max_post = $db->query_first_slave("SELECT MAX(postid) AS maxpostid FROM " . TABLE_PREFIX . "post");
	$max_post['maxpostid'] = intval($max_post['maxpostid']);
	$specialbbcode['quote3'] = $bbcode_parser->parse("[quote=John Doe;$max_post[maxpostid]]Lorem ipsum dolor sit amet[/quote]", 0, false);

	// ### Special URL for Image
	if (preg_match('#^[a-z0-9]+://#si', $stylevar['imgdir_statusicon']))
	{
		$statusicon_dir = $stylevar['imgdir_statusicon'];
	}
	else
	{
		$statusicon_dir = $vbulletin->options['bburl'] . '/' . $stylevar['imgdir_statusicon'];
	}

	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('misc_bbcode_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('help_bbcodes') . '");');
}

// ############################### Popup Smilies for vbCode ################
if ($_REQUEST['do'] == 'getsmilies')
{
	$editorid = $vbulletin->input->clean_gpc('r', 'editorid', TYPE_NOHTML);
	$editorid = preg_replace('#[^a-z0-9_]#i', '', $editorid);

	($hook = vBulletinHook::fetch_hook('misc_smiliespopup_start')) ? eval($hook) : false;

	$smilies = $db->query_read_slave("
		SELECT smilietext AS text, smiliepath AS path, smilie.title, smilieid,
			imagecategory.title AS category
		FROM " . TABLE_PREFIX . "smilie AS smilie
		LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
		ORDER BY imagecategory.displayorder, imagecategory.title, smilie.displayorder
	");

	$smcache = array();
	while ($smilie = $db->fetch_array($smilies))
	{
		$smcache["{$smilie['category']}"][] = $smilie;
	}

	$popup_smiliesbits = '';
	$bits = array();
	exec_switch_bg();
	foreach ($smcache AS $category => $smilies)
	{
		if (sizeof($bits) == 1)
		{
			eval('$smiliecells = "' . fetch_template('smiliepopup_straggler') . '";');
			eval('$smiliebits .= "' . fetch_template('smiliepopup_row') . '";');
		}

		($hook = vBulletinHook::fetch_hook('misc_smiliespopup_category')) ? eval($hook) : false;

		eval('$smiliebits .= "' . fetch_template('smiliepopup_category') . '";');
		$bits = array();
		foreach ($smilies AS $smilie)
		{
			($hook = vBulletinHook::fetch_hook('misc_smiliespopup_smilie')) ? eval($hook) : false;

			$smilie['js'] = addslashes_js($smilie['text']);
			$smiliehtml = "<img src=\"$smilie[path]\" id=\"smilie_$smilie[smilieid]\" alt=\"" . htmlspecialchars_uni($smilie['text']) . "\" title=\"" . htmlspecialchars_uni($smilie['title']) . "\" />";
			eval('$bits[] = "' . fetch_template('smiliepopup_smilie') . '";');
			if (sizeof($bits) == 2)
			{
				exec_switch_bg();
				$smiliecells = implode('', $bits);
				eval('$smiliebits .= "' . fetch_template('smiliepopup_row') . '";');
				$bits = array();
			}
		}
	}
	if (sizeof($bits) == 1)
	{
		eval('$smiliecells = "' . fetch_template('smiliepopup_straggler') . '";');
		eval('$smiliebits .= "' . fetch_template('smiliepopup_row') . '";');
	}

	($hook = vBulletinHook::fetch_hook('misc_smiliespopup_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('smiliepopup') . '");');

}

$vbulletin->input->clean_gpc('r', 'template', TYPE_NOHTML);

// ############################### start any page ###############################
if ($_REQUEST['do'] == 'debug_page' AND $vbulletin->GPC['template'] != '')
{
	if (!$vbulletin->debug)
	{
		print_no_permission();
	}

	$template_name = preg_replace('#[^a-z0-9_]#i', '', $vbulletin->GPC['template']);

	$navbits = construct_navbits(array('' => $template_name));
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template($template_name) . '");');
}

if ($_REQUEST['do'] == 'page' AND $vbulletin->GPC['template'] != '')
{
	$template_name = preg_replace('#[^a-z0-9_]#i', '', $vbulletin->GPC['template']);

	$navbits = construct_navbits(array('' => $template_name));
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template('custom_' . $template_name) . '");');
}

// ############################### start show rules ###############################
if ($_REQUEST['do'] == 'showrules')
{
	$navbits = construct_navbits(array(
		'' => $vbphrase['forum_rules']
	));

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template('help_rules') . '");');
}

$_REQUEST['do'] = 'showsmilies';

// ############################### start show smilies ###############################
if ($_REQUEST['do'] == 'showsmilies')
{
	$smiliebits = '';

	($hook = vBulletinHook::fetch_hook('misc_smilieslist_start')) ? eval($hook) : false;

	$smilies = $db->query_read_slave("
		SELECT smilietext,smiliepath,smilie.title,imagecategory.title AS category
		FROM " . TABLE_PREFIX . "smilie AS smilie
		LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
		ORDER BY imagecategory.displayorder, imagecategory.title, smilie.displayorder
	");

	while ($smilie = $db->fetch_array($smilies))
	{
		$smilie['title'] = htmlspecialchars_uni($smilie['title']);

		if ($smilie['category'] != $lastcat)
		{
			($hook = vBulletinHook::fetch_hook('misc_smilieslist_category')) ? eval($hook) : false;

			eval('$smiliebits .= "' . fetch_template('help_smilies_category') . '";');
		}
		exec_switch_bg();

		($hook = vBulletinHook::fetch_hook('misc_smilieslist_smilie')) ? eval($hook) : false;

		eval('$smiliebits .= "' . fetch_template('help_smilies_smilie') . '";');
		$lastcat = $smilie['category'];
	}

	$navbits = construct_navbits(array(
		'faq.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['faq'],
		'' => $vbphrase['smilie_list']
	));

	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('misc_smilieslist_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('help_smilies') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
