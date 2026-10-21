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
define('NO_REGISTER_GLOBALS', 1);
define('SKIP_SESSIONCREATE', 1);
define('DIE_QUIETLY', 1);
define('THIS_SCRIPT', 'external');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_external.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// check to see if there is a forum preference
if ($_REQUEST['forumids'] != '')
{
	$forumchoice = array();
	$forumids = explode(',', $_REQUEST['forumids']);
	foreach ($forumids AS $forumid)
	{
		$forumid = intval($forumid);
		$fp = &$bbuserinfo['forumpermissions']["$forumid"];
		if (isset($forumcache["$forumid"]) AND ($fp & CANVIEW) AND ($fp & CANVIEWOTHERS) AND verify_forum_password($forumid, $forumcache["$forumid"]['password'], false))
		{
			$forumchoice[] = $forumid;
		}
	}

	$number_of_forums = sizeof($forumchoice);
	if ($number_of_forums == 1)
	{
		$title = $forumcache["$forumchoice[0]"]['title'];
	}
	else if ($number_of_forums > 1)
	{
		$title = implode(',', $forumchoice);
	}
	else
	{
		$title = '';
	}

	if (!empty($forumchoice))
	{
		$forumchoice = 'AND thread.forumid IN(' . implode(',', $forumchoice) . ')';
	}
	else
	{
		$forumchoice = '';
	}
}
else
{
	foreach (array_keys($forumcache) AS $forumid)
	{
		$fp = &$bbuserinfo['forumpermissions']["$forumid"];
		if (($fp & CANVIEW) AND ($fp & CANVIEWOTHERS) AND verify_forum_password($forumid, $forumcache["$forumid"]['password'], false))
		{
			$forumchoice[] = $forumid;
		}
	}
	if (!empty($forumchoice))
	{
		$forumchoice = 'AND thread.forumid IN(' . implode(',', $forumchoice) . ')';
	}
	else
	{
		$forumchoice = '';
	}
}

if ($forumchoice != '')
{
	// query last 15 threads from visible / chosen forums
	$threads = $DB_site->query("
		SELECT thread.threadid, thread.title, thread.lastposter, thread.lastpost, thread.postusername, thread.dateline, forum.forumid, forum.title AS forumtitle, post.pagetext AS preview
		FROM " . TABLE_PREFIX . "thread AS thread
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = thread.firstpostid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE 1=1
			$forumchoice
			AND thread.visible = 1
			AND open <> 10
			AND deletionlog.primaryid IS NULL
		ORDER BY thread.dateline DESC
		LIMIT 15
	");
}

$threadcache = array();
while ($thread = $DB_site->fetch_array($threads))
{ // fetch the threads
	$threadcache[] = $thread;
}
$_REQUEST['type'] = strtoupper($_REQUEST['type']);
switch ($_REQUEST['type'])
{
	case 'JS':
	case 'XML':
	case 'RSS1':
	case 'RSS2':
		break;
	default:
		$_REQUEST['type'] = 'RSS';
}

if ($_REQUEST['type'] == 'JS' AND $vboptions['externaljs'])
{ // javascript output

	?>
	function thread(threadid, title, poster, threaddate, threadtime)
	{
		this.threadid = threadid;
		this.title = title;
		this.poster = poster;
		this.threaddate = threaddate;
		this.threadtime = threadtime;
	}
	<?php
	echo "var threads = new Array(" . sizeof ($threadcache) . ");\r\n";
	if (!empty($threadcache))
	{
		foreach ($threadcache AS $threadnum => $thread)
		{
			$thread['title'] = addslashes_js($thread['title']);
			$thread['poster'] = addslashes_js($thread['postusername']);
			echo "\tthreads[$threadnum] = new thread($thread[threadid], '$thread[title]', '$thread[poster]', '" . vbdate($vboptions['dateformat'], $thread['dateline']) . "', '" . vbdate($vboptions['timeformat'], $thread['dateline']) . "');\r\n";
		}
	}

}
else if ($_REQUEST['type'] == 'XML' AND $vboptions['externalxml'])
{ // XML output

	// set XML type and nocache headers
	header('Content-Type: text/xml');
	header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	// print out the page header
	echo '<?xml version="1.0" encoding="' . $stylevar['charset'] . '"?>' . "\r\n";
	echo "<source>\r\n\r\n";
	echo "\t<url>$vboptions[bburl]</url>\r\n\r\n";

	// list returned threads
	if (!empty($threadcache))
	{
		foreach ($threadcache AS $thread)
		{
			echo "\t<thread id=\"$thread[threadid]\">\r\n";
			echo "\t\t<title><![CDATA[$thread[title]]]></title>\r\n";
			echo "\t\t<author><![CDATA[$thread[postusername]]]></author>\r\n";
			echo "\t\t<date>" . vbdate($vboptions['dateformat'], $thread['dateline']) . "</date>\r\n";
			echo "\t\t<time>" . vbdate($vboptions['timeformat'], $thread['dateline']) . "</time>\r\n";
			echo "\t</thread>\r\n";
		}
	}
	echo "\r\n</source>";
}
else if (in_array($_REQUEST['type'], array('RSS', 'RSS1', 'RSS2')) AND $vboptions['externalrss'])
{ // RSS output
	// setup the board title
	if (empty($title))
	{ // just show board title
		$rsstitle = htmlspecialchars_uni($vboptions['bbtitle']);
	}
	else
	{ // show board title plus selection
		$rsstitle = htmlspecialchars_uni($vboptions['bbtitle']) . " - $title";
	}

	// set XML type and nocache headers
	header('Content-Type: text/xml');
	header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo '<?xml version="1.0" encoding="' . $stylevar['charset'] . '"?>' . "\r\n\r\n";

	# Each specs shared code is entered in full (duplicated) to make it easier to read
	switch($_REQUEST['type'])
	{
		case 'RSS':
			echo '<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://my.netscape.com/publish/formats/rss-0.91.dtd">' . "\r\n";
			echo '<rss version="0.91">' . "\r\n";
			echo "<channel>\r\n";
			echo "\t<title>$rsstitle</title>\r\n";
			echo "\t<link>$vboptions[bburl]</link>\r\n";
			echo "\t<description><![CDATA[" . htmlspecialchars_uni($vboptions['description']) . "]]></description>\r\n";
			echo "\t<language>$stylevar[languagecode]</language>\r\n";
		break;
		case 'RSS1':
			echo "<rdf:RDF
  xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"
  xmlns:dc=\"http://purl.org/dc/elements/1.1/\"
  xmlns:syn=\"http://purl.org/rss/1.0/modules/syndication/\"
  xmlns=\"http://purl.org/rss/1.0/\">\r\n\r\n";

			echo "\t<channel rdf:about=\"$vboptions[bburl]\">\r\n";
			echo "\t<title>$rsstitle</title>\r\n";
			echo "\t<link>$vboptions[bburl]</link>\r\n";
			echo "\t<description><![CDATA[". htmlspecialchars_uni($vboptions['description']) . "]]></description>\r\n";
			echo "\t<syn:updatePeriod>hourly</syn:updatePeriod>\r\n";
			echo "\t<syn:updateFrequency>1</syn:updateFrequency>\r\n";
			echo "\t<syn:updateBase>1970-01-01T00:00Z</syn:updateBase>\r\n";
			echo "\t<dc:language>$stylevar[languagecode]</dc:language>\r\n";
			echo "\t<dc:creator>vBulletin</dc:creator>\r\n";
			echo "\t<dc:date>" . gmdate('Y-m-d\TH:i:s', TIMENOW) . "Z</dc:date>\r\n";
			echo "\t<items>\r\n";
			echo "\t<rdf:Seq>\r\n";
			echo "\t<rdf:li rdf:resource=\"$vboptions[bburl]\" />\r\n";
			echo "\t</rdf:Seq>\r\n";
			echo "\t</items>\r\n";
			echo "\t</channel>\r\n";
		break;
		case 'RSS2':
			echo "<rss version=\"2.0\">\r\n";
			echo "<channel>\r\n";
			echo "\t<title>$rsstitle</title>\r\n";
			echo "\t<link>$vboptions[bburl]</link>\r\n";
			echo "\t<description><![CDATA[" . htmlspecialchars_uni($vboptions['description']) . "]]></description>\r\n";
			echo "\t<language>$stylevar[languagecode]</language>\r\n";
			echo "\t<pubDate>" . gmdate('D, d M Y H:i:s', TIMENOW) . " GMT</pubDate>\r\n";
			echo "\t<generator>vBulletin</generator>\r\n";
			echo "\t<ttl>60</ttl>\r\n";
		break;
	}

	$i = 0;

	// list returned threads
	if (!empty($threadcache))
	{
		foreach ($threadcache AS $thread)
		{
			$fp = &$bbuserinfo['forumpermissions']["$forumid"];

			switch($_REQUEST['type'])
			{
				case 'RSS':
					echo "\r\n\t<item>\r\n";
					echo "\t\t<title>$thread[title]</title>\r\n";
					echo "\t\t<link>$vboptions[bburl]/showthread.php?t=$thread[threadid]&amp;goto=newpost</link>\r\n";
					echo "\t\t<description><![CDATA[$vbphrase[forum]: " . htmlspecialchars_uni($thread['forumtitle']) . "\r\n$vbphrase[posted_by]: $thread[postusername]\r\n" .
						construct_phrase($vbphrase['post_time_x_at_y'], vbdate($vboptions['dateformat'], $thread['dateline']), vbdate($vboptions['timeformat'], $thread['dateline'])) .
						"]]></description>\r\n";
					echo "\t</item>\r\n";
					break;
				case 'RSS1':
					echo "\r\n\t<item rdf:about=\"$vboptions[bburl]/showthread.php?t=$thread[threadid]\">\r\n";
					echo "\t\t<title>$thread[title]</title>\r\n";
					echo "\t\t<link>$vboptions[bburl]/showthread.php?t=$thread[threadid]&amp;goto=newpost</link>\r\n";
					#echo "\t\t<content:encoded><![CDATA[". htmlspecialchars_uni(fetch_trimmed_title(strip_bbcode($thread['preview'], false, true), $vbulletin->options['threadpreview'])) ."]]></content:encoded>\r\n";
					echo "\t\t<description><![CDATA[". htmlspecialchars_uni(fetch_trimmed_title(strip_bbcode($thread['preview'], false, true), $vboptions['threadpreview'])) ."]]></description>\r\n";
					echo "\t\t<dc:date>" . gmdate('Y-m-d\TH:i:s', $thread['dateline']) . "Z</dc:date>\r\n";
					echo "\t\t<dc:creator><![CDATA[" . $thread['postusername'] . "]]></dc:creator>\r\n";
					echo "\t</item>\r\n";
					break;
				case 'RSS2':
					echo "\r\n\t<item>\r\n";
					echo "\t\t<title>$thread[title]</title>\r\n";
					echo "\t\t<link>$vboptions[bburl]/showthread.php?t=$thread[threadid]&amp;goto=newpost</link>\r\n";
					echo "\t\t<pubDate>" . gmdate('D, d M Y H:i:s', $thread['dateline']) . " GMT</pubDate>\r\n";
					echo "\t\t<description><![CDATA[". htmlspecialchars_uni(fetch_trimmed_title(strip_bbcode($thread['preview'], false, true), $vboptions['threadpreview'])) ."]]></description>\r\n";
					echo "\t\t<category domain=\"$vboptions[bburl]/forumdisplay.php?forumid=$thread[forumid]\"><![CDATA[" . htmlspecialchars_uni($thread['forumtitle']) . "]]></category>\r\n";

					# this bit is obtuse
					echo "\t\t<author><![CDATA[example@example.com (" . $thread['postusername'] . ")]]></author>\r\n";
					echo "\t\t<guid isPermaLink=\"false\">$vboptions[bburl]/showthread.php?t=$thread[threadid]</guid>\r\n";
					echo "\t</item>\r\n";
					break;
			}
		}
	}

	switch($_REQUEST['type'])
	{
		case 'RSS1':
			echo "</rdf:RDF>";
		break;
		default:
			echo "</channel>\r\n";
			echo "</rss>";
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12189 $
|| ####################################################################
\*======================================================================*/
?>