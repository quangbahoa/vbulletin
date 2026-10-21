#!/usr/local/bin/php -q
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

$start = time();

print ("Welcome to vB3 search re-indexer\n");
print ("What is the path to your includes folder? ");

$includepath = fetch_stdin();

// keep looping until they enter a path which exists
while (!is_dir($includepath))
{
	print ("\n$includepath is an invalid directory, please re-enter ");
	$includepath = fetch_stdin();
}

chdir("$includepath");
// tidy up variables dont need this any more
unset($includepath);

if (!file_exists('./config.php'))
{
	print ("\nInvalid includes path, exiting...");
	exit;
}
require_once ("./config.php");

if (empty($dbpassword))
{
	mysql_connect($servername, $dbusername);
}
else
{
	mysql_connect($servername, $dbusername, $dbpassword);
}

mysql_select_db($dbname);

define( 'TABLE_PREFIX' , $tableprefix );

//get vboptions
$vboptions = mysql_query_first("SELECT title, data FROM " . TABLE_PREFIX . "datastore WHERE title='options'");
$vboptions = unserialize($vboptions['data']);

print ("Would you like to drop the search table? (y/n) ");
$dropdb = fetch_stdin();
if ($dropdb == 'yes' or $dropdb == 'y')
{
	mysql_query("DELETE FROM " . TABLE_PREFIX . "postindex");
	mysql_query("DELETE FROM " . TABLE_PREFIX . "word");
	print ("The tables postindex and word are now empty\n");
}
// tidy up variables dont need this any more
unset($dropdb);

print ("What post number would you like to start at? (0) ");
$startat = intval(fetch_stdin());

print ("What post number would you like to end at? (none) ");
$endat = intval(fetch_stdin());

print ("Posts to process per cycle? (100000) ");
$perpage = intval(fetch_stdin());
if (!$perpage)
{
	$perpage = 100000;
}

$foruminfo = array('indexposts' => 1);
$firstpost = array();

$notdone = TRUE;

while ($notdone)
{

	$notdone = FALSE;

	$stopat = $startat + $perpage;
	if ($endat)
	{
		if ($stopat > $endat)
		{
			$stopat = $endat;
		}
		if ($startat >= $endat)
		{
			continue;
		}
	}

	$posts = mysql_query("
		SELECT postid, post.title, post.pagetext, post.threadid, thread.title AS threadtitle
		FROM " . TABLE_PREFIX . "post AS post
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		WHERE (forum.options & 16384)
			AND post.postid >= $startat
			AND post.postid <= $stopat
		ORDER BY post.postid
	");

	while ($post = mysql_fetch_array($posts))
	{
		$notdone = TRUE;

		if (empty($firstpost["$post[threadid]"]))
		{
			$getfirstpost = mysql_query_first("SELECT MIN(postid) AS postid FROM " . TABLE_PREFIX . "post WHERE threadid = $post[threadid]");
			$firstpost["$post[threadid]"] = $getfirstpost['postid'];
		}

		build_post_index($post['postid'], $foruminfo, iif($post['postid'] == $firstpost["$post[threadid]"], 1, 0), $post);

		print ("Processed post: $post[postid]\n");
		flush();
	}

	$startat += $perpage;
}

print_postindex_exec_time($start);

// ###################### Start getinput #######################
function fetch_stdin()
{
	$fp = fopen('php://stdin', 'r');
	$input = fgets($fp, 255);
	fclose($fp);

	return str_replace(array("\n", "\r"), array('', ''), $input);
}

// ###################### Start mysql_query_first #######################
function mysql_query_first($query)
{
	$query = mysql_query($query);
	return mysql_fetch_array($query);
}

// ###################### Start indexpost #######################
function build_post_index($postid, $foruminfo, $firstpost = -1, $post = false)
{
	global $vboptions;

	if ($vboptions['fulltextsearch'])
	{
		return;
	}

	if ($foruminfo['indexposts'])
	{
		global $DB_site, $vboptions;
		static $firstpst;

		if (is_array($post))
		{
			if (isset($post['threadtitle']))
			{
				$threadinfo = array('title' => $post['threadtitle']);
			}
		}
		else
		{
			$post = mysql_query_first("
				SELECT postid, post.title, pagetext, post.threadid, thread.title AS threadtitle
				FROM " . TABLE_PREFIX . "post AS post
				INNER JOIN " . TABLE_PREFIX . "thread AS thread USING(threadid)
				WHERE postid = $postid
			");
			$threadinfo = array('title' => $post['threadtitle']);
		}

		if (isset($firstpst["$post[threadid]"]))
		{
			if ($firstpst["$post[threadid]"] == $postid)
			{
				$firstpost = 1;
			}
			else
			{
				$firstpost = 0;
			}
		}

		if ($firstpost == -1)
		{
			$getfirstpost = mysql_query_first("
				SELECT MIN(postid) AS postid
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = " . intval($post['threadid'])
			);
			if ($getfirstpost['postid'] == $postid)
			{
				$firstpost = 1;
			}
			else
			{
				$firstpost = 0;
			}
		}


		$allwords = '';

		if ($firstpost)
		{
			if (!is_array($threadinfo))
			{
				$threadinfo = mysql_query_first("
					SELECT title
					FROM " . TABLE_PREFIX . "thread
					WHERE threadid = $post[threadid]
				");
			}

			$firstpst["$post[threadid]"] = $postid;

			$words = fetch_postindex_text($threadinfo['title']);
			$allwords .= $words;
			$wordarray = explode(' ', $words);
			foreach ($wordarray AS $word)
			{
				#$scores["$word"] += $vboptions['threadtitlescore'];
				$intitle["$word"] = 2;
				$scores["$word"]++;
			}
		}

		$words = fetch_postindex_text($post['title']);
		$allwords .= ' ' . $words;
		$wordarray = explode(' ', $words);

		foreach ($wordarray AS $word)
		{
			#$scores["$word"] += $vboptions['posttitlescore'];
			if (empty($intitle["$word"]))
			{
				$intitle["$word"] = 1;
			}
			$scores["$word"]++;
		}

		$words = fetch_postindex_text($post['pagetext']);
		$allwords .= ' ' . $words;
		$wordarray = explode(' ', $words);
		foreach ($wordarray AS $word)
		{
			$scores["$word"]++;
		}

		$getwordidsql = "title IN ('" . str_replace(" ", "','", $allwords) . "')";
		$words = mysql_query("SELECT wordid, title FROM " . TABLE_PREFIX . "word WHERE $getwordidsql");
		while ($word = mysql_fetch_array($words))
		{
			$word['title'] = strtolower($word['title']);
			$wordcache["$word[title]"] = $word['wordid'];
		}
		mysql_free_result($words);

		$insertsql = '';
		$newwords = '';
		$newtitlewords = '';

		foreach ($scores AS $word => $score)
		{
			if (!is_index_word($word))
			{
				unset($scores["$word"]);
				continue;
			}

			// prevent score going over 255 for overflow control
			if ($score > 255)
			{
				$scores["$word"] = 255;
			}
			// make sure intitle score is set
			$intitle["$word"] = intval($intitle["$word"]);

			if ($word)
			{
				if (isset($wordcache["$word"]))
				{ // Does this word already exist in the word table?
					$insertsql .= ", (" . addslashes($wordcache["$word"]) . ", $postid, $score, $intitle[$word])"; // yes so just add a postindex entry for this post/word
					unset($scores["$word"], $intitle["$word"]);
				}
				else
				{
					$newwords .= $word . ' '; // No so add it to the word table
				}
			}
		}

		if (!empty($insertsql))
		{
			$insertsql = substr($insertsql, 1);
			mysql_query("
				REPLACE INTO " . TABLE_PREFIX . "postindex
				(wordid, postid, score, intitle)
				VALUES
				$insertsql
			");
		}

		$newwords = trim($newwords);
		if ($newwords)
		{
			$insertwords = "('" . str_replace(" ", "'),('", $newwords) . "')";
			mysql_query("INSERT IGNORE INTO " . TABLE_PREFIX . "word (title) VALUES $insertwords");
			$selectwords = "title IN ('" . str_replace(" ", "','", $newwords) . "')";

			$scoressql = 'CASE title';
			foreach ($scores AS $word => $score)
			{
				$scoressql .= " WHEN '" . addslashes($word) . "' THEN $score";
			}
			$scoressql .= ' ELSE 1 END';

			$titlesql = 'CASE title';
			foreach($intitle AS $word => $intitlescore)
			{
				$titlesql .= " WHEN '" . addslashes($word) . "' THEN $intitlescore";
			}
			$titlesql .= ' ELSE 0 END';

			mysql_query("
				INSERT IGNORE INTO " . TABLE_PREFIX . "postindex
				(wordid, postid, score, intitle)
				SELECT DISTINCT wordid, $postid, $scoressql, $titlesql
				FROM " . TABLE_PREFIX . "word
				WHERE $selectwords
			");
		}
	}
}

// ###################### Start searchtextstrip #######################
function fetch_postindex_text($text)
{
	static $find, $replace;
	global $DB_site;

	// remove all bbcode tags
	$text = strip_bbcode($text);

	// make lower case and pad with spaces
	//$text = strtolower(" $text ");
	$text = " $text ";

	if (!is_array($find))
	{
		$find = array(
			'#[()"\'!\#{};]|\\\\|:(?!//)#s',			// allow through +- for boolean operators and strip colons that are not part of URLs
			'#([.,?&/_]+)( |\.)#s',						// \?\&\,
			'#\s+(-|\+)+([^\s]+)#si',					// remove leading +/- characters
			'#(\s?\w*\*\w*)#s',							// remove words containing asterisks
			'#\s+#s',									// whitespace to space
		);
		$replace = array(
			'',		// allow through +- for boolean operators and strip colons that are not part of URLs
			' ',	// \?\&\,
			' \2',	// remove leading +/- characters
			'',		// remove words containing asterisks
			' ',	// whitespace to space
		);
	}

	$text = strip_tags($text); // clean out HTML as it's probably not going to be indexed well anyway

	// use regular expressions above
	$text = preg_replace($find, $replace, $text);

	return trim(strtolower($text));
}

// ###################### Start stripvbcode #######################
function strip_bbcode($message, $stripquotes = false, $fast_and_dirty = false)
{
	$find = array();
	$replace = array();

	if ($stripquotes)
	{
		// [quote=username] and [quote]
		$message = preg_replace('#\[quote(=(&quot;|"|\'|).*\\2)?\]((?>[^\[]+?|(?R)|.))*\[/quote\]#siU', '', $message);
	}

	// a really quick and rather nasty way of removing vbcode
	if ($fast_and_dirty)
	{
		// any old thing in square brackets
		$find[] = '#\[.*/?\]#siU';
		$replace[] = '';

		$message = preg_replace($find, $replace, $message);
	}
	// the preferable way to remove vbcode
	else
	{
		// simple links
		$find[] = '#\[(email|url)=("??)(.+)\\2\]\\3\[/\\1\]#siU';
		$replace[] = '\3';

		// named links
		$find[] = '#\[(email|url)=("??)(.+)\\2\](.+)\[/\\1\]#siU';
		$replace[] = '\4 (\3)';

		// replace links (and quotes if specified) from message
		$message = preg_replace($find, $replace, $message);

		// strip out all other instances of [x]...[/x]
		while(preg_match_all('#\[([\w]+?)[^\]]*\](.*)(\[/\1\])#siU', $message, $regs))
		{
			foreach($regs[0] AS $key => $val)
			{
				$message  = str_replace($val, $regs[2]["$key"], $message);
			}
		}
		$message = str_replace('[*]', ' ', $message);
	}

	return trim($message);
}

// ###################### Start indexword #######################
function is_index_word($word)
{
	global $vboptions, $badwords, $goodwords;

	require_once('./searchwords.php'); // get the stop word list

	// is the word in the goodwords array?
	if (in_array(strtolower($word), $goodwords))
	{
		return 1;
	}
	else
	{
		// is the word outside the min/max char lengths for indexing?
		$wordlength = strlen($word);
		if ($wordlength < $vboptions['minsearchlength'] or $wordlength > $vboptions['maxsearchlength'])
		{
			return 0;
		}
		// is the word a common/bad word?
		else if (in_array(strtolower($word), $badwords))
		{
			return false;
		}
		// word is good
		else
		{
			return 1;
		}
	}

}

// ###################### Start iif #######################
function iif($expression, $returntrue, $returnfalse = '')
{
	return ( $expression ? $returntrue : $returnfalse );
}

// ###################### Start execution time #######################
function print_postindex_exec_time($starttime)
{
	$seconds = time() - $starttime;
	$d['h'] = floor($seconds/3600);
	$d['m'] = str_pad( floor( ($seconds - ($d['h']*3600)) / 60 ), 2, 0, STR_PAD_LEFT);
	$d['s'] = str_pad($seconds % 60, 2, 0, STR_PAD_LEFT);

	print ("Index complete after $d[h] hours, $d[m] minutes and $d[s] seconds\n");
	exit;
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9724 $
|| ####################################################################
\*======================================================================*/
?>