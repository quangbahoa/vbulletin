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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'search');
define('CSRF_PROTECTION', true);
define('ALTSEARCH', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('search', 'inlinemod', 'prefix');

// get special data templates from the datastore
$specialtemplates = array(
	'iconcache',
	'searchcloud'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'humanverify',
	'optgroup',
	'search_forums',
	'search_results',
	'search_results_postbit', // result from search posts
	'search_results_postbit_lastvisit',
	'threadbit', // result from search threads
	'threadbit_deleted', // result from deleted search threads
	'threadbit_lastvisit',
	'threadbit_announcement',
	'newreply_reviewbit_ignore',
	'threadadmin_imod_menu_thread',
	'threadadmin_imod_menu_post',
	'tag_cloud_link',
	'tag_cloud_box_search',
	'tag_cloud_headinclude'
);

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
	'tag'            => TYPE_STR, // TYPE_STR, because that's what the error cond for intro expects
	'forumchoice'    => TYPE_ARRAY,
	'prefixchoice'   => TYPE_ARRAY_NOHTML,
	'childforums'    => TYPE_BOOL,
	'titleonly'      => TYPE_BOOL,
	'showposts'      => TYPE_BOOL,
	'searchdate'     => TYPE_NOHTML,
	'beforeafter'    => TYPE_NOHTML,
	'sortby'         => TYPE_NOHTML,
	'sortorder'      => TYPE_NOHTML,
	'replyless'      => TYPE_UINT,
	'replylimit'     => TYPE_UINT,
	'searchthreadid' => TYPE_UINT,
	'saveprefs'      => TYPE_BOOL,
	'quicksearch'    => TYPE_BOOL,
	'searchtype'     => TYPE_BOOL,
	'exclude'        => TYPE_NOHTML,
	'nocache'        => TYPE_BOOL,
	'ajax'           => TYPE_BOOL,
	'humanverify'    => TYPE_ARRAY,
	'userid'         => TYPE_UINT,
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

if (empty($_POST['do']))
{
	$_POST['do'] = '';
}

if ($vbulletin->options['fulltextsearch'])
{
	if ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cansearchft_bool'])
	{
		// use boolean when user has boolean, ignore NL
		$vbulletin->GPC['searchtype'] = 1;
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

// workaround for 3.6 bug 1229 - 'find all threads started by x' + captcha
if ($_REQUEST['do'] == 'process' AND fetch_require_hvcheck('search') AND !isset($_POST['humanverify']))
{
	// guest user has come from a do=process link that does not include human verification
	$_REQUEST['do'] = 'intro';
}

// make first part of navbar
$navbits = array('search.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['search_forums']);

$errors = array();

// #############################################################################
if ((in_array($_REQUEST['do'], array('intro', 'showresults', 'doprefs')) == false) AND $vbulletin->options['searchfloodtime'] AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND !can_moderate())
{
	// get last search for this user and check floodcheck
	if ($prevsearch = $db->query_first("
		SELECT searchid, dateline
		FROM " . TABLE_PREFIX . "search AS search
		WHERE " . iif(!$vbulletin->userinfo['userid'], "ipaddress ='" . $db->escape_string(IPADDRESS) . "'", "userid = " . $vbulletin->userinfo['userid']) . "
		ORDER BY dateline DESC LIMIT 1
	"))
	{
		if (($timepassed = TIMENOW - $prevsearch['dateline']) < $vbulletin->options['searchfloodtime'])
		{
			if ($_REQUEST['do'] == 'process')
			{
				$errors[] = array('searchfloodcheck', $vbulletin->options['searchfloodtime'], ($vbulletin->options['searchfloodtime'] - $timepassed));
			}
			else
			{
				eval(standard_error(fetch_error('searchfloodcheck', $vbulletin->options['searchfloodtime'], ($vbulletin->options['searchfloodtime'] - $timepassed))));
			}
		}
	}
}

// #############################################################################
// allows an alternative processing branch to be executed
($hook = vBulletinHook::fetch_hook('search_before_process')) ? eval($hook) : false;

// #############################################################################
if ($_REQUEST['do'] == 'process')
{
	$vbulletin->input->clean_array_gpc('r', $globals);

	($hook = vBulletinHook::fetch_hook('search_process_start')) ? eval($hook) : false;

	if (!$vbulletin->options['threadtagging'])
	{
		//  tagging disabled, don't let them search on it
		$vbulletin->GPC['tag'] = '';
	}

	// #############################################################################
	// start search timer
	$searchstart = microtime();

	if ($vbulletin->GPC['userid'] AND $userinfo = fetch_userinfo($vbulletin->GPC['userid']))
	{
		$vbulletin->GPC_exists['searchuser'] = true;
		$vbulletin->GPC['searchuser'] = unhtmlspecialchars($userinfo['username']);
	}

	// #############################################################################
	// error if no search terms
	$vbulletin->GPC['prefixchoice'] = array_unique($vbulletin->GPC['prefixchoice']);
	$have_prefix_limit = false;

	foreach ($vbulletin->GPC['prefixchoice'] AS $prefixid)
	{
		if (!$prefixid OR $prefixid == '-1')
		{
			// searching on any or no prefix - this is not restrictive enough
			// so this overrides any other setting
			$have_prefix_limit = false;
			break;
		}
		else
		{
			// matched a prefix - we have a limit, but we might still have
			// a non-restrictive value so continue looping
			$have_prefix_limit = true;
		}
	}

	$have_search_limit = (
		$vbulletin->GPC['query']
		OR $vbulletin->GPC['searchuser']
		OR $vbulletin->GPC['replyless']
		OR $vbulletin->GPC['tag']
		OR $have_prefix_limit
	);

	if (!$have_search_limit)
	{
		$errors[] = 'searchspecifyterms';
	}

	if (fetch_require_hvcheck('search'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify = vB_HumanVerify::fetch_library($vbulletin);
		if (!$verify->verify_token($vbulletin->GPC['humanverify']))
		{
			$errors[] = $verify->fetch_error();
		}
	}

	if (empty($errors))
	{
		// #############################################################################
		// if searching within a thread, $showposts must be true and sorting should be "dateline ASC"
		if ($vbulletin->GPC['searchthreadid'])
		{
			$vbulletin->GPC['sortby'] = 'dateline';
			$vbulletin->GPC['sortorder'] = 'ASC';

			$vbulletin->GPC['showposts'] = true;
			$vbulletin->GPC['forumchoice'] = array();
			$vbulletin->GPC['starteronly'] = false;
			$vbulletin->GPC['titleonly'] = false;
			$vbulletin->GPC['replyless'] = false;
			$vbulletin->GPC['replylimit'] = false;
		}

		// if searching for only a tag, we must show results as threads
		if ($vbulletin->GPC['tag'] AND empty($vbulletin->GPC['query']) AND empty($vbulletin->GPC['searchuser']))
		{
			$vbulletin->GPC['showposts'] = false;
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
						// look for words that are contain &#1234;, ., or - and quote them (more logical behavior, 24676)
						$query_parts = '';
						$space_skipped = false;

						foreach (preg_split('#[ \r\n\t]#s', $query["$i"]) AS $query_part)
						{
							if ($space_skipped)
							{
								$query_parts .= ' ';
							}
							$space_skipped = true;

							if (preg_match('/(&#[0-9]+;|\.|-)/s', $query_part))
							{
								$query_parts .= '"' . $query_part . '"';
							}
							else
							{
								$query_parts .= $query_part;
							}
						}

						$vbulletin->GPC['query'] .= $query_parts;
					}
				}

				$vbulletin->GPC['query'] = preg_replace_callback(
					'#"([^"]+)"#si',
					function($matches)
					{
						return stripslashes(str_replace(' ', '*', $matches[0]));
					},
					$vbulletin->GPC['query']
				);
			}
			$vbulletin->GPC['query'] = sanitize_search_query($vbulletin->GPC['query'], $errors);
		}

		if (empty($errors))
		{

			// #############################################################################
			// get forums in which to search
			$forumchoice = implode(',', fetch_search_forumids($vbulletin->GPC['forumchoice'], $vbulletin->GPC['childforums']));

			// get prefixes
			if (in_array('', $vbulletin->GPC['prefixchoice']) OR empty($vbulletin->GPC['prefixchoice']))
			{
				// any prefix
				$vbulletin->GPC['prefixchoice'] = array();
				$prefixchoice = '';
				$display_prefixes = array();
			}
			else
			{
				$vbulletin->GPC['prefixchoice'] = array_unique($vbulletin->GPC['prefixchoice']);
				$prefixchoice = implode(',', $vbulletin->GPC['prefixchoice']);
				$display_prefixes = $vbulletin->GPC['prefixchoice'];
			}

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
			$searchhash = md5(strtolower($vbulletin->GPC['query']) . "||" . strtolower($vbulletin->GPC['searchuser']) . '||' . strtolower($vbulletin->GPC['tag']) . '||' . $vbulletin->GPC['exactname'] . '||' . $vbulletin->GPC['starteronly'] . "||$forumchoice||$prefixchoice||" . $vbulletin->GPC['childforums'] . '||' . $vbulletin->GPC['titleonly'] . '||' . $vbulletin->GPC['showposts'] . '||' . $vbulletin->GPC['searchdate'] . '||' . $vbulletin->GPC['beforeafter'] . '||' . $vbulletin->GPC['replyless'] . '||' . $vbulletin->GPC['replylimit'] . '||' . $vbulletin->GPC['searchthreadid'] . '||' . $vbulletin->GPC['exclude'] . iif($vbulletin->options['fulltextsearch'], '||' . $vbulletin->GPC['searchtype']));

			// #############################################################################
			// search for already existing searches...
			if (!$vbulletin->GPC['nocache'])
			{
				$getsearches = $db->query_read("
					SELECT * FROM " . TABLE_PREFIX . "search AS search
					WHERE searchhash = '" . $db->escape_string($searchhash) . "'
						AND userid = " . $vbulletin->userinfo['userid'] . "
						AND completed = 1
				");
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
							$searchtime = number_format(fetch_microtime_difference($searchstart), 5, '.', '');

							// insert new search into database
							/*insert query*/
							$db->query_write("
								REPLACE INTO " . TABLE_PREFIX . "search
									(userid, titleonly, ipaddress, personal, query, searchuser, forumchoice, prefixchoice,
									sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms,
									displayterms, searchhash, completed)
								VALUES
									(" . $vbulletin->userinfo['userid'] . ",
									" . intval($vbulletin->GPC['titleonly']) . ",
									'" . $db->escape_string(IPADDRESS) . "',
									" . ($vbulletin->options['searchsharing'] ? 0 : 1) . ",
									'" . $db->escape_string($search['query']) . "',
									'" . $db->escape_string($search['searchuser']) . "',
									'" . $db->escape_string($search['forumchoice']) . "',
									'" . $db->escape_string($search['prefixchoice']) . "',
									'" . $db->escape_string($search['sortby']) . "',
									'" . $db->escape_string($vbulletin->GPC['sortorder']) . "',
									$searchtime,
									" . intval($vbulletin->GPC['showposts']) . ",
									'" . implode(',', $search['orderedids']) . "',
									" . TIMENOW . ",
									'" . $db->escape_string($search['searchterms']) . "',
									'" . $db->escape_string($search['displayterms']) . "',
									'" . $db->escape_string($searchhash) . "',
									1)
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
								$searchtime = number_format(fetch_microtime_difference($searchstart), 5, '.', '');

								// insert new search into database
								/*insert query*/
								$db->query_write("
									REPLACE INTO " . TABLE_PREFIX . "search (userid, titleonly, ipaddress, personal, query, searchuser, forumchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms, displayterms, searchhash, completed)
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
										'" . $db->escape_string($searchhash) . "',
										1
									)
								");
								// redirect to new search result
								$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . 'searchid=' . $db->insert_id();
								eval(print_standard_redirect('search'));
								break;
							}
					}
				}
			}

			// now we know this will be a unique search, put a placeholder in
			// for the floodcheck
			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "search
					(userid, titleonly, ipaddress, personal, query, searchuser, forumchoice, prefixchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms, displayterms, searchhash, completed)
				VALUES (
					" . $vbulletin->userinfo['userid'] . ",
					" . intval($vbulletin->GPC['titleonly']) . " ,
					'" . $db->escape_string(IPADDRESS) . "',
					" . ($vbulletin->options['searchsharing'] ? 0 : 1) . ",
					'" . $db->escape_string($vbulletin->GPC['query']) . "',
					'" . $db->escape_string($vbulletin->GPC['searchuser']) . "',
					'" . $db->escape_string($forumchoice) . "',
					'" . $db->escape_string($prefixchoice) . "',
					'" . $db->escape_string($vbulletin->GPC['sortby']) . "',
					'" . $db->escape_string($vbulletin->GPC['sortorder']) . "',
					0,
					" . intval($vbulletin->GPC['showposts']) . ",
					'',
					" . TIMENOW . ",
					'" . $db->escape_string(serialize($searchterms)) . "',
					'',
					'" . $db->escape_string($searchhash) . "',
					0
				)
			");

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
				'prefixes' => $display_prefixes,
				'tag' => htmlspecialchars_uni($vbulletin->GPC['tag']),
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
					$errors[] = 'searchnametooshort';
				}
				else
				{
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

					$users = $db->query_read_slave($q);
					if ($db->num_rows($users))
					{
						$userids = array();
						while ($user = $db->fetch_array($users))
						{
							$postsum += $user['posts'];
							$display['users']["$user[userid]"] = $user['username'];
							$userids[] = (in_array($user['userid'], $coventry) AND !can_moderate()) ? -1 : $user['userid'];
						}

						$userids = implode(', ', $userids);

						if ($vbulletin->GPC['starteronly'])
						{
							if ($vbulletin->GPC['showposts'])
							{
								$post_query_logic[50] = "post.userid IN($userids)";
							}
							$thread_query_logic[] = "thread.postuserid IN($userids)";
						}
						// add the userids to the $post_query_logic search conditions
						else
						{
							if ($vbulletin->GPC['showposts'])
							{
								$post_query_logic[50] = "post.userid IN($userids)";
							}
							else
							{	// use the (threadid, userid) index of post to limit the join
								$post_join_query_logic = " AND post.userid IN($userids)";
							}
						}
					}
					else
					{
						$errors[] = array('invalidid', $vbphrase['user'], $vbulletin->options['contactuslink']);
					}
				}
			}
		}

		$tag_join = '';
		if ($vbulletin->GPC['tag'])
		{
			$verified_tag = $db->query_first_slave("
				SELECT tagid, tagtext
				FROM " . TABLE_PREFIX . "tag
				WHERE tagtext = '" . $db->escape_string(htmlspecialchars_uni($vbulletin->GPC['tag'])) . "'
			");
			if (!$verified_tag)
			{
				$errors[] = 'invalid_tag_specified';
			}
			else
			{
				$db->query_write("INSERT INTO " . TABLE_PREFIX . "tagsearch (tagid, dateline) VALUES (" . $verified_tag['tagid'] . ", " . TIMENOW . ")");

				$tag_join = "INNER JOIN " . TABLE_PREFIX . "tagthread AS tagthread ON (tagthread.tagid = $verified_tag[tagid] AND tagthread.threadid = thread.threadid)";
			}
		}

		if (empty($errors))
		{
			// #############################################################################
			// ########################## START WORD QUERY LOGIC ###########################
			// #############################################################################
			if ($vbulletin->GPC['query'] AND (!$vbulletin->options['fulltextsearch'] OR ($vbulletin->options['fulltextsearch'] AND $vbulletin->GPC['searchtype'])))
			{
				$querysplit = $vbulletin->GPC['query'];
				// split string into seperate words and back again, this will deal with MB languages without space delimiters
				$querysplit = implode(' ', split_string($querysplit));

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
								if (sizeof($display['common']) > 0)
								{
									$displayCommon = "<p>$vbphrase[words_very_common] : <b>" . implode('</b>, <b>', htmlspecialchars_uni($display['common'])) . '</b></p>';
								}
								else
								{
									$displayCommon = '';
								}
								$errors[] = array('searchnoresults', $displayCommon);
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
					$errors[] = array('searchnoresults', $displayCommon);
				}

				if (empty($errors))
				{
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
							$getwords = $db->query_read_slave("
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
								$postmatches = $db->query_read_slave("
									SELECT postid" . iif($vbulletin->GPC['titleonly'], ', intitle') . iif($vbulletin->GPC['sortby'] == 'rank', ", score AS origscore,
										CASE intitle
											WHEN 1 THEN score + " . $vbulletin->options['posttitlescore'] . "
											WHEN 2 THEN score + " . ($vbulletin->options['posttitlescore'] + $vbulletin->options['threadtitlescore']) . "
											ELSE score
										END AS score") . "
									FROM " . TABLE_PREFIX . "postindex
									WHERE wordid IN(" . implode(',', $wordids) . ") " . ($vbulletin->GPC['titleonly'] ? " AND intitle = 2" : "") . "
								");
								if ($db->num_rows($postmatches) == 0)
								{
									if ($wordtype == 'AND')
									{
										// could not find any posts containing required word
										$errors[] = array('searchnoresults', $displayCommon);
										break;
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
									$errors[] = array('searchnoresults', $displayCommon);
									break;
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

						if (empty($errors))
						{
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
								$posts = $db->query_read_slave($q);
								if ($db->num_rows($posts) == 0)
								{
									$errors[] = array('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink']);
								}
								else
								{
									while ($post = $db->fetch_array($posts))
									{
										$requiredposts[0][] = $post['postid'];
									}
									unset($post);
								}
								$db->free_result($posts);
							}

							// #############################################################################
							// get AND clauses
							if (!empty($words['AND']) AND empty($errors))
							{
								// intersect the post ids for all AND words - Note: array_intersect() IS BROKEN IN PHP 4.0.4
								foreach (array_keys($words['AND']) AS $word)
								{
									$requiredposts[] =& $queryWords["$word"];
								}
							}

							// #############################################################################
							// get OR clauses
							if (!empty($words['OR']) AND empty($errors))
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
											$errors[] = array('searchnoresults', $displayCommon);
											break 2;

										case 1:
											// found only one matching word from the current OR clause - translate this OR into an AND#
											$requiredposts[] = $checkwords[0];
											break;

										default:
											// found matches for two or more terms in the OR clause - process it as an OR
											foreach ($checkwords AS $checkword)
											{
												if (!empty($checkword))
												{
													$postids[] = implode(', ', $checkword);
												}
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
							if (!empty($requiredposts) AND empty($errors))
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
									$errors[] = array('searchnoresults', $displayCommon);
								}
								else
								{
									$post_query_logic[100] = 'post.postid IN(' . implode(',', $ANDs) . ')';
								}

								// clean up variables
								unset($requiredposts, $postids, $ANDs);
							}

							// #############################################################################
							// get NOT clauses
							if (!empty($words['NOT']) AND empty($errors))
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
									$post_query_logic[200] =  'post.postid NOT IN(' . implode(',', $postids) . ')';
								}

								// clean up variables
								unset($postids);
							}

							if ($vbulletin->GPC['titleonly'] AND !$vbulletin->GPC['starteronly'])
							{
								$fetchusers = '';
								if ($post_query_logic[50])
								{
									$fetchusers = $post_query_logic[50];
									unset($post_query_logic[50]);
								}

								if (!empty($post_query_logic))
								{
									$threadids = array();
									$threads = $db->query_read_slave("
										SELECT threadid
										FROM " . TABLE_PREFIX . "post AS post
										WHERE " . implode(" AND ", $post_query_logic) . "
									");
									while ($thread = $db->fetch_array($threads))
									{
										$threadids[] = $thread['threadid'];
									}

									if (!empty($threadids))
									{
										$postids = array();
										$posts = $db->query_read_slave("
											SELECT postid
											FROM " . TABLE_PREFIX . "post AS post
											WHERE threadid IN (" . implode(',', $threadids) . ")
										");
										while ($post = $db->fetch_array($posts))
										{
											$postids[] = $post['postid'];
										}
										unset($post_query_logic[100]);
										unset($post_query_logic[200]);

										if (!empty($postids))
										{
											$post_query_logic[] = 'post.postid IN(' . implode(',', $postids) . ')';
										}
									}
								}

								if ($fetchusers)
								{
									$post_query_logic[50] = $fetchusers;
								}
							}

							// check that we don't have only NOT words
							if (empty($words['AND']) AND empty($words['OR']) AND !empty($words['NOT']) AND empty($errors))
							{
								// user has ONLY specified a 'NOT' word... this would be bad
								$errors[] = array('searchnoresults', $displayCommon);
							}

							if (empty($errors))
							{
								($hook = vBulletinHook::fetch_hook('search_process_postindex')) ? eval($hook) : false;
							}

						}
					}
					else
					{
						// Fulltext ...
						foreach ($queryWords AS $word => $wordtype)
						{
							// Need something here to strip odd characters out of words that fulltext is probably not indexing

							$queryword = preg_replace_callback(
								'#"([^"]+)"#si',
								function($matches)
								{
									return stripslashes(str_replace(' ', '*', $matches[0]));
								},
								$word
							);

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
							//$userid_index = " USE INDEX (threadid)";
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
						if (!($fperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($fperms & $vbulletin->bf_ugp_forumpermissions['cansearch']) OR !verify_forum_password($forumid, $vbulletin->forumcache["$forumid"]['password'], false) OR !($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['indexposts']))
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
					// this means we are searching just on username/tag...
				}
			}
			else if ($vbulletin->GPC['searchthreadid'])
			{
				if ($vbulletin->options['fulltextsearch'])
				{
					$thread_query_logic[] = "thread.threadid = " . $vbulletin->GPC['searchthreadid'];
					//$userid_index = " USE INDEX (threadid)";
				}
				else
				{
					$requiredposts = array();
					$q = "
						SELECT postid FROM " . TABLE_PREFIX . "post
						WHERE threadid = " . $vbulletin->GPC['searchthreadid'] . "
					";
					$posts = $db->query_read_slave($q);
					if ($db->num_rows($posts) == 0)
					{
						$errors[] = array('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink']);
					}
					else
					{
						while ($post = $db->fetch_array($posts))
						{
							$requiredposts[] = $post['postid'];
						}
						unset($post);
					}
					$db->free_result($posts);

					if (!empty($requiredposts))
					{
						$post_query_logic[] = "post.postid IN(" . implode(',', $requiredposts) . ")";
					}
				}
			}

			if (empty($errors))
			{
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

				// match prefixes
				if ($prefixchoice)
				{
					$prefix_sql = array();
					foreach (explode(',', $prefixchoice) AS $prefixid)
					{
						if ($prefixid == '-1')
						{
							// no prefix
							$prefix_sql[] = "''";
						}
						else
						{
							$prefix_sql[] = "'" . $db->escape_string($prefixid) . "'";
						}
					}
					$thread_query_logic[] = "thread.prefixid IN (" . implode(',', $prefix_sql) . ")";
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

					// #############################################################################
					$threadids = array();
					$thread_select_logic = array();

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

					$Coventry = array();

					if (!empty($post_query_logic))
					{
						// don't retrieve tachy'd posts/threads
						require_once(DIR . '/includes/functions_bigthree.php');
						if ($Coventry = fetch_coventry())
						{
							$thread_select_logic[] = "thread.forumid, post.userid";
						}
					}

					require_once(DIR . '/includes/functions_forumlist.php');
					cache_moderators();

					if ((!empty($post_query_logic) OR !empty($post_join_query_logic)))
					{
						$hidden = array();
						$deleted = array();
						$allhidden = true;
						$alldeleted = true;
						foreach($vbulletin->forumcache AS $forumid => $forum)
						{
							if (can_moderate($forumid))
							{
								$deleted["$forumid"] = $forumid;
							}
							else
							{
								$alldeleted = false;
							}
							if (can_moderate($forumid, 'canmoderateposts'))
							{
								$hidden["$forumid"] = $forumid;
							}
							else
							{
								$allhidden = false;
							}
						}
						$modlogic = array();
						if (!empty($hidden) OR !empty($deleted))
						{
							if (!$allhidden AND !$alldeleted)
							{
								if ($allhidden)
								{
									$modlogic[] = "post.visible IN (0,1)";
								}
								else if ($alldeleted)
								{
									$modlogic[] = "post.visible IN (1,2)";
								}
								else
								{
									$modlogic[] = "post.visible = 1";
								}

								if (!$allhidden AND !empty($hidden))
								{
									$modlogic[] = "(post.visible = 0 AND forumid IN (" . implode(',', $hidden) . "))";
								}

								if (!$alldeleted AND !empty($deleted))
								{
									$modlogic[] = "(post.visible = 2 AND forumid IN (" . implode(',', $deleted) . "))";
								}

								$querylogic[] = "(" . implode(" OR ", $modlogic) . ")";
							}
						}
						else
						{
							$querylogic[] = "post.visible = 1";
						}
					}

					$threads = $db->query_read_slave("
						SELECT
						" . implode(', ', $thread_select_logic) . "
						FROM " . TABLE_PREFIX . "thread AS thread $userid_index
						$tag_join
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
						if (!can_moderate($thread['forumid']) AND in_array($thread['userid'], $Coventry))
						{
							continue;
						}

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

					if (!empty($itemids))
					{
						if ($vbulletin->GPC['sortby'] == 'rank')
						{
							foreach ($itemids AS $threadid => $thread)
							{
								$itemscores["$threadid"] = fetch_search_item_score($thread, $threadscores["$thread[threadid]"]);
							}
						}

						unset($postscores);
					}

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
						#$querylogic[] = $thread_query_logic[] = "post.postid = thread.firstpostid";
					}

					$do_thread_join = (!empty($thread_query_logic) OR !empty($tag_join) OR ($vbulletin->GPC['sortby'] == 'rank' AND !$rank_select_logic));

					$posts = $db->query_read_slave("
						SELECT postid, post.dateline
						" . iif($vbulletin->GPC['sortby'] == 'rank' AND !$rank_select_logic, ', IF(thread.views=0, thread.replycount+1, thread.views) as views, thread.replycount, thread.votenum, thread.votetotal') . "
						" . (!empty($rank_select_logic) ? ", $rank_select_logic" : "") . "
						FROM " . TABLE_PREFIX . "post AS post $userid_index
						" . ($do_thread_join ? "INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)" : '') . "
						$tag_join
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
					$db->free_result($posts);

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
						$errors[] = array('searchnoresults', $displayCommon);
					}
					else
					{
						// add in date scores
						fetch_search_date_scores($datescores, $itemscores, $mindate, $maxdate);

						// sort the score results
						$sortfunc = iif($vbulletin->GPC['sortorder'] == 'asc', 'asort', 'arsort');
						$sortfunc($itemscores);

						// create the final result set
						$orderedids = array_keys($itemscores);
					}
				}
				// sort by database field
				else
				{
					if (empty($itemids))
					{
						$errors[] = array('searchnoresults', $displayCommon);
					}
					else
					{
						// remove dupes and make query condition
						$itemids = iif($vbulletin->GPC['showposts'], 'postid', 'threadid') . ' IN(' . implode(',', array_keys($itemids)) . ')';

						// sort the results and create the final result set
						$orderedids = sort_search_items($itemids, $vbulletin->GPC['showposts'], $vbulletin->GPC['sortby'], $vbulletin->GPC['sortorder']);
					}
				}

				// #############################################################################
				// end sort the results into order
				// #############################################################################

				if (empty($errors))
				{
					// get rid of unwanted gubbins
					unset($itemids, $threadids, $postids, $postscores, $threadscores, $itemscores, $datescores);

					// final check to see if we've actually got some results
					if (empty($orderedids))
					{
						$errors[] = array('searchnoresults', $displayCommon);
					}
					else
					{
						// #############################################################################
						// finish search timer
						$searchtime = number_format(fetch_microtime_difference($searchstart), 5, '.', '');

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
							$display['words'] = preg_replace_callback(
								'#"([^"]+)"#si',
								function($matches)
								{
									return stripslashes(str_replace('*', ' ', $matches[0]));
								},
								$display['words']
							);
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
							REPLACE INTO " . TABLE_PREFIX . "search
								(userid, titleonly, ipaddress, personal, query, searchuser, forumchoice, prefixchoice, sortby, sortorder, searchtime, showposts, orderedids, dateline, searchterms, displayterms, searchhash, completed)
							VALUES
								(" . $vbulletin->userinfo['userid'] . ",
								" . intval($vbulletin->GPC['titleonly']) . ",
								'" . $db->escape_string(IPADDRESS) . "',
								" . ($vbulletin->options['searchsharing'] ? 0 : 1) . ",
								'" . $db->escape_string($vbulletin->GPC['query']) . "',
								'" . $db->escape_string($vbulletin->GPC['searchuser']) . "',
								'" . $db->escape_string($forumchoice) . "',
								'" . $db->escape_string($prefixchoice) . "',
								'" . $db->escape_string($vbulletin->GPC['sortby']) . "',
								'" . $db->escape_string($vbulletin->GPC['sortorder']) . "',
								$searchtime, " . intval($vbulletin->GPC['showposts']) . ",
								'" . implode(',', $orderedids) . "',
								" . time() . ",
								'" . $db->escape_string(serialize($searchterms)) . "',
								'" . $db->escape_string(serialize($display)) . "',
								'" . $db->escape_string($searchhash) . "',
								1)
						");
						$searchid = $db->insert_id();

						$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . "searchid=$searchid";
						eval(print_standard_redirect('search'));
					}
				}
			}
		}
	}

	$_REQUEST['do'] = 'intro';
}

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

	if (!empty($errors))
	{
		if ($url = fetch_titleonly_url($searchterms))
		{
			$errors[] = 'your_search_included_threads';
		}
		$errorlist = '';
		foreach(array_map('fetch_error', $errors) AS $error)
		{
			$errorlist .= "<li>$error</li>";
		}
		$show['errors'] = true;
	}
	else
	{
		$show['errors'] = false;
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
		$prefs = array_merge($prefs, vb_unserialize($vbulletin->userinfo['searchprefs']));
	}

	// if $forumid is specified, use it
	if (!empty($foruminfo['forumid']))
	{
		$vbulletin->GPC['forumchoice'][] = $foruminfo['forumid'];
	}

	// if search conditions are specified in the URI, use them
	foreach (array_keys($globals) AS $varname)
	{
		if ($vbulletin->GPC_exists["$varname"] AND $varname != 'forumchoice' AND $varname != 'prefixchoice' AND $varname != 'humanverify')
		{
			$prefs["$varname"] = $vbulletin->GPC["$varname"];
		}
	}

	if ($vbulletin->GPC['searchthreadid'])
	{
		$show['searchthread'] = true;
		$threadinfo = verify_id('thread', $vbulletin->GPC['searchthreadid'], true, true);
		$searchid = $threadinfo['threadid'];

		// check for visible / deleted thread
		if ((in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid'])) OR $threadinfo['open'] == 10 OR ((!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts'))) OR ($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'])))
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
		}


		$foruminfo = fetch_foruminfo($threadinfo['forumid']);
		// *********************************************************************************
		// check forum permissions
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		{
			print_no_permission();
		}
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
		{
			print_no_permission();
		}

		// *********************************************************************************
		// check if there is a forum password and if so, ensure the user has it set
		verify_forum_password($threadinfo['forumid'], $foruminfo['password']);
	}

	if ($_POST['do'] == 'process')
	{
		if (empty($vbulletin->GPC['exactname']))
		{
			$prefs['exactname'] = 0;
		}
		if (empty($vbulletin->GPC['childforums']))
		{
			$prefs['childforums'] = 0;
		}
	}

	// now check appropriate boxes, select menus etc...
	$formdata = array();
	foreach ($prefs AS $varname => $value)
	{
		$formdata["$varname"] = $$varname = htmlspecialchars_uni($value);
		$checkedvar = $varname . 'checked';
		$selectedvar = $varname . 'selected';
		$formdata["$checkedvar"] = $$checkedvar = array($value => 'checked="checked"');
		$formdata["$selectedvar"] = $$selectedvar = array($value => 'selected="selected"');
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

	// build prefix options
	$prefixsets = array();

	$prefixes_sql = $db->query_read("
		SELECT prefix.prefixsetid, prefix.prefixid, forumprefixset.forumid
		FROM " . TABLE_PREFIX . "prefix AS prefix
		INNER JOIN " . TABLE_PREFIX . "prefixset AS prefixset ON (prefixset.prefixsetid = prefix.prefixsetid)
		INNER JOIN " . TABLE_PREFIX . "forumprefixset AS forumprefixset ON
			(forumprefixset.prefixsetid = prefixset.prefixsetid)
		ORDER BY prefixset.displayorder, prefix.displayorder
	");
	while ($prefix = $db->fetch_array($prefixes_sql))
	{
		$forumperms =& $vbulletin->userinfo['forumpermissions']["$prefix[forumid]"];
		if (($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
			AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['cansearch'])
			AND verify_forum_password($prefix['forumid'], $vbulletin->forumcache["$prefix[forumid]"]['password'], false)
		)
		{
			$prefixsets["$prefix[prefixsetid]"]["$prefix[prefixid]"] = $prefix['prefixid'];
		}
	}

	$prefix_options = '';
	foreach ($prefixsets AS $prefixsetid => $prefixes)
	{
		$optgroup_options = '';
		foreach ($prefixes AS $prefixid)
		{
			$optionvalue = $prefixid;
			$optiontitle = htmlspecialchars_uni($vbphrase["prefix_{$prefixid}_title_plain"]);
			$optionselected = (in_array($prefixid, $vbulletin->GPC['prefixchoice']) ? ' selected="selected"' : '');
			$optionclass = '';

			eval('$optgroup_options .= "' . fetch_template('option') . '";');
		}

		// if there's only 1 prefix set available, we don't want to show the optgroup
		if (sizeof($prefixsets) > 1)
		{
			$optgroup_label = htmlspecialchars_uni($vbphrase["prefixset_{$prefixsetid}_title"]);
			eval('$prefix_options .= "' . fetch_template('optgroup') . '";');
		}
		else
		{
			$prefix_options = $optgroup_options;
		}
	}

	$prefix_selected = array(
		'any' => ((in_array('', $vbulletin->GPC['prefixchoice']) OR empty($vbulletin->GPC['prefixchoice'])) ? ' selected="selected"' : ''),
		'none' => (in_array('-1', $vbulletin->GPC['prefixchoice']) ? ' selected="selected"' : '')
	);

	$show['tag_option'] = $vbulletin->options['threadtagging'];

	// image verification
	if (fetch_require_hvcheck('search'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verification = vB_HumanVerify::fetch_library($vbulletin);
		$human_verify = $verification->output_token();
	}
	else
	{
		$human_verify = '';
	}

	if ($vbulletin->debug)
	{
		$show['nocache'] = true;
	}

	// tag cloud display
	if ($vbulletin->options['threadtagging'] == 1 AND $vbulletin->options['tagcloud_searchcloud'] == 1)
	{
		$tag_cloud = fetch_tagcloud('search');
		if ($tag_cloud)
		{
			eval('$tag_cloud_headinclude .= "' . fetch_template('tag_cloud_headinclude') . '";');
		}
	}
	else
	{
		$tag_cloud = '';
		$tag_cloud_headinclude = '';
	}

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
if ($_REQUEST['do'] == 'showresults')
{
	require_once(DIR . '/includes/functions_forumdisplay.php');

	$vbulletin->input->clean_array_gpc('r',  array(
		'pagenumber' => TYPE_INT,
		'perpage'    => TYPE_INT
	));

	// check for valid search result
	$gotsearch = false;
	if ($search =  $db->query_first("SELECT * FROM " . TABLE_PREFIX . "search AS search WHERE completed = 1 AND searchid = " . $vbulletin->GPC['searchid']))
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
	$searchterms = vb_unserialize($search['searchterms']);
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
			else if ($value !== '')
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
	$display = vb_unserialize($search['displayterms']);

	// $orderedids contains an ORDERED list of matching postids/threadids
	// EXCLUDING invisible and deleted items
	if (empty($search['orderedids']))
	{
		$orderedids = array('0');
	}
	else
	{
		$orderedids = explode(',', $search['orderedids']);
	}
	$numitems = sizeof($orderedids);

	// #############################################################################
	// #############################################################################

	// start the timer for the permissions check
	$go = microtime();

	// #############################################################################
	// don't retrieve tachy'd posts/threads
	require_once(DIR . '/includes/functions_bigthree.php');

	// query moderators for forum password purposes (and inline moderation)
	if ($vbulletin->userinfo['userid'])
	{
		cache_moderators();
	}

	// now check to see if the results can be viewed / searched etc.
	if ($search['showposts'])
	{
		// query posts
		$permQuery = "
			SELECT postid AS itemid, post.visible AS post_visible, thread.visible AS thread_visible, thread.forumid, thread.threadid, thread.postuserid, post.userid,
			IF(postuserid = " . $vbulletin->userinfo['userid'] . ", 'self', 'other') AS starter
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE postid IN(" . implode(', ', $orderedids) . ")
			AND thread.open <> 10
		";

		$hook_query_fields = $hook_query_joins = '';
		($hook = vBulletinHook::fetch_hook('search_results_query_posts')) ? eval($hook) : false;

		// query post data
		$dataQuery = "
			SELECT post.postid, post.title AS posttitle, post.dateline AS postdateline,
				post.iconid AS posticonid, post.pagetext, post.visible, post.attach,
				IF(post.userid = 0, post.username, user.username) AS username,
				thread.threadid, thread.title AS threadtitle, thread.iconid AS threadiconid, thread.replycount,
				IF(thread.views=0, thread.replycount+1, thread.views) as views, thread.firstpostid, thread.prefixid, thread.taglist,
				thread.pollid, thread.sticky, thread.open, thread.lastpost, thread.forumid, thread.visible AS thread_visible,
				user.userid
				" . (can_moderate() ? ",pdeletionlog.userid AS pdel_userid, pdeletionlog.username AS pdel_username, pdeletionlog.reason AS pdel_reason" : "") . "
				" . (can_moderate() ? ",tdeletionlog.userid AS tdel_userid, tdeletionlog.username AS tdel_username, tdeletionlog.reason AS tdel_reason" : "") . "
				" . iif($vbulletin->userinfo['userid'], ', threadread.readtime AS threadread') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)

			" . (can_moderate() ?
			"LEFT JOIN " . TABLE_PREFIX . "deletionlog AS tdeletionlog ON(thread.threadid = tdeletionlog.primaryid AND tdeletionlog.type = 'thread')
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS pdeletionlog ON(post.postid = pdeletionlog.primaryid AND pdeletionlog.type = 'post')"
				: "") . "

			" . iif($vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")") . "

			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
			$hook_query_joins
			WHERE post.postid IN";
	}
	else
	{
		// query threads
		$permQuery = "
			SELECT threadid AS itemid, forumid, visible AS thread_visible, postuserid,
			IF(postuserid = " . $vbulletin->userinfo['userid'] . ", 'self', 'other') AS starter
			FROM " . TABLE_PREFIX . "thread AS thread
			WHERE threadid IN(" . implode(', ', $orderedids) . ")
			AND thread.open <> 10
		";

		if ($vbulletin->options['threadpreview'] > 0)
		{
			$previewfield = "post.pagetext AS preview,";
			$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
		}
		else
		{
			$previewfield = "";
			$previewjoin = "";
		}

		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
		{
			$tachyjoin = "
				LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON
					(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "tachythreadcounter AS tachythreadcounter ON
					(tachythreadcounter.threadid = thread.threadid AND tachythreadcounter.userid = " . $vbulletin->userinfo['userid'] . ")
			";

			$tachycolumns = '
				IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount) AS replycount,
				IF(thread.views<=IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount), IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount)+1, thread.views) AS views,
				IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost,
				IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter,
				IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid
			';
		}
		else
		{
			$tachyjoin = '';

			$tachycolumns = '
				thread.replycount, IF(thread.views<=thread.replycount, replycount+1, thread.views) AS views,
				thread.lastpost, thread.lastposter, thread.lastpostid
			';
		}

		$hook_query_fields = $hook_query_joins = "";
		($hook = vBulletinHook::fetch_hook('search_results_query_threads')) ? eval($hook) : false;

		// query thread data
		$dataQuery = "
			SELECT $previewfield
				thread.threadid, thread.threadid AS postid, thread.title AS threadtitle, thread.iconid AS threadiconid, thread.dateline, thread.forumid,
				thread.sticky, thread.prefixid, thread.taglist, thread.pollid, thread.open, thread.lastpost AS postdateline, thread.visible,
				thread.hiddencount, thread.deletedcount, thread.attach, thread.postusername, thread.forumid,
				$tachycolumns,
				" . (can_moderate() ? "deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason," : "") . "
				user.userid AS postuserid
				" . iif($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid'], ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed") . "
				" . iif($vbulletin->userinfo['userid'], ', threadread.readtime AS threadread') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = thread.postuserid)

			" . (can_moderate() ? "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND deletionlog.type = 'thread')" : "") . "
			" . iif($vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")") . "
			" . iif($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread
				ON(subscribethread.threadid = thread.threadid AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . " AND canview = 1)") . "
			$previewjoin
			$tachyjoin
			$hook_query_joins
			WHERE thread.threadid IN
		";
	}

	$Coventry_array = fetch_coventry();

	$tmp = array();
	$items = $db->query_read_slave($permQuery);
	unset($permQuery);
	while ($item = $db->fetch_array($items))
	{
		if (!can_moderate($item['forumid']) AND (in_array($item['userid'], $Coventry_array) OR in_array($item['postuserid'], $Coventry_array)))
		{
			continue;
		}

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

	if ($vbulletin->userinfo['userid'])
	{
		// we need this for forum read times
		cache_ordered_forums(1);
	}

	foreach (array_keys($tmp) AS $forumid)
	{
		$forum =& $vbulletin->forumcache["$forumid"];
		if (!$forum)
		{
			// we don't know anything about this forum
			unset($tmp["$forumid"]);
			continue;
		}

		$fperms = $vbulletin->userinfo['forumpermissions']["$forumid"];

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
				AND $display['options']['action'] != 'getnew' AND $display['options']['action'] != 'getdaily'
			)
		)
		{
			// cannot view / search this forum, or does not have forum password
			unset($tmp["$forumid"]);
		}
		else if (!($fperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) AND ($search['showposts'] OR ($display['options']['action'] != 'getnew' AND $display['options']['action'] != 'getdaily' AND !$search['titleonly'])))
		{
			unset($tmp["$forumid"]);
		}
		else
		{
			if ($vbulletin->userinfo['userid'])
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
	if ($numitems == 0 AND empty($search['announceids']))
	{
		// show the getnew message if there are no results, this might be due to permissions
		if ($display['options']['action'] == 'getnew')
		{
			eval(standard_error(fetch_error('searchnoresults_getnew', $vbulletin->session->vars['sessionurl']), '', false));
		}
		else
		{
			if ($display['options']['action'] != 'getdaily' AND $url = fetch_titleonly_url(vb_unserialize($search['searchterms'])))
			{
				eval(standard_error(fetch_error('searchnoresults_titlesonly', $displayCommon, $url), '', false));
			}
			else
			{
				eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
			}
		}
	}
	else if ($numitems > 0)
	{
		$show['results'] = true;
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
	if (!empty($itemids))
	{
		$ids = implode(', ', array_keys($itemids));
		$dataQuery .= '(' . $ids . ')';
		$items = $db->query_read_slave($dataQuery);
		$itemidname = iif($search['showposts'], 'postid', 'threadid');

		if (!$search['showposts'])
		{
			$dotthreads = fetch_dot_threads_array($ids);
		}
	}

	// end search timer
	$searchtime = vb_number_format(fetch_microtime_difference($searchstart, $search['searchtime']), 2);

	if (!empty($itemids))
	{
		$managepost = $approvepost = $managethread = $approveattachment = $movethread = $deletethread = $approvethread = $openthread = array();
		while ($item = $db->fetch_array($items))
		{
			if ($search['showposts'])
			{
				if (can_moderate($item['forumid'], 'candeleteposts') OR can_moderate($item['forumid'], 'canremoveposts'))
				{
					$managepost["$item[postid]"] = 1;
					$show['managepost'] = true;
				}

				if (can_moderate($item['forumid'], 'canmoderateposts'))
				{
					$approvepost["$item[postid]"] = 1;
					$show['approvepost'] = true;
				}

				if (can_moderate($item['forumid'], 'canmanagethreads'))
				{
					$managethread["$item[postid]"] = 1;
					$show['managethread'] = true;
				}

				if (can_moderate($item['forumid'], 'canmoderateattachments') AND $item['attach'])
				{
					$approveattachment["$item[postid]"] = 1;
					$show['approveattachment'] = true;
				}
			}
			else
			{
				// unset the thread preview if it can't be seen
				$forumperms = fetch_permissions($item['forumid']);
				if ($vbulletin->options['threadpreview'] > 0 AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
				{
					$item['preview'] = '';
				}

				if (can_moderate($item['forumid'], 'canmanagethreads'))
				{
					$movethread["$item[threadid]"] = 1;
					$show['movethread'] = true;
				}

				if (can_moderate($item['forumid'], 'candeleteposts') OR can_moderate($item['forumid'], 'canremoveposts'))
				{
					$deletethread["$item[threadid]"] = 1;
					$show['deletethread'] = true;
				}

				if (can_moderate($item['forumid'], 'canmoderateposts'))
				{
					$approvethread["$item[threadid]"] = 1;
					$show['approvethread'] = true;
				}

				if (can_moderate($item['forumid'], 'canopenclose'))
				{
					$openthread["$item[threadid]"] = 1;
					$show['openthread'] = true;

				}
				if ($vbulletin->forumcache["$item[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'])
				{
					$show['threadicons'] = true;
				}
			}
			$item['forumtitle'] = $vbulletin->forumcache["$item[forumid]"]['title'];
			$itemids["$item[$itemidname]"] = $item;
		}
		unset($item, $dataQuery);
		$db->free_result($items);
	}
	// #############################################################################

	if (!empty($managepost) OR !empty($approvepost) OR !empty($managethread) OR !empty($approveattachment) OR !empty($movethread) OR !empty($deletethread) OR !empty($approvethread) OR !empty($openthread))
	{
		$show['inlinemod'] = true;
		$show['spamctrls'] = ($show['deletethread'] OR $show['managepost']);
		$url = SCRIPTPATH;
	}
	else
	{
		$show['inlinemod'] = false;
		$url = '';
	}

	$threadcolspan = 7;
	$announcecolspan = 6;

	if ($show['inlinemod'])
	{
		$threadcolspan++;
		$announcecolspan++;
	}
	if (!$show['threadicons'])
	{
		$threadcolspan--;
		$announcecolspan--;
	}


	if (!empty($search['announceids']) AND $vbulletin->GPC['pagenumber'] == 1)
	{
		$announcements = $db->query_read_slave("
			SELECT announcementid, startdate, title, announcement.views, forumid,
				user.username, user.userid, user.usertitle, user.customtitle, user.usergroupid,
				IF(user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
			FROM " . TABLE_PREFIX . "announcement AS announcement
			LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
			WHERE announcementid IN ($search[announceids])
			ORDER BY startdate DESC
		");
		while ($announcement = $db->fetch_array($announcements))
		{
			fetch_musername($announcement);
			$announcement['title'] = fetch_censored_text($announcement['title']);
			$announcement['postdate'] = vbdate($vbulletin->options['dateformat'], $announcement['startdate']);
			$announcement['statusicon'] = 'new';
			$announcement['views'] = vb_number_format($announcement['views']);
			$announcementidlink = "&amp;a=$announcement[announcementid]";
			$announcement['forumtitle'] = $vbulletin->forumcache["$announcement[forumid]"]['title'];
			$show['forumtitle'] = ($announcement['forumid'] == -1) ? false : true;

			eval('$announcebits .= "' . fetch_template('threadbit_announcement') . '";');
		}
	}

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
			if ($vbulletin->userinfo['userid'])
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

			$post['original_pagetext'] = $post['pagetext'];
			$strip_quotes = true;

			// php 5.5 fix
			$post['pagetext'] = preg_replace_callback('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siU', 
				function($matches) use ($display)
				{
					return process_quote_removal($matches[3], $display['highlight']);
				}, 
				$post['pagetext']
			);

			// Deal with the case that quote was the only content of the post
			if (trim($post['pagetext']) == '')
			{
				$post['pagetext'] = $post['original_pagetext'];
				$strip_quotes = false;
			}

			// get first 200 chars of page text
			$post['pagetext'] = htmlspecialchars_uni(fetch_censored_text(trim(fetch_trimmed_title(strip_bbcode($post['pagetext'], $strip_quotes), 200))));

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

			$show['disabled'] = ($managethread["$post[postid]"] OR $managepost["$post[postid]"] OR $approvepost["$post[postid]"] OR $approveattachment["$post[postid]"]) ? false : true;

			$show['moderated'] = (!$post['visible'] OR (!$post['thread_visible'] AND $post['postid'] == $post['firstpostid'])) ? true : false;

			if ($post['pdel_userid'])
			{
				$post['del_username'] =& $post['pdel_username'];
				$post['del_userid'] =& $post['pdel_userid'];
				$post['del_reason'] = fetch_censored_text($post['pdel_reason']);
				$post['del_phrase'] = $vbphrase['message_deleted_by_x'];
				$show['deleted'] = true;
			}
			else if ($post['tdel_userid'])
			{
				$post['del_username'] =& $post['tdel_username'];
				$post['del_userid'] =& $post['tdel_userid'];
				$post['del_reason'] = fetch_censored_text($post['tdel_reason']);
				$post['del_phrase'] = $vbphrase['thread_deleted_by_x'];
				$show['deleted'] = true;
			}
			else
			{
				$show['deleted'] = false;
			}

			if ($post['prefixid'])
			{
				$post['prefix_plain_html'] = htmlspecialchars_uni($vbphrase["prefix_$post[prefixid]_title_plain"]);
				$post['prefix_rich'] = $vbphrase["prefix_$post[prefixid]_title_rich"];
			}
			else
			{
				$post['prefix_plain_html'] = '';
				$post['prefix_rich'] = '';
			}

			$itemcount ++;
			exec_switch_bg();

			($hook = vBulletinHook::fetch_hook('search_results_postbit')) ? eval($hook) : false;

			if (($display['options']['action'] == 'getdaily' OR $display['options']['action'] == 'getnew') AND $search['sortby'] == 'lastpost' AND !$oldposts AND $post['postdateline'] <= $vbulletin->userinfo['lastvisit'] AND $vbulletin->userinfo['lastvisit'] != 0)
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
			$show['disabled'] = ($movethread["$thread[threadid]"] OR $deletethread["$thread[threadid]"] OR $approvethread["$thread[threadid]"] OR $openthread["$thread[threadid]"]) ? false : true;

			$itemcount++;
			exec_switch_bg();

			($hook = vBulletinHook::fetch_hook('search_results_threadbit')) ? eval($hook) : false;

			if (($display['options']['action'] == 'getdaily' OR $display['options']['action'] == 'getnew') AND $search['sortby'] == 'lastpost' AND !$oldposts AND $thread['lastpost'] <= $vbulletin->userinfo['lastvisit'] AND $vbulletin->userinfo['lastvisit'] != 0)
			{
				$oldposts = true;
				if ($display['options']['action'] == 'getnew')
				{
					$show['unread_posts'] = true;
				}
				eval('$searchbits .= "' . fetch_template('threadbit_lastvisit') . '";');
			}
			$forumperms = fetch_permissions($thread['forumid']);
			if ($thread['visible'] == 2)
			{
				$thread['deletedcount']++;
				$show['deletereason'] = (!empty($thread['del_reason'])) ?  true : false;
				$show['moderated'] = ($thread['hiddencount'] > 0 AND can_moderate($thread['forumid'], 'canmoderateposts')) ? true : false;
				$show['deletedthread'] = (can_moderate($thread['forumid']) OR $forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice']) ? true : false;
				eval('$searchbits .= "' . fetch_template('threadbit_deleted') . '";');
			}
			else
			{
				if (!$thread['visible'])
				{
					$thread['hiddencount']++;
				}
				$show['moderated'] = ($thread['hiddencount'] > 0 AND can_moderate($thread['forumid'], 'canmoderateposts')) ? true : false;
				$show['deletedthread'] = ($thread['deletedcount'] > 0 AND (can_moderate($thread['forumid']) OR $forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice'])) ? true : false;
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

	if (!empty($display['tag']))
	{
		$display_tag = "<b><u>$display[tag]</u></b>";
	}

	$show['no_prefix'] = false;
	if (!empty($display['prefixes']))
	{
		foreach ($display['prefixes'] AS $key => $prefixid)
		{
			if ($prefixid == '-1')
			{
				$show['no_prefix'] = true;
			}

			if (isset($vbphrase["prefix_{$prefixid}_title_plain"]))
			{
				$display['prefixes']["$key"] = '<b><u>' . htmlspecialchars_uni($vbphrase["prefix_{$prefixid}_title_plain"]) . '</u></b>';
			}
			else
			{
				unset($display['prefixes']["$key"]);
			}
		}
		$display_prefixes = implode(" $vbphrase[or] ", $display['prefixes']);
	}
	else
	{
		$display_prefixes = '';
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

	if ($display['options']['action'] != 'getnew' AND $display['options']['action'] != 'getdaily' AND $titlesearchurl = fetch_titleonly_url(vb_unserialize($search['searchterms'])))
	{
		$show['titleonlysearch'] = true;
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
		'days'       => TYPE_UINT,
		'exclude'    => TYPE_NOHTML,
		'include'    => TYPE_NOHTML,
		'showposts'  => TYPE_BOOL,
		'sortby'     => TYPE_NOHTML,
		'noannounce' => TYPE_BOOL,
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
		'words'     => array(),
		'highlight' => array(),
		'common'    => array(),
		'users'     => array(),
		'forums'    => $display['forums'],
		'options'   => array(
			'starteronly' => false,
			'childforums' => true,
			'action'      => $_REQUEST['do']
		),
	);

	($hook = vBulletinHook::fetch_hook('search_getnew_display')) ? eval($hook) : false;

	// get moderator cache for forum password purposes
	if ($vbulletin->userinfo['userid'])
	{
		cache_moderators();
	}

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

	if ($_REQUEST['do'] == 'getnew' AND $vbulletin->userinfo['userid'])
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
		$posts = $db->query_read_slave("
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
		$threads = $db->query_read_slave("
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

	$announcementids = array();
	if ($vbulletin->userinfo['userid'])
	{
		$basetime = TIMENOW;
		$mindate = $basetime - 2592000; // 30 days
		$announcements = $db->query_read_slave("
			SELECT announcement.announcementid
			FROM " . TABLE_PREFIX . "announcement AS announcement
			LEFT JOIN " . TABLE_PREFIX . "announcementread AS ar ON (announcement.announcementid = ar.announcementid AND ar.userid = " . $vbulletin->userinfo['userid'] . ")
			WHERE
				ISNULL(ar.userid) AND
				startdate < $basetime AND
				startdate > $mindate AND
				enddate > $basetime AND
				forumid IN(-1, " . implode(', ', $forumids) . ")
		");
		while ($announcement = $db->fetch_array($announcements))
		{
			$announcementids[] = $announcement['announcementid'];
		}
	}

	if (empty($orderedids) AND empty($announcementids))
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
	$sql_aids = $db->escape_string(implode(',', $announcementids));
	unset($orderedids, $announcementids);

	// check for previous searches
	if ($search = $db->query_first("SELECT searchid FROM " . TABLE_PREFIX . "search AS search WHERE userid = " . $vbulletin->userinfo['userid'] . " AND searchhash = '" . $db->escape_string($searchhash) . "' AND orderedids = '$sql_ids' AND announceids = '$sql_aids' AND completed = 1"))
	{
		// search has been done previously
		$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . "searchid=$search[searchid]";
		eval(print_standard_redirect('redirect_search'));
	}

	// end search timer
	$searchtime = number_format(fetch_microtime_difference($searchtime), 5, '.', '');

	($hook = vBulletinHook::fetch_hook('search_getnew_complete')) ? eval($hook) : false;

	/*insert query*/
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "search (userid, showposts, ipaddress, personal, forumchoice, sortby, sortorder, searchtime, orderedids, announceids, dateline, displayterms, searchhash, completed)
		VALUES (" . $vbulletin->userinfo['userid'] . ", " . intval($vbulletin->GPC['showposts']) . ", '" . $db->escape_string(IPADDRESS) . "', 1, '" . $db->escape_string($foruminfo['forumid']) . "', '" . $db->escape_string($vbulletin->GPC['sortby']) . "', 'DESC', $searchtime, '$sql_ids', '$sql_aids', " . TIMENOW . ", '" . $db->escape_string(serialize($display)) . "', '" . $db->escape_string($searchhash) . "', 1)
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
		'starteronly'    => TYPE_BOOL,
		'forumchoice'    => TYPE_ARRAY,
		'childforums'    => TYPE_BOOL,
		'searchthreadid' => TYPE_UINT,
	));

	// valid user id?
	if (!$vbulletin->GPC['userid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['user'], $vbulletin->options['contactuslink'])));
	}

	// get user info
	if ($user = $db->query_first_slave("SELECT userid, username, posts FROM " . TABLE_PREFIX . "user WHERE userid = " . $vbulletin->GPC['userid']))
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
	$starteronly = ($vbulletin->GPC['starteronly'] ? 1 : 0);
	$forumchoice = $foruminfo['forumid'];
	$childforums = 1;
	$titleonly = 0;
	$showposts = ($vbulletin->GPC['starteronly'] ? 0 : 1);
	$searchdate = 0;
	$beforeafter = 'after';
	$replyless = 0;
	$replylimit = 0;
	$searchthreadid = $vbulletin->GPC['searchthreadid'];

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

	$forumids = array();
	$noforumids = array();
	// #############################################################################
	// check to see if we should be searching in a particular forum or forums
	if ($vbulletin->GPC['searchthreadid'])
	{
		$showforums = false;
		$sql = "AND thread.threadid = " . $vbulletin->GPC['searchthreadid'];
	}
	else
	{
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
			$sql = "AND thread.forumid IN(" . implode(',', $forumids) . ")";
		}
	}

	// query post ids in dateline DESC order...
	$orderedids = array();
	if ($starteronly)
	{
		$threads = $db->query_read_slave("
			SELECT thread.threadid
			FROM " . TABLE_PREFIX . "thread AS thread
			WHERE thread.postuserid = $user[userid]
				$sql
			ORDER BY lastpost DESC
			LIMIT " . ($vbulletin->options['maxresults'] * 2) . "
		");
		while ($thread = $db->fetch_array($threads))
		{
			$orderedids[] = $thread['threadid'];
		}
	}
	else
	{
		$posts = $db->query_read_slave("
			SELECT postid
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE post.userid = $user[userid]
				$sql
			ORDER BY post.dateline DESC
			LIMIT " . ($vbulletin->options['maxresults'] * 2) . "
		");
		while ($post = $db->fetch_array($posts))
		{
			$orderedids[] = $post['postid'];
		}
		$db->free_result($posts);
	}

	// did we get some results?
	if (empty($orderedids))
	{
		eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
	}

	// set display terms
	$display = array(
		'words'     => array(),
		'highlight' => array(),
		'common'    => array(),
		'users'     => array($user['userid'] => $user['username']),
		'forums'    => iif($showforums, $display['forums'], 0),
		'options'   => array(
			'starteronly' => $starteronly,
			'childforums' => 1,
			'action'      => 'process'
		)
	);

	// end search timer
	$searchtime = number_format(fetch_microtime_difference($searchtime), 5, '.', '');

	$sort_order = ($showposts ? 'post.dateline' : 'lastpost');

	($hook = vBulletinHook::fetch_hook('search_finduser_complete')) ? eval($hook) : false;

	/*insert query*/
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "search
			(userid, ipaddress, personal,
			searchuser, forumchoice,
			sortby, sortorder, searchtime,
			showposts, orderedids, dateline,
			displayterms, searchhash, completed)
		VALUES
			(" . $vbulletin->userinfo['userid'] . ", '" . $db->escape_string(IPADDRESS) . "', 1,
			'" . $db->escape_string($user['username']) . "', '" . $db->escape_string($forumchoice) . "',
			'$sort_order', 'DESC', $searchtime,
			$showposts, '" . $db->escape_string(implode(',', $orderedids)) . "', " . TIMENOW . ",
			'" . $db->escape_string(serialize($display)) . "', '" . $db->escape_string($searchhash) . "', 1)
	");
	$searchid = $db->insert_id();

	$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'] . "searchid=$searchid";
	eval(print_standard_redirect('search'));

}

// #############################################################################
if ($_POST['do'] == 'doprefs')
{
	$vbulletin->input->clean_array_gpc('p',
		$globals
	);

	if ($vbulletin->userinfo['userid'])
	{
		// save preferences
		if ($vbulletin->GPC['saveprefs'])
		{
			$prefs = array(
				'exactname'   => $vbulletin->GPC['exactname'],
				'starteronly' => $vbulletin->GPC['starteronly'],
				'childforums' => $vbulletin->GPC['childforums'],
				'showposts'   => $vbulletin->GPC['showposts'],
				'titleonly'   => $vbulletin->GPC['titleonly'],
				'searchdate'  => $vbulletin->GPC['searchdate'],
				'beforeafter' => $vbulletin->GPC['beforeafter'],
				'sortby'      => $vbulletin->GPC['sortby'],
				'sortorder'   => $vbulletin->GPC['sortorder'],
				'replyless'   => $vbulletin->GPC['replyless'],
				'replylimit'  => $vbulletin->GPC['replylimit'],
				'searchtype'  => $vbulletin->GPC['searchtype'],
			);

			// init user data manager
			$userdata = datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
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
			$userdata = datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
			$userdata->set_existing($vbulletin->userinfo);
			$userdata->set('searchprefs', '');

			($hook = vBulletinHook::fetch_hook('search_doprefs_process')) ? eval($hook) : false;

			$userdata->save();

			$clearprefs = true;
		}
	}

	$vbulletin->url = 'search.php?' . $vbulletin->session->vars['sessionurl'];
	if (!empty($globals))
	{
		foreach (array_keys($globals) AS $varname)
		{
			if (is_array($vbulletin->GPC["$varname"]))
			{
				foreach ($vbulletin->GPC["$varname"] AS $_cleanme)
				{
					$vbulletin->url .= $varname . '[]=' . urlencode($_cleanme) . '&amp;';
				}
			}
			else
			{
				$vbulletin->url .= $varname . '=' . urlencode($vbulletin->GPC["$varname"]) . '&amp;';
			}
		}
		$vbulletin->url = substr($vbulletin->url, 0, -5);
	}

	($hook = vBulletinHook::fetch_hook('search_doprefs_complete')) ? eval($hook) : false;

	if (!$vbulletin->GPC['ajax'])
	{
		eval(print_standard_redirect($clearprefs ? 'search_preferencescleared' : 'search_preferencessaved', true, true));
	}
	else
	{
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_tag('message', fetch_phrase($clearprefs ? 'redirect_search_preferencescleared' : 'redirect_search_preferencessaved', 'frontredirect', 'redirect_'));
		$xml->print_xml();
	}
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
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
