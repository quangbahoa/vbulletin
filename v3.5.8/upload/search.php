<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
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
define('THIS_SCRIPT', 'search');
define('ALTSEARCH', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('search', 'inlinemod');

// get special data templates from the datastore
$specialtemplates = array(
	'iconcache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'search_forums',
	'search_results',
	'search_results_postbit', // result from search posts
	'search_results_postbit_lastvisit',
	'threadbit', // result from search threads
	'threadbit_deleted', // result from deleted search threads
	'threadbit_lastvisit',
	'newreply_reviewbit_ignore',
	'threadadmin_imod_menu_thread',
	'threadadmin_imod_menu_post',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_search.php');
require_once(DIR . '/includes/functions_forumlist.php');
require_once(DIR . '/includes/functions_misc.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['cansearch']))
{
	print_no_permission();
}

if (!$vbulletin->options['enablesearches'])
{
	eval(standard_error(fetch_error('searchdisabled')));
}

// #############################################################################

$globals = array(
	'query'          => TYPE_STR,
	'searchuser'     => TYPE_STR,
	'exactname'      => TYPE_BOOL,
	'starteronly'    => TYPE_BOOL,
	'forumchoice'    => TYPE_ARRAY,
	'childforums'    => TYPE_BOOL,
	'titleonly'      => TYPE_BOOL,
	'showposts'      => TYPE_BOOL,
	'searchdate'     => TYPE_NOHTML,
	'beforeafter'    => TYPE_NOHTML,
	'sortby'         => TYPE_NOHTML,
	'sortorder'      => TYPE_NOHTML,
	'replyless'      => TYPE_UINT,
	'replylimit'     => TYPE_UINT,
	'searchthread'   => TYPE_BOOL,
	'searchthreadid' => TYPE_UINT,
	'saveprefs'      => TYPE_BOOL,
	'quicksearch'    => TYPE_BOOL,
	'searchtype'     => TYPE_BOOL,
	'exclude'        => TYPE_NOHTML,
);

$vbulletin->input->clean_array_gpc('r', array(
	'doprefs'    => TYPE_NOHTML,
	'searchtype' => TYPE_BOOL,
	'searchid'   => TYPE_UINT,
));

// #############################################################################

if (empty($_REQUEST['do']))
{
	if ($vbulletin->GPC['searchid'])
	{
		$_REQUEST['do'] = 'showresults';
	}
	else
	{
		$_REQUEST['do'] = 'intro';
	}
}

if ($vbulletin->options['fulltextsearch'])
{
	if ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cansearchft_bool'])
	{
		// verify that server can support boolean mode
		$mysqlversion = $db->query_first("
			SELECT version() AS version
		");
		if (version_compare($mysqlversion['version'], '4.0.1', '<'))
		{
			// MySQL doesn't support boolean mode
		 	$vbulletin->GPC['searchtype'] = 0;
		}
		else
		{
			// use boolean when user has boolean, ignore NL
			$vbulletin->GPC['searchtype'] = 1;
		}
	}
	else
	{
		// user only has permission to use nl search
		$vbulletin->GPC['searchtype'] = 0;
	}
}

// check for extra variables from the advanced search form
if ($_POST['do'] == 'process')
{
	// don't go to do=process, go to do=doprefs
	if ($vbulletin->GPC['doprefs'] != '')
	{
		$_POST['do'] = 'doprefs';
		$_REQUEST['do'] = 'doprefs';
	}
}

// #############################################################################
if (in_array($_REQUEST['do'], array('intro', 'showresults', 'doprefs')) == false)
{
	// get last search for this user and check floodcheck
	if ($prevsearch = $db->query_first("
		SELECT searchid, dateline
		FROM " . TABLE_PREFIX . "search AS search
		WHERE " . iif(!$vbulletin->userinfo['userid'], "ipaddress ='" . $db->escape_string(IPADDRESS) . "'", "userid = " . $vbulletin->userinfo['userid']) . "
		ORDER BY dateline DESC LIMIT 1
	"))
	{
		if (($timepassed = TIMENOW - $prevsearch['dateline']) < $vbulletin->options['searchfloodtime'] AND $vbulletin->options['searchfloodtime'] != 0 AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND !can_moderate())
		{
			eval(standard_error(fetch_error('searchfloodcheck', $vbulletin->options['searchfloodtime'], ($vbulletin->options['searchfloodtime'] - $timepassed))));
		}
	}
}

// make first part of navbar
$navbits = array('search.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['search_forums']);

($hook = vBulletinHook::fetch_hook('search_start')) ? eval($hook) : false;

// #############################################################################
if ($_REQUEST['do'] == 'intro')
{
	$vbulletin->input->clean_array_gpc('r', $globals);

	// get list of forums moderated by this user to bypass password check
	$modforums = array();
	if ($vbulletin->userinfo['userid'] AND (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])) AND (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])))
	{
		// only do this query if the user is logged in, and is not a super mod or an admin
		DEVDEBUG('Querying moderators');
		cache_moderators();
	}

	// #############################################################################
	// read user's search preferences
	$prefs  = array(
		'exactname'   => 1,
		'starteronly' => 0,
		'childforums' => 1,
		'showposts'   => 0,
		'titleonly'   => 0,
		'searchdate'  => 0,
		'beforeafter' => 'after',
		'sortby'      => 'lastpost',
		'sortorder'   => 'descending',
		'replyless'   => 0,
		'replylimit'  => 0,
	);

	if ($vbulletin->userinfo['searchprefs'] != '')
	{
		$prefs = array_merge($prefs, unserialize($vbulletin->userinfo['searchprefs']));
	}

	// if $forumid is specified, use it
	if ($foruminfo['forumid'])
	{
		$vbulletin->GPC['forumchoice'][] = $foruminfo['forumid'];
	}

	// if search conditions are specified in the URI, use them
	foreach (array_keys($globals) AS $varname)
	{
		if ($vbulletin->GPC_exists["$varname"] AND $varname != 'forumchoice')
		{
			$prefs["$varname"] = $vbulletin->GPC["$varname"];
		}
	}

	// now check approprate boxes, select menus etc...
	foreach ($prefs AS $varname => $value)
	{
		$$varname = htmlspecialchars_uni($value);
		$checkedvar = $varname . 'checked';
		$selectedvar = $varname . 'selected';
		$$checkedvar = array($value => 'checked="checked"');
		$$selectedvar = array($value => 'selected="selected"');
	}

	// now get the IDs of the forums we are going to display
	fetch_search_forumids_array();

	$searchforumbits = '';
	$haveforum = false;

	foreach ($searchforumids AS $forumid)
	{
		$forum =& $vbulletin->forumcache["$forumid"];

		if (trim($forum['link']))
		{
			continue;
		}

		$optionvalue = $forumid;
		$optiontitle = "$forum[depthmark] $forum[title_clean]";
		if ($vbulletin->options['fulltextsearch'] AND !($vbulletin->userinfo['forumpermissions']["$forumid"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		{
			$optiontitle .= '*';
			$show['cantsearchposts'] = true;
		}
		$optionclass = 'fjdpth' . iif($forum['depth'] > 4, 4, $forum['depth']);

		if (in_array($forumid, $vbulletin->GPC['forumchoice']))
		{
			$optionselected = 'selected="selected"';
			$haveforum = true;
		}
		else
		{
			$optionselected = '';
		}

		eval('$searchforumbits .= "' . fetch_template('option') . '";');
	}

	$noforumselected = iif(!$haveforum, 'selected="selected"');

	// select the correct part of the forum jump menu
	$frmjmpsel['search'] = 'class="fjsel" selected="selected"';
	construct_forum_jump();

	// unlink the 'search' part of the navbits
	array_pop($navbits);

	$navbits[''] = $vbphrase['search_forums'];

	($hook = vBulletinHook::fetch_hook('search_intro')) ? eval($hook) : false;

	$templatename = 'search_forums';
}

// #############################################################################
if ($_REQUEST['do'] == 'process')
{
	$vbulletin->input->clean_array_gpc('r', $globals);

	($hook = vBulletinHook::fetch_hook('search_process_start')) ? eval($hook) : false;

	// #############################################################################
	// start search timer
	$searchstart = microtime();

	// #############################################################################
	// error if no search terms
	if (empty($vbulletin->GPC['query']) AND empty($vbulletin->GPC['searchuser']) AND empty($vbulletin->GPC['replyless']))
	{
		eval(standard_error(fetch_error('searchspecifyterms')));
	}

	// #############################################################################
	// if searching within a thread, $showposts must be true and sorting should be "dateline ASC"
	if ($vbulletin->GPC['searchthreadid'])
	{
		$vbulletin->GPC['showposts'] = true;
		$vbulletin->GPC['sortby'] = 'dateline';
		$vbulletin->GPC['sortorder'] = 'ASC';
		$vbulletin->GPC['forumchoice'] = array();
		$vbulletin->GPC['titleonly'] = false;
		$vbulletin->GPC['searchuser'] = '';
		$vbulletin->GPC['replyless'] = false;
		$vbulletin->GPC['replylimit'] = false;
	}

	// #############################################################################
	// make array of search terms for back referencing
	$searchterms = array();
	foreach ($globals AS $varname => $value)
	{
		if ($varname == 'forumchoice' AND is_array($vbulletin->GPC['forumchoice']))
		{
			$searchterms["$varname"] = $vbulletin->GPC['forumchoice'];
		}
		else
		{
			$searchterms["$varname"] = $vbulletin->GPC["$varname"];
		}
	}

	// #############################################################################
	// if query string is specified, check syntax and replace common syntax errors
	if ($vbulletin->GPC['query'])
	{
		// are we using FT and boolean search?
		if ($vbulletin->options['fulltextsearch'] AND $vbulletin->GPC['searchtype'])
		{
			// look for entire words that consist of "&#1234;". MySQL boolean
			// search will tokenize them seperately. Wrap them in quotes if they're
			// not already to emulate search for exactly that word.
			$query = explode('"', $vbulletin->GPC['query']);
			$query_part_count = count($query);

			$vbulletin->GPC['query'] = '';
			for ($i = 0; $i < $query_part_count; $i++)
			{
				// exploding by " means the 0th, 2nd, 4th... entries in the array
				// are outside of quotes
				if ($i % 2 == 1)
				{
					// 1st, 3rd.. entry = in quotes
					$vbulletin->GPC['query'] .= '"' . $query["$i"] . '"';
				}
				else
				{
					// look for words that are entirely &#1234;
					$vbulletin->GPC['query'] .= preg_replace(
						'/(?<=^|\s)((&#[0-9]+;)+)(?=\s|$)/',
						'"$1"',
						$query["$i"]
					);
				}
			}

			$vbulletin->GPC['query'] = preg_replace(
				'#"([^"]+)"#sie',
				"stripslashes(str_replace(' ' , '*', '\\0'))",
				$vbulletin->GPC['query']
			);
			// what about replacement words??
		}
		$vbulletin->GPC['query'] = sanitize_search_query($vbulletin->GPC['query']);
	}

	// #############################################################################
	// get forums in which to search
	$forumchoice = implode(',', fetch_search_forumids($vbulletin->GPC['forumchoice'], $vbulletin->GPC['childforums']));

	// #############################################################################
	// get correct sortby value
	$vbulletin->GPC['sortby'] = strtolower($vbulletin->GPC['sortby']);
	switch($vbulletin->GPC['sortby'])
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
			$vbulletin->GPC['sortby'] = 'forum.title';
			break;

		case 'threadstart':
			$vbulletin->GPC['sortby'] = 'thread.dateline';
			break;

		// set default sortby if not specified or unrecognized
		default:
			$vbulletin->GPC['sortby'] = 'lastpost';
	}

	// #############################################################################
	// if showing results as posts, translate the $sortby variable
	if ($vbulletin->GPC['showposts'])
	{
		switch($vbulletin->GPC['sortby'])
		{
			case 'title':
				$vbulletin->GPC['sortby'] = 'thread.title';
				break;
			case 'lastpost':
				$vbulletin->GPC['sortby'] = 'post.dateline';
				break;
			case 'postusername':
				$vbulletin->GPC['sortby'] = 'username';
				break;
		}
	}

	// #############################################################################
	// get correct sortorder value
	$vbulletin->GPC['sortorder'] = strtolower($vbulletin->GPC['sortorder']);
	switch($vbulletin->GPC['sortorder'])
	{
		case 'ascending':
			$vbulletin->GPC['sortorder'] = 'ASC';
			break;

		default:
			$vbulletin->GPC['sortorder'] = 'DESC';
			break;
	}

	// #############################################################################
	// build search hash
	$searchhash = md5(strtolower($vbulletin->GPC['query']) . "||" . strtolower($vbulletin->GPC['searchuser']) . '||' . $vbulletin->GPC['exactname'] . '||' . $vbulletin->GPC['starteronly'] . "||$forumchoice||" . $vbulletin->GPC['childforums'] . '||' . $vbulletin->GPC['titleonly'] . '||' . $vbulletin->GPC['showposts'] . '||' . $vbulletin->GPC['searchdate'] . '||' . $vbulletin->GPC['beforeafter'] . '||' . $vbulletin->GPC['replyless'] . '||' . $vbulletin->GPC['replylimit'] . '||' . $vbulletin->GPC['searchthreadid'] . '||' . $vbulletin->GPC['exclude'] . iif($vbulletin->options['fulltextsearch'], '||' . $vbulletin->GPC['searchtype']));

	// #############################################################################
	// search for already existing searches...
	$getsearches = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "search AS search
		WHERE searchhash = '" . $db->escape_string($searchhash) . "'
		AND userid = " . $vbulletin->userinfo['userid']
	);
	if ($numsearches = $db->num_rows($getsearches))
	{
		$highScore = 0;
		while ($getsearch = $db->fetch_array($getsearches))
		{
			// is $sortby the same?
			if ($getsearch['sortby'] == $vbulletin->GPC['sortby'])
			{
				if ($getsearch['sortorder'] == $vbulletin->GPC['sortorder'])
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
		$db->free_result($getsearches);

		// check our results and decide what to do
		switch ($highScore)
		{
			// #############################################################################
			// found a saved search that matches perfectly
			case 3:

				$searchtime = fetch_microtime_difference($searchstart);

				// redirect to saved search
				$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . "searchid=$search[searchid]";
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
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "search (userid, titleonly, ipaddress, personal, query, searchuser, forumchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms, displayterms, searchhash)
					VALUES (" . $vbulletin->userinfo['userid'] . ", " . intval($vbulletin->GPC['titleonly']) . " ,'" . $db->escape_string(IPADDRESS) . "', " . ($vbulletin->options['searchsharing'] ? 0 : 1) . ", '" . $db->escape_string($search['query']) . "', '" . $db->escape_string($search['searchuser']) . "', '" . $db->escape_string($search['forumchoice']) . "', '" . $db->escape_string($search['sortby']) . "', '" . $db->escape_string($vbulletin->GPC['sortorder']) . "', $searchtime, " . intval($vbulletin->GPC['showposts']) . ", '" . implode(',', $search['orderedids']) . "', " . TIMENOW . ", '" . $db->escape_string($search['searchterms']) . "', '" . $db->escape_string($search['displayterms']) . "', '" . $db->escape_string($searchhash) . "')
					### SAVE ITEM IDS IN ORDER ###
				");
				// redirect to new search result
				$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . 'searchid=' . $db->insert_id();
				eval(print_standard_redirect('search'));
				break;

			// #############################################################################
			// Found a search with correct query conditions, but ORDER BY clause needs to be totally redone
			case 1:
				if ($vbulletin->GPC['sortby'] == 'rank' OR $search['sortby'] == 'rank')
				{
					// if we are changing to or from a relevancy search, we need to re-do the search
					break;
				}
				else
				{
					// re order search items
					$search['orderedids'] = iif($search['showposts'], 'postid', 'threadid') . " IN($search[orderedids])";
					$search['orderedids'] = sort_search_items($search['orderedids'], $search['showposts'], $vbulletin->GPC['sortby'], $vbulletin->GPC['sortorder']);
					// stop search timer
					$searchtime = fetch_microtime_difference($searchstart);

					// insert new search into database
					/*insert query*/
					$db->query_write("
						REPLACE INTO " . TABLE_PREFIX . "search (userid, titleonly, ipaddress, personal, query, searchuser, forumchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms, displayterms, searchhash)
						VALUES (
							" . $vbulletin->userinfo['userid'] . ",
							" . intval($vbulletin->GPC['titleonly']) . ",
							'" . $db->escape_string(IPADDRESS) . "',
							" . ($vbulletin->options['searchsharing'] ? 0 : 1) . ",
							'" . $db->escape_string($search['query']) . "',
							'" . $db->escape_string($search['searchuser']) . "',
							'" . $db->escape_string($search['forumchoice']) . "',
							'" . $db->escape_string($vbulletin->GPC['sortby']) . "',
							'" . $db->escape_string($vbulletin->GPC['sortorder']) .
							"', $searchtime,
							$search[showposts],
							'" . implode(',', $search['orderedids']) . "',
							" . TIMENOW . ",
							'" . $db->escape_string(serialize($searchterms)) . "',
							'" . $db->escape_string($search['displayterms']) . "',
							'" . $db->escape_string($searchhash) . "'
						)
						### SAVE ITEM IDS IN ORDER ###
					");
					// redirect to new search result
					$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . 'searchid=' . $db->insert_id();
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

	// $post_query_logic stores all the SQL conditions for our search in posts
	$post_query_logic = array();

	// $thread_query_logic stores all SQL conditions for the search in threads
	$thread_query_logic = array();

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
			'starteronly' => $vbulletin->GPC['starteronly'],
			'childforums' => $vbulletin->GPC['childforums'],
			'action' => $_REQUEST['do']
		)
	);

	$postscores = array();

	($hook = vBulletinHook::fetch_hook('search_process_fullsearch')) ? eval($hook) : false;

	// #############################################################################
	// ####################### START USER QUERY LOGIC ##############################
	// #############################################################################
	$postsum = 0;
	if ($vbulletin->GPC['searchuser'])
	{
		// username too short
		if (!$vbulletin->GPC['exactname'] AND strlen($vbulletin->GPC['searchuser']) < 3)
		{
			eval(standard_error(fetch_error('searchnametooshort')));
		}

		$username = htmlspecialchars_uni($vbulletin->GPC['searchuser']);
		$q = "
			SELECT posts, userid, username
			FROM " . TABLE_PREFIX . "user AS user
			WHERE username " .
				($vbulletin->GPC['exactname'] ?
					"= '" . $db->escape_string($username) . "'" :
					"LIKE('%" . sanitize_word_for_sql($username) . "%')"
				)
		;

		require_once(DIR . '/includes/functions_bigthree.php');
		$coventry = fetch_coventry();

		$users = $db->query_read($q);
		if ($db->num_rows($users))
		{
			$userids = array();
			while ($user = $db->fetch_array($users))
			{
				$postsum += $user['posts'];
				$display['users']["$user[userid]"] = $user['username'];
				$userids[] = in_array($user['userid'], $coventry) ? -1 : $user['userid'];
			}

			$userids = implode(', ', $userids);

			if ($vbulletin->GPC['starteronly'])
			{
				if ($vbulletin->GPC['showposts'])
				{
					$post_query_logic[] = "post.userid IN($userids)";
				}
				$thread_query_logic[] = "thread.postuserid IN($userids)";
			}
			// add the userids to the $post_query_logic search conditions
			else
			{
				if ($vbulletin->GPC['showposts'])
				{
					$post_query_logic[] = "post.userid IN($userids)";
				}
				else
				{	// use the (threadid, userid) index of post to limit the join
					$post_join_query_logic = " AND post.userid IN($userids)";
				}
			}
		}
		else
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['user'], $vbulletin->options['contactuslink'])));
		}
	}

	// #############################################################################
	// ########################## START WORD QUERY LOGIC ###########################
	// #############################################################################
	if ($vbulletin->GPC['query'] AND (!$vbulletin->options['fulltextsearch'] OR ($vbulletin->options['fulltextsearch'] AND $vbulletin->GPC['searchtype'])))
	{

		$querysplit = $vbulletin->GPC['query'];

		// #############################################################################
		// if we are doing a relevancy sort, use all AND and OR words as OR
		if ($vbulletin->GPC['sortby'] == 'rank')
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
					$queryWords["$word"] =& $words['NOT']["$word"];
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
						eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
						break;

					case 1:
						// just one word is okay - use it as an AND word instead of an OR
						$word = implode('', $checkwords);
						$words['AND']["$word"] = 'AND';
						$queryWords["$word"] =& $words['AND']["$word"];
						break;

					default:
						// two or more words were okay - use them as an OR clause
						foreach ($checkwords AS $checkword)
						{
							$words['OR']["$word"]["$checkword"] = 'OR';
							$queryWords["$checkword"] =& $words['OR']["$word"]["$checkword"];
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
				$queryWords["$word"] =& $words['AND']["$word"];
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
			eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
		}

		if (!$vbulletin->options['fulltextsearch'])
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

				if ($vbulletin->options['allowwildcards'])
				{
					$queryword = str_replace('*', '%', $queryword);
				}
				$getwords = $db->query_read("
					SELECT wordid, title FROM " . TABLE_PREFIX . "word
					WHERE title LIKE('$queryword')
				");
				if ($db->num_rows($getwords))
				{
					// found some results for current word
					$wordids = array();
					while ($getword = $db->fetch_array($getwords))
					{
						$wordids[] = $getword['wordid'];
					}
					// query post ids for current word...
					// if $titleonly is specified, also get the value of postindex.intitle
					$postmatches = $db->query_read("
						SELECT postid" . iif($vbulletin->GPC['titleonly'], ', intitle') . iif($vbulletin->GPC['sortby'] == 'rank', ", score AS origscore,
							CASE intitle
								WHEN 1 THEN score + " . $vbulletin->options['posttitlescore'] . "
								WHEN 2 THEN score + " . ($vbulletin->options['posttitlescore'] + $vbulletin->options['threadtitlescore']) . "
								ELSE score
							END AS score") . "
						FROM " . TABLE_PREFIX . "postindex
						WHERE wordid IN(" . implode(',', $wordids) . ")
					");
					if ($db->num_rows($postmatches) == 0)
					{
						if ($wordtype == 'AND')
						{
							// could not find any posts containing required word
							eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
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
						if ($vbulletin->GPC['titleonly'])
						{
							while ($postmatch = $db->fetch_array($postmatches))
							{
								if ($postmatch['intitle'])
								{
									$bonus = iif(isset($postscores["$postmatch[postid]"]), $vbulletin->options['multimatchscore'], 0);
									$postscores["$postmatch[postid]"] += $postmatch['score'] + $bonus;
									$queryWords["$word"][] = $postmatch['postid'];
								}
							}
						}
						// don't bother checking that word exists in the title
						else
						{
							while ($postmatch = $db->fetch_array($postmatches))
							{
								$bonus = iif(isset($postscores["$postmatch[postid]"]), $vbulletin->options['multimatchscore'], 0);
								$postscores["$postmatch[postid]"] += $postmatch['score'] + $bonus;
								$queryWords["$word"][] = $postmatch['postid'];
							}
						}
					}
					// free SQL memory for postids query
					unset($postmatch);
					$db->free_result($postmatches);
				}
				else
				{
					if ($wordtype == 'AND')
					{
						// could not find required word in the database
						eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
					}
					else
					{
						// Could not find word in the database
						// remove this word from the $queryWords array so we don't use it in the posts query
						unset($queryWords["$word"]);
					}
				}
				unset($getword);
				$db->free_result($getwords);
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
			if ($vbulletin->GPC['searchthreadid'])
			{
				$q = "
					SELECT postid FROM " . TABLE_PREFIX . "post
					WHERE threadid = " . $vbulletin->GPC['searchthreadid'] . "
				";
				$posts = $db->query_read($q);
				if ($db->num_rows($posts) == 0)
				{
					eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
				}
				while ($post = $db->fetch_array($posts))
				{
					$requiredposts[0][] = $post['postid'];
				}
				unset($post);
				$db->free_result($posts);
			}

			// #############################################################################
			// get AND clauses
			if (!empty($words['AND']))
			{
				// intersect the post ids for all AND words - Note: array_intersect() IS BROKEN IN PHP 4.0.4
				foreach (array_keys($words['AND']) AS $word)
				{
					$requiredposts[] =& $queryWords["$word"];
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
							eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
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
					$post_query_logic = array_merge($post_query_logic, $or);
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
					eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
				}
				else
				{
					$post_query_logic[] = 'post.postid IN(' . implode(',', $ANDs) . ')';
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
					$post_query_logic[] =  'post.postid NOT IN(' . implode(',', $postids) . ')';
				}

				// clean up variables
				unset($postids);
			}

			// check that we don't have only NOT words
			if (empty($words['AND']) AND empty($words['OR']) AND !empty($words['NOT']))
			{
				// user has ONLY specified a 'NOT' word... this would be bad
				eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
			}

			($hook = vBulletinHook::fetch_hook('search_process_postindex')) ? eval($hook) : false;
		}
		else
		{
			// Fulltext ...
			foreach ($queryWords AS $word => $wordtype)
			{
				// Need something here to strip odd characters out of words that fulltext is probably not indexing

				$queryword = preg_replace('#"([^"]+)"#sie', "stripslashes(str_replace('*', ' ', '\\0'))", $word);

				if ($wordtype != 'NOT')
				{
					$display['highlight'][] = htmlspecialchars_uni(preg_replace('#"(.+)"#si', '\\1', $queryword));
				}

				// make sure word is safe to insert into the query
				$unsafeword = $queryword;
				$queryword = sanitize_word_for_sql($queryword);

				if (!$vbulletin->options['allowwildcards'])
				{
					# Don't allow wildcard searches so remove any *
					$queryword = str_replace('*', '', $queryword);
				}

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

			// if we are searching in a thread, the required posts MUST come from the thread we are searching!
			if ($vbulletin->GPC['searchthreadid'])
			{
				$thread_query_logic[] = "thread.threadid = " . $vbulletin->GPC['searchthreadid'];
				$userid_index = " USE INDEX (threadid)";
			}

			if ($vbulletin->GPC['titleonly'])
			{
				$thread_query_logic[] = "MATCH(thread.title) AGAINST ('$wordlist' IN BOOLEAN MODE)";
			}
			else
			{
				$post_query_logic[] = "MATCH(post.title, post.pagetext) AGAINST ('$wordlist' IN BOOLEAN MODE)";
			}

			($hook = vBulletinHook::fetch_hook('search_process_fulltext')) ? eval($hook) : false;
		}
	}
	else if ($vbulletin->options['fulltextsearch'] AND !$vbulletin->GPC['searchtype'])
	{
		// if we are searching in a thread, the required posts MUST come from the thread we are searching!
		if ($vbulletin->GPC['searchthreadid'])
		{
			$thread_query_logic[] = "thread.threadid = " . $vbulletin->GPC['searchthreadid'];
		}

		if ($vbulletin->GPC['query'])
		{
			if ($vbulletin->GPC['titleonly'])
			{
				if ($vbulletin->GPC['sortby'] == 'rank')
				{
					$rank_select_logic = "MATCH(thread.title) AGAINST ('" . $db->escape_string($vbulletin->GPC['query']) . "') AS score";
				}
				$thread_query_logic[] = "MATCH(thread.title) AGAINST ('" . $db->escape_string($vbulletin->GPC['query']) . "')";
			}
			else
			{
				if ($vbulletin->GPC['sortby'] == 'rank')
				{
					$rank_select_logic = "MATCH(post.title, post.pagetext) AGAINST ('" . $db->escape_string($vbulletin->GPC['query']) . "') AS score";
				}
				$post_query_logic[] = "MATCH(post.title, post.pagetext) AGAINST ('" . $db->escape_string($vbulletin->GPC['query']) . "')";
			}

			$nl_query_limit = 'LIMIT ' . $vbulletin->options['maxresults'];

			// Limit forums that are searched since we are going to return a very small result set in most cases.
			foreach ($vbulletin->userinfo['forumpermissions'] AS $forumid => $fperms)
			{
				if (!($fperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($fperms & $vbulletin->bf_ugp_forumpermissions['cansearch']) OR !verify_forum_password($forumid, $forum['password'], false) OR !($vbulletin->forumcache[$forumid]['options'] & $vbulletin->bf_misc_forumoptions['indexposts']))
				{
					$excludelist .= ",$forumid";
				}
				else if ((!$vbulletin->GPC['titleonly'] OR $vbulletin->GPC['showposts']) AND !($fperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
				{	// exclude forums that have canview but no canviewthreads if this is a post search
					$excludelist .= ",$forumid";
				}
			}

			if ($excludelist != '')
			{
				$thread_query_logic[] = "thread.forumid NOT IN (0$excludelist)";
			}

			$words = array();
			$display['words'] = array($vbulletin->GPC['query']);
			$display['common'] = array();
			$display['highlight'][] = htmlspecialchars_uni(preg_replace('#"(.+)"#si', '\\1', $vbulletin->GPC['query']));

		}
		else
		{
			// this means we are searching just on username...
		}
	}

	// #############################################################################
	// ######################### END WORD QUERY LOGIC ##############################
	// #############################################################################

	// #############################################################################
	// check if we are searching for posts from a specific time period
	if ($vbulletin->GPC['searchdate'] != 'lastvisit')
	{
		$vbulletin->GPC['searchdate'] = intval($vbulletin->GPC['searchdate']);
	}
	if ($vbulletin->GPC['searchdate'])
	{
		switch($vbulletin->GPC['searchdate'])
		{
			case 'lastvisit':
				// get posts from before/after last visit
				$datecut = $vbulletin->userinfo['lastvisit'];
				break;

			case 0:
				// do not specify a time period
				$datecut = 0;
				break;

			default:
				// get posts from before/after specified time period
				$datecut = TIMENOW - $vbulletin->GPC['searchdate'] * 86400;
		}
		if ($datecut)
		{
			switch($vbulletin->GPC['beforeafter'])
			{
				// get posts from before $datecut
				case 'before':
					$post_query_logic[] = "post.dateline < $datecut";
					break;

				// get posts from after $datecut
				default:
					$post_query_logic[] = "post.dateline > $datecut";
			}
		}
		unset($datecut);
	}

	// #############################################################################
	// check to see if there are conditions attached to number of thread replies
	if ($vbulletin->GPC['replyless'] OR $vbulletin->GPC['replylimit'] > 0)
	{
		if ($vbulletin->GPC['replyless'] == 1)
		{
			// get threads with at *most* $replylimit replies
			$thread_query_logic[] = "thread.replycount <= " . $vbulletin->GPC['replylimit'];
		}
		else
		{
			// get threads with at *least* $replylimit replies
			$thread_query_logic[] = "thread.replycount >= " . $vbulletin->GPC['replylimit'];
		}
	}

	// #############################################################################
	// check to see if we should be searching in a particular forum or forums
	if ($forumchoice)
	{
		$thread_query_logic[] = "thread.forumid IN($forumchoice)";
	}

	if ($vbulletin->GPC['exclude'])
	{
		$excludelist = explode(',', $vbulletin->GPC['exclude']);
		$excludearray = array();
		foreach ($excludelist AS $key => $excludeid)
		{
			if ($excludeforum = intval($excludeid))
			{
				$excludearray[] = $excludeforum;
			}
		}
		if (!empty($excludearray))
		{
			$thread_query_logic[] = "thread.forumid NOT IN (" . implode(',', $excludearray) . ")";
		}
	}

	($hook = vBulletinHook::fetch_hook('search_process_fetch')) ? eval($hook) : false;

	// #############################################################################
	// show results as threads
	// #############################################################################
	$querylogic = array_merge($post_query_logic, $thread_query_logic);
	if (!$vbulletin->GPC['showposts'])
	{
		// create new threadscores array to store scores for threads
		$threadscores = array();
		$thread_select_logic = array();

		// #############################################################################
		$threadids = array();

		// Natural Language
		if ($vbulletin->options['fulltextsearch'] AND !$vbulletin->GPC['searchtype'])
		{
			$thread_select_logic[] = "DISTINCT thread.threadid";
			if ($rank_select_logic)
			{
				$thread_select_logic[] = $rank_select_logic;
			}
		}
		else
		{
			$thread_select_logic[] = "thread.threadid";
			if ($vbulletin->GPC['sortby'] == 'rank')
			{
				if (!empty($post_query_logic))
				{
					$thread_select_logic[] = "post.postid";
				}
				$thread_select_logic[] = "IF(views <= replycount, replycount + 1, views) as views, replycount, votenum, votetotal, thread.lastpost";
			}
		}

		$threads = $db->query_read("
			SELECT
			" . implode(', ', $thread_select_logic) . "
			FROM " . TABLE_PREFIX . "thread AS thread $userid_index
			" . ((!empty($post_query_logic) OR !empty($post_join_query_logic)) ? "INNER JOIN " . TABLE_PREFIX . "post AS post ON(thread.threadid = post.threadid $post_join_query_logic)" : "") . "
			" . (!empty($querylogic) ? "WHERE " . implode(" AND ", $querylogic) : "") . "
			$nl_query_limit
		");

		$itemscores = array();
		$datescores = array();
		$mindate = TIMENOW;
		$maxdate = 0;
		while ($thread = $db->fetch_array($threads))
		{
			if ($vbulletin->GPC['sortby'] == 'rank')
			{
				$threadscores["$thread[threadid]"] += ($rank_select_logic) ? $thread['score'] : $postscores["$thread[postid]"];
				if ($mindate > $thread['lastpost'])
				{
					$mindate = $thread['lastpost'];
				}
				if ($maxdate < $thread['lastpost'])
				{
					$maxdate = $thread['lastpost'];
				}
				$datescores["$thread[threadid]"] = $thread['lastpost'];
				$itemids["$thread[threadid]"] = $thread;
			}
			else
			{
				$itemids["$thread[threadid]"] = true;
			}
		}
		unset($threadscores);
		unset($thread);
		$db->free_result($threads);

		if (empty($itemids))
		{
			eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
		}

		if ($vbulletin->GPC['sortby'] == 'rank')
		{
			foreach ($itemids AS $threadid => $thread)
			{
				$itemscores["$threadid"] = fetch_search_item_score($thread, $threadscores["$thread[threadid]"]);
			}
		}

		unset($postscores);

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
		$post_select_logic = array();

		if ($vbulletin->options['fulltextsearch'] AND $vbulletin->GPC['titleonly'])
		{
			$querylogic[] = $thread_query_logic[] = "post.postid = thread.firstpostid";
		}

		$posts = $db->query_read("
			SELECT postid, post.dateline
			" . iif($vbulletin->GPC['sortby'] == 'rank' AND !$rank_select_logic, ', IF(thread.views=0, thread.replycount+1, thread.views) as views, thread.replycount, thread.votenum, thread.votetotal') . "
			" . (!empty($rank_select_logic) ? ", $rank_select_logic" : "") . "
			FROM " . TABLE_PREFIX . "post AS post $userid_index
			" . ((!empty($thread_query_logic) OR ($vbulletin->GPC['sortby'] == 'rank' AND !$rank_select_logic)) ? "INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)" : "") . "
			" . (!empty($querylogic) ? "WHERE " . implode(" AND ", $querylogic) : "") . "
			$nl_query_limit
		");

		if ($vbulletin->GPC['sortby'] == 'rank')
		{
			$itemscores = array();
			$datescores = array();
			$mindate = TIMENOW;
			$maxdate = 0;

			while ($post = $db->fetch_array($posts))
			{
				if ($rank_select_logic)
				{
					$postscores["$post[postid]"] = $post['score'];
				}
				else
				{
					if ($mindate > $post['dateline'])
					{
						$mindate = $post['dateline'];
					}
					if ($maxdate < $post['dateline'])
					{
						$maxdate = $post['dateline'];
					}
					$datescores["{$post['postid']}"] = $post['dateline'];
				}

				$itemscores["{$post['postid']}"] = fetch_search_item_score($post, $postscores["{$post['postid']}"]);
			}
			unset($postscores);
		}
		else
		{
			$itemids = array();
			while ($post = $db->fetch_array($posts))
			{
				$itemids["{$post['postid']}"] = true;
			}
		}
		unset($post);
		$db->free_result($post);

	}
	// #############################################################################
	// end show results as posts
	// #############################################################################


	// #############################################################################
	// now sort the results into order
	// #############################################################################

	// sort by relevance
	if ($vbulletin->GPC['sortby'] == 'rank')
	{
		if (empty($itemscores))
		{
			eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
		}

		// add in date scores
		fetch_search_date_scores($datescores, $itemscores, $mindate, $maxdate);

		// sort the score results
		$sortfunc = iif($vbulletin->GPC['sortorder'] == 'asc', 'asort', 'arsort');
		$sortfunc($itemscores);

		// create the final result set
		$orderedids = array_keys($itemscores);
	}
	// sort by database field
	else
	{
		if (empty($itemids))
		{
			eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
		}

		// remove dupes and make query condition
		$itemids = iif($vbulletin->GPC['showposts'], 'postid', 'threadid') . ' IN(' . implode(',', array_keys($itemids)) . ')';

		// sort the results and create the final result set
		$orderedids = sort_search_items($itemids, $vbulletin->GPC['showposts'], $vbulletin->GPC['sortby'], $vbulletin->GPC['sortorder']);
	}

	// #############################################################################
	// end sort the results into order
	// #############################################################################


	// get rid of unwanted gubbins
	unset($itemids, $threadids, $postids, $postscores, $threadscores, $itemscores, $datescores);

	// final check to see if we've actually got some results
	if (empty($orderedids))
	{
		eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
	}

	// #############################################################################
	// finish search timer
	$searchtime = fetch_microtime_difference($searchstart);

	// #############################################################################
	// go through search words to build the display words for the results page summary bar

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

	if ($vbulletin->options['fulltextsearch'])
	{
		$display['words'] = preg_replace('#"([^"]+)"#sie', "stripslashes(str_replace('*', ' ', '\\0'))", $display['words']);
	}

	// make sure we have no duplicate entries in our $display array
	foreach (array_keys($display) AS $displaykey)
	{
		if ($displaykey != 'options' AND is_array($display["$displaykey"]))
		{
			$display["$displaykey"] = array_unique($display["$displaykey"]);
		}
	}

	($hook = vBulletinHook::fetch_hook('search_process_complete')) ? eval($hook) : false;

	// insert search results into search cache
	/*insert query*/
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "search (userid, titleonly, ipaddress, personal, query, searchuser, forumchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms, displayterms, searchhash)
		VALUES (" . $vbulletin->userinfo['userid'] . ", " . intval($vbulletin->GPC['titleonly']) . " ,'" . $db->escape_string(IPADDRESS) . "', " . ($vbulletin->options['searchsharing'] ? 0 : 1) . ", '" . $db->escape_string($vbulletin->GPC['query']) . "', '" . $db->escape_string($vbulletin->GPC['searchuser']) . "', '" . $db->escape_string($forumchoice) . "', '" . $db->escape_string($vbulletin->GPC['sortby']) . "', '" . $db->escape_string($vbulletin->GPC['sortorder']) . "', $searchtime, " . intval($vbulletin->GPC['showposts']) . ", '" . implode(',', $orderedids) . "', " . TIMENOW . ", '" . $db->escape_string(serialize($searchterms)) . "', '" . $db->escape_string(serialize($display)) . "', '" . $db->escape_string($searchhash) . "')
		### SAVE ORDERED IDS TO SEARCH CACHE ###
	");
	$searchid = $db->insert_id();

	$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . "searchid=$searchid";
	eval(print_standard_redirect('search'));

}

// #############################################################################
if ($_REQUEST['do'] == 'showresults')
{
	require_once(DIR . '/includes/functions_forumdisplay.php');

	$vbulletin->input->clean_array_gpc('r',  array(
		'pagenumber' => TYPE_INT,
		'perpage' => TYPE_INT
	));

	// check for valid search result
	$gotsearch = false;
	if ($search =  $db->query_first("SELECT * FROM " . TABLE_PREFIX . "search AS search WHERE searchid = " . $vbulletin->GPC['searchid']))
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
			else if ($search['userid'] == $vbulletin->userinfo['userid'])
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
		eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
	}

	($hook = vBulletinHook::fetch_hook('search_results_start')) ? eval($hook) : false;

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
	$orderedids = explode(',', $search['orderedids']);
	unset($search['orderedids']);
	$numitems = sizeof($orderedids);

	// #############################################################################
	// #############################################################################

	// start the timer for the permissions check
	$go = microtime();

	// #############################################################################
	// don't retrieve tachy'd posts/threads
	require_once(DIR . '/includes/functions_bigthree.php');
	if ($coventry = fetch_coventry('string'))
	{
		$coventry_post = "AND post.userid NOT IN ($coventry)";
		$coventry_thread = "AND thread.postuserid NOT IN ($coventry)";
	}

	// now check to see if the results can be viewed / searched etc.
	if ($search['showposts'])
	{
		// query posts
		// for now - don't query hidden posts as they need special templates to handle the results
		$permQuery = "
			SELECT postid AS itemid, post.visible AS post_visible, thread.visible AS thread_visible, thread.forumid, thread.threadid,
			IF(postuserid = " . $vbulletin->userinfo['userid'] . ", 'self', 'other') AS starter
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE postid IN(" . implode(', ', $orderedids) . ")
			AND thread.open <> 10
			###AND thread.visible = 1###
			###AND post.visible = 1###
			$coventry_post
			$coventry_thread
		";

		$hook_query_fields = $hook_query_joins = '';
		($hook = vBulletinHook::fetch_hook('search_results_query_posts')) ? eval($hook) : false;

		// query post data
		$dataQuery = "
			SELECT post.postid, post.title AS posttitle, post.dateline AS postdateline,
				post.iconid AS posticonid, post.pagetext, post.visible,
				IF(post.userid = 0, post.username, user.username) AS username,
				thread.threadid, thread.title AS threadtitle, thread.iconid AS threadiconid, thread.replycount,
				IF(thread.views=0, thread.replycount+1, thread.views) as views,
				thread.pollid, thread.sticky, thread.open, thread.lastpost, thread.forumid, thread.visible AS thread_visible,
				user.userid

				" . (can_moderate() ? ",pdeletionlog.userid AS pdel_userid, pdeletionlog.username AS pdel_username, pdeletionlog.reason AS pdel_reason" : "") . "
				" . (can_moderate() ? ",tdeletionlog.userid AS tdel_userid, tdeletionlog.username AS tdel_username, tdeletionlog.reason AS tdel_reason" : "") . "

				" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], ', threadread.readtime AS threadread') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)

			" . (can_moderate() ?
			"LEFT JOIN " . TABLE_PREFIX . "deletionlog AS tdeletionlog ON(thread.threadid = tdeletionlog.primaryid AND tdeletionlog.type = 'thread')
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS pdeletionlog ON(post.postid = pdeletionlog.primaryid AND pdeletionlog.type = 'post')"
				: "") . "

			" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")") . "

			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
			$hook_query_joins
			WHERE post.postid IN";
	}
	else
	{
		// query threads
		$permQuery = "
			SELECT threadid AS itemid, forumid, visible AS thread_visible,
			IF(postuserid = " . $vbulletin->userinfo['userid'] . ", 'self', 'other') AS starter
			FROM " . TABLE_PREFIX . "thread AS thread
			WHERE threadid IN(" . implode(', ', $orderedids) . ")
			AND thread.open <> 10
			##AND thread.visible = 1###
			$coventry_thread
		";

		if ($vbulletin->options['threadpreview'] > 0)
		{
			$previewfield = 'post.pagetext AS preview,';
			$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
		}
		else
		{
			$previewfield = '';
			$previewjoin = '';
		}

		$hook_query_fields = $hook_query_joins = '';
		($hook = vBulletinHook::fetch_hook('search_results_query_threads')) ? eval($hook) : false;

		// query thread data
		$dataQuery = "
			SELECT $previewfield
				thread.threadid, thread.threadid AS postid, thread.title AS threadtitle, thread.iconid AS threadiconid, thread.dateline, thread.forumid,
				thread.replycount, IF(thread.views=0, thread.replycount+1, thread.views) as views, thread.sticky,
				thread.pollid, thread.open, thread.lastpost AS postdateline, thread.visible, thread.hiddencount,
				thread.lastpost, thread.lastposter, thread.attach, thread.postusername, thread.forumid,

				" . (can_moderate() ? "deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason," : "") . "

				user.userid AS postuserid
				" . iif($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid'], ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed") . "
				" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], ', threadread.readtime AS threadread') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = thread.postuserid)

			" . (can_moderate() ? "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND type = 'thread')" : "") . "
			" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")") . "

			" . iif($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread
				ON(subscribethread.threadid = thread.threadid AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . " AND canview = 1)") . "
			$previewjoin
			$hook_query_joins
			WHERE thread.threadid IN
		";
	}

	// query moderators for forum password purposes (and inline moderation)
	cache_moderators();

	$tmp = array();
	$items = $db->query_read($permQuery);
	unset($permQuery);
	while ($item = $db->fetch_array($items))
	{
		if (!$search['showposts'])
		{
			// fake post_visible since we aren't looking for it in thread results
			$item['post_visible'] = 1;
		}

		if ((!$item['post_visible'] OR !$item['thread_visible']) AND !can_moderate($item['forumid'], 'canmoderateposts'))
		{	// post/thread is moderated and we don't have permission to see it
			continue;
		}
		else if (($item['post_visible'] == 2 OR $item['thread_visible'] == 2) AND !can_moderate($item['forumid']))
		{	// post/thread is deleted and we don't have permission to
			continue;
		}

		$tmp["$item[forumid]"]["$item[starter]"][] = $item['itemid'];
	}
	unset($item);
	$db->free_result($items);

	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
	{
		// we need this for forum read times
		require_once(DIR . '/includes/functions_forumlist.php');
		cache_ordered_forums(1);
	}

	foreach (array_keys($tmp) AS $forumid)
	{
		$forum =& $vbulletin->forumcache["$forumid"];
		$fperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];

		$items = vb_number_format(sizeof($tmp["$forumid"]['self']) + sizeof($tmp["$forumid"]['other']));

		// check CANVIEW / CANSEARCH permission and forum password for current forum
		if (
			!($fperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR !($fperms & $vbulletin->bf_ugp_forumpermissions['cansearch'])
			OR !verify_forum_password($forumid, $forum['password'], false)
			OR (
			(
				$vbulletin->options['fulltextsearch']
				AND !($vbulletin->bf_misc_forumoptions['indexposts'] & $vbulletin->forumcache["$forumid"]['options']))
				AND !$display['getnew']
			)
		)
		{
			// cannot view / search this forum, or does not have forum password
			unset($tmp["$forumid"]);
		}
		else if (!($fperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) AND ($search['showposts'] OR (!$display['getnew'] AND !$search['titleonly'])))
		{	// kill post searches in forums that we can't view threads in
			unset($tmp["$forumid"]);
		}
		else
		{
			if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
			{
				$lastread["$forumid"] = max($forum['forumread'], (TIMENOW - ($vbulletin->options['markinglimit'] * 86400)));
			}
			else
			{
				$forumview = intval(fetch_bbarray_cookie('forum_view', $forumid));

				//use which one produces the highest value, most likely cookie
				$lastread["$forumid"] = ($forumview > $vbulletin->userinfo['lastvisit'] ? $forumview : $vbulletin->userinfo['lastvisit']);
			}

			// check CANVIEWOTHERS permission
			if (!($fperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
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
		// show the getnew message if there are no results, this might be due to permissions
		if ($display['getnew'])
		{
			eval(standard_error(fetch_error('searchnoresults_getnew', $vbulletin->session->vars['sessionurl']), '', false));
		}
		else
		{
			eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
		}
	}

	DEVDEBUG('time to check permissions: ' . vb_number_format(fetch_microtime_difference($go), 4));

	// extra check to prevent DB error if someone sets it at 0
	if ($vbulletin->options['searchperpage'] < 1)
	{
		$vbulletin->options['searchperpage'] = 20;
	}

	// trim results down to maximum $vbulletin->options[maxresults]
	if ($vbulletin->options['maxresults'] > 0 AND $numitems > $vbulletin->options['maxresults'])
	{
		$clippedids = array();
		for ($i = 0; $i < $vbulletin->options['maxresults']; $i++)
		{
			$clippedids[] = $orderedids["$i"];
		}
		$orderedids =& $clippedids;
		$numitems = $vbulletin->options['maxresults'];
	}

	// #############################################################################
	// #############################################################################

	// get page split...
	sanitize_pageresults($numitems, $vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage'], 200, $vbulletin->options['searchperpage']);

	// get list of thread to display on this page
	$startat = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage'];
	$endat = $startat + $vbulletin->GPC['perpage'];
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
	$items = $db->query_read($dataQuery);
	$itemidname = iif($search['showposts'], 'postid', 'threadid');

	$dotthreads = fetch_dot_threads_array($ids);

	// end search timer
	$searchtime = vb_number_format(fetch_microtime_difference($searchstart, $search['searchtime']), 2);

	while ($item = $db->fetch_array($items))
	{
		// unset the thread preview if it can't be seen
		$forumperms = fetch_permissions($item['forumid']);
		if ($vbulletin->options['threadpreview'] > 0 AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		{
			$item['preview'] = '';
		}
		$item['forumtitle'] = $vbulletin->forumcache["$item[forumid]"]['title'];
		$itemids["$item[$itemidname]"] = $item;
	}
	unset($item, $dataQuery);
	$db->free_result($items);
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

	// initialize counters and template bits
	$searchbits = '';
	$itemcount = $startat;
	$first = $itemcount + 1;

	if ($vbulletin->options['threadpreview'] AND $vbulletin->userinfo['ignorelist'])
	{
		// Get Buddy List
		$buddy = array();
		if (trim($vbulletin->userinfo['buddylist']))
		{
			$buddylist = preg_split('/( )+/', trim($vbulletin->userinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
				foreach ($buddylist AS $buddyuserid)
			{
				$buddy["$buddyuserid"] = 1;
			}
		}
		DEVDEBUG('buddies: ' . implode(', ', array_keys($buddy)));
		// Get Ignore Users
		$ignore = array();
		if (trim($vbulletin->userinfo['ignorelist']))
		{
			$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
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

	if (can_moderate())
	{
		$threadcolspan = 8;
		$show['inlinemod'] = true;
		$url = SCRIPTPATH;
	}
	else
	{
		$show['inlinemod'] = false;
		$threadcolspan = 7;
		$url = '';
	}

	// initialize variable for inlinemod popup
	$threadadmin_imod_menu = '';

	($hook = vBulletinHook::fetch_hook('search_results_prebits')) ? eval($hook) : false;

	$oldposts = false;
	// #############################################################################
	// show results as posts
	if ($search['showposts'])
	{
		foreach ($itemids AS $post)
		{
			// do post folder icon
			if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
			{
				// new if post hasn't been read or made since forum was last read
				$isnew = ($post['postdateline'] > $post['threadread'] AND $post['postdateline'] > $vbulletin->forumcache["$post[forumid]"]['forumread']);
			}
			else
			{
				$isnew = ($post['postdateline'] > $vbulletin->userinfo['lastvisit']);
			}

			if ($isnew)
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
			$post['allowicons'] = $vbulletin->forumcache["$post[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'];

			// get POST icon from icon cache
			$post['posticonpath'] =& $vbulletin->iconcache["$post[posticonid]"]['iconpath'];
			$post['posticontitle'] =& $vbulletin->iconcache["$post[posticonid]"]['title'];

			// show post icon?
			if ($post['allowicons'])
			{
				// show specified icon
				if ($post['posticonpath'])
				{
					$post['posticon'] = true;
				}
				// show default icon
				else if (!empty($vbulletin->options['showdeficon']))
				{
					$post['posticon'] = true;
					$post['posticonpath'] = $vbulletin->options['showdeficon'];
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
			else
			{
				$post['posttitle'] = fetch_censored_text($post['posttitle']);
			}

			// format post text
			$post['pagetext'] = nl2br($post['pagetext']);

			// get highlight words
			$post['highlight'] =& $highlightwords;

			// get info from post
			$post = process_thread_array($post, $lastread["$post[forumid]"], $post['allowicons']);

			$show['managepost'] = iif(can_moderate($post['forumid'], 'candeleteposts') OR can_moderate($post['forumid'], 'canremoveposts'), true, false);
			$show['approvepost'] = (can_moderate($post['forumid'], 'canmoderateposts')) ? true : false;
			$show['managethread'] = (can_moderate($post['forumid'], 'canmanagethreads')) ? true : false;
			$show['disabled'] = ($show['managethread'] OR $show['managepost'] OR $show['approvepost']) ? false : true;

			$show['moderated'] = (!$post['visible'] OR !$post['thread_visible']) ? true : false;

			if ($post['pdel_userid'])
			{
				$post['del_username'] =& $post['pdel_username'];
				$post['del_userid'] =& $post['pdel_userid'];
				$post['del_reason'] =& $post['pdel_reason'];
				$show['deleted'] = true;
			}
			else if ($post['tdel_userid'])
			{
				$post['del_username'] =& $post['tdel_username'];
				$post['del_userid'] =& $post['tdel_userid'];
				$post['del_reason'] =& $post['tdel_reason'];
				$show['deleted'] = true;
			}
			else
			{
				$show['deleted'] = false;
			}

			$itemcount ++;
			exec_switch_bg();

			($hook = vBulletinHook::fetch_hook('search_results_postbit')) ? eval($hook) : false;

			if ($display['getnew'] AND $search['sortby'] == 'lastpost' AND !$oldposts AND $post['postdateline'] <= $vbulletin->userinfo['lastvisit'] AND $vbulletin->userinfo['lastvisit'] != 0)
			{
				$oldposts = true;
				eval('$searchbits .= "' . fetch_template('search_results_postbit_lastvisit') . '";');
			}

			eval('$searchbits .= "' . fetch_template('search_results_postbit') . '";');
		}

		if ($show['popups'] AND $show['inlinemod'])
		{
			eval('$threadadmin_imod_menu = "' . fetch_template('threadadmin_imod_menu_post') . '";');
		}
	}
	// #############################################################################
	// show results as threads
	else
	{
		$show['threadicons'] = true;
		$show['forumlink'] = true;

		// threadbit_deleted conditionals
		$show['threadtitle'] = true;
		$show['viewthread'] = true;
		$show['managethread'] = true;

		foreach ($itemids AS $thread)
		{
			// add highlight words
			$thread['highlight'] =& $highlightwords;

			// get info from thread
			$thread = process_thread_array($thread, $lastread["$thread[forumid]"]);

			// Inline Moderation
			$show['movethread'] = (can_moderate($thread['forumid'], 'canmanagethreads')) ? true : false;
			$show['deletethread'] = (can_moderate($thread['forumid'], 'candeleteposts') OR can_moderate($thread['forumid'], 'canremoveposts')) ? true : false;
			$show['approvethread'] = (can_moderate($thread['forumid'], 'canmoderateposts')) ? true : false;
			$show['openthread'] = (can_moderate($thread['forumid'], 'canopenclose')) ? true : false;
			$show['disabled'] = ($show['movethread'] OR $show['deletethread'] OR $show['approvethread'] OR $show['openthread']) ? false : true;

			$itemcount++;
			exec_switch_bg();

			($hook = vBulletinHook::fetch_hook('search_results_threadbit')) ? eval($hook) : false;

			if ($display['getnew'] AND $search['sortby'] == 'lastpost' AND !$oldposts AND $thread['lastpost'] <= $vbulletin->userinfo['lastvisit'] AND $vbulletin->userinfo['lastvisit'] != 0)
			{
				$oldposts = true;
				eval('$searchbits .= "' . fetch_template('threadbit_lastvisit') . '";');
			}

			if ($thread['visible'] == 2)
			{
				$show['deletereason'] = (!empty($thread['del_reason'])) ?  true : false;
				$show['moderated'] = ($thread['hiddencount'] > 0 AND can_moderate($thread['forumid'], 'canmoderateposts')) ? true : false;
				eval('$searchbits .= "' . fetch_template('threadbit_deleted') . '";');
			}
			else
			{
				$show['moderated'] = ((!$thread['visible'] OR $thread['hiddencount'] > 0) AND can_moderate($thread['forumid'], 'canmoderateposts')) ? true : false;
				eval('$searchbits .= "' . fetch_template('threadbit') . '";');
			}
		}

		if ($show['popups'] AND $show['inlinemod'])
		{
			eval('$threadadmin_imod_menu = "' . fetch_template('threadadmin_imod_menu_thread') . '";');
		}
	}
	// #############################################################################

	$last = $itemcount;

	$pagenav = construct_page_nav($vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage'], $numitems, 'search.php?' . $vbulletin->session->vars['sessionurl'] . 'searchid=' . $vbulletin->GPC['searchid'] . '&amp;pp=' . $vbulletin->GPC['perpage']);

	// #############################################################################
	// get the bits for the summary bar
	if (!empty($display['words']))
	{
		foreach ($display['words'] AS $key => $val)
		{
			$display['words']["$key"] = htmlspecialchars_uni($val);
		}
		$display['words'] = str_replace(
			array(
				'&lt;/u&gt;&lt;/b&gt;-&lt;b&gt;&lt;u&gt;',
				'&lt;/u&gt; OR &lt;u&gt;'),
			array(
				'</u></b>-<b><u>',
				'</u> OR <u>'),
			$display['words']
		);
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
			$display['users']["$userid"] = '<a href="member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userid\"><b><u>$username</u></b></a>";
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
			$display['forums']["$key"] = '<a href="forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumid\"><b><u>" . $vbulletin->forumcache["$forumid"]['title'] . '</u></b></a>';
		}
		$displayForums = implode(" $vbphrase[or] ", $display['forums']);
	}
	else
	{
		$displayForums = '';
	}
	$starteronly =& $display['options']['starteronly'];
	$childforums =& $display['options']['childforums'];
	$action =& $display['options']['action'];

	if ($vbulletin->options['fulltextsearch'])
	{
		DEVDEBUG('FULLTEXT Search');
	}
	else
	{
		DEVDEBUG('Default Search');
	}

	$searchminutes = floor((TIMENOW - $search['dateline']) / 60);
	if ($searchminutes >= 1)
	{
		$show['generated'] = true;
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
	$vbulletin->input->clean_array_gpc('r', array(
		'days'		=> TYPE_UINT,
		'exclude'	=> TYPE_NOHTML,
		'include'	=> TYPE_NOHTML,
		'showposts' => TYPE_BOOL,
		'oldmethod' => TYPE_BOOL,
		'sortby'    => TYPE_NOHTML,
	));

	switch($vbulletin->GPC['sortby'])
	{
		// sort variables that don't need changing
		case 'title':
			$sortby = 'thread.title ASC, thread.lastpost DESC';
			break;

		case 'views':
			$sortby = 'thread.views ASC, thread.lastpost DESC';
			break;

		case 'replycount':
			$sortby = 'thread.replycount ASC, thread.lastpost DESC';
			break;

		case 'postusername':
			$sortby = 'thread.postusername ASC, thread.lastpost DESC';
			break;

		// sort variables that need changing
		case 'forum':
			$sortby = 'thread.forumid ASC, thread.lastpost DESC';
			break;

		case 'threadstart':
			$sortby = 'thread.dateline DESC';
			break;

		// set default sortby if not specified or unrecognized
		default:
			$vbulletin->GPC['sortby'] = 'lastpost';
			$sortby = 'thread.lastpost DESC';
	}

	// #############################################################################
	// if showing results as posts, translate the $sortby variable
	if ($vbulletin->GPC['showposts'])
	{
		switch($vbulletin->GPC['sortby'])
		{
			case 'title':
				$sortby = 'thread.title ASC, post.dateline DESC';
				break;
			case 'lastpost':
				$sortby = 'post.dateline DESC';
				break;
			case 'postusername':
				$sortby = 'post.username ASC, post.dateline DESC';
				break;
		}
	}

	// get date:
	if ($_REQUEST['do'] == 'getnew' AND $vbulletin->userinfo['lastvisit'] != 0)
	{
		// if action = getnew and last visit date is set
		$datecut = $vbulletin->userinfo['lastvisit'];
	}
	else
	{
		$_REQUEST['do'] = 'getdaily';
		if ($vbulletin->GPC['days'] < 1)
		{
			$vbulletin->GPC['days'] = 1;
		}
		$datecut = TIMENOW - (24 * 60 * 60 * $vbulletin->GPC['days']);
	}

	($hook = vBulletinHook::fetch_hook('search_getnew_start')) ? eval($hook) : false;

	// build search hash
	$searchhash = md5($vbulletin->userinfo['userid'] . IPADDRESS . $forumid . $vbulletin->GPC['days'] . $vbulletin->userinfo['lastvisit'] . $vbulletin->GPC['include'] . '|' . $vbulletin->GPC['exclude']);

	// start search timer
	$searchtime = microtime();

	// if forumid is specified, get list of ids
	if ($foruminfo['forumid'])
	{
		// check forum exists
		if (isset($vbulletin->forumcache["{$foruminfo['forumid']}"]))
		{
			$display['forums'][] = $foruminfo['forumid'];
			// check forum permissions
			if (($vbulletin->userinfo['forumpermissions']["{$foruminfo['forumid']}"] & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($vbulletin->userinfo['forumpermissions']["{$foruminfo['forumid']}"] & $vbulletin->bf_ugp_forumpermissions['cansearch']))
			{
				$forumids = fetch_search_forumids($foruminfo['forumid'], 1);
			}
			else
			{
				// can not view specified forum
				eval(standard_error(fetch_error('invalidid', $vbphrase['forum'], $vbulletin->options['contactuslink'])));
			}
		}
		else
		{
			// specified forum does not exist
			eval(standard_error(fetch_error('invalidid', $vbphrase['forum'], $vbulletin->options['contactuslink'])));
		}
	}
	// forumid is not specified, get list of all forums user can view
	else
	{
		if ($vbulletin->GPC['exclude'])
		{
			$excludelist = explode(',', $vbulletin->GPC['exclude']);
			foreach ($excludelist AS $key => $excludeid)
			{
				$excludeid = intval($excludeid);
				unset($vbulletin->forumcache["$excludeid"]);
			}
		}
		if ($vbulletin->GPC['include'])
		{
			$includearray = array();
			$includelist = explode(',', $vbulletin->GPC['include']);
			foreach ($includelist AS $key => $includeid)
			{
				$includeid = intval($includeid);
				$includearray["$includeid"] = true;
			}
		}

		$forumids = array_keys($vbulletin->forumcache);
	}

	// set display terms
	$display = array(
		'words' => array(),
		'highlight' => array(),
		'common' => array(),
		'users' => array(),
		'forums' => $display['forums'],
		'options' => array(
			'starteronly' => false,
			'childforums' => true,
			'action' => $_REQUEST['do']
		),
		'getnew' => ($_REQUEST['do'] == 'getnew') ? 1 : 0,
	);

	($hook = vBulletinHook::fetch_hook('search_getnew_display')) ? eval($hook) : false;

	// get moderator cache for forum password purposes
	cache_moderators();

	// get forum ids for all forums user is allowed to view
	foreach ($forumids AS $key => $forumid)
	{
		if (is_array($includearray) AND empty($includearray["$forumid"]))
		{
			unset($forumids["$key"]);
			continue;
		}

		$fperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
		$forum =& $vbulletin->forumcache["$forumid"];

		if (!($fperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($fperms & $vbulletin->bf_ugp_forumpermissions['cansearch']) OR !verify_forum_password($forumid, $forum['password'], false))
		{
			unset($forumids["$key"]);
		}
	}

	if (empty($forumids))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['forum'], $vbulletin->options['contactuslink'])));
	}

	if ($_REQUEST['do'] == 'getnew' AND $vbulletin->userinfo['userid'] AND $vbulletin->options['threadmarking'] AND !$vbulletin->GPC['oldmethod'])
	{
		$marking_join = "
			LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")
			INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = thread.forumid)
			LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = forum.forumid AND forumread.userid = " . $vbulletin->userinfo['userid'] . ")
		";

		$cutoff = TIMENOW - ($vbulletin->options['markinglimit'] * 86400);

		$lastpost_where = "
			AND thread.lastpost > IF(threadread.readtime IS NULL, $cutoff, threadread.readtime)
			AND thread.lastpost > IF(forumread.readtime IS NULL, $cutoff, forumread.readtime)
			AND thread.lastpost > $cutoff
		";

		$post_lastpost_where = "
			AND post.dateline > IF(threadread.readtime IS NULL, $cutoff, threadread.readtime)
			AND post.dateline > IF(forumread.readtime IS NULL, $cutoff, forumread.readtime)
			AND post.dateline > $cutoff
		";
	}
	else
	{
		$marking_join = '';
		$lastpost_where = "AND thread.lastpost >= $datecut";
		$post_lastpost_where = "AND post.dateline >= $datecut";
	}

	($hook = vBulletinHook::fetch_hook('search_getnew_process')) ? eval($hook) : false;

	#even though showresults would filter thread.visible=0, thread.visible remains in these 2 queries so that the 4 part index on thread can be used.
	$orderedids = array();
	if ($vbulletin->GPC['showposts'])
	{
		$posts = $db->query_read("
			SELECT post.postid
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
			$marking_join
			WHERE thread.forumid IN(" . implode(', ', $forumids) . ")
				$lastpost_where
				AND thread.visible IN (0,1,2)
				AND thread.sticky IN (0,1)
				$post_lastpost_where
			ORDER BY $sortby
			LIMIT " . intval($vbulletin->options['maxresults'])
		);

		while ($post = $db->fetch_array($posts))
		{
			$orderedids[] = $post['postid'];
		}
	}
	else
	{
		$threads = $db->query_read("
			SELECT thread.threadid
			FROM " . TABLE_PREFIX . "thread AS thread
			$marking_join
			WHERE thread.forumid IN(" . implode(', ', $forumids) . ")
				$lastpost_where
				AND thread.visible IN (0,1,2)
				AND thread.sticky IN (0,1)
				AND thread.open <> 10
			ORDER BY $sortby
			LIMIT " . intval($vbulletin->options['maxresults'])
		);

		while ($thread = $db->fetch_array($threads))
		{
			$orderedids[] = $thread['threadid'];
		}
	}

	if (empty($orderedids))
	{
		if ($_REQUEST['do'] == 'getnew')
		{
			eval(standard_error(fetch_error('searchnoresults_getnew', $vbulletin->session->vars['sessionurl']), '', false));
		}
		else
		{
			eval(standard_error(fetch_error('searchnoresults', ''), '', false));
		}
	}

	$sql_ids = $db->escape_string(implode(',', $orderedids));
	unset($orderedids);

	// check for previous searches
	if ($search = $db->query_first("SELECT searchid FROM " . TABLE_PREFIX . "search AS search WHERE userid = " . $vbulletin->userinfo['userid'] . " AND searchhash = '" . $db->escape_string($searchhash) . "' AND orderedids = '$sql_ids'"))
	{
		// search has been done previously
		$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . "searchid=$search[searchid]";
		eval(print_standard_redirect('redirect_search'));
	}

	// end search timer
	$searchtime = fetch_microtime_difference($searchtime);

	($hook = vBulletinHook::fetch_hook('search_getnew_complete')) ? eval($hook) : false;

	/*insert query*/
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "search (userid, showposts, ipaddress, personal, forumchoice, sortby, sortorder, searchtime, orderedids, dateline, displayterms, searchhash)
		VALUES (" . $vbulletin->userinfo['userid'] . ", " . intval($vbulletin->GPC['showposts']) . ", '" . $db->escape_string(IPADDRESS) . "', 1, '" . $db->escape_string($foruminfo['forumid']) . "', '" . $db->escape_string($vbulletin->GPC['sortby']) . "', 'DESC', $searchtime, '$sql_ids', " . TIMENOW . ", '" . $db->escape_string(serialize($display)) . "', '" . $db->escape_string($searchhash) . "')
	");
	$searchid = $db->insert_id();

	$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . "searchid=$searchid";
	eval(print_standard_redirect('search'));
}

// #############################################################################
if ($_REQUEST['do'] == 'finduser')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid'	     => TYPE_UINT,
		'forumchoice' => TYPE_ARRAY,
		'childforums' => TYPE_BOOL,
	));

	// valid user id?
	if (!$vbulletin->GPC['userid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['user'], $vbulletin->options['contactuslink'])));
	}

	// get user info
	if ($user = $db->query_first("SELECT userid, username, posts FROM " . TABLE_PREFIX . "user WHERE userid = " . $vbulletin->GPC['userid']))
	{
		$searchuser =& $user['username'];
	}
	// could not find specified user
	else
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['user'], $vbulletin->options['contactuslink'])));
	}

	// #############################################################################
	// build search hash
	$query = '';
	$searchuser = $user['username'];
	$exactname = 1;
	$starteronly = 0;
	$forumchoice = $foruminfo['forumid'];
	$childforums = 1;
	$titleonly = 0;
	$showposts = 1;
	$searchdate = 0;
	$beforeafter = 'after';
	$replyless = 0;
	$replylimit = 0;
	$searchthreadid = 0;

	($hook = vBulletinHook::fetch_hook('search_finduser_start')) ? eval($hook) : false;

	$searchhash = md5(TIMENOW . "||" . $vbulletin->userinfo['userid'] . "||" . strtolower($searchuser) . "||$exactname||$starteronly||$forumchoice||$childforums||$titleonly||$showposts||$searchdate||$beforeafter||$replyless||$replylimit||$searchthreadid");

	// check if search already done
	//if ($search = $db->query_first("SELECT searchid FROM " . TABLE_PREFIX . "search AS search WHERE searchhash = '" . $db->escape_string($searchhash) . "'"))
	//{
	//	$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . "searchid=$search[searchid]";
	//	eval(print_standard_redirect('search'));
	//}

	// start search timer
	$searchtime = microtime();

	// #############################################################################
	// check to see if we should be searching in a particular forum or forums
	if ($forumids = fetch_search_forumids($vbulletin->GPC['forumchoice'], $vbulletin->GPC['childforums']))
	{
		$showforums = true;
	}
	else
	{
		foreach ($vbulletin->forumcache AS $forumid => $forum)
		{
			$fperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
			if (($fperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				$forumids[] = $forumid;
			}
		}
		$showforums = false;
	}

	if (empty($forumids))
	{
		eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
	}
	else
	{
		// query post ids in dateline DESC order...
		$orderedids = array();
		$posts = $db->query_read("
			SELECT postid
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE post.userid = $user[userid]
				AND thread.forumid IN(" . implode(',', $forumids) . ")
			ORDER BY post.dateline DESC
			LIMIT " . ($vbulletin->options['maxresults'] * 2) . "
		");
		while ($post = $db->fetch_array($posts))
		{
			$orderedids[] = $post['postid'];
		}
		unset($post);
		$db->free_result($posts);

		// did we get some results?
		if (empty($orderedids))
		{
			eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
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

		($hook = vBulletinHook::fetch_hook('search_finduser_complete')) ? eval($hook) : false;

		/*insert query*/
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "search (userid, ipaddress, personal, searchuser, forumchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, displayterms, searchhash)
			VALUES (" . $vbulletin->userinfo['userid'] . ", '" . $db->escape_string(IPADDRESS) . "', 1, '" . $db->escape_string($user['username']) . "', '" . $db->escape_string($forumchoice) . "', 'post.dateline', 'DESC', $searchtime, 1, '" . $db->escape_string(implode(',', $orderedids)) . "', " . TIMENOW . ", '" . $db->escape_string(serialize($display)) . "', '" . $db->escape_string($searchhash) . "')
		");
		$searchid = $db->insert_id();

		$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . "searchid=$searchid";
		eval(print_standard_redirect('search'));
	}
}

// #############################################################################
if ($_POST['do'] == 'doprefs')
{
	$vbulletin->input->clean_array_gpc('p', $globals);

	if ($vbulletin->userinfo['userid'])
	{
		// save preferences
		if ($vbulletin->GPC['saveprefs'])
		{
			$prefs = array(
				'exactname' 	=> $vbulletin->GPC['exactname'],
				'starteronly' 	=> $vbulletin->GPC['starteronly'],
				'childforums' 	=> $vbulletin->GPC['childforums'],
				'showposts' 	=> $vbulletin->GPC['showposts'],
				'titleonly' 	=> $vbulletin->GPC['titleonly'],
				'searchdate' 	=> $vbulletin->GPC['searchdate'],
				'beforeafter' 	=> $vbulletin->GPC['beforeafter'],
				'sortby' 		=> $vbulletin->GPC['sortby'],
				'sortorder' 	=> $vbulletin->GPC['sortorder'],
				'replyless' 	=> $vbulletin->GPC['replyless'],
				'replylimit' 	=> $vbulletin->GPC['replylimit'],
				'searchtype' 	=> $vbulletin->GPC['searchtype'],
			);

			// init user data manager
			$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
			$userdata->set_existing($vbulletin->userinfo);
			$userdata->set('searchprefs', serialize($prefs));

			($hook = vBulletinHook::fetch_hook('search_doprefs_process')) ? eval($hook) : false;

			$userdata->save();
			unset($prefs);
		}
		// clear preferences (only if prefs are set)
		else if ($vbulletin->userinfo['searchprefs'] != '')
		{
			unset($globals);

			// init user data manager
			$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
			$userdata->set_existing($vbulletin->userinfo);
			$userdata->set('searchprefs', '');

			($hook = vBulletinHook::fetch_hook('search_doprefs_process')) ? eval($hook) : false;

			$userdata->save();
		}
	}

	$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'];
	if (!empty($globals))
	{
		foreach (array_keys($globals) AS $varname)
		{
			if ($varname == 'forumchoice' AND is_array($vbulletin->GPC['forumchoice']))
			{
				foreach ($vbulletin->GPC['forumchoice'] AS $_forumid)
				{
					$vbulletin->url .= "forumchoice[]=" . urlencode($_forumid) . "&amp;";
				}
			}
			else
			{
				$vbulletin->url .= "$varname=" . urlencode($vbulletin->GPC["$varname"]) . '&amp;';
			}
		}
		$vbulletin->url = substr($vbulletin->url, 0, -5);
	}

	($hook = vBulletinHook::fetch_hook('search_doprefs_complete')) ? eval($hook) : false;

	eval(print_standard_redirect('search_preferencessaved', true, true));
}

// #############################################################################
// finish off the page

if ($templatename != '')
{
	($hook = vBulletinHook::fetch_hook('search_complete')) ? eval($hook) : false;

	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('print_output("' . fetch_template($templatename) . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14698 $
|| ####################################################################
\*======================================================================*/
?>