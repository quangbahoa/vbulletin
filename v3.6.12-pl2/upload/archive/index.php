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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('SESSION_BYPASS', 1);
define('THIS_SCRIPT', 'archive');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('forum');
$specialtemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (SLASH_METHOD AND strpos($archive_info , '/archive/index.php/') === false)
{
	exec_header_redirect($vbulletin->options['bburl'] . '/archive/index.php/');
}

// parse query string
$f = 0;
$p = 0;
$t = 0;
$output = '';

$endbit = str_replace('.html', '', $archive_info);
if (SLASH_METHOD)
{
	$endbit = substr(strrchr($endbit, '/') , 1);
}
else if (strpos($endbit, '&') !== false)
{
	$endbit = substr(strrchr($endbit, '&') , 1);
}
if ($endbit != '' AND $endbit != 'index.php')
{
	$queryparts = explode('-', $endbit);
	foreach ($queryparts AS $querypart)
	{
		if ($lastpart != '')
		{
			// can be:
			// f: forumid
			// p: pagenumber
			// t: threadid
			$$lastpart = $querypart;
			$lastpart = '';
		}
		else
		{
			switch ($querypart)
			{
				case 'f':
				case 'p':
				case 't':
					$lastpart = $querypart;
					break;
				default:
					$lastpart = '';
			}
		}
	}
}
else
{
	$do = 'index';
}

$vbulletin->input->clean_array_gpc('r', array(
	'pda'     => TYPE_BOOL,
	'login'   => TYPE_BOOL,
	'message' => TYPE_BOOL
));

$vbulletin->input->clean_array_gpc('c', array(
	COOKIE_PREFIX . 'pda' => TYPE_UINT
));

$vbulletin->input->clean_array_gpc('p', array(
	'username' => TYPE_STR,
	'password' => TYPE_STR,
));

// check to see if the person is using a PDA if so we'll sort in ASC
// force a redirect afterwards so we dont get problems with search engines
if ($vbulletin->GPC['pda'] OR $vbulletin->GPC[COOKIE_PREFIX . 'pda'])
{
	if ($t)
	{
		$t = intval($t);
		$querystring = 't-' . $t . iif($p, '-p-' . intval($p)) . '.html';
	}
	else if ($f)
	{
		$f = intval($f);
		$querystring = 'f-' . $f . iif($p, '-p-' . intval($p)) . '.html';
	}
}

if ($vbulletin->GPC['pda'])
{
	vbsetcookie('pda', '1', 1);
	exec_header_redirect($querystring);
}
else if ($vbulletin->GPC[COOKIE_PREFIX . 'pda'])
{
	$pda = true;
}

$title = $vbulletin->options['bbtitle'];

if ($vbulletin->userinfo['userid'] == 0 AND $vbulletin->GPC['login'])
{
	if (!empty($vbulletin->GPC['username']) AND !empty($vbulletin->GPC['password']))
	{
		require_once(DIR . '/includes/functions_login.php');
		$strikes = verify_strike_status($vbulletin->GPC['username'], true);
		if ($strikes === false)
		{ // user has got too many wrong passwords
			$error_message = fetch_error('strikes', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl']);
			$do = 'error';
		}
		else if (verify_authentication($vbulletin->GPC['username'], $vbulletin->GPC['password'], '', '', false, true))
		{
			exec_unstrike_user($vbulletin->GPC['username']);

			$db->query_write("DELETE FROM " . TABLE_PREFIX . "session WHERE sessionhash = '" . $db->escape_string($vbulletin->session->vars['dbsessionhash']) . "'");

			$vbulletin->session->vars = $vbulletin->session->fetch_session($vbulletin->userinfo['userid']);

			/*insert query*/
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "session
					(sessionhash, userid, host, idhash, lastactivity, styleid, loggedin, bypass, useragent)
				VALUES
					('" . $db->escape_string($vbulletin->session->vars['sessionhash']) . "', " . $vbulletin->session->vars['userid'] . ", '" . $db->escape_string($vbulletin->session->vars['host']) . "', '" . $db->escape_string($vbulletin->session->vars['idhash']) . "', " . TIMENOW . ", " . $vbulletin->session->vars['styleid'] . ", 1, " . iif ($logintype === 'cplogin', 1, 0) . ", '" . $db->escape_string($vbulletin->session->vars['useragent']) . "')
			");
			exec_header_redirect($querystring);
		}
		else
		{ // wrong username / password
			exec_strike_user($vbulletin->userinfo['username']);
			$error_message = fetch_error('badlogin', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'], $strikes);
			$do = 'error';
		}
	}
}

if ($do == 'error')
{
}
else if ($t)
{
	$do = 'thread';

	$threadinfo = fetch_threadinfo($t);
	$foruminfo = fetch_foruminfo($threadinfo['forumid']);

	$forumperms = $vbulletin->userinfo['forumpermissions'][$foruminfo['forumid']];
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) OR in_coventry($threadinfo['postuserid']) OR $threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
	{
		exit;
	}

	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	if (trim($foruminfo['link']) != '')
	{
		exec_header_redirect($foruminfo['link'], true);
	}

	$title = "$threadinfo[title] [$vbphrase[archive]] " . ($p > 1 ? ' - ' . construct_phrase($vbphrase['page_x'], $p) : '') . " - $title";

	$p = intval($p);
	$metatags = "<meta name=\"keywords\" content=\"$threadinfo[title], " . $vbulletin->options['keywords'] . "\" />
	<meta name=\"description\" content=\"[$vbphrase[archive]] " . ($p > 1 ? construct_phrase($vbphrase['page_x'], $p) . " " : "") . "$threadinfo[title] $foruminfo[title_clean]\" />
	";

}
else if ($f)
{
	$do = 'forum';

	$forumperms = $vbulletin->userinfo['forumpermissions'][$f];
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
	{
		exit;
	}

	$foruminfo = fetch_foruminfo($f, false);

	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	if (trim($foruminfo['link']) != '')
	{
		exec_header_redirect($foruminfo['link'], true);
	}

	$title = "$foruminfo[title_clean] [$vbphrase[archive]]" . ($p > 1 ? ' - ' . construct_phrase($vbphrase['page_x'], $p) : '') . " - $title";

	$p = intval($p);
	$metatags = "<meta name=\"keywords\" content=\"$foruminfo[title_clean], " . $vbulletin->options['keywords'] . "\" />
	<meta name=\"description\" content=\"[$vbphrase[archive]] " . ($p > 1 ? construct_phrase($vbphrase['page_x'], $p) . " " : "") . $foruminfo['description_clean'] . "\" />
	";

}
else
{
	$do = 'index';
	$metatags = "<meta name=\"keywords\" content=\"" . $vbulletin->options['keywords'] . "\" />
	<meta name=\"description\" content=\"" . $vbulletin->options['description'] . "\" />";
}

($hook = vBulletinHook::fetch_hook('archive_process_start')) ? eval($hook) : false;

if ($pda AND $vbulletin->userinfo['userid'] == 0 AND $vbulletin->GPC['login'] AND $do != 'error')
{
	$do = 'login';
}
if ($pda AND $vbulletin->userinfo['userid'] > 0 AND $vbulletin->GPC['message'] AND false)
{
	$do = 'message';
}

$output .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" dir=\"$stylevar[textdirection]\" lang=\"$stylevar[languagecode]\">
<head>
	<meta http-equiv=\"Content-Type\" content=\"text/html; charset=$stylevar[charset]\" />
	$metatags
	<title>$title</title>
	<link rel=\"stylesheet\" type=\"text/css\" href=\"" . $vbulletin->options['bburl'] . "/archive/archive.css\" />
</head>
<body>
<div class=\"pagebody\">
";

($hook = vBulletinHook::fetch_hook('archive_postheader')) ? eval($hook) : false;

// ********************************************************************************************
// display board

if ($do == 'index')
{
	$output .= print_archive_navigation(array());

	$output .= "<p class=\"largefont\">$vbphrase[view_full_version]: <a href=\"" . $vbulletin->options['bburl'] . '/' . $vbulletin->options['forumhome'] . '.php">' . $vbulletin->options['bbtitle'] . "</a></p>\n";

	$output .= "<div id=\"content\">\n";
	$output .= print_archive_forum_list();
	$output .= "</div>\n";

}

if ($Coventry = fetch_coventry('string'))
{
	$globalignore = "AND " . iif($do == 'forum', 'thread.post', 'post.') . "userid NOT IN ($Coventry) ";
}
else
{
	$globalignore = '';
}

// ********************************************************************************************
// display forum

if ($do == 'forum')
{
	// list threads

	$output .= print_archive_navigation($foruminfo);

	$output .= "<p class=\"largefont\">$vbphrase[view_full_version] : <a href=\"" . $vbulletin->options['bburl'] . "/forumdisplay.php?f=$foruminfo[forumid]\">$foruminfo[title_clean]</a></p>\n<hr />\n";

	if ($foruminfo['cancontainthreads'])
	{

		if (!$p)
		{
			$p = 1;
		}

		$output .= print_archive_page_navigation($foruminfo['threadcount'], $vbulletin->options['archive_threadsperpage'], "f-$foruminfo[forumid]");

		$threads = $db->query_read_slave("
			SELECT threadid , title, lastpost, replycount
			FROM " . TABLE_PREFIX . "thread AS thread
			WHERE forumid = $foruminfo[forumid]
				AND visible = 1
				AND open <> 10
				$globalignore
			ORDER BY dateline " . iif($pda, 'DESC', 'ASC') . "
			LIMIT " . ($p - 1) * $vbulletin->options['archive_threadsperpage'] . ',' . $vbulletin->options['archive_threadsperpage']
		);

		$start = ($p - 1) * $vbulletin->options['archive_threadsperpage'] + 1;
		if ($pda AND false)
		{
			$output .= "<span id=\"posting\"><a href=\"?message=1\" rel=\"nofollow\">New Thread</a></span>";
		}
		$output .= "<div id=\"content\">\n<ol start=\"$start\">\n";
		while ($thread = $db->fetch_array($threads))
		{
			if ($vbulletin->options['wordwrap'] != 0)
			{
				$thread['title'] = fetch_word_wrapped_string($thread['title']);
			}

			$thread['title'] = fetch_censored_text($thread['title']);

			($hook = vBulletinHook::fetch_hook('archive_forum_thread')) ? eval($hook) : false;

			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
			{
				$output .= "\t<li>$thread[title]" . iif($pda, " <i>(" . construct_phrase($vbphrase['x_replies'], $thread['replycount']) . ")</i>") . "</li>\n";
			}
			else if ($vbulletin->options['archive_threadtype'] OR $pda)
			{
				$output .= "\t<li><a href=\"" . (!SLASH_METHOD ? 'index.php?' : '') . "t-$thread[threadid].html\">$thread[title]</a>" . iif($pda, " <i>(" . construct_phrase($vbphrase['x_replies'], $thread['replycount']) . ")</i>") . "</li>\n";
			}
			else
			{
				$output .= "\t<li><a href=\"" . $vbulletin->options['bburl'] . "/showthread.php?t=$thread[threadid]\">$thread[title]</a></li>\n";
			}
		}
		$output .= "</ol>\n</div>\n";

	}
	else
	{
		$output .= "<div id=\"content\">\n";
		$output .= print_archive_forum_list($f);
		$output .= "</div>\n";
	}
}

// ********************************************************************************************
// display thread

if ($do == 'thread')
{
	if ($vbulletin->options['wordwrap'] != 0)
	{
		$threadinfo['title'] = fetch_word_wrapped_string($threadinfo['title']);
	}

	$threadinfo['title'] = fetch_censored_text($threadinfo['title']);

	$output .= print_archive_navigation($foruminfo, $threadinfo);

	$output .= "<p class=\"largefont\">$vbphrase[view_full_version] : <a href=\"" . $vbulletin->options['bburl'] . "/showthread.php?t=$threadinfo[threadid]\">$threadinfo[title]</a></p>\n<hr />\n";

	if ($p == 0)
	{
		$p = 1;
	}

	$output .= print_archive_page_navigation($threadinfo['replycount'] + 1, $vbulletin->options['archive_postsperpage'], "t-$threadinfo[threadid]");

	$posts = $db->query_read_slave("
		SELECT post.postid, post.pagetext, IFNULL( user.username , post.username ) AS username, dateline
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = post.userid)
		WHERE threadid = $threadinfo[threadid]
			AND visible = 1
			$globalignore
		ORDER BY dateline ASC
		LIMIT " . (($p - 1) * $vbulletin->options['archive_postsperpage']) . ',' . $vbulletin->options[archive_postsperpage]
	);
	if ($pda AND false)
	{
		$output .= "<span id=\"posting\"><a href=\"?message=1\" rel=\"nofollow\">New Reply</a></span>";
	}
	$i = 0;
	while ($post = $db->fetch_array($posts))
	{
		$i++;
		$post['pagetext_simp'] = strip_bbcode($post['pagetext']);
		$post['postdate'] = vbdate($vbulletin->options['dateformat'], $post['dateline']);
		$post['posttime'] = vbdate($vbulletin->options['timeformat'], $post['dateline']);

		if ($vbulletin->options['wordwrap'] != 0)
		{
			$post['pagetext_simp'] = fetch_word_wrapped_string($post['pagetext_simp']);
		}

		$post['pagetext_simp'] = fetch_censored_text($post['pagetext_simp']);

		($hook = vBulletinHook::fetch_hook('archive_thread_post')) ? eval($hook) : false;

		$output .= "\n<div class=\"post\"><div class=\"posttop\"><div class=\"username\">$post[username]</div><div class=\"date\">$post[postdate], $post[posttime]</div></div>";
		$output .= "<div class=\"posttext\">" . nl2br(htmlspecialchars_uni($post['pagetext_simp'])) . "</div></div><hr />\n\n";
	}

}

// ********************************************************************************************
// display login
if ($do == 'login')
{
	$output .= print_archive_navigation(array());

	$output .= "<p class=\"largefont\">$vbphrase[view_full_version]: <a href=\"" . $vbulletin->options['bburl'] . '/' . $vbulletin->options['forumhome'] . '.php">' . $vbulletin->options['bbtitle'] . "</a></p>\n";

	if (SLASH_METHOD)
	{
		$loginlink = "index.php/$querystring?login=1";
	}
	else
	{
		$loginlink = "index.php?login=1" . (!empty($querystring) ? "&amp;$querystring" : '');
	}

	$output .= "<div id=\"content\">\n";
	$output .= "<strong>$vbphrase[log_in]</strong>\n";
	$output .= "<form action=\"" . $vbulletin->options['bburl'] . "/archive/$loginlink\" method=\"post\">\n";
	$output .= "$vbphrase[username]: <input type=\"text\" name=\"username\" size=\"15\" />\n";
	$output .= "$vbphrase[password]: <input type=\"password\" name=\"password\" size=\"15\" />\n";
	$output .= "<input type=\"submit\" name=\"sbutton\" value=\"$vbphrase[log_in]\" />\n";
	$output .= "</form>\n";
	$output .= "</div>\n";
}

// ********************************************************************************************
// display error
if ($do == 'error')
{
	$output .= print_archive_navigation(array());

	$output .= "<p class=\"largefont\">$vbphrase[view_full_version]: <a href=\"" . $vbulletin->options['bburl'] . '/' . $vbulletin->options['forumhome'] . '.php">' . $vbulletin->options['bbtitle'] . "</a></p>\n";

	$output .= "<div id=\"content\">\n";
	$output .= $error_message;
	$output .= "</div>\n";
}

($hook = vBulletinHook::fetch_hook('archive_complete')) ? eval($hook) : false;

$output .= "

</div>
</body>
</html>";

if (defined('NOSHUTDOWNFUNC'))
{
	exec_shut_down();
}

echo $output;

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 24450 $
|| ####################################################################
\*======================================================================*/
?>
