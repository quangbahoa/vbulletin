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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/functions_databuild.php');

// ###################### Start getsearchposts #######################
function getsearchposts(&$query, $showerrors = 1)
{
	global $stylevar, $vbulletin, $searchthread, $searchthreadid, $titleonly;

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
	if ($vbulletin->options['allowwildcards'])
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

		$sqlwords = $vbulletin->db->query_read_slave("
			SELECT wordid, title
			FROM " . TABLE_PREFIX . "word
			WHERE title LIKE '" . $vbulletin->db->escape_string($word) . "'
		");
		if ($vbulletin->db->num_rows($sqlwords) == 0)
		{ // no words found
			if ($state == 1)
			{ // word is a required term
				if ($showerrors)
				{
					eval(standard_error(fetch_error('searchnoresults', $displayCommon)));
				}
				else
				{
					return '';
				}
			}
		}
		else
		{ // some words found
			while($thisword = $vbulletin->db->fetch_array($sqlwords))
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
			$vbulletin->db->free_result($sqlwords);
		}
	}

	if (!$havewords)
	{
		if ($showerrors)
		{
			eval(standard_error(fetch_error('searchnoresults', $displayCommon)));
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

	$posts = $vbulletin->db->query_read_slave("
		SELECT postid, wordid,
			CASE intitle
				WHEN 0 THEN score
				WHEN 1 THEN score + " . $vbulletin->options['posttitlescore'] . "
				WHEN 2 THEN score + " . $vbulletin->options['threadtitlescore'] . " + " . $vbulletin->options['posttitlescore'] . "
			ELSE score
			END AS score
		FROM " . TABLE_PREFIX . "postindex" . iif($searchthread, "
		INNER JOIN " . TABLE_PREFIX . "post USING (postid)
		INNER JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)") . "
		WHERE $wordids $intitle
		" . iif($searchthread, " AND thread.threadid = $searchthreadid")
	);
	while($post = $vbulletin->db->fetch_array($posts))
	{
		$wordlists[$post['postid']] .= " ,$post[wordid],";
		$postscores[$post['postid']] += $post['score'];
	}

	if (!$wordlists)
	{
		if ($showerrors)
		{
			eval(standard_error(fetch_error('searchnoresults', $displayCommon)));
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
	global $vbulletin;

	if ($vbulletin->options['fulltextsearch'])
	{
		$hook_query_joins = $hook_query_where = '';
		$similarthreads = null;

		($hook = vBulletinHook::fetch_hook('search_similarthreads_fulltext')) ? eval($hook) : false;

		if ($similarthreads !== null)
		{
			return $similarthreads;
		}

		$similarthreads = '';

		$safetitle = $vbulletin->db->escape_string($threadtitle);
		$threads = $vbulletin->db->query_read_slave("
			SELECT thread.threadid, MATCH(thread.title) AGAINST ('$safetitle') AS score
			FROM " . TABLE_PREFIX . "thread AS thread
			$hook_query_joins
			WHERE MATCH(thread.title) AGAINST ('$safetitle')
				AND thread.open <> 10
				" . iif($threadid, " AND thread.threadid <> $threadid") . "
				$hook_query_where
			LIMIT 5
		");
		while ($thread = $vbulletin->db->fetch_array($threads))
		{
			// this is an arbitrary number but items less then 4 - 5 seem to be rather unrelated
			if ($thread['score'] > 4)
			{
				$similarthreads .= ", $thread[threadid]";
			}
		}

		$vbulletin->db->free_result($threads);

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
			if (($score / $retval['searchables']) < $vbulletin->options['similarthreadthreshold'] OR $numposts >= $vbulletin->options['maxresults'])
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
			if (($score / $retval['searchables']) < $vbulletin->options['similarthreadthreshold'])
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

	$sim = $vbulletin->db->query_read_slave("
		SELECT DISTINCT thread.threadid
		FROM " . TABLE_PREFIX . "post AS post
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
		WHERE postid IN (0$similarposts) " . iif($threadid, " AND post.threadid <> $threadid") . "
		ORDER BY ($numposts - FIELD(post.postid $similarposts )) DESC
		LIMIT 5
	");
	$similarthreads = '';
	while ($simthrd = $vbulletin->db->fetch_array($sim))
	{
		$similarthreads .= ", $simthrd[threadid]";
	}
	$vbulletin->db->free_result($sim);

	return substr($similarthreads, 2);
}

// #############################################################################
// checks if word is goodword / badword / too long / too short
function verify_word_allowed(&$word)
{
	global $vbulletin, $phrasequery;

	$wordlower = strtolower($word);

	// check if the word contains wildcards
	if (strpos($wordlower, '*') !== false)
	{
		// check if wildcards are allowed
		if ($vbulletin->options['allowwildcards'])
		{
			// check the length of the word with all * characters removed
			// and make sure it's at least (minsearchlength - 1) characters long
			// in order to prevent searches like *a**... which would be bad
			if (vbstrlen(str_replace('*', '', $wordlower)) < ($vbulletin->options['minsearchlength'] - 1))
			{
				// word is too short
				$word = htmlspecialchars_uni($word);
				eval(standard_error(fetch_error('searchinvalidterm', $word, $vbulletin->options['minsearchlength'])));
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
			eval(standard_error(fetch_error('searchinvalidterm', $word, $vbulletin->options['minsearchlength'])));
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
	global $vbulletin;
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
	$word = str_replace($find, $replace, $vbulletin->db->escape_string($word));

	return $word;
}

// #############################################################################
// gets a list of forums from the user's selection
function fetch_search_forumids(&$forumchoice, $childforums = 0)
{
	global $vbulletin, $stylevar, $display;

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
		if ($forumid === 'subscribed' AND $vbulletin->userinfo['userid'] != 0)
		{
			DEVDEBUG("Querying subscribed forums for " . $vbulletin->userinfo['username']);
			$sforums = $vbulletin->db->query_read_slave("
				SELECT forumid FROM " . TABLE_PREFIX . "subscribeforum
				WHERE userid = " . $vbulletin->userinfo['userid']
			);
			if ($vbulletin->db->num_rows($sforums) == 0)
			{
				// no subscribed forums
				eval(standard_error(fetch_error('not_subscribed_to_any_forums')));
			}
			while ($sforum = $vbulletin->db->fetch_array($sforums))
			{
				$forumids["$sforum[forumid]"] .= $sforum['forumid'];
			}
			unset($sforum);
			$vbulletin->db->free_result($sforums);
		}
		// get a single forumid or no forumid at all
		else
		{
			$forumid = intval($forumid);
			if (isset($vbulletin->forumcache["$forumid"]) AND $vbulletin->forumcache["$forumid"]['link'] == '')
			{
				$forumids["$forumid"] = $forumid;
			}
		}
	}

	// now if there are any forumids we have to query, work out their child forums
	if (empty($forumids))
	{
		$forumchoice = array();
		$display['forums'] = array();
	}
	else
	{
		// set $forumchoice to show the returned forumids
		#$forumchoice = implode(',', $forumids);

		// put current forumids into the display table
		$display['forums'] = $forumids;

		// get child forums of selected forums
		if ($childforums)
		{
			require_once(DIR . '/includes/functions_misc.php');
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
	global $vbulletin;

	$itemids = array();

	// order threads
	if ($showposts == 0)
	{
		$items = $vbulletin->db->query_read_slave("
			SELECT threadid FROM " . TABLE_PREFIX . "thread AS thread" . iif($sortby == 'forum.title', "
			INNER JOIN " . TABLE_PREFIX . "forum AS forum USING(forumid)") . "
			WHERE $searchclause
			ORDER BY $sortby $sortorder
		");
		while ($item = $vbulletin->db->fetch_array($items))
		{
			$itemids[] = $item['threadid'];
		}
	}
	// order posts
	else
	{
		$jointhread = in_array($sortby, array('thread.title', 'replycount', 'views', 'thread.dateline', 'forum.title'));
		$items = $vbulletin->db->query_read_slave("
			SELECT postid FROM " . TABLE_PREFIX . "post AS post"
			. ($jointhread ? " INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)" : "")
			. ($sortby == 'forum.title' ? " INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)" : "") . "
			WHERE $searchclause
			ORDER BY $sortby $sortorder
		");
		while ($item = $vbulletin->db->fetch_array($items))
		{
			$itemids[] = $item['postid'];
		}
	}

	// free SQL result
	unset($item);
	$vbulletin->db->free_result($items);

	return $itemids;

}

// #############################################################################
// remove common syntax errors in search query string
function sanitize_search_query($query, &$errors)
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
		$errors[] = 'invalid_search_syntax';
		return $query;
	}
	else if (!empty($query))
	{
		// check that we have some words that are NOT boolean controls
		$boolwords = array('AND', 'OR', 'NOT', '-AND', '-OR', '-NOT');
		foreach (explode(' ', strtoupper($query)) AS $key => $word)
		{
			if (!in_array($word, $boolwords))
			{
				// word is good - return the query
				return $query;
			}
		}
	}

	// no good words found - show no search terms error
	$errors[] = 'searchspecifyterms';
	return $query;

}

// #############################################################################
// fetch the score for a search result
function fetch_search_item_score(&$item, $currentscore)
{
	global $vbulletin;
	global $replyscore, $viewscore, $ratescore, $searchtype;

	// for fulltext NL search, just use the score set by MySQL
	if ($vbulletin->options['fulltextsearch'] AND !$searchtype)
	{
		return $currentscore;
	}

	// don't prejudice un-rated threads!
	if ($item['votenum'] == 0)
	{
		$item['rating'] = 3;
	}
	else
	{
		$item['rating'] = $item['votetotal'] / $item['votenum'];
	}

	$replyscore = $vbulletin->options['replyfunc']($item['replycount']) * $vbulletin->options['replyscore'];
	$viewscore = $vbulletin->options['viewfunc']($item['views']) * $vbulletin->options['viewscore'];
	$ratescore = $vbulletin->options['ratefunc']($item['rating']) * $vbulletin->options['ratescore'];

	return $currentscore + $replyscore + $viewscore + $ratescore;
}

// #############################################################################
// fetch the date scores for search results
function fetch_search_date_scores(&$datescores, &$itemscores, $mindate, $maxdate)
{
	global $vbulletin, $searchtype;

	// for fulltext NL search, just use the score set by MySQL
	if ($vbulletin->options['fulltextsearch'] AND !$searchtype)
	{
		unset($datescores);
		return;
	}

	$datespread = $maxdate - $mindate;
	if ($datespread > 0 AND $vbulletin->options['datescore'] != 0)
	{
		foreach ($datescores AS $itemid => $dateline)
		{
			$datescore = ($dateline - $mindate) / $datespread * $vbulletin->options['datescore'];
			$itemscores["$itemid"] += $datescore;
		}
	}
	unset($datescores);
}

// #############################################################################
// fetch array of IDs of forums to display in the search form
function fetch_search_forumids_array($parentid = -1, $depthmark = '')
{
	global $searchforumids, $vbulletin;
	static $indexed_forum_cache;

	if ($parentid == -1)
	{
		$searchforumids = array();
		$indexed_forum_cache = array();
		foreach ($vbulletin->forumcache AS $forumid => $forum)
		{
			$indexed_forum_cache["$forum[parentid]"]["$forumid"] =& $vbulletin->forumcache["$forumid"];
		}
	}

	if (!empty($indexed_forum_cache["$parentid"]) AND is_array($indexed_forum_cache["$parentid"]))
	{
		foreach ($indexed_forum_cache["$parentid"] AS $forumid => $forum)
		{
			$forumperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
			if ($forum['displayorder'] != 0
				AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
				AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['cansearch'])
				AND ($forum['options'] & $vbulletin->bf_misc_forumoptions['active'])
				AND verify_forum_password($forum['forumid'], $forum['password'], false)
			)
			{
				$vbulletin->forumcache["$forumid"]['depthmark'] = $depthmark;
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

// #############################################################################
function fetch_titleonly_url($searchterms)
{
	global $vbulletin;

	$url = array();
	if ($vbulletin->options['fulltextsearch'] AND !$searchterms['titleonly'] AND !empty($searchterms['query']))
	{
		if ($forumchoice = implode(',', fetch_search_forumids($searchterms['forumchoice'], $searchterms['childforums'])))
		{
			$searchforums = array_flip(explode(',', $forumchoice));
		}
		else
		{
			$searchforums =& $vbulletin->forumcache;
		}

		foreach ($searchforums AS $forumid => $foo)
		{
			if ($vbulletin->userinfo['forumpermissions']["$forumid"] & $vbulletin->bf_ugp_forumpermissions['canview'] AND $vbulletin->userinfo['forumpermissions']["$forumid"] & $vbulletin->bf_ugp_forumpermissions['cansearch'] AND !($vbulletin->userinfo['forumpermissions']["$forumid"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
			{
				$url[] = 'forumchoice[]=' . intval($forumid);
			}
		}
	}

	if (!empty($url))
	{
		$url[] = 'do=process';
		$url[] = 'query=' . urlencode($searchterms['query']);
		$url[] = 'titleonly=1';
		if ($searchterms['searchuser'])
		{
			$url[] = 'searchuser=' . urlencode($searchterms['searchuser']);
		}
		if ($searchterms['exactname'])
		{
			$url[] = 'exactname=1';
		}
		if ($searchterms['searchdate'])
		{
			$url[] = 'searchdate=' . urlencode($searchterms['searchdate']);
		}
		if ($searchterms['beforeafter'] == 'before')
		{
			$url[] = 'beforeafter=before';
		}
		if ($searchterms['replyless'])
		{
			$url[] = 'replyless=1';
		}
		if ($searchterms['replylimit'])
		{
			$url[] = 'replylimit=' . intval($searchterms['replylimit']);
		}
		if ($searchterms['sortorder'] != 'descending')
		{
			$url[] = 'order=ascending';
		}
		if ($searchterms['sortby'] != 'lastpost')
		{
			$url[] = 'sortby=' . urlencode($searchterms['sortby']);
		}
		if ($searchterms['starteronly'])
		{
			$url[] = 'starteronly=1';
		}
		if ($searchterms['nocache'])
		{
			$url[] = 'nocache=1';
		}

		return 'search.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $url);
	}
	else
	{
		return false;
	}
}

/**
* Fetches the HTML for the tag cloud.
*
* @param	string	Type of cloud. Supports search, usage
*
* @return	string	Tag cloud HTML (nothing if no cloud)
*/
function fetch_tagcloud($type = 'usage')
{
	global $vbulletin, $stylevar, $vbphrase, $show, $template_hook;

	$tags = array();

	if ($vbulletin->options['tagcloud_usergroup'] > 0 AND !isset($vbulletin->usergroupcache[$vbulletin->options['tagcloud_usergroup']]))
	{
		// handle a usergroup being deleted: default to live permission checking
		$vbulletin->options['tagcloud_usergroup'] = -1;
	}

	$cacheable = ($vbulletin->options['tagcloud_usergroup'] != -1);

	if (!$cacheable)
	{
		$cloud = null;
	}
	else
	{
		switch ($type)
		{
			case 'search':
				$cloud = $vbulletin->searchcloud;
				break;

			case 'usage':
			default:
				$cloud = $vbulletin->tagcloud;
				break;
		}
	}

	if (!is_array($cloud) OR $cloud['dateline'] < (TIMENOW - (60 * $vbulletin->options['tagcloud_cachetime'])))
	{
		if ($type == 'search')
		{
			$tags_result = $vbulletin->db->query_read_slave("
				SELECT tagsearch.tagid, tag.tagtext, COUNT(*) AS searchcount
				FROM " . TABLE_PREFIX . "tagsearch AS tagsearch
				INNER JOIN " . TABLE_PREFIX . "tag AS tag ON (tagsearch.tagid = tag.tagid)
				" . ($vbulletin->options['tagcloud_searchhistory'] ?
					"WHERE tagsearch.dateline > " . (TIMENOW - (60 * 60 * 24 * $vbulletin->options['tagcloud_searchhistory'])) :
					'') . "
				GROUP BY tagsearch.tagid, tag.tagtext
				ORDER BY searchcount DESC
				LIMIT " . $vbulletin->options['tagcloud_tags']
			);
		}
		else
		{
			if (!$vbulletin->options['tagcloud_usergroup'])
			{
				$perm_limit = false;
			}
			else
			{
				$forums = array();
				$perm_limit = true;

				foreach ($vbulletin->forumcache AS $forumid => $forum)
				{
					// -1 for live permission checking
					$perm_array = ($vbulletin->options['tagcloud_usergroup'] == -1
						? $vbulletin->userinfo['forumpermissions']["$forumid"]
						: $forum['permissions'][$vbulletin->options['tagcloud_usergroup']]
					);

					if ($perm_array & $vbulletin->bf_ugp_forumpermissions['canview']
						AND $perm_array & $vbulletin->bf_ugp_forumpermissions['canviewthreads']
						AND $perm_array & $vbulletin->bf_ugp_forumpermissions['canviewothers']
					)
					{
						$forums[] = intval($forumid);
					}

				}
			}

			if (!$perm_limit OR $forums)
			{
				$tags_result = $vbulletin->db->query_read_slave("
					SELECT tagthread.tagid, tag.tagtext, COUNT(*) AS searchcount
					FROM " . TABLE_PREFIX . "tagthread AS tagthread
					INNER JOIN " . TABLE_PREFIX . "tag AS tag ON (tagthread.tagid = tag.tagid)
					INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (tagthread.threadid = thread.threadid)
					WHERE thread.open <> 10
						AND thread.visible = 1
					" . ($perm_limit ? "AND thread.forumid IN (" . implode(',', $forums) . ")" : '') . "
					" . ($vbulletin->options['tagcloud_usagehistory'] ?
						"AND tagthread.dateline > " . (TIMENOW - (60 * 60 * 24 * $vbulletin->options['tagcloud_usagehistory'])) :
						'') . "
					GROUP BY tagthread.tagid, tag.tagtext
					ORDER BY searchcount DESC
					LIMIT " . $vbulletin->options['tagcloud_tags']
				);
			}
		}

		while ($currenttag = $vbulletin->db->fetch_array($tags_result))
		{
			$tags["$currenttag[tagtext]"] = $currenttag;
			$totals[$currenttag['tagid']] = $currenttag['searchcount'];
		}

		// fetch the stddev levels
		$levels = fetch_standard_deviated_levels($totals, $vbulletin->options['tagcloud_levels']);

		// assign the levels back to the tags
		foreach ($tags AS $tagtext => $tag)
		{
			$tags[$tagtext]['level'] = $levels[$tag['tagid']];
			$tags[$tagtext]['tagtext_url'] = urlencode(unhtmlspecialchars($tag['tagtext']));
		}

		// sort the categories by title
		uksort($tags, 'strnatcasecmp');

		$cloud = array(
			'tags' => $tags,
			'count' => sizeof($tags),
			'dateline' => TIMENOW
		);

		if ($cacheable)
		{
			if ($type == 'search')
			{
				$vbulletin->searchcloud = $cloud;
				build_datastore('searchcloud', serialize($cloud), 1);
			}
			else
			{
				$vbulletin->tagcloud = $cloud;
				build_datastore('tagcloud', serialize($cloud), 1);
			}
		}
	}

	if (empty($cloud['tags']))
	{
		return '';
	}

	$cloud['links'] = '';

	foreach ($cloud['tags'] AS $thistag)
	{
		($hook = vBulletinHook::fetch_hook('tag_cloud_bit')) ? eval($hook) : false;

		eval('$cloud[\'links\'] .= "' . fetch_template('tag_cloud_link') . '";');
	}

	$cloud['count'] = vb_number_format($cloud['count']);

	if ($type == 'search')
	{
		eval('$cloud_html .= "' . fetch_template('tag_cloud_box_search') . '";');
	}
	else
	{
		eval('$cloud_html .= "' . fetch_template('tag_cloud_box') . '";');
	}

	return $cloud_html;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
