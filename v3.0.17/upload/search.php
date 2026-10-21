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
define('THIS_SCRIPT', 'search');
define('ALTSEARCH', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('search');

// get special data templates from the datastore
$specialtemplates = array(
	'iconcache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'search_forums',
	'search_results',
	'search_results_postbit', // result from search posts
	'threadbit', // result from search threads
	'newreply_reviewbit_ignore'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_search.php');
require_once('./includes/functions_forumlist.php');
require_once('./includes/functions_misc.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!($permissions['forumpermissions'] & CANSEARCH))
{
	print_no_permission();
}

if (!$vboptions['enablesearches'])
{
	eval(print_standard_error('error_searchdisabled'));
}

// #############################################################################

$globalize = array(
	'query' => STR,
	'searchuser' => STR,
	'exactname' => INT,
	'starteronly' => INT,
	'forumchoice',
	'childforums' => INT,
	'titleonly' => INT,
	'showposts' => INT,
	'searchdate' => STR,
	'beforeafter' => STR,
	'sortby' => STR,
	'sortorder' => STR,
	'replyless' => INT,
	'replylimit' => INT,
	'searchthread' => INT,
	'searchthreadid' => INT,
	'saveprefs' => INT
);

// #############################################################################

if (empty($_REQUEST['do']))
{
	if ($_REQUEST['searchid'])
	{
		$_REQUEST['do'] = 'showresults';
	}
	else if (!empty($_REQUEST['q']))
	{
		$_REQUEST['do'] = 'process';
		$_REQUEST['query'] = &$_REQUEST['q'];
	}
	else
	{
		$_REQUEST['do'] = 'intro';
	}
}

// check for extra variables from the advanced search form
if ($_POST['do'] == 'process')
{
	// don't go to do=process, go to do=doprefs
	if (isset($_POST['doprefs']))
	{
		$_POST['do'] = 'doprefs';
		$_REQUEST['do'] = 'doprefs';
	}
}

// #############################################################################
if (in_array($_REQUEST['do'], array('intro', 'showresults', 'doprefs')) == false)
{
	// get last search for this user and check floodcheck
	if ($prevsearch = $DB_site->query_first("
		SELECT searchid, dateline
		FROM " . TABLE_PREFIX . "search AS search
		WHERE " . iif($bbuserinfo['userid'] == 0, "ipaddress ='" . addslashes(IPADDRESS) . "'", "userid = $bbuserinfo[userid]") . "
		ORDER BY dateline DESC LIMIT 1
	"))
	{
		if (($timepassed = TIMENOW - $prevsearch['dateline']) < $vboptions['searchfloodtime'] AND $vboptions['searchfloodtime'] != 0 AND !($permissions['adminpermissions'] & CANCONTROLPANEL) AND !can_moderate())
		{
			$timeleft = $vboptions['searchfloodtime'] - $timepassed;
			eval(print_standard_error('error_searchfloodcheck'));
		}
	}
}

// make first part of navbar
$navbits = array("search.php?$session[sessionurl]" => $vbphrase['search_forums']);

// #############################################################################
if ($_REQUEST['do'] == 'intro')
{
	globalize($_REQUEST, array(
		'query' => STR,
		'searchuser' => STR,
		'forumid' => STR,
		'forumchoice'
	));

	// get list of forums moderated by this user to bypass password check
	$modforums = array();
	if ($bbuserinfo['userid'] AND (!($permissions['adminpermissions'] & ISMODERATOR)) AND (!($permissions['adminpermissions'] & CANCONTROLPANEL)))
	{
		// only do this query if the user is logged in, and is not a super mod or an admin
		DEVDEBUG('Querying moderators');
		cache_moderators();
	}

	// #############################################################################
	// read user's search preferences
	if ($bbuserinfo['searchprefs'] != '')
	{
		$prefs = unserialize($bbuserinfo['searchprefs']);
	}
	else
	{
		$prefs  = array(
			'exactname' => 1,
			'starteronly' => 0,
			'childforums' => 1,
			'showposts' => 0,
			'titleonly' => 0,
			'searchdate' => 0,
			'beforeafter' => 'after',
			'sortby' => 'lastpost',
			'sortorder' => 'descending',
			'replyless' => 0,
			'replylimit' => 0
		);
	}
	// if search conditions are specified in the URI, use them
	foreach (array_keys($globalize) AS $varname)
	{
		if (isset($_REQUEST["$varname"]))
		{
			$prefs["$varname"] = $_REQUEST["$varname"];
		}
	}
	// now check approprate boxes, select menus etc...
	foreach ($prefs AS $varname => $value)
	{
		if ($varname != 'query' AND $varname != 'searchuser')
		{
			$$varname = htmlspecialchars_uni($value);
		}
		$checkedvar = $varname . 'checked';
		$selectedvar = $varname . 'selected';
		$$checkedvar = array($value => HTML_CHECKED);
		$$selectedvar = array($value => HTML_SELECTED);
	}

	$query = htmlspecialchars_uni($query);
	$searchuser = htmlspecialchars_uni($searchuser);

	// if $forumchoice is not an array, make it one!
	if (!is_array($forumchoice))
	{
		$forumchoice = array($forumchoice);
	}
	// if $forumid is specified, use it
	if (isset($_REQUEST['forumid']))
	{
		$forumchoice[] = intval($_REQUEST['forumid']);
	}

	// now get the IDs of the forums we are going to display
	fetch_search_forumids_array();

	$searchforumbits = '';
	$haveforum = false;

	foreach ($searchforumids AS $forumid)
	{
		$forum = &$forumcache["$forumid"];

		if (trim($forum['link']))
		{
			continue;
		}

		$optionvalue = $forumid;
		$optiontitle = "$forum[depthmark] $forum[title]";
		$optionclass = 'fjdpth' . iif($forum['depth'] > 4, 4, $forum['depth']);

		if (in_array($forumid, $forumchoice))
		{
			$optionselected = HTML_SELECTED;
			$haveforum = true;
		}
		else
		{
			$optionselected = '';
		}

		eval('$searchforumbits .= "' . fetch_template('option') . '";');
	}

	$noforumselected = iif(!$haveforum, HTML_SELECTED);

	// select the correct part of the forum jump menu
	$frmjmpsel['search'] = 'class="fjsel" selected="selected"';
	construct_forum_jump();

	// unlink the 'search' part of the navbits
	array_pop($navbits);

	$navbits[''] = $vbphrase['search_forums'];

	$templatename = 'search_forums';
}

// #############################################################################
if ($_REQUEST['do'] == 'process')
{
	globalize($_REQUEST, $globalize);

	// #############################################################################
	// start search timer
	$searchstart = microtime();

	// #############################################################################
	// error if no search terms
	if (empty($query) AND empty($searchuser) AND empty($replyless))
	{
		eval(print_standard_error('searchspecifyterms'));
	}

	// #############################################################################
	// if searching within a thread, $showposts must be true and sorting should be "dateline ASC"
	if ($searchthreadid)
	{
		$showposts = 1;
		$sortby = 'dateline';
		$sortorder = 'ASC';
		$forumchoice = 0;
		$titlesonly = 0;
		$searchuser = '';
		$replyless = 0;
		$replylimit = 0;
	}

	// #############################################################################
	// make array of search terms for back referencing
	$searchterms = array();
	foreach ($globalize AS $varname => $value)
	{
		if (!in_array($value, array(INT, STR)))
		{
			$varname = $value;
		}
		$searchterms["$varname"] = $_REQUEST["$varname"];
	}

	// #############################################################################
	// if query string is specified, check syntax and replace common syntax errors
	if ($query)
	{
		if ($vboptions['fulltextsearch'])
		{
			$query = preg_replace('#"(.+?)"#sie', "stripslashes(str_replace(' ' , '*', '\\0'))", $query);
			// what about replacement words??
		}
		else
		{
			$vboptions['allow_phrase_searching'] = 1;
			// is this a phrase query?
			$phrasequery = '';
			if ($vboptions['allow_phrase_searching'] AND preg_match('/^"([^"]+)"$/siU', trim($query), $regs))
			{
				$query = trim(preg_replace('/\s+/s', ' ', $regs[1]));

				// only allow phrase searches if there is at least one space in the query!
				if (strpos($query, ' ') !== false)
				{
					$phrasequery = $query;
				}
			}
		}

		$query = sanitize_search_query($query);
	}

	// #############################################################################
	// get forums in which to search
	$forumchoice = implode(',', fetch_search_forumids($forumchoice, $childforums));

	// #############################################################################
	// get correct sortby value
	$sortby = strtolower($sortby);
	switch($sortby)
	{
		// sort variables that don't need changing
		case 'title':
		case 'views':
		case 'lastpost':
		case 'replycount':
		case 'postusername':
		case 'rank':
			break;

		// sort variables that need changing
		case 'forum':
			$sortby = 'forum.title';
			break;

		case 'threadstart':
			$sortby = 'thread.dateline';
			break;

		// set default sortby if not specified or unrecognized
		default:
			$sortby = 'lastpost';
	}

	// #############################################################################
	// if showing results as posts, translate the $sortby variable
	if ($showposts == 1)
	{
		switch($sortby)
		{
			case 'title':
				$sortby = 'thread.title';
				break;
			case 'lastpost':
				$sortby = 'post.dateline';
				break;
			case 'postusername':
				$sortby = 'username';
				break;
		}
	}

	// #############################################################################
	// get correct sortorder value
	$sortorder = strtolower($sortorder);
	switch($sortorder)
	{
		case 'ascending':
			$sortorder = 'ASC';
			break;

		default:
			$sortorder = 'DESC';
			break;
	}

	// #############################################################################
	// build search hash
	$searchhash = md5(strtolower(iif($phrasequery, '"' . $query . '"', $query)) . "||" . strtolower($searchuser) . "||$exactname||$starteronly||$forumchoice||$childforums||$titleonly||$showposts||$searchdate||$beforeafter||$replyless||$replylimit||$searchthreadid");

	// #############################################################################
	// search for already existing searches...
	$getsearches = $DB_site->query("
		SELECT * FROM " . TABLE_PREFIX . "search AS search
		WHERE searchhash = '" . addslashes($searchhash) . "'" . iif($forumchoice === 'subscribed', "
		AND userid = $bbuserinfo[userid]") . "
	");
	if ($numsearches = $DB_site->num_rows($getsearches))
	{
		$highScore = 0;
		while ($getsearch = $DB_site->fetch_array($getsearches))
		{
			// is $sortby the same?
			if ($getsearch['sortby'] == $sortby)
			{
				if ($getsearch['sortorder'] == $sortorder)
				{
					// search matches exactly
					$search = $getsearch;
					$highScore = 3;
				}
				else if ($highScore < 2)
				{
					// search matches but needs order reversed
					$search = $getsearch;
					$highScore = 2;
				}
			}
			// $sortby is different
			else if ($highScore < 1)
			{
				// search matches but needs total re-ordering
				$search = $getsearch;
				$highScore = 1;
			}
		}
		unset($getsearch);
		$DB_site->free_result($getsearches);

		// check our results and decide what to do
		switch ($highScore)
		{
			// #############################################################################
			// found a saved search that matches perfectly
			case 3:
				// redirect to saved search
				$url = "search.php?$session[sessionurl]searchid=$search[searchid]";
				eval(print_standard_redirect('search'));
				break;

			// #############################################################################
			// found a saved search and just need to reverse sort order
			case 2:
				// reverse sort order
				$search['orderedids'] = array_reverse(explode(',', $search['orderedids']));
				// stop search timer
				$searchtime = fetch_microtime_difference($searchstart);
				// insert new search into database
				$DB_site->query("
					REPLACE INTO " . TABLE_PREFIX . "search (userid, ipaddress, query, searchuser, forumchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms, displayterms, searchhash)
					VALUES ($bbuserinfo[userid], '" . addslashes(IPADDRESS) . "', '" . addslashes($search['query']) . "', '" . addslashes($search['searchuser']) . "', '" . addslashes($search['forumchoice']) . "', '" . addslashes($search['sortby']) . "', '" . addslashes($sortorder) . "', $searchtime, $showposts, '" . implode(',', $search['orderedids']) . "', " . TIMENOW . ", '" . addslashes($search['searchterms']) . "', '" . addslashes($search['displayterms']) . "', '" . addslashes($searchhash) . "')
					### SAVE ITEM IDS IN ORDER ###
				");
				// redirect to new search result
				$url = "search.php?$session[sessionurl]searchid=" . $DB_site->insert_id();
				eval(print_standard_redirect('search'));
				break;

			// #############################################################################
			// Found a search with correct query conditions, but ORDER BY clause needs to be totally redone
			case 1:
				if ($sortby == 'rank' OR $search['sortby'] == 'rank')
				{
					// if we are changing to or from a relevancy search, we need to re-do the search
					break;
				}
				else
				{
					// re order search items
					$search['orderedids'] = iif($search['showposts'], 'postid', 'threadid') . " IN($search[orderedids])";
					$search['orderedids'] = sort_search_items($search['orderedids'], $search['showposts'], $sortby, $sortorder);
					// stop search timer
					$searchtime = fetch_microtime_difference($searchstart);
					// insert new search into database
					$DB_site->query("
						REPLACE INTO " . TABLE_PREFIX . "search (userid, ipaddress, query, searchuser, forumchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms, displayterms, searchhash)
						VALUES ($bbuserinfo[userid], '" . addslashes(IPADDRESS) . "', '" . addslashes($search['query']) . "', '" . addslashes($search['searchuser']) . "', '" . addslashes($search['forumchoice']) . "', '" . addslashes($sortby) . "', '" . addslashes($sortorder) . "', $searchtime, $search[showposts], '" . implode(',', $search['orderedids']) . "', " . TIMENOW . ", '" . addslashes(serialize($searchterms)) . "', '" . addslashes($search['displayterms']) . "', '" . addslashes($searchhash) . "')
						### SAVE ITEM IDS IN ORDER ###
					");
					// redirect to new search result
					$url = "search.php?$session[sessionurl]searchid=" . $DB_site->insert_id();
					eval(print_standard_redirect('search'));
					break;
				}
		}
	}

	// #############################################################################
	// #############################################################################
	// if we got this far we need to do a full search
	// #############################################################################
	// #############################################################################

	// $postQueryLogic stores all the SQL conditions for our search in posts
	$postQueryLogic = array();

	// $threadQueryLogic stores all SQL conditions for the search in threads
	$threadQueryLogic = array();

	// $words stores all the search words with their word IDs
	$words = array(
		'AND' => array(),
		'OR' => array(),
		'NOT' => array(),
		'COMMON' => array()
	);

	// $queryWords provides a way to talk to words within the $words array
	$queryWords = array();

	// $display - stores a list of things searched for
	$display = array(
		'words' => array(),
		'highlight' => array(),
		'common' => array(),
		'users' => array(),
		'forums' => $display['forums'],
		'options' => array(
			'starteronly' => $starteronly,
			'childforums' => $childforums,
			'action' => $_REQUEST['do']
		)
	);

	$postscores = array();

	// #############################################################################
	// ####################### START USER QUERY LOGIC ##############################
	// #############################################################################
	if ($searchuser)
	{
		// username too short
		if (!$exactname AND strlen($searchuser) < 3)
		{
			eval(print_standard_error('searchnametooshort'));
		}

		$username = sanitize_word_for_sql(htmlspecialchars_uni($searchuser));
		$q = "
			SELECT userid, username FROM " . TABLE_PREFIX . "user AS user
			WHERE username " . iif($exactname, "= '$username'", "LIKE('%$username%')
		");

		require_once('./includes/functions_bigthree.php');
		$coventry = fetch_coventry();

		$users = $DB_site->query($q);
		if ($DB_site->num_rows($users))
		{
			$userids = array();
			while ($user = $DB_site->fetch_array($users))
			{
				$display['users']["$user[userid]"] = $user['username'];
				$userids[] = in_array($user['userid'], $coventry) ? -1 : $user['userid'];
			}

			$userids = implode(', ', $userids);

			// add some logic to the $threadQueryLogic if the search specifies $starteronly
			if ($starteronly)
			{
				if ($showposts)
				{
					$postQueryLogic[] = "post.userid IN($userids)";
					$postQueryLogic[] = "thread.postuserid IN($userids)";
					// This is supposed to be here twice
				}
				else
				{
					$postQueryLogic[] = "thread.postuserid IN($userids)";
					$threadQueryLogic[] = "thread.postuserid IN($userids)";
				}
			}
			// add the userids to the $postQueryLogic search conditions
			else
			{
				$postQueryLogic[] = "post.userid IN($userids)";
			}
		}
		else
		{
			$idname = $vbphrase['user'];
			eval(print_standard_error('invalidid'));
		}
	}

	// #############################################################################
	// ########################## START WORD QUERY LOGIC ###########################
	// #############################################################################
	if ($query)
	{
		$querysplit = $query;

		// #############################################################################
		// if we are doing a relevancy sort, use all AND and OR words as OR
		if ($sortby == 'rank')
		{
			$not = '';
			while (preg_match_all('# -(.*) #siU', " $querysplit ", $regs))
			{
				foreach ($regs[0] AS $word)
				{
					$not .= ' ' . trim($word);
					$querysplit = trim(str_replace($word, ' ', " $querysplit "));
				}
			}
			$querysplit = preg_replace('# (OR )*#si', ' OR ', $querysplit) . $not;
		}
		// #############################################################################

		// strip out common words from OR clauses pt1
		if (preg_match_all('#OR ([^\s]+) #sU', "$querysplit ", $regs))
		{
			foreach ($regs[1] AS $key => $word)
			{
				if (!verify_word_allowed($word))
				{
					$display['common'][] = $word;
					$querysplit = trim(str_replace($regs[0]["$key"], '', "$querysplit "));
				}
			}
		}
		// strip out common words from OR clauses pt2
		if (preg_match_all('# ([^\s]+) OR#sU', " $querysplit", $regs))
		{
			foreach ($regs[1] AS $key => $word)
			{
				if (!verify_word_allowed($word))
				{
					$display['common'][] = $word;
					$querysplit = trim(str_replace($regs[0]["$key"], ' ', " $querysplit "));
				}
			}
		}

		// regular expressions to match query syntax
		$syntax = array(
			'NOT' => '/( -[^\s]+)/si',
			'OR' => '#( ([^\s]+)(( OR [^\s]+)+))#si',
			'AND' => '/(\s|\+)+/siU'
		);

		// #############################################################################
		// find NOT clauses
		if (preg_match_all($syntax['NOT'], " $querysplit", $regs))
		{
			foreach ($regs[0] AS $word)
			{
				$word = substr(trim($word), 1);
				if (verify_word_allowed($word))
				{
					// word is okay - add it to the list of NOT words to be queried
					$words['NOT']["$word"] = 'NOT';
					$queryWords["$word"] = &$words['NOT']["$word"];
				}
				else
				{
					// word is bad or unindexed - add to list of common words
					$display['common'][] = $word;
				}
			}
			$querysplit = preg_replace($syntax['NOT'], ' ', " $querysplit");
		}

		// #############################################################################
		// find OR clauses
		if (preg_match_all($syntax['OR'], " $querysplit", $regs))
		{
			foreach ($regs[0] AS $word)
			{
				$word = trim($word);
				$orBits = explode(' OR ', $word);
				$checkwords = array();
				foreach ($orBits AS $orBit)
				{
					if (verify_word_allowed($orBit))
					{
						// word is okay - add it to the list of OR words for this clause
						$checkwords[] = $orBit;
					}
					else
					{
						// word is bad or unindexed - add to list of common words
						$display['common'][] = $orBit;
					}
				}
				// check to see how many words we have in the current OR clause
				switch(sizeof($checkwords))
				{
					case 0:
						// all words were bad or not indexed
						eval(print_standard_error('searchnoresults', 1, 0));
						break;

					case 1:
						// just one word is okay - use it as an AND word instead of an OR
						$word = implode('', $checkwords);
						$words['AND']["$word"] = 'AND';
						$queryWords["$word"] = &$words['AND']["$word"];
						break;

					default:
						// two or more words were okay - use them as an OR clause
						foreach ($checkwords AS $checkword)
						{
							$words['OR']["$word"]["$checkword"] = 'OR';
							$queryWords["$checkword"] = &$words['OR']["$word"]["$checkword"];
						}
						break;
				}
			}
			$querysplit = preg_replace($syntax['OR'], '', " $querysplit");
		}

		// #############################################################################
		// other words must be required (AND)
		foreach (preg_split($syntax['AND'], $querysplit, -1, PREG_SPLIT_NO_EMPTY) AS $word)
		{
			if (verify_word_allowed($word))
			{
				// word is okay - add it to the list of AND words to be queried
				$words['AND']["$word"] = 'AND';
				$queryWords["$word"] = &$words['AND']["$word"];
			}
			else
			{
				// word is bad or unindexed - add to list of common words
				$display['common'][] = $word;
			}
		}

		if (sizeof($display['common']) > 0)
		{
			$displayCommon = "<p>$vbphrase[words_very_common] : <b>" . implode('</b>, <b>', htmlspecialchars_uni($display['common'])) . '</b></p>';
		}
		else
		{
			$displayCommon = '';
		}

		// now that we've checked all the words, are there still some terms to search with?
		if (empty($queryWords) AND empty($display['users']))
		{
			// all search words bad or unindexed
			eval(print_standard_error('searchnoresults', 1, 0));
		}

		if (!$vboptions['fulltextsearch'])
		{
			// #############################################################################
			// get highlight words (part 1)
			foreach ($queryWords AS $word => $wordtype)
			{
				if ($wordtype != 'NOT')
				{
					$display['highlight'][] = $word;
				}
			}

			// #############################################################################
			// query words from word and postindex tables to get post ids
			// #############################################################################
			foreach ($queryWords AS $word => $wordtype)
			{
				// should remove characters just like we do when we insert into post index
				$queryword = preg_replace('#[()"\'!\#{};]|\\\\|:(?!//)#s', '', $word);

				// make sure word is safe to insert into the query
				$queryword = sanitize_word_for_sql($queryword);

				if ($vboptions['allowwildcards'])
				{
					$queryword = str_replace('*', '%', $queryword);
				}
				$getwords = $DB_site->query("
					SELECT wordid, title FROM " . TABLE_PREFIX . "word
					WHERE title LIKE('$queryword')
				");
				if ($DB_site->num_rows($getwords))
				{
					// found some results for current word
					$wordids = array();
					while ($getword = $DB_site->fetch_array($getwords))
					{
						$wordids[] = $getword['wordid'];
					}
					// query post ids for current word...
					// if $titleonly is specified, also get the value of postindex.intitle
					$postmatches = $DB_site->query("
						SELECT postid" . iif($titleonly, ', intitle') . iif($sortby == 'rank', ", score AS origscore,
							CASE intitle
								WHEN 1 THEN score + $vboptions[posttitlescore]
								WHEN 2 THEN score + $vboptions[posttitlescore] + $vboptions[threadtitlescore]
								ELSE score
							END AS score") . "
						FROM " . TABLE_PREFIX . "postindex
						WHERE wordid IN(" . implode(',', $wordids) . ")
					");
					if ($DB_site->num_rows($postmatches) == 0)
					{
						if ($wordtype == 'AND')
						{
							// could not find any posts containing required word
							eval(print_standard_error('searchnoresults', 1, 0));
						}
						else
						{
							// Could not find any posts containing word
							// remove this word from the $queryWords array so we don't use it in the posts query
							unset($queryWords["$word"]);
						}
					}
					else
					{
						// reset the $queryWords entry for current word
						$queryWords["$word"] = array();

						// check that word exists in the title
						if ($titleonly)
						{
							while ($postmatch = $DB_site->fetch_array($postmatches))
							{
								if ($postmatch['intitle'])
								{
									$bonus = iif(isset($postscores["$postmatch[postid]"]), $vboptions['multimatchscore'], 0);
									$postscores["$postmatch[postid]"] += $postmatch['score'] + $bonus;
									$queryWords["$word"][] = $postmatch['postid'];
								}
							}
						}
						// don't bother checking that word exists in the title
						else
						{
							while ($postmatch = $DB_site->fetch_array($postmatches))
							{
								$bonus = iif(isset($postscores["$postmatch[postid]"]), $vboptions['multimatchscore'], 0);
								$postscores["$postmatch[postid]"] += $postmatch['score'] + $bonus;
								$queryWords["$word"][] = $postmatch['postid'];
							}
						}
					}
					// free SQL memory for postids query
					unset($postmatch);
					$DB_site->free_result($postmatches);
				}
				else
				{
					if ($wordtype == 'AND')
					{
						// could not find required word in the database
						eval(print_standard_error('searchnoresults', 1, 0));
					}
					else
					{
						// Could not find word in the database
						// remove this word from the $queryWords array so we don't use it in the posts query
						unset($queryWords["$word"]);
					}
				}
				unset($getword);
				$DB_site->free_result($getwords);
			}

			// #############################################################################
			// get highlight words (part 2);
			foreach ($display['highlight'] AS $key => $word)
			{
				if (!isset($queryWords["$word"]))
				{
					unset($display['highlight']["$key"]);
				}
			}

			// #############################################################################
			// get posts with logic
			$requiredposts = array();

			// if we are searching in a thread, the required posts MUST come from the thread we are searching!
			if ($searchthreadid)
			{
				$q = "
					SELECT postid FROM " . TABLE_PREFIX . "post
					WHERE threadid = $searchthreadid
				";
				$posts = $DB_site->query($q);
				if ($DB_site->num_rows($posts) == 0)
				{
					$idname = $vbphrase['thread'];
					eval(print_standard_error('invalidid'));
				}
				while ($post = $DB_site->fetch_array($posts))
				{
					$requiredposts[0][] = $post['postid'];
				}
				unset($post);
				$DB_site->free_result($posts);
			}

			// #############################################################################
			// get AND clauses
			if (!empty($words['AND']))
			{
				// intersect the post ids for all AND words - Note: array_intersect() IS BROKEN IN PHP 4.0.4
				foreach (array_keys($words['AND']) AS $word)
				{
					$requiredposts[] = &$queryWords["$word"];
				}
			}

			// #############################################################################
			// get OR clauses
			if (!empty($words['OR']))
			{
				$or = array();
				// run through each OR clause
				foreach ($words['OR'] AS $orClause => $orWords)
				{
					// get the post ids for each OR word
					$checkwords = array();
					foreach (array_keys($orWords) AS $word)
					{
						if (isset($queryWords["$word"]))
						{
							$checkwords[] = $queryWords["$word"];
						}
					}

					// check to see that we still have valid OR clauses
					switch(sizeof($checkwords))
					{
						case 0:
							// no matches for any of the OR words in current clause - show no matches error
							eval(print_standard_error('searchnoresults', 1, 0));
							break;

						case 1:
							// found only one matching word from the current OR clause - translate this OR into an AND#
							$requiredposts[] = $checkwords[0];
							break;

						default:
							// found matches for two or more terms in the OR clause - process it as an OR
							foreach ($checkwords AS $checkword)
							{
								$postids[] = implode(', ', $checkword);
							}
							if (sizeof($postids) > 0)
							{
								$or[] = '(postid IN(' . implode(') OR postid IN(', $postids) . '))';
							}
							break;
					}
				}

				// now add the remaining OR terms to the query if there are any
				if (!empty($or))
				{
					$postQueryLogic = array_merge($postQueryLogic, $or);
				}

				// clean up variables
				unset($or, $orClause, $orWords, $word, $checkwords, $postids);
			}

			// #############################################################################
			// now stick together the AND words and any OR words where there was only one word found
			if (!empty($requiredposts))
			{
				// intersect all required post ids to get a definitive list of posts
				// that MUST be returned by the posts query
				$ANDs = false;

				foreach ($requiredposts AS $postids)
				{
					if (is_array($ANDs))
					{
						// intersect the existing AND postids with the postids for the next clause
						$ANDs = array_intersect($ANDs, $postids);
					}
					else
					{
						// this is the first time we have looped, so make $ANDs into an array
						$ANDs = $postids;
					}
				}

				// if there are no postids left, no matches were made from posts
				if (empty($ANDs))
				{
					// no posts matched the query
					eval(print_standard_error('searchnoresults', 1, 0));
				}
				else
				{
					$postQueryLogic[] = 'post.postid IN(' . implode(',', $ANDs) . ')';
				}

				// clean up variables
				unset($requiredposts, $postids, $ANDs);
			}

			// #############################################################################
			// get NOT clauses
			if (!empty($words['NOT']))
			{
				// merge the post ids for all NOT words to get a definitive list of posts
				// that MUST NOT be returned by the posts query
				$postids = array();

				foreach (array_keys($words['NOT']) AS $word)
				{
					if (isset($queryWords["$word"]))
					{
						$postids = array_merge($postids, $queryWords["$word"]);
					}
				}

				// remove duplicate post ids to make a smaller query
				if (!empty($postids))
				{
					$postids = array_unique($postids);
					$postQueryLogic[] =  'post.postid NOT IN(' . implode(',', $postids) . ')';
				}

				// clean up variables
				unset($postids);
			}

			// check that we don't have only NOT words
			if (empty($words['AND']) AND empty($words['OR']) AND !empty($words['NOT']))
			{
				// user has ONLY specified a 'NOT' word... this would be bad
				eval(print_standard_error('searchnoresults', 1, 0));
			}


		}
		else
		{
			// Fulltext ...
			foreach ($queryWords AS $word => $wordtype)
			{
				// Need something here to strip odd characters out of words that fulltext is probably not indexing

				$queryword = preg_replace('#"(.+?)"#sie', "stripslashes(str_replace('*', ' ', '\\0'))", $word);

				if ($wordtype != 'NOT')
				{
					$display['highlight'][] = htmlspecialchars_uni(preg_replace('#"(.+)"#si', '\\1', $queryword));
				}

				// make sure word is safe to insert into the query
				$unsafeword = $queryword;
				$queryword = sanitize_word_for_sql($queryword);
				$wordlist = iif($wordlist, "$wordlist ", $wordlist);
				switch ($wordtype)
				{
					case 'AND':
						$wordlist .= "+$queryword";
						break;
					case 'OR':
						$wordlist .= $queryword;
						break;
					case 'NOT':
						$wordlist .= "-$queryword";
						break;
				}
			}

 			if ($searchuser)
 			{
 				$postQueryIndex = " USE INDEX (userid)";
 			}

			// if we are searching in a thread, the required posts MUST come from the thread we are searching!
			if ($searchthreadid)
			{
				$postQueryLogic[] = "thread.threadid = $searchthreadid";
				$postQueryIndex = " USE INDEX (threadid)";
			}

			if ($titleonly)
			{
				$postQueryLogic[] = "MATCH(thread.title) AGAINST ('$wordlist' IN BOOLEAN MODE)";
			}
			else
			{
				$postQueryLogic[] = "MATCH(post.title,pagetext) AGAINST ('$wordlist' IN BOOLEAN MODE)";
			}
		}
	}

	// #############################################################################
	// ######################### END WORD QUERY LOGIC ##############################
	// #############################################################################

	// #############################################################################
	// check if we are searching for posts from a specific time period
	if ($searchdate != 'lastvisit')
	{
		$searchdate = intval($searchdate);
	}
	if ($searchdate)
	{
		switch($searchdate)
		{
			case 'lastvisit':
				// get posts from before/after last visit
				$datecut = $bbuserinfo['lastvisit'];
				break;

			case 0:
				// do not specify a time period
				$datecut = 0;
				break;

			default:
				// get posts from before/after specified time period
				$datecut = TIMENOW - $searchdate * 86400;
		}
		if ($datecut)
		{
			switch($beforeafter)
			{
				// get posts from before $datecut
				case 'before':
					$postQueryLogic[] = "post.dateline < $datecut";
					break;

				// get posts from after $datecut
				default:
					$postQueryLogic[] = "post.dateline > $datecut";
			}
		}
		unset($datecut);
	}

	// #############################################################################
	// check to see if there are conditions attached to number of thread replies
	if ($replyless OR $replylimit > 0)
	{
		if ($replyless == 1)
		{
			// get threads with at *most* $replylimit replies
			if ($showposts)
			{
				$postQueryLogic[] = "thread.replycount <= $replylimit";
			}
			else
			{
				$threadQueryLogic[] = "thread.replycount <= $replylimit";
			}
		}
		else
		{

			// get threads with at *least* $replylimit replies
			if ($showposts)
			{
				$postQueryLogic[] = "thread.replycount >= $replylimit";
			}
			else
			{
				$threadQueryLogic[] = "thread.replycount >= $replylimit";
			}
		}
	}

	// #############################################################################
	// check to see if we should be searching in a particular forum or forums
	if ($forumchoice)
	{
		if ($showposts)
		{
			$postQueryLogic[] = "thread.forumid IN($forumchoice)";
		}
		else
		{
			$threadQueryLogic[] = "thread.forumid IN($forumchoice)";
		}
	}

	// #############################################################################
	// phrase query logic
	if ($phrasequery)
	{
		if ($titleonly)
		{
			$postQueryLogic[] = "post.title LIKE('%" . sanitize_word_for_sql($phrasequery) . "%')";
		}
		else
		{
			$postQueryLogic[] = "post.pagetext LIKE('%" . sanitize_word_for_sql($phrasequery) . "%')";
		}
	}

	// #############################################################################
	// show results as threads
	// #############################################################################
	if ($showposts == 0)
	{
		// create new threadscores array to store scores for threads
		$threadscores = array();
		// get thread ids from post table excluding deleted threads/posts
		if (empty($postQueryLogic))
		{
			// no conditions to search on in the post table,
			// so add some logic to the query on the thread table
			$threadids = '1';
		}
		else
		{
			// #############################################################################
			// got some conditions to search on in the post table,
			// so do the query and then pass the resulting IDs to the the thread table query
			$threadids = array();
			$threads = $DB_site->query("
				SELECT post.postid, post.threadid
				FROM " . TABLE_PREFIX . "post AS post " . iif($vboptions['fulltextsearch'] AND $searchuser, "USE INDEX (userid)") . "
				INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
				WHERE " . implode(" AND ", $postQueryLogic) . "
				AND post.visible = 1
			");

			if ($sortby == 'rank')
			{
				while ($thread = $DB_site->fetch_array($threads))
				{
					$threadscores["$thread[threadid]"] += $postscores["$thread[postid]"];
					$threadids["$thread[threadid]"] = true;
				}
			}
			else
			{
				while ($thread = $DB_site->fetch_array($threads))
				{
					$threadids["$thread[threadid]"] = true;
				}
			}
			unset($thread);
			$DB_site->free_result($threads);

			if (empty($threadids))
			{
				eval(print_standard_error('searchnoresults', 1, 0));
			}

			// remove duplicate thread ids and make a query string
			$threadids = 'threadid IN(' . implode(',', array_keys($threadids)) . ')';
		}

		// create $itemscores array to store final scores for threads
		unset($postscores);

		// #############################################################################
		// query extra data from the thread table, and check thread deletion log
		$threads = $DB_site->query("
			SELECT threadid " . iif($sortby == 'rank', ', IF(views<=replycount, replycount+1, views) as views, replycount, votenum, votetotal, lastpost') . "
			FROM " . TABLE_PREFIX . "thread AS thread
			WHERE $threadids
			" . iif(!empty($threadQueryLogic), "AND " . implode("
			AND ", $threadQueryLogic)) . "
		");
		if ($sortby == 'rank')
		{
			$itemscores = array();
			$datescores = array();
			$mindate = TIMENOW;
			$maxdate = 0;
			while ($thread = $DB_site->fetch_array($threads))
			{
				if ($mindate > $thread['lastpost'])
				{
					$mindate = $thread['lastpost'];
				}
				if ($maxdate < $thread['lastpost'])
				{
					$maxdate = $thread['lastpost'];
				}
				$datescores["$thread[threadid]"] = $thread['lastpost'];
				$itemscores["$thread[threadid]"] = fetch_search_item_score($thread, $threadscores["$thread[threadid]"]);
			}
			unset($threadscores);
		}
		else
		{
			$itemids = array();
			while ($thread = $DB_site->fetch_array($threads))
			{
				$itemids["$thread[threadid]"] = true;
			}
		}
		unset($thread);
		$DB_site->free_result($threads);

	// #############################################################################
	// end show results as threads
	// #############################################################################
	}
	else
	{
	// #############################################################################
	// show results as posts
	// #############################################################################

		// #############################################################################
		// get post ids from post table
		$posts = $DB_site->query("
			SELECT postid, thread.title, post.dateline " . iif($sortby == 'rank', ', IF(thread.views=0, thread.replycount+1, thread.views) as views, thread.replycount, thread.votenum, thread.votetotal') . "
			FROM " . TABLE_PREFIX . "post AS post $postQueryIndex
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE " . implode(" AND ", $postQueryLogic) . "
			AND post.visible = 1
		");

		if ($sortby == 'rank')
		{
			$itemscores = array();
			$datescores = array();
			$mindate = TIMENOW;
			$maxdate = 0;
			while ($post = $DB_site->fetch_array($posts))
			{
				if ($mindate > $post['dateline'])
				{
					$mindate = $post['dateline'];
				}
				if ($maxdate < $post['dateline'])
				{
					$maxdate = $post['dateline'];
				}
				$datescores["$post[postid]"] = $post['dateline'];
				$itemscores["$post[postid]"] = fetch_search_item_score($post, $postscores["$post[postid]"]);
			}
			unset($postscores);
		}
		else
		{
			$itemids = array();
			while ($post = $DB_site->fetch_array($posts))
			{
				$itemids["$post[postid]"] = true;
			}
		}
		unset($post);
		$DB_site->free_result($post);

	}
	// #############################################################################
	// end show results as posts
	// #############################################################################


	// #############################################################################
	// now sort the results into order
	// #############################################################################

	// sort by relevance
	if ($sortby == 'rank')
	{
		if (empty($itemscores))
		{
			eval(print_standard_error('searchnoresults', 1, 0));
		}

		// add in date scores
		fetch_search_date_scores($datescores, $itemscores, $mindate, $maxdate);

		// sort the score results
		$sortfunc = iif($sortorder == 'asc', 'asort', 'arsort');
		$sortfunc($itemscores);

		// create the final result set
		$orderedids = array_keys($itemscores);
	}
	// sort by database field
	else
	{
		if (empty($itemids))
		{
			eval(print_standard_error('searchnoresults', 1, 0));
		}

		// remove dupes and make query condition
		$itemids = iif($showposts, 'postid', 'threadid') . ' IN(' . implode(',', array_keys($itemids)) . ')';

		// sort the results and create the final result set
		$orderedids = sort_search_items($itemids, $showposts, $sortby, $sortorder);
	}

	// #############################################################################
	// end sort the results into order
	// #############################################################################


	// get rid of unwanted gubbins
	unset($itemids, $threadids, $postids, $postscores, $threadscores, $itemscores, $datescores);

	// final check to see if we've actually got some results
	if (empty($orderedids))
	{
		eval(print_standard_error('searchnoresults', 1, 0));
	}

	// #############################################################################
	// finish search timer
	$searchtime = fetch_microtime_difference($searchstart);

	// #############################################################################
	// go through search words to build the display words for the results page summary bar

	if ($phrasequery)
	{
		$words = array();
		$display['words'] = array('"' . $phrasequery . '"');
		$display['common'] = array();
		$display['highlight'] = array($phrasequery);
		$query = '"' . $query . '"';
	}
	else
	{
		foreach ($words AS $wordtype => $searchwords)
		{
			switch($wordtype)
			{
				case 'AND':
					// do AND words
					foreach (array_keys($searchwords) AS $word)
					{
						$display['words'][] = $word;
					}
					break;
				case 'NOT':
					// do NOT words
					foreach (array_keys($searchwords) AS $word)
					{
						$display['words'][] = "</u></b>-<b><u>$word";
					}
					break;

				case 'OR':
					// do OR clauses
					foreach ($searchwords AS $orClause)
					{
						$or = array();
						foreach (array_keys($orClause) AS $orWord)
						{
							$or[] = $orWord;
						}
						$display['words'][] = implode('</u> OR <u>', $or);
					}
					break;

				default:
					// ignore COMMON words
			}
		}

		if ($vboptions['fulltextsearch'])
		{
			$display['words'] = preg_replace('#"(.+?)"#sie', "stripslashes(str_replace('*', ' ', '\\0'))", $display['words']);
		}
	}

	// make sure we have no duplicate entries in our $display array
	foreach (array_keys($display) AS $displaykey)
	{
		if ($displaykey != 'options' AND is_array($display["$displaykey"]))
		{
			$display["$displaykey"] = array_unique($display["$displaykey"]);
		}
	}

	// insert search results into search cache
	$DB_site->query("
		REPLACE INTO " . TABLE_PREFIX . "search (userid, ipaddress, query, searchuser, forumchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms, displayterms, searchhash)
		VALUES ($bbuserinfo[userid], '" . addslashes(IPADDRESS) . "', '" . addslashes($query) . "', '" . addslashes($searchuser) . "', '" . addslashes($forumchoice) . "', '" . addslashes($sortby) . "', '" . addslashes($sortorder) . "', $searchtime, $showposts, '" . implode(',', $orderedids) . "', " . TIMENOW . ", '" . addslashes(serialize($searchterms)) . "', '" . addslashes(serialize($display)) . "', '" . addslashes($searchhash) . "')
		### SAVE ORDERED IDS TO SEARCH CACHE ###
	");
	$searchid = $DB_site->insert_id();

	#$_REQUEST['forceredirect'] = 1;
	$url = "search.php?$session[sessionurl]searchid=$searchid";
	eval(print_standard_redirect('search'));

}

// #############################################################################
if ($_REQUEST['do'] == 'showresults')
{
	require_once('./includes/functions_forumdisplay.php');

	globalize($_REQUEST, array('searchid' => INT, 'pagenumber' => INT, 'perpage' => INT));

	// check for valid search result
	$gotsearch = false;
	if ($search =  $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "search AS search WHERE searchid = $searchid"))
	{
		// is this search customized for one user?
		if ($search['personal'])
		{
			// if search was by guest, do ip addresses match?
			if ($search['userid'] == 0 AND $search['ipaddress'] == IPADDRESS)
			{
				$gotsearch = true;
			}
			// if search was by reg.user, is it bbuser?
			else if ($search['userid'] == $bbuserinfo['userid'])
			{
				$gotsearch = true;
			}
		}
		// anyone can use this search result
		else
		{
			$gotsearch = true;
		}
	}
	if ($gotsearch == false)
	{
		eval(print_standard_error('searchnoresults', 1, 0));
	}

	// re-start the search timer
	$searchstart = microtime();

	// get the search terms that were used...
	$searchterms = unserialize($search['searchterms']);
	$searchquery = '';
	if (is_array($searchterms))
	{
		foreach ($searchterms AS $varname => $value)
		{
			if (is_array($value))
			{
				foreach ($value AS $value2)
				{
					$searchquery .= $varname . '[]=' . urlencode($value2) . '&amp;';
				}
			}
			else
			{
				$searchquery .= "$varname=" . urlencode($value) . '&amp;';
			}
		}
	}
	else
	{
		$searchquery = '';
	}

	// get the display stuff for the summary bar
	$display = unserialize($search['displayterms']);

	// $orderedids contains an ORDERED list of matching postids/threadids
	// EXCLUDING invisible and deleted items
	$orderedids = explode(',', $search['orderedids']);
	$numitems = sizeof($orderedids);

	// #############################################################################
	// #############################################################################

	// start the timer for the permissions check
	$go = microtime();

	// #############################################################################
	// don't retrieve tachy'd posts/threads
	require_once('./includes/functions_bigthree.php');
	if ($coventry = fetch_coventry('string'))
	{
		$coventry_post = "AND post.userid NOT IN ($coventry)";
		$coventry_thread = "AND thread.postuserid NOT IN ($coventry)";
	}

	// now check to see if the results can be viewed / searched etc.
	if ($search['showposts'])
	{
		// query posts
		$permQuery = "
			SELECT postid AS itemid, thread.forumid,
			IF(postuserid = $bbuserinfo[userid], 'self', 'other') AS starter
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS delpost ON(delpost.primaryid = post.postid AND delpost.type = 'post')
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS delthread ON(delthread.primaryid = thread.threadid AND delthread.type = 'thread')
			WHERE postid IN(" . implode(', ', $orderedids) . ")
			AND delpost.primaryid IS NULL
			AND delthread.primaryid IS NULL
			AND thread.open <> 10
			AND thread.visible = 1
			$coventry_post
			$coventry_thread
		";
		// query post data
		$dataQuery = "
			SELECT post.postid, post.title AS posttitle, post.dateline AS postdateline,
				post.iconid AS posticonid, post.pagetext,
				IF(post.userid = 0, post.username, user.username) AS username,
				thread.threadid, thread.title AS threadtitle, thread.iconid AS threadiconid, thread.replycount,
				IF(thread.views=0, thread.replycount+1, thread.views) as views,
				thread.pollid, thread.sticky, thread.open, thread.lastpost, thread.forumid,
				user.userid, user.username
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
			WHERE post.postid IN";
	}
	else
	{
		// query threads
		$permQuery = "
			SELECT threadid AS itemid, forumid,
			IF(postuserid = $bbuserinfo[userid], 'self', 'other') AS starter
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS delthread ON(delthread.primaryid = thread.threadid AND delthread.type = 'thread')
			WHERE threadid IN(" . implode(', ', $orderedids) . ")
			AND delthread.primaryid IS NULL
			AND thread.open <> 10
			AND thread.visible = 1
			$coventry_thread
		";

		if ($vboptions['threadpreview'] > 0)
		{
			$previewfield = 'post.pagetext AS preview,';
			$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
		}
		else
		{
			$previewfield = '';
			$previewjoin = '';
		}

		// query thread data
		$dataQuery = "
			SELECT $previewfield
				thread.threadid, thread.threadid AS postid, thread.title AS threadtitle, thread.iconid AS threadiconid,
				thread.replycount, IF(thread.views=0, thread.replycount+1, thread.views) as views, thread.sticky,
				thread.pollid, thread.open, thread.lastpost AS postdateline,
				thread.lastpost, thread.lastposter, thread.attach, thread.postusername, thread.forumid,
				user.userid AS postuserid
				" . iif($vboptions['threadsubscribed'] AND $bbuserinfo['userid'], ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed") . "
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = thread.postuserid)
			" . iif($vboptions['threadsubscribed'] AND $bbuserinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread
				ON(subscribethread.threadid = thread.threadid AND subscribethread.userid = $bbuserinfo[userid])") . "
			$previewjoin
			WHERE thread.threadid IN
		";
	}

	$tmp = array();
	$items = $DB_site->query($permQuery);
	unset($permQuery);
	while ($item = $DB_site->fetch_array($items))
	{
		$tmp["$item[forumid]"]["$item[starter]"][] = $item['itemid'];
	}
	unset($item);
	$DB_site->free_result($items);

	// query moderators for forum password purposes
	cache_moderators();

	foreach (array_keys($tmp) AS $forumid)
	{
		$forum = &$forumcache["$forumid"];
		$fperms = &$bbuserinfo['forumpermissions']["$forumid"];

		$items = vb_number_format(sizeof($tmp["$forumid"]['self']) + sizeof($tmp["$forumid"]['other']));

		// check CANVIEW / CANSEARCH permission and forum password for current forum
		if (!($fperms & CANVIEW) OR !($fperms & CANSEARCH) OR !verify_forum_password($forumid, $forum['password'], false) OR ($vboptions['fulltextsearch'] AND !($_FORUMOPTIONS['indexposts'] & $forumcache["$forumid"]['options'])))
		{
			// cannot view / search this forum, or does not have forum password
			unset($tmp["$forumid"]);
		}
		else
		{
			// get last read info
			if ($bbforumview = intval(fetch_bbarray_cookie('forum_view', $forumid)))
			{
				$lastread["$forumid"] = $bbforumview;
			}
			else
			{
				$lastread["$forumid"] = $bbuserinfo['lastvisit'];
			}

			// check CANVIEWOTHERS permission
			if (!($fperms & CANVIEWOTHERS))
			{
				// cannot view others' threads
				unset($tmp["$forumid"]['other']);
			}
		}

		$items = vb_number_format(sizeof($tmp["$forumid"]['self']) + sizeof($tmp["$forumid"]['other']));
	}

	// now get all threadids that still remain...
	$remaining = array();
	$i = 1;
	foreach ($tmp AS $A)
	{
		foreach ($A AS $B)
		{
			foreach ($B AS $itemid)
			{
				$remaining["$itemid"] = $itemid;
			}
		}
	}
	unset($tmp, $A, $B);

	// remove all ids from $orderedids that do not exist in $remaining
	$orderedids = array_intersect($orderedids, $remaining);
	unset($remaining);

	// rebuild the $orderedids array so keys go from 0 to n with no gaps
	$orderedids = array_merge($orderedids, array());

	// count the number of items
	$numitems = sizeof($orderedids);

	// do we still have some results?
	if ($numitems == 0)
	{
		eval(print_standard_error('searchnoresults', 1, 0));
	}

	DEVDEBUG('time to check permissions: ' . vb_number_format(fetch_microtime_difference($go), 4));

	// extra check to prevent DB error if someone sets it at 0
	if ($vboptions['searchperpage'] < 1)
	{
		$vboptions['searchperpage'] = 20;
	}

	// trim results down to maximum $vboptions[maxresults]
	if ($vboptions['maxresults'] > 0 AND $numitems > $vboptions['maxresults'])
	{
		$clippedids = array();
		for ($i = 0; $i < $vboptions['maxresults']; $i++)
		{
			$clippedids[] = $orderedids["$i"];
		}
		$orderedids = &$clippedids;
		$numitems = $vboptions['maxresults'];
	}

	// #############################################################################
	// #############################################################################

	// get page split...
	sanitize_pageresults($numitems, $pagenumber, $perpage, 200, $vboptions['searchperpage']);

	// get list of thread to display on this page
	$startat = ($pagenumber - 1) * $perpage;
	$endat = $startat + $perpage;
	$itemids = array();
	for ($i = $startat; $i < $endat; $i++)
	{
		if (isset($orderedids["$i"]))
		{
			$itemids["$orderedids[$i]"] = true;
		}
	}

	// #############################################################################
	// do data query
	$ids = implode(', ', array_keys($itemids));
	$dataQuery .= '(' . $ids . ')';
	$items = $DB_site->query($dataQuery);
	$itemidname = iif($search['showposts'], 'postid', 'threadid');

	$dotthreads = fetch_dot_threads_array($ids);

	// end search timer
	$searchtime = vb_number_format(fetch_microtime_difference($searchstart, $search['searchtime']), 2);

	while ($item = $DB_site->fetch_array($items))
	{
		$item['forumtitle'] = $forumcache["$item[forumid]"]['title'];
		$itemids["$item[$itemidname]"] = $item;
	}
	unset($item, $dataQuery);
	$DB_site->free_result($items);
	// #############################################################################


	// get highlight words
	if (!empty($display['highlight']))
	{
		$highlightwords = '&amp;highlight=' . urlencode(implode(' ', $display['highlight']));
	}
	else
	{
		$highlightwords = '';
	}

	// get iconcache
	$iconcache = unserialize($datastore['iconcache']);

	// get correct number for maxposts to display on showthread
	if (($bbuserinfo['maxposts'] != -1) AND ($bbuserinfo['maxposts'] != 0))
	{
		$vboptions['maxposts'] = $bbuserinfo['maxposts'];
	}

	// initialize counters and template bits
	$searchbits = '';
	$itemcount = $startat;
	$first = $itemcount + 1;

	if ($vboptions['threadpreview'] AND $bbuserinfo['ignorelist'])
	{
		// Get Buddy List
		$buddy = array();
		if (trim($bbuserinfo['buddylist']))
		{
			$buddylist = preg_split('/( )+/', trim($bbuserinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
				foreach ($buddylist AS $buddyuserid)
			{
				$buddy["$buddyuserid"] = 1;
			}
		}
		DEVDEBUG('buddies: ' . implode(', ', array_keys($buddy)));
		// Get Ignore Users
		$ignore = array();
		if (trim($bbuserinfo['ignorelist']))
		{
			$ignorelist = preg_split('/( )+/', trim($bbuserinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
			foreach ($ignorelist AS $ignoreuserid)
			{
				if (!$buddy["$ignoreuserid"])
				{
					$ignore["$ignoreuserid"] = 1;
				}
			}
		}
		DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));
	}

	// #############################################################################
	// show results as posts
	if ($search['showposts'])
	{
		foreach ($itemids AS $post)
		{
			// do post folder icon
			if ($post['postdateline'] > $bbuserinfo['lastvisit'])
			{
				$post['post_statusicon'] = 'new';
				$post['post_statustitle'] = $vbphrase['unread'];
			}
			else
			{
				$post['post_statusicon'] = 'old';
				$post['post_statustitle'] = $vbphrase['old'];
			}

			// allow icons?
			$post['allowicons'] = $forumcache["$post[forumid]"]['options'] & $_FORUMOPTIONS['allowicons'];

			// get POST icon from icon cache
			$post['posticonpath'] = &$iconcache["$post[posticonid]"]['iconpath'];
			$post['posticontitle'] = &$iconcache["$post[posticonid]"]['title'];

			// show post icon?
			if ($post['allowicons'])
			{
				// show specified icon
				if ($post['posticonpath'])
				{
					$post['posticon'] = true;
				}
				// show default icon
				else if (!empty($vboptions['showdeficon']))
				{
					$post['posticon'] = true;
					$post['posticonpath'] = $vboptions['showdeficon'];
					$post['posticontitle'] = '';
				}
				// do not show icon
				else
				{
					$post['posticon'] = false;
					$post['posticonpath'] = '';
					$post['posticontitle'] = '';
				}
			}
			// do not show post icon
			else
			{
				$post['posticon'] = false;
				$post['posticonpath'] = '';
				$post['posticontitle'] = '';
			}

			$post['pagetext'] = preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siUe', "process_quote_removal('\\3', \$display['highlight'])", $post['pagetext']);

			// get first 200 chars of page text
			$post['pagetext'] = htmlspecialchars_uni(fetch_censored_text(trim(fetch_trimmed_title(strip_bbcode($post['pagetext'], 1), 200))));

			// get post title
			if ($post['posttitle'] == '')
			{
				$post['posttitle'] = fetch_trimmed_title($post['pagetext'], 50);
			}

			// format post text
			$post['pagetext'] = nl2br($post['pagetext']);

			// get highlight words
			$post['highlight'] = &$highlightwords;

			// get info from post
			$post = process_thread_array($post, $lastread["$post[forumid]"], $post['allowicons']);

			$itemcount ++;
			exec_switch_bg();
			eval('$searchbits .= "' . fetch_template('search_results_postbit') . '";');
		}

	}
	// #############################################################################
	// show results as threads
	else
	{
		$show['threadicons'] = true;
		$show['forumlink'] = true;

		foreach ($itemids AS $thread)
		{
			// add highlight words
			$thread['highlight'] = &$highlightwords;

			// get info from thread
			$thread = process_thread_array($thread, $lastread["$thread[forumid]"]);

			$itemcount++;
			exec_switch_bg();
			eval('$searchbits .= "' . fetch_template('threadbit') . '";');
		}
	}
	// #############################################################################

	$last = $itemcount;

	$pagenav = construct_page_nav($numitems, "search.php?$session[sessionurl]searchid=$searchid&amp;pp=$perpage");

	// #############################################################################
	// get the bits for the summary bar
	if (!empty($display['words']))
	{
		foreach ($display['words'] AS $key => $val)
		{
			$display['words']["$key"] = htmlspecialchars_uni($val);
		}
		$display['words'] = str_replace(array('&lt;/u&gt;&lt;/b&gt;-&lt;b&gt;&lt;u&gt;', '&lt;/u&gt; OR &lt;u&gt;'),array('</u></b>-<b><u>', '</u> OR <u>'),$display['words']);
		$displayWords = '<b><u>' . implode('</u></b>, <b><u>', $display['words']) . '</u></b>';
	}
	else
	{
		$displayWords = '';
	}
	if (!empty($display['common']))
	{
		$displayCommon = '<b><u>' . implode('</u></b>, <b><u>', htmlspecialchars_uni($display['common'])) . '</u></b>';
	}
	else
	{
		$displayCommon = '';
	}
	if (!empty($display['users']))
	{
		foreach ($display['users'] AS $userid => $username)
		{
			$display['users']["$userid"] = "<a href=\"member.php?$session[sessionurl]u=$userid\"><b><u>$username</u></b></a>";
		}
		$displayUsers = implode(" $vbphrase[or] ", $display['users']);
	}
	else
	{
		$displayUsers = '';
	}
	if (!empty($display['forums']))
	{
		foreach ($display['forums'] AS $key => $forumid)
		{
			$display['forums']["$key"] = "<a href=\"forumdisplay.php?$session[sessionurl]f=$forumid\"><b><u>" . $forumcache["$forumid"]['title'] . '</u></b></a>';
		}
		$displayForums = implode(" $vbphrase[or] ", $display['forums']);
	}
	else
	{
		$displayForums = '';
	}
	$starteronly = &$display['options']['starteronly'];
	$childforums = &$display['options']['childforums'];
	$action = &$display['options']['action'];

	if ($vboptions['fulltextsearch'])
	{
		DEVDEBUG('FULLTEXT Search');
	}
	else
	{
		DEVDEBUG('Default Search');
	}

	// select the correct part of the forum jump menu
	$frmjmpsel['search'] = 'class="fjsel" selected="selected"';
	construct_forum_jump();

	// add to the navbits
	$navbits[''] = $vbphrase['search_results'];

	$templatename = 'search_results';
}

// #############################################################################
if ($_REQUEST['do'] == 'getnew' OR $_REQUEST['do'] == 'getdaily')
{
	globalize($_REQUEST, array(
		'forumid'   => INT,
		'days'      => INT,
		'exclude'   => STR,
		'showposts' => INT,
	));

	// get date:
	if ($_REQUEST['do'] == 'getnew' AND $bbuserinfo['lastvisit'] != 0)
	{
		// if action = getnew and last visit date is set
		$datecut = $bbuserinfo['lastvisit'];
	}
	else
	{
		$_REQUEST['do'] = 'getdaily';
		if ($days < 1)
		{
			$days = 1;
		}
		$datecut = TIMENOW - (24 * 60 * 60 * $days);
	}

	// build search hash
	$searchhash = md5($bbuserinfo['userid'] . IPADDRESS . $forumid . $days . $bbuserinfo['lastvisit']);

	// start search timer
	$searchtime = microtime();

	// if forumid is specified, get list of ids
	if ($forumid)
	{
		// check forum exists
		if (isset($forumcache["$forumid"]))
		{
			$display['forums'][] = $forumid;
			// check forum permissions
			if (($bbuserinfo['forumpermissions']["$forumid"] & CANVIEW) AND ($bbuserinfo['forumpermissions']["$forumid"] & CANSEARCH))
			{
				$forumids = fetch_search_forumids($forumid, 1);
			}
			else
			{
				// can not view specified forum
				$idname = $vbphrase['forum'];
				eval(print_standard_error('invalidid'));
			}
		}
		else
		{
			// specified forum does not exist
			$idname = $vbphrase['forum'];
			eval(print_standard_error('invalidid'));
		}
	}
	// forumid is not specified, get list of all forums user can view
	else
	{
		if ($exclude)
		{
			$excludelist = explode(',', $exclude);
			foreach ($excludelist AS $key => $excludeid)
			{
				$excludeid = intval($excludeid);
				unset($forumcache["$excludeid"]);
			}
		}
		$forumids = array_keys($forumcache);
	}

	// set display terms
	$display = array(
		'words' => array(),
		'highlight' => array(),
		'common' => array(),
		'users' => array(),
		'forums' => $display['forums'],
		'options' => array(
			'starteronly' => 0,
			'childforums' => 1,
			'action' => $_REQUEST['do']
		)
	);

	// get moderator cache for forum password purposes
	cache_moderators();

	// get forum ids for all forums user is allowed to view
	foreach ($forumids AS $key => $forumid)
	{
		$fperms = &$bbuserinfo['forumpermissions']["$forumid"];
		$forum = &$forumcache["$forumid"];

		if (!($fperms & CANVIEW) OR !($fperms & CANSEARCH) OR !verify_forum_password($forumid, $forum['password'], false))
		{
			unset($forumids["$key"]);
		}
	}

	if (empty($forumids))
	{
		$idname = $vbphrase['forum'];
		eval(print_standard_error('invalidid'));
	}

	$orderedids = array();
	if ($showposts)
	{
		$posts = $DB_site->query("
			SELECT postid
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
			WHERE forumid IN(" . implode(', ', $forumids) . ")
			AND thread.lastpost >= $datecut
			AND thread.visible = 1
			AND sticky IN (0,1)
			AND post.dateline >= $datecut
			AND post.visible = 1
			ORDER BY post.dateline DESC
			LIMIT " . intval($vboptions['maxresults']) . "
		");

		while ($post = $DB_site->fetch_array($posts))
		{
			$orderedids[] = $post['postid'];
		}
	}
	else
	{
		$threads = $DB_site->query("
			SELECT threadid
			FROM " . TABLE_PREFIX . "thread AS thread
			WHERE forumid IN(" . implode(', ', $forumids) . ")
			AND thread.lastpost >= $datecut
			AND visible = 1
			AND sticky IN (0,1)
			ORDER BY lastpost DESC
			LIMIT " . intval($vboptions['maxresults']) . "
		");

		while ($thread = $DB_site->fetch_array($threads))
		{
			$orderedids[] = $thread['threadid'];
		}
	}

	if (empty($orderedids))
	{
		eval(print_standard_error('searchnoresults', 1, 0));
	}

	$sql_ids = addslashes(implode(',', $orderedids));
	unset($orderedids);

	// check for previous searches
	if ($search = $DB_site->query_first("SELECT searchid FROM " . TABLE_PREFIX . "search AS search WHERE userid = $bbuserinfo[userid] AND searchhash = '" . addslashes($searchhash) . "' AND orderedids = '$sql_ids'"))
	{
		// search has been done previously
		$url = "search.php?$session[sessionurl]searchid=$search[searchid]";
		eval(print_standard_redirect('redirect_search'));
	}

	// end search timer
	$searchtime = fetch_microtime_difference($searchtime);

	$DB_site->query("
		REPLACE INTO " . TABLE_PREFIX . "search (userid, showposts, ipaddress, personal, forumchoice, sortby, sortorder, searchtime, orderedids, dateline, displayterms, searchhash)
		VALUES ($bbuserinfo[userid], $showposts, '" . addslashes(IPADDRESS) . "', 1, '" . addslashes($forumid) . "', 'lastpost', 'DESC', $searchtime, '$sql_ids', " . TIMENOW . ", '" . addslashes(serialize($display)) . "', '" . addslashes($searchhash) . "')
	");
	$searchid = $DB_site->insert_id();

	$url = "search.php?$session[sessionurl]searchid=$searchid";
	eval(print_standard_redirect('search'));
}

// #############################################################################
if ($_REQUEST['do'] == 'finduser')
{
	globalize($_REQUEST, array('userid' => INT, 'forumid' => INT));

	// valid user id?
	if ($userid == 0)
	{
		$idname = $vbphrase['user'];
		eval(print_standard_error('invalidid'));
	}

	// get user info
	if ($user = $DB_site->query_first("SELECT userid, username, posts FROM " . TABLE_PREFIX . "user WHERE userid = $userid"))
	{
		$searchuser = &$user['username'];
	}
	// could not find specified user
	else
	{
		$idname = $vbphrase['user'];
		eval(print_standard_error('invalidid'));
	}

	// #############################################################################
	// build search hash
	$query = '';
	$searchuser = $user['username'];
	$exactname = 1;
	$starteronly = 0;
	$forumchoice = $forumid;
	$childforums = 1;
	$titleonly = 0;
	$showposts = 1;
	$searchdate = 0;
	$beforeafter = 'after';
	$replyless = 0;
	$replylimit = 0;
	$searchthreadid = 0;

	$searchhash = md5(TIMENOW . "||$bbuserinfo[userid]||" . strtolower($searchuser) . "||$exactname||$starteronly||$forumchoice||$childforums||$titleonly||$showposts||$searchdate||$beforeafter||$replyless||$replylimit||$searchthreadid");

	// check if search already done
	//if ($search = $DB_site->query_first("SELECT searchid FROM " . TABLE_PREFIX . "search AS search WHERE searchhash = '" . addslashes($searchhash) . "'"))
	//{
	//	$url = "search.php?$session[sessionurl]searchid=$search[searchid]";
	//	eval(print_standard_redirect('search'));
	//}

	// start search timer
	$searchtime = microtime();

	// #############################################################################
	// check to see if we should be searching in a particular forum or forums
	if ($forumids = fetch_search_forumids($forumchoice, $childforums))
	{
		$forumids = 'thread.forumid IN(' . implode(',', $forumids) . ')';
		$showforums = true;
	}
	else
	{
		$forumids = '0';
		foreach ($forumcache AS $forumid => $forum)
		{
			$fperms = &$bbuserinfo['forumpermissions']["$forumid"];
			if (($fperms & CANVIEW) AND ($fperms & CANVIEWOTHERS))
			{
				$forumids .= ",$forumid";
			}
		}
		$forumids = "thread.forumid IN($forumids)";
		$showforums = false;
	}

	// query post ids in dateline DESC order...
	$orderedids = array();
	$posts = $DB_site->query("
		SELECT postid
		FROM " . TABLE_PREFIX . "post AS post " . iif($forumids, "
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS delthread ON(delthread.primaryid = post.threadid AND delthread.type = 'thread')") . "
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS delpost ON(delpost.primaryid = post.postid AND delpost.type = 'post')
		WHERE post.userid = $user[userid]
		AND delpost.primaryid IS NULL " . iif($forumids, "
		AND delthread.primaryid IS NULL
		AND post.visible = 1
		AND thread.visible = 1
		AND $forumids") . "
		ORDER BY post.dateline DESC
		LIMIT " . ($vboptions['maxresults'] * 2) . "
	");
	while ($post = $DB_site->fetch_array($posts))
	{
		$orderedids[] = $post['postid'];
	}
	unset($post);
	$DB_site->free_result($posts);

	// did we get some results?
	if (empty($orderedids))
	{
		eval(print_standard_error('searchnoresults', 1, 0));
	}

	// set display terms
	$display = array(
		'words' => array(),
		'highlight' => array(),
		'common' => array(),
		'users' => array($user['userid'] => $user['username']),
		'forums' => iif($showforums, $display['forums'], 0),
		'options' => array(
			'starteronly' => 0,
			'childforums' => 1,
			'action' => 'process'
		)
	);

	// end search timer
	$searchtime = fetch_microtime_difference($searchtime);

	$DB_site->query("
		REPLACE INTO " . TABLE_PREFIX . "search (userid, ipaddress, personal, searchuser, forumchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, displayterms, searchhash)
		VALUES ($bbuserinfo[userid], '" . addslashes(IPADDRESS) . "', 1, '" . addslashes($user['username']) . "', '" . addslashes($forumchoice) . "', 'post.dateline', 'DESC', $searchtime, 1, '" . addslashes(implode(',', $orderedids)) . "', " . TIMENOW . ", '" . addslashes(serialize($display)) . "', '" . addslashes($searchhash) . "')
	");
	$searchid = $DB_site->insert_id();

	$url = "search.php?$session[sessionurl]searchid=$searchid";
	eval(print_standard_redirect('search'));

}

// #############################################################################
if ($_POST['do'] == 'doprefs')
{
	globalize($_POST, $globalize);

	if ($bbuserinfo['userid'])
	{
		// save preferences
		if ($saveprefs)
		{
			$prefs = addslashes(serialize(array(
				'exactname' => $exactname,
				'starteronly' => $starteronly,
				'childforums' => $childforums,
				'showposts' => $showposts,
				'titleonly' => $titleonly,
				'searchdate' => $searchdate,
				'beforeafter' => $beforeafter,
				'sortby' => $sortby,
				'sortorder' => $sortorder,
				'replyless' => $replyless,
				'replylimit' => $replylimit
			)));
			$DB_site->query("UPDATE " . TABLE_PREFIX . "usertextfield SET searchprefs = '$prefs' WHERE userid = $bbuserinfo[userid]");
			unset($prefs);
		}
		// clear preferences (only if prefs are set)
		else if ($bbuserinfo['searchprefs'] != '')
		{
			unset($globalize);
			$DB_site->query("UPDATE " . TABLE_PREFIX . "usertextfield SET searchprefs = '' WHERE userid = $bbuserinfo[userid]");
		}

		$url = "search.php?$session[sessionurl]";
		if (!empty($globalize))
		{
			foreach (array_keys($globalize) AS $varname)
			{
				if ($varname == 'forumchoice' AND is_array($forumchoice))
				{
					foreach ($forumchoice AS $forumid)
					{
						$url .= "forumchoice[]=" . urlencode($forumid) . "&amp;";
					}
				}
				else
				{
					$url .= "$varname=" . urlencode($$varname) . '&amp;';
				}
			}
			$url = substr($url, 0, -5);
		}

		$_REQUEST['forceredirect'] = 1;
		eval(print_standard_redirect('search_preferencessaved'));
	}
}

// #############################################################################
// finish off the page

if ($templatename != '')
{
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template($templatename) . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14028 $
|| ####################################################################
\*======================================================================*/
?>
