<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
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
define('NOSHUTDOWNFUNC', 1);
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

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'forumids' => TYPE_STR,
	'type'     => TYPE_STR,
	'lastpost' => TYPE_BOOL,
));

($hook = vBulletinHook::fetch_hook('external_start')) ? eval($hook) : false;

// check to see if there is a forum preference
if ($vbulletin->GPC['forumids'] != '')
{
	$forumchoice = array();
	$forumids = explode(',', $vbulletin->GPC['forumids']);
	foreach ($forumids AS $forumid)
	{
		$forumid = intval($forumid);
		$forumperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];

		if (isset($vbulletin->forumcache["$forumid"]) AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND verify_forum_password($forumid, $vbulletin->forumcache["$forumid"]['password'], false))
		{
			$forumchoice[] = $forumid;
		}
	}

	$number_of_forums = sizeof($forumchoice);
	if ($number_of_forums == 1)
	{
		$title = $vbulletin->forumcache["$forumchoice[0]"]['title'];
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
	foreach (array_keys($vbulletin->forumcache) AS $forumid)
	{
		$forumperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
		if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'] AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])  AND verify_forum_password($forumid, $vbulletin->forumcache["$forumid"]['password'], false))
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

$cutoff = (!$vbulletin->options['externalcutoff']) ? 0 : TIMENOW - $vbulletin->options['externalcutoff'] * 86400;

// remove threads from users on the global ignore list if user is not a moderator
$globalignore = '';
if (trim($vbulletin->options['globalignore']) != '')
{
	require_once(DIR . '/includes/functions_bigthree.php');
	if ($Coventry = fetch_coventry('string'))
	{
		$globalignore = "AND postuserid NOT IN ($Coventry) ";
	}
}

$hook_query_fields = $hook_query_joins = $hook_query_where = '';
($hook = vBulletinHook::fetch_hook('external_query')) ? eval($hook) : false;

if ($forumchoice != '')
{
	// query last 15 threads from visible / chosen forums
	$threads = $db->query_read("
		SELECT thread.threadid, thread.title,
			" . ($vbulletin->GPC['lastpost'] ? "thread.lastposter AS postusername, thread.lastpost AS dateline," : "thread.postusername, thread.dateline,") . "
			forum.forumid,
			forum.title AS forumtitle,
			post.pagetext AS preview
			$hook_query_fields
		FROM " . TABLE_PREFIX . "thread AS thread
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = thread.firstpostid)
		$hook_query_joins
		WHERE 1=1
			$forumchoice
			AND thread.visible = 1
			AND post.visible = 1
			AND open <> 10
			AND " . ($vbulletin->GPC['lastpost'] ? "thread.lastpost" : "thread.dateline") . " > $cutoff
			$globalignore
			$hook_query_where
		ORDER BY " . ($vbulletin->GPC['lastpost'] ? "thread.lastpost DESC" : "thread.dateline DESC") . "
		LIMIT 15
	");
}

$threadcache = array();

while ($thread = $db->fetch_array($threads))
{ // fetch the threads
	$threadcache[] = $thread;
}

$vbulletin->GPC['type'] = strtoupper($vbulletin->GPC['type']);
switch ($vbulletin->GPC['type'])
{
	case 'JS':
	case 'XML':
	case 'RSS1':
	case 'RSS2':
		break;
	default:
		$handled = false;
		($hook = vBulletinHook::fetch_hook('external_type')) ? eval($hook) : false;
		if (!$handled)
		{
			$vbulletin->GPC['type'] = 'RSS';
		}
}

// argh
$db->close();

if ($vbulletin->GPC['type'] == 'JS' AND $vbulletin->options['externaljs'])
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
			echo "\tthreads[$threadnum] = new thread($thread[threadid], '$thread[title]', '$thread[poster]', '" . addslashes_js(vbdate($vbulletin->options['dateformat'], $thread['dateline'])) . "', '" . addslashes_js(vbdate($vbulletin->options['timeformat'], $thread['dateline'])) . "');\r\n";
		}
	}

}
else if ($vbulletin->GPC['type'] == 'XML' AND $vbulletin->options['externalxml'])
{ // XML output

	// set XML type and nocache headers
	header('Content-Type: text/xml' . ($stylevar['charset'] != '' ? '; charset=' .  $stylevar['charset'] : ''));
	header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	// print out the page header
	echo '<?xml version="1.0" encoding="' . $stylevar['charset'] . '"?>' . "\r\n";
	echo "<source>\r\n\r\n";
	echo "\t<url>" . $vbulletin->options['bburl'] . "</url>\r\n\r\n";

	// list returned threads
	if (!empty($threadcache))
	{
		foreach ($threadcache AS $thread)
		{
			echo "\t<thread id=\"$thread[threadid]\">\r\n";
			echo "\t\t<title><![CDATA[$thread[title]]]></title>\r\n";
			echo "\t\t<author><![CDATA[$thread[postusername]]]></author>\r\n";
			echo "\t\t<date>" . vbdate($vbulletin->options['dateformat'], $thread['dateline']) . "</date>\r\n";
			echo "\t\t<time>" . vbdate($vbulletin->options['timeformat'], $thread['dateline']) . "</time>\r\n";
			echo "\t</thread>\r\n";
		}
	}
	echo "\r\n</source>";
}
else if (in_array($vbulletin->GPC['type'], array('RSS', 'RSS1', 'RSS2')) AND $vbulletin->options['externalrss'])
{ // RSS output
	// setup the board title

	if (empty($title))
	{ // just show board title
		$rsstitle = htmlspecialchars_uni($vbulletin->options['bbtitle']);
	}
	else
	{ // show board title plus selection
		$rsstitle = htmlspecialchars_uni($vbulletin->options['bbtitle']) . " - $title";
	}

	// set XML type and nocache headers
	header('Content-Type: text/xml' . ($stylevar['charset'] != '' ? '; charset=' .  $stylevar['charset'] : ''));
	header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo '<?xml version="1.0" encoding="' . $stylevar['charset'] . '"?>' . "\r\n\r\n";

	# Each specs shared code is entered in full (duplicated) to make it easier to read
	switch($vbulletin->GPC['type'])
	{
		case 'RSS':
			echo '<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://my.netscape.com/publish/formats/rss-0.91.dtd">' . "\r\n";
			echo '<rss version="0.91">' . "\r\n";
			echo "<channel>\r\n";
			echo "\t<title>$rsstitle</title>\r\n";
			echo "\t<link>" . $vbulletin->options['bburl'] . "</link>\r\n";
			echo "\t<description><![CDATA[" . htmlspecialchars_uni($vbulletin->options['description']) . "]]></description>\r\n";
			echo "\t<language>$stylevar[languagecode]</language>\r\n";
		break;
		case 'RSS1':
			echo "<rdf:RDF
  xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"
  xmlns:dc=\"http://purl.org/dc/elements/1.1/\"
  xmlns:syn=\"http://purl.org/rss/1.0/modules/syndication/\"
  xmlns=\"http://purl.org/rss/1.0/\">\r\n\r\n";

			echo "\t<channel rdf:about=\"" . $vbulletin->options['bburl'] . "\">\r\n";
			echo "\t<title>$rsstitle</title>\r\n";
			echo "\t<link>" . $vbulletin->options['bburl'] . "</link>\r\n";
			echo "\t<description><![CDATA[". htmlspecialchars_uni($vbulletin->options['description']) . "]]></description>\r\n";
			echo "\t<syn:updatePeriod>hourly</syn:updatePeriod>\r\n";
			echo "\t<syn:updateFrequency>1</syn:updateFrequency>\r\n";
			echo "\t<syn:updateBase>1970-01-01T00:00Z</syn:updateBase>\r\n";
			echo "\t<dc:language>$stylevar[languagecode]</dc:language>\r\n";
			echo "\t<dc:creator>vBulletin</dc:creator>\r\n";
			echo "\t<dc:date>" . gmdate('Y-m-d\TH:i:s', TIMENOW) . "Z</dc:date>\r\n";
			echo "\t<items>\r\n";
			echo "\t<rdf:Seq>\r\n";
			echo "\t<rdf:li rdf:resource=\"" . $vbulletin->options['bburl'] . "\" />\r\n";
			echo "\t</rdf:Seq>\r\n";
			echo "\t</items>\r\n";
			echo "\t</channel>\r\n";
		break;
		case 'RSS2':
			echo "<rss version=\"2.0\">\r\n";
			echo "<channel>\r\n";
			echo "\t<title>$rsstitle</title>\r\n";
			echo "\t<link>" . $vbulletin->options['bburl'] . "</link>\r\n";
			echo "\t<description><![CDATA[" . htmlspecialchars_uni($vbulletin->options['description']) . "]]></description>\r\n";
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
			switch($vbulletin->GPC['type'])
			{
				case 'RSS':
					echo "\r\n\t<item>\r\n";
					echo "\t\t<title>$thread[title]</title>\r\n";
					echo "\t\t<link>" . $vbulletin->options['bburl'] . "/showthread.php?t=$thread[threadid]&amp;goto=newpost</link>\r\n";
					echo "\t\t<description><![CDATA[$vbphrase[forum]: " . htmlspecialchars_uni($thread['forumtitle']) . "\r\n$vbphrase[posted_by]: $thread[postusername]\r\n" .
						construct_phrase($vbphrase['post_time_x_at_y'], vbdate($vbulletin->options['dateformat'], $thread['dateline']), vbdate($vbulletin->options['timeformat'], $thread['dateline'])) .
						"]]></description>\r\n";
					echo "\t</item>\r\n";
					break;
				case 'RSS1':
					echo "\r\n\t<item rdf:about=\"" . $vbulletin->options['bburl'] . "/showthread.php?t=$thread[threadid]\">\r\n";
					echo "\t\t<title>$thread[title]</title>\r\n";
					echo "\t\t<link>" . $vbulletin->options['bburl'] . "/showthread.php?t=$thread[threadid]&amp;goto=newpost</link>\r\n";
					#echo "\t\t<content:encoded><![CDATA[". htmlspecialchars_uni(fetch_trimmed_title(strip_bbcode($thread['preview'], false, true), $vbulletin->options['threadpreview'])) ."]]></content:encoded>\r\n";
					echo "\t\t<description><![CDATA[". htmlspecialchars_uni(fetch_trimmed_title(strip_bbcode($thread['preview'], false, true), $vbulletin->options['threadpreview'])) ."]]></description>\r\n";
					echo "\t\t<dc:date>" . gmdate('Y-m-d\TH:i:s', $thread['dateline']) . "Z</dc:date>\r\n";
					echo "\t\t<dc:creator><![CDATA[" . $thread['postusername'] . "]]></dc:creator>\r\n";
					echo "\t</item>\r\n";
					break;
				case 'RSS2':
					echo "\r\n\t<item>\r\n";
					echo "\t\t<title>$thread[title]</title>\r\n";
					echo "\t\t<link>" . $vbulletin->options['bburl'] . "/showthread.php?t=$thread[threadid]&amp;goto=newpost</link>\r\n";
					echo "\t\t<pubDate>" . gmdate('D, d M Y H:i:s', $thread['dateline']) . " GMT</pubDate>\r\n";
					echo "\t\t<description><![CDATA[". htmlspecialchars_uni(fetch_trimmed_title(strip_bbcode($thread['preview'], false, true), $vbulletin->options['threadpreview'])) ."]]></description>\r\n";
					echo "\t\t<category domain=\"" . $vbulletin->options['bburl'] . "/forumdisplay.php?f=$thread[forumid]\"><![CDATA[" . htmlspecialchars_uni($thread['forumtitle']) . "]]></category>\r\n";

					# this bit is obtuse
					echo "\t\t<author><![CDATA[example@example.com (" . $thread['postusername'] . ")]]></author>\r\n";
					echo "\t\t<guid isPermaLink=\"false\">" . $vbulletin->options['bburl'] . "/showthread.php?t=$thread[threadid]</guid>\r\n";
					echo "\t</item>\r\n";
					break;
			}
		}
	}

	switch($vbulletin->GPC['type'])
	{
		case 'RSS1':
			echo "</rdf:RDF>";
		break;
		default:
			echo "</channel>\r\n";
			echo "</rss>";
	}
}

($hook = vBulletinHook::fetch_hook('external_complete')) ? eval($hook) : false;

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14595 $
|| ####################################################################
\*======================================================================*/
?>