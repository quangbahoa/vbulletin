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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('SESSION_BYPASS', 1);
define('NO_REGISTER_GLOBALS', 1);
define('THIS_SCRIPT', 'archive');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('forum');
$specialtemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_bigthree.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (SLASH_METHOD AND strpos($archive_info , '/archive/index.php/') === false)
{
	exec_header_redirect("$vboptions[bburl]/archive/index.php/");
}

// parse query string
$f = 0;
$p = 0;
$t = 0;

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

// check to see if the person is using a PDA if so we'll sort in ASC
// force a redirect afterwards so we dont get problems with search engines
if ($_GET['pda'] OR $_COOKIE[COOKIE_PREFIX . 'pda'])
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

if ($_GET['pda'])
{
	vbsetcookie('pda', '1', 1);
	exec_header_redirect($querystring);
}
else if ($_COOKIE[COOKIE_PREFIX . 'pda'])
{
	$pda = true;
}

$title = $vboptions['bbtitle'];

if ($bbuserinfo['userid'] == 0 AND $_GET['login'])
{
	if (!empty($_POST['username']) AND !empty($_POST['password']))
	{
		require_once('./includes/functions_login.php');
		$strikes = verify_strike_status($_POST['username'], true);
		if ($strikes === false)
		{ // user has got too many wrong passwords
			eval('$error_message = "' . fetch_phrase('error_strikes', PHRASETYPEID_ERROR, 'error_') . '";');
			$do = 'error';
		}
		else if (verify_authentication($_POST['username'], $_POST['password'], '', '', true))
		{
			exec_unstrike_user($_POST['username']);

			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "session WHERE sessionhash = '" . addslashes($session['dbsessionhash']) . "'");

			$session['sessionhash'] = fetch_sessionhash();
			$session['dbsessionhash'] = $session['sessionhash'];
			$DB_site->query("
				INSERT INTO " . TABLE_PREFIX . "session
					(sessionhash, userid, host, idhash, lastactivity, styleid, loggedin, bypass, useragent)
				VALUES
					('" . addslashes($session['sessionhash']) . "', " . intval($bbuserinfo['userid']) . ", '" . addslashes(SESSION_HOST) . "', '" . addslashes(SESSION_IDHASH) . "', " . TIMENOW . ", $session[styleid], 1, " . iif ($logintype === 'cplogin', 1, 0) . ", '" . addslashes(USER_AGENT) . "')
			");
			vbsetcookie('sessionhash', $session['sessionhash'], false, true);
			exec_header_redirect($querystring);
		}
		else
		{ // wrong username / password
			exec_strike_user($bbuserinfo['username']);
			eval('$error_message = "' . fetch_phrase('error_badlogin', PHRASETYPEID_ERROR, 'error_') . '";');
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

	$forumperms = $bbuserinfo['forumpermissions'][$foruminfo['forumid']];
	if (!($forumperms & CANVIEW) OR !($forumperms & CANVIEWOTHERS) OR in_coventry($threadinfo['postuserid']) OR $threadinfo['isdeleted'] OR !$threadinfo['visible'])
	{
		exit;
	}

	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	if (trim($foruminfo['link'] != ''))
	{
		exec_header_redirect($foruminfo['link']);
	}

	$title .= ' - ' . $threadinfo['title'];

}
else if ($f)
{
	$do = 'forum';

	$forumperms = $bbuserinfo['forumpermissions'][$f];
	if (!($forumperms & CANVIEW) OR !($forumperms & CANVIEWOTHERS))
	{
		exit;
	}

	$foruminfo = fetch_foruminfo($f, false);

	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	if (trim($foruminfo['link'] != ''))
	{
		exec_header_redirect($foruminfo['link']);
	}

	$title .= ' - ' . $foruminfo['title'];
}
else
{
	$do = 'index';
}

if ($pda AND $bbuserinfo['userid'] == 0 AND $_GET['login'] AND $do != 'error')
{
	$do = 'login';
}
if ($pda AND $bbuserinfo['userid'] > 0 AND $_GET['message'] AND false)
{
	$do = 'message';
}
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="<?php echo $stylevar['textdirection']; ?>" lang="<?php echo $stylevar['languagecode']; ?>">
<head>
	<title><?php echo $title; ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $stylevar['charset']; ?>" />
	<meta name="keywords" content="<?php echo $vboptions['keywords']; ?>" />
	<meta name="description" content="<?php echo $vboptions['description']; ?>" />
	<link rel="stylesheet" href="<?php echo $vboptions['bburl']; ?>/archive/archive.css" />
</head>
<body>
<div class="pagebody">
<?php

// ********************************************************************************************
// display board

if ($do == 'index')
{
	echo print_archive_navigation(array());

	echo "<p class=\"largefont\">$vbphrase[view_full_version]: <a href=\"$vboptions[bburl]/$vboptions[forumhome].php\">$vboptions[bbtitle]</a></p>\n";

	echo "<div id=\"content\">\n";
	print_archive_forum_list();
	echo "</div>\n";

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

	echo print_archive_navigation($foruminfo);

	echo "<p class=\"largefont\">$vbphrase[view_full_version] : <a href=\"$vboptions[bburl]/forumdisplay.php?f=$foruminfo[forumid]\">$foruminfo[title]</a></p>\n<hr />\n";

	if ($foruminfo['cancontainthreads'])
	{

		if (!$p)
		{
			$p = 1;
		}

		print_archive_page_navigation($foruminfo['threadcount'], $vboptions['archive_threadsperpage'], "f-$foruminfo[forumid]-p-");

		$threads = $DB_site->query("
			SELECT threadid , title, lastpost, replycount
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
			WHERE forumid = $foruminfo[forumid]
				AND visible = 1
				AND open <> 10
				AND deletionlog.primaryid IS NULL
				$globalignore
			ORDER BY dateline " . iif($pda, 'DESC', 'ASC') . "
			LIMIT " . ($p - 1) * $vboptions['archive_threadsperpage'] . ',' . $vboptions['archive_threadsperpage']
		);

		$start = ($p - 1) * $vboptions['archive_threadsperpage'] + 1;
		if ($pda AND false)
		{
			echo "<span id=\"posting\"><a href=\"?message=1\">New Thread</a></span>";
		}
		echo "<div id=\"content\">\n<ol start=\"$start\">\n";
		while ($thread = $DB_site->fetch_array($threads))
		{
			echo "\t<li><a href=\"" . (!SLASH_METHOD ? 'index.php?' : '') . "t-$thread[threadid].html\">$thread[title]</a>" . iif($pda, " <i>(" . construct_phrase($vbphrase['x_replies'], $thread['replycount']) . ")</i>") . "</li>\n";
		}
		echo "</ol>\n</div>\n";

	}
	else
	{
		echo "<div id=\"content\">\n";
		print_archive_forum_list($f);
		echo "</div>\n";
	}
}

// ********************************************************************************************
// display thread

if ($do == 'thread')
{
	echo print_archive_navigation($foruminfo, $threadinfo);

	echo "<p class=\"largefont\">$vbphrase[view_full_version] : <a href=\"$vboptions[bburl]/showthread.php?t=$threadinfo[threadid]\">$threadinfo[title]</a></p>\n<hr />\n";

	if ($p == 0)
	{
		$p = 1;
	}

	print_archive_page_navigation($threadinfo['replycount']+1, $vboptions['archive_postsperpage'], "t-$threadinfo[threadid]-p-");

	$posts = $DB_site->query("
		SELECT post.postid, post.pagetext, IFNULL( user.username , post.username ) AS username, dateline
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND deletionlog.type = 'post')
		WHERE threadid = $threadinfo[threadid]
			AND visible = 1
			AND deletionlog.primaryid IS NULL
			$globalignore
		ORDER BY dateline ASC
		LIMIT " . (($p - 1) * $vboptions['archive_postsperpage']) . ", $vboptions[archive_postsperpage]
	");
	if ($pda AND false)
	{
		echo "<span id=\"posting\"><a href=\"?message=1\">New Reply</a></span>";
	}
	$i = 0;
	while ($post = $DB_site->fetch_array($posts))
	{
		$i++;
		$post['pagetext'] = strip_bbcode($post['pagetext']);
		$post['postdate'] = vbdate($vboptions['dateformat'], $post['dateline']);
		$post['posttime'] = vbdate($vboptions['timeformat'], $post['dateline']);
		echo "\n<div class=\"post\"><div class=\"posttop\"><div class=\"username\">$post[username]</div><div class=\"date\">$post[postdate], $post[posttime]</div></div>";
		echo "<div class=\"posttext\">" . nl2br(htmlspecialchars_uni($post['pagetext'])) . "</div></div><hr />\n\n";
	}

}

// ********************************************************************************************
// display login
if ($do == 'login')
{
	echo print_archive_navigation(array());

	echo "<p class=\"largefont\">$vbphrase[view_full_version]: <a href=\"$vboptions[bburl]/$vboptions[forumhome].php\">$vboptions[bbtitle]</a></p>\n";

	if (SLASH_METHOD)
	{
		$loginlink = "index.php/$querystring?login=1";
	}
	else
	{
		$loginlink = "index.php?login=1" . (!empty($querystring) ? "&amp;$querystring" : '');
	}

	echo "<div id=\"content\">\n";
	echo "<strong>$vbphrase[log_in]</strong>\n";
	echo "<form action=\"$vboptions[bburl]/archive/$loginlink\" method=\"post\">\n";
	echo "$vbphrase[username]: <input type=\"text\" name=\"username\" size=\"15\" />\n";
	echo "$vbphrase[password]: <input type=\"password\" name=\"password\" size=\"15\" />\n";
	echo "<input type=\"submit\" name=\"sbutton\" value=\"$vbphrase[log_in]\" />\n";
	echo "</form>\n";
	echo "</div>\n";
}

// ********************************************************************************************
// display error
if ($do == 'error')
{
	echo print_archive_navigation(array());

	echo "<p class=\"largefont\">$vbphrase[view_full_version]: <a href=\"$vboptions[bburl]/$vboptions[forumhome].php\">$vboptions[bbtitle]</a></p>\n";

	echo "<div id=\"content\">\n";
	echo $error_message;
	echo "</div>\n";
}

echo "\n";

if (defined('NOSHUTDOWNFUNC'))
{
	exec_shut_down();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15922 $
|| ####################################################################
\*======================================================================*/
?>
</div>
</body>
</html>