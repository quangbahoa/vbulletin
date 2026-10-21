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

require_once('./includes/functions_databuild.php');

// ###################### Start getsearchposts #######################
function getsearchposts(&$query, $showerrors = 1)
{
	global $vboptions, $stylevar, $DB_site, $searchthread, $searchthreadid, $titleonly;

	if (empty($query))
	{
		return '';
	}

	// replace common search syntax errors
	$query = trim(preg_replace('/ ([\w\*]+) (\+|and) ([\w\*]+) /siU', ' +\1 +\3 ', " $query "));
	$qu_find = array(
		'  ',	// double spaces to single spaces
		'+ ',	// replace '+ ' with '+'
		'- ',	// replace '- ' with '-'
		'or ',	// remove 'OR '
		'and '	// replace 'AND ' with '+'
	);
	$qu_replace = array(
		' ',	// double spaces to single spaces
		'+',	// replace '+ ' with '+'
		'-',	// replace '- ' with '-'
		'',		// remove 'OR '
		'+' 	// replace 'AND ' with '+'
	);
	$query = str_replace($qu_find, $qu_replace, $query);

	// escape MySQL wildcards
	$qu_find = array(
		'%',	// escape % symbols
		'_' 	// escape _ symbols
	);
	$qu_replace = array(
		'\%',	// escape % symbols
		'\_' 	// escape _ symbols
	);
	if ($vboptions['allowwildcards'])
	{
		// strip duplicate * signs
		$query = preg_replace('/\*{2,}/s', '*', $query);

		$qu_find[] = '*';
		$qu_replace[] = '%';
	}
	$querywc = str_replace($qu_find, $qu_replace, $query);

	// get individual words
	$words = explode(' ', strtolower(addslashes($querywc)));

	$havewords = 0;
	$searchables = 0;

	$wordids = 'wordid IN (0';
	$wild = 0;
	foreach ($words AS $word)
	{
		if (!is_index_word($word))
		{
			// this is a BAD stop word, so strip don't process it as it will most likely
			// end up just screwing up the search
			continue;
		}
		$firstchar = substr($word,0,1);

		if (strpos($word, '%') !== false)
		{
			$wild++;
		}
		switch ($firstchar)
		{

			case '+':
				// this is a required term
				$state = 1;
				$word = substr($word, 1);
				break;
			case '-':
				// this is a blocked term
				$state = -1;
				$word = substr($word, 1);
				break;
			default:
				// this is an optional term
				$state = 0;
				break;
		}

		// the following is already checked in is_index_word() and this prevents
		// short words in $goodwords from being found

		$searchables++;

		$sqlwords = $DB_site->query("
			SELECT wordid, title
			FROM " . TABLE_PREFIX . "word
			WHERE title LIKE '" . addslashes($word) . "'
		");
		if ($DB_site->num_rows($sqlwords) == 0)
		{ // no words found
			if ($state == 1)
			{ // word is a required term
				if ($showerrors)
				{
					eval(print_standard_error('error_searchnoresults'));
				}
				else
				{
					return '';
				}
			}
		}
		else
		{ // some words found
			while($thisword = $DB_site->fetch_array($sqlwords))
			{
				if ($wild)
				{
					$wordparts['2']["$state"]["$wild"]["$thisword[title]"] = $thisword['wordid'];
				}
				else
				{
					$wordparts["$state"]["$thisword[title]"] = $thisword['wordid'];
				}
				$havewords = 1;
				$wordids .= ',' . intval($thisword['wordid']);
			}
			$DB_site->free_result($sqlwords);
		}
	}

	if (!$havewords)
	{
		if ($showerrors)
		{
			eval(print_standard_error('error_searchnoresults'));
		}
		else
		{
			return '';
		}
	}

	$wordids .= ')';

	$wordlists = array();
	$postscores = array();

	// ### GET POSTS THAT MATCH QUERY ##############################################
	if ($titleonly)
	{
		$intitle = ' AND intitle <> 0';
	}
	else
	{
		$intitle = '';
	}

	$threadids = array();

	$posts = $DB_site->query("
		SELECT postid, wordid,
			CASE intitle
				WHEN 0 THEN score
				WHEN 1 THEN score + $vboptions[posttitlescore]
				WHEN 2 THEN score + $vboptions[threadtitlescore] + $vboptions[posttitlescore]
			ELSE score
			END AS score
		FROM " . TABLE_PREFIX . "postindex" . iif($searchthread, "
		INNER JOIN " . TABLE_PREFIX . "post USING (postid)
		INNER JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)") . "
		WHERE $wordids $intitle
		" . iif($searchthread, " AND thread.threadid = $searchthreadid")
	);
	while($post = $DB_site->fetch_array($posts))
	{
		$wordlists[$post['postid']] .= " ,$post[wordid],";
		$postscores[$post['postid']] += $post['score'];
	}

	if (!$wordlists)
	{
		if ($showerrors)
		{
			eval(print_standard_error('error_searchnoresults'));
		}
		else
		{
			return '';
		}
	}

	$postids = ' AND postid IN (0';

	foreach ($wordlists AS $postid => $wordlist)
	{
		// go through the words found for each post

		// look at words we don't want:
		if (is_array($wordparts[-1]))
		{
			$wordfound = 0;
			foreach ($wordparts[-1] AS $wordid)
			{
				if (strpos($wordlist, ",$wordid,"))
				{
					// uh oh, bad word found, let's get out of here!
					unset($wordlists[$postid]);
					unset($postscores[$postid]);
					$wordfound = 1;
					break;
				}
			}

			if ($wordfound)
			{
				// bad word was found, don't go on with this post
				continue;
			}
		}

		// look at words we do want:
		if (is_array($wordparts[1]))
		{
			$wordnotfound = 0;
			foreach ($wordparts[1] AS $wordid)
			{
				if (!strpos($wordlist, ",$wordid,"))
				{
					// uh oh, word not found, let's get out of here!
					unset($wordlists[$postid]);
					unset($postscores[$postid]);
					$wordnotfound = 1;
					break;
				}
			}
			if ($wordnotfound)
			{
				// word was not found, don't go on with this post
				continue;
			}
		}

		// look at wild words
		if (is_array($wordparts['2']))
		{
			//required wild words
			if (is_array($wordparts['2']['1']))
			{
				$wordsfound = 1;
				foreach ($wordparts['2']['1'] AS $wildsearch)
				{
					$wordfound = 0;
					foreach ($wildsearch AS $wordid)
					{
						if (strpos($wordlist, ",$wordid,"))
						{
							$wordfound = 1;
							break;
						}
					}
					if (!$wordfound)
					{
						$wordsfound = 0;
						break;
					}
				}
				if (!$wordsfound)
				{
					// word was not found, don't go on with this post
					unset($wordlists[$postid]);
					unset($postscores[$postid]);
					continue;
				}
			}

			//excluded wild words
			if (is_array($wordparts['2']['-1']))
			{
				$wordsfound = 0;
				foreach ($wordparts['2']['-1'] AS $wildsearch)
				{
					$wordfound = 0;
					foreach ($wildsearch AS $wordid)
					{
						if (strpos($wordlist, ",$wordid,"))
						{
							$wordfound = 1;
							break;
						}
					}
					if ($wordfound)
					{
						$wordsfound = 1;
						break;
					}
				}
				if ($wordsfound)
				{
					// word was not found, don't go on with this post
					unset($wordlists[$postid]);
					unset($postscores[$postid]);
					continue;
				}
			}
		}

		// look at words we do want that are wildcards

		$postids .= ',' . intval($postid);

	}

	$postids .= ')';

	// returns a lot of useless stuff right now -- similar threads matching only uses the scores now. I was originally
	// planning on having the searching routine be a bit more complex than it is now
	return array('wordlists' => $wordlists, 'scores' => $postscores, 'wordparts' => $wordparts, 'searchables' => $searchables, 'postids' => $postids, 'threadids' => $threadids);

}

// ###################### Start getsimilarthreads #######################
function fetch_similar_threads($threadtitle, $threadid = 0)
{
	global $vboptions, $DB_site;

	if ($vboptions['fulltextsearch'])
	{
		$safetitle = addslashes($threadtitle);
		$threads = $DB_site->query("
			SELECT threadid, MATCH(title) AGAINST ('$safetitle') AS score
			FROM " . TABLE_PREFIX . "thread
			WHERE MATCH(title) AGAINST ('$safetitle')
				AND open <> 10
				" . iif($threadid, " AND threadid <> $threadid") . "
			LIMIT 5
		");
		while ($thread = $DB_site->fetch_array($threads))
		{
			// this is an arbitrary number but items less then 4 - 5 seem to be rather unrelated
			if ($thread['score'] > 4)
			{
				$similarthreads .= ", $thread[threadid]";
			}
		}

		$DB_site->free_result($threads);

		return substr($similarthreads, 2);
	}

	// take out + and - because they have special meanings in a search
	$threadtitle = str_replace('+', ' ', $threadtitle);
	$threadtitle = str_replace('-', ' ', $threadtitle);
	$threadtitle = fetch_postindex_text(trim($threadtitle));

	$retval = getsearchposts($threadtitle, 0);
	if (!$retval OR sizeof($retval['scores']) == 0)
	{
		return '';
	}

	if (sizeof($retval['scores']) < 20000)
	{
		// this version seems to die on the sort when a lot of posts are return
		arsort($retval['scores']);	// biggest scores first

		foreach ($retval['scores'] AS $postid => $score)
		{
			if (($score / $retval['searchables']) < $vboptions['similarthreadthreshold'] OR $numposts >= $vboptions['maxresults'])
			{
				break;
			}
			else
			{
				$similarposts .= ', ' . intval($postid);
				$numposts++;
			}
		}
	}
	else
	{
		$scorelist = array();
		$postlist  = array();
		$maxarrsize = min(40, sizeof($retval['scores']));
		for ($i = 0; $i < $maxarrsize; $i++)
		{
			$scorelist[$i] = -1;
			$postlist[$i] = 0;
		}
		foreach ($retval['scores'] AS $postid => $score)
		{
			if (($score / $retval['searchables']) < $vboptions['similarthreadthreshold'])
			{
				continue;
			}
			$arraymin = min($scorelist);
			if ($score > $arraymin)
			{
				$i = 0;
				foreach ($scorelist AS $thisscore)
				{
					if ($thisscore == $arraymin)
					{
						$scorelist["$i"] = $score;
						$postlist["$i"] = $postid;
						break;
					}
					$i++;
				}
			}
		}
		foreach ($postlist AS $postid)
		{
			if ($postid)
			{
				$numposts++;
				$similarposts .= ', ' . intval($postid);
			}
		}
	}

	if ($numposts == 0)
	{
		return '';
	}

	$sim = $DB_site->query("
		SELECT DISTINCT thread.threadid
		FROM " . TABLE_PREFIX . "post AS post
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
		WHERE postid IN (0$similarposts) " . iif($threadid, " AND post.threadid <> $threadid") . "
		ORDER BY ($numposts - FIELD(post.postid $similarposts )) DESC
		LIMIT 5
	");
	$similarthreads = '';
	while ($simthrd = $DB_site->fetch_array($sim))
	{
		$similarthreads .= ", $simthrd[threadid]";
	}
	$DB_site->free_result($sim);

	return substr($similarthreads, 2);
}

// #############################################################################
// checks if word is goodword / badword / too long / too short
function verify_word_allowed(&$word)
{
	global $vboptions, $phrasequery;

	if ($vboptions['fulltextsearch'])
	{
		return true;
	}

	$wordlower = strtolower($word);

	// check if the word contains wildcards
	if (strpos($wordlower, '*') !== false)
	{
		// check if wildcards are allowed
		if ($vboptions['allowwildcards'])
		{
			// check the length of the word with all * characters removed
			// and make sure it's at least (minsearchlength - 1) characters long
			// in order to prevent searches like *a**... which would be bad
			if (strlen(str_replace('*', '', $wordlower)) < ($vboptions['minsearchlength'] - 1))
			{
				// word is too short
				$word = htmlspecialchars_uni($word);
				eval(print_standard_error('searchinvalidterm'));
			}
			else
			{
				// word is of valid length
				return true;
			}
		}
		else
		{
			// wildcards are not allowed - error
			$word = htmlspecialchars_uni($word);
			eval(print_standard_error('searchinvalidterm'));
		}
	}
	// check if this is a word that would be indexed
	else if ($wordokay = is_index_word($word))
	{
		return true;
	}
	// something was wrong with the word... find out what
	else
	{
		// word is a bad word (common, too long, or too short; don't search on it)
		return false;
	}
}

// #############################################################################
// makes a word or phrase safe to put into a LIKE sql condition
function sanitize_word_for_sql($word)
{
	static $find, $replace;

	if (!is_array($find))
	{
		$find = array(
			'\\\*',	// remove escaped wildcard
			'%'	// escape % symbols
			//'_' 	// escape _ symbols
		);
		$replace = array(
			'*',	// remove escaped wildcard
			'\%'	// escape % symbols
			//'\_' 	// escape _ symbols
		);
	}

	// replace MySQL wildcards
	$word = str_replace($find, $replace, addslashes($word));

	return $word;
}

// #############################################################################
// gets a list of forums from the user's selection, based on permissions
function fetch_search_forumids(&$forumchoice, $childforums = 0)
{
	global $DB_site, $bbuserinfo, $vboptions, $stylevar, $display, $forumcache;

	// make sure that $forumchoice is an array
	if (!is_array($forumchoice))
	{
		$forumchoice = array($forumchoice);
	}

	// initialize the $forumids for return by this function
	$forumids = array();

	foreach ($forumchoice AS $forumid)
	{
		// get subscribed forumids
		if ($forumid === 'subscribed' AND $bbuserinfo['userid'] != 0)
		{
			DEVDEBUG("Querying subscribed forums for $bbuserinfo[username]");
			$sforums = $DB_site->query("
				SELECT forumid FROM " . TABLE_PREFIX . "subscribeforum
				WHERE userid = $bbuserinfo[userid]
			");
			if ($DB_site->num_rows($sforums) == 0)
			{
				// no subscribed forums
				eval(print_standard_error('not_subscribed_to_any_forums'));
			}
			while ($sforum = $DB_site->fetch_array($sforums))
			{
				$forumids["$sforum[forumid]"] .= $sforum['forumid'];
			}
			unset($sforum);
			$DB_site->free_result($sforums);
		}
		// get a single forumid or no forumid at all
		else
		{
			$forumid = intval($forumid);
			if (isset($forumcache["$forumid"]) AND $forumcache["$forumid"]['link'] == '')
			{
				$forumids["$forumid"] = $forumid;
			}
		}
	}

	// now if there are any forumids we have to query, work out their child forums
	if (empty($forumids))
	{
		$forumchoice = 0;
		$display['forums'] = array();
	}
	else
	{
		// set $forumchoice to show the returned forumids
		$forumchoice = implode(',', $forumids);

		// put current forumids into the display table
		$display['forums'] = $forumids;

		// get child forums of selected forums
		if ($childforums)
		{
			require_once('./includes/functions_misc.php');
			foreach ($forumids AS $forumid)
			{
				$children = fetch_child_forums($forumid, 'ARRAY');
				if (!empty($children))
				{
					foreach ($children AS $childid)
					{
						$forumids["$childid"] = $childid;
					}
				}
				unset($children);
			}
		}
	}

	// return the array of forumids
	return $forumids;
}

// #############################################################################
// sort search results
function sort_search_items($searchclause, $showposts, $sortby, $sortorder)
{
	global $DB_site;

	$itemids = array();

	// order threads
	if ($showposts == 0)
	{
		$items = $DB_site->query("
			SELECT threadid, visible FROM " . TABLE_PREFIX . "thread AS thread" . iif($sortby == 'forum.title', "
			INNER JOIN " . TABLE_PREFIX . "forum AS forum USING(forumid)") . "
			WHERE $searchclause
			ORDER BY $sortby $sortorder
		");
		while ($item = $DB_site->fetch_array($items))
		{
			if ($item['visible'] == 1)
			{
				$itemids[] = $item['threadid'];
			}
		}
	}
	// order posts
	else
	{
		$items = $DB_site->query("
			SELECT postid FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)" . iif($sortby == 'forum.title', "
			INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)") . "
			WHERE $searchclause
			ORDER BY $sortby $sortorder
		");
		while ($item = $DB_site->fetch_array($items))
		{
			$itemids[] = $item['postid'];
		}
	}

	// free SQL result
	unset($item);
	$DB_site->free_result($items);

	return $itemids;

}

// #############################################################################
// remove common syntax errors in search query string
function sanitize_search_query($query)
{
	$qu_find = array(
		'/\s+(\s*OR\s+)+/si',	// remove multiple OR strings
		'/^\s*(OR|AND|NOT|-)\s+/siU', 		// remove 'OR/AND/NOT/-' from beginning of query
		'/\s+(OR|AND|NOT|-)\s*$/siU', 		// remove 'OR/AND/NOT/-' from end of query
		'/\s+(-|NOT)\s+/si',	// remove trailing whitespace on '-' controls and translate 'not'
		'/\s+OR\s+/siU',		// capitalize ' or '
		'/\s+AND\s+/siU',		// remove ' and '
		'/\s+(-)+/s',			// remove ----word
		'/\s+/s',				// whitespace to single space
	);
	$qu_replace = array(
		' OR ',			// remove multiple OR strings
		'', 			// remove 'OR/AND/NOT/-' from beginning of query
		'',				// remove 'OR/AND/NOT/-' from end of query
		' -',			// remove trailing whitespace on '-' controls and translate 'not'
		' OR ',			// capitalize 'or '
		' ',			// remove ' and '
		' -',			// remove ----word
		' ',			// whitespace to single space
	);
	$query = trim(preg_replace($qu_find, $qu_replace, " $query "));

	// show error if query logic contains (apple OR -pear) or (-apple OR pear)
	if (strpos($query, ' OR -') !== false OR preg_match('/ -\w+ OR /siU', $query, $syntaxcheck))
	{
		eval(print_standard_error('Search terms contain invalid syntax: <b>-word1 OR word2</b> / <b>word1 OR -word2</b>', 0));
	}

	// check that after all our syntax correction we still have a query to work with
	if ($query == '')
	{
		eval(print_standard_error('searchspecifyterms'));
	}

	// check that we have some words that are NOT boolean controls
	$boolwords = array('AND', 'OR', 'NOT', '-AND', '-OR', '-NOT');
	foreach (explode(' ', strtoupper($query)) AS $key => $word)
	{
		if (! in_array($word, $boolwords))
		{
			// word is good - return the query
			return $query;
		}
	}

	// no good words found - show no search terms error
	eval(print_standard_error('searchspecifyterms'));

}

// #############################################################################
// fetch the score for a search result
function fetch_search_item_score(&$item, $currentscore)
{
	global $vboptions;
	global $replyscore, $viewscore, $ratescore;

	// don't prejudice un-rated threads!
	if ($item['votenum'] == 0)
	{
		$item['rating'] = 3;
	}
	else
	{
		$item['rating'] = $item['votetotal'] / $item['votenum'];
	}

	$replyscore = $vboptions['replyfunc']($item['replycount']) * $vboptions['replyscore'];
	$viewscore = $vboptions['viewfunc']($item['views']) * $vboptions['viewscore'];
	$ratescore = $vboptions['ratefunc']($item['rating']) * $vboptions['ratescore'];

	return $currentscore + $replyscore + $viewscore + $ratescore;
}

// #############################################################################
// fetch the date scores for search results
function fetch_search_date_scores(&$datescores, &$itemscores, $mindate, $maxdate)
{
	global $vboptions;

	$datespread = $maxdate - $mindate;
	if ($datespread > 0 AND $vboptions['datescore'] != 0)
	{
		foreach ($datescores AS $itemid => $dateline)
		{
			$datescore = ($dateline - $mindate) / $datespread * $vboptions['datescore'];
			$itemscores["$itemid"] += $datescore;
		}
	}
	unset($datescores);
}

// #############################################################################
// fetch array of IDs of forums to display in the search form
function fetch_search_forumids_array($parentid = -1, $depthmark = '')
{
	global $forumcache, $bbuserinfo, $_FORUMOPTIONS, $searchforumids;
	static $iforumcache;

	if ($parentid == -1)
	{
		$searchforumids = array();
		$iforumcache = array();
		foreach ($forumcache AS $forumid => $forum)
		{
			$iforumcache["$forum[parentid]"]["$forumid"] = &$forumcache["$forumid"];
		}
	}

	if (is_array($iforumcache["$parentid"]))
	{
		foreach ($iforumcache["$parentid"] AS $forumid => $forum)
		{
			$fp = $bbuserinfo['forumpermissions']["$forumid"];
			if ($forum['displayorder'] != 0
				/*AND $forum['link'] == ''*/
				AND ($fp & CANVIEW)
				AND ($fp & CANSEARCH)
				/*AND ($forum['options'] & $_FORUMOPTIONS['indexposts'])*/
				AND ($forum['options'] & $_FORUMOPTIONS['active'])
				AND verify_forum_password($forum['forumid'], $forum['password'], false)
			)
			{
				$forumcache["$forumid"]['depthmark'] = $depthmark;
				$searchforumids[] = $forumid;
				fetch_search_forumids_array($forumid, $depthmark . FORUM_PREPEND);
			}
		}
	}
}

// ###################### Start process_quote_removal #######################
function process_quote_removal($text, $cancelwords)
{
	$lowertext = strtolower($text);
	foreach ($cancelwords AS $word)
	{
		$word = str_replace('*', '', strtolower($word));
		if (strpos($lowertext, $word) !== false)
		{
			// we found a highlight word -- keep the quote
			return "\n" . str_replace('\"', '"', $text) . "\n";
		}
	}
	return '';
}

// #############################################################################
// used in ranking system:
function none($v)
{
	return $v;
}

function safelog($v)
{
	return log(abs($v)+1);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11479 $
|| ####################################################################
\*======================================================================*/
?>