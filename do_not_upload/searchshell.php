#!/usr/local/bin/php -q
<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('NOCOOKIES', 1);
define('NOHEADER', 1);

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$start = time();

print ("Welcome to vB3 search re-indexer\n");
print ("What is the full path to your main forum directory? ");

$forumspath = fetch_stdin();

// keep looping until they enter a path which exists
while (!is_dir($forumspath))
{
	print ("\n$forumspath is an invalid directory, please re-enter ");
	$forumspath = fetch_stdin();
}

$forumspath = preg_replace('#/$#', '', trim($forumspath));
chdir($forumspath);

if (!file_exists($forumspath . '/includes/init.php'))
{
	print ("\nInvalid forums path, exiting...");
	exit;
}

require_once('./global.php');
require_once('./includes/functions_databuild.php');

print ("Would you like to drop the search table? (y/n) ");
$dropdb = fetch_stdin();
if ($dropdb == 'yes' or $dropdb == 'y')
{
	$db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postindex");
	$db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "word");
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
	$notdone = false;

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

	$posts = $db->query_read("
		SELECT postid, post.title, post.pagetext, post.threadid, thread.title AS threadtitle
		FROM " . TABLE_PREFIX . "post AS post
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
		INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		WHERE (forum.options & 16384)
			AND post.postid >= $startat
			AND post.postid <= $stopat
		ORDER BY post.postid
	");

	while ($post = $db->fetch_array($posts))
	{
		$notdone = TRUE;

		if (empty($firstpost["$post[threadid]"]))
		{
			$getfirstpost = $db->query_first("SELECT MIN(postid) AS postid FROM " . TABLE_PREFIX . "post WHERE threadid = $post[threadid]");
			$firstpost["$post[threadid]"] = $getfirstpost['postid'];
		}

		build_post_index($post['postid'], $foruminfo, ($post['postid'] == $firstpost["$post[threadid]"]) ? 1 : 0, $post);

		print ("Processed post: $post[postid]\n");
		flush();
	}

	$startat += $perpage;
}

print_postindex_exec_time($start);

// ###################### Start getinput #######################
function fetch_stdin()
{
	static $fp;

	if ($fp)
	{
		$input = fgets($fp, 255);
	}
	else
	{
		$fp = fopen('php://stdin', 'r');
		$input = fgets($fp, 255);
	}

	return str_replace(array("\n", "\r"), array('', ''), $input);
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
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
