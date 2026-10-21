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

// identify where we are
define('VB_AREA', 'Archive');

// ###################### Start initialisation #######################
chdir('./..');
define('DIR', getcwd());

require_once(DIR . '/includes/init.php');

// ###################### Start functions #######################
if (DB_QUERIES)
{
	// start functions parse timer
	echo "Parsing functions.php\n";
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$beforetime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	echo "Time before: $beforetime\n";
}

require_once(DIR . '/includes/functions.php');

if (DB_QUERIES)
{
	// end functions parse timer
	$pageendtime = microtime();
	$starttime = explode(' ', $pagestarttime);
	$endtime = explode(' ', $pageendtime);
	$aftertime = $endtime[0] - $starttime[0] + $endtime[1] - $starttime[1];
	echo "Time after:  $aftertime\n";
	echo "\n<hr />\n\n";
}

require_once(DIR . '/includes/sessions.php');

// ###################### Start headers #######################
exec_headers();

// ############ Some stuff for the gmdate bug ####################
$vboptions['hourdiff'] = (date('Z', TIMENOW) / 3600 - $bbuserinfo['timezoneoffset']) * 3600;

// ###################### Get date / time info #######################
fetch_options_overrides($bbuserinfo);
fetch_time_data();

// ############################################ LANGUAGE STUFF ####################################
// initialize $vbphrase and set language constants
$vbphrase = init_language();

// ###################### Start templates & styles #######################
// allow archive to use a non-english language
$styleid = intval($styleid);

$style = $DB_site->query_first("
	SELECT * FROM " . TABLE_PREFIX . "style
	WHERE (styleid = $styleid" . iif(!($permissions['adminpermissions'] & CANCONTROLPANEL), ' AND userselect = 1') . ")
	OR styleid = $vboptions[styleid]
	ORDER BY styleid " . iif($styleid > $vboptions['styleid'], 'DESC', 'ASC') . "
");
$stylevar = fetch_stylevars($style, $bbuserinfo);

if ((substr(PHP_OS, 0, 3) == 'WIN' AND strpos($_SERVER['SERVER_SOFTWARE'], 'apache') === false) OR (strpos(SAPI_NAME, 'cgi') !== false AND @!ini_get('cgi.fix_pathinfo')))
{
	define('SLASH_METHOD', false);
	$archive_info = $_SERVER['QUERY_STRING'];
}
else
{
	define('SLASH_METHOD', true);
	$archive_info = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];
}

// check to see if server is too busy. this is checked at the end of session.php
if ((!empty($servertoobusy) AND $bbuserinfo['usergroupid'] != 6) OR $vboptions['archiveenabled'] == 0)
{
	exec_header_redirect("$vboptions[bburl]/$vboptions[forumhome].php");
}

// #############################################################################
// ### CACHE PERMISSIONS AND GRAB $permissions
// get the combined permissions for the current user
// this also creates the $fpermscache containing the user's forum permissions
$permissions = cache_permissions($bbuserinfo);
// #############################################################################

// check that board is active - if not admin, then display error
if ((!$vboptions['bbactive'] AND !($permissions['adminpermissions'] & CANCONTROLPANEL)) OR !($permissions['forumpermissions'] & CANVIEW))
{
	exec_header_redirect("$vboptions[bburl]/$vboptions[forumhome].php");
}

// deny access if password is expired
if ($bbuserinfo['userid'] AND $permissions['passwordexpires'])
{
	$passworddaysold = floor((TIMENOW - $bbuserinfo['passworddate']) / 86400);

	if ($passworddaysold >= $permissions['passwordexpires'])
	{
		exec_header_redirect("$vboptions[bburl]/$vboptions[forumhome].php");
	}
}

verify_ip_ban();

// #########################################################################################
// ###################### ARCHIVE FUNCTIONS ################################################

// function to list forums in their correct order and nesting
function print_archive_forum_list($parentid = -1, $indent = '')
{
	global $DB_site, $session, $vboptions, $bbuserinfo, $iforumcache, $_FORUMOPTIONS;
	if (!is_array($iforumcache))
	{
		$forums = $DB_site->query("
			SELECT forumid, title, link, parentid, displayorder,
			(options & $_FORUMOPTIONS[cancontainthreads]) AS cancontainthreads
			FROM " . TABLE_PREFIX . "forum AS forum
			WHERE displayorder <> 0 AND
			password = '' AND
			(options & $_FORUMOPTIONS[active])
			ORDER BY displayorder
		");
		$iforumcache = array();
		while ($forum = $DB_site->fetch_array($forums))
		{
			$iforumcache["$forum[parentid]"]["$forum[displayorder]"]["$forum[forumid]"] = $forum;
		}
		unset($forum);
		$DB_site->free_result($forums);
	}
	if (is_array($iforumcache["$parentid"]))
	{
		echo "$indent<ul>\n";
		foreach($iforumcache["$parentid"] AS $x)
		{
			foreach($x AS $forumid => $forum)
			{
				if (!($bbuserinfo['forumpermissions']["$forumid"] & CANVIEW) AND $vboptions['hideprivateforums'])
				{
					continue;
				}
				else
				{
					if ($forum['link'] !== '')
					{
						echo "$indent<li><a href=\"$forum[link]\">$forum[title]</a></li>\n";
					}
					else if ($forum['cancontainthreads'])
					{
						echo "$indent<li><a href=\"" . (!SLASH_METHOD ? 'index.php?' : '') . "f-$forumid.html\">$forum[title]</a></li>\n";
					}
					else
					{
						echo "$indent<li><strong>$forum[title]</strong></li>\n";
					}
					print_archive_forum_list($forumid, "$indent  ");
				}
			}
		}
		echo "$indent</ul>\n";
	}
}

// function to draw the navbar for the archive pages
function print_archive_navigation($foruminfo, $threadinfo='')
{
	global $vboptions, $forumcache, $vbphrase;
	$navarray = array("<a href=\"./\">$vboptions[bbtitle]</a>");

	if (!empty($foruminfo))
	{
		foreach(array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3))) AS $forumid)
		{
			if ($threadinfo == '' AND $forumid == $foruminfo['forumid'])
			{
				$navarray[] = $forumcache["$forumid"]['title'];
			}
			else
			{
				$navarray[] = "<a href=\"" . (!SLASH_METHOD ? 'index.php?' : '') . "f-$forumid.html\">" . $forumcache["$forumid"]['title'] . "</a>";
			}
		}
		if (is_array($threadinfo))
		{
			$navarray[] = $threadinfo['title'];
		}
	}
	global $pda, $bbuserinfo;
	if ($pda)
	{
		if ($bbuserinfo['userid'] == 0)
		{
			if (SLASH_METHOD)
			{
				$loginlink = '?login=1';
			}
			else
			{
				$loginlink = "index.php?login=1" . (!empty($querystring) ? "&amp;$querystring" : '');
			}
			$extra = '<div class="pda"><a href="' . $loginlink . '">' . $vbphrase['log_in'] . "</a></div>\n";
		}
	}
	else
	{
			if (SLASH_METHOD)
			{
				$pdalink = '?pda=1';
			}
			else
			{
				$pdalink = "index.php?pda=1" . (!empty($querystring) ? "&amp;$querystring" : '');
			}
		$extra = '<div class="pda"><a href="' . $pdalink . '">' . $vbphrase['pda'] . "</a></div>\n";
	}
	return '<div id="navbar">' . implode(' &gt; ', $navarray) . "</div>\n<hr />\n" . $extra;
}

// function to draw the page links for the archive pages
function print_archive_page_navigation($total, $perpage, $link)
{
	global $p, $vbphrase;
	$numpages = ceil($total / $perpage);
	if ($numpages > 1)
	{
		echo "<div id=\"pagenumbers\"><b>$vbphrase[pages] :</b>\n";

		for ($i=1; $i<=$numpages; $i++)
		{
			if ($i == $p)
			{
				echo "[<b>$i</b>]\n";
			}
			else
			{
				echo "<a href=\"" . (!SLASH_METHOD ? 'index.php?' : '') . "$link$i.html\">$i</a>\n";
			}
		}

		echo "</div>\n<hr />\n";
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12198 $
|| ####################################################################
\*======================================================================*/
?>